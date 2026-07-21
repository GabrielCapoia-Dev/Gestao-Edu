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
        'categoria',
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
            'status' => EventoCalendarioStatus::class,
            'ativo' => 'boolean',
            'enviar_todas_escolas' => 'boolean',
            'progresso' => 'float',
            'cor' => EventoCalendarioCor::class,
            'origem' => EventoCalendarioOrigem::class,
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
        return $query->where('ativo', true);
    }

    public function statusEfetivo(?CarbonInterface $agora = null): EventoCalendarioStatus
    {
        $agora ??= now();

        if (in_array($this->status, [EventoCalendarioStatus::CANCELADO, EventoCalendarioStatus::CONCLUIDO], true)) {
            return $this->status;
        }

        if ($this->data_inicio?->gt($agora)) {
            return EventoCalendarioStatus::AGENDADO;
        }

        if ($this->data_fim?->lt($agora)) {
            return EventoCalendarioStatus::CONCLUIDO;
        }

        return EventoCalendarioStatus::EM_ANDAMENTO;
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
