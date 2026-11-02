/**
 * 🎙️ GTLM DONATE & GIFT ANNOUNCER ENGINE v1.0
 * 
 * Hệ thống phát thanh viên / MC đọc thông báo Tip & Tặng Quà độc lập:
 * - 100% Giọng NAM tiếng Việt (Male Vietnamese Voice) dõng dạc, hào sảng, mạnh mẽ.
 * - Độc lập HOÀN TOÀN với Bot Queen GTLM (không dùng chung kênh, không can thiệp lip-sync của Queen).
 * - Cơ chế hàng đợi (Queue) chống giật/chèn giọng khi nhận liên hoàn quà (Combo x10, x50, x100).
 */

(function (window, document) {
    'use strict';

    class GTLMDonateAnnouncer {
        constructor() {
            this.voiceEnabled = localStorage.getItem('gtlm_donate_voice') !== 'false';
            this.maleVoice = null;
            this.queue = [];
            this.isAnnouncing = false;
            this.currentUtterance = null;

            this.initMaleVoice();
            if ('speechSynthesis' in window) {
                window.speechSynthesis.onvoiceschanged = () => this.initMaleVoice();
            }
        }

        initMaleVoice() {
            if (!('speechSynthesis' in window)) return;
            const voices = window.speechSynthesis.getVoices();
            if (!voices || voices.length === 0) return;

            // Tìm chính xác giọng NAM tiếng Việt (Microsoft An, NamMinh, Nam, Male, Minh)
            this.maleVoice = voices.find(v => 
                (v.lang && v.lang.toLowerCase().startsWith('vi')) && 
                (v.name.toLowerCase().includes(' an') || v.name.toLowerCase().includes('namminh') || v.name.toLowerCase().includes(' male') || v.name.toLowerCase().includes('nam') || v.name.toLowerCase().includes('minh') || v.name.toLowerCase().includes('duc'))
            ) || voices.find(v => (v.lang && v.lang.toLowerCase().startsWith('vi'))) || null;
        }

        /**
         * Phát giọng đọc thông báo tặng quà / tip
         */
        announce(rawText) {
            if (!rawText || !this.voiceEnabled) return;
            if (!('speechSynthesis' in window)) return;

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

            // Đưa vào hàng đợi
            this.queue.push(cleanText);
            this.processQueue();
        }

        processQueue() {
            if (this.isAnnouncing || this.queue.length === 0) return;

            const textToSpeak = this.queue.shift();
            this.isAnnouncing = true;

            try {
                const utterance = new SpeechSynthesisUtterance(textToSpeak);
                utterance.lang = 'vi-VN';

                if (this.maleVoice) {
                    utterance.voice = this.maleVoice;
                    // Nếu là giọng Microsoft An / Nam thì giữ pitch nam trầm chuẩn
                    const isKnownMale = this.maleVoice.name.toLowerCase().includes(' an') || 
                                        this.maleVoice.name.toLowerCase().includes('nam') || 
                                        this.maleVoice.name.toLowerCase().includes('male');
                    utterance.pitch = isKnownMale ? 0.95 : 0.65; // Nếu là giọng nữ hệ thống thì hạ pitch xuống quãng trầm nam
                } else {
                    utterance.pitch = 0.65; // Ép pitch thấp tạo giọng nam baritone
                }

                utterance.rate = 1.0; // Tốc độ dõng dạc, hào sảng

                utterance.onend = () => {
                    this.isAnnouncing = false;
                    this.currentUtterance = null;
                    // Đọc câu tiếp theo trong hàng đợi (nếu có)
                    setTimeout(() => this.processQueue(), 250);
                };

                utterance.onerror = (e) => {
                    console.log('Donate announce error:', e);
                    this.isAnnouncing = false;
                    this.currentUtterance = null;
                    setTimeout(() => this.processQueue(), 200);
                };

                this.currentUtterance = utterance;
                window.speechSynthesis.speak(utterance);

            } catch (err) {
                console.log('Error creating speech for donate:', err);
                this.isAnnouncing = false;
                setTimeout(() => this.processQueue(), 200);
            }
        }

        toggleVoice(enabled) {
            this.voiceEnabled = (enabled !== undefined) ? enabled : !this.voiceEnabled;
            localStorage.setItem('gtlm_donate_voice', this.voiceEnabled ? 'true' : 'false');
            if (!this.voiceEnabled) {
                this.queue = [];
                if (this.isAnnouncing && 'speechSynthesis' in window) {
                    window.speechSynthesis.cancel();
                    this.isAnnouncing = false;
                }
            }
            return this.voiceEnabled;
        }

        clearQueue() {
            this.queue = [];
        }
    }

    // Khởi tạo thực thể độc lập cho toàn bộ ứng dụng
    window.gtlmDonateVoice = new GTLMDonateAnnouncer();

    // Hàm gọi tiện ích tương thích với code giao diện cũ
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
