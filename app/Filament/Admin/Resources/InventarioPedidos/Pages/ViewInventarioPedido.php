<?php

namespace App\Filament\Admin\Resources\InventarioPedidos\Pages;

use App\Filament\Admin\Resources\InventarioPedidos\InventarioPedidoResource;
use App\Models\Enums\InventarioPedidoItemStatus;
use App\Models\InventarioPedido;
use App\Models\Item;
use App\Services\Inventario\InventarioContextService;
use App\Services\Inventario\InventarioPedidoService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewInventarioPedido extends ViewRecord
{
    protected static string $resource = InventarioPedidoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportarRomaneio')
                ->label('Romaneio')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->visible(fn (): bool => filled($this->getRecord()->inventario_romaneio_id) && $this->ehGestorGeral())
                ->url(fn (): string => route('inventario-romaneios.relatorio.pdf', ['romaneio' => $this->getRecord()->romaneio]))
                ->openUrlInNewTab(),

            Action::make('aprovarPedido')
                ->label('Analisar Pedido')
                ->icon('heroicon-o-check-badge')
                ->color('info')
                ->visible(fn (): bool => $this->getRecord()->isPendente() && $this->pode('Aprovar Pedidos de Inventário') && $this->ehGestorGeral())
                ->modalWidth('6xl')
                ->fillForm(fn (): array => $this->dadosAnalisePedido())
                ->schema([
                    Repeater::make('itens')
                        ->label('Itens do pedido')
                        ->schema([
                            Hidden::make('item_id'),
                            TextInput::make('item_nome')
                                ->label('Item')
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpanFull(),
                            TextInput::make('quantidade_solicitada')
                                ->label('Qtd. solicitada')
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('quantidade_aprovada')
                                ->label('Qtd. aprovada')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->step('0.001'),
                            Textarea::make('observacao_aprovacao')
                                ->label('Observação de aprovação')
                                ->rows(2)
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->reorderable(false)
                        ->addable(false)
                        ->deletable(false),
                    Textarea::make('observacao_gestor')
                        ->label('Observação geral do gestor')
                        ->rows(4)
                        ->maxLength(2000),
                ])
                ->action(function (array $data): void {
                    try {
                        $this->service()->aprovarPedido(
                            $this->getRecord(),
                            $data['itens'] ?? [],
                            $data['observacao_gestor'] ?? null,
                            Auth::user(),
                        );

                        $this->refreshRecordState();

                        Notification::make()
                            ->title('Pedido analisado com sucesso.')
                            ->success()
                            ->send();
                    } catch (\DomainException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('confirmarRecebimento')
                ->label('Conferir Recebimento')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->isEmAndamento() && $this->pode('Conferir Pedidos de Inventário') && $this->podeConferirPedido())
                ->modalWidth('6xl')
                ->fillForm(fn (): array => $this->dadosConferenciaPedido())
                ->schema([
                    Repeater::make('itens')
                        ->label('Conferência dos itens')
                        ->schema([
                            Hidden::make('item_id'),
                            TextInput::make('item_nome')
                                ->label('Item')
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpanFull(),
                            TextInput::make('quantidade_aprovada')
                                ->label('Qtd. do romaneio')
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('quantidade_recebida')
                                ->label('Qtd. recebida')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->step('0.001'),
                            Textarea::make('observacao_conferencia')
                                ->label('Observação do item')
                                ->rows(2)
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->reorderable(false)
                        ->addable(false)
                        ->deletable(false),
                    Textarea::make('observacao_conferencia')
                        ->label('Observação geral da conferência')
                        ->rows(4)
                        ->helperText('Obrigatória quando qualquer item chegar com quantidade diferente da aprovada no romaneio.')
                        ->maxLength(2000),
                ])
                ->action(function (array $data): void {
                    try {
                        $this->service()->conferirEntrega(
                            $this->getRecord(),
                            $data['itens'] ?? [],
                            $data['observacao_conferencia'] ?? null,
                            Auth::user(),
                        );

                        $this->refreshRecordState();

                        Notification::make()
                            ->title('Recebimento conferido com sucesso.')
                            ->success()
                            ->send();
                    } catch (\DomainException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function service(): InventarioPedidoService
    {
        return app(InventarioPedidoService::class);
    }

    protected function refreshRecordState(): void
    {
        /** @var InventarioPedido $record */
        $record = InventarioPedidoResource::getEloquentQuery()->findOrFail($this->getRecord()->getKey());
        $this->record = $record;
    }

    protected function pode(string $permissao): bool
    {
        return Auth::user()?->hasPermissionTo($permissao) ?? false;
    }

    protected function ehGestorGeral(): bool
    {
        return app(InventarioContextService::class)->ehGestorGeral(Auth::user());
    }

    protected function podeConferirPedido(): bool
    {
        if ($this->ehGestorGeral()) {
            return true;
        }

        return (int) $this->getRecord()->escola_id === (int) Auth::user()?->id_escola;
    }

    protected function dadosAnalisePedido(): array
    {
        return [
            'itens' => $this->getRecord()->itens()
                ->with('item')
                ->get()
                ->map(fn ($item): array => [
                    'item_id' => $item->item_id,
                    'item_nome' => $this->formatarNomeItem($item->item),
                    'quantidade_solicitada' => (float) $item->quantidade_solicitada,
                    'quantidade_aprovada' => (float) $item->quantidade_solicitada,
                    'observacao_aprovacao' => $item->observacao_aprovacao,
                ])
                ->all(),
            'observacao_gestor' => $this->getRecord()->observacao_gestor,
        ];
    }

    protected function dadosConferenciaPedido(): array
    {
        return [
            'itens' => $this->getRecord()->itens()
                ->with('item')
                ->get()
                ->reject(fn ($item) => $item->status === InventarioPedidoItemStatus::Recusado)
                ->map(fn ($item): array => [
                    'item_id' => $item->item_id,
                    'item_nome' => $this->formatarNomeItem($item->item),
                    'quantidade_aprovada' => (float) ($item->quantidade_aprovada ?? 0),
                    'quantidade_recebida' => (float) ($item->quantidade_aprovada ?? 0),
                    'observacao_conferencia' => $item->observacao_conferencia,
                ])
                ->all(),
            'observacao_conferencia' => $this->getRecord()->observacao_conferencia,
        ];
    }

    protected function formatarNomeItem(?Item $item): string
    {
        if (! $item) {
            return 'Item indisponível - N/A';
        }

        return $item->nome . ' - ' . strtoupper($item->unidade_medida?->value ?? 'N/A');
    }
}
