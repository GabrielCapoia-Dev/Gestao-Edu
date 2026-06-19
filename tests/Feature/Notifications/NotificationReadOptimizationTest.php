<?php

namespace Tests\Feature\Notifications;

use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\Escola;
use App\Models\Pedido;
use App\Models\TipoManutencao;
use App\Models\TipoStatus;
use App\Models\User;
use App\Notifications\SistemaNotification;
use App\Services\NotificationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NotificationReadOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_count_endpoint_returns_only_light_user_scoped_payload(): void
    {
        $user = $this->userWithNotificationAccess();
        $otherUser = $this->userWithNotificationAccess();

        $user->notify(new SistemaNotification('Aviso do usuario', 'Mensagem'));
        $otherUser->notify(new SistemaNotification('Aviso de outro usuario', 'Mensagem'));

        $response = $this
            ->actingAs($user)
            ->getJson(route('notifications.unreadCount'));

        $response->assertOk();

        $payload = $response->json();

        $this->assertSame(['unread', 'change_token'], array_keys($payload));
        $this->assertSame(1, $payload['unread']);
        $this->assertIsString($payload['change_token']);
    }

    public function test_mark_read_invalidates_unread_count_cache(): void
    {
        config()->set('notifications.unread_count_cache_ttl', 300);

        $user = $this->userWithNotificationAccess();
        $user->notify(new SistemaNotification('Aviso', 'Mensagem'));

        $notificationId = $user->notifications()->value('id');
        $service = app(NotificationCenterService::class);

        $this->assertSame(1, $service->unreadCountPayload($user)['unread']);

        $this
            ->actingAs($user)
            ->postJson(route('notifications.markRead', ['id' => $notificationId]))
            ->assertOk();

        $this->assertSame(0, $service->unreadCountPayload($user)['unread']);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = $this->userWithNotificationAccess();
        $otherUser = $this->userWithNotificationAccess();
        $otherUser->notify(new SistemaNotification('Aviso privado', 'Mensagem'));

        $otherNotification = $otherUser->notifications()->firstOrFail();

        $this
            ->actingAs($user)
            ->postJson(route('notifications.markRead', ['id' => $otherNotification->id]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNull($otherNotification->fresh()->read_at);
        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $otherUser->unreadNotifications()->count());
    }

    public function test_center_payload_enriches_order_notification_with_order_details(): void
    {
        $user = $this->userWithNotificationAccess();
        $escola = Escola::create(['codigo' => '001', 'nome' => 'Escola Central', 'ativo' => true]);
        $tipo = TipoManutencao::create(['nome' => 'Eletrica', 'ativo' => true]);
        $status = TipoStatus::create(['nome' => 'Em Aberto', 'ativo' => true]);
        $pedido = Pedido::create([
            'tipo_manutencao_id' => $tipo->id,
            'tipo_status_id' => $status->id,
            'descricao_pedido' => 'Troca de lampadas.',
            'nome_solicitante' => 'Direcao',
            'nivel_prioridade' => NivelEmergenciaPedido::INDEFINIDO,
            'escola_id' => $escola->id,
            'solicitante_id' => $user->id,
            'data_solicitacao' => now(),
            'data_identificacao_problema' => now(),
            'ativo' => true,
        ]);

        $user->notify(new SistemaNotification(
            titulo: 'Pedido Proximo do Vencimento',
            mensagem: "Pedido {$pedido->numero_protocolo} vence em 3 dia(s).",
            url: url("/admin/pedidos/{$pedido->id}/edit"),
        ));

        $this
            ->actingAs($user)
            ->getJson(route('notifications.center'))
            ->assertOk()
            ->assertJsonPath('items.0.pedido.protocolo', $pedido->numero_protocolo)
            ->assertJsonPath('items.0.pedido.escola', 'Escola Central')
            ->assertJsonPath('items.0.pedido.tipo', 'Eletrica')
            ->assertJsonPath('items.0.pedido.status', 'Em Aberto');
    }

    public function test_user_can_delete_own_notification_without_deleting_others(): void
    {
        $user = $this->userWithNotificationAccess();
        $otherUser = $this->userWithNotificationAccess();

        $user->notify(new SistemaNotification('Aviso do usuario', 'Mensagem'));
        $otherUser->notify(new SistemaNotification('Aviso de outro usuario', 'Mensagem'));

        $notificationId = $user->notifications()->value('id');

        $this
            ->actingAs($user)
            ->deleteJson(route('notifications.delete', ['id' => $notificationId]))
            ->assertOk()
            ->assertJsonPath('deleted', 1);

        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame(1, $otherUser->notifications()->count());
    }

    public function test_user_can_delete_all_own_notifications_without_deleting_others(): void
    {
        $user = $this->userWithNotificationAccess();
        $otherUser = $this->userWithNotificationAccess();

        $user->notify(new SistemaNotification('Aviso 1', 'Mensagem'));
        $user->notify(new SistemaNotification('Aviso 2', 'Mensagem'));
        $otherUser->notify(new SistemaNotification('Aviso de outro usuario', 'Mensagem'));

        $this
            ->actingAs($user)
            ->deleteJson(route('notifications.deleteAll'))
            ->assertOk()
            ->assertJsonPath('deleted', 2);

        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame(1, $otherUser->notifications()->count());
    }

    protected function userWithNotificationAccess(): User
    {
        Permission::findOrCreate('Visualizar Notificações');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $user->givePermissionTo('Visualizar Notificações');

        return $user;
    }
}
