/**
 * Error Tracker
 * Track và log errors để debug
 */

const ErrorTracker = {
    errors: [],
    maxErrors: 50,
    
    init: function() {
        // Track JavaScript errors
        window.addEventListener('error', (e) => {
            this.trackError({
                type: 'javascript',
                message: e.message,
                filename: e.filename,
                lineno: e.lineno,
                colno: e.colno,
                stack: e.error?.stack,
                timestamp: new Date().toISOString()
            });
        });
        
        // Track unhandled promise rejections
        window.addEventListener('unhandledrejection', (e) => {
            this.trackError({
                type: 'promise',
                message: e.reason?.message || String(e.reason),
                stack: e.reason?.stack,
                timestamp: new Date().toISOString()
            });
        });
        
        // Track resource loading errors
        window.addEventListener('error', (e) => {
            if (e.target !== window && e.target.tagName) {
                this.trackError({
                    type: 'resource',
                    message: `Failed to load ${e.target.tagName}: ${e.target.src || e.target.href}`,
                    element: e.target.tagName,
                    source: e.target.src || e.target.href,
                    timestamp: new Date().toISOString()
                });
            }
        }, true);
    },
    
    trackError: function(error) {
        this.errors.push(error);
        
        // Keep only last N errors
        if (this.errors.length > this.maxErrors) {
            this.errors.shift();
        }
        
        // Log to console in development
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.error('Error tracked:', error);
        }
        
        // Save to localStorage
        try {
            localStorage.setItem('errorLog', JSON.stringify(this.errors));
        } catch (e) {
            console.warn('Could not save error log:', e);
        }
        
        // Send to server (optional)
        // this.sendToServer(error);
    },
    
    getErrors: function() {
        return this.errors;
    },
    
    clearErrors: function() {
        this.errors = [];
        localStorage.removeItem('errorLog');
    },
    
    sendToServer: function(error) {
        // Optional: Send errors to server for logging
        fetch('api_log_error.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(error)
        }).catch(err => {
            console.warn('Could not send error to server:', err);
        });
    },
    
    getErrorReport: function() {
        return {
            total: this.errors.length,
            byType: this.errors.reduce((acc, err) => {
                acc[err.type] = (acc[err.type] || 0) + 1;
                return acc;
            }, {}),
            recent: this.errors.slice(-10),
            all: this.errors
        };
    }
};

// Initialize on load
ErrorTracker.init();

// Export
window.ErrorTracker = ErrorTracker;

