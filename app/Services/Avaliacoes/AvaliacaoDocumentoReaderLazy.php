<?php

namespace App\Services\Avaliacoes;

use App\Data\Avaliacoes\AvaliacaoDocumentoData;
use App\Models\Aluno;
use App\Models\AvaliacaoAlunoDocumento;
use App\Models\AvaliacaoTurmaCiclo;
use App\Models\Turma;

/**
 * Mantém documentos antigos exportáveis sem exigir conversão prévia.
 *
 * Se a turma ainda não foi inicializada no relacional e existe um documento
 * legado para o aluno, a leitura continua vindo do JSON. A partir do momento em
 * que a inicialização lazy termina, o reader original passa naturalmente a ler
 * o relacional (ou o snapshot final, quando concluído).
 */
class AvaliacaoDocumentoReaderLazy extends AvaliacaoDocumentoReader
{
    public function __construct(
        AvaliacaoPersistencia $persistencia,
        ParecerResponsaveisResolver $responsaveis,
    ) {
        parent::__construct($persistencia, $responsaveis);
    }

    public function ler(
        int $avaliacaoId,
        Aluno $aluno,
        Turma $turmaAvaliativa,
        bool $incluirResponsaveis = true,
    ): AvaliacaoDocumentoData {
        $ciclo = AvaliacaoTurmaCiclo::query()
            ->where('avaliacao_id', $avaliacaoId)
            ->where('turma_avaliativa_id', (int) $turmaAvaliativa->id)
            ->first();

        if (
            $ciclo
            && $ciclo->status !== AvaliacaoTurmaCiclo::STATUS_CONCLUIDA
            && $ciclo->operacional_inicializado_em === null
        ) {
            $documento = AvaliacaoAlunoDocumento::query()
                ->where('avaliacao_id', $avaliacaoId)
                ->where('aluno_id', (int) $aluno->id)
                ->first();

            if ($documento) {
                $responsaveisSnapshot = (array) ($documento->responsaveis_snapshot ?? []);

                return new AvaliacaoDocumentoData(
                    payload: (array) $documento->payload,
                    responsaveisSnapshot: $responsaveisSnapshot,
                    contexto: [
                        'escola' => (array) ($responsaveisSnapshot['escola'] ?? []),
                        'turma' => (array) ($responsaveisSnapshot['turma'] ?? []),
                        'serie' => (array) ($responsaveisSnapshot['serie'] ?? []),
                    ],
                    origem: 'json_legado',
                );
            }
        }

        return parent::ler($avaliacaoId, $aluno, $turmaAvaliativa, $incluirResponsaveis);
    }
}
