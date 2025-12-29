/**
 * Game Hi-Lo - JavaScript
 */

class HiloGame {
    constructor() {
        this.init();
    }

    init() {
        this.setupQuickBetButtons();
    }

    setupQuickBetButtons() {
        const quickButtons = document.querySelectorAll('.bet-quick-btn-hilo-enhanced');
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
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('guessForm') || document.querySelector('.start-button-hilo-enhanced')) {
        window.hiloGame = new HiloGame();
    }
});

