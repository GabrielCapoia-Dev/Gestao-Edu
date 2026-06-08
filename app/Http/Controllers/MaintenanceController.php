<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class MaintenanceController extends Controller
{
    private const TOKEN_PREFIX = 'maintenance_access_token:';
    private const DEFAULT_QUEUE = 'notifications,default,imports,exports';
    private const ALLOWED_QUEUES = [
        'default',
        'notifications',
        'imports',
        'exports',
    ];

    private const COMMAND_LABELS = [
        'cache.clear' => 'Limpar caches',
        'cache.warm' => 'Aquecer caches',
        'cache.rebuild' => 'Reconstruir caches',
        'permissions.sync' => 'Sincronizar permissoes',
        'migrate' => 'Executar migrations',
        'migrate.status' => 'Consultar migrations',
        'queue.retry_failed' => 'Reexecutar jobs com falha',
        'queue.prune_failed' => 'Limpar jobs falhos antigos',
        'schedule.run' => 'Executar scheduler',
        'storage.link' => 'Criar link do storage',
        'exports.prune' => 'Limpar exportacoes antigas',
        'notifications.overdue_orders' => 'Notificar pedidos atrasados',
        'notifications.due_orders' => 'Notificar pedidos a vencer',
        'notifications.stock_balances' => 'Notificar balancos de estoque vencidos',
        'notifications.pending_transfers' => 'Notificar alunos pendentes de transferencia',
    ];

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
        $authorization = $this->authorizeToken($request);

        if ($authorization instanceof JsonResponse) {
            return $authorization;
        }

        $response = $this->runCommandKey('cache.rebuild', $request);
        $this->refreshToken($request, $authorization);

        return $response;
    }

    public function clearCache(Request $request): JsonResponse
    {
        $authorization = $this->authorizeToken($request);

        if ($authorization instanceof JsonResponse) {
            return $authorization;
        }

        $response = $this->runCommandKey('cache.clear', $request);
        $this->refreshToken($request, $authorization);

        return $response;
    }

    public function warmCache(Request $request): JsonResponse
    {
        if ($response = $this->authorizeTokenResponse($request)) {
            return $response;
        }

        return $this->runCommandKey('cache.warm', $request);
    }

    public function syncPermissions(Request $request): JsonResponse
    {
        if ($response = $this->authorizeTokenResponse($request)) {
            return $response;
        }

        return $this->runCommandKey('permissions.sync', $request);
    }

    public function migrate(Request $request): JsonResponse
    {
        if ($response = $this->authorizeTokenResponse($request)) {
            return $response;
        }

        return $this->runCommandKey('migrate', $request);
    }

    public function info(Request $request): JsonResponse
    {
        if ($response = $this->authorizeTokenResponse($request)) {
            return $response;
        }

        return response()->json([
            'app' => [
                'name' => config('app.name'),
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'url' => config('app.url'),
                'timezone' => config('app.timezone'),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'server_time' => now()->toIso8601String(),
            ],
            'database' => [
                'default' => config('database.default'),
                'connection' => DB::connection()->getName(),
            ],
            'cache' => [
                'default' => config('cache.default'),
                'prefix' => config('cache.prefix'),
            ],
            'queue' => [
                'default' => config('queue.default'),
                'connection' => config('queue.connections.'.config('queue.default').'.driver'),
                'queues' => self::ALLOWED_QUEUES,
                'counts' => $this->queueCounts(),
            ],
            'maintenance' => [
                'token_ttl_minutes' => (int) config('maintenance.token_ttl_minutes', 15),
                'throttle' => '10 requisicoes por minuto',
            ],
        ]);
    }

    public function health(Request $request): JsonResponse
    {
        if ($response = $this->authorizeTokenResponse($request)) {
            return $response;
        }

        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
        ];

        $ok = collect($checks)->every(fn (array $check): bool => $check['ok']);

        return response()->json([
            'ok' => $ok,
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
        ], $ok ? 200 : 503);
    }

    public function commands(Request $request): JsonResponse
    {
        if ($response = $this->authorizeTokenResponse($request)) {
            return $response;
        }

        return response()->json([
            'commands' => collect(self::COMMAND_LABELS)
                ->map(fn (string $label, string $key): array => [
                    'key' => $key,
                    'label' => $label,
                    'endpoint' => route('maintenance.commands.run', ['key' => $key], false),
                ])
                ->values()
                ->all(),
            'queue_run_endpoint' => route('maintenance.queue.run', [], false),
        ]);
    }

    public function runCommand(Request $request, string $key): JsonResponse
    {
        if ($response = $this->authorizeTokenResponse($request)) {
            return $response;
        }

        return $this->runCommandKey($key, $request);
    }

    public function queueStatus(Request $request): JsonResponse
    {
        if ($response = $this->authorizeTokenResponse($request)) {
            return $response;
        }

        return response()->json([
            'ok' => true,
            'queue' => [
                'default' => config('queue.default'),
                'connection' => config('queue.connections.'.config('queue.default').'.driver'),
                'counts' => $this->queueCounts(),
            ],
        ]);
    }

    public function runQueue(Request $request): JsonResponse
    {
        if ($response = $this->authorizeTokenResponse($request)) {
            return $response;
        }

        $queue = $this->normalizeQueueList((string) $request->input('queue', self::DEFAULT_QUEUE));
        $tries = $this->boundedInteger($request->input('tries', 3), 1, 10);
        $timeout = $this->boundedInteger($request->input('timeout', 60), 5, 300);
        $maxJobs = $this->boundedInteger($request->input('max_jobs', 100), 1, 500);

        if ($queue === null) {
            return response()->json([
                'message' => 'Fila invalida. Use uma ou mais filas permitidas.',
                'allowed_queues' => self::ALLOWED_QUEUES,
            ], 422);
        }

        return $this->runCommands([
            [
                'queue:work',
                [
                    '--stop-when-empty' => true,
                    '--queue' => $queue,
                    '--tries' => $tries,
                    '--timeout' => $timeout,
                    '--max-jobs' => $maxJobs,
                ],
            ],
        ]);
    }

    public function retryFailedQueueJobs(Request $request): JsonResponse
    {
        if ($response = $this->authorizeTokenResponse($request)) {
            return $response;
        }

        return $this->runCommandKey('queue.retry_failed', $request);
    }

    public function runSchedule(Request $request): JsonResponse
    {
        if ($response = $this->authorizeTokenResponse($request)) {
            return $response;
        }

        return $this->runCommandKey('schedule.run', $request);
    }

    public function linkStorage(Request $request): JsonResponse
    {
        if ($response = $this->authorizeTokenResponse($request)) {
            return $response;
        }

        return $this->runCommandKey('storage.link', $request);
    }

    public function pruneExports(Request $request): JsonResponse
    {
        if ($response = $this->authorizeTokenResponse($request)) {
            return $response;
        }

        return $this->runCommandKey('exports.prune', $request);
    }

    private function authorizeTokenResponse(Request $request): ?JsonResponse
    {
        $authorization = $this->authorizeToken($request);

        return $authorization instanceof JsonResponse ? $authorization : null;
    }

    private function authorizeToken(Request $request): User|JsonResponse
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

        return $user;
    }

    private function runCommandKey(string $key, Request $request): JsonResponse
    {
        $commands = $this->commandsForKey($key, $request);

        if ($commands === null) {
            return response()->json([
                'message' => 'Comando de manutencao desconhecido.',
                'available_commands' => array_keys(self::COMMAND_LABELS),
            ], 404);
        }

        return $this->runCommands($commands, $key);
    }

    /**
     * @return array<int, array{0: string, 1?: array<string, mixed>}>|null
     */
    private function commandsForKey(string $key, Request $request): ?array
    {
        return match ($key) {
            'cache.clear' => [
                ['optimize:clear'],
                ['permission:cache-reset'],
            ],
            'cache.warm' => [
                ['config:cache'],
                ['view:cache'],
                ['event:cache'],
            ],
            'cache.rebuild' => [
                ['optimize:clear'],
                ['permission:cache-reset'],
                ['config:cache'],
                ['view:cache'],
                ['event:cache'],
            ],
            'permissions.sync' => [
                ['permission:cache-reset'],
                ['permissoes:criar'],
            ],
            'migrate' => [
                ['migrate', ['--force' => true]],
                ['permission:cache-reset'],
            ],
            'migrate.status' => [
                ['migrate:status'],
            ],
            'queue.retry_failed' => [
                ['queue:retry', ['id' => ['all']]],
            ],
            'queue.prune_failed' => [
                ['queue:prune-failed', ['--hours' => $this->boundedInteger($request->input('hours', 168), 1, 2160)]],
            ],
            'schedule.run' => [
                ['schedule:run'],
            ],
            'storage.link' => [
                ['storage:link'],
            ],
            'exports.prune' => [
                ['exports:prune', ['--days' => $this->boundedInteger($request->input('days', 7), 1, 365)]],
            ],
            'notifications.overdue_orders' => [
                ['app:notificar-pedidos-atrasados'],
            ],
            'notifications.due_orders' => [
                ['app:notificar-pedidos-a-vencer'],
            ],
            'notifications.stock_balances' => [
                ['app:notificar-balancos-estoque-vencidos'],
            ],
            'notifications.pending_transfers' => [
                ['app:notificar-alunos-pendentes-transferencia'],
            ],
            default => null,
        };
    }

    /**
     * @param  array<int, array{0: string, 1?: array<string, mixed>}>  $commands
     */
    private function runCommands(array $commands, ?string $key = null): JsonResponse
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
            'key' => $key,
            'results' => $results,
        ], $failed ? 500 : 200);
    }

    private function refreshToken(Request $request, User $user): void
    {
        $token = $request->bearerToken() ?: $request->input('access_token');

        if (! is_string($token) || $token === '') {
            return;
        }

        $ttlMinutes = max(1, (int) config('maintenance.token_ttl_minutes', 15));
        $expiresAt = now()->addMinutes($ttlMinutes);

        Cache::put($this->tokenCacheKey($token), [
            'user_id' => $user->id,
            'email' => $user->email,
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);
    }

    private function normalizeQueueList(string $queue): ?string
    {
        $queues = collect(explode(',', $queue))
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique()
            ->values();

        if ($queues->isEmpty()) {
            return null;
        }

        if ($queues->contains(fn (string $name): bool => ! in_array($name, self::ALLOWED_QUEUES, true))) {
            return null;
        }

        return $queues->implode(',');
    }

    private function boundedInteger(mixed $value, int $min, int $max): int
    {
        $integer = is_numeric($value) ? (int) $value : $min;

        return max($min, min($max, $integer));
    }

    private function queueCounts(): array
    {
        return [
            'jobs_table_exists' => $this->tableExists('jobs'),
            'failed_jobs_table_exists' => $this->tableExists('failed_jobs'),
            'pending_jobs' => $this->tableExists('jobs') ? DB::table('jobs')->count() : null,
            'failed_jobs' => $this->tableExists('failed_jobs') ? DB::table('failed_jobs')->count() : null,
            'pending_by_queue' => $this->pendingJobsByQueue(),
        ];
    }

    private function pendingJobsByQueue(): array
    {
        if (! $this->tableExists('jobs')) {
            return [];
        }

        return DB::table('jobs')
            ->select('queue', DB::raw('COUNT(*) as total'))
            ->groupBy('queue')
            ->pluck('total', 'queue')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('select 1');

            return [
                'ok' => true,
                'connection' => DB::connection()->getName(),
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        $key = 'maintenance_health:'.Str::random(12);

        try {
            Cache::put($key, 'ok', now()->addMinute());
            $ok = Cache::get($key) === 'ok';
            Cache::forget($key);

            return [
                'ok' => $ok,
                'store' => config('cache.default'),
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function checkStorage(): array
    {
        $path = storage_path();

        return [
            'ok' => is_dir($path) && is_writable($path),
            'path' => $path,
        ];
    }

    private function tokenCacheKey(string $token): string
    {
        return self::TOKEN_PREFIX.hash('sha256', $token);
    }
}
