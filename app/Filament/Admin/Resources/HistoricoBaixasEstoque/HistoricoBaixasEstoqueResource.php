<?php

namespace App\Filament\Admin\Resources\HistoricoBaixasEstoque;

use App\Filament\Admin\Resources\HistoricoBaixasEstoque\Pages\ListHistoricoBaixasEstoque;
use App\Filament\Admin\Resources\HistoricoBaixasEstoque\Tables\HistoricoBaixasEstoqueTable;
use App\Models\BaixasEstoques;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class HistoricoBaixasEstoqueResource extends Resource
{
    protected static ?string $model = BaixasEstoques::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;
    protected static string|UnitEnum|null $navigationGroup = 'Alimentação Escolar';
    protected static ?string $navigationParentItem = 'Gestão de Estoque';
    protected static ?string $navigationLabel = 'Listagem de Baixas';
    protected static ?string $pluralModelLabel = 'Listagem de Baixas';
    protected static ?string $modelLabel = 'Baixa';
    protected static ?string $slug = 'historico-baixas-estoque';
    protected static ?int $navigationSort = 7;

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasPermissionTo('Listar Gestão de Estoque') ?? false;
    }

    public static function table(Table $table): Table
    {
        return HistoricoBaixasEstoqueTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHistoricoBaixasEstoque::route('/'),
        ];
    }
}
