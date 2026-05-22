<?php

namespace Tests\Feature\Alunos;

use App\Filament\Admin\Resources\Alunos\Pages\ListAlunos;
use App\Jobs\ImportAlunosMatriculadosJob;
use App\Models\Aluno;
use App\Models\Escola;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use App\Services\Alunos\AlunoImportacaoSpreadsheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AlunoImportacaoSpreadsheetServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_importa_aba_matriculados_criando_serie_turma_e_aluno(): void
    {
        Storage::fake('local');

        Escola::query()->create([
            'nome' => 'CMEI - Cecilia Meireles',
            'ativo' => true,
        ]);

        $caminho = $this->criarPlanilhaNoStorage('local', [
            ['Escola', 'Seriacao', 'Turma', 'Turno', 'Curso', 'CGM', 'Nome do Aluno', 'Data de Nasc', 'Idade', 'Sexo', 'Telefone', 'RG', 'Situacao', 'Data Matricula'],
            ['CMEI - Cecilia Meireles', 'INFANTIL 4', 'A', 'Manha', 'EDUC INFANTIL', '1035708266', 'ALANA GRAZIELY DA SILVA SARAIVA', 44600, '4', 'F', null, null, 'Matriculado', 46058],
        ]);

        $resultado = app(AlunoImportacaoSpreadsheetService::class)->importar($caminho, null, 'local');

        $this->assertSame(1, $resultado['total_importado']);
        $this->assertSame(1, $resultado['series_criadas']);
        $this->assertSame(1, $resultado['turmas_criadas']);

        $this->assertDatabaseHas('series', [
            'nome' => 'INFANTIL 4',
        ]);

        $serie = Serie::query()->where('nome', 'INFANTIL 4')->firstOrFail();
        $turma = Turma::query()->where('id_serie', $serie->id)->firstOrFail();

        $this->assertSame('A', $turma->nome);
        $this->assertSame('manha', $turma->turno);

        $this->assertDatabaseHas('alunos', [
            'cgm' => '1035708266',
            'nome' => 'ALANA GRAZIELY DA SILVA SARAIVA',
            'id_turma' => $turma->id,
            'status' => Aluno::STATUS_MATRICULADO,
            'sexo' => 'F',
        ]);

        $aluno = Aluno::query()->where('cgm', '1035708266')->firstOrFail();

        $this->assertSame(
            '2022-02-08',
            $aluno->data_nascimento?->toDateString()
        );
        $this->assertSame(
            '2026-02-05',
            $aluno->data_matricula?->toDateString()
        );
    }

    public function test_importacao_ignora_repeticoes_de_cgm_no_arquivo(): void
    {
        Storage::fake('local');

        Escola::query()->create([
            'nome' => 'Escola Municipal Teste',
            'ativo' => true,
        ]);

        $caminho = $this->criarPlanilhaNoStorage('local', [
            ['Escola', 'Seriacao', 'Turma', 'Turno', 'CGM', 'Nome do Aluno', 'Data de Nascimento', 'Sexo', 'Data Matricula'],
            ['Escola Municipal Teste', '1 Ano', 'A', 'Tarde', '123', 'Aluno Um', '01/02/2018', 'M', '05/02/2026'],
            ['Escola Municipal Teste', '1 Ano', 'B', 'Tarde', '123', 'Aluno Dois', '02/02/2018', 'M', '05/02/2026'],
        ]);

        $resultado = app(AlunoImportacaoSpreadsheetService::class)->importar($caminho, null, 'local');

        $this->assertSame(1, $resultado['total_importado']);
        $this->assertSame(1, $resultado['duplicados_ignorados']);
        $this->assertDatabaseCount('alunos', 1);
        $this->assertDatabaseHas('alunos', [
            'cgm' => '123',
            'nome' => 'Aluno Um',
        ]);
    }

    public function test_importa_aluno_sem_data_de_matricula(): void
    {
        Storage::fake('local');

        Escola::query()->create([
            'nome' => 'Escola Municipal Teste',
            'ativo' => true,
        ]);

        $caminho = $this->criarPlanilhaNoStorage('local', [
            ['Escola', 'Seriacao', 'Turma', 'Turno', 'CGM', 'Nome do Aluno', 'Data de Nascimento', 'Sexo', 'Data Matricula'],
            ['Escola Municipal Teste', '1 Ano', 'A', 'Tarde', '456', 'Aluno Sem Data', '01/02/2018', 'F', null],
        ]);

        $resultado = app(AlunoImportacaoSpreadsheetService::class)->importar($caminho, null, 'local');

        $this->assertSame(1, $resultado['total_importado']);

        $aluno = Aluno::query()->where('cgm', '456')->firstOrFail();

        $this->assertNull($aluno->data_matricula);
    }

    public function test_importa_aluno_sem_coluna_de_data_de_matricula(): void
    {
        Storage::fake('local');

        Escola::query()->create([
            'nome' => 'Escola Municipal Teste',
            'ativo' => true,
        ]);

        $caminho = $this->criarPlanilhaNoStorage('local', [
            ['Escola', 'Seriacao', 'Turma', 'Turno', 'CGM', 'Nome do Aluno', 'Data de Nascimento', 'Sexo'],
            ['Escola Municipal Teste', '1 Ano', 'A', 'Tarde', '789', 'Aluno Sem Coluna', '01/02/2018', 'M'],
        ]);

        $resultado = app(AlunoImportacaoSpreadsheetService::class)->importar($caminho, null, 'local');

        $this->assertSame(1, $resultado['total_importado']);

        $aluno = Aluno::query()->where('cgm', '789')->firstOrFail();

        $this->assertNull($aluno->data_matricula);
    }

    public function test_acao_de_importacao_enfileira_processamento_sem_importar_no_request(): void
    {
        Queue::fake();
        Storage::fake('local');

        Permission::findOrCreate('Criar Alunos');
        Permission::findOrCreate('Listar Alunos');
        Permission::findOrCreate('Importar Alunos por Planilha');

        Escola::query()->create([
            'nome' => 'Escola Municipal Teste',
            'ativo' => true,
        ]);

        $caminho = $this->criarPlanilhaNoStorage('local', [
            ['Escola', 'Seriacao', 'Turma', 'Turno', 'CGM', 'Nome do Aluno', 'Data de Nascimento', 'Sexo'],
            ['Escola Municipal Teste', '1 Ano', 'A', 'Tarde', '321', 'Aluno Em Fila', '01/02/2018', 'M'],
        ]);

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo(['Criar Alunos', 'Listar Alunos', 'Importar Alunos por Planilha']);

        Livewire::actingAs($user)
            ->test(ListAlunos::class)
            ->callAction('importarMatriculados', [
                'arquivo' => [$caminho],
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseCount('alunos', 0);
        $this->assertDatabaseHas('export_requests', [
            'user_id' => $user->id,
            'type' => 'alunos_importacao_planilha',
            'format' => 'processo',
            'status' => 'queued',
        ]);

        Queue::assertPushed(ImportAlunosMatriculadosJob::class, function (ImportAlunosMatriculadosJob $job) use ($caminho, $user): bool {
            return $job->caminhoArquivo === $caminho
                && $job->usuarioId === $user->id
                && $job->disk === 'local'
                && filled($job->processRequestId)
                && $job->connection === 'database'
                && $job->queue === 'exports';
        });
    }

    public function test_acoes_de_modelo_e_importacao_respeitam_permissoes_especificas(): void
    {
        Permission::findOrCreate('Listar Alunos');
        Permission::findOrCreate('Exportar Modelo de Importacao de Alunos');
        Permission::findOrCreate('Importar Alunos por Planilha');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo('Listar Alunos');

        Livewire::actingAs($user)
            ->test(ListAlunos::class)
            ->assertActionHidden('exportarModeloImportacao')
            ->assertActionHidden('importarMatriculados');

        $user->givePermissionTo([
            'Exportar Modelo de Importacao de Alunos',
            'Importar Alunos por Planilha',
        ]);

        Livewire::actingAs($user->fresh())
            ->test(ListAlunos::class)
            ->assertActionVisible('exportarModeloImportacao')
            ->assertActionVisible('importarMatriculados');
    }

    public function test_job_de_importacao_processa_planilha_em_segundo_plano(): void
    {
        Notification::fake();
        Storage::fake('local');

        Escola::query()->create([
            'nome' => 'Escola Municipal Teste',
            'ativo' => true,
        ]);

        $caminho = $this->criarPlanilhaNoStorage('local', [
            ['Escola', 'Seriacao', 'Turma', 'Turno', 'CGM', 'Nome do Aluno', 'Data de Nascimento', 'Sexo'],
            ['Escola Municipal Teste', '1 Ano', 'A', 'Tarde', '654', 'Aluno Processado', '01/02/2018', 'F'],
        ]);

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        app(ImportAlunosMatriculadosJob::class, [
            'caminhoArquivo' => $caminho,
            'usuarioId' => $user->id,
            'disk' => 'local',
        ])->handle(app(AlunoImportacaoSpreadsheetService::class));

        $this->assertDatabaseHas('alunos', [
            'cgm' => '654',
            'nome' => 'Aluno Processado',
        ]);

        Notification::assertSentTo($user, \App\Notifications\SistemaNotification::class);
    }

    private function criarPlanilhaNoStorage(string $disk, array $linhas): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Matriculados');

        foreach ($linhas as $indice => $linha) {
            $sheet->fromArray($linha, null, 'A'.($indice + 1));
        }

        $arquivoTemporario = tempnam(sys_get_temp_dir(), 'alunos_importacao_test_');

        (new Xlsx($spreadsheet))->save($arquivoTemporario);
        $spreadsheet->disconnectWorksheets();

        $caminho = 'imports/tests/'.basename($arquivoTemporario).'.xlsx';
        Storage::disk($disk)->put($caminho, file_get_contents($arquivoTemporario));

        @unlink($arquivoTemporario);
        gc_collect_cycles();

        return $caminho;
    }
}
