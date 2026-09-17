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

    <script>
        function eventoLocalMap() {
            return {
                map: null,
                marker: null,
                query: '',
                loading: false,
                message: '',
                localInput() { return document.querySelector('input[name$="[local]"]'); },
                latitudeInput() { return document.querySelector('input[name$="[latitude]"]'); },
                longitudeInput() { return document.querySelector('input[name$="[longitude]"]'); },
                async init() {
                    if (this.$refs.map.dataset.initialized) return;
                    while (! window.L) await new Promise(resolve => setTimeout(resolve, 50));
                    this.$refs.map.dataset.initialized = 'true';
                    this.map = L.map(this.$refs.map).setView([-15.78, -47.93], 4);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors',
                    }).addTo(this.map);
                    this.map.on('click', ({ latlng }) => this.selectPoint(latlng.lat, latlng.lng, true));
                    const lat = parseFloat(this.latitudeInput()?.value);
                    const lng = parseFloat(this.longitudeInput()?.value);
                    if (Number.isFinite(lat) && Number.isFinite(lng)) this.selectPoint(lat, lng, false);
                    setTimeout(() => this.map.invalidateSize(), 200);
                },
                async search() {
                    if (! this.query.trim()) return;
                    this.loading = true;
                    this.message = '';
                    try {
                        const url = new URL('https://nominatim.openstreetmap.org/search');
                        url.search = new URLSearchParams({ q: this.query.trim(), format: 'jsonv2', limit: '1', 'accept-language': 'pt-BR' });
                        const response = await fetch(url, { headers: { Accept: 'application/json' } });
                        const results = await response.json();
                        if (! results.length) { this.message = 'Nenhum local encontrado.'; return; }
                        const result = results[0];
                        await this.selectPoint(parseFloat(result.lat), parseFloat(result.lon), false, result.display_name);
                    } catch (error) {
                        this.message = 'Não foi possível pesquisar o local agora.';
                    } finally { this.loading = false; }
                },
                async selectPoint(lat, lng, reverse = true, label = null) {
                    this.latitudeInput().value = lat.toFixed(7);
                    this.longitudeInput().value = lng.toFixed(7);
                    this.latitudeInput().dispatchEvent(new Event('input', { bubbles: true }));
                    this.longitudeInput().dispatchEvent(new Event('input', { bubbles: true }));
                    if (! this.marker) this.marker = L.marker([lat, lng], { draggable: true }).addTo(this.map);
                    else this.marker.setLatLng([lat, lng]);
                    this.marker.off('dragend').on('dragend', ({ target }) => {
                        const point = target.getLatLng();
                        this.selectPoint(point.lat, point.lng, true);
                    });
                    this.map.setView([lat, lng], 16);
                    if (label) this.setAddress(label);
                    else if (reverse) await this.reverseGeocode(lat, lng);
                },
                async reverseGeocode(lat, lng) {
                    try {
                        const url = new URL('https://nominatim.openstreetmap.org/reverse');
                        url.search = new URLSearchParams({ lat, lon: lng, format: 'jsonv2', 'accept-language': 'pt-BR' });
                        const response = await fetch(url, { headers: { Accept: 'application/json' } });
                        const result = await response.json();
                        if (result.display_name) this.setAddress(result.display_name);
                    } catch (error) { this.message = 'Ponto marcado. Não foi possível obter o endereço.'; }
                },
                setAddress(address) {
                    const input = this.localInput();
                    if (! input) return;
                    input.value = address;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    this.query = address;
                },
            };
        }
    </script>
    <style>
        .evento-local-map__search { display: flex; gap: .65rem; }
        .evento-local-map__search input { flex: 1; border: 1px solid #cbd5e1; border-radius: .65rem; padding: .65rem .8rem; }
        .evento-local-map__search button { border: 0; border-radius: .65rem; background: #17368d; color: #fff; padding: .65rem 1rem; font-weight: 600; }
        .evento-local-map__search button:disabled { opacity: .6; }
        .evento-local-map__hint, .evento-local-map__message { margin: .45rem 0; color: #64748b; font-size: .8rem; }
        .evento-local-map__canvas { height: 260px; border: 1px solid #cbd5e1; border-radius: .75rem; overflow: hidden; }
    </style>
</div>
