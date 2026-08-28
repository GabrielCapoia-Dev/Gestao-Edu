<?php

namespace App\Services\Avaliacoes;

use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\AvaliacaoAlunoSnapshot;
use App\Models\AvaliacaoInformacaoOperacional;
use App\Models\AvaliacaoRespostaOperacional;
use App\Models\AvaliacaoSnapshotEvento;
use App\Models\AvaliacaoTurmaCiclo;
use App\Models\AvaliacaoTurmaTokenEscrita;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AvaliacaoMovimentacaoRelacionalService
{
    public function __construct(
        private readonly AvaliacaoTurmaCicloService $ciclos,
        private readonly AvaliacaoDocumentoReader $reader,
        private readonly AvaliacaoSnapshotService $snapshots,
    ) {
    }

    public function mover(Aluno $origem, Aluno $destino, string $tipo, ?User $ator = null): void
    {
        $avaliacoesIds = AvaliacaoRespostaOperacional::query()
            ->where('aluno_id', (int) $origem->id)
            ->pluck('avaliacao_id')
            ->merge(AvaliacaoInformacaoOperacional::query()->where('aluno_id', (int) $origem->id)->pluck('avaliacao_id'))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        foreach ($avaliacoesIds as $avaliacaoId) {
            $avaliacao = Avaliacao::query()->findOrFail($avaliacaoId);
            $this->ciclos->sincronizarAvaliacao($avaliacao);
        }

        DB::transaction(function () use ($origem, $destino, $tipo, $ator, $avaliacoesIds): void {
            $pares = [];
            foreach ($avaliacoesIds as $avaliacaoId) {
                $origemCiclo = AvaliacaoTurmaCiclo::query()
                    ->where('avaliacao_id', $avaliacaoId)
                    ->where(function ($query) use ($origem): void {
                        $query->whereHas('respostas', fn ($respostas) => $respostas->where('aluno_id', (int) $origem->id))
                            ->orWhereHas('informacoes', fn ($informacoes) => $informacoes->where('aluno_id', (int) $origem->id));
                    })
                    ->first();
                $destinoCiclo = AvaliacaoTurmaCiclo::query()
                    ->where('avaliacao_id', $avaliacaoId)
                    ->where('turma_avaliativa_id', (int) $destino->id_turma)
                    ->first();

                if ($origemCiclo && $destinoCiclo) {
                    $pares[] = [$origemCiclo, $destinoCiclo];
                }
            }

            $cicloIds = collect($pares)->flatten()->pluck('id')->map(fn ($id): int => (int) $id)->unique()->sort()->values();
            $ciclosBloqueados = AvaliacaoTurmaCiclo::query()->whereIn('id', $cicloIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $tokens = AvaliacaoTurmaTokenEscrita::query()->whereIn('ciclo_id', $cicloIds)->orderBy('ciclo_id')->lockForUpdate()->get()->keyBy('ciclo_id');

            foreach ($pares as [$origemInicial, $destinoInicial]) {
                $origemCiclo = $ciclosBloqueados->get((int) $origemInicial->id);
                $destinoCiclo = $ciclosBloqueados->get((int) $destinoInicial->id);

                if (! $origemCiclo || ! $destinoCiclo || ! $tokens->has((int) $origemCiclo->id) || ! $tokens->has((int) $destinoCiclo->id)) {
                    throw new RuntimeException('A movimentação encontrou um ciclo sem token de escrita.');
                }
                if ($origemCiclo->status !== AvaliacaoTurmaCiclo::STATUS_ABERTA || $destinoCiclo->status !== AvaliacaoTurmaCiclo::STATUS_ABERTA) {
                    throw new RuntimeException('Movimentações avaliativas só são permitidas em ciclos abertos com roster dinâmico.');
                }

                $this->moverEntreCiclos($origem, $destino, $tipo, $ator, $origemCiclo, $destinoCiclo, $tokens);
            }
        }, 3);
    }

    private function moverEntreCiclos(
        Aluno $origem,
        Aluno $destino,
        string $tipo,
        ?User $ator,
        AvaliacaoTurmaCiclo $origemCiclo,
        AvaliacaoTurmaCiclo $destinoCiclo,
        $tokens,
    ): void {
        $respostasOrigem = AvaliacaoRespostaOperacional::query()
            ->where('ciclo_id', (int) $origemCiclo->id)
            ->where('aluno_id', (int) $origem->id)
            ->orderBy('pauta_id')
            ->get();
        $infosOrigem = AvaliacaoInformacaoOperacional::query()
            ->where('ciclo_id', (int) $origemCiclo->id)
            ->where('aluno_id', (int) $origem->id)
            ->orderBy('componente_curricular_id')
            ->get();

        if ($respostasOrigem->isEmpty() && $infosOrigem->isEmpty()) {
            return;
        }

        $turmaOrigem = Turma::query()->findOrFail((int) $origemCiclo->turma_avaliativa_id);
        $documento = $this->reader->ler((int) $origemCiclo->avaliacao_id, $origem, $turmaOrigem, false);
        $payload = [
            ...$documento->payload,
            'schema_version' => AvaliacaoSnapshotService::SCHEMA_VERSION,
            'snapshot_type' => $tipo,
            'movimentacao' => [
                'aluno_origem_id' => (int) $origem->id,
                'aluno_destino_id' => (int) $destino->id,
                'turma_origem_id' => (int) $origem->id_turma,
                'turma_destino_id' => (int) $destino->id_turma,
                'motivo' => $tipo,
                'realizada_em' => now()->toIso8601String(),
            ],
        ];
        $hash = $this->snapshots->hashPayload($payload);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $versaoEvento = (int) AvaliacaoSnapshotEvento::query()
            ->where('ciclo_id', (int) $origemCiclo->id)
            ->where('tipo', $tipo)
            ->max('versao') + 1;
        $evento = AvaliacaoSnapshotEvento::query()->create([
            'id' => (string) Str::uuid(),
            'idempotency_key' => implode(':', ['movimentacao', $tipo, $origem->id, $destino->id, $origemCiclo->avaliacao_id]),
            'tipo' => $tipo,
            'ciclo_id' => (int) $origemCiclo->id,
            'avaliacao_id' => (int) $origemCiclo->avaliacao_id,
            'turma_avaliativa_id' => (int) $origemCiclo->turma_avaliativa_id,
            'turma_origem_id' => (int) $origem->id_turma,
            'turma_destino_id' => (int) $destino->id_turma,
            'versao' => $versaoEvento,
            'schema_version' => AvaliacaoSnapshotService::SCHEMA_VERSION,
            'criado_por' => $ator?->id,
            'criado_por_snapshot' => $ator ? ['id' => (int) $ator->id, 'nome' => (string) $ator->name, 'email' => (string) $ator->email] : null,
            'motivo' => $tipo,
            'total_alunos' => 1,
            'total_respostas_esperadas' => $respostasOrigem->count(),
            'total_respostas_geradas' => $respostasOrigem->count(),
            'tamanho_bytes' => strlen($json),
            'payload_hash_agregado' => hash('sha256', $hash),
            'publicado_em' => now(),
        ]);
        AvaliacaoAlunoSnapshot::query()->create([
            'evento_id' => (string) $evento->id,
            'avaliacao_id' => (int) $origemCiclo->avaliacao_id,
            'ciclo_id' => (int) $origemCiclo->id,
            'turma_avaliativa_id' => (int) $origemCiclo->turma_avaliativa_id,
            'turma_origem_id' => (int) $origem->id_turma,
            'turma_destino_id' => (int) $destino->id_turma,
            'aluno_id' => (int) $origem->id,
            'cgm' => (string) $origem->cgm,
            'tipo' => $tipo,
            'schema_version' => AvaliacaoSnapshotService::SCHEMA_VERSION,
            'payload' => $payload,
            'payload_hash' => $hash,
            'total_pautas_esperadas' => $respostasOrigem->count(),
            'total_respostas' => $respostasOrigem->count(),
            'total_informacoes' => $infosOrigem->count(),
            'tamanho_bytes' => strlen($json),
        ]);

        $tokenDestinoId = (int) $tokens->get((int) $destinoCiclo->id)->id;
        foreach ($respostasOrigem as $resposta) {
            $existente = AvaliacaoRespostaOperacional::query()
                ->where('ciclo_id', (int) $destinoCiclo->id)
                ->where('aluno_id', (int) $destino->id)
                ->where('pauta_id', (int) $resposta->pauta_id)
                ->first();
            if ($existente && ((int) $existente->alternativa_id !== (int) $resposta->alternativa_id
                || trim((string) $existente->observacao) !== trim((string) $resposta->observacao))) {
                throw new RuntimeException("Há respostas divergentes na pauta {$resposta->pauta_id}; resolva o conflito antes da movimentação.");
            }
            if ($existente) {
                $resposta->delete();
                continue;
            }
            $resposta->forceFill([
                'token_escrita_id' => $tokenDestinoId,
                'ciclo_id' => (int) $destinoCiclo->id,
                'turma_avaliativa_id' => (int) $destinoCiclo->turma_avaliativa_id,
                'turma_origem_id' => (int) $destino->id_turma,
                'aluno_id' => (int) $destino->id,
                'version' => (int) $resposta->version + 1,
            ])->save();
        }

        foreach ($infosOrigem as $info) {
            $existente = AvaliacaoInformacaoOperacional::query()
                ->where('ciclo_id', (int) $destinoCiclo->id)
                ->where('aluno_id', (int) $destino->id)
                ->where('componente_chave', (int) $info->componente_chave)
                ->first();
            if ($existente && trim((string) $existente->texto) !== trim((string) $info->texto)) {
                throw new RuntimeException('Há informações complementares divergentes; resolva o conflito antes da movimentação.');
            }
            if ($existente) {
                $info->delete();
                continue;
            }
            $info->forceFill([
                'token_escrita_id' => $tokenDestinoId,
                'ciclo_id' => (int) $destinoCiclo->id,
                'turma_avaliativa_id' => (int) $destinoCiclo->turma_avaliativa_id,
                'turma_origem_id' => (int) $destino->id_turma,
                'aluno_id' => (int) $destino->id,
                'version' => (int) $info->version + 1,
            ])->save();
        }
    }
}
