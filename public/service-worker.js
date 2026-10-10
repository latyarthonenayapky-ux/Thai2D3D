const CACHE_NAME = 'thai2d3d-offline-shell-v1';
const OFFLINE_SHELL = /\/operator\/sessions\/\d+\/offline(?:\?.*)?$/;

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.add('/offline-sales.js'))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin || !OFFLINE_SHELL.test(url.pathname + url.search)) {
        return;
    }

    event.respondWith((async () => {
        let response;
        try {
            response = await fetch(request);
        } catch (error) {
            const cached = await caches.match(request);
            if (cached) return cached;
            return new Response('Open this Offline sales page once while connected to prepare it for offline use.', {
                status: 503,
                headers: { 'Content-Type': 'text/plain; charset=utf-8' },
            });
        }

        const finalUrl = new URL(response.url);
        const isOfflineShell = response.status === 200
            && !response.redirected
            && finalUrl.origin === self.location.origin
            && finalUrl.pathname === url.pathname
            && response.headers.get('content-type')?.includes('text/html');
        if (isOfflineShell) {
            try {
                const cache = await caches.open(CACHE_NAME);
                await cache.put(request, response.clone());
            } catch (error) {
                console.error('Could not cache the Offline sales page for later use.', error);
            }
        }
        return response;
    })());
});
