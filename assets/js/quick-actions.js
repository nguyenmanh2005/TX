/**
 * Quick Actions JavaScript
 * Các hành động nhanh cho trang chủ
 */

// Quick Actions Widget
function initQuickActions() {
    const quickActionsContainer = document.getElementById('quickActionsContainer');
    if (!quickActionsContainer) return;
    
    // Quick actions data - Mở rộng với nhiều actions hơn
    const quickActions = [
        {
            icon: '🎮',
            title: 'Chơi Game',
            description: 'Vào game ngay',
            url: '#games',
            color: 'var(--secondary-color)',
            shortcut: '1'
        },
        {
            icon: '🛒',
            title: 'Cửa Hàng',
            description: 'Mua themes & items',
            url: 'shop.php',
            color: 'var(--primary-color)',
            shortcut: '2'
        },
        {
            icon: '🏆',
            title: 'Xếp Hạng',
            description: 'Xem bảng xếp hạng',
            url: 'leaderboard.php',
            color: 'var(--warning-color)',
            shortcut: '3'
        },
        {
            icon: '🎯',
            title: 'Nhiệm Vụ',
            description: 'Hoàn thành quest',
            url: 'quests.php',
            color: 'var(--success-color)',
            shortcut: '4'
        },
        {
            icon: '💰',
            title: 'Nhận Quà',
            description: 'Giftcode & Daily',
            url: '#gifts',
            color: 'var(--info-color)',
            shortcut: '5'
        },
        {
            icon: '📊',
            title: 'Thống Kê',
            description: 'Xem thống kê',
            url: 'statistics.php',
            color: 'var(--secondary-dark)',
            shortcut: '6'
        },
        {
            icon: '🔔',
            title: 'Thông Báo',
            description: 'Xem thông báo',
            url: 'notifications.php',
            color: 'var(--danger-color)',
            shortcut: '7'
        },
        {
            icon: '👤',
            title: 'Hồ Sơ',
            description: 'Xem hồ sơ',
            url: 'profile.php',
            color: 'var(--primary-light)',
            shortcut: '8'
        }
    ];
    
    // Load recent actions from localStorage
    const recentActions = JSON.parse(localStorage.getItem('recentActions') || '[]');
    
    // Merge recent actions với quick actions (ưu tiên recent)
    const allActions = [...recentActions.slice(0, 2), ...quickActions].slice(0, 8);
    
    // Render quick actions
    quickActionsContainer.innerHTML = '';
    allActions.forEach((action, index) => {
        const actionCard = document.createElement('a');
        actionCard.href = action.url;
        actionCard.className = 'quick-action-card';
        actionCard.setAttribute('data-action', action.title);
        actionCard.style.animationDelay = (index * 0.1) + 's';
        
        const isRecent = recentActions.some(ra => ra.title === action.title);
        if (isRecent) {
            actionCard.classList.add('recent-action');
        }
        
        actionCard.innerHTML = `
            <div class="quick-action-icon" style="background: linear-gradient(135deg, ${action.color}, ${action.color}dd);">
                ${action.icon}
            </div>
            <div class="quick-action-content">
                <div class="quick-action-title">${action.title}${isRecent ? ' <span class="recent-badge">Mới</span>' : ''}</div>
                <div class="quick-action-desc">${action.description}</div>
            </div>
            <div class="quick-action-footer">
                ${action.shortcut ? `<span class="shortcut-key">${action.shortcut}</span>` : ''}
                <div class="quick-action-arrow">→</div>
            </div>
        `;
        
        // Track click để lưu vào recent
        actionCard.addEventListener('click', function() {
            saveRecentAction(action);
        });
        
        quickActionsContainer.appendChild(actionCard);
    });
}

// Save recent action
function saveRecentAction(action) {
    let recentActions = JSON.parse(localStorage.getItem('recentActions') || '[]');
    
    // Remove if exists
    recentActions = recentActions.filter(ra => ra.title !== action.title);
    
    // Add to beginning
    recentActions.unshift(action);
    
    // Keep only last 5
    recentActions = recentActions.slice(0, 5);
    
    localStorage.setItem('recentActions', JSON.stringify(recentActions));
}

// Keyboard Shortcuts
function initKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + K: Quick search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            openQuickSearch();
        }
        
        // Escape: Close modals/overlays
        if (e.key === 'Escape') {
            closeAllModals();
        }
        
        // Number keys: Quick actions (1-8)
        if (e.key >= '1' && e.key <= '8' && !e.ctrlKey && !e.metaKey && !e.altKey) {
            // Check if input/textarea is focused
            const activeElement = document.activeElement;
            if (activeElement && (activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA')) {
                return; // Don't trigger if typing in input
            }
            
            const quickActions = document.querySelectorAll('.quick-action-card');
            const index = parseInt(e.key) - 1;
            if (quickActions[index]) {
                quickActions[index].click();
            }
        }
    });
}

// Quick Search Modal
function openQuickSearch() {
    let searchModal = document.getElementById('quickSearchModal');
    
    if (!searchModal) {
        searchModal = document.createElement('div');
        searchModal.id = 'quickSearchModal';
        searchModal.className = 'modal-overlay';
        searchModal.innerHTML = `
            <div class="modal" style="max-width: 600px;">
                <div class="modal-header">
                    <h3 class="modal-title">🔍 Tìm Kiếm Nhanh</h3>
                    <button class="modal-close" onclick="closeQuickSearch()">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="search-box">
                        <input type="text" id="quickSearchInput" placeholder="Tìm kiếm game, trang, tính năng...">
                        <i class="fa-solid fa-search search-box-icon"></i>
                    </div>
                    <div id="quickSearchResults" class="quick-search-results"></div>
                </div>
            </div>
        `;
        document.body.appendChild(searchModal);
        
        // Focus input
        setTimeout(() => {
            document.getElementById('quickSearchInput').focus();
        }, 100);
        
        // Search functionality
        const searchInput = document.getElementById('quickSearchInput');
        searchInput.addEventListener('input', performQuickSearch);
        
        // Close on overlay click
        searchModal.addEventListener('click', function(e) {
            if (e.target === searchModal) {
                closeQuickSearch();
            }
        });
    }
    
    searchModal.style.display = 'flex';
    document.getElementById('quickSearchInput').value = '';
    document.getElementById('quickSearchInput').focus();
}

function closeQuickSearch() {
    const searchModal = document.getElementById('quickSearchModal');
    if (searchModal) {
        searchModal.style.display = 'none';
    }
}

function closeAllModals() {
    closeQuickSearch();
    const modals = document.querySelectorAll('.modal-overlay');
    modals.forEach(modal => {
        modal.style.display = 'none';
    });
}

// Quick Search Data - Mở rộng với nhiều items hơn
const searchData = [
    // Games
    { name: 'Bầu Cua', url: 'baucua.php', icon: '🎲', category: 'Game', keywords: ['baucua', 'bầu cua', 'game'] },
    { name: 'Blackjack', url: 'bj.php', icon: '🃏', category: 'Game', keywords: ['blackjack', 'bj', 'bài'] },
    { name: 'Slot Machine', url: 'slot.php', icon: '🎰', category: 'Game', keywords: ['slot', 'máy đánh bạc'] },
    { name: 'Roulette', url: 'roulette.php', icon: '🎡', category: 'Game', keywords: ['roulette', 'vòng quay'] },
    { name: 'Dice', url: 'dice.php', icon: '🎲', category: 'Game', keywords: ['dice', 'xí ngầu', 'xúc xắc'] },
    { name: 'Coin Flip', url: 'coinflip.php', icon: '🪙', category: 'Game', keywords: ['coin', 'flip', 'đồng xu'] },
    { name: 'RPS', url: 'rps.php', icon: '✌️', category: 'Game', keywords: ['rps', 'oẳn tù tì', 'kéo búa bao'] },
    { name: 'Xóc Đĩa', url: 'xocdia.php', icon: '🎲', category: 'Game', keywords: ['xóc đĩa', 'xocdia'] },
    { name: 'Poker', url: 'poker.php', icon: '🃏', category: 'Game', keywords: ['poker', 'bài tây'] },
    { name: 'Bingo', url: 'bingo.php', icon: '🎱', category: 'Game', keywords: ['bingo'] },
    // Pages
    { name: 'Cửa Hàng', url: 'shop.php', icon: '🛒', category: 'Trang', keywords: ['shop', 'cửa hàng', 'mua'] },
    { name: 'Bảng Xếp Hạng', url: 'leaderboard.php', icon: '🏆', category: 'Trang', keywords: ['leaderboard', 'xếp hạng', 'rank'] },
    { name: 'Nhiệm Vụ', url: 'quests.php', icon: '🎯', category: 'Trang', keywords: ['quest', 'nhiệm vụ', 'task'] },
    { name: 'Thống Kê', url: 'statistics.php', icon: '📊', category: 'Trang', keywords: ['statistics', 'thống kê', 'stats'] },
    { name: 'Hồ Sơ', url: 'profile.php', icon: '👤', category: 'Trang', keywords: ['profile', 'hồ sơ', 'profile'] },
    { name: 'Thông Báo', url: 'notifications.php', icon: '🔔', category: 'Trang', keywords: ['notifications', 'thông báo', 'notif'] },
    { name: 'Danh Hiệu', url: 'achievements.php', icon: '🎖️', category: 'Trang', keywords: ['achievements', 'danh hiệu', 'title'] },
    { name: 'Kho Đồ', url: 'inventory.php', icon: '📦', category: 'Trang', keywords: ['inventory', 'kho đồ', 'items'] },
    { name: 'Guild', url: 'guilds.php', icon: '🏆', category: 'Trang', keywords: ['guild', 'guilds', 'hội'] },
    { name: 'Tournament', url: 'tournament.php', icon: '🎯', category: 'Trang', keywords: ['tournament', 'giải đấu'] },
    { name: 'Friends', url: 'friends.php', icon: '👥', category: 'Trang', keywords: ['friends', 'bạn bè'] },
    { name: 'Chat', url: 'chat.php', icon: '💬', category: 'Trang', keywords: ['chat', 'trò chuyện'] }
];

// Search history
let searchHistory = JSON.parse(localStorage.getItem('searchHistory') || '[]');

function performQuickSearch() {
    const query = document.getElementById('quickSearchInput').value.toLowerCase().trim();
    const resultsContainer = document.getElementById('quickSearchResults');
    
    if (!query) {
        // Show search history
        if (searchHistory.length > 0) {
            resultsContainer.innerHTML = '<div style="margin-bottom: 15px; font-weight: 600; color: var(--text-light); font-size: 12px; text-transform: uppercase;">Lịch sử tìm kiếm</div>';
            searchHistory.slice(0, 5).forEach(item => {
                const historyItem = document.createElement('div');
                historyItem.className = 'quick-search-result-item';
                historyItem.style.cursor = 'pointer';
                historyItem.innerHTML = `
                    <div class="quick-search-result-icon">🔍</div>
                    <div class="quick-search-result-content">
                        <div class="quick-search-result-name">${item}</div>
                        <div class="quick-search-result-category">Nhấn để tìm lại</div>
                    </div>
                `;
                historyItem.onclick = () => {
                    document.getElementById('quickSearchInput').value = item;
                    performQuickSearch();
                };
                resultsContainer.appendChild(historyItem);
            });
        } else {
            resultsContainer.innerHTML = '<div class="empty-state"><div class="empty-state-icon">🔍</div><div class="empty-state-title">Nhập từ khóa để tìm kiếm</div><div class="empty-state-description">Hoặc nhấn Ctrl/Cmd + K để mở lại</div></div>';
        }
        return;
    }
    
    // Search with keywords
    const results = searchData.filter(item => {
        const nameMatch = item.name.toLowerCase().includes(query);
        const categoryMatch = item.category.toLowerCase().includes(query);
        const keywordMatch = item.keywords && item.keywords.some(kw => kw.toLowerCase().includes(query));
        return nameMatch || categoryMatch || keywordMatch;
    }).sort((a, b) => {
        // Sort by relevance
        const aNameMatch = a.name.toLowerCase().startsWith(query);
        const bNameMatch = b.name.toLowerCase().startsWith(query);
        if (aNameMatch && !bNameMatch) return -1;
        if (!aNameMatch && bNameMatch) return 1;
        return 0;
    });
    
    // Save to history
    if (query && !searchHistory.includes(query)) {
        searchHistory.unshift(query);
        searchHistory = searchHistory.slice(0, 10); // Keep last 10
        localStorage.setItem('searchHistory', JSON.stringify(searchHistory));
    }
    
    if (results.length === 0) {
        resultsContainer.innerHTML = '<div class="empty-state"><div class="empty-state-icon">😕</div><div class="empty-state-title">Không tìm thấy kết quả</div><div class="empty-state-description">Thử với từ khóa khác</div></div>';
        return;
    }
    
    resultsContainer.innerHTML = '';
    results.forEach(result => {
        const resultItem = document.createElement('a');
        resultItem.href = result.url;
        resultItem.className = 'quick-search-result-item';
        resultItem.innerHTML = `
            <div class="quick-search-result-icon">${result.icon}</div>
            <div class="quick-search-result-content">
                <div class="quick-search-result-name">${highlightMatch(result.name, query)}</div>
                <div class="quick-search-result-category">${result.category}</div>
            </div>
            <div class="quick-search-result-arrow">→</div>
        `;
        resultsContainer.appendChild(resultItem);
    });
}

function highlightMatch(text, query) {
    const regex = new RegExp(`(${query})`, 'gi');
    return text.replace(regex, '<mark>$1</mark>');
}

// Copy to Clipboard Helper
function copyToClipboard(text, showNotification = true) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            if (showNotification) {
                showToast('✅ Đã sao chép vào clipboard!', 'success');
            }
        }).catch(err => {
            console.error('Copy failed:', err);
            fallbackCopyToClipboard(text, showNotification);
        });
    } else {
        fallbackCopyToClipboard(text, showNotification);
    }
}

function fallbackCopyToClipboard(text, showNotification) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        if (showNotification) {
            showToast('✅ Đã sao chép vào clipboard!', 'success');
        }
    } catch (err) {
        console.error('Fallback copy failed:', err);
        if (showNotification) {
            showToast('❌ Không thể sao chép!', 'error');
        }
    }
    
    document.body.removeChild(textArea);
}

// Toast Notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    initQuickActions();
    initKeyboardShortcuts();
    
    // Add copy buttons to code elements
    document.querySelectorAll('code, .copyable').forEach(el => {
        if (!el.querySelector('.copy-btn')) {
            const copyBtn = document.createElement('button');
            copyBtn.className = 'copy-btn';
            copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i>';
            copyBtn.title = 'Sao chép';
            copyBtn.onclick = (e) => {
                e.stopPropagation();
                copyToClipboard(el.textContent);
            };
            el.style.position = 'relative';
            el.appendChild(copyBtn);
        }
    });
});

// Export functions
window.QuickActions = {
    initQuickActions,
    openQuickSearch,
    closeQuickSearch,
    copyToClipboard,
    showToast
};

