window.eventoDetailMap = ({ latitude, longitude, query }) => ({
    init() {
        const start = () => {
            if (!window.L) {
                setTimeout(start, 150);
                return;
            }

            if (this.$el.dataset.mapReady === 'true') return;

            const render = (lat, lng) => {
                const map = window.L.map(this.$el, { scrollWheelZoom: false }).setView([lat, lng], 16);
                window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                }).addTo(map);
                window.L.marker([lat, lng]).addTo(map);
                this.$el.dataset.mapReady = 'true';
                setTimeout(() => map.invalidateSize(), 100);

                fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`, {
                    headers: { Accept: 'application/json' },
                }).then((response) => response.json()).then((result) => {
                    const target = this.$el.closest('.gi-event-detail__section')?.querySelector('[data-evento-detail-address]');
                    if (result.display_name && target) target.textContent = result.display_name;
                }).catch(() => {});
            };

            render(
                Number.isFinite(latitude) ? latitude : -23.7658,
                Number.isFinite(longitude) ? longitude : -53.3250,
            );

            if (Number.isFinite(latitude) && Number.isFinite(longitude)) return;

            fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=br&q=${encodeURIComponent(`${query}, Umuarama, Paraná`)}`, {
                headers: { Accept: 'application/json' },
            }).then((response) => response.json()).then((items) => {
                if (!items[0]) return;
                const lat = Number(items[0].lat);
                const lng = Number(items[0].lon);
                this.$el.dataset.mapReady = 'false';
                this.$el.replaceChildren();
                render(lat, lng);
            });
        };

        this.$nextTick(start);
    },
});
