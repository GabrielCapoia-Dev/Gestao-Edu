<?php

namespace App\Models;

use Spatie\Permission\Models\Role as ModelsRole;

class Role extends ModelsRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'setor_id',
    ];

    public function setor()
    {
        return $this->belongsTo(Setor::class);
    }
}
