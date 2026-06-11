<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class SetorAcesso extends Model
{
    protected $table = 'setor_acessos';

    protected $fillable = [
        'setor_origem_id',
        'setor_alvo_id',
        'pode_listar',
        'pode_editar',
        'pode_cancelar',
        'pode_encaminhar',
    ];

    protected $casts = [
        'pode_listar' => 'boolean',
        'pode_editar' => 'boolean',
        'pode_cancelar' => 'boolean',
        'pode_encaminhar' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $acesso): void {
            if (
                filled($acesso->setor_origem_id)
                && (int) $acesso->setor_origem_id === (int) $acesso->setor_alvo_id
            ) {
                throw ValidationException::withMessages([
                    'setor_alvo_id' => 'Um setor nao pode possuir uma regra de acesso para ele mesmo.',
                ]);
            }
        });
    }

    public function setorOrigem()
    {
        return $this->belongsTo(Setor::class, 'setor_origem_id');
    }

    public function setorAlvo()
    {
        return $this->belongsTo(Setor::class, 'setor_alvo_id');
    }
}
