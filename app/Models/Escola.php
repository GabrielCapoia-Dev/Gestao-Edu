<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Escola extends LocalTrabalho
{
    protected static function booted(): void
    {
        static::addGlobalScope(
            'somente_escolas',
            fn (Builder $query): Builder => $query->where(
                $query->qualifyColumn('nao_e_escola'),
                false,
            ),
        );
    }
}
