<?php

namespace App\Services;

use App\Filament\Admin\Clusters\Aluno\Resources\Alunos\AlunoResource;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use App\Services\UserService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use App\Models\Turma;
use App\Models\Professor;

class TurmaService
{

    public function __construct(
        protected UserService $userService
    ) {}

    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) use ($user) {

                $this->userService->aplicarFiltroPorEscolaDoUsuarioEmTurma($query, $user);

                $query
                    ->with([
                        'escola:id,nome',
                        'serie:id,nome'
                    ])
                    ->withCount('alunos');
            })
            ->paginated([5, 10, 25, 50, 100])
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
                ->formatStateUsing(fn(string $state) => match ($state) {
                    'manha' => 'Manhã',
                    'tarde' => 'Tarde',
                    'noite' => 'Noite',
                    'integral' => 'Integral',
                    default => ucfirst($state),
                })
                ->color(fn(string $state) => match ($state) {
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
                ->fillForm(function (Turma $record, array $data): array {
                    $record->load([
                        'serie.componentesCurriculares',
                        'componentes',
                    ]);

                    $componentesDaSerie = $record->serie?->componentesCurriculares ?? collect();

                    $data['componentes'] = $componentesDaSerie->map(function ($componente) use ($record) {
                        $pivot = $record->componentes->firstWhere('id', $componente->id);

                        return [
                            'componente_curricular_id' => $componente->id,
                            'componente_nome'          => $componente->nome,
                            'professor_id'             => $pivot?->pivot->professor_id,
                            'tem_professor'            => $pivot ? (bool) $pivot->pivot->tem_professor : false,
                        ];
                    })->toArray();

                    return $data;
                })
                ->using(function (Turma $record, array $data): Turma {
                    $componentes = $data['componentes'] ?? [];
                    unset($data['componentes']);

                    $record->update($data);

                    $sync = [];
                    foreach ($componentes as $item) {
                        if (!empty($item['componente_curricular_id'])) {
                            $sync[$item['componente_curricular_id']] = [
                                'professor_id'  => $item['professor_id'] ?? null,
                                'tem_professor' => $item['tem_professor'] ?? false,
                            ];
                        }
                    }

                    $record->componentes()->sync($sync);

                    return $record;
                }),

            DeleteAction::make()
                ->before(function ($record, $action) {
                    if ($record->alunos()->exists()) {
                        Notification::make()
                            ->title('Não é possível excluir esta turma.')
                            ->body('Existem alunos vinculados a ela.')
                            ->danger()
                            ->send();

                        $action->cancel();
                    }
                })
                ->visible(fn() => $this->userService->podeExcluirTurmas(Auth::user())),
        ];
    }

    private function filtrosTabela(): array
    {
        /** @var \App\Models\User */
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
                                ->title('Ação cancelada.')
                                ->body('Não é possivel excluir turmas com alunos vinculados.')
                                ->danger()
                                ->send();

                            $action->halt();
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
        /** @var \App\Models\User */
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
                            ->options(\App\Models\Serie::pluck('nome', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (!$state) {
                                    $set('componentes', []);
                                    return;
                                }

                                $serie = \App\Models\Serie::with('componentesCurriculares')->find($state);
                                if (!$serie) {
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
                            ->placeholder('Selecione a série')
                            ->disabled(
                                function ($context) use ($user) {
                                    return $context === 'edit' && ! $user->hasPermissionTo('Editar Dados da Turma');
                                }
                            )
                            ->columnSpanFull(),

                        TextInput::make('nome')
                            ->label('Letra da Turma')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: A, B, C')
                            ->hint('Apenas a letra/identificador da turma')
                            ->disabled(
                                function ($context) use ($user) {
                                    return $context === 'edit' && ! $user->hasPermissionTo('Editar Dados da Turma');
                                }
                            ),

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
                            ->disabled(
                                function ($context) use ($user) {
                                    return $context === 'edit' && ! $user->hasPermissionTo('Editar Dados da Turma');
                                }
                            ),


                        Hidden::make('codigo')
                            ->default(fn() => 'TUR' . str_pad(Turma::max('id') + 1, 3, '0', STR_PAD_LEFT)),
                    ])
                    ->columns(2),

                Section::make('Professores por Componente')
                    ->schema([
                        // Placeholder::make('aviso')
                        //     ->label('')
                        //     ->content('Selecione a escola e a série para carregar os componentes curriculares')
                        //     ->visible(fn(Get $get) => !$get('id_serie') || !$get('id_escola')),

                        Repeater::make('componentes')
                            ->label('')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('componente_nome')
                                            ->label('Componente Curricular')
                                            ->disabled()
                                            ->dehydrated(false),

                                        Select::make('professor_id')
                                            ->label('Professor')
                                            ->options(function (Get $get) {
                                                $escolaId = $get('../../id_escola');
                                                if (!$escolaId) {
                                                    return [];
                                                }

                                                // Exclui professores com função administrativa
                                                return Professor::where('id_escola', $escolaId)
                                                    ->whereNull('funcao_administrativa_id')
                                                    ->pluck('nome', 'id')
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->placeholder('Selecione o professor')
                                            ->disabled(fn(Get $get) => $get('tem_professor'))
                                            ->dehydrated(fn(Get $get) => !$get('tem_professor')),

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
                            ->visible(fn(Get $get) => $get('id_serie') && $get('id_escola'))
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()

                    ->visible(fn(Get $get) => $get('id_serie') && $get('id_escola')),

            ]);
    }

    public function aplicarCodigo(array $data): array
    {
        $letra = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($data['turma'] ?? '')));

        if (blank($letra)) {
            return $data;
        }

        $data['turma']  = $letra;
        $data['codigo'] = 'TR' . $letra;

        // $data['codigo'] = Str::substr($data['codigo'], 0, 3);

        return $data;
    }

    public function forcarVinculoComEscola(array $data, ?User $auth): array
    {
        if ($auth && filled($auth->id_escola)) {
            $data['id_escola'] = $auth->id_escola;
        }

        return $data;
    }
}
