<?php
session_start();
include '../db_connect.php';
require_once '../include_css.php';
include '../load_theme.php';
require_once '../game_history_helper.php';

if (!isset($_SESSION['Iduser'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['Iduser'];
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$userName = $user['Name'];
$stmt->close();

// Auto-create history table
$conn->query("CREATE TABLE IF NOT EXISTS history_crash (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Iduser INT NOT NULL,
    Bet DECIMAL(30,2) NOT NULL,
    Result VARCHAR(255) NOT NULL,
    WinAmount DECIMAL(30,2) NOT NULL,
    Time DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false];

    if ($action === 'start') {
        $bet = (float) ($_POST['bet'] ?? 0);
        if ($bet <= 0) {
            $response['message'] = "Gtlm cược không hợp lệ!";
        } else {
            $conn->begin_transaction();
            try {
                // Khóa người dùng
                $res = $conn->query("SELECT Money FROM users WHERE Iduser = $userId FOR UPDATE");
                $userData = $res->fetch_assoc();
                if ($userData['Money'] < $bet) throw new Exception("Không đủ tiền!");

                $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");

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
                    $winAmount = round($game['bet'] * $multiplier);
                    $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $userId");
                    $resStr = "Cashout at x$multiplier";
                    $profit = $winAmount - $game['bet'];
                    $his = $conn->prepare("INSERT INTO history_crash (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
                    $his->bind_param("idss", $userId, $game['bet'], $resStr, $profit);
                    $his->execute();
                    logGameHistoryWithAll($conn, $userId, 'Crash', $game['bet'], $winAmount, true);
                    $conn->commit();
                    $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
                    $response = ['success' => true, 'winAmount' => number_format($winAmount, 0, ',', '.'), 'money' => number_format($newMoney, 0, ',', '.')];
                    unset($_SESSION['crash_game']);
                } catch (Exception $e) {
                    $conn->rollback();
                    $response['message'] = "Lỗi hệ thống!";
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
    <link
        href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;500;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <link rel="stylesheet" href="../assets/css/game-effects.css">
    <style>
        :root {
            --primary: #ff4757;
            --accent: #f1c40f;
            --glass: rgba(255, 255, 255, 0.08);
        }

        body {
            margin: 0;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Inter', sans-serif;
            overflow: hidden;
            cursor: url('../img/chuot.png'), auto !important;
        }

        .main-container {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 2.5rem;
            padding: 2rem;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.6);
            width: 98%;
            max-width: 1400px;
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 2rem;
            min-height: 85vh;
            align-self: center;
            margin: 20px 0;
        }

        .crash-area {
            position: relative;
            width: 100%;
            min-height: 600px;
            background: radial-gradient(circle at center, #0a0a1a 0%, #05050a 100%);
            border-radius: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 0 100px rgba(0, 0, 0, 0.9);
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
            top: 40px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            background: radial-gradient(circle, rgba(0,242,254,0.05) 0%, transparent 70%);
            padding: 20px;
        }

        .multiplier-display {
            font-size: 5rem;
            font-weight: 900;
            font-family: 'Orbitron', sans-serif;
            position: relative;
            z-index: 11;
            color: #fff;
            text-shadow: 0 0 20px rgba(0, 242, 254, 0.6);
            line-height: 1;
        }

        .multiplier-glow {
            position: absolute;
            font-size: 5.5rem;
            font-weight: 900;
            font-family: 'Orbitron', sans-serif;
            z-index: 10;
            filter: blur(30px);
            opacity: 0.4;
            color: var(--primary);
            pointer-events: none;
            white-space: nowrap;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            position: relative;
            z-index: 100
        }

        .btn-action {
            padding: 1.2rem;
            border-radius: 1.5rem;
            border: none;
            font-weight: 900;
            font-size: 1.4rem;
            cursor: url('../img/tay.png'), pointer !important;
            transition: 0.3s;
            text-transform: uppercase;
            position: relative;
            overflow: hidden
        }

        .btn-action::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transform: translateX(-100%);
            transition: 0.5s
        }

        .btn-action:hover::after {
            transform: translateX(100%)
        }

        #startBtn {
            background: linear-gradient(135deg, var(--primary), #ff6b81);
            color: #fff;
            box-shadow: 0 10px 30px rgba(255, 71, 87, 0.4)
        }

        #cashoutBtn {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: #fff;
            display: none;
            box-shadow: 0 10px 30px rgba(46, 204, 113, 0.4)
        }

        .input-group {
            background: rgba(0, 0, 0, 0.3);
            padding: 0.8rem 1.2rem;
            border-radius: 1.2rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: 0.3s
        }

        .input-group:focus-within {
            border-color: var(--primary);
            background: rgba(255, 71, 87, 0.05)
        }

        /* Premium Back Home Button */
        .back-home-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 999;
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            text-decoration: none;
            font-family: 'Orbitron', sans-serif;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .back-home-btn:hover {
            background: rgba(255, 71, 87, 0.1);
            border-color: #ff4757;
            color: #ff4757;
            box-shadow: 0 0 20px rgba(255, 71, 87, 0.4);
            transform: translateX(5px);
        }

        .back-home-btn i {
            font-size: 1.1rem;
        }

        .input-group label {
            display: block;
            font-size: 0.65rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.4);
            margin-bottom: 4px;
            font-weight: 700;
            letter-spacing: 1px
        }

        .input-group input {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.2rem;
            font-weight: 900;
            width: 100%;
            outline: none;
            font-family: 'Orbitron'
        }

        #userMoney {
            word-break: break-all;
            line-height: 1.2;
            display: inline-block;
            max-width: 100%;
            color: var(--accent)
        }

        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0
        }

        input[type=number] {
            -moz-appearance: textfield;
            appearance: textfield;
        }

        @media(max-width:1000px) {
            .glass-card {
                grid-template-columns: 1fr;
                height: auto;
                display: block;
                padding: 1.5rem
            }

            .sidebar {
                margin-bottom: 2rem
            }

            .crash-area {
                min-height: 400px
            }

            .multiplier-display,
            .multiplier-glow {
                font-size: 5rem;
            }
        }

        .btn-howto {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 0.65rem 1rem;
            border-radius: 1rem;
            cursor: url('../img/tay.png'), pointer !important;
            transition: all 0.3s;
            width: 100%;
            margin-top: 0.75rem
        }

        .btn-howto:hover {
            background: rgba(255, 71, 87, 0.15);
            border-color: var(--primary);
            color: #fff
        }
    </style>
</head>

<body>
    <a href="../index.php" class="back-home-btn">
        <i class="fas fa-th-large"></i> Dashboard
    </a>
    <div class="main-container">
        <div class="glass-card">
            <div class="sidebar">
                <h1
                    style="margin:0; font-size: 2.5rem; font-weight: 900; color: var(--primary); font-family: 'Orbitron'; text-shadow: 0 0 20px rgba(255,71,87,0.3);">
                    CRASH</h1>
                <p style="margin:0; opacity:0.4; font-size: 0.8rem; letter-spacing: 2px;">Vegas Royale Premium 3D</p>

                <div id="winFeed"
                    style="margin-top: 1rem; height: 120px; overflow: hidden; background: rgba(0,0,0,0.4); border-radius: 1rem; padding: 0.8rem; border: 1px solid rgba(255,255,255,0.05); font-size: 0.75rem;">
                    <div
                        style="opacity:0.5; margin-bottom: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
                        Hoạt động trực tuyến</div>
                    <div id="winList" style="display:flex; flex-direction:column; gap:6px;"></div>
                </div>

                <form id="gameForm" onsubmit="return false;" style="margin-top: 1rem;">
                    <div class="input-group">
                        <label>Gtlm cược (gtlm)</label>
                        <input type="number" id="betAmount" value="10000" min="1000" step="any">
                        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 5px; margin-top: 8px;">
                            <button type="button" onclick="quickBet(0.5)"
                                style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:8px; padding:4px; font-size:0.7rem; cursor:pointer;">1/2</button>
                            <button type="button" onclick="quickBet(2)"
                                style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:8px; padding:4px; font-size:0.7rem; cursor:pointer;">x2</button>
                            <button type="button" onclick="quickBet('max')"
                                style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:8px; padding:4px; font-size:0.7rem; cursor:pointer;">MAX</button>
                        </div>
                    </div>

                    <div class="input-group" style="margin-top: 1rem;">
                        <div
                            style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <label style="margin:0">Tự động rút (x)</label>
                            <label class="switch"
                                style="position:relative; display:inline-block; width:34px; height:20px;">
                                <input type="checkbox" id="enableAuto" checked style="opacity:0; width:0; height:0;">
                                <span class="slider"
                                    style="position:absolute; cursor:pointer; inset:0; background-color:#333; transition:.4s; border-radius:34px;"></span>
                            </label>
                        </div>
                        <input type="number" id="autoCashout" value="2.00" min="1.01" step="any">
                        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 5px; margin-top: 8px;">
                            <button type="button" onclick="$('#autoCashout').val(2.00); updatePotential();"
                                style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:8px; padding:4px; font-size:0.7rem; cursor:pointer;">x2</button>
                            <button type="button" onclick="$('#autoCashout').val(5.00); updatePotential();"
                                style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:8px; padding:4px; font-size:0.7rem; cursor:pointer;">x5</button>
                            <button type="button" onclick="$('#autoCashout').val(10.00); updatePotential();"
                                style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:8px; padding:4px; font-size:0.7rem; cursor:pointer;">x10</button>
                        </div>
                    </div>

                    <div id="proTipBox"
                        style="margin-top: 1rem; padding: 0.8rem; border-radius: 1rem; background: rgba(18, 194, 233, 0.05); border: 1px solid rgba(18, 194, 233, 0.2); min-height: 50px;">
                        <span
                            style="display:block; font-size:0.6rem; color:#12c2e9; font-weight:700; margin-bottom:4px; text-transform:uppercase;">GỢI
                            Ý CHIẾN THUẬT</span>
                        <div id="tipText" style="font-size:0.75rem; opacity:0.8; font-style:italic;">Đang tải mẹo...
                        </div>
                    </div>

                    <style>
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

                    <div class="stat-box"
                        style="margin-top: 1.5rem; background:rgba(0,0,0,0.2); padding: 1.2rem; border-radius:1.2rem; border: 1px dashed rgba(255,255,255,0.1);">
                        <span style="opacity:0.5; font-size:0.7rem; font-weight:700;">LỢI NHUẬN DỰ KIẾN</span>
                        <div id="potentialWin"
                            style="font-size:1.8rem; font-weight:900; color:var(--accent); font-family: 'Orbitron';">0
                        </div>
                    </div>

                    <button id="startBtn" type="submit" class="btn-action" style="width: 100%; margin-top: 1rem;"
                        onclick="startGame()">CẤT CÁNH</button>
                    <button id="cashoutBtn" type="button" class="btn-action" style="width: 100%; margin-top: 1rem;"
                        onclick="cashout()">RÚT Gtlm</button>
                </form>

                <div style="margin-top: auto; padding-top: 2rem; border-top: 1px solid rgba(255,255,255,0.1);">
                    <div style="display:flex; justify-content: space-between; align-items: center;">
                        <span style="opacity:0.5">Số Gtlm:</span>
                        <span id="userMoney"
                            style="font-weight:900; font-size:1.4rem; font-family: 'Orbitron';"><?php echo number_format($money, 0, ',', '.'); ?></span>
                    </div>
                    <div style="text-align: center; margin-top: 1.5rem;">
                        <a href="../index.php"
                            style="color: #fff; text-decoration: none; font-size: 0.8rem; opacity: 0.3; transition: 0.3s;"
                            onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.3">← Quay về
                            Dashboard</a>
                    </div>
                    <button class="btn-howto" onclick="crTutOpen()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <path d="M12 16v-4m0-4h.01" />
                        </svg>
                        Hướng dẫn chơi
                    </button>
                </div>
            </div>

            <div class="crash-area" id="crashArea">
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

            $.post('crash.php?action=start', { bet: bet }, function (res) {
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
                        $.get('crash.php?action=check', function(cres) {
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

            $.post('crash.php?action=lost');
        }

        function cashout() {
            if (!gameActive) return;
            clearInterval(multInterval);
            const finalMult = currentMult;
            gameActive = false;

            $.post('crash.php?action=cashout', { multiplier: finalMult }, function (res) {
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

                    Swal.fire({
                        title: '🚀 THÀNH CÔNG!',
                        html: `Rút tại <b style="color:var(--accent)">x${finalMult.toFixed(2)}</b> nhận <b style="color:#2ecc71">${res.winAmount} gtlm</b>`,
                        icon: 'success', background: '#111', color: '#fff', timer: 3000
                    });
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

        function quickBet(ratio) {
            const current = parseFloat($('#betAmount').val()) || 0;
            const money = parseFloat($('#userMoney').text().replace(/\./g, '').replace(',', '.')) || 0;
            if (ratio === 'max') $('#betAmount').val(money);
            else $('#betAmount').val(Math.floor(current * ratio));
            updatePotential();
        }

        function initProTips() {
            const tips = [
                "Nên cài đặt 'Tự động rút' ở mức x1.5 đến x2.0 để có lợi nhuận ổn định.",
                "Đừng quá tham lam, x10 là mốc rủi ro cực kỳ cao!",
                "Sử dụng phím cược nhanh để thao tác linh hoạt hơn trong từng ván.",
                "Nếu thua liên tiếp, hãy nghỉ ngơi và quay lại sau để tránh tâm lý gỡ gạc.",
                "Tốc độ phi thuyền tăng nhanh sau mốc x5, hãy cẩn thận!",
                "Vegas Royale luôn có những phần thưởng bất ngờ cho người chơi kiên trì."
            ];
            let i = 0;
            setInterval(() => {
                $('#tipText').fadeOut(300, function () {
                    $(this).text(tips[i]).fadeIn(300);
                    i = (i + 1) % tips.length;
                });
            }, 6000);
            $('#tipText').text(tips[0]);
        }

        function simulateWinFeed() {
            const users = ['ManhNT', 'VegasPro', 'LuckyGuy', 'CryptoKing', 'Player99', 'AdminRoyale', 'DiamondHand'];
            const list = $('#winList');

            function addWin() {
                const user = users[Math.floor(Math.random() * users.length)];
                const mult = (Math.random() * 5 + 1).toFixed(2);
                const win = (Math.random() * 500000 + 10000).toLocaleString('vi-VN');

                const item = $(`<div style="display:flex; justify-content: space-between; border-left: 2px solid var(--primary); padding-left: 8px; animation: slideIn 0.3s ease;">
                    <span style="font-weight:700;">${user}</span>
                    <span>x${mult}</span>
                    <span style="color:#2ecc71; font-weight:900;">+${win}</span>
                </div>`);

                list.prepend(item);
                if (list.children().length > 5) list.children().last().remove();

                setTimeout(addWin, Math.random() * 5000 + 2000);
            }
            addWin();
        }

        window.onload = () => {
            simulateWinFeed();
            initProTips();
            // Load 3D Engine
            if (typeof Crash3D !== 'undefined') {
                crash3d = new Crash3D('crash-3d-container');
            }
        };

        // Add slideIn animation
        $('<style>@keyframes slideIn { from { opacity: 0; transform: translateX(-10px); } to { opacity: 1; transform: translateX(0); } }</style>').appendTo('head');
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
            const prefix = window.location.pathname.includes('/games/') ? '../' : '';
            const scripts = ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js', 'assets/js/crash-tutorial.js', 'assets/js/crash-3d.js'];
            scripts.forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src;
                s.async = false;
                document.head.appendChild(s);
            });
        })();
    </script>
</body>

</html>