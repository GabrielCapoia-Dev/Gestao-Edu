<?php

namespace App\Filament\Admin\Resources\PedidosMerenda\Pages;

use App\Filament\Admin\Resources\PedidosMerenda\PedidosMerendaResource;
use App\Models\ContratoItem;
use App\Models\Item;
use App\Models\PedidoMerenda;
use App\Models\PedidoMerendaItem;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

class CreatePedidoMerenda extends Page
{
    protected static string $resource = PedidosMerendaResource::class;
    protected string $view = 'filament.pages.create-pedido-merenda';

    protected static ?string $title = 'Novo Pedido de Merenda';

    /** @var array<string, array<string, mixed>> */
    public array $itensPedido = [];

    public ?string $observacoes = null;

    public string $buscaItemDisponivel = '';

    public string $buscaItensPedido = '';

    public bool $modalAberto = false;

    public ?int $itemSelecionado = null;

    /** @var array<int, array<string, mixed>> */
    public array $contratosDoItem = [];

    public string $filtroEmpresaModal = '';

    public string $filtroContratoModal = '';

    public function abrirModal(): void
    {
        $this->itemSelecionado = null;
        $this->contratosDoItem = [];
        $this->buscaItemDisponivel = '';
        $this->filtroEmpresaModal = '';
        $this->filtroContratoModal = '';
        $this->modalAberto = true;
    }

    public function fecharModal(): void
    {
        $this->modalAberto = false;
        $this->itemSelecionado = null;
        $this->contratosDoItem = [];
        $this->filtroEmpresaModal = '';
        $this->filtroContratoModal = '';
    }

    public function updatedItemSelecionado(?int $value): void
    {
        $this->contratosDoItem = [];
        $this->filtroEmpresaModal = '';
        $this->filtroContratoModal = '';

        if (! $value) {
            return;
        }

        $registros = ContratoItem::query()
            ->with(['contrato.empresaContratada'])
            ->whereHas('contrato', fn ($query) => $query->where('ativo', true))
            ->where('item_id', $value)
            ->whereRaw('(quantidade_total - quantidade_utilizada - quantidade_reservada) > 0')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        foreach ($registros as $ci) {
            $this->contratosDoItem[$ci->id] = [
                'contrato_item_id' => $ci->id,
                'numero_contrato' => $ci->contrato->numero_contrato,
                'empresa' => $ci->contrato->empresaContratada->nome,
                'saldo' => (float) $ci->saldo_disponivel,
                'quantidade' => null,
            ];
        }
    }

    public function atualizarQuantidade(int $contratoItemId, ?string $valor): void
    {
        if (isset($this->contratosDoItem[$contratoItemId])) {
            $this->contratosDoItem[$contratoItemId]['quantidade'] = $valor !== '' ? $valor : null;
        }
    }

    public function confirmarAdicaoItem(): void
    {
        if (! $this->itemSelecionado) {
            Notification::make()
                ->title('Selecione um item.')
                ->warning()
                ->send();

            return;
        }

        $item = Item::find($this->itemSelecionado);
        $adicionados = 0;

        foreach ($this->contratosDoItem as $entry) {
            $quantidade = filled($entry['quantidade']) ? (float) $entry['quantidade'] : null;

            if (! $quantidade || $quantidade <= 0) {
                continue;
            }

            if ($quantidade > $entry['saldo']) {
                Notification::make()
                    ->title("Quantidade para \"{$entry['empresa']}\" excede o saldo disponivel ({$entry['saldo']}).")
                    ->danger()
                    ->send();

                return;
            }

            $chave = "ci_{$entry['contrato_item_id']}";

            $this->itensPedido = [
                $chave => [
                    'contrato_item_id' => $entry['contrato_item_id'],
                    'item_nome' => $item?->nome,
                    'unidade' => $item?->unidade_medida?->value,
                    'numero_contrato' => $entry['numero_contrato'],
                    'empresa' => $entry['empresa'],
                    'saldo' => $entry['saldo'],
                    'quantidade' => $quantidade,
                    'adicionado_em' => now()->toDateTimeString(),
                ],
            ] + $this->itensPedido;

            $adicionados++;
        }

        if ($adicionados === 0) {
            Notification::make()
                ->title('Preencha ao menos uma quantidade.')
                ->warning()
                ->send();

            return;
        }

        $this->fecharModal();

        Notification::make()
            ->title("{$adicionados} item(ns) adicionado(s) ao pedido.")
            ->success()
            ->send();
    }

    public function removerItem(string $chave): void
    {
        unset($this->itensPedido[$chave]);
    }

    public function confirmarPedido(): void
    {
        if (empty($this->itensPedido)) {
            Notification::make()
                ->title('Adicione ao menos um item ao pedido.')
                ->warning()
                ->send();

            return;
        }

        DB::transaction(function () {
            $pedido = PedidoMerenda::create([
                'observacoes' => $this->observacoes,
            ]);

            foreach ($this->itensPedido as $entry) {
                PedidoMerendaItem::create([
                    'pedido_merenda_id' => $pedido->id,
                    'contrato_item_id' => $entry['contrato_item_id'],
                    'quantidade_pedida' => $entry['quantidade'],
                ]);

                ContratoItem::where('id', $entry['contrato_item_id'])
                    ->increment('quantidade_reservada', $entry['quantidade']);
            }
        });

        Notification::make()
            ->title('Pedido criado com sucesso.')
            ->success()
            ->send();

        $this->redirect(PedidosMerendaResource::getUrl('index'));
    }

    public function getItensComSaldoProperty(): array
    {
        return Item::query()
            ->where('ativo', true)
            ->whereHas('contratoItens', function ($query) {
                $query->whereHas('contrato', fn ($contrato) => $contrato->where('ativo', true))
                    ->whereRaw('(quantidade_total - quantidade_utilizada - quantidade_reservada) > 0');
            })
            ->when(filled($this->buscaItemDisponivel), function ($query) {
                $busca = '%' . trim($this->buscaItemDisponivel) . '%';

                $query->where(function ($subquery) use ($busca) {
                    $subquery
                        ->where('nome', 'like', $busca)
                        ->orWhere('descricao', 'like', $busca)
                        ->orWhere('unidade_medida', 'like', $busca);
                });
            })
            ->orderBy('nome')
            ->limit(100)
            ->get()
            ->mapWithKeys(fn ($item) => [
                $item->id => "{$item->nome} - {$item->unidade_medida->value}",
            ])
            ->toArray();
    }

    public function getContratosFiltradosProperty(): array
    {
        return collect($this->contratosDoItem)
            ->filter(function (array $entry): bool {
                $filtroEmpresa = trim($this->filtroEmpresaModal);
                $filtroContrato = trim($this->filtroContratoModal);

                if ($filtroEmpresa !== '' && ! str_contains(mb_strtolower($entry['empresa']), mb_strtolower($filtroEmpresa))) {
                    return false;
                }

                if ($filtroContrato !== '' && ! str_contains(mb_strtolower($entry['numero_contrato']), mb_strtolower($filtroContrato))) {
                    return false;
                }

                return true;
            })
            ->sortBy([
                ['empresa', 'asc'],
                ['numero_contrato', 'asc'],
            ])
            ->all();
    }

    public function getItensPedidoFiltradosProperty(): array
    {
        return collect($this->itensPedido)
            ->filter(function (array $entry): bool {
                $busca = trim($this->buscaItensPedido);

                if ($busca === '') {
                    return true;
                }

                $busca = mb_strtolower($busca);

                return str_contains(mb_strtolower((string) $entry['item_nome']), $busca)
                    || str_contains(mb_strtolower((string) $entry['empresa']), $busca)
                    || str_contains(mb_strtolower((string) $entry['numero_contrato']), $busca);
            })
            ->all();
    }

    public function getTotalItensPedidoProperty(): int
    {
        return count($this->itensPedido);
    }

    public function getQuantidadeTotalPedidoProperty(): float
    {
        return (float) collect($this->itensPedido)->sum('quantidade');
    }

    public function getTotalContratosSelecionadosProperty(): int
    {
        return (int) collect($this->itensPedido)
            ->pluck('numero_contrato')
            ->filter()
            ->unique()
            ->count();
    }

    public function getTotalEmpresasSelecionadasProperty(): int
    {
        return (int) collect($this->itensPedido)
            ->pluck('empresa')
            ->filter()
            ->unique()
            ->count();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
