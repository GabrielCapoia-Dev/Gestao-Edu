<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvaliacaoRespostaFato extends Model
{
    protected $table = 'avaliacao_resposta_fatos';

    protected $fillable = [
        'documento_id',
        'avaliacao_id',
        'aluno_id',
        'turma_id',
        'escola_id',
        'pauta_id',
        'componente_curricular_id',
        'alternativa_id',
        'professor_id',
        'tem_observacao',
        'observacao',
        'respondido_em',
    ];

    protected function casts(): array
    {
        return [
            'tem_observacao' => 'boolean',
            'respondido_em' => 'datetime',
        ];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(AvaliacaoAlunoDocumento::class, 'documento_id');
    }

    public function avaliacao(): BelongsTo
    {
        return $this->belongsTo(Avaliacao::class, 'avaliacao_id');
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class, 'aluno_id');
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'turma_id');
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class, 'escola_id');
    }

    public function pauta(): BelongsTo
    {
        return $this->belongsTo(Pauta::class, 'pauta_id');
    }

    public function alternativa(): BelongsTo
    {
        return $this->belongsTo(Alternativa::class, 'alternativa_id');
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Professor::class, 'professor_id');
    }

    public function componente(): BelongsTo
    {
        return $this->belongsTo(ComponenteCurricular::class, 'componente_curricular_id');
    }
}
