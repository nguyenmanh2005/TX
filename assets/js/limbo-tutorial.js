/**
 * Limbo Tutorial - Animated GSAP walkthrough
 */
(function () {
    if (typeof gsap === 'undefined') return;

    const STEPS = [
        {
            title: '🚀 LIMBO LÀ GÌ?',
            desc: 'Bạn đặt một <b>Multiplier mục tiêu</b>. Hệ thống sẽ tung ra một số ngẫu nhiên — nếu số đó <b>lớn hơn hoặc bằng mục tiêu</b> của bạn, bạn THẮNG!',
            demo: null
        },
        {
            title: '💰 BƯỚC 1: ĐẶT CƯỢC',
            desc: 'Nhập số gtlm muốn cược. Ví dụ: <b>10,000 gtlm</b>.',
            demo: 'bet'
        },
        {
            title: '🎯 BƯỚC 2: CHỌN MỤC TIÊU',
            desc: 'Nhập <b>Multiplier mục tiêu</b>. Mục tiêu càng cao → xác suất thắng càng thấp nhưng thưởng càng lớn!<br><br><b>x2.0</b> = 49.5% xác suất thắng | <b>x10.0</b> = 9.9% | <b>x100.0</b> = ~1%',
            demo: 'target'
        },
        {
            title: '🚀 BƯỚC 3: NHẤN BẮT ĐẦU',
            desc: 'Nhấn <b>🚀 Bắt đầu</b>. Tên lửa sẽ phóng lên và số nhân sẽ tăng dần...',
            demo: 'rocket'
        },
        {
            title: '✅ THẮNG: KẾT QUẢ ≥ MỤC TIÊU',
            desc: 'Nếu <b>kết quả ≥ mục tiêu của bạn</b>: cược 10,000 × mục tiêu x2.0 = <b style="color:#2ecc71">20,000 gtlm</b>!',
            demo: 'win'
        },
        {
            title: '❌ THUA: KẾT QUẢ < MỤC TIÊU',
            desc: 'Nếu kết quả nhỏ hơn mục tiêu, bạn mất số gtlm đã cược. Hãy quản lý rủi ro bằng cách không đặt mục tiêu quá cao!',
            demo: 'lose'
        },
        {
            title: '⚡ CHIẾN THUẬT PRO',
            desc: '<b>Mục tiêu thấp (x1.5-x2.0)</b>: xác suất cao, thắng nhỏ nhưng ổn định.<br><b>Mục tiêu cao (x10+)</b>: rủi ro lớn nhưng một lần thắng đủ bù nhiều lần thua.<br><br>Chúc bạn phóng rocket lên cao! 🚀',
            demo: null
        }
    ];

    let step = 0, overlay = null;

    function buildUI() {
        if (document.getElementById('liTutOverlay')) return;
        overlay = document.createElement('div');
        overlay.id = 'liTutOverlay';
        overlay.innerHTML = `
        <style>
            #liTutOverlay{position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.82);display:flex;align-items:center;justify-content:center;font-family:'Inter',sans-serif;}
            .li-box{background:linear-gradient(135deg,rgba(255,71,87,0.12),rgba(26,26,46,0.95));border:1.5px solid rgba(255,71,87,0.4);border-radius:2rem;padding:2.5rem 3rem;max-width:560px;width:90%;backdrop-filter:blur(20px);box-shadow:0 30px 80px rgba(0,0,0,0.7);position:relative;}
            .li-badge{position:absolute;top:-18px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#ff4757,#ffa502);padding:6px 22px;border-radius:999px;font-size:0.78rem;font-weight:700;color:#fff;letter-spacing:1px;}
            .li-title{font-size:1.7rem;font-weight:900;color:#ff4757;margin:0.8rem 0 1rem;text-align:center;font-family:'Orbitron',sans-serif;text-shadow:0 0 20px rgba(255,71,87,0.5);}
            .li-desc{font-size:1.05rem;color:#e0e0e0;line-height:1.7;text-align:center;min-height:80px;margin-bottom:1.5rem;}
            .li-demo{height:80px;display:flex;align-items:center;justify-content:center;margin-bottom:1.5rem;overflow:hidden;}
            .li-nav{display:flex;align-items:center;justify-content:space-between;gap:1rem;}
            .li-btn{padding:0.8rem 1.8rem;border-radius:999px;border:none;font-weight:700;font-size:0.95rem;cursor:pointer;transition:0.2s;}
            .li-prev{background:rgba(255,255,255,0.1);color:#fff;}
            .li-prev:hover{background:rgba(255,255,255,0.2);}
            .li-next{background:linear-gradient(135deg,#ff4757,#ffa502);color:#fff;flex:1;}
            .li-next:hover{filter:brightness(1.15);transform:translateY(-1px);}
            .li-skip{position:absolute;top:1rem;right:1rem;background:none;border:none;color:rgba(255,255,255,0.4);font-size:0.8rem;cursor:pointer;padding:4px 10px;border-radius:999px;transition:0.2s;}
            .li-skip:hover{color:#fff;background:rgba(255,255,255,0.1);}
            .li-dots{display:flex;gap:6px;align-items:center;}
            .li-dot{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,0.25);transition:0.3s;}
            .li-dot.active{background:#ff4757;transform:scale(1.4);}
        </style>
        <div class="li-box">
            <button class="li-skip" onclick="liTutClose()">✕ Bỏ qua</button>
            <div class="li-badge" id="liStepBadge">BƯỚC 1 / ${STEPS.length}</div>
            <div class="li-title" id="liTitle"></div>
            <div class="li-desc" id="liDesc"></div>
            <div class="li-demo" id="liDemo"></div>
            <div class="li-nav">
                <button class="li-btn li-prev" id="liPrev" onclick="liTutPrev()">← Trước</button>
                <div class="li-dots" id="liDots"></div>
                <button class="li-btn li-next" id="liNext" onclick="liTutNext()">Tiếp theo →</button>
            </div>
        </div>`;
        document.body.appendChild(overlay);
        gsap.from(overlay.querySelector('.li-box'), { y: 60, opacity: 0, duration: 0.5, ease: 'back.out(1.4)' });
        document.addEventListener('keydown', function esc(e) { if (e.key === 'Escape') { liTutClose(); document.removeEventListener('keydown', esc); } });
        render();
    }

    function render() {
        const s = STEPS[step];
        document.getElementById('liStepBadge').textContent = `BƯỚC ${step + 1} / ${STEPS.length}`;
        document.getElementById('liTitle').innerHTML = s.title;
        document.getElementById('liDesc').innerHTML = s.desc;
        document.getElementById('liNext').textContent = step === STEPS.length - 1 ? '🚀 Bay thôi!' : 'Tiếp theo →';
        document.getElementById('liPrev').style.visibility = step === 0 ? 'hidden' : 'visible';

        const dotsEl = document.getElementById('liDots');
        dotsEl.innerHTML = '';
        STEPS.forEach((_, i) => { const d = document.createElement('div'); d.className = 'li-dot' + (i === step ? ' active' : ''); dotsEl.appendChild(d); });

        const demo = document.getElementById('liDemo');
        demo.innerHTML = '';
        buildDemo(s.demo, demo);

        gsap.from('#liTitle', { y: -12, opacity: 0, duration: 0.3 });
        gsap.from('#liDesc',  { y: 8,   opacity: 0, duration: 0.3, delay: 0.1 });
    }

    function buildDemo(type, el) {
        if (!type) return;
        if (type === 'bet') {
            el.innerHTML = '<div style="background:rgba(255,71,87,0.15);border:2px solid #ff4757;border-radius:12px;padding:12px 28px;color:#ff4757;font-weight:900;font-size:1.3rem;animation:lpulse 1s infinite alternate">💰 10,000 gtlm</div><style>@keyframes lpulse{from{box-shadow:0 0 8px #ff4757}to{box-shadow:0 0 25px #ff4757}}</style>';
            const b = document.getElementById('betAmount'); if (b) { b.style.outline='3px solid #ff4757'; b.style.borderRadius='8px'; }
        } else if (type === 'target') {
            el.innerHTML = '<div style="text-align:center"><div style="font-size:2.5rem;font-weight:900;color:#f1c40f;font-family:Orbitron,sans-serif;text-shadow:0 0 20px rgba(241,196,15,0.6);animation:lpulse 0.8s infinite alternate">2.00×</div><div style="color:#888;font-size:0.85rem;margin-top:4px">Xác suất thắng: 49.5%</div></div><style>@keyframes lpulse{from{transform:scale(1)}to{transform:scale(1.08)}}</style>';
        } else if (type === 'rocket') {
            el.innerHTML = `<div style="position:relative;height:70px;width:80px">
                <div id="liDemoRocket" style="position:absolute;bottom:0;left:50%;transform:translateX(-50%)">
                    <div style="width:20px;height:36px;position:relative;filter:drop-shadow(0 0 8px #ff4757)">
                        <div style="width:0;height:0;border-left:10px solid transparent;border-right:10px solid transparent;border-bottom:14px solid #ff4757;position:absolute;top:-13px;left:0"></div>
                        <div style="width:20px;height:30px;background:linear-gradient(180deg,#fff,#ccc);border-radius:40% 40% 15% 15%;"></div>
                        <div style="position:absolute;bottom:-14px;left:50%;transform:translateX(-50%);font-size:22px;line-height:1">🔥</div>
                    </div>
                </div>
            </div>`;
            gsap.to('#liDemoRocket', { y: -50, duration: 1.2, ease: 'power2.in', repeat: -1, repeatDelay: 0.3, yoyo: false,
                onRepeat: () => gsap.set('#liDemoRocket', { y: 0 }) });
        } else if (type === 'win') {
            el.innerHTML = '<div style="text-align:center"><div style="font-size:3rem;font-weight:900;color:#2ecc71;font-family:Orbitron,sans-serif;text-shadow:0 0 30px #2ecc71;animation:lpulse 0.7s infinite alternate">2.45×</div><div style="color:#2ecc71;font-size:0.95rem;margin-top:6px;font-weight:700">✅ Kết quả ≥ 2.0 → THẮNG 24,500 gtlm!</div></div><style>@keyframes lpulse{from{transform:scale(1)}to{transform:scale(1.05)}}</style>';
        } else if (type === 'lose') {
            el.innerHTML = '<div style="text-align:center"><div style="font-size:3rem;font-weight:900;color:#ff4757;font-family:Orbitron,sans-serif;text-shadow:0 0 30px #ff4757">1.32×</div><div style="color:#ff4757;font-size:0.95rem;margin-top:6px;font-weight:700">❌ Kết quả < 2.0 → Thua 10,000 gtlm</div></div>';
        }
    }

    function clearHighlights() {
        ['betAmount','targetMult'].forEach(id => { const e = document.getElementById(id); if (e) { e.style.outline=''; e.style.borderRadius=''; } });
    }

    window.liTutNext = function () {
        clearHighlights();
        if (step >= STEPS.length - 1) { liTutClose(); return; }
        step++;
        gsap.to(overlay.querySelector('.li-box'), { x: -20, opacity: 0, duration: 0.18, onComplete: () => { render(); gsap.fromTo(overlay.querySelector('.li-box'), { x: 20, opacity: 0 }, { x: 0, opacity: 1, duration: 0.25 }); } });
    };
    window.liTutPrev = function () {
        if (step <= 0) return;
        step--;
        gsap.to(overlay.querySelector('.li-box'), { x: 20, opacity: 0, duration: 0.18, onComplete: () => { render(); gsap.fromTo(overlay.querySelector('.li-box'), { x: -20, opacity: 0 }, { x: 0, opacity: 1, duration: 0.25 }); } });
    };
    window.liTutClose = function () {
        clearHighlights();
        if (!overlay) return;
        gsap.to(overlay, { opacity: 0, duration: 0.3, onComplete: () => { overlay.remove(); overlay = null; } });
    };
    window.liTutOpen = function () { step = 0; buildUI(); };

    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('btn-howto');
        if (btn) btn.addEventListener('click', function (e) { e.preventDefault(); liTutOpen(); });
    });
})();
