<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ProfilePreviewService
{
    public const PERMISSION = 'Visualizar Select de Perfis';

    private const SESSION_KEY = 'profile_preview';

    public function start(int $targetUserId, User $realUser): void
    {
        if (! $realUser->hasPermissionTo(self::PERMISSION)) {
            throw ValidationException::withMessages([
                'target_user_id' => 'Você não tem permissão para visualizar perfis.',
            ]);
        }

        $targetUser = User::query()
            ->whereKey($targetUserId)
            ->where('email_approved', true)
            ->first();

        if (! $targetUser) {
            throw ValidationException::withMessages([
                'target_user_id' => 'Selecione um usuário aprovado para visualizar.',
            ]);
        }

        session()->put(self::SESSION_KEY, [
            'real_user_id' => (int) $realUser->getKey(),
            'target_user_id' => (int) $targetUser->getKey(),
            'started_at' => now()->toISOString(),
        ]);
    }

    public function stop(): void
    {
        $realUserId = $this->realUserId();

        session()->forget(self::SESSION_KEY);

        if ($realUserId) {
            $realUser = User::query()->find($realUserId);

            if ($realUser) {
                Auth::setUser($realUser);
            }
        }
    }

    public function isActive(): bool
    {
        return $this->targetUser() instanceof User
            && $this->realUser() instanceof User;
    }

    public function realUserId(): ?int
    {
        $id = session(self::SESSION_KEY.'.real_user_id');

        return filled($id) ? (int) $id : null;
    }

    public function targetUserId(): ?int
    {
        $id = session(self::SESSION_KEY.'.target_user_id');

        return filled($id) ? (int) $id : null;
    }

    public function startedAt(): ?Carbon
    {
        $startedAt = session(self::SESSION_KEY.'.started_at');

        return filled($startedAt) ? Carbon::parse($startedAt) : null;
    }

    public function realUser(): ?User
    {
        $id = $this->realUserId();

        return $id ? User::query()->find($id) : null;
    }

    public function targetUser(): ?User
    {
        $id = $this->targetUserId();

        if (! $id) {
            return null;
        }

        $user = User::query()
            ->whereKey($id)
            ->where('email_approved', true)
            ->first();

        if (! $user) {
            $this->stop();

            return null;
        }

        return $user;
    }

    public function controlUser(): ?User
    {
        return $this->realUser() ?: Auth::user();
    }

    public function effectiveUser(): ?User
    {
        $realUser = $this->realUser();
        $targetUser = $this->targetUser();

        return $realUser && $targetUser ? $targetUser : Auth::user();
    }

    public function canControl(?User $user = null): bool
    {
        $user ??= $this->controlUser();

        return $user?->hasPermissionTo(self::PERMISSION) ?? false;
    }
}
