/**
 * 🤖 Bot AI Pai Gow Poker Thông Minh (ID: 42)
 *
 * QUY TRÌNH 3 GIAI ĐOẠN:
 *  1. BETTING: Chọn chip → điền bet → bấm "CHIA BÀI"
 *  2. SPLITTING: Đọc 7 lá → tự tính tay thấp tối ưu (House Way đơn giản) → click 2 lá → bấm "XÁC NHẬN CHIA TAY"
 *  3. RESULT: Đọc kết quả → bấm "VÁN MỚI" → lặp lại
 *
 * CHIẾN LƯỢC CHIA BÀI (Simplified House Way):
 *  - Nếu có đôi: giữ đôi ở tay cao (5 lá), đưa 2 lá cao nhất còn lại vào tay thấp.
 *  - Không đôi: đưa lá cao nhất thứ 2 và thứ 3 vào tay thấp.
 *  - Bot đọc data-index từ các .card trong #player-hand để click đúng.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 42] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Pai Gow Master');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimer = null;
    let lastChatTime = 0;
    let winStreak = 0;
    let lossStreak = 0;

    // Trạng thái game hiện tại
    const STATE = {
        BETTING: 'betting',      // Đang ở màn bet / ván mới
        SPLITTING: 'splitting',  // Đang chọn 2 lá thấp
        RESULT: 'result',        // Đã xem kết quả, cần bấm Ván Mới
        BUSY: 'busy'
    };

    function setBusy(val) {
        botIsBusy = val;
        if (busyTimer) clearTimeout(busyTimer);
        if (val) {
            busyTimer = setTimeout(() => { botIsBusy = false; }, 3000);
        }
    }

    // ═══════════════════════════════════════════════════════
    // PHÁT HIỆN TRẠNG THÁI GAME
    // ═══════════════════════════════════════════════════════
    function detectState() {
        const betForm = document.getElementById('bet-form');
        const splitControls = document.getElementById('split-controls');
        const resultView = document.getElementById('result-view');

        if (betForm && betForm.style.display !== 'none') return STATE.BETTING;
        if (splitControls && splitControls.style.display !== 'none') return STATE.SPLITTING;
        if (resultView && resultView.style.display !== 'none') return STATE.RESULT;
        return STATE.BUSY;
    }

    // ═══════════════════════════════════════════════════════
    // TÍNH TỐI ƯU 2 LÁ CHO TAY THẤP
    // Chiến lược House Way đơn giản:
    // - Tìm cặp đôi trong 7 lá. Nếu có, giữ đôi trong tay cao,
    //   đưa 2 lá cao nhất còn lại vào tay thấp.
    // - Nếu không có đôi, đưa lá rank cao thứ 2 và thứ 3 vào tay thấp.
    // ═══════════════════════════════════════════════════════
    function chooseLowHandIndices() {
        const cards = Array.from(document.querySelectorAll('#player-hand .card'));
        if (cards.length !== 7) return null;

        // Đọc rank từ card text (val: 2-10,J,Q,K,A → rank 2-14)
        function parseRank(el) {
            const valEl = el.querySelector('.card-v');
            if (!valEl) return 0;
            const v = valEl.textContent.trim();
            if (v === 'A') return 14;
            if (v === 'K') return 13;
            if (v === 'Q') return 12;
            if (v === 'J') return 11;
            return parseInt(v) || 0;
        }

        const indexed = cards.map((el, i) => ({
            el,
            domIndex: i,
            dataIndex: parseInt(el.getAttribute('data-index') ?? i),
            rank: parseRank(el)
        }));

        // Tìm đôi
        const rankCount = {};
        indexed.forEach(c => { rankCount[c.rank] = (rankCount[c.rank] || 0) + 1; });
        const pairRanks = Object.keys(rankCount).filter(r => rankCount[r] >= 2).map(Number);
        pairRanks.sort((a, b) => b - a); // đôi lớn nhất trước

        let lowIndices = [];

        if (pairRanks.length >= 2) {
            // 2 đôi trở lên: giữ đôi LỚN NHẤT ở tay cao, đưa đôi nhỏ vào tay thấp
            const smallPairRank = pairRanks[1];
            const pairCards = indexed.filter(c => c.rank === smallPairRank).slice(0, 2);
            lowIndices = pairCards.map(c => c.dataIndex);
        } else if (pairRanks.length === 1) {
            // 1 đôi: giữ đôi ở tay cao, đưa 2 lá cao nhất còn lại vào tay thấp
            const pairRank = pairRanks[0];
            const nonPair = indexed.filter(c => c.rank !== pairRank)
                .sort((a, b) => b.rank - a.rank);
            lowIndices = nonPair.slice(0, 2).map(c => c.dataIndex);
        } else {
            // Không đôi: đưa lá rank cao thứ 2 và thứ 3 vào tay thấp
            const sorted = [...indexed].sort((a, b) => b.rank - a.rank);
            lowIndices = [sorted[1].dataIndex, sorted[2].dataIndex];
        }

        return { lowIndices, cards: indexed };
    }

    // ═══════════════════════════════════════════════════════
    // CHAT
    // ═══════════════════════════════════════════════════════
    const winPhrases = [
        'Pai Gow Master đây! Thắng cả 2 tay, húp GTLM sạch bóng! 🃏',
        'Bài hay tay cao, ăn gọn vào ví! 💰',
        'House Way chuẩn chỉnh, thắng đẹp anh em ơi! 🏆',
        'Đôi lớn bảo vệ tay cao, bái phục! ✨'
    ];
    const drawPhrases = [
        'Hòa vẫn tốt, ít nhất không mất vốn! 🤝',
        'Push! Tiền về tay, ván sau bung lụa! 😊'
    ];
    const losePhrases = [
        'Bay màu nhẹ, Dealer may mắn ván này thôi! 😅',
        'Thua lần này, phục thù ván sau! 😤',
        'Bài xấu thì chịu, không phải do bot! 💣'
    ];

    function sendBotChat(type) {
        const now = Date.now();
        if (now - lastChatTime < 12000) return;
        if (Math.random() > 0.55) return;
        let list = type === 'win' ? winPhrases : (type === 'draw' ? drawPhrases : losePhrases);
        const msg = list[Math.floor(Math.random() * list.length)];
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(42, 'bot_42', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // GIAI ĐOẠN 1: BETTING
    // ═══════════════════════════════════════════════════════
    function doBetting() {
        const dealBtn = document.getElementById('deal-btn');
        if (!dealBtn || dealBtn.disabled) return;

        setBusy(true);

        // Chọn chip ngẫu nhiên (10K-100K, không bet MAX)
        const chips = Array.from(document.querySelectorAll('.chip-selector .chip'));
        const safeChips = chips.filter(c => {
            const v = c.getAttribute('data-value');
            return v && v !== 'allin' && parseInt(v) <= 100000;
        });
        const targetChip = safeChips.length > 0
            ? safeChips[Math.floor(Math.random() * safeChips.length)]
            : chips[0];

        if (targetChip) {
            BotVirtualCursor.moveToElement($(targetChip), 0.4, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { targetChip.click(); } catch (e) {}

                        // Bấm CHIA BÀI
                        setTimeout(() => {
                            BotVirtualCursor.moveToElement($(dealBtn), 0.5, 0, () => {
                                setTimeout(() => {
                                    BotVirtualCursor.simulateClick(() => {
                                        try { dealBtn.click(); } catch (e) {}
                                        setTimeout(() => { setBusy(false); }, 800);
                                    });
                                }, 100);
                            });
                        }, 200);
                    });
                }, 80);
            });
        } else {
            // Trực tiếp bấm CHIA BÀI
            BotVirtualCursor.moveToElement($(dealBtn), 0.5, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { dealBtn.click(); } catch (e) {}
                        setTimeout(() => { setBusy(false); }, 800);
                    });
                }, 100);
            });
        }
    }

    // ═══════════════════════════════════════════════════════
    // GIAI ĐOẠN 2: SPLITTING (Chọn 2 lá thấp)
    // ═══════════════════════════════════════════════════════
    function doSplitting() {
        const submitBtn = document.getElementById('submit-btn');
        if (!submitBtn) return;

        // Kiểm tra xem đã chọn 2 lá chưa (có .selected)
        const selectedCards = document.querySelectorAll('#player-hand .card.selected');
        if (selectedCards.length === 2) {
            // Đã có 2 lá được chọn → bấm XÁC NHẬN
            setBusy(true);
            BotVirtualCursor.moveToElement($(submitBtn), 0.5, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { submitBtn.click(); } catch (e) {}
                        setTimeout(() => { setBusy(false); }, 1000);
                    });
                }, 100);
            });
            return;
        }

        // Chưa chọn → tính tay thấp tối ưu
        const result = chooseLowHandIndices();
        if (!result || result.lowIndices.length < 2) return;

        setBusy(true);

        // Tìm 2 card element cần click
        const allCards = Array.from(document.querySelectorAll('#player-hand .card'));
        const toClick = allCards.filter(el => {
            const idx = parseInt(el.getAttribute('data-index') ?? -1);
            return result.lowIndices.includes(idx);
        });

        if (toClick.length < 2) {
            // Fallback: click 2 lá đầu tiên
            setBusy(false);
            return;
        }

        // Click lá 1
        BotVirtualCursor.moveToElement($(toClick[0]), 0.45, 0, () => {
            setTimeout(() => {
                BotVirtualCursor.simulateClick(() => {
                    try { toClick[0].click(); } catch (e) {}

                    // Click lá 2
                    setTimeout(() => {
                        BotVirtualCursor.moveToElement($(toClick[1]), 0.45, 0, () => {
                            setTimeout(() => {
                                BotVirtualCursor.simulateClick(() => {
                                    try { toClick[1].click(); } catch (e) {}
                                    setBusy(false); // Cho phép vòng tiếp theo click Submit
                                });
                            }, 100);
                        });
                    }, 250);
                });
            }, 100);
        });
    }

    // ═══════════════════════════════════════════════════════
    // GIAI ĐOẠN 3: RESULT → Bấm VÁN MỚI
    // ═══════════════════════════════════════════════════════
    function doResult() {
        const resetBtn = document.getElementById('reset-btn');
        if (!resetBtn) return;

        setBusy(true);

        // Đọc kết quả từ SweetAlert2 hoặc DOM nếu có
        const swalTitle = document.querySelector('.swal2-title');
        if (swalTitle) {
            const t = swalTitle.textContent.toLowerCase();
            if (t.includes('thắng')) { winStreak++; lossStreak = 0; sendBotChat('win'); }
            else if (t.includes('hòa')) { sendBotChat('draw'); }
            else { lossStreak++; winStreak = 0; sendBotChat('lose'); }
        }

        setTimeout(() => {
            BotVirtualCursor.moveToElement($(resetBtn), 0.5, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { resetBtn.click(); } catch (e) {}
                        setTimeout(() => { setBusy(false); }, 600);
                    });
                }, 100);
            });
        }, 1200); // Chờ 1.2s cho user xem kết quả rồi mới reset
    }

    // ═══════════════════════════════════════════════════════
    // VÒNG LẶP CHÍNH
    // ═══════════════════════════════════════════════════════
    function playTurn() {
        if (botIsBusy) return;

        const state = detectState();
        if (state === STATE.BETTING) doBetting();
        else if (state === STATE.SPLITTING) doSplitting();
        else if (state === STATE.RESULT) doResult();
    }

    // Khởi động - mỗi 800ms
    setInterval(playTurn, 800);
    setTimeout(playTurn, 1200);

})();
