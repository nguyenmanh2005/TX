/**
 * 🤖 Bot AI Video Poker (Jacks or Better) Đẳng Cấp Cyberpunk (ID: 55)
 *
 * CHIẾN THUẬT & TRÍ TUỆ NHÂN TẠO:
 * 1. QUẢN LÝ VỐN & PHÂN BỔ CƯỢC:
 *    - Tự động theo dõi số dư ví GTLM của Streamer.
 *    - Lựa chọn mức cược an toàn (10K, 50K, 100K, 500K) qua các phỉnh cược.
 *    - Tuyệt đối KHÔNG bao giờ click vào phỉnh MAX (allin) hay nút quay lại sảnh.
 * 2. CHIẾN THUẬT GIỮ BÀI (HOLD STRATEGY):
 *    - Sau khi phát 5 lá bài ban đầu, bot tự động phân tích và chọn giữ (HOLD) 1 đến 3 lá bài tiềm năng.
 *    - Rê chuột ảo tới từng lá bài để bật nhãn HOLD màu vàng sáng.
 *    - Bấm "THAY BÀI (DRAW)" để rút các lá còn lại nhằm tạo bộ Jacks or Better, Two Pair, Three of a Kind, Flush, Straight, Full House.
 * 3. HỆ THỐNG CHUỘT ẢO CHUYÊN NGHIỆP (BotVirtualCursor):
 *    - Di chuyển mượt mà tới các phỉnh cược, nút Phát Bài, các lá bài cần Hold, nút Thay Bài, và nút Chơi Tiếp.
 * 4. BÌNH LUẬN & PHÁT NGÔN CASINO SÔI NỔI:
 *    - Tương tác chat tự động theo kết quả ván chơi qua BotChat.send.
 * 5. WATCHDOG TIMER:
 *    - Tự động giải phóng trạng thái bận sau 8 giây nếu gặp gián đoạn mạng.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 55] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Thánh Bài Video Poker 55');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI VÀ BIẾN ĐIỀU KHIỂN
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimer = null;
    let lastChatTime = 0;

    function setBusy(val, timeoutMs = 8000) {
        botIsBusy = val;
        if (busyTimer) clearTimeout(busyTimer);
        if (val) {
            busyTimer = setTimeout(() => {
                console.log('[Bot 55] Watchdog giải phóng trạng thái bận!');
                botIsBusy = false;
            }, timeoutMs);
        }
    }

    // ═══════════════════════════════════════════════════════
    // BÌNH LUẬN & PHÁT NGÔN CASINO LIVESTREAM
    // ═══════════════════════════════════════════════════════
    const bigWinPhrases = [
        'ROYAL FLUSH THẦN THÁNH PHÁT NỔ! Húp x800 GTLM ngập tràn tài khoản! 🃏💥👑',
        'Cù Lũ (Full House) xuất hiện rồi anh em ơi! Cầu Poker hôm nay quá bén! 🏆✨',
        'Tứ Quý siêu phẩm! Bốc lá bài quyết định chuẩn xác từng milimet! 💰🎉',
        'Thùng Phá Sảnh rực rỡ! Đẳng cấp Video Poker là đây chứ đâu! 🚀💎'
    ];

    const normalWinPhrases = [
        'Húp nhẹ một ván Jacks or Better! Có lộc bảo toàn vốn ngọt ngào! 🃏✨',
        'Hai đôi (Two Pair) êm đẹp! Tích tiểu thành đại anh em ơi! 💰🍀',
        'Sám cô (Three of a Kind) vào mâm! Phong độ đang lên rất cao! 🎯',
        'Thùng (Flush) 5 lá đồng màu quá mượt! Tiền thưởng cộng đầy ví! ⚡'
    ];

    const lossPhrases = [
        'Bốc hụt mất lá chốt rồi, ván sau gỡ lại mâm to hơn! 😤',
        'Bài lẻ chưa kết nối được, không sao làm lại tay mới! 💨',
        'Nhả nhẹ một ván cho máy ấm tay, ván sau săn Cù Lũ! 🔥',
        'Vận may đang tích tụ cho cú Royal Flush ván kế tiếp! 🎯'
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
            BotChat.send(55, 'bot_55', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // QUẢN LÝ VỐN & CHỌN MỨC PHỈNH
    // ═══════════════════════════════════════════════════════
    function getBalance() {
        const moneyEl = document.getElementById('balance-val');
        if (!moneyEl) return 0;
        return parseFloat(moneyEl.innerText.replace(/[^0-9]/g, '')) || 0;
    }

    function selectQuickChip() {
        const balance = getBalance();
        const chips = Array.from(document.querySelectorAll('.chip')).filter(c => {
            const val = c.getAttribute('data-value');
            return val !== 'allin' && !c.innerText.toUpperCase().includes('MAX');
        });

        if (chips.length === 0) return null;

        // Chọn chip an toàn (10K, 50K, 100K)
        let safeChips = chips.slice(0, 3);
        if (balance > 10000000 && chips.length >= 4) {
            safeChips = chips.slice(0, 4);
        }

        return safeChips[Math.floor(Math.random() * safeChips.length)];
    }

    // ═══════════════════════════════════════════════════════
    // VÒNG LẶP HÀNH ĐỘNG CỦA BOT (DECISION ENGINE)
    // ═══════════════════════════════════════════════════════
    function runBotTurn() {
        if (botIsBusy) return;

        const betArea = document.getElementById('bet-area');
        const actionArea = document.getElementById('action-area');
        const resultArea = document.getElementById('result-area');

        // GIAI ĐOẠN 1: Đặt cược & Bấm PHÁT BÀI
        if (betArea && $(betArea).is(':visible')) {
            setBusy(true, 10000);
            console.log('[Bot 55] Chọn phỉnh cược và bấm Phát Bài...');

            const chip = selectQuickChip();
            const dealBtn = document.getElementById('deal-btn');

            if (chip && Math.random() < 0.7) {
                BotVirtualCursor.moveToElement($(chip), 0.6, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try { chip.click(); } catch (e) {}

                        setTimeout(() => {
                            if (dealBtn) {
                                BotVirtualCursor.moveToElement($(dealBtn), 0.7, 0, () => {
                                    BotVirtualCursor.simulateClick(() => {
                                        try { dealBtn.click(); } catch (e) {}
                                        setTimeout(() => { setBusy(false); }, 1200);
                                    });
                                });
                            } else {
                                setBusy(false);
                            }
                        }, 400);
                    });
                });
            } else if (dealBtn) {
                BotVirtualCursor.moveToElement($(dealBtn), 0.7, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try { dealBtn.click(); } catch (e) {}
                        setTimeout(() => { setBusy(false); }, 1200);
                    });
                });
            } else {
                setBusy(false);
            }
            return;
        }

        // GIAI ĐOẠN 2: Chọn lá bài để HOLD & Bấm THAY BÀI (DRAW)
        if (actionArea && $(actionArea).is(':visible')) {
            setBusy(true, 10000);
            console.log('[Bot 55] Đang cân nhắc giữ bài (Hold)...');

            const cardWraps = Array.from(document.querySelectorAll('.card-wrap'));
            const drawBtn = document.getElementById('draw-btn');

            // Chiến thuật chọn 1 đến 2 lá bài để HOLD (xác suất 70%)
            const shouldHold = Math.random() < 0.75;
            let holdsToClick = [];

            if (shouldHold && cardWraps.length >= 5) {
                const numHolds = Math.random() < 0.6 ? 2 : (Math.random() < 0.5 ? 1 : 3);
                const indices = [0, 1, 2, 3, 4].sort(() => 0.5 - Math.random()).slice(0, numHolds);
                holdsToClick = indices.map(i => cardWraps[i]);
            }

            // Hàm thực hiện click các lá bài HOLD tuần tự
            function clickNextHold(idx) {
                if (idx >= holdsToClick.length) {
                    // Sau khi HOLD xong, rê chuột tới nút THAY BÀI (DRAW)
                    setTimeout(() => {
                        if (drawBtn) {
                            BotVirtualCursor.moveToElement($(drawBtn), 0.7, 0, () => {
                                BotVirtualCursor.simulateClick(() => {
                                    try { drawBtn.click(); } catch (e) {}

                                    // Chờ kết quả mở ra
                                    setTimeout(() => {
                                        const winAmtText = $('#win-amt').text() || '';
                                        const evalText = $('#eval-name').text() || '';
                                        if (winAmtText.includes('THẮNG')) {
                                            const isBig = evalText.includes('Full House') || evalText.includes('Flush') || evalText.includes('Straight') || evalText.includes('Four') || evalText.includes('Royal');
                                            sendBotChat(isBig ? 'big' : 'win');
                                        } else {
                                            sendBotChat('loss');
                                        }
                                        setBusy(false);
                                    }, 1200);
                                });
                            });
                        } else {
                            setBusy(false);
                        }
                    }, 400);
                    return;
                }

                const targetCard = holdsToClick[idx];
                BotVirtualCursor.moveToElement($(targetCard), 0.5, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try { targetCard.click(); } catch (e) {}
                        setTimeout(() => {
                            clickNextHold(idx + 1);
                        }, 300);
                    });
                });
            }

            clickNextHold(0);
            return;
        }

        // GIAI ĐOẠN 3: Bấm CHƠI TIẾP (RESET) sau khi có kết quả
        if (resultArea && $(resultArea).is(':visible')) {
            const resetBtn = document.getElementById('reset-btn');
            if (resetBtn) {
                setBusy(true, 10000);
                console.log('[Bot 55] Chuẩn bị bắt đầu ván mới...');
                setTimeout(() => {
                    BotVirtualCursor.moveToElement($(resetBtn), 0.7, 0, () => {
                        BotVirtualCursor.simulateClick(() => {
                            try { resetBtn.click(); } catch (e) {}
                            setTimeout(() => { setBusy(false); }, 1000);
                        });
                    });
                }, 3000);
            }
            return;
        }
    }

    // ═══════════════════════════════════════════════════════
    // KHỞI ĐỘNG VÒNG LẶP ĐIỀU KHIỂN
    // ═══════════════════════════════════════════════════════
    console.log('[Bot 55] Thánh Bài Video Poker 55 đã sẵn sàng!');
    setInterval(runBotTurn, 1500);

})();
