/**
 * 🤖 Bot AI Leo Tháp Hoàng Gia - Tower of Light (ID: 53)
 *
 * CHIẾN THUẬT & TRÍ TUỆ NHÂN TẠO:
 * 1. QUẢN LÝ VỐN & PHÂN BỔ CƯỢC:
 *    - Tự động theo dõi số dư ví GTLM của Streamer.
 *    - Lựa chọn mức cược an toàn (10K, 50K, 100K, 500K) qua các nút cược nhanh.
 *    - Tuyệt đối KHÔNG bao giờ click bừa vào nút "ALL IN" hay các liên kết điều hướng ngoài lề.
 * 2. CHIẾN THUẬT LEO THÁP THỰC DỤNG & BẢO TOÀN LỢI NHUẬN:
 *    - Ở các tầng thấp (Tầng 0 -> 2): Tập trung leo để gia tăng cấp số nhân lợi nhuận.
 *    - Ở các tầng trung (Tầng 3 -> 5): Tăng tỷ lệ rút tiền an toàn (35% -> 75%) để húp GTLM lớn.
 *    - Ở các tầng cao (Tầng 6 trở lên): 85% - 95% chọn Rút tiền để chốt lời kỷ lục.
 * 3. HỆ THỐNG CHUỘT ẢO CHUYÊN NGHIỆP (BotVirtualCursor):
 *    - Rê chuột mượt mà tới các ô gạch tháp (.tile), nút "BẮT ĐẦU LEO" (#startBtn) và "RÚT TIỀN" (#cashoutBtn).
 * 4. BÌNH LUẬN & PHÁT NGÔN CASINO SÔI NỔI:
 *    - Tương tác chat tự động theo kết quả ván chơi, mang lại không khí kịch tính cho người xem.
 * 5. WATCHDOG TIMER:
 *    - Tự động giải phóng trạng thái kẹt mạng sau 8 giây.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 53] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Thần Leo Tháp 53');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI VÀ BIẾN ĐIỀU KHIỂN
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimer = null;
    let lastChatTime = 0;
    let winStreak = 0;
    let lossStreak = 0;

    function setBusy(val, timeoutMs = 8000) {
        botIsBusy = val;
        if (busyTimer) clearTimeout(busyTimer);
        if (val) {
            busyTimer = setTimeout(() => {
                console.log('[Bot 53] Watchdog giải phóng trạng thái bận!');
                botIsBusy = false;
            }, timeoutMs);
        }
    }

    // ═══════════════════════════════════════════════════════
    // BÌNH LUẬN & PHÁT NGÔN CASINO LIVESTREAM
    // ═══════════════════════════════════════════════════════
    const bigWinPhrases = [
        'Đỉnh cao chinh phục Tháp Hoàng Gia! Húp trọn nhân to GTLM thơm lừng! 🏰💎🔥',
        'Leo tầng cao chót vót rút tiền chuẩn không cần chỉnh! Quá đẳng cấp! 🏆✨',
        'Càng lên cao gió càng mát nhưng tay vẫn vững! Tiền về đầy ví anh em ơi! 💰🎉',
        'Tay chơi thứ thiệt là phải biết dừng đúng lúc! Húp mẻ GTLM cực đậm! 🚀💎'
    ];

    const normalWinPhrases = [
        'Rút tiền an toàn thành công! Có lộc đầu ngày rồi cả nhà ơi! 💎✨',
        'Chốt lời bảo toàn vốn là bí kíp trường tồn trên sới bài! 💰🍀',
        'Một pha nhảy tháp khéo léo né mìn thành công! Húp nhẹ GTLM! 🎯',
        'Lợi nhuận nhân đôi ngọt lịm! Chuẩn bị làm tiếp ván mới nào! ⚡'
    ];

    const lossPhrases = [
        'Ối dồi ôi dẫm trúng bẫy rồi! Quả mìn này giấu kín quá! 💥😤',
        'Tham thì thâm, vừa định rướn thêm tầng nữa thì bay màu! Ván sau phục thù! 💨',
        'Không sao hết, sới bạc thắng thua là chuyện thường tình! Làm lại tay mới! 🔥',
        'Nhả nhẹ một ván cho nhà cái đỡ rén, ván sau leo thẳng đỉnh tháp! 🏰'
    ];

    function sendBotChat(type) {
        const now = Date.now();
        if (now - lastChatTime < 12000) return;
        if (Math.random() > 0.6) return;

        let list = normalWinPhrases;
        if (type === 'big') list = bigWinPhrases;
        else if (type === 'loss') list = lossPhrases;

        const msg = list[Math.floor(Math.random() * list.length)];
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(53, 'bot_53', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // QUẢN LÝ VỐN & CHỌN MỨC CƯỢC
    // ═══════════════════════════════════════════════════════
    function getBalance() {
        const moneyEl = document.getElementById('userMoney');
        if (!moneyEl) return 0;
        return parseFloat(moneyEl.innerText.replace(/[^0-9]/g, '')) || 0;
    }

    function selectQuickBet() {
        const balance = getBalance();
        const betCandidates = [10000, 50000, 100000, 500000];

        // Lọc mức cược không vượt quá 5% số dư ví
        let maxSafeBet = balance * 0.05;
        if (maxSafeBet < 10000) maxSafeBet = 10000;

        const validBets = betCandidates.filter(b => b <= maxSafeBet);
        const chosenBet = validBets.length > 0 ? validBets[Math.floor(Math.random() * validBets.length)] : 10000;

        // Tìm nút quick-bet tương ứng (loại trừ nút ALL IN)
        const buttons = Array.from(document.querySelectorAll('.btn-quick-bet')).filter(btn => {
            const txt = btn.innerText.trim().toUpperCase();
            return txt !== 'ALL IN' && !txt.includes('ALL');
        });

        let targetBtn = buttons.find(b => {
            const txt = b.innerText.trim().toUpperCase();
            if (chosenBet >= 1000000 && txt === (chosenBet / 1000000) + 'M') return true;
            if (chosenBet < 1000000 && txt === (chosenBet / 1000) + 'K') return true;
            return false;
        });

        if (!targetBtn && buttons.length > 0) {
            targetBtn = buttons[0];
        }

        return targetBtn;
    }

    // ═══════════════════════════════════════════════════════
    // VÒNG LẶP CHÍNH CỦA BOT (DECISION ENGINE)
    // ═══════════════════════════════════════════════════════
    function runBotTurn() {
        if (botIsBusy) return;

        const startBtn = document.getElementById('startBtn');
        const cashoutBtn = document.getElementById('cashoutBtn');

        const isStartVisible = startBtn && $(startBtn).is(':visible');
        const isCashoutVisible = cashoutBtn && $(cashoutBtn).is(':visible');

        // TRƯỜNG HỢP 1: Game chưa chạy -> Đặt cược và bắt đầu leo tháp
        if (isStartVisible) {
            setBusy(true, 10000);
            console.log('[Bot 53] Chuẩn bị cược và bấm bắt đầu leo tháp...');

            const quickBetBtn = selectQuickBet();
            if (quickBetBtn && Math.random() < 0.7) {
                BotVirtualCursor.moveToElement($(quickBetBtn), 0.7, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try { quickBetBtn.click(); } catch (e) {}

                        setTimeout(() => {
                            // Rê chuột tới nút BẮT ĐẦU LEO
                            BotVirtualCursor.moveToElement($(startBtn), 0.8, 0, () => {
                                BotVirtualCursor.simulateClick(() => {
                                    try { startBtn.click(); } catch (e) {}
                                    setTimeout(() => {
                                        setBusy(false);
                                    }, 1200);
                                });
                            });
                        }, 500);
                    });
                });
            } else {
                // Rê thẳng tới nút BẮT ĐẦU LEO
                BotVirtualCursor.moveToElement($(startBtn), 0.8, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try { startBtn.click(); } catch (e) {}
                        setTimeout(() => {
                            setBusy(false);
                        }, 1200);
                    });
                });
            }
            return;
        }

        // TRƯỜNG HỢP 2: Game đang chạy -> Quyết định rút tiền hay leo tiếp
        if (isCashoutVisible) {
            // Lấy tầng đang kích hoạt
            let floorNum = typeof currentFloor !== 'undefined' ? currentFloor : 0;
            const activeFloorEl = document.querySelector('.floor.active');
            if (activeFloorEl && activeFloorEl.id) {
                const parsed = parseInt(activeFloorEl.id.replace('floor-', ''));
                if (!isNaN(parsed)) floorNum = parsed;
            }

            console.log(`[Bot 53] Đang ở tầng ${floorNum}`);

            // Cân nhắc Rút tiền nếu đã leo ít nhất 1 tầng
            if (floorNum >= 1) {
                let cashoutChance = 0.05; // Tầng 1: 5%
                if (floorNum === 2) cashoutChance = 0.20;
                else if (floorNum === 3) cashoutChance = 0.40;
                else if (floorNum === 4) cashoutChance = 0.60;
                else if (floorNum === 5) cashoutChance = 0.75;
                else if (floorNum >= 6) cashoutChance = 0.90;

                if (Math.random() < cashoutChance) {
                    // Quyết định Rút tiền!
                    setBusy(true, 10000);
                    console.log(`[Bot 53] Quyết định rút tiền an toàn tại tầng ${floorNum}!`);

                    BotVirtualCursor.moveToElement($(cashoutBtn), 0.8, 0, () => {
                        BotVirtualCursor.simulateClick(() => {
                            try { cashoutBtn.click(); } catch (e) {}

                            const isBig = floorNum >= 5;
                            sendBotChat(isBig ? 'big' : 'win');

                            // Dừng 3.5s - 4.5s chiêm ngưỡng kết quả
                            setTimeout(() => {
                                setBusy(false);
                            }, 3800);
                        });
                    });
                    return;
                }
            }

            // Quyết định leo tiếp: Chọn 1 ô tile trong tầng active
            if (!activeFloorEl) {
                console.log('[Bot 53] Không tìm thấy tầng active, chờ...');
                return;
            }

            const tiles = Array.from(activeFloorEl.querySelectorAll('.tile'));
            if (tiles.length === 0) return;

            setBusy(true, 10000);

            // Chọn ngẫu nhiên 1 trong 3 ô (0, 1 hoặc 2)
            const pickedIdx = Math.floor(Math.random() * tiles.length);
            const chosenTile = tiles[pickedIdx];

            console.log(`[Bot 53] Chọn ô tile #${pickedIdx} ở tầng ${floorNum}...`);

            BotVirtualCursor.moveToElement($(chosenTile), 0.7, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    try { chosenTile.click(); } catch (e) {}

                    // Chờ phản hồi kết quả ô
                    setTimeout(() => {
                        if (chosenTile.classList.contains('trap') || chosenTile.innerText.includes('💥')) {
                            // Dẫm phải bẫy
                            console.log('[Bot 53] Dẫm phải bẫy!');
                            sendBotChat('loss');
                            setTimeout(() => {
                                setBusy(false);
                            }, 3800);
                        } else {
                            // An toàn hoặc Max Win
                            console.log('[Bot 53] Bước nhảy an toàn!');
                            setTimeout(() => {
                                setBusy(false);
                            }, 1200);
                        }
                    }, 700);
                });
            });
        }
    }

    // ═══════════════════════════════════════════════════════
    // KHỞI ĐỘNG VÒNG LẶP ĐIỀU KHIỂN
    // ═══════════════════════════════════════════════════════
    console.log('[Bot 53] Thần Leo Tháp 53 đã sẵn sàng nhập cuộc!');
    setInterval(runBotTurn, 1500);

})();
