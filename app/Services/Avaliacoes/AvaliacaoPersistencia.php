<?php

namespace App\Services\Avaliacoes;

/**
 * Política de persistência das avaliações.
 *
 * Não existe mais seleção de driver em runtime:
 * - estado operacional aberto/reaberto é sempre relacional;
 * - JSON legado permanece somente como fonte histórica/fallback de dados ainda
 *   não inicializados no relacional;
 * - conclusão gera snapshots JSON canônicos pelo ciclo da turma.
 */
class AvaliacaoPersistencia
{
    public function gravaRelacional(): bool
    {
        return true;
    }

    public function gravaDocumentoLegado(): bool
    {
        return false;
    }

    public function leRelacional(): bool
    {
        return true;
    }
}
