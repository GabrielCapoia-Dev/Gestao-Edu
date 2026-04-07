<?php

namespace App\Filament\Admin\Pages;

use App\Models\Enums\MotivoBaixa;
use App\Models\Enums\TipoItem;
use App\Models\InventarioEstoque;
use App\Services\Inventario\InventarioContextService;
use App\Services\Inventario\InventarioDataService;
use BackedEnum;
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

    public static function canAccess(): bool
    {
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

        $itens = $this->dataService()->itens($this->inventarioAtual, $this->filtrosExportacao);
        $movimentacoes = $this->dataService()->movimentacoesDoInventario($this->inventarioAtual);
        $baixas = $this->dataService()->baixasDoInventario($this->inventarioAtual);
        $metricas = $this->dataService()->metricasGerais($itens, $movimentacoes, $baixas);

        return [
            [
                'titulo' => 'Itens no inventario',
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
                'descricao' => 'itens com saldo ate 10 unidades',
                'cor' => 'amber',
            ],
            [
                'titulo' => 'Baixas registradas',
                'valor' => $metricas->total_baixas,
                'descricao' => number_format((float) $metricas->quantidade_baixada, 3, ',', '.') . ' unidades baixadas',
                'cor' => 'rose',
            ],
        ];
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

        return $this->dataService()->itens($this->inventarioAtual, $this->filtrosExportacao);
    }

    public function getItensFiltradosProperty(): Collection
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
        return Auth::user()?->hasPermissionTo('Exportar Relatórios') ?? false;
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

    protected function contextService(): InventarioContextService
    {
        return app(InventarioContextService::class);
    }
}
