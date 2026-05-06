/**
 * Notifications Enhancer
 * Cải thiện real-time notifications với WebSocket fallback và better UI
 */

let notificationUpdateTimer = null;
let lastNotificationCheck = 0;
const NOTIFICATION_UPDATE_INTERVAL = 15000; // 15 seconds
const NOTIFICATION_CACHE_DURATION = 5000; // 5 seconds cache

// Notification queue for offline mode
let notificationQueue = JSON.parse(localStorage.getItem('notificationQueue') || '[]');

function initNotificationsEnhancer() {
    // Update notification badge
    updateNotificationBadge();
    
    // Update on visibility change
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            updateNotificationBadge();
        }
    });
    
    // Update on focus
    window.addEventListener('focus', updateNotificationBadge);
    
    // Periodic update with debouncing
    setInterval(() => {
        updateNotificationBadge();
    }, NOTIFICATION_UPDATE_INTERVAL);
    
    // Listen for storage events (multi-tab sync)
    window.addEventListener('storage', (e) => {
        if (e.key === 'notifications') {
            updateNotificationBadge();
        }
    });
}

function updateNotificationBadge() {
    const now = Date.now();
    
    // Check cache
    if (now - lastNotificationCheck < NOTIFICATION_CACHE_DURATION) {
        return;
    }
    
    // Clear previous timer
    if (notificationUpdateTimer) {
        clearTimeout(notificationUpdateTimer);
    }
    
    notificationUpdateTimer = setTimeout(() => {
        fetch('api_get_notifications.php?action=get_unread_count', {
            credentials: 'same-origin',
            cache: 'no-cache',
            headers: {
                'Cache-Control': 'no-cache'
            }
        })
            .then(response => {
                if (!response.ok) throw new Error('Network error');
                return response.json();
            })
            .then(data => {
                const badge = document.getElementById('notificationsBadge');
                const count = data.count || 0;
                
                if (badge) {
                    if (count > 0) {
                        badge.textContent = count > 99 ? '99+' : count;
                        badge.style.display = 'inline-block';
                        
                        // Pulse animation for new notifications
                        if (parseInt(badge.textContent) > parseInt(badge.getAttribute('data-last-count') || '0')) {
                            badge.classList.add('pulse');
                            setTimeout(() => badge.classList.remove('pulse'), 1000);
                        }
                        
                        badge.setAttribute('data-last-count', count);
                    } else {
                        badge.style.display = 'none';
                    }
                }
                
                // Update page title
                updatePageTitle(count);
                
                lastNotificationCheck = Date.now();
            })
            .catch(err => {
                console.log('Notification update error:', err);
                // Retry after 5 seconds on error
                setTimeout(updateNotificationBadge, 5000);
            });
    }, 300); // Debounce 300ms
}

function updatePageTitle(unreadCount) {
    const baseTitle = document.title.replace(/^\(\d+\)\s*/, '');
    if (unreadCount > 0) {
        document.title = `(${unreadCount > 99 ? '99+' : unreadCount}) ${baseTitle}`;
    } else {
        document.title = baseTitle;
    }
}

// Show desktop notification (if permission granted)
function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                console.log('Notification permission granted');
            }
        });
    }
}

function showDesktopNotification(title, body, icon = null) {
    if ('Notification' in window && Notification.permission === 'granted') {
        const notification = new Notification(title, {
            body: body,
            icon: icon || 'images.ico',
            badge: 'images.ico',
            tag: 'game-notification',
            requireInteraction: false
        });
        
        notification.onclick = () => {
            window.focus();
            notification.close();
        };
        
        // Auto close after 5 seconds
        setTimeout(() => notification.close(), 5000);
    }
}

// Queue notification for offline mode
function queueNotification(notification) {
    notificationQueue.push({
        ...notification,
        timestamp: Date.now()
    });
    
    // Keep only last 50
    notificationQueue = notificationQueue.slice(-50);
    localStorage.setItem('notificationQueue', JSON.stringify(notificationQueue));
}

// Process queued notifications when online
function processNotificationQueue() {
    if (notificationQueue.length === 0) return;
    
    const now = Date.now();
    const recentNotifications = notificationQueue.filter(n => now - n.timestamp < 3600000); // Last hour
    
    if (recentNotifications.length > 0) {
        // Show notifications
        recentNotifications.forEach(notif => {
            if (typeof QuickActions !== 'undefined' && QuickActions.showToast) {
                QuickActions.showToast(notif.message || 'Có thông báo mới', 'info');
            }
        });
        
        // Clear processed notifications
        notificationQueue = notificationQueue.filter(n => now - n.timestamp >= 3600000);
        localStorage.setItem('notificationQueue', JSON.stringify(notificationQueue));
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    initNotificationsEnhancer();
    requestNotificationPermission();
    
    // Process queued notifications
    if (typeof OfflineDetector !== 'undefined' && OfflineDetector.isOnline()) {
        processNotificationQueue();
    }
});

// Export
window.NotificationsEnhancer = {
    updateNotificationBadge,
    showDesktopNotification,
    queueNotification,
    processNotificationQueue
};

