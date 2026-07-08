<?php

namespace App\Filament\Admin\Pages;

use App\Models\Enums\TipoItem;
use App\Models\Estoque;
use App\Services\Estoque\GestaoEstoqueDataService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
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

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),

            'eyebrow' => 'Alimentação Escolar',
            'title' => "Gestão de Estoque",
            'description' => 'Acompanhe o estoque de itens, visualize alertas para itens com baixo saldo e gerencie as movimentações de forma eficiente.',
        ]);
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', Estoque::class);
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
        return $this->dataService()->cards();
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

    public function getItensFiltradosBaseProperty(): Collection
    {
        return $this->itensPaginados()->itens;
    }

    public function getItensFiltradosProperty(): Collection
    {
        return $this->itensPaginados()->itens;
    }

    public function getPaginacaoProperty(): array
    {
        return $this->itensPaginados()->paginacao;
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
        return Gate::allows('exportReports');
    }

    public function mudarAba(string $aba): void
    {
        $this->abaAtiva = $aba;
        $this->paginaAtual = 1;
    }

    public function mudarPagina(int $pagina): void
    {
        $totalPaginas = max(1, (int) $this->paginacao['totalPaginas']);

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
        return [
            Action::make('pedidos')
                ->label('Pedidos')
                ->icon('heroicon-o-list-bullet')
                ->color('gray')
                ->url(route('filament.admin.resources.pedidos-merenda.index')),

            Action::make('novoPedido')
                ->label('Novo Pedido')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(route('filament.admin.resources.pedidos-merenda.create')),

            Action::make('baixas')
                ->label('Baixas')
                ->icon('heroicon-o-arrow-trending-down')
                ->color('warning')
                ->url(route('filament.admin.resources.baixas-estoque.index')),

            Action::make('listagemBaixas')
                ->label('Listagem de Baixas')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->url(route('filament.admin.resources.historico-baixas-estoque.index')),

            Action::make('exportarPdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->visible(fn (): bool => $this->podeExportar)
                ->url(fn (): string => route('gestao-estoque.relatorio.pdf', array_merge(
                    $this->filtrosExportacao,
                    ['async' => 1],
                ))),

            Action::make('exportarXlsx')
                ->label('Exportar XLSX')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->visible(fn (): bool => $this->podeExportar)
                ->url(fn (): string => route('gestao-estoque.relatorio.xlsx', array_merge(
                    $this->filtrosExportacao,
                    ['async' => 1],
                ))),
        ];
    }

    protected function dataService(): GestaoEstoqueDataService
    {
        return app(GestaoEstoqueDataService::class);
    }

    protected function itensPaginados(): object
    {
        return $this->dataService()->itensPaginados(
            $this->filtrosExportacao,
            $this->paginaAtual,
            $this->porPagina,
        );
    }

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }
}
