<?php

namespace App\Services\Avaliacoes;

use App\Models\Aluno;
use App\Models\AvaliacaoAlunoDocumento;
use App\Models\User;

/**
 * Garante que uma movimentação nunca ignore respostas que ainda estejam somente
 * no JSON legado. Antes de transferir/remanejar, inicializa no relacional apenas
 * as avaliações efetivamente encontradas para o aluno de origem.
 */
class AvaliacaoMovimentacaoRelacionalServiceLazy extends AvaliacaoMovimentacaoRelacionalService
{
    public function __construct(
        AvaliacaoTurmaCicloService $ciclos,
        AvaliacaoDocumentoReader $reader,
        AvaliacaoSnapshotService $snapshots,
        private readonly AvaliacaoMigracaoLazyService $migracaoLazy,
    ) {
        parent::__construct($ciclos, $reader, $snapshots);
    }

    public function mover(Aluno $origem, Aluno $destino, string $tipo, ?User $ator = null): void
    {
        AvaliacaoAlunoDocumento::query()
            ->where('aluno_id', (int) $origem->id)
            ->pluck('avaliacao_id')
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->sort()
            ->each(fn (int $avaliacaoId) => $this->migracaoLazy->garantirParaAlunos(
                $avaliacaoId,
                [(int) $origem->id],
            ));

        parent::mover($origem, $destino, $tipo, $ator);
    }
}
