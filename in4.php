<?php
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require_once 'db_connect.php';
require_once 'load_theme.php';
require_once 'user_progress_helper.php';

$currentUserId = (int)$_SESSION['Iduser'];
$targetUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : $currentUserId);
if ($targetUserId <= 0) $targetUserId = $currentUserId;
$isOwnProfile = ($targetUserId === $currentUserId);

$activeTab = isset($_GET['tab']) ? trim($_GET['tab']) : 'overview';
if ($activeTab !== 'edit') $activeTab = 'overview';
$message = '';
$messageType = '';

// Kiểm tra quyền của người đang xem (chỉ quản trị viên mới thấy ID người dùng)
$viewerRole = (int)($_SESSION['Role'] ?? 0);
if (!isset($_SESSION['Role'])) {
    $vStmt = $conn->prepare("SELECT Role FROM users WHERE Iduser = ?");
    if ($vStmt) {
        $vStmt->bind_param("i", $currentUserId);
        $vStmt->execute();
        $vRes = $vStmt->get_result()->fetch_assoc();
        $viewerRole = (int)($vRes['Role'] ?? 0);
        $_SESSION['Role'] = $viewerRole;
        $vStmt->close();
    }
}
$isViewerAdmin = ($viewerRole >= 1); // 1: Admin, 2: Super Admin, 3: Owner

// Kiểm tra bảng user_profiles tồn tại
$checkProfileTable = $conn->query("SHOW TABLES LIKE 'user_profiles'");
$hasProfileTable = ($checkProfileTable && $checkProfileTable->num_rows > 0);

// --- XỬ LÝ CẬP NHẬT HỒ SƠ (CHỈ CHO PHÉP KHI LÀ CHÍNH MÌNH) ---
if ($isOwnProfile && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile_action'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $bio = trim($_POST['bio'] ?? '');
    $socialFacebook = trim($_POST['social_facebook'] ?? '');
    $socialDiscord = trim($_POST['social_discord'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $favoriteGame = trim($_POST['favorite_game'] ?? '');

    if (empty($name)) {
        $message = 'Tên hiển thị không được để trống!';
        $messageType = 'error';
    } else {
        // 1. Cập nhật bảng users
        $updateSql = "UPDATE users SET Name = ?, Email = ? WHERE Iduser = ?";
        $stmt = $conn->prepare($updateSql);
        if ($stmt) {
            $stmt->bind_param("ssi", $name, $email, $currentUserId);
            $stmt->execute();
            $stmt->close();
        }

        // 2. Cập nhật mật khẩu nếu có nhập
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $pwdStmt = $conn->prepare("UPDATE users SET Pass = ? WHERE Iduser = ?");
            if ($pwdStmt) {
                $pwdStmt->bind_param("si", $hashedPassword, $currentUserId);
                $pwdStmt->execute();
                $pwdStmt->close();
            }
        }

        // 3. Cập nhật ảnh đại diện nếu có upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowedExts)) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
                if (PHP_VERSION_ID < 80500) {
                    finfo_close($finfo);
                }

                if (in_array($mime, $allowedMimes)) {
                    if (!is_dir('uploads')) {
                        @mkdir('uploads', 0777, true);
                    }
                    $newFilename = uniqid('avatar_', true) . '.' . $ext;
                    $imagePath = 'uploads/' . $newFilename;

                    if (move_uploaded_file($_FILES['image']['tmp_name'], $imagePath)) {
                        $imgStmt = $conn->prepare("UPDATE users SET ImageURL = ? WHERE Iduser = ?");
                        if ($imgStmt) {
                            $imgStmt->bind_param("si", $imagePath, $currentUserId);
                            $imgStmt->execute();
                            $imgStmt->close();
                        }
                    }
                }
            }
        }

        // 4. Cập nhật bảng user_profiles (nếu có)
        if ($hasProfileTable) {
            $chk = $conn->prepare("SELECT user_id FROM user_profiles WHERE user_id = ?");
            if ($chk) {
                $chk->bind_param("i", $currentUserId);
                $chk->execute();
                $exists = $chk->get_result()->num_rows > 0;
                $chk->close();

                if ($exists) {
                    $uprof = $conn->prepare("UPDATE user_profiles SET bio = ?, website = ?, social_facebook = ?, social_discord = ? WHERE user_id = ?");
                    if ($uprof) {
                        $uprof->bind_param("ssssi", $bio, $website, $socialFacebook, $socialDiscord, $currentUserId);
                        $uprof->execute();
                        $uprof->close();
                    }
                } else {
                    $uprof = $conn->prepare("INSERT INTO user_profiles (user_id, bio, website, social_facebook, social_discord) VALUES (?, ?, ?, ?, ?)");
                    if ($uprof) {
                        $uprof->bind_param("issss", $currentUserId, $bio, $website, $socialFacebook, $socialDiscord);
                        $uprof->execute();
                        $uprof->close();
                    }
                }
            }
        }

        $message = 'Cập nhật thông tin hồ sơ thành công!';
        $messageType = 'success';
        $activeTab = 'overview';
    }
}

// --- LẤY THÔNG TIN NGƯỜI DÙNG CẦN XEM ---
$sqlUser = "SELECT u.Iduser, u.Name, u.Email, u.Money, u.Role, u.ImageURL, u.chat_frame_id, 
            u.active_title_id, u.avatar_frame_id, u.vip_expiry,
            a.icon as title_icon, a.name as title_name,
            af.ImageURL AS avatar_frame_image
            FROM users u
            LEFT JOIN achievements a ON u.active_title_id = a.id
            LEFT JOIN avatar_frames af ON u.avatar_frame_id = af.id
            WHERE u.Iduser = ?";
$stmtUser = $conn->prepare($sqlUser);
$stmtUser->bind_param("i", $targetUserId);
$stmtUser->execute();
$resUser = $stmtUser->get_result();

if (!$resUser || $resUser->num_rows === 0) {
    die("<div style='text-align:center; padding:50px; color:#fff; font-family:sans-serif;'><h2>⚠️ Không tìm thấy người dùng!</h2><p><a href='in4.php' style='color:#3498db;'>Quay về hồ sơ của bạn</a></p></div>");
}
$user = $resUser->fetch_assoc();
$stmtUser->close();

// Lấy hồ sơ mở rộng (Bio, Social...)
$profileData = [];
if ($hasProfileTable) {
    $profStmt = $conn->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
    if ($profStmt) {
        $profStmt->bind_param("i", $targetUserId);
        $profStmt->execute();
        $profileData = $profStmt->get_result()->fetch_assoc() ?: [];
        $profStmt->close();
    }
}

// Lấy tiến trình Level / Streak
$progress = up_get_progress($conn, $targetUserId);

// Vai trò hiển thị
switch ((int)$user['Role']) {
    case 0: $roleName = "Dân Thường"; $roleBadgeClass = "role-user"; break;
    case 1: $roleName = "Admin / Quản Trị Viên"; $roleBadgeClass = "role-admin"; break;
    case 2: $roleName = "Super Admin"; $roleBadgeClass = "role-superadmin"; break;
    case 3: $roleName = "Nhà Phát Triển / Owner"; $roleBadgeClass = "role-dev"; break;
    default: $roleName = "Thành Viên"; $roleBadgeClass = "role-user"; break;
}

// Kiểm tra VIP
$isVip = !empty($user['vip_expiry']) && strtotime($user['vip_expiry']) > time();
$vipExpiryText = $isVip ? date('d/m/Y H:i', strtotime($user['vip_expiry'])) : '';

// Thống kê game nổi bật (nếu có bảng game_history)
$gameHighlights = [];
$checkGameHistory = $conn->query("SHOW TABLES LIKE 'game_history'");
if ($checkGameHistory && $checkGameHistory->num_rows > 0) {
    $gStmt = $conn->prepare("SELECT game_name, COUNT(*) AS plays, SUM(is_win) AS wins, SUM(win_amount) AS total_win, SUM(bet_amount) AS total_bet FROM game_history WHERE user_id = ? GROUP BY game_name ORDER BY plays DESC LIMIT 4");
    if ($gStmt) {
        $gStmt->bind_param("i", $targetUserId);
        $gStmt->execute();
        $gRes = $gStmt->get_result();
        while ($r = $gRes->fetch_assoc()) {
            $gameHighlights[] = $r;
        }
        $gStmt->close();
    }
}

if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #090d16 100%)';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isOwnProfile ? 'Hồ Sơ Của Tôi' : 'Hồ Sơ: ' . htmlspecialchars($user['Name']) ?> | GTLM Gaming</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #6366f1;
            --primary-light: #818cf8;
            --secondary: #a855f7;
            --gold: #f59e0b;
            --gold-glow: rgba(245, 158, 11, 0.4);
            --success: #10b981;
            --danger: #ef4444;
            --dark-card: rgba(15, 23, 42, 0.85);
            --dark-card-inner: rgba(30, 41, 59, 0.7);
            --border-glow: rgba(99, 102, 241, 0.3);
            --border-radius: 20px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            font-family: 'Outfit', sans-serif;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            color: #f8fafc;
            padding-bottom: 50px;
            overflow-x: hidden;
        }

        button, a, label, select, input[type="submit"], input[type="button"] {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        /* Header Navbar */
        .top-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 40px;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-logo {
            font-size: 1.4rem;
            font-weight: 800;
            background: linear-gradient(135deg, #a855f7, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nav-btn-home {
            background: rgba(255, 255, 255, 0.06);
            color: #cbd5e1;
        }
        .nav-btn-home:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateY(-2px);
        }

        .nav-btn-action {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.35);
        }
        .nav-btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.5);
        }

        /* Container & Layout */
        .main-container {
            max-width: 1060px;
            margin: 30px auto 0;
            padding: 0 20px;
        }

        /* Banner & Hero Header */
        .profile-hero {
            background: var(--dark-card);
            backdrop-filter: blur(25px);
            border: 1px solid var(--border-glow);
            border-radius: 28px;
            padding: 40px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            margin-bottom: 25px;
            animation: fadeInDown 0.6s ease-out;
        }

        .profile-hero::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 350px;
            height: 350px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.2) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-body {
            display: flex;
            align-items: center;
            gap: 35px;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .hero-body {
                flex-direction: column;
                text-align: center;
            }
        }

        /* Avatar with Frames */
        .avatar-wrap {
            position: relative;
            width: 150px;
            height: 150px;
            flex-shrink: 0;
            margin: 0 auto;
        }

        .avatar-img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            transition: transform 0.4s ease;
        }

        .avatar-wrap:hover .avatar-img {
            transform: scale(1.05) rotate(3deg);
        }

        .frame-overlay {
            position: absolute;
            top: -12%;
            left: -12%;
            width: 124%;
            height: 124%;
            pointer-events: none;
            z-index: 5;
            object-fit: contain;
        }

        .hero-details {
            flex: 1;
            min-width: 280px;
        }

        .hero-title-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid var(--gold);
            color: var(--gold);
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .hero-name {
            font-size: 2.2rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .vip-crown {
            color: var(--gold);
            filter: drop-shadow(0 0 12px var(--gold-glow));
            animation: pulseCrown 2s infinite ease-in-out;
            font-size: 1.8rem;
        }
        @keyframes pulseCrown {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.15) rotate(5deg); }
        }

        .hero-meta {
            display: flex;
            gap: 20px;
            font-size: 0.95rem;
            color: #94a3b8;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .hero-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .role-badge {
            padding: 3px 12px;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .role-user { background: rgba(59, 130, 246, 0.15); border: 1px solid #3b82f6; color: #60a5fa; }
        .role-admin { background: rgba(168, 85, 247, 0.2); border: 1px solid #a855f7; color: #c084fc; }
        .role-superadmin { background: rgba(245, 158, 11, 0.2); border: 1px solid #f59e0b; color: #fbbf24; }
        .role-dev { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #f87171; }

        /* Navigation Tabs */
        .tab-menu {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            background: rgba(15, 23, 42, 0.7);
            padding: 8px;
            border-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(15px);
        }

        .tab-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 14px;
            border: none;
            background: transparent;
            color: #94a3b8;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .tab-btn:hover {
            color: white;
            background: rgba(255, 255, 255, 0.05);
        }

        .tab-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        /* Tab Content Panes */
        .tab-pane {
            display: none;
            animation: fadeIn 0.4s ease-out;
        }

        .tab-pane.active {
            display: block;
        }

        /* Grid Cards */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
        }

        .card-box {
            background: var(--dark-card);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
            transition: transform 0.3s ease, border-color 0.3s ease;
        }

        .card-box:hover {
            border-color: rgba(99, 102, 241, 0.4);
            transform: translateY(-3px);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .card-header h3 {
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #f1f5f9;
        }

        /* Balance Highlight */
        .balance-card {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(15, 23, 42, 0.9));
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .balance-val {
            font-size: 2.2rem;
            font-weight: 900;
            color: #34d399;
            text-shadow: 0 0 20px rgba(52, 211, 153, 0.3);
            margin: 10px 0;
            font-family: 'Poppins', sans-serif;
        }

        /* Level & XP bar */
        .xp-bar-wrap {
            background: rgba(255, 255, 255, 0.08);
            height: 12px;
            border-radius: 50px;
            overflow: hidden;
            margin: 10px 0 6px;
        }

        .xp-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #6366f1, #38bdf8);
            border-radius: 50px;
            transition: width 0.8s ease;
        }

        /* Info rows */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.95rem;
        }
        .info-row:last-child { border-bottom: none; }
        .info-lbl { color: #94a3b8; display: flex; align-items: center; gap: 8px; }
        .info-val { font-weight: 600; color: #f8fafc; }

        /* Social Icons */
        .social-row {
            display: flex;
            gap: 12px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .social-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #cbd5e1;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        .social-pill:hover {
            background: rgba(99, 102, 241, 0.2);
            border-color: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        /* Form Controls */
        .form-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
        }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
        }

        .form-avatar-box {
            text-align: center;
            background: var(--dark-card-inner);
            padding: 30px 20px;
            border-radius: 20px;
            border: 1px dashed rgba(255, 255, 255, 0.2);
        }

        .preview-avatar {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin: 0 auto 15px;
            display: block;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
            font-weight: 700;
            color: #cbd5e1;
        }

        .form-control {
            width: 100%;
            padding: 13px 18px;
            background: rgba(15, 23, 42, 0.7);
            border: 1.5px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            color: white;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.3);
            background: rgba(15, 23, 42, 0.9);
        }

        .btn-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 16px 28px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 14px;
            color: white;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.4);
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(99, 102, 241, 0.6);
        }

        /* Shortcut utilities grid */
        .shortcut-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 18px;
        }

        .shortcut-card {
            background: var(--dark-card);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 24px 20px;
            text-align: center;
            text-decoration: none;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .shortcut-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 15px 35px rgba(99, 102, 241, 0.25);
            background: rgba(30, 41, 59, 0.85);
        }

        .shortcut-icon {
            font-size: 2.2rem;
            margin-bottom: 4px;
        }

        .shortcut-title {
            font-weight: 700;
            font-size: 1rem;
        }

        .shortcut-desc {
            font-size: 0.8rem;
            color: #94a3b8;
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-25px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <!-- Three.js Canvas Background -->
    <canvas id="threejs-background"></canvas>
    <script>
        (function () {
            window.themeConfig = {
                particleCount: <?= $particleCount ?? 700 ?>,
                particleSize: <?= $particleSize ?? 0.05 ?>,
                particleColor: '<?= $particleColor ?? "#ffffff" ?>',
                particleOpacity: <?= $particleOpacity ?? 0.5 ?>,
                shapeCount: <?= $shapeCount ?? 10 ?>,
                shapeColors: <?= json_encode($shapeColors ?? ["#6366f1", "#a855f7", "#3b82f6"]) ?>,
                shapeOpacity: <?= $shapeOpacity ?? 0.2 ?>,
                bgGradient: <?= json_encode($bgGradient ?? ["#0f172a", "#1e1b4b", "#090d16"]) ?>
            };
            const script = document.createElement('script');
            script.src = 'threejs-background.js';
            document.head.appendChild(script);
        })();
    </script>

    <!-- Top Navigation Bar -->
    <header class="top-nav">
        <a href="index.php" class="nav-logo">
            <i class="fa-solid fa-gamepad"></i> GTLM GAMING
        </a>
        <div class="nav-actions">
            <a href="index.php" class="nav-btn nav-btn-home">
                <i class="fa-solid fa-house"></i> Sảnh Chính
            </a>
            <?php if ($isOwnProfile): ?>
                <a href="?tab=edit" class="nav-btn nav-btn-action">
                    <i class="fa-solid fa-pen-to-square"></i> Chỉnh Sửa
                </a>
            <?php else: ?>
                <a href="in4.php" class="nav-btn nav-btn-action">
                    <i class="fa-solid fa-user"></i> Hồ Sơ Của Tôi
                </a>
            <?php endif; ?>
        </div>
    </header>

    <div class="main-container">

        <!-- Banner Hồ Sơ Chính -->
        <section class="profile-hero">
            <div class="hero-body">
                <div class="avatar-wrap">
                    <?php if (!empty($user['avatar_frame_image'])): ?>
                        <img src="<?= htmlspecialchars($user['avatar_frame_image'], ENT_QUOTES, 'UTF-8') ?>" class="frame-overlay" alt="Khung Avatar">
                    <?php endif; ?>
                    <img src="<?= !empty($user['ImageURL']) ? htmlspecialchars($user['ImageURL'], ENT_QUOTES, 'UTF-8') : 'images.ico' ?>" class="avatar-img" id="hero-avatar" alt="Avatar" onerror="this.src='images.ico'">
                </div>

                <div class="hero-details">
                    <?php if (!empty($user['title_name'])): ?>
                        <div class="hero-title-badge">
                            <span><?= htmlspecialchars($user['title_icon'] ?? '✨', ENT_QUOTES, 'UTF-8') ?></span>
                            <?= htmlspecialchars($user['title_name'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <h1 class="hero-name">
                        <?= htmlspecialchars($user['Name'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($isVip): ?>
                            <i class="fa-solid fa-crown vip-crown" title="Hội Viên VIP (Hết hạn: <?= $vipExpiryText ?>)"></i>
                        <?php endif; ?>
                    </h1>

                    <div class="hero-meta">
                        <?php if ($isViewerAdmin): ?>
                            <div class="hero-meta-item">
                                <i class="fa-solid fa-id-badge"></i> ID: #<?= (int)$user['Iduser'] ?>
                            </div>
                        <?php endif; ?>
                        <div class="hero-meta-item">
                            <span class="role-badge <?= $roleBadgeClass ?>"><?= htmlspecialchars($roleName) ?></span>
                        </div>
                        <?php if ($isVip): ?>
                            <div class="hero-meta-item" style="color: var(--gold);">
                                <i class="fa-solid fa-star"></i> VIP Hết Hạn: <?= $vipExpiryText ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($profileData['bio'])): ?>
                        <p style="color: #cbd5e1; font-size: 0.95rem; line-height: 1.6; max-width: 600px; font-style: italic;">
                            "<?= htmlspecialchars($profileData['bio']) ?>"
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Navigation Tabs -->
        <nav class="tab-menu">
            <button class="tab-btn <?= $activeTab === 'overview' ? 'active' : '' ?>" onclick="switchTab('overview')">
                <i class="fa-solid fa-address-card"></i> Tổng Quan
            </button>
            <?php if ($isOwnProfile): ?>
                <button class="tab-btn <?= $activeTab === 'edit' ? 'active' : '' ?>" onclick="switchTab('edit')">
                    <i class="fa-solid fa-user-pen"></i> Chỉnh Sửa Hồ Sơ
                </button>
            <?php endif; ?>
        </nav>

        <!-- ═══════════════════════════════════════════ -->
        <!-- TAB 1: TỔNG QUAN HỒ SƠ                     -->
        <!-- ═══════════════════════════════════════════ -->
        <div id="tab-overview" class="tab-pane <?= $activeTab === 'overview' ? 'active' : '' ?>">
            <div class="dashboard-grid">

                <!-- Số dư tài khoản -->
                <div class="card-box balance-card">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-wallet" style="color: #34d399;"></i> Số Dư Ví</h3>
                        <span style="font-size: 0.8rem; color: #a7f3d0; font-weight: 700;">GTLM COIN</span>
                    </div>
                    <div class="balance-val">
                        <?= number_format($user['Money'], 0, ',', '.') ?> <span style="font-size: 1.1rem; color: #6ee7b7;">GTLM</span>
                    </div>
                    <p style="color: #94a3b8; font-size: 0.88rem;">Khả dụng cho toàn bộ mini-game và sảnh live stream.</p>
                </div>

                <!-- Cấp độ & Điểm kinh nghiệm -->
                <div class="card-box">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-chart-line" style="color: #818cf8;"></i> Tiến Trình Level</h3>
                        <span style="font-size: 0.85rem; font-weight: 800; color: #818cf8;">CẤP <?= (int)($progress['level'] ?? 1) ?></span>
                    </div>
                    <?php 
                        $curXp = (int)($progress['xp'] ?? 0);
                        $needXp = (int)($progress['level_up_xp'] ?? 1000);
                        if ($needXp <= 0) $needXp = 1000;
                        $pctXp = min(100, max(5, round(($curXp / $needXp) * 100)));
                    ?>
                    <div class="xp-bar-wrap">
                        <div class="xp-bar-fill" style="width: <?= $pctXp ?>%;"></div>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.82rem; color: #94a3b8; margin-bottom: 12px;">
                        <span>Kinh nghiệm: <?= number_format($curXp) ?> / <?= number_format($needXp) ?> XP</span>
                        <span><?= $pctXp ?>%</span>
                    </div>
                    <div class="info-row">
                        <span class="info-lbl"><i class="fa-solid fa-fire" style="color: #f97316;"></i> Streak Đăng Nhập:</span>
                        <span class="info-val"><?= (int)($progress['login_streak'] ?? 0) ?> ngày (Kỷ lục: <?= (int)($progress['best_login_streak'] ?? 0) ?>)</span>
                    </div>
                </div>

                <!-- Thông tin cá nhân -->
                <div class="card-box">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-circle-info" style="color: #38bdf8;"></i> Chi Tiết Tài Khoản</h3>
                    </div>
                    <div class="info-row">
                        <span class="info-lbl"><i class="fa-solid fa-envelope"></i> Email:</span>
                        <span class="info-val"><?= htmlspecialchars($user['Email']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-lbl"><i class="fa-solid fa-shield-halved"></i> Phân Quyền:</span>
                        <span class="info-val"><?= htmlspecialchars($roleName) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-lbl"><i class="fa-solid fa-gem"></i> Trạng Thái VIP:</span>
                        <span class="info-val" style="<?= $isVip ? 'color: var(--gold); font-weight:800;' : '' ?>">
                            <?= $isVip ? '👑 VIP Kích Hoạt' : 'Thường' ?>
                        </span>
                    </div>
                </div>

                <!-- Mạng xã hội & Liên kết -->
                <div class="card-box">
                    <div class="card-header">
                        <h3><i class="fa-solid fa-share-nodes" style="color: #c084fc;"></i> Mạng Xã Hội</h3>
                        <?php if ($isOwnProfile): ?>
                            <a href="?tab=edit" style="color: #818cf8; font-size: 0.85rem; text-decoration: none;">Cập nhật</a>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($profileData['social_facebook']) || !empty($profileData['social_discord']) || !empty($profileData['website'])): ?>
                        <div class="social-row">
                            <?php if (!empty($profileData['social_facebook'])): ?>
                                <a href="<?= htmlspecialchars($profileData['social_facebook']) ?>" target="_blank" class="social-pill">
                                    <i class="fa-brands fa-facebook" style="color: #1877f2;"></i> Facebook
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($profileData['social_discord'])): ?>
                                <span class="social-pill">
                                    <i class="fa-brands fa-discord" style="color: #5865f2;"></i> <?= htmlspecialchars($profileData['social_discord']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($profileData['website'])): ?>
                                <a href="<?= htmlspecialchars($profileData['website']) ?>" target="_blank" class="social-pill">
                                    <i class="fa-solid fa-globe" style="color: #10b981;"></i> Website
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #64748b; font-size: 0.9rem; font-style: italic; padding: 10px 0;">
                            Chưa cập nhật mạng xã hội. <?= $isOwnProfile ? '<a href="?tab=edit" style="color: var(--primary);">Thêm ngay</a>' : '' ?>
                        </p>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- ═══════════════════════════════════════════ -->
        <!-- TAB 2: CHỈNH SỬA HỒ SƠ                     -->
        <!-- ═══════════════════════════════════════════ -->
        <?php if ($isOwnProfile): ?>
        <div id="tab-edit" class="tab-pane <?= $activeTab === 'edit' ? 'active' : '' ?>">
            <div class="card-box">
                <div class="card-header">
                    <h3><i class="fa-solid fa-user-pen" style="color: var(--primary);"></i> Chỉnh Sửa Thông Tin Cá Nhân</h3>
                    <span style="font-size: 0.85rem; color: #94a3b8;">Cập nhật tức thì vào hệ thống</span>
                </div>

                <form action="in4.php" method="POST" enctype="multipart/form-data" class="form-grid">
                    <input type="hidden" name="update_profile_action" value="1">

                    <!-- Cột 1: Đổi ảnh đại diện -->
                    <div class="form-avatar-box">
                        <img src="<?= !empty($user['ImageURL']) ? htmlspecialchars($user['ImageURL'], ENT_QUOTES, 'UTF-8') : 'images.ico' ?>" class="preview-avatar" id="avatarPreview" alt="Xem trước">
                        <h4 style="font-size: 1rem; margin-bottom: 6px;">Ảnh Đại Diện</h4>
                        <p style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 15px;">JPG, PNG, GIF hoặc WEBP. Tối đa 5MB.</p>
                        <input type="file" name="image" id="imageInput" accept="image/*" style="display: none;" onchange="previewImage(this)">
                        <button type="button" class="nav-btn nav-btn-home" style="width: 100%; justify-content: center;" onclick="document.getElementById('imageInput').click()">
                            <i class="fa-solid fa-upload"></i> Chọn Ảnh Mới
                        </button>
                    </div>

                    <!-- Cột 2: Thông tin tài khoản -->
                    <div>
                        <div class="form-group">
                            <label><i class="fa-solid fa-user"></i> Tên Hiển Thị (Username)</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['Name'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fa-solid fa-envelope"></i> Email Liên Hệ</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['Email'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>

                        <div class="form-group">
                            <label><i class="fa-solid fa-comment-dots"></i> Tiểu Sử / Giới Thiệu (Bio)</label>
                            <textarea name="bio" class="form-control" rows="3" placeholder="Viết đôi dòng tâm sự hoặc câu châm ngôn của bạn..."><?= htmlspecialchars($profileData['bio'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="form-group">
                                <label><i class="fa-brands fa-facebook" style="color: #1877f2;"></i> Link Facebook</label>
                                <input type="url" name="social_facebook" class="form-control" placeholder="https://facebook.com/..." value="<?= htmlspecialchars($profileData['social_facebook'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="form-group">
                                <label><i class="fa-brands fa-discord" style="color: #5865f2;"></i> Discord Tag</label>
                                <input type="text" name="social_discord" class="form-control" placeholder="username#1234" value="<?= htmlspecialchars($profileData['social_discord'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fa-solid fa-globe"></i> Trang Web Cá Nhân</label>
                            <input type="url" name="website" class="form-control" placeholder="https://..." value="<?= htmlspecialchars($profileData['website'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="fa-solid fa-floppy-disk"></i> Lưu Tất Cả Thay Đổi
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));

            const targetBtn = document.querySelector(`button[onclick="switchTab('${tabId}')"]`);
            const targetPane = document.getElementById(`tab-${tabId}`);

            if (targetBtn) targetBtn.classList.add('active');
            if (targetPane) targetPane.classList.add('active');

            // Cập nhật URL không reload
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.replaceState({}, '', url);
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    $('#avatarPreview').attr('src', e.target.result);
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        <?php if (!empty($message)): ?>
            Swal.fire({
                icon: '<?= $messageType ?>',
                title: '<?= $messageType === "success" ? "Thành Công!" : "Thông Báo" ?>',
                text: '<?= addslashes($message) ?>',
                confirmButtonColor: '#6366f1'
            });
        <?php endif; ?>
    </script>
</body>
</html>