<div class="evento-participantes-filter-action">
    <button type="button" wire:click="$refresh" class="evento-participantes-filter-action__button">
        <x-filament::icon icon="heroicon-m-funnel" class="evento-participantes-filter-action__icon" />
        <span>Filtrar participantes</span>
    </button>
    <span>Use os filtros acima e atualize a lista de pessoas convidadas.</span>
</div>

<style>
    .evento-participantes-filter-action { display: flex; align-items: center; gap: .75rem; margin-top: -.25rem; }
    .evento-participantes-filter-action__button { display: inline-flex; align-items: center; gap: .45rem; border: 0; border-radius: .55rem; background: #173b91; color: #fff; padding: .58rem .9rem; font-size: .8rem; font-weight: 700; cursor: pointer; box-shadow: 0 4px 10px rgba(23, 59, 145, .2); }
    .evento-participantes-filter-action__button:hover { background: #2453b4; }
    .evento-participantes-filter-action__icon { width: 1rem; height: 1rem; }
    .evento-participantes-filter-action span:last-child { color: #64748b; font-size: .75rem; }
</style>
