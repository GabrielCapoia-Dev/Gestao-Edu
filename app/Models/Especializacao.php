<?php

// app/Models/Especializacao.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Especializacao extends Model
{
    protected $table = 'especializacoes';

    protected $fillable = [
        'nome',
    ];

    public function professores()
    {
        return $this->belongsToMany(
            Professor::class,
            'professor_especializacao',
            'especializacao_id',
            'professor_id'
        )->withTimestamps();
    }


    public function professorEspecializacoes()
    {
        return $this->hasMany(ProfessorEspecializacao::class, 'especializacao_id');
    }
}
