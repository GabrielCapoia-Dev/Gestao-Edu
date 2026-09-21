@php
    $statePath = $getStatePath();
    $formStatePath = str($statePath)->beforeLast('.');
    $previewStatePath = $formStatePath.'.publico_preview_aplicado';
@endphp

<div class="evento-participantes-filter-action">
    <button type="button" wire:click="$wire.set(@js($previewStatePath), true)" class="evento-participantes-filter-action__button">
        <x-filament::icon icon="heroicon-m-funnel" class="evento-participantes-filter-action__icon" />
        <span>Filtrar participantes</span>
    </button>
    <span>Use os filtros acima e atualize a lista de pessoas convidadas.</span>
</div>
