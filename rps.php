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

// Lấy kết quả từ session (nếu có)
$botChon = $_SESSION['rps_bot'] ?? "";
$userChon = $_SESSION['rps_user'] ?? "";
$thongBao = $_SESSION['rps_message'] ?? "";
$ketQuaClass = $_SESSION['rps_class'] ?? "";
$laThang = $_SESSION['rps_win'] ?? false;

// Xóa session sau khi lấy
unset($_SESSION['rps_bot']);
unset($_SESSION['rps_user']);
unset($_SESSION['rps_message']);
unset($_SESSION['rps_class']);
unset($_SESSION['rps_win']);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'play_rps') {
    $chon = $_POST["chon"] ?? "";
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");

    if (!in_array($chon, ["Đá", "Giấy", "Kéo"])) {
        $_SESSION['rps_message'] = "❌ Chọn Đá, Giấy hoặc Kéo!";
        $_SESSION['rps_class'] = "thua";
    } elseif ($cuoc > $soDu || $cuoc <= 0) {
        $_SESSION['rps_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['rps_class'] = "thua";
    } else {
        $botChon = ["Đá", "Giấy", "Kéo"][rand(0, 2)];
        
        // Logic thắng thua
        if ($chon === $botChon) {
            // Hòa
            $_SESSION['rps_message'] = "🤝 Hòa! Cả hai chọn " . $chon . ". Hoàn tiền cược!";
            $_SESSION['rps_class'] = "";
            $_SESSION['rps_win'] = false;
        } elseif (
            ($chon === "Đá" && $botChon === "Kéo") ||
            ($chon === "Giấy" && $botChon === "Đá") ||
            ($chon === "Kéo" && $botChon === "Giấy")
        ) {
            // Thắng
            $soDu += $cuoc;
            $_SESSION['rps_message'] = "🎉 Bạn thắng! Bạn: " . $chon . " - Bot: " . $botChon . ". Nhận " . number_format($cuoc) . " VNĐ!";
            $_SESSION['rps_class'] = "thang";
            $_SESSION['rps_win'] = true;
        } else {
            // Thua
            $soDu -= $cuoc;
            $_SESSION['rps_message'] = "😢 Bạn thua! Bạn: " . $chon . " - Bot: " . $botChon . ". Mất " . number_format($cuoc) . " VNĐ";
            $_SESSION['rps_class'] = "thua";
            $_SESSION['rps_win'] = false;
        }

        // Cập nhật số dư
        $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        if ($capNhat) {
            $capNhat->bind_param("di", $soDu, $userId);
            $capNhat->execute();
            $capNhat->close();
        }
        
        // Track quest progress và tự động cập nhật streak, VIP, reward points, social feed
        require_once 'game_history_helper.php';
        $winAmount = $laThang ? $cuoc : 0;
        logGameHistoryWithAll($conn, $userId, 'RPS', $cuoc, $winAmount, $laThang);
        
        // Lưu kết quả vào session
        $_SESSION['rps_bot'] = $botChon;
        $_SESSION['rps_user'] = $chon;
        
        // Redirect để tránh resubmit
        header("Location: rps.php");
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

$emoji = ["Đá" => "👊", "Giấy" => "✋", "Kéo" => "✌️"];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Oẳn Tù Tì</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
        <link rel="stylesheet" href="assets/css/game-effects.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhancements.css">
        <link rel="stylesheet" href="assets/css/game-effects.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhancements.css">
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
            background: rgba(79, 172, 254, 0.95);
            padding: 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(79, 172, 254, 0.4);
            border: 3px solid rgba(255, 255, 255, 0.5);
            max-width: 600px;
        }
        .game-box h1 { color: white; font-size: 28px; margin: 15px 0; }
        .balance { margin: 20px 0; font-size: 22px; font-weight: 700; color: #ffd700; }
        .vs-container {
            display: flex;
            justify-content: space-around;
            align-items: center;
            margin: 30px 0;
        }
        .choice {
            font-size: 100px;
            animation: choicePop 0.6s ease;
        }
        @keyframes choicePop {
            0% { transform: scale(0) rotate(-180deg); }
            100% { transform: scale(1) rotate(0deg); }
        }
        .vs-text {
            font-size: 48px;
            font-weight: 700;
            color: white;
        }
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

<div class="game-box game-container">
    <h1 class="game-title">✌️ Oẳn Tù Tì</h1>
    <div class="balance">💰 Số dư: <b><?= number_format($soDu, 0, ',', '.') ?> VNĐ</b></div>
    
    <?php if ($botChon && $userChon): ?>
        <div class="vs-container">
            <div>
                <div style="font-size: 24px; color: white; margin-bottom: 10px;">Bạn</div>
                <div class="choice"><?= $emoji[$userChon] ?></div>
                <div style="font-size: 20px; color: white; margin-top: 10px;"><?= $userChon ?></div>
            </div>
            <div class="vs-text">VS</div>
            <div>
                <div style="font-size: 24px; color: white; margin-bottom: 10px;">Bot</div>
                <div class="choice"><?= $emoji[$botChon] ?></div>
                <div style="font-size: 20px; color: white; margin-top: 10px;"><?= $botChon ?></div>
            </div>
        </div>
    <?php endif; ?>
    
    <form method="post" id="gameForm">
        <input type="hidden" name="action" value="play_rps">
        <label style="display: block; margin: 15px 0 8px; font-weight: 600; color: white; font-size: 16px;">✌️ Chọn của bạn:</label>
        <select name="chon" id="chonSelect" required>
            <option value="">-- Chọn --</option>
            <option value="Đá">👊 Đá</option>
            <option value="Giấy">✋ Giấy</option>
            <option value="Kéo">✌️ Kéo</option>
        </select><br>
        <label style="display: block; margin: 15px 0 8px; font-weight: 600; color: white; font-size: 16px;">💰 Số tiền cược:</label>
        <input type="number" name="cuoc" id="cuocInput" placeholder="Nhập số tiền cược" required min="1" value=""><br>
        <button type="submit" id="submitBtn" class="btn-game">✌️ Chơi Ngay</button>
        <p><a href="index.php" style="color: white; text-decoration: none;">🏠 Quay Lại Trang Chủ</a></p>
    </form>

    <?php if ($thongBao): ?>
        <div class="thongbao <?= $ketQuaClass ?> result-banner <?= $ketQuaClass === 'thang' ? 'result-win' : 'result-lose shake-on-lose' ?>">
            <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
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
<script>
    // Auto initialize game effects
    if (typeof GameEffectsAuto !== 'undefined') {
        GameEffectsAuto.init();
    }
</script>
</body>
</html>

