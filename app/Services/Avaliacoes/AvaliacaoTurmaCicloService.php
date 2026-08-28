<?php

namespace App\Services\Avaliacoes;

use App\Exceptions\AvaliacaoCicloFechadoException;
use App\Models\AvaliacaoTurmaCiclo;
use App\Models\AvaliacaoTurmaTokenEscrita;
use App\Models\Avaliacao;
use App\Models\Turma;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AvaliacaoTurmaCicloService
{
    public function sincronizarAvaliacao(Avaliacao $avaliacao): void
    {
        $turmas = $avaliacao->turmas()->get();
        $escopos = app(TurmaAvaliacaoAlunoScopeService::class)->escoposPorTurma($turmas);
        $turmaIds = $turmas->pluck('id')->map(fn ($id): int => (int) $id)->all();

        DB::transaction(function () use ($avaliacao, $turmas, $escopos, $turmaIds): void {
            $removidos = AvaliacaoTurmaCiclo::query()
                ->where('avaliacao_id', (int) $avaliacao->id)
                ->when($turmaIds !== [], fn ($query) => $query->whereNotIn('turma_avaliativa_id', $turmaIds))
                ->when($turmaIds === [], fn ($query) => $query)
                ->get();

            foreach ($removidos as $ciclo) {
                if ($ciclo->respostas()->exists() || $ciclo->informacoes()->exists() || $ciclo->eventos()->exists()) {
                    throw new \RuntimeException('Não é possível remover uma turma da avaliação depois do primeiro dado persistido.');
                }
                $ciclo->delete();
            }

            foreach ($turmas->sortBy('id') as $turma) {
                $origemId = (int) ($escopos[(int) $turma->id]['turma_origem_id'] ?? $turma->id);
                $ciclo = AvaliacaoTurmaCiclo::query()->firstOrCreate(
                    ['avaliacao_id' => (int) $avaliacao->id, 'turma_avaliativa_id' => (int) $turma->id],
                    [
                        'turma_origem_id' => $origemId,
                        'status' => AvaliacaoTurmaCiclo::STATUS_ABERTA,
                        'roster_mode' => AvaliacaoTurmaCiclo::ROSTER_DINAMICO,
                    ],
                );

                if ((int) $ciclo->turma_origem_id !== $origemId) {
                    if ($ciclo->respostas()->exists() || $ciclo->informacoes()->exists() || $ciclo->eventos()->exists()) {
                        throw new \RuntimeException('A turma de origem não pode mudar depois do primeiro dado persistido.');
                    }
                    $ciclo->forceFill(['turma_origem_id' => $origemId])->save();
                }

                if ($ciclo->aceitaEscrita()) {
                    AvaliacaoTurmaTokenEscrita::query()->firstOrCreate(
                        ['ciclo_id' => (int) $ciclo->id],
                        ['generation_uuid' => (string) Str::uuid()],
                    );
                }
            }
        }, 3);
    }

    public function obterOuCriar(int $avaliacaoId, int $turmaAvaliativaId, int $turmaOrigemId): AvaliacaoTurmaCiclo
    {
        return DB::transaction(function () use ($avaliacaoId, $turmaAvaliativaId, $turmaOrigemId): AvaliacaoTurmaCiclo {
            $ciclo = AvaliacaoTurmaCiclo::query()->firstOrCreate(
                [
                    'avaliacao_id' => $avaliacaoId,
                    'turma_avaliativa_id' => $turmaAvaliativaId,
                ],
                [
                    'turma_origem_id' => $turmaOrigemId,
                    'status' => AvaliacaoTurmaCiclo::STATUS_ABERTA,
                    'roster_mode' => AvaliacaoTurmaCiclo::ROSTER_DINAMICO,
                ],
            );

            if ((int) $ciclo->turma_origem_id !== $turmaOrigemId && $ciclo->roster_mode === AvaliacaoTurmaCiclo::ROSTER_DINAMICO) {
                $ciclo->forceFill(['turma_origem_id' => $turmaOrigemId])->save();
            }

            if (! $ciclo->aceitaEscrita()) {
                throw new AvaliacaoCicloFechadoException('Esta turma já foi concluída e não aceita novas respostas.');
            }

            AvaliacaoTurmaTokenEscrita::query()->firstOrCreate(
                ['ciclo_id' => (int) $ciclo->id],
                ['generation_uuid' => (string) Str::uuid()],
            );

            return $ciclo->refresh();
        }, 3);
    }

    public function obter(int $avaliacaoId, int $turmaAvaliativaId): ?AvaliacaoTurmaCiclo
    {
        return AvaliacaoTurmaCiclo::query()
            ->where('avaliacao_id', $avaliacaoId)
            ->where('turma_avaliativa_id', $turmaAvaliativaId)
            ->first();
    }

    public function obterParaEscritaCompartilhada(int $avaliacaoId, int $turmaAvaliativaId): ?AvaliacaoTurmaCiclo
    {
        $ciclo = AvaliacaoTurmaCiclo::query()
            ->select('avaliacao_turma_ciclos.*')
            ->addSelect('avaliacao_turma_tokens_escrita.id as token_escrita_bloqueado_id')
            ->join('avaliacao_turma_tokens_escrita', 'avaliacao_turma_tokens_escrita.ciclo_id', '=', 'avaliacao_turma_ciclos.id')
            ->where('avaliacao_turma_ciclos.avaliacao_id', $avaliacaoId)
            ->where('avaliacao_turma_ciclos.turma_avaliativa_id', $turmaAvaliativaId)
            ->sharedLock()
            ->first();

        if ($ciclo && ! $ciclo->aceitaEscrita()) {
            throw new AvaliacaoCicloFechadoException('Esta turma já foi concluída e não aceita novas respostas.');
        }

        return $ciclo;
    }

    public function bloquearTokenCompartilhado(int $cicloId): AvaliacaoTurmaTokenEscrita
    {
        $token = AvaliacaoTurmaTokenEscrita::query()
            ->where('ciclo_id', $cicloId)
            ->sharedLock()
            ->first();

        if (! $token) {
            throw (new ModelNotFoundException)->setModel(AvaliacaoTurmaTokenEscrita::class, [$cicloId]);
        }

        return $token;
    }

    public function bloquearTokenExclusivo(int $cicloId): AvaliacaoTurmaTokenEscrita
    {
        $token = AvaliacaoTurmaTokenEscrita::query()
            ->where('ciclo_id', $cicloId)
            ->lockForUpdate()
            ->first();

        if (! $token) {
            throw new AvaliacaoCicloFechadoException('Esta turma não possui uma janela de escrita ativa.');
        }

        return $token;
    }
}
