<?php

namespace Tests\Feature\Avaliacoes;

use App\Filament\Admin\Pages\AvaliacoesProfessor;
use App\Models\Aluno;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\AvaliacaoResposta;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Pauta;
use App\Models\PeriodoAvaliacao;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AvaliacaoAlunoStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_avaliacao_lista_pendente_bloqueado_sem_sobrescrever_resposta_bloqueada_em_massa(): void
    {
        Permission::findOrCreate('Responder Avaliações');

        [$usuario, $escola, $serie, $turma, $avaliacao, $pauta, $alternativaOriginal, $alternativaMassa] = $this->criarCenarioProfessor();

        $alunoBloqueado = Aluno::query()->create([
            'nome' => 'Aluno Bloqueado',
            'cgm' => 'CGM-BLOQ',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turma->id,
        ]);

        $alunoTransferido = Aluno::query()->create([
            'nome' => 'Aluno Transferido',
            'cgm' => 'CGM-FORA',
            'data_nascimento' => '2015-01-02',
            'id_turma' => $turma->id,
            'status' => Aluno::STATUS_TRANSFERIDO,
        ]);

        $alunoPendente = $this->criarAlunoPendenteTransferencia($turma, $serie, 'CGM-PEND');

        AvaliacaoResposta::query()->create([
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turma->id,
            'aluno_id' => $alunoBloqueado->id,
            'alternativa_id' => $alternativaOriginal->id,
            'respondido_em' => now(),
            'bloqueada' => true,
            'aluno_origem_id' => $alunoTransferido->id,
            'turma_origem_id' => $turma->id,
            'bloqueio_tipo' => 'remanejamento',
        ]);

        Livewire::actingAs($usuario)
            ->test(AvaliacoesProfessor::class)
            ->set('avaliacao', $avaliacao->id)
            ->set('serieEscola', $escola->id.':'.$serie->id)
            ->call('alternarTurma', $turma->id)
            ->call('alternarPauta', $turma->id, $pauta->id)
            ->assertSee('Aluno Bloqueado')
            ->assertSee('Aluno Pendente')
            ->assertDontSee('Aluno Transferido')
            ->set('avaliacaoEmMassaGlobal', $alternativaMassa->id)
            ->call('aplicarEmMassaNaSerie');

        $this->assertDatabaseHas('avaliacao_respostas', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turma->id,
            'aluno_id' => $alunoBloqueado->id,
            'alternativa_id' => $alternativaOriginal->id,
            'bloqueada' => true,
        ]);

        $this->assertDatabaseMissing('avaliacao_respostas', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turma->id,
            'aluno_id' => $alunoTransferido->id,
            'alternativa_id' => $alternativaMassa->id,
        ]);

        $this->assertDatabaseMissing('avaliacao_respostas', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turma->id,
            'aluno_id' => $alunoPendente->id,
            'alternativa_id' => $alternativaMassa->id,
        ]);
    }

    public function test_aluno_pendente_transferencia_fica_bloqueado_e_nao_conta_no_progresso(): void
    {
        Permission::findOrCreate('Responder Avaliações');

        [$usuario, $escola, $serie, $turma, $avaliacao, $pauta, $alternativaOriginal, $alternativaMassa, $componente] = $this->criarCenarioProfessor();

        $alunoRegular = Aluno::query()->create([
            'nome' => 'Aluno Regular',
            'cgm' => 'CGM-REG',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turma->id,
        ]);
        $alunoPendente = $this->criarAlunoPendenteTransferencia($turma, $serie, 'CGM-PEND-BLOCK');

        $component = Livewire::actingAs($usuario)
            ->test(AvaliacoesProfessor::class)
            ->set('avaliacao', $avaliacao->id)
            ->set('serieEscola', $escola->id.':'.$serie->id)
            ->call('alternarTurma', $turma->id)
            ->call('alternarPauta', $turma->id, $pauta->id)
            ->assertSee($alunoPendente->nome)
            ->assertSee('Aluno pendente de transferência');

        $this->assertSame(['preenchidas' => 0, 'total' => 1], $component->instance()->getProgressoProperty());
        $this->assertSame(0, $component->instance()->getProgressoPorAlunoProperty()[$alunoPendente->id]['total']);

        $component
            ->set("respostas.{$pauta->id}.{$alunoPendente->id}.alternativa_id", $alternativaMassa->id)
            ->set("informacoesComplementares.{$componente->id}.{$alunoPendente->id}", 'Texto bloqueado')
            ->set('avaliacaoEmMassaGlobal', $alternativaMassa->id)
            ->call('aplicarEmMassaNaSerie')
            ->set("respostas.{$pauta->id}.{$alunoRegular->id}.alternativa_id", $alternativaOriginal->id)
            ->call('salvarRespostas');

        $this->assertDatabaseMissing('avaliacao_respostas', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turma->id,
            'aluno_id' => $alunoPendente->id,
        ]);
        $this->assertDatabaseMissing('avaliacao_informacoes_complementares', [
            'avaliacao_id' => $avaliacao->id,
            'turma_id' => $turma->id,
            'aluno_id' => $alunoPendente->id,
        ]);
        $this->assertDatabaseHas('avaliacao_respostas', [
            'avaliacao_id' => $avaliacao->id,
            'pauta_id' => $pauta->id,
            'turma_id' => $turma->id,
            'aluno_id' => $alunoRegular->id,
            'alternativa_id' => $alternativaOriginal->id,
        ]);
    }

    private function criarCenarioProfessor(): array
    {
        $tipo = TipoAvaliacao::query()->create(['nome' => 'Tipo Status', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo Status', 'status' => true]);
        $escola = Escola::query()->create([
            'codigo' => 'ESC'.uniqid(),
            'nome' => 'Escola Status',
            'email' => 'status@teste.local',
            'telefone' => '(44) 99999-9999',
        ]);
        $serie = Serie::query()->create(['codigo' => 'SER'.uniqid(), 'nome' => '1o Ano']);
        $turma = Turma::query()->create([
            'codigo' => 'TUR'.uniqid(),
            'nome' => 'A',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP'.uniqid(),
            'nome' => 'Matematica',
        ]);
        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Responder Avaliações');
        $professor = Professor::query()->create([
            'user_id' => $usuario->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF'.uniqid(),
            'nome' => 'Professor Status',
            'email' => 'prof.status@teste.local',
        ]);
        $turma->componentes()->attach($componente->id, [
            'professor_id' => $professor->id,
            'tem_professor' => true,
        ]);

        $alternativaOriginal = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Original',
            'tem_observacao' => false,
            'status' => true,
        ]);
        $alternativaMassa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Massa',
            'tem_observacao' => false,
            'status' => true,
        ]);
        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Pauta status',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);
        $pauta->alternativas()->attach([$alternativaOriginal->id, $alternativaMassa->id]);

        $avaliacao = Avaliacao::query()->create([
            'nome' => 'Avaliacao Status',
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'data_inicio' => now()->subDay()->toDateString(),
            'data_fim' => now()->addDays(10)->toDateString(),
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacao->pautas()->attach($pauta->id);
        $avaliacao->turmas()->attach($turma->id);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escola->id]);

        return [$usuario, $escola, $serie, $turma, $avaliacao, $pauta, $alternativaOriginal, $alternativaMassa, $componente];
    }

    private function criarAlunoPendenteTransferencia(Turma $turmaDestino, Serie $serie, string $cgm): Aluno
    {
        $escolaOrigem = Escola::query()->create([
            'codigo' => 'ORIG'.uniqid(),
            'nome' => 'Escola Origem Status '.uniqid(),
            'email' => 'origem.status'.uniqid().'@teste.local',
            'telefone' => '(44) 99999-9999',
        ]);
        $turmaOrigem = Turma::query()->create([
            'codigo' => 'TOR'.uniqid(),
            'nome' => 'Origem',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escolaOrigem->id,
        ]);
        $alunoOrigem = Aluno::query()->create([
            'nome' => 'Aluno Pendente',
            'cgm' => $cgm,
            'data_nascimento' => '2015-01-03',
            'id_turma' => $turmaOrigem->id,
        ]);

        return Aluno::query()->create([
            'nome' => 'Aluno Pendente',
            'cgm' => $cgm,
            'data_nascimento' => '2015-01-03',
            'id_turma' => $turmaDestino->id,
            'status' => Aluno::STATUS_PENDENTE,
            'pendencia_origem_aluno_id' => $alunoOrigem->id,
        ]);
    }
}
