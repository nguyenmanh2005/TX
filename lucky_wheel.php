<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

// Load theme
require_once 'load_theme.php';

// Đảm bảo $bgGradientCSS có giá trị
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

$userId = $_SESSION['Iduser'];

// Lấy thông tin người dùng
$sql = "SELECT Iduser, Name, Money FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Kiểm tra bảng tồn tại
$checkRewardsTable = $conn->query("SHOW TABLES LIKE 'lucky_wheel_rewards'");
$checkLogsTable = $conn->query("SHOW TABLES LIKE 'lucky_wheel_logs'");
$wheelExists = $checkRewardsTable && $checkRewardsTable->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lucky Wheel - Vòng Quay May Mắn</title>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/game-lucky-wheel.css">
    <link rel="stylesheet" href="assets/css/game-animations.css">
    <link rel="stylesheet" href="assets/css/game-specific-animations.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .wheel-container {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }
        
        .header-wheel {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: var(--border-radius-lg);
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        
        /* Override với enhanced classes */
        .header-wheel-enhanced {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            padding: 30px 40px;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15),
                        0 0 0 1px rgba(102, 126, 234, 0.2);
            border: 2px solid rgba(102, 126, 234, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            animation: fadeInDown 0.6s ease-out;
        }
        
        .wheel-wrapper {
            position: relative;
            display: inline-block;
            margin: 40px 0;
        }
        
        #wheel {
            width: 500px;
            height: 500px;
            border-radius: 50%;
            border: 8px solid #2c3e50;
            box-shadow: 0 10px 50px rgba(0, 0, 0, 0.5);
            transition: transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99);
            position: relative;
            background: #ecf0f1;
        }
        
        .wheel-pointer {
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 30px solid transparent;
            border-right: 30px solid transparent;
            border-top: 60px solid #e74c3c;
            z-index: 10;
            filter: drop-shadow(0 5px 10px rgba(0, 0, 0, 0.5));
            pointer-events: none;
        }
        
        .spin-button {
            margin-top: 30px;
            padding: 20px 60px;
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius-lg);
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(243, 156, 18, 0.4);
        }
        
        .spin-button:hover:not(:disabled) {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 12px 35px rgba(243, 156, 18, 0.6);
        }
        
        .spin-button:disabled {
            opacity: 0.5;
            cursor: not-allowed !important;
        }
        
        .reward-popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.98);
            padding: 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            z-index: 1000;
            text-align: center;
            animation: popupShow 0.5s ease;
            max-width: 400px;
        }
        
        @keyframes popupShow {
            from {
                opacity: 0;
                transform: translate(-50%, -50%) scale(0.5);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }
        
        .reward-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: bounce 1s infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        .reward-message {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .reward-details {
            font-size: 18px;
            color: var(--text-dark);
            margin-bottom: 30px;
        }
        
        .close-popup {
            padding: 12px 30px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .history-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: var(--border-radius-lg);
            margin-top: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .history-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .history-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: rgba(0, 0, 0, 0.05);
            border-radius: var(--border-radius);
            margin-bottom: 10px;
        }
        
        .history-icon {
            font-size: 32px;
            margin-right: 15px;
        }
        
        .history-details {
            flex: 1;
            text-align: left;
        }
        
        .history-name {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .history-date {
            font-size: 12px;
            color: var(--text-light);
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .back-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        }
        
        .info-message {
            background: rgba(52, 152, 219, 0.1);
            border: 2px solid var(--primary-color);
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            color: var(--primary-color);
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="wheel-container-enhanced">
        <div class="header-wheel-enhanced">
            <h1>🎡 Lucky Wheel - Vòng Quay May Mắn</h1>
            <div class="user-info-enhanced">
                <div class="balance-display-enhanced">
                    <span>👤</span>
                    <span><?= htmlspecialchars($user['Name']) ?></span>
                    <span>|</span>
                    <span>💰</span>
                    <span><?= number_format($user['Money'], 0, ',', '.') ?> VNĐ</span>
            </div>
            </div>
        </div>
        
        <div class="info-message-enhanced">
            💡 Bạn có 1 lượt quay miễn phí mỗi ngày! Hãy thử vận may của mình nhé!
        </div>
        
        <?php if (!$wheelExists): ?>
            <div class="info-message" style="background: rgba(220, 53, 69, 0.1); border-color: #dc3545; color: #dc3545;">
                ⚠️ Hệ thống Lucky Wheel chưa được kích hoạt. Vui lòng chạy file <strong>create_lucky_wheel_tables.sql</strong> trong database.
            </div>
        <?php else: ?>
            <div class="wheel-wrapper-enhanced">
                <div class="wheel-pointer-enhanced"></div>
                <canvas id="wheel" width="500" height="500"></canvas>
            </div>
            
            <button id="spinButton" class="spin-button-enhanced">🎡 Quay Ngay</button>
            <div id="spinStatus" class="spin-status-enhanced"></div>
            
            <!-- Reward Popup -->
            <div id="rewardPopup" class="reward-popup-enhanced">
                <div class="reward-icon-enhanced" id="rewardIcon">🎁</div>
                <div class="reward-message-enhanced" id="rewardMessage">Chúc mừng!</div>
                <div class="reward-details-enhanced" id="rewardDetails"></div>
                <button class="close-popup-enhanced" onclick="if(window.luckyWheelEnhanced) window.luckyWheelEnhanced.closeRewardPopup()">Đóng</button>
            </div>
            
            <!-- History Section -->
            <div class="history-section-enhanced">
                <div class="history-title-enhanced">📜 Lịch Sử Quay (10 Lần Gần Nhất)</div>
                <div id="historyList">
                    <div style="text-align: center; padding: 20px; color: var(--text-light);">
                        Đang tải lịch sử...
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <a href="index.php" class="back-link-enhanced">🏠 Về Trang Chủ</a>
    </div>
    
    <script src="assets/js/game-lucky-wheel.js"></script>
    <script src="assets/js/game-animations-enhanced.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
            
            const interactiveElements = document.querySelectorAll('button, a, input, label, select');
            interactiveElements.forEach(el => {
                el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            });
            
            // Kiểm tra wheel có tồn tại không
            const wheelExists = <?= $wheelExists ? 'true' : 'false' ?>;
            if (!wheelExists) {
                const spinStatus = document.getElementById('spinStatus');
                if (spinStatus) {
                    spinStatus.innerHTML = '⚠️ Hệ thống Lucky Wheel chưa được kích hoạt. Vui lòng chạy file create_lucky_wheel_tables.sql trong database.';
                    spinStatus.classList.add('status-used');
                }
            }
        });
        
    </script>
</body>
</html>

