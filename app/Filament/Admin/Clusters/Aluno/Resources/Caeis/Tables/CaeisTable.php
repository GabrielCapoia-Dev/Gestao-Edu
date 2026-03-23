<?php

namespace App\Filament\Admin\Clusters\Aluno\Resources\Caeis\Tables;

use App\Models\User;
use App\Services\UserService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CaeisTable
{
    public static function configure(Table $table, ?User $user = null): Table
    {
        $user        = $user ?? Auth::user();
        $userService = app(UserService::class);

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($user, $userService) {
                $userService->aplicarFiltroPorEscolaDoUsuario($query, $user);
            })
            ->columns(static::columns())
            ->filters(static::filters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(12)
            ->recordActions([]);
    }

    /*
    |--------------------------------------------------------------------------
    | COLUMNS
    |--------------------------------------------------------------------------
    */

    public static function columns(): array
    {
        $statusOpcoes = [
            'Sim, Lista de Espera' => 'warning',
            'Sim, Em Atendimento'  => 'success',
            'Sim, Desistente'      => 'danger',
            'Sim, Desligado'       => 'gray',
            'Não'                  => 'danger',
        ];

        return [
            TextColumn::make('turma.escola.nome')
                ->label('Escola')
                ->sortable()
                ->searchable()
                ->wrap(),

            TextColumn::make('turma.serie.nome')
                ->label('Série')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('turma.turma')
                ->label('Turma')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('cgm')
                ->label('CGM')
                ->sortable()
                ->searchable()
                ->copyable()
                ->copyMessage('Copiado!')
                ->copyableState(fn($state) => $state)
                ->tooltip('Clique para copiar'),

            TextColumn::make('nome')
                ->label('Aluno')
                ->sortable()
                ->searchable()
                ->wrap(),

            TextColumn::make('encaminhado_para_caei')
                ->label('Encaminhado para CAEI')
                ->badge()
                ->color(fn(?string $state) => match ($state) {
                    'Sim'  => 'success',
                    'Nao', 'Não' => 'danger',
                    default => 'secondary',
                }),

            TextColumn::make('status_fonoaudiologo')
                ->label('Fonoaudiólogo')
                ->badge()
                ->color(fn(?string $state) => $statusOpcoes[$state] ?? 'secondary')
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('status_psicologo')
                ->label('Psicólogo')
                ->badge()
                ->color(fn(?string $state) => $statusOpcoes[$state] ?? 'secondary')
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('status_psicopedagogo')
                ->label('Psicopedagogo')
                ->badge()
                ->color(fn(?string $state) => $statusOpcoes[$state] ?? 'secondary')
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('avanco_caei')
                ->label('Avanço CAEI')
                ->badge()
                ->color(fn(?string $state) => match ($state) {
                    'Sim'                    => 'success',
                    'Nao'                    => 'danger',
                    'Nao está em atendimento' => 'warning',
                    default                  => 'secondary',
                })
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | FILTERS
    |--------------------------------------------------------------------------
    */

    public static function filters(): array
    {
        $optsStatus = [
            'Sim, Lista de Espera' => 'Sim, Lista de Espera',
            'Sim, Em Atendimento'  => 'Sim, Em Atendimento',
            'Sim, Desistente'      => 'Sim, Desistente',
            'Sim, Desligado'       => 'Sim, Desligado',
            'Não'                  => 'Não',
        ];

        return [
            SelectFilter::make('id_escola')
                ->label('Escola')
                ->relationship('turma.escola', 'nome')
                ->searchable()
                ->visible(fn() => User::pode('Filtrar Alunos por Escola'))
                ->preload()
                ->columnSpan(4),

            SelectFilter::make('id_serie')
                ->label('Série')
                ->relationship('turma.serie', 'nome')
                ->searchable()
                ->preload()
                ->columnSpan(4),

            SelectFilter::make('encaminhado_para_caei')
                ->label('Encaminhado CAEI')
                ->options(['Sim' => 'Sim', 'Nao' => 'Não'])
                ->columnSpan(4),

            SelectFilter::make('avanco_caei')
                ->label('Avanço CAEI')
                ->options([
                    'Sim'                    => 'Sim',
                    'Nao'                    => 'Não',
                    'Nao está em atendimento' => 'Não está em atendimento',
                ])
                ->columnSpan(3),

            SelectFilter::make('status_psicopedagogo')
                ->label('Psicopedagogo')
                ->options($optsStatus)
                ->columnSpan(3),

            SelectFilter::make('status_psicologo')
                ->label('Psicólogo')
                ->options($optsStatus)
                ->columnSpan(3),

            SelectFilter::make('status_fonoaudiologo')
                ->label('Fonoaudiólogo')
                ->options($optsStatus)
                ->columnSpan(3),
        ];
    }
}