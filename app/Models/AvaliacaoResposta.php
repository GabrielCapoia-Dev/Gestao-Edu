<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvaliacaoResposta extends Model
{
    use HasFactory;

    protected $table = 'avaliacao_respostas';

    protected $fillable = [
        'avaliacao_id',
        'pauta_id',
        'turma_id',
        'aluno_id',
        'professor_id',
        'alternativa_id',
        'observacao',
        'respondido_em',
    ];

    protected function casts(): array
    {
        return [
            'respondido_em' => 'datetime',
        ];
    }

    public function avaliacao(): BelongsTo
    {
        return $this->belongsTo(Avaliacao::class, 'avaliacao_id');
    }

    public function pauta(): BelongsTo
    {
        return $this->belongsTo(Pauta::class, 'pauta_id');
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'turma_id');
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class, 'aluno_id');
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Professor::class, 'professor_id');
    }

    public function alternativa(): BelongsTo
    {
        return $this->belongsTo(Alternativa::class, 'alternativa_id');
    }
}
