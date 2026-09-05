<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';

$botUser = getOrCreateBotStreamerUser($conn, 'bot_46', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require '../db_connect.php';

// AJAX history endpoint
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_history') {
    header('Content-Type: application/json; charset=utf-8');
    
    $id = $botUserId ?? 0;
    $sql = "SELECT * FROM history_roulette WHERE Iduser = ? ORDER BY Time DESC LIMIT 20";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'history' => $history
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$useBotTheme = $botUserId;
require_once '../load_theme.php';

// Đảm bảo các biến theme luôn tồn tại
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #072a1a 0%, #0d452c 50%, #03140c 100%)';
}
if (!isset($particleCount)) $particleCount = 500;
if (!isset($particleSize)) $particleSize = 0.05;
if (!isset($particleColor)) $particleColor = '#ffd700';
if (!isset($particleOpacity)) $particleOpacity = 0.5;
if (!isset($shapeCount)) $shapeCount = 15;
if (!isset($shapeColors)) $shapeColors = ['#ffd700', '#00ff88', '#e74c3c', '#f1c40f'];
if (!isset($shapeOpacity)) $shapeOpacity = 0.25;
if (!isset($bgGradient)) $bgGradient = ['#072a1a', '#0d452c', '#03140c'];

$userId = $botUserId;
$sql = "SELECT Money, Name FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get statistics from database for chart
$gameThang = 0;
$gameThua = 0;
$sqlStats = "SELECT COUNT(*) as total, SUM(CASE WHEN WinAmount > 0 THEN 1 ELSE 0 END) as wins FROM history_roulette WHERE Iduser = ?";
$stmtStats = $conn->prepare($sqlStats);
$stmtStats->bind_param("i", $userId);
$stmtStats->execute();
$resultStats = $stmtStats->get_result();
if ($rowStats = $resultStats->fetch_assoc()) {
    $gameThang = $rowStats['wins'] ?? 0;
    $gameThua = ($rowStats['total'] ?? 0) - $gameThang;
}
$stmtStats->close();

$soDu = $user['Money'] ?? 50000000;
$tenNguoiChoi = $user['Name'] ?? 'Bot Streamer';

// --- ROULETTE PRO DATA ---
$redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
$blackNumbers = [2, 4, 6, 8, 10, 11, 13, 15, 17, 20, 22, 24, 26, 28, 29, 31, 33, 35];

function getNumberColor($n)
{
    global $redNumbers;
    if ($n === 0)
        return "green";
    return in_array($n, $redNumbers) ? "red" : "black";
}

// --- AJAX HANDLER ---
if (isset($_GET['action']) && $_GET['action'] === 'spin_pro') {
    header('Content-Type: application/json');
    $bets = json_decode($_POST['bets'] ?? '[]', true);

    if (empty($bets)) {
        echo json_encode(['success' => false, 'message' => '⚠️ Vui lòng đặt cược trước khi quay!']);
        exit;
    }

    $totalBet = 0;
    foreach ($bets as $b) {
        $totalBet += (float) $b['amount'];
    }

    if ($totalBet <= 0) {
        echo json_encode(['success' => false, 'message' => '⚠️ Số Gtlm cược không hợp lệ!']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // SELECT FOR UPDATE để khóa bản ghi user
        $stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ? FOR UPDATE");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || $user['Money'] < $totalBet) {
            throw new Exception('⚠️ Số Gtlm không đủ cho tổng cược!');
        }

        $winningNumber = rand(0, 36);
        $color = getNumberColor($winningNumber);

        $totalWin = 0;
        $breakdown = [];

        foreach ($bets as $b) {
            $type = $b['type'];
            $val = $b['value'];
            $amt = (float) $b['amount'];
            $win = 0;

            switch ($type) {
                case 'straight':
                    if ($winningNumber == $val) $win = $amt * 36;
                    break;
                case 'red':
                    if ($color === 'red') $win = $amt * 2;
                    break;
                case 'black':
                    if ($color === 'black') $win = $amt * 2;
                    break;
                case 'even':
                    if ($winningNumber != 0 && $winningNumber % 2 == 0) $win = $amt * 2;
                    break;
                case 'odd':
                    if ($winningNumber != 0 && $winningNumber % 2 != 0) $win = $amt * 2;
                    break;
                case 'low':
                    if ($winningNumber >= 1 && $winningNumber <= 18) $win = $amt * 2;
                    break;
                case 'high':
                    if ($winningNumber >= 19 && $winningNumber <= 36) $win = $amt * 2;
                    break;
                case 'dozen':
                    if ($val == 1 && $winningNumber >= 1 && $winningNumber <= 12) $win = $amt * 3;
                    if ($val == 2 && $winningNumber >= 13 && $winningNumber <= 24) $win = $amt * 3;
                    if ($val == 3 && $winningNumber >= 25 && $winningNumber <= 36) $win = $amt * 3;
                    break;
                case 'column':
                    if ($winningNumber != 0 && ($winningNumber - $val) % 3 == 0) $win = $amt * 3;
                    break;
            }

            if ($win > 0) {
                $totalWin += $win;
                $breakdown[] = "Cược $type: Thắng " . number_format($win) . " gtlm";
            }
        }

        // Cập nhật số dư tương đối
        $stmt = $conn->prepare("UPDATE users SET Money = Money - ? + ? WHERE Iduser = ?");
        $stmt->bind_param("ddi", $totalBet, $totalWin, $userId);
        $stmt->execute();

        // Ghi log lịch sử riêng của roulette
        $historyStmt = $conn->prepare("INSERT INTO history_roulette (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $resultStr = "Số $winningNumber ($color)";
        $historyStmt->bind_param("idid", $userId, $totalBet, $resultStr, $totalWin);
        $historyStmt->execute();
        $historyStmt->close();

        // Ghi log tổng quát (Quest, BattlePass, etc)
        if (file_exists('../game_history_helper.php')) {
            require_once '../game_history_helper.php';
            logGameHistoryWithAll($conn, $userId, 'Roulette Pro', $totalBet, $totalWin, ($totalWin > 0));
        }

        $conn->commit();
        
        $newMoneyVal = $user['Money'] - $totalBet + $totalWin;

        echo json_encode([
            'success' => true,
            'number' => $winningNumber,
            'color' => $color,
            'totalWin' => $totalWin,
            'totalBet' => $totalBet,
            'newMoney' => number_format($newMoneyVal) . ' gtlm',
            'breakdown' => $breakdown,
            'message' => ($totalWin > 0) ? "🎉 CHIẾN THẮNG: TRÚNG SỐ $winningNumber! (" . strtoupper($color) . ")" : "💀 KẾT QUẢ: SỐ $winningNumber ($color). CHÚC BẠN MAY MẮN LẦN SAU!"
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Roulette Royal - Premium Casino</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/canvas-confetti/1.6.0/confetti.browser.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Poppins:wght@400;600;800&family=Orbitron:wght@600;800;900&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --gold: #ffd700;
            --gold-dark: #b8860b;
            --bg: #072a1a;
            --border: rgba(255, 215, 0, 0.3);
        }

        body {
            margin: 0;
            cursor: url('../img/chuot.png'), auto !important;
            font-family: 'Poppins', sans-serif;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            color: white;
            overflow-x: hidden;
            padding-bottom: 50px;
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

        /* Header */
        .casino-header {
            width: 100%;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            margin-bottom: 30px;
            box-sizing: border-box;
        }

        .logo-text {
            font-family: 'Cinzel', serif;
            font-size: 28px;
            color: var(--gold);
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
            letter-spacing: 5px;
        }

        .balance-pill {
            background: rgba(0, 0, 0, 0.6);
            padding: 10px 25px;
            border-radius: 30px;
            border: 1px solid var(--gold);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            color: var(--gold);
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.2);
        }

        /* Main Game Area */
        .game-wrapper {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: 50px;
            width: 100%;
            max-width: 1200px;
            padding: 0 20px;
            box-sizing: border-box;
            transition: transform 0.1s ease;
        }

        .game-wrapper.lose-shake {
            animation: lose-shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
        }

        @keyframes lose-shake {
            10%, 90% { transform: translate3d(-2px, 0, 0); }
            20%, 80% { transform: translate3d(3px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-6px, 0, 0); }
            40%, 60% { transform: translate3d(6px, 0, 0); }
        }

        /* Wheel Section */
        .wheel-container {
            position: relative;
            width: 300px;
            height: 300px;
            margin-top: 20px;
        }

        .wheel-outer-frame {
            position: absolute;
            inset: -15px;
            border-radius: 50%;
            border: 12px solid #3d2b1f;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 1), inset 0 0 20px rgba(0, 0, 0, 0.8);
            background: radial-gradient(circle, #4d3b2f, #1a0f0a);
        }

        .roulette-wheel {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: #2c3e50;
            border: 10px solid #222;
            position: relative;
            overflow: hidden;
        }

        .wheel-inner {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            transition: transform 6s cubic-bezier(0.1, 0, 0.1, 1);
            background: conic-gradient(#27ae60 0deg 9.73deg, #e74c3c 9.73deg 19.46deg, #2c3e50 19.46deg 29.19deg, #e74c3c 29.19deg 38.92deg,
                    #2c3e50 38.92deg 48.65deg, #e74c3c 48.65deg 58.38deg, #2c3e50 58.38deg 68.11deg, #e74c3c 68.11deg 77.84deg,
                    #2c3e50 77.84deg 87.57deg, #e74c3c 87.57deg 97.3deg, #27ae60 97.3deg 106.03deg, #e74c3c 106.03deg 115.76deg,
                    #2c3e50 115.76deg 125.49deg, #e74c3c 125.49deg 135.22deg, #2c3e50 135.22deg 144.95deg, #e74c3c 144.95deg 154.68deg,
                    #2c3e50 154.68deg 164.41deg, #e74c3c 164.41deg 174.14deg, #2c3e50 174.14deg 183.87deg, #e74c3c 183.87deg 193.6deg,
                    #2c3e50 193.6deg 203.33deg, #e74c3c 203.33deg 213.06deg, #2c3e50 213.06deg 222.79deg, #e74c3c 222.79deg 232.52deg,
                    #2c3e50 232.52deg 242.25deg, #e74c3c 242.25deg 251.98deg, #2c3e50 251.98deg 261.71deg, #e74c3c 261.71deg 271.44deg,
                    #2c3e50 271.44deg 281.17deg, #e74c3c 281.17deg 290.9deg, #2c3e50 290.9deg 300.63deg, #e74c3c 300.63deg 310.36deg,
                    #2c3e50 310.36deg 320.09deg, #e74c3c 320.09deg 329.82deg, #2c3e50 329.82deg 339.55deg, #e74c3c 339.55deg 349.28deg, #2c3e50 349.28deg 360deg);
        }

        .pointer {
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 15px solid transparent;
            border-right: 15px solid transparent;
            border-top: 30px solid var(--gold);
            z-index: 10;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.8));
        }

        .result-orb {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 70px;
            height: 70px;
            background: radial-gradient(circle at 30% 30%, #444, #111);
            border: 2px solid var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 800;
            color: var(--gold);
            z-index: 15;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.8);
            transition: background-color 0.4s ease, color 0.4s ease;
        }
        
        .wheel-number {
            position: absolute;
            width: 30px;
            height: 50%;
            left: calc(50% - 15px);
            top: 0;
            transform-origin: bottom center;
            display: flex;
            justify-content: center;
            padding-top: 5px;
            font-size: 13px;
            font-weight: 800;
            color: white;
            text-shadow: 1px 1px 2px black;
            box-sizing: border-box;
            user-select: none;
        }

        /* Betting Board - Clean Edition */
        .board-glass {
            background: rgba(10, 60, 40, 0.85);
            backdrop-filter: blur(20px);
            border: 3px solid #4a3728;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.7);
            flex: 1;
            min-width: 600px;
            max-width: 800px;
            border-style: double;
        }

        .grid-master {
            display: grid;
            grid-template-columns: 60px repeat(12, 1fr) 70px;
            grid-template-rows: repeat(3, 45px) 35px 35px;
            gap: 4px;
        }

        .cell {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
            color: rgba(255, 255, 255, 0.8);
            position: relative;
            border-radius: 4px;
        }

        .cell:hover {
            transform: scale(1.05);
            z-index: 2;
            border-color: var(--gold);
            background: rgba(255, 215, 0, 0.1);
        }

        .cell.red {
            background: #e74c3c;
            color: white;
            border-color: #c0392b;
        }

        .cell.black {
            background: #2c3e50;
            color: white;
            border-color: #1a252f;
        }

        .cell.green {
            background: #27ae60;
            color: white;
            grid-row: 1 / 4;
            grid-column: 1;
            border-color: #1e8449;
            font-size: 20px;
        }

        .cell.active {
            background: radial-gradient(circle, #f1c40f, #f39c12) !important;
            color: #333 !important;
            border-color: white;
            box-shadow: 0 0 15px #f1c40f;
            animation: pulse 1s infinite alternate;
        }

        .cell.active::after {
            content: '🪙';
            position: absolute;
            font-size: 20px;
            top: -8px;
            right: -8px;
        }

        .cell.winner {
            border-color: #ffd700 !important;
            background: radial-gradient(circle, #f1c40f, #27ae60) !important;
            box-shadow: 0 0 35px #ffd700, inset 0 0 15px #fff !important;
            animation: cell-bounce 0.6s infinite !important;
            z-index: 5;
            color: #000 !important;
        }

        @keyframes cell-bounce {
            0%, 100% { transform: scale(1.05); }
            50% { transform: scale(1.15) translateY(-5px); }
        }

        .floating-win {
            position: absolute;
            bottom: 50%;
            left: 50%;
            transform: translateX(-50%);
            color: var(--gold);
            font-family: 'Orbitron', 'Poppins', sans-serif;
            font-weight: 900;
            font-size: 1.2rem;
            pointer-events: none;
            text-shadow: 0 0 10px #000, 0 0 20px rgba(0,0,0,0.8);
            z-index: 100;
            white-space: nowrap;
        }

        @keyframes pulse {
            from { transform: scale(1.02); }
            to { transform: scale(1.08); }
        }

        .dozen-cell {
            grid-row: 4;
            grid-column: span 4;
            font-size: 14px;
            background: rgba(0, 0, 0, 0.4);
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .outside-cell {
            grid-row: 5;
            grid-column: span 2;
            font-size: 12px;
            background: rgba(0, 0, 0, 0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .col-cell {
            grid-column: 14;
            background: rgba(0, 0, 0, 0.5);
            font-size: 12px;
        }

        /* Control Bar */
        .casino-controls {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            background: rgba(0, 0, 0, 0.7);
            padding: 20px 40px;
            border-radius: 30px;
            border: 2px solid var(--border);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 1200px;
            box-sizing: border-box;
        }

        .chip-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
        }

        .chip {
            padding: 8px 15px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.9rem;
            color: white;
            transition: 0.3s;
            user-select: none;
        }

        .chip:hover, .chip.active {
            background: var(--gold);
            color: #000;
            border-color: var(--gold);
            transform: scale(1.1);
        }

        .controls-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 20px;
            width: 100%;
        }

        .money-input {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid var(--border);
            border-radius: 20px;
            padding: 10px 20px;
            color: var(--gold);
            font-size: 18px;
            font-weight: 800;
            width: 150px;
            outline: none;
            transition: 0.3s;
            text-align: center;
        }

        .money-input:focus {
            border-color: var(--gold);
            background: rgba(255, 255, 255, 0.1);
        }

        .btn-casino {
            padding: 15px 45px;
            border-radius: 35px;
            border: none;
            font-size: 18px;
            font-weight: 900;
            letter-spacing: 2px;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.3);
        }

        .btn-gold {
            background: linear-gradient(135deg, #f1c40f 0%, #d35400 100%);
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .btn-gold:hover {
            transform: translateY(-3px) scale(1.05);
            filter: brightness(1.1);
            box-shadow: 0 12px 20px rgba(241, 196, 15, 0.3);
        }

        .btn-gold:disabled {
            filter: grayscale(1);
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .btn-danger {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid #e74c3c;
            color: #e74c3c;
        }

        .btn-danger:hover {
            background: #e74c3c;
            color: white;
        }

        .status-marquee {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.9);
            border: 1px solid var(--gold);
            color: var(--gold);
            padding: 8px 30px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 1px;
            min-width: 400px;
            text-align: center;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.1);
            z-index: 100;
        }
    </style>
</head>

<body>

    <header class="casino-header">
        <div class="logo-text">ROULETTE ROYAL</div>
        <div class="balance-pill" id="balance-pill">
            <span>GTLM:</span>
            <span id="balance-val" style="font-size: 22px;"><?= number_format($soDu) ?></span>
        </div>
        <div style="font-size: 14px; color: #888;">PLAYER: <b><?= htmlspecialchars($tenNguoiChoi) ?></b></div>
    </header>

    <div class="game-wrapper" id="game-wrapper">
        <!-- Visualization: Wheel -->
        <div class="wheel-container">
            <div class="wheel-outer-frame"></div>
            <div class="pointer"></div>
            <div class="roulette-wheel">
                <div class="wheel-inner" id="wheel-inner"></div>
            </div>
            <div class="result-orb" id="result-orb">?</div>
        </div>

        <!-- Betting: Board -->
        <div class="board-glass">
            <div class="grid-master">
                <!-- Zero -->
                <div class="cell green" data-type="straight" data-val="0">0</div>

                <!-- Number Logic -->
                <?php
                $nums = [
                    [3, 6, 9, 12, 15, 18, 21, 24, 27, 30, 33, 36],
                    [2, 5, 8, 11, 14, 17, 20, 23, 26, 29, 32, 35],
                    [1, 4, 7, 10, 13, 16, 19, 22, 25, 28, 31, 34]
                ];
                foreach ($nums as $rowIdx => $row):
                    foreach ($row as $n):
                        $color = in_array($n, $redNumbers) ? 'red' : 'black';
                        echo "<div class='cell $color' data-type='straight' data-val='$n'>$n</div>";
                    endforeach;
                    echo "<div class='cell col-cell' data-type='column' data-val='" . (3 - $rowIdx) . "'>2 TO 1</div>";
                endforeach;
                ?>

                <!-- Outside -->
                <div class="cell dozen-cell" data-type="dozen" data-val="1">1st 12</div>
                <div class="cell dozen-cell" data-type="dozen" data-val="2">2nd 12</div>
                <div class="cell dozen-cell" data-type="dozen" data-val="3">3rd 12</div>
                <div></div> <!-- Spacer -->

                <div class="cell outside-cell" data-type="low" data-val="low">1-18</div>
                <div class="cell outside-cell" data-type="even" data-val="even">EVEN</div>
                <div class="cell outside-cell red" data-type="red" data-val="red">RED</div>
                <div class="cell outside-cell black" data-type="black" data-val="black">BLACK</div>
                <div class="cell outside-cell" data-type="odd" data-val="odd">ODD</div>
                <div class="cell outside-cell" data-type="high" data-val="high">19-36</div>
            </div>
        </div>

        <!-- Interactive: Controls -->
        <div class="casino-controls">
            <div class="chip-selector">
                <div class="chip active" data-value="10000">10K</div>
                <div class="chip" data-value="50000">50K</div>
                <div class="chip" data-value="100000">100K</div>
                <div class="chip" data-value="500000">500K</div>
                <div class="chip" data-value="1000000">1M</div>
                <div class="chip" data-value="5000000">5M</div>
                <div class="chip" data-value="allin">MAX</div>
            </div>
            <div class="controls-row">
                <input type="number" id="bet-amount" class="money-input" value="10000" step="5000">
                <button class="btn-casino btn-danger" id="btn-clear">CLEAR BETS</button>
                <button class="btn-casino btn-gold" id="btn-spin">PLACE BETS & SPIN</button>
                <a href="../index.php" style="color: #666; font-size: 15px; text-decoration: none; font-weight: bold; padding: 10px;">QUIT SESSION</a>
            </div>
        </div>
    </div>

    <div class="status-marquee" id="status-marquee">WELCOME TO THE HIGH TABLE. PLACE YOUR BETS.</div>

    <canvas id="threejs-background"></canvas>

    <script>
        // 1. ThreeJS Background and Effects Loader
        (function () {
            window.themeConfig = { 
                particleCount: <?= (int)$particleCount ?>, 
                particleSize: <?= (float)$particleSize ?>, 
                particleColor: '<?= htmlspecialchars($particleColor) ?>', 
                particleOpacity: <?= (float)$particleOpacity ?>, 
                shapeCount: <?= (int)$shapeCount ?>, 
                shapeColors: <?= json_encode($shapeColors) ?>, 
                shapeOpacity: <?= (float)$shapeOpacity ?>, 
                bgGradient: <?= json_encode($bgGradient) ?> 
            };
            const prefix = '../';
            ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'].forEach(src => {
                const script = document.createElement('script');
                script.src = prefix + src;
                script.async = false;
                document.head.appendChild(script);
            });
        })();

        // 2. Chip selection logic
        document.querySelectorAll('.chip').forEach(chip => {
            chip.addEventListener('click', function() {
                if (window.isSpinning) return;
                document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                const val = this.getAttribute('data-value');
                if (val === 'allin') {
                    const curBal = parseInt(document.getElementById('balance-val').innerText.replace(/\D/g, '')) || 0;
                    document.getElementById('bet-amount').value = curBal;
                } else {
                    document.getElementById('bet-amount').value = val;
                }
            });
        });

        // 3. Generate numbers on the wheel
        const wheelOrder = [0, 32, 15, 19, 4, 21, 2, 25, 17, 34, 6, 27, 13, 36, 11, 30, 8, 23, 10, 5, 24, 16, 33, 1, 20, 14, 31, 9, 22, 18, 29, 7, 28, 12, 35, 3, 26];
        const wheelInner = document.getElementById('wheel-inner');
        const sliceAngle = 360 / 37;
        
        wheelOrder.forEach((num, index) => {
            const numDiv = document.createElement('div');
            numDiv.className = 'wheel-number';
            numDiv.textContent = num;
            numDiv.style.transform = `rotate(${index * sliceAngle + sliceAngle / 2}deg)`;
            wheelInner.appendChild(numDiv);
        });

        window.currentBets = [];
        window.isSpinning = false;
        let totalRotation = 0;

        // 4. Click Cell on Board
        document.querySelectorAll('.cell').forEach(cell => {
            cell.addEventListener('click', function () {
                if (window.isSpinning) return;
                const type = this.dataset.type;
                const val = this.dataset.val;
                const amt = parseInt(document.getElementById('bet-amount').value);

                if (amt <= 0 || isNaN(amt)) { return; }

                this.classList.add('active');
                window.currentBets.push({ type, value: val, amount: amt });
                updateStatus();
            });
        });

        document.getElementById('btn-clear').addEventListener('click', () => {
            if (window.isSpinning) return;
            window.currentBets = [];
            document.querySelectorAll('.cell').forEach(c => {
                c.classList.remove('active');
                c.classList.remove('winner');
            });
            updateStatus();
        });

        function updateStatus() {
            const total = window.currentBets.reduce((sum, b) => sum + b.amount, 0);
            document.getElementById('status-marquee').textContent = window.currentBets.length > 0
                ? `ACTIVE BETS: ${window.currentBets.length} | TOTAL EXPOSURE: ${total.toLocaleString()} gtlm`
                : 'WAITING FOR BETS... CHOOSE NUMBERS OR AREAS ON THE BOARD.';
        }

        // 5. Spin and Win/Loss Result Handler (Tương tự Game id = 1)
        document.getElementById('btn-spin').addEventListener('click', async function () {
            if (window.isSpinning) return;
            if (window.currentBets.length === 0) {
                console.warn('Vui lòng đặt cược trước khi quay!');
                return;
            }

            const btn = this;
            window.isSpinning = true;
            btn.disabled = true;
            document.getElementById('btn-clear').disabled = true;
            document.getElementById('status-marquee').textContent = 'BALL IS SPINNING. NO MORE BETS!';

            const betsCopy = [...window.currentBets];

            try {
                const fd = new FormData();
                fd.append('bets', JSON.stringify(betsCopy));

                const res = await fetch('?action=spin_pro', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.success) {
                    const wheel = document.getElementById('wheel-inner');
                    const idx = wheelOrder.indexOf(data.number);

                    // Logic xoay mượt mà chân thực
                    totalRotation += (10 * 360) + (idx * (360 / 37));
                    wheel.style.transform = `rotate(-${totalRotation}deg)`;

                    setTimeout(() => {
                        const resOrb = document.getElementById('result-orb');
                        resOrb.textContent = data.number;
                        resOrb.style.color = 'white';
                        resOrb.style.backgroundColor = (data.color === 'red' ? '#e74c3c' : (data.color === 'black' ? '#2c3e50' : '#27ae60'));

                        document.getElementById('balance-val').textContent = data.newMoney;
                        document.getElementById('status-marquee').textContent = data.message;

                        const winningNumber = data.number;
                        const winningColor = data.color;

                        // ── HIỆU ỨNG THẮNG / THUA GIỐNG GAME ID = 1 ──
                        if (data.totalWin > 0) {
                            // 1. Kích hoạt hiệu ứng GameEffects chuẩn (không có popup modal che màn hình)
                            if (window.GameEffects && typeof window.GameEffects.showWin === 'function') {
                                window.GameEffects.showWin(data.totalWin);
                            } else if (typeof confetti === 'function') {
                                confetti({ particleCount: 200, spread: 100, origin: { y: 0.6 }, colors: ['#ffd700', '#ffffff', '#27ae60'] });
                            }

                            // 2. Đánh dấu các ô thắng (Winner bounce + glow) và spawn số GTLM floating +xxx
                            document.querySelectorAll('.cell.active').forEach(cell => {
                                const cType = cell.dataset.type;
                                const cVal = cell.dataset.val;
                                let isWin = false;
                                let multiplier = 0;

                                if (cType === 'straight' && parseInt(cVal) === winningNumber) { isWin = true; multiplier = 36; }
                                else if (cType === 'red' && winningColor === 'red') { isWin = true; multiplier = 2; }
                                else if (cType === 'black' && winningColor === 'black') { isWin = true; multiplier = 2; }
                                else if (cType === 'even' && winningNumber !== 0 && winningNumber % 2 === 0) { isWin = true; multiplier = 2; }
                                else if (cType === 'odd' && winningNumber !== 0 && winningNumber % 2 !== 0) { isWin = true; multiplier = 2; }
                                else if (cType === 'low' && winningNumber >= 1 && winningNumber <= 18) { isWin = true; multiplier = 2; }
                                else if (cType === 'high' && winningNumber >= 19 && winningNumber <= 36) { isWin = true; multiplier = 2; }
                                else if (cType === 'dozen') {
                                    if (cVal == '1' && winningNumber >= 1 && winningNumber <= 12) { isWin = true; multiplier = 3; }
                                    if (cVal == '2' && winningNumber >= 13 && winningNumber <= 24) { isWin = true; multiplier = 3; }
                                    if (cVal == '3' && winningNumber >= 25 && winningNumber <= 36) { isWin = true; multiplier = 3; }
                                }
                                else if (cType === 'column' && winningNumber !== 0 && (winningNumber - parseInt(cVal)) % 3 === 0) {
                                    isWin = true; multiplier = 3;
                                }

                                const cellBets = betsCopy.filter(b => b.type === cType && String(b.value) === String(cVal));
                                const cellBetAmt = cellBets.reduce((sum, b) => sum + b.amount, 0);

                                if (isWin) {
                                    cell.classList.add('winner');
                                    const winVal = cellBetAmt * multiplier;
                                    const float = $(`<div class="floating-win">+${winVal.toLocaleString('vi-VN')}</div>`).appendTo(cell);
                                    if (window.gsap) {
                                        gsap.to(float, { y: -80, opacity: 0, duration: 2.2, ease: "power2.out", onComplete: () => float.remove() });
                                    } else {
                                        setTimeout(() => float.remove(), 2200);
                                    }
                                } else {
                                    const float = $(`<div class="floating-win" style="color: #ff4757;">-${cellBetAmt.toLocaleString('vi-VN')}</div>`).appendTo(cell);
                                    if (window.gsap) {
                                        gsap.to(float, { y: -80, opacity: 0, duration: 2.2, ease: "power2.out", onComplete: () => float.remove() });
                                    } else {
                                        setTimeout(() => float.remove(), 2200);
                                    }
                                }
                            });

                        } else {
                            // Thua: Rung màn hình bàn game (lose-shake) và flash đỏ giống game id = 1
                            $('#game-wrapper').addClass('lose-shake');
                            if (window.GameEffects && typeof window.GameEffects.showLoss === 'function') {
                                window.GameEffects.showLoss(data.totalBet);
                            }
                            setTimeout(() => $('#game-wrapper').removeClass('lose-shake'), 600);

                            // Spawn số GTLM thua màu đỏ (-xxx) bay lên từ các ô cược
                            document.querySelectorAll('.cell.active').forEach(cell => {
                                const cType = cell.dataset.type;
                                const cVal = cell.dataset.val;
                                const cellBets = betsCopy.filter(b => b.type === cType && String(b.value) === String(cVal));
                                const cellBetAmt = cellBets.reduce((sum, b) => sum + b.amount, 0);
                                const float = $(`<div class="floating-win" style="color: #ff4757;">-${cellBetAmt.toLocaleString('vi-VN')}</div>`).appendTo(cell);
                                if (window.gsap) {
                                    gsap.to(float, { y: -80, opacity: 0, duration: 2.2, ease: "power2.out", onComplete: () => float.remove() });
                                } else {
                                    setTimeout(() => float.remove(), 2200);
                                }
                            });
                        }

                        // Sau khi hiển thị kết quả 3 giây, dọn bàn chuẩn bị ván tiếp theo
                        setTimeout(() => {
                            window.currentBets = [];
                            document.querySelectorAll('.cell').forEach(c => {
                                c.classList.remove('active');
                                c.classList.remove('winner');
                            });
                            btn.disabled = false;
                            document.getElementById('btn-clear').disabled = false;
                            window.isSpinning = false;
                            updateStatus();
                        }, 3000);

                    }, 6500);

                } else {
                    console.error(data.message);
                    btn.disabled = false;
                    document.getElementById('btn-clear').disabled = false;
                    window.isSpinning = false;
                    updateStatus();
                }
            } catch (e) {
                console.error(e);
                btn.disabled = false;
                document.getElementById('btn-clear').disabled = false;
                window.isSpinning = false;
            }
        });
    </script>

    <!-- 🤖 THUẬT TOÁN BOT STREAMER 46 CHUYÊN BIỆT -->
    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="bots/bot_46.js"></script>

</body>
</html>
