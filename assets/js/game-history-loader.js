/**
 * Universal Game History Loader
 * Include this in all game files: <script src="../game-history-loader.js"></script>
 */

class GameHistoryLoader {
    constructor(gameType, historySelector = '.history-box table', limit = 20) {
        this.gameType = gameType;
        this.historySelector = historySelector;
        this.limit = limit;
        this.isLoading = false;
    }
    
    async loadHistory() {
        if (this.isLoading) return;
        this.isLoading = true;
        
        try {
            const url = new URL('../game_history_universal.php', window.location.origin);
            url.searchParams.append('action', 'get_history');
            url.searchParams.append('game', this.gameType);
            url.searchParams.append('limit', this.limit);
            
            const response = await fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) {
                console.warn('History load failed:', response.status);
                this.isLoading = false;
                return false;
            }
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                this.updateHistoryDisplay(data.history);
                this.isLoading = false;
                return true;
            }
            
            this.isLoading = false;
            return false;
        } catch (error) {
            console.error('History load error:', error);
            this.isLoading = false;
            return false;
        }
    }
    
    updateHistoryDisplay(history) {
        const historyBox = document.querySelector(this.historySelector);
        
        if (!historyBox) {
            console.warn('History box not found:', this.historySelector);
            return;
        }
        
        const tbody = historyBox.querySelector('tbody') || historyBox;
        const isEmpty = historyBox.querySelector('tr').length <= 1;
        
        if (isEmpty) {
            // Replace "no data" message
            const emptyMsg = historyBox.parentElement.querySelector('p');
            if (emptyMsg) {
                emptyMsg.style.display = 'none';
            }
        }
        
        // Add latest records to top
        history.slice(0, 5).reverse().forEach(record => {
            const newRow = document.createElement('tr');
            newRow.style.animation = 'slideIn 0.3s ease';
            
            // Generic columns - can be customized per game
            const columns = this.getHistoryColumns(record);
            newRow.innerHTML = columns;
            
            if (tbody.querySelector('tbody')) {
                tbody.insertBefore(newRow, tbody.querySelector('tbody').firstChild);
            } else {
                historyBox.appendChild(newRow);
            }
        });
        
        // Limit displayed rows to avoid cluttering
        const rows = historyBox.querySelectorAll('tr');
        if (rows.length > 21) { // 20 data rows + 1 header
            for (let i = 21; i < rows.length; i++) {
                rows[i].remove();
            }
        }
    }
    
    getHistoryColumns(record) {
        // Override in specific game implementations
        const bet = parseInt(record.Bet || 0).toLocaleString('vi-VN');
        const winAmount = parseInt(record.WinAmount || 0).toLocaleString('vi-VN');
        
        return `
            <td>${record.Id || ''}</td>
            <td>${bet}</td>
            <td>${record.Result || ''}</td>
            <td>${winAmount}</td>
            <td>${record.Time || ''}</td>
        `;
    }
    
    // Auto-load on page load
    autoLoad() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.loadHistory());
        } else {
            this.loadHistory();
        }
    }
}

// Add styles for animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
`;
document.head.appendChild(style);
