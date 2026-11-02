<?php
/**
 * ♟️ Đấu Trường Cờ Ca-rô PvP Độc Lập
 * - Chế độ 1: Đánh Ngay Với AI (Standalone, không tạo thách đấu bot, không popup thông báo)
 * - Chế độ 2: Ghép Đấu 1vs1 Kỳ Thủ Trực Tuyến (Matchmaking radar tìm trận tức thì)
 */
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

$userId = (int)$_SESSION['Iduser'];
$challengeId = (int)($_GET['id'] ?? 0);

// Xử lý AJAX Actions cho Chế độ Đánh với AI & Tìm trận 1vs1
if (isset($_REQUEST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $act = $_REQUEST['action'];

    // 1. Khởi tạo ván đấu AI (Trừ cược trực tiếp, không tạo challenge bot)
    if ($act === 'start_ai_game') {
        $bet = (float)($_POST['bet_amount'] ?? 10000);
        if ($bet < 1000) $bet = 1000;

        $chk = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
        $chk->bind_param("i", $userId);
        $chk->execute();
        $currMoney = (float)($chk->get_result()->fetch_assoc()['Money'] ?? 0);
        $chk->close();

        if ($currMoney < $bet) {
            echo json_encode(['success' => false, 'message' => 'Số dư GTLM không đủ để đặt cược!']);
            exit;
        }

        // Trừ tiền cược
        $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");
        $newMoney = $currMoney - $bet;
        $_SESSION['caro_standalone_bet'] = $bet;

        echo json_encode([
            'success' => true,
            'bet' => $bet,
            'new_balance' => number_format($newMoney, 0, ',', '.')
        ]);
        exit;
    }

    // 2. Kết thúc ván đấu AI (Cộng thưởng nếu thắng, ghi game_history)
    if ($act === 'finish_ai_game') {
        $win = (int)($_POST['win'] ?? 0);
        $bet = (float)($_SESSION['caro_standalone_bet'] ?? ($_POST['bet_amount'] ?? 10000));
        unset($_SESSION['caro_standalone_bet']);

        $reward = 0;
        if ($win === 1) {
            $reward = $bet * 1.95;
            $conn->query("UPDATE users SET Money = Money + $reward WHERE Iduser = $userId");
            $conn->query("INSERT INTO game_history (user_id, game_name, bet_amount, win_amount, is_win, created_at) 
                          VALUES ($userId, 'Cờ Ca-rô AI', $bet, $reward, 1, NOW())");
        } else {
            $conn->query("INSERT INTO game_history (user_id, game_name, bet_amount, win_amount, is_win, created_at) 
                          VALUES ($userId, 'Cờ Ca-rô AI', $bet, 0, 0, NOW())");
        }

        $chk = $conn->query("SELECT Money FROM users WHERE Iduser = $userId");
        $finalMoney = (float)($chk->fetch_assoc()['Money'] ?? 0);

        echo json_encode([
            'success' => true,
            'win' => ($win === 1),
            'reward' => $reward,
            'new_balance' => number_format($finalMoney, 0, ',', '.')
        ]);
        exit;
    }

    // 3. Cơ chế Tìm Trận (Matchmaking 1vs1)
    if ($act === 'matchmake_search') {
        $bet = (float)($_POST['bet_amount'] ?? 10000);
        if ($bet < 1000) $bet = 1000;

        $chk = $conn->query("SELECT Money FROM users WHERE Iduser = $userId");
        $currMoney = (float)($chk->fetch_assoc()['Money'] ?? 0);
        if ($currMoney < $bet) {
            echo json_encode(['success' => false, 'message' => 'Số dư GTLM không đủ!']);
            exit;
        }

        // Bước 1: Tìm phòng cờ Caro pending của người chơi khác
        $stmtOpen = $conn->prepare("SELECT id, challenger_id, bet_amount FROM pvp_challenges 
                                    WHERE game_type = 'caro' AND status = 'pending' AND challenger_id != ? AND bet_amount <= ? 
                                    ORDER BY id DESC LIMIT 1");
        $stmtOpen->bind_param("id", $userId, $bet);
        $stmtOpen->execute();
        $openMatch = $stmtOpen->get_result()->fetch_assoc();
        $stmtOpen->close();

        if ($openMatch) {
            $cId = (int)$openMatch['id'];
            $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");
            $conn->query("UPDATE pvp_challenges SET opponent_id = $userId, status = 'accepted', accepted_at = NOW() WHERE id = $cId");

            echo json_encode([
                'success' => true,
                'matched' => true,
                'challenge_id' => $cId
            ]);
            exit;
        }

        // Bước 2: Ghép với một kỳ thủ online trong hệ thống
        $rivalStmt = $conn->query("SELECT Iduser, Name, ImageURL FROM users WHERE Iduser != $userId AND Name NOT LIKE 'bot_%' ORDER BY RAND() LIMIT 1");
        $rival = $rivalStmt ? $rivalStmt->fetch_assoc() : null;
        if (!$rival) {
            $rivalStmt = $conn->query("SELECT Iduser, Name, ImageURL FROM users WHERE Iduser != $userId ORDER BY RAND() LIMIT 1");
            $rival = $rivalStmt->fetch_assoc();
        }
        $rivalId = (int)$rival['Iduser'];
        $rivalName = $rival['Name'];

        $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");
        $boardJson = json_encode(array_fill(0, 15, array_fill(0, 15, 0)));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        $stmtIns = $conn->prepare("INSERT INTO pvp_challenges 
            (challenger_id, opponent_id, game_type, bet_amount, status, board_state, current_turn_id, accepted_at, expires_at)
            VALUES (?, ?, 'caro', ?, 'accepted', ?, ?, NOW(), ?)");
        $stmtIns->bind_param("iidsis", $userId, $rivalId, $bet, $boardJson, $userId, $expiresAt);
        $stmtIns->execute();
        $newId = $conn->insert_id;
        $stmtIns->close();

        echo json_encode([
            'success' => true,
            'matched' => true,
            'challenge_id' => $newId,
            'opponent_name' => $rivalName
        ]);
        exit;
    }

    exit;
}

// Lấy thông tin người dùng hiện tại
$stmtUser = $conn->prepare("SELECT Name, Money, ImageURL FROM users WHERE Iduser = ?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$currUser = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

$userName = $currUser['Name'] ?? 'Kỳ Thủ';
$userMoney = (float)($currUser['Money'] ?? 0);
$userAvatar = !empty($currUser['ImageURL']) ? $currUser['ImageURL'] : 'img/avatar_default.png';

require_once 'load_theme.php';

// Kiểm tra nếu đang vào 1 phòng thách đấu cụ thể
$challenge = null;
if ($challengeId > 0) {
    $sql = "SELECT c.*, u1.Name as challenger_name, u1.ImageURL as challenger_avatar,
                   u2.Name as opponent_name, u2.ImageURL as opponent_avatar
            FROM pvp_challenges c
            JOIN users u1 ON c.challenger_id = u1.Iduser
            JOIN users u2 ON c.opponent_id = u2.Iduser
            WHERE c.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $challengeId);
    $stmt->execute();
    $challenge = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$challenge || $challenge['game_type'] != 'caro' || 
        ($challenge['challenger_id'] != $userId && $challenge['opponent_id'] != $userId)) {
        header("Location: pvp_caro.php");
        exit();
    }
} else {
    // Lấy các trận đấu Cờ Ca-rô đang diễn ra hoặc đang chờ
    $stmtMy = $conn->prepare("
        SELECT c.*, 
               u1.Name as challenger_name, 
               u2.Name as opponent_name 
        FROM pvp_challenges c
        JOIN users u1 ON c.challenger_id = u1.Iduser
        JOIN users u2 ON c.opponent_id = u2.Iduser
        WHERE (c.challenger_id = ? OR c.opponent_id = ?) 
          AND c.game_type = 'caro' 
          AND c.status IN ('pending', 'accepted')
        ORDER BY c.id DESC LIMIT 6
    ");
    $stmtMy->bind_param("ii", $userId, $userId);
    $stmtMy->execute();
    $myMatches = $stmtMy->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtMy->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $challenge ? "Bàn Cờ Ca-rô PvP #$challengeId" : "♟️ Đấu Trường Cờ Ca-rô PvP Độc Lập" ?> | GTLM Gaming</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&family=Orbitron:wght@700;900&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/gtlm-realtime.js"></script>
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #1d4ed8;
            --gold: #fbbf24;
            --emerald: #10b981;
            --rose: #ef4444;
            --bg-dark: #070b14;
            --panel: rgba(15, 23, 42, 0.85);
            --border: rgba(255, 255, 255, 0.12);
        }
        * { box-sizing: border-box; user-select: none; }
        body {
            margin: 0;
            background: radial-gradient(circle at 50% 20%, #0f1f38 0%, var(--bg-dark) 100%);
            color: #f8fafc;
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            overflow-x: hidden;
            <?= isset($bgGradientCSS) ? "background-image: $bgGradientCSS; background-attachment: fixed;" : "" ?>
        }

        .caro-topbar {
            width: 100%;
            height: 65px;
            background: rgba(11, 15, 25, 0.95);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 25px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .topbar-btn {
            color: #94a3b8;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: 0.2s;
            border: none;
            background: none;
        }
        .topbar-btn:hover { color: #fff; }
        .user-balance-badge {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.4);
            padding: 6px 14px;
            border-radius: 12px;
            color: var(--emerald);
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        /* --- SẢNH LOBBY CỜ CA-RÔ RIÊNG BIỆT --- */
        .caro-lobby-container {
            width: 100%;
            max-width: 1000px;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        .hero-banner {
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.6) 0%, rgba(15, 23, 42, 0.9) 100%);
            border: 1px solid rgba(59, 130, 246, 0.4);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.5);
            position: relative;
            overflow: hidden;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid var(--primary);
            color: #93c5fd;
            font-size: 0.8rem;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 12px;
        }
        .hero-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.2rem;
            font-weight: 900;
            margin: 0 0 10px 0;
            background: linear-gradient(135deg, #fff 30%, #60a5fa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-desc {
            font-size: 1rem;
            color: #94a3b8;
            max-width: 650px;
            line-height: 1.5;
            margin: 0;
        }

        .caro-modes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
        }
        .mode-card {
            background: var(--panel);
            backdrop-filter: blur(14px);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            transition: 0.3s;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .mode-card:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
        }
        .mode-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        .mode-icon {
            width: 55px;
            height: 55px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }
        .ai-icon { background: rgba(59, 130, 246, 0.2); border: 1px solid var(--primary); }
        .pvp-icon { background: rgba(239, 68, 68, 0.2); border: 1px solid var(--rose); }

        .bet-chips-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            margin: 18px 0;
        }
        .bet-chip-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #fff;
            padding: 8px 4px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 0.85rem;
            cursor: pointer;
            transition: 0.2s;
            text-align: center;
        }
        .bet-chip-btn.active, .bet-chip-btn:hover {
            background: var(--primary);
            border-color: #60a5fa;
            color: #fff;
            transform: scale(1.05);
        }
        .btn-launch-mode {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            border: none;
            padding: 14px 20px;
            border-radius: 12px;
            font-weight: 900;
            font-size: 1rem;
            cursor: pointer;
            box-shadow: 0 0 20px rgba(59,130,246,0.4);
            transition: 0.2s;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-launch-mode:hover { transform: scale(1.03); }

        .my-matches-section {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 25px;
        }
        .match-row {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        /* --- BÀN CỜ ĐANG ĐẤU (MATCH PLAYING CHUNG) --- */
        .caro-match-layout {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            width: 100%;
        }
        .game-header {
            margin: 10px 0 20px 0;
            text-align: center;
            background: rgba(15, 23, 42, 0.85);
            padding: 12px 35px;
            border-radius: 50px;
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .player-info { display: flex; flex-direction: column; align-items: center; }
        .player-name { font-weight: 800; font-size: 1rem; color: #fff; }
        .player-symbol { font-size: 1.8rem; font-weight: 900; }
        .symbol-x { color: var(--rose); text-shadow: 0 0 12px rgba(239, 68, 68, 0.6); }
        .symbol-o { color: #38bdf8; text-shadow: 0 0 12px rgba(56, 189, 248, 0.6); }
        .vs-badge { background: var(--gold); color: #000; padding: 4px 12px; border-radius: 20px; font-weight: 900; font-size: 0.8rem; }

        .board-container {
            position: relative;
            background: rgba(15, 23, 42, 0.9);
            padding: 12px;
            border-radius: 16px;
            border: 2px solid rgba(59, 130, 246, 0.3);
            box-shadow: 0 20px 60px rgba(0,0,0,0.7);
        }
        .caro-board {
            display: grid;
            grid-template-columns: repeat(15, 34px);
            grid-template-rows: repeat(15, 34px);
            gap: 1px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            overflow: hidden;
        }
        .cell {
            background: #0b1120;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 900;
            cursor: pointer;
            transition: all 0.15s;
            user-select: none;
        }
        .cell:hover:not(.filled) {
            background: #1e293b;
        }
        .cell.filled { cursor: default; }
        .cell.last-move {
            box-shadow: inset 0 0 10px #fbbf24;
            background: rgba(251, 191, 36, 0.12);
        }
        .cell.win-cell {
            background: rgba(251, 191, 36, 0.35) !important;
            box-shadow: 0 0 20px #fbbf24, inset 0 0 10px #fbbf24 !important;
            animation: winPulse 0.8s infinite alternate !important;
        }
        @keyframes winPulse {
            0% { transform: scale(1); filter: brightness(1); }
            100% { transform: scale(1.1); filter: brightness(1.5); }
        }

        .status-bar {
            margin-top: 18px;
            padding: 12px 28px;
            border-radius: 30px;
            font-weight: 800;
            font-size: 1rem;
            text-align: center;
            min-width: 280px;
            transition: all 0.3s;
        }
        .status-my-turn {
            background: rgba(16, 185, 129, 0.15);
            color: var(--emerald);
            border: 1px solid var(--emerald);
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.3);
        }
        .status-opponent-turn {
            background: rgba(251, 191, 36, 0.15);
            color: var(--gold);
            border: 1px solid var(--gold);
        }

        .winner-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(7, 11, 20, 0.92);
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 20;
            border-radius: 14px;
            backdrop-filter: blur(8px);
        }
        .winner-msg {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.8rem;
            color: var(--gold);
            margin-bottom: 10px;
            text-shadow: 0 0 25px rgba(251, 191, 36, 0.6);
            text-align: center;
        }

        /* 📡 MODAL TÌM TRẬN 1VS1 (RADAR SCANNER MATCHMAKING) */
        .matchmaking-modal-bg {
            position: fixed;
            inset: 0;
            background: rgba(3, 7, 18, 0.85);
            backdrop-filter: blur(10px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .matchmaking-modal-bg.active { display: flex; }
        .matchmaking-box {
            background: rgba(15, 23, 42, 0.95);
            border: 2px solid rgba(59, 130, 246, 0.5);
            box-shadow: 0 25px 60px rgba(0,0,0,0.8), 0 0 40px rgba(59, 130, 246, 0.3);
            border-radius: 24px;
            padding: 35px 30px;
            width: 90%;
            max-width: 480px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .radar-circle {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 2px solid rgba(59, 130, 246, 0.5);
            position: relative;
            margin: 20px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.1) 0%, transparent 70%);
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.2);
        }
        .radar-sweep {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            border-radius: 50%;
            background: conic-gradient(from 0deg, rgba(59, 130, 246, 0.5) 0deg, transparent 60deg, transparent 360deg);
            animation: radarRotate 2s linear infinite;
        }
        @keyframes radarRotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .radar-center-icon {
            font-size: 2.2rem;
            z-index: 2;
            animation: pulseIcon 1.5s ease-in-out infinite alternate;
        }
        @keyframes pulseIcon {
            from { transform: scale(0.9); }
            to { transform: scale(1.1); }
        }

        @media (max-width: 600px) {
            .caro-board {
                grid-template-columns: repeat(15, 23px);
                grid-template-rows: repeat(15, 23px);
            }
            .cell { font-size: 13px; }
            .game-header { padding: 8px 20px; gap: 15px; }
            .hero-title { font-size: 1.6rem; }
        }
    </style>
</head>
<body>

    <!-- TOPBAR -->
    <header class="caro-topbar">
        <button onclick="handleBackAction()" class="topbar-btn">
            <i class="fa fa-arrow-left"></i> <span id="topbarBackText"><?= $challenge ? 'Rời Bàn Cờ' : 'Về Trang Chủ' ?></span>
        </button>
        <div style="font-weight: 900; font-size: 1.1rem; color: #60a5fa; display: flex; align-items: center; gap: 8px;">
            <span>♟️</span> ĐẤU TRƯỜNG CỜ CA-RÔ
        </div>
        <div class="user-balance-badge">
            <i class="fa fa-coins"></i>
            <span id="userMoneyTop"><?= number_format($userMoney, 0, ',', '.') ?></span> GTLM
        </div>
    </header>

    <?php if (!$challenge): ?>
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- 1. SẢNH LOBBY CỜ CA-RÔ ĐỘC LẬP -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <main class="caro-lobby-container" id="caroLobbyView">
        <div class="hero-banner">
            <div class="hero-badge"><i class="fa fa-bolt"></i> CHẾ ĐỘ ĐÁNH LIÊN TỤC 15x15</div>
            <h1 class="hero-title">ĐẤU TRƯỜNG CỜ CA-RÔ</h1>
            <p class="hero-desc">
                Cờ Ca-rô đã được tách riêng thành Sảnh Độc Lập! Chiến đấu liên tục từng nước cờ không cần chờ đợi với AI Đại Sư Ca-rô, hoặc tìm trận ghép đấu tay đôi giữa 2 kỳ thủ.
            </p>
        </div>

        <div class="caro-modes-grid">
            <!-- CHẾ ĐỘ 1: ĐẤU VỚI BOT ĐẠI SƯ CA-RÔ (ĐÁNH LIÊN TỤC KHÔNG CHỜ ĐỢI) -->
            <div class="mode-card" style="border: 2px solid rgba(59, 130, 246, 0.5);">
                <div>
                    <div class="mode-header">
                        <div class="mode-icon ai-icon">🤖</div>
                        <div>
                            <h2 style="margin: 0; font-size: 1.3rem; color: #93c5fd;">Đại Sư Ca-rô (AI)</h2>
                            <p style="margin: 4px 0 0 0; color: #94a3b8; font-size: 0.85rem;">Đánh riêng lập tức • Phản xạ 0.2s</p>
                        </div>
                    </div>
                    <p style="color: #cbd5e1; font-size: 0.9rem; line-height: 1.4;">
                        Chiến đấu tức thì không cần đợi người khác! Đánh trực tiếp trên bàn cờ, AI tự động phản đòn ngay lập tức.
                    </p>

                    <label style="font-weight: 800; font-size: 0.85rem; color: #94a3b8; display: block; margin-top: 15px;">CHỌN MỨC CƯỢC GTLM:</label>
                    <div class="bet-chips-row">
                        <div class="bet-chip-btn active" onclick="selectAiBet(10000)">10K</div>
                        <div class="bet-chip-btn" onclick="selectAiBet(50000)">50K</div>
                        <div class="bet-chip-btn" onclick="selectAiBet(100000)">100K</div>
                        <div class="bet-chip-btn" onclick="selectAiBet(500000)">500K</div>
                        <div class="bet-chip-btn" onclick="selectAiBet(1000000)">1M</div>
                    </div>
                </div>

                <button class="btn-launch-mode" onclick="launchAiGameDirect()">
                    <i class="fa fa-play"></i> VÀO ĐÁNH NGAY VỚI AI
                </button>
            </div>

            <!-- CHẾ ĐỘ 2: THÁCH ĐẤU PvP NGƯỜI THẬT (CƠ CHẾ TÌM TRẬN 1VS1) -->
            <div class="mode-card" style="border: 2px solid rgba(239, 68, 68, 0.4);">
                <div>
                    <div class="mode-header">
                        <div class="mode-icon pvp-icon">⚔️</div>
                        <div>
                            <h2 style="margin: 0; font-size: 1.3rem; color: #fca5a5;">Thách Đấu Kỳ Thủ (1vs1)</h2>
                            <p style="margin: 4px 0 0 0; color: #94a3b8; font-size: 0.85rem;">Người vs Người • Tranh hùng xưng bá</p>
                        </div>
                    </div>
                    <p style="color: #cbd5e1; font-size: 0.9rem; line-height: 1.4;">
                        Ghép trận nhanh với các kỳ thủ trực tuyến trong sảnh hoặc tạo bàn cờ chờ đối thủ vào tiếp chiêu.
                    </p>

                    <div style="margin: 18px 0; background: rgba(0,0,0,0.25); padding: 12px; border-radius: 12px;">
                        <div style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 5px;">Kỳ thủ đang trực tuyến:</div>
                        <div style="font-size: 1.1rem; font-weight: 800; color: #38bdf8;">🔥 Hơn 40 đối thủ sẵn sàng</div>
                    </div>
                </div>

                <button class="btn-launch-mode" style="background: linear-gradient(135deg, #ef4444, #b91c1c);" onclick="openMatchmakingModal()">
                    <i class="fa fa-bolt"></i> 🎯 TÌM TRẬN NGAY (1VS1)
                </button>
            </div>
        </div>

        <!-- DANH SÁCH BÀN CỜ ĐANG CÓ -->
        <?php if (!empty($myMatches)): ?>
        <div class="my-matches-section">
            <h3 style="margin: 0 0 15px 0; font-size: 1.1rem; color: var(--gold); display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-chess-board"></i> BÀN CỜ CỦA BẠN ĐANG HOẠT ĐỘNG
            </h3>
            <?php foreach ($myMatches as $m): ?>
            <div class="match-row">
                <div>
                    <span style="font-weight: 800; color: #fff;">Bàn #<?= $m['id'] ?></span>: 
                    <span style="color: #60a5fa; font-weight: 700;"><?= htmlspecialchars($m['challenger_name']) ?></span> vs 
                    <span style="color: #f87171; font-weight: 700;"><?= htmlspecialchars($m['opponent_name']) ?></span>
                    <span style="margin-left: 10px; color: var(--gold); font-size: 0.85rem;">(<?= number_format($m['bet_amount']) ?> GTLM)</span>
                </div>
                <div>
                    <a href="pvp_caro.php?id=<?= $m['id'] ?>" class="bet-chip-btn active" style="text-decoration: none; padding: 6px 16px; display: inline-block;">
                        VÀO BÀN CỜ 👉
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- CHẾ ĐỘ ĐẤU VỚI AI RIÊNG BIỆT (KHÔNG TẠO CHALLENGE BOT_66) -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <main class="caro-match-layout" id="caroAiMatchView" style="display: none;">
        <div class="game-header">
            <div class="player-info">
                <span class="player-name"><?= htmlspecialchars($userName) ?></span>
                <span class="player-symbol symbol-x">X</span>
            </div>
            <div class="vs-badge">VS AI</div>
            <div class="player-info">
                <span class="player-name">🤖 Đại Sư Ca-rô AI</span>
                <span class="player-symbol symbol-o">O</span>
            </div>
        </div>

        <div class="board-container">
            <div class="winner-overlay" id="aiWinnerOverlay">
                <div class="winner-msg" id="aiWinnerMsg">BẠN THẮNG!</div>
                <p id="aiRewardMsg" style="font-size: 1.2rem; font-weight: 700; color: #4ade80; text-align: center; margin: 0 0 20px 0;"></p>
                <div style="display: flex; gap: 12px; flex-wrap: wrap; justify-content: center;">
                    <button onclick="launchAiGameDirect()" class="btn-launch-mode" style="width: auto; padding: 12px 25px; background: linear-gradient(135deg, #10b981, #059669);">
                        <i class="fa fa-rotate"></i> ĐÁNH TIẾP VÁN MỚI
                    </button>
                    <button onclick="backToLobby()" class="btn-launch-mode" style="width: auto; padding: 12px 25px; background: rgba(255,255,255,0.1); border: 1px solid var(--border);">
                        VỀ SẢNH CỜ CA-RÔ
                    </button>
                </div>
            </div>
            <div class="caro-board" id="aiBoard">
                <!-- 15x15 Cells -->
            </div>
        </div>

        <div class="status-bar status-my-turn" id="aiStatusBar">
            👉 Đến lượt bạn hạ quân cờ X!
        </div>
    </main>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- 📡 MODAL TÌM TRẬN 1VS1 (RADAR MATCHMAKING SCANNER) -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div class="matchmaking-modal-bg" id="matchmakingModal">
        <div class="matchmaking-box">
            <h2 style="margin: 0; font-family: 'Orbitron'; font-size: 1.4rem; color: #60a5fa;">
                📡 TÌM TRẬN KỲ THỦ 1VS1
            </h2>
            <p style="color: #94a3b8; font-size: 0.85rem; margin: 6px 0 15px 0;">
                Hệ thống đang quét sảnh chờ tìm kiếm đối thủ xứng tầm
            </p>

            <div class="radar-circle">
                <div class="radar-sweep"></div>
                <div class="radar-center-icon">♟️</div>
            </div>

            <div style="font-family: 'JetBrains Mono', monospace; font-size: 1.2rem; font-weight: 800; color: var(--gold);" id="mmTimerDisplay">
                00:03
            </div>
            <div style="color: #cbd5e1; font-size: 0.9rem; margin-top: 4px;" id="mmStatusText">
                🔍 Đang dò tìm kỳ thủ trực tuyến...
            </div>

            <div style="margin: 15px 0 20px 0; width: 100%;">
                <label style="font-size: 0.8rem; font-weight: 700; color: #94a3b8; display: block; margin-bottom: 8px;">MỨC CƯỢC THÁCH ĐẤU:</label>
                <div class="bet-chips-row" style="margin: 0;">
                    <div class="bet-chip-btn active" onclick="selectMmBet(10000)">10K</div>
                    <div class="bet-chip-btn" onclick="selectMmBet(50000)">50K</div>
                    <div class="bet-chip-btn" onclick="selectMmBet(100000)">100K</div>
                    <div class="bet-chip-btn" onclick="selectMmBet(500000)">500K</div>
                </div>
            </div>

            <button onclick="closeMatchmakingModal()" class="btn-launch-mode" style="background: rgba(255,255,255,0.1); border: 1px solid var(--border);">
                HỦY TÌM TRẬN
            </button>
        </div>
    </div>

    <script>
        let selectedAiBet = 10000;
        let selectedMmBet = 10000;
        let isAiPlaying = false;
        let aiBoardState = Array.from({ length: 15 }, () => Array(15).fill(0));
        let aiGameActive = false;
        let audioCtx = null;

        function playWoodClickSound() {
            try {
                if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                if (audioCtx.state === 'suspended') audioCtx.resume();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(320, audioCtx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(120, audioCtx.currentTime + 0.08);
                gain.gain.setValueAtTime(0.7, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.08);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.09);
            } catch (e) {}
        }

        function selectAiBet(amt) {
            selectedAiBet = amt;
            $('.mode-card:first .bet-chip-btn').removeClass('active');
            $(event.target).addClass('active');
        }

        function selectMmBet(amt) {
            selectedMmBet = amt;
            $('#matchmakingModal .bet-chip-btn').removeClass('active');
            $(event.target).addClass('active');
        }

        function handleBackAction() {
            if (isAiPlaying) {
                backToLobby();
            } else {
                window.location.href = 'index.php';
            }
        }

        function backToLobby() {
            isAiPlaying = false;
            aiGameActive = false;
            $('#caroAiMatchView').hide();
            $('#caroLobbyView').show();
            $('#topbarBackText').text('Về Trang Chủ');
        }

        // ═══════════════════════════════════════════════════════════════════
        // CHẾ ĐỘ 1: ĐẤU VỚI AI ĐỘC LẬP TẠI CHỖ (KHÔNG TẠO CHALLENGE BOT_66)
        // ═══════════════════════════════════════════════════════════════════
        function launchAiGameDirect() {
            Swal.fire({
                title: 'Khởi tạo bàn cờ...',
                didOpen: () => Swal.showLoading(),
                background: '#0f172a', color: '#fff',
                timer: 400
            });

            $.post('pvp_caro.php', {
                action: 'start_ai_game',
                bet_amount: selectedAiBet
            }, function(res) {
                if (res.success) {
                    $('#userMoneyTop').text(res.new_balance);
                    isAiPlaying = true;
                    aiGameActive = true;
                    $('#caroLobbyView').hide();
                    $('#caroAiMatchView').show();
                    $('#aiWinnerOverlay').hide();
                    $('#topbarBackText').text('Rời Bàn Cờ');
                    initAiBoard();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Không thể vào bàn',
                        text: res.message || 'Lỗi hệ thống',
                        background: '#0f172a', color: '#fff'
                    });
                }
            }, 'json');
        }

        function initAiBoard() {
            aiBoardState = Array.from({ length: 15 }, () => Array(15).fill(0));
            const board = $('#aiBoard');
            board.empty();
            for (let r = 0; r < 15; r++) {
                for (let c = 0; c < 15; c++) {
                    board.append(`<div class="cell" data-row="${r}" data-col="${c}" onclick="makeAiMove(${r}, ${c})"></div>`);
                }
            }
            $('#aiStatusBar').text('👉 Đến lượt bạn hạ quân cờ X!').addClass('status-my-turn').removeClass('status-opponent-turn');
        }

        function makeAiMove(r, c) {
            if (!aiGameActive) return;
            if (aiBoardState[r][c] !== 0) return;

            // Người chơi đi X
            aiBoardState[r][c] = 1;
            playWoodClickSound();
            $(`.cell[data-row="${r}"][data-col="${c}"]`).html('<span class="symbol-x">X</span>').addClass('filled last-move');

            // Kiểm tra người chơi thắng
            const winLine = checkCaroWin(aiBoardState, r, c, 1);
            if (winLine) {
                handleAiMatchEnd(1, winLine);
                return;
            }

            // AI tính toán và đi O
            $('#aiStatusBar').text('⏳ Đại Sư Ca-rô đang tính nước...').addClass('status-opponent-turn').removeClass('status-my-turn');
            aiGameActive = false;

            setTimeout(() => {
                const [ar, ac] = calculateBestAiMove(aiBoardState);
                aiBoardState[ar][ac] = 2;
                playWoodClickSound();
                $('.cell').removeClass('last-move');
                $(`.cell[data-row="${ar}"][data-col="${ac}"]`).html('<span class="symbol-o">O</span>').addClass('filled last-move');

                const aiWinLine = checkCaroWin(aiBoardState, ar, ac, 2);
                if (aiWinLine) {
                    handleAiMatchEnd(2, aiWinLine);
                    return;
                }

                aiGameActive = true;
                $('#aiStatusBar').text('👉 Đến lượt bạn hạ quân cờ X!').addClass('status-my-turn').removeClass('status-opponent-turn');
            }, 250);
        }

        function handleAiMatchEnd(winner, winLine) {
            aiGameActive = false;
            winLine.forEach(([wr, wc]) => {
                $(`.cell[data-row="${wr}"][data-col="${wc}"]`).addClass('win-cell');
            });

            const win = (winner === 1) ? 1 : 0;
            $.post('pvp_caro.php', {
                action: 'finish_ai_game',
                win: win,
                bet_amount: selectedAiBet
            }, function(res) {
                if (res.success) {
                    $('#userMoneyTop').text(res.new_balance);
                    setTimeout(() => {
                        $('#aiWinnerOverlay').css('display', 'flex');
                        if (win === 1) {
                            $('#aiWinnerMsg').text('🏆 BẠN THẮNG!').css('color', '#10b981');
                            $('#aiRewardMsg').text('Bạn húp trọn +' + res.reward.toLocaleString('vi-VN') + ' GTLM!').css('color', '#4ade80');
                        } else {
                            $('#aiWinnerMsg').text('😢 BẠN THUA!').css('color', '#ef4444');
                            $('#aiRewardMsg').text('Đại Sư Ca-rô đã khóa thế cờ ngũ tử!').css('color', '#f87171');
                        }
                    }, 400);
                }
            }, 'json');
        }

        // Thuật toán kiểm tra thắng 5 quân
        function checkCaroWin(bd, r, c, p) {
            const dirs = [
                [[0, 1], [0, -1]],
                [[1, 0], [-1, 0]],
                [[1, 1], [-1, -1]],
                [[1, -1], [-1, 1]]
            ];
            for (let pair of dirs) {
                let line = [[r, c]];
                for (let [dr, dc] of pair) {
                    let nr = r + dr, nc = c + dc;
                    while (nr >= 0 && nr < 15 && nc >= 0 && nc < 15 && bd[nr][nc] === p) {
                        line.push([nr, nc]);
                        nr += dr; nc += dc;
                    }
                }
                if (line.length >= 5) return line;
            }
            return null;
        }

        // Thuật toán AI Heuristic thông minh
        function calculateBestAiMove(bd) {
            const dirs = [
                [[0, 1], [0, -1]],
                [[1, 0], [-1, 0]],
                [[1, 1], [-1, -1]],
                [[1, -1], [-1, 1]]
            ];

            function evalPos(r, c, p) {
                let score = 0;
                for (let pair of dirs) {
                    let count = 1, open = 0;
                    for (let [dr, dc] of pair) {
                        let nr = r + dr, nc = c + dc;
                        while (nr >= 0 && nr < 15 && nc >= 0 && nc < 15 && bd[nr][nc] === p) {
                            count++; nr += dr; nc += dc;
                        }
                        if (nr >= 0 && nr < 15 && nc >= 0 && nc < 15 && bd[nr][nc] === 0) open++;
                    }
                    if (count >= 5) score += 100000;
                    else if (count === 4 && open === 2) score += 20000;
                    else if (count === 4 && open === 1) score += 5000;
                    else if (count === 3 && open === 2) score += 3500;
                    else if (count === 3 && open === 1) score += 500;
                    else if (count === 2 && open === 2) score += 150;
                }
                return score;
            }

            let bestScore = -1, bestMoves = [];
            for (let r = 0; r < 15; r++) {
                for (let c = 0; c < 15; c++) {
                    if (bd[r][c] !== 0) continue;
                    let myS = evalPos(r, c, 2);
                    let opS = evalPos(r, c, 1);
                    let total = (myS * 1.2) + (opS * 1.05) + Math.max(0, 15 - (Math.abs(r - 7) + Math.abs(c - 7))) + Math.random() * 5;
                    if (total > bestScore) {
                        bestScore = total;
                        bestMoves = [[r, c]];
                    } else if (Math.abs(total - bestScore) < 5) {
                        bestMoves.push([r, c]);
                    }
                }
            }
            return bestMoves.length ? bestMoves[Math.floor(Math.random() * bestMoves.length)] : [7, 7];
        }

        // ═══════════════════════════════════════════════════════════════════
        // CHẾ ĐỘ 2: CƠ CHẾ TÌM TRẬN 1VS1 (MATCHMAKING RADAR)
        // ═══════════════════════════════════════════════════════════════════
        let mmTimer = null;
        let mmSeconds = 0;

        function openMatchmakingModal() {
            $('#matchmakingModal').addClass('active');
            mmSeconds = 0;
            $('#mmTimerDisplay').text('00:00');
            $('#mmStatusText').text('🔍 Đang dò tìm kỳ thủ trực tuyến...');

            mmTimer = setInterval(() => {
                mmSeconds++;
                const s = String(mmSeconds).padStart(2, '0');
                $('#mmTimerDisplay').text(`00:${s}`);
            }, 1000);

            // Bắt đầu quét tìm phòng trong hệ thống
            setTimeout(() => {
                $.post('pvp_caro.php', {
                    action: 'matchmake_search',
                    bet_amount: selectedMmBet
                }, function(res) {
                    if (res.success && res.challenge_id) {
                        clearInterval(mmTimer);
                        $('#mmStatusText').html('🎉 ĐÃ TÌM THẤY ĐỐI THỦ! Đang vào bàn cờ...');
                        setTimeout(() => {
                            window.location.href = 'pvp_caro.php?id=' + res.challenge_id;
                        }, 800);
                    } else {
                        closeMatchmakingModal();
                        Swal.fire({
                            icon: 'warning',
                            title: 'Chưa tìm được đối thủ',
                            text: res.message || 'Vui lòng thử lại sau giây lát!',
                            background: '#0f172a', color: '#fff'
                        });
                    }
                }, 'json').fail(function() {
                    closeMatchmakingModal();
                });
            }, 2500);
        }

        function closeMatchmakingModal() {
            clearInterval(mmTimer);
            $('#matchmakingModal').removeClass('active');
        }
    </script>

    <?php else: ?>
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- 2. BÀN CỜ CA-RÔ TRỰC TIẾP (KHI ĐANG TRONG TRẬN ĐẤU PVP_CARO.PHP?ID=X)-->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <main class="caro-match-layout">
        <div class="game-header">
            <div class="player-info" id="player1">
                <span class="player-name"><?= htmlspecialchars($challenge['challenger_name']) ?></span>
                <span class="player-symbol symbol-x">X</span>
            </div>
            <div class="vs-badge">VS</div>
            <div class="player-info" id="player2">
                <span class="player-name"><?= htmlspecialchars($challenge['opponent_name']) ?></span>
                <span class="player-symbol symbol-o">O</span>
            </div>
        </div>

        <div class="board-container">
            <div class="winner-overlay" id="winnerOverlay">
                <div class="winner-msg" id="winnerMsg">BẠN THẮNG!</div>
                <p id="rewardMsg" style="font-size: 1.2rem; font-weight: 700; color: #4ade80;">Bạn nhận được +<?= number_format($challenge['bet_amount'] * 2) ?> GTLM</p>
                <div style="display: flex; gap: 12px; margin-top: 20px; flex-wrap: wrap; justify-content: center;">
                    <a href="pvp_caro.php" class="btn-launch-mode" style="width: auto; padding: 12px 25px; background: linear-gradient(135deg, #10b981, #059669); text-decoration: none;">
                        <i class="fa fa-chess-board"></i> VỀ SẢNH ĐẤU TRƯỜNG
                    </a>
                </div>
            </div>
            <div class="caro-board" id="board">
                <!-- 15x15 Cells via JS -->
            </div>
        </div>

        <div class="status-bar status-my-turn" id="statusBar">
            Đang khởi tạo trận đấu...
        </div>
    </main>

    <script>
        const challengeId = <?= $challengeId ?>;
        const myId = <?= $userId ?>;
        const challengerId = <?= (int)$challenge['challenger_id'] ?>;
        const opponentId = <?= (int)$challenge['opponent_id'] ?>;
        const betAmount = <?= (float)$challenge['bet_amount'] ?>;

        let gameActive = true;
        let isMyTurn = false;
        let audioCtx = null;

        function playWoodClickSound() {
            try {
                if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                if (audioCtx.state === 'suspended') audioCtx.resume();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(320, audioCtx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(120, audioCtx.currentTime + 0.08);
                gain.gain.setValueAtTime(0.7, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.08);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.09);
            } catch (e) {}
        }

        function initBoard() {
            const board = $('#board');
            board.empty();
            for (let r = 0; r < 15; r++) {
                for (let c = 0; c < 15; c++) {
                    board.append(`<div class="cell" data-row="${r}" data-col="${c}" onclick="makeMove(${r}, ${c})"></div>`);
                }
            }
        }

        function updateUI(challengeData) {
            const boardData = typeof challengeData.board_state === 'string' ? JSON.parse(challengeData.board_state) : challengeData.board_state;
            if (!boardData) return;

            $('.cell').removeClass('filled last-move').empty();
            
            boardData.forEach((row, r) => {
                row.forEach((cell, c) => {
                    const cellEl = $(`.cell[data-row="${r}"][data-col="${c}"]`);
                    if (cell === 1) {
                        cellEl.html('<span class="symbol-x">X</span>').addClass('filled');
                    } else if (cell === 2) {
                        cellEl.html('<span class="symbol-o">O</span>').addClass('filled');
                    }
                });
            });

            if (challengeData.status === 'completed') {
                gameActive = false;
                $('#winnerOverlay').css('display', 'flex');
                if (challengeData.winner_id == myId) {
                    $('#winnerMsg').text('🏆 BẠN THẮNG!').css('color', '#10b981');
                    $('#rewardMsg').text('Bạn húp trọn +' + (betAmount * 2).toLocaleString() + ' GTLM!').css('color', '#4ade80');
                } else if (challengeData.winner_id) {
                    $('#winnerMsg').text('😢 BẠN THUA!').css('color', '#ef4444');
                    $('#rewardMsg').text('Bạn bị trừ -' + betAmount.toLocaleString() + ' GTLM').css('color', '#f87171');
                } else {
                    $('#winnerMsg').text('🤝 HÒA NHAU!').css('color', '#fbbf24');
                }
                $('#statusBar').text('Trận đấu kết thúc').removeClass('status-my-turn status-opponent-turn');
                return;
            }

            isMyTurn = (challengeData.current_turn_id == myId);
            if (isMyTurn) {
                $('#statusBar').text('👉 Đến lượt bạn hạ quân cờ!').addClass('status-my-turn').removeClass('status-opponent-turn');
            } else {
                $('#statusBar').text('⏳ Đang chờ đối thủ hạ cờ...').addClass('status-opponent-turn').removeClass('status-my-turn');
            }
        }

        function pollGame() {
            if (!gameActive) return;

            $.get('api_pvp_challenge.php', { action: 'get_challenge', challenge_id: challengeId }, function(res) {
                if (res.success && res.challenge) {
                    updateUI(res.challenge);
                }
                if (gameActive) {
                    setTimeout(pollGame, 1800);
                }
            });
        }

        function makeMove(row, col) {
            if (!isMyTurn || !gameActive) return;

            const targetCell = $(`.cell[data-row="${row}"][data-col="${col}"]`);
            if (targetCell.hasClass('filled')) return;

            playWoodClickSound();
            const playerSymbol = (myId == challengerId) ? 'X' : 'O';
            const symbolClass = (myId == challengerId) ? 'symbol-x' : 'symbol-o';
            targetCell.html(`<span class="${symbolClass}">${playerSymbol}</span>`).addClass('filled last-move');
            isMyTurn = false;
            $('#statusBar').text('Đang xử lý nước cờ...').addClass('status-opponent-turn').removeClass('status-my-turn');

            $.post('api_pvp_challenge.php', {
                action: 'make_move',
                challenge_id: challengeId,
                row: row,
                col: col
            }, function(res) {
                if (res.success) {
                    if (res.is_win) {
                        gameActive = false;
                        setTimeout(() => {
                            $('#winnerOverlay').css('display', 'flex');
                            $('#winnerMsg').text('🏆 BẠN THẮNG!').css('color', '#10b981');
                            $('#rewardMsg').text('Bạn húp trọn +' + (betAmount * 2).toLocaleString() + ' GTLM!');
                            $('#statusBar').text('Trận đấu kết thúc').removeClass('status-my-turn status-opponent-turn');
                        }, 300);
                    }
                } else {
                    Swal.fire({
                        text: res.message || 'Lỗi nước đi!',
                        icon: 'warning',
                        background: '#0f172a', color: '#fff', timer: 1500
                    });
                    isMyTurn = true;
                    targetCell.empty().removeClass('filled last-move');
                }
            }, 'json');
        }

        function handleBackAction() {
            window.location.href = 'pvp_caro.php';
        }

        $(document).ready(function() {
            initBoard();
            pollGame();

            // ⚡ Kết nối WebSocket Realtime 0ms cho ván cờ
            if (typeof GTLMRealtime !== 'undefined' && challengeId > 0) {
                GTLMRealtime.subscribe('pvp_' + challengeId, {
                    userId: myId,
                    username: <?= json_encode($userName) ?>
                });

                GTLMRealtime.on('caro_move', function(data) {
                    if (!gameActive) return;
                    if (data && data.challenge_id == challengeId) {
                        // Nước đi đối thủ nhận tức thì qua WebSocket 0ms
                        $.get('api_pvp_challenge.php', { action: 'get_challenge', challenge_id: challengeId }, function(res) {
                            if (res.success && res.challenge) {
                                playWoodClickSound();
                                updateUI(res.challenge);
                            }
                        });
                    }
                });
            }
        });
    </script>
    <?php endif; ?>

</body>
</html>
