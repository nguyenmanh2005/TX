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

// Kiểm tra bảng achievements có tồn tại không
$checkAchievementsTable = $conn->query("SHOW TABLES LIKE 'achievements'");
$achievementsTableExists = $checkAchievementsTable && $checkAchievementsTable->num_rows > 0;

// Kiểm tra bảng user_achievements có tồn tại không
$checkUserAchievementsTable = $conn->query("SHOW TABLES LIKE 'user_achievements'");
$userAchievementsTableExists = $checkUserAchievementsTable && $checkUserAchievementsTable->num_rows > 0;

// Kiểm tra bảng game_history có tồn tại không
$checkGameHistoryTable = $conn->query("SHOW TABLES LIKE 'game_history'");
$gameHistoryTableExists = $checkGameHistoryTable && $checkGameHistoryTable->num_rows > 0;

// Kiểm tra bảng streak
$checkStreakTable = $conn->query("SHOW TABLES LIKE 'user_streaks'");
$streakTableExists = $checkStreakTable && $checkStreakTable->num_rows > 0;

// Lấy thông tin người dùng
$sql = "SELECT Iduser, Name, Money FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Lỗi prepare statement: " . $conn->error);
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($uid, $uname, $umoney);
if ($stmt->fetch()) {
    $user = [
        'Iduser' => $uid,
        'Name' => $uname,
        'Money' => $umoney,
    ];
} else {
    $stmt->close();
    die("Không tìm thấy thông tin người dùng!");
}
$stmt->close();

// Lấy tất cả achievements
$allAchievements = [];
if ($achievementsTableExists) {
    $allAchievementsSql = "SELECT * FROM achievements ORDER BY 
        CASE rarity 
            WHEN 'legendary' THEN 1 
            WHEN 'epic' THEN 2 
            WHEN 'rare' THEN 3 
            ELSE 4 
        END, requirement_value DESC";
    $allAchievementsResult = $conn->query($allAchievementsSql);
    if ($allAchievementsResult) {
        while ($row = $allAchievementsResult->fetch_assoc()) {
            $allAchievements[] = $row;
        }
    }
}

// Lấy achievements đã đạt được
$unlockedIds = [];
if ($userAchievementsTableExists) {
    $userAchievementsSql = "SELECT achievement_id FROM user_achievements WHERE user_id = ?";
    $userAchievementsStmt = $conn->prepare($userAchievementsSql);
    if ($userAchievementsStmt) {
        $userAchievementsStmt->bind_param("i", $userId);
        $userAchievementsStmt->execute();
        $userAchievementsStmt->bind_result($achId);
        while ($userAchievementsStmt->fetch()) {
            $unlockedIds[] = $achId;
        }
        $userAchievementsStmt->close();
    }
}

// Tính toán progress cho từng achievement
foreach ($allAchievements as &$achievement) {
    $achievement['unlocked'] = in_array($achievement['id'], $unlockedIds);
    
    // Tính progress dựa trên requirement_type
    $progress = 0;
    $maxProgress = $achievement['requirement_value'];
    
    switch ($achievement['requirement_type']) {
        case 'money':
            $progress = min($user['Money'], $maxProgress);
            break;
        case 'games_played':
            if ($gameHistoryTableExists) {
                $gamesSql = "SELECT COUNT(*) as count FROM game_history WHERE user_id = ?";
                $gamesStmt = $conn->prepare($gamesSql);
                if ($gamesStmt) {
                    $gamesStmt->bind_param("i", $userId);
                    $gamesStmt->execute();
                    $gamesStmt->bind_result($count);
                    if ($gamesStmt->fetch()) {
                        $progress = min((int)$count, $maxProgress);
                    }
                    $gamesStmt->close();
                }
            }
            break;
        case 'big_win':
            if ($gameHistoryTableExists) {
                $bigWinSql = "SELECT COUNT(*) as count FROM game_history WHERE user_id = ? AND win_amount >= ?";
                $bigWinStmt = $conn->prepare($bigWinSql);
                if ($bigWinStmt) {
                    $bigWinStmt->bind_param("id", $userId, $maxProgress);
                    $bigWinStmt->execute();
                    $bigWinStmt->bind_result($count);
                    if ($bigWinStmt->fetch()) {
                        $progress = min((int)$count, $maxProgress);
                    }
                    $bigWinStmt->close();
                }
            }
            break;
        case 'coinflip_games':
            if ($gameHistoryTableExists) {
                $cfSql = "SELECT COUNT(*) as count FROM game_history WHERE user_id = ? AND game_name = 'Coin Flip'";
                $cfStmt = $conn->prepare($cfSql);
                if ($cfStmt) {
                    $cfStmt->bind_param("i", $userId);
                    $cfStmt->execute();
                    $cfStmt->bind_result($count);
                    if ($cfStmt->fetch()) {
                        $progress = min((int)$count, $maxProgress);
                    }
                    $cfStmt->close();
                }
            }
            break;
        case 'coinflip_wins':
            if ($gameHistoryTableExists) {
                $cfWinSql = "SELECT COUNT(*) as count FROM game_history WHERE user_id = ? AND game_name = 'Coin Flip' AND is_win = 1";
                $cfWinStmt = $conn->prepare($cfWinSql);
                if ($cfWinStmt) {
                    $cfWinStmt->bind_param("i", $userId);
                    $cfWinStmt->execute();
                    $cfWinStmt->bind_result($count);
                    if ($cfWinStmt->fetch()) {
                        $progress = min((int)$count, $maxProgress);
                    }
                    $cfWinStmt->close();
                }
            }
            break;
        case 'streak':
            if ($streakTableExists) {
                $streakSql = "SELECT current_streak FROM user_streaks WHERE user_id = ?";
                $streakStmt = $conn->prepare($streakSql);
                if ($streakStmt) {
                    $streakStmt->bind_param("i", $userId);
                    $streakStmt->execute();
                    $streakStmt->bind_result($streakVal);
                    if ($streakStmt->fetch()) {
                        $progress = min((int)$streakVal, $maxProgress);
                    }
                    $streakStmt->close();
                }
            }
            break;
        case 'rank':
            $rankSql = "SELECT COUNT(*) + 1 as rank
                        FROM users u2 
                        WHERE u2.Money > ?";
            $rankStmt = $conn->prepare($rankSql);
            if ($rankStmt) {
                $rankStmt->bind_param("d", $user['Money']);
                $rankStmt->execute();
                $rankStmt->bind_result($rankVal);
                if ($rankStmt->fetch()) {
                    $currentRank = (int)$rankVal;
                    if ($currentRank <= $maxProgress) {
                        $progress = $maxProgress; // Đã đạt
                    } else {
                        $progress = 0; // Chưa đạt
                    }
                }
                $rankStmt->close();
            }
            break;
    }
    
    $achievement['progress'] = $progress;
    if ($maxProgress > 0) {
        $achievement['progress_percent'] = min(100, ($progress / $maxProgress) * 100);
    } else {
        $achievement['progress_percent'] = 0;
    }

    // Tự động unlock achievement nếu đạt yêu cầu mà chưa có
    if ($maxProgress > 0 && !$achievement['unlocked'] && $progress >= $maxProgress && $userAchievementsTableExists) {
        // Thêm vào user_achievements (tránh trùng bằng IGNORE)
        $insertSql = "INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES (?, ?)";
        $insStmt = $conn->prepare($insertSql);
        if ($insStmt) {
            $achId = (int)$achievement['id'];
            $insStmt->bind_param("ii", $userId, $achId);
            if ($insStmt->execute()) {
                $achievement['unlocked'] = true;
                $unlockedIds[] = $achId;

                // Thưởng tiền nếu có cấu hình
                if (!empty($achievement['reward_money']) && $achievement['reward_money'] > 0) {
                    $rewardMoney = (int)$achievement['reward_money'];
                    $rewardSql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
                    $rwStmt = $conn->prepare($rewardSql);
                    if ($rwStmt) {
                        $rwStmt->bind_param("ii", $rewardMoney, $userId);
                        $rwStmt->execute();
                        $rwStmt->close();
                    }
                }
            }
            $insStmt->close();
        }
    }
}
unset($achievement);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh Hiệu - Achievements</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .achievements-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header-achievements {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: var(--border-radius-lg);
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2),
                        0 0 0 1px rgba(255, 255, 255, 0.1);
            text-align: center;
            animation: fadeInDown 0.6s ease;
            position: relative;
            overflow: hidden;
        }
        
        .header-achievements::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.05) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }
        
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-box {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            padding: 20px;
            border-radius: var(--border-radius-lg);
            text-align: center;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            animation: fadeInScale 0.5s ease backwards;
        }
        
        .stat-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .stat-box:hover::before {
            left: 100%;
        }
        
        .stat-box:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.5);
        }
        
        .stat-box .stat-number {
            font-size: 36px;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .stat-box .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .achievements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .achievement-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius-lg);
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 3px solid transparent;
            position: relative;
            overflow: hidden;
            animation: fadeInScale 0.5s ease backwards;
        }
        
        .achievement-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .achievement-card:hover::after {
            left: 100%;
        }
        
        .achievement-card.unlocked {
            border-color: var(--success-color);
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(255, 255, 255, 0.95) 100%);
        }
        
        .achievement-card.legendary {
            border-color: #ffd700;
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.1) 0%, rgba(255, 255, 255, 0.95) 100%);
        }
        
        .achievement-card.epic {
            border-color: #9b59b6;
            background: linear-gradient(135deg, rgba(155, 89, 182, 0.1) 0%, rgba(255, 255, 255, 0.95) 100%);
        }
        
        .achievement-card.rare {
            border-color: #3498db;
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.1) 0%, rgba(255, 255, 255, 0.95) 100%);
        }
        
        .achievement-card:hover {
            transform: translateY(-8px) scale(1.05) rotate(1deg);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2),
                        0 0 0 1px rgba(102, 126, 234, 0.1);
        }
        
        .achievement-card.unlocked:hover {
            box-shadow: 0 12px 35px rgba(40, 167, 69, 0.3),
                        0 0 30px rgba(40, 167, 69, 0.2);
        }
        
        .achievement-card.legendary:hover {
            box-shadow: 0 12px 35px rgba(255, 215, 0, 0.4),
                        0 0 30px rgba(255, 215, 0, 0.3);
        }
        
        .achievement-icon {
            font-size: 64px;
            text-align: center;
            margin-bottom: 15px;
            filter: grayscale(100%);
            transition: filter 0.3s ease;
        }
        
        .achievement-card.unlocked .achievement-icon {
            filter: grayscale(0%);
            animation: achievementPulse 2s ease-in-out infinite;
        }
        
        @keyframes achievementPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .achievement-name {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
            text-align: center;
        }
        
        .achievement-description {
            color: var(--text-dark);
            margin-bottom: 15px;
            text-align: center;
            min-height: 50px;
        }
        
        .achievement-progress {
            margin: 15px 0;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color) 0%, var(--secondary-color) 100%);
            border-radius: 10px;
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 600;
        }
        
        .achievement-reward {
            text-align: center;
            margin-top: 10px;
            font-weight: 600;
            color: var(--success-color);
        }
        
        .rarity-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: var(--border-radius);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .rarity-badge.legendary {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #333;
        }
        
        .rarity-badge.epic {
            background: linear-gradient(135deg, #9b59b6, #bb8fce);
            color: white;
        }
        
        .rarity-badge.rare {
            background: linear-gradient(135deg, #3498db, #5dade2);
            color: white;
        }
        
        .rarity-badge.common {
            background: linear-gradient(135deg, #95a5a6, #b2babb);
            color: white;
        }
        
        .unlocked-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--success-color);
            color: white;
            padding: 5px 10px;
            border-radius: var(--border-radius);
            font-size: 12px;
            font-weight: 600;
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
    </style>
</head>
<body>
    <div class="achievements-container">
        <div class="header-achievements">
            <h1>🏆 Danh Hiệu - Achievements</h1>
            <?php if (empty($allAchievements)): ?>
                <div style="background: rgba(220, 53, 69, 0.2); border: 2px solid #dc3545; color: #dc3545; padding: 15px; border-radius: var(--border-radius); margin: 20px 0; font-weight: 600;">
                    ⚠️ Chưa có danh hiệu nào! Vui lòng chạy file <strong>database_updates.sql</strong> để tạo bảng achievements.
                </div>
            <?php endif; ?>
            <div class="stats-summary">
                <div class="stat-box">
                    <div class="stat-label">Tổng số danh hiệu</div>
                    <div class="stat-number"><?= count($allAchievements) ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Đã đạt được</div>
                    <div class="stat-number"><?= count($unlockedIds) ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Tiến độ</div>
                    <div class="stat-number"><?= count($allAchievements) > 0 ? round((count($unlockedIds) / count($allAchievements)) * 100) : 0 ?>%</div>
                </div>
            </div>
        </div>
        
        <div class="achievements-grid">
            <?php foreach ($allAchievements as $achievement): ?>
                <div class="achievement-card <?= $achievement['unlocked'] ? 'unlocked' : '' ?> <?= $achievement['rarity'] ?>">
                    <?php if ($achievement['unlocked']): ?>
                        <div class="unlocked-badge">✓ Đã đạt</div>
                    <?php endif; ?>
                    <div class="rarity-badge <?= $achievement['rarity'] ?>">
                        <?= $achievement['rarity'] ?>
                    </div>
                    <div class="achievement-icon">
                        <?= htmlspecialchars($achievement['icon'] ?? '🏆') ?>
                    </div>
                    <div class="achievement-name">
                        <?= htmlspecialchars($achievement['name']) ?>
                    </div>
                    <div class="achievement-description">
                        <?= htmlspecialchars($achievement['description']) ?>
                    </div>
                    <div class="achievement-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $achievement['progress_percent'] ?>%">
                                <?= $achievement['progress_percent'] >= 10 ? number_format($achievement['progress']) . ' / ' . number_format($achievement['requirement_value']) : '' ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($achievement['reward_money'] > 0): ?>
                        <div class="achievement-reward">
                            💰 Phần thưởng: <?= number_format($achievement['reward_money'], 0, ',', '.') ?> VNĐ
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <a href="index.php" class="back-link">🏠 Về Trang Chủ</a>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
            
            const interactiveElements = document.querySelectorAll('button, a, input, label, select');
            interactiveElements.forEach(el => {
                el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            });
        });
    </script>
</body>
</html>

