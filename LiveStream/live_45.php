<?php
session_start();

require '../db_connect.php'; // DB trước khi gọi helper
require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_45', 50000000);
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
            $suits = ['s', 'c', 'h', 'd'];
            $deck = [];
            foreach (['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'] as $v) {
                foreach ($suits as $s)
                    $deck[] = $v . $s;
            }
            shuffle($deck);
            $cards = array_slice($deck, 0, 3);
            $_SESSION['reddog_bet'] = $bet;
            $_SESSION['reddog_cards'] = $cards;
            $v1 = getCardValue($cards[0]);
            $v2 = getCardValue($cards[1]);
            $spread = abs($v1 - $v2) - 1;
            if ($spread < 0)
                $spread = 0;
            $response = ['success' => true, 'cards' => [$cards[0], $cards[1]], 'spread' => $spread, 'money' => number_format($money - $bet, 0, ',', '.')];
        }
    } elseif ($action === 'ride') {
        $bet = $_SESSION['reddog_bet'];
        $cards = $_SESSION['reddog_cards'];
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
        $_SESSION['reddog_bet'] = $bet * 2;
        $response = ['success' => true];
    } elseif ($action === 'show') {
        $bet = $_SESSION['reddog_bet'];
        $cards = $_SESSION['reddog_cards'];
        $third = $cards[2];
        $v1 = getCardValue($cards[0]);
        $v2 = getCardValue($cards[1]);
        $v3 = getCardValue($third);
        $min = min($v1, $v2);
        $max = max($v1, $v2);
        $spread = $max - $min - 1;
        $win = ($v3 > $min && $v3 < $max);
        // House Edge: 15% chance to force loss on winning spreads
        if ($win && rand(1, 100) <= 15) {
            $win = false;
        }
        $payout = 0;
        if ($win) {
            $mult = 1;
            if ($spread == 1)
                $mult = 5;
            elseif ($spread == 2)
                $mult = 4;
            elseif ($spread == 3)
                $mult = 2;
            $payout = $bet + ($bet * $mult);
        }
        if ($payout > 0)
            $conn->query("UPDATE users SET Money = Money + $payout WHERE Iduser = $userId");
        $profit = $payout - $bet;
        $resMsg = $win ? "Thắng! Lá thứ 3 nằm trong khoảng." : "THUA RỒI! Lá thứ 3 nằm ngoài khoảng.";
        $his = $conn->prepare("INSERT INTO history_reddog (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $his->bind_param("idss", $userId, $bet, $resMsg, $profit);
        $his->execute();
        $conn->commit();
            $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
        $response = [
            'success' => true,
            'third' => $third,
            'win' => $win,
            'message' => $resMsg,
            'winAmount' => number_format($payout, 0, ',', '.'),
            'money' => number_format($newMoney, 0, ',', '.')
        ];
        unset($_SESSION['reddog_bet']);
    }
    echo json_encode($response);
    exit;
}
function getCardValue($c)
{
    $v = substr($c, 0, -1);
    if ($v === 'J')
        return 11;
    if ($v === 'Q')
        return 12;
    if ($v === 'K')
        return 13;
    if ($v === 'A')
        return 14;
    return (int) $v;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Red Dog Poker - spread Betting</title>
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
            background: <?= $bgGradientCSS ?>;
        }
        .glass {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 2.5rem;
        }
        .card {
            width: 100px;
            height: 140px;
            background: #fff;
            border-radius: 12px;
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 1.8rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }
        .card.back {
            background: linear-gradient(135deg, #ff9a9e, #fad0c4);
            color: transparent;
        }
        .table-area {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 4rem 0;
            align-items: center;
        }
        .spread-badge {
            background: #f1c40f;
            color: #000;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-weight: 900;
            font-size: 1.2rem;
            box-shadow: 0 0 15px #f1c40f;
        }
        .btn-premium {
            padding: 1.2rem 3rem;
            border: none;
            border-radius: 50px;
            font-weight: 900;
            cursor: pointer;
            transition: 0.4s;
            text-transform: uppercase;
        }
        .btn-red {
            background: #ff4757;
            color: #fff;
        }
        .btn-gold {
            background: #ffa502;
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
            background: #ff4757;
            color: #fff;
            border-color: #ff4757;
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(255, 71, 87, 0.4);
        }
    </style>
</head>
<body>
    <div class="game-wrapper" style="max-width:800px; margin:2rem auto; position:relative; z-index:1; padding: 0 15px; width: 100%;">
        <div class="glass" style="padding: 2.5rem; text-align: center; border-radius: 2rem; width: 100%;">
            <h1 style="margin: 0 0 1rem; font-size: 2.5rem; font-weight: 900; color: #ff9a9e; text-transform: uppercase; letter-spacing: 2px;">RED DOG POKER</h1>
            <div style="background: rgba(0,0,0,0.3); padding: 10px 25px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.2); display: inline-block; margin-bottom: 2rem; max-width: 100%;">
                <span style="opacity: 0.8; font-size: 0.9rem; margin-right: 5px;">SỐ GTLM:</span>
                <span id="userMoney" style="font-weight: 900; font-size: clamp(14px, 3vw, 1.5rem); color: #ffa502; word-break: break-all;"><?php echo number_format($money, 0, ',', '.'); ?> gtlm</span>
            </div>
            <div class="table-area">
                <div id="card1" class="card back"></div>
                <div id="spreadInfo" style="display:none; flex-direction:column; gap:10px; align-items:center;">
                    <div class="spread-badge">KHOẢNG: <span id="spreadValue">0</span></div>
                    <div style="font-size:0.8rem; opacity:0.7">Lá thứ 3 phải nằm ở giữa</div>
                </div>
                <div id="card3" class="card back" style="transform: scale(1.1); border: 3px solid #ffa502;"></div>
                <div id="card2" class="card back"></div>
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
                    <button class="btn-premium btn-red" id="dealBtn" onclick="deal()">CHIA BÀI</button>
                    <button class="btn-premium btn-gold" id="rideBtn" style="display:none" onclick="ride()">RIDE (GẤP ĐÔI)</button>
                    <button class="btn-premium btn-red" id="showBtn" style="display:none" onclick="show()">MỞ BÀI</button>
                    <button class="btn-premium btn-gold" id="newBtn" style="display:none" onclick="newGame()">VÁN MỚI</button>
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
        // Red Dog Game Logic
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
            return `<div style="position:relative; width:100%; height:100%;"><div style="color:${color}; font-size:1.5rem; position:absolute; top:8px; left:12px;">${v}</div><div style="color:${color}; font-size:4rem; margin-top:35px; line-height:1;">${symbol}</div></div>`;
        }
        function renderCard(elId, cardStr) {
            const el = document.getElementById(elId);
            el.classList.remove('back');
            el.innerHTML = getSuitSymbol(cardStr);
        }
        function resetCards() {
            $('#card1, #card2, #card3').addClass('back').empty();
            $('#spreadInfo').hide();
        }
        function deal() {
            const bet = $('#betAmount').val();
            if (bet < 1000) return Swal.fire('Lỗi', 'Cược tối thiểu 1,000 gtlm!', 'error');
            $.post('?action=deal', { bet: bet }, function(res) {
                if (!res.success) return Swal.fire('Lỗi', res.message, 'error');
                resetCards();
                renderCard('card1', res.cards[0]);
                renderCard('card2', res.cards[1]);
                $('#userMoney').text(res.money + ' gtlm');
                $('#dealBtn').hide();
                $('#chipSelector').css('opacity', '0.5').css('pointer-events', 'none');
                $('#betAmount').prop('disabled', true);
                if (res.spread === 0) {
                    $('#spreadInfo').show();
                    $('#spreadValue').text("0");
                    $('#showBtn').show();
                } else {
                    $('#spreadInfo').show();
                    $('#spreadValue').text(res.spread);
                    $('#rideBtn').show();
                    $('#showBtn').show();
                }
            });
        }
        function ride() {
            $.get('?action=ride', function(res) {
                if (res.success) {
                    $('#rideBtn').hide();
                    $('#betAmount').val($('#betAmount').val() * 2);
                    show();
                }
            });
        }
        function show() {
            $.get('?action=show', function(res) {
                if (!res.success) return;
                renderCard('card3', res.third);
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
                $('#rideBtn').hide();
                $('#showBtn').hide();
                $('#newBtn').show();
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

<!-- Bot AI Script -->
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script src="bots/bot_45.js?v=<?= time() ?>"></script>

</body>
</html>
