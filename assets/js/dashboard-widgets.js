/**
 * Dashboard Widgets JavaScript
 * Các widget tương tác cho trang chủ
 */

// Real-time Clock Widget
function initLiveClock() {
    const timeEl = document.getElementById('liveTime');
    const dateEl = document.getElementById('liveDate');
    
    if (!timeEl || !dateEl) return;
    
    function updateClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        
        timeEl.textContent = `${hours}:${minutes}:${seconds}`;
        
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };
        dateEl.textContent = now.toLocaleDateString('vi-VN', options);
    }
    
    updateClock();
    setInterval(updateClock, 1000);
}

// Animated Statistics Counter
function initAnimatedStats() {
    const statCards = document.querySelectorAll('.stat-value[data-target]');
    
    statCards.forEach(card => {
        const target = parseInt(card.getAttribute('data-target')) || 0;
        animateValue(card, 0, target, 1500);
    });
}

function animateValue(element, start, end, duration) {
    const startTime = performance.now();
    const range = end - start;
    
    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Easing function (ease-out)
        const easeProgress = 1 - Math.pow(1 - progress, 3);
        const current = Math.floor(start + (range * easeProgress));
        
        element.textContent = current.toLocaleString('vi-VN');
        
        if (progress < 1) {
            requestAnimationFrame(update);
        } else {
            element.textContent = end.toLocaleString('vi-VN');
        }
    }
    
    requestAnimationFrame(update);
}

// Quick Actions Widget
function initQuickActions() {
    const quickActionButtons = document.querySelectorAll('.quick-action-btn');
    
    quickActionButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const action = this.getAttribute('data-action');
            const url = this.getAttribute('href');
            
            // Add loading state
            this.classList.add('btn-loading');
            this.disabled = true;
            
            // Navigate after short delay for visual feedback
            setTimeout(() => {
                window.location.href = url;
            }, 300);
        });
    });
}

// Notification Badge Updater
function updateNotificationBadge() {
    fetch('api_get_notifications.php?action=get_unread_count', {
        credentials: 'same-origin'
    })
        .then(response => response.json())
        .then(data => {
            const badge = document.getElementById('notificationsBadge');
            if (badge && data.count > 0) {
                badge.textContent = data.count;
                badge.style.display = 'inline-block';
            } else if (badge) {
                badge.style.display = 'none';
            }
        })
        .catch(err => console.log('Notification badge update error:', err));
}

// Balance Updater (Real-time) - Cải thiện với debouncing và caching
let balanceUpdateTimer = null;
let lastBalanceUpdate = 0;
const BALANCE_UPDATE_INTERVAL = 30000; // 30 seconds
const BALANCE_CACHE_DURATION = 10000; // 10 seconds cache

function initBalanceUpdater() {
    const balanceEl = document.querySelector('.balance-value');
    if (!balanceEl) return;
    
    // Debounced update function
    function updateBalance() {
        const now = Date.now();
        
        // Check cache
        if (now - lastBalanceUpdate < BALANCE_CACHE_DURATION) {
            return;
        }
        
        // Clear previous timer
        if (balanceUpdateTimer) {
            clearTimeout(balanceUpdateTimer);
        }
        
        balanceUpdateTimer = setTimeout(() => {
            fetch('api_profile.php?action=get_balance', {
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
                    if (data.success && balanceEl) {
                        const newBalance = parseFloat(data.balance) || 0;
                        const oldBalance = parseFloat(balanceEl.getAttribute('data-balance')) || 0;
                        
                        if (newBalance !== oldBalance) {
                            // Animate balance change
                            animateBalanceChange(balanceEl, oldBalance, newBalance);
                            balanceEl.setAttribute('data-balance', newBalance);
                            lastBalanceUpdate = Date.now();
                        }
                    }
                })
                .catch(err => {
                    console.log('Balance update error:', err);
                    // Retry after 5 seconds on error
                    setTimeout(updateBalance, 5000);
                });
        }, 500); // Debounce 500ms
    }
    
    // Update on visibility change
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            updateBalance();
        }
    });
    
    // Update on focus
    window.addEventListener('focus', updateBalance);
    
    // Initial update
    updateBalance();
    
    // Periodic update
    setInterval(updateBalance, BALANCE_UPDATE_INTERVAL);
}

function animateBalanceChange(element, from, to) {
    const duration = 1000;
    const startTime = performance.now();
    const range = to - from;
    
    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easeProgress = 1 - Math.pow(1 - progress, 2);
        const current = from + (range * easeProgress);
        
        element.textContent = Math.floor(current).toLocaleString('vi-VN');
        
        if (progress < 1) {
            requestAnimationFrame(update);
        } else {
            element.textContent = Math.floor(to).toLocaleString('vi-VN');
        }
    }
    
    requestAnimationFrame(update);
}

// Tooltip Initializer
function initTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', function() {
            // Tooltip được xử lý bởi CSS
        });
    });
}

// Smooth Scroll to Top
function initScrollToTop() {
    const scrollBtn = document.querySelector('.fab[onclick*="scrollTo"]');
    if (!scrollBtn) return;
    
    let isVisible = false;
    
    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 300 && !isVisible) {
            scrollBtn.style.opacity = '1';
            scrollBtn.style.pointerEvents = 'auto';
            isVisible = true;
        } else if (window.pageYOffset <= 300 && isVisible) {
            scrollBtn.style.opacity = '0';
            scrollBtn.style.pointerEvents = 'none';
            isVisible = false;
        }
    });
}

// Initialize all widgets when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initLiveClock();
    initAnimatedStats();
    initQuickActions();
    updateNotificationBadge();
    initBalanceUpdater();
    initTooltips();
    initScrollToTop();
    initRealTimeDashboard();
    
    // Update notification badge every 30 seconds
    setInterval(updateNotificationBadge, 30000);
});

// Real-time Dashboard Widgets Updater
let dashboardUpdateInterval = null;
let lastDashboardUpdate = 0;
const DASHBOARD_UPDATE_INTERVAL = 10000; // 10 seconds

function initRealTimeDashboard() {
    const dashboardContainer = document.getElementById('real-time-dashboard');
    if (!dashboardContainer) return;
    
    function updateDashboard() {
        const now = Date.now();
        
        // Throttle updates
        if (now - lastDashboardUpdate < 5000) {
            return;
        }
        
        fetch('api_dashboard_widgets.php?action=get_all', {
            credentials: 'same-origin',
            cache: 'no-cache'
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateDashboardWidgets(data.data);
                    lastDashboardUpdate = now;
                }
            })
            .catch(err => console.log('Dashboard update error:', err));
    }
    
    // Initial update
    updateDashboard();
    
    // Periodic updates
    if (dashboardUpdateInterval) {
        clearInterval(dashboardUpdateInterval);
    }
    dashboardUpdateInterval = setInterval(updateDashboard, DASHBOARD_UPDATE_INTERVAL);
    
    // Update on visibility change
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            updateDashboard();
        }
    });
}

function updateDashboardWidgets(data) {
    // Update overview stats
    if (data.overview) {
        updateOverviewWidget(data.overview);
    }
    
    // Update recent activity
    if (data.recent_activity) {
        updateActivityWidget(data.recent_activity);
    }
    
    // Update game stats
    if (data.game_stats) {
        updateGameStatsWidget(data.game_stats);
    }
    
    // Update notifications
    if (data.notifications) {
        updateNotificationsWidget(data.notifications);
    }
}

function updateOverviewWidget(stats) {
    // Update balance
    const balanceEl = document.getElementById('dashboard-balance');
    if (balanceEl && stats.balance !== undefined) {
        const currentBalance = parseFloat(balanceEl.getAttribute('data-balance')) || 0;
        if (Math.abs(stats.balance - currentBalance) > 0.01) {
            animateBalanceChange(balanceEl, currentBalance, stats.balance);
            balanceEl.setAttribute('data-balance', stats.balance);
        }
    }
    
    // Update today stats
    if (stats.today) {
        updateElement('dashboard-games-today', stats.today.games_played);
        updateElement('dashboard-wins-today', stats.today.games_won);
        updateElement('dashboard-profit-today', stats.today.net_profit);
    }
    
    // Update streak
    if (stats.streak) {
        updateElement('dashboard-streak', stats.streak.current);
    }
    
    // Update rank
    if (stats.rank) {
        updateElement('dashboard-rank', stats.rank);
    }
}

function updateActivityWidget(activities) {
    const container = document.getElementById('dashboard-activity');
    if (!container) return;
    
    if (activities.length === 0) {
        container.innerHTML = '<div class="empty-activity">Chưa có hoạt động gần đây</div>';
        return;
    }
    
    let html = '';
    activities.forEach(activity => {
        const timeAgo = getTimeAgo(activity.time);
        html += `
            <div class="activity-item">
                <span class="activity-icon">${activity.icon}</span>
                <div class="activity-content">
                    <div class="activity-message">${activity.message}</div>
                    <div class="activity-time">${timeAgo}</div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function updateGameStatsWidget(stats) {
    const container = document.getElementById('dashboard-game-stats');
    if (!container) return;
    
    if (stats.length === 0) {
        container.innerHTML = '<div class="empty-stats">Chưa có thống kê game</div>';
        return;
    }
    
    let html = '<div class="game-stats-list">';
    stats.forEach(game => {
        html += `
            <div class="game-stat-item">
                <div class="game-name">${game.game_name}</div>
                <div class="game-meta">
                    <span>${game.plays} lượt</span>
                    <span>${game.win_rate}% thắng</span>
                    <span class="${game.net_profit >= 0 ? 'profit-positive' : 'profit-negative'}">
                        ${game.net_profit >= 0 ? '+' : ''}${formatNumber(game.net_profit)} VNĐ
                    </span>
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

function updateNotificationsWidget(notifications) {
    const badge = document.getElementById('notificationsBadge');
    if (badge) {
        if (notifications.length > 0) {
            badge.textContent = notifications.length;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    }
}

function updateElement(id, value) {
    const el = document.getElementById(id);
    if (el) {
        const currentValue = parseFloat(el.getAttribute('data-value')) || 0;
        if (Math.abs(value - currentValue) > 0.01) {
            animateValue(el, currentValue, value, 1000);
            el.setAttribute('data-value', value);
        }
    }
}

function getTimeAgo(timestamp) {
    const now = new Date();
    const time = new Date(timestamp);
    const diff = Math.floor((now - time) / 1000);
    
    if (diff < 60) return 'Vừa xong';
    if (diff < 3600) return Math.floor(diff / 60) + ' phút trước';
    if (diff < 86400) return Math.floor(diff / 3600) + ' giờ trước';
    return Math.floor(diff / 86400) + ' ngày trước';
}

function formatNumber(num) {
    return new Intl.NumberFormat('vi-VN').format(Math.floor(num));
}

// Export functions for use in other scripts
window.DashboardWidgets = {
    initLiveClock,
    initAnimatedStats,
    initQuickActions,
    updateNotificationBadge,
    initBalanceUpdater,
    animateValue,
    animateBalanceChange,
    initRealTimeDashboard,
    updateDashboardWidgets
};

