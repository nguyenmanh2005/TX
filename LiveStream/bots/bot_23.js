if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Bot Streamer");

    // Lặp mỗi 2-4 giây
    setInterval(() => {
        const playBtn = document.getElementById("play-btn");
        if (!playBtn || playBtn.disabled || playBtn.offsetParent === null) return;

        let totalBet = (parseInt(document.getElementById('bet-1')?.value) || 0) +
                       (parseInt(document.getElementById('bet-2')?.value) || 0) +
                       (parseInt(document.getElementById('bet-3')?.value) || 0) +
                       (parseInt(document.getElementById('bet-4')?.value) || 0);

        // Cơ hội 80% giữ nguyên cược, chỉ bấm nút Chơi
        // Nhưng nếu chưa có cược (totalBet == 0) thì BẮT BUỘC phải đi đổi tiền cược
        if (Math.random() < 0.80 && totalBet > 0) {
            BotVirtualCursor.moveToElement($(playBtn), 1, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { playBtn.click(); } catch(e){}
                    });
                }, 500);
            });
            return;
        }

        // 20% Cơ hội đi đổi tiền cược
        // 1. Chọn ngẫu nhiên cửa cược (1, 2, 3, 4)
        const betBoxes = Array.from(document.querySelectorAll('.bet-box'));
        if (betBoxes.length > 0) {
            const randomBox = betBoxes[Math.floor(Math.random() * betBoxes.length)];
            
            BotVirtualCursor.moveToElement($(randomBox), 1, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { randomBox.click(); } catch(e){}
                        
                        // 2. Chọn ngẫu nhiên phỉnh cược (tránh nút MAX cuối cùng)
                        setTimeout(() => {
                            const quickBtns = Array.from(document.querySelectorAll('.quick-btn'));
                            // Bỏ nút MAX (thường là nút cuối cùng)
                            if (quickBtns.length > 0) quickBtns.pop();
                            
                            if (quickBtns.length > 0) {
                                const randomChip = quickBtns[Math.floor(Math.random() * quickBtns.length)];
                                BotVirtualCursor.moveToElement($(randomChip), 1, 0, () => {
                                    setTimeout(() => {
                                        BotVirtualCursor.simulateClick(() => {
                                            try { randomChip.click(); } catch(e){}
                                            
                                            // 3. Bấm Đua/Play
                                            setTimeout(() => {
                                                BotVirtualCursor.moveToElement($(playBtn), 1, 0, () => {
                                                    setTimeout(() => {
                                                        BotVirtualCursor.simulateClick(() => {
                                                            try { playBtn.click(); } catch(e){}
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
    }, 2000 + Math.random() * 2000);
}
