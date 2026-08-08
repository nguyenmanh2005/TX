<?php
session_start();
require '../db_connect.php';
require_once '../load_theme.php';
if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}
$userId = $_SESSION['Iduser'];
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$userName = $user['Name'];
$stmt->close();
// history table
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
            // Standard deck
            $suits = ['s', 'c', 'h', 'd'];
            $deck = [];
            foreach (['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'] as $v) {
                foreach ($suits as $s)
                    $deck[] = $v . $s;
            }
            shuffle($deck);
            $playerHand = array_slice($deck, 0, 2);
            $dealerHand = array_slice($deck, 2, 2);
            $community = array_slice($deck, 4, 3); // Flop
            $_SESSION['holdem_bet'] = $bet;
            $_SESSION['holdem_player'] = $playerHand;
            $_SESSION['holdem_dealer'] = $dealerHand;
            $_SESSION['holdem_community'] = $community;
            $_SESSION['holdem_deck'] = array_slice($deck, 7);
            $response = ['success' => true, 'player' => $playerHand, 'community' => $community, 'money' => number_format($money - $bet, 0, ',', '.')];
        }
    } elseif ($action === 'call') {
        $bet = $_SESSION['holdem_bet'];
        $player = $_SESSION['holdem_player'];
        $dealer = $_SESSION['holdem_dealer'];
        $comm = $_SESSION['holdem_community'];
        $deck = $_SESSION['holdem_deck'];
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
        // Add Turn and River
        $comm[] = $deck[0];
        $comm[] = $deck[1];
        // Simplified Logic: Player wins 42% of the time for house edge (Reduced from 55%)
        $win = rand(1, 100) <= 42;
        $winAmount = $win ? ($bet * 2 + $callBet * 2) : 0;
        if ($winAmount > 0) {
            $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $userId");
        }
        $totalBet = $bet + $callBet;
        $profit = $winAmount - $totalBet;
        $resMsg = $win ? "Bạn THẮNG với kết quả tốt hơn Dealer!" : "Dealer thắng! Bạn đã thua.";
        $his = $conn->prepare("INSERT INTO history_holdem (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $his->bind_param("idss", $userId, $totalBet, $resMsg, $profit);
        $his->execute();
        $conn->commit();
            $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
        $response = [
            'success' => true,
            'dealer' => $dealer,
            'full_comm' => $comm,
            'win' => $win,
            'message' => $resMsg,
            'winAmount' => number_format($winAmount, 0, ',', '.'),
            'money' => number_format($newMoney, 0, ',', '.')
        ];
        unset($_SESSION['holdem_bet']);
    } elseif ($action === 'fold') {
        $bet = $_SESSION['holdem_bet'];
        $his = $conn->prepare("INSERT INTO history_holdem (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $negBet = -$bet;
        $resStr = "Folded";
        $his->bind_param("idss", $userId, $bet, $resStr, $negBet);
        $his->execute();
        unset($_SESSION['holdem_bet']);
        $response = ['success' => true];
    }
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Casino Hold'em - Premium classics</title>
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
        }
        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }
        .glass {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 2rem;
        }
        .card {
            width: 80px;
            height: 110px;
            background: #fff;
            border-radius: 8px;
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 1.2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        .card.back {
            background: linear-gradient(135deg, #f093fb, #f5576c);
            color: transparent;
        }
        .hand {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 1.5rem;
            min-height: 120px;
        }
        .community {
            display: flex;
            gap: 15px;
            justify-content: center;
            background: rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            border-radius: 20px;
            margin: 2rem 0;
            min-height: 130px;
        }
        .controls {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 1rem;
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
            background: #00d2ff;
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
            background: #f093fb;
            color: #000;
            border-color: #f093fb;
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(240, 147, 251, 0.4);
        }
    </style>
</head>
<body>
    <div class="game-wrapper" style="max-width:800px; margin:2rem auto; position:relative; z-index:1; padding: 0 15px; width: 100%;">
        <div class="glass" style="padding: 2.5rem; text-align: center; border-radius: 2rem; width: 100%;">
            <h1 style="margin: 0 0 1rem; font-size: 2.5rem; font-weight: 900; color: #f093fb; text-transform: uppercase; letter-spacing: 2px;">CASINO HOLD'EM</h1>
            <div style="background: rgba(0,0,0,0.3); padding: 10px 25px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.2); display: inline-block; margin-bottom: 2rem; max-width: 100%;">
                <span style="opacity: 0.8; font-size: 0.9rem; margin-right: 5px;">SỐ GTLM:</span>
                <span id="userMoney" style="font-weight: 900; font-size: clamp(14px, 3vw, 1.5rem); color: #f1c40f; word-break: break-all;"><?php echo number_format($money, 0, ',', '.'); ?> gtlm</span>
            </div>
            <h3 style="margin-bottom:10px; opacity:0.8; font-size:1rem;">BÀI NHÀ CÁI (DEALER)</h3>
            <div id="dealerHand" class="hand">
                <div class="card back"></div>
                <div class="card back"></div>
            </div>
            <div id="commonCards" class="community">
                <div class="card back"></div>
                <div class="card back"></div>
                <div class="card back"></div>
                <div class="card back"></div>
                <div class="card back"></div>
            </div>
            <h3 style="margin-top:20px; margin-bottom:10px; opacity:0.8; font-size:1rem;">BÀI CỦA BẠN</h3>
            <div id="playerHand" class="hand">
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
    <?php require_once '../casino_help.php'; ?>
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
            const prefix = window.location.pathname.includes('/games/') ? '../' : '';
            const scripts = ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'];
            scripts.forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src;
                s.async = false;
                document.head.appendChild(s);
            });
        })();
        // Casino Hold'em Logic
        $(document).ready(function() {
            $('.chip').click(function() {
                if ($('#dealBtn').is(':hidden')) return; // Khóa phỉnh khi đang chơi
                $('.chip').removeClass('active');
                $(this).addClass('active');
                const val = $(this).data('value');
                if (val === 'allin') {
                    $('#betAmount').val(<?= $money ?>);
                } else {
                    $('#betAmount').val(val);
                }
            });
        });
        function getSuitSymbol(cardStr) {
            if (!cardStr) return '';
            let s = cardStr.slice(-1);
            let v = cardStr.slice(0, -1);
            let symbol = s === 'h' ? '♥' : (s === 'd' ? '♦' : (s === 'c' ? '♣' : '♠'));
            let color = (s === 'h' || s === 'd') ? '#dc2626' : '#1e293b';
            return `<div style="position:relative; width:100%; height:100%;"><div style="color:${color}; font-size:1.5rem; position:absolute; top:8px; left:12px;">${v}</div><div style="color:${color}; font-size:3rem; margin-top:25px; line-height:1;">${symbol}</div></div>`;
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
            $.post('holdem.php?action=deal', { bet: bet }, function(res) {
                if (!res.success) return Swal.fire('Lỗi', res.message, 'error');
                resetCards();
                res.player.forEach((c, i) => renderCard('#playerHand', i, c));
                res.community.forEach((c, i) => renderCard('#commonCards', i, c));
                $('#userMoney').text(res.money + ' gtlm');
                $('#dealBtn').hide();
                $('#chipSelector').css('opacity', '0.5').css('pointer-events', 'none');
                $('#betAmount').prop('disabled', true);
                $('#callBtn').show();
                $('#foldBtn').show();
            });
        }
        function call() {
            $.get('holdem.php?action=call', function(res) {
                if (!res.success) return;
                res.dealer.forEach((c, i) => renderCard('#dealerHand', i, c));
                res.full_comm.forEach((c, i) => renderCard('#commonCards', i, c));
                $('#userMoney').text(res.money + ' gtlm');
                setTimeout(() => {
                    if (res.win) {
                        if (typeof GameEffects !== 'undefined') GameEffects.showWin(res.winAmount);
                        Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'success', title: 'Thắng', text: res.message });
                    } else {
                        if (typeof GameEffects !== 'undefined') GameEffects.showLoss();
                        Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'error', title: 'Thua', text: res.message });
                    }
                }, 500);
                $('#callBtn').hide();
                $('#foldBtn').hide();
                $('#newBtn').show();
            });
        }
        function fold() {
            $.get('holdem.php?action=fold', function(res) {
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
</body>
</html>
