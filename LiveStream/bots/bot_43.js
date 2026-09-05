/**
 * 🤖 Bot AI Texas Hold'em Poker Siêu Thông Minh (ID: 43)
 *
 * CHIẾN THUẬT & TRÍ THÔNG MINH:
 * 1. QUẢN LÝ VỐN CHUYÊN NGHIỆP (Bankroll Management):
 *    - Đọc số dư ví & chuỗi thắng/thua để nâng/hạ mức cược linh hoạt (10K -> 50K -> 100K -> 500K).
 *    - Thắng chuỗi -> Tăng cược ép sân các bot đối thủ.
 *    - Thua -> Tự động hạ về mức cơ sở an toàn (10K).
 * 2. ĐỌC VÒNG BÀI (Street Awareness):
 *    - Nhận biết từng vòng: Pre-Flop (0 lá chung) -> Flop (3 lá) -> Turn (4 lá) -> River (5 lá) -> Showdown.
 *    - Thời gian suy nghĩ tự nhiên theo từng vòng cược (Pre-flop nhanh, River cân nhắc kỹ).
 * 3. BẢO TOÀN LỢI THẾ CƯỢC:
 *    - Luôn Call/Check qua từng vòng để tối đa hóa cơ hội ăn trọn Pot x4 ở Showdown.
 *    - Tuyệt đối không tự Fold bỏ GTLM oan uổng.
 * 4. HỆ THỐNG WATCHDOG & CHỐNG KẸT:
 *    - Tự động đóng popup cản trở, phục hồi trạng thái sau 3s nếu có gián đoạn mạng.
 *    - Tương tác chat chuẩn phong cách Poker sảnh VIP.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 43] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Poker Pro 43');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI VÀ CHỈ SỐ BOT
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimer = null;
    let lastChatTime = 0;
    let winStreak = 0;
    let lossStreak = 0;
    let currentRoundActionCount = 0;
    let lastProcessedState = '';

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
    // PHÁT HIỆN TRẠNG THÁI BÀN POKER
    // ═══════════════════════════════════════════════════════
    const STATE = { START: 'start', PLAY: 'play', NONE: 'none' };

    function detectState() {
        // Đóng các popup SweetAlert nếu có che phủ
        if (typeof Swal !== 'undefined' && Swal.isVisible && Swal.isVisible()) {
            try { Swal.close(); } catch (e) { }
        }

        const startCtrl = document.getElementById('start-controls');
        const playCtrl = document.getElementById('play-controls');

        const startVisible = startCtrl && window.getComputedStyle(startCtrl).display !== 'none';
        const playVisible = playCtrl && window.getComputedStyle(playCtrl).display !== 'none';

        if (startVisible) return STATE.START;
        if (playVisible) return STATE.PLAY;
        return STATE.NONE;
    }

    // Xác định đang ở vòng nào dựa trên số lá bài chung trên bàn
    function getCurrentStreet() {
        const commCards = document.querySelectorAll('#community-area .card');
        const count = commCards.length;
        if (count === 0) return 'pre-flop';
        if (count === 3) return 'flop';
        if (count === 4) return 'turn';
        if (count === 5) return 'river';
        return 'unknown';
    }

    // ═══════════════════════════════════════════════════════
    // CHAT PHONG CÁCH POKER MASTER
    // ═══════════════════════════════════════════════════════
    const winPhrases = [
        'Bài tẩy giấu kỹ quá, Showdown ăn trọn Pot x4! 🏆💰',
        'Flush/Straight kết nối quá đẹp, húp sạch Pot bàn này! ♠♦',
        'Poker Master ra chiêu, các bot khác chỉ biết ngậm ngùi! 😎✨',
        'GTLM về ví như thác đổ, ván sau tất tay tiếp! 🚀',
        'Đọc bài chuẩn xác từng vòng, chiến thắng thuyết phục! 🃏'
    ];

    const losePhrases = [
        'River bẻ kèo hiểm thật, tạm thời bay màu nhẹ! 😅',
        'Bot bạn cầm đôi to quá, ván sau phục thù gom lại Pot! 😤',
        'Thua một ván không sao, quản lý vốn tốt là đường dài thắng lớn! 💪',
        'Gặp bad beat nhẹ, ván sau bắt bài gom lại cả vốn lẫn lời! 🎯'
    ];

    const streetPhrases = {
        'flop': [
            'Flop kết nối ổn áp, check xem Turn thế nào! 👀',
            'Flop có cửa bắt sảnh, theo tiếp không bỏ! 🃏'
        ],
        'turn': [
            'Turn xuất hiện đúng lá bài mong đợi, tự tin theo! 🔥',
            'Bài đang mạnh dần lên, giữ vững thế trận! 💰'
        ],
        'river': [
            'River chốt hạ rồi, sẵn sàng mở bài Showdown! ⚡',
            'Đến River là không ngán ai, lật bài nào anh em! 🏆'
        ]
    };

    function sendBotChat(type, street = '') {
        const now = Date.now();
        if (now - lastChatTime < 14000) return;
        if (Math.random() > 0.5) return;

        let list = [];
        if (type === 'win') list = winPhrases;
        else if (type === 'lose') list = losePhrases;
        else if (type === 'street' && streetPhrases[street]) list = streetPhrases[street];
        else list = winPhrases;

        const msg = list[Math.floor(Math.random() * list.length)];
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(43, 'bot_43', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // GIAI ĐOẠN 1: CHỌN MỨC CƯỢC & BẤM KHAI CUỘC
    // ═══════════════════════════════════════════════════════
    function doStart() {
        const btnStart = document.getElementById('btn-start');
        if (!btnStart || btnStart.disabled) return;

        setBusy(true, 3000);
        currentRoundActionCount = 0;

        // 1. Quản lý vốn thông minh (Smart Bankroll):
        // Chọn chip theo chuỗi thắng / thua
        let targetValue = 10000;
        if (winStreak >= 3) {
            targetValue = 500000; // Đang trên đà hưng phấn -> Đánh lớn 500K
        } else if (winStreak >= 2) {
            targetValue = 100000; // Thắng 2 ván liên tiếp -> Đánh 100K
        } else if (winStreak >= 1) {
            targetValue = 50000;  // Thắng 1 ván -> Nâng lên 50K
        } else {
            targetValue = 10000;  // Cơ bản an toàn 10K
        }

        const chips = Array.from(document.querySelectorAll('#start-controls .chip'));
        let targetChip = chips.find(c => parseInt(c.getAttribute('data-value') || '0') === targetValue);
        if (!targetChip) {
            targetChip = chips.find(c => {
                const v = parseInt(c.getAttribute('data-value') || '0');
                return v > 0 && v <= 100000;
            }) || chips[0];
        }

        // Chọn chip với con trỏ ảo mượt mà
        if (targetChip) {
            BotVirtualCursor.moveToElement($(targetChip), 0.4, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { targetChip.click(); } catch (e) { }

                        // Di chuyển tiếp đến nút KHAI CUỘC
                        setTimeout(() => {
                            BotVirtualCursor.moveToElement($(btnStart), 0.45, 0, () => {
                                setTimeout(() => {
                                    BotVirtualCursor.simulateClick(() => {
                                        try { btnStart.click(); } catch (e) { }
                                        setTimeout(() => { setBusy(false); }, 600);
                                    });
                                }, 80);
                            });
                        }, 200);
                    });
                }, 80);
            });
        } else {
            // Nhấp thẳng KHAI CUỘC
            BotVirtualCursor.moveToElement($(btnStart), 0.45, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { btnStart.click(); } catch (e) { }
                        setTimeout(() => { setBusy(false); }, 600);
                    });
                }, 80);
            });
        }
    }

    // ═══════════════════════════════════════════════════════
    // GIAI ĐOẠN 2: CHƠI VÒNG CƯỢC (THEO / CALL / CHECK)
    // ═══════════════════════════════════════════════════════
    function doPlay() {
        const btnCall = document.getElementById('btn-call');
        if (!btnCall || btnCall.disabled) return;

        setBusy(true, 3500);
        currentRoundActionCount++;

        const currentStreet = getCurrentStreet();

        // Chat ngẫu nhiên tạo tương tác chân thực
        if (Math.random() < 0.25) {
            sendBotChat('street', currentStreet);
        }

        // Tính thời gian suy nghĩ tự nhiên theo từng vòng bài
        let thinkMs = 400;
        if (currentStreet === 'pre-flop') thinkMs = 350 + Math.random() * 250;
        else if (currentStreet === 'flop') thinkMs = 450 + Math.random() * 300;
        else if (currentStreet === 'turn') thinkMs = 500 + Math.random() * 350;
        else if (currentStreet === 'river') thinkMs = 600 + Math.random() * 400;

        setTimeout(() => {
            // Tái kiểm tra nút Call trước khi click
            if (!btnCall || btnCall.disabled) {
                setBusy(false);
                return;
            }

            BotVirtualCursor.moveToElement($(btnCall), 0.4, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { btnCall.click(); } catch (e) { }
                        // Chờ server phản hồi xong mới mở khóa
                        setTimeout(() => { setBusy(false); }, 800);
                    });
                }, 80);
            });
        }, thinkMs);
    }

    // ═══════════════════════════════════════════════════════
    // THEO DÕI KẾT QUẢ SHOWDOWN
    // ═══════════════════════════════════════════════════════
    function setupResultWatcher() {
        const statusBox = document.getElementById('status-box');
        if (!statusBox) return;

        const observer = new MutationObserver(() => {
            const txt = (statusBox.textContent || '').trim();
            if (!txt || txt === lastProcessedState) return;
            lastProcessedState = txt;

            if (txt.includes('CHIẾN THẮNG')) {
                winStreak++;
                lossStreak = 0;
                sendBotChat('win');
            } else if (txt.includes('THẤT BẠI')) {
                lossStreak++;
                winStreak = 0;
                sendBotChat('lose');
            }
        });

        observer.observe(statusBox, { childList: true, subtree: true, characterData: true });
    }

    // ═══════════════════════════════════════════════════════
    // VÒNG LẶP CHÍNH (Game Loop)
    // ═══════════════════════════════════════════════════════
    function playTurn() {
        if (botIsBusy) return;

        const state = detectState();
        if (state === STATE.START) {
            doStart();
        } else if (state === STATE.PLAY) {
            doPlay();
        }
    }

    // Khởi tạo
    setupResultWatcher();

    // Tần suất quét nhịp nhàng mỗi 700ms
    setInterval(playTurn, 700);
    setTimeout(playTurn, 1000);

})();
