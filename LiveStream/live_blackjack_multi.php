<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'Thần Bài [Live]', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

if (!isset($botUserId)) {
    header("Location: ../login.php");
    exit;
}
require_once '../db_connect.php';
require_once '../load_theme.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Multiplayer Blackjack | Elite Table</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/game-blackjack-multi.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        window.currentUserId = <?= $botUserId ?>;
        window.tableId = <?= isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0 ?>;
        window.themeConfig = {
            particleCount: <?= $particleCount ?>,
            particleSize: <?= $particleSize ?>,
            particleColor: '<?= $particleColor ?>',
            particleOpacity: <?= $particleOpacity ?>,
            shapeCount: <?= $shapeCount ?>,
            shapeColors: <?= json_encode($shapeColors) ?>,
            shapeOpacity: <?= $shapeOpacity ?>,
            bgGradient: <?= json_encode($bgGradient) ?>
        };
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <style>
        body { background: <?= $bgGradientCSS ?> !important; background-attachment: fixed !important; }
        #threejs-background { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: -999; pointer-events: none; }
    </style>
</head>
<body>
    <canvas id="threejs-background"></canvas>
<?php if (!isset($_GET['table_id'])): ?>
    <!-- LOBBY UI -->
    <div style="max-width: 800px; margin: 50px auto; background: rgba(0,0,0,0.8); padding: 30px; border-radius: 20px; color: white;">
        <a href="../index.php" style="position:fixed; top:20px; left:20px; color:#94a3b8; text-decoration:none; font-weight:600; background: rgba(0,0,0,0.5); padding: 10px 20px; border-radius: 10px;">🏠 Ra Trang Chủ</a>
        <h1 style="text-align: center; color: #fbbf24; margin-bottom: 30px;">SẢNH BLACKJACK MULTIPLAYER</h1>
        
        <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
            <h2>Danh Sách Bàn Đang Mở</h2>
            <button onclick="createRoom()" style="background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 10px; font-weight: bold; cursor: pointer;">+ TẠO PHÒNG MỚI</button>
        </div>
        
        <div id="lobby-rooms" style="display: grid; gap: 15px;">
            <!-- Rooms loaded via JS -->
            <div style="text-align:center; padding: 20px;">Đang tải danh sách phòng...</div>
        </div>
    </div>
    
    <script>
        async function loadRooms() {
            const res = await fetch('../api_blackjack_lobby.php?action=list');
            const data = await res.json();
            if(data.success) {
                const container = document.getElementById('lobby-rooms');
                container.innerHTML = data.tables.length === 0 ? '<div style="text-align:center;">Không có phòng nào đang mở.</div>' : data.tables.map(t => `
                    <div style="background: rgba(255,255,255,0.1); padding: 15px 25px; border-radius: 15px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="margin: 0; color: #fbbf24; font-size: 20px;">${t.room_name}</h3>
                            <div style="font-size: 14px; opacity: 0.8; margin-top: 5px;">
                                Cược: ${Number(t.min_bet).toLocaleString()} - ${Number(t.max_bet).toLocaleString()} GTLM
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 20px;">
                            <div style="font-weight: bold;">
                                <span style="color: ${t.status === 'playing' ? '#ef4444' : '#10b981'}">${t.status === 'playing' ? 'Đang Chơi' : 'Đang Chờ'}</span>
                                | 👥 ${t.player_count}/5
                            </div>
                            <button onclick="window.location.href='watch.php?id=16&table_id=${t.id}'" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 8px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 5px;" class="btn-xem">
                                👁️ XEM
                            </button>
                            <button onclick="window.location.href='../games/blackjack_multi.php?id=${t.id}'" style="background: #3b82f6; color: white; border: none; padding: 8px 20px; border-radius: 8px; font-weight: bold; cursor: pointer;">
                                VÀO BÀN
                            </button>
                        </div>
                    </div>
                `).join('');
            }
        }
        
        async function createRoom() {
            const { value: formValues } = await Swal.fire({
                title: 'Tạo Phòng Blackjack',
                html:
                    '<input id="swal-input1" class="swal2-input" placeholder="Tên phòng (VD: Bàn BJ của tôi)">' +
                    '<select id="swal-input2" class="swal2-select" style="display:flex; width: 73%; margin: 1em auto; font-size: 1.125em; padding: 0.75em;">' +
                        '<option value="10000">Cược Tối Thiểu: 10,000 GTLM</option>' +
                        '<option value="50000">Cược Tối Thiểu: 50,000 GTLM</option>' +
                        '<option value="100000">Cược Tối Thiểu: 100,000 GTLM</option>' +
                        '<option value="500000">Cược Tối Thiểu: 500,000 GTLM</option>' +
                        '<option value="1000000">Cược Tối Thiểu: 1,000,000 GTLM</option>' +
                        '<option value="5000000">Cược Tối Thiểu: 5,000,000 GTLM</option>' +
                    '</select>' +
                    '<select id="swal-input3" class="swal2-select" style="display:flex; width: 73%; margin: 1em auto; font-size: 1.125em; padding: 0.75em;">' +
                        '<option value="0">Không có Bot</option>' +
                        '<option value="1">Thêm sẵn 1 Bot</option>' +
                        '<option value="2">Thêm sẵn 2 Bot</option>' +
                        '<option value="3">Thêm sẵn 3 Bot</option>' +
                        '<option value="4">Thêm sẵn 4 Bot</option>' +
                        '<option value="5">Thêm sẵn 5 Bot (Bàn full Bot)</option>' +
                    '</select>',
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Tạo Phòng',
                cancelButtonText: 'Hủy',
                confirmButtonColor: '#10b981',
                preConfirm: () => {
                    const name = document.getElementById('swal-input1').value;
                    const minBet = document.getElementById('swal-input2').value;
                    const botCount = document.getElementById('swal-input3').value;
                    if (!name || !minBet) {
                        Swal.showValidationMessage('Vui lòng nhập đầy đủ thông tin');
                        return false;
                    }
                    return [name, minBet, botCount]
                }
            });

            if (formValues) {
                const name = formValues[0];
                const minBet = formValues[1];
                const botCount = formValues[2];
                
                const fd = new FormData();
                fd.append('room_name', name);
                fd.append('min_bet', minBet);
                fd.append('max_bet', minBet * 100);
                fd.append('bot_count', botCount);
                
                const res = await fetch('../api_blackjack_lobby.php?action=create', { method: 'POST', body: fd });
                const data = await res.json();
                if(data.success) {
                    window.location.href = 'watch.php?id=16&table_id=' + data.table_id;
                } else {
                    Swal.fire('Lỗi', data.message, 'error');
                }
            }
        }
        
        loadRooms();
        setInterval(loadRooms, 3000);
    </script>
<?php else: ?>
    <!-- GAME UI -->

    <div class="table-container">
        <div class="blackjack-table">
            <!-- Dealer -->
            <div class="dealer-area">
                <div style="font-weight: 800; letter-spacing: 2px;">DEALER</div>
                <div class="dealer-cards" id="dealer-cards">
                    <!-- Cards from JS -->
                </div>
            </div>

            <!-- Players Seats -->
            <div class="seats-container">
                <?php for($i=0; $i<5; $i++): ?>
                    <div class="seat" id="seat-<?= $i ?>">
                        <div class="player-cards" id="player-cards-<?= $i ?>"></div>
                        <div class="status-badge" style="position:absolute; top:-30px; width:100%; background:#fbbf24; color:#000; border-radius:10px; font-size:10px; font-weight:800; display:none;">HIT</div>
                        <div class="player-avatar">
                            <img src="https://ui-avatars.com/api/?name=User&background=random" style="width:100%; height:100%;" alt="">
                        </div>
                        <div class="player-name" style="font-weight:600; font-size:12px;">TRỐNG</div>
                        <div class="player-bet" style="font-weight:700; font-size:11px; color:#fbbf24; margin-top: 2px; text-shadow: 1px 1px 2px #000; display:none;">0</div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        
        <div style="text-align:center; margin-top: 10px;">
            <button onclick="window.location.href='blackjack_multi.php'" style="background: rgba(0,0,0,0.5); color: white; border: 1px solid #fff; padding: 8px 15px; border-radius: 10px; cursor: pointer;">⬅ QUAY LẠI SẢNH</button>
            <button id="btn-add-bot" onclick="window.game.addBot()" style="background: #8b5cf6; color: white; border: 1px solid #fff; padding: 8px 15px; border-radius: 10px; cursor: pointer; display: none;">🤖 THÊM BOT</button>
        </div>

        <div class="chip-container" id="chip-container" style="display:none; justify-content:center; gap:10px; margin-top:20px; flex-wrap:wrap; max-width: 600px; margin-left: auto; margin-right: auto;">
            <button class="chip-btn" onclick="document.getElementById('bet-amount').value = 10000">10K</button>
            <button class="chip-btn" onclick="document.getElementById('bet-amount').value = 50000">50K</button>
            <button class="chip-btn" onclick="document.getElementById('bet-amount').value = 100000">100K</button>
            <button class="chip-btn" onclick="document.getElementById('bet-amount').value = 500000">500K</button>
            <button class="chip-btn" onclick="document.getElementById('bet-amount').value = 1000000">1M</button>
            <button class="chip-btn" onclick="document.getElementById('bet-amount').value = 5000000">5M</button>
            <button class="chip-btn btn-allin" onclick="document.getElementById('bet-amount').value = window.currentMoney || 10000" style="background: linear-gradient(45deg, #ef4444, #b91c1c);">ALL IN</button>
        </div>

        <div class="controls" id="game-controls">
            <input type="number" id="bet-amount" value="10000" step="1000" style="background:rgba(255,255,255,0.1); border:1px solid #fff; color:#fff; padding:10px; border-radius:10px; width:120px;">
            <button class="btn-game btn-bet" id="btn-bet">CƯỢC (DEAL)</button>
            <button class="btn-game btn-hit" id="btn-hit">HIT</button>
            <button class="btn-game btn-stand" id="btn-stand">STAND</button>
            <button class="btn-game btn-double" id="btn-double" style="background:#f59e0b;">X2 (DOUBLE)</button>
        </div>

        <a href="../index.php" style="position:fixed; top:20px; left:20px; color:#94a3b8; text-decoration:none; font-weight:600;">🏠 Ra Trang Chủ</a>
    </div>

    <!-- Chat Box -->
    <div class="chat-box">
        <div style="padding:15px; border-bottom:1px solid rgba(255,255,255,0.1); font-weight:800; font-size:12px;">CHAT BÀN CHƠI</div>
        <div class="chat-messages" id="chat-messages"></div>
        <input type="text" class="chat-input" id="chat-input" placeholder="Nhập tin nhắn...">
    </div>

    <script src="../assets/js/game-blackjack-multi.js?v=<?= time() ?>"></script>
<?php endif; ?>

    <!-- Premium Effects Loader -->
    <script>
        (function () {
            const prefix = '../';
            const scripts = ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'];
            scripts.forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src;
                s.async = false;
                document.head.appendChild(s);
            });
        })();
    </script>

<!-- AUTO-GENERATED BOT SCRIPT -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script>
    window.isBotStreamer = true;
    const originalFetch = window.fetch;
    window.fetch = function() {
        if (typeof arguments[0] === 'string' && arguments[0].includes('api_blackjack_multi.php')) {
            arguments[0] += (arguments[0].includes('?') ? '&' : '?') + 'is_bot=1';
        }
        return originalFetch.apply(this, arguments);
    };
</script>
<script>
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
</script>

</body>
</html>
