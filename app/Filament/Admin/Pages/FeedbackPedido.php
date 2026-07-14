<?php

namespace App\Filament\Admin\Pages;

use App\Models\Enums\NivelEmergenciaPedido;
use App\Models\FeedbackPedido as FeedbackPedidoModel;
use App\Models\User;
use App\Services\Exports\ExportRequestService;
use App\Services\Relatorios\FeedbackPedidoAnalyticsService;
use BackedEnum;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Throwable;
use UnitEnum;

class FeedbackPedido extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'feedback-pedidos';

    protected string $view = 'filament.pages.feedback-pedido';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Star;

    protected static string|UnitEnum|null $navigationGroup = 'Manutenção';

    protected static ?string $navigationParentItem = 'Pedidos';

    public static ?string $navigationLabel = 'Feedback de Pedidos';

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Manutenção',
            'title' => 'Feedback de Pedidos',
            'description' => 'Acompanhe satisfação das escolas, desempenho das empresas e relatórios de feedback em fila.',
        ]);
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', FeedbackPedidoModel::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->analytics()->baseQuery())
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->columns([
                Tables\Columns\TextColumn::make('pedido.numero_protocolo')
                    ->label('Protocolo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('pedido.escola.nome')
                    ->label('Escola')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('pedido.empresaContratada.nome')
                    ->label('Empresa')
                    ->placeholder('Sem empresa')
                    ->toggleable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('pedido.tipoManutencao.nome')
                    ->label('Tipo')
                    ->toggleable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('valor')
                    ->label('Nota')
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        (int) $state >= 4 => 'success',
                        (int) $state >= 3 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn ($state): string => "{$state}/5")
                    ->sortable(),

                Tables\Columns\TextColumn::make('itens_resumo')
                    ->label('Por problema')
                    ->state(fn (FeedbackPedidoModel $record): string => $record->itens
                        ->map(fn ($item): string => ($item->problema?->texto_problema ?? 'Problema') . ': ' . $item->valor . '/5')
                        ->take(3)
                        ->join(' | '))
                    ->limit(90)
                    ->wrap()
                    ->placeholder('Sem itens'),

                ViewColumn::make('comentario_feedback')
                    ->label('Comentário')
                    ->view('filament.tables.columns.feedback-pedido-comment'),

                Tables\Columns\TextColumn::make('pedido_reaberto')
                    ->label('Reaberto')
                    ->badge()
                    ->state(fn (FeedbackPedidoModel $record): bool => $this->analytics()->feedbackPossuiHistoricoReaberto($record))
                    ->formatStateUsing(fn ($state): string => $state ? 'Sim' : 'Não')
                    ->color(fn ($state): string => $state ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Avaliado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('visualizar_pedido')
                    ->label('Visualizar pedido')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->slideOver()
                    ->modalWidth('7xl')
                    ->extraModalWindowAttributes(['class' => 'pedido-view-modal-window'], merge: true)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalHeading(fn (FeedbackPedidoModel $record): string => 'Pedido '.$record->pedido?->numero_protocolo)
                    ->visible(fn (FeedbackPedidoModel $record): bool => $record->pedido !== null)
                    ->modalContent(fn (FeedbackPedidoModel $record): \Illuminate\Contracts\View\View => $this->pedidoModalContent($record)),
            ])
            ->filters($this->tableFilters(), layout: Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(12)
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<int, mixed>
     */
    public function tableFilters(): array
    {
        return [
            Tables\Filters\Filter::make('periodo')
                ->label('Período do pedido')
                ->visible(fn (): bool => filled($this->firstPedidoDate()))
                ->columnSpan(4)
                ->columns(2)
                ->schema([
                    Forms\Components\DatePicker::make('data_inicio')
                        ->label('Início')
                        ->minDate(fn (): ?string => $this->firstPedidoDate())
                        ->maxDate(now()->toDateString()),
                    Forms\Components\DatePicker::make('data_fim')
                        ->label('Fim')
                        ->minDate(fn (): ?string => $this->firstPedidoDate())
                        ->maxDate(now()->toDateString()),
                ])
                ->query(fn (Builder $query, array $data): Builder => $this->analytics()->applyFilters($query, $data)),

            Tables\Filters\SelectFilter::make('valor')
                ->label('Nota')
                ->columnSpan(2)
                ->visible(fn (): bool => $this->notaOptions() !== [])
                ->options(fn (): array => $this->notaOptions()),

            Tables\Filters\SelectFilter::make('nivel_prioridade')
                ->label('Prioridade')
                ->columnSpan(2)
                ->visible(fn (): bool => $this->prioridadeOptions() !== [])
                ->options(fn (): array => $this->prioridadeOptions())
                ->query(fn (Builder $query, array $data): Builder => $this->analytics()->applyFilters($query, [
                    'nivel_prioridade' => $data['value'] ?? null,
                ])),

            Tables\Filters\Filter::make('manutencao')
                ->label('Manutenção')
                ->visible(fn (): bool => $this->tipoManutencaoOptions() !== [])
                ->columnSpan(4)
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('tipo_manutencao_id')
                        ->label('Tipo')
                        ->placeholder('Todos os tipos')
                        ->options(fn (): array => $this->tipoManutencaoOptions())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(fn (callable $set): mixed => $set('tipo_manutencao_opcao_id', null)),

                    Forms\Components\Select::make('tipo_manutencao_opcao_id')
                        ->label('Opção do tipo')
                        ->placeholder('Todas as opções')
                        ->options(fn (Get $get): array => $this->tipoManutencaoOpcaoOptions($get('tipo_manutencao_id')))
                        ->visible(fn (Get $get): bool => filled($get('tipo_manutencao_id')) && $this->tipoManutencaoOpcaoOptions($get('tipo_manutencao_id')) !== [])
                        ->searchable()
                        ->preload(),
                ])
                ->query(fn (Builder $query, array $data): Builder => $this->analytics()->applyFilters($query, $data)),

            Tables\Filters\SelectFilter::make('escola_id')
                ->label('Escola')
                ->columnSpan(2)
                ->visible(fn (): bool => $this->escolaOptions() !== [])
                ->options(fn (): array => $this->escolaOptions())
                ->searchable()
                ->preload()
                ->query(fn (Builder $query, array $data): Builder => $this->analytics()->applyFilters($query, [
                    'escola_id' => $data['value'] ?? null,
                ])),

            Tables\Filters\SelectFilter::make('empresa_contratada_id')
                ->label('Empresa')
                ->columnSpan(2)
                ->visible(fn (): bool => $this->empresaOptions() !== [])
                ->options(fn (): array => $this->empresaOptions())
                ->searchable()
                ->preload()
                ->query(fn (Builder $query, array $data): Builder => $this->analytics()->applyFilters($query, [
                    'empresa_contratada_id' => $data['value'] ?? null,
                ])),

            Tables\Filters\SelectFilter::make('resultado')
                ->label('Resultado')
                ->columnSpan(2)
                ->visible(fn (): bool => $this->resultadoOptions() !== [])
                ->options(fn (): array => $this->resultadoOptions())
                ->query(fn (Builder $query, array $data): Builder => $this->analytics()->applyFilters($query, [
                    'resultado' => $data['value'] ?? null,
                ])),

            Tables\Filters\SelectFilter::make('reabrir_pedido')
                ->label('Reaberto')
                ->columnSpan(2)
                ->visible(fn (): bool => $this->reabertoOptions() !== [])
                ->options(fn (): array => $this->reabertoOptions())
                ->query(fn (Builder $query, array $data): Builder => $this->analytics()->applyFilters($query, [
                    'reabrir_pedido' => $data['value'] ?? null,
                ])),
        ];
    }

    /**
     * @return array{total:int,media:float,satisfacao:int,reabertos:int,criticas:int}
     */
    public function getMetricas(): array
    {
        return $this->analytics()->metrics(clone $this->getFilteredTableQuery());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRankingEmpresas(): array
    {
        return $this->analytics()->rankingEmpresas($this->feedbacksFiltrados());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRankingEscolas(): array
    {
        return $this->analytics()->rankingEscolas($this->feedbacksFiltrados());
    }

    /**
     * @return array<int, int>
     */
    public function getDistribuicaoNotas(): array
    {
        return $this->analytics()->noteDistribution(clone $this->getFilteredTableQuery());
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportar_feedback')
                ->label('Gerar relatório')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => Gate::allows('exportReports'))
                ->modalHeading('Gerar relatório de feedback')
                ->modalDescription('Informe obrigatoriamente o período do pedido. O PDF será enviado para Minhas Exportacoes.')
                ->form($this->exportForm())
                ->action(function (array $data): void {
                    $this->queueExport($data);
                }),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function exportForm(): array
    {
        return [
            Forms\Components\Select::make('report_type')
                ->label('Tipo de relatório')
                ->options($this->analytics()->reportTypeOptions())
                ->default(FeedbackPedidoAnalyticsService::REPORT_GERAL)
                ->required()
                ->native(false),

            Forms\Components\DatePicker::make('data_inicio')
                ->label('Data de início')
                ->minDate(fn (): ?string => $this->firstPedidoDate())
                ->maxDate(now()->toDateString())
                ->required(),

            Forms\Components\DatePicker::make('data_fim')
                ->label('Data de fim')
                ->minDate(fn (): ?string => $this->firstPedidoDate())
                ->maxDate(now()->toDateString())
                ->default(now()->toDateString())
                ->required(),

            Forms\Components\Select::make('valor')
                ->label('Nota')
                ->visible(fn (): bool => $this->notaOptions() !== [])
                ->options(fn (): array => $this->notaOptions())
                ->native(false)
                ->nullable(),

            Forms\Components\Select::make('nivel_prioridade')
                ->label('Prioridade')
                ->visible(fn (): bool => $this->prioridadeOptions() !== [])
                ->options(fn (): array => $this->prioridadeOptions())
                ->native(false)
                ->nullable(),

            Forms\Components\Select::make('tipo_manutencao_id')
                ->label('Tipo de manutenção')
                ->visible(fn (): bool => $this->tipoManutencaoOptions() !== [])
                ->options(fn (): array => $this->tipoManutencaoOptions())
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(fn (callable $set): mixed => $set('tipo_manutencao_opcao_id', null))
                ->nullable(),

            Forms\Components\Select::make('tipo_manutencao_opcao_id')
                ->label('Opção do tipo')
                ->options(fn (Get $get): array => $this->tipoManutencaoOpcaoOptions($get('tipo_manutencao_id')))
                ->visible(fn (Get $get): bool => filled($get('tipo_manutencao_id')) && $this->tipoManutencaoOpcaoOptions($get('tipo_manutencao_id')) !== [])
                ->searchable()
                ->preload()
                ->nullable(),

            Forms\Components\Select::make('escola_id')
                ->label('Escola')
                ->visible(fn (): bool => $this->escolaOptions() !== [])
                ->options(fn (): array => $this->escolaOptions())
                ->searchable()
                ->preload()
                ->nullable(),

            Forms\Components\Select::make('empresa_contratada_id')
                ->label('Empresa contratada')
                ->visible(fn (): bool => $this->empresaOptions() !== [])
                ->options(fn (): array => $this->empresaOptions())
                ->searchable()
                ->preload()
                ->nullable(),

            Forms\Components\Select::make('resultado')
                ->label('Resultado por problema')
                ->visible(fn (): bool => $this->resultadoOptions() !== [])
                ->options(fn (): array => $this->resultadoOptions())
                ->native(false)
                ->nullable(),

            Forms\Components\Select::make('reabrir_pedido')
                ->label('Reaberto')
                ->visible(fn (): bool => $this->reabertoOptions() !== [])
                ->options(fn (): array => $this->reabertoOptions())
                ->native(false)
                ->nullable(),
        ];
    }

    private function queueExport(array $data): void
    {
        try {
            $filters = $this->analytics()->normalizeFilters($data);
            $this->analytics()->assertReportFilters($filters);

            $exportRequest = app(ExportRequestService::class)->queue(
                user: Auth::user(),
                type: 'feedback_pedido_relatorio',
                format: 'pdf',
                filters: $filters,
                label: 'Feedback - ' . $this->analytics()->reportTypeLabel($filters['report_type'] ?? null),
                metadata: ['route' => 'filament.admin.pages.feedback-pedidos'],
            );

            Notification::make()
                ->title($exportRequest->wasRecentlyCreated ? 'Exportação enviada para a fila' : 'Exportação já está em andamento')
                ->body('Acompanhe o progresso em Minhas Exportacoes.')
                ->success()
                ->send();

            $this->redirect(url()->previous());
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Não foi possível iniciar a exportação')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    private function pedidoModalContent(FeedbackPedidoModel $record): \Illuminate\Contracts\View\View
    {
        $pedido = $record->pedido;

        abort_unless($pedido, 404);

        $pedido->load([
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

        $pedidoOriginal = $pedido->is_pedido_adicional ? $pedido->pedidoPrincipal : null;

        $historico = $pedido->historicos()
            ->with(['statusAnterior', 'statusNovo', 'usuario', 'setor'])
            ->orderByDesc('created_at')
            ->orderByRaw('CASE WHEN status_anterior_id IS NOT NULL THEN 1 ELSE 0 END DESC')
            ->get();

        $adicionais = $pedido->is_pedido_adicional
            ? collect()
            : $pedido->pedidosAdicionais()
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
            'pedido' => $pedido,
            'historico' => $historico,
            'adicionais' => $adicionais,
            'pedidoOriginal' => $pedidoOriginal,
            'usuario' => auth()->user(),
        ]);
    }

    private function analytics(): FeedbackPedidoAnalyticsService
    {
        return app(FeedbackPedidoAnalyticsService::class);
    }

    private function feedbacksFiltrados(): \Illuminate\Database\Eloquent\Collection
    {
        return (clone $this->getFilteredTableQuery())
            ->with(['pedido.escola', 'pedido.empresaContratada'])
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();
    }

    /**
     * @return array<string, string>
     */
    private function notaOptions(): array
    {
        return $this->analytics()->noteOptions();
    }

    /**
     * @return array<string, string>
     */
    private function prioridadeOptions(): array
    {
        $labels = collect(NivelEmergenciaPedido::cases())
            ->mapWithKeys(fn ($case): array => [$case->value => $case->label()]);

        return collect($this->analytics()->priorityOptions())
            ->mapWithKeys(fn (string $value, string $key): array => [$key => $labels[$key] ?? $value])
            ->toArray();
    }

    /**
     * @return array<int, string>
     */
    private function tipoManutencaoOptions(): array
    {
        return $this->analytics()->tipoManutencaoOptions();
    }

    /**
     * @return array<int, string>
     */
    private function tipoManutencaoOpcaoOptions(null|int|string $tipoManutencaoId = null): array
    {
        return $this->analytics()->tipoManutencaoOpcaoOptions($tipoManutencaoId);
    }

    /**
     * @return array<int, string>
     */
    private function escolaOptions(): array
    {
        return $this->analytics()->escolaOptions();
    }

    /**
     * @return array<int, string>
     */
    private function empresaOptions(): array
    {
        return $this->analytics()->empresaOptions();
    }

    /**
     * @return array<string, string>
     */
    private function resultadoOptions(): array
    {
        return $this->analytics()->resultadoOptions();
    }

    /**
     * @return array<string, string>
     */
    private function reabertoOptions(): array
    {
        return $this->analytics()->reabertoOptions();
    }

    private function firstPedidoDate(): ?string
    {
        return $this->analytics()->firstPedidoDate();
    }

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }
}
