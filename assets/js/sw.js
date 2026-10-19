const CACHE_PREFIX = 'sa-education-static-';
const CACHE_NAME = `${CACHE_PREFIX}v4`;
const APP_BASE = new URL('./', self.location.href);
const STATIC_ASSETS = [
    'manifest.json',
    'assets/css/estilo.css',
    'assets/css/login-cadastro.css',
    'assets/css/perfil.css',
    'assets/css/feed.css',
    'assets/css/catalog.css',
    'assets/js/app.js',
    'assets/js/feed.js',
    'assets/images/logo-app.png',
    'assets/images/logo-app-192.png',
    'assets/images/logo-app-512.png',
    'assets/images/guia-rapida.png',
    'assets/images/icons/user-icon.png'
];
const STATIC_PATHS = new Set(STATIC_ASSETS.map((asset) => new URL(asset, APP_BASE).pathname));
const OFFLINE_RESPONSE = () => new Response(
    '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sem conexão</title><body><main><h1>Sem conexão</h1><p>Conecte-se à internet para carregar esta página.</p></main></body></html>',
    { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' } }
);

const isSafeAssetRequest = (request, url) => {
    return request.method === 'GET'
        && url.origin === self.location.origin
        && url.search === ''
        && STATIC_PATHS.has(url.pathname);
};

const isCacheableResponse = (response) => {
    return response.ok
        && response.type === 'basic'
        && !/no-store/i.test(response.headers.get('Cache-Control') || '');
};

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(STATIC_ASSETS.map((asset) => new URL(asset, APP_BASE).href)))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME).map((key) => caches.delete(key))
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

    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .catch(OFFLINE_RESPONSE)
        );
        return;
    }

    if (isSafeAssetRequest(request, url)) {
        event.respondWith(
            caches.open(CACHE_NAME).then(async (cache) => {
                const cached = await cache.match(request);
                if (cached) {
                    return cached;
                }

                const response = await fetch(request);
                if (isCacheableResponse(response)) {
                    await cache.put(request, response.clone());
                }
                return response;
            })
        );
    }
});