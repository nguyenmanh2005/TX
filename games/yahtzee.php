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

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false];

    if ($action === 'roll') {
        $bet = (float) ($_POST['bet'] ?? 0);
        $keep = $_POST['keep'] ?? [];
        $rollCount = (int) ($_POST['rollCount'] ?? 0);

        if ($rollCount == 1) {
            if ($bet <= 0 || $bet > $money) {
                $response['message'] = "gtlm cÆ°á»£c khÃ´ng Ä‘á»§ hoáº·c khÃ´ng há»£p lá»‡!";
                echo json_encode($response);
                exit;
            }
            // Initial roll
            $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");
            $_SESSION['yahtzee_bet'] = $bet;
            $_SESSION['yahtzee_dice'] = [0, 0, 0, 0, 0];
        }

        $dice = $_SESSION['yahtzee_dice'] ?? [rand(1, 6), rand(1, 6), rand(1, 6), rand(1, 6), rand(1, 6)];
        for ($i = 0; $i < 5; $i++) {
            if (!in_array($i, $keep)) {
                $dice[$i] = rand(1, 6);
            }
        }
        $_SESSION['yahtzee_dice'] = $dice;

        $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];

        $response = [
            'success' => true,
            'dice' => $dice,
            'money' => number_format($newMoney, 0, ',', '.')
        ];
    } elseif ($action === 'submit') {
        $category = $_POST['category'];
        $dice = $_SESSION['yahtzee_dice'] ?? null;
        $bet = $_SESSION['yahtzee_bet'] ?? 0;

        if (!$dice || $bet <= 0) {
            $response['message'] = "PhiÃªn chÆ¡i Ä‘Ã£ káº¿t thÃºc hoáº·c khÃ´ng há»£p lá»‡!";
        } else {
            $counts = array_count_values($dice);
            $winMult = 0;

            switch ($category) {
                case 'ones':
                    $winMult = ($counts[1] ?? 0) * 0.5;
                    break;
                case 'twos':
                    $winMult = ($counts[2] ?? 0) * 1.0;
                    break;
                case 'threes':
                    $winMult = ($counts[3] ?? 0) * 1.5;
                    break;
                case 'fours':
                    $winMult = ($counts[4] ?? 0) * 2.0;
                    break;
                case 'fives':
                    $winMult = ($counts[5] ?? 0) * 2.5;
                    break;
                case 'sixes':
                    $winMult = ($counts[6] ?? 0) * 3.0;
                    break;
                case 'threeofakind':
                    if (max($counts) >= 3)
                        $winMult = 5;
                    break;
                case 'fourofakind':
                    if (max($counts) >= 4)
                        $winMult = 10;
                    break;
                case 'fullhouse':
                    $cv = array_values($counts);
                    sort($cv);
                    if ($cv === [2, 3] || $cv === [5])
                        $winMult = 15;
                    break;
                case 'yahtzee':
                    if (max($counts) === 5)
                        $winMult = 50;
                    break;
            }

            $winAmount = round($bet * $winMult);
            $profit = $winAmount - $bet;

            $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $userId");
            $resStr = "Dice: " . implode(',', $dice) . " | Category: $category";
            $his = $conn->prepare("INSERT INTO history_yahtzee (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
            $his->bind_param("idss", $userId, $bet, $resStr, $profit);
            $his->execute();

            unset($_SESSION['yahtzee_bet']);
            unset($_SESSION['yahtzee_dice']);

            $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
            $response = ['success' => true, 'winAmount' => number_format($winAmount, 0, ',', '.'), 'money' => number_format($newMoney, 0, ',', '.')];
        }
    }
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Yahtzee Royale - Cao Cáº¥p</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-color: #ff4757;
            --accent-color: #ffa502;
            --glass: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        body {
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            color: #fff;
            min-height: 100vh;
            font-family: 'Exo 2', system-ui, sans-serif;
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
            max-width: 1100px;
            margin: 2rem auto;
        }

        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 2.5rem;
            padding: 2.5rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            margin-bottom: 2rem;
        }

        .dice-area {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 3rem;
            flex-wrap: wrap;
        }

        .die {
            width: 100px;
            height: 100px;
            background: #fff;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #000;
            font-weight: 900;
            cursor: pointer;
            transition: 0.3s;
            position: relative;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        .die.held {
            background: var(--primary-color);
            color: #fff;
            transform: translateY(-10px);
            box-shadow: 0 0 20px var(--primary-color);
        }

        .die.held::after {
            content: "GIá»®";
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.8rem;
            font-weight: 900;
            color: var(--primary-color);
        }

        .score-card {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .score-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.2rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 1rem;
            border: 1px solid transparent;
            cursor: pointer;
            transition: 0.3s;
        }

        .score-row:hover {
            background: rgba(255, 71, 87, 0.1);
            border-color: var(--primary-color);
        }

        .score-label {
            font-weight: 700;
        }

        .score-mult {
            color: var(--accent-color);
            font-weight: 900;
        }

        .btn-roll {
            background: linear-gradient(135deg, var(--primary-color), #ff6b81);
            color: #fff;
            border: none;
            padding: 1.5rem;
            border-radius: 1.5rem;
            font-size: 1.5rem;
            font-weight: 900;
            cursor: pointer;
            width: 100%;
            transition: 0.3s;
            margin-top: 10px;
        }

        .btn-roll:hover:not(:disabled) {
            transform: scale(1.02);
            filter: brightness(1.1);
        }

        .btn-roll:disabled {
            opacity: 0.5;
        }

        button,
        a,
        input,
        select,
        .btn-help-game,
        .help-close-x,
        .die,
        .score-row {
            cursor: url('../img/tay.png'), pointer !important;
        }
    </style>
</head>

<body>
    <div id="threejs-background"></div>
    <div class="main-container">
        <div class="glass-card"
            style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem 3rem;">
            <div>
                <h1 style="margin:0; font-size: 2.5rem; font-weight: 900; color: var(--primary-color);">YAHTZEE</h1>
                <p style="margin:0; opacity:0.5">XÃºc xáº¯c Royale - Premium</p>
            </div>
            <div style="display:flex; align-items:center; gap:2rem;">
                <div id="userMoney" style="font-weight:900; font-size:1.8rem; color:var(--accent-color)">
                    <?php echo number_format($money, 0, ',', '.'); ?> gtlm</div>
                <a href="../index.php"
                    style="color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.2); padding: 0.5rem 1.5rem; border-radius: 50px; font-weight: 900;">THOÃT</a>
            </div>
        </div>

        <div class="glass-card">
            <div class="dice-area" id="diceArea">
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <div class="die" data-index="<?php echo $i; ?>" onclick="toggleHold(this)">?</div>
                <?php endfor; ?>
            </div>

            <div style="max-width: 600px; margin: 0 auto;">
                <div
                    style="display:flex; justify-content: space-between; margin-bottom: 20px; font-weight: 900; font-size: 1.2rem;">
                    <span>CÆ¯á»¢C: <input type="number" id="betAmount" value="10000"
                            style="background:none; border:none; border-bottom:2px solid var(--primary-color); color:#fff; width:100px; text-align:center; font-weight:900; outline:none;">
                        gtlm</span>
                    <span>Láº¦N Láº®C: <span id="rollCount" style="color:var(--primary-color)">0</span>/3</span>
                </div>

                <button class="btn-roll" id="rollBtn" onclick="rollDice()">Láº®C XÃšC Xáº®C</button>

                <h3 style="margin: 3rem 0 1.5rem; text-align:center; text-transform:uppercase; letter-spacing:2px;">Báº£ng
                    Äiá»ƒm & Tá»• Há»£p</h3>
                <div class="score-card" id="scoreCard">
                    <div class="score-row" onclick="submitScore('ones')"><span class="score-label">Bá»™ 1</span><span
                            class="score-mult">x0.5</span></div>
                    <div class="score-row" onclick="submitScore('twos')"><span class="score-label">Bá»™ 2</span><span
                            class="score-mult">x1.0</span></div>
                    <div class="score-row" onclick="submitScore('threes')"><span class="score-label">Bá»™ 3</span><span
                            class="score-mult">x1.5</span></div>
                    <div class="score-row" onclick="submitScore('fours')"><span class="score-label">Bá»™ 4</span><span
                            class="score-mult">x2.0</span></div>
                    <div class="score-row" onclick="submitScore('fives')"><span class="score-label">Bá»™ 5</span><span
                            class="score-mult">x2.5</span></div>
                    <div class="score-row" onclick="submitScore('sixes')"><span class="score-label">Bá»™ 6</span><span
                            class="score-mult">x3.0</span></div>
                    <div class="score-row" onclick="submitScore('threeofakind')"><span class="score-label">Bá»™
                            Ba</span><span class="score-mult">x5.0</span></div>
                    <div class="score-row" onclick="submitScore('fourofakind')"><span class="score-label">Tá»©
                            QuÃ½</span><span class="score-mult">x10.0</span></div>
                    <div class="score-row" onclick="submitScore('fullhouse')"><span class="score-label">CÃ¹
                            LÅ©</span><span class="score-mult">x15.0</span></div>
                    <div class="score-row" onclick="submitScore('yahtzee')"><span class="score-label"
                            style="color:var(--accent-color)">YAHTZEE</span><span class="score-mult">x50.0</span></div>
                </div>
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
