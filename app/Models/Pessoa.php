<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Centro da verdade da identidade física no sistema.
 *
 * Tabela física permanece `servidores` nesta fase (rename de tabela fica para depois).
 * `Servidor` estende esta classe apenas por compatibilidade de imports legados.
 */
class Pessoa extends Model
{
    public const STATUS_ATIVO = 'ativo';
    public const STATUS_INATIVO = 'inativo';

    protected $table = 'servidores';

    protected $fillable = [
        'cpf',
        'user_id',
        'id_escola',
        'setor_id',
        'matricula',
        'nome',
        'email',
        'telefone',
        'status',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'id_escola' => 'integer',
            'setor_id' => 'integer',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ATIVO => 'Ativo',
            self::STATUS_INATIVO => 'Inativo',
        ];
    }

    public static function normalizarCpf(?string $cpf): ?string
    {
        if ($cpf === null) {
            return null;
        }

        $digitos = preg_replace('/\D+/', '', $cpf) ?? '';

        if ($digitos === '') {
            return null;
        }

        return $digitos;
    }

    public static function formatarCpf(?string $cpf): ?string
    {
        $digitos = static::normalizarCpf($cpf);

        if ($digitos === null || strlen($digitos) !== 11) {
            return $cpf;
        }

        return sprintf(
            '%s.%s.%s-%s',
            substr($digitos, 0, 3),
            substr($digitos, 3, 3),
            substr($digitos, 6, 3),
            substr($digitos, 9, 2),
        );
    }

    public function setCpfAttribute(?string $value): void
    {
        $this->attributes['cpf'] = static::normalizarCpf($value);
    }

    public function setEmailAttribute(?string $value): void
    {
        $email = Str::lower(trim((string) $value));

        $this->attributes['email'] = $email !== '' ? $email : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class, 'id_escola');
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function professores(): HasMany
    {
        return $this->hasMany(Professor::class, 'servidor_id');
    }

    public function professor()
    {
        return $this->hasOne(Professor::class, 'servidor_id');
    }

    public function professorMatriculas(): HasMany
    {
        return $this->hasMany(ProfessorMatricula::class, 'servidor_id');
    }

    /** Fonte canônica das matrículas funcionais da pessoa. */
    public function matriculas(): HasMany
    {
        return $this->hasMany(PessoaMatricula::class, 'servidor_id');
    }

    public function servidorFuncoes(): HasMany
    {
        return $this->hasMany(ServidorFuncaoAdministrativa::class, 'servidor_id');
    }

    public function servidorFuncoesAtivas(): HasMany
    {
        return $this->servidorFuncoes()->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO);
    }

    /** @alias vínculos funcionais com matrícula */
    public function vinculos(): HasMany
    {
        return $this->servidorFuncoes();
    }

    /** @alias vínculos ativos */
    public function vinculosAtivos(): HasMany
    {
        return $this->servidorFuncoesAtivas();
    }

    /** @alias vínculos ativos (compatibilidade) */
    public function vinculosAtivas(): HasMany
    {
        return $this->vinculosAtivos();
    }

    public function matriculasAtivas(): HasMany
    {
        return $this->servidorFuncoesAtivas();
    }

    public function funcoes(): BelongsToMany
    {
        return $this->belongsToMany(
            FuncaoAdministrativa::class,
            'servidor_funcao_administrativa',
            'servidor_id',
            'funcao_administrativa_id',
        )
            ->using(ServidorFuncaoAdministrativa::class)
            ->withPivot([
                'id',
                'matricula',
                'id_escola',
                'setor_id',
                'status',
                'origem',
                'portaria',
                'principal',
                'data_inicio',
                'data_fim',
            ])
            ->withTimestamps();
    }

    public function funcoesAtivas(): BelongsToMany
    {
        return $this->funcoes()->wherePivot('status', ServidorFuncaoAdministrativa::STATUS_ATIVO);
    }

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ATIVO);
    }

    public function temFuncaoProfessor(): bool
    {
        return $this->funcoesAtivas()
            ->where('funcao_administrativa.exige_professor', true)
            ->exists();
    }

    public function possuiFuncaoAtivaNaoProfessor(): bool
    {
        return $this->funcoesAtivas()
            ->where('funcao_administrativa.exige_professor', false)
            ->exists();
    }

    public function ehProfessor(): bool
    {
        return $this->professores()->where('ativo', true)->exists();
    }
}
