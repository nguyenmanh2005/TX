/**
 * bot_16.js — Bot Blackjack Multiplayer Bàn 16 v2
 *
 * Fix:
 *  - Không click nút "👁️ XEM" nữa → chỉ click "VÀO BÀN"
 *  - Basic Strategy casino chuẩn (Hard + Soft)
 *  - Double Down đúng điều kiện (không random nữa)
 *  - Tạo phòng với tên cố định, cược thấp nhất
 *  - Timing tự nhiên hơn (suy nghĩ 1.5-3s trước mỗi action)
 */

if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Thần Bài Multiplayer ♠️👑");
    window.botActionLocked = false;

    // ─── BASIC STRATEGY ──────────────────────────────────────────
    const BJStrategy = {
        calcScore(cards) {
            let score = 0, aces = 0;
            for (const c of cards) {
                const v = c.value;
                if (['J', 'Q', 'K'].includes(v)) score += 10;
                else if (v === 'A') { score += 11; aces++; }
                else score += parseInt(v);
            }
            while (score > 21 && aces > 0) { score -= 10; aces--; }
            return { score, isSoft: aces > 0 };
        },

        decide(pCards, dCards) {
            if (!pCards || pCards.length < 2) return 'stand';
            const { score, isSoft } = this.calcScore(pCards);
            const dScore = dCards && dCards.length > 0 ? this.calcScore([dCards[0]]).score : 7;
            const canDouble = pCards.length === 2;

            // ── SOFT HAND ──
            if (isSoft) {
                if (score >= 19) return 'stand';
                if (score === 18) return dScore <= 8 ? 'stand' : 'hit';
                // Soft double
                if (canDouble) {
                    if (score === 17 && dScore >= 3 && dScore <= 6) return 'double';
                    if (score === 16 && dScore >= 4 && dScore <= 6) return 'double';
                    if (score === 15 && dScore >= 4 && dScore <= 6) return 'double';
                    if (score === 13 && dScore >= 5 && dScore <= 6) return 'double';
                    if (score === 14 && dScore >= 5 && dScore <= 6) return 'double';
                }
                return 'hit'; // Soft ≤17
            }

            // ── HARD HAND ──
            if (score >= 17) return 'stand';
            if (score <= 8)  return 'hit';

            // Hard double
            if (canDouble) {
                if (score === 11) return 'double';
                if (score === 10 && dScore <= 9) return 'double';
                if (score === 9  && dScore >= 3 && dScore <= 6) return 'double';
            }

            if (score <= 11) return 'hit';
            if (score === 12) return (dScore >= 4 && dScore <= 6) ? 'stand' : 'hit';
            if (score >= 13 && score <= 16) return dScore <= 6 ? 'stand' : 'hit';

            return 'stand';
        }
    };

    // ─── CLICK AN TOÀN ───────────────────────────────────────────
    function botClick(btn, delay, after) {
        if (!btn) return;
        window.botActionLocked = true;
        const thinkTime = (delay || 800) + Math.random() * 1200;
        setTimeout(() => {
            BotVirtualCursor.moveToElement($(btn), 0.8, 0, () => {
                setTimeout(() => {
                    BotVirtualCursor.simulateClick(() => {
                        try { btn.click(); } catch(e) {}
                        setTimeout(() => {
                            window.botActionLocked = false;
                            after && after();
                        }, after ? 500 : 1500);
                    });
                }, 400);
            });
        }, thinkTime);
    }

    // ─── MAIN LOOP ────────────────────────────────────────────────
    setInterval(() => {
        if (window.botActionLocked) return;

        // ── 1. XỬ LÝ SWEETALERT (TẠO PHÒNG / THÔNG BÁO) ──
        const swalConfirm = document.querySelector('.swal2-confirm');
        if (swalConfirm && swalConfirm.offsetParent !== null) {
            // Điền form tạo phòng nếu có
            const inp1 = document.getElementById('swal-input1');
            if (inp1 && !inp1.value) {
                inp1.value = 'Phòng Bot Live ' + Math.floor(Math.random() * 100);
            }
            // Chọn cược tối thiểu (option đầu tiên = 10K)
            const inp2 = document.getElementById('swal-input2');
            if (inp2) inp2.value = inp2.options[0]?.value || '10000';
            // Thêm 2 bot vào phòng cho vui
            const inp3 = document.getElementById('swal-input3');
            if (inp3) inp3.value = '2';

            botClick(swalConfirm, 500);
            return;
        }

        // ── 2. SẢNH LOBBY ──
        const lobbyRooms = document.getElementById('lobby-rooms');
        if (lobbyRooms && lobbyRooms.offsetParent !== null) {
            const rooms = Array.from(lobbyRooms.children).filter(div => div.innerText.includes('👥'));

            // Tìm phòng có chỗ trống và đang chờ (không đang chơi)
            const availableRooms = rooms.filter(div => {
                const matchCount = div.innerText.match(/👥\s*(\d+)\/5/);
                const isWaiting  = div.innerText.includes('Đang Chờ');
                return matchCount && parseInt(matchCount[1]) < 5 && isWaiting;
            });

            if (availableRooms.length > 0) {
                // VÀO BÀN — không click XEM
                const targetRoom = availableRooms[Math.floor(Math.random() * availableRooms.length)];
                // Tìm nút VÀO BÀN (nền xanh #3b82f6), bỏ qua nút XEM
                const btnVaoBan = Array.from(targetRoom.querySelectorAll('button'))
                    .find(b => b.innerText.trim().includes('VÀO BÀN'));
                if (btnVaoBan) {
                    window.botActionLocked = true; // Khoá vì sắp load trang khác
                    BotVirtualCursor.moveToElement($(btnVaoBan), 0.8, 0, () => {
                        setTimeout(() => { btnVaoBan.click(); }, 600);
                    });
                    return;
                }
            } else {
                // Không có phòng phù hợp → tạo phòng mới
                const btnTaoPhong = Array.from(document.querySelectorAll('button'))
                    .find(b => b.innerText.includes('TẠO PHÒNG MỚI'));
                if (btnTaoPhong) {
                    botClick(btnTaoPhong, 1000);
                }
            }
            return; // Ở sảnh → không làm gì thêm
        }

        // ── 3. TRONG BÀN CHƠI ──

        // Nút Ngồi vào ghế trống
        const ngoiBtns = Array.from(document.querySelectorAll('.player-cards button, .seat button'))
            .filter(b => b.innerText.includes('Ngồi'));
        if (ngoiBtns.length > 0) {
            const btn = ngoiBtns[Math.floor(Math.random() * ngoiBtns.length)];
            botClick(btn, 800, null);
            return;
        }

        const btnBet    = document.getElementById('btn-bet');
        const btnHit    = document.getElementById('btn-hit');
        const btnStand  = document.getElementById('btn-stand');
        const btnDouble = document.getElementById('btn-double');

        const isVisible = el => el && el.style.display !== 'none' && el.offsetParent !== null;

        // ── ĐẶT CƯỢC ──
        if (isVisible(btnBet)) {
            const betInput = document.getElementById('bet-amount');
            if (betInput) {
                // Đặt cược vừa phải: 50K mặc định
                const BET_LEVELS = [10000, 50000, 100000, 200000];
                betInput.value = BET_LEVELS[Math.floor(Math.random() * 2) + 1]; // 50K hoặc 100K
            }
            botClick(btnBet, 1500);
            return;
        }

        // ── HIT / STAND / DOUBLE ──
        if (isVisible(btnHit)) {
            // Đọc bài của bot
            let pCards = [], dCards = [];
            const mySeat = Array.from(document.querySelectorAll('.seat'))
                .find(s => s.dataset.userId == window.currentUserId);
            if (mySeat) {
                try { pCards = JSON.parse(mySeat.querySelector('.player-cards')?.dataset.cardString || '[]'); } catch(e) {}
            }
            const dContainer = document.getElementById('dealer-cards');
            if (dContainer) {
                try { dCards = JSON.parse(dContainer.dataset.cardString || '[]'); } catch(e) {}
            }

            const action = BJStrategy.decide(pCards, dCards);

            let targetBtn = btnStand;
            if (action === 'double' && isVisible(btnDouble)) {
                targetBtn = btnDouble;
            } else if (action === 'hit') {
                targetBtn = btnHit;
            }

            botClick(targetBtn, 1500);
            return;
        }

    }, 1000); // Poll mỗi 1s, action được delay ngẫu nhiên bên trong botClick
}
