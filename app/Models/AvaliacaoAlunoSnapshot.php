<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvaliacaoAlunoSnapshot extends Model
{
    protected $table = 'avaliacao_aluno_snapshots';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'schema_version' => 'integer'];
    }
}
