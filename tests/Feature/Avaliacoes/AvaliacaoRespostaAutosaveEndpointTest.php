<?php

namespace Tests\Feature\Avaliacoes;

use App\Models\Aluno;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Pauta;
use App\Models\PeriodoAvaliacao;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Models\User;
use App\Services\Avaliacoes\AvaliacaoAutosaveContextService;
use App\Services\Avaliacoes\AvaliacaoTurmaCicloService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AvaliacaoRespostaAutosaveEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_autosave_leve_persiste_resposta_sem_snapshot_livewire(): void
    {
        $cenario = $this->cenario();

        $payloadContexto = [
            'avaliacao_id' => $cenario['avaliacao']->id,
            'turma_id' => $cenario['turma']->id,
            'aluno_id' => $cenario['aluno']->id,
            'tipo' => 'resposta',
            'pauta_id' => $cenario['pauta']->id,
            'alternativa_contexto_id' => $cenario['alternativa']->id,
        ];
        $contexto = app(AvaliacaoAutosaveContextService::class)
            ->resolver($payloadContexto, $cenario['user']);
        $this->assertNotNull($contexto);
        $this->assertSame($cenario['aluno']->id, $contexto['aluno']->id);
        $this->assertSame($cenario['professor']->id, $contexto['professor_id']);
        $this->assertSame($cenario['alternativa']->id, $contexto['alternativa']->id);

        $permissaoResponder = $cenario['user']->permissions()->firstOrFail();
        $cenario['user']->revokePermissionTo($permissaoResponder);
        $this->assertNull(app(AvaliacaoAutosaveContextService::class)
            ->resolver($payloadContexto, $cenario['user']));
        $cenario['user']->givePermissionTo($permissaoResponder);

        $response = $this->actingAs($cenario['user'])->postJson(route('avaliacoes.respostas.autosave'), [
            'avaliacao_id' => $cenario['avaliacao']->id,
            'turma_id' => $cenario['turma']->id,
            'aluno_id' => $cenario['aluno']->id,
            'tipo' => 'resposta',
            'pauta_id' => $cenario['pauta']->id,
            'campo' => 'alternativa_id',
            'valor' => $cenario['alternativa']->id,
            'observacao' => null,
            'expected_version' => 0,
            'expected_values' => ['alternativa_id' => null, 'observacao' => null],
        ]);

        $response->assertOk()->assertJson(['saved' => true, 'version' => 1]);
        $this->assertDatabaseHas('avaliacao_respostas_operacionais', [
            'avaliacao_id' => $cenario['avaliacao']->id,
            'turma_avaliativa_id' => $cenario['turma']->id,
            'aluno_id' => $cenario['aluno']->id,
            'pauta_id' => $cenario['pauta']->id,
            'alternativa_id' => $cenario['alternativa']->id,
            'version' => 1,
        ]);
    }

    public function test_autosave_leve_persiste_informacao_complementar(): void
    {
        $cenario = $this->cenario();

        $this->actingAs($cenario['user'])
            ->postJson(route('avaliacoes.respostas.autosave'), [
                'avaliacao_id' => $cenario['avaliacao']->id,
                'turma_id' => $cenario['turma']->id,
                'aluno_id' => $cenario['aluno']->id,
                'tipo' => 'informacao',
                'componente_id' => $cenario['componente']->id,
                'campo' => 'observacao',
                'valor' => 'Informacao complementar do aluno',
                'expected_version' => 0,
            ])
            ->assertOk()
            ->assertJson(['saved' => true, 'version' => 1]);

        $this->assertDatabaseHas('avaliacao_informacoes_operacionais', [
            'avaliacao_id' => $cenario['avaliacao']->id,
            'turma_avaliativa_id' => $cenario['turma']->id,
            'aluno_id' => $cenario['aluno']->id,
            'componente_curricular_id' => $cenario['componente']->id,
            'texto' => 'Informacao complementar do aluno',
            'version' => 1,
        ]);
    }

    public function test_autosave_leve_mantem_conflito_de_versao(): void
    {
        $cenario = $this->cenario();
        $payload = [
            'avaliacao_id' => $cenario['avaliacao']->id,
            'turma_id' => $cenario['turma']->id,
            'aluno_id' => $cenario['aluno']->id,
            'tipo' => 'resposta',
            'pauta_id' => $cenario['pauta']->id,
            'campo' => 'alternativa_id',
            'valor' => $cenario['alternativa']->id,
            'expected_version' => 0,
            'expected_values' => ['alternativa_id' => null, 'observacao' => null],
        ];

        $this->actingAs($cenario['user'])->postJson(route('avaliacoes.respostas.autosave'), $payload)->assertOk();

        $this->actingAs($cenario['user'])
            ->postJson(route('avaliacoes.respostas.autosave'), [...$payload, 'valor' => null])
            ->assertStatus(409)
            ->assertJsonPath('saved', false);
    }

    public function test_autosave_retorna_erro_de_validacao_para_identificadores_inexistentes(): void
    {
        $cenario = $this->cenario();

        foreach (['avaliacao_id', 'turma_id', 'aluno_id', 'pauta_id'] as $campo) {
            $payload = [
                'avaliacao_id' => $cenario['avaliacao']->id,
                'turma_id' => $cenario['turma']->id,
                'aluno_id' => $cenario['aluno']->id,
                'tipo' => 'resposta',
                'pauta_id' => $cenario['pauta']->id,
                'campo' => 'alternativa_id',
                'valor' => $cenario['alternativa']->id,
                'expected_version' => 0,
            ];
            $payload[$campo] = 999999;

            $this->actingAs($cenario['user'])
                ->postJson(route('avaliacoes.respostas.autosave'), $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors($campo);
        }
    }

    /** @return array<string, mixed> */
    private function cenario(): array
    {
        Permission::findOrCreate('Responder Avaliações');

        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer endpoint', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Período endpoint', 'status' => true]);
        $escola = Escola::query()->create(['codigo' => 'ESC-END', 'nome' => 'Escola endpoint', 'email' => 'endpoint@teste.local']);
        $serie = Serie::query()->create(['codigo' => 'SER-END', 'nome' => 'Série endpoint']);
        $turma = Turma::query()->create([
            'codigo' => 'TUR-END', 'nome' => 'A', 'turno' => 'manha',
            'id_serie' => $serie->id, 'id_escola' => $escola->id,
        ]);
        $aluno = Aluno::query()->create([
            'nome' => 'Aluno endpoint', 'cgm' => 'CGM-END', 'data_nascimento' => '2015-01-01',
            'id_turma' => $turma->id, 'status' => Aluno::STATUS_MATRICULADO,
        ]);
        $componente = ComponenteCurricular::query()->create(['codigo' => 'COMP-END', 'nome' => 'Componente endpoint']);
        $user = User::factory()->create(['email_approved' => true, 'email_verified_at' => now()]);
        $user->givePermissionTo('Responder Avaliações');
        $professor = Professor::query()->create([
            'user_id' => $user->id, 'id_escola' => $escola->id, 'matricula' => 'PROF-END',
            'nome' => 'Professor endpoint', 'email' => 'prof-end@teste.local', 'ativo' => true,
        ]);
        $turma->componentes()->attach($componente->id, ['professor_id' => $professor->id, 'tem_professor' => true]);

        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id, 'nome' => 'Atende', 'tem_observacao' => false, 'status' => true,
        ]);
        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id, 'texto' => 'Pauta endpoint', 'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id, 'status' => true,
        ]);
        $pauta->alternativas()->attach($alternativa->id);
        $avaliacao = Avaliacao::query()->create([
            'tipo_avaliacao_id' => $tipo->id, 'periodo_avaliacao_id' => $periodo->id,
            'nome' => 'Avaliação endpoint', 'data_inicio' => now()->subDay(), 'data_fim' => now()->addDay(),
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacao->pautas()->attach($pauta->id);
        $avaliacao->turmas()->attach($turma->id);
        $avaliacao->series()->attach($serie->id);
        $avaliacao->componentes()->attach($componente->id);
        $avaliacao->escolas()->attach($escola->id);
        app(AvaliacaoTurmaCicloService::class)->sincronizarAvaliacao($avaliacao);

        return compact('user', 'professor', 'avaliacao', 'turma', 'aluno', 'componente', 'pauta', 'alternativa');
    }
}
