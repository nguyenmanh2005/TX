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

        setInterval(() => {
            if (botIsBusy) return;
            
            const startBtn = document.getElementById('startBtn');
            const cashoutBtn = document.getElementById('cashoutBtn');
            const chips = Array.from(document.querySelectorAll('.btn-quick-bet'));
            const autoCashoutInput = document.getElementById('autoCashout');
            
            const balance = updatePsychology();
            let targetBtn = null;

            // Nếu game đang bay (cashoutBtn đang hiện)
            if (cashoutBtn && cashoutBtn.style.display !== 'none' && !cashoutBtn.disabled) {
                // Tâm lý: Có thể "yếu tim" rút tay sớm trước khi chạm mốc auto
                if (Math.random() < 0.15) {
                    targetBtn = cashoutBtn;
                }
            } 
            // Nếu game đang chờ cược (startBtn đang hiện)
            else if (startBtn && startBtn.style.display !== 'none' && !startBtn.disabled) {
                // 1. 30% đổi mức tiền cược
                if (Math.random() < 0.3 && chips.length > 0) {
                    const safeChips = chips.filter(b => b.innerText !== 'ALL IN');
                    let suitableChips = safeChips;
                    if (balance < 500000) {
                        suitableChips = safeChips.filter(c => {
                            let val = parseInt(c.innerText.replace(/\D/g,'')) * (c.innerText.includes('M') ? 1000000 : 1000);
                            return val <= 50000;
                        });
                    } else if (balance > 10000000) {
                        suitableChips = safeChips.filter(c => {
                            let val = parseInt(c.innerText.replace(/\D/g,'')) * (c.innerText.includes('M') ? 1000000 : 1000);
                            return val >= 100000;
                        });
                    }
                    if (suitableChips.length === 0) suitableChips = safeChips;

                    const allInChip = chips.find(b => b.innerText === 'ALL IN');
                    if (Math.random() < 0.01 && balance > 0 && balance < 2000000 && allInChip) {
                        targetBtn = allInChip;
                    } else {
                        targetBtn = suitableChips[Math.floor(Math.random() * suitableChips.length)];
                    }
                } 
                // 2. Đổi mốc tự động rút tiền (Auto Cashout)
                else if (Math.random() < 0.4 && autoCashoutInput) {
                    // Bot chọn mốc ăn non (1.1 - 1.9) hoặc tham lam (2.0 - 10.0)
                    let rand = Math.random();
                    if (rand < 0.5) intendedAutoCashout = 1.1 + Math.random() * 0.8; // 1.1x -> 1.9x
                    else if (rand < 0.8) intendedAutoCashout = 2.0 + Math.random() * 2.0; // 2.0x -> 4.0x
                    else intendedAutoCashout = 4.0 + Math.random() * 6.0; // 4.0x -> 10.0x
                    
                    autoCashoutInput.value = intendedAutoCashout.toFixed(2);
                    // Fake trigger update
                    if (typeof updatePotential === 'function') updatePotential();
                    
                    // Thỉnh thoảng bấm vào một nút x2 x5 x10 ngẫu nhiên để trông giống người dùng hơn
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
                BotVirtualCursor.moveToElement($(targetBtn), 1, 0, () => {
                    setTimeout(() => { 
                        BotVirtualCursor.simulateClick(() => {
                            try { targetBtn.click(); } catch(e){}
                            botIsBusy = false;
                        });
                    }, 200 + Math.random() * 300);
                });
            }
        }, 800 + Math.random() * 1000);
    }
