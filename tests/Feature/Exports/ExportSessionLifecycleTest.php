<?php

namespace Tests\Feature\Exports;

use App\Filament\Admin\Pages\MinhasExportacoes;
use App\Livewire\ExportQueueTopbar;
use App\Models\ExportRequest;
use App\Models\User;
use App\Services\Exports\ExportSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportSessionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_remove_imediatamente_o_arquivo_da_sessao_atual(): void
    {
        Storage::fake('local');
        $this->bindSession('sessao-logout');

        $user = User::factory()->create();
        Auth::login($user);

        $exportRequest = $this->finishedExportRequest($user);
        Storage::disk('local')->put($exportRequest->file_path, 'relatorio');

        Auth::logout();

        $exportRequest->refresh();

        Storage::disk('local')->assertMissing('exports/teste/relatorio.pdf');
        $this->assertSame(ExportRequest::STATUS_EXPIRED, $exportRequest->status);
        $this->assertNotNull($exportRequest->session_ended_at);
        $this->assertNull($exportRequest->file_path);
    }

    public function test_sessao_inativa_e_expirada_pelo_monitor_e_remove_o_arquivo(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $exportRequest = $this->finishedExportRequest($user, [
            'session_hash' => hash('sha256', 'sessao-expirada'),
            'session_expires_at' => now()->subMinute(),
            'expires_at' => now()->subMinute(),
        ]);
        Storage::disk('local')->put($exportRequest->file_path, 'relatorio');

        $result = app(ExportSessionService::class)->expireInactiveSessions();

        $exportRequest->refresh();

        $this->assertSame(['expired' => 1, 'files_deleted' => 1], $result);
        Storage::disk('local')->assertMissing('exports/teste/relatorio.pdf');
        $this->assertSame(ExportRequest::STATUS_EXPIRED, $exportRequest->status);
    }

    public function test_comando_de_limpeza_de_exportacoes_conclui_sem_sessoes_expiradas(): void
    {
        Storage::fake('local');

        $this->artisan('exports:prune')
            ->assertExitCode(0);
    }

    public function test_arquivo_concluido_depois_do_encerramento_da_sessao_e_descartado(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $exportRequest = ExportRequest::query()->create([
            'user_id' => $user->id,
            'session_hash' => hash('sha256', 'sessao-encerrada-durante-o-job'),
            'session_expires_at' => now(),
            'session_ended_at' => now(),
            'expires_at' => now(),
            'type' => 'pedido_relatorio_geral',
            'format' => 'pdf',
            'label' => 'Relatório concorrente',
            'filters' => [],
            'metadata' => [],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_RUNNING,
            'status_message' => 'Finalizando arquivo.',
            'progress_current' => 95,
            'progress_total' => 100,
        ]);
        Storage::disk('local')->put('exports/teste/concorrente.pdf', 'relatorio');

        $exportRequest->markFinished([
            'disk' => 'local',
            'path' => 'exports/teste/concorrente.pdf',
            'file_name' => 'concorrente.pdf',
            'mime' => 'application/pdf',
            'size_bytes' => 9,
            'checksum' => sha1('relatorio'),
        ]);

        Storage::disk('local')->assertMissing('exports/teste/concorrente.pdf');
        $this->assertSame(ExportRequest::STATUS_EXPIRED, $exportRequest->refresh()->status);
        $this->assertNull($exportRequest->file_path);
    }

    public function test_processo_de_negocio_em_execucao_termina_com_seguranca_apos_logout_e_e_descartado(): void
    {
        $user = User::factory()->create();
        $sessionHash = hash('sha256', 'sessao-processo');

        $processo = ExportRequest::query()->create([
            'user_id' => $user->id,
            'session_hash' => $sessionHash,
            'session_expires_at' => now()->addHour(),
            'expires_at' => now()->addHour(),
            'type' => 'alunos_exclusao_massa',
            'format' => 'processo',
            'label' => 'Exclusão de alunos',
            'filters' => [],
            'metadata' => [],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_RUNNING,
            'status_message' => 'Excluindo alunos.',
            'progress_current' => 30,
            'progress_total' => 100,
        ]);

        app(ExportSessionService::class)->endSession($sessionHash, $user->id);

        $this->assertSame(ExportRequest::STATUS_RUNNING, $processo->refresh()->status);
        $this->assertNotNull($processo->session_ended_at);

        $processo->markProcessFinished('Exclusão concluída.');

        $this->assertSame(ExportRequest::STATUS_EXPIRED, $processo->refresh()->status);
    }

    public function test_processo_de_negocio_ainda_na_fila_e_descartado_ao_encerrar_a_sessao(): void
    {
        $user = User::factory()->create();
        $sessionHash = hash('sha256', 'sessao-processo-na-fila');

        $processo = ExportRequest::query()->create([
            'user_id' => $user->id,
            'session_hash' => $sessionHash,
            'session_expires_at' => now()->addHour(),
            'expires_at' => now()->addHour(),
            'type' => 'alunos_importacao_planilha',
            'format' => 'processo',
            'label' => 'Importação de alunos',
            'filters' => [],
            'metadata' => [],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_QUEUED,
            'status_message' => 'Aguardando processamento.',
            'progress_current' => 0,
            'progress_total' => 100,
        ]);

        app(ExportSessionService::class)->endSession($sessionHash, $user->id);

        $this->assertSame(ExportRequest::STATUS_EXPIRED, $processo->refresh()->status);
        $this->assertNotNull($processo->session_ended_at);
    }

    public function test_painel_do_topo_lista_somente_a_sessao_atual_com_progresso_real(): void
    {
        $this->bindSession('sessao-atual');
        $user = User::factory()->create();
        Auth::login($user);

        $sessions = app(ExportSessionService::class);
        $currentHash = $sessions->currentSessionHash();

        $current = ExportRequest::query()->create([
            'user_id' => $user->id,
            'session_hash' => $currentHash,
            'session_expires_at' => now()->addHour(),
            'expires_at' => now()->addHour(),
            'type' => 'pedido_relatorio_geral',
            'format' => 'pdf',
            'label' => 'Relatório atual',
            'filters' => [],
            'metadata' => [],
            'fingerprint' => fake()->uuid(),
            'status' => ExportRequest::STATUS_RUNNING,
            'status_message' => 'Gerando relatório.',
            'progress_current' => 35,
            'progress_total' => 100,
        ]);

        $other = $this->finishedExportRequest($user, [
            'session_hash' => hash('sha256', 'outra-sessao'),
            'session_expires_at' => now()->addHour(),
            'expires_at' => now()->addHour(),
        ]);

        $component = new ExportQueueTopbar;
        $component->open = true;
        $data = $component->render()->getData();

        $this->assertSame(1, $data['activeCount']);
        $this->assertSame(0, $data['readyCount']);
        $this->assertCount(1, $data['items']);
        $this->assertSame($current->getKey(), $data['items']->first()->getKey());
        $this->assertSame(35, $data['items']->first()->progress_percentage);
        $this->assertFalse(Gate::forUser($user)->allows('download', $other));
        $this->assertFalse(MinhasExportacoes::shouldRegisterNavigation());
    }

    private function bindSession(string $sessionId): void
    {
        $request = Request::create('/admin');
        $session = app('session')->driver();
        $session->setId($sessionId);
        $session->start();
        $session->put('auth.login_at', now()->timestamp);
        $request->setLaravelSession($session);

        app()->instance('request', $request);
    }

    private function finishedExportRequest(User $user, array $session = []): ExportRequest
    {
        $session = $session ?: app(ExportSessionService::class)->ownershipPayload();

        $exportRequest = ExportRequest::query()->create([
            ...$session,
            'user_id' => $user->id,
            'type' => 'pedido_relatorio_geral',
            'format' => 'pdf',
            'label' => 'Relatório geral',
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
            'size_bytes' => 9,
            'checksum' => sha1('relatorio'),
        ]);

        return $exportRequest->refresh();
    }
}
