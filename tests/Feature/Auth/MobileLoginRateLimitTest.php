<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileLoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_login_is_blocked_after_repeated_invalid_attempts(): void
    {
        $credentials = [
            'email' => 'unknown@example.com',
            'password' => 'invalid-password',
        ];

        for ($attempt = 1; $attempt <= 8; $attempt++) {
            $this->post('/app/login', $credentials)
                ->assertSessionHasErrors('email');
        }

        $response = $this->post('/app/login', $credentials);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Muitas tentativas',
            (string) session('errors')->first('email'),
        );

        $adminResponse = $this->post('/admin/login', $credentials);

        $adminResponse->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Muitas tentativas',
            (string) session('errors')->first('email'),
        );
    }
}
