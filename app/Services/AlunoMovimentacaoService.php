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

            $this->bloquearSeCgmAtivo($cgm, $turmaId, $usuario);

            $origem = $this->ultimaMatriculaHistorica($cgm);

            $aluno = Aluno::query()->create([
                ...$data,
                'cgm' => $cgm,
                'status' => Aluno::STATUS_MATRICULADO,
                'status_alterado_em' => now(),
                'status_alterado_por' => $usuario?->id,
                'status_motivo' => $data['status_motivo'] ?? 'Matricula criada no sistema.',
                'aluno_origem_id' => $origem?->id,
                'turma_origem_id' => $origem?->id_turma,
                'movimentacao_origem' => $origem ? $this->tipoOrigemPorStatus($origem) : null,
            ]);

            if ($origem) {
                $this->copiarDadosAvaliativosBloqueados(
                    origem: $origem,
                    destino: $aluno,
                    tipo: $this->tipoOrigemPorStatus($origem)
                );
            }

            return $aluno;
        });
    }

    public function bloquearSeCgmAtivo(string $cgm, int $turmaDestinoId, ?User $usuario = null, ?Aluno $ignorar = null): void
    {
        $cgm = Aluno::normalizarCgm($cgm);

        if ($cgm === '') {
            return;
        }

        $ativo = Aluno::query()
            ->with('turma.escola')
            ->where('cgm_matricula_ativa', $cgm)
            ->when($ignorar, fn (Builder $query): Builder => $query->whereKeyNot((int) $ignorar->id))
            ->first();

        if (! $ativo) {
            return;
        }

        $this->notificarImpedimentoMatricula($ativo, $turmaDestinoId, $usuario);

        throw new MatriculaAlunoBloqueadaException($ativo);
    }

    public function remanejar(Aluno $aluno, int $turmaDestinoId, ?User $usuario = null, ?string $motivo = null): Aluno
    {
        return DB::transaction(function () use ($aluno, $turmaDestinoId, $usuario, $motivo): Aluno {
            $aluno->refresh()->loadMissing('turma.escola');
            $turmaDestino = Turma::query()->with('escola')->findOrFail($turmaDestinoId);

            if (! $aluno->estaMatriculado()) {
                throw new RuntimeException('Somente alunos matriculados podem ser remanejados.');
            }

            if ((int) $aluno->id_turma === (int) $turmaDestino->id) {
                throw new RuntimeException('Selecione uma turma diferente para remanejar o aluno.');
            }

            if ((int) $aluno->turma?->id_escola !== (int) $turmaDestino->id_escola) {
                throw new RuntimeException('Remanejamento so pode ocorrer dentro da mesma escola.');
            }

            $aluno->forceFill([
                'status' => Aluno::STATUS_REMANEJADO,
                'status_alterado_em' => now(),
                'status_alterado_por' => $usuario?->id,
                'status_motivo' => $motivo ?: 'Aluno remanejado para outra turma da mesma escola.',
            ])->save();

            $novoAluno = Aluno::query()->create([
                'nome' => $aluno->nome,
                'cgm' => $aluno->cgm,
                'data_nascimento' => $aluno->data_nascimento,
                'id_turma' => (int) $turmaDestino->id,
                'status' => Aluno::STATUS_MATRICULADO,
                'status_alterado_em' => now(),
                'status_alterado_por' => $usuario?->id,
                'status_motivo' => $motivo ?: 'Matricula criada por remanejamento.',
                'aluno_origem_id' => (int) $aluno->id,
                'turma_origem_id' => (int) $aluno->id_turma,
                'movimentacao_origem' => self::MOVIMENTACAO_REMANEJAMENTO,
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
                'status_motivo' => $motivo ?: 'Parecer de transferencia gerado.',
            ])->save();

            return $aluno;
        });
    }

    public function marcarStatusFinal(Aluno $aluno, string $status, ?User $usuario = null, ?string $motivo = null): Aluno
    {
        if (! in_array($status, [Aluno::STATUS_APROVADO, Aluno::STATUS_RETIDO, Aluno::STATUS_TRANSFERIDO], true)) {
            throw new RuntimeException('Status final invalido para aluno.');
        }

        $aluno->forceFill([
            'status' => $status,
            'status_alterado_em' => now(),
            'status_alterado_por' => $usuario?->id,
            'status_motivo' => $motivo,
        ])->save();

        return $aluno;
    }

    public function copiarDadosAvaliativosBloqueados(Aluno $origem, Aluno $destino, string $tipo): void
    {
        $origem->loadMissing('turma');
        $destino->loadMissing('turma');

        if (! $destino->turma) {
            return;
        }

        $pautasCache = [];

        $origem->avaliacaoRespostas()
            ->whereNotNull('alternativa_id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $respostas) use ($destino, $tipo, &$pautasCache): void {
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
                            'bloqueada' => true,
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
            ->chunkById(200, function (Collection $registros) use ($destino, $tipo): void {
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
                            'bloqueada' => true,
                            'informacao_origem_id' => $registro->informacao_origem_id ?: $registro->id,
                            'aluno_origem_id' => $registro->aluno_origem_id ?: $registro->aluno_id,
                            'turma_origem_id' => $registro->turma_origem_id ?: $registro->turma_id,
                            'bloqueio_tipo' => $tipo,
                        ]
                    );
                }
            });
    }

    private function ultimaMatriculaHistorica(string $cgm): ?Aluno
    {
        return Aluno::query()
            ->with('turma')
            ->where('cgm', Aluno::normalizarCgm($cgm))
            ->where('status', '!=', Aluno::STATUS_MATRICULADO)
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

        $titulo = 'Impedimento de matricula por falta de transferencia';
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
                label: $podeReceberLink ? 'Abrir Parecer de Transferencia' : null,
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
