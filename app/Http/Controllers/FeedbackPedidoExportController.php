<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\FeedbackPedido;
use App\Services\Relatorios\FeedbackPedidoRelatorioService;
use App\Services\Relatorios\FeedbackGraficoService;
use App\Services\Relatorios\ChartRenderService;

class FeedbackPedidoExportController extends Controller
{
    /**
     * Exportar relatório geral (cards + gráficos + matriz + tabela)
     */
    public function exportarGeral(Request $request)
    {
        return $this->gerarPDF($request, 'geral');
    }

    /**
     * Exportar apenas tabela de feedbacks (listagem)
     */
    public function exportarListagem(Request $request)
    {
        return $this->gerarPDF($request, 'listagem');
    }

    /**
     * Exportar apenas gráficos e cards (análise visual)
     */
    public function exportarGraficos(Request $request)
    {
        return $this->gerarPDF($request, 'graficos');
    }

    /**
     * Exportar relatório de avaliação por empresa terceirizada
     */
    public function exportarTerceirizada(Request $request)
    {
        return $this->gerarPDF($request, 'terceirizada');
    }

    /**
     * Método principal que gera o PDF com flags para mostrar/esconder seções
     */
    private function gerarPDF(Request $request, string $tipo)
    {
        ini_set('memory_limit', '512M');

        try {
            /** @var \App\Models\User */
            $user = Auth::user();

            // Validar permissão
            if (!$user->hasPermissionTo('Visualizar Feedback de Pedidos')) {
                return abort(403, 'Sem permissão para exportar este relatório');
            }

            // Coletar filtros da query string
            $filters = $this->coletarFiltros($request);

            // Construir query com filtros
            $query = FeedbackPedido::with([
                'pedido.escola',
                'pedido.tipoManutencao',
            ]);

            // Aplicar filtros
            $this->aplicarFiltros($query, $filters);

            // Ordenar e coletar dados
            $feedbacks = $query->orderBy('created_at', 'desc')->get();

            // Calcular métricas
            $total = $feedbacks->count();
            $mediaGeral = $total > 0 ? round($feedbacks->avg('valor'), 2) : 0;
            $percentualSatisfacao = $total > 0
                ? round(($feedbacks->where('valor', '>=', 3)->count() / $total) * 100)
                : 0;

            // ===== GERAR GRÁFICOS =====
            $graficoMediaMensal = null;
            $graficoPorNota = null;

            // Gerar gráficos apenas se não for listagem
            if ($tipo !== 'listagem') {
                $graficoService = app(FeedbackGraficoService::class);
                $chartRender = app(ChartRenderService::class);

                // Dados dos gráficos
                $dadosMediaMensal = $graficoService->gerarDadosMediaMensal($filters);
                $dadosPorNota = $graficoService->gerarDadosPorNota($filters);

                // Configurações Chart.js
                $configMediaMensal = $graficoService->gerarChartConfig('media_mensal', $dadosMediaMensal);
                $configPorNota = $graficoService->gerarChartConfig('por_nota', $dadosPorNota);

                // Renderizar gráficos
                $graficoMediaMensal = $chartRender->renderizarGrafico($configMediaMensal, 700, 350);
                $graficoPorNota = $chartRender->renderizarGrafico($configPorNota, 700, 350);

                // Se falhar, usar renderização local
                if (!$graficoMediaMensal) {
                    $graficoMediaMensal = $chartRender->renderizarGraficoLocal($configMediaMensal);
                }
                if (!$graficoPorNota) {
                    $graficoPorNota = $chartRender->renderizarGraficoLocal($configPorNota);
                }
            }

            // ===== GERAR MATRIZ - TODOS OS ANOS COM TODOS OS MESES =====
            $matrizesAgrupadas = [];

            // Gerar matriz se for geral OU graficos
            if (in_array($tipo, ['geral', 'graficos']) && !$feedbacks->isEmpty()) {
                $matrizesAgrupadas = $this->gerarMatrizNotasPorMes($feedbacks);
            }

            // ===== GERAR MATRIZ POR EMPRESA TERCEIRIZADA =====
            $matrizesEmpresa = [];

            // Gerar matriz por empresa se for terceirizada
            if ($tipo === 'terceirizada' && !$feedbacks->isEmpty()) {
                $matrizesEmpresa = $this->gerarMatrizPorEmpresaTerceirizada($feedbacks);
            }

            // Gerar PDF
            $service = app(FeedbackPedidoRelatorioService::class);

            return $service->gerarComGraficosEMatriz(
                $mediaGeral,
                $total,
                $percentualSatisfacao,
                $feedbacks,
                $graficoMediaMensal,
                $graficoPorNota,
                [],
                $matrizesAgrupadas,
                $tipo, // Passar o tipo como parâmetro
                $matrizesEmpresa
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Erro ao exportar PDF de Feedback', [
                'erro' => $e->getMessage(),
                'arquivo' => $e->getFile(),
                'linha' => $e->getLine(),
                'stack' => $e->getTraceAsString(),
            ]);

            if (config('app.debug')) {
                return response()->json([
                    'erro' => $e->getMessage(),
                    'arquivo' => $e->getFile(),
                    'linha' => $e->getLine(),
                ], 500);
            }

            return abort(500, 'Erro ao gerar PDF');
        }
    }

    /**
     * Coletar filtros da query string
     */
    private function coletarFiltros(Request $request): array
    {
        $filters = [];

        if ($request->has('valor')) {
            $filters['valor'] = $request->input('valor');
        }
        if ($request->has('nivel_prioridade')) {
            $filters['nivel_prioridade'] = $request->input('nivel_prioridade');
        }
        if ($request->has('tipo_manutencao_id')) {
            $filters['tipo_manutencao_id'] = $request->input('tipo_manutencao_id');
        }
        if ($request->has('escola_id')) {
            $filters['escola_id'] = $request->input('escola_id');
        }
        if ($request->has('empresa_contratada_id')) {
            $filters['empresa_contratada_id'] = $request->input('empresa_contratada_id');
        }
        if ($request->has('mes')) {
            $filters['mes'] = $request->input('mes');
        }
        if ($request->has('data_inicio') && $request->has('data_fim')) {
            $filters['periodo'] = [
                'inicio' => $request->input('data_inicio'),
                'fim' => $request->input('data_fim'),
            ];
        }

        return $filters;
    }

    /**
     * Aplicar filtros na query
     */
    private function aplicarFiltros($query, array $filters): void
    {
        if (isset($filters['valor'])) {
            $query->where('valor', $filters['valor']);
        }
        if (isset($filters['nivel_prioridade'])) {
            $query->whereHas('pedido', fn($q) => $q->where('nivel_prioridade', $filters['nivel_prioridade']));
        }
        if (isset($filters['tipo_manutencao_id'])) {
            $query->whereHas('pedido', fn($q) => $q->where('tipo_manutencao_id', $filters['tipo_manutencao_id']));
        }
        if (isset($filters['escola_id'])) {
            $query->whereHas('pedido', fn($q) => $q->where('escola_id', $filters['escola_id']));
        }
        if (isset($filters['empresa_contratada_id'])) {
            $query->whereHas('pedido', fn($q) => $q->where('empresa_contratada_id', $filters['empresa_contratada_id']));
        }
        if (isset($filters['mes'])) {
            $query->whereMonth('created_at', $filters['mes']);
        }
        if (isset($filters['periodo'])) {
            if ($filters['periodo']['inicio']) {
                $query->whereDate('created_at', '>=', $filters['periodo']['inicio']);
            }
            if ($filters['periodo']['fim']) {
                $query->whereDate('created_at', '<=', $filters['periodo']['fim']);
            }
        }
    }

    /**
     * Gerar matriz de notas por mês
     */
    private function gerarMatrizNotasPorMes($feedbacks): array
    {
        $matrizesAgrupadas = [];

        $mesesLabels = [
            '01' => 'Janeiro',
            '02' => 'Fevereiro',
            '03' => 'Março',
            '04' => 'Abril',
            '05' => 'Maio',
            '06' => 'Junho',
            '07' => 'Julho',
            '08' => 'Agosto',
            '09' => 'Setembro',
            '10' => 'Outubro',
            '11' => 'Novembro',
            '12' => 'Dezembro',
        ];

        // Agrupar feedbacks por mês-ano
        $feedbacksAgrupados = $feedbacks
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m');
            });

        // Separar por ano
        $porAno = [];
        foreach ($feedbacksAgrupados as $mesChave => $registros) {
            $ano = substr($mesChave, 0, 4);
            $mesNum = substr($mesChave, 5, 2);
            $mesLabel = $mesesLabels[$mesNum] . '/' . substr($mesChave, 2, 2);

            if (!isset($porAno[$ano])) {
                $porAno[$ano] = [];
            }

            if (!isset($porAno[$ano][$mesLabel])) {
                $porAno[$ano][$mesLabel] = [
                    'mes_numero' => (int)$mesNum,
                    1 => 0,
                    2 => 0,
                    3 => 0,
                    4 => 0,
                    5 => 0,
                ];
            }

            foreach ($registros as $registro) {
                $porAno[$ano][$mesLabel][$registro->valor]++;
            }
        }

        // Ordenar anos
        ksort($porAno);

        // Ordenar meses dentro de cada ano
        foreach ($porAno as $ano => &$meses) {
            uasort($meses, function ($a, $b) {
                return $a['mes_numero'] <=> $b['mes_numero'];
            });

            // Remover o campo mes_numero após ordenação
            foreach ($meses as $mesLabel => &$dados) {
                unset($dados['mes_numero']);
            }
        }

        return $porAno;
    }

    /**
     * Gerar matriz agrupada por Empresa Terceirizada → Ano → Mês → Nota
     */
    private function gerarMatrizPorEmpresaTerceirizada($feedbacks): array
    {
        $resultado = [];

        $mesesLabels = [
            '01' => 'Janeiro',
            '02' => 'Fevereiro',
            '03' => 'Março',
            '04' => 'Abril',
            '05' => 'Maio',
            '06' => 'Junho',
            '07' => 'Julho',
            '08' => 'Agosto',
            '09' => 'Setembro',
            '10' => 'Outubro',
            '11' => 'Novembro',
            '12' => 'Dezembro',
        ];

        $porEmpresa = $feedbacks->groupBy(fn($f) => $f->pedido->empresaContratada->id ?? null);

        foreach ($porEmpresa as $empresaId => $feedbacksEmpresa) {

            $empresa = \App\Models\EmpresaContratada::find($empresaId);

            if (!$empresa) {
                continue;
            }

            $totalAvaliacoes = $feedbacksEmpresa->count();
            $avaliacoesPositivas = $feedbacksEmpresa->where('valor', '>=', 3)->count();

            $totalPedidos = \App\Models\Pedido::where('empresa_contratada_id', $empresaId)->count();

            $percentual = $totalPedidos > 0
                ? round(($avaliacoesPositivas / $totalPedidos) * 100, 2)
                : 0;

            $resultado[$empresaId] = [
                'empresa' => $empresa,
                'percentual' => $percentual,
                'anos' => [],
            ];

            foreach ($feedbacksEmpresa as $feedback) {

                $ano = $feedback->created_at->format('Y');
                $mesNumero = $feedback->created_at->format('m');
                $mesLabel = $mesesLabels[$mesNumero];

                if (!isset($resultado[$empresaId]['anos'][$ano][$mesLabel])) {
                    $resultado[$empresaId]['anos'][$ano][$mesLabel] = [
                        'mes_numero' => (int) $mesNumero,
                        1 => 0,
                        2 => 0,
                        3 => 0,
                        4 => 0,
                        5 => 0,
                    ];
                }

                $resultado[$empresaId]['anos'][$ano][$mesLabel][$feedback->valor]++;
            }
        }

        foreach ($resultado[$empresaId]['anos'] as $ano => &$meses) {

            uasort($meses, function ($a, $b) {
                return $a['mes_numero'] <=> $b['mes_numero'];
            });

            foreach ($meses as &$dados) {
                unset($dados['mes_numero']);
            }
        }

        return $resultado;
    }
}
