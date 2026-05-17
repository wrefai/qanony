/**
 * Qanony Service Worker — Cache-first for static assets
 * Dynamic search requests always go to network
 */

const CACHE_NAME = 'qanony-v2';

// Static assets to cache on install
const PRECACHE_ASSETS = [
    '/qanony/assets/css/app.css',
    '/qanony/assets/js/app.js',
    '/qanony/assets/fonts/cairo-arabic.woff2',
    '/qanony/assets/fonts/cairo-latin.woff2',
    '/qanony/assets/fonts/noto-naskh-arabic.woff2',
    '/qanony/assets/icons/icon-192.png',
    '/qanony/assets/icons/icon-512.png',
];

// Never cache these (dynamic / API)
const NETWORK_ONLY_PATTERNS = [
    /\/qanony\/search\//,
    /\/qanony\/documents\/\d+\/preview/,
    /\/qanony\/auth\//,
    /\/qanony\/api\//,
];

// ── Install: precache static assets ──────────────────────────
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return Promise.allSettled(
                PRECACHE_ASSETS.map(url =>
                    cache.add(url).catch(() => { /* ignore missing */ })
                )
            );
        }).then(() => self.skipWaiting())
    );
});

// ── Activate: clean old caches ───────────────────────────────
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
            )
        ).then(() => self.clients.claim())
    );
});

// ── Fetch: cache-first for assets, network-first for pages ───
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Only handle same-origin GET requests
    if (event.request.method !== 'GET' || url.origin !== location.origin) {
        return;
    }

    // Network-only patterns (dynamic content)
    if (NETWORK_ONLY_PATTERNS.some(p => p.test(url.pathname))) {
        return;
    }

    // Cache-first for static assets (fonts, css, js)
    if (url.pathname.startsWith('/qanony/assets/')) {
        event.respondWith(
            caches.match(event.request).then(cached => {
                if (cached) return cached;
                return fetch(event.request).then(response => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then(c => c.put(event.request, clone));
                    }
                    return response;
                });
            })
        );
        return;
    }

    // Network-first for HTML pages (always fresh)
    // (no event.respondWith — falls through to browser default)
});
