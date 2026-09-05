/**
 * 🤖 Bot AI Thợ Săn Mìn Thông Minh cho Game Dò Mìn Cổ Điển (ID: 40)
 * - Tự động chơi liên tục 24/7, có Watchdog chống kẹt bot.
 * - Quản lý vốn thông minh (Smart Bankroll): Chọn mức cược 10K, 50K, 100K theo chuỗi thắng/thua.
 * - Chiến thuật dò an toàn: Nhắm mục tiêu mở 2-3 ô an toàn, ưu tiên góc và ô biên.
 * - Tự động chốt lời: Bấm "💰 Rút" khi đạt chỉ tiêu, bảo toàn số dư.
 * - Tương tác chat tự động theo phong cách chuẩn dự án (GTLM, húp, bay màu, ra chiêu...).
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 40] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Thợ Săn Mìn');

    let botIsBusy = false;
    let targetSafePicks = 3;
    let winStreak = 0;
    let lossStreak = 0;
    let lastChatTime = 0;
    let busyTimer = null;

    function setBusy(val) {
        botIsBusy = val;
        if (busyTimer) clearTimeout(busyTimer);
        if (val) {
            // Watchdog: Tự động giải phóng bot sau 2s nếu animation hoặc mạng bị treo
            busyTimer = setTimeout(() => {
                botIsBusy = false;
            }, 2000);
        }
    }

    const winMessages = [
        'Húp trọn kim cương rồi anh em ơi, rút sớm cho chắc! 💎',
        'Lợi nhuận x{MULT} quá êm, chốt lời an toàn! 💰',
        'Tay khéo quá, né trọn 3 bãi mìn! ✨',
        'Húp GTLM nhẹ nhàng, bảo toàn mạng là trên hết! 🚀',
        'Biết đủ là thắng, biết đủ là vui! 😎'
    ];

    const loseMessages = [
        'BÙM! Dẫm trúng mìn rồi anh em ơi! 💣',
        'Vừa chạm nhẹ đã nổ tung, ván sau né góc này! 💥',
        'Tạm thời bay màu nhẹ, không sao ván sau phục thù! 😅',
        'Mìn giấu hiểm thật, ván sau ra chiêu lớn phục thù! 😤'
    ];

    function sendBotChat(isWin, mult) {
        const now = Date.now();
        if (now - lastChatTime < 15000) return;
        if (Math.random() > 0.5) return;

        const list = isWin ? winMessages : loseMessages;
        let msg = list[Math.floor(Math.random() * list.length)];
        msg = msg.replace('{MULT}', mult || '1.5');

        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(40, 'bot_40', msg);
            lastChatTime = now;
        }
    }

    function playTurn() {
        if (botIsBusy) return;

        // Tự động tắt mọi popup nếu có
        if (typeof Swal !== 'undefined' && Swal.isVisible && Swal.isVisible()) {
            try { Swal.close(); } catch (e) { }
        }

        const btnStart = document.getElementById('btn-start');
        const btnCashout = document.getElementById('btn-cashout');
        const betInput = document.getElementById('bet-amount');

        // Đảm bảo ô cược luôn có số hợp lệ
        if (betInput && (!betInput.value || parseInt(betInput.value) <= 0)) {
            betInput.value = 10000;
        }

        // Kiểm tra xem ván đang chạy hay đang dừng
        const isGameActive = btnCashout && $(btnCashout).is(':visible') && btnCashout.style.display !== 'none';
        const isGameIdle = btnStart && $(btnStart).is(':visible') && btnStart.style.display !== 'none' && !btnStart.disabled && !isGameActive;

        // ══════════════════════════════════════════════════════
        // GIAI ĐOẠN 1: GAME ĐANG DỪNG -> CHỌN CHIP VÀ BẮT ĐẦU VÁN
        // ══════════════════════════════════════════════════════
        if (isGameIdle) {
            setBusy(true);

            // 1. Xác định số ô cần mở an toàn
            if (winStreak >= 2) {
                targetSafePicks = Math.random() < 0.6 ? 3 : 4;
            } else if (lossStreak >= 2) {
                targetSafePicks = 2; // Thua thì chỉ mở 2 ô là rút ngay
            } else {
                targetSafePicks = 3;
            }

            // 2. Chọn mức cược thông minh
            let targetChipText = '10K';
            if (winStreak >= 3) targetChipText = Math.random() < 0.5 ? '100K' : '50K';
            else if (winStreak >= 1) targetChipText = '50K';
            else if (lossStreak >= 3) targetChipText = '10K';

            const quickBtns = Array.from(document.querySelectorAll('.btn-quick-bet'));
            const chipBtn = quickBtns.find(b => (b.innerText || '').trim().toUpperCase() === targetChipText) || quickBtns[0];

            const currentBet = parseInt($('#bet-amount').val()) || 0;
            const targetVal = targetChipText === '100K' ? 100000 : (targetChipText === '50K' ? 50000 : 10000);

            // Bấm chọn chip cược nếu khác mức hiện tại
            if (chipBtn && currentBet !== targetVal && Math.random() < 0.7) {
                BotVirtualCursor.moveToElement($(chipBtn), 0.4, 0, () => {
                    setTimeout(() => {
                        BotVirtualCursor.simulateClick(() => {
                            try { chipBtn.click(); } catch (e) { }
                            setTimeout(() => {
                                clickStart(btnStart);
                            }, 150);
                        });
                    }, 80);
                });
            } else {
                clickStart(btnStart);
            }
            return;
        }

        // ══════════════════════════════════════════════════════
        // GIAI ĐOẠN 2: GAME ĐANG CHẠY -> DÒ MÌN HOẶC RÚT GTLM (CASHOUT)
        // ══════════════════════════════════════════════════════
        if (isGameActive) {
            setBusy(true);

            const revealedCells = document.querySelectorAll('.mine-cell.revealed');
            const revealedCount = revealedCells.length;
            const cashoutText = (btnCashout.innerText || '');
            const matchMult = cashoutText.match(/x([0-9.]+)/);
            const curMult = matchMult ? matchMult[1] : '1.3';

            // 1. Kiểm tra đã đạt mục tiêu mở ô chưa?
            if (revealedCount >= targetSafePicks) {
                // 🎉 ĐÃ ĐẠT MỤC TIÊU -> BẤM NÚT RÚT GTLM
                BotVirtualCursor.moveToElement($(btnCashout), 0.5, 0, () => {
                    setTimeout(() => {
                        BotVirtualCursor.simulateClick(() => {
                            try { btnCashout.click(); } catch (e) { }
                            winStreak++;
                            lossStreak = 0;
                            sendBotChat(true, curMult);
                            setTimeout(() => { setBusy(false); }, 900);
                        });
                    }, 120);
                });
                return;
            }

            // 2. Chưa đạt mục tiêu -> CHỌN 1 Ô CHƯA MỞ ĐỂ ĐÀO
            const unrevealedCells = Array.from(document.querySelectorAll('.mine-cell:not(.revealed):not(.mine)'));
            if (unrevealedCells.length === 0) {
                setBusy(false);
                return;
            }

            // 🧠 CHIẾN THUẬT: Ưu tiên các góc (0, 4, 20, 24)
            const cornerIndices = [0, 4, 20, 24];
            const availableCorners = unrevealedCells.filter(c => cornerIndices.includes(parseInt(c.getAttribute('data-cell'))));

            let chosenCell = null;
            if (availableCorners.length > 0 && Math.random() < 0.65) {
                chosenCell = availableCorners[Math.floor(Math.random() * availableCorners.length)];
            } else {
                chosenCell = unrevealedCells[Math.floor(Math.random() * unrevealedCells.length)];
            }

            if (chosenCell) {
                BotVirtualCursor.moveToElement($(chosenCell), 0.45, 0, () => {
                    setTimeout(() => {
                        BotVirtualCursor.simulateClick(() => {
                            try { chosenCell.click(); } catch (e) { }
                            setTimeout(() => {
                                const hitMine = document.querySelectorAll('.mine-cell.mine').length > 0;
                                if (hitMine) {
                                    lossStreak++;
                                    winStreak = 0;
                                    sendBotChat(false, null);
                                    setTimeout(() => { setBusy(false); }, 900);
                                } else {
                                    setBusy(false);
                                }
                            }, 300);
                        });
                    }, 100);
                });
            } else {
                setBusy(false);
            }
        }
    }

    function clickStart(btnStart) {
        BotVirtualCursor.moveToElement($(btnStart), 0.5, 0, () => {
            setTimeout(() => {
                BotVirtualCursor.simulateClick(() => {
                    try { btnStart.click(); } catch (e) { }
                    setTimeout(() => { setBusy(false); }, 600);
                });
            }, 100);
        });
    }

    // Khởi động chu trình quét định kỳ mỗi 700ms
    setInterval(playTurn, 700);

    // Chạy lượt đầu tiên sau 1s
    setTimeout(playTurn, 1000);

})();
