<?php
session_start();
include '../db_connect.php';
require_once '../include_css.php';
include '../load_theme.php';
require_once 'bot_streamer_helper.php';

// Nạp thông tin tài khoản Bot thật 'bot_dragontiger' từ bảng users trong CSDL
$botUser = getOrCreateBotStreamerUser($conn, 'bot_dragontiger', 99999000);
$botId = $botUser['Iduser'];
$money = $botUser['Money'];
$userName = $botUser['Name'];

$botTheme = getBotStreamerTheme($conn, $botId);
$bgGradientCSS = $botTheme['bgGradientCSS'];

$suits = ['♠', '♥', '♦', '♣'];
$values = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];

if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action === 'get_smart_bet') {
        header('Content-Type: application/json');
        $smartBet = calculateSmartBotBet($conn, $botId, 'history_dragontiger', 50000);
        $sides = ['dragon', 'tiger'];
        $side = $sides[rand(0, 1)];
        echo json_encode([
            'success' => true,
            'smartBet' => $smartBet,
            'side' => $side,
            'botName' => $userName
        ]);
        exit;
    }

    if ($action === 'deal') {
        header('Content-Type: application/json');
        $betDragon = (int)($_POST['betDragon'] ?? 0);
        $betTiger = (int)($_POST['betTiger'] ?? 0);
        $betTie = (int)($_POST['betTie'] ?? 0);
        $totalBet = $betDragon + $betTiger + $betTie;

        $conn->begin_transaction();
        $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmtLock->bind_param("i", $botId);
        $stmtLock->execute();
        $botMoney = (float)($stmtLock->get_result()->fetch_assoc()['Money'] ?? 99999000);
        $stmtLock->close();

        $dValIdx = rand(0, 12); $dSuitIdx = rand(0, 3);
        $tValIdx = rand(0, 12); $tSuitIdx = rand(0, 3);
        $dragonCard = ['val' => $values[$dValIdx], 'suit' => $suits[$dSuitIdx], 'score' => $dValIdx + 1];
        $tigerCard = ['val' => $values[$tValIdx], 'suit' => $suits[$tSuitIdx], 'score' => $tValIdx + 1];
        
        $totalReturn = 0;
        $winAmount = -$totalBet;
        if ($dragonCard['score'] > $tigerCard['score']) { $totalReturn += ($betDragon * 2); $winAmount += ($betDragon * 2); }
        elseif ($dragonCard['score'] < $tigerCard['score']) { $totalReturn += ($betTiger * 2); $winAmount += ($betTiger * 2); }
        else { $totalReturn += ($betTie * 9) + ($betDragon * 0.5) + ($betTiger * 0.5); $winAmount += ($betTie * 9) + ($betDragon * 0.5) + ($betTiger * 0.5); }

        $netProfit = $winAmount;
        $newBotMoney = max(1000000, $botMoney + $netProfit);

        $up = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $up->bind_param("di", $newBotMoney, $botId);
        $up->execute();
        $up->close();

        $conn->commit();

        $resSide = ($dragonCard['score'] > $tigerCard['score']) ? 'Dragon' : ($dragonCard['score'] < $tigerCard['score'] ? 'Tiger' : 'Tie');

        echo json_encode([
            'success' => true,
            'dragonCard' => $dragonCard,
            'tigerCard' => $tigerCard,
            'winSide' => $resSide,
            'winAmount' => $winAmount,
            'money' => number_format($newBotMoney, 0, ',', '.')
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Long Hổ Tranh Đấu - Live Stream 24/7</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <style>
        html, body, div, p, span, section, header, footer, aside, nav, table, tr, td, iframe, canvas {
            cursor: url('../img/chuot.png'), default !important;
        }
        a, button, input, select, textarea, label, .btn, [role="button"], [onclick], .clickable, .bet-zone, .btn-premium {
            cursor: url('../img/tay.png'), pointer !important;
        }
        body { background: <?= $bgGradientCSS ?>; background-attachment: fixed; color: #fff; font-family: 'Exo 2', sans-serif; min-height: 100vh; overflow-x: hidden; }
        #threejs-background { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; pointer-events: none; }
        .glass { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 2rem; }
        .table-area { display: flex; justify-content: center; align-items: center; gap: 1.5rem; margin: 2rem 0; flex-wrap: wrap; }
        .side-box { flex: 1; min-width: 280px; padding: 2rem; border-radius: 2rem; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); transition: all 0.4s; }
        .side-box.dragon { border-color: #3498db; }
        .side-box.tiger { border-color: #e74c3c; }
        .side-box.winner-dragon { box-shadow: 0 0 50px #3498db; background: rgba(52, 152, 219, 0.15); transform: scale(1.05); }
        .side-box.winner-tiger { box-shadow: 0 0 50px #e74c3c; background: rgba(231, 76, 60, 0.15); transform: scale(1.05); }
        .playing-card { width: clamp(100px, 15vw, 140px); aspect-ratio: 2/3; background: rgba(255, 255, 255, 0.05); border: 2px dashed rgba(255, 255, 255, 0.2); border-radius: 1rem; color: rgba(255,255,255,0.2); display: flex; flex-direction: column; justify-content: center; align-items: center; margin: 1.5rem auto; position: relative; transition: transform 0.6s; }
        .playing-card.revealed { background: #fff; border: none; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5); }
        .playing-card.red { color: #e74c3c; }
        .playing-card.black { color: #2c3e50; }
        .card-val { font-size: 1.8rem; position: absolute; top: 0.5rem; left: 0.8rem; font-weight: 900; }
        .card-suit { font-size: clamp(3rem, 8vw, 5rem); }
        .bet-area { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1.5rem; margin: 2.5rem 0; pointer-events: none !important; }
        .bet-zone { padding: 1.5rem; border-radius: 1.5rem; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.2); cursor: default; }
        .bet-zone.focused { background: rgba(255,255,255,0.1); transform: translateY(-5px); }
        .bet-label { font-size: 1.2rem; font-weight: 900; margin-bottom: 0.8rem; letter-spacing: 1px; }
        .dragon-label { color: #3498db; }
        .tiger-label { color: #e74c3c; }
        .tie-label { color: #f1c40f; }
        .bet-zone input { width: 100%; background: transparent; border: none; color: #fff; text-align: center; font-size: 1.5rem; font-weight: bold; outline: none; pointer-events: none; }
        .btn-premium { background: linear-gradient(135deg, #f1c40f 0%, #d35400 100%); border: none; padding: 1.2rem 4rem; border-radius: 50px; color: #fff; font-size: clamp(1.2rem, 4vw, 1.5rem); font-weight: 900; text-transform: uppercase; letter-spacing: 3px; width: 100%; max-width: 400px; margin: 20px 0; pointer-events: none !important; }
        .chip-selector { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-bottom: 15px; width: 100%; pointer-events: none !important; }
        .chip { padding: 8px 18px; background: rgba(255,255,255,0.1); border: 2px solid rgba(255,255,255,0.3); border-radius: 25px; font-weight: bold; font-size: 1rem; color: #fff; }

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
            filter: drop-shadow(0 0 10px #f1c40f) drop-shadow(0 0 20px #f1c40f);
        }
        .cursor-bot-tag {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(11, 15, 25, 0.92);
            backdrop-filter: blur(8px);
            border: 1px solid #f1c40f;
            color: #fff;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 900;
            white-space: nowrap;
            box-shadow: 0 4px 15px rgba(241, 196, 15, 0.4);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .bot-tag-dot {
            width: 6px;
            height: 6px;
            background: #f1c40f;
            border-radius: 50%;
            box-shadow: 0 0 8px #f1c40f;
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
                <path d="M3 3l7 18 3-7 7-3L3 3z" fill="#f1c40f" stroke="#ffffff" stroke-width="2" stroke-linejoin="round"/>
            </svg>
        </div>
        <div class="cursor-bot-tag">
            <span class="bot-tag-dot"></span>
            <span id="cursorBotName"><?= htmlspecialchars($userName) ?></span>
        </div>
    </div>

    <div class="game-wrapper" style="max-width:1000px; margin:2rem auto; position:relative; z-index:1; padding: 0 15px; width: 100%;">
        <div class="glass" style="padding: 2.5rem; text-align: center; border-radius: 2rem; width: 100%;">
            <h1 style="margin: 0 0 1rem; font-size: clamp(2.5rem, 5vw, 3.5rem); font-weight: 900; background: linear-gradient(45deg, #f1c40f, #e67e22, #f1c40f); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-transform: uppercase; letter-spacing: 2px;">DRAGON TIGER</h1>
            <div style="color: #ff4757; font-weight: 900; font-size: 1.2rem; margin-bottom: 15px;">🔴 LIVE STREAM 24/7 — STREAMER BOT: <?= $userName ?></div>

            <div style="background: rgba(0,0,0,0.3); padding: 10px 25px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.2); display: inline-block; margin-bottom: 2rem; max-width: 100%;">
                <span style="opacity: 0.8; font-size: 0.9rem; margin-right: 5px;">Ví Bot CSDL (bot_dragontiger):</span>
                <span id="balance-val" style="font-weight: 900; font-size: clamp(14px, 3vw, 1.5rem); color: #f1c40f;"><?php echo number_format($money, 0, ',', '.'); ?></span> <span style="font-weight: 900; font-size: clamp(14px, 3vw, 1.5rem); color: #f1c40f;">gtlm</span>
            </div>

            <div class="table-area">
                <div id="dragon-box" class="side-box dragon">
                    <div class="dragon-label bet-label" style="font-size: 2rem;">RỒNG (DRAGON)</div>
                    <div id="dragon-card" class="playing-card">
                        <div class="card-suit" style="font-size:4rem; color:rgba(255,255,255,0.2)">🐉</div>
                    </div>
                </div>

                <div style="font-size: 3rem; font-weight: 900; color: rgba(255,255,255,0.2);">VS</div>

                <div id="tiger-box" class="side-box tiger">
                    <div class="tiger-label bet-label" style="font-size: 2rem;">HỔ (TIGER)</div>
                    <div id="tiger-card" class="playing-card">
                        <div class="card-suit" style="font-size:4rem; color:rgba(255,255,255,0.2)">🐯</div>
                    </div>
                </div>
            </div>

            <div id="betting-section">
                <div class="chip-selector" id="chipSelector">
                    <div class="chip active" id="chipVal">50K</div>
                </div>

                <div class="bet-area">
                    <div class="bet-zone dragon-zone focused" id="box-dragon">
                        <div class="dragon-label bet-label">DRAGON (1:1)</div>
                        <input type="number" id="bet-dragon" value="50000">
                    </div>
                    <div class="bet-zone tie-zone" id="box-tie">
                        <div class="tie-label bet-label">TIE (1:8)</div>
                        <input type="number" id="bet-tie" value="0">
                    </div>
                    <div class="bet-zone tiger-zone" id="box-tiger">
                        <div class="tiger-label bet-label">TIGER (1:1)</div>
                        <input type="number" id="bet-tiger" value="0">
                    </div>
                </div>

                <button id="deal-btn" class="btn-premium">⚡ STREAMER BOT QUYẾT ĐẤU</button>
            </div>
            
            <div id="reset-section" style="display: none; margin-top: 2rem;">
                <button id="reset-btn" class="btn-premium" style="background: linear-gradient(135deg, #3498db, #2ecc71);">VÁN MỚI</button>
            </div>
        </div>
    </div>

    <canvas id="threejs-background"></canvas>
    <script>
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
            ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'].forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src; s.async = false;
                document.head.appendChild(s);
            });
        })();

        let isDealing = false;

        function renderCard(target, card) {
            const colorClass = (card.suit === '♥' || card.suit === '♦') ? 'red' : 'black';
            $(target).addClass('revealed ' + colorClass)
                     .html(`<div class="card-val">${card.val}</div><div class="card-suit">${card.suit}</div>`);
        }

        function triggerDeal(betSide, smartBet) {
            if (isDealing) return;
            isDealing = true;
            const betDragon = (betSide === 'dragon') ? smartBet : 0;
            const betTiger = (betSide === 'tiger') ? smartBet : 0;
            const betTie = (betSide === 'tie') ? smartBet : 0;

            $.post('live_dragontiger.php?action=deal', { betDragon: betDragon, betTiger: betTiger, betTie: betTie }, function(res) {
                if (res.success) {
                    $('#betting-section').hide();
                    renderCard('#dragon-card', res.dragonCard);
                    renderCard('#tiger-card', res.tigerCard);
                    
                    if (res.winSide === 'Dragon') $('#dragon-box').addClass('winner-dragon');
                    else if (res.winSide === 'Tiger') $('#tiger-box').addClass('winner-tiger');
                    
                    $('#balance-val').text(res.money);

                    setTimeout(() => {
                        $('#reset-section').show();
                        setTimeout(() => {
                            $('#reset-section').hide();
                            $('#betting-section').show();
                            $('#dragon-box').removeClass('winner-dragon winner-tie');
                            $('#tiger-box').removeClass('winner-tiger winner-tie');
                            $('#dragon-card').removeClass('revealed red black').html('<div class="card-suit" style="font-size:4rem; color:rgba(255,255,255,0.2)">🐉</div>');
                            $('#tiger-card').removeClass('revealed red black').html('<div class="card-suit" style="font-size:4rem; color:rgba(255,255,255,0.2)">🐯</div>');
                            isDealing = false;
                        }, 3000);
                    }, 1000);
                } else {
                    isDealing = false;
                }
            });
        }

        // 🔄 AUTO-PLAY SPECTATOR LOOP WITH VIRTUAL MOUSE CURSOR GSAP ANIMATION 24/7
        function autoSpectatorLoop() {
            if (isDealing) return;

            $.get('live_dragontiger.php?action=get_smart_bet', function(res) {
                if (!res.success) return;
                const smartBet = res.smartBet;
                const side = res.side;

                $('#chipVal').text((smartBet / 1000) + 'K');
                if (side === 'dragon') {
                    $('#bet-dragon').val(smartBet); $('#bet-tiger').val(0);
                } else {
                    $('#bet-tiger').val(smartBet); $('#bet-dragon').val(0);
                }

                const cursor = $('#botVirtualCursor');
                $('#cursorBotName').text(res.botName);

                gsap.set(cursor, { opacity: 1, left: 100, top: 100, scale: 1 });

                const targetZone = $(`#box-${side}`);
                if (targetZone.length === 0) return;
                const offsetZone = targetZone.offset();

                gsap.to(cursor, {
                    left: offsetZone.left + targetZone.width() / 2,
                    top: offsetZone.top + targetZone.height() / 2,
                    duration: 0.9,
                    ease: "power2.out",
                    onComplete: () => {
                        gsap.to(cursor, { scale: 0.7, duration: 0.12, yoyo: true, repeat: 1, onComplete: () => {
                            const dealBtn = $('#deal-btn');
                            const offsetBtn = dealBtn.offset();
                            gsap.to(cursor, {
                                left: offsetBtn.left + dealBtn.width() / 2,
                                top: offsetBtn.top + dealBtn.height() / 2,
                                duration: 0.8,
                                delay: 0.3,
                                ease: "power2.inOut",
                                onComplete: () => {
                                    gsap.to(cursor, { scale: 0.7, duration: 0.12, yoyo: true, repeat: 1, onComplete: () => {
                                        triggerDeal(side, smartBet);
                                        gsap.to(cursor, { opacity: 0, delay: 0.5, duration: 0.4 });
                                    }});
                                }
                            });
                        }});
                    }
                });
            });
        }
        setTimeout(autoSpectatorLoop, 1500);
        setInterval(autoSpectatorLoop, 12000);
    </script>
</body>
</html>
