<?php

namespace App\Services\Avaliacoes;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Cache;
use JsonException;

class AvaliacaoDashboardMetricsService
{
    private const VERSION_CACHE_DAYS = 30;

    public function remember(
        User $user,
        array $filtros,
        array $filtrosAcompanhamento,
        array $paginacao,
        Closure $resolver
    ): array {
        $avaliacaoId = (int) ($filtros['avaliacao_id'] ?? 0);

        if ($avaliacaoId <= 0) {
            return $resolver();
        }

        return Cache::remember(
            $this->cacheKey($avaliacaoId, $user, $filtros, $filtrosAcompanhamento, $paginacao),
            $this->ttlSeconds(),
            $resolver
        );
    }

    public function forgetForAvaliacao(?int $avaliacaoId): void
    {
        $avaliacaoId = (int) $avaliacaoId;

        if ($avaliacaoId <= 0) {
            return;
        }

        Cache::put(
            $this->versionKey($avaliacaoId),
            $this->versionFor($avaliacaoId) + 1,
            now()->addDays(self::VERSION_CACHE_DAYS)
        );
    }

    public function versionFor(int $avaliacaoId): int
    {
        return max(1, (int) Cache::get($this->versionKey($avaliacaoId), 1));
    }

    private function cacheKey(
        int $avaliacaoId,
        User $user,
        array $filtros,
        array $filtrosAcompanhamento,
        array $paginacao
    ): string {
        $payload = [
            'version' => $this->versionFor($avaliacaoId),
            'user_id' => (int) $user->getKey(),
            'filtros' => $this->normalizarParaChave($filtros),
            'filtros_acompanhamento' => $this->normalizarParaChave($filtrosAcompanhamento),
            'paginacao' => $this->normalizarParaChave($paginacao),
        ];

        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $encoded = serialize($payload);
        }

        return 'avaliacoes:dashboard:metrics:' . sha1($encoded);
    }

    private function versionKey(int $avaliacaoId): string
    {
        return 'avaliacoes:dashboard:version:' . $avaliacaoId;
    }

    private function ttlSeconds(): int
    {
        return max(5, (int) config('performance.cache_ttl.avaliacoes_dashboard', 45));
    }

    private function normalizarParaChave(mixed $valor): mixed
    {
        if (! is_array($valor)) {
            return $valor;
        }

        $normalizado = [];

        foreach ($valor as $chave => $item) {
            $normalizado[$chave] = $this->normalizarParaChave($item);
        }

        if (array_is_list($normalizado)) {
            sort($normalizado);

            return $normalizado;
        }

        ksort($normalizado);

        return $normalizado;
    }
}
