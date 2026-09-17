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
            @input.debounce.300ms="filterSuggestions()"
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
    <div x-show="results.length" class="evento-local-map__results" role="listbox" aria-label="Locais encontrados">
        <template x-for="result in results" :key="result.place_id">
            <button type="button" role="option" @click="selectResult(result)">
                <strong x-text="result.name || result.label || result.display_name"></strong>
                <small x-text="result.display_name || 'Local salvo neste navegador'"></small>
            </button>
        </template>
    </div>
    <div x-ref="map" class="evento-local-map__canvas"></div>
    <p x-show="message" x-text="message" class="evento-local-map__message" role="status"></p>

    <style>
        .evento-local-map__search { display: flex; gap: .65rem; }
        .evento-local-map__search input { flex: 1; border: 1px solid #cbd5e1; border-radius: .65rem; padding: .65rem .8rem; }
        .evento-local-map__search button { border: 0; border-radius: .65rem; background: #17368d; color: #fff; padding: .65rem 1rem; font-weight: 600; }
        .evento-local-map__search button:disabled { opacity: .6; }
        .evento-local-map__hint, .evento-local-map__message { margin: .45rem 0; color: #64748b; font-size: .8rem; }
        .evento-local-map__results { display: grid; gap: .35rem; max-height: 12rem; overflow-y: auto; margin: .5rem 0; }
        .evento-local-map__results button { display: grid; gap: .12rem; padding: .55rem .7rem; border: 1px solid #dbe5f1; border-radius: .55rem; background: #fff; text-align: left; cursor: pointer; }
        .evento-local-map__results button:hover { border-color: #377bd1; background: #f2f7ff; }
        .evento-local-map__results strong { color: #17368d; font-size: .8rem; }
        .evento-local-map__results small { color: #64748b; font-size: .7rem; }
        .evento-local-map__canvas { height: 260px; border: 1px solid #cbd5e1; border-radius: .75rem; overflow: hidden; }
    </style>
</div>
