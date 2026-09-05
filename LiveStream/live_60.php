<?php
session_start();
require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_60', 50000000);
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
$particleColor = $particleColor ?? '#fbbf24';
$shapeColors   = $shapeColors   ?? ['#fbbf24','#f59e0b','#a78bfa','#ef4444'];
$bgGradient    = $bgGradient    ?? ['#0f0a00','#1a1200','#0a0010'];
if (empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg,'.$bgGradient[0].' 0%,'.$bgGradient[1].' 50%,'.($bgGradient[2]??$bgGradient[1]).' 100%)';
}
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    if ($_GET['action'] === 'drop') {
        $bet=$_POST['bet']??0; $risk=$_POST['risk']??'high'; $rows=(int)($_POST['rows']??16); $balls=(int)($_POST['balls']??10);
        $balls=max(1,min(100,$balls)); $totalBet=$bet*$balls;
        $conn->begin_transaction();
        $st=$conn->prepare("SELECT Money FROM users WHERE Iduser=? FOR UPDATE"); $st->bind_param("i",$userId); $st->execute();
        $locked=$st->get_result()->fetch_assoc()['Money']??0; $st->close();
        if($bet<=0||$totalBet>$locked){$conn->rollback();echo json_encode(['success'=>false,'message'=>'GTLM cược không hợp lệ!']);exit;}
        $mt=['low'=>[8=>[5.6,2.1,1.1,1,0.5,1,1.1,2.1,5.6],12=>[10,3,1.6,1.4,1.1,1,1,1,1.1,1.4,1.6,3,10],16=>[16,9,2,1.4,1.4,1.2,1.1,1,1,1,1.1,1.2,1.4,1.4,2,9,16]],
              'medium'=>[8=>[13,3,1.3,0.7,0.4,0.7,1.3,3,13],12=>[33,11,4,2,1.1,0.6,0.3,0.6,1.1,2,4,11,33],16=>[110,41,10,5,3,1.5,1,0.5,0.3,0.5,1,1.5,3,5,10,41,110]],
              'high'=>[8=>[29,4,1.5,0.3,0.2,0.3,1.5,4,29],12=>[141,22,5.5,2,0.6,0.2,0.1,0.2,0.6,2,5.5,22,141],16=>[1000,130,26,9,4,2,0.7,0.2,0.1,0.2,0.7,2,4,9,26,130,1000]]];
        $mults=$mt[$risk][$rows]??$mt['high'][16]; $slots=count($mults);
        $results=[];$tw=0;$sn=0;$jp=0;
        for($i=0;$i<$balls;$i++){$pos=0;for($r=0;$r<$rows;$r++)$pos+=rand(0,1);$pos=min($pos,$slots-1);$m=$mults[$pos];$w=round($bet*$m);if($m>=100)$jp++;$tw+=$w;$sn+=($w-$bet);$results[]=['slot'=>$pos,'mult'=>$m,'win'=>$w];}
        $nm=$locked-$totalBet+$tw; $conn->query("UPDATE users SET Money=$nm WHERE Iduser=$userId"); $conn->commit();
        echo json_encode(['success'=>true,'results'=>$results,'totalBet'=>$totalBet,'totalWin'=>$tw,'sessionNet'=>$sn,'jackpots'=>$jp,'money'=>number_format($nm,0,',','.')]);
    } else { echo json_encode(['success'=>false,'message'=>'invalid']); }
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Plinko Royale V3 - Bàn Live 60</title>
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
        #result-status-badge.badge-jackpot{background:linear-gradient(135deg,rgba(251,191,36,0.98),rgba(217,119,6,0.98));border:2px solid #fde047;color:#000;box-shadow:0 0 60px rgba(251,191,36,1);animation:pulseGlow .7s infinite alternate}
        #result-status-badge.badge-lose{background:linear-gradient(135deg,rgba(239,68,68,0.9),rgba(185,28,28,0.9));border:2px solid #f87171;box-shadow:0 0 30px rgba(239,68,68,0.6)}
        @keyframes pulseGlow{from{transform:translate(-50%,-50%) scale(1)}to{transform:translate(-50%,-50%) scale(1.08);filter:brightness(1.25)}}
        .header-bar{width:100%;padding:8px 24px;display:flex;justify-content:space-between;align-items:center;background:rgba(0,0,0,0.6);backdrop-filter:blur(15px);border-bottom:2px solid #fbbf24;box-sizing:border-box}
        .logo-royale{font-family:'Cinzel Decorative',serif;font-size:16px;font-weight:900;color:#fbbf24;letter-spacing:2px;text-shadow:0 0 12px rgba(251,191,36,.5)}
        .user-money{background:rgba(0,0,0,.5);padding:5px 18px;border-radius:30px;border:1px solid #fbbf24;font-weight:800;color:#fbbf24;font-size:15px}
        .game-wrapper{max-width:860px;margin:1rem auto;padding:0 12px;width:100%}
        .glass{background:rgba(8,6,0,.8);backdrop-filter:blur(20px);border:1px solid rgba(251,191,36,.2);border-radius:1.6rem;padding:1.4rem 1.8rem;box-shadow:0 20px 60px rgba(0,0,0,.6)}
        .section-title{font-family:'Cinzel Decorative',serif;font-size:.75rem;letter-spacing:3px;color:#fbbf24;opacity:.7;text-align:center;margin-bottom:14px;text-transform:uppercase}
        .controls-grid{display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;justify-content:center;margin-bottom:16px}
        .ctrl-group{display:flex;flex-direction:column;gap:6px}
        .ctrl-label{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#fbbf24;opacity:.7}
        .seg-ctrl{display:flex;gap:4px;flex-wrap:wrap}
        .seg-btn{padding:5px 11px;border-radius:20px;border:1px solid rgba(251,191,36,.25);background:rgba(251,191,36,.05);color:#fff;font-size:.78rem;font-weight:700;cursor:pointer;transition:.2s}
        .seg-btn.active,.seg-btn:hover{background:#fbbf24;border-color:#fbbf24;color:#000}
        .bet-input{background:rgba(0,0,0,.5);border:1px solid rgba(251,191,36,.4);border-radius:8px;padding:7px 12px;color:#fbbf24;font-size:1rem;font-weight:900;width:130px;outline:none}
        .quick-bets{display:flex;gap:4px;flex-wrap:wrap}
        .q-btn{padding:3px 9px;border-radius:14px;border:1px solid rgba(251,191,36,.25);background:rgba(251,191,36,.06);color:#fbbf24;font-size:.72rem;font-weight:700;cursor:pointer;transition:.2s}
        .q-btn:hover{background:rgba(251,191,36,.25)}
        .btn-royale{padding:11px 32px;border:none;border-radius:40px;background:linear-gradient(135deg,#fbbf24,#f59e0b,#d97706);color:#000;font-weight:900;font-size:1rem;cursor:pointer;transition:.3s;box-shadow:0 6px 25px rgba(251,191,36,.5);text-transform:uppercase;letter-spacing:1px;font-family:'Outfit',sans-serif}
        .btn-royale:hover:not(:disabled){transform:translateY(-2px) scale(1.04);filter:brightness(1.1)}
        .btn-royale:disabled{opacity:.45;cursor:not-allowed}
        .board-viz{background:rgba(0,0,0,.4);border:1px solid rgba(251,191,36,.15);border-radius:1rem;padding:20px;text-align:center;min-height:150px;display:flex;flex-direction:column;align-items:center;justify-content:center;margin-top:12px;position:relative;overflow:hidden}
        .ball-anim{font-size:1.6rem;animation:fallBall 1.2s ease-in forwards;position:absolute}
        @keyframes fallBall{0%{transform:translateY(-60px) rotate(-20deg) scale(.5);opacity:0}60%{opacity:1}100%{transform:translateY(130px) rotate(20deg) scale(1.2);opacity:0}}
        .result-log{font-size:.82rem;opacity:.75;min-height:36px;position:relative;z-index:2}
        .mults-display{display:flex;flex-wrap:wrap;justify-content:center;gap:4px;margin-bottom:8px}
        .mult-chip{padding:3px 8px;border-radius:8px;font-size:.7rem;font-weight:800}
        .mult-chip.low-m{background:rgba(251,191,36,.15);color:#fbbf24}
        .mult-chip.mid-m{background:rgba(16,185,129,.2);color:#34d399}
        .mult-chip.high-m{background:rgba(239,68,68,.2);color:#f87171}
        .mult-chip.mega-m{background:linear-gradient(135deg,rgba(251,191,36,.9),rgba(239,68,68,.9));color:#fff;font-size:.75rem}
        .stats-bar{display:flex;gap:20px;justify-content:center;margin-top:12px;flex-wrap:wrap}
        .stat-item{text-align:center}
        .stat-lbl{font-size:.68rem;opacity:.5;text-transform:uppercase;letter-spacing:1px}
        .stat-val{font-family:'Cinzel Decorative',serif;font-size:1rem;font-weight:900;color:#fbbf24}
        .home-link{display:none!important}
    </style>
</head>
<body>
    <canvas id="threejs-background"></canvas>
    <div id="result-status-badge"><span class="badge-icon">👑</span><span class="badge-text">ROYALE JACKPOT!</span></div>
    <header class="header-bar">
        <div class="logo-royale">🔥 PLINKO ROYALE V3</div>
        <div class="user-money">💰 <span id="balance-val"><?=number_format($money,0,',','.')?></span> GTLM</div>
        <div style="font-size:13px;color:#aaa">STREAMER: <b style="color:#fbbf24"><?=htmlspecialchars($userName)?></b></div>
    </header>
    <div class="game-wrapper"><div class="glass">
        <div class="section-title">Đấu Trường Plinko Royale V3 Multi-Drop</div>
        <div class="controls-grid">
            <div class="ctrl-group"><div class="ctrl-label">Số hàng đinh</div><div class="seg-ctrl" id="rowsCtrl"><button class="seg-btn" data-val="8">8</button><button class="seg-btn" data-val="12">12</button><button class="seg-btn active" data-val="16">16 🔥</button></div></div>
            <div class="ctrl-group"><div class="ctrl-label">Mức rủi ro</div><div class="seg-ctrl" id="riskCtrl"><button class="seg-btn" data-val="low">An Toàn</button><button class="seg-btn" data-val="medium">Vàng</button><button class="seg-btn active" data-val="high">X1000 👑</button></div></div>
            <div class="ctrl-group"><div class="ctrl-label">Số bóng Multi-Drop</div><div class="seg-ctrl" id="ballsCtrl"><button class="seg-btn" data-val="1">1</button><button class="seg-btn active" data-val="10">10</button><button class="seg-btn" data-val="25">25</button><button class="seg-btn" data-val="50">50 🔥</button><button class="seg-btn" data-val="100">100 💥</button></div></div>
            <div class="ctrl-group"><div class="ctrl-label">GTLM cược/bóng</div><input type="number" id="betAmt" class="bet-input" value="10000" min="1000" step="1000"><div class="quick-bets"><button class="q-btn" onclick="setBet(10000)">10K</button><button class="q-btn" onclick="setBet(50000)">50K</button><button class="q-btn" onclick="setBet(100000)">100K</button><button class="q-btn" onclick="setBet(500000)">500K</button><button class="q-btn" onclick="setBet(1000000)">1M</button></div></div>
            <div class="ctrl-group" style="justify-content:flex-end"><button class="btn-royale" id="dropBtn">💥 THẢ BÓNG ROYALE</button></div>
        </div>
        <div class="board-viz" id="boardViz"><div id="multsDisplay" class="mults-display"></div><div class="result-log" id="resultLog">Bot đang chuẩn bị màn trình diễn Royale...</div></div>
        <div class="stats-bar">
            <div class="stat-item"><div class="stat-lbl">Phiên Thắng</div><div class="stat-val" id="sessionWin">0</div></div>
            <div class="stat-item"><div class="stat-lbl">Jackpot Hits</div><div class="stat-val" id="jackpotHits">0</div></div>
            <div class="stat-item"><div class="stat-lbl">Lợi Nhuận</div><div class="stat-val" id="sessionProfit">0</div></div>
            <div class="stat-item"><div class="stat-lbl">Mult Đỉnh</div><div class="stat-val" id="bestMult">-</div></div>
        </div>
    </div></div>
    <script>
        window.themeConfig={particleCount:<?=$particleCount??1000?>,particleSize:<?=$particleSize??0.06?>,particleColor:'<?=$particleColor??"#fbbf24"?>',particleOpacity:<?=$particleOpacity??0.7?>,shapeCount:<?=$shapeCount??12?>,shapeColors:<?=json_encode($shapeColors??["#fbbf24","#f59e0b","#a78bfa","#ef4444"])?>,shapeOpacity:<?=$shapeOpacity??0.35?>,bgGradient:<?=json_encode($bgGradient??["#0f0a00","#1a1200","#0a0010"])?>};
    </script>
    <script src="../threejs-background.js"></script>
    <script src="../assets/js/game-effects.js"></script>
    <script src="../assets/js/game-effects-auto.js"></script>
    <script>
        let sW=0,sN=0,bM=0,tJ=0;
        function setBet(v){$('#betAmt').val(v);}
        const MT={low:{8:[5.6,2.1,1.1,1,0.5,1,1.1,2.1,5.6],12:[10,3,1.6,1.4,1.1,1,1,1,1.1,1.4,1.6,3,10],16:[16,9,2,1.4,1.4,1.2,1.1,1,1,1,1.1,1.2,1.4,1.4,2,9,16]},medium:{8:[13,3,1.3,0.7,0.4,0.7,1.3,3,13],12:[33,11,4,2,1.1,0.6,0.3,0.6,1.1,2,4,11,33],16:[110,41,10,5,3,1.5,1,0.5,0.3,0.5,1,1.5,3,5,10,41,110]},high:{8:[29,4,1.5,0.3,0.2,0.3,1.5,4,29],12:[141,22,5.5,2,0.6,0.2,0.1,0.2,0.6,2,5.5,22,141],16:[1000,130,26,9,4,2,0.7,0.2,0.1,0.2,0.7,2,4,9,26,130,1000]}};
        function updateMD(){const r=$('#riskCtrl .seg-btn.active').data('val')||'high',rw=parseInt($('#rowsCtrl .seg-btn.active').data('val'))||16;const ms=(MT[r]||{})[rw]||MT.high[16];const c=document.getElementById('multsDisplay');c.innerHTML='';ms.forEach(m=>{const ch=document.createElement('div');ch.className='mult-chip '+(m>=100?'mega-m':m>=10?'high-m':m>=2?'mid-m':'low-m');ch.textContent='x'+m;c.appendChild(ch);})}
        updateMD();
        $('.seg-ctrl').each(function(){$(this).find('.seg-btn').click(function(){$(this).siblings().removeClass('active');$(this).addClass('active');updateMD();});});
        function showRS(type,text,icon){const b=document.getElementById('result-status-badge');if(!b)return;b.className='';b.classList.add('badge-'+type);b.querySelector('.badge-icon').textContent=icon;b.querySelector('.badge-text').textContent=text;b.style.display='flex';void b.offsetWidth;b.classList.add('show');
        if(type==='jackpot'){if(typeof GameEffects!=='undefined'&&GameEffects.win)GameEffects.win();if(typeof confetti==='function'){confetti({particleCount:300,spread:100,origin:{y:.5},colors:['#fbbf24','#ef4444','#a78bfa','#fff']});setTimeout(()=>confetti({particleCount:200,angle:60,spread:80,origin:{x:0},colors:['#fbbf24','#fde047']}),400);setTimeout(()=>confetti({particleCount:200,angle:120,spread:80,origin:{x:1},colors:['#fbbf24','#fde047']}),600);}}
        else if(type==='win'){if(typeof GameEffects!=='undefined'&&GameEffects.win)GameEffects.win();if(typeof confetti==='function')confetti({particleCount:120,spread:70,origin:{y:.6},colors:['#fbbf24','#34d399','#a78bfa']});}
        else{if(typeof GameEffects!=='undefined'&&GameEffects.lose)GameEffects.lose();}
        setTimeout(()=>{b.classList.remove('show');setTimeout(()=>{b.style.display='none';},400);},4000);}
        $('#dropBtn').click(function(){
            const bet=parseInt($('#betAmt').val())||10000,risk=$('#riskCtrl .seg-btn.active').data('val')||'high',rows=parseInt($('#rowsCtrl .seg-btn.active').data('val'))||16,balls=parseInt($('#ballsCtrl .seg-btn.active').data('val'))||10;
            $(this).prop('disabled',true).text('⏳ Đang thả '+balls+' bóng...');
            const viz=document.getElementById('boardViz'),em=['🟡','🔴','🟣','🟠','🔵'];
            for(let i=0;i<Math.min(balls,8);i++)setTimeout(()=>{const b=document.createElement('div');b.className='ball-anim';b.textContent=em[i%em.length];b.style.left=(15+Math.random()*70)+'%';b.style.top='0';viz.appendChild(b);setTimeout(()=>b.remove(),1400);},i*150);
            $.post('?action=drop',{bet,risk,rows,balls},function(res){
                $('#dropBtn').prop('disabled',false).text('💥 THẢ BÓNG ROYALE');
                if(!res.success){$('#resultLog').text('❌ '+res.message);return;}
                $('#balance-val').text(res.money);
                let mx=0;res.results.forEach(r=>{if(r.mult>mx)mx=r.mult;});
                if(mx>bM){bM=mx;$('#bestMult').text('x'+mx);}
                sN+=res.sessionNet; tJ+=res.jackpots; if(res.jackpots>0)$('#jackpotHits').text(tJ);
                if(res.jackpots>0)showRS('jackpot','👑 x'+mx+' ROYALE JACKPOT! +'+res.totalWin.toLocaleString('vi-VN'),'👑');
                else if(res.sessionNet>0){sW++;$('#sessionWin').text(sW);showRS('win','🔥 THẮNG! +'+res.sessionNet.toLocaleString('vi-VN')+' GTLM','🎉');}
                else showRS('lose','😢 BAY MÀU '+res.sessionNet.toLocaleString('vi-VN')+' GTLM','😢');
                $('#sessionProfit').css('color',sN>=0?'#fbbf24':'#f87171').text((sN>=0?'+':'')+sN.toLocaleString('vi-VN'));
                $('#resultLog').html('💥 Thả <b>'+balls+'</b> bóng — Cược: <b>'+res.totalBet.toLocaleString('vi-VN')+'</b> | Thắng: <b>'+res.totalWin.toLocaleString('vi-VN')+'</b> | Jackpots: <b>'+res.jackpots+'</b> | Mult đỉnh: <b>x'+mx+'</b>');
            },'json');
        });
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="bots/bot_60.js"></script>
</body>
</html>
