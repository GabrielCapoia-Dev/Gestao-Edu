<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuncaoAdministrativa extends Model
{
    protected $table = 'funcao_administrativa';

    protected $fillable = [
        'nome',
        'portaria',
        'tem_relacao_turma',
    ];

    protected function casts(): array
    {
        return [
            'nome' => 'string',
            'portaria' => 'string',
            'tem_relacao_turma' => 'boolean',
        ];
    }

    public function professores()
    {
        return $this->hasMany(Professor::class, 'funcao_administrativa_id');
    }
}