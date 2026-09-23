/**
 * 🃏 Bot Streamer Bàn 25: Bậc Thầy Gacha 25 (Gacha Thẻ Nhân Phẩm) - CHẾ ĐỘ SIÊU TỐC (TURBO 24/7)
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') return;

    BotVirtualCursor.init('Bậc Thầy Gacha 25 🃏');

    let botIsBusy = false;
    let busyTimestamp = 0;

    const CASINO_CHATS = {
        pull: [
            "Cầu nhân phẩm hôm nay cực vượng, mở thẻ SSR nào! 🃏✨",
            "Tập trung tinh hoa vũ trụ, bấm thẻ kiếm lộc khủng! 🌟🔥",
            "Rút thẻ phát tài, tiếng này mùi SSR nồng nặc luôn! 💎🚀",
            "Tay đang đỏ au, kéo mạnh xem nổ thẻ nào! 👑✨"
        ],
        ssr: [
            "Úi xời nổ thẻ VIP rồi anh em ơi! Húp ngập mồm triệu GTLM! 🏆🎉",
            "Nhân phẩm vô cực! Thẻ hiếm về tay, phát tài rồi! 🤑💰",
            "Chói lóa cả màn hình! Đẳng cấp streamer gacha là đây! 🔥✨"
        ],
        normal: [
            "Thẻ này tích vận may, nhát sau bảo đảm nổ cực đại! 😅💪",
            "Khởi động nhẹ nhàng, còn thở là còn rút tiếp! 🔄💎"
        ]
    };

    function sendChat(type) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.pull;
            const msg = list[Math.floor(Math.random() * list.length)];
            BotChat.send(25, 'bot_25', msg);
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

        const btn = document.getElementById('btn-pull');
        if (!btn || btn.disabled) return;

        botIsBusy = true;
        busyTimestamp = Date.now();

        try {
            if (Math.random() < 0.3) sendChat('pull');

            // Rê chuột ảo tới nút Rút Thẻ và click nhanh
            await fastMoveAndClick($(btn), 0.25);

            // Chờ hoạt ảnh lật thẻ (khoảng 0.9s)
            await wait(900);

            // Kiểm tra phẩm cấp thẻ nhận được
            const rarityBadge = $('#res-rarity').text().toUpperCase();
            if (rarityBadge.includes('SSR') || rarityBadge.includes('UR') || rarityBadge.includes('SR')) {
                if (Math.random() < 0.5) sendChat('ssr');
            } else {
                if (Math.random() < 0.25) sendChat('normal');
            }

            await wait(200);

        } catch (e) {
            console.error('[Bot 25 Turbo Error]', e);
        } finally {
            botIsBusy = false;
        }
    }

    // Nhịp điệu siêu tốc mỗi 1.4 giây
    setInterval(executeBotTurn, 1400);

    // Watchdog
    setInterval(() => {
        if (botIsBusy && Date.now() - busyTimestamp > 4000) {
            botIsBusy = false;
            $('#btn-pull').prop('disabled', false);
        }
    }, 2000);

    console.log('[Bot 25 Turbo] Bậc Thầy Gacha đã sẵn sàng xuất kích thần tốc!');
})();
