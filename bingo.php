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

// Khởi tạo bảng Bingo 5x5 (1-25)
if (!isset($_SESSION['bingo_card'])) {
    $numbers = range(1, 25);
    shuffle($numbers);
    $_SESSION['bingo_card'] = array_chunk($numbers, 5);
    $_SESSION['bingo_marked'] = [];
    $_SESSION['bingo_drawn'] = [];
}

$thongBao = "";
$ketQuaClass = "";
$laThang = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");

    if ($action === "new_card") {
        $numbers = range(1, 25);
        shuffle($numbers);
        $_SESSION['bingo_card'] = array_chunk($numbers, 5);
        $_SESSION['bingo_marked'] = [];
        $_SESSION['bingo_drawn'] = [];
        $thongBao = "🆕 Bảng Bingo mới đã được tạo!";
    } elseif ($action === "draw" && $cuoc > 0) {
        if ($cuoc > $soDu || $cuoc <= 0) {
            $thongBao = "⚠️ Số tiền cược không hợp lệ!";
        } else {
            // Rút số mới
            $available = array_diff(range(1, 25), $_SESSION['bingo_drawn']);
            if (empty($available)) {
                $thongBao = "🎯 Tất cả số đã được rút! Tạo bảng mới để chơi tiếp!";
            } else {
                $drawn = array_rand(array_flip($available));
                $_SESSION['bingo_drawn'][] = $drawn;
                
                // Kiểm tra xem số có trong bảng không
                $found = false;
                foreach ($_SESSION['bingo_card'] as $row) {
                    if (in_array($drawn, $row)) {
                        $_SESSION['bingo_marked'][] = $drawn;
                        $found = true;
                        break;
                    }
                }
                
                // Kiểm tra thắng (5 số liên tiếp)
                $won = false;
                if (count($_SESSION['bingo_marked']) >= 5) {
                    // Kiểm tra hàng
                    foreach ($_SESSION['bingo_card'] as $row) {
                        $matched = count(array_intersect($row, $_SESSION['bingo_marked']));
                        if ($matched === 5) {
                            $won = true;
                            break;
                        }
                    }
                    // Kiểm tra cột
                    if (!$won) {
                        for ($i = 0; $i < 5; $i++) {
                            $col = array_column($_SESSION['bingo_card'], $i);
                            $matched = count(array_intersect($col, $_SESSION['bingo_marked']));
                            if ($matched === 5) {
                                $won = true;
                                break;
                            }
                        }
                    }
                }
                
                $thang = 0;
                if ($won) {
                    $thang = $cuoc * 5;
                    $soDu += $thang;
                    $thongBao = "🎉 BINGO! Bạn thắng " . number_format($thang) . " VNĐ!";
                    $ketQuaClass = "thang";
                    $laThang = true;
                    
                    // Reset
                    $numbers = range(1, 25);
                    shuffle($numbers);
                    $_SESSION['bingo_card'] = array_chunk($numbers, 5);
                    $_SESSION['bingo_marked'] = [];
                    $_SESSION['bingo_drawn'] = [];
                } else {
                    $soDu -= $cuoc;
                    $thongBao = "Số vừa rút: " . $drawn . ($found ? " ✅ (Có trong bảng!)" : " ❌") . ". Mất " . number_format($cuoc) . " VNĐ";
                    $ketQuaClass = "thua";
                }
                
                $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
                $capNhat->bind_param("di", $soDu, $userId);
                $capNhat->execute();

                // Track quest progress + tournament nếu user đã tham gia và tự động cập nhật streak, VIP, reward points, social feed
                require_once 'game_history_helper.php';
                $winAmount = $won ? $thang : 0;
                logGameHistoryWithAll($conn, $userId, 'Bingo', $cuoc, $winAmount, $won);

                require_once 'tournament_helper.php';
                logTournamentGame($conn, $userId, 'Bingo', $cuoc, $winAmount, $won);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Bingo</title>
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
        button, a, input { cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important; }
        .game-box {
            display: inline-block;
            background: rgba(102, 126, 234, 0.95);
            padding: 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.4);
            border: 3px solid rgba(255, 255, 255, 0.5);
            max-width: 700px;
        }
        .game-box h1 { color: white; font-size: 28px; margin: 15px 0; }
        .balance { margin: 20px 0; font-size: 22px; font-weight: 700; color: #ffd700; }
        .bingo-card {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin: 30px auto;
            max-width: 400px;
        }
        .bingo-cell {
            background: white;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 15px;
            font-size: 24px;
            font-weight: 700;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .bingo-cell.marked {
            background: #28a745;
            color: white;
            animation: markCell 0.3s ease;
        }
        @keyframes markCell {
            0% { transform: scale(0.8); }
            100% { transform: scale(1); }
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

<div class="game-box game-container">
    <h1 class="game-title">🎱 Bingo</h1>
    <div class="balance">💰 Số dư: <b><?= number_format($soDu, 0, ',', '.') ?> VNĐ</b></div>
    
    <div class="bingo-card">
        <?php foreach ($_SESSION['bingo_card'] as $row): ?>
            <?php foreach ($row as $num): ?>
                <div class="bingo-cell <?= in_array($num, $_SESSION['bingo_marked']) ? 'marked' : '' ?>">
                    <?= $num ?>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
    
    <form method="post">
        <label style="display: block; margin: 15px 0 8px; font-weight: 600; color: white; font-size: 16px;">💰 Số tiền cược:</label>
        <input type="number" name="cuoc" placeholder="Nhập số tiền cược" required min="1"><br>
        <button type="submit" name="action" value="draw" class="btn-game">🎱 Rút Số</button>
        <button type="submit" name="action" value="new_card" class="btn-game">🆕 Bảng Mới</button>
        <p><a href="index.php" style="color: white; text-decoration: none;">🏠 Quay Lại Trang Chủ</a></p>
    </form>

    <?php if ($thongBao): ?>
        <div class="thongbao <?= $ketQuaClass ?> result-banner <?= $ketQuaClass === 'thang' ? 'result-win' : 'result-lose shake-on-lose' ?>">
            <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
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

