/**
 * Game Enhancements JavaScript
 * Các tính năng nâng cao cho tất cả các trang game
 */

// Bet Amount Quick Buttons
function initBetQuickButtons() {
    const quickButtons = document.querySelectorAll('.bet-quick-btn');
    const betInput = document.querySelector('.bet-amount-input, input[name="cuoc"], #cuoc');
    
    if (!betInput) return;
    
    quickButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const amount = this.getAttribute('data-amount');
            if (amount && betInput) {
                betInput.value = parseInt(amount).toLocaleString('vi-VN');
                betInput.dispatchEvent(new Event('input', { bubbles: true }));
                
                // Visual feedback
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            }
        });
    });
}

// Format Bet Amount Input
function initBetAmountFormatter() {
    const betInputs = document.querySelectorAll('.bet-amount-input, input[name="cuoc"], #cuoc');
    
    betInputs.forEach(input => {
        // Format on input
        input.addEventListener('input', function() {
            let value = this.value.replace(/[^\d]/g, '');
            if (value) {
                this.value = parseInt(value).toLocaleString('vi-VN');
            }
        });
        
        // Format on blur
        input.addEventListener('blur', function() {
            let value = this.value.replace(/[^\d]/g, '');
            if (value) {
                this.value = parseInt(value).toLocaleString('vi-VN');
            }
        });
        
        // Remove formatting on focus for easier editing
        input.addEventListener('focus', function() {
            this.value = this.value.replace(/[^\d]/g, '');
        });
    });
}

// Game Button Loading State
function initGameButtonLoading() {
    const gameForms = document.querySelectorAll('form[method="post"]');
    
    gameForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                submitBtn.classList.add('btn-loading');
                submitBtn.disabled = true;
                
                // Show loading overlay
                showGameLoadingOverlay();
            }
        });
    });
}

// Show/Hide Loading Overlay
function showGameLoadingOverlay() {
    let overlay = document.querySelector('.game-loading-overlay');
    
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'game-loading-overlay';
        overlay.innerHTML = `
            <div class="game-loading-content">
                <div class="game-loading-spinner"></div>
                <div class="game-loading-text">Đang xử lý...</div>
            </div>
        `;
        document.body.appendChild(overlay);
    }
    
    overlay.classList.add('show');
}

function hideGameLoadingOverlay() {
    const overlay = document.querySelector('.game-loading-overlay');
    if (overlay) {
        overlay.classList.remove('show');
    }
}

// Animate Game Result
function animateGameResult(resultElement, isWin) {
    if (!resultElement) return;
    
    // Remove old classes
    resultElement.classList.remove('game-result-win', 'game-result-lose', 'game-result-draw');
    
    // Add appropriate class
    if (isWin === true) {
        resultElement.classList.add('game-result-win');
    } else if (isWin === false) {
        resultElement.classList.add('game-result-lose');
    } else {
        resultElement.classList.add('game-result-draw');
    }
    
    // Animate emojis
    const emojis = resultElement.querySelectorAll('.result-emoji, .emoji-item');
    emojis.forEach((emoji, index) => {
        emoji.style.animation = 'none';
        setTimeout(() => {
            emoji.style.animation = `emojiPop 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55) ${index * 0.1}s backwards`;
        }, 10);
    });
}

// Update Balance Display
function updateGameBalance(newBalance) {
    const balanceElements = document.querySelectorAll('.game-balance-value, .balance-value');
    
    balanceElements.forEach(el => {
        const oldBalance = parseFloat(el.textContent.replace(/[^\d]/g, '')) || 0;
        animateBalanceChange(el, oldBalance, newBalance);
    });
}

function animateBalanceChange(element, from, to) {
    const duration = 800;
    const startTime = performance.now();
    const range = to - from;
    
    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easeProgress = 1 - Math.pow(1 - progress, 2);
        const current = from + (range * easeProgress);
        
        element.textContent = Math.floor(current).toLocaleString('vi-VN') + ' VNĐ';
        
        if (progress < 1) {
            requestAnimationFrame(update);
        } else {
            element.textContent = Math.floor(to).toLocaleString('vi-VN') + ' VNĐ';
        }
    }
    
    requestAnimationFrame(update);
}

// Validate Bet Amount
function validateBetAmount(input, maxBalance) {
    const value = parseInt(input.value.replace(/[^\d]/g, '')) || 0;
    
    if (value <= 0) {
        input.style.borderColor = 'var(--danger-color)';
        input.style.boxShadow = '0 0 0 4px rgba(231, 76, 60, 0.1)';
        return false;
    }
    
    if (value > maxBalance) {
        input.style.borderColor = 'var(--warning-color)';
        input.style.boxShadow = '0 0 0 4px rgba(243, 156, 18, 0.1)';
        return false;
    }
    
    input.style.borderColor = 'var(--success-color)';
    input.style.boxShadow = '0 0 0 4px rgba(46, 204, 113, 0.1)';
    return true;
}

// Game Stats Updater
function updateGameStats(stats) {
    const statCards = document.querySelectorAll('.game-stat-card');
    
    statCards.forEach(card => {
        const statType = card.getAttribute('data-stat');
        const valueEl = card.querySelector('.game-stat-value');
        
        if (valueEl && stats[statType] !== undefined) {
            const targetValue = stats[statType];
            const currentValue = parseInt(valueEl.textContent.replace(/[^\d]/g, '')) || 0;
            
            if (targetValue !== currentValue) {
                animateValue(valueEl, currentValue, targetValue, 1000);
            }
        }
    });
}

// Initialize all game enhancements
document.addEventListener('DOMContentLoaded', function() {
    initBetQuickButtons();
    initBetAmountFormatter();
    initGameButtonLoading();
    
    // Hide loading overlay after page load
    setTimeout(hideGameLoadingOverlay, 500);
});

// Export functions
window.GameEnhancements = {
    showGameLoadingOverlay,
    hideGameLoadingOverlay,
    animateGameResult,
    updateGameBalance,
    validateBetAmount,
    updateGameStats,
    animateBalanceChange
};

