if (typeof BotVirtualCursor !== "undefined") {
        BotVirtualCursor.init("Bot Streamer");
        window.botActionLocked = false;
        
        setInterval(() => {
            if (window.botActionLocked) return;

            // --- XỬ LÝ MỌI THÔNG BÁO (SWEETALERT) ---
            const swalConfirm = document.querySelector('.swal2-confirm');
            if (swalConfirm && swalConfirm.offsetParent !== null) {
                // Nếu là popup Tạo Phòng thì điền thông tin trước
                const swalInput1 = document.getElementById('swal-input1');
                if (swalInput1) {
                    if (!swalInput1.value) swalInput1.value = "Phòng Live " + Math.floor(Math.random()*1000);
                    
                    const swalInput2 = document.getElementById('swal-input2');
                    if (swalInput2) {
                        const opts2 = Array.from(swalInput2.options);
                        swalInput2.value = opts2[Math.floor(Math.random() * opts2.length)].value;
                    }
                    const swalInput3 = document.getElementById('swal-input3');
                    if (swalInput3) {
                        const opts3 = Array.from(swalInput3.options);
                        swalInput3.value = opts3[Math.floor(Math.random() * opts3.length)].value;
                    }
                }

                window.botActionLocked = true;
                BotVirtualCursor.moveToElement($(swalConfirm), 1, 0, () => {
                    setTimeout(() => { 
                        swalConfirm.click(); 
                        setTimeout(() => { window.botActionLocked = false; }, 2000); 
                    }, 500);
                });
                return;
            }

            // --- LOGIC SẢNH (LOBBY) ---
            const lobbyRooms = document.getElementById('lobby-rooms');
            if (lobbyRooms && lobbyRooms.offsetParent !== null) {
                const rooms = Array.from(lobbyRooms.children).filter(div => div.innerText.includes('👥'));
                const availableRooms = rooms.filter(div => {
                    const match = div.innerText.match(/👥\s*(\d+)\/5/);
                    return match && parseInt(match[1]) < 5;
                });

                if (availableRooms.length > 0) {
                    // Chọn ngẫu nhiên 1 phòng trống để vào
                    const targetRoom = availableRooms[Math.floor(Math.random() * availableRooms.length)];
                    const btnXem = Array.from(targetRoom.querySelectorAll('button')).find(b => b.innerText.includes('XEM'));
                    if (btnXem) {
                        window.botActionLocked = true; // Khóa luôn vì sẽ load trang khác
                        BotVirtualCursor.moveToElement($(btnXem), 1, 0, () => {
                            setTimeout(() => { btnXem.click(); }, 500);
                        });
                        return;
                    }
                } else {
                    // Không có phòng trống -> Tạo phòng mới
                    const btnTaoPhong = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('+ TẠO PHÒNG MỚI'));
                    if (btnTaoPhong) {
                        window.botActionLocked = true;
                        BotVirtualCursor.moveToElement($(btnTaoPhong), 1, 0, () => {
                            setTimeout(() => { btnTaoPhong.click(); window.botActionLocked = false; }, 500);
                        });
                        return;
                    }
                }
                return; // Đã ở sảnh thì kết thúc vòng lặp, không bấm nút lung tung
            }

            // --- LOGIC TRONG BÀN CHƠI ---
            // Nếu có nút Ngồi thì ưu tiên Ngồi (giới hạn ngẫu nhiên 1 ghế trống)
            const ngoiBtns = Array.from(document.querySelectorAll('.player-cards button')).filter(b => b.innerText.includes('Ngồi'));
            if (ngoiBtns.length > 0) {
                const btn = ngoiBtns[Math.floor(Math.random() * ngoiBtns.length)];
                window.botActionLocked = true;
                BotVirtualCursor.moveToElement($(btn), 1, 0, () => {
                    setTimeout(() => { btn.click(); setTimeout(() => { window.botActionLocked = false; }, 2000); }, 500);
                });
                return;
            }

            // Nếu đang trong game, kiểm tra các nút hành động
            const btnBet = document.getElementById('btn-bet');
            const btnHit = document.getElementById('btn-hit');
            const btnStand = document.getElementById('btn-stand');
            const btnDouble = document.getElementById('btn-double');
            
            let targetBtn = null;
            
            if (btnBet && btnBet.style.display !== 'none' && btnBet.offsetParent !== null) {
                // Tự động nhập tiền cược ngẫu nhiên rồi bấm
                const betInput = document.getElementById('bet-amount');
                if (betInput) {
                    const min = parseInt(betInput.min) || 10000;
                    const max = parseInt(betInput.max) || 5000000;
                    const chips = [min, min*2, min*5, min*10].filter(c => c <= max);
                    betInput.value = chips[Math.floor(Math.random() * chips.length)] || min;
                }
                targetBtn = btnBet;
            } else if (btnHit && btnHit.style.display !== 'none' && btnHit.offsetParent !== null) {
                // Tới lượt bot, tính toán logic Blackjack
                const calcScore = (cards) => {
                    let score = 0, aces = 0;
                    for (let c of cards) {
                        if (['J', 'Q', 'K'].includes(c.value)) score += 10;
                        else if (c.value === 'A') { score += 11; aces++; }
                        else score += parseInt(c.value);
                    }
                    while (score > 21 && aces > 0) { score -= 10; aces--; }
                    return { score, isSoft: aces > 0 && score + 10 <= 21 }; // Soft nếu vẫn còn Ace được tính là 11
                };

                let mySeat = Array.from(document.querySelectorAll('.seat')).find(s => s.dataset.userId == window.currentUserId);
                let pCards = [], dCards = [];
                if (mySeat) {
                    try { pCards = JSON.parse(mySeat.querySelector('.player-cards').dataset.cardString || '[]'); } catch(e){}
                }
                const dContainer = document.getElementById('dealer-cards');
                if (dContainer) {
                    try { dCards = JSON.parse(dContainer.dataset.cardString || '[]'); } catch(e){}
                }

                if (pCards.length >= 2 && dCards.length >= 1) {
                    const pState = calcScore(pCards);
                    const dScore = calcScore([dCards[0]]).score;
                    const score = pState.score;
                    
                    let action = 'stand';
                    if (pState.isSoft) {
                        if (score <= 17) action = 'hit';
                        else if (score == 18) {
                            if (dScore >= 9) action = 'hit';
                            else action = 'stand';
                        } else {
                            action = 'stand';
                        }
                    } else {
                        if (score <= 11) action = 'hit';
                        else if (score == 12) {
                            action = (dScore >= 4 && dScore <= 6) ? 'stand' : 'hit';
                        } else if (score >= 13 && score <= 16) {
                            action = (dScore >= 2 && dScore <= 6) ? 'stand' : 'hit';
                        } else {
                            action = 'stand';
                        }
                    }
                    
                    // Xử lý Double Down 20% liều
                    if (action === 'hit' && pCards.length === 2 && score >= 9 && score <= 11 && btnDouble && btnDouble.style.display !== 'none') {
                        if (Math.random() < 0.2 || (score === 11 && dScore !== 11) || (score === 10 && dScore < 10)) {
                            action = 'double';
                        }
                    }
                    
                    if (action === 'double') targetBtn = btnDouble;
                    else if (action === 'hit') targetBtn = btnHit;
                    else targetBtn = btnStand;
                } else {
                    targetBtn = btnStand; // Fallback
                }
            }
            
            if (targetBtn) {
                window.botActionLocked = true;
                BotVirtualCursor.moveToElement($(targetBtn), 1, 0, () => {
                    setTimeout(() => { 
                        BotVirtualCursor.simulateClick(() => {
                            try { targetBtn.click(); } catch(e){}
                            setTimeout(() => { window.botActionLocked = false; }, 1000);
                        });
                    }, 500);
                });
            }
        }, 3000 + Math.random() * 2000);
    }
