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
}
