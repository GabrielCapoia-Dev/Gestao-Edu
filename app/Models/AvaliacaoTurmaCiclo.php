<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AvaliacaoTurmaCiclo extends Model
{
    public const STATUS_ABERTA = 'aberta';
    public const STATUS_REABERTA = 'reaberta';
    public const STATUS_CONCLUIDA = 'concluida';
    public const ROSTER_DINAMICO = 'dinamico';
    public const ROSTER_CONGELADO = 'congelado';

    protected $table = 'avaliacao_turma_ciclos';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'versao_conclusao' => 'integer',
            'operacional_inicializado_em' => 'datetime',
            'legado_documentos_migrados' => 'integer',
            'concluida_em' => 'datetime',
            'concluida_por_snapshot' => 'array',
            'reaberta_em' => 'datetime',
            'reaberta_por_snapshot' => 'array',
        ];
    }

    public function avaliacao(): BelongsTo { return $this->belongsTo(Avaliacao::class); }
    public function turmaAvaliativa(): BelongsTo { return $this->belongsTo(Turma::class, 'turma_avaliativa_id'); }
    public function turmaOrigem(): BelongsTo { return $this->belongsTo(Turma::class, 'turma_origem_id'); }
    public function tokenEscrita(): HasOne { return $this->hasOne(AvaliacaoTurmaTokenEscrita::class, 'ciclo_id'); }
    public function respostas(): HasMany { return $this->hasMany(AvaliacaoRespostaOperacional::class, 'ciclo_id'); }
    public function informacoes(): HasMany { return $this->hasMany(AvaliacaoInformacaoOperacional::class, 'ciclo_id'); }
    public function eventos(): HasMany { return $this->hasMany(AvaliacaoSnapshotEvento::class, 'ciclo_id'); }
    public function snapshotAtual(): BelongsTo { return $this->belongsTo(AvaliacaoSnapshotEvento::class, 'snapshot_evento_atual_id'); }

    public function aceitaEscrita(): bool
    {
        return in_array($this->status, [self::STATUS_ABERTA, self::STATUS_REABERTA], true);
    }
}
