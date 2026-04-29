<?php

namespace App\Filament\Admin\Pages;

use App\Models\Escola;
use App\Models\NotificacaoEnvio;
use App\Models\Professor;
use App\Models\Role;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\SistemaNotification;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Pages\Page;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use UnitEnum;

class CentralNotificacoes extends Page
{
    protected string $view = 'filament.pages.central-notificacoes';

    protected static ?string $title = 'Central de Notificações';

    protected static ?string $navigationLabel = 'Notificações';

    protected static ?string $slug = 'central-notificacoes';

    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    public string $modo = 'ativas';

    public string $busca = '';

    public string $periodo = '30';

    public string $prioridade = 'todas';

    public int $limite = 25;

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasPermissionTo('Visualizar Notificações') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('nova_notificacao')
                ->label('Nova notificação')
                ->icon(Heroicon::OutlinedMegaphone)
                ->color('primary')
                ->slideOver()
                ->modalSubmitActionLabel('Enviar notificação')
                ->modalHeading('Nova notificação')
                ->modalDescription('Crie um aviso e escolha exatamente quem deve receber.')
                ->visible(fn (): bool => $this->podeCriarNotificacoes())
                ->schema([
                    Components\Section::make('Mensagem')
                        ->schema([
                            TextInput::make('titulo')
                                ->label('Título')
                                ->required()
                                ->maxLength(120),

                            Textarea::make('mensagem')
                                ->label('Mensagem')
                                ->required()
                                ->rows(5)
                                ->maxLength(1500),

                            Select::make('prioridade')
                                ->label('Prioridade')
                                ->options($this->prioridadeOptions())
                                ->default('normal')
                                ->native(false)
                                ->required(),

                            TextInput::make('url')
                                ->label('Link de ação')
                                ->placeholder('https://...')
                                ->maxLength(2048),

                            TextInput::make('label')
                                ->label('Texto do botão')
                                ->placeholder('Ver detalhes')
                                ->maxLength(80),
                        ])
                        ->columns(1),

                    Components\Section::make('Destinatários')
                        ->schema([
                            Select::make('destino_tipo')
                                ->label('Enviar para')
                                ->options($this->destinoTipoOptions())
                                ->default('todos')
                                ->native(false)
                                ->live()
                                ->required(),

                            Select::make('usuarios_ids')
                                ->label('Usuários')
                                ->options(fn (): array => $this->usuariosOptions())
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->visible(fn (Get $get): bool => $get('destino_tipo') === 'usuarios')
                                ->required(fn (Get $get): bool => $get('destino_tipo') === 'usuarios'),

                            Select::make('roles_ids')
                                ->label('Níveis de acesso')
                                ->options(fn (): array => $this->rolesOptions())
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->visible(fn (Get $get): bool => $get('destino_tipo') === 'roles')
                                ->required(fn (Get $get): bool => $get('destino_tipo') === 'roles'),

                            Select::make('escolas_ids')
                                ->label('Escolas')
                                ->options(fn (): array => $this->escolasOptions())
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->visible(fn (Get $get): bool => $get('destino_tipo') === 'escolas')
                                ->required(fn (Get $get): bool => $get('destino_tipo') === 'escolas'),

                            Select::make('turmas_ids')
                                ->label('Turmas')
                                ->options(fn (): array => $this->turmasOptions())
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->visible(fn (Get $get): bool => $get('destino_tipo') === 'professores_turmas')
                                ->required(fn (Get $get): bool => $get('destino_tipo') === 'professores_turmas'),

                            Select::make('permissoes')
                                ->label('Permissões')
                                ->options(fn (): array => $this->permissoesOptions())
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->visible(fn (Get $get): bool => $get('destino_tipo') === 'permissoes')
                                ->required(fn (Get $get): bool => $get('destino_tipo') === 'permissoes'),
                        ])
                        ->columns(1),
                ])
                ->action(fn (array $data) => $this->enviarNotificacao($data)),
        ];
    }

    public function refreshCentral(): void
    {
        // Usado pelo wire:poll para manter contadores e listas atualizados.
    }

    public function setModo(string $modo): void
    {
        if (! in_array($modo, ['ativas', 'historico', 'todas', 'enviadas'], true)) {
            return;
        }

        if ($modo === 'enviadas' && ! $this->podeCriarNotificacoes()) {
            return;
        }

        $this->modo = $modo;
        $this->limite = 25;
    }

    public function updatedBusca(): void
    {
        $this->limite = 25;
    }

    public function updatedPeriodo(): void
    {
        $this->limite = 25;
    }

    public function updatedPrioridade(): void
    {
        $this->limite = 25;
    }

    public function carregarMais(): void
    {
        $this->limite += 25;
    }

    public function limparFiltros(): void
    {
        $this->busca = '';
        $this->periodo = '30';
        $this->prioridade = 'todas';
        $this->limite = 25;
    }

    public function marcarComoLida(string $id): void
    {
        $updated = $this->notificationBaseQuery()
            ->where('id', $id)
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            $this->dispatch('refresh-notifications');
        }
    }

    public function marcarComoNaoLida(string $id): void
    {
        $updated = $this->notificationBaseQuery()
            ->where('id', $id)
            ->update([
                'read_at' => null,
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            $this->dispatch('refresh-notifications');
        }
    }

    public function marcarTodasComoLidas(): void
    {
        $updated = $this->notificationBaseQuery()
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            $this->dispatch('refresh-notifications');

            FilamentNotification::make()
                ->title('Notificações atualizadas')
                ->body("{$updated} notificação(ões) marcadas como lidas.")
                ->success()
                ->send();
        }
    }

    public function stats(): array
    {
        $base = $this->notificationBaseQuery();

        return [
            'ativas' => (clone $base)->whereNull('read_at')->count(),
            'historico' => (clone $base)->whereNotNull('read_at')->count(),
            'hoje' => (clone $base)->where('created_at', '>=', now()->startOfDay())->count(),
            'urgentes' => (clone $base)
                ->whereNull('read_at')
                ->where('data', 'like', '%"prioridade":"urgente"%')
                ->count(),
            'enviadas' => $this->podeCriarNotificacoes()
                ? NotificacaoEnvio::query()->count()
                : 0,
        ];
    }

    public function notificacoes(): Collection
    {
        return $this->notificationQuery()
            ->limit($this->limite)
            ->get()
            ->map(fn (object $notification): array => $this->formatarNotificacao($notification));
    }

    public function temMaisNotificacoes(): bool
    {
        return $this->notificationQuery()
            ->limit($this->limite + 1)
            ->pluck('id')
            ->count() > $this->limite;
    }

    public function envios(): Collection
    {
        if (! $this->podeCriarNotificacoes()) {
            return collect();
        }

        return $this->enviosQuery()
            ->limit($this->limite)
            ->get()
            ->map(fn (NotificacaoEnvio $envio): array => $this->formatarEnvio($envio));
    }

    public function temMaisEnvios(): bool
    {
        if (! $this->podeCriarNotificacoes()) {
            return false;
        }

        return $this->enviosQuery()
            ->limit($this->limite + 1)
            ->pluck('id')
            ->count() > $this->limite;
    }

    public function podeCriarNotificacoes(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

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

    public function usuariosOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->limit(800)
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(fn (User $user): array => [
                $user->id => trim("{$user->name} · {$user->email}"),
            ])
            ->all();
    }

    public function rolesOptions(): array
    {
        return Role::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function escolasOptions(): array
    {
        return Escola::query()
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
    }

    public function turmasOptions(): array
    {
        return Turma::query()
            ->with(['escola:id,nome', 'serie:id,nome'])
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(function (Turma $turma): array {
                $partes = array_filter([
                    $turma->escola?->nome,
                    $turma->serie?->nome,
                    $turma->nome,
                    $turma->turno,
                ]);

                return [$turma->id => implode(' · ', $partes)];
            })
            ->all();
    }

    public function permissoesOptions(): array
    {
        return Permission::query()
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();
    }

    private function enviarNotificacao(array $data): void
    {
        abort_unless($this->podeCriarNotificacoes(), 403);

        [$destinatarios, $destinoLabel, $destinoIds] = $this->resolverDestinatarios($data);

        if ($destinatarios->isEmpty()) {
            FilamentNotification::make()
                ->title('Nenhum destinatário encontrado')
                ->body('Revise o público selecionado antes de enviar.')
                ->warning()
                ->send();

            return;
        }

        DB::transaction(function () use ($data, $destinatarios, $destinoLabel, $destinoIds): void {
            $envio = NotificacaoEnvio::query()->create([
                'user_id' => Auth::id(),
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
            ]);

            /** @var User|null $autor */
            $autor = Auth::user();

            $destinatarios->each(function (User $destinatario) use ($data, $destinoLabel, $envio, $autor): void {
                $destinatario->notify(new SistemaNotification(
                    titulo: $data['titulo'],
                    mensagem: $data['mensagem'],
                    url: filled($data['url'] ?? null) ? $data['url'] : null,
                    label: filled($data['label'] ?? null) ? $data['label'] : 'Ver detalhes',
                    prioridade: $data['prioridade'] ?? 'normal',
                    escopo: $destinoLabel,
                    metadata: [
                        'envio_id' => (string) $envio->id,
                        'enviado_por_id' => $autor?->id,
                        'enviado_por_nome' => $autor?->name,
                        'manual' => true,
                    ],
                ));
            });
        });

        $this->dispatch('refresh-notifications');

        FilamentNotification::make()
            ->title('Notificação enviada')
            ->body($destinatarios->count().' destinatário(s) receberão o aviso.')
            ->success()
            ->send();
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
            $query->whereHas('roles', fn ($roles) => $roles->whereIn('roles.id', $ids));
        } elseif ($tipo === 'escolas') {
            $ids = $this->idsSelecionados($data['escolas_ids'] ?? []);
            $query->where(function ($usuarios) use ($ids): void {
                $usuarios
                    ->whereIn('id_escola', $ids)
                    ->orWhereHas('escolas', fn ($escolas) => $escolas->whereIn('escolas.id', $ids))
                    ->orWhereHas('professores', fn ($professores) => $professores->whereIn('id_escola', $ids));
            });
        } elseif ($tipo === 'professores_turmas') {
            $ids = $this->idsSelecionados($data['turmas_ids'] ?? []);
            $userIds = Professor::query()
                ->whereNotNull('user_id')
                ->where(function ($professores) use ($ids): void {
                    $professores
                        ->whereHas('turmas', fn ($turmas) => $turmas->whereIn('turmas.id', $ids))
                        ->orWhereHas('turmasFuncao', fn ($turmas) => $turmas->whereIn('turmas.id', $ids));
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

    private function notificationBaseQuery(): Builder
    {
        return DB::table('notifications')
            ->where('notifiable_id', Auth::id())
            ->where('notifiable_type', User::class);
    }

    private function notificationQuery(): Builder
    {
        $query = $this->notificationBaseQuery();

        if ($this->modo === 'ativas') {
            $query->whereNull('read_at');
        } elseif ($this->modo === 'historico') {
            $query->whereNotNull('read_at');
        }

        $this->aplicarFiltrosNotificacao($query);

        return $query
            ->orderByRaw('read_at is null desc')
            ->orderByDesc('created_at');
    }

    private function enviosQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = NotificacaoEnvio::query()
            ->with('autor:id,name');

        if ($this->periodo !== 'todos') {
            $query->where('created_at', '>=', now()->subDays((int) $this->periodo));
        }

        if ($this->prioridade !== 'todas') {
            $query->where('prioridade', $this->prioridade);
        }

        if (filled($this->busca)) {
            $busca = '%'.str_replace(['%', '_'], ['\%', '\_'], $this->busca).'%';

            $query->where(function ($envios) use ($busca): void {
                $envios
                    ->where('titulo', 'like', $busca)
                    ->orWhere('mensagem', 'like', $busca)
                    ->orWhere('destino_label', 'like', $busca);
            });
        }

        return $query->orderByDesc('created_at');
    }

    private function aplicarFiltrosNotificacao(Builder $query): void
    {
        if ($this->periodo !== 'todos') {
            $query->where('created_at', '>=', now()->subDays((int) $this->periodo));
        }

        if ($this->prioridade !== 'todas') {
            $query->where(function (Builder $prioridades): void {
                $prioridades->where('data', 'like', '%"prioridade":"'.$this->prioridade.'"%');

                if ($this->prioridade === 'normal') {
                    $prioridades->orWhere('data', 'not like', '%"prioridade":%');
                }
            });
        }

        if (filled($this->busca)) {
            $busca = '%'.str_replace(['%', '_'], ['\%', '\_'], $this->busca).'%';

            $query->where(function ($notificacoes) use ($busca): void {
                $notificacoes
                    ->where('data', 'like', $busca)
                    ->orWhere('type', 'like', $busca);
            });
        }
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
            'titulo' => $data['titulo'] ?? 'Notificação',
            'mensagem' => $data['mensagem'] ?? '',
            'url' => $data['url'] ?? null,
            'label' => $data['label'] ?? 'Ver detalhes',
            'prioridade' => $prioridade,
            'prioridade_label' => $this->prioridadeOptions()[$prioridade] ?? ucfirst($prioridade),
            'prioridade_meta' => $this->prioridadeMeta($prioridade),
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
            'titulo' => $envio->titulo,
            'mensagem' => $envio->mensagem,
            'url' => $envio->url,
            'label' => $envio->label ?: 'Ver detalhes',
            'prioridade' => $prioridade,
            'prioridade_label' => $this->prioridadeOptions()[$prioridade] ?? ucfirst($prioridade),
            'prioridade_meta' => $this->prioridadeMeta($prioridade),
            'destino_label' => $envio->destino_label,
            'destinatarios_count' => $envio->destinatarios_count,
            'autor' => $envio->autor?->name ?? 'Sistema',
            'criada_em' => $envio->created_at?->format('d/m/Y H:i'),
            'criada_em_humano' => $envio->created_at?->diffForHumans(),
        ];
    }

    private function prioridadeMeta(string $prioridade): array
    {
        return match ($prioridade) {
            'urgente' => [
                'class' => 'nc-priority nc-priority--urgent',
                'icon' => 'heroicon-o-exclamation-triangle',
            ],
            'alta' => [
                'class' => 'nc-priority nc-priority--high',
                'icon' => 'heroicon-o-shield-exclamation',
            ],
            'informativa' => [
                'class' => 'nc-priority nc-priority--info',
                'icon' => 'heroicon-o-information-circle',
            ],
            default => [
                'class' => 'nc-priority nc-priority--normal',
                'icon' => 'heroicon-o-check-circle',
            ],
        };
    }
}
