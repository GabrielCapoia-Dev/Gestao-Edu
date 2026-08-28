<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvaliacaoSnapshotEvento extends Model
{
    use HasUuids;

    public const TIPO_CONCLUSAO = 'conclusao';
    public const TIPO_TRANSFERENCIA = 'transferencia';
    public const TIPO_REMANEJAMENTO = 'remanejamento';

    protected $table = 'avaliacao_snapshot_eventos';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'criado_por_snapshot' => 'array',
            'publicado_em' => 'datetime',
            'versao' => 'integer',
            'schema_version' => 'integer',
        ];
    }

    public function snapshots(): HasMany { return $this->hasMany(AvaliacaoAlunoSnapshot::class, 'evento_id'); }
    public function resumos(): HasMany { return $this->hasMany(AvaliacaoSnapshotResumoComponente::class, 'evento_id'); }
}
