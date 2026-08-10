<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    ];

    protected function casts(): array
    {
        return [
            'servidor_id' => 'integer',
            'matricula' => 'string',
            'turno' => 'string',
        ];
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
