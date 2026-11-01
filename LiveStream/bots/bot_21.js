if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Kỵ Mã Siêu Tốc 🐎🏁");
    let lastKnownBalance = null;

    const CASINO_CHATS = {
        race: [
            "Trường đua rực lửa! Ra chiêu chọn tuấn mã xung phong giật cúp! 🐎🏁🔥",
            "Ép ga hết cỡ! Khí thế áp đảo đường đua rước bão GTLM! ⚡🏆",
            "Vào tiền chắc tay, ủng hộ tuấn mã về nhất nào anh em! 🌟💎",
            "Dàn thú đua vào vị trí xuất phát! Ra chiêu quyết thắng! 🏇🚀"
        ],
        racing: [
            "Bứt tốc ngoạn mục ở khúc cua! Nước rút thần sầu nào! 🐎💨",
            "Đua căng thẳng từng mét! Tiến lên về đích húp trọn giải thưởng! 😱🏁"
        ],
        win: [
            "HÚP TRỌN GIẢI NHẤT ĐUA THÚ! Tuấn mã cán đích rực rỡ, tiền cộng đầy nick! 🏆🐎🤑",
            "Chiến thắng vang dội trường đua! Gáy khét lẹt cả sảnh! 👑🔥💰",
            "Đỉnh cao soi kèo thú đua! GTLM cộng dồn rực rỡ! 💎✨"
        ],
        loss: [
            "Bị ép cua phút chót hụt mất giải nhất, bay màu tiếng này! 😭💔",
            "Bốc hơi nhẹ, ván sau chọn lại thần mã phục thù! 💪🔄"
        ]
    };

    function sendChat(type) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.race;
            const msg = list[Math.floor(Math.random() * list.length)];
            BotChat.send(21, 'Kỵ Mã Siêu Tốc 🐎🏁', msg);
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

    function runRacingBot() {
        checkBalance();
        const raceBtn = document.getElementById('raceBtn');
        if (!raceBtn || raceBtn.disabled || (typeof raceActive !== 'undefined' && raceActive)) {
            setTimeout(runRacingBot, 1500);
            return;
        }

        // 80% cơ hội giữ nguyên mức cược và con vật, chỉ bấm ĐUA luôn
        if (Math.random() < 0.80) {
            clickRace(raceBtn);
            return;
        }

        // 20% cơ hội đổi con vật
        if (Math.random() < 0.50) {
            const animalSelect = document.getElementById('animalSelect');
            if (animalSelect && !animalSelect.disabled) {
                BotVirtualCursor.moveToElement($(animalSelect), 0.3, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try {
                            const newAnimal = Math.floor(Math.random() * 8) + 1;
                            animalSelect.value = newAnimal;
                            animalSelect.dispatchEvent(new Event('change'));
                        } catch (e) { }
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
            const maxIdx = Math.min(betBtns.length, 5);
            let targetBet = betBtns[Math.floor(Math.random() * maxIdx)];

            BotVirtualCursor.moveToElement($(targetBet), 0.4, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => { try { targetBet.click(); } catch (e) { } });
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
                    BotVirtualCursor.simulateClick(() => { 
                        try { 
                            raceBtn.click(); 
                            if (Math.random() < 0.35) sendChat('race');
                            setTimeout(() => {
                                if (Math.random() < 0.4) sendChat('racing');
                            }, 3500);
                        } catch (e) { } 
                    });
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
