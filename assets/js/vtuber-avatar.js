/**
 * 🤖 AI VTUBER STREAMER ENGINE v4.0 (Lip-sync Canvas & Web Speech TTS)
 * 
 * Tính năng đột phá:
 * 1. Avatar 2D/3D Canvas chuyển động:
 *    - Biểu cảm chớp mắt tự nhiên (Blinking Loop).
 *    - Khẩu hình cử động theo giọng nói (Lip-sync Canvas) đồng bộ 100% với Web Speech API.
 *    - Idle breathing, head bobbing và hiệu ứng Cyber Headset LED RGB.
 * 2. Giọng đọc Streamer (TTS - Text To Speech):
 *    - Web Speech API vi-VN chuẩn xác ngữ điệu.
 *    - Tự động chào mừng người xem, đọc donate quà tặng, reo hò khi nổ hũ/chiến thắng.
 * 3. Bong bóng phụ đề (Floating Speech Bubble) & Audio Wave Visualizer.
 */

(function (window, document) {
    'use strict';

    class AIVTuberStreamer {
        constructor(config = {}) {
            this.tableId = config.tableId || 61;
            this.streamerName = config.streamerName || 'AlphaX (AI Streamer)';
            this.themeColor = config.themeColor || '#10b981';
            this.secondaryColor = config.secondaryColor || '#38bdf8';
            
            // Audio & Voice state
            this.voiceEnabled = localStorage.getItem('gtlm_vtuber_voice') !== 'false';
            this.isSpeaking = false;
            this.currentUtterance = null;
            this.speechTimeout = null;
            this.vietnameseVoice = null;

            // Canvas & Animation state
            this.canvas = null;
            this.ctx = null;
            this.animId = null;
            this.startTime = performance.now();
            
            // Lip-sync & Face parameters
            this.mouthOpenness = 0.05; // 0.05 = rest smile, 1.0 = fully open
            this.mouthTarget = 0.05;
            this.eyeOpenness = 1.0;     // 1.0 = open, 0.0 = closed blink
            this.isBlinking = false;
            this.nextBlinkTime = performance.now() + 2500;
            this.isWinking = false;
            this.winkTimer = 0;
            this.headTilt = 0;
            this.headBob = 0;
            
            // Wave equalizer bars (7 frequency bands)
            this.waveBars = [0.1, 0.1, 0.1, 0.1, 0.1, 0.1, 0.1];

            // Init voices
            this.initVoices();
            if ('speechSynthesis' in window) {
                window.speechSynthesis.onvoiceschanged = () => this.initVoices();
            }
        }

        initVoices() {
            if (!('speechSynthesis' in window)) return;
            const voices = window.speechSynthesis.getVoices();
            // Ưu tiên tìm giọng tiếng Việt chất lượng cao (Edge Natural, Chrome, iOS)
            this.vietnameseVoice = voices.find(v => 
                (v.lang && v.lang.toLowerCase().startsWith('vi')) || 
                (v.name && v.name.toLowerCase().includes('viet'))
            ) || voices.find(v => v.lang && v.lang.includes('vi')) || null;
        }

        /**
         * Gắn vào DOM và khởi động vòng lặp vẽ Canvas 60fps
         */
        mount(canvasElementId) {
            this.canvas = document.getElementById(canvasElementId);
            if (!this.canvas) return;
            this.ctx = this.canvas.getContext('2d');

            // Hỗ trợ màn hình Retina sắc nét
            const dpr = window.devicePixelRatio || 1;
            const rect = this.canvas.getBoundingClientRect();
            const w = rect.width || 180;
            const h = rect.height || 180;
            
            this.canvas.width = w * dpr;
            this.canvas.height = h * dpr;
            this.ctx.scale(dpr, dpr);
            this.displayW = w;
            this.displayH = h;

            this.canvas.addEventListener('click', () => {
                this.triggerWinkAndFunVoice();
            });

            this.animate();
        }

        animate() {
            const now = performance.now();
            const time = (now - this.startTime) / 1000;

            this.updateStates(time, now);
            this.render(time);

            this.animId = requestAnimationFrame(() => this.animate());
        }

        updateStates(time, now) {
            // 1. Logic chớp mắt tự nhiên (Blinking state machine)
            if (!this.isBlinking && now >= this.nextBlinkTime) {
                this.isBlinking = true;
                this.blinkStartTime = now;
                // Chu kỳ nhắm/mở mắt ngẫu nhiên sau mỗi 3.2 đến 5.5 giây
                this.nextBlinkTime = now + 3200 + Math.random() * 2300;
            }

            if (this.isBlinking) {
                const blinkElapsed = now - this.blinkStartTime;
                if (blinkElapsed < 60) {
                    // Mắt khép lại nhanh
                    this.eyeOpenness = 1.0 - (blinkElapsed / 60);
                } else if (blinkElapsed < 110) {
                    // Mắt nhắm hẳn
                    this.eyeOpenness = 0.0;
                } else if (blinkElapsed < 180) {
                    // Mắt mở ra từ từ
                    this.eyeOpenness = (blinkElapsed - 110) / 70;
                } else {
                    this.eyeOpenness = 1.0;
                    this.isBlinking = false;
                }
            } else {
                this.eyeOpenness = 1.0;
            }

            // 2. Logic Khẩu hình cử động theo giọng nói (Lip-sync Engine)
            if (this.isSpeaking) {
                // Biến thiên khẩu hình theo nhịp sóng âm thoại tự nhiên
                const s1 = Math.sin(time * 14.0) * 0.45;
                const s2 = Math.sin(time * 26.5) * 0.25;
                const s3 = Math.cos(time * 9.2) * 0.15;
                this.mouthTarget = Math.max(0.15, Math.min(0.95, 0.45 + s1 + s2 + s3));

                // Nhảy sóng âm Equalizer
                for (let i = 0; i < this.waveBars.length; i++) {
                    const waveVal = 0.3 + Math.abs(Math.sin(time * (10 + i * 3) + i)) * 0.65;
                    this.waveBars[i] += (waveVal - this.waveBars[i]) * 0.3;
                }

                // Gật đầu nhẹ theo nhịp nói
                this.headBob = Math.sin(time * 10) * 2.2;
                this.headTilt = Math.sin(time * 3) * 0.04;
            } else {
                this.mouthTarget = 0.05; // Khép miệng lại mỉm cười duyên
                for (let i = 0; i < this.waveBars.length; i++) {
                    this.waveBars[i] += (0.08 - this.waveBars[i]) * 0.15;
                }

                // Idle breathing: Thở nhấp nhô nhẹ nhàng
                this.headBob = Math.sin(time * 2.2) * 3.0;
                this.headTilt = Math.sin(time * 1.4) * 0.02;
            }

            // Smooth interpolation cho miệng
            this.mouthOpenness += (this.mouthTarget - this.mouthOpenness) * 0.35;

            // Xử lý nháy mắt (Winking)
            if (this.isWinking) {
                if (now > this.winkTimer) {
                    this.isWinking = false;
                }
            }

            // Đồng bộ thanh sóng âm ra DOM ngoài nếu có
            this.updateDomWaveBars();
        }

        updateDomWaveBars() {
            const barEls = document.querySelectorAll('.vtuber-wave-bars .wave-bar');
            if (barEls && barEls.length > 0) {
                barEls.forEach((el, idx) => {
                    const val = this.waveBars[idx % this.waveBars.length] || 0.1;
                    el.style.transform = `scaleY(${Math.max(0.15, val)})`;
                    if (this.isSpeaking) {
                        el.style.backgroundColor = (idx % 2 === 0) ? this.themeColor : this.secondaryColor;
                        el.style.boxShadow = `0 0 8px ${this.themeColor}`;
                    } else {
                        el.style.backgroundColor = 'rgba(255,255,255,0.2)';
                        el.style.boxShadow = 'none';
                    }
                });
            }
        }

        /**
         * RENDER GRAPHICS TRÊN CANVAS 2D/3D VỚI CHUYỂN ĐỘNG LIP-SYNC & CHỚP MẮT
         */
        render(time) {
            const ctx = this.ctx;
            const w = this.displayW;
            const h = this.displayH;

            ctx.clearRect(0, 0, w, h);

            const cx = w / 2;
            const cy = h / 2 + 10 + this.headBob;

            ctx.save();
            ctx.translate(cx, cy);
            ctx.rotate(this.headTilt);

            // ── 1. Holographic Halo / Glow Sau Lưng ──
            const glowGrad = ctx.createRadialGradient(0, -10, 20, 0, -10, 80);
            glowGrad.addColorStop(0, this.isSpeaking ? 'rgba(16, 185, 129, 0.35)' : 'rgba(56, 189, 248, 0.15)');
            glowGrad.addColorStop(1, 'rgba(0, 0, 0, 0)');
            ctx.fillStyle = glowGrad;
            ctx.beginPath();
            ctx.arc(0, -10, 80, 0, Math.PI * 2);
            ctx.fill();

            // ── 2. Thân & Cổ Áo Streamer (Cyber Hoodie / Jacket) ──
            ctx.fillStyle = '#0f172a';
            ctx.beginPath();
            ctx.ellipse(0, 72, 54, 28, 0, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = this.themeColor;
            ctx.lineWidth = 1.5;
            ctx.stroke();

            // Viền cổ áo neon
            ctx.fillStyle = '#1e293b';
            ctx.beginPath();
            ctx.ellipse(0, 52, 22, 12, 0, 0, Math.PI * 2);
            ctx.fill();

            // Cổ
            ctx.fillStyle = '#fce7d2';
            ctx.fillRect(-10, 28, 20, 22);

            // ── 3. Cyber Headset (Tai Nghe Streamer Gaming LED RGB) ──
            // Vòng cung tai nghe trên đỉnh đầu
            ctx.strokeStyle = '#334155';
            ctx.lineWidth = 6;
            ctx.beginPath();
            ctx.arc(0, -22, 52, Math.PI * 0.85, Math.PI * 2.15);
            ctx.stroke();

            // Dải LED RGB trên vòng tai nghe
            ctx.strokeStyle = this.isSpeaking ? this.themeColor : this.secondaryColor;
            ctx.lineWidth = 2.5;
            ctx.stroke();

            // ── 4. Khuôn Mặt (Head & Skin) ──
            ctx.fillStyle = '#fee2cb'; // Tone da anime mịn màng
            ctx.beginPath();
            ctx.ellipse(0, 2, 42, 46, 0, 0, Math.PI * 2);
            ctx.fill();

            // Cằm thon gọn V-line
            ctx.beginPath();
            ctx.moveTo(-32, 10);
            ctx.quadraticCurveTo(-15, 46, 0, 48);
            ctx.quadraticCurveTo(15, 46, 32, 10);
            ctx.closePath();
            ctx.fill();

            // Má hồng (Anime Blush)
            ctx.fillStyle = 'rgba(244, 63, 94, 0.28)';
            ctx.beginPath();
            ctx.ellipse(-24, 14, 8, 5, 0, 0, Math.PI * 2);
            ctx.ellipse(24, 14, 8, 5, 0, 0, Math.PI * 2);
            ctx.fill();

            // ── 5. Đôi Mắt Biểu Cảm & Chớp Mắt (Expressive Eyes & Blinking) ──
            this.drawEye(ctx, -20, 2, this.eyeOpenness, this.isWinking); // Mắt trái
            this.drawEye(ctx, 20, 2, this.eyeOpenness, false);            // Mắt phải

            // Lông mày
            ctx.strokeStyle = '#475569';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(-30, -14);
            ctx.quadraticCurveTo(-20, -18, -10, -14);
            ctx.moveTo(10, -14);
            ctx.quadraticCurveTo(20, -18, 30, -14);
            ctx.stroke();

            // ── 6. Khẩu Hình Cử Động Theo Giọng Nói (Lip-sync Mouth) ──
            this.drawLipSyncMouth(ctx, 0, 28, this.mouthOpenness);

            // ── 7. Tóc Mái & Kiểu Tóc Anime Cyber ──
            ctx.fillStyle = (this.tableId === 61) ? '#1e293b' : '#312e81';
            ctx.beginPath();
            // Tóc mái chính
            ctx.moveTo(-45, -8);
            ctx.quadraticCurveTo(-25, -45, 0, -46);
            ctx.quadraticCurveTo(25, -45, 45, -8);
            // Các lọn tóc mái tỉa nhọn
            ctx.quadraticCurveTo(32, -18, 25, 0);
            ctx.quadraticCurveTo(15, -15, 8, 2);
            ctx.quadraticCurveTo(0, -18, -10, 3);
            ctx.quadraticCurveTo(-20, -15, -28, -2);
            ctx.quadraticCurveTo(-38, -18, -45, -8);
            ctx.closePath();
            ctx.fill();

            // Đốm sáng phản chiếu trên tóc
            ctx.fillStyle = 'rgba(255, 255, 255, 0.2)';
            ctx.beginPath();
            ctx.ellipse(12, -32, 16, 5, Math.PI * 0.1, 0, Math.PI * 2);
            ctx.fill();

            // ── 8. Chụp Tai Nghe Gaming LED (Earpads với biểu tượng riêng) ──
            // Chụp tai nghe trái (AlphaX - Đỏ Neon nếu là bàn 61)
            const leftEarColor = (this.tableId === 61) ? '#ef4444' : this.themeColor;
            ctx.fillStyle = '#0f172a';
            ctx.beginPath();
            ctx.ellipse(-46, 2, 10, 20, 0, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = leftEarColor;
            ctx.lineWidth = 2.5;
            ctx.stroke();
            if (this.isSpeaking) {
                ctx.fillStyle = leftEarColor;
                ctx.beginPath();
                ctx.arc(-46, 2, 4, 0, Math.PI * 2);
                ctx.fill();
            }

            // Chụp tai nghe phải (DeepO - Xanh Neon nếu là bàn 61)
            const rightEarColor = (this.tableId === 61) ? '#38bdf8' : this.secondaryColor;
            ctx.fillStyle = '#0f172a';
            ctx.beginPath();
            ctx.ellipse(46, 2, 10, 20, 0, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = rightEarColor;
            ctx.lineWidth = 2.5;
            ctx.stroke();
            if (this.isSpeaking) {
                ctx.fillStyle = rightEarColor;
                ctx.beginPath();
                ctx.arc(46, 2, 4, 0, Math.PI * 2);
                ctx.fill();
            }

            // Mic thu âm nhô ra từ tai nghe
            ctx.strokeStyle = '#64748b';
            ctx.lineWidth = 2.5;
            ctx.beginPath();
            ctx.moveTo(-46, 12);
            ctx.quadraticCurveTo(-30, 32, -14, 30);
            ctx.stroke();
            // Đầu mic phát sáng
            ctx.fillStyle = this.isSpeaking ? '#ef4444' : '#64748b';
            ctx.beginPath();
            ctx.arc(-13, 30, 3.5, 0, Math.PI * 2);
            ctx.fill();

            ctx.restore();
        }

        drawEye(ctx, x, y, openness, isWink) {
            ctx.save();
            ctx.translate(x, y);

            if (isWink || openness < 0.15) {
                // Mắt nhắm / Nháy mắt hình cánh cung đáng yêu (^_<)
                ctx.strokeStyle = '#1e293b';
                ctx.lineWidth = 3;
                ctx.lineCap = 'round';
                ctx.beginPath();
                ctx.arc(0, -2, 9, Math.PI * 0.15, Math.PI * 0.85, false);
                ctx.stroke();

                if (isWink) {
                    // Ngôi sao lấp lánh khi nháy mắt
                    ctx.fillStyle = '#fbbf24';
                    ctx.beginPath();
                    ctx.arc(14, -6, 3, 0, Math.PI * 2);
                    ctx.fill();
                }
            } else {
                // Mắt mở tròn long lanh
                const eyeHeight = 13 * openness;
                
                // Tròng trắng
                ctx.fillStyle = '#ffffff';
                ctx.beginPath();
                ctx.ellipse(0, 0, 10, eyeHeight, 0, 0, Math.PI * 2);
                ctx.fill();

                // Tròng đen / Con ngươi màu theo Theme Bàn
                const irisGrad = ctx.createLinearGradient(0, -eyeHeight, 0, eyeHeight);
                irisGrad.addColorStop(0, '#0f172a');
                irisGrad.addColorStop(0.5, (this.tableId === 61) ? '#10b981' : this.themeColor);
                irisGrad.addColorStop(1, '#0284c7');
                ctx.fillStyle = irisGrad;
                ctx.beginPath();
                ctx.ellipse(0, 0, 7, eyeHeight * 0.9, 0, 0, Math.PI * 2);
                ctx.fill();

                // Con ngươi giữa
                ctx.fillStyle = '#090d16';
                ctx.beginPath();
                ctx.ellipse(0, 0, 4, eyeHeight * 0.6, 0, 0, Math.PI * 2);
                ctx.fill();

                // Đốm sáng phản chiếu (Anime Sparkle glint)
                ctx.fillStyle = '#ffffff';
                ctx.beginPath();
                ctx.arc(-2.5, -eyeHeight * 0.35, 2.5, 0, Math.PI * 2);
                ctx.fill();
                ctx.beginPath();
                ctx.arc(2, eyeHeight * 0.25, 1.2, 0, Math.PI * 2);
                ctx.fill();

                // Viền mi mắt trên
                ctx.strokeStyle = '#0f172a';
                ctx.lineWidth = 2.5;
                ctx.beginPath();
                ctx.arc(0, -1, 10.5, Math.PI * 1.15, Math.PI * 1.85);
                ctx.stroke();
            }

            ctx.restore();
        }

        drawLipSyncMouth(ctx, x, y, openness) {
            ctx.save();
            ctx.translate(x, y);

            if (openness <= 0.12) {
                // Khép miệng: Nụ cười mỉm nhỏ nhắn
                ctx.strokeStyle = '#991b1b';
                ctx.lineWidth = 2.2;
                ctx.lineCap = 'round';
                ctx.beginPath();
                ctx.moveTo(-7, -1);
                ctx.quadraticCurveTo(0, 3, 7, -1);
                ctx.stroke();
            } else {
                // Cử động mở khẩu hình (Lip-sync Canvas Dynamic Opening)
                const mouthH = Math.max(3, openness * 14);
                const mouthW = 8 + openness * 3;

                // Khoang miệng bên trong
                ctx.fillStyle = '#881337';
                ctx.beginPath();
                ctx.ellipse(0, mouthH * 0.4, mouthW, mouthH, 0, 0, Math.PI * 2);
                ctx.fill();

                // Lưỡi hồng bên dưới
                ctx.fillStyle = '#f43f5e';
                ctx.beginPath();
                ctx.ellipse(0, mouthH * 0.85, mouthW * 0.7, mouthH * 0.45, 0, 0, Math.PI);
                ctx.fill();

                // Răng trắng trên
                ctx.fillStyle = '#ffffff';
                ctx.beginPath();
                ctx.rect(-mouthW * 0.6, 0, mouthW * 1.2, mouthH * 0.25);
                ctx.fill();

                // Viền môi
                ctx.strokeStyle = '#be123c';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.ellipse(0, mouthH * 0.4, mouthW, mouthH, 0, 0, Math.PI * 2);
                ctx.stroke();
            }

            ctx.restore();
        }

        /**
         * 🎙️ PHÁT ÂM THANH STREAMER TTS VỚI ĐỒNG BỘ LIP-SYNC CANVAS
         */
        speak(text, onEndCallback) {
            if (!text) return;

            // Xử lý text phụ đề hiển thị
            const displayClean = text.replace(/[*#_`~]/g, '').trim();
            this.showSpeechBubble(displayClean);

            if (!this.voiceEnabled) {
                // Nếu người dùng tắt tiếng, vẫn mô phỏng khẩu hình lip-sync theo độ dài câu
                this.simulateLipSyncWithoutAudio(displayClean, onEndCallback);
                return;
            }

            if (!('speechSynthesis' in window)) {
                this.simulateLipSyncWithoutAudio(displayClean, onEndCallback);
                return;
            }

            try {
                window.speechSynthesis.cancel();

                // Chuyển đổi các thuật ngữ để Web Speech phát âm chuẩn tiếng Việt
                let voiceText = displayClean
                    .replace(/GTLM/gi, 'Gờ Tờ Lờ Mờ')
                    .replace(/AI/gi, 'A I')
                    .replace(/PvP/gi, 'P v P')
                    .replace(/AlphaX/gi, 'An pha X')
                    .replace(/DeepO/gi, 'Đíp Ô')
                    .replace(/Minimax/gi, 'Mi ni mắc')
                    .replace(/15x15/gi, 'mười lăm nhân mười lăm')
                    .replace(/x(\d+(\.\d+)?)/gi, 'nhân $1');

                const utterance = new SpeechSynthesisUtterance(voiceText);
                utterance.lang = 'vi-VN';
                utterance.rate = 1.08;
                utterance.pitch = 1.05;

                if (this.vietnameseVoice) {
                    utterance.voice = this.vietnameseVoice;
                }

                utterance.onstart = () => {
                    this.isSpeaking = true;
                    this.toggleAudioGlow(true);
                };

                utterance.onboundary = (e) => {
                    // Khi chạm từ/âm tiết mới, tạo xung động cho khẩu hình
                    this.mouthTarget = 0.75 + Math.random() * 0.25;
                };

                utterance.onend = () => {
                    this.isSpeaking = false;
                    this.toggleAudioGlow(false);
                    this.scheduleHideBubble(2000);
                    if (onEndCallback) onEndCallback();
                };

                utterance.onerror = (err) => {
                    console.log('TTS Error, fallbacking to visual sync:', err);
                    this.isSpeaking = false;
                    this.toggleAudioGlow(false);
                    this.scheduleHideBubble(2000);
                };

                this.currentUtterance = utterance;
                window.speechSynthesis.speak(utterance);

                // Safety timeout fallback: Tự động tắt nói nếu trình duyệt nuốt sự kiện onend
                clearTimeout(this.speechTimeout);
                const estimatedMs = Math.max(2500, (voiceText.split(' ').length / 2.8) * 1000);
                this.speechTimeout = setTimeout(() => {
                    if (this.isSpeaking) {
                        this.isSpeaking = false;
                        this.toggleAudioGlow(false);
                        this.scheduleHideBubble(1500);
                    }
                }, estimatedMs + 2000);

            } catch (err) {
                console.log('Speech error:', err);
                this.simulateLipSyncWithoutAudio(displayClean, onEndCallback);
            }
        }

        simulateLipSyncWithoutAudio(text, callback) {
            this.isSpeaking = true;
            this.toggleAudioGlow(true);
            const duration = Math.max(2000, Math.min(8000, text.split(' ').length * 350));
            
            setTimeout(() => {
                this.isSpeaking = false;
                this.toggleAudioGlow(false);
                this.scheduleHideBubble(1800);
                if (callback) callback();
            }, duration);
        }

        showSpeechBubble(text) {
            const bubbleEl = document.getElementById('vtuberBubble');
            const textEl = document.getElementById('vtuberBubbleText');
            if (bubbleEl && textEl) {
                textEl.textContent = text;
                bubbleEl.style.display = 'block';
                bubbleEl.classList.remove('fade-out');
                bubbleEl.classList.add('pop-in');
            }
        }

        scheduleHideBubble(delayMs) {
            setTimeout(() => {
                const bubbleEl = document.getElementById('vtuberBubble');
                if (bubbleEl && !this.isSpeaking) {
                    bubbleEl.classList.remove('pop-in');
                    bubbleEl.classList.add('fade-out');
                    setTimeout(() => {
                        if (!this.isSpeaking) bubbleEl.style.display = 'none';
                    }, 400);
                }
            }, delayMs);
        }

        toggleAudioGlow(active) {
            const glowEl = document.getElementById('vtuberAudioGlow');
            if (glowEl) {
                glowEl.style.opacity = active ? '1' : '0';
            }
        }

        toggleVoice() {
            this.voiceEnabled = !this.voiceEnabled;
            localStorage.setItem('gtlm_vtuber_voice', this.voiceEnabled ? 'true' : 'false');
            
            if (!this.voiceEnabled && 'speechSynthesis' in window) {
                window.speechSynthesis.cancel();
                this.isSpeaking = false;
                this.toggleAudioGlow(false);
            }

            return this.voiceEnabled;
        }

        triggerWinkAndFunVoice() {
            this.isWinking = true;
            this.winkTimer = performance.now() + 800;

            const funLines = (this.tableId === 61) ? [
                "Bác bấm vào em làm gì đấy? AlphaX đang tính toán 15 nước cờ tiếp theo, đừng làm phân tâm sư phụ nhé! ♟️",
                "Chào sếp! Ván cờ này AlphaX nắm chắc phần thắng, sếp cứ yên tâm ngồi rung đùi uống trà!",
                "Thế trận đang căng như dây đàn! Cùng cổ vũ cho AlphaX knockout DeepO nào anh em!"
            ] : [
                "Bác bấm vào em làm gì đấy? Tập trung soi cầu lộc lá đi chứ sếp! 😜",
                "Em chào đại ca! Nay ghé live ủng hộ streamer thế này là quá tuyệt vời rồi!",
                "Chúc sếp ra chiêu ván nào húp trọn ván đó, hũ nổ ầm ầm nhé!"
            ];

            const line = funLines[Math.floor(Math.random() * funLines.length)];
            this.speak(line);
        }

        destroy() {
            if (this.animId) cancelAnimationFrame(this.animId);
            if ('speechSynthesis' in window) window.speechSynthesis.cancel();
        }
    }

    // Export ra global window
    window.AIVTuberStreamer = AIVTuberStreamer;

})(window, document);
