<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipeGestora extends Professor
{
    protected static function booted(): void
    {
        static::saved(function (self $model) {
            // O Filament passa turmasFuncao como array no state
            // mas não consegue fazer sync automático sem ->relationship()
            // Então tratamos via accessor temporário
            if (property_exists($model, '_turmasFuncaoSync')) {
                $model->turmasFuncao()->sync($model->_turmasFuncaoSync ?? []);
            }
        });
    }
    
}
