<?php

namespace Tests\Feature\Alunos;

use App\Livewire\AlunoParecerTransferenciaModal;
use App\Models\Alternativa;
use App\Models\Aluno;
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
use App\Services\AlunoTransferenciaPendenteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AlunoTransferenciaPendenteServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_professor_com_componente_completo_nao_fica_bloqueado_por_pendencia_de_outro_professor(): void
    {
        $cenario = $this->criarCenarioTransferenciaPendente();
        $service = app(AlunoTransferenciaPendenteService::class);

        AvaliacaoResposta::query()->create([
            'avaliacao_id' => $cenario['avaliacao']->id,
            'pauta_id' => $cenario['pauta_matematica']->id,
            'turma_id' => $cenario['turma_origem']->id,
            'aluno_id' => $cenario['aluno_origem']->id,
            'professor_id' => $cenario['professor_matematica']->id,
            'alternativa_id' => $cenario['alternativa']->id,
            'respondido_em' => now(),
        ]);

        $this->assertFalse($service->professorEstaBloqueado($cenario['usuario_matematica']));
        $this->assertNull($service->pendenciaAtivaParaProfessor($cenario['usuario_matematica']));

        $this->assertTrue($service->professorEstaBloqueado($cenario['usuario_historia']));
        $this->assertSame(
            $cenario['aluno_origem']->id,
            $service->pendenciaAtivaParaProfessor($cenario['usuario_historia'])?->id
        );
    }

    public function test_observacao_obrigatoria_vazia_mantem_professor_bloqueado_ate_completar_resposta(): void
    {
        $cenario = $this->criarCenarioTransferenciaPendente();
        $service = app(AlunoTransferenciaPendenteService::class);

        $alternativaComObservacao = Alternativa::query()->create([
            'tipo_avaliacao_id' => $cenario['tipo']->id,
            'nome' => 'Atende com observacao',
            'tem_observacao' => true,
            'status' => true,
        ]);
        $cenario['pauta_matematica']->alternativas()->attach($alternativaComObservacao->id);

        $resposta = AvaliacaoResposta::query()->create([
            'avaliacao_id' => $cenario['avaliacao']->id,
            'pauta_id' => $cenario['pauta_matematica']->id,
            'turma_id' => $cenario['turma_origem']->id,
            'aluno_id' => $cenario['aluno_origem']->id,
            'professor_id' => $cenario['professor_matematica']->id,
            'alternativa_id' => $alternativaComObservacao->id,
            'observacao' => '   ',
            'respondido_em' => now(),
        ]);

        $this->assertTrue($service->professorEstaBloqueado($cenario['usuario_matematica']));

        $resposta->update(['observacao' => 'Observacao preenchida corretamente.']);

        $this->assertFalse($service->professorEstaBloqueado($cenario['usuario_matematica']));
    }

    public function test_modal_parecer_exibe_apenas_pautas_do_componente_do_professor_sem_permissao_total(): void
    {
        $cenario = $this->criarCenarioTransferenciaPendente();

        Livewire::actingAs($cenario['usuario_matematica'])
            ->test(AlunoParecerTransferenciaModal::class, ['alunoId' => $cenario['aluno_origem']->id])
            ->call('alternarAvaliacaoParecer', $cenario['avaliacao']->id)
            ->assertSee('Pauta Matematica')
            ->assertSee('Matematica')
            ->assertDontSee('Pauta Historia')
            ->assertDontSee('Historia');
    }

    public function test_modal_parecer_mantem_professor_limitado_aos_componentes_dele_mesmo_apos_concluir_pendencia(): void
    {
        $cenario = $this->criarCenarioTransferenciaPendente();

        AvaliacaoResposta::query()->create([
            'avaliacao_id' => $cenario['avaliacao']->id,
            'pauta_id' => $cenario['pauta_matematica']->id,
            'turma_id' => $cenario['turma_origem']->id,
            'aluno_id' => $cenario['aluno_origem']->id,
            'professor_id' => $cenario['professor_matematica']->id,
            'alternativa_id' => $cenario['alternativa']->id,
            'respondido_em' => now(),
        ]);

        Livewire::actingAs($cenario['usuario_matematica'])
            ->test(AlunoParecerTransferenciaModal::class, ['alunoId' => $cenario['aluno_origem']->id])
            ->call('alternarAvaliacaoParecer', $cenario['avaliacao']->id)
            ->assertSee('Pauta Matematica')
            ->assertDontSee('Pauta Historia');
    }

    public function test_modal_parecer_exibe_todas_as_pautas_para_usuario_com_permissao_de_gerar_parecer(): void
    {
        Permission::findOrCreate('Gerar Parecer de Transferencia');

        $cenario = $this->criarCenarioTransferenciaPendente();
        $usuario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuario->givePermissionTo('Gerar Parecer de Transferencia');

        Livewire::actingAs($usuario)
            ->test(AlunoParecerTransferenciaModal::class, ['alunoId' => $cenario['aluno_origem']->id])
            ->call('alternarAvaliacaoParecer', $cenario['avaliacao']->id)
            ->assertSee('Pauta Matematica')
            ->assertSee('Pauta Historia');
    }

    private function criarCenarioTransferenciaPendente(): array
    {
        $escolaOrigem = $this->criarEscola('Escola Origem Pendencia');
        $escolaDestino = $this->criarEscola('Escola Destino Pendencia');
        $serie = Serie::query()->create(['codigo' => 'SER'.uniqid(), 'nome' => '1o Ano']);
        $turmaOrigem = $this->criarTurma($escolaOrigem, $serie, 'A');
        $turmaDestino = $this->criarTurma($escolaDestino, $serie, 'B');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer '.uniqid(), 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Periodo '.uniqid(), 'status' => true]);
        $componenteMatematica = ComponenteCurricular::query()->create([
            'codigo' => 'MAT'.uniqid(),
            'nome' => 'Matematica',
        ]);
        $componenteHistoria = ComponenteCurricular::query()->create([
            'codigo' => 'HIS'.uniqid(),
            'nome' => 'Historia',
        ]);

        $usuarioMatematica = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $usuarioHistoria = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $professorMatematica = $this->criarProfessor($usuarioMatematica, $escolaOrigem, 'Matematica');
        $professorHistoria = $this->criarProfessor($usuarioHistoria, $escolaOrigem, 'Historia');

        $turmaOrigem->componentes()->attach($componenteMatematica->id, [
            'professor_id' => $professorMatematica->id,
            'tem_professor' => true,
        ]);
        $turmaOrigem->componentes()->attach($componenteHistoria->id, [
            'professor_id' => $professorHistoria->id,
            'tem_professor' => true,
        ]);

        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Atende',
            'tem_observacao' => false,
            'status' => true,
        ]);

        $pautaMatematica = $this->criarPauta($tipo, $serie, $componenteMatematica, 'Pauta Matematica', $alternativa);
        $pautaHistoria = $this->criarPauta($tipo, $serie, $componenteHistoria, 'Pauta Historia', $alternativa);

        $avaliacao = Avaliacao::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'nome' => 'Avaliacao Transferencia '.uniqid(),
            'data_inicio' => now()->subDay()->toDateString(),
            'data_fim' => now()->addDays(10)->toDateString(),
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacao->pautas()->attach([$pautaMatematica->id, $pautaHistoria->id]);
        $avaliacao->turmas()->attach($turmaOrigem->id);

        $alunoOrigem = Aluno::query()->create([
            'nome' => 'Aluno com Transferencia Pendente',
            'cgm' => 'CGM'.uniqid(),
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaOrigem->id,
        ]);
        $alunoPendente = Aluno::query()->create([
            'nome' => 'Aluno com Transferencia Pendente',
            'cgm' => $alunoOrigem->cgm,
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turmaDestino->id,
            'status' => Aluno::STATUS_PENDENTE,
            'pendencia_origem_aluno_id' => $alunoOrigem->id,
        ]);

        return [
            'tipo' => $tipo,
            'avaliacao' => $avaliacao,
            'turma_origem' => $turmaOrigem,
            'aluno_origem' => $alunoOrigem,
            'aluno_pendente' => $alunoPendente,
            'usuario_matematica' => $usuarioMatematica,
            'usuario_historia' => $usuarioHistoria,
            'professor_matematica' => $professorMatematica,
            'professor_historia' => $professorHistoria,
            'pauta_matematica' => $pautaMatematica,
            'pauta_historia' => $pautaHistoria,
            'alternativa' => $alternativa,
        ];
    }

    private function criarEscola(string $nome): Escola
    {
        return Escola::query()->create([
            'codigo' => strtoupper(substr(md5($nome.uniqid()), 0, 8)),
            'nome' => $nome,
            'email' => strtolower(str_replace(' ', '.', $nome)).'@teste.local',
            'telefone' => '(44) 99999-9999',
        ]);
    }

    private function criarTurma(Escola $escola, Serie $serie, string $nome): Turma
    {
        return Turma::query()->create([
            'codigo' => 'TUR'.uniqid(),
            'nome' => $nome,
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
    }

    private function criarProfessor(User $usuario, Escola $escola, string $nome): Professor
    {
        return Professor::query()->create([
            'user_id' => $usuario->id,
            'id_escola' => $escola->id,
            'matricula' => 'PROF'.uniqid(),
            'nome' => 'Professor '.$nome,
            'email' => strtolower($nome).uniqid().'@edu.umuarama.pr.gov.br',
        ]);
    }

    private function criarPauta(
        TipoAvaliacao $tipo,
        Serie $serie,
        ComponenteCurricular $componente,
        string $texto,
        Alternativa $alternativa
    ): Pauta {
        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => $texto,
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);
        $pauta->alternativas()->attach($alternativa->id);

        return $pauta;
    }
}
