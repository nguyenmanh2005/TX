    if (typeof BotVirtualCursor !== "undefined") {
        BotVirtualCursor.init("Bot Streamer");
        let botIsBusy = false;
        
        setInterval(() => {
            if (botIsBusy || typeof isSyncing !== 'undefined' && isSyncing) return;
            
            const playBtn = document.getElementById('pulseBtn');
            const chips = Array.from(document.querySelectorAll('.btn-quick-bet'));
            const options = Array.from(document.querySelectorAll('.sync-option'));
            const moneyText = document.getElementById('userMoney');
            let balance = 0;
            if (moneyText) {
                balance = parseInt(moneyText.innerText.replace(/\./g, '')) || 0;
            }
            
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
            
            // Chiến thuật "thông minh":
            let shouldShake = false;
            // Bot thường đánh 1-2 cửa một ván
            if (numOptionsBet >= (1 + Math.floor(Math.random() * 2))) {
                shouldShake = true; 
            }
            // Không cược quá 1/3 vốn trừ khi máu me
            if (currentTotalBet > 0 && currentTotalBet >= balance / 3) {
                shouldShake = true; 
            }
            
            if (currentTotalBet === 0 || !shouldShake) {
                // 30% cơ hội đổi chip cược, 70% chọn cửa
                if (Math.random() < 0.3 && chips.length > 0) {
                    const safeChips = chips.filter(b => b.innerText !== 'ALL IN');
                    const allInChip = chips.find(b => b.innerText === 'ALL IN');
                    
                    let suitableChips = safeChips;
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

                    // 1% cơ hội ALL IN nếu tiền ít
                    if (Math.random() < 0.01 && balance > 0 && balance < 2000000 && allInChip) {
                        targetBtn = allInChip;
                    } else {
                        targetBtn = suitableChips[Math.floor(Math.random() * suitableChips.length)];
                    }
                } else if (options.length > 0) {
                    // Chọn cửa cược
                    let unbetOptions = options.filter(a => !myCharges[a.getAttribute('data-choice')]);
                    let betOptions = options.filter(a => myCharges[a.getAttribute('data-choice')]);
                    
                    let mainOptions = options.filter(a => a.getAttribute('data-choice') === 'Stable' || a.getAttribute('data-choice') === 'Volatile');
                    let sideOptions = options.filter(a => a.getAttribute('data-choice') !== 'Stable' && a.getAttribute('data-choice') !== 'Volatile');
                    
                    if (Math.random() < 0.8 && mainOptions.length > 0) {
                        // 80% ưu tiên đánh Chẵn/Lẻ (Stable/Volatile)
                        let unbetMain = mainOptions.filter(a => !myCharges[a.getAttribute('data-choice')]);
                        if (unbetMain.length > 0) {
                            targetBtn = unbetMain[Math.floor(Math.random() * unbetMain.length)];
                        } else {
                            targetBtn = mainOptions[Math.floor(Math.random() * mainOptions.length)];
                        }
                    } else {
                        // 20% rải rác vào các cửa vị 3, vị 4
                        if (unbetOptions.length > 0 && Math.random() < 0.7) {
                            targetBtn = unbetOptions[Math.floor(Math.random() * unbetOptions.length)];
                        } else if (betOptions.length > 0) {
                            targetBtn = betOptions[Math.floor(Math.random() * betOptions.length)];
                        } else {
                            targetBtn = options[Math.floor(Math.random() * options.length)];
                        }
                    }
                }
            } else {
                targetBtn = playBtn;
            }
            
            // Fallback an toàn
            if (!targetBtn && currentTotalBet > 0) targetBtn = playBtn;
            else if (!targetBtn) targetBtn = options[Math.floor(Math.random() * options.length)];

            if (targetBtn) {
                botIsBusy = true;
                BotVirtualCursor.moveToElement($(targetBtn), 1, 0, () => {
                    setTimeout(() => { 
                        BotVirtualCursor.simulateClick(() => {
                            try { targetBtn.click(); } catch(e){}
                            botIsBusy = false;
                        });
                    }, 200 + Math.random() * 300); // Tốc độ click nhanh
                });
            }
        }, 600 + Math.random() * 800); // Tốc độ chơi nhanh
    }
