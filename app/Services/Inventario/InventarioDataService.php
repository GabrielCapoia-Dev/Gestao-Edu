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
use Illuminate\Support\Collection;

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

    public function categoriasDisponiveis(Inventario $inventario): Collection
    {
        return $this->itens($inventario)
            ->pluck('tipo_item')
            ->filter()
            ->unique()
            ->values();
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
                    'label' => $enum?->label() ?? 'Nao informado',
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

    public function inventariosResumo(array $filtros = []): Collection
    {
        $busca = mb_strtolower(trim((string) ($filtros['busca'] ?? '')));
        $inventarios = Inventario::query()
            ->with(['escola', 'estoques.item'])
            ->get();

        if ($busca !== '') {
            $inventarios = $inventarios->filter(function (Inventario $inventario) use ($busca): bool {
                return str_contains(mb_strtolower((string) $inventario->escola?->nome), $busca)
                    || str_contains(mb_strtolower((string) $inventario->nome), $busca);
            })->values();
        }

        return $inventarios->map(function (Inventario $inventario): array {
            $itens = $this->itens($inventario);
            $movimentacoes = $this->movimentacoesDoInventario($inventario);
            $baixas = $this->baixasDoInventario($inventario);
            $metricas = $this->metricasGerais($itens, $movimentacoes, $baixas);

            return [
                'inventario_id' => $inventario->getKey(),
                'inventario_nome' => $inventario->nome,
                'escola_nome' => $inventario->escola?->nome ?? 'Escola nao informada',
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
            TipoMovimentacao::Transferencia->value => 'Transferencia',
            default => 'Saida',
        };
    }

    protected function descricaoOrdenacao(string $sortCol, string $sortDir): string
    {
        $label = match ($sortCol) {
            'quantidade' => 'Quantidade em inventario',
            'tipo_item' => 'Categoria',
            'valor_total' => 'Valor total',
            'atualizado' => 'Ultima atualizacao',
            default => 'Nome do item',
        };

        return $label . ' (' . ($sortDir === 'desc' ? 'decrescente' : 'crescente') . ')';
    }

    protected function precosReferencia(): Collection
    {
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
}
