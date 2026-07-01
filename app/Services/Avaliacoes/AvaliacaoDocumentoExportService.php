<?php

namespace App\Services\Avaliacoes;

use App\Models\Aluno;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\AvaliacaoExportacao;
use App\Models\AvaliacaoInformacaoComplementar;
use App\Models\AvaliacaoResposta;
use App\Models\Pauta;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
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

    /**
     * @param  array<string, mixed>  $params
     */
    public function exportar(array $params, ?User $usuario): Response
    {
        $avaliacao = $this->buscarAvaliacao((int) ($params['avaliacao_id'] ?? 0));
        $escopo = (string) ($params['escopo'] ?? 'turma');
        $turmas = $this->resolverTurmas($avaliacao, $escopo, $params, $usuario);
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
     * @param  array<string, mixed>  $params
     */
    public function exportarCsv(array $params, ?User $usuario): StreamedResponse
    {
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
                /** @var Collection<string, AvaliacaoResposta> $respostas */
                $respostas = $turmaDados['respostas'];
                /** @var Collection<string, AvaliacaoInformacaoComplementar> $informacoesComplementares */
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

        $documento = $this->montarDocumentoAluno(
            $avaliacao,
            $turma,
            $aluno,
            $pautas,
            $this->montarLegenda($this->alternativasPorPauta($avaliacao, $pautas)),
            $this->gestoresDaTurma($turma),
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
        if ($usuario?->hasRole('Admin')) {
            return;
        }

        if (! $usuario) {
            $query->whereRaw('1 = 0');

            return;
        }

        $escolasIds = $usuario->idsEscolasVinculadas();

        if ($escolasIds !== []) {
            $query->whereIn('id_escola', $escolasIds);

            return;
        }

        $query->whereRaw('1 = 0');
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

        foreach ($turmas as $turma) {
            $alunos = $this->alunosDaTurma($turma, $escopo, $params);

            if ($alunos->isEmpty()) {
                continue;
            }

            $pautas = $this->pautasDaTurma($avaliacao, $turma);

            if ($pautas->isEmpty()) {
                continue;
            }

            $alternativasPorPauta = $this->alternativasPorPauta($avaliacao, $pautas);
            $legenda = $this->montarLegenda($alternativasPorPauta);
            $gestores = $this->gestoresDaTurma($turma);

            foreach ($alunos as $aluno) {
                $documentos->push($this->montarDocumentoAluno(
                    $avaliacao,
                    $turma,
                    $aluno,
                    $pautas,
                    $legenda,
                    $gestores,
                    $logoDataUri
                ));
            }
        }

        return $documentos->values();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return Collection<int, Aluno>
     */
    private function alunosDaTurma(Turma $turma, string $escopo, array $params): Collection
    {
        $query = Aluno::query()
            ->where('id_turma', (int) $turma->id)
            ->where('status', '!=', Aluno::STATUS_PENDENTE)
            ->orderBy('nome');

        if ($escopo === 'aluno') {
            $query->whereKey((int) ($params['aluno_id'] ?? 0));
        }

        return $query->get(['id', 'nome', 'cgm', 'id_turma', 'status', 'tipo_vinculo', 'data_matricula', 'status_alterado_em']);
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
        ?string $documentoTipo = null
    ): array {
        $respostas = AvaliacaoResposta::query()
            ->where('avaliacao_id', (int) $avaliacao->id)
            ->where('turma_id', (int) $turma->id)
            ->where('aluno_id', (int) $aluno->id)
            ->whereIn('pauta_id', $pautas->pluck('id')->map(fn ($id) => (int) $id)->all())
            ->with(['alternativa:id,nome', 'professor:id,nome'])
            ->get()
            ->keyBy('pauta_id');

        $informacoesComplementares = AvaliacaoInformacaoComplementar::query()
            ->where('avaliacao_id', (int) $avaliacao->id)
            ->where('turma_id', (int) $turma->id)
            ->where('aluno_id', (int) $aluno->id)
            ->get(['aluno_id', 'componente_curricular_id', 'informacoes_complementares'])
            ->keyBy(fn (AvaliacaoInformacaoComplementar $registro): string => ((int) ($registro->componente_curricular_id ?? 0)).'-'.((int) $registro->aluno_id));

        $alunoId = (int) $aluno->id;

        $componentes = $pautas
            ->groupBy(fn (Pauta $pauta): string => $pauta->componente_curricular_id ? (string) $pauta->componente_curricular_id : 'geral')
            ->map(function (Collection $pautasDoComponente) use ($turma, $respostas, $informacoesComplementares, $alunoId): array {
                /** @var Pauta $primeiraPauta */
                $primeiraPauta = $pautasDoComponente->first();
                $componenteId = $primeiraPauta->componente_curricular_id ? (int) $primeiraPauta->componente_curricular_id : null;
                $informacaoComplementar = $informacoesComplementares->get(((int) ($componenteId ?? 0)).'-'.$alunoId);

                return [
                    'nome' => $primeiraPauta->componente?->nome ?? 'Geral',
                    'professor' => $this->professorDoComponente($turma, $componenteId, $pautasDoComponente, $respostas),
                    'informacoes_complementares' => trim((string) ($informacaoComplementar?->informacoes_complementares ?? '')),
                    'mostrar_informacoes_complementares' => trim((string) ($informacaoComplementar?->informacoes_complementares ?? '')) !== '',
                    'pautas' => $pautasDoComponente
                        ->values()
                        ->map(function (Pauta $pauta, int $index) use ($respostas): array {
                            /** @var AvaliacaoResposta|null $resposta */
                            $resposta = $respostas->get((int) $pauta->id);

                            return [
                                'ordem' => $index + 1,
                                'texto' => (string) $pauta->texto,
                                'resultado' => (string) ($resposta?->alternativa?->nome ?? 'Não Avaliado'),
                                'observacao' => (string) ($resposta?->observacao ?? ''),
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
            'escola' => $turma->escola?->nome ?? '',
            'estudante' => (string) $aluno->nome,
            'cgm' => (string) $aluno->cgm,
            'vinculo' => $aluno->tipoVinculoLabel(),
            'curso' => (string) ($turma->serie?->nome ?? ''),
            'turma' => $this->rotuloTurma($turma),
            'turno' => $this->formatarTurno($turma),
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
     * @param  Collection<int, AvaliacaoResposta>  $respostas
     */
    private function professorDoComponente(Turma $turma, ?int $componenteId, Collection $pautas, Collection $respostas): string
    {
        if ($componenteId) {
            $vinculo = TurmaComponenteProfessor::query()
                ->where('turma_id', (int) $turma->id)
                ->where('componente_curricular_id', $componenteId)
                ->with('professor:id,nome')
                ->first();

            if ($vinculo?->professor?->nome) {
                return (string) $vinculo->professor->nome;
            }
        }

        foreach ($pautas as $pauta) {
            $professor = $respostas->get((int) $pauta->id)?->professor;

            if ($professor?->nome) {
                return (string) $professor->nome;
            }
        }

        return '';
    }

    /**
     * @return array{diretor: string, coordenacao: string, tem_diretor: bool, tem_coordenacao: bool, pode_exportar: bool, motivo_bloqueio: string}
     */
    public function gestoresDaTurma(Turma $turma): array
    {
        $gestores = ServidorFuncaoAdministrativa::query()
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->where(function (Builder $query) use ($turma): void {
                $query
                    ->where('id_escola', (int) $turma->id_escola)
                    ->orWhereHas('turmas', fn (Builder $turmas): Builder => $turmas->whereKey((int) $turma->id));
            })
            ->whereHas('servidor', fn (Builder $servidor): Builder => $servidor->where('status', Servidor::STATUS_ATIVO))
            ->whereHas('funcaoAdministrativa', function (Builder $funcao): void {
                $funcao
                    ->where('ativo', true)
                    ->where(function (Builder $flags): void {
                        $flags
                            ->where('direcao_escolar', true)
                            ->orWhere('coordenacao_pedagogica', true);
                    });
            })
            ->with([
                'funcaoAdministrativa:id,nome,direcao_escolar,coordenacao_pedagogica',
                'servidor:id,nome,id_escola,status',
                'turmas:id',
            ])
            ->get();

        $diretor = $this->gestorPorFlag($gestores, $turma, 'direcao_escolar');
        $coordenacao = $this->gestorPorFlag($gestores, $turma, 'coordenacao_pedagogica');
        $diretorNome = $this->formatarGestor($diretor);
        $coordenacaoNome = $this->formatarGestor($coordenacao);
        $temDiretor = $diretorNome !== '';
        $temCoordenacao = $coordenacaoNome !== '';

        return [
            'diretor' => $diretorNome,
            'coordenacao' => $coordenacaoNome,
            'tem_diretor' => $temDiretor,
            'tem_coordenacao' => $temCoordenacao,
            'pode_exportar' => $temDiretor && $temCoordenacao,
            'motivo_bloqueio' => $this->motivoBloqueioGestores($temDiretor, $temCoordenacao),
        ];
    }

    private function motivoBloqueioGestores(bool $temDiretor, bool $temCoordenacao): string
    {
        if ($temDiretor && $temCoordenacao) {
            return '';
        }

        return 'A turma não possui vínculo com Diretor(a) ou Coordenador(a).';
    }

    /**
     * @param  Collection<int, ServidorFuncaoAdministrativa>  $gestores
     */
    private function gestorPorFlag(Collection $gestores, Turma $turma, string $flag): ?ServidorFuncaoAdministrativa
    {
        return $gestores
            ->filter(fn (ServidorFuncaoAdministrativa $vinculo): bool => (bool) ($vinculo->funcaoAdministrativa?->{$flag} ?? false))
            ->sort(function (ServidorFuncaoAdministrativa $a, ServidorFuncaoAdministrativa $b) use ($turma): int {
                $score = $this->pontuacaoGestorDaTurma($b, $turma) <=> $this->pontuacaoGestorDaTurma($a, $turma);

                return $score !== 0
                    ? $score
                    : strcmp((string) ($a->servidor?->nome ?? ''), (string) ($b->servidor?->nome ?? ''));
            })
            ->first();
    }

    private function pontuacaoGestorDaTurma(ServidorFuncaoAdministrativa $vinculo, Turma $turma): int
    {
        if ($vinculo->turmas->contains('id', (int) $turma->id)) {
            return 2;
        }

        if ((int) ($vinculo->id_escola ?? $vinculo->servidor?->id_escola) === (int) $turma->id_escola) {
            return 1;
        }

        return 0;
    }

    private function formatarGestor(?ServidorFuncaoAdministrativa $vinculo): string
    {
        $nome = $vinculo?->servidor?->nome;

        if (! $nome) {
            return '';
        }

        $portaria = trim((string) ($vinculo->portaria ?? ''));

        if ($portaria === '') {
            return (string) $nome;
        }

        return $nome.' - '.$portaria;
    }

    /**
     * @param  Collection<int, Turma>  $turmas
     * @param  array<string, mixed>  $params
     * @return Collection<int, array<string, mixed>>
     */
    private function montarDadosCsv(Avaliacao $avaliacao, Collection $turmas, string $escopo, array $params): Collection
    {
        return $turmas
            ->map(function (Turma $turma) use ($avaliacao, $escopo, $params): ?array {
                $alunos = $this->alunosDaTurma($turma, $escopo, $params);
                $pautas = $this->pautasDaTurma($avaliacao, $turma);

                if ($alunos->isEmpty() || $pautas->isEmpty()) {
                    return null;
                }

                $respostas = AvaliacaoResposta::query()
                    ->where('avaliacao_id', (int) $avaliacao->id)
                    ->where('turma_id', (int) $turma->id)
                    ->whereIn('pauta_id', $pautas->pluck('id')->map(fn ($id) => (int) $id)->all())
                    ->whereIn('aluno_id', $alunos->pluck('id')->map(fn ($id) => (int) $id)->all())
                    ->with(['alternativa:id,nome'])
                    ->get()
                    ->keyBy(fn (AvaliacaoResposta $resposta): string => $resposta->pauta_id.'-'.$resposta->aluno_id);

                $informacoesComplementares = AvaliacaoInformacaoComplementar::query()
                    ->where('avaliacao_id', (int) $avaliacao->id)
                    ->where('turma_id', (int) $turma->id)
                    ->whereIn('aluno_id', $alunos->pluck('id')->map(fn ($id) => (int) $id)->all())
                    ->get(['aluno_id', 'componente_curricular_id', 'informacoes_complementares'])
                    ->keyBy(fn (AvaliacaoInformacaoComplementar $registro): string => ((int) ($registro->componente_curricular_id ?? 0)).'-'.((int) $registro->aluno_id));

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
        $nome = trim((string) $turma->nome);

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
        $turno = trim(str_replace('_', ' ', (string) $turma->turno));

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

                return $this->alunosDaTurma($turma, $escopo, $params);
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
