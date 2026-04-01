<?php

namespace App\Relatorios;

use Illuminate\Database\Eloquent\Model;

class RelatorioComponenteProfessorFaltandoModel extends Model
{
    protected $table = 'sub'; // alias da subquery
    public $timestamps = false;
    protected $guarded = [];

    // row_num é único e gerado pelo OVER(), use como PK
    protected $primaryKey = 'row_num';
    public $incrementing = false;
    protected $keyType = 'int';
}