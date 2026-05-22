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
        $data = [
            'last_seen_at' => now(),
        ];

        if ($markLogin) {
            $data['last_login_at'] = now();
        }

        User::query()
            ->whereKey($user->getKey())
            ->update($data);

        $this->forgetCache();
    }

    public function onlineCount(): int
    {
        return $this->onlineUsers()->count();
    }

    public function onlineUsers(): Collection
    {
        return $this->remember('presence:online', fn (): Collection => $this->onlineQuery()
            ->orderBy('name')
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
            ->orderBy('last_login_at')
            ->orderBy('name')
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
    }

    protected function remember(string $key, \Closure $callback): mixed
    {
        $ttl = (int) config('performance.cache_ttl.online_users', 15);

        return Cache::remember($key, now()->addSeconds($ttl), $callback);
    }

    protected function onlineQuery(): Builder
    {
        return User::query()
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $this->onlineCutoff());
    }
}
