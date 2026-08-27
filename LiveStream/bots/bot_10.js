/**
 * 🤖 BOT STREAMER PRO V3 - XẠ THỦ ĐẠI DƯƠNG (BẮN CÁ ARCADE 3D)
 * - Tự động định vị các đàn cá đang bơi trên màn hình (Cá Xanh, Cá Vàng, Cá Mập, Rồng Biển).
 * - Chuột ảo GSAP ngắm bắn chuẩn xác theo đường bơi của cá, bắn liên thanh và đổi nòng pháo (500, 1000, 5000).
 * - Cập nhật liên tục số dư GTLM và hiệu ứng nổ húp cá.
 */
if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Xạ Thủ Đại Dương 🦈🔫");
    let botBusy = false;
    let lastBulletChangeTime = 0;

    function runFishShooterBot() {
        if (botBusy) return;

        const canvasEl = document.getElementById('gameCanvas');
        if (!canvasEl) {
            setTimeout(runFishShooterBot, 500);
            return;
        }

        const now = Date.now();

        // ── 1. ĐỔI CỠ ĐẠN PHÁO (30% cơ hội mỗi 15-30s) ──
        const bulletBtns = Array.from(document.querySelectorAll('.bullet-btn'));
        if (bulletBtns.length > 0 && now - lastBulletChangeTime > 15000 && Math.random() < 0.3) {
            lastBulletChangeTime = now;
            botBusy = true;
            const targetBtn = bulletBtns[Math.floor(Math.random() * bulletBtns.length)];
            BotVirtualCursor.moveToElement($(targetBtn), 0.25, 0, () => {
                BotVirtualCursor.simulateClick(() => {
                    try { targetBtn.click(); } catch(e){}
                    botBusy = false;
                    setTimeout(runFishShooterBot, 300);
                });
            });
            return;
        }

        // ── 2. TÌM VÀ NGẮM BẮN CÁ ĐANG BƠI ──
        let targetX = canvasEl.width * (0.3 + Math.random() * 0.4);
        let targetY = canvasEl.height * (0.2 + Math.random() * 0.5);

        // Nếu mảng fishes có cá đang sống
        if (typeof fishes !== 'undefined' && Array.isArray(fishes) && fishes.length > 0) {
            const aliveFishes = fishes.filter(f => !f.isDead && f.x > 50 && f.x < canvasEl.width - 50 && f.y > 50 && f.y < canvasEl.height - 150);
            if (aliveFishes.length > 0) {
                // Ưu tiên ngắm cá to (Cá Mập, Bạch Tuộc, Rồng Biển)
                aliveFishes.sort((a, b) => (b.multiplier || 1) - (a.multiplier || 1));
                const targetFish = aliveFishes[Math.random() < 0.7 ? 0 : Math.floor(Math.random() * aliveFishes.length)];
                targetX = targetFish.x;
                targetY = targetFish.y;
            }
        }

        botBusy = true;

        // Rê chuột ảo tới tọa độ con cá (0.15s - 0.22s)
        const cursor = $('#' + BotVirtualCursor.cursorId);
        gsap.set(cursor, { opacity: 1 });

        gsap.to(cursor, {
            left: targetX,
            top: targetY,
            duration: 0.18,
            ease: "power1.out",
            onComplete: () => {
                // Bắn 1-2 viên đạn liên tiếp
                shootAt(canvasEl, targetX, targetY);

                if (Math.random() < 0.5) {
                    setTimeout(() => {
                        shootAt(canvasEl, targetX + (Math.random() * 30 - 15), targetY + (Math.random() * 30 - 15));
                        botBusy = false;
                        setTimeout(runFishShooterBot, 250 + Math.random() * 250);
                    }, 180);
                } else {
                    botBusy = false;
                    setTimeout(runFishShooterBot, 250 + Math.random() * 300);
                }
            }
        });
    }

    function shootAt(canvasEl, clientX, clientY) {
        BotVirtualCursor.simulateClick();
        const event = new MouseEvent('mousedown', {
            bubbles: true,
            cancelable: true,
            view: window,
            clientX: clientX,
            clientY: clientY
        });
        canvasEl.dispatchEvent(event);
    }

    // Khởi động bot săn cá
    $(document).ready(function() {
        setTimeout(runFishShooterBot, 1000);
    });
}
