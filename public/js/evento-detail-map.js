window.eventoDetailMap = ({ latitude, longitude, query }) => ({
    init() {
        if (!window.L) return;

        this.$nextTick(() => {
            if (this.$el.dataset.mapReady === 'true') return;

            const render = (lat, lng) => {
                const map = window.L.map(this.$el, { scrollWheelZoom: false }).setView([lat, lng], 16);
                window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(map);
                window.L.marker([lat, lng]).addTo(map);
                this.$el.dataset.mapReady = 'true';
                setTimeout(() => map.invalidateSize(), 100);
            };

            if (Number.isFinite(latitude) && Number.isFinite(longitude)) {
                render(latitude, longitude);
                return;
            }

            fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=br&q=${encodeURIComponent(`${query}, Umuarama, Paraná`)}`, {
                headers: { Accept: 'application/json' },
            }).then((response) => response.json()).then((items) => {
                if (items[0]) render(Number(items[0].lat), Number(items[0].lon));
            });
        });
    },
});
