// Offline-capable public shell only. Account, booking, payment and identity
// documents are ALWAYS fetched online and never persisted by this worker.
const CACHE = 'resavar-shell-v4';
const SAFE_SHELL = ['/manifest.webmanifest', '/offline.html', '/offline-trip.js'];
const STATIC_PATH = /^\/build\/assets\/[a-zA-Z0-9_.-]+\.(?:js|css|woff2?|png|webp|svg)$/;
const PUBLIC_IMAGES = new Set(['/images/resavar-logo-dark.png', '/images/resavar-logo-light.png', '/images/resavar-pwa-192.png', '/images/resavar-pwa-512.png']);

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(SAFE_SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys =>
    Promise.all(keys.filter(key => /^(resavar-shell-|resavar-pwa-|resarva-pwa-|azari-pwa-)/.test(key) && key !== CACHE)
      .map(key => caches.delete(key)))
  ).then(() => self.clients.claim()));
});

self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  // Navigation is network-only. No document response is ever cached.
  // This also covers booking/payment/identity/account and guest documents.
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(async () =>
      (await caches.match('/offline.html')) ||
      new Response('Resavar cannot confirm live availability, prices, payments or booking changes while offline.', {
        status: 503, headers: {'Content-Type': 'text/plain; charset=UTF-8', 'Cache-Control': 'no-store'}
      })
    ));
    return;
  }

  const publicAsset = !url.search && (
    STATIC_PATH.test(url.pathname) ||
    PUBLIC_IMAGES.has(url.pathname) ||
    SAFE_SHELL.includes(url.pathname)
  );
  if (!publicAsset || request.headers.has('Authorization')) return;

  event.respondWith(caches.match(request).then(async cached => {
    if (cached) return cached;
    const response = await fetch(request);
    if (response.ok && response.type === 'basic' &&
        !response.headers.has('Set-Cookie') &&
        !/private|no-store/i.test(response.headers.get('Cache-Control') || '')) {
      const copy = response.clone();
      event.waitUntil(caches.open(CACHE).then(cache => cache.put(request, copy)));
    }
    return response;
  }));
});

