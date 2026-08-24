<?php

namespace Tests\Feature\Avaliacoes;

use App\Jobs\RebuildAvaliacaoDashboardFactsJob;
use App\Jobs\SyncAvaliacaoDashboardAlunoJob;
use App\Jobs\SyncAvaliacaoDashboardScopeJob;
use App\Models\Alternativa;
use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\AvaliacaoAlunoDocumento;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Pauta;
use App\Models\PeriodoAvaliacao;
use App\Models\Serie;
use App\Models\TipoAvaliacao;
use App\Models\Turma;
use App\Services\Avaliacoes\AvaliacaoAlunoDocumentoService;
use App\Services\Avaliacoes\AvaliacaoDashboardFactsService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AvaliacaoDashboardFactsIncrementalTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_documento_atualiza_somente_o_aluno_e_nao_executa_delete_global(): void
    {
        $cenario = $this->criarCenario();
        $documentoAlvo = $this->criarDocumentoRespondido(
            $cenario,
            $cenario['aluno_alvo'],
            $cenario['alternativa_inicial'],
        );
        $this->criarDocumentoRespondido(
            $cenario,
            $cenario['outro_aluno'],
            $cenario['alternativa_inicial'],
        );
        $service = app(AvaliacaoDashboardFactsService::class);

        $service->syncDocumento($cenario['avaliacao']->id, $cenario['aluno_alvo']->id);
        $service->syncDocumento($cenario['avaliacao']->id, $cenario['outro_aluno']->id);

        DB::table('avaliacao_dashboard_fatos')
            ->where('avaliacao_id', $cenario['avaliacao']->id)
            ->where('aluno_id', $cenario['outro_aluno']->id)
            ->update(['origem_version' => 77]);

        $documentoAlvo->forceFill([
            'payload' => $this->payloadRespondido($cenario, $cenario['alternativa_atualizada']),
            'version' => 2,
        ])->saveQuietly();

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query;
        });

        $service->syncDocumento($cenario['avaliacao']->id, $cenario['aluno_alvo']->id);

        $this->assertDatabaseCount('avaliacao_dashboard_fatos', 2);
        $this->assertDatabaseHas('avaliacao_dashboard_fatos', [
            'avaliacao_id' => $cenario['avaliacao']->id,
            'aluno_id' => $cenario['aluno_alvo']->id,
            'pauta_id' => $cenario['pauta']->id,
            'alternativa_id' => $cenario['alternativa_atualizada']->id,
            'origem_version' => 2,
        ]);
        $this->assertDatabaseHas('avaliacao_dashboard_fatos', [
            'avaliacao_id' => $cenario['avaliacao']->id,
            'aluno_id' => $cenario['outro_aluno']->id,
            'pauta_id' => $cenario['pauta']->id,
            'alternativa_id' => $cenario['alternativa_inicial']->id,
            'origem_version' => 77,
        ]);

        $deletesDeFatos = collect($queries)->filter(function (QueryExecuted $query): bool {
            $sql = strtolower(trim($query->sql));

            return str_starts_with($sql, 'delete from')
                && str_contains($sql, 'avaliacao_dashboard_fatos');
        });

        $this->assertNotEmpty($deletesDeFatos, 'A sincronização deve remover fatos obsoletos do aluno.');
        $deletesDeFatos->each(function (QueryExecuted $query) use ($cenario): void {
            $this->assertStringContainsString('aluno_id', strtolower($query->sql));
            $this->assertContains($cenario['aluno_alvo']->id, $query->bindings);
        });
    }

    public function test_duas_solicitacoes_mantem_uma_pendencia_e_incrementam_a_geracao(): void
    {
        $cenario = $this->criarCenario();
        Queue::fake();
        $service = app(AvaliacaoDashboardFactsService::class);

        $service->requestSyncDocumento(
            $cenario['avaliacao']->id,
            $cenario['aluno_alvo']->id,
            'primeira_alteracao',
            2,
        );
        $service->requestSyncDocumento(
            $cenario['avaliacao']->id,
            $cenario['aluno_alvo']->id,
            'segunda_alteracao',
            5,
        );

        $this->assertDatabaseCount('avaliacao_dashboard_pendencias', 1);
        $this->assertDatabaseHas('avaliacao_dashboard_pendencias', [
            'avaliacao_id' => $cenario['avaliacao']->id,
            'aluno_id' => $cenario['aluno_alvo']->id,
            'documento_version' => 5,
            'geracao' => 2,
            'motivo' => 'segunda_alteracao',
        ]);
        Queue::assertPushed(SyncAvaliacaoDashboardAlunoJob::class, 1);
    }

    public function test_autosave_despacha_sincronizacao_do_aluno_e_nao_rebuild_completo(): void
    {
        $cenario = $this->criarCenario();
        $documento = $this->criarDocumentoVazio($cenario, $cenario['aluno_alvo']);
        Bus::fake([
            SyncAvaliacaoDashboardAlunoJob::class,
            RebuildAvaliacaoDashboardFactsJob::class,
        ]);

        app(AvaliacaoAlunoDocumentoService::class)->salvarPauta(
            $documento,
            $cenario['pauta']->id,
            ['alternativa_id' => $cenario['alternativa_inicial']->id],
        );

        Bus::assertDispatched(
            SyncAvaliacaoDashboardAlunoJob::class,
            fn (SyncAvaliacaoDashboardAlunoJob $job): bool => $job->avaliacaoId === $cenario['avaliacao']->id
                && $job->alunoId === $cenario['aluno_alvo']->id,
        );
        Bus::assertNotDispatched(RebuildAvaliacaoDashboardFactsJob::class);
    }

    public function test_processamento_repetido_da_sincronizacao_nao_duplica_fatos(): void
    {
        $cenario = $this->criarCenario();
        $this->criarDocumentoRespondido(
            $cenario,
            $cenario['aluno_alvo'],
            $cenario['alternativa_inicial'],
        );
        Queue::fake();
        $service = app(AvaliacaoDashboardFactsService::class);

        $service->requestSyncDocumento(
            $cenario['avaliacao']->id,
            $cenario['aluno_alvo']->id,
            'primeira_tentativa',
            1,
        );
        $service->processPendingDocumento($cenario['avaliacao']->id, $cenario['aluno_alvo']->id, 1);

        $fatoId = DB::table('avaliacao_dashboard_fatos')
            ->where('avaliacao_id', $cenario['avaliacao']->id)
            ->where('aluno_id', $cenario['aluno_alvo']->id)
            ->where('pauta_id', $cenario['pauta']->id)
            ->value('id');

        $service->requestSyncDocumento(
            $cenario['avaliacao']->id,
            $cenario['aluno_alvo']->id,
            'retry',
            1,
        );
        $service->processPendingDocumento($cenario['avaliacao']->id, $cenario['aluno_alvo']->id, 2);

        $this->assertNotNull($fatoId);
        $this->assertSame(1, DB::table('avaliacao_dashboard_fatos')
            ->where('avaliacao_id', $cenario['avaliacao']->id)
            ->where('aluno_id', $cenario['aluno_alvo']->id)
            ->where('pauta_id', $cenario['pauta']->id)
            ->count());
        $this->assertSame($fatoId, DB::table('avaliacao_dashboard_fatos')
            ->where('avaliacao_id', $cenario['avaliacao']->id)
            ->where('aluno_id', $cenario['aluno_alvo']->id)
            ->where('pauta_id', $cenario['pauta']->id)
            ->value('id'));
        $this->assertDatabaseMissing('avaliacao_dashboard_pendencias', [
            'avaliacao_id' => $cenario['avaliacao']->id,
            'aluno_id' => $cenario['aluno_alvo']->id,
        ]);
    }

    public function test_alteracao_estrutural_encaminha_escopos_sem_rebuild_completo(): void
    {
        $cenario = $this->criarCenario();
        Bus::fake([
            SyncAvaliacaoDashboardScopeJob::class,
            RebuildAvaliacaoDashboardFactsJob::class,
        ]);

        $cenario['avaliacao']->update(['nome' => 'Avaliação incremental editada']);

        app(AvaliacaoDashboardFactsService::class)->requestSyncEstruturaAvaliacao(
            $cenario['avaliacao']->id,
            'teste_estrutura',
        );

        Bus::assertDispatched(
            SyncAvaliacaoDashboardScopeJob::class,
            fn (SyncAvaliacaoDashboardScopeJob $job): bool => $job->avaliacaoId === $cenario['avaliacao']->id
                && $job->scope === SyncAvaliacaoDashboardScopeJob::SCOPE_TURMA
                && $job->scopeId === $cenario['turma']->id,
        );
        Bus::assertNotDispatched(RebuildAvaliacaoDashboardFactsJob::class);
    }

    public function test_falha_do_rebuild_manual_nao_e_ocultada_por_autosave_incremental(): void
    {
        $cenario = $this->criarCenario();
        Queue::fake();
        $service = app(AvaliacaoDashboardFactsService::class);

        DB::table('avaliacao_dashboard_consolidacoes')->updateOrInsert(
            ['avaliacao_id' => $cenario['avaliacao']->id],
            [
                'status' => AvaliacaoDashboardFactsService::STATUS_REBUILD_PROCESSING,
                'pendencias_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        $service->markFailed(
            $cenario['avaliacao']->id,
            'falha controlada',
            AvaliacaoDashboardFactsService::STATUS_REBUILD_PROCESSING,
        );
        $service->requestSyncDocumento(
            $cenario['avaliacao']->id,
            $cenario['aluno_alvo']->id,
            'autosave_apos_falha',
            2,
        );

        $this->assertDatabaseHas('avaliacao_dashboard_consolidacoes', [
            'avaliacao_id' => $cenario['avaliacao']->id,
            'status' => AvaliacaoDashboardFactsService::STATUS_REBUILD_FAILED,
            'erro' => 'falha controlada',
        ]);
        Queue::assertPushed(SyncAvaliacaoDashboardAlunoJob::class, 1);
    }

    /**
     * @return array{
     *     avaliacao: Avaliacao,
     *     turma: Turma,
     *     aluno_alvo: Aluno,
     *     outro_aluno: Aluno,
     *     pauta: Pauta,
     *     componente: ComponenteCurricular,
     *     alternativa_inicial: Alternativa,
     *     alternativa_atualizada: Alternativa
     * }
     */
    private function criarCenario(): array
    {
        Queue::fake();

        $tipo = TipoAvaliacao::query()->create([
            'nome' => 'Parecer incremental',
            'status' => true,
        ]);
        $periodo = PeriodoAvaliacao::query()->create([
            'nome' => 'Período incremental',
            'status' => true,
        ]);
        $serie = Serie::query()->create([
            'codigo' => 'SER-INC',
            'nome' => 'Série incremental',
        ]);
        $componente = ComponenteCurricular::query()->create([
            'codigo' => 'COMP-INC',
            'nome' => 'Componente incremental',
        ]);
        $escola = Escola::query()->create([
            'codigo' => 'ESC-INC',
            'nome' => 'Escola incremental',
            'email' => 'escola.incremental@teste.local',
            'telefone' => '(44) 99999-9999',
        ]);
        $turma = Turma::query()->create([
            'codigo' => 'TUR-INC',
            'nome' => 'A',
            'turno' => 'manha',
            'id_serie' => $serie->id,
            'id_escola' => $escola->id,
        ]);
        $alunoAlvo = $this->criarAluno($turma, 'Aluno alvo', 'CGM-INC-1');
        $outroAluno = $this->criarAluno($turma, 'Outro aluno', 'CGM-INC-2');
        $pauta = Pauta::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'texto' => 'Pauta incremental',
            'serie_id' => $serie->id,
            'componente_curricular_id' => $componente->id,
            'status' => true,
        ]);
        $alternativaInicial = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Inicial',
            'tem_observacao' => false,
            'status' => true,
        ]);
        $alternativaAtualizada = Alternativa::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'nome' => 'Atualizada',
            'tem_observacao' => false,
            'status' => true,
        ]);
        $pauta->alternativas()->attach([$alternativaInicial->id, $alternativaAtualizada->id]);

        $avaliacao = Avaliacao::query()->create([
            'tipo_avaliacao_id' => $tipo->id,
            'periodo_avaliacao_id' => $periodo->id,
            'nome' => 'Avaliação incremental',
            'data_inicio' => now()->subDay()->toDateString(),
            'data_fim' => now()->addDay()->toDateString(),
            'status' => Avaliacao::STATUS_ATIVA,
        ]);
        $avaliacao->series()->sync([$serie->id]);
        $avaliacao->componentes()->sync([$componente->id]);
        $avaliacao->escolas()->sync([$escola->id]);
        $avaliacao->turmas()->sync([$turma->id]);
        $avaliacao->pautas()->sync([$pauta->id]);

        DB::table('avaliacao_dashboard_consolidacoes')
            ->where('avaliacao_id', $avaliacao->id)
            ->update([
                'status' => AvaliacaoDashboardFactsService::STATUS_READY,
                'pendencias_count' => 0,
                'updated_at' => now(),
            ]);

        return [
            'avaliacao' => $avaliacao,
            'turma' => $turma,
            'aluno_alvo' => $alunoAlvo,
            'outro_aluno' => $outroAluno,
            'pauta' => $pauta,
            'componente' => $componente,
            'alternativa_inicial' => $alternativaInicial,
            'alternativa_atualizada' => $alternativaAtualizada,
        ];
    }

    private function criarAluno(Turma $turma, string $nome, string $cgm): Aluno
    {
        return Aluno::query()->create([
            'nome' => $nome,
            'cgm' => $cgm,
            'data_nascimento' => '2015-01-01',
            'id_turma' => $turma->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
            'status' => Aluno::STATUS_MATRICULADO,
        ]);
    }

    /** @param array<string, mixed> $cenario */
    private function criarDocumentoVazio(array $cenario, Aluno $aluno): AvaliacaoAlunoDocumento
    {
        return $this->criarDocumento($cenario, $aluno, AvaliacaoAlunoDocumento::payloadVazio(), 0);
    }

    /** @param array<string, mixed> $cenario */
    private function criarDocumentoRespondido(
        array $cenario,
        Aluno $aluno,
        Alternativa $alternativa,
    ): AvaliacaoAlunoDocumento {
        return $this->criarDocumento($cenario, $aluno, $this->payloadRespondido($cenario, $alternativa), 1);
    }

    /** @param array<string, mixed> $cenario */
    private function criarDocumento(
        array $cenario,
        Aluno $aluno,
        array $payload,
        int $totalRespondidas,
    ): AvaliacaoAlunoDocumento {
        return AvaliacaoAlunoDocumento::query()->create([
            'avaliacao_id' => $cenario['avaliacao']->id,
            'aluno_id' => $aluno->id,
            'cgm' => $aluno->cgm,
            'turma_id' => $cenario['turma']->id,
            'escola_id' => $cenario['turma']->id_escola,
            'serie_id' => $cenario['turma']->id_serie,
            'payload' => $payload,
            'alternativa_ids' => [],
            'professor_ids' => [],
            'pauta_ids_respondidas' => $totalRespondidas > 0 ? [$cenario['pauta']->id] : [],
            'total_pautas_esperadas' => 1,
            'total_pautas_respondidas' => $totalRespondidas,
            'total_infos_complementares' => 0,
            'status_preenchimento' => $totalRespondidas > 0
                ? AvaliacaoAlunoDocumento::STATUS_COMPLETO
                : AvaliacaoAlunoDocumento::STATUS_VAZIO,
            'observacoes_obrigatorias_pendentes' => 0,
            'version' => 1,
        ]);
    }

    /** @param array<string, mixed> $cenario */
    private function payloadRespondido(array $cenario, Alternativa $alternativa): array
    {
        return [
            'v' => 1,
            'pautas' => [
                (string) $cenario['pauta']->id => [
                    'pauta_id' => $cenario['pauta']->id,
                    'alternativa_id' => $alternativa->id,
                    'componente_curricular_id' => $cenario['componente']->id,
                    'respondido_em' => now()->toIso8601String(),
                ],
            ],
            'informacoes_complementares' => [],
        ];
    }
}
