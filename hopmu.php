<?php
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}
require 'db_connect.php';

// Load theme
require_once 'load_theme.php';
// Đảm bảo $bgGradientCSS có giá trị
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

$userId = $_SESSION['Iduser'];
$sql = "SELECT Money, Name FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$currentBalance = $user['Money'];
$userName = $user['Name'];

$message = "";

// Mức phí mỗi lần bóc
$cost = 50000;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($currentBalance < $cost) {
        $message = "⚠️ Bạn không đủ số dư để rút thưởng. Cần ít nhất " . number_format($cost) . " VNĐ.";
    } else {
        // Trừ phí 50k
        $currentBalance -= $cost;

        // Danh sách phần thưởng với tỉ lệ (trượt nhiều)
        $bags = [
            ["label" => "Trượt!", "reward" => 0],
            ["label" => "Trượt!", "reward" => 0],
            ["label" => "Trượt!", "reward" => 0],
            ["label" => "Trượt!", "reward" => 0],
            ["label" => "Trượt!", "reward" => 0],
            ["label" => "Trượt!", "reward" => 0],
            ["label" => "Trượt!", "reward" => 0],
            ["label" => "Trượt!", "reward" => 0],
            ["label" => "Trượt!", "reward" => 0],
            ["label" => "Trượt!", "reward" => 0],
            ["label" => "Trượt!", "reward" => 0],
            ["label" => "Trượt!", "reward" => 0],
            ["label" => "Trượt!", "reward" => 0],
            ["label" => "Trượt!", "reward" => 0],
            ["label" => "10.000 VNĐ", "reward" => 10000],
            ["label" => "10.000 VNĐ", "reward" => 10000],
            ["label" => "50.000 VNĐ", "reward" => 50000],
            ["label" => "100.000 VNĐ", "reward" => 100000],
            ["label" => "200.000 VNĐ", "reward" => 200000],
            ["label" => "500.000 VNĐ", "reward" => 500000],
            ["label" => "1.000.000 VNĐ", "reward" => 1000000],
        ];

        // Random bag
        $randomBag = $bags[array_rand($bags)];
        $rewardText = $randomBag['label'];
        $rewardAmount = $randomBag['reward'];

        if ($rewardAmount > 0) {
            $message = "🎉 Xin chúc mừng! Bạn nhận được: <b>$rewardText</b>";
        } else {
            $message = "😢 Rất tiếc! Bạn đã bóc trượt. Thử lại nhé!";
        }

        // Cập nhật lại số dư
        $newBalance = $currentBalance + $rewardAmount;
        $update = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $update->bind_param("di", $newBalance, $userId);
        $update->execute();
        $update->close();
        
        // Track quest progress và tự động cập nhật streak, VIP, reward points, social feed
        require_once 'game_history_helper.php';
        logGameHistoryWithAll($conn, $userId, 'Hộp Mù', $cost, $rewardAmount, $rewardAmount > 0);
        
        $currentBalance = $newBalance;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
  <meta charset="UTF-8">
  <title>Bóc Túi Mù</title>
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
        background: <?= $bgGradientCSS ?>; background-attachment: fixed;
        text-align: center;
        padding: 40px 20px;
        min-height: 100vh;
    }
    
    * {
        cursor: inherit;
    }

    button, a, input[type="button"], input[type="submit"], label, select {
        cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
    }
    
    .game-container {
        max-width: 600px;
        margin: 0 auto;
        background: rgba(255, 255, 255, 0.98);
        padding: 40px;
        border-radius: var(--border-radius-lg);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
        border: 2px solid rgba(255, 255, 255, 0.5);
    }
    
    h1 {
        color: var(--primary-color);
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 20px;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .balance-display {
        font-size: 22px;
        font-weight: 700;
        color: var(--success-color);
        padding: 15px;
        background: rgba(232, 245, 233, 0.5);
        border-radius: var(--border-radius);
        border: 2px solid var(--success-color);
        margin: 20px 0;
    }
    
    .cost-info {
        font-size: 18px;
        font-weight: 600;
        color: var(--warning-color);
        padding: 12px;
        background: rgba(243, 156, 18, 0.1);
        border-radius: var(--border-radius);
        border: 2px solid var(--warning-color);
        margin: 20px 0;
    }
    
    button {
        background: <?= $bgGradientCSS ?>; background-attachment: fixed;
        color: white;
        font-size: 20px;
        font-weight: 700;
        padding: 16px 40px;
        border: none;
        border-radius: var(--border-radius);
        cursor: pointer;
        margin-top: 30px;
        box-shadow: 0 4px 15px rgba(0, 121, 107, 0.4);
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
    
    button:hover:not(:disabled) {
        transform: translateY(-4px) scale(1.05);
        box-shadow: 0 8px 25px rgba(0, 121, 107, 0.6);
        background: <?= $bgGradientCSS ?>; background-attachment: fixed;
    }
    
    button:disabled {
        opacity: 0.6;
        cursor: not-allowed !important;
    }
    
    .result {
        font-size: 22px;
        font-weight: 700;
        margin-top: 30px;
        padding: 20px;
        border-radius: var(--border-radius);
        animation: messageAppear 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        line-height: 1.8;
    }
    
    .result.win {
        color: #00ff00;
        background: rgba(40, 167, 69, 0.2);
        border: 3px solid #28a745;
        box-shadow: 0 0 25px rgba(40, 167, 69, 0.6);
        animation: messageAppear 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55), winPulse 1.5s ease infinite;
    }
    
    .result.lose {
        color: #ff6b6b;
        background: rgba(220, 53, 69, 0.2);
        border: 3px solid #dc3545;
    }
    
    @keyframes messageAppear {
        0% {
            opacity: 0;
            transform: translateY(-30px) scale(0.8);
        }
        50% {
            transform: translateY(5px) scale(1.1);
        }
        100% {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    @keyframes winPulse {
        0%, 100% {
            transform: scale(1);
            box-shadow: 0 0 25px rgba(40, 167, 69, 0.6);
        }
        50% {
            transform: scale(1.02);
            box-shadow: 0 0 40px rgba(40, 167, 69, 0.9);
        }
    }
    
    a {
        display: inline-block;
        margin-top: 25px;
        padding: 12px 24px;
        background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
        color: white;
        text-decoration: none;
        border-radius: var(--border-radius);
        font-weight: 600;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
    }
    
    a:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(52, 152, 219, 0.5);
    }
    
    .bag-icon {
        font-size: 80px;
        margin: 20px 0;
        animation: bagShake 2s ease-in-out infinite;
    }
    
    @keyframes bagShake {
        0%, 100% {
            transform: translateY(0) rotate(0deg);
        }
        25% {
            transform: translateY(-10px) rotate(-5deg);
        }
        75% {
            transform: translateY(-10px) rotate(5deg);
        }
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

    </style>
</head>
<body>
    <canvas id="threejs-background"></canvas>
  <div class="game-container">
    <h1>🎁 Bóc Túi Mù</h1>
    <p style="font-size: 18px; margin: 10px 0;">Xin chào <strong><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></strong></p>
    <div class="balance-display">💰 Số dư hiện tại: <strong><?= number_format($currentBalance, 0, ',', '.') ?> VNĐ</strong></div>
    <div class="cost-info">💸 Chi phí mỗi lần bóc: <strong><?= number_format($cost, 0, ',', '.') ?> VNĐ</strong></div>

    <div class="bag-icon">🎁</div>

    <form method="post" id="bagForm">
      <button type="submit" id="submitBtn">👉 Bóc túi ngay!</button>
    </form>
    <p><a href="index.php">🏠 Quay Lại Trang Chủ</a></p>

    <?php if (!empty($message)): ?>
      <div class="result <?= strpos($message, 'chúc mừng') !== false || strpos($message, '🎉') !== false ? 'win' : 'lose' ?>">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>
  </div>
  
  <script>
    // Đảm bảo cursor luôn hoạt động
    document.addEventListener('DOMContentLoaded', function() {
        document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
        
        const interactiveElements = document.querySelectorAll('button, a, input, label, select');
        interactiveElements.forEach(el => {
            el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
        });
    });
    
    // Xử lý form submit
    const form = document.getElementById('bagForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Đang bóc... 🎁';
        });
    }
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

    // Initialize Three.js Background
    (function() {
        // Pass theme config từ PHP sang JavaScript
        window.themeConfig = {
            particleCount: <?= isset($particleCount) ? $particleCount : 800 ?>,
            particleSize: <?= isset($particleSize) ? $particleSize : 0.05 ?>,
            particleColor: '<?= isset($particleColor) ? htmlspecialchars($particleColor, ENT_QUOTES) : "#ffffff" ?>',
            particleOpacity: <?= isset($particleOpacity) ? $particleOpacity : 0.6 ?>,
            shapeCount: <?= isset($shapeCount) ? $shapeCount : 10 ?>,
            shapeColors: <?= isset($shapeColors) ? json_encode($shapeColors) : json_encode(['#667eea', '#764ba2', '#4facfe', '#00f2fe']) ?>,
            shapeOpacity: <?= isset($shapeOpacity) ? $shapeOpacity : 0.3 ?>,
            bgGradient: <?= isset($bgGradient) ? json_encode($bgGradient) : json_encode(['#667eea', '#764ba2', '#4facfe']) ?>
        };
        
        // Load Three.js background script
        const script = document.createElement('script');
        script.src = 'threejs-background.js';
        script.onload = function() {
            console.log('Three.js background loaded');
        };
        document.head.appendChild(script);
    })();


</body>
</html>
