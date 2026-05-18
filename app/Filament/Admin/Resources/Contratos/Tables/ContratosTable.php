<?php

namespace App\Filament\Admin\Resources\Contratos\Tables;

use App\Services\UserSetorAccessService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ContratosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero_contrato')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('empresaContratada.nome')
                    ->label('Empresa Contratada')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('setor.nome')
                    ->label('Setor')
                    ->formatStateUsing(fn ($record): ?string => $record->setor?->nome_completo)
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('data_inicio')
                    ->label('Início')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('data_vencimento')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('Indeterminado'),

                TextColumn::make('alterado_por')
                    ->label('Alterado Por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('setor_id')
                    ->label('Setor')
                    ->options(fn () => app(UserSetorAccessService::class)->optionsForSelect(auth()->user()))
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('ativo')
                    ->label('Status')
                    ->trueLabel('Apenas ativos')
                    ->falseLabel('Apenas inativos')
                    ->placeholder('Todos'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
