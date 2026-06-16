<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\GoogleService;
use DomainException;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GoogleAuthNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_google_pendente_exibe_aviso_no_login(): void
    {
        $user = User::factory()->create(['email_approved' => false]);

        $this->mockGoogleCallback($user);

        $this->get(route('google.callback'))
            ->assertRedirect(Filament::getPanel('admin')->getLoginUrl());

        $this->get(route('filament.admin.auth.login'))
            ->assertOk()
            ->assertSee('Aguardando aprovação')
            ->assertSee('o acesso ainda depende da aprovação do administrador');
    }

    public function test_login_google_aprovado_mantem_notificacao_de_sucesso_para_o_painel(): void
    {
        $user = User::factory()->create(['email_approved' => true]);

        $this->mockGoogleCallback($user);

        $this->get(route('google.callback'))
            ->assertRedirect(Filament::getPanel('admin')->getUrl())
            ->assertSessionHas('filament.notifications', function (array $notifications): bool {
                return collect($notifications)->contains(
                    fn (array $notification): bool => $notification['title'] === 'Acesso permitido'
                        && $notification['status'] === 'success'
                );
            });

        $this->assertAuthenticatedAs($user);
    }

    public function test_erro_de_sessao_google_exibe_mensagem_especifica(): void
    {
        $this->mockGoogleProviderException(new InvalidStateException);

        $this->get(route('google.callback'))
            ->assertRedirect(Filament::getPanel('admin')->getLoginUrl());

        $this->get(route('filament.admin.auth.login'))
            ->assertOk()
            ->assertSee('Falha ao autenticar com Google')
            ->assertSee('Sua sessão expirou durante o login com Google');
    }

    public function test_erro_de_negocio_google_mantem_mensagem_util_ao_usuario(): void
    {
        $message = 'Seu e-mail nao esta autorizado. Entre em contato com o administrador.';

        $this->mockGoogleProviderException(new DomainException($message));

        $this->get(route('google.callback'))
            ->assertRedirect(Filament::getPanel('admin')->getLoginUrl());

        $this->get(route('filament.admin.auth.login'))
            ->assertOk()
            ->assertSee('Falha ao autenticar com Google')
            ->assertSee($message);
    }

    public function test_erro_inesperado_google_nao_expoe_detalhes_tecnicos(): void
    {
        $technicalMessage = 'SQLSTATE[HY000] connection secret at 10.0.0.8';

        $this->mockGoogleProviderException(new RuntimeException($technicalMessage));

        $this->get(route('google.callback'))
            ->assertRedirect(Filament::getPanel('admin')->getLoginUrl());

        $this->get(route('filament.admin.auth.login'))
            ->assertOk()
            ->assertSee('Falha ao autenticar com Google')
            ->assertSee('Não foi possível concluir o login com Google. Tente novamente em instantes.')
            ->assertDontSee($technicalMessage);
    }

    private function mockGoogleCallback(User $user): void
    {
        $provider = Mockery::mock(AbstractProvider::class);
        $oauthUser = Mockery::mock(SocialiteUserContract::class);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $provider->shouldReceive('user')
            ->once()
            ->andReturn($oauthUser);

        $service = Mockery::mock(GoogleService::class);
        $service->shouldReceive('registrarOuLogar')
            ->once()
            ->with($oauthUser)
            ->andReturn($user);

        $this->app->instance(GoogleService::class, $service);
    }

    private function mockGoogleProviderException(DomainException|InvalidStateException|RuntimeException $exception): void
    {
        $provider = Mockery::mock(AbstractProvider::class);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $provider->shouldReceive('user')
            ->once()
            ->andThrow($exception);
    }
}
