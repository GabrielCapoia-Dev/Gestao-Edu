<div
    class="evento-local-map"
    x-data="eventoLocalMap()"
    x-init="init()"
    wire:ignore
>
    <div class="evento-local-map__search">
        <input
            type="search"
            x-model="query"
            @keydown.enter.prevent="search()"
            placeholder="Pesquise um endereço ou local"
            aria-label="Pesquisar local do evento"
        />
        <button type="button" @click="search()" :disabled="loading">
            <span x-text="loading ? 'Pesquisando…' : 'Pesquisar no mapa'"></span>
        </button>
    </div>

    <div class="evento-local-map__hint">
        Pesquise um local ou clique no mapa para marcar o ponto do evento. O endereço será preenchido automaticamente.
    </div>
    <div x-ref="map" class="evento-local-map__canvas"></div>
    <p x-show="message" x-text="message" class="evento-local-map__message" role="status"></p>

    <style>
        .evento-local-map__search { display: flex; gap: .65rem; }
        .evento-local-map__search input { flex: 1; border: 1px solid #cbd5e1; border-radius: .65rem; padding: .65rem .8rem; }
        .evento-local-map__search button { border: 0; border-radius: .65rem; background: #17368d; color: #fff; padding: .65rem 1rem; font-weight: 600; }
        .evento-local-map__search button:disabled { opacity: .6; }
        .evento-local-map__hint, .evento-local-map__message { margin: .45rem 0; color: #64748b; font-size: .8rem; }
        .evento-local-map__canvas { height: 260px; border: 1px solid #cbd5e1; border-radius: .75rem; overflow: hidden; }
    </style>
</div>
