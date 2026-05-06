<?php

namespace App\Services\Relatorios;

use App\Models\Escola;
use App\Models\TipoManutencao;
use App\Models\TipoStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PedidoRelatorioGeralService
{
    public function __construct(
        protected RelatorioPdfRenderer $renderer,
    ) {}

    /**
     * Gera o PDF do relatorio analitico com insights e listagem.
     */
    public function gerar(array $filtros, User $usuario): Response
    {
        $reportFilters = $this->formatarFiltros($filtros);

        return $this->renderer->download('relatorios.Manutencao.geral-pedidos', [
            'metricas' => $this->calcularMetricas($filtros),
            'porStatus' => $this->agruparPorStatus($filtros),
            'porPrioridade' => $this->agruparPorPrioridade($filtros),
            'porTipo' => $this->agruparPorTipo($filtros),
            'porEscola' => $this->agruparPorEscola($filtros),
            'porMes' => $this->evolucaoMensal($filtros),
            'metricaFeedback' => $this->metricasFeedback($filtros),
            'pedidos' => $this->buscarPedidosLeve($filtros),
            'filtros' => $reportFilters,
            'reportFilters' => $reportFilters,
            'reportTitle' => 'Relatorio Analitico de Pedidos de Manutencao',
            'reportSubtitle' => 'Visao consolidada com indicadores e listagem detalhada',
            'usuarioExportacao' => $usuario,
            'dataExportacao' => Carbon::now(),
        ], 'relatorio-pedidos-' . now()->format('Y-m-d_H-i') . '.pdf');
    }

    protected function calcularMetricas(array $filtros): object
    {
        $row = (clone $this->queryBase($filtros))
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN ts.finaliza_pedido = 1 THEN 1 ELSE 0 END) AS concluidos,
                SUM(CASE WHEN ts.cancela_pedido = 1 THEN 1 ELSE 0 END) AS cancelados,
                SUM(CASE WHEN (ts.finaliza_pedido IS NULL OR ts.finaliza_pedido = 0)
                           AND (ts.cancela_pedido IS NULL OR ts.cancela_pedido = 0)
                          THEN 1 ELSE 0 END) AS abertos,
                SUM(CASE WHEN p.data_entrega IS NOT NULL AND p.data_prevista IS NOT NULL
                           AND p.data_entrega <= p.data_prevista THEN 1 ELSE 0 END) AS no_prazo,
                SUM(CASE WHEN p.data_entrega IS NOT NULL AND p.data_prevista IS NOT NULL
                           AND p.data_entrega > p.data_prevista THEN 1 ELSE 0 END) AS atrasados,
                SUM(CASE WHEN p.data_entrega IS NULL AND p.data_prevista IS NOT NULL
                           AND p.data_prevista < NOW() THEN 1 ELSE 0 END) AS vencidos,
                ROUND(AVG(CASE WHEN p.data_entrega IS NOT NULL AND p.data_solicitacao IS NOT NULL
                    THEN DATEDIFF(p.data_entrega, p.data_solicitacao) END), 1) AS tempo_medio
            ')
            ->leftJoin('tipo_status as ts', 'ts.id', '=', 'p.tipo_status_id')
            ->first();

        $total = (int) ($row->total ?? 0);
        $concluidos = (int) ($row->concluidos ?? 0);
        $noPrazo = (int) ($row->no_prazo ?? 0);
        $atrasados = (int) ($row->atrasados ?? 0);

        return (object) [
            'total' => $total,
            'concluidos' => $concluidos,
            'cancelados' => (int) ($row->cancelados ?? 0),
            'abertos' => (int) ($row->abertos ?? 0),
            'taxa_conclusao' => $total > 0 ? round(($concluidos / $total) * 100, 1) : 0,
            'no_prazo' => $noPrazo,
            'atrasados' => $atrasados,
            'vencidos' => (int) ($row->vencidos ?? 0),
            'taxa_prazo' => ($noPrazo + $atrasados) > 0
                ? round(($noPrazo / ($noPrazo + $atrasados)) * 100, 1)
                : 0,
            'tempo_medio' => $row->tempo_medio,
        ];
    }

    protected function agruparPorStatus(array $filtros): Collection
    {
        return (clone $this->queryBase($filtros))
            ->selectRaw('ts.nome, ts.cor, COUNT(*) as total')
            ->leftJoin('tipo_status as ts', 'ts.id', '=', 'p.tipo_status_id')
            ->groupBy('ts.id', 'ts.nome', 'ts.cor')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => (object) [
                'nome' => $r->nome ?? 'Sem Status',
                'cor' => '#' . ltrim($r->cor ?? '6b7280', '#'),
                'total' => (int) $r->total,
            ]);
    }

    protected function agruparPorPrioridade(array $filtros): Collection
    {
        return (clone $this->queryBase($filtros))
            ->selectRaw('p.nivel_prioridade as prioridade, COUNT(*) as total')
            ->groupBy('p.nivel_prioridade')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => (object) [
                'prioridade' => $r->prioridade ?? 'Indeterminado',
                'total' => (int) $r->total,
            ]);
    }

    protected function agruparPorTipo(array $filtros): Collection
    {
        return (clone $this->queryBase($filtros))
            ->selectRaw('
                tm.nome,
                COUNT(*) as total,
                SUM(CASE WHEN ts.finaliza_pedido = 1 THEN 1 ELSE 0 END) as concluidos
            ')
            ->leftJoin('tipo_manutencao as tm', 'tm.id', '=', 'p.tipo_manutencao_id')
            ->leftJoin('tipo_status as ts', 'ts.id', '=', 'p.tipo_status_id')
            ->groupBy('tm.id', 'tm.nome')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($r) => (object) [
                'nome' => $r->nome ?? 'Nao Informado',
                'total' => (int) $r->total,
                'concluidos' => (int) $r->concluidos,
                'taxa' => $r->total > 0 ? round(($r->concluidos / $r->total) * 100) : 0,
            ]);
    }

    protected function agruparPorEscola(array $filtros): Collection
    {
        $rows = (clone $this->queryBase($filtros))
            ->selectRaw('e.nome, COUNT(*) as total')
            ->leftJoin('escolas as e', 'e.id', '=', 'p.escola_id')
            ->groupBy('e.id', 'e.nome')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $max = $rows->max('total') ?: 1;

        return $rows->map(fn ($r) => (object) [
            'nome' => $r->nome ?? 'Nao Informada',
            'total' => (int) $r->total,
            'pct_bar' => round(($r->total / $max) * 100),
        ]);
    }

    protected function evolucaoMensal(array $filtros): Collection
    {
        $rows = (clone $this->queryBase($filtros))
            ->selectRaw("DATE_FORMAT(p.data_solicitacao, '%Y-%m') as mes, COUNT(*) as total")
            ->groupByRaw("DATE_FORMAT(p.data_solicitacao, '%Y-%m')")
            ->orderByRaw("DATE_FORMAT(p.data_solicitacao, '%Y-%m')")
            ->get();

        $max = $rows->max('total') ?: 1;

        return $rows->map(fn ($r) => (object) [
            'mes' => Carbon::createFromFormat('Y-m', $r->mes)->format('m/Y'),
            'total' => (int) $r->total,
            'pct_bar' => round(($r->total / $max) * 100),
        ]);
    }

    protected function metricasFeedback(array $filtros): object
    {
        $tabela = (new \App\Models\FeedbackPedido())->getTable();

        try {
            $pedidoIds = $this->queryBase($filtros)->select('p.id');

            $r = DB::table("{$tabela} as f")
                ->whereIn('f.pedido_id', $pedidoIds)
                ->selectRaw('
                    COUNT(*) AS total,
                    ROUND(AVG(f.valor), 2) AS media,
                    SUM(CASE WHEN f.valor >= 4 THEN 1 ELSE 0 END) AS satisfeitos
                ')
                ->first();

            $total = (int) ($r->total ?? 0);
            $satisfeitos = (int) ($r->satisfeitos ?? 0);

            return (object) [
                'total' => $total,
                'media' => $r->media ?? null,
                'satisfeitos' => $satisfeitos,
                'tx_satisfacao' => $total > 0 ? round(($satisfeitos / $total) * 100, 1) : null,
            ];
        } catch (\Throwable) {
            return (object) [
                'total' => 0,
                'media' => null,
                'satisfeitos' => 0,
                'tx_satisfacao' => null,
            ];
        }
    }

    protected function buscarPedidosLeve(array $filtros): Collection
    {
        return $this->queryBase($filtros)
            ->select([
                'p.id',
                'p.numero_protocolo',
                'p.nivel_prioridade',
                'p.data_solicitacao',
                'p.data_identificacao_problema',
                'p.data_prevista',
                'p.data_entrega',
                'p.is_pedido_adicional',
                'e.nome as escola_nome',
                'tm.nome as tipo_manutencao_nome',
                'ts.nome as status_nome',
                'ts.cor as status_cor',
                'ec.nome as empresa_nome',
                'principal.numero_protocolo as pedido_principal_protocolo',
                DB::raw("(SELECT GROUP_CONCAT(pp.texto_problema SEPARATOR ', ') FROM pedido_problemas pp WHERE pp.pedido_id = p.id) as problemas"),
                DB::raw("(SELECT COUNT(*) FROM pedidos pa WHERE pa.pedido_principal_id = p.id AND pa.ativo = 1) as adicionais_count"),
                DB::raw("(SELECT GROUP_CONCAT(CONCAT(ppf.texto_problema, ': ', fpi.valor, '/5 - ', fpi.resultado) SEPARATOR ' | ') FROM feedback_pedido_itens fpi LEFT JOIN pedido_problemas ppf ON ppf.id = fpi.pedido_problema_id WHERE fpi.pedido_id = p.id) as resultados_feedback"),
            ])
            ->leftJoin('escolas as e', 'e.id', '=', 'p.escola_id')
            ->leftJoin('tipo_manutencao as tm', 'tm.id', '=', 'p.tipo_manutencao_id')
            ->leftJoin('tipo_status as ts', 'ts.id', '=', 'p.tipo_status_id')
            ->leftJoin('empresas_contratadas as ec', 'ec.id', '=', 'p.empresa_contratada_id')
            ->leftJoin('pedidos as principal', 'principal.id', '=', 'p.pedido_principal_id')
            ->orderByDesc('p.data_solicitacao')
            ->get();
    }

    protected function queryBase(array $filtros): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('pedidos as p')->where('p.ativo', true);

        if (! empty($filtros['data_inicio'])) {
            $q->whereDate('p.data_solicitacao', '>=', $filtros['data_inicio']);
        }

        if (! empty($filtros['data_fim'])) {
            $q->whereDate('p.data_solicitacao', '<=', $filtros['data_fim']);
        }

        if (! empty($filtros['escola_id'])) {
            $q->where('p.escola_id', $filtros['escola_id']);
        }

        if (! empty($filtros['tipo_status_id'])) {
            $q->where('p.tipo_status_id', $filtros['tipo_status_id']);
        }

        if (! empty($filtros['tipo_manutencao_id'])) {
            $q->where('p.tipo_manutencao_id', $filtros['tipo_manutencao_id']);
        }

        if (! empty($filtros['nivel_prioridade'])) {
            $q->where('p.nivel_prioridade', $filtros['nivel_prioridade']);
        }

        if (! empty($filtros['tipo_registro'])) {
            match ($filtros['tipo_registro']) {
                'principais' => $q->where('p.is_pedido_adicional', false),
                'adicionais' => $q->where('p.is_pedido_adicional', true),
                default => null,
            };
        }

        return $q;
    }

    protected function formatarFiltros(array $filtros): array
    {
        $r = [];

        if (! empty($filtros['data_inicio']) || ! empty($filtros['data_fim'])) {
            $de = ! empty($filtros['data_inicio']) ? Carbon::parse($filtros['data_inicio'])->format('d/m/Y') : 'Inicio';
            $ate = ! empty($filtros['data_fim']) ? Carbon::parse($filtros['data_fim'])->format('d/m/Y') : 'Atual';
            $r['periodo'] = "{$de} a {$ate}";
        }

        if (! empty($filtros['escola_id'])) {
            $r['escola'] = Escola::find($filtros['escola_id'])?->nome ?? 'N/A';
        }

        if (! empty($filtros['tipo_status_id'])) {
            $r['status'] = TipoStatus::find($filtros['tipo_status_id'])?->nome ?? 'N/A';
        }

        if (! empty($filtros['tipo_manutencao_id'])) {
            $r['tipo'] = TipoManutencao::find($filtros['tipo_manutencao_id'])?->nome ?? 'N/A';
        }

        if (! empty($filtros['tipo_registro'])) {
            $r['tipo_registro'] = match ($filtros['tipo_registro']) {
                'principais' => 'Pedidos principais',
                'adicionais' => 'Pedidos adicionais',
                default => 'Todos os registros',
            };
        }

        return $r;
    }
}
