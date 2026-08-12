<?php

namespace Tests\Feature\Exports;

use App\Jobs\ProcessExportRequestJob;
use App\Models\ExportRequest;
use App\Models\User;
use App\Services\Exports\StalledExportRequestMonitorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StalledExportRequestMonitorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reencaminha_exportacao_em_fila_que_ficou_parada(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $exportRequest = ExportRequest::query()->create([
            'user_id' => $user->id,
            'type' => 'pedido_relatorio_geral',
            'format' => 'pdf',
            'label' => 'Relatorio geral de pedidos',
            'filters' => [],
            'metadata' => [],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_QUEUED,
            'status_message' => 'Aguardando processamento.',
            'progress_current' => 0,
            'progress_total' => 100,
        ]);
        $exportRequest->timestamps = false;
        $exportRequest->forceFill([
            'created_at' => now()->subMinutes(40),
            'updated_at' => now()->subMinutes(40),
        ])->save();
        $exportRequest->timestamps = true;

        $result = app(StalledExportRequestMonitorService::class)->handle(queuedMinutes: 30, runningMinutes: 45);

        $exportRequest->refresh();

        $this->assertSame(1, $result['checked']);
        $this->assertSame(1, $result['requeued']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(0, $result['cancelled']);
        $this->assertSame(ExportRequest::STATUS_QUEUED, $exportRequest->status);
        $this->assertSame('Fila recuperada automaticamente. Tentativa 1 de 3.', $exportRequest->status_message);
        $this->assertSame(1, $exportRequest->metadata['automatic_recovery_attempts']);
        $this->assertNull($exportRequest->finished_at);
        $this->assertDatabaseCount('failed_jobs', 0);
        Queue::assertPushed(ProcessExportRequestJob::class, fn (ProcessExportRequestJob $job): bool => $job->exportRequestId === $exportRequest->getKey()
        );
    }

    public function test_cancela_processo_em_execucao_sem_progresso_apos_limite(): void
    {
        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $processo = ExportRequest::query()->create([
            'user_id' => $user->id,
            'type' => 'alunos_importacao_planilha',
            'format' => 'processo',
            'label' => 'Importacao de alunos por planilha',
            'filters' => [],
            'metadata' => ['process_kind' => 'importacao_alunos'],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_RUNNING,
            'status_message' => 'Importando alunos da planilha.',
            'progress_current' => 10,
            'progress_total' => 100,
        ]);
        $processo->timestamps = false;
        $processo->forceFill([
            'started_at' => now()->subMinutes(60),
            'created_at' => now()->subMinutes(60),
            'updated_at' => now()->subMinutes(60),
        ])->save();
        $processo->timestamps = true;

        $result = app(StalledExportRequestMonitorService::class)->handle(queuedMinutes: 30, runningMinutes: 45);

        $processo->refresh();

        $this->assertSame(1, $result['checked']);
        $this->assertSame(0, $result['requeued']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(1, $result['cancelled']);
        $this->assertSame(ExportRequest::STATUS_CANCELLED, $processo->status);
        $this->assertSame('Processo cancelado por inatividade na fila.', $processo->status_message);

        $failedJob = DB::table('failed_jobs')->first();

        $this->assertNotNull($failedJob);
        $this->assertSame(config('imports.queue', config('exports.queue', 'exports')), $failedJob->queue);
    }

    public function test_ignora_solicitacao_ativa_recente(): void
    {
        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $exportRequest = ExportRequest::query()->create([
            'user_id' => $user->id,
            'type' => 'pedido_relatorio_geral',
            'format' => 'pdf',
            'label' => 'Relatorio geral de pedidos',
            'filters' => [],
            'metadata' => [],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_QUEUED,
            'status_message' => 'Aguardando processamento.',
            'progress_current' => 0,
            'progress_total' => 100,
        ]);

        $result = app(StalledExportRequestMonitorService::class)->handle(queuedMinutes: 30, runningMinutes: 45);

        $exportRequest->refresh();

        $this->assertSame(0, $result['checked']);
        $this->assertSame(0, $result['requeued']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(0, $result['cancelled']);
        $this->assertSame(ExportRequest::STATUS_QUEUED, $exportRequest->status);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_finaliza_com_falha_depois_do_limite_de_recuperacoes_automaticas(): void
    {
        Queue::fake();
        config()->set('exports.stalled_max_recovery_attempts', 3);

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $exportRequest = ExportRequest::query()->create([
            'user_id' => $user->id,
            'type' => 'pedido_relatorio_geral',
            'format' => 'pdf',
            'label' => 'Relatorio geral de pedidos',
            'filters' => [],
            'metadata' => ['automatic_recovery_attempts' => 3],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_RUNNING,
            'status_message' => 'Processando exportacao.',
            'progress_current' => 10,
            'progress_total' => 100,
            'started_at' => now()->subHour(),
        ]);
        $exportRequest->timestamps = false;
        $exportRequest->forceFill(['updated_at' => now()->subHour()])->save();
        $exportRequest->timestamps = true;

        $result = app(StalledExportRequestMonitorService::class)->handle(queuedMinutes: 30, runningMinutes: 45);

        $exportRequest->refresh();

        $this->assertSame(1, $result['failed']);
        $this->assertSame(ExportRequest::STATUS_FAILED, $exportRequest->status);
        $this->assertStringContainsString('3 tentativas automáticas', (string) $exportRequest->error_message);
        $this->assertDatabaseCount('failed_jobs', 1);
        Queue::assertNothingPushed();
    }

    public function test_reencaminha_exportacao_cancelada_pelo_monitor_antigo_sem_reabrir_cancelamento_do_usuario(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $canceladaAutomaticamente = ExportRequest::query()->create([
            'user_id' => $user->id,
            'type' => 'avaliacao_documento',
            'format' => 'pdf',
            'label' => 'Documento de avaliacao',
            'filters' => [],
            'metadata' => [],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_CANCELLED,
            'status_message' => 'Exportacao cancelada por inatividade na fila.',
            'progress_current' => 0,
            'progress_total' => 100,
            'cancel_requested_at' => now()->subHour(),
            'finished_at' => now()->subHour(),
        ]);
        $canceladaPeloUsuario = ExportRequest::query()->create([
            'user_id' => $user->id,
            'type' => 'pedido_relatorio_geral',
            'format' => 'pdf',
            'label' => 'Relatorio cancelado pelo usuario',
            'filters' => [],
            'metadata' => [],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_CANCELLED,
            'status_message' => 'Cancelado pelo usuario.',
            'progress_current' => 0,
            'progress_total' => 100,
            'cancel_requested_at' => now()->subHour(),
            'finished_at' => now()->subHour(),
        ]);

        $result = app(StalledExportRequestMonitorService::class)->handle();

        $canceladaAutomaticamente->refresh();
        $canceladaPeloUsuario->refresh();

        $this->assertSame(1, $result['checked']);
        $this->assertSame(1, $result['requeued']);
        $this->assertSame(ExportRequest::STATUS_QUEUED, $canceladaAutomaticamente->status);
        $this->assertNull($canceladaAutomaticamente->cancel_requested_at);
        $this->assertSame(ExportRequest::STATUS_CANCELLED, $canceladaPeloUsuario->status);
        Queue::assertPushed(ProcessExportRequestJob::class, fn (ProcessExportRequestJob $job): bool => $job->exportRequestId === $canceladaAutomaticamente->getKey()
        );
    }
}
