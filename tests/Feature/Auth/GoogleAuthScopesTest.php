<?php

namespace Tests\Feature\Auth;

use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Mockery;
use Tests\TestCase;

class GoogleAuthScopesTest extends TestCase
{
    public function test_login_google_solicita_apenas_escopos_de_identidade(): void
    {
        $provider = Mockery::mock(AbstractProvider::class);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $provider->shouldReceive('scopes')
            ->once()
            ->with(['openid', 'email', 'profile'])
            ->andReturnSelf();

        $provider->shouldReceive('with')
            ->once()
            ->with([
                'prompt' => 'select_account',
            ])
            ->andReturnSelf();

        $provider->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://accounts.google.com'));

        $response = $this->get(route('google.redirect'));

        $response->assertRedirect('https://accounts.google.com');
    }
}
