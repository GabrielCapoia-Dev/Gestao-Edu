<?php

namespace App\Models;

use App\Models\Enums\ImportacaoEventoCalendarioStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportacaoEventoCalendario extends Model
{
    protected $table = 'importacoes_eventos_calendario';

    protected $fillable = [
        'uuid',
        'usuario_id',
        'nome_arquivo',
        'disk',
        'caminho_arquivo',
        'checksum',
        'status',
        'total_linhas',
        'total_validas',
        'total_invalidas',
        'total_criadas',
        'total_atualizadas',
        'relatorio',
        'confirmada_em',
        'cancelada_em',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportacaoEventoCalendarioStatus::class,
            'total_linhas' => 'integer',
            'total_validas' => 'integer',
            'total_invalidas' => 'integer',
            'total_criadas' => 'integer',
            'total_atualizadas' => 'integer',
            'relatorio' => 'array',
            'confirmada_em' => 'datetime',
            'cancelada_em' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function linhas(): HasMany
    {
        return $this->hasMany(ImportacaoEventoCalendarioLinha::class, 'importacao_id')
            ->orderBy('numero_linha');
    }
}
