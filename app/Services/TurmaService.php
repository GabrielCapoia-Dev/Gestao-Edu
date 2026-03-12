<?php

namespace App\Services;

use App\Filament\Clusters\AlunoCluster\Resources\AlunoResource;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;
use App\Models\Escola;
use App\Models\IgnoredUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Services\UserService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;

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

                $query->withCount('alunos');
            })
            ->paginated([10, 25, 50, 100])
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
                ->sortable()
                ->searchable(),
            TextColumn::make('serie.nome')
                ->label('Série')
                ->sortable()
                ->searchable(),
            TextColumn::make('turma')
                ->label('Turma')
                ->sortable()
                ->sortable()
                ->searchable(),
            TextColumn::make('turno')
                ->label('Turno')
                ->sortable()
                ->searchable(),

            TextColumn::make('alunos_count')
                ->label('Qtd. Alunos')
                ->sortable(),

            TextColumn::make('created_at')
                ->label('Criado em')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('updated_at')
                ->label('Atualizado em')
                ->sortable()
                ->dateTime()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    public function acoesTabela(?User $user): array
    {
        return [
            Action::make('viewAlunos')
                ->label('Ver Alunos')
                ->icon('heroicon-o-eye')
                ->visible(fn() => $this->userService->podeVisualizarAlunos(Auth::user()))
                ->color('info')
                ->url(fn($record) => AlunoResource::getUrl('index', [
                    'turma' => $record->id,
                ])),
            EditAction::make(),
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
        return [
            SelectFilter::make('id_escola')
                ->label('Escola')
                ->relationship('escola', 'nome'),

            SelectFilter::make('id_serie')
                ->label('Série')
                ->multiple()
                ->searchable()
                ->preload()
                ->relationship('serie', 'nome'),

            SelectFilter::make('turno')
                ->options([
                    'Manhã' => 'Manhã',
                    'Tarde' => 'Tarde',
                    'Noite' => 'Noite',
                    'Integral' => 'Integral',
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
                }),
        ];
    }


    public static function configurarFormulario(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_escola')
                    ->label('Escola')
                    ->relationship('escola', 'nome')
                    ->required()
                    ->preload()
                    ->searchable()
                    ->default(fn() => Auth::user()?->id_escola)
                    ->dehydrated(true)
                    ->disabled(function () {
                        $user = Auth::user();

                        if (! $user) {
                            return false;
                        }
                        if (filled($user->id_escola)) {
                            return true;
                        }
                        return false;
                    }),

                Select::make('id_serie')
                    ->label('Série')
                    ->relationship('serie', 'nome')
                    ->required()
                    ->preload()
                    ->searchable(),

                TextInput::make('turma')
                    ->label('Turma')
                    ->required()
                    ->maxLength(1)
                    ->live(onBlur: false)
                    ->afterStateUpdated(function ($state, callable $set) {
                        $filtrado = strtoupper(preg_replace('/[^A-Za-z]/', '', $state ?? ''));
                        $set('turma', $filtrado);
                    })
                    ->dehydrateStateUsing(fn($state) => strtoupper($state ?? ''))
                    ->rule(
                        fn($get, $record) =>
                        "unique:turmas,turma," . ($record?->id ?? 'NULL') . ",id,id_escola,{$get('id_escola')},id_serie,{$get('id_serie')},turno,{$get('turno')}"
                    )
                    ->validationMessages([
                        'unique' => 'Ja existe essa turma na escola selecionada.',
                    ])
                    ->placeholder('Ex.: A')
                    ->helperText('Digite apenas uma letra (A–Z).'),

                Select::make('turno')
                    ->label('Turno')
                    ->options([
                        'Manhã' => 'Manhã',
                        'Tarde' => 'Tarde',
                        'Noite' => 'Noite',
                        'Integral' => 'Integral',
                    ])
                    ->required(),
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
