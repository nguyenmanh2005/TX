<?php
session_start();

// Kiểm tra đăng nhập: nếu chưa đăng nhập thì chuyển về trang đăng nhập
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

// Kết nối tới database
require 'db_connect.php';
require_once 'user_progress_helper.php';
require_once 'referral_helper.php';

// Kiểm tra kết nối database
if (!$conn || $conn->connect_error) {
    die("Lỗi kết nối database: " . ($conn ? $conn->connect_error : "Không thể kết nối"));
}

// Lấy thông tin người dùng hiện tại từ bảng users
$userId = $_SESSION['Iduser'];
$sql = "SELECT u.Iduser, u.Name, u.Money, u.active_title_id, u.Role, u.current_theme_id,
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
require_once 'load_theme.php';
// Đảm bảo $bgGradientCSS có giá trị
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

// Parse theme config cho Three.js (lấy từ load_theme.php hoặc mặc định)
$particleCount = $themeConfig['particle_count'] ?? 1000;
$particleSize = $themeConfig['particle_size'] ?? 0.05;
$particleColor = $themeConfig['particle_color'] ?? '#ffffff';
$particleOpacity = $themeConfig['particle_opacity'] ?? 0.6;
$shapeCount = $themeConfig['shape_count'] ?? 15;
$shapeColors = !empty($themeConfig['shape_colors']) ? json_decode($themeConfig['shape_colors'], true) : ['#667eea', '#764ba2', '#4facfe', '#00f2fe'];
$shapeOpacity = $themeConfig['shape_opacity'] ?? 0.3;
$bgGradient = $bgGradient ?? ['#667eea', '#764ba2', '#4facfe'];

// Tính xếp hạng hiện tại
$rankSql = "SELECT COUNT(*) + 1 as rank FROM users WHERE Money > ?";
$rankStmt = $conn->prepare($rankSql);
$rankStmt->bind_param("d", $user['Money']);
$rankStmt->execute();
$rankResult = $rankStmt->get_result();
$rankData = $rankResult->fetch_assoc();
$userRank = $rankData['rank'] ?? 999;
$rankStmt->close();

// Lấy thống kê cá nhân từ game_history
$personalStats = [
    'totalGames' => 0,
    'winRate' => 0,
    'totalEarned' => 0,
    'achievements' => 0,
];

// Kiểm tra bảng game_history có tồn tại không
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

// Đếm thành tích
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

if (file_exists('api_check_rank_achievements.php')) {
    require_once 'api_check_rank_achievements.php';
    if (function_exists('checkAndAwardRankAchievements')) {
        checkAndAwardRankAchievements($conn, $userId); // Pass userId to optimize
    }
}

// Lấy dữ liệu bảng xếp hạng top 10 người có Số Gtlm cao nhất
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

        // Kiểm tra hạn sử dụng
        if ($gift['expires_at'] && strtotime($gift['expires_at']) < time()) {
            $giftMessage = '<div class="message error">❌ Mã này đã hết hạn!</div>';
        } else {
            // Cập nhật gtlm người dùng (sử dụng prepared statement để tránh SQL injection)
            $reward = (float) $gift['reward'];
            $updateMoneySql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
            $updateMoneyStmt = $conn->prepare($updateMoneySql);
            $updateMoneyStmt->bind_param("di", $reward, $userId);
            $updateMoneyStmt->execute();
            $updateMoneyStmt->close();

            // Cập nhật trạng thái mã
            $updateSql = "UPDATE giftcodes SET used_by = ?, used_at = NOW() WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("ii", $userId, $gift['id']);
            $updateStmt->execute();
            $updateStmt->close();

            $giftMessage = '<div class="message success">🎉 Chúc mừng! Bạn nhận được <strong>' . number_format($reward, 0, ',', '.') . ' gtlm</strong> từ mã quà tặng!</div>';
        }
        $stmt->close();
    } else {
        $giftMessage = '<div class="message error">❌ Mã không tồn tại hoặc đã được sử dụng!</div>';
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/lobby.css">
    <link rel="stylesheet" href="assets/css/sound-ui.css">
    <?php require_once 'include_css.php';
    echo getCSSIncludes(['special_effects' => true]); ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <title>Trang Chủ - Giải Trí Lành Mạnh</title>
    <style>
        body {
            cursor: url('<?= dirname($_SERVER['PHP_SELF']) ?>/img/chuot.png'), url('img/chuot.png'), auto !important;
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
        }

        * {
            cursor: inherit;
        }

        button,
        a,
        input[type="button"],
        input[type="submit"],
        label,
        select,
        input[type="text"] {
            cursor: url('<?= dirname($_SERVER['PHP_SELF']) ?>/img/tay.png'), url('img/tay.png'), pointer !important;
        }

        /* Additional custom styles for index page */
        .game-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 25px;
            animation: fadeInUp 0.6s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .game-link {
            display: block;
            padding: 20px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius-lg);
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            text-align: center;
            font-weight: 700;
            font-size: 18px;
            box-shadow: 0 4px 20px rgba(52, 152, 219, 0.3),
                0 0 0 0 rgba(52, 152, 219, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
            animation: fadeInScale 0.5s ease backwards;
            border: 2px solid transparent;
            background-clip: padding-box;
            z-index: 1;
        }

        .game-link::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.4) 0%, transparent 70%);
            transform: translate(-50%, -50%);
            transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1), height 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: -1;
        }

        .game-link::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg,
                    transparent 0%,
                    rgba(255, 255, 255, 0.4) 50%,
                    transparent 100%);
            transition: left 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1;
        }

        .game-link:hover::before {
            width: 400px;
            height: 400px;
        }

        .game-link:hover::after {
            left: 100%;
        }

        .game-link:hover {
            transform: translateY(-12px) scale(1.08) rotate(2deg);
            box-shadow: 0 20px 50px rgba(52, 152, 219, 0.7),
                0 0 40px rgba(52, 152, 219, 0.5),
                0 0 80px rgba(52, 152, 219, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            background: linear-gradient(135deg,
                    var(--secondary-dark) 0%,
                    var(--secondary-color) 50%,
                    var(--secondary-dark) 100%);
            background-size: 200% 200%;
            animation: gradientShift 3s ease infinite, pulseGlow 2s ease-in-out infinite;
            border-color: rgba(255, 255, 255, 0.3);
        }

        .game-link:active {
            transform: translateY(-6px) scale(1.04);
            box-shadow: 0 10px 30px rgba(52, 152, 219, 0.6),
                0 0 20px rgba(52, 152, 219, 0.4);
        }

        @keyframes gradientShift {

            0%,
            100% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }
        }

        @keyframes pulseGlow {

            0%,
            100% {
                box-shadow: 0 20px 50px rgba(52, 152, 219, 0.7),
                    0 0 40px rgba(52, 152, 219, 0.5),
                    0 0 80px rgba(52, 152, 219, 0.3);
            }

            50% {
                box-shadow: 0 20px 50px rgba(52, 152, 219, 0.9),
                    0 0 60px rgba(52, 152, 219, 0.7),
                    0 0 100px rgba(52, 152, 219, 0.5);
            }
        }

        .game-link span {
            position: relative;
            z-index: 2;
            display: inline-block;
            transition: transform 0.3s ease;
        }

        .game-link:hover span {
            transform: scale(1.1);
        }

        .game-link:nth-child(1) {
            animation-delay: 0.1s;
        }

        .game-link:nth-child(2) {
            animation-delay: 0.15s;
        }

        .game-link:nth-child(3) {
            animation-delay: 0.2s;
        }

        .game-link:nth-child(4) {
            animation-delay: 0.25s;
        }

        .game-link:nth-child(5) {
            animation-delay: 0.3s;
        }

        .game-link:nth-child(6) {
            animation-delay: 0.35s;
        }

        .game-link:nth-child(7) {
            animation-delay: 0.4s;
        }

        .game-link:nth-child(8) {
            animation-delay: 0.45s;
        }

        .game-link:nth-child(9) {
            animation-delay: 0.5s;
        }

        .game-link:nth-child(10) {
            animation-delay: 0.55s;
        }

        .game-link:nth-child(11) {
            animation-delay: 0.6s;
        }

        .game-link:nth-child(12) {
            animation-delay: 0.65s;
        }

        .game-link:nth-child(13) {
            animation-delay: 0.7s;
        }

        .game-link:nth-child(14) {
            animation-delay: 0.75s;
        }

        .game-link:nth-child(15) {
            animation-delay: 0.8s;
        }

        .game-link:nth-child(16) {
            animation-delay: 0.85s;
        }

        .game-link:nth-child(17) {
            animation-delay: 0.9s;
        }

        .game-link:nth-child(18) {
            animation-delay: 0.95s;
        }

        .game-link:nth-child(19) {
            animation-delay: 1s;
        }

        .game-link:nth-child(20) {
            animation-delay: 1.05s;
        }

        .balance-display {
            font-size: 28px;
            font-weight: 700;
            color: var(--success-color);
            margin: 25px 0;
            padding: 20px;
            background: rgba(232, 245, 233, 0.95);
            border-radius: var(--border-radius-lg);
            border: 3px solid var(--success-color);
            box-shadow: var(--shadow-lg);
            text-align: center;
            animation: balancePulse 2s ease-in-out infinite, fadeInDown 0.6s ease;
            position: relative;
            overflow: hidden;
        }

        .balance-display::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(46, 204, 113, 0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }

        .balance-display>* {
            position: relative;
            z-index: 1;
        }

        .balance-display a {
            position: relative;
            z-index: 10 !important;
            pointer-events: auto !important;
        }

        @keyframes balancePulse {

            0%,
            100% {
                box-shadow: 0 4px 20px rgba(46, 204, 113, 0.3),
                    0 0 0 0 rgba(46, 204, 113, 0.4);
            }

            50% {
                box-shadow: 0 4px 30px rgba(46, 204, 113, 0.5),
                    0 0 20px rgba(46, 204, 113, 0.3);
            }
        }

        .season-pass {
            margin-top: 15px;
            padding: 12px 16px;
            border-radius: var(--border-radius-lg);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.08) 100%);
            border: 1px solid rgba(102, 126, 234, 0.4);
            text-align: left;
            font-size: 14px;
        }

        .season-pass-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .season-pass-bar {
            width: 100%;
            height: 10px;
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-top: 4px;
        }

        .season-pass-bar span {
            display: block;
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, var(--secondary-color) 0%, var(--success-color) 100%);
            transition: width 0.6s ease;
        }

        .info h3 {
            color: var(--primary-color);
            font-size: 24px;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .gift form {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .gift input[type="text"] {
            flex: 1;
            padding: 12px 18px;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            background: rgba(255, 255, 255, 0.95);
            color: var(--text-dark);
            font-size: 16px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .gift input[type="text"]:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.2);
        }

        .gift button {
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--success-color) 0%, var(--success-dark) 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
        }

        .gift button:hover:not(:disabled) {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 6px 20px rgba(46, 204, 113, 0.5);
        }

        .gift button:disabled {
            opacity: 0.6;
            cursor: not-allowed !important;
        }

        .message {
            margin-top: 15px;
            padding: 15px;
            border-radius: var(--border-radius);
            font-weight: 600;
            animation: messageSlide 0.5s ease;
        }

        .message.success {
            background: rgba(40, 167, 69, 0.2);
            border: 2px solid #28a745;
            color: #00ff00;
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.4);
        }

        .message.error {
            background: rgba(220, 53, 69, 0.2);
            border: 2px solid #dc3545;
            color: #ff6b6b;
        }

        @keyframes messageSlide {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .ranking table {
            animation: fadeIn 0.6s ease;
        }

        .ranking tr {
            animation: rowSlide 0.4s ease backwards;
        }

        .ranking tr:nth-child(1) {
            animation-delay: 0.1s;
        }

        .ranking tr:nth-child(2) {
            animation-delay: 0.2s;
        }

        .ranking tr:nth-child(3) {
            animation-delay: 0.3s;
        }

        .ranking tr:nth-child(4) {
            animation-delay: 0.4s;
        }

        .ranking tr:nth-child(5) {
            animation-delay: 0.5s;
        }

        .ranking tr:nth-child(6) {
            animation-delay: 0.6s;
        }

        .ranking tr:nth-child(7) {
            animation-delay: 0.7s;
        }

        .ranking tr:nth-child(8) {
            animation-delay: 0.8s;
        }

        .ranking tr:nth-child(9) {
            animation-delay: 0.9s;
        }

        .ranking tr:nth-child(10) {
            animation-delay: 1s;
        }

        @keyframes rowSlide {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .daily-checkin button {
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--warning-color) 0%, #e67e22 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(243, 156, 18, 0.3);
        }

        .daily-checkin button:hover:not(:disabled) {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 6px 20px rgba(243, 156, 18, 0.5);
        }

        .info-column .info,
        .info-column .gift {
            animation: fadeIn 0.6s ease;
        }

        /* Fix ranking table alignment */
        .ranking {
            flex: 0 0 auto;
            width: fit-content;
            min-width: fit-content;
            display: block;
            overflow: visible;
        }

        .ranking h2 {
            white-space: nowrap;
            text-align: center;
            margin-bottom: 20px;
            font-size: 18px;
        }

        .ranking table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            table-layout: auto;
            margin: 0;
        }

        .ranking th,
        .ranking td {
            padding: 8px 4px;
            vertical-align: middle;
            font-size: 12px;
        }

        .ranking th:nth-child(1),
        .ranking td:nth-child(1) {
            width: 30px;
            text-align: center;
        }

        .ranking th:nth-child(2),
        .ranking td:nth-child(2) {
            width: 45px;
            text-align: center;
            padding: 4px;
        }

        .ranking td:nth-child(2) .avatar-border {
            width: 35px;
            height: 35px;
            margin: 0 auto;
            border: 1px solid var(--border-color);
            border-radius: 50%;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .ranking td:nth-child(2) .avatar-border img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .ranking th:nth-child(3),
        .ranking td:nth-child(3) {
            text-align: left;
            padding-left: 4px;
            white-space: nowrap;
        }

        .ranking th:nth-child(4),
        .ranking td:nth-child(4) {
            text-align: right;
            padding-right: 4px;
            color: var(--success-color);
            font-weight: 700;
            white-space: nowrap;
        }

        /* Đảm bảo nội dung luôn hiển thị đủ */
        .ranking td {
            white-space: nowrap;
        }

        /* Fix dashboard layout alignment */
        .container {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            /* Changed from stretch to avoid excessive vertical space */
            gap: 25px;
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px 20px;
            overflow: visible;
        }

        .info-column {
            flex: 1.2;
            /* Sidebar width balance */
            display: flex;
            flex-direction: column;
            gap: 20px;
            min-width: 350px;
        }

        .info-column>.info,
        .info-column>.gift {
            flex: 0 0 auto;
        }

        .info {
            flex: 2.2;
            /* Main content width */
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Dashboard Menu Grid Styles */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 15px;
        }

        .menu-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 15px 8px;
            background: rgba(102, 126, 234, 0.05);
            border: 1px solid rgba(102, 126, 234, 0.15);
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--primary-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            font-size: 13px;
            font-weight: 600;
            gap: 8px;
        }

        .menu-item:hover {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white !important;
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.3);
            border-color: transparent;
        }

        .menu-item .menu-icon {
            font-size: 22px;
            display: block;
        }

        .menu-category-title {
            grid-column: span 2;
            font-size: 12px;
            font-weight: 800;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 15px 0 5px;
            padding-bottom: 5px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            text-align: left;
        }

        body.dark-mode .menu-item {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.1);
            color: #e0e0e0;
        }

        body.dark-mode .menu-category-title {
            color: #888;
            border-bottom-color: rgba(255, 255, 255, 0.1);
        }

        body {
            position: relative;
        }

        .container {
            position: relative;
            z-index: 1;
        }

        .header {
            position: relative;
            z-index: 1000;
        }

        /* Avatar và Dropdown - Fix hover triệt để */
        .daidien {
            position: relative;
            z-index: 10000;
            display: inline-block;
        }

        .daidien .avatar-wrapper {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 50px;
            pointer-events: auto;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .avatar-frame-overlay {
            position: absolute;
            top: -5px;
            left: -5px;
            width: calc(100% + 10px);
            height: calc(100% + 10px);
            z-index: 1;
            pointer-events: none !important;
            border-radius: 50%;
        }

        .avatar-frame-overlay img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
            pointer-events: none !important;
        }

        .daidien img {
            position: relative;
            z-index: 2;
            pointer-events: auto;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .dropdown-menu {
            z-index: 10002 !important;
            pointer-events: auto !important;
            top: 65px !important;
            margin-top: 5px;
        }

        .daidien:hover .dropdown-menu,
        .dropdown-menu:hover {
            display: flex !important;
        }

        /* Tạo vùng hover mở rộng */
        .daidien::before {
            content: '';
            position: absolute;
            top: -5px;
            left: -15px;
            right: -15px;
            bottom: -85px;
            z-index: 9998;
            pointer-events: none;
        }

        .daidien:hover::before {
            pointer-events: auto;
        }

        .dropdown-menu a {
            pointer-events: auto !important;
            z-index: 10003 !important;
        }

        /* Fix cursor for avatar and dropdown */
        .daidien {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .dropdown-menu a {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        /* Live Clock */
        .live-clock {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.9) 0%, rgba(118, 75, 162, 0.9) 100%);
            color: white;
            padding: 15px 25px;
            border-radius: var(--border-radius-lg);
            text-align: center;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            font-weight: 600;
            animation: fadeInDown 0.8s ease;
        }

        .live-clock .time {
            font-size: 32px;
            font-weight: 700;
            margin: 5px 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .live-clock .date {
            font-size: 16px;
            opacity: 0.9;
        }

        /* Animated Statistics */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: var(--border-radius-lg);
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 2px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            border-color: var(--secondary-color);
        }

        .stat-card .stat-icon {
            font-size: 36px;
            margin-bottom: 10px;
        }

        .stat-card .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            margin: 5px 0;
        }

        .stat-card .stat-label {
            font-size: 14px;
            color: var(--text-dark);
            opacity: 0.8;
        }

        /* Random Tips Section */
        .tips-section {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1) 0%, rgba(255, 152, 0, 0.1) 100%);
            border: 2px solid var(--warning-color);
            border-radius: var(--border-radius-lg);
            padding: 20px;
            margin: 20px 0;
            animation: fadeInUp 0.6s ease;
        }

        .quest-widget {
            background: rgba(255, 255, 255, 0.96);
            border-radius: var(--border-radius-lg);
            padding: 25px;
            margin: 20px 0;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            border: 2px solid rgba(102, 126, 234, 0.15);
        }

        .quest-widget-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 15px;
        }

        .quest-widget-header h3 {
            margin: 0;
            font-size: 22px;
            color: var(--primary-color);
        }

        .quest-widget-header p {
            margin: 4px 0 0;
            font-size: 14px;
            color: var(--text-dark);
            opacity: 0.8;
        }

        .quest-widget-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            font-size: 13px;
            color: var(--text-dark);
            opacity: 0.8;
        }

        .quest-widget-meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .quest-widget-link {
            padding: 10px 16px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .quest-widget-toggle {
            display: inline-flex;
            border-radius: 999px;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .quest-widget-toggle button {
            border: none;
            background: transparent;
            padding: 6px 14px;
            font-weight: 600;
            color: var(--text-dark);
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .quest-widget-toggle button.active {
            background: rgba(102, 126, 234, 0.12);
            color: var(--primary-color);
        }

        .quest-widget-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: flex-end;
        }

        .quest-widget-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }

        .quest-widget-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }

        .summary-item {
            background: rgba(102, 126, 234, 0.08);
            border-radius: var(--border-radius);
            padding: 12px 15px;
            text-align: center;
        }

        .summary-item .summary-label {
            display: block;
            font-size: 13px;
            color: var(--text-dark);
            opacity: 0.8;
        }

        .summary-item .summary-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .quest-widget-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .quest-widget-empty {
            text-align: center;
            padding: 16px;
            background: rgba(0, 0, 0, 0.02);
            border-radius: var(--border-radius);
            color: var(--text-dark);
            font-weight: 600;
        }

        .quest-pill {
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: var(--border-radius);
            padding: 15px;
            background: rgba(255, 255, 255, 0.98);
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .quest-pill.completed {
            border-color: rgba(40, 167, 69, 0.5);
            background: rgba(40, 167, 69, 0.08);
        }

        .quest-pill.claimed {
            opacity: 0.6;
        }

        .quest-pill-icon {
            font-size: 32px;
        }

        .quest-pill-content {
            flex: 1;
        }

        .quest-pill-title {
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 6px;
        }

        .quest-pill-meta {
            font-size: 12px;
            color: var(--text-dark);
            opacity: 0.8;
            margin-bottom: 4px;
        }

        .quest-pill-desc {
            font-size: 13px;
            color: var(--text-dark);
            opacity: 0.9;
            margin-bottom: 6px;
        }

        .quest-pill-progress {
            height: 8px;
            background: rgba(0, 0, 0, 0.08);
            border-radius: 999px;
            overflow: hidden;
        }

        .quest-pill-progress span {
            display: block;
            height: 100%;
            background: linear-gradient(90deg, var(--success-color) 0%, var(--secondary-color) 100%);
        }

        .activity-feed {
            background: rgba(255, 255, 255, 0.96);
            border-radius: var(--border-radius-lg);
            padding: 25px;
            margin: 20px 0;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .feed-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 15px;
        }

        .feed-header h3 {
            margin: 0;
            font-size: 22px;
            color: var(--primary-color);
        }

        .feed-header p {
            margin: 4px 0 0;
            font-size: 14px;
            color: var(--text-dark);
            opacity: 0.8;
        }

        .feed-actions {
            display: flex;
            gap: 8px;
        }

        .feed-actions button {
            border: none;
            background: rgba(102, 126, 234, 0.12);
            color: var(--primary-color);
            padding: 6px 14px;
            border-radius: 999px;
            font-weight: 600;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .feed-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .feed-card {
            display: flex;
            gap: 15px;
            align-items: center;
            padding: 15px;
            border-radius: var(--border-radius);
            background: rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.03);
        }

        .feed-card.highlight {
            border-color: rgba(255, 193, 7, 0.4);
            background: rgba(255, 193, 7, 0.12);
        }

        .feed-avatar {
            position: relative;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }

        .feed-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .feed-avatar-frame {
            position: absolute;
            top: -4px;
            left: -4px;
            width: calc(100% + 8px);
            height: calc(100% + 8px);
            pointer-events: none;
        }

        .feed-content {
            flex: 1;
        }

        .feed-message {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .feed-meta {
            font-size: 12px;
            color: var(--text-dark);
            opacity: 0.8;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .feed-empty {
            text-align: center;
            padding: 20px;
            border-radius: var(--border-radius);
            background: rgba(0, 0, 0, 0.03);
            color: var(--text-dark);
            font-weight: 600;
        }

        .notifications-widget {
            background: rgba(255, 255, 255, 0.96);
            border-radius: var(--border-radius-lg);
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .notifications-widget h3 {
            margin: 0 0 10px;
            font-size: 18px;
            color: var(--primary-color);
        }

        .notif-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 13px;
            max-height: 400px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
        }

        .notif-list::-webkit-scrollbar {
            width: 6px;
        }

        .notif-list::-webkit-scrollbar-track {
            background: transparent;
        }

        .notif-list::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        .notif-item {
            padding: 12px;
            border-radius: var(--border-radius);
            background: rgba(0, 0, 0, 0.02);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .notif-item:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateX(5px);
        }

        .notif-item.unread {
            background: rgba(102, 126, 234, 0.15);
            border-left: 3px solid #667eea;
            font-weight: 600;
        }

        .notif-item.unread::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: #667eea;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .notif-item.important {
            background: rgba(241, 196, 15, 0.1);
            border-left-color: #f1c40f;
        }

        .notif-icon {
            font-size: 20px;
            flex-shrink: 0;
        }

        .notif-content {
            flex: 1;
            min-width: 0;
        }

        .notif-title {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .notif-text {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.4;
        }

        .notif-item span.time,
        .notif-time {
            white-space: nowrap;
            color: rgba(255, 255, 255, 0.5);
            font-size: 11px;
            margin-left: 8px;
            flex-shrink: 0;
        }

        /* Badge pulse animation */
        .pulse {
            animation: badgePulse 0.5s ease;
        }

        @keyframes badgePulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.2);
            }

            100% {
                transform: scale(1);
            }
        }

        .tips-section h3 {
            color: var(--warning-color);
            margin-bottom: 15px;
            font-size: 20px;
        }

        .tip-content {
            font-size: 16px;
            line-height: 1.6;
            color: var(--text-dark);
            min-height: 60px;
            animation: fadeIn 0.5s ease;
        }

        .tip-content::before {
            content: "💡 ";
            font-size: 20px;
        }

        /* Confetti Animation */
        .confetti-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9999;
        }

        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background: var(--confetti-color, #ff6b6b);
            animation: confettiFall linear forwards;
        }

        @keyframes confettiFall {
            to {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0;
            }
        }

        /* Animated Balance Counter */
        .balance-display .balance-value {
            display: inline-block;
            transition: all 0.3s ease;
        }

        .balance-display.balance-update {
            animation: balanceUpdate 0.5s ease;
        }

        @keyframes balanceUpdate {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
                color: var(--success-color);
            }
        }

        /* Enhanced Particle Effect for Game Links */
        .game-link {
            position: relative;
        }

        .particle {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            animation: particleFloat var(--duration, 1s) ease-out forwards;
            z-index: 10;
        }

        @keyframes particleFloat {
            0% {
                opacity: 1;
                transform: translate(0, 0) scale(1) rotate(0deg);
            }

            50% {
                opacity: 0.8;
                transform: translate(calc(var(--tx) * 0.5), calc(var(--ty) * 0.5)) scale(1.2) rotate(180deg);
            }

            100% {
                opacity: 0;
                transform: translate(var(--tx), var(--ty)) scale(0) rotate(360deg);
            }
        }

        /* Typing Effect */
        .typing-effect {
            display: inline-block;
            border-right: 2px solid;
            animation: blink 0.75s step-end infinite;
        }

        @keyframes blink {

            from,
            to {
                border-color: transparent;
            }

            50% {
                border-color: currentColor;
            }
        }

        /* Tooltip */
        .tooltip {
            position: relative;
        }

        .tooltip::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%) translateY(-10px);
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            font-size: 14px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease, transform 0.3s ease;
            z-index: 1000;
        }

        .tooltip:hover::after {
            opacity: 1;
            transform: translateX(-50%) translateY(-5px);
        }

        /* Progress Bar */
        .progress-container {
            margin: 15px 0;
        }

        .progress-bar {
            width: 100%;
            height: 25px;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color) 0%, var(--secondary-color) 100%);
            border-radius: 15px;
            transition: width 0.5s ease;
            position: relative;
            overflow: hidden;
        }

        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% {
                transform: translateX(-100%);
            }

            100% {
                transform: translateX(100%);
            }
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-weight: 700;
            color: white;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
            z-index: 1;
        }

        /* Notification Toast */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            z-index: 10000;
            animation: toastSlideIn 0.3s ease;
            max-width: 350px;
        }

        @keyframes toastSlideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .toast.success {
            border-left: 4px solid var(--success-color);
        }

        .toast.error {
            border-left: 4px solid var(--error-color);
        }

        /* Floating Action Button */
        .fab {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            border: none;
            font-size: 24px;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            box-shadow: 0 4px 20px rgba(52, 152, 219, 0.4);
            transition: all 0.3s ease;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .messages-fab {
            position: fixed;
            bottom: 100px;
            right: 30px;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            border: none;
            font-size: 20px;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            box-shadow: 0 4px 18px rgba(52, 152, 219, 0.45);
            transition: all 0.3s ease;
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .messages-fab:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 26px rgba(52, 152, 219, 0.6);
        }

        .messages-fab .badge {
            position: absolute;
            top: -4px;
            right: -4px;
            min-width: 20px;
            height: 20px;
            padding: 0 5px;
            border-radius: 999px;
            background: #e74c3c;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 0 2px #fff;
        }

        .fab:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 6px 30px rgba(52, 152, 219, 0.6);
        }

        /* Server Notification Banner */
        .server-notification {
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: <?= $bgGradientCSS ?>; background-attachment: fixed;
            color: white;
            padding: 20px 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
            z-index: 10000;
            max-width: 90%;
            text-align: center;
            font-weight: 700;
            font-size: 18px;
            animation: notificationSlideDown 0.5s ease, notificationPulse 2s ease-in-out infinite;
            display: none;
        }

        @keyframes notificationSlideDown {
            from {
                transform: translateX(-50%) translateY(-100px);
                opacity: 0;
            }

            to {
                transform: translateX(-50%) translateY(0);
                opacity: 1;
            }
        }

        @keyframes notificationPulse {

            0%,
            100% {
                box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
            }

            50% {
                box-shadow: 0 8px 40px rgba(255, 107, 107, 0.8);
            }
        }

        .server-notification.show {
            display: block;
        }

        .server-notification .close-btn {
            position: absolute;
            top: 10px;
            right: 15px;
            background: rgba(255, 255, 255, 0.3);
            border: none;
            color: white;
            font-size: 20px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: all 0.3s ease;
        }

        .server-notification .close-btn:hover {
            background: rgba(255, 255, 255, 0.5);
            transform: scale(1.1);
        }

        /* Quick Links Section */
        .quick-links {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius-lg);
            padding: 25px;
            margin: 20px auto;
            max-width: 1200px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            border: 2px solid rgba(102, 126, 234, 0.15);
            animation: fadeInUp 0.6s ease;
        }

        .quick-links h2 {
            margin: 0 0 20px 0;
            font-size: 24px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quick-links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .quick-link-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: var(--border-radius);
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            text-decoration: none;
            color: var(--text-dark);
            display: block;
        }

        .quick-link-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
            border-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
        }

        .quick-link-icon {
            font-size: 36px;
            margin-bottom: 10px;
            display: block;
        }

        .quick-link-title {
            font-weight: 700;
            font-size: 16px;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .quick-link-desc {
            font-size: 13px;
            color: var(--text-dark);
            opacity: 0.8;
        }

        /* Quest Claim Button */
        .quest-claim-btn {
            margin-top: 10px;
            padding: 8px 16px;
            background: <?= $bgGradientCSS ?>; background-attachment: fixed;
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 14px;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: all 0.3s ease;
            width: 100%;
        }

        .quest-claim-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.4);
        }

        /* Quick Actions Widget */
        .quick-actions-widget {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius-lg);
            padding: 25px;
            margin: 20px 0;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            border: 2px solid rgba(102, 126, 234, 0.15);
            animation: fadeInUp 0.6s ease;
        }

        .quick-actions-widget h3 {
            margin: 0 0 20px 0;
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
        }

        #quickActionsContainer {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .quick-action-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 18px 20px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--text-dark);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.5s ease backwards;
        }

        .quick-action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.6s ease;
        }

        .quick-action-card:hover {
            transform: translateY(-5px) translateX(5px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            border-color: var(--primary-color);
        }

        .quick-action-card:hover::before {
            left: 100%;
        }

        .quick-action-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .quick-action-content {
            flex: 1;
        }

        .quick-action-title {
            font-weight: 700;
            font-size: 16px;
            color: var(--primary-color);
            margin-bottom: 4px;
        }

        .quick-action-desc {
            font-size: 13px;
            color: var(--text-light);
        }

        .quick-action-arrow {
            font-size: 20px;
            color: var(--text-light);
            transition: all 0.3s ease;
        }

        .quick-action-card:hover .quick-action-arrow {
            transform: translateX(5px);
            color: var(--primary-color);
        }

        /* Quick Search Modal */
        .quick-search-results {
            margin-top: 20px;
            max-height: 400px;
            overflow-y: auto;
        }

        .quick-search-result-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--text-dark);
            transition: all 0.2s ease;
            border: 2px solid transparent;
        }

        .quick-search-result-item:hover {
            background: rgba(102, 126, 234, 0.1);
            border-color: rgba(102, 126, 234, 0.3);
            transform: translateX(5px);
        }

        .quick-search-result-icon {
            font-size: 24px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 50%;
        }

        .quick-search-result-content {
            flex: 1;
        }

        .quick-search-result-name {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 4px;
        }

        .quick-search-result-name mark {
            background: rgba(255, 215, 0, 0.3);
            padding: 0 2px;
            border-radius: 3px;
        }

        .quick-search-result-category {
            font-size: 12px;
            color: var(--text-light);
        }

        .quick-search-result-arrow {
            color: var(--text-light);
            font-size: 18px;
        }

        .quick-search-result-item:hover .quick-search-result-arrow {
            transform: translateX(5px);
            color: var(--primary-color);
        }

        /* Toast Notifications */
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: white;
            padding: 15px 20px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            z-index: 10000;
            max-width: 350px;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s ease;
        }

        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .toast-success {
            border-left: 4px solid var(--success-color);
        }

        .toast-error {
            border-left: 4px solid var(--danger-color);
        }

        .toast-info {
            border-left: 4px solid var(--info-color);
        }

        /* Copy Button */
        .copy-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            padding: 5px 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        code:hover .copy-btn,
        .copyable:hover .copy-btn {
            opacity: 1;
        }

        /* Personal Statistics Widget */
        .personal-stats-widget {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius-lg);
            padding: 25px;
            margin: 20px 0;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            border: 2px solid rgba(102, 126, 234, 0.15);
            animation: fadeInUp 0.6s ease;
        }

        .personal-stats-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(102, 126, 234, 0.1);
        }

        .personal-stats-header h3 {
            margin: 0;
            font-size: 20px;
            color: var(--primary-color);
            font-weight: 700;
        }

        .stats-view-all {
            color: var(--secondary-color);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .stats-view-all:hover {
            color: var(--secondary-dark);
            transform: translateX(5px);
        }

        .personal-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
        }

        .personal-stat-item {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: var(--border-radius);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .personal-stat-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s ease;
        }

        .personal-stat-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
            border-color: var(--primary-color);
        }

        .personal-stat-item:hover::before {
            left: 100%;
        }

        .personal-stat-icon {
            font-size: 36px;
            line-height: 1;
            flex-shrink: 0;
        }

        .personal-stat-content {
            flex: 1;
        }

        .personal-stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
            line-height: 1.2;
        }

        .personal-stat-label {
            font-size: 13px;
            color: var(--text-light);
            font-weight: 500;
        }

        /* Favorite Games Widget */
        .favorite-games-widget {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius-lg);
            padding: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            border: 2px solid rgba(102, 126, 234, 0.15);
            animation: fadeInUp 0.6s ease;
        }



        /* Recent Action Badge */
        .recent-action {
            border-color: var(--success-color) !important;
        }

        .recent-badge {
            display: inline-block;
            background: var(--success-color);
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 6px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .quick-action-footer {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .shortcut-key {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            background: rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.2);
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-light);
        }

        .quick-action-card:hover .shortcut-key {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* Notification Badge Pulse */
        #notificationsBadge.pulse {
            animation: badgePulse 0.6s ease;
        }

        @keyframes badgePulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.2);
            }
        }

        /* ── Dark Mode ─────────────────────────────────────── */
        body.dark-mode {
            --bg-color: #0f0f1a;
            --card-bg: rgba(255, 255, 255, 0.04);
            --text-dark: #e0e0e0;
            --text-light: #999;
            --border-color: rgba(255, 255, 255, 0.1);
            background: #0f0f1a !important;
            color: #e0e0e0;
        }

        body.dark-mode .header {
            background: rgba(15, 15, 26, 0.95) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        body.dark-mode .info-column,
        body.dark-mode .info,
        body.dark-mode .ranking,
        body.dark-mode .gift {
            background: rgba(255, 255, 255, 0.04) !important;
            color: #e0e0e0;
        }

        body.dark-mode .stat-card,
        body.dark-mode .personal-stats-widget,
        body.dark-mode .personal-stat-item,
        body.dark-mode .live-clock,
        body.dark-mode .quick-link-card,
        body.dark-mode .balance-display {
            background: rgba(255, 255, 255, 0.05) !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
            color: #e0e0e0 !important;
        }

        body.dark-mode .personal-stats-widget,
        body.dark-mode .favorite-games-widget {
            background: rgba(20, 20, 40, 0.95) !important;
        }

        body.dark-mode .dropdown-menu {
            background: #1a1a2e !important;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        body.dark-mode .dropdown-menu a {
            color: #e0e0e0 !important;
        }

        body.dark-mode .dropdown-menu a:hover {
            background: rgba(102, 126, 234, 0.2) !important;
        }

        body.dark-mode table {
            background: transparent;
        }

        body.dark-mode td,
        body.dark-mode th {
            color: #e0e0e0;
            border-color: rgba(255, 255, 255, 0.08);
        }

        body.dark-mode tr:hover td {
            background: rgba(255, 255, 255, 0.04);
        }

        body.dark-mode input,
        body.dark-mode select {
            background: rgba(255, 255, 255, 0.08) !important;
            color: #e0e0e0 !important;
            border-color: rgba(255, 255, 255, 0.15) !important;
        }

        body.dark-mode .btn {
            background: rgba(102, 126, 234, 0.2) !important;
            color: #c0c8ff !important;
            border-color: rgba(102, 126, 234, 0.3) !important;
        }

        body.dark-mode .btn:hover {
            background: rgba(102, 126, 234, 0.4) !important;
        }

        body.dark-mode .game-link {
            background: rgba(255, 255, 255, 0.06) !important;
            color: #e0e0e0 !important;
            border-color: rgba(255, 255, 255, 0.08) !important;
        }

        body.dark-mode .game-link:hover {
            background: rgba(102, 126, 234, 0.25) !important;
        }

        body.dark-mode .personal-stat-label,
        body.dark-mode .stat-label {
            color: #888 !important;
        }

        body.dark-mode h1,
        body.dark-mode h2,
        body.dark-mode h3 {
            color: #e0e0e0;
        }

        /* ── Mobile Responsive Overrides ───────────────────── */
        @media (max-width: 900px) {
            .container {
                flex-direction: column;
                align-items: stretch;
                padding: 15px;
            }

            .info-column,
            .info,
            .ranking,
            .gift {
                max-width: 100%;
                width: 100%;
                margin-bottom: 20px;
            }

            .header {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
                text-align: center;
            }

            .stats-container {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
                gap: 10px;
            }

            .personal-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .game-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 500px) {
            .personal-stats-grid {
                grid-template-columns: 1fr;
            }

            .game-grid {
                grid-template-columns: 1fr;
            }

            .header .welcome {
                font-size: 1.2rem;
            }

            .menu-grid {
                grid-template-columns: 1fr;
            }

            .menu-category-title {
                grid-column: span 1;
            }
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <div class="header">
        <h1 class="welcome">Chào mừng, <span class="sparkle-text"><?php echo htmlspecialchars($user['Name']); ?></span>!
        </h1>
        <a href="preview_themes.php" class="theme-button" id="themeButton" title="Xem trước themes với full background">
            <span class="theme-icon">🎨</span>
            <span class="theme-text">Xem Themes</span>
        </a>
        <div class="daidien">
            <?php
            // Get user avatar and avatar frame (Optimized)
            // Use a single query to get everything if possible, but here we can keep it clean
            $avatarUrl = !empty($user['ImageURL']) ? htmlspecialchars($user['ImageURL']) : 'images.ico';
            $avatarFrameImage = null;

            // We already have avatar_frame_id in some cases, let's just make sure we get it
            $avatarSql = "SELECT u.ImageURL, af.ImageURL AS avatar_frame_image 
                              FROM users u 
                              LEFT JOIN avatar_frames af ON u.avatar_frame_id = af.id 
                              WHERE u.Iduser = ?";

            $avatarStmt = $conn->prepare($avatarSql);
            if ($avatarStmt) {
                $avatarStmt->bind_param("i", $userId);
                $avatarStmt->execute();
                $avatarResult = $avatarStmt->get_result();
                if ($avatarResult) {
                    $avatarData = $avatarResult->fetch_assoc();
                    if ($avatarData) {
                        $avatarUrl = !empty($avatarData['ImageURL']) ? htmlspecialchars($avatarData['ImageURL']) : 'images.ico';
                        $avatarFrameImage = !empty($avatarData['avatar_frame_image']) ? htmlspecialchars($avatarData['avatar_frame_image']) : null;
                    }
                }
                $avatarStmt->close();
            }
            ?>
            <div class="avatar-wrapper">
                <?php if ($avatarFrameImage): ?>
                    <div class="avatar-frame-overlay">
                        <img src="<?= $avatarFrameImage ?>" alt="Frame" onerror="this.style.display='none'">
                    </div>
                <?php endif; ?>
                <img src="<?= $avatarUrl ?>" alt="Ảnh đại diện"
                    style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;"
                    onerror="this.src='images.ico'">
            </div>
            <div class="dropdown-menu">
                <a href="in4.php"><i class="fa-solid fa-user icon"></i> Hồ sơ</a>
                <?php if (isset($user['Role']) && $user['Role'] == 1): ?>
                    <a href="admin_analytics.php"><i class="fa-solid fa-chart-line icon"></i> Thống Kê Website</a>
                    <a href="bot/index.php"><i class="fa-solid fa-robot icon"></i> Quản Lý Bot Army</a>
                <?php endif; ?>
                <a href="shop.php"><i class="fa-solid fa-store icon"></i> Cửa Hàng</a>
                <a href="achievements.php"><i class="fa-solid fa-trophy icon"></i> Danh Hiệu</a>
                <a href="select_title.php"><i class="fa-solid fa-crown icon"></i> Chọn Danh Hiệu</a>
                <a href="addimg.php"><i class="fa-solid fa-image icon"></i> Đổi ảnh đại diện</a>
                <a href="khungchat.php"><i class="fa-solid fa-comment icon"></i> Chọn Khung Chat</a>
                <a href="khungavatar.php"><i class="fa-solid fa-image icon"></i> Chọn Khung Avatar</a>
                <?php if (isset($user['Role']) && $user['Role'] == 1): ?>
                    <a href="admin_manage_frames.php"><i class="fa-solid fa-palette icon"></i> Admin - Quản Lý Khung</a>
                    <a href="admin_add_items.php"><i class="fa-solid fa-plus icon"></i> Admin - Thêm Items</a>
                    <a href="admin_manage_items.php"><i class="fa-solid fa-gear icon"></i> Admin - Quản Lý Items</a>
                    <a href="admin_manage_users.php"><i class="fa-solid fa-users-gear icon"></i> Admin - Quản Lý Users</a>
                    <a href="admin_fix_duplicates.php"><i class="fa-solid fa-broom icon"></i> Admin - Xử Lý Trùng Lặp</a>
                <?php endif; ?>
                <a href="#" id="darkModeToggle"><i class="fa-solid fa-moon icon"></i> Bật darkmode</a>
                <a href="login.php"><i class="fa-solid fa-right-from-bracket icon"></i> Đăng xuất</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="info-column">
            <!-- Live Clock -->
            <div class="live-clock">
                <div class="time" id="liveTime">--:--:--</div>
                <div class="date" id="liveDate">--/--/----</div>
            </div>

            <!-- Animated Statistics -->
            <div class="stats-container">
                <div class="stat-card tooltip" data-tooltip="Tổng số game có sẵn">
                    <div class="stat-icon">🎮</div>
                    <div class="stat-value" data-target="20">0</div>
                    <div class="stat-label">Game</div>
                </div>
                <div class="stat-card tooltip" data-tooltip="Số người trong bảng xếp hạng">
                    <div class="stat-icon">👥</div>
                    <div class="stat-value" data-target="<?= count($ranking) ?>">0</div>
                    <div class="stat-label">Người chơi</div>
                </div>
                <div class="stat-card tooltip" data-tooltip="Vị trí của bạn">
                    <div class="stat-icon">🏆</div>
                    <div class="stat-value" id="userRank">-</div>
                    <div class="stat-label">Xếp hạng</div>
                </div>
            </div>

            <!-- Personal Statistics Widget -->
            <div class="personal-stats-widget" id="personalStatsWidget">
                <div class="personal-stats-header">
                    <h3>📊 Thống Kê Cá Nhân</h3>
                    <a href="statistics.php" class="stats-view-all">Xem chi tiết →</a>
                </div>
                <div class="personal-stats-grid">
                    <div class="personal-stat-item">
                        <div class="personal-stat-icon">🎮</div>
                        <div class="personal-stat-content">
                            <div class="personal-stat-value" id="statTotalGames">
                                <?= number_format($personalStats['totalGames'], 0, ',', '.') ?>
                            </div>
                            <div class="personal-stat-label">Tổng game</div>
                        </div>
                    </div>
                    <div class="personal-stat-item">
                        <div class="personal-stat-icon">🏆</div>
                        <div class="personal-stat-content">
                            <div class="personal-stat-value" id="statWinRate"><?= $personalStats['winRate'] ?>%</div>
                            <div class="personal-stat-label">Tỷ lệ thắng</div>
                        </div>
                    </div>
                    <div class="personal-stat-item">
                        <div class="personal-stat-icon">💰</div>
                        <div class="personal-stat-content">
                            <div class="personal-stat-value" id="statTotalEarned">
                                <?= number_format($personalStats['totalEarned'], 0, ',', '.') ?>
                            </div>
                            <div class="personal-stat-label">Tổng kiếm được</div>
                        </div>
                    </div>
                    <div class="personal-stat-item">
                        <div class="personal-stat-icon">🎖️</div>
                        <div class="personal-stat-content">
                            <div class="personal-stat-value" id="statAchievements"><?= $personalStats['achievements'] ?>
                            </div>
                            <div class="personal-stat-label">Thành tích</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Menu Grid -->
            <div class="info">
                <h3>🛠️ Tiện Ích & Hệ Thống</h3>

                <div class="menu-grid">
                    <div class="menu-category-title">Khám phá</div>
                    <a href="about.php" class="menu-item tooltip" data-tooltip="Tìm hiểu thêm về trang web">
                        <span class="menu-icon">📘</span> Giới thiệu
                    </a>
                    <a href="social_feed.php" class="menu-item tooltip" data-tooltip="Xem hoạt động của cộng đồng">
                        <span class="menu-icon">📱</span> Bảng Tin
                    </a>
                    <a href="statistics.php" class="menu-item tooltip" data-tooltip="Xem thống kê chi tiết">
                        <span class="menu-icon">📊</span> Thống Kê
                    </a>
                    <a href="events.php" class="menu-item tooltip" data-tooltip="Tham gia các sự kiện đặc biệt">
                        <span class="menu-icon">🎉</span> Sự Kiện
                    </a>

                    <div class="menu-category-title">Nhiệm vụ & Thưởng</div>
                    <a href="quests.php" class="menu-item tooltip" data-tooltip="Hoàn thành nhiệm vụ">
                        <span class="menu-icon">🎯</span> Nhiệm Vụ
                    </a>
                    <a href="daily_challenges.php" class="menu-item tooltip" data-tooltip="Thử thách hàng ngày">
                        <span class="menu-icon">🎯</span> Thử Thách
                    </a>
                    <a href="streak_system.php" class="menu-item tooltip" data-tooltip="Chuỗi ngày chơi game">
                        <span class="menu-icon">🔥</span> Chuỗi
                    </a>
                    <a href="reward_points.php" class="menu-item tooltip" data-tooltip="Tích điểm đổi quà">
                        <span class="menu-icon">⭐</span> Điểm Thưởng
                    </a>
                    <a href="lucky_wheel.php" class="menu-item tooltip" data-tooltip="Quay wheel may mắn">
                        <span class="menu-icon">🎡</span> Lucky Wheel
                    </a>
                    <a href="daily_login.php" class="menu-item tooltip" data-tooltip="Nhận quà đăng nhập">
                        <span class="menu-icon">🎁</span> Điểm Danh
                    </a>

                    <div class="menu-category-title">Cửa hàng & Items</div>
                    <a href="shop.php" class="menu-item tooltip" data-tooltip="Mua theme và cursor đẹp">
                        <span class="menu-icon">🛒</span> Cửa Hàng
                    </a>
                    <a href="inventory.php" class="menu-item tooltip" data-tooltip="Quản lý items của bạn">
                        <span class="menu-icon">📦</span> Kho Đồ
                    </a>
                    <a href="marketplace.php" class="menu-item tooltip" data-tooltip="Mua bán và trao đổi items">
                        <span class="menu-icon">💼</span> Chợ
                    </a>
                    <a href="gift.php" class="menu-item tooltip" data-tooltip="Tặng quà cho người khác">
                        <span class="menu-icon">🎁</span> Tặng Quà
                    </a>

                    <div class="menu-category-title">Xã hội & Cạnh tranh</div>
                    <a href="chat.php" class="menu-item tooltip" data-tooltip="Trò chuyện với mọi người">
                        <span class="menu-icon">💬</span> Chat Tổng
                    </a>
                    <a href="guilds.php" class="menu-item tooltip" data-tooltip="Tham gia guild">
                        <span class="menu-icon">🏆</span> Guild
                    </a>
                    <a href="pvp_challenge.php" class="menu-item tooltip" data-tooltip="Thách đấu PvP">
                        <span class="menu-icon">⚔️</span> Đấu PvP
                    </a>
                    <a href="leaderboard.php" class="menu-item tooltip" data-tooltip="Bảng xếp hạng người chơi">
                        <span class="menu-icon">🏆</span> Xếp Hạng
                    </a>
                    <a href="tournament.php" class="menu-item tooltip" data-tooltip="Tham gia giải đấu">
                        <span class="menu-icon">🎯</span> Giải Đấu
                    </a>
                    <a href="trivia.php" class="menu-item tooltip" data-tooltip="Trắc nghiệm kiến thức">
                        <span class="menu-icon">📚</span> Trivia
                    </a>

                    <div class="menu-category-title">Tài khoản & Tùy chỉnh</div>
                    <a href="profile.php" class="menu-item tooltip" data-tooltip="Xem hồ sơ của bạn">
                        <span class="menu-icon">👤</span> Hồ Sơ
                    </a>
                    <a href="select_title.php" class="menu-item tooltip" data-tooltip="Chọn danh hiệu">
                        <span class="menu-icon">👑</span> Danh Hiệu
                    </a>
                    <a href="khungchat.php" class="menu-item tooltip" data-tooltip="Chọn khung chat">
                        <span class="menu-icon">🎨</span> Khung Chat
                    </a>
                    <a href="khungavatar.php" class="menu-item tooltip" data-tooltip="Chọn khung avatar">
                        <span class="menu-icon">🖼️</span> Khung Avatar
                    </a>
                    <a href="addimg.php" class="menu-item tooltip" data-tooltip="Đổi ảnh đại diện">
                        <span class="menu-icon">📸</span> Đổi Ảnh
                    </a>
                    <a href="notifications.php" class="menu-item tooltip" id="notificationsLink"
                        data-tooltip="Xem thông báo">
                        <span class="menu-icon">🔔</span> Thông Báo <span id="notificationsBadge"
                            style="display:none; padding:2px 6px; border-radius:999px; background:#e74c3c; color:#fff; font-size:11px; font-weight:700;">0</span>
                    </a>
                </div>

                <h1 style="font-size: 18px; margin: 30px 0 10px; color: var(--warning-color); text-align: center;">⚠️
                    Vui lòng đọc kỹ trước khi chơi</h1>
            </div>

            <!-- Checkin and Mini Events -->
            <div class="info">
                <div class="daily-checkin">
                    <h2>📅 Điểm danh mỗi ngày nhận quà!</h2>
                    <form method="post" action="diemdanh.php">
                        <button type="submit">✅ Điểm danh ngay</button>
                    </form>
                    <?php if (isset($_SESSION['msg'])): ?>
                        <p style="color: green; font-weight: bold; margin-top: 10px;">
                            <?php echo htmlspecialchars($_SESSION['msg'], ENT_QUOTES, 'UTF-8');
                            unset($_SESSION['msg']); ?>
                        </p>
                    <?php endif; ?>
                    <h2 style="margin-top: 20px;">Cào Thẻ Test Nhân Phẩm Hằng Ngày!</h2>
                    <p><a href="caothe.php" class="btn" style="width: 100%; text-align: center;">Cào nhẹ tay, quà đầy
                            tay!</a></p>
                </div>
            </div>

            <!-- Giftcode Section -->
            <div class="gift">
                <h3>🎁 Nhập Giftcode Nhận Quà</h3>
                <form method="post">
                    <input type="text" name="giftcode" placeholder="Nhập mã quà tặng..." required>
                    <button type="submit" name="submit_giftcode">Nhận quà</button>
                </form>
                <?php if (isset($giftMessage)): ?>
                    <?= $giftMessage ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cột thông tin người dùng và menu -->
        <div class="info">
            <div class="balance-display" id="balanceDisplay">
                💰 Số Gtlm: <span class="balance-value"
                    data-balance="<?= $user['Money'] ?>"><?php echo number_format($user['Money'], 0, ',', '.'); ?></span>
                gtlm
                <?php if (!empty($userProgress)): ?>
                    <div style="margin-top: 10px; font-size: 14px; color: #333;">
                        🔥 Level: <strong><?= (int) $userProgress['level'] ?></strong>
                        &nbsp;•&nbsp;
                        XP: <strong><?= (int) $userProgress['xp'] ?></strong>
                        &nbsp;•&nbsp;
                        Streak đăng nhập: <strong><?= (int) $userProgress['login_streak'] ?></strong> ngày (tốt nhất:
                        <?= (int) $userProgress['best_login_streak'] ?>)
                        &nbsp;•&nbsp;
                        <a href="leaderboard.php"
                            style="color: var(--secondary-dark); font-weight: 600; text-decoration: underline; cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important; position: relative; z-index: 10; pointer-events: auto !important; display: inline-block;">
                            Xem bảng xếp hạng
                        </a>
                    </div>
                    <div class="season-pass">
                        <div class="season-pass-header">
                            <span>🎟 Season Progress</span>
                            <span><?= $seasonProgressPercent ?>% ・ Level <?= $seasonLevel ?></span>
                        </div>
                        <div class="season-pass-bar">
                            <span style="width: <?= $seasonProgressPercent ?>%;"></span>
                        </div>
                        <div style="margin-top: 4px; font-size: 12px; color: #555;">
                            <?= $seasonXp ?> / <?= $seasonRequiredXp ?> XP
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($referralCode)): ?>
                <div
                    style="background: rgba(255, 255, 255, 0.95); padding: 15px; border-radius: var(--border-radius-lg); margin: 20px 0; font-size: 14px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                    <strong>🤝 Mời bạn bè cùng chơi</strong><br>
                    Mã giới thiệu của bạn: <code><?= htmlspecialchars($referralCode, ENT_QUOTES, 'UTF-8') ?></code><br>
                    Link mời nhanh:
                    <input type="text" readonly
                        value="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/auth.php?ref=' . $referralCode, ENT_QUOTES, 'UTF-8') ?>"
                        style="width: 100%; margin-top: 6px; padding: 6px 8px; border-radius: var(--border-radius); border: 1px solid var(--border-color); font-size: 12px;"
                        onclick="this.select();">
                    <small>✨ Bạn và bạn bè sẽ nhận thưởng coin khi hoàn tất đăng ký qua link này.</small>

                    <div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid rgba(102, 126, 234, 0.2);">
                        <a href="pvp_challenge.php"
                            style="display: block; padding: 12px 20px; background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; text-decoration: none; border-radius: var(--border-radius); font-weight: 600; text-align: center; transition: all 0.3s ease; cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;">
                            ⚔️ Thách Đấu PvP 1-1
                        </a>
                        <small
                            style="display: block; margin-top: 8px; text-align: center; color: var(--text-dark); opacity: 0.8;">Đấu
                            1-1 với bạn bè và giành chiến thắng!</small>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (defined('UP_EVENT_ACTIVE') && UP_EVENT_ACTIVE): ?>
                <div
                    style="margin-top: 15px; padding: 12px 16px; border-radius: var(--border-radius); background: rgba(255, 193, 7, 0.15); border: 1px solid rgba(255, 193, 7, 0.6); font-size: 14px;">
                    <strong>🎉 Sự kiện đang diễn ra:</strong>
                    <?= htmlspecialchars(UP_EVENT_NAME, ENT_QUOTES, 'UTF-8') ?><br>
                    <span>💎 Thưởng đăng nhập và hoạt động được nhân <?= UP_EVENT_REWARD_MULTIPLIER ?> lần.
                        <?= htmlspecialchars(UP_EVENT_LOGIN_BONUS_TEXT, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <!-- Hiển thị danh hiệu hiện tại -->
            <?php if (!empty($user['title_icon'])): ?>
                <div
                    style="background: rgba(255, 255, 255, 0.95); padding: 15px; border-radius: var(--border-radius-lg); margin: 20px 0; text-align: center; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); animation: fadeInUp 0.6s ease;">
                    <div style="font-size: 32px; margin-bottom: 10px;">
                        <?= htmlspecialchars($user['title_icon'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div style="font-weight: 700; color: var(--primary-color); font-size: 18px;">
                        <?= htmlspecialchars($user['title_name'] ?? 'Danh hiệu', ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div style="font-size: 14px; color: var(--text-dark); margin-top: 5px;">
                        Xếp hạng: #<?= $userRank ?>
                    </div>
                    <a href="select_title.php"
                        style="display: inline-block; margin-top: 10px; padding: 8px 16px; background: var(--secondary-color); color: white; text-decoration: none; border-radius: var(--border-radius); font-size: 14px; font-weight: 600; cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;">
                        Đổi danh hiệu
                    </a>
                </div>
            <?php else: ?>
                <div
                    style="background: rgba(255, 255, 255, 0.95); padding: 15px; border-radius: var(--border-radius-lg); margin: 20px 0; text-align: center; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); animation: fadeInUp 0.6s ease;">
                    <div style="font-size: 24px; margin-bottom: 10px;">🏆</div>
                    <div style="font-weight: 700; color: var(--text-dark); font-size: 16px; margin-bottom: 10px;">
                        Chưa có danh hiệu
                    </div>
                    <div style="font-size: 14px; color: var(--text-dark); margin-bottom: 10px;">
                        Xếp hạng: #<?= $userRank ?>
                        <?php if ($userRank <= 10): ?>
                            <br><span style="color: var(--success-color); font-weight: 600;">✨ Bạn đang trong top 10! Hãy vào
                                trang Danh Hiệu để nhận!</span>
                        <?php else: ?>
                            <br><span style="color: var(--warning-color);">Cố gắng lên top 10 để nhận danh hiệu!</span>
                        <?php endif; ?>
                    </div>
                    <a href="select_title.php"
                        style="display: inline-block; margin-top: 5px; padding: 8px 16px; background: var(--secondary-color); color: white; text-decoration: none; border-radius: var(--border-radius); font-size: 14px; font-weight: 600; cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;">
                        Chọn danh hiệu
                    </a>
                </div>
            <?php endif; ?>


            <!-- Modern Game Lobby -->
            <div class="hero-slider">
                <div class="slide active">
                    <div class="slide-content">
                        <h2>🎡 Vòng Quay May Mắn</h2>
                        <p>Thử vận may mỗi ngày để nhận hàng triệu GTLM!</p>
                        <a href="lucky_wheel.php" class="btn"
                            style="margin-top: 15px; background: var(--accent-gold); color: #000;">Chơi Ngay</a>
                    </div>
                    <div class="slide-img" style="font-size: 100px;">🎡</div>
                </div>
                <div class="slide">
                    <div class="slide-content">
                        <h2>🏆 Giải Đấu Ranking</h2>
                        <p>Đua top nhận danh hiệu và khung avatar độc quyền.</p>
                        <a href="tournament.php" class="btn"
                            style="margin-top: 15px; background: var(--accent-blue); color: #000;">Tham Gia</a>
                    </div>
                    <div class="slide-img" style="font-size: 100px;">🏅</div>
                </div>
            </div>

            <div class="lobby-tabs">
                <button class="tab-btn active" data-category="all">Tất cả</button>
                <button class="tab-btn" data-category="card">Game Bài</button>
                <button class="tab-btn" data-category="slots">Slots & Quay Số</button>
                <button class="tab-btn" data-category="mini">Mini Games</button>
            </div>

            <div class="game-grid-modern">
                <!-- Game Bài (Card Games) -->
                <a href="games/blackjack.php" class="game-card" data-category="card">
                    <span class="game-badge badge-hot">Hot</span>
                    <span class="game-icon">👑</span>
                    <span class="game-name">Xì Dách Royale</span>
                </a>
                <a href="games/bjo.php" class="game-card" data-category="card">
                    <span class="game-icon">👑</span>
                    <span class="game-name">Bj Cũ</span>
                </a>
                <a href="games/poker.php" class="game-card" data-category="card">
                    <span class="game-icon">🃏</span>
                    <span class="game-name">Poker Texas</span>
                </a>
                <a href="games/baccarat.php" class="game-card" data-category="card">
                    <span class="game-icon">🃏</span>
                    <span class="game-name">Baccarat Premium</span>
                </a>
                <a href="games/dragontiger.php" class="game-card" data-category="card">
                    <span class="game-icon">🐉</span>
                    <span class="game-name">Long Hổ</span>
                </a>
                <a href="games/threecard.php" class="game-card" data-category="card">
                    <span class="game-icon">🃏</span>
                    <span class="game-name">Three Card Poker</span>
                </a>
                <a href="games/war.php" class="game-card" data-category="card">
                    <span class="game-icon">⚔️</span>
                    <span class="game-name">Casino War</span>
                </a>
                <a href="games/letitride.php" class="game-card" data-category="card">
                    <span class="game-icon">🃏</span>
                    <span class="game-name">Let It Ride</span>
                </a>
                <a href="games/paigow.php" class="game-card" data-category="card">
                    <span class="game-icon">🃏</span>
                    <span class="game-name">Pai Gow Poker</span>
                </a>
                <a href="games/caribbean.php" class="game-card" data-category="card">
                    <span class="game-icon">🏖️</span>
                    <span class="game-name">Caribbean Stud</span>
                </a>
                <a href="games/holdem.php" class="game-card" data-category="card">
                    <span class="game-icon">🃏</span>
                    <span class="game-name">Casino Hold'em</span>
                </a>
                <a href="games/pontoon.php" class="game-card" data-category="card">
                    <span class="game-icon">🃏</span>
                    <span class="game-name">Pontoon Royale</span>
                </a>
                <a href="games/reddog.php" class="game-card" data-category="card">
                    <span class="game-icon">🐕</span>
                    <span class="game-name">Red Dog Poker</span>
                </a>
                <a href="games/videopoker.php" class="game-card" data-category="card">
                    <span class="game-icon">🎰</span>
                    <span class="game-name">Video Poker</span>
                </a>
                <a href="games/bj.php" class="game-card" data-category="card">
                    <span class="game-icon">🃏</span>
                    <span class="game-name">Xì Dách Classic</span>
                </a>

                <!-- Slots & Quay Số (Slots & Luck) -->
                <a href="games/slot.php" class="game-card" data-category="slots">
                    <span class="game-badge badge-new">New</span>
                    <span class="game-icon">🎰</span>
                    <span class="game-name">Slot Machine</span>
                </a>
                <a href="games/roulette.php" class="game-card" data-category="slots">
                    <span class="game-icon">🎡</span>
                    <span class="game-name">Roulette</span>
                </a>
                <a href="games/vq.php" class="game-card" data-category="slots">
                    <span class="game-icon">🎡</span>
                    <span class="game-name">Vòng Quay</span>
                </a>
                <a href="games/vietlott.php" class="game-card" data-category="slots">
                    <span class="game-icon">🎫</span>
                    <span class="game-name">Vietlott</span>
                </a>
                <a href="games/keno.php" class="game-card" data-category="slots">
                    <span class="game-icon">🎱</span>
                    <span class="game-name">Keno Premium</span>
                </a>
                <a href="games/bingo.php" class="game-card" data-category="slots">
                    <span class="game-icon">🎱</span>
                    <span class="game-name">Bingo Club</span>
                </a>
                <a href="games/ruttham.php" class="game-card" data-category="slots">
                    <span class="game-icon">🎟️</span>
                    <span class="game-name">Rút Thăm</span>
                </a>

                <!-- Mini Games & Casual -->
                <a href="games/baucua.php" class="game-card" data-category="mini">
                    <span class="game-badge badge-hot">Hot</span>
                    <span class="game-icon">🎲</span>
                    <span class="game-name">CYBER PETS</span>
                </a>
                <a href="games/xocdia.php" class="game-card" data-category="mini">
                    <span class="game-icon">🎲</span>
                    <span class="game-name">QUANTUM PULSE</span>
                </a>
                <a href="games/crash.php" class="game-card" data-category="mini">
                    <span class="game-badge badge-hot">Hot</span>
                    <span class="game-icon">🛫</span>
                    <span class="game-name">Crash Flight</span>
                </a>
                <a href="games/plinko.php" class="game-card" data-category="mini">
                    <span class="game-icon">🔴</span>
                    <span class="game-name">Plinko Royale</span>
                </a>
                <a href="games/limbo.php" class="game-card" data-category="mini">
                    <span class="game-icon">🚀</span>
                    <span class="game-name">Limbo Rocket</span>
                </a>
                <a href="games/mines.php" class="game-card" data-category="mini">
                    <span class="game-icon">💣</span>
                    <span class="game-name">Mines Premium</span>
                </a>
                <a href="games/minesweeper.php" class="game-card" data-category="mini">
                    <span class="game-icon">💣</span>
                    <span class="game-name">Dò Mìn Classic</span>
                </a>
                <a href="games/tower.php" class="game-card" data-category="mini">
                    <span class="game-icon">🗼</span>
                    <span class="game-name">Tower Climb</span>
                </a>
                <a href="games/scratch.php" class="game-card" data-category="mini">
                    <span class="game-icon">🎫</span>
                    <span class="game-name">Cào Thẻ</span>
                </a>
                <a href="games/dice.php" class="game-card" data-category="mini">
                    <span class="game-icon">🎲</span>
                    <span class="game-name">Lắc Xí Ngầu</span>
                </a>
                <a href="games/sicbo.php" class="game-card" data-category="mini">
                    <span class="game-icon">🎲</span>
                    <span class="game-name">Sic Bo</span>
                </a>
                <a href="games/craps.php" class="game-card" data-category="mini">
                    <span class="game-icon">🎲</span>
                    <span class="game-name">Craps</span>
                </a>
                <a href="games/fantan.php" class="game-card" data-category="mini">
                    <span class="game-icon">🔘</span>
                    <span class="game-name">Fan-Tan</span>
                </a>
                <a href="games/mahjong.php" class="game-card" data-category="mini">
                    <span class="game-icon">🀄</span>
                    <span class="game-name">Mahjong</span>
                </a>
                <a href="games/hilo.php" class="game-card" data-category="mini">
                    <span class="game-icon">📈</span>
                    <span class="game-name">Hi-Lo</span>
                </a>
                <a href="games/yahtzee.php" class="game-card" data-category="mini">
                    <span class="game-icon">🎲</span>
                    <span class="game-name">Yahtzee</span>
                </a>
                <a href="games/coinflip.php" class="game-card" data-category="mini">
                    <span class="game-icon">🪙</span>
                    <span class="game-name">Tung Đồng Xu</span>
                </a>
                <a href="games/rps.php" class="game-card" data-category="mini">
                    <span class="game-icon">✌️</span>
                    <span class="game-name">Oẳn Tù Tì</span>
                </a>
                <a href="games/number.php" class="game-card" data-category="mini">
                    <span class="game-icon">🎯</span>
                    <span class="game-name">Đoán Số</span>
                </a>
                <a href="games/duangua.php" class="game-card" data-category="mini">
                    <span class="game-icon">🐎</span>
                    <span class="game-name">Đua Thú</span>
                </a>
                <a href="bot.php" class="game-card" data-category="mini">
                    <span class="game-icon">🎴</span>
                    <span class="game-name">Đoán Màu Bài</span>
                </a>
                <a href="games/ac.php" class="game-card" data-category="mini">
                    <span class="game-icon">🎯</span>
                    <span class="game-name">Arcade</span>
                </a>
                <a href="games/cs.php" class="game-card" data-category="mini">
                    <span class="game-icon">💎</span>
                    <span class="game-name">Triệu Phú</span>
                </a>
                <a href="games/hopmu.php" class="game-card" data-category="mini">
                    <span class="game-icon">🎁</span>
                    <span class="game-name">Hộp Mú</span>
                </a>
            </div>
        </div>

        <!-- Cột bảng xếp hạng -->
        <div class="ranking">
            <h2>🏆 Top những người đẹp trai trên GTLM</h2>
            <table>
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Ảnh</th>
                        <th>Tên</th>
                        <th>Số Gtlm (GTLM)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($ranking)): ?>
                        <?php foreach ($ranking as $index => $r): ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--primary-color);"><?= $index + 1 ?></td>
                                <td>
                                    <div class="avatar-border"
                                        style="position: relative; width: 50px; height: 50px; margin: 0 auto;">
                                        <?php
                                        // Get avatar frame for ranking user (Optimized)
                                        $rankFrameImage = null;
                                        if (isset($r['avatar_frame_id']) && !empty($r['avatar_frame_id'])) {
                                            $rankFrameSql = "SELECT af.ImageURL FROM avatar_frames af WHERE af.id = ?";
                                            $rankFrameStmt = $conn->prepare($rankFrameSql);
                                            if ($rankFrameStmt) {
                                                $rankFrameStmt->bind_param("i", $r['avatar_frame_id']);
                                                $rankFrameStmt->execute();
                                                $rankFrameResult = $rankFrameStmt->get_result();
                                                if ($rankFrameResult) {
                                                    $rankFrameRow = $rankFrameResult->fetch_assoc();
                                                    if ($rankFrameRow) {
                                                        $rankFrameImage = $rankFrameRow['ImageURL'];
                                                    }
                                                }
                                                $rankFrameStmt->close();
                                            }
                                        }
                                        ?>
                                        <?php if ($rankFrameImage): ?>
                                            <div
                                                style="position: absolute; top: -5px; left: -5px; width: calc(100% + 10px); height: calc(100% + 10px); z-index: 1; pointer-events: none !important; border-radius: 50%;">
                                                <img src="<?= htmlspecialchars($rankFrameImage, ENT_QUOTES, 'UTF-8') ?>" alt="Frame"
                                                    style="width: 100%; height: 100%; object-fit: contain; border-radius: 50%; pointer-events: none !important;"
                                                    onerror="this.style.display='none'">
                                            </div>
                                        <?php endif; ?>
                                        <?php
                                        $avatarPath = !empty($r['ImageURL']) ? $r['ImageURL'] : 'images.ico';
                                        ?>
                                        <img src="<?= htmlspecialchars($avatarPath, ENT_QUOTES, 'UTF-8') ?>"
                                            alt="<?= htmlspecialchars($r['Name'], ENT_QUOTES, 'UTF-8') ?>"
                                            style="position: relative; z-index: 2; width: 100%; height: 100%; border-radius: 50%; object-fit: cover; pointer-events: auto;"
                                            onerror="this.src='images.ico'">
                                    </div>
                                </td>
                                <td style="font-weight: 600;">
                                    <?php
                                    $sparkleClass = '';
                                    if ($index === 0)
                                        $sparkleClass = 'sparkle-gold';
                                    elseif ($index < 3)
                                        $sparkleClass = 'sparkle-text';
                                    ?>
                                    <?php if (!empty($r['title_icon'])): ?>
                                        <span style="font-size: 20px; margin-right: 5px;"
                                            title="<?= htmlspecialchars($r['title_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($r['title_icon'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php endif; ?>
                                    <span
                                        class="<?= $sparkleClass ?>"><?= htmlspecialchars($r['Name'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td style="color: var(--success-color); font-weight: 700;"
                                    title="<?= number_format($r['Money'], 0, ',', '.') ?> gtlm">
                                    <?= number_format($r['Money'], 0, ',', '.') ?> gtlm
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">Không có dữ liệu xếp hạng!</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

        </div>

    </div>

    <!-- Quick Links Section -->
    <div class="quick-links" style="max-width: 1400px; margin: 30px auto; padding: 0 20px; clear: both;">
        <h2>⚡ Truy Cập Nhanh</h2>
        <div class="quick-links-grid">
            <a href="weekly_challenges.php" class="quick-link-card">
                <span class="quick-link-icon">📅</span>
                <div class="quick-link-title">Thử Thách Tuần</div>
                <div class="quick-link-desc">Hoàn thành nhiệm vụ tuần để nhận thưởng lớn</div>
            </a>
            <a href="daily_challenges.php" class="quick-link-card">
                <span class="quick-link-icon">🎯</span>
                <div class="quick-link-title">Nhiệm Vụ Ngày</div>
                <div class="quick-link-desc">Hoàn thành nhiệm vụ hàng ngày để kiếm thêm xu</div>
            </a>
            <a href="leaderboard.php" class="quick-link-card">
                <span class="quick-link-icon">🏆</span>
                <div class="quick-link-title">Bảng Xếp Hạng</div>
                <div class="quick-link-desc">Xem vị trí của bạn và so sánh với người chơi khác</div>
            </a>
            <a href="achievements.php" class="quick-link-card">
                <span class="quick-link-icon">🎖️</span>
                <div class="quick-link-title">Thành Tích</div>
                <div class="quick-link-desc">Xem và mở khóa các thành tích mới</div>
            </a>
            <a href="shop.php" class="quick-link-card">
                <span class="quick-link-icon">🛒</span>
                <div class="quick-link-title">Cửa Hàng</div>
                <div class="quick-link-desc">Mua themes, cursors và items độc đáo</div>
            </a>
            <a href="marketplace.php" class="quick-link-card">
                <span class="quick-link-icon">💼</span>
                <div class="quick-link-title">Chợ Trao Đổi</div>
                <div class="quick-link-desc">Mua bán items với người chơi khác</div>
            </a>
            <a href="pvp_challenge.php" class="quick-link-card">
                <span class="quick-link-icon">⚔️</span>
                <div class="quick-link-title">Thách Đấu PvP</div>
                <div class="quick-link-desc">Đấu 1-1 với người chơi khác và giành chiến thắng</div>
            </a>
        </div>
    </div>

    <!-- Floating Action Button -->
    <button class="fab" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" title="Lên đầu trang">↑</button>
    <button class="messages-fab" id="messagesFab" title="Tin nhắn riêng">
        💬
        <span class="badge" id="messagesBadge">0</span>
    </button>

    <!-- Confetti Container -->
    <div class="confetti-container" id="confettiContainer"></div>

    <!-- Server Notification Banner -->
    <div class="server-notification" id="serverNotification">
        <button class="close-btn" onclick="closeNotification()">×</button>
        <div id="notificationMessage"></div>
    </div>








    <!-- Premium Effects System -->
    <canvas id="threejs-background"></canvas>
    <script src="assets/js/sound-manager.js"></script>
    <script src="assets/js/lobby.js"></script>
    <script>
        (function () {
            window.themeConfig = {
                particleCount: <?= $particleCount ?? 800 ?>,
                particleSize: <?= $particleSize ?? 0.05 ?>,
                particleColor: '<?= $particleColor ?? "#ffffff" ?>',
                particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
                shapeCount: <?= $shapeCount ?? 10 ?>,
                shapeColors: <?= json_encode($shapeColors ?? ["#667eea", "#764ba2", "#4facfe", "#00f2fe"]) ?>,
                shapeOpacity: <?= $shapeOpacity ?? 0.3 ?>,
                bgGradient: <?= json_encode($bgGradient ?? ["#667eea", "#764ba2", "#4facfe"]) ?>
            };
            const prefix = window.location.pathname.includes('/games/') ? '../' : '';
            const scripts = ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'];

            scripts.forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src;
                s.async = false;
                document.head.appendChild(s);
            });
        })();
    </script>

    <!-- Live Clock + Stat Counters + Rank -->
    <script>
        (function () {
            // ── Đồng hồ sống ──────────────────────────────────────
            function updateClock() {
                const now = new Date();
                const pad = n => String(n).padStart(2, '0');
                const timeEl = document.getElementById('liveTime');
                const dateEl = document.getElementById('liveDate');
                if (timeEl) timeEl.textContent = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
                if (dateEl) {
                    const days = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
                    const months = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];
                    dateEl.textContent = days[now.getDay()] + ' ' + pad(now.getDate()) + '/' + months[now.getMonth()] + '/' + now.getFullYear();
                }
            }
            updateClock();
            setInterval(updateClock, 1000);

            // ── Xếp hạng từ PHP ───────────────────────────────────
            const rankEl = document.getElementById('userRank');
            if (rankEl) rankEl.textContent = '#<?= (int) $userRank ?>';

            // ── Animated counter (data-target) ────────────────────
            function animateCounter(el, target, duration) {
                if (!el || isNaN(target)) return;
                const start = 0;
                const startTime = performance.now();
                function step(now) {
                    const elapsed = now - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    // easeOutExpo
                    const eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
                    el.textContent = Math.round(eased * target).toLocaleString('vi-VN');
                    if (progress < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            }

            // Chạy counters khi element vào viewport
            const counters = document.querySelectorAll('.stat-value[data-target]');
            if ('IntersectionObserver' in window) {
                const obs = new IntersectionObserver(entries => {
                    entries.forEach(e => {
                        if (e.isIntersecting) {
                            animateCounter(e.target, parseInt(e.target.dataset.target), 1800);
                            obs.unobserve(e.target);
                        }
                    });
                }, { threshold: 0.3 });
                counters.forEach(el => obs.observe(el));
            } else {
                counters.forEach(el => animateCounter(el, parseInt(el.dataset.target), 1800));
            }
        })();
    </script>

    <!-- Dark Mode + Notifications + Messages -->
    <script>
        (function () {
            // ── Dark Mode ──────────────────────────────────────────
            const DARK_KEY = 'gtlm_dark';
            function applyDark(on) {
                document.body.classList.toggle('dark-mode', on);
                const toggle = document.getElementById('darkModeToggle');
                if (toggle) {
                    toggle.innerHTML = on
                        ? '<i class="fa-solid fa-sun icon"></i> Tắt darkmode'
                        : '<i class="fa-solid fa-moon icon"></i> Bật darkmode';
                }
            }
            // Restore saved preference
            applyDark(localStorage.getItem(DARK_KEY) === '1');
            document.addEventListener('click', function (e) {
                const t = e.target.closest('#darkModeToggle');
                if (!t) return;
                e.preventDefault();
                const on = !document.body.classList.contains('dark-mode');
                localStorage.setItem(DARK_KEY, on ? '1' : '0');
                applyDark(on);
            });

            // ── Notification Badge ────────────────────────────────
            function fetchNotifCount() {
                fetch('api_get_notifications.php?limit=20')
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'success') {
                            const cnt = (data.notifications || []).length;
                            const badge = document.getElementById('notificationsBadge');
                            if (badge) {
                                badge.textContent = cnt;
                                badge.style.display = cnt > 0 ? 'inline-block' : 'none';
                                if (cnt > 0) {
                                    badge.classList.remove('pulse');
                                    void badge.offsetWidth;
                                    badge.classList.add('pulse');
                                }
                            }
                        }
                    }).catch(() => { });
            }
            fetchNotifCount();
            setInterval(fetchNotifCount, 30000); // Poll mỗi 30s

            // ── Messages FAB ───────────────────────────────────────
            const msgFab = document.getElementById('messagesFab');
            if (msgFab) {
                msgFab.addEventListener('click', () => {
                    window.location.href = 'private_message.php';
                });
            }
        })();
    </script>

</body>






</html>