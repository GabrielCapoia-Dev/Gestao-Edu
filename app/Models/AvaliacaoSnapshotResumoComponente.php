<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvaliacaoSnapshotResumoComponente extends Model
{
    protected $table = 'avaliacao_snapshot_resumos_componentes';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['percentual' => 'float'];
    }
}
