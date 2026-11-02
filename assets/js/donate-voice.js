/**
 * 🎙️ GTLM DONATE & GIFT ANNOUNCER ENGINE v2.0
 * 
 * Hệ thống phát thanh viên / MC đọc thông báo Tip & Tặng Quà độc lập:
 * - 100% Giọng NAM tiếng Việt (Male Vietnamese Audio Stream) dõng dạc, hào sảng, mạnh mẽ.
 * - KHÔNG PHỤ THUỘC vào giọng cài đặt của trình duyệt (tránh tuyệt đối lỗi bị đọc tiếng Anh / Microsoft David).
 * - Độc lập HOÀN TOÀN với Bot Queen GTLM (không dùng chung kênh, không can thiệp lip-sync của Queen).
 * - Cơ chế hàng đợi (Queue) chống giật/chèn giọng khi nhận liên hoàn quà (Combo x10, x50, x100).
 */

(function (window, document) {
    'use strict';

    class GTLMDonateAnnouncer {
        constructor() {
            this.voiceEnabled = localStorage.getItem('gtlm_donate_voice') !== 'false';
            this.queue = [];
            this.isAnnouncing = false;
            this.currentAudio = null;
        }

        /**
         * Phát giọng đọc thông báo tặng quà / tip
         */
        announce(rawText) {
            if (!rawText || !this.voiceEnabled) return;

            // Chuẩn hóa phát âm tiếng Việt cho phát thanh viên nam
            let cleanText = String(rawText)
                .replace(/[*#_`~]/g, '')
                .replace(/GTLM/gi, 'Gờ Tờ Lờ Mờ')
                .replace(/combo\s*x?(\d+)/gi, 'com bô $1')
                .replace(/x(\d+(\.\d+)?)/gi, 'nhân $1')
                .replace(/AI/gi, 'A I')
                .replace(/PvP/gi, 'P v P')
                .replace(/VIP/gi, 'V I P')
                .replace(/\s+/g, ' ')
                .trim();

            if (!cleanText) return;

            // Đưa vào hàng đợi phát âm thanh
            this.queue.push(cleanText);
            this.processQueue();
        }

        processQueue() {
            if (this.isAnnouncing || this.queue.length === 0 || !this.voiceEnabled) return;

            const textToSpeak = this.queue.shift();
            this.isAnnouncing = true;

            try {
                const origin = window.location.origin;
                const pathParts = window.location.pathname.split('/');
                const appRoot = '/' + (pathParts[1] && !pathParts[1].includes('.') ? pathParts[1] + '/' : '');
                const ttsUrl = origin + appRoot + 'LiveStream/api_tts.php?voice=male&text=' + encodeURIComponent(textToSpeak);

                if (this.currentAudio) {
                    try {
                        this.currentAudio.pause();
                        this.currentAudio.currentTime = 0;
                    } catch (e) {}
                    this.currentAudio = null;
                }

                const audio = new Audio(ttsUrl);
                this.currentAudio = audio;

                audio.onended = () => {
                    this.isAnnouncing = false;
                    this.currentAudio = null;
                    // Đọc tiếp câu tiếp theo trong hàng đợi (nếu có)
                    setTimeout(() => this.processQueue(), 250);
                };

                audio.onerror = (e) => {
                    console.log('Donate Audio stream error:', e);
                    this.isAnnouncing = false;
                    this.currentAudio = null;
                    setTimeout(() => this.processQueue(), 200);
                };

                audio.play().catch(err => {
                    console.log('Donate Audio play prevented:', err);
                    this.isAnnouncing = false;
                    this.currentAudio = null;
                    setTimeout(() => this.processQueue(), 200);
                });

            } catch (err) {
                console.log('Donate queue error:', err);
                this.isAnnouncing = false;
                this.currentAudio = null;
                setTimeout(() => this.processQueue(), 200);
            }
        }

        toggleVoice(enabled) {
            this.voiceEnabled = (enabled !== undefined) ? enabled : !this.voiceEnabled;
            localStorage.setItem('gtlm_donate_voice', this.voiceEnabled ? 'true' : 'false');
            if (!this.voiceEnabled) {
                this.queue = [];
                if (this.currentAudio) {
                    try {
                        this.currentAudio.pause();
                        this.currentAudio.currentTime = 0;
                    } catch (e) {}
                    this.currentAudio = null;
                }
                this.isAnnouncing = false;
            }
            return this.voiceEnabled;
        }

        clearQueue() {
            this.queue = [];
            if (this.currentAudio) {
                try {
                    this.currentAudio.pause();
                    this.currentAudio.currentTime = 0;
                } catch (e) {}
                this.currentAudio = null;
            }
            this.isAnnouncing = false;
        }
    }

    // Khởi tạo thực thể độc lập cho toàn bộ ứng dụng
    window.gtlmDonateVoice = new GTLMDonateAnnouncer();

    // Hàm gọi tiện ích tương thích với toàn bộ hệ thống
    window.speakStreamerVoice = function (text) {
        if (window.gtlmDonateVoice) {
            window.gtlmDonateVoice.announce(text);
        }
    };

    window.speakDonateMale = function (text) {
        if (window.gtlmDonateVoice) {
            window.gtlmDonateVoice.announce(text);
        }
    };

})(window, document);
