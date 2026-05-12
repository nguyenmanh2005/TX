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
        // Danh sách tất cả games - Đã cập nhật đầy đủ 40 trò chơi
        this.games = [
            // Casino Category
            { id: 'roulette', name: 'Roulette Pro', icon: '🎡', url: 'games/roulette.php', category: 'casino', new: false },
            { id: 'slot', name: 'Classic Slot', icon: '🎰', url: 'games/slot.php', category: 'casino', new: false },
            { id: 'slot_premium', name: 'Slot Machine Premium', icon: '💎', url: 'games/slot_machine.php', category: 'casino', new: true },
            { id: 'baccarat', name: 'Baccarat', icon: '🃏', url: 'games/baccarat.php', category: 'card', new: true },
            { id: 'blackjack', name: 'Blackjack', icon: '🃏', url: 'games/blackjack.php', category: 'card', new: false },
            { id: 'poker', name: 'Texas Hold\'em', icon: '🃏', url: 'games/poker.php', category: 'card', new: false },
            { id: 'dragon_tiger', name: 'Dragon Tiger', icon: '🐉🐅', url: 'games/dragontiger.php', category: 'casino', new: true },
            { id: 'sicbo', name: 'Sicbo Tài Xỉu', icon: '🎲', url: 'games/sicbo_v2.php', category: 'casino', new: true },
            { id: 'xocdia', name: 'Xóc Đĩa VIP', icon: '⚪🔴', url: 'games/xocdia.php', category: 'casino', new: true },
            { id: 'fantan', name: 'Fan Tan', icon: '🟡', url: 'games/fantan.php', category: 'casino', new: true },
            
            // Mini Games Category
            { id: 'crash', name: 'Crash Rocket', icon: '🚀', url: 'games/crash.php', category: 'mini', new: true },
            { id: 'plinko', name: 'Plinko', icon: '⚪', url: 'games/plinko.php', category: 'mini', new: true },
            { id: 'mines', name: 'Mines', icon: '💣', url: 'games/mines.php', category: 'mini', new: true },
            { id: 'minesweeper', name: 'Dò Mìn (Classic)', icon: '🚩', url: 'games/minesweeper.php', category: 'mini', new: true },
            { id: 'tower', name: 'Tower', icon: '🏗️', url: 'games/tower.php', category: 'mini', new: true },
            { id: 'limbo', name: 'Limbo', icon: '🚀', url: 'games/limbo.php', category: 'mini', new: true },
            { id: 'keno', name: 'Keno', icon: '🎯', url: 'games/keno.php', category: 'mini', new: true },
            { id: 'hilo', name: 'Hi-Lo', icon: '📈', url: 'games/hilo.php', category: 'mini', new: true },
            { id: 'coinflip', name: 'Coin Flip', icon: '🪙', url: 'games/coinflip.php', category: 'mini', new: false },
            { id: 'rps', name: 'Oẳn Tù Tì', icon: '✌️✊🖐️', url: 'games/rps.php', category: 'mini', new: true },
            
            // Special & Social Games
            { id: 'banharc', name: 'Bắn Cá Arcade', icon: '🐟', url: 'games/banharc.php', category: 'arcade', new: true },
            { id: 'jojo_battle', name: 'JOJO Battle', icon: '⚔️', url: 'games/jojo_battle.php', category: 'mini', new: true },
            { id: 'battleroyale', name: 'Battle Royale', icon: '🪂', url: 'games/battleroyale.php', category: 'mini', new: true },
            { id: 'trivia', name: 'Đố Vui Có Thưởng', icon: '💡', url: 'trivia.php', category: 'mini', new: true },
            { id: 'world_boss', name: 'World Boss', icon: '👹', url: 'world_boss.php', category: 'mini', new: true },
            
            // Lottery Category
            { id: 'lottery_mini', name: 'Xổ số Mini', icon: '🎯', url: 'games/lottery.php', category: 'mini', new: true },
            { id: 'vietlott', name: 'Vietlott 6/45', icon: '🎱', url: 'games/vietlott.php', category: 'mini', new: true },
            { id: 'community_lottery', name: 'Xổ số Cộng Đồng', icon: '🏛️', url: 'games/community_lottery.php', category: 'mini', new: true },
            
            // Classic Vietnamese Games
            { id: 'baucua', name: 'CYBER PETS (Bầu Cua)', icon: '🎲', url: 'games/baucua.php', category: 'casino', new: false },
            { id: 'daga', name: 'Đá Gà SV388', icon: '🐓', url: 'games/daga.php', category: 'casino', new: true },
            { id: 'duangua', name: 'Đua Ngựa Royal', icon: '🐎', url: 'games/duangua.php', category: 'casino', new: true },
            { id: 'samloc', name: 'Sâm Lốc', icon: '🃏', url: 'games/samloc.php', category: 'card', new: true },
            { id: 'threecard', name: 'Ba Cây', icon: '🃏', url: 'games/threecard.php', category: 'card', new: true },
            { id: 'tusac', name: 'Tứ Sắc', icon: '🎴', url: 'games/tusac.php', category: 'card', new: true },
            
            // Others
            { id: 'lucky_wheel', name: 'Vòng Quay May Mắn', icon: '🎡', url: 'lucky_wheel.php', category: 'casino', new: false },
            { id: 'bingo', name: 'Bingo Live', icon: '🎯', url: 'games/bingo.php', category: 'casino', new: false },
            { id: 'scratch', name: 'Thẻ Cào May Mắn', icon: '🏷️', url: 'games/scratch.php', category: 'mini', new: true },
            { id: 'yahtzee', name: 'Yahtzee Dice', icon: '🎲', url: 'games/yahtzee.php', category: 'mini', new: true },
            { id: 'craps', name: 'Craps Table', icon: '🎲', url: 'games/craps.php', category: 'casino', new: true },
            { id: 'dice_roll', name: 'Dice Roll Pro', icon: '🎲', url: 'dice_roll.php', category: 'mini', new: true },
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

