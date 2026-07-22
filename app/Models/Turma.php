<?php

namespace App\Models;

use App\Models\Concerns\HasUuidCodigo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Turma extends Model
{
    use HasFactory;
    use HasUuidCodigo;
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
        )->withPivot('professor_id', 'tem_professor');
    }


    public function professores()
    {
        return $this->belongsToMany(
            Professor::class,
            'turma_componente_professor'
        )->withPivot('componente_curricular_id', 'tem_professor');
    }

    public function alunos()
    {
        return $this->hasMany(Aluno::class, 'id_turma');
    }

    public function avaliacoes()
    {
        return $this->belongsToMany(Avaliacao::class, 'avaliacao_turma')
            ->withTimestamps();
    }

    public function alocacoesTransporte(): BelongsToMany
    {
        return $this->belongsToMany(
            EventoCalendarioTransporteAlocacao::class,
            'evento_transporte_alocacao_turma',
            'turma_id',
            'alocacao_id',
        )->withTimestamps();
    }

    public function avaliacaoDocumentos()
    {
        return $this->hasMany(AvaliacaoAlunoDocumento::class, 'turma_id');
    }

}
