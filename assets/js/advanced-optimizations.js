/**
 * Advanced Optimizations
 * Tối ưu nâng cao cho performance
 */

// Request Queue for offline mode
const requestQueue = [];

// Queue requests when offline
function queueRequest(url, options) {
    if (typeof OfflineDetector !== 'undefined' && !OfflineDetector.isOnline()) {
        requestQueue.push({ url, options, timestamp: Date.now() });
        localStorage.setItem('requestQueue', JSON.stringify(requestQueue));
        return Promise.reject(new Error('Offline'));
    }
    return fetch(url, options);
}

// Process queued requests when online
function processRequestQueue() {
    if (requestQueue.length === 0) return;
    
    const queue = JSON.parse(localStorage.getItem('requestQueue') || '[]');
    const now = Date.now();
    const recentRequests = queue.filter(req => now - req.timestamp < 3600000); // Last hour
    
    recentRequests.forEach(req => {
        fetch(req.url, req.options)
            .then(() => {
                // Remove from queue on success
                const index = requestQueue.findIndex(r => r.url === req.url);
                if (index > -1) {
                    requestQueue.splice(index, 1);
                    localStorage.setItem('requestQueue', JSON.stringify(requestQueue));
                }
            })
            .catch(err => console.log('Queued request failed:', err));
    });
}

// Image compression helper (client-side)
function compressImage(file, maxWidth = 1920, quality = 0.8) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;
                
                if (width > maxWidth) {
                    height = (height * maxWidth) / width;
                    width = maxWidth;
                }
                
                canvas.width = width;
                canvas.height = height;
                
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                
                canvas.toBlob(resolve, 'image/jpeg', quality);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}

// Memory cleanup
function cleanupMemory() {
    // Clear old cached data
    const keys = Object.keys(localStorage);
    keys.forEach(key => {
        if (key.startsWith('cache_')) {
            const data = JSON.parse(localStorage.getItem(key) || '{}');
            if (data.timestamp && Date.now() - data.timestamp > 86400000) { // 24 hours
                localStorage.removeItem(key);
            }
        }
    });
}

// Initialize cleanup
setInterval(cleanupMemory, 3600000); // Every hour

// Process queue when online
if (typeof OfflineDetector !== 'undefined') {
    if (OfflineDetector.isOnline()) {
        processRequestQueue();
    }
    
    // Listen for online event
    window.addEventListener('online', processRequestQueue);
}

// Export
window.AdvancedOptimizations = {
    queueRequest,
    processRequestQueue,
    compressImage,
    cleanupMemory
};

