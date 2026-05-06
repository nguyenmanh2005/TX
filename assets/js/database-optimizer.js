/**
 * Database Optimizer - Tối ưu database queries
 */

class DatabaseOptimizer {
    constructor() {
        this.cache = new Map();
        this.pendingQueries = new Map();
        this.init();
    }

    init() {
        this.setupQueryCache();
        this.setupBatchRequests();
        this.setupConnectionPooling();
    }

    setupQueryCache() {
        // Cache API responses
        const originalFetch = window.fetch;
        window.fetch = async (url, options) => {
            // Check cache first
            const cacheKey = `${url}_${JSON.stringify(options)}`;
            const cached = this.cache.get(cacheKey);
            
            if (cached && Date.now() - cached.timestamp < 30000) { // 30s cache
                return new Response(JSON.stringify(cached.data), {
                    headers: { 'Content-Type': 'application/json' }
                });
            }
            
            // Make request
            const response = await originalFetch(url, options);
            const data = await response.json();
            
            // Cache response
            this.cache.set(cacheKey, {
                data: data,
                timestamp: Date.now()
            });
            
            return new Response(JSON.stringify(data), {
                headers: { 'Content-Type': 'application/json' }
            });
        };
    }

    setupBatchRequests() {
        // Batch multiple API calls into one
        this.batchQueue = [];
        this.batchTimer = null;
        
        this.batchRequest = (url, options) => {
            return new Promise((resolve) => {
                this.batchQueue.push({ url, options, resolve });
                
                if (!this.batchTimer) {
                    this.batchTimer = setTimeout(() => {
                        this.processBatch();
                    }, 100); // Batch every 100ms
                }
            });
        };
    }

    async processBatch() {
        if (this.batchQueue.length === 0) return;
        
        const batch = [...this.batchQueue];
        this.batchQueue = [];
        this.batchTimer = null;
        
        // Process batch requests
        const results = await Promise.all(
            batch.map(item => fetch(item.url, item.options))
        );
        
        results.forEach((result, index) => {
            batch[index].resolve(result);
        });
    }

    setupConnectionPooling() {
        // Optimize connection reuse
        // This is handled server-side, but we can optimize client requests
        this.requestQueue = [];
        this.maxConcurrent = 5;
        this.activeRequests = 0;
        
        this.queueRequest = async (url, options) => {
            return new Promise((resolve, reject) => {
                this.requestQueue.push({ url, options, resolve, reject });
                this.processQueue();
            });
        };
        
        this.processQueue = async () => {
            if (this.activeRequests >= this.maxConcurrent || this.requestQueue.length === 0) {
                return;
            }
            
            this.activeRequests++;
            const { url, options, resolve, reject } = this.requestQueue.shift();
            
            try {
                const response = await fetch(url, options);
                resolve(response);
            } catch (error) {
                reject(error);
            } finally {
                this.activeRequests--;
                this.processQueue();
            }
        };
    }

    // Clear cache
    clearCache() {
        this.cache.clear();
    }

    // Clear cache for specific key
    clearCacheKey(key) {
        this.cache.delete(key);
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    window.databaseOptimizer = new DatabaseOptimizer();
});

