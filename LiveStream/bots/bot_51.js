/**
 * 🤖 Bot AI Sicbo (Tài Xỉu / Xúc Xắc Cổ Điển) Siêu Thông Minh (ID: 51)
 *
 * CHIẾN THUẬT & TRÍ TUỆ ĐỈNH CAO:
 * 1. QUẢN LÝ VỐN & PHÂN BỔ CƯỢC:
 *    - Tự động theo dõi số dư ví GTLM của Streamer.
 *    - Chọn mức cược linh hoạt (10K, 50K, 100K, 500K) qua các nút `.btn-quick-bet`.
 *    - Tránh spam ALL IN bừa bãi, bảo toàn vốn để livestream 24/7 bền bỉ.
 * 2. CHIẾN THUẬT ĐẶT CỬA SICBO THƯỢNG THỪA:
 *    - 75% ván: Đặt vào các cửa cân bằng xác suất cao: Tài (Big 11-17), Xỉu (Small 4-10), Chẵn (Even), Lẻ (Odd).
 *    - 25% ván: Đặt kèm 1 cửa tài lộc thưởng lớn: Bất kỳ bộ ba (`any_triple` x30), Tổng may mắn (`total_9` -> `total_12`), hoặc Số đơn (`single_1` -> `single_6`).
 *    - Có tính toán chuỗi cầu (Tài/Xỉu) linh hoạt.
 * 3. HỆ THỐNG CHUỘT ẢO CHUYÊN NGHIỆP (BotVirtualCursor):
 *    - Di chuyển mượt mà tự nhiên tới nút chọn phỉnh cược -> các ô cược -> nút "LẮC XÚC XẮC".
 *    - Tuyệt đối KHÔNG click lung tung ra ngoài, không click nút "QUAY LẠI SẢNH" hay "DỌN BÀN" bừa bãi.
 * 4. TƯƠNG TÁC CHAT & CẢM XÚC:
 *    - Bắt sự kiện kết quả từ `#result-status-badge` để phát ngôn câu thoại Casino cực chất.
 *    - Dừng 3.5s - 4.5s cho người xem chiêm ngưỡng xúc xắc và nhận thưởng.
 * 5. WATCHDOG CHỐNG KẸT:
 *    - Tự động phục hồi trạng thái sau 8 giây nếu gặp gián đoạn mạng.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 51] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Thần Đổ Xúc Xắc 51');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI VÀ BIẾN ĐIỀU KHIỂN
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimer = null;
    let lastChatTime = 0;
    let winStreak = 0;
    let lossStreak = 0;
    let lastBetType = null;

    function setBusy(val, timeoutMs = 8000) {
        botIsBusy = val;
        if (busyTimer) clearTimeout(busyTimer);
        if (val) {
            busyTimer = setTimeout(() => {
                console.log('[Bot 51] Watchdog giải phóng trạng thái bận!');
                botIsBusy = false;
                if (typeof isRolling !== 'undefined') isRolling = false;
            }, timeoutMs);
        }
    }

    // ═══════════════════════════════════════════════════════
    // BÌNH LUẬN & PHÁT NGÔN CASINO LIVESTREAM
    // ═══════════════════════════════════════════════════════
    const bigWinPhrases = [
        'Nổ hũ bộ ba rồi anh em ơi! x30 GTLM thơm nức mũi! 🎲💥🔥',
        'Cầu chuẩn như sách giáo khoa! Húp trọn mâm xúc xắc vàng! 🏆✨',
        'Xúc xắc lắc ra kim cương! Tay thơm bạc tỷ đây rồi! 💵🎉',
        'Bảo rồi theo cầu là ăn đậm! Anh em vào xin vía may mắn nào! 🚀💎'
    ];

    const normalWinPhrases = [
        'Có lộc có lộc! Đoán chuẩn cửa rồi, cộng GTLM tươi rói! 🎲✨',
        'Tiếng xúc xắc leng keng nghe sướng tai quá! Tiếp đà chiến thắng! 💰',
        'Húp nhẹ ván này! Cầu đang chạy quá êm! 🍀🔥',
        'Thắng tiếp một ván nữa! Đang trên đà phong độ đỉnh cao! 🎯'
    ];

    const lossPhrases = [
        'Xúc xắc bẻ cầu một nhịp, ván sau bắt lại ngay! 😤',
        'Hụt một phát xíu xiu, bình tĩnh gỡ lại gấp bội! 💨',
        'Cầu đang đảo chiều, ván sau đổi cửa lụm lúa liền! 🔥🎲',
        'Không sao hết, tay chơi lớn không nản lòng! Chuẩn bị tay mới! 🎯'
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
            BotChat.send(51, 'bot_51', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // QUẢN LÝ VỐN & CHỌN MỨC CƯỢC
    // ═══════════════════════════════════════════════════════
    function getBalance() {
        const moneyEl = document.getElementById('balance-val');
        if (!moneyEl) return 1000000;
        return parseInt(moneyEl.innerText.replace(/\D/g, '')) || 0;
    }

    function selectOptimalChipButton() {
        const balance = getBalance();
        const chips = Array.from(document.querySelectorAll('.btn-quick-bet'));
        if (chips.length === 0) return null;

        // Lọc bỏ ALL IN để bot không all in vô tội vạ
        const safeChips = chips.filter(b => {
            const val = b.getAttribute('data-val');
            return val && val !== 'ALL';
        });
        if (safeChips.length === 0) return null;

        let targetChip = safeChips[0]; // Mặc định 10K

        if (balance > 10000000) {
            // Số dư dồi dào > 10M: Chọn 100K, 500K hoặc 1M
            const highChips = safeChips.filter(b => {
                const val = parseInt(b.getAttribute('data-val')) || 0;
                return val >= 100000 && val <= 1000000;
            });
            if (highChips.length > 0) targetChip = highChips[Math.floor(Math.random() * highChips.length)];
        } else if (balance > 2000000) {
            // Số dư khá > 2M: Chọn 50K hoặc 100K
            const midChips = safeChips.filter(b => {
                const val = parseInt(b.getAttribute('data-val')) || 0;
                return val >= 50000 && val <= 100000;
            });
            if (midChips.length > 0) targetChip = midChips[Math.floor(Math.random() * midChips.length)];
        } else {
            // Số dư thấp: Chọn 10K hoặc 50K
            const lowChips = safeChips.filter(b => {
                const val = parseInt(b.getAttribute('data-val')) || 0;
                return val <= 50000;
            });
            if (lowChips.length > 0) targetChip = lowChips[Math.floor(Math.random() * lowChips.length)];
        }

        return targetChip;
    }

    // ═══════════════════════════════════════════════════════
    // LỰA CHỌN CỬA CƯỢC THÔNG MINH
    // ═══════════════════════════════════════════════════════
    function pickBetItems() {
        const mainBetTypes = ['small', 'big', 'odd', 'even'];
        const luckyBetTypes = [
            'any_triple',
            'single_1', 'single_2', 'single_3', 'single_4', 'single_5', 'single_6',
            'total_9', 'total_10', 'total_11', 'total_12'
        ];

        let selectedTypes = [];

        // 1. Chọn 1 cửa chính: Theo cầu hoặc bẻ cầu
        let primaryType = mainBetTypes[Math.floor(Math.random() * mainBetTypes.length)];
        if (lastBetType && winStreak > 0 && Math.random() < 0.65) {
            // Đang thắng: Nuôi tiếp cửa cũ (theo cầu)
            primaryType = lastBetType;
        } else if (lastBetType && lossStreak >= 2) {
            // Đang thua 2 ván: Đổi cầu (bẻ cầu)
            if (lastBetType === 'small') primaryType = 'big';
            else if (lastBetType === 'big') primaryType = 'small';
            else if (lastBetType === 'odd') primaryType = 'even';
            else if (lastBetType === 'even') primaryType = 'odd';
        }

        lastBetType = primaryType;
        selectedTypes.push(primaryType);

        // 2. Xác suất 30% đánh thêm 1 cửa phụ để săn thưởng lớn
        if (Math.random() < 0.3) {
            const luckyType = luckyBetTypes[Math.floor(Math.random() * luckyBetTypes.length)];
            selectedTypes.push(luckyType);
        }

        // Tìm các DOM element tương ứng
        const items = [];
        selectedTypes.forEach(t => {
            const el = document.querySelector(`.bet-item[data-type="${t}"]`);
            if (el) items.push(el);
        });

        return items;
    }

    // ═══════════════════════════════════════════════════════
    // VÒNG LẶP CHÍNH CỦA BOT (GAMEPLAY LOOP)
    // ═══════════════════════════════════════════════════════
    function botLoop() {
        if (botIsBusy) return;
        if (typeof isRolling !== 'undefined' && isRolling) return;

        const rollBtn = document.getElementById('roll-btn');
        if (!rollBtn || rollBtn.disabled) return;

        // Bắt đầu 1 chu kỳ cược
        setBusy(true, 10000);

        // BƯỚC 1: RÊ CHUỘT CHỌN MỨC CHIP
        const chipBtn = selectOptimalChipButton();
        if (!chipBtn) {
            setBusy(false);
            return;
        }

        BotVirtualCursor.moveToElement($(chipBtn), 0.35, 0, () => {
            setTimeout(() => {
                BotVirtualCursor.simulateClick(() => {
                    try { chipBtn.click(); } catch (e) { }

                    // BƯỚC 2: RÊ CHUỘT ĐẶT CỬA CƯỢC
                    setTimeout(() => {
                        const betItems = pickBetItems();
                        placeBetsSequentially(betItems, () => {
                            // BƯỚC 3: RÊ CHUỘT BẤM "LẮC XÚC XẮC"
                            setTimeout(() => {
                                BotVirtualCursor.moveToElement($(rollBtn), 0.45, 0, () => {
                                    setTimeout(() => {
                                        BotVirtualCursor.simulateClick(() => {
                                            try { rollBtn.click(); } catch (e) { }

                                            // Đợi kết quả đổ xúc xắc (1.2s) + xem badge kết quả (3.5s) = 4.7s
                                            setTimeout(() => {
                                                setBusy(false);
                                            }, 4500);
                                        });
                                    }, 120);
                                });
                            }, 400);
                        });
                    }, 350);
                });
            }, 100);
        });
    }

    function placeBetsSequentially(items, onComplete) {
        if (!items || items.length === 0) {
            if (onComplete) onComplete();
            return;
        }

        let idx = 0;
        function betNext() {
            if (idx >= items.length) {
                if (onComplete) onComplete();
                return;
            }

            const item = items[idx];
            idx++;

            BotVirtualCursor.moveToElement($(item), 0.4, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { item.click(); } catch (e) { }
                        setTimeout(betNext, 350 + Math.random() * 200);
                    });
                }, 100);
            });
        }

        betNext();
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
                } else if (title.includes('BAY MÀU') || title.includes('TIẾC') || title.includes('THUA')) {
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
        setInterval(botLoop, 1600);
        console.log('[Bot 51] Thần Đổ Xúc Xắc 51 đã kích hoạt thành công!');
    });

})();
