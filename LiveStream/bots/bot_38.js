/**
 * 🤖 Bot Streamer - Game Mega Spin (ID: 38)
 * - Tách script riêng biệt khỏi live_38.php theo yêu cầu kiến trúc hệ thống.
 * - Tự động nhận diện chế độ bảo trì: Di chuyển con trỏ ảo quan sát tự nhiên.
 * - Hỗ trợ đầy đủ logic đặt cược và tương tác khi hệ thống mở lại.
 */

(function () {
    'use strict';

    function initMegaSpinBot() {
        if (typeof BotVirtualCursor === 'undefined') {
            setTimeout(initMegaSpinBot, 500);
            return;
        }

        BotVirtualCursor.init('Bot Streamer');

        // 1. Kiểm tra nếu trang đang ở chế độ bảo trì
        const isMaintenance = document.querySelector('.maintenance-box') || 
                              document.querySelector('.maintenance-container') ||
                              (document.body.innerText && document.body.innerText.includes('BẢO TRÌ'));

        if (isMaintenance) {
            console.log('[Bot 38] Kênh Mega Spin đang bảo trì. Chuyển sang chế độ con trỏ ảo quan sát.');
            const wanderCursor = () => {
                const targetX = 150 + Math.random() * Math.max(200, window.innerWidth - 300);
                const targetY = 150 + Math.random() * Math.max(200, window.innerHeight - 300);
                
                if (typeof BotVirtualCursor.moveTo === 'function') {
                    BotVirtualCursor.moveTo(targetX, targetY);
                } else if (typeof gsap !== 'undefined' && document.getElementById('bot-virtual-cursor')) {
                    gsap.to('#bot-virtual-cursor', { x: targetX, y: targetY, duration: 1.2, ease: 'power2.out' });
                }
                setTimeout(wanderCursor, 3500 + Math.random() * 4000);
            };
            setTimeout(wanderCursor, 1500);
            return;
        }

        // 2. Logic khi game Mega Spin hoạt động bình thường
        const playRound = () => {
            const betBtns = Array.from(document.querySelectorAll('.bet-btn, .btn-bet, .chip'));
            const submitBtn = document.querySelector('#placeBetBtn, .btn-submit, .btn-spin, #submitBet');

            if (betBtns.length > 0 && submitBtn && !submitBtn.disabled) {
                const randBetBtn = betBtns[Math.floor(Math.random() * betBtns.length)];
                BotVirtualCursor.moveToElement($(randBetBtn), 0.7, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try { randBetBtn.click(); } catch(e) {}
                        setTimeout(() => {
                            if (submitBtn && !submitBtn.disabled) {
                                BotVirtualCursor.moveToElement($(submitBtn), 0.7, 0, () => {
                                    BotVirtualCursor.simulateClick(() => {
                                        try { submitBtn.click(); } catch(e) {}
                                    });
                                });
                            }
                        }, 600);
                    });
                });
            }
            setTimeout(playRound, 6000 + Math.random() * 5000);
        };

        setTimeout(playRound, 3000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMegaSpinBot);
    } else {
        initMegaSpinBot();
    }
})();
