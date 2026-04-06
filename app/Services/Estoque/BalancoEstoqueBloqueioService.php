<?php

namespace App\Services\Estoque;

use App\Exceptions\ItemEmBalancoException;
use App\Models\BalancoEstoque;
use App\Models\BalancoEstoqueItem;
use App\Models\Enums\BalancoEstoqueStatus;
use Illuminate\Support\Collection;

class BalancoEstoqueBloqueioService
{
    public function assertItemDisponivel(int $itemId, ?int $ignorarBalancoId = null): void
    {
        $registro = $this->consultaBase([$itemId], $ignorarBalancoId)->first();

        if (! $registro) {
            return;
        }

        throw ItemEmBalancoException::porCodigo(
            $registro->item?->nome ?? 'Item',
            $registro->balanco?->codigo ?? 'sem código',
        );
    }

    public function buscarConflitos(array $itemIds, ?int $ignorarBalancoId = null): Collection
    {
        return $this->consultaBase($itemIds, $ignorarBalancoId)->get();
    }

    protected function consultaBase(array $itemIds, ?int $ignorarBalancoId = null)
    {
        return BalancoEstoqueItem::query()
            ->with(['item', 'balanco'])
            ->where('incluido_na_contagem', true)
            ->whereIn('item_id', $itemIds)
            ->whereHas('balanco', function ($query) use ($ignorarBalancoId) {
                $query->where('status', BalancoEstoqueStatus::EmAndamento);

                if ($ignorarBalancoId) {
                    $query->whereKeyNot($ignorarBalancoId);
                }
            });
    }
}
