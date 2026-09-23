/**
 * 🎁 Bot Streamer Bàn 29: Thánh Khui Túi 29 (Bóc Túi Mù) - TURBO FAST-PACED MODE 24/7
 * Chuẩn Modern Async/Await + Chuột Ảo An Toàn + Fallback Tự Động + Casino Rule 5.3
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 29] BotVirtualCursor chưa tải!');
        return;
    }

    BotVirtualCursor.init('Thánh Khui Túi 29 🎁');

    let botIsBusy = false;
    let busyTimestamp = 0;
    let consecutiveLoss = 0;

    const CASINO_CHATS = {
        opening: [
            "Túi mù hôm nay nặng tay, khui ngay xem có đồ VIP không! 🎁✨",
            "Cầu may mắn đang tới, xé túi này lấy hên nào anh em! 🌟💎",
            "Đang bóc túi... Nín thở chờ đón điều kỳ diệu! 🤞🔥",
            "Túi này màu đẹp quá, nổ vật phẩm huyền thoại 1.000.000 GTLM nào! 🌈🚀",
            "Tiếng này cầu tài lộc đang sáng, mở túi liên hoàn! ⚡🎁"
        ],
        win: [
            "Húp trọn đồ xịn rồi anh em ơi! Quá đã! 🎉🏆",
            "Nổ quà to rồi, lộc lá ngập tràn tài khoản! 🤑💰",
            "Tay hôm nay đỏ rực rỡ, húp thêm món này ấm túi! 🎁🔥",
            "Tuyệt vời! Túi mù phát tài, tiền về như lũ! 👑💎"
        ],
        loss: [
            "Túi này quà hơi hẻo, làm lại túi sau gỡ gạc ngay! 😅💔",
            "Bay màu túi này không sao, còn thở là còn bóc! 🔄💪",
            "Trượt tiếng này để dồn may mắn vào siêu hũ tiếp theo! ⚔️🔥"
        ]
    };

    function sendChat(type) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.opening;
            const msg = list[Math.floor(Math.random() * list.length)];
            BotChat.send(29, 'bot_29', msg);
        }
    }

    // Chuột ảo Turbo 0.22s
    function fastMoveAndClick(el, duration = 0.22) {
        return new Promise((resolve) => {
            const $el = $(el);
            if (!$el.length) return resolve(false);

            let hasResolved = false;
            const safetyTimer = setTimeout(() => {
                if (!hasResolved) {
                    hasResolved = true;
                    resolve(true);
                }
            }, (duration * 1000) + 300);

            try {
                BotVirtualCursor.moveToElement($el, duration, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try {
                            $el[0].click();
                            // Kích hoạt fallback nếu form không submit tự động
                            if (typeof window.openBagStreamer === 'function') {
                                window.openBagStreamer();
                            }
                        } catch (e) {}
                        if (!hasResolved) {
                            hasResolved = true;
                            clearTimeout(safetyTimer);
                            resolve(true);
                        }
                    });
                });
            } catch (err) {
                if (!hasResolved) {
                    hasResolved = true;
                    clearTimeout(safetyTimer);
                    resolve(false);
                }
            }
        });
    }

    const wait = (ms) => new Promise((res) => setTimeout(res, ms));

    async function executeBotTurn() {
        if (botIsBusy) return;

        const btn = document.getElementById('submitBtn');
        if (!btn || btn.disabled) return;

        botIsBusy = true;
        busyTimestamp = Date.now();

        try {
            if (Math.random() < 0.3) sendChat('opening');

            // 1. Rê chuột ảo tới nút bóc túi và click nhanh trong 0.22s
            await fastMoveAndClick($(btn), 0.22);

            // 2. Chờ hiệu ứng mở túi (khoảng 0.5s)
            await wait(550);

            // 3. Đọc kết quả từ container
            const resultText = $('#result-container').text().toLowerCase();
            const isWin = $('.result.win').length > 0 || resultText.includes('thắng') || resultText.includes('nhận được');

            if (isWin) {
                consecutiveLoss = 0;
                if (Math.random() < 0.4) sendChat('win');
            } else {
                consecutiveLoss++;
                if (Math.random() < 0.25) sendChat('loss');
            }

            await wait(150);

        } catch (e) {
            console.error('[Bot 29 Turbo Error]', e);
        } finally {
            botIsBusy = false;
        }
    }

    // Nhịp điệu siêu tốc mỗi 1.2 giây
    setInterval(executeBotTurn, 1200);

    // Watchdog
    setInterval(() => {
        // Tầng 1: Giải phóng bận nếu quá 3.5s
        if (botIsBusy && Date.now() - busyTimestamp > 3500) {
            botIsBusy = false;
            const btn = document.getElementById('submitBtn');
            if (btn) btn.disabled = false;
        }

        // Tầng 2: Nếu nút sẵn sàng mà bot chưa bấm -> Kích hoạt lượt mới
        const btn = document.getElementById('submitBtn');
        if (btn && !btn.disabled && !botIsBusy) {
            executeBotTurn();
        }
    }, 2000);

    console.log('[Bot 29 Turbo] Thánh Khui Túi sẵn sàng mở quà thần tốc 24/7!');
})();
