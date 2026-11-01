if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Chiến Thần Lượng Tử 🎲⚡");
    let lastKnownBalance = null;

    const CASINO_CHATS = {
        roll: [
            "Kích hoạt xúc xắc lượng tử! Ra chiêu điều hướng xác suất hạt siêu vi! 🎲⚡",
            "Dao động lượng tử cực đẹp! Vào tiền cửa sáng húp GTLM nào anh em! 🌌💎",
            "Cân bằng xác suất hoàn hảo, gieo xúc xắc bão lộc về nick! 🚀💰",
            "Hạt vi mô đang hội tụ năng lượng! Ra chiêu đón bão GTLM! ⚡💎"
        ],
        win: [
            "HÚP TRỌN XÚC XẮC LƯỢNG TỬ! Xác suất chuẩn từng hạt vi mô, GTLM về ngập nick! 🏆⚡🤑",
            "Gáy khét lẹt cả bàn Quantum Dice! Thần xúc xắc hiển linh! 👑🎲💰",
            "Lượng tử đồng pha! Tiền nổ rực rỡ cộng thẳng vào tài khoản! 💎🎉"
        ],
        loss: [
            "Xung hạt bị nhiễu làm bay màu tiếng lắc này! Không sao, ván sau bẻ lại quỹ đạo! 😅🔄",
            "Bốc hơi nhẹ, còn thở là còn nạp xung gỡ lại! 💪⚡"
        ]
    };

    function sendChat(type) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.roll;
            const msg = list[Math.floor(Math.random() * list.length)];
            BotChat.send(20, 'Chiến Thần Lượng Tử 🎲⚡', msg);
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

    function runBotDice() {
        checkBalance();
        const rollBtn = document.getElementById('rollBtn');
        if (!rollBtn || rollBtn.disabled || typeof isRolling !== 'undefined' && isRolling) {
            setTimeout(runBotDice, 1500);
            return;
        }

        // Bước 1: Quyết định chọn chế độ (Lớn hơn / Nhỏ hơn)
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
        if (Math.random() < 0.50) {
            clickRoll();
            return;
        }

        const betBtns = Array.from(document.querySelectorAll('.btn-quick-bet')).filter(b => !b.innerText.includes('ALL IN'));
        if (betBtns.length > 0) {
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
                    BotVirtualCursor.simulateClick(() => { 
                        try { 
                            rollBtn.click(); 
                            if (Math.random() < 0.35) sendChat('roll');
                        } catch (e) { } 
                    });
                    setTimeout(runBotDice, 3500 + Math.random() * 1500);
                }, 200);
            });
        }
    }

    // Bắt đầu chu trình
    setTimeout(runBotDice, 2000);
}
