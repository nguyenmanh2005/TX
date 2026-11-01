if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Thần Bài Caribbean 👑");
    let lastKnownBalance = null;

    const CASINO_CHATS = {
        deal: [
            "Khai cuộc ván bài Caribbean Stud Poker! Ra chiêu cược Ante săn Thùng Phá Sảnh! 👑♠️",
            "Chia 5 lá bài nào! Cầu bài đang đỏ, quyết tâm húp trọn ván giao lưu này! 💎🔥",
            "Bắt đầu ván poker biển Caribbean! Tự tin đọ tay bài cùng nhà cái! 🌟💰"
        ],
        call: [
            "Bài có cửa sáng! Ra chiêu Theo cược (CALL) x2 tiền đè bẹp nhà cái! 🃏⚡",
            "Thế bài quá nét, không CALL thì có lỗi với bản thân! Húp GTLM nào! 💥💪"
        ],
        fold: [
            "Bài xấu quá, Bỏ bài (FOLD) bảo toàn nick chờ thời cơ! 🛡️✋",
            "Rút lui chiến thuật, giữ vững nguồn vốn cho tiếng sau! 🍵✨"
        ],
        win: [
            "HÚP TRỌN VÁN POKER CARIBBEAN! Tay bài áp đảo tuyệt đối, tiền cộng đầy nick! 🏆👑🤑",
            "Gáy khét lẹt cả sảnh Caribbean Poker hôm nay! Chiến thắng giòn giã! 💎🎉🔥",
            "Nhà cái không đủ điều kiện hoặc bài non! Húp sạch tiền cược! ⚡💰"
        ],
        loss: [
            "Nhà cái mở ra bài to hơn rồi, bay màu tiếng cược này! 😭💔",
            "Bốc hơi chút ít GTLM, ván sau ra chiêu tất tay phục thù! 💪🔄"
        ]
    };

    function sendChat(type) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.deal;
            const msg = list[Math.floor(Math.random() * list.length)];
            BotChat.send(17, 'Thần Bài Caribbean 👑', msg);
        }
    }

    function readMoney() {
        const el = document.getElementById('userMoney');
        if (!el) return 0;
        return parseInt(el.innerText.replace(/\D/g, '')) || 0;
    }

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
            const curBal = readMoney();
            if (lastKnownBalance !== null) {
                if (curBal > lastKnownBalance) {
                    if (Math.random() < 0.6) sendChat('win');
                } else if (curBal < lastKnownBalance) {
                    if (Math.random() < 0.5) sendChat('loss');
                }
            }
            lastKnownBalance = curBal;
            botClick(document.getElementById('newBtn'), 2000);
            return;
        }

        if (isVisible('callBtn') && isVisible('foldBtn')) {
            const hand = window.currentHand;
            const dealer = window.dealerUp;
            const action = evaluateCaribbean(hand, dealer);
            
            console.log(`[Bot 17] Đánh giá: Hand=${hand}, DealerUp=${dealer} => Quyết định: ${action.toUpperCase()}`);
            
            // Suy nghĩ 2-4 giây cho Call/Fold
            if (action === 'call') {
                if (Math.random() < 0.4) sendChat('call');
                botClick(document.getElementById('callBtn'), 2000 + Math.random() * 2000);
            } else {
                if (Math.random() < 0.3) sendChat('fold');
                botClick(document.getElementById('foldBtn'), 2000 + Math.random() * 2000);
            }
            return;
        }

        if (isVisible('dealBtn')) {
            if (lastKnownBalance === null) lastKnownBalance = readMoney();
            if (Math.random() < 0.35) sendChat('deal');

            // Xác suất 30% đổi mức cược ngẫu nhiên (tránh click ALL IN)
            const chips = Array.from(document.querySelectorAll('.chip')).filter(c => !c.innerText.includes('MAX') && !c.innerText.includes('ALL'));
            if (chips.length > 0 && Math.random() < 0.3) {
                const randomChip = chips[Math.floor(Math.random() * chips.length)];
                BotVirtualCursor.moveToElement($(randomChip), 0.8, 0, () => {
                    setTimeout(() => {
                        BotVirtualCursor.simulateClick(() => {
                            try { randomChip.click(); } catch(e){}
                            // Sau khi đổi chip thì tiếp tục di chuột tới nút Deal
                            botClick(document.getElementById('dealBtn'), 1000);
                        });
                    }, 300);
                });
                return;
            }
            
            // 70% còn lại giữ nguyên mức cược và chia bài ngay
            botClick(document.getElementById('dealBtn'), 1500);
            return;
        }

    }, 2000);
}
