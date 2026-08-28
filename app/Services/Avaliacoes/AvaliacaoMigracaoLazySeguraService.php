<?php

namespace App\Services\Avaliacoes;

use App\Models\AvaliacaoAlunoDocumento;
use App\Models\AvaliacaoTurmaCiclo;
use App\Models\Turma;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Proteção adicional para bases legadas onde mais de uma turma avaliativa pode
 * apontar para a mesma turma física de alunos (casos integrais legados).
 *
 * O JSON antigo não contém informação suficiente para escolher uma das duas
 * turmas avaliativas. Nesses casos é melhor interromper e registrar a ambiguidade
 * do que duplicar silenciosamente as mesmas respostas em dois ciclos.
 */
class AvaliacaoMigracaoLazySeguraService extends AvaliacaoMigracaoLazyService
{
    public function __construct(
        AvaliacaoTurmaCicloService $ciclos,
        private readonly TurmaAvaliacaoAlunoScopeService $escoposSeguros,
    ) {
        parent::__construct($ciclos, $escoposSeguros);
    }

    public function garantirTurma(int $avaliacaoId, int $turmaAvaliativaId): AvaliacaoTurmaCiclo
    {
        $ciclo = AvaliacaoTurmaCiclo::query()
            ->where('avaliacao_id', $avaliacaoId)
            ->where('turma_avaliativa_id', $turmaAvaliativaId)
            ->first();

        if (
            $ciclo?->status === AvaliacaoTurmaCiclo::STATUS_CONCLUIDA
            || $ciclo?->operacional_inicializado_em !== null
        ) {
            return $ciclo;
        }

        $turmas = Turma::query()
            ->whereIn('id', DB::table('avaliacao_turma')
                ->where('avaliacao_id', $avaliacaoId)
                ->select('turma_id'))
            ->get();
        $escopos = $this->escoposSeguros->escoposPorTurma($turmas);
        $escopoAtual = $escopos[$turmaAvaliativaId] ?? null;

        if (is_array($escopoAtual)) {
            $turmaOrigemId = (int) $escopoAtual['turma_origem_id'];
            $possuiLegado = AvaliacaoAlunoDocumento::query()
                ->where('avaliacao_id', $avaliacaoId)
                ->where('turma_id', $turmaOrigemId)
                ->exists();

            if ($possuiLegado) {
                $candidatas = collect($escopos)
                    ->filter(fn (array $escopo): bool => (int) $escopo['turma_origem_id'] === $turmaOrigemId)
                    ->keys()
                    ->map(fn ($id): int => (int) $id)
                    ->values();

                if ($candidatas->count() > 1) {
                    throw new RuntimeException(
                        'O JSON legado da turma física '.$turmaOrigemId
                        .' corresponde a mais de uma turma avaliativa ('.$candidatas->implode(', ')
                        .'). A conversão automática foi bloqueada para evitar duplicação de respostas.',
                    );
                }
            }
        }

        return parent::garantirTurma($avaliacaoId, $turmaAvaliativaId);
    }
}
