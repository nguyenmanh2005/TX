<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_56', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require '../db_connect.php';

// AJAX history endpoint
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_history') {
    header('Content-Type: application/json; charset=utf-8');
    
    $id = $botUserId ?? 0;
    $sql = "SELECT * FROM history_vietlott WHERE Iduser = ? ORDER BY Time DESC LIMIT 20";
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
$sqlStats = "SELECT COUNT(*) as total, SUM(CASE WHEN WinAmount > 0 THEN 1 ELSE 0 END) as wins FROM history_vietlott WHERE Iduser = ?";
$stmtStats = $conn->prepare($sqlStats);
$stmtStats->bind_param("i", $userId);
$stmtStats->execute();
$resultStats = $stmtStats->get_result();
if ($rowStats = $resultStats->fetch_assoc()) {
    $gameThang = $rowStats['wins'] ?? 0;
    $gameThua = ($rowStats['total'] ?? 0) - $gameThang;
}
$stmtStats->close();

$money = $user['Money'];
$userName = $user['Name'];

// --- AJAX HANDLER ---
if (isset($_GET['action']) && $_GET['action'] === 'buy_vietlott') {
    header('Content-Type: application/json');
    $cost = 50000;

    if ($money < $cost) {
        echo json_encode(['success' => false, 'message' => '❌ Không đủ GTLM! Mỗi vé 50.000 GTLM.']);
        exit;
    }

    $rawNumbers = $_POST['numbers'] ?? '';
    if (empty($rawNumbers)) {
        echo json_encode(['success' => false, 'message' => '❌ Vui lòng chọn ít nhất 1 số.']);
        exit;
    }

    $selected = array_unique(array_map('intval', explode(',', $rawNumbers)));
    if (count($selected) < 1 || count($selected) > 6) {
        echo json_encode(['success' => false, 'message' => '❌ Vui lòng chọn từ 1 đến 6 số.']);
        exit;
    }

    // Quay số (6 số từ 1-45) với kiểm soát tỉ lệ
    function generateWinningNumbers()
    {
        $p = range(1, 45);
        shuffle($p);
        return array_slice($p, 0, 6);
    }

    $winningNumbers = generateWinningNumbers();
    $matchedNumbers = array_values(array_intersect($selected, $winningNumbers));
    $matchCount = count($matchedNumbers);

    // Kiểm soát tỉ lệ trúng giải lớn (3-6 số)
    if ($matchCount >= 3) {
        $chance = 100; // Mặc định 100%
        if ($matchCount === 3)
            $chance = 10;    // 10% trúng thật
        if ($matchCount === 4)
            $chance = 2;     // 2% trúng thật
        if ($matchCount === 5)
            $chance = 0.5;   // 0.5% trúng thật
        if ($matchCount === 6)
            $chance = 0.05;  // 0.05% trúng thật
        if (mt_rand(1, 10000) > $chance * 100) {
            // Re-roll: Quay lại cho đến khi matchCount <= 2
            $limit = 0;
            while ($matchCount >= 3 && $limit < 50) {
                $winningNumbers = generateWinningNumbers();
                $matchedNumbers = array_values(array_intersect($selected, $winningNumbers));
                $matchCount = count($matchedNumbers);
                $limit++;
            }
        }
    }
    sort($winningNumbers);

    // Tính thưởng
    $prize = 0;
    switch ($matchCount) {
        case 1:
            $prize = 10000;
            break;
        case 2:
            $prize = 50000;
            break;
        case 3:
            $prize = 200000;
            break;
        case 4:
            $prize = 1000000;
            break;
        case 5:
            $prize = 10000000;
            break;
        case 6:
            $prize = 100000000;
            break;
        default:
            $prize = 0;
    }

    $newMoney = $money - $cost + $prize;
    $conn->query("UPDATE users SET Money = $newMoney WHERE Iduser = $userId");
        
    // Insert vào history_vietlott table
    if (isset($botUserId)) {
        $historyStmt = $conn->prepare("INSERT INTO history_vietlott (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        if ($historyStmt) {
            $resultStr = implode(',', $winningNumbers);
            $historyStmt->bind_param("iisi", $userId, $cost, $resultStr, $prize);
            $historyStmt->execute();
            $historyStmt->close();
        }
    }

    if (file_exists('../game_history_helper.php')) {
        require_once '../game_history_helper.php';
        logGameHistoryWithAll($conn, $userId, 'Vietlott', $cost, $prize, $prize > 0);
    }

    echo json_encode([
        'success' => true,
        'winningNumbers' => $winningNumbers,
        'matchedNumbers' => $matchedNumbers,
        'prize' => $prize,
        'newBalance' => number_format($newMoney) . ' gtlm',
        'message' => ($prize > 0) ? "🎉 CHÚC MỪNG! Bạn trúng " . number_format($prize) . " gtlm!" : "😢 Rất tiếc! Chúc bạn may mắn lần sau."
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Vietlott Premium - Cơ Hội Đổi Đời</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Poppins:wght@400;600;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --v-gold: #ffd700;
            --v-blue: #0055a4;
            --v-red: #ed1c24;
        }

        body {
            margin: 0;
            cursor: url('../img/chuot.png'), auto !important;
            font-family: 'Poppins', sans-serif;
            background: <?= $bgGradientCSS ?>;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            color: white;
            overflow-x: hidden;
        }

        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: -1;
            pointer-events: none;
        }

        /* ── RESULT STATUS BADGE (CHÍNH XÁC NHƯ GAME 1) ── */
        #result-status-badge {
            position: fixed;
            top: 22%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.8);
            display: none;
            align-items: center;
            gap: 12px;
            padding: 12px 28px;
            border-radius: 50px;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6), 0 0 20px rgba(255, 255, 255, 0.2);
            z-index: 9999;
            pointer-events: none;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            opacity: 0;
            backdrop-filter: blur(10px);
        }

        #result-status-badge.show {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }

        #result-status-badge.badge-win {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.95), rgba(5, 150, 105, 0.95));
            border: 2px solid #34d399;
            color: #fff;
            box-shadow: 0 0 35px rgba(16, 185, 129, 0.7);
        }

        #result-status-badge.badge-jackpot {
            background: linear-gradient(135deg, rgba(234, 179, 8, 0.95), rgba(217, 119, 6, 0.95));
            border: 2px solid #fbbf24;
            color: #fff;
            box-shadow: 0 0 45px rgba(234, 179, 8, 0.9);
            animation: pulseGlow 1s infinite alternate;
        }

        #result-status-badge.badge-lose {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.9), rgba(185, 28, 28, 0.9));
            border: 2px solid #f87171;
            color: #fff;
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.6);
        }

        @keyframes pulseGlow {
            from { transform: translate(-50%, -50%) scale(1); filter: brightness(1); }
            to { transform: translate(-50%, -50%) scale(1.06); filter: brightness(1.2); }
        }

        .header-bar {
            width: 100%;
            padding: 10px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(15px);
            border-bottom: 2px solid var(--v-red);
            box-sizing: border-box;
        }

        .logo-vietlott {
            font-family: 'Cinzel', serif;
            font-size: 20px;
            color: var(--v-gold);
            letter-spacing: 2px;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
        }

        .user-money {
            background: rgba(0, 0, 0, 0.4);
            padding: 6px 20px;
            border-radius: 30px;
            border: 1px solid var(--v-gold);
            font-weight: 800;
            color: var(--v-gold);
            font-size: 15px;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.1);
        }

        .main-container {
            margin: 10px auto;
            max-width: 820px;
            width: 100%;
            padding: 0 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-sizing: border-box;
        }

        /* Glass Panel */
        .glass-panel {
            background: rgba(18, 18, 30, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            padding: 16px 22px;
            width: 100%;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            position: relative;
            box-sizing: border-box;
        }

        .section-title {
            font-size: 13px;
            font-weight: 800;
            color: var(--v-gold);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 12px;
            text-align: center;
        }

        /* Number Selection Grid */
        .number-grid {
            display: grid;
            grid-template-columns: repeat(9, 1fr);
            gap: 6px;
            margin-bottom: 14px;
            max-width: 740px;
            margin-left: auto;
            margin-right: auto;
        }

        .num-box {
            aspect-ratio: 1;
            width: 100%;
            max-width: 36px;
            height: 36px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            user-select: none;
        }

        .num-box:hover {
            transform: scale(1.12);
            background: rgba(255, 255, 255, 0.25);
            border-color: var(--v-gold);
        }

        .num-box.selected {
            background: linear-gradient(135deg, var(--v-red) 0%, #a00 100%);
            border-color: var(--v-gold);
            color: white;
            box-shadow: 0 0 16px rgba(237, 28, 36, 0.8);
            transform: scale(1.14);
        }

        /* Drawing Area */
        .drawing-zone {
            background: rgba(0, 0, 0, 0.4);
            padding: 10px 16px;
            border-radius: 16px;
            margin: 10px 0;
            min-height: 65px;
            display: flex;
            flex-direction: column;
            align-items: center;
            border: 1px dashed rgba(255, 215, 0, 0.3);
        }

        .winning-balls {
            display: flex;
            gap: 10px;
            margin-top: 6px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .ball {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 900;
            color: black;
            background: radial-gradient(circle at 30% 30%, #fff 0%, #ffd700 80%, #b8860b 100%);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
            transform: translateY(-20px);
            opacity: 0;
            animation: ballDrop 0.4s forwards;
        }

        .ball.matched {
            background: radial-gradient(circle at 30% 30%, #fff 0%, #ed1c24 80%, #a00 100%);
            color: white;
            box-shadow: 0 0 20px #ed1c24;
            transform: scale(1.1);
        }

        @keyframes ballDrop {
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Actions */
        .action-row {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .buy-btn {
            background: linear-gradient(135deg, var(--v-red) 0%, #a00 100%);
            color: white;
            border: 2px solid var(--v-gold);
            padding: 10px 45px;
            border-radius: 40px;
            font-size: 16px;
            font-weight: 900;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 6px 20px rgba(237, 28, 36, 0.4);
        }

        .buy-btn:hover:not(:disabled) {
            transform: translateY(-2px) scale(1.04);
            box-shadow: 0 10px 30px rgba(237, 28, 36, 0.6);
        }

        .buy-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .status-msg {
            margin-top: 8px;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid var(--v-red);
            color: #fff;
            padding: 6px 24px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
        }

        .home-link {
            display: none !important;
        }
    
        /* History Box Styles */
        .bottom-section {
            margin-top: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            max-width: 820px;
            width: 100%;
            margin-left: auto;
            margin-right: auto;
            box-sizing: border-box;
        }

        .history-box, .chart-box {
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            color: white;
            box-sizing: border-box;
        }

        .history-box h3, .chart-box h3 {
            margin-top: 0;
            font-size: 15px;
            color: #ffd700;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.4);
            margin-bottom: 10px;
        }

        .history-box table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .history-box table tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .history-box table td, .history-box table th {
            padding: 6px 8px;
            text-align: center;
        }

        .history-box table th {
            background: rgba(255, 255, 255, 0.1);
            font-weight: 700;
            color: #ffd700;
        }

        .history-box table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        @media (max-width: 768px) {
            .bottom-section {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
    
        /* Statistics Container */
        .stats-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .stat-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-item.wins {
            border-left: 3px solid #4ade80;
        }
        
        .stat-item.losses {
            border-left: 3px solid #ff6b6b;
        }
        
        .stat-item .label {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        
        .stat-item .value {
            font-size: 20px;
            font-weight: 700;
            color: #ffd700;
        }
        
        .chart-box {
            display: flex;
            flex-direction: column;
        }
        
        .chart-box canvas {
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <!-- ThreeJS 3D Canvas Background -->
    <canvas id="threejs-background"></canvas>

    <!-- Modal Status Badge (Thắng / Thua Game 1) -->
    <div id="result-status-badge">
        <span class="badge-icon">🎉</span>
        <span class="badge-text">CHIẾN THẮNG</span>
    </div>

    <header class="header-bar">
        <div class="logo-vietlott">VIETLOTT PREMIUM</div>
        <div class="user-money">💰 <span id="money-val"><?= number_format($money) ?> gtlm</span></div>
        <div style="font-size: 13px; color: #aaa;">STREAMER: <b style="color: #ffd700;"><?= htmlspecialchars($userName) ?></b></div>
    </header>

    <div class="main-container">
        <div class="glass-panel">
            <div class="section-title">CHỌN TỪ 1 ĐẾN 6 SỐ (1-45)</div>

            <div class="number-grid">
                <?php for ($i = 1; $i <= 45; $i++): ?>
                    <div class="num-box" data-num="<?= $i ?>" onclick="toggleNumber(this, <?= $i ?>)"><?= $i ?></div>
                <?php endfor; ?>
            </div>

            <div class="drawing-zone">
                <div class="section-title" id="draw-label" style="margin-bottom: 4px;">KẾT QUẢ QUAY THƯỞNG</div>
                <div class="winning-balls" id="ball-container">
                    <!-- Balls will appear here -->
                </div>
            </div>

            <div class="action-row">
                <button class="buy-btn" id="buy-trigger">🎫 MUA VÉ - 50.000 gtlm</button>
                <div style="color: #aaa; font-size: 12px;">BẠN ĐÃ CHỌN: <span id="selected-list"
                        style="color: var(--v-gold); font-weight: 800;">-</span></div>
                <div class="status-msg" id="status-bar">CHỌN SỐ VÀ NHẤN "MUA VÉ" ĐỂ THỬ VẬN MAY!</div>
            </div>
        </div>
    </div>

    <!-- Theme Config & Effects -->
    <script>
        window.themeConfig = {
            particleCount: <?= $particleCount ?? 800 ?>,
            particleSize: <?= $particleSize ?? 0.05 ?>,
            particleColor: '<?= $particleColor ?? "#ff00ff" ?>',
            particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
            shapeCount: <?= $shapeCount ?? 10 ?>,
            shapeColors: <?= json_encode($shapeColors ?? ["#ff00ff", "#00ffff", "#ffff00"]) ?>,
            shapeOpacity: <?= $shapeOpacity ?? 0.3 ?>,
            bgGradient: <?= json_encode($bgGradient ?? ["#000000", "#110011", "#220022"]) ?>
        };
    </script>
    <script src="../threejs-background.js"></script>
    <script src="../assets/js/game-effects.js"></script>
    <script src="../assets/js/game-effects-auto.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div class="bottom-section">
        <div class="history-box">
            <h3>📋 Lịch sử quay thưởng (10 lần gần nhất)</h3>
            <div class="table-responsive">
                <table id="historyTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cược</th>
                            <th>Kết quả</th>
                            <th>Thắng</th>
                            <th>Thời gian</th>
                        </tr>
                    </thead>
                    <tbody id="historyBody">
                        <tr>
                            <td colspan="5" style="text-align: center; color: #888; font-style: italic;">Chưa có lượt chơi nào</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="chart-box">
            <h3>📊 Thống kê Vietlott</h3>
            <div class="stats-container">
                <div class="stat-item wins">
                    <div class="label">Lần Thắng</div>
                    <div class="value" style="color: #4ade80;" id="stat-wins"><?= $gameThang ?></div>
                </div>
                <div class="stat-item losses">
                    <div class="label">Lần Thua</div>
                    <div class="value" style="color: #ff6b6b;" id="stat-losses"><?= $gameThua ?></div>
                </div>
            </div>
            <div style="position: relative; height: 160px;">
                <canvas id="gameChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Game Logic -->
    <script>
        let selectedNumbers = [];
        let isSpinning = false;

        function showResultStatus(type, text, icon) {
            const badge = document.getElementById('result-status-badge');
            if (!badge) return;
            badge.className = '';
            badge.classList.add('badge-' + type);
            badge.querySelector('.badge-icon').textContent = icon || (type === 'win' ? '🎉' : (type === 'jackpot' ? '👑' : '😢'));
            badge.querySelector('.badge-text').textContent = text;
            badge.style.display = 'flex';
            void badge.offsetWidth;
            badge.classList.add('show');

            if (type === 'win' || type === 'jackpot') {
                if (typeof GameEffects !== 'undefined' && GameEffects.win) {
                    GameEffects.win();
                }
            } else {
                if (typeof GameEffects !== 'undefined' && GameEffects.lose) {
                    GameEffects.lose();
                }
            }

            setTimeout(() => {
                badge.classList.remove('show');
                setTimeout(() => {
                    badge.style.display = 'none';
                }, 400);
            }, 3800);
        }

        function toggleNumber(el, num) {
            if (isSpinning) return;
            const idx = selectedNumbers.indexOf(num);
            if (idx > -1) {
                selectedNumbers.splice(idx, 1);
                el.classList.remove('selected');
            } else {
                if (selectedNumbers.length >= 6) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ text: 'Bạn chỉ được chọn tối đa 6 số!', icon: 'warning', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
                    }
                    return;
                }
                selectedNumbers.push(num);
                el.classList.add('selected');
            }
            selectedNumbers.sort((a, b) => a - b);
            document.getElementById('selected-list').textContent = selectedNumbers.length > 0 ? selectedNumbers.join(', ') : '-';
        }

        async function buyTicket() {
            if (isSpinning) return;
            if (selectedNumbers.length < 1) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ text: 'Vui lòng chọn ít nhất 1 số!', icon: 'warning', toast: true, position: 'top-end', showConfirmButton: false, timer: 2500 });
                }
                return;
            }

            isSpinning = true;
            document.getElementById('buy-trigger').disabled = true;
            document.getElementById('status-bar').textContent = "🎰 ĐANG QUAY SỐ... HỒI HỘP QUÁ!";
            const container = document.getElementById('ball-container');
            container.innerHTML = '';

            try {
                const formData = new FormData();
                formData.append('numbers', selectedNumbers.join(','));

                const res = await fetch('?action=buy_vietlott', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    // Animation quay bóng (400ms mỗi quả để kịch tính mượt mà)
                    for (let i = 0; i < 6; i++) {
                        await new Promise(r => setTimeout(r, 400));
                        const val = data.winningNumbers[i];
                        const ball = document.createElement('div');
                        ball.className = 'ball';
                        if (data.matchedNumbers.includes(val)) {
                            ball.classList.add('matched');
                        }
                        ball.textContent = val;
                        container.appendChild(ball);
                    }

                    setTimeout(() => {
                        document.getElementById('money-val').textContent = data.newBalance;
                        document.getElementById('status-bar').textContent = data.message;

                        const matchCount = data.matchedNumbers ? data.matchedNumbers.length : 0;
                        if (data.prize > 0) {
                            if (typeof confetti === 'function') {
                                confetti({ particleCount: 160, spread: 80, origin: { y: 0.6 }, colors: ['#ffd700', '#ed1c24', '#00ffff'] });
                            }
                            if (matchCount >= 3 || data.prize >= 200000) {
                                showResultStatus('jackpot', `👑 TRÚNG ${matchCount} SỐ! +${data.prize.toLocaleString('vi-VN')} GTLM`, '👑');
                            } else {
                                showResultStatus('win', `🎉 TRÚNG ${matchCount} SỐ! +${data.prize.toLocaleString('vi-VN')} GTLM`, '🎉');
                            }
                        } else {
                            showResultStatus('lose', `😢 BAY MÀU -50.000 GTLM (TRÚNG 0 SỐ)`, '😢');
                        }

                        isSpinning = false;
                        document.getElementById('buy-trigger').disabled = false;
                        loadVietlottHistory();
                    }, 400);

                } else {
                    document.getElementById('status-bar').textContent = data.message;
                    showResultStatus('lose', data.message, '⚠️');
                    isSpinning = false;
                    document.getElementById('buy-trigger').disabled = false;
                }
            } catch (e) {
                console.error(e);
                isSpinning = false;
                document.getElementById('buy-trigger').disabled = false;
            }
        }

        document.getElementById('buy-trigger').onclick = buyTicket;

        async function loadVietlottHistory() {
            try {
                const response = await fetch('?action=get_history', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) return;
                const data = await response.json();
                if (data.success && data.history && data.history.length > 0) {
                    const tbody = document.getElementById('historyBody');
                    if (tbody) {
                        tbody.innerHTML = '';
                        let wins = 0;
                        let losses = 0;
                        data.history.forEach(r => {
                            if (parseInt(r.WinAmount) > 0) wins++; else losses++;
                        });
                        const statWins = document.getElementById('stat-wins');
                        const statLosses = document.getElementById('stat-losses');
                        if (statWins) statWins.textContent = wins;
                        if (statLosses) statLosses.textContent = losses;

                        data.history.slice(0, 10).forEach((record) => {
                            const newRow = document.createElement('tr');
                            const winVal = parseInt(record.WinAmount);
                            const winColor = winVal > 0 ? '#4ade80' : '#ff6b6b';
                            
                            newRow.innerHTML = `
                                <td style="text-align: center; color: #ccc; font-family: monospace;">${record.Id}</td>
                                <td style="text-align: right; color: #eee;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                                <td style="text-align: center; color: #ffd700; font-weight: 600;">${record.Result || '-'}</td>
                                <td style="text-align: right; font-weight: bold; color: ${winColor};">${winVal.toLocaleString('vi-VN')}</td>
                                <td style="text-align: right; font-size: 11px; color: #888;">${record.Time}</td>
                            `;
                            tbody.appendChild(newRow);
                        });
                    }
                }
            } catch (error) {
                console.error('Load history error:', error);
            }
        }

        // Initialize Charts and Events
        document.addEventListener('DOMContentLoaded', function() {
            loadVietlottHistory();
            
            const ctxVietlott = document.getElementById('gameChart');
            if (ctxVietlott) {
                new Chart(ctxVietlott.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Thắng', 'Thua'],
                        datasets: [{
                            data: [<?= $gameThang ?>, <?= $gameThua ?>],
                            backgroundColor: ['rgba(74, 222, 128, 0.8)', 'rgba(255, 107, 107, 0.8)'],
                            borderColor: ['rgba(74, 222, 128, 1)', 'rgba(255, 107, 107, 1)'],
                            borderWidth: 2,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: 'rgba(255, 255, 255, 0.8)', padding: 10, usePointStyle: true, font: { size: 11 } }
                            }
                        }
                    }
                });
            }
        });
    </script>

    <!-- Nạp Bot AI Chuyên Nghiệp -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="bots/bot_56.js"></script>
</body>
</html>
