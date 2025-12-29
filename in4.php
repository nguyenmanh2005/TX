<?php
session_start();

// Kiểm tra đăng nhập: nếu chưa đăng nhập thì chuyển về trang đăng nhập
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

// Load theme & helper tiến trình
require_once 'load_theme.php';
require_once 'user_progress_helper.php';

// Lấy thông tin người dùng hiện tại từ bảng users
$userId = $_SESSION['Iduser'];
$sql = "SELECT u.Iduser, u.Name, u.Email, u.Pass, u.Money, u.reset_token, u.token_expiry, u.Role, u.ImageURL, u.chat_frame_id, 
        u.active_title_id, u.avatar_frame_id, a.icon as title_icon, a.name as title_name,
        af.ImageURL AS avatar_frame_image
        FROM users u
        LEFT JOIN achievements a ON u.active_title_id = a.id
        LEFT JOIN avatar_frames af ON u.avatar_frame_id = af.id
        WHERE u.Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows === 1) {
    $user = $result->fetch_assoc();
} else {
    die("Không tìm thấy thông tin người dùng!");
}
// Lấy tiến trình level / streak
$progress = up_get_progress($conn, (int)$userId);

$stmt->close();

// Xử lý vai trò người dùng
switch ($user['Role']) {
    case 0:
        $roleName = "Dân Thường";
        break;
    case 1:
        $roleName = "Nghiện";
        break;
    case 2:
        $roleName = "Siêu Cấp Nghiện";
        break;
    case 3:
        $roleName = "Nhà Cái";
        break;
    default:
        $roleName = "Chưa Xác Định";
        break;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ Sơ Người Dùng</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/animations.css">

    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .profile-container {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            padding: 20px;
        }

        .profile-box {
            background: rgba(255, 255, 255, 0.98);
            padding: 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            max-width: 700px;
            width: 100%;
            text-align: center;
            border: 2px solid rgba(255, 255, 255, 0.5);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease;
            animation: fadeInUp 0.6s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .profile-box:hover {
            transform: translateY(-5px) scale(1.01);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
        }

        .profile-box img {
            border-radius: 50%;
            width: 180px;
            height: 180px;
            object-fit: cover;
            margin-bottom: 25px;
            border: 5px solid var(--secondary-color);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: avatarPulse 2s ease infinite;
        }
        
        @keyframes avatarPulse {
            0%, 100% {
                box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
            }
            50% {
                box-shadow: 0 8px 30px rgba(52, 152, 219, 0.6);
            }
        }

        .profile-box:hover img {
            transform: scale(1.05) rotate(5deg);
            box-shadow: 0 8px 30px rgba(52, 152, 219, 0.6);
        }

        .profile-box h2 {
            margin: 0 0 25px 0;
            color: var(--primary-color);
            font-size: 32px;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            animation: fadeInDown 0.8s ease;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .profile-box p {
            font-size: 18px;
            margin: 15px 0;
            color: var(--text-dark);
            line-height: 1.8;
        }

        .profile-box p strong {
            color: var(--primary-color);
            font-weight: 700;
        }

        .profile-box a {
            display: inline-block;
            margin: 12px 10px;
            padding: 14px 28px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: #fff;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            position: relative;
            overflow: hidden;
        }
        
        .profile-box a::before {
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
        
        .profile-box a:hover::before {
            width: 300px;
            height: 300px;
        }

        .profile-box a:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.6);
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .chat-frame-container {
            margin-top: 25px;
            padding: 20px;
            background: rgba(240, 248, 255, 0.7);
            border-radius: var(--border-radius);
            border: 2px solid rgba(52, 152, 219, 0.3);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .chat-frame-container:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }

        .chat-frame-container img {
            max-width: 100%;
            max-height: 250px;
            border-radius: var(--border-radius);
            display: block;
            margin: 15px auto 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }
        
        .chat-frame-container:hover img {
            transform: scale(1.05);
        }
        
        .profile-info-item {
            padding: 15px;
            margin: 12px 0;
            background: rgba(240, 248, 255, 0.7);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--secondary-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: itemSlideIn 0.6s ease;
        }
        
        @keyframes itemSlideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .profile-info-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .profile-info-item:nth-child(1) {
            animation-delay: 0.1s;
        }
        
        .profile-info-item:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .profile-info-item:nth-child(3) {
            animation-delay: 0.3s;
        }
        
        .header a {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Hồ Sơ Người Dùng</h1>
        <a href="index.php" style="color: white;">Trang Chủ</a>
    </div>

    <div class="profile-container">
        <div class="profile-box">
            <div style="position: relative; display: inline-block; width: 180px; height: 180px; margin: 0 auto 25px;">
                <?php if (!empty($user['avatar_frame_image'])): ?>
                    <div style="position: absolute; top: -10px; left: -10px; width: calc(100% + 20px); height: calc(100% + 20px); z-index: 1; pointer-events: none !important; border-radius: 50%;">
                        <img src="<?= htmlspecialchars($user['avatar_frame_image'], ENT_QUOTES, 'UTF-8') ?>" 
                             alt="Frame" 
                             style="width: 100%; height: 100%; object-fit: contain; border-radius: 50%; pointer-events: none !important;"
                             onerror="this.style.display='none'">
                    </div>
                <?php endif; ?>
                <img src="<?= !empty($user['ImageURL']) ? htmlspecialchars($user['ImageURL'], ENT_QUOTES, 'UTF-8') : 'images.ico' ?>" 
                     alt="Ảnh đại diện" 
                     style="position: relative; z-index: 2; width: 100%; height: 100%; border-radius: 50%; object-fit: cover; pointer-events: auto;"
                     onerror="this.src='images.ico'">
            </div>
            <h2>
                <?php if (!empty($user['title_icon'])): ?>
                    <span style="font-size: 24px; margin-right: 8px;" title="<?= htmlspecialchars($user['title_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($user['title_icon'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php endif; ?>
                👤 <?= htmlspecialchars($user['Name'], ENT_QUOTES, 'UTF-8') ?>
            </h2>
            
            <div class="profile-info-item">
                <p><strong>📧 Email: </strong><?= htmlspecialchars($user['Email'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <?php if (!empty($progress)): ?>
                <div class="profile-info-item" style="background: rgba(232, 244, 248, 0.6); border-left-color: var(--secondary-color);">
                    <p>
                        <strong>🔥 Level:</strong> <?= (int)$progress['level'] ?>
                        &nbsp;•&nbsp;
                        <strong>XP:</strong> <?= (int)$progress['xp'] ?>
                        &nbsp;•&nbsp;
                        <strong>Streak đăng nhập:</strong> <?= (int)$progress['login_streak'] ?> ngày
                        (tốt nhất: <?= (int)$progress['best_login_streak'] ?>)
                    </p>
                </div>
            <?php endif; ?>
            
            <div class="profile-info-item" style="background: rgba(232, 245, 233, 0.5); border-left-color: var(--success-color);">
                <p><strong>💰 Số Dư: </strong><span style="color: var(--success-color); font-weight: 700; font-size: 20px;"><?= number_format($user['Money'], 0, ',', '.') ?> VNĐ</span></p>
            </div>
            
            <div class="profile-info-item" style="background: rgba(255, 243, 224, 0.5); border-left-color: var(--warning-color);">
                <p><strong>🎭 Vai trò: </strong><?= htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <?php if (!empty($user['chat_frame_id'])): ?>
                <div class="chat-frame-container">
                    <p><strong>🎨 Khung Chat</strong></p>
                    <img src="<?= htmlspecialchars($user['chat_frame_id'], ENT_QUOTES, 'UTF-8') ?>" alt="Khung chat" onerror="this.style.display='none'">
                </div>
            <?php endif; ?>

            <div style="margin-top: 30px;">
                <a href="editProfile.php">✏️ Chỉnh sửa hồ sơ</a>
                <a href="index.php">🏠 Trang Chủ</a>
            </div>
        </div>
    </div>

    <script>
        // Đảm bảo cursor luôn hoạt động
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
            
            // Set cursor cho tất cả buttons và links
            const interactiveElements = document.querySelectorAll('button, a, label, select');
            interactiveElements.forEach(el => {
                el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                // Đảm bảo cursor không bị mất khi hover
                el.addEventListener('mouseenter', function() {
                    this.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                });
                el.addEventListener('mouseleave', function() {
                    this.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                });
            });
        });
    </script>
</body>
</html>
