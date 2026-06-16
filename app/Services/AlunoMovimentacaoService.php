<?php

namespace App\Services;

use App\Exceptions\MatriculaAlunoBloqueadaException;
use App\Models\Aluno;
use App\Models\AvaliacaoInformacaoComplementar;
use App\Models\AvaliacaoResposta;
use App\Models\Pauta;
use App\Models\Turma;
use App\Models\User;
use App\Notifications\SistemaNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AlunoMovimentacaoService
{
    public const MOVIMENTACAO_REMANEJAMENTO = 'remanejamento';

    public const MOVIMENTACAO_TRANSFERENCIA = 'transferencia';

    public const MOVIMENTACAO_HISTORICO = 'historico';

    public function criarMatricula(array $data, ?User $usuario = null): Aluno
    {
        return DB::transaction(function () use ($data, $usuario): Aluno {
            $cgm = Aluno::normalizarCgm((string) ($data['cgm'] ?? ''));
            $turmaId = (int) ($data['id_turma'] ?? 0);

            $origemPendente = $this->bloquearSeCgmAtivo(
                cgm: $cgm,
                turmaDestinoId: $turmaId,
                usuario: $usuario,
                permitirPendencia: true
            );

            $origemHistorica = $origemPendente ? null : $this->ultimaMatriculaHistorica($cgm);
            $status = $origemPendente ? Aluno::STATUS_PENDENTE : Aluno::STATUS_MATRICULADO;

            $aluno = Aluno::query()->create([
                ...$data,
                'nome' => $origemPendente?->nome ?? $data['nome'] ?? null,
                'cgm' => $cgm,
                'data_nascimento' => $origemPendente?->data_nascimento ?? $data['data_nascimento'] ?? null,
                'status' => $status,
                'status_alterado_em' => now(),
                'status_alterado_por' => $usuario?->id,
                'status_motivo' => $data['status_motivo'] ?? ($origemPendente
                    ? 'Matrícula criada como pendente por transferência não finalizada na escola de origem.'
                    : 'Matrícula criada no sistema.'),
                'aluno_origem_id' => $origemHistorica?->id,
                'turma_origem_id' => $origemHistorica?->id_turma,
                'movimentacao_origem' => $origemHistorica ? $this->tipoOrigemPorStatus($origemHistorica) : null,
                'pendencia_origem_aluno_id' => $origemPendente?->id,
            ]);

            if ($origemHistorica) {
                $this->copiarDadosAvaliativosBloqueados(
                    origem: $origemHistorica,
                    destino: $aluno,
                    tipo: $this->tipoOrigemPorStatus($origemHistorica)
                );
            }

            if ($aluno->estaPendente()) {
                app(AlunoTransferenciaPendenteService::class)->notificarPendencia($aluno, $usuario, true);
            }

            return $aluno;
        });
    }

    public function criarMatriculaEmLote(array $linhas, ?User $usuario = null): array
    {
        $turmaIds = array_unique(array_map(fn (array $l): int => (int) ($l['id_turma'] ?? 0), $linhas));
        $cgms = array_values(array_unique(array_map(
            fn (array $l): string => Aluno::normalizarCgm((string) ($l['cgm'] ?? '')),
            $linhas
        )));

        $turmas = Turma::query()->whereIn('id', $turmaIds)->get()->keyBy('id');

        $alunosCadastrados = Aluno::query()
            ->with('turma')
            ->whereIn('cgm', $cgms)
            ->whereIn('status', [Aluno::STATUS_MATRICULADO, Aluno::STATUS_PENDENTE])
            ->get();

        $pendentesIndex = $alunosCadastrados
            ->where('status', Aluno::STATUS_PENDENTE)
            ->keyBy('cgm');

        $ativosIndex = $alunosCadastrados
            ->filter(fn (Aluno $a): bool => $a->cgm_matricula_ativa !== null)
            ->keyBy('cgm_matricula_ativa');

        $importados = 0;
        $criadosPendentes = 0;
        $alunosNotificar = [];

        foreach ($linhas as $linha) {
            $cgm = Aluno::normalizarCgm((string) ($linha['cgm'] ?? ''));
            $turmaId = (int) ($linha['id_turma'] ?? 0);
            $turma = $turmas->get($turmaId);

            $escolaId = (int) ($turma?->id_escola ?? 0);
            $chaveUnidade = Aluno::chaveCgmUnidade($escolaId, $cgm);

            if ($chaveUnidade !== null && $alunosCadastrados->first(fn (Aluno $a): bool => $a->cgm_unidade_matricula_ativa === $chaveUnidade)) {
                throw new MatriculaAlunoBloqueadaException(
                    $alunosCadastrados->first(fn (Aluno $a): bool => $a->cgm_unidade_matricula_ativa === $chaveUnidade),
                    'Este CGM já está cadastrado nesta unidade.'
                );
            }

            if (isset($pendentesIndex[$cgm])) {
                throw new MatriculaAlunoBloqueadaException(
                    $pendentesIndex[$cgm],
                    'Este CGM já possui uma matrícula pendente em outra unidade. Resolva a pendência antes de criar uma nova matrícula.'
                );
            }

            $origemPendente = $ativosIndex[$cgm] ?? null;
            $origemHistorica = $origemPendente ? null : $this->ultimaMatriculaHistorica($cgm);
            $status = $origemPendente ? Aluno::STATUS_PENDENTE : Aluno::STATUS_MATRICULADO;

            $aluno = new Aluno([
                ...$linha,
                'nome' => $origemPendente?->nome ?? $linha['nome'],
                'cgm' => $cgm,
                'data_nascimento' => $origemPendente?->data_nascimento ?? $linha['data_nascimento'] ?? null,
                'status' => $status,
                'status_alterado_em' => now(),
                'status_alterado_por' => $usuario?->id,
                'status_motivo' => $linha['status_motivo'] ?? ($origemPendente
                    ? 'Matrícula criada como pendente por transferência não finalizada na escola de origem.'
                    : 'Matrícula criada no sistema.'),
                'aluno_origem_id' => $origemHistorica?->id,
                'turma_origem_id' => $origemHistorica?->id_turma,
                'movimentacao_origem' => $origemHistorica ? $this->tipoOrigemPorStatus($origemHistorica) : null,
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
                $this->copiarDadosAvaliativosBloqueados(
                    origem: $origemHistorica,
                    destino: $aluno,
                    tipo: $this->tipoOrigemPorStatus($origemHistorica)
                );
            }
        }

        foreach ($alunosNotificar as $alunoPendente) {
            app(AlunoTransferenciaPendenteService::class)->notificarPendencia($alunoPendente, $usuario, true);
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
    ): ?Aluno
    {
        $cgm = Aluno::normalizarCgm($cgm);

        if ($cgm === '') {
            return null;
        }

        $turmaDestino = Turma::query()->find($turmaDestinoId);
        $escolaDestinoId = (int) ($turmaDestino?->id_escola ?? 0);
        $chaveUnidade = Aluno::chaveCgmUnidade($escolaDestinoId, $cgm);

        if ($chaveUnidade) {
            $ativoNaMesmaUnidade = Aluno::query()
                ->with('turma.escola')
                ->where('cgm_unidade_matricula_ativa', $chaveUnidade)
                ->when($ignorar, fn (Builder $query): Builder => $query->whereKeyNot((int) $ignorar->id))
                ->first();

            if ($ativoNaMesmaUnidade) {
                throw new MatriculaAlunoBloqueadaException(
                    $ativoNaMesmaUnidade,
                    'Este CGM já está cadastrado nesta unidade.'
                );
            }
        }

        $pendente = Aluno::query()
            ->with('turma.escola')
            ->where('cgm', $cgm)
            ->where('status', Aluno::STATUS_PENDENTE)
            ->when($ignorar, fn (Builder $query): Builder => $query->whereKeyNot((int) $ignorar->id))
            ->first();

        if ($pendente) {
            throw new MatriculaAlunoBloqueadaException(
                $pendente,
                'Este CGM já possui uma matrícula pendente em outra unidade. Resolva a pendência antes de criar uma nova matrícula.'
            );
        }

        $ativo = Aluno::query()
            ->with('turma.escola')
            ->where('cgm_matricula_ativa', $cgm)
            ->when($ignorar, fn (Builder $query): Builder => $query->whereKeyNot((int) $ignorar->id))
            ->first();

        if (! $ativo) {
            return null;
        }

        if ($permitirPendencia) {
            return $ativo;
        }

        $this->notificarImpedimentoMatricula($ativo, $turmaDestinoId, $usuario);

        throw new MatriculaAlunoBloqueadaException($ativo);
    }

    public function remanejar(Aluno $aluno, int $turmaDestinoId, ?User $usuario = null, ?string $motivo = null): Aluno
    {
        return DB::transaction(function () use ($aluno, $turmaDestinoId, $usuario, $motivo): Aluno {
            $aluno->refresh()->loadMissing('turma.escola', 'turma.serie');
            $turmaDestino = Turma::query()->with(['escola', 'serie'])->findOrFail($turmaDestinoId);
            $statusDestino = $aluno->estaPendente() ? Aluno::STATUS_PENDENTE : Aluno::STATUS_MATRICULADO;
            $pendenciaOrigemId = $aluno->pendencia_origem_aluno_id;

            if (! $aluno->estaMatriculado() && ! $aluno->estaPendente()) {
                throw new RuntimeException('Somente alunos matriculados ou pendentes podem ser remanejados.');
            }

            if ((int) $aluno->id_turma === (int) $turmaDestino->id) {
                throw new RuntimeException('Selecione uma turma diferente para remanejar o aluno.');
            }

            if ((int) $aluno->turma?->id_escola !== (int) $turmaDestino->id_escola) {
                throw new RuntimeException('Remanejamento so pode ocorrer dentro da mesma escola.');
            }

            if ((int) $aluno->turma?->id_serie !== (int) $turmaDestino->id_serie) {
                throw new RuntimeException('Remanejamento so pode ocorrer entre turmas da mesma série.');
            }

            $aluno->forceFill([
                'status' => Aluno::STATUS_REMANEJADO,
                'status_alterado_em' => now(),
                'status_alterado_por' => $usuario?->id,
                'status_motivo' => $motivo ?: 'Aluno remanejado para outra turma da mesma escola.',
            ])->save();

            $this->bloquearDadosAvaliativosOrigem($aluno, self::MOVIMENTACAO_REMANEJAMENTO);

            $novoAluno = Aluno::query()->create([
                'nome' => $aluno->nome,
                'cgm' => $aluno->cgm,
                'data_nascimento' => $aluno->data_nascimento,
                'id_turma' => (int) $turmaDestino->id,
                'status' => $statusDestino,
                'status_alterado_em' => now(),
                'status_alterado_por' => $usuario?->id,
                'status_motivo' => $motivo ?: 'Matrícula criada por remanejamento.',
                'aluno_origem_id' => (int) $aluno->id,
                'turma_origem_id' => (int) $aluno->id_turma,
                'movimentacao_origem' => self::MOVIMENTACAO_REMANEJAMENTO,
                'pendencia_origem_aluno_id' => $pendenciaOrigemId,
            ]);

            $this->copiarDadosAvaliativosBloqueados($aluno, $novoAluno, self::MOVIMENTACAO_REMANEJAMENTO);

            return $novoAluno;
        });
    }

    public function transferir(Aluno $aluno, ?User $usuario = null, ?string $motivo = null): Aluno
    {
        return DB::transaction(function () use ($aluno, $usuario, $motivo): Aluno {
            $aluno->refresh();

            if (! $aluno->estaMatriculado()) {
                throw new RuntimeException('Somente alunos matriculados podem ser transferidos.');
            }

            $aluno->forceFill([
                'status' => Aluno::STATUS_TRANSFERIDO,
                'status_alterado_em' => now(),
                'status_alterado_por' => $usuario?->id,
                'status_motivo' => $motivo ?: 'Parecer de transferência gerado.',
            ])->save();

            $this->bloquearDadosAvaliativosOrigem($aluno, self::MOVIMENTACAO_TRANSFERENCIA);
            $this->resolverPendenciasDeTransferencia($aluno, $usuario);

            return $aluno;
        });
    }

    public function marcarStatusFinal(Aluno $aluno, string $status, ?User $usuario = null, ?string $motivo = null): Aluno
    {
        if (! in_array($status, [Aluno::STATUS_APROVADO, Aluno::STATUS_RETIDO, Aluno::STATUS_TRANSFERIDO], true)) {
            throw new RuntimeException('Status final inválido para aluno.');
        }

        $aluno->forceFill([
            'status' => $status,
            'status_alterado_em' => now(),
            'status_alterado_por' => $usuario?->id,
            'status_motivo' => $motivo,
        ])->save();

        $this->bloquearDadosAvaliativosOrigem(
            $aluno,
            $status === Aluno::STATUS_TRANSFERIDO ? self::MOVIMENTACAO_TRANSFERENCIA : self::MOVIMENTACAO_HISTORICO
        );

        return $aluno;
    }

    public function copiarDadosAvaliativosBloqueados(Aluno $origem, Aluno $destino, string $tipo): void
    {
        $origem->loadMissing('turma');
        $destino->loadMissing('turma');

        if (! $destino->turma) {
            return;
        }

        $this->sincronizarAvaliacoesHistoricasComTurmaDestino($origem, $destino);

        $pautasCache = [];
        $bloquearCopia = $this->copiaAvaliativaDeveFicarBloqueada($tipo);

        $origem->avaliacaoRespostas()
            ->whereNotNull('alternativa_id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $respostas) use ($destino, $tipo, $bloquearCopia, &$pautasCache): void {
                foreach ($respostas as $resposta) {
                    if (! $this->respostaPodeIrParaTurma($resposta, $destino, $pautasCache)) {
                        continue;
                    }

                    $existente = AvaliacaoResposta::query()
                        ->where('avaliacao_id', (int) $resposta->avaliacao_id)
                        ->where('pauta_id', (int) $resposta->pauta_id)
                        ->where('turma_id', (int) $destino->id_turma)
                        ->where('aluno_id', (int) $destino->id)
                        ->first();

                    if ($existente && ! $existente->bloqueada) {
                        continue;
                    }

                    AvaliacaoResposta::query()->updateOrCreate(
                        [
                            'avaliacao_id' => (int) $resposta->avaliacao_id,
                            'pauta_id' => (int) $resposta->pauta_id,
                            'turma_id' => (int) $destino->id_turma,
                            'aluno_id' => (int) $destino->id,
                        ],
                        [
                            'professor_id' => $resposta->professor_id,
                            'alternativa_id' => $resposta->alternativa_id,
                            'observacao' => $resposta->observacao,
                            'respondido_em' => $resposta->respondido_em,
                            'bloqueada' => $bloquearCopia,
                            'resposta_origem_id' => $resposta->resposta_origem_id ?: $resposta->id,
                            'aluno_origem_id' => $resposta->aluno_origem_id ?: $resposta->aluno_id,
                            'turma_origem_id' => $resposta->turma_origem_id ?: $resposta->turma_id,
                            'bloqueio_tipo' => $tipo,
                        ]
                    );
                }
            });

        $origem->avaliacaoInformacoesComplementares()
            ->whereNotNull('informacoes_complementares')
            ->orderBy('id')
            ->chunkById(200, function (Collection $registros) use ($destino, $tipo, $bloquearCopia): void {
                foreach ($registros as $registro) {
                    if (! $this->turmaParticipaDaAvaliacao((int) $destino->id_turma, (int) $registro->avaliacao_id)) {
                        continue;
                    }

                    $existente = AvaliacaoInformacaoComplementar::query()
                        ->where('avaliacao_id', (int) $registro->avaliacao_id)
                        ->where('turma_id', (int) $destino->id_turma)
                        ->where('aluno_id', (int) $destino->id)
                        ->where('componente_curricular_id', $registro->componente_curricular_id)
                        ->first();

                    if ($existente && ! $existente->bloqueada) {
                        continue;
                    }

                    AvaliacaoInformacaoComplementar::query()->updateOrCreate(
                        [
                            'avaliacao_id' => (int) $registro->avaliacao_id,
                            'turma_id' => (int) $destino->id_turma,
                            'aluno_id' => (int) $destino->id,
                            'componente_curricular_id' => $registro->componente_curricular_id,
                        ],
                        [
                            'professor_id' => $registro->professor_id,
                            'informacoes_complementares' => $registro->informacoes_complementares,
                            'bloqueada' => $bloquearCopia,
                            'informacao_origem_id' => $registro->informacao_origem_id ?: $registro->id,
                            'aluno_origem_id' => $registro->aluno_origem_id ?: $registro->aluno_id,
                            'turma_origem_id' => $registro->turma_origem_id ?: $registro->turma_id,
                            'bloqueio_tipo' => $tipo,
                        ]
                    );
                }
            });
    }

    private function copiaAvaliativaDeveFicarBloqueada(string $tipo): bool
    {
        return $tipo !== self::MOVIMENTACAO_TRANSFERENCIA;
    }

    private function bloquearDadosAvaliativosOrigem(Aluno $aluno, string $tipo): void
    {
        AvaliacaoResposta::query()
            ->where('aluno_id', (int) $aluno->id)
            ->update([
                'bloqueada' => true,
                'bloqueio_tipo' => $tipo,
                'updated_at' => now(),
            ]);

        AvaliacaoInformacaoComplementar::query()
            ->where('aluno_id', (int) $aluno->id)
            ->update([
                'bloqueada' => true,
                'bloqueio_tipo' => $tipo,
                'updated_at' => now(),
            ]);
    }

    private function sincronizarAvaliacoesHistoricasComTurmaDestino(Aluno $origem, Aluno $destino): void
    {
        $turmaOrigemId = (int) $origem->id_turma;
        $turmaDestinoId = (int) $destino->id_turma;

        if ($turmaOrigemId <= 0 || $turmaDestinoId <= 0) {
            return;
        }

        $avaliacoesIds = DB::table('avaliacao_turma')
            ->where('turma_id', $turmaOrigemId)
            ->pluck('avaliacao_id')
            ->merge($origem->avaliacaoRespostas()->pluck('avaliacao_id'))
            ->merge($origem->avaliacaoInformacoesComplementares()->pluck('avaliacao_id'))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($avaliacoesIds->isEmpty()) {
            return;
        }

        $agora = now();

        DB::table('avaliacao_turma')->insertOrIgnore(
            $avaliacoesIds
                ->map(fn (int $avaliacaoId): array => [
                    'avaliacao_id' => $avaliacaoId,
                    'turma_id' => $turmaDestinoId,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ])
                ->all()
        );
    }

    private function ultimaMatriculaHistorica(string $cgm): ?Aluno
    {
        return Aluno::query()
            ->with('turma')
            ->where('cgm', Aluno::normalizarCgm($cgm))
            ->whereNotIn('status', [Aluno::STATUS_MATRICULADO, Aluno::STATUS_PENDENTE])
            ->latest('status_alterado_em')
            ->latest('updated_at')
            ->first();
    }

    private function tipoOrigemPorStatus(Aluno $origem): string
    {
        return $origem->status === Aluno::STATUS_TRANSFERIDO
            ? self::MOVIMENTACAO_TRANSFERENCIA
            : self::MOVIMENTACAO_HISTORICO;
    }

    private function resolverPendenciasDeTransferencia(Aluno $origem, ?User $usuario): void
    {
        Aluno::query()
            ->where('pendencia_origem_aluno_id', (int) $origem->id)
            ->where('status', Aluno::STATUS_PENDENTE)
            ->orderBy('id')
            ->get()
            ->each(function (Aluno $pendente) use ($origem, $usuario): void {
                $this->copiarDadosAvaliativosBloqueados($origem, $pendente, self::MOVIMENTACAO_TRANSFERENCIA);

                $pendente->forceFill([
                    'status' => Aluno::STATUS_MATRICULADO,
                    'status_alterado_em' => now(),
                    'status_alterado_por' => $usuario?->id,
                    'status_motivo' => 'Pendência de transferência resolvida pelo parecer da escola de origem.',
                    'aluno_origem_id' => (int) $origem->id,
                    'turma_origem_id' => (int) $origem->id_turma,
                    'movimentacao_origem' => self::MOVIMENTACAO_TRANSFERENCIA,
                    'pendencia_origem_aluno_id' => null,
                ])->save();
            });
    }

    private function respostaPodeIrParaTurma(AvaliacaoResposta $resposta, Aluno $destino, array &$pautasCache): bool
    {
        if (! $this->turmaParticipaDaAvaliacao((int) $destino->id_turma, (int) $resposta->avaliacao_id)) {
            return false;
        }

        $pautaId = (int) $resposta->pauta_id;

        if (! array_key_exists($pautaId, $pautasCache)) {
            $pautasCache[$pautaId] = Pauta::query()->find($pautaId);
        }

        $pauta = $pautasCache[$pautaId];

        if (! $pauta) {
            return false;
        }

        return is_null($pauta->serie_id)
            || (int) $pauta->serie_id === (int) $destino->turma?->id_serie;
    }

    private function turmaParticipaDaAvaliacao(int $turmaId, int $avaliacaoId): bool
    {
        return DB::table('avaliacao_turma')
            ->where('turma_id', $turmaId)
            ->where('avaliacao_id', $avaliacaoId)
            ->exists();
    }

    private function notificarImpedimentoMatricula(Aluno $alunoAtivo, int $turmaDestinoId, ?User $usuario): void
    {
        $alunoAtivo->loadMissing('turma.escola');

        $titulo = 'Impedimento de matrícula por falta de transferência';
        $mensagem = (new MatriculaAlunoBloqueadaException($alunoAtivo))->getMessage();
        $escolaId = (int) ($alunoAtivo->turma?->id_escola ?? 0);
        $url = route('filament.admin.pages.parecer-transferencia-aluno', ['aluno' => $alunoAtivo->id]);

        $destinatarios = $this->usuariosParaNotificarImpedimento($escolaId);

        foreach ($destinatarios as $destinatario) {
            $podeReceberLink = $destinatario->hasPermissionLike('realizar transferencia de aluno')
                || $destinatario->hasPermissionLike('realizar tranferencia de aluno');

            $destinatario->notify(new SistemaNotification(
                titulo: $titulo,
                mensagem: $mensagem,
                url: $podeReceberLink ? $url : null,
                label: $podeReceberLink ? 'Abrir parecer de transferência' : null,
                prioridade: 'alta',
                escopo: $alunoAtivo->turma?->escola?->nome,
                metadata: [
                    'tipo' => 'impedimento_matricula_transferencia',
                    'aluno_id' => (int) $alunoAtivo->id,
                    'turma_destino_id' => $turmaDestinoId,
                    'solicitante_id' => $usuario?->id,
                ]
            ));
        }
    }

    private function usuariosParaNotificarImpedimento(int $escolaId): Collection
    {
        return User::query()
            ->with(['roles.permissions', 'permissions', 'escolas:id', 'professores:id,user_id,id_escola'])
            ->whereNotNull('email')
            ->get()
            ->filter(function (User $user) use ($escolaId): bool {
                $temPermissaoEscola = $user->hasPermissionLike('notificar impedimento de matricula por falta de transferencia')
                    && $this->usuarioPertenceAEscola($user, $escolaId);
                $temPermissaoGestao = $user->hasPermissionLike('gerenciar impedimento de matricula por falta de transferencia');

                return $temPermissaoEscola || $temPermissaoGestao;
            })
            ->unique('id')
            ->values();
    }

    private function usuarioPertenceAEscola(User $user, int $escolaId): bool
    {
        if ($escolaId <= 0) {
            return false;
        }

        $ids = collect([$user->id_escola])
            ->merge($user->escolas->pluck('id'))
            ->merge($user->professores->pluck('id_escola'))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return in_array($escolaId, $ids, true);
    }
}
