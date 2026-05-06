/**
 * 🎰 SLOT MACHINE SOUND EFFECTS
 * Web Audio API - Không cần file âm thanh ngoài
 * Tích hợp: <script src="slot-sounds.js"></script>
 * Gọi: SlotSounds.spin() / .reelTick() / .reelStop(i) / .insertCoin() / .win() / .lose() / .bigWin() / .coinDrop()
 */

const SlotSounds = (() => {
    let ctx = null;

    function getCtx() {
        if (!ctx) ctx = new (window.AudioContext || window.webkitAudioContext)();
        if (ctx.state === 'suspended') ctx.resume();
        return ctx;
    }

    function masterGain(vol = 0.5) {
        const g = getCtx().createGain();
        g.gain.value = vol;
        g.connect(getCtx().destination);
        return g;
    }

    // ─────────────────────────────────────────
    // 🪙 BỎ COIN VÀO MÁY — "clink clink đúng chỗ"
    // ─────────────────────────────────────────
    function insertCoin() {
        const ac = getCtx();
        const out = masterGain(0.55);

        // Tiếng rơi kim loại: 3 ping nhanh
        [1200, 900, 1500].forEach((freq, i) => {
            setTimeout(() => {
                const osc = ac.createOscillator();
                const g = ac.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(freq, ac.currentTime);
                osc.frequency.exponentialRampToValueAtTime(freq * 0.5, ac.currentTime + 0.15);
                g.gain.setValueAtTime(0.6, ac.currentTime);
                g.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.18);
                osc.connect(g); g.connect(out);
                osc.start(); osc.stop(ac.currentTime + 0.2);
            }, i * 55);
        });

        // Tiếng "thunk" nhỏ khi coin chạm đáy
        setTimeout(() => {
            const buf = ac.createBuffer(1, ac.sampleRate * 0.06, ac.sampleRate);
            const d = buf.getChannelData(0);
            for (let j = 0; j < d.length; j++) d[j] = (Math.random() * 2 - 1) * Math.pow(1 - j / d.length, 4);
            const src = ac.createBufferSource();
            const g = ac.createGain();
            const f = ac.createBiquadFilter();
            f.type = 'lowpass'; f.frequency.value = 300;
            g.gain.setValueAtTime(0.4, ac.currentTime);
            g.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.06);
            src.buffer = buf; src.connect(f); f.connect(g); g.connect(out); src.start();
        }, 180);
    }

    // ─────────────────────────────────────────
    // 🎰 TIẾNG QUAY — lever kéo xoẹt + motor rồ
    // ─────────────────────────────────────────
    function spin() {
        const ac = getCtx();
        const out = masterGain(0.45);

        // "Xoẹt" lever kéo: sweep noise
        const buf = ac.createBuffer(1, ac.sampleRate * 0.25, ac.sampleRate);
        const d = buf.getChannelData(0);
        for (let j = 0; j < d.length; j++) d[j] = (Math.random() * 2 - 1) * Math.pow(1 - j / d.length, 1.5);
        const src = ac.createBufferSource();
        const sweep = ac.createBiquadFilter();
        sweep.type = 'bandpass';
        sweep.frequency.setValueAtTime(800, ac.currentTime);
        sweep.frequency.exponentialRampToValueAtTime(200, ac.currentTime + 0.25);
        const g = ac.createGain();
        g.gain.setValueAtTime(0.7, ac.currentTime);
        g.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.25);
        src.buffer = buf; src.connect(sweep); sweep.connect(g); g.connect(out); src.start();

        // "Rồ" motor: oscillator buzz tăng tốc
        setTimeout(() => {
            const osc = ac.createOscillator();
            const og = ac.createGain();
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(40, ac.currentTime);
            osc.frequency.linearRampToValueAtTime(90, ac.currentTime + 0.4);
            og.gain.setValueAtTime(0, ac.currentTime);
            og.gain.linearRampToValueAtTime(0.3, ac.currentTime + 0.1);
            og.gain.setValueAtTime(0.3, ac.currentTime + 0.3);
            og.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.5);
            osc.connect(og); og.connect(out);
            osc.start(); osc.stop(ac.currentTime + 0.5);
        }, 50);
    }

    // ─────────────────────────────────────────
    // ⚙️ REEL TICK — tiếng cột cuộn (gọi loop trong JS)
    // ─────────────────────────────────────────
    function reelTick() {
        const ac = getCtx();
        const out = masterGain(0.4);

        // Tiếng "tách" nhựa/kim loại nhanh
        const buf = ac.createBuffer(1, ac.sampleRate * 0.018, ac.sampleRate);
        const d = buf.getChannelData(0);
        for (let j = 0; j < d.length; j++) d[j] = (Math.random() * 2 - 1) * Math.pow(1 - j / d.length, 5);
        const src = ac.createBufferSource();
        const f = ac.createBiquadFilter();
        f.type = 'bandpass'; f.frequency.value = 1200 + Math.random() * 600;
        const g = ac.createGain();
        g.gain.setValueAtTime(0.7, ac.currentTime);
        g.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.018);
        src.buffer = buf; src.connect(f); f.connect(g); g.connect(out); src.start();
    }

    // ─────────────────────────────────────────
    // 🛑 REEL STOP — "thụp" + bounce nhỏ (gọi 3 lần với delay khác nhau)
    // ─────────────────────────────────────────
    function reelStop(reelIndex = 0) {
        const ac = getCtx();
        const out = masterGain(0.65);

        // Thud chính
        const buf = ac.createBuffer(1, ac.sampleRate * 0.12, ac.sampleRate);
        const d = buf.getChannelData(0);
        for (let j = 0; j < d.length; j++) d[j] = (Math.random() * 2 - 1) * Math.pow(1 - j / d.length, 3);
        const src = ac.createBufferSource();
        const f = ac.createBiquadFilter();
        f.type = 'lowpass'; f.frequency.value = 500;
        const g = ac.createGain();
        g.gain.setValueAtTime(1, ac.currentTime);
        g.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.12);
        src.buffer = buf; src.connect(f); f.connect(g); g.connect(out); src.start();

        // Pitch thấp dần
        const osc = ac.createOscillator();
        const og = ac.createGain();
        const baseFreq = 140 - reelIndex * 15;
        osc.type = 'sine';
        osc.frequency.setValueAtTime(baseFreq, ac.currentTime);
        osc.frequency.exponentialRampToValueAtTime(baseFreq * 0.5, ac.currentTime + 0.1);
        og.gain.setValueAtTime(0.5, ac.currentTime);
        og.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.1);
        osc.connect(og); og.connect(out);
        osc.start(); osc.stop(ac.currentTime + 0.12);

        // Bounce nhỏ sau 60ms
        setTimeout(() => {
            const osc2 = ac.createOscillator();
            const og2 = ac.createGain();
            osc2.type = 'sine';
            osc2.frequency.value = baseFreq * 1.3;
            og2.gain.setValueAtTime(0.2, ac.currentTime);
            og2.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.06);
            osc2.connect(og2); og2.connect(out);
            osc2.start(); osc2.stop(ac.currentTime + 0.07);
        }, 60);
    }

    // ─────────────────────────────────────────
    // 🎉 THẮNG NHỎ — ding ding ding vui vẻ
    // ─────────────────────────────────────────
    function win() {
        const ac = getCtx();
        const out = masterGain(0.5);

        // Arpeggio vui: C-E-G-C nhanh
        const notes = [523, 659, 784, 1047];
        notes.forEach((freq, i) => {
            setTimeout(() => {
                // Sine chính
                const osc = ac.createOscillator();
                const g = ac.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                g.gain.setValueAtTime(0.5, ac.currentTime);
                g.gain.setValueAtTime(0.5, ac.currentTime + 0.08);
                g.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.2);
                osc.connect(g); g.connect(out);
                osc.start(); osc.stop(ac.currentTime + 0.22);

                // Octave trên mờ hơn
                const osc2 = ac.createOscillator();
                const g2 = ac.createGain();
                osc2.type = 'triangle';
                osc2.frequency.value = freq * 2;
                g2.gain.setValueAtTime(0.15, ac.currentTime);
                g2.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.15);
                osc2.connect(g2); g2.connect(out);
                osc2.start(); osc2.stop(ac.currentTime + 0.16);
            }, i * 100);
        });

        // Hi-hat tí tách
        [0, 100, 200, 300].forEach(ms => {
            setTimeout(() => {
                const buf = ac.createBuffer(1, ac.sampleRate * 0.03, ac.sampleRate);
                const d = buf.getChannelData(0);
                for (let j = 0; j < d.length; j++) d[j] = (Math.random() * 2 - 1) * (1 - j / d.length);
                const src = ac.createBufferSource();
                const f = ac.createBiquadFilter();
                f.type = 'highpass'; f.frequency.value = 8000;
                const g = ac.createGain();
                g.gain.setValueAtTime(0.15, ac.currentTime);
                g.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.03);
                src.buffer = buf; src.connect(f); f.connect(g); g.connect(out); src.start();
            }, ms);
        });
    }

    // ─────────────────────────────────────────
    // 😞 THUA — "buồn bã" descend + thở dài
    // ─────────────────────────────────────────
    function lose() {
        const ac = getCtx();
        const out = masterGain(0.45);

        // "Bùm bùm bùm" nặng nề xuống
        [300, 240, 180].forEach((freq, i) => {
            setTimeout(() => {
                const osc = ac.createOscillator();
                const g = ac.createGain();
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(freq, ac.currentTime);
                osc.frequency.exponentialRampToValueAtTime(freq * 0.65, ac.currentTime + 0.22);
                g.gain.setValueAtTime(0.4, ac.currentTime);
                g.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.25);
                osc.connect(g); g.connect(out);
                osc.start(); osc.stop(ac.currentTime + 0.27);
            }, i * 200);
        });

        // "Tiếng thở dài" — noise sweep xuống
        setTimeout(() => {
            const buf = ac.createBuffer(1, ac.sampleRate * 0.4, ac.sampleRate);
            const d = buf.getChannelData(0);
            for (let j = 0; j < d.length; j++) d[j] = (Math.random() * 2 - 1) * 0.3 * Math.pow(1 - j / d.length, 1);
            const src = ac.createBufferSource();
            const f = ac.createBiquadFilter();
            f.type = 'bandpass';
            f.frequency.setValueAtTime(1200, ac.currentTime);
            f.frequency.exponentialRampToValueAtTime(200, ac.currentTime + 0.4);
            const g = ac.createGain();
            g.gain.setValueAtTime(0.25, ac.currentTime);
            g.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.4);
            src.buffer = buf; src.connect(f); f.connect(g); g.connect(out); src.start();
        }, 600);

        // Nốt buồn cuối (minor third)
        setTimeout(() => {
            [196, 233].forEach(freq => {
                const osc = ac.createOscillator();
                const g = ac.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                g.gain.setValueAtTime(0.2, ac.currentTime);
                g.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.6);
                osc.connect(g); g.connect(out);
                osc.start(); osc.stop(ac.currentTime + 0.65);
            });
        }, 700);
    }

    // ─────────────────────────────────────────
    // 💰 BIG WIN / JACKPOT — fanfare hoành tráng hài hước
    // ─────────────────────────────────────────
    function bigWin() {
        const ac = getCtx();
        const out = masterGain(0.4);

        // Fanfare dài: C major arpeggio lên rồi chord cuối
        const fanfare = [523, 659, 784, 1047, 784, 1047, 1319, 1047, 1319, 1568];
        fanfare.forEach((freq, i) => {
            setTimeout(() => {
                // Chord: sine + square + triangle
                ['sine', 'square', 'triangle'].forEach((type, t) => {
                    const osc = ac.createOscillator();
                    const g = ac.createGain();
                    osc.type = type;
                    osc.frequency.value = type === 'square' ? freq / 2 : freq;
                    const vol = [0.35, 0.08, 0.18][t];
                    g.gain.setValueAtTime(vol, ac.currentTime);
                    g.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + (i === fanfare.length - 1 ? 0.6 : 0.18));
                    osc.connect(g); g.connect(out);
                    osc.start(); osc.stop(ac.currentTime + (i === fanfare.length - 1 ? 0.65 : 0.2));
                });

                // Crash cymbal mỗi 3 nốt
                if (i % 3 === 0) {
                    const nbuf = ac.createBuffer(1, ac.sampleRate * 0.08, ac.sampleRate);
                    const nd = nbuf.getChannelData(0);
                    for (let j = 0; j < nd.length; j++) nd[j] = (Math.random() * 2 - 1) * Math.pow(1 - j / nd.length, 2);
                    const nsrc = ac.createBufferSource();
                    const nf = ac.createBiquadFilter();
                    nf.type = 'highpass'; nf.frequency.value = 5000;
                    const ng = ac.createGain();
                    ng.gain.setValueAtTime(0.3, ac.currentTime);
                    ng.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.08);
                    nsrc.buffer = nbuf; nsrc.connect(nf); nf.connect(ng); ng.connect(out); nsrc.start();
                }
            }, i * 120);
        });

        // "Drum roll" trước khi kết
        for (let i = 0; i < 16; i++) {
            setTimeout(() => {
                const buf = ac.createBuffer(1, ac.sampleRate * 0.025, ac.sampleRate);
                const d = buf.getChannelData(0);
                for (let j = 0; j < d.length; j++) d[j] = (Math.random() * 2 - 1) * Math.pow(1 - j / d.length, 3);
                const src = ac.createBufferSource();
                const f = ac.createBiquadFilter();
                f.type = 'bandpass'; f.frequency.value = 250;
                const g = ac.createGain();
                g.gain.setValueAtTime(0.4 + i * 0.02, ac.currentTime);
                g.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.025);
                src.buffer = buf; src.connect(f); f.connect(g); g.connect(out); src.start();
            }, i * 35);
        }

        // "WOW" bass boom kết thúc
        setTimeout(() => {
            const osc = ac.createOscillator();
            const g = ac.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(80, ac.currentTime);
            osc.frequency.exponentialRampToValueAtTime(40, ac.currentTime + 0.4);
            g.gain.setValueAtTime(0.8, ac.currentTime);
            g.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.5);
            osc.connect(g); g.connect(out);
            osc.start(); osc.stop(ac.currentTime + 0.55);
        }, 1050);
    }

    // ─────────────────────────────────────────
    // 💵 Gtlm RƠI — coins đổ ra cửa máy
    // ─────────────────────────────────────────
    function coinDrop() {
        const ac = getCtx();
        const out = masterGain(0.4);
        const count = 22;

        for (let i = 0; i < count; i++) {
            // Mật độ cao đầu, thưa dần cuối
            const delay = Math.pow(i / count, 0.6) * 1200 + Math.random() * 40;
            setTimeout(() => {
                const freq = 700 + Math.random() * 1400;
                const osc = ac.createOscillator();
                const g = ac.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, ac.currentTime);
                osc.frequency.exponentialRampToValueAtTime(freq * 0.6, ac.currentTime + 0.07);
                const vol = 0.25 + Math.random() * 0.25;
                g.gain.setValueAtTime(vol, ac.currentTime);
                g.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.09);
                osc.connect(g); g.connect(out);
                osc.start(); osc.stop(ac.currentTime + 0.1);

                // Thêm tiếng "lăn" kim loại
                if (i % 4 === 0) {
                    const osc2 = ac.createOscillator();
                    const g2 = ac.createGain();
                    osc2.type = 'triangle';
                    osc2.frequency.setValueAtTime(freq * 1.5, ac.currentTime);
                    osc2.frequency.exponentialRampToValueAtTime(freq * 0.9, ac.currentTime + 0.05);
                    g2.gain.setValueAtTime(0.1, ac.currentTime);
                    g2.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + 0.06);
                    osc2.connect(g2); g2.connect(out);
                    osc2.start(); osc2.stop(ac.currentTime + 0.07);
                }
            }, delay);
        }
    }

    // ─────────────────────────────────────────
    // 🎲 HELPER: tự động loop reelTick trong khi quay
    // Trả về { stop } để gọi khi dừng
    // ─────────────────────────────────────────
    function startReelLoop(speedMs = 80) {
        let running = true;
        let interval;

        function tick() {
            if (!running) return;
            reelTick();
            interval = setTimeout(tick, speedMs);
        }
        tick();

        return {
            stop: () => {
                running = false;
                clearTimeout(interval);
            },
            setSpeed: (ms) => {
                speedMs = ms;
            }
        };
    }

    // Public API
    return {
        insertCoin,
        spin,
        reelTick,
        reelStop,
        win,
        lose,
        bigWin,
        coinDrop,
        startReelLoop,

        // Unlock AudioContext (gọi 1 lần sau user gesture nếu cần)
        unlock: () => getCtx()
    };
})();
