/**
 * Game Rock Paper Scissors - JavaScript
 */

class RockPaperScissorsGame {
    constructor() {
        this.selectedChoice = null;
        this.init();
    }

    init() {
        this.setupQuickBetButtons();
        this.setupChoiceButtons();
        this.setupFormValidation();
        this.animateOnLoad();
    }

    setupQuickBetButtons() {
        const quickButtons = document.querySelectorAll('.bet-quick-btn-rps-enhanced');
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

    setupChoiceButtons() {
        const choiceButtons = document.querySelectorAll('.choice-btn-rps');
        choiceButtons.forEach(btn => {
            const radio = btn.querySelector('input[type="radio"]');
            if (radio) {
                // Click on label
                btn.addEventListener('click', (e) => {
                    if (e.target !== radio) {
                        radio.checked = true;
                        radio.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
                
                radio.addEventListener('change', () => {
                    choiceButtons.forEach(b => {
                        b.classList.remove('selected');
                        b.style.transform = '';
                    });
                    if (radio.checked) {
                        btn.classList.add('selected');
                        this.selectedChoice = radio.value;
                        // Animation
                        btn.style.transform = 'scale(1.1)';
                        setTimeout(() => {
                            btn.style.transform = '';
                        }, 200);
                    }
                });
            }
        });
    }

    setupFormValidation() {
        const form = document.getElementById('rpsForm');
        if (!form) return;
        
        const cuocInput = document.getElementById('cuocInput');
        const playButton = form.querySelector('.play-button-rps-enhanced');
        
        if (cuocInput) {
            cuocInput.addEventListener('input', () => {
                const value = parseInt(cuocInput.value.replace(/[^\d]/g, ''));
                if (value > 0) {
                    cuocInput.value = value;
                }
            });
        }
        
        form.addEventListener('submit', (e) => {
            const choice = form.querySelector('input[name="choice"]:checked');
            const cuoc = parseInt(cuocInput?.value || 0);
            
            if (!choice) {
                e.preventDefault();
                this.showError('Vui lòng chọn Rock, Paper hoặc Scissors!');
                return false;
            }
            
            if (!cuoc || cuoc <= 0) {
                e.preventDefault();
                this.showError('Vui lòng nhập số tiền cược hợp lệ!');
                cuocInput?.focus();
                return false;
            }
            
            // Disable button và show loading
            if (playButton) {
                playButton.disabled = true;
                playButton.textContent = '⏳ Đang xử lý...';
                playButton.style.opacity = '0.7';
            }
        });
    }

    showError(message) {
        // Tạo hoặc cập nhật error message
        let errorDiv = document.getElementById('rps-error-message');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.id = 'rps-error-message';
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
        // Animate battle area
        const battleArea = document.querySelector('.rps-battle-area');
        if (battleArea) {
            battleArea.style.opacity = '0';
            battleArea.style.transform = 'translateY(20px)';
            setTimeout(() => {
                battleArea.style.transition = 'all 0.6s ease-out';
                battleArea.style.opacity = '1';
                battleArea.style.transform = 'translateY(0)';
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
        
        // Animate choice displays
        const choiceDisplays = document.querySelectorAll('.choice-display-rps');
        choiceDisplays.forEach((display, index) => {
            if (display.querySelector('.rps-icon')) {
                display.style.opacity = '0';
                display.style.transform = 'rotateY(90deg)';
                setTimeout(() => {
                    display.style.transition = 'all 0.6s ease-out';
                    display.style.opacity = '1';
                    display.style.transform = 'rotateY(0deg)';
                }, 200 + (index * 100));
            }
        });
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
    
    .choice-btn-rps.selected {
        transform: scale(1.05) !important;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4) !important;
    }
    
    .choice-btn-rps {
        transition: all 0.3s ease;
    }
    
    .bet-quick-btn-rps-enhanced {
        transition: all 0.2s ease;
    }
`;
document.head.appendChild(style);

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('rpsForm')) {
        window.rpsGame = new RockPaperScissorsGame();
    }
});

