<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Laudo extends Model
{
    protected $table = 'laudos';

    protected $fillable = [
        'nome',
    ];

    public function alunos()
    {
        return $this->belongsToMany(Aluno::class, 'aluno_laudo', 'laudo_id', 'aluno_id')
            ->using(AlunoLaudo::class)
            ->withPivot('anexo_laudo_path')
            ->withTimestamps();
    }

    public function alunoLaudos()
    {
        return $this->hasMany(AlunoLaudo::class, 'laudo_id');
    }
}
