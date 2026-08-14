<?php

namespace Tests\Feature\Exports;

use App\Contracts\Exports\ExportHandler;
use App\Http\Controllers\Exports\ExportRequestController;
use App\Jobs\ProcessExportRequestJob;
use App\Models\ExportRequest;
use App\Models\FuncaoAdministrativa;
use App\Models\Pessoa;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;
use App\Services\Exports\ExportFileResult;
use App\Services\Exports\ExportManager;
use App\Services\Exports\ExportRequestService;
use App\Services\Exports\ExportSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
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
            'user_id_legado' => $user->id,
            'user_nome_snapshot' => $user->name,
            'user_email_snapshot' => $user->email,
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

    public function test_tela_legada_de_exportacoes_nao_aparece_mais_na_navegacao(): void
    {
        $this->assertFalse(\App\Filament\Admin\Pages\MinhasExportacoes::shouldRegisterNavigation());
    }

    public function test_rota_de_download_entrega_arquivo_pronto(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $this->makeOperational($user);

        Route::middleware('web')->get('/_test/baixar-exportacao-da-sessao', function () use ($user) {
            $exportRequest = $this->finishedExportRequest($user);
            Storage::disk('local')->put($exportRequest->file_path, 'conteudo do relatorio');
            Gate::authorize('download', $exportRequest);

            return app(ExportRequestController::class)->download($exportRequest);
        });

        $this->actingAs($user)
            ->get('/_test/baixar-exportacao-da-sessao')
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_cancelamento_de_export_request_cancela_imediatamente(): void
    {
        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $exportRequest = ExportRequest::query()->create([
            ...$this->activeSessionPayload(),
            'user_id' => $user->id,
            'type' => 'alunos_importacao_planilha',
            'format' => 'processo',
            'label' => 'Importacao de alunos por planilha',
            'filters' => [],
            'metadata' => [],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_QUEUED,
            'status_message' => 'Aguardando processamento.',
            'progress_current' => 0,
            'progress_total' => 100,
        ]);

        $exportRequest->requestCancellation();

        $exportRequest->refresh();

        $this->assertSame(ExportRequest::STATUS_CANCELLED, $exportRequest->status);
        $this->assertSame('Cancelado pelo usuario.', $exportRequest->status_message);
        $this->assertNotNull($exportRequest->finished_at);
    }

    public function test_job_marca_exportacao_como_falha_quando_worker_interrompe_antes_do_catch(): void
    {
        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $exportRequest = ExportRequest::query()->create([
            ...$this->activeSessionPayload(),
            'user_id' => $user->id,
            'type' => 'pedido_relatorio_geral',
            'format' => 'pdf',
            'label' => 'Relatorio geral de pedidos',
            'filters' => [],
            'metadata' => [],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_RUNNING,
            'status_message' => 'Preparando consulta de pedidos.',
            'progress_current' => 10,
            'progress_total' => 100,
            'started_at' => now()->subMinutes(2),
        ]);

        $job = new ProcessExportRequestJob($exportRequest->getKey());
        $job->failed(new \RuntimeException('Processo excedeu o tempo limite.'));

        $exportRequest->refresh();

        $this->assertSame(ExportRequest::STATUS_FAILED, $exportRequest->status);
        $this->assertSame('Falha ao gerar arquivo.', $exportRequest->status_message);
        $this->assertSame('Processo excedeu o tempo limite.', $exportRequest->error_message);
        $this->assertNotNull($exportRequest->finished_at);
    }

    public function test_job_mantem_exportacao_na_fila_quando_uma_tentativa_falha(): void
    {
        config()->set('exports.handlers.teste_falha_temporaria', TemporaryFailingExportHandler::class);

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $exportRequest = ExportRequest::query()->create([
            ...$this->activeSessionPayload(),
            'user_id' => $user->id,
            'type' => 'teste_falha_temporaria',
            'format' => 'pdf',
            'label' => 'Exportacao temporaria',
            'filters' => [],
            'metadata' => [],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_QUEUED,
            'status_message' => 'Aguardando processamento.',
            'progress_current' => 0,
            'progress_total' => 100,
        ]);

        $job = new ProcessExportRequestJob($exportRequest->getKey());

        try {
            $job->handle(app(ExportManager::class));
            $this->fail('A falha temporaria do handler deveria ser propagada ao worker.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Falha temporaria de teste.', $exception->getMessage());
        }

        $exportRequest->refresh();

        $this->assertSame(3, $job->tries);
        $this->assertFalse($job->failOnTimeout);
        $this->assertSame(ExportRequest::STATUS_QUEUED, $exportRequest->status);
        $this->assertSame('Falha temporária. Uma nova tentativa será executada automaticamente.', $exportRequest->status_message);
        $this->assertSame('Falha temporaria de teste.', $exportRequest->error_message);
        $this->assertNull($exportRequest->finished_at);
    }

    private function finishedExportRequest(User $user): ExportRequest
    {
        $exportRequest = ExportRequest::query()->create([
            ...$this->currentSessionPayload(),
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

    private function activeSessionPayload(): array
    {
        return [
            'session_hash' => hash('sha256', 'sessao-de-teste'),
            'session_expires_at' => now()->addHour(),
            'expires_at' => now()->addHour(),
        ];
    }

    private function currentSessionPayload(): array
    {
        return app(ExportSessionService::class)->ownershipPayload(request());
    }

    private function makeOperational(User $user): void
    {
        $pessoa = Pessoa::query()->create([
            'user_id' => $user->id,
            'nome' => $user->name,
            'email' => $user->email,
            'status' => Pessoa::STATUS_ATIVO,
        ]);
        $funcao = FuncaoAdministrativa::query()->create([
            'nome' => 'Cargo de teste',
            'codigo' => 'cargo_exportacao_teste',
            'categoria' => FuncaoAdministrativa::CATEGORIA_ADMINISTRATIVO,
            'ativo' => true,
            'exige_professor' => false,
            'concede_acesso_sistema' => true,
        ]);

        ServidorFuncaoAdministrativa::query()->create([
            'servidor_id' => $pessoa->id,
            'funcao_administrativa_id' => $funcao->id,
            'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
            'origem' => 'teste',
        ]);
    }
}

class TemporaryFailingExportHandler implements ExportHandler
{
    public function handle(ExportRequest $exportRequest): ExportFileResult
    {
        throw new \RuntimeException('Falha temporaria de teste.');
    }
}
