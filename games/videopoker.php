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
$conn->query("CREATE TABLE IF NOT EXISTS history_videopoker (
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
            max-width: 900px;
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

        .pay-table {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            background: rgba(0, 0, 0, 0.3);
            padding: 1.5rem;
            border-radius: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--glass-border);
            font-size: 0.8rem;
        }

        .pay-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 10px;
            border-radius: 8px;
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
            gap: clamp(0.5rem, 2vw, 1.2rem);
            margin: 2rem 0;
            min-height: 160px;
            perspective: 1000px;
            flex-wrap: wrap;
        }

        .card-wrap {
            position: relative;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .card {
            width: clamp(75px, 11vw, 105px);
            aspect-ratio: 2/3;
            background: #fff;
            color: #000;
            border-radius: 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: clamp(1.5rem, 3vw, 2.5rem);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
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
            top: 0.5rem;
            left: 0.5rem;
            font-size: 1.1rem;
        }

        .card-s {
            font-size: 3.5rem;
        }

        .held-tag {
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary);
            color: #000;
            padding: 2px 10px;
            border-radius: 5px;
            font-size: 0.7rem;
            font-weight: 900;
            letter-spacing: 1px;
            opacity: 0;
            transition: 0.3s;
        }

        .hold .held-tag {
            opacity: 1;
            transform: translateX(-50%) translateY(-5px);
        }

        .hold .card {
            transform: translateY(-10px);
            ring: 4px solid var(--primary);
            box-shadow: 0 0 20px rgba(251, 191, 36, 0.4);
            border: 2px solid var(--primary);
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
            max-width: 300px;
            margin: 0.5rem 0;
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
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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

        @media (max-width: 600px) {
            .card {
                width: 75px;
            }

            .pay-table {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>


    <div class="main-container">
        <h1 class="game-title">VIDEO POKER</h1>
        <div class="balance-pill">💰 Số Gtlm: <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span>
            gtlm
        </div>

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
                <div class="card" style="background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.1);">P</div>
                <div class="card" style="background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.1);">O</div>
                <div class="card" style="background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.1);">K</div>
                <div class="card" style="background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.1);">E</div>
                <div class="card" style="background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.1);">R</div>
            </div>

            <div id="bet-area">
                <div class="input-group">
                    <span>Số gtlm cược</span>
                    <input type="number" id="bet-amt" value="1000" min="100" step="100">
                </div>
                <button id="deal-btn" class="btn btn-blue">PHÁT BÀI</button>
            </div>

            <div id="action-area" style="display: none;">
                <p style="margin-bottom: 1.5rem; font-weight: 700; opacity: 0.8;">Nhấp vào lá bài để GIỮ (HOLD)</p>
                <button id="draw-btn" class="btn btn-gold">THAY BÀI (DRAW)</button>
            </div>

            <div id="result-area" style="display: none; margin-top: 2rem;">
                <h2 id="eval-name"
                    style="color: var(--primary); margin-bottom: 0.5rem; font-size: 2.5rem; font-weight: 900;"></h2>
                <h3 id="win-amt" style="margin-bottom: 1.5rem; font-size: 1.5rem;"></h3>
                <button id="reset-btn" class="btn btn-blue">CHƠI TIẾP</button>
            </div>
        </div>

        <div class="history-section">
            <h2 style="font-size: 1.2rem; letter-spacing: 2px; margin-bottom: 1rem;">NHẬT KÝ CHIẾN THẮNG</h2>
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