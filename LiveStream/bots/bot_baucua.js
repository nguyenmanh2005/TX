if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Bot Streamer");
    let botIsBusy = false;

    // ---- Trạng thái "tâm lý" của bot ----
    let recentResults = []; // true = thắng, false = thua, giữ tối đa 10 ván
    let recentAnimalPicks = []; // giữ tối đa 5 con thú gần nhất để tránh lặp
    let lastBalance = null;

    function updatePsychology() {
        const moneyText = document.getElementById('userMoney');
        if (!moneyText) return;
        const balance = parseInt(moneyText.innerText.replace(/\./g, '')) || 0;
        if (lastBalance !== null && balance !== lastBalance) {
            recentResults.push(balance > lastBalance);
            if (recentResults.length > 10) recentResults.shift();
        }
        lastBalance = balance;
        return balance;
    }

    // Chuỗi thua liên tiếp gần nhất
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
        if (botIsBusy || typeof isRolling !== 'undefined' && isRolling) return;

        const playBtn = document.getElementById('playBtn');
        const chips = Array.from(document.querySelectorAll('.btn-quick-bet'));
        const animals = Array.from(document.querySelectorAll('.animal-tile'));
        const balance = updatePsychology();

        let targetBtn = null;
        let currentTotalBet = 0;
        let numAnimalsBet = 0;
        if (typeof myBets !== 'undefined') {
            for (let a in myBets) {
                if (myBets[a] > 0) {
                    currentTotalBet += myBets[a];
                    numAnimalsBet++;
                }
            }
        }

        const loseStreak = currentLoseStreak();
        const winStreak = currentWinStreak();

        // ---- Quyết định số con vật sẽ đánh, có yếu tố "tâm lý" ----
        let targetAnimalCount = 1 + Math.floor(Math.random() * 3);
        if (loseStreak >= 3) {
            // Thua liên tục: 60% co lại đánh ít (thận trọng), 40% gỡ (đánh nhiều hơn)
            targetAnimalCount = Math.random() < 0.6
                ? 1
                : Math.min(4, targetAnimalCount + 1);
        } else if (winStreak >= 3) {
            // Đang thắng: tự tin đánh dàn trải hơn
            targetAnimalCount = Math.min(4, targetAnimalCount + 1);
        }

        let shouldShake = numAnimalsBet >= targetAnimalCount;

        // Giới hạn % vốn cược trong ván, co giãn theo tâm lý
        let maxBetRatio = 1 / 3;
        if (loseStreak >= 4) maxBetRatio = 1 / 5; // thua nhiều thì bớt liều
        if (winStreak >= 4) maxBetRatio = 1 / 2;  // thắng nhiều thì mạnh dạn hơn

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

                // ALL IN dễ xảy ra hơn khi đang "máu me" gỡ gấp (thua streak dài + ít tiền)
                const allInChance = loseStreak >= 5 ? 0.03 : 0.01;
                if (Math.random() < allInChance && balance > 0 && balance < 2000000 && allInChip) {
                    targetBtn = allInChip;
                } else {
                    targetBtn = suitableChips[Math.floor(Math.random() * suitableChips.length)];
                }
            } else if (animals.length > 0) {
                let unbetAnimals = animals.filter(a => !myBets[a.getAttribute('data-animal')]);

                // Né các con vừa đánh gần đây để không bị lộ pattern
                let freshAnimals = unbetAnimals.filter(
                    a => !recentAnimalPicks.includes(a.getAttribute('data-animal'))
                );
                if (freshAnimals.length > 0) unbetAnimals = freshAnimals;

                let betAnimals = animals.filter(a => myBets[a.getAttribute('data-animal')]);

                if (unbetAnimals.length > 0 && Math.random() < 0.7) {
                    targetBtn = unbetAnimals[Math.floor(Math.random() * unbetAnimals.length)];
                } else if (betAnimals.length > 0) {
                    targetBtn = betAnimals[Math.floor(Math.random() * betAnimals.length)];
                } else {
                    targetBtn = animals[Math.floor(Math.random() * animals.length)];
                }

                if (targetBtn) {
                    const animalName = targetBtn.getAttribute('data-animal');
                    recentAnimalPicks.push(animalName);
                    if (recentAnimalPicks.length > 5) recentAnimalPicks.shift();
                }
            }
        } else {
            targetBtn = playBtn;
        }

        if (!targetBtn && currentTotalBet > 0) targetBtn = playBtn;
        else if (!targetBtn) targetBtn = animals[Math.floor(Math.random() * animals.length)];

        if (targetBtn) {
            botIsBusy = true;
            // Thời gian phản ứng thay đổi theo tâm lý: thua liên tục -> nhanh hơn (nóng vội)
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