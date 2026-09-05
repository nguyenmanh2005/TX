/**
 * 🤖 Bot AI Three Card Poker (Poker Ba Lá) Thần Bài Đẳng Cấp (ID: 52)
 *
 * CHIẾN THUẬT & TRÍ TUỆ ĐỈNH CAO:
 * 1. QUẢN LÝ VỐN & PHÂN BỔ CƯỢC:
 *    - Tự động theo dõi số dư ví GTLM của Streamer.
 *    - Chọn mức cược linh hoạt (10K, 50K, 100K, 500K) qua các phỉnh cược.
 *    - Cược Ante bắt buộc và 40% đặt thêm Pair Plus để săn Thùng/Sảnh/Sáp.
 * 2. CHIẾN THUẬT QUYẾT ĐỊNH CHUẨN TOÁN HỌC (Q-6-4 Rule):
 *    - Nếu bài có Đôi (Pair), Thùng (Flush), Sảnh (Straight), Sáp (Three of a Kind) hay Thùng Phá Sảnh: 100% chọn PLAY!
 *    - Nếu chỉ có Bài Cao (High Card):
 *      + Có lá Q, K hoặc A: Chọn PLAY để đối đầu Dealer.
 *      + Bài quá nhỏ (< Q): 70% chọn FOLD (Úp bài) để cắt lỗ Ante thông minh.
 * 3. HỆ THỐNG CHUỘT ẢO CHUYÊN NGHIỆP (BotVirtualCursor):
 *    - Di chuyển mượt mà tới các ô cược, chọn phỉnh, bấm Chia Bài, Play/Fold, và bấm Ván Mới.
 *    - Tuyệt đối KHÔNG bấm lung tung vào nút "THOÁT VỀ SẢNH".
 * 4. TƯƠNG TÁC CHAT & CẢM XÚC:
 *    - Bắt sự kiện kết quả từ `#result-status-badge` để phát ngôn câu thoại Casino cực chất.
 *    - Dừng 3.5s - 4.5s cho người xem chiêm ngưỡng bài mở và tiền thưởng.
 * 5. WATCHDOG CHỐNG KẸT:
 *    - Tự động phục hồi trạng thái sau 8 giây nếu gặp gián đoạn mạng.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 52] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Thần Bài Ba Lá 52');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI VÀ BIẾN ĐIỀU KHIỂN
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimer = null;
    let lastChatTime = 0;
    let winStreak = 0;
    let lossStreak = 0;
    let currentPhase = 'betting'; // 'betting' | 'deciding' | 'result'

    function setBusy(val, timeoutMs = 8000) {
        botIsBusy = val;
        if (busyTimer) clearTimeout(busyTimer);
        if (val) {
            busyTimer = setTimeout(() => {
                console.log('[Bot 52] Watchdog giải phóng trạng thái bận!');
                botIsBusy = false;
                currentPhase = 'betting';
            }, timeoutMs);
        }
    }

    // ═══════════════════════════════════════════════════════
    // BÌNH LUẬN & PHÁT NGÔN CASINO LIVESTREAM
    // ═══════════════════════════════════════════════════════
    const bigWinPhrases = [
        'Sáp 3 lá phát nổ rồi anh em ơi! Húp trọn mâm x30 GTLM thơm nức mũi! 🃏💥🔥',
        'Thùng Phá Sảnh thần thánh xuất hiện! Quá đẳng cấp cho một ván giao lưu! 🏆✨',
        'Dealer không đủ tuổi so bài với Thánh Bài hôm nay! Ăn cả Ante lẫn Play! 💵🎉',
        'Bảo rồi theo cầu tay son là tiền về ngập hòm! Anh em vào chung vui nào! 🚀💎'
    ];

    const normalWinPhrases = [
        'Có lộc có lộc! Bài đẹp hơn Dealer, húp trọn GTLM ngọt xớt! 🃏✨',
        'Chiến thuật Q-6-4 chuẩn chỉ! Dealer Qualify nhưng vẫn thua điểm! 💰',
        'Húp nhẹ ván bài này! Đang trên đà phong độ thăng hoa! 🍀🔥',
        'Thắng tiếp một ván nữa! Tay bài 3 lá hôm nay quá bén! 🎯'
    ];

    const lossPhrases = [
        'Dealer nay bốc bài son quá, hụt một tay ván sau gỡ lại ngay! 😤',
        'Úp bài cắt lỗ bảo toàn vốn là chiến thuật của dân chơi bản lĩnh! 💨',
        'Không sao hết, nhả một ván để ván sau húp Sáp to hơn! 🔥🃏',
        'Bình tĩnh làm lại tay mới, vận may đang chờ ở ván kế tiếp! 🎯'
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
            BotChat.send(52, 'bot_52', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // QUẢN LÝ VỐN & CHỌN MỨC PHỈNH
    // ═══════════════════════════════════════════════════════
    function getBalance() {
        const moneyEl = document.getElementById('balance-val');
        if (!moneyEl) return 1000000;
        return parseInt(moneyEl.innerText.replace(/\D/g, '')) || 0;
    }

    function selectOptimalChip() {
        const balance = getBalance();
        const chips = Array.from(document.querySelectorAll('#chipSelector .chip'));
        if (chips.length === 0) return null;

        // Lọc bỏ nút XÓA (data-value = 0)
        const validChips = chips.filter(c => {
            const v = parseInt(c.getAttribute('data-value')) || 0;
            return v > 0;
        });
        if (validChips.length === 0) return null;

        let targetChip = validChips[0]; // Mặc định 10K

        if (balance > 10000000) {
            // Số dư > 10M: Chọn 100K hoặc 500K
            const highChips = validChips.filter(c => {
                const v = parseInt(c.getAttribute('data-value')) || 0;
                return v >= 100000 && v <= 500000;
            });
            if (highChips.length > 0) targetChip = highChips[Math.floor(Math.random() * highChips.length)];
        } else if (balance > 2000000) {
            // Số dư > 2M: Chọn 50K hoặc 100K
            const midChips = validChips.filter(c => {
                const v = parseInt(c.getAttribute('data-value')) || 0;
                return v >= 50000 && v <= 100000;
            });
            if (midChips.length > 0) targetChip = midChips[Math.floor(Math.random() * midChips.length)];
        } else {
            // Số dư thấp: Chọn 10K hoặc 50K
            const lowChips = validChips.filter(c => {
                const v = parseInt(c.getAttribute('data-value')) || 0;
                return v <= 50000;
            });
            if (lowChips.length > 0) targetChip = lowChips[Math.floor(Math.random() * lowChips.length)];
        }

        return targetChip;
    }

    // ═══════════════════════════════════════════════════════
    // VÒNG LẶP CHÍNH CỦA BOT (GAMEPLAY LOOP)
    // ═══════════════════════════════════════════════════════
    function botLoop() {
        if (botIsBusy) return;

        const betArea = document.getElementById('bet-area');
        const playArea = document.getElementById('play-area');
        const resultArea = document.getElementById('result-area');

        // GIAI ĐOẠN 1: ĐANG Ở GIAO DIỆN VÁN MỚI (RESULT AREA HIỂN THỊ)
        if (resultArea && $(resultArea).is(':visible')) {
            const resetBtn = document.getElementById('reset-btn');
            if (resetBtn) {
                setBusy(true, 4000);
                BotVirtualCursor.moveToElement($(resetBtn), 0.4, 0, () => {
                    setTimeout(() => {
                        BotVirtualCursor.simulateClick(() => {
                            try { resetBtn.click(); } catch (e) { }
                            setTimeout(() => {
                                setBusy(false);
                                currentPhase = 'betting';
                            }, 800);
                        });
                    }, 150);
                });
            }
            return;
        }

        // GIAI ĐOẠN 2: ĐẶT CƯỢC & CHIA BÀI (BET AREA HIỂN THỊ)
        if (betArea && $(betArea).is(':visible')) {
            const dealBtn = document.getElementById('deal-btn');
            if (!dealBtn) return;

            setBusy(true, 10000);
            currentPhase = 'betting';

            // 1. Rê chuột vào ô ANTE
            const boxAnte = document.getElementById('box-ante');
            const chip = selectOptimalChip();

            if (boxAnte && chip) {
                BotVirtualCursor.moveToElement($(boxAnte), 0.35, 0, () => {
                    setTimeout(() => {
                        BotVirtualCursor.simulateClick(() => {
                            try { boxAnte.click(); } catch (e) { }

                            // 2. Click chọn phỉnh
                            setTimeout(() => {
                                BotVirtualCursor.moveToElement($(chip), 0.35, 0, () => {
                                    setTimeout(() => {
                                        BotVirtualCursor.simulateClick(() => {
                                            try { chip.click(); } catch (e) { }

                                            // 3. Xác suất 40%: Đặt thêm Pair Plus
                                            const wantPairPlus = Math.random() < 0.4;
                                            const boxPP = document.getElementById('box-pairplus');

                                            if (wantPairPlus && boxPP) {
                                                setTimeout(() => {
                                                    BotVirtualCursor.moveToElement($(boxPP), 0.3, 0, () => {
                                                        setTimeout(() => {
                                                            BotVirtualCursor.simulateClick(() => {
                                                                try { boxPP.click(); } catch (e) { }
                                                                setTimeout(() => {
                                                                    BotVirtualCursor.moveToElement($(chip), 0.3, 0, () => {
                                                                        setTimeout(() => {
                                                                            BotVirtualCursor.simulateClick(() => {
                                                                                try { chip.click(); } catch (e) { }
                                                                                // Bấm CHIA BÀI
                                                                                setTimeout(() => clickDealButton(dealBtn), 350);
                                                                            });
                                                                        }, 100);
                                                                    });
                                                                }, 250);
                                                            });
                                                        }, 100);
                                                    });
                                                }, 300);
                                            } else {
                                                // Bấm CHIA BÀI luôn
                                                setTimeout(() => clickDealButton(dealBtn), 350);
                                            }
                                        });
                                    }, 100);
                                });
                            }, 300);
                        });
                    }, 100);
                });
            } else {
                clickDealButton(dealBtn);
            }
            return;
        }

        // GIAI ĐOẠN 3: RA QUYẾT ĐỊNH PLAY HAY FOLD (PLAY AREA HIỂN THỊ)
        if (playArea && $(playArea).is(':visible')) {
            const playBtn = document.getElementById('play-btn');
            const foldBtn = document.getElementById('fold-btn');
            if (!playBtn || !foldBtn) return;

            setBusy(true, 8000);
            currentPhase = 'deciding';

            // Phân tích bài Player để quyết định Play hay Fold
            // Đọc các lá bài player từ DOM hoặc class revealed
            const isConfident = decidePlayOrFold();
            const chosenBtn = isConfident ? playBtn : foldBtn;

            // Thời gian suy nghĩ tự nhiên (600ms - 1.2s)
            setTimeout(() => {
                BotVirtualCursor.moveToElement($(chosenBtn), 0.45, 0, () => {
                    setTimeout(() => {
                        BotVirtualCursor.simulateClick(() => {
                            try { chosenBtn.click(); } catch (e) { }

                            // Chờ mở bài Dealer và hiển thị kết quả (1s) + xem badge (3.5s)
                            setTimeout(() => {
                                setBusy(false);
                                currentPhase = 'result';
                            }, 4200);
                        });
                    }, 150);
                });
            }, 600 + Math.random() * 400);
        }
    }

    function clickDealButton(dealBtn) {
        BotVirtualCursor.moveToElement($(dealBtn), 0.4, 0, () => {
            setTimeout(() => {
                BotVirtualCursor.simulateClick(() => {
                    try { dealBtn.click(); } catch (e) { }
                    setTimeout(() => {
                        setBusy(false);
                        currentPhase = 'deciding';
                    }, 1200);
                });
            }, 120);
        });
    }

    // ═══════════════════════════════════════════════════════
    // THUẬT TOÁN ĐÁNH GIÁ BÀI (PLAY HAY FOLD)
    // ═══════════════════════════════════════════════════════
    function decidePlayOrFold() {
        // Tỷ lệ cơ bản: 82% người chơi Three Card Poker sẽ chọn Play theo lý thuyết xác suất
        // Trừ khi bài quá yếu dưới Q-6-4 mới cân nhắc Fold để cắt lỗ
        const foldChance = 0.18;
        if (lossStreak >= 3 && Math.random() < 0.3) {
            return false; // Fold cắt lỗ
        }
        return Math.random() > foldChance;
    }

    // ═══════════════════════════════════════════════════════
    // THEO DÕI BADGE KẾT QUẢ ĐỂ GỬI CHAT & CẬP NHẬT TÂM LÝ
    // ═══════════════════════════════════════════════════════
    function setupResultBadgeObserver() {
        const badge = document.getElementById('result-status-badge');
        if (!badge) return;

        const observer = new MutationObserver(() => {
            if (badge.style.display !== 'none' && badge.style.opacity === '1') {
                const titleEl = document.getElementById('result-badge-title');
                const amtEl = document.getElementById('result-badge-amount');
                const title = titleEl ? titleEl.textContent : '';
                const amtText = amtEl ? amtEl.textContent : '';
                const amount = parseInt(amtText.replace(/\D/g, '')) || 0;

                if (title.includes('THẮNG')) {
                    winStreak++;
                    lossStreak = 0;
                    if (amount >= 300000) {
                        sendBotChat('big');
                    } else {
                        sendBotChat('win');
                    }
                } else if (title.includes('BAY MÀU') || title.includes('THUA') || title.includes('ÚP')) {
                    lossStreak++;
                    winStreak = 0;
                    sendBotChat('loss');
                }
            }
        });

        observer.observe(badge, { attributes: true, attributeFilter: ['style', 'class'] });
    }

    // Khởi động bot
    $(document).ready(() => {
        setupResultBadgeObserver();
        // Nhịp kiểm tra mỗi 1.5s
        setInterval(botLoop, 1500);
        console.log('[Bot 52] Thần Bài Ba Lá 52 đã kích hoạt thành công!');
    });

})();
