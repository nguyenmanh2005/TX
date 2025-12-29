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

// Kiểm tra và tạo bảng weekly_leaderboard nếu chưa có
$checkTable = $conn->query("SHOW TABLES LIKE 'weekly_leaderboard'");
if (!$checkTable || $checkTable->num_rows == 0) {
    $createTable = "CREATE TABLE IF NOT EXISTS weekly_leaderboard (
        id INT AUTO_INCREMENT PRIMARY KEY,
        week_start DATE NOT NULL,
        user_id INT NOT NULL,
        total_earned DECIMAL(15,2) DEFAULT 0,
        total_games INT DEFAULT 0,
        total_wins INT DEFAULT 0,
        win_rate DECIMAL(5,2) DEFAULT 0,
        rank_position INT DEFAULT 0,
        reward_claimed TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(Iduser) ON DELETE CASCADE,
        UNIQUE KEY unique_week_user (week_start, user_id),
        INDEX idx_week_rank (week_start, rank_position),
        INDEX idx_week_earned (week_start, total_earned DESC)
    )";
    $conn->query($createTable);
    
    $createRewards = "CREATE TABLE IF NOT EXISTS weekly_leaderboard_rewards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        week_start DATE NOT NULL,
        rank_position INT NOT NULL,
        reward_money INT NOT NULL,
        reward_xp INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_week_rank (week_start, rank_position)
    )";
    $conn->query($createRewards);
}

// Tính tuần hiện tại (bắt đầu từ thứ 2)
$today = new DateTime();
$dayOfWeek = $today->format('w'); // 0 = Chủ nhật, 1 = Thứ 2, ...
if ($dayOfWeek == 0) {
    $dayOfWeek = 7; // Chuyển Chủ nhật thành 7
}
$daysToMonday = $dayOfWeek - 1;
$weekStart = clone $today;
$weekStart->modify("-$daysToMonday days");
$weekStartStr = $weekStart->format('Y-m-d');
$weekEnd = clone $weekStart;
$weekEnd->modify('+6 days');
$weekEndStr = $weekEnd->format('Y-m-d');

// Lấy thông tin người dùng
$sql = "SELECT Iduser, Name, Money FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Cập nhật leaderboard từ game_history
$checkGameHistory = $conn->query("SHOW TABLES LIKE 'game_history'");
if ($checkGameHistory && $checkGameHistory->num_rows > 0) {
    // Tính toán stats cho tất cả users
    $sql = "SELECT 
                user_id,
                SUM(CASE WHEN is_win = 1 THEN win_amount - bet_amount ELSE 0 END) as total_earned,
                COUNT(*) as total_games,
                SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END) as total_wins
            FROM game_history
            WHERE DATE(played_at) BETWEEN ? AND ?
            GROUP BY user_id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $weekStartStr, $weekEndStr);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $leaderboardData = [];
    while ($row = $result->fetch_assoc()) {
        $winRate = $row['total_games'] > 0 ? round(($row['total_wins'] / $row['total_games']) * 100, 2) : 0;
        $leaderboardData[] = [
            'user_id' => $row['user_id'],
            'total_earned' => $row['total_earned'] ?? 0,
            'total_games' => $row['total_games'],
            'total_wins' => $row['total_wins'],
            'win_rate' => $winRate
        ];
    }
    $stmt->close();
    
    // Sắp xếp theo total_earned
    usort($leaderboardData, function($a, $b) {
        return $b['total_earned'] <=> $a['total_earned'];
    });
    
    // Cập nhật vào database
    $conn->begin_transaction();
    try {
        foreach ($leaderboardData as $rank => $data) {
            $rankPosition = $rank + 1;
            $sql = "INSERT INTO weekly_leaderboard 
                    (week_start, user_id, total_earned, total_games, total_wins, win_rate, rank_position)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    total_earned = VALUES(total_earned),
                    total_games = VALUES(total_games),
                    total_wins = VALUES(total_wins),
                    win_rate = VALUES(win_rate),
                    rank_position = VALUES(rank_position)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sidiidi", $weekStartStr, $data['user_id'], $data['total_earned'], 
                            $data['total_games'], $data['total_wins'], $data['win_rate'], $rankPosition);
            $stmt->execute();
            $stmt->close();
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
    }
}

// Lấy top 100 leaderboard
$sql = "SELECT wl.*, u.Name, u.ImageURL
        FROM weekly_leaderboard wl
        JOIN users u ON wl.user_id = u.Iduser
        WHERE wl.week_start = ?
        ORDER BY wl.rank_position ASC
        LIMIT 100";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $weekStartStr);
$stmt->execute();
$result = $stmt->get_result();
$leaderboard = [];
while ($row = $result->fetch_assoc()) {
    $leaderboard[] = $row;
}
$stmt->close();

// Lấy rank của user hiện tại
$userRank = null;
$userStats = null;
foreach ($leaderboard as $entry) {
    if ($entry['user_id'] == $userId) {
        $userRank = $entry['rank_position'];
        $userStats = $entry;
        break;
    }
}

// Nếu user không có trong top 100, tìm rank thực tế
if (!$userRank) {
    $sql = "SELECT rank_position, total_earned, total_games, total_wins, win_rate
            FROM weekly_leaderboard
            WHERE week_start = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $weekStartStr, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $userStats = $result->fetch_assoc();
        $userRank = $userStats['rank_position'];
    }
    $stmt->close();
}

// Phần thưởng theo rank
$rankRewards = [
    1 => ['money' => 5000000, 'xp' => 5000, 'name' => '👑 Hạng 1'],
    2 => ['money' => 3000000, 'xp' => 3000, 'name' => '🥈 Hạng 2'],
    3 => ['money' => 2000000, 'xp' => 2000, 'name' => '🥉 Hạng 3'],
    4 => ['money' => 1000000, 'xp' => 1000, 'name' => '🏅 Top 4-10'],
    10 => ['money' => 500000, 'xp' => 500, 'name' => '⭐ Top 11-50'],
    50 => ['money' => 200000, 'xp' => 200, 'name' => '🎯 Top 51-100']
];

// Kiểm tra đã claim reward chưa
$rewardClaimed = false;
if ($userRank && $userRank <= 100) {
    $sql = "SELECT reward_claimed FROM weekly_leaderboard WHERE week_start = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $weekStartStr, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $rewardClaimed = $result->fetch_assoc()['reward_claimed'] == 1;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Leaderboard - Bảng Xếp Hạng Tuần</title>
    <link rel="stylesheet" href="assets/css/main.css">
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
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header-leaderboard {
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
        
        .header-leaderboard::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.05) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }
        
        .header-leaderboard h1 {
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
        
        .week-info {
            margin-top: 15px;
            color: #666;
            font-size: 18px;
        }
        
        .user-rank-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            animation: fadeInUp 0.6s ease 0.2s backwards;
        }
        
        .user-rank-display {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .user-rank-number {
            font-size: 64px;
            font-weight: 900;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .user-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-box {
            padding: 20px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-radius: 16px;
            text-align: center;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
        }
        
        .leaderboard-table {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 700;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }
        
        .rank-badge {
            display: inline-block;
            width: 40px;
            height: 40px;
            line-height: 40px;
            text-align: center;
            border-radius: 50%;
            font-weight: 700;
            color: white;
        }
        
        .rank-1 { background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%); }
        .rank-2 { background: linear-gradient(135deg, #c0c0c0 0%, #e8e8e8 100%); }
        .rank-3 { background: linear-gradient(135deg, #cd7f32 0%, #e6a85c 100%); }
        .rank-other { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        
        .claim-reward-btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }
        
        .claim-reward-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.5);
        }
        
        .claim-reward-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-leaderboard">
            <h1>🏆 Weekly Leaderboard</h1>
            <div class="week-info">
                Tuần từ <?= date('d/m/Y', strtotime($weekStartStr)) ?> đến <?= date('d/m/Y', strtotime($weekEndStr)) ?>
            </div>
        </div>
        
        <?php if ($userRank && $userStats): ?>
            <div class="user-rank-card">
                <div class="user-rank-display">
                    <div style="font-size: 20px; color: #666; margin-bottom: 10px;">Xếp Hạng Của Bạn</div>
                    <div class="user-rank-number">#<?= $userRank ?></div>
                </div>
                
                <div class="user-stats">
                    <div class="stat-box">
                        <div class="stat-label">Tổng Kiếm Được</div>
                        <div class="stat-value"><?= number_format($userStats['total_earned']) ?> VNĐ</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Số Game</div>
                        <div class="stat-value"><?= number_format($userStats['total_games']) ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Số Thắng</div>
                        <div class="stat-value"><?= number_format($userStats['total_wins']) ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Tỷ Lệ Thắng</div>
                        <div class="stat-value"><?= number_format($userStats['win_rate'], 1) ?>%</div>
                    </div>
                </div>
                
                <?php
                $reward = null;
                if ($userRank == 1) {
                    $reward = $rankRewards[1];
                } elseif ($userRank == 2) {
                    $reward = $rankRewards[2];
                } elseif ($userRank == 3) {
                    $reward = $rankRewards[3];
                } elseif ($userRank <= 10) {
                    $reward = $rankRewards[4];
                } elseif ($userRank <= 50) {
                    $reward = $rankRewards[10];
                } elseif ($userRank <= 100) {
                    $reward = $rankRewards[50];
                }
                
                if ($reward && !$rewardClaimed):
                ?>
                    <div style="text-align: center; margin-top: 20px; padding: 20px; background: rgba(40, 167, 69, 0.1); border-radius: 16px;">
                        <div style="font-size: 18px; font-weight: 700; color: #28a745; margin-bottom: 10px;">
                            <?= $reward['name'] ?> - Phần Thưởng
                        </div>
                        <div style="color: #666; margin-bottom: 15px;">
                            <?= number_format($reward['money']) ?> VNĐ + <?= number_format($reward['xp']) ?> XP
                        </div>
                        <button class="claim-reward-btn" onclick="claimReward()">
                            🎁 Nhận Phần Thưởng
                        </button>
                    </div>
                <?php elseif ($reward && $rewardClaimed): ?>
                    <div style="text-align: center; margin-top: 20px; padding: 20px; background: rgba(108, 117, 125, 0.1); border-radius: 16px; color: #666;">
                        ✅ Đã nhận phần thưởng tuần này
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="leaderboard-table">
            <h2 style="margin-bottom: 20px; text-align: center; color: #333;">Top 100 Người Chơi</h2>
            <table>
                <thead>
                    <tr>
                        <th>Hạng</th>
                        <th>Người Chơi</th>
                        <th>Tổng Kiếm Được</th>
                        <th>Số Game</th>
                        <th>Số Thắng</th>
                        <th>Tỷ Lệ Thắng</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leaderboard)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: #666;">
                                Chưa có dữ liệu tuần này. Hãy chơi game để xuất hiện trên bảng xếp hạng!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($leaderboard as $entry): 
                            $isCurrentUser = $entry['user_id'] == $userId;
                            $rankClass = $entry['rank_position'] == 1 ? 'rank-1' : 
                                        ($entry['rank_position'] == 2 ? 'rank-2' : 
                                        ($entry['rank_position'] == 3 ? 'rank-3' : 'rank-other'));
                        ?>
                            <tr style="<?= $isCurrentUser ? 'background: rgba(102, 126, 234, 0.1); font-weight: 700;' : '' ?>">
                                <td>
                                    <span class="rank-badge <?= $rankClass ?>">
                                        <?= $entry['rank_position'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <img src="<?= htmlspecialchars($entry['ImageURL'] ?? 'img/default-avatar.png') ?>" 
                                             style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                        <span><?= htmlspecialchars($entry['Name']) ?></span>
                                        <?= $isCurrentUser ? '<span style="color: #667eea;">(Bạn)</span>' : '' ?>
                                    </div>
                                </td>
                                <td><?= number_format($entry['total_earned']) ?> VNĐ</td>
                                <td><?= number_format($entry['total_games']) ?></td>
                                <td><?= number_format($entry['total_wins']) ?></td>
                                <td><?= number_format($entry['win_rate'], 1) ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" style="display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 12px; font-weight: 600;">
                🏠 Về Trang Chủ
            </a>
        </div>
    </div>
    
    <script>
        function claimReward() {
            $.ajax({
                url: 'api_weekly_leaderboard.php',
                method: 'POST',
                data: {
                    action: 'claim_reward'
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

