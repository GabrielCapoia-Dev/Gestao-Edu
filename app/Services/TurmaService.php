<?php

namespace App\Services;

use App\Filament\Admin\Actions\ExportSelectedRecordsBulkAction;
use App\Models\Aluno;
use App\Models\Escola;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TurmaService
{
    public function __construct(
        protected UserService $userService,
        protected PessoaScopeService $pessoaScopeService,
    ) {}

    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): void {
                // Fluxo: o escopo da listagem vem da policy; aqui a tabela carrega escola/serie/alunos para evitar consultas repetidas nas colunas.
                $query->with([
                    'escola:id,nome',
                    'serie:id,nome',
                ])->withCount('alunos');
            })
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(5)
            ->searchable([
                'codigo',
                'turno',
                fn (Builder $query, string $search): Builder => $query->whereIn(
                    'turno',
                    collect([
                        'manha' => 'Manhã',
                        'tarde' => 'Tarde',
                        'noite' => 'Noite',
                        'integral' => 'Integral',
                    ])
                        ->filter(fn (string $label): bool => str_contains(
                            Str::lower(Str::ascii($label)),
                            Str::lower(Str::ascii($search))
                        ))
                        ->keys()
                        ->all()
                ),
            ])
            ->searchPlaceholder('Buscar por escola, série, turma, código ou turno')
            ->columns($this->colunasTabela())
            ->recordActions($this->acoesTabela($user))
            ->toolbarActions($this->acoesEmMassa($user))
            ->filters($this->filtrosTabela())
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    public function colunasTabela(): array
    {
        return [
            TextColumn::make('escola.nome')
                ->label('Escola')
                ->searchable()
                ->sortable()
                ->wrap(),

            TextColumn::make('serie.nome')
                ->label('Série')
                ->searchable()
                ->sortable(),

            TextColumn::make('nome')
                ->label('Turma')
                ->searchable()
                ->sortable(),

            TextColumn::make('turno')
                ->label('Turno')
                ->badge()
                ->formatStateUsing(fn (string $state) => match ($state) {
                    'manha' => 'Manhã',
                    'tarde' => 'Tarde',
                    'noite' => 'Noite',
                    'integral' => 'Integral',
                    default => ucfirst($state),
                })
                ->color(fn (string $state) => match ($state) {
                    'manha' => 'info',
                    'tarde' => 'warning',
                    'noite' => 'gray',
                    'integral' => 'success',
                    default => 'secondary',
                })
                ->sortable(),

            TextColumn::make('alunos_count')
                ->label('Alunos')
                ->numeric()
                ->sortable()
                ->alignCenter(),

            TextColumn::make('created_at')
                ->label('Criado em')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    public function acoesTabela(?User $user): array
    {
        return [
            EditAction::make()
                ->modal()
                ->slideOver()
                ->fillForm(function (Turma $record, array $data): array {
                    // Fluxo: ao editar, a turma carrega a serie e seus componentes; cada componente vira uma linha do repeater com professor atual ou marcador "sem professor".
                    $record->load([
                        'serie.componentesCurriculares',
                        'componentes',
                    ]);

                    $data['id_escola'] = $record->id_escola;
                    $data['id_serie'] = $record->id_serie;
                    $data['nome'] = $record->nome;
                    $data['turno'] = $record->turno;
                    $data['codigo'] = $record->codigo;

                    $data['componentes'] = static::componentesFormulario($record);

                    return $data;
                })
                ->using(function (Turma $record, array $data): Turma {
                    $data = $this->validarEscolaNoEscopo($data, Auth::user(), $record);
                    $componentes = $data['componentes'] ?? [];
                    unset($data['componentes']);

                    $record->update($data);
                    $this->salvarComponentes($record, $componentes);

                    return $record;
                }),

            Action::make('ver_alunos')
                ->label('Ver Alunos')
                ->icon('heroicon-o-academic-cap')
                ->url(fn (Turma $record) => route('filament.admin.resources.alunos.index', [
                    'turma' => $record->id,
                ]))
                // Impacto: este atalho depende do filtro da tela de alunos reconhecer o parametro turma; alterar rota/parametro quebra a navegacao entre turma e alunos.
                ->visible(fn () => $this->userService->podeVisualizarAlunos(Auth::user())),

            DeleteAction::make()
                ->before(function (Turma $record, $action) {
                    if ($record->alunos()->exists()) {
                        // Impacto: excluir turma com alunos deixaria alunos orfaos e quebraria relatorios/filtros por turma.
                        Notification::make()
                            ->title('Ação bloqueada')
                            ->body('Não é possível excluir turma com alunos vinculados.')
                            ->danger()
                            ->send();

                        $action->cancel();
                    }
                })
                ->visible(fn () => $this->userService->podeExcluirTurmas(Auth::user())),
        ];
    }

    private function filtrosTabela(): array
    {
        /** @var User */
        $user = Auth::user();

        return [
            SelectFilter::make('id_serie')
                ->label('Série')
                ->relationship('serie', 'nome')
                ->searchable(),

            SelectFilter::make('id_escola')
                ->label('Escola')
                ->relationship('escola', 'nome', modifyQueryUsing: fn (Builder $query): Builder => $query
                    ->where('ativo', true)
                    ->orderBy('nome'))
                ->searchable()
                ->visible(function () use ($user) {
                    return $user && Gate::forUser($user)->allows('filterBySchool', Turma::class);
                }),

            SelectFilter::make('turno')
                ->label('Turno')
                ->options([
                    'manha' => 'Manhã',
                    'tarde' => 'Tarde',
                    'noite' => 'Noite',
                    'integral' => 'Integral',
                ]),

            Filter::make('com_alunos_pendentes')
                ->label('Com alunos pendentes')
                ->query(fn (Builder $query): Builder => $this->filtrarComAlunosPendentes($query)),
        ];
    }

    public function filtrarComAlunosPendentes(Builder $query): Builder
    {
        return $query->whereHas(
            'alunos',
            fn (Builder $alunos): Builder => $alunos->where('status', Aluno::STATUS_PENDENTE),
        );
    }

    private function acoesEmMassa(?User $user): array
    {
        return [
            ExportSelectedRecordsBulkAction::make(
                'turmas_selecionadas',
                'XLSX de turmas selecionadas',
                'turmas.bulk_action',
            ),

            DeleteBulkAction::make()
                ->before(function ($records, $action) {
                    foreach ($records as $record) {
                        if ($record->alunos()->exists()) {
                            Notification::make()
                                ->title('Ação bloqueada')
                                ->body('Não é possível excluir turmas com alunos vinculados.')
                                ->danger()
                                ->send();

                            $action->cancel();
                            break;
                        }
                    }
                })
                ->requiresConfirmation()
                ->visible(function ($records) use ($user) {
                    return $user && Gate::forUser($user)->allows('deleteBulk', Turma::class);
                }),
        ];
    }

    public static function configurarFormulario(Schema $schema): Schema
    {
        /** @var User */
        $user = Auth::user();

        return $schema
            ->components([
                Section::make('Dados da Turma')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('id_escola')
                            ->label('Escola')
                            ->relationship('escola', 'nome', modifyQueryUsing: function (Builder $query) use ($user): Builder {
                                $query = app(PessoaScopeService::class)
                                    ->applyEscolaScope($query, $user, 'escolas.id');

                                return $query
                                    ->where('ativo', true)
                                    ->orderBy('nome');
                            })
                            ->getOptionLabelUsing(function ($value) use ($user): ?string {
                                $query = app(PessoaScopeService::class)
                                    ->applyEscolaScope(Escola::query(), $user, 'escolas.id');

                                return $query->whereKey($value)->value('nome');
                            })
                            ->searchable()
                            ->required()
                            ->live()
                            ->placeholder('Selecione a escola')
                            ->disabled(function ($context) use ($user) {
                                return $context === 'edit' && ! Gate::forUser($user)->allows('editSchool', Turma::class);
                            })
                            ->columnSpanFull(),

                        Select::make('id_serie')
                            ->label('Série')
                            ->options(fn (): array => Serie::query()->orderBy('nome')->pluck('nome', 'id')->toArray())
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                // Fluxo: ao escolher a serie, o formulario busca os componentes curriculares da serie e monta automaticamente as linhas de professor por componente.
                                if (! $state) {
                                    $set('componentes', []);

                                    return;
                                }

                                $serie = Serie::with('componentesCurriculares')->find($state);

                                if (! $serie) {
                                    $set('componentes', []);

                                    return;
                                }

                                $componentes = $serie->componentesCurriculares->map(function ($componente) {
                                    return [
                                        'componente_curricular_id' => $componente->id,
                                        'componente_nome' => $componente->nome,
                                        'professor_id' => null,
                                        'sem_professor' => true,
                                    ];
                                })->toArray();

                                $set('componentes', $componentes);
                            })
                            ->placeholder('Selecione a Série')
                            ->disabled(function ($context) use ($user) {
                                return $context === 'edit' && ! Gate::forUser($user)->allows('editData', Turma::class);
                            })
                            ->columnSpanFull(),

                        TextInput::make('nome')
                            ->label('Letra da Turma')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: A, B, C')
                            ->hint('Apenas a letra/identificador da turma')
                            ->disabled(function ($context) use ($user) {
                                return $context === 'edit' && ! Gate::forUser($user)->allows('editData', Turma::class);
                            }),

                        Select::make('turno')
                            ->label('Turno')
                            ->options([
                                'manha' => 'Manhã',
                                'tarde' => 'Tarde',
                                'noite' => 'Noite',
                                'integral' => 'Integral',
                            ])
                            ->required()
                            ->placeholder('Selecione o turno')
                            ->disabled(function ($context) use ($user) {
                                return $context === 'edit' && ! Gate::forUser($user)->allows('editData', Turma::class);
                            }),

                        Hidden::make('codigo')
                            ->dehydrated(false),
                    ])
                    ->columns(2),

                Section::make('Professores por Componente')
                    ->schema([
                        Repeater::make('componentes')
                            ->label('')
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'lg' => 12,
                                ])
                                    ->schema([
                                        Textarea::make('componente_nome')
                                            ->label('Componente Curricular')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->autosize()
                                            ->extraInputAttributes([
                                                'style' => '
                            background: transparent !important;
                            border: none !important;
                            box-shadow: none !important;
                            font-weight: 500;
                            font-size: 0.95rem;
                            color: var(--gray-900) !important;
                            padding-left: 0 !important;
                            cursor: default;
                        ',
                                            ])
                                            ->extraAttributes([
                                                'style' => '
                            border-left: 3px solid var(--primary-500);
                            padding-left: 0.5rem;
                            border-radius: 0;
                        ',
                                            ])
                                            ->columnSpan([
                                                'default' => 1,
                                                'lg' => 3,
                                            ]),

                                        Select::make('professor_id')
                                            ->label('Professor')
                                            ->options(function (Get $get) {
                                                // Impacto: professores disponiveis sao filtrados pela escola da turma; remover esse filtro permite vincular professor de outra unidade.
                                                $escolaId = $get('../../id_escola');
                                                if (! $escolaId) {
                                                    return [];
                                                }

                                                return static::professoresOptionsParaTurma($escolaId);
                                            })
                                            ->getOptionLabelUsing(
                                                fn ($value): ?string => Professor::query()
                                                    ->whereKey($value)
                                                    ->first(['id', 'turno', 'matricula', 'nome'])
                                                    ?->rotuloParaVinculoTurma()
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->placeholder('Selecione o professor')
                                            ->afterStateUpdated(function ($state, Set $set) {
                                                $set('sem_professor', blank($state));
                                            })
                                            ->columnSpan([
                                                'default' => 1,
                                                'lg' => 9,
                                            ]),

                                        Checkbox::make('sem_professor')
                                            ->label('Não tem Professor?')
                                            ->default(false)
                                            ->live()
                                            ->afterStateUpdated(function ($state, Set $set) {
                                                if ($state) {
                                                    $set('professor_id', null);
                                                }
                                            })
                                            ->columnSpan([
                                                'default' => 1,
                                                'lg' => 12,
                                            ]),

                                        Hidden::make('componente_curricular_id'),
                                    ]),
                            ])
                            ->visible(fn (Get $get) => $get('id_serie') && $get('id_escola'))
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->visible(fn (Get $get) => $get('id_serie') && $get('id_escola')),
            ]);
    }

    public static function professoresOptionsParaTurma(int|string|null $escolaId): array
    {
        if (! $escolaId) {
            return [];
        }

        return Professor::where('id_escola', $escolaId)
            ->where('ativo', true)
            ->disponivelParaComponente()
            ->orderBy('turno')
            ->orderBy('matricula')
            ->orderBy('nome')
            ->get(['id', 'turno', 'matricula', 'nome'])
            ->mapWithKeys(fn (Professor $professor): array => [
                $professor->id => $professor->rotuloParaVinculoTurma(),
            ])
            ->toArray();
    }

    public static function componentesFormulario(Turma $record): array
    {
        $record->loadMissing([
            'serie.componentesCurriculares',
            'componentes',
        ]);

        $componentesDaSerie = $record->serie?->componentesCurriculares ?? collect();

        return $componentesDaSerie->map(function ($componente) use ($record): array {
            $pivot = $record->componentes->firstWhere('id', $componente->id);
            $professorId = $pivot?->pivot->professor_id;

            return [
                'componente_curricular_id' => $componente->id,
                'componente_nome' => $componente->nome,
                'professor_id' => $professorId,
                'sem_professor' => blank($professorId),
            ];
        })->toArray();
    }

    public function aplicarCodigo(array $data): array
    {
        // Impacto: normaliza a letra/codigo antes de salvar; mudar este padrao afeta buscas, exibicao e possiveis integracoes que dependem do codigo TR{letra}.
        $letra = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($data['turma'] ?? '')));

        if (blank($letra)) {
            return $data;
        }

        $data['turma'] = $letra;
        $data['codigo'] = 'TR'.$letra;

        return $data;
    }

    public function forcarVinculoComEscola(array $data, ?User $auth): array
    {
        return $this->validarEscolaNoEscopo($data, $auth);
    }

    public function validarEscolaNoEscopo(array $data, ?User $user, ?Turma $turma = null): array
    {
        $escolaId = (int) ($data['id_escola'] ?? $turma?->id_escola ?? 0);
        $mesmaEscola = $turma && (int) $turma->id_escola === $escolaId;
        $escolaValida = Escola::query()
            ->whereKey($escolaId)
            ->when(! $mesmaEscola, fn (Builder $query): Builder => $query->where('ativo', true))
            ->exists();

        if (! $escolaValida || ! $this->pessoaScopeService->canAccessEscola($user, $escolaId)) {
            throw ValidationException::withMessages([
                'id_escola' => 'A escola selecionada não pertence ao seu escopo de acesso.',
            ]);
        }

        $data['id_escola'] = $escolaId;

        return $data;
    }

    public function salvarComponentes(Turma $turma, array $componentes): void
    {
        $componentesNormalizados = collect($componentes)
            ->filter(fn (array $componente): bool => isset($componente['componente_curricular_id']))
            ->map(function (array $componente): array {
                $semProfessor = $this->componenteSemProfessor($componente);
                $professorId = (! $semProfessor && filled($componente['professor_id'] ?? null))
                    ? (int) $componente['professor_id']
                    : null;

                return [
                    'componente_curricular_id' => (int) $componente['componente_curricular_id'],
                    'professor_id' => $professorId,
                ];
            })
            ->values();

        if ($componentesNormalizados->isEmpty()) {
            return;
        }

        $componentesIds = $componentesNormalizados
            ->pluck('componente_curricular_id')
            ->all();

        $professoresAnteriores = TurmaComponenteProfessor::query()
            ->where('turma_id', $turma->id)
            ->whereIn('componente_curricular_id', $componentesIds)
            ->pluck('professor_id')
            ->all();

        $componentesNormalizados->each(function (array $componente) use ($turma): void {
            TurmaComponenteProfessor::query()->updateOrCreate(
                [
                    'turma_id' => $turma->id,
                    'componente_curricular_id' => $componente['componente_curricular_id'],
                ],
                [
                    'professor_id' => $componente['professor_id'],
                    'tem_professor' => filled($componente['professor_id']),
                ],
            );
        });

        $professoresAtualizados = $componentesNormalizados
            ->pluck('professor_id')
            ->all();

        $professoresParaSincronizar = collect($professoresAnteriores)
            ->merge($professoresAtualizados)
            ->filter()
            ->map(fn ($professorId): int => (int) $professorId)
            ->unique()
            ->values()
            ->all();

        if ($professoresParaSincronizar !== []) {
            app(ProfessorEscolaVinculoService::class)->sincronizarPorProfessores($professoresParaSincronizar);
        }
    }

    /**
     * @param array<string, mixed> $componente
     */
    private function componenteSemProfessor(array $componente): bool
    {
        if (array_key_exists('sem_professor', $componente)) {
            return (bool) $componente['sem_professor'];
        }

        return (bool) ($componente['tem_professor'] ?? false);
    }
}
