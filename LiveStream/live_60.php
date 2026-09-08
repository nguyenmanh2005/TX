<?php
session_start();

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../game_history_helper.php';
require_once __DIR__ . '/bot_streamer_helper.php';

$botUser = getOrCreateBotStreamerUser($conn, 'bot_60', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

$useBotTheme = $botUserId;
require_once __DIR__ . '/../load_theme.php';

$userId = $botUserId;

// Lấy thông tin người dùng và số dư
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user ? (float)$user['Money'] : 50000000;
$userName = $user ? $user['Name'] : 'bot_60';
$stmt->close();

// Fallback theme Hoàng Gia V3
$particleColor = $particleColor ?? '#fbbf24';
$shapeColors   = $shapeColors   ?? ['#fbbf24', '#f59e0b', '#a78bfa', '#ef4444'];
$bgGradient    = $bgGradient    ?? ['#0f0a00', '#1a1200', '#0a0010'];
if (empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, ' . $bgGradient[0] . ' 0%, ' . $bgGradient[1] . ' 50%, ' . ($bgGradient[2] ?? $bgGradient[1]) . ' 100%)';
}

// Bảng cấu hình Hệ số nhân chuẩn xác từ api_plinko_royale_v3.php
$royaleConfig = [
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

// AJAX Action Handler
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];

    if ($action === 'config') {
        echo json_encode([
            'success'  => true,
            'config'   => $royaleConfig,
            'balance'  => $money,
            'username' => $userName
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'drop') {
        $totalBet  = (float)($_POST['bet'] ?? 10000);
        $ballCount = (int)($_POST['ballCount'] ?? 10);
        $rows      = (int)($_POST['rows'] ?? 16);
        $risk      = $_POST['risk'] ?? 'high';

        if ($totalBet < 1000) $totalBet = 10000;
        if ($ballCount < 1) $ballCount = 1;
        if ($ballCount > 100) $ballCount = 100;
        if (!isset($royaleConfig[$rows][$risk])) {
            $rows = 16;
            $risk = 'high';
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $locked = $res ? (float)$res['Money'] : 0;
            $stmt->close();

            // Tự động nạp thêm tiền cho bot nếu cần
            if ($locked < $totalBet) {
                $conn->query("UPDATE users SET Money = 50000000 WHERE Iduser = $userId");
                $locked = 50000000;
            }

            // Trừ tiền cược
            $conn->query("UPDATE users SET Money = Money - {$totalBet} WHERE Iduser = {$userId}");

            $multipliers = $royaleConfig[$rows][$risk];
            $betPerBall = $totalBet / $ballCount;
            $results = [];
            $totalWin = 0;
            $maxMultHit = 0;
            $jackpotSlot = false;

            for ($b = 0; $b < $ballCount; $b++) {
                $path = [];
                $slot = 0;
                for ($i = 0; $i < $rows; $i++) {
                    $dir = rand(0, 1);
                    $path[] = $dir;
                    if ($dir === 1) $slot++;
                }
                $slot = min($slot, count($multipliers) - 1);
                $mult = $multipliers[$slot];
                if ($mult > $maxMultHit) $maxMultHit = $mult;
                if ($mult >= 100) $jackpotSlot = true;

                $win = round($betPerBall * $mult);
                $totalWin += $win;

                $results[] = [
                    'path'       => $path,
                    'slot'       => $slot,
                    'multiplier' => $mult,
                    'winAmount'  => $win
                ];
            }

            if ($totalWin > 0) {
                $conn->query("UPDATE users SET Money = Money + {$totalWin} WHERE Iduser = {$userId}");
            }

            $profit = $totalWin - $totalBet;
            $newMoney = $locked - $totalBet + $totalWin;

            // Ghi lịch sử history_plinko
            $resStr = "R:$rows|Risk:$risk|Balls:$ballCount|MaxX:$maxMultHit";
            $his = $conn->prepare("INSERT INTO history_plinko (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
            if ($his) {
                $his->bind_param("idss", $userId, $totalBet, $resStr, $profit);
                $his->execute();
                $his->close();
            }

            // Ghi log game_history theo Rule 5.2
            if (function_exists('logGameHistoryWithAll')) {
                logGameHistoryWithAll($conn, $userId, 'Plinko Royale V3', $totalBet, $totalWin, $totalWin > $totalBet);
            } else if (function_exists('logGameHistory')) {
                logGameHistory($conn, $userId, 'Plinko Royale V3', $totalBet, $totalWin, $totalWin > $totalBet);
            }

            $conn->commit();

            echo json_encode([
                'success'    => true,
                'results'    => $results,
                'totalBet'   => $totalBet,
                'totalWin'   => $totalWin,
                'newBalance' => $newMoney,
                'money'      => number_format($newMoney, 0, ',', '.'),
                'maxMult'    => $maxMultHit,
                'jackpot'    => $jackpotSlot
            ], JSON_UNESCAPED_UNICODE);
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
    <title>Plinko Royale V3 - Bàn Live 60</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/plinko-royale-v3.css">
    <link rel="stylesheet" href="../assets/css/sound-fx-hub.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700;900&family=Outfit:wght@400;600;700;800;900&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script src="../assets/js/sound-fx-hub.js"></script>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        #threejs-background {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: -1; pointer-events: none;
        }

        /* Top Header */
        .live-header-bar {
            width: 100%;
            padding: 8px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(10, 15, 29, 0.85);
            backdrop-filter: blur(15px);
            border-bottom: 2px solid #fbbf24;
            box-shadow: 0 4px 20px rgba(0,0,0,0.6);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .logo-royale {
            font-family: 'Cinzel Decorative', serif;
            font-size: 16px;
            font-weight: 900;
            color: #fbbf24;
            letter-spacing: 2px;
            text-shadow: 0 0 14px rgba(251,191,36,0.6);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .user-money {
            background: rgba(0, 0, 0, 0.6);
            padding: 5px 16px;
            border-radius: 24px;
            border: 1px solid #fbbf24;
            font-weight: 800;
            color: #fbbf24;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 0 12px rgba(251,191,36,0.25);
        }

        /* Main Game Layout adapted for Stream Viewport */
        .plinko-main-grid {
            display: grid;
            grid-template-columns: 290px 1fr;
            gap: 16px;
            max-width: 1180px;
            width: 100%;
            margin: 12px auto;
            padding: 0 16px;
        }

        @media (max-width: 900px) {
            .plinko-main-grid { grid-template-columns: 1fr; }
        }

        .plinko-panel {
            background: rgba(15, 23, 42, 0.88);
            border: 1px solid rgba(251, 191, 36, 0.35);
            border-radius: 20px;
            padding: 18px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.6);
            height: fit-content;
        }

        .plinko-panel h3 {
            color: #fbbf24;
            font-size: 15px;
            font-weight: 800;
            margin: 0 0 14px 0;
            border-bottom: 1px dashed rgba(251,191,36,0.3);
            padding-bottom: 8px;
            letter-spacing: 1px;
            display: flex; align-items: center; gap: 6px;
        }

        .form-group-royale { margin-bottom: 14px; }
        .form-group-royale label {
            display: block;
            font-size: 11px;
            color: #fde047;
            font-weight: 800;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .segment-group {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
        }
        .segment-btn {
            background: #0f172a;
            border: 1px solid #334155;
            color: #94a3b8;
            font-size: 12px;
            font-weight: 700;
            padding: 8px 0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.25s;
            font-family: 'Outfit', sans-serif;
        }
        .segment-btn:hover { color: #fff; border-color: #fbbf24; }
        .segment-btn.active {
            background: linear-gradient(135deg, #f59e0b, #b45309);
            color: #fff;
            border-color: #fde047;
            box-shadow: 0 3px 12px rgba(245, 158, 11, 0.45);
            font-weight: 800;
        }
        .segment-btn[data-risk="high"].active {
            background: linear-gradient(135deg, #ef4444, #991b1b);
            border-color: #fca5a5;
            box-shadow: 0 3px 12px rgba(239, 68, 68, 0.55);
        }

        .ball-count-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 4px;
        }

        .bet-input-wrapper {
            display: flex;
            align-items: center;
            background: #090d16;
            border: 2px solid #334155;
            border-radius: 12px;
            overflow: hidden;
        }
        .bet-input-wrapper input {
            background: transparent;
            border: none;
            color: #fbbf24;
            font-size: 16px;
            font-weight: 900;
            padding: 8px 12px;
            width: 100%;
            outline: none;
            font-family: 'Orbitron', sans-serif;
            text-align: center;
        }

        .bet-quick-btns {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 4px;
            margin-top: 6px;
        }
        .btn-bet-quick {
            background: rgba(51, 65, 85, 0.7);
            border: 1px solid #475569;
            color: #e2e8f0;
            font-size: 11px;
            font-weight: 800;
            padding: 6px 0;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.2s;
            text-align: center;
        }
        .btn-bet-quick:hover, .btn-bet-quick.active {
            background: #fbbf24;
            color: #000;
            border-color: #fde047;
            box-shadow: 0 2px 8px rgba(251,191,36,0.4);
        }

        .btn-drop-main {
            width: 100%;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border: 2px solid #fde047;
            color: #000;
            font-weight: 900;
            font-size: 15px;
            padding: 13px 6px;
            border-radius: 14px;
            cursor: pointer;
            box-shadow: 0 6px 24px rgba(245, 158, 11, 0.6);
            transition: all 0.25s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-family: 'Outfit', sans-serif;
            white-space: nowrap;
        }
        .btn-drop-main:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(245, 158, 11, 0.8);
        }
        .btn-drop-main:disabled {
            background: #475569 !important;
            border-color: #64748b !important;
            color: #94a3b8 !important;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Stage Canvas Container */
        .plinko-stage-wrapper {
            background: rgba(10, 15, 29, 0.92);
            border: 2px solid rgba(251, 191, 36, 0.35);
            border-radius: 24px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            box-shadow: inset 0 0 40px rgba(0,0,0,0.8), 0 15px 40px rgba(0,0,0,0.6);
        }

        .session-profit-bar {
            display: flex;
            justify-content: space-around;
            align-items: center;
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px;
            padding: 8px 16px;
            width: 100%;
            margin-bottom: 12px;
        }
        .session-stat { text-align: center; }
        .session-label { display: block; font-size: 10px; color: #94a3b8; font-weight: 800; text-transform: uppercase; }
        .session-value { font-size: 14px; font-weight: 900; font-family: 'Orbitron', sans-serif; }
        .session-divider { width: 1px; height: 24px; background: rgba(255,255,255,0.1); }

        canvas#plinkoCanvas {
            max-width: 100%;
            height: auto;
            border-radius: 14px;
            background: radial-gradient(circle at 50% 10%, #1e293b 0%, #090d16 80%);
            display: block;
        }

        /* Win Toast */
        #winToastContainer {
            position: absolute;
            top: 60px;
            right: 20px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            pointer-events: none;
            z-index: 80;
        }
        .win-toast {
            background: rgba(15, 23, 42, 0.92);
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 6px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
            animation: toastIn 0.25s ease-out forwards;
        }
        .win-toast.toast-jackpot {
            border-color: #fbbf24;
            background: linear-gradient(135deg, rgba(239,68,68,0.9), rgba(180,83,9,0.9));
            box-shadow: 0 0 20px rgba(251,191,36,0.6);
        }
        .win-toast.toast-big {
            border-color: #f59e0b;
            background: rgba(30, 41, 59, 0.95);
        }
        @keyframes toastIn {
            from { transform: translateX(30px); opacity: 0; }
            to   { transform: translateX(0); opacity: 1; }
        }
        @keyframes toastOut {
            from { transform: translateX(0); opacity: 1; }
            to   { transform: translateX(30px); opacity: 0; }
        }

        /* Celebration Overlay */
        .royale-celebration-overlay {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(10px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 99999;
        }
        .royale-celebration-overlay.active { display: flex; }
        .royale-celebration-box {
            background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%);
            border: 3px solid #fbbf24;
            border-radius: 28px;
            padding: 32px 48px;
            text-align: center;
            box-shadow: 0 0 80px rgba(251,191,36,0.8);
            animation: celPop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            max-width: 500px;
        }
        @keyframes celPop {
            0% { transform: scale(0.6); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>
    <canvas id="threejs-background"></canvas>

    <!-- Header Bar -->
    <header class="live-header-bar">
        <div class="logo-royale">👑 PLINKO ROYALE V3 MULTI-DROP</div>
        <div class="user-money">💰 <span id="plinkoBalance"><?= number_format($money, 0, ',', '.') ?> GTLM</span></div>
        <div style="font-size:12px; color:#aaa;">STREAMER: <b style="color:#fbbf24;"><?= htmlspecialchars($userName) ?></b></div>
    </header>

    <!-- Main Game Grid -->
    <main class="plinko-main-grid">
        <!-- Col 1: Bảng Điều Khiển Cược -->
        <aside class="plinko-panel">
            <h3>⚡ BẢNG ĐIỀU KHIỂN CƯỢC</h3>

            <div class="form-group-royale">
                <label>1. Số Hàng Đinh Chốt (Rows)</label>
                <div class="segment-group" id="rowsGroup">
                    <button class="segment-btn btn-row-select" data-rows="8">8 Hàng</button>
                    <button class="segment-btn btn-row-select" data-rows="12">12 Hàng</button>
                    <button class="segment-btn btn-row-select active" data-rows="16">16 Hàng 🔥</button>
                </div>
            </div>

            <div class="form-group-royale">
                <label>2. Mức Độ Rủi Ro (Risk)</label>
                <div class="segment-group" id="riskGroup">
                    <button class="segment-btn btn-risk-select" data-risk="low">An Toàn</button>
                    <button class="segment-btn btn-risk-select" data-risk="medium">Royale Vàng</button>
                    <button class="segment-btn btn-risk-select active" data-risk="high">X1000 👑</button>
                </div>
            </div>

            <div class="form-group-royale">
                <label>3. Số Lượng Bóng Thả (Multi-Drop)</label>
                <div class="ball-count-grid" id="ballsGroup">
                    <button class="segment-btn btn-ball-select" data-count="1">1</button>
                    <button class="segment-btn btn-ball-select active" data-count="10">10</button>
                    <button class="segment-btn btn-ball-select" data-count="25">25</button>
                    <button class="segment-btn btn-ball-select" data-count="50">50 🔥</button>
                    <button class="segment-btn btn-ball-select" style="color:#fde047;" data-count="100">100 💥</button>
                </div>
            </div>

            <div class="form-group-royale">
                <label>4. Tổng Số GTLM Cược</label>
                <div class="bet-input-wrapper">
                    <input type="number" id="betInput" value="10000" min="1000" step="1000">
                </div>
                <div class="bet-quick-btns" id="betQuickBtns">
                    <button class="btn-bet-quick active" data-bet="10000">10K</button>
                    <button class="btn-bet-quick" data-bet="50000">50K</button>
                    <button class="btn-bet-quick" data-bet="100000">100K</button>
                    <button class="btn-bet-quick" data-bet="500000">500K</button>
                    <button class="btn-bet-quick" data-bet="1000000">1M</button>
                </div>
            </div>

            <div style="margin-top:20px;">
                <button id="btnDropMain" class="btn-drop-main" onclick="PlinkoRoyaleV3.triggerDrop()">
                    💥 THẢ BÓNG ROYALE
                </button>
            </div>
        </aside>

        <!-- Col 2: Sân Khấu Plinko Canvas Physics 60 FPS -->
        <section class="plinko-stage-wrapper">
            <!-- Session Profit Bar -->
            <div class="session-profit-bar" id="sessionProfitBar">
                <div class="session-stat">
                    <span class="session-label">💰 CỬA CƯỢC</span>
                    <span class="session-value" id="sessionBetEl">0 GTLM</span>
                </div>
                <div class="session-divider"></div>
                <div class="session-stat">
                    <span class="session-label">🎯 TỔNG THẮNG</span>
                    <span class="session-value" id="sessionWinEl" style="color:#34d399;">0 GTLM</span>
                </div>
                <div class="session-divider"></div>
                <div class="session-stat">
                    <span class="session-label">📈 LỢI NHUẬN</span>
                    <span class="session-value" id="sessionProfitEl" style="color:#94a3b8;">0 GTLM</span>
                </div>
            </div>

            <!-- Canvas Stage 60 FPS -->
            <canvas id="plinkoCanvas" width="760" height="580"></canvas>

            <!-- Win Toasts -->
            <div id="winToastContainer"></div>
        </section>
    </main>

    <!-- Royale Celebration Modal Overlay -->
    <div class="royale-celebration-overlay" id="royaleCelebrationOverlay" onclick="this.classList.remove('active')">
        <div class="royale-celebration-box">
            <div style="font-size:64px; margin-bottom:10px;">👑💥🎉</div>
            <h2 id="celMultText" style="font-size:32px; color:#fde047; margin:0 0 10px 0; font-weight:900;">X1000 ROYALE JACKPOT!</h2>
            <p style="color:#f8fafc; font-size:15px; margin:0 0 18px 0;">Bùng nổ trận địa Plinko Royale V3 với số tiền thắng siêu khủng:</p>
            <div id="celWinText" style="font-size:36px; font-weight:900; color:#34d399; margin-bottom:16px;">+10,000,000 GTLM</div>
            <div style="font-size:12px; color:#94a3b8;">(Click vào bất kỳ đâu để đóng)</div>
        </div>
    </div>

    <!-- ThreeJS Config -->
    <script>
        window.themeConfig = {
            particleCount: <?= $particleCount ?? 800 ?>,
            particleSize: <?= $particleSize ?? 0.05 ?>,
            particleColor: '<?= $particleColor ?? "#fbbf24" ?>',
            particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
            shapeCount: <?= $shapeCount ?? 10 ?>,
            shapeColors: <?= json_encode($shapeColors ?? ["#fbbf24","#f59e0b","#a78bfa","#ef4444"]) ?>,
            shapeOpacity: <?= $shapeOpacity ?? 0.35 ?>,
            bgGradient: <?= json_encode($bgGradient ?? ["#0f0a00","#1a1200","#0a0010"]) ?>
        };
    </script>
    <script src="../threejs-background.js"></script>

    <!-- Plinko Royale V3 Canvas Engine -->
    <script>
        const PlinkoRoyaleV3 = (() => {
            let canvas, ctx;
            let config = <?= json_encode($royaleConfig) ?>;
            let currentRows = 16;
            let currentRisk = 'high';
            let currentBallCount = 10;
            let currentBet = 10000;
            let isDropping = false;
            let activeBalls = [];
            let pegs = [];
            let slotBoxes = [];
            let slotHitTimer = [];
            let particles = [];
            let floatingTexts = [];
            let animId = null;

            // Session tracking
            let sessionTotalBet = 0;
            let sessionTotalWin = 0;
            let lastToastThrottle = 0;

            function init() {
                canvas = document.getElementById('plinkoCanvas');
                if (!canvas) return;
                ctx = canvas.getContext('2d');
                canvas.width = 760;
                canvas.height = 580;

                setupEventListeners();
                buildStage();
                startPhysicsLoop();
            }

            function setupEventListeners() {
                // Rows select
                $('.btn-row-select').click(function() {
                    if (isDropping) return;
                    $('.btn-row-select').removeClass('active');
                    $(this).addClass('active');
                    currentRows = parseInt($(this).data('rows'));
                    buildStage();
                });

                // Risk select
                $('.btn-risk-select').click(function() {
                    if (isDropping) return;
                    $('.btn-risk-select').removeClass('active');
                    $(this).addClass('active');
                    currentRisk = $(this).data('risk');
                    buildStage();
                });

                // Ball count select
                $('.btn-ball-select').click(function() {
                    if (isDropping) return;
                    $('.btn-ball-select').removeClass('active');
                    $(this).addClass('active');
                    currentBallCount = parseInt($(this).data('count'));
                });

                // Bet quick buttons
                $('.btn-bet-quick').click(function() {
                    if (isDropping) return;
                    $('.btn-bet-quick').removeClass('active');
                    $(this).addClass('active');
                    const b = parseInt($(this).data('bet'));
                    $('#betInput').val(b);
                    currentBet = b;
                });

                $('#betInput').on('input change', function() {
                    currentBet = parseInt($(this).val()) || 10000;
                    $('.btn-bet-quick').removeClass('active');
                    $(`.btn-bet-quick[data-bet="${currentBet}"]`).addClass('active');
                });
            }

            function buildStage() {
                pegs = [];
                slotBoxes = [];
                const topY = 40;
                const bottomY = 500;
                const rowHeight = (bottomY - topY) / currentRows;
                const centerX = canvas.width / 2;

                const colSpacing = Math.min(38, (canvas.width - 60) / (currentRows + 2));

                for (let r = 0; r < currentRows; r++) {
                    const pegsInRow = r + 3;
                    const rowWidth = (pegsInRow - 1) * colSpacing;
                    const startX = centerX - rowWidth / 2;
                    const y = topY + r * rowHeight;

                    for (let c = 0; c < pegsInRow; c++) {
                        const x = startX + c * colSpacing;
                        pegs.push({ x, y, radius: 3.5, row: r, col: c });
                    }
                }

                // Calculate slot boxes directly under bottom pegs
                if (config[currentRows] && config[currentRows][currentRisk]) {
                    const mults = config[currentRows][currentRisk];
                    const bottomPegsInRow = currentRows + 2;
                    const bottomRowWidth = (bottomPegsInRow - 1) * colSpacing;
                    const bottomStartX = centerX - bottomRowWidth / 2;
                    const slotWidth = Math.max(20, Math.floor(colSpacing - 3));

                    for (let i = 0; i <= currentRows; i++) {
                        const gapCenterX = bottomStartX + i * colSpacing + colSpacing / 2;
                        slotBoxes.push({
                            idx: i,
                            mult: mults[i] || 1,
                            x: gapCenterX - slotWidth / 2,
                            y: bottomY + 12,
                            width: slotWidth,
                            height: 38,
                            centerX: gapCenterX
                        });
                    }
                }
            }

            // Physics 60 FPS Loop
            function startPhysicsLoop() {
                function loop() {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);

                    // 1. Draw Pegs with Golden Glow
                    pegs.forEach(p => {
                        ctx.beginPath();
                        ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
                        ctx.fillStyle = '#fbbf24';
                        ctx.shadowColor = '#f59e0b';
                        ctx.shadowBlur = 6;
                        ctx.fill();
                        ctx.shadowBlur = 0;
                    });

                    // 2. Draw Multiplier Buckets inside Canvas
                    slotBoxes.forEach(sb => {
                        const hitAlpha = slotHitTimer[sb.idx] || 0;
                        if (slotHitTimer[sb.idx] > 0) slotHitTimer[sb.idx] -= 0.05;

                        let bgGrad = ctx.createLinearGradient(sb.x, sb.y, sb.x, sb.y + sb.height);
                        let textColor = '#fff';
                        let borderColor = '#334155';

                        if (sb.mult >= 100) {
                            bgGrad.addColorStop(0, hitAlpha > 0 ? '#fef08a' : '#ef4444');
                            bgGrad.addColorStop(1, '#7f1d1d');
                            textColor = '#fef08a';
                            borderColor = hitAlpha > 0 ? '#ffffff' : '#f59e0b';
                        } else if (sb.mult >= 10) {
                            bgGrad.addColorStop(0, hitAlpha > 0 ? '#fef08a' : '#f59e0b');
                            bgGrad.addColorStop(1, '#92400e');
                            textColor = '#000';
                            borderColor = hitAlpha > 0 ? '#ffffff' : '#fbbf24';
                        } else if (sb.mult >= 2) {
                            bgGrad.addColorStop(0, hitAlpha > 0 ? '#d8b4fe' : '#8b5cf6');
                            bgGrad.addColorStop(1, '#4c1d95');
                            textColor = '#fff';
                            borderColor = hitAlpha > 0 ? '#ffffff' : '#a855f7';
                        } else {
                            bgGrad.addColorStop(0, hitAlpha > 0 ? '#64748b' : '#1e293b');
                            bgGrad.addColorStop(1, '#0f172a');
                            textColor = '#94a3b8';
                            borderColor = hitAlpha > 0 ? '#ffffff' : '#334155';
                        }

                        ctx.save();
                        if (hitAlpha > 0) {
                            ctx.shadowColor = sb.mult >= 10 ? '#fbbf24' : '#38bdf8';
                            ctx.shadowBlur = 22;
                            ctx.translate(sb.centerX, sb.y + sb.height / 2);
                            ctx.scale(1 + hitAlpha * 0.16, 1 + hitAlpha * 0.16);
                            ctx.translate(-sb.centerX, -(sb.y + sb.height / 2));
                        }

                        ctx.beginPath();
                        if (typeof ctx.roundRect === 'function') {
                            ctx.roundRect(sb.x, sb.y, sb.width, sb.height, 6);
                        } else {
                            ctx.rect(sb.x, sb.y, sb.width, sb.height);
                        }
                        ctx.fillStyle = bgGrad;
                        ctx.fill();
                        ctx.lineWidth = hitAlpha > 0 ? 2.5 : 1.2;
                        ctx.strokeStyle = borderColor;
                        ctx.stroke();

                        ctx.fillStyle = textColor;
                        ctx.font = 'bold 11px Orbitron, Outfit, sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText(`${sb.mult}x`, sb.centerX, sb.y + sb.height / 2);
                        ctx.restore();
                    });

                    // 3. Update & Draw Balls
                    for (let i = activeBalls.length - 1; i >= 0; i--) {
                        const ball = activeBalls[i];
                        updateBallPhysics(ball);
                        drawBall(ball);

                        if (ball.finished) {
                            activeBalls.splice(i, 1);
                        }
                    }

                    // 4. Update & Draw Particle Explosions
                    for (let i = particles.length - 1; i >= 0; i--) {
                        const p = particles[i];
                        p.x += p.vx;
                        p.y += p.vy;
                        p.vy += 0.22;
                        p.alpha -= p.life;

                        if (p.alpha <= 0) {
                            particles.splice(i, 1);
                            continue;
                        }

                        ctx.save();
                        ctx.globalAlpha = p.alpha;
                        ctx.beginPath();
                        ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
                        ctx.fillStyle = p.color;
                        ctx.shadowColor = p.color;
                        ctx.shadowBlur = 8;
                        ctx.fill();
                        ctx.restore();
                    }

                    // 5. Update & Draw Floating Popups
                    for (let i = floatingTexts.length - 1; i >= 0; i--) {
                        const ft = floatingTexts[i];
                        ft.y -= ft.vy;
                        ft.alpha -= ft.life || 0.014;

                        if (ft.alpha <= 0) {
                            floatingTexts.splice(i, 1);
                            continue;
                        }

                        ctx.save();
                        ctx.globalAlpha = Math.min(1, ft.alpha);
                        ctx.font = `900 ${ft.fontSize}px Outfit, sans-serif`;
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.shadowColor = '#000';
                        ctx.shadowBlur = 10;
                        ctx.fillStyle = '#000';
                        ctx.fillText(ft.text, ft.x + 1, ft.y + 1);
                        ctx.shadowBlur = 0;
                        ctx.fillStyle = ft.color;
                        ctx.shadowColor = ft.color;
                        ctx.shadowBlur = 14;
                        ctx.fillText(ft.text, ft.x, ft.y);
                        ctx.restore();
                    }

                    // Auto release Drop button when all balls complete
                    if (activeBalls.length === 0 && isDropping) {
                        isDropping = false;
                        const btn = document.getElementById('btnDropMain');
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '💥 THẢ BÓNG ROYALE';
                        }
                    }

                    animId = requestAnimationFrame(loop);
                }
                loop();
            }

            function drawBall(ball) {
                // Ball Trail
                if (ball.trail && ball.trail.length > 1) {
                    ctx.beginPath();
                    ctx.moveTo(ball.trail[0].x, ball.trail[0].y);
                    for (let i = 1; i < ball.trail.length; i++) {
                        ctx.lineTo(ball.trail[i].x, ball.trail[i].y);
                    }
                    ctx.strokeStyle = ball.color || 'rgba(251, 191, 36, 0.4)';
                    ctx.lineWidth = 2.5;
                    ctx.stroke();
                }

                ctx.beginPath();
                ctx.arc(ball.x, ball.y, ball.radius, 0, Math.PI * 2);

                const grad = ctx.createRadialGradient(ball.x - 2, ball.y - 2, 1, ball.x, ball.y, ball.radius);
                if (ball.isJackpot) {
                    grad.addColorStop(0, '#ffffff');
                    grad.addColorStop(0.5, '#ef4444');
                    grad.addColorStop(1, '#991b1b');
                    ctx.shadowColor = '#ef4444';
                    ctx.shadowBlur = 14;
                } else {
                    grad.addColorStop(0, '#ffffff');
                    grad.addColorStop(0.5, ball.color || '#fbbf24');
                    grad.addColorStop(1, '#b45309');
                    ctx.shadowColor = ball.color || '#fbbf24';
                    ctx.shadowBlur = 8;
                }

                ctx.fillStyle = grad;
                ctx.fill();
                ctx.shadowBlur = 0;
            }

            function updateBallPhysics(ball) {
                const topY = 40;
                const bottomY = 500;
                const rowHeight = (bottomY - topY) / currentRows;
                const colSpacing = Math.min(38, (canvas.width - 60) / (currentRows + 2));

                if (ball.currentRow < currentRows) {
                    const targetRowY = topY + ball.currentRow * rowHeight;

                    ball.vy += 0.48; // Gravity
                    ball.y += ball.vy;

                    // Keep X smoothly aligned with peg slots
                    const expectedTargetX = (canvas.width / 2) + (ball.currentSlot * 2 - ball.currentRow) * (colSpacing / 2);
                    ball.x += (expectedTargetX - ball.x) * 0.3;

                    // Update trail
                    ball.trail.push({ x: ball.x, y: ball.y });
                    if (ball.trail.length > 7) ball.trail.shift();

                    if (ball.y >= targetRowY) {
                        const dir = ball.path[ball.currentRow];
                        ball.currentRow++;
                        if (dir === 1) ball.currentSlot++;
                        ball.vy = -ball.vy * 0.26;
                        ball.vx = dir === 0 ? -1.3 : 1.3;

                        if (typeof SoundFXHub !== 'undefined') SoundFXHub.playPop();
                    }
                } else {
                    // Final vertical drop directly into the target bucket center
                    const targetBox = slotBoxes[ball.destinationSlot];
                    const targetX = targetBox ? targetBox.centerX : (canvas.width / 2);

                    ball.vy += 0.58;
                    ball.y += ball.vy;
                    ball.x += (targetX - ball.x) * 0.35;

                    ball.trail.push({ x: ball.x, y: ball.y });
                    if (ball.trail.length > 5) ball.trail.shift();

                    if (ball.y >= bottomY + 18) {
                        ball.finished = true;
                        handleBallLand(ball);
                    }
                }
            }

            function handleBallLand(ball) {
                slotHitTimer[ball.destinationSlot] = 1.0;
                const targetBox = slotBoxes[ball.destinationSlot] || { centerX: canvas.width / 2, y: 515 };
                const mult = ball.multiplier;
                const win = ball.winAmount;

                // 1. Spawn Upward Particle Explosion
                const numParticles = mult >= 100 ? 50 : (mult >= 10 ? 35 : (mult >= 2 ? 22 : 12));
                const pColors = mult >= 100 ? ['#fde047', '#ef4444', '#ffffff', '#fbbf24', '#f97316'] :
                    (mult >= 10 ? ['#fbbf24', '#f59e0b', '#ffffff', '#fde047'] : ['#38bdf8', '#a855f7', '#ffffff', '#818cf8']);

                for (let p = 0; p < numParticles; p++) {
                    const angle = -Math.PI / 2 + (Math.random() - 0.5) * Math.PI * 1.1;
                    const speed = Math.random() * 7 + 3;
                    particles.push({
                        x: targetBox.centerX + (Math.random() * 12 - 6),
                        y: targetBox.y + 6,
                        vx: Math.cos(angle) * speed,
                        vy: Math.sin(angle) * speed,
                        radius: Math.random() * 3.5 + 2,
                        color: pColors[Math.floor(Math.random() * pColors.length)],
                        alpha: 1.0,
                        life: Math.random() * 0.025 + 0.02
                    });
                }

                // 2. Floating Popup Text
                let popupText = `+${new Intl.NumberFormat('vi-VN').format(win)}`;
                if (mult >= 100) popupText = `💥 X${mult}!`;
                else if (mult >= 10) popupText = `🔥 X${mult}: +${new Intl.NumberFormat('vi-VN').format(win)}`;

                floatingTexts.push({
                    x: targetBox.centerX,
                    y: targetBox.y - 8,
                    text: popupText,
                    color: mult >= 100 ? '#fde047' : (mult >= 2 ? '#34d399' : '#94a3b8'),
                    fontSize: mult >= 100 ? 22 : (mult >= 10 ? 17 : 13),
                    alpha: 1.0,
                    vy: mult >= 10 ? 2.0 : 1.4,
                    life: 0.013
                });

                // 3. Update session
                sessionTotalWin += win;
                updateSessionUI();

                // 4. Show Win Toast (throttled)
                const now = Date.now();
                if (now - lastToastThrottle > 220 || mult >= 2) {
                    lastToastThrottle = now;
                    showWinToast(mult, win);
                }

                // 5. Sound and Royale celebration
                if (mult >= 100) {
                    if (typeof SoundFXHub !== 'undefined') {
                        SoundFXHub.playJackpot();
                        SoundFXHub.playBossRoar();
                    }
                    if (typeof confetti === 'function') {
                        confetti({ particleCount: 200, spread: 90, origin: { y: 0.6 }, colors: ['#fbbf24','#ef4444','#fff'] });
                    }
                    triggerRoyaleCelebration(mult, win);
                } else if (mult >= 2) {
                    if (typeof SoundFXHub !== 'undefined') SoundFXHub.playLotteryWin();
                } else {
                    if (typeof SoundFXHub !== 'undefined') SoundFXHub.playPop();
                }
            }

            function updateSessionUI() {
                const betEl    = document.getElementById('sessionBetEl');
                const winEl    = document.getElementById('sessionWinEl');
                const profitEl = document.getElementById('sessionProfitEl');
                if (!betEl) return;

                const profit = sessionTotalWin - sessionTotalBet;
                betEl.textContent    = new Intl.NumberFormat('vi-VN').format(sessionTotalBet) + ' GTLM';
                winEl.textContent    = new Intl.NumberFormat('vi-VN').format(sessionTotalWin) + ' GTLM';
                profitEl.textContent = (profit >= 0 ? '+' : '') + new Intl.NumberFormat('vi-VN').format(profit) + ' GTLM';
                profitEl.style.color = profit > 0 ? '#34d399' : (profit < 0 ? '#ef4444' : '#94a3b8');
            }

            function showWinToast(mult, winAmount) {
                const container = document.getElementById('winToastContainer');
                if (!container) return;

                while (container.children.length >= 5) {
                    container.removeChild(container.firstChild);
                }

                const toast = document.createElement('div');
                let cls = 'win-toast';
                let icon = '🎱';
                if (mult >= 100) { cls += ' toast-jackpot'; icon = '👑💥'; }
                else if (mult >= 10)  { cls += ' toast-big'; icon = '🔥'; }
                else if (mult >= 2)   { icon = '✨'; }

                toast.className = cls;
                toast.innerHTML = `
                    <span>${icon}</span>
                    <div>
                        <div style="font-weight:800; color:#fbbf24;">X${mult} (${currentRows}H / ${currentRisk.toUpperCase()})</div>
                        <div style="font-weight:900; color:${winAmount > 0 ? '#34d399' : '#94a3b8'};">+${new Intl.NumberFormat('vi-VN').format(winAmount)} GTLM</div>
                    </div>
                `;
                container.appendChild(toast);

                setTimeout(() => {
                    toast.style.animation = 'toastOut 0.25s ease forwards';
                    setTimeout(() => toast.remove(), 250);
                }, 3000);
            }

            function triggerRoyaleCelebration(mult, win) {
                const overlay = document.getElementById('royaleCelebrationOverlay');
                if (!overlay) return;
                document.getElementById('celMultText').textContent = `X${mult} ROYALE JACKPOT!`;
                document.getElementById('celWinText').textContent = `+${new Intl.NumberFormat('vi-VN').format(win)} GTLM`;
                overlay.classList.add('active');
                setTimeout(() => overlay.classList.remove('active'), 5000);
            }

            const BALL_COLORS = ['#fbbf24', '#f59e0b', '#ef4444', '#ec4899', '#a855f7', '#38bdf8'];

            // Trigger Drop
            async function triggerDrop() {
                if (isDropping) return;
                const btn = document.getElementById('btnDropMain');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '⏳ ĐANG THẢ ' + currentBallCount + ' BÓNG...';
                }
                isDropping = true;

                // Anti-stuck watchdog (unlocks drop button after max 8.5 seconds guaranteed)
                setTimeout(() => {
                    if (isDropping && activeBalls.length === 0) {
                        isDropping = false;
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '💥 THẢ BÓNG ROYALE';
                        }
                    }
                }, 8500);

                sessionTotalBet = currentBet;
                sessionTotalWin = 0;
                updateSessionUI();

                const formData = new FormData();
                formData.append('bet', currentBet);
                formData.append('ballCount', currentBallCount);
                formData.append('rows', currentRows);
                formData.append('risk', currentRisk);

                try {
                    const res = await fetch('live_60.php?action=drop', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();

                    if (!data.success) {
                        isDropping = false;
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '💥 THẢ BÓNG ROYALE';
                        }
                        return;
                    }

                    $('#plinkoBalance').text(data.money + ' GTLM');

                    const topY = 25;
                    const centerX = canvas.width / 2;
                    const staggerDelay = Math.max(30, Math.min(100, Math.floor(1800 / data.results.length)));

                    data.results.forEach((r, idx) => {
                        setTimeout(() => {
                            activeBalls.push({
                                x: centerX + (Math.random() * 8 - 4),
                                y: topY,
                                vx: 0,
                                vy: 2.2,
                                radius: 7,
                                path: r.path,
                                destinationSlot: r.slot,
                                multiplier: r.multiplier,
                                winAmount: r.winAmount,
                                isJackpot: r.multiplier >= 100,
                                color: BALL_COLORS[idx % BALL_COLORS.length],
                                currentRow: 0,
                                currentSlot: 0,
                                finished: false,
                                trail: []
                            });
                        }, idx * staggerDelay);
                    });
                } catch (e) {
                    console.error('[Plinko Royale V3] Drop error:', e);
                    isDropping = false;
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '💥 THẢ BÓNG ROYALE NGAY';
                    }
                }
            }

            return {
                init,
                triggerDrop,
                getIsDropping: () => isDropping
            };
        })();

        $(document).ready(() => {
            PlinkoRoyaleV3.init();
        });
    </script>

    <!-- Nạp Chuột Ảo và Logic Bot 60 -->
    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="bots/bot_60.js"></script>
</body>
</html>
