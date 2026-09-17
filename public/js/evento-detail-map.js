(() => {
    const initMaps = () => {
        if (! window.L) return;

        document.querySelectorAll('[data-evento-detail-map]:not([data-map-ready])').forEach((element) => {
            const latitude = Number(element.dataset.latitude);
            const longitude = Number(element.dataset.longitude);
            if (! Number.isFinite(latitude) || ! Number.isFinite(longitude)) return;

            const map = window.L.map(element, { scrollWheelZoom: false }).setView([latitude, longitude], 16);
            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);
            window.L.marker([latitude, longitude]).addTo(map);
            element.dataset.mapReady = 'true';
        });
    };

    document.addEventListener('DOMContentLoaded', initMaps);
    document.addEventListener('livewire:navigated', initMaps);
    document.addEventListener('livewire:morphed', initMaps);
})();
