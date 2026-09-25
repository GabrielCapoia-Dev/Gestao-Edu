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
use App\Services\Avaliacoes\AvaliacaoAutosaveContextService;
use App\Services\Avaliacoes\AvaliacaoMigracaoLazyService;
use App\Services\Avaliacoes\AvaliacaoTurmaCicloService;
use App\Services\Avaliacoes\TurmaAvaliacaoAlunoScopeService;
use App\Services\PessoaScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AvaliacaoRespostaAutosaveController extends Controller
{
    public function __invoke(
        Request $request,
        AvaliacaoRespostaStore $store,
        AvaliacaoAutosaveContextService $contextos,
        AvaliacaoMigracaoLazyService $migracaoLazy,
        TurmaAvaliacaoAlunoScopeService $escopos,
    ): JsonResponse {
        $dados = $request->validate([
            'avaliacao_id' => ['required', 'integer'],
            'turma_id' => ['required', 'integer'],
            'aluno_id' => ['required', 'integer'],
            'tipo' => ['required', Rule::in(['resposta', 'informacao'])],
            'pauta_id' => ['required_if:tipo,resposta', 'nullable', 'integer'],
            'componente_id' => ['nullable', 'integer', 'min:0'],
            'campo' => ['required', Rule::in(['alternativa_id', 'observacao'])],
            'valor' => ['nullable'],
            'alternativa_id' => ['nullable', 'integer', 'min:0'],
            'observacao' => ['nullable', 'string', 'max:1500'],
            'expected_version' => ['nullable', 'integer', 'min:0'],
            'expected_values' => ['nullable', 'array'],
        ]);

        $alternativaIdSolicitado = $this->normalizarId($dados['campo'] === 'alternativa_id'
            ? ($dados['valor'] ?? null)
            : ($dados['alternativa_id'] ?? null));
        $dados['alternativa_contexto_id'] = $alternativaIdSolicitado;

        $user = $request->user();
        // EnsurePasswordIsChanged ja validou canAuthenticate nesta mesma
        // requisicao. O contexto ainda confere a permissao e o vinculo exatos.
        Gate::forUser($user)->authorize('respond', Avaliacao::class);
        $contexto = $contextos->resolver($dados, $user, acessoOperacionalValidado: true);
        $avaliacao = $contexto['avaliacao'] ?? Avaliacao::query()->find((int) $dados['avaliacao_id']);
        $this->validarModeloExistente($avaliacao, 'avaliacao_id');
        abort_unless($avaliacao->estaAbertaParaPreenchimento(), 403);

        $turma = $contexto['turma'] ?? Turma::query()->find((int) $dados['turma_id']);
        $this->validarModeloExistente($turma, 'turma_id');
        if (! $contexto) {
            $this->validarTurmaNaAvaliacao($avaliacao, $turma);
        }

        $cicloAtual = $contexto['ciclo'] ?? app(AvaliacaoTurmaCicloService::class)
            ->obterComToken((int) $avaliacao->id, (int) $turma->id);
        if ($cicloAtual) {
            $migracaoLazy->adotarCicloPronto($cicloAtual);
        }
        $escopo = $cicloAtual
            ? ['turma_origem_id' => (int) $cicloAtual->turma_origem_id]
            : ($escopos->escoposPorTurma(collect([$turma]))[(int) $turma->id] ?? null);
        abort_unless(is_array($escopo), 403);

        abort_unless($cicloAtual === null || $cicloAtual->aceitaEscrita(), 403);

        $aluno = $contexto['aluno'] ?? null;
        if (! $aluno) {
            $aluno = Aluno::query()
                ->whereKey((int) $dados['aluno_id'])
                ->where('id_turma', (int) $escopo['turma_origem_id'])
                ->whereIn('status', [Aluno::STATUS_MATRICULADO, Aluno::STATUS_PENDENTE])
                ->first();
            if (! $aluno) {
                $this->validarModeloExistente(
                    Aluno::query()->whereKey((int) $dados['aluno_id'])->first(['id']),
                    'aluno_id',
                );
                abort(404);
            }
        }
        abort_unless(! $this->alunoBloqueado($aluno), 403);

        $pauta = null;
        $componenteId = null;
        $professorId = null;

        if ($dados['tipo'] === 'resposta') {
            $pauta = $contexto['pauta'] ?? Pauta::query()
                ->whereKey((int) ($dados['pauta_id'] ?? 0))
                ->where('status', true)
                ->whereHas('avaliacoes', fn ($query) => $query->whereKey($avaliacao->id))
                ->first();
            if (! $pauta) {
                $this->validarModeloExistente(
                    Pauta::query()->whereKey((int) ($dados['pauta_id'] ?? 0))->first(['id']),
                    'pauta_id',
                );
                abort(404);
            }

            abort_unless($pauta->serie_id === null || (int) $pauta->serie_id === (int) $turma->id_serie, 403);
            $componenteId = $pauta->componente_curricular_id !== null
                ? (int) $pauta->componente_curricular_id
                : null;
            $professorId = $contexto['professor_id']
                ?? $this->validarVinculoProfessor($user, $turma, $componenteId);
        } else {
            $componenteId = max(0, (int) ($dados['componente_id'] ?? 0));
            $professorId = $contexto['professor_id']
                ?? $this->validarVinculoProfessor($user, $turma, $componenteId > 0 ? $componenteId : null);
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

            $alternativaId = $alternativaIdSolicitado;

            $alternativa = $contexto
                ? $contexto['alternativa']
                : ($alternativaId !== null
                    ? $this->alternativaPermitida($avaliacao, $pauta, $alternativaId)
                    : null);
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

    private function validarModeloExistente(mixed $modelo, string $campo): void
    {
        if ($modelo !== null) {
            return;
        }

        throw ValidationException::withMessages([
            $campo => trans('validation.exists', ['attribute' => str_replace('_id', '', $campo)]),
        ]);
    }

    private function validarTurmaNaAvaliacao(Avaliacao $avaliacao, Turma $turma): void
    {
        abort_unless(DB::table('avaliacao_turma')
            ->where('avaliacao_id', (int) $avaliacao->id)
            ->where('turma_id', (int) $turma->id)
            ->exists(), 403);
    }

    private function validarVinculoProfessor($user, Turma $turma, ?int $componenteId): ?int
    {
        $identidadeProfessor = fn ($query) => $query
            ->where('prof.user_id', (int) $user->getKey())
            ->orWhere(function ($servidor) use ($user): void {
                $servidor
                    ->where('serv.user_id', (int) $user->getKey())
                    ->where('serv.status', 'ativo')
                    ->whereNull('serv.deleted_at');
            });

        $query = DB::table('turma_componente_professor as tcp')
            ->join('professores as prof', 'prof.id', '=', 'tcp.professor_id')
            ->leftJoin('servidores as serv', 'serv.id', '=', 'prof.servidor_id')
            ->where('tcp.turma_id', (int) $turma->id)
            ->where('tcp.tem_professor', true)
            ->where('prof.ativo', true)
            ->where('prof.id_escola', (int) $turma->id_escola)
            ->where($identidadeProfessor);

        if ($componenteId !== null) {
            $query->where('tcp.componente_curricular_id', $componenteId);
        }

        $vinculo = $query->orderBy('tcp.professor_id')->first(['tcp.professor_id']);
        if ($vinculo) {
            return (int) $vinculo->professor_id;
        }

        $possuiProfessorAtivo = DB::table('professores as prof')
            ->leftJoin('servidores as serv', 'serv.id', '=', 'prof.servidor_id')
            ->where('prof.ativo', true)
            ->where($identidadeProfessor)
            ->exists();
        abort_if($possuiProfessorAtivo, 403);

        $scope = app(PessoaScopeService::class);
        if (! $scope->hasGlobalAccess($user)) {
            abort_unless(in_array((int) $turma->id_escola, $scope->escolaIdsDosVinculos($user), true), 403);
        }

        $fallback = DB::table('turma_componente_professor')
            ->where('turma_id', (int) $turma->id)
            ->where('tem_professor', true)
            ->when($componenteId !== null, fn ($fallback) => $fallback
                ->where('componente_curricular_id', $componenteId))
            ->orderBy('professor_id')
            ->first(['professor_id']);

        abort_unless($fallback, 403);

        return (int) $fallback->professor_id;
    }

    private function alternativaPermitida(Avaliacao $avaliacao, Pauta $pauta, int $alternativaId): ?Alternativa
    {
        $alternativa = Alternativa::query()
            ->whereKey($alternativaId)
            ->where('status', true)
            ->select('alternativas.*')
            ->selectRaw(
                'EXISTS (SELECT 1 FROM avaliacao_pauta_alternativa apa WHERE apa.avaliacao_id = ? AND apa.pauta_id = ?) AS possui_overrides',
                [(int) $avaliacao->id, (int) $pauta->id],
            )
            ->selectRaw(
                'EXISTS (SELECT 1 FROM avaliacao_pauta_alternativa apa WHERE apa.avaliacao_id = ? AND apa.pauta_id = ? AND apa.alternativa_id = alternativas.id) AS override_permitido',
                [(int) $avaliacao->id, (int) $pauta->id],
            )
            ->selectRaw(
                'EXISTS (SELECT 1 FROM alternativa_pauta ap WHERE ap.pauta_id = ?) AS possui_alternativas_pauta',
                [(int) $pauta->id],
            )
            ->selectRaw(
                'EXISTS (SELECT 1 FROM alternativa_pauta ap WHERE ap.pauta_id = ? AND ap.alternativa_id = alternativas.id) AS alternativa_pauta_permitida',
                [(int) $pauta->id],
            )
            ->first();

        if (! $alternativa) {
            return null;
        }

        if ((bool) $alternativa->possui_overrides) {
            return (bool) $alternativa->override_permitido ? $alternativa : null;
        }

        if ((bool) $alternativa->possui_alternativas_pauta) {
            return (bool) $alternativa->alternativa_pauta_permitida ? $alternativa : null;
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
