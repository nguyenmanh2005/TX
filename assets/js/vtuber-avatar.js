/**
 * 👑 BOT QUEEN GTLM - AI ASSISTANT & VTUBER ENGINE v5.0
 * 
 * Hình tượng: Nữ hoàng trợ lý GTLM (Female AI Companion)
 * Tính năng:
 * - Avatar 2D Canvas Nữ Hoàng: Vương miện vàng 👑, tóc dài thướt tha, mắt to tròn chớp mắt tự nhiên.
 * - Khẩu hình Lip-sync đồng bộ giọng nói Web Speech API vi-VN.
 * - Trợ lý giải đáp thắc mắc, hướng dẫn người chơi, chào hỏi lễ phép và trêu đùa duyên dáng.
 * - Giao diện trực quan, dễ nhìn, dễ thao tác, không che khuất màn hình chơi game.
 */

(function (window, document) {
    'use strict';

    class QueenGTLMAvatar {
        constructor(config = {}) {
            this.tableId = config.tableId || 1;
            this.queenName = 'Queen GTLM 👑';
            
            // Audio state
            this.voiceEnabled = localStorage.getItem('gtlm_queen_voice') !== 'false';
            this.isSpeaking = false;
            this.currentUtterance = null;
            this.speechTimeout = null;
            this.femaleVoice = null;
            this.hasConfirmedFemaleVoice = false;
            this.currentAudio = null;

            // Canvas & Animation state
            this.canvas = null;
            this.ctx = null;
            this.animId = null;
            this.startTime = performance.now();
            
            // Lip-sync & Face parameters
            this.mouthOpenness = 0.05; // 0.05 = rest sweet smile, 1.0 = fully open
            this.mouthTarget = 0.05;
            this.eyeOpenness = 1.0;     // 1.0 = open, 0.0 = closed blink
            this.isBlinking = false;
            this.nextBlinkTime = performance.now() + 2800;
            this.isWinking = false;
            this.winkTimer = 0;
            this.headTilt = 0;
            this.headBob = 0;
            
            // Wave equalizer bars (7 gold frequency bands)
            this.waveBars = [0.1, 0.1, 0.1, 0.1, 0.1, 0.1, 0.1];

            // Init female voice
            this.initVoices();
            if ('speechSynthesis' in window) {
                window.speechSynthesis.onvoiceschanged = () => this.initVoices();
            }
        }

        initVoices() {
            if (!('speechSynthesis' in window)) return;
            const voices = window.speechSynthesis.getVoices();
            if (!voices || voices.length === 0) return;

            // KIỂM TRA & LOẠI TRỪ TUYỆT ĐỐI CÁC GIỌNG NAM (An, Nam, Male, NamMinh, Duc, Tuan, Minh)
            const isMaleName = (name) => {
                const lower = String(name).toLowerCase();
                return lower.includes(' an') || lower.includes('namminh') || lower.includes(' male') || lower.includes('nam') || lower.includes('minh') || lower.includes('duc') || lower.includes('tuan');
            };

            // Ưu tiên 1: Giọng Nữ tiếng Việt được định danh rõ (HoaiMy, Linh, Female, Nu, Lan, Mai)
            let femaleVi = voices.find(v => 
                (v.lang && v.lang.toLowerCase().startsWith('vi')) && 
                !isMaleName(v.name) &&
                (v.name.toLowerCase().includes('female') || v.name.toLowerCase().includes('hoaimy') || v.name.toLowerCase().includes('linh') || v.name.toLowerCase().includes('nu'))
            );

            // Ưu tiên 2: "Google tiếng Việt" trên Chrome (đây là giọng nữ chuẩn 100%)
            if (!femaleVi) {
                femaleVi = voices.find(v => 
                    (v.lang && v.lang.toLowerCase().startsWith('vi')) && 
                    v.name.toLowerCase().includes('google') &&
                    !isMaleName(v.name)
                );
            }

            if (femaleVi) {
                this.femaleVoice = femaleVi;
                this.hasConfirmedFemaleVoice = true;
            } else {
                this.femaleVoice = null;
                this.hasConfirmedFemaleVoice = false;
            }
        }

        mount(canvasElementId) {
            this.canvas = document.getElementById(canvasElementId);
            if (!this.canvas) return;
            this.ctx = this.canvas.getContext('2d');

            const dpr = window.devicePixelRatio || 1;
            // Cố định kích thước chuẩn 140x140 để đảm bảo luôn vẽ sắc nét kể cả khi ban đầu phần tử bị ẩn
            const w = 140;
            const h = 140;
            
            this.canvas.width = w * dpr;
            this.canvas.height = h * dpr;
            this.canvas.style.width = w + 'px';
            this.canvas.style.height = h + 'px';
            
            // Dùng setTransform để thiết lập ma trận tỉ lệ dpr, tránh bị scale cộng dồn nhiều lần
            this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            this.displayW = w;
            this.displayH = h;

            if (!this.hasClickListener) {
                this.canvas.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.triggerWinkAndSweetTalk();
                });
                this.hasClickListener = true;
            }

            if (!this.animId) {
                this.animate();
            }
        }

        animate() {
            const now = performance.now();
            const time = (now - this.startTime) / 1000;

            this.updateStates(time, now);
            this.render(time);

            this.animId = requestAnimationFrame(() => this.animate());
        }

        updateStates(time, now) {
            // 1. Chớp mắt tự nhiên (Natural Blinking Loop)
            if (!this.isBlinking && now >= this.nextBlinkTime) {
                this.isBlinking = true;
                this.blinkStartTime = now;
                this.nextBlinkTime = now + 3000 + Math.random() * 2500;
            }

            if (this.isBlinking) {
                const elapsed = now - this.blinkStartTime;
                if (elapsed < 60) {
                    this.eyeOpenness = 1.0 - (elapsed / 60);
                } else if (elapsed < 110) {
                    this.eyeOpenness = 0.0;
                } else if (elapsed < 180) {
                    this.eyeOpenness = (elapsed - 110) / 70;
                } else {
                    this.eyeOpenness = 1.0;
                    this.isBlinking = false;
                }
            } else {
                this.eyeOpenness = 1.0;
            }

            // 2. Khẩu hình cử động theo giọng nói (Lip-sync Engine)
            if (this.isSpeaking) {
                const s1 = Math.sin(time * 13.5) * 0.45;
                const s2 = Math.sin(time * 24.0) * 0.25;
                const s3 = Math.cos(time * 8.5) * 0.15;
                this.mouthTarget = Math.max(0.12, Math.min(0.92, 0.45 + s1 + s2 + s3));

                // Sóng âm Equalizer nhảy múa
                for (let i = 0; i < this.waveBars.length; i++) {
                    const waveVal = 0.25 + Math.abs(Math.sin(time * (11 + i * 2.5) + i)) * 0.7;
                    this.waveBars[i] += (waveVal - this.waveBars[i]) * 0.35;
                }

                // Gật đầu nhẹ nhàng nữ tính khi nói
                this.headBob = Math.sin(time * 9) * 1.8;
                this.headTilt = Math.sin(time * 3.5) * 0.035;
            } else {
                this.mouthTarget = 0.05; // Nụ cười mỉm duyên dáng
                for (let i = 0; i < this.waveBars.length; i++) {
                    this.waveBars[i] += (0.08 - this.waveBars[i]) * 0.15;
                }

                // Idle breathing: Thở nhịp nhàng uyển chuyển
                this.headBob = Math.sin(time * 2.2) * 2.5;
                this.headTilt = Math.sin(time * 1.3) * 0.02;
            }

            this.mouthOpenness += (this.mouthTarget - this.mouthOpenness) * 0.35;

            if (this.isWinking && now > this.winkTimer) {
                this.isWinking = false;
            }

            this.updateDomWaveBars();
        }

        updateDomWaveBars() {
            const barEls = document.querySelectorAll('.queen-wave-bars .wave-bar');
            if (barEls && barEls.length > 0) {
                barEls.forEach((el, idx) => {
                    const val = this.waveBars[idx % this.waveBars.length] || 0.1;
                    el.style.transform = `scaleY(${Math.max(0.15, val)})`;
                    if (this.isSpeaking) {
                        el.style.backgroundColor = '#fbbf24';
                        el.style.boxShadow = '0 0 8px rgba(251, 191, 36, 0.8)';
                    } else {
                        el.style.backgroundColor = 'rgba(255, 255, 255, 0.25)';
                        el.style.boxShadow = 'none';
                    }
                });
            }
        }

        /**
         * RENDER GRAPHICS NỮ HOÀNG QUEEN GTLM TRÊN CANVAS 2D
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

            // ── 1. Hào Quang Hoàng Gia (Royal Golden Aura) ──
            const auraGrad = ctx.createRadialGradient(0, -12, 15, 0, -12, 75);
            auraGrad.addColorStop(0, this.isSpeaking ? 'rgba(251, 191, 36, 0.4)' : 'rgba(244, 114, 182, 0.25)');
            auraGrad.addColorStop(1, 'rgba(0, 0, 0, 0)');
            ctx.fillStyle = auraGrad;
            ctx.beginPath();
            ctx.arc(0, -12, 75, 0, Math.PI * 2);
            ctx.fill();

            // ── 2. Tóc Dài Phía Sau (Long Flowing Back Hair) ──
            ctx.fillStyle = '#4a284e'; // Tone tím thạch anh hoàng gia
            ctx.beginPath();
            ctx.ellipse(0, 16, 48, 52, 0, 0, Math.PI * 2);
            ctx.fill();

            // Hai lọn tóc buông dài qua vai
            ctx.beginPath();
            ctx.ellipse(-36, 38, 14, 28, Math.PI * 0.1, 0, Math.PI * 2);
            ctx.ellipse(36, 38, 14, 28, -Math.PI * 0.1, 0, Math.PI * 2);
            ctx.fill();

            // ── 3. Váy Hoàng Gia & Cổ Áo (Royal Dress & Choker) ──
            // Thân áo
            ctx.fillStyle = '#1e1b4b';
            ctx.beginPath();
            ctx.ellipse(0, 68, 48, 25, 0, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = '#fbbf24';
            ctx.lineWidth = 1.5;
            ctx.stroke();

            // Cổ thon thả
            ctx.fillStyle = '#ffebd9';
            ctx.fillRect(-8, 24, 16, 20);

            // Vòng cổ Choker hoàng gia đính kim cương
            ctx.fillStyle = '#831843';
            ctx.fillRect(-10, 34, 20, 5);
            // Viên ngọc vàng giữa cổ
            ctx.fillStyle = '#fbbf24';
            ctx.beginPath();
            ctx.arc(0, 36.5, 3.5, 0, Math.PI * 2);
            ctx.fill();

            // ── 4. Khuôn Mặt V-Line Nữ Tính (Anime Face) ──
            ctx.fillStyle = '#fff0e1';
            ctx.beginPath();
            ctx.ellipse(0, 0, 38, 40, 0, 0, Math.PI * 2);
            ctx.fill();

            // Cằm thon nhỏ
            ctx.beginPath();
            ctx.moveTo(-28, 8);
            ctx.quadraticCurveTo(-14, 40, 0, 42);
            ctx.quadraticCurveTo(14, 40, 28, 8);
            ctx.closePath();
            ctx.fill();

            // Má hồng phớt đáng yêu (Blush)
            ctx.fillStyle = 'rgba(251, 113, 133, 0.35)';
            ctx.beginPath();
            ctx.ellipse(-22, 12, 7, 4.5, 0, 0, Math.PI * 2);
            ctx.ellipse(22, 12, 7, 4.5, 0, 0, Math.PI * 2);
            ctx.fill();

            // ── 5. Đôi Mắt Nữ Hoàng & Hàng Mi Cong (Feminine Eyes & Lashes) ──
            this.drawQueenEye(ctx, -18, 0, this.eyeOpenness, this.isWinking); // Mắt trái
            this.drawQueenEye(ctx, 18, 0, this.eyeOpenness, false);            // Mắt phải

            // Lông mày mảnh mai duyên dáng
            ctx.strokeStyle = '#581c87';
            ctx.lineWidth = 1.8;
            ctx.lineCap = 'round';
            ctx.beginPath();
            ctx.moveTo(-26, -14);
            ctx.quadraticCurveTo(-18, -17, -10, -13);
            ctx.moveTo(10, -13);
            ctx.quadraticCurveTo(18, -17, 26, -14);
            ctx.stroke();

            // ── 6. Khẩu Hình Môi Hồng Cử Động (Lip-sync Mouth) ──
            this.drawLipSyncMouth(ctx, 0, 24, this.mouthOpenness);

            // ── 7. Tóc Mái Anime Dịu Dàng (Front Bangs) ──
            ctx.fillStyle = '#6b21a8';
            ctx.beginPath();
            ctx.moveTo(-42, -10);
            ctx.quadraticCurveTo(-24, -42, 0, -42);
            ctx.quadraticCurveTo(24, -42, 42, -10);
            // Các lọn tóc mái thưa uốn nhẹ
            ctx.quadraticCurveTo(30, -18, 22, -2);
            ctx.quadraticCurveTo(14, -16, 6, 0);
            ctx.quadraticCurveTo(0, -18, -8, 1);
            ctx.quadraticCurveTo(-18, -16, -24, -3);
            ctx.quadraticCurveTo(-34, -18, -42, -10);
            ctx.closePath();
            ctx.fill();

            // Vệt highlight óng ả trên tóc
            ctx.fillStyle = 'rgba(244, 219, 255, 0.4)';
            ctx.beginPath();
            ctx.ellipse(8, -28, 15, 4, Math.PI * 0.1, 0, Math.PI * 2);
            ctx.fill();

            // ── 8. 👑 VƯƠNG MIỆN NỮ HOÀNG QUEEN GTLM (Golden Royal Crown) ──
            ctx.save();
            ctx.translate(0, -42);
            
            // Đế vương miện
            ctx.fillStyle = '#f59e0b';
            ctx.beginPath();
            ctx.roundRect(-20, -4, 40, 7, 3);
            ctx.fill();
            ctx.strokeStyle = '#fbbf24';
            ctx.lineWidth = 1.5;
            ctx.stroke();

            // 3 Đỉnh vương miện
            ctx.fillStyle = '#fbbf24';
            ctx.beginPath();
            ctx.moveTo(-18, -4);
            ctx.lineTo(-14, -18); // Đỉnh trái
            ctx.lineTo(-7, -7);
            ctx.lineTo(0, -23);   // Đỉnh giữa cao nhất
            ctx.lineTo(7, -7);
            ctx.lineTo(14, -18);  // Đỉnh phải
            ctx.lineTo(18, -4);
            ctx.closePath();
            ctx.fill();
            ctx.stroke();

            // Viên ruby đỏ trên đỉnh giữa
            ctx.fillStyle = '#ef4444';
            ctx.beginPath();
            ctx.arc(0, -23, 3, 0, Math.PI * 2);
            ctx.fill();

            // Hai viên ngọc lục bảo hai bên đỉnh
            ctx.fillStyle = '#10b981';
            ctx.beginPath();
            ctx.arc(-14, -18, 2.2, 0, Math.PI * 2);
            ctx.arc(14, -18, 2.2, 0, Math.PI * 2);
            ctx.fill();

            // Lấp lánh trên vương miện
            ctx.fillStyle = '#ffffff';
            ctx.beginPath();
            ctx.arc(0, -24, 1.2, 0, Math.PI * 2);
            ctx.fill();

            ctx.restore();

            ctx.restore();
        }

        drawQueenEye(ctx, x, y, openness, isWink) {
            ctx.save();
            ctx.translate(x, y);

            if (isWink || openness < 0.15) {
                // Mắt nhắm dịu dàng hình cánh cung (^_~)
                ctx.strokeStyle = '#3b0764';
                ctx.lineWidth = 2.8;
                ctx.lineCap = 'round';
                ctx.beginPath();
                ctx.arc(0, -2, 8.5, Math.PI * 0.15, Math.PI * 0.85, false);
                ctx.stroke();

                // Hàng mi mắt cong lên khi nhắm
                ctx.beginPath();
                ctx.moveTo(7, 0);
                ctx.lineTo(10, -3);
                ctx.stroke();

                if (isWink) {
                    ctx.fillStyle = '#fbbf24';
                    ctx.beginPath();
                    ctx.arc(12, -7, 3, 0, Math.PI * 2);
                    ctx.fill();
                }
            } else {
                const eyeHeight = 11.5 * openness;
                
                // Tròng trắng
                ctx.fillStyle = '#ffffff';
                ctx.beginPath();
                ctx.ellipse(0, 0, 9, eyeHeight, 0, 0, Math.PI * 2);
                ctx.fill();

                // Tròng mắt Nữ Hoàng màu Tím Hổ Phách huyền ảo
                const irisGrad = ctx.createLinearGradient(0, -eyeHeight, 0, eyeHeight);
                irisGrad.addColorStop(0, '#3b0764');
                irisGrad.addColorStop(0.5, '#9333ea');
                irisGrad.addColorStop(1, '#ec4899');
                ctx.fillStyle = irisGrad;
                ctx.beginPath();
                ctx.ellipse(0, 0, 6.5, eyeHeight * 0.9, 0, 0, Math.PI * 2);
                ctx.fill();

                // Con ngươi
                ctx.fillStyle = '#1e1b4b';
                ctx.beginPath();
                ctx.ellipse(0, 0, 3.5, eyeHeight * 0.6, 0, 0, Math.PI * 2);
                ctx.fill();

                // Đốm sáng long lanh (Anime Highlights)
                ctx.fillStyle = '#ffffff';
                ctx.beginPath();
                ctx.arc(-2, -eyeHeight * 0.35, 2.2, 0, Math.PI * 2);
                ctx.arc(2, eyeHeight * 0.25, 1.2, 0, Math.PI * 2);
                ctx.fill();

                // Hàng mi mắt trên cong vút sắc nét
                ctx.strokeStyle = '#3b0764';
                ctx.lineWidth = 2.6;
                ctx.beginPath();
                ctx.arc(0, -1, 9.5, Math.PI * 1.15, Math.PI * 1.85);
                ctx.stroke();

                // Đuôi mi mắt cong kiêu kỳ
                ctx.beginPath();
                ctx.moveTo(8, -4);
                ctx.quadraticCurveTo(11, -7, 12, -9);
                ctx.stroke();
            }

            ctx.restore();
        }

        drawLipSyncMouth(ctx, x, y, openness) {
            ctx.save();
            ctx.translate(x, y);

            if (openness <= 0.12) {
                // Khép miệng: Nụ cười mỉm hồng xinh
                ctx.strokeStyle = '#e11d48';
                ctx.lineWidth = 2.0;
                ctx.lineCap = 'round';
                ctx.beginPath();
                ctx.moveTo(-6, -1);
                ctx.quadraticCurveTo(0, 2.5, 6, -1);
                ctx.stroke();
            } else {
                // Cử động mở khẩu hình theo lời nói (Lip-sync)
                const mouthH = Math.max(2.5, openness * 11);
                const mouthW = 6.5 + openness * 2.5;

                ctx.fillStyle = '#9f1239';
                ctx.beginPath();
                ctx.ellipse(0, mouthH * 0.4, mouthW, mouthH, 0, 0, Math.PI * 2);
                ctx.fill();

                // Lưỡi hồng xinh
                ctx.fillStyle = '#fb7185';
                ctx.beginPath();
                ctx.ellipse(0, mouthH * 0.85, mouthW * 0.7, mouthH * 0.45, 0, 0, Math.PI);
                ctx.fill();

                // Răng trên trắng
                ctx.fillStyle = '#ffffff';
                ctx.beginPath();
                ctx.rect(-mouthW * 0.6, 0, mouthW * 1.2, mouthH * 0.22);
                ctx.fill();

                // Viền môi bóng hồng
                ctx.strokeStyle = '#f43f5e';
                ctx.lineWidth = 1.8;
                ctx.beginPath();
                ctx.ellipse(0, mouthH * 0.4, mouthW, mouthH, 0, 0, Math.PI * 2);
                ctx.stroke();
            }

            ctx.restore();
        }

        /**
         * 🎙️ PHÁT GIỌNG NỮ HOÀNG QUEEN GTLM VỚI WEB SPEECH API & ĐỒNG BỘ LIP-SYNC
         */
        speak(text, onEndCallback) {
            if (!text) return;

            const displayClean = text.replace(/[*#_`~]/g, '').trim();
            this.showSpeechBubble(displayClean);

            if (!this.voiceEnabled) {
                this.simulateLipSyncWithoutAudio(displayClean, onEndCallback);
                return;
            }

            // Dừng audio stream trước đó nếu đang phát
            if (this.currentAudio) {
                try {
                    this.currentAudio.pause();
                    this.currentAudio.currentTime = 0;
                } catch(e){}
                this.currentAudio = null;
            }

            // 1. NẾU TRÌNH DUYỆT CÓ GIỌNG NỮ TIẾNG VIỆT ĐÍCH THỰC (HoaiMy, Google tiếng Việt...)
            if (this.hasConfirmedFemaleVoice && this.femaleVoice && ('speechSynthesis' in window)) {
                try {
                    let voiceText = displayClean
                        .replace(/GTLM/gi, 'Gờ Tờ Lờ Mờ')
                        .replace(/AI/gi, 'A I')
                        .replace(/PvP/gi, 'P v P')
                        .replace(/VIP/gi, 'V I P')
                        .replace(/Queen/gi, 'Quin')
                        .replace(/15x15/gi, 'mười lăm nhân mười lăm')
                        .replace(/x(\d+(\.\d+)?)/gi, 'nhân $1');

                    const utterance = new SpeechSynthesisUtterance(voiceText);
                    utterance.lang = 'vi-VN';
                    utterance.voice = this.femaleVoice;
                    utterance.rate = 1.05;  // Tốc độ chuẩn rõ ràng
                    utterance.pitch = 1.35; // Giọng nữ trong trẻo, ngọt ngào, nữ tính

                    utterance.onstart = () => {
                        this.isSpeaking = true;
                        this.toggleAudioGlow(true);
                    };

                    utterance.onboundary = (e) => {
                        this.mouthTarget = 0.65 + Math.random() * 0.3;
                    };

                    utterance.onend = () => {
                        this.isSpeaking = false;
                        this.toggleAudioGlow(false);
                        this.scheduleHideBubble(2200);
                        if (onEndCallback) onEndCallback();
                    };

                    utterance.onerror = () => {
                        this.isSpeaking = false;
                        this.toggleAudioGlow(false);
                        this.scheduleHideBubble(2000);
                    };

                    this.currentUtterance = utterance;
                    window.speechSynthesis.speak(utterance);

                    clearTimeout(this.speechTimeout);
                    const estimatedMs = Math.max(2500, (voiceText.split(' ').length / 2.6) * 1000);
                    this.speechTimeout = setTimeout(() => {
                        if (this.isSpeaking) {
                            this.isSpeaking = false;
                            this.toggleAudioGlow(false);
                            this.scheduleHideBubble(1500);
                        }
                    }, estimatedMs + 2000);
                    return;

                } catch (err) {
                    console.log('Queen WebSpeech error, fallback audio stream:', err);
                }
            }

            // 2. KHI HỆ THỐNG KHÔNG CÓ GIỌNG NỮ TIẾNG VIỆT (VD chỉ có giọng Nam Microsoft An):
            // SỬ DỤNG AUDIO STREAM NỮ TIẾNG VIỆT CHUẨN 100% CỦA QUEEN GTLM
            this.speakViaAudioStream(displayClean, onEndCallback);
        }

        speakViaAudioStream(text, onEndCallback) {
            try {
                const origin = window.location.origin;
                const pathParts = window.location.pathname.split('/');
                const appRoot = '/' + (pathParts[1] && !pathParts[1].includes('.') ? pathParts[1] + '/' : '');
                const ttsUrl = origin + appRoot + 'LiveStream/api_tts.php?text=' + encodeURIComponent(text);

                const audio = new Audio(ttsUrl);
                this.currentAudio = audio;

                audio.onplay = () => {
                    this.isSpeaking = true;
                    this.toggleAudioGlow(true);
                };

                // Đồng bộ nhịp khẩu hình Lip-sync theo âm thanh
                audio.ontimeupdate = () => {
                    if (this.isSpeaking) {
                        this.mouthTarget = 0.35 + Math.random() * 0.55;
                    }
                };

                audio.onended = () => {
                    this.isSpeaking = false;
                    this.toggleAudioGlow(false);
                    this.mouthTarget = 0.05;
                    this.scheduleHideBubble(2200);
                    this.currentAudio = null;
                    if (onEndCallback) onEndCallback();
                };

                audio.onerror = (e) => {
                    console.log('Queen Audio stream error, fallback simulate:', e);
                    this.simulateLipSyncWithoutAudio(text, onEndCallback);
                };

                audio.play().catch(err => {
                    console.log('Audio play prevented or blocked:', err);
                    this.simulateLipSyncWithoutAudio(text, onEndCallback);
                });

            } catch (err) {
                this.simulateLipSyncWithoutAudio(text, onEndCallback);
            }
        }

        simulateLipSyncWithoutAudio(text, callback) {
            this.isSpeaking = true;
            this.toggleAudioGlow(true);
            const duration = Math.max(2000, Math.min(8000, text.split(' ').length * 320));
            
            setTimeout(() => {
                this.isSpeaking = false;
                this.toggleAudioGlow(false);
                this.scheduleHideBubble(1800);
                if (callback) callback();
            }, duration);
        }

        showSpeechBubble(text) {
            const bubbleEl = document.getElementById('queenBubble');
            const textEl = document.getElementById('queenBubbleText');
            if (bubbleEl && textEl) {
                textEl.textContent = text;
                bubbleEl.style.display = 'block';
                bubbleEl.classList.remove('fade-out');
                bubbleEl.classList.add('pop-in');
            }
        }

        scheduleHideBubble(delayMs) {
            setTimeout(() => {
                const bubbleEl = document.getElementById('queenBubble');
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
            const glowEl = document.getElementById('queenAudioGlow');
            if (glowEl) {
                glowEl.style.opacity = active ? '1' : '0';
            }
        }

        toggleVoice() {
            this.voiceEnabled = !this.voiceEnabled;
            localStorage.setItem('gtlm_queen_voice', this.voiceEnabled ? 'true' : 'false');
            
            if (!this.voiceEnabled) {
                if (this.currentAudio) {
                    try { this.currentAudio.pause(); } catch(e){}
                    this.currentAudio = null;
                }
                if ('speechSynthesis' in window) {
                    window.speechSynthesis.cancel();
                }
                this.isSpeaking = false;
                this.toggleAudioGlow(false);
            }

            return this.voiceEnabled;
        }

        triggerWinkAndSweetTalk() {
            this.isWinking = true;
            this.winkTimer = performance.now() + 850;

            const sweetLines = [
                "Dạ em đây sếp ơi! Sếp cần Queen hướng dẫn tính năng nào hay có thắc mắc gì cứ bảo em nha! 💖",
                "Hi hi, sếp bấm vào em làm chi đó? Đừng trêu Queen ngại nha! 🥰",
                "Queen GTLM luôn ở đây đồng hành cùng sếp nè! Chúc sếp chơi game vui vẻ và thắng đậm nha!",
                "Dạ sếp ơi, sếp cần em hướng dẫn cách nhận GTLM miễn phí hay cách chơi cờ Ca-rô không ạ? 👑"
            ];

            const line = sweetLines[Math.floor(Math.random() * sweetLines.length)];
            this.speak(line);
        }

        destroy() {
            if (this.animId) cancelAnimationFrame(this.animId);
            if (this.currentAudio) { try { this.currentAudio.pause(); } catch(e){} this.currentAudio = null; }
            if ('speechSynthesis' in window) window.speechSynthesis.cancel();
        }
    }

    // Export ra global window
    window.QueenGTLMAvatar = QueenGTLMAvatar;
    // Tương thích ngược nếu còn code cũ gọi AIVTuberStreamer
    window.AIVTuberStreamer = QueenGTLMAvatar;

})(window, document);
