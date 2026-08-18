<?php

namespace Tests\Feature\Alunos;

use App\Models\Aluno;
use App\Models\Escola;
use App\Models\Serie;
use App\Models\Turma;
use App\Services\AlunoMovimentacaoService;
use App\Services\Alunos\AlunoImportacaoSpreadsheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\TestCase;

class AlunoContraTurnoImportacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_contra_turno_pode_ser_vinculado_em_outra_serie_no_mesmo_turno_da_mesma_escola(): void
    {
        $escola = $this->criarEscola('Escola Contra Turno');
        $seriePrincipal = $this->criarSerie('2 Ano');
        $serieContraTurno = $this->criarSerie('5 Ano');
        $turmaPrincipal = $this->criarTurma($escola, $seriePrincipal, 'A', 'manha');
        $turmaContraTurno = $this->criarTurma($escola, $serieContraTurno, 'B', 'manha');

        $principal = $this->criarAlunoPrincipal($turmaPrincipal, 'CGM-CT-1');

        $contraTurno = app(AlunoMovimentacaoService::class)->vincularContraTurno(
            $principal,
            $turmaContraTurno->id
        );

        $this->assertTrue($contraTurno->isContraTurno());
        $this->assertSame(Aluno::STATUS_MATRICULADO, $contraTurno->status);
        $this->assertSame($turmaContraTurno->id, $contraTurno->id_turma);
        $this->assertSame($principal->id, $contraTurno->aluno_origem_id);
        $this->assertSame($principal->cgm, $contraTurno->cgm_contra_turno_ativo);
        $this->assertTrue((bool) $principal->fresh()->permite_contra_turno);
    }

    public function test_contra_turno_pode_ser_vinculado_na_mesma_turma_do_principal(): void
    {
        $escola = $this->criarEscola('Escola Mesma Turma CT');
        $serie = $this->criarSerie('5 Ano');
        $turma = $this->criarTurma($escola, $serie, 'A', 'integral');
        $principal = $this->criarAlunoPrincipal($turma, 'CGM-CT-MESMA-TURMA');

        $contraTurno = app(AlunoMovimentacaoService::class)->vincularContraTurno(
            $principal,
            $turma->id
        );

        $this->assertTrue($contraTurno->isContraTurno());
        $this->assertSame($principal->id_turma, $contraTurno->id_turma);
        $this->assertSame($principal->id, $contraTurno->aluno_origem_id);
        $this->assertSame(Aluno::STATUS_MATRICULADO, $contraTurno->status);
    }

    public function test_contra_turno_continua_bloqueado_em_outra_escola(): void
    {
        $escolaOrigem = $this->criarEscola('Escola Origem CT');
        $escolaDestino = $this->criarEscola('Escola Destino CT');
        $serie = $this->criarSerie('3 Ano');
        $turmaPrincipal = $this->criarTurma($escolaOrigem, $serie, 'A', 'manha');
        $turmaOutraEscola = $this->criarTurma($escolaDestino, $serie, 'B', 'tarde');

        $principal = $this->criarAlunoPrincipal($turmaPrincipal, 'CGM-CT-2');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mesma escola');

        app(AlunoMovimentacaoService::class)->vincularContraTurno(
            $principal,
            $turmaOutraEscola->id
        );
    }

    public function test_importacao_aceita_principal_e_contra_turno_do_mesmo_cgm_na_mesma_turma(): void
    {
        Storage::fake('local');

        $escola = $this->criarEscola('Escola Importacao CT');

        $caminho = $this->criarPlanilhaNoStorage('local', [
            ['Escola', 'Seriacao', 'Turma', 'Turno', 'CGM', 'Nome do Aluno', 'Data de Nascimento', 'Sexo', 'Data Matricula', 'Tipo de vinculo'],
            [$escola->nome, '5 Ano', 'A', 'Integral', '9001', 'Aluno Dois Vinculos', '01/02/2018', 'M', '05/02/2026', 'Contra Turno'],
            [$escola->nome, '5 Ano', 'A', 'Integral', '9001', 'Aluno Dois Vinculos', '01/02/2018', 'M', '05/02/2026', 'Principal'],
        ]);

        $resultado = app(AlunoImportacaoSpreadsheetService::class)->importar($caminho, null, 'local');

        $this->assertSame(2, $resultado['total_importado']);
        $this->assertSame(0, $resultado['total_pendente']);
        $this->assertSame(0, $resultado['duplicados_ignorados']);
        $this->assertDatabaseCount('alunos', 2);

        $principal = Aluno::query()
            ->where('cgm', '9001')
            ->where('tipo_vinculo', Aluno::TIPO_VINCULO_PRINCIPAL)
            ->firstOrFail();

        $contraTurno = Aluno::query()
            ->where('cgm', '9001')
            ->where('tipo_vinculo', Aluno::TIPO_VINCULO_CONTRA_TURNO)
            ->firstOrFail();

        $this->assertSame(Aluno::STATUS_MATRICULADO, $principal->status);
        $this->assertSame(Aluno::STATUS_MATRICULADO, $contraTurno->status);
        $this->assertSame($principal->id, $contraTurno->aluno_origem_id);
        $this->assertSame($principal->id_turma, $contraTurno->id_turma);
        $this->assertTrue((bool) $principal->fresh()->permite_contra_turno);
    }

    public function test_importacao_de_principal_em_nova_escola_cria_pendencia_aguardando_transferencia(): void
    {
        Storage::fake('local');

        $escolaOrigem = $this->criarEscola('Escola Origem Importacao');
        $escolaDestino = $this->criarEscola('Escola Destino Importacao');
        $serieOrigem = $this->criarSerie('4 Ano Origem');
        $turmaOrigem = $this->criarTurma($escolaOrigem, $serieOrigem, 'A', 'manha');
        $alunoOrigem = $this->criarAlunoPrincipal($turmaOrigem, '9002');

        $caminho = $this->criarPlanilhaNoStorage('local', [
            ['Escola', 'Seriacao', 'Turma', 'Turno', 'CGM', 'Nome do Aluno', 'Data de Nascimento', 'Sexo', 'Tipo de vinculo'],
            [$escolaDestino->nome, '4 Ano', 'B', 'Tarde', '9002', 'Nome da planilha deve ser ignorado', '02/02/2018', 'F', 'Principal'],
        ]);

        $resultado = app(AlunoImportacaoSpreadsheetService::class)->importar($caminho, null, 'local');

        $this->assertSame(1, $resultado['total_importado']);
        $this->assertSame(1, $resultado['total_pendente']);

        $pendente = Aluno::query()
            ->where('cgm', '9002')
            ->where('status', Aluno::STATUS_PENDENTE)
            ->firstOrFail();

        $this->assertSame($alunoOrigem->id, $pendente->pendencia_origem_aluno_id);
        $this->assertSame($alunoOrigem->nome, $pendente->nome);
        $this->assertSame($escolaDestino->id, $pendente->turma->id_escola);
        $this->assertNull($pendente->cgm_matricula_ativa);
        $this->assertSame(
            Aluno::chaveCgmUnidade($escolaDestino->id, '9002'),
            $pendente->cgm_unidade_matricula_ativa
        );
    }

    public function test_contra_turno_da_planilha_e_criado_mesmo_quando_principal_da_nova_escola_esta_pendente(): void
    {
        Storage::fake('local');

        $escolaOrigem = $this->criarEscola('Escola Antiga');
        $escolaDestino = $this->criarEscola('Escola Nova');
        $serieOrigem = $this->criarSerie('1 Ano Origem');
        $turmaOrigem = $this->criarTurma($escolaOrigem, $serieOrigem, 'A', 'manha');
        $alunoOrigem = $this->criarAlunoPrincipal($turmaOrigem, '9003');

        $caminho = $this->criarPlanilhaNoStorage('local', [
            ['Escola', 'Seriacao', 'Turma', 'Turno', 'CGM', 'Nome do Aluno', 'Data de Nascimento', 'Sexo', 'Tipo de vinculo'],
            [$escolaDestino->nome, '1 Ano', 'B', 'Manha', '9003', 'Aluno Em Transferencia', '01/02/2019', 'M', 'Principal'],
            [$escolaDestino->nome, 'Sala de Recursos', 'SRM', 'Tarde', '9003', 'Aluno Em Transferencia', '01/02/2019', 'M', 'Contra Turno'],
        ]);

        $resultado = app(AlunoImportacaoSpreadsheetService::class)->importar($caminho, null, 'local');

        $this->assertSame(2, $resultado['total_importado']);
        $this->assertSame(1, $resultado['total_pendente']);

        $pendente = Aluno::query()
            ->with('turma')
            ->where('cgm', '9003')
            ->where('tipo_vinculo', Aluno::TIPO_VINCULO_PRINCIPAL)
            ->where('status', Aluno::STATUS_PENDENTE)
            ->firstOrFail();

        $contraTurno = Aluno::query()
            ->with('turma')
            ->where('cgm', '9003')
            ->where('tipo_vinculo', Aluno::TIPO_VINCULO_CONTRA_TURNO)
            ->where('status', Aluno::STATUS_MATRICULADO)
            ->firstOrFail();

        $this->assertSame($alunoOrigem->id, $pendente->pendencia_origem_aluno_id);
        $this->assertSame($escolaDestino->id, $pendente->turma->id_escola);
        $this->assertSame($escolaDestino->id, $contraTurno->turma->id_escola);
        $this->assertSame($pendente->id, $contraTurno->aluno_origem_id);
        $this->assertSame($pendente->id_turma, $contraTurno->turma_origem_id);
        $this->assertTrue((bool) $pendente->fresh()->permite_contra_turno);
    }

    private function criarEscola(string $nome): Escola
    {
        return Escola::query()->create([
            'nome' => $nome,
            'ativo' => true,
        ]);
    }

    private function criarSerie(string $nome): Serie
    {
        return Serie::query()->create([
            'nome' => $nome,
        ]);
    }

    private function criarTurma(Escola $escola, Serie $serie, string $nome, string $turno): Turma
    {
        return Turma::query()->create([
            'nome' => $nome,
            'turno' => $turno,
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }

    private function criarAlunoPrincipal(Turma $turma, string $cgm): Aluno
    {
        return Aluno::query()->create([
            'nome' => 'Aluno '.$cgm,
            'cgm' => $cgm,
            'data_nascimento' => '2018-02-01',
            'sexo' => 'M',
            'id_turma' => $turma->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
            'status' => Aluno::STATUS_MATRICULADO,
        ]);
    }

    private function criarPlanilhaNoStorage(string $disk, array $linhas): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Matriculados');

        foreach ($linhas as $indice => $linha) {
            $sheet->fromArray($linha, null, 'A'.($indice + 1));
        }

        $arquivoTemporario = tempnam(sys_get_temp_dir(), 'alunos_contra_turno_importacao_test_');

        (new Xlsx($spreadsheet))->save($arquivoTemporario);
        $spreadsheet->disconnectWorksheets();

        $caminho = 'imports/tests/'.basename($arquivoTemporario).'.xlsx';
        Storage::disk($disk)->put($caminho, file_get_contents($arquivoTemporario));

        @unlink($arquivoTemporario);
        gc_collect_cycles();

        return $caminho;
    }
}
