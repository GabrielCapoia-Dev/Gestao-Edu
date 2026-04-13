<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class Turma extends Model
{
    use HasFactory;
    use Notifiable;

    protected $table = 'turmas';

    protected $fillable = [
        'codigo',
        'nome',
        'turno',
        'id_serie',
        'id_escola',
    ];

    public function casts(): array
    {
        return [
            'codigo' => 'string',
            'nome' => 'string',
            'turno' => 'string',
            'id_serie' => 'integer',
            'id_escola' => 'integer',
        ];
    }

    public function serie()
    {
        return $this->belongsTo(Serie::class, 'id_serie');
    }

    public function escola()
    {
        return $this->belongsTo(Escola::class, 'id_escola');
    }

    public function componentes()
    {
        return $this->belongsToMany(
            ComponenteCurricular::class,
            'turma_componente_professor'
        )->withPivot('professor_id');
    }


    public function professores()
    {
        return $this->belongsToMany(
            Professor::class,
            'turma_componente_professor'
        )->withPivot('componente_curricular_id');
    }
}
