<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ServidorFuncaoAdministrativa extends Pivot
{
    public const STATUS_ATIVO = 'ativo';
    public const STATUS_INATIVO = 'inativo';

    protected $table = 'servidor_funcao_administrativa';

    public $incrementing = true;

    protected $fillable = [
        'servidor_id',
        'funcao_administrativa_id',
        'id_escola',
        'setor_id',
        'status',
        'origem',
        'portaria',
        'data_inicio',
        'data_fim',
    ];

    protected function casts(): array
    {
        return [
            'servidor_id' => 'integer',
            'funcao_administrativa_id' => 'integer',
            'id_escola' => 'integer',
            'setor_id' => 'integer',
            'data_inicio' => 'date',
            'data_fim' => 'date',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class, 'servidor_id');
    }

    public function funcaoAdministrativa(): BelongsTo
    {
        return $this->belongsTo(FuncaoAdministrativa::class, 'funcao_administrativa_id');
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class, 'id_escola');
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }
}
