/**
 * Memory Manager - Quản lý bộ nhớ và cleanup
 */

class MemoryManager {
    constructor() {
        this.observers = [];
        this.intervals = [];
        this.timeouts = [];
        this.eventListeners = [];
        this.init();
    }

    init() {
        this.setupPeriodicCleanup();
        this.setupVisibilityCleanup();
        this.trackResources();
    }

    setupPeriodicCleanup() {
        // Cleanup every 5 minutes
        setInterval(() => {
            this.cleanup();
        }, 5 * 60 * 1000);
    }

    setupVisibilityCleanup() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.aggressiveCleanup();
            }
        });
    }

    trackResources() {
        // Track IntersectionObservers
        const originalObserver = window.IntersectionObserver;
        window.IntersectionObserver = class extends originalObserver {
            constructor(...args) {
                super(...args);
                window.memoryManager.observers.push(this);
            }
        };

        // Track setInterval
        const originalSetInterval = window.setInterval;
        window.setInterval = (...args) => {
            const id = originalSetInterval(...args);
            window.memoryManager.intervals.push(id);
            return id;
        };

        // Track setTimeout
        const originalSetTimeout = window.setTimeout;
        window.setTimeout = (...args) => {
            const id = originalSetTimeout(...args);
            window.memoryManager.timeouts.push(id);
            return id;
        };
    }

    cleanup() {
        // Cleanup old cache entries
        this.cleanupCache();

        // Cleanup unused observers
        this.cleanupObservers();

        // Cleanup old localStorage
        this.cleanupLocalStorage();

        // Force garbage collection hint
        if (window.gc) {
            window.gc();
        }
    }

    aggressiveCleanup() {
        // More aggressive cleanup when page is hidden
        this.cleanup();
        
        // Clear non-critical intervals
        this.intervals.forEach(id => {
            if (id % 2 === 0) { // Clear even IDs (less critical)
                clearInterval(id);
            }
        });

        // Clear non-critical timeouts
        this.timeouts.forEach(id => {
            clearTimeout(id);
        });
    }

    cleanupCache() {
        if ('caches' in window) {
            caches.keys().then(keys => {
                keys.forEach(key => {
                    // Keep only latest version
                    if (!key.includes('v1')) {
                        caches.delete(key);
                    }
                });
            });
        }
    }

    cleanupObservers() {
        // Disconnect unused observers
        this.observers.forEach(observer => {
            if (observer._targets && observer._targets.length === 0) {
                observer.disconnect();
            }
        });
    }

    cleanupLocalStorage() {
        const now = Date.now();
        const keys = Object.keys(localStorage);
        
        keys.forEach(key => {
            if (key.startsWith('cache_') || key.startsWith('temp_')) {
                try {
                    const data = JSON.parse(localStorage.getItem(key));
                    if (data.timestamp && now - data.timestamp > (data.ttl || 3600000)) {
                        localStorage.removeItem(key);
                    }
                } catch (e) {
                    // Invalid data, remove it
                    localStorage.removeItem(key);
                }
            }
        });
    }

    // Register cleanup callback
    registerCleanup(callback) {
        this.cleanupCallbacks = this.cleanupCallbacks || [];
        this.cleanupCallbacks.push(callback);
    }

    // Get memory usage (if available)
    getMemoryUsage() {
        if (performance.memory) {
            return {
                used: performance.memory.usedJSHeapSize,
                total: performance.memory.totalJSHeapSize,
                limit: performance.memory.jsHeapSizeLimit
            };
        }
        return null;
    }
}

// Initialize
window.memoryManager = new MemoryManager();

// Cleanup on unload
window.addEventListener('beforeunload', () => {
    if (window.memoryManager) {
        window.memoryManager.cleanup();
    }
});

