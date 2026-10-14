const CACHE_NAME = 'sa-education-static-v2';
const ASSET_CACHE = [
    './',
    './index.php',
    './login.php',
    './cadastro.php',
    './manifest.json',
    './assets/css/estilo.css',
    './assets/css/login-cadastro.css',
    './assets/js/app.js',
    './assets/images/logo-app.png',
    './assets/images/guia-rapida.png',
    './assets/images/icons/user-icon.png'
];

const isSafeAssetRequest = (url) => {
    const destination = url.pathname.toLowerCase();
    return destination.endsWith('.css')
        || destination.endsWith('.js')
        || destination.endsWith('.png')
        || destination.endsWith('.jpg')
        || destination.endsWith('.jpeg')
        || destination.endsWith('.svg')
        || destination.endsWith('.webp')
        || destination.endsWith('.ico')
        || destination.endsWith('.json');
};

const isProtectedHtml = (url) => {
    const pathname = url.pathname.toLowerCase();
    return pathname.includes('/perfil-')
        || pathname.includes('/salas.php')
        || pathname.includes('/feed.php')
        || pathname.includes('/ranking.php')
        || pathname.includes('/biblioteca.php')
        || pathname.includes('/quiz.php')
        || pathname.includes('/sala.php')
        || pathname.includes('/criar-sala.php')
        || pathname.includes('/entrar-sala.php');
};

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(ASSET_CACHE))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.includes('/api/')) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response && response.status === 200) {
                        const copy = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, copy)).catch(() => {});
                    }
                    return response;
                })
                .catch(() => {
                    if (isProtectedHtml(url)) {
                        return caches.match('./login.php') || caches.match('./index.php');
                    }
                    return caches.match(request) || caches.match('./index.php') || caches.match('./login.php');
                })
        );
        return;
    }

    if (isSafeAssetRequest(url)) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                if (response && response.status === 200) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, clone)).catch(() => {});
                }
                return response;
            }).catch(() => cached))
        );
        return;
    }

    event.respondWith(fetch(request).catch(() => caches.match('./index.php')));
});