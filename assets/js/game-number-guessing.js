/**
 * Game Number Guessing - JavaScript
 */

class NumberGuessingGame {
    constructor() {
        this.init();
    }

    init() {
        this.setupQuickBetButtons();
        this.setupFormValidation();
        this.animateOnLoad();
    }

    setupQuickBetButtons() {
        const quickButtons = document.querySelectorAll('.bet-quick-btn-number-enhanced');
        quickButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const amount = btn.dataset.amount || btn.textContent.replace(/[^\d]/g, '');
                const input = document.getElementById('cuocInput');
                if (input) {
                    input.value = parseInt(amount);
                    // Trigger input event để validate
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
                
                quickButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // Animation
                btn.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    btn.style.transform = '';
                }, 150);
            });
        });
    }

    setupFormValidation() {
        const form = document.getElementById('numberForm');
        if (!form) return;
        
        const soInput = document.getElementById('soInput');
        const cuocInput = document.getElementById('cuocInput');
        const submitBtn = form.querySelector('.guess-button-number-enhanced');
        
        // Validate số đoán
        if (soInput) {
            soInput.addEventListener('input', () => {
                const value = parseInt(soInput.value);
                if (value < 1) {
                    soInput.value = 1;
                } else if (value > 100) {
                    soInput.value = 100;
                }
            });
        }
        
        // Validate số tiền cược
        if (cuocInput) {
            cuocInput.addEventListener('input', () => {
                const value = parseInt(cuocInput.value.replace(/[^\d]/g, ''));
                if (value > 0) {
                    cuocInput.value = value;
                } else if (cuocInput.value !== '') {
                    cuocInput.value = '';
                }
            });
        }
        
        form.addEventListener('submit', (e) => {
            const action = e.submitter?.value;
            
            // Nếu là new_game, không cần validate
            if (action === 'new_game') {
                return true;
            }
            
            const so = parseInt(soInput?.value || 0);
            const cuoc = parseInt(cuocInput?.value || 0);
            
            if (!so || so < 1 || so > 100) {
                e.preventDefault();
                this.showError('Vui lòng nhập số từ 1 đến 100!');
                soInput?.focus();
                return false;
            }
            
            if (!cuoc || cuoc <= 0) {
                e.preventDefault();
                this.showError('Vui lòng nhập số tiền cược hợp lệ!');
                cuocInput?.focus();
                return false;
            }
            
            // Disable button và show loading
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = '⏳ Đang xử lý...';
                submitBtn.style.opacity = '0.7';
            }
        });
    }

    showError(message) {
        // Tạo hoặc cập nhật error message
        let errorDiv = document.getElementById('number-error-message');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.id = 'number-error-message';
            errorDiv.style.cssText = `
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: #e74c3c;
                color: white;
                padding: 15px 30px;
                border-radius: 12px;
                box-shadow: 0 4px 15px rgba(231, 76, 60, 0.4);
                z-index: 10000;
                font-weight: 600;
                animation: slideDown 0.3s ease-out;
            `;
            document.body.appendChild(errorDiv);
        }
        
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        
        setTimeout(() => {
            errorDiv.style.animation = 'slideUp 0.3s ease-out';
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 300);
        }, 3000);
    }

    animateOnLoad() {
        // Animate game area
        const gameArea = document.querySelector('.number-game-area');
        if (gameArea) {
            gameArea.style.opacity = '0';
            gameArea.style.transform = 'translateY(20px)';
            setTimeout(() => {
                gameArea.style.transition = 'all 0.6s ease-out';
                gameArea.style.opacity = '1';
                gameArea.style.transform = 'translateY(0)';
            }, 100);
        }
        
        // Animate result banner if exists
        const resultBanner = document.getElementById('resultBanner');
        if (resultBanner) {
            resultBanner.style.opacity = '0';
            resultBanner.style.transform = 'scale(0.8)';
            setTimeout(() => {
                resultBanner.style.transition = 'all 0.5s ease-out';
                resultBanner.style.opacity = '1';
                resultBanner.style.transform = 'scale(1)';
            }, 300);
            
            // Scroll to result
            setTimeout(() => {
                resultBanner.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 500);
        }
        
        // Animate hint box
        const hintBox = document.querySelector('.number-hint-box');
        if (hintBox) {
            hintBox.style.opacity = '0';
            hintBox.style.transform = 'translateX(-20px)';
            setTimeout(() => {
                hintBox.style.transition = 'all 0.6s ease-out';
                hintBox.style.opacity = '1';
                hintBox.style.transform = 'translateX(0)';
            }, 200);
        }
    }
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateX(-50%) translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    }
    
    @keyframes slideUp {
        from {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        to {
            opacity: 0;
            transform: translateX(-50%) translateY(-20px);
        }
    }
`;
document.head.appendChild(style);

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('numberForm')) {
        window.numberGuessingGame = new NumberGuessingGame();
    }
});

