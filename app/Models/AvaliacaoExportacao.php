<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvaliacaoExportacao extends Model
{
    use HasFactory;

    protected $table = 'avaliacao_exportacoes';

    protected $fillable = [
        'avaliacao_id',
        'escola_id',
        'turma_id',
        'aluno_id',
        'user_id',
        'escopo',
        'formato',
        'quantidade_alunos',
        'quantidade_paginas',
        'parametros',
        'exportado_em',
    ];

    protected function casts(): array
    {
        return [
            'parametros' => 'array',
            'exportado_em' => 'datetime',
            'quantidade_alunos' => 'integer',
            'quantidade_paginas' => 'integer',
        ];
    }

    public function avaliacao(): BelongsTo
    {
        return $this->belongsTo(Avaliacao::class, 'avaliacao_id');
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class, 'escola_id');
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'turma_id');
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class, 'aluno_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
