/**
 * Bot AI dành riêng cho game Xổ số Mini (ID: 36)
 * - Tự động tạo 5 số ngẫu nhiên
 * - Tự động điền vào ô input
 * - Bấm nút Quay Số
 * - Đợi kết quả và lặp lại
 * - Tích hợp LiveStream Chat 24/7 theo chuẩn Casino Rule 5.3
 */

if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Thánh Soi Cầu Mini 🎯🎲");
    let lastKnownBalance = null;

    const CASINO_CHATS = {
        bet: [
            "Ra chiêu chốt bộ số may mắn {num}! Soi cầu cực chuẩn đón Jackpot GTLM! 🎯⚡",
            "Cầu số đang vào guồng, vào tiền tự tin rước tài lộc về nick! 💎🔥",
            "Khóa số đỉnh cao, tiếng này không húp thì tiếng nào húp! 🌟💰",
            "Dàn số tài lộc đã điểm! Bắn số đón đầu giải đặc biệt! 🚀💎"
        ],
        win: [
            "TRÚNG SỐ RỒI ANH EM ƠI! Húp trọn giải thưởng Xổ Số Mini, tiền đầy nick! 🏆🎉🤑",
            "Soi cầu chuẩn từng milimet! Gáy khét lẹt cả phòng live hôm nay! 👑💎💰",
            "Cơm gạo về nick! Thắng đậm ván giao lưu Xổ Số! 🌟🎉"
        ],
        loss: [
            "Lệch mất vài nhịp số, bay màu nhẹ ván này! Tiếng sau làm lại! 😅🔄",
            "Bốc hơi một chút, xốc lại tinh thần soi cầu phục thù! 💪🎯"
        ]
    };

    function sendChat(type, replaceObj = {}) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.bet;
            let msg = list[Math.floor(Math.random() * list.length)];
            for (let k in replaceObj) {
                msg = msg.replace(new RegExp(`\\{${k}\\}`, 'g'), replaceObj[k]);
            }
            BotChat.send(36, 'Thánh Soi Cầu Mini 🎯🎲', msg);
        }
    }

    function checkBalance() {
        const el = document.getElementById('userMoney') || document.querySelector('.money-text') || document.getElementById('balance');
        if (!el) return;
        const curBal = parseInt(el.innerText.replace(/\D/g, '')) || 0;
        if (lastKnownBalance !== null) {
            if (curBal > lastKnownBalance) {
                if (Math.random() < 0.6) sendChat('win');
            } else if (curBal < lastKnownBalance) {
                if (Math.random() < 0.4) sendChat('loss');
            }
        }
        lastKnownBalance = curBal;
    }
    
    function playLottery() {
        checkBalance();
        const inputField = document.getElementById('user-number');
        const submitBtn = document.getElementById('btn-submit');
        
        if (!inputField || !submitBtn || submitBtn.disabled) {
            setTimeout(playLottery, 2000);
            return;
        }

        // Sinh 5 số ngẫu nhiên
        let randomNum = Math.floor(Math.random() * 90000) + 10000;
        
        // Di chuyển chuột tới ô input
        BotVirtualCursor.moveToElement($(inputField), 1, 0, () => {
            setTimeout(() => {
                inputField.value = randomNum;
                inputField.dispatchEvent(new Event('input', { bubbles: true }));
                
                // Di chuyển tới nút Submit và click
                setTimeout(() => {
                    BotVirtualCursor.moveToElement($(submitBtn), 1, 0, () => {
                        setTimeout(() => {
                            BotVirtualCursor.simulateClick(() => {
                                try {
                                    var btn = document.getElementById('btn-submit');
                                    if (btn) {
                                        btn.click();
                                        if (Math.random() < 0.4) sendChat('bet', { num: randomNum });
                                    }
                                } catch(e){
                                    console.error("[Bot 36] Error clicking:", e);
                                }
                                // Chờ ván tiếp theo
                                setTimeout(playLottery, 5000 + Math.random() * 5000);
                            });
                        }, 500);
                    });
                }, 500);
            }, 500);
        });
    }

    // Bắt đầu vòng lặp đầu tiên sau 3s
    setTimeout(playLottery, 3000);
}
