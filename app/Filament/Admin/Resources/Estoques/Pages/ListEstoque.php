<?php

namespace App\Filament\Admin\Resources\Estoques\Pages;

use App\Filament\Admin\Resources\Estoques\EstoqueResource;
use App\Filament\Admin\Resources\Estoques\Tables\EstoqueTable;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListEstoque extends ListRecords
{
    protected static string $resource = EstoqueResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function table(Table $table): Table
    {
        return EstoqueTable::configure($table);
    }
}