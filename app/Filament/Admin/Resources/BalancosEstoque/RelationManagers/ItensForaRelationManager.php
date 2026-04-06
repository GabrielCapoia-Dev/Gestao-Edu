<?php

namespace App\Filament\Admin\Resources\BalancosEstoque\RelationManagers;

use App\Filament\Admin\Resources\BalancosEstoque\BalancoEstoqueResource;
use App\Models\BalancoEstoque;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ItensForaRelationManager extends RelationManager
{
    protected static string $relationship = 'itensFora';

    protected static ?string $title = 'Itens Fora Deste Balanço';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof BalancoEstoque
            && ! $ownerRecord->isAgendado()
            && BalancoEstoqueResource::canView($ownerRecord)
            && is_subclass_of($pageClass, ViewRecord::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('item.nome')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('item.unidade_medida')
                    ->label('Unidade')
                    ->formatStateUsing(fn ($state) => strtoupper($state?->value ?? (string) $state)),
                TextColumn::make('saldo_sistema_antes')
                    ->label('Saldo Virtual na Abertura')
                    ->numeric(decimalPlaces: 3, decimalSeparator: ',', thousandsSeparator: '.'),
                TextColumn::make('created_at')
                    ->label('Snapshot em')
                    ->dateTime('d/m/Y H:i'),
            ]);
    }
}
