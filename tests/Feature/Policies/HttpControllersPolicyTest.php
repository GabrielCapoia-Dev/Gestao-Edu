<?php

namespace Tests\Feature\Policies;

use App\Models\NotificacaoEnvio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class HttpControllersPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_notification_center_index_retorna_403_sem_permissao(): void
    {
        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson(route('notifications.center'))
            ->assertForbidden();
    }

    public function test_notification_center_index_retorna_200_com_permissao(): void
    {
        Permission::findOrCreate('Visualizar Notificações');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo('Visualizar Notificações');

        $this->actingAs($user)
            ->getJson(route('notifications.center'))
            ->assertOk();
    }

    public function test_notification_send_retorna_403_sem_permissao_criar(): void
    {
        Permission::findOrCreate('Visualizar Notificações');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo('Visualizar Notificações');

        $this->actingAs($user)
            ->postJson(route('notifications.send'), [
                'titulo' => 'Teste',
                'mensagem' => 'Mensagem',
                'prioridade' => 'normal',
                'destino_tipo' => 'todos',
            ])
            ->assertForbidden();
    }
}