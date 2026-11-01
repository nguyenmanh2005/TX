/**
 * 🤖 Bot Streamer - Game Mega Spin (ID: 38)
 * - Tách script riêng biệt khỏi live_38.php theo yêu cầu kiến trúc hệ thống.
 * - Tự động nhận diện chế độ bảo trì: Di chuyển con trỏ ảo quan sát tự nhiên.
 * - Hỗ trợ đầy đủ logic đặt cược và tương tác khi hệ thống mở lại.
 * - Tích hợp LiveStream Chat 24/7 theo chuẩn Casino Rule 5.3
 */

(function () {
    'use strict';

    function initMegaSpinBot() {
        if (typeof BotVirtualCursor === 'undefined') {
            setTimeout(initMegaSpinBot, 500);
            return;
        }

        BotVirtualCursor.init('Vòng Quay Hoàng Gia 🎡👑');

        const CASINO_CHATS = {
            maintenance: [
                "Kênh Mega Spin đang bảo trì nâng cấp vòng quay siêu cấp! Anh em cùng chờ đón nhé! 🍵✨",
                "Hệ thống đang tích luỹ thêm hũ Jackpot khủng! Chuẩn bị tinh thần giật giải nào! 💎🌟",
                "Hiệu chỉnh bánh xe số học! Lát nữa mở lại ta cùng quay banh nóc! 🎡⚡"
            ],
            spin: [
                "Quay Mega Spin Hoàng Gia! Ra chiêu quay bánh xe triệu đô rước GTLM! 🎡👑🔥",
                "Vòng quay may mắn khởi động! Căn đúng ô Jackpot húp trọn quà khủng! 🌟💰",
                "Ra chiêu tự tin, bánh xe quay tròn mang lộc lá về nick! 💎🚀"
            ],
            win: [
                "NỔ THƯỞNG MEGA SPIN! Bánh xe dừng đúng ô Hoàng Kim, tiền cộng ngập nick! 🏆🎡🤑",
                "Gáy khét lẹt cả sảnh quay hôm nay! Jackpot đã về tay! 👑🔥💰",
                "Lộc lá ngập tràn! Chiến thắng vẻ vang với vòng quay may mắn! 🌟🎉"
            ],
            loss: [
                "Dừng lệch mất 1 ô kim cương, bay màu nhẹ! Vòng sau phục thù! 😅🔄",
                "Bốc hơi một chút, tích luỹ may mắn cho cú nổ lớn tiếp theo! 💪🎡"
            ]
        };

        function sendChat(type) {
            if (typeof BotChat !== 'undefined' && BotChat.send) {
                const list = CASINO_CHATS[type] || CASINO_CHATS.spin;
                const msg = list[Math.floor(Math.random() * list.length)];
                BotChat.send(37, 'Vòng Quay Hoàng Gia 🎡👑', msg);
            }
        }

        // 1. Kiểm tra nếu trang đang ở chế độ bảo trì
        const isMaintenance = document.querySelector('.maintenance-box') || 
                              document.querySelector('.maintenance-container') ||
                              (document.body.innerText && document.body.innerText.includes('BẢO TRÌ'));

        if (isMaintenance) {
            console.log('[Bot 37] Kênh Mega Spin đang bảo trì. Chuyển sang chế độ con trỏ ảo quan sát.');
            const wanderCursor = () => {
                const targetX = 150 + Math.random() * Math.max(200, window.innerWidth - 300);
                const targetY = 150 + Math.random() * Math.max(200, window.innerHeight - 300);
                
                if (typeof BotVirtualCursor.moveTo === 'function') {
                    BotVirtualCursor.moveTo(targetX, targetY);
                } else if (typeof gsap !== 'undefined' && document.getElementById('bot-virtual-cursor')) {
                    gsap.to('#bot-virtual-cursor', { x: targetX, y: targetY, duration: 1.2, ease: 'power2.out' });
                }
                if (Math.random() < 0.25) sendChat('maintenance');
                setTimeout(wanderCursor, 8000 + Math.random() * 6000);
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
                                        try { 
                                            submitBtn.click(); 
                                            if (Math.random() < 0.4) sendChat('spin');
                                        } catch(e) {}
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
