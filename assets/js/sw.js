const CACHE_NAME = 'meu-pwa-cache-v1';
const ASSETS = [
    '/',
    '/index.php',
    '/manifest.json',
    '/assets/images/logo_SAeducation_pocket.png',
    '/assets/images/logo_SAeducation.png',
    '/assets/css/estilo.css',
    '/assets/js/app.js'
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