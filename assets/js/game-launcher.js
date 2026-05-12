/**
 * Game Launcher - Component để quản lý và truy cập nhanh các games
 */

class GameLauncher {
    constructor() {
        this.games = [];
        this.recentGames = [];
        this.favoriteGames = [];
        this.init();
    }

    init() {
        this.loadGames();
        this.loadRecentGames();
        this.loadFavoriteGames();
        this.setupEventListeners();
    }

    loadGames() {
        // Danh sách tất cả games
        this.games = [
            { id: 'roulette', name: 'Roulette', icon: '🎡', url: 'roulette.php', category: 'casino', new: false },
            { id: 'slot', name: 'Slot Machine', icon: '🎰', url: 'slot.php', category: 'casino', new: false },
            { id: 'lucky_wheel', name: 'Lucky Wheel', icon: '🎡', url: 'lucky_wheel.php', category: 'casino', new: false },
            { id: 'baucua', name: 'CYBER PETS', icon: '🎲', url: 'baucua.php', category: 'casino', new: false },
            { id: 'plinko', name: 'Plinko', icon: '⚪', url: 'plinko.php', category: 'mini', new: true },
            { id: 'mines', name: 'Mines', icon: '💣', url: 'mines.php', category: 'mini', new: true },
            { id: 'wheel', name: 'Wheel', icon: '🎡', url: 'wheel.php', category: 'mini', new: true },
            { id: 'crash', name: 'Crash', icon: '🚀', url: 'crash.php', category: 'mini', new: true },
            { id: 'tower', name: 'Tower', icon: '🏗️', url: 'tower.php', category: 'mini', new: true },
            { id: 'limbo', name: 'Limbo', icon: '🚀', url: 'limbo.php', category: 'mini', new: true },
            { id: 'keno', name: 'Keno', icon: '🎯', url: 'keno.php', category: 'casino', new: true },
            { id: 'dice_roll', name: 'Dice Roll', icon: '🎲', url: 'dice_roll.php', category: 'casino', new: true },
            { id: 'baccarat', name: 'Baccarat', icon: '🃏', url: 'baccarat.php', category: 'card', new: true },
            { id: 'hilo', name: 'Hi-Lo', icon: '📈', url: 'hilo.php', category: 'casino', new: true },
            { id: 'aviator', name: 'Aviator', icon: '✈️', url: 'aviator.php', category: 'mini', new: true },
            { id: 'dragon_tiger', name: 'Dragon Tiger', icon: '🐉🐅', url: 'dragon_tiger.php', category: 'casino', new: true },
            { id: 'coinflip', name: 'Coin Flip', icon: '🪙', url: 'coinflip.php', category: 'casino', new: false },
            { id: 'dice', name: 'Dice', icon: '🎲', url: 'dice.php', category: 'casino', new: false },
            { id: 'poker', name: 'Poker', icon: '🃏', url: 'poker.php', category: 'card', new: false },
            { id: 'blackjack', name: 'Blackjack', icon: '🃏', url: 'bj.php', category: 'card', new: false },
            { id: 'bingo', name: 'Bingo', icon: '🎯', url: 'bingo.php', category: 'casino', new: false },
            { id: 'banharc', name: 'Bắn Cá Arcade', icon: '🐟', url: 'games/banharc.php', category: 'arcade', new: true },
        ];
    }

    loadRecentGames() {
        const stored = localStorage.getItem('recentGames');
        if (stored) {
            this.recentGames = JSON.parse(stored);
        }
    }

    loadFavoriteGames() {
        const stored = localStorage.getItem('favoriteGames');
        if (stored) {
            this.favoriteGames = JSON.parse(stored);
        }
    }

    saveRecentGames() {
        localStorage.setItem('recentGames', JSON.stringify(this.recentGames));
    }

    saveFavoriteGames() {
        localStorage.setItem('favoriteGames', JSON.stringify(this.favoriteGames));
    }

    addToRecent(gameId) {
        // Remove nếu đã có
        this.recentGames = this.recentGames.filter(id => id !== gameId);
        // Thêm vào đầu
        this.recentGames.unshift(gameId);
        // Giới hạn 10 games
        this.recentGames = this.recentGames.slice(0, 10);
        this.saveRecentGames();
    }

    toggleFavorite(gameId) {
        const index = this.favoriteGames.indexOf(gameId);
        if (index > -1) {
            this.favoriteGames.splice(index, 1);
        } else {
            this.favoriteGames.push(gameId);
        }
        this.saveFavoriteGames();
        this.render();
    }

    isFavorite(gameId) {
        return this.favoriteGames.includes(gameId);
    }

    setupEventListeners() {
        // Search games
        const searchInput = document.getElementById('gameSearch');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.filterGames(e.target.value);
            });
        }

        // Filter by category
        const categoryFilters = document.querySelectorAll('.game-category-filter');
        categoryFilters.forEach(filter => {
            filter.addEventListener('click', (e) => {
                const category = e.target.dataset.category;
                this.filterByCategory(category);
            });
        });
    }

    filterGames(searchTerm) {
        const term = searchTerm.toLowerCase();
        const filtered = this.games.filter(game =>
            game.name.toLowerCase().includes(term) ||
            game.category.toLowerCase().includes(term)
        );
        this.renderGames(filtered);
    }

    filterByCategory(category) {
        if (category === 'all') {
            this.renderGames(this.games);
        } else {
            const filtered = this.games.filter(game => game.category === category);
            this.renderGames(filtered);
        }
    }

    render() {
        this.renderGames(this.games);
        this.renderRecentGames();
        this.renderFavoriteGames();
    }

    renderGames(games) {
        const container = document.getElementById('gamesGrid');
        if (!container) return;

        container.innerHTML = games.map(game => {
            const isFav = this.isFavorite(game.id);
            return `
                <div class="game-card-launcher" data-game-id="${game.id}">
                    ${game.new ? '<span class="game-badge-new">NEW</span>' : ''}
                    <button class="game-favorite-btn ${isFav ? 'active' : ''}" 
                            onclick="gameLauncher.toggleFavorite('${game.id}')">
                        ${isFav ? '❤️' : '🤍'}
                    </button>
                    <div class="game-icon-launcher">${game.icon}</div>
                    <div class="game-name-launcher">${game.name}</div>
                    <a href="${game.url}" class="game-play-btn" onclick="gameLauncher.addToRecent('${game.id}')">
                        Chơi Ngay
                    </a>
                </div>
            `;
        }).join('');
    }

    renderRecentGames() {
        const container = document.getElementById('recentGamesList');
        if (!container) return;

        const recent = this.recentGames
            .map(id => this.games.find(g => g.id === id))
            .filter(g => g);

        if (recent.length === 0) {
            container.innerHTML = '<p class="empty-state">Chưa có game gần đây</p>';
            return;
        }

        container.innerHTML = recent.slice(0, 5).map(game => `
            <a href="${game.url}" class="recent-game-item" onclick="gameLauncher.addToRecent('${game.id}')">
                <span class="recent-game-icon">${game.icon}</span>
                <span class="recent-game-name">${game.name}</span>
            </a>
        `).join('');
    }

    renderFavoriteGames() {
        const container = document.getElementById('favoriteGamesList');
        if (!container) return;

        const favorites = this.favoriteGames
            .map(id => this.games.find(g => g.id === id))
            .filter(g => g);

        if (favorites.length === 0) {
            container.innerHTML = '<p class="empty-state">Chưa có game yêu thích</p>';
            return;
        }

        container.innerHTML = favorites.map(game => `
            <a href="${game.url}" class="favorite-game-item" onclick="gameLauncher.addToRecent('${game.id}')">
                <span class="favorite-game-icon">${game.icon}</span>
                <span class="favorite-game-name">${game.name}</span>
            </a>
        `).join('');
    }
}

// Initialize
let gameLauncher;
document.addEventListener('DOMContentLoaded', function () {
    gameLauncher = new GameLauncher();
    gameLauncher.render();
});

