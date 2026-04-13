<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Escola;
use App\Models\Turma;
use App\Models\User;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AlunoService
{
    public function __construct(
        protected UserService $userService
    ) {}

    public function configurarFormulario(Schema $schema, string $operation): Schema
    {
        return $schema->components([
            Section::make('Dados do Aluno')
                ->schema([
                    TextInput::make('nome')
                        ->label('Nome')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('cgm')
                        ->label('CGM')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    DatePicker::make('data_nascimento')
                        ->label('Data de Nascimento')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y'),

                    Select::make('id_turma')
                        ->label('Turma')
                        ->options(fn() => $this->opcoesDeTurmas(Auth::user()))
                        ->searchable()
                        ->preload()
                        ->required(),
                ])
                ->columns(2),
        ]);
    }

    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) use ($user) {
                $this->userService->aplicarFiltroAlunosDoUsuario($query, $user);

                if (request()->filled('turma')) {
                    $query->where('id_turma', request()->integer('turma'));
                }

                $query->with(['turma.escola', 'turma.serie']);
            })
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
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
                ->wrap(),

            TextColumn::make('cgm')
                ->label('CGM')
                ->searchable()
                ->sortable(),

            TextColumn::make('data_nascimento')
                ->label('Data de Nascimento')
                ->date('d/m/Y')
                ->sortable(),

            TextColumn::make('turma.serie.nome')
                ->label('Série')
                ->sortable()
                ->toggleable(),

            TextColumn::make('turma.nome')
                ->label('Turma')
                ->sortable()
                ->badge(),

            TextColumn::make('turma.escola.nome')
                ->label('Escola')
                ->sortable()
                ->toggleable(),
        ];
    }

    private function filtrosTabela(?User $user): array
    {
        return [
            SelectFilter::make('id_turma')
                ->label('Turma')
                ->options($this->opcoesDeTurmas($user))
                ->searchable()
                ->preload(),

            SelectFilter::make('id_escola')
                ->label('Escola')
                ->options($this->opcoesDeEscolas($user))
                ->searchable()
                ->preload()
                ->query(function (Builder $query, array $data) {
                    if (blank($data['value'] ?? null)) {
                        return $query;
                    }

                    return $query->whereHas('turma', fn(Builder $q) => $q->where('id_escola', $data['value']));
                })
                ->visible(fn() => $user?->hasPermissionTo('Filtrar Alunos por Escola') ?? false),
        ];
    }

    private function acoesTabela(?User $user): array
    {
        return [
            EditAction::make()
                ->visible(fn() => $this->userService->podeEditarAlunos($user)),

            DeleteAction::make()
                ->visible(fn() => $this->userService->podeExcluirAlunos($user)),
        ];
    }

    private function acoesEmMassa(?User $user): array
    {
        return [
            DeleteBulkAction::make()
                ->visible(fn() => $user?->hasPermissionTo('Excluir Alunos em Massa') ?? false),
        ];
    }

    public function queryVisivel(?User $user): Builder
    {
        $query = Aluno::query();

        return $this->userService->aplicarFiltroAlunosDoUsuario($query, $user);
    }

    public function opcoesDeTurmas(?User $user): array
    {
        $query = Turma::query()
            ->with(['serie:id,nome', 'escola:id,nome'])
            ->orderBy('nome');

        $this->userService->aplicarFiltroTurmasDoUsuario($query, $user);

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
        $query = Escola::query()->orderBy('nome');

        if (! $user?->hasRole('Admin') && filled($user?->id_escola)) {
            $query->whereKey($user->id_escola);
        }

        return $query->pluck('nome', 'id')->toArray();
    }
}
