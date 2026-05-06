<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';
require_once 'user_progress_helper.php';

// Kiểm tra kết nối database
if (!$conn || $conn->connect_error) {
    die("Lỗi kết nối database: " . ($conn ? $conn->connect_error : "Không thể kết nối"));
}

// Load theme
require_once 'load_theme.php';

$userId = $_SESSION['Iduser'];
$sql = "SELECT Money, Name FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Lỗi prepare statement: " . $conn->error);
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) {
    die("Không tìm thấy thông tin người dùng!");
}
$soDu = $user['Money'];
$tenNguoiChoi = $user['Name'];

// Lấy kết quả từ session (nếu có)
$ketQua = $_SESSION['coinflip_result'] ?? "";
$thongBao = $_SESSION['coinflip_message'] ?? "";
$ketQuaClass = $_SESSION['coinflip_class'] ?? "";
$laThang = $_SESSION['coinflip_win'] ?? false;

// Xóa session sau khi lấy
unset($_SESSION['coinflip_result']);
unset($_SESSION['coinflip_message']);
unset($_SESSION['coinflip_class']);
unset($_SESSION['coinflip_win']);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'flip_coin') {
    $chon = $_POST["chon"] ?? "";
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");

    if (!in_array($chon, ["Ngửa", "Sấp"])) {
        $_SESSION['coinflip_message'] = "❌ Chọn Ngửa hoặc Sấp!";
        $_SESSION['coinflip_class'] = "thua";
    } elseif ($cuoc > $soDu || $cuoc <= 0) {
        $_SESSION['coinflip_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['coinflip_class'] = "thua";
    } else {
        $ketQua = (rand(0, 1) === 0) ? "Ngửa" : "Sấp";
        
        if ($chon === $ketQua) {
            $soDu += $cuoc;
            $_SESSION['coinflip_message'] = "🎉 Bạn thắng " . number_format($cuoc) . " VNĐ! Kết quả: " . $ketQua;
            $_SESSION['coinflip_class'] = "thang";
            $_SESSION['coinflip_win'] = true;
        } else {
            $soDu -= $cuoc;
            $_SESSION['coinflip_message'] = "😢 Bạn mất " . number_format($cuoc) . " VNĐ. Kết quả: " . $ketQua;
            $_SESSION['coinflip_class'] = "thua";
            $_SESSION['coinflip_win'] = false;
        }

        // Cập nhật số dư
        $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        if ($capNhat) {
            $capNhat->bind_param("di", $soDu, $userId);
            $capNhat->execute();
            $capNhat->close();
        }
        
        // Track quest progress + XP cho game và tự động cập nhật streak, VIP, reward points, social feed
        require_once 'game_history_helper.php';
        $winAmount = $laThang ? $cuoc : 0;
        logGameHistoryWithAll($conn, $userId, 'Coin Flip', $cuoc, $winAmount, $laThang);

        // Cộng XP: chơi được XP cơ bản, thắng được thêm bonus theo tiền cược
        $baseXp = 5; // mỗi ván
        $betXp = (int)round(min($cuoc, 50000) / 10000) * 2; // thêm chút XP theo mức cược, giới hạn
        $winBonusXp = $laThang ? (int)round($cuoc / 10000) * 5 : 0;
        $totalXp = $baseXp + $betXp + $winBonusXp;
        up_add_xp($conn, $userId, $totalXp);
        
        // Lưu kết quả vào session
        $_SESSION['coinflip_result'] = $ketQua;
        
        // Redirect để tránh resubmit
        header("Location: coinflip.php");
        exit();
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
    <title>Tung Đồng Xu</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
        <link rel="stylesheet" href="assets/css/game-effects.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhancements.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
        <link rel="stylesheet" href="assets/css/game-coinflip.css">
        <link rel="stylesheet" href="assets/css/game-animations.css">
        <link rel="stylesheet" href="assets/css/game-specific-animations.css">
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
        button, a, input, select { cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important; }
        .game-box {
            display: inline-block;
            background: rgba(245, 87, 108, 0.95);
            padding: 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(245, 87, 108, 0.4);
            border: 3px solid rgba(255, 255, 255, 0.5);
            max-width: 600px;
        }
        .game-box h1 { color: white; font-size: 28px; margin: 15px 0; }
        .balance { margin: 20px 0; font-size: 22px; font-weight: 700; color: #ffd700; }
        .coin {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            margin: 30px auto;
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            border: 10px solid #b8860b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 80px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: flipCoin 1s ease;
        }
        @keyframes flipCoin {
            0% { transform: rotateY(0deg) scale(1); }
            50% { transform: rotateY(1800deg) scale(1.2); }
            100% { transform: rotateY(3600deg) scale(1); }
        }
        .coin.ngua::before { content: "🪙"; }
        .coin.sap::before { content: "🪙"; transform: rotateY(180deg); }
        select, input {
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
        .result-text {
            font-size: 32px;
            font-weight: 700;
            margin: 20px 0;
            color: white;
        }
        .thang { color: #00ff00; }
        .thua { color: #ff6b6b; }
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

<div class="coinflip-container-enhanced">
    <div class="game-box-coinflip-enhanced">
        <div class="game-header-coinflip-enhanced">
            <h1 class="game-title-coinflip-enhanced">🪙 Tung Đồng Xu</h1>
            <div class="balance-coinflip-enhanced">
                <span>💰</span>
                <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
            </div>
        </div>
        
        <div class="coin-display-enhanced">
            <div class="coin-enhanced <?= $ketQua ? (strtolower($ketQua) === 'ngửa' ? '' : 'show-back') : '' ?>" data-coin-result="<?= htmlspecialchars($ketQua ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <div class="coin-face-enhanced front">🪙</div>
                <div class="coin-face-enhanced back">🪙</div>
            </div>
        </div>
    
    <?php if ($ketQua): ?>
            <div class="result-text-enhanced <?= $ketQuaClass ?>" style="text-align: center; font-size: 24px; font-weight: 700; margin: 20px 0; color: #2c3e50;">
                Kết quả: <?= htmlspecialchars($ketQua, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        
        <div class="choice-buttons-enhanced">
            <div class="choice-btn-enhanced" data-choice="Ngửa">
                <span class="choice-icon-enhanced">🪙</span>
                <span class="choice-label-enhanced">Ngửa</span>
                <span class="choice-multiplier-enhanced">x2.0</span>
            </div>
            <div class="choice-btn-enhanced" data-choice="Sấp">
                <span class="choice-icon-enhanced">🪙</span>
                <span class="choice-label-enhanced">Sấp</span>
                <span class="choice-multiplier-enhanced">x2.0</span>
            </div>
        </div>
    
        <div class="game-controls-coinflip-enhanced">
    <form method="post" id="gameForm">
        <input type="hidden" name="action" value="flip_coin">
                <input type="hidden" name="chon" id="chonInput" value="">
                
                <div class="control-group-coinflip-enhanced">
                    <label class="control-label-coinflip-enhanced">💰 Số tiền cược:</label>
                    <input type="number" name="cuoc" id="cuocInput" class="control-input-coinflip-enhanced" placeholder="Nhập số tiền cược" required min="1">
                    <div class="bet-quick-amounts-coinflip-enhanced">
                        <button type="button" class="bet-quick-btn-coinflip-enhanced" data-amount="10000">10K</button>
                        <button type="button" class="bet-quick-btn-coinflip-enhanced" data-amount="50000">50K</button>
                        <button type="button" class="bet-quick-btn-coinflip-enhanced" data-amount="100000">100K</button>
                        <button type="button" class="bet-quick-btn-coinflip-enhanced" data-amount="200000">200K</button>
                        <button type="button" class="bet-quick-btn-coinflip-enhanced" data-amount="500000">500K</button>
                    </div>
                </div>
                
                <button type="submit" id="flipButton" class="flip-button-coinflip-enhanced">🪙 Tung Đồng Xu</button>
    </form>
        </div>

    <?php if ($thongBao): ?>
            <div class="result-banner-coinflip-enhanced <?= $ketQuaClass === 'thang' ? 'win' : 'lose' ?>">
            <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
        
        <p style="text-align: center; margin-top: 20px;">
            <a href="index.php" style="color: #f39c12; text-decoration: none; font-weight: 600; font-size: 16px;">🏠 Quay Lại Trang Chủ</a>
        </p>
    </div>
</div>

<script>
    // Reset form sau khi hiển thị kết quả
    <?php if ($thongBao): ?>
        setTimeout(function() {
            document.getElementById('gameForm').reset();
            document.getElementById('cuocInput').value = '';
            document.getElementById('chonSelect').selectedIndex = 0;
            // Scroll to form
            document.getElementById('gameForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 3000);
    <?php endif; ?>
</script>

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

    <script src="assets/js/game-effects.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="assets/js/game-effects-auto.js"></script>

        <script src="assets/js/game-enhancements.js"></script>
        <script src="assets/js/game-coinflip.js"></script>
        <script src="assets/js/game-animations-enhanced.js"></script>
<script>
    // Auto initialize game effects
    if (typeof GameEffectsAuto !== 'undefined') {
        GameEffectsAuto.init();
    }
</script>
</body>
</html>


