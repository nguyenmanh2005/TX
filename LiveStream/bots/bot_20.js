if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Xạ Thủ Xúc Xắc Craps 🎲");

    const CASINO_CHATS = {
        bet: [
            "Đấu trường Craps rực lửa! Ra chiêu lắc cặp xúc xắc định mệnh! 🎲⚡",
            "Comeout Roll! Tay lắc ma thuật gieo 7 hoặc 11 húp trọn GTLM ngay hiệp 1! 🌟💎",
            "Vào tiền chắc tay, ném xúc xắc chuẩn từng góc độ! 🎯💰",
            "Trận địa hôm nay cầu đang thuận, gieo xúc xắc rước lộc về nick! 💎🚀"
        ],
        point: [
            "Đã thiết lập Điểm Point! Tập trung hỏa lực săn xúc xắc trùng điểm húp đậm! 🎲🔥",
            "Giữ vững tay ném, né số 7 đón trúng điểm Point nào! 🛡️⚡"
        ],
        win: [
            "7 HOẶC 11 RỒI! HÚP TRỌN ĐẤU TRƯỜNG CRAPS! Tiền nhảy ting ting vào nick! 🏆🎲🤑",
            "Gáy khét lẹt cả sòng Craps hôm nay! Tay lắc đỉnh của chóp! 👑💎💰",
            "Trúng điểm Point ngoạn mục! Lộc lá GTLM về nick ngập tràn! 🌟🎉"
        ],
        loss: [
            "Craps out rồi, bay màu tiếng lắc này! Không sao, hiệp sau gỡ lại! 😅🔄",
            "Bốc hơi chút ít GTLM, xốc lại tinh thần ném ván mới! 💪🎲"
        ]
    };

    function sendChat(type) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.bet;
            const msg = list[Math.floor(Math.random() * list.length)];
            BotChat.send(20, 'Xạ Thủ Xúc Xắc Craps 🎲', msg);
        }
    }

    function playCraps() {
        // Xóa popup Swal nếu có (thắng/thua/lỗi) để tiếp tục
        const swalConfirm = document.querySelector('.swal2-confirm');
        if (swalConfirm && swalConfirm.offsetParent !== null) {
            const swalTxt = (document.querySelector('.swal2-title')?.innerText || '').toLowerCase();
            if (swalTxt.includes('thắng') || swalTxt.includes('win')) {
                if (Math.random() < 0.6) sendChat('win');
            } else if (swalTxt.includes('thua') || swalTxt.includes('craps') || swalTxt.includes('lose')) {
                if (Math.random() < 0.5) sendChat('loss');
            }
            BotVirtualCursor.moveToElement($(swalConfirm), 0.5, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => { try { swalConfirm.click(); } catch (e) { } });
                    setTimeout(playCraps, 1500);
                }, 300);
            });
            return;
        }

        const rollBtn = document.getElementById('roll-btn');
        if (!rollBtn || rollBtn.disabled) {
            // Đang lắc hoặc bị disable thì đợi
            setTimeout(playCraps, 1000);
            return;
        }

        // Kiểm tra xem có đang ở phase Point không (đã có điểm, không được đổi cược)
        const betArea = document.getElementById('bet-area');
        const isPointPhase = betArea && betArea.style.display === 'none';

        if (!isPointPhase) {
            // Phase đầu tiên (Comeout), chọn mức cược
            const betBtns = Array.from(document.querySelectorAll('.quick-btn')).filter(b => !b.innerText.includes('ALL IN') && b.offsetParent !== null);
            if (betBtns.length > 0) {
                const maxIdx = Math.min(betBtns.length, 4);
                let btn = betBtns[Math.floor(Math.random() * maxIdx)];

                BotVirtualCursor.moveToElement($(btn), 0.4, 0, () => {
                    setTimeout(() => {
                        BotVirtualCursor.simulateClick(() => { try { btn.click(); } catch (e) { } });
                        setTimeout(() => {
                            if (Math.random() < 0.35) sendChat('bet');
                            clickRollBtn(rollBtn);
                        }, 300);
                    }, 200);
                });
                return;
            }
        } else {
            if (Math.random() < 0.25) sendChat('point');
        }

        // Phase Point hoặc không tìm thấy nút cược -> Chỉ việc nhấn lắc
        clickRollBtn(rollBtn);
    }

    function clickRollBtn(btn) {
        BotVirtualCursor.moveToElement($(btn), 0.4, 0, () => {
            setTimeout(() => {
                BotVirtualCursor.simulateClick(() => { try { btn.click(); } catch (e) { } });
                setTimeout(playCraps, 3200 + Math.random() * 800);
            }, 200);
        });
    }

    // Bắt đầu chu trình tự động của Bot sau 2 giây
    setTimeout(playCraps, 2000);
}
