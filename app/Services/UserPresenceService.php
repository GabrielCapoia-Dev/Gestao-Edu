<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class UserPresenceService
{
    public const PERMISSION = 'Visualizar Usuarios Online';
    public const ONLINE_WINDOW_SECONDS = 30;

    public function touch(User $user, bool $markLogin = false): void
    {
        $now = now();
        $cacheKey = 'presence:touch:user:'.$user->getKey();
        $minInterval = max(5, (int) config('performance.presence_touch_min_interval_seconds', 20));

        if (! $markLogin && Cache::has($cacheKey)) {
            return;
        }

        $data = [
            'last_seen_at' => $now,
        ];

        if ($markLogin) {
            $data['last_login_at'] = $now;
        }

        User::query()
            ->whereKey($user->getKey())
            ->update($data);

        Cache::put($cacheKey, true, now()->addSeconds($minInterval));

        if ($markLogin) {
            $this->forgetCache();
        }
    }

    public function onlineCount(): int
    {
        return (int) $this->remember('presence:online-count', fn (): int => $this->onlineQuery()->count(), 'online_count');
    }

    public function totalUsersCount(): int
    {
        return (int) $this->remember('presence:users-count', fn (): int => User::query()->count(), 'users_count');
    }

    public function onlineUsers(): Collection
    {
        return $this->remember('presence:online', fn (): Collection => $this->onlineQuery()
            ->orderBy('name')
            ->limit(max(5, (int) config('performance.online_users_limit', 25)))
            ->get(['id', 'name', 'email', 'last_login_at', 'last_seen_at']));
    }

    public function offlineUsers(): Collection
    {
        return $this->remember('presence:offline', fn (): Collection => User::query()
            ->where(function ($query): void {
                $query
                    ->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $this->onlineCutoff());
            })
            ->orderByRaw('last_login_at IS NULL')
            ->orderByDesc('last_login_at')
            ->orderBy('name')
            ->limit(max(5, (int) config('performance.offline_users_limit', 25)))
            ->get(['id', 'name', 'email', 'last_login_at', 'last_seen_at']));
    }

    public function onlineCutoff(): \Illuminate\Support\Carbon
    {
        return now()->subSeconds(self::ONLINE_WINDOW_SECONDS);
    }

    public function forgetCache(): void
    {
        Cache::forget('presence:online');
        Cache::forget('presence:offline');
        Cache::forget('presence:online-count');
        Cache::forget('presence:users-count');
    }

    protected function remember(string $key, \Closure $callback, string $ttlKey = 'online_users'): mixed
    {
        $ttl = (int) config("performance.cache_ttl.{$ttlKey}", 15);

        return Cache::remember($key, now()->addSeconds($ttl), $callback);
    }

    protected function onlineQuery(): Builder
    {
        return User::query()
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $this->onlineCutoff());
    }
}
