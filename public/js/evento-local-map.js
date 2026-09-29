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
        addressTimer: null,
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
        addressDisplayInput() {
            return this.componentRoot()?.querySelector('[data-evento-map-address]') || null;
        },
        mapAddressInput() {
            return this.addressDisplayInput() || this.fieldInput('endereco_mapa');
        },
        setField(input, value) {
            if (! input) return false;
            input.value = value;
            const modelAttribute = [...input.attributes].find(attribute => attribute.name.startsWith('wire:model'));
            const model = modelAttribute?.value
                || (input.name || '').replace(/\]\[?/g, '.').replace(/[\[\]]/g, '').replace(/\.+/g, '.').replace(/^\.|\.$/g, '');
            const component = input.closest('[wire\\:id]');
            const wire = component && window.Livewire
                ? window.Livewire.find(component.getAttribute('wire:id'))
                : null;

            if (! model || ! wire) return false;

            wire.$wire.set(model, value, false);

            return true;
        },
        async init() {
            if (this.$refs.map.dataset.initialized) return;
            while (! window.L) await new Promise(resolve => setTimeout(resolve, 50));
            this.$refs.map.dataset.initialized = 'true';
            this.loadSuggestions();
            this.componentRoot()?.addEventListener('input', ({ target }) => {
                if (target === this.localInput()) {
                    clearTimeout(this.referenceTimer);
                    this.referenceTimer = setTimeout(() => this.searchSavedReference(), 400);
                }
                if (target === this.mapAddressInput()) {
                    clearTimeout(this.addressTimer);
                    this.addressTimer = setTimeout(() => this.searchTypedAddress(), 500);
                }
            });
            this.map = L.map(this.$refs.map).setView([-23.7658, -53.3250], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(this.map);
            console.info('[evento-local-map] Mapa do evento pronto para receber cliques.');
            this.map.on('click', ({ latlng }) => {
                const coordenadas = { latitude: latlng.lat, longitude: latlng.lng };
                console.log('[evento-local-map] Coordenadas clicadas no mapa:', coordenadas);
                this.selectPoint(latlng.lat, latlng.lng, true)
                    .catch(error => console.error('[evento-local-map] Falha ao processar o ponto clicado:', error));
            });
            const lat = parseFloat(this.latitudeInput()?.value);
            const lng = parseFloat(this.longitudeInput()?.value);
            if (Number.isFinite(lat) && Number.isFinite(lng)) {
                const endereco = this.mapAddressInput()?.value?.trim();
                this.selectPoint(lat, lng, ! endereco, endereco || null);
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
            if (! this.marker) this.marker = L.marker([lat, lng], { draggable: true, bubblingMouseEvents: true }).addTo(this.map);
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
        async searchTypedAddress() {
            const endereco = this.mapAddressInput()?.value?.trim() || '';
            if (endereco.length < 3) return;

            this.loading = true;
            this.message = '';
            try {
                const results = await this.requestLocations(endereco);
                const result = results.find(item => item.origem === 'salvo') || results[0];
                if (result) {
                    await this.selectResult(result);
                } else {
                    this.message = 'Nenhum local encontrado para o endereço informado.';
                }
            } catch (error) {
                this.message = 'Não foi possível localizar o endereço agora.';
            } finally {
                this.loading = false;
            }
        },
        async reverseGeocode(lat, lng) {
            try {
                const endpoint = this.$root.dataset.reverseGeocodeUrl || '/admin/eventos-calendario/localizacoes/reverter';
                const url = new URL(endpoint, window.location.origin);
                url.searchParams.set('latitude', lat);
                url.searchParams.set('longitude', lng);
                console.info('[evento-local-map] Consultando Nominatim para as coordenadas:', { latitude: lat, longitude: lng, url: url.toString() });

                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                let result = null;
                try {
                    result = await response.json();
                } catch (error) {
                    console.error('[evento-local-map] A resposta do endpoint de geocodificação não é JSON.', { status: response.status, error });
                }
                console.log('[evento-local-map] Resposta do Nominatim:', { status: response.status, ok: response.ok, result });

                if (! response.ok) throw new Error(`HTTP ${response.status}`);
                if (result?.endereco) { this.setAddress(result.endereco); this.message = ''; return; }
                console.warn('[evento-local-map] O Nominatim não retornou endereço para o ponto.', { lat, lng, status: response.status, result });
            } catch (error) {
                console.error('[evento-local-map] Falha ao consultar o Nominatim para o ponto selecionado.', { lat, lng, error });
            }

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
            const input = this.mapAddressInput();
            const updated = this.setField(input, address);
            if (address) {
                console.log('[evento-local-map] Endereço retornado para o ponto selecionado:', address, {
                    inputEncontrado: Boolean(input),
                    estadoLivewireAtualizado: updated,
                    componenteEncontrado: Boolean(this.componentRoot()),
                });
            }
            this.query = address;
            window.dispatchEvent(new CustomEvent('evento-mapa-endereco', {
                detail: {
                    componentId: this.componentRoot()?.getAttribute('wire:id'),
                    address,
                },
            }));
            return updated;
        },
    };
};
