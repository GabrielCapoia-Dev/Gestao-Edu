<?php

namespace Tests\Feature\Exports;

use App\Jobs\ProcessExportRequestJob;
use App\Models\ExportRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Exports\ExportRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

    public function test_tela_de_minhas_exportacoes_ativa_polling_para_download_automatico(): void
    {
        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole(Role::findOrCreate('Admin', 'web'));

        $exportRequest = $this->finishedExportRequest($user);

        $this->actingAs($user)
            ->get(route('filament.admin.pages.minhas-exportacoes', ['download' => $exportRequest->getKey()]))
            ->assertOk()
            ->assertSee('wire:poll.3s="pollAutoDownload"', false);
    }

    public function test_rota_de_download_entrega_arquivo_pronto(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $exportRequest = $this->finishedExportRequest($user);
        Storage::disk('local')->put($exportRequest->file_path, 'conteudo do relatorio');

        $this->actingAs($user)
            ->get(route('exports.download', $exportRequest))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    private function finishedExportRequest(User $user): ExportRequest
    {
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

        $exportRequest->markFinished([
            'disk' => 'local',
            'path' => 'exports/teste/relatorio.pdf',
            'file_name' => 'relatorio.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 19,
            'checksum' => sha1('conteudo do relatorio'),
        ]);

        return $exportRequest->refresh();
    }
}
