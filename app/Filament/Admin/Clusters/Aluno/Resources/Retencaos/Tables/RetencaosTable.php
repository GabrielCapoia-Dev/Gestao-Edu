<?php

namespace App\Filament\Admin\Clusters\Aluno\Resources\Retencaos\Tables;

use App\Models\AlunoRetencao;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RetencaosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(static::columns())
            ->defaultSort('created_at', 'desc');
    }

    /*
    |--------------------------------------------------------------------------
    | COLUMNS
    |--------------------------------------------------------------------------
    */

    public static function columns(): array
    {
        return [
            TextColumn::make('aluno.cgm')
                ->label('CGM')
                ->sortable()
                ->searchable()
                ->copyable()
                ->copyMessage('Copiado!')
                ->copyableState(fn($state) => $state)
                ->tooltip('Clique para copiar'),

            TextColumn::make('aluno.nome')
                ->label('Aluno')
                ->sortable()
                ->searchable()
                ->wrap(),

            TextColumn::make('aluno.turma.escola.nome')
                ->label('Escola')
                ->sortable()
                ->searchable()
                ->wrap()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('aluno.turma.serie.nome')
                ->label('Série Atual')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('aluno.turma.turma')
                ->label('Turma Atual')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('vezes_retido')
                ->label('Qtd. Retenções')
                ->sortable(),

            TextColumn::make('serie.nome')
                ->label('Série em que foi retido')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: false),

            TextColumn::make('ano_retido')
                ->label('Ano em que foi retido')
                ->sortable(),

            TextColumn::make('motivo_retido')
                ->label('Motivos')
                ->formatStateUsing(function ($state) {
                    if (empty($state)) return '-';
                    if (is_array($state)) return implode(', ', $state);
                    return (string) $state;
                })
                ->wrap()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('created_at')
                ->label('Criado')
                ->since()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('updated_at')
                ->label('Atualizado')
                ->since()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }
}