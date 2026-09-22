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
            placeholder="Busque um endereço para posicionar no mapa"
            aria-label="Buscar endereço no mapa"
        />
        <button type="button" @click="search()" :disabled="loading">
            <span x-text="loading ? 'Pesquisando…' : 'Pesquisar no mapa'"></span>
        </button>
    </div>

    <div class="evento-local-map__hint">
        Pesquise um endereço ou clique no mapa para marcar o ponto do evento. O campo “Endereço do mapa” será preenchido automaticamente.
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

</div>
