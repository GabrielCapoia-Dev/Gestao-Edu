<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificacaoEnvio extends Model
{
    use HasUuids;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

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
        'status',
        'queued_at',
        'processing_started_at',
        'processed_at',
        'failed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'destinatarios_ids' => 'array',
            'filtros' => 'array',
            'queued_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
