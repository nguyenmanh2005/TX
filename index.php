<?php
session_start();

// Kiểm tra nếu người dùng đã bật giao diện V2 hoặc V3
if (isset($_COOKIE['use_new_ui'])) {
    if ($_COOKIE['use_new_ui'] == '1' || $_COOKIE['use_new_ui'] == '2') {
        header("Location: v2/index.html");
        exit();
    } elseif ($_COOKIE['use_new_ui'] == '3') {
        header("Location: v3/index.php");
        exit();
    }
}

// Kiểm tra đăng nhập: nếu chưa đăng nhập thì chuyển về trang đăng nhập
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';
require_once 'user_progress_helper.php';
require_once 'referral_helper.php';
require_once 'api_event_helper.php';

// Kiểm tra và kích hoạt ngẫu nhiên Flash Event chớp nhoáng (tối đa 2 lần/ngày)
EventHelper::checkOrTriggerFlashEvent($conn);

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

// Lấy tổng số người chơi thực tế từ database
$totalUsers = 0;
$userCountResult = $conn->query("SELECT COUNT(*) AS total FROM users");
if ($userCountResult) {
    $row = $userCountResult->fetch_assoc();
    $totalUsers = (int) $row['total'];
}

// Tính tổng số game có sẵn từ file index.php
$totalGames = 0;
if (file_exists(__FILE__)) {
    $indexContent = file_get_contents(__FILE__);
    $totalGames = substr_count($indexContent, 'class="game-card"');
}
if ($totalGames <= 0) {
    $totalGames = 61; // Fallback to 61 if file read fails
}

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
            cursor: url('img/chuot.png'), auto !important;
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
            cursor: url('img/tay.png'), pointer !important;
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

        /* Floating Guild Chat */
        .guild-chat-widget {
            position: fixed;
            bottom: 90px;
            right: 30px;
            width: 320px;
            height: 450px;
            background: rgba(26, 26, 46, 0.98);
            backdrop-filter: blur(15px);
            border: 2px solid rgba(102, 126, 234, 0.3);
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            z-index: 9999;
            box-shadow: 0 15px 40px rgba(0,0,0,0.4);
            transition: all 0.3s ease;
            transform: translateY(0);
        }

        .guild-chat-widget.minimized {
            height: 45px;
            transform: translateY(0);
        }

        .guild-chat-header {
            padding: 12px 15px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: #fff;
            border-radius: 13px 13px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }

        .guild-chat-messages {
            flex: 1;
            padding: 10px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .guild-chat-messages::-webkit-scrollbar { width: 5px; }
        .guild-chat-messages::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 10px; }

        .gm-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 13px;
        }

        .gm-user { font-weight: bold; color: #f1c40f; margin-bottom: 3px; }
        .gm-text { color: #ecf0f1; line-height: 1.4; }

        .guild-chat-input-area {
            padding: 10px;
            display: flex;
            gap: 5px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .guild-chat-input-area input {
            flex: 1;
            background: rgba(255,255,255,0.1) !important;
            border: none !important;
            color: #fff !important;
            padding: 8px 12px !important;
            border-radius: 20px !important;
            font-size: 13px !important;
        }

                /* Notifications Dropdown */
        .notification-container {
            position: relative;
            display: inline-block;
        }

        .notif-bell {
            font-size: 1.5em;
            color: #fff;
            cursor: pointer;
            position: relative;
            padding: 10px;
            transition: transform 0.3s;
        }

        .notif-bell:hover { transform: scale(1.1); }

        .notif-badge {
            position: absolute;
            top: 5px; right: 5px;
            background: #e74c3c;
            color: #fff;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.6em;
            font-weight: bold;
            display: none;
            border: 2px solid #1a1a2e;
        }

        .notif-dropdown {
            display: none;
            position: absolute;
            top: 50px; right: 0;
            width: 350px;
            background: rgba(26, 26, 46, 0.98);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.5);
            z-index: 10001;
            overflow: hidden;
            animation: slideInDown 0.3s ease;
        }

        .notif-header {
            padding: 15px;
            background: rgba(255,255,255,0.05);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notif-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .notif-item {
            padding: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            transition: background 0.3s;
            cursor: pointer;
        }

        .notif-item:hover { background: rgba(255,255,255,0.05); }
        .notif-item.unread { background: rgba(52, 152, 219, 0.05); }

        .notif-title { font-weight: bold; font-size: 0.95em; color: #4facfe; margin-bottom: 5px; }
        .notif-msg { font-size: 0.85em; color: #ccc; line-height: 1.4; }
        .notif-time { font-size: 0.75em; color: #666; margin-top: 8px; }

        /* Global Jackpot Banner */
        .jackpot-banner {
            background: linear-gradient(135deg, #f1c40f 0%, #d35400 100%);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(241, 196, 15, 0.4);
            border: 3px solid rgba(255, 255, 255, 0.2);
            animation: pulse-glow 2s infinite alternate;
        }

        @keyframes pulse-glow {
            from { box-shadow: 0 10px 30px rgba(241, 196, 15, 0.3); }
            to { box-shadow: 0 10px 60px rgba(241, 196, 15, 0.6); }
        }

        .jackpot-label {
            font-size: 1em;
            font-weight: 800;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 5px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .jackpot-amount {
            font-size: 4em;
            font-weight: 900;
            color: #fff;
            font-family: 'Courier New', Courier, monospace;
            text-shadow: 0 0 20px #000;
            margin: 5px 0;
        }

        .jackpot-winner {
            font-size: 0.9em;
            background: rgba(0,0,0,0.3);
            display: inline-block;
            padding: 5px 20px;
            border-radius: 20px;
            color: #fff;
        }

        .jackpot-coins {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none;
            background: url('https://www.transparenttextures.com/patterns/stardust.png');
            opacity: 0.3;
        }
        .daily-reward-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85);
            z-index: 10000;
            backdrop-filter: blur(10px);
            justify-content: center;
            align-items: center;
        }

        .reward-container {
            background: rgba(26, 26, 46, 0.95);
            border: 2px solid #f1c40f;
            border-radius: 20px;
            padding: 40px;
            width: 90%;
            max-width: 800px;
            text-align: center;
            position: relative;
            box-shadow: 0 0 50px rgba(241, 196, 15, 0.3);
        }

        .streak-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin: 30px 0;
        }

        .streak-day {
            background: rgba(255,255,255,0.05);
            padding: 15px 5px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
        }

        .streak-day.active {
            background: rgba(241, 196, 15, 0.2);
            border-color: #f1c40f;
            transform: scale(1.1);
        }

        .streak-day.claimed {
            opacity: 0.5;
            background: rgba(46, 204, 113, 0.2);
            border-color: #2ecc71;
        }

        .day-label { font-size: 0.8em; color: #aaa; margin-bottom: 5px; }
        .reward-icon { font-size: 1.5em; margin: 10px 0; }
        .reward-val { font-weight: bold; font-size: 0.7em; color: #2ecc71; }
        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            font-size: 10px;
            margin-right: 5px;
            color: #fff;
            box-shadow: 0 0 5px rgba(0,0,0,0.3);
        }
        .badge-whaler { background: linear-gradient(135deg, #f1c40f, #d35400); } /* Rich */
        .badge-veteran { background: linear-gradient(135deg, #3498db, #2980b9); } /* Old player */
        .badge-pro { background: linear-gradient(135deg, #2ecc71, #27ae60); } /* High win rate */
        .particle {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            animation: particleFloat var(--duration, 1s) ease-out forwards;
            z-index: 10;
        }

        @keyframes particleFloat {
            50% {
                transform: translateY(-5px);
            }
        }

        /* Lobby Social Widgets */
        .live-ticker-container {
            width: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            color: #fff;
            padding: 10px 0;
            overflow: hidden;
            position: relative;
            z-index: 100;
            border-bottom: 2px solid var(--primary-color);
        }

        .ticker-wrapper {
            display: flex;
            white-space: nowrap;
            animation: tickerMove 30s linear infinite;
        }

        @keyframes tickerMove {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }

        .ticker-item {
            margin-right: 50px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ticker-name { color: #f1c40f; }
        .ticker-amount { color: #2ecc71; }

        .guild-war-widget {
            background: linear-gradient(135deg, rgba(26, 26, 46, 0.9) 0%, rgba(22, 33, 62, 0.9) 100%);
            border: 2px solid rgba(241, 196, 15, 0.3);
            border-radius: var(--border-radius-lg);
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .guild-war-widget h2 {
            font-size: 1.5em;
            color: #f1c40f;
            text-align: center;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .top-guild-list {
            list-style: none;
            padding: 0;
        }

        .top-guild-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            margin-bottom: 8px;
            border-left: 4px solid var(--primary-color);
        }

        .top-guild-item:nth-child(1) { border-left-color: #f1c40f; background: rgba(241, 196, 15, 0.1); }
        .top-guild-item:nth-child(2) { border-left-color: #bdc3c7; }
        .top-guild-item:nth-child(3) { border-left-color: #cd7f32; }

        .pvp-alert {
            position: fixed;
            top: 100px;
            right: 20px;
            background: #e67e22;
            color: #fff;
            padding: 15px 25px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            z-index: 1000;
            display: none;
            animation: slideInRight 0.5s ease;
            border-left: 5px solid #fff;
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes particleExplode {
            0% {
                opacity: 1;
                transform: translate(0, 0) scale(1);
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
</head>

<body>
    <!-- Live Win Ticker -->
    <div class="live-ticker-container" id="liveTicker">
        <div class="ticker-wrapper" id="tickerWrapper">
            <div class="ticker-item">Đang tải tin tức mới nhất...</div>
        </div>
    </div>

    <!-- Flash Event Banner -->
    <div id="flashEventBanner" style="display: none; background: linear-gradient(90deg, #ff007f, #7928ca); padding: 15px; border-bottom: 2px solid #fff; text-align: center; color: white; box-shadow: 0 4px 15px rgba(255,0,127,0.5); position: relative; z-index: 1000; animation: notificationPulse 2s infinite;">
        <div style="font-weight: 800; font-size: 18px; letter-spacing: 1px; text-transform: uppercase;">
            ⚡ SỰ KIỆN CHỚP NHOÁNG ĐANG DIỄN RA! ⚡
        </div>
        <div style="font-size: 14px; margin-top: 5px;">
            Nhân <strong id="feMultiplier" style="color: #ffeb3b; font-size: 18px;">x2</strong> GTLM cho tất cả phần thưởng! Kết thúc sau: <strong id="feCountdown">00:00:00</strong>
        </div>
    </div>

    <!-- Community Lottery Jackpot Meter -->
    <div style="background: linear-gradient(90deg, #1e1b4b, #312e81); padding: 15px; border-bottom: 2px solid #fbbf24; text-align: center; color: white; display: flex; align-items: center; justify-content: center; gap: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); position: relative; z-index: 1000;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fa fa-trophy" style="color: #fbbf24; font-size: 24px;"></i>
            <span style="font-weight: 800; font-size: 14px; letter-spacing: 1px; color: #94a3b8; text-transform: uppercase;">JACKPOT HÔM NAY</span>
        </div>
        <div style="font-size: 28px; font-weight: 800; color: #fbbf24; text-shadow: 0 0 10px rgba(251, 191, 36, 0.5); font-family: 'JetBrains Mono', monospace;">
            <?= number_format($currentJackpot, 0, ',', '.') ?> <span style="font-size: 14px;">GTLM</span>
        </div>
        <a href="games/community_lottery.php" class="btn btn-sm" style="background: #fbbf24; color: #000; padding: 5px 15px; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 12px;">MUA VÉ NGAY</a>
    </div>

    <!-- PVP Challenge Alert -->
    <div class="pvp-alert" id="pvpAlert">
        <div style="display: flex; align-items: center; gap: 15px;">
            <i class="fa fa-swords" style="font-size: 24px;"></i>
            <div>
                <strong id="challengerName">Ai đó</strong> đang thách đấu bạn!
                <div style="margin-top: 5px;">
                    <a href="pvp_challenge.php" class="btn btn-sm" style="background: #fff; color: #e67e22; padding: 2px 10px;">XEM NGAY</a>
                </div>
            </div>
        </div>
    </div>

    <div class="header">
        <h1 class="welcome">Chào mừng, <span class="sparkle-text"><?php echo htmlspecialchars($user['Name']); ?></span>!
        </h1>
        
        <div style="display: flex; align-items: center; gap: 15px;">
            <div class="notification-container">
                <div class="notif-bell" onclick="toggleNotifDropdown()">
                    <i class="fa fa-bell"></i>
                    <span class="notif-badge" id="notifCountBadge">0</span>
                </div>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header">
                        <strong>Thông báo</strong>
                        <span onclick="markAllAsRead()" style="font-size: 0.8em; color: #4facfe; cursor: pointer;">Đánh dấu đã đọc</span>
                    </div>
                    <div class="notif-list" id="notifList">
                        <!-- Notifications load here -->
                    </div>
                    <div style="padding: 10px; text-align: center; font-size: 0.8em; border-top: 1px solid rgba(255,255,255,0.1);">
                        <a href="#" style="color: #666; text-decoration: none;">Xem tất cả</a>
                    </div>
                </div>
            </div>

            <a href="preview_themes.php" class="theme-button" id="themeButton" title="Xem trước themes với full background">
                <span class="theme-icon">🎨</span>
                <span class="theme-text">Xem Themes</span>
            </a>
        </div>
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
                    <a href="admin_advanced_center.php"><i class="fa-solid fa-shield-halved icon"></i> Master Trận Địa</a>
                    <a href="admin_analytics.php"><i class="fa-solid fa-chart-line icon"></i> Thống Kê Website</a>
                    <a href="bot/index.php"><i class="fa-solid fa-robot icon"></i> Quản Lý Bot Army</a>
                <?php endif; ?>
                <a href="shop.php"><i class="fa-solid fa-store icon"></i> Cửa Hàng</a>
                <a href="achievements.php"><i class="fa-solid fa-trophy icon"></i> Danh Hiệu</a>
                <a href="select_title.php"><i class="fa-solid fa-crown icon"></i> Chọn Danh Hiệu</a>
                <a href="addimg.php"><i class="fa-solid fa-image icon"></i> Đổi ảnh đại diện</a>
                <a href="khungchat.php"><i class="fa-solid fa-comment icon"></i> Chọn Khung Chat</a>
                <a href="khungavatar.php"><i class="fa-solid fa-image icon"></i> Chọn Khung Avatar</a>
                <a href="event_center.php"><i class="fa-solid fa-calendar-days icon"></i> Trung Tâm Sự Kiện</a>
                <a href="seasonal_pass.php"><i class="fa-solid fa-ticket icon"></i> Thẻ Mùa Giải</a>
                <a href="pets.php"><i class="fa-solid fa-paw icon"></i> Hệ Thống Thú Cưng</a>
                <?php if (isset($user['Role']) && $user['Role'] == 1): ?>
                    <a href="admin_dashboard.php"><i class="fa-solid fa-gauge icon"></i> Admin Dashboard</a>
                    <a href="admin_manage_frames.php"><i class="fa-solid fa-palette icon"></i> Admin - Quản Lý Khung</a>
                    <a href="admin_manage_items.php"><i class="fa-solid fa-gear icon"></i> Admin - Quản Lý Items</a>
                    <a href="admin_manage_users.php"><i class="fa-solid fa-users-gear icon"></i> Admin - Quản Lý Users</a>
                    <a href="admin_Event_Manager.php"><i class="fa-solid fa-calendar-check icon"></i> Admin - Quản Lý Sự Kiện</a>
                    <a href="admin_tournaments.php"><i class="fa-solid fa-award icon"></i> Admin - Quản Lý Giải Đấu</a>
                <?php endif; ?>
                <div style="border-top: 1px solid rgba(255,255,255,0.1); margin: 6px 0;"></div>
                <a href="switch_ui.php?v=v1" style="color: #60a5fa; font-weight: 700;"><i class="fa-solid fa-desktop icon"></i> Giao diện V1 (Mặc định)</a>
                <a href="switch_ui.php?v=v2" style="color: #f59e0b; font-weight: 700;"><i class="fa-solid fa-bolt icon"></i> Giao diện V2 (React/Vite)</a>
                <a href="switch_ui.php?v=v3" style="color: #4ade80; font-weight: 700;"><i class="fa-solid fa-layer-group icon"></i> Giao diện V3 (Dashboard 3 Cột)</a>
                <div style="border-top: 1px solid rgba(255,255,255,0.1); margin: 6px 0;"></div>
                <a href="#" id="darkModeToggle"><i class="fa-solid fa-moon icon"></i> Bật darkmode</a>
                <a href="login.php"><i class="fa-solid fa-right-from-bracket icon"></i> Đăng xuất</a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Global Jackpot -->
        <div class="jackpot-banner">
            <div class="jackpot-coins"></div>
            <div class="jackpot-label">🏆 HŨ RỒNG THẦN 🏆</div>
            <div class="jackpot-amount" id="jackpotAmount">100.000.000</div>
            <div class="jackpot-winner">
                Người vừa nổ hũ: <strong id="lastJackpotWinner">Chưa có</strong> 
                (<span id="lastJackpotAmount">0</span> GTLM)
            </div>
        </div>

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
                    <div class="stat-value" data-target="<?= $totalGames ?>">0</div>
                    <div class="stat-label">Game</div>
                </div>
                <div class="stat-card tooltip" data-tooltip="Tổng số người chơi">
                    <div class="stat-icon">👥</div>
                    <div class="stat-value" data-target="<?= $totalUsers ?>">0</div>
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
                    <a href="tower_of_gods.php" class="menu-item tooltip" data-tooltip="Khiêu chiến 100 tầng Boss AI">
                        <span class="menu-icon">🗼</span> Tháp Thần Bài
                    </a>
                    <a href="my_lounge.php" class="menu-item tooltip" data-tooltip="Trưng bày Cúp & Nội Thất Hoàng Gia">
                        <span class="menu-icon">🏡</span> Biệt Thự Cúp
                    </a>
                    <a href="games/plinko.php" class="menu-item tooltip" data-tooltip="Plinko Royale V1">
                        <span class="menu-icon">🔴</span> Plinko V1
                    </a>
                    <a href="games/plinko_v2.php" class="menu-item tooltip" data-tooltip="Plinko V2">
                        <span class="menu-icon">🔮</span> Plinko V2
                    </a>
                    <a href="plinko_royale_v3.php" class="menu-item tooltip" data-tooltip="Plinko Royale V3 Multi-Drop">
                        <span class="menu-icon">🎰</span> Plinko Royale V3
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
                    <a href="crafting.php" class="menu-item tooltip" data-tooltip="Rèn vật phẩm hiếm">
                        <span class="menu-icon">🛠️</span> Workshop
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
                    <a href="spectator.php" class="menu-item tooltip" data-tooltip="Xem live & Tip">
                        <span class="menu-icon">👀</span> Spectator
                    </a>
                    <a href="tournaments.php" class="menu-item tooltip" data-tooltip="Tham gia giải đấu">
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

            <!-- ⚡ FLASH EVENT SURPRISE BANNER -->
            <?php
            $flashMultiplier = EventHelper::getActiveFlashMultiplier($conn);
            if ($flashMultiplier > 1.00):
            ?>
            <div class="flash-event-banner" style="background: linear-gradient(135deg, #ef4444 0%, #f59e0b 50%, #facc15 100%); padding: 16px 20px; border-radius: 16px; text-align: center; color: white; margin: 15px 0; box-shadow: 0 0 25px rgba(239, 68, 68, 0.45); font-weight: 800; border: 2px solid #fff; position: relative; overflow: hidden; animation: pulse 1.5s infinite alternate;">
                <div style="font-size: 15px; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="fa fa-bolt" style="font-size: 20px; color: #fff; text-shadow: 0 0 10px #facc15;"></i>
                    <span>Sự Kiện Chớp Nhoáng (x2 Multiplier) đang nổ ra!</span>
                </div>
                <div style="font-size: 12px; font-weight: 500; color: rgba(255,255,255,0.9); margin-top: 4px;">
                    Tất cả phần thưởng GTLM nhận được sẽ nhân đôi! Mau ra chiêu!
                </div>
            </div>
            <?php endif; ?>

            <!-- 📖 STORYLINE EVENT SHORTCUT BANNER -->
            <a href="storyline_event.php" style="display: flex; align-items: center; justify-content: space-between; background: linear-gradient(135deg, #1e1b4b 0%, #311042 100%); border: 1.5px solid rgba(139, 92, 246, 0.4); border-radius: 16px; padding: 15px 20px; text-decoration: none; margin: 15px 0; transition: all 0.3s; box-shadow: 0 8px 25px rgba(139, 92, 246, 0.2); position: relative; overflow: hidden;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <span style="font-size: 28px;">📖</span>
                    <div style="text-align: left;">
                        <span style="font-weight: 800; color: #c084fc; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; display: block;">Event Cốt Truyện</span>
                        <span style="font-size: 12px; color: #cbd5e1; margin-top: 2px; display: block;">Vượt ải mỗi ngày - Nhận GTLM cực khủng</span>
                    </div>
                </div>
                <span style="background: #a855f7; color: white; padding: 5px 15px; border-radius: 20px; font-size: 11px; font-weight: 800; box-shadow: 0 0 10px rgba(168, 85, 247, 0.5);">THAM GIA</span>
            </a>

            <!-- 👥 COMMUNITY GOAL PROGRESS CARD -->
            <?php
            $today = date('Y-m-d');
            $goalRes = $conn->query("SELECT * FROM community_goals WHERE goal_date = '$today' LIMIT 1");
            $goal = $goalRes ? $goalRes->fetch_assoc() : null;
            $targetVal = $goal ? (int)$goal['target_value'] : 1000000;
            $currentVal = $goal ? (int)$goal['current_value'] : 0;
            $pct = min(100, round(($currentVal / $targetVal) * 100, 2));
            ?>
            <div style="background: rgba(30, 41, 59, 0.65); backdrop-filter: blur(12px); border: 1.5px solid rgba(255,255,255,0.08); padding: 18px 20px; border-radius: 18px; box-shadow: 0 10px 30px rgba(0,0,0,0.25); margin: 15px 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <span style="font-weight: 800; color: #fbbf24; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;"><i class="fa fa-users"></i> Mục Tiêu Cộng Đồng</span>
                    <span style="font-size: 12px; color: #94a3b8; font-weight: 700;"><?= number_format($currentVal) ?> / <?= number_format($targetVal) ?> cược</span>
                </div>
                <div style="background: rgba(0,0,0,0.4); height: 18px; border-radius: 9px; overflow: hidden; position: relative; border: 1px solid rgba(255,255,255,0.05);">
                    <div style="width: <?= $pct ?>%; height: 100%; background: linear-gradient(90deg, #6366f1, #a855f7, #ec4899); transition: width 1s ease-in-out; border-radius: 9px; position: relative;">
                        <div style="position: absolute; top:0; left:0; right:0; bottom:0; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent); animation: shimmer 2s infinite;"></div>
                    </div>
                    <div style="position: absolute; width: 100%; text-align: center; top:0; line-height: 16px; font-size: 10px; font-weight: 900; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.85);">
                        <?= $pct ?>% Hoàn Thành
                    </div>
                </div>
                <p style="font-size: 12px; color: #94a3b8; margin: 10px 0 0; text-align: center; line-height: 1.4;">
                    🎯 Cả server chung tay đạt 1.000.000 cược hôm nay để **NHÂN ĐÔI tỉ lệ trúng Jackpot** toàn server!
                </p>
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
                <button class="tab-btn" data-category="vip" style="color: #fbbf24; border-color: #fbbf24;">VIP & Tycoon</button>
                <button class="tab-btn" data-category="social">Sư Đồ & Bang Hội</button>
            </div>

            <div class="game-grid-modern">
                <!-- AFK & VIP Games -->
                <a href="games/farm.php" class="game-card" data-category="vip">
                    <span class="game-badge badge-new" style="background:#22c55e;">NEW</span>
                    <span class="game-icon">🌾</span>
                    <span class="game-name">Nông Trại AFK</span>
                </a>
                <a href="games/mining.php" class="game-card" data-category="vip">
                    <span class="game-badge badge-hot" style="background:#fbbf24;">AFK</span>
                    <span class="game-icon">⛏️</span>
                    <span class="game-name">Khu Mỏ Khoáng Sản</span>
                </a>
                <a href="tower_of_gods.php" class="game-card" data-category="vip">
                    <span class="game-badge badge-hot" style="background:#a855f7;">HOT</span>
                    <span class="game-icon">🗼</span>
                    <span class="game-name">Tháp Thần Bài</span>
                </a>
                <a href="my_lounge.php" class="game-card" data-category="vip">
                    <span class="game-badge badge-new" style="background:#f59e0b;">VIP</span>
                    <span class="game-icon">🏡</span>
                    <span class="game-name">Biệt Thự & Cúp</span>
                </a>
                <a href="games/pets.php" class="game-card" data-category="vip">
                    <span class="game-badge badge-new" style="background:#ec4899;">PETS</span>
                    <span class="game-icon">🐾</span>
                    <span class="game-name">Chuồng Thú Cưng</span>
                </a>
                <a href="games/plinko.php" class="game-card" data-category="vip slots mini">
                    <span class="game-badge badge-hot" style="background:#ef4444;">V1</span>
                    <span class="game-icon">🔴</span>
                    <span class="game-name">Plinko V1</span>
                </a>
                <a href="games/plinko_v2.php" class="game-card" data-category="vip slots mini">
                    <span class="game-badge badge-new" style="background:#eab308;">V2</span>
                    <span class="game-icon">🔮</span>
                    <span class="game-name">Plinko V2</span>
                </a>
                <a href="plinko_royale_v3.php" class="game-card" data-category="vip slots mini">
                    <span class="game-badge badge-new" style="background:linear-gradient(135deg,#ef4444,#f59e0b); color:#fff; font-weight:bold;">MULTI</span>
                    <span class="game-icon">🎰</span>
                    <span class="game-name">Plinko Royale V3</span>
                </a>
                <a href="games/market.php" class="game-card" data-category="vip">
                    <span class="game-badge badge-new" style="background:#ef4444;">VIP</span>
                    <span class="game-icon">📈</span>
                    <span class="game-name">Sàn Chứng Khoán</span>
                </a>
                <a href="games/greedy_cave.php" class="game-card" data-category="vip">
                    <span class="game-badge badge-new" style="background:#8b5cf6;">HOT</span>
                    <span class="game-icon">🦇</span>
                    <span class="game-name">Hang Tham Lam</span>
                </a>

                <!-- Game Bài (Card Games) -->
                <a href="games/tusac.php" class="game-card" data-category="card">
                    <span class="game-badge badge-new">New</span>
                    <span class="game-icon">🎴</span>
                    <span class="game-name">Tứ Sắc Cổ Truyền</span>
                </a>
                <a href="games/samloc.php" class="game-card" data-category="card">
                    <span class="game-badge badge-new">New</span>
                    <span class="game-icon">🃏</span>
                    <span class="game-name">Sâm Lốc Tốc Độ</span>
                </a>
                <a href="games/blackjack.php" class="game-card" data-category="card">
                    <span class="game-badge badge-hot">Hot</span>
                    <span class="game-icon">👑</span>
                    <span class="game-name">Xì Dách Royale</span>
                </a>
                <a href="games/blackjack_multi.php" class="game-card" data-category="card">
                    <span class="game-badge badge-new">Multi</span>
                    <span class="game-icon">👥</span>
                    <span class="game-name">Xì Dách Multiplayer</span>
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
                <a href="games/community_lottery.php" class="game-card" data-category="slots">
                    <span class="game-badge badge-hot">Hot</span>
                    <span class="game-icon">🎫</span>
                    <span class="game-name">Xổ Số Cộng Đồng</span>
                </a>

                <a href="games/roulette.php" class="game-card" data-category="slots">
                    <span class="game-icon">🎡</span>
                    <span class="game-name">Roulette</span>
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
                <a href="games/daga.php" class="game-card" data-category="mini">
                    <span class="game-badge badge-new">New</span>
                    <span class="game-icon">🐓</span>
                    <span class="game-name">Đại Chiến Thần Kê Premium</span>
                </a>
                <a href="games/battleroyale.php" class="game-card" data-category="mini">
                    <span class="game-badge badge-new">New</span>
                    <span class="game-icon">🔥</span>
                    <span class="game-name">Battle Royale Số</span>
                </a>
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
                    <span class="game-name">Xanh Đỏ Classic</span>
                </a>
                <a href="games/sicbo_v2.php" class="game-card" data-category="mini">
                    <span class="game-badge badge-new">New</span>
                    <span class="game-icon">🎲</span>
                    <span class="game-name">Xanh Đỏ 3D</span>
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
                <a href="games/horserace.php" class="game-card" data-category="mini">
                    <span class="game-badge badge-new">New</span>
                    <span class="game-icon">🐎</span>
                    <span class="game-name">Đua Ngựa Pari-Mutuel</span>
                </a>
                <a href="games/duangua.php" class="game-card" data-category="mini">
                    <span class="game-badge badge-hot">Hot</span>
                    <span class="game-icon">🐎</span>
                    <span class="game-name">Đua Thú Premium</span>
                </a>
                <a href="games/jojo_battle.php" class="game-card" data-category="mini">
                    <span class="game-badge badge-new">New</span>
                    <span class="game-icon">👊</span>
                    <span class="game-name">Đại Chiến JoJo</span>
                </a>
                <a href="games/horserace_pvp.php" class="game-card" data-category="mini">
                    <span class="game-badge badge-new">PVP</span>
                    <span class="game-icon">🐎</span>
                    <span class="game-name">Đua Ngựa PVP</span>
                </a>
                <a href="guild_pro.php" class="game-card" data-category="mini">
                    <span class="game-badge badge-hot">Hot</span>
                    <span class="game-icon">🛡️</span>
                    <span class="game-name">Bang Hội Pro</span>
                </a>
                <a href="games/hopmu.php" class="game-card" data-category="mini">
                    <span class="game-icon">🎁</span>
                    <span class="game-name">Hộp Mú</span>
                </a>
                
                <!-- Social, Mentor and Guild Tournament Cards -->
                <a href="social_feed.php" class="game-card" data-category="social">
                    <span class="game-badge badge-hot">Feed</span>
                    <span class="game-icon">📱</span>
                    <span class="game-name">Bảng Tin & Tương Tác</span>
                </a>
                <a href="mentor_center.php" class="game-card" data-category="social">
                    <span class="game-badge badge-new">🤝</span>
                    <span class="game-icon">🤝</span>
                    <span class="game-name">Trung Tâm Sư Đồ</span>
                </a>
                <a href="guild_tournament.php" class="game-card" data-category="social">
                    <span class="game-badge badge-hot">GvG</span>
                    <span class="game-icon">🏆</span>
                    <span class="game-name">Đại Chiến Bang Hội</span>
                </a>
            </div>
        </div>

        <!-- Cột bảng xếp hạng -->
        <div class="ranking">
            <!-- Guild War Top Widget -->
            <div class="guild-war-widget" id="guildWarWidget">
                <h2><i class="fa fa-shield-halved"></i> Top Bang Hội</h2>
                <div class="top-guild-list" id="topGuildList">
                    <div style="text-align: center; color: #bdc3c7; padding: 10px;">Đang cập nhật dữ liệu...</div>
                </div>
                <div style="text-align: center; margin-top: 10px;">
                    <a href="guild_war.php" style="color: #f1c40f; font-size: 0.8em; text-decoration: none;">Chi tiết sự kiện &raquo;</a>
                </div>
            </div>

            <!-- Battle Pass Progress Widget -->
            <div class="guild-war-widget" style="margin-top: 20px; border-color: #4facfe;">
                <h2><i class="fa fa-star" style="color: #4facfe;"></i> Battle Pass</h2>
                <div style="padding: 10px; text-align: center;">
                    <div style="font-size: 0.9em; margin-bottom: 5px;">Mùa 1: Khởi Đầu</div>
                    <a href="battle_pass.php" class="btn btn-sm" style="background: linear-gradient(90deg, #4facfe, #00f2fe); border: none; width: 100%;">XEM TIẾN ĐỘ</a>
                </div>
            </div>

            <!-- World Boss Status Widget -->
            <div class="guild-war-widget" style="margin-top: 20px; border-color: #ff4500; background: rgba(255, 69, 0, 0.05);">
                <h2><i class="fa fa-dragon" style="color: #ff4500;"></i> World Boss</h2>
                <div style="padding: 10px; text-align: center;">
                    <div id="boss-status-lobby" style="font-size: 0.85em; margin-bottom: 10px; color: #ff4500; font-weight: bold;">Hắc Long Thần đang xuất hiện!</div>
                    <a href="world_boss.php" class="btn btn-sm" style="background: #ff4500; border: none; width: 100%; color: #fff;">THAM CHIẾN NGAY</a>
                </div>
            </div>

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

                                    <!-- Achievement Badges -->
                                    <?php if ($r['Money'] > 100000000): ?>
                                        <span class="rank-badge badge-whaler" title="Đại gia GTLM">💎</span>
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
            <a href="events.php" class="quick-link-card" style="border-left: 4px solid #f97316;">
                <span class="quick-link-icon">🎪</span>
                <div class="quick-link-title">Đại Sảnh Sự Kiện</div>
                <div class="quick-link-desc">Tổng hợp mọi sự kiện, phần thưởng, nhiệm vụ</div>
            </a>
            <a href="battle_pass.php" class="quick-link-card" style="border-left: 4px solid #4facfe;">
                <span class="quick-link-icon">⭐</span>
                <div class="quick-link-title">Battle Pass</div>
                <div class="quick-link-desc">Làm nhiệm vụ, thăng cấp, nhận quà khủng</div>
            </a>
            <a href="world_boss.php" class="quick-link-card" style="border-left: 4px solid #ff4500;">
                <span class="quick-link-icon">🐲</span>
                <div class="quick-link-title">Boss Thế Giới</div>
                <div class="quick-link-desc">Hợp sức tiêu diệt Hắc Long Thần</div>
            </a>
            <a href="oracle_prophecy.php" class="quick-link-card" style="border-left: 4px solid #8b5cf6;">
                <span class="quick-link-icon">🔮</span>
                <div class="quick-link-title">Lời Tiên Tri</div>
                <div class="quick-link-desc">Gửi thông điệp, bình chọn sự kiện hàng tuần</div>
            </a>
            <a href="server_history.php" class="quick-link-card" style="border-left: 4px solid #10b981;">
                <span class="quick-link-icon">📜</span>
                <div class="quick-link-title">Biên Niên Sử</div>
                <div class="quick-link-desc">Lịch sử hào hùng và các sự kiện đáng nhớ của Server</div>
            </a>
            <a href="hall_of_fame.php" class="quick-link-card" style="border-left: 4px solid #fbbf24;">
                <span class="quick-link-icon">🏆</span>
                <div class="quick-link-title">Đại Lộ Danh Vọng</div>
                <div class="quick-link-desc">Tôn vinh những huyền thoại xuất chúng nhất</div>
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

    <!-- Daily Reward Modal -->
    <div class="daily-reward-modal" id="dailyRewardModal">
        <div class="reward-container">
            <h2 style="color: #f1c40f; font-size: 2.5em; margin-bottom: 10px;">🎁 QUÀ ĐĂNG NHẬP</h2>
            <p>Đăng nhập liên tiếp để nhận thưởng lớn hơn!</p>
            
            <div class="streak-grid" id="streakGrid">
                <!-- Days injected by JS -->
            </div>

            <button onclick="claimDailyReward()" id="btnClaimReward" class="btn btn-lg" style="background: #f1c40f; color: #000; padding: 15px 50px; font-weight: 800; border-radius: 30px;">
                NHẬN QUÀ NGAY
            </button>
            <p id="claimStatus" style="margin-top: 15px; color: #2ecc71; font-weight: bold;"></p>
        </div>
    </div>
    <div class="guild-chat-widget minimized" id="guildChatWidget" style="display: none;">
        <div class="guild-chat-header" onclick="toggleGuildChat()">
            <span><i class="fa fa-users"></i> Chat Bang Hội</span>
            <i class="fa fa-chevron-up" id="chatToggleIcon"></i>
        </div>
        <div class="guild-chat-messages" id="guildChatMessages">
            <!-- Messages load here -->
        </div>
        <div class="guild-chat-input-area">
            <input type="text" id="guildChatMessage" placeholder="Nhập tin nhắn..." onkeypress="if(event.key==='Enter') sendGuildMessage()">
            <button onclick="sendGuildMessage()" style="background: transparent; border: none; color: var(--primary-color); cursor: pointer;">
                <i class="fa fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <!-- Confetti Container -->
    <div class="confetti-container" id="confettiContainer"></div>

    <!-- Server Notification Banner -->
    <div class="server-notification" id="serverNotification">
        <button class="close-btn" onclick="closeNotification()">×</button>
        <div id="notificationMessage"></div>
    </div>








    <!-- Switch UI Button -->
    <a href="switch_ui.php?v=new" class="fab" style="bottom: 80px; background: linear-gradient(135deg, #f1c40f 0%, #f39c12 100%); color: #000; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 20px; box-shadow: 0 4px 15px rgba(241, 196, 15, 0.4);" title="Chuyển sang giao diện mới">
        ✨
    </a>

    <!-- Premium Effects System -->
    <canvas id="threejs-background"></canvas>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
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

            // ── Lobby Social Widgets Logic ────────────────────────
            function updateLobbySocial() {
                fetch('api_lobby_social.php?action=get_social_data')
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) return;

                        // 1. Update Ticker
                        const tickerWrapper = document.getElementById('tickerWrapper');
                        if (tickerWrapper && data.live_wins.length > 0) {
                            tickerWrapper.innerHTML = '';
                            data.live_wins.forEach(win => {
                                const item = document.createElement('div');
                                item.className = 'ticker-item';
                                item.innerHTML = `🎉 <span class="ticker-name">${win.Name}</span> vừa thắng <span class="ticker-amount">${Number(win.win_amount).toLocaleString()} gtlm</span> trong game <strong>${win.game_name}</strong>!`;
                                tickerWrapper.appendChild(item);
                            });
                            // Duplicate to ensure smooth scrolling
                            tickerWrapper.innerHTML += tickerWrapper.innerHTML;
                        }

                        // 2. Update Top Guilds
                        const guildWidget = document.getElementById('guildWarWidget');
                        const guildList = document.getElementById('topGuildList');
                        if (guildWidget && guildList) {
                            if (data.top_guilds.length > 0) {
                                guildList.innerHTML = '';
                                data.top_guilds.forEach((guild, idx) => {
                                    const item = document.createElement('div');
                                    item.className = 'top-guild-item';
                                    item.innerHTML = `
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span style="font-weight: 800; color: #f1c40f;">#${idx+1}</span>
                                            <span class="guild-tag">[${guild.tag}]</span>
                                            <strong>${guild.name}</strong>
                                        </div>
                                        <div style="color: #2ecc71; font-weight: 800;">${Number(guild.points).toLocaleString()}</div>
                                    `;
                                    guildList.appendChild(item);
                                });
                            } else {
                                guildList.innerHTML = '<div style="text-align: center; color: #bdc3c7; padding: 10px; font-size: 0.9em;">Chưa có dữ liệu tuần này</div>';
                            }
                        }

                        // 3. PVP Alerts
                        if (data.challenges && data.challenges.length > 0) {
                            const alert = document.getElementById('pvpAlert');
                            const nameSpan = document.getElementById('challengerName');
                            if (alert && nameSpan) {
                                nameSpan.textContent = data.challenges[0].challenger_name;
                                alert.style.display = 'block';
                                // Tự động ẩn sau 10s
                                setTimeout(() => { alert.style.display = 'none'; }, 10000);
                            }
                        }
                    });
            }

            updateLobbySocial();
            setInterval(updateLobbySocial, 60000); // Cập nhật mỗi phút

            // ── Daily Reward Logic ─────────────────────────────
            function checkDailyReward() {
                fetch('api_daily_reward.php?action=check')
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.can_claim) {
                            showRewardModal(data.streak);
                        }
                    });
            }

            function showRewardModal(currentStreak) {
                const grid = document.getElementById('streakGrid');
                const rewards = [10000, 25000, 50000, 100000, 200000, 500000, 1000000];
                grid.innerHTML = '';
                
                for (let i = 1; i <= 7; i++) {
                    const isClaimed = i <= currentStreak;
                    const isActive = i === (currentStreak % 7) + 1;
                    
                    grid.innerHTML += `
                        <div class="streak-day ${isClaimed ? 'claimed' : ''} ${isActive ? 'active' : ''}">
                            <div class="day-label">Ngày ${i}</div>
                            <div class="reward-icon">${i === 7 ? '👑' : '💰'}</div>
                            <div class="reward-val">${(rewards[i-1]/1000)}K</div>
                        </div>
                    `;
                }
                document.getElementById('dailyRewardModal').style.display = 'flex';
            }

            function claimDailyReward() {
                const btn = document.getElementById('btnClaimReward');
                btn.disabled = true;
                btn.textContent = 'ĐANG XỬ LÝ...';

                fetch('api_daily_reward.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=claim'
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        Swal.fire('Thành công!', `Bạn đã nhận được ${data.amount.toLocaleString()} GTLM!`, 'success');
                        document.getElementById('dailyRewardModal').style.display = 'none';
                        location.reload();
                    } else {
                        if (typeof Swal !== 'undefined') { Swal.fire('Thông báo', String(data.message), 'info'); } else { alert(data.message); };
                        btn.disabled = false;
                        btn.textContent = 'NHẬN QUÀ NGAY';
                    }
                });
            }
            window.claimDailyReward = claimDailyReward;

            checkDailyReward();

            // ── Notification Logic ─────────────────────────────
            let isNotifOpen = false;

            function toggleNotifDropdown() {
                const dropdown = document.getElementById('notifDropdown');
                isNotifOpen = !isNotifOpen;
                dropdown.style.display = isNotifOpen ? 'block' : 'none';
                if (isNotifOpen) loadNotifications();
            }
            window.toggleNotifDropdown = toggleNotifDropdown;

            function loadNotifications() {
                fetch('api_notifications.php?action=get_notifications')
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const badge = document.getElementById('notifCountBadge');
                            if (data.unread_count > 0) {
                                badge.textContent = data.unread_count;
                                badge.style.display = 'block';
                            } else {
                                badge.style.display = 'none';
                            }

                            const list = document.getElementById('notifList');
                            if (data.notifications.length === 0) {
                                list.innerHTML = '<div style="padding: 20px; text-align: center; color: #666;">Không có thông báo mới</div>';
                                return;
                            }

                            list.innerHTML = '';
                            data.notifications.forEach(n => {
                                const div = document.createElement('div');
                                div.className = `notif-item ${n.is_read == 0 ? 'unread' : ''}`;
                                div.onclick = () => markAsRead(n.id);
                                div.innerHTML = `
                                    <div class="notif-title">${n.title}</div>
                                    <div class="notif-msg">${n.message}</div>
                                    <div class="notif-time">${n.created_at}</div>
                                `;
                                list.appendChild(div);
                            });
                        }
                    });
            }

            function markAsRead(id) {
                fetch('api_notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=mark_as_read&id=${id}`
                }).then(() => loadNotifications());
            }

            function markAllAsRead() {
                fetch('api_notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=mark_as_read&id=0`
                }).then(() => loadNotifications());
            }
            window.markAllAsRead = markAllAsRead;

            loadNotifications();
            setInterval(loadNotifications, 30000); // Polling mỗi 30s

            let lastGuildMsgId = 0;
            let isChatMinimized = true;

            function toggleGuildChat() {
                const widget = document.getElementById('guildChatWidget');
                const icon = document.getElementById('chatToggleIcon');
                isChatMinimized = !isChatMinimized;
                widget.classList.toggle('minimized', isChatMinimized);
                icon.className = isChatMinimized ? 'fa fa-chevron-up' : 'fa fa-chevron-down';
                if (!isChatMinimized) {
                    scrollToBottom();
                }
            }
            window.toggleGuildChat = toggleGuildChat;

            function scrollToBottom() {
                const msgs = document.getElementById('guildChatMessages');
                msgs.scrollTop = msgs.scrollHeight;
            }

            function loadGuildMessages() {
                fetch(`api_guild_chat.php?action=load&last_id=${lastGuildMsgId}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.messages.length > 0) {
                            const container = document.getElementById('guildChatMessages');
                            data.messages.forEach(msg => {
                                const div = document.createElement('div');
                                div.className = 'gm-item';
                                div.innerHTML = `
                                    <div class="gm-user">${msg.username}</div>
                                    <div class="gm-text">${msg.message}</div>
                                `;
                                container.appendChild(div);
                                lastGuildMsgId = msg.id;
                            });
                            if (!isChatMinimized) scrollToBottom();
                        }
                    });
            }

            function sendGuildMessage() {
                const input = document.getElementById('guildChatMessage');
                const msg = input.value.trim();
                if (!msg) return;

                fetch('api_guild_chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=send&message=${encodeURIComponent(msg)}`
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        input.value = '';
                        loadGuildMessages();
                    } else {
                        if (typeof Swal !== 'undefined') { Swal.fire('Thông báo', String(data.message), 'info'); } else { alert(data.message); };
                    }
                });
            }
            window.sendGuildMessage = sendGuildMessage;

            // Check if user is in a guild
            fetch('api_guild_chat.php?action=get_status')
                .then(r => r.json())
                .then(data => {
                    if (data.in_guild) {
                        document.getElementById('guildChatWidget').style.display = 'flex';
                        loadGuildMessages();
                        setInterval(loadGuildMessages, 5000);
                    }
                });

            // ── Messages FAB ───────────────────────────────────────
            const msgFab = document.getElementById('messagesFab');
            if (msgFab) {
                msgFab.addEventListener('click', () => {
                    window.location.href = 'private_message.php';
                });
            }

            // ── Jackpot Logic ──────────────────────────────────
            function updateJackpot() {
                fetch('api_jackpot.php?action=get_status')
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            animateNumber('jackpotAmount', data.amount);
                            document.getElementById('lastJackpotWinner').textContent = data.last_winner || 'Chưa có';
                            document.getElementById('lastJackpotAmount').textContent = Number(data.last_amount || 0).toLocaleString();
                        }
                    });
            }

            function animateNumber(id, target) {
                const el = document.getElementById(id);
                const current = parseInt(el.textContent.replace(/\./g, '')) || 0;
                const step = (target - current) / 20;
                let count = current;
                
                const timer = setInterval(() => {
                    count += step;
                    if ((step > 0 && count >= target) || (step < 0 && count <= target)) {
                        count = target;
                        clearInterval(timer);
                    }
                    el.textContent = Math.floor(count).toLocaleString('vi-VN');
                }, 50);
            }

            updateJackpot();
            setInterval(updateJackpot, 5000);

            // ── Lottery Notification ──
            const lotteryDrawTime = new Date('<?= date('Y-m-d') ?> 20:00:00');
            setInterval(() => {
                const now = new Date();
                const diffMin = (lotteryDrawTime - now) / 60000;
                if (diffMin > 0 && diffMin <= 30 && !window.lotteryNotified) {
                    if (typeof showToast === 'function') {
                        showToast(`🔔 Xổ số cộng đồng sẽ quay thưởng trong ${Math.round(diffMin)} phút nữa!`, 'info');
                        window.lotteryNotified = true;
                    }
                }
            }, 60000);
        })();

        // 🎣 CLIFFHANGER: Hiện gợi ý khi người dùng chuẩn bị rời đi
        window.addEventListener('beforeunload', function (e) {
            // Chỉ hiện nếu là người dùng thật và có GTLM
            const money = parseInt(document.querySelector('.user-money')?.textContent.replace(/,/g, '') || 0);
            if (money > 0 && Math.random() < 0.3) {
                // Hầu hết trình duyệt hiện đại sẽ hiển thị thông báo mặc định,
                // nhưng ta có thể dùng một custom modal nếu họ ở lại lâu hơn hoặc quay lại.
                console.log("Cliffhanger triggered: Jackpot x3 at 8PM!");
            }
        });

        // 🐾 OFFLINE REWARDS: Kiểm tra và nhắc nhở nhận quà từ Pet
        async function checkPetRewards() {
            try {
                const res = await fetch('api_pets.php');
                const data = await res.json();
                if (data.collected > 1000) {
                    Swal.fire({
                        title: '🎁 Linh Thú Mang Quà Về!',
                        text: `Linh thú của bạn đã nhặt được ${data.collected.toLocaleString()} GTLM trong lúc bạn offline!`,
                        imageUrl: 'https://cdn-icons-png.flaticon.com/512/616/616408.png',
                        imageWidth: 100,
                        confirmButtonText: 'NHẬN NGAY 🔥',
                        confirmButtonColor: '#6366f1'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch('api_pets.php?action=claim')
                            .then(() => location.reload());
                        }
                    });
                }
            } catch(e) {}
        }
        // PvP Listener moved to load_theme.php for global availability
    </script>

    <!-- 🔮 NPC: Lão Tiên Tri GTLM UI -->
    <style>
        .oracle-bubble {
            position: fixed;
            bottom: 30px;
            left: 30px;
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            cursor: pointer;
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.5);
            z-index: 99999;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 3px solid rgba(255, 255, 255, 0.2);
        }
        .oracle-bubble:hover {
            transform: scale(1.1) translateY(-5px);
            box-shadow: 0 12px 35px rgba(99, 102, 241, 0.7);
        }
        .oracle-bubble .status-dot {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 15px;
            height: 15px;
            background: #10b981;
            border-radius: 50%;
            border: 2px solid white;
            animation: pulse-green 2s infinite;
        }

        .oracle-window {
            position: fixed;
            bottom: 110px;
            left: 30px;
            width: 350px;
            height: 500px;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            display: none;
            flex-direction: column;
            z-index: 99999;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            overflow: hidden;
            animation: slideUp 0.4s ease-out;
        }
        .oracle-header {
            background: linear-gradient(90deg, #6366f1, #a855f7);
            padding: 15px 20px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .oracle-header h3 { margin: 0; font-size: 16px; display: flex; align-items: center; gap: 10px; }
        .oracle-chat { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; scroll-behavior: smooth; }
        .oracle-msg { padding: 10px 15px; border-radius: 15px; font-size: 14px; max-width: 85%; line-height: 1.5; }
        .msg-ai { background: rgba(255,255,255,0.05); color: #e2e8f0; align-self: flex-start; border-bottom-left-radius: 2px; border: 1px solid rgba(255,255,255,0.05); }
        .msg-user { background: #6366f1; color: white; align-self: flex-end; border-bottom-right-radius: 2px; }
        
        .oracle-input { padding: 15px; background: rgba(0,0,0,0.2); display: flex; gap: 10px; border-top: 1px solid rgba(255,255,255,0.05); }
        .oracle-input input {
            flex: 1; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            padding: 10px 15px; border-radius: 10px; color: white; outline: none; font-size: 14px;
        }
        .oracle-input button {
            background: #6366f1; border: none; color: white; width: 40px; height: 40px; border-radius: 10px; cursor: pointer;
        }

        .typing { font-style: italic; color: #94a3b8; font-size: 12px; margin-bottom: 5px; display: none; }

        @keyframes pulse-green { 0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); } 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>

    <div class="oracle-bubble" id="oracleBubble" onclick="toggleOracle()" title="Hỏi Lão Tiên Tri GTLM">
        🔮
        <div class="status-dot"></div>
    </div>

    <div class="oracle-window" id="oracleWindow">
        <div class="oracle-header">
            <h3><span>🔮</span> Lão Tiên Tri GTLM</h3>
            <span onclick="toggleOracle()" style="cursor:pointer; opacity:0.7">✕</span>
        </div>
        <div class="oracle-chat" id="oracleChat">
            <div class="oracle-msg msg-ai">Chào tiểu tử! Hôm nay vận khí của ngươi thế nào? Có muốn lão phán cho một quẻ hay giải đáp luật chơi trận địa không?</div>
        </div>
        <div class="typing" id="oracleTyping" style="margin-left: 20px;">Lão đang bấm độn...</div>
        <form class="oracle-input" onsubmit="sendToOracle(event)">
            <input type="text" id="oracleInput" placeholder="Hỏi về xác suất, luật chơi, dự đoán..." autocomplete="off">
            <button type="submit"><i class="fa fa-paper-plane"></i></button>
        </form>
    </div>

    <script>
        function toggleOracle() {
            const win = document.getElementById('oracleWindow');
            const isVisible = win.style.display === 'flex';
            win.style.display = isVisible ? 'none' : 'flex';
            if (!isVisible) {
                document.getElementById('oracleInput').focus();
            }
        }

        function sendToOracle(e) {
            e.preventDefault();
            const input = document.getElementById('oracleInput');
            const text = input.value.trim();
            if (!text) return;

            appendMsg(text, 'user');
            input.value = '';

            const typing = document.getElementById('oracleTyping');
            typing.style.display = 'block';

            const formData = new FormData();
            formData.append('question', text);

            fetch('api_npc_oracle.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                typing.style.display = 'none';
                if (data.success) {
                    appendMsg(data.answer, 'ai');
                }
            })
            .catch(() => {
                typing.style.display = 'none';
                appendMsg("Lão đang mệt, hãy quay lại sau ván giao lưu tới.", 'ai');
            });
        }

        function appendMsg(text, type) {
            const chat = document.getElementById('oracleChat');
            const div = document.createElement('div');
            div.className = `oracle-msg msg-${type}`;
            div.textContent = text;
            chat.appendChild(div);
            chat.scrollTop = chat.scrollHeight;
        }

        // 🏆 SHARE WIN CARD: Hiển thị thẻ khoe chiến tích khi húp đậm
        function showShareCard(amount, game) {
            const modal = document.getElementById('shareWinModal');
            document.getElementById('shareWinAmount').textContent = amount.toLocaleString();
            document.getElementById('shareWinGame').textContent = game;
            document.getElementById('shareWinTime').textContent = new Date().toLocaleTimeString();
            
            // Link ref thực tế (giả định dùng Iduser làm mã ref)
            const userId = '<?= $_SESSION['Iduser'] ?>';
            document.getElementById('shareRefLink').value = window.location.origin + window.location.pathname.replace('index.php', '') + 'register.php?ref=' + userId;
            
            modal.style.display = 'flex';
        }

        function closeShareCard() {
            document.getElementById('shareWinModal').style.display = 'none';
        }

        function copyRefLink() {
            const input = document.getElementById('shareRefLink');
            input.select();
            document.execCommand('copy');
            Swal.fire({ title: 'Đã sao chép!', text: 'Gửi link này cho bạn bè để húp 1% hoa hồng lộc!', icon: 'success', timer: 2000, showConfirmButton: false });
        }
    </script>

    <style>
        .share-card-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85); backdrop-filter: blur(10px);
            display: none; align-items: center; justify-content: center; z-index: 100000;
            animation: fadeIn 0.3s ease;
        }
        .share-card-content {
            background: linear-gradient(135deg, #1e1b4b 0%, #020617 100%);
            width: 380px; border-radius: 30px; border: 2px solid #fbbf24;
            padding: 30px; position: relative; text-align: center;
            box-shadow: 0 0 50px rgba(251, 191, 36, 0.3);
        }
        .badge-win {
            background: #fbbf24; color: #000; font-weight: 900; padding: 5px 20px;
            border-radius: 20px; font-size: 14px; letter-spacing: 2px;
        }
        .close-card {
            position: absolute; top: 20px; right: 20px; background: none; border: none;
            color: white; font-size: 20px; cursor: pointer; opacity: 0.5;
        }
        .share-card-body h2 {
            font-size: 42px; font-weight: 900; margin: 20px 0 0 0; color: #fbbf24;
            text-shadow: 0 0 20px rgba(251, 191, 36, 0.5);
        }
        .share-card-body p { color: #94a3b8; font-weight: 700; margin-bottom: 20px; }
        .share-card-info { font-size: 12px; color: #64748b; background: rgba(255,255,255,0.05); padding: 8px; border-radius: 10px; }
        
        .ref-link-box { margin-top: 30px; text-align: left; }
        .ref-link-box small { color: #64748b; margin-bottom: 5px; display: block; }
        .link-input { display: flex; gap: 10px; }
        .link-input input {
            flex: 1; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            color: #fff; padding: 10px; border-radius: 10px; font-size: 11px;
        }
        .link-input button { background: #fbbf24; border: none; border-radius: 10px; width: 40px; cursor: pointer; }
        
        .btn-share-social {
            margin-top: 20px; width: 100%; padding: 15px; border-radius: 15px; border: none;
            background: #1877f2; color: white; font-weight: 800; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>

    <div id="shareWinModal" class="share-card-overlay">
        <div class="share-card-content">
            <button class="close-card" onclick="closeShareCard()">✕</button>
            <div class="share-card-header">
                <span class="badge-win">CHIẾN TÍCH TRẬN ĐỊA</span>
            </div>
            <div class="share-card-body">
                <h2 id="shareWinAmount">0</h2>
                <p>GTLM ĐÃ VỀ KHO</p>
                <div class="share-card-info">
                    <span id="shareWinGame">Game Name</span> • <span id="shareWinTime">Time</span>
                </div>
            </div>
            <div class="share-card-footer">
                <div class="ref-link-box">
                    <small>Mời bạn húp lộc, nhận 1% hoa hồng thụ động:</small>
                    <div class="link-input">
                        <input type="text" id="shareRefLink" readonly value="">
                        <button onclick="copyRefLink()"><i class="fa fa-copy"></i></button>
                    </div>
                </div>
                <button class="btn-share-social" onclick="Swal.fire('Tính năng đang mở!', 'Hệ thống đang kết nối tới API Facebook...', 'info')">
                    <i class="fab fa-facebook"></i> KHOE LÊN FACEBOOK
                </button>
            </div>
        </div>
    </div>
    <script src="assets/js/event_banner.js"></script>
    <!-- Flash Event Checker -->
    <script>
        function checkFlashEvent() {
            $.get('api_flash_event.php', function(res) {
                if (res.active) {
                    $('#flashEventBanner').show();
                    $('#feMultiplier').text('x' + res.multiplier);
                    
                    // Simple countdown
                    const endDate = new Date(res.end_time).getTime();
                    const now = new Date(res.current_time).getTime(); // Sync with server roughly
                    let distance = endDate - now;
                    
                    const interval = setInterval(function() {
                        distance -= 1000;
                        if (distance < 0) {
                            clearInterval(interval);
                            $('#flashEventBanner').slideUp();
                            return;
                        }
                        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                        
                        $('#feCountdown').text(
                            String(hours).padStart(2, '0') + ':' + 
                            String(minutes).padStart(2, '0') + ':' + 
                            String(seconds).padStart(2, '0')
                        );
                    }, 1000);
                }
            }, 'json');
        }
        
        $(document).ready(function() {
            checkFlashEvent();
        });
    </script>
</body>






</html>