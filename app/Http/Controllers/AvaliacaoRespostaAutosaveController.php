<?php

namespace App\Http\Controllers;

use App\Exceptions\AvaliacaoCicloFechadoException;
use App\Exceptions\AvaliacaoRespostaConcorrenteException;
use App\Models\Aluno;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\Pauta;
use App\Models\Turma;
use App\Services\Avaliacoes\AvaliacaoRespostaStore;
use App\Services\Avaliacoes\AvaliacaoTurmaCicloService;
use App\Services\Avaliacoes\TurmaAvaliacaoAlunoScopeService;
use App\Services\PessoaScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AvaliacaoRespostaAutosaveController extends Controller
{
    public function __invoke(
        Request $request,
        AvaliacaoRespostaStore $store,
        TurmaAvaliacaoAlunoScopeService $escopos,
    ): JsonResponse {
        $dados = $request->validate([
            'avaliacao_id' => ['required', 'integer', 'exists:avaliacoes,id'],
            'turma_id' => ['required', 'integer', 'exists:turmas,id'],
            'aluno_id' => ['required', 'integer', 'exists:alunos,id'],
            'tipo' => ['required', Rule::in(['resposta', 'informacao'])],
            'pauta_id' => ['nullable', 'integer', 'exists:pautas,id'],
            'componente_id' => ['nullable', 'integer', 'min:0'],
            'campo' => ['required', Rule::in(['alternativa_id', 'observacao'])],
            'valor' => ['nullable'],
            'alternativa_id' => ['nullable', 'integer', 'min:0'],
            'observacao' => ['nullable', 'string', 'max:1500'],
            'expected_version' => ['nullable', 'integer', 'min:0'],
            'expected_values' => ['nullable', 'array'],
        ]);

        $user = $request->user();
        Gate::forUser($user)->authorize('respond', Avaliacao::class);

        $avaliacao = Avaliacao::query()->findOrFail((int) $dados['avaliacao_id']);
        abort_unless($avaliacao->estaAbertaParaPreenchimento(), 403);

        $turma = Turma::query()->findOrFail((int) $dados['turma_id']);
        $this->validarTurmaNoEscopo($user, $avaliacao, $turma);

        $escopo = $escopos->escoposPorTurma(collect([$turma]))[(int) $turma->id] ?? null;
        abort_unless(is_array($escopo), 403);

        $cicloAtual = app(AvaliacaoTurmaCicloService::class)
            ->obter((int) $avaliacao->id, (int) $turma->id);
        abort_unless($cicloAtual === null || $cicloAtual->aceitaEscrita(), 403);

        $aluno = Aluno::query()
            ->whereKey((int) $dados['aluno_id'])
            ->where('id_turma', (int) $escopo['turma_origem_id'])
            ->whereIn('status', [Aluno::STATUS_MATRICULADO, Aluno::STATUS_PENDENTE])
            ->firstOrFail();
        abort_unless(! $this->alunoBloqueado($aluno), 403);

        $pauta = null;
        $componenteId = null;
        $professorId = null;

        if ($dados['tipo'] === 'resposta') {
            $pauta = Pauta::query()
                ->whereKey((int) ($dados['pauta_id'] ?? 0))
                ->where('status', true)
                ->whereHas('avaliacoes', fn ($query) => $query->whereKey($avaliacao->id))
                ->firstOrFail();

            abort_unless($pauta->serie_id === null || (int) $pauta->serie_id === (int) $turma->id_serie, 403);
            $componenteId = $pauta->componente_curricular_id !== null
                ? (int) $pauta->componente_curricular_id
                : null;
            $professorId = $this->validarVinculoProfessor($user, $turma, $componenteId);
        } else {
            $componenteId = max(0, (int) ($dados['componente_id'] ?? 0));
            $professorId = $this->validarVinculoProfessor($user, $turma, $componenteId > 0 ? $componenteId : null);
        }

        try {
            if ($dados['tipo'] === 'informacao') {
                abort_unless($dados['campo'] === 'observacao', 422);

                $texto = $this->normalizarTexto($dados['valor'] ?? null);
                $version = $store->salvarInformacao(
                    (int) $avaliacao->id,
                    (int) $turma->id,
                    $aluno,
                    $componenteId,
                    $texto,
                    $professorId,
                    $dados['expected_version'] ?? null,
                );

                return response()->json(['saved' => true, 'version' => $version]);
            }

            $alternativaId = $this->normalizarId($dados['campo'] === 'alternativa_id'
                ? ($dados['valor'] ?? null)
                : ($dados['alternativa_id'] ?? null));

            $alternativa = $alternativaId !== null
                ? $this->alternativaPermitida($avaliacao, $pauta, $alternativaId)
                : null;
            abort_unless($alternativaId === null || $alternativa !== null, 422);

            $observacao = $this->normalizarTexto($dados['observacao'] ?? null);

            if ($alternativaId === null || (($alternativa?->tem_observacao ?? false) && $observacao === null)) {
                $store->removerPauta(
                    (int) $avaliacao->id,
                    (int) $turma->id,
                    $aluno,
                    (int) $pauta->id,
                    $dados['expected_version'] ?? null,
                );

                return response()->json(['saved' => true, 'version' => 0]);
            }

            $version = $store->salvarPauta(
                (int) $avaliacao->id,
                (int) $turma->id,
                $aluno,
                (int) $pauta->id,
                [
                    'alternativa_id' => $alternativaId,
                    'observacao' => $alternativa->tem_observacao ? $observacao : null,
                    'professor_id' => $professorId,
                    'componente_curricular_id' => $componenteId,
                    'respondido_em' => now(),
                ],
                $dados['expected_version'] ?? null,
                (array) ($dados['expected_values'] ?? []),
            );

            return response()->json(['saved' => true, 'version' => $version]);
        } catch (AvaliacaoRespostaConcorrenteException|AvaliacaoCicloFechadoException $exception) {
            return response()->json([
                'saved' => false,
                'message' => $exception->getMessage(),
            ], 409);
        }
    }

    private function validarTurmaNoEscopo($user, Avaliacao $avaliacao, Turma $turma): void
    {
        abort_unless(DB::table('avaliacao_turma')
            ->where('avaliacao_id', (int) $avaliacao->id)
            ->where('turma_id', (int) $turma->id)
            ->exists(), 403);

        $scope = app(PessoaScopeService::class);
        if (! $scope->hasGlobalAccess($user)) {
            abort_unless(in_array((int) $turma->id_escola, $scope->escolaIdsDosVinculos($user), true), 403);
        }
    }

    private function validarVinculoProfessor($user, Turma $turma, ?int $componenteId): ?int
    {
        $professorIds = $user->professores()
            ->where('ativo', true)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        $query = DB::table('turma_componente_professor')
            ->where('turma_id', (int) $turma->id)
            ->where('tem_professor', true);

        if ($professorIds->isNotEmpty()) {
            $query->whereIn('professor_id', $professorIds->all());
        }

        if ($componenteId !== null) {
            $query->where('componente_curricular_id', $componenteId);
        }

        $vinculo = $query->orderBy('professor_id')->first(['professor_id']);

        abort_unless($vinculo || $professorIds->isEmpty(), 403);

        return $vinculo ? (int) $vinculo->professor_id : null;
    }

    private function alternativaPermitida(Avaliacao $avaliacao, Pauta $pauta, int $alternativaId): ?Alternativa
    {
        $alternativa = Alternativa::query()
            ->whereKey($alternativaId)
            ->where('status', true)
            ->first();

        if (! $alternativa) {
            return null;
        }

        $overrides = DB::table('avaliacao_pauta_alternativa')
            ->where('avaliacao_id', (int) $avaliacao->id)
            ->where('pauta_id', (int) $pauta->id)
            ->pluck('alternativa_id')
            ->map(fn ($id): int => (int) $id);

        if ($overrides->isNotEmpty()) {
            return $overrides->contains($alternativaId) ? $alternativa : null;
        }

        $alternativasDaPauta = DB::table('alternativa_pauta')
            ->where('pauta_id', (int) $pauta->id)
            ->pluck('alternativa_id')
            ->map(fn ($id): int => (int) $id);

        if ($alternativasDaPauta->isNotEmpty()) {
            return $alternativasDaPauta->contains($alternativaId) ? $alternativa : null;
        }

        if ((int) $alternativa->tipo_avaliacao_id === (int) $pauta->tipo_avaliacao_id) {
            return $alternativa;
        }

        return (int) $alternativa->tipo_avaliacao_id === (int) $avaliacao->tipo_avaliacao_id
            ? $alternativa
            : null;
    }

    private function alunoBloqueado(Aluno $aluno): bool
    {
        return $aluno->status === Aluno::STATUS_PENDENTE
            && (int) $aluno->pendencia_origem_aluno_id > 0;
    }

    private function normalizarId(mixed $valor): ?int
    {
        $id = (int) $valor;

        return $id > 0 ? $id : null;
    }

    private function normalizarTexto(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto !== '' ? mb_substr($texto, 0, 1500) : null;
    }
}
