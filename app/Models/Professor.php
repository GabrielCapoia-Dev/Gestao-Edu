<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Professor extends Model
{
    protected $table = 'professores';

    protected $fillable = [
        'id_escola',
        'matricula',
        'nome',
        'email',
        'turno',
        'professor_srm',
        'profissional_apoio',
    ];

    public function alunos()
    {
        return $this->hasMany(Aluno::class);
    }

    public function escola()
    {
        return $this->belongsTo(Escola::class, 'id_escola');
    }

    /**
     * Relação 1:N com especializações — igual Aluno::retencoes()
     */
    public function especializacoes()
    {
        return $this->hasMany(ProfessorEspecializacao::class, 'id_professor');
    }

    /**
     * Accessor "virtual" para usar em IconColumn::make('especializacao_educacao_especial')
     * Retorna true se QUALQUER especialização do professor for de Educação Especial.
     */
    public function getEspecializacaoEducacaoEspecialAttribute(): bool
    {
        return $this->especializacoes
            ->contains('especializacao_educacao_especial', true);
    }
}
