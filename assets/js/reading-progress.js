/**
 * Reading Progress Indicator
 * Hiển thị tiến độ đọc trang
 */

function initReadingProgress() {
    const progressBar = document.createElement('div');
    progressBar.id = 'readingProgressBar';
    progressBar.className = 'reading-progress-bar';
    document.body.appendChild(progressBar);
    
    const updateProgress = throttle(() => {
        const windowHeight = window.innerHeight;
        const documentHeight = document.documentElement.scrollHeight;
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        const scrollableHeight = documentHeight - windowHeight;
        const progress = scrollableHeight > 0 ? (scrollTop / scrollableHeight) * 100 : 0;
        
        progressBar.style.width = Math.min(100, Math.max(0, progress)) + '%';
    }, 16); // ~60fps
    
    window.addEventListener('scroll', updateProgress, { passive: true });
    updateProgress(); // Initial update
}

// Throttle helper
function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', initReadingProgress);

// Export
window.ReadingProgress = {
    initReadingProgress
};

