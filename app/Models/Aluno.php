<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aluno extends Model
{
    use HasFactory;

    protected $table = 'alunos';

    protected $fillable = [
        'nome',
        'cgm',
        'data_nascimento',
        'id_turma',
    ];

    protected function casts(): array
    {
        return [
            'nome' => 'string',
            'cgm' => 'string',
            'data_nascimento' => 'date',
            'id_turma' => 'integer',
        ];
    }

    public function turma()
    {
        return $this->belongsTo(Turma::class, 'id_turma');
    }

    public function avaliacaoRespostas()
    {
        return $this->hasMany(AvaliacaoResposta::class, 'aluno_id');
    }
}
