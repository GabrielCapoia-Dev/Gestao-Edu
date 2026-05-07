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

    public function test_avaliacao_lista_apenas_matriculados_e_nao_sobrescreve_resposta_bloqueada_em_massa(): void
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
            'bloqueio_tipo' => 'transferencia',
        ]);

        Livewire::actingAs($usuario)
            ->test(AvaliacoesProfessor::class)
            ->set('avaliacao', $avaliacao->id)
            ->set('serieEscola', $escola->id.':'.$serie->id)
            ->call('alternarTurma', $turma->id)
            ->call('alternarPauta', $turma->id, $pauta->id)
            ->assertSee('Aluno Bloqueado')
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

        return [$usuario, $escola, $serie, $turma, $avaliacao, $pauta, $alternativaOriginal, $alternativaMassa];
    }
}
