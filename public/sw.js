const CACHE = 'resavar-shell-v2';
const SAFE_SHELL = ['/', '/manifest.webmanifest', '/images/resavar-logo-dark.png', '/images/resavar-logo-light.png'];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(SAFE_SHELL)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key !== CACHE).map(key => caches.delete(key)))).then(() => self.clients.claim()));
});
function isSensitive(url) {
  return /\/(account|azaridevadmin|payments?|booking\/.*payment|guest-verification|identity|documents?)(\/|$)/i.test(url.pathname);
}
self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin || isSensitive(url)) return;
  event.respondWith(fetch(request).then(response => {
    if (response.ok && ['document','style','script','image','font'].includes(request.destination)) {
      const copy = response.clone();
      caches.open(CACHE).then(cache => cache.put(request, copy));
    }
    return response;
  }).catch(async () => {
    const cached = await caches.match(request);
    if (cached) return cached;
    if (request.destination === 'document') {
      return new Response('<!doctype html><html><head><meta name="viewport" content="width=device-width"><title>Resavar offline</title></head><body><main><h1>You are offline</h1><p>Resavar cannot confirm live availability, prices, payments or booking changes while offline.</p><p><a href="/">Try again</a></p></main></body></html>', {headers:{'Content-Type':'text/html; charset=UTF-8','Cache-Control':'no-store'}});
    }
    return Response.error();
  }));
});
self.addEventListener('push', event => {
  let payload = {};
  try { payload = event.data ? event.data.json() : {}; } catch { payload = {body: event.data ? event.data.text() : ''}; }
  event.waitUntil(self.registration.showNotification(payload.title || 'Resavar', {
    body: payload.body || 'There is an update to your stay.',
    icon: '/images/resavar-logo-dark.png',
    data: {url: payload.url || '/account/bookings'}
  }));
});
self.addEventListener('notificationclick', event => {
  event.notification.close();
  const url = event.notification.data && event.notification.data.url ? event.notification.data.url : '/account/bookings';
  event.waitUntil(clients.openWindow(url));
});