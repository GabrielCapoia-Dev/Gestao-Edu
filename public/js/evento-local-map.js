window.eventoLocalMap = function () {
    return {
        map: null,
        marker: null,
        query: '',
        results: [],
        suggestions: [],
        loading: false,
        message: '',
        referenceTimer: null,
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
        addressDisplayInput() {
            return this.componentRoot()?.querySelector('[data-evento-map-address]') || null;
        },
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
            this.localInput()?.addEventListener('input', () => {
                clearTimeout(this.referenceTimer);
                this.referenceTimer = setTimeout(() => this.searchSavedReference(), 400);
            });
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
                this.results = await this.requestLocations(this.query);
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
            await this.selectPoint(parseFloat(result.lat), parseFloat(result.lng), false, result.label);
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
            this.setAddress(label || '');
            this.message = label ? '' : 'Localizando o endereço do ponto...';
            if (! label && reverse) await this.reverseGeocode(lat, lng);
            if (this.localInput()?.value) this.saveSuggestion(this.localInput().value, lat, lng);
        },
        coordinateLabel(lat, lng) {
            return `Coordenadas: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        },
        async reverseGeocode(lat, lng) {
            try {
                const response = await fetch(`/admin/eventos-calendario/localizacoes/reverter?latitude=${encodeURIComponent(lat)}&longitude=${encodeURIComponent(lng)}`, { headers: { Accept: 'application/json' } });
                const result = await response.json();
                if (result.endereco) { this.setAddress(result.endereco); this.message = ''; return; }
            } catch (error) { /* A consulta direta abaixo mantém o mapa utilizável se a rota interna falhar. */ }

            try {
                const url = new URL('https://nominatim.openstreetmap.org/reverse');
                url.search = new URLSearchParams({ lat, lon: lng, format: 'jsonv2', addressdetails: '1', 'accept-language': 'pt-BR' });
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const result = await response.json();
                if (result.display_name) { this.setAddress(result.display_name); this.message = ''; return; }
            } catch (error) { /* Mensagem abaixo orienta a pesquisa manual. */ }

            this.setAddress('');
            this.message = 'Ponto marcado, mas não foi possível identificar o endereço. Pesquise o local pelo nome.';
        },
        async requestLocations(query, somenteSalvos = false) {
            const params = new URLSearchParams({ q: query, somente_salvos: somenteSalvos ? '1' : '0' });
            const response = await fetch(`/admin/eventos-calendario/localizacoes?${params}`, { headers: { Accept: 'application/json' } });
            if (! response.ok) throw new Error('Busca indisponível');
            return (await response.json()).items || [];
        },
        async searchSavedReference() {
            const reference = this.localInput()?.value?.trim() || '';
            if (reference.length < 2) return;
            try {
                const results = await this.requestLocations(reference, true);
                const normalized = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().trim();
                const exact = results.find(result => normalized(result.referencia) === normalized(reference));
                if (exact) await this.selectResult(exact);
            } catch (error) { /* O mapa continua disponível para busca manual. */ }
        },
        setAddress(address) {
            const display = this.addressDisplayInput();
            if (display) display.value = address;
            this.setField(this.mapAddressInput(), address);
            this.query = address;
            window.dispatchEvent(new CustomEvent('evento-mapa-endereco', {
                detail: {
                    componentId: this.componentRoot()?.getAttribute('wire:id'),
                    address,
                },
            }));
        },
    };
};
