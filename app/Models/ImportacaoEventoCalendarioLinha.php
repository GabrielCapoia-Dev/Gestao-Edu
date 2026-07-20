<?php

namespace App\Models;

use App\Models\Enums\ImportacaoEventoCalendarioAcao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportacaoEventoCalendarioLinha extends Model
{
    protected $table = 'importacao_evento_calendario_linhas';

    protected $fillable = [
        'importacao_id',
        'evento_calendario_id',
        'numero_linha',
        'dados_originais',
        'dados_normalizados',
        'erros',
        'acao',
    ];

    protected function casts(): array
    {
        return [
            'numero_linha' => 'integer',
            'dados_originais' => 'array',
            'dados_normalizados' => 'array',
            'erros' => 'array',
            'acao' => ImportacaoEventoCalendarioAcao::class,
        ];
    }

    public function importacao(): BelongsTo
    {
        return $this->belongsTo(ImportacaoEventoCalendario::class, 'importacao_id');
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(EventoCalendario::class, 'evento_calendario_id');
    }
}
