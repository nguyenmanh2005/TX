/**
 * 🐾 Bot Streamer Bàn 1: Chiến Thần Linh Thú (Bầu Cua Cyber Pets) - TURBO AI SMART STREAMER PRO
 * Chuẩn Modern Async/Await + Chuột Ảo Đa Điểm Siêu Nhanh + AI Bắt Cầu Linh Thú + Casino Rule 5.3
 * Tích hợp LiveStream Chat 24/7 đồng bộ qua BotChat
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 1] BotVirtualCursor chưa tải!');
        return;
    }

    BotVirtualCursor.init('Chiến Thần Linh Thú 🐾');

    let botIsBusy = false;
    let busyTimestamp = 0;

    const ANIMALS = ["Chó", "Gà", "Mèo", "Cá", "Chim", "Heo"];
    const EMOJIS = {
        "Chó": "🐶",
        "Gà": "🐔",
        "Mèo": "🐱",
        "Cá": "🐟",
        "Chim": "🐦",
        "Heo": "🐷"
    };

    // AI Commander Intelligence Engine
    const aiCommander = {
        consecutiveWins: 0,
        consecutiveLosses: 0,
        historyRolls: [], // Mảng lưu các kết quả xúc xắc gần nhất
        currentStrategy: 'CARPET_SPREAD', // 'STREAK_HUNTER', 'CARPET_SPREAD', 'SOLO_SNIPER', 'SAFE_GRIND'
        currentBetChipBtn: '#btnChip100k',
        targetAnimals: ['Cá', 'Gà'],
        totalProfit: 0
    };

    const CASINO_CHATS = {
        streak_hunter: [
            "Cầu bệt {animal} đang vào guồng cực nét! Ra chiêu dồn hỏa lực bắt trọn bão {animal} {emoji}! 🎯⚡",
            "Linh thú {animal} nổ 2 nháy liên tục, thế trận này không theo thì quá phí! Khóa chặt cửa {animal}! 🐾💎",
            "Bắt nhịp cầu bệt: Ván này tất tay linh thú {animal} {emoji} húp đậm GTLM đón bão! 🚀💰"
        ],
        carpet_spread: [
            "Trận địa biến động, triển khai thế trận Rải Thảm Tam Linh: {trio} mỗi cửa 100K GTLM bảo toàn nick! 🛡️✨",
            "Chiến thuật bao vây 3 cửa xác suất trúng 70%: Gom lộc lá {trio} húp GTLM đều tay! 🐾🎲",
            "Rải thảm đa mục tiêu đón đầu xúc xắc, cứ 3 cửa mà vã là tiền về nick rủng rỉnh! 🌟💎"
        ],
        solo_sniper: [
            "Đang trên đà thắng hoa! Độc tiễn bắn tỉa: Dồn 500.000 GTLM vào linh thú {animal} {emoji}! 🎯🔥👑",
            "Cảm giác linh tính mách bảo: Tiếng này cửa {animal} {emoji} sẽ mở ra siêu bão tam hoa! Ra chiêu dứt khoát! ⚡💰",
            "Thừa thắng xông lên, đè bẹp trận địa với cú đấm thép vào cửa {animal}! Húp trọn hũ lớn nào anh em! 🏆💎"
        ],
        safe_grind: [
            "Cầu đang nhấp nhô, giữ nhịp an toàn ra chiêu 50.000 GTLM vào 2 cửa {trio} tích tiểu thành đại! 🛡️🔄",
            "Phòng ngự phản công: Điểm huyệt nhẹ nhàng để cân bằng tài chính hồi máu cho nick! 🍵💪",
            "Đánh chắc tay, quản trị rủi ro tối cao. Anh em bình tĩnh cùng tôi lội ngược dòng! 🎯🌟"
        ],
        win_multi: [
            "HÚP TRỌN TAM HOA / KÉP {animal} {emoji}! Nhân 2 nhân 3 tài khoản ngập mặt +{win} GTLM! 🏆🎉🤑",
            "BÃO LINH THÚ NỔ TƯNG BỪNG! {animal} về rực rỡ, tiền cộng đầy nick anh em ơi! 👑💎🔥",
            "Đỉnh cao phán đoán! Tiếng bạc thế kỷ húp trọn vẹn trận địa Bầu Cua! 🐾💰✨"
        ],
        win_normal: [
            "Húp nhẹ nhàng linh thú về đúng hẹn! +{win} GTLM vào nick rực rỡ! 🚀✨",
            "Lộc lá đã về! Tiền tươi thóc thật cộng thẳng vào tài khoản! 💰👍"
        ],
        loss: [
            "Linh thú lặn sâu quá làm bay màu tiếng này! Không sao, còn thở là còn ra chiêu bẻ cầu! 😅🔄",
            "Hơi lệch nhịp một chút, ván sau triển khai rải thảm gỡ lại gấp đôi! 💪🔥"
        ]
    };

    function sendChat(type, replaceObj = {}) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.safe_grind;
            let msg = list[Math.floor(Math.random() * list.length)];
            for (let k in replaceObj) {
                msg = msg.replace(new RegExp(`\\{${k}\\}`, 'g'), replaceObj[k]);
            }
            BotChat.send(1, 'bot_1', msg);
        }
    }

    // Chuột ảo Turbo 0.16s - 0.20s di chuyển và click
    function fastMoveAndClick(el, duration = 0.18) {
        return new Promise((resolve) => {
            const $el = $(el);
            if (!$el.length) return resolve(false);

            let hasResolved = false;
            const safetyTimer = setTimeout(() => {
                if (!hasResolved) {
                    hasResolved = true;
                    resolve(true);
                }
            }, (duration * 1000) + 250);

            try {
                BotVirtualCursor.moveToElement($el, duration, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try {
                            $el[0].click();
                        } catch (e) {}
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

    // Phân tích kết quả sau mỗi ván để điều chỉnh chiến thuật
    function analyzeLastRound(res) {
        if (!res || !res.results) return;

        aiCommander.historyRolls.push(res.results);
        if (aiCommander.historyRolls.length > 20) aiCommander.historyRolls.shift();

        // Đếm số lần xuất hiện của các con trong ván vừa rồi
        const counts = {};
        res.results.forEach(a => { counts[a] = (counts[a] || 0) + 1; });

        let maxCountAnimal = null;
        let maxCount = 0;
        for (let a in counts) {
            if (counts[a] > maxCount) {
                maxCount = counts[a];
                maxCountAnimal = a;
            }
        }

        if (res.win) {
            aiCommander.consecutiveWins++;
            aiCommander.consecutiveLosses = 0;

            if (maxCount >= 2 && aiCommander.targetAnimals.includes(maxCountAnimal)) {
                sendChat('win_multi', {
                    animal: maxCountAnimal,
                    emoji: EMOJIS[maxCountAnimal] || '🐾',
                    win: res.winAmount || '0'
                });
            } else {
                sendChat('win_normal', {
                    win: res.winAmount || '0'
                });
            }
        } else {
            aiCommander.consecutiveLosses++;
            aiCommander.consecutiveWins = 0;
            sendChat('loss', {});
        }

        // Chọn chiến thuật cho ván kế tiếp dựa trên trí thông minh phân tích
        if (maxCount >= 2 && Math.random() < 0.75) {
            // Có bão đôi hoặc bão tam -> Kích hoạt Bắt Bệt
            aiCommander.currentStrategy = 'STREAK_HUNTER';
            aiCommander.targetAnimals = [maxCountAnimal];
            aiCommander.currentBetChipBtn = '#btnChip100k';
        } else if (aiCommander.consecutiveWins >= 2) {
            // Đang đỏ thắng liên tục -> Bắn Tỉa Đơn 1 con chip khủng
            aiCommander.currentStrategy = 'SOLO_SNIPER';
            // Chọn con hot nhất trong 5 ván gần đây
            const hot = getHottestAnimal();
            aiCommander.targetAnimals = [hot];
            aiCommander.currentBetChipBtn = '#btnChip500k';
        } else if (aiCommander.consecutiveLosses >= 2) {
            // Đang thua -> Chuyển sang Phòng ngự hồi máu
            aiCommander.currentStrategy = 'SAFE_GRIND';
            aiCommander.targetAnimals = getRandomAnimals(2);
            aiCommander.currentBetChipBtn = '#btnChip50k';
        } else {
            // Mặc định hoặc xoay chuyển -> Rải Thảm 3 linh vật
            aiCommander.currentStrategy = 'CARPET_SPREAD';
            aiCommander.targetAnimals = getRandomAnimals(3);
            aiCommander.currentBetChipBtn = '#btnChip100k';
        }
    }

    function getHottestAnimal() {
        const counts = {};
        ANIMALS.forEach(a => counts[a] = 0);
        aiCommander.historyRolls.slice(-6).forEach(round => {
            round.forEach(a => counts[a] = (counts[a] || 0) + 1);
        });
        let maxA = ANIMALS[0], maxC = -1;
        for (let a of ANIMALS) {
            if (counts[a] > maxC) {
                maxC = counts[a];
                maxA = a;
            }
        }
        return maxA;
    }

    function getRandomAnimals(count) {
        const shuffled = [...ANIMALS].sort(() => 0.5 - Math.random());
        return shuffled.slice(0, count);
    }

    // Lắng nghe sự kiện ván hoàn thành từ live_1.php
    window.onBaucuaRoundComplete = function (res) {
        analyzeLastRound(res);
        // Sau khi hoàn tất hiển thị kết quả, kích hoạt ván tiếp theo sau một nhịp ngắn (400ms)
        setTimeout(() => {
            runBotTurn();
        }, 400);
    };

    // Chu kỳ hành động một ván của Bot: Chọn Chip -> Chọn Linh Vật -> Bấm Xóc
    async function runBotTurn() {
        if (botIsBusy || (typeof isRolling !== 'undefined' && isRolling)) return;

        const playBtn = document.getElementById('playBtn');
        if (!playBtn || playBtn.disabled) return;

        botIsBusy = true;
        busyTimestamp = Date.now();

        try {
            // 1. Bình luận chat theo chiến thuật
            const chosen = aiCommander.targetAnimals[0] || 'Cá';
            const trioStr = aiCommander.targetAnimals.map(a => `${a} ${EMOJIS[a]}`).join(' - ');

            if (aiCommander.currentStrategy === 'STREAK_HUNTER') {
                sendChat('streak_hunter', { animal: chosen, emoji: EMOJIS[chosen] || '🐾' });
            } else if (aiCommander.currentStrategy === 'CARPET_SPREAD') {
                sendChat('carpet_spread', { trio: trioStr });
            } else if (aiCommander.currentStrategy === 'SOLO_SNIPER') {
                sendChat('solo_sniper', { animal: chosen, emoji: EMOJIS[chosen] || '🐾' });
            } else {
                sendChat('safe_grind', { trio: trioStr });
            }

            // 2. Di chuyển chuột chọn Chip cược (0.16s)
            const chipBtn = document.querySelector(aiCommander.currentBetChipBtn) || document.getElementById('btnChip100k');
            if (chipBtn) {
                await fastMoveAndClick(chipBtn, 0.16);
            }

            // 3. Di chuyển chuột click các linh vật mục tiêu (mỗi con 0.16s)
            for (let animal of aiCommander.targetAnimals) {
                const tile = document.getElementById(`tile-${animal}`) || document.querySelector(`.animal-tile[data-animal="${animal}"]`);
                if (tile) {
                    await fastMoveAndClick(tile, 0.16);
                }
            }

            // 4. Di chuyển chuột đến nút XÓC NGAY và ấn (0.18s)
            const livePlayBtn = document.getElementById('playBtn');
            if (livePlayBtn && !livePlayBtn.disabled) {
                await fastMoveAndClick(livePlayBtn, 0.18);
            }

        } catch (err) {
            console.error('[Bot 1 Error]:', err);
        } finally {
            botIsBusy = false;
        }
    }

    // Giám sát tự động (Watchdog & Polling Fallback)
    setInterval(() => {
        // Watchdog chống kẹt nếu busy kéo dài quá 4.5s
        if (botIsBusy && (Date.now() - busyTimestamp > 4500)) {
            console.warn('[Bot 1 Watchdog] Reset busy lock');
            botIsBusy = false;
            if (typeof isRolling !== 'undefined') isRolling = false;
        }

        // Tự khởi chạy nếu không đang xóc và không bận
        if (!botIsBusy && (typeof isRolling === 'undefined' || !isRolling)) {
            const playBtn = document.getElementById('playBtn');
            if (playBtn && !playBtn.disabled) {
                runBotTurn();
            }
        }
    }, 900);

    // Thông báo mở màn khi Streamer vào trận
    setTimeout(() => {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(1, 'bot_1', 'Chào mừng toàn thể anh em đến với Chiến Trường Linh Thú Bàn 1! Hôm nay cùng tôi săn bão tam hoa húp trọn GTLM nhé! 🐾🔥🚀');
        }
        runBotTurn();
    }, 1200);

    console.log('[Bot 1] Chiến Thần Linh Thú Turbo AI khởi động thành công!');
})();