<?php

namespace Tests\Feature\Exports;

use App\Models\ExportRequest;
use App\Models\User;
use App\Services\Exports\StalledExportRequestMonitorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StalledExportRequestMonitorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancela_exportacao_em_fila_que_ficou_parada_e_registra_falha_na_fila(): void
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
        $exportRequest->timestamps = false;
        $exportRequest->forceFill([
            'created_at' => now()->subMinutes(40),
            'updated_at' => now()->subMinutes(40),
        ])->save();
        $exportRequest->timestamps = true;

        $result = app(StalledExportRequestMonitorService::class)->handle(queuedMinutes: 30, runningMinutes: 45);

        $exportRequest->refresh();

        $this->assertSame(1, $result['checked']);
        $this->assertSame(1, $result['cancelled']);
        $this->assertSame(ExportRequest::STATUS_CANCELLED, $exportRequest->status);
        $this->assertSame('Exportacao cancelada por inatividade na fila.', $exportRequest->status_message);
        $this->assertStringContainsString((string) $exportRequest->getKey(), (string) $exportRequest->error_message);
        $this->assertNotNull($exportRequest->finished_at);

        $failedJob = DB::table('failed_jobs')->first();

        $this->assertNotNull($failedJob);
        $this->assertStringContainsString((string) $exportRequest->getKey(), $failedJob->payload);
        $this->assertStringContainsString('cancelada automaticamente', $failedJob->exception);
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
        $this->assertSame(0, $result['cancelled']);
        $this->assertSame(ExportRequest::STATUS_QUEUED, $exportRequest->status);
        $this->assertDatabaseCount('failed_jobs', 0);
    }
}
