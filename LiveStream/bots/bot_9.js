/**
 * 🤖 BOT STREAMER PRO V3 - THẦN BÀI BACCARAT (BACCARAT ROYALE)
 * - Tự động nhận diện trạng thái: Chờ ván -> Chọn chip cược -> Đặt cửa KING/QUEEN/DRAW -> KHAI CUỘC.
 * - Quản lý vốn thông minh: Đa dạng hóa phỉnh cược (50K, 100K, 500K, 1M, 5M), NGĂN CHẶN bấm nút XÓA.
 * - Chuột ảo GSAP di chuyển mượt mà 0.25s - 0.3s với hiệu ứng click nảy rõ nét trên từng ô cược.
 */
if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Thần Bài Baccarat 👑👸");
    let botBusy = false;

    function getBotBalance() {
        const balanceEl = document.getElementById('userBalance');
        if (!balanceEl) return 50000000;
        return parseInt(balanceEl.innerText.replace(/\D/g, '')) || 0;
    }

    function runBaccaratStreamerBot() {
        if (botBusy) return;

        const dealBtn = document.getElementById('dealBtn');
        if (!dealBtn) {
            setTimeout(runBaccaratStreamerBot, 500);
            return;
        }

        // ── 1. NẾU VÁN BÀI ĐANG CHIA: BOT CHỜ VÀ THEO DÕI LẬT BÀI ──
        if ($(dealBtn).hasClass('disabled') || (typeof BaccaratLogic !== 'undefined' && BaccaratLogic.isGameRunning)) {
            setTimeout(runBaccaratStreamerBot, 400);
            return;
        }

        // ── 2. NẾU BÀN ĐANG CHỜ CƯỢC: CHUẨN BỊ VÁN MỚI ──
        botBusy = true;
        const balance = getBotBalance();

        // Lấy danh sách chip an toàn (LOẠI BỎ NÚT XÓA)
        const chips = Array.from(document.querySelectorAll('#chipSelector .chip')).filter(c => {
            const val = parseInt(c.getAttribute('data-value')) || 0;
            return val > 0;
        });

        // Phân loại phỉnh cược linh hoạt
        let targetChip = null;
        if (chips.length > 0) {
            const chipMap = {};
            chips.forEach(c => {
                const txt = c.innerText.trim();
                chipMap[txt] = c;
            });

            const rand = Math.random();
            if (rand < 0.25 && chipMap['50K']) {
                targetChip = chipMap['50K'];   // 25% cược thăm dò 50K
            } else if (rand < 0.55 && chipMap['100K']) {
                targetChip = chipMap['100K'];  // 30% cược nhẹ 100K
            } else if (rand < 0.80 && chipMap['500K']) {
                targetChip = chipMap['500K'];  // 25% cược tiêu chuẩn 500K
            } else if (rand < 0.95 && chipMap['1M']) {
                targetChip = chipMap['1M'];    // 15% cược to 1M
            } else if (chipMap['5M'] && balance >= 10000000) {
                targetChip = chipMap['5M'];    // 5% cược khủng 5M
            } else {
                targetChip = chips[Math.floor(Math.random() * chips.length)];
            }
        }

        // Chọn cửa cược Baccarat (KING: 48%, QUEEN: 44%, DRAW: 8%)
        const boxPlayer = document.getElementById('box-player');
        const boxBanker = document.getElementById('box-banker');
        const boxTie = document.getElementById('box-tie');

        let targetBox = boxBanker;
        const randBox = Math.random();
        if (randBox < 0.48) {
            targetBox = boxBanker; // 👑 KING (Nhà Cái)
        } else if (randBox < 0.92) {
            targetBox = boxPlayer; // 👸 QUEEN (Người Chơi)
        } else {
            targetBox = boxTie || boxBanker; // 🤝 DRAW (Hòa 1:8)
        }

        // BƯỚC 1: Nghỉ 0.8s - 1.2s trước ván mới
        setTimeout(() => {
            if (targetChip) {
                BotVirtualCursor.moveToElement($(targetChip), 0.25, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try { targetChip.click(); } catch(e){}

                        // BƯỚC 2: Rê chuột đặt vào ô cược đã chọn
                        if (targetBox) {
                            setTimeout(() => {
                                BotVirtualCursor.moveToElement($(targetBox), 0.25, 0, () => {
                                    BotVirtualCursor.simulateClick(() => {
                                        try { targetBox.click(); } catch(e){}

                                        // BƯỚC 3: Rê chuột bấm "KHAI CUỘC"
                                        executeDeal(dealBtn);
                                    });
                                });
                            }, 150);
                        } else {
                            executeDeal(dealBtn);
                        }
                    });
                });
            } else {
                executeDeal(dealBtn);
            }
        }, 900 + Math.random() * 400);
    }

    function executeDeal(dealBtn) {
        setTimeout(() => {
            BotVirtualCursor.moveToElement($(dealBtn), 0.25, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    try { dealBtn.click(); } catch(e){}
                    botBusy = false;
                    setTimeout(runBaccaratStreamerBot, 800);
                });
            });
        }, 180);
    }

    // Khởi động bot streamer
    $(document).ready(function() {
        setTimeout(runBaccaratStreamerBot, 1000);
    });
}
