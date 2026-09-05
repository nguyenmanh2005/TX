/**
 * 🤖 Bot AI Cào Vé Số (Scratch Card) Chuyên Nghiệp (ID: 50)
 *
 * TÍNH NĂNG & TRÍ THÔNG MINH CAO CẤP:
 * 1. QUẢN LÝ TÀI CHÍNH & MỨC CƯỢC:
 *    - Tự động kiểm tra số dư ví GTLM của Streamer.
 *    - Chọn mức cược linh hoạt (10K, 50K, 100K, 500K) thông qua rê chuột ảo tới các nút `.btn-quick-bet`.
 *    - Tránh spam ALL IN nguy hiểm, giữ nhịp chơi bền bỉ cho người xem livestream.
 * 2. CƠ CHẾ CÀO THẺ ĐA PHONG CÁCH (Interactive Scratching):
 *    - Phong cách 1 (70%): Cào tỉ mỉ từng ô (.scratch-tile), di chuột ảo tới từng vị trí và cào hé lộ biểu tượng.
 *    - Phong cách 2 (30%): Cào nhanh siêu tốc, sau khi mở 1-2 ô thì rê chuột bấm nút "⚡ Cào Nhanh (Mở Hết)".
 * 3. HỆ THỐNG CHUỘT ẢO CHUYÊN NGHIỆP:
 *    - Di chuyển mượt mà tự nhiên với `BotVirtualCursor.moveToElement`.
 *    - Hiệu ứng click sống động (`simulateClick`).
 *    - Tuyệt đối KHÔNG click lung tung vào các nút Hướng Dẫn, Quay về Dashboard, v.v.
 * 4. TƯƠNG TÁC CHAT & CẢM XÚC:
 *    - Bắt sự kiện từ modal kết quả `#result-status-badge` để phát ngôn câu thoại Casino cực chất.
 *    - Dừng 3.5s - 4.5s để người xem theo dõi kết quả thắng/thua rõ ràng.
 * 5. WATCHDOG CHỐNG KẸT:
 *    - Tự động phục hồi trạng thái sau 7 giây nếu gặp sự cố lag mạng.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 50] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('Thần Cào Vé 50');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI VÀ BIẾN ĐIỀU KHIỂN
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimer = null;
    let lastChatTime = 0;
    let winStreak = 0;
    let lossStreak = 0;
    let currentStep = 'idle'; // 'idle' | 'betting' | 'scratching' | 'waiting_result'

    function setBusy(val, timeoutMs = 8000) {
        botIsBusy = val;
        if (busyTimer) clearTimeout(busyTimer);
        if (val) {
            busyTimer = setTimeout(() => {
                console.log('[Bot 50] Watchdog giải phóng trạng thái bận!');
                botIsBusy = false;
                currentStep = 'idle';
            }, timeoutMs);
        }
    }

    // ═══════════════════════════════════════════════════════
    // BÌNH LUẬN & PHÁT NGÔN CASINO LIVESTREAM
    // ═══════════════════════════════════════════════════════
    const bigWinPhrases = [
        'Ối giồi ôi! Nổ trúng biểu tượng kim cương / jackpot x100 GTLM rồi anh em ơi! 💎🎉🔥',
        'Cào phát ra quà khủng luôn! Tay này son quá, húp trọn vinh quang! 🏆✨',
        'Jackpot cào thẻ phát nổ rực rỡ! Lộc lá ngập tràn phòng live! 🎰💰',
        'Bảo rồi mà, phong thủy hôm nay quá đẹp! Anh em vào xin vía nào! 🚀💵'
    ];

    const normalWinPhrases = [
        'Có lộc có lộc! Khớp 3 biểu tượng rực rỡ, húp GTLM nhẹ nhàng! 🍒🍋',
        'Cào là trúng! Tiếp tục giữ phong độ nào anh em ơi! 🔔⭐',
        'Thơm phức! Vé số hôm nay mát tay thật sự! 🍀✨',
        'Thắng tiếp một ván nữa! Đang trên đà húp thưởng! 💰'
    ];

    const lossPhrases = [
        'Hụt một phát xíu xiu! Vé sau bù lại liền tay! 😤',
        'Vé này nhả lộc cho ván sau nổ to hơn! Anh em kiên nhẫn nhé! 💨',
        'Chưa khớp 3 hình nhưng cảm giác vé tiếp theo sẽ nổ Diamond! 💎🎯',
        'Tập trung làm lại vé mới, không có gì phải xoắn! 🔥'
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
            BotChat.send(50, 'bot_50', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // QUẢN LÝ VỐN & CHỌN MỨC CƯỢC
    // ═══════════════════════════════════════════════════════
    function getBalance() {
        const moneyEl = document.getElementById('userMoney');
        if (!moneyEl) return 1000000;
        return parseInt(moneyEl.innerText.replace(/\D/g, '')) || 0;
    }

    function selectOptimalBetButton() {
        const balance = getBalance();
        const quickBtns = Array.from(document.querySelectorAll('.btn-quick-bet'));
        if (quickBtns.length === 0) return null;

        // Lọc bỏ ALL IN để bot không bấm bừa bãi
        const normalBtns = quickBtns.filter(b => !b.innerText.includes('ALL IN'));
        if (normalBtns.length === 0) return null;

        let target = normalBtns[0]; // Mặc định 10K

        if (balance > 10000000) {
            // Số dư dồi dào > 10M: Chọn 100K, 500K hoặc 1M
            const highBtns = normalBtns.filter(b => b.innerText.includes('100K') || b.innerText.includes('500K') || b.innerText.includes('1M'));
            if (highBtns.length > 0) {
                target = highBtns[Math.floor(Math.random() * highBtns.length)];
            }
        } else if (balance > 2000000) {
            // Số dư khá > 2M: Chọn 50K hoặc 100K
            const midBtns = normalBtns.filter(b => b.innerText.includes('50K') || b.innerText.includes('100K'));
            if (midBtns.length > 0) {
                target = midBtns[Math.floor(Math.random() * midBtns.length)];
            }
        } else {
            // Số dư thấp: Chọn 10K hoặc 50K
            const lowBtns = normalBtns.filter(b => b.innerText.includes('10K') || b.innerText.includes('50K'));
            if (lowBtns.length > 0) {
                target = lowBtns[Math.floor(Math.random() * lowBtns.length)];
            }
        }

        return target;
    }

    // ═══════════════════════════════════════════════════════
    // THỰC HIỆN CÀO CÁC Ô TỈ MỈ (PHONG CÁCH CHUYÊN NGHIỆP)
    // ═══════════════════════════════════════════════════════
    function scratchTilesSequentially(tiles, onComplete) {
        if (!tiles || tiles.length === 0) {
            if (onComplete) onComplete();
            return;
        }

        let currentIdx = 0;

        function scratchNext() {
            if (currentIdx >= tiles.length) {
                if (onComplete) onComplete();
                return;
            }

            const tile = tiles[currentIdx];
            currentIdx++;

            if (!tile || $(tile).hasClass('revealed')) {
                scratchNext();
                return;
            }

            BotVirtualCursor.moveToElement($(tile), 0.35, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { tile.click(); } catch (e) { }

                        // Nghỉ một chút giữa các lần cào (350ms - 550ms) tạo cảm giác như người thật đang cào thẻ
                        const delayBetweenScratches = 350 + Math.random() * 200;
                        setTimeout(scratchNext, delayBetweenScratches);
                    });
                }, 80);
            });
        }

        scratchNext();
    }

    // ═══════════════════════════════════════════════════════
    // VÒNG LẶP CHÍNH CỦA BOT (GAMEPLAY LOOP)
    // ═══════════════════════════════════════════════════════
    function botLoop() {
        if (botIsBusy) return;

        // Kiểm tra biến trạng thái trong live_50.php
        if (typeof isBuying !== 'undefined' && isBuying) return;
        if (typeof isAutoRevealing !== 'undefined' && isAutoRevealing) return;

        const buyBtn = document.getElementById('buyBtn');
        if (!buyBtn || buyBtn.disabled) return;

        const unrevealedTiles = Array.from(document.querySelectorAll('.scratch-tile:not(.revealed)'));
        const hasActiveGrid = typeof currentGrid !== 'undefined' && currentGrid.length === 9;

        // TRƯỜNG HỢP 1: ĐANG CÓ VÉ CẦN CÀO
        if (hasActiveGrid && unrevealedTiles.length > 0) {
            setBusy(true, 10000);
            currentStep = 'scratching';

            // 70% cào từng ô một cách kịch tính, 30% cào 1-2 ô rồi cào nhanh
            const isSpeedScratch = Math.random() < 0.3 && unrevealedTiles.length > 4;

            if (isSpeedScratch) {
                // Cào trước 2 ô
                const firstTwo = unrevealedTiles.slice(0, 2);
                scratchTilesSequentially(firstTwo, () => {
                    // Sau đó rê chuột tới nút 'Cào Nhanh' để mở hết
                    setTimeout(() => {
                        BotVirtualCursor.moveToElement($(buyBtn), 0.4, 0, () => {
                            setTimeout(() => {
                                BotVirtualCursor.simulateClick(() => {
                                    try { buyBtn.click(); } catch (e) { }
                                    // Chờ mở hết và badge xuất hiện
                                    setTimeout(() => {
                                        setBusy(false);
                                    }, 4000);
                                });
                            }, 100);
                        });
                    }, 300);
                });
            } else {
                // Cào tuần tự toàn bộ các ô còn lại theo thứ tự xáo trộn nhẹ
                const shuffledTiles = [...unrevealedTiles].sort(() => Math.random() - 0.5);
                scratchTilesSequentially(shuffledTiles, () => {
                    // Đã cào xong hết, đợi hiệu ứng kết thúc
                    setTimeout(() => {
                        setBusy(false);
                    }, 4000);
                });
            }
            return;
        }

        // TRƯỜNG HỢP 2: BẮT ĐẦU VÁN MỚI (CHỌN CƯỢC & MUA VÉ)
        if (!hasActiveGrid || unrevealedTiles.length === 0) {
            setBusy(true, 8000);
            currentStep = 'betting';

            // 35% xác suất đổi mức cược cho phong phú
            const shouldChangeBet = Math.random() < 0.35;
            const betBtn = selectOptimalBetButton();

            if (shouldChangeBet && betBtn) {
                BotVirtualCursor.moveToElement($(betBtn), 0.4, 0, () => {
                    setTimeout(() => {
                        BotVirtualCursor.simulateClick(() => {
                            try { betBtn.click(); } catch (e) { }

                            // Sau khi chọn cược, rê chuột tới nút Mua Vé
                            setTimeout(() => {
                                clickBuyButton(buyBtn);
                            }, 400);
                        });
                    }, 120);
                });
            } else {
                // Bấm Mua Vé luôn
                clickBuyButton(buyBtn);
            }
        }
    }

    function clickBuyButton(buyBtn) {
        BotVirtualCursor.moveToElement($(buyBtn), 0.45, 0, () => {
            setTimeout(() => {
                BotVirtualCursor.simulateClick(() => {
                    try { buyBtn.click(); } catch (e) { }

                    // Đợi server xử lý mua vé (khoảng 800ms - 1.2s)
                    setTimeout(() => {
                        setBusy(false);
                        currentStep = 'ready_to_scratch';
                    }, 1200);
                });
            }, 150);
        });
    }

    // ═══════════════════════════════════════════════════════
    // THEO DÕI BADGE THẮNG/THUA ĐỂ GỬI CHAT & CẬP NHẬT TÂM LÝ
    // ═══════════════════════════════════════════════════════
    function setupResultBadgeObserver() {
        const badge = document.getElementById('result-status-badge');
        if (!badge) return;

        const observer = new MutationObserver(() => {
            if (badge.style.display !== 'none' && badge.style.opacity === '1') {
                const titleEl = document.getElementById('result-badge-title');
                const amtEl = document.getElementById('result-badge-amount');
                const title = titleEl ? titleEl.textContent : '';
                const amtText = amtEl ? amtEl.textContent : '';
                const amount = parseInt(amtText.replace(/\D/g, '')) || 0;

                if (title.includes('THẮNG')) {
                    winStreak++;
                    lossStreak = 0;
                    if (amount >= 500000) {
                        sendBotChat('big');
                    } else {
                        sendBotChat('win');
                    }
                } else if (title.includes('BAY MÀU') || title.includes('THUA') || title.includes('KHÔNG')) {
                    lossStreak++;
                    winStreak = 0;
                    sendBotChat('loss');
                }
            }
        });

        observer.observe(badge, { attributes: true, attributeFilter: ['style', 'class'] });
    }

    // Khởi động bot
    $(document).ready(() => {
        setupResultBadgeObserver();
        // Nhịp kiểm tra mỗi 1.2s - 1.8s
        setInterval(botLoop, 1500);
        console.log('[Bot 50] Thần Cào Vé 50 đã kích hoạt thành công!');
    });

})();
