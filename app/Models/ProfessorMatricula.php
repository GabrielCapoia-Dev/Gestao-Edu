<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * Matrícula funcional do cargo Professor (turno pertence à matrícula).
 *
 * Máximo de 2 matrículas distintas por pessoa — enforced no service.
 */
class ProfessorMatricula extends Model
{
    public const MAX_POR_PESSOA = 2;

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
        return $this->belongsTo(Pessoa::class, 'servidor_id');
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

    public function lotacoes(): HasMany
    {
        return $this->professores();
    }

    public function turnoLabel(): string
    {
        return Professor::turnosOptions()[$this->turno] ?? 'Não informado';
    }

    public static function assertTurnoValido(string $turno): void
    {
        if (! array_key_exists($turno, Professor::turnosOptions())) {
            throw ValidationException::withMessages([
                'matriculas_professor' => 'Turno informado é inválido.',
            ]);
        }
    }

    /**
     * Turnos de turma compatíveis com o turno da matrícula do professor.
     *
     * @return list<string>
     */
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
     * Turnos já usados pelas outras matrículas (exceto a de $indiceAtual).
     *
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
            if ($turno !== '' && array_key_exists($turno, Professor::TURNOS)) {
                $turnos[] = $turno;
            }
        }

        return array_values(array_unique($turnos));
    }

    /**
     * Opções de turno permitidas para uma matrícula, dado o que os irmãos já usam.
     *
     * Regras:
     * - integral sozinho (não combina com nenhuma outra);
     * - segunda matrícula só no par manhã+tarde.
     *
     * @param  list<string>  $turnosIrmaos
     * @return array<string, string> value => label
     */
    public static function turnosDisponiveisParaItem(array $turnosIrmaos, ?string $turnoAtual = null): array
    {
        $todos = Professor::turnosOptions();
        $turnosIrmaos = array_values(array_unique(array_filter(
            $turnosIrmaos,
            fn (string $turno): bool => array_key_exists($turno, $todos),
        )));

        if ($turnosIrmaos === []) {
            return $todos;
        }

        if (in_array('integral', $turnosIrmaos, true)) {
            // Outra matrícula já é integral: item atual não deveria existir;
            // se for legado, mantém só o valor atual.
            return filled($turnoAtual) && isset($todos[$turnoAtual])
                ? [$turnoAtual => $todos[$turnoAtual]]
                : [];
        }

        // Irmãos usam manhã e/ou tarde.
        $ocupados = array_values(array_intersect($turnosIrmaos, ['manha', 'tarde']));

        if ($ocupados === []) {
            return $todos;
        }

        if (count($ocupados) >= 2) {
            // Já há manhã e tarde nos irmãos: este item só pode manter o próprio (legado).
            return filled($turnoAtual) && isset($todos[$turnoAtual])
                ? [$turnoAtual => $todos[$turnoAtual]]
                : [];
        }

        // Um irmão em manhã ou tarde: este item só pode ser o complemento (não integral).
        $complemento = $ocupados[0] === 'manha' ? 'tarde' : 'manha';
        $permitidos = [$complemento];

        if (filled($turnoAtual) && $turnoAtual !== 'integral' && isset($todos[$turnoAtual])) {
            $permitidos[] = $turnoAtual;
        }

        $permitidos = array_values(array_unique($permitidos));

        return array_intersect_key($todos, array_flip($permitidos));
    }

    /**
     * Pode adicionar mais uma matrícula?
     *
     * @param  array<int|string, array<string, mixed>>  $matriculas
     */
    public static function podeAdicionarMatricula(array $matriculas): bool
    {
        $items = array_values(array_filter($matriculas, 'is_array'));
        $count = count($items);

        if ($count >= self::MAX_POR_PESSOA) {
            return false;
        }

        $turnos = self::turnosDosIrmaos($items);

        if (in_array('integral', $turnos, true)) {
            return false;
        }

        // Já tem manhã e tarde.
        if (in_array('manha', $turnos, true) && in_array('tarde', $turnos, true)) {
            return false;
        }

        return true;
    }

    /**
     * Valida o conjunto de turnos das matrículas da pessoa.
     *
     * @param  list<string>  $turnos
     * @throws ValidationException
     */
    public static function assertConjuntoTurnosValido(array $turnos): void
    {
        $turnos = array_values(array_filter($turnos));
        $count = count($turnos);

        if ($count === 0) {
            return;
        }

        foreach ($turnos as $turno) {
            self::assertTurnoValido((string) $turno);
        }

        if ($count > self::MAX_POR_PESSOA) {
            throw ValidationException::withMessages([
                'matriculas_professor' => 'Cada pessoa pode ter no máximo '.self::MAX_POR_PESSOA.' matrículas.',
            ]);
        }

        if (count($turnos) !== count(array_unique($turnos))) {
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

        if ($count === 2) {
            $set = collect($turnos)->sort()->values()->all();
            if ($set !== ['manha', 'tarde']) {
                throw ValidationException::withMessages([
                    'matriculas_professor' => 'Duas matrículas só são permitidas no par manhã + tarde.',
                ]);
            }
        }
    }
}
