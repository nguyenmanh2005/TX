/**
 * Plinko Tutorial - Animated GSAP walkthrough
 * Mô phỏng 1 ván chơi hoàn chỉnh để hướng dẫn người chơi
 */
(function () {
    if (typeof gsap === 'undefined') return;

    const STEPS = [
        {
            title: '🔴 PLINKO LÀ GÌ?',
            desc: 'Bạn thả một quả bóng từ trên xuống. Bóng sẽ va vào các đinh và rơi vào 1 trong 9 ô thưởng ở dưới cùng.',
            action: null
        },
        {
            title: '💰 BƯỚC 1: ĐẶT CƯỢC',
            desc: 'Nhập số <b>gtlm muốn cược</b> vào ô "gtlm cược". Bạn có thể cược từ 1,000 đến toàn bộ Số Gtlm.',
            action: 'highlight-bet'
        },
        {
            title: '🎱 BƯỚC 2: THẢ BÓNG',
            desc: 'Nhấn nút <b>🎱 THẢ BÓNG</b>. Bóng sẽ tự động rơi và va vào các đinh ngẫu nhiên.',
            action: 'highlight-btn'
        },
        {
            title: '📍 BÓNG RƠITHEO LỘ TRÌNH NGẪU NHIÊN',
            desc: 'Mỗi khi bóng chạm đinh, nó có <b>50% rơi trái, 50% rơi phải</b>. Hoàn toàn ngẫu nhiên và công bằng!',
            action: 'animate-ball'
        },
        {
            title: '🏆 CÁC Ô THƯỞNG',
            desc: '<b>x5.0</b> (2 ô ngoài cùng) · <b>x2.0</b> · <b>x1.2</b> · <b>x0.5</b> · <b>x0.2</b> (ô giữa). Ô ngoài = thưởng cao nhất!',
            action: 'highlight-pockets'
        },
        {
            title: '✅ THẮNG VÀ THUA',
            desc: 'Nếu bóng rơi vào ô <b>x5.0</b>: cược 10,000 → nhận 50,000 gtlm! Ô <b>x0.2</b>: nhận lại 2,000 gtlm.',
            action: 'show-win'
        },
        {
            title: '🎉 BẮT ĐẦU CHƠI!',
            desc: 'Đơn giản vậy thôi! Chọn mức cược phù hợp và thả bóng. Chúc bạn may mắn! 🍀',
            action: null
        }
    ];

    let currentStep = 0;
    let overlay = null;

    function buildOverlay() {
        if (document.getElementById('plTutOverlay')) return;
        overlay = document.createElement('div');
        overlay.id = 'plTutOverlay';
        overlay.innerHTML = `
        <style>
            #plTutOverlay {
                position:fixed;inset:0;z-index:99999;
                background:rgba(0,0,0,0.82);
                display:flex;align-items:center;justify-content:center;
                font-family:'Inter',sans-serif;
            }
            .pl-tut-box {
                background:linear-gradient(135deg,rgba(18,194,233,0.15),rgba(196,113,237,0.15));
                border:1.5px solid rgba(18,194,233,0.4);
                border-radius:2rem;
                padding:2.5rem 3rem;
                max-width:560px;width:90%;
                backdrop-filter:blur(20px);
                box-shadow:0 30px 80px rgba(0,0,0,0.7),0 0 60px rgba(18,194,233,0.15);
                position:relative;
            }
            .pl-tut-step-badge {
                position:absolute;top:-18px;left:50%;transform:translateX(-50%);
                background:linear-gradient(135deg,#12c2e9,#c471ed);
                padding:6px 22px;border-radius:999px;
                font-size:0.78rem;font-weight:700;color:#fff;letter-spacing:1px;
            }
            .pl-tut-title {
                font-size:1.7rem;font-weight:900;color:#12c2e9;
                margin:0.8rem 0 1rem;text-align:center;font-family:'Orbitron',sans-serif;
                text-shadow:0 0 20px rgba(18,194,233,0.5);
            }
            .pl-tut-desc {
                font-size:1.05rem;color:#e0e0e0;line-height:1.7;text-align:center;
                min-height:80px;margin-bottom:1.5rem;
            }
            .pl-tut-demo {
                height:80px;display:flex;align-items:center;justify-content:center;
                margin-bottom:1.5rem;position:relative;overflow:hidden;
            }
            /* Mini plinko demo */
            .pl-demo-ball {
                width:14px;height:14px;
                background:radial-gradient(circle at 30% 30%,#fff,#12c2e9);
                border-radius:50%;box-shadow:0 0 15px #12c2e9;
                position:absolute;top:8px;left:50%;
                transform:translateX(-50%);
                display:none;
            }
            .pl-demo-pocket {
                width:36px;height:28px;border-radius:6px;
                display:flex;align-items:center;justify-content:center;
                font-size:0.65rem;font-weight:900;margin:0 3px;
                border:1px solid rgba(255,255,255,0.2);
                transition:transform 0.3s,box-shadow 0.3s;
            }
            .pl-tut-nav {
                display:flex;align-items:center;justify-content:space-between;gap:1rem;
            }
            .pl-tut-btn {
                padding:0.8rem 1.8rem;border-radius:999px;border:none;
                font-weight:700;font-size:0.95rem;cursor:pointer;transition:0.2s;
            }
            .pl-tut-prev { background:rgba(255,255,255,0.1);color:#fff; }
            .pl-tut-prev:hover { background:rgba(255,255,255,0.2); }
            .pl-tut-next { background:linear-gradient(135deg,#12c2e9,#c471ed);color:#fff;flex:1; }
            .pl-tut-next:hover { filter:brightness(1.15);transform:translateY(-1px); }
            .pl-tut-skip {
                position:absolute;top:1rem;right:1rem;
                background:none;border:none;color:rgba(255,255,255,0.4);
                font-size:0.8rem;cursor:pointer;padding:4px 10px;
                border-radius:999px;transition:0.2s;
            }
            .pl-tut-skip:hover{color:#fff;background:rgba(255,255,255,0.1);}
            .pl-tut-dots { display:flex;gap:6px;align-items:center; }
            .pl-tut-dot {
                width:8px;height:8px;border-radius:50%;
                background:rgba(255,255,255,0.25);transition:0.3s;
            }
            .pl-tut-dot.active { background:#12c2e9;transform:scale(1.4); }
            /* Pocket highlight */
            .pocket-hi { animation:pocketHi 0.5s ease infinite alternate; }
            @keyframes pocketHi {
                from { transform:scale(1); box-shadow:0 0 8px currentColor; }
                to   { transform:scale(1.3); box-shadow:0 0 25px currentColor; }
            }
        </style>
        <div class="pl-tut-box">
            <button class="pl-tut-skip" onclick="plTutClose()">✕ Bỏ qua</button>
            <div class="pl-tut-step-badge" id="plStepBadge">BƯỚC 1 / ${STEPS.length}</div>
            <div class="pl-tut-title" id="plTutTitle"></div>
            <div class="pl-tut-desc" id="plTutDesc"></div>
            <div class="pl-tut-demo" id="plTutDemo">
                <div class="pl-demo-ball" id="plDemoBall"></div>
            </div>
            <div class="pl-tut-nav">
                <button class="pl-tut-btn pl-tut-prev" id="plPrev" onclick="plTutPrev()">← Trước</button>
                <div class="pl-tut-dots" id="plDots"></div>
                <button class="pl-tut-btn pl-tut-next" id="plNext" onclick="plTutNext()">Tiếp theo →</button>
            </div>
        </div>`;
        document.body.appendChild(overlay);
        gsap.from(overlay.querySelector('.pl-tut-box'), { y: 60, opacity: 0, duration: 0.5, ease: 'back.out(1.4)' });

        // Escape key
        document.addEventListener('keydown', function onKey(e) {
            if (e.key === 'Escape') { plTutClose(); document.removeEventListener('keydown', onKey); }
        });
        renderStep();
    }

    function renderStep() {
        if (!overlay) return;
        const s = STEPS[currentStep];
        document.getElementById('plStepBadge').textContent = `BƯỚC ${currentStep + 1} / ${STEPS.length}`;
        document.getElementById('plTutTitle').innerHTML = s.title;
        document.getElementById('plTutDesc').innerHTML = s.desc;
        document.getElementById('plNext').textContent = currentStep === STEPS.length - 1 ? '🎱 Bắt đầu chơi!' : 'Tiếp theo →';
        document.getElementById('plPrev').style.visibility = currentStep === 0 ? 'hidden' : 'visible';

        // Dots
        const dotsEl = document.getElementById('plDots');
        dotsEl.innerHTML = '';
        STEPS.forEach((_, i) => {
            const d = document.createElement('div');
            d.className = 'pl-tut-dot' + (i === currentStep ? ' active' : '');
            dotsEl.appendChild(d);
        });

        // Demo area
        const demo = document.getElementById('plTutDemo');
        demo.innerHTML = '<div class="pl-demo-ball" id="plDemoBall"></div>';

        runAction(s.action, demo);

        gsap.from('#plTutTitle', { y: -15, opacity: 0, duration: 0.35 });
        gsap.from('#plTutDesc', { y: 10, opacity: 0, duration: 0.35, delay: 0.1 });
    }

    function runAction(action, demo) {
        if (!action) return;
        if (action === 'highlight-bet') {
            const el = document.getElementById('betAmount');
            if (el) { el.style.outline = '3px solid #12c2e9'; el.style.borderRadius = '8px'; el.focus(); }
            demo.innerHTML = '<div style="background:rgba(18,194,233,0.2);border:2px solid #12c2e9;border-radius:12px;padding:12px 28px;color:#12c2e9;font-weight:900;font-size:1.3rem;animation:pulse2 1s infinite alternate">💰 10,000 gtlm</div><style>@keyframes pulse2{from{box-shadow:0 0 8px #12c2e9}to{box-shadow:0 0 25px #12c2e9}}</style>';
        } else if (action === 'highlight-btn') {
            const el = document.getElementById('dropBtn');
            if (el) gsap.to(el, { scale: 1.08, repeat: 3, yoyo: true, duration: 0.4 });
            demo.innerHTML = '<button style="background:linear-gradient(135deg,#12c2e9,#c471ed);color:#fff;border:none;padding:14px 35px;border-radius:14px;font-size:1.1rem;font-weight:900;animation:pulse2 1s infinite alternate;cursor:default">🎱 THẢ BÓNG</button>';
        } else if (action === 'animate-ball') {
            demo.innerHTML = '<div style="position:relative;width:260px;height:70px;background:rgba(0,0,0,0.3);border-radius:12px;overflow:hidden"><div id="plDemoBall2" style="position:absolute;top:5px;left:50%;transform:translateX(-50%);width:12px;height:12px;background:radial-gradient(circle at 30% 30%,#fff,#12c2e9);border-radius:50%;box-shadow:0 0 10px #12c2e9"></div></div>';
            const ball = document.getElementById('plDemoBall2');
            if (ball) {
                const tl = gsap.timeline({ repeat: -1, repeatDelay: 0.5 });
                tl.set(ball, { x: 0, y: 0 })
                    .to(ball, { x: -25, y: 18, duration: 0.2, ease: 'bounce.out' })
                    .to(ball, { x: 10, y: 36, duration: 0.2, ease: 'bounce.out' })
                    .to(ball, { x: -15, y: 54, duration: 0.2, ease: 'bounce.out' });
            }
        } else if (action === 'highlight-pockets') {
            const mults = [5.0, 2.0, 1.2, 0.5, 0.2, 0.5, 1.2, 2.0, 5.0];
            const colors = ['#2ecc71', '#2ecc71', '#f1c40f', '#e67e22', '#e74c3c', '#e67e22', '#f1c40f', '#2ecc71', '#2ecc71'];
            demo.innerHTML = '<div style="display:flex;gap:4px;align-items:flex-end">' +
                mults.map((m, i) => `<div style="width:30px;height:26px;background:${colors[i]};border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:0.58rem;font-weight:900;color:#000;animation:pulse2 ${0.6 + i * 0.07}s infinite alternate">x${m}</div>`).join('') +
                '</div><style>@keyframes pulse2{from{transform:scaleY(1)}to{transform:scaleY(1.25)}}</style>';
        } else if (action === 'show-win') {
            demo.innerHTML = '<div style="text-align:center"><div style="font-size:1.4rem;font-weight:900;color:#2ecc71;text-shadow:0 0 20px #2ecc71;animation:pulse2 0.8s infinite alternate">🎉 x5.0 → +40,000 gtlm!</div></div><style>@keyframes pulse2{from{transform:scale(1)}to{transform:scale(1.08)}}</style>';
        }
    }

    window.plTutNext = function () {
        // Remove highlights
        const el = document.getElementById('betAmount');
        if (el) { el.style.outline = ''; el.style.borderRadius = ''; }

        if (currentStep >= STEPS.length - 1) { plTutClose(); return; }
        currentStep++;
        gsap.to(overlay.querySelector('.pl-tut-box'), {
            x: -20, opacity: 0, duration: 0.18,
            onComplete: () => { renderStep(); gsap.fromTo(overlay.querySelector('.pl-tut-box'), { x: 20, opacity: 0 }, { x: 0, opacity: 1, duration: 0.25 }); }
        });
    };

    window.plTutPrev = function () {
        if (currentStep <= 0) return;
        currentStep--;
        gsap.to(overlay.querySelector('.pl-tut-box'), {
            x: 20, opacity: 0, duration: 0.18,
            onComplete: () => { renderStep(); gsap.fromTo(overlay.querySelector('.pl-tut-box'), { x: -20, opacity: 0 }, { x: 0, opacity: 1, duration: 0.25 }); }
        });
    };

    window.plTutClose = function () {
        if (!overlay) return;
        gsap.to(overlay, { opacity: 0, duration: 0.3, onComplete: () => { overlay.remove(); overlay = null; } });
        // Remove highlights
        const el = document.getElementById('betAmount');
        if (el) { el.style.outline = ''; el.style.borderRadius = ''; }
    };

    window.plTutOpen = function () {
        currentStep = 0;
        buildOverlay();
    };

    // Hook nút how-to
    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('btn-howto');
        if (btn) btn.addEventListener('click', function (e) { e.preventDefault(); plTutOpen(); });
    });
})();
