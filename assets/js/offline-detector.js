/**
 * Offline Detection & Handling
 * Phát hiện và xử lý khi mất kết nối
 */

let isOnline = navigator.onLine;
let offlineNotificationShown = false;

function initOfflineDetector() {
    // Listen for online/offline events
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    
    // Check initial status
    if (!isOnline) {
        handleOffline();
    }
    
    // Periodic connectivity check
    setInterval(checkConnectivity, 30000); // Check every 30 seconds
}

function handleOnline() {
    isOnline = true;
    offlineNotificationShown = false;
    
    // Hide offline notification
    const offlineBanner = document.getElementById('offlineBanner');
    if (offlineBanner) {
        offlineBanner.remove();
    }
    
    // Show online notification
    if (typeof QuickActions !== 'undefined' && QuickActions.showToast) {
        QuickActions.showToast('✅ Đã kết nối lại internet!', 'success');
    }
    
    // Retry pending requests
    retryPendingRequests();
}

function handleOffline() {
    isOnline = false;
    
    // Show offline banner
    if (!offlineNotificationShown) {
        showOfflineBanner();
        offlineNotificationShown = true;
    }
    
    if (typeof QuickActions !== 'undefined' && QuickActions.showToast) {
        QuickActions.showToast('⚠️ Mất kết nối internet!', 'warning');
    }
}

function showOfflineBanner() {
    // Remove existing banner
    const existing = document.getElementById('offlineBanner');
    if (existing) return;
    
    const banner = document.createElement('div');
    banner.id = 'offlineBanner';
    banner.className = 'offline-banner';
    banner.innerHTML = `
        <div class="offline-banner-content">
            <i class="fa-solid fa-wifi-slash"></i>
            <span>Không có kết nối internet. Đang thử kết nối lại...</span>
        </div>
    `;
    document.body.appendChild(banner);
}

function checkConnectivity() {
    // Simple connectivity check
    fetch('api_profile.php?action=ping', {
        method: 'HEAD',
        cache: 'no-cache',
        timeout: 5000
    })
        .then(() => {
            if (!isOnline) {
                handleOnline();
            }
        })
        .catch(() => {
            if (isOnline) {
                handleOffline();
            }
        });
}

function retryPendingRequests() {
    // Retry any pending requests stored in queue
    // This would be implemented based on your request queue system
    console.log('Retrying pending requests...');
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', initOfflineDetector);

// Export
window.OfflineDetector = {
    isOnline: () => isOnline,
    checkConnectivity
};

