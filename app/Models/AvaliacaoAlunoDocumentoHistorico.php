<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvaliacaoAlunoDocumentoHistorico extends Model
{
    public const MOVIMENTACAO_REMANEJAMENTO = 'remanejamento';

    public const MOVIMENTACAO_TRANSFERENCIA = 'transferencia';

    protected $table = 'avaliacao_aluno_documentos_historico';

    protected $fillable = [
        'avaliacao_id',
        'documento_id',
        'aluno_origem_id',
        'aluno_destino_id',
        'cgm',
        'turma_id',
        'escola_id',
        'serie_id',
        'movimentacao_tipo',
        'payload',
        'responsaveis_snapshot',
        'responsaveis_snapshot_em',
        'alternativa_ids',
        'total_pautas_esperadas',
        'total_pautas_respondidas',
        'total_infos_complementares',
        'status_preenchimento',
        'movimentado_em',
        'movimentado_por',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'responsaveis_snapshot' => 'array',
            'responsaveis_snapshot_em' => 'datetime',
            'alternativa_ids' => 'array',
            'total_pautas_esperadas' => 'integer',
            'total_pautas_respondidas' => 'integer',
            'total_infos_complementares' => 'integer',
            'movimentado_em' => 'datetime',
        ];
    }

    public function avaliacao(): BelongsTo
    {
        return $this->belongsTo(Avaliacao::class, 'avaliacao_id');
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(AvaliacaoAlunoDocumento::class, 'documento_id');
    }

    public function alunoOrigem(): BelongsTo
    {
        return $this->belongsTo(Aluno::class, 'aluno_origem_id');
    }

    public function alunoDestino(): BelongsTo
    {
        return $this->belongsTo(Aluno::class, 'aluno_destino_id');
    }

    public function movimentadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'movimentado_por');
    }
}
