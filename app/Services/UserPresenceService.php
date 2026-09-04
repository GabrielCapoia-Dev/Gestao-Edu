<?php

namespace App\Services;

use App\Jobs\SyncUserLoginPresenceJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Throwable;

class UserPresenceService
{
    public const PERMISSION = 'Visualizar Usuarios Online';
    public const DEFAULT_ONLINE_WINDOW_SECONDS = 180;

    private const ACTIVE_USERS_CACHE_KEY = 'presence:active-users';

    private const ACTIVE_USERS_LOCK_KEY = 'presence:active-users:lock';

    private const ACTIVE_USERS_REDIS_KEY = 'presence:active-users:zset:v2';

    public function touch(User $user, bool $markLogin = false): void
    {
        $now = now();
        $cacheKey = 'presence:touch:user:'.$user->getKey();
        $minInterval = max(5, (int) config('performance.presence_touch_min_interval_seconds', 20));

        if (! $markLogin && $this->cacheHas($cacheKey)) {
            return;
        }

        $this->markOnline((int) $user->getKey(), $now->getTimestamp());
        $this->cachePut($cacheKey, true, now()->addSeconds($minInterval));

        $historySyncInterval = max(
            $minInterval,
            (int) config('performance.presence_history_sync_interval_seconds', 900),
        );
        $historyCacheKey = 'presence:last-seen-sync:user:'.$user->getKey();

        if (! $markLogin && ! $this->cacheAdd($historyCacheKey, true, now()->addSeconds($historySyncInterval))) {
            return;
        }

        $this->syncHistory($user, $now, $markLogin);
    }

    public function touchLoginDeferred(User $user): void
    {
        $now = now();
        $minInterval = max(5, (int) config('performance.presence_touch_min_interval_seconds', 20));

        $this->markOnline((int) $user->getKey(), $now->getTimestamp());
        $this->cachePut(
            'presence:touch:user:'.$user->getKey(),
            true,
            now()->addSeconds($minInterval),
        );

        SyncUserLoginPresenceJob::dispatch(
            (int) $user->getKey(),
            $now->getTimestamp(),
        )->onQueue('default');
    }

    public function syncLoginHistory(User $user, \Illuminate\Support\Carbon $seenAt): void
    {
        $this->syncHistory($user, $seenAt, true);
    }

    public function onlineCount(): int
    {
        $activeUserIds = $this->activeUserIds();

        return $activeUserIds === null
            ? $this->onlineQuery()->count()
            : count($activeUserIds);
    }

    public function totalUsersCount(): int
    {
        return (int) $this->remember('presence:users-count', fn (): int => User::query()->count(), 'users_count');
    }

    public function onlineUsers(): Collection
    {
        $activeUserIds = $this->activeUserIds();

        if ($activeUserIds === []) {
            return collect();
        }

        $query = $activeUserIds === null
            ? $this->onlineQuery()
            : User::query()->whereKey($activeUserIds);

        return $query
            ->orderBy('name')
            ->limit(max(5, (int) config('performance.online_users_limit', 25)))
            ->get(['id', 'name', 'email', 'last_login_at', 'last_seen_at']);
    }

    public function offlineUsers(): Collection
    {
        $activeUserIds = $this->activeUserIds();
        $query = User::query();

        if ($activeUserIds === null) {
            $query->where(function ($query): void {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $this->onlineCutoff());
            });
        } elseif ($activeUserIds !== []) {
            $query->whereNotIn($query->getModel()->getQualifiedKeyName(), $activeUserIds);
        }

        return $query
            ->orderByRaw('last_login_at IS NULL')
            ->orderByDesc('last_login_at')
            ->orderBy('name')
            ->limit(max(5, (int) config('performance.offline_users_limit', 25)))
            ->get(['id', 'name', 'email', 'last_login_at', 'last_seen_at']);
    }

    public function onlineCutoff(): \Illuminate\Support\Carbon
    {
        $window = max(
            30,
            (int) config('performance.presence_online_window_seconds', self::DEFAULT_ONLINE_WINDOW_SECONDS),
        );

        return now()->subSeconds($window);
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

    protected function cacheAdd(string $key, mixed $value, mixed $ttl): bool
    {
        try {
            return Cache::add($key, $value, $ttl);
        } catch (Throwable) {
            return true;
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

    private function markOnline(int $userId, int $seenAt): void
    {
        if ($this->usesRedisPresence()) {
            try {
                $redis = Redis::connection((string) config('cache.stores.redis.connection', 'cache'));
                $cutoff = $seenAt - $this->onlineWindowSeconds();

                $redis->zadd(self::ACTIVE_USERS_REDIS_KEY, $seenAt, (string) $userId);
                $redis->zremrangebyscore(self::ACTIVE_USERS_REDIS_KEY, '-inf', (string) ($cutoff - 1));
                $redis->expire(self::ACTIVE_USERS_REDIS_KEY, $this->onlineWindowSeconds() * 2);

                return;
            } catch (Throwable) {
                // Continua no armazenamento compativel quando o Redis falhar.
            }
        }

        try {
            Cache::lock(self::ACTIVE_USERS_LOCK_KEY, 5)->block(2, function () use ($userId, $seenAt): void {
                $activeUsers = $this->normalizedActiveUsers(Cache::get(self::ACTIVE_USERS_CACHE_KEY, []));
                $cutoff = $seenAt - $this->onlineWindowSeconds();
                $activeUsers = array_filter(
                    $activeUsers,
                    static fn (int $timestamp): bool => $timestamp >= $cutoff,
                );
                $activeUsers[$userId] = $seenAt;

                Cache::put(
                    self::ACTIVE_USERS_CACHE_KEY,
                    $activeUsers,
                    now()->addSeconds($this->onlineWindowSeconds() * 2),
                );
            });
        } catch (Throwable) {
            // Mantem o fallback por last_seen_at quando o Redis estiver indisponivel.
        }
    }

    /** @return list<int>|null */
    private function activeUserIds(): ?array
    {
        if ($this->usesRedisPresence()) {
            try {
                $redis = Redis::connection((string) config('cache.stores.redis.connection', 'cache'));
                $cutoff = now()->getTimestamp() - $this->onlineWindowSeconds();

                return collect($redis->zrangebyscore(
                    self::ACTIVE_USERS_REDIS_KEY,
                    (string) $cutoff,
                    '+inf',
                ))
                    ->map(static fn (int|string $id): int => (int) $id)
                    ->filter(static fn (int $id): bool => $id > 0)
                    ->values()
                    ->all();
            } catch (Throwable) {
                // Continua no armazenamento compativel quando o Redis falhar.
            }
        }

        try {
            $activeUsers = $this->normalizedActiveUsers(Cache::get(self::ACTIVE_USERS_CACHE_KEY, []));
            $cutoff = now()->getTimestamp() - $this->onlineWindowSeconds();

            return collect($activeUsers)
                ->filter(static fn (int $timestamp): bool => $timestamp >= $cutoff)
                ->keys()
                ->map(static fn (int|string $id): int => (int) $id)
                ->values()
                ->all();
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<int, int> */
    private function normalizedActiveUsers(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $activeUsers = [];

        foreach ($value as $userId => $timestamp) {
            if ((int) $userId > 0 && (int) $timestamp > 0) {
                $activeUsers[(int) $userId] = (int) $timestamp;
            }
        }

        return $activeUsers;
    }

    private function onlineWindowSeconds(): int
    {
        return max(
            30,
            (int) config('performance.presence_online_window_seconds', self::DEFAULT_ONLINE_WINDOW_SECONDS),
        );
    }

    private function usesRedisPresence(): bool
    {
        return config('cache.default') === 'redis';
    }

    private function syncHistory(User $user, \Illuminate\Support\Carbon $seenAt, bool $markLogin): void
    {
        $data = ['last_seen_at' => $seenAt];

        if ($markLogin) {
            $data['last_login_at'] = $seenAt;
        }

        User::query()
            ->whereKey($user->getKey())
            ->update($data);

        $historySyncInterval = max(
            (int) config('performance.presence_touch_min_interval_seconds', 20),
            (int) config('performance.presence_history_sync_interval_seconds', 900),
        );

        $this->cachePut(
            'presence:last-seen-sync:user:'.$user->getKey(),
            true,
            $seenAt->copy()->addSeconds($historySyncInterval),
        );

        if ($markLogin) {
            $this->forgetCache();
        }
    }
}
