/**
 * Auto Game Effects - Tự động áp dụng hiệu ứng cho các game
 * File này sẽ tự động phát hiện và áp dụng hiệu ứng dựa trên class và ID của elements
 */

(function() {
    'use strict';
    
    // Đợi DOM load xong
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGameEffects);
    } else {
        initGameEffects();
    }
    
    function initGameEffects() {
        // Kiểm tra xem GameEffects có sẵn không
        if (typeof GameEffects === 'undefined') {
            console.warn('GameEffects library not loaded');
            return;
        }
        
        // Tự động thêm button press effect cho tất cả submit buttons
        const submitButtons = document.querySelectorAll('button[type="submit"], .btn-game, button:not([type="button"])');
        submitButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                GameEffects.buttonPressEffect(this);
                const rect = this.getBoundingClientRect();
                GameEffects.createParticleExplosion(rect.left + rect.width / 2, rect.top + rect.height / 2, 10);
            });
        });
        
        // Tự động phát hiện win/lose messages và áp dụng hiệu ứng - Cải thiện
        const winMessages = document.querySelectorAll('.thang, .win, .result-win, .message.win, .thongbao.thang');
        winMessages.forEach(msg => {
            // Kiểm tra xem có phải big win không (dựa vào text)
            const text = msg.textContent || '';
            const isBigWin = text.includes('JACKPOT') || text.includes('thắng lớn') || 
                           text.includes('WTF') || text.includes('Ân Bờ Lí') ||
                           text.match(/\d{7,}/); // Số tiền >= 10 triệu
            
            // Thêm class để CSS có thể style
            msg.classList.add('win-message-animated');
            
            if (isBigWin) {
                msg.classList.add('big-win-message');
                GameEffects.celebrateBigWin(0);
                setTimeout(() => {
                    GameEffects.addGlowEffect(msg, 4000);
                    GameEffects.pulseElement(msg, 3000);
                }, 500);
            } else {
                GameEffects.celebrateWin(0);
                setTimeout(() => {
                    GameEffects.bounceElement(msg);
                    GameEffects.addGlowEffect(msg, 2000);
                }, 500);
            }
        });
        
        const loseMessages = document.querySelectorAll('.thua, .lose, .result-lose, .message.lose, .thongbao.thua');
        loseMessages.forEach(msg => {
            msg.classList.add('lose-message-animated');
            const container = msg.closest('.game-box, .slot-machine, .game-container, .game-box');
            if (container) {
                GameEffects.showLoseEffect(container);
            } else {
                GameEffects.showLoseEffect(msg);
            }
        });
        
        // Tự động thêm hover effects cho các interactive elements
        const interactiveElements = document.querySelectorAll('.reel, .dice-display, .card, .number-btn, .color-btn');
        interactiveElements.forEach(el => {
            el.addEventListener('mouseenter', function() {
                this.style.transition = 'all 0.3s ease';
            });
        });
        
        // Tự động thêm pulse effect cho balance display
        const balanceDisplays = document.querySelectorAll('.balance, .money, [class*="balance"]');
        balanceDisplays.forEach(balance => {
            // Pulse khi có thay đổi
            const observer = new MutationObserver(() => {
                GameEffects.pulseElement(balance, 1000);
            });
            observer.observe(balance, { childList: true, characterData: true, subtree: true });
        });
        
        // Tự động thêm confetti khi có class "big-win" hoặc "jackpot"
        const bigWinElements = document.querySelectorAll('.big-win, .jackpot, [class*="jackpot"]');
        bigWinElements.forEach(el => {
            GameEffects.celebrateBigWin(0);
        });
        
        // Tự động thêm shake effect cho lose elements
        const loseElements = document.querySelectorAll('.shake-on-lose, .lose-shake');
        loseElements.forEach(el => {
            GameEffects.showLoseEffect(el);
        });
        
        console.log('Game Effects Auto initialized');
    }
    
    // Export để có thể gọi từ các game khác
    window.GameEffectsAuto = {
        init: initGameEffects
    };
})();

