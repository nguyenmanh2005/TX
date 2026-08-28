if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Bot Streamer");

    function playCraps() {
        // Xóa popup Swal nếu có (thắng/thua/lỗi) để tiếp tục
        const swalConfirm = document.querySelector('.swal2-confirm');
        if (swalConfirm && swalConfirm.offsetParent !== null) {
            BotVirtualCursor.moveToElement($(swalConfirm), 1, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => { try { swalConfirm.click(); } catch (e) { } });
                    setTimeout(playCraps, 2000);
                }, 400);
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
            // Lọc bỏ ALL IN để tránh cháy túi sớm
            const betBtns = Array.from(document.querySelectorAll('.quick-btn')).filter(b => !b.innerText.includes('ALL IN') && b.offsetParent !== null);
            if (betBtns.length > 0) {
                // Chọn các mức cược từ nhỏ đến vừa, thỉnh thoảng mới cược to
                const maxIdx = Math.min(betBtns.length, 4);
                let btn = betBtns[Math.floor(Math.random() * maxIdx)];

                BotVirtualCursor.moveToElement($(btn), 0.5, 0, () => {
                    setTimeout(() => {
                        BotVirtualCursor.simulateClick(() => { try { btn.click(); } catch (e) { } });
                        // Chọn cược xong thì nhấn lắc
                        setTimeout(() => clickRollBtn(rollBtn), 300);
                    }, 200);
                });
                return;
            }
        }

        // Phase Point hoặc không tìm thấy nút cược -> Chỉ việc nhấn lắc
        clickRollBtn(rollBtn);
    }

    function clickRollBtn(btn) {
        BotVirtualCursor.moveToElement($(btn), 0.5, 0, () => {
            setTimeout(() => {
                BotVirtualCursor.simulateClick(() => { try { btn.click(); } catch (e) { } });
                // Đợi 3.5-4.5s cho hiệu ứng lắc (0.6s) và popup chữ nổi (2.5s) kết thúc
                setTimeout(playCraps, 3500 + Math.random() * 1000);
            }, 200);
        });
    }

    // Bắt đầu chu trình tự động của Bot sau 2 giây
    setTimeout(playCraps, 2000);
}
