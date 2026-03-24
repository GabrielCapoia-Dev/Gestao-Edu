<?php

namespace App\Filament\Admin\Resources\Estoques\Pages;

use App\Filament\Admin\Resources\Estoques\EstoqueResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEstoque extends CreateRecord
{
    protected static string $resource = EstoqueResource::class;
}
