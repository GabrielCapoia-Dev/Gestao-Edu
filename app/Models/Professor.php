<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Professor extends Model
{
    use HasFactory;

    public const EMAIL_INSTITUCIONAL_DOMINIO = 'edu.umuarama.pr.gov.br';

    public const TURNOS = [
        'manha' => 'Manhã',
        'tarde' => 'Tarde',
        'integral' => 'Integral',
    ];

    protected $table = 'professores';

    protected $fillable = [
        'user_id',
        'servidor_id',
        'professor_matricula_id',
        'servidor_funcao_administrativa_id',
        'id_escola',
        'matricula',
        'turno',
        'nome',
        'email',
        'telefone',
        'ativo',
        'desativado_em',
        'desativado_por_id',
        'motivo_desativacao',
    ];

    protected function casts(): array
    {
        return [
            'matricula' => 'string',
            'turno' => 'string',
            'nome' => 'string',
            'email' => 'string',
            'telefone' => 'string',
            'ativo' => 'boolean',
            'desativado_em' => 'datetime',
            'desativado_por_id' => 'integer',
            'motivo_desativacao' => 'string',
        ];
    }

    /**
     * Relação 1:1 com User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function servidor(): BelongsTo
    {
        // Instância concreta Servidor (extends Pessoa) para typehints legados.
        return $this->belongsTo(Servidor::class, 'servidor_id')->withTrashed();
    }

    /** Centro da verdade da identidade (mesmo registro de servidores). */
    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'servidor_id')->withTrashed();
    }

    public function professorMatricula(): BelongsTo
    {
        return $this->belongsTo(ProfessorMatricula::class, 'professor_matricula_id');
    }

    public function vinculoFuncional(): BelongsTo
    {
        return $this->belongsTo(ServidorFuncaoAdministrativa::class, 'servidor_funcao_administrativa_id');
    }

    /**
     * Nome canônico: preferir Pessoa; colunas locais são espelho legado.
     */
    public function nomeCanonico(): string
    {
        $this->loadMissing('pessoa');

        return filled($this->pessoa?->nome) ? (string) $this->pessoa->nome : (string) ($this->attributes['nome'] ?? '');
    }

    public function emailCanonico(): ?string
    {
        $this->loadMissing('pessoa');

        if (filled($this->pessoa?->email)) {
            return (string) $this->pessoa->email;
        }

        $email = $this->attributes['email'] ?? null;

        return filled($email) ? (string) $email : null;
    }

    public function telefoneCanonico(): ?string
    {
        $this->loadMissing('pessoa');

        if (filled($this->pessoa?->telefone)) {
            return (string) $this->pessoa->telefone;
        }

        $telefone = $this->attributes['telefone'] ?? null;

        return filled($telefone) ? (string) $telefone : null;
    }

    public function turnoEfetivo(): ?string
    {
        $this->loadMissing('professorMatricula');

        if (filled($this->professorMatricula?->turno)) {
            return (string) $this->professorMatricula->turno;
        }

        return filled($this->turno) ? (string) $this->turno : null;
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
        return static::where('email', static::normalizarEmail($email))->get();
    }

    /**
     * Verifica se algum professor com esse email já tem conta
     */
    public static function emailJaTemConta(string $email): bool
    {
        return static::where('email', static::normalizarEmail($email))
            ->whereNotNull('user_id')
            ->exists();
    }

    public static function normalizarEmail(?string $email): string
    {
        return Pessoa::normalizarEmail($email) ?? '';
    }

    public static function emailInstitucionalValido(?string $email): bool
    {
        $emailNormalizado = static::normalizarEmail($email);

        if ($emailNormalizado === '') {
            return false;
        }

        return str_ends_with($emailNormalizado, '@' . static::EMAIL_INSTITUCIONAL_DOMINIO);
    }

    public static function turnosOptions(): array
    {
        return static::TURNOS;
    }

    public function turnoLabel(): string
    {
        return static::turnosOptions()[$this->turno] ?? 'Não informado';
    }

    public function rotuloParaVinculoTurma(): string
    {
        return sprintf(
            '%s - %s - %s',
            $this->turnoLabel(),
            filled($this->matricula) ? $this->matricula : 'Sem matrícula',
            filled($this->nome) ? $this->nome : 'Sem nome',
        );
    }

    public function setEmailAttribute(?string $value): void
    {
        $email = static::normalizarEmail($value);

        $this->attributes['email'] = $email !== '' ? $email : null;
    }

    public function escola()
    {
        return $this->belongsTo(Escola::class, 'id_escola');
    }

    public function desativadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'desativado_por_id')->withTrashed();
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

    public function componentesFuncionais()
    {
        return $this->belongsToMany(
            ComponenteCurricular::class,
            'professor_componente_funcional',
            'professor_id',
            'componente_curricular_id',
        )->withTimestamps();
    }

    /**
     * Verifica se o professor tem função administrativa
     */
    public function temFuncaoAdministrativa(): bool
    {
        return $this->servidor?->possuiFuncaoAtivaNaoProfessor() ?? false;
    }

    /**
     * Verifica se a função administrativa do professor tem relação com turmas
     */
    public function funcaoTemRelacaoTurma(): bool
    {
        return $this->servidor?->funcoesAtivas()
            ->where('funcao_administrativa.exige_professor', false)
            ->where('funcao_administrativa.tem_relacao_turma', true)
            ->exists() ?? false;
    }

    /**
     * Scope para professores SEM função administrativa (disponíveis para componentes)
     */
    public function scopeDisponivelParaComponente($query)
    {
        return $query
            ->where('ativo', true)
            ->whereDoesntHave('servidor.servidorFuncoesAtivas.funcaoAdministrativa', function ($funcoes): void {
                $funcoes->where('exige_professor', false);
            });
    }

    /**
     * Scope para professores COM função administrativa
     */
    public function scopeComFuncaoAdministrativa($query)
    {
        return $query->whereHas('servidor.servidorFuncoesAtivas.funcaoAdministrativa', function ($funcoes): void {
            $funcoes->where('exige_professor', false);
        });
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    public function scopeInativos($query)
    {
        return $query->where('ativo', false);
    }

}
