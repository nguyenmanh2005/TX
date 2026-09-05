<?php
session_start();
require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_61', 50000000);
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
// Fallback theme - màu vàng huyền bí
$particleColor = $particleColor ?? '#a855f7';
$shapeColors   = $shapeColors   ?? ['#a855f7','#fbbf24','#3b82f6','#ef4444'];
$bgGradient    = $bgGradient    ?? ['#030611','#0d0821','#0a0020'];
if (empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg,'.$bgGradient[0].' 0%,'.$bgGradient[1].' 50%,'.($bgGradient[2]??$bgGradient[1]).' 100%)';
}
// AJAX handler - Tower battle
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    if ($_GET['action'] === 'battle') {
        $char   = $_POST['char']   ?? 'kiem_thanh';
        $floor  = (int)($_POST['floor'] ?? 1);
        $bet    = (int)($_POST['bet']   ?? 10000);

        $conn->begin_transaction();
        $st = $conn->prepare("SELECT Money FROM users WHERE Iduser=? FOR UPDATE");
        $st->bind_param("i", $userId); $st->execute();
        $locked = $st->get_result()->fetch_assoc()['Money'] ?? 0; $st->close();
        if ($bet <= 0 || $bet > $locked) { $conn->rollback(); echo json_encode(['success'=>false,'message'=>'GTLM không đủ!']); exit; }

        // Probability table by floor (simplified)
        $boss = ($floor % 10 === 0);
        $pJackpot = $boss ? 0.05 : ($floor <= 10 ? 0.10 : ($floor <= 25 ? 0.08 : ($floor <= 50 ? 0.06 : 0.04)));
        $pWin3    = $boss ? 0.15 : ($floor <= 10 ? 0.20 : ($floor <= 25 ? 0.15 : ($floor <= 50 ? 0.12 : 0.08)));
        $pWin     = $boss ? 0.20 : ($floor <= 10 ? 0.30 : ($floor <= 25 ? 0.25 : ($floor <= 50 ? 0.22 : 0.14)));
        $pHalf    = $boss ? 0.12 : ($floor <= 10 ? 0.15 : ($floor <= 25 ? 0.18 : 0.18));
        $pRetry   = $boss ? 0.10 : 0.12;
        $pDeath   = 1 - $pJackpot - $pWin3 - $pWin - $pHalf - $pRetry;
        $pDeath   = max(0, $pDeath);

        // Character bonus
        if ($char === 'phap_su')   { $pJackpot += 0.05; $pDeath -= 0.05; }
        if ($char === 'cung_thu')  { $pWin3 += 0.05; $pWin += 0.05; $pDeath -= 0.10; }
        if ($char === 'ninja')     { $pDeath -= 0.05; $pWin += 0.05; }
        $pDeath = max(0, $pDeath);

        // Roll
        $roll = mt_rand(0, 9999) / 10000;
        $result = 'death';
        $cum = 0;
        $outcomes = ['jackpot'=>$pJackpot,'win3'=>$pWin3,'win'=>$pWin,'half'=>$pHalf,'retry'=>$pRetry,'death'=>$pDeath];
        foreach ($outcomes as $k => $p) { $cum += $p; if ($roll < $cum) { $result = $k; break; } }

        $mult = ['jackpot'=>5,'win3'=>3,'win'=>1,'half'=>-0.5,'retry'=>0,'death'=>-1][$result] ?? -1;
        $winAmount = round($bet * $mult);
        if ($result === 'retry') $winAmount = 0;

        $newMoney = $locked + $winAmount;
        if ($result !== 'retry') {
            $conn->query("UPDATE users SET Money = $newMoney WHERE Iduser = $userId");
        } else {
            $newMoney = $locked;
        }
        $conn->commit();

        $nextFloor = in_array($result, ['jackpot','win3','win']) ? $floor + 1 : ($result === 'death' ? 1 : $floor);
        $labels = ['jackpot'=>'JACKPOT — ĐẠI THẮNG HUYỀN THOẠI!','win3'=>'THẮNG LỚNX3 — LÊN TẦNG!','win'=>'CHIẾN THẮNG — LÊN TẦNG!','half'=>'NỬA THẮNG — GIỮ NGUYÊN','retry'=>'HÒA — THỬ LẠI MIỄN PHÍ','death'=>'TỬ THẦN — BAY MÀU & RESET!'];
        echo json_encode(['success'=>true,'result'=>$result,'label'=>$labels[$result],'winAmount'=>$winAmount,'floor'=>$floor,'nextFloor'=>$nextFloor,'money'=>number_format($newMoney,0,',','.'),'isBoss'=>$boss]);
    } else { echo json_encode(['success'=>false,'message'=>'invalid']); }
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tháp Thần Bài - Bàn Live 61</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700;900&family=Outfit:wght@400;600;800;900&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box}
        body{margin:0;background:<?=$bgGradientCSS?>;background-attachment:fixed;color:#fff;font-family:'Outfit',sans-serif;min-height:100vh;overflow-x:hidden;display:flex;flex-direction:column;align-items:center}
        #threejs-background{position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:-1;pointer-events:none}
        #result-status-badge{position:fixed;top:22%;left:50%;transform:translate(-50%,-50%) scale(0.8);display:none;align-items:center;gap:12px;padding:12px 28px;border-radius:50px;font-size:20px;font-weight:800;letter-spacing:1px;text-transform:uppercase;box-shadow:0 10px 30px rgba(0,0,0,0.6);z-index:9999;pointer-events:none;transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275);opacity:0;backdrop-filter:blur(10px)}
        #result-status-badge.show{opacity:1;transform:translate(-50%,-50%) scale(1)}
        #result-status-badge.badge-win{background:linear-gradient(135deg,rgba(16,185,129,0.95),rgba(5,150,105,0.95));border:2px solid #34d399;box-shadow:0 0 35px rgba(16,185,129,0.7)}
        #result-status-badge.badge-jackpot{background:linear-gradient(135deg,rgba(168,85,247,0.98),rgba(124,58,237,0.98));border:2px solid #c084fc;color:#fff;box-shadow:0 0 60px rgba(168,85,247,1);animation:pulseGlow .7s infinite alternate}
        #result-status-badge.badge-lose{background:linear-gradient(135deg,rgba(239,68,68,0.9),rgba(185,28,28,0.9));border:2px solid #f87171;box-shadow:0 0 30px rgba(239,68,68,0.6)}
        #result-status-badge.badge-tie{background:linear-gradient(135deg,rgba(59,130,246,0.95),rgba(37,99,235,0.95));border:2px solid #60a5fa;box-shadow:0 0 30px rgba(59,130,246,0.7)}
        @keyframes pulseGlow{from{transform:translate(-50%,-50%) scale(1)}to{transform:translate(-50%,-50%) scale(1.08);filter:brightness(1.3)}}
        .header-bar{width:100%;padding:8px 24px;display:flex;justify-content:space-between;align-items:center;background:rgba(0,0,0,0.6);backdrop-filter:blur(15px);border-bottom:2px solid #a855f7;box-sizing:border-box}
        .logo-tower{font-family:'Cinzel Decorative',serif;font-size:15px;font-weight:900;color:#fbbf24;letter-spacing:2px;text-shadow:0 0 14px rgba(251,191,36,.5)}
        .user-money{background:rgba(0,0,0,.5);padding:5px 18px;border-radius:30px;border:1px solid #a855f7;font-weight:800;color:#c084fc;font-size:15px}
        .game-wrapper{max-width:860px;margin:1rem auto;padding:0 12px;width:100%}
        .glass{background:rgba(3,6,17,.85);backdrop-filter:blur(20px);border:1px solid rgba(168,85,247,.2);border-radius:1.6rem;padding:1.4rem 1.8rem;box-shadow:0 20px 60px rgba(0,0,0,.7)}
        .section-title{font-family:'Cinzel Decorative',serif;font-size:.75rem;letter-spacing:3px;color:#fbbf24;opacity:.7;text-align:center;margin-bottom:14px;text-transform:uppercase}

        /* Floor display */
        .floor-display{text-align:center;margin-bottom:16px}
        .floor-num{font-family:'Cinzel Decorative',serif;font-size:clamp(3rem,8vw,5rem);font-weight:900;line-height:1;background:linear-gradient(180deg,#fff 0%,#fbbf24 60%,#d97706 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;filter:drop-shadow(0 0 20px rgba(251,191,36,.45));animation:glowPulse 2.5s ease-in-out infinite}
        @keyframes glowPulse{0%,100%{filter:drop-shadow(0 0 15px rgba(251,191,36,.35))}50%{filter:drop-shadow(0 0 35px rgba(251,191,36,.75))}}
        .floor-label{font-size:.75rem;opacity:.5;text-transform:uppercase;letter-spacing:2px}
        .boss-badge{display:inline-block;padding:4px 14px;background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.5);border-radius:20px;color:#f87171;font-size:.75rem;font-weight:900;letter-spacing:2px;margin-top:4px}

        /* Char selector */
        .char-row{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-bottom:16px}
        .char-btn{padding:6px 14px;border-radius:20px;border:1px solid rgba(168,85,247,.3);background:rgba(168,85,247,.08);color:#c084fc;font-size:.78rem;font-weight:700;cursor:pointer;transition:.2s;display:flex;align-items:center;gap:4px}
        .char-btn.active,.char-btn:hover{background:#a855f7;border-color:#a855f7;color:#fff}

        /* Bet & action */
        .bet-row{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:center;margin-bottom:14px}
        .bet-input{background:rgba(0,0,0,.5);border:1px solid rgba(168,85,247,.4);border-radius:8px;padding:7px 12px;color:#c084fc;font-size:1rem;font-weight:900;width:130px;outline:none}
        .quick-bets{display:flex;gap:5px;flex-wrap:wrap}
        .q-btn{padding:3px 9px;border-radius:14px;border:1px solid rgba(168,85,247,.25);background:rgba(168,85,247,.06);color:#c084fc;font-size:.72rem;font-weight:700;cursor:pointer;transition:.2s}
        .q-btn:hover{background:rgba(168,85,247,.25)}
        .btn-battle{padding:11px 36px;border:none;border-radius:40px;background:linear-gradient(135deg,#a855f7,#7c3aed);color:#fff;font-weight:900;font-size:1rem;cursor:pointer;transition:.3s;box-shadow:0 6px 25px rgba(168,85,247,.5);text-transform:uppercase;letter-spacing:1px;font-family:'Outfit',sans-serif}
        .btn-battle:hover:not(:disabled){transform:translateY(-2px) scale(1.04);filter:brightness(1.1)}
        .btn-battle:disabled{opacity:.45;cursor:not-allowed}

        /* Wheel preview */
        .wheel-area{background:rgba(0,0,0,.4);border:1px solid rgba(168,85,247,.15);border-radius:1rem;padding:20px;text-align:center;min-height:150px;display:flex;flex-direction:column;align-items:center;justify-content:center;margin-bottom:14px;position:relative;overflow:hidden}
        .spin-anim{font-size:3rem;animation:spinWheel 1s linear infinite}
        @keyframes spinWheel{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
        .result-text{font-size:1.1rem;font-weight:800;margin-top:8px;min-height:30px}

        /* Odds chips */
        .odds-row{display:flex;flex-wrap:wrap;gap:6px;justify-content:center;margin-bottom:12px}
        .odd-chip{padding:4px 10px;border-radius:10px;font-size:.7rem;font-weight:800}
        .odd-jackpot{background:rgba(168,85,247,.25);color:#c084fc}
        .odd-win{background:rgba(16,185,129,.2);color:#34d399}
        .odd-half{background:rgba(251,191,36,.15);color:#fbbf24}
        .odd-death{background:rgba(239,68,68,.2);color:#f87171}
        .odd-retry{background:rgba(100,116,139,.2);color:#94a3b8}

        .stats-bar{display:flex;gap:20px;justify-content:center;margin-top:4px;flex-wrap:wrap}
        .stat-item{text-align:center}
        .stat-lbl{font-size:.68rem;opacity:.5;text-transform:uppercase;letter-spacing:1px}
        .stat-val{font-family:'Cinzel Decorative',serif;font-size:1rem;font-weight:900;color:#fbbf24}
        .home-link{display:none!important}
    </style>
</head>
<body>
    <canvas id="threejs-background"></canvas>
    <div id="result-status-badge"><span class="badge-icon">⚔️</span><span class="badge-text">CHIẾN THẮNG</span></div>
    <header class="header-bar">
        <div class="logo-tower">🗼 THÁP THẦN BÀI</div>
        <div class="user-money">💰 <span id="balance-val"><?=number_format($money,0,',','.')?></span> GTLM</div>
        <div style="font-size:13px;color:#aaa">STREAMER: <b style="color:#c084fc"><?=htmlspecialchars($userName)?></b></div>
    </header>
    <div class="game-wrapper"><div class="glass">
        <div class="section-title">Tháp Thần Bài — Vận Mệnh Chi Lộ</div>

        <!-- Floor display -->
        <div class="floor-display">
            <div class="floor-label">TẦNG HIỆN TẠI</div>
            <div class="floor-num" id="floorNum">1</div>
            <div id="bossTag" style="display:none"><span class="boss-badge">⚡ BOSS TẦNG</span></div>
        </div>

        <!-- Char selector -->
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;opacity:.6;text-align:center;margin-bottom:8px;">Chọn Nhân Vật</div>
        <div class="char-row" id="charRow">
            <button class="char-btn active" data-char="kiem_thanh">⚔️ Kiếm Thần</button>
            <button class="char-btn" data-char="phap_su">🔮 Pháp Sư</button>
            <button class="char-btn" data-char="cung_thu">🏹 Cung Thủ</button>
            <button class="char-btn" data-char="ninja">🥷 Ninja</button>
            <button class="char-btn" data-char="bao_than">💥 Bạo Thần</button>
            <button class="char-btn" data-char="ma_kiem_si">🌙 Ma Kiếm</button>
        </div>

        <!-- Odds display -->
        <div class="odds-row" id="oddsRow">
            <span class="odd-chip odd-jackpot">🌟 JACKPOT x5</span>
            <span class="odd-chip odd-win">⬆️ WIN x3</span>
            <span class="odd-chip odd-win">✅ WIN x1</span>
            <span class="odd-chip odd-half">💫 HALF</span>
            <span class="odd-chip odd-retry">🔄 RETRY</span>
            <span class="odd-chip odd-death">💀 DEATH</span>
        </div>

        <!-- Wheel area -->
        <div class="wheel-area" id="wheelArea">
            <div id="spinEl" style="font-size:3rem;opacity:.4">🎡</div>
            <div class="result-text" id="resultText">Bot đang leo tháp...</div>
        </div>

        <!-- Bet row -->
        <div class="bet-row">
            <div style="display:flex;flex-direction:column;gap:5px">
                <div style="font-size:.7rem;font-weight:700;opacity:.6;text-transform:uppercase">GTLM Cược/Tầng</div>
                <input type="number" id="betAmt" class="bet-input" value="10000" min="1000" step="1000">
                <div class="quick-bets">
                    <button class="q-btn" onclick="setBet(10000)">10K</button>
                    <button class="q-btn" onclick="setBet(50000)">50K</button>
                    <button class="q-btn" onclick="setBet(100000)">100K</button>
                    <button class="q-btn" onclick="setBet(500000)">500K</button>
                </div>
            </div>
            <button class="btn-battle" id="battleBtn">⚔️ CHIẾN!</button>
        </div>

        <div class="stats-bar">
            <div class="stat-item"><div class="stat-lbl">Tầng Cao Nhất</div><div class="stat-val" id="bestFloor">1</div></div>
            <div class="stat-item"><div class="stat-lbl">Tổng Thắng</div><div class="stat-val" id="totalWin">0</div></div>
            <div class="stat-item"><div class="stat-lbl">Jackpot Hits</div><div class="stat-val" id="jpHits">0</div></div>
            <div class="stat-item"><div class="stat-lbl">Lần Leo</div><div class="stat-val" id="runCount">0</div></div>
        </div>
    </div></div>

    <script>
        window.themeConfig={particleCount:<?=$particleCount??1000?>,particleSize:<?=$particleSize??0.05?>,particleColor:'<?=$particleColor??"#a855f7"?>',particleOpacity:<?=$particleOpacity??0.7?>,shapeCount:<?=$shapeCount??12?>,shapeColors:<?=json_encode($shapeColors??["#a855f7","#fbbf24","#3b82f6","#ef4444"])?>,shapeOpacity:<?=$shapeOpacity??0.35?>,bgGradient:<?=json_encode($bgGradient??["#030611","#0d0821","#0a0020"])?>};
    </script>
    <script src="../threejs-background.js"></script>
    <script src="../assets/js/game-effects.js"></script>
    <script src="../assets/js/game-effects-auto.js"></script>
    <script>
        let currentFloor=1, bestFloor=1, totalWon=0, jpHits=0, runCount=0;
        function setBet(v){$('#betAmt').val(v);}

        $('#charRow .char-btn').click(function(){
            $('#charRow .char-btn').removeClass('active');
            $(this).addClass('active');
        });

        function updateFloorDisplay(floor, isBoss){
            $('#floorNum').text(floor);
            if(isBoss) $('#bossTag').show(); else $('#bossTag').hide();
        }

        function showRS(type, text, icon){
            const b=document.getElementById('result-status-badge');
            if(!b)return; b.className=''; b.classList.add('badge-'+type);
            b.querySelector('.badge-icon').textContent=icon;
            b.querySelector('.badge-text').textContent=text;
            b.style.display='flex'; void b.offsetWidth; b.classList.add('show');
            if(type==='jackpot'||type==='win'){
                if(typeof GameEffects!=='undefined'&&GameEffects.win)GameEffects.win();
                if(typeof confetti==='function') confetti({particleCount:type==='jackpot'?250:100,spread:80,origin:{y:.5},colors:['#a855f7','#fbbf24','#3b82f6']});
            } else if(type==='lose'){
                if(typeof GameEffects!=='undefined'&&GameEffects.lose)GameEffects.lose();
            }
            setTimeout(()=>{b.classList.remove('show');setTimeout(()=>{b.style.display='none';},400);},3500);
        }

        $('#battleBtn').click(function(){
            const bet=parseInt($('#betAmt').val())||10000;
            const char=$('#charRow .char-btn.active').data('char')||'kiem_thanh';
            $(this).prop('disabled',true).text('⚔️ Đang chiến...');

            // Spin animation
            const spinEl=document.getElementById('spinEl');
            spinEl.style.fontSize='3rem'; spinEl.style.opacity='1';
            spinEl.className='spin-anim'; spinEl.textContent='🎡';

            $.post('?action=battle',{bet,char,floor:currentFloor},function(res){
                $('#battleBtn').prop('disabled',false).text('⚔️ CHIẾN!');
                spinEl.className=''; spinEl.textContent='🎯';

                if(!res.success){$('#resultText').text('❌ '+res.message);return;}
                $('#balance-val').text(res.money);
                currentFloor=res.nextFloor;
                updateFloorDisplay(currentFloor,false);

                if(currentFloor>bestFloor){bestFloor=currentFloor;$('#bestFloor').text(bestFloor);}

                const net=res.winAmount;
                if(net>0) totalWon+=net;
                $('#totalWin').text(totalWon.toLocaleString('vi-VN'));

                if(res.result==='jackpot'){
                    jpHits++; $('#jpHits').text(jpHits);
                    showRS('jackpot','🌟 JACKPOT TẦNG '+(currentFloor-1)+'! +'+net.toLocaleString('vi-VN'),'🌟');
                    spinEl.textContent='🌟';
                } else if(res.result==='win3'){
                    showRS('win','⬆️ THẮNG LỚN x3! LÊN TẦNG '+currentFloor,'🏆');
                    spinEl.textContent='🏆';
                } else if(res.result==='win'){
                    showRS('win','✅ CHIẾN THẮNG! LÊN TẦNG '+currentFloor,'⚔️');
                    spinEl.textContent='⚔️';
                } else if(res.result==='half'){
                    showRS('win','💫 NỬA THẮNG — GIỮ TẦNG '+currentFloor,'💫');
                    spinEl.textContent='💫';
                } else if(res.result==='retry'){
                    showRS('tie','🔄 HÒA — THỬ LẠI TẦNG '+currentFloor+' MIỄN PHÍ','🔄');
                    spinEl.textContent='🔄';
                    runCount--;
                } else {
                    showRS('lose','💀 TỬ THẦN! BAY MÀU & RESET TẦNG 1','💀');
                    spinEl.textContent='💀';
                }

                runCount++;
                $('#runCount').text(runCount);
                $('#resultText').text(res.label);
            },'json');
        });
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="bots/bot_61.js"></script>
</body>
</html>
