/**
 * Game Tower - JavaScript
 */

class TowerGame {
    constructor() {
        this.selectedColor = null;
        this.init();
    }

    init() {
        this.setupColorButtons();
        this.setupQuickBetButtons();
    }

    setupColorButtons() {
        const colorButtons = document.querySelectorAll('.color-btn-tower');
        colorButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove previous selection
                colorButtons.forEach(b => b.classList.remove('selected'));
                
                // Add selection
                btn.classList.add('selected');
                this.selectedColor = btn.dataset.color;
                
                // Update hidden input
                const hiddenInput = document.getElementById('selectedColor');
                if (hiddenInput) {
                    hiddenInput.value = this.selectedColor;
                }
            });
        });
    }

    setupQuickBetButtons() {
        const quickButtons = document.querySelectorAll('.bet-quick-btn-tower-enhanced');
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
    if (document.getElementById('towerForm')) {
        window.towerGame = new TowerGame();
    }
});

