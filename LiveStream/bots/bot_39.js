/**
 * 🤖 Bot AI Chuyên Gia Dò Mìn Thông Minh cho Game Mines (ID: 39)
 * - Tuyệt đối không click bừa bãi: Chạy theo State Machine bài bản (Chuẩn bị -> Dò Mìn -> Chốt Lời / Rút GTLM).
 * - Quản lý vốn thông minh (Smart Bankroll): Chọn chip cược phù hợp theo số dư và chuỗi thắng/thua.
 * - Chiến thuật dò mìn có tính toán: Đặt mục tiêu an toàn (2-4 ô), ưu tiên vị trí chiến lược (góc, biên hoặc dò lân cận).
 * - Tự động bấm Cashout ("Rút GTLM") khi đạt mục tiêu an toàn, bảo toàn lợi nhuận.
 * - Tự động chat cảm thán theo chuẩn từ lóng dự án (GTLM, húp, bay màu, ra chiêu...).
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 39] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Chuyên Gia Dò Mìn');

    let botIsBusy = false;
    let targetSafePicks = 3; // Số ô an toàn dự kiến mở trong ván này trước khi Cashout
    let winStreak = 0;
    let lossStreak = 0;
    let lastChatTime = 0;
    let consecutiveTurnsInRound = 0;

    const winMessages = [
        'Húp trọn kim cương rồi anh em ơi, té thôi! 💎',
        'Lợi nhuận x{MULT} quá ngon, chốt lời an toàn! 💰',
        'Thần mìn phù hộ, đào đâu trúng ngọc đó! ✨',
        'Húp đậm GTLM ván này, ai theo cầu đỏ không! 🚀',
        'Biết đủ là thắng, rút GTLM về ví ngay! 😎'
    ];

    const loseMessages = [
        'Ối dồi ôi, vừa chạm nhẹ đã nổ tung rồi! 💣',
        'Dẫm trúng mìn xui quá, ván sau né góc này ra! 💥',
        'Tạm thời bay màu nhẹ, ván sau gỡ lại gấp bội! 😅',
        'Bẫy laser gắt thật, để ván sau ra chiêu lớn phục thù! 😤'
    ];

    function sendBotChat(isWin, mult) {
        const now = Date.now();
        if (now - lastChatTime < 15000) return; // Giãn cách 15s để không spam
        if (Math.random() > 0.5) return;

        const list = isWin ? winMessages : loseMessages;
        let msg = list[Math.floor(Math.random() * list.length)];
        msg = msg.replace('{MULT}', mult || '1.5');

        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(39, 'bot_39', msg);
            lastChatTime = now;
        }
    }

    function readBalance() {
        const el = document.getElementById('userMoney');
        if (!el) return 50000000;
        const text = el.innerText || el.textContent || '';
        const num = parseInt(text.replace(/[^\d]/g, ''), 10);
        return isNaN(num) ? 50000000 : num;
    }

    // ── CHU TRÌNH TRÍ THÔNG MINH CỦA BOT ──
    function botLoop() {
        if (botIsBusy) return;

        const startBtn = document.getElementById('startBtn');
        const cashoutBtn = document.getElementById('cashoutBtn');

        // KIỂM TRA TRẠNG THÁI: GAME ĐANG DỪNG HAY ĐANG CHƠI?
        const isGameIdle = startBtn && $(startBtn).is(':visible') && !startBtn.disabled;
        const isGameActive = cashoutBtn && $(cashoutBtn).is(':visible');

        // ══════════════════════════════════════════════════════
        // GIAI ĐOẠN 1: GAME ĐANG CHỜ -> CHUẨN BỊ VÀ BẮT ĐẦU VÁN MỚI
        // ══════════════════════════════════════════════════════
        if (isGameIdle) {
            botIsBusy = true;
            consecutiveTurnsInRound = 0;

            // 1. Quyết định mục tiêu số ô mở an toàn
            if (winStreak >= 2) {
                // Đang thắng: tự tin mở 3 - 4 ô
                targetSafePicks = Math.random() < 0.6 ? 3 : 4;
            } else if (lossStreak >= 2) {
                // Đang thua: cẩn trọng mở 2 - 3 ô để giữ vốn
                targetSafePicks = Math.random() < 0.7 ? 2 : 3;
            } else {
                targetSafePicks = 3;
            }

            // 2. Chọn mức cược thông minh
            let targetBetAmount = '10K';
            if (winStreak >= 3) {
                targetBetAmount = Math.random() < 0.5 ? '100K' : '50K';
            } else if (winStreak >= 1) {
                targetBetAmount = '50K';
            } else if (lossStreak >= 3) {
                targetBetAmount = '10K';
            } else {
                targetBetAmount = '10K';
            }

            // Tìm nút quick bet
            const quickBtns = Array.from(document.querySelectorAll('.btn-quick-bet'));
            const betBtn = quickBtns.find(b => (b.innerText || '').trim().toUpperCase() === targetBetAmount) || quickBtns[0];

            // BƯỚC 1.1: Di chuyển chuột chọn chip cược (nếu cần đổi)
            const currentBetVal = parseInt($('#betAmount').val()) || 0;
            const targetBetVal = targetBetAmount === '100K' ? 100000 : (targetBetAmount === '50K' ? 50000 : 10000);

            if (betBtn && currentBetVal !== targetBetVal && Math.random() < 0.7) {
                BotVirtualCursor.moveToElement($(betBtn), 0.7, 0, () => {
                    setTimeout(() => {
                        BotVirtualCursor.simulateClick(() => {
                            try { betBtn.click(); } catch (e) { }

                            // BƯỚC 1.2: Di chuyển chuột bấm "🚀 BẮT ĐẦU DÒ"
                            setTimeout(() => {
                                clickStartGame(startBtn);
                            }, 400);
                        });
                    }, 300);
                });
            } else {
                // Bấm thẳng nút Bắt đầu
                clickStartGame(startBtn);
            }
            return;
        }

        // ══════════════════════════════════════════════════════
        // GIAI ĐOẠN 2: GAME ĐANG CHƠI -> MỞ Ô HOẶC CHỐT LỜI (RÚT GTLM)
        // ══════════════════════════════════════════════════════
        if (isGameActive) {
            botIsBusy = true;

            const revealedTiles = document.querySelectorAll('.tile.revealed');
            const revealedCount = revealedTiles.length;
            const curMult = $('#curMult').text() || '1.0';

            // 1. Kiểm tra đã đạt mục tiêu an toàn chưa?
            if (revealedCount >= targetSafePicks && !cashoutBtn.disabled) {
                // 🎉 ĐÃ ĐẠT MỤC TIÊU -> DI CHUYỂN CHUỘT BẤM "💰 RÚT" (CHỐT LỜI)
                BotVirtualCursor.moveToElement($(cashoutBtn), 0.8, 0, () => {
                    setTimeout(() => {
                        BotVirtualCursor.simulateClick(() => {
                            try { cashoutBtn.click(); } catch (e) { }
                            winStreak++;
                            lossStreak = 0;
                            sendBotChat(true, curMult);

                            // Chờ hiệu ứng nhận GTLM xong và giải phóng bot
                            setTimeout(() => {
                                botIsBusy = false;
                            }, 3000);
                        });
                    }, 400);
                });
                return;
            }

            // 2. Chưa đạt mục tiêu -> CHỌN 1 Ô CHƯA MỞ ĐỂ ĐÀO TIẾP
            const unrevealedTiles = Array.from(document.querySelectorAll('.tile:not(.revealed):not(.mine):not(.safe)'));
            if (unrevealedTiles.length === 0) {
                botIsBusy = false;
                return;
            }

            // 🧠 CHIẾN THUẬT CHỌN Ô CÓ TÍNH TOÁN:
            // Ưu tiên các góc (0, 4, 20, 24) hoặc các ô biên ít mìn hơn
            const cornerIndices = [0, 4, 20, 24];
            const availableCorners = unrevealedTiles.filter(t => cornerIndices.includes(parseInt(t.getAttribute('data-index'))));

            let chosenTile = null;
            if (availableCorners.length > 0 && Math.random() < 0.6) {
                chosenTile = availableCorners[Math.floor(Math.random() * availableCorners.length)];
            } else {
                chosenTile = unrevealedTiles[Math.floor(Math.random() * unrevealedTiles.length)];
            }

            if (chosenTile) {
                consecutiveTurnsInRound++;
                BotVirtualCursor.moveToElement($(chosenTile), 0.7, 0, () => {
                    setTimeout(() => {
                        BotVirtualCursor.simulateClick(() => {
                            try { chosenTile.click(); } catch (e) { }

                            // Chờ kết quả mở ô
                            setTimeout(() => {
                                // Kiểm tra xem có vừa nổ mìn không
                                const hitMine = document.querySelectorAll('.tile.mine').length > 0;
                                if (hitMine) {
                                    lossStreak++;
                                    winStreak = 0;
                                    sendBotChat(false, null);
                                    setTimeout(() => {
                                        botIsBusy = false;
                                    }, 2500);
                                } else {
                                    // Mở trúng kim cương thành công
                                    botIsBusy = false;
                                }
                            }, 700);
                        });
                    }, 350);
                });
            } else {
                botIsBusy = false;
            }
        }
    }

    function clickStartGame(startBtn) {
        BotVirtualCursor.moveToElement($(startBtn), 0.8, 0, () => {
            setTimeout(() => {
                BotVirtualCursor.simulateClick(() => {
                    try { startBtn.click(); } catch (e) { }
                    setTimeout(() => {
                        botIsBusy = false;
                    }, 1200);
                });
            }, 300);
        });
    }

    // Khởi chạy vòng lặp kiểm tra trạng thái mượt mà mỗi 1.2 - 1.8 giây
    setInterval(botLoop, 1400);

    // Bắt đầu vòng lặp đầu tiên sau 2 giây khi nạp trang
    setTimeout(botLoop, 2000);

})();
