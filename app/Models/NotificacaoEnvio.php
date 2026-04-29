<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificacaoEnvio extends Model
{
    use HasUuids;

    protected $table = 'notificacao_envios';

    protected $fillable = [
        'user_id',
        'titulo',
        'mensagem',
        'url',
        'label',
        'prioridade',
        'destino_tipo',
        'destino_label',
        'destinatarios_count',
        'destinatarios_ids',
        'filtros',
    ];

    protected function casts(): array
    {
        return [
            'destinatarios_ids' => 'array',
            'filtros' => 'array',
        ];
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
