<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Professor extends Model
{
    use HasFactory;

    protected $table = 'professores';

    protected $fillable = [
        'user_id',
        'id_escola',
        'matricula',
        'nome',
        'email',
        'telefone',
        'funcao_administrativa_id',
        'portaria',
    ];

    protected function casts(): array
    {
        return [
            'matricula' => 'string',
            'nome' => 'string',
            'email' => 'string',
            'telefone' => 'string',
            'portaria' => 'string',
        ];
    }

    /**
     * Relação 1:1 com User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Verifica se o professor já tem conta de usuário
     */
    public function temContaUsuario(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Busca TODOS os professores pelo email (pode retornar múltiplos)
     */
    public static function buscarTodosPorEmail(string $email): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('email', strtolower(trim($email)))->get();
    }

    /**
     * Verifica se algum professor com esse email já tem conta
     */
    public static function emailJaTemConta(string $email): bool
    {
        return static::where('email', strtolower(trim($email)))
            ->whereNotNull('user_id')
            ->exists();
    }

    public function escola()
    {
        return $this->belongsTo(Escola::class, 'id_escola');
    }

    public function turmas()
    {
        return $this->belongsToMany(
            Turma::class,
            'turma_componente_professor'
        )->withPivot('componente_curricular_id');
    }

    public function componentesPorTurma()
    {
        return $this->belongsToMany(
            ComponenteCurricular::class,
            'turma_componente_professor'
        )->withPivot('turma_id');
    }

    public function funcaoAdministrativa()
    {
        return $this->belongsTo(FuncaoAdministrativa::class, 'funcao_administrativa_id');
    }

    /**
     * Turmas vinculadas através da função administrativa
     */
    public function turmasFuncao()
    {
        return $this->belongsToMany(
            Turma::class,
            'professor_funcao_turma',
            'professor_id',
            'turma_id'
        )->withTimestamps();
    }

    /**
     * Verifica se o professor tem função administrativa
     */
    public function temFuncaoAdministrativa(): bool
    {
        return !is_null($this->funcao_administrativa_id);
    }

    /**
     * Verifica se a função administrativa do professor tem relação com turmas
     */
    public function funcaoTemRelacaoTurma(): bool
    {
        return $this->funcaoAdministrativa?->tem_relacao_turma ?? false;
    }

    /**
     * Scope para professores SEM função administrativa (disponíveis para componentes)
     */
    public function scopeDisponivelParaComponente($query)
    {
        return $query->whereNull('funcao_administrativa_id');
    }

    /**
     * Scope para professores COM função administrativa
     */
    public function scopeComFuncaoAdministrativa($query)
    {
        return $query->whereNotNull('funcao_administrativa_id');
    }
}
