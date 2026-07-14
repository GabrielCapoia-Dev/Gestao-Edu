<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvaliacaoDashboardFato extends Model
{
    protected $table = 'avaliacao_dashboard_fatos';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'respondida' => 'boolean',
            'observacao_pendente' => 'boolean',
            'respondida_em' => 'datetime',
            'origem_version' => 'integer',
        ];
    }
}
