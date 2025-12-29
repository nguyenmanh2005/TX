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

// Kiểm tra và tạo bảng achievement_notifications nếu chưa có
$checkTable = $conn->query("SHOW TABLES LIKE 'achievement_notifications'");
if (!$checkTable || $checkTable->num_rows == 0) {
    $createTable = "CREATE TABLE IF NOT EXISTS achievement_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        achievement_id INT NOT NULL,
        notification_type VARCHAR(50) DEFAULT 'achievement_unlocked',
        message TEXT,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(Iduser) ON DELETE CASCADE,
        FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
        INDEX idx_user_read (user_id, is_read),
        INDEX idx_created (created_at)
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

// Lấy danh sách notifications
$sql = "SELECT an.*, a.name as achievement_name, a.icon as achievement_icon, 
               a.rarity as achievement_rarity, a.reward_money, a.reward_xp
        FROM achievement_notifications an
        JOIN achievements a ON an.achievement_id = a.id
        WHERE an.user_id = ?
        ORDER BY an.created_at DESC
        LIMIT 50";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

// Đếm số notifications chưa đọc
$sql = "SELECT COUNT(*) as count FROM achievement_notifications WHERE user_id = ? AND is_read = 0";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$unreadCount = $result->fetch_assoc()['count'] ?? 0;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Achievement Notifications - Thông Báo Danh Hiệu</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header-notifications {
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
        
        .header-notifications::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.05) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }
        
        .header-notifications h1 {
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
        
        .unread-badge {
            display: inline-block;
            padding: 8px 16px;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            border-radius: 20px;
            font-weight: 700;
            margin-left: 15px;
            animation: pulse 2s ease-in-out infinite;
        }
        
        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .notification-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border: 3px solid #e0e0e0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease backwards;
        }
        
        .notification-card::before {
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
        
        .notification-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .notification-card.unread {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(255, 255, 255, 0.98) 100%);
        }
        
        .notification-card.legendary {
            border-color: #ffd700;
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.3);
        }
        
        .notification-card.epic {
            border-color: #9b59b6;
            box-shadow: 0 8px 25px rgba(155, 89, 182, 0.3);
        }
        
        .notification-card.rare {
            border-color: #3498db;
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
        }
        
        .notification-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
        }
        
        .notification-card:hover::before {
            opacity: 1;
        }
        
        .notification-card:hover::after {
            left: 100%;
        }
        
        .notification-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .notification-icon {
            font-size: 48px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
        }
        
        .notification-title {
            flex: 1;
            font-size: 20px;
            font-weight: 700;
            color: #333;
        }
        
        .notification-time {
            font-size: 14px;
            color: #999;
        }
        
        .notification-message {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .notification-reward {
            display: flex;
            gap: 20px;
            padding: 15px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 12px;
            flex-wrap: wrap;
        }
        
        .reward-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #667eea;
        }
        
        .mark-read-btn {
            padding: 8px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .mark-read-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .mark-all-read-btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .mark-all-read-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.5);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .empty-state-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .empty-state-text {
            font-size: 18px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-notifications">
            <h1>🏆 Achievement Notifications</h1>
            <?php if ($unreadCount > 0): ?>
                <div class="unread-badge"><?= $unreadCount ?> Chưa Đọc</div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($notifications)): ?>
            <?php if ($unreadCount > 0): ?>
                <div style="text-align: right; margin-bottom: 20px;">
                    <button class="mark-all-read-btn" onclick="markAllAsRead()">
                        ✅ Đánh Dấu Tất Cả Đã Đọc
                    </button>
                </div>
            <?php endif; ?>
            
            <div class="notifications-list">
                <?php foreach ($notifications as $notif): 
                    $isUnread = $notif['is_read'] == 0;
                    $rarity = $notif['achievement_rarity'] ?? 'common';
                    $timeAgo = '';
                    $created = new DateTime($notif['created_at']);
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
                    <div class="notification-card <?= $isUnread ? 'unread' : '' ?> <?= $rarity ?>">
                        <div class="notification-header">
                            <div class="notification-icon">
                                <?= htmlspecialchars($notif['achievement_icon'] ?? '🏆') ?>
                            </div>
                            <div class="notification-title">
                                <?= htmlspecialchars($notif['achievement_name']) ?>
                            </div>
                            <div class="notification-time">
                                <?= $timeAgo ?>
                            </div>
                        </div>
                        
                        <div class="notification-message">
                            <?= htmlspecialchars($notif['message'] ?? 'Bạn đã đạt được danh hiệu mới!') ?>
                        </div>
                        
                        <?php if ($notif['reward_money'] > 0 || $notif['reward_xp'] > 0): ?>
                            <div class="notification-reward">
                                <?php if ($notif['reward_money'] > 0): ?>
                                    <div class="reward-item">
                                        <i class="fas fa-coins"></i>
                                        <span>+<?= number_format($notif['reward_money']) ?> VNĐ</span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($notif['reward_xp'] > 0): ?>
                                    <div class="reward-item">
                                        <i class="fas fa-star"></i>
                                        <span>+<?= number_format($notif['reward_xp']) ?> XP</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($isUnread): ?>
                            <button class="mark-read-btn" onclick="markAsRead(<?= $notif['id'] ?>)">
                                Đánh Dấu Đã Đọc
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <div class="empty-state-text">
                    Chưa có thông báo nào. Hãy đạt được danh hiệu mới để nhận thông báo!
                </div>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" style="display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 12px; font-weight: 600;">
                🏠 Về Trang Chủ
            </a>
            <a href="achievements.php" style="display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; text-decoration: none; border-radius: 12px; font-weight: 600; margin-left: 15px;">
                🏆 Xem Tất Cả Danh Hiệu
            </a>
        </div>
    </div>
    
    <script>
        function markAsRead(notificationId) {
            $.ajax({
                url: 'api_achievement_notifications.php',
                method: 'POST',
                data: {
                    action: 'mark_read',
                    notification_id: notificationId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        location.reload();
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
        
        function markAllAsRead() {
            $.ajax({
                url: 'api_achievement_notifications.php',
                method: 'POST',
                data: {
                    action: 'mark_all_read'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Thành Công!',
                            text: response.message,
                            timer: 1500,
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

