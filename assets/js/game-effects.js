/**
 * Game Effects JavaScript - Hiệu ứng cho tất cả các game
 */

// Tạo confetti khi thắng - Cải thiện
function createConfetti(count = 100) {
    const colors = ['#ff6b6b', '#4ecdc4', '#ffe66d', '#a8e6cf', '#ff8b94', '#95e1d3', '#f38181', '#ffd700', '#ff6b9d'];
    const container = document.body;
    
    for (let i = 0; i < count; i++) {
        const confetti = document.createElement('div');
        confetti.className = 'confetti';
        confetti.style.left = Math.random() * 100 + '%';
        confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
        confetti.style.animationDelay = Math.random() * 2 + 's';
        confetti.style.animationDuration = (Math.random() * 2 + 2.5) + 's';
        // Random shape
        if (Math.random() > 0.5) {
            confetti.style.borderRadius = '50%';
        } else {
            confetti.style.transform = 'rotate(45deg)';
        }
        container.appendChild(confetti);
        
        setTimeout(() => confetti.remove(), 6000);
    }
}

// Tạo particle explosion
function createParticleExplosion(x, y, count = 30) {
    for (let i = 0; i < count; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = x + 'px';
        particle.style.top = y + 'px';
        
        const angle = (Math.PI * 2 * i) / count;
        const velocity = 100 + Math.random() * 50;
        const tx = Math.cos(angle) * velocity;
        const ty = Math.sin(angle) * velocity;
        
        particle.style.setProperty('--tx', tx + 'px');
        particle.style.setProperty('--ty', ty + 'px');
        
        document.body.appendChild(particle);
        setTimeout(() => particle.remove(), 1000);
    }
}

// Tạo emoji float animation
function createEmojiFloat(emoji, x, y) {
    const emojiEl = document.createElement('div');
    emojiEl.className = 'emoji-float';
    emojiEl.textContent = emoji;
    emojiEl.style.left = x + 'px';
    emojiEl.style.top = y + 'px';
    document.body.appendChild(emojiEl);
    
    setTimeout(() => emojiEl.remove(), 2000);
}

// Hiệu ứng thắng lớn - Cải thiện
function celebrateBigWin(amount) {
    // Confetti nhiều hơn
    createConfetti(200);
    
    // Emoji float với nhiều loại hơn
    const emojis = ['🎉', '🎊', '💰', '💎', '🏆', '⭐', '✨', '🔥', '💵', '🎁', '👑', '💸'];
    for (let i = 0; i < 30; i++) {
        setTimeout(() => {
            const x = Math.random() * window.innerWidth;
            const y = window.innerHeight;
            createEmojiFloat(emojis[Math.floor(Math.random() * emojis.length)], x, y);
        }, i * 80);
    }
    
    // Multiple particle explosions
    createParticleExplosion(window.innerWidth / 2, window.innerHeight / 2, 60);
    setTimeout(() => {
        createParticleExplosion(window.innerWidth / 4, window.innerHeight / 2, 40);
        createParticleExplosion(window.innerWidth * 3 / 4, window.innerHeight / 2, 40);
    }, 300);
    
    // Shake màn hình nhẹ với hiệu ứng tốt hơn
    document.body.style.animation = 'shake 0.6s ease-in-out';
    setTimeout(() => {
        document.body.style.animation = '';
    }, 600);
    
    // Thêm flash effect
    const flash = document.createElement('div');
    flash.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 215, 0, 0.3); z-index: 9998; pointer-events: none; animation: fadeOut 0.5s ease-out;';
    document.body.appendChild(flash);
    setTimeout(() => flash.remove(), 500);
}

// Hiệu ứng thắng thường
function celebrateWin(amount) {
    createConfetti(50);
    
    const emojis = ['🎉', '🎊', '💰', '✨'];
    for (let i = 0; i < 10; i++) {
        setTimeout(() => {
            const x = Math.random() * window.innerWidth;
            const y = window.innerHeight;
            createEmojiFloat(emojis[Math.floor(Math.random() * emojis.length)], x, y);
        }, i * 150);
    }
}

// Hiệu ứng thua
function showLoseEffect(element) {
    if (element) {
        element.classList.add('shake-effect');
        setTimeout(() => {
            element.classList.remove('shake-effect');
        }, 500);
    }
}

// Animate number counter
function animateNumber(element, start, end, duration = 1000) {
    const startTime = performance.now();
    const difference = end - start;
    
    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        const easeOutQuart = 1 - Math.pow(1 - progress, 4);
        const current = Math.floor(start + difference * easeOutQuart);
        
        element.textContent = current.toLocaleString('vi-VN');
        
        if (progress < 1) {
            requestAnimationFrame(update);
        } else {
            element.textContent = end.toLocaleString('vi-VN');
            element.classList.add('number-pop');
        }
    }
    
    requestAnimationFrame(update);
}

// Thêm glow effect vào element
function addGlowEffect(element, duration = 2000) {
    element.classList.add('glow-effect');
    setTimeout(() => {
        element.classList.remove('glow-effect');
    }, duration);
}

// Spin animation cho reel/slot
function spinReel(element, duration = 500) {
    element.classList.add('spin-reel');
    setTimeout(() => {
        element.classList.remove('spin-reel');
    }, duration);
}

// Bounce animation
function bounceElement(element) {
    element.classList.add('bounce-effect');
    setTimeout(() => {
        element.classList.remove('bounce-effect');
    }, 600);
}

// Card flip animation
function flipCard(element) {
    element.classList.add('card-flip');
    setTimeout(() => {
        element.classList.remove('card-flip');
    }, 600);
}

// Pulse animation
function pulseElement(element, duration = 2000) {
    element.classList.add('pulse-effect');
    setTimeout(() => {
        element.classList.remove('pulse-effect');
    }, duration);
}

// Button press effect
function buttonPressEffect(button) {
    button.classList.add('button-press');
    setTimeout(() => {
        button.classList.remove('button-press');
    }, 200);
}

// Show win message với animation
function showWinMessage(message, container) {
    const messageEl = document.createElement('div');
    messageEl.className = 'win-message';
    messageEl.textContent = message;
    container.appendChild(messageEl);
    
    setTimeout(() => {
        messageEl.style.opacity = '0';
        setTimeout(() => messageEl.remove(), 600);
    }, 3000);
}

// Show lose message với animation
function showLoseMessage(message, container) {
    const messageEl = document.createElement('div');
    messageEl.className = 'lose-message';
    messageEl.textContent = message;
    container.appendChild(messageEl);
    
    setTimeout(() => {
        messageEl.style.opacity = '0';
        setTimeout(() => messageEl.remove(), 600);
    }, 3000);
}

// Loading spinner
function showLoadingSpinner(container) {
    const spinner = document.createElement('div');
    spinner.className = 'loading-spinner';
    container.appendChild(spinner);
    return spinner;
}

// Rainbow text effect
function addRainbowText(element) {
    element.classList.add('rainbow-text');
}

// Streak indicator
function showStreakIndicator(text, container) {
    const indicator = document.createElement('div');
    indicator.className = 'streak-indicator';
    indicator.textContent = text;
    container.appendChild(indicator);
    
    setTimeout(() => {
        indicator.style.opacity = '0';
        setTimeout(() => indicator.remove(), 600);
    }, 3000);
}

// Disable button với effect
function disableButton(button) {
    button.classList.add('disabled-effect');
    button.disabled = true;
}

// Enable button
function enableButton(button) {
    button.classList.remove('disabled-effect');
    button.disabled = false;
}

// Export functions để sử dụng trong các game
window.GameEffects = {
    createConfetti,
    createParticleExplosion,
    createEmojiFloat,
    celebrateBigWin,
    celebrateWin,
    showLoseEffect,
    animateNumber,
    addGlowEffect,
    spinReel,
    bounceElement,
    flipCard,
    pulseElement,
    buttonPressEffect,
    showWinMessage,
    showLoseMessage,
    showLoadingSpinner,
    addRainbowText,
    showStreakIndicator,
    disableButton,
    enableButton
};

