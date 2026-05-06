/**
 * Tower Tutorial - Animated GSAP walkthrough
 */
(function () {
    if (typeof gsap === 'undefined') return;

    const STEPS = [
        {
            title: '🏢 TOWER LÀ GÌ?',
            desc: 'Bạn leo từng tầng của tòa tháp. Mỗi tầng có <b>3 ô</b> — 1 ô là <b>BẪY 💥</b>, 2 ô còn lại là <b>AN TOÀN 💎</b>. Chọn đúng để leo lên, sai là rơi xuống mất cược!',
            demo: null
        },
        {
            title: '💰 BƯỚC 1: ĐẶT CƯỢC & BẮT ĐẦU',
            desc: 'Nhập số gtlm cược rồi nhấn <b>🚀 Bắt đầu leo</b>. Gtlm cược sẽ bị trừ ngay khi bắt đầu.',
            demo: 'start'
        },
        {
            title: '🏠 BƯỚC 2: CHỌN Ô Ở TẦNG HIỆN TẠI',
            desc: 'Tầng đang sáng là tầng bạn cần chọn. Nhấp vào 1 trong 3 ô. Nếu <b>💎 AN TOÀN</b> → lên tầng tiếp. Nếu <b>💥 BẪY</b> → mất cược!',
            demo: 'floor'
        },
        {
            title: '📈 HỆ SỐ NHÂN TĂNG THEO TẦNG',
            desc: 'Tầng 1 = x1.42 · Tầng 3 = x2.95 · Tầng 5 = x5.94 · Tầng 10 = x13.8<br><br>Mỗi tầng bạn leo, thưởng tiềm năng tăng <b>×1.45</b>!',
            demo: 'multipliers'
        },
        {
            title: '💰 BƯỚC 3: RÚT Gtlm BAT KỲ LÚC NÀO',
            desc: 'Bất cứ lúc nào sau tầng 1, bạn có thể nhấn <b>💰 RÚT Gtlm</b> để nhận thưởng an toàn. Đừng tham quá!',
            demo: 'cashout'
        },
        {
            title: '🏆 LEO TỚI TẦNG 10!',
            desc: 'Nếu bạn leo đến đỉnh tháp (tầng 10), bạn nhận thưởng tối đa <b>×13.8</b> số Gtlm cược. Cược 10,000 → nhận <b style="color:#f1c40f">138,000 gtlm</b>!',
            demo: 'maxwin'
        },
        {
            title: '⚡ CHIẾN THUẬT PRO',
            desc: '<b>Rút Gtlm sớm</b> (tầng 3-5): ổn định, ít rủi ro.<br><b>Leo cao</b> (tầng 7-10): rủi ro lớn nhưng thưởng cực hấp dẫn.<br><br>Quản lý tốt số Gtlm và <b>biết dừng đúng lúc</b> là chìa khóa! 🗝️',
            demo: null
        }
    ];

    let step = 0, overlay = null;

    function buildUI() {
        if (document.getElementById('twTutOverlay')) return;
        overlay = document.createElement('div');
        overlay.id = 'twTutOverlay';
        overlay.innerHTML = `
        <style>
            #twTutOverlay{position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.82);display:flex;align-items:center;justify-content:center;font-family:'Inter',sans-serif;}
            .tw-box{background:linear-gradient(135deg,rgba(255,165,2,0.12),rgba(26,26,46,0.95));border:1.5px solid rgba(255,165,2,0.4);border-radius:2rem;padding:2.5rem 3rem;max-width:560px;width:90%;backdrop-filter:blur(20px);box-shadow:0 30px 80px rgba(0,0,0,0.7);position:relative;}
            .tw-badge{position:absolute;top:-18px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#ffa502,#f39c12);padding:6px 22px;border-radius:999px;font-size:0.78rem;font-weight:700;color:#fff;letter-spacing:1px;}
            .tw-title{font-size:1.7rem;font-weight:900;color:#ffa502;margin:0.8rem 0 1rem;text-align:center;font-family:'Orbitron',sans-serif;text-shadow:0 0 20px rgba(255,165,2,0.5);}
            .tw-desc{font-size:1.05rem;color:#e0e0e0;line-height:1.7;text-align:center;min-height:80px;margin-bottom:1.5rem;}
            .tw-demo{height:80px;display:flex;align-items:center;justify-content:center;margin-bottom:1.5rem;}
            .tw-nav{display:flex;align-items:center;justify-content:space-between;gap:1rem;}
            .tw-btn{padding:0.8rem 1.8rem;border-radius:999px;border:none;font-weight:700;font-size:0.95rem;cursor:pointer;transition:0.2s;}
            .tw-prev{background:rgba(255,255,255,0.1);color:#fff;}
            .tw-prev:hover{background:rgba(255,255,255,0.2);}
            .tw-next{background:linear-gradient(135deg,#ffa502,#f39c12);color:#fff;flex:1;}
            .tw-next:hover{filter:brightness(1.15);transform:translateY(-1px);}
            .tw-skip{position:absolute;top:1rem;right:1rem;background:none;border:none;color:rgba(255,255,255,0.4);font-size:0.8rem;cursor:pointer;padding:4px 10px;border-radius:999px;transition:0.2s;}
            .tw-skip:hover{color:#fff;background:rgba(255,255,255,0.1);}
            .tw-dots{display:flex;gap:6px;align-items:center;}
            .tw-dot{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,0.25);transition:0.3s;}
            .tw-dot.active{background:#ffa502;transform:scale(1.4);}
        </style>
        <div class="tw-box">
            <button class="tw-skip" onclick="twTutClose()">✕ Bỏ qua</button>
            <div class="tw-badge" id="twStepBadge">BƯỚC 1 / ${STEPS.length}</div>
            <div class="tw-title" id="twTitle"></div>
            <div class="tw-desc" id="twDesc"></div>
            <div class="tw-demo" id="twDemo"></div>
            <div class="tw-nav">
                <button class="tw-btn tw-prev" id="twPrev" onclick="twTutPrev()">← Trước</button>
                <div class="tw-dots" id="twDots"></div>
                <button class="tw-btn tw-next" id="twNext" onclick="twTutNext()">Tiếp theo →</button>
            </div>
        </div>`;
        document.body.appendChild(overlay);
        gsap.from(overlay.querySelector('.tw-box'), { y: 60, opacity: 0, duration: 0.5, ease: 'back.out(1.4)' });
        document.addEventListener('keydown', function esc(e) { if (e.key === 'Escape') { twTutClose(); document.removeEventListener('keydown', esc); } });
        render();
    }

    function render() {
        const s = STEPS[step];
        document.getElementById('twStepBadge').textContent = `BƯỚC ${step + 1} / ${STEPS.length}`;
        document.getElementById('twTitle').innerHTML = s.title;
        document.getElementById('twDesc').innerHTML = s.desc;
        document.getElementById('twNext').textContent = step === STEPS.length - 1 ? '🏢 Bắt đầu leo!' : 'Tiếp theo →';
        document.getElementById('twPrev').style.visibility = step === 0 ? 'hidden' : 'visible';
        const dotsEl = document.getElementById('twDots');
        dotsEl.innerHTML = '';
        STEPS.forEach((_, i) => { const d = document.createElement('div'); d.className = 'tw-dot' + (i === step ? ' active' : ''); dotsEl.appendChild(d); });
        const demo = document.getElementById('twDemo');
        demo.innerHTML = '';
        buildDemo(s.demo, demo);
        gsap.from('#twTitle', { y: -12, opacity: 0, duration: 0.3 });
        gsap.from('#twDesc', { y: 8, opacity: 0, duration: 0.3, delay: 0.1 });
    }

    function buildDemo(type, el) {
        if (!type) return;
        if (type === 'start') {
            el.innerHTML = '<button style="background:linear-gradient(135deg,#ffa502,#f39c12);color:#fff;border:none;padding:14px 35px;border-radius:14px;font-size:1.1rem;font-weight:900;animation:twp 1s infinite alternate;cursor:default">🚀 Bắt đầu leo</button><style>@keyframes twp{from{box-shadow:0 0 10px #ffa502}to{box-shadow:0 0 30px #ffa502,0 0 0 4px rgba(255,165,2,0.2)}}</style>';
        } else if (type === 'floor') {
            el.innerHTML = '<div style="display:flex;gap:10px">' +
                ['💎', '?', '?'].map((t, i) => `<div style="width:56px;height:46px;background:${i === 0 ? 'linear-gradient(135deg,#27ae60,#2ecc71)' : 'rgba(255,255,255,0.1)'};border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;border:1px solid ${i === 0 ? '#2ecc71' : 'rgba(255,255,255,0.15)'};${i === 0 ? 'box-shadow:0 0 20px rgba(46,204,113,0.7);animation:twp2 0.8s infinite alternate' : ''}">${t}</div>`).join('') +
                '</div><style>@keyframes twp2{from{transform:scale(1)}to{transform:scale(1.1)}}</style>';
        } else if (type === 'multipliers') {
            const mults = ['x1.4', 'x2.0', 'x2.9', 'x4.2', 'x5.9'];
            el.innerHTML = '<div style="display:flex;flex-direction:column;gap:4px;width:180px">' +
                mults.reverse().map((m, i) => `<div style="background:rgba(255,165,2,${0.08 + i * 0.07});border-radius:6px;padding:4px 12px;display:flex;justify-content:space-between;font-size:0.82rem;color:#e0e0e0;border:1px solid rgba(255,165,2,${0.1 + i * 0.08})"><span>Tầng ${5 - i}</span><b style="color:#ffa502">${m}</b></div>`).join('') +
                '</div>';
        } else if (type === 'cashout') {
            el.innerHTML = '<button style="background:linear-gradient(135deg,#2ecc71,#27ae60);color:#fff;border:none;padding:14px 30px;border-radius:14px;font-size:1rem;font-weight:900;animation:twp3 1s infinite;cursor:default">💰 Rút x2.95</button><style>@keyframes twp3{0%,100%{box-shadow:0 0 10px rgba(46,204,113,0.4)}50%{box-shadow:0 0 35px rgba(46,204,113,0.9),0 0 0 5px rgba(46,204,113,0.15)}}</style>';
        } else if (type === 'maxwin') {
            el.innerHTML = '<div style="text-align:center"><div style="font-size:1.8rem;font-weight:900;color:#f1c40f;text-shadow:0 0 20px rgba(241,196,15,0.6);animation:twp2 0.8s infinite alternate">🏆 x13.8 = 138,000 gtlm!</div></div><style>@keyframes twp2{from{transform:scale(1)}to{transform:scale(1.06)}}</style>';
        }
    }

    window.twTutNext = function () { if (step >= STEPS.length - 1) { twTutClose(); return; } step++; gsap.to(overlay.querySelector('.tw-box'), { x: -20, opacity: 0, duration: 0.18, onComplete: () => { render(); gsap.fromTo(overlay.querySelector('.tw-box'), { x: 20, opacity: 0 }, { x: 0, opacity: 1, duration: 0.25 }); } }); };
    window.twTutPrev = function () { if (step <= 0) return; step--; gsap.to(overlay.querySelector('.tw-box'), { x: 20, opacity: 0, duration: 0.18, onComplete: () => { render(); gsap.fromTo(overlay.querySelector('.tw-box'), { x: -20, opacity: 0 }, { x: 0, opacity: 1, duration: 0.25 }); } }); };
    window.twTutClose = function () { if (!overlay) return; gsap.to(overlay, { opacity: 0, duration: 0.3, onComplete: () => { overlay.remove(); overlay = null; } }); };
    window.twTutOpen = function () { step = 0; buildUI(); };

    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('btn-howto');
        if (btn) btn.addEventListener('click', function (e) { e.preventDefault(); twTutOpen(); });
    });
})();
