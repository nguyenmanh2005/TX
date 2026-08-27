// Bot Battle Royale - AI thông minh: theo dõi thua/thắng, tự điều chỉnh cược

function startBattleRoyaleBot() {
    if (typeof BotVirtualCursor === "undefined") {
        setTimeout(startBattleRoyaleBot, 500);
        return;
    }

    BotVirtualCursor.init("Bot Streamer");

    // === TRẠNG THÁI NỘI BỘ ===
    let lastBalance = null;        // Số dư lần trước
    let lossStreak   = 0;          // Chuỗi thua liên tiếp
    let winStreak    = 0;          // Chuỗi thắng liên tiếp
    let lastBet      = 0;          // Mức cược vừa dùng
    let roundHistory = [];         // Lịch sử ['win','loss','loss',...]

    // Các nhóm mức cược theo "chế độ"
    const BETS_SAFE       = [5000,  10000, 15000, 20000, 25000];       // Phòng thủ
    const BETS_NORMAL     = [10000, 25000, 50000, 75000, 100000];      // Bình thường
    const BETS_AGGRESSIVE = [50000, 100000, 200000, 300000, 500000];   // Gỡ gạc
    const BETS_ALLIN      = [500000, 750000, 1000000, 1500000];        // Máu me (hiếm)

    function getBetMode() {
        if (lossStreak >= 5) return 'safe';         // Thua 5+ lần -> thu mình lại
        if (lossStreak >= 3) return 'aggressive';   // Thua 3-4 lần -> gỡ gạc
        if (winStreak >= 4)  return 'allin';        // Thắng 4+ lần liên tiếp -> liều một phát
        if (winStreak >= 2)  return 'aggressive';   // Thắng 2-3 lần -> tăng dần
        return 'normal';
    }

    function pickBet(mode) {
        let pool;
        switch (mode) {
            case 'safe':       pool = BETS_SAFE;       break;
            case 'aggressive': pool = BETS_AGGRESSIVE; break;
            case 'allin':      pool = BETS_ALLIN;      break;
            default:           pool = BETS_NORMAL;
        }

        // Không chọn lại đúng mức cược cũ
        let candidates = pool.filter(v => v !== lastBet);
        if (candidates.length === 0) candidates = pool;

        // Random trong pool
        const val = candidates[Math.floor(Math.random() * candidates.length)];
        return val;
    }

    function readCurrentBalance() {
        const el = document.getElementById('balance');
        if (!el) return null;
        const raw = el.textContent.replace(/[^0-9]/g, '');
        return parseInt(raw) || null;
    }

    function updateStreak() {
        const cur = readCurrentBalance();
        if (lastBalance === null || cur === null) {
            lastBalance = cur;
            return;
        }

        if (cur > lastBalance) {
            winStreak++;
            lossStreak = 0;
            roundHistory.push('win');
            console.log('[Bot BR] ✅ THẮNG! WinStreak:', winStreak);
        } else if (cur < lastBalance) {
            lossStreak++;
            winStreak = 0;
            roundHistory.push('loss');
            console.log('[Bot BR] ❌ THUA! LossStreak:', lossStreak);
        }

        // Giữ tối đa 20 lần gần nhất
        if (roundHistory.length > 20) roundHistory.shift();

        lastBalance = cur;
    }

    // Gõ số vào ô input #bet như người thật gõ bàn phím
    function typeBetValue(val) {
        const input = document.getElementById('bet');
        if (!input) return;
        const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
        setter.call(input, val);
        input.dispatchEvent(new Event('input',  { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    const playRound = () => {
        const joinBtn  = document.getElementById('join-btn');
        const betInput = document.getElementById('bet');
        const isPlaying = typeof window.isPlaying !== 'undefined' ? window.isPlaying : false;

        if (joinBtn && joinBtn.offsetParent !== null && !joinBtn.disabled && !isPlaying) {

            // Đọc thắng/thua ván trước rồi cập nhật
            updateStreak();

            const mode   = getBetMode();
            const betVal = pickBet(mode);
            lastBet = betVal;

            console.log('[Bot BR] Mode:', mode, '| Cược:', betVal.toLocaleString(), 'GTLM | LossSt:', lossStreak, '| WinSt:', winStreak);

            if (betInput) {
                // Di chuột đến ô input, gõ số vào
                BotVirtualCursor.moveToElement($(betInput), 0.5, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        typeBetValue(betVal);

                        // Ngập ngừng 0.8-1.5s rồi RA CHIÊU
                        setTimeout(() => {
                            BotVirtualCursor.moveToElement($(joinBtn), 0.6, 0, () => {
                                BotVirtualCursor.simulateClick(() => {
                                    try { joinBtn.click(); } catch(e){}
                                });
                            });
                        }, 800 + Math.random() * 700);
                    });
                });
            } else {
                // Fallback: click btn-small preset
                const betAmounts = Array.from(document.querySelectorAll('.btn-small')).filter(b => b.offsetParent !== null);
                if (betAmounts.length > 0) {
                    const randBtn = betAmounts[Math.floor(Math.random() * betAmounts.length)];
                    BotVirtualCursor.moveToElement($(randBtn), 0.5, 0, () => {
                        BotVirtualCursor.simulateClick(() => {
                            try { randBtn.click(); } catch(e){}
                            setTimeout(() => {
                                BotVirtualCursor.moveToElement($(joinBtn), 0.6, 0, () => {
                                    BotVirtualCursor.simulateClick(() => {
                                        try { joinBtn.click(); } catch(e){}
                                    });
                                });
                            }, 800 + Math.random() * 700);
                        });
                    });
                }
            }
        }

        setTimeout(playRound, isPlaying ? 12000 : 7000 + Math.random() * 4000);
    };

    // Ghi nhận số dư ban đầu
    lastBalance = readCurrentBalance();
    setTimeout(playRound, 2500);
}

startBattleRoyaleBot();
