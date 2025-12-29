/**
 * Game Dice Roll - JavaScript
 */

class DiceRollGame {
    constructor() {
        this.init();
    }

    init() {
        this.setupQuickBetButtons();
        this.setupBetTypeToggle();
        this.setupNumDiceChange();
    }

    setupQuickBetButtons() {
        const quickButtons = document.querySelectorAll('.bet-quick-btn-dice-roll-enhanced');
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

    setupBetTypeToggle() {
        const betTypeInputs = document.querySelectorAll('input[name="bet_type"]');
        const betValueInput = document.getElementById('betValueInput');
        
        betTypeInputs.forEach(input => {
            input.addEventListener('change', () => {
                if (input.value === 'total') {
                    betValueInput.style.display = 'block';
                    document.getElementById('betValue').required = true;
                } else {
                    betValueInput.style.display = 'none';
                    document.getElementById('betValue').required = false;
                }
            });
        });
    }

    setupNumDiceChange() {
        const numDiceSelect = document.getElementById('numDiceSelect');
        const betValueInput = document.getElementById('betValue');
        
        if (numDiceSelect && betValueInput) {
            numDiceSelect.addEventListener('change', () => {
                const numDice = parseInt(numDiceSelect.value);
                const minTotal = numDice * 1;
                const maxTotal = numDice * 6;
                
                betValueInput.min = minTotal;
                betValueInput.max = maxTotal;
                betValueInput.value = Math.round((minTotal + maxTotal) / 2);
            });
        }
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('diceRollForm')) {
        window.diceRollGame = new DiceRollGame();
    }
});

