<?php

namespace App\Models;

use App\Models\Enums\DashboardPrioridade;
use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\Enums\EventoCalendarioCor;
use App\Models\Enums\EventoCalendarioOrigem;
use App\Models\Enums\EventoCalendarioStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class EventoCalendario extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'eventos_calendario';

    protected $fillable = [
        'publico_alvo_id',
        'escola_id',
        'setor_id',
        'enviar_todas_escolas',
        'titulo',
        'descricao',
        'local',
        'latitude',
        'longitude',
        'categoria',
        'categoria_detalhe',
        'assunto',
        'prioridade',
        'data_inicio',
        'data_fim',
        'link_acao',
        'texto_botao',
        'status',
        'ativo',
        'progresso',
        'cor',
        'origem',
        'fonte_externa',
        'identificador_externo',
        'criado_por_id',
        'atualizado_por_id',
        'excluido_por_id',
        'ultima_importacao_id',
    ];

    protected function casts(): array
    {
        return [
            'categoria' => EventoCalendarioCategoria::class,
            'prioridade' => DashboardPrioridade::class,
            'data_inicio' => 'datetime',
            'data_fim' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'status' => EventoCalendarioStatus::class,
            'ativo' => 'boolean',
            'enviar_todas_escolas' => 'boolean',
            'progresso' => 'float',
            'cor' => EventoCalendarioCor::class,
            'origem' => EventoCalendarioOrigem::class,
            'possui_transporte' => 'boolean',
            'possui_inversao_fila' => 'boolean',
            'escolas_agendadas_count' => 'integer',
            'escolas_publico_count' => 'integer',
            'total_estudantes_transporte' => 'integer',
        ];
    }

    public function publicoAlvo(): BelongsTo
    {
        return $this->belongsTo(PublicoAlvo::class);
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class);
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function escolasAgendadas(): HasMany
    {
        return $this->hasMany(EventoCalendarioEscola::class, 'evento_calendario_id');
    }

    /**
     * Relação exclusiva da listagem. Mantém o carregamento parcial separado
     * de escolasAgendadas para não alterar a semântica das regras de domínio.
     */
    public function escolasResumo(): HasMany
    {
        return $this->hasMany(EventoCalendarioEscola::class, 'evento_calendario_id');
    }

    public function historicos(): HasMany
    {
        return $this->hasMany(EventoCalendarioHistorico::class, 'evento_calendario_id')
            ->latest('created_at')
            ->latest('id');
    }

    public function alocacoesTransporte(): HasMany
    {
        return $this->hasMany(EventoCalendarioTransporteAlocacao::class, 'evento_calendario_id');
    }

    public function alocacoesTransporteAtivas(): HasMany
    {
        return $this->alocacoesTransporte()->whereNull('removido_em');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_id')->withTrashed();
    }

    public function atualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atualizado_por_id')->withTrashed();
    }

    public function excluidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'excluido_por_id')->withTrashed();
    }

    public function ultimaImportacao(): BelongsTo
    {
        return $this->belongsTo(ImportacaoEventoCalendario::class, 'ultima_importacao_id');
    }

    public function scopeNoPeriodo(Builder $query, CarbonInterface $inicio, CarbonInterface $fim): Builder
    {
        return $query
            ->where('data_inicio', '<=', $fim)
            ->where('data_fim', '>=', $inicio);
    }

    public function scopePublicados(Builder $query): Builder
    {
        return $query
            ->where('ativo', true)
            ->where('status', EventoCalendarioStatus::PUBLICADO);
    }

    public function scopeComTransporte(Builder $query): Builder
    {
        return $query->whereHas(
            'escolasAgendadas',
            fn (Builder $escolas): Builder => $escolas->where('precisa_transporte', true),
        );
    }

    public function scopeSemTransporte(Builder $query): Builder
    {
        return $query->whereDoesntHave(
            'escolasAgendadas',
            fn (Builder $escolas): Builder => $escolas->where('precisa_transporte', true),
        );
    }

    public function scopeComStatus(Builder $query, EventoCalendarioStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof EventoCalendarioStatus ? $status->value : $status);
    }

    public function possuiTransporte(): bool
    {
        if (array_key_exists('possui_transporte', $this->attributes)) {
            return (bool) $this->getAttribute('possui_transporte');
        }

        if ($this->relationLoaded('escolasAgendadas')) {
            return $this->escolasAgendadas->contains(
                fn (EventoCalendarioEscola $escola): bool => $escola->precisa_transporte,
            );
        }

        return $this->escolasAgendadas()
            ->where('precisa_transporte', true)
            ->exists();
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
        static::creating(function (EventoCalendario $evento): void {
            $evento->criado_por_id ??= Auth::id();
            $evento->atualizado_por_id ??= Auth::id();
        });

        static::updating(function (EventoCalendario $evento): void {
            if (! $evento->isDirty('excluido_por_id')) {
                $evento->atualizado_por_id = Auth::id() ?? $evento->atualizado_por_id;
            }
        });

        static::deleting(function (EventoCalendario $evento): void {
            if ($evento->isForceDeleting()) {
                return;
            }

            $evento->forceFill(['excluido_por_id' => Auth::id()])->saveQuietly();
        });
    }
}
