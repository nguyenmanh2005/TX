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

// Kiểm tra và tạo bảng streak nếu chưa có
$checkTable = $conn->query("SHOW TABLES LIKE 'user_streaks'");
if (!$checkTable || $checkTable->num_rows == 0) {
    $createTable = "CREATE TABLE IF NOT EXISTS user_streaks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        current_streak INT DEFAULT 0,
        longest_streak INT DEFAULT 0,
        last_play_date DATE,
        total_days_played INT DEFAULT 0,
        streak_bonus_multiplier DECIMAL(3,2) DEFAULT 1.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(Iduser) ON DELETE CASCADE,
        INDEX idx_user_date (user_id, last_play_date)
    )";
    $conn->query($createTable);
}

// Lấy thông tin người dùng
$sql = "SELECT Iduser, Name, Money FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Lấy thông tin streak
$sql = "SELECT * FROM user_streaks WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$streakData = $result->fetch_assoc();
$stmt->close();

// Nếu chưa có record, tạo mới
if (!$streakData) {
    $sql = "INSERT INTO user_streaks (user_id, current_streak, longest_streak, last_play_date, total_days_played)
            VALUES (?, 0, 0, NULL, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();

    // Reload
    $sql = "SELECT * FROM user_streaks WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $streakData = $result->fetch_assoc();
    $stmt->close();
}

// Tính toán streak bonus multiplier
$currentStreak = $streakData['current_streak'] ?? 0;
$streakBonus = 1.00;
if ($currentStreak >= 30) {
    $streakBonus = 2.00; // 100% bonus cho streak 30+ ngày
} elseif ($currentStreak >= 14) {
    $streakBonus = 1.50; // 50% bonus cho streak 14+ ngày
} elseif ($currentStreak >= 7) {
    $streakBonus = 1.25; // 25% bonus cho streak 7+ ngày
} elseif ($currentStreak >= 3) {
    $streakBonus = 1.10; // 10% bonus cho streak 3+ ngày
}

// Cập nhật multiplier nếu khác
if ($streakData['streak_bonus_multiplier'] != $streakBonus) {
    $sql = "UPDATE user_streaks SET streak_bonus_multiplier = ? WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("di", $streakBonus, $userId);
    $stmt->execute();
    $stmt->close();
}

// Lấy lịch sử streak gần đây (30 ngày)
$sql = "SELECT DATE(played_at) as play_date, COUNT(*) as games_played
        FROM game_history
        WHERE user_id = ? AND played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(played_at)
        ORDER BY play_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$playHistory = [];
while ($row = $result->fetch_assoc()) {
    $playHistory[$row['play_date']] = $row['games_played'];
}
$stmt->close();

// Tính streak milestones và rewards
$milestones = [
    3 => ['reward' => 50000, 'xp' => 50, 'name' => '🔥 Streak 3 Ngày'],
    7 => ['reward' => 150000, 'xp' => 150, 'name' => '⚡ Streak 1 Tuần'],
    14 => ['reward' => 350000, 'xp' => 350, 'name' => '💎 Streak 2 Tuần'],
    30 => ['reward' => 1000000, 'xp' => 1000, 'name' => '👑 Streak 1 Tháng'],
    60 => ['reward' => 2500000, 'xp' => 2500, 'name' => '🌟 Streak 2 Tháng'],
    100 => ['reward' => 5000000, 'xp' => 5000, 'name' => '🏆 Streak 100 Ngày']
];

// Kiểm tra milestone đã claim chưa
$claimedMilestones = [];
$checkClaimed = $conn->query("SHOW TABLES LIKE 'streak_milestone_rewards'");
if ($checkClaimed && $checkClaimed->num_rows > 0) {
    $sql = "SELECT milestone_days FROM streak_milestone_rewards WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $claimedMilestones[] = $row['milestone_days'];
    }
    $stmt->close();
} else {
    // Tạo bảng nếu chưa có
    $createClaimed = "CREATE TABLE IF NOT EXISTS streak_milestone_rewards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        milestone_days INT NOT NULL,
        reward_money INT NOT NULL,
        reward_xp INT NOT NULL,
        claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(Iduser) ON DELETE CASCADE,
        UNIQUE KEY unique_user_milestone (user_id, milestone_days)
    )";
    $conn->query($createClaimed);
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Streak System - Chuỗi Ngày Chơi</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            animation: fadeIn 0.6s ease;
        }

        * {
            cursor: inherit;
        }

        button,
        a,
        input[type="button"],
        input[type="submit"],
        label,
        select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header-streak {
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

        .header-streak::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.05) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        .header-streak h1 {
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

        .streak-display {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            text-align: center;
            animation: fadeInUp 0.6s ease 0.2s backwards;
        }

        .streak-number {
            font-size: 72px;
            font-weight: 900;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 50%, #c44569 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 20px 0;
            animation: pulse 2s ease-in-out infinite;
        }

        .streak-label {
            font-size: 24px;
            color: #666;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .bonus-info {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .bonus-item {
            padding: 20px 30px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-radius: 16px;
            border: 2px solid rgba(102, 126, 234, 0.3);
        }

        .bonus-item-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }

        .bonus-item-value {
            font-size: 28px;
            font-weight: 700;
            color: #667eea;
        }

        .milestones-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .milestone-card {
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

        .milestone-card::before {
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

        .milestone-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .milestone-card.achieved {
            border-color: #28a745;
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.15) 0%, rgba(255, 255, 255, 0.98) 100%);
        }

        .milestone-card.claimed {
            opacity: 0.6;
            border-color: #ccc;
        }

        .milestone-card:hover {
            transform: translateY(-10px) scale(1.04) rotate(1deg);
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.25);
        }

        .milestone-card:hover::before {
            opacity: 1;
        }

        .milestone-card:hover::after {
            left: 100%;
        }

        .milestone-icon {
            font-size: 48px;
            text-align: center;
            margin-bottom: 15px;
        }

        .milestone-name {
            font-size: 22px;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
            text-align: center;
        }

        .milestone-reward {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 15px;
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

        .claim-milestone-btn {
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

        .claim-milestone-btn::before {
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

        .claim-milestone-btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .claim-milestone-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.5);
        }

        .claim-milestone-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .calendar-view {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            margin-bottom: 30px;
        }

        .calendar-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }

        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .calendar-day.played {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .calendar-day.today {
            border: 3px solid #667eea;
            background: rgba(102, 126, 234, 0.1);
        }

        .calendar-day.empty {
            background: #f0f0f0;
            color: #ccc;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header-streak">
            <h1>🔥 Streak System</h1>
            <p style="color: #666; margin-top: 10px; font-size: 18px;">Chơi game mỗi ngày để duy trì streak và nhận
                bonus!</p>
        </div>

        <div class="streak-display">
            <div class="streak-label">Chuỗi Ngày Chơi Hiện Tại</div>
            <div class="streak-number"><?= $currentStreak ?></div>
            <div style="color: #666; font-size: 18px; margin-bottom: 20px;">
                Chuỗi dài nhất: <strong><?= $streakData['longest_streak'] ?? 0 ?></strong> ngày
            </div>

            <div class="bonus-info">
                <div class="bonus-item">
                    <div class="bonus-item-label">Bonus Multiplier</div>
                    <div class="bonus-item-value"><?= number_format(($streakBonus - 1) * 100, 0) ?>%</div>
                </div>
                <div class="bonus-item">
                    <div class="bonus-item-label">Tổng Ngày Chơi</div>
                    <div class="bonus-item-value"><?= $streakData['total_days_played'] ?? 0 ?></div>
                </div>
            </div>
        </div>

        <div class="calendar-view">
            <div class="calendar-title">📅 Lịch Chơi 30 Ngày Gần Đây</div>
            <div class="calendar-grid">
                <?php
                $today = date('Y-m-d');
                for ($i = 29; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $dayNum = date('d', strtotime($date));
                    $isPlayed = isset($playHistory[$date]);
                    $isToday = ($date == $today);
                    $class = $isPlayed ? 'played' : ($isToday ? 'today' : 'empty');
                    echo "<div class='calendar-day $class'>$dayNum</div>";
                }
                ?>
            </div>
        </div>

        <div class="milestones-grid">
            <?php foreach ($milestones as $days => $milestone):
                $isAchieved = $currentStreak >= $days;
                $isClaimed = in_array($days, $claimedMilestones);
                $canClaim = $isAchieved && !$isClaimed;
                ?>
                <div class="milestone-card <?= $isAchieved ? 'achieved' : '' ?> <?= $isClaimed ? 'claimed' : '' ?>">
                    <div class="milestone-icon">
                        <?php
                        if ($days >= 100)
                            echo '🏆';
                        elseif ($days >= 60)
                            echo '🌟';
                        elseif ($days >= 30)
                            echo '👑';
                        elseif ($days >= 14)
                            echo '💎';
                        elseif ($days >= 7)
                            echo '⚡';
                        else
                            echo '🔥';
                        ?>
                    </div>
                    <div class="milestone-name"><?= $milestone['name'] ?></div>
                    <div style="text-align: center; color: #666; margin-bottom: 15px;">
                        Streak <?= $days ?> ngày
                    </div>

                    <div class="milestone-reward">
                        <div class="reward-item">
                            <i class="fas fa-coins"></i>
                            <span><?= number_format($milestone['reward']) ?> gtlm</span>
                        </div>
                        <div class="reward-item">
                            <i class="fas fa-star"></i>
                            <span><?= number_format($milestone['xp']) ?> XP</span>
                        </div>
                    </div>

                    <?php if ($isClaimed): ?>
                        <div
                            style="text-align: center; padding: 14px; background: #6c757d; color: white; border-radius: 12px; font-weight: 700;">
                            ✅ Đã Nhận
                        </div>
                    <?php elseif ($canClaim): ?>
                        <button class="claim-milestone-btn" onclick="claimMilestone(<?= $days ?>)">
                            🎁 Nhận Phần Thưởng
                        </button>
                    <?php else: ?>
                        <button class="claim-milestone-btn" disabled>
                            ⏳ Chưa Đạt (<?= $currentStreak ?>/<?= $days ?>)
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php"
                style="display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 12px; font-weight: 600;">
                🏠 Về Trang Chủ
            </a>
        </div>
    </div>

    <script>
        function claimMilestone(days) {
            $.ajax({
                url: 'api_streak.php',
                method: 'POST',
                data: {
                    action: 'claim_milestone',
                    milestone_days: days
                },
                dataType: 'json',
                success: function (response) {
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
                error: function () {
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