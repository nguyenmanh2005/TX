/**
 * Game Mines - JavaScript Enhanced
 */

class MinesEnhanced {
    constructor() {
        this.gridSize = 5;
        this.revealedCells = [];
        this.mineCells = [];
        this.currentBet = 0;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupQuickBetButtons();
    }

    setupEventListeners() {
        const form = document.getElementById('gameForm');
        if (form) {
            form.addEventListener('submit', (e) => {
                const action = document.getElementById('gameAction').value;
                if (action === 'start') {
                    // Show grid after form submit
                    setTimeout(() => {
                        this.createGrid();
                    }, 500);
                }
            });
        }

        const cashoutBtn = document.getElementById('cashoutButton');
        if (cashoutBtn) {
            cashoutBtn.addEventListener('click', () => this.handleCashout());
        }
    }

    setupQuickBetButtons() {
        const quickButtons = document.querySelectorAll('.bet-quick-btn-mines-enhanced');
        quickButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const amount = btn.dataset.amount || btn.textContent.replace(/[^\d]/g, '');
                const input = document.getElementById('cuocInput');
                if (input) {
                    input.value = parseInt(amount).toLocaleString('vi-VN');
                    this.currentBet = parseInt(amount);
                }
            });
        });
    }

    createGrid() {
        const grid = document.getElementById('minesGrid');
        const gameInfo = document.getElementById('gameInfo');
        
        if (!grid) return;
        
        grid.style.display = 'grid';
        if (gameInfo) gameInfo.style.display = 'block';
        
        grid.innerHTML = '';
        
        for (let i = 0; i < this.gridSize * this.gridSize; i++) {
            const cell = document.createElement('div');
            cell.className = 'mine-cell-enhanced';
            cell.dataset.index = i;
            cell.textContent = '?';
            cell.addEventListener('click', () => this.revealCell(i));
            grid.appendChild(cell);
        }
    }

    revealCell(index) {
        const cell = document.querySelector(`[data-index="${index}"]`);
        if (!cell || cell.classList.contains('revealed')) return;
        
        // Gửi request đến server
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="play_mines">
            <input type="hidden" name="game_action" value="reveal">
            <input type="hidden" name="cell_index" value="${index}">
            <input type="hidden" name="cuoc" value="${this.currentBet}">
        `;
        document.body.appendChild(form);
        form.submit();
    }

    handleCashout() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="play_mines">
            <input type="hidden" name="game_action" value="cashout">
            <input type="hidden" name="cuoc" value="${this.currentBet}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('minesGrid')) {
        window.minesEnhanced = new MinesEnhanced();
    }
});

