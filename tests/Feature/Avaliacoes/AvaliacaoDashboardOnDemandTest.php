<?php

namespace Tests\Feature\Avaliacoes;

use App\Jobs\RebuildAvaliacaoDashboardFactsJob;
use App\Jobs\SyncAvaliacaoDashboardAlunoJob;
use App\Jobs\SyncAvaliacaoDashboardScopeJob;
use App\Jobs\AtualizarAvaliacaoDashboardTurmaResumoJob;
use App\Models\Alternativa;
use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Pauta;
use App\Models\PeriodoAvaliacao;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Services\Avaliacoes\AvaliacaoAlunoDocumentoService;
use App\Services\Avaliacoes\AvaliacaoDashboardTurmaResumoService;
use App\Services\Avaliacoes\AvaliacaoRespostaStore;
use App\Services\Avaliacoes\AvaliacaoTurmaCicloService;
use App\Services\Avaliacoes\AvaliacaoDashboardFactsService;
use App\Services\Avaliacoes\AvaliacaoDashboardOnDemandQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AvaliacaoDashboardOnDemandTest extends TestCase
{
    use RefreshDatabase;

    public function test_documento_atualiza_consulta_imediatamente_sem_fatos_pendencias_ou_jobs(): void
    {
        Queue::fake();
        $cenario = $this->criarCenario();
        $queries = app(AvaliacaoDashboardOnDemandQueryService::class);

        $this->assertSame(1, $queries->esperados([$cenario['avaliacao']->id])->count());
        $this->assertSame(0, $queries->respostas([$cenario['avaliacao']->id], true)->count());

        $documento = app(AvaliacaoAlunoDocumentoService::class)
            ->obterOuCriar($cenario['avaliacao']->id, $cenario['aluno']);
        app(AvaliacaoAlunoDocumentoService::class)->salvarPauta(
            $documento,
            $cenario['pauta']->id,
            [
                'alternativa_id' => $cenario['alternativa']->id,
                'componente_curricular_id' => $cenario['componente']->id,
            ],
        );

        $this->assertSame(1, $queries->respostas([$cenario['avaliacao']->id], true)->count());
        $this->assertDatabaseCount('avaliacao_dashboard_fatos', 0);
        $this->assertDatabaseCount('avaliacao_dashboard_pendencias', 0);
        $this->assertDatabaseCount('avaliacao_dashboard_escopo_pendencias', 0);
        Queue::assertNothingPushed();
    }

    public function test_rebuild_servico_comando_e_jobs_legados_sao_inertes(): void
    {
        Queue::fake();
        $cenario = $this->criarCenario();
        $avaliacaoId = (int) $cenario['avaliacao']->id;
        $alunoId = (int) $cenario['aluno']->id;
        $service = app(AvaliacaoDashboardFactsService::class);

        $this->assertFalse($service->requestRebuild($avaliacaoId));
        $this->assertFalse($service->rebuild($avaliacaoId));
        $service->requestSyncDocumento($avaliacaoId, $alunoId);
        $service->requestSyncEstruturaAvaliacao($avaliacaoId);

        (new RebuildAvaliacaoDashboardFactsJob($avaliacaoId))->handle();
        (new SyncAvaliacaoDashboardAlunoJob($avaliacaoId, $alunoId))->handle();
        (new SyncAvaliacaoDashboardScopeJob($avaliacaoId, 'turma', (int) $cenario['turma']->id, 'teste'))->handle();

        $this->artisan('avaliacoes:rebuild-dashboard-facts', ['avaliacaoId' => $avaliacaoId])
            ->expectsOutputToContain('Rebuild desativado')
            ->assertSuccessful();

        $this->assertDatabaseCount('avaliacao_dashboard_fatos', 0);
        $this->assertDatabaseCount('avaliacao_dashboard_pendencias', 0);
        $this->assertDatabaseCount('avaliacao_dashboard_escopo_pendencias', 0);
        Queue::assertNothingPushed();
    }

    public function test_resumo_por_turma_consolida_indicadores_sem_fatos_legados(): void
    {
        Queue::fake();
        $cenario = $this->criarCenario();
        app(AvaliacaoTurmaCicloService::class)->sincronizarAvaliacao($cenario['avaliacao']);
        app(AvaliacaoRespostaStore::class)->salvarPauta(
            (int) $cenario['avaliacao']->id,
            (int) $cenario['turma']->id,
            $cenario['aluno'],
            (int) $cenario['pauta']->id,
            [
                'alternativa_id' => (int) $cenario['alternativa']->id,
                'componente_curricular_id' => (int) $cenario['componente']->id,
            ],
        );
        Queue::assertPushed(AtualizarAvaliacaoDashboardTurmaResumoJob::class);

        $job = new AtualizarAvaliacaoDashboardTurmaResumoJob(
            (int) $cenario['avaliacao']->id,
            (int) $cenario['turma']->id,
        );
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame($cenario['avaliacao']->id.':'.$cenario['turma']->id, $job->uniqueId());
        $this->assertSame(600, $job->uniqueFor());
        $this->assertTrue($job->delay->between(now()->addMinutes(9), now()->addMinutes(11)));

        $service = app(AvaliacaoDashboardTurmaResumoService::class);
        $service->recalcular((int) $cenario['avaliacao']->id, (int) $cenario['turma']->id);

        $this->assertDatabaseHas('avaliacao_dashboard_turma_resumos', [
            'avaliacao_id' => $cenario['avaliacao']->id,
            'turma_id' => $cenario['turma']->id,
            'componente_chave' => AvaliacaoDashboardTurmaResumoService::TOTAL_COMPONENT_KEY,
            'preenchimentos_esperados' => 1,
            'preenchimentos_respondidos' => 1,
            'alunos_total' => 1,
            'alunos_pendentes' => 0,
            'pautas_total' => 1,
        ]);
    }

    /**
     * @return array{
     *     avaliacao: Avaliacao,
     *     aluno: Aluno,
     *     turma: Turma,
     *     pauta: Pauta,
     *     componente: ComponenteCurricular,
     *     alternativa: Alternativa
     * }
     */
    private function criarCenario(): array
    {
        $tipo = TipoAvaliacao::query()->create(['nome' => 'Parecer sob demanda', 'status' => true]);
        $periodo = PeriodoAvaliacao::query()->create(['nome' => 'Período sob demanda', 'status' => true]);
        $serie = Serie::query()->create(['codigo' => 'SER-ON-DEMAND', 'nome' => 'Série sob demanda']);
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-ON-DEMAND',
            'nome' => 'Componente sob demanda',
        ]);
        $escola = Escola::query()->create([
            'codigo' => 'ESC-ON-DEMAND',
            'nome' => 'Escola sob demanda',
            'email' => 'escola.on-demand@teste.local',
            'telefone' => '(44) 99999-9999',
        ]);
        $turma = Turma::query()->create([
            'codigo' => 'TUR-ON-DEMAND',
            'nome' => 'A',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
        $aluno = Aluno::query()->create([
            'nome' => 'Aluno sob demanda',
            'cgm' => 'CGM-ON-DEMAND',
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turma->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
            'status' => Aluno::STATUS_MATRICULADO,
        ]);
        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Pauta sob demanda',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);
        $alternativa = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Atende',
            'tem_observacao' => false,
            'status' => true,
        ]);
        $pauta->alternativas()->attach($alternativa->id);

        $avaliacao = Avaliacao::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'nome' => 'Avaliação sob demanda',
            'data_inicio' => now()->subDay()->toDateString(),
            'data_fim' => now()->addDay()->toDateString(),
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escola->id]);
        $avaliacao->turmas()->sync([$turma->id]);
        $avaliacao->pautas()->sync([$pauta->id]);

        return compact('avaliacao', 'aluno', 'turma', 'pauta', 'componente', 'alternativa');
    }
}
