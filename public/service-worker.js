const STATIC_CACHE = 'sistema-juridico-static-v1';
const SAFE_ASSETS = [
    '/manifest.webmanifest',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/imagenes/federacion%20cafeteros%20logo.png',
    '/css/app.css',
];

self.addEventListener('install', event => {
    event.waitUntil(caches.open(STATIC_CACHE).then(cache => cache.addAll(SAFE_ASSETS)));
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(caches.keys().then(keys => Promise.all(
        keys.filter(key => key !== STATIC_CACHE).map(key => caches.delete(key)),
    )));
    self.clients.claim();
});

self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => new Response(
            '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Sin conexión</title><body style="font-family:system-ui;display:grid;min-height:100vh;place-items:center;margin:0;background:#f1f5f9"><main><h1>Sin conexión a Internet.</h1><p>Reconéctate para utilizar el Sistema Jurídico.</p></main></body></html>',
            { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' } },
        )));
        return;
    }

    const safe = url.pathname.startsWith('/build/assets/')
        || url.pathname.startsWith('/icons/')
        || url.pathname.startsWith('/imagenes/')
        || url.pathname.startsWith('/css/')
        || url.pathname === '/manifest.webmanifest';
    if (!safe) return;
    event.respondWith(caches.match(request).then(cached => cached || fetch(request).then(response => {
        if (response.ok) caches.open(STATIC_CACHE).then(cache => cache.put(request, response.clone()));
        return response;
    })));
});

self.addEventListener('push', event => {
    let payload = {};
    try { payload = event.data?.json() || {}; } catch (_) { payload = {}; }
    event.waitUntil(self.registration.showNotification(payload.title || 'Sistema Jurídico', {
        body: payload.body || 'Tienes una nueva notificación.',
        icon: payload.icon || '/icons/icon-192.png',
        badge: payload.badge || '/icons/icon-192.png',
        tag: payload.tag,
        renotify: false,
        data: payload.data || { url: '/dashboard' },
    }));
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    const candidate = event.notification.data?.url || '/dashboard';
    const target = new URL(candidate, self.location.origin);
    if (target.origin !== self.location.origin) target.href = self.location.origin + '/dashboard';

    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(async windows => {
        const existing = windows[0];
        if (existing) {
            await existing.navigate(target.href);
            return existing.focus();
        }
        return self.clients.openWindow(target.href);
    }));
});
