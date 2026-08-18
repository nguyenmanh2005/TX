<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_minesweeper', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;




require '../db_connect.php';

// AJAX history endpoint
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_history') {
    header('Content-Type: application/json; charset=utf-8');
    
    $id = $botUserId ?? 0;
    $sql = "SELECT * FROM history_minesweeper WHERE Iduser = ? ORDER BY Time DESC LIMIT 20";
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
$sqlStats = "SELECT COUNT(*) as total, SUM(CASE WHEN WinAmount > 0 THEN 1 ELSE 0 END) as wins FROM history_minesweeper WHERE Iduser = ?";
$stmtStats = $conn->prepare($sqlStats);
$stmtStats->bind_param("i", $userId);
$stmtStats->execute();
$resultStats = $stmtStats->get_result();
if ($rowStats = $resultStats->fetch_assoc()) {
    $gameThang = $rowStats['wins'] ?? 0;
    $gameThua = ($rowStats['total'] ?? 0) - $gameThang;
}
$stmtStats->close();


$soDu = $user['Money'];
$tenNguoiChoi = $user['Name'];

// AJAX handler
// Multipliers table
$mineMultipliers = [
    1 => 1.1, 2 => 1.3, 3 => 1.5, 4 => 1.7, 
    5 => 2.0, 
    6 => 2.4, 7 => 2.9, 8 => 3.5, 9 => 4.2, 
    10 => 5.0, 
    11 => 5.8, 12 => 6.7, 13 => 7.7, 14 => 8.8, 
    15 => 10.0, 
    16 => 15.0, 17 => 25.0, 18 => 40.0, 19 => 60.0, 
    20 => 80.0, 21 => 90.0, 
    22 => 100.0
];

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false, 'message' => ''];

    if ($action === 'new_game') {
        $board = array_fill(0, 25, 0);
        $mines = [];
        while (count($mines) < 3) {
            $pos = rand(0, 24);
            if (!in_array($pos, $mines)) {
                $mines[] = $pos;
                $board[$pos] = -1;
            }
        }
        $_SESSION['mines_board'] = $board;
        $_SESSION['mines_revealed'] = [];
        $_SESSION['mines_cuoc'] = 0;
        $response = [
            'success' => true,
            'message' => '🆕 Ván mới bắt đầu! Hãy thả thính.',
            'newBalance' => number_format($soDu, 0, ',', '.') . ' gtlm',
            'board' => array_fill(0, 25, '?')
        ];
    } elseif ($action === 'start') {
        $cuoc = (int) ($_POST['cuoc'] ?? 0);
        if ($cuoc <= 0 || $cuoc > $soDu) {
            $response['message'] = '⚠️ Số GTLM muốn chiến không hợp lệ hoặc không đủ vốn!';
        } else {
            $_SESSION['mines_cuoc'] = $cuoc;
            $soDu -= $cuoc;
            $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
            $capNhat->bind_param("di", $soDu, $userId);
            $capNhat->execute();
            $response = [
                'success' => true,
                'message' => '🎯 Đã ra chiêu ' . number_format($cuoc, 0, ',', '.') . ' GTLM! Chúc may mắn!',
                'newBalance' => number_format($soDu, 0, ',', '.') . ' gtlm'
            ];
        }
    } elseif ($action === 'reveal') {
        $cell = (int) ($_POST['cell'] ?? -1);
        if ($_SESSION['mines_cuoc'] <= 0) {
            $response['message'] = '⚠️ Hãy thả thính trước khi mở ô!';
        } elseif (in_array($cell, $_SESSION['mines_revealed'])) {
            $response['message'] = '⚠️ Ô này đã được mở!';
        } elseif ($cell < 0 || $cell >= 25) {
            $response['message'] = '⚠️ Ô không hợp lệ!';
        } else {
            $_SESSION['mines_revealed'][] = $cell;
            $board = $_SESSION['mines_board'];

            if ($board[$cell] === -1) {
                // Thua
                // Track quest progress + Game of Day + Combo Streak + Random Events
                require_once '../game_history_helper.php';
                logGameHistoryWithAll($conn, $userId, 'Minesweeper', $_SESSION['mines_cuoc'], 0, false);

                $historyStmt = $conn->prepare("INSERT INTO history_minesweeper (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, 'Thua', 0, NOW())");
                $historyStmt->bind_param("ii", $userId, $_SESSION['mines_cuoc']);
                $historyStmt->execute();

                $response = [
                    'success' => true,
                    'isGameOver' => true,
                    'isWin' => false,
                    'message' => '💣 BÙM! Bạn đã trúng mìn!',
                    'cellValue' => '💣',
                    'newBalance' => number_format($soDu, 0, ',', '.') . ' gtlm'
                ];
                // Reset session sau khi thua
                $_SESSION['mines_cuoc'] = 0;
            } else {
                // An toàn hoặc Thắng
                $safeCount = count($_SESSION['mines_revealed']);
                $totalSafe = 22;
                $currentMult = $mineMultipliers[$safeCount] ?? 1.0;

                if ($safeCount >= $totalSafe) {
                    $thang = $_SESSION['mines_cuoc'] * $mineMultipliers[22];
                    $soDu += $thang;
                    $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
                    $capNhat->bind_param("di", $soDu, $userId);
                    $capNhat->execute();

                    // Track quest progress + Game of Day + Combo Streak + Random Events
                    require_once '../game_history_helper.php';
                    logGameHistoryWithAll($conn, $userId, 'Minesweeper', $_SESSION['mines_cuoc'], $thang, true);

                    $historyStmt = $conn->prepare("INSERT INTO history_minesweeper (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, 'Thắng x100', ?, NOW())");
                    $historyStmt->bind_param("iii", $userId, $_SESSION['mines_cuoc'], $thang);
                    $historyStmt->execute();

                    $response = [
                        'success' => true,
                        'isGameOver' => true,
                        'isWin' => true,
                        'message' => '🎉 CHIẾN THẮNG! Bạn đã mở hết và nhận ' . number_format($thang, 0, ',', '.') . ' gtlm (x100)!',
                        'cellValue' => '💎',
                        'newBalance' => number_format($soDu, 0, ',', '.') . ' gtlm'
                    ];
                    $_SESSION['mines_cuoc'] = 0;
                } else {
                    $response = [
                        'success' => true,
                        'isGameOver' => false,
                        'message' => '✅ An toàn! Đang ở mức x' . $currentMult,
                        'cellValue' => '💎',
                        'currentMult' => $currentMult,
                        'potentialWin' => $_SESSION['mines_cuoc'] * $currentMult
                    ];
                }
            }
        }
    } elseif ($action === 'cashout') {
        if ($_SESSION['mines_cuoc'] <= 0) {
            $response['message'] = '⚠️ Ván chơi chưa bắt đầu hoặc đã kết thúc!';
        } else {
            $safeCount = count($_SESSION['mines_revealed'] ?? []);
            if ($safeCount == 0) {
                $response['message'] = '⚠️ Bạn phải mở ít nhất 1 ô trước khi rút!';
            } else {
                $currentMult = $mineMultipliers[$safeCount] ?? 1.0;
                $thang = $_SESSION['mines_cuoc'] * $currentMult;
                $soDu += $thang;
                $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
                $capNhat->bind_param("di", $soDu, $userId);
                $capNhat->execute();

                require_once '../game_history_helper.php';
                logGameHistoryWithAll($conn, $userId, 'Minesweeper', $_SESSION['mines_cuoc'], $thang, true);

                $historyStmt = $conn->prepare("INSERT INTO history_minesweeper (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
                $resStr = "Cashout x$currentMult";
                $historyStmt->bind_param("iisi", $userId, $_SESSION['mines_cuoc'], $resStr, $thang);
                $historyStmt->execute();

                $response = [
                    'success' => true,
                    'isGameOver' => true,
                    'isWin' => true,
                    'message' => '💰 Rút thành công! Bạn nhận được ' . number_format($thang, 0, ',', '.') . ' gtlm (x' . $currentMult . ')',
                    'newBalance' => number_format($soDu, 0, ',', '.') . ' gtlm'
                ];
                $_SESSION['mines_cuoc'] = 0;
            }
        }
    }
    echo json_encode($response);
    exit;
}

// Khởi tạo ván đầu tiên nếu chưa có
if (!isset($_SESSION['mines_board'])) {
    $board = array_fill(0, 25, 0);
    $mines = [];
    while (count($mines) < 3) {
        $pos = rand(0, 24);
        if (!in_array($pos, $mines)) {
            $mines[] = $pos;
            $board[$pos] = -1;
        }
    }
    $_SESSION['mines_board'] = $board;
    $_SESSION['mines_revealed'] = [];
    $_SESSION['mines_cuoc'] = 0;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Dò Mìn - AJAX Edition</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/canvas-confetti/1.6.0/confetti.browser.min.js"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <link rel="stylesheet" href="../assets/css/game-effects.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <style>
        body {
            position: relative;
            cursor: url('../img/chuot.png'), auto !important;
            font-family: 'Segoe UI', sans-serif;
            text-align: center;
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            padding: 50px;
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
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

        .game-box {
            background: rgba(40, 44, 52, 0.95);
            padding: 30px;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            width: 95%;
            max-width: 600px;
            color: white;
            z-index: 1;
        }

        .game-title {
            font-size: 32px;
            margin-bottom: 20px;
            font-weight: 800;
            color: #fff;
        }

        .balance {
            font-size: 20px;
            color: #ffd700;
            margin-bottom: 30px;
        }

        .mines-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-bottom: 30px;
        }

        .mine-cell {
            aspect-ratio: 1;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            transition: all 0.3s;
            cursor: url('../img/tay.png'), pointer !important;
        }

        .mine-cell:hover:not(.revealed):not(.mine) {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .mine-cell.revealed {
            background: rgba(40, 167, 69, 0.3);
            border-color: #28a745;
            color: #28a745;
        }

        .mine-cell.mine {
            background: rgba(220, 53, 69, 0.3);
            border-color: #dc3545;
            animation: shake 0.5s;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            20% {
                transform: translateX(-5px);
            }

            40% {
                transform: translateX(5px);
            }

            60% {
                transform: translateX(-5px);
            }

            80% {
                transform: translateX(5px);
            }
        }

        input[type="number"] {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 12px 20px;
            border-radius: 12px;
            color: white;
            font-size: 18px;
            width: 80%;
            margin-bottom: 20px;
            text-align: center;
        }

        .btn-game {
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 700;
            border: none;
            color: white;
            transition: 0.3s;
            cursor: url('../img/tay.png'), pointer !important;
        }

        .btn-start {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        }

        .btn-new {
            background: rgba(255, 255, 255, 0.1);
        }

        .btn-game:hover {
            transform: translateY(-2px);
            filter: brightness(1.2);
        }

        .btn-game:disabled {
            opacity: 0.5;
            cursor: not-allowed !important;
        }

        .thongbao {
            margin-top: 25px;
            padding: 15px;
            border-radius: 12px;
            font-weight: 600;
            min-height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .thongbao.thang {
            background: rgba(40, 167, 69, 0.1);
            color: #4ade80;
        }

        .thongbao.thua {
            background: rgba(220, 53, 69, 0.1);
            color: #ff6b6b;
        }

        .home-link {
            color: rgba(255, 255, 255, 0.5);
            text-decoration: none;
            font-size: 14px;
            margin-top: 20px;
            display: inline-block;
        }
    
        /* Statistics Container */
        .stats-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .stat-item.wins {
            border-left: 4px solid #4ade80;
        }
        
        .stat-item.losses {
            border-left: 4px solid #ff6b6b;
        }
        
        .stat-item .label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        
        .stat-item .value {
            font-size: 28px;
            font-weight: 700;
            color: #ffd700;
        }
        
        .chart-box {
            display: flex;
            flex-direction: column;
        }
        
        .chart-box canvas {
            margin-top: 20px;
        }

        .btn-quick-bet {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: url('../img/tay.png'), pointer !important;
            font-weight: 600;
            transition: 0.3s;
            font-size: 0.75rem;
        }

        .btn-quick-bet:hover {
            background: #28a745;
            color: #fff;
            border-color: #28a745;
        }

    </style>
</head>

<body>


    <div class="game-box">
        <h1 class="game-title">💣 Dò Mìn AJAX</h1>
        <div class="balance">💰 Số Gtlm: <b id="balance-val"><?= number_format($soDu, 0, ',', '.') ?> gtlm</b></div>

        <div class="mines-grid" id="mines-grid">
            <?php for ($i = 0; $i < 25; $i++): ?>
                <button class="mine-cell" data-cell="<?= $i ?>"></button>
            <?php endfor; ?>
        </div>

        <div id="bet-section">
            <input type="number" id="bet-amount" placeholder="GTLM muốn chiến (GTLM)" min="1" max="<?= $soDu ?>">
            <div class="quick-bets" style="display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 20px; justify-content: center;">
                <button class="btn-quick-bet" onclick="setBet(10000)">10K</button>
                <button class="btn-quick-bet" onclick="setBet(50000)">50K</button>
                <button class="btn-quick-bet" onclick="setBet(100000)">100K</button>
                <button class="btn-quick-bet" onclick="setBet(500000)">500K</button>
                <button class="btn-quick-bet" onclick="setBet(1000000)">1M</button>
                <button class="btn-quick-bet" onclick="setBet(5000000)">5M</button>
                <button class="btn-quick-bet" onclick="setBet('ALLIN')" style="background: #dc3545; color:#fff; border:none; font-weight:800;">ALL IN</button>
            </div>
            <div style="display: flex; justify-content: center; gap: 10px;">
                <button id="btn-start" class="btn-game btn-start">🎯 Thả thính</button>
                <button id="btn-cashout" class="btn-game" style="background: #f39c12; display: none;">💰 Rút (x1.0)</button>
                <button id="btn-new" class="btn-game btn-new">🆕 Làm mới</button>
            </div>
        </div>

        <div id="status-box" class="thongbao">Sẵn sàng! Hãy thả thính ngay.</div>

        <a href="../index.php" class="home-link">🏠 Quay lại trang chủ</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function setBet(amount) {
            const balanceText = document.getElementById('balance-val').textContent.replace(/[^0-9]/g, '');
            const money = parseInt(balanceText) || 0;
            if (amount === 'ALLIN') {
                document.getElementById('bet-amount').value = money;
            } else {
                document.getElementById('bet-amount').value = amount;
            }
        }

        // Three.js Background
        (function () {
            window.themeConfig = {
                particleCount: <?= $particleCount ?>,
                particleSize: <?= $particleSize ?>,
                particleColor: '<?= $particleColor ?>',
                particleOpacity: <?= $particleOpacity ?>,
                shapeCount: <?= $shapeCount ?>,
                shapeColors: <?= json_encode($shapeColors) ?>,
                shapeOpacity: <?= $shapeOpacity ?>,
                bgGradient: <?= json_encode($bgGradient) ?>
            };
            const script = document.createElement('script');
            script.src = '../threejs-background.js';
            document.head.appendChild(script);
        })();

        document.addEventListener('DOMContentLoaded', function () {
            const grid = document.getElementById('mines-grid');
            const cells = document.querySelectorAll('.mine-cell');
            const btnStart = document.getElementById('btn-start');
            const btnCashout = document.getElementById('btn-cashout');
            const btnNew = document.getElementById('btn-new');
            const statusBox = document.getElementById('status-box');
            const balanceVal = document.getElementById('balance-val');
            const betAmount = document.getElementById('bet-amount');

            let isGameActive = false;

            // Reset UI
            function resetUI(boardData) {
                cells.forEach((cell, idx) => {
                    cell.className = 'mine-cell';
                    cell.textContent = '';
                    cell.disabled = false;
                });
                isGameActive = false;
                betAmount.disabled = false;
                btnStart.style.display = 'inline-block';
                btnCashout.style.display = 'none';
                btnStart.disabled = false;
            }

            // Action Start
            btnStart.addEventListener('click', async () => {
                const amount = betAmount.value;
                if (!amount || amount <= 0) return Swal.fire('Lỗi', 'Vui lòng nhập gtlm cược!', 'error');

                try {
                    const formData = new FormData();
                    formData.append('cuoc', amount);
                    const res = await fetch('?action=start', { method: 'POST', body: formData });
                    const data = await res.json();

                    if (data.success) {
                        isGameActive = true;
                        betAmount.disabled = true;
                        btnStart.style.display = 'none';
                        btnCashout.style.display = 'inline-block';
                        btnCashout.textContent = '💰 Rút (x1.0)';
                        balanceVal.textContent = data.newBalance;
                        statusBox.textContent = data.message;
                        statusBox.className = 'thongbao';
                    } else {
                        Swal.fire('Thông báo', data.message, 'warning');
                    }
                } catch (e) {
                    console.error(e);
                }
            });

            // Action Cashout
            btnCashout.addEventListener('click', async () => {
                if (!isGameActive) return;
                try {
                    const res = await fetch('?action=cashout', { method: 'POST' });
                    const data = await res.json();
                    
                    if (data.success) {
                        isGameActive = false;
                        btnStart.style.display = 'inline-block';
                        btnCashout.style.display = 'none';
                        statusBox.textContent = data.message;
                        statusBox.className = 'thongbao thang';
                        balanceVal.textContent = data.newBalance;
                        
                        if (typeof confetti === 'function') {
                            confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } });
                        }
                        Swal.fire('Thắng rồi!', data.message, 'success');
                    } else {
                        Swal.fire('Lỗi', data.message, 'error');
                    }
                } catch(e) {
                    console.error(e);
                }
            });

            // Action New Game
            btnNew.addEventListener('click', async () => {
                try {
                    const res = await fetch('?action=new_game');
                    const data = await res.json();
                    if (data.success) {
                        resetUI();
                        statusBox.textContent = data.message;
                        statusBox.className = 'thongbao';
                    }
                } catch (e) {
                    console.error(e);
                }
            });

            // Action Reveal
            grid.addEventListener('click', async (e) => {
                const cell = e.target.closest('.mine-cell');
                if (!cell || !isGameActive || cell.classList.contains('revealed')) return;

                const cellIdx = cell.dataset.cell;
                try {
                    const formData = new FormData();
                    formData.append('cell', cellIdx);
                    const res = await fetch('?action=reveal', { method: 'POST', body: formData });
                    const data = await res.json();

                    if (data.success) {
                        cell.textContent = data.cellValue;
                        cell.classList.add(data.cellValue === '💣' ? 'mine' : 'revealed');
                        statusBox.textContent = data.message;
                        
                        if (data.currentMult) {
                            btnCashout.textContent = `💰 Rút (x${data.currentMult})`;
                        }

                        if (data.isGameOver) {
                            isGameActive = false;
                            btnStart.style.display = 'inline-block';
                            btnCashout.style.display = 'none';
                            statusBox.className = 'thongbao ' + (data.isWin ? 'thang' : 'thua');
                            balanceVal.textContent = data.newBalance;

                            if (data.isWin && typeof confetti === 'function') {
                                confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } });
                            }

                            setTimeout(() => {
                                Swal.fire(data.isWin ? 'Thắng rồi!' : 'Bùm!', data.message, data.isWin ? 'success' : 'error');
                                if (!data.isWin) btnNew.click(); // Auto reset nếu thua
                            }, 500);
                        }
                    } else {
                        Swal.fire('Hệ thống', data.message, 'info');
                    }
                } catch (e) {
                    console.error(e);
                }
            });
        });
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
                
                // Exclude common navigation/help buttons
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
