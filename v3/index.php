<?php
session_start();

// Kiểm tra cookie: nếu người dùng chuyển sang v1 hoặc v2 thì redirect
if (isset($_COOKIE['use_new_ui'])) {
    if ($_COOKIE['use_new_ui'] == '1' || $_COOKIE['use_new_ui'] == '2') {
        header("Location: ../v2/index.html");
        exit();
    } elseif ($_COOKIE['use_new_ui'] == '0') {
        header("Location: ../index.php");
        exit();
    }
}

// Kiểm tra đăng nhập: nếu chưa đăng nhập thì chuyển về trang đăng nhập
if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}

require '../db_connect.php';
require_once '../user_progress_helper.php';
require_once '../referral_helper.php';
require_once '../api_event_helper.php';

// Kiểm tra và kích hoạt ngẫu nhiên Flash Event chớp nhoáng (tối đa 2 lần/ngày)
EventHelper::checkOrTriggerFlashEvent($conn);

if (!$conn || $conn->connect_error) {
    die("Lỗi kết nối database: " . ($conn ? $conn->connect_error : "Không thể kết nối"));
}

// Lấy thông tin người dùng hiện tại từ bảng users
$userId = $_SESSION['Iduser'];
$sql = "SELECT u.Iduser, u.Name, u.Money, u.active_title_id, u.Role, u.current_theme_id, u.ImageURL, u.avatar_frame_id,
            a.icon as title_icon, a.name as title_name
            FROM users u
            LEFT JOIN achievements a ON u.active_title_id = a.id
            WHERE u.Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows === 1) {
    $user = $result->fetch_assoc();
    $adminData = ['Role' => $user['Role']];
} else {
    die("Không tìm thấy thông tin người dùng!");
}
$stmt->close();

// Lấy tiến trình level / streak
$userProgress = up_get_progress($conn, (int) $userId);
$seasonLevel = isset($userProgress['level']) ? (int) $userProgress['level'] : 1;
$seasonXp = isset($userProgress['xp']) ? (int) $userProgress['xp'] : 0;
$seasonRequiredXp = up_required_xp_for_level($seasonLevel);
$seasonProgressPercent = $seasonRequiredXp > 0 ? min(100, round(($seasonXp / $seasonRequiredXp) * 100)) : 0;

// Referral: lấy mã giới thiệu của user
$referralCode = ref_get_or_create_code($conn, (int) $userId);

// Load theme (sử dụng load_theme.php để đồng nhất)
require_once '../load_theme.php';
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #311042 100%)';
}

// Parse theme config cho Three.js
$particleCount = $themeConfig['particle_count'] ?? 800;
$particleSize = $themeConfig['particle_size'] ?? 0.05;
$particleColor = $themeConfig['particle_color'] ?? '#ffffff';
$particleOpacity = $themeConfig['particle_opacity'] ?? 0.6;
$shapeCount = $themeConfig['shape_count'] ?? 10;
$shapeColors = !empty($themeConfig['shape_colors']) ? json_decode($themeConfig['shape_colors'], true) : ['#667eea', '#764ba2', '#4facfe', '#00f2fe'];
$shapeOpacity = $themeConfig['shape_opacity'] ?? 0.3;

// Tính xếp hạng hiện tại
$rankSql = "SELECT COUNT(*) + 1 as rank FROM users WHERE Money > ?";
$rankStmt = $conn->prepare($rankSql);
$rankStmt->bind_param("d", $user['Money']);
$rankStmt->execute();
$rankResult = $rankStmt->get_result();
$rankData = $rankResult->fetch_assoc();
$userRank = $rankData['rank'] ?? 999;
$rankStmt->close();

// Lấy tổng số người chơi thực tế
$totalUsers = 0;
$userCountResult = $conn->query("SELECT COUNT(*) AS total FROM users");
if ($userCountResult) {
    $row = $userCountResult->fetch_assoc();
    $totalUsers = (int) $row['total'];
}

// Tổng game hiện tại (khoảng 65+ games)
$totalGames = 65;

// Lấy thống kê cá nhân từ game_history
$personalStats = [
    'totalGames' => 0,
    'winRate' => 0,
    'totalEarned' => 0,
    'achievements' => 0,
];

// Fetch current lottery jackpot
$lotterySql = "SELECT jackpot_pool FROM lottery_draws WHERE status = 'pending' ORDER BY id ASC LIMIT 1";
$lStmt = $conn->prepare($lotterySql);
$lStmt->execute();
$lRes = $lStmt->get_result();
$lotteryData = $lRes->fetch_assoc();
$currentJackpot = (float)($lotteryData['jackpot_pool'] ?? 1000000000);
$lStmt->close();

$ghCheck = $conn->query("SHOW TABLES LIKE 'game_history'");
if ($ghCheck && $ghCheck->num_rows > 0) {
    $ghStmt = $conn->prepare(
        "SELECT COUNT(*) as total,
                SUM(CASE WHEN is_win=1 THEN 1 ELSE 0 END) as wins,
                SUM(CASE WHEN is_win=1 THEN win_amount - bet_amount ELSE 0 END) as earned
         FROM game_history WHERE user_id = ?"
    );
    if ($ghStmt) {
        $ghStmt->bind_param("i", $userId);
        $ghStmt->execute();
        $ghRow = $ghStmt->get_result()->fetch_assoc();
        $ghStmt->close();
        $personalStats['totalGames'] = (int) ($ghRow['total'] ?? 0);
        $personalStats['totalEarned'] = (int) max(0, $ghRow['earned'] ?? 0);
        $personalStats['winRate'] = $personalStats['totalGames'] > 0
            ? round(($ghRow['wins'] / $personalStats['totalGames']) * 100)
            : 0;
    }
}

$uaCheck = $conn->query("SHOW TABLES LIKE 'user_achievements'");
if ($uaCheck && $uaCheck->num_rows > 0) {
    $uaStmt = $conn->prepare("SELECT COUNT(*) as total FROM user_achievements WHERE user_id = ?");
    if ($uaStmt) {
        $uaStmt->bind_param("i", $userId);
        $uaStmt->execute();
        $personalStats['achievements'] = (int) ($uaStmt->get_result()->fetch_assoc()['total'] ?? 0);
        $uaStmt->close();
    }
}

// Lấy dữ liệu bảng xếp hạng top 10
$sqlRank = "SELECT u.Name, u.Money, u.ImageURL, u.active_title_id, u.avatar_frame_id, 
                a.icon as title_icon, a.name as title_name
                FROM users u
                LEFT JOIN achievements a ON u.active_title_id = a.id
                ORDER BY u.Money DESC LIMIT 10";
$resultRank = $conn->query($sqlRank);
$ranking = [];
if ($resultRank) {
    while ($row = $resultRank->fetch_assoc()) {
        $ranking[] = $row;
    }
}

$giftMessage = '';
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_giftcode'])) {
    $inputCode = trim($_POST['giftcode']);
    $codeSql = "SELECT * FROM giftcodes WHERE code = ? AND (used_by IS NULL OR used_by = 0)";
    $stmt = $conn->prepare($codeSql);
    $stmt->bind_param("s", $inputCode);
    $stmt->execute();
    $giftResult = $stmt->get_result();

    if ($giftResult->num_rows > 0) {
        $gift = $giftResult->fetch_assoc();
        if ($gift['expires_at'] && strtotime($gift['expires_at']) < time()) {
            $giftMessage = '<div class="message error" style="color:#ef4444; font-weight:bold; margin-top:10px;">❌ Mã này đã hết hạn!</div>';
        } else {
            $reward = (float) $gift['reward'];
            $updateMoneySql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
            $updateMoneyStmt = $conn->prepare($updateMoneySql);
            $updateMoneyStmt->bind_param("di", $reward, $userId);
            $updateMoneyStmt->execute();
            $updateMoneyStmt->close();

            $updateSql = "UPDATE giftcodes SET used_by = ?, used_at = NOW() WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("ii", $userId, $gift['id']);
            $updateStmt->execute();
            $updateStmt->close();

            $giftMessage = '<div class="message success" style="color:#22c55e; font-weight:bold; margin-top:10px;">🎉 Chúc mừng! Bạn nhận được <strong>' . number_format($reward, 0, ',', '.') . ' GTLM</strong> từ mã quà tặng!</div>';
        }
        $stmt->close();
    } else {
        $giftMessage = '<div class="message error" style="color:#ef4444; font-weight:bold; margin-top:10px;">❌ Mã không tồn tại hoặc đã được sử dụng!</div>';
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="../">
    <title>V3 Dashboard - Giải Trí Lành Mạnh</title>
    <link rel="icon" type="image/x-icon" href="images.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/lobby.css">
    <link rel="stylesheet" href="assets/css/sound-ui.css">
    <?php 
    require_once '../include_css.php';
    echo getCSSIncludes(['special_effects' => true]); 
    ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <style>
        body {
            cursor: url('img/chuot.png'), auto !important;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #f8fafc;
            overflow-x: hidden;
        }
        * {
            box-sizing: border-box;
        }
        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), pointer !important;
        }

        /* ================= V3 3-COLUMN DASHBOARD STYLES ================= */
        .main-wrapper {
            display: flex;
            gap: 24px;
            max-width: 1720px;
            margin: 25px auto;
            padding: 0 20px;
            align-items: flex-start;
        }

        /* 1. COLLAPSIBLE SIDEBAR */
        .sidebar {
            width: 280px;
            flex-shrink: 0;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            border: 1.5px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 20px 14px;
            position: sticky;
            top: 25px;
            max-height: calc(100vh - 50px);
            overflow-y: auto;
            transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1), padding 0.35s;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            z-index: 99;
        }
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
        }
        .sidebar.collapsed {
            width: 75px;
            padding: 20px 10px;
        }
        .sidebar.collapsed .menu-text,
        .sidebar.collapsed .menu-category-title {
            display: none;
        }
        .sidebar.collapsed .menu-item {
            justify-content: center;
            padding: 14px 0;
        }
        .sidebar.collapsed .menu-icon {
            margin-right: 0;
            font-size: 24px;
        }
        .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .sidebar-title {
            font-size: 16px;
            font-weight: 800;
            background: linear-gradient(to right, #60a5fa, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sidebar-toggle {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #f8fafc;
            border-radius: 10px;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.25s;
        }
        .sidebar-toggle:hover {
            background: rgba(96, 165, 250, 0.25);
            border-color: #60a5fa;
            transform: scale(1.05);
        }
        .menu-category {
            margin-bottom: 22px;
        }
        .menu-category-title {
            font-size: 11px;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin: 0 0 10px 8px;
        }
        .menu-item {
            display: flex;
            align-items: center;
            padding: 11px 14px;
            margin-bottom: 6px;
            border-radius: 12px;
            text-decoration: none;
            color: #e2e8f0;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.25s;
            border: 1px solid transparent;
        }
        .menu-item:hover {
            background: linear-gradient(90deg, rgba(96, 165, 250, 0.15), rgba(192, 132, 252, 0.15));
            border-color: rgba(96, 165, 250, 0.3);
            color: #60a5fa;
            transform: translateX(5px);
        }
        .menu-icon {
            font-size: 20px;
            margin-right: 12px;
            width: 26px;
            text-align: center;
            transition: transform 0.25s;
        }
        .menu-item:hover .menu-icon {
            transform: scale(1.2);
        }

        /* 2. MAIN CONTENT & TOP SECTION */
        .main-content {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .top-section {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 20px;
            align-items: center;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(15px);
            border: 1.5px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 20px 25px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }
        .live-clock {
            text-align: center;
            background: rgba(255, 255, 255, 0.05);
            padding: 12px 20px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .live-clock .time {
            font-size: 24px;
            font-weight: 900;
            color: #38bdf8;
            letter-spacing: 2px;
            text-shadow: 0 0 15px rgba(56, 189, 248, 0.5);
        }
        .live-clock .date {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 2px;
        }
        .jackpot-banner-modern {
            text-align: center;
            background: linear-gradient(135deg, rgba(234, 179, 8, 0.15), rgba(239, 68, 68, 0.15));
            border: 1.5px solid rgba(234, 179, 8, 0.4);
            border-radius: 16px;
            padding: 12px 20px;
            box-shadow: 0 0 25px rgba(234, 179, 8, 0.2);
        }
        .jackpot-banner-modern .label {
            font-size: 13px;
            font-weight: 800;
            color: #facc15;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .jackpot-banner-modern .amount {
            font-size: 32px;
            font-weight: 900;
            color: #ffffff;
            text-shadow: 0 0 20px #eab308;
            margin: 4px 0;
        }
        .stats-container-modern {
            display: flex;
            gap: 14px;
        }
        .stat-card-modern {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 12px 18px;
            text-align: center;
            min-width: 105px;
        }
        .stat-card-modern .icon {
            font-size: 22px;
            margin-bottom: 4px;
        }
        .stat-card-modern .value {
            font-size: 18px;
            font-weight: 800;
            color: #4ade80;
        }
        .stat-card-modern .label {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
        }

        /* 3. CONTENT WRAPPER (GRID 3-COLUMN) */
        .content-wrapper {
            display: grid;
            grid-template-columns: 310px 1fr 320px;
            gap: 24px;
            align-items: flex-start;
        }

        /* LEFT & RIGHT COLUMNS */
        .widget-card {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            border: 1.5px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.35);
        }
        .widget-title {
            font-size: 16px;
            font-weight: 800;
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 0;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .personal-stat-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .p-stat-box {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 12px;
            padding: 12px;
            text-align: center;
        }
        .p-stat-box .v {
            font-size: 18px;
            font-weight: 800;
            color: #60a5fa;
        }
        .p-stat-box .l {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 3px;
        }

        /* CENTER COLUMN (GAME LOBBY) */
        .center-column {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .lobby-tabs {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 8px;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            border: 1.5px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            scrollbar-width: none;
        }
        .tab-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #cbd5e1;
            padding: 10px 18px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            white-space: nowrap;
            transition: all 0.25s;
        }
        .tab-btn:hover, .tab-btn.active {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: #ffffff;
            border-color: transparent;
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
            transform: translateY(-2px);
        }
        .game-grid-v3 {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 16px;
        }
        .game-card-v3 {
            background: rgba(30, 41, 59, 0.7);
            border: 1.5px solid rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            padding: 18px 14px;
            text-align: center;
            text-decoration: none;
            color: #f8fafc;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
        }
        .game-card-v3:hover {
            background: rgba(51, 65, 85, 0.9);
            border-color: #60a5fa;
            transform: translateY(-7px) scale(1.03);
            box-shadow: 0 15px 30px rgba(96, 165, 250, 0.35);
        }
        .game-card-v3 .icon {
            font-size: 42px;
            margin-bottom: 12px;
            transition: transform 0.3s;
        }
        .game-card-v3:hover .icon {
            transform: scale(1.15) rotate(5deg);
        }
        .game-card-v3 .name {
            font-size: 15px;
            font-weight: 700;
            line-height: 1.3;
        }
        .game-badge-v3 {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ef4444;
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 20px;
            text-transform: uppercase;
            box-shadow: 0 2px 10px rgba(239, 68, 68, 0.5);
        }

        /* RANKING TABLE IN RIGHT COLUMN */
        .ranking-table-v3 {
            width: 100%;
            border-collapse: collapse;
        }
        .ranking-table-v3 th {
            text-align: left;
            padding: 10px 8px;
            font-size: 12px;
            color: #94a3b8;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .ranking-table-v3 td {
            padding: 10px 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 13px;
        }
        .ranking-table-v3 tr:hover td {
            background: rgba(255, 255, 255, 0.03);
        }

        /* RESPONSIVE BREAKPOINTS */
        @media (max-width: 1400px) {
            .content-wrapper {
                grid-template-columns: 280px 1fr;
            }
            .right-column {
                display: none;
            }
        }
        @media (max-width: 1024px) {
            .main-wrapper {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                max-height: none;
                position: relative;
                top: 0;
            }
            .content-wrapper {
                grid-template-columns: 1fr;
            }
            .top-section {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .stats-container-modern {
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<!-- Three.js Canvas Container -->
<div id="three-container" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; pointer-events: none;"></div>

<!-- Header (Khung Top từ V1 với chức năng Switch UI trong Avatar) -->
<header class="header" style="background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(15px); border-bottom: 1px solid rgba(255,255,255,0.1); padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 1000;">
    <div style="display: flex; align-items: center; gap: 15px;">
        <a href="v3/index.php" style="text-decoration: none; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 28px;">🎮</span>
            <div>
                <span style="font-size: 18px; font-weight: 900; background: linear-gradient(90deg, #38bdf8, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">GTLM PORTAL</span>
                <span style="background: #a855f7; color: #fff; font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 6px; margin-left: 6px;">V3</span>
            </div>
        </a>
    </div>

    <div style="display: flex; align-items: center; gap: 20px;">
        <div style="background: rgba(255,255,255,0.08); padding: 8px 16px; border-radius: 999px; border: 1px solid rgba(255,255,255,0.15); font-weight: 700; font-size: 14px;">
            💰 <span style="color: #4ade80;"><?= number_format($user['Money'], 0, ',', '.') ?></span> GTLM
        </div>

        <!-- Avatar & UI Switcher Dropdown -->
        <div class="daidien" style="position: relative;">
            <div class="avatar-wrapper" style="width: 45px; height: 45px; position: relative;">
                <?php
                $avatarPath = !empty($user['ImageURL']) ? $user['ImageURL'] : 'images.ico';
                ?>
                <img src="<?= htmlspecialchars($avatarPath, ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 2px solid #60a5fa;" onerror="this.src='images.ico'">
            </div>
            <div class="dropdown-menu" style="display: none; position: absolute; right: 0; top: 55px; background: #0f172a; border: 1px solid rgba(255,255,255,0.15); border-radius: 14px; padding: 10px; width: 240px; box-shadow: 0 15px 35px rgba(0,0,0,0.5); flex-direction: column; gap: 6px; z-index: 9999;">
                <div style="padding: 8px 12px; border-bottom: 1px solid rgba(255,255,255,0.1); font-weight: 800; font-size: 14px; color: #38bdf8;">
                    <?= htmlspecialchars($user['Name'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <a href="profile.php" style="color: #e2e8f0; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; display: flex; align-items: center; gap: 10px;"><i class="fa-solid fa-user"></i> Hồ sơ của bạn</a>
                <a href="shop.php" style="color: #e2e8f0; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; display: flex; align-items: center; gap: 10px;"><i class="fa-solid fa-store"></i> Cửa hàng giao diện</a>
                <div style="border-top: 1px solid rgba(255,255,255,0.1); margin: 4px 0;"></div>
                <div style="font-size: 11px; font-weight: 800; color: #94a3b8; padding: 4px 12px; text-transform: uppercase;">Chuyển đổi giao diện</div>
                <a href="switch_ui.php?v=v1" style="color: #60a5fa; font-weight: 700; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; display: flex; align-items: center; gap: 10px;"><i class="fa-solid fa-desktop"></i> V1 (PHP Mặc định)</a>
                <a href="switch_ui.php?v=v2" style="color: #f59e0b; font-weight: 700; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; display: flex; align-items: center; gap: 10px;"><i class="fa-solid fa-bolt"></i> V2 (React / Vite)</a>
                <a href="switch_ui.php?v=v3" style="background: rgba(74, 222, 128, 0.15); color: #4ade80; font-weight: 700; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; display: flex; align-items: center; gap: 10px;"><i class="fa-solid fa-layer-group"></i> V3 (3-Column Dashboard)</a>
                <div style="border-top: 1px solid rgba(255,255,255,0.1); margin: 4px 0;"></div>
                <a href="login.php" style="color: #ef4444; font-weight: 700; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-size: 13px; display: flex; align-items: center; gap: 10px;"><i class="fa-solid fa-right-from-bracket"></i> Đăng xuất</a>
            </div>
        </div>
    </div>
</header>

<!-- MAIN WRAPPER WITH SIDEBAR & DASHBOARD GRID -->
<div class="main-wrapper">
    
    <!-- ====== SIDEBAR (COLLAPSIBLE) ====== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">
                <i class="fa-solid fa-bars-staggered" style="color: #60a5fa;"></i>
                <span class="menu-text">DANH MỤC</span>
            </div>
            <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()" title="Gập / Mở Sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <!-- CATEGORY 1: Khám phá -->
            <div class="menu-category">
                <h4 class="menu-category-title">Khám phá</h4>
                <a href="about.php" class="menu-item" title="Giới thiệu">
                    <span class="menu-icon">📘</span>
                    <span class="menu-text">Giới thiệu</span>
                </a>
                <a href="social_feed.php" class="menu-item" title="Bảng Tin">
                    <span class="menu-icon">📱</span>
                    <span class="menu-text">Bảng Tin</span>
                </a>
                <a href="statistics.php" class="menu-item" title="Thống Kê">
                    <span class="menu-icon">📊</span>
                    <span class="menu-text">Thống Kê</span>
                </a>
                <a href="events.php" class="menu-item" title="Sự Kiện">
                    <span class="menu-icon">🎉</span>
                    <span class="menu-text">Sự Kiện</span>
                </a>
                <a href="server_history.php" class="menu-item" title="Biên Niên Sử">
                    <span class="menu-icon">📜</span>
                    <span class="menu-text">Biên Niên Sử</span>
                </a>
            </div>

            <!-- CATEGORY 2: Nhiệm vụ & Thưởng -->
            <div class="menu-category">
                <h4 class="menu-category-title">Nhiệm vụ & Thưởng</h4>
                <a href="quests.php" class="menu-item" title="Nhiệm Vụ">
                    <span class="menu-icon">🎯</span>
                    <span class="menu-text">Nhiệm Vụ</span>
                </a>
                <a href="tower_of_gods.php" class="menu-item" title="Tháp Thần Bài">
                    <span class="menu-icon">🗼</span>
                    <span class="menu-text">Tháp Thần Bài</span>
                </a>
                <a href="my_lounge.php" class="menu-item" title="Biệt Thự & Cúp">
                    <span class="menu-icon">🏡</span>
                    <span class="menu-text">Biệt Thự & Cúp</span>
                </a>
                <a href="daily_challenges.php" class="menu-item" title="Thử Thách">
                    <span class="menu-icon">🔥</span>
                    <span class="menu-text">Thử Thách</span>
                </a>
                <a href="streak_system.php" class="menu-item" title="Chuỗi Đăng Nhập">
                    <span class="menu-icon">⚡</span>
                    <span class="menu-text">Chuỗi Ngày</span>
                </a>
                <a href="reward_points.php" class="menu-item" title="Điểm Thưởng">
                    <span class="menu-icon">⭐</span>
                    <span class="menu-text">Điểm Thưởng</span>
                </a>
                <a href="lucky_wheel.php" class="menu-item" title="Lucky Wheel">
                    <span class="menu-icon">🎡</span>
                    <span class="menu-text">Lucky Wheel</span>
                </a>
                <a href="daily_login.php" class="menu-item" title="Điểm Danh">
                    <span class="menu-icon">🎁</span>
                    <span class="menu-text">Điểm Danh</span>
                </a>
            </div>

            <!-- CATEGORY 3: Cửa hàng & Items -->
            <div class="menu-category">
                <h4 class="menu-category-title">Cửa hàng & Items</h4>
                <a href="shop.php" class="menu-item" title="Cửa Hàng">
                    <span class="menu-icon">🛒</span>
                    <span class="menu-text">Cửa Hàng</span>
                </a>
                <a href="inventory.php" class="menu-item" title="Kho Đồ">
                    <span class="menu-icon">📦</span>
                    <span class="menu-text">Kho Đồ</span>
                </a>
                <a href="marketplace.php" class="menu-item" title="Chợ Giao Dịch">
                    <span class="menu-icon">💼</span>
                    <span class="menu-text">Chợ Giao Dịch</span>
                </a>
                <a href="crafting.php" class="menu-item" title="Workshop">
                    <span class="menu-icon">🛠️</span>
                    <span class="menu-text">Workshop Rèn</span>
                </a>
                <a href="gift.php" class="menu-item" title="Tặng Quà">
                    <span class="menu-icon">🎁</span>
                    <span class="menu-text">Tặng Quà</span>
                </a>
            </div>

            <!-- CATEGORY 4: Xã hội & Cạnh tranh -->
            <div class="menu-category">
                <h4 class="menu-category-title">Xã hội & Cạnh tranh</h4>
                <a href="chat.php" class="menu-item" title="Chat Tổng">
                    <span class="menu-icon">💬</span>
                    <span class="menu-text">Chat Tổng</span>
                </a>
                <a href="guilds.php" class="menu-item" title="Bang Hội">
                    <span class="menu-icon">🏆</span>
                    <span class="menu-text">Bang Hội</span>
                </a>
                <a href="pvp_challenge.php" class="menu-item" title="Đấu PvP">
                    <span class="menu-icon">⚔️</span>
                    <span class="menu-text">Đấu PvP</span>
                </a>
                <a href="leaderboard.php" class="menu-item" title="Xếp Hạng">
                    <span class="menu-icon">👑</span>
                    <span class="menu-text">Bảng Xếp Hạng</span>
                </a>
                <a href="tournaments.php" class="menu-item" title="Giải Đấu">
                    <span class="menu-icon">🎯</span>
                    <span class="menu-text">Giải Đấu</span>
                </a>
            </div>

            <!-- CATEGORY 5: Tài khoản -->
            <div class="menu-category">
                <h4 class="menu-category-title">Tài Khoản</h4>
                <a href="profile.php" class="menu-item" title="Hồ Sơ">
                    <span class="menu-icon">👤</span>
                    <span class="menu-text">Hồ Sơ Của Bạn</span>
                </a>
                <a href="select_title.php" class="menu-item" title="Danh Hiệu">
                    <span class="menu-icon">👑</span>
                    <span class="menu-text">Danh Hiệu</span>
                </a>
                <a href="khungchat.php" class="menu-item" title="Khung Chat">
                    <span class="menu-icon">🎨</span>
                    <span class="menu-text">Khung Chat</span>
                </a>
                <a href="khungavatar.php" class="menu-item" title="Khung Avatar">
                    <span class="menu-icon">🖼️</span>
                    <span class="menu-text">Khung Avatar</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- ====== MAIN CONTENT AREA ====== -->
    <div class="main-content">

        <!-- ========== TOP SECTION (Clock, Jackpot, Stats) ========== -->
        <div class="top-section">
            <div class="live-clock">
                <div class="time" id="liveTime">--:--:--</div>
                <div class="date" id="liveDate">--/--/----</div>
            </div>

            <div class="jackpot-banner-modern">
                <div class="label">🏆 HŨ RỒNG THẦN TOÀN SERVER 🏆</div>
                <div class="amount" id="jackpotAmount"><?= number_format($currentJackpot, 0, ',', '.') ?> GTLM</div>
                <div style="font-size: 12px; color: #cbd5e1;">Cùng tranh tài nổ hũ nhận giải thưởng cực đại mỗi ngày</div>
            </div>

            <div class="stats-container-modern">
                <div class="stat-card-modern">
                    <div class="icon">🎮</div>
                    <div class="value"><?= $totalGames ?></div>
                    <div class="label">Game có sẵn</div>
                </div>
                <div class="stat-card-modern">
                    <div class="icon">👥</div>
                    <div class="value"><?= number_format($totalUsers, 0, ',', '.') ?></div>
                    <div class="label">Người chơi</div>
                </div>
                <div class="stat-card-modern">
                    <div class="icon">🏆</div>
                    <div class="value">#<?= $userRank ?></div>
                    <div class="label">Xếp hạng</div>
                </div>
            </div>
        </div>

        <!-- ========== CONTENT GRID (3-COLUMN) ========== -->
        <div class="content-wrapper">

            <!-- ===== LEFT COLUMN ===== -->
            <section class="left-column">
                
                <!-- Personal Statistics Widget -->
                <div class="widget-card">
                    <div class="widget-title">
                        <span>📊 Thống Kê Cá Nhân</span>
                        <a href="statistics.php" style="font-size: 12px; color: #60a5fa; text-decoration: none;">Chi tiết &rarr;</a>
                    </div>
                    <div class="personal-stat-grid">
                        <div class="p-stat-box">
                            <div class="v"><?= number_format($personalStats['totalGames'], 0, ',', '.') ?></div>
                            <div class="l">Tổng game đã chơi</div>
                        </div>
                        <div class="p-stat-box">
                            <div class="v" style="color: #34d399;"><?= $personalStats['winRate'] ?>%</div>
                            <div class="l">Tỷ lệ chiến thắng</div>
                        </div>
                        <div class="p-stat-box">
                            <div class="v" style="color: #fbbf24;"><?= number_format($personalStats['totalEarned'], 0, ',', '.') ?></div>
                            <div class="l">GTLM kiếm được</div>
                        </div>
                        <div class="p-stat-box">
                            <div class="v" style="color: #c084fc;"><?= $personalStats['achievements'] ?></div>
                            <div class="l">Thành tích đạt được</div>
                        </div>
                    </div>
                </div>

                <!-- Checkin & Daily Rewards -->
                <div class="widget-card">
                    <div class="widget-title">
                        <span>📅 Điểm Danh Nhận Quà</span>
                    </div>
                    <form method="post" action="diemdanh.php" style="margin-bottom: 12px;">
                        <button type="submit" style="width: 100%; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; padding: 12px; border-radius: 12px; font-weight: 800; font-size: 14px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4); transition: all 0.25s;">
                            ✅ Điểm Danh Hôm Nay
                        </button>
                    </form>
                    <a href="caothe.php" style="display: block; text-align: center; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 10px; border-radius: 12px; text-decoration: none; color: #facc15; font-size: 13px; font-weight: 700;">
                        🎫 Cào Thẻ Nhân Phẩm Hằng Ngày
                    </a>
                </div>

                <!-- Storyline Event Shortcut -->
                <div class="widget-card" style="background: linear-gradient(135deg, rgba(30, 27, 75, 0.9), rgba(49, 16, 66, 0.9)); border-color: rgba(192, 132, 252, 0.3);">
                    <div class="widget-title" style="color: #c084fc;">
                        <span>📖 Cốt Truyện Đại Chiến</span>
                        <span style="font-size: 11px; background: #a855f7; color: white; padding: 2px 8px; border-radius: 10px;">HOT</span>
                    </div>
                    <p style="font-size: 13px; color: #e2e8f0; margin: 0 0 14px; line-height: 1.5;">Vượt các ải thử thách cốt truyện độc quyền, đánh bại Boss và nhận thưởng khủng mỗi ngày!</p>
                    <a href="storyline_event.php" style="display: block; text-align: center; background: linear-gradient(90deg, #a855f7, #6366f1); color: white; padding: 10px; border-radius: 12px; text-decoration: none; font-weight: 800; font-size: 13px; box-shadow: 0 4px 15px rgba(168, 85, 247, 0.4);">
                        THAM GIA NGAY &rarr;
                    </a>
                </div>

                <!-- Giftcode Box -->
                <div class="widget-card">
                    <div class="widget-title">
                        <span>🎁 Nhập Giftcode</span>
                    </div>
                    <form method="post" style="display: flex; gap: 8px;">
                        <input type="text" name="giftcode" placeholder="Mã quà tặng..." required style="flex: 1; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; padding: 10px 12px; color: white; font-size: 13px;">
                        <button type="submit" name="submit_giftcode" style="background: #3b82f6; color: white; border: none; padding: 10px 16px; border-radius: 10px; font-weight: 700; font-size: 13px;">Nhận</button>
                    </form>
                    <?= $giftMessage ?>
                </div>

            </section>

            <!-- ===== CENTER COLUMN (GAME LOBBY - MAIN FOCUS) ===== -->
            <section class="center-column">
                
                <!-- Category Tabs -->
                <div class="lobby-tabs" id="lobbyTabs">
                    <button class="tab-btn active" data-category="all">Tất cả Game</button>
                    <button class="tab-btn" data-category="vip">🌟 VIP & AFK</button>
                    <button class="tab-btn" data-category="card">🃏 Game Bài</button>
                    <button class="tab-btn" data-category="slots">🎰 Slots & Xổ Số</button>
                    <button class="tab-btn" data-category="mini">🎯 Mini Games</button>
                    <button class="tab-btn" data-category="social">🤝 Bang Hội & Social</button>
                </div>

                <!-- Game Grid V3 (All 60+ Games) -->
                <div class="game-grid-v3" id="gameGrid">
                    
                    <!-- VIP & AFK Games -->
                    <a href="games/farm.php" class="game-card-v3" data-category="vip">
                        <span class="game-badge-v3" style="background:#22c55e;">NEW</span>
                        <span class="icon">🌾</span>
                        <span class="name">Nông Trại AFK</span>
                    </a>
                    <a href="games/mining.php" class="game-card-v3" data-category="vip">
                        <span class="game-badge-v3" style="background:#fbbf24;">AFK</span>
                        <span class="icon">⛏️</span>
                        <span class="name">Khu Mỏ Khoáng Sản</span>
                    </a>
                    <a href="tower_of_gods.php" class="game-card-v3" data-category="vip">
                        <span class="game-badge-v3" style="background:#a855f7;">HOT</span>
                        <span class="icon">🗼</span>
                        <span class="name">Tháp Thần Bài</span>
                    </a>
                    <a href="plinko_royale_v3.php" class="game-card-v3" data-category="vip slots">
                        <span class="game-badge-v3" style="background:linear-gradient(135deg,#f59e0b,#ef4444); color:#fff;">V3 ROYALE</span>
                        <span class="icon">🎰</span>
                        <span class="name">Plinko Royale V3</span>
                    </a>
                    <a href="my_lounge.php" class="game-card-v3" data-category="vip social">
                        <span class="game-badge-v3" style="background:#ec4899;">LUXURY</span>
                        <span class="icon">🏡</span>
                        <span class="name">Biệt Thự Hoàng Gia</span>
                    </a>
                    </a>
                    <a href="games/pets.php" class="game-card-v3" data-category="vip">
                        <span class="game-badge-v3" style="background:#ec4899;">PETS</span>
                        <span class="icon">🐾</span>
                        <span class="name">Chuồng Thú Cưng</span>
                    </a>
                    <a href="games/market.php" class="game-card-v3" data-category="vip">
                        <span class="game-badge-v3" style="background:#ef4444;">VIP</span>
                        <span class="icon">📈</span>
                        <span class="name">Sàn Chứng Khoán</span>
                    </a>
                    <a href="games/greedy_cave.php" class="game-card-v3" data-category="vip">
                        <span class="game-badge-v3" style="background:#8b5cf6;">HOT</span>
                        <span class="icon">🦇</span>
                        <span class="name">Hang Tham Lam</span>
                    </a>

                    <!-- Card Games -->
                    <a href="games/tusac.php" class="game-card-v3" data-category="card">
                        <span class="game-badge-v3">NEW</span>
                        <span class="icon">🎴</span>
                        <span class="name">Tứ Sắc Cổ Truyền</span>
                    </a>
                    <a href="games/samloc.php" class="game-card-v3" data-category="card">
                        <span class="game-badge-v3">NEW</span>
                        <span class="icon">🃏</span>
                        <span class="name">Sâm Lốc Tốc Độ</span>
                    </a>
                    <a href="games/blackjack.php" class="game-card-v3" data-category="card">
                        <span class="game-badge-v3" style="background:#f59e0b;">HOT</span>
                        <span class="icon">👑</span>
                        <span class="name">Xì Dách Royale</span>
                    </a>
                    <a href="games/blackjack_multi.php" class="game-card-v3" data-category="card">
                        <span class="game-badge-v3" style="background:#3b82f6;">MULTI</span>
                        <span class="icon">👥</span>
                        <span class="name">Xì Dách Multi</span>
                    </a>
                    <a href="games/poker.php" class="game-card-v3" data-category="card">
                        <span class="icon">🃏</span>
                        <span class="name">Poker Texas</span>
                    </a>
                    <a href="games/baccarat.php" class="game-card-v3" data-category="card">
                        <span class="icon">🂡</span>
                        <span class="name">Baccarat Premium</span>
                    </a>
                    <a href="games/dragontiger.php" class="game-card-v3" data-category="card">
                        <span class="icon">🐉</span>
                        <span class="name">Long Hổ</span>
                    </a>
                    <a href="games/threecard.php" class="game-card-v3" data-category="card">
                        <span class="icon">🃏</span>
                        <span class="name">Three Card Poker</span>
                    </a>
                    <a href="games/holdem.php" class="game-card-v3" data-category="card">
                        <span class="icon">♠️</span>
                        <span class="name">Casino Hold'em</span>
                    </a>
                    <a href="games/bj.php" class="game-card-v3" data-category="card">
                        <span class="icon">🃏</span>
                        <span class="name">Xì Dách Classic</span>
                    </a>

                    <!-- Slots & Xổ Số -->
                    <a href="games/community_lottery.php" class="game-card-v3" data-category="slots">
                        <span class="game-badge-v3" style="background:#ef4444;">HOT</span>
                        <span class="icon">🎫</span>
                        <span class="name">Xổ Số Cộng Đồng</span>
                    </a>
                    <a href="games/plinko_v2.php" class="game-card-v3" data-category="slots">
                        <span class="game-badge-v3" style="background:#eab308;">HOT</span>
                        <span class="icon">🔮</span>
                        <span class="name">Plinko V2</span>
                    </a>
                    <a href="games/slot.php" class="game-card-v3" data-category="slots">
                        <span class="game-badge-v3">NEW</span>
                        <span class="icon">🎰</span>
                        <span class="name">Slot Machine</span>
                    </a>
                    <a href="games/roulette.php" class="game-card-v3" data-category="slots">
                        <span class="icon">🎡</span>
                        <span class="name">Roulette</span>
                    </a>
                    <a href="games/vietlott.php" class="game-card-v3" data-category="slots">
                        <span class="icon">🎟️</span>
                        <span class="name">Vietlott</span>
                    </a>
                    <a href="games/keno.php" class="game-card-v3" data-category="slots">
                        <span class="icon">🎱</span>
                        <span class="name">Keno Premium</span>
                    </a>
                    <a href="games/bingo.php" class="game-card-v3" data-category="slots">
                        <span class="icon">🎱</span>
                        <span class="name">Bingo Club</span>
                    </a>
                    <a href="games/ruttham.php" class="game-card-v3" data-category="slots">
                        <span class="icon">🎟️</span>
                        <span class="name">Rút Thăm</span>
                    </a>

                    <!-- Mini Games -->
                    <a href="games/daga.php" class="game-card-v3" data-category="mini">
                        <span class="game-badge-v3">NEW</span>
                        <span class="icon">🐓</span>
                        <span class="name">Đá Gà Premium</span>
                    </a>
                    <a href="games/battleroyale.php" class="game-card-v3" data-category="mini">
                        <span class="game-badge-v3">NEW</span>
                        <span class="icon">🔥</span>
                        <span class="name">Battle Royale Số</span>
                    </a>
                    <a href="games/baucua.php" class="game-card-v3" data-category="mini">
                        <span class="game-badge-v3" style="background:#f59e0b;">HOT</span>
                        <span class="icon">🎲</span>
                        <span class="name">CYBER PETS</span>
                    </a>
                    <a href="games/crash.php" class="game-card-v3" data-category="mini">
                        <span class="game-badge-v3" style="background:#f59e0b;">HOT</span>
                        <span class="icon">🛫</span>
                        <span class="name">Crash Flight</span>
                    </a>
                    <a href="games/plinko.php" class="game-card-v3" data-category="mini">
                        <span class="icon">🔴</span>
                        <span class="name">Plinko Royale</span>
                    </a>
                    <a href="games/mines.php" class="game-card-v3" data-category="mini">
                        <span class="icon">💣</span>
                        <span class="name">Mines Premium</span>
                    </a>
                    <a href="games/tower.php" class="game-card-v3" data-category="mini">
                        <span class="icon">🗼</span>
                        <span class="name">Tower Climb</span>
                    </a>
                    <a href="games/scratch.php" class="game-card-v3" data-category="mini">
                        <span class="icon">🎫</span>
                        <span class="name">Cào Thẻ</span>
                    </a>
                    <a href="games/dice.php" class="game-card-v3" data-category="mini">
                        <span class="icon">🎲</span>
                        <span class="name">Lắc Xí Ngầu</span>
                    </a>
                    <a href="games/sicbo_v2.php" class="game-card-v3" data-category="mini">
                        <span class="game-badge-v3">NEW</span>
                        <span class="icon">🎲</span>
                        <span class="name">Xanh Đỏ 3D</span>
                    </a>
                    <a href="games/horserace.php" class="game-card-v3" data-category="mini">
                        <span class="game-badge-v3">NEW</span>
                        <span class="icon">🐎</span>
                        <span class="name">Đua Ngựa Pari</span>
                    </a>
                    <a href="games/duangua.php" class="game-card-v3" data-category="mini">
                        <span class="game-badge-v3" style="background:#f59e0b;">HOT</span>
                        <span class="icon">🐎</span>
                        <span class="name">Đua Thú Premium</span>
                    </a>
                    <a href="games/jojo_battle.php" class="game-card-v3" data-category="mini">
                        <span class="game-badge-v3">NEW</span>
                        <span class="icon">👊</span>
                        <span class="name">Đại Chiến JoJo</span>
                    </a>
                    <a href="games/horserace_pvp.php" class="game-card-v3" data-category="mini">
                        <span class="game-badge-v3" style="background:#ef4444;">PVP</span>
                        <span class="icon">🐎</span>
                        <span class="name">Đua Ngựa PVP</span>
                    </a>

                    <!-- Social & Guilds -->
                    <a href="guild_pro.php" class="game-card-v3" data-category="social">
                        <span class="game-badge-v3" style="background:#3b82f6;">PRO</span>
                        <span class="icon">🛡️</span>
                        <span class="name">Bang Hội Pro</span>
                    </a>
                    <a href="social_feed.php" class="game-card-v3" data-category="social">
                        <span class="icon">📱</span>
                        <span class="name">Bảng Tin & Feed</span>
                    </a>
                    <a href="mentor_center.php" class="game-card-v3" data-category="social">
                        <span class="game-badge-v3">NEW</span>
                        <span class="icon">🤝</span>
                        <span class="name">Trung Tâm Sư Đồ</span>
                    </a>
                    <a href="guild_tournament.php" class="game-card-v3" data-category="social">
                        <span class="game-badge-v3" style="background:#f59e0b;">GvG</span>
                        <span class="icon">🏆</span>
                        <span class="name">Đại Chiến Bang Hội</span>
                    </a>

                </div>

            </section>

            <!-- ===== RIGHT COLUMN (RANKINGS & WIDGETS) ===== -->
            <section class="right-column">
                
                <!-- World Boss Widget -->
                <div class="widget-card" style="border-color: rgba(255, 69, 0, 0.4); background: linear-gradient(135deg, rgba(30, 20, 30, 0.9), rgba(60, 20, 20, 0.9));">
                    <div class="widget-title" style="color: #ff4500;">
                        <span>🐉 World Boss Thần Long</span>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 13px; font-weight: 700; color: #f8fafc; margin-bottom: 12px;">Hắc Long Thần đang xuất hiện tại Đấu Trường!</div>
                        <a href="world_boss.php" style="display: block; background: #ff4500; color: white; padding: 10px; border-radius: 12px; text-decoration: none; font-weight: 800; font-size: 13px; box-shadow: 0 4px 15px rgba(255, 69, 0, 0.4);">THAM CHIẾN NGAY</a>
                    </div>
                </div>

                <!-- Battle Pass Widget -->
                <div class="widget-card" style="border-color: rgba(79, 172, 254, 0.4);">
                    <div class="widget-title" style="color: #4facfe;">
                        <span>⭐ Battle Pass Mùa 1</span>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 13px; color: #cbd5e1; margin-bottom: 12px;">Hoàn thành nhiệm vụ nhận Thần Binh & GTLM Khủng</div>
                        <a href="battle_pass.php" style="display: block; background: linear-gradient(90deg, #4facfe, #00f2fe); color: #0f172a; padding: 10px; border-radius: 12px; text-decoration: none; font-weight: 800; font-size: 13px;">XEM TIẾN ĐỘ BATTLE PASS</a>
                    </div>
                </div>

                <!-- Top Ranking Table -->
                <div class="widget-card">
                    <div class="widget-title">
                        <span>🏆 Top Đại Gia GTLM</span>
                        <a href="leaderboard.php" style="font-size: 12px; color: #60a5fa; text-decoration: none;">Tất cả &rarr;</a>
                    </div>
                    <table class="ranking-table-v3">
                        <thead>
                            <tr>
                                <th style="width: 30px;">#</th>
                                <th>Đại Gia</th>
                                <th style="text-align: right;">GTLM</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($ranking)): ?>
                                <?php foreach ($ranking as $index => $r): ?>
                                    <tr>
                                        <td style="font-weight: 800; color: <?= $index === 0 ? '#facc15' : ($index === 1 ? '#cbd5e1' : ($index === 2 ? '#fb923c' : '#94a3b8')) ?>;">
                                            <?= $index + 1 ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <div style="width: 32px; height: 32px; border-radius: 50%; overflow: hidden; position: relative; flex-shrink: 0; border: 1.5px solid <?= $index === 0 ? '#facc15' : '#60a5fa' ?>;">
                                                    <img src="<?= htmlspecialchars(!empty($r['ImageURL']) ? $r['ImageURL'] : 'images.ico', ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='images.ico'">
                                                </div>
                                                <div style="min-width: 0;">
                                                    <div style="font-weight: 700; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: <?= $index === 0 ? '#facc15' : '#e2e8f0' ?>;">
                                                        <?php if (!empty($r['title_icon'])): ?>
                                                            <span><?= htmlspecialchars($r['title_icon'], ENT_QUOTES, 'UTF-8') ?></span>
                                                        <?php endif; ?>
                                                        <?= htmlspecialchars($r['Name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="text-align: right; font-weight: 800; color: #4ade80;">
                                            <?= number_format($r['Money'], 0, ',', '.') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; color: #94a3b8;">Chưa có dữ liệu!</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </section>

        </div>

    </div>

</div>

<!-- SCRIPTS FOR V3 DASHBOARD -->
<script>
// 1. Sidebar Toggle function with localStorage state
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const isCollapsed = sidebar.classList.toggle('collapsed');
    localStorage.setItem('v3_sidebar_collapsed', isCollapsed ? '1' : '0');
}
document.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('v3_sidebar_collapsed') === '1') {
        document.getElementById('sidebar').classList.add('collapsed');
    }
});

// 2. Lobby Tabs Filtering
document.addEventListener('DOMContentLoaded', () => {
    const tabButtons = document.querySelectorAll('#lobbyTabs .tab-btn');
    const gameCards = document.querySelectorAll('#gameGrid .game-card-v3');

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            tabButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const targetCategory = btn.getAttribute('data-category');
            gameCards.forEach(card => {
                if (targetCategory === 'all' || card.getAttribute('data-category') === targetCategory) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
});

// 3. Live Clock Update
function updateLiveClock() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    
    const day = String(now.getDate()).padStart(2, '0');
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const year = now.getFullYear();

    const timeEl = document.getElementById('liveTime');
    const dateEl = document.getElementById('liveDate');
    if (timeEl) timeEl.textContent = `${hours}:${minutes}:${seconds}`;
    if (dateEl) dateEl.textContent = `${day}/${month}/${year}`;
}
setInterval(updateLiveClock, 1000);
updateLiveClock();

// 4. Three.js Subtle Particle Background
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('three-container');
    if (!container || typeof THREE === 'undefined') return;

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    container.appendChild(renderer.domElement);

    const count = <?= (int)$particleCount ?>;
    const geometry = new THREE.BufferGeometry();
    const positions = new Float32Array(count * 3);
    for (let i = 0; i < count * 3; i += 3) {
        positions[i] = (Math.random() - 0.5) * 50;
        positions[i + 1] = (Math.random() - 0.5) * 50;
        positions[i + 2] = (Math.random() - 0.5) * 50;
    }
    geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));

    const material = new THREE.PointsMaterial({
        size: <?= (float)$particleSize ?>,
        color: new THREE.Color('<?= htmlspecialchars($particleColor, ENT_QUOTES, 'UTF-8') ?>'),
        transparent: true,
        opacity: <?= (float)$particleOpacity ?>,
    });

    const particles = new THREE.Points(geometry, material);
    scene.add(particles);
    camera.position.z = 20;

    function animate() {
        requestAnimationFrame(animate);
        particles.rotation.y += 0.0007;
        particles.rotation.x += 0.0003;
        renderer.render(scene, camera);
    }
    animate();

    window.addEventListener('resize', () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    });
});
</script>

</body>
</html>
