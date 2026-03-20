<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class Serie extends Model
{
    use HasFactory;
    use Notifiable;

    protected $table = 'series';

    protected $fillable = [
        'codigo',
        'nome',
    ];

    public function casts(): array
    {
        return [
            'codigo' => 'string',
            'nome' => 'string',
        ];
    }

    public function turmas()
    {
        return $this->hasMany(Turma::class, 'id_serie');
    }

    public function componentesCurriculares()
    {
        return $this->belongsToMany(
            ComponenteCurricular::class,
            'serie_componente_curricular'
        );
    }
}
