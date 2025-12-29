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

$ketQua = "";
$thongBao = "";
$ketQuaClass = "";
$laThang = false;
$multi = 0;

$segments = [
    ["label" => "x0.5", "multiplier" => 0.5, "color" => "#ff6b6b"],
    ["label" => "x1", "multiplier" => 1, "color" => "#4ecdc4"],
    ["label" => "x2", "multiplier" => 2, "color" => "#45b7d1"],
    ["label" => "x3", "multiplier" => 3, "color" => "#f9ca24"],
    ["label" => "x5", "multiplier" => 5, "color" => "#f0932b"],
    ["label" => "x10", "multiplier" => 10, "color" => "#eb4d4b"],
    ["label" => "x0", "multiplier" => 0, "color" => "#95a5a6"],
    ["label" => "x1.5", "multiplier" => 1.5, "color" => "#6c5ce7"]
];

// Lấy kết quả từ session (nếu có)
$ketQua = $_SESSION['luckywheel_result'] ?? "";
$thongBao = $_SESSION['luckywheel_message'] ?? "";
$ketQuaClass = $_SESSION['luckywheel_class'] ?? "";
$laThang = $_SESSION['luckywheel_win'] ?? false;

// Xóa session sau khi lấy
unset($_SESSION['luckywheel_result']);
unset($_SESSION['luckywheel_message']);
unset($_SESSION['luckywheel_class']);
unset($_SESSION['luckywheel_win']);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'spin_wheel') {
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");

    if ($cuoc > $soDu || $cuoc <= 0) {
        $_SESSION['luckywheel_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['luckywheel_class'] = "thua";
    } else {
        $segment = $segments[array_rand($segments)];
        $multi = $segment['multiplier'];
        $ketQua = $segment['label'];
        $thang = 0;
        
        if ($multi > 0) {
            $thang = floor($cuoc * $multi);
            $soDu += $thang;
            $_SESSION['luckywheel_message'] = "🎉 " . $ketQua . "! Bạn thắng " . number_format($thang) . " VNĐ!";
            $_SESSION['luckywheel_class'] = "thang";
            $_SESSION['luckywheel_win'] = true;
        } else {
            $soDu -= $cuoc;
            $_SESSION['luckywheel_message'] = "😢 " . $ketQua . "! Mất " . number_format($cuoc) . " VNĐ!";
            $_SESSION['luckywheel_class'] = "thua";
            $_SESSION['luckywheel_win'] = false;
        }

        if ($thang >= 5000000) {
            $message = "🎉 " . htmlspecialchars($tenNguoiChoi) . " vừa thắng lớn " . number_format($thang, 0, ',', '.') . " VNĐ trong Vòng Quay May Mắn! 🎊";
            $expiresAt = date('Y-m-d H:i:s', time() + 30);
            $checkTable = $conn->query("SHOW TABLES LIKE 'server_notifications'");
            if ($checkTable && $checkTable->num_rows > 0) {
                $insertSql = "INSERT INTO server_notifications (user_id, user_name, message, amount, notification_type, expires_at) VALUES (?, ?, ?, ?, 'big_win', ?)";
                $insertStmt = $conn->prepare($insertSql);
                if ($insertStmt) {
                    $insertStmt->bind_param("issds", $userId, $tenNguoiChoi, $message, $thang, $expiresAt);
                    $insertStmt->execute();
                    $insertStmt->close();
                }
            }
        }

        // Cập nhật số dư
        $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        if ($capNhat) {
            $capNhat->bind_param("di", $soDu, $userId);
            $capNhat->execute();
            $capNhat->close();
        }
        
        // Lưu kết quả vào session
        $_SESSION['luckywheel_result'] = $ketQua;
        
        // Redirect để tránh resubmit
        header("Location: luckywheel.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Vòng Quay May Mắn</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/game-effects.css">

    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            font-family: 'Segoe UI', sans-serif;
            text-align: center;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            padding: 50px;
            min-height: 100vh;
        }
        * { cursor: inherit; }
        button, a, input { cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important; }
        .game-box {
            display: inline-block;
            background: rgba(250, 112, 154, 0.95);
            padding: 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(250, 112, 154, 0.4);
            border: 3px solid rgba(255, 255, 255, 0.5);
            max-width: 600px;
        }
        .game-box h1 { color: white; font-size: 28px; margin: 15px 0; }
        .balance { margin: 20px 0; font-size: 22px; font-weight: 700; color: #ffd700; }
        .wheel-container {
            margin: 30px 0;
        }
        .wheel {
            width: 300px;
            height: 300px;
            border-radius: 50%;
            margin: 0 auto;
            border: 10px solid #333;
            position: relative;
            overflow: hidden;
            background: conic-gradient(
                #ff6b6b 0deg 45deg,
                #4ecdc4 45deg 90deg,
                #45b7d1 90deg 135deg,
                #f9ca24 135deg 180deg,
                #f0932b 180deg 225deg,
                #eb4d4b 225deg 270deg,
                #95a5a6 270deg 315deg,
                #6c5ce7 315deg 360deg
            );
            animation: spinWheel 3s ease-out;
        }
        @keyframes spinWheel {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(<?= $ketQua ? (360 * 5 + (array_search($ketQua, array_column($segments, 'label')) * 45)) : 0 ?>deg); }
        }
        .wheel-pointer {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 15px solid transparent;
            border-right: 15px solid transparent;
            border-top: 30px solid #ffd700;
            z-index: 10;
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
    <h1 class="game-title">🎡 Vòng Quay May Mắn</h1>
    <div class="balance">💰 Số dư: <b><?= number_format($soDu, 0, ',', '.') ?> VNĐ</b></div>
    
    <div class="wheel-container">
        <div class="wheel-pointer"></div>
        <div class="wheel"></div>
    </div>
    
    <form method="post" id="gameForm">
        <input type="hidden" name="action" value="spin_wheel">
        <label style="display: block; margin: 15px 0 8px; font-weight: 600; color: white; font-size: 16px;">💰 Số tiền cược:</label>
        <input type="number" name="cuoc" id="cuocInput" placeholder="Nhập số tiền cược" required min="1" value=""><br>
        <button type="submit" id="submitBtn" class="btn-game">🎡 Quay</button>
        <p><a href="index.php" style="color: white; text-decoration: none;">🏠 Quay Lại Trang Chủ</a></p>
    </form>

    <?php if ($thongBao): ?>
        <div class="thongbao <?= $ketQuaClass ?> result-banner <?= $ketQuaClass === 'thang' ? 'result-win' : 'result-lose shake-on-lose' ?>">
            <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
</div>

    <script src="assets/js/game-effects.js"></script>
    <script src="assets/js/game-effects-auto.js"></script>

<script>
    // Auto initialize game effects
    if (typeof GameEffectsAuto !== 'undefined') {
        GameEffectsAuto.init();
    }
</script>
</body>
</html>

