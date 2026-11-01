/**
 * 🌾 Bot Streamer Bàn 23: Nông Dân Triệu Phú 24 (Nông Trại AFK) - TURBO FAST-PACED MODE
 * Chuẩn Modern Async/Await + Chuột Ảo An Toàn + Triple Watchdog + Casino Rule 5.3
 * Nhịp điệu dồn dập: Gieo hạt siêu tốc -> Bón phân ép chín liên tục -> Thu hoạch giòn giã!
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 23] BotVirtualCursor chưa nạp!');
        return;
    }

    BotVirtualCursor.init('Nông Dân Triệu Phú 24 🌾');

    let botIsBusy = false;
    let busyTimestamp = 0;

    const CASINO_CHATS = {
        harvest: [
            "Vụ mùa bội thu rồi anh em ơi! Thu hoạch nông sản húp đậm GTLM! 🌾💰",
            "Cây chín vàng óng ả! Lộc trời ban, gặt hái phát tài nào! 🚜🎉",
            "Nông sản vụ này tươi rói, bán lấy bạc triệu ngay! 🍎✨",
            "Húp trọn mẻ nông sản VIP, tiền về như nước mùa lũ! 🤑💎"
        ],
        plant: [
            "Gieo hạt giống tài lộc, đất lành chim đậu chờ ngày gặt hái! 🌱✨",
            "Đầu tư vụ ngô mới, tiếng này tỷ lệ sinh lời cực cao! 🌽🔥",
            "Xuống giống cà chua đỏ mọng, chuẩn bị đón lộc vần! 🍅🌟",
            "Trồng cây sinh tiền, cần cù cày cuốc ngày kiếm vài triệu GTLM! 🍀💪"
        ],
        fertilize: [
            "Bón phân siêu tốc, ép cây chín thần tốc phục vụ anh em xem live! 💩⚡",
            "Tăng tốc mùa màng, rút ngắn thời gian húp lộc liền tay! 🚀✨",
            "Phân bón chất lượng cao, cây lớn nhanh như thổi! 🌿🌟"
        ],
        idle: [
            "Nông trại AFK sinh sôi nảy nở, ngắm cây lớn cũng kiếm ra tiền! 🌻✨",
            "Ai bảo làm nông vất vả, tôi ngồi mát húp GTLM đều đều! 😎💰"
        ]
    };

    function sendChat(type) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.idle;
            const msg = list[Math.floor(Math.random() * list.length)];
            BotChat.send(23, 'bot_23', msg);
        }
    }

    // Chuột ảo Turbo tốc độ cao 0.22s
    function fastMoveAndClick(el, duration = 0.22) {
        return new Promise((resolve) => {
            const $el = $(el);
            if (!$el.length) return resolve(false);

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
                        } catch (e) {}
                        if (!hasResolved) {
                            hasResolved = true;
                            clearTimeout(safetyTimer);
                            resolve(true);
                        }
                    });
                });
            } catch (err) {
                if (!hasResolved) {
                    hasResolved = true;
                    clearTimeout(safetyTimer);
                    resolve(false);
                }
            }
        });
    }

    const wait = (ms) => new Promise((res) => setTimeout(res, ms));

    // Đảm bảo bot luôn có hạt giống và phân bón dồi dào
    function autoSupplyIfNeeded() {
        if (typeof inventory !== 'undefined') {
            if ((inventory.seed_wheat || 0) < 5) {
                $.post('../api_farm.php', { action: 'buy_item', item: 'WHEAT', amount: 50 });
            }
            if ((inventory.fertilizer || 0) < 5) {
                $.post('../api_farm.php', { action: 'buy_item', item: 'FERTILIZER', amount: 50 });
            }
        }
    }

    async function executeBotTurn() {
        if (botIsBusy) return;

        botIsBusy = true;
        busyTimestamp = Date.now();

        try {
            autoSupplyIfNeeded();

            // 1. ƯU TIÊN 1: Thu hoạch nếu có ô chín (.plot.ready)
            const readyPlots = $('.plot.ready');
            if (readyPlots.length > 0) {
                const targetPlot = $(readyPlots[0]);
                if (Math.random() < 0.35) sendChat('harvest');
                await fastMoveAndClick(targetPlot, 0.22);
                await wait(200);
                return;
            }

            // 2. ƯU TIÊN 2: Gieo hạt vào ô đất trống
            const allPlots = $('.plot');
            let emptyPlot = null;
            let emptyPlotIndex = -1;

            allPlots.each(function (idx) {
                const timerText = $(this).find('.plot-timer').text().trim();
                if (!timerText || timerText === '' || timerText === 'Trống' || timerText === '0s') {
                    emptyPlot = $(this);
                    emptyPlotIndex = idx;
                    return false;
                }
            });

            if (emptyPlot) {
                if (Math.random() < 0.35) sendChat('plant');
                await fastMoveAndClick(emptyPlot, 0.22);
                await wait(150);

                // Gieo Lúa mì (WHEAT) hoặc Ngô (CORN)
                const seed = (Math.random() < 0.7) ? 'WHEAT' : 'CORN';
                if (typeof plantSeed === 'function') {
                    window.selectedPlotIndex = emptyPlotIndex;
                    plantSeed(seed);
                }
                $('#seedModal').hide();
                await wait(200);
                return;
            }

            // 3. ƯU TIÊN 3: Nếu tất cả cây đều đang lớn -> Bón phân ép chín / giảm 50% TG
            const growingPlots = $('.plot[data-harvest]');
            if (growingPlots.length > 0) {
                // Chọn 1 ô cây đang lớn
                const randomIdx = Math.floor(Math.random() * growingPlots.length);
                const targetPlot = $(growingPlots[randomIdx]);

                if (Math.random() < 0.3) sendChat('fertilize');
                await fastMoveAndClick(targetPlot, 0.22);
                await wait(150);

                // Thực hiện bón phân ép nhanh
                const plotIdAttr = targetPlot.attr('onclick') || '';
                const match = plotIdAttr.match(/openModal\((\d+)/);
                const plotIndex = match ? parseInt(match[1], 10) : randomIdx;

                window.selectedPlotIndex = plotIndex;
                if (typeof executeFertilize === 'function') {
                    executeFertilize(Math.random() < 0.6 ? 'single' : 'max');
                }
                $('#seedModal').hide();
                await wait(250);
                return;
            }

            // 4. Nếu không có việc gì (hiếm khi xảy ra), chat vui vẻ
            if (Math.random() < 0.2) {
                sendChat('idle');
            }

        } catch (e) {
            console.error('[Bot 23 Error]', e);
        } finally {
            botIsBusy = false;
        }
    }

    // Vòng lặp nông nghiệp TURBO: 1.4 giây
    setInterval(executeBotTurn, 1400);

    // TRIPLE-LAYER WATCHDOG
    setInterval(() => {
        // Tầng 1: Giải phóng bận sau 5s
        if (botIsBusy && Date.now() - busyTimestamp > 5000) {
            console.warn('[Bot 23 Watchdog] Tự giải phóng cờ bận');
            botIsBusy = false;
        }

        // Tầng 2: Nếu modal hạt giống kẹt mở quá 2s -> Tự đóng
        const $modal = $('#seedModal');
        if ($modal.is(':visible')) {
            $modal.hide();
        }

        // Tầng 3: Tự động fetch data nông trại định kỳ để tránh lệch trạng thái
        if (typeof fetchFarm === 'function') {
            fetchFarm();
        }
    }, 2500);

    console.log('[Bot 23] Nông Dân Triệu Phú 24 TURBO đã kích hoạt!');
})();
