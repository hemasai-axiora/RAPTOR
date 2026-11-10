/**
 * Raptor Platform — Service Worker & Offline Cache Engine (PWA)
 * Release: Raptor v2.2 (Sprint 6 Hardening)
 */

const CACHE_NAME = 'raptor-pwa-v2.2.0';
const PRECACHE_ASSETS = [
    'manifest.json',
    'assets/css/raptor-tokens.css',
    'assets/css/raptor-glass.css',
    'logo.png'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(PRECACHE_ASSETS).catch(function () {
                // Precache optional if server offline during registration
            });
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys.filter(function (key) {
                    return key !== CACHE_NAME;
                }).map(function (key) {
                    return caches.delete(key);
                })
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET') return;

    var url = new URL(event.request.url);
    var isStatic = url.pathname.includes('/assets/') || 
                   url.pathname.endsWith('.css') || 
                   url.pathname.endsWith('.png') || 
                   url.pathname.endsWith('.json');

    if (isStatic) {
        event.respondWith(
            caches.match(event.request).then(function (cached) {
                return cached || fetch(event.request).then(function (response) {
                    if (response && response.status === 200) {
                        var clone = response.clone();
                        caches.open(CACHE_NAME).then(function (cache) {
                            cache.put(event.request, clone);
                        });
                    }
                    return response;
                });
            }).catch(function () {
                return fetch(event.request);
            })
        );
    }
});

self.addEventListener('push', function (event) {
    var data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = {}; }
    var title = data.title || 'RAPTOR';
    var options = {
        body: data.body || data.message || 'You have a new notification.',
        icon: data.icon || 'logo.png',
        badge: data.badge || '',
        data: { url: data.url || '/public/index.php?route=notifications/index' }
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = event.notification.data && event.notification.data.url ? event.notification.data.url : '/public/index.php?route=notifications/index';
    event.waitUntil(clients.openWindow(url));
});
