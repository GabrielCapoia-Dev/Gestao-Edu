<?php

namespace App\Filament\Admin\Resources\Pedidos\Tables;

use App\Filament\Admin\Actions\VincularSetorBulkAction;
use App\Filament\Admin\Components\SliderRating;
use App\Filament\Admin\Resources\Pedidos\Tables\Actions\ExportarRelatorioAction;
use App\Models\EmpresaContratada;
use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\Enums\ResultadoFeedbackPedido;
use App\Models\Enums\SetorAccessCapability;
use App\Models\Pedido;
use App\Models\Setor;
use App\Models\TipoManutencao;
use App\Models\TipoManutencaoOpcao;
use App\Models\TipoStatus;
use App\Models\User;
use App\Services\Exports\ExportRequestService;
use App\Services\PedidoService;
use App\Services\SetorPedidoAccessService;
use App\Support\PedidoImageUpload;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\Layout\View as LayoutView;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PedidosTable
{
    public static function configure(Table $table, ?User $user = null): Table
    {
        $service = app(PedidoService::class);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with([
                    'pedidoPrincipal.tipoManutencao',
                    'pedidoPrincipal.tipoStatus',
                    'pedidoPrincipal.escola',
                    'pedidoPrincipal.setor',
                    'pedidoPrincipal.setorOrigem',
                    'pedidoPrincipal.empresaContratada',
                    'pedidoPrincipal.solicitante',
                    'pedidoPrincipal.problemas',
                    'tipoManutencao',
                    'tipoStatus',
                    'escola',
                    'setor',
                    'setorOrigem',
                    'empresaContratada',
                    'solicitante.escola',
                    'solicitante.escolas',
                ])
                ->withCount(['pedidosAdicionais', 'problemas']))
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->defaultSort('updated_at', 'desc')
            ->striped()
            ->searchable([
                'pedidoPrincipal.numero_protocolo',
                'tipoManutencao.nome',
                'escola.nome',
                'setor.nome',
                'setorOrigem.nome',
                'tipoStatus.nome',
                'empresaContratada.nome',
                'nivel_prioridade',
                'nome_solicitante',
                'descricao_pedido',
                'problemas.texto_problema',
                'solicitante.name',
                'responsavel.name',
            ])
            ->searchPlaceholder('Buscar por protocolo, escola, setor, status, tipo ou descrição')
            ->columns(static::columns($user))
            ->filters(static::filters($user), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(12)
            ->recordClasses(fn (Pedido $record): ?string => ! $record->is_pedido_adicional
                && (int) ($record->pedidos_adicionais_count ?? 0) > 0
                    ? 'pedido-card--has-additionals'
                    : null)
            ->recordActions(static::actions($user, $service), position: RecordActionsPosition::AfterContent)
            ->groupedBulkActions(static::bulkActions($user, $service))
            ->headerActions(static::headerActions($user));
    }

    public static function headerActions(?User $user): array
    {
        return [
            ExportarRelatorioAction::make(),
        ];
    }

    public static function filters(?User $user = null): array
    {
        return [
            SelectFilter::make('tipo_status_id')
                ->label('Status')
                ->columnSpan(3)
                ->multiple()
                ->relationship(
                    name: 'tipoStatus',
                    titleAttribute: 'nome',
                    modifyQueryUsing: fn ($query) => $query->where('ativo', true)->orderBy('nome')
                )
                ->searchable()
                ->preload(),

            SelectFilter::make('setor_id')
                ->label('Setor')
                ->columnSpan(3)
                ->options(fn () => app(SetorPedidoAccessService::class)->optionsForCapability(
                    auth()->user(),
                    SetorAccessCapability::LISTAR,
                ))
                ->searchable()
                ->preload(),

            SelectFilter::make('escola_id')
                ->label('Escola')
                ->columnSpan(3)
                ->relationship('escola', 'nome', modifyQueryUsing: function ($query) use ($user) {
                    $service = app(PedidoService::class);
                    $escolaIds = $service->escolaIdsParaEscopo($user);

                    return $query
                        ->where('ativo', true)
                        ->when(
                            ! $service->podeVerTodosOsPedidos($user) && $escolaIds !== [],
                            fn ($builder) => $builder->whereIn('id', $escolaIds)
                        )
                        ->orderBy('nome');
                })
                ->searchable()
                ->preload(),

            SelectFilter::make('empresa_contratada_id')
                ->label('Empresa')
                ->columnSpan(3)
                ->options(fn (): array => $user
                    ? EmpresaContratada::query()
                        ->where('ativo', true)
                        ->doSetorDoUsuario($user)
                        ->orderBy('nome')
                        ->pluck('nome', 'id')
                        ->toArray()
                    : [])
                ->searchable()
                ->preload(),

            SelectFilter::make('nivel_prioridade')
                ->label('Prioridade')
                ->columnSpan(3)
                ->options(
                    collect(NivelEmergenciaPedido::cases())
                        ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                        ->toArray()
                ),

            SelectFilter::make('relacao_pedido')
                ->label('Tipo de pedido')
                ->columnSpan(3)
                ->options([
                    'principais' => 'Pedidos principais',
                    'com_adicionais' => 'Principais com adicionais',
                    'sem_adicionais' => 'Principais sem adicionais',
                    'adicionais' => 'Pedidos adicionais',
                ])
                ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                    'principais' => $query->where('is_pedido_adicional', false),
                    'com_adicionais' => $query
                        ->where('is_pedido_adicional', false)
                        ->has('pedidosAdicionais'),
                    'sem_adicionais' => $query
                        ->where('is_pedido_adicional', false)
                        ->doesntHave('pedidosAdicionais'),
                    'adicionais' => $query->where('is_pedido_adicional', true),
                    default => $query,
                }),

            Filter::make('manutencao')
                ->label('Manutenção')
                ->columnSpan(6)
                ->columns(2)
                ->schema([
                    Select::make('tipo_manutencao_nome')
                        ->label('Tipo de manutenção')
                        ->placeholder('Todos os tipos')
                        ->options(fn (): array => TipoManutencao::query()
                            ->where('ativo', true)
                            ->whereNotNull('nome')
                            ->orderBy('nome')
                            ->pluck('nome')
                            ->unique()
                            ->mapWithKeys(fn (string $nome): array => [$nome => $nome])
                            ->toArray())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(fn (callable $set) => $set('tipo_manutencao_opcao_texto', null)),

                    Select::make('tipo_manutencao_opcao_texto')
                        ->label('Opção do tipo')
                        ->placeholder('Todas as opções')
                        ->options(fn (Get $get): array => TipoManutencaoOpcao::query()
                            ->where('ativo', true)
                            ->when(
                                filled($get('tipo_manutencao_nome')),
                                fn (Builder $query) => $query->whereIn(
                                    'tipo_manutencao_id',
                                    TipoManutencao::query()
                                        ->where('nome', $get('tipo_manutencao_nome'))
                                        ->pluck('id')
                                )
                            )
                            ->orderBy('texto')
                            ->pluck('texto')
                            ->unique()
                            ->mapWithKeys(fn (string $texto): array => [$texto => $texto])
                            ->toArray())
                        ->visible(fn (Get $get): bool => filled($get('tipo_manutencao_nome')))
                        ->searchable()
                        ->preload(),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            filled($data['tipo_manutencao_nome'] ?? null),
                            fn (Builder $builder) => $builder->whereHas(
                                'tipoManutencao',
                                fn (Builder $tipoQuery) => $tipoQuery->where('nome', $data['tipo_manutencao_nome'])
                            )
                        )
                        ->when(
                            filled($data['tipo_manutencao_opcao_texto'] ?? null),
                            fn (Builder $builder) => $builder->whereHas(
                                'problemas',
                                fn (Builder $problemaQuery) => $problemaQuery->where('texto_problema', $data['tipo_manutencao_opcao_texto'])
                            )
                        );
                }),

            Filter::make('periodo_identificacao')
                ->label('Identificação do problema')
                ->columnSpan(6)
                ->columns(2)
                ->schema([
                    DatePicker::make('data_inicio')
                        ->label('De'),
                    DatePicker::make('data_fim')
                        ->label('Até'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            filled($data['data_inicio'] ?? null),
                            fn (Builder $builder) => $builder->whereDate('data_identificacao_problema', '>=', $data['data_inicio'])
                        )
                        ->when(
                            filled($data['data_fim'] ?? null),
                            fn (Builder $builder) => $builder->whereDate('data_identificacao_problema', '<=', $data['data_fim'])
                        );
                }),

            Filter::make('periodo_previsto')
                ->label('Previsão')
                ->columnSpan(6)
                ->columns(2)
                ->schema([
                    DatePicker::make('data_inicio')
                        ->label('De'),
                    DatePicker::make('data_fim')
                        ->label('Até'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            filled($data['data_inicio'] ?? null),
                            fn (Builder $builder) => $builder->whereDate('data_prevista', '>=', $data['data_inicio'])
                        )
                        ->when(
                            filled($data['data_fim'] ?? null),
                            fn (Builder $builder) => $builder->whereDate('data_prevista', '<=', $data['data_fim'])
                        );
                }),

            Filter::make('periodo_conclusao')
                ->label('Conclusão')
                ->columnSpan(6)
                ->columns(2)
                ->schema([
                    DatePicker::make('data_inicio')
                        ->label('De'),
                    DatePicker::make('data_fim')
                        ->label('Até'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            filled($data['data_inicio'] ?? null),
                            fn (Builder $builder) => $builder->whereDate('data_entrega', '>=', $data['data_inicio'])
                        )
                        ->when(
                            filled($data['data_fim'] ?? null),
                            fn (Builder $builder) => $builder->whereDate('data_entrega', '<=', $data['data_fim'])
                        );
                }),
        ];
    }

    public static function columns(?User $user): array
    {
        return [
            Split::make([
                LayoutView::make('filament.admin.resources.pedidos.tables.pedido-card-top-actions')
                    ->grow(false)
                    ->extraAttributes(['class' => 'pedido-card-actions-slot']),

                TextColumn::make('numero_protocolo')
                    ->label('Protocolo')
                    ->description('Protocolo', position: 'above')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Copiado')
                    ->copyMessageDuration(1500)
                    ->tooltip('Clique para copiar')
                    ->weight('bold')
                    ->grow(false)
                    ->extraAttributes(['class' => 'pedido-card-protocol'], merge: true),
            ])
                ->from('md')
                ->extraAttributes(['class' => 'pedido-card-top']),

            Stack::make([
                TextColumn::make('pedidoPrincipal.numero_protocolo')
                    ->label('Pedido original')
                    ->description('Pedido original', position: 'above')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Pedido principal')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraAttributes(['class' => 'pedido-card-field'], merge: true),
            ])
                ->extraAttributes(['class' => 'pedido-card-original-stack']),

            Grid::make([
                'default' => 1,
                'md' => 2,
                'xl' => 4,
            ])
                ->schema([
                    TextColumn::make('tipoManutencao.nome')
                        ->label('Tipo')
                        ->description('Tipo', position: 'above')
                        ->sortable()
                        ->color('gray')
                        ->wrap()
                        ->extraAttributes(['class' => 'pedido-card-field'], merge: true),

                    TextColumn::make('escola.nome')
                        ->label('Escola')
                        ->description('Escola', position: 'above')
                        ->icon('heroicon-o-building-office-2')
                        ->state(fn (Pedido $record): ?string => static::nomeEscolaDoPedido($record))
                        ->placeholder('Escola não informada')
                        ->sortable()
                        ->wrap()
                        ->extraAttributes(['class' => 'pedido-card-field pedido-card-field--school'], merge: true),

                    TextColumn::make('setor.nome_completo')
                        ->label('Setor atual')
                        ->description('Setor atual', position: 'above')
                        ->icon('heroicon-o-map-pin')
                        ->badge()
                        ->color('primary')
                        ->placeholder('Setor não informado')
                        ->wrap()
                        ->extraAttributes(['class' => 'pedido-card-field pedido-card-field--sector'], merge: true),

                    TextColumn::make('setorOrigem.nome_completo')
                        ->label('Setor de origem')
                        ->description('Setor de origem', position: 'above')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->placeholder('Origem não informada')
                        ->wrap()
                        ->extraAttributes(['class' => 'pedido-card-field pedido-card-field--sector'], merge: true),

                    TextColumn::make('acesso_operacional')
                        ->label('Seu acesso')
                        ->description('Seu acesso', position: 'above')
                        ->badge()
                        ->icon(fn (Pedido $record): string => static::iconeAcessoOperacional($record, $user))
                        ->state(fn (Pedido $record): string => static::rotuloAcessoOperacional($record, $user))
                        ->color(fn (Pedido $record): string => static::corAcessoOperacional($record, $user))
                        ->wrap()
                        ->extraAttributes(['class' => 'pedido-card-field pedido-card-field--access'], merge: true),

                    TextColumn::make('pedidos_adicionais_count')
                        ->label('Adicionais')
                        ->description('Adicionais', position: 'above')
                        ->badge()
                        ->icon(fn (Pedido $record): ?string => (int) ($record->pedidos_adicionais_count ?? 0) > 0
                            ? 'heroicon-o-plus-circle'
                            : null)
                        ->color(fn (Pedido $record): string => (int) ($record->pedidos_adicionais_count ?? 0) > 0 ? 'warning' : 'gray')
                        ->state(fn (Pedido $record): string => (int) ($record->pedidos_adicionais_count ?? 0).' adicional(is)')
                        ->sortable(['pedidos_adicionais_count'])
                        ->weight('bold')
                        ->wrap()
                        ->toggleable()
                        ->extraAttributes(['class' => 'pedido-card-field pedido-card-field--additionals'], merge: true),

                    TextColumn::make('tipoStatus.nome')
                        ->label('Status')
                        ->description('Status', position: 'above')
                        ->badge()
                        ->formatStateUsing(fn (Pedido $record): string => static::statusListagem($record))
                        ->color(fn (Pedido $record) => Color::hex(static::corStatusListagem($record)))
                        ->wrap()
                        ->extraAttributes(['class' => 'pedido-card-field pedido-card-field--status'], merge: true),

                    TextColumn::make('nivel_prioridade')
                        ->label('Prioridade')
                        ->description('Prioridade', position: 'above')
                        ->badge()
                        ->color(fn (Pedido $record) => Color::hex(
                            match ($record->nivel_prioridade?->value) {
                                'Emergencial' => '#a10000',
                                'Corretivo' => '#973f00',
                                'Preventivo' => '#013891',
                                default => '#2b2b2b',
                            }
                        ))
                        ->wrap()
                        ->extraAttributes(['class' => 'pedido-card-field pedido-card-field--priority'], merge: true),
                ])
                ->extraAttributes(['class' => 'pedido-card-main-grid']),

            Grid::make([
                'default' => 1,
                'sm' => 2,
                'xl' => 4,
            ])
                ->schema([
                    TextColumn::make('data_prevista')
                        ->label('Previsto para')
                        ->description('Previsto para', position: 'above')
                        ->icon('heroicon-o-clock')
                        ->sortable()
                        ->date('d/m/Y')
                        ->color(function (Pedido $record) {
                            if (! $record->data_prevista) {
                                return null;
                            }

                            $prevista = Carbon::parse($record->data_prevista);

                            if ($record->data_entrega) {
                                $entrega = Carbon::parse($record->data_entrega);

                                return $entrega->greaterThan($prevista)
                                    ? Color::hex('#a10000')
                                    : Color::hex('#10b981');
                            }

                            if ($prevista->isPast()) {
                                return Color::hex('#a10000');
                            }

                            return now()->diffInDays($prevista, false) <= 15
                                ? Color::hex('#ff6600')
                                : null;
                        })
                        ->size('sm')
                        ->placeholder('Sem data prevista')
                        ->wrap()
                        ->extraAttributes(['class' => 'pedido-card-field pedido-card-field--date'], merge: true),

                    TextColumn::make('data_entrega')
                        ->label('Concluído em')
                        ->description('Concluído em', position: 'above')
                        ->icon('heroicon-o-check-circle')
                        ->sortable()
                        ->date('d/m/Y')
                        ->color('success')
                        ->size('sm')
                        ->placeholder('Não concluído')
                        ->wrap()
                        ->extraAttributes(['class' => 'pedido-card-field pedido-card-field--date'], merge: true),

                    TextColumn::make('created_at')
                        ->label('Criado em')
                        ->dateTime('d/m/Y H:i')
                        ->description('Criado em', position: 'above')
                        ->wrap()
                        ->toggleable(isToggledHiddenByDefault: false)
                        ->extraAttributes(['class' => 'pedido-card-field pedido-card-field--date'], merge: true),

                    TextColumn::make('updated_at')
                        ->label('Atualizado em')
                        ->dateTime('d/m/Y H:i')
                        ->description('Atualizado em', position: 'above')
                        ->wrap()
                        ->toggleable(isToggledHiddenByDefault: false)
                        ->extraAttributes(['class' => 'pedido-card-field pedido-card-field--date'], merge: true),

                ])
                ->extraAttributes(['class' => 'pedido-card-meta-grid']),

            LayoutView::make('filament.admin.resources.pedidos.tables.pedido-card-footer-actions')
                ->extraAttributes(['class' => 'pedido-card-footer-slot']),

            TextColumn::make('created_at_sort')
                ->label('Data de criação')
                ->sortable(['created_at'])
                ->extraAttributes(['class' => 'pedido-card-sort-only'], merge: true),

            TextColumn::make('tipo_pedido_sort')
                ->label('Tipo de pedido')
                ->sortable(['is_pedido_adicional'])
                ->extraAttributes(['class' => 'pedido-card-sort-only'], merge: true),

            TextColumn::make('pedido_principal_sort')
                ->label('Pedido principal')
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                    ->orderBy(
                        DB::table('pedidos as principal')
                            ->select('principal.numero_protocolo')
                            ->whereColumn('principal.id', 'pedidos.pedido_principal_id')
                            ->limit(1),
                        $direction
                    ))
                ->extraAttributes(['class' => 'pedido-card-sort-only'], merge: true),

            TextColumn::make('updated_at_sort')
                ->label('Data de atualização')
                ->sortable(['updated_at'])
                ->extraAttributes(['class' => 'pedido-card-sort-only'], merge: true),

        ];
    }

    public static function actions(?User $user, PedidoService $service): array
    {
        return [
            Action::make('visualizar')
                ->label('Visualizar')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->slideOver()
                ->modalWidth('7xl')
                ->extraModalWindowAttributes(['class' => 'pedido-view-modal-window'], merge: true)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->visible(fn () => $user?->hasPermissionTo('Visualizar Histórico de Pedidos') ?? false)
                ->modalContent(function (Pedido $record) {
                    $record->load([
                        'tipoManutencao',
                        'tipoStatus',
                        'pedidoPrincipal.tipoManutencao',
                        'pedidoPrincipal.tipoStatus',
                        'pedidoPrincipal.escola',
                        'pedidoPrincipal.setor',
                        'pedidoPrincipal.setorOrigem',
                        'pedidoPrincipal.empresaContratada',
                        'pedidoPrincipal.solicitante',
                        'pedidoPrincipal.problemas',
                        'escola',
                        'setor',
                        'empresaContratada',
                        'solicitante',
                        'responsavel',
                        'problemas',
                        'arquivos.usuario',
                        'ultimoFeedback.itens.problema',
                    ]);

                    $pedidoOriginal = $record->is_pedido_adicional ? $record->pedidoPrincipal : null;

                    $historico = $record->historicos()
                        ->with(['statusAnterior', 'statusNovo', 'usuario', 'setor'])
                        ->orderByDesc('created_at')
                        ->orderByRaw('CASE WHEN status_anterior_id IS NOT NULL THEN 1 ELSE 0 END DESC')
                        ->get();

                    $adicionais = $record->is_pedido_adicional
                        ? collect()
                        : $record->pedidosAdicionais()
                            ->with([
                                'tipoManutencao',
                                'problemas',
                                'tipoStatus',
                                'escola',
                                'setor',
                                'empresaContratada',
                                'solicitante',
                                'arquivos.usuario',
                                'feedbackItens.problema',
                            ])
                            ->get();

                    return view('components.pedido.visualizar', [
                        'pedido' => $record,
                        'historico' => $historico,
                        'adicionais' => $adicionais,
                        'pedidoOriginal' => $pedidoOriginal,
                        'usuario' => auth()->user(),
                    ]);
                }),

            Action::make('cancelar_adicional')
                ->label('Cancelar adicional')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(function (Pedido $record, array $arguments) use ($user, $service): bool {
                    $adicional = static::pedidoAdicionalParaAcao($record, $arguments);

                    return $adicional instanceof Pedido
                        && $service->podeCancelarPedidoAdicional($adicional, $user);
                })
                ->requiresConfirmation()
                ->modalHeading('Cancelar pedido adicional')
                ->modalDescription('O pedido adicional permanecera vinculado para consulta, mas não entrará na avaliação do pedido principal.')
                ->modalSubmitActionLabel('Cancelar adicional')
                ->schema([
                    Textarea::make('descricao')
                        ->label('Motivo do cancelamento')
                        ->required()
                        ->maxLength(1000),
                ])
                ->action(function (Pedido $record, array $arguments, array $data) use ($user, $service): void {
                    $adicional = static::pedidoAdicionalParaAcao($record, $arguments);

                    if (! $user || ! $adicional instanceof Pedido) {
                        return;
                    }

                    $cancelado = $service->cancelarPedidoAdicional(
                        $adicional,
                        $user,
                        trim((string) ($data['descricao'] ?? ''))
                    );

                    Notification::make()
                        ->title($cancelado ? 'Pedido adicional cancelado.' : 'Não foi possível cancelar o pedido adicional.')
                        ->color($cancelado ? 'warning' : 'danger')
                        ->send();
                }),

            Action::make('promover_adicional')
                ->label('Transformar em principal')
                ->icon('heroicon-o-arrow-up-circle')
                ->color('warning')
                ->visible(function (Pedido $record, array $arguments) use ($user, $service): bool {
                    $adicional = static::pedidoAdicionalParaAcao($record, $arguments);

                    return $adicional instanceof Pedido
                        && $service->podePromoverPedidoAdicional($adicional, $user);
                })
                ->requiresConfirmation()
                ->modalHeading('Transformar adicional em pedido principal')
                ->modalDescription('O pedido deixara de estar vinculado e passara a aparecer separadamente na fila, mantendo protocolo, fotos, problemas e histórico.')
                ->modalSubmitActionLabel('Transformar em principal')
                ->schema([
                    Select::make('tipo_status_id')
                        ->label('Status do pedido')
                        ->options(fn (): array => $service->statusOptionsParaPromocaoPedidoAdicional($user))
                        ->required()
                        ->searchable()
                        ->preload(),

                    Textarea::make('descricao')
                        ->label('Descrição da ação realizada')
                        ->required()
                        ->maxLength(1000),
                ])
                ->action(function (Pedido $record, array $arguments, array $data) use ($user, $service): void {
                    $adicional = static::pedidoAdicionalParaAcao($record, $arguments);
                    $status = TipoStatus::query()->find((int) ($data['tipo_status_id'] ?? 0));

                    if (! $user || ! $adicional instanceof Pedido || ! $status instanceof TipoStatus) {
                        return;
                    }

                    $promovido = $service->promoverPedidoAdicional(
                        $adicional,
                        $user,
                        $status,
                        trim((string) ($data['descricao'] ?? ''))
                    );

                    Notification::make()
                        ->title($promovido ? 'Pedido transformado em principal.' : 'Não foi possível transformar o pedido adicional.')
                        ->color($promovido ? 'success' : 'danger')
                        ->send();
                }),

            Action::make('vincular_adicionais')
                ->label('Vincular Adicionais')
                ->icon('heroicon-o-plus-circle')
                ->color('info')
                ->visible(fn (Pedido $record) => static::statusEh($record, 'Em Manutenção')
                    && ! $record->is_pedido_adicional
                    && $service->podeVincularAdicionaisAoPedido($record, $user))
                ->modalHeading('Vincular pedidos adicionais')
                ->modalSubmitActionLabel('Vincular')
                ->schema([
                    Section::make('Pedidos adicionais')
                        ->schema([
                            static::pedidosAdicionaisRepeater(true),
                        ]),
                ])
                ->action(function (Pedido $record, array $data) use ($user, $service) {
                    $criados = $service->criarPedidosAdicionais($record, $data['pedidos_adicionais'] ?? [], $user);

                    Notification::make()
                        ->title($criados->count().' pedido(s) adicional(is) vinculado(s).')
                        ->success()
                        ->send();
                }),

            Action::make('finalizar')
                ->label('Avaliar Pedido')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (Pedido $record) => static::statusEh($record, 'Em Manutenção')
                    && ! $record->is_pedido_adicional
                    && $service->podeAvaliarRegistro($record, $user))
                ->modalHeading('Avaliar Pedido')
                ->modalDescription('Registre a avaliação do atendimento ou reabra o pedido para que ele volte ao fluxo operacional.')
                ->modalSubmitActionLabel('Confirmar Avaliação')
                ->modalCancelActionLabel('Cancelar')
                ->modalWidth('6xl')
                ->schema(fn (Pedido $record) => static::avaliacaoSchema($record, $service))
                ->action(function (Pedido $record, array $data) use ($user, $service) {
                    $service->avaliarPedido($record, $data, $user);

                    Notification::make()
                        ->title(! empty($data['reabrir_pedido'])
                            ? 'Pedido reaberto para nova execução.'
                            : 'Pedido concluído com avaliação por problema.')
                        ->success()
                        ->send();
                }),

            Action::make('gerenciar')
                ->label('Gerenciar')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn (Pedido $record) => static::podeExibirAcaoGerenciar($record, $user, $service))
                ->action(function (Pedido $record) use ($user, $service) {
                    $service->assumirPedido($record, $user);

                    redirect(route('filament.admin.resources.pedidos.edit', $record));
                })
                ->openUrlInNewTab(false),
        ];
    }

    public static function podeExibirAcaoVisualizar(?User $user): bool
    {
        return $user?->hasPermissionTo('Visualizar Histórico de Pedidos') ?? false;
    }

    public static function podeExibirAcaoGerenciar(Pedido $record, ?User $user, ?PedidoService $service = null): bool
    {
        $service ??= app(PedidoService::class);

        return $service->podeGerenciarRegistro($record, $user);
    }

    public static function podeExibirAcaoVincularAdicionais(Pedido $record, ?User $user, ?PedidoService $service = null): bool
    {
        $service ??= app(PedidoService::class);

        return static::statusEh($record, 'Em Manutenção')
            && ! $record->is_pedido_adicional
            && $service->podeVincularAdicionaisAoPedido($record, $user);
    }

    public static function podeExibirAcaoFinalizar(Pedido $record, ?User $user): bool
    {
        return static::statusEh($record, 'Em Manutenção')
            && ! $record->is_pedido_adicional
            && app(PedidoService::class)->podeAvaliarRegistro($record, $user);
    }

    public static function bulkActions(?User $user, PedidoService $service): array
    {
        return [
            BulkAction::make('alterar_status')
                ->label('Alterar status')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => $service->statusOptionsParaAlteracaoEmMassa($user) !== [])
                ->schema([
                    Select::make('tipo_status_id')
                        ->label('Novo status')
                        ->options(fn (): array => $service->statusOptionsParaAlteracaoEmMassa($user))
                        ->searchable()
                        ->preload()
                        ->required(),

                    Textarea::make('descricao')
                        ->label('Observação')
                        ->rows(3)
                        ->maxLength(1000)
                        ->helperText('Se ficar em branco, o histórico usara a mensagem automatica.'),
                ])
                ->requiresConfirmation()
                ->modalHeading('Alterar status dos pedidos selecionados')
                ->modalDescription('Somente pedidos que você pode gerenciar serão atualizados. Status com fluxo próprio continuam nas ações especificas.')
                ->action(function (EloquentCollection $records, array $data) use ($user, $service) {
                    if (! $user) {
                        return;
                    }

                    $statusId = (int) ($data['tipo_status_id'] ?? 0);
                    $statusOptions = $service->statusOptionsParaAlteracaoEmMassa($user);

                    if (! array_key_exists($statusId, $statusOptions)) {
                        Notification::make()
                            ->title('Status indisponivel para alteracao em massa.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $novoStatus = TipoStatus::query()->findOrFail($statusId);
                    $observacao = trim((string) ($data['descricao'] ?? ''));
                    $alterados = 0;

                    DB::transaction(function () use ($records, $user, $service, $novoStatus, $observacao, &$alterados): void {
                        foreach ($records as $record) {
                            if (! $record instanceof Pedido || ! $service->podeGerenciarRegistro($record, $user)) {
                                continue;
                            }

                            if ((int) $record->tipo_status_id === (int) $novoStatus->id) {
                                continue;
                            }

                            $descricao = "Usuario {$user->name} alterou o status do pedido para {$novoStatus->nome}.";

                            if ($observacao !== '') {
                                $descricao .= ' '.$observacao;
                            }

                            $service->alterarStatus(
                                $record,
                                $novoStatus,
                                $user,
                                $descricao
                            );

                            $alterados++;
                        }
                    });

                    Notification::make()
                        ->title($alterados.' pedido(s) tiveram o status alterado.')
                        ->success()
                        ->send();
                })
                ->deselectRecordsAfterCompletion(),

            BulkAction::make('exportar_pdf_simplificado')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->visible(fn () => $user?->hasPermissionLike('exportar relatorios') ?? false)
                ->requiresConfirmation()
                ->modalHeading('Exportar pedidos selecionados em PDF')
                ->modalDescription('O arquivo será gerado em segundo plano com um pedido por página, contendo somente o cabecalho e as imagens do problema.')
                ->modalSubmitActionLabel('Enviar para fila')
                ->action(function (EloquentCollection $records) use ($user): mixed {
                    if (! $user) {
                        return null;
                    }

                    $pedidoIds = $records
                        ->filter(fn ($record): bool => $record instanceof Pedido)
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->filter()
                        ->values()
                        ->all();

                    if ($pedidoIds === []) {
                        Notification::make()
                            ->title('Nenhum pedido selecionado para exportação.')
                            ->danger()
                            ->send();

                        return null;
                    }

                    try {
                        $exportRequest = app(ExportRequestService::class)->queue(
                            user: $user,
                            type: 'pedido_relatorio_simplificado',
                            format: 'pdf',
                            filters: ['pedido_ids' => $pedidoIds],
                            label: 'PDF simplificado de pedidos',
                            metadata: ['source' => 'pedidos.bulk_action'],
                        );

                        Notification::make()
                            ->title($exportRequest->wasRecentlyCreated ? 'Exportação enviada para a fila' : 'Exportação já está em andamento')
                            ->body('Acompanhe o progresso em Minhas Exportacoes.')
                            ->success()
                            ->send();

                        return redirect()->route('filament.admin.pages.minhas-exportacoes', [
                            'download' => $exportRequest->getKey(),
                        ]);
                    } catch (\Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Não foi possível iniciar a exportação')
                            ->body('Tente novamente em alguns instantes.')
                            ->danger()
                            ->send();

                        return null;
                    }
                })
                ->deselectRecordsAfterCompletion(),

            VincularSetorBulkAction::make(
                permission: 'Encaminhar Pedidos para Setor',
                recordsLabel: 'pedidos selecionados',
                updateRecord: function (Pedido $record, int $setorId) use ($user, $service): bool {
                    if (! $user || ! $service->podeGerenciarRegistro($record, $user)) {
                        return false;
                    }

                    $service->encaminharParaSetor(
                        $record,
                        Setor::query()->findOrFail($setorId),
                        $user,
                    );

                    return true;
                },
                visible: fn (): bool => $user !== null
                    && app(SetorPedidoAccessService::class)->allowedSetorIds(
                        $user,
                        SetorAccessCapability::ENCAMINHAR,
                    ) !== [],
                options: fn (): array => app(SetorPedidoAccessService::class)->optionsForCapability(
                    $user,
                    SetorAccessCapability::ENCAMINHAR,
                ),
                assertCanUse: fn (int $setorId) => app(SetorPedidoAccessService::class)->assertCan(
                    $user,
                    SetorAccessCapability::ENCAMINHAR,
                    $setorId,
                ),
            ),

            BulkAction::make('enviar_para_empresa')
                ->label('Enviar para empresa')
                ->icon('heroicon-o-building-office-2')
                ->color('primary')
                ->visible(fn () => $service->podeEnviarParaEmpresa($user))
                ->schema([
                    Select::make('empresa_contratada_id')
                        ->label('Empresa')
                        ->options(fn (): array => EmpresaContratada::query()
                            ->where('ativo', true)
                            ->doSetorDoUsuario(Auth::user())
                            ->orderBy('nome')
                            ->pluck('nome', 'id')
                            ->toArray())
                        ->searchable()
                        ->preload()
                        ->required(),
                ])
                ->requiresConfirmation()
                ->modalHeading('Enviar pedidos selecionados para empresa')
                ->modalDescription('Somente pedidos que você pode gerenciar serão enviados para a empresa selecionada.')
                ->action(function (EloquentCollection $records, array $data) use ($user, $service) {
                    $enviados = 0;
                    $empresaId = (int) ($data['empresa_contratada_id'] ?? 0);

                    foreach ($records as $record) {
                        if (! $user || ! $record instanceof Pedido) {
                            continue;
                        }

                        if ($service->enviarParaEmpresa($record, $empresaId, $user)) {
                            $enviados++;
                        }
                    }

                    Notification::make()
                        ->title($enviados.' pedido(s) enviado(s) para a empresa.')
                        ->success()
                        ->send();
                })
                ->deselectRecordsAfterCompletion(),

            BulkAction::make('cancelar')
                ->label('Cancelar pedidos')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancelar pedidos selecionados')
                ->modalDescription('Somente pedidos que você pode gerenciar serão cancelados. Concluídos, cancelados e adicionais são ignorados.')
                ->schema([
                    Textarea::make('descricao')
                        ->label('Motivo')
                        ->required()
                        ->maxLength(1000),
                ])
                ->visible(fn () => ($user?->hasPermissionTo('Editar Pedidos') ?? false)
                    && app(SetorPedidoAccessService::class)->allowedSetorIds(
                        $user,
                        SetorAccessCapability::CANCELAR,
                    ) !== [])
                ->action(function (EloquentCollection $records, array $data) use ($user, $service) {
                    $cancelados = 0;

                    foreach ($records as $record) {
                        if (! $record instanceof Pedido || ! $user) {
                            continue;
                        }

                        if ($service->cancelarPedido($record, $user, $data['descricao'] ?? 'Cancelado em massa.')) {
                            $cancelados++;
                        }
                    }

                    Notification::make()
                        ->title($cancelados.' pedido(s) cancelado(s).')
                        ->warning()
                        ->send();
                }),
        ];
    }

    private static function pedidoAdicionalParaAcao(Pedido $record, array $arguments = []): ?Pedido
    {
        if ($record->is_pedido_adicional) {
            $record->loadMissing(['pedidoPrincipal.tipoStatus', 'tipoStatus']);

            return $record;
        }

        $adicionalId = $arguments['adicional'] ?? null;

        if (blank($adicionalId)) {
            return null;
        }

        return $record->pedidosAdicionais()
            ->with(['pedidoPrincipal.tipoStatus', 'tipoStatus'])
            ->find($adicionalId);
    }

    private static function avaliacaoSchema(Pedido $record, PedidoService $service): array
    {
        return [
            Section::make('Avaliação geral')
                ->schema([
                    Toggle::make('reabrir_pedido')
                        ->label('Reabrir pedido')
                        ->helperText('Caso o problema não tenha sido solucionado marque essa opção para reabrir o pedido')
                        ->inline(false)
                        ->live(),

                    Textarea::make('descricao')
                        ->label('Comentário geral')
                        ->required()
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            ...static::avaliacoesPorProblemaSchema($record, $service),

            Section::make('Fotos da Conclusão')
                ->schema([
                    FileUpload::make('fotos_conclusao')
                        ->label('Adicionar Fotos')
                        ->acceptedFileTypes(PedidoImageUpload::MIME_TYPES)
                        ->validationMessages([
                            'mimetypes' => PedidoImageUpload::MESSAGE,
                        ])
                        ->helperText(PedidoImageUpload::MESSAGE)
                        ->multiple()
                        ->maxFiles(10)
                        ->storeFileNamesIn('nome_original')
                        ->directory('pedidos/conclusao')
                        ->disk('public')
                        ->storeFiles()
                        ->visibility('public')
                        ->columnSpanFull(),

                ])
                ->visible(fn (Get $get) => ! (bool) $get('reabrir_pedido')),
        ];
    }

    private static function avaliacoesPorProblemaSchema(Pedido $record, PedidoService $service): array
    {
        return $service->problemasParaAvaliacao($record)
            ->map(function ($problema) {
                $pedido = $problema->pedido;
                $protocolo = $pedido?->numero_protocolo ?: 'Pedido';
                $tipo = $pedido?->tipoManutencao?->nome ?: 'Tipo não informado';

                return Section::make("{$protocolo} - {$problema->texto_problema}")
                    ->description($tipo)
                    ->schema([
                        SliderRating::make("avaliacoes.{$problema->id}.valor")
                            ->label('Nota (1 a 5)')
                            ->required()
                            ->columnSpan(1),

                        Select::make("avaliacoes.{$problema->id}.resultado")
                            ->label('Resultado')
                            ->options(
                                collect(ResultadoFeedbackPedido::cases())
                                    ->mapWithKeys(fn (ResultadoFeedbackPedido $resultado) => [$resultado->value => $resultado->label()])
                                    ->toArray()
                            )
                            ->default(ResultadoFeedbackPedido::Atendido->value)
                            ->required()
                            ->native(false)
                            ->columnSpan(1),

                        Textarea::make("avaliacoes.{$problema->id}.comentario")
                            ->label('Comentário do problema')
                            ->required()
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get) => ! (bool) $get('reabrir_pedido'));
            })
            ->all();
    }

    private static function pedidosAdicionaisRepeater(bool $required): Repeater
    {
        return Repeater::make('pedidos_adicionais')
            ->label('Pedidos adicionais')
            ->addActionLabel('Adicionar serviço realizado')
            ->minItems($required ? 1 : 0)
            ->schema([
                Select::make('tipo_manutencao_id')
                    ->label('Tipo de manutenção')
                    ->options(fn () => TipoManutencao::query()
                        ->where('ativo', true)
                        ->orderBy('nome')
                        ->pluck('nome', 'id')
                        ->toArray())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('tipo_manutencao_opcao_ids', []))
                    ->required(),

                Select::make('tipo_manutencao_opcao_ids')
                    ->label('Problemas atendidos')
                    ->multiple()
                    ->options(fn (Get $get) => filled($get('tipo_manutencao_id'))
                        ? TipoManutencaoOpcao::query()
                            ->where('tipo_manutencao_id', $get('tipo_manutencao_id'))
                            ->where('ativo', true)
                            ->orderBy('texto')
                            ->pluck('texto', 'id')
                            ->toArray()
                        : [])
                    ->searchable()
                    ->preload()
                    ->required(),

                DatePicker::make('data_identificacao_problema')
                    ->label('Identificado em')
                    ->maxDate(now())
                    ->default(now())
                    ->required(),

                Textarea::make('descricao_pedido')
                    ->label('Descrição rápida')
                    ->rows(3)
                    ->required()
                    ->maxLength(1000)
                    ->columnSpanFull(),

                FileUpload::make('arquivos')
                    ->label('Fotos do pedido adicional')
                    ->acceptedFileTypes(PedidoImageUpload::MIME_TYPES)
                    ->validationMessages([
                        'mimetypes' => PedidoImageUpload::MESSAGE,
                    ])
                    ->helperText(PedidoImageUpload::MESSAGE)
                    ->multiple()
                    ->required()
                    ->minFiles(1)
                    ->maxFiles(10)
                    ->storeFileNamesIn('nome_original')
                    ->directory('pedidos/adicionais')
                    ->disk('public')
                    ->storeFiles()
                    ->visibility('public')
                    ->columnSpanFull(),

            ])
            ->columns(2)
            ->defaultItems($required ? 1 : 0)
            ->columnSpanFull();
    }

    private static function statusEh(Pedido $record, string $nome): bool
    {
        $status = app(PedidoService::class)->statusPorNome($nome);

        return $status && (int) $record->tipo_status_id === (int) $status->id;
    }

    private static function statusListagem(Pedido $record): string
    {
        if (static::pedidoEncaminhadoParaSetor($record)) {
            return 'Encaminhado ao Setor - '.$record->setor->nome_completo;
        }

        return $record->tipoStatus?->nome ?? 'Sem status';
    }

    private static function corStatusListagem(Pedido $record): string
    {
        if (static::pedidoEncaminhadoParaSetor($record)) {
            static $corEncaminhado = null;

            $corEncaminhado ??= app(PedidoService::class)->statusPorNome('Encaminhado ao Setor')?->cor ?? '#8b5cf6';

            return $corEncaminhado;
        }

        return $record->tipoStatus?->cor ?? '#6b7280';
    }

    private static function pedidoEncaminhadoParaSetor(Pedido $record): bool
    {
        if (! filled($record->setor_id) || $record->setor === null || $record->setor->ehSetorGeral()) {
            return false;
        }

        return in_array($record->tipoStatus?->nome, ['Encaminhado ao Setor', 'Em Aberto'], true);
    }

    private static function rotuloAcessoOperacional(Pedido $record, ?User $user): string
    {
        if ($record->tipoStatus?->finaliza_pedido || $record->tipoStatus?->cancela_pedido) {
            return 'Encerrado';
        }

        $service = app(PedidoService::class);

        if ($service->podeGerenciarRegistro($record, $user)) {
            return 'Gerenciável';
        }

        if (
            $service->podeVincularAdicionaisAoPedido($record, $user)
            || $service->podeAvaliarRegistro($record, $user)
        ) {
            return 'Ações da escola';
        }

        return 'Somente leitura';
    }

    private static function corAcessoOperacional(Pedido $record, ?User $user): string
    {
        return match (static::rotuloAcessoOperacional($record, $user)) {
            'Gerenciável' => 'success',
            'Ações da escola' => 'info',
            'Encerrado' => 'gray',
            default => 'warning',
        };
    }

    private static function iconeAcessoOperacional(Pedido $record, ?User $user): string
    {
        return match (static::rotuloAcessoOperacional($record, $user)) {
            'Gerenciável' => 'heroicon-o-pencil-square',
            'Ações da escola' => 'heroicon-o-building-office-2',
            'Encerrado' => 'heroicon-o-lock-closed',
            default => 'heroicon-o-eye',
        };
    }

    private static function nomeEscolaDoPedido(Pedido $record): ?string
    {
        if (filled($record->escola?->nome)) {
            return $record->escola->nome;
        }

        if (filled($record->solicitante?->escola?->nome)) {
            return $record->solicitante->escola->nome;
        }

        $escolas = $record->solicitante?->escolas;

        return $escolas?->count() === 1 ? $escolas->first()?->nome : null;
    }
}
