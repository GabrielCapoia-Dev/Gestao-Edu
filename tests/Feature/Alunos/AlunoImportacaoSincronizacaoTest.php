<?php

namespace Tests\Feature\Alunos;

use App\Models\Aluno;
use App\Models\Escola;
use App\Models\Serie;
use App\Models\Turma;
use App\Services\AlunoMovimentacaoService;
use App\Services\Alunos\AlunoImportacaoSpreadsheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AlunoImportacaoSincronizacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_principal_ja_existente_e_identico_na_unidade_nao_gera_duplicidade(): void
    {
        Storage::fake('local');

        $escola = $this->criarEscola('Escola Sincronizacao');
        $serie = $this->criarSerie('2 Ano');
        $turma = $this->criarTurma($escola, $serie, 'A', 'manha');

        $this->criarAlunoPrincipal($turma, '1001', [
            'nome' => 'Aluno Igual',
            'data_nascimento' => '2018-02-01',
            'sexo' => 'M',
            'data_matricula' => '2026-02-05',
        ]);

        $caminho = $this->criarPlanilhaNoStorage('local', [
            $this->cabecalho(),
            [$escola->nome, $serie->nome, 'A', 'Manha', '1001', 'Aluno Igual', '01/02/2018', 'M', '05/02/2026', 'Principal'],
        ]);

        $resultado = app(AlunoImportacaoSpreadsheetService::class)->importar($caminho, null, 'local');

        $this->assertSame(0, $resultado['total_importado']);
        $this->assertSame(0, $resultado['total_atualizado']);
        $this->assertSame(0, $resultado['total_remanejado']);
        $this->assertSame(1, $resultado['total_sem_alteracao']);
        $this->assertDatabaseCount('alunos', 1);
    }

    public function test_principal_ja_existente_tem_dados_atualizados_pela_planilha(): void
    {
        Storage::fake('local');

        $escola = $this->criarEscola('Escola Atualizacao');
        $serie = $this->criarSerie('3 Ano');
        $turma = $this->criarTurma($escola, $serie, 'A', 'tarde');

        $this->criarAlunoPrincipal($turma, '1002', [
            'nome' => 'Nome Antigo',
            'data_nascimento' => '2017-01-01',
            'sexo' => 'M',
            'data_matricula' => '2026-01-10',
        ]);

        $caminho = $this->criarPlanilhaNoStorage('local', [
            $this->cabecalho(),
            [$escola->nome, $serie->nome, 'A', 'Tarde', '1002', 'Nome Atualizado', '02/03/2017', 'F', '11/02/2026', 'Principal'],
        ]);

        $resultado = app(AlunoImportacaoSpreadsheetService::class)->importar($caminho, null, 'local');

        $this->assertSame(1, $resultado['total_atualizado']);
        $this->assertSame(0, $resultado['total_importado']);
        $this->assertDatabaseHas('alunos', [
            'cgm' => '1002',
            'nome' => 'Nome Atualizado',
            'sexo' => 'F',
            'id_turma' => $turma->id,
            'status' => Aluno::STATUS_MATRICULADO,
        ]);

        $aluno = Aluno::query()->where('cgm_matricula_ativa', '1002')->firstOrFail();
        $this->assertSame('2017-03-02', $aluno->data_nascimento?->toDateString());
        $this->assertSame('2026-02-11', $aluno->data_matricula?->toDateString());
    }

    public function test_principal_muda_de_turma_por_remanejamento_e_mantem_historico(): void
    {
        Storage::fake('local');

        $escola = $this->criarEscola('Escola Remanejamento Principal');
        $serieOrigem = $this->criarSerie('4 Ano');
        $serieDestino = $this->criarSerie('5 Ano');
        $turmaOrigem = $this->criarTurma($escola, $serieOrigem, 'A', 'manha');
        $turmaDestino = $this->criarTurma($escola, $serieDestino, 'B', 'tarde');
        $turmaContraTurno = $this->criarTurma($escola, $serieOrigem, 'SRM', 'tarde');

        $principalAntigo = $this->criarAlunoPrincipal($turmaOrigem, '1003', [
            'nome' => 'Aluno Remanejado',
        ]);

        $contraTurno = app(AlunoMovimentacaoService::class)->vincularContraTurno(
            $principalAntigo,
            $turmaContraTurno->id,
        );

        $caminho = $this->criarPlanilhaNoStorage('local', [
            $this->cabecalho(),
            [$escola->nome, $serieDestino->nome, 'B', 'Tarde', '1003', 'Aluno Remanejado Atualizado', '01/02/2018', 'M', '05/02/2026', 'Principal'],
        ]);

        $resultado = app(AlunoImportacaoSpreadsheetService::class)->importar($caminho, null, 'local');

        $this->assertSame(1, $resultado['total_remanejado']);
        $this->assertSame(Aluno::STATUS_REMANEJADO, $principalAntigo->fresh()->status);

        $novoPrincipal = Aluno::query()
            ->where('cgm_matricula_ativa', '1003')
            ->firstOrFail();

        $this->assertNotSame($principalAntigo->id, $novoPrincipal->id);
        $this->assertSame($turmaDestino->id, $novoPrincipal->id_turma);
        $this->assertSame('Aluno Remanejado Atualizado', $novoPrincipal->nome);
        $this->assertSame($principalAntigo->id, $novoPrincipal->aluno_origem_id);
        $this->assertSame($turmaOrigem->id, $novoPrincipal->turma_origem_id);
        $this->assertSame(AlunoMovimentacaoService::MOVIMENTACAO_REMANEJAMENTO, $novoPrincipal->movimentacao_origem);

        $contraTurno->refresh();
        $this->assertSame(Aluno::STATUS_MATRICULADO, $contraTurno->status);
        $this->assertSame($novoPrincipal->id, $contraTurno->aluno_origem_id);
        $this->assertSame($novoPrincipal->id_turma, $contraTurno->turma_origem_id);
    }

    public function test_contra_turno_existente_muda_de_turma_por_remanejamento(): void
    {
        Storage::fake('local');

        $escola = $this->criarEscola('Escola Remanejamento CT');
        $seriePrincipal = $this->criarSerie('2 Ano');
        $serieCtOrigem = $this->criarSerie('Sala Recursos');
        $serieCtDestino = $this->criarSerie('5 Ano');
        $turmaPrincipal = $this->criarTurma($escola, $seriePrincipal, 'A', 'manha');
        $turmaCtOrigem = $this->criarTurma($escola, $serieCtOrigem, 'SRM', 'tarde');
        $turmaCtDestino = $this->criarTurma($escola, $serieCtDestino, 'B', 'manha');

        $principal = $this->criarAlunoPrincipal($turmaPrincipal, '1004', [
            'nome' => 'Aluno Contra Turno',
        ]);

        $contraTurnoAntigo = app(AlunoMovimentacaoService::class)->vincularContraTurno(
            $principal,
            $turmaCtOrigem->id,
        );

        $caminho = $this->criarPlanilhaNoStorage('local', [
            $this->cabecalho(),
            [$escola->nome, $serieCtDestino->nome, 'B', 'Manha', '1004', 'Aluno Contra Turno Atualizado', '03/02/2018', 'F', '06/02/2026', 'Contra Turno'],
        ]);

        $resultado = app(AlunoImportacaoSpreadsheetService::class)->importar($caminho, null, 'local');

        $this->assertSame(1, $resultado['total_remanejado']);
        $this->assertSame(Aluno::STATUS_REMANEJADO, $contraTurnoAntigo->fresh()->status);

        $novoContraTurno = Aluno::query()
            ->where('cgm_contra_turno_ativo', '1004')
            ->firstOrFail();

        $this->assertNotSame($contraTurnoAntigo->id, $novoContraTurno->id);
        $this->assertSame($turmaCtDestino->id, $novoContraTurno->id_turma);
        $this->assertSame('Aluno Contra Turno Atualizado', $novoContraTurno->nome);
        $this->assertSame('F', $novoContraTurno->sexo);
        $this->assertSame($principal->id, $novoContraTurno->aluno_origem_id);
        $this->assertSame($turmaPrincipal->id, $novoContraTurno->turma_origem_id);
        $this->assertSame(AlunoMovimentacaoService::MOVIMENTACAO_CONTRA_TURNO, $novoContraTurno->movimentacao_origem);
    }

    public function test_principal_em_outra_escola_continua_sendo_criado_como_pendente(): void
    {
        Storage::fake('local');

        $escolaOrigem = $this->criarEscola('Escola Origem Pendente');
        $escolaDestino = $this->criarEscola('Escola Destino Pendente');
        $serie = $this->criarSerie('1 Ano');
        $turmaOrigem = $this->criarTurma($escolaOrigem, $serie, 'A', 'manha');
        $this->criarTurma($escolaDestino, $serie, 'B', 'tarde');

        $origem = $this->criarAlunoPrincipal($turmaOrigem, '1005', [
            'nome' => 'Aluno Transferencia',
        ]);

        $caminho = $this->criarPlanilhaNoStorage('local', [
            $this->cabecalho(),
            [$escolaDestino->nome, $serie->nome, 'B', 'Tarde', '1005', 'Aluno Transferencia', '01/02/2018', 'M', '05/02/2026', 'Principal'],
        ]);

        $resultado = app(AlunoImportacaoSpreadsheetService::class)->importar($caminho, null, 'local');

        $this->assertSame(1, $resultado['total_importado']);
        $this->assertSame(1, $resultado['total_pendente']);

        $pendente = Aluno::query()
            ->where('cgm', '1005')
            ->where('status', Aluno::STATUS_PENDENTE)
            ->firstOrFail();

        $this->assertSame($origem->id, $pendente->pendencia_origem_aluno_id);
        $this->assertSame($escolaDestino->id, $pendente->turma->id_escola);
    }

    public function test_importacao_reconhece_ativo_mesmo_com_chaves_derivadas_inconsistentes(): void
    {
        Storage::fake('local');

        $escolaHistorica = $this->criarEscola('Escola Histórica');
        $escolaOrigem = $this->criarEscola('Escola Origem Inconsistente');
        $escolaDestino = $this->criarEscola('Escola Destino Inconsistente');
        $serie = $this->criarSerie('Infantil 4');
        $turmaHistorica = $this->criarTurma($escolaHistorica, $serie, 'A', 'manha');
        $turmaOrigem = $this->criarTurma($escolaOrigem, $serie, 'B', 'manha');
        $this->criarTurma($escolaDestino, $serie, 'C', 'tarde');

        $historico = $this->criarAlunoPrincipal($turmaHistorica, '1035154880', [
            'status' => Aluno::STATUS_TRANSFERIDO,
        ]);
        DB::table('alunos')->where('id', $historico->id)->update([
            'cgm_matricula_ativa' => $historico->cgm,
            'cgm_unidade_matricula_ativa' => $escolaHistorica->id.'|'.$historico->cgm,
        ]);

        $origem = $this->criarAlunoPrincipal($turmaOrigem, '1035154880');
        DB::table('alunos')->where('id', $origem->id)->update([
            'cgm_matricula_ativa' => null,
        ]);

        $caminho = $this->criarPlanilhaNoStorage('local', [
            $this->cabecalho(),
            [$escolaDestino->nome, $serie->nome, 'C', 'Tarde', '1035154880', 'Aluno Atualizado', '08/09/2021', 'F', '04/09/2026', 'Principal'],
        ]);

        $resultado = app(AlunoImportacaoSpreadsheetService::class)->importar($caminho, null, 'local');

        $this->assertSame(1, $resultado['total_importado']);
        $this->assertSame(1, $resultado['total_pendente']);
        $this->assertSame(2, Aluno::query()->where('cgm', '1035154880')->count());
        $this->assertDatabaseHas('alunos', [
            'id' => $origem->id,
            'status' => Aluno::STATUS_MATRICULADO,
        ]);
        $this->assertDatabaseHas('alunos', [
            'cgm' => '1035154880',
            'status' => Aluno::STATUS_PENDENTE,
            'pendencia_origem_aluno_id' => $origem->id,
        ]);
    }

    private function cabecalho(): array
    {
        return [
            'Escola',
            'Seriacao',
            'Turma',
            'Turno',
            'CGM',
            'Nome do Aluno',
            'Data de Nascimento',
            'Sexo',
            'Data Matricula',
            'Tipo de vinculo',
        ];
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

    private function criarAlunoPrincipal(Turma $turma, string $cgm, array $dados = []): Aluno
    {
        return Aluno::query()->create([
            'nome' => $dados['nome'] ?? 'Aluno '.$cgm,
            'cgm' => $cgm,
            'data_nascimento' => $dados['data_nascimento'] ?? '2018-02-01',
            'sexo' => $dados['sexo'] ?? 'M',
            'data_matricula' => $dados['data_matricula'] ?? '2026-02-05',
            'id_turma' => $turma->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
            'status' => $dados['status'] ?? Aluno::STATUS_MATRICULADO,
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

        $arquivoTemporario = tempnam(sys_get_temp_dir(), 'alunos_sincronizacao_test_');

        (new Xlsx($spreadsheet))->save($arquivoTemporario);
        $spreadsheet->disconnectWorksheets();

        $caminho = 'imports/tests/'.basename($arquivoTemporario).'.xlsx';
        Storage::disk($disk)->put($caminho, file_get_contents($arquivoTemporario));

        @unlink($arquivoTemporario);
        gc_collect_cycles();

        return $caminho;
    }
}
