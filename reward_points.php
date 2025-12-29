<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

// Load theme
require_once 'load_theme.php';

$userId = $_SESSION['Iduser'];

// Kiểm tra và tạo bảng reward_points và reward_point_transactions nếu chưa có
$checkPoints = $conn->query("SHOW TABLES LIKE 'reward_points'");
if (!$checkPoints || $checkPoints->num_rows == 0) {
    $createPoints = "CREATE TABLE IF NOT EXISTS reward_points (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        total_points INT DEFAULT 0,
        available_points INT DEFAULT 0,
        lifetime_points INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(Iduser) ON DELETE CASCADE,
        INDEX idx_points (total_points DESC)
    )";
    $conn->query($createPoints);
    
    $createTransactions = "CREATE TABLE IF NOT EXISTS reward_point_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        points INT NOT NULL,
        transaction_type VARCHAR(50) NOT NULL,
        description TEXT,
        related_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(Iduser) ON DELETE CASCADE,
        INDEX idx_user_date (user_id, created_at),
        INDEX idx_type (transaction_type)
    )";
    $conn->query($createTransactions);
    
    $createRewards = "CREATE TABLE IF NOT EXISTS reward_point_rewards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        cost_points INT NOT NULL,
        reward_type VARCHAR(50) NOT NULL,
        reward_value INT NOT NULL,
        icon VARCHAR(50) DEFAULT '🎁',
        is_active TINYINT(1) DEFAULT 1,
        stock_limit INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($createRewards);
    
    // Tạo rewards mẫu
    $sampleRewards = [
        ['Tiền 10,000 VNĐ', 'Đổi 100 điểm lấy 10,000 VNĐ', 100, 'money', 10000, '💰'],
        ['Tiền 50,000 VNĐ', 'Đổi 400 điểm lấy 50,000 VNĐ', 400, 'money', 50000, '💰'],
        ['Tiền 100,000 VNĐ', 'Đổi 750 điểm lấy 100,000 VNĐ', 750, 'money', 100000, '💰'],
        ['Tiền 500,000 VNĐ', 'Đổi 3,500 điểm lấy 500,000 VNĐ', 3500, 'money', 500000, '💰'],
        ['XP 50', 'Đổi 50 điểm lấy 50 XP', 50, 'xp', 50, '⭐'],
        ['XP 200', 'Đổi 180 điểm lấy 200 XP', 180, 'xp', 200, '⭐'],
        ['XP 500', 'Đổi 400 điểm lấy 500 XP', 400, 'xp', 500, '⭐']
    ];
    
    foreach ($sampleRewards as $reward) {
        $sql = "INSERT INTO reward_point_rewards (name, description, cost_points, reward_type, reward_value, icon)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE name = VALUES(name)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssisis", $reward[0], $reward[1], $reward[2], $reward[3], $reward[4], $reward[5]);
        $stmt->execute();
        $stmt->close();
    }
}

// Lấy thông tin người dùng
$sql = "SELECT Iduser, Name, Money FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Lấy thông tin points
$sql = "SELECT * FROM reward_points WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userPoints = $result->fetch_assoc();
$stmt->close();

// Nếu chưa có, tạo mới
if (!$userPoints) {
    $sql = "INSERT INTO reward_points (user_id, total_points, available_points, lifetime_points) VALUES (?, 0, 0, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
    
    // Reload
    $sql = "SELECT * FROM reward_points WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userPoints = $result->fetch_assoc();
    $stmt->close();
}

// Lấy lịch sử giao dịch
$sql = "SELECT * FROM reward_point_transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();

// Lấy danh sách rewards
$sql = "SELECT * FROM reward_point_rewards WHERE is_active = 1 ORDER BY cost_points ASC";
$result = $conn->query($sql);
$rewards = [];
while ($row = $result->fetch_assoc()) {
    $rewards[] = $row;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reward Points - Điểm Thưởng</title>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            animation: fadeIn 0.6s ease;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header-points {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 24px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3),
                        0 0 0 1px rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
            animation: fadeInDown 0.6s ease;
            position: relative;
            overflow: hidden;
        }
        
        .header-points::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.05) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }
        
        .header-points h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 42px;
            font-weight: 800;
            letter-spacing: -1px;
            position: relative;
            z-index: 1;
        }
        
        .points-display {
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 12px 35px rgba(255, 215, 0, 0.3);
            color: #333;
            text-align: center;
            animation: fadeInUp 0.6s ease 0.2s backwards;
            position: relative;
            overflow: hidden;
        }
        
        .points-display::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
            animation: float 8s ease-in-out infinite;
        }
        
        .points-icon {
            font-size: 80px;
            margin-bottom: 20px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
            animation: pulse 2s ease-in-out infinite;
        }
        
        .points-value {
            font-size: 72px;
            font-weight: 900;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .points-label {
            font-size: 24px;
            font-weight: 600;
            opacity: 0.9;
        }
        
        .points-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .stat-box {
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 16px;
            text-align: center;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.8;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 800;
        }
        
        .rewards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        
        .reward-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border: 3px solid #e0e0e0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            animation: fadeInScale 0.5s ease backwards;
        }
        
        .reward-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.1) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        
        .reward-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .reward-card:hover {
            transform: translateY(-10px) scale(1.04) rotate(1deg);
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.25);
        }
        
        .reward-card:hover::before {
            opacity: 1;
        }
        
        .reward-card:hover::after {
            left: 100%;
        }
        
        .reward-icon {
            font-size: 56px;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .reward-name {
            font-size: 22px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .reward-description {
            text-align: center;
            color: #666;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .reward-cost {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: #ffd700;
            margin-bottom: 15px;
        }
        
        .redeem-btn {
            width: 100%;
            padding: 14px;
            margin-top: 15px;
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            color: #333;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
        }
        
        .redeem-btn::before {
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
        
        .redeem-btn:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .redeem-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.5);
        }
        
        .redeem-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .transactions-section {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            margin-bottom: 30px;
        }
        
        .transactions-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
        }
        
        .transaction-item {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .transaction-item:hover {
            background: rgba(102, 126, 234, 0.05);
        }
        
        .transaction-info {
            flex: 1;
        }
        
        .transaction-points {
            font-size: 20px;
            font-weight: 700;
        }
        
        .points-positive {
            color: #28a745;
        }
        
        .points-negative {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-points">
            <h1>⭐ Reward Points</h1>
            <p style="color: #666; margin-top: 10px; font-size: 18px;">Tích điểm khi chơi game và đổi lấy phần thưởng!</p>
        </div>
        
        <div class="points-display">
            <div class="points-icon">⭐</div>
            <div class="points-value"><?= number_format($userPoints['available_points']) ?></div>
            <div class="points-label">Điểm Khả Dụng</div>
            
            <div class="points-stats">
                <div class="stat-box">
                    <div class="stat-label">Tổng Điểm</div>
                    <div class="stat-value"><?= number_format($userPoints['total_points']) ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Điểm Đã Dùng</div>
                    <div class="stat-value"><?= number_format($userPoints['total_points'] - $userPoints['available_points']) ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Tổng Điểm Đời</div>
                    <div class="stat-value"><?= number_format($userPoints['lifetime_points']) ?></div>
                </div>
            </div>
        </div>
        
        <div class="rewards-grid">
            <?php foreach ($rewards as $reward): 
                $canAfford = $userPoints['available_points'] >= $reward['cost_points'];
            ?>
                <div class="reward-card">
                    <div class="reward-icon"><?= htmlspecialchars($reward['icon']) ?></div>
                    <div class="reward-name"><?= htmlspecialchars($reward['name']) ?></div>
                    <div class="reward-description"><?= htmlspecialchars($reward['description']) ?></div>
                    <div class="reward-cost">
                        <?= number_format($reward['cost_points']) ?> điểm
                    </div>
                    
                    <?php if ($canAfford): ?>
                        <button class="redeem-btn" onclick="redeemReward(<?= $reward['id'] ?>, '<?= htmlspecialchars($reward['name']) ?>', <?= $reward['cost_points'] ?>)">
                            🎁 Đổi Ngay
                        </button>
                    <?php else: ?>
                        <button class="redeem-btn" disabled>
                            ⏳ Không Đủ Điểm (Cần <?= number_format($reward['cost_points'] - $userPoints['available_points']) ?> điểm nữa)
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (!empty($transactions)): ?>
            <div class="transactions-section">
                <div class="transactions-title">📋 Lịch Sử Giao Dịch</div>
                <?php foreach ($transactions as $trans): 
                    $isPositive = $trans['points'] > 0;
                    $timeAgo = '';
                    $created = new DateTime($trans['created_at']);
                    $now = new DateTime();
                    $diff = $now->diff($created);
                    
                    if ($diff->days > 0) {
                        $timeAgo = $diff->days . ' ngày trước';
                    } elseif ($diff->h > 0) {
                        $timeAgo = $diff->h . ' giờ trước';
                    } elseif ($diff->i > 0) {
                        $timeAgo = $diff->i . ' phút trước';
                    } else {
                        $timeAgo = 'Vừa xong';
                    }
                ?>
                    <div class="transaction-item">
                        <div class="transaction-info">
                            <div style="font-weight: 600; color: #333; margin-bottom: 5px;">
                                <?= htmlspecialchars($trans['description'] ?? $trans['transaction_type']) ?>
                            </div>
                            <div style="font-size: 12px; color: #999;">
                                <?= $timeAgo ?>
                            </div>
                        </div>
                        <div class="transaction-points <?= $isPositive ? 'points-positive' : 'points-negative' ?>">
                            <?= $isPositive ? '+' : '' ?><?= number_format($trans['points']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" style="display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 12px; font-weight: 600;">
                🏠 Về Trang Chủ
            </a>
        </div>
    </div>
    
    <script>
        function redeemReward(rewardId, rewardName, costPoints) {
            Swal.fire({
                title: 'Xác Nhận Đổi Thưởng',
                html: `Bạn có chắc muốn đổi <strong>${rewardName}</strong> với giá <strong>${costPoints.toLocaleString()} điểm</strong>?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ffd700',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Đổi Ngay',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'api_reward_points.php',
                        method: 'POST',
                        data: {
                            action: 'redeem',
                            reward_id: rewardId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Thành Công!',
                                    text: response.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Lỗi!',
                                    text: response.message
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Lỗi!',
                                text: 'Không thể kết nối đến server!'
                            });
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>

