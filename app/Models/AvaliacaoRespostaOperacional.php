<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvaliacaoRespostaOperacional extends Model
{
    protected $table = 'avaliacao_respostas_operacionais';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['respondido_em' => 'datetime', 'version' => 'integer'];
    }
}
