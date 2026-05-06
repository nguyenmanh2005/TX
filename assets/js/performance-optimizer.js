/**
 * Performance Optimizer - Tối ưu hiệu năng cho web
 */

class PerformanceOptimizer {
    constructor() {
        this.init();
    }

    init() {
        this.setupLazyLoading();
        this.setupImageOptimization();
        this.setupCodeSplitting();
        this.setupCaching();
        this.setupDebounceThrottle();
        this.setupResourceHints();
    }

    setupLazyLoading() {
        // Lazy load images
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                        observer.unobserve(img);
                    }
                }
            });
        });
        
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
        }

        // Lazy load scripts
        const scripts = document.querySelectorAll('script[data-src]');
        scripts.forEach(script => {
            if ('IntersectionObserver' in window) {
                const scriptObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const newScript = document.createElement('script');
                            newScript.src = entry.target.dataset.src;
                            document.head.appendChild(newScript);
                            scriptObserver.unobserve(entry.target);
                        }
                    });
                });
                scriptObserver.observe(script);
            }
        });
    }

    setupImageOptimization() {
        // Convert images to WebP if supported
        if (this.supportsWebP()) {
            document.querySelectorAll('img[data-webp]').forEach(img => {
                img.src = img.dataset.webp;
            });
        }

        // Compress images on the fly
        this.compressImages();
    }

    supportsWebP() {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;
        return canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
    }

    compressImages() {
        // Client-side image compression (if needed)
        // This is a placeholder - actual compression should be done server-side
    }

    setupCodeSplitting() {
        // Load modules on demand
        this.loadModule = async (moduleName) => {
            try {
                const module = await import(`./modules/${moduleName}.js`);
                return module;
            } catch (error) {
                console.error(`Error loading module ${moduleName}:`, error);
            }
        };
    }

    setupCaching() {
        // Service Worker caching (if available)
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/register-service-worker.js')
                .then(registration => {
                    console.log('Service Worker registered:', registration);
                })
                .catch(error => {
                    console.error('Service Worker registration failed:', error);
                });
}

        // LocalStorage caching for API responses
        this.cacheAPIResponse = (key, data, ttl = 60000) => {
            const cacheData = {
                data: data,
                timestamp: Date.now(),
                ttl: ttl
            };
            localStorage.setItem(`cache_${key}`, JSON.stringify(cacheData));
        };

        this.getCachedAPIResponse = (key) => {
            const cached = localStorage.getItem(`cache_${key}`);
            if (!cached) return null;

            const cacheData = JSON.parse(cached);
            const now = Date.now();
            
            if (now - cacheData.timestamp > cacheData.ttl) {
                localStorage.removeItem(`cache_${key}`);
                return null;
            }

            return cacheData.data;
        };
    }

    setupDebounceThrottle() {
        // Debounce function
        this.debounce = (func, wait) => {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
        };

        // Throttle function
        this.throttle = (func, limit) => {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
        };

        // Apply to scroll events
        const handleScroll = this.throttle(() => {
            // Scroll handling
    }, 100);
    
        window.addEventListener('scroll', handleScroll, { passive: true });
}

    setupResourceHints() {
        // Preconnect to external domains
        const preconnectDomains = [
            'https://cdnjs.cloudflare.com',
            'https://fonts.googleapis.com'
        ];

        preconnectDomains.forEach(domain => {
            const link = document.createElement('link');
            link.rel = 'preconnect';
            link.href = domain;
            document.head.appendChild(link);
        });

        // Prefetch critical resources
        const prefetchResources = [
            '/assets/css/game-animations.css',
            '/assets/js/game-animations-enhanced.js'
    ];
    
        prefetchResources.forEach(resource => {
        const link = document.createElement('link');
            link.rel = 'prefetch';
        link.href = resource;
        document.head.appendChild(link);
    });
}

    // Optimize animations
    optimizeAnimations() {
        // Use will-change for animated elements
        document.querySelectorAll('.animate-fade-in, .animate-bounce, .animate-pulse').forEach(el => {
            el.style.willChange = 'transform, opacity';
        });

        // Use transform instead of position changes
        // This is already handled in CSS, but we can add JS optimizations
    }

    // Memory cleanup
    cleanup() {
        // Remove unused event listeners
        // Clear intervals/timeouts
        // Clean up observers
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    window.performanceOptimizer = new PerformanceOptimizer();
});
    
// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.performanceOptimizer) {
        window.performanceOptimizer.cleanup();
    }
});
