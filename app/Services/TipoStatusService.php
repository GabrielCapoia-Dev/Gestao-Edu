<?php

namespace App\Services;

use App\Models\TipoStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TipoStatusService
{

    public function listarAtivos(): Builder
    {
        return TipoStatus::query()->where('ativo', true)->orderBy('ordem');
    }

    public function obterStatusInicial(): ?TipoStatus
    {
        return TipoStatus::where('ativo', true)
            ->orderBy('ordem')
            ->first();
    }
}
