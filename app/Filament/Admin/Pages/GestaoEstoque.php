<?php

namespace App\Filament\Admin\Pages;

use App\Models\Enums\TipoItem;
use App\Models\Estoque;
use App\Models\EstoqueMovimentacao;
use App\Services\Estoque\GestaoEstoqueDataService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class GestaoEstoque extends Page
{
    protected string $view = 'filament.pages.gestao-estoque';

    protected static ?string $title = 'Gestao de Estoque';
    protected static ?string $slug = 'gestao-estoque';
    protected static ?int $navigationSort = 5;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingStorefront;
    protected static string|UnitEnum|null $navigationGroup = 'Alimentacao Escolar';

    public string $busca = '';
    public int $porPagina = 5;
    public int $paginaAtual = 1;
    public string $sortCol = 'nome';
    public string $sortDir = 'asc';
    public string $abaAtiva = 'todas';

    public bool $slideOverAberto = false;
    public ?int $estoqueSelecionadoId = null;
    public string $itemSelecionadoNome = '';
    public string $itemSelecionadoUnidade = '';
    public array $movimentacoes = [];
    public int $totalMovimentacoesItem = 0;

    public static function canAccess(): bool
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return $user->hasPermissionTo('Listar Gestão de Estoque');
    }

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
        if (! $this->estoqueSelecionadoId) {
            return null;
        }

        return Estoque::find($this->estoqueSelecionadoId);
    }

    public function getCardsProperty(): array
    {
        $itens = $this->dataService()->itens();
        $totalItens = $itens->count();
        $itensZerados = $itens->where('status', 'zerado')->count();
        $itensCriticos = $itens->where('status', 'critico')->count();
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
                'titulo' => 'Movimentacoes',
                'valor' => $totalMovimentacoes,
                'icone' => 'heroicon-o-arrow-path',
                'cor' => 'green',
                'descricao' => 'entradas e saidas registradas',
            ],
        ];
    }

    public function getAbasProperty(): array
    {
        $categorias = $this->dataService()->categoriasDisponiveis();
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

    public function getItensFiltradosBaseProperty(): \Illuminate\Support\Collection
    {
        return $this->dataService()->itens($this->filtrosExportacao);
    }

    public function getItensFiltradosProperty(): \Illuminate\Support\Collection
    {
        return $this->itensFiltradosBase
            ->slice(($this->paginaAtual - 1) * $this->porPagina, $this->porPagina)
            ->values();
    }

    public function getPaginacaoProperty(): array
    {
        $total = $this->itensFiltradosBase->count();
        $totalPaginas = $total > 0 ? (int) ceil($total / $this->porPagina) : 1;

        return [
            'total' => $total,
            'porPagina' => $this->porPagina,
            'paginaAtual' => $this->paginaAtual,
            'totalPaginas' => $totalPaginas,
            'de' => $total === 0 ? 0 : ($this->paginaAtual - 1) * $this->porPagina + 1,
            'ate' => min($this->paginaAtual * $this->porPagina, $total),
        ];
    }

    public function getFiltrosExportacaoProperty(): array
    {
        return [
            'busca' => $this->busca,
            'categoria' => $this->abaAtiva,
            'sortCol' => $this->sortCol,
            'sortDir' => $this->sortDir,
        ];
    }

    public function getPodeExportarProperty(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $user?->hasPermissionTo('Exportar Relatórios') ?? false;
    }

    public function mudarAba(string $aba): void
    {
        $this->abaAtiva = $aba;
        $this->paginaAtual = 1;
    }

    public function mudarPagina(int $pagina): void
    {
        $total = $this->itensFiltradosBase->count();
        $totalPaginas = max(1, (int) ceil($total / $this->porPagina));

        $this->paginaAtual = max(1, min($pagina, $totalPaginas));
    }

    public function abrirSlideOver(int $estoqueId): void
    {
        $estoque = Estoque::with('item')->find($estoqueId);

        if (! $estoque || ! $estoque->item) {
            return;
        }

        $this->estoqueSelecionadoId = $estoqueId;
        $this->itemSelecionadoNome = $estoque->item->nome;
        $this->itemSelecionadoUnidade = strtoupper($estoque->item->unidade_medida->value);

        $movimentacoes = $this->dataService()->movimentacoesPorEstoque($estoque);
        $this->totalMovimentacoesItem = $movimentacoes->count();
        $this->movimentacoes = $movimentacoes->take(50)->values()->all();

        $this->slideOverAberto = true;
    }

    public function fecharSlideOver(): void
    {
        $this->slideOverAberto = false;
        $this->estoqueSelecionadoId = null;
        $this->movimentacoes = [];
        $this->totalMovimentacoesItem = 0;
        $this->itemSelecionadoNome = '';
        $this->itemSelecionadoUnidade = '';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function dataService(): GestaoEstoqueDataService
    {
        return app(GestaoEstoqueDataService::class);
    }
}
