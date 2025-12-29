/**
 * API Cache Manager - Quản lý cache cho API calls
 */

class APICacheManager {
    constructor() {
        this.cache = new Map();
        this.defaultTTL = 60000; // 60 seconds
        this.init();
    }

    init() {
        this.setupCacheCleanup();
        this.interceptFetch();
    }

    setupCacheCleanup() {
        // Clean up expired cache every minute
        setInterval(() => {
            const now = Date.now();
            for (const [key, value] of this.cache.entries()) {
                if (now - value.timestamp > value.ttl) {
                    this.cache.delete(key);
                }
            }
        }, 60000);
    }

    interceptFetch() {
        const originalFetch = window.fetch;
        const self = this;
        
        window.fetch = async function(url, options = {}) {
            // Only cache GET requests
            if (options.method && options.method !== 'GET') {
                return originalFetch(url, options);
            }
            
            // Check cache
            const cacheKey = self.getCacheKey(url, options);
            const cached = self.get(cacheKey);
            
            if (cached) {
                return new Response(JSON.stringify(cached), {
                    headers: { 'Content-Type': 'application/json' }
                });
            }
            
            // Make request
            try {
                const response = await originalFetch(url, options);
                const data = await response.json();
                
                // Cache response
                self.set(cacheKey, data, self.defaultTTL);
                
                return new Response(JSON.stringify(data), {
                    headers: { 'Content-Type': 'application/json' }
                });
            } catch (error) {
                console.error('Fetch error:', error);
                throw error;
            }
        };
    }

    getCacheKey(url, options) {
        return `${url}_${JSON.stringify(options)}`;
    }

    get(key) {
        const cached = this.cache.get(key);
        if (!cached) return null;
        
        const now = Date.now();
        if (now - cached.timestamp > cached.ttl) {
            this.cache.delete(key);
            return null;
        }
        
        return cached.data;
    }

    set(key, data, ttl = this.defaultTTL) {
        this.cache.set(key, {
            data: data,
            timestamp: Date.now(),
            ttl: ttl
        });
    }

    invalidate(pattern) {
        for (const key of this.cache.keys()) {
            if (key.includes(pattern)) {
                this.cache.delete(key);
            }
        }
    }

    clear() {
        this.cache.clear();
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    window.apiCacheManager = new APICacheManager();
});

