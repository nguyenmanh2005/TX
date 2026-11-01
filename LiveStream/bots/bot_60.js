/**
 * 🤖 Bot AI Tháp Thần Bài - Leo Tháp Vô Cực (Bàn Live ID: 61)
 *
 * CHIẾN THUẬT & TRÍ TUỆ NHÂN TẠO CAO CẤP:
 * 1. QUẢN LÝ ĐỘI HÌNH & TƯỚNG LEO THÁP THÔNG MINH:
 *    - Tầng thường (1-9, 11-19...): Luân chuyển linh hoạt các tướng farm tài nguyên & duy trì sức mạnh (Thần Tài, Đạo Tặc, Giả Kim, Kiếm Thánh).
 *    - Tầng Boss (10, 20, 30...): Tự động đổi tướng chuyên diệt Boss (Kiếm Thánh, Cuồng Sĩ, Ninja, Pháp Sư).
 *    - Tuyệt kỹ Lãnh Tụ (#btnSkill): Tự động kích hoạt khi vào tầng Boss hoặc khi sẵn sàng để tối đa hóa cơ hội chiến thắng.
 * 2. TƯƠNG TÁC GIAO DIỆN & VÒNG ĐỜI TRẬN ĐẤU CHUẨN XÁC:
 *    - Trạng thái Chiến Đấu: Tự động chờ đợi trận chiến 3v3 kết thúc (3.5s snappy animation).
 *    - Màn hình Kết Quả (#ovResult):
 *      + Thắng: Chat ăn mừng chuẩn Casino Rule 5.3 (húp GTLM, quét sạch quái...), di chuyển chuột ảo tới nút Lên Tầng (#btnAdvanceFloor / .btn-advance-floor) và click.
 *      + Thua: Chat an ủi (bay màu, còn thở là còn gỡ...), di chuyển chuột ảo tới nút Thử Lại (#btnRetryFloor / .btn-retry-floor) và click.
 *      + Kèm cơ chế tự giải cứu trực tiếp (Direct Fallback Action) gọi Game.nextFloor() / Game.closeOverlay() nếu giao diện trễ.
 *    - Sảnh Đấu (Ready Phase): Di chuyển chuột ảo tới #btnStart và click dứt khoát.
 * 3. HỆ THỐNG CHUỘT ẢO AN TOÀN TUYỆT ĐỐI (BotVirtualCursor):
 *    - moveAndClick dạng Promise với Safety Timeout tích hợp 100%, không phụ thuộc jQuery :visible, không bao giờ đơ hay deadlock.
 * 4. BÌNH LUẬN & PHÁT NGÔN CASINO SÔI NỔI (BotChat.send / api_spectator.php / postMessage):
 *    - Tuân thủ 100% Bảng thuật ngữ Casino Rule 5.3: húp GTLM, bay màu, ra chiêu, chiến, giao lưu, trận địa...
 * 5. TRIPLE-LAYER WATCHDOG TIMER & ANTI-STUCK:
 *    - Giám sát trạng thái bận giải phóng sau 12 giây.
 *    - Định kỳ 2s kiểm tra nếu #ovResult active quá 4s hoặc #btnStart sẵn sàng quá 4s mà chưa đánh thì lập tức kích hoạt lượt chơi.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 60] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Đại Hiệp Leo Tháp 61 🗼');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI VÀ BIẾN ĐIỀU KHIỂN
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimer = null;
    let lastChatTime = 0;
    let turnCount = 0;
    let winStreak = 0;
    let loseStreak = 0;
    let lastFloor = 0;

    function setBusy(val, timeoutMs = 12000) {
        botIsBusy = val;
        if (busyTimer) clearTimeout(busyTimer);
        if (val) {
            busyTimer = setTimeout(() => {
                console.warn('[Bot 60 Watchdog] Tự động giải phóng trạng thái bận!');
                botIsBusy = false;
            }, timeoutMs);
        }
    }

    // ═══════════════════════════════════════════════════════
    // PHÁT NGÔN & BÌNH LUẬN CASINO CHUẨN RULE 5.3
    // ═══════════════════════════════════════════════════════
    const CHAT_START = [
        "Chiến nào ae ơi, tầng này quyết không lùi bước!",
        "Vào trận địa, quét sạch toàn bộ quái vật nào ae!",
        "Đội hình đã dàn trận xong, ae cùng theo dõi và húp lộc nhé!",
        "Ra chiêu rực lửa, quyết tâm chinh phục đỉnh tháp Thần Bài!",
        "Giao lưu nhẹ với quái tầng này xem bản lĩnh tới đâu!",
        "Lên đồ và sẵn sàng chiến đấu, chúc toàn thể ae húp đậm GTLM!"
    ];

    const CHAT_WIN = [
        "Húp trọn GTLM tầng này rồi ae ơi, quá ngọt ngào!",
        "Đội hình càn quét sạch sẽ trận địa, húp no nê phúc lộc!",
        "Quét sạch quái rồi, lên tầng tiếp theo chiến tiếp thôi ae!",
        "Ra chiêu chuẩn chỉ, húp GTLM nhẹ nhàng êm ái!",
        "Chiến thắng giòn giã, leo tầng thần tốc ae ơi!",
        "Đại thắng rực rỡ, phúc lộc đầy tay!"
    ];

    const CHAT_LOSE = [
        "Cay quá ae ơi, bay màu ở tầng này rồi!",
        "Trận địa khốc liệt quá, bay màu toàn bộ đội hình rồi ae!",
        "Không sao ae, còn thở là còn gỡ, làm lại kèo mới từ tầng 1!",
        "Giao lưu lại từ tầng 1 để tích lũy thêm nội lực phục thù!",
        "Bay màu một hiệp để lấy đà leo cao hơn, ae đừng lo!"
    ];

    const CHAT_BOSS = [
        "Gặp Boss tầng rồi ae ơi, kích hoạt tuyệt kỹ lãnh tụ khô máu!",
        "Boss tầng xuất hiện, toàn đội đồng loạt ra chiêu tối thượng!",
        "Trận địa Boss rực lửa, ae cùng cổ vũ để húp hũ Boss nhé!"
    ];

    function sendBotChat(type, extraMsg) {
        const now = Date.now();
        if (now - lastChatTime < 6000 && type !== 'boss' && type !== 'win') return;

        let list = CHAT_START;
        if (type === 'win') list = CHAT_WIN;
        else if (type === 'lose') list = CHAT_LOSE;
        else if (type === 'boss') list = CHAT_BOSS;

        const msg = extraMsg || list[Math.floor(Math.random() * list.length)];
        lastChatTime = now;

        // 1. BotChat toàn cục nếu có
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(60, 'bot_60', msg);
        }

        // 2. Spectator API POST
        try {
            fetch('api_spectator.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'send_chat',
                    table_id: 61,
                    message: '🎙️ ' + msg
                })
            }).catch(() => {});
        } catch (e) {}

        // 3. PostMessage gửi tới cha nếu nằm trong iframe
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'bot_chat', text: msg, tableId: 61 }, '*');
            }
        } catch (e) {}
    }

    function sendBotReaction(emoji) {
        try {
            fetch('api_spectator.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'send_reaction',
                    table_id: 61,
                    emoji: emoji
                })
            }).catch(() => {});
        } catch (e) {}
    }

    // ═══════════════════════════════════════════════════════
    // CHUỘT ẢO ASYNC AN TOÀN TUYỆT ĐỐI (SAFETY TIMEOUT)
    // ═══════════════════════════════════════════════════════
    function moveAndClick(el, duration = 0.45) {
        return new Promise(resolve => {
            if (!el || $(el).length === 0) {
                resolve();
                return;
            }

            let done = false;
            const finish = () => {
                if (!done) {
                    done = true;
                    resolve();
                }
            };

            // Safety timeout đảm bảo luôn giải phóng
            const safetyTimer = setTimeout(() => {
                try { el.click(); } catch (e) {}
                finish();
            }, (duration * 1000) + 400);

            try {
                BotVirtualCursor.moveToElement($(el), duration, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        clearTimeout(safetyTimer);
                        try { el.click(); } catch (e) {}
                        setTimeout(finish, 120);
                    });
                });
            } catch (err) {
                clearTimeout(safetyTimer);
                try { el.click(); } catch (e) {}
                finish();
            }
        });
    }

    // ═══════════════════════════════════════════════════════
    // VÒNG ĐIỀU KHIỂN AI CHÍNH CỦA BOT
    // ═══════════════════════════════════════════════════════
    async function executeBotTurn() {
        if (botIsBusy) return;

        // 1. KIỂM TRA MÀN HÌNH KẾT QUẢ OVERLAY (#ovResult)
        const ovResult = document.getElementById('ovResult');
        if (ovResult && ovResult.classList.contains('active')) {
            setBusy(true, 5000);

            const btnAdvance = ovResult.querySelector('.btn-advance-floor, #btnAdvanceFloor');
            const btnRetry   = ovResult.querySelector('.btn-retry-floor, #btnRetryFloor');

            if (btnAdvance) {
                winStreak++;
                loseStreak = 0;
                sendBotChat('win');
                sendBotReaction('🔥');

                await new Promise(r => setTimeout(r, 600));
                await moveAndClick(btnAdvance, 0.45);

                // Fallback trực tiếp để không bao giờ bị kẹt overlay
                try {
                    if (window.Game && window.Game.nextFloor) window.Game.nextFloor();
                } catch(e) {}
                ovResult.classList.remove('active');

                await new Promise(r => setTimeout(r, 300));
                setBusy(false);
                setTimeout(executeBotTurn, 250);
                return;
            } else if (btnRetry) {
                loseStreak++;
                winStreak = 0;
                sendBotChat('lose');
                sendBotReaction('💀');

                await new Promise(r => setTimeout(r, 600));
                await moveAndClick(btnRetry, 0.45);

                // Fallback trực tiếp
                try {
                    if (window.Game && window.Game.closeOverlay) window.Game.closeOverlay();
                } catch(e) {}
                ovResult.classList.remove('active');

                await new Promise(r => setTimeout(r, 300));
                setBusy(false);
                setTimeout(executeBotTurn, 250);
                return;
            } else {
                // Overlay trống hoặc chưa render nút
                ovResult.classList.remove('active');
                setBusy(false);
                return;
            }
        }

        // 2. KIỂM TRA TRẠNG THÁI ĐANG CHIẾN ĐẤU
        if (window.Game && window.Game.isBattling && window.Game.isBattling()) {
            return;
        }

        const btnStart = document.getElementById('btnStart');
        if (!btnStart || btnStart.disabled) {
            return;
        }

        // 3. SẴN SÀNG Ở SẢNH TẦNG (READY PHASE)
        setBusy(true, 10000);
        turnCount++;

        try {
            const floorEl = document.getElementById('floorNum');
            const currentFloor = floorEl ? (parseInt(floorEl.textContent) || 1) : 1;
            const isBoss = (currentFloor % 10 === 0);

            // CHIẾN THUẬT CHỌN TƯỚNG & TUYỆT KỸ
            if (isBoss) {
                // Tầng Boss: Ưu tiên sát thủ / tanker
                const bossPicks = ['kiem_thanh', 'cuong_chien_si', 'phap_su', 'ninja'];
                const chosen = bossPicks[Math.floor(Math.random() * bossPicks.length)];
                const charBtn = document.getElementById('btnChar_' + chosen);
                if (charBtn && !charBtn.classList.contains('active')) {
                    await moveAndClick(charBtn, 0.35);
                }

                // Kích hoạt Tuyệt Kỹ nếu sẵn sàng
                const btnSkill = document.getElementById('btnSkill');
                if (btnSkill && btnSkill.classList.contains('ready') && !btnSkill.disabled) {
                    sendBotChat('boss');
                    sendBotReaction('⚡');
                    await moveAndClick(btnSkill, 0.35);
                }
            } else if (currentFloor !== lastFloor && Math.random() < 0.25) {
                // Tầng thường: Thỉnh thoảng đổi tướng farm GTLM
                const farmPicks = ['than_tai', 'dao_tac', 'gia_kim', 'kiem_thanh'];
                const chosen = farmPicks[Math.floor(Math.random() * farmPicks.length)];
                const charBtn = document.getElementById('btnChar_' + chosen);
                if (charBtn && !charBtn.classList.contains('active')) {
                    await moveAndClick(charBtn, 0.3);
                }
            }

            lastFloor = currentFloor;

            // Chat mở đầu (chu kỳ 4 trận hoặc khi gặp Boss)
            if (turnCount % 4 === 0 || isBoss) {
                sendBotChat('start');
            }

            // Bấm vào nút CHIẾN ĐẤU
            const activeBtnStart = document.getElementById('btnStart');
            if (activeBtnStart && !activeBtnStart.disabled) {
                await moveAndClick(activeBtnStart, 0.45);

                // Fallback nếu sau 1.2s vẫn chưa kích hoạt chiến đấu
                setTimeout(() => {
                    const checkBtn = document.getElementById('btnStart');
                    if (checkBtn && !checkBtn.disabled && (!window.Game || !window.Game.isBattling || !window.Game.isBattling())) {
                        try {
                            if (window.Game && window.Game.startBattle) window.Game.startBattle();
                        } catch(e) {}
                    }
                }, 1200);
            }

            // Đợi trận chiến hoàn thành (combat animation mất ~3.5s)
            // Nhả busy sau 4.2s để chu kỳ kế tiếp có thể xử lý overlay kết quả ngay lập tức
            setTimeout(() => {
                setBusy(false);
            }, 4200);

        } catch (err) {
            console.error('[Bot 60] Lỗi thực thi lượt leo tháp:', err);
            setBusy(false);
        }
    }

    // ═══════════════════════════════════════════════════════
    // TRIPLE-LAYER ANTI-STUCK WATCHDOG TIMER
    // ═══════════════════════════════════════════════════════
    let overlayStuckSeconds = 0;
    let readyStuckSeconds = 0;

    setInterval(() => {
        const ovResult = document.getElementById('ovResult');
        if (ovResult && ovResult.classList.contains('active')) {
            overlayStuckSeconds += 2;
            if (overlayStuckSeconds >= 4) {
                console.warn('[Bot 60 Watchdog] Tự động giải phóng màn hình kết quả sau 4s!');
                const btnAdvance = ovResult.querySelector('.btn-advance-floor, #btnAdvanceFloor');
                const btnRetry   = ovResult.querySelector('.btn-retry-floor, #btnRetryFloor');
                if (btnAdvance) {
                    try { window.Game.nextFloor(); } catch(e) { btnAdvance.click(); }
                } else if (btnRetry) {
                    try { window.Game.closeOverlay(); } catch(e) { btnRetry.click(); }
                } else {
                    ovResult.classList.remove('active');
                }
                botIsBusy = false;
                overlayStuckSeconds = 0;
            }
            return;
        } else {
            overlayStuckSeconds = 0;
        }

        const btnStart = document.getElementById('btnStart');
        if (btnStart && !btnStart.disabled && (!window.Game || !window.Game.isBattling || !window.Game.isBattling())) {
            readyStuckSeconds += 2;
            if (readyStuckSeconds >= 4) {
                console.warn('[Bot 60 Watchdog] Nút bắt đầu đang sẵn sàng mà bot nghỉ quá 4s, tự kích hoạt!');
                botIsBusy = false;
                readyStuckSeconds = 0;
                executeBotTurn();
            }
        } else {
            readyStuckSeconds = 0;
        }
    }, 2000);

    // ═══════════════════════════════════════════════════════
    // KHỞI ĐỘNG VÒNG ĐIỀU KHIỂN ĐỊNH KỲ
    // ═══════════════════════════════════════════════════════
    $(document).ready(() => {
        console.log('[Bot 60] 🗼 Đại Hiệp Leo Tháp 61 khởi động thành công!');
        setTimeout(() => { executeBotTurn(); }, 1600);
        setInterval(() => { executeBotTurn(); }, 3200);
    });

})();
