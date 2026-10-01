<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

/**
 * Matrícula funcional da pessoa.
 *
 * A tabela física continua `professor_matriculas` por compatibilidade. O nome
 * neutro é a API canônica para novos fluxos; ProfessorMatricula permanece como
 * alias legado.
 */
class PessoaMatricula extends Model
{
    use SoftDeletes;

    public const MAX_POR_PESSOA = 2;

    public const TURNOS = [
        'manha' => 'Manhã',
        'tarde' => 'Tarde',
        'integral' => 'Integral',
    ];

    protected $table = 'professor_matriculas';

    protected $fillable = [
        'servidor_id',
        'matricula',
        'turno',
        'carga_horaria',
        'jornada',
    ];

    protected function casts(): array
    {
        return [
            'servidor_id' => 'integer',
            'matricula' => 'string',
            'turno' => 'string',
            'carga_horaria' => 'integer',
            'jornada' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PessoaMatricula $matricula): void {
            self::assertTurnoValido((string) $matricula->turno);
            $matricula->carga_horaria = $matricula->turno === 'integral'
                ? Pessoa::CARGA_HORARIA_40
                : Pessoa::CARGA_HORARIA_20;

            if ($matricula->turno === 'integral') {
                $matricula->jornada = false;
            }
        });
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'servidor_id')->withTrashed();
    }

    /** @alias compatibilidade */
    public function servidor(): BelongsTo
    {
        return $this->pessoa();
    }

    public function professores(): HasMany
    {
        return $this->hasMany(Professor::class, 'professor_matricula_id');
    }

    /** @alias compatibilidade */
    public function lotacoes(): HasMany
    {
        return $this->professores();
    }

    public static function turnosOptions(): array
    {
        return self::TURNOS;
    }

    public function turnoLabel(): string
    {
        return self::TURNOS[$this->turno] ?? 'Não informado';
    }

    public function cargaHorariaLabel(): string
    {
        return Pessoa::CARGAS_HORARIAS[$this->carga_horaria] ?? 'Não informada';
    }

    public function jornadaLabel(): string
    {
        return $this->jornada ? 'Jornada' : 'Matrícula comum';
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $matriculas
     */
    public static function assertConjuntoFuncionalValido(array $matriculas, string $campo = 'matriculas'): void
    {
        $matriculas = array_values(array_filter($matriculas, 'is_array'));
        $turnos = collect($matriculas)->pluck('turno')->filter()->map(fn (mixed $turno): string => (string) $turno)->all();
        self::assertConjuntoTurnosValido($turnos);

        if (count($matriculas) < 1) {
            throw ValidationException::withMessages([$campo => 'A pessoa deve possuir ao menos uma matrícula.']);
        }

        $jornadas = collect($matriculas)->filter(
            fn (array $matricula): bool => filter_var($matricula['jornada'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );

        foreach ($matriculas as $matricula) {
            $turno = (string) ($matricula['turno'] ?? '');
            $carga = filled($matricula['carga_horaria'] ?? null)
                ? (int) $matricula['carga_horaria']
                : ($turno === 'integral' ? Pessoa::CARGA_HORARIA_40 : Pessoa::CARGA_HORARIA_20);

            if (($turno === 'integral' && $carga !== Pessoa::CARGA_HORARIA_40)
                || (in_array($turno, ['manha', 'tarde'], true) && $carga !== Pessoa::CARGA_HORARIA_20)) {
                throw ValidationException::withMessages([
                    $campo => 'A carga horária deve ser 20 horas para manhã ou tarde e 40 horas para turno integral.',
                ]);
            }
        }

        if ($jornadas->isEmpty()) {
            return;
        }

        if ($jornadas->count() !== 1 || count($matriculas) !== 2 || in_array('integral', $turnos, true)) {
            throw ValidationException::withMessages([
                $campo => 'A jornada exige uma matrícula comum de 20 horas e uma matrícula de jornada no turno oposto.',
            ]);
        }

        $numeros = collect($matriculas)->pluck('matricula')->map(fn (mixed $numero): string => mb_strtolower(trim((string) $numero)));
        if ($numeros->filter()->count() !== 2 || $numeros->unique()->count() !== 2
            || collect($turnos)->sort()->values()->all() !== ['manha', 'tarde']) {
            throw ValidationException::withMessages([
                $campo => 'A jornada exige duas matrículas diferentes, uma de manhã e outra à tarde.',
            ]);
        }
    }

    public static function assertTurnoValido(string $turno): void
    {
        if (! array_key_exists($turno, self::TURNOS)) {
            throw ValidationException::withMessages([
                'matriculas_professor' => 'Turno informado é inválido.',
            ]);
        }
    }

    /** @return list<string> */
    public static function turnosTurmaCompativeis(string $turnoMatricula): array
    {
        return match ($turnoMatricula) {
            'manha' => ['manha'],
            'tarde' => ['tarde'],
            'integral' => ['manha', 'tarde', 'integral'],
            default => [$turnoMatricula],
        };
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $matriculas
     * @return list<string>
     */
    public static function turnosDosIrmaos(array $matriculas, int|string|null $indiceAtual = null): array
    {
        $turnos = [];

        foreach ($matriculas as $key => $item) {
            if ($indiceAtual !== null && (string) $key === (string) $indiceAtual) {
                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $turno = (string) ($item['turno'] ?? '');
            if ($turno !== '' && array_key_exists($turno, self::TURNOS)) {
                $turnos[] = $turno;
            }
        }

        return array_values(array_unique($turnos));
    }

    /**
     * @param  list<string>  $turnosIrmaos
     * @return array<string, string>
     */
    public static function turnosDisponiveisParaItem(array $turnosIrmaos, ?string $turnoAtual = null): array
    {
        $todos = self::TURNOS;
        $turnosIrmaos = array_values(array_unique(array_filter(
            $turnosIrmaos,
            fn (string $turno): bool => array_key_exists($turno, $todos),
        )));

        if ($turnosIrmaos === []) {
            return $todos;
        }

        if (in_array('integral', $turnosIrmaos, true)) {
            return filled($turnoAtual) && isset($todos[$turnoAtual])
                ? [$turnoAtual => $todos[$turnoAtual]]
                : [];
        }

        $ocupados = array_values(array_intersect($turnosIrmaos, ['manha', 'tarde']));

        if ($ocupados === []) {
            return $todos;
        }

        if (count($ocupados) >= 2) {
            return filled($turnoAtual) && isset($todos[$turnoAtual])
                ? [$turnoAtual => $todos[$turnoAtual]]
                : [];
        }

        $complemento = $ocupados[0] === 'manha' ? 'tarde' : 'manha';
        $permitidos = [$complemento];

        if (filled($turnoAtual) && $turnoAtual !== 'integral' && isset($todos[$turnoAtual])) {
            $permitidos[] = $turnoAtual;
        }

        return array_intersect_key($todos, array_flip(array_values(array_unique($permitidos))));
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $matriculas
     *
     * @throws ValidationException
     */
    public static function assertCompativelComCargaHoraria(
        int|string|null $cargaHoraria,
        bool|int|string|null $jornada,
        array $matriculas,
        string $campo = 'matriculas',
    ): void {
        if (! filled($cargaHoraria)) {
            return;
        }

        $cargaHoraria = (int) $cargaHoraria;
        $jornada = filter_var($jornada, FILTER_VALIDATE_BOOLEAN);
        $matriculas = array_values(array_filter($matriculas, 'is_array'));
        $turnos = collect($matriculas)
            ->pluck('turno')
            ->filter(fn (mixed $turno): bool => filled($turno))
            ->map(fn (mixed $turno): string => (string) $turno)
            ->values()
            ->all();

        self::assertConjuntoTurnosValido($turnos);

        if ($cargaHoraria === Pessoa::CARGA_HORARIA_40) {
            if (count($matriculas) !== 1 || $turnos !== ['integral']) {
                throw ValidationException::withMessages([
                    $campo => 'Servidor de 40 horas deve possuir uma única matrícula no turno integral.',
                ]);
            }

            return;
        }

        if ($cargaHoraria !== Pessoa::CARGA_HORARIA_20) {
            return;
        }

        if (! $jornada) {
            if (! in_array(count($matriculas), [1, 2], true)
                || (count($matriculas) === 1 && ! in_array($turnos[0] ?? null, ['manha', 'tarde', 'integral'], true))
                || (count($matriculas) === 2 && collect($turnos)->sort()->values()->all() !== ['manha', 'tarde'])) {
                throw ValidationException::withMessages([
                    $campo => 'Sem jornada, informe uma matrícula (manhã, tarde ou integral) ou duas matrículas (manhã e tarde).',
                ]);
            }

            return;
        }

        $numeros = collect($matriculas)
            ->pluck('matricula')
            ->map(fn (mixed $numero): string => mb_strtolower(trim((string) $numero)))
            ->filter()
            ->values();

        if (count($matriculas) !== 2
            || collect($turnos)->sort()->values()->all() !== ['manha', 'tarde']
            || $numeros->count() !== 2
            || $numeros->unique()->count() !== 2) {
            throw ValidationException::withMessages([
                $campo => 'A jornada exige duas matrículas diferentes, uma de manhã e outra à tarde.',
            ]);
        }
    }

    /** @param array<int|string, array<string, mixed>> $matriculas */
    public static function podeAdicionarMatricula(array $matriculas): bool
    {
        $items = array_values(array_filter($matriculas, 'is_array'));

        if (count($items) >= self::MAX_POR_PESSOA) {
            return false;
        }

        $turnos = self::turnosDosIrmaos($items);

        return ! in_array('integral', $turnos, true)
            && ! (in_array('manha', $turnos, true) && in_array('tarde', $turnos, true));
    }

    /**
     * @param  list<string>  $turnos
     *
     * @throws ValidationException
     */
    public static function assertConjuntoTurnosValido(array $turnos): void
    {
        $turnos = array_values(array_filter($turnos));
        $count = count($turnos);

        foreach ($turnos as $turno) {
            self::assertTurnoValido((string) $turno);
        }

        if ($count > self::MAX_POR_PESSOA) {
            throw ValidationException::withMessages([
                'matriculas_professor' => 'Cada pessoa pode ter no máximo '.self::MAX_POR_PESSOA.' matrículas.',
            ]);
        }

        if ($count !== count(array_unique($turnos))) {
            throw ValidationException::withMessages([
                'matriculas_professor' => 'Não é permitido duas matrículas no mesmo turno.',
            ]);
        }

        if (in_array('integral', $turnos, true)) {
            if ($count > 1) {
                throw ValidationException::withMessages([
                    'matriculas_professor' => 'Matrícula integral já cobre manhã e tarde. Remova a outra matrícula ou altere o turno.',
                ]);
            }

            return;
        }

        if ($count === 2 && collect($turnos)->sort()->values()->all() !== ['manha', 'tarde']) {
            throw ValidationException::withMessages([
                'matriculas_professor' => 'Duas matrículas só são permitidas no par manhã + tarde.',
            ]);
        }
    }
}
