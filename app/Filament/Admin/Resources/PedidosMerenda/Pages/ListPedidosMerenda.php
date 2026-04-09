<?php

namespace App\Filament\Admin\Resources\PedidosMerenda\Pages;

use App\Filament\Admin\Resources\PedidosMerenda\PedidosMerendaResource;
use App\Models\Enums\StatusPedidoMerenda;
use App\Models\Estoque;
use App\Models\PedidoMerenda;
use App\Models\PedidoMerendaItem;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListPedidosMerenda extends Page
{
    protected static string $resource = PedidosMerendaResource::class;

    protected string $view = 'filament.pages.list-pedidos-merenda';

    protected static ?string $title = 'Pedidos de Merenda';

    public string $busca = '';

    public array $statusSelecionados = [];

    public ?string $criadoPor = null;

    public ?string $dataInicio = null;

    public ?string $dataFim = null;

    public bool $mostrarCancelados = false;

    public int $porPagina = 5;

    public int $paginaAguardando = 1;

    public int $paginaParcial = 1;

    public int $paginaFinalizado = 1;

    public bool $modalItensAberto = false;

    public ?int $pedidoSelecionadoId = null;

    public function mount(): void
    {
        $this->statusSelecionados = [
            StatusPedidoMerenda::Aguardando->value,
            StatusPedidoMerenda::ParcialmenteEntregue->value,
            StatusPedidoMerenda::Entregue->value,
        ];
    }

    public function updatedBusca(): void
    {
        $this->resetPaginas();
    }

    public function updatedCriadoPor(): void
    {
        $this->resetPaginas();
    }

    public function updatedDataInicio(): void
    {
        $this->resetPaginas();
    }

    public function updatedDataFim(): void
    {
        $this->resetPaginas();
    }

    public function updatedStatusSelecionados(): void
    {
        $this->resetPaginas();
    }

    public function updatedPorPagina(): void
    {
        $this->resetPaginas();
    }

    public function updatedMostrarCancelados(bool $value): void
    {
        if ($value && ! in_array(StatusPedidoMerenda::Cancelado->value, $this->statusSelecionados, true)) {
            $this->statusSelecionados[] = StatusPedidoMerenda::Cancelado->value;
        }

        if (! $value) {
            $this->statusSelecionados = array_values(array_filter(
                $this->statusSelecionados,
                fn (string $status) => $status !== StatusPedidoMerenda::Cancelado->value
            ));
        }

        $this->resetPaginas();
    }

    public function getPedidosProperty(): Collection
    {
        return $this->buildPedidosQuery()
            ->with([
                'itens.contratoItem.item',
                'itens.contratoItem.contrato.empresaContratada',
            ])
            ->get()
            ->sortByDesc('created_at')
            ->values();
    }

    public function getPedidoSelecionadoProperty(): ?PedidoMerenda
    {
        if (! $this->pedidoSelecionadoId) {
            return null;
        }

        return PedidoMerenda::query()
            ->with([
                'itens.contratoItem.item',
                'itens.contratoItem.contrato.empresaContratada',
            ])
            ->find($this->pedidoSelecionadoId);
    }

    public function getPedidosAguardandoBaseProperty(): Collection
    {
        return $this->pedidos->where('status', StatusPedidoMerenda::Aguardando)->values();
    }

    public function getPedidosParciaisBaseProperty(): Collection
    {
        return $this->pedidos->where('status', StatusPedidoMerenda::ParcialmenteEntregue)->values();
    }

    public function getPedidosFinalizadosBaseProperty(): Collection
    {
        $status = [StatusPedidoMerenda::Entregue];

        if ($this->mostrarCancelados) {
            $status[] = StatusPedidoMerenda::Cancelado;
        }

        return $this->pedidos
            ->filter(fn (PedidoMerenda $pedido) => in_array($pedido->status, $status, true))
            ->values();
    }

    public function getPedidosAguardandoProperty(): Collection
    {
        return $this->sliceCollection($this->pedidosAguardandoBase, $this->paginaAguardando);
    }

    public function getPedidosParciaisProperty(): Collection
    {
        return $this->sliceCollection($this->pedidosParciaisBase, $this->paginaParcial);
    }

    public function getPedidosFinalizadosProperty(): Collection
    {
        return $this->sliceCollection($this->pedidosFinalizadosBase, $this->paginaFinalizado);
    }

    public function getPaginacaoAguardandoProperty(): array
    {
        return $this->buildPaginacao($this->pedidosAguardandoBase, $this->paginaAguardando);
    }

    public function getPaginacaoParcialProperty(): array
    {
        return $this->buildPaginacao($this->pedidosParciaisBase, $this->paginaParcial);
    }

    public function getPaginacaoFinalizadoProperty(): array
    {
        return $this->buildPaginacao($this->pedidosFinalizadosBase, $this->paginaFinalizado);
    }

    public function getResumoCardsProperty(): array
    {
        $pedidos = $this->pedidos;
        $itens = $pedidos->flatMap->itens;

        return [
            [
                'titulo' => 'Pedidos visiveis',
                'valor' => $pedidos->count(),
                'descricao' => $pedidos->where('status', '!=', StatusPedidoMerenda::Cancelado)->count() . ' ativos no filtro atual',
            ],
            [
                'titulo' => 'Aguardando entrega',
                'valor' => $pedidos->where('status', StatusPedidoMerenda::Aguardando)->count(),
                'descricao' => number_format((float) $itens->where('quantidade_entregue', 0)->sum('quantidade_pedida'), 3, ',', '.') . ' unidades ainda sem entrega',
            ],
            [
                'titulo' => 'Entrega parcial',
                'valor' => $pedidos->where('status', StatusPedidoMerenda::ParcialmenteEntregue)->count(),
                'descricao' => number_format((float) $itens->sum('quantidade_entregue'), 3, ',', '.') . ' unidades entregues',
            ],
            [
                'titulo' => 'Empresas envolvidas',
                'valor' => $pedidos
                    ->flatMap->itens
                    ->map(fn (PedidoMerendaItem $item) => $item->contratoItem?->contrato?->empresaContratada?->nome)
                    ->filter()
                    ->unique()
                    ->count(),
                'descricao' => $pedidos->flatMap->itens->pluck('contrato_item_id')->filter()->unique()->count() . ' contratos relacionados',
            ],
        ];
    }

    public function getCriadoresDisponiveisProperty(): array
    {
        return PedidoMerenda::query()
            ->whereNotNull('criado_por')
            ->orderBy('criado_por')
            ->pluck('criado_por')
            ->unique()
            ->values()
            ->all();
    }

    public function abrirModalItens(int $pedidoId): void
    {
        $this->pedidoSelecionadoId = $pedidoId;
        $this->modalItensAberto = true;
    }

    public function fecharModalItens(): void
    {
        $this->modalItensAberto = false;
        $this->pedidoSelecionadoId = null;
    }

    public function limparFiltros(): void
    {
        $this->reset([
            'busca',
            'criadoPor',
            'dataInicio',
            'dataFim',
            'mostrarCancelados',
        ]);

        $this->statusSelecionados = [
            StatusPedidoMerenda::Aguardando->value,
            StatusPedidoMerenda::ParcialmenteEntregue->value,
            StatusPedidoMerenda::Entregue->value,
        ];

        $this->porPagina = 5;
        $this->resetPaginas();
    }

    public function mudarPagina(string $secao, int $pagina): void
    {
        $property = $this->paginaProperty($secao);
        $paginacao = match ($secao) {
            'aguardando' => $this->paginacaoAguardando,
            'parcial' => $this->paginacaoParcial,
            'finalizado' => $this->paginacaoFinalizado,
            default => ['totalPaginas' => 1],
        };

        $this->{$property} = max(1, min($pagina, $paginacao['totalPaginas']));
    }

    public function salvarQuantidade(int $pedidoItemId, float $novaQuantidade): void
    {
        $pedidoItem = PedidoMerendaItem::with(['contratoItem', 'pedido'])->findOrFail($pedidoItemId);
        $quantidadeAnterior = (float) $pedidoItem->quantidade_pedida;
        $jaEntregue = (float) $pedidoItem->quantidade_entregue;

        if ($novaQuantidade < 0) {
            Notification::make()->title('Quantidade invalida.')->danger()->send();

            return;
        }

        if ($novaQuantidade < $jaEntregue) {
            Notification::make()
                ->title("Nao e possivel reduzir abaixo da quantidade ja entregue ({$jaEntregue}).")
                ->danger()
                ->send();

            return;
        }

        $contratoItem = $pedidoItem->contratoItem;
        $saldoMaximo = (float) $contratoItem->saldo_disponivel + $quantidadeAnterior;

        if ($novaQuantidade > $saldoMaximo) {
            Notification::make()
                ->title("Quantidade excede o saldo disponivel ({$saldoMaximo}).")
                ->danger()
                ->send();

            return;
        }

        $diferenca = $novaQuantidade - $quantidadeAnterior;

        DB::transaction(function () use ($pedidoItem, $contratoItem, $novaQuantidade, $diferenca) {
            $pedidoItem->update(['quantidade_pedida' => $novaQuantidade]);
            $contratoItem->increment('quantidade_reservada', $diferenca);
            $pedidoItem->pedido->recalcularStatus();
        });

        Notification::make()
            ->title('Quantidade atualizada com sucesso.')
            ->success()
            ->send();
    }

    public function salvarEntregaParcial(int $pedidoItemId, float $quantidadeEntregaAgora): void
    {
        $pedidoItem = PedidoMerendaItem::with(['contratoItem.item', 'pedido'])->findOrFail($pedidoItemId);
        $pendente = (float) $pedidoItem->quantidade_pendente;

        if ($quantidadeEntregaAgora <= 0) {
            Notification::make()->title('Informe uma quantidade maior que zero.')->warning()->send();

            return;
        }

        if ($quantidadeEntregaAgora > $pendente) {
            Notification::make()
                ->title("Quantidade excede o saldo pendente de entrega ({$pendente}).")
                ->danger()
                ->send();

            return;
        }

        $contratoItem = $pedidoItem->contratoItem;
        $pedido = $pedidoItem->pedido;

        try {
            DB::transaction(function () use ($pedidoItem, $contratoItem, $pedido, $quantidadeEntregaAgora) {
                $pedidoItem->increment('quantidade_entregue', $quantidadeEntregaAgora);

                $contratoItem->decrement('quantidade_reservada', $quantidadeEntregaAgora);
                $contratoItem->increment('quantidade_utilizada', $quantidadeEntregaAgora);

                $estoque = Estoque::firstOrCreate(
                    ['item_id' => $contratoItem->item_id],
                    ['quantidade' => 0]
                );

                $estoque->entrada(
                    quantidade: $quantidadeEntregaAgora,
                    pedidoMerendaId: $pedido->id,
                    observacao: "Entrega parcial do pedido #{$pedido->id}",
                );

                $pedido->recalcularStatus();
            });
        } catch (\DomainException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Entrega registrada com sucesso.')
            ->success()
            ->send();
    }

    public function cancelarPedido(int $pedidoId): void
    {
        $pedido = PedidoMerenda::query()
            ->with('itens.contratoItem')
            ->findOrFail($pedidoId);

        DB::transaction(function () use ($pedido) {
            foreach ($pedido->itens as $pedidoItem) {
                $pendente = (float) $pedidoItem->quantidade_pendente;

                if ($pendente <= 0) {
                    continue;
                }

                $pedidoItem->contratoItem->decrement('quantidade_reservada', $pendente);
            }

            $pedido->update(['status' => StatusPedidoMerenda::Cancelado]);
        });

        Notification::make()
            ->title("Pedido #{$pedido->id} cancelado. Saldo pendente devolvido aos contratos.")
            ->warning()
            ->send();

        if ($this->pedidoSelecionadoId === $pedidoId) {
            $this->fecharModalItens();
        }
    }

    public function formatarStatus(StatusPedidoMerenda|string|null $status): string
    {
        if ($status instanceof StatusPedidoMerenda) {
            return $status->label();
        }

        return StatusPedidoMerenda::tryFrom((string) $status)?->label() ?? '-';
    }

    public function badgeStatusClasse(StatusPedidoMerenda|string|null $status): string
    {
        $status = $status instanceof StatusPedidoMerenda ? $status : StatusPedidoMerenda::tryFrom((string) $status);

        return match ($status) {
            StatusPedidoMerenda::Aguardando => 'pm-badge-warning',
            StatusPedidoMerenda::ParcialmenteEntregue => 'pm-badge-info',
            StatusPedidoMerenda::Entregue => 'pm-badge-success',
            StatusPedidoMerenda::Cancelado => 'pm-badge-danger',
            default => 'pm-badge-neutral',
        };
    }

    protected function buildPedidosQuery(): Builder
    {
        return PedidoMerenda::query()
            ->withCount('itens')
            ->withSum('itens as quantidade_total_pedida', 'quantidade_pedida')
            ->withSum('itens as quantidade_total_entregue', 'quantidade_entregue')
            ->when($this->busca !== '', function (Builder $query) {
                $busca = '%' . trim($this->busca) . '%';

                $query->where(function (Builder $subquery) use ($busca) {
                    $subquery
                        ->where('id', 'like', $busca)
                        ->orWhere('observacoes', 'like', $busca)
                        ->orWhere('criado_por', 'like', $busca)
                        ->orWhereHas('itens.contratoItem.item', fn (Builder $itemQuery) => $itemQuery->where('nome', 'like', $busca))
                        ->orWhereHas('itens.contratoItem.contrato', fn (Builder $contratoQuery) => $contratoQuery->where('numero_contrato', 'like', $busca))
                        ->orWhereHas(
                            'itens.contratoItem.contrato.empresaContratada',
                            fn (Builder $empresaQuery) => $empresaQuery->where('nome', 'like', $busca)
                        );
                });
            })
            ->when($this->criadoPor, fn (Builder $query) => $query->where('criado_por', $this->criadoPor))
            ->when($this->dataInicio, fn (Builder $query) => $query->whereDate('created_at', '>=', $this->dataInicio))
            ->when($this->dataFim, fn (Builder $query) => $query->whereDate('created_at', '<=', $this->dataFim))
            ->when(
                $this->statusSelecionados !== [],
                fn (Builder $query) => $query->whereIn('status', $this->statusSelecionados)
            );
    }

    protected function sliceCollection(Collection $items, int $pagina): Collection
    {
        return $items
            ->slice(($pagina - 1) * $this->porPagina, $this->porPagina)
            ->values();
    }

    protected function buildPaginacao(Collection $items, int $paginaAtual): array
    {
        $total = $items->count();
        $totalPaginas = $total > 0 ? (int) ceil($total / $this->porPagina) : 1;

        return [
            'total' => $total,
            'porPagina' => $this->porPagina,
            'paginaAtual' => max(1, min($paginaAtual, $totalPaginas)),
            'totalPaginas' => $totalPaginas,
            'de' => $total === 0 ? 0 : (($paginaAtual - 1) * $this->porPagina) + 1,
            'ate' => min($paginaAtual * $this->porPagina, $total),
        ];
    }

    protected function paginaProperty(string $secao): string
    {
        return match ($secao) {
            'aguardando' => 'paginaAguardando',
            'parcial' => 'paginaParcial',
            'finalizado' => 'paginaFinalizado',
            default => 'paginaAguardando',
        };
    }

    protected function resetPaginas(): void
    {
        $this->paginaAguardando = 1;
        $this->paginaParcial = 1;
        $this->paginaFinalizado = 1;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
