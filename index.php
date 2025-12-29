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
    $userProgress = up_get_progress($conn, (int)$userId);
    $seasonLevel = isset($userProgress['level']) ? (int)$userProgress['level'] : 1;
    $seasonXp = isset($userProgress['xp']) ? (int)$userProgress['xp'] : 0;
    $seasonRequiredXp = up_required_xp_for_level($seasonLevel);
    $seasonProgressPercent = $seasonRequiredXp > 0 ? min(100, round(($seasonXp / $seasonRequiredXp) * 100)) : 0;

    // Referral: lấy mã giới thiệu của user
    $referralCode = ref_get_or_create_code($conn, (int)$userId);
    
    // Load theme (sử dụng load_theme.php để đồng nhất)
    require_once 'load_theme.php';
    
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

    // Kiểm tra và cấp danh hiệu rank (chạy mỗi lần load trang)
    // Kiểm tra file và bảng tồn tại trước khi gọi
    $checkAchievementsTable = $conn->query("SHOW TABLES LIKE 'achievements'");
    if ($checkAchievementsTable && $checkAchievementsTable->num_rows > 0 && file_exists('api_check_rank_achievements.php')) {
        require_once 'api_check_rank_achievements.php';
        if (function_exists('checkAndAwardRankAchievements')) {
            checkAndAwardRankAchievements($conn);
        }
    }
    
    // Lấy dữ liệu bảng xếp hạng top 10 người có số dư cao nhất
    // Check if avatar_frame_id column exists
    $checkColumnSql = "SHOW COLUMNS FROM users LIKE 'avatar_frame_id'";
    $checkColumnResult = $conn->query($checkColumnSql);
    
    if ($checkColumnResult && $checkColumnResult->num_rows > 0) {
        // Column exists
        $sqlRank = "SELECT u.Name, u.Money, u.ImageURL, u.active_title_id, u.avatar_frame_id, 
                    a.icon as title_icon, a.name as title_name
                    FROM users u
                    LEFT JOIN achievements a ON u.active_title_id = a.id
                    ORDER BY u.Money DESC LIMIT 10";
    } else {
        // Column doesn't exist
        $sqlRank = "SELECT u.Name, u.Money, u.ImageURL, u.active_title_id, 
                    a.icon as title_icon, a.name as title_name
                    FROM users u
                    LEFT JOIN achievements a ON u.active_title_id = a.id
                    ORDER BY u.Money DESC LIMIT 10";
    }
    $resultRank = $conn->query($sqlRank);
    $ranking = [];
    if ($resultRank) {
        while ($row = $resultRank->fetch_assoc()) {
            $ranking[] = $row;
        }
    }

    // Game gần đây cho "Tiếp tục chơi"
    $recentGames = [];
    $checkGameHistory = $conn->query("SHOW TABLES LIKE 'game_history'");
    if ($checkGameHistory && $checkGameHistory->num_rows > 0) {
        $recentSql = "SELECT game_name, MAX(played_at) AS last_played
                      FROM game_history
                      WHERE user_id = ?
                      GROUP BY game_name
                      ORDER BY last_played DESC
                      LIMIT 6";
        $recentStmt = $conn->prepare($recentSql);
        if ($recentStmt) {
            $recentStmt->bind_param("i", $userId);
            $recentStmt->execute();
            $recentResult = $recentStmt->get_result();
            $map = [
                'Bầu Cua' => 'baucua.php',
                'Blackjack' => 'bj.php',
                'Slot Machine' => 'slot.php',
                'Roulette' => 'roulette.php',
                'Coin Flip' => 'coinflip.php',
                'RPS' => 'rps.php',
                'Xóc Đĩa' => 'xocdia.php',
                'Bot' => 'bot.php',
                'Vòng Quay' => 'vq.php',
                'Vietlott' => 'vietlott.php',
                'Cơ hội triệu phú' => 'cs.php',
                'Hộp Mù' => 'hopmu.php',
                'Rút Thăm' => 'ruttham.php',
                'Đua Thú' => 'duangua.php',
                'Đoán Số' => 'number.php',
                'Poker' => 'poker.php',
                'Bingo' => 'bingo.php',
                'Dice' => 'dice.php',
                'Minesweeper' => 'minesweeper.php',
                'Memory Game' => 'memory.php',
                'Tic Tac Toe' => 'tictactoe.php',
                'Snake Game' => 'snake.php',
                '2048 Game' => 'game2048.php',
                'Flappy Bird' => 'flappybird.php',
            ];
            while ($row = $recentResult->fetch_assoc()) {
                $name = $row['game_name'];
                if (isset($map[$name])) {
                    $recentGames[] = [
                        'name' => $name,
                        'file' => $map[$name],
                        'last_played' => $row['last_played'],
                    ];
                }
            }
            $recentStmt->close();
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
                // Cập nhật tiền người dùng (sử dụng prepared statement để tránh SQL injection)
                $reward = (float)$gift['reward'];
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

                $giftMessage = '<div class="message success">🎉 Chúc mừng! Bạn nhận được <strong>' . number_format($reward, 0, ',', '.') . ' VNĐ</strong> từ mã quà tặng!</div>';
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
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
        <link rel="stylesheet" href="assets/css/dashboard-enhancements.css">
        <link rel="stylesheet" href="assets/css/offline-detector.css">
        <link rel="stylesheet" href="assets/css/reading-progress.css">
        <link rel="stylesheet" href="assets/css/drag-drop.css">
        <link rel="stylesheet" href="assets/css/share-buttons.css">
        <link rel="stylesheet" href="assets/css/user-feedback.css">
        <link rel="stylesheet" href="assets/css/dashboard-enhanced.css">
        <link rel="stylesheet" href="assets/css/game-statistics.css">
        <link rel="stylesheet" href="assets/css/mobile-optimizations.css">
        <link rel="stylesheet" href="assets/css/sound-control.css">
        <link rel="stylesheet" href="assets/css/performance-optimizations.css">

        <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
        <title>Trang Chủ - Giải Trí Lành Mạnh</title>
        <style>
            body {
                cursor: url('chuot.png'), url('../chuot.png'), auto !important;
                background: <?= $bgGradientCSS ?>;
                background-attachment: fixed;
            }
            
            * {
                cursor: inherit;
            }
            
            button, a, input[type="button"], input[type="submit"], label, select, input[type="text"] {
                cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
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
                0%, 100% {
                    background-position: 0% 50%;
                }
                50% {
                    background-position: 100% 50%;
                }
            }
            
            @keyframes pulseGlow {
                0%, 100% {
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
            
            .game-link:nth-child(1) { animation-delay: 0.1s; }
            .game-link:nth-child(2) { animation-delay: 0.15s; }
            .game-link:nth-child(3) { animation-delay: 0.2s; }
            .game-link:nth-child(4) { animation-delay: 0.25s; }
            .game-link:nth-child(5) { animation-delay: 0.3s; }
            .game-link:nth-child(6) { animation-delay: 0.35s; }
            .game-link:nth-child(7) { animation-delay: 0.4s; }
            .game-link:nth-child(8) { animation-delay: 0.45s; }
            .game-link:nth-child(9) { animation-delay: 0.5s; }
            .game-link:nth-child(10) { animation-delay: 0.55s; }
            .game-link:nth-child(11) { animation-delay: 0.6s; }
            .game-link:nth-child(12) { animation-delay: 0.65s; }
            .game-link:nth-child(13) { animation-delay: 0.7s; }
            .game-link:nth-child(14) { animation-delay: 0.75s; }
            .game-link:nth-child(15) { animation-delay: 0.8s; }
            .game-link:nth-child(16) { animation-delay: 0.85s; }
            .game-link:nth-child(17) { animation-delay: 0.9s; }
            .game-link:nth-child(18) { animation-delay: 0.95s; }
            .game-link:nth-child(19) { animation-delay: 1s; }
            .game-link:nth-child(20) { animation-delay: 1.05s; }

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
            
            .balance-display > * {
                position: relative;
                z-index: 1;
            }
            
            .balance-display a {
                position: relative;
                z-index: 10 !important;
                pointer-events: auto !important;
            }
            
            @keyframes balancePulse {
                0%, 100% {
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
            
            .ranking tr:nth-child(1) { animation-delay: 0.1s; }
            .ranking tr:nth-child(2) { animation-delay: 0.2s; }
            .ranking tr:nth-child(3) { animation-delay: 0.3s; }
            .ranking tr:nth-child(4) { animation-delay: 0.4s; }
            .ranking tr:nth-child(5) { animation-delay: 0.5s; }
            .ranking tr:nth-child(6) { animation-delay: 0.6s; }
            .ranking tr:nth-child(7) { animation-delay: 0.7s; }
            .ranking tr:nth-child(8) { animation-delay: 0.8s; }
            .ranking tr:nth-child(9) { animation-delay: 0.9s; }
            .ranking tr:nth-child(10) { animation-delay: 1s; }
            
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
            
            .info-column .info, .info-column .gift {
                animation: fadeIn 0.6s ease;
            }
            
            /* Fix ranking table alignment */
            .ranking {
                overflow-x: auto;
                max-width: 100%;
            }
            
            .ranking table {
                table-layout: fixed;
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
            }
            
            .ranking th,
            .ranking td {
                padding: 12px 10px;
                vertical-align: middle;
            }
            
            .ranking th:nth-child(1),
            .ranking td:nth-child(1) {
                width: 10%;
                min-width: 50px;
                text-align: center;
            }
            
            .ranking th:nth-child(2),
            .ranking td:nth-child(2) {
                width: 15%;
                min-width: 80px;
                text-align: center;
                padding: 8px 10px;
                overflow: hidden;
            }
            
            .ranking td:nth-child(2) {
                vertical-align: middle;
            }
            
            .ranking td:nth-child(2) .avatar-border {
                width: 50px;
                height: 50px;
                margin: 0 auto;
                border: 2px solid var(--border-color);
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
                width: 30%;
                min-width: 120px;
                text-align: left;
                padding-left: 15px;
                word-break: break-word;
            }
            
            .ranking th:nth-child(4),
            .ranking td:nth-child(4) {
                width: 45%;
                min-width: 150px;
                text-align: right;
                padding-right: 15px;
                padding-left: 10px;
            }
            
            .ranking td:nth-child(4) {
                font-size: 12px;
                line-height: 1.4;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            .ranking td:nth-child(4):hover {
                overflow: visible;
                white-space: normal;
                word-break: break-word;
                z-index: 100;
                position: relative;
                background: rgba(255, 255, 255, 0.98) !important;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
                border-radius: var(--border-radius);
                padding: 12px 15px;
            }
            
            /* Đảm bảo container không tràn */
            .container {
                overflow-x: hidden;
                max-width: 100%;
            }
            
            .info-column {
                overflow-x: hidden;
                max-width: 100%;
            }
            
            /* Three.js canvas background */
            #threejs-background {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: -1;
                pointer-events: none;
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
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
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
                0% { transform: scale(1); }
                50% { transform: scale(1.2); }
                100% { transform: scale(1); }
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
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.1); color: var(--success-color); }
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
                from, to { border-color: transparent; }
                50% { border-color: currentColor; }
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
                0% { transform: translateX(-100%); }
                100% { transform: translateX(100%); }
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
                z-index: 1000;
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
                background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 50%, #c44569 100%);
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
                0%, 100% {
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
                background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
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
            
            .favorite-games-widget h3 {
                margin: 0 0 15px 0;
                font-size: 18px;
                font-weight: 700;
            }
            
            .favorite-games-list {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            
            .favorite-game-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 12px 15px;
                background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
                border: 2px solid rgba(102, 126, 234, 0.2);
                border-radius: var(--border-radius);
                transition: all 0.3s ease;
                text-decoration: none;
                color: var(--text-dark);
            }
            
            .favorite-game-item:hover {
                transform: translateX(5px);
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
                border-color: var(--primary-color);
            }
            
            .favorite-game-info {
                display: flex;
                align-items: center;
                gap: 12px;
                flex: 1;
            }
            
            .favorite-game-icon {
                font-size: 24px;
            }
            
            .favorite-game-details {
                flex: 1;
            }
            
            .favorite-game-name {
                font-weight: 600;
                color: var(--primary-color);
                margin-bottom: 3px;
            }
            
            .favorite-game-stats {
                font-size: 12px;
                color: var(--text-light);
            }
            
            .favorite-game-badge {
                background: linear-gradient(135deg, var(--secondary-color), var(--secondary-dark));
                color: white;
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 700;
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
                0%, 100% {
                    transform: scale(1);
                }
                50% {
                    transform: scale(1.2);
                }
            }
        </style>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="assets/js/dashboard-widgets.js"></script>
        <script src="assets/js/dashboard-enhanced.js"></script>
        <script src="assets/js/game-statistics.js"></script>
        <script src="assets/js/database-optimizer.js"></script>
        <script src="assets/js/api-cache-manager.js"></script>
        <script src="assets/js/performance-optimizer.js"></script>
        <script src="assets/js/performance-advanced.js"></script>
        <script src="assets/js/bundle-optimizer.js"></script>
        <script src="assets/js/performance-advanced.js"></script>
        <script src="assets/js/request-queue.js"></script>
        <script src="assets/js/memory-manager.js"></script>
        <script src="assets/js/image-optimizer.js"></script>
        <script src="assets/js/sound-effects.js"></script>
        <script src="assets/js/quick-actions.js"></script>
        <script src="assets/js/offline-detector.js"></script>
        <script src="assets/js/notifications-enhancer.js"></script>
        <script src="assets/js/performance-optimizer.js"></script>
        <script src="assets/js/theme-preview.js"></script>
        <script src="assets/js/auto-refresh.js"></script>
        <script src="assets/js/reading-progress.js"></script>
        <script src="assets/js/back-to-top-enhanced.js"></script>
        <script src="assets/js/drag-drop.js"></script>
        <script src="assets/js/share-buttons.js"></script>
        <script src="assets/js/error-tracker.js"></script>
        <script src="assets/js/user-feedback.js"></script>
        <script src="assets/js/analytics.js"></script>
        <script src="assets/js/critical-css-loader.js"></script>
        <script src="assets/js/resource-hints.js"></script>
        <script src="register-service-worker.js"></script>
        <script src="assets/js/feature-tests.js"></script>
    </head>
    <body>
        <canvas id="threejs-background"></canvas>
        <div class="header">
            <h1 class="welcome">Chào mừng, <?php echo htmlspecialchars($user['Name']); ?>!</h1>
            <a href="preview_themes.php" class="theme-button" id="themeButton" title="Xem trước themes với full background">
                <span class="theme-icon">🎨</span>
                <span class="theme-text">Xem Themes</span>
            </a>
            <div class="daidien">
                <?php
                // Get user avatar and avatar frame (with error handling)
                $avatarUrl = 'images.ico';
                $avatarFrameImage = null;
                
                // First try to get avatar with frame
                $avatarSql = "SELECT u.ImageURL";
                // Check if avatar_frame_id column exists
                $checkColumnSql = "SHOW COLUMNS FROM users LIKE 'avatar_frame_id'";
                $checkColumnResult = $conn->query($checkColumnSql);
                
                if ($checkColumnResult && $checkColumnResult->num_rows > 0) {
                    // Column exists, try to join with avatar_frames
                    $checkTableSql = "SHOW TABLES LIKE 'avatar_frames'";
                    $checkTableResult = $conn->query($checkTableSql);
                    
                    if ($checkTableResult && $checkTableResult->num_rows > 0) {
                        // Both column and table exist
                        $avatarSql = "SELECT u.ImageURL, u.avatar_frame_id, af.ImageURL AS avatar_frame_image 
                                      FROM users u 
                                      LEFT JOIN avatar_frames af ON u.avatar_frame_id = af.id 
                                      WHERE u.Iduser = ?";
                    } else {
                        // Table doesn't exist, just get avatar
                        $avatarSql = "SELECT u.ImageURL FROM users u WHERE u.Iduser = ?";
                    }
                } else {
                    // Column doesn't exist, just get avatar
                    $avatarSql = "SELECT u.ImageURL FROM users u WHERE u.Iduser = ?";
                }
                
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
                } else {
                    // Fallback: simple query
                    $simpleSql = "SELECT ImageURL FROM users WHERE Iduser = ?";
                    $simpleStmt = $conn->prepare($simpleSql);
                    if ($simpleStmt) {
                        $simpleStmt->bind_param("i", $userId);
                        $simpleStmt->execute();
                        $simpleResult = $simpleStmt->get_result();
                        if ($simpleResult) {
                            $simpleData = $simpleResult->fetch_assoc();
                            if ($simpleData) {
                                $avatarUrl = !empty($simpleData['ImageURL']) ? htmlspecialchars($simpleData['ImageURL']) : 'images.ico';
                            }
                        }
                        $simpleStmt->close();
                    }
                }
                ?>
                <div class="avatar-wrapper">
                    <?php if ($avatarFrameImage): ?>
                        <div class="avatar-frame-overlay">
                            <img src="<?= $avatarFrameImage ?>" alt="Frame" 
                                 onerror="this.style.display='none'">
                        </div>
                    <?php endif; ?>
                    <img src="<?= $avatarUrl ?>" alt="Ảnh đại diện" 
                         style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;"
                         onerror="this.src='images.ico'">
                </div>
                <div class="dropdown-menu">
                    <a href="in4.php"><i class="fa-solid fa-user icon"></i> Hồ sơ</a>
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
            <!-- Cột GIỚI THIỆU, Điểm danh và Giftcode -->
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
                
                <!-- Dashboard Enhanced Widgets -->
                <div id="dashboard-widgets"></div>
                
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
                                <div class="personal-stat-value" id="statTotalGames">0</div>
                                <div class="personal-stat-label">Tổng game</div>
                            </div>
                        </div>
                        <div class="personal-stat-item">
                            <div class="personal-stat-icon">🏆</div>
                            <div class="personal-stat-content">
                                <div class="personal-stat-value" id="statWinRate">0%</div>
                                <div class="personal-stat-label">Tỷ lệ thắng</div>
                            </div>
                        </div>
                        <div class="personal-stat-item">
                            <div class="personal-stat-icon">💰</div>
                            <div class="personal-stat-content">
                                <div class="personal-stat-value" id="statTotalEarned">0</div>
                                <div class="personal-stat-label">Tổng kiếm được</div>
                            </div>
                        </div>
                        <div class="personal-stat-item">
                            <div class="personal-stat-icon">🎖️</div>
                            <div class="personal-stat-content">
                                <div class="personal-stat-value" id="statAchievements">0</div>
                                <div class="personal-stat-label">Thành tích</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quest Highlight Widget -->
                <div class="quest-widget" id="questWidget">
                    <div class="quest-widget-header">
                        <div>
                            <h3>🎯 Nhiệm vụ nổi bật</h3>
                            <div class="quest-widget-meta">
                                <span id="questWidgetDate">Đang tải...</span>
                                <span id="questWidgetRefresh">Lần cập nhật cuối: --:--</span>
                            </div>
                        </div>
                        <div class="quest-widget-actions">
                            <div class="quest-widget-toggle">
                                <button type="button" id="questToggleDaily" class="active">Hàng ngày</button>
                                <button type="button" id="questToggleWeekly">Hàng tuần</button>
                            </div>
                            <a href="quests.php" class="quest-widget-link">Quản lý</a>
                        </div>
                    </div>
                    <div class="quest-widget-summary">
                        <div class="summary-item">
                            <span class="summary-label">Hoàn thành</span>
                            <span class="summary-value" id="questSummaryCompleted">0/0</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Đã nhận thưởng</span>
                            <span class="summary-value" id="questSummaryClaimed">0</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Tiến độ chung</span>
                            <span class="summary-value" id="questSummaryPercent">0%</span>
                        </div>
                    </div>
                    <div class="quest-widget-list" id="questWidgetList">
                        <div class="quest-widget-empty">Đang tải nhiệm vụ...</div>
                    </div>
                </div>
                
                <!-- Activity Feed -->
                <div class="activity-feed">
                    <div class="feed-header">
                        <div>
                            <h3>🔥 Hoạt động nổi bật</h3>
                            <p id="activityFeedSubtitle">Mọi người đang ăn mừng khắp nơi!</p>
                        </div>
                        <div class="feed-actions">
                            <button type="button" id="refreshFeedBtn">Làm mới</button>
                        </div>
                    </div>
                    <div class="feed-list" id="activityFeedList">
                        <div class="feed-empty">Đang tải hoạt động...</div>
                    </div>
                </div>

                <div class="notifications-widget">
                    <h3>🔔 Thông báo gần đây</h3>
                    <div class="notif-list" id="notifWidgetList">
                        <div class="feed-empty">Đang tải thông báo...</div>
                    </div>
                </div>
                
                <!-- Quick Actions Widget -->
                <div class="quick-actions-widget">
                    <h3>⚡ Hành Động Nhanh</h3>
                    <div id="quickActionsContainer">
                        <div class="skeleton skeleton-text"></div>
                        <div class="skeleton skeleton-text"></div>
                        <div class="skeleton skeleton-text"></div>
                    </div>
                </div>
                
                <!-- Random Tips -->
                <div class="tips-section">
                    <h3>💡 Mẹo hay hôm nay</h3>
                    <div class="tip-content" id="tipContent">Đang tải...</div>
                </div>
                
                <div class="info">
                    <p><a href="about.php" class="btn tooltip" data-tooltip="Tìm hiểu thêm về trang web">📘 Giới thiệu</a></p>
                    <p><a href="shop.php" class="btn tooltip" data-tooltip="Mua theme và cursor đẹp">🛒 Cửa Hàng</a></p>
                    <p><a href="achievements.php" class="btn tooltip" data-tooltip="Xem tất cả danh hiệu">🏆 Danh Hiệu</a></p>
                    <p><a href="quests.php" class="btn tooltip" data-tooltip="Xem và hoàn thành nhiệm vụ hàng ngày/tuần">🎯 Nhiệm Vụ</a></p>
                    <p><a href="daily_challenges.php" class="btn tooltip" data-tooltip="Thử thách hàng ngày với phần thưởng hấp dẫn">🎯 Thử Thách Hàng Ngày</a></p>
                    <p><a href="streak_system.php" class="btn tooltip" data-tooltip="Chuỗi ngày chơi game để nhận bonus multiplier">🔥 Streak System</a></p>
                    <p><a href="weekly_leaderboard.php" class="btn tooltip" data-tooltip="Bảng xếp hạng tuần với phần thưởng hấp dẫn">🏆 Weekly Leaderboard</a></p>
                    <p><a href="achievement_notifications.php" class="btn tooltip" data-tooltip="Xem thông báo khi đạt danh hiệu mới">🔔 Achievement Notifications</a></p>
                    <p><a href="vip_system.php" class="btn tooltip" data-tooltip="Hệ thống VIP với nhiều đặc quyền và phần thưởng">👑 VIP System</a></p>
                    <p><a href="reward_points.php" class="btn tooltip" data-tooltip="Tích điểm khi chơi game và đổi lấy phần thưởng">⭐ Reward Points</a></p>
                    <p><a href="social_feed.php" class="btn tooltip" data-tooltip="Xem hoạt động của cộng đồng">📱 Social Feed</a></p>
                    <p><a href="statistics.php" class="btn tooltip" data-tooltip="Xem thống kê chi tiết về game và thành tích">📊 Thống Kê</a></p>
                    <p><a href="inventory.php" class="btn tooltip" data-tooltip="Xem và quản lý tất cả items đã mua">📦 Kho Đồ</a></p>
                    <p><a href="lucky_wheel.php" class="btn tooltip" data-tooltip="Quay wheel may mắn hàng ngày để nhận phần thưởng">🎡 Lucky Wheel</a></p>
                    <p><a href="gift.php" class="btn tooltip" data-tooltip="Tặng quà (tiền, items) cho người dùng khác">🎁 Tặng Quà</a></p>
                    <p><a href="guilds.php" class="btn tooltip" data-tooltip="Tạo hoặc tham gia guild để cùng nhau phát triển">🏆 Guild</a></p>
                    <p><a href="guild_leaderboard.php" class="btn tooltip" data-tooltip="Xem bảng xếp hạng các guild">🏅 Guild Leaderboard</a></p>
                    <p><a href="tournament.php" class="btn tooltip" data-tooltip="Tham gia giải đấu và giành phần thưởng lớn">🎯 Giải Đấu</a></p>
                    <p><a href="trivia.php" class="btn tooltip" data-tooltip="Kiểm tra kiến thức với các câu hỏi trắc nghiệm">📚 Trivia Quiz</a></p>
                    <p><a href="events.php" class="btn tooltip" data-tooltip="Tham gia các sự kiện đặc biệt để nhận phần thưởng độc quyền">🎉 Sự Kiện</a></p>
                    <p><a href="pvp_challenge.php" class="btn tooltip" data-tooltip="Thách đấu và đấu 1-1 với người chơi khác">⚔️ Thách Đấu PvP</a></p>
                    <p><a href="notifications.php" class="btn tooltip" id="notificationsLink" data-tooltip="Xem tất cả thông báo của bạn">🔔 Thông Báo <span id="notificationsBadge" style="display:none; margin-left:6px; padding:2px 6px; border-radius:999px; background:#e74c3c; color:#fff; font-size:11px; font-weight:700;">0</span></a></p>
                    <p><a href="daily_login.php" class="btn tooltip" data-tooltip="Nhận phần thưởng đăng nhập hàng ngày">🎁 Đăng Nhập Hàng Ngày</a></p>
                    <p><a href="leaderboard.php" class="btn tooltip" data-tooltip="Xem bảng xếp hạng người chơi">🏆 Bảng Xếp Hạng</a></p>
                    <p><a href="profile.php" class="btn tooltip" data-tooltip="Xem và chỉnh sửa hồ sơ của bạn">👤 Hồ Sơ</a></p>
                    <p><a href="marketplace.php" class="btn tooltip" data-tooltip="Mua bán và trao đổi items">🛒 Chợ Trao Đổi</a></p>
                    <p><a href="select_title.php" class="btn tooltip" data-tooltip="Chọn danh hiệu để hiển thị">👑 Chọn Danh Hiệu</a></p>
                    <p><a href="addimg.php" class="btn tooltip" data-tooltip="Thay đổi ảnh đại diện của bạn">📸 Cập Nhật Ảnh Đại Diện</a></p>
                    <h1 style="font-size: 22px; margin: 20px 0; color: var(--warning-color);">⚠️ Mấy con lợn vui lòng đọc trước khi chơi</h1>
                    <p><a href="chat.php" class="btn tooltip" data-tooltip="Trò chuyện với mọi người">💬 Chat Tổng</a></p>
                    <p><a href="khungchat.php" class="btn tooltip" data-tooltip="Chọn khung chat">🎨 Chọn Khung Chat</a></p>
                    <p><a href="khungavatar.php" class="btn tooltip" data-tooltip="Chọn khung avatar">🖼️ Chọn Khung Avatar</a></p>
                </div>
                <div class="info">
                    <div class="daily-checkin">
                        <h2>📅 Điểm danh mỗi ngày nhận quà!</h2>
                        <form method="post" action="diemdanh.php">
                            <button type="submit">✅ Điểm danh ngay</button>
                            
                        </form>
                        <?php if (isset($_SESSION['msg'])): ?>
                            <p style="color: green; font-weight: bold;"><?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?></p>
                        <?php endif; ?>
                        <h2>Cào Thẻ Test Nhân Phẩm Hằng Ngày!</h2>
                        <p><a href="caothe.php">Cào nhẹ tay, quà đầy tay!</a></p>
                    </div>
                </div>
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
                    💰 Số dư: <span class="balance-value" data-balance="<?= $user['Money'] ?>"><?php echo number_format($user['Money'], 0, ',', '.'); ?></span> VNĐ (ảo)
                    <?php if (!empty($userProgress)): ?>
                        <div style="margin-top: 10px; font-size: 14px; color: #333;">
                            🔥 Level: <strong><?= (int)$userProgress['level'] ?></strong>
                            &nbsp;•&nbsp;
                            XP: <strong><?= (int)$userProgress['xp'] ?></strong>
                            &nbsp;•&nbsp;
                            Streak đăng nhập: <strong><?= (int)$userProgress['login_streak'] ?></strong> ngày (tốt nhất: <?= (int)$userProgress['best_login_streak'] ?>)
                            &nbsp;•&nbsp;
                            <a href="leaderboard.php" style="color: var(--secondary-dark); font-weight: 600; text-decoration: underline; cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important; position: relative; z-index: 10; pointer-events: auto !important; display: inline-block;">
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
                    <div style="background: rgba(255, 255, 255, 0.95); padding: 15px; border-radius: var(--border-radius-lg); margin: 20px 0; font-size: 14px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                        <strong>🤝 Mời bạn bè cùng chơi</strong><br>
                        Mã giới thiệu của bạn: <code><?= htmlspecialchars($referralCode, ENT_QUOTES, 'UTF-8') ?></code><br>
                        Link mời nhanh:
                        <input type="text" readonly value="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/auth.php?ref=' . $referralCode, ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; margin-top: 6px; padding: 6px 8px; border-radius: var(--border-radius); border: 1px solid var(--border-color); font-size: 12px;" onclick="this.select();">
                        <small>✨ Bạn và bạn bè sẽ nhận thưởng coin khi hoàn tất đăng ký qua link này.</small>
                        
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid rgba(102, 126, 234, 0.2);">
                            <a href="pvp_challenge.php" style="display: block; padding: 12px 20px; background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white; text-decoration: none; border-radius: var(--border-radius); font-weight: 600; text-align: center; transition: all 0.3s ease; cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;">
                                ⚔️ Thách Đấu PvP 1-1
                            </a>
                            <small style="display: block; margin-top: 8px; text-align: center; color: var(--text-dark); opacity: 0.8;">Đấu 1-1 với bạn bè và giành chiến thắng!</small>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (defined('UP_EVENT_ACTIVE') && UP_EVENT_ACTIVE): ?>
                    <div style="margin-top: 15px; padding: 12px 16px; border-radius: var(--border-radius); background: rgba(255, 193, 7, 0.15); border: 1px solid rgba(255, 193, 7, 0.6); font-size: 14px;">
                        <strong>🎉 Sự kiện đang diễn ra:</strong> <?= htmlspecialchars(UP_EVENT_NAME, ENT_QUOTES, 'UTF-8') ?><br>
                        <span>💎 Thưởng đăng nhập và hoạt động được nhân <?= UP_EVENT_REWARD_MULTIPLIER ?> lần. <?= htmlspecialchars(UP_EVENT_LOGIN_BONUS_TEXT, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>
                
                <!-- Hiển thị danh hiệu hiện tại -->
                <?php if (!empty($user['title_icon'])): ?>
                    <div style="background: rgba(255, 255, 255, 0.95); padding: 15px; border-radius: var(--border-radius-lg); margin: 20px 0; text-align: center; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); animation: fadeInUp 0.6s ease;">
                        <div style="font-size: 32px; margin-bottom: 10px;">
                            <?= htmlspecialchars($user['title_icon'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div style="font-weight: 700; color: var(--primary-color); font-size: 18px;">
                            <?= htmlspecialchars($user['title_name'] ?? 'Danh hiệu', ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div style="font-size: 14px; color: var(--text-dark); margin-top: 5px;">
                            Xếp hạng: #<?= $userRank ?>
                        </div>
                        <a href="select_title.php" style="display: inline-block; margin-top: 10px; padding: 8px 16px; background: var(--secondary-color); color: white; text-decoration: none; border-radius: var(--border-radius); font-size: 14px; font-weight: 600; cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;">
                            Đổi danh hiệu
                        </a>
                    </div>
                <?php else: ?>
                    <div style="background: rgba(255, 255, 255, 0.95); padding: 15px; border-radius: var(--border-radius-lg); margin: 20px 0; text-align: center; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); animation: fadeInUp 0.6s ease;">
                        <div style="font-size: 24px; margin-bottom: 10px;">🏆</div>
                        <div style="font-weight: 700; color: var(--text-dark); font-size: 16px; margin-bottom: 10px;">
                            Chưa có danh hiệu
                        </div>
                        <div style="font-size: 14px; color: var(--text-dark); margin-bottom: 10px;">
                            Xếp hạng: #<?= $userRank ?>
                            <?php if ($userRank <= 10): ?>
                                <br><span style="color: var(--success-color); font-weight: 600;">✨ Bạn đang trong top 10! Hãy vào trang Danh Hiệu để nhận!</span>
                            <?php else: ?>
                                <br><span style="color: var(--warning-color);">Cố gắng lên top 10 để nhận danh hiệu!</span>
                            <?php endif; ?>
                        </div>
                        <a href="select_title.php" style="display: inline-block; margin-top: 5px; padding: 8px 16px; background: var(--secondary-color); color: white; text-decoration: none; border-radius: var(--border-radius); font-size: 14px; font-weight: 600; cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;">
                            Chọn danh hiệu
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($recentGames)): ?>
                <h3 style="margin-top: 20px; color: var(--primary-color);">⏱ Tiếp tục chơi</h3>
                <div class="game-grid" style="margin-bottom: 10px;">
                    <?php foreach ($recentGames as $g): ?>
                        <a href="<?= htmlspecialchars($g['file'], ENT_QUOTES, 'UTF-8') ?>" class="game-link">
                            <span>🎮 <?= htmlspecialchars($g['name'], ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Favorite Games Widget -->
                <div class="favorite-games-widget" id="favoriteGamesWidget" style="margin-top: 20px;">
                    <h3 style="color: var(--primary-color); margin-bottom: 15px;">⭐ Game Yêu Thích Của Bạn</h3>
                    <div class="favorite-games-list" id="favoriteGamesList">
                        <div style="text-align: center; padding: 20px; color: var(--text-light);">Đang tải...</div>
                    </div>
                </div>

                <h3 style="margin-top: 20px; color: var(--primary-color);">🎮 Danh sách game</h3>
                <div class="game-grid">
                    <a href="baucua.php" class="game-link"><span>🎲 Bầu Cua</span></a>
                    <a href="bj.php" class="game-link"><span>🃏 Black Jack</span></a>
                    <a href="ac.php" class="game-link"><span>🎯 Arcade</span></a>
                    <a href="bot.php" class="game-link"><span>🎴 Đoán màu bài</span></a>
                    <a href="xocdia.php" class="game-link"><span>🎲 Xóc Đĩa</span></a>
                    <a href="vq.php" class="game-link"><span>🎡 Vòng Quay</span></a>
                    <a href="vietlott.php" class="game-link"><span>🎫 Vietlott</span></a>
                    <a href="cs.php" class="game-link"><span>💎 Cơ hội triệu phú</span></a>
                    <a href="hopmu.php" class="game-link"><span>🎁 Hộp Mú</span></a>
                    <a href="ruttham.php" class="game-link"><span>🎟️ Rút Thăm</span></a>
                    <a href="duangua.php" class="game-link"><span>🐎 Đua Thú</span></a>
                    <a href="dice.php" class="game-link"><span>🎲 Lắc Xí Ngầu</span></a>
                    <a href="slot.php" class="game-link"><span>🎰 Slot Machine</span></a>
                    <a href="roulette.php" class="game-link"><span>🎡 Roulette</span></a>
                    <a href="coinflip.php" class="game-link"><span>🪙 Tung Đồng Xu</span></a>
                    <a href="rps.php" class="game-link"><span>✌️ Oẳn Tù Tì</span></a>
                    <a href="number.php" class="game-link"><span>🎯 Đoán Số</span></a>
                    <a href="poker.php" class="game-link"><span>🃏 Poker</span></a>
                    <a href="bingo.php" class="game-link"><span>🎱 Bingo</span></a>
                    <a href="minesweeper.php" class="game-link"><span>💣 Dò Mìn</span></a>
                    <a href="memory.php" class="game-link"><span>🧠 Memory Game</span></a>
                    <a href="tictactoe.php" class="game-link"><span>⭕ Cờ Caro</span></a>
                    <a href="snake.php" class="game-link"><span>🐍 Rắn Săn Mồi</span></a>
                    <a href="game2048.php" class="game-link"><span>🎯 2048 Game</span></a>
                    <a href="flappybird.php" class="game-link"><span>🐦 Flappy Bird</span></a>
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
                            <th>Số dư (VNĐ)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($ranking)): ?>
                            <?php foreach ($ranking as $index => $r): ?>
                                <tr>
                                    <td style="font-weight: 700; color: var(--primary-color);"><?= $index + 1 ?></td>
                                    <td>
                                        <div class="avatar-border" style="position: relative; width: 50px; height: 50px; margin: 0 auto;">
                                            <?php
                                            // Get avatar frame for ranking user (with error handling)
                                            $rankFrameImage = null;
                                            if (isset($r['avatar_frame_id']) && !empty($r['avatar_frame_id'])) {
                                                // Check if table exists
                                                $checkTableSql = "SHOW TABLES LIKE 'avatar_frames'";
                                                $checkTableResult = $conn->query($checkTableSql);
                                                
                                                if ($checkTableResult && $checkTableResult->num_rows > 0) {
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
                                            }
                                            ?>
                                            <?php if ($rankFrameImage): ?>
                                                <div style="position: absolute; top: -5px; left: -5px; width: calc(100% + 10px); height: calc(100% + 10px); z-index: 1; pointer-events: none !important; border-radius: 50%;">
                                                    <img src="<?= htmlspecialchars($rankFrameImage, ENT_QUOTES, 'UTF-8') ?>" 
                                                         alt="Frame" 
                                                         style="width: 100%; height: 100%; object-fit: contain; border-radius: 50%; pointer-events: none !important;"
                                                         onerror="this.style.display='none'">
                                                </div>
                                            <?php endif; ?>
                                            <img src="<?= !empty($r['ImageURL']) ? htmlspecialchars($r['ImageURL'], ENT_QUOTES, 'UTF-8') : 'images.ico' ?>" 
                                                alt="<?= htmlspecialchars($r['Name'], ENT_QUOTES, 'UTF-8') ?>" 
                                                style="position: relative; z-index: 2; width: 100%; height: 100%; border-radius: 50%; object-fit: cover; pointer-events: auto;"
                                                onerror="this.src='images.ico'">
                                        </div>
                                    </td>
                                    <td style="font-weight: 600;">
                                        <?php if (!empty($r['title_icon'])): ?>
                                            <span style="font-size: 20px; margin-right: 5px;" title="<?= htmlspecialchars($r['title_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($r['title_icon'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($r['Name'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td style="color: var(--success-color); font-weight: 700;" title="<?= number_format($r['Money'], 0, ',', '.') ?> VNĐ">
                                        <?= number_format($r['Money'], 0, ',', '.') ?> VNĐ
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
    </body>
    <script>
        let questWidgetType = 'daily';
        let questWidgetTimer = null;
        const QUEST_WIDGET_REFRESH_MS = 60 * 1000;
        let activityFeedTimer = null;
        const FEED_REFRESH_MS = 15000;
        let messagesTimer = null;
        const MESSAGES_REFRESH_MS = 10000;
        let notificationsTimer = null;
        const NOTIFICATIONS_REFRESH_MS = 15000;
        
        function renderQuestPill(quest) {
            const wrapper = document.createElement('div');
            wrapper.className = 'quest-pill';
            if (quest.claimed == 1 || quest.is_claimed == 1) {
                wrapper.classList.add('claimed');
            } else if (quest.is_completed == 1 || quest.is_completed === true) {
                wrapper.classList.add('completed');
            }
            
            const icon = document.createElement('div');
            icon.className = 'quest-pill-icon';
            icon.textContent = quest.icon || '🎯';
            
            const content = document.createElement('div');
            content.className = 'quest-pill-content';
            
            const title = document.createElement('div');
            title.className = 'quest-pill-title';
            title.textContent = quest.name || quest.challenge_name || 'Nhiệm vụ';
            
            const desc = document.createElement('div');
            desc.className = 'quest-pill-desc';
            desc.textContent = quest.description || '';
            
            const meta = document.createElement('div');
            meta.className = 'quest-pill-meta';
            const requirementValue = Number(quest.requirement || quest.requirement_value || 0);
            const progressValue = Number(quest.progress || quest.user_progress || 0);
            const rewardMoney = Number(quest.reward_money || 0);
            const rewardXp = Number(quest.reward_xp || 0);
            let rewardText = '';
            if (rewardMoney > 0) rewardText += `${rewardMoney.toLocaleString('vi-VN')} VNĐ`;
            if (rewardXp > 0) rewardText += (rewardText ? ' + ' : '') + `${rewardXp} XP`;
            meta.textContent = `${progressValue}/${requirementValue}${rewardText ? ' • Thưởng ' + rewardText : ''}`;
            
            const progressBar = document.createElement('div');
            progressBar.className = 'quest-pill-progress';
            const progressFill = document.createElement('span');
            const percent = requirementValue > 0 ? Math.max(0, Math.min(100, (progressValue / requirementValue) * 100)) : 0;
            progressFill.style.width = percent + '%';
            progressBar.appendChild(progressFill);
            
            content.appendChild(title);
            if (desc.textContent) {
                content.appendChild(desc);
            }
            content.appendChild(meta);
            content.appendChild(progressBar);
            
            // Add claim button if completed but not claimed
            if ((quest.is_completed == 1 || quest.is_completed === true) && !(quest.claimed == 1 || quest.is_claimed == 1)) {
                const claimBtn = document.createElement('button');
                claimBtn.className = 'quest-claim-btn';
                claimBtn.textContent = 'Nhận thưởng';
                claimBtn.onclick = (e) => {
                    e.stopPropagation();
                    claimChallengeReward(quest.id, questWidgetType);
                };
                content.appendChild(claimBtn);
            }
            
            wrapper.appendChild(icon);
            wrapper.appendChild(content);
            return wrapper;
        }
        
        function claimChallengeReward(challengeId, type) {
            const apiUrl = type === 'daily' ? 'api_daily_challenges.php' : 'api_weekly_challenges.php';
            $.post(apiUrl, {
                action: 'claim',
                challenge_id: challengeId
            }, function(response) {
                if (response.status === 'success') {
                    Swal.fire('🎉 Thành công', response.message, 'success');
                    loadQuestWidget(type, true);
                } else {
                    Swal.fire('❌ Lỗi', response.message || 'Không thể nhận thưởng', 'error');
                }
            }, 'json').fail(function() {
                Swal.fire('❌ Lỗi', 'Không thể kết nối server', 'error');
            });
        }
        
        function updateQuestWidgetUI(data) {
            const listEl = document.getElementById('questWidgetList');
            const dateEl = document.getElementById('questWidgetDate');
            const refreshEl = document.getElementById('questWidgetRefresh');
            const completedEl = document.getElementById('questSummaryCompleted');
            const claimedEl = document.getElementById('questSummaryClaimed');
            const percentEl = document.getElementById('questSummaryPercent');
            
            if (!data || data.status !== 'success') {
                listEl.innerHTML = '<div class="quest-widget-empty">Không thể tải nhiệm vụ. Vui lòng mở trang Nhiệm Vụ để xem chi tiết.</div>';
                dateEl.textContent = 'Không thể tải dữ liệu';
                refreshEl.textContent = 'Lần cập nhật cuối: --:--';
                completedEl.textContent = '0/0';
                claimedEl.textContent = '0';
                percentEl.textContent = '0%';
                return;
            }
            
            const summary = data.summary || {};
            const quests = data.quests || [];
            const total = summary.total || 0;
            const completed = summary.completed || 0;
            const claimed = summary.claimed || 0;
            const percent = summary.progress_percent || 0;
            
            completedEl.textContent = `${completed}/${total}`;
            claimedEl.textContent = claimed;
            percentEl.textContent = percent + '%';
            
            const questDate = summary.quest_date ? new Date(summary.quest_date + 'T00:00:00') : null;
            if (questDate && !isNaN(questDate.getTime())) {
                const label = summary.quest_type === 'weekly' ? 'Tuần bắt đầu' : 'Ngày';
                dateEl.textContent = `${label}: ${questDate.toLocaleDateString('vi-VN')}`;
            } else {
                dateEl.textContent = 'Không xác định được ngày nhiệm vụ';
            }
            const now = new Date();
            refreshEl.textContent = `Lần cập nhật cuối: ${now.toLocaleTimeString('vi-VN')}`;
            
            listEl.innerHTML = '';
            if (!quests.length) {
                listEl.innerHTML = '<div class="quest-widget-empty">Hoàn thành tất cả nhiệm vụ rồi! 🎉</div>';
                return;
            }
            
            quests.forEach((quest) => {
                listEl.appendChild(renderQuestPill(quest));
            });
        }
        
        function scheduleQuestWidgetRefresh() {
            if (questWidgetTimer) {
                clearTimeout(questWidgetTimer);
            }
            questWidgetTimer = setTimeout(() => loadQuestWidget(questWidgetType, false), QUEST_WIDGET_REFRESH_MS);
        }
        
        function setQuestWidgetType(type) {
            if (questWidgetType === type) {
                loadQuestWidget(type, true);
                return;
            }
            questWidgetType = type;
            const dailyBtn = document.getElementById('questToggleDaily');
            const weeklyBtn = document.getElementById('questToggleWeekly');
            if (dailyBtn && weeklyBtn) {
                dailyBtn.classList.toggle('active', type === 'daily');
                weeklyBtn.classList.toggle('active', type === 'weekly');
            }
            loadQuestWidget(type, true);
        }
        
        function loadQuestWidget(type = 'daily', resetTimer = true) {
            questWidgetType = type;
            const url = `api_challenges_widget.php?type=${encodeURIComponent(type)}`;
            fetch(url, {
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Transform data to match expected format
                        const transformed = {
                            status: 'success',
                            summary: {
                                total: data.summary.total,
                                completed: data.summary.completed,
                                claimed: data.summary.claimed,
                                progress_percent: data.summary.percent,
                                quest_date: data.date || data.week_start,
                                quest_type: type
                            },
                            quests: data.challenges.map(c => ({
                                id: c.id,
                                name: c.challenge_name,
                                description: c.description,
                                progress: c.user_progress,
                                requirement: c.requirement_value,
                                is_completed: c.is_completed == 1,
                                claimed: c.claimed == 1,
                                reward_money: c.reward_money,
                                reward_xp: c.reward_xp
                            }))
                        };
                        updateQuestWidgetUI(transformed);
                    } else {
                        updateQuestWidgetUI(null);
                    }
                })
                .catch(() => updateQuestWidgetUI(null))
                .finally(() => {
                    if (resetTimer) {
                        scheduleQuestWidgetRefresh();
                    }
                });
        }
        
        function renderFeedCard(item) {
            const card = document.createElement('div');
            card.className = 'feed-card';
            if (item.type === 'big_win' && item.amount >= 1000000) {
                card.classList.add('highlight');
            }
            
            const avatarWrapper = document.createElement('div');
            avatarWrapper.className = 'feed-avatar';
            const avatarImg = document.createElement('img');
            avatarImg.src = item.user?.avatar || 'images.ico';
            avatarImg.alt = item.user?.name || 'Người chơi';
            avatarImg.onerror = () => { avatarImg.src = 'images.ico'; };
            avatarWrapper.appendChild(avatarImg);
            
            if (item.user?.avatar_frame) {
                const frameImg = document.createElement('img');
                frameImg.src = item.user.avatar_frame;
                frameImg.alt = 'Frame';
                frameImg.className = 'feed-avatar-frame';
                frameImg.onerror = () => { frameImg.style.display = 'none'; };
                avatarWrapper.appendChild(frameImg);
            }
            
            const content = document.createElement('div');
            content.className = 'feed-content';
            
            const message = document.createElement('div');
            message.className = 'feed-message';
            if (item.user?.title_icon) {
                message.innerHTML = `<span style="margin-right:6px;">${item.user.title_icon}</span>${item.message}`;
            } else {
                message.textContent = item.message;
            }
            
            const meta = document.createElement('div');
            meta.className = 'feed-meta';
            const timeSpan = document.createElement('span');
            timeSpan.textContent = item.time_ago || '';
            meta.appendChild(timeSpan);
            
            if (item.amount) {
                const amountSpan = document.createElement('span');
                amountSpan.textContent = `${number_format(item.amount, 0, ',', '.')} VNĐ`;
                meta.appendChild(amountSpan);
            }
            
            content.appendChild(message);
            content.appendChild(meta);
            
            card.appendChild(avatarWrapper);
            card.appendChild(content);
            return card;
        }
        
        function updateActivityFeed(data) {
            const listEl = document.getElementById('activityFeedList');
            const subtitleEl = document.getElementById('activityFeedSubtitle');
            if (!listEl) return;
            
            if (!data || data.status !== 'success') {
                listEl.innerHTML = '<div class="feed-empty">Không tải được hoạt động. Vui lòng thử lại sau.</div>';
                if (subtitleEl) {
                    subtitleEl.textContent = 'Có lỗi khi tải dữ liệu.';
                }
                return;
            }
            
            const notifications = data.notifications || [];
            listEl.innerHTML = '';
            if (!notifications.length) {
                listEl.innerHTML = '<div class="feed-empty">Chưa có hoạt động nổi bật nào.</div>';
                if (subtitleEl) {
                    subtitleEl.textContent = 'Hãy là người đầu tiên tạo highlight!';
                }
                return;
            }
            
            notifications.forEach(item => {
                listEl.appendChild(renderFeedCard(item));
            });
            
            if (subtitleEl) {
                const first = notifications[0];
                subtitleEl.textContent = `${first.user?.name || 'Ai đó'} vừa ${first.type === 'big_win' ? 'thắng lớn' : 'tạo highlight'}!`;
            }
        }
        
        function loadPersonalStats() {
            fetch('api_statistics.php', {
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.stats) {
                        const stats = data.stats.totals || {};
                        const achievementsCount = data.stats.achievementsCount || 0;
                        
                        // Animate numbers
                        animateValue('statTotalGames', 0, stats.totalGames || 0, 1000);
                        animateValue('statWinRate', 0, stats.winRate || 0, 1000, '%');
                        animateValue('statTotalEarned', 0, stats.totalEarned || 0, 1000, '', true);
                        animateValue('statAchievements', 0, achievementsCount, 1000);
                        
                        // Load favorite games
                        loadFavoriteGames(data.stats.gameStats || []);
                    } else {
                        // Set default values if API fails
                        document.getElementById('statTotalGames').textContent = '0';
                        document.getElementById('statWinRate').textContent = '0%';
                        document.getElementById('statTotalEarned').textContent = '0';
                        document.getElementById('statAchievements').textContent = '0';
                        loadFavoriteGames([]);
                    }
                })
                .catch(err => {
                    console.log('Personal stats load error:', err);
                    // Set default values on error
                    document.getElementById('statTotalGames').textContent = '0';
                    document.getElementById('statWinRate').textContent = '0%';
                    document.getElementById('statTotalEarned').textContent = '0';
                    document.getElementById('statAchievements').textContent = '0';
                    loadFavoriteGames([]);
                });
        }
        
        function loadFavoriteGames(gameStats) {
            const listEl = document.getElementById('favoriteGamesList');
            if (!listEl) return;
            
            if (!gameStats || gameStats.length === 0) {
                listEl.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--text-light);">Chưa có dữ liệu game. Hãy chơi game để xem thống kê!</div>';
                return;
            }
            
            // Sort by plays and take top 5
            const topGames = gameStats
                .sort((a, b) => b.plays - a.plays)
                .slice(0, 5);
            
            if (topGames.length === 0) {
                listEl.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--text-light);">Chưa có dữ liệu game. Hãy chơi game để xem thống kê!</div>';
                return;
            }
            
            const gameMap = {
                'Bầu Cua': { file: 'baucua.php', icon: '🎲' },
                'Blackjack': { file: 'bj.php', icon: '🃏' },
                'Slot Machine': { file: 'slot.php', icon: '🎰' },
                'Roulette': { file: 'roulette.php', icon: '🎡' },
                'Coin Flip': { file: 'coinflip.php', icon: '🪙' },
                'RPS': { file: 'rps.php', icon: '✌️' },
                'Xóc Đĩa': { file: 'xocdia.php', icon: '🎲' },
                'Bot': { file: 'bot.php', icon: '🎴' },
                'Vòng Quay': { file: 'vq.php', icon: '🎡' },
                'Vietlott': { file: 'vietlott.php', icon: '🎫' },
                'Cơ hội triệu phú': { file: 'cs.php', icon: '💎' },
                'Hộp Mù': { file: 'hopmu.php', icon: '🎁' },
                'Rút Thăm': { file: 'ruttham.php', icon: '🎟️' },
                'Đua Thú': { file: 'duangua.php', icon: '🐎' },
                'Đoán Số': { file: 'number.php', icon: '🎯' },
                'Poker': { file: 'poker.php', icon: '🃏' },
                'Bingo': { file: 'bingo.php', icon: '🎱' },
                'Dice': { file: 'dice.php', icon: '🎲' },
                'Minesweeper': { file: 'minesweeper.php', icon: '💣' },
                'Memory Game': { file: 'memory.php', icon: '🧠' },
                'Tic Tac Toe': { file: 'tictactoe.php', icon: '⭕' },
                'Snake Game': { file: 'snake.php', icon: '🐍' },
                '2048 Game': { file: 'game2048.php', icon: '🎯' },
                'Flappy Bird': { file: 'flappybird.php', icon: '🐦' }
            };
            
            listEl.innerHTML = '';
            topGames.forEach((game, index) => {
                const gameInfo = gameMap[game.game_name] || { file: '#', icon: '🎮' };
                const item = document.createElement('a');
                item.href = gameInfo.file;
                item.className = 'favorite-game-item';
                item.style.animationDelay = (index * 0.1) + 's';
                
                item.innerHTML = `
                    <div class="favorite-game-info">
                        <div class="favorite-game-icon">${gameInfo.icon}</div>
                        <div class="favorite-game-details">
                            <div class="favorite-game-name">${game.game_name}</div>
                            <div class="favorite-game-stats">${game.plays} lần chơi • Tỷ lệ thắng: ${game.win_rate}%</div>
                        </div>
                    </div>
                    <div class="favorite-game-badge">#${index + 1}</div>
                `;
                
                listEl.appendChild(item);
            });
        }
        
        function animateValue(elementId, start, end, duration, suffix = '', isMoney = false) {
            const element = document.getElementById(elementId);
            if (!element) return;
            
            const startTime = performance.now();
            const range = end - start;
            
            function update(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                // Easing function (ease-out)
                const easeProgress = 1 - Math.pow(1 - progress, 3);
                const current = start + (range * easeProgress);
                
                if (isMoney) {
                    element.textContent = number_format(Math.floor(current), 0, ',', '.');
                } else if (suffix === '%') {
                    element.textContent = current.toFixed(1) + suffix;
                } else {
                    element.textContent = Math.floor(current) + (suffix || '');
                }
                
                if (progress < 1) {
                    requestAnimationFrame(update);
                } else {
                    // Ensure final value is exact
                    if (isMoney) {
                        element.textContent = number_format(end, 0, ',', '.');
                    } else if (suffix === '%') {
                        element.textContent = end.toFixed(1) + suffix;
                    } else {
                        element.textContent = end + (suffix || '');
                    }
                }
            }
            
            requestAnimationFrame(update);
        }
        
        function scheduleActivityFeedRefresh() {
            if (activityFeedTimer) {
                clearTimeout(activityFeedTimer);
            }
            activityFeedTimer = setTimeout(() => loadActivityFeed(false), FEED_REFRESH_MS);
        }
        
        function loadActivityFeed(resetTimer = true) {
            fetch('api_get_notifications.php?limit=5', {
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(updateActivityFeed)
                .catch(() => updateActivityFeed(null))
                .finally(() => {
                    if (resetTimer) {
                        scheduleActivityFeedRefresh();
                    }
                });
        }

        function updateNotificationsWidget(data) {
            const listEl = document.getElementById('notifWidgetList');
            if (!listEl) return;
            if (!data || !data.success) {
                listEl.innerHTML = '<div class="feed-empty">Không tải được thông báo.</div>';
                return;
            }
            const items = data.notifications || [];
            if (!items.length) {
                listEl.innerHTML = '<div class="feed-empty">Chưa có thông báo nào.</div>';
                return;
            }
            listEl.innerHTML = '';
            items.slice(0, 5).forEach((n) => {
                const div = document.createElement('div');
                div.className = 'notif-item' + (!n.is_read ? ' unread' : '');
                const text = document.createElement('div');
                text.textContent = (n.icon || '🔔') + ' ' + (n.title || n.content || '');
                const time = document.createElement('span');
                time.className = 'time';
                time.textContent = new Date(n.created_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
                div.appendChild(text);
                div.appendChild(time);
                listEl.appendChild(div);
            });
        }
        
        function updateMessagesFab(data) {
            const fab = document.getElementById('messagesFab');
            const badge = document.getElementById('messagesBadge');
            if (!fab || !badge) return;
            
            if (!data || !data.success) {
                badge.style.display = 'none';
                return;
            }
            
            const count = data.count || 0;
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
                if (count > lastMessagesCount) {
                    showToast(`Bạn có ${count} tin nhắn riêng chưa đọc!`, 'success');
                }
            } else {
                badge.style.display = 'none';
            }
            lastMessagesCount = count;
        }
        
        function scheduleMessagesRefresh() {
            if (messagesTimer) {
                clearTimeout(messagesTimer);
            }
            messagesTimer = setTimeout(loadMessagesUnreadCount, MESSAGES_REFRESH_MS);
        }
        
        function loadMessagesUnreadCount() {
            fetch('api_friends.php?action=get_unread_count', {
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(updateMessagesFab)
                .catch(() => updateMessagesFab(null))
                .finally(() => {
                    scheduleMessagesRefresh();
                });
        }
        
        function updateNotificationsBadge(data) {
            const badge = document.getElementById('notificationsBadge');
            if (!badge) return;
            
            if (!data || !data.success) {
                badge.style.display = 'none';
                return;
            }
            
            const count = data.count || 0;
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'inline-block';
                if (count > lastNotificationsCount) {
                    showToast(`Bạn có ${count} thông báo mới!`, 'success');
                }
            } else {
                badge.style.display = 'none';
            }
            lastNotificationsCount = count;
        }
        
        function scheduleNotificationsRefresh() {
            if (notificationsTimer) {
                clearTimeout(notificationsTimer);
            }
            notificationsTimer = setTimeout(loadNotificationsUnreadCount, NOTIFICATIONS_REFRESH_MS);
        }
        
        function loadNotificationsUnreadCount() {
            fetch('api_notifications.php?action=get_unread_count', {
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(updateNotificationsBadge)
                .catch(() => updateNotificationsBadge(null))
                .finally(() => {
                    scheduleNotificationsRefresh();
                });
        }

        function loadNotificationsWidget() {
            fetch('api_notifications.php?action=get_list&limit=5', {
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(updateNotificationsWidget)
                .catch(() => updateNotificationsWidget(null));
        }
        
        let lastNotificationsCount = 0;
        let lastMessagesCount = 0;

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = 'toast ' + (type === 'error' ? 'error' : 'success');
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        // Đảm bảo cursor luôn hoạt động
        document.addEventListener('DOMContentLoaded', function() {
            const dailyBtn = document.getElementById('questToggleDaily');
            const weeklyBtn = document.getElementById('questToggleWeekly');
            if (dailyBtn) {
                dailyBtn.addEventListener('click', () => setQuestWidgetType('daily'));
            }
            if (weeklyBtn) {
                weeklyBtn.addEventListener('click', () => setQuestWidgetType('weekly'));
            }
            loadQuestWidget('daily', true);
            
            const refreshFeedBtn = document.getElementById('refreshFeedBtn');
            if (refreshFeedBtn) {
                refreshFeedBtn.addEventListener('click', () => loadActivityFeed(true));
            }
            loadActivityFeed(true);
            
            const messagesFab = document.getElementById('messagesFab');
            if (messagesFab) {
                messagesFab.addEventListener('click', function() {
                    window.location.href = 'friends.php';
                });
            }
            loadMessagesUnreadCount();
            loadNotificationsUnreadCount();
            loadNotificationsWidget();
            loadPersonalStats();
            
            document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
            
            const interactiveElements = document.querySelectorAll('button, a, input, label, select, .daidien, .daidien img, .dropdown-menu, .dropdown-menu a');
            interactiveElements.forEach(el => {
                el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                
                // Thêm event listeners để đảm bảo cursor không bị mất
                el.addEventListener('mouseenter', function() {
                    this.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                });
                el.addEventListener('mouseleave', function() {
                    this.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                });
            });
            
            // Fix hover avatar và dropdown menu triệt để
            const daidien = document.querySelector('.daidien');
            const avatarWrapper = document.querySelector('.daidien .avatar-wrapper');
            const daidienImg = document.querySelector('.daidien .avatar-wrapper img[alt="Ảnh đại diện"]');
            const dropdownMenu = document.querySelector('.dropdown-menu');
            const dropdownLinks = document.querySelectorAll('.dropdown-menu a');
            
            if (daidien) {
                daidien.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                daidien.style.pointerEvents = 'auto';
                
                // Đảm bảo dropdown hiển thị khi hover vào daidien hoặc dropdown
                let hoverTimeout;
                daidien.addEventListener('mouseenter', function() {
                    clearTimeout(hoverTimeout);
                    if (dropdownMenu) {
                        dropdownMenu.style.display = 'flex';
                        dropdownMenu.style.pointerEvents = 'auto';
                    }
                });
                
                daidien.addEventListener('mouseleave', function(e) {
                    // Chỉ đóng nếu không hover vào dropdown
                    if (dropdownMenu && !dropdownMenu.matches(':hover') && !e.relatedTarget?.closest('.dropdown-menu')) {
                        hoverTimeout = setTimeout(() => {
                            if (dropdownMenu && !dropdownMenu.matches(':hover')) {
                                dropdownMenu.style.display = 'none';
                            }
                        }, 100);
                    }
                });
            }
            
            if (avatarWrapper) {
                avatarWrapper.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                avatarWrapper.style.pointerEvents = 'auto';
                
                // Frame overlay không được block events
                const frameOverlay = avatarWrapper.querySelector('.avatar-frame-overlay');
                if (frameOverlay) {
                    frameOverlay.style.pointerEvents = 'none';
                    const frameImg = frameOverlay.querySelector('img');
                    if (frameImg) {
                        frameImg.style.pointerEvents = 'none';
                    }
                }
            }
            
            if (daidienImg) {
                daidienImg.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                daidienImg.style.pointerEvents = 'auto';
            }
            
            if (dropdownMenu) {
                dropdownMenu.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                dropdownMenu.style.pointerEvents = 'auto';
                
                // Giữ dropdown mở khi hover vào nó
                dropdownMenu.addEventListener('mouseenter', function() {
                    clearTimeout(hoverTimeout);
                    this.style.display = 'flex';
                    this.style.pointerEvents = 'auto';
                });
                
                dropdownMenu.addEventListener('mouseleave', function() {
                    hoverTimeout = setTimeout(() => {
                        this.style.display = 'none';
                    }, 150);
                });
            }
            
            dropdownLinks.forEach(link => {
                link.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                link.style.pointerEvents = 'auto';
                link.addEventListener('mouseenter', function() {
                    this.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                    clearTimeout(hoverTimeout);
                });
            });
        });
        
        // Live Clock
        function updateClock() {
            const now = new Date();
            const time = now.toLocaleTimeString('vi-VN');
            const date = now.toLocaleDateString('vi-VN', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            const timeEl = document.getElementById('liveTime');
            const dateEl = document.getElementById('liveDate');
            
            if (timeEl) timeEl.textContent = time;
            if (dateEl) dateEl.textContent = date;
        }
        setInterval(updateClock, 1000);
        updateClock();
        
        // Animated Counter for Statistics
        function animateCounter(element, target, duration = 2000) {
            const start = 0;
            const increment = target / (duration / 16);
            let current = start;
            
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    element.textContent = target;
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(current);
                }
            }, 16);
        }
        
        // Initialize animated counters
        document.addEventListener('DOMContentLoaded', function() {
            const statValues = document.querySelectorAll('.stat-value[data-target]');
            statValues.forEach(stat => {
                const target = parseInt(stat.getAttribute('data-target'));
                if (!isNaN(target)) {
                    animateCounter(stat, target);
                }
            });
            
            // Calculate user rank
            const userBalance = <?= $user['Money'] ?>;
            const userRankEl = document.getElementById('userRank');
            if (userRankEl) {
                let rank = 1;
                <?php foreach ($ranking as $r): ?>
                    if (<?= $r['Money'] ?> > userBalance) rank++;
                <?php endforeach; ?>
                userRankEl.textContent = rank;
                animateCounter(userRankEl, rank);
            }
        });
        
        // Random Tips
        const tips = [
            "Nhớ điểm danh mỗi ngày để nhận quà miễn phí!",
            "Sử dụng giftcode để nhận thêm tiền thưởng!",
            "Chơi có trách nhiệm, đừng quá đà nhé!",
            "Kiểm tra bảng xếp hạng để xem vị trí của bạn!",
            "Tham gia chat để giao lưu với mọi người!",
            "Cập nhật ảnh đại diện để cá nhân hóa hồ sơ!",
            "Đọc kỹ hướng dẫn trước khi chơi game!",
            "Quản lý số dư một cách thông minh!",
            "Thử nhiều game khác nhau để tìm game yêu thích!",
            "Chúc bạn may mắn và vui vẻ!"
        ];
        
        function showRandomTip() {
            const tipContent = document.getElementById('tipContent');
            if (tipContent) {
                const randomTip = tips[Math.floor(Math.random() * tips.length)];
                tipContent.style.opacity = '0';
                setTimeout(() => {
                    tipContent.textContent = randomTip;
                    tipContent.style.opacity = '1';
                }, 300);
            }
        }
        
        // Change tip every 10 seconds
        showRandomTip();
        setInterval(showRandomTip, 10000);
        
        // Confetti Animation
        function createConfetti() {
            const container = document.getElementById('confettiContainer');
            if (!container) return;
            
            const colors = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#f9ca24', '#f0932b', '#eb4d4b', '#6c5ce7', '#a29bfe'];
            const confettiCount = 100;
            
            for (let i = 0; i < confettiCount; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.animationDelay = Math.random() * 2 + 's';
                confetti.style.animationDuration = (Math.random() * 3 + 2) + 's';
                confetti.style.setProperty('--confetti-color', colors[Math.floor(Math.random() * colors.length)]);
                confetti.style.transform = `rotate(${Math.random() * 360}deg)`;
                container.appendChild(confetti);
                
                setTimeout(() => confetti.remove(), 5000);
            }
        }
        
        // Trigger confetti on successful giftcode
        <?php if (isset($giftMessage) && strpos($giftMessage, 'success') !== false): ?>
            setTimeout(createConfetti, 500);
        <?php endif; ?>
        
        // Enhanced Particle Effect on Game Link Hover
        document.querySelectorAll('.game-link').forEach(link => {
            let particleTimeout;
            
            link.addEventListener('mouseenter', function(e) {
                const rect = this.getBoundingClientRect();
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                const particleCount = 15;
                const colors = ['#ffffff', '#4ecdc4', '#45b7d1', '#f9ca24', '#ff6b6b'];
                
                // Clear any existing particles
                this.querySelectorAll('.particle').forEach(p => p.remove());
                
                for (let i = 0; i < particleCount; i++) {
                    const particle = document.createElement('div');
                    particle.className = 'particle';
                    
                    const angle = (Math.PI * 2 * i) / particleCount;
                    const distance = 60 + Math.random() * 40;
                    const size = 3 + Math.random() * 4;
                    const duration = 0.8 + Math.random() * 0.4;
                    const color = colors[Math.floor(Math.random() * colors.length)];
                    
                    particle.style.width = size + 'px';
                    particle.style.height = size + 'px';
                    particle.style.left = centerX + 'px';
                    particle.style.top = centerY + 'px';
                    particle.style.background = color;
                    particle.style.boxShadow = `0 0 ${size * 2}px ${color}`;
                    particle.style.setProperty('--tx', Math.cos(angle) * distance + 'px');
                    particle.style.setProperty('--ty', Math.sin(angle) * distance + 'px');
                    particle.style.animationDuration = duration + 's';
                    
                    this.appendChild(particle);
                    setTimeout(() => particle.remove(), duration * 1000);
                }
                
                // Add ripple effect
                const ripple = document.createElement('div');
                ripple.style.position = 'absolute';
                ripple.style.left = centerX + 'px';
                ripple.style.top = centerY + 'px';
                ripple.style.width = '0px';
                ripple.style.height = '0px';
                ripple.style.borderRadius = '50%';
                ripple.style.border = '2px solid rgba(255, 255, 255, 0.6)';
                ripple.style.transform = 'translate(-50%, -50%)';
                ripple.style.animation = 'rippleEffect 0.8s ease-out';
                ripple.style.pointerEvents = 'none';
                this.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 800);
            });
            
            link.addEventListener('mouseleave', function() {
                this.querySelectorAll('.particle').forEach(p => p.remove());
            });
        });
        
        // Add ripple animation keyframes
        if (!document.getElementById('gameLinkAnimations')) {
            const style = document.createElement('style');
            style.id = 'gameLinkAnimations';
            style.textContent = `
                @keyframes rippleEffect {
                    0% {
                        width: 0px;
                        height: 0px;
                        opacity: 1;
                    }
                    100% {
                        width: 200px;
                        height: 200px;
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }
        
        // Animated Balance Update
        function updateBalanceDisplay(newBalance) {
            const balanceValue = document.querySelector('.balance-value');
            const balanceDisplay = document.getElementById('balanceDisplay');
            
            if (balanceValue && balanceDisplay) {
                const oldBalance = parseInt(balanceValue.getAttribute('data-balance') || balanceValue.textContent.replace(/\./g, ''));
                const targetBalance = parseInt(newBalance);
                
                balanceDisplay.classList.add('balance-update');
                
                const duration = 1000;
                const steps = 60;
                const increment = (targetBalance - oldBalance) / steps;
                let current = oldBalance;
                let step = 0;
                
                const timer = setInterval(() => {
                    step++;
                    current += increment;
                    if (step >= steps) {
                        balanceValue.textContent = number_format(targetBalance, 0, ',', '.');
                        balanceValue.setAttribute('data-balance', targetBalance);
                        balanceDisplay.classList.remove('balance-update');
                        clearInterval(timer);
                    } else {
                        balanceValue.textContent = number_format(Math.floor(current), 0, ',', '.');
                    }
                }, duration / steps);
            }
        }
        
        // Number format helper
        function number_format(number, decimals, dec_point, thousands_sep) {
            number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
            const n = !isFinite(+number) ? 0 : +number;
            const prec = !isFinite(+decimals) ? 0 : Math.abs(decimals);
            const sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep;
            const dec = (typeof dec_point === 'undefined') ? '.' : dec_point;
            let s = '';
            
            const toFixedFix = function (n, prec) {
                const k = Math.pow(10, prec);
                return '' + Math.round(n * k) / k;
            };
            
            s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
            if (s[0].length > 3) {
                s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
            }
            if ((s[1] || '').length < prec) {
                s[1] = s[1] || '';
                s[1] += new Array(prec - s[1].length + 1).join('0');
            }
            return s.join(dec);
        }
        
        // Smooth Scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
        
        // Show FAB on scroll
        let lastScrollTop = 0;
        const fab = document.querySelector('.fab');
        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            if (fab) {
                if (scrollTop > 300) {
                    fab.style.display = 'flex';
                } else {
                    fab.style.display = 'none';
                }
            }
            lastScrollTop = scrollTop;
        });
        
        // Initialize FAB visibility
        if (fab) {
            fab.style.display = 'none';
        }
        
        // Server Notifications
        let notificationCheckInterval;
        let currentNotificationId = null;
        
        function checkServerNotifications() {
            fetch('api_get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success' && data.notifications.length > 0) {
                        const notification = data.notifications[0];
                        
                        // Chỉ hiển thị nếu là thông báo mới
                        if (notification.id !== currentNotificationId) {
                            showServerNotification(notification.message);
                            currentNotificationId = notification.id;
                            
                            // Tự động ẩn sau 30 giây
                            setTimeout(() => {
                                closeNotification();
                                currentNotificationId = null;
                            }, 30000);
                        }
                    }
                })
                .catch(error => console.error('Error fetching notifications:', error));
        }
        
        function showServerNotification(message) {
            const notificationEl = document.getElementById('serverNotification');
            const messageEl = document.getElementById('notificationMessage');
            
            if (notificationEl && messageEl) {
                messageEl.innerHTML = message;
                notificationEl.classList.add('show');
            }
        }
        
        function closeNotification() {
            const notificationEl = document.getElementById('serverNotification');
            if (notificationEl) {
                notificationEl.classList.remove('show');
                currentNotificationId = null;
            }
        }
        
        // Kiểm tra thông báo mỗi 2 giây
        notificationCheckInterval = setInterval(checkServerNotifications, 2000);
        checkServerNotifications(); // Kiểm tra ngay lập tức
        
        // Three.js 3D Background với Theme Config
        let scene, particlesMaterial, shapes, themeConfig;
        
        (function() {
            const canvas = document.getElementById('threejs-background');
            if (!canvas) return;
            
            // Lấy config từ PHP
            themeConfig = {
                particleCount: <?= $particleCount ?>,
                particleSize: <?= $particleSize ?>,
                particleColor: '<?= $particleColor ?>',
                particleOpacity: <?= $particleOpacity ?>,
                shapeCount: <?= $shapeCount ?>,
                shapeColors: <?= json_encode($shapeColors) ?>,
                shapeOpacity: <?= $shapeOpacity ?>,
                bgGradient: <?= json_encode($bgGradient) ?>
            };
            
            // Background gradient đã được set trong CSS qua $bgGradientCSS
            // JavaScript chỉ cần update khi dark mode
            
            scene = new THREE.Scene();
            const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
            const renderer = new THREE.WebGLRenderer({ canvas: canvas, alpha: true, antialias: true });
            renderer.setSize(window.innerWidth, window.innerHeight);
            renderer.setPixelRatio(window.devicePixelRatio);
            
            // Tạo các particles với config
            const particlesGeometry = new THREE.BufferGeometry();
            const particlesCount = themeConfig.particleCount;
            const posArray = new Float32Array(particlesCount * 3);
            
            for (let i = 0; i < particlesCount * 3; i++) {
                posArray[i] = (Math.random() - 0.5) * 20;
            }
            
            particlesGeometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3));
            
            // Convert hex color to number
            const particleColorNum = parseInt(themeConfig.particleColor.replace('#', ''), 16);
            
            particlesMaterial = new THREE.PointsMaterial({
                size: themeConfig.particleSize,
                color: particleColorNum,
                transparent: true,
                opacity: themeConfig.particleOpacity
            });
            
            const particlesMesh = new THREE.Points(particlesGeometry, particlesMaterial);
            scene.add(particlesMesh);
            
            // Tạo các hình dạng 3D với config
            shapes = [];
            const colors = themeConfig.shapeColors.map(c => parseInt(c.replace('#', ''), 16));
            
            for (let i = 0; i < themeConfig.shapeCount; i++) {
                const geometry = new THREE.IcosahedronGeometry(Math.random() * 0.5 + 0.3, 0);
                const material = new THREE.MeshStandardMaterial({
                    color: colors[Math.floor(Math.random() * colors.length)],
                    transparent: true,
                    opacity: themeConfig.shapeOpacity,
                    wireframe: Math.random() > 0.5
                });
                const mesh = new THREE.Mesh(geometry, material);
                mesh.position.set(
                    (Math.random() - 0.5) * 15,
                    (Math.random() - 0.5) * 15,
                    (Math.random() - 0.5) * 15
                );
                mesh.rotation.set(
                    Math.random() * Math.PI,
                    Math.random() * Math.PI,
                    Math.random() * Math.PI
                );
                shapes.push(mesh);
                scene.add(mesh);
            }
            
            // Ánh sáng
            const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
            scene.add(ambientLight);
            
            const pointLight = new THREE.PointLight(0xffffff, 1);
            pointLight.position.set(5, 5, 5);
            scene.add(pointLight);
            
            camera.position.z = 5;
            
            // Animation
            function animate() {
                requestAnimationFrame(animate);
                
                particlesMesh.rotation.y += 0.001;
                particlesMesh.rotation.x += 0.0005;
                
                shapes.forEach((shape, index) => {
                    shape.rotation.x += 0.01 * (index % 3 + 1);
                    shape.rotation.y += 0.01 * (index % 2 + 1);
                    shape.position.y += Math.sin(Date.now() * 0.001 + index) * 0.001;
                });
                
                renderer.render(scene, camera);
            }
            
            // Resize handler
            window.addEventListener('resize', () => {
                camera.aspect = window.innerWidth / window.innerHeight;
                camera.updateProjectionMatrix();
                renderer.setSize(window.innerWidth, window.innerHeight);
            });
            
            animate();
        })();
        
        // Dark mode toggle với Three.js background
        let isDarkMode = false;
        const darkModeConfig = {
            particleColor: '#ff6b6b',
            particleOpacity: 0.8,
            particleSize: 0.03,
            shapeColors: ['#1a1a2e', '#16213e', '#0f3460', '#e94560'],
            shapeOpacity: 0.4,
            bgGradient: ['#0f0c29', '#302b63', '#24243e']
        };
        
        function applyDarkModeTheme() {
            if (!scene || !particlesMaterial || !shapes) return;
            
            // Cập nhật background gradient cho dark mode
            if (darkModeConfig.bgGradient && darkModeConfig.bgGradient.length >= 2) {
                const gradient = `linear-gradient(135deg, ${darkModeConfig.bgGradient[0]} 0%, ${darkModeConfig.bgGradient[1]} 50%, ${darkModeConfig.bgGradient[2] || darkModeConfig.bgGradient[1]} 100%)`;
                document.body.style.background = gradient;
                document.body.style.backgroundAttachment = 'fixed';
            }
            
            // Cập nhật particles
            const particleColorNum = parseInt(darkModeConfig.particleColor.replace('#', ''), 16);
            particlesMaterial.color.setHex(particleColorNum);
            particlesMaterial.opacity = darkModeConfig.particleOpacity;
            particlesMaterial.size = darkModeConfig.particleSize;
            
            // Cập nhật shapes
            const colors = darkModeConfig.shapeColors.map(c => parseInt(c.replace('#', ''), 16));
            shapes.forEach((shape, index) => {
                if (shape.material) {
                    shape.material.color.setHex(colors[index % colors.length]);
                    shape.material.opacity = darkModeConfig.shapeOpacity;
                }
            });
        }
        
        function applyOriginalTheme() {
            if (!scene || !particlesMaterial || !shapes || !themeConfig) return;
            
            // Khôi phục background gradient gốc từ CSS ($bgGradientCSS)
            // CSS đã set background, không cần set lại qua JavaScript
            // Chỉ cần khôi phục particles và shapes
            
            // Khôi phục particles
            const particleColorNum = parseInt(themeConfig.particleColor.replace('#', ''), 16);
            particlesMaterial.color.setHex(particleColorNum);
            particlesMaterial.opacity = themeConfig.particleOpacity;
            particlesMaterial.size = themeConfig.particleSize;
            
            // Khôi phục shapes
            const colors = themeConfig.shapeColors.map(c => parseInt(c.replace('#', ''), 16));
            shapes.forEach((shape, index) => {
                if (shape.material) {
                    shape.material.color.setHex(colors[Math.floor(Math.random() * colors.length)]);
                    shape.material.opacity = themeConfig.shapeOpacity;
                }
            });
        }
        
        const darkModeToggle = document.getElementById('darkModeToggle');
        if (darkModeToggle) {
            darkModeToggle.addEventListener('click', function(e) {
                e.preventDefault();
                isDarkMode = !isDarkMode;
                document.body.classList.toggle('dark-mode');
                
                if (isDarkMode) {
                    applyDarkModeTheme();
                    this.innerHTML = '<i class="fa-solid fa-sun icon"></i> Tắt darkmode';
                } else {
                    applyOriginalTheme();
                    this.innerHTML = '<i class="fa-solid fa-moon icon"></i> Bật darkmode';
                }
            });
        }
        
        // Xử lý giftcode form
        const giftForm = document.querySelector('.gift form');
        if (giftForm) {
            giftForm.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Đang xử lý...';
                }
            });
        }

        // Xử lý nút themes - lưu vào sessionStorage để shop.php biết cần mở tab themes
        const themeButton = document.getElementById('themeButton');
        if (themeButton) {
            themeButton.addEventListener('click', function(e) {
                sessionStorage.setItem('openTab', 'themes');
            });
        }
        
        // Tự động check daily login khi vào trang chủ
        (function() {
            // Kiểm tra bảng tồn tại bằng cách gọi API
            fetch('api_daily_login.php?action=get_status')
                .then(response => response.json())
                .then(data => {
                    if (data.success !== undefined) {
                        // Bảng tồn tại, check login
                        fetch('api_daily_login.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=check_login'
                        })
                        .then(response => response.json())
                        .then(data => {
                            // Login checked, có thể hiển thị badge nếu cần
                        })
                        .catch(err => console.log('Daily login check error:', err));
                    }
                })
                .catch(err => {
                    // Bảng chưa tồn tại hoặc lỗi, không làm gì
                });
        })();
    </script>
    </html>