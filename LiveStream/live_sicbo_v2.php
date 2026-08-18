<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_sicbo_v2', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require '../db_connect.php';
require_once '../load_theme.php';

$userId = $botUserId;
// Auto-create history table
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$stmt->close();
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    if ($action === 'roll') {
        $bets = json_decode($_POST['bets'], true); // Array of {type: 'small', amount: 1000}
        $totalBet = 0;
        foreach ($bets as $b)
            $totalBet += (int) $b['amount'];
        if ($totalBet <= 0 || $totalBet > $money) {
            echo json_encode(['success' => false, 'message' => 'Lượng Chiến không hợp lệ!']);
            exit;
        }
        $dice = [rand(1, 6), rand(1, 6), rand(1, 6)];
        $sum = array_sum($dice);
        sort($dice);
        $diceStr = implode(',', $dice);
        $counts = array_count_values($dice);
        $isTriple = (count($counts) === 1);
        $anyDouble = (count($counts) < 3);
        $winAmount = -$totalBet;
        $winLog = [];
        $totalRevenue = 0;
        foreach ($bets as $b) {
            $type = $b['type'];
            $amt = (int) $b['amount'];
            $won = false;
            $pay = 0;
            if ($type === 'small') {
                if ($sum >= 4 && $sum <= 10 && !$isTriple) {
                    $won = true;
                    $pay = 1;
                }
            } elseif ($type === 'big') {
                if ($sum >= 11 && $sum <= 17 && !$isTriple) {
                    $won = true;
                    $pay = 1;
                }
            } elseif ($type === 'odd') {
                if ($sum % 2 != 0 && !$isTriple) {
                    $won = true;
                    $pay = 1;
                }
            } elseif ($type === 'even') {
                if ($sum % 2 == 0 && !$isTriple) {
                    $won = true;
                    $pay = 1;
                }
            } elseif ($type === 'any_triple') {
                if ($isTriple) {
                    $won = true;
                    $pay = 30;
                }
            } elseif (strpos($type, 'triple_') === 0) {
                $v = (int) str_replace('triple_', '', $type);
                if ($isTriple && $dice[0] == $v) {
                    $won = true;
                    $pay = 180;
                }
            } elseif (strpos($type, 'double_') === 0) {
                $v = (int) str_replace('double_', '', $type);
                if ($counts[$v] >= 2) {
                    $won = true;
                    $pay = 10;
                }
            } elseif (strpos($type, 'total_') === 0) {
                $v = (int) str_replace('total_', '', $type);
                if ($sum == $v) {
                    $won = true;
                    $pArr = [4 => 60, 5 => 30, 6 => 18, 7 => 12, 8 => 8, 9 => 7, 10 => 6, 11 => 6, 12 => 7, 13 => 8, 14 => 12, 15 => 18, 16 => 30, 17 => 60];
                    $pay = $pArr[$v] ?? 0;
                }
            } elseif (strpos($type, 'single_') === 0) {
                $v = (int) str_replace('single_', '', $type);
                if (isset($counts[$v])) {
                    $won = true;
                    $pay = $counts[$v];
                } // 1x, 2x, 3x
            }
            if ($won) {
                $revenue = $amt * ($pay + 1);
                $winAmount += $revenue;
                $totalRevenue += $revenue;
                $typeNames = [
                    'small' => 'Xanh',
                    'big' => 'Đỏ',
                    'odd' => 'Lẻ',
                    'even' => 'Chẵn',
                    'any_triple' => 'Bất kỳ bộ ba'
                ];
                $displayName = $typeNames[$type] ?? $type;
                if (strpos($type, 'single_') === 0) {
                    $displayName = 'Số ' . str_replace('single_', '', $type);
                } elseif (strpos($type, 'total_') === 0) {
                    $displayName = 'Tổng ' . str_replace('total_', '', $type);
                } elseif (strpos($type, 'double_') === 0) {
                    $displayName = 'Cặp ' . str_replace('double_', '', $type);
                } elseif (strpos($type, 'triple_') === 0) {
                    $displayName = 'Bộ ba ' . str_replace('triple_', '', $type);
                }
                $winLog[] = "$displayName (Thắng x$pay): +" . number_format($revenue, 0, ',', '.');
            }
        }
        $newMoney = $money + $winAmount;
        $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $stmt->bind_param("di", $newMoney, $userId);
        $stmt->execute();
        $stmt->close();
        // History
        $his = $conn->prepare("INSERT INTO history_sicbo (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $his->bind_param("idss", $userId, $totalBet, $diceStr, $winAmount);
        $his->execute();
        $his->close();
        echo json_encode([
            'success' => true,
            'dice' => $dice,
            'sum' => $sum,
            'winAmount' => $winAmount,
            'totalRevenue' => $totalRevenue,
            'winLog' => $winLog,
            'money' => number_format($newMoney, 0, ',', '.'),
            'rawMoney' => $newMoney
        ]);
        exit;
    } elseif ($action === 'get_history') {
        $stmt = $conn->prepare("SELECT Bet, Result, WinAmount, Time FROM history_sicbo WHERE Iduser = ? ORDER BY Time DESC LIMIT 10");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'history' => $res]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xanh Đỏ Đối Kháng - Đỉnh Cao Xúc Xắc</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <style>
        :root {
            --primary: #ef4444;
            --secondary: #6ee7b7;
            --accent: #fbbf24;
            --glass: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Exo 2', system-ui, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
        }
        .main-container {
            position: relative;
            z-index: 1;
            width: 95%;
            max-width: 1100px;
            margin: 2rem auto;
            text-align: center;
        }
        .game-title {
            font-size: clamp(2.5rem, 8vw, 4rem);
            font-weight: 900;
            color: var(--primary);
            text-shadow: 0 0 30px rgba(239, 68, 68, 0.4);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 12px;
        }
        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 3rem;
            padding: clamp(1.5rem, 5vw, 3rem);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            margin-bottom: 2rem;
        }
        .balance-pill {
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid var(--accent);
            padding: 0.8rem 2rem;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 2rem;
            color: var(--accent);
            font-weight: 700;
        }
        /* 3D Dice Styles */
        .dice-area {
            display: flex;
            justify-content: center;
            gap: 4rem;
            margin: 4rem 0;
            perspective: 1000px;
            min-height: 120px;
        }
        .dice-container {
            width: 60px;
            height: 60px;
            position: relative;
            transform-style: preserve-3d;
            transition: transform 1.5s cubic-bezier(0.17, 0.67, 0.83, 0.67);
        }
        .dice-face {
            position: absolute;
            width: 60px;
            height: 60px;
            background-color: #ffffff !important;
            color: #000000 !important;
            border: 2px solid #ddd;
            border-radius: 10px;
            display: grid !important;
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(3, 1fr);
            padding: 6px;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.1);
        }
        .dot {
            background-color: currentColor !important;
            border-radius: 50%;
            width: 10px;
            height: 10px;
            margin: auto;
        }
        .face-1 { transform: rotateY(0deg) translateZ(30px); }
        .face-2 { transform: rotateY(90deg) translateZ(30px); }
        .face-3 { transform: rotateX(90deg) translateZ(30px); }
        .face-4 { transform: rotateX(-90deg) translateZ(30px); }
        .face-5 { transform: rotateY(-90deg) translateZ(30px); }
        .face-6 { transform: rotateY(180deg) translateZ(30px); }
        .show-1 { transform: rotateY(0deg) rotateX(0deg); }
        .show-2 { transform: rotateY(-90deg) rotateX(0deg); }
        .show-3 { transform: rotateY(0deg) rotateX(-90deg); }
        .show-4 { transform: rotateY(0deg) rotateX(90deg); }
        .show-5 { transform: rotateY(90deg) rotateX(0deg); }
        .show-6 { transform: rotateY(180deg) rotateX(0deg); }
        .chip-selector {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin-bottom: 2.5rem;
        }
        .btn-quick-bet {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: url('../img/tay.png'), pointer !important;
            font-weight: 600;
            transition: 0.3s;
        }
        .btn-quick-bet:hover, .btn-quick-bet.sel {
            background: var(--accent);
            color: #000;
            border-color: var(--accent);
        }
        .bet-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin-bottom: 3rem;
        }
        .bet-item {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
            border-radius: 1.2rem;
            padding: 1.2rem 0.5rem;
            cursor: url('../img/tay.png'), pointer !important;
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }
        .bet-item:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.3);
        }
        .bet-item.active {
            border-color: var(--accent);
            background: rgba(251, 191, 36, 0.15);
            box-shadow: inset 0 0 20px rgba(251, 191, 36, 0.1);
        }
        .bet-item .label {
            font-size: 0.9rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .bet-item .odds {
            font-size: 0.75rem;
            color: var(--accent);
            font-weight: 700;
            opacity: 0.8;
        }
        .bet-amount-badge {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: var(--accent);
            color: #000;
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 0.7rem;
            font-weight: 900;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }
        .btn-roll {
            background: linear-gradient(135deg, var(--primary) 0%, #7f1d1d 100%);
            border: none;
            padding: 1.2rem 5rem;
            border-radius: 50px;
            color: #fff;
            font-size: 1.5rem;
            font-weight: 900;
            cursor: url('../img/tay.png'), pointer !important;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 4px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.4);
        }
        .btn-roll:hover:not(:disabled) {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(239, 68, 68, 0.6);
        }
        .btn-roll:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .history-section {
            display: none;
            background: var(--glass);
            border-radius: 2.5rem;
            padding: 2.5rem;
            border: 1px solid var(--glass-border);
            margin-bottom: 5rem;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .history-table th {
            color: rgba(255, 255, 255, 0.5);
            text-transform: uppercase;
            font-size: 0.8rem;
            padding: 1.2rem;
            border-bottom: 2px solid var(--glass-border);
        }
        .history-table td {
            padding: 1.2rem;
            border-bottom: 1px solid var(--glass-border);
            font-weight: 600;
        }
        @keyframes shakeContainer {
            0% { transform: translate(1px, 1px) rotate(0deg); }
            10% { transform: translate(-1px, -2px) rotate(-1deg); }
            20% { transform: translate(-3px, 0px) rotate(1deg); }
            30% { transform: translate(3px, 2px) rotate(0deg); }
            40% { transform: translate(1px, -1px) rotate(1deg); }
            50% { transform: translate(-1px, 2px) rotate(-1deg); }
            60% { transform: translate(-3px, 1px) rotate(0deg); }
            70% { transform: translate(3px, 1px) rotate(-1deg); }
            80% { transform: translate(-1px, -1px) rotate(1deg); }
            90% { transform: translate(1px, 2px) rotate(0deg); }
            100% { transform: translate(1px, -2px) rotate(-1deg); }
        }
        .shaking {
            animation: shakeContainer 0.1s infinite;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <h1 class="game-title">XANH ĐỎ ĐỐI KHÁNG</h1>
        <div class="balance-pill">💰 Số Gtlm: <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span> gtlm</div>
        <div class="info-guide" style="max-width: 800px; margin: 0 auto 2rem; background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 20px; border-left: 5px solid var(--accent); text-align: left; border-right: 1px solid rgba(255,255,255,0.1); border-top: 1px solid rgba(255,255,255,0.1); box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            💡 <b>HƯỚNG DẪN NHANH:</b> Thả thính vào các ô Chiến. Bạn có thể Chiến nhiều ô cùng lúc.<br>
            - <b>Xanh:</b> Tổng điểm 4-10. Tỷ lệ 1 ăn 1.<br>
            - <b>Đỏ:</b> Tổng điểm 11-17. Tỷ lệ 1 ăn 1.<br>
            - <b>Bộ ba:</b> 3 xúc xắc giống nhau (không tính Thiên thần/ Ác quỷ). Húp x30!
            <button onclick="showFullRules()" style="background: var(--accent); color: #000; border: none; padding: 8px 20px; border-radius: 50px; cursor: pointer; margin-top: 10px; font-weight: 800; text-transform: uppercase; font-size: 0.7rem; box-shadow: 0 5px 15px rgba(251, 191, 36, 0.4);">📜 Xem chi tiết luật</button>
        </div>
        <div class="glass-card">
            <div class="trend-bar" style="display: flex; gap: 12px; justify-content: center; margin-bottom: 2.5rem; padding: 12px; background: rgba(0,0,0,0.3); border-radius: 50px; border: 1px solid rgba(255,255,255,0.1);">
                <span style="font-size: 0.75rem; opacity: 0.5; align-self: center; margin-right: 10px; font-weight: 800; letter-spacing: 1px;">TRENDING:</span>
                <div id="trend-content" style="display: flex; gap: 8px;">
                    <!-- Trends will appear here -->
                </div>
            </div>
            <div class="dice-area" id="dice-wrapper">
                <!-- Dice elements -->
                <div class="dice-container" id="dice-1">
                    <div class="dice-face face-1"><div class="dot" style="grid-area: 2/2;"></div></div>
                    <div class="dice-face face-2"><div class="dot" style="grid-area: 1/1;"></div><div class="dot" style="grid-area: 3/3;"></div></div>
                    <div class="dice-face face-3"><div class="dot" style="grid-area: 1/1;"></div><div class="dot" style="grid-area: 2/2;"></div><div class="dot" style="grid-area: 3/3;"></div></div>
                    <div class="dice-face face-4"><div class="dot" style="grid-area: 1/1;"></div><div class="dot" style="grid-area: 1/3;"></div><div class="dot" style="grid-area: 3/1;"></div><div class="dot" style="grid-area: 3/3;"></div></div>
                    <div class="dice-face face-5"><div class="dot" style="grid-area: 1/1;"></div><div class="dot" style="grid-area: 1/3;"></div><div class="dot" style="grid-area: 2/2;"></div><div class="dot" style="grid-area: 3/1;"></div><div class="dot" style="grid-area: 3/3;"></div></div>
                    <div class="dice-face face-6"><div class="dot" style="grid-area: 1/1;"></div><div class="dot" style="grid-area: 1/2;"></div><div class="dot" style="grid-area: 1/3;"></div><div class="dot" style="grid-area: 3/1;"></div><div class="dot" style="grid-area: 3/2;"></div><div class="dot" style="grid-area: 3/3;"></div></div>
                </div>
                <div class="dice-container" id="dice-2">
                    <div class="dice-face face-1"><div class="dot" style="grid-area: 2/2;"></div></div>
                    <div class="dice-face face-2"><div class="dot" style="grid-area: 1/1;"></div><div class="dot" style="grid-area: 3/3;"></div></div>
                    <div class="dice-face face-3"><div class="dot" style="grid-area: 1/1;"></div><div class="dot" style="grid-area: 2/2;"></div><div class="dot" style="grid-area: 3/3;"></div></div>
                    <div class="dice-face face-4"><div class="dot" style="grid-area: 1/1;"></div><div class="dot" style="grid-area: 1/3;"></div><div class="dot" style="grid-area: 3/1;"></div><div class="dot" style="grid-area: 3/3;"></div></div>
                    <div class="dice-face face-5"><div class="dot" style="grid-area: 1/1;"></div><div class="dot" style="grid-area: 1/3;"></div><div class="dot" style="grid-area: 2/2;"></div><div class="dot" style="grid-area: 3/1;"></div><div class="dot" style="grid-area: 3/3;"></div></div>
                    <div class="dice-face face-6"><div class="dot" style="grid-area: 1/1;"></div><div class="dot" style="grid-area: 1/2;"></div><div class="dot" style="grid-area: 1/3;"></div><div class="dot" style="grid-area: 3/1;"></div><div class="dot" style="grid-area: 3/2;"></div><div class="dot" style="grid-area: 3/3;"></div></div>
                </div>
                <div class="dice-container" id="dice-3">
                    <div class="dice-face face-1"><div class="dot" style="grid-area: 2/2;"></div></div>
                    <div class="dice-face face-2"><div class="dot" style="grid-area: 1/1;"></div><div class="dot" style="grid-area: 3/3;"></div></div>
                    <div class="dice-face face-3"><div class="dot" style="grid-area: 1/1;"></div><div class="dot" style="grid-area: 2/2;"></div><div class="dot" style="grid-area: 3/3;"></div></div>
                    <div class="dice-face face-4"><div class="dot" style="grid-area: 1/1;"></div><div class="dot" style="grid-area: 1/3;"></div><div class="dot" style="grid-area: 3/1;"></div><div class="dot" style="grid-area: 3/3;"></div></div>
                    <div class="dice-face face-5"><div class="dot" style="grid-area: 1/1;"></div><div class="dot" style="grid-area: 1/3;"></div><div class="dot" style="grid-area: 2/2;"></div><div class="dot" style="grid-area: 3/1;"></div><div class="dot" style="grid-area: 3/3;"></div></div>
                    <div class="dice-face face-6"><div class="dot" style="grid-area: 1/1;"></div><div class="dot" style="grid-area: 1/2;"></div><div class="dot" style="grid-area: 1/3;"></div><div class="dot" style="grid-area: 3/1;"></div><div class="dot" style="grid-area: 3/2;"></div><div class="dot" style="grid-area: 3/3;"></div></div>
                </div>
            </div>
            <div class="chip-selector" style="display: flex; justify-content: center; flex-wrap: wrap; gap: 0.8rem; margin-bottom: 2.5rem;">
                <button type="button" class="btn-quick-bet sel" data-val="10000">10K</button>
                <button type="button" class="btn-quick-bet" data-val="50000">50K</button>
                <button type="button" class="btn-quick-bet" data-val="100000">100K</button>
                <button type="button" class="btn-quick-bet" data-val="500000">500K</button>
                <button type="button" class="btn-quick-bet" data-val="1000000">1M</button>
                <button type="button" class="btn-quick-bet" data-val="5000000">5M</button>
                <button type="button" class="btn-quick-bet" data-val="ALL">ALL IN</button>
                <button type="button" onclick="clearBets()" style="background: rgba(255,255,255,0.1); color: #fff; border: 1px solid var(--glass-border); border-radius: 8px; cursor: pointer; padding: 8px 15px; font-weight: 800; font-size: 0.75rem; transition: 0.3s; text-transform: uppercase;">DỌN BÀN</button>
            </div>
            <div class="bet-grid">
                <!-- Main Bets -->
                <div class="bet-item" data-type="small"><div class="label">🔵 Xanh (4-10)</div><div class="odds">1 ĂN 1</div><div class="bet-amount-badge" style="display:none">0</div></div>
                <div class="bet-item" data-type="odd"><div class="label">⚖️ LẺ</div><div class="odds">1 ĂN 1</div><div class="bet-amount-badge" style="display:none">0</div></div>
                <div class="bet-item" data-type="any_triple" style="grid-column: span 2; background: rgba(251, 191, 36, 0.2); border: 2px solid var(--accent);"><div class="label">✨ BẤT KỲ BỘ BA ✨</div><div class="odds">1 ĂN 30</div><div class="bet-amount-badge" style="display:none">0</div></div>
                <div class="bet-item" data-type="even"><div class="label">⚖️ CHẴN</div><div class="odds">1 ĂN 1</div><div class="bet-amount-badge" style="display:none">0</div></div>
                <div class="bet-item" data-type="big"><div class="label">🔴 Đỏ (11-17)</div><div class="odds">1 ĂN 1</div><div class="bet-amount-badge" style="display:none">0</div></div>
                <!-- Single Numbers -->
                <div style="grid-column: 1 / -1; margin: 1.5rem 0; font-size: 0.7rem; font-weight: 800; letter-spacing: 2px; color: rgba(255,255,255,0.4); border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1.5rem; text-transform: uppercase;">🎲 CHIẾN SỐ DUY NHẤT (x1, x2, x3)</div>
                <div class="bet-item" data-type="single_1"><div class="label">❶</div><div class="bet-amount-badge" style="display:none">0</div></div>
                <div class="bet-item" data-type="single_2"><div class="label">❷</div><div class="bet-amount-badge" style="display:none">0</div></div>
                <div class="bet-item" data-type="single_3"><div class="label">❸</div><div class="bet-amount-badge" style="display:none">0</div></div>
                <div class="bet-item" data-type="single_4"><div class="label">❹</div><div class="bet-amount-badge" style="display:none">0</div></div>
                <div class="bet-item" data-type="single_5"><div class="label">❺</div><div class="bet-amount-badge" style="display:none">0</div></div>
                <div class="bet-item" data-type="single_6"><div class="label">❻</div><div class="bet-amount-badge" style="display:none">0</div></div>
                <!-- Totals -->
                <div style="grid-column: 1 / -1; margin: 1.5rem 0; font-size: 0.7rem; font-weight: 800; letter-spacing: 2px; color: rgba(255,255,255,0.4); border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1.5rem; text-transform: uppercase;">🎯 CHIẾN TỔNG ĐIỂM</div>
                <div class="bet-item" data-type="total_4"><div class="label">TỔNG 4</div><div class="odds">1:60</div><div class="bet-amount-badge" style="display:none">0</div></div>
                <div class="bet-item" data-type="total_9"><div class="label">TỔNG 9</div><div class="odds">1:7</div><div class="bet-amount-badge" style="display:none">0</div></div>
                <div class="bet-item" data-type="total_10"><div class="label">TỔNG 10</div><div class="odds">1:6</div><div class="bet-amount-badge" style="display:none">0</div></div>
                <div class="bet-item" data-type="total_11"><div class="label">TỔNG 11</div><div class="odds">1:6</div><div class="bet-amount-badge" style="display:none">0</div></div>
                <div class="bet-item" data-type="total_12"><div class="label">TỔNG 12</div><div class="odds">1:7</div><div class="bet-amount-badge" style="display:none">0</div></div>
                <div class="bet-item" data-type="total_17"><div class="label">TỔNG 17</div><div class="odds">1:60</div><div class="bet-amount-badge" style="display:none">0</div></div>
            </div>
            <button id="roll-btn" class="btn-roll">LẮC XÚC XẮC</button>
        </div>
        <div class="history-section">
            <h2 style="font-size: 1.2rem; letter-spacing: 2px; margin-bottom: 2rem; color: var(--accent);">LỊCH SỬ GẦN ĐÂY</h2>
            <div style="overflow-x: auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>Lượng Chiến</th>
                            <th>Kết quả</th>
                            <th>Húp/Về Cõi</th>
                        </tr>
                    </thead>
                    <tbody id="history-body"></tbody>
                </table>
            </div>
        </div>
        <div style="margin-top: 3rem; margin-bottom: 5rem;"><a href="../index.php" style="color: #fff; text-decoration: none; font-weight: 700; border: 1px solid rgba(255,255,255,0.2); padding: 1rem 3rem; border-radius: 50px; transition: 0.3s; background: rgba(255,255,255,0.05);">🏠 QUAY LẠI SẢNH</a></div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/confetti.browser.min.js"></script>
    <style>
        .trend-bubble { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 0.7rem; color: #fff; }
        .t-big { background: var(--primary); }
        .t-small { background: var(--secondary); color: #000; }
    </style>
    <script>
        function showFullRules() {
            Swal.fire({
                title: 'LUẬT CHƠI SIC BO',
                html: `<div style="text-align: left; font-size: 0.85rem;">
                    <b>1. Đỏ/Xanh:</b> Thắng x1. Đỏ (11-17), Xanh (4-10). Thua nếu ra Bộ Ba (3 xúc xắc giống nhau).<br>
                    <b>2. Chẵn/Lẻ:</b> Thắng x1. Thua nếu ra Bộ Ba.<br>
                    <b>3. Cược Đơn:</b> Đoán số xuất hiện trên xúc xắc. 1 con: x1, 2 con: x2, 3 con: x3.<br>
                    <b>4. Bộ Ba:</b> Cược 3 xúc xắc giống hệt nhau. Thắng x30.<br>
                    <b>5. Tổng Điểm:</b> Cược chính xác tổng 3 xúc xắc. Tỷ lệ từ x6 đến x60.
                </div>`,
                icon: 'info'
            });
        }
        function updateTrends(history) {
            let html = '';
            history.slice(0, 10).reverse().forEach(h => {
                let sum = h.Result.split(',').reduce((a, b) => parseInt(a) + parseInt(b), 0);
                let isBig = sum >= 11;
                html += `<div class="trend-bubble ${isBig ? 't-big' : 't-small'}" title="Tổng: ${sum}">${isBig ? 'Đ' : 'X'}</div>`;
            });
            $('#trend-content').html(html);
        }
        let currentChip = 10000;
        let bets = {};
        let isRolling = false;
        $('.btn-quick-bet').click(function() {
            if ($(this).data('val') === undefined) return;
            $('.btn-quick-bet').removeClass('sel');
            $(this).addClass('sel');
            currentChip = $(this).data('val');
        });
        $('.bet-item').click(function() {
            if (isRolling) return;
            let type = $(this).data('type');
            let moneyText = $('#balance-val').text().replace(/\./g, '');
            let maxMoney = parseInt(moneyText);
            let currentTotalBet = Object.values(bets).reduce((a,b) => a+b, 0);
            let addAmount = currentChip;
            if (currentChip === 'ALL') {
                addAmount = maxMoney - currentTotalBet;
            } else {
                addAmount = parseInt(currentChip);
            }
            if (addAmount <= 0 || (currentTotalBet + addAmount > maxMoney)) {
                Swal.fire('Lỗi', 'Không đủ GTLM!', 'error');
                return;
            }
            bets[type] = (bets[type] || 0) + addAmount;
            $(this).addClass('active');
            let badge = $(this).find('.bet-amount-badge');
            badge.text(new Intl.NumberFormat().format(bets[type])).show();
        });
        function clearBets() {
            if (isRolling) return;
            bets = {};
            $('.bet-item').removeClass('active');
            $('.bet-amount-badge').hide().text('0');
        }
        $('#roll-btn').click(function() {
            if (isRolling || Object.keys(bets).length === 0) {
                if (Object.keys(bets).length === 0) Swal.fire('Lỗi', 'Vui lòng Chiến trước khi lắc!', 'error');
                return;
            }
            isRolling = true;
            $('#roll-btn').prop('disabled', true);
            $('#dice-wrapper').addClass('shaking');
            let betArray = [];
            for (let type in bets) {
                betArray.push({ type: type, amount: bets[type] });
            }
            $.post('?action=roll', { bets: JSON.stringify(betArray) }, function(data) {
                if (!data.success) {
                    Swal.fire('Lỗi', data.message, 'error');
                    isRolling = false;
                    $('#roll-btn').prop('disabled', false);
                    $('#dice-wrapper').removeClass('shaking');
                    return;
                }
                setTimeout(() => {
                    $('#dice-wrapper').removeClass('shaking');
                    data.dice.forEach((val, i) => {
                        const diceEl = $(`#dice-${i+1}`);
                        diceEl.removeClass('show-1 show-2 show-3 show-4 show-5 show-6');
                        void diceEl[0].offsetWidth; 
                        diceEl.addClass(`show-${val}`);
                    });
                    setTimeout(() => {
                        isRolling = false;
                        $('#roll-btn').prop('disabled', false);
                        $('#balance-val').text(data.money);
                        if (data.winAmount > 0) {
                            try {
                                if (typeof confetti !== 'undefined') {
                                    confetti({ particleCount: 100, spread: 70, origin: { y: 0.6 } });
                                }
                            } catch (e) { console.error(e); }
                            Swal.fire({
                                title: 'Húp Lớn!',
                                html: `Tổng thu về: <b style="color:#10b981">${Number(data.totalRevenue).toLocaleString()}</b> GTLM<br>Lãi ròng: <b style="color:#fbbf24">+${Number(data.winAmount).toLocaleString()}</b> GTLM<hr style="border-color:rgba(255,255,255,0.1); margin:10px 0;"><small style="text-align:left; display:block; max-height:150px; overflow-y:auto; line-height: 1.6;">${(data.winLog || []).join('<br>')}</small>`,
                                icon: 'success'
                            });
                        } else {
                            Swal.fire('Về Cõi!', `Tiếc quá, bạn đã bay màu số GTLM này!`, 'error');
                        }
                        loadHistory();
                        clearBets();
                    }, 1500);
                }, 1000);
            });
        });
        function loadHistory() {
            $.get('sicbo_v2.php?action=get_history', function(data) {
                if (data.success) {
                    updateTrends(data.history);
                    let html = '';
                    data.history.forEach(h => {
                        let winClass = h.WinAmount > 0 ? 'text-green-400' : 'text-red-400';
                        let winText = h.WinAmount > 0 ? `+${parseFloat(h.WinAmount).toLocaleString()}` : parseFloat(h.WinAmount).toLocaleString();
                        html += `<tr><td>${h.Time}</td><td>${parseFloat(h.Bet).toLocaleString()}</td><td style="letter-spacing: 5px;">${h.Result.split(',').join(' ')}</td><td class="${winClass}">${winText}</td></tr>`;
                    });
                    $('#history-body').html(html);
                }
            });
        }
        loadHistory();
    </script>
    <canvas id="threejs-background"></canvas>
    <script>
        (function () {
            window.themeConfig = {
                particleCount: 400,
                particleSize: 0.05,
                particleColor: '#ffffff',
                particleOpacity: 0.4,
                shapeCount: 5,
                shapeColors: ["#ef4444", "#fbbf24"],
                shapeOpacity: 0.2
            };
            const prefix = '../';
            const scripts = ['threejs-background.js'];
            scripts.forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src;
                s.async = false;
                document.head.appendChild(s);
            });
        })();
    </script>

<!-- AUTO-GENERATED BOT SCRIPT -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script>
    if (typeof BotVirtualCursor !== "undefined") {
        BotVirtualCursor.init("Bot Streamer");
        setInterval(() => {
            const allBtns = Array.from(document.querySelectorAll("button, .btn-bet, .chip, .spin-btn, #btnSpin, .bet-button, .card, .btn-primary, .btn-success, input[type='button'], input[type='submit']"));
            const btns = allBtns.filter(b => {
                if(b.offsetParent === null || b.disabled) return false;
                const txt = (b.innerText || b.value || "").toLowerCase();
                const cls = (b.className || "").toLowerCase();
                const id = (b.id || "").toLowerCase();
                
                if(txt.includes("hướng dẫn") || txt.includes("trang chủ") || txt.includes("nạp") || txt.includes("rút") || txt.includes("lịch sử") || txt.includes("quay lại") || txt.includes("thoát")) return false;
                if(cls.includes("back") || cls.includes("help") || cls.includes("guide") || cls.includes("close") || cls.includes("swal") || cls.includes("nav")) return false;
                if(id.includes("guide") || id.includes("back") || id.includes("close") || id.includes("nav")) return false;
                
                return true;
            });
            
            if(btns.length > 0) {
                const btn = btns[Math.floor(Math.random() * btns.length)];
                BotVirtualCursor.moveToElement($(btn), 1, 0, () => {
                    setTimeout(() => { 
                        BotVirtualCursor.simulateClick(() => {
                            try { btn.click(); } catch(e){}
                        });
                    }, 500);
                });
            }
        }, 3000 + Math.random() * 4000);
    }
</script>

</body>
</html>
