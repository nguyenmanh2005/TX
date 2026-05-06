/**
 * Game Coin Flip - JavaScript Enhanced
 */

class CoinFlipEnhanced {
    constructor() {
        this.selectedChoice = null;
        this.isFlipping = false;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupQuickBetButtons();
        this.setupChoiceButtons();
    }

    setupEventListeners() {
        const form = document.getElementById('gameForm');
        if (form) {
            form.addEventListener('submit', (e) => {
                if (!this.selectedChoice) {
                    e.preventDefault();
                    alert('Vui lòng chọn Ngửa hoặc Sấp!');
                    return;
                }
                
                if (this.isFlipping) {
                    e.preventDefault();
                    return;
                }
                
                this.flipCoin();
            });
        }
    }

    setupQuickBetButtons() {
        const quickButtons = document.querySelectorAll('.bet-quick-btn-coinflip-enhanced');
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

    setupChoiceButtons() {
        const choiceButtons = document.querySelectorAll('.choice-btn-enhanced');
        choiceButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove previous selection
                choiceButtons.forEach(b => b.classList.remove('selected'));
                
                // Add selection
                btn.classList.add('selected');
                this.selectedChoice = btn.dataset.choice;
                
                // Update hidden input
                const hiddenInput = document.getElementById('chonInput');
                if (hiddenInput) {
                    hiddenInput.value = this.selectedChoice;
                }
            });
        });
    }

    flipCoin() {
        if (this.isFlipping) return;
        
        this.isFlipping = true;
        const coin = document.querySelector('.coin-enhanced');
        const flipButton = document.getElementById('flipButton');
        
        if (coin) {
            coin.classList.add('flipping');
            
            // Random result (sẽ được server xử lý)
            setTimeout(() => {
                coin.classList.remove('flipping');
                this.isFlipping = false;
            }, 1000);
        }
        
        if (flipButton) {
            flipButton.disabled = true;
            flipButton.textContent = '⏳ Đang tung...';
        }
    }

    showResult(result) {
        const coin = document.querySelector('.coin-enhanced');
        if (!coin) return;
        
        // result: "Ngửa" hoặc "Sấp"
        if (result === "Ngửa") {
            coin.classList.remove('show-back');
        } else {
            coin.classList.add('show-back');
        }
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('gameForm')) {
        window.coinFlipEnhanced = new CoinFlipEnhanced();
        
        // Show result nếu có từ server
        const result = document.querySelector('[data-coin-result]');
        if (result && window.coinFlipEnhanced) {
            const coinResult = result.dataset.coinResult;
            window.coinFlipEnhanced.showResult(coinResult);
        }
    }
});

