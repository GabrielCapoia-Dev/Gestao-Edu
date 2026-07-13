<?php

namespace Tests\Concerns;

use App\Models\Aluno;
use App\Models\AvaliacaoAlunoDocumento;
use App\Services\Avaliacoes\AvaliacaoAlunoDocumentoService;

trait CreatesAvaliacaoDocumentos
{
    protected function criarDocumentoResposta(array $dados): AvaliacaoAlunoDocumento
    {
        $aluno = Aluno::query()->findOrFail($dados['aluno_id']);
        $service = app(AvaliacaoAlunoDocumentoService::class);
        $documento = $service->obterOuCriar((int) $dados['avaliacao_id'], $aluno, somentePrincipal: false);

        if (! empty($dados['alternativa_id'])) {
            $service->salvarPauta($documento, (int) $dados['pauta_id'], [
                'alternativa_id' => (int) $dados['alternativa_id'],
                'observacao' => $dados['observacao'] ?? null,
                'professor_id' => $dados['professor_id'] ?? null,
                'componente_curricular_id' => $dados['componente_curricular_id'] ?? null,
                'respondido_em' => $dados['respondido_em'] ?? now(),
            ]);
        }

        if (array_key_exists('informacoes_complementares', $dados) && ! empty($dados['componente_curricular_id'])) {
            $service->salvarInfoComplementar(
                $documento->fresh() ?? $documento,
                (int) $dados['componente_curricular_id'],
                $dados['informacoes_complementares'],
                $dados['professor_id'] ?? null,
            );
        }

        return $documento->fresh();
    }
}
