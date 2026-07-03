<?php

namespace App\Services;

use App\Exceptions\MatriculaAlunoBloqueadaException;
use App\Jobs\DeleteAlunosEmMassaJob;
use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\Escola;
use App\Models\ExportRequest;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AlunoService
{
    public function __construct(
        protected UserService $userService
    ) {}

    public function configurarFormulario(Schema $schema, string $operation): Schema
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $schema->components([
            Section::make('Dados do Aluno')
                ->columnSpanFull()
                ->schema([
                    Hidden::make('cgm_consultado')
                        ->default(false)
                        ->dehydrated(false),

                    Hidden::make('cgm_encontrado_aluno_id')
                        ->dehydrated(false),

                    Hidden::make('tipo_vinculo')
                        ->default(Aluno::TIPO_VINCULO_PRINCIPAL),

                    TextInput::make('cgm')
                        ->label('CGM')
                        ->required()
                        ->maxLength(255)
                        ->live(debounce: 500)
                        ->afterStateUpdated(function (?string $state, Set $set, ?Aluno $record = null, ?string $operation = null): void {
                            $this->consultarCgmFormulario($state, $set, $record, $operation);
                        })
                        ->helperText(fn (Get $get, ?string $operation = null): ?string => $this->textoAjudaCgmFormulario($get, $operation)),

                    TextInput::make('nome')
                        ->label('Nome')
                        ->required()
                        ->maxLength(255)
                        ->extraInputAttributes(fn (Get $get, ?string $operation = null): array => $this->atributosCampoBloqueadoAposCgm($get, $operation))
                        ->disabled(fn (Get $get, ?string $operation = null): bool => $this->campoBloqueadoAposCgm($get, $operation)),

                    DatePicker::make('data_nascimento')
                        ->label('Data de Nascimento')
                        ->required()
                        ->native()
                        ->displayFormat('d/m/Y')
                        ->extraInputAttributes(fn (Get $get, ?string $operation = null): array => $this->atributosCampoBloqueadoAposCgm($get, $operation))
                        ->disabled(fn (Get $get, ?string $operation = null): bool => $this->campoBloqueadoAposCgm($get, $operation)),

                    Select::make('sexo')
                        ->label('Sexo')
                        ->options([
                            'F' => 'Feminino',
                            'M' => 'Masculino',
                        ])
                        ->native(false)
                        ->extraInputAttributes(fn (Get $get, ?string $operation = null): array => $this->atributosCampoBloqueadoAposCgm($get, $operation))
                        ->disabled(fn (Get $get, ?string $operation = null): bool => $this->campoBloqueadoAposCgm($get, $operation)),

                    DatePicker::make('data_matricula')
                        ->label('Data de Matricula')
                        ->native()
                        ->displayFormat('d/m/Y')
                        ->extraInputAttributes(fn (Get $get, ?string $operation = null): array => $this->atributosCampoBloqueadoAposCgm($get, $operation))
                        ->disabled(fn (Get $get, ?string $operation = null): bool => $this->campoBloqueadoAposCgm($get, $operation)),

                    Select::make('id_escola')
                        ->label('Escola')
                        ->options(fn () => $this->opcoesDeEscolas($user))
                        ->default(fn () => $this->escolaInicialFormularioAluno($user))
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('id_serie', null);
                            $set('id_turma', null);
                            $set('turma_contra_turno_id', null);
                        })
                        ->extraInputAttributes(fn (Get $get, ?string $operation = null): array => $this->atributosCampoBloqueadoAposCgm($get, $operation))
                        ->disabled(fn (Get $get, ?string $operation = null): bool => $this->campoBloqueadoAposCgm($get, $operation)
                            || $this->deveTravarEscolaAluno($user))
                        ->dehydrated(false)
                        ->columnSpanFull(),

                    Select::make('id_serie')
                        ->label('Serie')
                        ->options(fn (Get $get): array => $this->opcoesDeSeriesPorEscola((int) ($get('id_escola') ?? 0), $user))
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('id_turma', null);
                            $set('turma_contra_turno_id', null);
                        })
                        ->extraInputAttributes(fn (Get $get, ?string $operation = null): array => $this->atributosCampoBloqueadoAposCgm($get, $operation))
                        ->disabled(fn (Get $get, ?string $operation = null): bool => $this->campoBloqueadoAposCgm($get, $operation)
                            || blank($get('id_escola')))
                        ->dehydrated(false)
                        ->columnSpanFull(),

                    Select::make('id_turma')
                        ->label('Turma')
                        ->options(fn (Get $get): array => $this->opcoesDeTurmasPorEscolaSerie(
                            (int) ($get('id_escola') ?? 0),
                            (int) ($get('id_serie') ?? 0),
                            $user
                        ))
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('turma_contra_turno_id', null);
                        })
                        ->extraInputAttributes(fn (Get $get, ?string $operation = null): array => $this->atributosCampoBloqueadoAposCgm($get, $operation))
                        ->disabled(fn (Get $get, ?string $operation = null): bool => $this->campoBloqueadoAposCgm($get, $operation)
                            || blank($get('id_escola'))
                            || blank($get('id_serie')))
                        ->columnSpanFull(),

                    Checkbox::make('permite_contra_turno')
                        ->label('Permite contra turno')
                        ->live()
                        ->visible(fn (Get $get, ?string $operation = null): bool => $operation !== 'create'
                            && ($get('tipo_vinculo') ?? Aluno::TIPO_VINCULO_PRINCIPAL) === Aluno::TIPO_VINCULO_PRINCIPAL)
                        ->columnSpanFull(),

                    Select::make('turma_contra_turno_id')
                        ->label('Turma de contra turno')
                        ->options(fn (Get $get): array => $this->opcoesDeTurmasParaContraTurnoPorFormulario(
                            (int) ($get('id_turma') ?? 0),
                            $user
                        ))
                        ->searchable()
                        ->visible(fn (Get $get, ?string $operation = null): bool => $operation !== 'create'
                            && ($get('tipo_vinculo') ?? Aluno::TIPO_VINCULO_PRINCIPAL) === Aluno::TIPO_VINCULO_PRINCIPAL
                            && (bool) ($get('permite_contra_turno') ?? false))
                        ->helperText('Opcional. Se informado, cria o vinculo secundario em turno diferente.')
                        ->columnSpanFull(),

                    Select::make('status')
                        ->label('Status')
                        ->options(Aluno::statusOptions())
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (?string $operation = null): bool => $operation !== 'create'),
                ])
                ->columns(2),
        ]);
    }

    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) use ($user) {
                $this->userService->aplicarFiltroAlunosDoUsuario($query, $user);

                $pendenciaProfessor = app(AlunoTransferenciaPendenteService::class)->pendenciaAtivaParaProfessor($user);

                if ($pendenciaProfessor) {
                    $query->whereKey((int) $pendenciaProfessor->id);
                } elseif (request()->filled('turma')) {
                    $query->where('id_turma', request()->integer('turma'));
                }

                $query
                    ->with(['turma.escola', 'turma.serie'])
                    ->select('alunos.*')
                    ->selectSub(function ($subquery): void {
                        $subquery
                            ->from('avaliacao_turma')
                            ->selectRaw('count(*) > 0')
                            ->whereColumn('avaliacao_turma.turma_id', 'alunos.id_turma');
                    }, 'turma_tem_avaliacoes');
            })
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->searchable([
                'status',
                'sexo',
                'turma.nome',
                'turma.serie.nome',
                'turma.escola.nome',
                fn (Builder $query, string $search): Builder => $query->where(function (Builder $labelQuery) use ($search): void {
                    $search = Str::lower(Str::ascii($search));

                    $status = collect(Aluno::statusOptions())
                        ->filter(fn (string $label): bool => str_contains(Str::lower(Str::ascii($label)), $search))
                        ->keys()
                        ->all();
                    $sexos = collect([
                        'F' => 'Feminino',
                        'M' => 'Masculino',
                    ])
                        ->filter(fn (string $label): bool => str_contains(Str::lower(Str::ascii($label)), $search))
                        ->keys()
                        ->all();

                    $tipos = collect(Aluno::tiposVinculoOptions())
                        ->filter(fn (string $label): bool => str_contains(Str::lower(Str::ascii($label)), $search))
                        ->keys()
                        ->all();

                    $labelQuery
                        ->whereIn('status', $status)
                        ->orWhereIn('sexo', $sexos)
                        ->orWhereIn('tipo_vinculo', $tipos);
                }),
            ])
            ->searchPlaceholder('Buscar por nome, CGM, status, turma, serie ou escola')
            ->columns($this->colunasTabela($user))
            ->filters($this->filtrosTabela($user))
            ->recordAction(null)
            ->recordActions($this->acoesTabela($user), RecordActionsPosition::AfterContent)
            ->toolbarActions($this->acoesEmMassa($user))
            ->defaultSort('nome')
            ->striped();
    }

    private function colunasTabela(?User $user): array
    {
        $podeListarEscolas = $user?->hasPermissionTo('Listar Escolas') ?? false;

        return [
            TextColumn::make('nome')
                ->label('Nome')
                ->description(fn (Aluno $record): string => trim(collect([
                    filled($record->cgm) ? 'CGM: '.$record->cgm : null,
                ])->filter()->join(' | ')))
                ->searchable()
                ->sortable()
                ->copyable()
                ->copyMessage('Copiado')
                ->copyMessageDuration(1500)
                ->tooltip('Clique para copiar')
                ->weight('bold')
                ->wrap()
                ->extraAttributes(['class' => 'aluno-card-name'], merge: true),

            Grid::make([
                'default' => 2,
                'md' => 3,
                'xl' => 6,
            ])
                ->schema([
                    TextColumn::make('status')
                        ->label('Status')
                        ->formatStateUsing(fn (?string $state): string => Aluno::statusOptions()[$state] ?? ucfirst((string) $state))
                        ->badge()
                        ->color(fn (?string $state): string => match ($state) {
                            Aluno::STATUS_MATRICULADO => 'success',
                            Aluno::STATUS_PENDENTE => 'warning',
                            Aluno::STATUS_REMANEJADO => 'warning',
                            Aluno::STATUS_TRANSFERIDO => 'info',
                            Aluno::STATUS_APROVADO => 'success',
                            Aluno::STATUS_RETIDO => 'danger',
                            Aluno::STATUS_CONTRA_TURNO_ENCERRADO => 'gray',
                            default => 'gray',
                        })
                        ->sortable()
                        ->extraAttributes(['class' => 'aluno-card-field'], merge: true),

                    TextColumn::make('sexo')
                        ->label('Sexo')
                        ->formatStateUsing(fn (?string $state): string => match ($state) {
                            'F' => 'Feminino',
                            'M' => 'Masculino',
                            default => (string) $state,
                        })
                        ->badge()
                        ->placeholder('Nao informado')
                        ->extraAttributes(['class' => 'aluno-card-field'], merge: true),

                    TextColumn::make('turma.serie.nome')
                        ->label('Série')
                        ->badge()
                        ->color('gray')
                        ->placeholder('–')
                        ->searchable()
                        ->sortable()
                        ->wrap()
                        ->extraAttributes(['class' => 'aluno-card-field'], merge: true),

                    TextColumn::make('turma.nome')
                        ->label('Turma')
                        ->badge()
                        ->color('primary')
                        ->placeholder('–')
                        ->searchable()
                        ->sortable()
                        ->wrap()
                        ->extraAttributes(['class' => 'aluno-card-field'], merge: true),

                    TextColumn::make('turma.turno')
                        ->label('Turno')
                        ->formatStateUsing(fn (?string $state): string => match ($state) {
                            'manha' => 'Manhã',
                            'tarde' => 'Tarde',
                            'noite' => 'Noite',
                            'integral' => 'Integral',
                            default => ucfirst((string) $state),
                        })
                        ->badge()
                        ->color(fn (?string $state): string => match ($state) {
                            'manha' => 'info',
                            'tarde' => 'warning',
                            'noite' => 'gray',
                            'integral' => 'success',
                            default => 'gray',
                        })
                        ->placeholder('–')
                        ->sortable()
                        ->wrap()
                        ->extraAttributes(['class' => 'aluno-card-field'], merge: true),

                    TextColumn::make('turma.escola.nome')
                        ->label('Escola')
                        ->icon('heroicon-o-building-office-2')
                        ->sortable()
                        ->placeholder('Escola nao informada')
                        ->wrap()
                        ->visible($podeListarEscolas)
                        ->extraAttributes(['class' => 'aluno-card-field aluno-card-field--school'], merge: true),
                ])
                ->extraAttributes(['class' => 'aluno-card-main-grid']),
        ];
    }

    private function filtrosTabela(?User $user): array
    {
        return [
            SelectFilter::make('id_serie')
                ->label('Serie')
                ->options(fn (): array => $this->opcoesDeSeries($user))
                ->searchable()
                ->query(function (Builder $query, array $data) {
                    if (blank($data['value'] ?? null)) {
                        return $query;
                    }

                    return $query->whereHas('turma', fn (Builder $q) => $q->where('id_serie', $data['value']));
                }),

            SelectFilter::make('status')
                ->label('Status')
                ->options(Aluno::statusOptions()),

            SelectFilter::make('tipo_vinculo')
                ->label('Vinculo')
                ->options(Aluno::tiposVinculoOptions()),

            SelectFilter::make('id_turma')
                ->label('Turma')
                ->options(fn (): array => $this->opcoesDeTurmas($user))
                ->searchable(),

            SelectFilter::make('sexo')
                ->label('Sexo')
                ->options([
                    'F' => 'Feminino',
                    'M' => 'Masculino',
                ]),

            SelectFilter::make('sem_professor')
                ->label('Professor')
                ->options([
                    '1' => 'Pendentes sem professor',
                ])
                ->query(function (Builder $query, array $data) {
                    if (($data['value'] ?? null) !== '1') {
                        return $query;
                    }

                    $this->aplicarFiltroPendenciaSemProfessor($query);

                    return $query;
                }),

            SelectFilter::make('id_escola')
                ->label('Escola')
                ->options(fn (): array => $this->opcoesDeEscolas($user))
                ->searchable()
                ->query(function (Builder $query, array $data) {
                    if (blank($data['value'] ?? null)) {
                        return $query;
                    }

                    return $query->whereHas('turma', fn (Builder $q) => $q->where('id_escola', $data['value']));
                })
                ->visible(fn () => $user?->hasPermissionTo('Filtrar Alunos por Escola') ?? false),
        ];
    }

    private function acoesTabela(?User $user): array
    {
        $podeListarEscolas = $user?->hasPermissionTo('Listar Escolas') ?? false;

        return [
            ActionGroup::make([
                ViewAction::make('visualizar')
                    ->label('Visualizar')
                    ->modalHeading(fn (Aluno $record): string => $record->nome)
                    ->modalWidth('4xl')
                    ->schema([
                        Section::make('Dados do Aluno')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('nome')
                                    ->label('Nome'),
                                \Filament\Infolists\Components\TextEntry::make('cgm')
                                    ->label('CGM'),
                                \Filament\Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->formatStateUsing(fn (?string $state): string => Aluno::statusOptions()[$state] ?? ucfirst((string) $state)),
                                \Filament\Infolists\Components\TextEntry::make('sexo')
                                    ->label('Sexo')
                                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                                        'F' => 'Feminino',
                                        'M' => 'Masculino',
                                        default => (string) $state,
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('data_nascimento')
                                    ->label('Data de Nascimento')
                                    ->date('d/m/Y')
                                    ->placeholder('Nao informada'),
                                \Filament\Infolists\Components\TextEntry::make('data_matricula')
                                    ->label('Data de Matricula')
                                    ->date('d/m/Y')
                                    ->placeholder('Nao informada'),
                                \Filament\Infolists\Components\TextEntry::make('tipo_vinculo')
                                    ->label('Vinculo')
                                    ->formatStateUsing(fn (?string $state): string => Aluno::tiposVinculoOptions()[$state] ?? ucfirst((string) $state)),
                            ])
                            ->columns(3),

                        Section::make('Turma')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('turma.serie.nome')
                                    ->label('Serie'),
                                \Filament\Infolists\Components\TextEntry::make('turma.nome')
                                    ->label('Turma'),
                                \Filament\Infolists\Components\TextEntry::make('turma.escola.nome')
                                    ->label('Escola')
                                    ->visible($podeListarEscolas),
                            ])
                            ->columns(3),

                        Section::make('Contra Turno')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('contra_turno_status')
                                    ->label('Situacao')
                                    ->getStateUsing(function ($record): string {
                                        if (! $record instanceof Aluno) {
                                            return 'Nao se aplica';
                                        }
                                        if ($record->isContraTurno()) {
                                            return 'Vinculo secundario ativo';
                                        }
                                        if ($record->isPrincipal()) {
                                            return $record->permite_contra_turno
                                                ? 'Permite contra turno'
                                                : 'Nao permite contra turno';
                                        }
                                        return 'Nao se aplica';
                                    }),
                            ])
                            ->columns(1),
                    ]),

                Action::make('remanejar')
                    ->label('Remanejar')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('warning')
                    ->visible(fn (Aluno $record): bool => $this->podeRemanejar($record, $user))
                    ->modalHeading(fn (Aluno $record): string => 'Remanejar '.$record->nome)
                    ->modalSubmitActionLabel('Remanejar')
                    ->schema(fn (Aluno $record): array => [
                        Select::make('turma_destino_id')
                            ->label('Nova turma')
                            ->options(fn () => $this->opcoesDeTurmasParaRemanejamento($record, $user))
                            ->searchable()
                            ->required(),
                        Textarea::make('motivo')
                            ->label('Motivo')
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->action(function (Aluno $record, array $data): void {
                        app(AlunoMovimentacaoService::class)->remanejar(
                            $record,
                            (int) $data['turma_destino_id'],
                            Auth::user(),
                            $data['motivo'] ?? null
                        );

                        Notification::make()
                            ->title('Aluno remanejado com sucesso.')
                            ->success()
                            ->send();
                    }),

                Action::make('voltar_turma_anterior')
                    ->label('Voltar turma anterior')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('info')
                    ->visible(fn (Aluno $record): bool => $this->podeVoltarTurmaAnterior($record, $user))
                    ->requiresConfirmation()
                    ->modalHeading('Voltar para a turma anterior')
                    ->modalDescription('A volta sera registrada como um novo remanejamento.')
                    ->action(function (Aluno $record): void {
                        app(AlunoMovimentacaoService::class)->voltarParaTurmaAnterior($record, Auth::user());

                        Notification::make()
                            ->title('Aluno retornado para a turma anterior com sucesso.')
                            ->success()
                            ->send();
                    }),

                Action::make('marcar_contra_turno')
                    ->label('Marcar contra turno')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->visible(fn (Aluno $record): bool => $this->podeMarcarContraTurno($record, $user))
                    ->fillForm(fn (Aluno $record): array => [
                        'turma_contra_turno_id' => $this->turmaContraTurnoAtivaId($record),
                        'motivo' => null,
                    ])
                    ->modalHeading(fn (Aluno $record): string => 'Contra turno de '.$record->nome)
                    ->modalSubmitActionLabel('Salvar')
                    ->schema(fn (Aluno $record): array => [
                        Select::make('turma_contra_turno_id')
                            ->label('Turma de contra turno')
                            ->options(fn () => $this->opcoesDeTurmasParaContraTurno($record, $user))
                            ->searchable(),
                        Textarea::make('motivo')
                            ->label('Motivo')
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->action(function (Aluno $record, array $data): void {
                        $service = app(AlunoMovimentacaoService::class);
                        $service->marcarContraTurno($record, Auth::user(), $data['motivo'] ?? null);

                        if (filled($data['turma_contra_turno_id'] ?? null)) {
                            $service->vincularContraTurno(
                                $record,
                                (int) $data['turma_contra_turno_id'],
                                Auth::user(),
                                $data['motivo'] ?? null
                            );
                        }

                        Notification::make()
                            ->title('Contra turno atualizado com sucesso.')
                            ->success()
                            ->send();
                    }),

                Action::make('encerrar_contra_turno')
                    ->label('Encerrar contra turno')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (Aluno $record): bool => $this->podeEncerrarContraTurno($record, $user))
                    ->requiresConfirmation()
                    ->modalHeading('Encerrar contra turno')
                    ->modalDescription('O vinculo secundario sera encerrado e seus dados avaliativos ficarao bloqueados.')
                    ->action(function (Aluno $record): void {
                        app(AlunoMovimentacaoService::class)->encerrarContraTurno($record, Auth::user());

                        Notification::make()
                            ->title('Contra turno encerrado com sucesso.')
                            ->success()
                            ->send();
                    }),

                Action::make('parecer_transferencia')
                    ->label('Parecer de Transferencia')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->slideOver()
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalHeading(fn (Aluno $record): string => 'Parecer de Transferencia')
                    ->modalDescription(fn (Aluno $record): string => trim(collect([
                        $record->nome,
                        'CGM: '.$record->cgm,
                        $record->turma?->escola?->nome,
                        $record->turma?->serie?->nome.' - '.$record->turma?->nome,
                        $record->statusLabel(),
                    ])->filter()->join(' | ')))
                    ->modalContent(fn (Aluno $record) => view('components.alunos.parecer-transferencia-modal', [
                        'aluno' => $record,
                    ]))
                    ->visible(fn (Aluno $record): bool => $this->podeGerarParecerTransferencia($record, $user)),

                EditAction::make()
                    ->modalWidth('4xl')
                    ->fillForm(function (Aluno $record): array {
                        $record->loadMissing('turma');

                        return [
                            'nome' => $record->nome,
                            'cgm' => $record->cgm,
                            'data_nascimento' => $record->data_nascimento?->format('Y-m-d'),
                            'sexo' => $record->sexo,
                            'data_matricula' => $record->data_matricula?->format('Y-m-d'),
                            'id_escola' => $record->turma?->id_escola,
                            'id_serie' => $record->turma?->id_serie,
                            'id_turma' => $record->id_turma,
                            'tipo_vinculo' => $record->tipo_vinculo,
                            'permite_contra_turno' => (bool) $record->permite_contra_turno,
                            'turma_contra_turno_id' => $record->isPrincipal() ? $this->turmaContraTurnoAtivaId($record) : null,
                            'status' => $record->status,
                        ];
                    })
                    ->using(function (Aluno $record, array $data) use ($user): Aluno {
                        $permiteContraTurno = (bool) ($data['permite_contra_turno'] ?? false);
                        $turmaContraTurnoId = (int) ($data['turma_contra_turno_id'] ?? 0);

                        unset($data['tipo_vinculo'], $data['permite_contra_turno'], $data['turma_contra_turno_id']);
                        unset($data['id_escola'], $data['id_serie']);
                        $this->validarTurmaPermitida((int) ($data['id_turma'] ?? 0), $user);

                        try {
                            app(AlunoMovimentacaoService::class)->bloquearSeCgmAtivo(
                                (string) ($data['cgm'] ?? ''),
                                (int) ($data['id_turma'] ?? 0),
                                $user,
                                $record
                            );
                        } catch (MatriculaAlunoBloqueadaException $exception) {
                            Notification::make()
                                ->title('Matricula impedida')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();

                            throw ValidationException::withMessages([
                                'data.cgm' => $exception->getMessage(),
                            ]);
                        }

                        $record->update($data);

                        if ($record->isPrincipal()) {
                            $movimentacaoService = app(AlunoMovimentacaoService::class);

                            if (! $permiteContraTurno) {
                                $contraTurnoAtivo = $this->contraTurnoAtivo($record);

                                if ($contraTurnoAtivo) {
                                    $movimentacaoService->encerrarContraTurno($contraTurnoAtivo, $user, 'Contra turno removido pela ficha do aluno.');
                                } else {
                                    $record->forceFill(['permite_contra_turno' => false])->save();
                                }
                            } else {
                                $movimentacaoService->marcarContraTurno($record, $user, 'Contra turno marcado pela ficha do aluno.');

                                if ($turmaContraTurnoId > 0) {
                                    $movimentacaoService->vincularContraTurno(
                                        $record,
                                        $turmaContraTurnoId,
                                        $user,
                                        'Turma de contra turno vinculada pela ficha do aluno.'
                                    );
                                }
                            }

                            $record->refresh();
                        }

                        return $record;
                    })
                    ->visible(fn (Aluno $record) => $this->podeEditarAluno($record, $user)),

                DeleteAction::make()
                    ->visible(fn (Aluno $record) => $this->podeExcluirAluno($record, $user)),
            ])
                ->label('Ações')
                ->icon('heroicon-m-ellipsis-vertical')
                ->button()
                ->color('gray'),
        ];
    }

    public function podeRemanejar(Aluno $record, ?User $user): bool
    {
        return ! $this->professorEstaBloqueado($user)
            && $record->isPrincipal()
            && ($record->estaMatriculado() || $record->estaPendente())
            && ($user?->hasPermissionLike('Realizar Remanejamento de Aluno') ?? false);
    }

    public function podeVoltarTurmaAnterior(Aluno $record, ?User $user): bool
    {
        return ! $this->professorEstaBloqueado($user)
            && $record->isPrincipal()
            && ($record->estaMatriculado() || $record->estaPendente())
            && (int) $record->turma_origem_id > 0
            && ($user?->hasPermissionLike('Realizar Remanejamento de Aluno') ?? false);
    }

    public function podeMarcarContraTurno(Aluno $record, ?User $user): bool
    {
        return ! $this->professorEstaBloqueado($user)
            && $record->isPrincipal()
            && $record->estaMatriculado()
            && $this->userService->podeEditarAlunos($user);
    }

    public function podeEncerrarContraTurno(Aluno $record, ?User $user): bool
    {
        return ! $this->professorEstaBloqueado($user)
            && $record->isContraTurno()
            && $record->estaMatriculado()
            && $this->userService->podeEditarAlunos($user);
    }

    public function podeGerarParecerTransferencia(Aluno $record, ?User $user): bool
    {
        return $record->isPrincipal()
            && $this->alunoTemAvaliacoes($record)
            && ($this->professorEstaRestritoAoAluno($user, $record)
                || (($user?->hasPermissionLike('realizar transferencia de aluno') ?? false)
                    || ($user?->hasPermissionLike('realizar tranferencia de aluno') ?? false)
                    || ($user?->hasPermissionLike('gerar parecer de transferencia') ?? false)));
    }

    public function podeEditarAluno(Aluno $record, ?User $user): bool
    {
        return ! $this->professorEstaBloqueado($user)
            && $record->estaMatriculado()
            && $record->isPrincipal()
            && $this->userService->podeEditarAlunos($user);
    }

    public function podeExcluirAluno(Aluno $record, ?User $user): bool
    {
        return ! $this->professorEstaBloqueado($user)
            && $record->estaMatriculado()
            && $record->isPrincipal()
            && $this->userService->podeExcluirAlunos($user);
    }

    private function acoesEmMassa(?User $user): array
    {
        return [
            BulkAction::make('marcar_contra_turno_massa')
                ->label('Marcar contra turno')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->visible(fn () => ! $this->professorEstaBloqueado($user)
                    && $this->userService->podeEditarAlunos($user))
                ->schema([
                    Select::make('turma_contra_turno_id')
                        ->label('Turma de contra turno')
                        ->options(fn (): array => $this->opcoesGeraisDeTurmasContraTurno($user))
                        ->searchable(),
                ])
                ->action(function (array $data, $records): void {
                    $service = app(AlunoMovimentacaoService::class);
                    $currentUser = Auth::user();
                    $sucessos = 0;
                    $falhas = [];
                    $turmaContraTurnoId = (int) ($data['turma_contra_turno_id'] ?? 0);
                    $records = $records instanceof Builder ? $records->get() : collect($records);

                    foreach ($records as $record) {
                        try {
                            if (! $record instanceof Aluno || ! $record->isPrincipal() || ! $record->estaMatriculado()) {
                                throw new \RuntimeException('Registro invalido para contra turno.');
                            }

                            $service->marcarContraTurno($record, $currentUser, 'Contra turno marcado em massa.');

                            if ($turmaContraTurnoId > 0) {
                                $service->vincularContraTurno($record, $turmaContraTurnoId, $currentUser, 'Turma de contra turno vinculada em massa.');
                            }

                            $sucessos++;
                        } catch (\Throwable $exception) {
                            $falhas[] = ($record instanceof Aluno ? $record->nome : 'Registro selecionado').': '.$exception->getMessage();
                        }
                    }

                    Notification::make()
                        ->title('Ação em massa concluída.')
                        ->body($this->mensagemResultadoContraTurnoMassa($sucessos, $falhas))
                        ->{$falhas === [] ? 'success' : 'warning'}()
                        ->send();
                })
                ->deselectRecordsAfterCompletion(),

            BulkAction::make('delete')
                ->label('Excluir selecionados')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Excluir alunos selecionados')
                ->modalDescription('A exclusao sera enviada para processamento em segundo plano. Voce podera continuar usando o sistema.')
                ->modalSubmitActionLabel('Enviar para exclusao')
                ->fetchSelectedRecords(false)
                ->visible(fn () => ! $this->professorEstaBloqueado($user)
                    && ($user?->hasPermissionTo('Excluir Alunos em Massa') ?? false))
                ->action(function ($recordsQuery): void {
                    $ids = $recordsQuery
                        ->pluck('alunos.id')
                        ->map(fn ($id): int => (int) $id)
                        ->values()
                        ->all();
                    $processo = $this->criarProcessoExclusaoEmMassa($ids);

                    DeleteAlunosEmMassaJob::dispatch($ids, Auth::id(), $processo->getKey())->afterCommit();

                    Notification::make()
                        ->title('Exclusao enviada para processamento')
                        ->body(count($ids).' aluno(s) foram enviados para exclusao em segundo plano. Acompanhe em Minhas Exportacoes.')
                        ->success()
                        ->send();
                })
                ->deselectRecordsAfterCompletion(),
        ];
    }

    public function queryVisivel(?User $user): Builder
    {
        $query = Aluno::query();

        return $this->userService->aplicarFiltroAlunosDoUsuario($query, $user);
    }

    private function criarProcessoExclusaoEmMassa(array $ids): ExportRequest
    {
        return ExportRequest::query()->create([
            'user_id' => Auth::id(),
            'type' => 'alunos_exclusao_massa',
            'format' => 'processo',
            'label' => 'Exclusao de alunos em massa',
            'filters' => ['total' => count($ids)],
            'metadata' => ['process_kind' => 'exclusao_alunos_massa'],
            'fingerprint' => hash('sha256', 'alunos_exclusao|'.Auth::id().'|'.json_encode($ids).'|'.Str::uuid()),
            'status' => ExportRequest::STATUS_QUEUED,
            'status_message' => 'Aguardando processamento.',
            'progress_current' => 0,
            'progress_total' => max(1, count($ids)),
            'expires_at' => now()->addDays((int) config('exports.expiration_days', 7)),
        ]);
    }

    private function formularioAlunoLiberadoAposCgm(Get $get): bool
    {
        return filled($get('cgm')) && (bool) $get('cgm_consultado');
    }

    private function campoBloqueadoAposCgm(Get $get, ?string $operation): bool
    {
        return $operation === 'create' && ! $this->formularioAlunoLiberadoAposCgm($get);
    }

    private function atributosCampoBloqueadoAposCgm(Get $get, ?string $operation): array
    {
        if (! $this->campoBloqueadoAposCgm($get, $operation)) {
            return [];
        }

        return [
            'class' => 'bg-gray-100 text-gray-500 cursor-not-allowed opacity-75',
        ];
    }

    private function textoAjudaCgmFormulario(Get $get, ?string $operation): ?string
    {
        if ($operation !== 'create') {
            return filled($get('cgm_encontrado_aluno_id'))
                ? 'CGM encontrado em outro cadastro. Confira os dados antes de salvar.'
                : null;
        }

        if (! $this->formularioAlunoLiberadoAposCgm($get)) {
            return 'Informe o CGM para liberar os demais campos.';
        }

        return filled($get('cgm_encontrado_aluno_id'))
            ? 'Aluno encontrado no sistema. Confira os dados e selecione serie e turma de destino.'
            : 'CGM nao encontrado. Preencha os dados do novo aluno.';
    }

    private function consultarCgmFormulario(?string $state, Set $set, ?Aluno $record, ?string $operation): void
    {
        $cgm = Aluno::normalizarCgm($state);
        $set('cgm', $cgm);
        $set('cgm_consultado', $cgm !== '');
        $set('cgm_encontrado_aluno_id', null);

        if ($cgm === '') {
            return;
        }

        $alunoExistente = $this->alunoPorCgmParaFormulario($cgm, $record?->getKey());

        if (! $alunoExistente) {
            return;
        }

        $set('cgm_encontrado_aluno_id', (int) $alunoExistente->id);

        if ($operation !== 'create') {
            return;
        }

        $set('nome', $alunoExistente->nome);
        $set('data_nascimento', $alunoExistente->data_nascimento?->format('Y-m-d'));
        $set('sexo', $alunoExistente->sexo);
        $set('data_matricula', $alunoExistente->data_matricula?->format('Y-m-d'));
    }

    private function alunoPorCgmParaFormulario(string $cgm, ?int $ignorarAlunoId = null): ?Aluno
    {
        $query = Aluno::query()
            ->where('cgm', Aluno::normalizarCgm($cgm));

        if ($ignorarAlunoId) {
            $query->whereKeyNot($ignorarAlunoId);
        }

        return $query
            ->orderByRaw("case when tipo_vinculo = ? then 0 else 1 end", [Aluno::TIPO_VINCULO_PRINCIPAL])
            ->latest('status_alterado_em')
            ->latest('updated_at')
            ->first();
    }

    private function professorEstaBloqueado(?User $user): bool
    {
        return app(AlunoTransferenciaPendenteService::class)->professorEstaBloqueado($user);
    }

    private function professorEstaRestritoAoAluno(?User $user, Aluno $aluno): bool
    {
        return app(AlunoTransferenciaPendenteService::class)->professorEstaRestritoAoAluno($user, $aluno);
    }

    public function opcoesDeSeries(?User $user): array
    {
        $query = Serie::query()
            ->whereHas('turmas', function (Builder $q) use ($user): void {
                $this->aplicarFiltroTurmasFormularioAluno($q, $user);
            })
            ->orderBy('nome');

        return $query->pluck('nome', 'id')->toArray();
    }

    public function opcoesDeTurmas(?User $user): array
    {
        $query = Turma::query()
            ->with(['serie:id,nome', 'escola:id,nome'])
            ->orderBy('nome');

        $this->aplicarFiltroTurmasFormularioAluno($query, $user);

        return $query->get()
            ->mapWithKeys(function (Turma $turma) {
                $label = trim(collect([
                    $turma->escola?->nome,
                    $turma->serie?->nome,
                    $turma->nome,
                ])->filter()->join(' - '));

                return [$turma->id => $label];
            })
            ->toArray();
    }

    public function opcoesDeEscolas(?User $user): array
    {
        $query = Escola::query()->where('ativo', true)->orderBy('nome');

        $escolaIds = $this->idsEscolasVinculadasFormularioAluno($user);

        if (! $this->podeEscolherEscolaAluno($user) && $escolaIds !== []) {
            $query->whereKey($escolaIds);
        }

        return $query->pluck('nome', 'id')->toArray();
    }

    private function opcoesDeTurmasParaRemanejamento(Aluno $aluno, ?User $user): array
    {
        $aluno->loadMissing('turma');

        $query = Turma::query()
            ->with(['serie:id,nome', 'escola:id,nome'])
            ->where('id_escola', (int) $aluno->turma?->id_escola)
            ->where('id_serie', (int) $aluno->turma?->id_serie)
            ->whereKeyNot((int) $aluno->id_turma)
            ->orderBy('nome');

        $this->aplicarFiltroTurmasFormularioAluno($query, $user);

        return $query->get()
            ->mapWithKeys(function (Turma $turma) {
                return [$turma->id => trim(collect([
                    $turma->serie?->nome,
                    $turma->nome,
                    $turma->turno,
                ])->filter()->join(' - '))];
            })
            ->toArray();
    }

    private function opcoesDeTurmasParaContraTurno(Aluno $aluno, ?User $user): array
    {
        $aluno->loadMissing('turma');

        if (! $aluno->turma) {
            return [];
        }

        $query = Turma::query()
            ->with(['serie:id,nome', 'escola:id,nome'])
            ->where('id_escola', (int) $aluno->turma->id_escola)
            ->where('id_serie', (int) $aluno->turma->id_serie)
            ->where('turno', '!=', (string) $aluno->turma->turno)
            ->whereKeyNot((int) $aluno->id_turma)
            ->orderBy('nome');

        $this->aplicarFiltroTurmasFormularioAluno($query, $user);

        return $query->get()
            ->mapWithKeys(fn (Turma $turma): array => [
                (int) $turma->id => trim(collect([
                    $turma->escola?->nome,
                    $turma->serie?->nome,
                    $turma->nome,
                    $turma->turno,
                ])->filter()->join(' - ')),
            ])
            ->all();
    }

    private function opcoesDeTurmasParaContraTurnoPorFormulario(int $turmaAtualId, ?User $user): array
    {
        if ($turmaAtualId <= 0) {
            return [];
        }

        $turmaAtual = Turma::query()->find($turmaAtualId);

        if (! $turmaAtual) {
            return [];
        }

        $query = Turma::query()
            ->with(['serie:id,nome', 'escola:id,nome'])
            ->where('id_escola', (int) $turmaAtual->id_escola)
            ->where('id_serie', (int) $turmaAtual->id_serie)
            ->where('turno', '!=', (string) $turmaAtual->turno)
            ->whereKeyNot((int) $turmaAtual->id)
            ->orderBy('nome');

        $this->aplicarFiltroTurmasFormularioAluno($query, $user);

        return $query->get()
            ->mapWithKeys(fn (Turma $turma): array => [
                (int) $turma->id => trim(collect([
                    $turma->escola?->nome,
                    $turma->serie?->nome,
                    $turma->nome,
                    $turma->turno,
                ])->filter()->join(' - ')),
            ])
            ->all();
    }

    private function opcoesGeraisDeTurmasContraTurno(?User $user): array
    {
        $query = Turma::query()
            ->with(['serie:id,nome', 'escola:id,nome'])
            ->orderBy('nome');

        $this->aplicarFiltroTurmasFormularioAluno($query, $user);

        return $query->get()
            ->mapWithKeys(fn (Turma $turma): array => [
                (int) $turma->id => trim(collect([
                    $turma->escola?->nome,
                    $turma->serie?->nome,
                    $turma->nome,
                    $turma->turno,
                ])->filter()->join(' - ')),
            ])
            ->all();
    }

    private function opcoesDeSeriesPorEscola(int $escolaId, ?User $user): array
    {
        if ($escolaId <= 0) {
            return [];
        }

        $query = Turma::query()
            ->with('serie:id,nome')
            ->where('id_escola', $escolaId)
            ->whereNotNull('id_serie');

        $this->aplicarFiltroTurmasFormularioAluno($query, $user);

        return $query
            ->get()
            ->pluck('serie')
            ->filter(fn (?Serie $serie): bool => $serie !== null)
            ->unique(fn (Serie $serie): int => (int) $serie->id)
            ->sortBy(fn (Serie $serie): string => mb_strtolower((string) $serie->nome))
            ->mapWithKeys(fn (Serie $serie): array => [(int) $serie->id => (string) $serie->nome])
            ->all();
    }

    private function opcoesDeTurmasPorEscolaSerie(int $escolaId, int $serieId, ?User $user): array
    {
        if ($escolaId <= 0 || $serieId <= 0) {
            return [];
        }

        $query = Turma::query()
            ->with('serie:id,nome')
            ->where('id_escola', $escolaId)
            ->where('id_serie', $serieId)
            ->orderBy('nome');

        $this->aplicarFiltroTurmasFormularioAluno($query, $user);

        return $query
            ->get()
            ->mapWithKeys(fn (Turma $turma): array => [
                (int) $turma->id => trim(collect([
                    $turma->serie?->nome,
                    $turma->nome,
                ])->filter()->join(' ')),
            ])
            ->all();
    }

    private function escolaInicialFormularioAluno(?User $user): ?int
    {
        $escolaIds = $this->idsEscolasVinculadasFormularioAluno($user);

        if (count($escolaIds) === 1) {
            return $escolaIds[0];
        }

        if (filled($user?->id_escola) && in_array((int) $user->id_escola, $escolaIds, true)) {
            return (int) $user->id_escola;
        }

        return null;
    }

    private function deveTravarEscolaAluno(?User $user): bool
    {
        return ! $this->podeEscolherEscolaAluno($user)
            && count($this->idsEscolasVinculadasFormularioAluno($user)) === 1;
    }

    private function podeEscolherEscolaAluno(?User $user): bool
    {
        return ($user?->hasRole('Admin') ?? false) || $this->podeEditarEscolaAluno($user);
    }

    private function podeEditarEscolaAluno(?User $user): bool
    {
        return ($user?->hasPermissionTo('Editar Escola do Aluno') ?? false)
            || ($user?->hasPermissionTo('Editar Escola da Turma') ?? false);
    }

    private function idsEscolasVinculadasFormularioAluno(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return collect([$user->id_escola])
            ->merge($user->idsEscolasVinculadas())
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function validarTurmaPermitida(int $turmaId, ?User $user): void
    {
        $query = Turma::query()->whereKey($turmaId);

        $this->aplicarFiltroTurmasFormularioAluno($query, $user);

        if (! $query->exists()) {
            throw ValidationException::withMessages([
                'data.id_turma' => 'Selecione uma turma permitida para o seu acesso.',
            ]);
        }
    }

    private function aplicarFiltroTurmasFormularioAluno(Builder $query, ?User $user): Builder
    {
        if ($this->podeEditarEscolaAluno($user)) {
            return $query;
        }

        return $this->userService->aplicarFiltroTurmasDoUsuario($query, $user);
    }

    private function alunoTemAvaliacoes(Aluno $aluno): bool
    {
        if (array_key_exists('turma_tem_avaliacoes', $aluno->getAttributes())) {
            return (bool) $aluno->getAttribute('turma_tem_avaliacoes');
        }

        $aluno->loadMissing('turma');

        return $aluno->turma?->avaliacoes()->exists() ?? false;
    }

    private function contraTurnoAtivo(Aluno $aluno): ?Aluno
    {
        return Aluno::query()
            ->where('cgm_contra_turno_ativo', $aluno->cgm)
            ->where('tipo_vinculo', Aluno::TIPO_VINCULO_CONTRA_TURNO)
            ->where('status', Aluno::STATUS_MATRICULADO)
            ->first();
    }

    private function turmaContraTurnoAtivaId(Aluno $aluno): ?int
    {
        return $this->contraTurnoAtivo($aluno)?->id_turma;
    }

    private function mensagemResultadoContraTurnoMassa(int $sucessos, array $falhas): string
    {
        $mensagem = $sucessos.' aluno(s) processado(s) com sucesso.';

        if ($falhas === []) {
            return $mensagem;
        }

        return $mensagem.' Falhas: '.implode(' | ', $falhas);
    }

    private function aplicarFiltroPendenciaSemProfessor(Builder $query): void
    {
        $query->whereExists(function ($pendencias): void {
            $pendencias
                ->selectRaw('1')
                ->from('avaliacao_turma as at')
                ->join('avaliacoes as av', 'av.id', '=', 'at.avaliacao_id')
                ->join('avaliacao_pauta as ap', 'ap.avaliacao_id', '=', 'at.avaliacao_id')
                ->join('pautas as p', 'p.id', '=', 'ap.pauta_id')
                ->join('turmas as t', 't.id', '=', 'at.turma_id')
                ->whereColumn('at.turma_id', 'alunos.id_turma')
                ->where('av.status', Avaliacao::STATUS_ATIVA)
                ->where('p.status', true)
                ->whereNotNull('p.componente_curricular_id')
                ->where(function ($series): void {
                    $series
                        ->whereNull('p.serie_id')
                        ->orWhereColumn('p.serie_id', 't.id_serie');
                })
                ->whereNotExists(function ($professores): void {
                    $professores
                        ->selectRaw('1')
                        ->from('turma_componente_professor as tcp')
                        ->whereColumn('tcp.turma_id', 'at.turma_id')
                        ->whereColumn('tcp.componente_curricular_id', 'p.componente_curricular_id')
                        ->whereNotNull('tcp.professor_id');
                })
                ->whereNotExists(function ($respostas): void {
                    $respostas
                        ->selectRaw('1')
                        ->from('avaliacao_respostas as ar')
                        ->whereColumn('ar.avaliacao_id', 'at.avaliacao_id')
                        ->whereColumn('ar.pauta_id', 'p.id')
                        ->whereColumn('ar.turma_id', 'at.turma_id')
                        ->whereColumn('ar.aluno_id', 'alunos.id')
                        ->whereNotNull('ar.alternativa_id');
                });
        });
    }
}
