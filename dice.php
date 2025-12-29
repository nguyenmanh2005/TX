<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

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
$ketQua = $_SESSION['dice_result'] ?? 0;
$thongBao = $_SESSION['dice_message'] ?? "";
$ketQuaClass = $_SESSION['dice_class'] ?? "";
$laThang = $_SESSION['dice_win'] ?? false;

// Xóa session sau khi lấy
unset($_SESSION['dice_result']);
unset($_SESSION['dice_message']);
unset($_SESSION['dice_class']);
unset($_SESSION['dice_win']);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'roll_dice') {
    $chon = (int) ($_POST["chon"] ?? 0);
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");

    if ($chon < 1 || $chon > 6) {
        $_SESSION['dice_message'] = "❌ Chọn số từ 1 đến 6!";
        $_SESSION['dice_class'] = "thua";
    } elseif ($cuoc > $soDu || $cuoc <= 0) {
        $_SESSION['dice_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['dice_class'] = "thua";
    } else {
        // Tung xúc xắc
        $ketQua = rand(1, 6);
        $thang = 0;
        
        if ($chon === $ketQua) {
            // Đoán đúng
            $thang = $cuoc * 6; // x6 vì tỷ lệ 1/6
            $soDu += $thang;
            $_SESSION['dice_message'] = "🎉 CHÍNH XÁC! Bạn đoán đúng số " . $ketQua . "! Thắng " . number_format($thang) . " VNĐ!";
            $_SESSION['dice_class'] = "thang";
            $_SESSION['dice_win'] = true;
            
            if ($thang >= 5000000) {
                $message = "🎉 " . htmlspecialchars($tenNguoiChoi) . " vừa thắng lớn " . number_format($thang, 0, ',', '.') . " VNĐ trong Lắc Xí Ngầu! 🎊";
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
        } else {
            // Đoán sai
            $soDu -= $cuoc;
            $_SESSION['dice_message'] = "😢 Tiếc quá! Bạn chọn " . $chon . " nhưng xúc xắc ra " . $ketQua . ". Mất " . number_format($cuoc) . " VNĐ";
            $_SESSION['dice_class'] = "thua";
            $_SESSION['dice_win'] = false;
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
        $winAmount = isset($thang) ? $thang : 0;
        logGameHistoryWithAll($conn, $userId, 'Dice', $cuoc, $winAmount, $laThang);
        
        // Lưu kết quả vào session
        $_SESSION['dice_result'] = $ketQua;
        
        // Redirect để tránh resubmit
        header("Location: dice.php");
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

$diceEmoji = [
    1 => "⚀",
    2 => "⚁",
    3 => "⚂",
    4 => "⚃",
    5 => "⚄",
    6 => "⚅"
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Lắc Xí Ngầu</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
        <link rel="stylesheet" href="assets/css/game-effects.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhancements.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
        <link rel="stylesheet" href="assets/css/game-dice.css">
        <link rel="stylesheet" href="assets/css/game-animations.css">
        <link rel="stylesheet" href="assets/css/game-specific-animations.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            font-family: 'Segoe UI', sans-serif;
            text-align: center;
            background: <?= $bgGradientCSS ?>;
            padding: 50px;
            min-height: 100vh;
            background-attachment: fixed;
        }
        * { cursor: inherit; }
        button, a, input, select { cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important; }
        .game-box {
            display: inline-block;
            background: rgba(102, 126, 234, 0.95);
            padding: 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.4);
            border: 3px solid rgba(255, 255, 255, 0.5);
            max-width: 600px;
        }
        .game-box h1 { color: white; font-size: 28px; margin: 15px 0; }
        .balance { margin: 20px 0; font-size: 22px; font-weight: 700; color: #ffd700; }
        .dice-container {
            margin: 40px 0;
        }
        .dice-display {
            font-size: 150px;
            margin: 30px 0;
            animation: rollDice 1s ease;
        }
        @keyframes rollDice {
            0% { transform: rotate(0deg) scale(0); opacity: 0; }
            50% { transform: rotate(180deg) scale(1.5); opacity: 0.5; }
            100% { transform: rotate(360deg) scale(1); opacity: 1; }
        }
        .number-selector {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 30px 0;
            flex-wrap: wrap;
        }
        .number-btn {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 4px solid white;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 32px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .number-btn:hover {
            background: rgba(255, 255, 255, 0.4);
            transform: scale(1.1);
        }
        .number-btn.selected {
            background: #28a745;
            border-color: #ffd700;
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.8);
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
        .thongbao.thang { color: #00ff00; background: rgba(40, 167, 69, 0.3); border: 3px solid #28a745; }
        .thongbao.thua { color: #ff6b6b; background: rgba(220, 53, 69, 0.3); border: 3px solid #dc3545; }
        .info-box {
            background: rgba(255, 255, 255, 0.2);
            padding: 15px;
            border-radius: var(--border-radius);
            margin: 20px 0;
            color: white;
        }
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

<div class="dice-container-enhanced">
    <div class="game-box-dice-enhanced">
        <div class="game-header-dice-enhanced">
            <h1 class="game-title-dice-enhanced">🎲 Lắc Xí Ngầu</h1>
            <div class="balance-dice-enhanced">
                <span>💰</span>
                <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
            </div>
        </div>
    
        <div class="payout-info-dice-enhanced">
            <div class="payout-title-dice-enhanced">💡 Đoán đúng số, thắng x6!</div>
            <div class="payout-multiplier-dice-enhanced">x6.0</div>
        </div>
        
        <div class="dice-display-enhanced">
            <div class="dice-3d-enhanced <?= $ketQua > 0 ? 'show-result' : '' ?>" data-dice-result="<?= $ketQua ?>">
                <div class="dice-face-enhanced">
                    <?= $ketQua > 0 ? $diceEmoji[$ketQua] : '🎲' ?>
                </div>
            </div>
    </div>
    
    <?php if ($ketQua > 0): ?>
            <div class="result-text-enhanced <?= $ketQuaClass ?>" style="text-align: center; font-size: 24px; font-weight: 700; margin: 20px 0; color: #2c3e50;">
                Kết quả: <?= $ketQua ?>
        </div>
    <?php endif; ?>
    
        <div class="number-selection-enhanced">
            <div class="control-group-dice-enhanced">
                <label class="control-label-dice-enhanced">🎯 Chọn số (1-6):</label>
                <div class="number-grid-enhanced">
            <?php for ($i = 1; $i <= 6; $i++): ?>
                        <button type="button" class="number-btn-enhanced" data-number="<?= $i ?>">
                    <?= $i ?>
                </button>
            <?php endfor; ?>
        </div>
            </div>
        </div>
        
        <div class="game-controls-dice-enhanced">
            <form method="post" id="gameForm">
                <input type="hidden" name="action" value="roll_dice">
                <input type="hidden" name="chon" id="chonInput" value="" required>
        
                <div class="control-group-dice-enhanced">
                    <label class="control-label-dice-enhanced">💰 Số tiền cược:</label>
                    <input type="number" name="cuoc" id="cuocInput" class="control-input-dice-enhanced" placeholder="Nhập số tiền cược" required min="1">
                    <div class="bet-quick-amounts-dice-enhanced">
                        <button type="button" class="bet-quick-btn-dice-enhanced" data-amount="10000">10K</button>
                        <button type="button" class="bet-quick-btn-dice-enhanced" data-amount="50000">50K</button>
                        <button type="button" class="bet-quick-btn-dice-enhanced" data-amount="100000">100K</button>
                        <button type="button" class="bet-quick-btn-dice-enhanced" data-amount="200000">200K</button>
                        <button type="button" class="bet-quick-btn-dice-enhanced" data-amount="500000">500K</button>
                    </div>
                </div>
                
                <button type="submit" id="rollButton" class="roll-button-dice-enhanced">🎲 Lắc Xúc Xắc</button>
    </form>
        </div>

    <?php if ($thongBao): ?>
            <div class="result-banner-dice-enhanced <?= $ketQuaClass === 'thang' ? 'win' : 'lose' ?>">
            <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
        
        <p style="text-align: center; margin-top: 20px;">
            <a href="index.php" style="color: #3498db; text-decoration: none; font-weight: 600; font-size: 16px;">🏠 Quay Lại Trang Chủ</a>
        </p>
    </div>
</div>

    <script src="assets/js/game-dice.js"></script>
    <script src="assets/js/game-animations-enhanced.js"></script>
<script>
    
    // Reset form sau khi hiển thị kết quả
    <?php if ($thongBao): ?>
        // Apply game effects based on result
        <?php if ($laThang): ?>
            <?php if (isset($thang) && $thang >= 5000000): ?>
                // Big win celebration
                if (typeof GameEffects !== 'undefined') {
                    GameEffects.celebrateBigWin(<?= $thang ?>);
                    setTimeout(() => {
                        const diceDisplay = document.querySelector('.dice-display');
                        if (diceDisplay) {
                            GameEffects.addGlowEffect(diceDisplay, 3000);
                            GameEffects.bounceElement(diceDisplay);
                        }
                    }, 500);
                }
            <?php else: ?>
                // Normal win
                if (typeof GameEffects !== 'undefined') {
                    GameEffects.celebrateWin(<?= isset($thang) ? $thang : 0 ?>);
                    setTimeout(() => {
                        const diceDisplay = document.querySelector('.dice-display');
                        if (diceDisplay) {
                            GameEffects.bounceElement(diceDisplay);
                            GameEffects.addGlowEffect(diceDisplay, 2000);
                        }
                    }, 500);
                }
            <?php endif; ?>
        <?php else: ?>
            // Lose effect
            if (typeof GameEffects !== 'undefined') {
                GameEffects.showLoseEffect(document.querySelector('.game-box'));
                const diceDisplay = document.querySelector('.dice-display');
                if (diceDisplay) {
                    diceDisplay.classList.add('shake-effect');
                    setTimeout(() => diceDisplay.classList.remove('shake-effect'), 500);
                }
            }
        <?php endif; ?>
        
        // Reset form sau 2 giây
        setTimeout(function() {
            document.getElementById('gameForm').reset();
            document.getElementById('selectedNumber').value = '';
            selectedNumber = null;
            document.querySelectorAll('.number-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
            document.getElementById('cuocInput').value = '';
            // Scroll to form
            document.getElementById('gameForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 3000);
    <?php endif; ?>
    
    // Add button press effect
    document.getElementById('submitBtn')?.addEventListener('click', function(e) {
        if (typeof GameEffects !== 'undefined') {
            GameEffects.buttonPressEffect(this);
            const rect = this.getBoundingClientRect();
            GameEffects.createParticleExplosion(rect.left + rect.width / 2, rect.top + rect.height / 2, 15);
        }
    });
    
    // Add effect when selecting number
    window.selectNumber = function(num) {
        if (typeof GameEffects !== 'undefined') {
            const btn = document.querySelector(`[data-number="${num}"]`);
            if (btn) {
                GameEffects.bounceElement(btn);
            }
        }
        // Existing selectNumber logic...
        selectedNumber = num;
        document.getElementById('selectedNumber').value = num;
        document.querySelectorAll('.number-btn').forEach(b => {
            b.classList.remove('selected');
        });
        document.querySelector(`[data-number="${num}"]`).classList.add('selected');
    };
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

