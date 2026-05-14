<?php

namespace App\Models;

use App\Models\Concerns\HasUuidCodigo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class Serie extends Model
{
    use HasFactory;
    use HasUuidCodigo;
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

    public function pautas()
    {
        return $this->hasMany(Pauta::class, 'serie_id');
    }

    public function avaliacoes()
    {
        return $this->belongsToMany(
            Avaliacao::class,
            'avaliacao_serie'
        )->withTimestamps();
    }
}
