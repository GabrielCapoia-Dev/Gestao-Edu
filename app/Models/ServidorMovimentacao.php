<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServidorMovimentacao extends Model
{
    public $timestamps = false;

    protected $table = 'servidor_movimentacoes';

    protected $fillable = ['servidor_id', 'usuario_id', 'tipo', 'alteracoes', 'ocorrido_em'];

    protected function casts(): array
    {
        return ['alteracoes' => 'array', 'ocorrido_em' => 'datetime'];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class)->withTrashed();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id')->withTrashed();
    }
}
