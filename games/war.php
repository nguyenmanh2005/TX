<?php
session_start();
require '../db_connect.php';
require_once '../load_theme.php';

// Kiá»ƒm tra Ä‘Äƒng nháº­p
if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['Iduser'];

// Láº¥y thÃ´ng tin user
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$userName = $user['Name'];
$stmt->close();

// AJAX handler
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false, 'message' => ''];

    // Khá»Ÿi táº¡o bá»™ bÃ i
    $suits = ['â™ ', 'â™¥', 'â™¦', 'â™£'];
    $values = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
    $valueMap = array_flip($values); // 2=0, ..., A=12

    if ($action === 'deal') {
        $bet = (int) ($_POST['bet'] ?? 0);
        if ($bet <= 0 || $bet > $money) {
            $response['message'] = "gtlm cÆ°á»£c khÃ´ng há»£p lá»‡!";
        } else {
            // RÃºt 2 lÃ¡
            $pValIdx = rand(0, 12);
            $pSuitIdx = rand(0, 3);
            $dValIdx = rand(0, 12);
            $dSuitIdx = rand(0, 3);

            $playerCard = ['val' => $values[$pValIdx], 'suit' => $suits[$pSuitIdx], 'score' => $pValIdx + 2];
            $dealerCard = ['val' => $values[$dValIdx], 'suit' => $suits[$dSuitIdx], 'score' => $dValIdx + 2];

            $_SESSION['war_bet'] = $bet;
            $_SESSION['war_player_card'] = $playerCard;
            $_SESSION['war_dealer_card'] = $dealerCard;

            $status = "";
            $winAmount = 0;
            $over = false;

            if ($playerCard['score'] > $dealerCard['score']) {
                $winAmount = $bet; // Tháº¯ng Äƒn 1-1
                $status = "WIN";
                $over = true;
            } elseif ($playerCard['score'] < $dealerCard['score']) {
                $winAmount = -$bet;
                $status = "LOSE";
                $over = true;
            } else {
                $status = "TIE";
                $over = false;
            }

            if ($over) {
                $newMoney = $money + $winAmount;
                $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
                $stmt->bind_param("di", $newMoney, $userId);
                $stmt->execute();
                $stmt->close();

                // LÆ°u lá»‹ch sá»­
                $resStr = "P: " . $playerCard['val'] . $playerCard['suit'] . " vs D: " . $dealerCard['val'] . $dealerCard['suit'];
                $his = $conn->prepare("INSERT INTO history_war (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
                $his->bind_param("idss", $userId, $bet, $resStr, $winAmount);
                $his->execute();
                $his->close();
            }

            $response = [
                'success' => true,
                'playerCard' => $playerCard,
                'dealerCard' => $dealerCard,
                'status' => $status,
                'money' => number_format($money + ($over ? $winAmount : 0), 0, ',', '.'),
                'rawMoney' => $money + ($over ? $winAmount : 0)
            ];
        }
    } elseif ($action === 'war') {
        $bet = $_SESSION['war_bet'];
        if ($money < $bet) {
            $response['message'] = "KhÃ´ng Ä‘á»§ gtlm Ä‘á»ƒ tham chiáº¿n!";
        } else {
            // RÃºt thÃªm 2 lÃ¡ sau khi bá» qua 3 lÃ¡ (visual only, here we just pick 2)
            $pValIdx = rand(0, 12);
            $pSuitIdx = rand(0, 3);
            $dValIdx = rand(0, 12);
            $dSuitIdx = rand(0, 3);

            $playerCardNew = ['val' => $values[$pValIdx], 'suit' => $suits[$pSuitIdx], 'score' => $pValIdx + 2];
            $dealerCardNew = ['val' => $values[$dValIdx], 'suit' => $suits[$dSuitIdx], 'score' => $dValIdx + 2];

            $winAmount = 0;
            $status = "";
            if ($playerCardNew['score'] >= $dealerCardNew['score']) {
                // Tháº¯ng tráº­n War: Ä‚n cÆ°á»£c War (1-1) vÃ  Ä‘áº©y (push) cÆ°á»£c Ante. Tá»•ng lÃ  tháº¯ng 1 Ä‘Æ¡n vá»‹ bet ban Ä‘áº§u.
                // User requirement says "Tháº¯ng -> nhÃ¢n Ä‘Ã´i bet". In War context, usually player pays 1 more unit.
                // If they win, they get 2 units back (the new bet + 1 win).
                $winAmount = $bet;
                $status = "WIN_WAR";
            } else {
                // Thua tráº­n War: Máº¥t cáº£ Ante vÃ  War bet. Tá»•ng lÃ  máº¥t 2 Ä‘Æ¡n vá»‹ bet ban Ä‘áº§u.
                $winAmount = -($bet * 2);
                $status = "LOSE_WAR";
            }

            $newMoney = $money + $winAmount;
            $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
            $stmt->bind_param("di", $newMoney, $userId);
            $stmt->execute();
            $stmt->close();

            // LÆ°u lá»‹ch sá»­
            $resStr = "WAR! P: " . $playerCardNew['val'] . $playerCardNew['suit'] . " vs D: " . $dealerCardNew['val'] . $dealerCardNew['suit'];
            $totalBet = $bet * 2;
            $his = $conn->prepare("INSERT INTO history_war (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
            $his->bind_param("idss", $userId, $totalBet, $resStr, $winAmount);
            $his->execute();
            $his->close();

            $response = [
                'success' => true,
                'playerCard' => $playerCardNew,
                'dealerCard' => $dealerCardNew,
                'status' => $status,
                'money' => number_format($newMoney, 0, ',', '.'),
                'rawMoney' => $newMoney
            ];
        }
    } elseif ($action === 'surrender') {
        $bet = $_SESSION['war_bet'];
        $loss = ceil($bet / 2);
        $newMoney = $money - $loss;

        $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $stmt->bind_param("di", $newMoney, $userId);
        $stmt->execute();
        $stmt->close();

        // LÆ°u lá»‹ch sá»­
        $resStr = "Surrender";
        $his = $conn->prepare("INSERT INTO history_war (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $winAmt = -$loss;
        $his->bind_param("iisi", $userId, $bet, $resStr, $winAmt);
        $his->execute();
        $his->close();

        $response = [
            'success' => true,
            'status' => 'SURRENDER',
            'money' => number_format($newMoney, 0, ',', '.'),
            'rawMoney' => $newMoney
        ];
    } elseif ($action === 'get_history') {
        $stmt = $conn->prepare("SELECT * FROM history_war WHERE Iduser = ? ORDER BY Time DESC LIMIT 10");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $history = [];
        while ($row = $res->fetch_assoc()) {
            $history[] = $row;
        }
        $response = ['success' => true, 'history' => $history];
    }

    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Casino War - Tráº­n Chiáº¿n BÃ i TÃ¢y</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <style>
        :root {
            --primary: #e94560;
            --secondary: #4ecca3;
            --accent: #f0932b;
            --bg-dark: #1a1a2e;
            --glass: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
            --text-muted: rgba(255, 255, 255, 0.6);
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
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            overflow-x: hidden;
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
            padding: 1rem;
            text-align: center;
        }

        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 2rem;
            padding: 2.5rem 1.5rem;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            margin-bottom: 2rem;
            transition: transform 0.3s ease;
        }

        .game-title {
            font-size: clamp(2rem, 8vw, 3.5rem);
            font-weight: 900;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 4px;
            background: linear-gradient(to right, #fff, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .balance-pill {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.8rem 1.5rem;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 2rem;
            border: 1px solid var(--glass-border);
            font-weight: 700;
            font-size: 1.1rem;
        }

        .card-area {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: clamp(1rem, 5vw, 4rem);
            margin: 2.5rem 0;
            perspective: 1000px;
            flex-wrap: wrap;
        }

        .card-container {
            flex: 1;
            min-width: 140px;
            text-align: center;
        }

        .card-label {
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 1rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .playing-card {
            width: clamp(100px, 20vw, 140px);
            aspect-ratio: 2/3;
            background: #fff;
            border-radius: 1rem;
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .playing-card.red {
            color: #e74c3c;
        }

        .playing-card.black {
            color: #2c3e50;
        }

        .card-value {
            position: absolute;
            top: 0.8rem;
            left: 0.8rem;
            font-size: 1.5rem;
            font-weight: 900;
        }

        .card-suit {
            font-size: clamp(3rem, 10vw, 4.5rem);
        }

        .controls {
            margin-top: 2rem;
        }

        .bet-input-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        input[type="number"] {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--glass-border);
            padding: 1rem 1.5rem;
            border-radius: 1rem;
            color: #fff;
            font-size: 1.3rem;
            width: 100%;
            max-width: 250px;
            text-align: center;
            outline: none;
            transition: border-color 0.3s;
        }

        input[type="number"]:focus {
            border-color: var(--primary);
        }

        .btn {
            background: linear-gradient(135deg, var(--primary) 0%, #c62a48 100%);
            border: none;
            padding: 1rem 2.5rem;
            border-radius: 1rem;
            color: #fff;
            font-size: 1.1rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin: 0.5rem;
            width: auto;
            min-width: 180px;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(233, 69, 96, 0.4);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary) 0%, #3a9679 100%);
        }

        .btn-war {
            background: linear-gradient(135deg, var(--accent) 0%, #d35400 100%);
        }

        .status-msg {
            margin-top: 1.5rem;
            font-size: 1.8rem;
            font-weight: 900;
            min-height: 2.5rem;
            text-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
        }

        .history-container {
            overflow-x: auto;
            width: 100%;
            margin-top: 1rem;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 500px;
        }

        .history-table th {
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
            color: var(--text-muted);
            padding: 1rem;
            border-bottom: 2px solid var(--glass-border);
        }

        .history-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--glass-border);
            font-weight: 600;
        }

        .shimmer {
            animation: shimmer 2s infinite ease-in-out;
        }

        @keyframes shimmer {

            0%,
            100% {
                opacity: 0.6;
            }

            50% {
                opacity: 1;
            }
        }

        @media (max-width: 600px) {
            .main-container {
                padding: 0.5rem;
            }

            .glass-card {
                padding: 1.5rem 1rem;
            }

            .card-area {
                gap: 1rem;
            }

            .btn {
                width: 100%;
                margin: 0.5rem 0;
            }
        }
    </style>
</head>

<body>


    <div class="main-container">
        <h1 class="game-title">CASINO WAR</h1>
        <p style="color: rgba(255,255,255,0.6); margin-bottom: 2rem;">Lá»›n hÆ¡n lÃ  tháº¯ng - ÄÆ¡n giáº£n & Ká»‹ch tÃ­nh</p>

        <div class="glass-card">
            <div class="balance-pill">ðŸ’° Sá»‘ Gtlm: <span id="balance-display"
                    style="color: var(--secondary);"><?= number_format($money, 0, ',', '.') ?></span> gtlm</div>

            <div class="card-area">
                <div class="card-container">
                    <div class="card-label">Dealer</div>
                    <div id="dealer-card" class="playing-card">
                        <div class="card-value">?</div>
                        <div class="card-suit">ðŸ‚ </div>
                    </div>
                </div>
                <div class="card-container">
                    <div class="card-label">Báº¡n</div>
                    <div id="player-card" class="playing-card">
                        <div class="card-value">?</div>
                        <div class="card-suit">ðŸ‚ </div>
                    </div>
                </div>
            </div>

            <div id="status" class="status-msg"></div>

            <div class="controls">
                <div id="betting-controls" class="bet-input-wrapper">
                    <input type="number" id="bet-amount" value="1000" min="100" step="100">
                    <button id="deal-btn" class="btn">Chia bÃ i</button>
                </div>

                <div id="tie-controls" style="display: none;">
                    <p style="margin-bottom: 15px; font-weight: 600;">HÃ’A! Báº¡n muá»‘n lÃ m gÃ¬?</p>
                    <button id="war-btn" class="btn btn-war">THAM CHIáº¾N (WAR)</button>
                    <button id="surrender-btn" class="btn btn-secondary">Äáº¦U HÃ€NG (Lose 1/2)</button>
                </div>

                <div id="result-controls" style="display: none;">
                    <button id="reset-btn" class="btn">VÃ¡n má»›i</button>
                </div>
            </div>
        </div>

        <div class="glass-card" style="margin-top: 3rem;">
            <h2 style="margin-bottom: 2rem; font-size: 1.5rem; text-transform: uppercase; letter-spacing: 2px;">Lá»‹ch sá»­
                vÃ¡n Ä‘áº¥u</h2>
            <div class="history-container">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Thá»i gian</th>
                            <th>gtlm cÆ°á»£c</th>
                            <th>Káº¿t quáº£</th>
                            <th>Tháº¯ng/Thua</th>
                        </tr>
                    </thead>
                    <tbody id="history-body">
                        <!-- History via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>

        <a href="../index.php" class="btn btn-secondary"
            style="margin-top: 2rem; display: inline-block; text-decoration: none; width: auto;">Quay láº¡i gtlm sáº£nh</a>
    </div>


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
