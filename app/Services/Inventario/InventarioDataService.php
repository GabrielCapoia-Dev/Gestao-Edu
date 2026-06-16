<?php

namespace App\Services\Inventario;

use App\Models\ContratoItem;
use App\Models\Enums\MotivoBaixa;
use App\Models\Enums\TipoItem;
use App\Models\Enums\TipoMovimentacao;
use App\Models\Inventario;
use App\Models\InventarioBaixa;
use App\Models\InventarioEstoque;
use App\Models\InventarioMovimentacao;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class InventarioDataService
{
    protected ?Collection $precosReferenciaCache = null;

    public function normalizarFiltros(array $filtros = []): array
    {
        $categoria = (string) ($filtros['categoria'] ?? $filtros['abaAtiva'] ?? 'todas');
        $sortCol = (string) ($filtros['sortCol'] ?? 'nome');
        $sortDir = (string) ($filtros['sortDir'] ?? 'asc');

        return [
            'busca' => trim((string) ($filtros['busca'] ?? '')),
            'categoria' => $categoria !== '' ? $categoria : 'todas',
            'sortCol' => in_array($sortCol, ['nome', 'quantidade', 'tipo_item', 'valor_total', 'atualizado'], true) ? $sortCol : 'nome',
            'sortDir' => $sortDir === 'desc' ? 'desc' : 'asc',
        ];
    }

    public function itens(Inventario $inventario, array $filtros = []): Collection
    {
        // Fluxo: dashboards e relatorios pedem os itens por inventario; aqui o estoque bruto vira DTO de tela com status, valor de referencia, filtros e ordenacao.
        $filtros = $this->normalizarFiltros($filtros);
        $precos = $this->precosReferencia();

        $itens = InventarioEstoque::query()
            ->with('item')
            ->where('inventario_id', $inventario->getKey())
            ->get()
            ->filter(fn (InventarioEstoque $estoque): bool => $estoque->item !== null)
            ->map(function (InventarioEstoque $estoque) use ($precos): array {
                $item = $estoque->item;
                $quantidade = (float) $estoque->quantidade;
                $valorUnitario = (float) ($precos->get($item->getKey()) ?? 0);

                return [
                    'inventario_estoque_id' => $estoque->id,
                    'item_id' => $item->id,
                    'nome' => $item->nome,
                    'descricao' => $item->descricao,
                    'unidade' => strtoupper($item->unidade_medida->value),
                    'tipo_item' => $item->tipo_item->value,
                    'tipo_label' => $item->tipo_item->label(),
                    'quantidade' => $quantidade,
                    'status' => $this->resolverStatus($quantidade),
                    'valor_unitario_referencia' => round($valorUnitario, 2),
                    'valor_total' => round($quantidade * $valorUnitario, 2),
                    'atualizado' => $estoque->updated_at?->format('d/m/Y H:i'),
                    'atualizado_raw' => $estoque->updated_at,
                ];
            })
            ->values();

        // Impacto: filtros sao aplicados em memoria porque os campos exibidos misturam estoque, item e calculos; mover para SQL exige revisar ordenacao e labels.
        if ($filtros['categoria'] !== 'todas') {
            $itens = $itens->where('tipo_item', $filtros['categoria'])->values();
        }

        if ($filtros['busca'] !== '') {
            $termo = mb_strtolower($filtros['busca']);

            $itens = $itens->filter(function (array $item) use ($termo): bool {
                return str_contains(mb_strtolower($item['nome']), $termo)
                    || str_contains(mb_strtolower((string) $item['descricao']), $termo)
                    || str_contains(mb_strtolower($item['tipo_label']), $termo);
            })->values();
        }

        return $this->ordenarItens($itens, $filtros['sortCol'], $filtros['sortDir']);
    }

    public function itensPaginados(Inventario $inventario, array $filtros = [], int $pagina = 1, int $porPagina = 8): object
    {
        $filtros = $this->normalizarFiltros($filtros);
        $pagina = max(1, $pagina);
        $porPagina = max(1, $porPagina);

        if ($filtros['sortCol'] === 'valor_total') {
            $itens = $this->itens($inventario, $filtros);
            $total = $itens->count();
            $totalPaginas = $total > 0 ? (int) ceil($total / $porPagina) : 1;
            $pagina = min($pagina, $totalPaginas);

            return (object) [
                'itens' => $itens->slice(($pagina - 1) * $porPagina, $porPagina)->values(),
                'paginacao' => $this->paginacaoArray($total, $pagina, $porPagina),
            ];
        }

        $query = InventarioEstoque::query()
            ->join('itens as item_filtro', 'item_filtro.id', '=', 'inventario_estoques.item_id')
            ->with('item')
            ->where('inventario_estoques.inventario_id', $inventario->getKey())
            ->select('inventario_estoques.*');

        if ($filtros['categoria'] !== 'todas') {
            $query->where('item_filtro.tipo_item', $filtros['categoria']);
        }

        if ($filtros['busca'] !== '') {
            $termo = '%'.$filtros['busca'].'%';

            $query->where(function ($builder) use ($termo): void {
                $builder
                    ->where('item_filtro.nome', 'like', $termo)
                    ->orWhere('item_filtro.descricao', 'like', $termo)
                    ->orWhere('item_filtro.tipo_item', 'like', $termo);
            });
        }

        $total = (clone $query)->count('inventario_estoques.id');
        $totalPaginas = $total > 0 ? (int) ceil($total / $porPagina) : 1;
        $pagina = min($pagina, $totalPaginas);

        match ($filtros['sortCol']) {
            'quantidade' => $query->orderBy('inventario_estoques.quantidade', $filtros['sortDir']),
            'tipo_item' => $query->orderBy('item_filtro.tipo_item', $filtros['sortDir'])->orderBy('item_filtro.nome'),
            'atualizado' => $query->orderBy('inventario_estoques.updated_at', $filtros['sortDir']),
            default => $query->orderBy('item_filtro.nome', $filtros['sortDir']),
        };

        $precos = $this->precosReferencia();
        $itens = $query
            ->forPage($pagina, $porPagina)
            ->get()
            ->filter(fn (InventarioEstoque $estoque): bool => $estoque->item !== null)
            ->map(fn (InventarioEstoque $estoque): array => $this->mapearEstoque($estoque, $precos))
            ->values();

        return (object) [
            'itens' => $itens,
            'paginacao' => $this->paginacaoArray($total, $pagina, $porPagina),
        ];
    }

    public function categoriasDisponiveis(Inventario $inventario): Collection
    {
        return Cache::remember('inventario:categorias:'.$inventario->getKey(), now()->addSeconds($this->cacheTtl()), fn (): Collection => InventarioEstoque::query()
            ->join('itens', 'itens.id', '=', 'inventario_estoques.item_id')
            ->where('inventario_estoques.inventario_id', $inventario->getKey())
            ->distinct()
            ->orderBy('itens.tipo_item')
            ->pluck('itens.tipo_item')
            ->filter()
            ->values());
    }

    public function cards(Inventario $inventario, array $filtros = []): array
    {
        $cacheKey = 'inventario:cards:'.$inventario->getKey().':'.md5(json_encode($this->normalizarFiltros($filtros)));

        return Cache::remember($cacheKey, now()->addSeconds($this->cacheTtl()), function () use ($inventario, $filtros): array {
            $itens = $this->itens($inventario, $filtros);
            $movimentacoes = $this->movimentacoesDoInventario($inventario);
            $baixas = $this->baixasDoInventario($inventario);
            $metricas = $this->metricasGerais($itens, $movimentacoes, $baixas);

            return [
                [
                    'titulo' => 'Itens no inventário',
                    'valor' => $metricas->total_itens,
                    'descricao' => 'itens atualmente cadastrados',
                    'cor' => 'blue',
                ],
                [
                    'titulo' => 'Valor estimado',
                    'valor' => 'R$ ' . number_format((float) $metricas->valor_total, 2, ',', '.'),
                    'descricao' => 'referencia calculada pelos contratos',
                    'cor' => 'emerald',
                ],
                [
                    'titulo' => 'Estoque baixo',
                    'valor' => $metricas->itens_criticos,
                    'descricao' => 'itens com saldo até 10 unidades',
                    'cor' => 'amber',
                ],
                [
                    'titulo' => 'Baixas registradas',
                    'valor' => $metricas->total_baixas,
                    'descricao' => number_format((float) $metricas->quantidade_baixada, 3, ',', '.') . ' unidades baixadas',
                    'cor' => 'rose',
                ],
            ];
        });
    }

    public function movimentacoesPorEstoque(int|InventarioEstoque $estoque): Collection
    {
        $estoqueId = $estoque instanceof InventarioEstoque ? $estoque->getKey() : $estoque;

        return InventarioMovimentacao::query()
            ->with(['estoque.item', 'pedido'])
            ->where('inventario_estoque_id', $estoqueId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (InventarioMovimentacao $movimentacao): array => $this->mapearMovimentacao($movimentacao))
            ->values();
    }

    public function baixasPorEstoque(int|InventarioEstoque $estoque): Collection
    {
        $estoqueId = $estoque instanceof InventarioEstoque ? $estoque->getKey() : $estoque;

        return InventarioBaixa::query()
            ->with('estoque.item')
            ->where('inventario_estoque_id', $estoqueId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (InventarioBaixa $baixa): array => $this->mapearBaixa($baixa))
            ->values();
    }

    public function movimentacoesDoInventario(Inventario $inventario): Collection
    {
        $estoqueIds = InventarioEstoque::query()
            ->where('inventario_id', $inventario->getKey())
            ->pluck('id');

        if ($estoqueIds->isEmpty()) {
            return collect();
        }

        return InventarioMovimentacao::query()
            ->with(['estoque.item', 'pedido'])
            ->whereIn('inventario_estoque_id', $estoqueIds)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (InventarioMovimentacao $movimentacao): array => $this->mapearMovimentacao($movimentacao))
            ->values();
    }

    public function baixasDoInventario(Inventario $inventario): Collection
    {
        $estoqueIds = InventarioEstoque::query()
            ->where('inventario_id', $inventario->getKey())
            ->pluck('id');

        if ($estoqueIds->isEmpty()) {
            return collect();
        }

        return InventarioBaixa::query()
            ->with('estoque.item')
            ->whereIn('inventario_estoque_id', $estoqueIds)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (InventarioBaixa $baixa): array => $this->mapearBaixa($baixa))
            ->values();
    }

    public function metricasGerais(Collection $itens, Collection $movimentacoes, Collection $baixas): object
    {
        // Fluxo: a tela monta itens/movimentacoes/baixas separadamente e entrega aqui; o resultado consolida cards numericos sem consultar banco de novo.
        $totalEntradas = (float) $movimentacoes
            ->where('tipo', TipoMovimentacao::Entrada->value)
            ->sum('quantidade');

        $totalSaidas = (float) $movimentacoes
            ->where('tipo', TipoMovimentacao::Saida->value)
            ->sum('quantidade');

        return (object) [
            'total_itens' => $itens->count(),
            'quantidade_total' => round((float) $itens->sum('quantidade'), 3),
            'valor_total' => round((float) $itens->sum('valor_total'), 2),
            'itens_criticos' => $itens->where('status', 'critico')->count(),
            'itens_zerados' => $itens->where('status', 'zerado')->count(),
            'total_movimentacoes' => $movimentacoes->count(),
            'total_entradas' => round($totalEntradas, 3),
            'total_saidas' => round($totalSaidas, 3),
            'saldo_movimentado' => round($totalEntradas - $totalSaidas, 3),
            'total_baixas' => $baixas->count(),
            'quantidade_baixada' => round((float) $baixas->sum('quantidade'), 3),
        ];
    }

    public function resumoItem(InventarioEstoque $estoque): object
    {
        $movimentacoes = $this->movimentacoesPorEstoque($estoque);
        $baixas = $this->baixasPorEstoque($estoque);
        $entradas = (float) $movimentacoes->where('tipo', TipoMovimentacao::Entrada->value)->sum('quantidade');
        $saidas = (float) $movimentacoes->where('tipo', TipoMovimentacao::Saida->value)->sum('quantidade');

        return (object) [
            'saldo_atual' => round((float) $estoque->quantidade, 3),
            'total_movimentacoes' => $movimentacoes->count(),
            'total_entradas' => round($entradas, 3),
            'total_saidas' => round($saidas, 3),
            'total_baixas' => $baixas->count(),
            'quantidade_baixada' => round((float) $baixas->sum('quantidade'), 3),
            'ultima_movimentacao' => $movimentacoes->first()['data'] ?? 'N/A',
            'primeira_movimentacao' => $movimentacoes->last()['data'] ?? 'N/A',
        ];
    }

    public function porCategoria(Collection $itens): Collection
    {
        $rows = $itens
            ->groupBy('tipo_item')
            ->map(function (Collection $grupo, string $tipoItem): array {
                $enum = TipoItem::tryFrom($tipoItem);

                return [
                    'tipo_item' => $tipoItem,
                    'label' => $enum?->label() ?? 'Não informado',
                    'total_itens' => $grupo->count(),
                    'quantidade_total' => round((float) $grupo->sum('quantidade'), 3),
                    'valor_total' => round((float) $grupo->sum('valor_total'), 2),
                ];
            })
            ->sortByDesc('valor_total')
            ->values();

        $max = (float) ($rows->max('valor_total') ?: 1);

        return $rows->map(function (array $row) use ($max): array {
            $row['pct_barra'] = $max > 0 ? (int) round(($row['valor_total'] / $max) * 100) : 0;

            return $row;
        });
    }

    public function formatarFiltros(array $filtros = []): array
    {
        $filtros = $this->normalizarFiltros($filtros);
        $resultado = [];

        if ($filtros['busca'] !== '') {
            $resultado['busca'] = $filtros['busca'];
        }

        if ($filtros['categoria'] !== 'todas') {
            $resultado['categoria'] = TipoItem::tryFrom($filtros['categoria'])?->label() ?? 'Categoria invalida';
        }

        $resultado['ordenacao'] = $this->descricaoOrdenacao($filtros['sortCol'], $filtros['sortDir']);

        return $resultado;
    }

    public function normalizarFiltrosRelatorioEnvios(array $filtros = []): array
    {
        $periodo = (string) ($filtros['periodo'] ?? 'geral');
        $mes = (int) ($filtros['mes'] ?? 0);
        $ano = (int) ($filtros['ano'] ?? now()->year);

        return [
            'busca' => trim((string) ($filtros['busca'] ?? '')),
            'escolas' => collect($filtros['escolas'] ?? [])
                ->map(fn (mixed $id): int => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'periodo' => $periodo === 'mensal' ? 'mensal' : 'geral',
            'mes' => $periodo === 'mensal' && $mes >= 1 && $mes <= 12 ? $mes : null,
            'ano' => $ano >= 2000 ? $ano : now()->year,
        ];
    }

    public function formatarFiltrosRelatorioEnvios(array $filtros = []): array
    {
        $filtros = $this->normalizarFiltrosRelatorioEnvios($filtros);
        $resultado = [];

        if ($filtros['busca'] !== '') {
            $resultado['busca'] = $filtros['busca'];
        }

        if ($filtros['escolas'] !== []) {
            $resultado['escolas'] = $this->rotuloEscolasSelecionadas($filtros['escolas']);
        }

        $resultado['periodo'] = $this->rotuloPeriodoRelatorioEnvios($filtros);

        return $resultado;
    }

    public function mesesRelatorioOptions(): array
    {
        return [
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Marco',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro',
        ];
    }

    public function anosRelatorioEnviosOptions(): array
    {
        $primeiraData = InventarioMovimentacao::query()
            ->where('tipo', TipoMovimentacao::Entrada)
            ->whereNotNull('inventario_pedido_id')
            ->orderBy('created_at')
            ->value('created_at');

        $anoAtual = now()->year;
        $anoInicial = $primeiraData instanceof Carbon
            ? $primeiraData->year
            : ($primeiraData ? Carbon::parse($primeiraData)->year : $anoAtual);

        $anoInicial = max(2000, min($anoInicial, $anoAtual));

        return collect(range($anoAtual, $anoInicial))
            ->mapWithKeys(fn (int $ano): array => [$ano => (string) $ano])
            ->all();
    }

    public function escolasRelatorioEnviosOptions(string $busca = ''): array
    {
        return $this->inventariosPanorama(['busca' => $busca])
            ->mapWithKeys(fn (Inventario $inventario): array => [
                (int) $inventario->escola_id => $inventario->escola?->nome ?? $inventario->nome,
            ])
            ->all();
    }

    public function relatorioEnviosEscolas(array $filtros = []): object
    {
        // Fluxo: o relatorio parte dos inventarios filtrados, busca somente entradas geradas por pedidos/romaneios, agrupa por escola e devolve resumo + detalhamento por item.
        $filtros = $this->normalizarFiltrosRelatorioEnvios($filtros);
        $inventarios = $this->inventariosPanorama($filtros);
        $inventarioIds = $inventarios
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->values()
            ->all();

        // Impacto: retorno vazio preserva o mesmo contrato de dados usado por Blade/PDF/XLSX; trocar por null exigiria tratar excecoes nos relatorios.
        if ($inventarioIds === []) {
            return (object) [
                'filtros' => $filtros,
                'periodo_label' => $this->rotuloPeriodoRelatorioEnvios($filtros),
                'escolas' => collect(),
                'movimentacoes' => collect(),
                'resumo' => (object) [
                    'total_escolas' => 0,
                    'total_pedidos' => 0,
                    'total_entregas' => 0,
                    'total_itens' => 0,
                    'quantidade_total' => 0.0,
                    'valor_total' => 0.0,
                ],
            ];
        }

        $precos = $this->precosReferencia();

        $movimentacoes = InventarioMovimentacao::query()
            ->with(['estoque.inventario.escola', 'estoque.item', 'pedido.romaneio'])
            ->where('tipo', TipoMovimentacao::Entrada)
            ->whereNotNull('inventario_pedido_id')
            ->whereHas('estoque', fn ($estoqueQuery) => $estoqueQuery->whereIn('inventario_id', $inventarioIds))
            ->when(
                $filtros['periodo'] === 'mensal',
                fn ($query) => $query
                    ->whereMonth('created_at', (int) $filtros['mes'])
                    ->whereYear('created_at', (int) $filtros['ano'])
            )
            ->orderBy('created_at')
            ->get()
            ->map(function (InventarioMovimentacao $movimentacao) use ($precos): array {
                // Fluxo: cada entrada de inventario escolar vira uma linha de envio, conectando escola, pedido, romaneio, item, quantidade e valor de referencia.
                $itemId = $movimentacao->estoque?->item_id;
                $valorUnitario = round((float) ($precos->get($itemId) ?? 0), 2);
                $quantidade = round((float) $movimentacao->quantidade, 3);

                return [
                    'inventario_id' => (int) ($movimentacao->estoque?->inventario_id ?? 0),
                    'inventario_nome' => $movimentacao->estoque?->inventario?->nome ?? 'Inventário Escolar',
                    'escola_id' => (int) ($movimentacao->estoque?->inventario?->escola_id ?? 0),
                    'escola_nome' => $movimentacao->estoque?->inventario?->escola?->nome ?? 'Escola não informada',
                    'pedido_id' => (int) ($movimentacao->inventario_pedido_id ?? 0),
                    'romaneio_codigo' => $movimentacao->pedido?->romaneio?->codigo,
                    'item_id' => (int) ($itemId ?? 0),
                    'item_nome' => $movimentacao->estoque?->item?->nome ?? 'Item',
                    'categoria' => $movimentacao->estoque?->item?->tipo_item?->label() ?? 'N/A',
                    'unidade' => strtoupper($movimentacao->estoque?->item?->unidade_medida?->value ?? 'N/A'),
                    'quantidade' => $quantidade,
                    'valor_unitario_referencia' => $valorUnitario,
                    'valor_total' => round($quantidade * $valorUnitario, 2),
                    'observacao' => $movimentacao->observacao,
                    'registrado_por' => $movimentacao->registrado_por ?? 'N/A',
                    'data' => $movimentacao->created_at?->format('d/m/Y H:i') ?? 'N/A',
                    'data_raw' => $movimentacao->created_at,
                ];
            })
            ->values();

        $escolas = $movimentacoes
            ->groupBy('inventario_id')
            ->map(function (Collection $rows): array {
                // Fluxo: depois das linhas individuais, agrupamos por inventario para gerar totais por escola e uma lista consolidada de itens recebidos.
                $primeira = $rows
                    ->sortBy(fn (array $row) => $row['data_raw']?->timestamp ?? 0)
                    ->first();
                $ultima = $rows
                    ->sortByDesc(fn (array $row) => $row['data_raw']?->timestamp ?? 0)
                    ->first();
                $base = $rows->first();

                $itens = $rows
                    ->groupBy('item_id')
                    ->map(function (Collection $itemRows): array {
                        $base = $itemRows->first();
                        $ultimaEntrega = $itemRows
                            ->sortByDesc(fn (array $row) => $row['data_raw']?->timestamp ?? 0)
                            ->first();

                        return [
                            'item_id' => (int) ($base['item_id'] ?? 0),
                            'nome' => $base['item_nome'] ?? 'Item',
                            'categoria' => $base['categoria'] ?? 'N/A',
                            'unidade' => $base['unidade'] ?? 'N/A',
                            'quantidade_total' => round((float) $itemRows->sum('quantidade'), 3),
                            'valor_unitario_referencia' => round((float) ($base['valor_unitario_referencia'] ?? 0), 2),
                            'valor_total' => round((float) $itemRows->sum('valor_total'), 2),
                            'total_entregas' => $itemRows->count(),
                            'ultima_entrega' => $ultimaEntrega['data'] ?? 'N/A',
                            'romaneios' => $itemRows
                                ->pluck('romaneio_codigo')
                                ->filter()
                                ->unique()
                                ->values()
                                ->implode(', '),
                        ];
                    })
                    ->sortByDesc('valor_total')
                    ->values();

                return [
                    'inventario_id' => (int) ($base['inventario_id'] ?? 0),
                    'inventario_nome' => $base['inventario_nome'] ?? 'Inventário Escolar',
                    'escola_id' => (int) ($base['escola_id'] ?? 0),
                    'escola_nome' => $base['escola_nome'] ?? 'Escola não informada',
                    'total_pedidos' => $rows->pluck('pedido_id')->filter()->unique()->count(),
                    'total_entregas' => $rows->count(),
                    'total_itens' => $itens->count(),
                    'quantidade_total' => round((float) $rows->sum('quantidade'), 3),
                    'valor_total' => round((float) $rows->sum('valor_total'), 2),
                    'primeiro_envio' => $primeira['data'] ?? 'N/A',
                    'ultimo_envio' => $ultima['data'] ?? 'N/A',
                    'itens' => $itens,
                ];
            })
            ->sortBy('escola_nome', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return (object) [
            'filtros' => $filtros,
            'periodo_label' => $this->rotuloPeriodoRelatorioEnvios($filtros),
            'escolas' => $escolas,
            'movimentacoes' => $movimentacoes,
            'resumo' => (object) [
                'total_escolas' => $escolas->count(),
                'total_pedidos' => $movimentacoes->pluck('pedido_id')->filter()->unique()->count(),
                'total_entregas' => $movimentacoes->count(),
                'total_itens' => (int) $escolas->sum('total_itens'),
                'quantidade_total' => round((float) $movimentacoes->sum('quantidade'), 3),
                'valor_total' => round((float) $movimentacoes->sum('valor_total'), 2),
            ],
        ];
    }

    public function inventariosResumo(array $filtros = []): Collection
    {
        return $this->inventariosPanorama($filtros)->map(function (Inventario $inventario): array {
            $itens = $this->itens($inventario);
            $movimentacoes = $this->movimentacoesDoInventario($inventario);
            $baixas = $this->baixasDoInventario($inventario);
            $metricas = $this->metricasGerais($itens, $movimentacoes, $baixas);

            return [
                'inventario_id' => $inventario->getKey(),
                'escola_id' => (int) $inventario->escola_id,
                'inventario_nome' => $inventario->nome,
                'escola_nome' => $inventario->escola?->nome ?? 'Escola não informada',
                'total_itens' => $metricas->total_itens,
                'quantidade_total' => $metricas->quantidade_total,
                'valor_total' => $metricas->valor_total,
                'itens_criticos' => $metricas->itens_criticos,
                'itens_zerados' => $metricas->itens_zerados,
                'total_movimentacoes' => $metricas->total_movimentacoes,
                'total_baixas' => $metricas->total_baixas,
                'quantidade_baixada' => $metricas->quantidade_baixada,
                'ultima_movimentacao' => $movimentacoes->first()['data'] ?? 'Sem movimentacoes',
            ];
        })->values();
    }

    public function metricasInventarios(Collection $inventarios): object
    {
        return (object) [
            'total_inventarios' => $inventarios->count(),
            'total_itens' => $inventarios->sum('total_itens'),
            'quantidade_total' => round((float) $inventarios->sum('quantidade_total'), 3),
            'valor_total' => round((float) $inventarios->sum('valor_total'), 2),
            'total_movimentacoes' => (int) $inventarios->sum('total_movimentacoes'),
            'total_baixas' => (int) $inventarios->sum('total_baixas'),
            'quantidade_baixada' => round((float) $inventarios->sum('quantidade_baixada'), 3),
        ];
    }

    public function movimentacoesGeraisInventarios(array $inventarioIds = []): Collection
    {
        $query = InventarioMovimentacao::query()->with(['estoque.inventario.escola', 'estoque.item', 'pedido']);

        if ($inventarioIds !== []) {
            $query->whereHas('estoque', fn ($estoqueQuery) => $estoqueQuery->whereIn('inventario_id', $inventarioIds));
        }

        return $query
            ->orderByDesc('created_at')
            ->get()
            ->map(function (InventarioMovimentacao $movimentacao): array {
                $base = $this->mapearMovimentacao($movimentacao);
                $base['escola_nome'] = $movimentacao->estoque?->inventario?->escola?->nome ?? 'N/A';

                return $base;
            })
            ->values();
    }

    public function comparativoValorPorEscola(Collection $inventarios): Collection
    {
        $max = (float) ($inventarios->max('valor_total') ?: 1);

        return $inventarios
            ->sortByDesc('valor_total')
            ->map(function (array $inventario) use ($max): array {
                $inventario['pct_barra'] = $max > 0 ? (int) round(($inventario['valor_total'] / $max) * 100) : 0;

                return $inventario;
            })
            ->values();
    }

    public function comparativoBaixasPorEscola(Collection $inventarios, ?string $tipoBaixa = null): Collection
    {
        $inventarioIds = $inventarios
            ->pluck('inventario_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        if ($inventarioIds === []) {
            return collect();
        }

        $precos = $this->precosReferencia();
        $tipoBaixa = $this->normalizarTipoBaixa($tipoBaixa);

        $baixasPorInventario = InventarioBaixa::query()
            ->with(['estoque.inventario.escola', 'estoque.item'])
            ->whereHas('estoque', fn ($estoqueQuery) => $estoqueQuery->whereIn('inventario_id', $inventarioIds))
            ->when($tipoBaixa !== null, fn ($query) => $query->where('motivo', $tipoBaixa))
            ->get()
            ->groupBy(fn (InventarioBaixa $baixa): int => (int) ($baixa->estoque?->inventario_id ?? 0));

        $rows = $inventarios
            ->map(function (array $inventario) use ($baixasPorInventario, $precos): array {
                $baixas = $baixasPorInventario->get((int) $inventario['inventario_id'], collect());

                $valorBaixado = round((float) $baixas->sum(function (InventarioBaixa $baixa) use ($precos): float {
                    $itemId = $baixa->estoque?->item_id;
                    $valorUnitario = (float) ($precos->get($itemId) ?? 0);

                    return round((float) $baixa->quantidade * $valorUnitario, 2);
                }), 2);

                return [
                    'inventario_id' => $inventario['inventario_id'],
                    'inventario_nome' => $inventario['inventario_nome'],
                    'escola_nome' => $inventario['escola_nome'],
                    'total_baixas_filtradas' => $baixas->count(),
                    'quantidade_baixada_filtrada' => round((float) $baixas->sum('quantidade'), 3),
                    'valor_baixado' => $valorBaixado,
                ];
            })
            ->filter(fn (array $inventario): bool => $inventario['total_baixas_filtradas'] > 0)
            ->sortByDesc('valor_baixado')
            ->values();

        $max = (float) ($rows->max('valor_baixado') ?: 1);

        return $rows
            ->map(function (array $inventario) use ($max): array {
                $inventario['pct_barra'] = $max > 0 ? (int) round(($inventario['valor_baixado'] / $max) * 100) : 0;

                return $inventario;
            })
            ->values();
    }

    public function tiposBaixaDisponiveis(): array
    {
        return collect(MotivoBaixa::cases())
            ->mapWithKeys(fn (MotivoBaixa $motivo): array => [$motivo->value => $motivo->label()])
            ->prepend('Todas as baixas', 'todas')
            ->all();
    }

    public function rotuloTipoBaixa(?string $tipoBaixa): string
    {
        $tipoBaixa = $this->normalizarTipoBaixa($tipoBaixa);

        return $tipoBaixa !== null
            ? (MotivoBaixa::tryFrom($tipoBaixa)?->label() ?? 'Tipo de baixa')
            : 'Todas as baixas';
    }

    protected function ordenarItens(Collection $itens, string $sortCol, string $sortDir): Collection
    {
        $asc = $sortDir === 'asc';

        $ordenado = match ($sortCol) {
            'quantidade' => $asc
                ? $itens->sortBy('quantidade')
                : $itens->sortByDesc('quantidade'),
            'tipo_item' => $asc
                ? $itens->sortBy('tipo_label', SORT_NATURAL | SORT_FLAG_CASE)
                : $itens->sortByDesc('tipo_label', SORT_NATURAL | SORT_FLAG_CASE),
            'valor_total' => $asc
                ? $itens->sortBy('valor_total')
                : $itens->sortByDesc('valor_total'),
            'atualizado' => $asc
                ? $itens->sortBy(fn (array $item) => $item['atualizado_raw']?->timestamp ?? 0)
                : $itens->sortByDesc(fn (array $item) => $item['atualizado_raw']?->timestamp ?? 0),
            default => $asc
                ? $itens->sortBy('nome', SORT_NATURAL | SORT_FLAG_CASE)
                : $itens->sortByDesc('nome', SORT_NATURAL | SORT_FLAG_CASE),
        };

        return $ordenado->values();
    }

    protected function resolverStatus(float $quantidade): string
    {
        if ($quantidade <= 0) {
            return 'zerado';
        }

        if ($quantidade <= 10) {
            return 'critico';
        }

        return 'normal';
    }

    protected function mapearMovimentacao(InventarioMovimentacao $movimentacao): array
    {
        $tipo = $movimentacao->tipo?->value ?? TipoMovimentacao::Saida->value;

        return [
            'id' => $movimentacao->id,
            'inventario_estoque_id' => $movimentacao->inventario_estoque_id,
            'item_nome' => $movimentacao->estoque?->item?->nome ?? 'N/A',
            'categoria' => $movimentacao->estoque?->item?->tipo_item?->label() ?? 'N/A',
            'tipo' => $tipo,
            'tipo_label' => $this->tipoMovimentacaoLabel($tipo),
            'quantidade' => (float) $movimentacao->quantidade,
            'pedido_id' => $movimentacao->inventario_pedido_id,
            'observacao' => $movimentacao->observacao,
            'registrado_por' => $movimentacao->registrado_por ?? 'N/A',
            'data' => $movimentacao->created_at?->format('d/m/Y H:i') ?? 'N/A',
            'data_raw' => $movimentacao->created_at,
        ];
    }

    protected function mapearBaixa(InventarioBaixa $baixa): array
    {
        return [
            'id' => $baixa->id,
            'inventario_estoque_id' => $baixa->inventario_estoque_id,
            'item_nome' => $baixa->estoque?->item?->nome ?? 'N/A',
            'categoria' => $baixa->estoque?->item?->tipo_item?->label() ?? 'N/A',
            'motivo' => $baixa->motivo?->label() ?? 'N/A',
            'descricao' => $baixa->descricao,
            'quantidade' => (float) $baixa->quantidade,
            'saldo_anterior' => (float) $baixa->saldo_anterior,
            'saldo_posterior' => (float) $baixa->saldo_posterior,
            'registrado_por' => $baixa->registrado_por ?? 'N/A',
            'data' => $baixa->created_at?->format('d/m/Y H:i') ?? 'N/A',
            'data_raw' => $baixa->created_at,
        ];
    }

    protected function tipoMovimentacaoLabel(string $tipo): string
    {
        return match ($tipo) {
            TipoMovimentacao::Entrada->value => 'Entrada',
            TipoMovimentacao::Transferencia->value => 'Transferência',
            default => 'Saída',
        };
    }

    protected function descricaoOrdenacao(string $sortCol, string $sortDir): string
    {
        $label = match ($sortCol) {
            'quantidade' => 'Quantidade em inventário',
            'tipo_item' => 'Categoria',
            'valor_total' => 'Valor total',
            'atualizado' => 'Ultima atualizacao',
            default => 'Nome do item',
        };

        return $label . ' (' . ($sortDir === 'desc' ? 'decrescente' : 'crescente') . ')';
    }

    protected function inventariosPanorama(array $filtros = []): Collection
    {
        // Impacto: este metodo e a base comum do panorama e do relatorio de envios; mudar busca/escolas aqui altera dashboards e exportacoes juntos.
        $busca = mb_strtolower(trim((string) ($filtros['busca'] ?? '')));
        $escolas = collect($filtros['escolas'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $inventarios = Inventario::query()
            ->with(['escola', 'estoques.item'])
            ->get();

        if ($busca !== '') {
            $inventarios = $inventarios->filter(function (Inventario $inventario) use ($busca): bool {
                return str_contains(mb_strtolower((string) $inventario->escola?->nome), $busca)
                    || str_contains(mb_strtolower((string) $inventario->nome), $busca);
            })->values();
        }

        if ($escolas !== []) {
            $inventarios = $inventarios
                ->filter(fn (Inventario $inventario): bool => in_array((int) $inventario->escola_id, $escolas, true))
                ->values();
        }

        return $inventarios
            ->sortBy(
                fn (Inventario $inventario): string => mb_strtolower((string) ($inventario->escola?->nome ?? $inventario->nome)),
                SORT_NATURAL | SORT_FLAG_CASE
            )
            ->values();
    }

    protected function mapearEstoque(InventarioEstoque $estoque, Collection $precos): array
    {
        $item = $estoque->item;
        $quantidade = (float) $estoque->quantidade;
        $valorUnitario = (float) ($precos->get($item->getKey()) ?? 0);

        return [
            'inventario_estoque_id' => $estoque->id,
            'item_id' => $item->id,
            'nome' => $item->nome,
            'descricao' => $item->descricao,
            'unidade' => strtoupper($item->unidade_medida->value),
            'tipo_item' => $item->tipo_item->value,
            'tipo_label' => $item->tipo_item->label(),
            'quantidade' => $quantidade,
            'status' => $this->resolverStatus($quantidade),
            'valor_unitario_referencia' => round($valorUnitario, 2),
            'valor_total' => round($quantidade * $valorUnitario, 2),
            'atualizado' => $estoque->updated_at?->format('d/m/Y H:i'),
            'atualizado_raw' => $estoque->updated_at,
        ];
    }

    protected function paginacaoArray(int $total, int $pagina, int $porPagina): array
    {
        $totalPaginas = $total > 0 ? (int) ceil($total / $porPagina) : 1;

        return [
            'total' => $total,
            'porPagina' => $porPagina,
            'paginaAtual' => $pagina,
            'totalPaginas' => $totalPaginas,
            'de' => $total === 0 ? 0 : ($pagina - 1) * $porPagina + 1,
            'ate' => min($pagina * $porPagina, $total),
        ];
    }

    protected function cacheTtl(): int
    {
        return max(1, (int) config('performance.cache_ttl.inventory_dashboard', 60));
    }

    protected function precosReferencia(): Collection
    {
        // Fluxo: primeiro busca preco do contrato ativo, depois completa com ultimo preco historico. O cache evita repetir essa consulta em cada linha de item/relatorio.
        if ($this->precosReferenciaCache instanceof Collection) {
            return $this->precosReferenciaCache;
        }

        $precosAtivos = ContratoItem::query()
            ->whereHas('contrato', fn ($query) => $query->where('ativo', true))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['item_id', 'preco_unitario'])
            ->unique('item_id')
            ->mapWithKeys(fn (ContratoItem $item): array => [$item->item_id => (float) $item->preco_unitario]);

        $precosFallback = ContratoItem::query()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get(['item_id', 'preco_unitario'])
            ->unique('item_id')
            ->mapWithKeys(fn (ContratoItem $item): array => [$item->item_id => (float) $item->preco_unitario]);

        return $this->precosReferenciaCache = $precosAtivos->union($precosFallback);
    }

    protected function normalizarTipoBaixa(?string $tipoBaixa): ?string
    {
        $tipoBaixa = trim((string) $tipoBaixa);

        if ($tipoBaixa === '' || $tipoBaixa === 'todas') {
            return null;
        }

        return MotivoBaixa::tryFrom($tipoBaixa)?->value;
    }

    protected function rotuloEscolasSelecionadas(array $escolas): string
    {
        $nomes = collect($this->escolasRelatorioEnviosOptions())
            ->only($escolas)
            ->values();

        if ($nomes->isEmpty()) {
            return 'Escolas selecionadas';
        }

        $texto = $nomes->take(3)->implode(', ');

        if ($nomes->count() > 3) {
            $texto .= ' +' . ($nomes->count() - 3);
        }

        return $texto;
    }

    protected function rotuloPeriodoRelatorioEnvios(array $filtros): string
    {
        if (($filtros['periodo'] ?? 'geral') !== 'mensal') {
            return 'Geral';
        }

        $mes = (int) ($filtros['mes'] ?? 0);
        $ano = (int) ($filtros['ano'] ?? now()->year);
        $meses = $this->mesesRelatorioOptions();

        return ($meses[$mes] ?? 'Mes') . ' de ' . $ano;
    }
}
