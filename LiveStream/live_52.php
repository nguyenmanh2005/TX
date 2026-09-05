<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_52', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require '../db_connect.php';
$useBotTheme = $botUserId;
require_once '../load_theme.php';

// Fallback theme cho Bàn 52 (Three Card Poker - Tím Neon Vũ Trụ)
$particleColor = $particleColor ?? '#00f2fe';
$shapeColors = $shapeColors ?? ['#00f2fe', '#712cf9', '#ff4757', '#ffd700'];
$bgGradient = $bgGradient ?? ['#000000', '#050015', '#0a0025'];
if (empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, ' . $bgGradient[0] . ' 0%, ' . $bgGradient[1] . ' 50%, ' . ($bgGradient[2] ?? $bgGradient[1]) . ' 100%)';
}



$userId = $botUserId;

$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$stmt->close();

const RANK_NAMES = ['High Card', 'Pair', 'Flush', 'Straight', 'Three of a Kind', 'Straight Flush'];

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
        return $a['rank'] - $b['rank'];
    });

    $isFlush = ($hand[0]['suit'] === $hand[1]['suit'] && $hand[1]['suit'] === $hand[2]['suit']);

    // Straight check (special case A-2-3)
    $isStraight = false;
    if ($hand[0]['rank'] + 1 === $hand[1]['rank'] && $hand[1]['rank'] + 1 === $hand[2]['rank']) {
        $isStraight = true;
    } elseif ($hand[0]['rank'] === 2 && $hand[1]['rank'] === 3 && $hand[2]['rank'] === 14) {
        $isStraight = true; // A-2-3
    }

    $isThreeOfAKind = ($hand[0]['rank'] === $hand[1]['rank'] && $hand[1]['rank'] === $hand[2]['rank']);
    $isPair = ($hand[0]['rank'] === $hand[1]['rank'] || $hand[1]['rank'] === $hand[2]['rank'] || $hand[0]['rank'] === $hand[2]['rank']);

    if ($isStraight && $isFlush)
        return ['rank' => 5, 'score' => 5000 + $hand[2]['rank']];
    if ($isThreeOfAKind)
        return ['rank' => 4, 'score' => 4000 + $hand[2]['rank']];
    if ($isStraight)
        return ['rank' => 3, 'score' => 3000 + $hand[2]['rank']];
    if ($isFlush)
        return ['rank' => 2, 'score' => 2000 + $hand[2]['rank']];
    if ($isPair) {
        $pairVal = ($hand[0]['rank'] === $hand[1]['rank']) ? $hand[0]['rank'] : $hand[1]['rank'];
        return ['rank' => 1, 'score' => 1000 + $pairVal];
    }
    return ['rank' => 0, 'score' => $hand[2]['rank']];
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'deal') {
        $ante = (int) ($_POST['ante'] ?? 0);
        $pairPlus = (int) ($_POST['pairPlus'] ?? 0);

        if ($ante <= 0 || ($ante + $pairPlus) > $money) {
            echo json_encode(['success' => false, 'message' => 'Cược không hợp lệ hoặc không đủ Gtlm!']);
            exit;
        }

        // Khấu trừ GTLM cược ban đầu (Ante + PairPlus)
        $conn->query("UPDATE users SET Money = Money - " . ($ante + $pairPlus) . " WHERE Iduser = $userId");

        $playerHand = [getCard(), getCard(), getCard()];
        $dealerHand = [getCard(), getCard(), getCard()];

        $_SESSION['3cp_ante'] = $ante;
        $_SESSION['3cp_pairPlus'] = $pairPlus;
        $_SESSION['3cp_playerHand'] = $playerHand;
        $_SESSION['3cp_dealerHand'] = $dealerHand;

        echo json_encode([
            'success' => true,
            'playerHand' => $playerHand,
            'playerEval' => evaluateHand($playerHand)['rank'],
            'money' => number_format($money - ($ante + $pairPlus), 0, ',', '.')
        ]);
        exit;
    } elseif ($action === 'play') {
        $playerHand = $_SESSION['3cp_playerHand'];
        $dealerHand = $_SESSION['3cp_dealerHand'];
        $ante = $_SESSION['3cp_ante'];
        $pairPlus = $_SESSION['3cp_pairPlus'];
        $play = $ante; // Play bet = Ante bet

        // Kiểm tra GTLM cược Play
        $currentMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
        if ($currentMoney < $play) {
            echo json_encode(['success' => false, 'message' => 'Không đủ GTLM để đặt cược Play!']);
            exit;
        }
        $conn->begin_transaction();
            $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
            $stmtLock->bind_param("i", $userId);
            $stmtLock->execute();
            $lockedMoney = $stmtLock->get_result()->fetch_assoc()['Money'] ?? 0;
            $stmtLock->close();
            if ($play > $lockedMoney) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Số dư không đủ hoặc thao tác quá nhanh!']);
                exit;
            }
            $conn->query("UPDATE users SET Money = Money - $play WHERE Iduser = $userId");

        $pEval = evaluateHand($playerHand);
        $dEval = evaluateHand($dealerHand);

        // Calculate Winnings (Total returned to user)
        $totalReturn = 0;
        $winAmount = -($ante + $pairPlus + $play); // Net win/loss
        $msg = "";

        // Pair Plus Payout (independent)
        $ppWin = 0;
        if ($pairPlus > 0) {
            if ($pEval['rank'] == 5)
                $ppWin = $pairPlus * 41; // SF 40:1 payout + original bet
            elseif ($pEval['rank'] == 4)
                $ppWin = $pairPlus * 31; // 3K 30:1 payout + original bet
            elseif ($pEval['rank'] == 3)
                $ppWin = $pairPlus * 7; // S 6:1 payout + original bet
            elseif ($pEval['rank'] == 2)
                $ppWin = $pairPlus * 5; // F 4:1 payout + original bet
            elseif ($pEval['rank'] == 1)
                $ppWin = $pairPlus * 2; // P 1:1 payout + original bet
        }
        $totalReturn += $ppWin;
        $winAmount += $ppWin;

        // Dealer Qualifies? (Needs Queen high or better)
        $dQualifies = ($dEval['rank'] > 0 || $dEval['score'] >= 12); // Q rank is 12

        if (!$dQualifies) {
            $totalReturn += ($ante * 2) + $play; // Ante wins 1:1, Play pushes
            $winAmount += ($ante * 2) + $play;
            $msg = "Dealer không đủ điều kiện (Qualify). Ante thắng, Play hòa.";
        } else {
            if ($pEval['score'] > $dEval['score']) {
                $totalReturn += ($ante * 2) + ($play * 2);
                $winAmount += ($ante * 2) + ($play * 2);
                $msg = "Bạn thắng Dealer!";
            } elseif ($pEval['score'] < $dEval['score']) {
                $msg = "Dealer thắng bạn.";
            } else {
                $totalReturn += $ante + $play;
                $winAmount += $ante + $play;
                $msg = "Hòa (Push).";
            }
        }

        if ($totalReturn > 0) {
            $conn->query("UPDATE users SET Money = Money + $totalReturn WHERE Iduser = $userId");
        }

        $conn->commit();
            $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];

        echo json_encode([
            'success' => true,
            'dealerHand' => $dealerHand,
            'dealerEval' => RANK_NAMES[$dEval['rank']],
            'playerEval' => RANK_NAMES[$pEval['rank']],
            'winAmount' => $winAmount,
            'message' => $msg,
            'money' => number_format($newMoney, 0, ',', '.')
        ]);
        exit;
    } elseif ($action === 'fold') {
        $ante = $_SESSION['3cp_ante'];
        $pairPlus = $_SESSION['3cp_pairPlus'];
        $winAmount = -($ante + $pairPlus);
        // Money was already deducted in deal step, so we don't need to do anything here except return new balance.
        $conn->commit();
            $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];

        echo json_encode([
            'success' => true, 
            'winAmount' => $winAmount, 
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
    <title>Three Card Poker - Casino Classics</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <style>
        body {
            margin: 0;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Exo 2', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            cursor: url('../img/chuot.png'), auto !important;
        }

        * { cursor: inherit; }
        button, a, input, select, .chip, .bet-input-box, .btn-premium {
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
            padding: 28px 56px;
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
            font-size: 3.8rem;
            margin-bottom: 8px;
            animation: badgeIconBounce 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
        }
        @keyframes badgeIconBounce {
            0% { transform: scale(0) rotate(-20deg); }
            70% { transform: scale(1.3) rotate(10deg); }
            100% { transform: scale(1) rotate(0deg); }
        }
        #result-badge-title {
            font-size: 1.8rem;
            font-weight: 900;
            letter-spacing: 2px;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        #result-badge-amount {
            font-size: 1.4rem;
            font-weight: 800;
            opacity: 0.95;
            font-family: 'Orbitron', sans-serif;
        }
        #result-badge-msg {
            font-size: 0.85rem;
            opacity: 0.75;
            margin-top: 6px;
            max-width: 320px;
            font-family: 'Exo 2', sans-serif;
        }

        .glass {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1.6rem;
        }

        .hand-section {
            margin-bottom: 0.8rem;
        }

        .label {
            font-size: 0.95rem;
            font-weight: 800;
            color: #00d2ff;
            margin-bottom: 0.35rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .card-row {
            display: flex;
            justify-content: center;
            gap: 0.6rem;
            min-height: 95px;
            perspective: 1000px;
            flex-wrap: wrap;
        }

        .card {
            width: clamp(55px, 9vw, 68px);
            aspect-ratio: 2/3;
            background: rgba(255, 255, 255, 0.05);
            border: 2px dashed rgba(255, 255, 255, 0.2);
            border-radius: 0.6rem;
            color: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 1.5rem;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        
        .card.revealed {
            background: #fff;
            border: none;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.4);
        }

        .card.red {
            color: #e74c3c;
        }

        .card.black {
            color: #2c3e50;
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

        .bet-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 0.8rem;
            margin: 0.8rem auto;
            max-width: 440px;
        }

        .bet-input-box {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0.6rem 0.8rem;
            border-radius: 1rem;
            cursor: pointer;
            transition: 0.3s;
        }

        .bet-input-box.focused {
            border-color: #f1c40f;
            box-shadow: 0 0 12px rgba(241, 196, 15, 0.3);
        }

        .bet-input-box span {
            display: block;
            font-size: 0.72rem;
            opacity: 0.7;
            margin-bottom: 2px;
        }

        .bet-input-box input {
            width: 100%;
            background: transparent;
            border: none;
            color: #fff;
            text-align: center;
            font-size: 1.15rem;
            font-weight: 700;
            outline: none;
            pointer-events: none;
        }

        .btn-premium {
            padding: 0.75rem 2.2rem;
            border: none;
            border-radius: 40px;
            font-weight: 900;
            font-size: 0.95rem;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
        }
        .btn-deal { background: #00d2ff; color: #000; }
        .btn-play { background: #2ecc71; color: #fff; }
        .btn-fold { background: #e74c3c; color: #fff; }

        .chip-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: center;
            margin-bottom: 8px;
            width: 100%;
        }
        .chip {
            padding: 5px 12px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.82rem;
            color: #fff;
            transition: 0.2s;
            user-select: none;
        }
        .chip:hover, .chip.active {
            background: #f1c40f;
            color: #000;
            border-color: #f1c40f;
            transform: scale(1.05);
            box-shadow: 0 3px 10px rgba(241, 196, 15, 0.4);
        }
    </style>
</head>

<body>
    <!-- 🏆 Badge thông báo thắng/thua giống game ID 1 -->
    <div id="result-status-badge">
        <div id="result-badge-icon">🏆</div>
        <div id="result-badge-title">THẮNG THREE CARD!</div>
        <div id="result-badge-amount">+100,000 GTLM</div>
        <div id="result-badge-msg">Bài lớn hơn Dealer!</div>
    </div>
    <div class="game-wrapper" style="max-width:720px; margin:0.5rem auto 1rem; position:relative; z-index:1; padding: 0 12px; width: 100%;">
        <div class="glass" style="padding: 1.1rem 1.4rem; text-align: center; border-radius: 1.5rem; width: 100%;">
            <h1 style="margin: 0 0 0.4rem; font-size: clamp(1.4rem, 3.2vw, 1.8rem); font-weight: 900; color: #00d2ff; text-transform: uppercase; letter-spacing: 2px;">THREE CARD POKER</h1>
            <div style="background: rgba(0,0,0,0.3); padding: 5px 18px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.2); display: inline-block; margin-bottom: 0.7rem; max-width: 100%;">
                <span style="opacity: 0.8; font-size: 0.85rem; margin-right: 5px;">SỐ GTLM:</span>
                <span id="balance-val" style="font-weight: 900; font-size: clamp(14px, 2.5vw, 1.3rem); color: #f1c40f; word-break: break-all;"><?php echo number_format($money, 0, ',', '.'); ?></span> <span style="font-weight: 900; font-size: clamp(14px, 2.5vw, 1.3rem); color: #f1c40f;">gtlm</span>
            </div>

            <div id="dealer-area" class="hand-section" style="margin-bottom: 0.5rem;">
                <div class="label" style="font-size: 0.85rem; margin-bottom: 0.25rem;">Nhà Cái (Dealer)</div>
                <div id="dealer-hand" class="card-row" style="min-height: 75px; gap: 0.5rem;">
                    <div class="card" id="dc-0"><img src="img/anh-bai/PNG/Cards (large)/card_back.png" style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.6rem; opacity: 0.5;"></div>
                    <div class="card" id="dc-1"><img src="img/anh-bai/PNG/Cards (large)/card_back.png" style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.6rem; opacity: 0.5;"></div>
                    <div class="card" id="dc-2"><img src="img/anh-bai/PNG/Cards (large)/card_back.png" style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.6rem; opacity: 0.5;"></div>
                </div>
            </div>

            <div id="player-area" class="hand-section" style="margin-bottom: 0.5rem;">
                <div class="label" style="font-size: 0.85rem; margin-bottom: 0.25rem;">Bạn (Player)</div>
                <div id="player-hand" class="card-row" style="min-height: 75px; gap: 0.5rem;">
                    <div class="card" id="pc-0"><img src="img/anh-bai/PNG/Cards (large)/card_back.png" style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.6rem; opacity: 0.5;"></div>
                    <div class="card" id="pc-1"><img src="img/anh-bai/PNG/Cards (large)/card_back.png" style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.6rem; opacity: 0.5;"></div>
                    <div class="card" id="pc-2"><img src="img/anh-bai/PNG/Cards (large)/card_back.png" style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.6rem; opacity: 0.5;"></div>
                </div>
            </div>

            <div id="bet-area">
                <p style="margin-bottom: 6px; opacity: 0.8; font-size: 0.78rem;">CHỌN Ô CƯỢC SAU ĐÓ CHỌN PHỈNH</p>
                <div class="chip-selector" id="chipSelector" style="margin-bottom: 6px;">
                    <div class="chip" data-value="10000">10K</div>
                    <div class="chip" data-value="50000">50K</div>
                    <div class="chip" data-value="100000">100K</div>
                    <div class="chip" data-value="500000">500K</div>
                    <div class="chip" data-value="1000000">1M</div>
                    <div class="chip" data-value="5000000">5M</div>
                    <div class="chip" data-value="0">XÓA</div>
                </div>

                <div class="bet-grid" style="margin: 0.5rem auto; gap: 0.6rem;">
                    <div class="bet-input-box focused" id="box-ante" onclick="selectBetBox('ante')">
                        <span>ANTE (Bắt buộc)</span>
                        <input type="number" id="ante" value="10000">
                    </div>
                    <div class="bet-input-box" id="box-pairplus" onclick="selectBetBox('pairplus')">
                        <span>PAIR PLUS (Tùy chọn)</span>
                        <input type="number" id="pairplus" value="0">
                    </div>
                </div>
                <button id="deal-btn" class="btn-premium btn-deal" style="padding: 0.65rem 2.5rem; font-size: 0.95rem;">CHIA BÀI</button>
            </div>

            <div id="play-area" style="display: none; margin-top: 1rem;">
                <p style="margin-bottom: 0.8rem; font-weight: 700; color: #f1c40f; font-size: 1rem;">BẠN MUỐN THEO (PLAY) HAY ÚP BÀI (FOLD)?</p>
                <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
                    <button id="play-btn" class="btn-premium btn-play" style="padding: 0.65rem 2rem;">PLAY (THEO)</button>
                    <button id="fold-btn" class="btn-premium btn-fold" style="padding: 0.65rem 2rem;">FOLD (ÚP BÀI)</button>
                </div>
            </div>

            <div id="result-area" style="display: none; margin-top: 1rem;">
                <button id="reset-btn" class="btn-premium btn-deal" style="padding: 0.65rem 2.5rem;">VÁN MỚI</button>
            </div>
            
            <div style="margin-top: 1rem; margin-bottom: 0.5rem;">
                <a href="../index.php" style="color:#fff; text-decoration:none; border:1px solid rgba(255,255,255,0.2); padding:0.5rem 1.6rem; border-radius:50px; font-weight: bold; font-size: 0.85rem; background: rgba(0,0,0,0.2); transition: 0.3s; display: inline-block;">🏠 THOÁT VỀ SẢNH</a>
            </div>
        </div>
    </div>

    <!-- Premium Three.js Effects System -->
    <canvas id="threejs-background"></canvas>
    <script>
        window.themeConfig = {
            particleCount: <?= $particleCount ?? 800 ?>,
            particleSize: <?= $particleSize ?? 0.05 ?>,
            particleColor: '<?= $particleColor ?? "#00f2fe" ?>',
            particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
            shapeCount: <?= $shapeCount ?? 15 ?>,
            shapeColors: <?= json_encode($shapeColors ?? ["#00f2fe", "#712cf9", "#ff4757", "#ffd700"]) ?>,
            shapeOpacity: <?= $shapeOpacity ?? 0.35 ?>,
            bgGradient: <?= json_encode($bgGradient ?? ["#000000", "#050015", "#0a0025"]) ?>
        };
    </script>
    <script src="../threejs-background.js"></script>
    <script src="../assets/js/game-effects.js"></script>
    <script src="../assets/js/game-effects-auto.js"></script>

    <script>
        let currentBetBox = 'ante';

        function selectBetBox(box) {
            currentBetBox = box;
            $('.bet-input-box').removeClass('focused');
            $('#box-' + box).addClass('focused');
        }

        function showResultBadge(isWin, winAmount, statusMsg) {
            const badge = document.getElementById('result-status-badge');
            const icon  = document.getElementById('result-badge-icon');
            const title = document.getElementById('result-badge-title');
            const amtEl = document.getElementById('result-badge-amount');
            const msgEl = document.getElementById('result-badge-msg');

            if (!badge) return;

            if (isWin === true) {
                badge.style.borderColor = '#f1c40f';
                badge.style.boxShadow   = '0 25px 80px rgba(0,0,0,0.85), 0 0 80px rgba(241,196,15,0.6)';
                icon.textContent  = '🏆';
                title.textContent = 'THẮNG THREE CARD!';
                title.style.color = '#f1c40f';
                amtEl.textContent = '+' + parseInt(winAmount).toLocaleString('vi-VN') + ' GTLM';
                amtEl.style.color = '#4ade80';
            } else if (isWin === false) {
                badge.style.borderColor = '#e74c3c';
                badge.style.boxShadow   = '0 25px 80px rgba(0,0,0,0.85), 0 0 60px rgba(231,76,60,0.5)';
                icon.textContent  = '💨';
                title.textContent = 'BAY MÀU!';
                title.style.color = '#e74c3c';
                amtEl.textContent = '-' + parseInt(Math.abs(winAmount)).toLocaleString('vi-VN') + ' GTLM';
                amtEl.style.color = '#ff4757';
            } else {
                badge.style.borderColor = '#38bdf8';
                badge.style.boxShadow   = '0 25px 80px rgba(0,0,0,0.85), 0 0 60px rgba(56,189,248,0.5)';
                icon.textContent  = '🤝';
                title.textContent = 'HÒA TIỀN!';
                title.style.color = '#38bdf8';
                amtEl.textContent = '0 GTLM';
                amtEl.style.color = '#38bdf8';
            }
            msgEl.textContent = statusMsg || '';

            badge.style.display = 'block';
            requestAnimationFrame(() => {
                badge.style.transform = 'translate(-50%, -50%) scale(1.08)';
                badge.style.opacity   = '1';
                setTimeout(() => { badge.style.transform = 'translate(-50%, -50%) scale(1)'; }, 150);
            });
            setTimeout(() => {
                badge.style.transform = 'translate(-50%, -50%) scale(0.8)';
                badge.style.opacity   = '0';
                setTimeout(() => { badge.style.display = 'none'; }, 400);
            }, 3500);
        }

        $(document).ready(function() {
            $('.chip').click(function() {
                if ($('#deal-btn').is(':hidden')) return;
                const val = $(this).data('value');
                $('#' + currentBetBox).val(val);
                
                // Add tiny animation to show it registered
                $('#box-' + currentBetBox).css('transform', 'scale(1.05)');
                setTimeout(() => $('#box-' + currentBetBox).css('transform', 'none'), 150);
            });

            function renderCard(target, card) {
                const suitMap = {'♥': 'hearts', '♦': 'diamonds', '♣': 'clubs', '♠': 'spades'};
                const suitStr = suitMap[card.suit];
                let valStr = card.val;
                if (!isNaN(valStr) && parseInt(valStr) < 10) valStr = '0' + parseInt(valStr);
                const url = `img/anh-bai/PNG/Cards (large)/card_${suitStr}_${valStr}.png`;

                $(target).addClass('revealed')
                         .html(`<img src="${url}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.8rem;">`)
                         .css({'background': 'transparent', 'border': 'none', 'padding': '0'});
            }

            $('#deal-btn').click(function() {
                const ante = parseInt($('#ante').val()) || 0;
                const pairPlus = parseInt($('#pairplus').val()) || 0;

                if (ante < 100) return Swal.fire('Lỗi', 'Cược Ante tối thiểu 100 gtlm!', 'error');

                $.post('?action=deal', { ante: ante, pairPlus: pairPlus }, function(res) {
                    if (!res.success) return Swal.fire('Lỗi', res.message, 'error');
                    
                    $('#balance-val').text(res.money);
                    $('#bet-area').hide();
                    
                    renderCard('#pc-0', res.playerHand[0]);
                    renderCard('#pc-1', res.playerHand[1]);
                    renderCard('#pc-2', res.playerHand[2]);
                    
                    $('#play-area').show();
                });
            });

            $('#play-btn').click(function() {
                $.post('?action=play', function(res) {
                    if (!res.success) return Swal.fire('Lỗi', res.message, 'error');
                    
                    $('#play-area').hide();
                    
                    renderCard('#dc-0', res.dealerHand[0]);
                    renderCard('#dc-1', res.dealerHand[1]);
                    renderCard('#dc-2', res.dealerHand[2]);
                    
                    $('#balance-val').text(res.money);

                    setTimeout(() => {
                        if (res.winAmount > 0) {
                            if (typeof GameEffects !== 'undefined') {
                                if (res.winAmount >= 500000) GameEffects.showBigWin(res.winAmount);
                                else GameEffects.showWin(res.winAmount);
                            }
                            showResultBadge(true, res.winAmount, res.message || 'Bạn thắng Dealer!');
                        } else if (res.winAmount < 0) {
                            if (typeof GameEffects !== 'undefined') GameEffects.showLoss(Math.abs(res.winAmount));
                            showResultBadge(false, Math.abs(res.winAmount), res.message || 'Dealer thắng bạn.');
                        } else {
                            showResultBadge('push', 0, 'Hòa tiền (Push) - Hoàn trả tiền cược.');
                        }
                    }, 500);

                    $('#result-area').show();
                });
            });

            $('#fold-btn').click(function() {
                $.post('?action=fold', function(res) {
                    if (!res.success) return;
                    
                    $('#play-area').hide();
                    $('#balance-val').text(res.money);
                    
                    const lossAmt = Math.abs(res.winAmount) || 10000;
                    if (typeof GameEffects !== 'undefined') GameEffects.showLoss(lossAmt);
                    showResultBadge(false, lossAmt, 'Bạn đã úp bài và cắt lỗ cược Ante.');
                    
                    $('#result-area').show();
                });
            });

            $('#reset-btn').click(function() {
                $('#result-area').hide();
                $('#bet-area').show();
                
                const resetCard = '<img src="img/anh-bai/PNG/Cards (large)/card_back.png" style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.8rem; opacity: 0.5;">';
                for(let i=0; i<3; i++) {
                    $('#pc-'+i).removeClass('revealed red black').html(resetCard).css({'background': '', 'border': '', 'padding': ''});
                    $('#dc-'+i).removeClass('revealed red black').html(resetCard).css({'background': '', 'border': '', 'padding': ''});
                }
            });
        });
    </script>

    <!-- SMART PRO BOT SCRIPT -->
    <script>
    if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
    if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
    </script>
    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="bots/bot_52.js"></script>
</body>
</html>