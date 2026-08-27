<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_31', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

if (!isset($botUserId)) {
    header("Location: ../login.php");
    exit;
}
require_once '../db_connect.php';
require_once '../load_theme.php';

// Fetch current balance
$userId = $botUserId;
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$currentBalance = $user['Money'];
$stmt->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>PvP Horse Racing | Real-time Arena</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/game-horserace-pvp.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Background Dynamic */
        canvas#bg { position: fixed; top: 0; left: 0; z-index: -1; }
    </style>
</head>
<body>
    <canvas id="bg"></canvas>

    <div class="race-container">
        <header style="text-align: center; margin-bottom: 30px;">
            <h1 style="font-size: 3rem; font-weight: 800; margin: 0; background: linear-gradient(to right, #818cf8, #f472b6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">PVP HORSE RACING</h1>
            <p style="color: #94a3b8;">Chọn ngựa, đặt cược và cùng hàng ngàn người xem trực tiếp!</p>
            <div style="margin-top: 10px; font-size: 1.2rem; color: #fff;">
                Số dư: <strong style="color: #4ade80;" id="user-balance"><?= number_format($currentBalance, 0, ',', '.') ?></strong> GTLM
            </div>
        </header>

        <div class="status-bar">
            <span id="room-status">ĐANG KHỞI TẠO...</span>
            <div class="countdown" id="countdown-timer">--s</div>
        </div>

        <div class="racetrack">
            <div class="finish-line"></div>
            <?php for($i=1; $i<=6; $i++): ?>
                <div class="lane" id="lane-<?= $i ?>">
                    <div class="horse-wrapper" id="horse-<?= $i ?>">
                        <div class="horse-info">#<?= $i ?> Horse</div>
                        <div class="horse-sprite">🐎</div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>

        <div class="betting-panel">
            <h2 style="text-align: center; margin-bottom: 20px;">CHỌN CHIẾN MÃ CỦA BẠN</h2>
            <div class="betting-grid">
                <?php for($i=1; $i<=6; $i++): ?>
                    <div class="horse-bet-card" data-id="<?= $i ?>">
                        <div style="font-size: 2rem;">🐎</div>
                        <div style="font-weight: 800; color: #f59e0b;">#<?= $i ?></div>
                        <div style="font-size: 12px; color: #94a3b8;">X6.0 Payout</div>
                    </div>
                <?php endfor; ?>
            </div>

            <div style="text-align: center; margin-top: 30px; display: flex; justify-content: center; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="number" id="bet-amount" value="10000" min="1000" style="padding: 15px; border-radius: 15px; border: 2px solid #6366f1; background: rgba(0,0,0,0.5); color: #fff; font-size: 1.2rem; font-weight: 800; width: 200px; text-align: center; outline: none;" placeholder="Số GTLM cược">
                <button id="place-bet-btn" style="background: linear-gradient(135deg, #6366f1, #a855f7); border: none; color: #fff; padding: 15px 40px; border-radius: 15px; font-size: 1.2rem; font-weight: 800; cursor: pointer; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);">
                    ĐẶT CƯỢC
                </button>
            </div>
        </div>

        <div class="player-list">
            <h3><i class="fa fa-users"></i> NGƯỜI CHƠI ĐÃ CƯỢC</h3>
            <div id="player-bets-list">
                <!-- Data from JS -->
            </div>
        </div>

        <div style="text-align: center; margin-top: 40px;">
            <a href="../index.php" style="color: #94a3b8; text-decoration: none; font-weight: 600;">🏠 QUAY LẠI TRANG CHỦ</a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script>
        // Background initialization
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
        const script = document.createElement('script'); script.src = '../threejs-background.js'; document.head.appendChild(script);
    </script>
    <script src="../assets/js/game-horserace-pvp.js"></script>

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
