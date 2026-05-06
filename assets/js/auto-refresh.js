/**
 * Auto Refresh System
 * Tự động refresh các widget và data
 */

let autoRefreshIntervals = {};

function initAutoRefresh() {
    // Refresh balance every 30 seconds
    autoRefreshIntervals.balance = setInterval(() => {
        if (typeof DashboardWidgets !== 'undefined' && DashboardWidgets.initBalanceUpdater) {
            // Balance updater tự động chạy
        }
    }, 30000);
    
    // Refresh notifications every 15 seconds
    autoRefreshIntervals.notifications = setInterval(() => {
        if (typeof NotificationsEnhancer !== 'undefined' && NotificationsEnhancer.updateNotificationBadge) {
            NotificationsEnhancer.updateNotificationBadge();
        }
    }, 15000);
    
    // Refresh quest widget every 60 seconds
    autoRefreshIntervals.quests = setInterval(() => {
        if (typeof loadQuestWidget === 'function') {
            loadQuestWidget(questWidgetType || 'daily', false);
        }
    }, 60000);
    
    // Refresh activity feed every 30 seconds
    autoRefreshIntervals.activityFeed = setInterval(() => {
        if (typeof loadActivityFeed === 'function') {
            loadActivityFeed(false);
        }
    }, 30000);
    
    // Pause when tab is hidden
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            pauseAutoRefresh();
        } else {
            resumeAutoRefresh();
        }
    });
}

function pauseAutoRefresh() {
    Object.keys(autoRefreshIntervals).forEach(key => {
        clearInterval(autoRefreshIntervals[key]);
    });
}

function resumeAutoRefresh() {
    initAutoRefresh();
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', initAutoRefresh);

// Export
window.AutoRefresh = {
    initAutoRefresh,
    pauseAutoRefresh,
    resumeAutoRefresh
};

