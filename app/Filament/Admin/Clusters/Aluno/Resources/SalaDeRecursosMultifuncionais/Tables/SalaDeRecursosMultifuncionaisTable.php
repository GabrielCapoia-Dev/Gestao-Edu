<?php

namespace App\Filament\Admin\Clusters\Aluno\Resources\SalaDeRecursosMultifuncionais\Tables;

use App\Models\Aluno;
use App\Models\Professor;
use App\Models\User;
use App\Services\AlunoService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SalaDeRecursosMultifuncionaisTable
{
    public static function configure(Table $table, ?User $user = null): Table
    {
        $user        = $user ?? Auth::user();
        $alunoService = app(AlunoService::class);

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($user, $alunoService) {
                $alunoService->aplicarFiltroPorEscolaDoUsuario($query, $user);
            })
            ->columns(static::columns())
            ->filters(static::filters())
            ->recordActions(static::actions())
            ->defaultSort('nome');
    }

    /*
    |--------------------------------------------------------------------------
    | COLUMNS
    |--------------------------------------------------------------------------
    */

    public static function columns(): array
    {
        return [
            TextColumn::make('turma.escola.nome')
                ->label('Escola')
                ->sortable()
                ->searchable()
                ->wrap()
                ->toggleable(isToggledHiddenByDefault: false),

            TextColumn::make('cgm')
                ->label('CGM')
                ->sortable()
                ->searchable()
                ->wrap()
                ->toggleable(isToggledHiddenByDefault: false)
                ->copyable()
                ->copyMessage('Copiado!')
                ->copyableState(fn($state) => $state)
                ->tooltip('Clique para copiar'),

            TextColumn::make('nome')
                ->label('Aluno')
                ->sortable()
                ->searchable()
                ->wrap()
                ->toggleable(isToggledHiddenByDefault: false),

            TextColumn::make('turma.serie.nome')
                ->label('Série')
                ->sortable(),

            TextColumn::make('turma.turma')
                ->label('Turma')
                ->sortable(),

            TextColumn::make('turma.turno')
                ->label('Turno')
                ->sortable(),

            TextColumn::make('professores_srm')
                ->label('Professor SRM')
                ->getStateUsing(function (Aluno $record) {
                    if (! $record->turma) return '—';

                    $professores = Professor::query()
                        ->where('id_escola', $record->turma->id_escola)
                        ->where('turno', $record->turma->turno)
                        ->where('professor_srm', true)
                        ->orderBy('nome')
                        ->pluck('nome')
                        ->toArray();

                    return empty($professores) ? 'Sem professor SRM' : implode(', ', $professores);
                })
                ->wrap()
                ->toggleable(isToggledHiddenByDefault: false),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | FILTERS
    |--------------------------------------------------------------------------
    */

    public static function filters(): array
    {
        return [
            SelectFilter::make('id_escola')
                ->label('Escola')
                ->relationship('turma.escola', 'nome')
                ->searchable()
                ->visible(fn() => User::pode('Filtrar Alunos por Escola'))
                ->columnSpan(2)
                ->preload(),

            SelectFilter::make('id_serie')
                ->label('Série')
                ->relationship('turma.serie', 'nome')
                ->searchable()
                ->columnSpan(2)
                ->preload(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIONS
    |--------------------------------------------------------------------------
    */

    public static function actions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}