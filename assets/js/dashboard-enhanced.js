/**
 * Dashboard Enhanced - Advanced Widgets & Real-time Updates
 */

class DashboardEnhanced {
    constructor() {
        this.widgets = new Map();
        this.updateInterval = null;
        this.init();
    }

    init() {
        this.setupWidgets();
        this.setupQuickActions();
        this.setupSearch();
        this.setupRealTimeUpdates();
        this.setupDragAndDrop();
    }

    setupWidgets() {
        // Game Statistics Widget
        this.createStatsWidget();
        
        // Recent Activity Widget
        this.createActivityWidget();
        
        // Quick Game Access Widget
        this.createQuickGamesWidget();
        
        // Balance & Rewards Widget
        this.createBalanceWidget();
    }

    async createStatsWidget() {
        const container = document.getElementById('dashboard-widgets');
        if (!container) return;

        const widget = document.createElement('div');
        widget.className = 'dashboard-widget stats-widget';
        widget.innerHTML = `
            <div class="widget-header">
                <h3>📊 Thống Kê Games</h3>
                <button class="widget-refresh" onclick="dashboardEnhanced.refreshWidget('stats')">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div class="widget-content" id="stats-content">
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-icon">🎮</div>
                        <div class="stat-value" id="totalGamesStat">-</div>
                        <div class="stat-label">Tổng Games</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">🏆</div>
                        <div class="stat-value" id="winRateStat">-</div>
                        <div class="stat-label">Tỷ Lệ Thắng</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">💰</div>
                        <div class="stat-value" id="biggestWinStat">-</div>
                        <div class="stat-label">Thắng Lớn Nhất</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">⭐</div>
                        <div class="stat-value" id="favoriteGameStat">-</div>
                        <div class="stat-label">Game Yêu Thích</div>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(widget);
        this.widgets.set('stats', widget);
        this.refreshWidget('stats');
    }

    async createActivityWidget() {
        const container = document.getElementById('dashboard-widgets');
        if (!container) return;

        const widget = document.createElement('div');
        widget.className = 'dashboard-widget activity-widget';
        widget.innerHTML = `
            <div class="widget-header">
                <h3>📝 Hoạt Động Gần Đây</h3>
            </div>
            <div class="widget-content" id="activity-content">
                <div class="activity-list">
                    <div class="activity-item loading">
                        <div class="activity-icon">⏳</div>
                        <div class="activity-text">Đang tải...</div>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(widget);
        this.widgets.set('activity', widget);
        this.loadRecentActivity();
    }

    createQuickGamesWidget() {
        const container = document.getElementById('dashboard-widgets');
        if (!container) return;

        const widget = document.createElement('div');
        widget.className = 'dashboard-widget quick-games-widget';
        widget.innerHTML = `
            <div class="widget-header">
                <h3>🎮 Chơi Nhanh</h3>
            </div>
            <div class="widget-content">
                <div class="quick-games-grid" id="quick-games-grid">
                    <!-- Will be populated dynamically -->
                </div>
            </div>
        `;
        container.appendChild(widget);
        this.widgets.set('quickGames', widget);
        this.loadQuickGames();
    }

    createBalanceWidget() {
        const container = document.getElementById('dashboard-widgets');
        if (!container) return;

        const balanceEl = document.querySelector('.balance-display');
        if (!balanceEl) return;

        // Enhance existing balance display
        balanceEl.classList.add('enhanced-balance');
        this.updateBalance();
    }

    async refreshWidget(widgetName) {
        if (widgetName === 'stats') {
            try {
                const response = await fetch('api_game_statistics.php?action=get_stats');
                const data = await response.json();
                
                if (data.status === 'success') {
                    const stats = data.stats;
                    document.getElementById('totalGamesStat').textContent = stats.totalGames.toLocaleString('vi-VN');
                    document.getElementById('winRateStat').textContent = stats.winRate.toFixed(1) + '%';
                    document.getElementById('biggestWinStat').textContent = stats.biggestWin.toLocaleString('vi-VN') + ' VNĐ';
                    document.getElementById('favoriteGameStat').textContent = stats.favoriteGame || 'Chưa có';
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }
    }

    async loadRecentActivity() {
        try {
            const response = await fetch('api_dashboard_widgets.php?action=recent_activity');
            const data = await response.json();
            
            const container = document.getElementById('activity-content');
            if (!container) return;

            if (data.status === 'success' && data.activities) {
                const list = container.querySelector('.activity-list');
                list.innerHTML = '';
                
                data.activities.forEach(activity => {
                    const item = document.createElement('div');
                    item.className = 'activity-item';
                    item.innerHTML = `
                        <div class="activity-icon">${activity.icon || '🎮'}</div>
                        <div class="activity-text">
                            <div class="activity-title">${activity.title}</div>
                            <div class="activity-time">${activity.time}</div>
                        </div>
                    `;
                    list.appendChild(item);
                });
            }
        } catch (error) {
            console.error('Error loading activity:', error);
        }
    }

    async loadQuickGames() {
        const games = [
            { name: 'Tài Xỉu', url: 'baucua.php', icon: '🎲' },
            { name: 'Roulette', url: 'roulette.php', icon: '🎰' },
            { name: 'Slot', url: 'slot.php', icon: '🎰' },
            { name: 'Poker', url: 'poker.php', icon: '🃏' },
            { name: 'Dice', url: 'dice.php', icon: '⚀' },
            { name: 'Plinko', url: 'plinko.php', icon: '⚪' }
        ];

        const grid = document.getElementById('quick-games-grid');
        if (!grid) return;

        games.forEach(game => {
            const item = document.createElement('a');
            item.href = game.url;
            item.className = 'quick-game-item';
            item.innerHTML = `
                <div class="quick-game-icon">${game.icon}</div>
                <div class="quick-game-name">${game.name}</div>
            `;
            grid.appendChild(item);
        });
    }

    setupQuickActions() {
        const quickActions = document.createElement('div');
        quickActions.className = 'quick-actions-bar';
        quickActions.innerHTML = `
            <button class="quick-action-btn" onclick="window.location.href='shop.php'">
                <i class="fas fa-shopping-cart"></i>
                <span>Shop</span>
            </button>
            <button class="quick-action-btn" onclick="window.location.href='quests.php'">
                <i class="fas fa-tasks"></i>
                <span>Nhiệm Vụ</span>
            </button>
            <button class="quick-action-btn" onclick="window.location.href='leaderboard.php'">
                <i class="fas fa-trophy"></i>
                <span>Bảng Xếp Hạng</span>
            </button>
            <button class="quick-action-btn" onclick="window.location.href='friends.php'">
                <i class="fas fa-users"></i>
                <span>Bạn Bè</span>
            </button>
        `;
        document.body.insertBefore(quickActions, document.body.firstChild);
    }

    setupSearch() {
        const searchBar = document.createElement('div');
        searchBar.className = 'dashboard-search';
        searchBar.innerHTML = `
            <input type="text" id="dashboard-search-input" placeholder="🔍 Tìm kiếm games, tính năng...">
            <div class="search-results" id="search-results"></div>
        `;
        
        const header = document.querySelector('.header') || document.querySelector('header');
        if (header) {
            header.appendChild(searchBar);
        }

        const input = document.getElementById('dashboard-search-input');
        if (input) {
            input.addEventListener('input', (e) => {
                this.handleSearch(e.target.value);
            });
        }
    }

    handleSearch(query) {
        if (query.length < 2) {
            document.getElementById('search-results').innerHTML = '';
            return;
        }

        const games = [
            { name: 'Tài Xỉu', url: 'baucua.php' },
            { name: 'Roulette', url: 'roulette.php' },
            { name: 'Slot Machine', url: 'slot.php' },
            { name: 'Poker', url: 'poker.php' },
            { name: 'Dice', url: 'dice.php' },
            { name: 'Plinko', url: 'plinko.php' },
            { name: 'Mines', url: 'mines.php' },
            { name: 'Wheel', url: 'wheel.php' }
        ];

        const results = games.filter(game => 
            game.name.toLowerCase().includes(query.toLowerCase())
        );

        const resultsEl = document.getElementById('search-results');
        resultsEl.innerHTML = '';
        
        if (results.length > 0) {
            results.forEach(game => {
                const item = document.createElement('a');
                item.href = game.url;
                item.className = 'search-result-item';
                item.textContent = game.name;
                resultsEl.appendChild(item);
            });
            resultsEl.style.display = 'block';
        } else {
            resultsEl.style.display = 'none';
        }
    }

    setupRealTimeUpdates() {
        // Update balance every 30 seconds
        setInterval(() => {
            this.updateBalance();
        }, 30000);

        // Update widgets every 60 seconds
        setInterval(() => {
            this.refreshWidget('stats');
            this.loadRecentActivity();
        }, 60000);
    }

    async updateBalance() {
        try {
            const response = await fetch('api_profile.php?action=get_balance');
            const data = await response.json();
            
            if (data.status === 'success') {
                const balanceEl = document.querySelector('.balance-display .balance-amount');
                if (balanceEl) {
                    balanceEl.textContent = parseFloat(data.balance).toLocaleString('vi-VN') + ' VNĐ';
                }
            }
        } catch (error) {
            console.error('Error updating balance:', error);
        }
    }

    setupDragAndDrop() {
        // Make widgets draggable
        this.widgets.forEach((widget, name) => {
            const header = widget.querySelector('.widget-header');
            if (header) {
                header.style.cursor = 'move';
                header.addEventListener('mousedown', (e) => {
                    this.startDrag(widget, e);
                });
            }
        });
    }

    startDrag(widget, e) {
        // Drag and drop implementation
        // This is a simplified version
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    window.dashboardEnhanced = new DashboardEnhanced();
});

