<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

class UserPresenceService
{
    public const PERMISSION = 'Visualizar Usuarios Online';
    public const ONLINE_WINDOW_SECONDS = 30;

    public function touch(User $user, bool $markLogin = false): void
    {
        $now = now();
        $cacheKey = 'presence:touch:user:'.$user->getKey();
        $minInterval = max(5, (int) config('performance.presence_touch_min_interval_seconds', 20));

        if (! $markLogin && $this->cacheHas($cacheKey)) {
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

        $this->cachePut($cacheKey, true, now()->addSeconds($minInterval));

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
        $this->cacheForget('presence:online');
        $this->cacheForget('presence:offline');
        $this->cacheForget('presence:online-count');
        $this->cacheForget('presence:users-count');
    }

    protected function remember(string $key, \Closure $callback, string $ttlKey = 'online_users'): mixed
    {
        $ttl = (int) config("performance.cache_ttl.{$ttlKey}", 15);

        try {
            return Cache::remember($key, now()->addSeconds($ttl), $callback);
        } catch (Throwable) {
            return $callback();
        }
    }

    protected function cacheHas(string $key): bool
    {
        try {
            return Cache::has($key);
        } catch (Throwable) {
            return false;
        }
    }

    protected function cachePut(string $key, mixed $value, mixed $ttl): void
    {
        try {
            Cache::put($key, $value, $ttl);
        } catch (Throwable) {
            //
        }
    }

    protected function cacheForget(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (Throwable) {
            //
        }
    }

    protected function onlineQuery(): Builder
    {
        return User::query()
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $this->onlineCutoff());
    }
}
