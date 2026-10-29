/* Only static, public offline resources are cached, never authenticated responses. */
const CACHE = 'archi-offline-shell-v2';
const SHELL = '/pwa/offline.html';
const SCRIPT = '/pwa/offline.js';
self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE).then(cache => cache.addAll([new Request(SHELL, { credentials: 'omit' }), new Request(SCRIPT, { credentials: 'omit' })])).then(() => self.skipWaiting()));
});
self.addEventListener('activate', event => {
    event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith('archi-offline-shell-') && key !== CACHE).map(key => caches.delete(key)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);
    if (event.request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (url.pathname === SCRIPT) {
        event.respondWith(caches.open(CACHE).then(cache => cache.match(SCRIPT)).then(response => response || fetch(event.request)));
    } else if (event.request.mode === 'navigate') {
        event.respondWith(fetch(event.request).catch(async () => (await (await caches.open(CACHE)).match(SHELL)) || Response.error()));
    }
});
