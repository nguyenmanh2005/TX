<?php
session_start();
include '../db_connect.php';
require_once '../include_css.php';
include '../load_theme.php';
require_once 'bot_streamer_helper.php';
require_once '../game_history_helper.php';

// Nạp thông tin tài khoản Bot thật 'bot_xocdia' từ bảng users trong CSDL
$botUser = getOrCreateBotStreamerUser($conn, 'bot_xocdia', 99999000);
$botId = $botUser['Iduser'];
$money = $botUser['Money'];
$userName = $botUser['Name'];

$botTheme = getBotStreamerTheme($conn, $botId);
$bgGradientCSS = $botTheme['bgGradientCSS'];

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action']; $response = ['success' => false];
    
    if ($action === 'get_smart_bet') {
        $smartBet = calculateSmartBotBet($conn, $botId, 'history_xocdia', 30000);
        $choices = ['Stable', 'Volatile', 'Triple Negative', 'Triple Positive'];
        $choice1 = $choices[rand(0, 3)];
        echo json_encode([
            'success' => true,
            'smartBet' => $smartBet,
            'choice' => $choice1,
            'botName' => $userName
        ]);
        exit;
    }

    if ($action === 'play') {
        $betsData = json_decode($_POST['bets'] ?? '[]', true);
        $totalCharge = 0;
        foreach($betsData as $b) { if($b['amount'] > 0) $totalCharge += $b['amount']; }

        $conn->begin_transaction();
        $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmtLock->bind_param("i", $botId);
        $stmtLock->execute();
        $botMoney = (float)($stmtLock->get_result()->fetch_assoc()['Money'] ?? 99999000);
        $stmtLock->close();

        $orbs = [rand(0,1), rand(0,1), rand(0,1), rand(0,1)];
        $posCount = array_sum($orbs);
        $state = ($posCount % 2 === 0) ? 'Stable' : 'Volatile';
        
        $totalReward = 0;
        foreach($betsData as $b) {
            $c = $b['choice']; $amt = $b['amount'];
            if ($c === 'Stable' && $state === 'Stable') $totalReward += $amt * 1.96;
            elseif ($c === 'Volatile' && $state === 'Volatile') $totalReward += $amt * 1.96;
            elseif ($c === 'Full Negative' && $posCount === 0) $totalReward += $amt * 12;
            elseif ($c === 'Full Positive' && $posCount === 4) $totalReward += $amt * 12;
            elseif ($c === 'Triple Negative' && $posCount === 1) $totalReward += $amt * 3.5;
            elseif ($c === 'Triple Positive' && $posCount === 3) $totalReward += $amt * 3.5;
        }
        
        $netProfit = $totalReward - $totalCharge;
        $newBotMoney = max(1000000, $botMoney + $netProfit);

        $up = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $up->bind_param("di", $newBotMoney, $botId);
        $up->execute();
        $up->close();

        $resStr = "P: $posCount, N: ".(4-$posCount);
        $his = $conn->prepare("INSERT INTO history_xocdia (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $his->bind_param("idss", $botId, $totalCharge, $resStr, $netProfit);
        $his->execute();
        $his->close();

        $conn->commit();

        echo json_encode([
            'success'=>true, 'orbs'=>$orbs, 'state'=>$state, 'posCount'=>$posCount,
            'rewardAmount'=>number_format($totalReward,0,',','.'),
            'money'=>number_format($newBotMoney,0,',','.'), 'win'=>($totalReward > 0)
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quantum Pulse - Live Stream 24/7</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <style>
        :root { --primary: #00f2fe; --accent: #712cf9; --glass: rgba(255,255,255,0.06); }
        html, body, div, p, span, section, header, footer, aside, nav, table, tr, td, iframe, canvas {
            cursor: url('../img/chuot.png'), default !important;
        }
        a, button, input, select, textarea, label, .btn, [role="button"], [onclick], .clickable, .sync-option, .btn-pulse {
            cursor: url('../img/tay.png'), pointer !important;
        }
        body { margin:0; background: <?= $bgGradientCSS ?>; background-attachment: fixed; color:#fff; font-family:'Inter',sans-serif; overflow:hidden; }
        #threejs-background { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; pointer-events: none; }
        .main-container { height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; box-sizing:border-box; }
        .glass-card { background:var(--glass); backdrop-filter:blur(30px); border:1px solid rgba(255,255,255,0.1); border-radius:2rem; padding:1.5rem; box-shadow:0 40px 100px rgba(0,0,0,0.8); width:95%; max-width:1200px; display:grid; grid-template-columns:320px 1fr; gap:1.5rem; height:90vh; }
        .sidebar { display:flex; flex-direction:column; gap:1rem; }
        .chamber-area { position:relative; background:rgba(0,0,0,0.5); border-radius:2rem; border:1px solid rgba(0,242,254,0.2); overflow:hidden; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:20px; }
        .stat-card { background:rgba(0,0,0,0.3); padding:1rem; border-radius:1.5rem; border:1px solid rgba(255,255,255,0.05); text-align:center; }
        .stat-card span { display:block; font-size:0.6rem; opacity:0.5; font-weight:700; text-transform:uppercase; margin-bottom:5px; }
        .stat-card b { font-size:1.3rem; font-family:'Orbitron'; color:var(--primary); }
        .sync-board { display:grid; grid-template-columns: repeat(2, 1fr); gap:10px; width:100%; margin-top:20px; pointer-events:none !important; }
        .sync-option { background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.1); border-radius:1.2rem; padding:15px; text-align:center; transition:0.3s; position:relative; cursor:default; overflow:hidden; }
        .sync-option.active { border-color:var(--primary); background:rgba(0,242,254,0.1); }
        .sync-option b { display:block; font-family:'Orbitron'; font-size:1.1rem; margin-bottom:5px; color: var(--primary); }
        .sync-option span { font-size:0.7rem; opacity:0.5; }
        .charge-amt { position:absolute; top:5px; right:10px; font-size:0.7rem; font-weight:900; color:var(--primary); display:none; }
        .quantum-chamber { width:300px; height:300px; position:relative; display:flex; align-items:center; justify-content:center; }
        .containment-field { width:220px; height:220px; background:radial-gradient(circle, rgba(0,242,254,0.1) 0%, rgba(0,0,0,0.4) 100%); border-radius:50%; border:2px solid var(--primary); display:grid; grid-template-columns: repeat(2, 45px); grid-template-rows: repeat(2, 45px); align-content:center; justify-content:center; gap:20px; position:relative; }
        .vacuum-gate { width:240px; height:240px; background:rgba(0,0,0,0.6); border-radius:50%; position:absolute; z-index:20; border:2px solid #333; backdrop-filter:blur(5px); display:flex; align-items:center; justify-content:center; }
        .vacuum-gate::after { content:'⚡'; font-size:60px; color:var(--primary); opacity:0.3; }
        .orb { width:45px; height:45px; border-radius:50%; position:relative; transition:0.6s; }
        .orb.negative { background: #007bff; box-shadow: 0 0 15px #007bff; border: 3px solid #fff; }
        .orb.positive { background: #ff4757; box-shadow: 0 0 15px #ff4757; border: 3px solid #fff; }
        .btn-pulse { padding:1.2rem; border-radius:1.5rem; border:none; font-weight:900; font-size:1.3rem; transition:0.3s; text-transform:uppercase; background:linear-gradient(135deg, #007bff, #ff4757); color:#fff; width:100%; font-family:'Orbitron'; pointer-events:none !important; }

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
            filter: drop-shadow(0 0 10px #00f2fe) drop-shadow(0 0 20px #00f2fe);
        }
        .cursor-bot-tag {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(11, 15, 25, 0.92);
            backdrop-filter: blur(8px);
            border: 1px solid #00f2fe;
            color: #fff;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 900;
            white-space: nowrap;
            box-shadow: 0 4px 15px rgba(0, 242, 254, 0.4);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .bot-tag-dot {
            width: 6px;
            height: 6px;
            background: #00f2fe;
            border-radius: 50%;
            box-shadow: 0 0 8px #00f2fe;
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
                <path d="M3 3l7 18 3-7 7-3L3 3z" fill="#00f2fe" stroke="#ffffff" stroke-width="2" stroke-linejoin="round"/>
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
                    <h1 style="margin:0; font-size: 2rem; font-weight: 900; color: var(--primary); font-family: 'Orbitron';">TRẬN ĐỊA TRẮNG ĐỎ</h1>
                    <p style="margin:0; color:#ff007f; font-size: 0.75rem; letter-spacing: 1px; font-weight:900;">🔴 LIVE STREAM 24/7 — STREAMER BOT: <?= $userName ?></p>
                </div>
                <div class="stat-card">
                    <span>Ví Bot CSDL (bot_xocdia)</span>
                    <b id="userMoney"><?= number_format($money, 0, ',', '.') ?> GTLM</b>
                </div>
                <div class="stat-card">
                    <span>TỔNG BOT CƯỢC</span>
                    <b id="totalCharge">0 GTLM</b>
                </div>
                <div style="margin-top:auto;">
                    <div class="stat-card" style="margin-bottom:10px;">
                        <span>CƯỢC THÔNG MINH (SMART AI)</span>
                        <input type="number" id="customCharge" value="30000" readonly style="background:none; border:none; color:var(--primary); font-family:'Orbitron'; font-size:1.2rem; font-weight:900; width:100%; text-align:center; outline:none;">
                    </div>
                    <button id="pulseBtn" class="btn-pulse">⚡ STREAMER BOT KÍCH XUNG</button>
                </div>
            </div>
            <div class="chamber-area">
                <div class="quantum-chamber">
                    <div class="vacuum-gate" id="vacuumGate"></div>
                    <div class="containment-field" id="containmentField">
                        <div class="orb positive"></div>
                        <div class="orb negative"></div>
                        <div class="orb positive"></div>
                        <div class="orb negative"></div>
                    </div>
                </div>
                <div class="sync-board">
                    <div class="sync-option" data-choice="Stable" id="opt-Stable">
                        <b>STABLE (CHẮN)</b><span>2 Positive / 2 Negative (1.96x)</span>
                        <div class="charge-amt" id="amt-Stable">0</div>
                    </div>
                    <div class="sync-option" data-choice="Volatile" id="opt-Volatile">
                        <b>VOLATILE (LẺ)</b><span>3:1 / 1:3 Distribution (1.96x)</span>
                        <div class="charge-amt" id="amt-Volatile">0</div>
                    </div>
                    <div class="sync-option" data-choice="Triple Negative" id="opt-Triple Negative">
                        <b>TRIPLE NEGATIVE</b><span>3 Negative + 1 Positive (3.5x)</span>
                        <div class="charge-amt" id="amt-Triple Negative">0</div>
                    </div>
                    <div class="sync-option" data-choice="Triple Positive" id="opt-Triple Positive">
                        <b>TRIPLE POSITIVE</b><span>3 Positive + 1 Negative (3.5x)</span>
                        <div class="charge-amt" id="amt-Triple Positive">0</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let myCharges = {};
        let isSyncing = false;

        function placeCharge(choice) {
            if (isSyncing) return;
            let amt = parseInt($('#customCharge').val()) || 30000;
            myCharges[choice] = (myCharges[choice] || 0) + amt;
            updateUI();
        }

        function updateUI() {
            let total = 0;
            $('.sync-option').removeClass('active');
            $('.charge-amt').hide();
            for (let c in myCharges) {
                if (myCharges[c] > 0) {
                    total += myCharges[c];
                    $(`#opt-${c.replace(/ /g, '\\ ')}`).addClass('active');
                    $(`#amt-${c.replace(/ /g, '\\ ')}`).text(myCharges[c].toLocaleString('vi-VN')).show();
                }
            }
            $('#totalCharge').text(total.toLocaleString('vi-VN') + ' GTLM');
        }

        function triggerPulse() {
            let total = 0;
            const chargesData = [];
            for (let c in myCharges) {
                if (myCharges[c] > 0) {
                    total += myCharges[c];
                    chargesData.push({ choice: c, amount: myCharges[c] });
                }
            }
            if (total === 0 || isSyncing) return;
            isSyncing = true;
            $('#pulseBtn').text('ĐANG KÍCH XUNG...');
            const gate = $('#vacuumGate');
            const tl = gsap.timeline();
            tl.to(gate, { y: 0, opacity: 1, duration: 0.3 })
              .to(gate, { x: "random(-10, 10)", y: "random(-5, 5)", duration: 0.1, repeat: 20, yoyo: true });

            $.post('live_xocdia.php?action=play', { bets: JSON.stringify(chargesData) }, function(res) {
                if (res.success) {
                    setTimeout(() => {
                        gsap.to(gate, { y: -300, opacity: 0, duration: 0.8, ease: "power2.inOut" });
                        $('#containmentField').empty();
                        res.orbs.forEach((o, i) => {
                            const orb = $(`<div class="orb ${o === 1 ? 'positive' : 'negative'}"></div>`).appendTo('#containmentField');
                            gsap.from(orb, { scale: 0.5, opacity: 0, duration: 0.4, delay: i * 0.05 });
                        });
                        setTimeout(() => {
                            $('#userMoney').text(res.money + ' GTLM');
                            setTimeout(() => {
                                gsap.to(gate, { y: 0, opacity: 1, duration: 0.5 });
                                $('#pulseBtn').text('⚡ STREAMER BOT KÍCH XUNG');
                                isSyncing = false;
                                myCharges = {};
                                updateUI();
                            }, 2500);
                        }, 1000);
                    }, 2000);
                } else {
                    isSyncing = false;
                }
            });
        }

        // 🔄 AUTO-PLAY SPECTATOR LOOP WITH VIRTUAL MOUSE CURSOR GSAP ANIMATION 24/7
        function autoSpectatorLoop() {
            if (isSyncing) return;

            $.get('live_xocdia.php?action=get_smart_bet', function(res) {
                if (!res.success) return;
                const smartBet = res.smartBet;
                const choice = res.choice;
                $('#customCharge').val(smartBet);

                const cursor = $('#botVirtualCursor');
                $('#cursorBotName').text(res.botName);

                gsap.set(cursor, { opacity: 1, left: 100, top: 100, scale: 1 });

                const targetOpt = $(`#opt-${choice.replace(/ /g, '\\ ')}`);
                if (targetOpt.length === 0) return;
                const offsetOpt = targetOpt.offset();

                gsap.to(cursor, {
                    left: offsetOpt.left + targetOpt.width() / 2,
                    top: offsetOpt.top + targetOpt.height() / 2,
                    duration: 0.9,
                    ease: "power2.out",
                    onComplete: () => {
                        gsap.to(cursor, { scale: 0.7, duration: 0.12, yoyo: true, repeat: 1, onComplete: () => {
                            placeCharge(choice);
                            
                            const pulseBtn = $('#pulseBtn');
                            const offsetBtn = pulseBtn.offset();
                            gsap.to(cursor, {
                                left: offsetBtn.left + pulseBtn.width() / 2,
                                top: offsetBtn.top + pulseBtn.height() / 2,
                                duration: 0.8,
                                delay: 0.3,
                                ease: "power2.inOut",
                                onComplete: () => {
                                    gsap.to(cursor, { scale: 0.7, duration: 0.12, yoyo: true, repeat: 1, onComplete: () => {
                                        triggerPulse();
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
