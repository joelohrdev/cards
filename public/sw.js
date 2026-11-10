/*
 * Service worker for the installed app.
 *
 * - Built assets under /build/ have content hashes in their names, so they are
 *   served from the cache first and fetched once.
 * - Pages always come from the network, so card data, sign-in and CSRF tokens are
 *   never stale. When there is no connection, a page load shows /offline.html.
 * - Everything else (API calls, photos, other sites) is left to the browser.
 *
 * Bump VERSION when this file or offline.html changes to clear old caches.
 */
const VERSION = 'v1';
const ASSET_CACHE = `assets-${VERSION}`;
const OFFLINE_CACHE = `offline-${VERSION}`;
const OFFLINE_URL = '/offline.html';
const OFFLINE_FILES = [OFFLINE_URL, '/icons/icon-192.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(OFFLINE_CACHE)
            .then((cache) => cache.addAll(OFFLINE_FILES))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    const keep = [ASSET_CACHE, OFFLINE_CACHE];

    event.waitUntil(
        caches
            .keys()
            .then((names) =>
                Promise.all(
                    names
                        .filter((name) => !keep.includes(name))
                        .map((name) => caches.delete(name)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.open(ASSET_CACHE).then(async (cache) => {
                const cached = await cache.match(request);

                if (cached) {
                    return cached;
                }

                const response = await fetch(request);

                if (response.ok) {
                    cache.put(request, response.clone());
                }

                return response;
            }),
        );

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(async () => {
                const offline = await caches.match(OFFLINE_URL);

                return offline ?? Response.error();
            }),
        );
    }
});
