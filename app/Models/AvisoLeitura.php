<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvisoLeitura extends Model
{
    protected $table = 'aviso_leituras';

    protected $fillable = [
        'aviso_id',
        'user_id',
        'versao_envio',
        'lido_em',
    ];

    protected function casts(): array
    {
        return [
            'versao_envio' => 'integer',
            'lido_em' => 'datetime',
        ];
    }

    public function aviso(): BelongsTo
    {
        return $this->belongsTo(Aviso::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
