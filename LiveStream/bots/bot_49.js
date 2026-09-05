/**
 * 🤖 Bot AI Sâm Lốc Siêu Thông Minh (Bàn 49)
 *
 * CHIẾN THUẬT & TRÍ THÔNG MINH SÂM LỐC:
 * 1. QUẢN LÝ VÒNG CHƠI & BẮT BÀI:
 *    - Khi đánh đầu: Ưu tiên xả sảnh dài (3+ lá), sau đó tới bộ ba, đôi, và rác nhỏ.
 *    - Tuyệt đối giữ Heo (2) khôn ngoan, không đánh Heo đầu ván tránh bị bắt bài hoặc chặt tứ quý.
 *    - Đè bài thông minh: Dùng lá nhỏ nhất có thể để đè nước đi của đối thủ.
 *    - Bắt Heo: Nếu đối phương ra Heo (2), bot lập tức kiểm tra Tứ Quý để CHẶT NGAY!
 *    - Bỏ lượt có chiến thuật: Không xé lẻ bộ sảnh/đôi quý khi đối thủ đánh lá lẻ không đáng kể.
 * 2. TỰ ĐỘNG KHỞI TẠO VÁN:
 *    - Khi bàn ở trạng thái chờ (waiting), bot tự động thêm bot vào phòng để trận đấu bắt đầu ngay,
 *      không để khán giả trên live stream phải đợi lâu.
 * 3. ĐIỀU KHIỂN CHUỘT ẢO (BotVirtualCursor):
 *    - Di chuyển mượt mà tới các lá bài cần chọn và bấm "ĐÁNH BÀI" (#btn-play) hoặc "BỎ LƯỢT" (#btn-pass).
 *    - Không click lung tung, không spam phím.
 * 4. THÔNG BÁO & HIỆU ỨNG THẮNG THUA:
 *    - Hiển thị badge kết quả chiến thắng 🏆 / thất bại ❌ chuẩn phong cách game ID 1.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 49] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Thánh Bài Sâm 49');

    let botIsBusy = false;
    let busyTimer = null;
    let lastChatTime = 0;
    let lastActionTime = 0;
    let hasHandledEnd = false;

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
    // CHAT PHONG CÁCH SÂM LỐC
    // ═══════════════════════════════════════════════════════
    const winPhrases = [
        'Ván bài quá mượt, hết bài làng đền! Húp trọn GTLM! 🃏✨',
        'Tẩy bài sạch bóng, đền làng không lối thoát! 🏆💰',
        'Chiến thuật xả sảnh giữ chốt đỉnh cao! Đỉnh nóc kịch trần! 😎🚀',
        'Sâm Lốc biến ảo, bắt đúng nhịp là lụm lúa! ❤️🔥'
    ];

    const losePhrases = [
        'Bị chặt bất ngờ quá, ván sau gom bài phục thù! 😤',
        'Bài hơi lẻ, nhưng quản lý vốn thì vẫn dư sức lật kèo! 🎯',
        'Đối thủ đánh rát đấy, ván sau xem tôi xả sảnh nhé! 💪'
    ];

    function sendBotChat(status) {
        const now = Date.now();
        if (now - lastChatTime < 15000) return;
        if (Math.random() > 0.6) return;

        const list = status === 'win' ? winPhrases : losePhrases;
        const msg = list[Math.floor(Math.random() * list.length)];
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(49, 'bot_49', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // THUẬT TOÁN PHÂN TÍCH BÀI TỐI ƯU (THÁNH BÀI AI ENGINE)
    // ═══════════════════════════════════════════════════════
    function getNextOpponentCardCount() {
        // Lượt chơi theo chiều kim đồng hồ: Từ slot-0 (Bạn) sang slot-1 (Bên phải)
        for (let i = 1; i <= 4; i++) {
            const $slot = $(`#slot-${i}`);
            if ($slot.is(':visible')) {
                const txt = $slot.find('.card-count').text() || '';
                const match = txt.match(/(\d+)/);
                if (match) return parseInt(match[1], 10);
            }
        }
        return 10;
    }

    function parseCardsFromHand() {
        const cards = [];
        $('#my-hand .card').each(function () {
            const id = $(this).attr('data-id');
            if (id) {
                const parts = id.split('_');
                const v = parseInt(parts[0], 10);
                const s = parts[1] || '';
                cards.push({ id, v, s, el: $(this) });
            }
        });
        cards.sort((a, b) => a.v - b.v);
        return cards;
    }

    function analyzeHand(cards) {
        const sorted = [...cards].sort((a, b) => a.v - b.v);
        
        // 1. Tách Tứ Quý trước (đặc quyền bắt Heo, tuyệt đối không xé lẻ)
        const quads = [];
        const nonQuadCards = [];
        const valMap = {};
        sorted.forEach(c => {
            if (!valMap[c.v]) valMap[c.v] = [];
            valMap[c.v].push(c);
        });
        
        for (let v in valMap) {
            if (valMap[v].length === 4) {
                quads.push(valMap[v]);
            } else {
                nonQuadCards.push(...valMap[v]);
            }
        }
        nonQuadCards.sort((a, b) => a.v - b.v);

        // 2. Tìm Sảnh tối ưu từ nonQuadCards (sảnh không chứa 2, tối thiểu 3 lá)
        const straights = [];
        let pool = [...nonQuadCards];

        function findLongestStraight(p) {
            const valid = p.filter(c => c.v < 15);
            if (valid.length < 3) return null;
            const uVals = [];
            const mapV = {};
            valid.forEach(c => {
                if (!mapV[c.v]) { mapV[c.v] = []; uVals.push(c.v); }
                mapV[c.v].push(c);
            });
            uVals.sort((a, b) => a - b);

            let bestRun = [];
            let curRun = [];
            for (let i = 0; i < uVals.length; i++) {
                if (curRun.length === 0) {
                    curRun.push(uVals[i]);
                } else {
                    if (uVals[i] === curRun[curRun.length - 1] + 1) {
                        curRun.push(uVals[i]);
                    } else {
                        if (curRun.length >= 3 && curRun.length > bestRun.length) {
                            bestRun = [...curRun];
                        }
                        curRun = [uVals[i]];
                    }
                }
            }
            if (curRun.length >= 3 && curRun.length > bestRun.length) {
                bestRun = [...curRun];
            }

            if (bestRun.length >= 3) {
                return bestRun.map(v => mapV[v][0]);
            }
            return null;
        }

        while (true) {
            const st = findLongestStraight(pool);
            if (!st) break;
            straights.push(st);
            const stIds = st.map(c => c.id);
            pool = pool.filter(c => !stIds.includes(c.id));
        }

        // 3. Từ bài còn lại trong pool, tìm Bộ Ba, Đôi, và Rác thuần túy
        const triples = [];
        const pairs = [];
        const pureSingles = [];

        const remMap = {};
        pool.forEach(c => {
            if (!remMap[c.v]) remMap[c.v] = [];
            remMap[c.v].push(c);
        });

        for (let v in remMap) {
            const grp = remMap[v];
            if (grp.length === 3) triples.push(grp);
            else if (grp.length === 2) pairs.push(grp);
            else pureSingles.push(...grp);
        }

        triples.sort((a, b) => a[0].v - b[0].v);
        pairs.sort((a, b) => a[0].v - b[0].v);
        pureSingles.sort((a, b) => a.v - b.v);
        straights.sort((a, b) => b.length - a.length || a[0].v - b[0].v);

        return {
            sorted,
            quads,
            straights,
            triples,
            pairs,
            pureSingles
        };
    }

    // ═══════════════════════════════════════════════════════
    // CHIẾN THUẬT RA BÀI ĐẲNG CẤP "THÁNH BÀI SÂM LỐC"
    // ═══════════════════════════════════════════════════════
    function decideMove(cards, lastMove) {
        if (!cards || cards.length === 0) return null;

        const analysis = analyzeHand(cards);
        const { sorted, quads, straights, triples, pairs, pureSingles } = analysis;
        const nextCount = getNextOpponentCardCount();

        // ── 1. ĐẦU VÒNG (Không có lastMove) ──
        if (!lastMove || !lastMove.type) {
            // [Chiến thuật 1.1] CHỐNG THỐI HEO:
            // Nếu bài chỉ còn 2 hoặc 3 lá mà có Heo (15):
            // Phải đánh Heo ra trước để giành cái / tống Heo an toàn, tuyệt đối không để Heo là lá cuối cùng!
            const twos = sorted.filter(c => c.v === 15);
            if (twos.length > 0 && cards.length <= 3) {
                console.log('[Thánh Bài] Xả Heo an toàn chống thối 2!');
                return [twos[0]];
            }

            // [Chiến thuật 1.2] BÁO BÀI ĐỐI PHƯƠNG:
            // Nếu người kế tiếp chỉ còn 1 lá (nextCount === 1):
            // Tuyệt đối không đánh lá rác nhỏ! Đánh Sảnh, Đôi, Ba hoặc lá to nhất!
            if (nextCount === 1) {
                if (straights.length > 0) return straights[0];
                if (triples.length > 0) return triples[0];
                if (pairs.length > 0) return pairs[0];
                // Buộc phải đánh lẻ: Đánh lá to nhất trên tay để khóa cửa!
                console.log('[Thánh Bài] Người kế tiếp báo bài! Ra lá to nhất khóa cửa!');
                return [sorted[sorted.length - 1]];
            }

            // [Chiến thuật 1.3] XẢ SẢNH DÀI:
            // Ưu tiên sảnh dài từ 5 lá trở lên để tống số lượng lớn bài
            if (straights.length > 0 && straights[0].length >= 5) {
                return straights[0];
            }

            // [Chiến thuật 1.4] XẢ BỘ BA & ĐÔI NHỎ:
            if (triples.length > 0) return triples[0];
            if (pairs.length > 0) return pairs[0];
            if (straights.length > 0) return straights[0];

            // [Chiến thuật 1.5] TỐNG RÁC NHỎ:
            // Ưu tiên xả rác thuần túy nhỏ nhất (trừ Heo 15)
            const nonTwos = pureSingles.filter(c => c.v < 15);
            if (nonTwos.length > 0) return [nonTwos[0]];

            const allNonTwos = sorted.filter(c => c.v < 15);
            if (allNonTwos.length > 0) return [allNonTwos[0]];

            return [sorted[0]];
        }

        // ── 2. PHẢI ĐÈ BÀI ĐỐI THỦ ──
        const moveType = lastMove.type;
        const moveVal = lastMove.value;
        const moveCount = lastMove.count || 0;

        // [A] ĐỐI THỦ ĐÁNH BÀI LẺ (SINGLE)
        if (moveType === 'single') {
            // [Chiến thuật 2.1] BẮT HEO BẰNG TỨ QUÝ:
            if (moveVal === 15) {
                if (quads.length > 0) {
                    console.log('[Thánh Bài] 🐷 BẮT TRỌN HEO VÀNG! Tứ Quý chặt ngay!');
                    if (typeof BotChat !== 'undefined' && BotChat.send) {
                        BotChat.send(49, 'bot_49', 'Bắt trọn Heo vàng! Tứ quý ra chiêu húp trọn GTLM! 🐷⚡');
                    }
                    return quads[0];
                }
                return null; // Không có tứ quý -> nhịn để giữ bài
            }

            // [Chiến thuật 2.2] CHẶN CỬA BÁO BÀI (Người kế tiếp còn 1 lá):
            if (nextCount === 1) {
                // Người kế tiếp chỉ còn 1 lá -> BẮT BUỘC ĐÁNH LÁ TO NHẤT CÓ THỂ để chặn!
                const bigCards = sorted.filter(c => c.v > moveVal);
                if (bigCards.length > 0) {
                    const topCard = bigCards[bigCards.length - 1];
                    console.log('[Thánh Bài] Khóa cửa người báo bài bằng lá đỉnh:', topCard.id);
                    return [topCard];
                }
                return null;
            }

            // [Chiến thuật 2.3] ĐÈ BẰNG RÁC THUẦN TÚY (Không phá bộ/sảnh):
            const beaters = pureSingles.filter(c => c.v > moveVal && c.v < 15);
            if (beaters.length > 0) {
                return [beaters[0]];
            }

            // [Chiến thuật 2.4] TẬN DỤNG HEO (15) CƯỚP CÁI:
            // Khi bài đối thủ là A (14) hoặc K (13), hoặc bài mình chỉ còn <= 3 lá:
            const twos = sorted.filter(c => c.v === 15);
            if (twos.length > 0 && (cards.length <= 3 || moveVal >= 13)) {
                return [twos[0]];
            }

            // [Chiến thuật 2.5] CHẤP NHẬN XÉ ĐÔI NHỎ NẾU CẦN GIÀNH LƯỢT:
            // Nếu bài đối thủ nhỏ (<= 8) và mình có đôi nhỏ (<= 9), không xé sảnh, không xé tứ quý:
            if (cards.length <= 4 && pairs.length > 0) {
                const softPair = pairs.find(p => p[0].v > moveVal && p[0].v < 15);
                if (softPair) {
                    return [softPair[0]];
                }
            }

            return null; // Nhịn bài thông minh, bảo toàn sảnh và bộ
        }

        // [B] ĐỐI THỦ ĐÁNH ĐÔI (PAIR)
        if (moveType === 'pair') {
            const beatPairs = pairs.filter(p => p[0].v > moveVal);
            if (beatPairs.length > 0) {
                return beatPairs[0];
            }
            return null;
        }

        // [C] ĐỐI THỦ ĐÁNH BỘ BA (TRIPLE)
        if (moveType === 'triple') {
            const beatTriples = triples.filter(t => t[0].v > moveVal);
            if (beatTriples.length > 0) {
                return beatTriples[0];
            }
            return null;
        }

        // [D] ĐỐI THỦ ĐÁNH SẢNH (STRAIGHT)
        if (moveType === 'straight') {
            for (let s of straights) {
                if (s.length >= moveCount) {
                    for (let start = 0; start <= s.length - moveCount; start++) {
                        const sub = s.slice(start, start + moveCount);
                        if (sub[sub.length - 1].v > moveVal) {
                            return sub;
                        }
                    }
                }
            }
            return null;
        }

        // [E] ĐỐI THỦ ĐÁNH TỨ QUÝ (QUAD)
        if (moveType === 'quad') {
            const beatQuads = quads.filter(q => q[0].v > moveVal);
            if (beatQuads.length > 0) {
                console.log('[Thánh Bài] Tứ quý đè tứ quý! Đẳng cấp tối thượng!');
                return beatQuads[0];
            }
            return null;
        }

        return null;
    }

    // ═══════════════════════════════════════════════════════
    // THỰC HIỆN NƯỚC ĐI BẰNG CHUỘT ẢO
    // ═══════════════════════════════════════════════════════
    function executePlay(cardsToPlay) {
        if (!cardsToPlay || cardsToPlay.length === 0) {
            executePass();
            return;
        }

        setBusy(true, 4000);

        // Bỏ chọn các lá cũ trước
        $('#my-hand .card.selected').each(function () {
            const id = $(this).attr('data-id');
            if (typeof toggleCard === 'function') toggleCard(id);
        });

        // Click chọn lần lượt từng lá bài cần đánh
        let idx = 0;
        function pickNextCard() {
            if (idx < cardsToPlay.length) {
                const card = cardsToPlay[idx];
                idx++;
                BotVirtualCursor.moveToElement(card.el, 0.4, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        if (typeof toggleCard === 'function') toggleCard(card.id);
                        setTimeout(pickNextCard, 200);
                    });
                });
            } else {
                // Di chuyển tới nút ĐÁNH BÀI
                const $btnPlay = $('#btn-play');
                if ($btnPlay.length && !$btnPlay.prop('disabled')) {
                    BotVirtualCursor.moveToElement($btnPlay, 0.5, 0, () => {
                        BotVirtualCursor.simulateClick(() => {
                            if (typeof playCards === 'function') playCards();
                            setBusy(false);
                        });
                    });
                } else {
                    setBusy(false);
                }
            }
        }

        pickNextCard();
    }

    function executePass() {
        const $btnPass = $('#btn-pass');
        if ($btnPass.length && !$btnPass.prop('disabled')) {
            setBusy(true, 2500);
            BotVirtualCursor.moveToElement($btnPass, 0.5, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    if (typeof passTurn === 'function') passTurn();
                    setBusy(false);
                });
            });
        }
    }

    let lobbyEnteredAt = 0;
    let hasMovedInLobby = false;

    // ═══════════════════════════════════════════════════════
    // VÒNG LẶP CHÍNH CỦA BOT
    // ═══════════════════════════════════════════════════════
    function botLoop() {
        if (botIsBusy) return;

        const now = Date.now();
        if (now - lastActionTime < 800) return;

        // ── A. XỬ LÝ KHI Ở SẢNH CHỜ (LOBBY VIEW) ──
        const $lobby = $('.lobby-container');
        if ($lobby.length && $lobby.is(':visible')) {
            lastActionTime = now;

            if (!lobbyEnteredAt) {
                lobbyEnteredAt = now;
                hasMovedInLobby = false;
            }

            // Chờ ít nhất 2.5s để khán giả xem danh sách phòng và streamer "lựa bàn"
            if (now - lobbyEnteredAt < 2500) {
                if (!hasMovedInLobby) {
                    hasMovedInLobby = true;
                    const $header = $('.lobby-title');
                    if ($header.length) {
                        BotVirtualCursor.moveToElement($header, 1.0, 0);
                    }
                }
                return;
            }

            // Chờ danh sách phòng được nạp từ API
            if (typeof window.roomsLoaded !== 'undefined' && !window.roomsLoaded) {
                return;
            }

            // Tìm các phòng đang mở và còn chỗ trống (< 4 người)
            const availableButtons = [];
            $('.room-card').each(function() {
                const count = parseInt($(this).attr('data-players') || '0', 10);
                const status = $(this).attr('data-status') || '';
                if (count < 4 && status !== 'playing') {
                    const $btn = $(this).find('.btn-join-room');
                    if ($btn.length && $btn.is(':visible')) {
                        availableButtons.push($btn);
                    }
                }
            });

            // Nếu có phòng mở: 75% vào phòng có sẵn, 25% tự tạo phòng riêng để live hấp dẫn
            const shouldJoinExisting = (availableButtons.length > 0) && (Math.random() > 0.25 || availableButtons.length >= 3);

            if (shouldJoinExisting) {
                // Chọn một phòng mở để vào bàn
                const $targetBtn = availableButtons[Math.floor(Math.random() * availableButtons.length)];
                setBusy(true, 3500);
                BotVirtualCursor.moveToElement($targetBtn, 0.8, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        $targetBtn.click();
                    });
                });
                return;
            } else {
                // Nếu chưa có phòng hoặc bot muốn host trận mới, bấm "+ TẠO PHÒNG MỚI"
                const $btnCreate = $('#btn-create-room');
                if ($btnCreate.length && $btnCreate.is(':visible')) {
                    setBusy(true, 4000);
                    BotVirtualCursor.moveToElement($btnCreate, 0.8, 0, () => {
                        BotVirtualCursor.simulateClick(() => {
                            if (typeof botCreateRoom === 'function') {
                                botCreateRoom();
                            } else {
                                $btnCreate.click();
                            }
                        });
                    });
                    return;
                }
            }
            return;
        }

        // ── B. XỬ LÝ KHI Ở BÀN CHƠI GAME (TABLE VIEW) ──
        lobbyEnteredAt = 0;
        hasMovedInLobby = false;

        // 1. Tự động thêm bot nếu bàn đang chờ
        const $waitingControls = $('#waiting-controls');
        const $btnAddBot = $('#btn-add-bot');
        if ($waitingControls.is(':visible') && $btnAddBot.is(':visible') && !$btnAddBot.prop('disabled')) {
            const playerCount = $('.player-slot:visible').length;
            if (playerCount < 4) {
                lastActionTime = now;
                setBusy(true, 2500);
                BotVirtualCursor.moveToElement($btnAddBot, 0.6, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        if (typeof addBot === 'function') addBot();
                        setBusy(false);
                    });
                });
                return;
            }
        }

        // 2. Xử lý Hô Sâm / Xin Làng (khi đã nhận 10 lá bài)
        const $xinLangBtn = $('#btn-xin-lang');
        const $skipXinLangBtn = $('#btn-skip-xin-lang');
        if ($('#xinlang-controls').is(':visible') && $xinLangBtn.is(':visible')) {
            const myCards = parseCardsFromHand();
            const straights = findStraights(myCards);
            if (straights.length > 0 && straights[0].length >= 8) {
                lastActionTime = now;
                setBusy(true, 2000);
                BotVirtualCursor.moveToElement($xinLangBtn, 0.6, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        if (typeof xinLang === 'function') xinLang();
                        setBusy(false);
                    });
                });
                return;
            } else if ($skipXinLangBtn.is(':visible')) {
                // Bài bình thường, ngắm bài 1.2s rồi rê chuột bấm BỎ QUA để vào trận nhanh
                lastActionTime = now;
                setBusy(true, 2500);
                setTimeout(() => {
                    if ($skipXinLangBtn.is(':visible')) {
                        BotVirtualCursor.moveToElement($skipXinLangBtn, 0.5, 0, () => {
                            BotVirtualCursor.simulateClick(() => {
                                if (typeof skipXinLang === 'function') skipXinLang();
                                setBusy(false);
                            });
                        });
                    } else {
                        setBusy(false);
                    }
                }, 1200);
                return;
            }
        }

        // 3. Đến lượt bot đánh bài
        if (typeof isMyTurn !== 'undefined' && isMyTurn) {
            lastActionTime = now;
            const myCards = parseCardsFromHand();
            if (myCards.length > 0) {
                let lastMove = null;
                if (typeof window.currentTableLastMove !== 'undefined') {
                    lastMove = window.currentTableLastMove;
                }
                const cardsToPlay = decideMove(myCards, lastMove);
                executePlay(cardsToPlay);
            }
        }

        // 4. Kiểm tra kết thúc ván
        const statusText = ($('#game-status').text() || '').toLowerCase();
        if (statusText.includes('kết thúc ván')) {
            if (!hasHandledEnd) {
                hasHandledEnd = true;
                const isWon = $('#slot-0 .player-info').text().includes('WINNER') || (typeof window.lastRoundWinner !== 'undefined' && window.lastRoundWinner === myUserId);
                sendBotChat(isWon ? 'win' : 'lose');

                // Nghỉ 3.8s để người xem theo dõi kết quả, sau đó streamer quay về Sảnh Chờ để tìm bàn mới
                setTimeout(() => {
                    const $btnBack = $('#btn-back-lobby');
                    if ($btnBack.length && $btnBack.is(':visible')) {
                        setBusy(true, 5000);
                        BotVirtualCursor.moveToElement($btnBack, 0.8, 0, () => {
                            BotVirtualCursor.simulateClick(() => {
                                window.location.href = 'live_49.php';
                            });
                        });
                    }
                }, 3800);
            }
        } else {
            hasHandledEnd = false;
        }
    }

    // Khởi động bot loop
    setInterval(botLoop, 800);
    console.log('[Bot 49] Sâm Lốc Master AI (Lobby + Table) đã sẵn sàng!');

})();
