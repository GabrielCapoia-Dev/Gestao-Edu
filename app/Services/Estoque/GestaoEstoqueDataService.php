<?php

namespace App\Services\Estoque;

use App\Models\BaixasEstoques;
use App\Models\Enums\TipoItem;
use App\Models\Enums\TipoMovimentacao;
use App\Models\Estoque;
use App\Models\EstoqueMovimentacao;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class GestaoEstoqueDataService
{
    public function normalizarFiltros(array $filtros = []): array
    {
        $categoria = (string) ($filtros['categoria'] ?? $filtros['abaAtiva'] ?? 'todas');
        $sortCol = (string) ($filtros['sortCol'] ?? 'nome');
        $sortDir = (string) ($filtros['sortDir'] ?? 'asc');

        return [
            'busca' => trim((string) ($filtros['busca'] ?? '')),
            'categoria' => $categoria !== '' ? $categoria : 'todas',
            'sortCol' => in_array($sortCol, ['nome', 'quantidade', 'tipo_item', 'atualizado'], true) ? $sortCol : 'nome',
            'sortDir' => $sortDir === 'desc' ? 'desc' : 'asc',
        ];
    }

    public function itens(array $filtros = []): Collection
    {
        // Fluxo: a pagina de gestao envia filtros; o servico carrega estoque + item, transforma em linhas de exibicao, aplica busca/categoria e devolve ordenado.
        $filtros = $this->normalizarFiltros($filtros);

        $itens = Estoque::query()
            ->with('item')
            ->get()
            ->filter(fn (Estoque $estoque): bool => $estoque->item !== null)
            ->map(function (Estoque $estoque): array {
                $item = $estoque->item;
                $quantidade = (float) $estoque->quantidade;

                return [
                    'estoque_id' => $estoque->id,
                    'item_id' => $item->id,
                    'nome' => $item->nome,
                    'descricao' => $item->descricao,
                    'unidade' => strtoupper($item->unidade_medida->value),
                    'tipo_item' => $item->tipo_item->value,
                    'tipo_label' => $item->tipo_item->label(),
                    'quantidade' => $quantidade,
                    'status' => $this->resolverStatus($quantidade),
                    'atualizado' => $estoque->updated_at?->format('d/m/Y H:i'),
                    'atualizado_raw' => $estoque->updated_at,
                ];
            })
            ->values();

        // Impacto: filtros em colecao mantem a mesma regra usada nos cards, slide-over e relatorios; alterar aqui muda todos os consumidores do estoque.
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

    public function itensPaginados(array $filtros = [], int $pagina = 1, int $porPagina = 5): object
    {
        $filtros = $this->normalizarFiltros($filtros);
        $pagina = max(1, $pagina);
        $porPagina = max(1, $porPagina);

        $query = Estoque::query()
            ->join('itens as item_filtro', 'item_filtro.id', '=', 'estoque.item_id')
            ->with('item')
            ->select('estoque.*');

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

        $total = (clone $query)->count('estoque.id');
        $totalPaginas = $total > 0 ? (int) ceil($total / $porPagina) : 1;
        $pagina = min($pagina, $totalPaginas);

        match ($filtros['sortCol']) {
            'quantidade' => $query->orderBy('estoque.quantidade', $filtros['sortDir']),
            'tipo_item' => $query->orderBy('item_filtro.tipo_item', $filtros['sortDir'])->orderBy('item_filtro.nome'),
            'atualizado' => $query->orderBy('estoque.updated_at', $filtros['sortDir']),
            default => $query->orderBy('item_filtro.nome', $filtros['sortDir']),
        };

        $itens = $query
            ->forPage($pagina, $porPagina)
            ->get()
            ->filter(fn (Estoque $estoque): bool => $estoque->item !== null)
            ->map(fn (Estoque $estoque): array => $this->mapearEstoque($estoque))
            ->values();

        return (object) [
            'itens' => $itens,
            'paginacao' => [
                'total' => $total,
                'porPagina' => $porPagina,
                'paginaAtual' => $pagina,
                'totalPaginas' => $totalPaginas,
                'de' => $total === 0 ? 0 : ($pagina - 1) * $porPagina + 1,
                'ate' => min($pagina * $porPagina, $total),
            ],
        ];
    }

    public function categoriasDisponiveis(): Collection
    {
        return Cache::remember('gestao-estoque:categorias', now()->addSeconds($this->cacheTtl()), fn (): Collection => Estoque::query()
            ->join('itens', 'itens.id', '=', 'estoque.item_id')
            ->distinct()
            ->orderBy('itens.tipo_item')
            ->pluck('itens.tipo_item')
            ->filter()
            ->values());
    }

    public function cards(): array
    {
        return Cache::remember('gestao-estoque:cards', now()->addSeconds($this->cacheTtl()), function (): array {
            $totalItens = Estoque::query()->whereHas('item')->count();
            $itensZerados = Estoque::query()->whereHas('item')->where('quantidade', '<=', 0)->count();
            $itensCriticos = Estoque::query()->whereHas('item')->where('quantidade', '>', 0)->where('quantidade', '<=', 10)->count();
            $totalMovimentacoes = EstoqueMovimentacao::count();

            return [
                [
                    'titulo' => 'Itens no Estoque',
                    'valor' => $totalItens,
                    'icone' => 'heroicon-o-cube',
                    'cor' => 'blue',
                    'descricao' => 'itens cadastrados',
                ],
                [
                    'titulo' => 'Estoque Baixo',
                    'valor' => $itensCriticos,
                    'icone' => 'heroicon-o-exclamation-triangle',
                    'cor' => 'amber',
                    'descricao' => 'itens com <= 10 unidades',
                ],
                [
                    'titulo' => 'Itens Zerados',
                    'valor' => $itensZerados,
                    'icone' => 'heroicon-o-x-circle',
                    'cor' => 'red',
                    'descricao' => 'sem saldo em estoque',
                ],
                [
                    'titulo' => 'Movimentações',
                    'valor' => $totalMovimentacoes,
                    'icone' => 'heroicon-o-arrow-path',
                    'cor' => 'green',
                    'descricao' => 'entradas e saídas registradas',
                ],
            ];
        });
    }

    public function movimentacoesPorEstoque(int|Estoque $estoque): Collection
    {
        $estoqueId = $estoque instanceof Estoque ? $estoque->getKey() : $estoque;

        return EstoqueMovimentacao::query()
            ->with(['estoque.item', 'pedidoMerenda'])
            ->where('estoque_id', $estoqueId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EstoqueMovimentacao $movimentacao): array => $this->mapearMovimentacao($movimentacao))
            ->values();
    }

    public function baixasPorEstoque(int|Estoque $estoque): Collection
    {
        $estoqueId = $estoque instanceof Estoque ? $estoque->getKey() : $estoque;

        return BaixasEstoques::query()
            ->with('estoque.item')
            ->where('estoque_id', $estoqueId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (BaixasEstoques $baixa): array => $this->mapearBaixa($baixa))
            ->values();
    }

    public function movimentacoesDosFiltros(array $filtros = []): Collection
    {
        // Fluxo: primeiro resolve quais estoques aparecem com os filtros atuais, depois busca as movimentacoes desses estoques para metricas e relatorios.
        $estoqueIds = $this->itens($filtros)->pluck('estoque_id')->all();

        if ($estoqueIds === []) {
            return collect();
        }

        return EstoqueMovimentacao::query()
            ->with(['estoque.item', 'pedidoMerenda'])
            ->whereIn('estoque_id', $estoqueIds)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EstoqueMovimentacao $movimentacao): array => $this->mapearMovimentacao($movimentacao))
            ->values();
    }

    public function baixasDosFiltros(array $filtros = []): Collection
    {
        $estoqueIds = $this->itens($filtros)->pluck('estoque_id')->all();

        if ($estoqueIds === []) {
            return collect();
        }

        return BaixasEstoques::query()
            ->with('estoque.item')
            ->whereIn('estoque_id', $estoqueIds)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (BaixasEstoques $baixa): array => $this->mapearBaixa($baixa))
            ->values();
    }

    public function metricasGerais(Collection $itens, Collection $movimentacoes, Collection $baixas): object
    {
        // Impacto: estes totais alimentam cards do dashboard e exportacoes; mudar nomes/campos exige revisar views e servicos de relatorio.
        $totalEntradas = (float) $movimentacoes
            ->where('tipo', TipoMovimentacao::Entrada->value)
            ->sum('quantidade');

        $totalSaidas = (float) $movimentacoes
            ->where('tipo', TipoMovimentacao::Saida->value)
            ->sum('quantidade');

        return (object) [
            'total_itens' => $itens->count(),
            'quantidade_total' => round((float) $itens->sum('quantidade'), 3),
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

    public function resumoItem(Estoque $estoque): object
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
                    'label' => $enum?->label() ?? 'Nao informado',
                    'total_itens' => $grupo->count(),
                    'quantidade_total' => round((float) $grupo->sum('quantidade'), 3),
                ];
            })
            ->sortByDesc('quantidade_total')
            ->values();

        $max = (float) ($rows->max('quantidade_total') ?: 1);

        return $rows->map(function (array $row) use ($max): array {
            $row['pct_barra'] = $max > 0 ? (int) round(($row['quantidade_total'] / $max) * 100) : 0;

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
            'atualizado' => $asc
                ? $itens->sortBy(fn (array $item) => $item['atualizado_raw']?->timestamp ?? 0)
                : $itens->sortByDesc(fn (array $item) => $item['atualizado_raw']?->timestamp ?? 0),
            default => $asc
                ? $itens->sortBy('nome', SORT_NATURAL | SORT_FLAG_CASE)
                : $itens->sortByDesc('nome', SORT_NATURAL | SORT_FLAG_CASE),
        };

        return $ordenado->values();
    }

    protected function mapearEstoque(Estoque $estoque): array
    {
        $item = $estoque->item;
        $quantidade = (float) $estoque->quantidade;

        return [
            'estoque_id' => $estoque->id,
            'item_id' => $item->id,
            'nome' => $item->nome,
            'descricao' => $item->descricao,
            'unidade' => strtoupper($item->unidade_medida->value),
            'tipo_item' => $item->tipo_item->value,
            'tipo_label' => $item->tipo_item->label(),
            'quantidade' => $quantidade,
            'status' => $this->resolverStatus($quantidade),
            'atualizado' => $estoque->updated_at?->format('d/m/Y H:i'),
            'atualizado_raw' => $estoque->updated_at,
        ];
    }

    protected function cacheTtl(): int
    {
        return max(1, (int) config('performance.cache_ttl.inventory_dashboard', 60));
    }

    protected function resolverStatus(float $quantidade): string
    {
        // Impacto: o status visual do estoque nasce aqui. Alterar limites de critico/zerado muda badges, cards e alertas de baixa quantidade.
        if ($quantidade <= 0) {
            return 'zerado';
        }

        if ($quantidade <= 10) {
            return 'critico';
        }

        return 'normal';
    }

    protected function mapearMovimentacao(EstoqueMovimentacao $movimentacao): array
    {
        $tipo = $movimentacao->tipo?->value ?? TipoMovimentacao::Saida->value;

        return [
            'id' => $movimentacao->id,
            'estoque_id' => $movimentacao->estoque_id,
            'item_nome' => $movimentacao->estoque?->item?->nome ?? 'N/A',
            'categoria' => $movimentacao->estoque?->item?->tipo_item?->label() ?? 'N/A',
            'tipo' => $tipo,
            'tipo_label' => $this->tipoMovimentacaoLabel($tipo),
            'quantidade' => (float) $movimentacao->quantidade,
            'pedido_id' => $movimentacao->pedido_merenda_id,
            'observacao' => $movimentacao->observacao,
            'registrado_por' => $movimentacao->registrado_por ?? 'N/A',
            'data' => $movimentacao->created_at?->format('d/m/Y H:i') ?? 'N/A',
            'data_raw' => $movimentacao->created_at,
        ];
    }

    protected function mapearBaixa(BaixasEstoques $baixa): array
    {
        return [
            'id' => $baixa->id,
            'estoque_id' => $baixa->estoque_id,
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
            TipoMovimentacao::Transferencia->value => 'Transferencia',
            default => 'Saida',
        };
    }

    protected function descricaoOrdenacao(string $sortCol, string $sortDir): string
    {
        $label = match ($sortCol) {
            'quantidade' => 'Quantidade em estoque',
            'tipo_item' => 'Categoria',
            'atualizado' => 'Ultima atualizacao',
            default => 'Nome do item',
        };

        return $label . ' (' . ($sortDir === 'desc' ? 'decrescente' : 'crescente') . ')';
    }
}
