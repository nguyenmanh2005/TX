<?php
session_start();
include '../db_connect.php';
require_once '../include_css.php';
include '../load_theme.php';
require_once 'bot_streamer_helper.php';
require_once '../game_history_helper.php';

// Nạp thông tin tài khoản Bot thật 'bot_daga' từ bảng users trong CSDL
$botUser = getOrCreateBotStreamerUser($conn, 'bot_daga', 77777000);
$botId = $botUser['Iduser'];
$money = $botUser['Money'];
$userName = $botUser['Name'];

$botTheme = getBotStreamerTheme($conn, $botId);
$bgGradientCSS = $botTheme['bgGradientCSS'];

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action === 'get_smart_bet') {
        header('Content-Type: application/json');
        $smartBet = calculateSmartBotBet($conn, $botId, 'history_daga', 30000);
        $sides = ['meron', 'wala', 'draw'];
        $side = $sides[rand(0, 2)];
        echo json_encode([
            'success' => true,
            'smartBet' => $smartBet,
            'side' => $side,
            'botName' => $userName
        ]);
        exit;
    }

    if ($action === 'bet') {
        header('Content-Type: application/json');
        $side = $_POST['side'] ?? 'meron';
        $amount = (int)($_POST['amount'] ?? 20000);

        $conn->begin_transaction();
        $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmtLock->bind_param("i", $botId);
        $stmtLock->execute();
        $botMoney = (float)($stmtLock->get_result()->fetch_assoc()['Money'] ?? 77777000);
        $stmtLock->close();

        $rand = rand(1, 100);
        $winner = ($rand <= 48) ? 'meron' : (($rand <= 96) ? 'wala' : 'draw');
        $finalWin = ($side === $winner) ? ($side === 'draw' ? $amount * 8 : $amount * 1) : -$amount;

        $newBotMoney = max(1000000, $botMoney + $finalWin);

        $up = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $up->bind_param("di", $newBotMoney, $botId);
        $up->execute();
        $up->close();

        $his = $conn->prepare("INSERT INTO history_daga (Iduser, BetSide, BetAmount, Winner, WinAmount, Time) VALUES (?, ?, ?, ?, ?, NOW())");
        $his->bind_param("isdsd", $botId, $side, $amount, $winner, $finalWin);
        $his->execute();
        $his->close();

        $conn->commit();

        echo json_encode([
            'success' => true,
            'winner' => $winner,
            'winAmount' => $finalWin,
            'newMoney' => number_format($newBotMoney, 0, ',', '.'),
            'rawMoney' => $newBotMoney
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>🐓 ĐẠI CHIẾN THẦN KÊ - Live Stream 24/7 🐓</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php echo getCSSIncludes(['special_effects' => true]); ?>
    <style>
        :root {
            --meron: #ff4757;
            --wala: #2e86de;
            --draw: #2ecc71;
            --tet-gold: #fdcb6e;
        }
        html, body, div, p, span, section, header, footer, aside, nav, table, tr, td, iframe, canvas {
            cursor: url('../img/chuot.png'), default !important;
        }
        a, button, input, select, textarea, label, .btn, [role="button"], [onclick], .clickable, .bet-btn {
            cursor: url('../img/tay.png'), pointer !important;
        }
        body {
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: white;
            font-family: 'Exo 2', sans-serif;
            text-align: center;
            overflow-x: hidden;
        }
        #threejs-background { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; pointer-events: none; }
        .arena {
            width: 95%;
            max-width: 900px;
            height: 450px;
            margin: 2rem auto;
            background: radial-gradient(circle, rgba(44, 62, 80, 0.5) 0%, rgba(26, 26, 26, 0.8) 100%);
            position: relative;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 3rem;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5), inset 0 0 100px rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }
        .rooster {
            width: 180px;
            height: 180px;
            position: absolute;
            bottom: 60px;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            filter: drop-shadow(0 10px 20px rgba(0,0,0,0.5));
        }
        #rooster-meron { left: 80px; transform: scaleX(-1); }
        #rooster-wala { right: 80px; transform: scaleX(1); }
        .rooster-label {
            position: absolute;
            bottom: -30px;
            left: 50%;
            transform: translateX(-50%);
            padding: 5px 20px;
            border-radius: 50px;
            font-weight: 900;
            font-size: 0.8rem;
            letter-spacing: 2px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .shaking { animation: shake 0.1s infinite; }
        @keyframes shake {
            0% { transform: translate(2px, 1px) scaleX(var(--sx)); }
            50% { transform: translate(-2px, -1px) scaleX(var(--sx)); }
            100% { transform: translate(2px, -1px) scaleX(var(--sx)); }
        }
        .jump { animation: jump 0.4s infinite; }
        @keyframes jump {
            0%, 100% { bottom: 60px; }
            50% { bottom: 120px; }
        }
        .betting-area {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-bottom: 3rem;
            flex-wrap: wrap;
            pointer-events: none !important;
        }
        .bet-btn {
            padding: 1.2rem 2.5rem;
            font-size: 1.2rem;
            font-weight: 900;
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 2rem;
            color: white;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .btn-meron { background: linear-gradient(135deg, var(--meron), #c0392b); }
        .btn-wala { background: linear-gradient(135deg, var(--wala), #191970); }
        .btn-draw { background: linear-gradient(135deg, var(--draw), #27ae60); }
        .status-overlay {
            position: absolute;
            top: 10%; left: 50%;
            transform: translateX(-50%);
            font-size: 3rem;
            font-weight: 900;
            text-shadow: 0 0 30px rgba(0,0,0,0.8);
            display: none;
            z-index: 100;
            letter-spacing: 5px;
            font-style: italic;
        }

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

    <h1 style="font-size: 3rem; font-weight: 900; margin: 2rem 0; letter-spacing: 5px; text-shadow: 0 0 20px rgba(255,255,255,0.2);">🐓 ĐẠI CHIẾN THẦN KÊ 🐓</h1>
    <div style="color: #ff4757; font-weight: 900; font-size: 1.2rem; margin-bottom: 5px;">🔴 LIVE STREAM 24/7 — STREAMER BOT: <?= $userName ?></div>
    <div class="balance" style="margin-bottom: 2rem;">💰 Ví Bot CSDL (bot_daga): <b id="money-display" style="color: var(--tet-gold); font-size: 1.5rem;"><?= number_format($money, 0, ',', '.') ?></b> GTLM</div>

    <div class="arena">
        <div id="rooster-meron" class="rooster" style="--sx: -1">
            <span style="font-size: 7rem; filter: drop-shadow(0 5px 15px rgba(255,0,0,0.5));">🐓</span>
            <div class="rooster-label" style="background: var(--meron);">MERON</div>
        </div>
        <div id="rooster-wala" class="rooster" style="--sx: 1">
            <span style="font-size: 7rem; filter: drop-shadow(0 5px 15px rgba(0,0,255,0.5));">🐓</span>
            <div class="rooster-label" style="background: var(--wala);">WALA</div>
        </div>
        <div id="status" class="status-overlay">FIGHT!</div>
    </div>

    <div class="controls">
        <input type="number" id="bet-amount" value="30000" readonly style="background: #000; color: #fff; border: 1px solid #444; padding: 0.8rem; font-size: 1.2rem; border-radius: 10px; width: 250px; text-align: center;"><br><br>
        <div class="betting-area">
            <button id="btn-meron" class="bet-btn btn-meron">CHIẾN MERON<br><small>1 húp 1</small></button>
            <button id="btn-draw" class="bet-btn btn-draw">HÒA (BDD)<br><small>1 húp 8</small></button>
            <button id="btn-wala" class="bet-btn btn-wala">CHIẾN WALA<br><small>1 húp 1</small></button>
        </div>
    </div>

    <script>
        let isPlaying = false;

        function placeBet(side) {
            if (isPlaying) return;
            isPlaying = true;
            
            $('#status').text('ĐANG CHIẾN...').fadeIn();
            $('#rooster-meron, #rooster-wala').addClass('shaking jump');
            
            $('#rooster-meron').animate({ left: '300px' }, 2000);
            $('#rooster-wala').animate({ right: '300px' }, 2000);

            const amt = parseInt($('#bet-amount').val()) || 30000;

            $.post('live_daga.php?action=bet', { side: side, amount: amt }, function(data) {
                if (!data.success) {
                    resetArena();
                    return;
                }

                setTimeout(() => {
                    $('#rooster-meron, #rooster-wala').removeClass('shaking jump');
                    $('#status').text(data.winner.toUpperCase() + ' HÚP!').css('color', data.winner === 'meron' ? 'red' : (data.winner === 'wala' ? 'blue' : 'green'));
                    
                    if (data.winner === 'meron') {
                        $('#rooster-wala').fadeOut();
                        $('#rooster-meron').animate({ left: '325px' }, 500);
                    } else if (data.winner === 'wala') {
                        $('#rooster-meron').fadeOut();
                        $('#rooster-wala').animate({ right: '325px' }, 500);
                    }

                    setTimeout(() => {
                        $('#money-display').text(data.newMoney);
                        resetArena();
                    }, 1000);
                }, 3000);
            });
        }

        function resetArena() {
            isPlaying = false;
            $('#status').hide();
            $('#rooster-meron').show().css('left', '100px');
            $('#rooster-wala').show().css('right', '100px');
        }

        // 🔄 AUTO-PLAY SPECTATOR LOOP WITH VIRTUAL MOUSE CURSOR GSAP ANIMATION 24/7
        function autoSpectatorLoop() {
            if (isPlaying) return;

            $.get('live_daga.php?action=get_smart_bet', function(res) {
                if (!res.success) return;
                $('#bet-amount').val(res.smartBet);

                const cursor = $('#botVirtualCursor');
                $('#cursorBotName').text(res.botName);

                gsap.set(cursor, { opacity: 1, left: 100, top: 100, scale: 1 });

                const targetBtn = $(`#btn-${res.side}`);
                if (targetBtn.length === 0) return;
                const offsetBtn = targetBtn.offset();

                gsap.to(cursor, {
                    left: offsetBtn.left + targetBtn.width() / 2,
                    top: offsetBtn.top + targetBtn.height() / 2,
                    duration: 0.9,
                    ease: "power2.out",
                    onComplete: () => {
                        gsap.to(cursor, { scale: 0.7, duration: 0.12, yoyo: true, repeat: 1, onComplete: () => {
                            placeBet(res.side);
                            gsap.to(cursor, { opacity: 0, delay: 0.5, duration: 0.4 });
                        }});
                    }
                });
            });
        }
        setTimeout(autoSpectatorLoop, 2000);
        setInterval(autoSpectatorLoop, 12000);

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
            ['threejs-background.js', 'assets/js/game-effects.js'].forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src; s.async = false;
                document.head.appendChild(s);
            });
        })();
    </script>
    <canvas id="threejs-background"></canvas>
</body>
</html>
