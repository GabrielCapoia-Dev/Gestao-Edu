<?php

namespace Tests\Feature\Notifications;

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
