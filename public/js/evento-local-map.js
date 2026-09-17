window.eventoLocalMap = function () {
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
            const latitude = this.latitudeInput();
            const longitude = this.longitudeInput();
            if (! latitude || ! longitude) return;
            latitude.value = lat.toFixed(7);
            longitude.value = lng.toFixed(7);
            latitude.dispatchEvent(new Event('input', { bubbles: true }));
            longitude.dispatchEvent(new Event('input', { bubbles: true }));
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
};
