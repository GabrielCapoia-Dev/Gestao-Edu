<?php

namespace App\Filament\Admin\Pages;

use App\Models\ContratoItem;
use App\Models\Contrato;
use App\Models\Item;
use App\Models\Enums\TipoItem;
use Filament\Pages\Page;
use BackedEnum;
use UnitEnum;
use Filament\Support\Icons\Heroicon;

class GestaoMargens extends Page
{
    protected string $view = 'filament.pages.gestao-margens';

    protected static ?string $title = 'Gestão de Margens';
    protected static ?string $slug = 'gestao-margens';
    protected static ?int $navigationSort = 3;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::ChartBar;
    protected static string|UnitEnum|null $navigationGroup = 'Alimentação Escolar';
    protected static ?string $navigationParentItem = 'Contratos';

    // Estado de ordenação
    public string $sortCol = 'nome';
    public string $sortDir = 'asc';


    public function sortBy(string $coluna): void
    {
        if ($this->sortCol === $coluna) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortCol = $coluna;
            $this->sortDir = 'asc';
        }
    }



    // -------------------------------------------------------------------------
    // Estado da página
    // -------------------------------------------------------------------------

    /** Aba (TipoItem value) ativa na tabela */
    public string $abaAtiva = 'todas';

    /** SlideOver aberto? */
    public bool $slideOverAberto = false;

    /** item_id do item selecionado para o slideOver */
    public ?int $itemSelecionadoId = null;

    /** Dados do slideOver */
    public array $contratosDoItem = [];
    public string $itemSelecionadoNome = '';
    public string $itemSelecionadoUnidade = '';

    // -------------------------------------------------------------------------
    // Computed: Cards do topo
    // -------------------------------------------------------------------------

    public function getCardsProperty(): array
    {
        $contratosAtivos = Contrato::where('ativo', true)->count();

        // Valor financeiro total disponível (saldo_disponivel * preco_unitario)
        $todosContratoItens = ContratoItem::query()
            ->whereHas('contrato', fn($q) => $q->where('ativo', true))
            ->get();

        $valorTotalDisponivel = $todosContratoItens->sum(
            fn($ci) => $ci->saldo_disponivel * (float) $ci->preco_unitario
        );

        // Itens com margem crítica: saldo disponível total <= 10% da quantidade total contratada
        $itensCriticos = $this->getItensMargem()
            ->filter(function ($item) {
                if ($item['total_contratado'] <= 0) return false;
                $percentual = ($item['saldo_disponivel'] / $item['total_contratado']) * 100;
                return $percentual <= 10 && $item['saldo_disponivel'] > 0;
            })
            ->count();

        // Itens completamente zerados
        $itensZerados = $this->getItensMargem()
            ->filter(fn($item) => $item['saldo_disponivel'] <= 0)
            ->count();

        return [
            [
                'titulo'    => 'Contratos Ativos',
                'valor'     => $contratosAtivos,
                'icone'     => 'heroicon-o-document-text',
                'cor'       => 'blue',
                'descricao' => 'contratos em vigor',
            ],
            [
                'titulo'    => 'Margem Crítica',
                'valor'     => $itensCriticos,
                'icone'     => 'heroicon-o-exclamation-triangle',
                'cor'       => 'amber',
                'descricao' => 'itens com ≤ 10% de saldo',
            ],
            [
                'titulo'    => 'Itens Esgotados',
                'valor'     => $itensZerados,
                'icone'     => 'heroicon-o-x-circle',
                'cor'       => 'red',
                'descricao' => 'sem saldo disponível',
            ],
            [
                'titulo'    => 'Valor Disponível',
                'valor'     => 'R$ ' . number_format($valorTotalDisponivel, 2, ',', '.'),
                'icone'     => 'heroicon-o-banknotes',
                'cor'       => 'green',
                'descricao' => 'em saldo financeiro',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Computed: Abas disponíveis (somente categorias com itens e saldo)
    // -------------------------------------------------------------------------

    public function getAbasProperty(): array
    {
        $categorias = $this->getItensMargem()
            ->filter(fn($item) => $item['saldo_disponivel'] > 0)
            ->pluck('tipo_item')
            ->unique()
            ->values();

        $abas = [['value' => 'todas', 'label' => 'Todos', 'icone' => null]];

        foreach (TipoItem::cases() as $tipo) {
            if ($categorias->contains($tipo->value)) {
                $abas[] = [
                    'value' => $tipo->value,
                    'label' => $tipo->label(),
                    'icone' => null,
                ];
            }
        }

        return $abas;
    }

    // -------------------------------------------------------------------------
    // Computed: Itens da tabela filtrados pela aba ativa
    // -------------------------------------------------------------------------

    public function getItensFiltradosProperty(): \Illuminate\Support\Collection
    {
        $itens = $this->getItensMargem();

        if ($this->abaAtiva !== 'todas') {
            $itens = $itens->filter(fn($item) => $item['tipo_item'] === $this->abaAtiva);
        }

        $dir = $this->sortDir === 'asc';

        return match ($this->sortCol) {
            'nome'              => $dir ? $itens->sortBy('nome', SORT_NATURAL | SORT_FLAG_CASE)
                : $itens->sortByDesc('nome', SORT_NATURAL | SORT_FLAG_CASE),
            'total_contratado'  => $dir ? $itens->sortBy('total_contratado')
                : $itens->sortByDesc('total_contratado'),
            'total_utilizado'   => $dir ? $itens->sortBy('total_utilizado')
                : $itens->sortByDesc('total_utilizado'),
            'total_reservado'   => $dir ? $itens->sortBy('total_reservado')
                : $itens->sortByDesc('total_reservado'),
            'saldo_disponivel'  => $dir ? $itens->sortBy('saldo_disponivel')
                : $itens->sortByDesc('saldo_disponivel'),
            'percentual'        => $dir ? $itens->sortBy('percentual')
                : $itens->sortByDesc('percentual'),
            'qtd_contratos'     => $dir ? $itens->sortBy('qtd_contratos')
                : $itens->sortByDesc('qtd_contratos'),
            default             => $itens->sortBy('nome', SORT_NATURAL | SORT_FLAG_CASE),
        };
    }
    // -------------------------------------------------------------------------
    // Lógica central: agrupa contratos por item_id
    // -------------------------------------------------------------------------

    protected function getItensMargem(): \Illuminate\Support\Collection
    {
        $contratoItens = ContratoItem::query()
            ->with(['item', 'contrato'])
            ->whereHas('contrato', fn($q) => $q->where('ativo', true))
            ->get();

        return $contratoItens
            ->groupBy('item_id')
            ->map(function ($grupo) {
                $primeiro = $grupo->first();
                $item     = $primeiro->item;

                $totalContratado  = $grupo->sum(fn($ci) => (float) $ci->quantidade_total);
                $totalUtilizado   = $grupo->sum(fn($ci) => (float) $ci->quantidade_utilizada);
                $totalReservado   = $grupo->sum(fn($ci) => (float) $ci->quantidade_reservada);
                $saldoDisponivel  = $grupo->sum(fn($ci) => (float) $ci->saldo_disponivel);
                $qtdContratos     = $grupo->count();

                $percentual = $totalContratado > 0
                    ? round(($saldoDisponivel / $totalContratado) * 100, 1)
                    : 0;

                return [
                    'item_id'          => $item->id,
                    'nome'             => $item->nome,
                    'unidade'          => $item->unidade_medida->value,
                    'tipo_item'        => $item->tipo_item->value,
                    'tipo_label'       => $item->tipo_item->label(),
                    'total_contratado' => $totalContratado,
                    'total_utilizado'  => $totalUtilizado,
                    'total_reservado'  => $totalReservado,
                    'saldo_disponivel' => $saldoDisponivel,
                    'percentual'       => $percentual,
                    'qtd_contratos'    => $qtdContratos,
                    'status'           => $this->resolverStatus($percentual, $saldoDisponivel),
                ];
            })
            ->values();
    }

    protected function resolverStatus(float $percentual, float $saldo): string
    {
        if ($saldo <= 0) return 'zerado';
        if ($percentual <= 10) return 'critico';
        if ($percentual <= 30) return 'baixo';
        return 'normal';
    }

    // -------------------------------------------------------------------------
    // Ações da tabela
    // -------------------------------------------------------------------------

    public function mudarAba(string $aba): void
    {
        $this->abaAtiva = $aba;
    }

    public function abrirSlideOver(int $itemId): void
    {
        $item = Item::find($itemId);

        if (! $item) return;

        $this->itemSelecionadoId     = $itemId;
        $this->itemSelecionadoNome   = $item->nome;
        $this->itemSelecionadoUnidade = $item->unidade_medida->value;

        $this->contratosDoItem = ContratoItem::query()
            ->with(['contrato.empresaContratada'])
            ->whereHas('contrato', fn($q) => $q->where('ativo', true))
            ->where('item_id', $itemId)
            ->get()
            ->map(function (ContratoItem $ci) {
                return [
                    'empresa'          => $ci->contrato->empresaContratada->nome,
                    'numero_contrato'  => $ci->contrato->numero_contrato,
                    'data_vencimento'  => $ci->contrato->data_vencimento?->format('d/m/Y') ?? 'Indeterminado',
                    'quantidade_total' => (float) $ci->quantidade_total,
                    'quantidade_utilizada' => (float) $ci->quantidade_utilizada,
                    'quantidade_reservada' => (float) $ci->quantidade_reservada,
                    'saldo_disponivel' => (float) $ci->saldo_disponivel,
                    'preco_unitario'   => (float) $ci->preco_unitario,
                    'valor_total_disponivel' => (float) $ci->saldo_disponivel * (float) $ci->preco_unitario,
                    'percentual'       => $ci->quantidade_total > 0
                        ? round(($ci->saldo_disponivel / $ci->quantidade_total) * 100, 1)
                        : 0,
                    'ativo'            => $ci->contrato->ativo,
                    'vencido'          => $ci->contrato->data_vencimento?->isPast() ?? false,
                ];
            })
            ->sortByDesc('saldo_disponivel')
            ->values()
            ->toArray();

        $this->slideOverAberto = true;
    }

    public function fecharSlideOver(): void
    {
        $this->slideOverAberto      = false;
        $this->itemSelecionadoId    = null;
        $this->contratosDoItem      = [];
        $this->itemSelecionadoNome  = '';
        $this->itemSelecionadoUnidade = '';
    }

    // -------------------------------------------------------------------------
    // Header actions (vazio por ora, seguindo padrão do projeto)
    // -------------------------------------------------------------------------

    protected function getHeaderActions(): array
    {
        return [];
    }
}
