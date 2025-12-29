/**
 * Performance Advanced - Tối ưu performance nâng cao
 */

class PerformanceAdvanced {
    constructor() {
        this.init();
    }

    init() {
        this.setupVirtualScrolling();
        this.setupRequestIdleCallback();
        this.setupCriticalCSS();
        this.setupPrefetching();
        this.setupServiceWorker();
    }

    setupVirtualScrolling() {
        // Virtual scrolling for long lists
        this.virtualScroll = (container, items, itemHeight) => {
            const visibleCount = Math.ceil(container.offsetHeight / itemHeight);
            let startIndex = 0;
            
            const render = () => {
                const endIndex = Math.min(startIndex + visibleCount, items.length);
                const visibleItems = items.slice(startIndex, endIndex);
                
                container.innerHTML = '';
                visibleItems.forEach(item => {
                    container.appendChild(item);
                });
            };
            
            container.addEventListener('scroll', () => {
                const newStartIndex = Math.floor(container.scrollTop / itemHeight);
                if (newStartIndex !== startIndex) {
                    startIndex = newStartIndex;
                    render();
                }
            });
            
            render();
        };
    }

    setupRequestIdleCallback() {
        // Use requestIdleCallback for non-critical tasks
        if ('requestIdleCallback' in window) {
            this.scheduleTask = (task, timeout = 5000) => {
                requestIdleCallback(task, { timeout });
            };
        } else {
            // Fallback
            this.scheduleTask = (task) => {
                setTimeout(task, 1);
            };
        }
        
        // Schedule non-critical tasks
        this.scheduleTask(() => {
            this.preloadNextPage();
            this.warmupCache();
        });
    }

    setupCriticalCSS() {
        // Inline critical CSS
        const criticalCSS = `
            body { margin: 0; padding: 0; }
            .loading { display: none; }
        `;
        
        const style = document.createElement('style');
        style.textContent = criticalCSS;
        document.head.appendChild(style);
    }

    setupPrefetching() {
        // Prefetch next likely pages
        const links = document.querySelectorAll('a[href]');
        links.forEach(link => {
            link.addEventListener('mouseenter', () => {
                const href = link.getAttribute('href');
                if (href && !href.startsWith('#')) {
                    const prefetchLink = document.createElement('link');
                    prefetchLink.rel = 'prefetch';
                    prefetchLink.href = href;
                    document.head.appendChild(prefetchLink);
                }
            }, { once: true });
        });
    }

    setupServiceWorker() {
        // Register service worker for offline support
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(registration => {
                    console.log('Service Worker registered:', registration);
                })
                .catch(error => {
                    console.error('Service Worker registration failed:', error);
                });
        }
    }

    preloadNextPage() {
        // Preload likely next page
        const nextPage = this.getNextPage();
        if (nextPage) {
            const link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = nextPage;
            document.head.appendChild(link);
        }
    }

    getNextPage() {
        // Determine next likely page based on current page
        const currentPath = window.location.pathname;
        const pageMap = {
            '/index.php': '/games.php',
            '/games.php': '/slot.php',
            '/slot.php': '/dice.php'
        };
        return pageMap[currentPath];
    }

    warmupCache() {
        // Warm up cache with common API calls
        const commonAPIs = [
            '/api_dashboard_widgets.php',
            '/api_game_statistics.php'
        ];
        
        commonAPIs.forEach(api => {
            fetch(api).catch(() => {}); // Silent fail
        });
    }

    // Debounce scroll events
    optimizeScroll() {
        let ticking = false;
        const handleScroll = () => {
            if (!ticking) {
                window.requestAnimationFrame(() => {
                    // Scroll handling
                    ticking = false;
                });
                ticking = true;
            }
        };
        
        window.addEventListener('scroll', handleScroll, { passive: true });
    }

    // Optimize resize events
    optimizeResize() {
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                // Resize handling
            }, 250);
        });
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    window.performanceAdvanced = new PerformanceAdvanced();
});
