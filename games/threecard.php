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
$conn->query("CREATE TABLE IF NOT EXISTS history_threecard (
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
            echo json_encode(['success' => false, 'message' => 'Cược không hợp lệ!']);
            exit;
        }

        $playerHand = [getCard(), getCard(), getCard()];
        $dealerHand = [getCard(), getCard(), getCard()];

        $_SESSION['3cp_ante'] = $ante;
        $_SESSION['3cp_pairPlus'] = $pairPlus;
        $_SESSION['3cp_playerHand'] = $playerHand;
        $_SESSION['3cp_dealerHand'] = $dealerHand;

        echo json_encode([
            'success' => true,
            'playerHand' => $playerHand,
            'playerEval' => evaluateHand($playerHand)['rank']
        ]);
        exit;
    } elseif ($action === 'play') {
        $playerHand = $_SESSION['3cp_playerHand'];
        $dealerHand = $_SESSION['3cp_dealerHand'];
        $ante = $_SESSION['3cp_ante'];
        $pairPlus = $_SESSION['3cp_pairPlus'];
        $play = $ante; // Play bet = Ante bet

        $pEval = evaluateHand($playerHand);
        $dEval = evaluateHand($dealerHand);

        $winAmount = -($ante + $pairPlus + $play);
        $msg = "";

        // Pair Plus Payout (independent)
        $ppWin = 0;
        if ($pairPlus > 0) {
            if ($pEval['rank'] == 5)
                $ppWin = $pairPlus * 41; // SF 40:1
            elseif ($pEval['rank'] == 4)
                $ppWin = $pairPlus * 31; // 3K 30:1
            elseif ($pEval['rank'] == 3)
                $ppWin = $pairPlus * 7; // S 6:1
            elseif ($pEval['rank'] == 2)
                $ppWin = $pairPlus * 5; // F 4:1
            elseif ($pEval['rank'] == 1)
                $ppWin = $pairPlus * 2; // P 1:1
        }
        $winAmount += $ppWin;

        // Dealer Qualifies? (Needs Queen high or better)
        $dQualifies = ($dEval['rank'] > 0 || $dEval['score'] >= 12); // Q rank is 12

        if (!$dQualifies) {
            $winAmount += ($ante * 2) + $play; // Ante wins 1:1, Play pushes
            $msg = "Dealer không đủ điều kiện (Qualify). Ante thắng, Play hòa.";
        } else {
            if ($pEval['score'] > $dEval['score']) {
                $winAmount += ($ante * 2) + ($play * 2);
                $msg = "Bạn thắng Dealer!";
            } elseif ($pEval['score'] < $dEval['score']) {
                $msg = "Dealer thắng bạn.";
            } else {
                $winAmount += $ante + $play;
                $msg = "Hòa (Push).";
            }
        }

        $newMoney = $money + $winAmount;
        $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $stmt->bind_param("di", $newMoney, $userId);
        $stmt->execute();
        $stmt->close();

        // History
        $hisStr = "P: " . RANK_NAMES[$pEval['rank']] . " vs D: " . RANK_NAMES[$dEval['rank']];
        $his = $conn->prepare("INSERT INTO history_threecard (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $totalBet = $ante + $pairPlus + $play;
        $his->bind_param("idss", $userId, $totalBet, $hisStr, $winAmount);
        $his->execute();
        $his->close();

        echo json_encode([
            'success' => true,
            'dealerHand' => $dealerHand,
            'dealerEval' => RANK_NAMES[$dEval['rank']],
            'playerEval' => RANK_NAMES[$pEval['rank']],
            'winAmount' => $winAmount,
            'message' => $msg,
            'money' => number_format($newMoney, 0, ',', '.'),
            'rawMoney' => $newMoney
        ]);
        exit;
    } elseif ($action === 'fold') {
        $ante = $_SESSION['3cp_ante'];
        $pairPlus = $_SESSION['3cp_pairPlus'];
        $winAmount = -($ante + $pairPlus);
        $newMoney = $money + $winAmount;

        $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $stmt->bind_param("di", $newMoney, $userId);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'winAmount' => $winAmount, 'money' => number_format($newMoney, 0, ',', '.'), 'rawMoney' => $newMoney]);
        exit;
    } elseif ($action === 'get_history') {
        $stmt = $conn->prepare("SELECT Bet, Result, WinAmount, Time FROM history_threecard WHERE Iduser = ? ORDER BY Time DESC LIMIT 10");
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
    <title>Three Card Poker - Đỉnh Cao Trí Tuệ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <style>
        :root {
            --primary: #3282b8;
            --secondary: #4ecca3;
            --danger: #be3144;
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
            text-shadow: 0 0 20px rgba(50, 130, 184, 0.3);
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
            background: rgba(78, 204, 163, 0.1);
            border: 1px solid var(--secondary);
            padding: 0.8rem 2rem;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 2rem;
            color: var(--secondary);
            font-weight: 700;
        }

        .hand-section {
            margin-bottom: 2.5rem;
        }

        .label {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .card-row {
            display: flex;
            justify-content: center;
            gap: 1rem;
            min-height: 150px;
            perspective: 1000px;
            flex-wrap: wrap;
        }

        .card {
            width: clamp(80px, 12vw, 100px);
            aspect-ratio: 2/3;
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

        .bet-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .bet-input-box {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
            padding: 1.2rem;
            border-radius: 1.5rem;
        }

        .bet-input-box span {
            display: block;
            font-size: 0.8rem;
            opacity: 0.6;
            margin-bottom: 0.5rem;
        }

        .bet-input-box input {
            width: 100%;
            background: transparent;
            border: none;
            color: #fff;
            text-align: center;
            font-size: 1.4rem;
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
            margin: 0.5rem;
            font-size: 1rem;
        }

        .btn-ante {
            background: linear-gradient(135deg, var(--primary) 0%, #0f4c75 100%);
            color: #fff;
            width: 100%;
            max-width: 300px;
        }

        .btn-play {
            background: linear-gradient(135deg, var(--secondary) 0%, #218c74 100%);
            color: #fff;
        }

        .btn-fold {
            background: linear-gradient(135deg, var(--danger) 0%, #822727 100%);
            color: #fff;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
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

            .btn {
                width: 100%;
                margin: 0.5rem 0;
            }
        }
    </style>
</head>

<body>


    <div class="main-container">
        <h1 class="game-title">THREE CARD POKER</h1>
        <div class="balance-pill">💰 Số Gtlm: <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span>
            gtlm
        </div>

        <div class="glass-card">
            <div id="dealer-area" class="hand-section">
                <div class="label">Dealer</div>
                <div id="dealer-hand" class="card-row">
                    <div class="card">🃟</div>
                    <div class="card">🃟</div>
                    <div class="card">🃟</div>
                </div>
            </div>

            <div id="player-area" class="hand-section">
                <div class="label">Bạn</div>
                <div id="player-hand" class="card-row">
                    <div class="card">🃟</div>
                    <div class="card">🃟</div>
                    <div class="card">🃟</div>
                </div>
                <div id="player-rank"
                    style="font-weight: 900; margin-top: 1rem; color: var(--secondary); font-size: 1.2rem;"></div>
            </div>

            <div id="bet-area">
                <div class="bet-grid">
                    <div class="bet-input-box">
                        <span>ANTE (Bắt buộc)</span>
                        <input type="number" id="ante" value="1000" min="100" step="100">
                    </div>
                    <div class="bet-input-box">
                        <span>PAIR PLUS (Tùy chọn)</span>
                        <input type="number" id="pairplus" value="0" min="0" step="100">
                    </div>
                </div>
                <button id="deal-btn" class="btn btn-ante">Chia Bài</button>
            </div>

            <div id="play-area" style="display: none; margin-top: 2rem;">
                <p style="margin-bottom: 1.5rem; font-weight: 700;">Bạn muốn Theo hay Úp bài?</p>
                <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                    <button id="play-btn" class="btn btn-play">PLAY (Theo)</button>
                    <button id="fold-btn" class="btn btn-fold">FOLD (Úp bài)</button>
                </div>
            </div>

            <div id="result-area" style="display: none; margin-top: 2rem;">
                <h2 id="result-msg" style="margin-bottom: 1.5rem;"></h2>
                <button id="reset-btn" class="btn btn-ante">Ván Mới</button>
            </div>
        </div>

        <div class="history-section">
            <h2 style="font-size: 1.2rem; letter-spacing: 2px;">LỊCH SỬ ĐẶT CƯỢC</h2>
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