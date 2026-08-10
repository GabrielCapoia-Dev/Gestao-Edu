<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserActorSnapshot
{
    public static function find(?int $userId): ?User
    {
        if (! $userId) {
            return null;
        }

        $query = User::query();

        if (self::userUsesSoftDeletes()) {
            $query->withTrashed();
        }

        return $query->find($userId);
    }

    public static function relation(BelongsTo $relation): BelongsTo
    {
        if (self::userUsesSoftDeletes()) {
            $relation->withTrashed();
        }

        return $relation;
    }

    /** @return array{nome: ?string, email: ?string} */
    public static function values(?User $user): array
    {
        return [
            'nome' => filled($user?->name) ? (string) $user->name : null,
            'email' => filled($user?->email) ? (string) $user->email : null,
        ];
    }

    public static function displayName(
        ?User $user,
        ?string $snapshot,
        ?int $legacyId,
        ?int $currentId = null,
    ): string {
        if (filled($user?->name)) {
            return (string) $user->name;
        }

        if (filled($snapshot)) {
            return (string) $snapshot;
        }

        $referenceId = $legacyId ?: $currentId;

        return $referenceId
            ? "Usuário legado #{$referenceId}"
            : 'Sistema';
    }

    public static function displayEmail(?User $user, ?string $snapshot): ?string
    {
        return filled($user?->email)
            ? (string) $user->email
            : (filled($snapshot) ? (string) $snapshot : null);
    }

    public static function canReceiveNotification(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->canAuthenticate();
    }

    private static function userUsesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive(User::class), true);
    }
}
