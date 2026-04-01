<?php

namespace App\Relatorios;

use Illuminate\Database\Eloquent\Model;

class RelatorioComponenteProfessorFaltandoModel extends Model
{
    protected $table = null;
    public $timestamps = false;
    protected $guarded = [];

    protected $primaryKey = 'row_num';
    public $incrementing = false;
    protected $keyType = 'int';
}