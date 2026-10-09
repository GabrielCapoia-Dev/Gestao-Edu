<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\GoogleService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Tests\TestCase;

class InactiveUserAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_cannot_login_manually_on_admin(): void
    {
        $user = User::factory()->create([
            'email_approved' => true,
            'ativo' => false,
            'password' => Hash::make('Senha@1234'),
        ]);

        $this->post(route('admin.login.store'), [
            'email' => $user->email,
            'password' => 'Senha@1234',
        ])->assertRedirect(route('filament.admin.auth.login'));

        $this->assertGuest();
    }

    public function test_google_login_does_not_reactivate_inactive_or_archived_account(): void
    {
        $user = User::factory()->create([
            'email' => 'inativo@example.com',
            'email_approved' => true,
            'ativo' => false,
        ]);

        try {
            app(GoogleService::class)->registrarOuLogar($this->fakeOAuthUser($user->email));
            $this->fail('O login de conta inativa deveria ser bloqueado.');
        } catch (DomainException) {
            $this->assertFalse($user->fresh()->ativo);
        }

        $user->delete();

        try {
            app(GoogleService::class)->registrarOuLogar($this->fakeOAuthUser($user->email));
            $this->fail('O login de conta arquivada deveria ser bloqueado.');
        } catch (DomainException) {
            $this->assertSame(1, User::withTrashed()->where('email', $user->email)->count());
            $this->assertTrue(User::withTrashed()->findOrFail($user->id)->trashed());
        }
    }

    public function test_google_login_prioritizes_primary_email_over_archived_google_alias(): void
    {
        $archived = User::factory()->create([
            'email' => 'conta-antiga@example.com',
            'google_email' => 'conta-ativa@example.com',
            'ativo' => false,
        ]);
        $archived->delete();

        $active = User::factory()->create([
            'email' => 'conta-ativa@example.com',
            'email_approved' => true,
            'ativo' => true,
        ]);

        $resolved = app(GoogleService::class)->registrarOuLogar($this->fakeOAuthUser($active->email));

        $this->assertSame($active->id, $resolved->id);
        $this->assertTrue(User::withTrashed()->findOrFail($archived->id)->trashed());
    }

    private function fakeOAuthUser(string $email): SocialiteUserContract
    {
        return new class($email) implements SocialiteUserContract {
            public function __construct(private readonly string $email) {}

            public function getId()
            {
                return 'google-'.md5($this->email);
            }

            public function getNickname()
            {
                return null;
            }

            public function getName()
            {
                return 'Usuário Inativo';
            }

            public function getEmail()
            {
                return $this->email;
            }

            public function getAvatar()
            {
                return null;
            }
        };
    }
}
