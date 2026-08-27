if (typeof BotVirtualCursor !== "undefined") {
        BotVirtualCursor.init("Bot Streamer");
        let botIsBusy = false;
        
        setInterval(() => {
            if (botIsBusy || typeof isRolling !== 'undefined' && isRolling) return;
            
            const playBtn = document.getElementById('playBtn');
            const chips = Array.from(document.querySelectorAll('.btn-quick-bet'));
            const animals = Array.from(document.querySelectorAll('.animal-tile'));
            const moneyText = document.getElementById('userMoney');
            let balance = 0;
            if (moneyText) {
                balance = parseInt(moneyText.innerText.replace(/\./g, '')) || 0;
            }
            
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
            
            // Chiến thuật "thông minh":
            let shouldShake = false;
            // Bot thường đánh 1-3 con một ván
            if (numAnimalsBet >= (1 + Math.floor(Math.random() * 3))) {
                shouldShake = true; 
            }
            // Không cược quá 1/3 vốn trừ khi máu me
            if (currentTotalBet > 0 && currentTotalBet >= balance / 3) {
                shouldShake = true; 
            }
            
            if (currentTotalBet === 0 || !shouldShake) {
                // 30% cơ hội đổi chip cược, 70% chọn linh thú
                if (Math.random() < 0.3 && chips.length > 0) {
                    const safeChips = chips.filter(b => b.innerText !== 'ALL IN');
                    const allInChip = chips.find(b => b.innerText === 'ALL IN');
                    
                    let suitableChips = safeChips;
                    // Lựa cơm gắp mắm: Nghèo thì đánh chip nhỏ, giàu đánh chip to
                    if (balance < 500000) {
                        suitableChips = safeChips.filter(c => {
                            let valStr = c.innerText.replace(/\D/g,'');
                            let mult = c.innerText.includes('M') ? 1000000 : 1000;
                            return (parseInt(valStr) * mult) <= 50000;
                        });
                    } else if (balance > 10000000) {
                        suitableChips = safeChips.filter(c => {
                            let valStr = c.innerText.replace(/\D/g,'');
                            let mult = c.innerText.includes('M') ? 1000000 : 1000;
                            return (parseInt(valStr) * mult) >= 100000;
                        });
                    }
                    if (suitableChips.length === 0) suitableChips = safeChips;

                    // 1% cơ hội bạo phát bạo tàn ALL IN nếu tiền ít
                    if (Math.random() < 0.01 && balance > 0 && balance < 2000000 && allInChip) {
                        targetBtn = allInChip;
                    } else {
                        targetBtn = suitableChips[Math.floor(Math.random() * suitableChips.length)];
                    }
                } else if (animals.length > 0) {
                    // Chọn linh thú
                    let unbetAnimals = animals.filter(a => !myBets[a.getAttribute('data-animal')]);
                    let betAnimals = animals.filter(a => myBets[a.getAttribute('data-animal')]);
                    
                    // 70% chọn thú mới chưa cược, 30% nhồi thêm tiền vào thú đã cược
                    if (unbetAnimals.length > 0 && Math.random() < 0.7) {
                        targetBtn = unbetAnimals[Math.floor(Math.random() * unbetAnimals.length)];
                    } else if (betAnimals.length > 0) {
                        targetBtn = betAnimals[Math.floor(Math.random() * betAnimals.length)];
                    } else {
                        targetBtn = animals[Math.floor(Math.random() * animals.length)];
                    }
                }
            } else {
                targetBtn = playBtn;
            }
            
            // Fallback an toàn
            if (!targetBtn && currentTotalBet > 0) targetBtn = playBtn;
            else if (!targetBtn) targetBtn = animals[Math.floor(Math.random() * animals.length)];

            if (targetBtn) {
                botIsBusy = true;
                BotVirtualCursor.moveToElement($(targetBtn), 1, 0, () => {
                    setTimeout(() => { 
                        BotVirtualCursor.simulateClick(() => {
                            try { targetBtn.click(); } catch(e){}
                            botIsBusy = false;
                        });
                    }, 200 + Math.random() * 300); // Tốc độ click cực nhanh
                });
            }
        }, 600 + Math.random() * 800); // Suy nghĩ 0.6s - 1.4s trước khi hành động
    }
