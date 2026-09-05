<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_51', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require '../db_connect.php';
$useBotTheme = $botUserId;
require_once '../load_theme.php';

// Fallback theme cho Bàn 51 (Sicbo Đỏ Ruby Neon)
$particleColor = $particleColor ?? '#ff4757';
$shapeColors = $shapeColors ?? ['#ff4757', '#ff6b81', '#70a1ff', '#ffa502'];
$bgGradient = $bgGradient ?? ['#000000', '#12001a', '#250033'];
if (empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, ' . $bgGradient[0] . ' 0%, ' . $bgGradient[1] . ' 50%, ' . ($bgGradient[2] ?? $bgGradient[1]) . ' 100%)';
}

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
            echo json_encode(['success' => false, 'message' => 'Cược không hợp lệ!']);
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
    <title>Sic Bo - Đỉnh Cao Xúc Xắc</title>
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
            margin: 0;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Exo 2', system-ui, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: url('../img/chuot.png'), auto !important;
        }
        * { cursor: inherit; }
        button, a, input, select, .bet-item, .btn-quick-bet, .btn-roll {
            cursor: url('../img/tay.png'), pointer !important;
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

        /* 🏆 Badge Thông Báo Thắng / Thua Chuẩn Game ID 1 */
        #result-status-badge {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.5);
            background: rgba(10, 12, 24, 0.94);
            border-radius: 24px;
            padding: 28px 56px;
            text-align: center;
            z-index: 99999;
            pointer-events: none;
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 2px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.85);
            font-family: 'Orbitron', 'Exo 2', sans-serif;
            transition: transform 0.4s cubic-bezier(0.17, 0.89, 0.32, 1.49), opacity 0.4s;
            opacity: 0;
        }
        #result-badge-icon {
            font-size: 3.8rem;
            margin-bottom: 8px;
            animation: badgeIconBounce 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
        }
        @keyframes badgeIconBounce {
            0% { transform: scale(0) rotate(-20deg); }
            70% { transform: scale(1.3) rotate(10deg); }
            100% { transform: scale(1) rotate(0deg); }
        }
        #result-badge-title {
            font-size: 1.8rem;
            font-weight: 900;
            letter-spacing: 2px;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        #result-badge-amount {
            font-size: 1.4rem;
            font-weight: 800;
            opacity: 0.95;
            font-family: 'Orbitron', sans-serif;
        }
        #result-badge-msg {
            font-size: 0.85rem;
            opacity: 0.75;
            margin-top: 6px;
            max-width: 320px;
            font-family: 'Exo 2', system-ui, sans-serif;
        }
        .main-container {
            position: relative;
            z-index: 1;
            width: 96%;
            max-width: 960px;
            margin: 0.5rem auto 1rem;
            text-align: center;
        }
        .game-title {
            font-size: clamp(1.8rem, 4vw, 2.4rem);
            font-weight: 900;
            color: var(--primary);
            text-shadow: 0 0 25px rgba(239, 68, 68, 0.4);
            margin-bottom: 0.2rem;
            text-transform: uppercase;
            letter-spacing: 6px;
        }
        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 1.8rem;
            padding: 1.2rem 1.6rem;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.5);
            margin-bottom: 0.8rem;
        }
        .balance-pill {
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid var(--accent);
            padding: 0.4rem 1.4rem;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 0.8rem;
            color: var(--accent);
            font-weight: 700;
            font-size: 0.85rem;
        }
        .dice-area {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 1rem;
            min-height: 65px;
        }
        .die {
            width: clamp(50px, 8vw, 65px);
            aspect-ratio: 1;
            background: #fff;
            border-radius: 0.8rem;
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(2rem, 4vw, 2.6rem);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.5);
            transition: transform 0.1s;
        }
        .chip-selector {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .btn-quick-bet {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: url('../img/tay.png'), pointer !important;
            font-weight: 600;
            transition: 0.3s;
            font-size: 0.78rem;
        }
        .btn-quick-bet:hover, .btn-quick-bet.sel {
            background: var(--accent);
            color: #000;
            border-color: var(--accent);
        }
        .bet-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(115px, 1fr));
            gap: 0.6rem;
            margin-bottom: 1.2rem;
        }
        .bet-item {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
            border-radius: 0.9rem;
            padding: 0.7rem 0.4rem;
            cursor: url('../img/tay.png'), pointer !important;
            transition: all 0.2s;
            position: relative;
        }
        .bet-item:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.2);
        }
        .bet-item.active {
            border-color: var(--accent);
            background: rgba(251, 191, 36, 0.1);
        }
        .bet-item .label {
            font-size: 0.76rem;
            font-weight: 800;
            color: #fca5a5;
            margin-bottom: 3px;
            text-transform: uppercase;
        }
        .bet-item .odds {
            font-size: 0.65rem;
            color: rgba(255, 255, 255, 0.4);
            font-weight: 600;
        }
        .chip-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: var(--accent);
            color: #000;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            font-size: 0.62rem;
            font-weight: 900;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.4);
            z-index: 2;
        }
        .btn-roll {
            background: linear-gradient(135deg, var(--primary) 0%, #7f1d1d 100%);
            border: none;
            padding: 0.85rem 3.5rem;
            border-radius: 50px;
            color: #fff;
            font-size: 1.15rem;
            font-weight: 900;
            cursor: url('../img/tay.png'), pointer !important;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 3px;
            width: 100%;
            max-width: 320px;
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
        }
        .btn-roll:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(239, 68, 68, 0.6);
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
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .history-table th {
            color: rgba(255, 255, 255, 0.4);
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
        @media (max-width: 768px) {
            .bet-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .btn-roll {
                padding: 1rem 2rem;
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <!-- 🏆 Badge thông báo thắng/thua giống game ID 1 -->
    <div id="result-status-badge">
        <div id="result-badge-icon">🏆</div>
        <div id="result-badge-title">THẮNG SIC BO!</div>
        <div id="result-badge-amount">+100,000 GTLM</div>
        <div id="result-badge-msg">Nổ thưởng xúc xắc rực rỡ!</div>
    </div>
    <div class="main-container">
        <h1 class="game-title">SIC BO</h1>
        <div class="balance-pill">💰 Số Gtlm: <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span>
            gtlm
        </div>
        <div class="glass-card">
            <div class="dice-area" id="dice-container">
                <div class="die">🎲</div>
                <div class="die">🎲</div>
                <div class="die">🎲</div>
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
                <div class="bet-item" data-type="small">
                    <div class="label">Ác quỷ (4-10)</div>
                    <div class="odds">1:1</div>
                </div>
                <div class="bet-item" data-type="odd">
                    <div class="label">LẺ</div>
                    <div class="odds">1:1</div>
                </div>
                <div class="bet-item" data-type="any_triple" style="grid-column: span 2;">
                    <div class="label">BẤT KỲ BỘ BA</div>
                    <div class="odds">1:30</div>
                </div>
                <div class="bet-item" data-type="even">
                    <div class="label">CHẴN</div>
                    <div class="odds">1:1</div>
                </div>
                <div class="bet-item" data-type="big">
                    <div class="label">Thiên thần (11-17)</div>
                    <div class="odds">1:1</div>
                </div>
                <div class="bet-item" data-type="single_1">
                    <div class="label">Số 1</div>
                    <div class="odds">x1,x2,x3</div>
                </div>
                <div class="bet-item" data-type="single_2">
                    <div class="label">Số 2</div>
                    <div class="odds">x1,x2,x3</div>
                </div>
                <div class="bet-item" data-type="single_3">
                    <div class="label">Số 3</div>
                    <div class="odds">x1,x2,x3</div>
                </div>
                <div class="bet-item" data-type="single_4">
                    <div class="label">Số 4</div>
                    <div class="odds">x1,x2,x3</div>
                </div>
                <div class="bet-item" data-type="single_5">
                    <div class="label">Số 5</div>
                    <div class="odds">x1,x2,x3</div>
                </div>
                <div class="bet-item" data-type="single_6">
                    <div class="label">Số 6</div>
                    <div class="odds">x1,x2,x3</div>
                </div>
                <div class="bet-item" data-type="total_9">
                    <div class="label">Tổng 9</div>
                    <div class="odds">1:7</div>
                </div>
                <div class="bet-item" data-type="total_10">
                    <div class="label">Tổng 10</div>
                    <div class="odds">1:6</div>
                </div>
                <div class="bet-item" data-type="total_11">
                    <div class="label">Tổng 11</div>
                    <div class="odds">1:6</div>
                </div>
                <div class="bet-item" data-type="total_12">
                    <div class="label">Tổng 12</div>
                    <div class="odds">1:7</div>
                </div>
                <div class="bet-item" data-type="total_4">
                    <div class="label">Tổng 4</div>
                    <div class="odds">1:60</div>
                </div>
                <div class="bet-item" data-type="total_17">
                    <div class="label">Tổng 17</div>
                    <div class="odds">1:60</div>
                </div>
            </div>
            <button id="roll-btn" class="btn-roll">LẮC XÚC XẮC</button>
        </div>
        <div class="history-section">
            <h2 style="font-size: 1.2rem; letter-spacing: 2px; margin-bottom: 1rem;">LỊCH SỬ GẦN ĐÂY</h2>
            <div style="overflow-x: auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>gtlm cược</th>
                            <th>Kết quả</th>
                            <th>Thắng/Thua</th>
                        </tr>
                    </thead>
                    <tbody id="history-body"></tbody>
                </table>
            </div>
        </div>
        <div style="margin-top: 1rem; margin-bottom: 2rem;"><a href="../index.php"
                style="color: var(--primary); text-decoration: none; font-weight: 700; border: 1px solid var(--primary); padding: 0.5rem 1.8rem; border-radius: 50px; font-size: 0.85rem; transition: 0.3s;">🏠
                QUAY LẠI SẢNH</a></div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/confetti.browser.min.js"></script>
    <?php require_once '../casino_help.php'; ?>
    <script>
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
            if (badge.length === 0) {
                $(this).append(`<div class="bet-amount-badge chip-badge"></div>`);
                badge = $(this).find('.bet-amount-badge');
            }
            let displayBet = bets[type];
            if (displayBet >= 1000) {
                displayBet = (displayBet / 1000) + 'K';
            }
            badge.text(displayBet).show();
        });
        function clearBets() {
            if (isRolling) return;
            bets = {};
            $('.bet-item').removeClass('active');
            $('.bet-amount-badge').remove();
        }
        $('#roll-btn').click(function() {
            if (isRolling || Object.keys(bets).length === 0) {
                if (Object.keys(bets).length === 0) Swal.fire('Lỗi', 'Vui lòng đặt cược trước khi lắc!', 'error');
                return;
            }
            isRolling = true;
            $('#roll-btn').prop('disabled', true);
            let betArray = [];
            for (let type in bets) {
                betArray.push({ type: type, amount: bets[type] });
            }
            $.post('?action=roll', { bets: JSON.stringify(betArray) }, function(data) {
                if (!data.success) {
                    Swal.fire('Lỗi', data.message, 'error');
                    isRolling = false;
                    $('#roll-btn').prop('disabled', false);
                    return;
                }
                let diceEls = document.querySelectorAll('.die');
                diceEls.forEach((el, idx) => {
                    el.textContent = data.dice[idx];
                    el.style.transform = 'scale(1.2)';
                    setTimeout(() => el.style.transform = 'scale(1)', 200);
                });
                setTimeout(() => {
                    isRolling = false;
                    $('#roll-btn').prop('disabled', false);
                    $('#balance-val').text(data.money);
                    clearBets();
                    if (data.winAmount > 0) {
                        try {
                            if (typeof confetti !== 'undefined') {
                                confetti({ particleCount: 100, spread: 70, origin: { y: 0.6 } });
                            }
                        } catch(e) { console.error(e); }
                        if (window.GameEffects) {
                            if (data.winAmount >= 500000) window.GameEffects.showBigWin(data.winAmount);
                            else window.GameEffects.showWin(data.winAmount);
                        }
                        showResultBadge(true, data.winAmount, 'Tổng thu về: ' + Number(data.totalRevenue).toLocaleString('vi-VN') + ' GTLM');
                    } else {
                        const totalLossAmt = Math.abs(data.winAmount) || 10000;
                        if (window.GameEffects) window.GameEffects.showLoss(totalLossAmt);
                        showResultBadge(false, totalLossAmt, 'Trắng tay rồi - Chúc may mắn ván sau nhé!');
                    }
                }, 1000);
            }, 'json').fail(function(xhr) {
                isRolling = false;
                $('#roll-btn').prop('disabled', false);
                Swal.fire('Lỗi', 'Không thể kết nối đến máy chủ', 'error');
            });
        });

        function showResultBadge(isWin, winAmount, statusMsg) {
            const badge = document.getElementById('result-status-badge');
            const icon  = document.getElementById('result-badge-icon');
            const title = document.getElementById('result-badge-title');
            const amtEl = document.getElementById('result-badge-amount');
            const msgEl = document.getElementById('result-badge-msg');

            if (!badge) return;

            if (isWin) {
                badge.style.borderColor = '#f1c40f';
                badge.style.boxShadow   = '0 25px 80px rgba(0,0,0,0.85), 0 0 80px rgba(241,196,15,0.6)';
                icon.textContent  = '🏆';
                title.textContent = 'THẮNG SIC BO!';
                title.style.color = '#f1c40f';
                amtEl.textContent = '+' + parseInt(winAmount).toLocaleString('vi-VN') + ' GTLM';
                amtEl.style.color = '#4ade80';
            } else {
                badge.style.borderColor = '#e74c3c';
                badge.style.boxShadow   = '0 25px 80px rgba(0,0,0,0.85), 0 0 60px rgba(231,76,60,0.5)';
                icon.textContent  = '💨';
                title.textContent = 'BAY MÀU!';
                title.style.color = '#e74c3c';
                amtEl.textContent = '-' + parseInt(winAmount).toLocaleString('vi-VN') + ' GTLM';
                amtEl.style.color = '#ff4757';
            }
            msgEl.textContent = statusMsg || '';

            badge.style.display = 'block';
            requestAnimationFrame(() => {
                badge.style.transform = 'translate(-50%, -50%) scale(1.08)';
                badge.style.opacity   = '1';
                setTimeout(() => { badge.style.transform = 'translate(-50%, -50%) scale(1)'; }, 150);
            });
            setTimeout(() => {
                badge.style.transform = 'translate(-50%, -50%) scale(0.8)';
                badge.style.opacity   = '0';
                setTimeout(() => { badge.style.display = 'none'; }, 400);
            }, 3500);
        }
    </script>

    <!-- Premium Three.js Effects System -->
    <canvas id="threejs-background"></canvas>
    <script>
        window.themeConfig = {
            particleCount: <?= $particleCount ?? 800 ?>,
            particleSize: <?= $particleSize ?? 0.05 ?>,
            particleColor: '<?= $particleColor ?? "#ff4757" ?>',
            particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
            shapeCount: <?= $shapeCount ?? 15 ?>,
            shapeColors: <?= json_encode($shapeColors ?? ["#ff4757", "#ff6b81", "#70a1ff", "#ffa502"]) ?>,
            shapeOpacity: <?= $shapeOpacity ?? 0.35 ?>,
            bgGradient: <?= json_encode($bgGradient ?? ["#000000", "#12001a", "#250033"]) ?>
        };
    </script>
    <script src="../threejs-background.js"></script>
    <script src="../assets/js/game-effects.js"></script>
    <script src="../assets/js/game-effects-auto.js"></script>

    <!-- SMART PRO BOT SCRIPT -->
    <script>
    if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
    if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
    </script>
    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="bots/bot_51.js"></script>
</body>
</html>