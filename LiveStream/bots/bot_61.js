/**
 * 🤖 Bot AI Tháp Thần Bài - Leo Tháp Vô Cực (Bàn Live ID: 61)
 *
 * TÍNH NĂNG THÔNG MINH & TRÍ TUỆ NHÂN TẠO:
 * 1. Chuột ảo BotVirtualCursor mượt mà, chân thực ('Đại Hiệp Leo Tháp 61 🗼')
 * 2. Phân tích tầng hiện tại:
 *    - Tầng thường: Chọn chiến binh thu thập tài nguyên hoặc duy trì sát thương (Thần Tài, Đạo Tặc, Giả Kim, Kiếm Thánh)
 *    - Tầng Boss (Tầng % 10 === 0): Tự động đổi danh tướng sinh tồn / bạo kích (Kiếm Thánh, Pháp Sư, Ninja, Cuồng Sĩ)
 *      và kích hoạt Tuyệt Kỹ Lãnh Tụ (#btnSkill) ngay lập tức khi sẵn sàng!
 * 3. Tương tác chính xác với sân đấu 3v3 RPG và màn hình kết quả (#ovResult):
 *    - Khi Chiến Thắng: Chat ăn mừng húp GTLM, di chuột ảo bấm 'Lên Tầng X!'
 *    - Khi Thua Trận: Chat an ủi theo ngôn ngữ Casino chuẩn mực, di chuột ảo bấm 'Quay Lại Tầng 1'
 * 4. Cơ chế safeMoveAndClick có timeout fallback bảo vệ, không bao giờ đơ chuột
 * 5. Watchdog timer định kỳ 3.5s tự phục hồi trạng thái
 * 6. Tuân thủ 100% Bảng thuật ngữ Casino Rule 5.3 (húp GTLM, bay màu, ra chiêu, chiến, giao lưu, trận địa...)
 */
(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 61] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Đại Hiệp Leo Tháp 61 🗼');

    let botIsBusy = false;
    let busyTimestamp = 0;
    let resultHandled = false;
    let lastFloor = 0;
    let roundsPlayed = 0;

    // Bộ câu chat chuẩn Casino Rule 5.3
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
        "Cay quá ae ơi, bay màu ở tầng Boss rồi!",
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

    function sendBotLiveChat(msg) {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'bot_chat', text: msg, tableId: 61 }, '*');
            }
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

    /**
     * Di chuyển chuột ảo và click an toàn có timeout fallback
     */
    function safeMoveAndClick(el, duration, callback) {
        if (!el || !$(el).is(':visible')) {
            if (callback) setTimeout(callback, 200);
            return;
        }

        let executed = false;
        const done = () => {
            if (!executed) {
                executed = true;
                if (callback) callback();
            }
        };

        const timer = setTimeout(done, (duration * 1000) + 500);

        try {
            BotVirtualCursor.moveToElement($(el), duration || 0.6, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    try { el.click(); } catch (e) {}
                    clearTimeout(timer);
                    setTimeout(done, 250);
                });
            });
        } catch (err) {
            console.warn('[Bot 61 Click Err]', err);
            try { el.click(); } catch (e) {}
            clearTimeout(timer);
            setTimeout(done, 200);
        }
    }

    function setBusy(state) {
        botIsBusy = state;
        busyTimestamp = state ? Date.now() : 0;
    }

    /**
     * Chu kỳ AI chính điều khiển leo tháp
     */
    function runBotCycle() {
        if (botIsBusy) return;

        // 1. KIỂM TRA MÀN HÌNH KẾT QUẢ OVERLAY (#ovResult)
        const ovResult = document.getElementById('ovResult');
        if (ovResult && ovResult.classList.contains('active')) {
            if (resultHandled) return;
            resultHandled = true;
            setBusy(true);

            const btnAdvance = ovResult.querySelector('.btn-advance-floor');
            const btnRetry   = ovResult.querySelector('.btn-retry-floor');

            if (btnAdvance) {
                // Thắng trận!
                const winMsg = CHAT_WIN[Math.floor(Math.random() * CHAT_WIN.length)];
                sendBotLiveChat(winMsg);
                sendBotReaction('🔥');

                setTimeout(() => {
                    safeMoveAndClick(btnAdvance, 0.7, () => {
                        console.log('[Bot 61] 🚀 Đã bấm Lên Tầng Kế Tiếp!');
                        setTimeout(() => {
                            setBusy(false);
                            resultHandled = false;
                        }, 800);
                    });
                }, 1400);

            } else if (btnRetry) {
                // Thua trận!
                const loseMsg = CHAT_LOSE[Math.floor(Math.random() * CHAT_LOSE.length)];
                sendBotLiveChat(loseMsg);
                sendBotReaction('💀');

                setTimeout(() => {
                    safeMoveAndClick(btnRetry, 0.7, () => {
                        console.log('[Bot 61] 🔄 Đã bấm Thử Lại Tầng 1!');
                        setTimeout(() => {
                            setBusy(false);
                            resultHandled = false;
                        }, 800);
                    });
                }, 1400);
            } else {
                setBusy(false);
                resultHandled = false;
            }
            return;
        }

        resultHandled = false;

        // 2. KIỂM TRA ĐANG CHIẾN ĐẤU (isBattling)
        const btnStart = document.getElementById('btnStart');
        if (!btnStart || btnStart.disabled) {
            return;
        }

        // 3. SẴN SÀNG Ở SẢNH TẦNG (READY PHASE)
        const floorEl = document.getElementById('floorNum');
        const currentFloor = floorEl ? (parseInt(floorEl.textContent) || 1) : 1;
        const isBoss = (currentFloor % 10 === 0);

        setBusy(true);

        // CHIẾN THUẬT CHỌN TƯỚNG & KÍCH HOẠT TUYỆT KỸ:
        let pickPromise = Promise.resolve();

        if (isBoss) {
            // Tầng Boss: Cần sát thủ hoặc tanker dũng mãnh
            const bossPicks = ['kiem_thanh', 'phap_su', 'ninja', 'cuong_chien_si'];
            const chosenChar = bossPicks[Math.floor(Math.random() * bossPicks.length)];
            const charBtn = document.getElementById('btnChar_' + chosenChar);

            if (charBtn && !charBtn.classList.contains('active')) {
                pickPromise = new Promise((resolve) => {
                    safeMoveAndClick(charBtn, 0.5, resolve);
                });
            }
        } else if (currentFloor !== lastFloor && Math.random() < 0.25) {
            // Tầng thường: Thỉnh thoảng đổi tướng nhận thưởng GTLM
            const farmPicks = ['than_tai', 'dao_tac', 'gia_kim', 'kiem_thanh'];
            const chosenChar = farmPicks[Math.floor(Math.random() * farmPicks.length)];
            const charBtn = document.getElementById('btnChar_' + chosenChar);

            if (charBtn && !charBtn.classList.contains('active')) {
                pickPromise = new Promise((resolve) => {
                    safeMoveAndClick(charBtn, 0.5, resolve);
                });
            }
        }

        lastFloor = currentFloor;

        pickPromise.then(() => {
            // Kiểm tra Tuyệt Kỹ (#btnSkill) nếu là Boss
            const btnSkill = document.getElementById('btnSkill');
            if (isBoss && btnSkill && btnSkill.classList.contains('ready') && !btnSkill.disabled) {
                const bossMsg = CHAT_BOSS[Math.floor(Math.random() * CHAT_BOSS.length)];
                sendBotLiveChat(bossMsg);
                sendBotReaction('⚡');

                safeMoveAndClick(btnSkill, 0.5, () => {
                    console.log('[Bot 61] ⚡ Đã kích hoạt Tuyệt Kỹ Lãnh Tụ!');
                    proceedToBattle();
                });
            } else {
                proceedToBattle();
            }
        });

        function proceedToBattle() {
            // Chat mở đầu trận (tần suất hợp lý, tránh spam)
            roundsPlayed++;
            if (roundsPlayed % 3 === 0 || isBoss) {
                const startMsg = CHAT_START[Math.floor(Math.random() * CHAT_START.length)];
                sendBotLiveChat(startMsg);
            }

            // Bấm vào nút CHIẾN ĐẤU
            setTimeout(() => {
                const currentBtnStart = document.getElementById('btnStart');
                if (currentBtnStart && !currentBtnStart.disabled) {
                    safeMoveAndClick(currentBtnStart, 0.6, () => {
                        console.log('[Bot 61] ⚔️ Đã bấm Tiến Vào Chiến Đấu Tầng ' + currentFloor);
                        setTimeout(() => {
                            setBusy(false);
                        }, 500);
                    });
                } else {
                    setBusy(false);
                }
            }, 300);
        }
    }

    // WATCHDOG BẢO VỆ CHỐNG TREO 100%
    function watchdog() {
        const now = Date.now();

        // 1. Giải phóng cờ busy nếu bị kẹt quá 14s
        if (botIsBusy && (now - busyTimestamp > 14000)) {
            console.warn('[Bot 61 Watchdog] Cảnh báo bận quá lâu, tự động giải phóng cờ botIsBusy!');
            botIsBusy = false;
            resultHandled = false;
        }

        // 2. Nếu overlay active quá 5s mà chưa được xử lý
        const ovResult = document.getElementById('ovResult');
        if (ovResult && ovResult.classList.contains('active')) {
            const btnAdvance = ovResult.querySelector('.btn-advance-floor');
            const btnRetry   = ovResult.querySelector('.btn-retry-floor');
            if (btnAdvance && !botIsBusy) {
                btnAdvance.click();
            } else if (btnRetry && !botIsBusy) {
                btnRetry.click();
            }
            return;
        }

        // 3. Nếu nút bắt đầu đang sẵn sàng mà bot không làm gì quá lâu
        const btnStart = document.getElementById('btnStart');
        if (btnStart && !btnStart.disabled && !botIsBusy) {
            runBotCycle();
        }
    }

    console.log('[Bot 61] 🗼 Khởi chạy Tower of Gods AI Bot 61 đỉnh cao!');
    setTimeout(() => {
        runBotCycle();
    }, 1800);

    setInterval(runBotCycle, 3000);
    setInterval(watchdog, 3500);

})();
