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

// Kiểm tra và tạo bảng vip_levels và user_vip nếu chưa có
$checkVipLevels = $conn->query("SHOW TABLES LIKE 'vip_levels'");
if (!$checkVipLevels || $checkVipLevels->num_rows == 0) {
    $createVipLevels = "CREATE TABLE IF NOT EXISTS vip_levels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        level INT NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        required_spent DECIMAL(15,2) DEFAULT 0,
        bonus_multiplier DECIMAL(3,2) DEFAULT 1.00,
        daily_bonus INT DEFAULT 0,
        color VARCHAR(20) DEFAULT '#667eea',
        icon VARCHAR(50) DEFAULT '⭐',
        benefits TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->query($createVipLevels);
    
    // Tạo dữ liệu mẫu
    $vipLevels = [
        [1, 'Bronze', 0, 1.05, 10000, '#cd7f32', '🥉', 'Bonus 5% khi thắng, Daily bonus 10,000 VNĐ'],
        [2, 'Silver', 1000000, 1.10, 50000, '#c0c0c0', '🥈', 'Bonus 10% khi thắng, Daily bonus 50,000 VNĐ'],
        [3, 'Gold', 5000000, 1.15, 100000, '#ffd700', '🥇', 'Bonus 15% khi thắng, Daily bonus 100,000 VNĐ'],
        [4, 'Platinum', 20000000, 1.25, 250000, '#e5e4e2', '💎', 'Bonus 25% khi thắng, Daily bonus 250,000 VNĐ'],
        [5, 'Diamond', 50000000, 1.50, 500000, '#b9f2ff', '💠', 'Bonus 50% khi thắng, Daily bonus 500,000 VNĐ']
    ];
    
    foreach ($vipLevels as $level) {
        $sql = "INSERT INTO vip_levels (level, name, required_spent, bonus_multiplier, daily_bonus, color, icon, benefits)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE name = VALUES(name)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isddiss", $level[0], $level[1], $level[2], $level[3], $level[4], $level[5], $level[6], $level[7]);
        $stmt->execute();
        $stmt->close();
    }
}

$checkUserVip = $conn->query("SHOW TABLES LIKE 'user_vip'");
if (!$checkUserVip || $checkUserVip->num_rows == 0) {
    $createUserVip = "CREATE TABLE IF NOT EXISTS user_vip (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        vip_level INT DEFAULT 1,
        total_spent DECIMAL(15,2) DEFAULT 0,
        daily_bonus_claimed DATE NULL,
        vip_points INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(Iduser) ON DELETE CASCADE,
        INDEX idx_level (vip_level),
        INDEX idx_spent (total_spent DESC)
    )";
    $conn->query($createUserVip);
}

// Lấy thông tin người dùng
$sql = "SELECT Iduser, Name, Money FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Lấy thông tin VIP của user
$sql = "SELECT uv.*, vl.name as vip_name, vl.bonus_multiplier, vl.daily_bonus, vl.color, vl.icon, vl.benefits
        FROM user_vip uv
        LEFT JOIN vip_levels vl ON uv.vip_level = vl.level
        WHERE uv.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userVip = $result->fetch_assoc();
$stmt->close();

// Nếu chưa có, tạo mới
if (!$userVip) {
    $sql = "INSERT INTO user_vip (user_id, vip_level, total_spent) VALUES (?, 1, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
    
    // Reload
    $sql = "SELECT uv.*, vl.name as vip_name, vl.bonus_multiplier, vl.daily_bonus, vl.color, vl.icon, vl.benefits
            FROM user_vip uv
            LEFT JOIN vip_levels vl ON uv.vip_level = vl.level
            WHERE uv.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userVip = $result->fetch_assoc();
    $stmt->close();
}

// Tính toán total_spent từ game_history
$checkGameHistory = $conn->query("SHOW TABLES LIKE 'game_history'");
if ($checkGameHistory && $checkGameHistory->num_rows > 0) {
    $sql = "SELECT SUM(bet_amount) as total FROM game_history WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $totalSpent = $result->fetch_assoc()['total'] ?? 0;
    $stmt->close();
    
    // Cập nhật total_spent
    if ($userVip['total_spent'] != $totalSpent) {
        $sql = "UPDATE user_vip SET total_spent = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("di", $totalSpent, $userId);
        $stmt->execute();
        $stmt->close();
        $userVip['total_spent'] = $totalSpent;
    }
}

// Xác định VIP level hiện tại dựa trên total_spent
$sql = "SELECT * FROM vip_levels WHERE required_spent <= ? ORDER BY level DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("d", $userVip['total_spent']);
$stmt->execute();
$result = $stmt->get_result();
$currentVipLevel = $result->fetch_assoc();
$stmt->close();

// Cập nhật VIP level nếu cần
if ($currentVipLevel && $currentVipLevel['level'] > $userVip['vip_level']) {
    $sql = "UPDATE user_vip SET vip_level = ? WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $currentVipLevel['level'], $userId);
    $stmt->execute();
    $stmt->close();
    $userVip['vip_level'] = $currentVipLevel['level'];
    $userVip['vip_name'] = $currentVipLevel['name'];
    $userVip['bonus_multiplier'] = $currentVipLevel['bonus_multiplier'];
    $userVip['daily_bonus'] = $currentVipLevel['daily_bonus'];
    $userVip['color'] = $currentVipLevel['color'];
    $userVip['icon'] = $currentVipLevel['icon'];
    $userVip['benefits'] = $currentVipLevel['benefits'];
}

// Lấy tất cả VIP levels
$sql = "SELECT * FROM vip_levels ORDER BY level ASC";
$result = $conn->query($sql);
$allVipLevels = [];
while ($row = $result->fetch_assoc()) {
    $allVipLevels[] = $row;
}

// Tính progress đến level tiếp theo
$nextLevel = null;
foreach ($allVipLevels as $level) {
    if ($level['level'] > $userVip['vip_level']) {
        $nextLevel = $level;
        break;
    }
}

$progressPercent = 0;
if ($nextLevel) {
    $currentLevel = $allVipLevels[$userVip['vip_level'] - 1] ?? null;
    $currentRequired = $currentLevel ? $currentLevel['required_spent'] : 0;
    $nextRequired = $nextLevel['required_spent'];
    $progress = $userVip['total_spent'] - $currentRequired;
    $needed = $nextRequired - $currentRequired;
    $progressPercent = $needed > 0 ? min(100, round(($progress / $needed) * 100)) : 100;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIP System - Hệ Thống VIP</title>
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
        
        .header-vip {
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
        
        .header-vip::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.05) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }
        
        .header-vip h1 {
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
        
        .current-vip-card {
            background: linear-gradient(135deg, <?= $userVip['color'] ?? '#667eea' ?> 0%, <?= $userVip['color'] ?? '#764ba2' ?> 100%);
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
            color: white;
            text-align: center;
            animation: fadeInUp 0.6s ease 0.2s backwards;
            position: relative;
            overflow: hidden;
        }
        
        .current-vip-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 0%, transparent 70%);
            animation: float 8s ease-in-out infinite;
        }
        
        .vip-icon {
            font-size: 80px;
            margin-bottom: 20px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
            animation: pulse 2s ease-in-out infinite;
        }
        
        .vip-level-name {
            font-size: 36px;
            font-weight: 900;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .vip-benefits {
            margin-top: 20px;
            font-size: 18px;
            opacity: 0.95;
        }
        
        .vip-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .stat-box {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 16px;
            text-align: center;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 800;
        }
        
        .progress-section {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            animation: fadeInUp 0.6s ease 0.4s backwards;
        }
        
        .progress-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
            margin-bottom: 15px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmer 2s infinite;
        }
        
        .progress-text {
            text-align: center;
            color: #666;
            font-size: 16px;
            font-weight: 600;
        }
        
        .vip-levels-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        
        .vip-level-card {
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
        
        .vip-level-card::before {
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
        
        .vip-level-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .vip-level-card.current {
            border-color: <?= $userVip['color'] ?? '#667eea' ?>;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(255, 255, 255, 0.98) 100%);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.3);
        }
        
        .vip-level-card.unlocked {
            border-color: #28a745;
        }
        
        .vip-level-card.locked {
            opacity: 0.6;
            filter: grayscale(50%);
        }
        
        .vip-level-card:hover {
            transform: translateY(-10px) scale(1.04) rotate(1deg);
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.25);
        }
        
        .vip-level-card:hover::before {
            opacity: 1;
        }
        
        .vip-level-card:hover::after {
            left: 100%;
        }
        
        .level-icon {
            font-size: 56px;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .level-name {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .level-required {
            text-align: center;
            color: #666;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .level-benefits {
            padding: 15px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 12px;
            margin-bottom: 15px;
            font-size: 14px;
            color: #666;
            line-height: 1.6;
        }
        
        .claim-daily-btn {
            width: 100%;
            padding: 14px;
            margin-top: 15px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        
        .claim-daily-btn::before {
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
        
        .claim-daily-btn:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .claim-daily-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.5);
        }
        
        .claim-daily-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-vip">
            <h1>👑 VIP System</h1>
            <p style="color: #666; margin-top: 10px; font-size: 18px;">Nâng cấp VIP để nhận nhiều đặc quyền và phần thưởng!</p>
        </div>
        
        <div class="current-vip-card">
            <div class="vip-icon"><?= htmlspecialchars($userVip['icon'] ?? '⭐') ?></div>
            <div class="vip-level-name"><?= htmlspecialchars($userVip['vip_name'] ?? 'Bronze') ?> VIP</div>
            <div style="font-size: 20px; opacity: 0.9; margin-bottom: 20px;">
                Level <?= $userVip['vip_level'] ?>
            </div>
            
            <div class="vip-benefits">
                <?= htmlspecialchars($userVip['benefits'] ?? '') ?>
            </div>
            
            <div class="vip-stats">
                <div class="stat-box">
                    <div class="stat-label">Bonus Multiplier</div>
                    <div class="stat-value"><?= number_format((($userVip['bonus_multiplier'] ?? 1.0) - 1) * 100, 0) ?>%</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Daily Bonus</div>
                    <div class="stat-value"><?= number_format($userVip['daily_bonus'] ?? 0) ?> VNĐ</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Tổng Đã Chi</div>
                    <div class="stat-value"><?= number_format($userVip['total_spent']) ?> VNĐ</div>
                </div>
            </div>
            
            <?php if ($userVip['daily_bonus'] > 0): 
                $today = date('Y-m-d');
                $lastClaimed = $userVip['daily_bonus_claimed'] ?? null;
                $canClaim = ($lastClaimed != $today);
            ?>
                <div style="margin-top: 30px;">
                    <?php if ($canClaim): ?>
                        <button class="claim-daily-btn" onclick="claimDailyBonus()">
                            🎁 Nhận Daily Bonus (<?= number_format($userVip['daily_bonus']) ?> VNĐ)
                        </button>
                    <?php else: ?>
                        <button class="claim-daily-btn" disabled>
                            ✅ Đã Nhận Hôm Nay
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($nextLevel): ?>
            <div class="progress-section">
                <div class="progress-title">Tiến Độ Đến <?= htmlspecialchars($nextLevel['name']) ?> VIP</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $progressPercent ?>%"></div>
                </div>
                <div class="progress-text">
                    <?= number_format($userVip['total_spent']) ?> / <?= number_format($nextLevel['required_spent']) ?> VNĐ
                    (Còn <?= number_format($nextLevel['required_spent'] - $userVip['total_spent']) ?> VNĐ)
                </div>
            </div>
        <?php endif; ?>
        
        <div class="vip-levels-grid">
            <?php foreach ($allVipLevels as $level): 
                $isCurrent = $level['level'] == $userVip['vip_level'];
                $isUnlocked = $level['level'] <= $userVip['vip_level'];
                $isLocked = $level['level'] > $userVip['vip_level'];
            ?>
                <div class="vip-level-card <?= $isCurrent ? 'current' : '' ?> <?= $isUnlocked ? 'unlocked' : 'locked' ?>">
                    <div class="level-icon" style="color: <?= htmlspecialchars($level['color']) ?>;">
                        <?= htmlspecialchars($level['icon']) ?>
                    </div>
                    <div class="level-name" style="color: <?= htmlspecialchars($level['color']) ?>;">
                        <?= htmlspecialchars($level['name']) ?> VIP
                    </div>
                    <div class="level-required">
                        Yêu cầu: <?= number_format($level['required_spent']) ?> VNĐ
                    </div>
                    <div class="level-benefits">
                        <?= htmlspecialchars($level['benefits']) ?>
                    </div>
                    <?php if ($isCurrent): ?>
                        <div style="text-align: center; padding: 10px; background: rgba(102, 126, 234, 0.1); border-radius: 8px; color: #667eea; font-weight: 700;">
                            ✅ Đang Sử Dụng
                        </div>
                    <?php elseif ($isUnlocked): ?>
                        <div style="text-align: center; padding: 10px; background: rgba(40, 167, 69, 0.1); border-radius: 8px; color: #28a745; font-weight: 700;">
                            ✅ Đã Mở Khóa
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 10px; background: rgba(108, 117, 125, 0.1); border-radius: 8px; color: #6c757d; font-weight: 700;">
                            🔒 Chưa Mở Khóa
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" style="display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 12px; font-weight: 600;">
                🏠 Về Trang Chủ
            </a>
        </div>
    </div>
    
    <script>
        function claimDailyBonus() {
            $.ajax({
                url: 'api_vip.php',
                method: 'POST',
                data: {
                    action: 'claim_daily_bonus'
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
    </script>
</body>
</html>

