<?php
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bag_index'])) {
    $cost = 50000;

    if ($currentBalance < $cost) {
        $message = "⚠️ Bạn không đủ số dư để rút thưởng (cần " . number_format($cost) . " VNĐ).";
    } else {
        $currentBalance -= $cost;

        // Danh sách phần thưởng có tỉ lệ cụ thể
        $bags = [
            ["label" => "Trượt!", "reward" => 0, "chance" => 50],
            ["label" => "10.000 VNĐ", "reward" => 10000, "chance" => 20],
            ["label" => "50.000 VNĐ", "reward" => 50000, "chance" => 12],
            ["label" => "100.000 VNĐ", "reward" => 100000, "chance" => 12],
            ["label" => "200.000 VNĐ", "reward" => 270000, "chance" => 3],
            ["label" => "500.000 VNĐ", "reward" => 500000, "chance" => 2],
            ["label" => "1.000.000 VNĐ", "reward" => 1000000, "chance" => 1],
        ];

        function getRandomBag($bags) {
            $rand = mt_rand(1, 100);
            $sum = 0;
            foreach ($bags as $bag) {
                $sum += $bag['chance'];
                if ($rand <= $sum) return $bag;
            }
            return $bags[0];
        }

        $chosenIndex = (int)$_POST['bag_index']; // Để hiển thị lại túi đã chọn
        $randomBag = getRandomBag($bags);
        $rewardText = $randomBag['label'];
        $rewardAmount = $randomBag['reward'];

        if ($rewardAmount > 0) {
            $message = "🎉 Bạn đã chọn Túi số <b>" . htmlspecialchars($chosenIndex + 1, ENT_QUOTES, 'UTF-8') . "</b> và nhận được <b>" . htmlspecialchars($rewardText, ENT_QUOTES, 'UTF-8') . "</b>";
        } else {
            $message = "😢 Bạn chọn Túi số <b>" . htmlspecialchars($chosenIndex + 1, ENT_QUOTES, 'UTF-8') . "</b> nhưng... bóc trượt!";
        }

        $newBalance = $currentBalance + $rewardAmount;
        $update = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $update->bind_param("di", $newBalance, $userId);
        $update->execute();
        $update->close();
        
        // Track quest progress và tự động cập nhật streak, VIP, reward points, social feed
        require_once 'game_history_helper.php';
        logGameHistoryWithAll($conn, $userId, 'Rút Thăm', $cost, $rewardAmount, $rewardAmount > 0);
        
        $currentBalance = $newBalance;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Rút Thăm Trúng Thưởng</title>
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
        background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%);
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
        max-width: 900px;
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
    
    .result {
        font-size: 22px;
        font-weight: 700;
        margin-top: 30px;
        padding: 20px;
        border-radius: var(--border-radius);
        animation: messageAppear 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        line-height: 1.8;
    }
    
    .result.win {
        color: #00ff00;
        background: rgba(40, 167, 69, 0.2);
        border: 3px solid #28a745;
        box-shadow: 0 0 25px rgba(40, 167, 69, 0.6);
        animation: messageAppear 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55), winPulse 1.5s ease infinite;
    }
    
    .result.lose {
        color: #ff6b6b;
        background: rgba(220, 53, 69, 0.2);
        border: 3px solid #dc3545;
    }
    
    @keyframes messageAppear {
        0% {
            opacity: 0;
            transform: translateY(-30px) scale(0.5) rotate(-10deg);
        }
        50% {
            transform: translateY(5px) scale(1.1) rotate(5deg);
        }
        100% {
            opacity: 1;
            transform: translateY(0) scale(1) rotate(0deg);
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
    
    .bags {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 20px;
        margin: 40px 0;
    }
    
    .bag {
        background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        color: white;
        font-size: 18px;
        font-weight: 700;
        padding: 25px;
        border: none;
        border-radius: var(--border-radius-lg);
        cursor: pointer;
        width: 140px;
        height: 140px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 6px 20px rgba(243, 156, 18, 0.4);
        position: relative;
        overflow: hidden;
    }
    
    .bag::before {
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
    
    .bag:hover::before {
        width: 300px;
        height: 300px;
    }
    
    .bag:hover:not(:disabled) {
        transform: translateY(-8px) scale(1.05) rotate(5deg);
        box-shadow: 0 10px 30px rgba(243, 156, 18, 0.6);
        background: linear-gradient(135deg, #e67e22 0%, #f39c12 100%);
    }
    
    .bag:active:not(:disabled) {
        transform: translateY(-4px) scale(1.02);
    }
    
    .bag:disabled {
        opacity: 0.6;
        cursor: not-allowed !important;
        transform: none;
    }
    
    .bag.selected {
        background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
        box-shadow: 0 0 30px rgba(46, 204, 113, 0.8);
        animation: bagSelect 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    
    @keyframes bagSelect {
        0% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.2) rotate(10deg);
        }
        100% {
            transform: scale(1.1) rotate(0deg);
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
  </style>
</head>
<body>
  <div class="game-container">
    <h1>🎁 Rút Thăm Trúng Thưởng</h1>
    <p style="font-size: 18px; margin: 10px 0; color: #333;">Xin chào <strong><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></strong></p>
    <div class="balance-display">💰 Số dư hiện tại: <strong><?= number_format($currentBalance, 0, ',', '.') ?> VNĐ</strong></div>
    <div class="cost-info">💸 Chi phí mỗi lần rút: <strong>50.000 VNĐ</strong></div>

    <form method="post" id="bagForm">
      <div class="bags">
        <?php for ($i = 0; $i < 6; $i++): ?>
          <button type="submit" name="bag_index" value="<?= $i ?>" class="bag" id="bag<?= $i ?>">
            <span style="font-size: 40px; display: block; margin-bottom: 10px;">🎁</span>
            <span>Túi <?= $i + 1 ?></span>
          </button>
        <?php endfor; ?>
      </div>
    </form>
    <p><a href="index.php">🏠 Quay lại Trang Chủ</a></p>

    <?php if (!empty($message)): ?>
      <div id="resultBox" class="result <?= strpos($message, '🎉') !== false || strpos($message, 'nhận được') !== false ? 'win' : 'lose' ?>">
        <?= $message ?>
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
    const bags = document.querySelectorAll('.bag');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            const clickedBag = e.submitter;
            if (clickedBag) {
                // Disable tất cả bags
                bags.forEach(bag => {
                    bag.disabled = true;
                    const icon = bag.querySelector('span:first-child');
                    const text = bag.querySelector('span:last-child');
                    if (icon && text) {
                        icon.textContent = '⏳';
                        text.textContent = 'Đang xử lý...';
                    }
                });
                
                // Highlight bag được chọn
                clickedBag.classList.add('selected');
            }
        });
    }
    
    // Animation cho result box
    const resultBox = document.getElementById("resultBox");
    if (resultBox) {
        resultBox.style.opacity = 0;
        resultBox.style.transform = "scale(0.5) rotate(-10deg)";
        setTimeout(() => {
            resultBox.style.transition = "opacity 0.6s ease, transform 0.6s ease";
            resultBox.style.opacity = 1;
            resultBox.style.transform = "scale(1) rotate(0deg)";
        }, 100);
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
</body>
</html>
