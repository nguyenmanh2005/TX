/**
 * 🪙 Bot Streamer Bàn 18: Thánh Lật Kèo 18 (Tung Đồng Xu) - CHẾ ĐỘ SIÊU TỐC (TURBO 24/7)
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') return;

    BotVirtualCursor.init('Thánh Lật Kèo 18 🪙');

    let botIsBusy = false;
    let busyTimestamp = 0;

    const CASINO_CHATS = {
        bet: [
            "Cầu đang bệt Sấp đẹp quá, vào mạnh tay mặt Vàng nào! 🪙🔥",
            "Đồng xu quay tít mù, tiếng này tôi kết Ngửa lấy lộc! ⚪✨",
            "Kèo x2 ăn chắc, lật đúng mặt là tiền về đầy ví! 💰💎",
            "Tung đồng xu thử vận may, tiếng này thơm phức! 🌟🚀"
        ],
        win: [
            "Húp trọn kèo x2 rồi anh em ơi! Lộc lá đầy bao! 🏆🎉",
            "Đúng như tiên tri! Lật kèo ngoạn mục, ăn đậm ván này! 🤑💰",
            "Húp trọn tiếng này rồi! Đẳng cấp thánh lật kèo là đây! 👑✨"
        ],
        loss: [
            "Lật ngược mặt phút chót, ván sau gấp đôi phục thù ngay! 🔄💪",
            "Còn thở là còn lật, ván sau tất tay bảo đảm ăn chắc! ⚔️🔥"
        ]
    };

    function sendChat(type) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.bet;
            const msg = list[Math.floor(Math.random() * list.length)];
            BotChat.send(18, 'bot_18', msg);
        }
    }

    function fastMoveAndClick(el, duration = 0.25) {
        return new Promise((resolve) => {
            const $el = $(el);
            if (!$el.length) return resolve(false);

            let hasResolved = false;
            const safetyTimer = setTimeout(() => {
                if (!hasResolved) {
                    hasResolved = true;
                    resolve(true);
                }
            }, (duration * 1000) + 200);

            try {
                BotVirtualCursor.moveToElement($el, duration, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try {
                            $el[0].click();
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

        const btnSap = document.getElementById('btn-sap');
        const btnNgua = document.getElementById('btn-ngua');
        if (!btnSap || btnSap.disabled) return;

        botIsBusy = true;
        busyTimestamp = Date.now();

        try {
            if (Math.random() < 0.3) sendChat('bet');

            // 1. Nhập mức cược
            const amounts = [50000, 100000, 200000, 500000];
            const chosenAmt = amounts[Math.floor(Math.random() * amounts.length)];
            $('#bet-amount').val(chosenAmt);

            // 2. Chọn mặt xu (50% Sấp, 50% Ngửa)
            const isHeads = Math.random() < 0.5;
            const targetBtn = isHeads ? btnSap : btnNgua;

            // 3. Rê chuột click nhanh trong 0.25s
            await fastMoveAndClick($(targetBtn), 0.25);

            // 4. Chờ lật xu (1s)
            await wait(1050);

            // 5. Đọc kết quả
            const isWin = $('.floating-win').length > 0;
            if (isWin) {
                if (Math.random() < 0.4) sendChat('win');
            } else {
                if (Math.random() < 0.3) sendChat('loss');
            }

            await wait(200);

        } catch (e) {
            console.error('[Bot 18 Turbo Error]', e);
        } finally {
            botIsBusy = false;
        }
    }

    // Nhịp điệu siêu tốc mỗi 1.4 giây
    setInterval(executeBotTurn, 1400);

    // Watchdog
    setInterval(() => {
        if (botIsBusy && Date.now() - busyTimestamp > 5000) {
            botIsBusy = false;
            $('.action-btn').prop('disabled', false);
        }
    }, 2000);

    console.log('[Bot 18 Turbo] Thánh Lật Kèo sẵn sàng xuất kích!');
})();
