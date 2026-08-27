if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Bot Streamer");
    let botIsBusy = false;

    let recentResults = [];
    let lastBalance = null;
    let intendedAutoCashout = 2.0;

    function updatePsychology() {
        const moneyText = document.getElementById('userMoney');
        if (!moneyText) return 0;
        const balance = parseInt(moneyText.innerText.replace(/\./g, '')) || 0;
        if (lastBalance !== null && balance !== lastBalance) {
            recentResults.push(balance > lastBalance);
            if (recentResults.length > 10) recentResults.shift();
        }
        lastBalance = balance;
        return balance;
    }

    function currentLoseStreak() {
        let streak = 0;
        for (let i = recentResults.length - 1; i >= 0; i--) {
            if (recentResults[i] === false) streak++;
            else break;
        }
        return streak;
    }

    function currentWinStreak() {
        let streak = 0;
        for (let i = recentResults.length - 1; i >= 0; i--) {
            if (recentResults[i] === true) streak++;
            else break;
        }
        return streak;
    }

    setInterval(() => {
        if (botIsBusy) return;

        const startBtn = document.getElementById('startBtn');
        const cashoutBtn = document.getElementById('cashoutBtn');
        const chips = Array.from(document.querySelectorAll('.btn-quick-bet'));
        const autoCashoutInput = document.getElementById('autoCashout');

        const balance = updatePsychology();
        const loseStreak = currentLoseStreak();
        const winStreak = currentWinStreak();
        let targetBtn = null;

        // Nếu game đang bay (cashoutBtn đang hiện)
        if (cashoutBtn && cashoutBtn.style.display !== 'none' && !cashoutBtn.disabled) {
            // Tâm lý: thua liên tục -> yếu tim hơn, rút sớm nhiều hơn để bảo toàn vốn
            // thắng liên tục -> tự tin hơn, ít rút sớm hơn, để mốc auto tự chạy
            let earlyCashoutChance = 0.15;
            if (loseStreak >= 3) earlyCashoutChance = 0.28;
            if (winStreak >= 3) earlyCashoutChance = 0.08;

            if (Math.random() < earlyCashoutChance) {
                targetBtn = cashoutBtn;
            }
        }
        // Nếu game đang chờ cược (startBtn đang hiện)
        else if (startBtn && startBtn.style.display !== 'none' && !startBtn.disabled) {
            // 1. Đổi mức tiền cược, tỷ lệ vốn co giãn theo tâm lý
            if (Math.random() < 0.3 && chips.length > 0) {
                const safeChips = chips.filter(b => b.innerText !== 'ALL IN');
                let suitableChips = safeChips;

                // Thua nhiều -> né chip to hơn một chút; thắng nhiều -> dám chơi chip to hơn
                let richThreshold = 10000000;
                let poorThreshold = 500000;
                if (loseStreak >= 4) richThreshold = 15000000;
                if (winStreak >= 4) poorThreshold = 300000;

                if (balance < poorThreshold) {
                    suitableChips = safeChips.filter(c => {
                        let val = parseInt(c.innerText.replace(/\D/g, '')) * (c.innerText.includes('M') ? 1000000 : 1000);
                        return val <= 50000;
                    });
                } else if (balance > richThreshold) {
                    suitableChips = safeChips.filter(c => {
                        let val = parseInt(c.innerText.replace(/\D/g, '')) * (c.innerText.includes('M') ? 1000000 : 1000);
                        return val >= 100000;
                    });
                }
                if (suitableChips.length === 0) suitableChips = safeChips;

                const allInChip = chips.find(b => b.innerText === 'ALL IN');
                const allInChance = loseStreak >= 5 ? 0.03 : 0.01;
                if (Math.random() < allInChance && balance > 0 && balance < 2000000 && allInChip) {
                    targetBtn = allInChip;
                } else {
                    targetBtn = suitableChips[Math.floor(Math.random() * suitableChips.length)];
                }
            }
            // 2. Đổi mốc tự động rút tiền (Auto Cashout), phân bố lệch theo tâm lý
            else if (Math.random() < 0.4 && autoCashoutInput) {
                let rand = Math.random();
                if (loseStreak >= 3) {
                    // Đang thua: thiên về ăn non để chắc ăn, ít liều mốc cao
                    if (rand < 0.65) intendedAutoCashout = 1.1 + Math.random() * 0.6; // 1.1x -> 1.7x
                    else if (rand < 0.9) intendedAutoCashout = 1.7 + Math.random() * 1.3; // 1.7x -> 3.0x
                    else intendedAutoCashout = 3.0 + Math.random() * 3.0; // 3.0x -> 6.0x
                } else if (winStreak >= 3) {
                    // Đang thắng: dám đặt mốc tham lam hơn
                    if (rand < 0.35) intendedAutoCashout = 1.1 + Math.random() * 0.8;
                    else if (rand < 0.65) intendedAutoCashout = 2.0 + Math.random() * 2.0;
                    else intendedAutoCashout = 4.0 + Math.random() * 8.0; // có thể lên tới 12x
                } else {
                    // Bình thường
                    if (rand < 0.5) intendedAutoCashout = 1.1 + Math.random() * 0.8;
                    else if (rand < 0.8) intendedAutoCashout = 2.0 + Math.random() * 2.0;
                    else intendedAutoCashout = 4.0 + Math.random() * 6.0;
                }

                autoCashoutInput.value = intendedAutoCashout.toFixed(2);
                if (typeof updatePotential === 'function') updatePotential();

                const quickMultipliers = Array.from(document.querySelectorAll('button[onclick*="autoCashout"]'));
                if (Math.random() < 0.3 && quickMultipliers.length > 0) {
                    targetBtn = quickMultipliers[Math.floor(Math.random() * quickMultipliers.length)];
                }
            }
            // 3. Nếu không đổi gì thì bấm CẤT CÁNH
            else {
                targetBtn = startBtn;
            }
        }

        if (targetBtn) {
            botIsBusy = true;
            // Thua liên tục -> phản xạ nhanh/nóng vội hơn một chút
            const baseDelay = loseStreak >= 3 ? 150 : 200;
            const delayRange = loseStreak >= 3 ? 200 : 300;

            BotVirtualCursor.moveToElement($(targetBtn), 1, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { targetBtn.click(); } catch (e) { }
                        botIsBusy = false;
                    });
                }, baseDelay + Math.random() * delayRange);
            });
        }
    }, 800 + Math.random() * 1000);
}