<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_blackjack_multi', 50000000);
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
        window.tableId = <?= isset($_GET['id']) ? (int)$_GET['id'] : 0 ?>;
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
<?php if (!isset($_GET['id'])): ?>
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
                            <button onclick="window.location.href='blackjack_multi.php?id=${t.id}'" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 8px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                                👁️ XEM
                            </button>
                            <button onclick="window.location.href='blackjack_multi.php?id=${t.id}'" style="background: #3b82f6; color: white; border: none; padding: 8px 20px; border-radius: 8px; font-weight: bold; cursor: pointer;">
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
                    window.location.href = 'blackjack_multi.php?id=' + data.table_id;
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
    if (typeof BotVirtualCursor !== "undefined") {
        BotVirtualCursor.init("Bot Streamer");
        setInterval(() => {
            const allBtns = Array.from(document.querySelectorAll("button, .btn-bet, .chip, .spin-btn, #btnSpin, .bet-button, .card, .btn-primary, .btn-success, input[type='button'], input[type='submit']"));
            const btns = allBtns.filter(b => {
                if(b.offsetParent === null || b.disabled) return false;
                const txt = (b.innerText || b.value || "").toLowerCase();
                const cls = (b.className || "").toLowerCase();
                const id = (b.id || "").toLowerCase();
                
                // Exclude common navigation/help buttons
                if(txt.includes("hướng dẫn") || txt.includes("trang chủ") || txt.includes("nạp") || txt.includes("rút") || txt.includes("lịch sử") || txt.includes("quay lại") || txt.includes("thoát")) return false;
                if(cls.includes("back") || cls.includes("help") || cls.includes("guide") || cls.includes("close") || cls.includes("swal") || cls.includes("nav")) return false;
                if(id.includes("guide") || id.includes("back") || id.includes("close") || id.includes("nav")) return false;
                
                return true;
            });
            
            if(btns.length > 0) {
                const btn = btns[Math.floor(Math.random() * btns.length)];
                BotVirtualCursor.moveToElement($(btn), 1, 0, () => {
                    setTimeout(() => { 
                        BotVirtualCursor.simulateClick(() => {
                            try { btn.click(); } catch(e){}
                        });
                    }, 500);
                });
            }
        }, 3000 + Math.random() * 4000);
    }
</script>

</body>
</html>
