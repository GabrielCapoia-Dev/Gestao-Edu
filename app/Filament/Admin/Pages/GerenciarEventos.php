<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Pages\Actions\EventoCalendarioCreateAction;
use App\Filament\Admin\Pages\Schemas\EventoCalendarioForm;
use App\Models\Enums\EventoCalendarioStatus;
use App\Models\Enums\ListaPermissoes;
use App\Models\EventoCalendario;
use App\Models\ImportacaoEventoCalendario;
use App\Models\User;
use App\Livewire\Transporte\MotoristasTransporteTable;
use App\Livewire\Transporte\VeiculosTransporteTable;
use App\Services\Dashboard\EventoCalendarioListQueryService;
use App\Services\Dashboard\EventoCalendarioAccessService;
use App\Services\Dashboard\EventoCalendarioService;
use App\Services\Dashboard\EventoCalendarioRelatorioService;
use App\Services\Dashboard\EventoCalendarioWorkflowService;
use App\Services\ProfilePreviewService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class GerenciarEventos extends Page implements HasTable
{
    use InteractsWithTable;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'eventos-calendario';

    protected static ?string $title = 'Eventos da agenda';

    protected string $view = 'filament.admin.pages.gerenciar-eventos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    public static function canAccess(): bool
    {
        $user = static::usuarioEfetivo();

        return $user !== null
            && Gate::forUser($user)->allows('viewAny', EventoCalendario::class);
    }

    public function getHeader(): ?View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Início',
            'title' => 'Eventos da agenda',
            'description' => 'Acompanhe solicitações, publicações e eventos distribuídos para as escolas.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        $user = static::usuarioEfetivo();

        return [
            Action::make('gerenciarTransporte')
                ->label('Gerenciar transporte')
                ->icon('heroicon-o-truck')
                ->color('gray')
                ->visible(fn (): bool => $user !== null
                    && Gate::forUser($user)->allows('manageTransport', EventoCalendario::class))
                ->authorize(fn (): bool => $user !== null
                    && Gate::forUser($user)->allows('manageTransport', EventoCalendario::class))
                ->modalHeading('Gerenciar transporte')
                ->modalDescription('Cadastre e mantenha os motoristas e veículos disponíveis para os eventos.')
                ->modalIcon('heroicon-o-truck')
                ->slideOver()
                ->modalWidth('6xl')
                ->stickyModalHeader()
                ->closeModalByClickingAway(false)
                ->formWrapper(false)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->schema([
                    Tabs::make('Cadastros de transporte')
                        ->contained(false)
                        ->tabs([
                            Tab::make('Motoristas')
                                ->icon('heroicon-o-identification')
                                ->schema([
                                    LivewireComponent::make(MotoristasTransporteTable::class)
                                        ->lazy()
                                        ->key('motoristas-transporte'),
                                ]),
                            Tab::make('Veículos')
                                ->icon('heroicon-o-truck')
                                ->schema([
                                    LivewireComponent::make(VeiculosTransporteTable::class)
                                        ->lazy()
                                        ->key('veiculos-transporte'),
                                ]),
                        ]),
                ]),
            Action::make('importar')
                ->label('Importar planilha')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible(fn (): bool => $user !== null
                    && Gate::forUser($user)->allows('create', ImportacaoEventoCalendario::class))
                ->url(ImportarEventosCalendario::getUrl()),
            EventoCalendarioCreateAction::make('criarEvento', $user),
        ];
    }

    public function table(Table $table): Table
    {
        $user = static::usuarioEfetivo();

        abort_unless($user, 403);

        return $table
            ->query($this->listagem()->query($user))
            ->columns([
                TextColumn::make('criadoPor.name')
                    ->label('Criado por')
                    ->placeholder('Usuário não informado')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('titulo')
                    ->label('Evento')
                    ->description(fn (EventoCalendario $record): ?string => filled($record->descricao)
                        ? Str::limit(trim((string) $record->descricao), 110)
                        : null)
                    ->searchable(['titulo', 'descricao'])
                    ->sortable()
                    ->weight('bold')
                    ->wrap(),

                TextColumn::make('local')
                    ->label('Local')
                    ->placeholder('Não informado')
                    ->searchable()
                    ->toggleable()
                    ->wrap(),

                TextColumn::make('resumo_escolas')
                    ->label('Escolas e estimativas')
                    ->state(fn (EventoCalendario $record): array => $this->resumoEscolas($record))
                    ->listWithLineBreaks()
                    ->wrap(),

                TextColumn::make('total_estudantes_transporte')
                    ->label('Total de alunos')
                    ->state(fn (EventoCalendario $record): string => $record->possui_transporte
                        ? number_format((int) $record->total_estudantes_transporte, 0, ',', '.').' estimado(s)'
                        : '—')
                    ->alignCenter(),

                TextColumn::make('hora_inicio')
                    ->label('Início')
                    ->state(fn (EventoCalendario $record): string => $record->data_inicio->format('H:i'))
                    ->alignCenter(),

                TextColumn::make('hora_fim')
                    ->label('Fim')
                    ->state(fn (EventoCalendario $record): string => $record->data_fim->format('H:i'))
                    ->alignCenter(),

                TextColumn::make('data_inicio')
                    ->label('Data do evento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state?->label() ?? (string) $state)
                    ->color(fn (mixed $state): string => $state?->color() ?? 'gray')
                    ->alignCenter(),

                IconColumn::make('possui_inversao_fila')
                    ->label('Atenção')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('warning')
                    ->falseIcon(false)
                    ->tooltip(fn (EventoCalendario $record): ?string => $record->possui_inversao_fila
                        ? 'Este evento foi solicitado depois de outro evento já registrado, mas está agendado para uma data anterior.'
                        : null)
                    ->alignCenter(),
            ])
            ->filters($this->filtros($user), layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns([
                'default' => 1,
                'md' => 2,
                'xl' => 4,
            ])
            ->recordClasses(fn (EventoCalendario $record): ?string => $record->possui_inversao_fila
                ? 'gi-event-row--attention'
                : null)
            ->recordActions([
                ActionGroup::make($this->acoesDoRegistro($user))
                    ->label('Ações')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make($this->acoesEmMassa($user)),
            ])
            ->defaultSort(function (Builder $query) use ($user): Builder {
                if ($this->getTableSortColumn()) {
                    return $query;
                }

                if ($user->hasPermissionTo(ListaPermissoes::ListarEventosTransporte->label())) {
                    $query->orderByRaw(
                        'CASE WHEN eventos_calendario.status = ? THEN 0 ELSE 1 END',
                        [EventoCalendarioStatus::PENDENTE_APROVACAO->value],
                    );
                }

                return $query
                    ->orderBy('eventos_calendario.data_inicio')
                    ->orderBy('eventos_calendario.created_at')
                    ->orderBy('eventos_calendario.id');
            })
            ->defaultKeySort(false)
            ->searchPlaceholder('Buscar por evento, descrição ou criador')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->emptyStateHeading('Nenhum evento encontrado')
            ->emptyStateDescription('Altere os filtros ou cadastre um novo evento, caso possua permissão.')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    /**
     * @return array{pendentes: int, publicados: int, rejeitados: int, total_transporte: int}
     */
    public function getIndicadores(): array
    {
        $query = $this->getFilteredTableQuery();

        if (! $query || ! $this->podeVisualizarIndicadores()) {
            return $this->indicadoresVazios();
        }

        return $this->listagem()->indicadores(clone $query);
    }

    public function podeVisualizarIndicadores(): bool
    {
        return static::usuarioEfetivo()?->hasPermissionTo(
            ListaPermissoes::ListarEventosTransporte->label(),
        ) ?? false;
    }

    public static function usuarioEfetivo(): ?User
    {
        return app(ProfilePreviewService::class)->effectiveUser();
    }

    /** @return array<int, Filter|SelectFilter|TernaryFilter> */
    private function filtros(User $user): array
    {
        return [
            SelectFilter::make('escola_id')
                ->label('Escola')
                ->options(fn (): array => $this->listagem()->schoolOptions($user))
                ->searchable()
                ->preload()
                ->query(function (Builder $query, array $data): Builder {
                    $schoolId = (int) ($data['value'] ?? 0);

                    return $schoolId > 0
                        ? $this->listagem()->applySchoolFilter($query, $schoolId)
                        : $query;
                }),

            SelectFilter::make('status')
                ->label('Status')
                ->options(collect(EventoCalendarioStatus::cases())->mapWithKeys(
                    fn (EventoCalendarioStatus $status): array => [$status->value => $status->label()],
                )->all()),

            Filter::make('periodo_evento')
                ->label('Período do evento')
                ->columns(2)
                ->schema([
                    DatePicker::make('inicio')->label('De')->native(false),
                    DatePicker::make('fim')->label('Até')->native(false),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when(
                        filled($data['inicio'] ?? null),
                        fn (Builder $eventos): Builder => $eventos->whereDate('data_fim', '>=', $data['inicio']),
                    )
                    ->when(
                        filled($data['fim'] ?? null),
                        fn (Builder $eventos): Builder => $eventos->whereDate('data_inicio', '<=', $data['fim']),
                    )),

            Filter::make('data_evento')
                ->label('Data do evento')
                ->schema([
                    DatePicker::make('data')->label('Data exata')->native(false),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    filled($data['data'] ?? null),
                    fn (Builder $eventos): Builder => $eventos->whereDate('data_inicio', $data['data']),
                )),

            Filter::make('data_criacao')
                ->label('Data de criação')
                ->columns(2)
                ->schema([
                    DatePicker::make('inicio')->label('De')->native(false),
                    DatePicker::make('fim')->label('Até')->native(false),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when(
                        filled($data['inicio'] ?? null),
                        fn (Builder $eventos): Builder => $eventos->whereDate('created_at', '>=', $data['inicio']),
                    )
                    ->when(
                        filled($data['fim'] ?? null),
                        fn (Builder $eventos): Builder => $eventos->whereDate('created_at', '<=', $data['fim']),
                    )),

            SelectFilter::make('criado_por_id')
                ->label('Criador do evento')
                ->options(fn (): array => $this->listagem()->creatorOptions($user))
                ->searchable()
                ->preload(),

            TernaryFilter::make('possui_transporte')
                ->label('Transporte')
                ->placeholder('Com ou sem transporte')
                ->trueLabel('Com transporte')
                ->falseLabel('Sem transporte')
                ->queries(
                    true: fn (Builder $query): Builder => $query->comTransporte(),
                    false: fn (Builder $query): Builder => $query->semTransporte(),
                    blank: fn (Builder $query): Builder => $query,
                )
                ->native(false),
        ];
    }

    /** @return array<int, Action|EditAction> */
    private function acoesDoRegistro(User $user): array
    {
        return [
            Action::make('detalhes')
                ->label('Ver detalhes')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->authorize(fn (EventoCalendario $record): bool => Gate::forUser($user)->allows('view', $record))
                ->modalHeading(fn (EventoCalendario $record): string => $record->titulo)
                ->modalContent(fn (EventoCalendario $record): View => view(
                    'filament.admin.pages.partials.evento-calendario-detalhes',
                    ['evento' => $this->listagem()->detalhes($user, (int) $record->getKey())],
                ))
                ->extraModalFooterActions(fn (EventoCalendario $record): array => $this->acoesDoDetalhe($user, $record))
                ->slideOver()
                ->modalWidth('4xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),

            Action::make('relatorio')
                ->label('Gerar relatório PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->authorize(fn (EventoCalendario $record): bool => Gate::forUser($user)->allows('report', $record))
                ->action(fn (EventoCalendario $record) => app(EventoCalendarioRelatorioService::class)
                    ->download($user, $record)),

            EditAction::make('editar')
                ->label('Editar')
                ->icon('heroicon-o-pencil-square')
                ->slideOver()
                ->modalWidth('3xl')
                ->closeModalByClickingAway(false)
                ->schema(fn (): array => EventoCalendarioForm::components($user))
                ->fillForm(fn (EventoCalendario $record): array => EventoCalendarioForm::dadosParaEdicao(
                    $this->listagem()->detalhes($user, (int) $record->getKey()),
                    $record->attributesToArray(),
                ))
                ->authorize(fn (EventoCalendario $record): bool => Gate::forUser($user)->allows('update', $record))
                ->using(fn (EventoCalendario $record, array $data): EventoCalendario => app(EventoCalendarioService::class)
                    ->atualizar($record, $data, [], $user))
                ->successNotificationTitle('Evento atualizado'),

            Action::make('publicar')
                ->label(fn (EventoCalendario $record): string => $record->status === EventoCalendarioStatus::PENDENTE_APROVACAO
                    && $record->possui_transporte
                    ? 'Aprovar e publicar'
                    : 'Publicar')
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->visible(fn (EventoCalendario $record): bool => $record->status !== EventoCalendarioStatus::PUBLICADO
                    && Gate::forUser($user)->allows('publish', $record))
                ->authorize(fn (EventoCalendario $record): bool => Gate::forUser($user)->allows('publish', $record))
                ->requiresConfirmation()
                ->modalHeading(fn (EventoCalendario $record): string => $record->possui_transporte
                    ? 'Aprovar e publicar este evento?'
                    : 'Publicar este evento?')
                ->modalDescription('O evento ficará visível na agenda dos destinatários autorizados.')
                ->modalSubmitActionLabel('Publicar')
                ->action(function (EventoCalendario $record) use ($user): void {
                    app(EventoCalendarioWorkflowService::class)->publicar($record, $user);

                    Notification::make()->title('Evento publicado')->success()->send();
                }),

            Action::make('rejeitar')
                ->label('Rejeitar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (EventoCalendario $record): bool => in_array($record->status, [
                    EventoCalendarioStatus::PENDENTE_APROVACAO,
                    EventoCalendarioStatus::PUBLICADO,
                ], true)
                    && $record->possuiTransporte()
                    && Gate::forUser($user)->allows('reject', $record))
                ->authorize(fn (EventoCalendario $record): bool => Gate::forUser($user)->allows('reject', $record))
                ->requiresConfirmation()
                ->modalHeading('Rejeitar evento de transporte')
                ->modalDescription('O evento permanecerá registrado e não será exibido na agenda.')
                ->modalSubmitActionLabel('Rejeitar evento')
                ->schema([
                    Textarea::make('motivo')
                        ->label('Motivo da rejeição')
                        ->helperText('Opcional. A justificativa ficará registrada no histórico do evento.')
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->action(function (EventoCalendario $record, array $data) use ($user): void {
                    app(EventoCalendarioWorkflowService::class)->rejeitar(
                        $record,
                        $user,
                        filled($data['motivo'] ?? null) ? trim((string) $data['motivo']) : null,
                    );

                    Notification::make()->title('Evento rejeitado')->success()->send();
                }),

            Action::make('desativar')
                ->label('Desativar')
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->visible(fn (EventoCalendario $record): bool => $record->status === EventoCalendarioStatus::PUBLICADO
                    && Gate::forUser($user)->allows('deactivate', $record))
                ->authorize(fn (EventoCalendario $record): bool => Gate::forUser($user)->allows('deactivate', $record))
                ->requiresConfirmation()
                ->modalHeading('Desativar este evento?')
                ->modalDescription('O evento deixará de aparecer na agenda, mas permanecerá registrado.')
                ->modalSubmitActionLabel('Desativar')
                ->action(function (EventoCalendario $record) use ($user): void {
                    app(EventoCalendarioWorkflowService::class)->desativar($record, $user);

                    Notification::make()->title('Evento desativado')->success()->send();
                }),
        ];
    }

    /** @return array<int, Action> */
    private function acoesDoDetalhe(User $user, EventoCalendario $evento): array
    {
        if (! in_array($evento->status, [
            EventoCalendarioStatus::PENDENTE_APROVACAO,
            EventoCalendarioStatus::PUBLICADO,
        ], true)
            || ! $evento->possui_transporte) {
            return [];
        }

        return [
            Action::make('aprovarNoDetalhe')
                ->label('Aprovar e publicar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (EventoCalendario $record): bool => $record->status === EventoCalendarioStatus::PENDENTE_APROVACAO
                    && Gate::forUser($user)->allows('publish', $record))
                ->authorize(fn (EventoCalendario $record): bool => Gate::forUser($user)->allows('publish', $record))
                ->requiresConfirmation()
                ->modalHeading('Aprovar e publicar este evento?')
                ->modalDescription('O evento ficará visível na agenda dos destinatários autorizados.')
                ->modalSubmitActionLabel('Aprovar e publicar')
                ->action(function (EventoCalendario $record) use ($user): void {
                    app(EventoCalendarioWorkflowService::class)->publicar($record, $user);
                    Notification::make()->title('Evento aprovado e publicado')->success()->send();
                })
                ->cancelParentActions(),

            Action::make('rejeitarNoDetalhe')
                ->label('Rejeitar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (EventoCalendario $record): bool => Gate::forUser($user)->allows('reject', $record))
                ->authorize(fn (EventoCalendario $record): bool => Gate::forUser($user)->allows('reject', $record))
                ->requiresConfirmation()
                ->modalHeading('Rejeitar evento de transporte')
                ->modalDescription('O evento permanecerá registrado e não será exibido na agenda.')
                ->modalSubmitActionLabel('Rejeitar evento')
                ->schema([
                    Textarea::make('motivo')
                        ->label('Motivo da rejeição')
                        ->helperText('Opcional. A justificativa ficará registrada no histórico do evento.')
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->action(function (EventoCalendario $record, array $data) use ($user): void {
                    app(EventoCalendarioWorkflowService::class)->rejeitar(
                        $record,
                        $user,
                        filled($data['motivo'] ?? null) ? trim((string) $data['motivo']) : null,
                    );
                    Notification::make()->title('Evento rejeitado')->success()->send();
                })
                ->cancelParentActions(),
        ];
    }

    /** @return array<int, BulkAction> */
    private function acoesEmMassa(User $user): array
    {
        return [
            BulkAction::make('publicar')
                ->label('Publicar')
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->visible(fn (): bool => Gate::forUser($user)->allows('publish', EventoCalendario::class))
                ->requiresConfirmation()
                ->modalHeading('Publicar eventos selecionados')
                ->modalDescription('Todos os eventos selecionados precisam estar no seu escopo e respeitar a permissão específica de transporte.')
                ->action(function (iterable $records) use ($user): void {
                    $records = $this->eventosSelecionados($user, $records);

                    foreach ($records as $record) {
                        Gate::forUser($user)->authorize('publish', $record);
                    }

                    DB::transaction(function () use ($records, $user): void {
                        foreach ($records as $record) {
                            app(EventoCalendarioWorkflowService::class)->publicar($record, $user);
                        }
                    });

                    Notification::make()
                        ->title($records->count().' evento(s) publicado(s)')
                        ->success()
                        ->send();
                })
                ->deselectRecordsAfterCompletion(),

            BulkAction::make('desativar')
                ->label('Desativar')
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->visible(fn (): bool => Gate::forUser($user)->allows('deactivate', EventoCalendario::class))
                ->requiresConfirmation()
                ->modalHeading('Desativar eventos selecionados')
                ->modalDescription('Todos os eventos precisam estar publicados e dentro do seu escopo. Nenhum registro será excluído.')
                ->action(function (iterable $records) use ($user): void {
                    $records = $this->eventosSelecionados($user, $records);

                    foreach ($records as $record) {
                        Gate::forUser($user)->authorize('deactivate', $record);
                    }

                    DB::transaction(function () use ($records, $user): void {
                        foreach ($records as $record) {
                            app(EventoCalendarioWorkflowService::class)->desativar($record, $user);
                        }
                    });

                    Notification::make()
                        ->title($records->count().' evento(s) desativado(s)')
                        ->success()
                        ->send();
                })
                ->deselectRecordsAfterCompletion(),
        ];
    }

    /** @return list<string> */
    private function resumoEscolas(EventoCalendario $evento): array
    {
        $totalEscolas = $evento->enviar_todas_escolas
            ? (int) $evento->escolas_publico_count
            : (int) $evento->escolas_agendadas_count;

        if ($evento->enviar_todas_escolas) {
            return [$totalEscolas > 0
                ? "Todas as {$totalEscolas} escolas do escopo"
                : 'Todas as escolas do escopo'];
        }

        $resumo = $evento->escolasResumo
            ->map(function ($agendamento): string {
                $nome = $agendamento->escola?->nome ?? 'Escola não informada';

                return $agendamento->precisa_transporte
                    ? $nome.' — '.number_format((int) $agendamento->quantidade_estimada_transporte, 0, ',', '.').' aluno(s)'
                    : $nome;
            })
            ->values()
            ->all();

        $restantes = max(0, $totalEscolas - count($resumo));

        if ($restantes > 0) {
            $resumo[] = "+{$restantes} escola(s)";
        }

        return $resumo !== [] ? $resumo : ['Sem escola vinculada'];
    }

    /** @return EloquentCollection<int, EventoCalendario> */
    private function eventosSelecionados(User $user, iterable $records): EloquentCollection
    {
        $ids = [];

        foreach ($records as $record) {
            if ($record instanceof EventoCalendario) {
                $ids[] = (int) $record->getKey();
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));

        $eventos = app(EventoCalendarioAccessService::class)
            ->aplicarEscopo(
                $user,
                EventoCalendario::query()->with(
                    'escolasAgendadas:id,evento_calendario_id,precisa_transporte',
                ),
            )
            ->whereKey($ids)
            ->get();

        abort_unless($ids !== [] && $eventos->count() === count($ids), 403);

        return $eventos;
    }

    /** @return array{pendentes: int, publicados: int, rejeitados: int, total_transporte: int} */
    private function indicadoresVazios(): array
    {
        return [
            'pendentes' => 0,
            'publicados' => 0,
            'rejeitados' => 0,
            'total_transporte' => 0,
        ];
    }

    private function listagem(): EventoCalendarioListQueryService
    {
        return app(EventoCalendarioListQueryService::class);
    }
}
