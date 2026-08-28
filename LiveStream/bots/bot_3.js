/**
 * 🤖 BOT STREAMER PRO V3 - PHI CÔNG VŨ TRỤ (CRASH FLIGHT 3D)
 * - Tự động nhận diện trạng thái: Đặt cược -> Chọn Chip -> Cài Auto -> Cất cánh -> Rút GTLM.
 * - Chuột ảo GSAP di chuyển mượt mà 0.25s - 0.3s, có click rung nảy rõ nét trên từng nút.
 * - Quản lý vốn thông minh: Cược tỉ lệ 2% - 5% tổng số dư, streamer biết chốt lời tay khi tên lửa bay cao.
 */
if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Phi Công Vũ Trụ 🚀");
    let botBusy = false;

    function getBotBalance() {
        const moneyEl = document.getElementById('userMoney');
        if (!moneyEl) return 50000000;
        return parseInt(moneyEl.innerText.replace(/\./g, '')) || 0;
    }

    function runCrashStreamerBot() {
        if (botBusy) return;

        const startBtn = document.getElementById('startBtn');
        const cashoutBtn = document.getElementById('cashoutBtn');

        // ── 1. GIAI ĐOẠN TÊN LỬA ĐANG BAY (FLYING) ──
        if (cashoutBtn && $(cashoutBtn).is(':visible') && !cashoutBtn.disabled) {
            const multDisp = document.getElementById('multDisp');
            const curMult = multDisp ? parseFloat(multDisp.innerText.replace(/[^0-9.]/g, '')) || 1.0 : 1.0;

            // Streamer theo dõi: Nếu bay lên > 1.8x thì 40% chốt lời tay
            if (curMult >= 1.8 && Math.random() < 0.4) {
                botBusy = true;
                BotVirtualCursor.moveToElement($(cashoutBtn), 0.2, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try { cashoutBtn.click(); } catch (e) { }
                        botBusy = false;
                        setTimeout(runCrashStreamerBot, 1000);
                    });
                });
                return;
            }

            // Tiếp tục theo dõi mỗi 300ms
            setTimeout(runCrashStreamerBot, 300);
            return;
        }

        // ── 2. GIAI ĐOẠN ĐẶT CƯỢC & CẤT CÁNH (BETTING) ──
        if (startBtn && $(startBtn).is(':visible') && !startBtn.disabled) {
            botBusy = true;
            const balance = getBotBalance();

            // Tìm các nút phỉnh cược
            const chips = Array.from(document.querySelectorAll('.btn-quick-bet')).filter(b => b.innerText.trim() !== 'ALL IN');
            let targetChip = null;

            if (chips.length > 0) {
                // Phân loại phỉnh cược linh hoạt theo chiến thuật streamer
                // 10K, 50K, 100K, 500K, 1M, 5M
                const chipMap = {};
                chips.forEach(c => {
                    const txt = c.innerText.trim();
                    chipMap[txt] = c;
                });

                const randStrategy = Math.random();
                if (randStrategy < 0.25 && chipMap['50K']) {
                    targetChip = chipMap['50K'];   // 25% cược thăm dò 50K
                } else if (randStrategy < 0.50 && chipMap['100K']) {
                    targetChip = chipMap['100K'];  // 25% cược nhẹ 100K
                } else if (randStrategy < 0.75 && chipMap['500K']) {
                    targetChip = chipMap['500K'];  // 25% cược vừa 500K
                } else if (randStrategy < 0.90 && chipMap['1M']) {
                    targetChip = chipMap['1M'];    // 15% cược lớn 1M
                } else if (chipMap['5M'] && balance >= 10000000) {
                    targetChip = chipMap['5M'];    // 10% khô máu 5M
                } else {
                    targetChip = chips[Math.floor(Math.random() * chips.length)];
                }
            }

            // Chọn nút Auto Cashout (x2, x5, x10)
            const autoButtons = Array.from(document.querySelectorAll('button[onclick*="autoCashout"]'));
            let targetAuto = null;
            if (autoButtons.length > 0 && Math.random() < 0.6) {
                const r = Math.random();
                if (r < 0.50) targetAuto = autoButtons[0];      // 50% chọn x2
                else if (r < 0.80) targetAuto = autoButtons[1]; // 30% chọn x5
                else targetAuto = autoButtons[2] || autoButtons[0]; // 20% chọn x10 (liều ăn nhiều)
            }

            // BƯỚC 1: Rê chuột bấm chọn Chip cược
            setTimeout(() => {
                if (targetChip) {
                    BotVirtualCursor.moveToElement($(targetChip), 0.25, 0, () => {
                        BotVirtualCursor.simulateClick(() => {
                            try { targetChip.click(); } catch (e) { }

                            // BƯỚC 2: Rê chuột chọn mốc Auto Cashout (nếu có)
                            if (targetAuto) {
                                setTimeout(() => {
                                    BotVirtualCursor.moveToElement($(targetAuto), 0.22, 0, () => {
                                        BotVirtualCursor.simulateClick(() => {
                                            try { targetAuto.click(); } catch (e) { }
                                            // BƯỚC 3: Rê chuột bấm CẤT CÁNH
                                            executeLaunch(startBtn);
                                        });
                                    });
                                }, 150);
                            } else {
                                // BƯỚC 3: Rê chuột bấm CẤT CÁNH
                                executeLaunch(startBtn);
                            }
                        });
                    });
                } else {
                    executeLaunch(startBtn);
                }
            }, 700 + Math.random() * 300); // Khoảng nghỉ 0.7s - 1.0s trước ván mới
            return;
        }

        // Chờ trạng thái tiếp theo
        setTimeout(runCrashStreamerBot, 400);
    }

    function executeLaunch(startBtn) {
        setTimeout(() => {
            BotVirtualCursor.moveToElement($(startBtn), 0.25, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    try { startBtn.click(); } catch (e) { }
                    botBusy = false;
                    setTimeout(runCrashStreamerBot, 500);
                });
            });
        }, 180);
    }

    // Khởi động bot streamer sau khi trang load
    $(document).ready(function () {
        setTimeout(runCrashStreamerBot, 1000);
    });
}
