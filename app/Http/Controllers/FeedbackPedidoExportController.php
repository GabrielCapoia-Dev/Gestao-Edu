<?php

namespace App\Http\Controllers;

use App\Models\EmpresaContratada;
use App\Models\Escola;
use App\Models\FeedbackPedido;
use App\Models\TipoManutencao;
use App\Services\Relatorios\ChartRenderService;
use App\Services\Relatorios\FeedbackGraficoService;
use App\Services\Relatorios\FeedbackPedidoRelatorioService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FeedbackPedidoExportController extends Controller
{
    public function exportarGeral(Request $request)
    {
        return $this->gerarPDF($request, 'geral');
    }

    public function exportarListagem(Request $request)
    {
        return $this->gerarPDF($request, 'listagem');
    }

    public function exportarGraficos(Request $request)
    {
        return $this->gerarPDF($request, 'graficos');
    }

    public function exportarTerceirizada(Request $request)
    {
        return $this->gerarPDF($request, 'terceirizada');
    }

    private function gerarPDF(Request $request, string $tipo)
    {
        ini_set('memory_limit', '512M');

        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            if (! $user->hasPermissionTo('Visualizar Feedback de Pedidos')) {
                return abort(403, 'Sem permissao para exportar este relatorio');
            }

            $filters = $this->coletarFiltros($request);
            $formattedFilters = $this->formatarFiltros($filters);

            $query = FeedbackPedido::with([
                'pedido.escola',
                'pedido.tipoManutencao',
            ]);

            $this->aplicarFiltros($query, $filters);

            $feedbacks = $query->orderBy('created_at', 'desc')->get();

            $total = $feedbacks->count();
            $mediaGeral = $total > 0 ? round($feedbacks->avg('valor'), 2) : 0;
            $percentualSatisfacao = $total > 0
                ? round(($feedbacks->where('valor', '>=', 3)->count() / $total) * 100)
                : 0;

            $graficoMediaMensal = null;
            $graficoPorNota = null;

            if ($tipo !== 'listagem') {
                $graficoService = app(FeedbackGraficoService::class);
                $chartRender = app(ChartRenderService::class);

                $dadosMediaMensal = $graficoService->gerarDadosMediaMensal($filters);
                $dadosPorNota = $graficoService->gerarDadosPorNota($filters);

                $configMediaMensal = $graficoService->gerarChartConfig('media_mensal', $dadosMediaMensal);
                $configPorNota = $graficoService->gerarChartConfig('por_nota', $dadosPorNota);

                $graficoMediaMensal = $chartRender->renderizarGrafico($configMediaMensal, 700, 350);
                $graficoPorNota = $chartRender->renderizarGrafico($configPorNota, 700, 350);

                if (! $graficoMediaMensal) {
                    $graficoMediaMensal = $chartRender->renderizarGraficoLocal($configMediaMensal);
                }

                if (! $graficoPorNota) {
                    $graficoPorNota = $chartRender->renderizarGraficoLocal($configPorNota);
                }
            }

            $matrizesAgrupadas = [];

            if (in_array($tipo, ['geral', 'graficos'], true) && ! $feedbacks->isEmpty()) {
                $matrizesAgrupadas = $this->gerarMatrizNotasPorMes($feedbacks);
            }

            $matrizesEmpresa = [];

            if ($tipo === 'terceirizada' && ! $feedbacks->isEmpty()) {
                $matrizesEmpresa = $this->gerarMatrizPorEmpresaTerceirizada($feedbacks);
            }

            return app(FeedbackPedidoRelatorioService::class)->gerarComGraficosEMatriz(
                $mediaGeral,
                $total,
                $percentualSatisfacao,
                $feedbacks,
                $graficoMediaMensal,
                $graficoPorNota,
                [],
                $matrizesAgrupadas,
                $tipo,
                $matrizesEmpresa,
                $formattedFilters
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao exportar PDF de Feedback', [
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

    private function formatarFiltros(array $filters): array
    {
        $resultado = [];

        if (isset($filters['valor'])) {
            $resultado['nota'] = (string) $filters['valor'];
        }

        if (isset($filters['nivel_prioridade'])) {
            $resultado['prioridade'] = (string) $filters['nivel_prioridade'];
        }

        if (isset($filters['tipo_manutencao_id'])) {
            $resultado['tipo'] = TipoManutencao::find($filters['tipo_manutencao_id'])?->nome ?? 'N/A';
        }

        if (isset($filters['escola_id'])) {
            $resultado['escola'] = Escola::find($filters['escola_id'])?->nome ?? 'N/A';
        }

        if (isset($filters['empresa_contratada_id'])) {
            $resultado['empresa'] = EmpresaContratada::find($filters['empresa_contratada_id'])?->nome ?? 'N/A';
        }

        if (isset($filters['mes'])) {
            $mes = str_pad((string) $filters['mes'], 2, '0', STR_PAD_LEFT);
            $resultado['mes'] = [
                '01' => 'Janeiro',
                '02' => 'Fevereiro',
                '03' => 'Marco',
                '04' => 'Abril',
                '05' => 'Maio',
                '06' => 'Junho',
                '07' => 'Julho',
                '08' => 'Agosto',
                '09' => 'Setembro',
                '10' => 'Outubro',
                '11' => 'Novembro',
                '12' => 'Dezembro',
            ][$mes] ?? $mes;
        }

        if (isset($filters['periodo'])) {
            $inicio = ! empty($filters['periodo']['inicio'])
                ? Carbon::parse($filters['periodo']['inicio'])->format('d/m/Y')
                : 'Inicio';
            $fim = ! empty($filters['periodo']['fim'])
                ? Carbon::parse($filters['periodo']['fim'])->format('d/m/Y')
                : 'Atual';

            $resultado['periodo'] = "{$inicio} a {$fim}";
        }

        return $resultado;
    }

    private function aplicarFiltros($query, array $filters): void
    {
        if (isset($filters['valor'])) {
            $query->where('valor', $filters['valor']);
        }

        if (isset($filters['nivel_prioridade'])) {
            $query->whereHas('pedido', fn ($q) => $q->where('nivel_prioridade', $filters['nivel_prioridade']));
        }

        if (isset($filters['tipo_manutencao_id'])) {
            $query->whereHas('pedido', fn ($q) => $q->where('tipo_manutencao_id', $filters['tipo_manutencao_id']));
        }

        if (isset($filters['escola_id'])) {
            $query->whereHas('pedido', fn ($q) => $q->where('escola_id', $filters['escola_id']));
        }

        if (isset($filters['empresa_contratada_id'])) {
            $query->whereHas('pedido', fn ($q) => $q->where('empresa_contratada_id', $filters['empresa_contratada_id']));
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

    private function gerarMatrizNotasPorMes($feedbacks): array
    {
        $porAno = [];

        $mesesLabels = [
            '01' => 'Janeiro',
            '02' => 'Fevereiro',
            '03' => 'Marco',
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

        $feedbacksAgrupados = $feedbacks->groupBy(fn ($item) => $item->created_at->format('Y-m'));

        foreach ($feedbacksAgrupados as $mesChave => $registros) {
            $ano = substr($mesChave, 0, 4);
            $mesNum = substr($mesChave, 5, 2);
            $mesLabel = $mesesLabels[$mesNum] . '/' . substr($mesChave, 2, 2);

            if (! isset($porAno[$ano])) {
                $porAno[$ano] = [];
            }

            if (! isset($porAno[$ano][$mesLabel])) {
                $porAno[$ano][$mesLabel] = [
                    'mes_numero' => (int) $mesNum,
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

        ksort($porAno);

        foreach ($porAno as &$meses) {
            uasort($meses, fn ($a, $b) => $a['mes_numero'] <=> $b['mes_numero']);

            foreach ($meses as &$dados) {
                unset($dados['mes_numero']);
            }
        }

        unset($meses, $dados);

        return $porAno;
    }

    private function gerarMatrizPorEmpresaTerceirizada($feedbacks): array
    {
        $resultado = [];

        $mesesLabels = [
            '01' => 'Janeiro',
            '02' => 'Fevereiro',
            '03' => 'Marco',
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

        $porEmpresa = $feedbacks->groupBy(fn ($f) => $f->pedido->empresaContratada->id ?? null);

        foreach ($porEmpresa as $empresaId => $feedbacksEmpresa) {
            $empresa = EmpresaContratada::find($empresaId);

            if (! $empresa) {
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

                if (! isset($resultado[$empresaId]['anos'][$ano][$mesLabel])) {
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

        foreach ($resultado as &$empresaData) {
            ksort($empresaData['anos']);

            foreach ($empresaData['anos'] as &$meses) {
                uasort($meses, fn ($a, $b) => $a['mes_numero'] <=> $b['mes_numero']);

                foreach ($meses as &$dados) {
                    unset($dados['mes_numero']);
                }
            }
        }

        unset($empresaData, $meses, $dados);

        return $resultado;
    }
}
