<?php

namespace App\Models;

use App\Models\Concerns\HasUuidCodigo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComponenteCurricular extends Model
{
    use HasFactory;
    use HasUuidCodigo;

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

    public function pautas()
    {
        return $this->hasMany(Pauta::class, 'componente_curricular_id');
    }

    public function avaliacoes()
    {
        return $this->belongsToMany(
            Avaliacao::class,
            'avaliacao_componente'
        )->withTimestamps();
    }
}
