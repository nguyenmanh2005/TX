<?php
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['Iduser'])) {
    header("Location: xoc-dia/login.php"); // Hoặc trang đăng nhập tương ứng
    exit();
}

require 'db_connect.php';

// Load theme
require_once 'load_theme.php';

// Lấy thông tin người dùng hiện tại
$userId = $_SESSION['Iduser'];
$sqlUser = "SELECT Iduser, Name, Money FROM users WHERE Iduser = ?";
$stmtUser = $conn->prepare($sqlUser);
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();
if ($resultUser && $resultUser->num_rows === 1) {
    $user = $resultUser->fetch_assoc();
} else {
    die("Không tìm thấy thông tin người dùng!");
}
$stmtUser->close();

$message = "";
$gameResult = "";
$currentBalance = $user['Money'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Lấy dữ liệu gửi lên: lựa chọn cược và số tiền cược
    $betChoice = isset($_POST['betChoice']) ? $_POST['betChoice'] : "";
    $betAmount = isset($_POST['betAmount']) ? floatval($_POST['betAmount']) : 0;
    
    // Kiểm tra tiền cược hợp lệ
    if ($betChoice == "" || $betAmount <= 0) {
        $message = "Vui lòng chọn kết quả cược và nhập số tiền cược hợp lệ.";
    } elseif ($betAmount > $currentBalance) {
        $message = "Số tiền cược vượt quá số dư hiện tại.";
    } else {
        // Tạo 4 xúc xắc (0 là Sấp, 1 là Ngửa)
        $x1 = rand(0, 1);
        $x2 = rand(0, 1);
        $x3 = rand(0, 1);
        $x4 = rand(0, 1);

        // Đếm số Ngửa (giá trị 1)
        $soNgua = $x1 + $x2 + $x3 + $x4;
        
        // Xác định kết quả của ván chơi
        switch ($soNgua) {
            case 0:
                $gameResult = "4 Úp";
                break;
            case 1:
                $gameResult = "1 Ngửa 3 Úp";
                break;
            case 2:
                $gameResult = "Hòa";
                break;
            case 3:
                $gameResult = "3 Ngửa 1 Úp";
                break;
            case 4:
                $gameResult = "4 Ngửa";
                break;
            default:
                $gameResult = "Không xác định";
                break;
        }

        // So sánh cược của người chơi với kết quả thực tế
        if ($betChoice === $gameResult) {
            // thắng, cộng tiền cược
            $newBalance = $currentBalance + $betAmount;
            $message = "Chúc mừng, bạn thắng! ";
        } else {
            // thua, trừ tiền cược
            $newBalance = $currentBalance - $betAmount;
            $message = "Buông bỏ không phải là hạnh phúc hãy thử lại vận may ở lần tiếp ";
        }
        
        $message .= "Kết quả: $gameResult. Số dư của bạn: $newBalance.";

        // Cập nhật số dư mới vào database
        $sqlUpdate = "UPDATE users SET Money = ? WHERE Iduser = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bind_param("di", $newBalance, $userId);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        // Track quest progress và tự động cập nhật streak, VIP, reward points, social feed
        require_once 'game_history_helper.php';
        $isWin = ($betChoice === $gameResult);
        $winAmount = $isWin ? $betAmount : 0;
        logGameHistoryWithAll($conn, $userId, 'Xóc Đĩa', $betAmount, $winAmount, $isWin);

        // Cập nhật số dư hiện tại trong session (nếu cần) và biến
        $currentBalance = $newBalance;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Trò chơi Xóc Đĩa - Tiền cược</title>
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
            font-family: 'Segoe UI', Arial, sans-serif;
            text-align: center;
            margin-top: 50px;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 30px;
            position: relative;
            overflow-x: hidden;
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
        
        .container {
            position: relative;
            z-index: 1;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select, input[type="number"] {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .container {
            background: rgba(0, 121, 107, 0.98);
            color: #fff;
            padding: 40px;
            border-radius: var(--border-radius-lg);
            display: inline-block;
            box-shadow: 0 8px 30px rgba(0, 121, 107, 0.5);
            border: 3px solid rgba(255, 255, 255, 0.5);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            max-width: 600px;
            width: 90%;
            position: relative;
            z-index: 1;
        }


        .container:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 12px 40px rgba(0, 121, 107, 0.7);
        }

        .container h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 25px;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.3);
            color: #ffd700;
        }

        input, select {
            padding: 14px 18px;
            font-size: 16px;
            border-radius: var(--border-radius);
            border: 2px solid rgba(255, 255, 255, 0.3);
            margin: 10px;
            background: rgba(255, 255, 255, 0.95);
            color: var(--text-dark);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            width: 80%;
            max-width: 300px;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.2);
        }

        button {
            padding: 16px 32px;
            font-size: 18px;
            font-weight: 600;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
            transition: var(--transition);
            margin: 15px;
        }

        button:hover:not(:disabled) {
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.6);
            background: linear-gradient(135deg, #2980b9 0%, #3498db 100%);
        }
        
        button:disabled {
            opacity: 0.6;
            cursor: not-allowed !important;
        }

        .message {
            font-size: 20px;
            font-weight: 700;
            margin-top: 25px;
            padding: 20px;
            border-radius: var(--border-radius);
            background: rgba(255, 255, 255, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
            animation: messageAppear 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .message.win {
            color: #00ff00;
            background: rgba(40, 167, 69, 0.3);
            border: 3px solid #28a745;
            box-shadow: 0 0 25px rgba(40, 167, 69, 0.6);
            animation: messageAppear 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55), winPulse 1.5s ease infinite;
        }

        .message.lose {
            color: #ff6b6b;
            background: rgba(220, 53, 69, 0.3);
            border: 3px solid #dc3545;
            animation: messageAppear 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55), loseShake 0.8s ease;
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
                transform: scale(1.03);
                box-shadow: 0 0 40px rgba(40, 167, 69, 0.9);
            }
        }

        @keyframes loseShake {
            0%, 100% { transform: translateX(0) rotate(0deg); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-10px) rotate(-5deg); }
            20%, 40%, 60%, 80% { transform: translateX(10px) rotate(5deg); }
        }
        
        .balance-display {
            font-size: 22px;
            font-weight: 700;
            color: #ffd700;
            padding: 15px;
            background: rgba(255, 215, 0, 0.2);
            border-radius: var(--border-radius);
            border: 2px solid #ffd700;
            margin: 20px 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .container a {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .container a:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.5);
        }

        label {
            display: block;
            margin: 15px 0 8px;
            font-weight: 600;
            font-size: 16px;
            color: white;
        }
    </style>
</head>
<body>
    <canvas id="threejs-background"></canvas>
    <div class="container">
        <h1>🎲 Xóc Đĩa</h1>
        <div class="balance-display">💰 Số dư: <strong><?= number_format($currentBalance, 0, ',', '.') ?> VNĐ</strong></div>
        <form method="post" id="gameForm">
            <div>
                <label for="betChoice">🎯 Chọn kết quả cược:</label>
                <select name="betChoice" id="betChoice">
                    <option value="">-- Chọn kết quả --</option>
                    <option value="1 Ngửa 3 Úp">1 Ngửa 3 Úp</option>
                    <option value="3 Ngửa 1 Úp">3 Ngửa 1 Úp</option>
                    <option value="4 Ngửa">4 Ngửa</option>
                    <option value="4 Úp">4 Úp</option>
                    <option value="Hòa">Hòa</option>
                </select>
            </div>
            <div>
                <label for="betAmount">💰 Số tiền cược:</label>
                <input type="number" step="0.01" min="0" name="betAmount" id="betAmount" placeholder="Nhập số tiền cược" required max="<?= $currentBalance ?>">
            </div>
            <button type="submit" id="submitBtn">🎲 Xóc Đĩa</button>
            <p><a href="index.php">🏠 Quay lại trang chủ</a></p>
        </form>
        
        <?php if (!empty($message)): ?>
            <div class="message <?= strpos($message, 'thắng') !== false || strpos($message, 'Chúc mừng') !== false ? 'win' : 'lose' ?>">
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
        
        // Xử lý form submit với animation
        const form = document.getElementById('gameForm');
        const submitBtn = document.getElementById('submitBtn');
        
        if (form && submitBtn) {
            form.addEventListener('submit', function(e) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Đang xóc... 🎲';
            });
        }
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

<?php
$conn->close();
?>
