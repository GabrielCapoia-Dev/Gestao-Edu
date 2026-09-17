window.eventoDetailMap = ({ latitude, longitude }) => ({
    init() {
        if (!window.L || !Number.isFinite(latitude) || !Number.isFinite(longitude)) return;

        this.$nextTick(() => {
            if (this.$el.dataset.mapReady === 'true') return;

            const map = window.L.map(this.$el, { scrollWheelZoom: false }).setView([latitude, longitude], 16);
            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);
            window.L.marker([latitude, longitude]).addTo(map);
            this.$el.dataset.mapReady = 'true';
            setTimeout(() => map.invalidateSize(), 100);
        });
    },
});
