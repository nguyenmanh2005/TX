/**
 * Plinko Royale V3 JS Engine
 * [NEW FILE] - Physics Canvas 60 FPS, Multi-Drop Stream & AI Bot Race
 * Tuân thủ Rule 2.1 & không đè file cũ
 */

const PlinkoRoyaleV3 = (() => {
    let canvas, ctx;
    let config = {};
    let currentRows = 16;
    let currentRisk = 'high';
    let currentBallCount = 10;
    let currentBet = 10000;
    let isDropping = false;
    let activeBalls = [];
    let pegs = [];
    let slots = [];
    let animId = null;
    // Session tracking
    let sessionTotalBet = 0;
    let sessionTotalWin = 0;
    let lastToastThrottle = {};

    // 1. Initialize
    async function init() {
        canvas = document.getElementById('plinkoCanvas');
        if (!canvas) return;
        ctx = canvas.getContext('2d');
        canvas.width = 800;
        canvas.height = 660;

        await loadConfig();
        setupEventListeners();
        buildStage();
        startPhysicsLoop();
    }

    async function loadConfig() {
        try {
            const res = await fetch('api_plinko_royale_v3.php?action=config');
            const data = await res.json();
            if (data.success) {
                config = data.config;
                if (document.getElementById('plinkoBalance')) {
                    document.getElementById('plinkoBalance').textContent = new Intl.NumberFormat().format(data.balance) + ' GTLM';
                }
                renderMultiplierSlots();
            }
        } catch (e) {
            console.error('Lỗi tải config Plinko Royale:', e);
        }
    }

    function setupEventListeners() {
        // Rows select
        document.querySelectorAll('.btn-row-select').forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (isDropping) return;
                document.querySelectorAll('.btn-row-select').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentRows = parseInt(btn.dataset.rows);
                buildStage();
                renderMultiplierSlots();
            });
        });

        // Risk select
        document.querySelectorAll('.btn-risk-select').forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (isDropping) return;
                document.querySelectorAll('.btn-risk-select').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentRisk = btn.dataset.risk;
                renderMultiplierSlots();
            });
        });

        // Ball count select
        document.querySelectorAll('.btn-ball-select').forEach(btn => {
            btn.addEventListener('click', (e) => {
                if (isDropping) return;
                document.querySelectorAll('.btn-ball-select').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentBallCount = parseInt(btn.dataset.count);
                updateTotalBetDisplay();
            });
        });

        // Bet input
        const betInput = document.getElementById('betInput');
        if (betInput) {
            betInput.addEventListener('input', () => {
                currentBet = parseInt(betInput.value) || 1000;
                updateTotalBetDisplay();
            });
        }
    }

    function setQuickBet(mult) {
        if (isDropping) return;
        const betInput = document.getElementById('betInput');
        if (!betInput) return;
        let val = parseInt(betInput.value) || 10000;
        if (mult === 0.5) val = Math.max(1000, Math.floor(val / 2));
        else if (mult === 2) val = val * 2;
        else if (mult === 10) val = val * 10;
        else if (mult === -1) {
            // Max bet limit
            val = 5000000;
        }
        currentBet = val;
        betInput.value = val;
        updateTotalBetDisplay();
    }

    function updateTotalBetDisplay() {
        const el = document.getElementById('totalBetDisplay');
        if (el) {
            el.textContent = new Intl.NumberFormat().format(currentBet) + ' GTLM';
        }
    }

    let slotBoxes = [];
    let slotHitTimer = [];
    let particles = [];
    let floatingTexts = [];

    // 2. Build Peg Stage & Canvas Slots
    function buildStage() {
        pegs = [];
        slotBoxes = [];
        const topY = 60;
        const bottomY = 560;
        const rowHeight = (bottomY - topY) / currentRows;
        const centerX = canvas.width / 2;

        for (let r = 0; r < currentRows; r++) {
            const pegsInRow = r + 3;
            const rowWidth = pegsInRow * 38;
            const startX = centerX - rowWidth / 2;
            const y = topY + r * rowHeight;

            for (let c = 0; c < pegsInRow; c++) {
                const x = startX + c * 38;
                pegs.push({ x, y, radius: 4, row: r, col: c });
            }
        }

        // Calculate exact bounding boxes for slots directly under bottom row of pegs
        if (config[currentRows] && config[currentRows][currentRisk]) {
            const mults = config[currentRows][currentRisk];
            const bottomPegsInRow = currentRows + 2;
            const bottomRowWidth = bottomPegsInRow * 38;
            const bottomStartX = centerX - bottomRowWidth / 2;
            const slotWidth = 35;

            for (let i = 0; i <= currentRows; i++) {
                const gapCenterX = bottomStartX + i * 38 + 19;
                slotBoxes.push({
                    idx: i,
                    mult: mults[i] || 1,
                    x: gapCenterX - slotWidth / 2,
                    y: bottomY + 14,
                    width: slotWidth,
                    height: 44,
                    centerX: gapCenterX
                });
            }
        }
    }

    function renderMultiplierSlots() {
        buildStage();
        const container = document.getElementById('multiplierSlotsContainer');
        if (container) {
            container.style.display = 'none'; // Slots are now rendered directly inside high-def Canvas!
        }
    }

    // 3. Physics Loop 60 FPS
    function startPhysicsLoop() {
        function loop() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            // Draw Pegs with Golden Glow
            pegs.forEach(p => {
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
                ctx.fillStyle = '#fbbf24';
                ctx.shadowColor = '#f59e0b';
                ctx.shadowBlur = 6;
                ctx.fill();
                ctx.shadowBlur = 0;
            });

            // Draw Multiplier Buckets directly on Canvas for Pixel-Perfect Alignment & Glow
            slotBoxes.forEach(sb => {
                const hitAlpha = slotHitTimer[sb.idx] || 0;
                if (slotHitTimer[sb.idx] > 0) slotHitTimer[sb.idx] -= 0.05;

                let bgGrad = ctx.createLinearGradient(sb.x, sb.y, sb.x, sb.y + sb.height);
                let textColor = '#fff';
                let borderColor = '#334155';

                if (sb.mult >= 100) {
                    bgGrad.addColorStop(0, hitAlpha > 0 ? '#fef08a' : '#ef4444');
                    bgGrad.addColorStop(1, '#7f1d1d');
                    textColor = '#fef08a';
                    borderColor = hitAlpha > 0 ? '#ffffff' : '#f59e0b';
                } else if (sb.mult >= 10) {
                    bgGrad.addColorStop(0, hitAlpha > 0 ? '#fef08a' : '#f59e0b');
                    bgGrad.addColorStop(1, '#92400e');
                    textColor = '#000';
                    borderColor = hitAlpha > 0 ? '#ffffff' : '#fbbf24';
                } else if (sb.mult >= 2) {
                    bgGrad.addColorStop(0, hitAlpha > 0 ? '#d8b4fe' : '#8b5cf6');
                    bgGrad.addColorStop(1, '#4c1d95');
                    textColor = '#fff';
                    borderColor = hitAlpha > 0 ? '#ffffff' : '#a855f7';
                } else {
                    bgGrad.addColorStop(0, hitAlpha > 0 ? '#64748b' : '#1e293b');
                    bgGrad.addColorStop(1, '#0f172a');
                    textColor = '#94a3b8';
                    borderColor = hitAlpha > 0 ? '#ffffff' : '#334155';
                }

                ctx.save();
                if (hitAlpha > 0) {
                    ctx.shadowColor = sb.mult >= 10 ? '#fbbf24' : '#38bdf8';
                    ctx.shadowBlur = 25;
                    ctx.translate(sb.centerX, sb.y + sb.height / 2);
                    ctx.scale(1 + hitAlpha * 0.18, 1 + hitAlpha * 0.18);
                    ctx.translate(-sb.centerX, -(sb.y + sb.height / 2));
                }

                ctx.beginPath();
                if (typeof ctx.roundRect === 'function') {
                    ctx.roundRect(sb.x, sb.y, sb.width, sb.height, 8);
                } else {
                    ctx.rect(sb.x, sb.y, sb.width, sb.height);
                }
                ctx.fillStyle = bgGrad;
                ctx.fill();
                ctx.lineWidth = hitAlpha > 0 ? 2.5 : 1.2;
                ctx.strokeStyle = borderColor;
                ctx.stroke();

                ctx.fillStyle = textColor;
                ctx.font = 'bold 12px Outfit, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(`${sb.mult}x`, sb.centerX, sb.y + sb.height / 2);
                ctx.restore();
            });

            // Update & Draw Balls
            for (let i = activeBalls.length - 1; i >= 0; i--) {
                const ball = activeBalls[i];
                updateBallPhysics(ball);
                drawBall(ball);

                if (ball.finished) {
                    activeBalls.splice(i, 1);
                }
            }

            // Update & Draw Particles (Hiệu ứng ăn GTLM nổ hạt)
            for (let i = particles.length - 1; i >= 0; i--) {
                const p = particles[i];
                p.x += p.vx;
                p.y += p.vy;
                p.vy += 0.22;
                p.alpha -= p.life;

                if (p.alpha <= 0) {
                    particles.splice(i, 1);
                    continue;
                }

                ctx.save();
                ctx.globalAlpha = p.alpha;
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
                ctx.fillStyle = p.color;
                ctx.shadowColor = p.color;
                ctx.shadowBlur = 8;
                ctx.fill();
                ctx.restore();
            }

            // Update & Draw Floating Popups (+GTLM / +Mult)
            for (let i = floatingTexts.length - 1; i >= 0; i--) {
                const ft = floatingTexts[i];
                ft.y -= ft.vy;
                ft.alpha -= ft.life || 0.012;

                if (ft.alpha <= 0) {
                    floatingTexts.splice(i, 1);
                    continue;
                }

                ctx.save();
                ctx.globalAlpha = Math.min(1, ft.alpha);
                ctx.font = `900 ${ft.fontSize}px Outfit, sans-serif`;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                // Strong shadow for readability on dark canvas
                ctx.shadowColor = '#000';
                ctx.shadowBlur = 10;
                ctx.fillStyle = '#000';
                ctx.fillText(ft.text, ft.x + 1, ft.y + 1); // shadow pass
                ctx.shadowBlur = 0;
                ctx.fillStyle = ft.color;
                // Color glow
                ctx.shadowColor = ft.color;
                ctx.shadowBlur = 14;
                ctx.fillText(ft.text, ft.x, ft.y);
                ctx.restore();
            }

            if (activeBalls.length === 0 && isDropping) {
                isDropping = false;
                const btn = document.getElementById('btnDropMain');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '💥 THẢ BÓNG ROYALE NGAY';
                }
            }

            animId = requestAnimationFrame(loop);
        }
        loop();
    }

    function drawBall(ball) {
        ctx.beginPath();
        ctx.arc(ball.x, ball.y, ball.radius, 0, Math.PI * 2);

        const grad = ctx.createRadialGradient(ball.x - 2, ball.y - 2, 1, ball.x, ball.y, ball.radius);
        if (ball.isJackpot) {
            grad.addColorStop(0, '#ffffff');
            grad.addColorStop(0.5, '#ef4444');
            grad.addColorStop(1, '#991b1b');
            ctx.shadowColor = '#ef4444';
            ctx.shadowBlur = 14;
        } else {
            grad.addColorStop(0, '#ffffff');
            grad.addColorStop(0.5, '#38bdf8');
            grad.addColorStop(1, '#0284c7');
            ctx.shadowColor = '#38bdf8';
            ctx.shadowBlur = 8;
        }

        ctx.fillStyle = grad;
        ctx.fill();
        ctx.shadowBlur = 0;
    }

    function updateBallPhysics(ball) {
        const topY = 60;
        const bottomY = 560;
        const rowHeight = (bottomY - topY) / currentRows;

        if (ball.currentRow < currentRows) {
            const targetRowY = topY + ball.currentRow * rowHeight;

            ball.vy += 0.45; // Gravity
            ball.y += ball.vy;

            // Keep X aligned exactly with physical peg paths (no horizontal drift!)
            const expectedTargetX = (canvas.width / 2) + (ball.currentSlot * 2 - ball.currentRow) * 19;
            ball.x += (expectedTargetX - ball.x) * 0.28;

            if (ball.y >= targetRowY) {
                const dir = ball.path[ball.currentRow];
                ball.currentRow++;
                if (dir === 1) {
                    ball.currentSlot++;
                }
                ball.vy = -ball.vy * 0.25;

                if (dir === 0) {
                    ball.vx = -1.4;
                } else {
                    ball.vx = 1.4;
                }

                if (typeof SoundFXHub !== 'undefined') {
                    SoundFXHub.playPop();
                }
            }
        } else {
            // Final vertical drop directly straight into the target bucket center
            const targetBox = slotBoxes[ball.destinationSlot];
            const targetX = targetBox ? targetBox.centerX : (canvas.width / 2);

            ball.vy += 0.55;
            ball.y += ball.vy;
            ball.x += (targetX - ball.x) * 0.35; // Lock to bucket center

            if (ball.y >= bottomY + 20) {
                ball.finished = true;
                handleBallLand(ball);
            }
        }
    }

    function handleBallLand(ball) {
        slotHitTimer[ball.destinationSlot] = 1.0;

        const targetBox = slotBoxes[ball.destinationSlot] || { centerX: canvas.width / 2, y: 570 };
        const mult = ball.multiplier;
        const win = ball.winAmount;

        // 1. Spawn Particle Explosion — bắn LÊN TRÊN (angle ~-π/2)
        const numParticles = mult >= 10 ? 45 : (mult >= 2 ? 28 : 16);
        const pColors = mult >= 100 ? ['#fde047', '#ef4444', '#ffffff', '#fbbf24', '#f97316'] :
            (mult >= 10 ? ['#fbbf24', '#f59e0b', '#ffffff', '#fde047'] : ['#38bdf8', '#a855f7', '#ffffff', '#818cf8']);

        for (let p = 0; p < numParticles; p++) {
            // Góc bắn: từ -π (thẳng trái) đến 0 (thẳng phải), trung tâm là -π/2 (thẳng lên)
            const angle = -Math.PI / 2 + (Math.random() - 0.5) * Math.PI * 1.1;
            const speed = Math.random() * 7 + 3;
            particles.push({
                x: targetBox.centerX + (Math.random() * 14 - 7),
                y: targetBox.y + 8,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                radius: Math.random() * 4 + 2,
                color: pColors[Math.floor(Math.random() * pColors.length)],
                alpha: 1.0,
                life: Math.random() * 0.025 + 0.018
            });
        }

        // 2. Floating Popup Text (to và rõ hơn)
        let popupText = `+${new Intl.NumberFormat('vi-VN').format(win)}`;
        if (mult >= 100) popupText = `💥 X${mult}!`;
        else if (mult >= 10) popupText = `🔥 X${mult}: +${new Intl.NumberFormat('vi-VN').format(win)}`;

        floatingTexts.push({
            x: targetBox.centerX,
            y: targetBox.y - 5,
            text: popupText,
            color: mult >= 100 ? '#fde047' : (mult >= 2 ? '#34d399' : '#94a3b8'),
            fontSize: mult >= 100 ? 24 : (mult >= 10 ? 18 : 14),
            alpha: 1.0,
            vy: mult >= 10 ? 2.2 : 1.5,
            life: 0.012
        });

        // 3. Update session counters
        sessionTotalWin += win;
        updateSessionUI();

        // 4. Show DOM Toast (throtled — không spam quá 5 toast/giây)
        const now = Date.now();
        if (!lastToastThrottle._last || now - lastToastThrottle._last > 200 || mult >= 2) {
            lastToastThrottle._last = now;
            showWinToast(mult, win);
        }

        // 5. Play sound & trigger royale celebration
        if (mult >= 100) {
            if (typeof SoundFXHub !== 'undefined') {
                SoundFXHub.playJackpot();
                SoundFXHub.playBossRoar();
            }
            triggerRoyaleCelebration(mult, win);
        } else if (mult >= 2) {
            if (typeof SoundFXHub !== 'undefined') SoundFXHub.playLotteryWin();
        } else {
            if (typeof SoundFXHub !== 'undefined') SoundFXHub.playPop();
        }
    }

    // ===== Session UI Helpers =====
    function updateSessionUI() {
        const betEl    = document.getElementById('sessionBetEl');
        const winEl    = document.getElementById('sessionWinEl');
        const profitEl = document.getElementById('sessionProfitEl');
        if (!betEl) return;

        const profit = sessionTotalWin - sessionTotalBet;
        betEl.textContent    = new Intl.NumberFormat('vi-VN').format(sessionTotalBet) + ' GTLM';
        winEl.textContent    = new Intl.NumberFormat('vi-VN').format(sessionTotalWin) + ' GTLM';
        profitEl.textContent = (profit >= 0 ? '+' : '') + new Intl.NumberFormat('vi-VN').format(profit) + ' GTLM';
        profitEl.style.color = profit > 0 ? '#34d399' : (profit < 0 ? '#ef4444' : '#94a3b8');

        // Flash animation
        profitEl.classList.remove('flash-win', 'flash-loss');
        void profitEl.offsetWidth; // reflow
        profitEl.classList.add(profit >= 0 ? 'flash-win' : 'flash-loss');
    }

    function flashBalance(isWin) {
        const el = document.getElementById('plinkoBalance');
        if (!el) return;
        el.classList.remove('balance-flash-up', 'balance-flash-down');
        void el.offsetWidth;
        el.classList.add(isWin ? 'balance-flash-up' : 'balance-flash-down');
    }

    // ===== Win Toast DOM Notification =====
    function showWinToast(mult, winAmount) {
        const container = document.getElementById('winToastContainer');
        if (!container) return;

        // Max 6 toasts visible at once
        while (container.children.length >= 6) {
            container.removeChild(container.firstChild);
        }

        const toast = document.createElement('div');
        let cls = 'win-toast';
        let icon = '🎱';
        let amtCls = 'neutral';

        if (mult >= 100) { cls += ' toast-jackpot'; icon = '💥'; amtCls = 'jackpot'; }
        else if (mult >= 10)  { cls += ' toast-big'; icon = '🔥'; amtCls = 'positive'; }
        else if (mult >= 2)   { icon = '✨'; amtCls = 'positive'; }
        else if (mult < 1)    { icon = '😶'; }

        toast.className = cls;
        toast.innerHTML = `
            <span class="toast-icon">${icon}</span>
            <div class="toast-body">
                <div class="toast-mult">X${mult} — ${currentRows} hàng / ${currentRisk}</div>
                <div class="toast-amount ${amtCls}">+${new Intl.NumberFormat('vi-VN').format(winAmount)} GTLM</div>
            </div>
        `;
        container.appendChild(toast);

        // Auto remove after 3.5s
        setTimeout(() => {
            toast.style.animation = 'toastOut 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }

    // 4. Trigger Drop API & Stream
    async function triggerDrop() {
        if (isDropping) return;
        const btn = document.getElementById('btnDropMain');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '⏳ ĐANG THẢ BÓNG ROYALE...';
        }
        isDropping = true;

        // Reset session counters for this drop
        sessionTotalBet = currentBet;
        sessionTotalWin = 0;
        updateSessionUI();

        const formData = new FormData();
        formData.append('bet', currentBet);
        formData.append('ballCount', currentBallCount);
        formData.append('rows', currentRows);
        formData.append('risk', currentRisk);

        try {
            const res = await fetch('api_plinko_royale_v3.php?action=drop', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (!data.success) {
                Swal.fire({ title: '⚠️ Lỗi Thả Bóng', text: data.message, icon: 'warning' });
                isDropping = false;
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '💥 THẢ BÓNG ROYALE NGAY';
                }
                return;
            }

            const balEl = document.getElementById('plinkoBalance');
            if (balEl) {
                const oldBalance = parseFloat(balEl.textContent.replace(/[^0-9]/g, '')) || 0;
                balEl.textContent = new Intl.NumberFormat('vi-VN').format(data.newBalance) + ' GTLM';
                flashBalance(data.newBalance >= oldBalance);
            }

            const topY = 40;
            const centerX = canvas.width / 2;

            data.results.forEach((r, idx) => {
                setTimeout(() => {
                    activeBalls.push({
                        x: centerX + (Math.random() * 8 - 4),
                        y: topY,
                        vx: 0,
                        vy: 2,
                        radius: 8,
                        path: r.path,
                        destinationSlot: r.slot,
                        multiplier: r.multiplier,
                        winAmount: r.winAmount,
                        currentRow: 0,
                        currentSlot: 0,
                        isJackpot: r.multiplier >= 100,
                        finished: false
                    });
                }, idx * (currentBallCount > 30 ? 40 : 80));
            });

        } catch (e) {
            console.error('Lỗi khi thả bóng:', e);
            isDropping = false;
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '💥 THẢ BÓNG ROYALE NGAY';
            }
        }
    }

    function triggerRoyaleCelebration(mult, win) {
        const overlay = document.getElementById('royaleCelebrationOverlay');
        const wrapper = document.getElementById('plinkoStageWrapper');
        if (wrapper) {
            wrapper.classList.add('shake');
            setTimeout(() => wrapper.classList.remove('shake'), 600);
        }

        if (overlay) {
            document.getElementById('celMultText').textContent = `X${mult} ROYALE JACKPOT!`;
            document.getElementById('celWinText').textContent = `+${new Intl.NumberFormat().format(win)} GTLM`;
            overlay.classList.add('active');

            setTimeout(() => {
                overlay.classList.remove('active');
            }, 4000);
        }
    }


    return { init, triggerDrop, setQuickBet };
})();

document.addEventListener('DOMContentLoaded', () => {
    PlinkoRoyaleV3.init();
});
