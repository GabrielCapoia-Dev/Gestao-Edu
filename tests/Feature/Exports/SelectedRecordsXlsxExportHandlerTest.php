<?php

namespace Tests\Feature\Exports;

use App\Models\Aluno;
use App\Models\Escola;
use App\Models\ExportRequest;
use App\Models\Serie;
use App\Models\Servidor;
use App\Models\Turma;
use App\Models\User;
use App\Services\Exports\Handlers\SelectedRecordsXlsxExportHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SelectedRecordsXlsxExportHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_gera_planilhas_dos_tres_tipos_com_apenas_os_registros_selecionados(): void
    {
        Storage::fake('local');
        config()->set('exports.disk', 'local');

        Permission::findOrCreate('Listar Turmas');
        Permission::findOrCreate('Listar Pessoas');

        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Admin', 'web'));
        $user->givePermissionTo(['Listar Turmas', 'Listar Pessoas']);

        $escola = $this->criarEscola('Escola Exportação');
        $serie = Serie::query()->create(['codigo' => 'SER-EXP', 'nome' => '1º Ano']);
        $turmaSelecionada = $this->criarTurma($escola, $serie, 'TUR-EXP-1', 'A');
        $turmaNaoSelecionada = $this->criarTurma($escola, $serie, 'TUR-EXP-2', 'B');
        $alunoSelecionado = Aluno::query()->create([
            'nome' => 'Aluno Selecionado',
            'cgm' => 'CGM-EXP-1',
            'data_nascimento' => '2018-01-02',
            'id_turma' => $turmaSelecionada->id,
        ]);
        Aluno::query()->create([
            'nome' => 'Aluno Não Selecionado',
            'cgm' => 'CGM-EXP-2',
            'data_nascimento' => '2018-02-03',
            'id_turma' => $turmaNaoSelecionada->id,
        ]);
        $servidorSelecionado = Servidor::query()->create([
            'nome' => 'Servidor Selecionado',
            'email' => 'servidor.selecionado@teste.local',
            'status' => Servidor::STATUS_ATIVO,
        ]);
        Servidor::query()->create([
            'nome' => 'Servidor Não Selecionado',
            'email' => 'servidor.nao.selecionado@teste.local',
            'status' => Servidor::STATUS_ATIVO,
        ]);

        $casos = [
            ['turmas_selecionadas', $turmaSelecionada->id, 'Turmas', 'A', 'B'],
            ['alunos_selecionados', $alunoSelecionado->id, 'Alunos', 'Aluno Selecionado', 'Aluno Não Selecionado'],
            ['servidores_selecionados', $servidorSelecionado->id, 'Servidores', 'Servidor Selecionado', 'Servidor Não Selecionado'],
        ];

        foreach ($casos as [$type, $id, $sheetName, $expected, $unexpected]) {
            $request = $this->criarSolicitacao($user, $type, [$id]);
            $result = app(SelectedRecordsXlsxExportHandler::class)->handle($request);

            Storage::disk('local')->assertExists($result->path);
            $path = Storage::disk('local')->path($result->path);
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertSame($sheetName, $sheet->getTitle());
            $this->assertStringContainsString($expected, json_encode($sheet->toArray(), JSON_UNESCAPED_UNICODE));
            $this->assertStringNotContainsString($unexpected, json_encode($sheet->toArray(), JSON_UNESCAPED_UNICODE));

            $spreadsheet->disconnectWorksheets();
            unset($sheet, $spreadsheet);
            gc_collect_cycles();
        }
    }

    public function test_rejeita_solicitacao_quando_um_id_esta_fora_do_escopo_do_usuario(): void
    {
        Storage::fake('local');
        Permission::findOrCreate('Listar Turmas');

        $escolaPermitida = $this->criarEscola('Escola Permitida');
        $escolaBloqueada = $this->criarEscola('Escola Bloqueada');
        $serie = Serie::query()->create(['codigo' => 'SER-ESCOPO', 'nome' => '2º Ano']);
        $turmaPermitida = $this->criarTurma($escolaPermitida, $serie, 'TUR-PERMITIDA', 'A');
        $turmaBloqueada = $this->criarTurma($escolaBloqueada, $serie, 'TUR-BLOQUEADA', 'B');
        $user = User::factory()->create(['id_escola' => $escolaPermitida->id]);
        $user->givePermissionTo('Listar Turmas');
        $request = $this->criarSolicitacao($user, 'turmas_selecionadas', [
            $turmaPermitida->id,
            $turmaBloqueada->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('não estão mais disponíveis no seu escopo de acesso');

        app(SelectedRecordsXlsxExportHandler::class)->handle($request);
    }

    private function criarSolicitacao(User $user, string $type, array $ids): ExportRequest
    {
        return ExportRequest::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'format' => 'xlsx',
            'label' => 'Teste de exportação',
            'filters' => ['ids' => $ids],
            'metadata' => [],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_RUNNING,
            'status_message' => 'Processando.',
            'progress_current' => 0,
            'progress_total' => 100,
        ]);
    }

    private function criarEscola(string $nome): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome), 0, 8)),
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'telefone' => '(44) 99999-9999',
            'ativo' => true,
        ]);
    }

    private function criarTurma(Escola $escola, Serie $serie, string $codigo, string $nome): Turma
    {
        return Turma::query()->create([
            'codigo' => $codigo,
            'nome' => $nome,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }
}
