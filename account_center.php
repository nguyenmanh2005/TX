<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

// Load theme
require_once 'load_theme.php';
require_once 'user_progress_helper.php';

// Đảm bảo $bgGradientCSS có giá trị
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

$userId = $_SESSION['Iduser'];

// Lấy thông tin người dùng
$sql = "SELECT u.Iduser, u.Name, u.Email, u.Money, u.ImageURL, u.active_title_id,
        a.icon as title_icon, a.name as title_name
        FROM users u
        LEFT JOIN achievements a ON u.active_title_id = a.id
        WHERE u.Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Lấy tiến trình
$progress = up_get_progress($conn, (int) $userId);

// Tính xếp hạng
$rankSql = "SELECT COUNT(*) + 1 as rank FROM users WHERE Money > ?";
$rankStmt = $conn->prepare($rankSql);
$rankStmt->bind_param("d", $user['Money']);
$rankStmt->execute();
$rankResult = $rankStmt->get_result();
$rankData = $rankResult->fetch_assoc();
$userRank = $rankData['rank'] ?? 999;
$rankStmt->close();

// Đếm số achievements
$achievementsCount = 0;
$checkAchievementsTable = $conn->query("SHOW TABLES LIKE 'user_achievements'");
if ($checkAchievementsTable && $checkAchievementsTable->num_rows > 0) {
    $sql = "SELECT COUNT(*) as total FROM user_achievements WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $achievementsCount = $result->fetch_assoc()['total'] ?? 0;
    $stmt->close();
}

// Đếm số quests đã hoàn thành
$questsCompleted = 0;
$checkQuestsTable = $conn->query("SHOW TABLES LIKE 'user_quests'");
if ($checkQuestsTable && $checkQuestsTable->num_rows > 0) {
    $sql = "SELECT COUNT(*) as total FROM user_quests WHERE user_id = ? AND is_completed = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $questsCompleted = $result->fetch_assoc()['total'] ?? 0;
    $stmt->close();
}

// Đếm số bạn bè
$friendsCount = 0;
$checkFriendsTable = $conn->query("SHOW TABLES LIKE 'friends'");
if ($checkFriendsTable && $checkFriendsTable->num_rows > 0) {
    $sql = "SELECT COUNT(*) as total FROM friends WHERE user_id = ? AND status = 'accepted'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $friendsCount = $result->fetch_assoc()['total'] ?? 0;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trung Tâm Tài Khoản</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/animations.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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

        .account-center {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header-center {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 24px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
        }

        .header-center h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 42px;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 20px;
        }

        .user-profile-card {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 30px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .user-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #667eea;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .user-info {
            text-align: left;
        }

        .user-name {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }

        .user-stats {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .stat-item {
            padding: 10px 20px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 12px;
            border: 2px solid rgba(102, 126, 234, 0.2);
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
            margin-top: 5px;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .menu-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            text-align: center;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .menu-card::before {
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

        .menu-card:hover::before {
            opacity: 1;
        }

        .menu-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.25);
        }

        .menu-icon {
            font-size: 64px;
            margin-bottom: 15px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.1));
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .menu-title {
            font-size: 22px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }

        .menu-description {
            font-size: 14px;
            color: #666;
            line-height: 1.6;
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
        }

        .back-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        }
    </style>
</head>

<body>
    <div class="account-center">
        <div class="header-center">
            <h1>🎯 Trung Tâm Tài Khoản</h1>

            <div class="user-profile-card">
                <img src="<?= htmlspecialchars($user['ImageURL'] ?? 'images.ico') ?>"
                    alt="<?= htmlspecialchars($user['Name']) ?>" class="user-avatar" onerror="this.src='images.ico'">
                <div class="user-info">
                    <div class="user-name">
                        <?= $user['title_icon'] ? htmlspecialchars($user['title_icon']) . ' ' : '' ?>
                        <?= htmlspecialchars($user['Name']) ?>
                    </div>
                    <div style="color: #666; margin-bottom: 10px;">📧 <?= htmlspecialchars($user['Email']) ?></div>
                    <div class="user-stats">
                        <div class="stat-item">
                            <div class="stat-label">💰 Số Gtlm</div>
                            <div class="stat-value"><?= number_format($user['Money'], 0, ',', '.') ?> gtlm</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">🏆 Xếp Hạng</div>
                            <div class="stat-value">#<?= number_format($userRank) ?></div>
                        </div>
                        <?php if (!empty($progress)): ?>
                            <div class="stat-item">
                                <div class="stat-label">🔥 Level</div>
                                <div class="stat-value"><?= (int) $progress['level'] ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">⭐ XP</div>
                                <div class="stat-value"><?= number_format($progress['xp'], 0, ',', '.') ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="menu-grid">
            <a href="in4.php" class="menu-card">
                <div class="menu-icon">👤</div>
                <div class="menu-title">Hồ Sơ</div>
                <div class="menu-description">Xem và chỉnh sửa thông tin cá nhân</div>
            </a>

            <a href="editProfile.php" class="menu-card">
                <div class="menu-icon">✏️</div>
                <div class="menu-title">Chỉnh Sửa Hồ Sơ</div>
                <div class="menu-description">Cập nhật thông tin, đổi mật khẩu, avatar</div>
            </a>

            <a href="statistics.php" class="menu-card">
                <div class="menu-icon">📊</div>
                <div class="menu-title">Thống Kê</div>
                <div class="menu-description">Xem thống kê chi tiết về game và thành tích</div>
            </a>

            <a href="achievements.php" class="menu-card">
                <div class="menu-icon">🏅</div>
                <div class="menu-title">Danh Hiệu</div>
                <div class="menu-description">Xem danh sách danh hiệu đã đạt được (<?= $achievementsCount ?>)</div>
            </a>

            <a href="quests.php" class="menu-card">
                <div class="menu-icon">🎯</div>
                <div class="menu-title">Nhiệm Vụ</div>
                <div class="menu-description">Hoàn thành nhiệm vụ để nhận phần thưởng (<?= $questsCompleted ?> đã hoàn
                    thành)</div>
            </a>

            <a href="friends.php" class="menu-card">
                <div class="menu-icon">👥</div>
                <div class="menu-title">Bạn Bè</div>
                <div class="menu-description">Quản lý bạn bè và nhắn tin riêng (<?= $friendsCount ?> bạn)</div>
            </a>

            <a href="inventory.php" class="menu-card">
                <div class="menu-icon">📦</div>
                <div class="menu-title">Kho Đồ</div>
                <div class="menu-description">Xem và quản lý items đã mua (themes, cursors, frames)</div>
            </a>

            <a href="shop.php" class="menu-card">
                <div class="menu-icon">🛒</div>
                <div class="menu-title">Cửa Hàng</div>
                <div class="menu-description">Mua themes, cursors, frames và items khác</div>
            </a>

            <a href="gift.php" class="menu-card">
                <div class="menu-icon">🎁</div>
                <div class="menu-title">Tặng Quà</div>
                <div class="menu-description">Tặng gtlm hoặc items cho bạn bè</div>
            </a>

            <a href="lucky_wheel.php" class="menu-card">
                <div class="menu-icon">🎡</div>
                <div class="menu-title">Lucky Wheel</div>
                <div class="menu-description">Quay vòng quay may mắn hàng ngày</div>
            </a>

            <a href="leaderboard.php" class="menu-card">
                <div class="menu-icon">🏆</div>
                <div class="menu-title">Bảng Xếp Hạng</div>
                <div class="menu-description">Xem top người chơi giàu nhất server</div>
            </a>

            <a href="diemdanh.php" class="menu-card">
                <div class="menu-icon">📅</div>
                <div class="menu-title">Điểm Danh</div>
                <div class="menu-description">Điểm danh hàng ngày để nhận phần thưởng</div>
            </a>

            <a href="monthly_pass.php" class="menu-card" style="border: 1px solid #ffd700; background: linear-gradient(135deg, rgba(255,255,255,0.98) 0%, rgba(255,215,0,0.05) 100%);">
                <div class="menu-icon">✨</div>
                <div class="menu-title" style="color: #b8860b;">Monthly Pass</div>
                <div class="menu-description">Gói thuê bao tháng với nhiều đặc quyền VIP</div>
            </a>

            <a href="crafting.php" class="menu-card" style="border: 1px solid #ff4500; background: linear-gradient(135deg, rgba(255,255,255,0.98) 0%, rgba(255,69,0,0.05) 100%);">
                <div class="menu-icon">🔨</div>
                <div class="menu-title" style="color: #ff4500;">Workshop</div>
                <div class="menu-description">Chế tác và nâng cấp vật phẩm hiếm</div>
            </a>

            <a href="tournaments.php" class="menu-card" style="border: 1px solid #ffd700; background: linear-gradient(135deg, rgba(255,255,255,0.98) 0%, rgba(255,215,0,0.05) 100%);">
                <div class="menu-icon">🏆</div>
                <div class="menu-title" style="color: #daa520;">Tournament</div>
                <div class="menu-description">Giải đấu có buy-in và Prize Pool cực lớn</div>
            </a>
        </div>

        <div style="text-align: center;">
            <a href="index.php" class="back-link">🏠 Về Trang Chủ</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";

            const interactiveElements = document.querySelectorAll('button, a, input, label, select');
            interactiveElements.forEach(el => {
                el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            });
        });
    </script>
</body>

</html>