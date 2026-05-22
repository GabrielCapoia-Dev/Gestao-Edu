<?php

namespace App\Http\Controllers;

use App\Services\NotificationCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NotificationCenterController extends Controller
{
    public function __construct(
        private readonly NotificationCenterService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $start = microtime(true);
        $user = $request->user();

        abort_unless($this->service->canView($user), 403);

        $result = $this->service->payload($user, $request->only([
            'modo',
            'busca',
            'periodo',
            'prioridade',
            'page',
            'per_page',
        ]));

        $this->logSlow('notifications.center', $start, ['user_id' => $user?->id]);

        return response()->json($result);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $start = microtime(true);
        $user = $request->user();

        abort_unless($this->service->canView($user), 403);

        $result = $this->service->unreadCountPayload($user);

        $this->logSlow('notifications.unreadCount', $start, ['user_id' => $user?->id]);

        return response()->json($result);
    }

    private function logSlow(string $route, float $start, array $context): void
    {
        if (! (bool) config('performance.instrumentation.enabled', false)) {
            return;
        }

        $elapsed = (microtime(true) - $start) * 1000;
        $thresholds = config('performance.slow_threshold_ms', []);
        $threshold = (int) ($thresholds[$route] ?? data_get($thresholds, $route, 0));

        if ($threshold > 0 && $elapsed > $threshold) {
            Log::warning("Rota lenta: {$route}", [
                'elapsed_ms' => round($elapsed, 2),
                ...$context,
            ]);
        }
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->service->canView($user), 403);

        $this->service->markRead($user, $id);

        return response()->json([
            'ok' => true,
            'stats' => $this->service->stats($user),
        ]);
    }

    public function markUnread(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->service->canView($user), 403);

        $this->service->markUnread($user, $id);

        return response()->json([
            'ok' => true,
            'stats' => $this->service->stats($user),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->service->canView($user), 403);

        $updated = $this->service->markAllRead($user);

        return response()->json([
            'ok' => true,
            'updated' => $updated,
            'stats' => $this->service->stats($user),
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->service->canCreate($user), 403);

        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:120'],
            'mensagem' => ['required', 'string', 'max:1500'],
            'url' => ['nullable', 'string', 'max:2048'],
            'label' => ['nullable', 'string', 'max:80'],
            'prioridade' => ['required', Rule::in(array_keys($this->service->prioridadeOptions()))],
            'destino_tipo' => ['required', Rule::in(array_keys($this->service->destinoTipoOptions($user)))],
            'usuarios_ids' => ['array'],
            'usuarios_ids.*' => ['integer'],
            'roles_ids' => ['array'],
            'roles_ids.*' => ['integer'],
            'escolas_ids' => ['array'],
            'escolas_ids.*' => ['integer'],
            'turmas_ids' => ['array'],
            'turmas_ids.*' => ['integer'],
            'permissoes' => ['array'],
            'permissoes.*' => ['string'],
            'setores_ids' => ['array'],
            'setores_ids.*' => ['integer'],
        ]);

        $result = $this->service->send($user, $data);

        if (($result['count'] ?? 0) < 1) {
            throw ValidationException::withMessages([
                'destinatarios' => 'Nenhum destinatário encontrado para o público selecionado.',
            ]);
        }

        return response()->json([
            'ok' => true,
            ...$result,
            'stats' => $this->service->stats($user),
        ]);
    }
}
