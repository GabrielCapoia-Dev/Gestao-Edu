<?php

namespace App\Services\Relatorios;

use App\Models\Pedido;
use App\Models\TipoStatus;
use App\Models\TipoManutencao;
use App\Models\Escola;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
// Builder não usado — queryBase usa DB::table puro para evitar casts do Eloquent
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PedidoRelatorioGeralService
{
    /**
     * Gera o PDF do relatório analítico com insights + listagem.
     */
    public function gerar(array $filtros = [], User $usuario): Response
    {
        $pdf = Pdf::loadView('relatorios.manutencao.geral-pedidos', [
            // Métricas agregadas via SQL — sem carregar registros na memória
            'metricas'         => $this->calcularMetricas($filtros),
            'porStatus'        => $this->agruparPorStatus($filtros),
            'porPrioridade'    => $this->agruparPorPrioridade($filtros),
            'porTipo'          => $this->agruparPorTipo($filtros),
            'porEscola'        => $this->agruparPorEscola($filtros),
            'porMes'           => $this->evolucaoMensal($filtros),
            'metricaFeedback'  => $this->metricasFeedback($filtros),

            // Listagem leve: JOIN direto, só colunas visíveis, sem eager loading
            'pedidos'          => $this->buscarPedidosLeve($filtros),

            // Metadados
            'filtros'          => $this->formatarFiltros($filtros),
            'usuarioExportacao' => $usuario,
            'dataExportacao'   => Carbon::now(),
        ])
        ->setPaper('a4', 'portrait')
        ->setOptions([
            'dpi'                     => 96,   // 150 consome ~2.5x mais memória por imagem
            'defaultFont'             => 'DejaVu Sans',
            'isRemoteEnabled'         => false, // desabilita fetch HTTP de recursos externos
            'isHtml5ParserEnabled'    => true,
            'isFontSubsettingEnabled' => true,
            'isPhpEnabled'            => false,
            'chroot'                  => public_path(),
        ]);

        return $pdf->download('relatorio-pedidos-' . now()->format('Y-m-d_H-i') . '.pdf');
    }

    // =========================================================================
    // AGREGAÇÕES VIA SQL
    // =========================================================================

    /**
     * Métricas gerais em uma única query: totais, taxas, prazo e tempo médio.
     */
    protected function calcularMetricas(array $filtros): object
    {
        $row = (clone $this->queryBase($filtros))
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN ts.finaliza_pedido = 1 THEN 1 ELSE 0 END)  AS concluidos,
                SUM(CASE WHEN ts.cancela_pedido  = 1 THEN 1 ELSE 0 END)  AS cancelados,
                SUM(CASE WHEN (ts.finaliza_pedido IS NULL OR ts.finaliza_pedido = 0)
                           AND (ts.cancela_pedido  IS NULL OR ts.cancela_pedido  = 0)
                          THEN 1 ELSE 0 END) AS abertos,
                SUM(CASE WHEN p.data_entrega IS NOT NULL AND p.data_prevista IS NOT NULL
                           AND p.data_entrega <= p.data_prevista THEN 1 ELSE 0 END) AS no_prazo,
                SUM(CASE WHEN p.data_entrega IS NOT NULL AND p.data_prevista IS NOT NULL
                           AND p.data_entrega >  p.data_prevista THEN 1 ELSE 0 END) AS atrasados,
                SUM(CASE WHEN p.data_entrega IS NULL AND p.data_prevista IS NOT NULL
                           AND p.data_prevista < NOW()           THEN 1 ELSE 0 END) AS vencidos,
                ROUND(AVG(CASE WHEN p.data_entrega IS NOT NULL AND p.data_solicitacao IS NOT NULL
                    THEN DATEDIFF(p.data_entrega, p.data_solicitacao) END), 1)      AS tempo_medio
            ')
            ->leftJoin('tipo_status as ts', 'ts.id', '=', 'p.tipo_status_id')
            ->first();

        $total      = (int) ($row->total      ?? 0);
        $concluidos = (int) ($row->concluidos ?? 0);
        $noPrazo    = (int) ($row->no_prazo   ?? 0);
        $atrasados  = (int) ($row->atrasados  ?? 0);

        return (object) [
            'total'          => $total,
            'concluidos'     => $concluidos,
            'cancelados'     => (int) ($row->cancelados ?? 0),
            'abertos'        => (int) ($row->abertos    ?? 0),
            'taxa_conclusao' => $total > 0 ? round(($concluidos / $total) * 100, 1) : 0,
            'no_prazo'       => $noPrazo,
            'atrasados'      => $atrasados,
            'vencidos'       => (int) ($row->vencidos   ?? 0),
            'taxa_prazo'     => ($noPrazo + $atrasados) > 0
                                    ? round(($noPrazo / ($noPrazo + $atrasados)) * 100, 1)
                                    : 0,
            'tempo_medio'    => $row->tempo_medio,
        ];
    }

    /**
     * Distribuição por status com cor.
     */
    protected function agruparPorStatus(array $filtros): Collection
    {
        return (clone $this->queryBase($filtros))
            ->selectRaw('ts.nome, ts.cor, COUNT(*) as total')
            ->leftJoin('tipo_status as ts', 'ts.id', '=', 'p.tipo_status_id')
            ->groupBy('ts.id', 'ts.nome', 'ts.cor')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => (object) [
                'nome'  => $r->nome  ?? 'Sem Status',
                'cor'   => '#' . ltrim($r->cor ?? '6b7280', '#'),
                'total' => (int) $r->total,
            ]);
    }

    /**
     * Distribuição por prioridade.
     */
    protected function agruparPorPrioridade(array $filtros): Collection
    {
        return (clone $this->queryBase($filtros))
            ->selectRaw('p.nivel_prioridade as prioridade, COUNT(*) as total')
            ->groupBy('p.nivel_prioridade')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => (object) [
                'prioridade' => $r->prioridade ?? 'Indeterminado',
                'total'      => (int) $r->total,
            ]);
    }

    /**
     * Top 8 tipos de manutenção com taxa de conclusão por tipo.
     */
    protected function agruparPorTipo(array $filtros): Collection
    {
        return (clone $this->queryBase($filtros))
            ->selectRaw('
                tm.nome,
                COUNT(*) as total,
                SUM(CASE WHEN ts.finaliza_pedido = 1 THEN 1 ELSE 0 END) as concluidos
            ')
            ->leftJoin('tipo_manutencao as tm', 'tm.id', '=', 'p.tipo_manutencao_id')
            ->leftJoin('tipo_status as ts',    'ts.id', '=', 'p.tipo_status_id')
            ->groupBy('tm.id', 'tm.nome')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn($r) => (object) [
                'nome'       => $r->nome ?? 'Não Informado',
                'total'      => (int) $r->total,
                'concluidos' => (int) $r->concluidos,
                'taxa'       => $r->total > 0 ? round(($r->concluidos / $r->total) * 100) : 0,
            ]);
    }

    /**
     * Top 10 escolas com percentual relativo ao maior volume.
     */
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

        return $rows->map(fn($r) => (object) [
            'nome'    => $r->nome ?? 'Não Informada',
            'total'   => (int) $r->total,
            'pct_bar' => round(($r->total / $max) * 100),
        ]);
    }

    /**
     * Evolução mensal de pedidos abertos.
     */
    protected function evolucaoMensal(array $filtros): Collection
    {
        $rows = (clone $this->queryBase($filtros))
            ->selectRaw("DATE_FORMAT(p.data_solicitacao, '%Y-%m') as mes, COUNT(*) as total")
            ->groupByRaw("DATE_FORMAT(p.data_solicitacao, '%Y-%m')")
            ->orderByRaw("DATE_FORMAT(p.data_solicitacao, '%Y-%m')")
            ->get();

        $max = $rows->max('total') ?: 1;

        return $rows->map(fn($r) => (object) [
            'mes'     => \Carbon\Carbon::createFromFormat('Y-m', $r->mes)->format('m/Y'),
            'total'   => (int) $r->total,
            'pct_bar' => round(($r->total / $max) * 100),
        ]);
    }

    /**
     * Métricas de feedback (média, satisfação ≥ 4) via subquery.
     */
    protected function metricasFeedback(array $filtros): object
    {
        // Nome real da tabela definido no model FeedbackPedido ($table)
        // Ajuste a constante abaixo se necessário
        $tabela = (new \App\Models\FeedbackPedido())->getTable();

        try {
            $pedidoIds = $this->queryBase($filtros)->select('p.id');

            $r = DB::table("{$tabela} as f")
                ->whereIn('f.pedido_id', $pedidoIds)
                ->selectRaw('
                    COUNT(*)                                         AS total,
                    ROUND(AVG(f.valor), 2)                           AS media,
                    SUM(CASE WHEN f.valor >= 4 THEN 1 ELSE 0 END)   AS satisfeitos
                ')
                ->first();

            $total     = (int) ($r->total      ?? 0);
            $satisfeit = (int) ($r->satisfeitos ?? 0);

            return (object) [
                'total'         => $total,
                'media'         => $r->media ?? null,
                'satisfeitos'   => $satisfeit,
                'tx_satisfacao' => $total > 0 ? round(($satisfeit / $total) * 100, 1) : null,
            ];
        } catch (\Throwable $e) {
            // Tabela não encontrada ou erro — retorna vazio sem quebrar o relatório
            return (object) [
                'total'         => 0,
                'media'         => null,
                'satisfeitos'   => 0,
                'tx_satisfacao' => null,
            ];
        }
    }

    // =========================================================================
    // LISTAGEM LEVE
    // =========================================================================

    /**
     * Retorna apenas as colunas visíveis na tabela de listagem usando JOIN direto.
     * Evita carregar modelos Eloquent completos e relacionamentos na memória.
     */
    protected function buscarPedidosLeve(array $filtros): Collection
    {
        return $this->queryBase($filtros)
            ->select([
                'p.id',
                'p.numero_protocolo',
                'p.nivel_prioridade',
                'p.data_solicitacao',
                'p.data_prevista',
                'p.data_entrega',
                'e.nome  as escola_nome',
                'tm.nome as tipo_manutencao_nome',
                'ts.nome as status_nome',
                'ts.cor  as status_cor',
                'ec.nome as empresa_nome',
            ])
            ->leftJoin('escolas as e',                   'e.id',  '=', 'p.escola_id')
            ->leftJoin('tipo_manutencao as tm',          'tm.id', '=', 'p.tipo_manutencao_id')
            ->leftJoin('tipo_status as ts',              'ts.id', '=', 'p.tipo_status_id')
            ->leftJoin('empresas_contratadas as ec',     'ec.id', '=', 'p.empresa_contratada_id')
            ->orderByDesc('p.data_solicitacao')
            ->get();
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Query base com alias "p" e filtros aplicados.
     * Todas as agregações partem daqui via (clone $this->queryBase($filtros)).
     */
    protected function queryBase(array $filtros): \Illuminate\Database\Query\Builder
    {
        // DB::table ignora os $casts do Eloquent — retorna stdClass com valores primitivos
        $q = DB::table('pedidos as p')->where('p.ativo', true);

        if (!empty($filtros['data_inicio'])) {
            $q->whereDate('p.data_solicitacao', '>=', $filtros['data_inicio']);
        }
        if (!empty($filtros['data_fim'])) {
            $q->whereDate('p.data_solicitacao', '<=', $filtros['data_fim']);
        }
        if (!empty($filtros['escola_id'])) {
            $q->where('p.escola_id', $filtros['escola_id']);
        }
        if (!empty($filtros['tipo_status_id'])) {
            $q->where('p.tipo_status_id', $filtros['tipo_status_id']);
        }
        if (!empty($filtros['tipo_manutencao_id'])) {
            $q->where('p.tipo_manutencao_id', $filtros['tipo_manutencao_id']);
        }
        if (!empty($filtros['nivel_prioridade'])) {
            $q->where('p.nivel_prioridade', $filtros['nivel_prioridade']);
        }

        return $q;
    }

    /**
     * Formata os filtros para exibição no cabeçalho do PDF.
     */
    protected function formatarFiltros(array $filtros): array
    {
        $r = [];

        if (!empty($filtros['data_inicio']) || !empty($filtros['data_fim'])) {
            $de  = !empty($filtros['data_inicio']) ? Carbon::parse($filtros['data_inicio'])->format('d/m/Y') : 'Início';
            $ate = !empty($filtros['data_fim'])    ? Carbon::parse($filtros['data_fim'])->format('d/m/Y')    : 'Atual';
            $r['periodo'] = "{$de} a {$ate}";
        }
        if (!empty($filtros['escola_id'])) {
            $r['escola'] = Escola::find($filtros['escola_id'])?->nome ?? 'N/A';
        }
        if (!empty($filtros['tipo_status_id'])) {
            $r['status'] = TipoStatus::find($filtros['tipo_status_id'])?->nome ?? 'N/A';
        }
        if (!empty($filtros['tipo_manutencao_id'])) {
            $r['tipo'] = TipoManutencao::find($filtros['tipo_manutencao_id'])?->nome ?? 'N/A';
        }

        return $r;
    }
}