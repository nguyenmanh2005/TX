/**
 * Game Dice - JavaScript Enhanced
 */

class DiceEnhanced {
    constructor() {
        this.selectedNumber = null;
        this.isRolling = false;
        this.diceEmoji = {
            1: "⚀",
            2: "⚁",
            3: "⚂",
            4: "⚃",
            5: "⚄",
            6: "⚅"
        };
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupQuickBetButtons();
        this.setupNumberButtons();
    }

    setupEventListeners() {
        const form = document.getElementById('gameForm');
        if (form) {
            form.addEventListener('submit', (e) => {
                if (!this.selectedNumber) {
                    e.preventDefault();
                    alert('Vui lòng chọn số từ 1 đến 6!');
                    return;
                }
                
                if (this.isRolling) {
                    e.preventDefault();
                    return;
                }
                
                this.rollDice();
            });
        }
    }

    setupQuickBetButtons() {
        const quickButtons = document.querySelectorAll('.bet-quick-btn-dice-enhanced');
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

    setupNumberButtons() {
        const numberButtons = document.querySelectorAll('.number-btn-enhanced');
        numberButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove previous selection
                numberButtons.forEach(b => b.classList.remove('selected'));
                
                // Add selection
                btn.classList.add('selected');
                this.selectedNumber = parseInt(btn.dataset.number);
                
                // Update hidden input
                const hiddenInput = document.getElementById('chonInput');
                if (hiddenInput) {
                    hiddenInput.value = this.selectedNumber;
                }
            });
        });
    }

    rollDice() {
        if (this.isRolling) return;
        
        this.isRolling = true;
        const dice = document.querySelector('.dice-3d-enhanced');
        const rollButton = document.getElementById('rollButton');
        
        if (dice) {
            dice.classList.add('rolling');
            
            setTimeout(() => {
                dice.classList.remove('rolling');
                this.isRolling = false;
            }, 1000);
        }
        
        if (rollButton) {
            rollButton.disabled = true;
            rollButton.textContent = '⏳ Đang lắc...';
        }
    }

    showResult(result) {
        const dice = document.querySelector('.dice-3d-enhanced');
        if (!dice) return;
        
        const diceFace = dice.querySelector('.dice-face-enhanced');
        if (diceFace && this.diceEmoji[result]) {
            diceFace.textContent = this.diceEmoji[result];
        }
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('gameForm')) {
        window.diceEnhanced = new DiceEnhanced();
        
        // Show result nếu có từ server
        const result = document.querySelector('[data-dice-result]');
        if (result && window.diceEnhanced) {
            const diceResult = parseInt(result.dataset.diceResult);
            if (diceResult) {
                window.diceEnhanced.showResult(diceResult);
            }
        }
    }
});

