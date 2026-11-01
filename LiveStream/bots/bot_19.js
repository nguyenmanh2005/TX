if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Thần Tài Soi Cầu 🎟️");

    const CASINO_CHATS = {
        bet: [
            "Ra chiêu mua vé số cộng đồng! Rước Thần Tài gõ cửa nick hôm nay! 🎟️✨",
            "Cầu số đang vào nhịp cực nét! Anh em cùng chọn số húp Jackpot bạc tỷ GTLM! 💎🔥",
            "Vào tiền bao trọn hàng số may mắn! Khí thế ngút trời! 🌟💰",
            "Đoán số như thần, tiếng này không nổ Jackpot thì hơi phí! 🎯💎"
        ],
        spin: [
            "Vòng quay may mắn kích hoạt! Ra chiêu giật thưởng x2 x5 GTLM! 🎡⚡",
            "Quay thưởng vòng xoay rực rỡ! Lộc lá cộng dồn vào nick! 🌟🎉"
        ],
        win: [
            "HÚP TRỌN GIẢI ĐẶC BIỆT XỔ SỐ! Tiền về nick như thác lũ anh em ơi! 🏆🎉🤑",
            "Nổ giải thưởng lớn rồi! Thần Tài gáy khét cả bàn live hôm nay! 👑💎💰",
            "Trúng đậm vé số cộng đồng! Lộc lá tràn trề cả sảnh! 💎✨"
        ],
        loss: [
            "Trượt mất 1 số cuối đáng tiếc, bay màu ván này! Vé sau tất tay húp bù! 😅💔",
            "Bốc hơi nhẹ, còn thở là còn nuôi số bẻ cầu! 💪🎟️"
        ]
    };

    function sendChat(type) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.bet;
            const msg = list[Math.floor(Math.random() * list.length)];
            BotChat.send(19, 'Thần Tài Soi Cầu 🎟️', msg);
        }
    }

    setInterval(() => {
        const allBtns = Array.from(document.querySelectorAll("button, .btn-bet, .chip, .spin-btn, #btnSpin, .bet-button, .card, .btn-primary, .btn-success, input[type='button'], input[type='submit'], .num-btn"));
        const btns = allBtns.filter(b => {
            if (b.offsetParent === null || b.disabled) return false;
            const txt = (b.innerText || b.value || "").toLowerCase();
            const cls = (b.className || "").toLowerCase();
            const id = (b.id || "").toLowerCase();

            // Exclude common navigation/help buttons
            if (txt.includes("hướng dẫn") || txt.includes("trang chủ") || txt.includes("nạp") || txt.includes("rút") || txt.includes("lịch sử") || txt.includes("quay lại") || txt.includes("thoát")) return false;
            if (cls.includes("back") || cls.includes("help") || cls.includes("guide") || cls.includes("close") || cls.includes("swal") || cls.includes("nav")) return false;
            if (id.includes("guide") || id.includes("back") || id.includes("close") || id.includes("nav")) return false;

            return true;
        });

        if (btns.length > 0) {
            const btn = btns[Math.floor(Math.random() * btns.length)];
            BotVirtualCursor.moveToElement($(btn), 1, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { 
                            btn.click(); 
                            const btnTxt = (btn.innerText || "").toLowerCase();
                            if (btnTxt.includes('quay') || btn.id.includes('spin')) {
                                if (Math.random() < 0.5) sendChat('spin');
                            } else if (Math.random() < 0.3) {
                                sendChat('bet');
                            }
                        } catch (e) { }
                    });
                }, 500);
            });
        }
    }, 3000 + Math.random() * 4000);
}
