/**
 * Game Limbo - JavaScript
 */

class LimboGame {
    constructor() {
        this.init();
    }

    init() {
        this.setupPresetButtons();
        this.setupQuickBetButtons();
        this.setupMultiplierInput();
    }

    setupPresetButtons() {
        const presetButtons = document.querySelectorAll('.preset-btn');
        presetButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const multiplier = btn.dataset.multiplier;
                const input = document.getElementById('targetMultiplierInput');
                if (input) {
                    input.value = multiplier;
                    this.updateWinChance(multiplier);
                }
                
                presetButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });
    }

    setupQuickBetButtons() {
        const quickButtons = document.querySelectorAll('.bet-quick-btn-limbo-enhanced');
        quickButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const amount = btn.dataset.amount || btn.textContent.replace(/[^\d]/g, '');
                const input = document.getElementById('cuocInput');
                if (input) {
                    input.value = parseInt(amount).toLocaleString('vi-VN');
                }
                
                quickButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });
    }

    setupMultiplierInput() {
        const input = document.getElementById('targetMultiplierInput');
        if (input) {
            input.addEventListener('input', (e) => {
                const multiplier = parseFloat(e.target.value) || 0;
                if (multiplier >= 1.01) {
                    this.updateWinChance(multiplier);
                }
            });
        }
    }

    updateWinChance(multiplier) {
        // Calculate win chance: (1 / multiplier) * 100
        const winChance = (1 / multiplier) * 100;
        const display = document.getElementById('winChanceValue');
        if (display) {
            display.textContent = winChance.toFixed(2);
        }
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('limboForm')) {
        window.limboGame = new LimboGame();
        
        // Initialize win chance if multiplier input has value
        const multiplierInput = document.getElementById('targetMultiplierInput');
        if (multiplierInput && multiplierInput.value) {
            window.limboGame.updateWinChance(parseFloat(multiplierInput.value));
        }
    }
});
