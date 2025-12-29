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

// Kiểm tra bảng daily_challenges có tồn tại không
$checkTable = $conn->query("SHOW TABLES LIKE 'daily_challenges'");
$tableExists = $checkTable && $checkTable->num_rows > 0;

// Tạo bảng nếu chưa có
if (!$tableExists) {
    $createTable = "CREATE TABLE IF NOT EXISTS daily_challenges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        challenge_date DATE NOT NULL,
        challenge_type VARCHAR(50) NOT NULL,
        challenge_name VARCHAR(255) NOT NULL,
        description TEXT,
        requirement_value INT NOT NULL,
        reward_money INT DEFAULT 0,
        reward_xp INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_date_type (challenge_date, challenge_type)
    )";
    $conn->query($createTable);
    
    $createProgress = "CREATE TABLE IF NOT EXISTS daily_challenge_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        challenge_id INT NOT NULL,
        progress INT DEFAULT 0,
        is_completed TINYINT(1) DEFAULT 0,
        completed_at TIMESTAMP NULL,
        claimed TINYINT(1) DEFAULT 0,
        claimed_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(Iduser) ON DELETE CASCADE,
        FOREIGN KEY (challenge_id) REFERENCES daily_challenges(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_challenge (user_id, challenge_id),
        INDEX idx_user_date (user_id, challenge_id)
    )";
    $conn->query($createProgress);
}

// Lấy thông tin người dùng
$sql = "SELECT Iduser, Name, Money FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Lấy thử thách hôm nay
$today = date('Y-m-d');
$challenges = [];

if ($tableExists) {
    $sql = "SELECT dc.*, 
            COALESCE(dcp.progress, 0) as user_progress,
            COALESCE(dcp.is_completed, 0) as is_completed,
            COALESCE(dcp.claimed, 0) as claimed
            FROM daily_challenges dc
            LEFT JOIN daily_challenge_progress dcp ON dc.id = dcp.challenge_id AND dcp.user_id = ?
            WHERE dc.challenge_date = ?
            ORDER BY dc.id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $userId, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $challenges[] = $row;
    }
    $stmt->close();
    
    // Nếu chưa có thử thách hôm nay, tạo tự động
    if (empty($challenges)) {
        $autoChallenges = [
            [
                'type' => 'play_games',
                'name' => 'Chơi 5 Game',
                'description' => 'Chơi tổng cộng 5 game bất kỳ',
                'requirement' => 5,
                'reward_money' => 50000,
                'reward_xp' => 50
            ],
            [
                'type' => 'win_games',
                'name' => 'Thắng 3 Game',
                'description' => 'Thắng tổng cộng 3 game',
                'requirement' => 3,
                'reward_money' => 100000,
                'reward_xp' => 100
            ],
            [
                'type' => 'earn_money',
                'name' => 'Kiếm 500,000 VNĐ',
                'description' => 'Kiếm được tổng cộng 500,000 VNĐ từ các game',
                'requirement' => 500000,
                'reward_money' => 200000,
                'reward_xp' => 150
            ]
        ];
        
        foreach ($autoChallenges as $challenge) {
            $sql = "INSERT INTO daily_challenges (challenge_date, challenge_type, challenge_name, description, requirement_value, reward_money, reward_xp)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE challenge_name = VALUES(challenge_name)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssiii", $today, $challenge['type'], $challenge['name'], 
                            $challenge['description'], $challenge['requirement'], 
                            $challenge['reward_money'], $challenge['reward_xp']);
            $stmt->execute();
            $challengeId = $conn->insert_id;
            $stmt->close();
            
            // Tạo progress cho user
            $sql = "INSERT INTO daily_challenge_progress (user_id, challenge_id, progress)
                    VALUES (?, ?, 0)
                    ON DUPLICATE KEY UPDATE progress = progress";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $userId, $challengeId);
            $stmt->execute();
            $stmt->close();
        }
        
        // Reload challenges
        $sql = "SELECT dc.*, 
                COALESCE(dcp.progress, 0) as user_progress,
                COALESCE(dcp.is_completed, 0) as is_completed,
                COALESCE(dcp.claimed, 0) as claimed
                FROM daily_challenges dc
                LEFT JOIN daily_challenge_progress dcp ON dc.id = dcp.challenge_id AND dcp.user_id = ?
                WHERE dc.challenge_date = ?
                ORDER BY dc.id";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $userId, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $challenges = [];
        while ($row = $result->fetch_assoc()) {
            $challenges[] = $row;
        }
        $stmt->close();
    }
}

// Cập nhật progress từ game_history
if ($tableExists && !empty($challenges)) {
    require_once 'game_history_helper.php';
    
    foreach ($challenges as $challenge) {
        if ($challenge['is_completed'] == 1) continue;
        
        $progress = 0;
        $todayStart = $today . ' 00:00:00';
        $todayEnd = $today . ' 23:59:59';
        
        switch ($challenge['challenge_type']) {
            case 'play_games':
                $sql = "SELECT COUNT(*) as count FROM game_history 
                        WHERE user_id = ? AND played_at BETWEEN ? AND ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $userId, $todayStart, $todayEnd);
                $stmt->execute();
                $result = $stmt->get_result();
                $progress = $result->fetch_assoc()['count'] ?? 0;
                $stmt->close();
                break;
                
            case 'win_games':
                $sql = "SELECT COUNT(*) as count FROM game_history 
                        WHERE user_id = ? AND is_win = 1 AND played_at BETWEEN ? AND ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $userId, $todayStart, $todayEnd);
                $stmt->execute();
                $result = $stmt->get_result();
                $progress = $result->fetch_assoc()['count'] ?? 0;
                $stmt->close();
                break;
                
            case 'earn_money':
                $sql = "SELECT SUM(win_amount - bet_amount) as total FROM game_history 
                        WHERE user_id = ? AND is_win = 1 AND played_at BETWEEN ? AND ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $userId, $todayStart, $todayEnd);
                $stmt->execute();
                $result = $stmt->get_result();
                $progress = max(0, $result->fetch_assoc()['total'] ?? 0);
                $stmt->close();
                break;
        }
        
        // Cập nhật progress
        $isCompleted = ($progress >= $challenge['requirement_value']) ? 1 : 0;
        $sql = "UPDATE daily_challenge_progress 
                SET progress = ?, is_completed = ?, completed_at = ?
                WHERE user_id = ? AND challenge_id = ?";
        $stmt = $conn->prepare($sql);
        $completedAt = $isCompleted ? date('Y-m-d H:i:s') : null;
        $stmt->bind_param("iissi", $progress, $isCompleted, $completedAt, $userId, $challenge['id']);
        $stmt->execute();
        $stmt->close();
        
        // Cập nhật lại challenge trong array
        $challenge['user_progress'] = $progress;
        $challenge['is_completed'] = $isCompleted;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thử Thách Hàng Ngày - Daily Challenges</title>
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
        
        .header-challenges {
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
        
        .header-challenges::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.05) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }
        
        .header-challenges h1 {
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
        
        .challenges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        
        .challenge-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border: 3px solid #e0e0e0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease backwards;
        }
        
        .challenge-card::before {
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
        
        .challenge-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .challenge-card.completed {
            border-color: #28a745;
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.15) 0%, rgba(255, 255, 255, 0.98) 100%);
        }
        
        .challenge-card.completed::before {
            background: radial-gradient(circle, rgba(40, 167, 69, 0.15) 0%, transparent 70%);
        }
        
        .challenge-card:hover {
            transform: translateY(-10px) scale(1.04) rotate(1deg);
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.25),
                        0 0 0 1px rgba(102, 126, 234, 0.1);
        }
        
        .challenge-card:hover::before {
            opacity: 1;
        }
        
        .challenge-card:hover::after {
            left: 100%;
        }
        
        .challenge-card.completed:hover {
            box-shadow: 0 18px 45px rgba(40, 167, 69, 0.3),
                        0 0 30px rgba(40, 167, 69, 0.2);
        }
        
        .challenge-icon {
            font-size: 56px;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .challenge-name {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .challenge-description {
            color: #666;
            margin-bottom: 20px;
            text-align: center;
            min-height: 50px;
        }
        
        .challenge-progress {
            margin: 20px 0;
        }
        
        .progress-bar {
            width: 100%;
            height: 12px;
            background: #e0e0e0;
            border-radius: 6px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 6px;
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
            margin-top: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #666;
        }
        
        .challenge-reward {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .reward-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 20px;
            font-weight: 600;
            color: #667eea;
        }
        
        .claim-btn {
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
        
        .claim-btn::before {
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
        
        .claim-btn:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .claim-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.5);
        }
        
        .claim-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .claimed-badge {
            width: 100%;
            padding: 14px;
            margin-top: 15px;
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-challenges">
            <h1>🎯 Thử Thách Hàng Ngày</h1>
            <p style="color: #666; margin-top: 10px; font-size: 18px;">Hoàn thành thử thách để nhận phần thưởng!</p>
        </div>
        
        <?php if (empty($challenges)): ?>
            <div style="text-align: center; padding: 40px; background: rgba(255, 255, 255, 0.98); border-radius: 20px;">
                <p style="font-size: 18px; color: #666;">Chưa có thử thách hôm nay. Vui lòng quay lại sau!</p>
            </div>
        <?php else: ?>
            <div class="challenges-grid">
                <?php foreach ($challenges as $challenge): 
                    $progressPercent = min(100, round(($challenge['user_progress'] / $challenge['requirement_value']) * 100));
                    $isCompleted = $challenge['is_completed'] == 1;
                    $isClaimed = $challenge['claimed'] == 1;
                ?>
                    <div class="challenge-card <?= $isCompleted ? 'completed' : '' ?>">
                        <div class="challenge-icon">
                            <?php
                            switch ($challenge['challenge_type']) {
                                case 'play_games':
                                    echo '🎮';
                                    break;
                                case 'win_games':
                                    echo '🏆';
                                    break;
                                case 'earn_money':
                                    echo '💰';
                                    break;
                                default:
                                    echo '⭐';
                            }
                            ?>
                        </div>
                        <div class="challenge-name"><?= htmlspecialchars($challenge['challenge_name']) ?></div>
                        <div class="challenge-description"><?= htmlspecialchars($challenge['description']) ?></div>
                        
                        <div class="challenge-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $progressPercent ?>%"></div>
                            </div>
                            <div class="progress-text">
                                <?= number_format($challenge['user_progress']) ?> / <?= number_format($challenge['requirement_value']) ?>
                            </div>
                        </div>
                        
                        <div class="challenge-reward">
                            <?php if ($challenge['reward_money'] > 0): ?>
                                <div class="reward-item">
                                    <i class="fas fa-coins"></i>
                                    <span><?= number_format($challenge['reward_money']) ?> VNĐ</span>
                                </div>
                            <?php endif; ?>
                            <?php if ($challenge['reward_xp'] > 0): ?>
                                <div class="reward-item">
                                    <i class="fas fa-star"></i>
                                    <span><?= number_format($challenge['reward_xp']) ?> XP</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($isClaimed): ?>
                            <div class="claimed-badge">✅ Đã Nhận Phần Thưởng</div>
                        <?php elseif ($isCompleted): ?>
                            <button class="claim-btn" onclick="claimReward(<?= $challenge['id'] ?>)">
                                🎁 Nhận Phần Thưởng
                            </button>
                        <?php else: ?>
                            <button class="claim-btn" disabled>
                                ⏳ Chưa Hoàn Thành
                            </button>
                        <?php endif; ?>
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
        function claimReward(challengeId) {
            $.ajax({
                url: 'api_daily_challenges.php',
                method: 'POST',
                data: {
                    action: 'claim',
                    challenge_id: challengeId
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

