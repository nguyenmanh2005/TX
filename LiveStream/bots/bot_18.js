if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Thần Đồng Xu 🪙");

    function botClick(btn, thinkTime) {
        if (!btn || btn.offsetParent === null || btn.disabled) return;
        window.botActionLocked = true;
        // Safety unlock sau 10s nếu kẹt
        const safety = setTimeout(() => { window.botActionLocked = false; }, thinkTime + 5000);
        
        setTimeout(() => {
            BotVirtualCursor.moveToElement($(btn), 0.8, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { btn.click(); } catch(e){ console.error(e); }
                        clearTimeout(safety);
                        setTimeout(() => { window.botActionLocked = false; }, 1000);
                    });
                }, 300);
            });
        }, thinkTime);
    }

    setInterval(() => {
        if (window.botActionLocked) return;

        // 1. Tự động tắt bảng thông báo (SweetAlert) báo Thắng/Thua để chơi tiếp
        const swalConfirm = document.querySelector('.swal2-confirm');
        if (swalConfirm && swalConfirm.offsetParent !== null) {
            botClick(swalConfirm, 1000);
            return;
        }

        // 2. Chơi game Coinflip
        const btnSap = document.getElementById('btn-sap');
        const btnNgua = document.getElementById('btn-ngua');
        
        if (btnSap && !btnSap.disabled && btnNgua && !btnNgua.disabled) {
            // Chọn chip ngẫu nhiên (chỉ thỉnh thoảng đổi chip, và loại trừ nút ALL IN)
            const chips = Array.from(document.querySelectorAll('.quick-btn')).filter(b => !b.innerText.includes('ALL IN'));
            if (chips.length > 0 && Math.random() < 0.3) {
                const randomChip = chips[Math.floor(Math.random() * chips.length)];
                try { randomChip.click(); } catch(e){}
            }

            // Chọn ngẫu nhiên Sấp hoặc Ngửa (tỉ lệ 50/50)
            const choiceBtn = Math.random() > 0.5 ? btnSap : btnNgua;
            
            // Log hành động
            console.log(`[Bot 18] Đặt cược: ${document.getElementById('bet-amount').value} => Chọn: ${choiceBtn.id === 'btn-sap' ? 'SẤP' : 'NGỬA'}`);
            
            botClick(choiceBtn, 1500 + Math.random() * 2000);
        }

    }, 3000);
}
