<?php

namespace App\Services\Avaliacoes;

use App\Models\AvaliacaoSnapshotEvento;
use App\Models\AvaliacaoTurmaCiclo;
use App\Models\User;

/**
 * Garante que a conclusão sempre parta do estado operacional relacional.
 *
 * Isso também cobre turmas legadas que nunca foram abertas por um professor:
 * ao concluir explicitamente, o JSON atual é reidratado uma única vez e o
 * AvaliacaoSnapshotService gera o snapshot final normalmente.
 */
class AvaliacaoSnapshotServiceLazy extends AvaliacaoSnapshotService
{
    public function __construct(
        AvaliacaoTurmaCicloService $ciclos,
        ParecerResponsaveisResolver $responsaveis,
        private readonly AvaliacaoMigracaoLazyService $migracaoLazy,
    ) {
        parent::__construct($ciclos, $responsaveis);
    }

    public function concluir(AvaliacaoTurmaCiclo|int $ciclo, User $ator): AvaliacaoSnapshotEvento
    {
        $registro = $ciclo instanceof AvaliacaoTurmaCiclo
            ? $ciclo
            : AvaliacaoTurmaCiclo::query()->findOrFail((int) $ciclo);

        if ($registro->status !== AvaliacaoTurmaCiclo::STATUS_CONCLUIDA) {
            $this->migracaoLazy->garantirTurma(
                (int) $registro->avaliacao_id,
                (int) $registro->turma_avaliativa_id,
            );
        }

        return parent::concluir((int) $registro->id, $ator);
    }
}
