<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaldoEleitoral extends Model
{
    public const TIPO_ADICAO = 'adicao';

    public const TIPO_USO = 'uso';

    public const TIPO_ESTORNO = 'estorno';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_APROVADO = 'aprovado';

    public const STATUS_REJEITADO = 'rejeitado';

    protected $table = 'saldos_eleitorais';

    protected $fillable = [
        'servidor_id', 'movimento_origem_id', 'solicitante_id', 'aprovador_id', 'tipo', 'dias', 'status',
        'datas', 'lancamento_manual', 'observacao', 'decidido_em',
    ];

    protected function casts(): array
    {
        return ['dias' => 'integer', 'lancamento_manual' => 'boolean', 'decidido_em' => 'datetime'];
    }

    /** Normaliza arrays legados/JSON escalares para uma lista segura de datas. */
    protected function datas(): Attribute
    {
        return Attribute::make(
            get: static function (?string $value): ?array {
                if ($value === null || $value === '') {
                    return null;
                }

                $decoded = json_decode($value, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? [$value] : null;
                }
                if (is_string($decoded)) {
                    $decoded = json_decode($decoded, true) ?? $decoded;
                }

                if (is_array($decoded)) {
                    return array_values(array_filter($decoded, static fn (mixed $date): bool =>
                        is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
                    ));
                }

                return is_string($decoded) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $decoded) === 1
                    ? [$decoded]
                    : null;
            },
            set: static fn (?array $value): ?string => $value === null
                ? null
                : json_encode(array_values($value), JSON_THROW_ON_ERROR),
        );
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class)->withTrashed();
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id')->withTrashed();
    }

    public function aprovador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovador_id')->withTrashed();
    }

    public function movimentoOrigem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'movimento_origem_id');
    }

    public function estornos(): HasMany
    {
        return $this->hasMany(self::class, 'movimento_origem_id')->where('tipo', self::TIPO_ESTORNO);
    }

    public function scopePendentes(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDENTE);
    }
}
