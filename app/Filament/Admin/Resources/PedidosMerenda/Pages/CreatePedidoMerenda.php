<?php

namespace App\Filament\Admin\Resources\PedidosMerenda\Pages;

use App\Filament\Admin\Resources\PedidosMerenda\PedidosMerendaResource;
use App\Models\ContratoItem;
use App\Models\Item;
use App\Models\PedidoMerenda;
use App\Models\PedidoMerendaItem;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreatePedidoMerenda extends Page
{
    protected static string $resource = PedidosMerendaResource::class;
    protected static string $view = 'filament.pages.create-pedido-merenda';

    // -------------------------------------------------------------------------
    // Estado em memória — nada vai para o banco até confirmarPedido()
    // -------------------------------------------------------------------------

    /** @var array Lista de itens montados pelo usuário antes de confirmar */
    public array $itensPedido = [];

    /** Observações gerais do pedido */
    public ?string $observacoes = null;

    // -------------------------------------------------------------------------
    // Estado do modal
    // -------------------------------------------------------------------------

    public bool $modalAberto = false;

    /** item_id selecionado no modal */
    public ?int $itemSelecionado = null;

    /**
     * Contratos com saldo do item selecionado.
     * Formato: [ contrato_item_id => [ 'contrato_item_id', 'numero_contrato', 'empresa', 'saldo', 'quantidade' ] ]
     */
    public array $contratosDoItem = [];

    // -------------------------------------------------------------------------
    // Abrir / fechar modal
    // -------------------------------------------------------------------------

    public function abrirModal(): void
    {
        $this->itemSelecionado  = null;
        $this->contratosDoItem  = [];
        $this->modalAberto      = true;
    }

    public function fecharModal(): void
    {
        $this->modalAberto     = false;
        $this->itemSelecionado = null;
        $this->contratosDoItem = [];
    }

    // -------------------------------------------------------------------------
    // Quando o usuário seleciona um item no modal
    // -------------------------------------------------------------------------

    public function updatedItemSelecionado(?int $value): void
    {
        $this->contratosDoItem = [];

        if (! $value) {
            return;
        }

        // Busca todos os contrato_item de contratos ATIVOS
        // que tenham saldo disponível (total - utilizada - reservada > 0)
        $registros = ContratoItem::query()
            ->with(['contrato.empresaContratada'])
            ->whereHas('contrato', fn($q) => $q->where('ativo', true))
            ->where('item_id', $value)
            ->get()
            ->filter(fn($ci) => $ci->saldo_disponivel > 0);

        foreach ($registros as $ci) {
            $this->contratosDoItem[$ci->id] = [
                'contrato_item_id' => $ci->id,
                'numero_contrato'  => $ci->contrato->numero_contrato,
                'empresa'          => $ci->contrato->empresaContratada->nome,
                'saldo'            => (float) $ci->saldo_disponivel,
                'quantidade'       => null, // preenchido pelo usuário
            ];
        }
    }

    // -------------------------------------------------------------------------
    // Atualiza quantidade digitada para um contrato específico
    // -------------------------------------------------------------------------

    public function atualizarQuantidade(int $contratoItemId, ?string $valor): void
    {
        if (isset($this->contratosDoItem[$contratoItemId])) {
            $this->contratosDoItem[$contratoItemId]['quantidade'] = $valor !== '' ? $valor : null;
        }
    }

    // -------------------------------------------------------------------------
    // Confirma adição dos itens do modal para a lista em memória
    // -------------------------------------------------------------------------

    public function confirmarAdicaoItem(): void
    {
        if (! $this->itemSelecionado) {
            Notification::make()
                ->title('Selecione um item.')
                ->warning()
                ->send();
            return;
        }

        $adicionados = 0;

        foreach ($this->contratosDoItem as $entry) {
            $quantidade = filled($entry['quantidade']) ? (float) $entry['quantidade'] : null;

            if (! $quantidade || $quantidade <= 0) {
                continue; // usuário deixou em branco = não quer pedir desse contrato
            }

            if ($quantidade > $entry['saldo']) {
                Notification::make()
                    ->title("Quantidade para \"{$entry['empresa']}\" excede o saldo disponível ({$entry['saldo']}).")
                    ->danger()
                    ->send();
                return;
            }

            // Chave única para evitar duplicata na lista em memória
            $chave = "ci_{$entry['contrato_item_id']}";

            $item = Item::find($this->itemSelecionado);

            $this->itensPedido[$chave] = [
                'contrato_item_id' => $entry['contrato_item_id'],
                'item_nome'        => $item?->nome,
                'unidade'          => $item?->unidade_medida?->value,
                'numero_contrato'  => $entry['numero_contrato'],
                'empresa'          => $entry['empresa'],
                'saldo'            => $entry['saldo'],
                'quantidade'       => $quantidade,
            ];

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

    // -------------------------------------------------------------------------
    // Remove item da lista em memória
    // -------------------------------------------------------------------------

    public function removerItem(string $chave): void
    {
        unset($this->itensPedido[$chave]);
    }

    // -------------------------------------------------------------------------
    // Confirma o pedido — única operação que persiste no banco
    // -------------------------------------------------------------------------

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
                    'contrato_item_id'  => $entry['contrato_item_id'],
                    'quantidade_pedida' => $entry['quantidade'],
                ]);

                // Reserva o saldo no contrato_item
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

    // -------------------------------------------------------------------------
    // Opções do select de item (apenas itens com saldo em algum contrato ativo)
    // -------------------------------------------------------------------------

    public function getItensComSaldoProperty(): array
    {
        $idsComSaldo = ContratoItem::query()
            ->whereHas('contrato', fn($q) => $q->where('ativo', true))
            ->get()
            ->filter(fn($ci) => $ci->saldo_disponivel > 0)
            ->pluck('item_id')
            ->unique();

        return Item::whereIn('id', $idsComSaldo)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get()
            ->mapWithKeys(fn($item) => [
                $item->id => "{$item->nome} - {$item->unidade_medida->value}",
            ])
            ->toArray();
    }

    // -------------------------------------------------------------------------
    // Actions do header
    // -------------------------------------------------------------------------

    protected function getHeaderActions(): array
    {
        return [];
    }
}