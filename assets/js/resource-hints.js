/**
 * Resource Hints
 * Preconnect và DNS prefetch cho better performance
 */

function addResourceHints() {
    const hints = [
        // Preconnect to external domains
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossOrigin: true },
        { rel: 'preconnect', href: 'https://cdn.jsdelivr.net' },
        { rel: 'preconnect', href: 'https://code.jquery.com' },
        
        // DNS prefetch
        { rel: 'dns-prefetch', href: 'https://cdnjs.cloudflare.com' },
        { rel: 'dns-prefetch', href: 'https://api.example.com' }
    ];
    
    hints.forEach(hint => {
        const link = document.createElement('link');
        link.rel = hint.rel;
        link.href = hint.href;
        if (hint.crossOrigin) {
            link.crossOrigin = 'anonymous';
        }
        document.head.appendChild(link);
    });
}

// Add hints on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', addResourceHints);
} else {
    addResourceHints();
}

