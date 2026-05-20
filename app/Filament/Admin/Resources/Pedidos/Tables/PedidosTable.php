<?php

namespace App\Filament\Admin\Resources\Pedidos\Tables;

use App\Filament\Admin\Actions\VincularSetorBulkAction;
use App\Filament\Admin\Components\PedidoImageUpload;
use App\Filament\Admin\Components\SliderRating;
use App\Filament\Admin\Resources\Pedidos\Tables\Actions\ExportarRelatorioAction;
use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\Enums\ResultadoFeedbackPedido;
use App\Models\Pedido;
use App\Models\Setor;
use App\Models\TipoManutencao;
use App\Models\TipoManutencaoOpcao;
use App\Models\User;
use App\Services\PedidoService;
use App\Services\UserSetorAccessService;
use App\Support\PedidoFotoUpload;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;

class PedidosTable
{
    public static function configure(Table $table, ?User $user = null): Table
    {
        $service = app(PedidoService::class);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->where('is_pedido_adicional', false)
                ->with(['pedidoPrincipal'])
                ->withCount(['pedidosAdicionais', 'problemas']))
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->defaultSort('updated_at', 'desc')
            ->striped()
            ->columns(static::columns($user))
            ->filters(static::filters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(12)
            ->recordActions(static::actions($user, $service))
            ->groupedBulkActions(static::bulkActions($user, $service))
            ->headerActions(static::headerActions($user));
    }

    public static function headerActions(?User $user): array
    {
        return [
            ExportarRelatorioAction::make(),
        ];
    }

    public static function filters(): array
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
                ->options(fn () => app(UserSetorAccessService::class)->optionsForSelect(auth()->user()))
                ->searchable()
                ->preload(),

            SelectFilter::make('escola_id')
                ->label('Escola')
                ->columnSpan(3)
                ->relationship('escola', 'nome')
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

            Filter::make('manutencao')
                ->label('Manutencao')
                ->columnSpan(6)
                ->columns(2)
                ->schema([
                    Select::make('tipo_manutencao_nome')
                        ->label('Tipo de manutencao')
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
                        ->label('Opcao do tipo')
                        ->placeholder('Todas as opcoes')
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
        ];
    }

    public static function columns(?User $user): array
    {
        return [
            Split::make([
                Stack::make([
                    TextColumn::make('numero_protocolo')
                        ->label('Protocolo')
                        ->searchable()
                        ->sortable()
                        ->weight('bold'),

                    TextColumn::make('pedidoPrincipal.numero_protocolo')
                        ->label('Pedido original')
                        ->badge()
                        ->color('gray')
                        ->placeholder('Pedido principal'),

                    TextColumn::make('tipoManutencao.nome')
                        ->label('Tipo')
                        ->sortable()
                        ->color('gray'),
                ])->space(1),

                Stack::make([
                    TextColumn::make('escola.nome')
                        ->label('Escola')
                        ->icon('heroicon-o-building-office-2')
                        ->alignCenter()
                        ->sortable(),

                    TextColumn::make('pedidos_adicionais_count')
                        ->label('Adicionais')
                        ->badge()
                        ->alignCenter()
                        ->color(fn (Pedido $record): string => (int) ($record->pedidos_adicionais_count ?? 0) > 0 ? 'info' : 'gray')
                        ->state(fn (Pedido $record): string => (int) ($record->pedidos_adicionais_count ?? 0).' adicional(is)'),
                ])->space(1),

                Stack::make([
                    TextColumn::make('tipoStatus.nome')
                        ->alignCenter()
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(function (Pedido $record) {
                            $status = $record->tipoStatus?->nome ?? 'Sem status';
                            $setor = $record->setor?->nome_completo;

                            return $setor ? "{$status} - {$setor}" : $status;
                        })
                        ->color(fn (Pedido $record) => Color::hex($record->tipoStatus?->cor ?? '#6b7280')),

                    TextColumn::make('nivel_prioridade')
                        ->label('Prioridade')
                        ->alignCenter()
                        ->badge()
                        ->color(fn (Pedido $record) => Color::hex(
                            match ($record->nivel_prioridade?->value) {
                                'Emergencial' => '#a10000',
                                'Corretivo' => '#973f00',
                                'Preventivo' => '#013891',
                                default => '#2b2b2b',
                            }
                        )),

                ])->space(1),

                Stack::make([
                    TextColumn::make('data_prevista')
                        ->label('Previsto para')
                        ->icon('heroicon-o-clock')
                        ->sortable()
                        ->alignCenter()
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
                        ->placeholder('Sem previsão'),

                    TextColumn::make('data_entrega')
                        ->label('Concluido em')
                        ->icon('heroicon-o-check-circle')
                        ->sortable()
                        ->alignCenter()
                        ->date('d/m/Y')
                        ->color('success')
                        ->size('sm')
                        ->placeholder('Em andamento'),
                ])->space(1),

                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description('Atualizado em:', position: 'above')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
            ]),
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
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->visible(fn () => $user?->hasPermissionTo('Visualizar Histórico de Pedidos') ?? false)
                ->modalContent(function (Pedido $record) {
                    $record->load([
                        'tipoManutencao',
                        'tipoStatus',
                        'escola',
                        'setor',
                        'empresaContratada',
                        'solicitante',
                        'responsavel',
                        'problemas',
                        'arquivos.usuario',
                        'ultimoFeedback.itens.problema',
                    ]);

                    $historico = $record->historicos()
                        ->with(['statusAnterior', 'statusNovo', 'usuario', 'setor'])
                        ->orderByDesc('created_at')
                        ->orderByRaw('CASE WHEN status_anterior_id IS NOT NULL THEN 1 ELSE 0 END DESC')
                        ->get();

                    $adicionais = $record->pedidosAdicionais()
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
                    ]);
                }),

            Action::make('vincular_adicionais')
                ->label('Vincular Adicionais')
                ->icon('heroicon-o-plus-circle')
                ->color('info')
                ->visible(fn (Pedido $record) => static::statusEh($record, 'Em Manutenção')
                    && ! $record->is_pedido_adicional
                    && $service->podeVincularAdicionais($user))
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
                    && ($user?->hasPermissionTo('Avaliar Pedidos') ?? false))
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
                ->visible(fn (Pedido $record) => $service->podeGerenciarRegistro($record, $user))
                ->action(function (Pedido $record) use ($user, $service) {
                    $service->assumirPedido($record, $user);

                    redirect(route('filament.admin.resources.pedidos.edit', $record));
                })
                ->openUrlInNewTab(false),
        ];
    }

    public static function bulkActions(?User $user, PedidoService $service): array
    {
        return [
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
            ),

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
                ->visible(fn () => $user?->hasPermissionTo('Editar Pedidos') ?? false)
                ->action(function (EloquentCollection $records, array $data) use ($user, $service) {
                    $statusCancelado = $service->statusPorNome('Cancelado', true);
                    $cancelados = 0;

                    foreach ($records as $record) {
                        if (! $record instanceof Pedido || ! $service->podeGerenciarRegistro($record, $user)) {
                            continue;
                        }

                        $service->alterarStatus(
                            $record,
                            $statusCancelado,
                            $user,
                            $data['descricao'] ?? 'Cancelado em massa.'
                        );

                        $cancelados++;
                    }

                    Notification::make()
                        ->title($cancelados.' pedido(s) cancelado(s).')
                        ->warning()
                        ->send();
                }),
        ];
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
                    PedidoFotoUpload::configure(
                        PedidoImageUpload::make('fotos_conclusao')
                            ->label('Fotos da conclusão')
                            ->multiple()
                            ->maxFiles(10)
                            ->storeFileNamesIn('nome_original')
                            ->directory('pedidos/conclusao')
                            ->disk('public')
                            ->visibility('public'),
                        'pedido-fotos-conclusao'
                    )
                        ->helperText('Formatos aceitos: JPG, PNG, WebP. Arquivos .jfif não são suportados.')
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

                PedidoFotoUpload::configure(
                    PedidoImageUpload::make('arquivos')
                        ->label('Fotos do pedido adicional')
                        ->multiple()
                        ->required()
                        ->minFiles(1)
                        ->maxFiles(10)
                        ->storeFileNamesIn('nome_original')
                        ->directory('pedidos/adicionais')
                        ->disk('public')
                        ->visibility('public'),
                    'pedido-fotos-adicional'
                )
                    ->helperText('Obrigatório: envie de 1 a 10 imagens nos formatos JPEG, JPG, PNG ou WEBP, com até 5 MB cada.')
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
}
