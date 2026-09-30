<?php

namespace App\Services\Relatorios;

use App\Models\Escola;
use App\Models\TipoManutencao;
use App\Models\TipoStatus;
use App\Models\User;
use App\Services\PedidoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PedidoRelatorioGeralService
{
    public function __construct(
        protected RelatorioPdfRenderer $renderer,
        protected PedidoService $pedidoService,
    ) {}

    /** Gera somente o PDF analítico; a listagem é processada separadamente. */
    public function gerar(array $filtros, User $usuario): Response
    {
        $reportFilters = $this->formatarFiltros($filtros);

        return $this->renderer->download('relatorios.Manutencao.geral-pedidos', [
            'metricas' => $this->calcularMetricas($filtros, $usuario),
            'porStatus' => $this->agruparPorStatus($filtros, $usuario),
            'porPrioridade' => $this->agruparPorPrioridade($filtros, $usuario),
            'porTipo' => $this->agruparPorTipo($filtros, $usuario),
            'porEscola' => $this->agruparPorEscola($filtros, $usuario),
            'porEmpresa' => $this->agruparPorEmpresa($filtros, $usuario),
            'porMes' => $this->evolucaoMensal($filtros, $usuario),
            'metricaFeedback' => $this->metricasFeedback($filtros, $usuario),
            'filtros' => $reportFilters,
            'reportFilters' => $reportFilters,
            'reportTitle' => 'Relatório Analítico de Pedidos de Manutenção',
            'reportSubtitle' => 'Visão consolidada com indicadores e agrupamentos',
            'usuarioExportacao' => $usuario,
            'dataExportacao' => Carbon::now(),
        ], 'relatório-pedidos-' . now()->format('Y-m-d_H-i') . '.pdf');
    }

    public function firstPedidoDate(User $usuario): ?string
    {
        $date = (clone $this->queryBase([], $usuario))->min('p.data_solicitacao');

        return $date ? Carbon::parse($date)->toDateString() : null;
    }

    protected function calcularMetricas(array $filtros, User $usuario): object
    {
        $row = (clone $this->queryBase($filtros, $usuario))
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

    protected function agruparPorStatus(array $filtros, User $usuario): Collection
    {
        return (clone $this->queryBase($filtros, $usuario))
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

    protected function agruparPorPrioridade(array $filtros, User $usuario): Collection
    {
        return (clone $this->queryBase($filtros, $usuario))
            ->selectRaw('p.nivel_prioridade as prioridade, COUNT(*) as total')
            ->groupBy('p.nivel_prioridade')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => (object) [
                'prioridade' => $r->prioridade ?? 'Indeterminado',
                'total' => (int) $r->total,
            ]);
    }

    protected function agruparPorTipo(array $filtros, User $usuario): Collection
    {
        return (clone $this->queryBase($filtros, $usuario))
            ->selectRaw('
                tm.nome,
                COUNT(*) as total,
                SUM(CASE WHEN ts.finaliza_pedido = 1 THEN 1 ELSE 0 END) as concluidos
            ')
            ->leftJoin('tipo_manutencao as tm', 'tm.id', '=', 'p.tipo_manutencao_id')
            ->leftJoin('tipo_status as ts', 'ts.id', '=', 'p.tipo_status_id')
            ->groupBy('tm.id', 'tm.nome')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => (object) [
                'nome' => $r->nome ?? 'Não Informado',
                'total' => (int) $r->total,
                'concluidos' => (int) $r->concluidos,
                'taxa' => $r->total > 0 ? round(($r->concluidos / $r->total) * 100) : 0,
            ]);
    }

    protected function agruparPorEscola(array $filtros, User $usuario): Collection
    {
        $rows = (clone $this->queryBase($filtros, $usuario))
            ->selectRaw('e.nome, COUNT(*) as total')
            ->leftJoin('escolas as e', 'e.id', '=', 'p.escola_id')
            ->groupBy('e.id', 'e.nome')
            ->orderByDesc('total')
            ->get();

        $max = $rows->max('total') ?: 1;

        return $rows->map(fn ($r) => (object) [
            'nome' => $r->nome ?? 'Não Informada',
            'total' => (int) $r->total,
            'pct_bar' => round(($r->total / $max) * 100),
        ]);
    }

    protected function agruparPorEmpresa(array $filtros, User $usuario): Collection
    {
        $rows = (clone $this->queryBase($filtros, $usuario))
            ->selectRaw('
                ec.nome,
                COUNT(*) as total,
                SUM(CASE WHEN ts.finaliza_pedido = 1 THEN 1 ELSE 0 END) as concluidos
            ')
            ->leftJoin('empresas_contratadas as ec', 'ec.id', '=', 'p.empresa_contratada_id')
            ->leftJoin('tipo_status as ts', 'ts.id', '=', 'p.tipo_status_id')
            ->groupBy('ec.id', 'ec.nome')
            ->orderByDesc('total')
            ->get();

        return $rows->map(fn ($r) => (object) [
            'nome' => $r->nome ?? 'Sem empresa',
            'total' => (int) $r->total,
            'concluidos' => (int) $r->concluidos,
            'taxa' => $r->total > 0 ? round(((int) $r->concluidos / (int) $r->total) * 100) : 0,
        ]);
    }

    protected function evolucaoMensal(array $filtros, User $usuario): Collection
    {
        $rows = (clone $this->queryBase($filtros, $usuario))
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

    protected function metricasFeedback(array $filtros, User $usuario): object
    {
        $tabela = (new \App\Models\FeedbackPedido())->getTable();

        try {
            $pedidoIds = $this->queryBase($filtros, $usuario)->select('p.id');

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

    public function queryListagem(array $filtros, User $usuario): QueryBuilder
    {
        return $this->queryBase($filtros, $usuario)
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
            ->orderBy('p.id');
    }

    /** @param object $pedido */
    public function mapPedidoListagem(object $pedido): array
    {
        return [
            'numero_protocolo' => $pedido->numero_protocolo,
            'nivel_prioridade' => $pedido->nivel_prioridade,
            'data_solicitacao' => $pedido->data_solicitacao,
            'data_identificacao_problema' => $pedido->data_identificacao_problema,
            'data_prevista' => $pedido->data_prevista,
            'data_entrega' => $pedido->data_entrega,
            'is_pedido_adicional' => (bool) $pedido->is_pedido_adicional,
            'escola_nome' => $pedido->escola_nome,
            'tipo_manutencao_nome' => $pedido->tipo_manutencao_nome,
            'status_nome' => $pedido->status_nome,
            'status_cor' => $pedido->status_cor,
            'empresa_nome' => $pedido->empresa_nome,
            'pedido_principal_protocolo' => $pedido->pedido_principal_protocolo,
            'adicionais_count' => (int) ($pedido->adicionais_count ?? 0),
            'problemas' => $pedido->problemas,
            'resultados_feedback' => $pedido->resultados_feedback,
        ];
    }

    protected function queryBase(array $filtros, User $usuario): QueryBuilder
    {
        $q = DB::table('pedidos as p')->where('p.ativo', true);

        $this->pedidoService->aplicarEscopoConsulta(
            $q,
            $usuario,
            'p.escola_id',
            'p.setor_id',
            'p.setor_origem_id'
        );

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

    public function formatarFiltros(array $filtros): array
    {
        $r = [];

        if (! empty($filtros['data_inicio']) || ! empty($filtros['data_fim'])) {
            $de = ! empty($filtros['data_inicio']) ? Carbon::parse($filtros['data_inicio'])->format('d/m/Y') : 'Início';
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
