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

    public function getPedidosAguardandoProperty(): Collection
    {
        return $this->pedidos->where('status', StatusPedidoMerenda::Aguardando);
    }

    public function getPedidosParciaisProperty(): Collection
    {
        return $this->pedidos->where('status', StatusPedidoMerenda::ParcialmenteEntregue);
    }

    public function getPedidosFinalizadosProperty(): Collection
    {
        $status = [StatusPedidoMerenda::Entregue];

        if ($this->mostrarCancelados) {
            $status[] = StatusPedidoMerenda::Cancelado;
        }

        return $this->pedidos->filter(
            fn (PedidoMerenda $pedido) => in_array($pedido->status, $status, true)
        )->values();
    }

    public function getResumoCardsProperty(): array
    {
        $pedidos = $this->pedidos;
        $itens = $pedidos->flatMap->itens;

        return [
            [
                'titulo' => 'Pedidos visiveis',
                'valor' => $pedidos->count(),
                'meta' => $pedidos->where('status', '!=', StatusPedidoMerenda::Cancelado)->count() . ' ativos',
                'cor' => 'sky',
            ],
            [
                'titulo' => 'Aguardando entrega',
                'valor' => $pedidos->where('status', StatusPedidoMerenda::Aguardando)->count(),
                'meta' => number_format((float) $itens->where('quantidade_entregue', 0)->sum('quantidade_pedida'), 3, ',', '.') . ' volumes',
                'cor' => 'amber',
            ],
            [
                'titulo' => 'Entrega parcial',
                'valor' => $pedidos->where('status', StatusPedidoMerenda::ParcialmenteEntregue)->count(),
                'meta' => number_format((float) $itens->sum('quantidade_entregue'), 3, ',', '.') . ' entregues',
                'cor' => 'blue',
            ],
            [
                'titulo' => 'Empresas envolvidas',
                'valor' => $pedidos
                    ->flatMap(fn (PedidoMerenda $pedido) => $pedido->itens)
                    ->map(fn (PedidoMerendaItem $item) => $item->contratoItem?->contrato?->empresaContratada?->nome)
                    ->filter()
                    ->unique()
                    ->count(),
                'meta' => $pedidos->flatMap->itens->pluck('contrato_item_id')->filter()->unique()->count() . ' contratos/item',
                'cor' => 'rose',
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

        $itens = $pedido->itens;

        DB::transaction(function () use ($pedido, $itens) {
            foreach ($itens as $pedidoItem) {
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
            StatusPedidoMerenda::Aguardando => 'pm-status-waiting',
            StatusPedidoMerenda::ParcialmenteEntregue => 'pm-status-partial',
            StatusPedidoMerenda::Entregue => 'pm-status-done',
            StatusPedidoMerenda::Cancelado => 'pm-status-cancelled',
            default => 'pm-status-neutral',
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

    protected function getHeaderActions(): array
    {
        return [];
    }
}
