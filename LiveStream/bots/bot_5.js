/**
 * 🤖 BOT STREAMER PRO V3 - LONG HỔ TRANH BÁ (DRAGON - TIE - TIGER)
 * - Tự động nhận diện và phân tích 3 cửa: Rồng (Dragon), Hòa (Tie), Hổ (Tiger).
 * - Quản lý vốn thông minh: Tự chọn phỉnh cược theo túi GTLM, NGĂN CHẶN TUYỆT ĐỐI bấm nút XÓA.
 * - Quy trình người thật: Chọn ô cược -> Chọn phỉnh cược -> Bấm QUYẾT ĐẤU -> Bấm VÁN MỚI.
 */
if (typeof BotVirtualCursor !== "undefined") {
    BotVirtualCursor.init("Thần Bài Rồng Hổ 🐯🐉");
    let botRunning = false;
    let matchCount = 0;
    let lastKnownBalance = null;
    let lastBetSideName = 'Rồng 🐉';

    const CASINO_CHATS = {
        bet: [
            "Thế trận Long Hổ cuộn trào! Ra chiêu đặt trọn niềm tin vào cửa {side}! 🐉🐯⚡",
            "Cầu bệt đang lộ rõ, dồn hỏa lực vào {side} bắt trọn lộc lá GTLM! 🎯🔥",
            "Ra chiêu dứt khoát không do dự, quyết đấu trận địa phân định giang sơn! ⚔️💎",
            "Bắt nhịp cầu chuẩn chỉ, vào tiền cửa {side} húp GTLM nào anh em! 🌟💰"
        ],
        win: [
            "HÚP NGẬP MẶT CỬA {side}! Điểm số áp đảo, GTLM về đầy nick! 🏆🎉💰",
            "Đoán cầu như thần! Long Hổ hiển linh ăn trọn ván giao lưu này! 👑🐯🐉✨",
            "Gáy khét lẹt cả sảnh Long Hổ! Tiền cộng rực rỡ vào tài khoản! 💎🤑"
        ],
        loss: [
            "Bị đối phương ép 1 nút, bay màu tiếng này hơi cay! 😅💔",
            "Tạm thời về cõi một ván, hồi sức ra chiêu bẻ cầu ngay lập tức! 💪🔄",
            "Lệch một nhịp bài làm bốc hơi GTLM, ván sau tất tay phục thù! 🛡️⚡"
        ]
    };

    function sendChat(type, replaceObj = {}) {
        if (typeof BotChat !== 'undefined' && BotChat.send) {
            const list = CASINO_CHATS[type] || CASINO_CHATS.bet;
            let msg = list[Math.floor(Math.random() * list.length)];
            for (let k in replaceObj) {
                msg = msg.replace(new RegExp(`\\{${k}\\}`, 'g'), replaceObj[k]);
            }
            BotChat.send(5, 'Thần Bài Rồng Hổ 🐯🐉', msg);
        }
    }

    function runSmartBot5() {
        if (botRunning) return;

        // ── 1. GIAI ĐOẠN MỞ BÀI XONG: BẤM "VÁN MỚI" ──
        const resetSection = document.getElementById('reset-section');
        const resetBtn = document.getElementById('reset-btn');
        if (resetSection && resetSection.style.display !== 'none' && resetBtn) {
            botRunning = true;
            const balanceText = document.getElementById('balance-val');
            if (balanceText && lastKnownBalance !== null) {
                const curBal = parseInt(balanceText.innerText.replace(/\./g, '')) || 0;
                if (curBal > lastKnownBalance) {
                    if (Math.random() < 0.6) sendChat('win', { side: lastBetSideName });
                } else if (curBal < lastKnownBalance) {
                    if (Math.random() < 0.5) sendChat('loss');
                }
                lastKnownBalance = curBal;
            }
            setTimeout(() => {
                BotVirtualCursor.moveToElement($(resetBtn), 0.3, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try { resetBtn.click(); } catch (e) { }
                        botRunning = false;
                        setTimeout(runSmartBot5, 400);
                    });
                });
            }, 1200 + Math.random() * 600);
            return;
        }

        // ── 2. GIAI ĐOẠN ĐẶT CƯỢC ──
        const dealBtn = document.getElementById('deal-btn');
        if (!dealBtn || $(dealBtn).is(':hidden')) {
            setTimeout(runSmartBot5, 500);
            return;
        }

        botRunning = true;
        matchCount++;

        const balanceText = document.getElementById('balance-val');
        let balance = 50000000;
        if (balanceText) {
            balance = parseInt(balanceText.innerText.replace(/\./g, '')) || 0;
            if (lastKnownBalance === null) lastKnownBalance = balance;
        }

        // ── LỰA CHỌN CỬA CƯỢC (DRAGON / TIE / TIGER) ──
        let mainSide = 'dragon';
        const randSide = Math.random();
        if (randSide < 0.48) {
            mainSide = 'dragon'; // 🐉 Cửa Rồng
            lastBetSideName = 'Rồng 🐉';
        } else if (randSide < 0.92) {
            mainSide = 'tiger';  // 🐯 Cửa Hổ
            lastBetSideName = 'Hổ 🐯';
        } else {
            mainSide = 'tie';    // 🤝 Cửa Hòa 1 ăn 8
            lastBetSideName = 'Hòa 🤝';
        }

        // 20% cơ hội chơi chiến thuật cao thủ: Cược chính Rồng/Hổ + Lót thêm cửa Hòa (Tie)
        const alsoBetTie = (mainSide !== 'tie' && Math.random() < 0.20);

        // ── LỌC VÀ CHỌN PHỈNH CƯỢC (NGĂN CHẶN 100% NÚT XÓA) ──
        const allChips = Array.from(document.querySelectorAll('.chip'));
        // Loại bỏ hoàn toàn phỉnh có data-value = 0 hoặc chữ XÓA
        const validChips = allChips.filter(c => {
            const val = parseInt(c.getAttribute('data-value')) || 0;
            const txt = (c.innerText || '').trim().toUpperCase();
            return val > 0 && !txt.includes('XÓA') && !txt.includes('DELETE') && !txt.includes('CLEAR');
        });

        // Lựa chọn mức phỉnh thông minh tương ứng với số dư
        let targetChipVal = 100000;
        if (balance < 500000) {
            targetChipVal = 10000;
        } else if (balance < 2000000) {
            targetChipVal = (Math.random() < 0.7 ? 50000 : 100000);
        } else if (balance < 10000000) {
            targetChipVal = (Math.random() < 0.5 ? 100000 : 500000);
        } else if (balance < 50000000) {
            targetChipVal = (Math.random() < 0.6 ? 500000 : 1000000);
        } else {
            targetChipVal = (Math.random() < 0.5 ? 1000000 : 5000000);
        }

        // Tìm phần tử phỉnh cược hợp lệ
        let selectedChipEl = validChips.find(c => parseInt(c.getAttribute('data-value')) === targetChipVal);
        if (!selectedChipEl) {
            selectedChipEl = validChips.find(c => (parseInt(c.getAttribute('data-value')) || 0) > 0) || validChips[0];
        }

        const mainBoxEl = document.getElementById('box-' + mainSide);
        const tieBoxEl = document.getElementById('box-tie');
        const tieChipEl = validChips.find(c => parseInt(c.getAttribute('data-value')) === 10000 || parseInt(c.getAttribute('data-value')) === 50000) || validChips[0];

        // ── THỰC THI QUY TRÌNH THAO TÁC CƯỢC ──

        // BƯỚC 1: Chọn Ô Cược Chính (Dragon / Tiger / Tie)
        BotVirtualCursor.moveToElement($(mainBoxEl), 0.28, 0, () => {
            BotVirtualCursor.simulateClick(() => {
                try { mainBoxEl.click(); } catch (e) { }

                // BƯỚC 2: Chọn Phỉnh Cược Hợp Lệ (Không bao giờ click XÓA)
                setTimeout(() => {
                    BotVirtualCursor.moveToElement($(selectedChipEl), 0.25, 0, () => {
                        BotVirtualCursor.simulateClick(() => {
                            try { selectedChipEl.click(); } catch (e) { }

                            // BƯỚC 2.5 (Tùy chọn): Lót thêm cửa Hòa (Tie 1 ăn 8)
                            if (alsoBetTie && tieBoxEl && tieChipEl) {
                                setTimeout(() => {
                                    BotVirtualCursor.moveToElement($(tieBoxEl), 0.25, 0, () => {
                                        BotVirtualCursor.simulateClick(() => {
                                            try { tieBoxEl.click(); } catch (e) { }
                                            setTimeout(() => {
                                                BotVirtualCursor.moveToElement($(tieChipEl), 0.22, 0, () => {
                                                    BotVirtualCursor.simulateClick(() => {
                                                        try { tieChipEl.click(); } catch (e) { }
                                                        // Chuyển sang bấm Quyết Đấu
                                                        triggerDeal();
                                                    });
                                                });
                                            }, 150);
                                        });
                                    });
                                }, 180);
                            } else {
                                // Chuyển sang bấm Quyết Đấu trực tiếp
                                triggerDeal();
                            }
                        });
                    });
                }, 180);
            });
        });

        // BƯỚC 3: Rê chuột dứt khoát bấm "QUYẾT ĐẤU"
        function triggerDeal() {
            setTimeout(() => {
                BotVirtualCursor.moveToElement($(dealBtn), 0.28, 0, () => {
                    BotVirtualCursor.simulateClick(() => {
                        try { 
                            dealBtn.click(); 
                            if (Math.random() < 0.4) sendChat('bet', { side: lastBetSideName });
                        } catch (e) { }
                        botRunning = false;
                        setTimeout(runSmartBot5, 1200);
                    });
                });
            }, 200 + Math.random() * 150);
        }
    }

    // Khởi động vòng lặp kiểm tra
    setInterval(runSmartBot5, 500);
}
