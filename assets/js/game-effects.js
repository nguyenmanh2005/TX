/**
 * Premium Game Effects Library v4.0 — Enhanced Edition
 * Advanced visual feedback with cinematic quality effects
 */

const GameEffects = {
    init: function () {
        this.createCanvas();
        this.injectStyles();
        this.particles = [];
        this.coins = [];
        this.sparks = [];
        this._paused = false;
        this._lastFrame = 0;
        document.addEventListener('visibilitychange', () => { this._paused = document.hidden; });
        this.animate();
    },

    createCanvas: function () {
        if (document.getElementById('effects-canvas')) return;
        const canvas = document.createElement('canvas');
        canvas.id = 'effects-canvas';
        canvas.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:9999;';
        document.body.appendChild(canvas);
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        const resize = () => { canvas.width = window.innerWidth; canvas.height = window.innerHeight; };
        window.addEventListener('resize', resize);
        resize();
    },

    injectStyles: function () {
        if (document.getElementById('game-effects-styles')) return;
        const style = document.createElement('style');
        style.id = 'game-effects-styles';
        style.textContent = `
            @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@800&display=swap');

            /* Floating text */
            .float-text {
                position: fixed;
                pointer-events: none;
                z-index: 10001;
                font-family: 'Outfit', 'Orbitron', sans-serif;
                font-weight: 800;
                font-size: 3.5rem;
                -webkit-text-stroke: 2px rgba(0,0,0,0.5);
                text-shadow: 0 0 30px currentColor, 0 4px 12px rgba(0,0,0,0.8);
                animation: premiumFloat 1.8s cubic-bezier(0.17, 0.89, 0.32, 1.49) forwards;
                white-space: nowrap;
            }
            @keyframes premiumFloat {
                0%   { opacity:0; transform: translateY(20px) scale(0.3) rotate(-8deg); }
                20%  { opacity:1; transform: translateY(-30px) scale(1.3) rotate(4deg); }
                60%  { opacity:1; transform: translateY(-70px) scale(1.0); }
                100% { opacity:0; transform: translateY(-140px) scale(0.8); }
            }

            /* Balance bump */
            .balance-bump { animation: balanceBump 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
            @keyframes balanceBump {
                0%   { transform: scale(1); }
                35%  { transform: scale(1.4); filter: brightness(2) drop-shadow(0 0 10px #f1c40f); }
                100% { transform: scale(1); filter: brightness(1); }
            }

            /* Screen flashes */
            .screen-flash-win, .screen-flash-lose, .screen-flash-big {
                position: fixed; inset: 0; pointer-events: none; z-index: 9998;
                animation: flashFade 1.1s ease-out forwards;
            }
            .screen-flash-win  { background: radial-gradient(circle at center, rgba(46,204,113,0.5) 0%, transparent 65%); }
            .screen-flash-lose { background: radial-gradient(circle at center, rgba(231,76,60,0.5) 0%, transparent 65%); }
            .screen-flash-big  { background: radial-gradient(circle at center, rgba(241,196,15,0.55) 0%, transparent 55%); }
            @keyframes flashFade {
                0%   { opacity:0; }
                35%  { opacity:1; }
                100% { opacity:0; }
            }

            /* Vignette pulse on big win */
            .vignette-pulse {
                position: fixed; inset: 0; pointer-events: none; z-index: 9997;
                background: radial-gradient(ellipse at center, transparent 40%, rgba(241,196,15,0.25) 100%);
                animation: vignettePulse 0.6s ease-out 3 alternate;
            }
            @keyframes vignettePulse {
                0%   { opacity: 0; }
                100% { opacity: 1; }
            }

            /* Big Win Banner — cinematic */
            .big-win-banner {
                position: fixed; inset: 0; z-index: 10002;
                display: flex; flex-direction: column;
                align-items: center; justify-content: center;
                pointer-events: none;
                background: radial-gradient(ellipse at center, rgba(241,196,15,0.12) 0%, transparent 65%);
                animation: bannerFade 3.5s ease forwards;
            }
            .bw-stars {
                font-size: 2.5rem;
                margin-bottom: 0.5rem;
                animation: starsAppear 0.4s ease forwards;
                opacity: 0;
            }
            .big-win-banner .bw-title {
                font-family: 'Orbitron', 'Outfit', sans-serif;
                font-size: clamp(3rem, 9vw, 7rem);
                font-weight: 900;
                color: #f1c40f;
                text-shadow: 0 0 80px #f39c12, 0 0 30px #fff, 0 0 120px rgba(241,196,15,0.5);
                -webkit-text-stroke: 3px rgba(180,100,0,0.5);
                animation: titlePop 0.7s cubic-bezier(0.175,0.885,0.32,1.6) 0.1s both;
                transform: scale(0);
                letter-spacing: 4px;
            }
            .big-win-banner .bw-subtitle {
                font-family: 'Outfit', sans-serif;
                font-size: clamp(1rem, 3vw, 1.8rem);
                font-weight: 800;
                color: rgba(255,255,255,0.8);
                letter-spacing: 8px;
                text-transform: uppercase;
                margin-top: 0.3rem;
                animation: titlePop 0.5s 0.4s both;
                transform: scale(0);
            }
            .big-win-banner .bw-amount {
                font-family: 'Orbitron', 'Outfit', sans-serif;
                font-size: clamp(1.8rem, 5.5vw, 4rem);
                font-weight: 900;
                color: #fff;
                text-shadow: 0 0 40px rgba(255,255,255,0.9), 0 0 80px rgba(46,204,113,0.5);
                margin-top: 0.8rem;
                animation: titlePop 0.6s 0.3s cubic-bezier(0.175,0.885,0.32,1.6) both;
                transform: scale(0);
            }
            .bw-bar {
                width: 180px; height: 3px;
                background: linear-gradient(90deg, transparent, #f1c40f, transparent);
                margin: 1rem auto 0;
                animation: barExpand 0.6s 0.5s ease both;
                transform: scaleX(0);
            }
            @keyframes titlePop {
                0%   { transform: scale(0) translateY(20px); }
                70%  { transform: scale(1.08) translateY(-3px); }
                100% { transform: scale(1) translateY(0); }
            }
            @keyframes starsAppear {
                0%   { opacity: 0; transform: scale(0); }
                100% { opacity: 1; transform: scale(1); }
            }
            @keyframes barExpand {
                0%   { transform: scaleX(0); opacity: 0; }
                100% { transform: scaleX(1); opacity: 1; }
            }
            @keyframes bannerFade {
                0%,55% { opacity:1; }
                100%   { opacity:0; }
            }

            /* Shockwave ring */
            .shockwave {
                position: fixed; border-radius: 50%; pointer-events: none;
                border: 3px solid rgba(255,71,87,0.9);
                z-index: 10000;
                animation: shockExpand 0.85s cubic-bezier(0.17,0.67,0.38,1) forwards;
            }
            .shockwave-blue { border-color: rgba(18,194,233,0.9); }
            .shockwave-gold { border-color: rgba(241,196,15,0.9); }
            @keyframes shockExpand {
                0%   { transform: translate(-50%,-50%) scale(0); opacity: 1; }
                100% { transform: translate(-50%,-50%) scale(10); opacity: 0; }
            }

            /* Multiplier glow pulse */
            @keyframes multGlowPulse {
                0%,100% { filter: brightness(1) drop-shadow(0 0 8px currentColor); }
                50%      { filter: brightness(1.5) drop-shadow(0 0 35px currentColor); }
            }
            .mult-pulsing { animation: multGlowPulse 0.45s ease infinite; }

            /* Balance bump */
            @keyframes balanceBump {
                0%   { transform: scale(1); }
                30%  { transform: scale(1.35); filter: brightness(1.8) drop-shadow(0 0 8px #f1c40f); }
                100% { transform: scale(1); }
            }
            .balance-bump { animation: balanceBump 0.55s cubic-bezier(0.175, 0.885, 0.32, 1.275); }

            /* Tower effects */
            @keyframes safeGlow {
                0%,100% { box-shadow: 0 0 15px #2ecc71, inset 0 0 10px rgba(46,204,113,0.3); }
                50%      { box-shadow: 0 0 45px #2ecc71, inset 0 0 25px rgba(46,204,113,0.6); }
            }
            .tile-safe-anim { animation: safeGlow 0.6s ease 3; }

            @keyframes trapShake {
                0%,100% { transform: translateX(0) scale(1); }
                15%  { transform: translateX(-8px) scale(1.05); }
                30%  { transform: translateX(8px) scale(1.05); }
                45%  { transform: translateX(-6px); }
                60%  { transform: translateX(6px); }
                75%  { transform: translateX(-3px); }
            }
            .tile-trap-anim { animation: trapShake 0.5s ease forwards; }

            /* Glitter spark */
            .glitter-spark {
                position: fixed; pointer-events: none; z-index: 10000;
                border-radius: 50%;
                animation: glitterFly var(--dur, 0.8s) ease-out forwards;
            }
            @keyframes glitterFly {
                0%   { opacity:1; transform: translate(0,0) scale(1); }
                100% { opacity:0; transform: translate(var(--tx,0px), var(--ty,-60px)) scale(0.2); }
            }

            /* Match highlight */
            @keyframes matchPulse {
                0%,100% { transform: scale(1); filter: brightness(1); }
                50%      { transform: scale(1.15); filter: brightness(1.8) drop-shadow(0 0 15px #f1c40f); }
            }
            .match-highlight { animation: matchPulse 0.4s ease infinite; }

            /* Limbo counter */
            @keyframes multGlowPulse {
                0%,100% { filter: brightness(1) drop-shadow(0 0 10px currentColor); }
                50%      { filter: brightness(1.4) drop-shadow(0 0 30px currentColor); }
            }
        `;
        document.head.appendChild(style);
    },

    // ── Helpers ───────────────────────────────────────────
    flash: function (type) {
        const el = document.createElement('div');
        el.className = `screen-flash-${type}`;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 1300);
    },

    shake: function (el) {
        el.animate([
            { transform: 'translate(0,0)' },
            { transform: 'translate(-7px,2px)' },
            { transform: 'translate(7px,-2px)' },
            { transform: 'translate(-5px,1px)' },
            { transform: 'translate(5px,-1px)' },
            { transform: 'translate(-3px,0)' },
            { transform: 'translate(0,0)' }
        ], { duration: 380 });
    },

    floatingText: function (text, x, y, color) {
        const el = document.createElement('div');
        el.className = 'float-text';
        el.textContent = text;
        el.style.left = x + 'px';
        el.style.top = y + 'px';
        el.style.color = color || '#f1c40f';
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 1900);
    },

    animateValue: function (el, start, end, duration) {
        if (!el) return;
        let t0 = null;
        const step = (ts) => {
            if (!t0) t0 = ts;
            const p = Math.min((ts - t0) / duration, 1);
            el.innerHTML = new Intl.NumberFormat('vi-VN').format(Math.floor(p * (end - start) + start)) + ' gtlm';
            if (p < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
        const c = el.closest('.balance-pill, .game-balance-enhanced') || el;
        c.classList.remove('balance-bump'); void c.offsetWidth; c.classList.add('balance-bump');
    },

    // ── Fireworks ─────────────────────────────────────────
    fireworks: function (x, y, count = 50) {
        const colors = ['#f1c40f','#e74c3c','#2ecc71','#3498db','#9b59b6','#fff','#ff6b81','#ffd32a','#00e5ff','#ff9f43'];
        for (let i = 0; i < count; i++) {
            const angle = (Math.PI * 2 / count) * i + (Math.random() - 0.5) * 0.5;
            const speed = Math.random() * 12 + 5;
            const col = colors[Math.floor(Math.random() * colors.length)];
            this.particles.push({
                x, y,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed - 2,
                radius: Math.random() * 4 + 1.5,
                color: col,
                alpha: 1,
                decay: Math.random() * 0.011 + 0.007,
                gravity: 0.22,
                glow: true
            });
            // Sparkle trails
            if (Math.random() > 0.6) {
                this.sparks.push({ x, y, angle, speed: speed * 0.6, color: col, progress: 0 });
            }
        }
    },

    // ── Coin Blast ────────────────────────────────────────
    coinBlast: function (x, y, count = 18) {
        const balanceEl = document.querySelector('.balance-pill,.game-balance-enhanced,.user-money,#userBalance,#userMoney');
        let tx = window.innerWidth / 2, ty = 50;
        if (balanceEl) {
            const r = balanceEl.getBoundingClientRect();
            tx = r.left + r.width / 2; ty = r.top + r.height / 2;
        }
        for (let i = 0; i < count; i++) {
            setTimeout(() => {
                this.coins.push({
                    x, y, targetX: tx, targetY: ty,
                    vx: (Math.random() - 0.5) * 24,
                    vy: (Math.random() - 0.5) * 24 - 14,
                    size: Math.random() * 9 + 10,
                    angle: Math.random() * Math.PI * 2,
                    rotationSpeed: (Math.random() - 0.5) * 0.28,
                    phase: 'explode', life: 0
                });
            }, i * (600 / count));
        }
    },

    // ── BIG WIN ───────────────────────────────────────────
    showBigWin: function (amount, x, y) {
        this.flash('big');
        setTimeout(() => this.flash('win'), 300);

        // Vignette pulse
        const vig = document.createElement('div');
        vig.className = 'vignette-pulse';
        document.body.appendChild(vig);
        setTimeout(() => vig.remove(), 4000);

        // Banner
        const banner = document.createElement('div');
        banner.className = 'big-win-banner';
        banner.innerHTML = `
            <div class="bw-stars" style="animation-delay:0s">★ ★ ★</div>
            <div class="bw-title">BIG WIN</div>
            <div class="bw-subtitle">Vegas Royale</div>
            <div class="bw-amount">+${new Intl.NumberFormat('vi-VN').format(amount)} gtlm</div>
            <div class="bw-bar"></div>
        `;
        document.body.appendChild(banner);
        setTimeout(() => banner.remove(), 3800);

        const cx = x || window.innerWidth / 2, cy = y || window.innerHeight / 2;
        // Multi-wave fireworks
        this.fireworks(cx, cy, 90);
        setTimeout(() => this.fireworks(cx - 220, cy + 60, 55), 300);
        setTimeout(() => this.fireworks(cx + 220, cy + 60, 55), 500);
        setTimeout(() => this.fireworks(cx, cy - 80, 40), 700);
        this.coinBlast(cx, cy, 45);

        // Shockwave rings
        this._shockwaveAt(cx, cy, '');
        setTimeout(() => this._shockwaveAt(cx, cy, 'shockwave-gold'), 200);
    },

    showWin: function (amount, x, y) {
        const cx = x || window.innerWidth / 2, cy = y || window.innerHeight / 2;
        this.flash('win');
        this.floatingText(`+${new Intl.NumberFormat('vi-VN').format(amount)}`, cx - 60, cy, '#2ecc71');
        this.fireworks(cx, cy, 45);
        this.coinBlast(cx, cy, 16);
    },

    showLoss: function (amount, x, y) {
        this.flash('lose');
        this.shake(document.body);
        if (amount) this.floatingText(`-${new Intl.NumberFormat('vi-VN').format(amount)}`, x || window.innerWidth / 2, y || window.innerHeight / 2, '#e74c3c');
    },

    // ── CRASH EXPLOSION ───────────────────────────────────
    crashExplosion: function (x, y) {
        // Multiple shockwave rings with different colors
        this._shockwaveAt(x, y, '');
        setTimeout(() => this._shockwaveAt(x, y, ''), 120);
        setTimeout(() => this._shockwaveAt(x, y, 'shockwave-blue'), 240);

        // Floating "CRASH" text
        const txt = document.createElement('div');
        txt.className = 'float-text';
        txt.textContent = '💥 CRASHED';
        txt.style.cssText = `left:${x - 120}px;top:${y - 30}px;color:#ff4757;font-size:2.8rem;`;
        document.body.appendChild(txt);
        setTimeout(() => txt.remove(), 1900);

        this.flash('lose');
        this.shake(document.body);
        this.fireworks(x, y, 90);

        // Hot debris particles
        const cols = ['#ff4757','#ff6b81','#ffa502','#fff','#ffdd59'];
        for (let i = 0; i < 50; i++) {
            const angle = Math.random() * Math.PI * 2;
            const spd = Math.random() * 16 + 4;
            this.particles.push({
                x, y,
                vx: Math.cos(angle) * spd,
                vy: Math.sin(angle) * spd - 4,
                radius: Math.random() * 6 + 2,
                color: cols[Math.floor(Math.random() * cols.length)],
                alpha: 1,
                decay: Math.random() * 0.016 + 0.008,
                gravity: 0.35,
                glow: true
            });
        }
    },

    _shockwaveAt: function(x, y, extraClass) {
        const sw = document.createElement('div');
        sw.className = `shockwave ${extraClass}`;
        sw.style.cssText = `left:${x}px;top:${y}px;width:60px;height:60px;`;
        document.body.appendChild(sw);
        setTimeout(() => sw.remove(), 950);
    },

    // ── PLINKO TRAIL ──────────────────────────────────────
    plinkoTrail: function (x, y, color) {
        for (let i = 0; i < 8; i++) {
            this.particles.push({
                x: x + (Math.random() - 0.5) * 10,
                y: y + (Math.random() - 0.5) * 10,
                vx: (Math.random() - 0.5) * 3.5,
                vy: (Math.random() - 0.5) * 3.5,
                radius: Math.random() * 4 + 2,
                color: color || '#12c2e9',
                alpha: 0.8,
                decay: 0.04,
                gravity: 0,
                glow: true
            });
        }
    },

    // ── GLITTER REVEAL ────────────────────────────────────
    glitterReveal: function (el) {
        if (!el) return;
        const rect = el.getBoundingClientRect();
        const cx = rect.left + rect.width / 2, cy = rect.top + rect.height / 2;
        const colors = ['#f1c40f','#fff','#c471ed','#12c2e9','#ff6b81','#2ecc71'];
        for (let i = 0; i < 22; i++) {
            const spark = document.createElement('div');
            const size = Math.random() * 9 + 4;
            const angle = Math.random() * Math.PI * 2;
            const dist = Math.random() * 80 + 30;
            const dur = (Math.random() * 0.45 + 0.5).toFixed(2);
            spark.className = 'glitter-spark';
            spark.style.cssText = `width:${size}px;height:${size}px;background:${colors[Math.floor(Math.random()*colors.length)]};left:${cx}px;top:${cy}px;--tx:${(Math.cos(angle)*dist).toFixed(0)}px;--ty:${(Math.sin(angle)*dist-45).toFixed(0)}px;--dur:${dur}s;box-shadow:0 0 6px currentColor;`;
            document.body.appendChild(spark);
            setTimeout(() => spark.remove(), parseFloat(dur)*1000+100);
        }
    },

    // ── TOWER SAFE ────────────────────────────────────────
    towerSafe: function (el) {
        if (!el) return;
        el.classList.add('tile-safe-anim');
        setTimeout(() => el.classList.remove('tile-safe-anim'), 1800);
        const rect = el.getBoundingClientRect();
        const cx = rect.left + rect.width/2, cy = rect.top + rect.height/2;
        this.coinBlast(cx, cy, 8);
        for (let i = 0; i < 14; i++) {
            this.particles.push({ x:cx, y:cy, vx:(Math.random()-0.5)*13, vy:(Math.random()-0.5)*13-5, radius:Math.random()*4+2, color:'#2ecc71', alpha:1, decay:0.025, gravity:0.2, glow:true });
        }
    },

    // ── TOWER BOOM ────────────────────────────────────────
    towerBoom: function (el) {
        if (!el) return;
        el.classList.add('tile-trap-anim');
        const rect = el.getBoundingClientRect();
        const x = rect.left + rect.width/2, y = rect.top + rect.height/2;
        this._shockwaveAt(x, y, '');
        this.flash('lose');
        this.shake(document.body);
        this.fireworks(x, y, 35);
        for (let i = 0; i < 22; i++) {
            this.particles.push({ x, y, vx:(Math.random()-0.5)*17, vy:(Math.random()-0.5)*17-3, radius:Math.random()*5+2, color:['#ff4757','#ff6b81','#ffa502'][Math.floor(Math.random()*3)], alpha:1, decay:0.02, gravity:0.3, glow:true });
        }
    },

    // ── LIMBO COUNTER ─────────────────────────────────────
    limboCount: function (el, start, end, win, duration) {
        if (!el) return;
        duration = duration || Math.min(2000, Math.max(600, end * 80));
        let t0 = null;
        const step = (ts) => {
            if (!t0) t0 = ts;
            const p = Math.min((ts - t0) / duration, 1);
            const eased = p < 0.5 ? 2*p*p : -1+(4-2*p)*p;
            const cur = start + eased * (end - start);
            el.textContent = cur.toFixed(2) + 'x';
            const hue = p < 0.7 ? (60 - p*60) : (win ? 120 : 0);
            el.style.color = `hsl(${hue}, 100%, 65%)`;
            if (p < 1) requestAnimationFrame(step);
            else {
                el.style.color = win ? '#2ecc71' : '#ff4757';
                if (win) { el.classList.add('mult-pulsing'); setTimeout(() => el.classList.remove('mult-pulsing'), 1500); }
            }
        };
        requestAnimationFrame(step);
    },

    // ── WIN CHANCE RING ───────────────────────────────────
    drawWinChanceRing: function (svgEl, chance) {
        if (!svgEl) return;
        const r = 48, circ = 2 * Math.PI * r;
        const fill = circ * Math.max(0, Math.min(1, chance / 100));
        const circle = svgEl.querySelector('circle.ring-fill');
        if (circle) {
            circle.style.strokeDasharray = `${fill} ${circ}`;
            const hue = Math.round(chance * 1.2);
            circle.style.stroke = `hsl(${hue}, 100%, 55%)`;
        }
    },

    // ── ANIMATION LOOP ────────────────────────────────────
    animate: function (ts) {
        requestAnimationFrame((t) => this.animate(t));
        if (this._paused) return;
        if (this.particles.length === 0 && this.coins.length === 0 && this.sparks.length === 0) return;
        if (ts && ts - this._lastFrame < 14) return;
        this._lastFrame = ts || 0;
        if (!this.ctx) return;

        const ctx = this.ctx;
        ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        // Particles with optional glow
        for (let i = this.particles.length - 1; i >= 0; i--) {
            const p = this.particles[i];
            p.x += p.vx; p.y += p.vy;
            p.vy += p.gravity || 0.12;
            p.vx *= 0.985;
            p.alpha -= p.decay;
            if (p.alpha <= 0) { this.particles.splice(i, 1); continue; }
            ctx.globalAlpha = p.alpha;
            if (p.glow) {
                ctx.shadowBlur = 10;
                ctx.shadowColor = p.color;
            }
            ctx.fillStyle = p.color;
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
            ctx.fill();
            if (p.glow) ctx.shadowBlur = 0;
        }

        // Sparks (streak trails)
        for (let i = this.sparks.length - 1; i >= 0; i--) {
            const s = this.sparks[i];
            s.progress += 0.06;
            if (s.progress >= 1) { this.sparks.splice(i, 1); continue; }
            const x2 = s.x + Math.cos(s.angle) * s.speed * s.progress * 8;
            const y2 = s.y + Math.sin(s.angle) * s.speed * s.progress * 8;
            ctx.globalAlpha = (1 - s.progress) * 0.6;
            ctx.strokeStyle = s.color;
            ctx.lineWidth = 1.5;
            ctx.shadowBlur = 5;
            ctx.shadowColor = s.color;
            ctx.beginPath();
            ctx.moveTo(s.x, s.y);
            ctx.lineTo(x2, y2);
            ctx.stroke();
            ctx.shadowBlur = 0;
        }

        // Coins
        for (let i = this.coins.length - 1; i >= 0; i--) {
            const c = this.coins[i];
            c.life++;
            if (c.phase === 'explode') {
                c.x += c.vx; c.y += c.vy;
                c.vx *= 0.93; c.vy *= 0.93; c.vy += 0.55;
                if (c.life > 22) c.phase = 'target';
            } else {
                const dx = c.targetX - c.x, dy = c.targetY - c.y;
                if (Math.sqrt(dx*dx+dy*dy) < 16) { this.coins.splice(i, 1); continue; }
                c.x += dx * 0.2; c.y += dy * 0.2;
            }
            c.angle += c.rotationSpeed;
            ctx.globalAlpha = 1;
            ctx.save();
            ctx.translate(c.x, c.y);
            ctx.rotate(c.angle);
            const grd = ctx.createRadialGradient(-c.size*0.3, -c.size*0.3, 0, 0, 0, c.size);
            grd.addColorStop(0, '#fff59d');
            grd.addColorStop(0.5, '#f1c40f');
            grd.addColorStop(1, '#c7a006');
            ctx.fillStyle = grd;
            ctx.shadowBlur = 14;
            ctx.shadowColor = '#f1c40f';
            ctx.beginPath();
            ctx.ellipse(0, 0, c.size, c.size * Math.max(0.15, Math.abs(Math.sin(c.angle))), 0, 0, Math.PI*2);
            ctx.fill();
            ctx.strokeStyle = '#b7950b';
            ctx.lineWidth = 1.5;
            ctx.stroke();
            ctx.shadowBlur = 0;
            ctx.restore();
        }

        ctx.globalAlpha = 1;
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => GameEffects.init());
} else {
    GameEffects.init();
}
window.GameEffects = GameEffects;