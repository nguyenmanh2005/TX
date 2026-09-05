<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_17', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require '../db_connect.php';
require_once '../load_theme.php';



$userId = $botUserId;
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$userName = $user['Name'];
$stmt->close();

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false];

    if ($action === 'deal') {
        $bet = (float) ($_POST['bet'] ?? 0);
        if ($bet <= 0 || $bet > $money) {
            $response['message'] = "gtlm cược không hợp lệ!";
        } else {
            $conn->begin_transaction();
            $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
            $stmtLock->bind_param("i", $userId);
            $stmtLock->execute();
            $lockedMoney = $stmtLock->get_result()->fetch_assoc()['Money'] ?? 0;
            $stmtLock->close();
            if ($bet > $lockedMoney) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Số dư không đủ hoặc thao tác quá nhanh!']);
                exit;
            }
            $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");

            // Simulating 5 cards each
            $suits = ['s', 'c', 'h', 'd'];
            $deck = [];
            foreach (['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'] as $v) {
                foreach ($suits as $s)
                    $deck[] = $v . $s;
            }
            shuffle($deck);
            $playerHand = array_slice($deck, 0, 5);
            $dealerHand = array_slice($deck, 5, 5);

            $_SESSION['caribbean_bet'] = $bet;
            $_SESSION['caribbean_player'] = $playerHand;
            $_SESSION['caribbean_dealer'] = $dealerHand;

            $response = ['success' => true, 'player' => $playerHand, 'dealer_up' => $dealerHand[0], 'money' => number_format($money - $bet, 0, ',', '.')];
        }
    } elseif ($action === 'fold') {
        $bet = $_SESSION['caribbean_bet'];
        $his = $conn->prepare("INSERT INTO history_caribbean (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $negBet = -$bet;
        $resStr = "Folded";
        $his->bind_param("idss", $userId, $bet, $resStr, $negBet);
        $his->execute();
        unset($_SESSION['caribbean_bet']);
        $response = ['success' => true];
    } elseif ($action === 'call') {
        $bet = $_SESSION['caribbean_bet'];
        $playerHand = $_SESSION['caribbean_player'];
        $dealerHand = $_SESSION['caribbean_dealer'];

        // Deduct call bet (2x ante)
        $callBet = $bet * 2;
        $conn->begin_transaction();
            $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
            $stmtLock->bind_param("i", $userId);
            $stmtLock->execute();
            $lockedMoney = $stmtLock->get_result()->fetch_assoc()['Money'] ?? 0;
            $stmtLock->close();
            if ($callBet > $lockedMoney) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Số dư không đủ hoặc thao tác quá nhanh!']);
                exit;
            }
            $conn->query("UPDATE users SET Money = Money - $callBet WHERE Iduser = $userId");

        // Logic: Compare hands
        // Simplified: Random winner for demo purposes, with 1/3 chance for dealer NOT qualifying
        $dealerQualifies = rand(1, 4) > 1; // 75% chance
        $playerWins = rand(1, 100) <= 44; // Reduced from 50% to 44% for house edge

        $winAmount = 0;
        $resMsg = "";

        if (!$dealerQualifies) {
            $winAmount = $bet * 2; // Ante pays 1:1, Call is a push
            $resMsg = "Dealer không đủ điều kiện (No AK high). Ante thắng 1:1!";
        } else {
            if ($playerWins) {
                $winAmount = ($bet * 2) + ($callBet * 2); // Simplified 1:1 payout
                $resMsg = "Bạn THẮNG Dealer!";
            } else {
                $winAmount = 0;
                $resMsg = "Dealer thắng! Bạn đã thua.";
            }
        }

        if ($winAmount > 0) {
            $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $userId");
        }

        $totalBet = $bet + $callBet;
        $profit = $winAmount - $totalBet;
        $his = $conn->prepare("INSERT INTO history_caribbean (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $his->bind_param("idss", $userId, $totalBet, $resMsg, $profit);
        $his->execute();

        $conn->commit();
            $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
        $response = [
            'success' => true,
            'dealer' => $dealerHand,
            'win' => ($winAmount > 0),
            'message' => $resMsg,
            'winAmount' => number_format($winAmount, 0, ',', '.'),
            'money' => number_format($newMoney, 0, ',', '.')
        ];
        unset($_SESSION['caribbean_bet']);
    }
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Caribbean Stud - Casino Classics</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Exo 2', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
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

        .glass {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 2rem;
        }

        .card {
            width: 100px;
            height: 140px;
            background: #fff;
            border-radius: 10px;
            color: #000;
            position: relative;
            transition: 0.5s;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        .card.back {
            background: linear-gradient(135deg, #00d2ff, #3a7bd5);
            color: transparent;
        }

        .hand {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 2rem 0;
            height: 160px;
        }

        .controls {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 2rem;
        }

        .btn-premium {
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 900;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
        }

        .btn-deal {
            background: #f1c40f;
            color: #000;
        }

        .btn-call {
            background: #2ecc71;
            color: #fff;
        }

        .btn-fold {
            background: #e74c3c;
            color: #fff;
        }

        .chip-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-bottom: 25px;
            width: 100%;
        }
        .chip {
            padding: 8px 18px;
            background: rgba(255,255,255,0.1);
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            color: #fff;
            transition: 0.3s;
            user-select: none;
        }
        .chip:hover, .chip.active {
            background: #00d2ff;
            color: #000;
            border-color: #00d2ff;
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(0, 210, 255, 0.4);
        }
        
        .floating-win { 
            position: absolute; 
            bottom: 50%; 
            left: 50%; 
            transform: translateX(-50%); 
            color: #f1c40f; 
            font-weight: 900; 
            font-size: 2.5rem; 
            pointer-events: none; 
            text-shadow: 0 0 15px #000, 0 0 5px #000; 
            z-index: 100; 
        }
        .lose-shake { 
            animation: lose-shake 0.5s cubic-bezier(.36,.07,.19,.97) both; 
        }
        @keyframes lose-shake { 
            10%, 90% { transform: translate3d(-1px, 0, 0); } 
            20%, 80% { transform: translate3d(2px, 0, 0); } 
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); } 
            40%, 60% { transform: translate3d(4px, 0, 0); } 
        }
    </style>
</head>

<body>
    <div class="game-wrapper" style="max-width:800px; margin:0 auto; position:relative; z-index:1; padding: 0 15px; width: 100%; transform: scale(0.8); transform-origin: top center; margin-top: 1rem;">
        <div class="glass" style="padding: 2.5rem; text-align: center; border-radius: 2rem; width: 100%;">
            <h1 style="margin: 0 0 1rem; font-size: 2.5rem; font-weight: 900; color: #00d2ff; text-transform: uppercase; letter-spacing: 2px;">CARIBBEAN STUD</h1>
            <div style="background: rgba(0,0,0,0.3); padding: 10px 25px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.2); display: inline-block; margin-bottom: 2rem; max-width: 100%;">
                <span style="opacity: 0.8; font-size: 0.9rem; margin-right: 5px;">SỐ GTLM:</span>
                <span id="userMoney" style="font-weight: 900; font-size: clamp(14px, 3vw, 1.5rem); color: #f1c40f; word-break: break-all;"><?php echo number_format($money, 0, ',', '.'); ?> gtlm</span>
            </div>

            <h3 style="margin-bottom:10px; opacity:0.8; font-size:1rem;">BÀI NHÀ CÁI (DEALER)</h3>
            <div id="dealerHand" class="hand">
                <!-- Dealer Cards -->
                <div class="card back"></div>
                <div class="card back"></div>
                <div class="card back"></div>
                <div class="card back"></div>
                <div class="card back"></div>
            </div>
            
            <div style="margin: 1.5rem 0; opacity: 0.5; font-weight: bold; font-size: 1.2rem;">VS</div>
            
            <h3 style="margin-bottom:10px; opacity:0.8; font-size:1rem;">BÀI CỦA BẠN</h3>
            <div id="playerHand" class="hand">
                <!-- Player Cards -->
                <div class="card back"></div>
                <div class="card back"></div>
                <div class="card back"></div>
                <div class="card back"></div>
                <div class="card back"></div>
            </div>

            <div class="betting-area" style="display:flex; flex-direction:column; align-items:center;">
                <div class="chip-selector" id="chipSelector">
                    <div class="chip active" data-value="10000">10K</div>
                    <div class="chip" data-value="50000">50K</div>
                    <div class="chip" data-value="100000">100K</div>
                    <div class="chip" data-value="500000">500K</div>
                    <div class="chip" data-value="1000000">1M</div>
                    <div class="chip" data-value="5000000">5M</div>
                    <div class="chip" data-value="allin">MAX</div>
                </div>

                <div class="controls" style="display:flex; gap:20px; justify-content:center; align-items:center; flex-wrap: wrap;">
                    <div style="background: rgba(0,0,0,0.3); padding: 5px 15px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.2); display: flex; align-items: center; gap: 10px;">
                        <span style="font-weight: bold; opacity: 0.8;">CƯỢC:</span>
                        <input type="number" id="betAmount" value="10000" style="background: transparent; color:#fff; outline:none; font-size:1.3rem; font-weight:900; text-align:center; width:120px; border: none;">
                    </div>
                    
                    <button class="btn-premium btn-deal" id="dealBtn" onclick="deal()">DEAL (CHIA BÀI)</button>
                    <button class="btn-premium btn-call" id="callBtn" style="display:none" onclick="call()">CALL (THEO CƯỢC x2)</button>
                    <button class="btn-premium btn-fold" id="foldBtn" style="display:none" onclick="fold()">FOLD (BỎ BÀI)</button>
                    <button class="btn-premium btn-deal" id="newBtn" style="display:none" onclick="newGame()">VÁN MỚI</button>
                </div>
            </div>
            
            <div style="margin-top: 3rem;">
                <a href="../index.php" style="color:#fff; text-decoration:none; border:1px solid rgba(255,255,255,0.2); padding:0.8rem 2rem; border-radius:50px; font-weight: bold; background: rgba(0,0,0,0.2); transition: 0.3s; display: inline-block;">🏠 THOÁT VỀ SẢNH</a>
            </div>
        </div>
    </div>

    <!-- Premium Effects System -->
    <canvas id="threejs-background"></canvas>
    <script>
        (function() {
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
            const pathParts = window.location.pathname.split('/');
            const appRoot = '/' + pathParts[1] + '/'; 
            const prefix = window.location.origin + appRoot;
            const scripts = ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'];
            
            scripts.forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src;
                s.async = false;
                document.head.appendChild(s);
            });
        })();

        // Caribbean Stud Poker Logic
        $(document).ready(function() {
            $('.chip').click(function() {
                if ($('#dealBtn').is(':hidden')) return; // Khóa phỉnh khi đang chơi
                $('.chip').removeClass('active');
                $(this).addClass('active');
                const val = $(this).data('value');
                if (val === 'allin') {
                    document.getElementById('betAmount').value = <?= $money ?>;
                } else {
                    document.getElementById('betAmount').value = val;
                }
            });
        });

        function getSuitSymbol(cardStr) {
            if (!cardStr) return '';
            let s = cardStr.slice(-1);
            let v = cardStr.slice(0, -1);
            let symbol = s === 'h' ? '♥' : (s === 'd' ? '♦' : (s === 'c' ? '♣' : '♠'));
            let color = (s === 'h' || s === 'd') ? '#dc2626' : '#1e293b';
            return `<div style="position:relative; width:100%; height:100%;"><div style="color:${color}; font-size:1.5rem; position:absolute; top:8px; left:12px;">${v}</div><div style="color:${color}; font-size:3.5rem; margin-top:35px; line-height:1;">${symbol}</div></div>`;
        }

        function renderCard(containerId, index, cardStr) {
            const el = $(`${containerId} .card`).eq(index);
            el.removeClass('back').html(getSuitSymbol(cardStr));
        }

        function resetCards() {
            $('.card').addClass('back').empty();
        }

        function deal() {
            const bet = $('#betAmount').val();
            if (bet < 1000) return Swal.fire('Lỗi', 'Cược tối thiểu 1,000 gtlm!', 'error');

            $.post('?action=deal', { bet: bet }, function(res) {
                if (!res.success) return Swal.fire('Lỗi', res.message, 'error');
                
                resetCards();
                
                res.player.forEach((c, i) => renderCard('#playerHand', i, c));
                renderCard('#dealerHand', 0, res.dealer_up); // Dealer only shows first card
                $('#userMoney').text(res.money + ' gtlm');
                
                $('#dealBtn').hide();
                $('#chipSelector').css('opacity', '0.5').css('pointer-events', 'none');
                $('#betAmount').prop('disabled', true);
                
                // Lưu state cho bot đọc
                window.currentHand = res.player;
                window.dealerUp = res.dealer_up;

                $('#callBtn').show();
                $('#foldBtn').show();
            });
        }

        function call() {
            $.get('?action=call', function(res) {
                if (!res.success) {
                    Swal.fire('Lỗi', res.message, 'error');
                    // Tự động chuyển qua Fold nếu lỗi (vd không đủ GTLM) để bot không bị kẹt
                    if (window.isBotStreamer || true) {
                        setTimeout(() => { fold(); }, 1500);
                    }
                    return;
                }
                
                res.dealer.forEach((c, i) => renderCard('#dealerHand', i, c));
                
                $('#userMoney').text(res.money + ' gtlm');
                
                setTimeout(() => {
                    if (res.win) {
                        if (typeof GameEffects !== 'undefined') GameEffects.showWin(res.winAmount);
                        const float = $('<div class="floating-win">+' + res.winAmount.toLocaleString('vi-VN') + '</div>').appendTo('.game-wrapper');
                        gsap.to(float, { y: -150, opacity: 0, duration: 2.5, ease: "power2.out", onComplete: () => float.remove() });
                    } else {
                        $('.game-wrapper').addClass('lose-shake');
                        if (typeof GameEffects !== 'undefined') GameEffects.showLoss();
                        const float = $('<div class="floating-win" style="color: #ff4757;">' + res.message + '</div>').appendTo('.game-wrapper');
                        gsap.to(float, { y: -150, opacity: 0, duration: 2.5, ease: "power2.out", onComplete: () => float.remove() });
                        setTimeout(() => $('.game-wrapper').removeClass('lose-shake'), 500);
                    }
                }, 500);
                
                $('#callBtn').hide();
                $('#foldBtn').hide();
                $('#newBtn').show();
            });
        }

        function fold() {
            $.get('?action=fold', function(res) {
                if (res.success) {
                    if (typeof GameEffects !== 'undefined') GameEffects.showLoss('Folded', 'Bạn đã bỏ bài.');
                    else Swal.fire('Folded', 'Bạn đã bỏ bài.', 'info');
                    
                    $('#callBtn').hide();
                    $('#foldBtn').hide();
                    $('#newBtn').show();
                }
            });
        }

        function newGame() {
            resetCards();
            $('#newBtn').hide();
            $('#dealBtn').show();
            $('#chipSelector').css('opacity', '1').css('pointer-events', 'auto');
            $('#betAmount').prop('disabled', false);
        }
    </script>

<!-- AUTO-GENERATED BOT SCRIPT -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script src="bots/bot_17.js"></script>

</body>
</html>

