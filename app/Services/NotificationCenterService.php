<?php

namespace App\Services;

use App\Jobs\SendManualNotificationBatchJob;
use App\Models\Escola;
use App\Models\NotificacaoEnvio;
use App\Models\Professor;
use App\Models\Role;
use App\Models\Turma;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Throwable;

class NotificationCenterService
{
    public function canView(?User $user): bool
    {
        return $user?->hasPermissionTo('Visualizar Notificações') ?? false;
    }

    public function canCreate(?User $user): bool
    {
        return $user?->hasPermissionTo('Criar Notificações') ?? false;
    }

    public function prioridadeOptions(): array
    {
        return [
            'normal' => 'Normal',
            'informativa' => 'Informativa',
            'alta' => 'Alta',
            'urgente' => 'Urgente',
        ];
    }

    public function destinoTipoOptions(): array
    {
        return [
            'todos' => 'Todos os usuários',
            'usuarios' => 'Usuários específicos',
            'roles' => 'Níveis de acesso',
            'escolas' => 'Usuários por escola',
            'professores_turmas' => 'Professores por turma',
            'permissoes' => 'Usuários por permissão',
        ];
    }

    public function formOptions(): array
    {
        $ttl = now()->addMinutes(5);

        return Cache::remember('notifications:form-options', $ttl, function () use ($ttl): array {
            return [
                'usuarios' => $this->cacheRemember('notifications:form-options:usuarios', $ttl, fn (): array => User::query()
                    ->orderBy('name')
                    ->limit(1000)
                    ->get(['id', 'name', 'email'])
                    ->map(fn (User $user): array => [
                        'id' => (string) $user->id,
                        'label' => trim("{$user->name} · {$user->email}"),
                    ])
                    ->values()
                    ->all()),

                'roles' => $this->cacheRemember('notifications:form-options:roles', $ttl, fn (): array => Role::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Role $role): array => [
                        'id' => (string) $role->id,
                        'label' => $role->name,
                    ])
                    ->values()
                    ->all()),

                'escolas' => $this->cacheRemember('notifications:form-options:escolas', $ttl, fn (): array => Escola::query()
                    ->orderBy('nome')
                    ->get(['id', 'nome'])
                    ->map(fn (Escola $escola): array => [
                        'id' => (string) $escola->id,
                        'label' => $escola->nome,
                    ])
                    ->values()
                    ->all()),

                'turmas' => $this->cacheRemember('notifications:form-options:turmas', $ttl, fn (): array => Turma::query()
                    ->with(['escola:id,nome', 'serie:id,nome'])
                    ->orderBy('nome')
                    ->get()
                    ->map(function (Turma $turma): array {
                        $partes = array_filter([
                            $turma->escola?->nome,
                            $turma->serie?->nome,
                            $turma->nome,
                            $turma->turno,
                        ]);

                        return [
                            'id' => (string) $turma->id,
                            'label' => implode(' · ', $partes),
                        ];
                    })
                    ->values()
                    ->all()),

                'permissoes' => $this->cacheRemember('notifications:form-options:permissoes', $ttl, fn (): array => Permission::query()
                    ->orderBy('name')
                    ->pluck('name')
                    ->map(fn (string $permission): array => [
                        'id' => $permission,
                        'label' => $permission,
                    ])
                    ->values()
                    ->all()),
            ];
        });
    }

    private function cacheRemember(string $key, \DateTimeInterface|\DateInterval|int $ttl, \Closure $callback): mixed
    {
        return Cache::remember($key, $ttl, $callback);
    }

    public function payload(User $user, array $filters): array
    {
        $mode = $this->normalizeMode($filters['modo'] ?? 'todas', $user);
        $page = $this->normalizePage($filters['page'] ?? 1);
        $perPage = $this->normalizePerPage($filters['per_page'] ?? 18);
        $stats = $this->stats($user);

        if ($mode === 'enviadas') {
            $query = $this->enviosQuery($filters);
            $total = (clone $query)->count();
            $pagination = $this->pagination($page, $perPage, $total);
            $records = $query
                ->offset(($pagination['page'] - 1) * $perPage)
                ->limit($perPage)
                ->get();

            return [
                'mode' => $mode,
                'stats' => $stats,
                'unread_counter' => $this->unreadCountPayload($user),
                'items' => $records->map(fn (NotificacaoEnvio $envio): array => $this->formatarEnvio($envio))->values(),
                'pagination' => $pagination,
                'can_create' => $this->canCreate($user),
                'server_time' => now()->toIso8601String(),
            ];
        }

        $query = $this->notificationQuery($user, $filters, $mode);
        $total = (clone $query)->count();
        $pagination = $this->pagination($page, $perPage, $total);
        $records = $query
            ->offset(($pagination['page'] - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return [
            'mode' => $mode,
            'stats' => $stats,
            'unread_counter' => $this->unreadCountPayload($user),
            'items' => $records->map(fn (object $notification): array => $this->formatarNotificacao($notification))->values(),
            'pagination' => $pagination,
            'can_create' => $this->canCreate($user),
            'server_time' => now()->toIso8601String(),
        ];
    }

    public function stats(User $user): array
    {
        $row = $this->notificationBaseQuery($user)
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when read_at is null then 1 else 0 end) as ativas')
            ->selectRaw('sum(case when read_at is not null then 1 else 0 end) as historico')
            ->selectRaw('sum(case when created_at >= ? then 1 else 0 end) as hoje', [now()->startOfDay()])
            ->selectRaw('sum(case when read_at is null and data like ? then 1 else 0 end) as urgentes', ['%"prioridade":"urgente"%'])
            ->selectRaw('max(updated_at) as latest_updated_at')
            ->first();

        $total = (int) ($row->total ?? 0);
        $ativas = (int) ($row->ativas ?? 0);
        $historico = (int) ($row->historico ?? 0);
        $hoje = (int) ($row->hoje ?? 0);
        $urgentes = (int) ($row->urgentes ?? 0);
        $latestUpdatedAt = (string) ($row->latest_updated_at ?? '');
        $enviadas = $this->canCreate($user)
            ? NotificacaoEnvio::query()->count()
            : 0;

        return [
            'total' => $total,
            'ativas' => $ativas,
            'historico' => $historico,
            'hoje' => $hoje,
            'urgentes' => $urgentes,
            'enviadas' => $enviadas,
            'change_token' => sha1(implode('|', [
                $total,
                $ativas,
                $historico,
                $hoje,
                $urgentes,
                $enviadas,
                $latestUpdatedAt,
            ])),
        ];
    }

    public function unreadCountPayload(User $user): array
    {
        $ttl = max(1, (int) config('notifications.unread_count_cache_ttl', 30));
        $key = $this->unreadCountCacheKey($user);

        $cached = Cache::get($key);

        if (is_array($cached) && array_key_exists('unread', $cached)) {
            return $cached;
        }

        try {
            return Cache::lock($key.':lock', 5)->block(1, function () use ($key, $ttl, $user): array {
                return Cache::remember(
                    $key,
                    now()->addSeconds($ttl),
                    fn (): array => $this->freshUnreadCountPayload($user)
                );
            });
        } catch (Throwable) {
            return $this->freshUnreadCountPayload($user);
        }
    }

    public function unreadCount(User $user): int
    {
        return (int) $this->unreadCountPayload($user)['unread'];
    }

    public function markRead(User $user, string $id): int
    {
        $updated = $this->notificationBaseQuery($user)
            ->where('id', $id)
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            $this->forgetUnreadCountCache($user);
        }

        return $updated;
    }

    public function markUnread(User $user, string $id): int
    {
        $updated = $this->notificationBaseQuery($user)
            ->where('id', $id)
            ->update([
                'read_at' => null,
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            $this->forgetUnreadCountCache($user);
        }

        return $updated;
    }

    public function markAllRead(User $user): int
    {
        $updated = $this->notificationBaseQuery($user)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            $this->forgetUnreadCountCache($user);
        }

        return $updated;
    }

    public function forgetUnreadCountCache(User|int|string $user): void
    {
        Cache::forget($this->unreadCountCacheKey($user));
    }

    public function send(User $autor, array $data): array
    {
        [$destinatarios, $destinoLabel, $destinoIds] = $this->resolverDestinatarios($data);

        if ($destinatarios->isEmpty()) {
            return [
                'count' => 0,
                'destino_label' => $destinoLabel,
            ];
        }

        $envio = DB::transaction(function () use ($autor, $data, $destinatarios, $destinoLabel, $destinoIds): NotificacaoEnvio {
            return NotificacaoEnvio::query()->create([
                'user_id' => $autor->id,
                'titulo' => $data['titulo'],
                'mensagem' => $data['mensagem'],
                'url' => filled($data['url'] ?? null) ? $data['url'] : null,
                'label' => filled($data['label'] ?? null) ? $data['label'] : null,
                'prioridade' => $data['prioridade'] ?? 'normal',
                'destino_tipo' => $data['destino_tipo'] ?? 'todos',
                'destino_label' => $destinoLabel,
                'destinatarios_count' => $destinatarios->count(),
                'destinatarios_ids' => $destinatarios->pluck('id')->values()->all(),
                'filtros' => [
                    'destino_ids' => $destinoIds,
                    'destino_tipo' => $data['destino_tipo'] ?? 'todos',
                ],
                'status' => NotificacaoEnvio::STATUS_QUEUED,
                'queued_at' => now(),
            ]);
        });

        try {
            SendManualNotificationBatchJob::dispatch((string) $envio->id);
        } catch (Throwable $exception) {
            $envio->forceFill([
                'status' => NotificacaoEnvio::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();

            Log::error('Falha ao enfileirar lote manual de notificacoes.', [
                'notificacao_envio_id' => $envio->id,
                'exception' => $exception,
            ]);

            throw $exception;
        }

        return [
            'count' => $destinatarios->count(),
            'destino_label' => $destinoLabel,
            'queued' => true,
            'envio_id' => (string) $envio->id,
        ];
    }

    private function notificationBaseQuery(User $user): QueryBuilder
    {
        return DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', User::class);
    }

    private function freshUnreadCountPayload(User $user): array
    {
        $row = $this->notificationBaseQuery($user)
            ->whereNull('read_at')
            ->selectRaw('count(*) as unread')
            ->selectRaw('max(created_at) as latest_unread_created_at')
            ->first();

        $unread = (int) ($row->unread ?? 0);
        $latestUnreadCreatedAt = (string) ($row->latest_unread_created_at ?? '');

        return [
            'unread' => $unread,
            'change_token' => sha1($user->id.'|'.$unread.'|'.$latestUnreadCreatedAt),
        ];
    }

    private function unreadCountCacheKey(User|int|string $user): string
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        return 'notifications:unread-count:user:'.$userId;
    }

    private function notificationQuery(User $user, array $filters, string $mode): QueryBuilder
    {
        $query = $this->notificationBaseQuery($user);

        if ($mode === 'ativas') {
            $query->whereNull('read_at');
        } elseif ($mode === 'historico') {
            $query->whereNotNull('read_at');
        }

        $this->aplicarFiltrosNotificacao($query, $filters);

        return $query
            ->orderByRaw('read_at is null desc')
            ->orderByDesc('created_at');
    }

    private function enviosQuery(array $filters): EloquentBuilder
    {
        $query = NotificacaoEnvio::query()
            ->with('autor:id,name');

        $periodo = $filters['periodo'] ?? '30';

        if ($periodo !== 'todos') {
            $query->where('created_at', '>=', now()->subDays((int) $periodo));
        }

        if (($filters['prioridade'] ?? 'todas') !== 'todas') {
            $query->where('prioridade', $filters['prioridade']);
        }

        if (filled($filters['busca'] ?? null)) {
            $busca = $this->likeNeedle((string) $filters['busca']);

            $query->where(function (EloquentBuilder $envios) use ($busca): void {
                $envios
                    ->where('titulo', 'like', $busca)
                    ->orWhere('mensagem', 'like', $busca)
                    ->orWhere('destino_label', 'like', $busca);
            });
        }

        return $query->orderByDesc('created_at');
    }

    private function aplicarFiltrosNotificacao(QueryBuilder $query, array $filters): void
    {
        $periodo = $filters['periodo'] ?? '30';

        if ($periodo !== 'todos') {
            $query->where('created_at', '>=', now()->subDays((int) $periodo));
        }

        $prioridade = $filters['prioridade'] ?? 'todas';

        if ($prioridade !== 'todas') {
            $query->where(function (QueryBuilder $prioridades) use ($prioridade): void {
                $prioridades->where('data', 'like', '%"prioridade":"'.$prioridade.'"%');

                if ($prioridade === 'normal') {
                    $prioridades->orWhere('data', 'not like', '%"prioridade":%');
                }
            });
        }

        if (filled($filters['busca'] ?? null)) {
            $busca = $this->likeNeedle((string) $filters['busca']);

            $query->where(function (QueryBuilder $notificacoes) use ($busca): void {
                $notificacoes
                    ->where('data', 'like', $busca)
                    ->orWhere('type', 'like', $busca);
            });
        }
    }

    private function resolverDestinatarios(array $data): array
    {
        $tipo = $data['destino_tipo'] ?? 'todos';
        $ids = collect();
        $query = User::query();

        if ($tipo === 'usuarios') {
            $ids = $this->idsSelecionados($data['usuarios_ids'] ?? []);
            $query->whereIn('id', $ids);
        } elseif ($tipo === 'roles') {
            $ids = $this->idsSelecionados($data['roles_ids'] ?? []);
            $query->whereHas('roles', fn (EloquentBuilder $roles) => $roles->whereIn('roles.id', $ids));
        } elseif ($tipo === 'escolas') {
            $ids = $this->idsSelecionados($data['escolas_ids'] ?? []);
            $query->where(function (EloquentBuilder $usuarios) use ($ids): void {
                $usuarios
                    ->whereIn('id_escola', $ids)
                    ->orWhereHas('escolas', fn (EloquentBuilder $escolas) => $escolas->whereIn('escolas.id', $ids))
                    ->orWhereHas('professores', fn (EloquentBuilder $professores) => $professores->whereIn('id_escola', $ids));
            });
        } elseif ($tipo === 'professores_turmas') {
            $ids = $this->idsSelecionados($data['turmas_ids'] ?? []);
            $userIds = Professor::query()
                ->whereNotNull('user_id')
                ->where(function (EloquentBuilder $professores) use ($ids): void {
                    $professores
                        ->whereHas('turmas', fn (EloquentBuilder $turmas) => $turmas->whereIn('turmas.id', $ids))
                        ->orWhereHas('turmasFuncao', fn (EloquentBuilder $turmas) => $turmas->whereIn('turmas.id', $ids));
                })
                ->pluck('user_id')
                ->unique()
                ->values();

            $query->whereIn('id', $userIds);
        } elseif ($tipo === 'permissoes') {
            $ids = collect($data['permissoes'] ?? [])
                ->filter(fn ($permission): bool => filled($permission))
                ->values();

            if ($ids->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->permission($ids->all());
            }
        }

        $destinatarios = $query
            ->whereNotNull('email')
            ->orderBy('name')
            ->get()
            ->unique('id')
            ->values();

        return [
            $destinatarios,
            $this->destinoLabel($tipo, $ids, $destinatarios),
            $ids->values()->all(),
        ];
    }

    private function idsSelecionados(array $ids): Collection
    {
        return collect($ids)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    private function destinoLabel(string $tipo, Collection $ids, Collection $destinatarios): string
    {
        return match ($tipo) {
            'usuarios' => $this->resumirNomes($destinatarios->pluck('name'), 'Usuários específicos'),
            'roles' => $this->resumirNomes(Role::query()->whereIn('id', $ids)->orderBy('name')->pluck('name'), 'Níveis de acesso'),
            'escolas' => $this->resumirNomes(Escola::query()->whereIn('id', $ids)->orderBy('nome')->pluck('nome'), 'Escolas'),
            'professores_turmas' => $this->resumirNomes(Turma::query()->whereIn('id', $ids)->orderBy('nome')->pluck('nome'), 'Professores por turma'),
            'permissoes' => $this->resumirNomes($ids, 'Usuários por permissão'),
            default => 'Todos os usuários',
        };
    }

    private function resumirNomes(Collection $nomes, string $fallback): string
    {
        $nomes = $nomes
            ->filter(fn ($nome): bool => filled($nome))
            ->values();

        if ($nomes->isEmpty()) {
            return $fallback;
        }

        if ($nomes->count() <= 3) {
            return $nomes->join(', ');
        }

        return $nomes->take(3)->join(', ').' +'.($nomes->count() - 3);
    }

    private function formatarNotificacao(object $notification): array
    {
        $data = json_decode((string) $notification->data, true);

        if (! is_array($data)) {
            $data = [];
        }

        $prioridade = $data['prioridade'] ?? 'normal';
        $createdAt = Carbon::parse($notification->created_at);
        $readAt = filled($notification->read_at) ? Carbon::parse($notification->read_at) : null;

        return [
            'id' => $notification->id,
            'kind' => 'notification',
            'titulo' => $data['titulo'] ?? 'Notificação',
            'mensagem' => $data['mensagem'] ?? '',
            'url' => $data['url'] ?? null,
            'label' => $data['label'] ?? 'Ver detalhes',
            'prioridade' => $prioridade,
            'prioridade_label' => $this->prioridadeOptions()[$prioridade] ?? ucfirst($prioridade),
            'escopo' => $data['escopo'] ?? null,
            'enviado_por_nome' => $data['enviado_por_nome'] ?? null,
            'lida' => filled($notification->read_at),
            'criada_em' => $createdAt->format('d/m/Y H:i'),
            'criada_em_humano' => $createdAt->diffForHumans(),
            'lida_em' => $readAt?->format('d/m/Y H:i'),
        ];
    }

    private function formatarEnvio(NotificacaoEnvio $envio): array
    {
        $prioridade = $envio->prioridade ?: 'normal';

        return [
            'id' => $envio->id,
            'kind' => 'sent',
            'titulo' => $envio->titulo,
            'mensagem' => $envio->mensagem,
            'url' => $envio->url,
            'label' => $envio->label ?: 'Ver detalhes',
            'prioridade' => $prioridade,
            'prioridade_label' => $this->prioridadeOptions()[$prioridade] ?? ucfirst($prioridade),
            'destino_label' => $envio->destino_label,
            'destinatarios_count' => $envio->destinatarios_count,
            'autor' => $envio->autor?->name ?? 'Sistema',
            'criada_em' => $envio->created_at?->format('d/m/Y H:i'),
            'criada_em_humano' => $envio->created_at?->diffForHumans(),
        ];
    }

    private function normalizeMode(string $mode, User $user): string
    {
        if (! in_array($mode, ['ativas', 'historico', 'todas', 'enviadas'], true)) {
            return 'todas';
        }

        if ($mode === 'enviadas' && ! $this->canCreate($user)) {
            return 'todas';
        }

        return $mode;
    }

    private function pagination(int $page, int $perPage, int $total): array
    {
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
            'from' => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
            'to' => min($total, $page * $perPage),
            'has_previous' => $page > 1,
            'has_next' => $page < $lastPage,
        ];
    }

    private function normalizePage(mixed $page): int
    {
        return max(1, (int) $page);
    }

    private function normalizePerPage(mixed $perPage): int
    {
        return max(9, min(60, (int) $perPage));
    }

    private function likeNeedle(string $value): string
    {
        return '%'.str_replace(['%', '_'], ['\%', '\_'], $value).'%';
    }
}
