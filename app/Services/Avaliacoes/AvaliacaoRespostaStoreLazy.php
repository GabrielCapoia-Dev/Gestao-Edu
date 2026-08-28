<?php

namespace App\Services\Avaliacoes;

use App\Models\Aluno;
use App\Models\AvaliacaoInformacaoOperacional;
use App\Models\AvaliacaoRespostaOperacional;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        $ciclo = $this->migracaoLazy->garantirTurma($avaliacaoId, $turmaAvaliativaId);

        return DB::transaction(function () use (
            $avaliacaoId,
            $turmaAvaliativaId,
            $aluno,
            $pautaId,
            $dados,
            $expectedVersion,
            $expectedValues,
            $ciclo,
        ): int {
            // Usa exatamente a mesma chave do índice UNIQUE
            // (ciclo_id, aluno_id, pauta_id). Pautas diferentes continuam
            // independentes, mesmo para o mesmo aluno.
            AvaliacaoRespostaOperacional::query()
                ->where('ciclo_id', (int) $ciclo->id)
                ->where('aluno_id', (int) $aluno->id)
                ->where('pauta_id', $pautaId)
                ->lockForUpdate()
                ->first();

            return parent::salvarPauta(
                $avaliacaoId,
                $turmaAvaliativaId,
                $aluno,
                $pautaId,
                $dados,
                $expectedVersion,
                $expectedValues,
            );
        }, 3);
    }

    public function removerPauta(
        int $avaliacaoId,
        int $turmaAvaliativaId,
        Aluno $aluno,
        int $pautaId,
        ?int $expectedVersion = null,
    ): void {
        $ciclo = $this->migracaoLazy->garantirTurma($avaliacaoId, $turmaAvaliativaId);

        DB::transaction(function () use ($avaliacaoId, $turmaAvaliativaId, $aluno, $pautaId, $expectedVersion, $ciclo): void {
            AvaliacaoRespostaOperacional::query()
                ->where('ciclo_id', (int) $ciclo->id)
                ->where('aluno_id', (int) $aluno->id)
                ->where('pauta_id', $pautaId)
                ->lockForUpdate()
                ->first();

            parent::removerPauta($avaliacaoId, $turmaAvaliativaId, $aluno, $pautaId, $expectedVersion);
        }, 3);
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
        $ciclo = $this->migracaoLazy->garantirTurma($avaliacaoId, $turmaAvaliativaId);

        return DB::transaction(function () use (
            $avaliacaoId,
            $turmaAvaliativaId,
            $aluno,
            $componenteId,
            $texto,
            $professorId,
            $expectedVersion,
            $ciclo,
        ): int {
            AvaliacaoInformacaoOperacional::query()
                ->where('ciclo_id', (int) $ciclo->id)
                ->where('aluno_id', (int) $aluno->id)
                ->where('componente_chave', $componenteId)
                ->lockForUpdate()
                ->first();

            return parent::salvarInformacao(
                $avaliacaoId,
                $turmaAvaliativaId,
                $aluno,
                $componenteId,
                $texto,
                $professorId,
                $expectedVersion,
            );
        }, 3);
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
