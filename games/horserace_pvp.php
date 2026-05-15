<?php
session_start();
if (!isset($_SESSION['Iduser'])) {
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
    <title>PvP Horse Racing | Real-time Arena</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/game-horserace-pvp.css">
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

            <div style="text-align: center; margin-top: 30px;">
                <button id="place-bet-btn" style="background: linear-gradient(135deg, #6366f1, #a855f7); border: none; color: #fff; padding: 15px 60px; border-radius: 15px; font-size: 1.2rem; font-weight: 800; cursor: pointer; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);">
                    ĐẶT CƯỢC 10.000 GTLM
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
</body>
</html>
