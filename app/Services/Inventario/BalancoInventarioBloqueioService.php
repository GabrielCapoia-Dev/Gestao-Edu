<?php

namespace App\Services\Inventario;

use App\Exceptions\ItemEmBalancoException;
use App\Models\BalancoInventarioItem;
use App\Models\Enums\BalancoInventarioStatus;
use Illuminate\Support\Collection;

class BalancoInventarioBloqueioService
{
    public function assertItemDisponivel(int $inventarioId, int $itemId, ?int $ignorarBalancoId = null): void
    {
        $registro = $this->consultaBase($inventarioId, [$itemId], $ignorarBalancoId)->first();

        if (! $registro) {
            return;
        }

        throw ItemEmBalancoException::porCodigo(
            $registro->item?->nome ?? 'Item',
            $registro->balanco?->codigo ?? 'sem código',
        );
    }

    public function buscarConflitos(int $inventarioId, array $itemIds, ?int $ignorarBalancoId = null): Collection
    {
        return $this->consultaBase($inventarioId, $itemIds, $ignorarBalancoId)->get();
    }

    protected function consultaBase(int $inventarioId, array $itemIds, ?int $ignorarBalancoId = null)
    {
        return BalancoInventarioItem::query()
            ->with(['item', 'balanco'])
            ->where('incluido_na_contagem', true)
            ->whereIn('item_id', $itemIds)
            ->whereHas('balanco', function ($query) use ($inventarioId, $ignorarBalancoId) {
                $query
                    ->where('inventario_id', $inventarioId)
                    ->where('status', BalancoInventarioStatus::EmAndamento);

                if ($ignorarBalancoId) {
                    $query->whereKeyNot($ignorarBalancoId);
                }
            });
    }
}
