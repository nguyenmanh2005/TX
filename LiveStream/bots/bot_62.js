/**
 * 🤖 Bot Streamer Bàn 62: Thợ Mỏ Siêu Hạng ⛏️💎 (Mining Tycoon)
 * - Tự động thu hoạch mỏ khoáng sản tích lũy
 * - Tích hợp chuột ảo Turbo & Bộ phát ngôn Casino Rule 5.3 chuẩn chỉnh
 */
(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') return;
    BotVirtualCursor.init("Thợ Mỏ Siêu Hạng ⛏️💎");

    const CASINO_CHATS = {
        mine: [
            "Đào trúng mạch khoáng sản VIP! Ra chiêu cuốc vàng khai thác ngập tràn GTLM! ⛏️💎🔥",
            "Mỏ kim cương hôm nay cho sản lượng cực đỉnh! Tiền tươi thóc thật về nick rủng rỉnh! 🌟💰",
            "Trận địa mỏ khoáng sản bùng nổ, anh em cùng tôi thu hoạch lộc lá đón bão nhé! 🚀💎",
            "Khai thác ngày đêm, cứ đầy bồn chứa là húp trọn vẹn GTLM! 🛡️✨"
        ],
        claim: [
            "HÚP TRỌN MẺ VÀNG KHOÁNG SẢN! GTLM cộng dồn ngập nick! 🏆⛏️🤑",
            "Gáy khét lẹt cả khu mỏ hôm nay! Thu hoạch kim cương thành công rực rỡ! 👑💎💰",
            "Vàng ròng nhảy ting ting vào tài khoản! Đẳng cấp thợ mỏ thần tài! 🌟🎉"
        ]
    };

    function sendChat(type) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.mine;
            const msg = list[Math.floor(Math.random() * list.length)];
            BotChat.send(62, 'Thợ Mỏ Siêu Hạng ⛏️💎', msg);
        }
    }

    let isBusy = false;

    function runMiningTurn() {
        if (isBusy) return;
        const btn = document.getElementById('btnClaimAll');
        if (!btn || btn.disabled) return;

        isBusy = true;
        if (Math.random() < 0.4) sendChat('mine');

        BotVirtualCursor.moveToElement($(btn), 0.5, 0, () => {
            setTimeout(() => {
                BotVirtualCursor.simulateClick(() => {
                    $.get('live_62.php?action=claim_all', function (res) {
                        if (res && res.success) {
                            $('#balance-val').text(res.balance);
                            sendChat('claim');
                            const nextVal = Math.floor(Math.random() * 150000 + 80000);
                            $('#accumulatedDisplay').text('+' + nextVal.toLocaleString('vi-VN'));
                        }
                        isBusy = false;
                    }).fail(function () {
                        isBusy = false;
                    });
                });
            }, 300);
        });
    }

    setInterval(() => {
        if (!isBusy) runMiningTurn();
    }, 4500);

    setTimeout(() => {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(62, 'Thợ Mỏ Siêu Hạng ⛏️💎', 'Chào mừng anh em đến với Mỏ Khoáng Bàn 62! Cùng tôi vác cuốc vàng húp trọn kim cương GTLM nhé! ⛏️💎🚀');
        }
        runMiningTurn();
    }, 1500);
})();
