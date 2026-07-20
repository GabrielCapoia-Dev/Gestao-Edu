<?php

namespace App\Models;

use App\Models\Enums\DashboardPrioridade;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Aviso extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'publico_alvo_id',
        'titulo',
        'descricao',
        'link_acao',
        'texto_botao',
        'prioridade',
        'posicao_preferencial',
        'ordem_manual',
        'inicio_exibicao',
        'fim_exibicao',
        'ativo',
        'criado_por_id',
        'atualizado_por_id',
        'excluido_por_id',
    ];

    protected function casts(): array
    {
        return [
            'prioridade' => DashboardPrioridade::class,
            'posicao_preferencial' => 'integer',
            'ordem_manual' => 'integer',
            'inicio_exibicao' => 'datetime',
            'fim_exibicao' => 'datetime',
            'ativo' => 'boolean',
        ];
    }

    public function publicoAlvo(): BelongsTo
    {
        return $this->belongsTo(PublicoAlvo::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_id');
    }

    public function atualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atualizado_por_id');
    }

    public function excluidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'excluido_por_id');
    }

    public function scopeVigentes(Builder $query, ?CarbonInterface $agora = null): Builder
    {
        $agora ??= now();

        return $query
            ->where('ativo', true)
            ->where('inicio_exibicao', '<=', $agora)
            ->where('fim_exibicao', '>=', $agora);
    }

    public function statusExibicao(?CarbonInterface $agora = null): string
    {
        $agora ??= now();

        if (! $this->ativo) {
            return 'inativo';
        }

        if ($this->inicio_exibicao?->gt($agora)) {
            return 'agendado';
        }

        if ($this->fim_exibicao?->lt($agora)) {
            return 'expirado';
        }

        return 'ativo';
    }

    public function statusExibicaoLabel(?CarbonInterface $agora = null): string
    {
        return match ($this->statusExibicao($agora)) {
            'ativo' => 'Ativo',
            'agendado' => 'Agendado',
            'expirado' => 'Expirado',
            default => 'Inativo',
        };
    }

    public function linkAcaoSeguro(): ?string
    {
        $link = trim((string) $this->link_acao);

        if ($link === '') {
            return null;
        }

        if (str_starts_with($link, '/') && ! str_starts_with($link, '//')) {
            return $link;
        }

        $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $link : null;
    }

    protected static function booted(): void
    {
        static::deleting(function (Aviso $aviso): void {
            if ($aviso->isForceDeleting()) {
                return;
            }

            $aviso->forceFill([
                'excluido_por_id' => Auth::id(),
            ])->saveQuietly();
        });
    }
}
