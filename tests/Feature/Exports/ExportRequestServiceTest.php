<?php

namespace Tests\Feature\Exports;

use App\Jobs\ProcessExportRequestJob;
use App\Models\ExportRequest;
use App\Models\User;
use App\Services\Exports\ExportRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExportRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_exportacao_e_reaproveita_solicitacao_ativa_com_mesmos_filtros(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $service = app(ExportRequestService::class);

        $first = $service->queue(
            user: $user,
            type: 'pedido_relatorio_geral',
            format: 'pdf',
            filters: ['data_inicio' => '2026-01-01', 'data_fim' => '2026-01-31'],
            label: 'Relatorio geral de pedidos',
        );

        $second = $service->queue(
            user: $user,
            type: 'pedido_relatorio_geral',
            format: 'pdf',
            filters: ['data_fim' => '2026-01-31', 'data_inicio' => '2026-01-01'],
            label: 'Relatorio geral de pedidos',
        );

        $this->assertTrue($first->is($second));
        $this->assertDatabaseHas('export_requests', [
            'id' => $first->getKey(),
            'user_id' => $user->id,
            'type' => 'pedido_relatorio_geral',
            'format' => 'pdf',
            'status' => ExportRequest::STATUS_QUEUED,
        ]);

        Queue::assertPushed(ProcessExportRequestJob::class, 1);
    }

    public function test_limita_quantidade_de_exportacoes_ativas_por_usuario(): void
    {
        Queue::fake();
        config()->set('exports.max_active_per_user', 1);

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $service = app(ExportRequestService::class);

        $service->queue($user, 'pedido_relatorio_geral', 'pdf', ['data_inicio' => '2026-01-01']);

        $this->expectException(\RuntimeException::class);

        $service->queue($user, 'pedido_relatorio_geral', 'pdf', ['data_inicio' => '2026-02-01']);
    }
}
