/**
 * Scratch Card Tutorial - Animated GSAP walkthrough
 */
(function () {
    if (typeof gsap === 'undefined') return;

    const STEPS = [
        {
            title: '🎫 SCRATCH CARD LÀ GÌ?',
            desc: 'Bạn mua một tờ vé số gồm <b>9 ô</b>. Cào lộ <b>3 biểu tượng giống nhau</b> là THẮNG! Giống hệt cào vé số ngoài đời thực!',
            demo: null
        },
        {
            title: '💰 BƯỚC 1: CHỌN MỨC MUA VÉ',
            desc: 'Nhập số gtlm muốn dùng để mua vé. Mua xong thì <b>cào luôn</b> — không cần đợi!',
            demo: 'bet'
        },
        {
            title: '🎫 BƯỚC 2: NHẤN MUA VÉ',
            desc: 'Nhấn <b>🎫 Mua Vé</b>. Hệ thống sẽ tạo ngẫu nhiên 9 ô biểu tượng cho tờ vé của bạn.',
            demo: 'buy'
        },
        {
            title: '✋ BƯỚC 3: CÀO TỪNG Ô',
            desc: 'Nhấp vào <b>từng ô</b> để cào lộ biểu tượng bên dưới. Cào hết 9 ô để xem kết quả!',
            demo: 'scratch'
        },
        {
            title: '🏆 ĐIỀU KIỆN THẮNG',
            desc: 'Có <b>3 ô giống nhau</b> → THẮNG! Ví dụ: 3 ô 💎 → thưởng x50 số Gtlm mua vé.<br><br>Cần <b>chính xác 3 ô trùng nhau</b>, không cần cùng hàng.',
            demo: 'win'
        },
        {
            title: '💎 BẢNG THƯỞNG',
            desc: '🍒 Cherry = <b>×2</b> · 🍋 Lemon = <b>×5</b> · 🔔 Bell = <b>×10</b><br>⭐ Star = <b>×20</b> · 💎 Diamond = <b>×50</b> · 🎰 Jackpot = <b>×100</b><br><br>Mua vé 5,000 + Jackpot → nhận <b style="color:#f1c40f">500,000 gtlm</b>!',
            demo: 'paytable'
        },
        {
            title: '🍀 MÙA VÉ MỚI, MAY MẮN MỚI!',
            desc: 'Xác suất trúng khoảng <b>35%</b>. Mỗi lần mua là một tấm vé độc lập — vận may có thể đến bất cứ lúc nào!<br><br>Nhấn <b>🎫 Mua Vé Tiếp</b> để chơi ván mới. Chúc bạn may mắn! 🌟',
            demo: null
        }
    ];

    let step = 0, overlay = null;

    function buildUI() {
        if (document.getElementById('scTutOverlay')) return;
        overlay = document.createElement('div');
        overlay.id = 'scTutOverlay';
        overlay.innerHTML = `
        <style>
            #scTutOverlay{position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.82);display:flex;align-items:center;justify-content:center;font-family:'Inter',sans-serif;}
            .sc-box{background:linear-gradient(135deg,rgba(196,113,237,0.12),rgba(26,26,46,0.95));border:1.5px solid rgba(196,113,237,0.4);border-radius:2rem;padding:2.5rem 3rem;max-width:560px;width:90%;backdrop-filter:blur(20px);box-shadow:0 30px 80px rgba(0,0,0,0.7);position:relative;}
            .sc-badge{position:absolute;top:-18px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#c471ed,#12c2e9);padding:6px 22px;border-radius:999px;font-size:0.78rem;font-weight:700;color:#fff;letter-spacing:1px;}
            .sc-title{font-size:1.7rem;font-weight:900;color:#c471ed;margin:0.8rem 0 1rem;text-align:center;font-family:'Orbitron',sans-serif;text-shadow:0 0 20px rgba(196,113,237,0.5);}
            .sc-desc{font-size:1.05rem;color:#e0e0e0;line-height:1.7;text-align:center;min-height:80px;margin-bottom:1.5rem;}
            .sc-demo{height:80px;display:flex;align-items:center;justify-content:center;margin-bottom:1.5rem;}
            .sc-nav{display:flex;align-items:center;justify-content:space-between;gap:1rem;}
            .sc-btn{padding:0.8rem 1.8rem;border-radius:999px;border:none;font-weight:700;font-size:0.95rem;cursor:pointer;transition:0.2s;}
            .sc-prev{background:rgba(255,255,255,0.1);color:#fff;}
            .sc-prev:hover{background:rgba(255,255,255,0.2);}
            .sc-next{background:linear-gradient(135deg,#c471ed,#12c2e9);color:#fff;flex:1;}
            .sc-next:hover{filter:brightness(1.15);transform:translateY(-1px);}
            .sc-skip{position:absolute;top:1rem;right:1rem;background:none;border:none;color:rgba(255,255,255,0.4);font-size:0.8rem;cursor:pointer;padding:4px 10px;border-radius:999px;transition:0.2s;}
            .sc-skip:hover{color:#fff;background:rgba(255,255,255,0.1);}
            .sc-dots{display:flex;gap:6px;align-items:center;}
            .sc-dot{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,0.25);transition:0.3s;}
            .sc-dot.active{background:#c471ed;transform:scale(1.4);}
        </style>
        <div class="sc-box">
            <button class="sc-skip" onclick="scTutClose()">✕ Bỏ qua</button>
            <div class="sc-badge" id="scStepBadge">BƯỚC 1 / ${STEPS.length}</div>
            <div class="sc-title" id="scTitle"></div>
            <div class="sc-desc" id="scDesc"></div>
            <div class="sc-demo" id="scDemo"></div>
            <div class="sc-nav">
                <button class="sc-btn sc-prev" id="scPrev" onclick="scTutPrev()">← Trước</button>
                <div class="sc-dots" id="scDots"></div>
                <button class="sc-btn sc-next" id="scNext" onclick="scTutNext()">Tiếp theo →</button>
            </div>
        </div>`;
        document.body.appendChild(overlay);
        gsap.from(overlay.querySelector('.sc-box'), { y: 60, opacity: 0, duration: 0.5, ease: 'back.out(1.4)' });
        document.addEventListener('keydown', function esc(e) { if (e.key === 'Escape') { scTutClose(); document.removeEventListener('keydown', esc); } });
        render();
    }

    function render() {
        const s = STEPS[step];
        document.getElementById('scStepBadge').textContent = `BƯỚC ${step + 1} / ${STEPS.length}`;
        document.getElementById('scTitle').innerHTML = s.title;
        document.getElementById('scDesc').innerHTML = s.desc;
        document.getElementById('scNext').textContent = step === STEPS.length - 1 ? '🎫 Cào vé thôi!' : 'Tiếp theo →';
        document.getElementById('scPrev').style.visibility = step === 0 ? 'hidden' : 'visible';
        const dotsEl = document.getElementById('scDots');
        dotsEl.innerHTML = '';
        STEPS.forEach((_, i) => { const d = document.createElement('div'); d.className = 'sc-dot' + (i === step ? ' active' : ''); dotsEl.appendChild(d); });
        const demo = document.getElementById('scDemo');
        demo.innerHTML = '';
        buildDemo(s.demo, demo);
        gsap.from('#scTitle', { y: -12, opacity: 0, duration: 0.3 });
        gsap.from('#scDesc', { y: 8, opacity: 0, duration: 0.3, delay: 0.1 });
    }

    function buildDemo(type, el) {
        if (!type) return;
        if (type === 'bet') {
            el.innerHTML = '<div style="background:rgba(196,113,237,0.15);border:2px solid #c471ed;border-radius:12px;padding:12px 28px;color:#c471ed;font-weight:900;font-size:1.3rem;animation:scp 1s infinite alternate">🎫 5,000 gtlm / vé</div><style>@keyframes scp{from{box-shadow:0 0 8px #c471ed}to{box-shadow:0 0 25px #c471ed}}</style>';
        } else if (type === 'buy') {
            el.innerHTML = '<button style="background:linear-gradient(135deg,#c471ed,#12c2e9);color:#fff;border:none;padding:14px 30px;border-radius:14px;font-size:1.1rem;font-weight:900;animation:scp2 1s infinite alternate;cursor:default">🎫 Mua Vé</button><style>@keyframes scp2{from{transform:scale(1)}to{transform:scale(1.05);box-shadow:0 0 20px rgba(196,113,237,0.5)}}</style>';
        } else if (type === 'scratch') {
            const tiles = ['🟣', '🟣', '🟣', '🟣', '🟣', '?', '?', '?', '?'];
            el.innerHTML = '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:5px;width:160px">' +
                tiles.map((t, i) => `<div style="aspect-ratio:1;background:${t === '?' ? 'linear-gradient(135deg,#8e44ad,#c0392b)' : 'rgba(255,255,255,0.07)'};border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:${t === '?' ? '0.6rem' : '1.2rem'};color:${t === '?' ? '#fff' : '#e0e0e0'};border:1px solid rgba(255,255,255,0.1)">${t === '?' ? '✋' : t}</div>`).join('') +
                '</div>';
        } else if (type === 'win') {
            const syms = ['💎', '🍋', '💎', '🍒', '💎', '⭐', '🔔', '🍒', '🍒'];
            el.innerHTML = '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:4px;width:140px">' +
                syms.map((s, i) => `<div style="aspect-ratio:1;background:${s === '💎' ? 'rgba(196,113,237,0.3)' : 'rgba(255,255,255,0.05)'};border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;border:1px solid ${s === '💎' ? 'rgba(196,113,237,0.6)' : 'rgba(255,255,255,0.08)'};${s === '💎' ? 'animation:scp2 0.5s infinite alternate;box-shadow:0 0 10px rgba(196,113,237,0.5)' : ''}">${s}</div>`).join('') +
                '</div><style>@keyframes scp2{from{transform:scale(1)}to{transform:scale(1.15)}}</style>';
        } else if (type === 'paytable') {
            el.innerHTML = '<div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center">' +
                [['🍒', 'x2'], ['🍋', 'x5'], ['🔔', 'x10'], ['⭐', 'x20'], ['💎', 'x50'], ['🎰', 'x100']].map(([s, m]) =>
                    `<div style="background:rgba(255,255,255,0.05);border-radius:8px;padding:5px 10px;text-align:center;border:1px solid rgba(255,255,255,0.1)"><div style="font-size:1.2rem">${s}</div><div style="font-size:0.7rem;color:#c471ed;font-weight:900">${m}</div></div>`
                ).join('') +
                '</div>';
        }
    }

    window.scTutNext = function () { if (step >= STEPS.length - 1) { scTutClose(); return; } step++; gsap.to(overlay.querySelector('.sc-box'), { x: -20, opacity: 0, duration: 0.18, onComplete: () => { render(); gsap.fromTo(overlay.querySelector('.sc-box'), { x: 20, opacity: 0 }, { x: 0, opacity: 1, duration: 0.25 }); } }); };
    window.scTutPrev = function () { if (step <= 0) return; step--; gsap.to(overlay.querySelector('.sc-box'), { x: 20, opacity: 0, duration: 0.18, onComplete: () => { render(); gsap.fromTo(overlay.querySelector('.sc-box'), { x: -20, opacity: 0 }, { x: 0, opacity: 1, duration: 0.25 }); } }); };
    window.scTutClose = function () { if (!overlay) return; gsap.to(overlay, { opacity: 0, duration: 0.3, onComplete: () => { overlay.remove(); overlay = null; } }); };
    window.scTutOpen = function () { step = 0; buildUI(); };

    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('btn-howto');
        if (btn) btn.addEventListener('click', function (e) { e.preventDefault(); scTutOpen(); });
    });
})();
