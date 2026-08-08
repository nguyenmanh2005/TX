/**
 * Error Handler & Retry Logic
 * Xử lý lỗi và retry tự động
 */

// Retry configuration
const RETRY_CONFIG = {
    maxRetries: 3,
    retryDelay: 1000, // 1 second
    backoffMultiplier: 2
};

// Retry fetch with exponential backoff
async function fetchWithRetry(url, options = {}, retries = RETRY_CONFIG.maxRetries) {
    try {
        const response = await fetch(url, {
            ...options,
            credentials: 'same-origin'
        });
        
        if (!response.ok && retries > 0) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        return response;
    } catch (error) {
        if (retries > 0) {
            const delay = RETRY_CONFIG.retryDelay * 
                         Math.pow(RETRY_CONFIG.backoffMultiplier, 
                                 RETRY_CONFIG.maxRetries - retries);
            
            console.log(`Retrying ${url} in ${delay}ms... (${retries} retries left)`);
            
            await new Promise(resolve => setTimeout(resolve, delay));
            return fetchWithRetry(url, options, retries - 1);
        }
        
        throw error;
    }
}

// Error handler
function handleError(error, context = '') {
    console.error(`Error in ${context}:`, error);
    
    // Show user-friendly error message
    const errorMessage = getErrorMessage(error);
    showErrorToast(errorMessage);
    
    // Log to server (optional)
    logErrorToServer(error, context);
}

function getErrorMessage(error) {
    if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
        return '❌ Lỗi kết nối mạng! Vui lòng kiểm tra kết nối internet.';
    }
    
    if (error.message.includes('404')) {
        return '❌ Không tìm thấy tài nguyên!';
    }
    
    if (error.message.includes('500')) {
        return '❌ Lỗi server! Vui lòng thử lại sau.';
    }
    
    return '❌ Đã xảy ra lỗi! Vui lòng thử lại.';
}

function showErrorToast(message) {
    if (window.QuickActions && window.QuickActions.showToast) {
        window.QuickActions.showToast(message, 'error');
    } else {
        if (typeof Swal !== 'undefined') { Swal.fire('Thông báo', String(message), 'info'); } else { alert(message); };
    }
}

// Log error to server (optional)
function logErrorToServer(error, context) {
    // Only log in development or if explicitly enabled
    if (window.location.hostname === 'localhost' || window.DEBUG_MODE) {
        fetch('api_error_log.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                error: error.message,
                stack: error.stack,
                context: context,
                url: window.location.href,
                userAgent: navigator.userAgent,
                timestamp: new Date().toISOString()
            })
        }).catch(err => console.log('Failed to log error:', err));
    }
}

// Safe JSON parse
function safeJSONParse(jsonString, defaultValue = null) {
    try {
        return JSON.parse(jsonString);
    } catch (e) {
        console.error('JSON parse error:', e);
        return defaultValue;
    }
}

// Safe async function wrapper
function safeAsync(fn, errorHandler = handleError) {
    return async (...args) => {
        try {
            return await fn(...args);
        } catch (error) {
            errorHandler(error, fn.name);
            return null;
        }
    };
}

// Global error handler
window.addEventListener('error', (event) => {
    handleError(event.error, 'Global Error Handler');
});

// Unhandled promise rejection handler
window.addEventListener('unhandledrejection', (event) => {
    handleError(event.reason, 'Unhandled Promise Rejection');
    event.preventDefault();
});

// Network status monitoring
function initNetworkMonitor() {
    if ('ononline' in window) {
        window.addEventListener('online', () => {
            showToast('✅ Đã kết nối lại internet!', 'success');
            // Retry failed requests
            retryFailedRequests();
        });
        
        window.addEventListener('offline', () => {
            showToast('⚠️ Mất kết nối internet!', 'warning');
        });
    }
}

// Failed requests queue
const failedRequests = [];

function addFailedRequest(request) {
    failedRequests.push(request);
}

function retryFailedRequests() {
    if (failedRequests.length === 0) return;
    
    showToast(`🔄 Đang thử lại ${failedRequests.length} yêu cầu...`, 'info');
    
    const requests = [...failedRequests];
    failedRequests.length = 0;
    
    requests.forEach(request => {
        fetchWithRetry(request.url, request.options)
            .then(response => {
                if (request.resolve) request.resolve(response);
            })
            .catch(error => {
                if (request.reject) request.reject(error);
            });
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    initNetworkMonitor();
});

// Export functions
window.ErrorHandler = {
    fetchWithRetry,
    handleError,
    safeAsync,
    safeJSONParse,
    addFailedRequest,
    retryFailedRequests
};

