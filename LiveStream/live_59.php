<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_59', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require '../db_connect.php';

$useBotTheme = $botUserId;
require_once '../load_theme.php';

$userId = $botUserId;

$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$userName = $user['Name'];
$stmt->close();

// Fallback theme
$particleColor = $particleColor ?? '#12c2e9';
$shapeColors   = $shapeColors   ?? ['#12c2e9', '#a29bfe', '#fd79a8', '#00cec9'];
$bgGradient    = $bgGradient    ?? ['#0f0c29', '#302b63', '#24243e'];
if (empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, ' . $bgGradient[0] . ' 0%, ' . $bgGradient[1] . ' 50%, ' . ($bgGradient[2] ?? $bgGradient[1]) . ' 100%)';
}

// AJAX handler
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'drop') {
        $bet   = (int)($_POST['bet']  ?? 0);
        $risk  = $_POST['risk']  ?? 'medium';
        $rows  = (int)($_POST['rows'] ?? 12);
        $balls = (int)($_POST['balls'] ?? 1);
        $balls = max(1, min(50, $balls));

        $conn->begin_transaction();
        $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $locked = $stmt->get_result()->fetch_assoc()['Money'] ?? 0;
        $stmt->close();

        $totalBet = $bet * $balls;
        if ($bet <= 0 || $totalBet > $locked) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'GTLM cược không hợp lệ!']);
            exit;
        }

        $multTable = [
            'low'    => [8 => [5.6,2.1,1.1,1,0.5,1,1.1,2.1,5.6],     12 => [10,3,1.6,1.4,1.1,1,1,1,1.1,1.4,1.6,3,10],          16 => [16,9,2,1.4,1.4,1.2,1.1,1,1,1,1.1,1.2,1.4,1.4,2,9,16]],
            'medium' => [8 => [13,3,1.3,0.7,0.4,0.7,1.3,3,13],        12 => [33,11,4,2,1.1,0.6,0.3,0.6,1.1,2,4,11,33],           16 => [110,41,10,5,3,1.5,1,0.5,0.3,0.5,1,1.5,3,5,10,41,110]],
            'high'   => [8 => [29,4,1.5,0.3,0.2,0.3,1.5,4,29],        12 => [141,22,5.5,2,0.6,0.2,0.1,0.2,0.6,2,5.5,22,141],     16 => [999,130,26,9,4,2,0.7,0.2,0.1,0.2,0.7,2,4,9,26,130,999]],
        ];

        $mults = $multTable[$risk][$rows] ?? $multTable['medium'][12];
        $slots = count($mults);

        $results = []; $totalWin = 0; $sessionNet = 0;
        for ($i = 0; $i < $balls; $i++) {
            $pos = 0;
            for ($r = 0; $r < $rows; $r++) $pos += rand(0, 1);
            $pos = min($pos, $slots - 1);
            $mult = $mults[$pos];
            $win  = round($bet * $mult);
            $totalWin   += $win;
            $sessionNet += ($win - $bet);
            $results[]   = ['slot' => $pos, 'mult' => $mult, 'win' => $win];
        }

        $newMoney = $locked - $totalBet + $totalWin;
        $conn->query("UPDATE users SET Money = $newMoney WHERE Iduser = $userId");
        $conn->commit();

        echo json_encode(['success' => true, 'results' => $results, 'totalBet' => $totalBet, 'totalWin' => $totalWin, 'sessionNet' => $sessionNet, 'money' => number_format($newMoney, 0, ',', '.')]);
        exit;
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; background: <?= $bgGradientCSS ?>; background-attachment: fixed;
            color: #fff; font-family: 'Inter', sans-serif; min-height: 100vh;
            overflow-x: hidden; display: flex; flex-direction: column; align-items: center;
        }
        #threejs-background { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: -1; pointer-events: none; }

        #result-status-badge {
            position: fixed; top: 22%; left: 50%; transform: translate(-50%,-50%) scale(0.8);
            display: none; align-items: center; gap: 12px; padding: 12px 28px; border-radius: 50px;
            font-size: 20px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase;
            box-shadow: 0 10px 30px rgba(0,0,0,0.6); z-index: 9999; pointer-events: none;
            transition: all 0.4s cubic-bezier(0.175,0.885,0.32,1.275); opacity: 0; backdrop-filter: blur(10px);
        }
        #result-status-badge.show { opacity: 1; transform: translate(-50%,-50%) scale(1); }
        #result-status-badge.badge-win  { background: linear-gradient(135deg,rgba(16,185,129,0.95),rgba(5,150,105,0.95)); border: 2px solid #34d399; box-shadow: 0 0 35px rgba(16,185,129,0.7); }
        #result-status-badge.badge-jackpot { background: linear-gradient(135deg,rgba(234,179,8,0.95),rgba(217,119,6,0.95)); border: 2px solid #fbbf24; box-shadow: 0 0 45px rgba(234,179,8,0.9); animation: pulseGlow 0.8s infinite alternate; }
        #result-status-badge.badge-lose { background: linear-gradient(135deg,rgba(239,68,68,0.9),rgba(185,28,28,0.9)); border: 2px solid #f87171; box-shadow: 0 0 30px rgba(239,68,68,0.6); }
        @keyframes pulseGlow { from { transform: translate(-50%,-50%) scale(1); } to { transform: translate(-50%,-50%) scale(1.06); filter: brightness(1.2); } }

        .header-bar { width:100%; padding:8px 24px; display:flex; justify-content:space-between; align-items:center; background:rgba(0,0,0,0.5); backdrop-filter:blur(15px); border-bottom:2px solid #12c2e9; box-sizing:border-box; }
        .logo-plinko { font-family:'Orbitron',sans-serif; font-size:18px; font-weight:900; color:#12c2e9; letter-spacing:2px; }
        .user-money { background:rgba(0,0,0,0.4); padding:5px 18px; border-radius:30px; border:1px solid #12c2e9; font-weight:800; color:#12c2e9; font-size:15px; }

        .game-wrapper { max-width:820px; margin:1rem auto; padding:0 12px; width:100%; }
        .glass { background:rgba(15,12,41,0.75); backdrop-filter:blur(20px); border:1px solid rgba(18,194,233,0.2); border-radius:1.6rem; padding:1.4rem 1.8rem; box-shadow:0 20px 50px rgba(0,0,0,0.5); }

        .controls-row { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; justify-content:center; margin-bottom:16px; }
        .ctrl-group { display:flex; flex-direction:column; gap:6px; min-width:110px; }
        .ctrl-label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; opacity:0.6; }
        .seg-ctrl { display:flex; gap:4px; }
        .seg-btn { padding:5px 11px; border-radius:20px; border:1px solid rgba(255,255,255,0.2); background:rgba(255,255,255,0.05); color:#fff; font-size:0.78rem; font-weight:700; cursor:pointer; transition:0.2s; }
        .seg-btn.active, .seg-btn:hover { background:#12c2e9; border-color:#12c2e9; color:#000; }
        .bet-input { background:rgba(0,0,0,0.4); border:1px solid rgba(18,194,233,0.4); border-radius:8px; padding:6px 12px; color:#fff; font-size:1rem; font-weight:900; width:120px; outline:none; }
        .btn-drop { padding:10px 28px; border:none; border-radius:40px; background:linear-gradient(135deg,#12c2e9,#a29bfe); color:#fff; font-weight:900; font-size:0.95rem; cursor:pointer; transition:0.3s; box-shadow:0 6px 20px rgba(18,194,233,0.4); text-transform:uppercase; letter-spacing:1px; }
        .btn-drop:hover:not(:disabled) { transform:translateY(-2px) scale(1.04); filter:brightness(1.1); }
        .btn-drop:disabled { opacity:0.5; cursor:not-allowed; }

        .stats-bar { display:flex; gap:24px; justify-content:center; margin-top:12px; flex-wrap:wrap; }
        .stat-item { text-align:center; }
        .stat-lbl { font-size:0.7rem; opacity:0.5; text-transform:uppercase; letter-spacing:1px; }
        .stat-val { font-family:'Orbitron',sans-serif; font-size:1.1rem; font-weight:900; color:#12c2e9; }

        .board-viz { background:rgba(0,0,0,0.35); border:1px solid rgba(18,194,233,0.15); border-radius:1rem; padding:20px; text-align:center; min-height:160px; display:flex; flex-direction:column; align-items:center; justify-content:center; margin-top:12px; position:relative; overflow:hidden; }
        .plinko-pins { display:flex; flex-wrap:wrap; justify-content:center; gap:10px; max-width:380px; }
        .pin { width:7px; height:7px; border-radius:50%; background:#12c2e9; opacity:0.4; }
        .ball-anim { font-size:1.8rem; animation:fallBall 1.1s ease-in forwards; position:absolute; }
        @keyframes fallBall { 0% { transform:translateY(-50px) scale(0.5); opacity:0; } 50% { opacity:1; } 100% { transform:translateY(120px) scale(1.1); opacity:0; } }
        .result-log { margin-top:8px; font-size:0.82rem; opacity:0.75; min-height:36px; position:relative; z-index:2; }
        .quick-bets { display:flex; gap:5px; flex-wrap:wrap; justify-content:center; }
        .q-btn { padding:3px 9px; border-radius:14px; border:1px solid rgba(255,255,255,0.2); background:rgba(255,255,255,0.06); color:#fff; font-size:0.75rem; font-weight:700; cursor:pointer; transition:0.2s; }
        .q-btn:hover { background:rgba(18,194,233,0.3); border-color:#12c2e9; }
        .home-link { display:none !important; }
    </style>
</head>
<body>
    <canvas id="threejs-background"></canvas>
    <div id="result-status-badge"><span class="badge-icon">🎉</span><span class="badge-text">THẮNG</span></div>

    <header class="header-bar">
        <div class="logo-plinko">🎱 PLINKO V2 PRO</div>
        <div class="user-money">💰 <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span> GTLM</div>
        <div style="font-size:13px; color:#aaa;">STREAMER: <b style="color:#12c2e9;"><?= htmlspecialchars($userName) ?></b></div>
    </header>

    <div class="game-wrapper">
        <div class="glass">
            <div style="text-align:center; font-family:'Orbitron',sans-serif; font-size:0.85rem; letter-spacing:2px; opacity:0.45; margin-bottom:14px;">ADVANCED GRAVITY ENGINE</div>
            <div class="controls-row">
                <div class="ctrl-group">
                    <div class="ctrl-label">Risk Level</div>
                    <div class="seg-ctrl" id="riskCtrl">
                        <button class="seg-btn" data-val="low">LOW</button>
                        <button class="seg-btn active" data-val="medium">MED</button>
                        <button class="seg-btn" data-val="high">HIGH</button>
                    </div>
                </div>
                <div class="ctrl-group">
                    <div class="ctrl-label">Số hàng đinh</div>
                    <div class="seg-ctrl" id="rowsCtrl">
                        <button class="seg-btn" data-val="8">8</button>
                        <button class="seg-btn active" data-val="12">12</button>
                        <button class="seg-btn" data-val="16">16</button>
                    </div>
                </div>
                <div class="ctrl-group">
                    <div class="ctrl-label">Số bóng</div>
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
                        <button class="q-btn" onclick="setBet(10000)">10K</button>
                        <button class="q-btn" onclick="setBet(50000)">50K</button>
                        <button class="q-btn" onclick="setBet(100000)">100K</button>
                        <button class="q-btn" onclick="setBet(500000)">500K</button>
                    </div>
                </div>
                <div class="ctrl-group" style="justify-content:flex-end;">
                    <button class="btn-drop" id="dropBtn">🎱 THẢ BÓNG</button>
                </div>
            </div>

            <div class="board-viz" id="boardViz">
                <div class="plinko-pins" id="pinGrid"></div>
                <div class="result-log" id="resultLog">Bot đang chuẩn bị thả bóng...</div>
            </div>

            <div class="stats-bar">
                <div class="stat-item"><div class="stat-lbl">Phiên Thắng</div><div class="stat-val" id="sessionWin">0</div></div>
                <div class="stat-item"><div class="stat-lbl">Phiên Thua</div><div class="stat-val" id="sessionLose">0</div></div>
                <div class="stat-item"><div class="stat-lbl">Lợi Nhuận Phiên</div><div class="stat-val" id="sessionProfit">0</div></div>
                <div class="stat-item"><div class="stat-lbl">Mult Tốt Nhất</div><div class="stat-val" id="bestMult">-</div></div>
            </div>
        </div>
    </div>

    <script>
        window.themeConfig = {
            particleCount: <?= $particleCount ?? 800 ?>,
            particleSize: <?= $particleSize ?? 0.05 ?>,
            particleColor: '<?= $particleColor ?? "#12c2e9" ?>',
            particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
            shapeCount: <?= $shapeCount ?? 10 ?>,
            shapeColors: <?= json_encode($shapeColors ?? ["#12c2e9","#a29bfe","#fd79a8"]) ?>,
            shapeOpacity: <?= $shapeOpacity ?? 0.3 ?>,
            bgGradient: <?= json_encode($bgGradient ?? ["#0f0c29","#302b63","#24243e"]) ?>
        };
    </script>
    <script src="../threejs-background.js"></script>
    <script src="../assets/js/game-effects.js"></script>
    <script src="../assets/js/game-effects-auto.js"></script>

    <script>
        let sessionWins = 0, sessionLoses = 0, sessionNet = 0, bestMult = 0;
        function setBet(v) { $('#betAmt').val(v); }

        $('.seg-ctrl').each(function() {
            $(this).find('.seg-btn').click(function() {
                $(this).siblings().removeClass('active');
                $(this).addClass('active');
            });
        });

        function drawPins(rows) {
            const grid = document.getElementById('pinGrid');
            grid.innerHTML = '';
            for (let r = 2; r <= Math.min(rows, 8); r++) {
                for (let c = 0; c < r; c++) {
                    const pin = document.createElement('div');
                    pin.className = 'pin';
                    grid.appendChild(pin);
                }
            }
        }
        drawPins(12);
        $('#rowsCtrl .seg-btn').click(function() { drawPins(parseInt($(this).data('val'))); });

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
                if (typeof confetti === 'function') confetti({ particleCount: type === 'jackpot' ? 200 : 100, spread: 70, origin: { y: 0.6 }, colors: ['#12c2e9','#a29bfe','#ffd700'] });
            } else {
                if (typeof GameEffects !== 'undefined' && GameEffects.lose) GameEffects.lose();
            }
            setTimeout(() => { badge.classList.remove('show'); setTimeout(() => { badge.style.display = 'none'; }, 400); }, 3500);
        }

        $('#dropBtn').click(function() {
            const bet   = parseInt($('#betAmt').val()) || 10000;
            const risk  = $('#riskCtrl .seg-btn.active').data('val') || 'medium';
            const rows  = parseInt($('#rowsCtrl .seg-btn.active').data('val')) || 12;
            const balls = parseInt($('#ballsCtrl .seg-btn.active').data('val')) || 1;

            $(this).prop('disabled', true).text('⏳ Đang thả...');

            const viz = document.getElementById('boardViz');
            for (let i = 0; i < Math.min(balls, 5); i++) {
                setTimeout(() => {
                    const b = document.createElement('div');
                    b.className = 'ball-anim';
                    b.textContent = '🟢';
                    b.style.left = (30 + Math.random() * 40) + '%';
                    b.style.top = '0';
                    viz.appendChild(b);
                    setTimeout(() => b.remove(), 1200);
                }, i * 180);
            }

            $.post('?action=drop', { bet, risk, rows, balls }, function(res) {
                $('#dropBtn').prop('disabled', false).text('🎱 THẢ BÓNG');
                if (!res.success) { $('#resultLog').text('❌ ' + res.message); return; }

                $('#balance-val').text(res.money);
                let maxMult = 0;
                res.results.forEach(r => { if (r.mult > maxMult) maxMult = r.mult; });
                if (maxMult > bestMult) { bestMult = maxMult; $('#bestMult').text('x' + maxMult); }

                sessionNet += res.sessionNet;
                if (res.sessionNet > 0) {
                    sessionWins++;
                    $('#sessionWin').text(sessionWins);
                    if (maxMult >= 50) showResultStatus('jackpot', '👑 JACKPOT x' + maxMult + '!', '👑');
                    else showResultStatus('win', '🎱 THẮNG! +' + res.sessionNet.toLocaleString('vi-VN') + ' GTLM', '🎉');
                } else {
                    sessionLoses++;
                    $('#sessionLose').text(sessionLoses);
                    showResultStatus('lose', '😢 BAY MÀU ' + res.sessionNet.toLocaleString('vi-VN') + ' GTLM', '😢');
                }
                $('#sessionProfit').css('color', sessionNet >= 0 ? '#34d399' : '#f87171').text((sessionNet >= 0 ? '+' : '') + sessionNet.toLocaleString('vi-VN'));
                $('#resultLog').html('🎱 Thả <b>' + balls + '</b> bóng — Cược: <b>' + res.totalBet.toLocaleString('vi-VN') + '</b> | Thắng: <b>' + res.totalWin.toLocaleString('vi-VN') + '</b> | Mult max: <b>x' + maxMult + '</b>');
            }, 'json');
        });
    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="bots/bot_59.js"></script>
</body>
</html>
