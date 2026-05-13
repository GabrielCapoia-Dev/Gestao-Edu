<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendManualNotificationBatchJob;
use App\Models\NotificacaoEnvio;
use App\Models\User;
use App\Services\NotificationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ManualNotificationQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_send_creates_tracking_record_and_queues_batch_job(): void
    {
        Queue::fake();

        $autor = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $destinatario = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $result = app(NotificationCenterService::class)->send($autor, [
            'titulo' => 'Aviso',
            'mensagem' => 'Mensagem importante',
            'prioridade' => 'normal',
            'destino_tipo' => 'usuarios',
            'usuarios_ids' => [$destinatario->id],
        ]);

        $this->assertSame(1, $result['count']);
        $this->assertTrue($result['queued']);
        $this->assertDatabaseHas('notificacao_envios', [
            'id' => $result['envio_id'],
            'status' => NotificacaoEnvio::STATUS_QUEUED,
            'destinatarios_count' => 1,
        ]);
        $this->assertDatabaseCount('notifications', 0);

        Queue::assertPushedOn(
            config('notifications.queue', 'notifications'),
            SendManualNotificationBatchJob::class
        );
    }

    public function test_manual_notification_batch_job_is_idempotent(): void
    {
        $autor = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $destinatarios = User::factory()->count(2)->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $envio = NotificacaoEnvio::query()->create([
            'user_id' => $autor->id,
            'titulo' => 'Comunicado',
            'mensagem' => 'Mensagem enviada em lote',
            'prioridade' => 'alta',
            'destino_tipo' => 'usuarios',
            'destino_label' => 'Usuarios especificos',
            'destinatarios_count' => $destinatarios->count(),
            'destinatarios_ids' => $destinatarios->pluck('id')->all(),
            'filtros' => ['destino_tipo' => 'usuarios'],
            'status' => NotificacaoEnvio::STATUS_QUEUED,
            'queued_at' => now(),
        ]);

        $job = new SendManualNotificationBatchJob((string) $envio->id);
        $job->handle();
        $job->handle();

        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notificacao_envios', [
            'id' => $envio->id,
            'status' => NotificacaoEnvio::STATUS_PROCESSED,
        ]);

        foreach ($destinatarios as $destinatario) {
            $this->assertDatabaseHas('notifications', [
                'notifiable_type' => User::class,
                'notifiable_id' => $destinatario->id,
            ]);
        }
    }
}
