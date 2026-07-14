<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvaliacaoAlunoDocumento extends Model
{
    public const STATUS_VAZIO = 'vazio';

    public const STATUS_PARCIAL = 'parcial';

    public const STATUS_COMPLETO = 'completo';

    protected $table = 'avaliacao_aluno_documentos';

    protected $fillable = [
        'avaliacao_id',
        'aluno_id',
        'cgm',
        'turma_id',
        'escola_id',
        'serie_id',
        'payload',
        'responsaveis_snapshot',
        'responsaveis_snapshot_em',
        'alternativa_ids',
        'professor_ids',
        'pauta_ids_respondidas',
        'total_pautas_esperadas',
        'total_pautas_respondidas',
        'total_infos_complementares',
        'status_preenchimento',
        'observacoes_obrigatorias_pendentes',
        'primeira_resposta_em',
        'ultima_resposta_em',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'responsaveis_snapshot' => 'array',
            'responsaveis_snapshot_em' => 'datetime',
            'alternativa_ids' => 'array',
            'professor_ids' => 'array',
            'pauta_ids_respondidas' => 'array',
            'total_pautas_esperadas' => 'integer',
            'total_pautas_respondidas' => 'integer',
            'total_infos_complementares' => 'integer',
            'observacoes_obrigatorias_pendentes' => 'integer',
            'primeira_resposta_em' => 'datetime',
            'ultima_resposta_em' => 'datetime',
            'version' => 'integer',
        ];
    }

    public static function payloadVazio(): array
    {
        return [
            'v' => 1,
            'pautas' => [],
            'informacoes_complementares' => [],
        ];
    }

    public function avaliacao(): BelongsTo
    {
        return $this->belongsTo(Avaliacao::class, 'avaliacao_id');
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class, 'aluno_id');
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'turma_id');
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class, 'escola_id');
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class, 'serie_id');
    }


    public function historicos(): HasMany
    {
        return $this->hasMany(AvaliacaoAlunoDocumentoHistorico::class, 'documento_id');
    }

    public function pautasPayload(): array
    {
        $payload = $this->payload ?? self::payloadVazio();

        return is_array($payload['pautas'] ?? null) ? $payload['pautas'] : [];
    }

    public function informacoesComplementaresPayload(): array
    {
        $payload = $this->payload ?? self::payloadVazio();

        return is_array($payload['informacoes_complementares'] ?? null)
            ? $payload['informacoes_complementares']
            : [];
    }

    public function respostaDaPauta(int $pautaId): ?array
    {
        $pautas = $this->pautasPayload();
        $chave = (string) $pautaId;

        return isset($pautas[$chave]) && is_array($pautas[$chave]) ? $pautas[$chave] : null;
    }

    public function informacaoDoComponente(int $componenteId): ?array
    {
        $infos = $this->informacoesComplementaresPayload();
        $chave = (string) $componenteId;

        return isset($infos[$chave]) && is_array($infos[$chave]) ? $infos[$chave] : null;
    }
}
