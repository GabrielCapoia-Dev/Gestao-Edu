<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurmaComponenteProfessor extends Model
{
    protected $table = 'turma_componente_professor';

    protected $fillable = [
        'turma_id',
        'componente_curricular_id',
        'professor_id',
        'tem_professor',
    ];

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'turma_id');
    }

    public function componente(): BelongsTo
    {
        return $this->belongsTo(ComponenteCurricular::class, 'componente_curricular_id');
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Professor::class, 'professor_id');
    }
}