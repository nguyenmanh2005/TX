<?php
session_start();
require '../db_connect.php';
require_once '../load_theme.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['Iduser'];

// Auto-create history table
$conn->query("CREATE TABLE IF NOT EXISTS history_paigow (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Iduser INT NOT NULL,
    Bet DECIMAL(30,2) NOT NULL,
    Result VARCHAR(255) NOT NULL,
    WinAmount DECIMAL(30,2) NOT NULL,
    Time DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

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
        // Take 2 cards for low hand from remaining, but not so strong they beat the pair?
        // Actually, just put the 2 highest remaining in low hand, rest in high.
        $lowHand = array_splice($remaining, 0, 2);
        $highHand = array_merge($highHand, $remaining);

        // Final check: if low > high, move one from low to high and vice versa.
        $evalH = evaluateHand($highHand);
        $evalL = evaluateHand($lowHand);
        if ($evalL['score'] > $evalH['score']) {
            // Swap highest of low with lowest of high
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
            echo json_encode(['success' => false, 'message' => 'Cược không hợp lệ!']);
            exit;
        }
        $_SESSION['paigow_bet'] = $bet;
        $hand = [];
        for ($i = 0; $i < 7; $i++)
            $hand[] = getCard();
        $_SESSION['paigow_hand'] = $hand;

        echo json_encode(['success' => true, 'hand' => $hand]);
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
            $winAmount = $bet * 0.95; // 5% commission
            $status = "Bạn thắng cả 2 tay! +95%";
        } elseif (!$highWin && !$lowWin) {
            $winAmount = -$bet;
            $status = "Dealer thắng cả 2 tay!";
        } else {
            $winAmount = 0;
            $status = "Hòa (Push) - Bạn thắng 1 tay và Dealer thắng 1 tay.";
        }

        $newMoney = $money + $winAmount;
        $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $stmt->bind_param("di", $newMoney, $userId);
        $stmt->execute();
        $stmt->close();

        // History
        $his = $conn->prepare("INSERT INTO history_paigow (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $resStr = "P: (H:" . $evalP_H['rank'] . "/L:" . $evalP_L['rank'] . ") D: (H:" . $evalD_H['rank'] . "/L:" . $evalD_L['rank'] . ")";
        $his->bind_param("idss", $userId, $bet, $resStr, $winAmount);
        $his->execute();
        $his->close();

        echo json_encode([
            'success' => true,
            'dealerHigh' => $dealerSplit['high'],
            'dealerLow' => $dealerSplit['low'],
            'winAmount' => $winAmount,
            'status' => $status,
            'money' => number_format($newMoney, 0, ',', '.'),
            'rawMoney' => $newMoney
        ]);
        exit;
    } elseif ($action === 'get_history') {
        $stmt = $conn->prepare("SELECT Bet, Result, WinAmount, Time FROM history_paigow WHERE Iduser = ? ORDER BY Time DESC LIMIT 10");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $his = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'history' => $his]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Pai Gow Poker - Bậc Thầy Phân Loại</title>
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
            --accent: #064e3b;
            --glass: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Exo 2', system-ui, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
        }

        .main-container {
            position: relative;
            z-index: 1;
            width: 95%;
            max-width: 1000px;
            margin: 2rem auto;
            text-align: center;
        }

        .game-title {
            font-size: clamp(2rem, 8vw, 3.5rem);
            font-weight: 900;
            color: var(--primary);
            text-shadow: 0 0 20px rgba(251, 191, 36, 0.3);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 4px;
        }

        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 2.5rem;
            padding: clamp(1.5rem, 5vw, 2.5rem);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            margin-bottom: 2rem;
        }

        .balance-pill {
            background: rgba(110, 231, 183, 0.1);
            border: 1px solid var(--secondary);
            padding: 0.8rem 2rem;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 2rem;
            color: var(--secondary);
            font-weight: 700;
        }

        .hand-area {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.8rem;
            margin: 1.5rem 0;
            min-height: 140px;
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
            border-color: var(--primary);
            transform: translateY(-15px);
            box-shadow: 0 15px 30px rgba(251, 191, 36, 0.4);
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

        .section-label {
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--primary);
            margin-top: 1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.8;
        }

        .btn {
            padding: 1.2rem 3rem;
            border-radius: 50px;
            border: none;
            font-weight: 900;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: 1rem;
            width: 100%;
            max-width: 320px;
            margin: 0.5rem;
        }

        .btn-gold {
            background: linear-gradient(135deg, var(--primary) 0%, #d97706 100%);
            color: #000;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .split-box {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .reveal-section {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 2rem;
            padding: 1.5rem;
            border: 1px solid var(--glass-border);
        }

        .history-section {
            background: var(--glass);
            border-radius: 2rem;
            padding: 2rem;
            border: 1px solid var(--glass-border);
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .history-table th {
            color: rgba(255, 255, 255, 0.4);
            text-transform: uppercase;
            font-size: 0.8rem;
            padding: 1rem;
            border-bottom: 2px solid var(--glass-border);
        }

        .history-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--glass-border);
            font-weight: 600;
        }

        .input-group {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
            padding: 1.2rem;
            border-radius: 1.5rem;
            margin: 0 auto 1.5rem;
            max-width: 250px;
        }

        .input-group span {
            display: block;
            font-size: 0.8rem;
            opacity: 0.6;
            margin-bottom: 0.5rem;
        }

        .input-group input {
            width: 100%;
            background: transparent;
            border: none;
            color: #fff;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            outline: none;
        }

        @media (max-width: 600px) {
            .card {
                width: 65px;
            }

            .split-box {
                gap: 1rem;
            }
        }
    </style>
</head>

<body>


    <div class="main-container">
        <h1 class="game-title">PAI GOW POKER</h1>
        <div class="balance-pill">💰 Số Gtlm: <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span>
            gtlm
        </div>

        <div class="glass-card">
            <div id="dealer-view" style="display: none;">
                <h3 style="color: var(--danger); font-weight: 900; letter-spacing: 2px;">DEALER'S HAND</h3>
                <div class="split-box">
                    <div class="reveal-section">
                        <div class="section-label">Low Hand (2)</div>
                        <div id="dealer-low" class="hand-area"></div>
                    </div>
                    <div class="reveal-section">
                        <div class="section-label">High Hand (5)</div>
                        <div id="dealer-high" class="hand-area"></div>
                    </div>
                </div>
            </div>

            <div id="player-view">
                <div id="bet-form">
                    <div class="input-group">
                        <span>Số gtlm đặt cược</span>
                        <input type="number" id="bet-amt" value="1000" min="100" step="100">
                    </div>
                    <button id="deal-btn" class="btn btn-gold">CHIA 7 LÁ BÀI</button>
                </div>

                <div id="split-controls" style="display: none;">
                    <h3 id="split-instruction" style="margin-bottom: 1.5rem;">Chọn 2 lá cho tay BÀI THẤP (Low Hand)</h3>
                    <div id="player-hand" class="hand-area"></div>
                    <button id="submit-btn" class="btn btn-gold">XÁC NHẬN PHÂN TAY</button>
                </div>
            </div>

            <div id="result-view" style="display: none; margin-top: 2rem;">
                <h2 id="result-status"
                    style="color: var(--primary); font-size: 2rem; font-weight: 900; margin-bottom: 0.5rem;"></h2>
                <h3 id="result-amount" style="font-size: 1.5rem; margin-bottom: 1.5rem;"></h3>
                <button id="reset-btn" class="btn btn-gold">VÁN MỚI</button>
            </div>
        </div>

        <div class="history-section">
            <h2 style="font-size: 1.2rem; letter-spacing: 2px; margin-bottom: 1rem;">LỊCH SỬ GẦN ĐÂY</h2>
            <div style="overflow-x: auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>gtlm cược</th>
                            <th>Kết quả</th>
                            <th>Thắng/Thua</th>
                        </tr>
                    </thead>
                    <tbody id="history-body"></tbody>
                </table>
            </div>
            <div style="margin-top: 2.5rem;"><a href="../index.php"
                    style="color: var(--primary); text-decoration: none; font-weight: 700; border: 1px solid var(--primary); padding: 0.8rem 2.5rem; border-radius: 50px; transition: 0.3s;">🏠
                    QUAY LẠI SẢNH</a></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/confetti.browser.min.js"></script>

    <?php require_once '../casino_help.php'; ?>












    <!-- Premium Effects System -->
    <canvas id="threejs-background"></canvas>
    <script>
        (function () {
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
    </script>

</body>

</html>