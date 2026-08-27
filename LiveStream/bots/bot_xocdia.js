if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Bot Streamer");
    let botIsBusy = false;

    // ---- Trạng thái "tâm lý" của bot ----
    let recentResults = []; // true = thắng, false = thua, giữ tối đa 10 ván
    let recentOptionPicks = []; // giữ tối đa 5 lựa chọn gần nhất để tránh lặp
    let lastBalance = null;

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
        if (botIsBusy || typeof isSyncing !== 'undefined' && isSyncing) return;

        const playBtn = document.getElementById('pulseBtn');
        const chips = Array.from(document.querySelectorAll('.btn-quick-bet'));
        const options = Array.from(document.querySelectorAll('.sync-option'));
        const balance = updatePsychology();

        let targetBtn = null;
        let currentTotalBet = 0;
        let numOptionsBet = 0;
        if (typeof myCharges !== 'undefined') {
            for (let c in myCharges) {
                if (myCharges[c] > 0) {
                    currentTotalBet += myCharges[c];
                    numOptionsBet++;
                }
            }
        }

        const loseStreak = currentLoseStreak();
        const winStreak = currentWinStreak();

        // ---- Số cửa sẽ đánh, có yếu tố tâm lý ----
        let targetOptionCount = 1 + Math.floor(Math.random() * 2);
        if (loseStreak >= 3) {
            targetOptionCount = Math.random() < 0.6 ? 1 : Math.min(3, targetOptionCount + 1);
        } else if (winStreak >= 3) {
            targetOptionCount = Math.min(3, targetOptionCount + 1);
        }

        let shouldShake = numOptionsBet >= targetOptionCount;

        let maxBetRatio = 1 / 3;
        if (loseStreak >= 4) maxBetRatio = 1 / 5;
        if (winStreak >= 4) maxBetRatio = 1 / 2;

        if (currentTotalBet > 0 && currentTotalBet >= balance * maxBetRatio) {
            shouldShake = true;
        }

        if (currentTotalBet === 0 || !shouldShake) {
            if (Math.random() < 0.3 && chips.length > 0) {
                const safeChips = chips.filter(b => b.innerText !== 'ALL IN');
                const allInChip = chips.find(b => b.innerText === 'ALL IN');

                let suitableChips = safeChips;
                if (balance < 500000) {
                    suitableChips = safeChips.filter(c => {
                        let valStr = c.innerText.replace(/\D/g, '');
                        let mult = c.innerText.includes('M') ? 1000000 : 1000;
                        return (parseInt(valStr) * mult) <= 50000;
                    });
                } else if (balance > 10000000) {
                    suitableChips = safeChips.filter(c => {
                        let valStr = c.innerText.replace(/\D/g, '');
                        let mult = c.innerText.includes('M') ? 1000000 : 1000;
                        return (parseInt(valStr) * mult) >= 100000;
                    });
                }
                if (suitableChips.length === 0) suitableChips = safeChips;

                const allInChance = loseStreak >= 5 ? 0.03 : 0.01;
                if (Math.random() < allInChance && balance > 0 && balance < 2000000 && allInChip) {
                    targetBtn = allInChip;
                } else {
                    targetBtn = suitableChips[Math.floor(Math.random() * suitableChips.length)];
                }
            } else if (options.length > 0) {
                let unbetOptions = options.filter(a => !myCharges[a.getAttribute('data-choice')]);
                let betOptions = options.filter(a => myCharges[a.getAttribute('data-choice')]);

                let mainOptions = options.filter(a =>
                    a.getAttribute('data-choice') === 'Stable' || a.getAttribute('data-choice') === 'Volatile'
                );
                let sideOptions = options.filter(a =>
                    a.getAttribute('data-choice') !== 'Stable' && a.getAttribute('data-choice') !== 'Volatile'
                );

                // Tỷ lệ ưu tiên cửa chính co giãn theo tâm lý:
                // thua liên tục -> bám cửa chính an toàn hơn; thắng liên tục -> dám thử cửa phụ nhiều hơn
                let mainPreference = 0.8;
                if (loseStreak >= 3) mainPreference = 0.9;
                if (winStreak >= 3) mainPreference = 0.65;

                if (Math.random() < mainPreference && mainOptions.length > 0) {
                    let unbetMain = mainOptions.filter(a => !myCharges[a.getAttribute('data-choice')]);
                    // Né lặp lại đúng cửa vừa đánh gần đây (nếu còn lựa chọn khác)
                    let freshMain = unbetMain.filter(
                        a => !recentOptionPicks.includes(a.getAttribute('data-choice'))
                    );
                    if (freshMain.length > 0) unbetMain = freshMain;

                    if (unbetMain.length > 0) {
                        targetBtn = unbetMain[Math.floor(Math.random() * unbetMain.length)];
                    } else {
                        targetBtn = mainOptions[Math.floor(Math.random() * mainOptions.length)];
                    }
                } else {
                    let freshUnbet = unbetOptions.filter(
                        a => !recentOptionPicks.includes(a.getAttribute('data-choice'))
                    );
                    if (freshUnbet.length > 0) unbetOptions = freshUnbet;

                    if (unbetOptions.length > 0 && Math.random() < 0.7) {
                        targetBtn = unbetOptions[Math.floor(Math.random() * unbetOptions.length)];
                    } else if (betOptions.length > 0) {
                        targetBtn = betOptions[Math.floor(Math.random() * betOptions.length)];
                    } else {
                        targetBtn = options[Math.floor(Math.random() * options.length)];
                    }
                }

                if (targetBtn) {
                    const choiceName = targetBtn.getAttribute('data-choice');
                    recentOptionPicks.push(choiceName);
                    if (recentOptionPicks.length > 5) recentOptionPicks.shift();
                }
            }
        } else {
            targetBtn = playBtn;
        }

        if (!targetBtn && currentTotalBet > 0) targetBtn = playBtn;
        else if (!targetBtn) targetBtn = options[Math.floor(Math.random() * options.length)];

        if (targetBtn) {
            botIsBusy = true;
            const baseDelay = loseStreak >= 3 ? 100 : 200;
            const delayRange = loseStreak >= 3 ? 150 : 300;

            BotVirtualCursor.moveToElement($(targetBtn), 1, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { targetBtn.click(); } catch (e) { }
                        botIsBusy = false;
                    });
                }, baseDelay + Math.random() * delayRange);
            });
        }
    }, 600 + Math.random() * 800);
}