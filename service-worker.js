/**
 * FlexNet Service Worker
 * Handles offline support, caching, and background sync
 */

const CACHE_VERSION = 'flexnet-v1';
const RUNTIME_CACHE = 'flexnet-runtime-v1';
const API_CACHE = 'flexnet-api-v1';
const IMAGE_CACHE = 'flexnet-images-v1';

// Files to cache on install
const STATIC_ASSETS = [
    '/',
    '/index.html',
    '/admin/login.html',
    '/admin/dashboard.html',
    '/admin/locations.html',
    '/admin/locations-new.html',
    '/admin/households.html',
    '/admin/households-new.html',
    '/admin/admins.html',
    '/admin/admins-new.html',
    '/admin/payments.html',
    '/admin/profile.html',
    '/admin/api-client.js',
    '/user/login.html',
    '/user/dashboard.html',
    '/user/profile.html',
    '/user/subscriptions.html',
    '/user/billing.html',
    '/user/settings.html',
    '/user/change-pin.html',
    '/user/pin-change-success.html',
    '/user/onboarding.html'
];

/**
 * Install event - cache static assets
 */
self.addEventListener('install', (event) => {
    console.log('[Service Worker] Installing...');
    
    event.waitUntil(
        caches.open(CACHE_VERSION).then((cache) => {
            console.log('[Service Worker] Caching static assets');
            return cache.addAll(STATIC_ASSETS).catch(err => {
                console.warn('[Service Worker] Some assets failed to cache:', err);
            });
        }).then(() => self.skipWaiting())
    );
});

/**
 * Activate event - clean up old caches
 */
self.addEventListener('activate', (event) => {
    console.log('[Service Worker] Activating...');
    
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_VERSION && cacheName !== RUNTIME_CACHE && 
                        cacheName !== API_CACHE && cacheName !== IMAGE_CACHE) {
                        console.log('[Service Worker] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

/**
 * Fetch event - implement caching strategies
 */
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip cross-origin and non-http requests
    if (url.origin !== location.origin || !request.url.startsWith('http')) {
        return;
    }

    // API requests - Network first with cache fallback
    if (url.pathname.startsWith('/api/')) {
        return event.respondWith(networkFirst(request));
    }

    // Images - Cache first with network fallback
    if (request.destination === 'image') {
        return event.respondWith(cacheFirstImages(request));
    }

    // CSS, JS, fonts - Cache first with network fallback
    if (request.destination === 'style' || request.destination === 'script' || request.destination === 'font') {
        return event.respondWith(cacheFirst(request));
    }

    // HTML - Network first with cache fallback
    if (request.destination === '' || request.destination === 'document') {
        return event.respondWith(networkFirst(request));
    }

    // Default - Network first
    event.respondWith(networkFirst(request));
});

/**
 * Cache first strategy
 */
async function cacheFirst(request) {
    try {
        const cache = await caches.open(CACHE_VERSION);
        const cached = await cache.match(request);
        
        if (cached) {
            return cached;
        }

        const response = await fetch(request);
        
        if (response.ok) {
            cache.put(request, response.clone());
        }
        
        return response;
    } catch (error) {
        console.error('[Service Worker] Fetch error:', error);
        return new Response('Offline - Resource not available', { status: 503 });
    }
}

/**
 * Cache first for images with fallback
 */
async function cacheFirstImages(request) {
    try {
        const cache = await caches.open(IMAGE_CACHE);
        const cached = await cache.match(request);
        
        if (cached) {
            return cached;
        }

        const response = await fetch(request);
        
        if (response.ok) {
            cache.put(request, response.clone());
        }
        
        return response;
    } catch (error) {
        // Return placeholder image on error
        return new Response(
            `<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect fill="#222" width="200" height="200"/><text x="50%" y="50%" fill="#999" text-anchor="middle" dy=".3em">Image unavailable</text></svg>`,
            { headers: { 'Content-Type': 'image/svg+xml' } }
        );
    }
}

/**
 * Network first strategy (with cache fallback)
 */
async function networkFirst(request) {
    try {
        const response = await fetch(request);
        
        if (response.ok) {
            const cache = await caches.open(
                request.url.includes('/api/') ? API_CACHE : RUNTIME_CACHE
            );
            cache.put(request, response.clone());
        }
        
        return response;
    } catch (error) {
        console.error('[Service Worker] Network error:', error);
        
        const cache = await caches.open(
            request.url.includes('/api/') ? API_CACHE : RUNTIME_CACHE
        );
        const cached = await cache.match(request);
        
        if (cached) {
            return cached;
        }

        // Return offline page
        if (request.destination === 'document') {
            return caches.match('/offline.html') || 
                   new Response('You are offline', { status: 503 });
        }

        return new Response('Offline - Resource not available', { status: 503 });
    }
}

/**
 * Message event - handle messages from clients
 */
self.addEventListener('message', (event) => {
    console.log('[Service Worker] Message received:', event.data);
    
    if (event.data.action === 'CLEAR_CACHE') {
        clearAllCaches();
    }
    
    if (event.data.action === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

/**
 * Clear all caches
 */
async function clearAllCaches() {
    const cacheNames = await caches.keys();
    await Promise.all(cacheNames.map(name => caches.delete(name)));
    console.log('[Service Worker] All caches cleared');
}

/**
 * Background sync for offline actions
 */
self.addEventListener('sync', (event) => {
    console.log('[Service Worker] Background sync:', event.tag);
    
    if (event.tag === 'sync-payments') {
        event.waitUntil(syncPayments());
    }
    
    if (event.tag === 'sync-data') {
        event.waitUntil(syncData());
    }
});

/**
 * Sync pending payments
 */
async function syncPayments() {
    try {
        const db = await openIndexedDB();
        const pendingPayments = await getPendingPayments(db);
        
        for (const payment of pendingPayments) {
            try {
                await fetch('/api/payments/initiate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payment)
                });
                
                await markPaymentSynced(db, payment.id);
            } catch (error) {
                console.error('[Service Worker] Payment sync error:', error);
            }
        }
    } catch (error) {
        console.error('[Service Worker] Sync payments error:', error);
    }
}

/**
 * Sync data changes
 */
async function syncData() {
    try {
        const db = await openIndexedDB();
        const pendingChanges = await getPendingChanges(db);
        
        for (const change of pendingChanges) {
            try {
                await fetch(change.endpoint, {
                    method: change.method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(change.data)
                });
                
                await markChangeSynced(db, change.id);
            } catch (error) {
                console.error('[Service Worker] Sync error:', error);
            }
        }
    } catch (error) {
        console.error('[Service Worker] Sync data error:', error);
    }
}

/**
 * IndexedDB helpers
 */
function openIndexedDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('FlexNetDB', 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            
            if (!db.objectStoreNames.contains('pending_payments')) {
                db.createObjectStore('pending_payments', { keyPath: 'id' });
            }
            
            if (!db.objectStoreNames.contains('pending_changes')) {
                db.createObjectStore('pending_changes', { keyPath: 'id' });
            }
        };
    });
}

async function getPendingPayments(db) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['pending_payments']);
        const store = transaction.objectStore('pending_payments');
        const request = store.getAll();
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
    });
}

async function getPendingChanges(db) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['pending_changes']);
        const store = transaction.objectStore('pending_changes');
        const request = store.getAll();
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
    });
}

async function markPaymentSynced(db, id) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['pending_payments'], 'readwrite');
        const store = transaction.objectStore('pending_payments');
        const request = store.delete(id);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve();
    });
}

async function markChangeSynced(db, id) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['pending_changes'], 'readwrite');
        const store = transaction.objectStore('pending_changes');
        const request = store.delete(id);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve();
    });
}

console.log('[Service Worker] Loaded and ready');
