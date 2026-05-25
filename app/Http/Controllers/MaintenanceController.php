<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MaintenanceController extends Controller
{
    private const TOKEN_PREFIX = 'maintenance_access_token:';

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if (
            ! $user
            || ! Hash::check($credentials['password'], (string) $user->password)
            || ! $user->hasRole('Admin')
        ) {
            return response()->json([
                'message' => 'Credenciais invalidas para manutencao.',
            ], 403);
        }

        $token = Str::random(80);
        $ttlMinutes = max(1, (int) config('maintenance.token_ttl_minutes', 15));
        $expiresAt = now()->addMinutes($ttlMinutes);

        Cache::put($this->tokenCacheKey($token), [
            'user_id' => $user->id,
            'email' => $user->email,
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function rebuildCache(Request $request): JsonResponse
    {
        if ($response = $this->authorizeToken($request)) {
            return $response;
        }

        return $this->runCommands([
            ['optimize:clear'],
            ['permission:cache-reset'],
            ['config:cache'],
            ['view:cache'],
            ['event:cache'],
        ]);
    }

    public function syncPermissions(Request $request): JsonResponse
    {
        if ($response = $this->authorizeToken($request)) {
            return $response;
        }

        return $this->runCommands([
            ['permission:cache-reset'],
            ['permissoes:criar'],
        ]);
    }

    public function migrate(Request $request): JsonResponse
    {
        if ($response = $this->authorizeToken($request)) {
            return $response;
        }

        return $this->runCommands([
            ['migrate', ['--force' => true]],
            ['permission:cache-reset'],
        ]);
    }

    private function authorizeToken(Request $request): ?JsonResponse
    {
        $token = $request->bearerToken() ?: $request->input('access_token');

        if (! is_string($token) || $token === '') {
            return response()->json([
                'message' => 'Token de manutencao ausente.',
            ], 401);
        }

        $payload = Cache::get($this->tokenCacheKey($token));

        if (! is_array($payload) || empty($payload['user_id'])) {
            return response()->json([
                'message' => 'Token de manutencao invalido ou expirado.',
            ], 401);
        }

        $user = User::query()->find($payload['user_id']);

        if (! $user || ! $user->hasRole('Admin')) {
            return response()->json([
                'message' => 'Token sem permissao de manutencao.',
            ], 403);
        }

        return null;
    }

    /**
     * @param  array<int, array{0: string, 1?: array<string, mixed>}>  $commands
     */
    private function runCommands(array $commands): JsonResponse
    {
        $results = [];
        $failed = false;

        foreach ($commands as $definition) {
            $command = $definition[0];
            $parameters = $definition[1] ?? [];
            $exitCode = Artisan::call($command, $parameters);
            $output = trim(Artisan::output());

            $results[] = [
                'command' => $command,
                'parameters' => $parameters ?? [],
                'exit_code' => $exitCode,
                'output' => $output,
            ];

            if ($exitCode !== 0) {
                $failed = true;
                break;
            }
        }

        return response()->json([
            'ok' => ! $failed,
            'results' => $results,
        ], $failed ? 500 : 200);
    }

    private function tokenCacheKey(string $token): string
    {
        return self::TOKEN_PREFIX.hash('sha256', $token);
    }
}
