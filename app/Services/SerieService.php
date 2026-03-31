<?php

namespace App\Services;

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


class SerieService
{
    public function __construct(
        protected UserService $userService,
    ) {}

    /** Configura a tabela completa (paginações, colunas, filtros, ações, ordenação). */
    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(5)
            ->columns($this->colunasTabela())
            ->recordActions($this->acoesTabela($user))
            ->toolbarActions($this->acoesEmMassa($user))
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    private function colunasTabela(): array
    {
        return [
            TextColumn::make('codigo')
                ->label('Código')
                ->searchable()
                ->sortable(),

            TextColumn::make('nome')
                ->label('Nome')
                ->searchable()
                ->sortable(),

            TextColumn::make('componentesCurriculares.nome')
                ->label('Componentes')
                ->badge()
                ->separator(',')
                ->wrap()
                ->limit(10)
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('turmas_count')
                ->label('Qtd. Turmas')
                ->counts('turmas')
                ->sortable(),

            TextColumn::make('created_at')
                ->label('Criado')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('updated_at')
                ->label('Atualizado')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    private function acoesTabela(?User $user): array
    {
        return [
            EditAction::make(),
            DeleteAction::make()
                ->before(function (User $record, DeleteAction $action) use ($user) {
                    if (! $this->userService->podeDeletar($user, $record)) {
                        $action->failure();
                        $action->halt();
                    }
                })
                ->visible(
                    fn() =>
                    $this->userService->ehAdmin(Auth::user())
                ),
        ];
    }

    private function acoesEmMassa(?User $user): array
    {
        return [
            DeleteBulkAction::make()
                ->before(function ($records, $action) use ($user) {
                    if (! $this->userService->podeDeletarEmLote($user, $records)) {
                        $action->halt();
                    }
                })
                ->visible(fn() => $this->userService->ehAdmin(Auth::user())),
        ];
    }
}
