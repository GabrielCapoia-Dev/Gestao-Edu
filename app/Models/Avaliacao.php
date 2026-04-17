<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Avaliacao extends Model
{
    use HasFactory;

    public const STATUS_ATIVA = 'ativa';
    public const STATUS_INATIVA = 'inativa';
    public const STATUS_ENCERRADA = 'encerrada';
    public const STATUS_CANCELADA = 'cancelada';

    protected $table = 'avaliacoes';

    protected $fillable = [
        'nome',
        'data_inicio',
        'data_fim',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim' => 'date',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ATIVA => 'Ativa',
            self::STATUS_INATIVA => 'Inativa',
            self::STATUS_ENCERRADA => 'Encerrada',
            self::STATUS_CANCELADA => 'Cancelada',
        ];
    }

    public function pautas(): BelongsToMany
    {
        return $this->belongsToMany(Pauta::class, 'avaliacao_pauta')
            ->withTimestamps();
    }

    public function turmas(): BelongsToMany
    {
        return $this->belongsToMany(Turma::class, 'avaliacao_turma')
            ->withTimestamps();
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(AvaliacaoResposta::class, 'avaliacao_id');
    }

    public function scopePendentesParaData(Builder $query, CarbonInterface|string|null $data = null): Builder
    {
        $referencia = $data instanceof CarbonInterface
            ? $data
            : Carbon::parse($data ?? now());

        return $query
            ->where('status', self::STATUS_ATIVA)
            ->whereDate('data_inicio', '<=', $referencia)
            ->whereDate('data_fim', '>=', $referencia);
    }
}
