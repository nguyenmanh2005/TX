/**
 * 🐓 Bot Streamer Bàn 4: Chủ Sới Gà 04 (Đại Chiến Thần Kê) - CHẾ ĐỘ SIÊU TỐC (TURBO 24/7)
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') return;

    BotVirtualCursor.init('Chủ Sới Gà 04 🐓');

    let botIsBusy = false;
    let busyTimestamp = 0;

    const CASINO_CHATS = {
        bet: [
            "Meron hôm nay chân cựa bén ngót, vào mạnh tay cửa Đỏ nào! 🐓🔥",
            "Wala phong độ đang lên cao, tiếng này kết cửa Xanh lấy lộc! 🔵⚡",
            "Kèo này cân tài cân sức, vào nhẹ cửa Hòa x8 thử vận may! 🤝✨",
            "Sới gà hôm nay rực lửa! Tiếng này tôi kết Meron, bơi vào bờ nào! 🌊💰"
        ],
        fighting: [
            "Mổ nó đi Meron! Tung đòn sấm sét nào! 💥🐓",
            "Wala né đòn quá ảo diệu! Phản kích đi nào! ⚡🔥",
            "Đòn cựa hiểm hóc quá! Nín thở nào! 😱🥊"
        ],
        win: [
            "Húp trọn kèo sới gà rồi anh em ơi! Quá đã! 🏆🎉",
            "Tuyệt đỉnh thần kê! Gà chiến quá dũng mãnh, ăn đậm ván này! 🤑💰",
            "Húp trọn ván này! Thắng liên tiếp rồi anh em! 👑✨"
        ],
        loss: [
            "Bị dính cựa hiểm, ván sau tất tay phục thù ngay! 🔄💪",
            "Còn thở là còn gỡ, làm lại tiếng sau lấy lại cả vốn lẫn lãi! ⚔️🔥"
        ]
    };

    function sendChat(type) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.bet;
            const msg = list[Math.floor(Math.random() * list.length)];
            BotChat.send(4, 'bot_4', msg);
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
        if (botIsBusy || window.isPlaying) return;

        botIsBusy = true;
        busyTimestamp = Date.now();

        try {
            if (Math.random() < 0.3) sendChat('bet');

            // 1. Nhập mức cược nhanh
            const amounts = [50000, 100000, 200000, 500000];
            const chosenAmt = amounts[Math.floor(Math.random() * amounts.length)];
            $('#bet-amount').val(chosenAmt);

            // 2. Chọn cửa cược: Meron (47%), Wala (47%), Draw (6%)
            const rand = Math.random() * 100;
            let targetBtn = (rand < 47) ? '.btn-meron' : ((rand < 94) ? '.btn-wala' : '.btn-draw');

            const $btn = $(targetBtn);
            if ($btn.length) {
                await fastMoveAndClick($btn, 0.25);
            }

            // 3. Trong khi gà đang chọi (khoảng 1.2s)
            await wait(600);
            if (Math.random() < 0.3) sendChat('fighting');

            await wait(800);

            // 4. Đọc kết quả
            const statusText = $('#status').text().toUpperCase();
            if (statusText.includes('HÚP') || statusText.includes('THẮNG')) {
                if (Math.random() < 0.4) sendChat('win');
            } else {
                if (Math.random() < 0.3) sendChat('loss');
            }

            await wait(200);

        } catch (e) {
            console.error('[Bot 4 Turbo Error]', e);
        } finally {
            botIsBusy = false;
        }
    }

    // Nhịp điệu siêu tốc mỗi 1.5 giây
    setInterval(executeBotTurn, 1500);

    // Watchdog
    setInterval(() => {
        if (botIsBusy && Date.now() - busyTimestamp > 6000) {
            botIsBusy = false;
            window.isPlaying = false;
        }
    }, 2000);

    console.log('[Bot 4 Turbo] Sẵn sàng xuất kích thần tốc!');
})();
