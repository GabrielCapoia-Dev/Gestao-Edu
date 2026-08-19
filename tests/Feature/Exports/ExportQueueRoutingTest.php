<?php

namespace Tests\Feature\Exports;

use App\Jobs\ImportAlunosMatriculadosJob;
use App\Jobs\ProcessExportRequestJob;
use App\Models\User;
use App\Services\Exports\ExportRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExportQueueRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_jobs_usam_filas_internas_fixas(): void
    {
        $exportJob = new ProcessExportRequestJob('export-request-de-teste');
        $importJob = new ImportAlunosMatriculadosJob(
            caminhoArquivo: 'imports/alunos.xlsx',
            usuarioId: null,
        );

        $this->assertSame('exports', $exportJob->queue);
        $this->assertSame('imports', $importJob->queue);
        $this->assertSame('exports', config('exports.queue'));
        $this->assertSame('imports', config('imports.queue'));
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
            return $job->queue === 'exports';
        });
    }
}
