/**
 * Game Statistics - Widget để hiển thị thống kê games
 */

class GameStatistics {
    constructor() {
        this.stats = {
            totalGames: 0,
            totalWins: 0,
            totalLosses: 0,
            totalWagered: 0,
            totalWon: 0,
            favoriteGame: null,
            winRate: 0,
            biggestWin: 0
        };
        this.init();
    }

    init() {
        this.loadStatistics();
        this.setupAutoRefresh();
    }

    async loadStatistics() {
        try {
            const response = await fetch('api_game_statistics.php?action=get_stats');
            const data = await response.json();
            
            if (data.status === 'success') {
                this.stats = data.stats;
                this.render();
            }
        } catch (error) {
            console.error('Error loading statistics:', error);
        }
    }

    render() {
        this.renderTotalGames();
        this.renderWinRate();
        this.renderBiggestWin();
        this.renderFavoriteGame();
    }

    renderTotalGames() {
        const element = document.getElementById('totalGamesStat');
        if (element) {
            element.textContent = this.stats.totalGames.toLocaleString('vi-VN');
        }
    }

    renderWinRate() {
        const element = document.getElementById('winRateStat');
        if (element) {
            const rate = this.stats.totalGames > 0 
                ? ((this.stats.totalWins / this.stats.totalGames) * 100).toFixed(1)
                : 0;
            element.textContent = rate + '%';
        }
    }

    renderBiggestWin() {
        const element = document.getElementById('biggestWinStat');
        if (element) {
            element.textContent = this.stats.biggestWin.toLocaleString('vi-VN') + ' VNĐ';
        }
    }

    renderFavoriteGame() {
        const element = document.getElementById('favoriteGameStat');
        if (element && this.stats.favoriteGame) {
            element.textContent = this.stats.favoriteGame;
        }
    }

    setupAutoRefresh() {
        // Refresh mỗi 60 giây
        setInterval(() => {
            this.loadStatistics();
        }, 60000);
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('totalGamesStat')) {
        window.gameStatistics = new GameStatistics();
    }
});

