<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Aluno extends Model
{
    use HasFactory;

    public const TIPO_VINCULO_PRINCIPAL = 'principal';

    public const TIPO_VINCULO_CONTRA_TURNO = 'contra_turno';

    public const STATUS_MATRICULADO = 'matriculado';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_REMANEJADO = 'remanejado';

    public const STATUS_TRANSFERIDO = 'transferido';

    public const STATUS_APROVADO = 'aprovado';

    public const STATUS_RETIDO = 'retido';

    public const STATUS_CONTRA_TURNO_ENCERRADO = 'contra_turno_encerrado';

    protected $table = 'alunos';

    protected $fillable = [
        'nome',
        'cgm',
        'cgm_matricula_ativa',
        'cgm_contra_turno_ativo',
        'cgm_unidade_matricula_ativa',
        'data_nascimento',
        'sexo',
        'data_matricula',
        'id_turma',
        'tipo_vinculo',
        'permite_contra_turno',
        'status',
        'status_alterado_em',
        'status_alterado_por',
        'status_motivo',
        'aluno_origem_id',
        'turma_origem_id',
        'movimentacao_origem',
        'pendencia_origem_aluno_id',
    ];

    protected function casts(): array
    {
        return [
            'nome' => 'string',
            'cgm' => 'string',
            'cgm_matricula_ativa' => 'string',
            'cgm_contra_turno_ativo' => 'string',
            'cgm_unidade_matricula_ativa' => 'string',
            'data_nascimento' => 'date',
            'sexo' => 'string',
            'data_matricula' => 'date',
            'id_turma' => 'integer',
            'tipo_vinculo' => 'string',
            'permite_contra_turno' => 'boolean',
            'status' => 'string',
            'status_alterado_em' => 'datetime',
            'status_alterado_por' => 'integer',
            'aluno_origem_id' => 'integer',
            'turma_origem_id' => 'integer',
            'pendencia_origem_aluno_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Aluno $aluno): void {
            $aluno->cgm = self::normalizarCgm($aluno->cgm);
            $aluno->tipo_vinculo = $aluno->tipo_vinculo ?: self::TIPO_VINCULO_PRINCIPAL;
            $aluno->status = $aluno->status ?: self::STATUS_MATRICULADO;
            $aluno->permite_contra_turno = $aluno->isContraTurno()
                ? false
                : (bool) $aluno->permite_contra_turno;
            $aluno->cgm_matricula_ativa = $aluno->isPrincipal() && $aluno->status === self::STATUS_MATRICULADO
                ? $aluno->cgm
                : null;
            $aluno->cgm_contra_turno_ativo = $aluno->isContraTurno() && $aluno->status === self::STATUS_MATRICULADO
                ? $aluno->cgm
                : null;

            $escolaId = ! $aluno->relationLoaded('turma') || ! $aluno->turma
                ? (int) Turma::query()->whereKey((int) $aluno->id_turma)->value('id_escola')
                : (int) $aluno->turma->id_escola;

            $aluno->cgm_unidade_matricula_ativa = $aluno->estaAtivoNaUnidade()
                ? self::chaveCgmUnidade($escolaId, $aluno->cgm)
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
            self::STATUS_PENDENTE => 'Pendente',
            self::STATUS_REMANEJADO => 'Remanejado',
            self::STATUS_TRANSFERIDO => 'Transferido',
            self::STATUS_APROVADO => 'Aprovado',
            self::STATUS_RETIDO => 'Retido',
            self::STATUS_CONTRA_TURNO_ENCERRADO => 'Contra turno encerrado',
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

    public static function chaveCgmUnidade(int $escolaId, ?string $cgm): ?string
    {
        $cgm = self::normalizarCgm($cgm);

        if ($escolaId <= 0 || $cgm === '') {
            return null;
        }

        return $escolaId.'|'.$cgm;
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status] ?? ucfirst((string) $this->status);
    }

    public static function tiposVinculoOptions(): array
    {
        return [
            self::TIPO_VINCULO_PRINCIPAL => 'Principal',
            self::TIPO_VINCULO_CONTRA_TURNO => 'Contra turno',
        ];
    }

    public function tipoVinculoLabel(): string
    {
        $tipoVinculo = $this->tipo_vinculo ?: self::TIPO_VINCULO_PRINCIPAL;

        return self::tiposVinculoOptions()[$tipoVinculo] ?? ucfirst((string) $tipoVinculo);
    }

    public function isPrincipal(): bool
    {
        return $this->tipo_vinculo === self::TIPO_VINCULO_PRINCIPAL;
    }

    public function isContraTurno(): bool
    {
        return $this->tipo_vinculo === self::TIPO_VINCULO_CONTRA_TURNO;
    }

    public function estaMatriculado(): bool
    {
        return $this->status === self::STATUS_MATRICULADO;
    }

    public function estaPendente(): bool
    {
        return $this->status === self::STATUS_PENDENTE;
    }

    public function estaAtivoNaUnidade(): bool
    {
        return $this->isPrincipal()
            && in_array($this->status, [self::STATUS_MATRICULADO, self::STATUS_PENDENTE], true);
    }

    public function podeExportarDados(): bool
    {
        return ! $this->estaPendente();
    }

    public function turma()
    {
        return $this->belongsTo(Turma::class, 'id_turma');
    }

    public function statusAlteradoPor()
    {
        return $this->belongsTo(User::class, 'status_alterado_por')->withTrashed();
    }

    public function alunoOrigem()
    {
        return $this->belongsTo(self::class, 'aluno_origem_id');
    }

    public function pendenciaOrigem()
    {
        return $this->belongsTo(self::class, 'pendencia_origem_aluno_id');
    }

    public function pendenciasDestino()
    {
        return $this->hasMany(self::class, 'pendencia_origem_aluno_id');
    }

    public function turmaOrigem()
    {
        return $this->belongsTo(Turma::class, 'turma_origem_id');
    }

    public function avaliacaoDocumentos()
    {
        return $this->hasMany(AvaliacaoAlunoDocumento::class, 'aluno_id');
    }

}
