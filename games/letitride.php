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
$conn->query("CREATE TABLE IF NOT EXISTS history_letitride (
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
$userName = $user['Name'];
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
            echo json_encode(['success' => false, 'message' => 'gtlm cược (x3) vượt quá Số Gtlm!']);
            exit;
        }

        $playerHand = [getCard(), getCard(), getCard()];
        $community = [getCard(), getCard()];

        $_SESSION['lir_bet'] = $bet;
        $_SESSION['lir_bets_active'] = [true, true, true]; // 3 bets
        $_SESSION['lir_hand'] = $playerHand;
        $_SESSION['lir_community'] = $community;

        echo json_encode(['success' => true, 'hand' => $playerHand]);
        exit;
    } elseif ($action === 'action') {
        $step = (int) $_POST['step']; // 1 or 2
        $decision = $_POST['decision']; // 'letitride' or 'pull'

        if ($decision === 'pull') {
            $_SESSION['lir_bets_active'][$step - 1] = false;
        }

        if ($step === 1) {
            echo json_encode(['success' => true, 'community1' => $_SESSION['lir_community'][0]]);
        } else {
            $hand = array_merge($_SESSION['lir_hand'], $_SESSION['lir_community']);
            $eval = evaluate5CardHand($hand);
            $bet = $_SESSION['lir_bet'];
            $activeCount = 0;
            foreach ($_SESSION['lir_bets_active'] as $a)
                if ($a)
                    $activeCount++;

            $winAmount = ($activeCount * $bet * $eval['pay']);
            if ($eval['pay'] == 0)
                $winAmount = -($activeCount * $bet);
            // If they win, they keep their bet and get the pay. 
            // In my logic, I'll calculate NET win/loss.
            // If pay 1:1, net win is activeCount * bet. If pay 0, net loss is activeCount * bet.

            $newMoney = $money + $winAmount;
            $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
            $stmt->bind_param("di", $newMoney, $userId);
            $stmt->execute();
            $stmt->close();

            // History
            $his = $conn->prepare("INSERT INTO history_letitride (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
            $totalBet = $activeCount * $bet;
            $resStr = $eval['name'] . " ($activeCount bets)";
            $his->bind_param("idss", $userId, $totalBet, $resStr, $winAmount);
            $his->execute();
            $his->close();

            echo json_encode([
                'success' => true,
                'community2' => $_SESSION['lir_community'][1],
                'eval' => $eval['name'],
                'winAmount' => $winAmount,
                'money' => number_format($newMoney, 0, ',', '.'),
                'rawMoney' => $newMoney
            ]);
        }
        exit;
    } elseif ($action === 'get_history') {
        $stmt = $conn->prepare("SELECT Bet, Result, WinAmount, Time FROM history_letitride WHERE Iduser = ? ORDER BY Time DESC LIMIT 10");
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
    <title>Let It Ride Poker - Đợi Chờ Hạnh Phúc</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <style>
        :root {
            --primary: #3b82f6;
            --secondary: #10b981;
            --accent: #f59e0b;
            --danger: #ef4444;
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
            text-shadow: 0 0 20px rgba(59, 130, 246, 0.3);
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
            padding: clamp(1.5rem, 5vw, 3rem);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            margin-bottom: 2rem;
        }

        .balance-pill {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--secondary);
            padding: 0.8rem 2rem;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 2rem;
            color: var(--secondary);
            font-weight: 700;
        }

        .section-label {
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.7;
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
            border: 1px dashed var(--glass-border);
            border-radius: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: rgba(255, 255, 255, 0.1);
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

        .bet-circles {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .circle {
            width: 65px;
            height: 65px;
            border: 3px solid var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 1.2rem;
            transition: all 0.3s;
            background: rgba(59, 130, 246, 0.1);
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
            padding: 1.2rem 2.5rem;
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
            background: linear-gradient(135deg, var(--primary) 0%, #1e40af 100%);
            color: #fff;
        }

        .btn-accent {
            background: linear-gradient(135deg, var(--accent) 0%, #d97706 100%);
            color: #fff;
        }

        .btn-red {
            background: linear-gradient(135deg, var(--danger) 0%, #991b1b 100%);
            color: #fff;
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
            .card-slot {
                width: 75px;
            }

            .bet-circles {
                gap: 1rem;
            }

            .circle {
                width: 55px;
                height: 55px;
            }
        }
    </style>
</head>

<body>


    <div class="main-container">
        <h1 class="game-title">LET IT RIDE POKER</h1>
        <div class="balance-pill">💰 Số Gtlm: <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span>
            gtlm
        </div>

        <div class="glass-card">
            <div class="hand-area">
                <div class="section-label">Community Cards</div>
                <div id="community-area" class="card-row">
                    <div class="card-slot" id="comm-1">🃟</div>
                    <div class="card-slot" id="comm-2">🃟</div>
                </div>

                <div class="section-label">Your Hand</div>
                <div id="player-hand" class="card-row">
                    <div class="card-slot">🃟</div>
                    <div class="card-slot">🃟</div>
                    <div class="card-slot">🃟</div>
                </div>
            </div>

            <div class="bet-circles">
                <div class="circle" id="c-1">1</div>
                <div class="circle" id="c-2">2</div>
                <div class="circle" id="c-3">$</div>
            </div>

            <div id="bet-form">
                <div class="input-group">
                    <span>Cược cơ sở (Mỗi vị trí x3)</span>
                    <input type="number" id="bet-amt" value="1000" min="100" step="100">
                </div>
                <button id="deal-btn" class="btn btn-blue">BẮT ĐẦU VÁN</button>
            </div>

            <div id="action-1" style="display: none;">
                <h3 style="margin-bottom: 1.5rem;">Cược Lượt 1: Bạn muốn giữ lại hay rút về?</h3>
                <div style="display: flex; flex-direction: column; align-items: center;">
                    <button onclick="sendAction(1, 'letitride')" class="btn btn-accent">LET IT RIDE (Giữ)</button>
                    <button onclick="sendAction(1, 'pull')" class="btn btn-red">PULL (Rút về)</button>
                </div>
            </div>

            <div id="action-2" style="display: none;">
                <h3 style="margin-bottom: 1.5rem;">Cược Lượt 2: Bạn muốn giữ lại hay rút về?</h3>
                <div style="display: flex; flex-direction: column; align-items: center;">
                    <button onclick="sendAction(2, 'letitride')" class="btn btn-accent">LET IT RIDE (Giữ)</button>
                    <button onclick="sendAction(2, 'pull')" class="btn btn-red">PULL (Rút về)</button>
                </div>
            </div>

            <div id="result-area" style="display: none; margin-top: 2rem;">
                <h2 id="final-eval"
                    style="color: var(--accent); margin-bottom: 0.5rem; font-size: 2rem; font-weight: 900;"></h2>
                <h3 id="win-msg" style="margin-bottom: 1.5rem; font-size: 1.5rem;"></h3>
                <button id="reset-btn" class="btn btn-blue">VÁN MỚI</button>
            </div>
        </div>

        <div class="history-section">
            <h2 style="font-size: 1.2rem; letter-spacing: 2px; margin-bottom: 1rem;">LỊCH SỬ CHƠI</h2>
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
            <div style="margin-top: 2rem;"><a href="../index.php"
                    style="color: var(--primary); text-decoration: none; font-weight: 700;">🏠 Về Trang Chủ</a></div>
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