<?php

namespace App\Services\Avaliacoes;

use App\Exceptions\AvaliacaoRespostaConcorrenteException;
use App\Models\Aluno;
use App\Models\AvaliacaoInformacaoOperacional;
use App\Models\AvaliacaoRespostaOperacional;
use App\Models\AvaliacaoTurmaCiclo;
use App\Support\Avaliacoes\AvaliacaoPerformanceContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Store operacional após o corte para o relacional.
 *
 * O JSON legado é utilizado somente pela inicialização lazy. O autosave escreve
 * diretamente na unidade mínima aluno × pauta (ou aluno × componente para texto)
 * e não volta ao documento JSON.
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

        app(AvaliacaoPerformanceContext::class)->add([
            'acao' => 'autosave_resposta',
            'ciclo_id' => (int) $ciclo->id,
            'avaliacao_id' => $avaliacaoId,
            'turma_id' => $turmaAvaliativaId,
            'aluno_id' => (int) $aluno->id,
            'pauta_id' => $pautaId,
        ]);

        $version = DB::transaction(function () use (
            $avaliacaoId,
            $turmaAvaliativaId,
            $aluno,
            $pautaId,
            $dados,
            $expectedVersion,
            $expectedValues,
            $ciclo,
        ): int {
            $existente = $this->bloquearResposta($ciclo, (int) $aluno->id, $pautaId);

            if (! $existente) {
                $alternativaId = array_key_exists('alternativa_id', $dados)
                    ? ((int) ($dados['alternativa_id'] ?? 0) ?: null)
                    : null;

                if ($alternativaId === null) {
                    return 0;
                }

                $tokenId = $this->tokenId($ciclo);
                $agora = now();
                $linha = [
                    'token_escrita_id' => $tokenId,
                    'ciclo_id' => (int) $ciclo->id,
                    'avaliacao_id' => $avaliacaoId,
                    'turma_avaliativa_id' => $turmaAvaliativaId,
                    'turma_origem_id' => (int) $ciclo->turma_origem_id,
                    'aluno_id' => (int) $aluno->id,
                    'pauta_id' => $pautaId,
                    'componente_curricular_id' => $dados['componente_curricular_id'] ?? null,
                    'professor_id' => $dados['professor_id'] ?? null,
                    'alternativa_id' => $alternativaId,
                    'observacao' => array_key_exists('observacao', $dados)
                        ? $this->normalizarTexto($dados['observacao'])
                        : null,
                    'respondido_em' => $dados['respondido_em'] ?? $agora,
                    'version' => 1,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];

                // Garante o primeiro INSERT sem depender de gap lock. Em uma
                // corrida real, somente uma requisição cria a chave única.
                if (DB::table('avaliacao_respostas_operacionais')->insertOrIgnore([$linha]) === 1) {
                    return 1;
                }

                $existente = $this->bloquearResposta($ciclo, (int) $aluno->id, $pautaId);
                if (! $existente) {
                    throw new RuntimeException('A resposta concorrente não pôde ser recuperada após o INSERT.');
                }

                if ($this->mesmosCamposInformados($existente, $dados)) {
                    return (int) $existente->version;
                }

                throw new AvaliacaoRespostaConcorrenteException(
                    'A resposta foi preenchida por outro usuário enquanto você salvava.',
                );
            }

            $this->validarVersaoDaResposta($existente, $expectedVersion, $expectedValues, $dados);

            $alternativaId = array_key_exists('alternativa_id', $dados)
                ? ((int) ($dados['alternativa_id'] ?? 0) ?: null)
                : ($existente->alternativa_id ? (int) $existente->alternativa_id : null);

            if ($alternativaId === null) {
                $existente->delete();

                return 0;
            }

            $existente->forceFill([
                'alternativa_id' => $alternativaId,
                'observacao' => array_key_exists('observacao', $dados)
                    ? $this->normalizarTexto($dados['observacao'])
                    : $existente->observacao,
                'professor_id' => array_key_exists('professor_id', $dados)
                    ? $dados['professor_id']
                    : $existente->professor_id,
                'componente_curricular_id' => array_key_exists('componente_curricular_id', $dados)
                    ? $dados['componente_curricular_id']
                    : $existente->componente_curricular_id,
                'respondido_em' => $dados['respondido_em'] ?? now(),
                'version' => (int) $existente->version + 1,
            ])->save();

            return (int) $existente->version;
        }, 3);

        $this->agendarResumoDashboard($avaliacaoId, $turmaAvaliativaId);

        return $version;
    }

    public function removerPauta(
        int $avaliacaoId,
        int $turmaAvaliativaId,
        Aluno $aluno,
        int $pautaId,
        ?int $expectedVersion = null,
    ): void {
        $ciclo = $this->migracaoLazy->garantirTurma($avaliacaoId, $turmaAvaliativaId);

        DB::transaction(function () use ($ciclo, $aluno, $pautaId, $expectedVersion): void {
            $existente = $this->bloquearResposta($ciclo, (int) $aluno->id, $pautaId);

            if (! $existente) {
                if ($expectedVersion !== null && $expectedVersion > 0) {
                    throw new AvaliacaoRespostaConcorrenteException('A resposta já foi removida por outro usuário.');
                }

                return;
            }

            if ($expectedVersion !== null && (int) $existente->version !== $expectedVersion) {
                throw new AvaliacaoRespostaConcorrenteException('A resposta foi alterada por outro usuário.');
            }

            $existente->delete();
        }, 3);

        $this->agendarResumoDashboard($avaliacaoId, $turmaAvaliativaId);
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
        $texto = $this->normalizarTexto($texto);

        $version = DB::transaction(function () use (
            $avaliacaoId,
            $turmaAvaliativaId,
            $aluno,
            $componenteId,
            $texto,
            $professorId,
            $expectedVersion,
            $ciclo,
        ): int {
            $existente = AvaliacaoInformacaoOperacional::query()
                ->where('ciclo_id', (int) $ciclo->id)
                ->where('aluno_id', (int) $aluno->id)
                ->where('componente_chave', $componenteId)
                ->lockForUpdate()
                ->first();

            if (! $existente) {
                if ($texto === null) {
                    return 0;
                }

                $agora = now();
                $linha = [
                    'token_escrita_id' => $this->tokenId($ciclo),
                    'ciclo_id' => (int) $ciclo->id,
                    'avaliacao_id' => $avaliacaoId,
                    'turma_avaliativa_id' => $turmaAvaliativaId,
                    'turma_origem_id' => (int) $ciclo->turma_origem_id,
                    'aluno_id' => (int) $aluno->id,
                    'componente_curricular_id' => $componenteId > 0 ? $componenteId : null,
                    'componente_chave' => max(0, $componenteId),
                    'professor_id' => $professorId,
                    'texto' => $texto,
                    'version' => 1,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];

                if (DB::table('avaliacao_informacoes_operacionais')->insertOrIgnore([$linha]) === 1) {
                    return 1;
                }

                $existente = AvaliacaoInformacaoOperacional::query()
                    ->where('ciclo_id', (int) $ciclo->id)
                    ->where('aluno_id', (int) $aluno->id)
                    ->where('componente_chave', $componenteId)
                    ->lockForUpdate()
                    ->first();

                if ($existente && $this->normalizarTexto($existente->texto) === $texto) {
                    return (int) $existente->version;
                }

                throw new AvaliacaoRespostaConcorrenteException(
                    'A informação complementar foi preenchida por outro usuário enquanto você salvava.',
                );
            }

            if ($expectedVersion !== null && (int) $existente->version !== $expectedVersion) {
                throw new AvaliacaoRespostaConcorrenteException('A informação complementar foi alterada por outro usuário.');
            }

            if ($texto === null) {
                $existente->delete();

                return 0;
            }

            $existente->forceFill([
                'texto' => $texto,
                'professor_id' => $professorId,
                'version' => (int) $existente->version + 1,
            ])->save();

            return (int) $existente->version;
        }, 3);

        $this->agendarResumoDashboard($avaliacaoId, $turmaAvaliativaId);

        return $version;
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

    public function respostasDaAvaliacaoParaAlunos(int $avaliacaoId, array $alunoIds, ?array $turmaAvaliativaIds = null): Collection
    {
        $this->migracaoLazy->garantirParaAlunos($avaliacaoId, $alunoIds, $turmaAvaliativaIds);

        return parent::respostasDaAvaliacaoParaAlunos($avaliacaoId, $alunoIds, $turmaAvaliativaIds);
    }

    public function informacoesDaAvaliacaoParaAlunos(int $avaliacaoId, array $alunoIds, ?array $turmaAvaliativaIds = null): Collection
    {
        $this->migracaoLazy->garantirParaAlunos($avaliacaoId, $alunoIds, $turmaAvaliativaIds);

        return parent::informacoesDaAvaliacaoParaAlunos($avaliacaoId, $alunoIds, $turmaAvaliativaIds);
    }

    private function bloquearResposta(
        AvaliacaoTurmaCiclo $ciclo,
        int $alunoId,
        int $pautaId,
    ): ?AvaliacaoRespostaOperacional {
        return AvaliacaoRespostaOperacional::query()
            ->where('ciclo_id', (int) $ciclo->id)
            ->where('aluno_id', $alunoId)
            ->where('pauta_id', $pautaId)
            ->lockForUpdate()
            ->first();
    }

    private function tokenId(AvaliacaoTurmaCiclo $ciclo): int
    {
        $tokenId = (int) ($ciclo->getAttribute('token_escrita_bloqueado_id')
            ?: $ciclo->tokenEscrita()->value('id'));

        if ($tokenId <= 0) {
            throw new RuntimeException('A turma não possui token de escrita ativo.');
        }

        return $tokenId;
    }

    /** @param array<string,mixed> $dados */
    private function mesmosCamposInformados(AvaliacaoRespostaOperacional $existente, array $dados): bool
    {
        foreach (['alternativa_id', 'observacao', 'professor_id', 'componente_curricular_id'] as $campo) {
            if (! array_key_exists($campo, $dados)) {
                continue;
            }

            $atual = match ($campo) {
                'observacao' => $this->normalizarTexto($existente->observacao),
                default => ((int) ($existente->{$campo} ?? 0) ?: null),
            };
            $novo = match ($campo) {
                'observacao' => $this->normalizarTexto($dados[$campo] ?? null),
                default => ((int) ($dados[$campo] ?? 0) ?: null),
            };

            if ($atual !== $novo) {
                return false;
            }
        }

        return true;
    }

    /**
     * Mantém o merge otimista por campo do fluxo anterior: uma mudança paralela
     * em observação não invalida necessariamente uma mudança de alternativa e
     * vice-versa, desde que o campo que este request viu ainda tenha o valor
     * esperado.
     *
     * @param array<string,mixed> $expectedValues
     * @param array<string,mixed> $dados
     */
    private function validarVersaoDaResposta(
        AvaliacaoRespostaOperacional $existente,
        ?int $expectedVersion,
        array $expectedValues,
        array $dados,
    ): void {
        if ($expectedVersion === null || (int) $existente->version === $expectedVersion) {
            return;
        }

        foreach (['alternativa_id', 'observacao'] as $campo) {
            if (! array_key_exists($campo, $dados)) {
                continue;
            }

            $atual = $campo === 'alternativa_id'
                ? ((int) ($existente->{$campo} ?? 0) ?: null)
                : $this->normalizarTexto($existente->{$campo});
            $esperado = $campo === 'alternativa_id'
                ? ((int) ($expectedValues[$campo] ?? 0) ?: null)
                : $this->normalizarTexto($expectedValues[$campo] ?? null);

            if (! array_key_exists($campo, $expectedValues) || $atual !== $esperado) {
                throw new AvaliacaoRespostaConcorrenteException('A resposta foi alterada por outro usuário.');
            }
        }
    }

    private function normalizarTexto(mixed $texto): ?string
    {
        $texto = trim((string) ($texto ?? ''));

        return $texto !== '' ? $texto : null;
    }
}
