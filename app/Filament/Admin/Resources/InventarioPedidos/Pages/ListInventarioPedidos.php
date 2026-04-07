<?php

namespace App\Filament\Admin\Resources\InventarioPedidos\Pages;

use App\Filament\Admin\Resources\InventarioPedidos\InventarioPedidoResource;
use App\Models\Enums\InventarioPedidoStatus;
use App\Services\Inventario\InventarioContextService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListInventarioPedidos extends ListRecords
{
    protected static string $resource = InventarioPedidoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Novo Pedido')
                ->visible(fn (): bool => InventarioPedidoResource::canCreate()),
        ];
    }

    public function getTabs(): array
    {
        $user = Auth::user();

        if (! $user?->hasPermissionTo('Listar Pedidos de Inventário')) {
            return [];
        }

        $tabs = [];
        $tableQuery = $this->getTableQuery();

        $tabs['todos'] = Tab::make('Todos')
            ->badge(fn (): int => (clone $tableQuery)->count())
            ->modifyQueryUsing(fn (Builder $query) => $query->reorder()->orderByDesc('updated_at'));

        foreach (InventarioPedidoStatus::cases() as $status) {
            $count = (clone $tableQuery)->where('status', $status->value)->count();

            if ($count === 0) {
                continue;
            }

            $tabs[$status->value] = Tab::make($status->label())
                ->badge($count)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status->value))
                ->extraAttributes([
                    'style' => 'background-color: rgba(15, 118, 110, 0.08); border: 1px solid rgba(15, 118, 110, 0.12);',
                ]);
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): ?string
    {
        $query = $this->getTableQuery();

        foreach ([InventarioPedidoStatus::Pendente, InventarioPedidoStatus::Aprovado, InventarioPedidoStatus::EmAndamento] as $status) {
            if ((clone $query)->where('status', $status->value)->exists()) {
                return $status->value;
            }
        }

        return 'todos';
    }
}
