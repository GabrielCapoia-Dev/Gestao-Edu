<?php

namespace App\Models;

use App\Services\PessoaEmailService;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Centro da verdade da identidade física no sistema.
 *
 * Tabela física permanece `servidores` nesta fase (rename de tabela fica para depois).
 * `Servidor` estende esta classe apenas por compatibilidade de imports legados.
 */
class Pessoa extends Model
{
    use SoftDeletes;

    public const STATUS_ATIVO = 'ativo';

    public const STATUS_INATIVO = 'inativo';

    public const CARGO_PENDENTE_CODIGO = 'cargo_pendente';

    public const CARGA_HORARIA_20 = 20;

    public const CARGA_HORARIA_40 = 40;

    public const CARGAS_HORARIAS = [
        self::CARGA_HORARIA_20 => '20 horas semanais',
        self::CARGA_HORARIA_40 => '40 horas semanais',
    ];

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
        'carga_horaria',
        'jornada',
        'lotacao_id',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'id_escola' => 'integer',
            'setor_id' => 'integer',
            'carga_horaria' => 'integer',
            'jornada' => 'boolean',
            'lotacao_id' => 'integer',
            'email_duplicado' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Pessoa $pessoa): void {
            static::assertDadosFuncionaisValidos($pessoa->carga_horaria, $pessoa->jornada);
            app(PessoaEmailService::class)->assertDisponivel($pessoa);

            if ($pessoa->exists && $pessoa->isDirty('status') && $pessoa->status === self::STATUS_ATIVO) {
                if (blank($pessoa->email) || filter_var($pessoa->email, FILTER_VALIDATE_EMAIL) === false) {
                    throw ValidationException::withMessages([
                        'status' => 'Informe um e-mail válido antes de ativar a pessoa.',
                    ]);
                }

                $possuiCargoValido = $pessoa->professores()->where('ativo', true)->exists()
                    || $pessoa->vinculosAtivos()
                        ->whereHas('funcaoAdministrativa', fn (Builder $cargos): Builder => $cargos
                            ->where('codigo', '<>', self::CARGO_PENDENTE_CODIGO))
                        ->exists();

                if (! $possuiCargoValido) {
                    throw ValidationException::withMessages([
                        'status' => 'Defina um cargo antes de ativar a pessoa.',
                    ]);
                }

                if (blank($pessoa->user_id) || ! $pessoa->user || $pessoa->user->trashed()) {
                    throw ValidationException::withMessages([
                        'status' => 'A pessoa precisa possuir um usuário válido antes de ser ativada.',
                    ]);
                }
            }

            if (! $pessoa->isDirty('status') || $pessoa->status !== self::STATUS_INATIVO) {
                return;
            }

            $operador = Auth::user();
            $alvo = $pessoa->user;

            if ($operador instanceof User && $alvo instanceof User) {
                if ((int) $operador->getKey() === (int) $alvo->getKey()) {
                    throw ValidationException::withMessages([
                        'status' => 'Você não pode inativar o próprio cadastro.',
                    ]);
                }

                if (((int) $alvo->getKey() === 1 || $alvo->hasRole('Admin')) && ! $operador->hasRole('Admin')) {
                    throw ValidationException::withMessages([
                        'status' => 'Somente um administrador pode inativar outro administrador.',
                    ]);
                }
            }
        });

        static::saved(function (Pessoa $pessoa): void {
            if ($pessoa->wasChanged('status') && $pessoa->status === self::STATUS_INATIVO && $pessoa->user) {
                app(UserService::class)->invalidarCredenciais($pessoa->user);
            }
        });
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ATIVO => 'Ativo',
            self::STATUS_INATIVO => 'Inativo',
        ];
    }

    /** @return array<int, string> */
    public static function cargaHorariaOptions(): array
    {
        return self::CARGAS_HORARIAS;
    }

    public function cargaHorariaLabel(): string
    {
        if (Schema::hasColumn('professor_matriculas', 'carga_horaria')) {
            $matriculas = $this->relationLoaded('matriculas') ? $this->matriculas : $this->matriculas()->get();
            if ($matriculas->isNotEmpty()) {
                return $matriculas
                    ->map(fn (PessoaMatricula $matricula): string => "{$matricula->matricula}: {$matricula->cargaHorariaLabel()}")
                    ->implode('; ');
            }
        }

        return self::CARGAS_HORARIAS[$this->carga_horaria] ?? 'Não informada';
    }

    public function jornadaLabel(): string
    {
        if (Schema::hasColumn('professor_matriculas', 'jornada')) {
            $matriculas = $this->relationLoaded('matriculas') ? $this->matriculas : $this->matriculas()->get();
            if ($matriculas->isNotEmpty()) {
                return $matriculas->contains(fn (PessoaMatricula $matricula): bool => (bool) $matricula->jornada)
                    ? 'Sim'
                    : 'Não';
            }
        }

        return match ($this->jornada) {
            true => 'Sim',
            false => 'Não',
            default => 'Não informada',
        };
    }

    public function lotacaoLabel(): string
    {
        if (! $this->lotacao) {
            return 'Não informada';
        }

        return collect([$this->lotacao->codigo, $this->lotacao->nome])
            ->filter(fn (mixed $valor): bool => filled($valor))
            ->implode(' - ') ?: 'Não informada';
    }

    public static function assertDadosFuncionaisValidos(
        int|string|null $cargaHoraria,
        bool|int|string|null $jornada,
    ): void {
        $cargaHoraria = filled($cargaHoraria) ? (int) $cargaHoraria : null;
        $jornada = $jornada === null ? null : filter_var($jornada, FILTER_VALIDATE_BOOLEAN);

        if ($cargaHoraria !== null && ! array_key_exists($cargaHoraria, self::CARGAS_HORARIAS)) {
            throw ValidationException::withMessages([
                'carga_horaria' => 'A carga horária deve ser de 20 ou 40 horas semanais.',
            ]);
        }

        if ($jornada === true && $cargaHoraria !== self::CARGA_HORARIA_20) {
            throw ValidationException::withMessages([
                'jornada' => 'Jornada adicional só é permitida para servidores de 20 horas semanais.',
            ]);
        }
    }

    /** @param list<int|string> $escolaIds */
    public static function assertLotacaoVinculada(
        int|string|null $lotacaoId,
        array $escolaIds,
        string $campo = 'lotacao_id',
    ): void {
        if (! filled($lotacaoId)) {
            return;
        }

        $escolaIds = collect($escolaIds)
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($escolaIds === [] || ! Lotacao::query()
            ->whereKey((int) $lotacaoId)
            ->whereIn('escola_id', $escolaIds)
            ->exists()) {
            throw ValidationException::withMessages([
                $campo => 'A lotação deve pertencer a uma das escolas vinculadas à pessoa.',
            ]);
        }
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

    public static function cpfValido(?string $cpf): bool
    {
        $digitos = static::normalizarCpf($cpf);

        if ($digitos === null || strlen($digitos) !== 11 || preg_match('/^(\d)\1{10}$/', $digitos)) {
            return false;
        }

        foreach ([9, 10] as $quantidade) {
            $soma = 0;

            for ($indice = 0; $indice < $quantidade; $indice++) {
                $soma += (int) $digitos[$indice] * ($quantidade + 1 - $indice);
            }

            $verificador = ($soma * 10) % 11;

            if ((int) $digitos[$quantidade] !== ($verificador === 10 ? 0 : $verificador)) {
                return false;
            }
        }

        return true;
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
        $this->attributes['email'] = static::normalizarEmail($value);
    }

    public function setNomeAttribute(string $value): void
    {
        $this->attributes['nome'] = Str::upper($value);
    }

    public static function normalizarEmail(?string $email): ?string
    {
        $normalizado = Str::lower(trim((string) $email));

        return $normalizado !== '' ? $normalizado : null;
    }

    public function scopeComEmailDuplicado(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->whereNotNull("{$table}.email_normalizado")
            ->whereExists(function ($duplicados) use ($table): void {
                $duplicados
                    ->selectRaw('1')
                    ->from("{$table} as pessoa_email_duplicado")
                    ->whereColumn('pessoa_email_duplicado.email_normalizado', "{$table}.email_normalizado")
                    ->whereColumn('pessoa_email_duplicado.id', '<>', "{$table}.id")
                    ->whereNull('pessoa_email_duplicado.deleted_at');
            });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function escola(): BelongsTo
    {
        return $this->belongsTo(Escola::class, 'id_escola');
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    public function lotacao(): BelongsTo
    {
        return $this->belongsTo(Lotacao::class);
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

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(ServidorMovimentacao::class, 'servidor_id')->latest('ocorrido_em');
    }

    public function saldoEleitoralMovimentacoes(): HasMany
    {
        return $this->hasMany(SaldoEleitoral::class, 'servidor_id')->latest('created_at');
    }

    public function servidorFuncoesAtivas(): HasMany
    {
        return $this->servidorFuncoes()->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO);
    }

    public function alocacoesTransporte(): HasMany
    {
        return $this->hasMany(EventoCalendarioTransporteAlocacao::class, 'motorista_id');
    }

    public function alocacoesTransporteAtivas(): HasMany
    {
        return $this->alocacoesTransporte()->whereNull('removido_em');
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
