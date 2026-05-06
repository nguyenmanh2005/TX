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
$userName = $user['Name'];
$stmt->close();

// history table
$conn->query("CREATE TABLE IF NOT EXISTS history_caribbean (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Iduser INT NOT NULL,
    Bet DECIMAL(30,2) NOT NULL,
    Result VARCHAR(255) NOT NULL,
    WinAmount DECIMAL(30,2) NOT NULL,
    Time DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false];

    if ($action === 'deal') {
        $bet = (float) ($_POST['bet'] ?? 0);
        if ($bet <= 0 || $bet > $money) {
            $response['message'] = "gtlm cược không hợp lệ!";
        } else {
            $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");

            // Simulating 5 cards each
            $suits = ['s', 'c', 'h', 'd'];
            $deck = [];
            foreach (['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'] as $v) {
                foreach ($suits as $s)
                    $deck[] = $v . $s;
            }
            shuffle($deck);
            $playerHand = array_slice($deck, 0, 5);
            $dealerHand = array_slice($deck, 5, 5);

            $_SESSION['caribbean_bet'] = $bet;
            $_SESSION['caribbean_player'] = $playerHand;
            $_SESSION['caribbean_dealer'] = $dealerHand;

            $response = ['success' => true, 'player' => $playerHand, 'dealer_up' => $dealerHand[0], 'money' => number_format($money - $bet, 0, ',', '.')];
        }
    } elseif ($action === 'fold') {
        $bet = $_SESSION['caribbean_bet'];
        $his = $conn->prepare("INSERT INTO history_caribbean (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $negBet = -$bet;
        $resStr = "Folded";
        $his->bind_param("idss", $userId, $bet, $resStr, $negBet);
        $his->execute();
        unset($_SESSION['caribbean_bet']);
        $response = ['success' => true];
    } elseif ($action === 'call') {
        $bet = $_SESSION['caribbean_bet'];
        $playerHand = $_SESSION['caribbean_player'];
        $dealerHand = $_SESSION['caribbean_dealer'];

        // Deduct call bet (2x ante)
        $callBet = $bet * 2;
        $conn->query("UPDATE users SET Money = Money - $callBet WHERE Iduser = $userId");

        // Logic: Compare hands
        // Simplified: Random winner for demo purposes, with 1/3 chance for dealer NOT qualifying
        $dealerQualifies = rand(1, 4) > 1; // 75% chance
        $playerWins = rand(1, 100) <= 44; // Reduced from 50% to 44% for house edge

        $winAmount = 0;
        $resMsg = "";

        if (!$dealerQualifies) {
            $winAmount = $bet * 2; // Ante pays 1:1, Call is a push
            $resMsg = "Dealer không đủ điều kiện (No AK high). Ante thắng 1:1!";
        } else {
            if ($playerWins) {
                $winAmount = ($bet * 2) + ($callBet * 2); // Simplified 1:1 payout
                $resMsg = "Bạn THẮNG Dealer!";
            } else {
                $winAmount = 0;
                $resMsg = "Dealer thắng! Bạn đã thua.";
            }
        }

        if ($winAmount > 0) {
            $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $userId");
        }

        $totalBet = $bet + $callBet;
        $profit = $winAmount - $totalBet;
        $his = $conn->prepare("INSERT INTO history_caribbean (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $his->bind_param("idss", $userId, $totalBet, $resMsg, $profit);
        $his->execute();

        $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
        $response = [
            'success' => true,
            'dealer' => $dealerHand,
            'win' => ($winAmount > 0),
            'message' => $resMsg,
            'winAmount' => number_format($winAmount, 0, ',', '.'),
            'money' => number_format($newMoney, 0, ',', '.')
        ];
        unset($_SESSION['caribbean_bet']);
    }
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Caribbean Stud - Casino Classics</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            width: 100px;
            height: 140px;
            background: #fff;
            border-radius: 10px;
            color: #000;
            position: relative;
            transition: 0.5s;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        .card.back {
            background: linear-gradient(135deg, #00d2ff, #3a7bd5);
            color: transparent;
        }

        .hand {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 2rem 0;
            height: 160px;
        }

        .controls {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 2rem;
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

        .btn-deal {
            background: #f1c40f;
            color: #000;
        }

        .btn-call {
            background: #2ecc71;
            color: #fff;
        }

        .btn-fold {
            background: #e74c3c;
            color: #fff;
        }
    </style>
</head>

<body>
    <div id="threejs-background"></div>
    <div class="container" style="max-width:1000px; margin:2rem auto; position:relative; z-index:1;">
        <div class="glass"
            style="padding: 1.5rem 3rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1 style="margin:0; font-size: 2.2rem; font-weight: 900; color: #00d2ff;">CARIBBEAN STUD</h1>
            <div style="display:flex; align-items:center; gap:2rem;">
                <div id="userMoney" style="font-weight:900; font-size:1.5rem; color:#f1c40f">
                    <?php echo number_format($money, 0, ',', '.'); ?> gtlm</div>
                <a href="../index.php"
                    style="color:#fff; text-decoration:none; border:1px solid rgba(255,255,255,0.2); padding:0.5rem 1.5rem; border-radius:50px;">THOÁT</a>
            </div>
        </div>

        <div class="glass" style="padding: 3rem; text-align: center;">
            <div id="dealerHand" class="hand">
                <!-- Dealer Cards -->
                <div class="card back"></div>
                <div class="card back"></div>
                <div class="card back"></div>
                <div class="card back"></div>
                <div class="card back"></div>
            </div>
            <div style="margin: 2rem 0; opacity: 0.5;">VS</div>
            <div id="playerHand" class="hand">
                <!-- Player Cards -->
                <div class="card back"></div>
                <div class="card back"></div>
                <div class="card back"></div>
                <div class="card back"></div>
                <div class="card back"></div>
            </div>

            <div class="controls">
                <input type="number" id="betAmount" value="10000" class="glass"
                    style="color:#fff; padding:10px 20px; outline:none; font-size:1.2rem; font-weight:900; text-align:center; width:150px; border-radius:50px;">
                <button class="btn-premium btn-deal" id="dealBtn" onclick="deal()">DEAL</button>
                <button class="btn-premium btn-call" id="callBtn" style="display:none" onclick="call()">CALL (2x
                    Ante)</button>
                <button class="btn-premium btn-fold" id="foldBtn" style="display:none" onclick="fold()">FOLD</button>
            </div>
        </div>
    </div>

    
    <?php require_once '../casino_help.php'; ?>


    
    


    


    


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
    </script>

</body>

</html>
