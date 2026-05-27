// assets/js/event_banner.js
(function () {
    let currentEventId = null;
    let countdownTimer = null;

    // Templates UI theo từng loại event
    const eventTemplates = {
        money_rain: (event) => `
            <div class="reb-body">
                <p class="reb-desc">${event.description}</p>
                ${!event.participated
                    ? `<button class="reb-btn" onclick="EventBanner.claimRain()">
                           💸 Nhận GTLM ngay!
                       </button>`
                    : `<div class="reb-claimed">✅ Đã húp rồi!</div>`
                }
            </div>`,

        mystery_box: (event) => `
            <div class="reb-body">
                <p class="reb-desc">${event.description}</p>
                ${!event.participated
                    ? `<button class="reb-btn reb-btn-gold" onclick="EventBanner.openBox()">
                           🎁 Mở hộp quà!
                       </button>`
                    : `<div class="reb-claimed">✅ Đã mở rồi!</div>`
                }
            </div>`,

        lucky_number: (event) => `
            <div class="reb-body">
                <p class="reb-desc">${event.description}</p>
                ${!event.participated
                    ? `<div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin-top:8px">
                           ${Array.from({length: event.config.number_range}, (_, i) =>
                               `<button class="reb-num-btn" onclick="EventBanner.guessNumber(${i+1})">${i+1}</button>`
                           ).join('')}
                       </div>`
                    : `<div class="reb-claimed">✅ Đã đoán xong!</div>`
                }
            </div>`,

        golden_hour: (event) => `
            <div class="reb-body">
                <p class="reb-desc">${event.description}</p>
                <div class="reb-buff">⚡ XP x${event.config.xp_multiplier} đang active!</div>
            </div>`,

        double_win: (event) => `
            <div class="reb-body">
                <p class="reb-desc">${event.description}</p>
                <div class="reb-buff">🔥 Chơi game ngay để húp nhân đôi!</div>
            </div>`,
    };

    const styles = `
        <style>
        #random-event-banner { position:fixed; bottom:80px; right:20px; z-index:9999;
            width:320px; animation: rebSlideIn .4s cubic-bezier(0.175, 0.885, 0.32, 1.275); font-family: 'Outfit', sans-serif; }
        @keyframes rebSlideIn {
            from { transform: translateX(120%); opacity:0; }
            to   { transform: translateX(0);    opacity:1; }
        }
        .reb-card { background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
            border: 1px solid rgba(139, 92, 246, 0.4); border-radius: 18px;
            padding: 20px; color: #fff; box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(12px); }
        .reb-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .reb-title  { font-size: 16px; font-weight: 700; background: linear-gradient(90deg, #fff, #a855f7); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .reb-timer  { font-size: 13px; background: rgba(255, 255, 255, 0.1);
            padding: 4px 12px; border-radius: 20px; font-variant-numeric: tabular-nums; font-weight: 600; }
        .reb-desc   { font-size: 14px; opacity: 0.85; margin-bottom: 16px; line-height: 1.6; }
        .reb-btn    { width: 100%; padding: 12px; border: none; border-radius: 12px;
            background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff;
            font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }
        .reb-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4); }
        .reb-btn:active { transform: translateY(0); }
        .reb-btn-gold { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); }
        .reb-claimed  { text-align: center; font-size: 14px; opacity: 0.6; padding: 10px; border: 1px dashed rgba(255,255,255,0.2); border-radius: 12px; }
        .reb-buff     { background: rgba(255, 255, 255, 0.05); border-radius: 12px;
            padding: 12px; font-size: 14px; text-align: center; border: 1px solid rgba(255,255,255,0.1); }
        .reb-num-btn  { width: 44px; height: 44px; border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05); color: #fff; border-radius: 10px;
            font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.2s; }
        .reb-num-btn:hover { background: var(--primary, #6366f1); border-color: transparent; transform: scale(1.1); }
        .reb-close { background: none; border: none; color: #fff; opacity: 0.4;
            cursor: pointer; font-size: 20px; line-height: 1; padding: 5px; transition: opacity 0.2s; }
        .reb-close:hover { opacity: 1; }
        </style>
    `;

    if (!document.getElementById('random-event-banner-style')) {
        const styleEl = document.createElement('div');
        styleEl.id = 'random-event-banner-style';
        styleEl.innerHTML = styles;
        document.head.appendChild(styleEl);
    }

    function formatTime(seconds) {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    }

    function render(event) {
        let container = document.getElementById('random-event-banner');
        if (!container) {
            container = document.createElement('div');
            container.id = 'random-event-banner';
            document.body.appendChild(container);
        }

        const tmpl = eventTemplates[event.event_type];
        const body  = tmpl ? tmpl(event) : `<p class="reb-desc">${event.description}</p>`;
        container.innerHTML = `
            <div class="reb-card">
                <div class="reb-header">
                    <span class="reb-title">${event.event_name}</span>
                    <span class="reb-timer" id="reb-timer">${formatTime(event.seconds_left)}</span>
                    <button class="reb-close" onclick="EventBanner.close()">✕</button>
                </div>
                ${body}
            </div>`;

        if (countdownTimer) clearInterval(countdownTimer);
        let left = event.seconds_left;
        countdownTimer = setInterval(() => {
            left--;
            const el = document.getElementById('reb-timer');
            if (el) el.textContent = formatTime(Math.max(0, left));
            if (left <= 0) { clearInterval(countdownTimer); pollEvent(); }
        }, 1000);
    }

    async function pollEvent() {
        try {
            const res  = await fetch('api_random_event.php?action=get_active');
            const data = await res.json();
            if (data.status === 'active') {
                if (data.event.id !== currentEventId) {
                    currentEventId = data.event.id;
                    render(data.event);
                    if (window.Swal) {
                        if (data.event.event_type === 'money_rain') {
                            Swal.fire({
                                title: '💸 MƯA GTLM ĐÃ XUẤT HIỆN!',
                                html: `<p style="font-size: 15px; opacity:0.85; line-height:1.6;">Cơn mưa tài lộc đang đổ xuống! Hãy nhanh tay gõ <b>!nhận</b> trong khung Chat thế giới hoặc nhấn nút bên dưới để húp GTLM miễn phí!</p>`,
                                icon: 'info',
                                confirmButtonText: '💸 Húp GTLM Ngay',
                                showCancelButton: true,
                                cancelButtonText: 'Đóng',
                                background: '#1e1b4b',
                                color: '#fff',
                                confirmButtonColor: '#10b981'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    EventBanner.claimRain();
                                }
                            });
                        } else if (data.event.event_type === 'lucky_number') {
                            Swal.fire({
                                title: '🔢 SỐ MAY MẮN XUẤT HIỆN!',
                                html: `<p style="font-size: 15px; opacity:0.85; line-height:1.6;">Dự đoán con số may mắn từ 1 đến ${data.event.config.number_range} để húp trọn <b>200.000 GTLM</b>! Hãy gõ <b>!đoán [số]</b> trong Chat hoặc chọn số dưới đây:</p>
                                <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap; margin-top:15px;">
                                    ${Array.from({length: data.event.config.number_range}, (_, i) =>
                                        `<button class="reb-num-btn" style="width:40px; height:40px; font-weight:800; cursor:pointer;" onclick="Swal.clickConfirm(); EventBanner.guessNumber(${i+1});">${i+1}</button>`
                                    ).join('')}
                                </div>`,
                                icon: 'question',
                                showConfirmButton: false,
                                showCancelButton: true,
                                cancelButtonText: 'Để sau',
                                background: '#1e1b4b',
                                color: '#fff'
                            });
                        } else {
                            Swal.fire({ 
                                title: '🎉 Sự kiện bất ngờ!',
                                text: data.event.event_name, 
                                timer: 5000,
                                showConfirmButton: false, 
                                position: 'top-end', 
                                toast: true,
                                background: '#1e1b4b',
                                color: '#fff'
                            });
                        }
                    }
                }
            } else {
                if (currentEventId) {
                    currentEventId = null;
                    const el = document.getElementById('random-event-banner');
                    if (el) el.innerHTML = '';
                    if (countdownTimer) clearInterval(countdownTimer);
                }
            }
        } catch (e) { }
    }

    window.EventBanner = {
        close() { 
            const el = document.getElementById('random-event-banner');
            if (el) el.innerHTML = ''; 
        },

        async claimRain() {
            const res  = await fetch('api_random_event.php?action=claim_rain');
            const data = await res.json();
            if (window.Swal) {
                Swal.fire({ 
                    icon: data.status === 'success' ? 'success' : 'error',
                    title: data.message, 
                    timer: 2500, 
                    showConfirmButton: false,
                    background: '#1e1b4b',
                    color: '#fff'
                });
            }
            if (data.status === 'success') setTimeout(pollEvent, 500);
        },

        async openBox() {
            const res  = await fetch('api_random_event.php?action=open_box');
            const data = await res.json();
            if (data.status === 'success') {
                if (window.Swal) {
                    Swal.fire({ 
                        icon: 'success', 
                        title: data.message,
                        html: `<div style="font-size:64px; margin-top:20px;">${data.prize.type === 'gtlm' ? '💰' : '⚡'}</div>`, 
                        timer: 3000, 
                        showConfirmButton: false,
                        background: '#1e1b4b',
                        color: '#fff'
                    });
                }
                setTimeout(pollEvent, 500);
            } else if (window.Swal) {
                Swal.fire({ icon: 'error', title: data.message, timer: 2000, showConfirmButton: false, background: '#1e1b4b', color: '#fff' });
            }
        },

        async guessNumber(n) {
            const fd = new FormData();
            fd.append('action', 'guess_number');
            fd.append('guess', n);
            const res  = await fetch('api_random_event.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (window.Swal) {
                Swal.fire({
                    icon:  data.correct ? 'success' : 'error',
                    title: data.correct ? '🎉 Chính xác!' : '❌ Sai rồi!',
                    text:  data.message, 
                    timer: 3000, 
                    showConfirmButton: false,
                    background: '#1e1b4b',
                    color: '#fff'
                });
            }
            setTimeout(pollEvent, 500);
        },
    };

    pollEvent();
    setInterval(pollEvent, 30000);
})();
