/**
 * FlexNet PWA Initialization
 * Registers service worker and manages offline functionality
 */

class PWAManager {
    constructor() {
        this.deferredPrompt = null;
        this.isOnline = navigator.onLine;
        this.init();
    }

    /**
     * Initialize PWA features
     */
    async init() {
        console.log('[PWA] Initializing...');
        
        // Register service worker
        await this.registerServiceWorker();
        
        // Handle install prompt
        this.handleInstallPrompt();
        
        // Listen for online/offline events
        this.setupNetworkListeners();
        
        // Setup cache management
        this.setupCache();
        
        console.log('[PWA] Initialization complete');
    }

    /**
     * Register service worker
     */
    async registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            console.log('[PWA] Service Worker not supported');
            return;
        }

        try {
            const registration = await navigator.serviceWorker.register('/service-worker.js', {
                scope: '/'
            });
            
            console.log('[PWA] Service Worker registered:', registration);
            
            // Check for updates
            registration.addEventListener('updatefound', () => {
                const newWorker = registration.installing;
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        this.showUpdateNotification();
                    }
                });
            });
            
            return registration;
        } catch (error) {
            console.error('[PWA] Service Worker registration failed:', error);
        }
    }

    /**
     * Handle install prompt
     */
    handleInstallPrompt() {
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredPrompt = e;
            this.showInstallButton();
        });

        window.addEventListener('appinstalled', () => {
            console.log('[PWA] App installed');
            this.hideInstallButton();
            this.deferredPrompt = null;
        });
    }

    /**
     * Show install button
     */
    showInstallButton() {
        const installBtn = document.getElementById('pwa-install-btn');
        if (installBtn) {
            installBtn.style.display = 'inline-block';
            installBtn.addEventListener('click', async () => {
                if (this.deferredPrompt) {
                    this.deferredPrompt.prompt();
                    const { outcome } = await this.deferredPrompt.userChoice;
                    console.log(`[PWA] User response to install: ${outcome}`);
                    this.deferredPrompt = null;
                }
            });
        }
    }

    /**
     * Hide install button
     */
    hideInstallButton() {
        const installBtn = document.getElementById('pwa-install-btn');
        if (installBtn) {
            installBtn.style.display = 'none';
        }
    }

    /**
     * Setup network listeners
     */
    setupNetworkListeners() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.showNotification('You are online!', 'success');
            this.syncOfflineData();
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.showNotification('You are offline. Changes will be synced when online.', 'warning');
        });
    }

    /**
     * Setup cache management
     */
    setupCache() {
        // Store auth tokens in IndexedDB
        this.setupIndexedDB();
        
        // Setup local storage optimizations
        this.optimizeLocalStorage();
    }

    /**
     * Setup IndexedDB for offline data
     */
    async setupIndexedDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('FlexNetDB', 1);
            
            request.onerror = () => {
                console.error('[PWA] IndexedDB error:', request.error);
                reject(request.error);
            };
            
            request.onsuccess = () => {
                console.log('[PWA] IndexedDB ready');
                resolve(request.result);
            };
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Create object stores
                const stores = [
                    'pending_payments',
                    'pending_changes',
                    'user_data',
                    'admin_data',
                    'locations',
                    'households'
                ];
                
                stores.forEach(store => {
                    if (!db.objectStoreNames.contains(store)) {
                        db.createObjectStore(store, { keyPath: 'id' });
                    }
                });
            };
        });
    }

    /**
     * Optimize local storage
     */
    optimizeLocalStorage() {
        // Limit localStorage to 5MB
        const maxSize = 5 * 1024 * 1024;
        let currentSize = 0;

        Object.keys(localStorage).forEach(key => {
            currentSize += key.length + localStorage[key].length;
        });

        if (currentSize > maxSize) {
            // Clear oldest data
            const keys = Object.keys(localStorage).sort();
            while (currentSize > maxSize * 0.8 && keys.length > 0) {
                const key = keys.shift();
                if (!['admin_token', 'user_token'].includes(key)) {
                    currentSize -= key.length + localStorage[key].length;
                    localStorage.removeItem(key);
                }
            }
        }
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('FlexNet', {
                body: message,
                icon: '/user/favicon/android-chrome-192x192.png'
            });
        }
    }

    /**
     * Show update notification
     */
    showUpdateNotification() {
        const msg = document.createElement('div');
        msg.className = 'alert alert-info alert-dismissible fade show position-fixed bottom-0 start-0 m-3';
        msg.style.zIndex = '9999';
        msg.innerHTML = `
            <strong>Update available!</strong> An updated version of FlexNet is ready.
            <button onclick="location.reload()" class="btn btn-sm btn-primary ms-2">Reload</button>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(msg);
    }

    /**
     * Sync offline data
     */
    async syncOfflineData() {
        console.log('[PWA] Syncing offline data...');
        
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({ action: 'SYNC_DATA' });
        }
    }

    /**
     * Save data for offline access
     */
    async saveForOffline(key, data) {
        try {
            const db = await this.getDB();
            const transaction = db.transaction(['user_data'], 'readwrite');
            const store = transaction.objectStore('user_data');
            
            return new Promise((resolve, reject) => {
                const request = store.put({ id: key, data, timestamp: Date.now() });
                request.onerror = () => reject(request.error);
                request.onsuccess = () => resolve();
            });
        } catch (error) {
            console.error('[PWA] Save for offline error:', error);
        }
    }

    /**
     * Get offline data
     */
    async getOfflineData(key) {
        try {
            const db = await this.getDB();
            const transaction = db.transaction(['user_data']);
            const store = transaction.objectStore('user_data');
            
            return new Promise((resolve, reject) => {
                const request = store.get(key);
                request.onerror = () => reject(request.error);
                request.onsuccess = () => resolve(request.result?.data);
            });
        } catch (error) {
            console.error('[PWA] Get offline data error:', error);
        }
    }

    /**
     * Get IndexedDB instance
     */
    async getDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('FlexNetDB', 1);
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
        });
    }

    /**
     * Request notification permission
     */
    async requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            const permission = await Notification.requestPermission();
            console.log('[PWA] Notification permission:', permission);
            return permission === 'granted';
        }
        return Notification.permission === 'granted';
    }
}

// Initialize PWA on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    window.pwaManager = new PWAManager();
});

export default PWAManager;
