<?php
session_start();

require '../db_connect.php'; // DB trước khi gọi helper
require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_44', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require_once '../load_theme.php';


$userId = $botUserId;
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
            $_SESSION['pontoon_bet'] = $bet;
            $_SESSION['pontoon_player'] = $playerHand;
            $_SESSION['pontoon_dealer'] = $dealerHand;
            $_SESSION['pontoon_deck'] = array_slice($deck, 4);
            $response = ['success' => true, 'player' => $playerHand, 'money' => number_format($money - $bet, 0, ',', '.')];
        }
    } elseif ($action === 'twist') {
        $deck = $_SESSION['pontoon_deck'];
        $player = $_SESSION['pontoon_player'];
        $card = array_shift($deck);
        $player[] = $card;
        $_SESSION['pontoon_player'] = $player;
        $_SESSION['pontoon_deck'] = $deck;
        $response = ['success' => true, 'card' => $card, 'isBust' => (calculatePontoonScore($player) > 21)];
        if ($response['isBust']) {
            resolvePontoon(false, "Quá 21 điểm! Bạn đã thua.");
        }
    } elseif ($action === 'stick') {
        $player = $_SESSION['pontoon_player'];
        $dealer = $_SESSION['pontoon_dealer'];
        $deck = $_SESSION['pontoon_deck'];
        // Dealer logic: Deal until 17+
        while (calculatePontoonScore($dealer) < 17) {
            $dealer[] = array_shift($deck);
        }
        $pScore = calculatePontoonScore($player);
        $dScore = calculatePontoonScore($dealer);
        $win = false;
        $msg = "";
        if ($dScore > 21) {
            $win = true;
            $msg = "Dealer Quá 21 điểm! Bạn THẮNG.";
        } elseif ($pScore > $dScore) {
            $win = true;
            $msg = "Bạn thắng Dealer với điểm cao hơn ($pScore vs $dScore).";
        } elseif (count($player) >= 5 && $pScore <= 21) {
            $win = true;
            $msg = "5-Card Trick! Bạn thắng tuyệt đối.";
        } else {
            $win = false;
            $msg = "Dealer thắng ($dScore vs $pScore). Bạn đã thua.";
        }
        // House Edge: 10% chance to force loss even if technically won
        if ($win && rand(1, 10) === 1) {
            $win = false;
            $msg = "Dealer có bộ bài ẩn mạnh hơn! Bạn đã thua.";
        }
        $res = resolvePontoon($win, $msg, $dealer);
        $response = array_merge(['success' => true], $res);
    }
    echo json_encode($response);
    exit;
}
function calculatePontoonScore($hand)
{
    $score = 0;
    $aces = 0;
    foreach ($hand as $c) {
        $v = substr($c, 0, -1);
        if ($v === 'A') {
            $aces++;
            $score += 11;
        } elseif (in_array($v, ['J', 'Q', 'K']))
            $score += 10;
        else
            $score += (int) $v;
    }
    while ($score > 21 && $aces > 0) {
        $score -= 10;
        $aces--;
    }
    return $score;
}
function resolvePontoon($win, $msg, $dealer = null)
{
    global $conn, $userId;
    $bet = $_SESSION['pontoon_bet'];
    $winAmount = $win ? $bet * 2 : 0;
    if ($winAmount > 0)
        $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $userId");
    $profit = $winAmount - $bet;
    $his = $conn->prepare("INSERT INTO history_pontoon (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
    $his->bind_param("idss", $userId, $bet, $msg, $profit);
    $his->execute();
    unset($_SESSION['pontoon_bet']);
    $conn->commit();
            $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
    return ['win' => $win, 'message' => $msg, 'dealer' => $dealer, 'winAmount' => number_format($winAmount, 0, ',', '.'), 'money' => number_format($newMoney, 0, ',', '.')];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Pontoon - UK Blackjack Royale</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/canvas-confetti/1.6.0/confetti.browser.min.js"></script>
    <script src="../assets/js/game-effects.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: transparent;
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
            background: <?= $bgGradientCSS ?>;
        }
        .glass {
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 2rem;
        }
        .card {
            width: 90px;
            height: 130px;
            background: #fff;
            border-radius: 10px;
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        .card.back {
            background: linear-gradient(135deg, #12c2e9, #c471ed, #f64f59);
            color: transparent;
        }
        .hand {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 2rem 0;
            min-height: 140px;
        }
        .btn-premium {
            padding: 1.2rem 2.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 900;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
        }
        .btn-twist {
            background: #00d2ff;
            color: #000;
        }
        .btn-stick {
            background: #f1c40f;
            color: #000;
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
    </style>
</head>
<body>
    <div class="game-wrapper" style="max-width:800px; margin:2rem auto; position:relative; z-index:1; padding: 0 15px; width: 100%;">
        <div class="glass" style="padding: 2.5rem; text-align: center; border-radius: 2rem; width: 100%;">
            <h1 style="margin: 0 0 1rem; font-size: 2.5rem; font-weight: 900; color: #12c2e9; text-transform: uppercase; letter-spacing: 2px;">PONTOON</h1>
            <div style="background: rgba(0,0,0,0.3); padding: 10px 25px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.2); display: inline-block; margin-bottom: 2rem; max-width: 100%;">
                <span style="opacity: 0.8; font-size: 0.9rem; margin-right: 5px;">SỐ GTLM:</span>
                <span id="userMoney" style="font-weight: 900; font-size: clamp(14px, 3vw, 1.5rem); color: #f1c40f; word-break: break-all;"><?php echo number_format($money, 0, ',', '.'); ?> gtlm</span>
            </div>
            <h3 style="margin-bottom:10px; opacity:0.8; font-size:1rem;">BÀI NHÀ CÁI (DEALER)</h3>
            <div id="dealerHand" class="hand">
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
                    <button class="btn-premium btn-twist" id="dealBtn" onclick="deal()">CHIA BÀI</button>
                    <button class="btn-premium btn-twist" id="twistBtn" style="display:none" onclick="twist()">TWIST (RÚT THÊM)</button>
                    <button class="btn-premium btn-stick" id="stickBtn" style="display:none" onclick="stick()">STICK (DỪNG)</button>
                    <button class="btn-premium btn-stick" id="newBtn" style="display:none" onclick="newGame()">VÁN MỚI</button>
                </div>
            </div>
            <div style="margin-top: 3rem;">
                <a href="../index.php" style="color:#fff; text-decoration:none; border:1px solid rgba(255,255,255,0.2); padding:0.8rem 2rem; border-radius:50px; font-weight: bold; background: rgba(0,0,0,0.2); transition: 0.3s; display: inline-block;">🏠 THOÁT VỀ SẢNH</a>
            </div>
        </div>
    </div>
    <?php require_once '../casino_help.php'; ?>
    <!-- Badge thông báo thắng/thua giống game ID 1 -->
    <div id="result-status-badge" style="
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) scale(0.5);
        background: rgba(0,0,0,0.88);
        border-radius: 20px;
        padding: 28px 52px;
        text-align: center;
        z-index: 10000;
        pointer-events: none;
        backdrop-filter: blur(22px);
        border: 2px solid rgba(255,255,255,0.15);
        box-shadow: 0 25px 80px rgba(0,0,0,0.8);
        font-family: 'Outfit', 'Exo 2', sans-serif;
        transition: transform 0.4s cubic-bezier(0.17, 0.89, 0.32, 1.49), opacity 0.4s;
        opacity: 0;
    ">
        <div id="result-badge-icon" style="font-size: 3.5rem; margin-bottom: 8px;"></div>
        <div id="result-badge-title" style="font-size: 1.8rem; font-weight: 800; letter-spacing: 2px; margin-bottom: 6px;"></div>
        <div id="result-badge-amount" style="font-size: 1.3rem; font-weight: 700; opacity: 0.9;"></div>
        <div id="result-badge-msg" style="font-size: 0.85rem; opacity: 0.65; margin-top: 6px; max-width: 320px;"></div>
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
            ['../threejs-background.js', '../assets/js/game-effects-auto.js'].forEach(src => {
                const s = document.createElement('script');
                s.src = src;
                s.async = false;
                document.head.appendChild(s);
            });
        })();

        // === Badge kết quả giống game ID 1 ===
        function showResultBadge(isWin, winAmount, statusMsg) {
            const badge = document.getElementById('result-status-badge');
            const icon  = document.getElementById('result-badge-icon');
            const title = document.getElementById('result-badge-title');
            const amtEl = document.getElementById('result-badge-amount');
            const msgEl = document.getElementById('result-badge-msg');

            if (isWin) {
                badge.style.borderColor = '#f1c40f';
                badge.style.boxShadow   = '0 25px 80px rgba(0,0,0,0.8), 0 0 80px rgba(241,196,15,0.5)';
                icon.textContent  = '🏆';
                title.textContent = 'THẮNG!';
                title.style.color = '#f1c40f';
                amtEl.textContent = '+' + parseInt(winAmount).toLocaleString('vi-VN') + ' GTLM';
                amtEl.style.color = '#f1c40f';
            } else {
                badge.style.borderColor = '#e74c3c';
                badge.style.boxShadow   = '0 25px 80px rgba(0,0,0,0.8), 0 0 60px rgba(231,76,60,0.4)';
                icon.textContent  = '❌';
                title.textContent = 'THUA!';
                title.style.color = '#e74c3c';
                amtEl.textContent = '';
                amtEl.style.color = '#e74c3c';
            }
            msgEl.textContent = statusMsg || '';

            badge.style.display = 'block';
            requestAnimationFrame(() => {
                badge.style.transform = 'translate(-50%, -50%) scale(1.05)';
                badge.style.opacity   = '1';
                setTimeout(() => { badge.style.transform = 'translate(-50%, -50%) scale(1)'; }, 150);
            });
            setTimeout(() => {
                badge.style.transform = 'translate(-50%, -50%) scale(0.8)';
                badge.style.opacity   = '0';
                setTimeout(() => { badge.style.display = 'none'; }, 400);
            }, 3500);
        }
        // Pontoon Game Logic
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
            return `<div style="position:relative; width:100%; height:100%;"><div style="color:${color}; font-size:1.5rem; position:absolute; top:8px; left:12px;">${v}</div><div style="color:${color}; font-size:4rem; margin-top:30px; line-height:1;">${symbol}</div></div>`;
        }
        function addCard(containerId, cardStr, isBack = false) {
            let content = isBack ? '' : getSuitSymbol(cardStr);
            let cls = isBack ? 'card back' : 'card';
            $(containerId).append(`<div class="${cls}" data-card="${cardStr || ''}">${content}</div>`);
        }
        function resetCards() {
            $('#dealerHand, #playerHand').empty();
        }
        function deal() {
            const bet = $('#betAmount').val();
            if (bet < 1000) return Swal.fire('Lỗi', 'Cược tối thiểu 1,000 gtlm!', 'error');
            $.post('?action=deal', { bet: bet }, function(res) {
                if (!res.success) return Swal.fire('Lỗi', res.message, 'error');
                resetCards();
                addCard('#dealerHand', '', true);
                addCard('#dealerHand', '', true);
                res.player.forEach(c => addCard('#playerHand', c));
                $('#userMoney').text(res.money + ' gtlm');
                $('#dealBtn').hide();
                $('#chipSelector').css('opacity', '0.5').css('pointer-events', 'none');
                $('#betAmount').prop('disabled', true);
                $('#twistBtn').show();
                $('#stickBtn').show();
            });
        }
        function twist() {
            $.get('?action=twist', function(res) {
                if (!res.success) return;
                addCard('#playerHand', res.card);
                if (res.isBust) {
                    $('#twistBtn').hide();
                    $('#stickBtn').hide();
                    $('#newBtn').show();
                    showResultBadge(false, 0, 'Quá 21 điểm! Bạn đã thua.');
                    if (typeof GameEffects !== 'undefined') GameEffects.showLoss(parseInt($('#betAmount').val()) || 10000);
                }
            });
        }
        function stick() {
            $.get('?action=stick', function(res) {
                if (!res.success) return;
                $('#dealerHand').empty();
                res.dealer.forEach(c => addCard('#dealerHand', c));
                $('#userMoney').text(res.money + ' gtlm');
                const winAmount = parseInt(String(res.winAmount).replace(/\D/g, '')) || 0;
                setTimeout(() => {
                    if (res.win) {
                        showResultBadge(true, winAmount, res.message);
                        if (typeof GameEffects !== 'undefined') GameEffects.showWin(winAmount);
                        if (typeof confetti === 'function') confetti({ particleCount: 150, spread: 80, origin: { y: 0.5 } });
                    } else {
                        showResultBadge(false, 0, res.message);
                        if (typeof GameEffects !== 'undefined') GameEffects.showLoss(parseInt($('#betAmount').val()) || 10000);
                    }
                }, 400);
                $('#twistBtn').hide();
                $('#stickBtn').hide();
                $('#newBtn').show();
            });
        }
        function newGame() {
            resetCards();
            addCard('#dealerHand', '', true);
            addCard('#dealerHand', '', true);
            addCard('#playerHand', '', true);
            addCard('#playerHand', '', true);
            $('#newBtn').hide();
            $('#dealBtn').show();
            $('#chipSelector').css('opacity', '1').css('pointer-events', 'auto');
            $('#betAmount').prop('disabled', false);
        }
    </script>

<!-- Bot AI Script -->
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script src="bots/bot_44.js?v=<?= time() ?>"></script>

</body>
</html>
