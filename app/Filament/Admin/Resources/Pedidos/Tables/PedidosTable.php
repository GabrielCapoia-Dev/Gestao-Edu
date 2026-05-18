<?php

namespace App\Filament\Admin\Resources\Pedidos\Tables;

use App\Filament\Admin\Components\SliderRating;
use App\Filament\Admin\Resources\Pedidos\Tables\Actions\ExportarRelatorioAction;
use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\Enums\ResultadoFeedbackPedido;
use App\Models\Pedido;
use App\Models\TipoManutencao;
use App\Models\TipoManutencaoOpcao;
use App\Models\User;
use App\Services\PedidoService;
use App\Services\UserSetorAccessService;
use App\Support\PedidoFotoUpload;
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
use Filament\Schemas\Components\View;
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
                ->with(['problemas', 'pedidoPrincipal'])
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
            SelectFilter::make('tipo_registro')
                ->label('Tipo de registro')
                ->columnSpan(3)
                ->default('principais')
                ->options([
                    'principais' => 'Pedidos principais',
                    'adicionais' => 'Pedidos adicionais',
                    'todos' => 'Todos',
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return match ($data['value'] ?? 'principais') {
                        'adicionais' => $query->where('is_pedido_adicional', true),
                        'todos' => $query,
                        default => $query->where('is_pedido_adicional', false),
                    };
                }),

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

            SelectFilter::make('tipo_manutencao_id')
                ->label('Tipo')
                ->columnSpan(3)
                ->relationship('tipoManutencao', 'nome')
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
                        ->sortable(),

                    TextColumn::make('problemas_resumo')
                        ->label('Problemas')
                        ->state(fn (Pedido $record): string => $record->problemas
                            ->pluck('texto_problema')
                            ->take(3)
                            ->join(' | '))
                        ->limit(90)
                        ->wrap()
                        ->color('gray')
                        ->size('sm')
                        ->placeholder('Sem problemas segmentados'),
                ])->space(1),

                Stack::make([
                    TextColumn::make('escola.nome')
                        ->label('Escola')
                        ->icon('heroicon-o-building-office-2')
                        ->alignCenter()
                        ->sortable(),

                    TextColumn::make('nome_solicitante')
                        ->label('Solicitante')
                        ->icon('heroicon-o-user')
                        ->alignCenter()
                        ->color('gray')
                        ->size('sm'),
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

                    TextColumn::make('responsavel.name')
                        ->label('Responsável')
                        ->alignCenter()
                        ->icon('heroicon-o-user-circle')
                        ->color('gray')
                        ->size('sm')
                        ->placeholder('Sem responsável'),
                ])->space(1),

                Stack::make([
                    TextColumn::make('data_solicitacao')
                        ->label('Solicitado em')
                        ->icon('heroicon-o-calendar')
                        ->date('d/m/Y')
                        ->alignCenter()
                        ->sortable(),

                    TextColumn::make('data_identificacao_problema')
                        ->label('Identificado em')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->date('d/m/Y')
                        ->alignCenter()
                        ->sortable()
                        ->color('warning')
                        ->placeholder('Não informado'),

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
                ])->space(1),

                Stack::make([
                    TextColumn::make('descricao_pedido')
                        ->label('Descrição')
                        ->limit(70)
                        ->wrap()
                        ->color('gray')
                        ->size('sm'),

                    TextColumn::make('pedidos_adicionais_count')
                        ->label('Adicionais')
                        ->badge()
                        ->color('info')
                        ->state(fn (Pedido $record): int => (int) ($record->pedidos_adicionais_count ?? 0)),

                    TextColumn::make('ultimoHistorico.descricao_alteracao')
                        ->label('Última Alteração')
                        ->limit(60)
                        ->wrap()
                        ->color('primary')
                        ->size('sm')
                        ->placeholder('Sem alterações'),
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
            Action::make('historico')
                ->label('Histórico')
                ->icon('heroicon-o-clock')
                ->color('info')
                ->slideOver()
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->visible(fn () => $user?->hasPermissionTo('Visualizar Histórico de Pedidos') ?? false)
                ->modalContent(function (Pedido $record) {
                    $historico = $record->historicos()
                        ->with(['statusAnterior', 'statusNovo', 'usuario', 'setor'])
                        ->orderByDesc('created_at')
                        ->get();

                    return view('components.pedido.historico', [
                        'pedido' => $record,
                        'historico' => $historico,
                    ]);
                }),

            Action::make('adicionais')
                ->label('Adicionais')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->slideOver()
                ->modalWidth('4xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->visible(fn (Pedido $record) => ! $record->is_pedido_adicional && $record->pedidosAdicionais()->exists())
                ->modalContent(fn (Pedido $record) => view('components.pedido.pedidos-adicionais', [
                    'pedido' => $record,
                    'adicionais' => $record->pedidosAdicionais()
                        ->with(['tipoManutencao', 'problemas', 'tipoStatus'])
                        ->get(),
                ])),

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
                        ->title($criados->count() . ' pedido(s) adicional(is) vinculado(s).')
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
                ->modalDescription('Antes de seguir para avaliação e conclusão do pedido, confirme se deseja adicionar serviços extras realizados nesta solicitação.')
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
                        ->title($cancelados . ' pedido(s) cancelado(s).')
                        ->warning()
                        ->send();
                }),
        ];
    }

    private static function avaliacaoSchema(Pedido $record, PedidoService $service): array
    {
        return [
            Section::make('Serviços adicionais')
                ->schema([
                    Toggle::make('adicionar_adicionais')
                        ->label('Adicionar novos pedidos a esta solicitação antes de avaliar')
                        ->helperText('Use quando o prestador realizou serviços além do que estava descrito no pedido original.')
                        ->live(),

                    static::pedidosAdicionaisRepeater(false)
                        ->visible(fn (Get $get) => (bool) $get('adicionar_adicionais')),
                ]),

            Section::make('Avaliação geral')
                ->schema([
                    Toggle::make('reabrir_pedido')
                        ->label('Reabrir pedido')
                        ->helperText('A nota 1 não reabre automaticamente. Use este botão quando o pedido precisar voltar para execução.')
                        ->inline(false),

                    Textarea::make('descricao')
                        ->label('Comentário geral')
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            ...static::avaliacoesPorProblemaSchema($record, $service),

            Section::make('Fotos da Conclusão')
                ->schema([
                    PedidoFotoUpload::configure(
                        FileUpload::make('fotos_conclusao')
                            ->label('Adicionar Fotos')
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

                    View::make('components.forms.camera-file-upload-tools')
                        ->viewData([
                            'target' => 'pedido-fotos-conclusao',
                        ])
                        ->columnSpanFull(),
                ]),
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
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2);
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

                SliderRating::make('valor')
                    ->label('Nota do adicional')
                    ->default(5)
                    ->required(),

                Select::make('resultado')
                    ->label('Resultado do adicional')
                    ->options(
                        collect(ResultadoFeedbackPedido::cases())
                            ->mapWithKeys(fn (ResultadoFeedbackPedido $resultado) => [$resultado->value => $resultado->label()])
                            ->toArray()
                    )
                    ->default(ResultadoFeedbackPedido::Atendido->value)
                    ->required()
                    ->native(false),

                Textarea::make('comentario')
                    ->label('Comentário do adicional')
                    ->maxLength(1000)
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
