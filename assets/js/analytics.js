/**
 * Analytics System
 * Track user behavior và page views
 */

const Analytics = {
    events: [],
    maxEvents: 100,
    
    init: function() {
        // Track page view
        this.trackPageView();
        
        // Track clicks on important elements
        document.addEventListener('click', (e) => {
            const target = e.target.closest('[data-track]');
            if (target) {
                this.trackEvent('click', {
                    element: target.getAttribute('data-track'),
                    text: target.textContent.trim().substring(0, 50),
                    url: window.location.href
                });
            }
        });
        
        // Track form submissions
        document.addEventListener('submit', (e) => {
            if (e.target.tagName === 'FORM') {
                this.trackEvent('form_submit', {
                    form: e.target.id || e.target.className,
                    url: window.location.href
                });
            }
        });
        
        // Track errors
        if (typeof ErrorTracker !== 'undefined') {
            const originalTrack = ErrorTracker.trackError;
            ErrorTracker.trackError = (error) => {
                originalTrack.call(ErrorTracker, error);
                this.trackEvent('error', {
                    type: error.type,
                    message: error.message
                });
            };
        }
        
        // Save events periodically
        setInterval(() => this.saveEvents(), 30000); // Every 30 seconds
        
        // Send events on page unload
        window.addEventListener('beforeunload', () => {
            this.sendEvents();
        });
    },
    
    trackPageView: function() {
        this.trackEvent('page_view', {
            url: window.location.href,
            title: document.title,
            referrer: document.referrer
        });
    },
    
    trackEvent: function(eventName, data = {}) {
        const event = {
            name: eventName,
            data: data,
            timestamp: new Date().toISOString(),
            sessionId: this.getSessionId(),
            userId: this.getUserId()
        };
        
        this.events.push(event);
        
        // Keep only last N events
        if (this.events.length > this.maxEvents) {
            this.events.shift();
        }
        
        // Log in development
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.log('Analytics event:', event);
        }
    },
    
    getSessionId: function() {
        let sessionId = sessionStorage.getItem('analytics_session_id');
        if (!sessionId) {
            sessionId = 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            sessionStorage.setItem('analytics_session_id', sessionId);
        }
        return sessionId;
    },
    
    getUserId: function() {
        // Get from session or cookie
        return null; // Implement based on your auth system
    },
    
    saveEvents: function() {
        try {
            localStorage.setItem('analytics_events', JSON.stringify(this.events));
        } catch (e) {
            console.warn('Could not save analytics events:', e);
        }
    },
    
    sendEvents: function() {
        if (this.events.length === 0) return;
        
        // Send to server
        fetch('api_track_analytics.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                events: this.events
            }),
            keepalive: true // Important for beforeunload
        }).catch(err => {
            console.warn('Could not send analytics:', err);
            // Save for later
            this.saveEvents();
        });
        
        // Clear sent events
        this.events = [];
    },
    
    getReport: function() {
        return {
            totalEvents: this.events.length,
            byType: this.events.reduce((acc, event) => {
                acc[event.name] = (acc[event.name] || 0) + 1;
                return acc;
            }, {}),
            events: this.events
        };
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => Analytics.init());

// Export
window.Analytics = Analytics;

