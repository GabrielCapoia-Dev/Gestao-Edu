<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvaliacaoInformacaoOperacional extends Model
{
    protected $table = 'avaliacao_informacoes_operacionais';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }
}
