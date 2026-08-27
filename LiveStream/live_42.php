<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_42', 50000000);
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
$stmt->close();

function getCard()
{
    $suits = ['♠', '♥', '♦', '♣'];
    $vals = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
    $vIdx = rand(0, 12);
    $sIdx = rand(0, 3);
    return ['val' => $vals[$vIdx], 'suit' => $suits[$sIdx], 'rank' => $vIdx + 2];
}

function evaluateHand($hand)
{
    usort($hand, function ($a, $b) {
        return $b['rank'] - $a['rank'];
    });
    $count = count($hand);

    if ($count == 2) {
        if ($hand[0]['rank'] == $hand[1]['rank'])
            return ['rank' => 1, 'score' => 1000 + $hand[0]['rank']];
        return ['rank' => 0, 'score' => $hand[0]['rank']];
    } else {
        // Simple 5-card evaluation
        $ranks = array_column($hand, 'rank');
        $suits = array_column($hand, 'suit');
        $counts = array_count_values($ranks);
        arsort($counts);
        $vals = array_values($counts);

        $isFlush = (count(array_unique($suits)) === 1);
        $isStraight = false;
        if (count(array_unique($ranks)) === 5 && ($hand[0]['rank'] - $hand[4]['rank'] === 4))
            $isStraight = true;
        // A-2-3-4-5
        if (!$isStraight && count(array_unique($ranks)) === 5 && $ranks[0] == 14 && $ranks[1] == 5 && $ranks[4] == 2)
            $isStraight = true;

        if ($isStraight && $isFlush)
            return ['rank' => 8, 'score' => 8000 + $hand[0]['rank']];
        if ($vals[0] == 4)
            return ['rank' => 7, 'score' => 7000 + array_search(4, $counts)];
        if ($vals[0] == 3 && ($vals[1] ?? 0) == 2)
            return ['rank' => 6, 'score' => 6000 + array_search(3, $counts)];
        if ($isFlush)
            return ['rank' => 5, 'score' => 5000 + $hand[0]['rank']];
        if ($isStraight)
            return ['rank' => 4, 'score' => 4000 + $hand[0]['rank']];
        if ($vals[0] == 3)
            return ['rank' => 3, 'score' => 3000 + array_search(3, $counts)];
        if ($vals[0] == 2 && ($vals[1] ?? 0) == 2)
            return ['rank' => 2, 'score' => 2000 + array_search(2, $counts)];
        if ($vals[0] == 2)
            return ['rank' => 1, 'score' => 1000 + array_search(2, $counts)];
        return ['rank' => 0, 'score' => $hand[0]['rank']];
    }
}

function houseWay($hand)
{
    usort($hand, function ($a, $b) {
        return $b['rank'] - $a['rank'];
    });
    $ranks = array_column($hand, 'rank');
    $counts = array_count_values($ranks);
    arsort($counts);
    $vals = array_values($counts);

    // This is a VERY simplified House Way
    // If pair, keep pair in high hand, take 2 next high cards for low hand.
    // Ensure high > low.

    // One Pair
    if ($vals[0] == 2 && ($vals[1] ?? 0) == 1) {
        $pairRank = array_search(2, $counts);
        $highHand = [];
        $lowHand = [];
        // Extract pair
        foreach ($hand as $c)
            if ($c['rank'] == $pairRank && count($highHand) < 2)
                $highHand[] = $c;
        $remaining = [];
        foreach ($hand as $c) {
            $found = false;
            foreach ($highHand as $h)
                if ($h === $c)
                    $found = true;
            if (!$found)
                $remaining[] = $c;
        }
        $lowHand = array_splice($remaining, 0, 2);
        $highHand = array_merge($highHand, $remaining);

        // Final check: if low > high, move one from low to high and vice versa.
        $evalH = evaluateHand($highHand);
        $evalL = evaluateHand($lowHand);
        if ($evalL['score'] > $evalH['score']) {
            $temp = $lowHand[0];
            $lowHand[0] = $highHand[count($highHand) - 1];
            $highHand[count($highHand) - 1] = $temp;
        }
        return ['high' => $highHand, 'low' => $lowHand];
    }

    // Default: split highest 5 and lowest 2, then swap if needed
    $lowHand = array_splice($hand, 0, 2);
    $highHand = $hand;
    return ['high' => $highHand, 'low' => $lowHand];
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'deal') {
        $bet = (int) ($_POST['bet'] ?? 0);
        if ($bet <= 0 || $bet > $money) {
            echo json_encode(['success' => false, 'message' => 'gtlm cược không hợp lệ!']);
            exit;
        }
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

        $_SESSION['paigow_bet'] = $bet;
        $hand = [];
        for ($i = 0; $i < 7; $i++)
            $hand[] = getCard();
        $_SESSION['paigow_hand'] = $hand;

        echo json_encode(['success' => true, 'hand' => $hand, 'money' => number_format($money - $bet, 0, ',', '.')]);
        exit;
    } elseif ($action === 'submit_split') {
        $lowIndices = json_decode($_POST['lowIndices']); // indexes of cards for low hand
        if (count($lowIndices) !== 2) {
            echo json_encode(['success' => false, 'message' => 'Bạn phải chọn đúng 2 lá cho tay bài thấp!']);
            exit;
        }

        $allCards = $_SESSION['paigow_hand'];
        $lowHand = [];
        $highHand = [];
        foreach ($allCards as $idx => $card) {
            if (in_array($idx, $lowIndices))
                $lowHand[] = $card;
            else
                $highHand[] = $card;
        }

        $evalP_H = evaluateHand($highHand);
        $evalP_L = evaluateHand($lowHand);

        if ($evalP_L['score'] > $evalP_H['score']) {
            echo json_encode(['success' => false, 'message' => 'Tay 5 lá (Cao) phải mạnh hơn tay 2 lá (Thấp)!']);
            exit;
        }

        // Dealer split
        $dealerFull = [];
        for ($i = 0; $i < 7; $i++)
            $dealerFull[] = getCard();
        $dealerSplit = houseWay($dealerFull);
        $evalD_H = evaluateHand($dealerSplit['high']);
        $evalD_L = evaluateHand($dealerSplit['low']);

        $highWin = ($evalP_H['score'] > $evalD_H['score']);
        $lowWin = ($evalP_L['score'] > $evalD_L['score']);

        $bet = $_SESSION['paigow_bet'];
        $winAmount = 0;
        $status = "";

        if ($highWin && $lowWin) {
            $winAmount = $bet * 2; // 1:1 payout + original bet
            $status = "Bạn thắng cả 2 tay! +100%";
        } elseif (!$highWin && !$lowWin) {
            $winAmount = 0;
            $status = "Dealer thắng cả 2 tay!";
        } else {
            $winAmount = $bet; // push, get bet back
            $status = "Hòa (Push) - Bạn thắng 1 tay và Dealer thắng 1 tay.";
        }

        if ($winAmount > 0) {
            $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $userId");
        }

        $conn->commit();
            $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];

        echo json_encode([
            'success' => true,
            'dealerHigh' => $dealerSplit['high'],
            'dealerLow' => $dealerSplit['low'],
            'winAmount' => $winAmount > $bet ? ($winAmount - $bet) : ($winAmount == 0 ? -$bet : 0),
            'status' => $status,
            'money' => number_format($newMoney, 0, ',', '.')
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Pai Gow Poker - Casino Classics</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
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
            width: clamp(70px, 10vw, 90px);
            aspect-ratio: 2/3;
            background: #fff;
            color: #000;
            border-radius: 0.8rem;
            border: 3px solid transparent;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            font-weight: 900;
            font-size: clamp(1.5rem, 3vw, 2rem);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card.selected {
            border-color: #00d2ff;
            transform: translateY(-15px);
            box-shadow: 0 15px 30px rgba(0, 210, 255, 0.4);
        }

        .card.red {
            color: #dc2626;
        }

        .card.black {
            color: #1e293b;
        }

        .card-v {
            position: absolute;
            top: 0.5rem;
            left: 0.5rem;
            font-size: 1rem;
        }

        .card-s {
            font-size: 3rem;
        }

        .hand-area {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.8rem;
            margin: 1.5rem 0;
            min-height: 140px;
        }

        .split-box {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 1rem;
            margin-bottom: 2rem;
        }

        .reveal-section {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 2rem;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .section-label {
            font-size: 0.9rem;
            font-weight: 800;
            color: #f1c40f;
            margin-top: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.8;
        }

        .btn-premium {
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 900;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
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
    <div class="game-wrapper" style="max-width:1000px; margin:2rem auto; position:relative; z-index:1; padding: 0 15px; width: 100%;">
        <div class="glass" style="padding: 2.5rem; text-align: center; border-radius: 2rem; width: 100%;">
            <h1 style="margin: 0 0 1rem; font-size: 2.5rem; font-weight: 900; color: #00d2ff; text-transform: uppercase; letter-spacing: 2px;">PAI GOW POKER</h1>
            <div style="background: rgba(0,0,0,0.3); padding: 10px 25px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.2); display: inline-block; margin-bottom: 2rem; max-width: 100%;">
                <span style="opacity: 0.8; font-size: 0.9rem; margin-right: 5px;">SỐ GTLM:</span>
                <span id="balance-val" style="font-weight: 900; font-size: clamp(14px, 3vw, 1.5rem); color: #f1c40f; word-break: break-all;"><?php echo number_format($money, 0, ',', '.'); ?></span> <span style="font-weight: 900; font-size: clamp(14px, 3vw, 1.5rem); color: #f1c40f;">gtlm</span>
            </div>

            <!-- Dealer Area -->
            <div id="dealer-view" style="display: none;">
                <h3 style="margin-bottom:10px; opacity:0.8; font-size:1.2rem; font-weight: bold;">BÀI NHÀ CÁI (DEALER)</h3>
                <div class="split-box">
                    <div class="reveal-section">
                        <div class="section-label">Tay Thấp (2 lá)</div>
                        <div id="dealer-low" class="hand-area"></div>
                    </div>
                    <div class="reveal-section">
                        <div class="section-label">Tay Cao (5 lá)</div>
                        <div id="dealer-high" class="hand-area"></div>
                    </div>
                </div>
                <div style="margin: 1.5rem 0; opacity: 0.5; font-weight: bold; font-size: 1.5rem;">VS</div>
            </div>

            <!-- Betting & Player Area -->
            <div id="player-view">
                <div id="bet-form" class="betting-area" style="display:flex; flex-direction:column; align-items:center;">
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
                            <input type="number" id="bet-amt" value="10000" style="background: transparent; color:#fff; outline:none; font-size:1.3rem; font-weight:900; text-align:center; width:120px; border: none;">
                        </div>
                        
                        <button class="btn-premium" id="deal-btn">CHIA BÀI</button>
                    </div>
                </div>

                <div id="split-controls" style="display: none;">
                    <h3 id="split-instruction" style="margin-bottom: 1.5rem; font-size: 1.2rem; color: #00d2ff;">Chọn 2 lá bài cho tay BÀI THẤP (Low Hand)</h3>
                    <div id="player-hand" class="hand-area"></div>
                    <div style="margin-top: 20px;">
                        <button id="submit-btn" class="btn-premium" style="background: #2ecc71; color: #fff;">XÁC NHẬN CHIA TAY</button>
                    </div>
                </div>
            </div>

            <div id="result-view" style="display: none; margin-top: 2rem;">
                <button id="reset-btn" class="btn-premium">VÁN MỚI</button>
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
            const prefix = window.location.pathname.includes('/games/') ? '../' : '';
            const scripts = ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'];
            
            scripts.forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src;
                s.async = false;
                document.head.appendChild(s);
            });
        })();

        // Pai Gow Logic
        let currentHand = [];
        let selectedIndices = [];

        $(document).ready(function() {
            $('.chip').click(function() {
                if ($('#deal-btn').is(':hidden')) return; // Khóa phỉnh khi đang chơi
                $('.chip').removeClass('active');
                $(this).addClass('active');
                const val = $(this).data('value');
                if (val === 'allin') {
                    $('#bet-amt').val(<?= $money ?>);
                } else {
                    $('#bet-amt').val(val);
                }
            });

            $('#deal-btn').click(function() {
                const bet = $('#bet-amt').val();
                if (bet < 1000) return Swal.fire('Lỗi', 'Cược tối thiểu 1,000 gtlm!', 'error');

                $.post('?action=deal', { bet: bet }, function(res) {
                    if (!res.success) return Swal.fire('Lỗi', res.message, 'error');
                    
                    currentHand = res.hand;
                    selectedIndices = [];
                    renderPlayerHand();
                    
                    $('#balance-val').text(res.money);
                    $('#bet-form').hide();
                    $('#split-controls').show();
                    $('#dealer-view').hide();
                    $('#result-view').hide();
                });
            });

            $('#submit-btn').click(function() {
                if (selectedIndices.length !== 2) {
                    return Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'warning', title: 'Nhắc nhở', text: 'Bạn phải chọn đúng 2 lá cho tay bài thấp!' });
                }

                $.post('?action=submit_split', { lowIndices: JSON.stringify(selectedIndices) }, function(res) {
                    if (!res.success) {
                        return Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'error', title: 'Lỗi', text: res.message });
                    }

                    $('#dealer-view').show();
                    renderHand('#dealer-low', res.dealerLow);
                    renderHand('#dealer-high', res.dealerHigh);

                    $('#balance-val').text(res.money);

                    setTimeout(() => {
                        if (res.winAmount > 0) {
                            if (typeof GameEffects !== 'undefined') GameEffects.showWin(res.winAmount);
                            Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'success', title: 'Thắng', text: res.status });
                        } else if (res.winAmount < 0) {
                            if (typeof GameEffects !== 'undefined') GameEffects.showLoss();
                            Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'error', title: 'Thua', text: res.status });
                        } else {
                            Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'info', title: 'Hòa', text: res.status });
                        }
                    }, 500);

                    $('#split-controls').hide();
                    $('#result-view').show();
                });
            });

            $('#reset-btn').click(function() {
                $('#result-view').hide();
                $('#dealer-view').hide();
                $('#bet-form').show();
                $('#player-hand').empty();
                $('#dealer-low').empty();
                $('#dealer-high').empty();
                $('.chip.active').click(); // trigger reset bet text
            });
        });

        function getSuitColor(suit) {
            return (suit === '♥' || suit === '♦') ? 'red' : 'black';
        }

        function renderPlayerHand() {
            const container = $('#player-hand');
            container.empty();
            currentHand.forEach((card, index) => {
                const colorClass = getSuitColor(card.suit);
                const el = $(`<div class="card ${colorClass}" data-index="${index}">
                    <div class="card-v">${card.val}</div>
                    <div class="card-s">${card.suit}</div>
                </div>`);
                
                el.click(function() {
                    const idx = $(this).data('index');
                    if (selectedIndices.includes(idx)) {
                        selectedIndices = selectedIndices.filter(i => i !== idx);
                        $(this).removeClass('selected');
                    } else {
                        if (selectedIndices.length >= 2) return;
                        selectedIndices.push(idx);
                        $(this).addClass('selected');
                    }
                });
                
                container.append(el);
            });
        }

        function renderHand(containerId, hand) {
            const container = $(containerId);
            container.empty();
            hand.forEach((card) => {
                const colorClass = getSuitColor(card.suit);
                container.append(`<div class="card ${colorClass}">
                    <div class="card-v">${card.val}</div>
                    <div class="card-s">${card.suit}</div>
                </div>`);
            });
        }
    </script>

<!-- AUTO-GENERATED BOT SCRIPT -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script>
    if (typeof BotVirtualCursor !== "undefined") {
        BotVirtualCursor.init("Bot Streamer");
        setInterval(() => {
            const allBtns = Array.from(document.querySelectorAll("button, .btn-bet, .chip, .spin-btn, #btnSpin, .bet-button, .card, .btn-primary, .btn-success, input[type='button'], input[type='submit']"));
            const btns = allBtns.filter(b => {
                if(b.offsetParent === null || b.disabled) return false;
                const txt = (b.innerText || b.value || "").toLowerCase();
                const cls = (b.className || "").toLowerCase();
                const id = (b.id || "").toLowerCase();
                
                // Exclude common navigation/help buttons
                if(txt.includes("hướng dẫn") || txt.includes("trang chủ") || txt.includes("nạp") || txt.includes("rút") || txt.includes("lịch sử") || txt.includes("quay lại") || txt.includes("thoát")) return false;
                if(cls.includes("back") || cls.includes("help") || cls.includes("guide") || cls.includes("close") || cls.includes("swal") || cls.includes("nav")) return false;
                if(id.includes("guide") || id.includes("back") || id.includes("close") || id.includes("nav")) return false;
                
                return true;
            });
            
            if(btns.length > 0) {
                const btn = btns[Math.floor(Math.random() * btns.length)];
                BotVirtualCursor.moveToElement($(btn), 1, 0, () => {
                    setTimeout(() => { 
                        BotVirtualCursor.simulateClick(() => {
                            try { btn.click(); } catch(e){}
                        });
                    }, 500);
                });
            }
        }, 3000 + Math.random() * 4000);
    }
</script>

</body>
</html>