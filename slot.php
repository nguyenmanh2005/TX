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
$reels = $_SESSION['slot_reels'] ?? [];
$thongBao = $_SESSION['slot_message'] ?? "";
$ketQuaClass = $_SESSION['slot_class'] ?? "";
$laThang = $_SESSION['slot_win'] ?? false;

// Xóa session sau khi lấy
unset($_SESSION['slot_reels']);
unset($_SESSION['slot_message']);
unset($_SESSION['slot_class']);
unset($_SESSION['slot_win']);

$symbols = ["🍒", "🍋", "🍊", "🍇", "⭐", "💎", "🔔", "7️⃣"];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'spin') {
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");

    if ($cuoc > $soDu || $cuoc <= 0) {
        $_SESSION['slot_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['slot_class'] = "thua";
    } else {
        // Quay 3 cuộn
        $reels = [];
        for ($i = 0; $i < 3; $i++) {
            $reels[] = $symbols[array_rand($symbols)];
        }
        
        $winAmount = 0;
        // Tính thắng
        if ($reels[0] === $reels[1] && $reels[1] === $reels[2]) {
            // 3 giống nhau
            if ($reels[0] === "💎") {
                $winAmount = $cuoc * 10; // Kim cương x3
            } elseif ($reels[0] === "7️⃣") {
                $winAmount = $cuoc * 8; // Số 7 x3
            } elseif ($reels[0] === "⭐") {
                $winAmount = $cuoc * 6; // Sao x3
            } else {
                $winAmount = $cuoc * 3; // Khác x3
            }
            $soDu += $winAmount;
            $_SESSION['slot_message'] = "🎉 JACKPOT! Bạn thắng " . number_format($winAmount) . " VNĐ!";
            $_SESSION['slot_class'] = "thang";
        } elseif ($reels[0] === $reels[1] || $reels[1] === $reels[2] || $reels[0] === $reels[2]) {
            // 2 giống nhau
            $winAmount = floor($cuoc * 1.5);
            $soDu += $winAmount;
            $_SESSION['slot_message'] = "🎊 Bạn thắng " . number_format($winAmount) . " VNĐ!";
            $_SESSION['slot_class'] = "thang";
        } else {
            $soDu -= $cuoc;
            $_SESSION['slot_message'] = "😢 Chúc may mắn lần sau! Mất " . number_format($cuoc) . " VNĐ";
            $_SESSION['slot_class'] = "thua";
        }

        $laThang = ($winAmount > 0);
        $_SESSION['slot_win'] = $laThang;

        if ($winAmount >= 5000000) {
            $message = "🎉 " . htmlspecialchars($tenNguoiChoi) . " vừa thắng lớn " . number_format($winAmount, 0, ',', '.') . " VNĐ trong Slot Machine! 🎊";
            $expiresAt = date('Y-m-d H:i:s', time() + 30);
            $checkTable = $conn->query("SHOW TABLES LIKE 'server_notifications'");
            if ($checkTable && $checkTable->num_rows > 0) {
                $insertSql = "INSERT INTO server_notifications (user_id, user_name, message, amount, notification_type, expires_at) VALUES (?, ?, ?, ?, 'big_win', ?)";
                $insertStmt = $conn->prepare($insertSql);
                if ($insertStmt) {
                    $insertStmt->bind_param("issds", $userId, $tenNguoiChoi, $message, $winAmount, $expiresAt);
                    $insertStmt->execute();
                    $insertStmt->close();
                }
            }
            
            // Tạo feed activity
            require_once 'notification_helper.php';
            $feedMessage = "🎉 " . htmlspecialchars($tenNguoiChoi) . " vừa thắng lớn " . number_format($winAmount, 0, ',', '.') . " VNĐ trong Slot Machine!";
            createFeedActivity($conn, $userId, 'big_win', $feedMessage, ['game' => 'Slot Machine', 'amount' => $winAmount]);
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
        logGameHistoryWithAll($conn, $userId, 'Slot Machine', $cuoc, $winAmount, $laThang);
        
        // Lưu kết quả vào session
        $_SESSION['slot_reels'] = $reels;
        
        // Redirect để tránh resubmit
        header("Location: slot.php");
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
    <title>Slot Machine</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
        <link rel="stylesheet" href="assets/css/game-effects.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhancements.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
        <link rel="stylesheet" href="assets/css/game-slot.css">
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
        
        /* Canvas cho pháo hoa (nếu có) */
        canvas:not(#threejs-background) {
            position: fixed;
            pointer-events: none;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 999;
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
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.4),
                        0 0 0 1px rgba(255, 255, 255, 0.1);
            border: 3px solid rgba(255, 255, 255, 0.5);
            max-width: 600px;
            animation: fadeInScale 0.6s ease;
            position: relative;
            overflow: hidden;
        }
        
        .game-box::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: float 8s ease-in-out infinite;
        }
        .game-box h1 { color: white; font-size: 28px; margin: 15px 0; }
        .balance { margin: 20px 0; font-size: 22px; font-weight: 700; color: #ffd700; }
        .slot-machine {
            background: #1a1a1a;
            border: 5px solid #ffd700;
            border-radius: 20px;
            padding: 30px;
            margin: 30px 0;
        }
        .reels {
            display: flex;
            justify-content: center;
            gap: 20px;
            font-size: 100px;
            margin: 20px 0;
        }
        .reel {
            background: white;
            border-radius: 15px;
            padding: 20px;
            min-width: 120px;
            animation: spinReel 0.8s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .reel::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .reel:hover::before {
            left: 100%;
        }
        
        .reel:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        @keyframes spinReel {
            0% { 
                transform: translateY(-200px) rotate(360deg) scale(0.5); 
                opacity: 0; 
            }
            50% {
                transform: translateY(-50px) rotate(180deg) scale(1.1);
                opacity: 0.7;
            }
            100% { 
                transform: translateY(0) rotate(0deg) scale(1); 
                opacity: 1; 
            }
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
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3),
                        0 0 0 0 rgba(40, 167, 69, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        button:hover::before {
            width: 300px;
            height: 300px;
        }
        
        button:hover { 
            transform: translateY(-5px) scale(1.08); 
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.5),
                        0 0 20px rgba(40, 167, 69, 0.3);
        }
        
        button:active {
            transform: translateY(-2px) scale(1.05);
        }
        .thang { border-color: #28a745; box-shadow: 0 0 30px rgba(40, 167, 69, 0.8); }
        .thua { border-color: #dc3545; }
        .thongbao {
            margin-top: 20px;
            font-size: 20px;
            font-weight: 700;
            padding: 15px 25px;
            border-radius: var(--border-radius);
            min-height: 50px;
            animation: bounceIn 0.6s ease;
            position: relative;
            overflow: hidden;
        }
        
        .thongbao::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmer 2s infinite;
        }
        
        .thongbao.thang { 
            color: #00ff00; 
            background: rgba(40, 167, 69, 0.3);
            border: 2px solid #28a745;
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.5);
            animation: bounceIn 0.6s ease, glow 2s ease-in-out infinite;
        }
        
        .thongbao.thua { 
            color: #ff6b6b; 
            background: rgba(220, 53, 69, 0.3);
            border: 2px solid #dc3545;
            animation: shake 0.5s ease;
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

<div class="slot-container-enhanced">
    <div class="game-box-enhanced">
        <div class="game-header-slot-enhanced">
            <h1 class="game-title-slot-enhanced">🎰 Slot Machine</h1>
            <div class="balance-slot-enhanced">
                <span>💰</span>
                <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
            </div>
        </div>
    
        <div class="slot-machine-enhanced <?= $ketQuaClass ?>">
            <div class="reels-enhanced">
            <?php if (!empty($reels)): ?>
                <?php foreach ($reels as $reel): ?>
                        <div class="reel-enhanced"><?= $reel ?></div>
                <?php endforeach; ?>
            <?php else: ?>
                    <div class="reel-enhanced">❓</div>
                    <div class="reel-enhanced">❓</div>
                    <div class="reel-enhanced">❓</div>
            <?php endif; ?>
        </div>
    </div>
    
        <div class="game-controls-slot-enhanced">
    <form method="post" id="gameForm">
        <input type="hidden" name="action" value="spin">
                <div class="control-group-slot-enhanced">
                    <label class="control-label-slot-enhanced">💰 Số tiền cược:</label>
                    <input type="number" name="cuoc" id="cuocInput" class="control-input-slot-enhanced" placeholder="Nhập số tiền cược" required min="1" value="">
                    <div class="bet-quick-amounts-slot-enhanced">
                        <button type="button" class="bet-quick-btn-slot-enhanced" data-amount="10000">10K</button>
                        <button type="button" class="bet-quick-btn-slot-enhanced" data-amount="50000">50K</button>
                        <button type="button" class="bet-quick-btn-slot-enhanced" data-amount="100000">100K</button>
                        <button type="button" class="bet-quick-btn-slot-enhanced" data-amount="200000">200K</button>
                        <button type="button" class="bet-quick-btn-slot-enhanced" data-amount="500000">500K</button>
                    </div>
                </div>
                <button type="submit" id="submitBtn" class="spin-button-slot-enhanced">🎰 Quay Slot</button>
    </form>
        </div>

    <?php if ($thongBao): ?>
            <div class="result-banner-slot-enhanced <?= $ketQuaClass === 'thang' ? 'win' : 'lose' ?>">
            <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
        
        <div class="payout-table-enhanced">
            <div class="payout-title-enhanced">📊 Bảng Payout</div>
            <div class="payout-grid-enhanced">
                <div class="payout-item-enhanced">
                    <div class="payout-symbols-enhanced">💎💎💎</div>
                    <div class="payout-multiplier-enhanced">x10</div>
                </div>
                <div class="payout-item-enhanced">
                    <div class="payout-symbols-enhanced">7️⃣7️⃣7️⃣</div>
                    <div class="payout-multiplier-enhanced">x8</div>
                </div>
                <div class="payout-item-enhanced">
                    <div class="payout-symbols-enhanced">⭐⭐⭐</div>
                    <div class="payout-multiplier-enhanced">x6</div>
                </div>
                <div class="payout-item-enhanced">
                    <div class="payout-symbols-enhanced">🍒🍒🍒</div>
                    <div class="payout-multiplier-enhanced">x3</div>
                </div>
                <div class="payout-item-enhanced">
                    <div class="payout-symbols-enhanced">🍋🍋🍋</div>
                    <div class="payout-multiplier-enhanced">x3</div>
                </div>
                <div class="payout-item-enhanced">
                    <div class="payout-symbols-enhanced">🍊🍊🍊</div>
                    <div class="payout-multiplier-enhanced">x3</div>
                </div>
                <div class="payout-item-enhanced">
                    <div class="payout-symbols-enhanced">🍇🍇🍇</div>
                    <div class="payout-multiplier-enhanced">x3</div>
                </div>
                <div class="payout-item-enhanced">
                    <div class="payout-symbols-enhanced">🔔🔔🔔</div>
                    <div class="payout-multiplier-enhanced">x3</div>
                </div>
                <div class="payout-item-enhanced">
                    <div class="payout-symbols-enhanced">2 giống</div>
                    <div class="payout-multiplier-enhanced">x1.5</div>
                </div>
            </div>
        </div>
        
        <p style="text-align: center; margin-top: 20px;">
            <a href="index.php" style="color: #667eea; text-decoration: none; font-weight: 600; font-size: 16px;">🏠 Quay Lại Trang Chủ</a>
        </p>
    </div>
</div>

<script>
    // Reset form sau khi hiển thị kết quả
    <?php if ($thongBao): ?>
        // Apply game effects based on result
        <?php if ($laThang): ?>
            <?php if ($winAmount >= 5000000): ?>
                // Big win celebration
                if (typeof GameEffects !== 'undefined') {
                    GameEffects.celebrateBigWin(<?= $winAmount ?>);
                    setTimeout(() => {
                        const reels = document.querySelectorAll('.reel');
                        reels.forEach(reel => GameEffects.addGlowEffect(reel, 3000));
                    }, 500);
                }
            <?php else: ?>
                // Normal win
                if (typeof GameEffects !== 'undefined') {
                    GameEffects.celebrateWin(<?= $winAmount ?>);
                    setTimeout(() => {
                        const reels = document.querySelectorAll('.reel');
                        reels.forEach(reel => GameEffects.bounceElement(reel));
                    }, 500);
                }
            <?php endif; ?>
        <?php else: ?>
            // Lose effect
            if (typeof GameEffects !== 'undefined') {
                GameEffects.showLoseEffect(document.querySelector('.slot-machine'));
            }
        <?php endif; ?>
        
        setTimeout(function() {
            document.getElementById('gameForm').reset();
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
        <script src="assets/js/game-ui-enhanced.js"></script>
        <script src="assets/js/game-confetti.js"></script>
        <script src="assets/js/game-slot.js"></script>
    <script src="assets/js/game-animations-enhanced.js"></script>
<script>
    // Auto initialize game effects
    if (typeof GameEffectsAuto !== 'undefined') {
        GameEffectsAuto.init();
    }
    
    // Trigger confetti nếu thắng lớn
    <?php if ($laThang && isset($winAmount) && $winAmount >= 10000000): ?>
    if (window.gameConfetti) {
        setTimeout(() => {
            window.gameConfetti.createBigWinConfetti();
        }, 500);
    }
    <?php elseif ($laThang): ?>
    if (window.gameConfetti) {
        setTimeout(() => {
            window.gameConfetti.createWinConfetti();
        }, 500);
    }
    <?php endif; ?>
</script>
</body>
</html>

