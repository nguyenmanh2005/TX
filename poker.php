<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

// Load theme
require_once 'load_theme.php';

$userId = $_SESSION['Iduser'];
$sql = "SELECT Money, Name FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$soDu = $user['Money'];
$tenNguoiChoi = $user['Name'];

$laBai = [];
$thongBao = "";
$ketQuaClass = "";
$laThang = false;
$loaiBai = "";

$chat = ["♠", "♥", "♦", "♣"];
$so = ["A", "2", "3", "4", "5", "6", "7", "8", "9", "10", "J", "Q", "K"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"]);

    if ($cuoc > $soDu || $cuoc <= 0) {
        $thongBao = "⚠️ Số tiền cược không hợp lệ!";
    } else {
        // Rút 5 lá bài
        $boBai = [];
        foreach ($chat as $c) {
            foreach ($so as $s) {
                $boBai[] = $s . $c;
            }
        }
        shuffle($boBai);
        $laBai = array_slice($boBai, 0, 5);
        
        // Xác định loại bài
        $soBai = [];
        $chatBai = [];
        foreach ($laBai as $bai) {
            $soBai[] = substr($bai, 0, -1);
            $chatBai[] = substr($bai, -1);
        }
        
        $demSo = array_count_values($soBai);
        $demChat = array_count_values($chatBai);
        
        $thang = 0;
        $loaiBai = "";
        
        // Royal Flush
        if (count($demChat) === 1 && in_array("A", $soBai) && in_array("K", $soBai) && in_array("Q", $soBai) && in_array("J", $soBai) && in_array("10", $soBai)) {
            $thang = $cuoc * 100;
            $loaiBai = "Royal Flush";
        }
        // Straight Flush
        elseif (count($demChat) === 1) {
            $thang = $cuoc * 50;
            $loaiBai = "Straight Flush";
        }
        // Four of a Kind
        elseif (max($demSo) === 4) {
            $thang = $cuoc * 25;
            $loaiBai = "Four of a Kind";
        }
        // Full House
        elseif (max($demSo) === 3 && count($demSo) === 2) {
            $thang = $cuoc * 10;
            $loaiBai = "Full House";
        }
        // Flush
        elseif (count($demChat) === 1) {
            $thang = $cuoc * 5;
            $loaiBai = "Flush";
        }
        // Straight
        elseif (count($demSo) === 5) {
            $thang = $cuoc * 3;
            $loaiBai = "Straight";
        }
        // Three of a Kind
        elseif (max($demSo) === 3) {
            $thang = $cuoc * 2;
            $loaiBai = "Three of a Kind";
        }
        // Two Pair
        elseif (count(array_filter($demSo, function($v) { return $v === 2; })) === 2) {
            $thang = $cuoc * 1.5;
            $loaiBai = "Two Pair";
        }
        // One Pair
        elseif (max($demSo) === 2) {
            $thang = $cuoc * 1.2;
            $loaiBai = "One Pair";
        }
        
        if ($thang > 0) {
            $soDu += $thang;
            $thongBao = "🎉 " . $loaiBai . "! Bạn thắng " . number_format($thang) . " VNĐ!";
            $ketQuaClass = "thang";
            $laThang = true;
        } else {
            $soDu -= $cuoc;
            $thongBao = "😢 Không có kết hợp nào! Mất " . number_format($cuoc) . " VNĐ";
            $ketQuaClass = "thua";
        }

        if ($thang >= 5000000) {
            $message = "🎉 " . htmlspecialchars($tenNguoiChoi) . " vừa thắng lớn " . number_format($thang, 0, ',', '.') . " VNĐ trong Poker! 🎊";
            $expiresAt = date('Y-m-d H:i:s', time() + 30);
            $insertSql = "INSERT INTO server_notifications (user_id, user_name, message, amount, notification_type, expires_at) VALUES (?, ?, ?, ?, 'big_win', ?)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("issds", $userId, $tenNguoiChoi, $message, $thang, $expiresAt);
            $insertStmt->execute();
            $insertStmt->close();
        }

        $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        if ($capNhat) {
            $capNhat->bind_param("di", $soDu, $userId);
            $capNhat->execute();
            $capNhat->close();
        }

        // Track quest progress để cập nhật nhiệm vụ & thống kê và tự động cập nhật streak, VIP, reward points, social feed
        require_once 'game_history_helper.php';
        $winAmount = $laThang ? $thang : 0;
        logGameHistoryWithAll($conn, $userId, 'Poker', $cuoc, $winAmount, $laThang);

        // Track tournament progress nếu user đang tham gia giải
        require_once 'tournament_helper.php';
        logTournamentGame($conn, $userId, 'Poker', $cuoc, $winAmount, $laThang);
    }
}

// Luôn reload số dư từ database để đảm bảo chính xác
$reloadSql = "SELECT Money FROM users WHERE Iduser = ?";
$reloadStmt = $conn->prepare($reloadSql);
if ($reloadStmt) {
    $reloadStmt->bind_param("i", $userId);
    $reloadStmt->execute();
    $reloadResult = $reloadStmt->get_result();
    $reloadUser = $reloadResult->fetch_assoc();
    if ($reloadUser) {
        $soDu = $reloadUser['Money'];
    }
    $reloadStmt->close();
}
if ($stmt) {
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Poker</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
        <link rel="stylesheet" href="assets/css/game-effects.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhancements.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
        <link rel="stylesheet" href="assets/css/game-poker.css">
        <link rel="stylesheet" href="assets/css/game-animations.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            font-family: 'Segoe UI', sans-serif;
            text-align: center;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            padding: 50px;
            min-height: 100vh;
            position: relative;
        }
        
        /* Three.js canvas background */
        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }
        
        .game-box {
            position: relative;
            z-index: 1;
        }
        * { cursor: inherit; }
        button, a, input { cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important; }
        .game-box {
            display: inline-block;
            background: rgba(44, 62, 80, 0.95);
            padding: 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(44, 62, 80, 0.4);
            border: 3px solid rgba(255, 255, 255, 0.5);
            max-width: 700px;
        }
        .game-box h1 { color: white; font-size: 28px; margin: 15px 0; }
        .balance { margin: 20px 0; font-size: 22px; font-weight: 700; color: #ffd700; }
        .cards-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 30px 0;
            flex-wrap: wrap;
        }
        .card {
            width: 80px;
            height: 120px;
            background: white;
            border-radius: 10px;
            border: 3px solid #333;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            font-size: 32px;
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            animation: dealCard 0.5s ease;
        }
        .card.red { color: #dc3545; }
        .card.black { color: #000; }
        @keyframes dealCard {
            0% { transform: translateY(-200px) rotate(-180deg); opacity: 0; }
            100% { transform: translateY(0) rotate(0deg); opacity: 1; }
        }
        input {
            padding: 14px 18px;
            margin: 12px;
            font-size: 16px;
            border-radius: var(--border-radius);
            border: 2px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.95);
            width: 80%;
            max-width: 300px;
        }
        button {
            padding: 16px 32px;
            font-size: 18px;
            font-weight: 600;
            border: none;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: var(--border-radius);
            margin: 15px;
        }
        button:hover { transform: translateY(-3px) scale(1.05); }
        .thongbao {
            margin-top: 20px;
            font-size: 20px;
            font-weight: 700;
            padding: 15px 25px;
            border-radius: var(--border-radius);
            min-height: 50px;
        }
        .thongbao.thang { color: #00ff00; background: rgba(40, 167, 69, 0.3); }
        .thongbao.thua { color: #ff6b6b; background: rgba(220, 53, 69, 0.3); }
        .hand-type {
            font-size: 24px;
            font-weight: 700;
            color: #ffd700;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <canvas id="threejs-background"></canvas>
<?php if ($laThang): ?>
    <canvas id="phaohoa"></canvas>
    <script>
        const canvas = document.getElementById("phaohoa");
        const ctx = canvas.getContext("2d");
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        let particles = [];
        function createFirework() {
            let x = Math.random() * canvas.width;
            let y = Math.random() * canvas.height / 2;
            let colors = ["#ffd700", "#ff6b6b", "#4ecdc4", "#45b7d1"];
            for (let i = 0; i < 30; i++) {
                let angle = (Math.PI * 2 * i) / 30;
                let speed = 2 + Math.random() * 2;
                particles.push({
                    x: x, y: y,
                    dx: Math.cos(angle) * speed,
                    dy: Math.sin(angle) * speed,
                    life: 50, maxLife: 50,
                    color: colors[Math.floor(Math.random() * colors.length)],
                    size: 2
                });
            }
        }
        function update() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            for (let i = particles.length - 1; i >= 0; i--) {
                let p = particles[i];
                p.x += p.dx; p.y += p.dy; p.dy += 0.1; p.life--;
                if (p.life <= 0) { particles.splice(i, 1); continue; }
                let alpha = p.life / p.maxLife;
                ctx.globalAlpha = alpha;
                ctx.fillStyle = p.color;
                ctx.fillRect(p.x, p.y, 3, 3);
            }
            ctx.globalAlpha = 1;
        }
        for (let i = 0; i < 2; i++) {
            setTimeout(() => createFirework(), i * 500);
        }
        let updateInterval = setInterval(update, 50);
        setTimeout(() => {
            clearInterval(updateInterval);
            document.getElementById("phaohoa").remove();
        }, 3000);
    </script>
<?php endif; ?>

<div class="poker-container-enhanced">
    <div class="game-box-poker-enhanced">
        <div class="game-header-poker-enhanced">
            <h1 class="game-title-poker-enhanced">🃏 Poker</h1>
            <div class="balance-poker-enhanced">
                <span>💰</span>
                <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
            </div>
        </div>
    
    <?php if (!empty($laBai)): ?>
            <div class="cards-container-poker-enhanced">
            <?php foreach ($laBai as $bai): ?>
                <?php 
                $mau = (strpos($bai, "♥") !== false || strpos($bai, "♦") !== false) ? "red" : "black";
                    $suit = substr($bai, -1);
                    $value = substr($bai, 0, -1);
                ?>
                    <div class="card-poker-enhanced <?= $mau ?>">
                        <span class="card-suit-poker top-left"><?= $suit ?></span>
                        <span class="card-value-poker"><?= $value ?></span>
                        <span class="card-suit-poker bottom-right"><?= $suit ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($loaiBai): ?>
                <div class="hand-type-poker-enhanced"><?= $loaiBai ?></div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="payout-table-poker-enhanced">
            <div class="payout-table-title-poker">📊 Bảng Thanh Toán</div>
            <div class="payout-list-poker">
                <div class="payout-item-poker"><span class="hand-name">Royal Flush</span><span class="multiplier">x100</span></div>
                <div class="payout-item-poker"><span class="hand-name">Straight Flush</span><span class="multiplier">x50</span></div>
                <div class="payout-item-poker"><span class="hand-name">Four of a Kind</span><span class="multiplier">x25</span></div>
                <div class="payout-item-poker"><span class="hand-name">Full House</span><span class="multiplier">x10</span></div>
                <div class="payout-item-poker"><span class="hand-name">Flush</span><span class="multiplier">x5</span></div>
                <div class="payout-item-poker"><span class="hand-name">Straight</span><span class="multiplier">x3</span></div>
                <div class="payout-item-poker"><span class="hand-name">Three of a Kind</span><span class="multiplier">x2</span></div>
                <div class="payout-item-poker"><span class="hand-name">Two Pair</span><span class="multiplier">x1.5</span></div>
                <div class="payout-item-poker"><span class="hand-name">One Pair</span><span class="multiplier">x1.2</span></div>
            </div>
        </div>
        
        <div class="game-controls-poker-enhanced">
    <form method="post">
                <div class="control-group-poker-enhanced">
                    <label class="control-label-poker-enhanced">💰 Số tiền cược:</label>
                    <input type="number" name="cuoc" id="cuocInput" class="control-input-poker-enhanced" placeholder="Nhập số tiền cược" required min="1">
                    <div class="bet-quick-amounts-poker-enhanced">
                        <button type="button" class="bet-quick-btn-poker-enhanced" data-amount="10000">10K</button>
                        <button type="button" class="bet-quick-btn-poker-enhanced" data-amount="50000">50K</button>
                        <button type="button" class="bet-quick-btn-poker-enhanced" data-amount="100000">100K</button>
                        <button type="button" class="bet-quick-btn-poker-enhanced" data-amount="200000">200K</button>
                        <button type="button" class="bet-quick-btn-poker-enhanced" data-amount="500000">500K</button>
                    </div>
                </div>
                <button type="submit" class="deal-button-poker-enhanced">🃏 Rút Bài</button>
    </form>
        </div>

    <?php if ($thongBao): ?>
            <div class="result-banner-poker-enhanced <?= $ketQuaClass === 'thang' ? 'win' : 'lose' ?>">
            <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
        
        <p style="text-align: center; margin-top: 20px;">
            <a href="index.php" style="color: #2c3e50; text-decoration: none; font-weight: 600; font-size: 16px;">🏠 Quay Lại Trang Chủ</a>
        </p>
    </div>
</div>

<script>
    // Initialize Three.js Background
    (function() {
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
        const script = document.createElement('script');
        script.src = 'threejs-background.js';
        script.onload = function() { console.log('Three.js background loaded'); };
        document.head.appendChild(script);
    })();
</script>

    <script src="assets/js/game-poker.js"></script>
    <script src="assets/js/game-animations-enhanced.js"></script>
    <script src="assets/js/game-effects.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/game-effects-auto.js"></script>
        <script src="assets/js/game-enhancements.js"></script>
<script>
    // Auto initialize game effects
    if (typeof GameEffectsAuto !== 'undefined') {
        GameEffectsAuto.init();
    }
</script>
</body>
</html>

