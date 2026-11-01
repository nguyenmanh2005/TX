/**
 * 🎲 Bot Streamer Bàn 20: Xạ Thủ Xúc Xắc Craps - TURBO AI CASINO PRO ENGINE
 * - Tối ưu thời gian chờ giữa các hiệp xúc xắc (Async / Turbo Mouse 0.16s - 0.2s)
 * - Tự động phát hiện & gỡ lỗi tức thì nếu gặp popup SweetAlert2 / Modal
 * - Cơ chế tự phục hồi tiền cược hợp lệ nếu gặp lỗi input
 * - Tư duy thích ứng theo chuỗi thắng/thua (Martingale / Safe Grind / Hot Shooter)
 * - Triple-Layer Watchdog chống kẹt 24/7
 * - Tích hợp LiveStream Chat 24/7 theo chuẩn Casino Rule 5.3
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 20] BotVirtualCursor chưa sẵn sàng!');
        return;
    }

    BotVirtualCursor.init('Xạ Thủ Xúc Xắc Craps 🎲');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI & AI COMMANDER
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimestamp = 0;
    let lastChatTime = 0;
    let lastBalance = null;
    let isRollingAction = false;

    const aiCommander = {
        consecutiveWins: 0,
        consecutiveLosses: 0,
        totalRolls: 0,
        currentPhase: 'comeout', // 'comeout' hoặc 'point'
        currentPoint: 0,
        preferredBet: 100000,
        strategy: 'HOT_SHOOTER' // 'HOT_SHOOTER', 'SAFE_DEFENSE', 'STEADY_GRIND'
    };

    // ═══════════════════════════════════════════════════════
    // BÌNH LUẬN CASINO CHUẨN RULE 5.3
    // ═══════════════════════════════════════════════════════
    const CASINO_CHATS = {
        comeout_roll: [
            "Đấu trường Craps rực lửa! Ra chiêu hiệp mở màn Comeout Roll {bet} GTLM! 🎲⚡",
            "Comeout Roll khởi động! Tay lắc ma thuật nhắm thẳng 7 hoặc 11 húp trọn GTLM! 🌟💎",
            "Trận địa hôm nay cầu xúc xắc đang thuận, gieo cặp xí ngầu đón bão tài lộc! 🎯💰",
            "Vào tiền tự tin, khóa chặt bàn Craps rước lộc về nick anh em ơi! 💎🚀"
        ],
        point_roll: [
            "Đã thiết lập Điểm Point [{point}]! Dồn hỏa lực ném trúng {point} né số 7 húp đậm! 🎲🔥",
            "Hiệp đấu cân não Point [{point}]! Giữ vững cổ tay thần sầu đón chiến thắng vẻ vang! 🛡️⚡",
            "Xạ thủ xúc xắc ngắm nghía góc ném: Đưa viên bi bạc về đúng điểm {point}! 🎯✨",
            "Cả sòng nín thở chờ xúc xắc lăn về điểm [{point}] nào! 🎲💎"
        ],
        hot_shooter: [
            "Đang trên đà thắng hoa liên tiếp! Xạ thủ vào form đẩy mức cược lên {bet} GTLM! 🔥👑",
            "Chuỗi đỏ rực rỡ! Tiếng bạc này ném một phát rung chuyển trận địa Craps! 🚀💎💰",
            "Khí thế ngút ngàn, thừa thắng xông lên ép bàn Craps nhả hũ GTLM! 🏆⚡"
        ],
        safe_defense: [
            "Cầu đang nhấp nhô khó đoán, hạ nhịp về {bet} GTLM đánh phòng ngự hồi máu cho nick! 🛡️🔄",
            "Kỷ luật bảo toàn nick là số 1, hiệp này ra chiêu điểm huyệt nhẹ nhàng rồi lội ngược dòng! 🍵💪",
            "Bình tĩnh cùng tôi, quản trị vốn thông minh hiệp sau bẻ cầu lấy lại tất cả! 🎯🌟"
        ],
        win_natural: [
            "7 HOẶC 11 RỒI! HÚP TRỌN VÁN COMEOUT! GTLM cộng dồn ngập tràn nick! 🏆🎲🤑",
            "NATURAL ĐỈNH CAO! Gáy khét lẹt cả sòng Craps hôm nay anh em ơi! 👑💎💰",
            "Ném chuẩn từng milimet! Tiền nhảy ting ting vào tài khoản! 🌟🎉"
        ],
        win_point: [
            "TRÚNG ĐIỂM POINT [{point}] XUẤT THẦN! Húp trọn hiệp đấu nghẹt thở! 🏆🎲✨",
            "Bắn hạ điểm Point ngọt xớt! Đẳng cấp xạ thủ xúc xắc Craps không làm anh em thất vọng! 👑💎",
            "Lộc lá GTLM bùng nổ! Chiến thắng giòn giã trên trận địa xúc xắc! 💰🎉"
        ],
        loss_craps: [
            "Craps out / dính số 7 rồi, bay màu tiếng này hơi tiếc! 😅🔄",
            "Bốc hơi chút ít GTLM, không sao hết, còn thở là còn lắc tiếp ván mới! 💪🎲",
            "Lệch góc ném một xíu, hiệp sau xốc lại tinh thần húp lại gấp đôi! 🛡️🔥"
        ]
    };

    function sendChat(type, replaceObj = {}) {
        const now = Date.now();
        if (now - lastChatTime < 2800) return; // Tránh spam chat quá dày
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.comeout_roll;
            let msg = list[Math.floor(Math.random() * list.length)];
            for (let k in replaceObj) {
                msg = msg.replace(new RegExp(`\\{${k}\\}`, 'g'), replaceObj[k]);
            }
            BotChat.send(20, 'Xạ Thủ Xúc Xắc Craps 🎲', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // CHUỘT ẢO TURBO PRO (0.16s - 0.20s)
    // ═══════════════════════════════════════════════════════
    function fastMoveAndClick(el, duration = 0.18) {
        return new Promise((resolve) => {
            const $el = $(el);
            if (!$el.length || $el.is(':hidden')) return resolve(false);

            let hasResolved = false;
            const safetyTimer = setTimeout(() => {
                if (!hasResolved) {
                    hasResolved = true;
                    resolve(true);
                }
            }, (duration * 1000) + 300);

            try {
                BotVirtualCursor.moveToElement($el, duration, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try {
                            $el[0].click();
                        } catch (e) {
                            $el.trigger('click');
                        }
                        if (!hasResolved) {
                            hasResolved = true;
                            clearTimeout(safetyTimer);
                            resolve(true);
                        }
                    });
                });
            } catch (err) {
                try { $el[0].click(); } catch (e) {}
                if (!hasResolved) {
                    hasResolved = true;
                    clearTimeout(safetyTimer);
                    resolve(true);
                }
            }
        });
    }

    // ═══════════════════════════════════════════════════════
    // HỆ THỐNG TỰ ĐỘNG GỠ LỖI POPUP (SELF-HEALING ENGINE)
    // ═══════════════════════════════════════════════════════
    function dismissPopupsAndHeal() {
        // 1. Kiểm tra SweetAlert2
        const swalContainer = document.querySelector('.swal2-container');
        if (swalContainer && swalContainer.offsetParent !== null) {
            console.log('[Bot 20 Self-Healing] Phát hiện Popup SweetAlert2, tự động xử lý...');
            const swalTxt = (document.querySelector('.swal2-title')?.innerText || '' + ' ' + (document.querySelector('.swal2-html-container')?.innerText || '')).toLowerCase();

            // Nếu popup thông báo lỗi cược không hợp lệ -> Sửa lại ô cược
            if (swalTxt.includes('không hợp lệ') || swalTxt.includes('lỗi')) {
                const betInput = document.getElementById('bet-amt');
                if (betInput) betInput.value = '100000';
            }

            // Click nút xác nhận / đóng popup tức thì
            const confirmBtn = document.querySelector('.swal2-confirm') || document.querySelector('.swal2-close');
            if (confirmBtn) {
                try { confirmBtn.click(); } catch (e) {}
            }
            if (typeof Swal !== 'undefined' && Swal.close) {
                try { Swal.close(); } catch (e) {}
            }
            return true;
        }

        // 2. Kiểm tra Modal thông thường nếu có
        const genericModals = document.querySelectorAll('.modal.show, .popup-overlay');
        if (genericModals.length > 0) {
            const closeBtn = document.querySelector('.modal.show .close, .modal.show .btn-close, .popup-close');
            if (closeBtn) {
                try { closeBtn.click(); } catch (e) {}
            }
            return true;
        }

        return false;
    }

    // ═══════════════════════════════════════════════════════
    // THEO DÕI SỐ DƯ & ĐIỀU CHỈNH CHIẾN THUẬT
    // ═══════════════════════════════════════════════════════
    function getBalance() {
        const el = document.getElementById('balance-val') || document.querySelector('.balance-text');
        if (!el) return 50000000;
        return parseInt(el.innerText.replace(/\D/g, '')) || 0;
    }

    function updatePsychologyAndOutcome() {
        const balance = getBalance();
        const statusMsg = (document.getElementById('status-msg')?.innerText || '').toLowerCase();

        if (lastBalance !== null && balance !== lastBalance) {
            const isWin = balance > lastBalance;
            if (isWin) {
                aiCommander.consecutiveWins++;
                aiCommander.consecutiveLosses = 0;

                if (aiCommander.currentPhase === 'point') {
                    sendChat('win_point', { point: aiCommander.currentPoint });
                } else {
                    sendChat('win_natural', {});
                }
            } else {
                aiCommander.consecutiveLosses++;
                aiCommander.consecutiveWins = 0;
                sendChat('loss_craps', {});
            }

            // Điều chỉnh chiến thuật & mức cược thích ứng
            if (aiCommander.consecutiveWins >= 2) {
                aiCommander.strategy = 'HOT_SHOOTER';
                aiCommander.preferredBet = (balance >= 10000000) ? 500000 : 200000;
            } else if (aiCommander.consecutiveLosses >= 2) {
                aiCommander.strategy = 'SAFE_DEFENSE';
                aiCommander.preferredBet = 100000;
            } else {
                aiCommander.strategy = 'STEADY_GRIND';
                aiCommander.preferredBet = 100000;
            }
        }
        lastBalance = balance;
    }

    // ═══════════════════════════════════════════════════════
    // VÒNG LẶP HÀNH ĐỘNG TURBO (TURBO PLAY CYCLE)
    // ═══════════════════════════════════════════════════════
    async function executeCrapsTurn() {
        // 1. Tự động dọn dẹp popup trước khi hành động
        if (dismissPopupsAndHeal()) {
            setTimeout(executeCrapsTurn, 300);
            return;
        }

        if (botIsBusy || isRollingAction) return;

        const rollBtn = document.getElementById('roll-btn');
        if (!rollBtn) return;

        // Nếu nút đang disabled (đang trong hiệu ứng xoay xúc xắc 600ms)
        if (rollBtn.disabled) return;

        botIsBusy = true;
        busyTimestamp = Date.now();

        try {
            updatePsychologyAndOutcome();

            const betArea = document.getElementById('bet-area');
            const pointTag = document.getElementById('point-tag');
            const isPointPhase = (betArea && betArea.style.display === 'none') || (pointTag && pointTag.style.display !== 'none');

            // Cập nhật trạng thái phase
            if (isPointPhase) {
                aiCommander.currentPhase = 'point';
                const ptVal = parseInt(document.getElementById('point-val')?.innerText) || 0;
                aiCommander.currentPoint = ptVal;
            } else {
                aiCommander.currentPhase = 'comeout';
            }

            // GIAI ĐOẠN 1: COMEOUT PHASE (CẦN ĐẶT CƯỢC)
            if (aiCommander.currentPhase === 'comeout') {
                // Đảm bảo ô input cược có giá trị hợp lệ
                const betInput = document.getElementById('bet-amt');
                if (betInput) {
                    const currentVal = parseInt(betInput.value);
                    if (isNaN(currentVal) || currentVal <= 0) {
                        betInput.value = aiCommander.preferredBet;
                    }
                }

                // Chọn nút phỉnh tương ứng với mức cược chiến thuật
                const targetAmount = aiCommander.preferredBet;
                let targetBtn = null;
                const quickBtns = Array.from(document.querySelectorAll('.quick-btn')).filter(b => !b.innerText.includes('ALL IN') && b.offsetParent !== null);

                if (targetAmount >= 500000) {
                    targetBtn = quickBtns.find(b => b.innerText.includes('500K')) || quickBtns[0];
                } else {
                    targetBtn = quickBtns.find(b => b.innerText.includes('100K')) || quickBtns[0];
                }

                if (targetBtn && !targetBtn.classList.contains('active')) {
                    await fastMoveAndClick(targetBtn, 0.16);
                }

                // Phát ngôn chiến thuật
                if (aiCommander.strategy === 'HOT_SHOOTER') {
                    if (Math.random() < 0.45) sendChat('hot_shooter', { bet: (targetAmount / 1000) + 'K' });
                } else if (aiCommander.strategy === 'SAFE_DEFENSE') {
                    if (Math.random() < 0.35) sendChat('safe_defense', { bet: (targetAmount / 1000) + 'K' });
                } else {
                    if (Math.random() < 0.3) sendChat('comeout_roll', { bet: (targetAmount / 1000) + 'K' });
                }
            } else {
                // GIAI ĐOẠN 2: POINT PHASE (LẮC TIẾP TỤC ĐỂ TRÚNG POINT)
                if (Math.random() < 0.35) {
                    sendChat('point_roll', { point: aiCommander.currentPoint });
                }
            }

            // NHẤN NÚT LẮC XÚC XẮC (TỐI ƯU SIÊU NHANH 0.16s)
            const liveRollBtn = document.getElementById('roll-btn');
            if (liveRollBtn && !liveRollBtn.disabled) {
                isRollingAction = true;
                await fastMoveAndClick(liveRollBtn, 0.16);

                // Thời gian xoay của animation trong live_20.php là 600ms
                // Chờ đúng 750ms là sẵn sàng cho nhịp kế tiếp!
                setTimeout(() => {
                    isRollingAction = false;
                    botIsBusy = false;
                }, 750 + Math.random() * 200);
            } else {
                botIsBusy = false;
            }

        } catch (err) {
            console.error('[Bot 20 Error]:', err);
            dismissPopupsAndHeal();
            botIsBusy = false;
            isRollingAction = false;
        }
    }

    // ═══════════════════════════════════════════════════════
    // TRIPLE-LAYER WATCHDOG & TỐI ƯU NHỊP ĐỘ CHẠY (POLLING)
    // ═══════════════════════════════════════════════════════
    setInterval(() => {
        // Tầng 1: Tự gỡ popup ngay lập tức nếu xuất hiện bất thình lình
        dismissPopupsAndHeal();

        // Tầng 2: Watchdog chống kẹt nếu busy kéo dài quá 3.5 giây
        if (botIsBusy && (Date.now() - busyTimestamp > 3500)) {
            console.warn('[Bot 20 Watchdog] Tự động giải phóng trạng thái kẹt!');
            botIsBusy = false;
            isRollingAction = false;
            const rollBtn = document.getElementById('roll-btn');
            if (rollBtn && rollBtn.disabled) {
                rollBtn.disabled = false;
                rollBtn.innerText = 'LẮC XÚC XẮC';
            }
        }

        // Tầng 3: Tự động kích hoạt hiệp kế tiếp với độ trễ siêu mượt (500ms)
        if (!botIsBusy && !isRollingAction) {
            const rollBtn = document.getElementById('roll-btn');
            if (rollBtn && !rollBtn.disabled) {
                executeCrapsTurn();
            }
        }
    }, 550);

    // Lời chào mở màn khi Streamer lên sóng
    setTimeout(() => {
        dismissPopupsAndHeal();
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(20, 'Xạ Thủ Xúc Xắc Craps 🎲', 'Chào mừng toàn thể anh em đến với Đấu Trường Craps Bàn 20! Xạ thủ vào vị trí, cùng tôi gieo xúc xắc rực lửa húp trọn GTLM nhé! 🎲🔥🚀');
        }
        executeCrapsTurn();
    }, 1200);

    console.log('[Bot 20] Xạ Thủ Xúc Xắc Craps TURBO AI Pro khởi động thành công!');
})();
