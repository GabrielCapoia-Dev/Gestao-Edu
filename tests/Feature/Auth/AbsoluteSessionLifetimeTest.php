<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsoluteSessionLifetimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_is_forced_to_login_after_one_hour(): void
    {
        $user = User::factory()->create([
            'email_approved' => true,
        ]);

        $this->actingAs($user)
            ->withSession([
                'auth.login_at' => now()->subMinutes(61)->timestamp,
            ])
            ->get('/test')
            ->assertRedirect(Filament::getLoginUrl());

        $this->assertGuest();
    }

    public function test_authenticated_user_remains_logged_before_absolute_lifetime(): void
    {
        $user = User::factory()->create([
            'email_approved' => true,
        ]);

        $this->actingAs($user)
            ->withSession([
                'auth.login_at' => now()->subMinutes(59)->timestamp,
            ])
            ->get('/test')
            ->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_user_without_session_marker_starts_lifetime_from_current_session(): void
    {
        $user = User::factory()->create([
            'email_approved' => true,
            'last_login_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->get('/test')
            ->assertOk()
            ->assertSessionHas('auth.login_at');

        $this->assertAuthenticatedAs($user);
    }
}
