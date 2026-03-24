<?php

namespace App\Filament\Admin\Pages;

use App\Models\Estoque;
use App\Models\EstoqueMovimentacao;
use App\Models\Enums\TipoMovimentacao;
use App\Models\Enums\TipoItem;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class GestaoEstoque extends Page
{
    protected string $view = 'filament.pages.gestao-estoque';

    protected static ?string $title = 'Gestão de Estoque';
    protected static ?string $slug = 'gestao-estoque';
    protected static ?int $navigationSort = 5;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingStorefront;
    protected static string|UnitEnum|null $navigationGroup = 'Alimentação Escolar';

    public string $busca = '';

    // Paginação
    public int $porPagina = 10;
    public int $paginaAtual = 1;

    // Ordenação
    public string $sortCol = 'nome';
    public string $sortDir = 'asc';

    // Aba ativa
    public string $abaAtiva = 'todas';

    // SlideOver de movimentações
    public bool $slideOverAberto = false;
    public ?int $estoqueSelecionadoId = null;
    public string $itemSelecionadoNome = '';
    public string $itemSelecionadoUnidade = '';
    public array $movimentacoes = [];

    // -------------------------------------------------------------------------
    // Hooks de reset de página
    // -------------------------------------------------------------------------

    public function updatedBusca(): void
    {
        $this->paginaAtual = 1;
    }

    public function updatedAbaAtiva(): void
    {
        $this->paginaAtual = 1;
    }

    public function updatedPorPagina(): void
    {
        $this->paginaAtual = 1;
    }

    // -------------------------------------------------------------------------
    // Ordenação
    // -------------------------------------------------------------------------

    public function sortBy(string $coluna): void
    {
        if ($this->sortCol === $coluna) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortCol = $coluna;
            $this->sortDir = 'asc';
        }

        $this->paginaAtual = 1;
    }

    public function getEstoqueSelecionadoProperty(): ?Estoque
    {
        if (! $this->estoqueSelecionadoId) return null;

        return Estoque::find($this->estoqueSelecionadoId);
    }

    // -------------------------------------------------------------------------
    // Computed: Cards do topo
    // -------------------------------------------------------------------------

    public function getCardsProperty(): array
    {
        $itens = $this->getItensEstoque();

        $totalItens         = $itens->count();
        $itensZerados       = $itens->filter(fn($i) => $i['quantidade'] <= 0)->count();
        $itensCriticos      = $itens->filter(fn($i) => $i['quantidade'] > 0 && $i['quantidade'] <= 10)->count();
        $totalMovimentacoes = EstoqueMovimentacao::count();

        return [
            [
                'titulo'    => 'Itens no Estoque',
                'valor'     => $totalItens,
                'icone'     => 'heroicon-o-cube',
                'cor'       => 'blue',
                'descricao' => 'itens cadastrados',
            ],
            [
                'titulo'    => 'Estoque Baixo',
                'valor'     => $itensCriticos,
                'icone'     => 'heroicon-o-exclamation-triangle',
                'cor'       => 'amber',
                'descricao' => 'itens com ≤ 10 unidades',
            ],
            [
                'titulo'    => 'Itens Zerados',
                'valor'     => $itensZerados,
                'icone'     => 'heroicon-o-x-circle',
                'cor'       => 'red',
                'descricao' => 'sem saldo em estoque',
            ],
            [
                'titulo'    => 'Movimentações',
                'valor'     => $totalMovimentacoes,
                'icone'     => 'heroicon-o-arrow-path',
                'cor'       => 'green',
                'descricao' => 'entradas e saídas registradas',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Computed: Abas disponíveis
    // -------------------------------------------------------------------------

    public function getAbasProperty(): array
    {
        $categorias = $this->getItensEstoque()
            ->pluck('tipo_item')
            ->unique()
            ->values();

        $abas = [['value' => 'todas', 'label' => 'Todos']];

        foreach (TipoItem::cases() as $tipo) {
            if ($categorias->contains($tipo->value)) {
                $abas[] = [
                    'value' => $tipo->value,
                    'label' => $tipo->label(),
                ];
            }
        }

        return $abas;
    }

    // -------------------------------------------------------------------------
    // Computed: base filtrada/ordenada (sem paginação)
    // -------------------------------------------------------------------------

    public function getItensFiltradosBaseProperty(): \Illuminate\Support\Collection
    {
        $itens = $this->getItensEstoque();

        // Filtro de aba
        if ($this->abaAtiva !== 'todas') {
            $itens = $itens->filter(fn($i) => $i['tipo_item'] === $this->abaAtiva);
        }

        // Busca
        $termo = mb_strtolower(trim($this->busca));
        if ($termo !== '') {
            $itens = $itens->filter(
                fn($i) => str_contains(mb_strtolower($i['nome']), $termo)
            );
        }

        // Ordenação
        $dir = $this->sortDir === 'asc';

        return match ($this->sortCol) {
            'nome'       => $dir
                ? $itens->sortBy('nome', SORT_NATURAL | SORT_FLAG_CASE)
                : $itens->sortByDesc('nome', SORT_NATURAL | SORT_FLAG_CASE),
            'quantidade' => $dir
                ? $itens->sortBy('quantidade')
                : $itens->sortByDesc('quantidade'),
            default => $itens->sortBy('nome', SORT_NATURAL | SORT_FLAG_CASE),
        };
    }

    // -------------------------------------------------------------------------
    // Computed: página atual
    // -------------------------------------------------------------------------

    public function getItensFiltradosProperty(): \Illuminate\Support\Collection
    {
        return $this->itensFiltradosBase
            ->slice(($this->paginaAtual - 1) * $this->porPagina, $this->porPagina)
            ->values();
    }

    // -------------------------------------------------------------------------
    // Computed: metadados de paginação para a view
    // -------------------------------------------------------------------------

    public function getPaginacaoProperty(): array
    {
        $total        = $this->itensFiltradosBase->count();
        $totalPaginas = $total > 0 ? (int) ceil($total / $this->porPagina) : 1;

        return [
            'total'        => $total,
            'porPagina'    => $this->porPagina,
            'paginaAtual'  => $this->paginaAtual,
            'totalPaginas' => $totalPaginas,
            'de'           => $total === 0 ? 0 : ($this->paginaAtual - 1) * $this->porPagina + 1,
            'ate'          => min($this->paginaAtual * $this->porPagina, $total),
        ];
    }

    // -------------------------------------------------------------------------
    // Lógica central: monta coleção de itens do estoque
    // -------------------------------------------------------------------------

    protected function getItensEstoque(): \Illuminate\Support\Collection
    {
        return Estoque::query()
            ->with('item')
            ->get()
            ->map(function (Estoque $estoque) {
                $item = $estoque->item;

                return [
                    'estoque_id' => $estoque->id,
                    'item_id'    => $item->id,
                    'nome'       => $item->nome,
                    'unidade'    => strtoupper($item->unidade_medida->value),
                    'tipo_item'  => $item->tipo_item->value,
                    'tipo_label' => $item->tipo_item->label(),
                    'quantidade' => (float) $estoque->quantidade,
                    'status'     => $this->resolverStatus((float) $estoque->quantidade),
                    'atualizado' => $estoque->updated_at->format('d/m/Y H:i'),
                ];
            })
            ->values();
    }

    protected function resolverStatus(float $quantidade): string
    {
        if ($quantidade <= 0)  return 'zerado';
        if ($quantidade <= 10) return 'critico';
        return 'normal';
    }

    // -------------------------------------------------------------------------
    // Ações
    // -------------------------------------------------------------------------

    public function mudarAba(string $aba): void
    {
        $this->abaAtiva    = $aba;
        $this->paginaAtual = 1;
    }

    public function mudarPagina(int $pagina): void
    {
        $total        = $this->itensFiltradosBase->count();
        $totalPaginas = (int) ceil($total / $this->porPagina);

        $this->paginaAtual = max(1, min($pagina, $totalPaginas));
    }

    public function abrirSlideOver(int $estoqueId): void
    {
        $estoque = Estoque::with('item')->find($estoqueId);

        if (! $estoque) return;

        $this->estoqueSelecionadoId   = $estoqueId;
        $this->itemSelecionadoNome    = $estoque->item->nome;
        $this->itemSelecionadoUnidade = strtoupper($estoque->item->unidade_medida->value);

        $this->movimentacoes = $estoque->movimentacoes()
            ->with('pedidoMerenda')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function (EstoqueMovimentacao $mov) {
                return [
                    'id'             => $mov->id,
                    'tipo'           => $mov->tipo->value,
                    'tipo_label'     => $mov->tipo === TipoMovimentacao::Entrada ? 'Entrada' : 'Saída',
                    'quantidade'     => (float) $mov->quantidade,
                    'pedido_id'      => $mov->pedido_merenda_id,
                    'observacao'     => $mov->observacao,
                    'registrado_por' => $mov->registrado_por ?? '—',
                    'data'           => $mov->created_at->format('d/m/Y H:i'),
                ];
            })
            ->toArray();

        $this->slideOverAberto = true;
    }

    public function fecharSlideOver(): void
    {
        $this->slideOverAberto      = false;
        $this->estoqueSelecionadoId = null;
        $this->movimentacoes        = [];
        $this->itemSelecionadoNome  = '';
        $this->itemSelecionadoUnidade = '';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}