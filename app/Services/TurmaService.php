<?php

namespace App\Services;

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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TurmaService
{
    public function __construct(
        protected UserService $userService
    ) {}

    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) use ($user) {
                // Fluxo: a tabela de turmas primeiro aplica o escopo do usuario, depois carrega escola/serie/alunos para evitar consultas repetidas nas colunas.
                $this->userService->aplicarFiltroPorEscolaDoUsuarioEmTurma($query, $user);

                $query->with([
                    'escola:id,nome',
                    'serie:id,nome',
                ])->withCount('alunos');
            })
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(5)
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

                    $componentesDaSerie = $record->serie?->componentesCurriculares ?? collect();

                    $data['componentes'] = $componentesDaSerie->map(function ($componente) use ($record) {
                        $pivot = $record->componentes->firstWhere('id', $componente->id);

                        return [
                            'componente_curricular_id' => $componente->id,
                            'componente_nome' => $componente->nome,
                            'professor_id' => $pivot?->pivot->professor_id,
                            'tem_professor' => $pivot ? (bool) $pivot->pivot->tem_professor : false,
                        ];
                    })->toArray();

                    return $data;
                })
                ->using(function (Turma $record, array $data): Turma {
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
                ->searchable()
                ->preload(),

            SelectFilter::make('id_escola')
                ->label('Escola')
                ->relationship('escola', 'nome')
                ->searchable()
                ->preload()
                ->visible(function () use ($user) {
                    return $user->hasPermissionTo('Filtrar Turmas por Escola');
                }),

            SelectFilter::make('turno')
                ->label('Turno')
                ->options([
                    'manha' => 'Manhã',
                    'tarde' => 'Tarde',
                    'noite' => 'Noite',
                    'integral' => 'Integral',
                ]),
        ];
    }

    private function acoesEmMassa(?User $user): array
    {
        return [
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
                    return $user->hasPermissionTo('Excluir Turmas em Massa');
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
                            ->relationship('escola', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->placeholder('Selecione a escola')
                            ->disabled(function ($context) use ($user) {
                                return $context === 'edit' && ! $user->hasPermissionTo('Editar Escola da Turma');
                            })
                            ->columnSpanFull(),

                        Select::make('id_serie')
                            ->label('Série')
                            ->options(Serie::pluck('nome', 'id'))
                            ->searchable()
                            ->preload()
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
                                    ];
                                })->toArray();

                                $set('componentes', $componentes);
                            })
                            ->placeholder('Selecione a Série')
                            ->disabled(function ($context) use ($user) {
                                return $context === 'edit' && ! $user->hasPermissionTo('Editar Dados da Turma');
                            })
                            ->columnSpanFull(),

                        TextInput::make('nome')
                            ->label('Letra da Turma')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: A, B, C')
                            ->hint('Apenas a letra/identificador da turma')
                            ->disabled(function ($context) use ($user) {
                                return $context === 'edit' && ! $user->hasPermissionTo('Editar Dados da Turma');
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
                                return $context === 'edit' && ! $user->hasPermissionTo('Editar Dados da Turma');
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
                                Grid::make(3)
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
                                            ]),

                                        Select::make('professor_id')
                                            ->label('Professor')
                                            ->options(function (Get $get) {
                                                // Impacto: professores disponiveis sao filtrados pela escola da turma; remover esse filtro permite vincular professor de outra unidade.
                                                $escolaId = $get('../../id_escola');
                                                if (! $escolaId) {
                                                    return [];
                                                }

                                                return Professor::where('id_escola', $escolaId)
                                                    ->whereNull('funcao_administrativa_id')
                                                    ->pluck('nome', 'id')
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->placeholder('Selecione o professor')
                                            ->disabled(fn (Get $get) => $get('tem_professor'))
                                            ->dehydrated(fn (Get $get) => ! $get('tem_professor')),

                                        Checkbox::make('tem_professor')
                                            ->label('Não tem Professor?')
                                            ->default(false)
                                            ->live()
                                            ->afterStateUpdated(function ($state, Set $set) {
                                                if ($state) {
                                                    $set('professor_id', null);
                                                }
                                            }),

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
        // Impacto: usuario vinculado a escola nao pode criar turma em outra unidade; remover isso quebra isolamento entre escolas.
        if ($auth && filled($auth->id_escola)) {
            $data['id_escola'] = $auth->id_escola;
        }

        return $data;
    }

    public function salvarComponentes(Turma $turma, array $componentes): void
    {
        $componentesNormalizados = collect($componentes)
            ->filter(fn (array $componente): bool => isset($componente['componente_curricular_id']))
            ->map(function (array $componente): array {
                $professorId = filled($componente['professor_id'] ?? null)
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
}
