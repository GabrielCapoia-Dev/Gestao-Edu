<?php

namespace App\Models;

use App\Services\Avaliacoes\AvaliacaoDashboardMetricsService;
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
        'bloqueada',
        'resposta_origem_id',
        'aluno_origem_id',
        'turma_origem_id',
        'bloqueio_tipo',
    ];

    protected static function booted(): void
    {
        $invalidarDashboard = function (self $resposta): void {
            $service = app(AvaliacaoDashboardMetricsService::class);

            foreach (array_unique([(int) $resposta->getOriginal('avaliacao_id'), (int) $resposta->avaliacao_id]) as $avaliacaoId) {
                $service->forgetForAvaliacao($avaliacaoId);
            }
        };

        static::saved($invalidarDashboard);
        static::deleted($invalidarDashboard);
    }

    protected function casts(): array
    {
        return [
            'respondido_em' => 'datetime',
            'bloqueada' => 'boolean',
            'resposta_origem_id' => 'integer',
            'aluno_origem_id' => 'integer',
            'turma_origem_id' => 'integer',
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

    public function respostaOrigem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'resposta_origem_id');
    }

    public function alunoOrigem(): BelongsTo
    {
        return $this->belongsTo(Aluno::class, 'aluno_origem_id');
    }

    public function turmaOrigem(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'turma_origem_id');
    }
}
