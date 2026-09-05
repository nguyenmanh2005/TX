/**
 * 🤖 Bot AI Oẳn Tù Tì (Rock Paper Scissors) Siêu Thông Minh (ID: 47)
 *
 * CHIẾN THUẬT & TRÍ THÔNG MINH TÂM LÝ HỌC:
 * 1. QUẢN LÝ VỐN CHUYÊN NGHIỆP:
 *    - Tự động điều chỉnh mức cược theo chuỗi: 10K -> 50K -> 100K -> 500K.
 *    - Khi thua: Trở về 10K an toàn để bảo toàn vốn.
 * 2. CHIẾN THUẬT TÂM LÝ OẲN TÙ TÌ (Win-Stay, Lose-Shift):
 *    - Thắng ván trước: Đối thủ thường đổi nước đi -> Bot dự đoán trước 1 bước.
 *    - Thua ván trước: Bot chuyển sang thế phản đòn khắc chế nước đi vừa ra của bot đối thủ.
 *    - Luân chuyển Đá (👊), Giấy (✋), Kéo (✌️) biến ảo, không thể bị bắt bài.
 * 3. QUY TRÌNH HÀNH ĐỘNG CHUẨN XÁC:
 *    - Bước 1: Chọn thế tay (Đá, Giấy hoặc Kéo) bằng chuột ảo.
 *    - Bước 2: Cài đặt mức cược tương ứng.
 *    - Bước 3: Di chuyển chuột ảo bấm "CHẾT NÀY!" (#btn-play).
 *    - Bước 4: Chờ kết thúc animation lắc tay 1.5s và hiển thị badge kết quả.
 * 4. WATCHDOG & CHỐNG KẸT:
 *    - Watchdog 3.5s tự phục hồi nếu mạng trễ hoặc animation bị gián đoạn.
 *    - Tuyệt đối không click lung tung vào các nút ngoài cuộc chơi.
 */

(function () {
    'use strict';

    if (typeof BotVirtualCursor === 'undefined') {
        console.warn('[Bot 47] BotVirtualCursor chưa được nạp!');
        return;
    }

    BotVirtualCursor.init('RPS Master 47');

    // ═══════════════════════════════════════════════════════
    // TRẠNG THÁI VÀ CHỈ SỐ BOT
    // ═══════════════════════════════════════════════════════
    let botIsBusy = false;
    let busyTimer = null;
    let lastChatTime = 0;
    let winStreak = 0;
    let lossStreak = 0;
    let lastUserChoice = 'Đá';
    let lastBotOpponentChoice = 'Kéo';
    let lastResult = 'win';

    function setBusy(val, timeoutMs = 3500) {
        botIsBusy = val;
        if (busyTimer) clearTimeout(busyTimer);
        if (val) {
            busyTimer = setTimeout(() => {
                botIsBusy = false;
            }, timeoutMs);
        }
    }

    // ═══════════════════════════════════════════════════════
    // CHIẾN THUẬT CHỌN THẾ TAY TÂM LÝ HỌC
    // ═══════════════════════════════════════════════════════
    const choices = ['Đá', 'Giấy', 'Kéo'];
    const beats = { 'Đá': 'Kéo', 'Giấy': 'Đá', 'Kéo': 'Giấy' };
    const losesTo = { 'Kéo': 'Đá', 'Đá': 'Giấy', 'Giấy': 'Kéo' };

    function pickNextChoice() {
        // Tâm lý học Oẳn Tù Tì:
        // - Nếu vừa THẮNG: người chơi nghiệp dư thường ra lại thế vừa thắng -> Bot chọn nước khắc chế thế đó!
        // - Nếu vừa THUA: người chơi thường đổi sang thế vừa thắng họ -> Bot chuẩn bị sẵn nước khắc chế!
        if (lastResult === 'win') {
            // Đối thủ dự đoán ta ra lại thế cũ, nên họ sẽ ra thế khắc ta -> Ta ra thế khắc chế thế khắc chế đó
            return losesTo[lastUserChoice];
        } else if (lastResult === 'lose') {
            // Ta chọn thế khắc lại thế vừa thắng của đối thủ
            return losesTo[lastBotOpponentChoice] || choices[Math.floor(Math.random() * 3)];
        } else {
            // Hòa: Chọn ngẫu nhiên thế khác thế vừa hòa
            const others = choices.filter(c => c !== lastUserChoice);
            return others[Math.floor(Math.random() * others.length)];
        }
    }

    // ═══════════════════════════════════════════════════════
    // CHAT PHONG CÁCH OẲN TÙ TÌ
    // ═══════════════════════════════════════════════════════
    const winPhrases = [
        'Đoán trước được nước đi luôn! Húp trọn GTLM! 👊✨',
        'Bắt bài đối thủ quá chuẩn, chuỗi thắng lại nối dài! 🏆',
        'Bao bọc trọn búa, kéo cắt ngọt ngào! GTLM về ví! 💰',
        'Chiến thuật tâm lý học đỉnh cao, không trượt phát nào! 😎🚀'
    ];

    const drawPhrases = [
        'Tâm đầu ý hợp thế nhỉ, hòa GTLM về ván sau so tài tiếp! 🤝',
        'Đụng hàng rồi, ván sau ra chiêu độc lạ hơn! 👀'
    ];

    const losePhrases = [
        'Bị lừa một ván nhẹ! Ván sau đọc vị lại ngay! 😤',
        'Nước đi bất ngờ đấy, nhưng ván sau tôi phục thù! 🎯',
        'Thua một ván không sờn lòng, quản lý vốn là bất bại! 💪'
    ];

    function sendBotChat(status) {
        const now = Date.now();
        if (now - lastChatTime < 14000) return;
        if (Math.random() > 0.5) return;

        let list = winPhrases;
        if (status === 'draw') list = drawPhrases;
        else if (status === 'lose') list = losePhrases;

        const msg = list[Math.floor(Math.random() * list.length)];
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            BotChat.send(47, 'bot_47', msg);
            lastChatTime = now;
        }
    }

    // ═══════════════════════════════════════════════════════
    // VÒNG CHƠI CHÍNH
    // ═══════════════════════════════════════════════════════
    function playRound() {
        if (botIsBusy) return;

        const btnPlay = document.getElementById('btn-play');
        const cuocInput = document.getElementById('cuoc');

        if (!btnPlay || btnPlay.disabled) return;

        setBusy(true, 4000);

        // 1. Quản lý vốn theo chuỗi thắng/thua
        let targetBet = 10000;
        if (winStreak >= 3) targetBet = 500000;
        else if (winStreak >= 2) targetBet = 100000;
        else if (winStreak >= 1) targetBet = 50000;
        else targetBet = 10000;

        if (cuocInput) {
            cuocInput.value = targetBet;
        }

        // 2. Chọn thế tay
        const choiceToPick = pickNextChoice();
        lastUserChoice = choiceToPick;

        const choiceBtn = document.querySelector(`.choice-btn[data-choice="${choiceToPick}"]`);
        if (!choiceBtn) {
            setBusy(false);
            return;
        }

        // Di chuyển chuột ảo tới nút chọn Đá/Giấy/Kéo
        BotVirtualCursor.moveToElement($(choiceBtn), 0.35, 0, () => {
            setTimeout(() => {
                BotVirtualCursor.simulateClick(() => {
                    try { choiceBtn.click(); } catch (e) { }

                    // Di chuyển tiếp đến nút "CHẾT NÀY!" (#btn-play)
                    setTimeout(() => {
                        BotVirtualCursor.moveToElement($(btnPlay), 0.4, 0, () => {
                            setTimeout(() => {
                                BotVirtualCursor.simulateClick(() => {
                                    try { btnPlay.click(); } catch (e) { }

                                    // Chờ animation lắc tay 1.5s + hiển thị kết quả
                                    setTimeout(() => {
                                        setBusy(false);
                                    }, 2200);
                                });
                            }, 80);
                        });
                    }, 200);
                });
            }, 80);
        });
    }

    // ═══════════════════════════════════════════════════════
    // THEO DÕI KẾT QUẢ TỪ BADGE
    // ═══════════════════════════════════════════════════════
    function setupResultWatcher() {
        const badge = document.getElementById('result-status-badge');
        if (badge) {
            const observer = new MutationObserver(() => {
                if (badge.style.display !== 'none' && badge.style.opacity === '1') {
                    const title = (document.getElementById('result-badge-title') || {}).textContent || '';
                    if (title.includes('THẮNG')) {
                        winStreak++;
                        lossStreak = 0;
                        lastResult = 'win';
                        sendBotChat('win');
                    } else if (title.includes('HÒA')) {
                        lastResult = 'draw';
                        sendBotChat('draw');
                    } else if (title.includes('THUA')) {
                        lossStreak++;
                        winStreak = 0;
                        lastResult = 'lose';
                        sendBotChat('lose');
                    }
                }
            });
            observer.observe(badge, { attributes: true, attributeFilter: ['style'] });
        }
    }

    // Khởi tạo
    setupResultWatcher();

    // Vòng lặp định kỳ mỗi 800ms
    setInterval(playRound, 800);
    setTimeout(playRound, 1200);

})();
