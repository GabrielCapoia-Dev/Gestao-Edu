<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessorComponenteSolicitacao extends Model
{
    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_APROVADA = 'aprovada';

    public const STATUS_REJEITADA = 'rejeitada';

    protected $table = 'professor_componente_solicitacoes';

    protected $fillable = [
        'turma_componente_professor_id',
        'professor_id',
        'solicitado_por_id',
        'status',
        'analisado_por_id',
        'analisado_em',
        'motivo_rejeicao',
    ];

    protected function casts(): array
    {
        return [
            'turma_componente_professor_id' => 'integer',
            'professor_id' => 'integer',
            'solicitado_por_id' => 'integer',
            'analisado_por_id' => 'integer',
            'analisado_em' => 'datetime',
        ];
    }

    public function vinculo(): BelongsTo
    {
        return $this->belongsTo(TurmaComponenteProfessor::class, 'turma_componente_professor_id');
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Professor::class);
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por_id')->withTrashed();
    }

    public function analisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'analisado_por_id')->withTrashed();
    }
}
