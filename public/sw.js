/**
 * Now Logistics ERP — Service Worker
 *
 * Strategy:
 *  - Static assets (CSS/JS/fonts/images): Cache-First with network fallback.
 *  - Shipment tracking pages (/track/*): Stale-While-Revalidate so drivers
 *    can see the last-known state while offline.
 *  - API GPS endpoint (/api/v1/vehicles/*/gps): Background-sync queued via
 *    IndexedDB when offline; replayed when connectivity returns.
 *  - All other requests: Network-First.
 */

const CACHE_VERSION = 'now-v1';
const STATIC_CACHE  = `${CACHE_VERSION}-static`;
const TRACK_CACHE   = `${CACHE_VERSION}-track`;
const GPS_QUEUE_KEY = 'gps-pending-queue';

const STATIC_EXTENSIONS = ['.js', '.css', '.woff2', '.woff', '.ttf', '.png', '.svg', '.ico'];

// ── Install ──────────────────────────────────────────────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) =>
            cache.addAll([
                '/offline.html',
            ]).catch(() => { /* offline.html may not exist yet */ })
        )
    );
    self.skipWaiting();
});

// ── Activate ─────────────────────────────────────────────────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((k) => k.startsWith('now-') && k !== STATIC_CACHE && k !== TRACK_CACHE)
                    .map((k) => caches.delete(k))
            )
        )
    );
    self.clients.claim();
});

// ── Fetch ─────────────────────────────────────────────────────────────────────
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET for everything except GPS API
    if (request.method !== 'GET') {
        if (url.pathname.includes('/api/v1/vehicles/') && url.pathname.endsWith('/gps')) {
            event.respondWith(handleGpsPost(request));
        }
        return;
    }

    // Shipment tracking pages → Stale-While-Revalidate
    if (url.pathname.startsWith('/track/')) {
        event.respondWith(staleWhileRevalidate(TRACK_CACHE, request));
        return;
    }

    // Static assets → Cache-First
    if (STATIC_EXTENSIONS.some((ext) => url.pathname.endsWith(ext))) {
        event.respondWith(cacheFirst(STATIC_CACHE, request));
        return;
    }

    // Everything else → Network-First
    event.respondWith(networkFirst(request));
});

// ── Strategies ────────────────────────────────────────────────────────────────
async function cacheFirst(cacheName, request) {
    const cached = await caches.match(request);
    if (cached) { return cached; }
    const response = await fetch(request);
    if (response.ok) {
        const cache = await caches.open(cacheName);
        cache.put(request, response.clone());
    }
    return response;
}

async function staleWhileRevalidate(cacheName, request) {
    const cache  = await caches.open(cacheName);
    const cached = await cache.match(request);
    const fetchPromise = fetch(request).then((response) => {
        if (response.ok) { cache.put(request, response.clone()); }
        return response;
    }).catch(() => cached);
    return cached ?? fetchPromise;
}

async function networkFirst(request) {
    try {
        const response = await fetch(request);
        return response;
    } catch {
        const cached = await caches.match(request);
        return cached ?? caches.match('/offline.html');
    }
}

// ── GPS offline queue (IndexedDB) ─────────────────────────────────────────────
async function handleGpsPost(request) {
    try {
        return await fetch(request.clone());
    } catch {
        // Offline: store in IndexedDB for later replay
        const body = await request.clone().json().catch(() => ({}));
        await enqueueGps({ url: request.url, body, timestamp: Date.now() });
        return new Response(JSON.stringify({ queued: true }), {
            status: 202,
            headers: { 'Content-Type': 'application/json' },
        });
    }
}

function openDb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open('now-gps-queue', 1);
        req.onupgradeneeded = (e) => e.target.result.createObjectStore('queue', { autoIncrement: true });
        req.onsuccess       = (e) => resolve(e.target.result);
        req.onerror         = (e) => reject(e.target.error);
    });
}

async function enqueueGps(entry) {
    const db    = await openDb();
    const tx    = db.transaction('queue', 'readwrite');
    tx.objectStore('queue').add(entry);
    return new Promise((resolve, reject) => {
        tx.oncomplete = resolve;
        tx.onerror    = (e) => reject(e.target.error);
    });
}

// ── Background Sync: replay queued GPS positions ──────────────────────────────
self.addEventListener('sync', (event) => {
    if (event.tag === 'gps-sync') {
        event.waitUntil(replayGpsQueue());
    }
});

async function replayGpsQueue() {
    const db      = await openDb();
    const tx      = db.transaction('queue', 'readwrite');
    const store   = tx.objectStore('queue');
    const entries = await new Promise((resolve) => {
        const all = [];
        store.openCursor().onsuccess = (e) => {
            const cursor = e.target.result;
            if (cursor) { all.push({ key: cursor.key, value: cursor.value }); cursor.continue(); }
            else { resolve(all); }
        };
    });

    for (const { key, value } of entries) {
        try {
            const response = await fetch(value.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-From-SW': '1' },
                body: JSON.stringify(value.body),
            });
            if (response.ok) {
                db.transaction('queue', 'readwrite').objectStore('queue').delete(key);
            }
        } catch {
            // still offline, leave in queue
        }
    }
}
