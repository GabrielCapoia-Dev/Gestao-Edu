<?php

namespace App\Services\Avaliacoes;

use App\Exceptions\ResponsaveisParecerInvalidosException;
use App\Models\Aluno;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\AvaliacaoAlunoSnapshot;
use App\Models\AvaliacaoExportacao;
use App\Models\AvaliacaoAlunoDocumento;
use App\Models\AvaliacaoTurmaCiclo;
use App\Models\Pauta;
use App\Models\Professor;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use App\Services\PessoaScopeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AvaliacaoDocumentoExportService
{
    private const VIEW = 'relatorios.Avaliacoes.documento';

    /** @var array<int, string> */
    private array $nomesProfessores = [];

    /** @var array<int, string> */
    private array $nomesAlternativas = [];

    /** @var array<string, string> */
    private array $nomesProfessoresPorTurmaComponente = [];

    /**
     * @param  array<string, mixed>  $params
     */
    public function exportar(array $params, ?User $usuario): Response
    {
        $this->nomesProfessores = [];
        $this->nomesAlternativas = [];
        $this->nomesProfessoresPorTurmaComponente = [];
        $avaliacao = $this->buscarAvaliacao((int) ($params['avaliacao_id'] ?? 0));
        $escopo = (string) ($params['escopo'] ?? 'turma');
        $turmas = $this->resolverTurmas($avaliacao, $escopo, $params, $usuario);
        $this->capturarSnapshotsParecer($avaliacao, $turmas, $escopo, $params);
        $documentos = $this->montarDocumentos($avaliacao, $turmas, $escopo, $params, $usuario);

        if ($documentos->isEmpty()) {
            throw new NotFoundHttpException('Nenhum aluno encontrado para exportação.');
        }

        $documentosComPaginas = $this->prepararDocumentosParaPdf($documentos, $escopo);

        $pdf = $this->criarPdf($documentosComPaginas);
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        $conteudo = $pdf->output();
        $quantidadePaginas = $dompdf->getCanvas()->get_page_count();

        $this->registrarLog(
            $avaliacao,
            $turmas,
            $escopo,
            $params,
            $usuario,
            'pdf',
            $documentosComPaginas->count(),
            $quantidadePaginas
        );

        return response($conteudo, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', addslashes($this->nomeArquivo($avaliacao, $escopo, $turmas, $documentosComPaginas))),
        ]);
    }

    /**
     * Captura os responsáveis antes de uma solicitação assíncrona ser criada.
     *
     * @param  array<string, mixed>  $params
     */
    public function prepararSnapshotsParecer(array $params, ?User $usuario): void
    {
        $avaliacao = $this->buscarAvaliacao((int) ($params['avaliacao_id'] ?? 0));
        $escopo = (string) ($params['escopo'] ?? 'turma');
        $turmas = $this->resolverTurmas($avaliacao, $escopo, $params, $usuario);

        $this->capturarSnapshotsParecer($avaliacao, $turmas, $escopo, $params);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function exportarCsv(array $params, ?User $usuario): StreamedResponse
    {
        $this->nomesProfessores = [];
        $this->nomesAlternativas = [];
        $this->nomesProfessoresPorTurmaComponente = [];
        $avaliacao = $this->buscarAvaliacao((int) ($params['avaliacao_id'] ?? 0));
        $escopo = (string) ($params['escopo'] ?? 'turma');
        $turmas = $this->resolverTurmas($avaliacao, $escopo, $params, $usuario);
        $dados = $this->montarDadosCsv($avaliacao, $turmas, $escopo, $params);

        if ($dados->isEmpty()) {
            throw new NotFoundHttpException('Nenhum dado encontrado para exportação.');
        }

        $quantidadeAlunos = $dados
            ->flatMap(fn (array $turmaDados): Collection => $turmaDados['alunos'])
            ->count();

        $this->registrarLog(
            $avaliacao,
            $turmas,
            $escopo,
            $params,
            $usuario,
            'csv',
            $quantidadeAlunos,
            null
        );

        return response()->streamDownload(function () use ($avaliacao, $dados): void {
            echo "\xEF\xBB\xBF";

            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            $delimiter = ';';

            fputcsv($out, [
                'Avaliação ID',
                'Avaliação Nome',
                'Turma ID',
                'Turma Nome',
                'Aluno ID',
                'Aluno Nome',
                'Aluno CGM',
                'Aluno Vínculo',
                'Aluno Status',
                'Pauta ID',
                'Pauta Texto',
                'Componente',
                'Alternativa ID',
                'Alternativa',
                'Observação',
                'Respondido Em',
                'Professor ID',
                'Informações Complementares (Componente)',
            ], $delimiter);

            foreach ($dados as $turmaDados) {
                /** @var Turma $turma */
                $turma = $turmaDados['turma'];
                /** @var Collection<int, Aluno> $alunos */
                $alunos = $turmaDados['alunos'];
                /** @var Collection<int, Pauta> $pautas */
                $pautas = $turmaDados['pautas'];
                /** @var Collection<string, object> $respostas */
                $respostas = $turmaDados['respostas'];
                /** @var Collection<string, object> $informacoesComplementares */
                $informacoesComplementares = $turmaDados['informacoes_complementares'];

                foreach ($alunos as $aluno) {
                    foreach ($pautas as $pauta) {
                        $resposta = $respostas->get($pauta->id.'-'.$aluno->id);
                        $info = $informacoesComplementares->get(((int) ($pauta->componente_curricular_id ?? 0)).'-'.((int) $aluno->id));

                        fputcsv($out, [
                            (int) $avaliacao->id,
                            (string) $avaliacao->nome,
                            (int) $turma->id,
                            $this->nomeTurma($turma),
                            (int) $aluno->id,
                            (string) $aluno->nome,
                            (string) $aluno->cgm,
                            $aluno->tipoVinculoLabel(),
                            $aluno->statusLabel(),
                            (int) $pauta->id,
                            (string) $pauta->texto,
                            (string) ($pauta->componente?->nome ?? ''),
                            $resposta?->alternativa_id ? (int) $resposta->alternativa_id : '',
                            (string) ($resposta?->alternativa?->nome ?? ''),
                            (string) ($resposta?->observacao ?? ''),
                            $resposta?->respondido_em?->toDateTimeString() ?? '',
                            $resposta?->professor_id ? (int) $resposta->professor_id : '',
                            (string) ($info?->informacoes_complementares ?? ''),
                        ], $delimiter);
                    }
                }
            }

            fclose($out);
        }, $this->nomeArquivoCsv($avaliacao, $escopo, $turmas), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{filename: string, contents: string}
     */
    public function gerarPdfAluno(int $avaliacaoId, Aluno $aluno, ?User $usuario, ?string $prefixoArquivo = null): array
    {
        $avaliacao = $this->buscarAvaliacao($avaliacaoId);
        $aluno->loadMissing('turma.escola', 'turma.serie');

        if (! $aluno->podeExportarDados()) {
            throw new NotFoundHttpException('Alunos com transferência pendente não podem ser exportados.');
        }

        /** @var Turma|null $turma */
        $turma = $aluno->turma;

        if (! $turma) {
            throw new NotFoundHttpException('Turma do aluno não encontrada.');
        }

        if (! $turma->avaliacoes()->whereKey((int) $avaliacao->id)->exists()) {
            throw new NotFoundHttpException('A avaliação não pertence a turma do aluno.');
        }

        $pautas = $this->pautasDaTurma($avaliacao, $turma);

        if ($pautas->isEmpty()) {
            throw new NotFoundHttpException('Nenhuma pauta encontrada para a avaliação do aluno.');
        }

        if (app(AvaliacaoPersistencia::class)->leRelacional()) {
            $documentoPersistido = app(AvaliacaoDocumentoReader::class)
                ->ler((int) $avaliacao->id, $aluno, $turma);
            $gestores = app(ParecerResponsaveisResolver::class)
                ->dadosParaDocumento($documentoPersistido->responsaveisSnapshot);
        } else {
            $documentoPersistido = app(AvaliacaoParecerSnapshotService::class)
                ->capturarParaAluno($avaliacao, $turma, $aluno);
            $gestores = app(ParecerResponsaveisResolver::class)
                ->dadosParaDocumento($documentoPersistido->responsaveis_snapshot ?? []);
        }

        $documento = $this->montarDocumentoAluno(
            $avaliacao,
            $turma,
            $aluno,
            $pautas,
            $this->montarLegenda($this->alternativasPorPauta($avaliacao, $pautas)),
            $gestores,
            $this->logoDataUri(),
            $prefixoArquivo === 'parecer-transferência' ? 'Parecer de Transferência' : null
        );

        $documentosComPaginas = $this->prepararDocumentosParaPdf(collect([$documento]), 'aluno');

        $pdf = $this->criarPdf($documentosComPaginas);
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        $conteudo = $pdf->output();

        $this->registrarLog(
            $avaliacao,
            collect([$turma]),
            'aluno',
            [
                'aluno_id' => (int) $aluno->id,
                'turma_id' => (int) $turma->id,
            ],
            $usuario,
            'pdf',
            1,
            $dompdf->getCanvas()->get_page_count()
        );

        $partes = array_filter([
            $prefixoArquivo,
            'avaliacao',
            (string) $avaliacao->id,
            $aluno->nome,
        ]);

        return [
            'filename' => Str::slug(implode('-', $partes)).'.pdf',
            'contents' => $conteudo,
        ];
    }

    private function buscarAvaliacao(int $avaliacaoId): Avaliacao
    {
        /** @var Avaliacao|null $avaliacao */
        $avaliacao = Avaliacao::query()
            ->with([
                'tipo:id,nome',
                'periodo:id,nome',
                'pautas' => fn ($query) => $query
                    ->where('status', true)
                    ->with(['componente:id,nome', 'tipo:id,nome', 'alternativas']),
            ])
            ->find($avaliacaoId);

        if (! $avaliacao) {
            throw new NotFoundHttpException('Avaliação não encontrada.');
        }

        return $avaliacao;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return Collection<int, Turma>
     */
    private function resolverTurmas(Avaliacao $avaliacao, string $escopo, array $params, ?User $usuario): Collection
    {
        $query = Turma::query()
            ->whereHas('avaliacoes', fn (Builder $query): Builder => $query->whereKey((int) $avaliacao->id))
            ->with(['escola:id,nome', 'serie:id,nome']);

        $this->aplicarEscopoUsuario($query, $usuario);

        if ($escopo === 'aluno') {
            $aluno = Aluno::query()->find((int) ($params['aluno_id'] ?? 0));

            if (! $aluno) {
                throw new NotFoundHttpException('Aluno não encontrado.');
            }

            if (! $aluno->podeExportarDados()) {
                throw new NotFoundHttpException('Alunos com transferência pendente não podem ser exportados.');
            }

            $query->whereKey((int) $aluno->id_turma);
        } elseif ($escopo === 'turma') {
            $query->whereKey((int) ($params['turma_id'] ?? 0));
        } elseif ($escopo === 'escola') {
            $query->where('id_escola', (int) ($params['escola_id'] ?? 0));
        } else {
            throw new NotFoundHttpException('Escopo de exportação inválido.');
        }

        $turmas = $query
            ->orderBy('id_escola')
            ->orderBy('id_serie')
            ->orderBy('nome')
            ->get();

        if ($turmas->isEmpty()) {
            throw new NotFoundHttpException('Nenhuma turma encontrada para exportação.');
        }

        return $turmas;
    }

    private function aplicarEscopoUsuario(Builder $query, ?User $usuario): void
    {
        $scope = app(PessoaScopeService::class);

        if ($scope->hasGlobalAccess($usuario)) {
            return;
        }

        if (! $usuario) {
            $query->whereRaw('1 = 0');

            return;
        }

        $escolasIds = $scope->escolaIdsDosVinculos($usuario);

        if ($escolasIds !== []) {
            $query->whereIn('id_escola', $escolasIds);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    /**
     * @param  Collection<int, Turma>  $turmas
     * @param  array<string, mixed>  $params
     */
    private function capturarSnapshotsParecer(
        Avaliacao $avaliacao,
        Collection $turmas,
        string $escopo,
        array $params,
    ): void {
        if (app(AvaliacaoPersistencia::class)->leRelacional()) {
            return;
        }

        DB::transaction(function () use ($avaliacao, $turmas, $escopo, $params): void {
            foreach ($turmas as $turma) {
                $alunos = $this->alunosDaTurma($turma, $escopo, $params, $avaliacao);

                if ($alunos->isEmpty() || $this->pautasDaTurma($avaliacao, $turma)->isEmpty()) {
                    continue;
                }

                app(AvaliacaoParecerSnapshotService::class)
                    ->capturarParaAlunos($avaliacao, $turma, $alunos);
            }
        });
    }

    /**
     * @param  Collection<int, Turma>  $turmas
     * @param  array<string, mixed>  $params
     * @return Collection<int, array<string, mixed>>
     */
    private function montarDocumentos(Avaliacao $avaliacao, Collection $turmas, string $escopo, array $params, ?User $usuario): Collection
    {
        $documentos = collect();
        $logoDataUri = $this->logoDataUri();
        $reader = app(AvaliacaoDocumentoBatchReader::class);
        $this->precarregarProfessoresPorTurmaComponente($turmas);

        foreach ($turmas as $turma) {
            $alunos = $this->alunosDaTurma($turma, $escopo, $params, $avaliacao);

            if ($alunos->isEmpty()) {
                continue;
            }

            $pautas = $this->pautasDaTurma($avaliacao, $turma);

            if ($pautas->isEmpty()) {
                continue;
            }

            $alternativasPorPauta = $this->alternativasPorPauta($avaliacao, $pautas);
            $legenda = $this->montarLegenda($alternativasPorPauta);
            $documentosPorAluno = $reader->lerParaAlunos((int) $avaliacao->id, $alunos, $turma);
            $this->precarregarNomesProfessores($documentosPorAluno);
            $this->precarregarNomesAlternativas($documentosPorAluno);
            foreach ($alunos as $aluno) {
                $documentos->push($this->montarDocumentoAluno(
                    $avaliacao,
                    $turma,
                    $aluno,
                    $pautas,
                    $legenda,
                    ['diretor' => '', 'coordenacao' => ''],
                    $logoDataUri,
                    null,
                    $documentosPorAluno->get((int) $aluno->id),
                ));
            }
        }

        return $documentos->values();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return Collection<int, Aluno>
     */
    private function alunosDaTurma(
        Turma $turma,
        string $escopo,
        array $params,
        ?Avaliacao $avaliacao = null,
    ): Collection
    {
        $alunosDoSnapshot = $this->alunosDoSnapshotCongelado(
            $turma,
            $escopo,
            $params,
            $avaliacao,
        );

        if ($alunosDoSnapshot !== null) {
            return $alunosDoSnapshot;
        }

        $escopos = app(TurmaAvaliacaoAlunoScopeService::class)
            ->escoposPorTurma(collect([$turma]));
        $escopoTurma = $escopos[(int) $turma->id] ?? [
            'turma_origem_id' => (int) $turma->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
        ];

        $query = Aluno::query()
            ->where('id_turma', $escopoTurma['turma_origem_id'])
            ->orderBy('nome');

        if ($escopoTurma['tipo_vinculo'] === Aluno::TIPO_VINCULO_CONTRA_TURNO) {
            $query->where('status', Aluno::STATUS_MATRICULADO);
        } else {
            $query->where('status', '!=', Aluno::STATUS_PENDENTE);
        }

        if ($escopo === 'aluno') {
            $query->whereKey((int) ($params['aluno_id'] ?? 0));
        }

        return $query->get(['id', 'nome', 'cgm', 'id_turma', 'status', 'tipo_vinculo', 'data_matricula', 'status_alterado_em']);
    }

    /**
     * Em uma avaliação concluída, o roster congelado é a fonte histórica do
     * parecer. A turma atual pode ter alunos transferidos/remanejados ou
     * vínculos de contra turno diferentes do vínculo de compatibilidade
     * retornado pelo escopo.
     *
     * @param  array<string, mixed>  $params
     * @return Collection<int, Aluno>|null
     */
    private function alunosDoSnapshotCongelado(
        Turma $turma,
        string $escopo,
        array $params,
        ?Avaliacao $avaliacao,
    ): ?Collection {
        if ($avaliacao === null || ! app(AvaliacaoPersistencia::class)->leRelacional()) {
            return null;
        }

        $ciclo = AvaliacaoTurmaCiclo::query()
            ->where('avaliacao_id', (int) $avaliacao->id)
            ->where('turma_avaliativa_id', (int) $turma->id)
            ->first(['status', 'snapshot_evento_atual_id']);

        if ($ciclo?->status !== AvaliacaoTurmaCiclo::STATUS_CONCLUIDA
            || ! $ciclo->snapshot_evento_atual_id) {
            return null;
        }

        $alunoIds = AvaliacaoAlunoSnapshot::query()
            ->where('evento_id', (string) $ciclo->snapshot_evento_atual_id)
            ->where('tipo', 'conclusao')
            ->orderBy('aluno_id')
            ->pluck('aluno_id')
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($alunoIds->isEmpty()) {
            return collect();
        }

        $query = Aluno::query()
            ->whereIn('id', $alunoIds->all())
            ->orderBy('nome');

        if ($escopo === 'aluno') {
            $query->whereKey((int) ($params['aluno_id'] ?? 0));
        }

        return $query->get([
            'id',
            'nome',
            'cgm',
            'id_turma',
            'status',
            'tipo_vinculo',
            'data_matricula',
            'status_alterado_em',
        ]);
    }

    /**
     * @return Collection<int, Pauta>
     */
    private function pautasDaTurma(Avaliacao $avaliacao, Turma $turma): Collection
    {
        return $avaliacao->pautas
            ->filter(fn (Pauta $pauta): bool => (is_null($pauta->serie_id) || (int) $pauta->serie_id === (int) $turma->id_serie))
            ->sortBy(fn (Pauta $pauta): string => mb_strtolower(implode('|', [
                (string) ($pauta->componente?->nome ?? ''),
                (string) $pauta->texto,
            ])))
            ->values();
    }

    /**
     * @param  Collection<int, Pauta>  $pautas
     * @return array<int, Collection<int, Alternativa>>
     */
    private function alternativasPorPauta(Avaliacao $avaliacao, Collection $pautas): array
    {
        $pautasIds = $pautas->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($pautasIds === []) {
            return [];
        }

        $overrides = DB::table('avaliacao_pauta_alternativa')
            ->where('avaliacao_id', (int) $avaliacao->id)
            ->whereIn('pauta_id', $pautasIds)
            ->get(['pauta_id', 'alternativa_id'])
            ->groupBy('pauta_id')
            ->map(fn (Collection $rows): array => $rows->pluck('alternativa_id')->map(fn ($id) => (int) $id)->all());

        $overrideAlternativas = Alternativa::query()
            ->whereIn('id', $overrides->flatten()->unique()->values()->all())
            ->orderedForDocumento()
            ->get()
            ->keyBy('id');

        $alternativasTipoAvaliacao = Alternativa::query()
            ->where('tipo_avaliacao_id', (int) $avaliacao->tipo_avaliacao_id)
            ->where('status', true)
            ->orderedForDocumento()
            ->get();

        $porPauta = [];

        foreach ($pautas as $pauta) {
            $pautaId = (int) $pauta->id;
            $overrideIds = $overrides->get($pautaId, []);

            if ($overrideIds !== []) {
                $porPauta[$pautaId] = collect($overrideIds)
                    ->map(fn (int $id) => $overrideAlternativas->get($id))
                    ->filter()
                    ->values();

                continue;
            }

            $alternativas = $pauta->alternativas
                ->where('status', true)
                ->values();

            if ($alternativas->isEmpty()) {
                $alternativas = $alternativasTipoAvaliacao;
            } else {
                $alternativas = Alternativa::sortCollectionForDocumento($alternativas);
            }

            $porPauta[$pautaId] = $alternativas->values();
        }

        return $porPauta;
    }

    /**
     * @param  array<int, Collection<int, Alternativa>>  $alternativasPorPauta
     * @return Collection<int, array{nome: string, descricao: string}>
     */
    private function montarLegenda(array $alternativasPorPauta): Collection
    {
        return collect($alternativasPorPauta)
            ->flatMap(fn (Collection $alternativas): Collection => $alternativas)
            ->filter(fn (Alternativa $alternativa): bool => (bool) $alternativa->vai_no_documento)
            ->unique(fn (Alternativa $alternativa): int => (int) $alternativa->id)
            ->pipe(fn (Collection $alternativas): Collection => Alternativa::sortCollectionForDocumento($alternativas))
            ->map(fn (Alternativa $alternativa): array => [
                'nome' => mb_strtoupper((string) $alternativa->nome),
                'descricao' => trim((string) (
                    $alternativa->descricao_documento
                    ?: $alternativa->observacao
                    ?: $alternativa->nome
                )),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, Pauta>  $pautas
     * @param  Collection<int, array{nome: string, descricao: string}>  $legenda
     * @param  array{diretor: string, coordenacao: string, tem_diretor: bool, tem_coordenacao: bool, pode_exportar: bool, motivo_bloqueio: string}  $gestores
     * @return array<string, mixed>
     */
    private function montarDocumentoAluno(
        Avaliacao $avaliacao,
        Turma $turma,
        Aluno $aluno,
        Collection $pautas,
        Collection $legenda,
        array $gestores,
        string $logoDataUri,
        ?string $documentoTipo = null,
        ?\App\Data\Avaliacoes\AvaliacaoDocumentoData $documento = null,
    ): array {
        $documento ??= app(AvaliacaoDocumentoReader::class)->ler((int) $avaliacao->id, $aluno, $turma);

        if ($documento->responsaveisSnapshot === []) {
            throw new ResponsaveisParecerInvalidosException(
                'O documento do aluno não possui snapshot de responsáveis do parecer.'
            );
        }

        $snapshot = $documento->responsaveisSnapshot;
        $gestores = app(ParecerResponsaveisResolver::class)->dadosParaDocumento($snapshot);
        $escolaSnapshot = is_array($documento->contexto['escola'] ?? null) ? $documento->contexto['escola'] : [];
        $turmaSnapshot = is_array($documento->contexto['turma'] ?? null) ? $documento->contexto['turma'] : [];
        $serieSnapshot = is_array($documento->contexto['serie'] ?? null) ? $documento->contexto['serie'] : [];
        $escolaNome = trim((string) ($escolaSnapshot['nome'] ?? ''));
        $turmaNome = trim((string) ($turmaSnapshot['nome'] ?? ''));
        $turmaTurno = trim((string) ($turmaSnapshot['turno'] ?? ''));
        $serieNome = trim((string) ($serieSnapshot['nome'] ?? ''));

        $pautasPayload = $documento->pautas();
        $infosPayload = $documento->informacoes();

        $alternativaIds = collect($pautasPayload)
            ->pluck('alternativa_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $nomesAlternativas = collect($this->nomesAlternativas);
        $alternativasFaltantes = collect($alternativaIds)
            ->reject(fn (int $id): bool => $nomesAlternativas->has($id))
            ->values();

        if ($alternativasFaltantes->isNotEmpty()) {
            $nomesAlternativas = $nomesAlternativas->merge(
                Alternativa::query()
                    ->whereIn('id', $alternativasFaltantes->all())
                    ->pluck('nome', 'id')
            );
            $this->nomesAlternativas = $nomesAlternativas->all();
        }

        $componentes = $pautas
            ->groupBy(fn (Pauta $pauta): string => $pauta->componente_curricular_id ? (string) $pauta->componente_curricular_id : 'geral')
            ->map(function (Collection $pautasDoComponente) use ($turma, $pautasPayload, $infosPayload, $nomesAlternativas): array {
                /** @var Pauta $primeiraPauta */
                $primeiraPauta = $pautasDoComponente->first();
                $componenteId = $primeiraPauta->componente_curricular_id ? (int) $primeiraPauta->componente_curricular_id : null;
                $textoInfo = trim((string) ($infosPayload[(string) ((int) ($componenteId ?? 0))]['texto'] ?? ''));

                return [
                    'nome' => $primeiraPauta->componente?->nome ?? 'Geral',
                    'professor' => $this->professorDoComponente(
                        $turma,
                        $componenteId,
                        $pautasDoComponente,
                        collect($pautasPayload),
                        is_array($infosPayload[(string) ((int) ($componenteId ?? 0))] ?? null)
                            ? $infosPayload[(string) ((int) ($componenteId ?? 0))]
                            : null,
                    ),
                    'informacoes_complementares' => $textoInfo,
                    'mostrar_informacoes_complementares' => $textoInfo !== '',
                    'pautas' => $pautasDoComponente
                        ->values()
                        ->map(function (Pauta $pauta, int $index) use ($pautasPayload, $nomesAlternativas): array {
                            $resposta = $pautasPayload[(string) (int) $pauta->id] ?? null;
                            $alternativaId = (int) ($resposta['alternativa_id'] ?? 0);

                            return [
                                'ordem' => $index + 1,
                                'texto' => (string) $pauta->texto,
                                'resultado' => (string) ($nomesAlternativas[$alternativaId] ?? 'Não Avaliado'),
                                'observacao' => (string) ($resposta['observacao'] ?? ''),
                            ];
                        })
                        ->all(),
                ];
            })
            ->values();

        return [
            'logo' => $logoDataUri,
            'avaliacao_titulo' => mb_strtoupper(trim(implode(' - ', array_filter([
                $avaliacao->tipo?->nome,
                $avaliacao->nome,
            ])))),
            'escola' => $escolaNome !== '' ? $escolaNome : ($turma->escola?->nome ?? ''),
            'estudante' => (string) $aluno->nome,
            'cgm' => (string) $aluno->cgm,
            'vinculo' => $aluno->tipoVinculoLabel(),
            'curso' => $serieNome !== '' ? $serieNome : (string) ($turma->serie?->nome ?? ''),
            'turma' => $turmaNome !== '' ? $this->rotuloTurmaNome($turmaNome) : $this->rotuloTurma($turma),
            'turno' => $turmaTurno !== '' ? $this->formatarTurnoValor($turmaTurno) : $this->formatarTurno($turma),
            'documento_tipo' => $documentoTipo,
            'ano_letivo' => (string) ($avaliacao->data_inicio?->format('Y') ?? now()->format('Y')),
            'periodo_avaliacao' => $this->periodoAvaliacaoDoAluno($avaliacao, $aluno),
            'data_impressao' => $this->formatarDataExtenso(now()),
            'diretor' => $gestores['diretor'],
            'coordenacao' => $gestores['coordenacao'],
            'legenda' => $legenda->all(),
            'componentes' => $componentes->all(),
        ];
    }

    /**
     * @param  Collection<int, Pauta>  $pautas
     * @param  Collection<string, array>  $pautasPayload
     */
    private function professorDoComponente(
        Turma $turma,
        ?int $componenteId,
        Collection $pautas,
        Collection $pautasPayload,
        ?array $infoPayload = null,
    ): string
    {
        $professorIds = [];
        $professorNomes = [];
        foreach ($pautas as $pauta) {
            $item = $pautasPayload->get((string) (int) $pauta->id) ?? $pautasPayload[(string) (int) $pauta->id] ?? null;
            if (! is_array($item)) {
                continue;
            }

            if (filled($item['professor_nome'] ?? null)) {
                $professorNomes[] = trim((string) $item['professor_nome']);
            }
            if (! empty($item['professor_id'])) {
                $professorIds[] = (int) $item['professor_id'];
            }
        }

        if (filled($infoPayload['professor_nome'] ?? null)) {
            $professorNomes[] = trim((string) $infoPayload['professor_nome']);
        }
        if (! empty($infoPayload['professor_id'])) {
            $professorIds[] = (int) $infoPayload['professor_id'];
        }

        $professorNomes = array_values(array_unique(array_filter($professorNomes)));
        if ($professorNomes !== []) {
            return implode(' / ', $professorNomes);
        }

        $professorIds = array_values(array_unique(array_filter($professorIds)));
        if ($professorIds !== []) {
            $faltantes = array_values(array_diff($professorIds, array_keys($this->nomesProfessores)));
            if ($faltantes !== []) {
                $this->nomesProfessores += Professor::query()
                    ->whereIn('id', $faltantes)
                    ->pluck('nome', 'id')
                    ->map(fn ($nome): string => (string) $nome)
                    ->all();
            }

            $nomesAtuais = collect($professorIds)
                ->map(fn (int $id): ?string => $this->nomesProfessores[$id] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($nomesAtuais !== []) {
                return implode(' / ', $nomesAtuais);
            }
        }

        if ($componenteId) {
            $chave = (int) $turma->id.':'.$componenteId;
            if (array_key_exists($chave, $this->nomesProfessoresPorTurmaComponente)) {
                return $this->nomesProfessoresPorTurmaComponente[$chave];
            }

            $vinculo = TurmaComponenteProfessor::query()
                ->where('turma_id', (int) $turma->id)
                ->where('componente_curricular_id', $componenteId)
                ->with('professor:id,nome')
                ->first();

            if ($vinculo?->professor?->nome) {
                return (string) $vinculo->professor->nome;
            }
        }

        return '';
    }

    /** @param Collection<int, \App\Data\Avaliacoes\AvaliacaoDocumentoData> $documentos */
    private function precarregarNomesProfessores(Collection $documentos): void
    {
        $ids = $documentos
            ->flatMap(fn (\App\Data\Avaliacoes\AvaliacaoDocumentoData $documento): Collection => collect([
                $documento->pautas(),
                $documento->informacoes(),
            ])->flatten(1))
            ->pluck('professor_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($ids->isNotEmpty()) {
            $this->nomesProfessores = $this->nomesProfessores + Professor::query()
                ->whereIn('id', $ids->all())
                ->pluck('nome', 'id')
                ->map(fn ($nome): string => (string) $nome)
                ->all();
        }
    }

    /** @param Collection<int, \App\Data\Avaliacoes\AvaliacaoDocumentoData> $documentos */
    private function precarregarNomesAlternativas(Collection $documentos): void
    {
        $ids = $documentos
            ->flatMap(fn (\App\Data\Avaliacoes\AvaliacaoDocumentoData $documento): Collection => collect($documento->pautas()))
            ->pluck('alternativa_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->reject(fn (int $id): bool => array_key_exists($id, $this->nomesAlternativas))
            ->values();

        if ($ids->isNotEmpty()) {
            $this->nomesAlternativas += Alternativa::query()
                ->whereIn('id', $ids->all())
                ->pluck('nome', 'id')
                ->map(fn ($nome): string => (string) $nome)
                ->all();
        }
    }

    /** @param Collection<int, Turma> $turmas */
    private function precarregarProfessoresPorTurmaComponente(Collection $turmas): void
    {
        $vinculos = TurmaComponenteProfessor::query()
            ->whereIn('turma_id', $turmas->pluck('id')->map(fn ($id): int => (int) $id)->all())
            ->with('professor:id,nome')
            ->get(['turma_id', 'componente_curricular_id', 'professor_id']);

        foreach ($vinculos as $vinculo) {
            $nome = trim((string) ($vinculo->professor?->nome ?? ''));
            if ($nome === '') {
                continue;
            }

            $this->nomesProfessoresPorTurmaComponente[
                (int) $vinculo->turma_id.':'.(int) $vinculo->componente_curricular_id
            ] = $nome;
        }
    }

    /**
     * @return array{diretor: string, coordenacao: string, tem_diretor: bool, tem_coordenacao: bool, pode_exportar: bool, motivo_bloqueio: string}
     */
    public function gestoresDaTurma(Turma $turma, ?int $avaliacaoId = null): array
    {
        if ($avaliacaoId) {
            $alunoIds = Aluno::query()
                ->where('id_turma', (int) $turma->id)
                ->where('status', '!=', Aluno::STATUS_PENDENTE)
                ->pluck('id');

            if ($alunoIds->isNotEmpty()) {
                $documentos = AvaliacaoAlunoDocumento::query()
                    ->where('avaliacao_id', $avaliacaoId)
                    ->whereIn('aluno_id', $alunoIds->all())
                    ->get(['id', 'aluno_id', 'responsaveis_snapshot']);

                if ($documentos->count() === $alunoIds->count()) {
                    try {
                        $responsaveis = $documentos
                            ->map(fn (AvaliacaoAlunoDocumento $documento): array =>
                                app(ParecerResponsaveisResolver::class)
                                    ->dadosParaDocumento($documento->responsaveis_snapshot ?? [])
                            );

                        return [
                            ...$responsaveis->first(),
                            'tem_diretor' => true,
                            'tem_coordenacao' => true,
                            'pode_exportar' => true,
                            'motivo_bloqueio' => '',
                        ];
                    } catch (ResponsaveisParecerInvalidosException) {
                        // Snapshot ausente/incompleto: a elegibilidade atual decide
                        // se os documentos faltantes podem ser capturados.
                    }
                }
            }
        }

        return app(ParecerResponsaveisResolver::class)->elegibilidade($turma);
    }

    /**
     * @param  Collection<int, Turma>  $turmas
     * @param  array<string, mixed>  $params
     * @return Collection<int, array<string, mixed>>
     */
    private function montarDadosCsv(Avaliacao $avaliacao, Collection $turmas, string $escopo, array $params): Collection
    {
        $reader = app(AvaliacaoDocumentoBatchReader::class);

        return $turmas
            ->map(function (Turma $turma) use ($avaliacao, $escopo, $params, $reader): ?array {
                $alunos = $this->alunosDaTurma($turma, $escopo, $params, $avaliacao);
                $pautas = $this->pautasDaTurma($avaliacao, $turma);

                if ($alunos->isEmpty() || $pautas->isEmpty()) {
                    return null;
                }

                $respostas = collect();
                $informacoesComplementares = collect();
                $alternativaIds = [];
                $documentosPorAluno = $reader->lerParaAlunos((int) $avaliacao->id, $alunos, $turma, false);
                $this->precarregarNomesAlternativas($documentosPorAluno);

                foreach ($alunos as $aluno) {
                    $alunoId = (int) $aluno->id;
                    $documento = $documentosPorAluno->get($alunoId);

                    foreach ($documento->pautas() as $pautaId => $item) {
                        if (! is_array($item) || empty($item['alternativa_id'])) {
                            continue;
                        }

                        $alternativaIds[] = (int) $item['alternativa_id'];
                        $respostas->put($pautaId.'-'.$alunoId, (object) [
                            'pauta_id' => (int) $pautaId,
                            'aluno_id' => (int) $alunoId,
                            'alternativa_id' => (int) $item['alternativa_id'],
                            'observacao' => $item['observacao'] ?? null,
                            'professor_id' => $item['professor_id'] ?? null,
                            'respondido_em' => isset($item['respondido_em'])
                                ? \Carbon\Carbon::parse($item['respondido_em'])
                                : null,
                        ]);
                    }

                    foreach ($documento->informacoes() as $componenteId => $info) {
                        $informacoesComplementares->put(
                            ((int) $componenteId).'-'.(int) $alunoId,
                            (object) [
                                'aluno_id' => (int) $alunoId,
                                'componente_curricular_id' => (int) $componenteId,
                                'informacoes_complementares' => (string) ($info['texto'] ?? ''),
                            ]
                        );
                    }
                }

                $nomesAlternativas = collect($this->nomesAlternativas);

                $respostas = $respostas->map(function (object $resposta) use ($nomesAlternativas): object {
                    $resposta->alternativa = (object) [
                        'id' => (int) $resposta->alternativa_id,
                        'nome' => (string) ($nomesAlternativas[(int) $resposta->alternativa_id] ?? ''),
                    ];

                    return $resposta;
                });

                return [
                    'turma' => $turma,
                    'alunos' => $alunos,
                    'pautas' => $pautas,
                    'respostas' => $respostas,
                    'informacoes_complementares' => $informacoesComplementares,
                ];
            })
            ->filter()
            ->values();
    }

    private function rotuloTurma(Turma $turma): string
    {
        return $this->rotuloTurmaNome((string) $turma->nome);
    }

    private function rotuloTurmaNome(string $nome): string
    {
        $nome = trim($nome);

        if ($nome === '') {
            return 'Turma';
        }

        return preg_match('/^turma\b/i', $nome) === 1 ? $nome : 'Turma '.$nome;
    }

    private function nomeTurma(Turma $turma): string
    {
        return implode(' - ', array_filter([
            $turma->escola?->nome,
            $turma->serie?->nome,
            $this->rotuloTurma($turma),
        ]));
    }

    private function formatarTurno(Turma $turma): string
    {
        return $this->formatarTurnoValor((string) $turma->turno);
    }

    private function formatarTurnoValor(string $turno): string
    {
        $turno = trim(str_replace('_', ' ', $turno));

        if ($turno === '') {
            return '';
        }

        return mb_convert_case($turno, MB_CASE_TITLE, 'UTF-8');
    }

    private function logoDataUri(): string
    {
        $paths = [
            'C:\\Users\\gabriel.capoia\\Documents\\2026\\App Parecer\\app-desktop-electron\\src\\assets\\logo-prefeitura.jpeg',
            base_path('../../App Parecer/app-desktop-electron/src/assets/logo-prefeitura.jpeg'),
            base_path('../../App Parecer/logo-prefeitura.jpeg'),
            public_path('images/logo-umuarma-educacao-abrinq.jpeg'),
            public_path('images/logo-umuarama.png'),
        ];

        foreach ($paths as $path) {
            $realPath = realpath($path);

            if (! $realPath || ! is_file($realPath)) {
                continue;
            }

            $mime = str_ends_with(Str::lower($realPath), '.png') ? 'image/png' : 'image/jpeg';

            return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($realPath));
        }

        return '';
    }

    /**
     * @param  Collection<int, array<string, mixed>>|array<int, array<string, mixed>>  $documentos
     */
    private function criarPdf(Collection|array $documentos)
    {
        return Pdf::loadView(self::VIEW, [
            'documentos' => collect($documentos)->values(),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'dpi' => 96,
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'isFontSubsettingEnabled' => true,
                'isPhpEnabled' => false,
                'chroot' => public_path(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $documento
     */
    private function contarPaginasDocumento(array $documento): int
    {
        $pdf = $this->criarPdf([array_replace($documento, [
            'precisa_pagina_em_branco' => false,
        ])]);
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        return max(1, (int) $dompdf->getCanvas()->get_page_count());
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $documentos
     * @return Collection<int, array<string, mixed>>
     */
    private function prepararDocumentosParaPdf(Collection $documentos, string $escopo): Collection
    {
        return $documentos
            ->values()
            ->map(function (array $documento) use ($escopo): array {
                $paginas = $this->contarPaginasDocumento($documento);
                $documento['paginas_estimadas'] = $paginas;
                $documento['precisa_pagina_em_branco'] = $this->deveAdicionarPaginaEmBranco($escopo, $paginas);

                return $documento;
            });
    }

    private function deveAdicionarPaginaEmBranco(string $escopo, int $paginas): bool
    {
        if ($escopo === 'aluno') {
            return false;
        }

        return $paginas > 0 && $paginas % 2 !== 0;
    }

    private function periodoAvaliacaoDoAluno(Avaliacao $avaliacao, Aluno $aluno): string
    {
        $inicio = $avaliacao->data_inicio?->copy();
        $fim = $avaliacao->data_fim?->copy();
        $matricula = $aluno->data_matricula?->copy();
        $transferencia = $aluno->status === Aluno::STATUS_TRANSFERIDO
            ? $aluno->status_alterado_em?->copy()?->startOfDay()
            : null;

        if ($matricula && (! $inicio || $matricula->gt($inicio))) {
            $inicio = $matricula;
        }

        if ($transferencia && (! $fim || $transferencia->lt($fim))) {
            $fim = $transferencia;
        }

        if (! $inicio && ! $fim) {
            return '';
        }

        if (! $inicio) {
            $inicio = $fim?->copy();
        }

        if (! $fim) {
            $fim = $inicio?->copy();
        }

        if ($inicio && $fim && $inicio->gt($fim)) {
            $inicio = $fim->copy();
        }

        return trim(implode(' a ', array_filter([
            $inicio ? $this->formatarDataExtenso($inicio) : null,
            $fim ? $this->formatarDataExtenso($fim) : null,
        ])));
    }

    private function formatarDataExtenso(Carbon $data): string
    {
        return $data->locale('pt_BR')->translatedFormat('d \d\e F \d\e Y');
    }

    /**
     * @param  Collection<int, Turma>  $turmas
     * @param  array<string, mixed>  $params
     */
    private function registrarLog(
        Avaliacao $avaliacao,
        Collection $turmas,
        string $escopo,
        array $params,
        ?User $usuario,
        string $formato,
        int $quantidadeAlunos,
        ?int $quantidadePaginas
    ): void {
        /** @var Turma|null $primeiraTurma */
        $primeiraTurma = $turmas->first();
        $alunosExportados = $this->alunosExportadosParaLog($avaliacao, $turmas, $escopo, $params);
        $parametros = [
            'avaliacao' => $avaliacao->nome,
            'tipo' => $avaliacao->tipo?->nome,
            'turmas' => $turmas->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        ];

        if ($escopo === 'aluno') {
            $parametros['aluno_tipo_vinculo'] = (string) ($alunosExportados->first()?->tipo_vinculo ?: Aluno::TIPO_VINCULO_PRINCIPAL);
        } else {
            $parametros['vinculos_por_tipo'] = $this->contarVinculosPorTipo($alunosExportados);
        }

        AvaliacaoExportacao::query()->create([
            'avaliacao_id' => (int) $avaliacao->id,
            'escola_id' => $escopo === 'escola' ? (int) ($params['escola_id'] ?? 0) : $primeiraTurma?->id_escola,
            'turma_id' => $escopo === 'turma' || $escopo === 'aluno' ? (int) ($params['turma_id'] ?? $primeiraTurma?->id) : null,
            'aluno_id' => $escopo === 'aluno' ? (int) ($params['aluno_id'] ?? 0) : null,
            'user_id' => $usuario?->id,
            'escopo' => $escopo,
            'formato' => $formato,
            'quantidade_alunos' => $quantidadeAlunos,
            'quantidade_paginas' => $quantidadePaginas,
            'parametros' => $parametros,
            'exportado_em' => now(),
        ]);
    }

    /**
     * @param  Collection<int, Turma>  $turmas
     * @param  array<string, mixed>  $params
     * @return Collection<int, Aluno>
     */
    private function alunosExportadosParaLog(Avaliacao $avaliacao, Collection $turmas, string $escopo, array $params): Collection
    {
        return $turmas
            ->flatMap(function (Turma $turma) use ($avaliacao, $escopo, $params): Collection {
                if ($this->pautasDaTurma($avaliacao, $turma)->isEmpty()) {
                    return collect();
                }

                return $this->alunosDaTurma($turma, $escopo, $params, $avaliacao);
            })
            ->values();
    }

    /**
     * @param  Collection<int, Aluno>  $alunos
     * @return array<string, int>
     */
    private function contarVinculosPorTipo(Collection $alunos): array
    {
        $contagens = $alunos->countBy(
            fn (Aluno $aluno): string => (string) ($aluno->tipo_vinculo ?: Aluno::TIPO_VINCULO_PRINCIPAL)
        );

        $resultado = [];

        foreach (array_keys(Aluno::tiposVinculoOptions()) as $tipo) {
            $resultado[$tipo] = (int) $contagens->get($tipo, 0);
        }

        return $resultado;
    }

    /**
     * @param  Collection<int, Turma>  $turmas
     * @param  Collection<int, array<string, mixed>>  $documentos
     */
    private function nomeArquivo(Avaliacao $avaliacao, string $escopo, Collection $turmas, Collection $documentos): string
    {
        $partes = ['avaliacao', $avaliacao->id, $escopo];

        if ($escopo === 'aluno') {
            $partes[] = $documentos->first()['estudante'] ?? 'aluno';
        } elseif ($escopo === 'turma') {
            $partes[] = $turmas->first()?->nome ?? 'turma';
        } else {
            $partes[] = $turmas->first()?->escola?->nome ?? 'escola';
        }

        return Str::slug(implode('-', array_filter($partes))).'.pdf';
    }

    /**
     * @param  Collection<int, Turma>  $turmas
     */
    private function nomeArquivoCsv(Avaliacao $avaliacao, string $escopo, Collection $turmas): string
    {
        $partes = ['avaliacao', $avaliacao->id, $escopo];

        if ($escopo === 'turma') {
            $partes[] = $turmas->first()?->nome ?? 'turma';
        } elseif ($escopo === 'escola') {
            $partes[] = $turmas->first()?->escola?->nome ?? 'escola';
        } else {
            $partes[] = 'aluno';
        }

        $partes[] = now()->format('Ymd_His');

        return Str::slug(implode('-', array_filter($partes))).'.csv';
    }
}
