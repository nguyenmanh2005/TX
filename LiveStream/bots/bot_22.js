if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Bậc Thầy Fan-Tan 🀄");
    let lastKnownBalance = null;

    const CASINO_CHATS = {
        bet: [
            "Trận địa Fan-Tan truyền thống! Ra chiêu bốc hạt chia 4 đón lộc GTLM! 🀄⚡",
            "Cầu nút đang bệt cửa đẹp, vào tiền bắt trọn hạt dư cuối cùng! 💎🔥",
            "Ra chiêu dứt khoát vào cửa số đẹp, anh em cùng đếm hạt nào! 🌟💰",
            "Đếm hạt như thần, tiếng này cửa sáng vào tiền không ngần ngại! 🎯🀄"
        ],
        win: [
            "ĐÚNG NÚT DƯ RỒI! Húp trọn ván Fan-Tan, GTLM về nick ngập tràn! 🏆🀄🤑",
            "Bậc thầy đếm hạt ra tay! Gáy khét lẹt cả sảnh hôm nay! 👑💎💰",
            "Cơm gạo về nick! Thắng đậm ván giao lưu Fan-Tan! 🌟🎉"
        ],
        loss: [
            "Dư lệch mất 1 hạt, bay màu ván này rồi! Ván sau phục thù! 😅🔄",
            "Bốc hơi chút ít, xốc lại tinh thần đếm hạt ván mới! 💪🀄"
        ]
    };

    function sendChat(type) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.bet;
            const msg = list[Math.floor(Math.random() * list.length)];
            BotChat.send(22, 'Bậc Thầy Fan-Tan 🀄', msg);
        }
    }

    function checkBalance() {
        const el = document.getElementById('userMoney') || document.getElementById('balance');
        if (!el) return;
        const curBal = parseInt(el.innerText.replace(/\D/g, '')) || 0;
        if (lastKnownBalance !== null) {
            if (curBal > lastKnownBalance) {
                if (Math.random() < 0.6) sendChat('win');
            } else if (curBal < lastKnownBalance) {
                if (Math.random() < 0.5) sendChat('loss');
            }
        }
        lastKnownBalance = curBal;
    }

    // Lặp mỗi 2-4 giây
    setInterval(() => {
        checkBalance();
        const playBtn = document.getElementById("play-btn");
        if (!playBtn || playBtn.disabled || playBtn.offsetParent === null) return;

        let totalBet = (parseInt(document.getElementById('bet-1')?.value) || 0) +
            (parseInt(document.getElementById('bet-2')?.value) || 0) +
            (parseInt(document.getElementById('bet-3')?.value) || 0) +
            (parseInt(document.getElementById('bet-4')?.value) || 0);

        // Cơ hội 80% giữ nguyên cược, chỉ bấm nút Chơi
        if (Math.random() < 0.80 && totalBet > 0) {
            BotVirtualCursor.moveToElement($(playBtn), 1, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { 
                            playBtn.click(); 
                            if (Math.random() < 0.35) sendChat('bet');
                        } catch (e) { }
                    });
                }, 500);
            });
            return;
        }

        // 20% Cơ hội đi đổi GTLM cược
        // 1. Chọn ngẫu nhiên cửa cược (1, 2, 3, 4)
        const betBoxes = Array.from(document.querySelectorAll('.bet-box'));
        if (betBoxes.length > 0) {
            const randomBox = betBoxes[Math.floor(Math.random() * betBoxes.length)];

            BotVirtualCursor.moveToElement($(randomBox), 1, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { randomBox.click(); } catch (e) { }

                        // 2. Chọn ngẫu nhiên phỉnh cược (tránh nút MAX cuối cùng)
                        setTimeout(() => {
                            const quickBtns = Array.from(document.querySelectorAll('.quick-btn'));
                            if (quickBtns.length > 0) quickBtns.pop();

                            if (quickBtns.length > 0) {
                                const randomChip = quickBtns[Math.floor(Math.random() * quickBtns.length)];
                                BotVirtualCursor.moveToElement($(randomChip), 1, 0, () => {
                                    setTimeout(() => {
                                        BotVirtualCursor.simulateClick(() => {
                                            try { randomChip.click(); } catch (e) { }

                                            // 3. Bấm Play
                                            setTimeout(() => {
                                                BotVirtualCursor.moveToElement($(playBtn), 1, 0, () => {
                                                    setTimeout(() => {
                                                        BotVirtualCursor.simulateClick(() => {
                                                            try { 
                                                                playBtn.click(); 
                                                                if (Math.random() < 0.35) sendChat('bet');
                                                            } catch (e) { }
                                                        });
                                                    }, 300);
                                                });
                                            }, 500);
                                        });
                                    }, 400);
                                });
                            }
                        }, 400);
                    });
                }, 300);
            });
        }
    }, 2500 + Math.random() * 2000);
}
