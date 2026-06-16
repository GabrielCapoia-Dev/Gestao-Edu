<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminLoginNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_exibe_notificacoes_filament_e_flashes_uma_unica_vez(): void
    {
        $notifications = [
            Notification::make()
                ->title('Aguardando aprovação')
                ->body('Seu acesso depende da aprovação do administrador.')
                ->warning()
                ->toArray(),
            Notification::make()
                ->title('Falha ao autenticar com Google')
                ->body('Tente novamente em instantes.')
                ->danger()
                ->toArray(),
        ];

        $session = [
            'filament.notifications' => $notifications,
            'session_expired' => 'Sua sessão expirou. Faça login novamente.',
            'status' => 'Senha redefinida. Entre novamente usando a nova senha.',
        ];

        $this->withSession($session)
            ->get(route('filament.admin.auth.login'))
            ->assertOk()
            ->assertSee('Aguardando aprovação')
            ->assertSee('Falha ao autenticar com Google')
            ->assertSee('Sessão expirada')
            ->assertSee('Senha redefinida. Entre novamente usando a nova senha.');

        $this->get(route('filament.admin.auth.login'))
            ->assertOk()
            ->assertDontSee('Aguardando aprovação')
            ->assertDontSee('Falha ao autenticar com Google')
            ->assertDontSee('Sua sessão expirou. Faça login novamente.')
            ->assertDontSee('Senha redefinida. Entre novamente usando a nova senha.');
    }

    public function test_login_nao_duplica_avisos_identicos(): void
    {
        $notification = Notification::make()
            ->title('Acesso negado')
            ->body('Entre em contato com o administrador.')
            ->danger()
            ->toArray();

        $this->withSession([
            'filament.notifications' => [$notification, $notification],
        ])->get(route('filament.admin.auth.login'))
            ->assertOk()
            ->assertSeeTextInOrder([
                'Acesso negado',
                'Entre em contato com o administrador.',
            ])
            ->assertViewHas('loginNotices', fn (array $notices): bool => count($notices) === 1);
    }

    public function test_login_manual_sem_autorizacao_exibe_aviso_de_aprovacao(): void
    {
        $user = User::factory()->create([
            'email_approved' => false,
            'password' => Hash::make('Senha@1234'),
        ]);

        $this->post(route('admin.login.store'), [
            'email' => $user->email,
            'password' => 'Senha@1234',
        ])->assertRedirect(route('filament.admin.auth.login'));

        $this->get(route('filament.admin.auth.login'))
            ->assertOk()
            ->assertSee('Aguardando aprovação')
            ->assertSee('Entre em contato com o administrador');

        $this->assertGuest();
    }
}
