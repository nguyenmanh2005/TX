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

// Khởi tạo bảng 5x5 với 3 mìn
if (!isset($_SESSION['mines_board'])) {
    $board = array_fill(0, 25, 0);
    $mines = [];
    while (count($mines) < 3) {
        $pos = rand(0, 24);
        if (!in_array($pos, $mines)) {
            $mines[] = $pos;
            $board[$pos] = -1;
        }
    }
    $_SESSION['mines_board'] = $board;
    $_SESSION['mines_revealed'] = [];
    $_SESSION['mines_cuoc'] = 0;
}

$thongBao = "";
$ketQuaClass = "";
$laThang = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");
    $cell = isset($_POST["cell"]) ? (int)$_POST["cell"] : -1;

    if ($action === "new_game") {
        $board = array_fill(0, 25, 0);
        $mines = [];
        while (count($mines) < 3) {
            $pos = rand(0, 24);
            if (!in_array($pos, $mines)) {
                $mines[] = $pos;
                $board[$pos] = -1;
            }
        }
        $_SESSION['mines_board'] = $board;
        $_SESSION['mines_revealed'] = [];
        $_SESSION['mines_cuoc'] = 0;
        $thongBao = "🆕 Game mới! Tìm 3 mìn trong 25 ô!";
    } elseif ($action === "start" && $cuoc > 0) {
        if ($cuoc > $soDu || $cuoc <= 0) {
            $thongBao = "⚠️ Số tiền cược không hợp lệ!";
        } else {
            $_SESSION['mines_cuoc'] = $cuoc;
            $soDu -= $cuoc;
            $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
            $capNhat->bind_param("di", $soDu, $userId);
            $capNhat->execute();
            $thongBao = "🎯 Đã đặt cược " . number_format($cuoc) . " VNĐ! Chọn ô an toàn!";
        }
    } elseif ($action === "reveal" && $cell >= 0 && $cell < 25) {
        if ($_SESSION['mines_cuoc'] == 0) {
            $thongBao = "⚠️ Hãy đặt cược trước!";
        } elseif (in_array($cell, $_SESSION['mines_revealed'])) {
            $thongBao = "⚠️ Ô này đã được mở!";
        } else {
            $_SESSION['mines_revealed'][] = $cell;
            $board = $_SESSION['mines_board'];
            
            if ($board[$cell] === -1) {
                // Trúng mìn!
                $thongBao = "💣 BÙM! Trúng mìn! Mất " . number_format($_SESSION['mines_cuoc']) . " VNĐ!";
                $ketQuaClass = "thua";
                $laThang = false;
                
                // Track quest progress và tự động cập nhật streak, VIP, reward points, social feed
                require_once 'game_history_helper.php';
                logGameHistoryWithAll($conn, $userId, 'Minesweeper', $_SESSION['mines_cuoc'], 0, false);
                
                // Reset
                $board = array_fill(0, 25, 0);
                $mines = [];
                while (count($mines) < 3) {
                    $pos = rand(0, 24);
                    if (!in_array($pos, $mines)) {
                        $mines[] = $pos;
                        $board[$pos] = -1;
                    }
                }
                $_SESSION['mines_board'] = $board;
                $_SESSION['mines_revealed'] = [];
                $_SESSION['mines_cuoc'] = 0;
            } else {
                $safeCount = count($_SESSION['mines_revealed']);
                $totalSafe = 22; // 25 - 3 mìn
                
                if ($safeCount >= $totalSafe) {
                    // Thắng!
                    $thang = $_SESSION['mines_cuoc'] * 3;
                    $soDu += $thang;
                    $thongBao = "🎉 Bạn đã tìm hết mìn! Thắng " . number_format($thang) . " VNĐ!";
                    $ketQuaClass = "thang";
                    $laThang = true;
                    
                    // Track quest progress và tự động cập nhật streak, VIP, reward points, social feed
                    require_once 'game_history_helper.php';
                    logGameHistoryWithAll($conn, $userId, 'Minesweeper', $_SESSION['mines_cuoc'], $thang, true);
                    
                    // Reset
                    $board = array_fill(0, 25, 0);
                    $mines = [];
                    while (count($mines) < 3) {
                        $pos = rand(0, 24);
                        if (!in_array($pos, $mines)) {
                            $mines[] = $pos;
                            $board[$pos] = -1;
                        }
                    }
                    $_SESSION['mines_board'] = $board;
                    $_SESSION['mines_revealed'] = [];
                    $_SESSION['mines_cuoc'] = 0;
                } else {
                    $thongBao = "✅ An toàn! Đã mở " . $safeCount . "/" . $totalSafe . " ô an toàn!";
                    $ketQuaClass = "thang";
                }
            }
            
            $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
            $capNhat->bind_param("di", $soDu, $userId);
            $capNhat->execute();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Dò Mìn</title>
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
            background: rgba(67, 67, 67, 0.95);
            padding: 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
            border: 3px solid rgba(255, 255, 255, 0.5);
            max-width: 600px;
        }
        .game-box h1 { color: white; font-size: 28px; margin: 15px 0; }
        .balance { margin: 20px 0; font-size: 22px; font-weight: 700; color: #ffd700; }
        .mines-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            margin: 30px auto;
            max-width: 300px;
        }
        .mine-cell {
            background: #555;
            border: 2px solid #333;
            border-radius: 5px;
            padding: 20px;
            font-size: 20px;
            font-weight: 700;
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .mine-cell:hover { background: #666; }
        .mine-cell.revealed {
            background: #28a745;
            color: white;
        }
        .mine-cell.mine {
            background: #dc3545;
            color: white;
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
    <h1 class="game-title">💣 Dò Mìn</h1>
    <div class="balance">💰 Số dư: <b><?= number_format($soDu, 0, ',', '.') ?> VNĐ</b></div>
    
    <div class="mines-grid">
        <?php for ($i = 0; $i < 25; $i++): ?>
            <?php
            $revealed = in_array($i, $_SESSION['mines_revealed']);
            $isMine = $_SESSION['mines_board'][$i] === -1;
            $class = "";
            $content = "?";
            if ($revealed) {
                $class = $isMine ? "mine" : "revealed";
                $content = $isMine ? "💣" : "✅";
            }
            ?>
            <form method="post" style="margin: 0; display: inline;">
                <input type="hidden" name="cell" value="<?= $i ?>">
                <input type="hidden" name="action" value="reveal">
                <button type="submit" class="mine-cell <?= $class ?>" style="width: 100%; border: none; background: inherit; cursor: pointer;">
                    <?= $content ?>
                </button>
            </form>
        <?php endfor; ?>
    </div>
    
    <form method="post">
        <label style="display: block; margin: 15px 0 8px; font-weight: 600; color: white; font-size: 16px;">💰 Số tiền cược:</label>
        <input type="number" name="cuoc" placeholder="Nhập số tiền cược" required min="1"><br>
        <button type="submit" name="action" value="start" class="btn-game">🎯 Bắt Đầu</button>
        <button type="submit" name="action" value="new_game" class="btn-game">🆕 Game Mới</button>
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

