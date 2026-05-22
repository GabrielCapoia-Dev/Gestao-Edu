<?php

namespace Tests\Feature\Users;

use App\Models\User;
use App\Services\UserPresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserPresenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_touch_updates_last_seen_and_login_timestamp(): void
    {
        Carbon::setTestNow('2026-05-11 09:00:00');

        $user = User::factory()->create();

        app(UserPresenceService::class)->touch($user, markLogin: true);

        $user->refresh();

        $this->assertSame('2026-05-11 09:00:00', $user->last_seen_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-11 09:00:00', $user->last_login_at?->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_online_and_offline_lists_follow_presence_window_and_last_login_order(): void
    {
        Carbon::setTestNow('2026-05-11 10:00:00');

        User::factory()->create([
            'name' => 'Online B',
            'last_seen_at' => now()->subSeconds(10),
            'last_login_at' => now()->subHour(),
        ]);

        User::factory()->create([
            'name' => 'Online A',
            'last_seen_at' => now()->subSeconds(20),
            'last_login_at' => now()->subHour(),
        ]);

        User::factory()->create([
            'name' => 'Offline Recente',
            'last_seen_at' => now()->subMinutes(5),
            'last_login_at' => now()->subDay(),
        ]);

        User::factory()->create([
            'name' => 'Offline Antigo',
            'last_seen_at' => null,
            'last_login_at' => now()->subDays(3),
        ]);

        User::factory()->create([
            'name' => 'Offline Nunca',
            'last_seen_at' => null,
            'last_login_at' => null,
        ]);

        $presence = app(UserPresenceService::class);

        $this->assertSame(['Online A', 'Online B'], $presence->onlineUsers()->pluck('name')->all());
        $this->assertSame(
            ['Offline Recente', 'Offline Antigo', 'Offline Nunca'],
            $presence->offlineUsers()->pluck('name')->all()
        );

        Carbon::setTestNow();
    }

    public function test_touch_throttles_repeated_heartbeats_for_same_user(): void
    {
        config()->set('performance.presence_touch_min_interval_seconds', 20);

        Carbon::setTestNow('2026-05-11 11:00:00');

        $user = User::factory()->create();
        $presence = app(UserPresenceService::class);

        $presence->touch($user);

        $this->assertSame('2026-05-11 11:00:00', $user->refresh()->last_seen_at?->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-05-11 11:00:05');

        $presence->touch($user);

        $this->assertSame('2026-05-11 11:00:00', $user->refresh()->last_seen_at?->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-05-11 11:00:21');

        $presence->touch($user);

        $this->assertSame('2026-05-11 11:00:21', $user->refresh()->last_seen_at?->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }
}
