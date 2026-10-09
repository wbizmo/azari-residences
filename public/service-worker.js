// Versioned, public-only cache. Never persist authenticated pages, booking data or payment responses.
const CACHE_NAME = 'resavar-pwa-v4';
const STATIC_ASSETS = ['/manifest.webmanifest', '/offline.html'];
const OLD_PREFIXES = ['azari-pwa-', 'resarva-pwa-', 'resavar-pwa-'];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(
                keys.filter(key => OLD_PREFIXES.some(prefix => key.startsWith(prefix)) && key !== CACHE_NAME)
                    .map(key => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Network-only navigation. If offline, show a generic, non-personalized page.
    // No HTML responses, credentials, checkout actions or trip documents are cached.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(async () => {
                const fallback = await caches.match('/offline.html');
                return fallback || Response.error();
            })
        );
        return;
    }

    // Cache only explicitly public static assets. Vite fingerprinted assets are
    // immutable by filename; avoid accidental caching of signed/private URLs.
    const isPublicAsset = url.pathname === '/manifest.webmanifest' ||
        url.pathname === '/offline.html' ||
        /^\/build\/assets\/[a-zA-Z0-9_.-]+\.(?:js|css|woff2?|png|webp|svg)$/.test(url.pathname);
    if (!isPublicAsset || url.search || request.headers.has('Authorization')) return;

    event.respondWith(
        caches.match(request).then(async cached => {
            if (cached) return cached;
            const response = await fetch(request);
            if (response.ok && response.type === 'basic' &&
                !response.headers.has('Set-Cookie') &&
                !/private|no-store/i.test(response.headers.get('Cache-Control') || '')) {
                const copy = response.clone();
                event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.put(request, copy)));
            }
            return response;
        })
    );
});
