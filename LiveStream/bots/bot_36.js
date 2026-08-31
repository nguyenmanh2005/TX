/**
 * Bot AI dành riêng cho game Xổ số Mini (ID: 36)
 * - Tự động tạo 5 số ngẫu nhiên
 * - Tự động điền vào ô input
 * - Bấm nút Quay Số
 * - Đợi kết quả và lặp lại
 */

if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Bot Streamer");
    
    function playLottery() {
        const inputField = document.getElementById('user-number');
        const submitBtn = document.getElementById('btn-submit');
        
        if (!inputField || !submitBtn || submitBtn.disabled) {
            setTimeout(playLottery, 2000);
            return;
        }

        // Sinh 5 số ngẫu nhiên
        let randomNum = Math.floor(Math.random() * 90000) + 10000;
        
        // Di chuyển chuột tới ô input
        BotVirtualCursor.moveToElement($(inputField), 1, 0, () => {
            setTimeout(() => {
                inputField.value = randomNum;
                inputField.dispatchEvent(new Event('input', { bubbles: true }));
                
                // Di chuyển tới nút Submit và click
                setTimeout(() => {
                    BotVirtualCursor.moveToElement($(submitBtn), 1, 0, () => {
                        setTimeout(() => {
                            BotVirtualCursor.simulateClick(() => {
                                console.log("[Bot 36] simulateClick callback called");
                                try {
                                    var btn = document.getElementById('btn-submit');
                                    if (btn) {
                                        btn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
                                    }
                                } catch(e){
                                    console.error("[Bot 36] Error clicking:", e);
                                }
                                // Chờ ván tiếp theo
                                setTimeout(playLottery, 5000 + Math.random() * 5000);
                            });
                        }, 500);
                    });
                }, 500);
            }, 500);
        });
    }

    // Bắt đầu vòng lặp đầu tiên sau 3s
    setTimeout(playLottery, 3000);
}
