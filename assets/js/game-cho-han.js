/**
 * Game Cho-Han - JavaScript
 */

class ChoHanGame {
    constructor() {
        this.init();
    }

    init() {
        this.setupQuickBetButtons();
        this.setupBetOptions();
    }

    setupQuickBetButtons() {
        const quickButtons = document.querySelectorAll('.bet-quick-btn-cho-han-enhanced');
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

    setupBetOptions() {
        const betOptions = document.querySelectorAll('.bet-option-cho-han');
        betOptions.forEach(option => {
            const radio = option.querySelector('input[type="radio"]');
            if (radio) {
                radio.addEventListener('change', () => {
                    betOptions.forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    if (radio.checked) {
                        option.classList.add('selected');
                    }
                });
            }
        });
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('choHanForm')) {
        window.choHanGame = new ChoHanGame();
    }
});
