<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComponenteCurricular extends Model
{
    use HasFactory;

    protected $table = 'componentes_curriculares';

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

    public function series()
    {
        return $this->belongsToMany(
            Serie::class,
            'serie_componente_curricular'
        );
    }

    public function turmas()
    {
        return $this->belongsToMany(
            Turma::class,
            'turma_componente_professor'
        )->withPivot('professor_id');
    }
}
