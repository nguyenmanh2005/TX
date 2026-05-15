/**
 * 🎵 GTLM AUDIO CORE - Generative Sound System
 * Tự động tạo âm thanh bằng Web Audio API
 */
const GTLMAudio = (() => {
    let ctx = null;

    function getCtx() {
        if (!ctx) ctx = new (window.AudioContext || window.webkitAudioContext)();
        if (ctx.state === 'suspended') ctx.resume();
        return ctx;
    }

    function createGain(vol = 0.5) {
        const g = getCtx().createGain();
        g.gain.value = vol;
        g.connect(getCtx().destination);
        return g;
    }

    return {
        // Tiếng Click (Nút bấm)
        playClick: () => {
            const ac = getCtx();
            const out = createGain(0.3);
            const osc = ac.createOscillator();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(1200, ac.currentTime);
            osc.frequency.exponentialRampToValueAtTime(400, ac.currentTime + 0.1);
            out.gain.exponentialRampToValueAtTime(0.01, ac.currentTime + 0.1);
            osc.connect(out);
            osc.start(); osc.stop(ac.currentTime + 0.1);
        },

        // Tiếng Thắng (Win Arpeggio)
        playWin: () => {
            const ac = getCtx();
            const out = createGain(0.4);
            const notes = [523.25, 659.25, 783.99, 1046.50]; // C5, E5, G5, C6
            notes.forEach((freq, i) => {
                setTimeout(() => {
                    const osc = ac.createOscillator();
                    const g = ac.createGain();
                    osc.type = 'triangle';
                    osc.frequency.value = freq;
                    g.gain.setValueAtTime(0.3, ac.currentTime);
                    g.gain.exponentialRampToValueAtTime(0.01, ac.currentTime + 0.3);
                    osc.connect(g); g.connect(out);
                    osc.start(); osc.stop(ac.currentTime + 0.35);
                }, i * 100);
            });
        },

        // Tiếng Thua (Descend)
        playLose: () => {
            const ac = getCtx();
            const out = createGain(0.4);
            const osc = ac.createOscillator();
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(300, ac.currentTime);
            osc.frequency.exponentialRampToValueAtTime(100, ac.currentTime + 0.5);
            out.gain.linearRampToValueAtTime(0, ac.currentTime + 0.5);
            osc.connect(out);
            osc.start(); osc.stop(ac.currentTime + 0.5);
        },

        // Tiếng Đồng Xu (Chip/Coin)
        playCoin: () => {
            const ac = getCtx();
            const out = createGain(0.4);
            const osc = ac.createOscillator();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(1500, ac.currentTime);
            osc.frequency.exponentialRampToValueAtTime(1800, ac.currentTime + 0.05);
            out.gain.exponentialRampToValueAtTime(0.01, ac.currentTime + 0.15);
            osc.connect(out);
            osc.start(); osc.stop(ac.currentTime + 0.15);
        },

        // Tiếng Xào Bài (Card shuffle/flip)
        playCard: () => {
            const ac = getCtx();
            const out = createGain(0.2);
            const bufferSize = ac.sampleRate * 0.1;
            const buffer = ac.createBuffer(1, bufferSize, ac.sampleRate);
            const data = buffer.getChannelData(0);
            for (let i = 0; i < bufferSize; i++) {
                data[i] = (Math.random() * 2 - 1) * (1 - i / bufferSize);
            }
            const source = ac.createBufferSource();
            source.buffer = buffer;
            const filter = ac.createBiquadFilter();
            filter.type = 'lowpass';
            filter.frequency.value = 1500;
            source.connect(filter); filter.connect(out);
            source.start();
        },

        // Tiếng Xúc Xắc (Dice roll)
        playDice: () => {
            const ac = getCtx();
            const out = createGain(0.3);
            for (let i = 0; i < 3; i++) {
                setTimeout(() => {
                    const osc = ac.createOscillator();
                    osc.type = 'square';
                    osc.frequency.setValueAtTime(100 + Math.random() * 50, ac.currentTime);
                    const g = ac.createGain();
                    g.gain.setValueAtTime(0.2, ac.currentTime);
                    g.gain.exponentialRampToValueAtTime(0.01, ac.currentTime + 0.05);
                    osc.connect(g); g.connect(out);
                    osc.start(); osc.stop(ac.currentTime + 0.06);
                }, i * 60);
            }
        }
    };
})();
