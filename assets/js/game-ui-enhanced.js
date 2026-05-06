/**
 * Game UI Enhanced - JavaScript cho cải thiện UI/UX games
 * Version 2.0
 */

class GameUIEnhanced {
    constructor() {
        this.init();
    }

    init() {
        this.setupLoadingStates();
        this.setupButtonAnimations();
        this.setupInputEnhancements();
        this.setupResultAnimations();
        this.setupConfetti();
        this.setupQuickAmountButtons();
        this.setupBalanceUpdate();
    }

    /**
     * Setup loading states cho buttons và forms
     */
    setupLoadingStates() {
        // Tự động thêm loading state cho buttons khi submit form
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', (e) => {
                const submitBtn = form.querySelector('button[type="submit"], .game-btn-enhanced');
                if (submitBtn && !submitBtn.disabled) {
                    this.showButtonLoading(submitBtn);
                }
            });
        });

        // Disable buttons khi đang loading
        document.querySelectorAll('.game-btn-enhanced').forEach(btn => {
            btn.addEventListener('click', function() {
                if (this.classList.contains('btn-loading')) {
                    return false;
                }
            });
        });
    }

    /**
     * Hiển thị loading state cho button
     */
    showButtonLoading(button) {
        if (!button) return;
        
        button.classList.add('btn-loading');
        button.disabled = true;
        const originalText = button.innerHTML;
        button.dataset.originalText = originalText;
        button.innerHTML = '<span class="spinner-small"></span> Đang xử lý...';
    }

    /**
     * Ẩn loading state cho button
     */
    hideButtonLoading(button) {
        if (!button) return;
        
        button.classList.remove('btn-loading');
        button.disabled = false;
        if (button.dataset.originalText) {
            button.innerHTML = button.dataset.originalText;
            delete button.dataset.originalText;
        }
    }

    /**
     * Setup button animations
     */
    setupButtonAnimations() {
        document.querySelectorAll('.game-btn-enhanced').forEach(btn => {
            // Ripple effect
            btn.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.classList.add('ripple');
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
    }

    /**
     * Setup input enhancements
     */
    setupInputEnhancements() {
        // Format số tiền khi nhập
        document.querySelectorAll('.control-input-enhanced[type="number"], .bet-amount-input').forEach(input => {
            input.addEventListener('input', function() {
                const value = this.value.replace(/,/g, '');
                if (value && !isNaN(value)) {
                    // Format với dấu phẩy
                    const formatted = parseInt(value).toLocaleString('vi-VN');
                    // Không update nếu đang focus để tránh conflict
                    if (document.activeElement !== this) {
                        this.value = formatted;
                    }
                }
            });

            // Format khi blur
            input.addEventListener('blur', function() {
                const value = this.value.replace(/,/g, '');
                if (value && !isNaN(value)) {
                    this.value = parseInt(value).toLocaleString('vi-VN');
                }
            });
        });

        // Auto-focus first input
        const firstInput = document.querySelector('.control-input-enhanced, .bet-amount-input');
        if (firstInput && !firstInput.value) {
            setTimeout(() => firstInput.focus(), 300);
        }
    }

    /**
     * Setup result animations
     */
    setupResultAnimations() {
        // Animate result khi có kết quả
        const resultElement = document.querySelector('.game-result-enhanced');
        if (resultElement) {
            // Trigger animation
            resultElement.style.animation = 'none';
            setTimeout(() => {
                resultElement.style.animation = '';
            }, 10);
        }

        // Animate emojis
        document.querySelectorAll('.result-emoji-enhanced').forEach((emoji, index) => {
            emoji.style.animationDelay = (index * 0.1) + 's';
        });
    }

    /**
     * Setup confetti effects
     */
    setupConfetti() {
        // Tự động trigger confetti khi có class .big-win
        if (document.querySelector('.big-win, .game-result-win-enhanced')) {
            const isBigWin = document.querySelector('.big-win');
            if (isBigWin) {
                this.showConfetti(200);
            } else {
                this.showConfetti(50);
            }
        }
    }

    /**
     * Hiển thị confetti
     */
    showConfetti(count = 100) {
        const colors = ['#ff6b6b', '#4ecdc4', '#ffe66d', '#a8e6cf', '#ff8b94', '#95e1d3', '#f38181'];
        const confettiContainer = document.createElement('div');
        confettiContainer.style.position = 'fixed';
        confettiContainer.style.top = '0';
        confettiContainer.style.left = '0';
        confettiContainer.style.width = '100%';
        confettiContainer.style.height = '100%';
        confettiContainer.style.pointerEvents = 'none';
        confettiContainer.style.zIndex = '9999';
        document.body.appendChild(confettiContainer);

        for (let i = 0; i < count; i++) {
            const confetti = document.createElement('div');
            const size = Math.random() * 10 + 5;
            const color = colors[Math.floor(Math.random() * colors.length)];
            const startX = Math.random() * window.innerWidth;
            const duration = Math.random() * 3 + 2;
            const delay = Math.random() * 0.5;

            confetti.style.position = 'absolute';
            confetti.style.width = size + 'px';
            confetti.style.height = size + 'px';
            confetti.style.backgroundColor = color;
            confetti.style.left = startX + 'px';
            confetti.style.top = '-10px';
            confetti.style.borderRadius = '50%';
            confetti.style.boxShadow = `0 0 ${size}px ${color}`;
            confetti.style.animation = `confettiFall ${duration}s ease-out ${delay}s forwards`;

            confettiContainer.appendChild(confetti);
        }

        // Remove sau khi animation xong
        setTimeout(() => {
            confettiContainer.remove();
        }, 5000);
    }

    /**
     * Setup quick amount buttons
     */
    setupQuickAmountButtons() {
        document.querySelectorAll('.bet-quick-btn-enhanced').forEach(btn => {
            btn.addEventListener('click', function() {
                const amount = this.dataset.amount || this.textContent.replace(/[^\d]/g, '');
                const input = document.querySelector('.control-input-enhanced[type="number"], .bet-amount-input');
                if (input) {
                    input.value = parseInt(amount).toLocaleString('vi-VN');
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    
                    // Highlight button
                    document.querySelectorAll('.bet-quick-btn-enhanced').forEach(b => {
                        b.classList.remove('active');
                    });
                    this.classList.add('active');
                }
            });
        });
    }

    /**
     * Setup balance update animation
     */
    setupBalanceUpdate() {
        const balanceElement = document.querySelector('.balance-value, .balance-value-enhanced');
        if (balanceElement) {
            // Observe balance changes
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'childList' || mutation.type === 'characterData') {
                        balanceElement.classList.add('balance-update');
                        setTimeout(() => {
                            balanceElement.classList.remove('balance-update');
                        }, 1000);
                    }
                });
            });

            observer.observe(balanceElement, {
                childList: true,
                characterData: true,
                subtree: true
            });
        }
    }

    /**
     * Show loading overlay
     */
    showLoadingOverlay(message = 'Đang xử lý...') {
        let overlay = document.querySelector('.game-loading-overlay-enhanced');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'game-loading-overlay-enhanced';
            overlay.innerHTML = `
                <div class="game-loading-content-enhanced">
                    <div class="game-loading-spinner-enhanced"></div>
                    <div class="game-loading-text-enhanced">${message}</div>
                </div>
            `;
            document.body.appendChild(overlay);
        }
        
        const textElement = overlay.querySelector('.game-loading-text-enhanced');
        if (textElement) {
            textElement.textContent = message;
        }
        
        setTimeout(() => {
            overlay.classList.add('show');
        }, 10);
    }

    /**
     * Hide loading overlay
     */
    hideLoadingOverlay() {
        const overlay = document.querySelector('.game-loading-overlay-enhanced');
        if (overlay) {
            overlay.classList.remove('show');
            setTimeout(() => {
                overlay.remove();
            }, 400);
        }
    }

    /**
     * Show result với animation
     */
    showResult(type, message, emojis = []) {
        const resultElement = document.querySelector('.game-result-enhanced');
        if (!resultElement) return;

        resultElement.className = `game-result-enhanced game-result-${type}-enhanced`;
        
        let content = '';
        if (emojis.length > 0) {
            content += '<div class="result-emojis">';
            emojis.forEach(emoji => {
                content += `<span class="result-emoji-enhanced">${emoji}</span>`;
            });
            content += '</div>';
        }
        
        content += `<div class="result-message-enhanced result-message-${type}-enhanced">${message}</div>`;
        resultElement.innerHTML = content;

        // Trigger animation
        resultElement.style.animation = 'none';
        setTimeout(() => {
            resultElement.style.animation = '';
        }, 10);

        // Show confetti nếu thắng
        if (type === 'win') {
            this.showConfetti(100);
        }
    }

    /**
     * Animate number counter
     */
    animateNumber(element, start, end, duration = 1000) {
        const startTime = performance.now();
        const difference = end - start;

        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function
            const easeOutCubic = 1 - Math.pow(1 - progress, 3);
            const current = Math.floor(start + difference * easeOutCubic);
            
            element.textContent = current.toLocaleString('vi-VN');
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                element.textContent = end.toLocaleString('vi-VN');
            }
        };

        requestAnimationFrame(animate);
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#2ecc71' : type === 'error' ? '#e74c3c' : '#3498db'};
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 10001;
            animation: slideInRight 0.3s ease-out;
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes confettiFall {
        0% {
            transform: translateY(0) rotate(0deg);
            opacity: 1;
        }
        100% {
            transform: translateY(100vh) rotate(720deg);
            opacity: 0;
        }
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        transform: scale(0);
        animation: rippleAnimation 0.6s ease-out;
        pointer-events: none;
    }
    
    @keyframes rippleAnimation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    .balance-update {
        animation: balancePulse 0.5s ease-out;
    }
    
    @keyframes balancePulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    .bet-quick-btn-enhanced.active {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.3), rgba(118, 75, 162, 0.3)) !important;
        border-color: #667eea !important;
        transform: scale(1.05);
    }
    
    .spinner-small {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
`;
document.head.appendChild(style);

// Initialize khi DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.gameUI = new GameUIEnhanced();
    });
} else {
    window.gameUI = new GameUIEnhanced();
}

// Export cho sử dụng global
window.GameUIEnhanced = GameUIEnhanced;








