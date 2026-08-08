/**
 * Global Sound FX & Audio Hub (Web Audio API Synthesizer)
 * [NEW FILE] - Hệ thống âm thanh toàn cục cho Web-Game GTLM
 * Hoạt động độc lập, không ghi đè lên các file JS/CSS cũ
 */

const SoundFXHub = (function() {
    let audioCtx = null;
    let isMuted = false;
    let widgetEl = null;

    // Khởi tạo AudioContext khi người dùng tương tác lần đầu
    function initContext() {
        if (!audioCtx) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) {
                audioCtx = new AudioContextClass();
            }
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        return audioCtx;
    }

    // Đọc trạng thái Mute từ localStorage
    function loadSettings() {
        const savedMute = localStorage.getItem('gtlm_sound_muted');
        isMuted = (savedMute === 'true');
    }

    // Lưu trạng thái Mute vào localStorage
    function saveSettings() {
        localStorage.setItem('gtlm_sound_muted', isMuted ? 'true' : 'false');
        updateWidgetUI();
    }

    // Phát âm thanh đơn tần số (Tone Synthesizer)
    function playTone(freq, type, duration, startTimeOffset = 0, volume = 0.25) {
        if (isMuted) return;
        const ctx = initContext();
        if (!ctx) return;

        try {
            const now = ctx.currentTime + startTimeOffset;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = type; // 'sine', 'square', 'sawtooth', 'triangle'
            osc.frequency.setValueAtTime(freq, now);

            gain.gain.setValueAtTime(volume, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + duration);

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start(now);
            osc.stop(now + duration);
        } catch (e) {
            console.error('Audio synth error:', e);
        }
    }

    // Hiệu ứng 1: Nổ Hũ Jackpot (Fanfare hoàng gia rực rỡ + xu rơi)
    function playJackpotSound() {
        if (isMuted) return;
        initContext();
        triggerWaveAnimation();

        // Chuỗi hợp âm chiến thắng hoàng gia
        const notes = [523.25, 659.25, 783.99, 1046.50, 783.99, 1046.50, 1318.51, 1567.98];
        notes.forEach((freq, idx) => {
            playTone(freq, 'sawtooth', 0.35, idx * 0.12, 0.3);
            playTone(freq * 1.5, 'triangle', 0.25, idx * 0.12, 0.15);
        });
    }

    // Hiệu ứng 2: Thắng Xổ Số Cộng Đồng / Thắng Đậm
    function playLotteryWinSound() {
        if (isMuted) return;
        initContext();
        triggerWaveAnimation();

        // Chuỗi chuông kim loại rộn ràng
        playTone(587.33, 'sine', 0.4, 0, 0.3);    // D5
        playTone(880.00, 'sine', 0.4, 0.15, 0.3); // A5
        playTone(1174.66, 'sine', 0.6, 0.3, 0.35);// D6
        playTone(1760.00, 'triangle', 0.8, 0.45, 0.25); // A6
    }

    // Hiệu ứng 3: Vòng Quay Lucky Wheel (Tiếng quay tạch tạch tạch + chuông báo)
    function playLuckyWheelSpinSound() {
        if (isMuted) return;
        initContext();

        for (let i = 0; i < 12; i++) {
            playTone(800 + (i * 40), 'square', 0.05, i * 0.08, 0.1);
        }
        playTone(1318.51, 'sine', 0.5, 1.0, 0.3);
    }

    // Hiệu ứng 4: Boss Hắc Long Thần Gầm Rú (Âm trầm sấm sét)
    function playBossRoarSound() {
        if (isMuted) return;
        initContext();
        triggerWaveAnimation();

        playTone(80, 'sawtooth', 1.2, 0, 0.4);
        playTone(60, 'square', 1.5, 0.1, 0.45);
        playTone(45, 'sawtooth', 1.8, 0.3, 0.5);
    }

    // Hiệu ứng 5: Thách Đấu PvP (Tiếng kèn / đụng độ chiến trường)
    function playPvpChallengeSound() {
        if (isMuted) return;
        initContext();
        triggerWaveAnimation();

        playTone(440.00, 'square', 0.25, 0, 0.35);    // A4
        playTone(440.00, 'square', 0.25, 0.2, 0.35);  // A4
        playTone(659.25, 'sawtooth', 0.6, 0.4, 0.4);  // E5
    }

    // Hiệu ứng 6: Nhận tin nhắn / Phản hồi Bot
    function playMessagePopSound() {
        if (isMuted) return;
        initContext();

        playTone(900, 'sine', 0.1, 0, 0.2);
        playTone(1200, 'sine', 0.15, 0.06, 0.2);
    }

    // Cập nhật giao diện nút điều khiển âm thanh floating widget
    function updateWidgetUI() {
        if (!widgetEl) return;
        const iconEl = widgetEl.querySelector('.sound-icon');
        const textEl = widgetEl.querySelector('.sound-label');
        if (isMuted) {
            iconEl.textContent = '🔇';
            textEl.textContent = 'Âm thanh: Tắt';
            widgetEl.classList.add('muted');
        } else {
            iconEl.textContent = '🔊';
            textEl.textContent = 'Âm thanh: Bật';
            widgetEl.classList.remove('muted');
        }
    }

    // Kích hoạt hiệu ứng sóng âm nhấp nháy khi có âm thanh phát
    function triggerWaveAnimation() {
        if (!widgetEl) return;
        widgetEl.classList.remove('playing');
        void widgetEl.offsetWidth; // Trigger reflow
        widgetEl.classList.add('playing');
    }

    // Tạo Floating Audio Control Widget ở góc dưới bên trái màn hình
    function createAudioWidget() {
        if (document.getElementById('gtlm-sound-widget')) return;

        widgetEl = document.createElement('div');
        widgetEl.id = 'gtlm-sound-widget';
        widgetEl.className = 'sound-fx-widget';
        widgetEl.title = 'Bấm để Bật/Tắt hiệu ứng âm thanh GTLM';
        widgetEl.innerHTML = `
            <span class="sound-icon">🔊</span>
            <span class="sound-label">Âm thanh: Bật</span>
            <div class="sound-waves">
                <span class="wave w1"></span>
                <span class="wave w2"></span>
                <span class="wave w3"></span>
            </div>
        `;

        widgetEl.addEventListener('click', () => {
            initContext();
            isMuted = !isMuted;
            saveSettings();
            if (!isMuted) {
                playMessagePopSound();
            }
        });

        document.body.appendChild(widgetEl);
        updateWidgetUI();
    }

    // Bắt đầu tiến trình polling kiểm tra sự kiện âm thanh và AI Bot
    function startSmartPolling() {
        setInterval(() => {
            fetch('api_bot_smart_chat.php?action=scan')
                .then(res => res.json())
                .then(data => {
                    if (!data.success) return;
                    
                    // Phát âm thanh khi có sự kiện
                    if (data.sound_events && data.sound_events.length > 0) {
                        data.sound_events.forEach(evt => {
                            if (evt.type === 'jackpot') {
                                playJackpotSound();
                            } else if (evt.type === 'pvp_challenge') {
                                playPvpChallengeSound();
                            }
                        });
                    }
                })
                .catch(err => console.debug('Sound & AI poll skip'));
        }, 12000); // Polling mỗi 12 giây
    }

    // Tự động khởi tạo khi DOM sẵn sàng
    document.addEventListener('DOMContentLoaded', () => {
        loadSettings();
        createAudioWidget();
        startSmartPolling();
    });

    // Public API để các game khác có thể gọi trực tiếp
    return {
        playJackpot: playJackpotSound,
        playLotteryWin: playLotteryWinSound,
        playLuckySpin: playLuckyWheelSpinSound,
        playBossRoar: playBossRoarSound,
        playPvpChallenge: playPvpChallengeSound,
        playPvpHorn: playPvpChallengeSound,
        playPop: playMessagePopSound,
        toggleMute: () => {
            isMuted = !isMuted;
            saveSettings();
        }
    };
})();
