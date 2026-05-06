/**
 * Enhanced Back to Top Button
 * Nút quay lại đầu trang với animations
 */

function initBackToTop() {
    // Check if FAB already exists
    let fab = document.querySelector('.fab[onclick*="scrollTo"]');
    
    if (!fab) {
        // Create new FAB
        fab = document.createElement('button');
        fab.className = 'fab';
        fab.innerHTML = '↑';
        fab.title = 'Lên đầu trang';
        fab.setAttribute('aria-label', 'Lên đầu trang');
        document.body.appendChild(fab);
    }
    
    // Enhanced scroll behavior
    fab.addEventListener('click', function(e) {
        e.preventDefault();
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
        
        // Add click animation
        this.style.transform = 'scale(0.9)';
        setTimeout(() => {
            this.style.transform = '';
        }, 150);
    });
    
    // Show/hide based on scroll position
    let isVisible = false;
    const scrollHandler = throttle(() => {
        const scrollY = window.pageYOffset;
        
        if (scrollY > 300 && !isVisible) {
            fab.style.opacity = '1';
            fab.style.pointerEvents = 'auto';
            fab.style.transform = 'scale(1)';
            isVisible = true;
        } else if (scrollY <= 300 && isVisible) {
            fab.style.opacity = '0';
            fab.style.pointerEvents = 'none';
            fab.style.transform = 'scale(0.8)';
            isVisible = false;
        }
    }, 100);
    
    window.addEventListener('scroll', scrollHandler, { passive: true });
    
    // Initial check
    scrollHandler();
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
document.addEventListener('DOMContentLoaded', initBackToTop);

// Export
window.BackToTop = {
    initBackToTop
};

