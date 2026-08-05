const CACHE_NAME = 'meu-pwa-cache-v1';
const ASSETS = [
    '/',
    '/index.html',
    '/manifest.json',
    '/logo_SAeducation_pocket.png',
    '/logo_SAeducation.png'
];

self.addEventListener('install', (e) => {
    e.waitUntil (
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(ASSETS);
        })
    );
});

self.addEventListener('fetch', (e) => {
   e.respondWith(
        caches.match(e.request).then((response) => {
            return response || fetch(e.request);
        })
    );
});