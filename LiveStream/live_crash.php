<?php
session_start();
include '../db_connect.php';
require_once '../include_css.php';
include '../load_theme.php';
require_once 'bot_streamer_helper.php';

// Nạp thông tin tài khoản Bot thật 'bot_crash' từ bảng users trong CSDL
$botUser = getOrCreateBotStreamerUser($conn, 'bot_crash', 66666000);
$botId = $botUser['Iduser'];
$money = $botUser['Money'];
$userName = $botUser['Name'];

$botTheme = getBotStreamerTheme($conn, $botId);
$bgGradientCSS = $botTheme['bgGradientCSS'];

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false];

    if ($action === 'get_smart_bet') {
        $smartBet = calculateSmartBotBet($conn, $botId, 'history_crash', 50000);
        $autoMultiplier = round(1.5 + (rand(1, 25) / 10), 2);
        echo json_encode([
            'success' => true,
            'smartBet' => $smartBet,
            'autoMultiplier' => $autoMultiplier,
            'botName' => $userName
        ]);
        exit;
    }

    if ($action === 'start') {
        $bet = (float)($_POST['bet'] ?? 20000);
        
        $conn->begin_transaction();
        $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmtLock->bind_param("i", $botId);
        $stmtLock->execute();
        $botMoney = (float)($stmtLock->get_result()->fetch_assoc()['Money'] ?? 66666000);
        $stmtLock->close();

        $newBotMoney = max(1000000, $botMoney - $bet);
        $up = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $up->bind_param("di", $newBotMoney, $botId);
        $up->execute();
        $up->close();

        $instantCrash = rand(1, 100) <= 5;
        $crashPoint = $instantCrash ? 1.00 : max(1.01, round((100 / (rand(1, 1000000) / 10000)) * 0.96, 2));
        $_SESSION['live_crash_game'] = [
            'bet' => $bet,
            'crashPoint' => $crashPoint,
            'status' => 'active',
            'start_time' => microtime(true)
        ];

        $conn->commit();

        echo json_encode([
            'success' => true,
            'money' => number_format($newBotMoney, 0, ',', '.')
        ]);
        exit;
    } elseif ($action === 'cashout') {
        $mult = (float)($_POST['multiplier'] ?? 1.5);
        $winAmount = 0;
        if (isset($_SESSION['live_crash_game'])) {
            $bet = $_SESSION['live_crash_game']['bet'];
            $winAmount = round($bet * $mult);

            $conn->begin_transaction();
            $up = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            $up->bind_param("di", $winAmount, $botId);
            $up->execute();
            $up->close();
            $conn->commit();

            unset($_SESSION['live_crash_game']);
        }
        $freshMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $botId")->fetch_assoc()['Money'];
        echo json_encode([
            'success' => true,
            'winAmount' => number_format($winAmount, 0, ',', '.'),
            'money' => number_format($freshMoney, 0, ',', '.')
        ]);
        exit;
    } elseif ($action === 'check') {
        if (isset($_SESSION['live_crash_game'])) {
            $game = $_SESSION['live_crash_game'];
            $elapsed = microtime(true) - $game['start_time'];
            $currentMult = pow(1.005, ($elapsed * 1000) / 50);
            if ($currentMult >= $game['crashPoint']) {
                echo json_encode(['success' => true, 'crashed' => true, 'crashPoint' => $game['crashPoint']]);
            } else {
                echo json_encode(['success' => true, 'crashed' => false]);
            }
        }
        exit;
    } elseif ($action === 'lost') {
        unset($_SESSION['live_crash_game']);
        echo json_encode(['success' => true]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Crash Flight Premium - Live Stream 24/7</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
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
        :root { --primary: #ff4757; --accent: #f1c40f; --glass: rgba(255, 255, 255, 0.08); }
        html, body, div, p, span, section, header, footer, aside, nav, table, tr, td, iframe, canvas {
            cursor: url('../img/chuot.png'), default !important;
        }
        a, button, input, select, textarea, label, .btn, [role="button"], [onclick], .clickable, .btn-action {
            cursor: url('../img/tay.png'), pointer !important;
        }
        body { margin: 0; background: <?= $bgGradientCSS ?>; background-attachment: fixed; color: #fff; font-family: 'Inter', sans-serif; overflow: hidden; }
        #threejs-background { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; pointer-events: none; }
        .main-container { height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; box-sizing: border-box; }
        .glass-card { background: var(--glass); backdrop-filter: blur(30px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 2.5rem; padding: 2rem; width: 95%; max-width: 1100px; display: grid; grid-template-columns: 300px 1fr; gap: 1.5rem; max-height: 92vh; }
        .crash-area { position: relative; width: 100%; min-height: 500px; background: radial-gradient(circle at center, #0a0a1a 0%, #05050a 100%); border-radius: 2rem; border: 1px solid rgba(255, 255, 255, 0.05); overflow: hidden; display: flex; align-items: center; justify-content: center; }
        #crash-3d-container { position: absolute; inset: 0; z-index: 1; }
        .mult-wrapper { position: absolute; top: 40px; left: 50%; transform: translateX(-50%); z-index: 10; display: flex; flex-direction: column; align-items: center; pointer-events: none; }
        .multiplier-display { font-size: 5rem; font-weight: 900; font-family: 'Orbitron', sans-serif; z-index: 11; color: #fff; text-shadow: 0 0 20px rgba(0, 242, 254, 0.6); }
        .multiplier-glow { position: absolute; font-size: 5.5rem; font-weight: 900; font-family: 'Orbitron', sans-serif; filter: blur(30px); opacity: 0.4; color: var(--primary); pointer-events: none; }
        .sidebar { display: flex; flex-direction: column; gap: 1rem; }
        .btn-action { padding: 1.2rem; border-radius: 1.5rem; border: none; font-weight: 900; font-size: 1.4rem; transition: 0.3s; text-transform: uppercase; background: linear-gradient(135deg, var(--primary), #ff6b81); color: #fff; width: 100%; pointer-events: none !important; }
        .input-group { background: rgba(0, 0, 0, 0.3); padding: 0.8rem 1.2rem; border-radius: 1.2rem; border: 1px solid rgba(255, 255, 255, 0.05); pointer-events: none !important; }
        .input-group input { background: none; border: none; color: #fff; font-size: 1.2rem; font-weight: 900; width: 100%; outline: none; font-family: 'Orbitron'; }

        /* 🤖 Virtual Bot Mouse Cursor Style */
        .bot-virtual-cursor {
            position: fixed;
            z-index: 99999;
            pointer-events: none;
            opacity: 0;
            transform: translate(-5px, -5px);
            transition: opacity 0.3s ease;
        }
        .cursor-pointer-arrow svg {
            filter: drop-shadow(0 0 10px #ff4757) drop-shadow(0 0 20px #ff4757);
        }
        .cursor-bot-tag {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(11, 15, 25, 0.92);
            backdrop-filter: blur(8px);
            border: 1px solid #ff4757;
            color: #fff;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 900;
            white-space: nowrap;
            box-shadow: 0 4px 15px rgba(255, 71, 87, 0.4);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .bot-tag-dot {
            width: 6px;
            height: 6px;
            background: #ff4757;
            border-radius: 50%;
            box-shadow: 0 0 8px #ff4757;
            animation: pulse-dot 1.5s infinite;
        }
        @keyframes pulse-dot { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.5); opacity: 0.4; } }
    </style>
</head>
<body>

    <!-- 🤖 Virtual Bot Streamer Animated Pointer Cursor -->
    <div id="botVirtualCursor" class="bot-virtual-cursor">
        <div class="cursor-pointer-arrow">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                <path d="M3 3l7 18 3-7 7-3L3 3z" fill="#ff4757" stroke="#ffffff" stroke-width="2" stroke-linejoin="round"/>
            </svg>
        </div>
        <div class="cursor-bot-tag">
            <span class="bot-tag-dot"></span>
            <span id="cursorBotName"><?= htmlspecialchars($userName) ?></span>
        </div>
    </div>

    <div class="main-container">
        <div class="glass-card">
            <div class="sidebar">
                <h1 style="margin:0; font-size: 2.5rem; font-weight: 900; color: var(--primary); font-family: 'Orbitron';">CRASH</h1>
                <p style="margin:0; color:#ff4757; font-size: 0.75rem; letter-spacing: 1px; font-weight:900;">🔴 LIVE STREAM 24/7 — STREAMER BOT: <?= $userName ?></p>
                
                <div class="input-group">
                    <label style="font-size:0.65rem; color:rgba(255,255,255,0.4); font-weight:700;">Ví Bot CSDL (bot_crash)</label>
                    <input type="number" id="betAmount" value="50000" readonly>
                </div>
                <div class="input-group" style="margin-top: 1rem;">
                    <label style="font-size:0.65rem; color:rgba(255,255,255,0.4); font-weight:700;">TỰ ĐỘNG RÚT BOT (X)</label>
                    <input type="number" id="autoCashout" value="2.00" readonly>
                </div>
                <div style="margin-top: auto;">
                    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="opacity:0.5; font-size:0.8rem;">Ví Bot Streamer:</span>
                        <span id="userMoney" style="font-weight:900; font-size:1.4rem; font-family: 'Orbitron'; color:var(--accent);"><?php echo number_format($money, 0, ',', '.'); ?></span>
                    </div>
                    <button id="startBtn" class="btn-action">🚀 STREAMER BOT PHÓNG LIVE</button>
                    <button id="cashoutBtn" class="btn-action" style="display:none; background:linear-gradient(135deg, #2ecc71, #27ae60);">RÚT BOT</button>
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

        function startGame() {
            if (gameActive) return;
            const bet = $('#betAmount').val();
            const auto = parseFloat($('#autoCashout').val()) || 2.00;
            $.post('live_crash.php?action=start', { bet: bet }, function (res) {
                if (res.success) {
                    crashPoint = 0;
                    $('#userMoney').text(res.money);
                    $('#startBtn').hide();
                    $('#cashoutBtn').show();
                    $('#multDisp').removeClass('crashed').text('1.00x');
                    $('#multGlow').text('1.00x');
                    if (crash3d) crash3d.onStart();
                    gameActive = true;
                    currentMult = 1.00;

                    let checkInterval = setInterval(() => {
                        if (!gameActive) { clearInterval(checkInterval); return; }
                        $.get('live_crash.php?action=check', function(cres) {
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
                        if (crash3d) crash3d.setSpeed(currentMult);

                        if (auto > 1 && currentMult >= auto) {
                            cashout();
                            clearInterval(checkInterval);
                        }
                    }, 50);
                }
            });
        }

        function crashed() {
            clearInterval(multInterval);
            gameActive = false;
            $('#multDisp').css({ 'color': '#ff4757', 'text-shadow': '0 0 40px #ff4757' }).text('💥 ' + crashPoint.toFixed(2) + 'x');
            $('#multGlow').css('color', '#ff4757').text('💥 ' + crashPoint.toFixed(2) + 'x');
            $('#cashoutBtn').hide();
            $('#startBtn').show().text('🚀 STREAMER BOT PHÓNG LIVE');
            if (crash3d) crash3d.onCrash();
            $.post('live_crash.php?action=lost');
        }

        function cashout() {
            if (!gameActive) return;
            clearInterval(multInterval);
            const finalMult = currentMult;
            gameActive = false;
            $.post('live_crash.php?action=cashout', { multiplier: finalMult }, function (res) {
                if (res.success) {
                    $('#userMoney').text(res.money);
                    if (crash3d) crash3d.onCashout();
                    $('#multDisp').css('color', '#2ecc71').text('🎉 RÚT ' + finalMult.toFixed(2) + 'x');
                    $('#cashoutBtn').hide();
                    $('#startBtn').show().text('🚀 STREAMER BOT PHÓNG LIVE');
                }
            });
        }

        // 🔄 AUTO-PLAY SPECTATOR LOOP WITH VIRTUAL MOUSE CURSOR GSAP ANIMATION 24/7
        function autoSpectatorLoop() {
            if (gameActive) return;

            $.get('live_crash.php?action=get_smart_bet', function(res) {
                if (!res.success) return;
                $('#betAmount').val(res.smartBet);
                $('#autoCashout').val(res.autoMultiplier);

                const cursor = $('#botVirtualCursor');
                $('#cursorBotName').text(res.botName);

                gsap.set(cursor, { opacity: 1, left: 100, top: 100, scale: 1 });

                const startBtn = $('#startBtn');
                if (startBtn.length === 0) return;
                const offsetBtn = startBtn.offset();

                gsap.to(cursor, {
                    left: offsetBtn.left + startBtn.width() / 2,
                    top: offsetBtn.top + startBtn.height() / 2,
                    duration: 0.9,
                    ease: "power2.out",
                    onComplete: () => {
                        gsap.to(cursor, { scale: 0.7, duration: 0.12, yoyo: true, repeat: 1, onComplete: () => {
                            startGame();
                            gsap.to(cursor, { opacity: 0, delay: 0.5, duration: 0.4 });
                        }});
                    }
                });
            });
        }
        setTimeout(autoSpectatorLoop, 2000);
        setInterval(autoSpectatorLoop, 15000);

        (function () {
            window.themeConfig = {
                particleCount: <?= (int)$botTheme['particleCount'] ?>, 
                particleSize: 0.05, 
                particleColor: '<?= htmlspecialchars($botTheme['particleColor']) ?>', 
                particleOpacity: <?= (float)$botTheme['particleOpacity'] ?>,
                shapeCount: <?= (int)$botTheme['shapeCount'] ?>, 
                shapeColors: <?= json_encode($botTheme['shapeColors']) ?>, 
                shapeOpacity: <?= (float)$botTheme['shapeOpacity'] ?>,
                bgGradient: <?= json_encode($botTheme['bgGradient']) ?>
            };
            const prefix = '../';
            ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/crash-3d.js'].forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src; s.async = false;
                document.head.appendChild(s);
            });
        })();

        window.onload = () => {
            if (typeof Crash3D !== 'undefined') {
                crash3d = new Crash3D('crash-3d-container');
            }
        };
    </script>
    <canvas id="threejs-background"></canvas>
</body>
</html>
