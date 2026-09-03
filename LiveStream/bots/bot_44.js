/**
 * 🤖 Bot AI Pontoon (UK Blackjack Royale) Thông Minh (ID: 44)
 *
 * CHIẾN THUẬT & TRÍ THÔNG MINH:
 * 1. QUẢN LÝ VỐN THÔNG MINH (Bankroll Management):
 *    - Tự động thay đổi cược dựa trên chuỗi thắng/thua: 10K -> 50K -> 100K -> 500K.
 *    - Khi thua: Trở về mức cơ bản an toàn 10K.
 * 2. CHIẾN LƯỢC CHƠI BÀI PONTOON CHUẨN XÁC:
 *    - Luật Pontoon: Dưới 15 điểm BẮT BUỘC phải RÚT (Twist).
 *    - Từ 15 - 16 điểm:
 *        + Nếu đã có 4 lá: Rút tiếp để săn 5-Card Trick (thắng tuyệt đối).
 *        + Nếu 2-3 lá: Cân nhắc dừng (Stick) để tránh quắc (bust).
 *    - Từ 17 điểm trở lên: DỪNG (Stick) ngay để bảo toàn điểm số cạnh tranh với Dealer.
 * 3. HỆ THỐNG VÒNG LẶP & ĐIỀU KHIỂN:
 *    - Nhận biết 3 trạng thái: IDLE (Chia bài) -> IN_HAND (Rút/Dừng) -> FINISHED (Ván mới).
 *    - Không bao giờ click bừa vào nút không hợp lệ.
 * 4. WATCHDOG & CHỐNG KẸT:
 *    - Tự động giải phóng bot sau 3s nếu có sự cố.
 *    - Chat tương tác phong cách dân chơi Casino thượng lưu.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 44] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Pontoon Master 44');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI VÀ CHỈ SỐ BOT
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimer = null;
    let lastChatTime = 0;
    let winStreak = 0;
    let lossStreak = 0;

    function setBusy(val, timeoutMs = 3000) {
        botIsBusy = val;
        if (busyTimer) clearTimeout(busyTimer);
        if (val) {
            busyTimer = setTimeout(() => {
                botIsBusy = false;
            }, timeoutMs);
        }
    }

    // ═══════════════════════════════════════════════════════
    // TÍNH ĐIỂM BÀI PONTOON CỦA NGƯỜI CHƠI
    // ═══════════════════════════════════════════════════════
    function getPlayerCardsAndScore() {
        const cardEls = Array.from(document.querySelectorAll('#playerHand .card:not(.back)'));
        let cards = [];

        cardEls.forEach(el => {
            const dataCard = el.getAttribute('data-card');
            if (dataCard) {
                cards.push(dataCard);
            } else {
                // Fallback đọc từ text content
                const valEl = el.querySelector('div div:first-child');
                const val = valEl ? valEl.textContent.trim() : '';
                if (val) cards.push(val + 's');
            }
        });

        let score = 0;
        let aces = 0;

        cards.forEach(c => {
            let v = c.slice(0, -1);
            if (v === 'A') {
                aces++;
                score += 11;
            } else if (['J', 'Q', 'K'].includes(v)) {
                score += 10;
            } else {
                score += parseInt(v) || 0;
            }
        });

        while (score > 21 && aces > 0) {
            score -= 10;
            aces--;
        }

        return { cards, count: cards.length, score };
    }

    // ═══════════════════════════════════════════════════════
    // PHÁT HIỆN TRẠNG THÁI GAME
    // ═══════════════════════════════════════════════════════
    const STATE = { DEAL: 'deal', IN_HAND: 'in_hand', NEW_GAME: 'new_game', NONE: 'none' };

    function detectState() {
        const dealBtn = document.getElementById('dealBtn');
        const twistBtn = document.getElementById('twistBtn');
        const stickBtn = document.getElementById('stickBtn');
        const newBtn = document.getElementById('newBtn');

        if (newBtn && window.getComputedStyle(newBtn).display !== 'none') {
            return STATE.NEW_GAME;
        }

        if (twistBtn && stickBtn && window.getComputedStyle(twistBtn).display !== 'none') {
            return STATE.IN_HAND;
        }

        if (dealBtn && window.getComputedStyle(dealBtn).display !== 'none') {
            return STATE.DEAL;
        }

        return STATE.NONE;
    }

    // ═══════════════════════════════════════════════════════
    // CHAT PHONG CÁCH CASINO ROYALE
    // ═══════════════════════════════════════════════════════
    const winPhrases = [
        'Húp trọn ván Pontoon ngọt ngào! Điểm cao hơn Dealer là lúa về ví! 🃏💰',
        'Chiến thuật đếm nút chuẩn chỉnh, Dealer chỉ biết ngậm ngùi! ✨',
        'Pontoon 21 nút hoặc 5-Card Trick, húp sạch tiền bàn! 🏆',
        'Dừng đúng lúc, ăn tiền đúng chỗ! GTLM về như suối! 🚀',
        'Thần bài tái xuất, chuỗi thắng lại tiếp tục! 😎'
    ];

    const losePhrases = [
        'Quá tam ba bận, ván sau bắt bài Dealer gom lại! 😤',
        'Tham thì thâm, ráng rút thêm tí là quắc! Không sao ván sau phục thù! 😅',
        'Dealer quái chiêu thật, ván sau tất tay lấy lại vốn! 🎯',
        'Thua một ván không sờn lòng, quản lý vốn là đường dài vô địch! 💪'
    ];

    function sendBotChat(isWin) {
        const now = Date.now();
        if (now - lastChatTime < 14000) return;
        if (Math.random() > 0.5) return;

        const list = isWin ? winPhrases : losePhrases;
        const msg = list[Math.floor(Math.random() * list.length)];
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(44, 'bot_44', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // HÀNH ĐỘNG 1: CHỌN CHIP VÀ CHIA BÀI
    // ═══════════════════════════════════════════════════════
    function doDeal() {
        const dealBtn = document.getElementById('dealBtn');
        if (!dealBtn || dealBtn.disabled) return;

        setBusy(true, 3000);

        // Quản lý vốn theo chuỗi thắng
        let targetValue = 10000;
        if (winStreak >= 3) targetValue = 500000;
        else if (winStreak >= 2) targetValue = 100000;
        else if (winStreak >= 1) targetValue = 50000;
        else targetValue = 10000;

        const chips = Array.from(document.querySelectorAll('#chipSelector .chip'));
        let targetChip = chips.find(c => parseInt(c.getAttribute('data-value') || '0') === targetValue);
        if (!targetChip) {
            targetChip = chips.find(c => {
                const v = parseInt(c.getAttribute('data-value') || '0');
                return v > 0 && v <= 100000;
            }) || chips[0];
        }

        if (targetChip) {
            BotVirtualCursor.moveToElement($(targetChip), 0.4, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { targetChip.click(); } catch (e) { }

                        setTimeout(() => {
                            BotVirtualCursor.moveToElement($(dealBtn), 0.45, 0, () => {
                                setTimeout(() => {
                                    BotVirtualCursor.simulateClick(() => {
                                        try { dealBtn.click(); } catch (e) { }
                                        setTimeout(() => { setBusy(false); }, 700);
                                    });
                                }, 80);
                            });
                        }, 180);
                    });
                }, 80);
            });
        } else {
            BotVirtualCursor.moveToElement($(dealBtn), 0.45, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { dealBtn.click(); } catch (e) { }
                        setTimeout(() => { setBusy(false); }, 700);
                    });
                }, 80);
            });
        }
    }

    // ═══════════════════════════════════════════════════════
    // HÀNH ĐỘNG 2: XỬ LÝ LƯỢT CHƠI (TWIST HOẶC STICK)
    // ═══════════════════════════════════════════════════════
    function doInHand() {
        const twistBtn = document.getElementById('twistBtn');
        const stickBtn = document.getElementById('stickBtn');

        if (!twistBtn || !stickBtn) return;

        const handInfo = getPlayerCardsAndScore();
        const score = handInfo.score;
        const cardCount = handInfo.count;

        // Nếu chưa nạp xong bài lên DOM thì chờ
        if (cardCount < 2) return;

        setBusy(true, 3000);

        let shouldTwist = false;

        // Luật Pontoon:
        // - Dưới 15 điểm: BẮT BUỘC RÚT
        if (score < 15) {
            shouldTwist = true;
        }
        // - Đạt 15 hoặc 16 điểm:
        else if (score === 15 || score === 16) {
            // Nếu đã có 4 lá, rút thêm lá thứ 5 để săn 5-Card Trick (thắng tuyệt đối)
            if (cardCount === 4) {
                shouldTwist = true;
            } else {
                // Đủ 15 điểm thì có thể dừng an toàn
                shouldTwist = false;
            }
        }
        // - Từ 17 điểm trở lên: DỪNG (Stick) để không bị quắc
        else {
            shouldTwist = false;
        }

        const targetBtn = shouldTwist ? twistBtn : stickBtn;

        // Thời gian suy nghĩ tự nhiên
        const thinkMs = 350 + Math.random() * 300;

        setTimeout(() => {
            BotVirtualCursor.moveToElement($(targetBtn), 0.4, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { targetBtn.click(); } catch (e) { }
                        setTimeout(() => { setBusy(false); }, 650);
                    });
                }, 80);
            });
        }, thinkMs);
    }

    // ═══════════════════════════════════════════════════════
    // HÀNH ĐỘNG 3: VÁN MỚI (NEW GAME)
    // ═══════════════════════════════════════════════════════
    function doNewGame() {
        const newBtn = document.getElementById('newBtn');
        if (!newBtn) return;

        setBusy(true, 3000);

        // Chờ 1.5s cho hiệu ứng thông báo và người xem nhìn kết quả
        setTimeout(() => {
            BotVirtualCursor.moveToElement($(newBtn), 0.45, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { newBtn.click(); } catch (e) { }
                        setTimeout(() => { setBusy(false); }, 600);
                    });
                }, 80);
            });
        }, 1500);
    }

    // ═══════════════════════════════════════════════════════
    // THEO DÕI KẾT QUẢ TỪ BADGE HOẶC DOM
    // ═══════════════════════════════════════════════════════
    function setupResultWatcher() {
        // Lắng nghe sự kiện custom hoặc badge
        const badge = document.getElementById('result-status-badge');
        if (badge) {
            const observer = new MutationObserver(() => {
                if (badge.style.display !== 'none' && badge.style.opacity === '1') {
                    const title = (document.getElementById('result-badge-title') || {}).textContent || '';
                    if (title.includes('THẮNG')) {
                        winStreak++;
                        lossStreak = 0;
                        sendBotChat(true);
                    } else if (title.includes('THUA')) {
                        lossStreak++;
                        winStreak = 0;
                        sendBotChat(false);
                    }
                }
            });
            observer.observe(badge, { attributes: true, attributeFilter: ['style'] });
        }
    }

    // ═══════════════════════════════════════════════════════
    // VÒNG LẶP CHÍNH (Game Loop)
    // ═══════════════════════════════════════════════════════
    function playTurn() {
        if (botIsBusy) return;

        const state = detectState();
        if (state === STATE.DEAL) {
            doDeal();
        } else if (state === STATE.IN_HAND) {
            doInHand();
        } else if (state === STATE.NEW_GAME) {
            doNewGame();
        }
    }

    // Khởi tạo
    setupResultWatcher();

    // Tần suất quét nhịp nhàng mỗi 700ms
    setInterval(playTurn, 700);
    setTimeout(playTurn, 1000);

})();
