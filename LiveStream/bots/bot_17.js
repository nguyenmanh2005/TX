if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Thần Bài Caribbean 👑");

    function parseCard(c) {
        if (!c) return 0;
        let v = c.slice(0, -1);
        if (v === 'A') return 14;
        if (v === 'K') return 13;
        if (v === 'Q') return 12;
        if (v === 'J') return 11;
        return parseInt(v);
    }

    function evaluateCaribbean(handCards, dealerCard) {
        if (!handCards || handCards.length < 5) return 'call';
        let ranks = handCards.map(parseCard).sort((a, b) => b - a);
        let dealerRank = parseCard(dealerCard);

        // Check Pairs or better
        let isPairOrBetter = false;
        let counts = {};
        for (let r of ranks) {
            counts[r] = (counts[r] || 0) + 1;
            if (counts[r] >= 2) isPairOrBetter = true;
        }

        // 1. Luôn CALL nếu có Đôi hoặc mạnh hơn (Straight, Flush, v.v...)
        if (isPairOrBetter) return 'call';

        // 2. FOLD nếu bài thấp hơn A-K
        if (ranks[0] !== 14 || ranks[1] !== 13) return 'fold';

        // 3. Nếu là A-K High:
        // - CALL nếu dealer upcard (2-Q) trùng với 1 trong các lá của player
        if (dealerRank >= 2 && dealerRank <= 12 && ranks.includes(dealerRank)) return 'call';
        // - CALL nếu dealer upcard là A hoặc K và player có Q hoặc J
        if ((dealerRank === 14 || dealerRank === 13) && (ranks.includes(12) || ranks.includes(11))) return 'call';
        // - CALL nếu dealer upcard là 2-5 và player có Q hoặc J
        if (dealerRank >= 2 && dealerRank <= 5 && (ranks.includes(12) || ranks.includes(11))) return 'call';

        return 'fold'; // Trường hợp A-K yếu nhất
    }

    function botClick(btn, thinkTime) {
        if (!btn || btn.offsetParent === null || btn.disabled) return;
        window.botActionLocked = true;
        // Safety unlock after 10s
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

        const isVisible = (id) => {
            const el = document.getElementById(id);
            return el && el.style.display !== 'none' && el.offsetParent !== null;
        };

        // 1. Tự động tắt bảng thông báo (SweetAlert) nếu có
        const swalConfirm = document.querySelector('.swal2-confirm');
        if (swalConfirm && swalConfirm.offsetParent !== null) {
            botClick(swalConfirm, 1000);
            return;
        }

        // 2. Nhấn VÁN MỚI
        if (isVisible('newBtn')) {
            botClick(document.getElementById('newBtn'), 2000);
            return;
        }

        if (isVisible('callBtn') && isVisible('foldBtn')) {
            const hand = window.currentHand;
            const dealer = window.dealerUp;
            const action = evaluateCaribbean(hand, dealer);
            
            console.log(`[Bot 17] Đánh giá: Hand=${hand}, DealerUp=${dealer} => Quyết định: ${action.toUpperCase()}`);
            
            // Suy nghĩ 2-4 giây cho Call/Fold
            if (action === 'call') botClick(document.getElementById('callBtn'), 2000 + Math.random() * 2000);
            else botClick(document.getElementById('foldBtn'), 2000 + Math.random() * 2000);
            return;
        }

        if (isVisible('dealBtn')) {
            // Chọn chip ngẫu nhiên
            const chips = document.querySelectorAll('.chip');
            if (chips.length > 0 && Math.random() < 0.3) {
                const randomChip = chips[Math.floor(Math.random() * (chips.length - 1))]; // Bỏ chip All-in
                try { randomChip.click(); } catch(e){}
            }
            botClick(document.getElementById('dealBtn'), 1500);
            return;
        }

    }, 2000);
}
