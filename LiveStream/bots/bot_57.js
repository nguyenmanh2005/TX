/**
 * 🤖 Bot AI Casino War - Trận Chiến Bài Tây (ID: 57)
 *
 * CHIẾN THUẬT & TRÍ TUỆ NHÂN TẠO:
 * 1. QUẢN LÝ VỐN & PHÂN BỔ CƯỢC:
 *    - Theo dõi số dư ví GTLM của Streamer.
 *    - Chọn mức cược an toàn (10K, 50K, 100K, 500K) qua các phỉnh cược.
 *    - Tuyệt đối không click vào phỉnh MAX (all-in).
 * 2. BẢN LĨNH CHIẾN TRƯỜNG KHI HÒA BÀI (TIE BATTLE):
 *    - Khi hòa bài, bot có 75% quyết định THAM CHIẾN (WAR) để săn thưởng x2, và 25% chọn ĐẦU HÀNG để bảo toàn vốn.
 * 3. HỆ THỐNG CHUỘT ẢO CHUYÊN NGHIỆP (BotVirtualCursor):
 *    - Di chuyển mượt mà tới các phỉnh cược, nút CHIA BÀI, nút THAM CHIẾN / ĐẦU HÀNG, và nút VÁN MỚI.
 * 4. BÌNH LUẬN & TƯƠNG TÁC CASINO SÔI NỔI:
 *    - Tự động phát ngôn nhận định thế trận, gáy chiến thắng hoặc than thở hóm hỉnh qua BotChat.send.
 * 5. WATCHDOG TIMER:
 *    - Tự động giải phóng trạng thái bận sau 8 giây nếu gặp gián đoạn.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 57] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Thần Bài Casino War 57');

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
                console.log('[Bot 57] Watchdog giải phóng trạng thái bận!');
                botIsBusy = false;
            }, timeoutMs);
        }
    }

    // ═══════════════════════════════════════════════════════
    // BÌNH LUẬN & PHÁT NGÔN CASINO LIVESTREAM
    // ═══════════════════════════════════════════════════════
    const warWinPhrases = [
        'THAM CHIẾN TOÀN THẮNG! Húp trọn mâm to GTLM của Dealer! 👑💥⚔️',
        'Đẳng cấp Casino War! Rút thêm lá bài quyết định đè bẹp nhà cái! 🏆✨',
        'Chiến tranh bùng nổ và phần thắng thuộc về Streamer! Quá rực rỡ! 🚀💰'
    ];

    const normalWinPhrases = [
        'Một nốt nhạc hạ gục Dealer! Bài lớn hơn là có GTLM đút túi! 🃏✨',
        'Thắng ngọt ngào tay chơi ơi! Cầu bài đang rất ủng hộ! 💸🍀',
        'Lá bài tẩy đẹp mê ly, tiền thưởng cộng về ví rồi! ⚡'
    ];

    const tiePhrases = [
        'Hòa bài rồi anh em ơi! Căng như dây đàn, vào thế chiến tranh! ⚔️🔥',
        'Cân kèo từng điểm số! Chuẩn bị tham chiến khô máu với Dealer! 🎯',
        'Hòa rồi, phải ra đòn quyết định thôi!'
    ];

    const lossPhrases = [
        'Gặp ngay lá bài cứng cựa của Dealer, ván sau gỡ lại mâm to hơn! 😤',
        'Thua non nửa nút, không sao làm lại tay mới! 💨',
        'Nhả nhẹ một ván, chuẩn bị vào thế trận lội ngược dòng! 🔥'
    ];

    function sendBotChat(type) {
        const now = Date.now();
        if (now - lastChatTime < 10000) return;
        if (type !== 'war_win' && Math.random() > 0.6) return;

        let list = normalWinPhrases;
        if (type === 'war_win') list = warWinPhrases;
        else if (type === 'tie') list = tiePhrases;
        else if (type === 'loss') list = lossPhrases;

        const msg = list[Math.floor(Math.random() * list.length)];
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(57, 'bot_57', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // QUẢN LÝ VỐN & CHỌN MỨC PHỈNH
    // ═══════════════════════════════════════════════════════
    function selectSafeChip() {
        const chips = Array.from(document.querySelectorAll('.chip')).filter(c => {
            const val = c.getAttribute('data-value');
            return val !== 'allin' && !c.innerText.toUpperCase().includes('MAX');
        });

        if (chips.length === 0) return null;

        // Chọn các mức cược an toàn 10K, 50K, 100K, 500K
        const safeChips = chips.slice(0, 4);
        return safeChips[Math.floor(Math.random() * safeChips.length)];
    }

    // ═══════════════════════════════════════════════════════
    // VÒNG LẶP HÀNH ĐỘNG CỦA BOT (DECISION ENGINE)
    // ═══════════════════════════════════════════════════════
    function runBotTurn() {
        if (botIsBusy) return;

        const betForm = document.getElementById('bet-form');
        const dealBtn = document.getElementById('deal-btn');
        const tieControls = document.getElementById('tie-controls');
        const resultArea = document.getElementById('result-area');
        const resetBtn = document.getElementById('reset-btn');

        // GIAI ĐOẠN 1: Đặt cược & Bấm CHIA BÀI
        if (betForm && $(betForm).is(':visible') && dealBtn && $(dealBtn).is(':visible')) {
            setBusy(true, 8000);
            console.log('[Bot 57] Chọn phỉnh cược và bấm Chia Bài...');

            const chip = selectSafeChip();
            if (chip && Math.random() < 0.6) {
                BotVirtualCursor.moveToElement($(chip), 0.5, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try { chip.click(); } catch (e) {}

                        setTimeout(() => {
                            BotVirtualCursor.moveToElement($(dealBtn), 0.6, 0, () => {
                                BotVirtualCursor.simulateClick(() => {
                                    console.log('[Bot 57] Kích hoạt Chia Bài!');
                                    try { dealBtn.click(); } catch (e) {}
                                    setTimeout(() => { setBusy(false); }, 1500);
                                });
                            });
                        }, 350);
                    });
                });
            } else {
                BotVirtualCursor.moveToElement($(dealBtn), 0.6, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        console.log('[Bot 57] Kích hoạt Chia Bài trực tiếp!');
                        try { dealBtn.click(); } catch (e) {}
                        setTimeout(() => { setBusy(false); }, 1500);
                    });
                });
            }
            return;
        }

        // GIAI ĐOẠN 2: Xử lý HÒA BÀI (Tie Controls)
        if (tieControls && $(tieControls).is(':visible')) {
            setBusy(true, 8000);
            sendBotChat('tie');

            const warBtn = document.getElementById('war-btn');
            const surrenderBtn = document.getElementById('surrender-btn');

            // 75% chọn Tham Chiến (WAR), 25% chọn Đầu Hàng (Surrender)
            const chooseWar = Math.random() < 0.75;
            const targetBtn = chooseWar ? warBtn : surrenderBtn;

            console.log(`[Bot 57] Quyết định tình huống Hòa: ${chooseWar ? 'THAM CHIẾN (WAR)' : 'ĐẦU HÀNG'}`);

            if (targetBtn) {
                setTimeout(() => {
                    BotVirtualCursor.moveToElement($(targetBtn), 0.7, 0, () => {
                        BotVirtualCursor.simulateClick(() => {
                            try { targetBtn.click(); } catch (e) {}
                            setTimeout(() => { setBusy(false); }, 1800);
                        });
                    });
                }, 800);
            } else {
                setBusy(false);
            }
            return;
        }

        // GIAI ĐOẠN 3: Kết thúc ván & Bấm VÁN MỚI
        if (resultArea && $(resultArea).is(':visible') && resetBtn && $(resetBtn).is(':visible')) {
            setBusy(true, 8000);

            // Kiểm tra kết quả phản hồi chat
            const badge = document.getElementById('result-status-badge');
            if (badge) {
                const txt = badge.innerText || '';
                if (txt.includes('ĐẠI THẮNG CHIẾN TRANH')) {
                    sendBotChat('war_win');
                } else if (txt.includes('CHIẾN THẮNG')) {
                    sendBotChat('win');
                } else if (txt.includes('BAY MÀU') || txt.includes('THUA')) {
                    sendBotChat('loss');
                }
            }

            console.log('[Bot 57] Nghỉ ngơi chiêm ngưỡng kết quả, chuẩn bị Ván Mới...');
            setTimeout(() => {
                BotVirtualCursor.moveToElement($(resetBtn), 0.7, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        console.log('[Bot 57] Kích hoạt Ván Mới!');
                        try { resetBtn.click(); } catch (e) {}
                        setTimeout(() => { setBusy(false); }, 1000);
                    });
                });
            }, 3000);
            return;
        }
    }

    // ═══════════════════════════════════════════════════════
    // KHỞI CHẠY BOT ENGINE
    // ═══════════════════════════════════════════════════════
    console.log('[Bot 57] Khởi động AI Casino War thành công!');
    setTimeout(() => {
        runBotTurn();
    }, 1500);

    setInterval(() => {
        runBotTurn();
    }, 3500);

})();
