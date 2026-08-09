<?php
session_start();
include '../db_connect.php';
require_once '../include_css.php';
include '../load_theme.php';
require_once 'bot_streamer_helper.php';
require_once '../game_history_helper.php';

// Nạp thông tin tài khoản Bot thật 'bot_baucua' từ bảng users trong CSDL
$botUser = getOrCreateBotStreamerUser($conn, 'bot_baucua', 88888000);
$botId = $botUser['Iduser'];
$money = $botUser['Money'];
$userName = $botUser['Name'];

$botTheme = getBotStreamerTheme($conn, $botId);
$bgGradientCSS = $botTheme['bgGradientCSS'];

$animals = ["Chó", "Gà", "Mèo", "Cá", "Chim", "Heo"];
$emojis = ["Chó" => "🐶", "Gà" => "🐔", "Mèo" => "🐱", "Cá" => "🐟", "Chim" => "🐦", "Heo" => "🐷"];

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    
    if ($action === 'get_smart_bet') {
        $smartBet = calculateSmartBotBet($conn, $botId, 'history_baucua', 30000);
        $choice1 = $animals[rand(0, 5)];
        $choice2 = $animals[rand(0, 5)];
        echo json_encode([
            'success' => true,
            'smartBet' => $smartBet,
            'choices' => [$choice1, $choice2],
            'botName' => $userName
        ]);
        exit;
    }

    if ($action === 'play') {
        $bet = (float) ($_POST['bet'] ?? 0);
        $betsData = json_decode($_POST['bets'] ?? '[]', true);
        $totalBet = 0;
        foreach ($betsData as $b) {
            if ($b['amount'] > 0) $totalBet += $b['amount'];
        }
        
        $conn->begin_transaction();
        $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmtLock->bind_param("i", $botId);
        $stmtLock->execute();
        $botMoney = (float)($stmtLock->get_result()->fetch_assoc()['Money'] ?? 88888000);
        $stmtLock->close();

        // Roll 3 dice
        $results = [];
        for ($i = 0; $i < 3; $i++) $results[] = $animals[rand(0, 5)];
        $totalWin = 0;
        $winAnimals = array_count_values($results);
        foreach ($betsData as $b) {
            $a = $b['animal'];
            $amt = $b['amount'];
            if (isset($winAnimals[$a])) {
                $totalWin += $amt * ($winAnimals[$a] + 1);
            }
        }

        $netProfit = $totalWin - $totalBet;
        $newBotMoney = max(1000000, $botMoney + $netProfit);

        $up = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $up->bind_param("di", $newBotMoney, $botId);
        $up->execute();
        $up->close();

        $resStr = implode(', ', $results);
        $his = $conn->prepare("INSERT INTO history_baucua (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $his->bind_param("idss", $botId, $totalBet, $resStr, $netProfit);
        $his->execute();
        $his->close();

        $conn->commit();

        echo json_encode([
            'success' => true,
            'results' => $results,
            'winAmount' => number_format($totalWin, 0, ',', '.'),
            'money' => number_format($newBotMoney, 0, ',', '.'),
            'win' => ($totalWin > 0)
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chiến Trường Linh Thú - Live Stream 24/7</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php echo getCSSIncludes(['special_effects' => true]); ?>
    <style>
        :root {
            --primary: #00ff88;
            --accent: #f1c40f;
            --glass: rgba(255, 255, 255, 0.06);
        }
        html, body, div, p, span, section, header, footer, aside, nav, table, tr, td, iframe, canvas {
            cursor: url('../img/chuot.png'), default !important;
        }
        a, button, input, select, textarea, label, .btn, [role="button"], [onclick], .clickable, .animal-tile, .btn-action {
            cursor: url('../img/tay.png'), pointer !important;
        }
        body {
            margin: 0;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Inter', sans-serif;
            overflow: hidden;
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
            border-radius: 2rem;
            padding: 1.5rem;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.6);
            width: 95%;
            max-width: 1200px;
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 1.5rem;
            max-height: 92vh;
            align-self: center;
        }
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .game-area {
            position: relative;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .betting-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            width: 100%;
            max-width: 650px;
            margin-top: 20px;
            pointer-events: none !important;
        }
        .animal-tile {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1.5rem;
            padding: 15px;
            text-align: center;
            transition: 0.3s;
            position: relative;
            cursor: default;
            overflow: hidden;
        }
        .animal-tile.active {
            border-color: var(--primary);
            background: rgba(0, 255, 136, 0.1);
            box-shadow: 0 0 20px rgba(0, 255, 136, 0.2);
        }
        .animal-emoji {
            font-size: 3.5rem;
            display: block;
            margin-bottom: 5px;
            transition: 0.3s;
            filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.5));
        }
        .animal-name {
            font-family: 'Orbitron';
            font-weight: 900;
            font-size: 0.8rem;
            letter-spacing: 1px;
            opacity: 0.6;
        }
        .bet-amount-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--primary);
            color: #000;
            font-weight: 900;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-family: 'Orbitron';
            display: none;
        }
        .shaker-stage {
            width: 100%;
            height: 250px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            perspective: 1000px;
        }
        .bowl {
            width: 180px;
            height: 120px;
            background: radial-gradient(circle at 50% 20%, #ffffff, #888, #444);
            border-radius: 100px 100px 20px 20px;
            position: absolute;
            z-index: 20;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.8), inset 0 5px 15px rgba(255, 255, 255, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid rgba(255, 255, 255, 0.3);
            transform-origin: bottom center;
        }
        .shaking-sparks {
            position: absolute;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 25;
            display: none;
        }
        .spark {
            position: absolute;
            width: 2px;
            height: 15px;
            background: var(--primary);
            border-radius: 2px;
            box-shadow: 0 0 10px var(--primary);
        }
        .dice-result {
            display: flex;
            gap: 20px;
            z-index: 10;
        }
        .die {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
            transform: scale(0);
            opacity: 0;
        }
        .btn-action {
            padding: 1.2rem;
            border-radius: 1.5rem;
            border: none;
            font-weight: 900;
            font-size: 1.3rem;
            transition: 0.3s;
            text-transform: uppercase;
            background: linear-gradient(135deg, var(--primary), #00b894);
            color: #000;
            box-shadow: 0 10px 30px rgba(0, 255, 136, 0.3);
            width: 100%;
            pointer-events: none !important;
        }
        .stat-card {
            background: rgba(0, 0, 0, 0.3);
            padding: 1rem;
            border-radius: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            text-align: center;
        }
        .stat-card span {
            display: block;
            font-size: 0.6rem;
            opacity: 0.5;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .animal-tile.winner { border-color: #fff; background: rgba(0, 255, 136, 0.4); box-shadow: 0 0 50px var(--primary); animation: animal-bounce 0.6s infinite; z-index: 5; }
        @keyframes animal-bounce { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1) translateY(-10px); } }

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
            filter: drop-shadow(0 0 10px #00ff88) drop-shadow(0 0 20px #00ff88);
        }
        .cursor-bot-tag {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(11, 15, 25, 0.92);
            backdrop-filter: blur(8px);
            border: 1px solid #00ff88;
            color: #fff;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 900;
            white-space: nowrap;
            box-shadow: 0 4px 15px rgba(0, 255, 136, 0.4);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .bot-tag-dot {
            width: 6px;
            height: 6px;
            background: #00ff88;
            border-radius: 50%;
            box-shadow: 0 0 8px #00ff88;
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
                <path d="M3 3l7 18 3-7 7-3L3 3z" fill="#00ff88" stroke="#ffffff" stroke-width="2" stroke-linejoin="round"/>
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
                <div>
                    <h1 style="margin:0; font-size: 2.2rem; font-weight: 900; color: var(--primary); font-family: 'Orbitron'; letter-spacing: 2px;">CHIẾN TRƯỜNG LINH THÚ</h1>
                    <p style="margin:0; color:#ff4757; font-size: 0.75rem; letter-spacing: 1px; font-weight:900;">🔴 LIVE STREAM 24/7 — STREAMER BOT: <?= $userName ?></p>
                </div>
                <div class="stat-card">
                    <span>Ví Bot CSDL (bot_baucua)</span>
                    <div style="display:flex; align-items:baseline; justify-content:center; gap:5px;">
                        <b id="userMoney" style="color:var(--accent)"><?= number_format($money, 0, ',', '.') ?></b>
                        <small style="opacity:0.5; font-weight:900; font-size:0.6rem;">GTLM</small>
                    </div>
                </div>
                <div class="stat-card" style="background:rgba(0,255,136,0.05); border-color:rgba(0,255,136,0.1)">
                    <span>TỔNG BOT CƯỢC</span>
                    <div style="display:flex; align-items:baseline; justify-content:center; gap:5px;">
                        <b id="totalBet" style="color:var(--primary)">0</b>
                    </div>
                </div>
                <div style="margin-top:auto;">
                    <div class="stat-card" style="margin-bottom:10px; padding:0.8rem; border-color:rgba(255,255,255,0.1)">
                        <span>CƯỢC THÔNG MINH (SMART AI)</span>
                        <input type="number" id="customBet" value="30000" readonly style="background:none; border:none; color:var(--accent); font-family:'Orbitron'; font-size:1.2rem; font-weight:900; width:100%; text-align:center; outline:none;">
                    </div>
                    <button id="playBtn" class="btn-action">⚡ STREAMER BOT RA CHIÊU</button>
                </div>
            </div>
            <div class="game-area">
                <div class="shaker-stage">
                    <div class="dice-result">
                        <div class="die" id="die0">🐶</div>
                        <div class="die" id="die1">🐱</div>
                        <div class="die" id="die2">🐔</div>
                    </div>
                    <div class="bowl" id="bowl"></div>
                    <div class="shaking-sparks" id="shakingSparks"></div>
                </div>
                <div class="betting-grid">
                    <?php foreach ($animals as $a): ?>
                        <div class="animal-tile" data-animal="<?= $a ?>">
                            <span class="animal-emoji"><?= $emojis[$a] ?></span>
                            <span class="animal-name"><?= strtoupper($a) ?></span>
                            <span class="bet-amount-badge" id="bet-<?= $a ?>">0</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        let myBets = {};
        let isRolling = false;
        const animalEmojis = <?= json_encode($emojis) ?>;

        function placeBet(animal) {
            if (isRolling) return;
            let amt = parseInt($('#customBet').val()) || 30000;
            myBets[animal] = (myBets[animal] || 0) + amt;
            updateBetUI();
        }

        function clearBets() {
            if (isRolling) return;
            myBets = {};
            updateBetUI();
        }

        function updateBetUI() {
            let total = 0;
            $('.animal-tile').removeClass('active');
            $('.bet-amount-badge').hide();
            for (let a in myBets) {
                if (myBets[a] > 0) {
                    total += myBets[a];
                    $(`.animal-tile[data-animal="${a}"]`).addClass('active');
                    $(`#bet-${a}`).text(myBets[a].toLocaleString('vi-VN')).show();
                }
            }
            $('#totalBet').text(total.toLocaleString('vi-VN'));
        }

        function playGame() {
            let total = 0;
            const betsData = [];
            for (let a in myBets) {
                if (myBets[a] > 0) {
                    total += myBets[a];
                    betsData.push({ animal: a, amount: myBets[a] });
                }
            }
            if (total === 0 || isRolling) return;
            isRolling = true;
            $('#playBtn').text('ĐANG XÓC LIVE...');
            const bowl = $('#bowl'), sparks = $('#shakingSparks');
            $('.animal-tile').removeClass('winner');
            $('.die').css('opacity', 0);
            const tl = gsap.timeline();
            tl.to(bowl, { y: 0, rotateX: 0, rotateZ: 0, opacity: 1, duration: 0.4, ease: "bounce.out" })
              .add(() => {
                  sparks.show().empty();
                  for(let i=0; i<12; i++) {
                      $('<div class="spark"></div>').css({
                          left: Math.random()*100 + '%', top: Math.random()*100 + '%',
                          transform: `rotate(${Math.random()*360}deg)`
                      }).appendTo(sparks);
                  }
                  gsap.to('.spark', { opacity: 0, scale: 2, duration: 0.2, repeat: -1, stagger: 0.1 });
                  gsap.to(bowl, {
                      x: "random(-12, 12)", y: "random(-6, 6)", rotateZ: "random(-10, 10)",
                      duration: 0.1, repeat: -1, yoyo: true
                  });
              });

            $.post('live_baucua.php?action=play', { bets: JSON.stringify(betsData) }, function (res) {
                if (res.success) {
                    setTimeout(() => {
                        gsap.killTweensOf(bowl);
                        sparks.hide();
                        gsap.to(bowl, { y: -250, x: 100, rotateZ: 45, opacity: 0, duration: 0.8, ease: "power2.inOut" });
                        res.results.forEach((animal, i) => {
                            const die = $(`#die${i}`);
                            die.text(animalEmojis[animal]).css('opacity', 1);
                            gsap.fromTo(die, { scale: 0, rotate: -180 }, { scale: 1, rotate: 0, duration: 0.6, delay: i * 0.2, ease: "back.out(1.7)" });
                            $(`.animal-tile[data-animal="${animal}"]`).addClass('winner');
                        });
                        setTimeout(() => {
                            $('#userMoney').text(res.money);
                            if (res.win) {
                                if (window.GameEffects) window.GameEffects.showWin(parseInt(res.winAmount.replace(/\./g, '')));
                            }
                            setTimeout(() => {
                                gsap.to(bowl, { y: 0, x: 0, rotateZ: 0, opacity: 1, duration: 0.5 });
                                gsap.to('.die', { scale: 0, opacity: 0, duration: 0.3 });
                                $('.animal-tile').removeClass('winner');
                                $('#playBtn').text('⚡ STREAMER BOT RA CHIÊU');
                                isRolling = false;
                                clearBets();
                            }, 2500);
                        }, 1200);
                    }, 1500);
                } else {
                    gsap.killTweensOf(bowl);
                    sparks.hide();
                    isRolling = false;
                }
            });
        }

        // 🔄 AUTO-PLAY SPECTATOR LOOP WITH VIRTUAL MOUSE CURSOR GSAP ANIMATION 24/7
        function autoSpectatorLoop() {
            if (isRolling) return;

            $.get('live_baucua.php?action=get_smart_bet', function(res) {
                if (!res.success) return;
                const smartBet = res.smartBet;
                const choices = res.choices;
                $('#customBet').val(smartBet);

                const cursor = $('#botVirtualCursor');
                $('#cursorBotName').text(res.botName);

                // Initial position of Virtual Mouse Cursor
                gsap.set(cursor, { opacity: 1, left: 100, top: 100, scale: 1 });

                // Step 1: Move mouse cursor to target animal tile 1
                const targetTile = $(`.animal-tile[data-animal="${choices[0]}"]`);
                if (targetTile.length === 0) return;
                const offset1 = targetTile.offset();

                gsap.to(cursor, {
                    left: offset1.left + targetTile.width() / 2,
                    top: offset1.top + targetTile.height() / 2,
                    duration: 0.9,
                    ease: "power2.out",
                    onComplete: () => {
                        // Click animation press
                        gsap.to(cursor, { scale: 0.7, duration: 0.12, yoyo: true, repeat: 1, onComplete: () => {
                            placeBet(choices[0]);
                        }});

                        // Step 2: Move mouse cursor to target animal tile 2 (if different)
                        if (choices[1] && choices[1] !== choices[0]) {
                            const targetTile2 = $(`.animal-tile[data-animal="${choices[1]}"]`);
                            const offset2 = targetTile2.offset();
                            gsap.to(cursor, {
                                left: offset2.left + targetTile2.width() / 2,
                                top: offset2.top + targetTile2.height() / 2,
                                duration: 0.7,
                                delay: 0.3,
                                ease: "power2.out",
                                onComplete: () => {
                                    gsap.to(cursor, { scale: 0.7, duration: 0.12, yoyo: true, repeat: 1, onComplete: () => {
                                        placeBet(choices[1]);
                                        moveToPlayBtn();
                                    }});
                                }
                            });
                        } else {
                            setTimeout(moveToPlayBtn, 300);
                        }
                    }
                });

                function moveToPlayBtn() {
                    const playBtn = $('#playBtn');
                    const offsetBtn = playBtn.offset();
                    gsap.to(cursor, {
                        left: offsetBtn.left + playBtn.width() / 2,
                        top: offsetBtn.top + playBtn.height() / 2,
                        duration: 0.8,
                        delay: 0.3,
                        ease: "power2.inOut",
                        onComplete: () => {
                            gsap.to(cursor, { scale: 0.7, duration: 0.12, yoyo: true, repeat: 1, onComplete: () => {
                                playGame();
                                gsap.to(cursor, { opacity: 0, delay: 0.5, duration: 0.4 });
                            }});
                        }
                    });
                }
            });
        }

        setTimeout(autoSpectatorLoop, 1500);
        setInterval(autoSpectatorLoop, 14000);

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
    </script>
    <canvas id="threejs-background"></canvas>
</body>
</html>
