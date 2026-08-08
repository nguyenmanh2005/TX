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
$stmt->close();

function getCard()
{
    $suits = ['♠', '♥', '♦', '♣'];
    $vals = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
    $vIdx = rand(0, 12);
    $sIdx = rand(0, 3);
    return ['val' => $vals[$vIdx], 'suit' => $suits[$sIdx], 'rank' => $vIdx + 2];
}

function evaluate5CardHand($hand)
{
    usort($hand, function ($a, $b) {
        return $a['rank'] - $b['rank'];
    });

    $ranks = array_column($hand, 'rank');
    $suits = array_column($hand, 'suit');
    $counts = array_count_values($ranks);
    arsort($counts);

    $isFlush = (count(array_unique($suits)) === 1);

    // Straight check
    $isStraight = false;
    if (count(array_unique($ranks)) === 5) {
        if ($ranks[4] - $ranks[0] === 4)
            $isStraight = true;
        elseif ($ranks[4] === 14 && $ranks[3] === 5 && $ranks[0] === 2)
            $isStraight = true; // A-2-3-4-5
    }

    $vals = array_values($counts);
    $primary = $vals[0];
    $secondary = $vals[1] ?? 0;

    if ($isStraight && $isFlush && $ranks[4] === 14 && $ranks[0] === 10)
        return ['rank' => 9, 'name' => 'Royal Flush', 'pay' => 1000];
    if ($isStraight && $isFlush)
        return ['rank' => 8, 'name' => 'Straight Flush', 'pay' => 200];
    if ($primary === 4)
        return ['rank' => 7, 'name' => 'Four of a Kind', 'pay' => 50];
    if ($primary === 3 && $secondary === 2)
        return ['rank' => 6, 'name' => 'Full House', 'pay' => 11];
    if ($isFlush)
        return ['rank' => 5, 'name' => 'Flush', 'pay' => 8];
    if ($isStraight)
        return ['rank' => 4, 'name' => 'Straight', 'pay' => 5];
    if ($primary === 3)
        return ['rank' => 3, 'name' => 'Three of a Kind', 'pay' => 3];
    if ($primary === 2 && $secondary === 2)
        return ['rank' => 2, 'name' => 'Two Pair', 'pay' => 2];
    if ($primary === 2) {
        $pairRank = array_search(2, $counts);
        if ($pairRank >= 10)
            return ['rank' => 1, 'name' => 'Pair of 10s+', 'pay' => 1];
    }

    return ['rank' => 0, 'name' => 'No Hand', 'pay' => 0];
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'deal') {
        $bet = (int) ($_POST['bet'] ?? 0);
        if ($bet <= 0 || ($bet * 3) > $money) {
            echo json_encode(['success' => false, 'message' => 'gtlm cược (x3) vượt quá Số Gtlm hiện có!']);
            exit;
        }

        // Deduct 3x bet immediately
        $conn->query("UPDATE users SET Money = Money - " . ($bet * 3) . " WHERE Iduser = $userId");

        $playerHand = [getCard(), getCard(), getCard()];
        $community = [getCard(), getCard()];

        $_SESSION['lir_bet'] = $bet;
        $_SESSION['lir_bets_active'] = [true, true, true]; // 3 bets
        $_SESSION['lir_hand'] = $playerHand;
        $_SESSION['lir_community'] = $community;

        echo json_encode(['success' => true, 'hand' => $playerHand, 'money' => number_format($money - ($bet * 3), 0, ',', '.')]);
        exit;
    } elseif ($action === 'action') {
        $step = (int) $_POST['step']; // 1 or 2
        $decision = $_POST['decision']; // 'letitride' or 'pull'

        if ($decision === 'pull') {
            $_SESSION['lir_bets_active'][$step - 1] = false;
        }

        if ($step === 1) {
            echo json_encode(['success' => true, 'community1' => $_SESSION['lir_community'][0], 'active_bets' => $_SESSION['lir_bets_active']]);
        } else {
            $hand = array_merge($_SESSION['lir_hand'], $_SESSION['lir_community']);
            $eval = evaluate5CardHand($hand);
            $bet = $_SESSION['lir_bet'];
            $activeCount = 0;
            foreach ($_SESSION['lir_bets_active'] as $a)
                if ($a)
                    $activeCount++;

            // If they pulled, refund those bets
            $pulledBets = 3 - $activeCount;
            $refund = $pulledBets * $bet;

            // Calculate winnings on active bets
            $winAmount = ($activeCount * $bet * $eval['pay']);
            
            // Total money added back to balance
            $totalReturn = $refund;
            if ($eval['pay'] > 0) {
                // Return active bets + winnings
                $totalReturn += ($activeCount * $bet) + $winAmount;
            }

            if ($totalReturn > 0) {
                $conn->query("UPDATE users SET Money = Money + $totalReturn WHERE Iduser = $userId");
            }

            $conn->commit();
            $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];

            $status = "";
            if ($eval['pay'] > 0) {
                $status = "Bạn đã thắng với " . $eval['name'] . " (x" . $eval['pay'] . ") bằng " . $activeCount . " cược kích hoạt!";
            } else {
                $status = "Bài của bạn không đủ điểm. Bạn đã thua " . $activeCount . " cược.";
            }

            echo json_encode([
                'success' => true,
                'community2' => $_SESSION['lir_community'][1],
                'eval' => $eval['name'],
                'winAmount' => ($eval['pay'] > 0) ? $winAmount : -($activeCount * $bet),
                'status' => $status,
                'money' => number_format($newMoney, 0, ',', '.'),
                'active_bets' => $_SESSION['lir_bets_active']
            ]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Let It Ride Poker - Casino Classics</title>
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

        .card-row {
            display: flex;
            justify-content: center;
            gap: 1rem;
            min-height: 140px;
            perspective: 1000px;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }

        .card-slot {
            width: clamp(80px, 12vw, 100px);
            aspect-ratio: 2/3;
            background: rgba(255, 255, 255, 0.03);
            border: 1px dashed rgba(255, 255, 255, 0.1);
            border-radius: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .card {
            width: 100%;
            height: 100%;
            background: #fff;
            border-radius: 0.8rem;
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 2rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.4);
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .card.red {
            color: #e74c3c;
        }

        .card.black {
            color: #2c3e50;
        }

        .card-v {
            position: absolute;
            top: 0.5rem;
            left: 0.5rem;
            font-size: 1.1rem;
        }

        .card-s {
            font-size: 3.5rem;
        }

        .section-label {
            font-size: 0.9rem;
            font-weight: 800;
            color: #00d2ff;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.8;
        }

        .bet-circles {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .circle {
            width: 65px;
            height: 65px;
            border: 3px solid #00d2ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 1.2rem;
            transition: all 0.3s;
            background: rgba(0, 210, 255, 0.1);
            position: relative;
        }

        .circle.inactive {
            border-color: #475569;
            color: #475569;
            background: rgba(0, 0, 0, 0.2);
            opacity: 0.5;
        }

        .circle.inactive::after {
            content: '';
            position: absolute;
            width: 80%;
            height: 3px;
            background: #94a3b8;
            transform: rotate(-45deg);
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
        .btn-deal { background: #f1c40f; color: #000; }
        .btn-ride { background: #2ecc71; color: #fff; }
        .btn-pull { background: #e74c3c; color: #fff; }

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
            <h1 style="margin: 0 0 1rem; font-size: 2.5rem; font-weight: 900; color: #00d2ff; text-transform: uppercase; letter-spacing: 2px;">LET IT RIDE POKER</h1>
            <div style="background: rgba(0,0,0,0.3); padding: 10px 25px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.2); display: inline-block; margin-bottom: 2rem; max-width: 100%;">
                <span style="opacity: 0.8; font-size: 0.9rem; margin-right: 5px;">SỐ GTLM:</span>
                <span id="balance-val" style="font-weight: 900; font-size: clamp(14px, 3vw, 1.5rem); color: #f1c40f; word-break: break-all;"><?php echo number_format($money, 0, ',', '.'); ?></span> <span style="font-weight: 900; font-size: clamp(14px, 3vw, 1.5rem); color: #f1c40f;">gtlm</span>
            </div>

            <div class="hand-area">
                <div class="section-label">Bài Chung (Community Cards)</div>
                <div id="community-area" class="card-row">
                    <div class="card-slot" id="comm-1"><img src="img/anh-bai/PNG/Cards (large)/card_back.png" style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.8rem; opacity: 0.5;"></div>
                    <div class="card-slot" id="comm-2"><img src="img/anh-bai/PNG/Cards (large)/card_back.png" style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.8rem; opacity: 0.5;"></div>
                </div>

                <div class="section-label">Bài Của Bạn (Your Hand)</div>
                <div id="player-hand" class="card-row">
                    <div class="card-slot" id="play-1"><img src="img/anh-bai/PNG/Cards (large)/card_back.png" style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.8rem; opacity: 0.5;"></div>
                    <div class="card-slot" id="play-2"><img src="img/anh-bai/PNG/Cards (large)/card_back.png" style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.8rem; opacity: 0.5;"></div>
                    <div class="card-slot" id="play-3"><img src="img/anh-bai/PNG/Cards (large)/card_back.png" style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.8rem; opacity: 0.5;"></div>
                </div>
            </div>

            <div class="bet-circles">
                <div class="circle" id="c-1">1</div>
                <div class="circle" id="c-2">2</div>
                <div class="circle" id="c-3">$</div>
            </div>

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
                        <span style="font-weight: bold; opacity: 0.8;">CƯỢC (x3):</span>
                        <input type="number" id="bet-amt" value="10000" style="background: transparent; color:#fff; outline:none; font-size:1.3rem; font-weight:900; text-align:center; width:120px; border: none;">
                    </div>
                    
                    <button class="btn-premium btn-deal" id="deal-btn">BẮT ĐẦU VÁN</button>
                </div>
            </div>

            <div id="action-1" style="display: none;">
                <h3 style="margin-bottom: 1.5rem; color: #00d2ff; font-weight: bold;">Cược Lượt 1: Bạn muốn giữ lại hay rút về?</h3>
                <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                    <button onclick="sendAction(1, 'letitride')" class="btn-premium btn-ride">LET IT RIDE (GIỮ)</button>
                    <button onclick="sendAction(1, 'pull')" class="btn-premium btn-pull">PULL (RÚT VỀ 1 PHẦN)</button>
                </div>
            </div>

            <div id="action-2" style="display: none;">
                <h3 style="margin-bottom: 1.5rem; color: #00d2ff; font-weight: bold;">Cược Lượt 2: Bạn muốn giữ lại hay rút về?</h3>
                <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                    <button onclick="sendAction(2, 'letitride')" class="btn-premium btn-ride">LET IT RIDE (GIỮ)</button>
                    <button onclick="sendAction(2, 'pull')" class="btn-premium btn-pull">PULL (RÚT VỀ 1 PHẦN)</button>
                </div>
            </div>

            <div id="result-area" style="display: none; margin-top: 2rem;">
                <button id="reset-btn" class="btn-premium btn-deal">VÁN MỚI</button>
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

        // Let It Ride Logic
        $(document).ready(function() {
            $('.chip').click(function() {
                if ($('#deal-btn').is(':hidden')) return;
                $('.chip').removeClass('active');
                $(this).addClass('active');
                const val = $(this).data('value');
                if (val === 'allin') {
                    $('#bet-amt').val(Math.floor(<?= $money ?> / 3));
                } else {
                    $('#bet-amt').val(val);
                }
            });

            $('#deal-btn').click(function() {
                const bet = $('#bet-amt').val();
                if (bet < 1000) return Swal.fire('Lỗi', 'Cược cơ sở tối thiểu 1,000 gtlm!', 'error');

                $.post('letitride.php?action=deal', { bet: bet }, function(res) {
                    if (!res.success) return Swal.fire('Lỗi', res.message, 'error');
                    
                    $('#balance-val').text(res.money);
                    
                    // Render player hand
                    $('#play-1').html(getCardHtml(res.hand[0]));
                    $('#play-2').html(getCardHtml(res.hand[1]));
                    $('#play-3').html(getCardHtml(res.hand[2]));
                    
                    $('#bet-form').hide();
                    $('#chipSelector').css('opacity', '0.5').css('pointer-events', 'none');
                    $('#action-1').show();
                    
                    // Reset circles
                    $('.circle').removeClass('inactive');
                });
            });

            $('#reset-btn').click(function() {
                $('#result-area').hide();
                $('#bet-form').show();
                $('#chipSelector').css('opacity', '1').css('pointer-events', 'auto');
                
                const backImg = '<img src="img/anh-bai/PNG/Cards (large)/card_back.png" style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.8rem; opacity: 0.5;">';
                $('#play-1').html(backImg);
                $('#play-2').html(backImg);
                $('#play-3').html(backImg);
                $('#comm-1').html(backImg);
                $('#comm-2').html(backImg);
                
                $('.circle').removeClass('inactive');
                $('.chip.active').click();
            });
        });

        function getCardHtml(card) {
            const suitMap = {'♥': 'hearts', '♦': 'diamonds', '♣': 'clubs', '♠': 'spades'};
            const suitStr = suitMap[card.suit];
            let valStr = card.val;
            if (!isNaN(valStr) && parseInt(valStr) < 10) valStr = '0' + parseInt(valStr);
            const url = `img/anh-bai/PNG/Cards (large)/card_${suitStr}_${valStr}.png`;
            return `<img src="${url}" class="card card-img" style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.8rem; background: transparent; padding: 0; border: none; box-shadow: 0 10px 20px rgba(0,0,0,0.4);">`;
        }

        function sendAction(step, decision) {
            $.post('letitride.php?action=action', { step: step, decision: decision }, function(res) {
                if (!res.success) return;

                if (decision === 'pull') {
                    $('#c-' + step).addClass('inactive');
                }

                if (step === 1) {
                    $('#comm-1').html(getCardHtml(res.community1));
                    $('#action-1').hide();
                    $('#action-2').show();
                } else {
                    $('#comm-2').html(getCardHtml(res.community2));
                    $('#balance-val').text(res.money);
                    $('#action-2').hide();
                    
                    setTimeout(() => {
                        if (res.winAmount > 0) {
                            if (typeof GameEffects !== 'undefined') GameEffects.showWin(res.winAmount);
                            Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'success', title: 'Thắng', text: res.status });
                        } else {
                            if (typeof GameEffects !== 'undefined') GameEffects.showLoss();
                            Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'error', title: 'Thua', text: res.status });
                        }
                    }, 500);

                    $('#result-area').show();
                }
            });
        }
    </script>
</body>
</html>