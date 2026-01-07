<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Aluno extends Model
{
    protected $table = 'alunos';

    protected $fillable = [
        'id_professor',
        'id_turma',
        'cgm',
        'nome',
        'sexo',
        'data_nascimento',
        'dificuldade_aprendizagem',
        'frequenta_srm',
        'encaminhado_para_sme',
        'encaminhado_para_caei',
        'status_fonoaudiologo',
        'status_psicologo',
        'ja_foi_retido',
        'status_psicopedagogo',
        'avanco_caei',
    ];

    public function turma()
    {
        return $this->belongsTo(Turma::class, 'id_turma');
    }

    public function professor()
    {
        return $this->belongsTo(Professor::class, 'id_professor');
    }

    // relação many-to-many (para usar TextColumn::make('laudos.nome'), etc.)
    public function laudos()
    {
        return $this->belongsToMany(Laudo::class, 'aluno_laudo', 'aluno_id', 'laudo_id')
            ->using(AlunoLaudo::class)
            ->withPivot('anexo_laudo_path')
            ->withTimestamps();
    }

    // relação 1:N com o pivot, para o Repeater
    public function laudosPivot()
    {
        return $this->hasMany(AlunoLaudo::class, 'aluno_id');
    }

    public function retencoes()
    {
        return $this->hasMany(AlunoRetencao::class, 'id_aluno');
    }
}
