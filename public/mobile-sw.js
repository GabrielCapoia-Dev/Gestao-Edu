const MOBILE_CACHE = 'gestao-edu-mobile-v1';
const OFFLINE_URL = '/offline-mobile.html';
const PRECACHE_URLS = [
    OFFLINE_URL,
    '/mobile.webmanifest',
    '/css/mobile-app.css',
    '/js/mobile-pwa.js',
    '/pwa/icon-192.png',
    '/pwa/icon-512.png',
    '/pwa/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(MOBILE_CACHE)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys
                .filter((key) => key !== MOBILE_CACHE)
                .map((key) => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => caches.match(OFFLINE_URL))
        );

        return;
    }

    if (
        url.pathname.startsWith('/css/') ||
        url.pathname.startsWith('/js/') ||
        url.pathname.startsWith('/pwa/') ||
        url.pathname === '/mobile.webmanifest'
    ) {
        event.respondWith(
            caches.match(event.request).then((cached) => {
                if (cached) {
                    return cached;
                }

                return fetch(event.request).then((response) => {
                    const responseClone = response.clone();

                    caches.open(MOBILE_CACHE).then((cache) => {
                        cache.put(event.request, responseClone);
                    });

                    return response;
                });
            })
        );
    }
});
