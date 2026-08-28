<?php

namespace App\Services\Avaliacoes;

use App\Models\Aluno;
use Illuminate\Support\Collection;

/**
 * Adapta o store existente ao corte direto para o relacional.
 *
 * Antes da primeira leitura/escrita de uma turma, converte o JSON legado daquela
 * turma uma única vez. Depois disso, todo o fluxo segue pelo store relacional
 * original sem dual-write.
 */
class AvaliacaoRespostaStoreLazy extends AvaliacaoRespostaStore
{
    public function __construct(
        AvaliacaoPersistencia $persistencia,
        AvaliacaoTurmaCicloService $ciclos,
        AvaliacaoAlunoDocumentoService $documentos,
        private readonly AvaliacaoMigracaoLazyService $migracaoLazy,
    ) {
        parent::__construct($persistencia, $ciclos, $documentos);
    }

    public function salvarPauta(
        int $avaliacaoId,
        int $turmaAvaliativaId,
        Aluno $aluno,
        int $pautaId,
        array $dados,
        ?int $expectedVersion = null,
        array $expectedValues = [],
    ): int {
        $this->migracaoLazy->garantirTurma($avaliacaoId, $turmaAvaliativaId);

        return parent::salvarPauta(
            $avaliacaoId,
            $turmaAvaliativaId,
            $aluno,
            $pautaId,
            $dados,
            $expectedVersion,
            $expectedValues,
        );
    }

    public function removerPauta(
        int $avaliacaoId,
        int $turmaAvaliativaId,
        Aluno $aluno,
        int $pautaId,
        ?int $expectedVersion = null,
    ): void {
        $this->migracaoLazy->garantirTurma($avaliacaoId, $turmaAvaliativaId);

        parent::removerPauta($avaliacaoId, $turmaAvaliativaId, $aluno, $pautaId, $expectedVersion);
    }

    public function salvarInformacao(
        int $avaliacaoId,
        int $turmaAvaliativaId,
        Aluno $aluno,
        int $componenteId,
        ?string $texto,
        ?int $professorId,
        ?int $expectedVersion = null,
    ): int {
        $this->migracaoLazy->garantirTurma($avaliacaoId, $turmaAvaliativaId);

        return parent::salvarInformacao(
            $avaliacaoId,
            $turmaAvaliativaId,
            $aluno,
            $componenteId,
            $texto,
            $professorId,
            $expectedVersion,
        );
    }

    public function salvarPautasEmMassaParaAlunos(
        int $avaliacaoId,
        Collection $alunos,
        array $respostasPorAluno,
        array $turmaAvaliativaPorAluno,
    ): int {
        collect($turmaAvaliativaPorAluno)
            ->values()
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->each(fn (int $turmaId) => $this->migracaoLazy->garantirTurma($avaliacaoId, $turmaId));

        return parent::salvarPautasEmMassaParaAlunos(
            $avaliacaoId,
            $alunos,
            $respostasPorAluno,
            $turmaAvaliativaPorAluno,
        );
    }

    public function respostasDaAvaliacaoParaAlunos(int $avaliacaoId, array $alunoIds): Collection
    {
        $this->migracaoLazy->garantirParaAlunos($avaliacaoId, $alunoIds);

        return parent::respostasDaAvaliacaoParaAlunos($avaliacaoId, $alunoIds);
    }

    public function informacoesDaAvaliacaoParaAlunos(int $avaliacaoId, array $alunoIds): Collection
    {
        $this->migracaoLazy->garantirParaAlunos($avaliacaoId, $alunoIds);

        return parent::informacoesDaAvaliacaoParaAlunos($avaliacaoId, $alunoIds);
    }
}
