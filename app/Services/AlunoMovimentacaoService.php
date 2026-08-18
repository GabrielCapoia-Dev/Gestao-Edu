<?php

namespace App\Services;

use App\Exceptions\MatriculaAlunoBloqueadaException;
use App\Models\Aluno;
use App\Models\AvaliacaoAlunoDocumentoHistorico;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\SistemaNotification;
use App\Services\Avaliacoes\AvaliacaoAlunoDocumentoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AlunoMovimentacaoService
{
    public const MOVIMENTACAO_REMANEJAMENTO = 'remanejamento';

    public const MOVIMENTACAO_TRANSFERENCIA = 'transferencia';

    public const MOVIMENTACAO_HISTORICO = 'historico';

    public const MOVIMENTACAO_CONTRA_TURNO = 'contra_turno';

    public function criarMatricula(array $data, ?User $usuario = null): Aluno
    {
        return DB::transaction(function () use ($data, $usuario): Aluno {
            $cgm = Aluno::normalizarCgm((string) ($data['cgm'] ?? ''));
            $turmaId = (int) ($data['id_turma'] ?? 0);

            $this->assertUsuarioPodeAcessarTurma(
                $usuario,
                Turma::query()->find($turmaId)
            );

            $origemPendente = $this->bloquearSeCgmAtivo(
                cgm: $cgm,
                turmaDestinoId: $turmaId,
                usuario: $usuario,
                permitirPendencia: true
            );

            $origemHistorica = $origemPendente
                ? null
                : $this->ultimaMatriculaHistorica($cgm);

            $status = $origemPendente
                ? Aluno::STATUS_PENDENTE
                : Aluno::STATUS_MATRICULADO;

            $aluno = Aluno::query()->create([
                ...$data,
                'nome' => $origemPendente?->nome ?? $data['nome'] ?? null,
                'cgm' => $cgm,
                'data_nascimento' => $origemPendente?->data_nascimento
                    ?? $data['data_nascimento']
                    ?? null,
                'sexo' => $origemPendente?->sexo
                    ?? $data['sexo']
                    ?? null,
                'data_matricula' => $origemPendente?->data_matricula
                    ?? $data['data_matricula']
                    ?? null,
                'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
                'permite_contra_turno' => false,
                'status' => $status,
                'status_alterado_em' => now(),
                'status_alterado_por' => $usuario?->id,
                'status_motivo' => $data['status_motivo'] ?? (
                    $origemPendente
                        ? 'Matricula criada como pendente por transferencia nao finalizada na escola de origem.'
                        : 'Matricula criada no sistema.'
                ),
                'aluno_origem_id' => $origemHistorica?->id,
                'turma_origem_id' => $origemHistorica?->id_turma,
                'movimentacao_origem' => $origemHistorica
                    ? $this->tipoOrigemPorStatus($origemHistorica)
                    : null,
                'pendencia_origem_aluno_id' => $origemPendente?->id,
            ]);

            if ($origemHistorica) {
                $this->moverDocumentosAvaliativos(
                    origem: $origemHistorica,
                    destino: $aluno,
                    tipo: $this->tipoOrigemPorStatus($origemHistorica),
                    usuario: $usuario
                );
            }

            if ($aluno->estaPendente()) {
                app(AlunoTransferenciaPendenteService::class)
                    ->notificarPendencia($aluno, $usuario, true);
            }

            return $aluno;
        });
    }

    public function criarMatriculaEmLote(array $linhas, ?User $usuario = null): array
    {
        $turmaIds = array_unique(
            array_map(
                fn (array $l): int => (int) ($l['id_turma'] ?? 0),
                $linhas
            )
        );

        $cgms = array_values(
            array_unique(
                array_map(
                    fn (array $l): string => Aluno::normalizarCgm(
                        (string) ($l['cgm'] ?? '')
                    ),
                    $linhas
                )
            )
        );

        $turmas = Turma::query()
            ->whereIn('id', $turmaIds)
            ->get()
            ->keyBy('id');

        foreach ($turmaIds as $turmaId) {
            $this->assertUsuarioPodeAcessarTurma(
                $usuario,
                $turmas->get((int) $turmaId)
            );
        }

        $alunosCadastrados = Aluno::query()
            ->with('turma')
            ->whereIn('cgm', $cgms)
            ->where('tipo_vinculo', Aluno::TIPO_VINCULO_PRINCIPAL)
            ->whereIn('status', [
                Aluno::STATUS_MATRICULADO,
                Aluno::STATUS_PENDENTE,
            ])
            ->get();

        $pendentesIndex = $alunosCadastrados
            ->where('status', Aluno::STATUS_PENDENTE)
            ->keyBy('cgm');

        $ativosIndex = $alunosCadastrados
            ->filter(
                fn (Aluno $a): bool =>
                    $a->cgm_matricula_ativa !== null
            )
            ->keyBy('cgm_matricula_ativa');

        $importados = 0;
        $criadosPendentes = 0;
        $alunosNotificar = [];

        foreach ($linhas as $linha) {
            $cgm = Aluno::normalizarCgm(
                (string) ($linha['cgm'] ?? '')
            );

            $turmaId = (int) ($linha['id_turma'] ?? 0);
            $turma = $turmas->get($turmaId);

            $escolaId = (int) ($turma?->id_escola ?? 0);

            $chaveUnidade = Aluno::chaveCgmUnidade(
                $escolaId,
                $cgm
            );

            if (
                $chaveUnidade !== null
                && $alunosCadastrados->first(
                    fn (Aluno $a): bool =>
                        $a->cgm_unidade_matricula_ativa === $chaveUnidade
                )
            ) {
                $alunoExistente = $alunosCadastrados->first(
                    fn (Aluno $a): bool =>
                        $a->cgm_unidade_matricula_ativa === $chaveUnidade
                );

                throw new MatriculaAlunoBloqueadaException(
                    $alunoExistente,
                    'Este CGM ja esta cadastrado nesta unidade.'
                );
            }

            if (isset($pendentesIndex[$cgm])) {
                throw new MatriculaAlunoBloqueadaException(
                    $pendentesIndex[$cgm],
                    'Este CGM ja possui uma matricula pendente em outra unidade. Resolva a pendencia antes de criar uma nova matricula.'
                );
            }

            $origemPendente = $ativosIndex[$cgm] ?? null;

            $origemHistorica = $origemPendente
                ? null
                : $this->ultimaMatriculaHistorica($cgm);

            $status = $origemPendente
                ? Aluno::STATUS_PENDENTE
                : Aluno::STATUS_MATRICULADO;

            $aluno = new Aluno([
                ...$linha,
                'nome' => $origemPendente?->nome
                    ?? $linha['nome'],
                'cgm' => $cgm,
                'data_nascimento' => $origemPendente?->data_nascimento
                    ?? $linha['data_nascimento']
                    ?? null,
                'sexo' => $origemPendente?->sexo
                    ?? $linha['sexo']
                    ?? null,
                'data_matricula' => $origemPendente?->data_matricula
                    ?? $linha['data_matricula']
                    ?? null,
                'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
                'permite_contra_turno' => false,
                'status' => $status,
                'status_alterado_em' => now(),
                'status_alterado_por' => $usuario?->id,
                'status_motivo' => $linha['status_motivo'] ?? (
                    $origemPendente
                        ? 'Matricula criada como pendente por transferencia nao finalizada na escola de origem.'
                        : 'Matricula criada no sistema.'
                ),
                'aluno_origem_id' => $origemHistorica?->id,
                'turma_origem_id' => $origemHistorica?->id_turma,
                'movimentacao_origem' => $origemHistorica
                    ? $this->tipoOrigemPorStatus($origemHistorica)
                    : null,
                'pendencia_origem_aluno_id' => $origemPendente?->id,
            ]);

            $aluno->setRelation('turma', $turma);
            $aluno->save();

            $importados++;

            if ($aluno->estaPendente()) {
                $criadosPendentes++;
                $alunosNotificar[] = $aluno;
            }

            if ($origemHistorica) {
                $this->moverDocumentosAvaliativos(
                    origem: $origemHistorica,
                    destino: $aluno,
                    tipo: $this->tipoOrigemPorStatus($origemHistorica),
                    usuario: $usuario
                );
            }
        }

        foreach ($alunosNotificar as $alunoPendente) {
            app(AlunoTransferenciaPendenteService::class)
                ->notificarPendencia(
                    $alunoPendente,
                    $usuario,
                    true
                );
        }

        return [
            'total_importado' => $importados,
            'total_pendente' => $criadosPendentes,
        ];
    }

    public function bloquearSeCgmAtivo(
        string $cgm,
        int $turmaDestinoId,
        ?User $usuario = null,
        ?Aluno $ignorar = null,
        bool $permitirPendencia = false
    ): ?Aluno {
        $cgm = Aluno::normalizarCgm($cgm);

        if ($cgm === '') {
            return null;
        }

        $turmaDestino = Turma::query()->find($turmaDestinoId);

        $this->assertUsuarioPodeAcessarTurma(
            $usuario,
            $turmaDestino
        );

        $escolaDestinoId = (int) ($turmaDestino?->id_escola ?? 0);

        $chaveUnidade = Aluno::chaveCgmUnidade(
            $escolaDestinoId,
            $cgm
        );

        if ($chaveUnidade) {
            $ativoNaMesmaUnidade = Aluno::query()
                ->with('turma.escola')
                ->where(
                    'cgm_unidade_matricula_ativa',
                    $chaveUnidade
                )
                ->when(
                    $ignorar,
                    fn (Builder $query): Builder =>
                        $query->whereKeyNot((int) $ignorar->id)
                )
                ->first();

            if ($ativoNaMesmaUnidade) {
                throw new MatriculaAlunoBloqueadaException(
                    $ativoNaMesmaUnidade,
                    'Este CGM ja esta cadastrado nesta unidade.'
                );
            }
        }

        $pendente = Aluno::query()
            ->with('turma.escola')
            ->where('cgm', $cgm)
            ->where(
                'tipo_vinculo',
                Aluno::TIPO_VINCULO_PRINCIPAL
            )
            ->where(
                'status',
                Aluno::STATUS_PENDENTE
            )
            ->when(
                $ignorar,
                fn (Builder $query): Builder =>
                    $query->whereKeyNot((int) $ignorar->id)
            )
            ->first();

        if ($pendente) {
            throw new MatriculaAlunoBloqueadaException(
                $pendente,
                'Este CGM ja possui uma matricula pendente em outra unidade. Resolva a pendencia antes de criar uma nova matricula.'
            );
        }

        $ativo = Aluno::query()
            ->with('turma.escola')
            ->where(
                'cgm_matricula_ativa',
                $cgm
            )
            ->where(
                'tipo_vinculo',
                Aluno::TIPO_VINCULO_PRINCIPAL
            )
            ->when(
                $ignorar,
                fn (Builder $query): Builder =>
                    $query->whereKeyNot((int) $ignorar->id)
            )
            ->first();

        if (! $ativo) {
            return null;
        }

        if ($permitirPendencia) {
            return $ativo;
        }

        $this->notificarImpedimentoMatricula(
            $ativo,
            $turmaDestinoId,
            $usuario
        );

        throw new MatriculaAlunoBloqueadaException($ativo);
    }

    public function remanejar(
        Aluno $aluno,
        int $turmaDestinoId,
        ?User $usuario = null,
        ?string $motivo = null
    ): Aluno {
        return DB::transaction(
            function () use (
                $aluno,
                $turmaDestinoId,
                $usuario,
                $motivo
            ): Aluno {
                $aluno
                    ->refresh()
                    ->loadMissing(
                        'turma.escola',
                        'turma.serie'
                    );

                $turmaDestino = Turma::query()
                    ->with([
                        'escola',
                        'serie',
                    ])
                    ->findOrFail($turmaDestinoId);

                $this->assertUsuarioPodeAcessarTurma(
                    $usuario,
                    $aluno->turma
                );

                $this->assertUsuarioPodeAcessarTurma(
                    $usuario,
                    $turmaDestino
                );

                $statusDestino = $aluno->estaPendente()
                    ? Aluno::STATUS_PENDENTE
                    : Aluno::STATUS_MATRICULADO;

                $pendenciaOrigemId = $aluno->pendencia_origem_aluno_id;

                $this->assertAlunoPrincipal(
                    $aluno,
                    'Somente o vinculo principal pode ser remanejado.'
                );

                if (
                    ! $aluno->estaMatriculado()
                    && ! $aluno->estaPendente()
                ) {
                    throw new RuntimeException(
                        'Somente alunos matriculados ou pendentes podem ser remanejados.'
                    );
                }

                $this->bloquearMovimentacaoSePossuiContraTurnoAtivo(
                    $aluno
                );

                if (
                    (int) $aluno->id_turma
                    === (int) $turmaDestino->id
                ) {
                    throw new RuntimeException(
                        'Selecione uma turma diferente para remanejar o aluno.'
                    );
                }

                if (
                    (int) $aluno->turma?->id_escola
                    !== (int) $turmaDestino->id_escola
                ) {
                    throw new RuntimeException(
                        'Remanejamento so pode ocorrer dentro da mesma escola.'
                    );
                }

                if (
                    (int) $aluno->turma?->id_serie
                    !== (int) $turmaDestino->id_serie
                ) {
                    throw new RuntimeException(
                        'Remanejamento so pode ocorrer entre turmas da mesma serie.'
                    );
                }

                $aluno->forceFill([
                    'status' => Aluno::STATUS_REMANEJADO,
                    'status_alterado_em' => now(),
                    'status_alterado_por' => $usuario?->id,
                    'status_motivo' => $motivo
                        ?: 'Aluno remanejado para outra turma da mesma escola.',
                ])->save();

                $novoAluno = Aluno::query()->create([
                    'nome' => $aluno->nome,
                    'cgm' => $aluno->cgm,
                    'data_nascimento' => $aluno->data_nascimento,
                    'sexo' => $aluno->sexo,
                    'data_matricula' => $aluno->data_matricula,
                    'id_turma' => (int) $turmaDestino->id,
                    'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
                    'permite_contra_turno' => (bool) $aluno->permite_contra_turno,
                    'status' => $statusDestino,
                    'status_alterado_em' => now(),
                    'status_alterado_por' => $usuario?->id,
                    'status_motivo' => $motivo
                        ?: 'Matricula criada por remanejamento.',
                    'aluno_origem_id' => (int) $aluno->id,
                    'turma_origem_id' => (int) $aluno->id_turma,
                    'movimentacao_origem' => self::MOVIMENTACAO_REMANEJAMENTO,
                    'pendencia_origem_aluno_id' => $pendenciaOrigemId,
                ]);

                $this->garantirAvaliacoesDaOrigemNaTurmaDestino(
                    $aluno,
                    $novoAluno
                );

                $this->moverDocumentosAvaliativos(
                    $aluno,
                    $novoAluno,
                    AvaliacaoAlunoDocumentoHistorico::MOVIMENTACAO_REMANEJAMENTO,
                    $usuario
                );

                return $novoAluno;
            }
        );
    }

    public function transferir(
        Aluno $aluno,
        ?User $usuario = null,
        ?string $motivo = null
    ): Aluno {
        return DB::transaction(
            function () use (
                $aluno,
                $usuario,
                $motivo
            ): Aluno {
                $aluno
                    ->refresh()
                    ->loadMissing('turma');

                $this->assertUsuarioPodeAcessarTurma(
                    $usuario,
                    $aluno->turma
                );

                $this->assertAlunoPrincipal(
                    $aluno,
                    'Somente o vinculo principal pode ser transferido.'
                );

                if (! $aluno->estaMatriculado()) {
                    throw new RuntimeException(
                        'Somente alunos matriculados podem ser transferidos.'
                    );
                }

                $this->bloquearMovimentacaoSePossuiContraTurnoAtivo(
                    $aluno
                );

                $aluno->forceFill([
                    'status' => Aluno::STATUS_TRANSFERIDO,
                    'status_alterado_em' => now(),
                    'status_alterado_por' => $usuario?->id,
                    'status_motivo' => $motivo
                        ?: 'Parecer de transferencia gerado.',
                ])->save();

                $this->resolverPendenciasDeTransferencia(
                    $aluno,
                    $usuario
                );

                return $aluno;
            }
        );
    }

    public function marcarStatusFinal(
        Aluno $aluno,
        string $status,
        ?User $usuario = null,
        ?string $motivo = null
    ): Aluno {
        $this->assertAlunoPrincipal(
            $aluno,
            'Somente o vinculo principal pode receber status final.'
        );

        if (
            ! in_array(
                $status,
                [
                    Aluno::STATUS_APROVADO,
                    Aluno::STATUS_RETIDO,
                    Aluno::STATUS_TRANSFERIDO,
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Status final invalido para aluno.'
            );
        }

        $this->bloquearMovimentacaoSePossuiContraTurnoAtivo(
            $aluno
        );

        $aluno->forceFill([
            'status' => $status,
            'status_alterado_em' => now(),
            'status_alterado_por' => $usuario?->id,
            'status_motivo' => $motivo,
        ])->save();

        if ($status === Aluno::STATUS_TRANSFERIDO) {
            $this->resolverPendenciasDeTransferencia(
                $aluno,
                $usuario
            );
        }

        return $aluno;
    }

    public function marcarContraTurno(
        Aluno $aluno,
        ?User $usuario = null,
        ?string $motivo = null
    ): Aluno {
        return DB::transaction(
            function () use (
                $aluno,
                $usuario,
                $motivo
            ): Aluno {
                $aluno->refresh();

                $this->assertAlunoPrincipal(
                    $aluno,
                    'Somente o vinculo principal pode ser marcado como contra turno.'
                );

                if (! $aluno->estaMatriculado()) {
                    throw new RuntimeException(
                        'Somente alunos matriculados podem ser marcados como contra turno.'
                    );
                }

                $aluno->forceFill([
                    'permite_contra_turno' => true,
                    'status_alterado_em' => now(),
                    'status_alterado_por' => $usuario?->id,
                    'status_motivo' => $motivo
                        ?: 'Aluno marcado como apto para contra turno.',
                ])->save();

                return $aluno;
            }
        );
    }

    public function vincularContraTurno(
        Aluno $aluno,
        int $turmaDestinoId,
        ?User $usuario = null,
        ?string $motivo = null
    ): Aluno {
        return DB::transaction(
            function () use (
                $aluno,
                $turmaDestinoId,
                $usuario,
                $motivo
            ): Aluno {
                $aluno
                    ->refresh()
                    ->loadMissing(
                        'turma.escola',
                        'turma.serie'
                    );

                $this->assertAlunoPrincipal(
                    $aluno,
                    'Somente o vinculo principal pode receber turma de contra turno.'
                );

                if (! $aluno->estaMatriculado()) {
                    throw new RuntimeException(
                        'Somente alunos matriculados podem receber turma de contra turno.'
                    );
                }

                $turmaDestino = Turma::query()
                    ->with([
                        'escola',
                        'serie',
                    ])
                    ->findOrFail($turmaDestinoId);

                $this->validarTurmaContraTurno(
                    $aluno,
                    $turmaDestino,
                    $usuario
                );

                $existente = $this->contraTurnoAtivoDoPrincipal(
                    $aluno
                );

                if (
                    $existente
                    && (int) $existente->id_turma
                    !== (int) $turmaDestino->id
                ) {
                    throw new RuntimeException(
                        'O aluno ja possui um vinculo ativo de contra turno.'
                    );
                }

                $aluno->forceFill([
                    'permite_contra_turno' => true,
                    'status_alterado_em' => now(),
                    'status_alterado_por' => $usuario?->id,
                    'status_motivo' => $motivo
                        ?: 'Aluno marcado como apto para contra turno.',
                ])->save();

                if ($existente) {
                    return $existente;
                }

                return Aluno::query()->create([
                    'nome' => $aluno->nome,
                    'cgm' => $aluno->cgm,
                    'data_nascimento' => $aluno->data_nascimento,
                    'sexo' => $aluno->sexo,
                    'data_matricula' => $aluno->data_matricula,
                    'id_turma' => (int) $turmaDestino->id,
                    'tipo_vinculo' => Aluno::TIPO_VINCULO_CONTRA_TURNO,
                    'permite_contra_turno' => false,
                    'status' => Aluno::STATUS_MATRICULADO,
                    'status_alterado_em' => now(),
                    'status_alterado_por' => $usuario?->id,
                    'status_motivo' => $motivo
                        ?: 'Vinculo de contra turno criado.',
                    'aluno_origem_id' => (int) $aluno->id,
                    'turma_origem_id' => (int) $aluno->id_turma,
                    'movimentacao_origem' => self::MOVIMENTACAO_CONTRA_TURNO,
                ]);
            }
        );
    }

    public function encerrarContraTurno(
        Aluno $aluno,
        ?User $usuario = null,
        ?string $motivo = null
    ): Aluno {
        return DB::transaction(
            function () use (
                $aluno,
                $usuario,
                $motivo
            ): Aluno {
                $aluno->refresh();

                if (! $aluno->isContraTurno()) {
                    throw new RuntimeException(
                        'Somente vinculos de contra turno podem ser encerrados.'
                    );
                }

                if (! $aluno->estaMatriculado()) {
                    throw new RuntimeException(
                        'Somente vinculos de contra turno ativos podem ser encerrados.'
                    );
                }

                $aluno->forceFill([
                    'status' => Aluno::STATUS_CONTRA_TURNO_ENCERRADO,
                    'status_alterado_em' => now(),
                    'status_alterado_por' => $usuario?->id,
                    'status_motivo' => $motivo
                        ?: 'Vinculo de contra turno encerrado.',
                ])->save();

                $principal = $this->principalAtivoPorCgm(
                    $aluno->cgm
                );

                if ($principal) {
                    $principal->forceFill([
                        'permite_contra_turno' => false,
                        'status_alterado_em' => now(),
                        'status_alterado_por' => $usuario?->id,
                        'status_motivo' => $motivo
                            ?: 'Contra turno desmarcado.',
                    ])->save();
                }

                return $aluno;
            }
        );
    }

    public function voltarParaTurmaAnterior(
        Aluno $aluno,
        ?User $usuario = null,
        ?string $motivo = null
    ): Aluno {
        $this->assertAlunoPrincipal(
            $aluno,
            'Somente o vinculo principal pode voltar para a turma anterior.'
        );

        if ((int) $aluno->turma_origem_id <= 0) {
            throw new RuntimeException(
                'O aluno nao possui turma anterior registrada para retorno.'
            );
        }

        return $this->remanejar(
            $aluno,
            (int) $aluno->turma_origem_id,
            $usuario,
            $motivo
                ?: 'Aluno retornado para a turma anterior.'
        );
    }

    public function moverDocumentosAvaliativos(
        Aluno $origem,
        Aluno $destino,
        string $tipo,
        ?User $usuario = null,
    ): void {
        $destino->loadMissing('turma');

        if (! $destino->turma) {
            return;
        }

        $this->garantirAvaliacoesDaOrigemNaTurmaDestino(
            $origem,
            $destino
        );

        $tipoHistorico = match ($tipo) {
            self::MOVIMENTACAO_TRANSFERENCIA,
            AvaliacaoAlunoDocumentoHistorico::MOVIMENTACAO_TRANSFERENCIA
                => AvaliacaoAlunoDocumentoHistorico::MOVIMENTACAO_TRANSFERENCIA,

            default
                => AvaliacaoAlunoDocumentoHistorico::MOVIMENTACAO_REMANEJAMENTO,
        };

        app(AvaliacaoAlunoDocumentoService::class)
            ->moverDocumentosDoAluno(
                $origem,
                $destino,
                $tipoHistorico,
                $usuario
            );
    }

    /**
     * @deprecated Use moverDocumentosAvaliativos()
     */
    public function copiarDadosAvaliativosBloqueados(
        Aluno $origem,
        Aluno $destino,
        string $tipo
    ): void {
        $this->moverDocumentosAvaliativos(
            $origem,
            $destino,
            $tipo
        );
    }

    private function garantirAvaliacoesDaOrigemNaTurmaDestino(
        Aluno $origem,
        Aluno $destino
    ): void {
        $turmaOrigemId = (int) $origem->id_turma;
        $turmaDestinoId = (int) $destino->id_turma;

        if (
            $turmaOrigemId <= 0
            || $turmaDestinoId <= 0
        ) {
            return;
        }

        $avaliacoesIds = DB::table('avaliacao_turma')
            ->where(
                'turma_id',
                $turmaOrigemId
            )
            ->pluck('avaliacao_id')
            ->merge(
                DB::table('avaliacao_aluno_documentos')
                    ->where(
                        'aluno_id',
                        (int) $origem->id
                    )
                    ->pluck('avaliacao_id')
            )
            ->map(
                fn ($id): int =>
                    (int) $id
            )
            ->filter()
            ->unique()
            ->values();

        if ($avaliacoesIds->isEmpty()) {
            return;
        }

        $agora = now();

        DB::table('avaliacao_turma')
            ->insertOrIgnore(
                $avaliacoesIds
                    ->map(
                        fn (int $avaliacaoId): array => [
                            'avaliacao_id' => $avaliacaoId,
                            'turma_id' => $turmaDestinoId,
                            'created_at' => $agora,
                            'updated_at' => $agora,
                        ]
                    )
                    ->all()
            );
    }

    private function ultimaMatriculaHistorica(
        string $cgm
    ): ?Aluno {
        return Aluno::query()
            ->with('turma')
            ->where(
                'cgm',
                Aluno::normalizarCgm($cgm)
            )
            ->where(
                'tipo_vinculo',
                Aluno::TIPO_VINCULO_PRINCIPAL
            )
            ->whereNotIn(
                'status',
                [
                    Aluno::STATUS_MATRICULADO,
                    Aluno::STATUS_PENDENTE,
                ]
            )
            ->latest('status_alterado_em')
            ->latest('updated_at')
            ->first();
    }

    private function tipoOrigemPorStatus(
        Aluno $origem
    ): string {
        return $origem->status === Aluno::STATUS_TRANSFERIDO
            ? AvaliacaoAlunoDocumentoHistorico::MOVIMENTACAO_TRANSFERENCIA
            : AvaliacaoAlunoDocumentoHistorico::MOVIMENTACAO_REMANEJAMENTO;
    }

    private function resolverPendenciasDeTransferencia(
        Aluno $origem,
        ?User $usuario
    ): void {
        $pendentes = Aluno::query()
            ->where(
                'pendencia_origem_aluno_id',
                (int) $origem->id
            )
            ->where(
                'tipo_vinculo',
                Aluno::TIPO_VINCULO_PRINCIPAL
            )
            ->where(
                'status',
                Aluno::STATUS_PENDENTE
            )
            ->orderBy('id')
            ->get();

        if ($pendentes->isEmpty()) {
            return;
        }

        /** @var Aluno $destinoPrincipal */
        $destinoPrincipal = $pendentes->first();

        $this->garantirAvaliacoesDaOrigemNaTurmaDestino(
            $origem,
            $destinoPrincipal
        );

        $this->moverDocumentosAvaliativos(
            $origem,
            $destinoPrincipal,
            AvaliacaoAlunoDocumentoHistorico::MOVIMENTACAO_TRANSFERENCIA,
            $usuario
        );

        $destinoPrincipal->forceFill([
            'status' => Aluno::STATUS_MATRICULADO,
            'status_alterado_em' => now(),
            'status_alterado_por' => $usuario?->id,
            'status_motivo' => 'Pendencia de transferencia resolvida pelo parecer da escola de origem.',
            'aluno_origem_id' => (int) $origem->id,
            'turma_origem_id' => (int) $origem->id_turma,
            'movimentacao_origem' => self::MOVIMENTACAO_TRANSFERENCIA,
            'pendencia_origem_aluno_id' => null,
        ])->save();

        /*
         * Demais pendentes da mesma origem nao recebem
         * o documento. Existe apenas um dono da ficha.
         */
        $pendentes
            ->skip(1)
            ->each(
                function (Aluno $pendente) use (
                    $origem,
                    $usuario
                ): void {
                    $pendente->forceFill([
                        'status' => Aluno::STATUS_MATRICULADO,
                        'status_alterado_em' => now(),
                        'status_alterado_por' => $usuario?->id,
                        'status_motivo' =>
                            'Pendencia resolvida sem rebind de documento '
                            .'(destino principal ja recebeu a ficha avaliativa da origem #'
                            .$origem->id
                            .').',
                        'aluno_origem_id' => (int) $origem->id,
                        'turma_origem_id' => (int) $origem->id_turma,
                        'movimentacao_origem' => self::MOVIMENTACAO_TRANSFERENCIA,
                        'pendencia_origem_aluno_id' => null,
                    ])->save();
                }
            );
    }

    private function notificarImpedimentoMatricula(
        Aluno $alunoAtivo,
        int $turmaDestinoId,
        ?User $usuario
    ): void {
        $alunoAtivo->loadMissing('turma.escola');

        $titulo = 'Impedimento de matricula por falta de transferencia';

        $mensagem = (
            new MatriculaAlunoBloqueadaException($alunoAtivo)
        )->getMessage();

        $escolaId = (int) (
            $alunoAtivo->turma?->id_escola ?? 0
        );

        $url = route(
            'filament.admin.pages.parecer-transferencia-aluno',
            [
                'aluno' => $alunoAtivo->id,
            ]
        );

        $destinatarios = $this
            ->usuariosParaNotificarImpedimento(
                $escolaId
            );

        foreach ($destinatarios as $destinatario) {
            $podeReceberLink =
                $destinatario->hasPermissionLike(
                    'realizar transferencia de aluno'
                )
                || $destinatario->hasPermissionLike(
                    'realizar tranferencia de aluno'
                );

            $destinatario->notify(
                new SistemaNotification(
                    titulo: $titulo,
                    mensagem: $mensagem,
                    url: $podeReceberLink
                        ? $url
                        : null,
                    label: $podeReceberLink
                        ? 'Abrir parecer de transferencia'
                        : null,
                    prioridade: 'alta',
                    escopo: $alunoAtivo->turma?->escola?->nome,
                    metadata: [
                        'tipo' => 'impedimento_matricula_transferencia',
                        'aluno_id' => (int) $alunoAtivo->id,
                        'turma_destino_id' => $turmaDestinoId,
                        'solicitante_id' => $usuario?->id,
                    ]
                )
            );
        }
    }

    private function usuariosParaNotificarImpedimento(
        int $escolaId
    ): Collection {
        return User::query()
            ->with([
                'roles.permissions',
                'permissions',
                'escolas:id',
                'professores:id,user_id,id_escola',
            ])
            ->whereNotNull('email')
            ->get()
            ->filter(
                function (User $user) use ($escolaId): bool {
                    $temPermissao =
                        $user->hasPermissionLike(
                            'notificar impedimento de matricula por falta de transferencia'
                        )
                        || $user->hasPermissionLike(
                            'gerenciar impedimento de matricula por falta de transferencia'
                        );

                    return $temPermissao
                        && $this->usuarioPertenceAEscola(
                            $user,
                            $escolaId
                        );
                }
            )
            ->unique('id')
            ->values();
    }

    /**
     * Valida se o usuário possui acesso à turma informada.
     *
     * Esta validação é utilizada por:
     * - criação individual de matrícula;
     * - importação de alunos em lote;
     * - remanejamento;
     * - transferência;
     * - validações de CGM.
     *
     * Usuário nulo representa uma execução interna/sistêmica
     * e não sofre restrição de acesso.
     */
    private function assertUsuarioPodeAcessarTurma(
        ?User $usuario,
        ?Turma $turma
    ): void {
        if (! $turma) {
            throw new RuntimeException(
                'A turma informada não foi encontrada.'
            );
        }

        /*
         * Execuções internas podem não possuir usuário.
         */
        if (! $usuario) {
            return;
        }

        /*
         * Utiliza a regra central já existente no UserService,
         * evitando duplicação de regras de acesso.
         */
        if (
            ! app(UserService::class)
                ->podeAcessarTurma(
                    $usuario,
                    $turma
                )
        ) {
            throw new AuthorizationException(
                'Você não possui permissão para acessar a turma informada.'
            );
        }
    }

    private function assertAlunoPrincipal(
        Aluno $aluno,
        string $message
    ): void {
        if (! $aluno->isPrincipal()) {
            throw new RuntimeException($message);
        }
    }

    private function bloquearMovimentacaoSePossuiContraTurnoAtivo(
        Aluno $aluno
    ): void {
        if ($this->contraTurnoAtivoDoPrincipal($aluno)) {
            throw new RuntimeException(
                'Resolva o vinculo ativo de contra turno antes de movimentar a matricula principal.'
            );
        }
    }

    /**
     * O vínculo de Contra Turno pode utilizar qualquer turma da mesma escola,
     * inclusive a própria turma da matrícula Principal.
     */
    private function validarTurmaContraTurno(
        Aluno $aluno,
        Turma $turmaDestino,
        ?User $usuario = null
    ): void {
        $turmaOrigem = $aluno->turma;

        if (! $turmaOrigem) {
            throw new RuntimeException(
                'Turma principal do aluno nao encontrada.'
            );
        }

        $this->assertUsuarioPodeAcessarTurma($usuario, $turmaOrigem);
        $this->assertUsuarioPodeAcessarTurma($usuario, $turmaDestino);

        if (
            (int) $turmaOrigem->id_escola
            !== (int) $turmaDestino->id_escola
        ) {
            throw new RuntimeException(
                'Contra turno so pode ocorrer dentro da mesma escola.'
            );
        }
    }

    private function contraTurnoAtivoDoPrincipal(
        Aluno $aluno
    ): ?Aluno {
        return Aluno::query()
            ->where(
                'cgm_contra_turno_ativo',
                Aluno::normalizarCgm($aluno->cgm)
            )
            ->where(
                'tipo_vinculo',
                Aluno::TIPO_VINCULO_CONTRA_TURNO
            )
            ->where(
                'status',
                Aluno::STATUS_MATRICULADO
            )
            ->first();
    }

    private function principalAtivoPorCgm(
        string $cgm
    ): ?Aluno {
        return Aluno::query()
            ->where(
                'cgm_matricula_ativa',
                Aluno::normalizarCgm($cgm)
            )
            ->where(
                'tipo_vinculo',
                Aluno::TIPO_VINCULO_PRINCIPAL
            )
            ->first();
    }

    private function usuarioPertenceAEscola(
        User $user,
        int $escolaId
    ): bool {
        if ($escolaId <= 0) {
            return false;
        }

        return app(PessoaScopeService::class)
            ->canAccessEscola(
                $user,
                $escolaId
            );
    }
}
