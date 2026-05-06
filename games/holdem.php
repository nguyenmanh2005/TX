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
$conn->query("CREATE TABLE IF NOT EXISTS history_holdem (
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
            // Standard deck
            $suits = ['s', 'c', 'h', 'd'];
            $deck = [];
            foreach (['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'] as $v) {
                foreach ($suits as $s)
                    $deck[] = $v . $s;
            }
            shuffle($deck);
            $playerHand = array_slice($deck, 0, 2);
            $dealerHand = array_slice($deck, 2, 2);
            $community = array_slice($deck, 4, 3); // Flop

            $_SESSION['holdem_bet'] = $bet;
            $_SESSION['holdem_player'] = $playerHand;
            $_SESSION['holdem_dealer'] = $dealerHand;
            $_SESSION['holdem_community'] = $community;
            $_SESSION['holdem_deck'] = array_slice($deck, 7);

            $response = ['success' => true, 'player' => $playerHand, 'community' => $community, 'money' => number_format($money - $bet, 0, ',', '.')];
        }
    } elseif ($action === 'call') {
        $bet = $_SESSION['holdem_bet'];
        $player = $_SESSION['holdem_player'];
        $dealer = $_SESSION['holdem_dealer'];
        $comm = $_SESSION['holdem_community'];
        $deck = $_SESSION['holdem_deck'];

        $callBet = $bet * 2;
        $conn->query("UPDATE users SET Money = Money - $callBet WHERE Iduser = $userId");

        // Add Turn and River
        $comm[] = $deck[0];
        $comm[] = $deck[1];

        // Simplified Logic: Player wins 42% of the time for house edge (Reduced from 55%)
        $win = rand(1, 100) <= 42;
        $winAmount = $win ? ($bet * 2 + $callBet * 2) : 0;

        if ($winAmount > 0) {
            $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $userId");
        }

        $totalBet = $bet + $callBet;
        $profit = $winAmount - $totalBet;
        $resMsg = $win ? "Bạn THẮNG với kết quả tốt hơn Dealer!" : "Dealer thắng! Bạn đã thua.";

        $his = $conn->prepare("INSERT INTO history_holdem (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $his->bind_param("idss", $userId, $totalBet, $resMsg, $profit);
        $his->execute();

        $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
        $response = [
            'success' => true,
            'dealer' => $dealer,
            'full_comm' => $comm,
            'win' => $win,
            'message' => $resMsg,
            'winAmount' => number_format($winAmount, 0, ',', '.'),
            'money' => number_format($newMoney, 0, ',', '.')
        ];
        unset($_SESSION['holdem_bet']);
    } elseif ($action === 'fold') {
        $bet = $_SESSION['holdem_bet'];
        $his = $conn->prepare("INSERT INTO history_holdem (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $negBet = -$bet;
        $resStr = "Folded";
        $his->bind_param("idss", $userId, $bet, $resStr, $negBet);
        $his->execute();
        unset($_SESSION['holdem_bet']);
        $response = ['success' => true];
    }
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Casino Hold'em - Premium classics</title>
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
        }

        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        .glass {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 2rem;
        }

        .card {
            width: 80px;
            height: 110px;
            background: #fff;
            border-radius: 8px;
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 1.2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .card.back {
            background: linear-gradient(135deg, #f093fb, #f5576c);
            color: transparent;
        }

        .hand {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 1.5rem;
            min-height: 120px;
        }

        .community {
            display: flex;
            gap: 15px;
            justify-content: center;
            background: rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            border-radius: 20px;
            margin: 2rem 0;
            min-height: 130px;
        }

        .controls {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 1rem;
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
            background: #00d2ff;
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
    <div class="container" style="max-width:900px; margin:1.5rem auto; position:relative; z-index:1;">
        <div class="glass"
            style="padding: 1.5rem 3rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h1 style="margin:0; font-size: 2rem; font-weight: 900; color: #f093fb;">CASINO HOLD'EM</h1>
            <div style="display:flex; align-items:center; gap:2rem;">
                <div id="userMoney" style="font-weight:900; font-size:1.5rem; color:#f1c40f">
                    <?php echo number_format($money, 0, ',', '.'); ?> gtlm</div>
                <a href="../index.php"
                    style="color:#fff; text-decoration:none; border:1px solid rgba(255,255,255,0.2); padding:0.5rem 1.5rem; border-radius:50px;">THOÁT</a>
            </div>
        </div>

        <div class="glass" style="padding: 2.5rem; text-align: center;">
            <div id="dealerHand" class="hand">
                <div class="card back"></div>
                <div class="card back"></div>
            </div>

            <div id="commonCards" class="community">
                <div class="card back"></div>
                <div class="card back"></div>
                <div class="card back"></div>
                <div class="card back"></div>
                <div class="card back"></div>
            </div>

            <div id="playerHand" class="hand">
                <div class="card back"></div>
                <div class="card back"></div>
            </div>

            <div class="controls">
                <input type="number" id="betAmount" value="10000" class="glass"
                    style="color:#fff; padding:10px 20px; outline:none; font-size:1.2rem; font-weight:900; text-align:center; width:140px; border-radius:50px;">
                <button class="btn-premium btn-deal" id="dealBtn" onclick="deal()">DEAL ANTE</button>
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
