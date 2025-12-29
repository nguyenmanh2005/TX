/**
 * Critical CSS Loader
 * Load critical CSS inline, defer non-critical CSS
 */

function loadCriticalCSS() {
    // Critical CSS is already inlined in <style> tag
    // This script handles non-critical CSS loading
    
    const nonCriticalCSS = [
        'assets/css/animations.css',
        'assets/css/dashboard-enhancements.css',
        'assets/css/game-ui-enhancements.css',
        'assets/css/profile-enhancements.css',
        'assets/css/shop-enhancements.css',
        'assets/css/leaderboard-enhancements.css'
    ];
    
    // Load after page load
    window.addEventListener('load', () => {
        setTimeout(() => {
            nonCriticalCSS.forEach(href => {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = href;
                link.media = 'print';
                link.onload = function() {
                    this.media = 'all';
                };
                document.head.appendChild(link);
            });
        }, 100);
    });
}

// Initialize
loadCriticalCSS();

