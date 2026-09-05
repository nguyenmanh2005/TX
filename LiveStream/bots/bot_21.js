if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Bot Streamer");

    function runBotDice() {
        const rollBtn = document.getElementById('rollBtn');
        if (!rollBtn || rollBtn.disabled || typeof isRolling !== 'undefined' && isRolling) {
            setTimeout(runBotDice, 1500);
            return;
        }

        // Bước 1: Quyết định chọn chế độ (Lớn hơn / Nhỏ hơn) - 40% cơ hội đổi chế độ để sếp dễ thấy
        if (Math.random() < 0.40) {
            const modeBtns = Array.from(document.querySelectorAll('.mode-btn'));
            if (modeBtns.length > 0) {
                const targetMode = modeBtns[Math.floor(Math.random() * modeBtns.length)];
                if (!targetMode.classList.contains('active')) {
                    BotVirtualCursor.moveToElement($(targetMode), 0.3, 0, () => {
                        BotVirtualCursor.simulateClick(() => { try { targetMode.click(); } catch (e) { } });
                        setTimeout(chooseBet, 400);
                    });
                    return;
                }
            }
        }

        // Nếu không đổi chế độ, đi thẳng tới chọn cược
        chooseBet();
    }

    function chooseBet() {
        // 50% cơ hội đổi GTLM cược, 50% giữ nguyên và click xúc xắc luôn
        if (Math.random() < 0.50) {
            clickRoll();
            return;
        }

        const betBtns = Array.from(document.querySelectorAll('.btn-quick-bet')).filter(b => !b.innerText.includes('ALL IN'));
        if (betBtns.length > 0) {
            // Nghiêng về các mức cược thấp (ưu tiên đầu mảng)
            const maxIdx = Math.min(betBtns.length, 5);
            let targetBet = betBtns[Math.floor(Math.random() * maxIdx)];

            BotVirtualCursor.moveToElement($(targetBet), 0.4, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => { try { targetBet.click(); } catch (e) { } });
                    setTimeout(clickRoll, 400);
                }, 200);
            });
        } else {
            clickRoll();
        }
    }

    function clickRoll() {
        const rollBtn = document.getElementById('rollBtn');
        if (rollBtn) {
            BotVirtualCursor.moveToElement($(rollBtn), 0.5, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => { try { rollBtn.click(); } catch (e) { } });
                    // Đợi xúc xắc lăn xong rồi mới chơi tiếp
                    setTimeout(runBotDice, 3500 + Math.random() * 1500);
                }, 200);
            });
        }
    }

    // Bắt đầu chu trình
    setTimeout(runBotDice, 2000);
}
