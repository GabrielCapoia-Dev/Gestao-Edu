<?php

namespace App\Filament\Admin\Pages;

use App\Models\Enums\MotivoBaixa;
use App\Models\Enums\TipoItem;
use App\Models\InventarioEstoque;
use App\Services\Inventario\InventarioContextService;
use App\Services\Inventario\InventarioDataService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class GestaoInventario extends Page
{
    protected string $view = 'filament.pages.gestao-inventario';

    protected static ?string $title = 'Inventário Escolar';

    protected static ?string $slug = 'gestao-inventario';

    protected static ?int $navigationSort = 11;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|UnitEnum|null $navigationGroup = 'Alimentação Escolar';

    protected $queryString = [
        'inventario' => ['except' => null],
    ];

    public ?int $inventario = null;

    public string $busca = '';

    public int $porPagina = 8;

    public int $paginaAtual = 1;

    public string $sortCol = 'nome';

    public string $sortDir = 'asc';

    public string $abaAtiva = 'todas';

    public bool $slideOverAberto = false;

    public ?int $inventarioEstoqueSelecionadoId = null;

    public string $itemSelecionadoNome = '';

    public string $itemSelecionadoUnidade = '';

    public array $movimentacoes = [];

    public int $totalMovimentacoesItem = 0;

    public bool $modalBaixaAberto = false;

    public ?int $baixaInventarioEstoqueId = null;

    public string $baixaItemNome = '';

    public string $baixaQuantidade = '';

    public ?string $baixaMotivo = null;

    public string $baixaDescricao = '';

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        $inventarioAtual = $this->inventarioAtual;

        return view('filament.admin.pages.partials.page-header', [
            'actions' => $this->getCachedHeaderActions(),
            'eyebrow' => 'Inventário Escolar',
            'title' => $inventarioAtual?->escola?->nome ?? 'Inventário não selecionado',
            'description' => $inventarioAtual
                ? 'Gestão operacional do inventário da escola, com exportações, histórico e baixas.'
                : 'Selecione um inventário disponível para visualizar os dados.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pedidosEscola')
                ->label('Pedidos da escola')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('primary')
                ->url(route('filament.admin.resources.pedidos-inventario.index')),

            Action::make('balancos')
                ->label('Balanços')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('gray')
                ->url(route('filament.admin.resources.balancos-inventario.index')),

            Action::make('exportarPdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->visible(fn (): bool => $this->inventarioAtual !== null && $this->podeExportar)
                ->url(fn (): string => $this->inventarioAtual
                    ? route('gestao-inventario.relatorio.pdf', [
                        'inventario' => $this->inventarioAtual->id,
                        'async' => 1,
                    ] + $this->filtrosExportacao)
                    : '#')
                ->openUrlInNewTab(),

            Action::make('exportarXlsx')
                ->label('Exportar XLSX')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->visible(fn (): bool => $this->inventarioAtual !== null && $this->podeExportar)
                ->url(fn (): string => $this->inventarioAtual
                    ? route('gestao-inventario.relatorio.xlsx', [
                        'inventario' => $this->inventarioAtual->id,
                        'async' => 1,
                    ] + $this->filtrosExportacao)
                    : '#'),
        ];
    }

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return ($user?->hasPermissionTo('Listar Gestão de Inventário') ?? false)
            || ($user?->hasPermissionTo('Listar Inventários') ?? false);
    }

    public function mount(): void
    {
        if (! $this->inventario) {
            $this->inventario = $this->inventariosDisponiveis->keys()->map(fn ($id) => (int) $id)->first();
        }
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

    public function updatedInventario(): void
    {
        $this->paginaAtual = 1;
        $this->fecharSlideOver();
        $this->fecharModalBaixa();
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

    public function getInventarioAtualProperty()
    {
        return $this->contextService()->resolverInventario(Auth::user(), $this->inventario);
    }

    public function getInventariosDisponiveisProperty(): Collection
    {
        return $this->contextService()
            ->queryInventariosVisiveis(Auth::user())
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(fn ($inventario) => [$inventario->getKey() => $inventario->escola?->nome ?? $inventario->nome]);
    }

    public function getCardsProperty(): array
    {
        if (! $this->inventarioAtual) {
            return [];
        }

        return $this->dataService()->cards($this->inventarioAtual, $this->filtrosExportacao);
    }

    public function getAbasProperty(): array
    {
        if (! $this->inventarioAtual) {
            return [['value' => 'todas', 'label' => 'Todos']];
        }

        $categorias = $this->dataService()->categoriasDisponiveis($this->inventarioAtual);
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
        if (! $this->inventarioAtual) {
            return collect();
        }

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
        return Auth::user()?->hasPermissionTo('Exportar Relatórios') ?? false;
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
        $estoque = InventarioEstoque::with('item')->find($estoqueId);

        if (! $estoque || ! $estoque->item) {
            return;
        }

        $this->inventarioEstoqueSelecionadoId = $estoqueId;
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
        $this->inventarioEstoqueSelecionadoId = null;
        $this->movimentacoes = [];
        $this->totalMovimentacoesItem = 0;
        $this->itemSelecionadoNome = '';
        $this->itemSelecionadoUnidade = '';
    }

    public function abrirModalBaixa(int $estoqueId): void
    {
        $estoque = InventarioEstoque::with('item')->find($estoqueId);

        if (! $estoque || ! $estoque->item) {
            return;
        }

        $this->baixaInventarioEstoqueId = $estoque->getKey();
        $this->baixaItemNome = $estoque->item->nome;
        $this->baixaQuantidade = '';
        $this->baixaMotivo = MotivoBaixa::Perda->value;
        $this->baixaDescricao = '';
        $this->modalBaixaAberto = true;
    }

    public function fecharModalBaixa(): void
    {
        $this->modalBaixaAberto = false;
        $this->baixaInventarioEstoqueId = null;
        $this->baixaItemNome = '';
        $this->baixaQuantidade = '';
        $this->baixaMotivo = null;
        $this->baixaDescricao = '';
    }

    public function registrarBaixa(): void
    {
        $this->validate([
            'baixaInventarioEstoqueId' => ['required', 'integer'],
            'baixaQuantidade' => ['required', 'numeric', 'min:0.001'],
            'baixaMotivo' => ['required', 'string'],
            'baixaDescricao' => ['required', 'string', 'max:1000'],
        ]);

        $estoque = InventarioEstoque::find($this->baixaInventarioEstoqueId);

        if (! $estoque) {
            Notification::make()
                ->title('Item de inventario nao encontrado.')
                ->danger()
                ->send();

            return;
        }

        try {
            $estoque->registrarBaixa(
                (float) $this->baixaQuantidade,
                MotivoBaixa::from((string) $this->baixaMotivo),
                trim($this->baixaDescricao),
            );

            Notification::make()
                ->title('Baixa registrada com sucesso.')
                ->success()
                ->send();

            $this->fecharModalBaixa();
        } catch (\DomainException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function dataService(): InventarioDataService
    {
        return app(InventarioDataService::class);
    }

    protected function itensPaginados(): object
    {
        if (! $this->inventarioAtual) {
            return (object) [
                'itens' => collect(),
                'paginacao' => [
                    'total' => 0,
                    'porPagina' => $this->porPagina,
                    'paginaAtual' => 1,
                    'totalPaginas' => 1,
                    'de' => 0,
                    'ate' => 0,
                ],
            ];
        }

        return $this->dataService()->itensPaginados(
            $this->inventarioAtual,
            $this->filtrosExportacao,
            $this->paginaAtual,
            $this->porPagina,
        );
    }

    protected function contextService(): InventarioContextService
    {
        return app(InventarioContextService::class);
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
