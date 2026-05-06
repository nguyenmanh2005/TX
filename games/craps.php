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
$conn->query("CREATE TABLE IF NOT EXISTS history_craps (
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

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'roll') {
        $bet = (int) ($_POST['bet'] ?? 0);
        $phase = $_SESSION['craps_phase'] ?? 'comeout';
        $point = $_SESSION['craps_point'] ?? 0;

        if ($phase === 'comeout') {
            if ($bet <= 0 || $bet > $money) {
                echo json_encode(['success' => false, 'message' => 'Cược không hợp lệ!']);
                exit;
            }
            $_SESSION['craps_bet'] = $bet;
        } else {
            $bet = $_SESSION['craps_bet'];
        }

        $d1 = rand(1, 6);
        $d2 = rand(1, 6);
        $sum = $d1 + $d2;

        $winAmount = 0;
        $status = "";
        $gameOver = false;

        if ($phase === 'comeout') {
            if ($sum == 7 || $sum == 11) {
                $winAmount = $bet;
                $status = "Natural! Bạn thắng!";
                $gameOver = true;
            } elseif ($sum == 2 || $sum == 3 || $sum == 12) {
                $winAmount = -$bet;
                $status = "Craps! Bạn thua.";
                $gameOver = true;
            } else {
                $_SESSION['craps_point'] = $sum;
                $_SESSION['craps_phase'] = 'point';
                $status = "Point established: $sum. Tiếp tục lắc!";
                $gameOver = false;
            }
        } else {
            if ($sum == $point) {
                $winAmount = $bet;
                $status = "Hit the Point! Bạn thắng!";
                $gameOver = true;
            } elseif ($sum == 7) {
                $winAmount = -$bet;
                $status = "Seven Out! Bạn thua.";
                $gameOver = true;
            } else {
                $status = "Kết quả: $sum. Lắc tiếp để trúng $point hoặc tránh 7!";
                $gameOver = false;
            }
        }

        if ($gameOver) {
            $newMoney = $money + $winAmount;
            $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
            $stmt->bind_param("di", $newMoney, $userId);
            $stmt->execute();
            $stmt->close();

            // History
            $his = $conn->prepare("INSERT INTO history_craps (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
            $resStr = "Result: $sum (Phase: $phase)";
            $his->bind_param("idss", $userId, $bet, $resStr, $winAmount);
            $his->execute();
            $his->close();

            unset($_SESSION['craps_phase'], $_SESSION['craps_point'], $_SESSION['craps_bet']);
            $money = $newMoney;
        }

        echo json_encode([
            'success' => true,
            'dice' => [$d1, $d2],
            'sum' => $sum,
            'status' => $status,
            'gameOver' => $gameOver,
            'winAmount' => $winAmount,
            'point' => $_SESSION['craps_point'] ?? 0,
            'money' => number_format($money, 0, ',', '.'),
            'rawMoney' => $money
        ]);
        exit;
    } elseif ($action === 'get_history') {
        $stmt = $conn->prepare("SELECT Bet, Result, WinAmount, Time FROM history_craps WHERE Iduser = ? ORDER BY Time DESC LIMIT 10");
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <meta charset="UTF-8">
    <title>Craps - Đấu Trường Xúc Xắc</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #fbbf24;
            --secondary: #6ee7b7;
            --danger: #ef4444;
            --accent: #2563eb;
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
            font-size: clamp(2.5rem, 8vw, 4.5rem);
            font-weight: 900;
            color: var(--primary);
            text-shadow: 0 0 30px rgba(251, 191, 36, 0.4);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 15px;
        }

        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 3rem;
            padding: clamp(1.5rem, 5vw, 3.5rem);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
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

        .die-row {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 2.5rem;
        }

        .die {
            width: clamp(80px, 15vw, 110px);
            aspect-ratio: 1;
            background: #fff;
            border-radius: 1.2rem;
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(3rem, 6vw, 4.5rem);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
            transition: all 0.1s;
        }

        .point-marker {
            background: #fff;
            color: #000;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            position: absolute;
            top: 2rem;
            right: 2rem;
            border: 5px solid #000;
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        .status-msg {
            font-size: clamp(1.2rem, 3vw, 1.8rem);
            font-weight: 800;
            color: var(--primary);
            margin: 2rem 0;
            min-height: 4rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
        }

        .bet-input-container {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
            padding: 1.5rem;
            border-radius: 2rem;
            margin: 0 auto 2.5rem;
            max-width: 300px;
        }

        .bet-input-container span {
            display: block;
            font-size: 0.8rem;
            opacity: 0.6;
            margin-bottom: 0.8rem;
            letter-spacing: 2px;
        }

        .bet-input-container input {
            width: 100%;
            background: transparent;
            border: none;
            color: #fff;
            text-align: center;
            font-size: 2rem;
            font-weight: 900;
            outline: none;
        }

        .btn-roll {
            background: linear-gradient(135deg, var(--primary) 0%, #d97706 100%);
            border: none;
            padding: 1.2rem 5rem;
            border-radius: 50px;
            color: #000;
            font-size: 1.5rem;
            font-weight: 900;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 4px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 10px 30px rgba(251, 191, 36, 0.4);
        }

        .btn-roll:hover:not(:disabled) {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(251, 191, 36, 0.6);
        }

        .btn-roll:disabled {
            opacity: 0.5;
            filter: grayscale(1);
            cursor: not-allowed;
        }

        .history-section {
            background: var(--glass);
            border-radius: 2.5rem;
            padding: 2.5rem;
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
            padding: 1.2rem;
            border-bottom: 2px solid var(--glass-border);
        }

        .history-table td {
            padding: 1.2rem;
            border-bottom: 1px solid var(--glass-border);
            font-weight: 600;
        }

        @media (max-width: 600px) {
            .point-marker {
                width: 55px;
                height: 55px;
                font-size: 0.8rem;
                top: 1rem;
                right: 1rem;
            }

            .btn-roll {
                padding: 1rem 2rem;
                font-size: 1.2rem;
            }
        }
    </style>
</head>

<body>


    <div class="main-container">
        <h1 class="game-title">CRAPS</h1>
        <div class="balance-pill">💰 Số Gtlm: <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span>
            gtlm
        </div>

        <div class="glass-card">
            <div id="point-tag" class="point-marker" style="display: none;">
                <span style="font-size: 0.6rem; opacity: 0.6;">POINT</span>
                <span id="point-val" style="font-size: 1.5rem;">OFF</span>
            </div>

            <div class="die-row">
                <div class="die">🎲</div>
                <div class="die">🎲</div>
            </div>

            <div id="status-msg" class="status-msg">Sẵn sàng đặt cược?</div>

            <div id="bet-area">
                <div class="bet-input-container">
                    <span>ĐẶT CƯỢC PASS LINE</span>
                    <input type="number" id="bet-amt" value="1000" min="100" step="100">
                </div>
            </div>

            <button id="roll-btn" class="btn-roll">LẮC XÚC XẮC</button>
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