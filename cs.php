<?php
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

// Lấy số dư
$userId = $_SESSION['Iduser'];
$stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($money);
$stmt->fetch();
$stmt->close();

$message = "";
$winning = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['user_number'])) {
    $userInput = trim($_POST['user_number']);

    if (!preg_match('/^\d{5}$/', $userInput)) {
        $message = "❌ Vui lòng nhập đúng 5 chữ số (VD: 12345, 00129)";
    } else {
        // Sinh chuỗi ngẫu nhiên 5 chữ số
        $winning = "";
        for ($i = 0; $i < 5; $i++) {
            $winning .= strval(rand(0, 9));
        }

        // So sánh từng chữ số theo vị trí
        $correct = 0;
        for ($i = 0; $i < 5; $i++) {
            if ($userInput[$i] === $winning[$i]) {
                $correct++;
            }
        }

        // Tính thưởng theo số trúng
        $prizeTable = [0, 10000, 20000, 50000, 100000, 1000000];
        $prize = $prizeTable[$correct];

        if ($prize > 0) {
            $newBalance = $money + $prize;
            $message = "🎉 Bạn trúng $correct số! Nhận thưởng: " . number_format($prize) . " VNĐ.";
        } else {
            $newBalance = $money;
            $message = "😢 Không trúng số nào. Thử lại nhé!";
        }

        // Cập nhật số dư
        $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $stmt->bind_param("di", $newBalance, $userId);
        $stmt->execute();
        $stmt->close();

        // Track quest progress (game này không có cược, chỉ có thưởng) và tự động cập nhật streak, VIP, reward points, social feed
        require_once 'game_history_helper.php';
        $betAmount = 0; // Không có cược
        $winAmount = $prize;
        $isWin = ($prize > 0);
        logGameHistoryWithAll($conn, $userId, 'Cơ hội triệu phú', $betAmount, $winAmount, $isWin);

        $money = $newBalance;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Vietlott Mini</title>
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

    button, a, input[type="button"], input[type="submit"], label, select, input[type="text"] {
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
    
    form {
        margin: 30px 0;
        padding: 25px;
        background: rgba(240, 248, 255, 0.5);
        border-radius: var(--border-radius);
        border: 2px solid rgba(255, 255, 255, 0.3);
    }
    
    label {
        display: block;
        margin: 15px 0 10px;
        font-weight: 600;
        color: var(--text-dark);
        font-size: 18px;
    }
    
    input[type="text"] {
        padding: 14px 20px;
        font-size: 24px;
        font-weight: 700;
        width: 200px;
        text-align: center;
        border: 3px solid var(--border-color);
        border-radius: var(--border-radius);
        background: rgba(255, 255, 255, 0.95);
        color: var(--text-dark);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        letter-spacing: 4px;
    }
    
    input[type="text"]:focus {
        outline: none;
        border-color: var(--secondary-color);
        box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.2);
    }
    
    button {
        padding: 14px 32px;
        font-size: 18px;
        font-weight: 700;
        background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        border: none;
        color: white;
        border-radius: var(--border-radius);
        cursor: pointer;
        margin-top: 20px;
        box-shadow: 0 4px 15px rgba(46, 204, 113, 0.4);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    button:hover:not(:disabled) {
        transform: translateY(-3px) scale(1.05);
        box-shadow: 0 8px 25px rgba(46, 204, 113, 0.6);
        background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    }
    
    button:disabled {
        opacity: 0.6;
        cursor: not-allowed !important;
    }
    
    .result {
        margin-top: 30px;
        font-size: 20px;
        font-weight: 700;
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
    
    .winning {
        font-weight: 700;
        font-size: 24px;
        color: #e67e22;
        margin-top: 20px;
        padding: 15px;
        background: rgba(230, 126, 34, 0.1);
        border-radius: var(--border-radius);
        border: 2px solid #e67e22;
        animation: winningAppear 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    
    @keyframes winningAppear {
        0% {
            opacity: 0;
            transform: scale(0.5) rotate(-180deg);
        }
        50% {
            transform: scale(1.2) rotate(10deg);
        }
        100% {
            opacity: 1;
            transform: scale(1) rotate(0deg);
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
    <h1>🎲 Vietlott Mini - Dự Đoán 5 Chữ Số</h1>
    <div class="balance-display">💰 Số dư hiện tại: <strong><?= number_format($money, 0, ',', '.') ?> VNĐ</strong></div>

    <form method="POST" id="lotteryForm">
      <label>🎯 Nhập 5 chữ số (VD: 12345):</label>
      <input type="text" name="user_number" id="userNumber" maxlength="5" pattern="\d{5}" required placeholder="00000">
      <br>
      <button type="submit" id="submitBtn">🎰 Quay số</button>
      <p><a href="index.php">🏠 Quay Lại Trang Chủ</a></p>
    </form>

    <?php if ($message): ?>
      <div class="result <?= strpos($message, 'trúng') !== false || strpos($message, '🎉') !== false ? 'win' : 'lose' ?>">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
      </div>
      <?php if ($winning): ?>
        <div class="winning">🎯 Kết quả: <strong><?= htmlspecialchars($winning, ENT_QUOTES, 'UTF-8') ?></strong></div>
      <?php endif; ?>
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
    
    // Chỉ cho phép nhập số
    const userNumberInput = document.getElementById('userNumber');
    const form = document.getElementById('lotteryForm');
    const submitBtn = document.getElementById('submitBtn');
    
    if (userNumberInput) {
        userNumberInput.addEventListener('input', function(e) {
            // Chỉ cho phép số
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Giới hạn 5 chữ số
            if (this.value.length > 5) {
                this.value = this.value.slice(0, 5);
            }
        });
        
        userNumberInput.addEventListener('keypress', function(e) {
            // Chỉ cho phép số
            if (!/[0-9]/.test(e.key) && !['Backspace', 'Delete', 'Tab', 'Enter'].includes(e.key)) {
                e.preventDefault();
            }
        });
    }
    
    // Xử lý form submit
    if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
            const value = userNumberInput.value;
            if (value.length !== 5) {
                e.preventDefault();
                alert("Vui lòng nhập đúng 5 chữ số!");
                return false;
            }
            submitBtn.disabled = true;
            submitBtn.textContent = 'Đang quay... 🎰';
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
</body>
</html>
