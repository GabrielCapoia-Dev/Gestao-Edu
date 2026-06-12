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
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
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
use Filament\Tables\Columns\TextColumn;
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

                    TextInput::make('cgm')
                        ->label('CGM')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Set $set, ?string $operation = null): void {
                            $cgm = Aluno::normalizarCgm($state);
                            $set('cgm', $cgm);

                            if ($operation !== 'create') {
                                return;
                            }

                            $set('cgm_consultado', $cgm !== '');
                            $set('cgm_encontrado_aluno_id', null);

                            if ($cgm === '') {
                                return;
                            }

                            $alunoExistente = $this->alunoPorCgmParaFormulario($cgm);

                            if (! $alunoExistente) {
                                return;
                            }

                            $set('cgm_encontrado_aluno_id', (int) $alunoExistente->id);
                            $set('nome', $alunoExistente->nome);
                            $set('data_nascimento', $alunoExistente->data_nascimento?->format('Y-m-d'));
                            $set('sexo', $alunoExistente->sexo);
                            $set('data_matricula', $alunoExistente->data_matricula?->format('Y-m-d'));
                        })
                        ->helperText(fn (Get $get, ?string $operation = null): ?string => $operation === 'create'
                            ? $this->textoAjudaCgmCadastro($get)
                            : null),

                    TextInput::make('nome')
                        ->label('Nome')
                        ->required()
                        ->maxLength(255)
                        ->disabled(fn (Get $get, ?string $operation = null): bool => $operation === 'create'
                            && ! $this->formularioAlunoLiberadoAposCgm($get)),

                    DatePicker::make('data_nascimento')
                        ->label('Data de Nascimento')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->disabled(fn (Get $get, ?string $operation = null): bool => $operation === 'create'
                            && ! $this->formularioAlunoLiberadoAposCgm($get)),

                    Select::make('sexo')
                        ->label('Sexo')
                        ->options([
                            'F' => 'Feminino',
                            'M' => 'Masculino',
                        ])
                        ->native(false)
                        ->disabled(fn (Get $get, ?string $operation = null): bool => $operation === 'create'
                            && ! $this->formularioAlunoLiberadoAposCgm($get)),

                    DatePicker::make('data_matricula')
                        ->label('Data de Matricula')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->disabled(fn (Get $get, ?string $operation = null): bool => $operation === 'create'
                            && ! $this->formularioAlunoLiberadoAposCgm($get)),

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
                        })
                        ->disabled(fn (Get $get, ?string $operation = null): bool => ($operation === 'create'
                            && ! $this->formularioAlunoLiberadoAposCgm($get))
                            || $this->deveTravarEscolaAluno($user))
                        ->dehydrated(false)
                        ->columnSpanFull(),

                    Select::make('id_serie')
                        ->label('Série')
                        ->options(fn (Get $get): array => $this->opcoesDeSeriesPorEscola((int) ($get('id_escola') ?? 0), $user))
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('id_turma', null);
                        })
                        ->disabled(fn (Get $get, ?string $operation = null): bool => ($operation === 'create'
                            && ! $this->formularioAlunoLiberadoAposCgm($get))
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
                        ->disabled(fn (Get $get, ?string $operation = null): bool => ($operation === 'create'
                            && ! $this->formularioAlunoLiberadoAposCgm($get))
                            || blank($get('id_escola'))
                            || blank($get('id_serie')))
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

                    $labelQuery
                        ->whereIn('status', $status)
                        ->orWhereIn('sexo', $sexos);
                }),
            ])
            ->searchPlaceholder('Buscar por nome, CGM, status, turma, serie ou escola')
            ->columns($this->colunasTabela())
            ->filters($this->filtrosTabela($user))
            ->recordActions($this->acoesTabela($user))
            ->toolbarActions($this->acoesEmMassa($user))
            ->defaultSort('nome')
            ->striped();
    }

    private function colunasTabela(): array
    {
        return [
            TextColumn::make('nome')
                ->label('Nome')
                ->searchable()
                ->sortable()
                ->copyable()
                ->wrap(),

            TextColumn::make('cgm')
                ->label('CGM')
                ->searchable()
                ->sortable()
                ->copyable(),

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
                    default => 'gray',
                })
                ->sortable(),

            TextColumn::make('data_nascimento')
                ->label('Data de Nascimento')
                ->date('d/m/Y')
                ->sortable()
                ->copyable(),

            TextColumn::make('sexo')
                ->label('Sexo')
                ->formatStateUsing(fn (?string $state): string => match ($state) {
                    'F' => 'Feminino',
                    'M' => 'Masculino',
                    default => (string) $state,
                })
                ->badge()
                ->toggleable(),

            TextColumn::make('data_matricula')
                ->label('Data de Matricula')
                ->date('d/m/Y')
                ->sortable()
                ->toggleable(),

            TextColumn::make('turma.serie.nome')
                ->searchable()
                ->label('Série')
                ->sortable()
                ->toggleable(),

            TextColumn::make('turma.nome')
                ->searchable()
                ->label('Turma')
                ->sortable()
                ->badge(),

            TextColumn::make('turma.escola.nome')
                ->searchable()
                ->label('Escola')
                ->sortable()
                ->toggleable(),
        ];
    }

    private function filtrosTabela(?User $user): array
    {
        return [
            SelectFilter::make('id_serie')
                ->label('Série')
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
        return [
            Action::make('remanejar')
                ->label('Remanejar')
                ->icon('heroicon-o-arrows-right-left')
                ->color('warning')
                ->visible(fn (Aluno $record): bool => ! $this->professorEstaBloqueado($user)
                    && ($record->estaMatriculado() || $record->estaPendente())
                    && ($user?->hasPermissionLike('realizar remanejamento de aluno') ?? false))
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
                ->visible(fn (Aluno $record): bool => $this->alunoTemAvaliacoes($record)
                    && ($this->professorEstaRestritoAoAluno($user, $record)
                    || (($user?->hasPermissionLike('realizar transferencia de aluno') ?? false)
                        || ($user?->hasPermissionLike('realizar tranferencia de aluno') ?? false)
                        || ($user?->hasPermissionLike('gerar parecer de transferencia') ?? false)))),

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
                        'status' => $record->status,
                    ];
                })
                ->using(function (Aluno $record, array $data) use ($user): Aluno {
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

                    return $record;
                })
                ->visible(fn (Aluno $record) => ! $this->professorEstaBloqueado($user)
                    && $record->estaMatriculado()
                    && $this->userService->podeEditarAlunos($user)),

            DeleteAction::make()
                ->visible(fn (Aluno $record) => ! $this->professorEstaBloqueado($user)
                    && $record->estaMatriculado()
                    && $this->userService->podeExcluirAlunos($user)),
        ];
    }

    private function acoesEmMassa(?User $user): array
    {
        return [
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

    private function textoAjudaCgmCadastro(Get $get): string
    {
        if (! $this->formularioAlunoLiberadoAposCgm($get)) {
            return 'Informe o CGM para liberar os demais campos.';
        }

        return filled($get('cgm_encontrado_aluno_id'))
            ? 'Aluno encontrado no sistema. Confira os dados e selecione serie e turma de destino.'
            : 'CGM nao encontrado. Preencha os dados do novo aluno.';
    }

    private function alunoPorCgmParaFormulario(string $cgm): ?Aluno
    {
        return Aluno::query()
            ->where('cgm', Aluno::normalizarCgm($cgm))
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

        if (! $user?->hasRole('Admin') && filled($user?->id_escola) && ! $this->podeEditarEscolaAluno($user)) {
            $query->whereKey($user->id_escola);
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
        return filled($user?->id_escola) ? (int) $user->id_escola : null;
    }

    private function deveTravarEscolaAluno(?User $user): bool
    {
        return filled($user?->id_escola) && ! $this->podeEditarEscolaAluno($user);
    }

    private function podeEditarEscolaAluno(?User $user): bool
    {
        return ($user?->hasPermissionTo('Editar Escola do Aluno') ?? false)
            || ($user?->hasPermissionTo('Editar Escola da Turma') ?? false);
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
