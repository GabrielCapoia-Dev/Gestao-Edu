<?php

namespace Tests\Feature\Alunos;

use App\Models\Aluno;
use App\Models\Escola;
use App\Models\Serie;
use App\Models\Turma;
use App\Services\AlunoMovimentacaoService;
use App\Services\Alunos\AlunoImportacaoSincronizacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlunoVinculoReflexoTransferenciaTest extends TestCase
{
    use RefreshDatabase;

    public function test_transferencia_move_o_par_da_origem_e_ativa_o_par_pendente_no_destino(): void
    {
        $escolaOrigem = $this->criarEscola('Escola Origem');
        $escolaDestino = $this->criarEscola('Escola Destino');
        $serie = $this->criarSerie('4 Ano');

        $turmaPrincipalOrigem = $this->criarTurma($escolaOrigem, $serie, 'C', 'tarde');
        $turmaContraOrigem = $this->criarTurma($escolaOrigem, $serie, 'SRM', 'manha');
        $turmaPrincipalDestino = $this->criarTurma($escolaDestino, $serie, 'B', 'integral');
        $turmaContraDestino = $this->criarTurma($escolaDestino, $serie, 'SRM', 'tarde');

        $principalOrigem = $this->criarPrincipal(
            $turmaPrincipalOrigem,
            '1022433357',
            Aluno::STATUS_MATRICULADO,
        );

        $contraOrigem = app(AlunoMovimentacaoService::class)->vincularContraTurno(
            $principalOrigem,
            $turmaContraOrigem->id,
        );

        $principalDestino = Aluno::query()->create([
            'nome' => $principalOrigem->nome,
            'cgm' => $principalOrigem->cgm,
            'data_nascimento' => $principalOrigem->data_nascimento,
            'sexo' => $principalOrigem->sexo,
            'data_matricula' => $principalOrigem->data_matricula,
            'id_turma' => $turmaPrincipalDestino->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
            'status' => Aluno::STATUS_PENDENTE,
            'pendencia_origem_aluno_id' => $principalOrigem->id,
        ]);

        $contraDestino = Aluno::query()->create([
            'nome' => $principalDestino->nome,
            'cgm' => $principalDestino->cgm,
            'data_nascimento' => $principalDestino->data_nascimento,
            'sexo' => $principalDestino->sexo,
            'data_matricula' => $principalDestino->data_matricula,
            'id_turma' => $turmaContraDestino->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_CONTRA_TURNO,
            'status' => Aluno::STATUS_PENDENTE,
            'aluno_origem_id' => $principalDestino->id,
            'turma_origem_id' => $principalDestino->id_turma,
            'movimentacao_origem' => AlunoMovimentacaoService::MOVIMENTACAO_CONTRA_TURNO,
        ]);

        app(AlunoMovimentacaoService::class)->transferir($principalOrigem);

        $this->assertSame(Aluno::STATUS_TRANSFERIDO, $principalOrigem->fresh()->status);
        $this->assertSame(Aluno::STATUS_TRANSFERIDO, $contraOrigem->fresh()->status);

        $principalDestino->refresh();
        $contraDestino->refresh();

        $this->assertSame(Aluno::STATUS_MATRICULADO, $principalDestino->status);
        $this->assertSame(Aluno::STATUS_MATRICULADO, $contraDestino->status);
        $this->assertNull($principalDestino->pendencia_origem_aluno_id);
        $this->assertSame($principalDestino->id, $contraDestino->aluno_origem_id);
        $this->assertSame($principalDestino->cgm, $contraDestino->cgm_contra_turno_ativo);
        $this->assertNull($contraOrigem->fresh()->cgm_contra_turno_ativo);
    }

    public function test_dados_atualizados_pelo_contra_turno_refletem_no_principal_da_mesma_escola(): void
    {
        $escola = $this->criarEscola('Escola Reflexo');
        $serie = $this->criarSerie('5 Ano');
        $turmaPrincipal = $this->criarTurma($escola, $serie, 'A', 'integral');
        $turmaContra = $this->criarTurma($escola, $serie, 'SRM', 'tarde');

        $principal = $this->criarPrincipal(
            $turmaPrincipal,
            'CGM-REFLEXO',
            Aluno::STATUS_MATRICULADO,
        );

        $contra = app(AlunoMovimentacaoService::class)->vincularContraTurno(
            $principal,
            $turmaContra->id,
        );

        $resultado = app(AlunoImportacaoSincronizacaoService::class)->sincronizarContraTurnos([
            [
                'numero_linha' => 2,
                'nome' => 'Nome Atualizado Pela Planilha',
                'cgm' => 'CGM-REFLEXO',
                'data_nascimento' => '2017-05-10',
                'sexo' => 'F',
                'data_matricula' => '2026-02-20',
                'turma' => $turmaContra,
            ],
        ]);

        $this->assertSame(1, $resultado['total_atualizado']);
        $this->assertSame('Nome Atualizado Pela Planilha', $contra->fresh()->nome);
        $this->assertSame('Nome Atualizado Pela Planilha', $principal->fresh()->nome);
        $this->assertSame('F', $principal->fresh()->sexo);
        $this->assertSame('2017-05-10', $principal->fresh()->data_nascimento?->toDateString());
        $this->assertSame('2026-02-20', $principal->fresh()->data_matricula?->toDateString());
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

    private function criarTurma(
        Escola $escola,
        Serie $serie,
        string $nome,
        string $turno,
    ): Turma {
        return Turma::query()->create([
            'nome' => $nome,
            'turno' => $turno,
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }

    private function criarPrincipal(
        Turma $turma,
        string $cgm,
        string $status,
    ): Aluno {
        return Aluno::query()->create([
            'nome' => 'Aluno '.$cgm,
            'cgm' => $cgm,
            'data_nascimento' => '2018-01-01',
            'sexo' => 'M',
            'data_matricula' => '2026-02-10',
            'id_turma' => $turma->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
            'status' => $status,
        ]);
    }
}
