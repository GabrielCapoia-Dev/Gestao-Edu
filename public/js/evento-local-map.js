window.eventoLocalMap = function () {
    return {
        map: null,
        marker: null,
        query: '',
        results: [],
        suggestions: [],
        loading: false,
        message: '',
        componentRoot() {
            return this.$root.closest('[wire\\:id]');
        },
        fieldInput(field) {
            const root = this.componentRoot();

            return [...(root?.querySelectorAll('input') || [])].find(input => {
                const name = input.name || '';
                return name.endsWith(`[${field}]`)
                    || name.endsWith(`.${field}`)
                    || (input.getAttribute('wire:model') || '').endsWith(`.${field}`);
            }) || null;
        },
        localInput() { return this.fieldInput('local'); },
        latitudeInput() { return this.fieldInput('latitude'); },
        longitudeInput() { return this.fieldInput('longitude'); },
        mapAddressInput() { return this.fieldInput('endereco_mapa'); },
        setField(input, value) {
            if (! input) return;
            input.value = value;
            const model = input.getAttribute('wire:model') || input.getAttribute('wire:model.live');
            const component = input.closest('[wire\\:id]');
            if (model && component && window.Livewire) {
                window.Livewire.find(component.getAttribute('wire:id'))?.$wire.set(model, value, false);
            }
        },
        async init() {
            if (this.$refs.map.dataset.initialized) return;
            while (! window.L) await new Promise(resolve => setTimeout(resolve, 50));
            this.$refs.map.dataset.initialized = 'true';
            this.loadSuggestions();
            this.map = L.map(this.$refs.map).setView([-23.7658, -53.3250], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(this.map);
            this.map.on('click', ({ latlng }) => this.selectPoint(latlng.lat, latlng.lng, true));
            const lat = parseFloat(this.latitudeInput()?.value);
            const lng = parseFloat(this.longitudeInput()?.value);
            if (Number.isFinite(lat) && Number.isFinite(lng)) {
                this.selectPoint(lat, lng, false, this.mapAddressInput()?.value || this.coordinateLabel(lat, lng));
            }
            setTimeout(() => this.map.invalidateSize(), 200);
        },
        async search() {
            if (! this.query.trim()) return;
            this.loading = true;
            this.message = '';
            this.results = [];
            try {
                const url = new URL('https://nominatim.openstreetmap.org/search');
                url.search = new URLSearchParams({
                    q: this.query.trim(),
                    format: 'jsonv2',
                    limit: '10',
                    countrycodes: 'br',
                    viewbox: '-54.4,-23.2,-52.4,-24.5',
                    bounded: '0',
                    dedupe: '1',
                    addressdetails: '1',
                    namedetails: '1',
                    'accept-language': 'pt-BR',
                });
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                this.results = (await response.json()).sort((left, right) => {
                    const local = value => /umuarama|alto para[ií]so|perobal|xambr[eê]/i.test(value.display_name || '') ? 0 : 1;
                    return local(left) - local(right);
                });
                if (! this.results.length) { this.message = 'Nenhum local encontrado em Umuarama e região.'; return; }
            } catch (error) {
                this.message = 'Não foi possível pesquisar o local agora.';
            } finally { this.loading = false; }
        },
        loadSuggestions() {
            try {
                this.suggestions = JSON.parse(localStorage.getItem('gestao-edu:evento-locais') || '[]');
            } catch (error) { this.suggestions = []; }
        },
        filterSuggestions() {
            const term = this.query.trim().toLocaleLowerCase();
            this.results = term.length < 2
                ? []
                : this.suggestions.filter(item => item.label.toLocaleLowerCase().includes(term));
        },
        saveSuggestion(label, lat, lng) {
            const items = [{ label, lat, lng }, ...this.suggestions.filter(item => item.label !== label)].slice(0, 10);
            this.suggestions = items;
            localStorage.setItem('gestao-edu:evento-locais', JSON.stringify(items));
        },
        async selectResult(result) {
            this.results = [];
            await this.selectPoint(parseFloat(result.lat), parseFloat(result.lon), false, result.display_name || result.label);
        },
        async selectPoint(lat, lng, reverse = true, label = null) {
            const latitude = this.latitudeInput();
            const longitude = this.longitudeInput();
            if (! this.marker) this.marker = L.marker([lat, lng], { draggable: true }).addTo(this.map);
            else this.marker.setLatLng([lat, lng]);
            this.marker.off('dragend').on('dragend', ({ target }) => {
                const point = target.getLatLng();
                this.selectPoint(point.lat, point.lng, true);
            });
            this.map.setView([lat, lng], 16);
            if (latitude && longitude) {
                this.setField(latitude, lat.toFixed(7));
                this.setField(longitude, lng.toFixed(7));
            }
            this.setAddress(label || this.coordinateLabel(lat, lng));
            if (! label && reverse) await this.reverseGeocode(lat, lng);
            if (this.localInput()?.value) this.saveSuggestion(this.localInput().value, lat, lng);
        },
        coordinateLabel(lat, lng) {
            return `Coordenadas: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
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
            this.setField(this.mapAddressInput(), address);
            this.query = address;
        },
    };
};
