<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_55', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require '../db_connect.php';
require_once '../include_css.php';
$useBotTheme = $botUserId;
require_once '../load_theme.php';

$userId = $botUserId;
// Auto-create history table
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$stmt->close();
function getCard($exclude = [])
{
    $suits = ['♠', '♥', '♦', '♣'];
    $vals = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
    do {
        $vIdx = rand(0, 12);
        $sIdx = rand(0, 3);
        $card = ['val' => $vals[$vIdx], 'suit' => $suits[$sIdx], 'rank' => $vIdx + 2];
        $id = $card['val'] . $card['suit'];
        $found = false;
        foreach ($exclude as $e)
            if ($e['val'] . $e['suit'] == $id)
                $found = true;
    } while ($found);
    return $card;
}
function evaluateHand($hand)
{
    usort($hand, function ($a, $b) {
        return $a['rank'] - $b['rank'];
    });
    $ranks = array_column($hand, 'rank');
    $suits = array_column($hand, 'suit');
    $counts = array_count_values($ranks);
    arsort($counts);
    $vals = array_values($counts);
    $isFlush = (count(array_unique($suits)) === 1);
    $isStraight = false;
    if (count(array_unique($ranks)) === 5) {
        if ($ranks[4] - $ranks[0] === 4)
            $isStraight = true;
        elseif ($ranks[4] === 14 && $ranks[3] === 5 && $ranks[0] === 2)
            $isStraight = true; // A-2-3-4-5
    }
    if ($isStraight && $isFlush && $ranks[4] === 14 && $ranks[0] === 10)
        return ['name' => 'Royal Flush', 'pay' => 800];
    if ($isStraight && $isFlush)
        return ['name' => 'Straight Flush', 'pay' => 50];
    if ($vals[0] == 4)
        return ['name' => 'Four of a Kind', 'pay' => 25];
    if ($vals[0] == 3 && $vals[1] == 2)
        return ['name' => 'Full House', 'pay' => 9];
    if ($isFlush)
        return ['name' => 'Flush', 'pay' => 6];
    if ($isStraight)
        return ['name' => 'Straight', 'pay' => 4];
    if ($vals[0] == 3)
        return ['name' => 'Three of a Kind', 'pay' => 3];
    if ($vals[0] == 2 && $vals[1] == 2)
        return ['name' => 'Two Pair', 'pay' => 2];
    if ($vals[0] == 2) {
        $pairRank = array_search(2, $counts);
        if ($pairRank >= 11)
            return ['name' => 'Jacks or Better', 'pay' => 1];
    }
    return ['name' => 'Bust', 'pay' => 0];
}
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    if ($action === 'deal') {
        $bet = (int) ($_POST['bet'] ?? 0);
        if ($bet <= 0 || $bet > $money) {
            echo json_encode(['success' => false, 'message' => 'Cược không hợp lệ!']);
            exit;
        }
        $_SESSION['vp_bet'] = $bet;
        $hand = [];
        for ($i = 0; $i < 5; $i++)
            $hand[] = getCard($hand);
        $_SESSION['vp_hand'] = $hand;
        echo json_encode(['success' => true, 'hand' => $hand]);
        exit;
    } elseif ($action === 'draw') {
        $hold = json_decode($_POST['hold']); // array of booleans [true, false, ...]
        $hand = $_SESSION['vp_hand'];
        $exclude = $hand;
        $newHand = [];
        foreach ($hold as $idx => $isHeld) {
            if ($isHeld) {
                $newHand[$idx] = $hand[$idx];
            } else {
                $c = getCard($exclude);
                $newHand[$idx] = $c;
                $exclude[] = $c;
            }
        }
        ksort($newHand);
        $finalHand = array_values($newHand);
        $eval = evaluateHand($finalHand);
        $bet = $_SESSION['vp_bet'];
        $winAmount = ($bet * $eval['pay']);
        if ($eval['pay'] == 0)
            $winAmount = -$bet;
        $newMoney = $money + $winAmount;
        $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $stmt->bind_param("di", $newMoney, $userId);
        $stmt->execute();
        $stmt->close();
        // History
        $his = $conn->prepare("INSERT INTO history_videopoker (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $resStr = $eval['name'];
        $his->bind_param("idss", $userId, $bet, $resStr, $winAmount);
        $his->execute();
        $his->close();
        echo json_encode([
            'success' => true,
            'hand' => $finalHand,
            'eval' => $eval['name'],
            'winAmount' => $winAmount,
            'money' => number_format($newMoney, 0, ',', '.'),
            'rawMoney' => $newMoney
        ]);
        exit;
    } elseif ($action === 'get_history') {
        $stmt = $conn->prepare("SELECT Bet, Result, WinAmount, Time FROM history_videopoker WHERE Iduser = ? ORDER BY Time DESC LIMIT 10");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'history' => $res]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Video Poker - Jacks or Better</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <style>
        :root {
            --primary: #fbbf24;
            --secondary: #6ee7b7;
            --danger: #ef4444;
            --accent: #0369a1;
            --glass: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Exo 2', system-ui, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: url('../img/chuot.png'), auto !important;
        }
        * { cursor: inherit; }
        button, a, input, .card, .card-wrap, .chip, .btn {
            cursor: url('../img/tay.png'), pointer !important;
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

        /* 🏆 Badge Thông Báo Thắng / Thua Chuẩn Game ID 1 */
        #result-status-badge {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.5);
            background: rgba(10, 12, 24, 0.94);
            border-radius: 24px;
            padding: 24px 48px;
            text-align: center;
            z-index: 99999;
            pointer-events: none;
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 2px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.85);
            font-family: 'Orbitron', 'Exo 2', sans-serif;
            transition: transform 0.4s cubic-bezier(0.17, 0.89, 0.32, 1.49), opacity 0.4s;
            opacity: 0;
        }
        #result-badge-icon {
            font-size: 3.5rem;
            margin-bottom: 6px;
            animation: badgeIconBounce 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
        }
        @keyframes badgeIconBounce {
            0% { transform: scale(0) rotate(-20deg); }
            70% { transform: scale(1.3) rotate(10deg); }
            100% { transform: scale(1) rotate(0deg); }
        }
        #result-badge-title {
            font-size: 1.6rem;
            font-weight: 900;
            letter-spacing: 2px;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        #result-badge-amount {
            font-size: 1.3rem;
            font-weight: 800;
            opacity: 0.95;
            font-family: 'Orbitron', sans-serif;
        }
        #result-badge-msg {
            font-size: 0.82rem;
            opacity: 0.75;
            margin-top: 6px;
            max-width: 320px;
            font-family: 'Exo 2', sans-serif;
        }

        .main-container {
            position: relative;
            z-index: 1;
            width: 96%;
            max-width: 820px;
            margin: 0.5rem auto 1rem;
            text-align: center;
        }
        .game-title {
            font-size: clamp(1.4rem, 3.5vw, 1.8rem);
            font-weight: 900;
            color: var(--primary);
            text-shadow: 0 0 20px rgba(251, 191, 36, 0.3);
            margin-bottom: 0.2rem;
            text-transform: uppercase;
            letter-spacing: 3px;
        }
        .balance-pill {
            background: rgba(110, 231, 183, 0.1);
            border: 1px solid var(--secondary);
            padding: 4px 16px;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 0.6rem;
            color: var(--secondary);
            font-weight: 700;
            font-size: 0.85rem;
        }
        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 1.6rem;
            padding: 1rem 1.4rem;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.5);
            margin-bottom: 0.8rem;
        }
        .pay-table {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 4px;
            background: rgba(0, 0, 0, 0.3);
            padding: 0.5rem 0.8rem;
            border-radius: 1rem;
            margin-bottom: 0.6rem;
            border: 1px solid var(--glass-border);
            font-size: 0.7rem;
        }
        .pay-row {
            display: flex;
            justify-content: space-between;
            padding: 2px 6px;
            border-radius: 6px;
            transition: background 0.3s;
        }
        .pay-row:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        .pay-name {
            opacity: 0.7;
            font-weight: 600;
        }
        .pay-val {
            color: var(--primary);
            font-weight: 800;
        }
        .hand-area {
            display: flex;
            justify-content: center;
            gap: clamp(0.3rem, 1.5vw, 0.8rem);
            margin: 0.5rem 0;
            min-height: 115px;
            perspective: 1000px;
            flex-wrap: wrap;
        }
        .card-wrap {
            position: relative;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .card {
            width: clamp(52px, 8.5vw, 68px);
            aspect-ratio: 2/3;
            background: #fff;
            color: #000;
            border-radius: 0.6rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: clamp(1.2rem, 2.5vw, 1.8rem);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        .card.red {
            color: #dc2626;
        }
        .card.black {
            color: #1e293b;
        }
        .card-v {
            position: absolute;
            top: 0.3rem;
            left: 0.3rem;
            font-size: 0.85rem;
        }
        .card-s {
            font-size: 2.2rem;
        }
        .held-tag {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary);
            color: #000;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 900;
            letter-spacing: 0.5px;
            opacity: 0;
            transition: 0.25s;
        }
        .hold .held-tag {
            opacity: 1;
            transform: translateX(-50%) translateY(-2px);
        }
        .hold .card {
            transform: translateY(-8px);
            box-shadow: 0 0 16px rgba(251, 191, 36, 0.5);
            border: 2px solid var(--primary);
        }
        .input-group {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
            padding: 0.45rem 0.8rem;
            border-radius: 1rem;
            margin: 0 auto 0.6rem;
            max-width: 220px;
        }
        .input-group span {
            display: block;
            font-size: 0.68rem;
            opacity: 0.6;
            margin-bottom: 0.2rem;
        }
        .input-group input {
            width: 100%;
            background: transparent;
            border: none;
            color: #fff;
            text-align: center;
            font-size: 1.15rem;
            font-weight: 700;
            outline: none;
        }
        .btn {
            padding: 0.65rem 2.2rem;
            border-radius: 50px;
            border: none;
            font-weight: 900;
            cursor: pointer;
            transition: all 0.25s;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9rem;
            width: 100%;
            max-width: 240px;
            margin: 0.3rem 0;
        }
        .btn-blue {
            background: linear-gradient(135deg, var(--accent) 0%, #0369a1 100%);
            color: #fff;
        }
        .btn-gold {
            background: linear-gradient(135deg, var(--primary) 0%, #d97706 100%);
            color: #000;
        }
        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.4);
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .chip-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: center;
            margin-bottom: 6px;
        }
        .chip {
            padding: 4px 10px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.78rem;
            color: #fff;
            transition: 0.25s;
            user-select: none;
        }
        .chip:hover, .chip.active {
            background: var(--primary);
            color: #000;
            border-color: var(--primary);
            transform: scale(1.06);
        }
        @media (max-width: 600px) {
            .card {
                width: 50px;
            }
            .pay-table {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- 🏆 Badge thông báo thắng/thua giống game ID 1 -->
    <div id="result-status-badge">
        <div id="result-badge-icon">🏆</div>
        <div id="result-badge-title">THẮNG VIDEO POKER!</div>
        <div id="result-badge-amount">+100,000 GTLM</div>
        <div id="result-badge-msg">Jacks or Better x1!</div>
    </div>

    <div class="main-container">
        <h1 class="game-title">VIDEO POKER</h1>
        <div class="balance-pill">💰 SỐ GTLM: <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span> GTLM</div>
        <div class="glass-card">
            <div class="pay-table">
                <div class="pay-row"><span class="pay-name">ROYAL FLUSH</span><span class="pay-val">800</span></div>
                <div class="pay-row"><span class="pay-name">STRAIGHT FLUSH</span><span class="pay-val">50</span></div>
                <div class="pay-row"><span class="pay-name">FOUR OF A KIND</span><span class="pay-val">25</span></div>
                <div class="pay-row"><span class="pay-name">FULL HOUSE</span><span class="pay-val">9</span></div>
                <div class="pay-row"><span class="pay-name">FLUSH</span><span class="pay-val">6</span></div>
                <div class="pay-row"><span class="pay-name">STRAIGHT</span><span class="pay-val">4</span></div>
                <div class="pay-row"><span class="pay-name">THREE OF A KIND</span><span class="pay-val">3</span></div>
                <div class="pay-row"><span class="pay-name">TWO PAIR</span><span class="pay-val">2</span></div>
                <div class="pay-row"><span class="pay-name">JACKS OR BETTER</span><span class="pay-val">1</span></div>
            </div>
            <div id="hand-view" class="hand-area">
                <div class="card-wrap"><div class="held-tag">HOLD</div><div class="card" style="background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.2);">P</div></div>
                <div class="card-wrap"><div class="held-tag">HOLD</div><div class="card" style="background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.2);">O</div></div>
                <div class="card-wrap"><div class="held-tag">HOLD</div><div class="card" style="background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.2);">K</div></div>
                <div class="card-wrap"><div class="held-tag">HOLD</div><div class="card" style="background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.2);">E</div></div>
                <div class="card-wrap"><div class="held-tag">HOLD</div><div class="card" style="background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.2);">R</div></div>
            </div>
            <div id="bet-area">
                <div class="chip-selector">
                    <div class="chip active" data-value="10000">10K</div>
                    <div class="chip" data-value="50000">50K</div>
                    <div class="chip" data-value="100000">100K</div>
                    <div class="chip" data-value="500000">500K</div>
                    <div class="chip" data-value="1000000">1M</div>
                    <div class="chip" data-value="5000000">5M</div>
                </div>
                <div class="input-group">
                    <span>SỐ GTLM CƯỢC</span>
                    <input type="number" id="bet-amt" value="10000" min="1000" step="1000">
                </div>
                <button id="deal-btn" class="btn btn-blue">PHÁT BÀI</button>
                <div style="margin-top: 0.6rem;">
                    <a href="../index.php" style="color: #fff; text-decoration: none; font-weight: 700; border: 1px solid rgba(255,255,255,0.2); padding: 0.4rem 1.6rem; border-radius: 50px; font-size: 0.78rem; background: rgba(0,0,0,0.2); transition: 0.3s; display: inline-block;">🏠 THOÁT VỀ SẢNH</a>
                </div>
            </div>
            <div id="action-area" style="display: none;">
                <p style="margin-bottom: 0.8rem; font-weight: 700; opacity: 0.85; font-size: 0.85rem;">Nhấp vào lá bài để GIỮ (HOLD)</p>
                <button id="draw-btn" class="btn btn-gold">THAY BÀI (DRAW)</button>
            </div>
            <div id="result-area" style="display: none; margin-top: 0.8rem;">
                <h2 id="eval-name" style="color: var(--primary); margin-bottom: 0.3rem; font-size: 1.5rem; font-weight: 900;"></h2>
                <h3 id="win-amt" style="margin-bottom: 0.8rem; font-size: 1.1rem;"></h3>
                <button id="reset-btn" class="btn btn-blue">CHƠI TIẾP</button>
            </div>
        </div>
    </div>
    <script>
        function showResultBadge(type, title, amount, msg) {
            const badge = $('#result-status-badge');
            const icon = $('#result-badge-icon');
            const titleEl = $('#result-badge-title');
            const amountEl = $('#result-badge-amount');
            const msgEl = $('#result-badge-msg');

            if (type === 'win') {
                icon.text('🏆');
                titleEl.text(title || 'THẮNG VIDEO POKER!').css('color', '#fbbf24');
                amountEl.text(amount).css('color', '#4ade80');
                badge.css({
                    'border-color': 'rgba(251, 191, 36, 0.7)',
                    'box-shadow': '0 0 50px rgba(251, 191, 36, 0.35)'
                });
            } else {
                icon.text('💨');
                titleEl.text(title || 'BAY MÀU!').css('color', '#ff4757');
                amountEl.text(amount).css('color', '#ff4757');
                badge.css({
                    'border-color': 'rgba(239, 68, 68, 0.7)',
                    'box-shadow': '0 0 50px rgba(239, 68, 68, 0.35)'
                });
            }

            msgEl.text(msg || '');
            badge.stop(true, true).css({ display: 'block', opacity: 0, transform: 'translate(-50%, -50%) scale(0.6)' });
            
            setTimeout(() => {
                badge.css({ opacity: 1, transform: 'translate(-50%, -50%) scale(1)' });
            }, 10);

            setTimeout(() => {
                badge.css({ opacity: 0, transform: 'translate(-50%, -50%) scale(0.7)' });
                setTimeout(() => { badge.hide(); }, 400);
            }, 3500);
        }

        // Game Logic for Video Poker
        $(document).ready(function() {
            let isDealt = false;
            let holdState = [false, false, false, false, false];

            // Chip Selector Logic
            $('.chip').on('click', function() {
                $('.chip').removeClass('active');
                $(this).addClass('active');
                const val = $(this).attr('data-value');
                $('#bet-amt').val(val);
            });

            function renderCard(cardData, element) {
                const suitMap = {'♥': 'hearts', '♦': 'diamonds', '♣': 'clubs', '♠': 'spades'};
                const suitStr = suitMap[cardData.suit];
                let valStr = cardData.val;
                if (!isNaN(valStr) && parseInt(valStr) < 10) valStr = '0' + parseInt(valStr);
                const url = `img/anh-bai/PNG/Cards (large)/card_${suitStr}_${valStr}.png`;
                element.html(`<img src="${url}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.6rem;">`);
                element.attr('class', 'card card-img');
                element.css({'background': 'transparent', 'color': 'transparent', 'padding': '0', 'border': 'none', 'box-shadow': 'none'});
            }

            $('.card-wrap').click(function() {
                if (!isDealt) return;
                const index = $(this).index();
                holdState[index] = !holdState[index];
                $(this).toggleClass('hold', holdState[index]);
            });

            $('#deal-btn').click(function() {
                const bet = $('#bet-amt').val();
                if (bet < 1000) return Swal.fire('Lỗi', 'Cược tối thiểu 1,000 gtlm!', 'error');
                $.post('?action=deal', { bet: bet }, function(res) {
                    if (!res.success) return Swal.fire('Lỗi', res.message, 'error');
                    isDealt = true;
                    holdState = [false, false, false, false, false];
                    $('.card-wrap').removeClass('hold');
                    $('.card-wrap .card').each(function(i) {
                        renderCard(res.hand[i], $(this));
                    });
                    $('#bet-area').hide();
                    $('#result-area').hide();
                    $('#action-area').show();
                });
            });

            $('#draw-btn').click(function() {
                if (!isDealt) return;
                isDealt = false;
                const betAmt = parseInt($('#bet-amt').val()) || 10000;
                $.post('?action=draw', { hold: JSON.stringify(holdState) }, function(res) {
                    if (!res.success) return Swal.fire('Lỗi', res.message || 'Lỗi', 'error');
                    $('.card-wrap .card').each(function(i) {
                        renderCard(res.hand[i], $(this));
                    });
                    $('#eval-name').text(res.eval);
                    if (res.winAmount > 0) {
                        $('#win-amt').text("THẮNG " + new Intl.NumberFormat('vi-VN').format(res.winAmount) + " GTLM!").css('color', 'var(--secondary)');
                        const isBig = res.eval.includes('Full House') || res.eval.includes('Flush') || res.eval.includes('Straight') || res.eval.includes('Four') || res.eval.includes('Royal');
                        if (typeof GameEffects !== 'undefined') {
                            if (isBig) GameEffects.showBigWin(res.winAmount);
                            else GameEffects.showWin(res.winAmount);
                        }
                        showResultBadge('win', 'THẮNG ' + res.eval.toUpperCase() + '!', '+' + new Intl.NumberFormat('vi-VN').format(res.winAmount) + ' GTLM', 'Sở hữu thế bài ' + res.eval + ' xuất sắc!');
                    } else {
                        $('#win-amt').text("THUA " + new Intl.NumberFormat('vi-VN').format(betAmt) + " GTLM").css('color', 'var(--danger)');
                        if (typeof GameEffects !== 'undefined') {
                            GameEffects.showLoss(betAmt);
                        }
                        showResultBadge('loss', 'BAY MÀU VÁN POKER!', '-' + new Intl.NumberFormat('vi-VN').format(betAmt) + ' GTLM', 'Chưa kết nối được thế bài Jacks or Better!');
                    }
                    $('#balance-val').text(res.money);
                    $('#action-area').hide();
                    $('#result-area').show();
                });
            });

            $('#reset-btn').click(function() {
                $('.card-wrap').removeClass('hold');
                const letters = ['P', 'O', 'K', 'E', 'R'];
                $('.card-wrap .card').each(function(i) {
                    $(this).html(letters[i]).attr('class', 'card').css({'background': 'rgba(255,255,255,0.05)', 'color': 'rgba(255,255,255,0.2)'});
                });
                $('#result-area').hide();
                $('#bet-area').show();
            });
        });
    </script>

    <!-- 🌌 Nền ThreeJS 3D Cyberpunk Neon -->
    <canvas id="threejs-background"></canvas>
    <script>
        window.themeConfig = {
            particleCount: <?= $particleCount ?? 800 ?>,
            particleSize: <?= $particleSize ?? 0.05 ?>,
            particleColor: '<?= $particleColor ?? "#ff00ff" ?>',
            particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
            shapeCount: <?= $shapeCount ?? 15 ?>,
            shapeColors: <?= json_encode($shapeColors ?? ["#ff00ff", "#00ffff", "#ffff00", "#ff4757"]) ?>,
            shapeOpacity: <?= $shapeOpacity ?? 0.35 ?>,
            bgGradient: <?= json_encode($bgGradient ?? ["#000000", "#110011", "#220022"]) ?>
        };
    </script>
    <script src="../threejs-background.js"></script>
    <script src="../assets/js/game-effects.js"></script>
    <script src="../assets/js/game-effects-auto.js"></script>

    <!-- 🤖 Nạp Bot AI Chuyên Nghiệp Thánh Bài Video Poker 55 -->
    <script src="../assets/js/bot_chat.js"></script>
    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="bots/bot_55.js"></script>

</body>
</html>