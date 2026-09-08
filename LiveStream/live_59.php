<?php
session_start();

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../game_history_helper.php';
require_once __DIR__ . '/bot_streamer_helper.php';

$botUser = getOrCreateBotStreamerUser($conn, 'bot_59', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

$useBotTheme = $botUserId;
require_once __DIR__ . '/../load_theme.php';

$userId = $botUserId;

// Lấy thông tin số dư
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user ? (float)$user['Money'] : 50000000;
$userName = $user ? $user['Name'] : 'bot_59';
$stmt->close();

// Fallback theme colors
$particleColor = $particleColor ?? '#12c2e9';
$shapeColors   = $shapeColors   ?? ['#12c2e9', '#a29bfe', '#fd79a8', '#00cec9'];
$bgGradient    = $bgGradient    ?? ['#0f0c29', '#302b63', '#24243e'];
if (empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, ' . $bgGradient[0] . ' 0%, ' . $bgGradient[1] . ' 50%, ' . ($bgGradient[2] ?? $bgGradient[1]) . ' 100%)';
}

// Bảng cấu hình Multipliers chuẩn game gốc (giống api_plinko_v2.php)
$plinkoConfig = [
    8 => [
        'low'    => [5.6, 2.1, 1.1, 1, 0.5, 1, 1.1, 2.1, 5.6],
        'medium' => [13, 3, 1.3, 0.7, 0.4, 0.7, 1.3, 3, 13],
        'high'   => [29, 4, 1.5, 0.3, 0.2, 0.3, 1.5, 4, 29]
    ],
    12 => [
        'low'    => [10, 3, 1.6, 1.4, 1.1, 1, 0.5, 1, 1.1, 1.4, 1.6, 3, 10],
        'medium' => [33, 11, 4, 2, 1.1, 0.6, 0.3, 0.6, 1.1, 2, 4, 11, 33],
        'high'   => [170, 24, 8.1, 2, 0.7, 0.2, 0.2, 0.2, 0.7, 2, 8.1, 24, 170]
    ],
    16 => [
        'low'    => [16, 9, 2, 1.4, 1.4, 1.2, 1.1, 1, 0.5, 1, 1.1, 1.2, 1.4, 1.4, 2, 9, 16],
        'medium' => [110, 41, 10, 5, 3, 1.5, 1, 0.5, 0.3, 0.5, 1, 1.5, 3, 5, 10, 41, 110],
        'high'   => [1000, 130, 26, 9, 4, 2, 0.2, 0.2, 0.2, 0.2, 0.2, 2, 4, 9, 26, 130, 1000]
    ]
];

// AJAX handler
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];

    if ($action === 'config') {
        echo json_encode(['success' => true, 'config' => $plinkoConfig]);
        exit;
    }

    if ($action === 'drop') {
        $bet   = (float)($_POST['bet'] ?? 10000);
        $risk  = $_POST['risk'] ?? 'medium';
        $rows  = (int)($_POST['rows'] ?? 12);
        $balls = (int)($_POST['balls'] ?? 1);
        $balls = max(1, min(50, $balls));

        if (!isset($plinkoConfig[$rows])) $rows = 12;
        if (!isset($plinkoConfig[$rows][$risk])) $risk = 'medium';

        $totalBet = $bet * $balls;

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $locked = $res ? (float)$res['Money'] : 0;
            $stmt->close();

            if ($bet <= 0 || $totalBet > $locked) {
                // Tự động hồi vốn cho bot streamer nếu cạn ví
                if ($locked < $totalBet) {
                    $conn->query("UPDATE users SET Money = 50000000 WHERE Iduser = $userId");
                    $locked = 50000000;
                } else {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => 'Số dư không đủ để cược!']);
                    exit;
                }
            }

            $multipliers = $plinkoConfig[$rows][$risk];
            $results = [];
            $totalWin = 0;

            for ($i = 0; $i < $balls; $i++) {
                $path = [];
                $slot = 0;
                for ($r = 0; $r < $rows; $r++) {
                    $dir = rand(0, 1); // 0 = trái, 1 = phải
                    $path[] = $dir;
                    if ($dir === 1) {
                        $slot++;
                    }
                }
                $slot = min($slot, count($multipliers) - 1);
                $mult = $multipliers[$slot];
                $win = round($bet * $mult);
                $totalWin += $win;

                $results[] = [
                    'path' => $path,
                    'slot' => $slot,
                    'mult' => $mult,
                    'win'  => $win
                ];
            }

            $newMoney = $locked - $totalBet + $totalWin;
            $conn->query("UPDATE users SET Money = $newMoney WHERE Iduser = $userId");

            // Lưu lịch sử Plinko
            $profit = $totalWin - $totalBet;
            $resStr = "R:$rows|Risk:$risk|Balls:$balls|AvgX:" . ($totalBet > 0 ? round($totalWin / $totalBet, 2) : 0);
            $his = $conn->prepare("INSERT INTO history_plinko (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
            if ($his) {
                $his->bind_param("idss", $userId, $totalBet, $resStr, $profit);
                $his->execute();
                $his->close();
            }

            // Ghi nhận game_history chuẩn quy tắc Rule 5.2
            if (function_exists('logGameHistoryWithAll')) {
                logGameHistoryWithAll($conn, $userId, 'Plinko V2 Pro', $totalBet, $totalWin, $totalWin > $totalBet);
            } else if (function_exists('logGameHistory')) {
                logGameHistory($conn, $userId, 'Plinko V2 Pro', $totalBet, $totalWin, $totalWin > $totalBet);
            }

            $conn->commit();

            echo json_encode([
                'success'    => true,
                'results'    => $results,
                'totalBet'   => $totalBet,
                'totalWin'   => $totalWin,
                'sessionNet' => $profit,
                'money'      => number_format($newMoney, 0, ',', '.'),
                'rawMoney'   => $newMoney
            ]);
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi xử lý: ' . $e->getMessage()]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Plinko V2 Pro - Bàn Live 59</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;500;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; background: <?= $bgGradientCSS ?>; background-attachment: fixed;
            color: #fff; font-family: 'Inter', sans-serif; min-height: 100vh;
            overflow-x: hidden; display: flex; flex-direction: column; align-items: center;
        }
        #threejs-background { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: -1; pointer-events: none; }

        #result-status-badge {
            position: fixed; top: 20%; left: 50%; transform: translate(-50%,-50%) scale(0.8);
            display: none; align-items: center; gap: 12px; padding: 10px 24px; border-radius: 50px;
            font-size: 18px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase;
            box-shadow: 0 10px 30px rgba(0,0,0,0.6); z-index: 9999; pointer-events: none;
            transition: all 0.4s cubic-bezier(0.175,0.885,0.32,1.275); opacity: 0; backdrop-filter: blur(10px);
        }
        #result-status-badge.show { opacity: 1; transform: translate(-50%,-50%) scale(1); }
        #result-status-badge.badge-win  { background: linear-gradient(135deg,rgba(16,185,129,0.95),rgba(5,150,105,0.95)); border: 2px solid #34d399; box-shadow: 0 0 35px rgba(16,185,129,0.7); }
        #result-status-badge.badge-jackpot { background: linear-gradient(135deg,rgba(234,179,8,0.95),rgba(217,119,6,0.95)); border: 2px solid #fbbf24; box-shadow: 0 0 45px rgba(234,179,8,0.9); animation: pulseGlow 0.8s infinite alternate; }
        #result-status-badge.badge-lose { background: linear-gradient(135deg,rgba(239,68,68,0.9),rgba(185,28,28,0.9)); border: 2px solid #f87171; box-shadow: 0 0 30px rgba(239,68,68,0.6); }
        @keyframes pulseGlow { from { transform: translate(-50%,-50%) scale(1); } to { transform: translate(-50%,-50%) scale(1.05); filter: brightness(1.2); } }

        .header-bar { width:100%; padding:6px 20px; display:flex; justify-content:space-between; align-items:center; background:rgba(0,0,0,0.55); backdrop-filter:blur(15px); border-bottom:2px solid #12c2e9; box-sizing:border-box; }
        .logo-plinko { font-family:'Orbitron',sans-serif; font-size:16px; font-weight:900; color:#12c2e9; letter-spacing:2px; display:flex; align-items:center; gap:8px; }
        .user-money { background:rgba(0,0,0,0.45); padding:4px 14px; border-radius:24px; border:1px solid #12c2e9; font-weight:800; color:#12c2e9; font-size:14px; }

        .game-wrapper { max-width:820px; margin:6px auto; padding:0 10px; width:100%; }
        .glass { background:rgba(15,12,41,0.8); backdrop-filter:blur(20px); border:1px solid rgba(18,194,233,0.25); border-radius:1.4rem; padding:12px 18px; box-shadow:0 15px 40px rgba(0,0,0,0.55); }

        .controls-row { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; justify-content:center; margin-bottom:10px; }
        .ctrl-group { display:flex; flex-direction:column; gap:4px; }
        .ctrl-label { font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; opacity:0.6; }
        .seg-ctrl { display:flex; gap:3px; background:rgba(0,0,0,0.35); padding:3px; border-radius:16px; border:1px solid rgba(255,255,255,0.08); }
        .seg-btn { padding:4px 10px; border-radius:12px; border:none; background:transparent; color:rgba(255,255,255,0.7); font-size:0.75rem; font-weight:700; cursor:pointer; transition:0.2s; }
        .seg-btn:hover { color:#fff; }
        .seg-btn.active { background:#12c2e9; color:#000; box-shadow:0 2px 10px rgba(18,194,233,0.4); }
        .seg-btn.risk-low.active { background:#00b09b; color:#fff; }
        .seg-btn.risk-med.active { background:#f1c40f; color:#000; }
        .seg-btn.risk-high.active { background:#ff4e50; color:#fff; }

        .bet-input { background:rgba(0,0,0,0.45); border:1px solid rgba(18,194,233,0.4); border-radius:8px; padding:5px 10px; color:#fff; font-family:'Orbitron',sans-serif; font-size:0.9rem; font-weight:700; width:110px; outline:none; text-align:center; }
        .quick-bets { display:flex; gap:4px; margin-top:2px; }
        .q-btn { padding:3px 7px; border-radius:10px; border:1px solid rgba(255,255,255,0.15); background:rgba(255,255,255,0.06); color:#fff; font-size:0.7rem; font-weight:700; cursor:pointer; transition:0.2s; }
        .q-btn:hover, .q-btn.active { background:rgba(18,194,233,0.3); border-color:#12c2e9; color:#12c2e9; }

        .btn-drop { padding:9px 24px; border:none; border-radius:30px; background:linear-gradient(135deg,#12c2e9,#a29bfe); color:#fff; font-weight:900; font-size:0.92rem; font-family:'Orbitron',sans-serif; cursor:pointer; transition:0.25s; box-shadow:0 4px 15px rgba(18,194,233,0.4); text-transform:uppercase; letter-spacing:1px; flex-shrink:0; }
        .btn-drop:hover:not(:disabled) { transform:translateY(-2px); filter:brightness(1.15); box-shadow:0 8px 22px rgba(18,194,233,0.6); }
        .btn-drop:disabled { opacity:0.5; cursor:not-allowed; transform:none; }

        /* Board Area Chuẩn Game Gốc */
        .board-container {
            background: rgba(0,0,0,0.45);
            border: 1px solid rgba(18,194,233,0.18);
            border-radius: 1.2rem;
            padding: 10px 10px 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        #plinkoCanvas {
            display: block;
            margin: 0 auto;
            max-width: 100%;
        }

        .pockets-wrapper {
            display: flex;
            justify-content: center;
            gap: 3px;
            margin-top: 4px;
            z-index: 5;
            user-select: none;
        }

        .pocket {
            border-radius: 6px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-family: 'Orbitron', sans-serif;
            font-size: 0.68rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.5);
            transition: transform 0.12s ease, filter 0.12s ease;
            color: #fff;
            text-align: center;
        }
        .pocket.hit {
            transform: translateY(4px) scale(1.15);
            filter: brightness(1.6);
            box-shadow: 0 0 16px #fff;
        }
        .pkt-ultra   { background: #ff0055; color: #fff; }
        .pkt-high    { background: #ff5e00; color: #fff; }
        .pkt-mid     { background: #ffa600; color: #000; }
        .pkt-mid-low { background: #ffdb00; color: #000; }
        .pkt-low     { background: #ffee55; color: #000; }

        .result-log {
            margin-top: 6px;
            font-size: 0.8rem;
            color: rgba(255,255,255,0.85);
            min-height: 24px;
            text-align: center;
        }

        .stats-bar { display:flex; gap:18px; justify-content:center; margin-top:8px; flex-wrap:wrap; }
        .stat-item { text-align:center; }
        .stat-lbl { font-size:0.65rem; opacity:0.5; text-transform:uppercase; letter-spacing:1px; }
        .stat-val { font-family:'Orbitron',sans-serif; font-size:0.95rem; font-weight:800; color:#12c2e9; }
    </style>
</head>
<body>
    <canvas id="threejs-background"></canvas>
    <div id="result-status-badge"><span class="badge-icon">🎉</span><span class="badge-text">HÚP</span></div>

    <header class="header-bar">
        <div class="logo-plinko">🎱 PLINKO V2 PRO</div>
        <div class="user-money">💰 <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span> GTLM</div>
        <div style="font-size:12px; color:#aaa;">STREAMER: <b style="color:#12c2e9;"><?= htmlspecialchars($userName) ?></b></div>
    </header>

    <div class="game-wrapper">
        <div class="glass">
            <!-- Controls Row -->
            <div class="controls-row">
                <div class="ctrl-group">
                    <div class="ctrl-label">Mức rủi ro (Risk)</div>
                    <div class="seg-ctrl" id="riskCtrl">
                        <button class="seg-btn risk-low" data-val="low">LOW</button>
                        <button class="seg-btn risk-med active" data-val="medium">MED</button>
                        <button class="seg-btn risk-high" data-val="high">HIGH</button>
                    </div>
                </div>

                <div class="ctrl-group">
                    <div class="ctrl-label">Số hàng đinh (Rows)</div>
                    <div class="seg-ctrl" id="rowsCtrl">
                        <button class="seg-btn" data-val="8">8</button>
                        <button class="seg-btn active" data-val="12">12</button>
                        <button class="seg-btn" data-val="16">16</button>
                    </div>
                </div>

                <div class="ctrl-group">
                    <div class="ctrl-label">Số bóng thả</div>
                    <div class="seg-ctrl" id="ballsCtrl">
                        <button class="seg-btn active" data-val="1">1</button>
                        <button class="seg-btn" data-val="5">5</button>
                        <button class="seg-btn" data-val="10">10</button>
                        <button class="seg-btn" data-val="25">25</button>
                    </div>
                </div>

                <div class="ctrl-group">
                    <div class="ctrl-label">GTLM cược/bóng</div>
                    <input type="number" id="betAmt" class="bet-input" value="10000" min="1000" step="1000">
                    <div class="quick-bets">
                        <button class="q-btn active" onclick="setBet(10000, this)">10K</button>
                        <button class="q-btn" onclick="setBet(50000, this)">50K</button>
                        <button class="q-btn" onclick="setBet(100000, this)">100K</button>
                        <button class="q-btn" onclick="setBet(500000, this)">500K</button>
                    </div>
                </div>

                <div class="ctrl-group" style="justify-content:flex-end;">
                    <button class="btn-drop" id="dropBtn">🎱 THẢ BÓNG</button>
                </div>
            </div>

            <!-- Plinko Board Area Canvas Thật Chuẩn Game Gốc -->
            <div class="board-container" id="boardContainer">
                <canvas id="plinkoCanvas" width="720" height="340"></canvas>
                <div class="pockets-wrapper" id="pocketsWrapper"></div>
                <div class="result-log" id="resultLog">Bot đang phân tích bảng tỷ lệ, sẵn sàng thả bóng...</div>
            </div>

            <!-- Stats Bar -->
            <div class="stats-bar">
                <div class="stat-item"><div class="stat-lbl">Phiên Húp</div><div class="stat-val" id="sessionWin">0</div></div>
                <div class="stat-item"><div class="stat-lbl">Phiên Bay Màu</div><div class="stat-val" id="sessionLose">0</div></div>
                <div class="stat-item"><div class="stat-lbl">Lợi Nhuận Phiên</div><div class="stat-val" id="sessionProfit">0</div></div>
                <div class="stat-item"><div class="stat-lbl">Mult Cao Nhất</div><div class="stat-val" id="bestMult">-</div></div>
            </div>
        </div>
    </div>

    <script>
        window.themeConfig = {
            particleCount: <?= $particleCount ?? 600 ?>,
            particleSize: <?= $particleSize ?? 0.05 ?>,
            particleColor: '<?= $particleColor ?? "#12c2e9" ?>',
            particleOpacity: <?= $particleOpacity ?? 0.5 ?>,
            shapeCount: <?= $shapeCount ?? 8 ?>,
            shapeColors: <?= json_encode($shapeColors ?? ["#12c2e9","#a29bfe","#fd79a8"]) ?>,
            shapeOpacity: <?= $shapeOpacity ?? 0.3 ?>,
            bgGradient: <?= json_encode($bgGradient ?? ["#0f0c29","#302b63","#24243e"]) ?>
        };
    </script>
    <script src="../threejs-background.js"></script>
    <script src="../assets/js/game-effects.js"></script>
    <script src="../assets/js/game-effects-auto.js"></script>

    <!-- Plinko Physics Canvas Engine -->
    <script>
        const canvas = document.getElementById('plinkoCanvas');
        const ctx = canvas.getContext('2d');
        const pocketsWrapper = document.getElementById('pocketsWrapper');

        const plinkoConfig = <?= json_encode($plinkoConfig) ?>;
        let currentRows = 12;
        let currentRisk = 'medium';

        let pins = [];
        let balls = [];
        let multipliers = [];
        let activeBallsCount = 0;

        let sessionWins = 0, sessionLoses = 0, sessionNet = 0, bestMult = 0;

        const CANVAS_WIDTH = 720;
        let CANVAS_HEIGHT = 340;
        const PIN_RADIUS = 3.5;
        const BALL_RADIUS = 6.5;
        let ROW_SPACING = 22;
        let COL_SPACING = 28;

        function setBet(v, el) {
            $('#betAmt').val(v);
            $('.q-btn').removeClass('active');
            if (el) $(el).addClass('active');
        }

        function setupBoard() {
            multipliers = plinkoConfig[currentRows][currentRisk];
            COL_SPACING = Math.min(38, (CANVAS_WIDTH - 80) / (currentRows + 2));
            ROW_SPACING = COL_SPACING * 0.85;

            CANVAS_HEIGHT = Math.round((currentRows + 1.6) * ROW_SPACING);
            canvas.width = CANVAS_WIDTH;
            canvas.height = CANVAS_HEIGHT;

            pins = [];
            for (let r = 0; r < currentRows; r++) {
                const rowPins = r + 3;
                const startX = CANVAS_WIDTH / 2 - ((rowPins - 1) * COL_SPACING) / 2;
                const y = 25 + r * ROW_SPACING;

                for (let i = 0; i < rowPins; i++) {
                    pins.push({
                        x: startX + i * COL_SPACING,
                        y: y,
                        hitFlash: 0
                    });
                }
            }

            buildPockets();
        }

        function buildPockets() {
            pocketsWrapper.innerHTML = '';
            const pocketWidth = Math.max(20, Math.floor(COL_SPACING - 3));

            multipliers.forEach((mult, i) => {
                const div = document.createElement('div');
                div.className = 'pocket';
                div.style.width = pocketWidth + 'px';
                div.style.height = '28px';
                div.innerText = mult >= 100 ? mult : (mult + 'x');

                if (mult >= 10) div.classList.add('pkt-ultra');
                else if (mult >= 3) div.classList.add('pkt-high');
                else if (mult >= 1.5) div.classList.add('pkt-mid');
                else if (mult >= 1) div.classList.add('pkt-mid-low');
                else div.classList.add('pkt-low');

                div.id = 'pocket-' + i;
                pocketsWrapper.appendChild(div);
            });

            pocketsWrapper.style.width = ((currentRows + 1) * COL_SPACING) + 'px';
        }

        function render() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            // Vẽ đinh với hiệu ứng sáng chớp khi va chạm
            pins.forEach(pin => {
                ctx.beginPath();
                ctx.arc(pin.x, pin.y, PIN_RADIUS, 0, Math.PI * 2);

                if (pin.hitFlash > 0) {
                    ctx.fillStyle = '#ffffff';
                    ctx.shadowBlur = 14;
                    ctx.shadowColor = '#00f2fe';
                    pin.hitFlash -= 0.06;
                } else {
                    ctx.fillStyle = 'rgba(18, 194, 233, 0.65)';
                    ctx.shadowBlur = 4;
                    ctx.shadowColor = 'rgba(18, 194, 233, 0.4)';
                }
                ctx.fill();
                ctx.shadowBlur = 0;
            });

            // Vẽ bóng với vệt sáng đuôi (trail) lấp lánh
            balls.forEach(ball => {
                if (ball.trail && ball.trail.length > 1) {
                    ctx.beginPath();
                    ctx.moveTo(ball.trail[0].x, ball.trail[0].y);
                    for (let i = 1; i < ball.trail.length; i++) {
                        ctx.lineTo(ball.trail[i].x, ball.trail[i].y);
                    }
                    ctx.strokeStyle = 'rgba(251, 191, 36, 0.45)';
                    ctx.lineWidth = 3;
                    ctx.stroke();
                }

                ctx.beginPath();
                ctx.arc(ball.x, ball.y, BALL_RADIUS, 0, Math.PI * 2);
                ctx.fillStyle = '#fbbf24';
                ctx.shadowBlur = 10;
                ctx.shadowColor = '#f59e0b';
                ctx.fill();
                ctx.shadowBlur = 0;
            });

            requestAnimationFrame(render);
        }

        function dropPhysicsBall(data, onBallComplete) {
            activeBallsCount++;

            const ball = {
                x: CANVAS_WIDTH / 2 + (Math.random() * 6 - 3),
                y: -10,
                trail: []
            };
            balls.push(ball);

            const tl = gsap.timeline({
                onComplete: () => {
                    // Ô thưởng nảy lên và phát sáng
                    const pkt = document.getElementById('pocket-' + data.slot);
                    if (pkt) {
                        pkt.classList.add('hit');
                        setTimeout(() => pkt.classList.remove('hit'), 220);
                    }

                    // Xóa bóng khỏi mảng
                    balls = balls.filter(b => b !== ball);
                    activeBallsCount--;

                    if (onBallComplete) onBallComplete(data);
                }
            });

            let curX = CANVAS_WIDTH / 2;
            let curY = 25;

            // Rơi từ đỉnh xuống hàng đinh đầu tiên
            tl.to(ball, {
                y: curY - PIN_RADIUS - BALL_RADIUS,
                duration: 0.18,
                ease: "power1.in"
            });

            // Duyệt từng bước va chạm theo mảng path từ backend
            data.path.forEach((dir, r) => {
                const nextY = 25 + (r + 1) * ROW_SPACING;
                const xOffset = COL_SPACING / 2;
                const nextX = curX + (dir === 1 ? xOffset : -xOffset);

                tl.to(ball, {
                    x: nextX,
                    y: nextY - PIN_RADIUS - BALL_RADIUS,
                    duration: 0.22,
                    ease: "bounce.out",
                    onUpdate: () => {
                        ball.trail.push({ x: ball.x, y: ball.y });
                        if (ball.trail.length > 8) ball.trail.shift();
                    },
                    onStart: () => {
                        // Kích hoạt flash đinh gần nhất
                        pins.forEach(p => {
                            if (Math.abs(p.x - curX) < 12 && Math.abs(p.y - curY) < 12) {
                                p.hitFlash = 1.0;
                            }
                        });
                    }
                });

                curX = nextX;
                curY = nextY;
            });

            // Rơi thẳng vào khe thưởng
            tl.to(ball, {
                x: curX,
                y: CANVAS_HEIGHT + 15,
                duration: 0.16,
                ease: "power2.in"
            });
        }

        function showResultStatus(type, text, icon) {
            const badge = document.getElementById('result-status-badge');
            if (!badge) return;
            badge.className = '';
            badge.classList.add('badge-' + type);
            badge.querySelector('.badge-icon').textContent = icon;
            badge.querySelector('.badge-text').textContent = text;
            badge.style.display = 'flex';
            void badge.offsetWidth;
            badge.classList.add('show');

            if (type === 'win' || type === 'jackpot') {
                if (typeof GameEffects !== 'undefined' && GameEffects.win) GameEffects.win();
                if (typeof confetti === 'function') confetti({
                    particleCount: type === 'jackpot' ? 150 : 80,
                    spread: 65,
                    origin: { y: 0.55 },
                    colors: ['#12c2e9','#a29bfe','#fbbf24']
                });
            } else {
                if (typeof GameEffects !== 'undefined' && GameEffects.lose) GameEffects.lose();
            }

            setTimeout(() => {
                badge.classList.remove('show');
                setTimeout(() => { badge.style.display = 'none'; }, 400);
            }, 3000);
        }

        // Tương tác nút Controls
        $('#riskCtrl .seg-btn').click(function() {
            $('#riskCtrl .seg-btn').removeClass('active');
            $(this).addClass('active');
            currentRisk = $(this).data('val');
            setupBoard();
        });

        $('#rowsCtrl .seg-btn').click(function() {
            $('#rowsCtrl .seg-btn').removeClass('active');
            $(this).addClass('active');
            currentRows = parseInt($(this).data('val'));
            setupBoard();
        });

        $('#ballsCtrl .seg-btn').click(function() {
            $('#ballsCtrl .seg-btn').removeClass('active');
            $(this).addClass('active');
        });

        // Xử lý nút Thả Bóng
        $('#dropBtn').click(function() {
            if ($(this).prop('disabled')) return;

            const bet   = parseInt($('#betAmt').val()) || 10000;
            const risk  = $('#riskCtrl .seg-btn.active').data('val') || 'medium';
            const rows  = parseInt($('#rowsCtrl .seg-btn.active').data('val')) || 12;
            const balls = parseInt($('#ballsCtrl .seg-btn.active').data('val')) || 1;

            const btn = $(this);
            btn.prop('disabled', true).text('⏳ Đang thả...');
            $('#resultLog').text(`🎱 Đang thả ${balls} bóng vào trận địa Plinko...`);

            $.post('?action=drop', { bet, risk, rows, balls }, function(res) {
                if (!res.success) {
                    $('#resultLog').html('❌ <span style="color:#f87171">' + res.message + '</span>');
                    btn.prop('disabled', false).text('🎱 THẢ BÓNG');
                    return;
                }

                $('#balance-val').text(res.money);

                let completedBalls = 0;
                let maxMult = 0;

                res.results.forEach((ballData, idx) => {
                    if (ballData.mult > maxMult) maxMult = ballData.mult;

                    setTimeout(() => {
                        dropPhysicsBall(ballData, (d) => {
                            completedBalls++;

                            if (completedBalls === res.results.length) {
                                // Toàn bộ bóng đã hạ cánh
                                btn.prop('disabled', false).text('🎱 THẢ BÓNG');

                                if (maxMult > bestMult) {
                                    bestMult = maxMult;
                                    $('#bestMult').text('x' + maxMult);
                                }

                                sessionNet += res.sessionNet;
                                if (res.sessionNet > 0) {
                                    sessionWins++;
                                    $('#sessionWin').text(sessionWins);
                                    if (maxMult >= 20) {
                                        showResultStatus('jackpot', '👑 SIÊU HÚP x' + maxMult + '! +' + res.sessionNet.toLocaleString('vi-VN') + ' GTLM', '👑');
                                    } else {
                                        showResultStatus('win', '🎉 HÚP +' + res.sessionNet.toLocaleString('vi-VN') + ' GTLM', '🎉');
                                    }
                                } else {
                                    sessionLoses++;
                                    $('#sessionLose').text(sessionLoses);
                                    showResultStatus('lose', '😢 BAY MÀU ' + Math.abs(res.sessionNet).toLocaleString('vi-VN') + ' GTLM', '😢');
                                }

                                $('#sessionProfit')
                                    .css('color', sessionNet >= 0 ? '#34d399' : '#f87171')
                                    .text((sessionNet >= 0 ? '+' : '') + sessionNet.toLocaleString('vi-VN'));

                                $('#resultLog').html('🎱 Thả <b>' + balls + '</b> bóng — Cược: <b>' + res.totalBet.toLocaleString('vi-VN') + '</b> | Thắng: <b>' + res.totalWin.toLocaleString('vi-VN') + '</b> | Mult cao nhất: <b style="color:#fbbf24">x' + maxMult + '</b>');
                            }
                        });
                    }, idx * 160);
                });
            }, 'json').fail(function() {
                btn.prop('disabled', false).text('🎱 THẢ BÓNG');
                $('#resultLog').text('⚠️ Kết nối gián đoạn, tự phục hồi bàn cược.');
            });
        });

        // Khởi chạy bàn Plinko
        setupBoard();
        requestAnimationFrame(render);
    </script>

    <!-- Nạp Bot Streamer Virtual Cursor & Logic Bot 59 -->
    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="bots/bot_59.js"></script>
</body>
</html>
