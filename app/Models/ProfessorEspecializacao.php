<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessorEspecializacao extends Model
{
    protected $table = 'professor_especializacoes';

    protected $fillable = [
        'id_professor',
        'tipo',                          // Magistério, Licenciatura, etc.
        'descricao_especializacao',      // texto livre
        'especializacao_educacao_especial', // bool
        'anexo_especializacao_path',     // caminho do PDF
    ];

    protected $casts = [
        'especializacao_educacao_especial' => 'boolean',
    ];

    public function professor()
    {
        return $this->belongsTo(Professor::class, 'id_professor');
    }
}
