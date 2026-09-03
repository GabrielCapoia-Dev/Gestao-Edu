<?php

namespace Tests\Feature\Exports;

use App\Jobs\ImportAlunosMatriculadosJob;
use App\Jobs\Middleware\LimitAvaliacaoExportConcurrency;
use App\Jobs\ProcessExportRequestJob;
use App\Models\ExportRequest;
use App\Models\User;
use App\Services\Exports\ExportRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExportQueueRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_exportacoes_e_importacoes_usam_filas_redis_separadas(): void
    {
        $exportJob = new ProcessExportRequestJob('export-request-de-teste');
        $importJob = new ImportAlunosMatriculadosJob(
            caminhoArquivo: 'imports/alunos.xlsx',
            usuarioId: null,
        );

        $this->assertSame('exports_redis', $exportJob->connection);
        $this->assertSame('exports', $exportJob->queue);
        $this->assertSame('imports', $importJob->queue);
        $this->assertSame('redis', config('queue.connections.exports_redis.driver'));
        $this->assertSame('default', config('queue.connections.exports_redis.connection'));
        $this->assertSame('exports', config('queue.connections.exports_redis.queue'));
        $this->assertGreaterThan(
            (int) config('exports.job_timeout', 900),
            (int) config('queue.connections.exports_redis.retry_after'),
        );
    }

    public function test_exportacao_de_avaliacao_aplica_limite_global_distribuido(): void
    {
        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $request = ExportRequest::query()->create([
            'user_id' => $user->id,
            'type' => 'avaliacao_documento',
            'format' => 'pdf',
            'filters' => ['avaliacao_id' => 1],
            'metadata' => [],
            'fingerprint' => fake()->sha256(),
            'status' => ExportRequest::STATUS_QUEUED,
            'status_message' => 'Aguardando processamento.',
            'progress_current' => 0,
            'progress_total' => 100,
        ]);

        $middleware = (new ProcessExportRequestJob((string) $request->getKey()))->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(LimitAvaliacaoExportConcurrency::class, $middleware[0]);
        $this->assertSame(2, (int) config('exports.avaliacao_max_concurrent'));
    }

    public function test_exportacao_de_arquivo_habilita_download_automatico_por_padrao(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $exportRequest = app(ExportRequestService::class)->queue(
            user: $user,
            type: 'pedido_relatorio_simplificado',
            format: 'pdf',
            filters: ['pedido_ids' => [1, 2, 3]],
            label: 'PDF simplificado de pedidos',
        );

        $this->assertTrue((bool) data_get($exportRequest->metadata, 'auto_download'));

        Queue::assertPushed(ProcessExportRequestJob::class, function (ProcessExportRequestJob $job): bool {
            return $job->connection === 'exports_redis'
                && $job->queue === 'exports';
        });
    }

    public function test_comando_de_recuperacao_reenfileira_exportacao_que_ficou_parada(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $exportRequest = ExportRequest::query()->create([
            'user_id' => $user->id,
            'session_hash' => hash('sha256', 'sessao-exportacao-parada'),
            'session_expires_at' => now()->addHour(),
            'expires_at' => now()->addHour(),
            'type' => 'pedido_relatorio_simplificado',
            'format' => 'pdf',
            'label' => 'PDF parado',
            'filters' => ['pedido_ids' => [1]],
            'metadata' => ['auto_download' => true],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_QUEUED,
            'status_message' => 'Aguardando processamento.',
            'progress_current' => 0,
            'progress_total' => 100,
        ]);

        Artisan::call('exports:recover-queued', ['--limit' => 100]);

        Queue::assertPushed(ProcessExportRequestJob::class, function (ProcessExportRequestJob $job) use ($exportRequest): bool {
            return $job->exportRequestId === (string) $exportRequest->getKey()
                && $job->connection === 'exports_redis'
                && $job->queue === 'exports';
        });

        $this->assertNotNull(
            data_get($exportRequest->refresh()->metadata, 'recovery_dispatch_at')
        );
    }
}
