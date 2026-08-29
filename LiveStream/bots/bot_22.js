if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Bot Streamer");

    function runRacingBot() {
        const raceBtn = document.getElementById('raceBtn');
        // Nếu nút bị disable hoặc đang đua (raceActive = true) thì đợi
        if (!raceBtn || raceBtn.disabled || (typeof raceActive !== 'undefined' && raceActive)) {
            setTimeout(runRacingBot, 1500);
            return;
        }

        // 80% cơ hội giữ nguyên mức cược và con vật, chỉ bấm ĐUA luôn
        if (Math.random() < 0.80) {
            clickRace(raceBtn);
            return;
        }

        // 20% cơ hội đổi chiến thuật
        // Đổi con vật (50% trong số 20%)
        if (Math.random() < 0.50) {
            const animalSelect = document.getElementById('animalSelect');
            if (animalSelect && !animalSelect.disabled) {
                BotVirtualCursor.moveToElement($(animalSelect), 0.3, 0, () => {
                    BotVirtualCursor.simulateClick(() => { 
                        try {
                            const newAnimal = Math.floor(Math.random() * 8) + 1;
                            animalSelect.value = newAnimal;
                            // Trigger change event if needed
                            animalSelect.dispatchEvent(new Event('change'));
                        } catch(e){} 
                    });
                    setTimeout(chooseRacingBet, 400);
                });
                return;
            }
        }
        
        chooseRacingBet();
    }

    function chooseRacingBet() {
        const betBtns = Array.from(document.querySelectorAll('.qbtn')).filter(b => b.id !== 'maxBtn');
        if (betBtns.length > 0) {
            // Nghiêng về các mức cược thấp và vừa (ưu tiên mảng đầu)
            const maxIdx = Math.min(betBtns.length, 5);
            let targetBet = betBtns[Math.floor(Math.random() * maxIdx)];
            
            BotVirtualCursor.moveToElement($(targetBet), 0.4, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => { try { targetBet.click(); } catch(e){} });
                    setTimeout(() => clickRace(document.getElementById('raceBtn')), 300);
                }, 200);
            });
        } else {
            clickRace(document.getElementById('raceBtn'));
        }
    }

    function clickRace(raceBtn) {
        if (raceBtn && !raceBtn.disabled) {
            BotVirtualCursor.moveToElement($(raceBtn), 0.4, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => { try { raceBtn.click(); } catch(e){} });
                    // Đợi cuộc đua bắt đầu và kết thúc (khoảng 8-10 giây)
                    setTimeout(runRacingBot, 8000 + Math.random() * 2000);
                }, 200);
            });
        } else {
            setTimeout(runRacingBot, 1500);
        }
    }

    // Bắt đầu chu trình
    setTimeout(runRacingBot, 2000);
}
