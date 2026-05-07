<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aluno extends Model
{
    use HasFactory;

    public const STATUS_MATRICULADO = 'matriculado';

    public const STATUS_REMANEJADO = 'remanejado';

    public const STATUS_TRANSFERIDO = 'transferido';

    public const STATUS_APROVADO = 'aprovado';

    public const STATUS_RETIDO = 'retido';

    protected $table = 'alunos';

    protected $fillable = [
        'nome',
        'cgm',
        'cgm_matricula_ativa',
        'data_nascimento',
        'id_turma',
        'status',
        'status_alterado_em',
        'status_alterado_por',
        'status_motivo',
        'aluno_origem_id',
        'turma_origem_id',
        'movimentacao_origem',
    ];

    protected function casts(): array
    {
        return [
            'nome' => 'string',
            'cgm' => 'string',
            'cgm_matricula_ativa' => 'string',
            'data_nascimento' => 'date',
            'id_turma' => 'integer',
            'status' => 'string',
            'status_alterado_em' => 'datetime',
            'status_alterado_por' => 'integer',
            'aluno_origem_id' => 'integer',
            'turma_origem_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Aluno $aluno): void {
            $aluno->cgm = self::normalizarCgm($aluno->cgm);
            $aluno->status = $aluno->status ?: self::STATUS_MATRICULADO;
            $aluno->cgm_matricula_ativa = $aluno->status === self::STATUS_MATRICULADO
                ? $aluno->cgm
                : null;

            if (! $aluno->status_alterado_em) {
                $aluno->status_alterado_em = now();
            }
        });
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_MATRICULADO => 'Matriculado',
            self::STATUS_REMANEJADO => 'Remanejado',
            self::STATUS_TRANSFERIDO => 'Transferido',
            self::STATUS_APROVADO => 'Aprovado',
            self::STATUS_RETIDO => 'Retido',
        ];
    }

    public static function statusFinais(): array
    {
        return [
            self::STATUS_TRANSFERIDO,
            self::STATUS_APROVADO,
            self::STATUS_RETIDO,
        ];
    }

    public static function normalizarCgm(?string $cgm): string
    {
        return trim((string) $cgm);
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function estaMatriculado(): bool
    {
        return $this->status === self::STATUS_MATRICULADO;
    }

    public function turma()
    {
        return $this->belongsTo(Turma::class, 'id_turma');
    }

    public function statusAlteradoPor()
    {
        return $this->belongsTo(User::class, 'status_alterado_por');
    }

    public function alunoOrigem()
    {
        return $this->belongsTo(self::class, 'aluno_origem_id');
    }

    public function turmaOrigem()
    {
        return $this->belongsTo(Turma::class, 'turma_origem_id');
    }

    public function avaliacaoRespostas()
    {
        return $this->hasMany(AvaliacaoResposta::class, 'aluno_id');
    }

    public function avaliacaoInformacoesComplementares()
    {
        return $this->hasMany(AvaliacaoInformacaoComplementar::class, 'aluno_id');
    }
}
