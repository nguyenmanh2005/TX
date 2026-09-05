/**
 * 🤖 Bot AI Yahtzee Royale - Cao Thủ Xí Ngầu (ID: 58)
 *
 * CHIẾN THUẬT & TRÍ TUỆ NHÂN TẠO:
 * 1. QUẢN LÝ VỐN & PHÂN BỔ CƯỢC:
 *    - Theo dõi số dư ví GTLM của Streamer.
 *    - Lựa chọn mức cược an toàn (10K, 50K, 100K, 500K) qua các nút cược nhanh.
 *    - Tuyệt đối KHÔNG bao giờ click vào nút "ALL IN".
 * 2. CHIẾN THUẬT GIỮ XÚC XẮC (HOLD STRATEGY):
 *    - Sau lượt lắc 1 & 2: Phân tích tần suất các mặt xúc xắc (1-6).
 *    - Tự động giữ (HOLD) các mặt xúc xắc giống nhau để nuôi bộ Ba, Tứ Quý, Cù Lũ hoặc săn kỳ tích YAHTZEE x50.
 * 3. THUẬT TOÁN TỐI ƯU HÓA ĐIỂM SỐ (SCORING MATRIX):
 *    - Tự động quét và chọn tổ hợp mang lại hệ số nhân GTLM cao nhất:
 *      + 5 mặt trùng nhau: YAHTZEE x50.0.
 *      + Cù Lũ (3 + 2): FULL HOUSE x15.0.
 *      + 4 mặt trùng: TỨ QUÝ x10.0.
 *      + 3 mặt trùng: BỘ BA x5.0.
 *      + Điểm đơn lẻ: Ưu tiên chọn các số lớn (Bộ 6, Bộ 5, Bộ 4) có số lượng cao nhất.
 * 4. HỆ THỐNG CHUỘT ẢO CHUYÊN NGHIỆP (BotVirtualCursor):
 *    - Di chuyển mượt mà tới các phím cược, xúc xắc cần giữ, nút LẮC XÚC XẮC và các hàng trong Bảng Điểm.
 * 5. BÌNH LUẬN & PHÁT NGÔN CASINO SÔI NỔI:
 *    - Tương tác chat tự động theo kết quả ván chơi qua BotChat.send.
 * 6. WATCHDOG TIMER:
 *    - Tự động giải phóng trạng thái bận sau 10 giây nếu gặp gián đoạn.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 58] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Thánh Xúc Xắc Yahtzee 58');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI VÀ BIẾN ĐIỀU KHIỂN
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimer = null;
    let lastChatTime = 0;

    function setBusy(val, timeoutMs = 10000) {
        botIsBusy = val;
        if (busyTimer) clearTimeout(busyTimer);
        if (val) {
            busyTimer = setTimeout(() => {
                console.log('[Bot 58] Watchdog giải phóng trạng thái bận!');
                botIsBusy = false;
            }, timeoutMs);
        }
    }

    // ═══════════════════════════════════════════════════════
    // BÌNH LUẬN & PHÁT NGÔN CASINO LIVESTREAM
    // ═══════════════════════════════════════════════════════
    const jackpotPhrases = [
        'YAHTZEE X50 THẦN THÁNH NỔ RỒI ANH EM ƠI! Húp trọn hàng triệu GTLM ngập ví! 👑💥🎲',
        'Cù Lũ siêu phẩm x15! Lắc tay xúc xắc chuẩn không cần chỉnh! 🏆✨',
        'Tứ Quý x10 vào mâm! Phong độ xóc xí ngầu đỉnh cao hôm nay! 🚀💰'
    ];

    const normalWinPhrases = [
        'Húp nhẹ tiền thưởng Yahtzee! Tích tiểu thành đại quá ngọt ngào! 🎲✨',
        'Bộ ba ăn chặt! Lãi ròng rồi tay chơi ơi! 💸🍀',
        'Xúc xắc lắc đẹp mê ly, tiền thưởng cộng về tài khoản rồi! ⚡'
    ];

    const lossPhrases = [
        'Lắc lệch mất một nhịp xúc xắc, ván sau gỡ lại mâm to hơn! 😤',
        'Xí ngầu chưa kết nối được bộ đẹp, làm lại tay mới săn Yahtzee! 💨',
        'Nhả nhẹ một ván cho máy ấm tay, ván sau nổ Cù Lũ! 🔥'
    ];

    function sendBotChat(type) {
        const now = Date.now();
        if (now - lastChatTime < 10000) return;
        if (type !== 'jackpot' && Math.random() > 0.65) return;

        let list = normalWinPhrases;
        if (type === 'jackpot') list = jackpotPhrases;
        else if (type === 'loss') list = lossPhrases;

        const msg = list[Math.floor(Math.random() * list.length)];
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(58, 'bot_58', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // QUẢN LÝ VỐN & PHÍM CƯỢC
    // ═══════════════════════════════════════════════════════
    function selectSafeBetBtn() {
        const btns = Array.from(document.querySelectorAll('.quick-btn')).filter(b => {
            const txt = (b.innerText || '').toUpperCase();
            return !txt.includes('ALL IN') && !txt.includes('5M');
        });

        if (btns.length === 0) return null;
        return btns[Math.floor(Math.random() * Math.min(btns.length, 3))];
    }

    // ═══════════════════════════════════════════════════════
    // PHÂN TÍCH XÚC XẮC & THUẬT TOÁN TỐI ƯU HÓA
    // ═══════════════════════════════════════════════════════
    function getDiceValues() {
        const dice = [];
        $('.die').each(function () {
            const txt = $(this).text().trim();
            const val = parseInt(txt);
            dice.push(isNaN(val) ? 0 : val);
        });
        return dice;
    }

    function chooseBestCategory(dice) {
        const counts = {};
        dice.forEach(d => {
            if (d > 0) counts[d] = (counts[d] || 0) + 1;
        });

        const maxCount = Math.max(0, ...Object.values(counts));
        const cv = Object.values(counts).sort((a, b) => a - b);

        // 1. Yahtzee (5 con giống nhau)
        if (maxCount === 5) return 'yahtzee';

        // 2. Cù Lũ (3 + 2)
        if (cv.length === 2 && cv[0] === 2 && cv[1] === 3) return 'fullhouse';

        // 3. Tứ Quý (>= 4 con giống nhau)
        if (maxCount >= 4) return 'fourofakind';

        // 4. Bộ Ba (>= 3 con giống nhau)
        if (maxCount >= 3) return 'threeofakind';

        // 5. Chọn mặt đơn lẻ mang lại điểm cao nhất
        let bestCat = 'ones';
        let bestScore = 0;
        const multMap = { 1: 0.5, 2: 1.0, 3: 1.5, 4: 2.0, 5: 2.5, 6: 3.0 };
        const nameMap = { 1: 'ones', 2: 'twos', 3: 'threes', 4: 'fours', 5: 'fives', 6: 'sixes' };

        for (let num = 6; num >= 1; num--) {
            const count = counts[num] || 0;
            const score = count * multMap[num];
            if (score > bestScore) {
                bestScore = score;
                bestCat = nameMap[num];
            }
        }

        return bestCat;
    }

    // ═══════════════════════════════════════════════════════
    // VÒNG LẶP HÀNH ĐỘNG CỦA BOT (DECISION ENGINE)
    // ═══════════════════════════════════════════════════════
    function runBotTurn() {
        if (botIsBusy) return;

        const rollCountText = $('#rollCount').text().trim();
        const rollCount = parseInt(rollCountText) || 0;
        const rollBtn = document.getElementById('rollBtn');

        // BƯỚC 1: VÁN MỚI (Lắc lần đầu tiên)
        if (rollCount === 0) {
            setBusy(true, 8000);
            console.log('[Bot 58] Bắt đầu ván mới: Chọn cược và lắc xí ngầu...');

            const betBtn = selectSafeBetBtn();
            if (betBtn && Math.random() < 0.6) {
                BotVirtualCursor.moveToElement($(betBtn), 0.5, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try { betBtn.click(); } catch (e) {}

                        setTimeout(() => {
                            if (rollBtn && !rollBtn.disabled) {
                                BotVirtualCursor.moveToElement($(rollBtn), 0.6, 0, () => {
                                    BotVirtualCursor.simulateClick(() => {
                                        console.log('[Bot 58] Bấm LẮC XÚC XẮC lượt 1!');
                                        try { rollBtn.click(); } catch (e) {}
                                        setTimeout(() => { setBusy(false); }, 1500);
                                    });
                                });
                            } else {
                                setBusy(false);
                            }
                        }, 300);
                    });
                });
            } else {
                if (rollBtn && !rollBtn.disabled) {
                    BotVirtualCursor.moveToElement($(rollBtn), 0.6, 0, () => {
                        BotVirtualCursor.simulateClick(() => {
                            console.log('[Bot 58] Bấm LẮC XÚC XẮC lượt 1 trực tiếp!');
                            try { rollBtn.click(); } catch (e) {}
                            setTimeout(() => { setBusy(false); }, 1500);
                        });
                    });
                } else {
                    setBusy(false);
                }
            }
            return;
        }

        // BƯỚC 2: SAU KHI ĐÃ LẮC LẦN 1 HOẶC LẦN 2
        if (rollCount === 1 || rollCount === 2) {
            setBusy(true, 9000);
            const dice = getDiceValues();
            console.log(`[Bot 58] Xúc xắc sau lượt ${rollCount}:`, dice);

            // Kiểm tra xem đã có Yahtzee hoặc Cù Lũ cực mạnh chưa
            const counts = {};
            dice.forEach(d => { if (d > 0) counts[d] = (counts[d] || 0) + 1; });
            const maxCount = Math.max(0, ...Object.values(counts));

            // Nếu đã trúng Yahtzee (5 con giống nhau) hoặc Cù Lũ hoàn hảo -> ghi điểm luôn!
            if (maxCount === 5 || (rollCount === 2 && maxCount >= 4)) {
                console.log('[Bot 58] Bộ xúc xắc quá đẹp! Chốt ghi điểm ngay!');
                submitBestScore(dice);
                return;
            }

            // Chiến thuật GIỮ (HOLD): Giữ các viên có số lặp nhiều nhất
            let mostFreqVal = 0;
            let maxFreq = 0;
            for (const [val, freq] of Object.entries(counts)) {
                if (freq > maxFreq || (freq === maxFreq && parseInt(val) > mostFreqVal)) {
                    maxFreq = freq;
                    mostFreqVal = parseInt(val);
                }
            }

            // Nếu có ít nhất 2 viên giống nhau: Giữ lại những viên đó
            if (maxFreq >= 2 && mostFreqVal > 0) {
                const diceElementsToHold = [];
                $('.die').each(function (i) {
                    const txt = $(this).text().trim();
                    if (parseInt(txt) === mostFreqVal && !$(this).hasClass('held')) {
                        diceElementsToHold.push($(this));
                    }
                });

                if (diceElementsToHold.length > 0) {
                    console.log(`[Bot 58] Giữ các xúc xắc giá trị ${mostFreqVal}...`);
                    holdElementsSequentially(diceElementsToHold, () => {
                        // Sau khi giữ, bấm lắc tiếp
                        setTimeout(() => {
                            if (rollBtn && !rollBtn.disabled) {
                                BotVirtualCursor.moveToElement($(rollBtn), 0.6, 0, () => {
                                    BotVirtualCursor.simulateClick(() => {
                                        console.log(`[Bot 58] Bấm LẮC XÚC XẮC lượt ${rollCount + 1}!`);
                                        try { rollBtn.click(); } catch (e) {}
                                        setTimeout(() => { setBusy(false); }, 1500);
                                    });
                                });
                            } else {
                                setBusy(false);
                            }
                        }, 400);
                    });
                    return;
                }
            }

            // Nếu không có gì giữ hoặc đã giữ xong, bấm lắc tiếp
            if (rollBtn && !rollBtn.disabled) {
                BotVirtualCursor.moveToElement($(rollBtn), 0.6, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        console.log(`[Bot 58] Lắc tiếp lượt ${rollCount + 1}!`);
                        try { rollBtn.click(); } catch (e) {}
                        setTimeout(() => { setBusy(false); }, 1500);
                    });
                });
            } else {
                // Nếu nút roll bị disable (đã hết lượt) -> chuyển sang ghi điểm
                submitBestScore(dice);
            }
            return;
        }

        // BƯỚC 3: ĐÃ LẮC ĐỦ 3 LƯỢT (rollCount === 3) -> BẮT BUỘC CHỌN ĐIỂM
        if (rollCount >= 3) {
            setBusy(true, 9000);
            const dice = getDiceValues();
            console.log('[Bot 58] Đã hết lượt lắc, chọn tổ hợp điểm tối ưu:', dice);
            submitBestScore(dice);
            return;
        }
    }

    function holdElementsSequentially(elements, onComplete) {
        let idx = 0;
        function next() {
            if (idx >= elements.length) {
                if (onComplete) onComplete();
                return;
            }
            const el = elements[idx];
            idx++;
            BotVirtualCursor.moveToElement(el, 0.4, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    try { el[0].click(); } catch (e) {}
                    setTimeout(next, 250);
                });
            });
        }
        next();
    }

    function submitBestScore(dice) {
        const bestCat = chooseBestCategory(dice);
        console.log(`[Bot 58] Chọn tổ hợp ghi điểm: ${bestCat}`);

        const row = document.querySelector(`.score-row[data-cat="${bestCat}"]`);
        if (row) {
            BotVirtualCursor.moveToElement($(row), 0.7, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    console.log(`[Bot 58] Click chọn ${bestCat}!`);
                    try { row.click(); } catch (e) {}

                    setTimeout(() => {
                        // Kiểm tra kết quả phản hồi chat
                        if (bestCat === 'yahtzee' || bestCat === 'fullhouse' || bestCat === 'fourofakind') {
                            sendBotChat('jackpot');
                        } else {
                            sendBotChat('win');
                        }

                        // Nghỉ ngơi 3.5s trước khi mở lượt tiếp theo
                        setTimeout(() => {
                            setBusy(false);
                        }, 3500);
                    }, 800);
                });
            });
        } else {
            setBusy(false);
        }
    }

    // ═══════════════════════════════════════════════════════
    // KHỞI CHẠY BOT ENGINE
    // ═══════════════════════════════════════════════════════
    console.log('[Bot 58] Khởi động AI Yahtzee Royale thành công!');
    setTimeout(() => {
        runBotTurn();
    }, 1500);

    setInterval(() => {
        runBotTurn();
    }, 4000);

})();
