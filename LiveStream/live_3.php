<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_3', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

include '../db_connect.php';
require_once '../include_css.php';
$useBotTheme = $botUserId;
include '../load_theme.php';
require_once '../game_history_helper.php';
require_once '../dynamic_event_helper.php';
if (!isset($botUserId)) {
    header('Location: ../login.php');
    exit;
}
$userId = $botUserId;
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$userName = $user['Name'];
$stmt->close();
// Auto-create history table
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false];
    if ($action === 'start') {
        $rawBet = $_POST['bet'] ?? 0;
        // FIX: Chống Array Injection & Validate dữ liệu
        if (is_array($rawBet) || !is_numeric($rawBet)) {
            $response['message'] = "Dữ liệu cược không hợp lệ!";
            echo json_encode($response); exit;
        }
        $bet = (float)$rawBet;
        $minBet = 1000; // FIX: Chấp nhận Bet cực nhỏ
        if ($bet < $minBet) {
            $response['message'] = "Mức cược tối thiểu là " . number_format($minBet) . " gtlm!";
        } else {
            $conn->begin_transaction();
            try {
                // FIX: Sử dụng Prepared Statement cho an toàn tuyệt đối
                $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $userData = $stmt->get_result()->fetch_assoc();
                if (!$userData || $userData['Money'] < $bet) throw new Exception("Không đủ  Gtlm!");
                // Trừ  Gtlm
                $stmt = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
                $stmt->bind_param("di", $bet, $userId);
                $stmt->execute();
                $instantCrash = rand(1, 100) <= 5;
                if ($instantCrash) {
                    $crashPoint = 1.00;
                } else {
                    $e = 100 / (rand(1, 1000000) / 10000);
                    $crashPoint = max(1.01, round($e * 0.96, 2));
                }
                $_SESSION['crash_game'] = [
                    'bet' => $bet,
                    'crashPoint' => $crashPoint,
                    'status' => 'active',
                    'start_time' => microtime(true)
                ];
                $conn->commit();
                $newMoney = $userData['Money'] - $bet;
                $response = [
                    'success' => true,
                    'money' => number_format($newMoney, 0, ',', '.')
                ];
            } catch (Exception $e) {
                $conn->rollback();
                $response['message'] = $e->getMessage();
            }
        }
    } elseif ($action === 'cashout') {
        $multiplier = (float) ($_POST['multiplier'] ?? 1.0);
        if (!isset($_SESSION['crash_game']) || $_SESSION['crash_game']['status'] !== 'active') {
            $response['message'] = "Phiên chơi không tồn tại!";
        } else {
            $game = $_SESSION['crash_game'];
            $elapsed = microtime(true) - $game['start_time'];
            $serverMult = pow(1.005, ($elapsed * 1000) / 50);
            if ($multiplier > $serverMult + 0.5) {
                $response['message'] = "Dữ liệu không khớp!";
            } elseif ($multiplier > $game['crashPoint']) {
                $response['message'] = "Đã nổ! Bạn không kịp rút Gtlm.";
                $response['crashPoint'] = $game['crashPoint'];
            } else {
                $conn->begin_transaction();
                try {
                    // Khóa bản ghi để đảm bảo tính nhất quán
                    $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $stmt->get_result();
                    $eventMult = DynamicEventHelper::getModifier($conn, 'crash');
                    $winAmount = round($game['bet'] * $multiplier * $eventMult);
                    $updateStmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
                    $updateStmt->bind_param("di", $winAmount, $userId);
                    $updateStmt->execute();
                    $resStr = "Cashout at x$multiplier";
                    $profit = $winAmount - $game['bet'];
                    $his = $conn->prepare("INSERT INTO history_crash (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
                    $his->bind_param("idid", $userId, $game['bet'], $resStr, $profit);
                    $his->execute();
                    logGameHistoryWithAll($conn, $userId, 'Crash', $game['bet'], $winAmount, true);
                    $conn->commit();
                    // Lấy số dư mới sau khi commit
                    $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $newMoney = $stmt->get_result()->fetch_assoc()['Money'];
                    $response = [
                        'success' => true, 
                        'winAmount' => number_format($winAmount, 0, ',', '.'), 
                        'winAmountRaw' => $winAmount, 
                        'money' => number_format($newMoney, 0, ',', '.')
                    ];
                    unset($_SESSION['crash_game']);
                } catch (Exception $e) {
                    $conn->rollback();
                    $response['message'] = "Lỗi hệ thống quyết toán!";
                }
            }
        }
    } elseif ($action === 'check') {
        if (isset($_SESSION['crash_game']) && $_SESSION['crash_game']['status'] === 'active') {
            $game = $_SESSION['crash_game'];
            $elapsed = microtime(true) - $game['start_time'];
            $currentMult = pow(1.005, ($elapsed * 1000) / 50);
            if ($currentMult >= $game['crashPoint']) {
                $response = ['success' => true, 'crashed' => true, 'crashPoint' => $game['crashPoint']];
            } else {
                $response = ['success' => true, 'crashed' => false];
            }
        }
    } elseif ($action === 'lost') {
        if (isset($_SESSION['crash_game'])) {
            $game = $_SESSION['crash_game'];
            $his = $conn->prepare("INSERT INTO history_crash (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
            $negBet = -$game['bet'];
            $resStr = "Crashed at x" . $game['crashPoint'];
            $his->bind_param("idss", $userId, $game['bet'], $resStr, $negBet);
            $his->execute();
            logGameHistoryWithAll($conn, $userId, 'Crash', $game['bet'], 0, false);
            unset($_SESSION['crash_game']);
            $response = ['success' => true];
        }
    } elseif ($action === 'get_balance') {
        $stmtB = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
        $stmtB->bind_param("i", $userId);
        $stmtB->execute();
        $resB = $stmtB->get_result()->fetch_assoc();
        $stmtB->close();
        $response = ['success' => true, 'money' => number_format($resB['Money'] ?? 0, 0, ',', '.')];
    }
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Crash Flight Premium - Vegas Royale</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <!-- Post-processing for Bloom effect -->
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/postprocessing/EffectComposer.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/postprocessing/RenderPass.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/postprocessing/ShaderPass.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/shaders/CopyShader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/shaders/LuminosityHighPassShader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/postprocessing/UnrealBloomPass.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <style>
        :root {
            --primary: #ff4757;
            --accent: #f1c40f;
            --glass: rgba(255, 255, 255, 0.08);
        }
        * {
            box-sizing: border-box;
            scrollbar-width: none !important;
        }
        ::-webkit-scrollbar {
            display: none !important;
        }
        body {
            margin: 0;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Inter', sans-serif;
            overflow: hidden;
            cursor: url('../img/chuot.png'), auto !important;
            height: 100vh;
            width: 100vw;
        }
        .main-container {
            height: 100vh;
            width: 100vw;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 15px;
            box-sizing: border-box;
            overflow: hidden;
        }
        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1.5rem;
            padding: 1rem 1.2rem;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.6);
            width: 100%;
            max-width: 1020px;
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 1rem;
            max-height: 94vh;
            align-self: center;
        }
        .crash-area {
            position: relative;
            width: 100%;
            min-height: 380px;
            background: radial-gradient(circle at center, #0a0a1a 0%, #05050a 100%);
            border-radius: 1.2rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #crash-3d-container {
            position: absolute;
            inset: 0;
            z-index: 1;
        }
        #crash-graph-canvas {
            position: absolute;
            inset: 0;
            z-index: 5;
            pointer-events: none;
            opacity: 0.6;
        }
        /* HUD Multiplier - Moved to Top */
        .mult-wrapper {
            position: absolute;
            top: 25px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            background: radial-gradient(circle, rgba(0,242,254,0.05) 0%, transparent 70%);
            padding: 10px;
        }
        .multiplier-display {
            font-size: 4rem;
            font-weight: 900;
            font-family: 'Orbitron', sans-serif;
            position: relative;
            z-index: 11;
            color: #fff;
            text-shadow: 0 0 20px rgba(0, 242, 254, 0.6);
            line-height: 1;
        }
        .btn-quick-bet {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 5px 2px;
            border-radius: 6px;
            cursor: url('../img/tay.png'), pointer !important;
            font-weight: 600;
            transition: 0.3s;
            font-size: 0.7rem;
        }
        .btn-quick-bet:hover {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
        .multiplier-glow {
            position: absolute;
            font-size: 4.2rem;
            font-weight: 900;
            font-family: 'Orbitron', sans-serif;
            z-index: 10;
            filter: blur(25px);
            opacity: 0.4;
            color: var(--primary);
            pointer-events: none;
            white-space: nowrap;
        }
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            position: relative;
            z-index: 100;
            justify-content: space-between;
        }
        .btn-action {
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            border: none;
            font-weight: 900;
            font-size: 1.15rem;
            cursor: url('../img/tay.png'), pointer !important;
            transition: 0.3s;
            text-transform: uppercase;
            position: relative;
            overflow: hidden;
        }
        .btn-action::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transform: translateX(-100%);
            transition: 0.5s;
        }
        .btn-action:hover::after {
            transform: translateX(100%);
        }
        #startBtn {
            background: linear-gradient(135deg, var(--primary), #ff6b81);
            color: #fff;
            box-shadow: 0 10px 25px rgba(255, 71, 87, 0.4);
        }
        #cashoutBtn {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: #fff;
            display: none;
            box-shadow: 0 10px 25px rgba(46, 204, 113, 0.4);
        }
        .input-group {
            background: rgba(0, 0, 0, 0.3);
            padding: 0.5rem 0.8rem;
            border-radius: 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: 0.3s;
        }
        .input-group:focus-within {
            border-color: var(--primary);
            background: rgba(255, 71, 87, 0.05);
        }
        .input-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            opacity: 0.6;
            margin-bottom: 3px;
            text-transform: uppercase;
        }
        .input-group input {
            width: 100%;
            background: transparent;
            border: none;
            color: #fff;
            font-size: 1.1rem;
            font-weight: 800;
            font-family: 'Orbitron', sans-serif;
            outline: none;
        }
        .switch input:checked+.slider {
            background-color: var(--primary);
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 14px;
            width: 14px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        .switch input:checked+.slider:before {
            transform: translateX(14px);
        }
        #autoCashout:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="glass-card">
            <div class="sidebar">
                <div>
                    <h1 style="margin:0; font-size: 1.8rem; font-weight: 900; color: var(--primary); font-family: 'Orbitron'; text-shadow: 0 0 15px rgba(255,71,87,0.3);">
                        CRASH</h1>
                    <p style="margin:0; opacity:0.4; font-size: 0.7rem; letter-spacing: 2px;">Vegas Royale Premium 3D</p>
                </div>
                <form id="gameForm" onsubmit="return false;" style="margin-top: 0.2rem;">
                    <div class="input-group">
                        <label>GTLM cược (GTLM)</label>
                        <input type="number" id="betAmount" value="10000" min="1000">
                        <div class="quick-bets" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; margin-top: 6px;">
                            <button class="btn-quick-bet" type="button" onclick="setBet(10000)">10K</button>
                            <button class="btn-quick-bet" type="button" onclick="setBet(50000)">50K</button>
                            <button class="btn-quick-bet" type="button" onclick="setBet(100000)">100K</button>
                            <button class="btn-quick-bet" type="button" onclick="setBet(500000)">500K</button>
                            <button class="btn-quick-bet" type="button" onclick="setBet(1000000)">1M</button>
                            <button class="btn-quick-bet" type="button" onclick="setBet(5000000)">5M</button>
                            <button class="btn-quick-bet" type="button" onclick="setBet('ALLIN')" style="grid-column: span 3; background: var(--primary); color:#fff; border:none; font-weight:800; padding: 4px;">ALL IN</button>
                        </div>
                    </div>
                    <div class="input-group" style="margin-top: 0.5rem;">
                        <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                            <label style="margin:0">Tự động rút (x)</label>
                            <label class="switch" style="position:relative; display:inline-block; width:34px; height:20px;">
                                <input type="checkbox" id="enableAuto" checked style="opacity:0; width:0; height:0;">
                                <span class="slider" style="position:absolute; cursor:pointer; inset:0; background-color:#333; transition:.4s; border-radius:34px;"></span>
                            </label>
                        </div>
                        <input type="number" id="autoCashout" value="2.00" min="1.01" step="any">
                        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 4px; margin-top: 5px;">
                            <button type="button" onclick="$('#autoCashout').val(2.00); updatePotential();"
                                style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:6px; padding:3px; font-size:0.7rem; cursor:pointer;">x2</button>
                            <button type="button" onclick="$('#autoCashout').val(5.00); updatePotential();"
                                style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:6px; padding:3px; font-size:0.7rem; cursor:pointer;">x5</button>
                            <button type="button" onclick="$('#autoCashout').val(10.00); updatePotential();"
                                style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:6px; padding:3px; font-size:0.7rem; cursor:pointer;">x10</button>
                        </div>
                    </div>
                    <div class="stat-box" style="margin-top: 0.5rem; background:rgba(0,0,0,0.2); padding: 0.6rem 0.8rem; border-radius:0.8rem; border: 1px dashed rgba(255,255,255,0.1);">
                        <span style="opacity:0.5; font-size:0.65rem; font-weight:700;">LỢI NHUẬN DỰ KIẾN</span>
                        <div id="potentialWin" style="font-size:1.3rem; font-weight:900; color:var(--accent); font-family: 'Orbitron';">0</div>
                    </div>
                    <button id="startBtn" type="submit" class="btn-action" style="width: 100%; margin-top: 0.5rem;" onclick="startGame()">CẤT CÁNH</button>
                    <button id="cashoutBtn" type="button" class="btn-action" style="width: 100%; margin-top: 0.5rem;" onclick="cashout()">RÚT GTLM</button>
                </form>
                <div style="margin-top: 0.4rem; padding-top: 0.4rem; border-top: 1px solid rgba(255,255,255,0.1);">
                    <div style="display:flex; justify-content: space-between; align-items: center;">
                        <span style="opacity:0.5; font-size:0.8rem;">Số GTLM:</span>
                        <span id="userMoney" style="font-weight:900; font-size:1.2rem; font-family: 'Orbitron'; color:#38bdf8;"><?php echo number_format($money, 0, ',', '.'); ?></span>
                    </div>
                </div>
            </div>
            <div class="crash-area" id="crashArea">
                <?php 
                $activeEvent = DynamicEventHelper::getActiveEvent($conn);
                if ($activeEvent && ($activeEvent['game_type'] === 'crash' || $activeEvent['game_type'] === 'all')): ?>
                <div class="event-banner" style="position: absolute; top: 20px; right: 20px; z-index: 20; background: linear-gradient(135deg, #f1c40f, #e67e22); padding: 10px 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(241, 196, 15, 0.4); animation: pulse 2s infinite;">
                    <div style="font-size: 10px; font-weight: 800; color: #000; text-transform: uppercase;">SỰ KIỆN ĐANG DIỄN RA</div>
                    <div style="font-size: 14px; font-weight: 900; color: #000;"><?= htmlspecialchars($activeEvent['name']) ?> (x<?= $activeEvent['multiplier'] ?>)</div>
                </div>
                <?php endif; ?>
                <div id="crash-3d-container"></div>
                <div class="mult-wrapper">
                    <div id="multGlow" class="multiplier-glow">1.00x</div>
                    <div id="multDisp" class="multiplier-display">1.00x</div>
                </div>
            </div>
        </div>
    </div>
    <script>
        let crashPoint = 0;
        let currentMult = 1.00;
        let gameActive = false;
        let multInterval = null;
        let crash3d = null;
        // Graph
        let graphPoints = [];
        function updatePotential() {
            const bet = parseFloat($('#betAmount').val()) || 0;
            const auto = parseFloat($('#autoCashout').val()) || 1;
            const potWin = Math.round(bet * (gameActive ? currentMult : auto));
            $('#potentialWin').text(potWin.toLocaleString('vi-VN'));
        }
        $('#betAmount, #autoCashout, #enableAuto').on('input change', updatePotential);
        $('#enableAuto').on('change', function () {
            $('#autoCashout').prop('disabled', !this.checked);
        });
        updatePotential();
        function startGame() {
            if (gameActive) return;
            const bet = $('#betAmount').val();
            const auto = parseFloat($('#autoCashout').val()) || 0;
            $.post('?action=start', { bet: bet }, function (res) {
                if (res.success) {
                    crashPoint = 0; // Don't know it yet
                    $('#userMoney').text(res.money);
                    $('#startBtn').hide();
                    $('#cashoutBtn').show();
                    $('#multDisp').removeClass('crashed').text('1.00x');
                    $('#multGlow').text('1.00x');
                    $('#potentialWin').css('color', 'var(--accent)');
                    if (crash3d) crash3d.onStart();
                    const startTime = Date.now();
                    gameActive = true;
                    currentMult = 1.00;
                    // Poll server for crash status every 500ms
                    let checkInterval = setInterval(() => {
                        if (!gameActive) { clearInterval(checkInterval); return; }
                        $.get('?action=check', function(cres) {
                            if (cres.crashed) {
                                crashPoint = cres.crashPoint;
                                crashed();
                                clearInterval(checkInterval);
                            }
                        });
                    }, 500);
                    multInterval = setInterval(() => {
                        currentMult *= 1.005;
                        const txt = currentMult.toFixed(2) + 'x';
                        $('#multDisp').text(txt);
                        $('#multGlow').text(txt);
                        const hue = Math.max(0, 120 - (currentMult - 1) * 30);
                        const col = `hsl(${hue},100%,65%)`;
                        $('#multDisp').css({ 'color': col, 'text-shadow': `0 0 40px hsl(${hue},100%,50%)` });
                        $('#multGlow').css('color', `hsl(${hue},100%,45%)`);
                        if (crash3d) crash3d.setSpeed(currentMult);
                        const potWin = Math.round(bet * currentMult);
                        $('#potentialWin').text(potWin.toLocaleString('vi-VN'));
                        const isAuto = $('#enableAuto').is(':checked');
                        if (isAuto && auto > 1 && currentMult >= auto) {
                            cashout();
                            clearInterval(checkInterval);
                        }
                    }, 50);
                } else {
                    Swal.fire('Lỗi', res.message, 'error');
                }
            });
        }
        function crashed() {
            clearInterval(multInterval);
            gameActive = false;
            $('#multDisp').removeClass('mult-pulsing').css({ 'color': '#ff4757', 'text-shadow': '0 0 40px #ff4757' }).text('💥 ' + crashPoint.toFixed(2) + 'x');
            $('#multGlow').css('color', '#ff4757').text('💥 ' + crashPoint.toFixed(2) + 'x');
            $('#cashoutBtn').hide();
            $('#startBtn').show().text('CHƠI LẠI');
            $('#potentialWin').text('0').css('color', '#ff4757');
            if (crash3d) crash3d.onCrash();
            if (window.GameEffects) {
                const area = document.getElementById('crashArea').getBoundingClientRect();
                window.GameEffects.crashExplosion(area.left + area.width / 2, area.top + area.height / 2);
            }
            $.post('?action=lost');
        }
        function cashout() {
            if (!gameActive) return;
            clearInterval(multInterval);
            const finalMult = currentMult;
            gameActive = false;
            $.post('?action=cashout', { multiplier: finalMult }, function (res) {
                if (res.success) {
                    const rawWin = parseInt(res.winAmount.replace(/[^0-9]/g, ''));
                    $('#userMoney').text(res.money);
                    if (crash3d) crash3d.onCashout();
                    if (window.GameEffects) {
                        if (finalMult >= 3) window.GameEffects.showBigWin(rawWin);
                        else window.GameEffects.showWin(rawWin);
                    }
                    $('#multDisp').addClass('mult-pulsing').css('color', '#2ecc71');
                    setTimeout(() => $('#multDisp').removeClass('mult-pulsing'), 2000);
                    $('#cashoutBtn').hide();
                    $('#startBtn').show().text('TIẾP TỤC');
                } else {
                    if (res.crashPoint) {
                        crashPoint = res.crashPoint;
                        crashed();
                    } else {
                        Swal.fire('Lỗi', res.message, 'error');
                        gameActive = false;
                        clearInterval(multInterval);
                        $('#cashoutBtn').hide();
                        $('#startBtn').show();
                    }
                }
            });
        }
        function setBet(amount) {
            const money = parseFloat($('#userMoney').text().replace(/\./g, ''));
            if (amount === 'ALLIN') {
                $('#betAmount').val(money);
            } else {
                $('#betAmount').val(amount);
            }
            updatePotential();
        }

        setInterval(() => {
            $.get('live_3.php?action=get_balance', function(res) {
                if (res && res.success && res.money) {
                    $('#userMoney').text(res.money);
                }
            });
        }, 2000);

        let checkCrash3D = setInterval(() => {
            if (typeof Crash3D !== 'undefined' && typeof THREE !== 'undefined' && typeof THREE.EffectComposer !== 'undefined') {
                try {
                    crash3d = new Crash3D('crash-3d-container');
                } catch(e) {
                    document.getElementById('crash-3d-container').innerHTML = '<div style="color:red; background:white; padding:10px; z-index: 100; position:relative;">Error initializing Crash3D: ' + e.message + '</div>';
                }
                clearInterval(checkCrash3D);
            }
        }, 100);
    </script>
    <canvas id="threejs-background"></canvas>
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
            const prefix = '../';
            const scripts = ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js', 'assets/js/crash-tutorial.js', 'assets/js/crash-3d.js'];
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
<script src="bots/bot_3.js"></script>

</body>
</html>
