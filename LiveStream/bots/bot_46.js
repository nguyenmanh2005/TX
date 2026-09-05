/**
 * 🤖 Bot Streamer 46 - Roulette Royal Intelligence Engine
 * Tự động đặt cược bàn Roulette chuyên nghiệp, chân thực như streamer thật.
 * Tuyệt đối không click lung tung hay ấn nhầm nút chức năng.
 */
(function() {
    if (typeof BotVirtualCursor === "undefined") {
        console.warn("[Bot_46] BotVirtualCursor chưa sẵn sàng.");
        return;
    }

    BotVirtualCursor.init("Bot Streamer");

    let botIsBusy = false;
    let roundState = 'idle'; // 'idle' -> 'betting' -> 'spinning' -> 'cooldown'
    let recentResults = []; // boolean: true = win, false = loss
    let lastBalance = null;
    let plan = null;
    let currentStep = 0;

    function getBalance() {
        const balEl = document.getElementById('balance-val');
        if (!balEl) return 0;
        return parseInt(balEl.innerText.replace(/\D/g, '')) || 0;
    }

    function updatePsychology() {
        const balance = getBalance();
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

    /**
     * Lập kế hoạch cược thông minh cho ván roulette
     */
    function createBettingPlan() {
        const balance = updatePsychology();
        if (balance < 10000) return null;

        const loseStreak = currentLoseStreak();
        const winStreak = currentWinStreak();

        // 1. Chọn mức chip phù hợp theo vốn và tâm lý
        let chipValue = "10000";
        if (balance >= 20000000) {
            chipValue = Math.random() < 0.7 ? "500000" : "1000000";
        } else if (balance >= 5000000) {
            chipValue = Math.random() < 0.6 ? "100000" : "500000";
        } else if (balance >= 1000000) {
            chipValue = Math.random() < 0.6 ? "50000" : "100000";
        } else {
            chipValue = "10000";
        }

        // 2. Chọn chiến thuật cược Roulette thực tế
        const targetCells = [];
        const strat = Math.random();

        if (strat < 0.45) {
            // Chiến thuật A: Cược Vùng Lớn (Đỏ/Đen, Chẵn/Lẻ, Tài/Xỉu)
            const outsideTypes = [
                '.cell[data-type="red"]',
                '.cell[data-type="black"]',
                '.cell[data-type="even"]',
                '.cell[data-type="odd"]',
                '.cell[data-type="low"]',
                '.cell[data-type="high"]'
            ];
            const pick = outsideTypes[Math.floor(Math.random() * outsideTypes.length)];
            const el = document.querySelector(pick);
            if (el) targetCells.push(el);

            // 50% cơ hội kèm thêm 1 số may mắn (straight bet)
            if (Math.random() < 0.5) {
                const luckyNum = Math.floor(Math.random() * 37);
                const straightEl = document.querySelector(`.cell[data-type="straight"][data-val="${luckyNum}"]`);
                if (straightEl) targetCells.push(straightEl);
            }
        } else if (strat < 0.75) {
            // Chiến thuật B: Cược Tá (Dozens) hoặc Cột (Columns)
            const groupTypes = [
                '.cell[data-type="dozen"][data-val="1"]',
                '.cell[data-type="dozen"][data-val="2"]',
                '.cell[data-type="dozen"][data-val="3"]',
                '.cell[data-type="column"][data-val="1"]',
                '.cell[data-type="column"][data-val="2"]',
                '.cell[data-type="column"][data-val="3"]'
            ];
            const pick = groupTypes[Math.floor(Math.random() * groupTypes.length)];
            const el = document.querySelector(pick);
            if (el) targetCells.push(el);

            // Kèm thêm 1-2 con số cảm tính
            const numCount = 1 + Math.floor(Math.random() * 2);
            for (let i = 0; i < numCount; i++) {
                const luckyNum = Math.floor(Math.random() * 37);
                const straightEl = document.querySelector(`.cell[data-type="straight"][data-val="${luckyNum}"]`);
                if (straightEl && !targetCells.includes(straightEl)) targetCells.push(straightEl);
            }
        } else {
            // Chiến thuật C: Săn Số Độc Đắc (Straight Numbers Hunter)
            const favNums = [0, 7, 8, 11, 17, 21, 23, 26, 29, 32, 35];
            const numCount = 2 + Math.floor(Math.random() * 2); // 2-3 số
            for (let i = 0; i < numCount; i++) {
                const n = Math.random() < 0.6 ? favNums[Math.floor(Math.random() * favNums.length)] : Math.floor(Math.random() * 37);
                const straightEl = document.querySelector(`.cell[data-type="straight"][data-val="${n}"]`);
                if (straightEl && !targetCells.includes(straightEl)) targetCells.push(straightEl);
            }
        }

        if (targetCells.length === 0) {
            const defaultCell = document.querySelector('.cell[data-type="red"]');
            if (defaultCell) targetCells.push(defaultCell);
        }

        return {
            chipValue: chipValue,
            targetCells: targetCells
        };
    }

    // Vòng lặp điều khiển chính của Bot
    setInterval(() => {
        // Kiểm tra nếu vòng quay đang chạy
        if (window.isSpinning) {
            roundState = 'spinning';
            return;
        }

        if (botIsBusy) return;

        // Nếu vừa quay xong, chờ một lát để thưởng thức hiệu ứng thắng/thua
        if (roundState === 'spinning') {
            roundState = 'cooldown';
            botIsBusy = true;
            setTimeout(() => {
                roundState = 'idle';
                botIsBusy = false;
            }, 2000 + Math.random() * 1500);
            return;
        }

        if (roundState === 'idle') {
            plan = createBettingPlan();
            if (!plan || plan.targetCells.length === 0) return;

            roundState = 'betting';
            currentStep = 0;
        }

        if (roundState === 'betting' && plan) {
            // Bước 0: Chọn Chip
            if (currentStep === 0) {
                const chipEl = document.querySelector(`.chip[data-value="${plan.chipValue}"]`);
                if (chipEl && !chipEl.classList.contains('active')) {
                    botIsBusy = true;
                    BotVirtualCursor.moveToElement($(chipEl), 0.6, 0, () => {
                        setTimeout(() => {
                            BotVirtualCursor.simulateClick(() => {
                                try { chipEl.click(); } catch(e) {}
                                currentStep++;
                                botIsBusy = false;
                            });
                        }, 200 + Math.random() * 200);
                    });
                    return;
                } else {
                    currentStep++; // Chip đã đúng, chuyển sang bước đặt cược
                }
            }

            // Bước 1..N: Lần lượt đặt cược vào các ô trên bàn
            const cellIndex = currentStep - 1;
            if (cellIndex < plan.targetCells.length) {
                const targetCell = plan.targetCells[cellIndex];
                if (targetCell) {
                    botIsBusy = true;
                    BotVirtualCursor.moveToElement($(targetCell), 0.5 + Math.random() * 0.3, 0, () => {
                        setTimeout(() => {
                            BotVirtualCursor.simulateClick(() => {
                                try { targetCell.click(); } catch(e) {}
                                currentStep++;
                                botIsBusy = false;
                            });
                        }, 250 + Math.random() * 200);
                    });
                    return;
                } else {
                    currentStep++;
                }
            }

            // Bước cuối: Nhấn Quay Bàn (PLACE BETS & SPIN)
            if (currentStep > plan.targetCells.length) {
                const spinBtn = document.getElementById('btn-spin');
                if (spinBtn && !spinBtn.disabled) {
                    // Kiểm tra xem đã có cược hợp lệ chưa
                    if (window.currentBets && window.currentBets.length > 0) {
                        botIsBusy = true;
                        BotVirtualCursor.moveToElement($(spinBtn), 0.7, 0, () => {
                            setTimeout(() => {
                                BotVirtualCursor.simulateClick(() => {
                                    try { spinBtn.click(); } catch(e) {}
                                    roundState = 'spinning';
                                    botIsBusy = false;
                                    plan = null;
                                });
                            }, 300 + Math.random() * 250);
                        });
                        return;
                    }
                }
            }
        }
    }, 400);

})();
