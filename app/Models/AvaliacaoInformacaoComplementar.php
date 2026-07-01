<?php

namespace App\Models;

use App\Services\Avaliacoes\AvaliacaoDashboardMetricsService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvaliacaoInformacaoComplementar extends Model
{
    use HasFactory;

    protected $table = 'avaliacao_informacoes_complementares';

    protected $fillable = [
        'avaliacao_id',
        'turma_id',
        'aluno_id',
        'componente_curricular_id',
        'professor_id',
        'informacoes_complementares',
        'bloqueada',
        'informacao_origem_id',
        'aluno_origem_id',
        'turma_origem_id',
        'bloqueio_tipo',
    ];

    protected static function booted(): void
    {
        $invalidarDashboard = function (self $informacao): void {
            $service = app(AvaliacaoDashboardMetricsService::class);

            foreach (array_unique([(int) $informacao->getOriginal('avaliacao_id'), (int) $informacao->avaliacao_id]) as $avaliacaoId) {
                $service->forgetForAvaliacao($avaliacaoId);
            }
        };

        static::saved($invalidarDashboard);
        static::deleted($invalidarDashboard);
    }

    protected function casts(): array
    {
        return [
            'bloqueada' => 'boolean',
            'informacao_origem_id' => 'integer',
            'aluno_origem_id' => 'integer',
            'turma_origem_id' => 'integer',
        ];
    }

    public function avaliacao(): BelongsTo
    {
        return $this->belongsTo(Avaliacao::class, 'avaliacao_id');
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

    public function componente(): BelongsTo
    {
        return $this->belongsTo(ComponenteCurricular::class, 'componente_curricular_id');
    }

    public function informacaoOrigem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'informacao_origem_id');
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
