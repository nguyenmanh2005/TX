<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require '../db_connect.php';
require_once '../load_theme.php';

$userId = $_SESSION['Iduser'];
$sql = "SELECT Money, Name FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$soDu = $user['Money'];
$tenNguoiChoi = $user['Name'];

// --- ROULETTE PRO DATA ---
$redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
$blackNumbers = [2, 4, 6, 8, 10, 11, 13, 15, 17, 20, 22, 24, 26, 28, 29, 31, 33, 35];

function getNumberColor($n)
{
    global $redNumbers;
    if ($n === 0)
        return "green";
    return in_array($n, $redNumbers) ? "red" : "black";
}

// --- AJAX HANDLER ---
if (isset($_GET['action']) && $_GET['action'] === 'spin_pro') {
    header('Content-Type: application/json');
    $bets = json_decode($_POST['bets'] ?? '[]', true);

    if (empty($bets)) {
        echo json_encode(['success' => false, 'message' => '⚠️ Vui lòng đặt cược trước khi quay!']);
        exit;
    }

    $totalBet = 0;
    foreach ($bets as $b) {
        $totalBet += (int) $b['amount'];
    }

    if ($totalBet > $soDu || $totalBet <= 0) {
        echo json_encode(['success' => false, 'message' => '⚠️ Số Gtlm không đủ cho tổng cược!']);
        exit;
    }

    $winningNumber = rand(0, 36);
    $color = getNumberColor($winningNumber);

    $totalWin = 0;
    $breakdown = [];

    foreach ($bets as $b) {
        $type = $b['type'];
        $val = $b['value'];
        $amt = (int) $b['amount'];
        $win = 0;

        switch ($type) {
            case 'straight':
                if ($winningNumber == $val)
                    $win = $amt * 36;
                break;
            case 'red':
                if ($color === 'red')
                    $win = $amt * 2;
                break;
            case 'black':
                if ($color === 'black')
                    $win = $amt * 2;
                break;
            case 'even':
                if ($winningNumber != 0 && $winningNumber % 2 == 0)
                    $win = $amt * 2;
                break;
            case 'odd':
                if ($winningNumber != 0 && $winningNumber % 2 != 0)
                    $win = $amt * 2;
                break;
            case 'low':
                if ($winningNumber >= 1 && $winningNumber <= 18)
                    $win = $amt * 2;
                break;
            case 'high':
                if ($winningNumber >= 19 && $winningNumber <= 36)
                    $win = $amt * 2;
                break;
            case 'dozen':
                if ($val == 1 && $winningNumber >= 1 && $winningNumber <= 12)
                    $win = $amt * 3;
                if ($val == 2 && $winningNumber >= 13 && $winningNumber <= 24)
                    $win = $amt * 3;
                if ($val == 3 && $winningNumber >= 25 && $winningNumber <= 36)
                    $win = $amt * 3;
                break;
            case 'column':
                if ($winningNumber != 0 && ($winningNumber - $val) % 3 == 0)
                    $win = $amt * 3;
                break;
        }

        if ($win > 0) {
            $totalWin += $win;
            $breakdown[] = "Cược $type: Thắng " . number_format($win) . " gtlm";
        }
    }

    $newMoney = $soDu - $totalBet + $totalWin;
    $conn->query("UPDATE users SET Money = $newMoney WHERE Iduser = $userId");

    if (file_exists('../game_history_helper.php')) {
        require_once '../game_history_helper.php';
        logGameHistoryWithAll($conn, $userId, 'Roulette Pro', $totalBet, $totalWin, ($totalWin > 0));
    }

    echo json_encode([
        'success' => true,
        'number' => $winningNumber,
        'color' => $color,
        'totalWin' => $totalWin,
        'totalBet' => $totalBet,
        'newMoney' => number_format($newMoney) . ' gtlm',
        'breakdown' => $breakdown,
        'message' => ($totalWin > 0) ? "🎉 CHIẾN THẮNG: TRÚNG SỐ $winningNumber! (" . strtoupper($color) . ")" : "💀 KẾT QUẢ: SỐ $winningNumber ($color). CHÚC BẠN MAY MẮN LẦN SAU!"
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Roulette Royal - Premium Casino</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/canvas-confetti/1.6.0/confetti.browser.min.js"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Poppins:wght@400;600;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --gold: #ffd700;
            --gold-dark: #b8860b;
            --bg: #072a1a;
            --border: rgba(255, 215, 0, 0.3);
        }

        body {
            margin: 0;
            cursor: url('../img/chuot.png'), auto !important;
            font-family: 'Poppins', sans-serif;
            background:
                <?= $bgGradientCSS ?>
            ;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            color: white;
            overflow-x: hidden;
            padding-bottom: 50px;
        }

        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        /* Header */
        .casino-header {
            width: 100%;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            margin-bottom: 30px;
            box-sizing: border-box;
        }

        .logo-text {
            font-family: 'Cinzel', serif;
            font-size: 28px;
            color: var(--gold);
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
            letter-spacing: 5px;
        }

        .balance-pill {
            background: rgba(0, 0, 0, 0.6);
            padding: 10px 25px;
            border-radius: 30px;
            border: 1px solid var(--gold);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            color: var(--gold);
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.2);
        }

        /* Main Game Area */
        .game-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 50px;
            width: 100%;
            max-width: 1200px;
            padding: 0 20px;
            box-sizing: border-box;
        }

        /* Wheel Section */
        .wheel-container {
            position: relative;
            width: 400px;
            height: 400px;
        }

        .wheel-outer-frame {
            position: absolute;
            inset: -20px;
            border-radius: 50%;
            border: 15px solid #3d2b1f;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 1), inset 0 0 30px rgba(0, 0, 0, 0.8);
            background: radial-gradient(circle, #4d3b2f, #1a0f0a);
        }

        .roulette-wheel {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: #2c3e50;
            border: 10px solid #222;
            position: relative;
            overflow: hidden;
        }

        .wheel-inner {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            transition: transform 6s cubic-bezier(0.1, 0, 0.1, 1);
            background: conic-gradient(#27ae60 0deg 9.73deg, #e74c3c 9.73deg 19.46deg, #2c3e50 19.46deg 29.19deg, #e74c3c 29.19deg 38.92deg,
                    #2c3e50 38.92deg 48.65deg, #e74c3c 48.65deg 58.38deg, #2c3e50 58.38deg 68.11deg, #e74c3c 68.11deg 77.84deg,
                    #2c3e50 77.84deg 87.57deg, #e74c3c 87.57deg 97.3deg, #27ae60 97.3deg 106.03deg, #e74c3c 106.03deg 115.76deg,
                    #2c3e50 115.76deg 125.49deg, #e74c3c 125.49deg 135.22deg, #2c3e50 135.22deg 144.95deg, #e74c3c 144.95deg 154.68deg,
                    #2c3e50 154.68deg 164.41deg, #e74c3c 164.41deg 174.14deg, #2c3e50 174.14deg 183.87deg, #e74c3c 183.87deg 193.6deg,
                    #2c3e50 193.6deg 203.33deg, #e74c3c 203.33deg 213.06deg, #2c3e50 213.06deg 222.79deg, #e74c3c 222.79deg 232.52deg,
                    #2c3e50 232.52deg 242.25deg, #e74c3c 242.25deg 251.98deg, #2c3e50 251.98deg 261.71deg, #e74c3c 261.71deg 271.44deg,
                    #2c3e50 271.44deg 281.17deg, #e74c3c 281.17deg 290.9deg, #2c3e50 290.9deg 300.63deg, #e74c3c 300.63deg 310.36deg,
                    #2c3e50 310.36deg 320.09deg, #e74c3c 320.09deg 329.82deg, #2c3e50 329.82deg 339.55deg, #e74c3c 339.55deg 349.28deg, #2c3e50 349.28deg 360deg);
        }

        .pointer {
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 20px solid transparent;
            border-right: 20px solid transparent;
            border-top: 40px solid var(--gold);
            z-index: 10;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.8));
        }

        .result-orb {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100px;
            height: 100px;
            background: radial-gradient(circle at 30% 30%, #444, #111);
            border: 2px solid var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            font-weight: 800;
            color: var(--gold);
            z-index: 15;
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.8);
        }

        /* Betting Board - Clean Edition */
        .board-glass {
            background: rgba(10, 60, 40, 0.85);
            backdrop-filter: blur(20px);
            border: 4px solid #4a3728;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.7);
            width: 100%;
            border-style: double;
        }

        .grid-master {
            display: grid;
            grid-template-columns: 80px repeat(12, 1fr) 100px;
            grid-template-rows: repeat(3, 60px) 50px 50px;
            gap: 4px;
        }

        .cell {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
            color: rgba(255, 255, 255, 0.8);
            position: relative;
            border-radius: 4px;
        }

        .cell:hover {
            transform: scale(1.05);
            z-index: 2;
            border-color: var(--gold);
            background: rgba(255, 215, 0, 0.1);
        }

        .cell.red {
            background: #e74c3c;
            color: white;
            border-color: #c0392b;
        }

        .cell.black {
            background: #2c3e50;
            color: white;
            border-color: #1a252f;
        }

        .cell.green {
            background: #27ae60;
            color: white;
            grid-row: 1 / 4;
            grid-column: 1;
            border-color: #1e8449;
            font-size: 30px;
        }

        .cell.active {
            background: radial-gradient(circle, #f1c40f, #f39c12) !important;
            color: #333 !important;
            border-color: white;
            box-shadow: 0 0 15px #f1c40f;
            animation: pulse 1s infinite alternate;
        }

        .cell.active::after {
            content: '🪙';
            position: absolute;
            font-size: 24px;
            top: -10px;
            right: -10px;
        }

        @keyframes pulse {
            from {
                transform: scale(1.02);
            }

            to {
                transform: scale(1.08);
            }
        }

        .dozen-cell {
            grid-row: 4;
            grid-column: span 4;
            font-size: 16px;
            background: rgba(0, 0, 0, 0.4);
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .outside-cell {
            grid-row: 5;
            grid-column: span 2;
            font-size: 14px;
            background: rgba(0, 0, 0, 0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .col-cell {
            grid-column: 14;
            background: rgba(0, 0, 0, 0.5);
            font-size: 14px;
        }

        /* Control Bar */
        .casino-controls {
            margin-top: 40px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            background: rgba(0, 0, 0, 0.7);
            padding: 25px 50px;
            border-radius: 60px;
            border: 2px solid var(--border);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            width: fit-content;
        }

        .bet-input-box {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .bet-input-box label {
            font-size: 14px;
            font-weight: 800;
            color: #aaa;
            text-transform: uppercase;
        }

        .money-input {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid var(--border);
            border-radius: 25px;
            padding: 12px 25px;
            color: var(--gold);
            font-size: 20px;
            font-weight: 800;
            width: 150px;
            outline: none;
            transition: 0.3s;
            text-align: center;
        }

        .money-input:focus {
            border-color: var(--gold);
            background: rgba(255, 255, 255, 0.1);
        }

        .btn-casino {
            padding: 15px 45px;
            border-radius: 35px;
            border: none;
            font-size: 18px;
            font-weight: 900;
            letter-spacing: 2px;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.3);
        }

        .btn-gold {
            background: linear-gradient(135deg, #f1c40f 0%, #d35400 100%);
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .btn-gold:hover {
            transform: translateY(-3px) scale(1.05);
            filter: brightness(1.1);
            box-shadow: 0 12px 20px rgba(241, 196, 15, 0.3);
        }

        .btn-gold:disabled {
            filter: grayscale(1);
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .btn-danger {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid #e74c3c;
            color: #e74c3c;
        }

        .btn-danger:hover {
            background: #e74c3c;
            color: white;
        }

        .status-marquee {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.9);
            border: 1px solid var(--gold);
            color: var(--gold);
            padding: 8px 30px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 1px;
            min-width: 400px;
            text-align: center;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.1);
        }
    </style>
</head>

<body>


    <header class="casino-header">
        <div class="logo-text">ROULETTE ROYAL</div>
        <div class="balance-pill" id="balance-pill">
            <span>GOLD CHIPS:</span>
            <span id="balance-val" style="font-size: 22px;"><?= number_format($soDu) ?></span>
        </div>
        <div style="font-size: 14px; color: #888;">PLAYER: <b><?= htmlspecialchars($tenNguoiChoi) ?></b></div>
    </header>

    <div class="game-wrapper">
        <!-- Visualization: Wheel -->
        <div class="wheel-container">
            <div class="wheel-outer-frame"></div>
            <div class="pointer"></div>
            <div class="roulette-wheel">
                <div class="wheel-inner" id="wheel-inner"></div>
            </div>
            <div class="result-orb" id="result-orb">?</div>
        </div>

        <!-- Betting: Board -->
        <div class="board-glass">
            <div class="grid-master">
                <!-- Zero -->
                <div class="cell green" data-type="straight" data-val="0">0</div>

                <!-- Number Logic -->
                <?php
                $nums = [
                    [3, 6, 9, 12, 15, 18, 21, 24, 27, 30, 33, 36],
                    [2, 5, 8, 11, 14, 17, 20, 23, 26, 29, 32, 35],
                    [1, 4, 7, 10, 13, 16, 19, 22, 25, 28, 31, 34]
                ];
                foreach ($nums as $rowIdx => $row):
                    foreach ($row as $n):
                        $color = in_array($n, $redNumbers) ? 'red' : 'black';
                        echo "<div class='cell $color' data-type='straight' data-val='$n'>$n</div>";
                    endforeach;
                    echo "<div class='cell col-cell' data-type='column' data-val='" . (3 - $rowIdx) . "'>2 TO 1</div>";
                endforeach;
                ?>

                <!-- Outside -->
                <div class="cell dozen-cell" data-type="dozen" data-val="1">1st 12</div>
                <div class="cell dozen-cell" data-type="dozen" data-val="2">2nd 12</div>
                <div class="cell dozen-cell" data-type="dozen" data-val="3">3rd 12</div>
                <div></div> <!-- Spacer -->

                <div class="cell outside-cell" data-type="low" data-val="low">1-18</div>
                <div class="cell outside-cell" data-type="even" data-val="even">EVEN</div>
                <div class="cell outside-cell red" data-type="red" data-val="red">RED</div>
                <div class="cell outside-cell black" data-type="black" data-val="black">BLACK</div>
                <div class="cell outside-cell" data-type="odd" data-val="odd">ODD</div>
                <div class="cell outside-cell" data-type="high" data-val="high">19-36</div>
            </div>
        </div>

        <!-- Interactive: Controls -->
        <div class="casino-controls">
            <div class="bet-input-box">
                <label>Bet Value:</label>
                <input type="number" id="bet-amount" class="money-input" value="10000" step="5000">
            </div>
            <button class="btn-casino btn-danger" id="btn-clear">CLEAR BETS</button>
            <button class="btn-casino btn-gold" id="btn-spin">PLACE BETS & SPIN</button>
            <a href="../index.php"
                style="color: #666; font-size: 15px; margin-left: 20px; text-decoration: none; font-weight: bold;">QUIT
                SESSION</a>
        </div>
    </div>

    <div class="status-marquee" id="status-marquee">WELCOME TO THE HIGH TABLE. PLACE YOUR BETS.</div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function () {
            window.themeConfig = { particleCount: <?= $particleCount ?>, particleSize: <?= $particleSize ?>, particleColor: '<?= $particleColor ?>', particleOpacity: <?= $particleOpacity ?>, shapeCount: <?= $shapeCount ?>, shapeColors: <?= json_encode($shapeColors) ?>, shapeOpacity: <?= $shapeOpacity ?>, bgGradient: <?= json_encode($bgGradient) ?> };
            const script = document.createElement('script'); script.src = '../threejs-background.js'; document.head.appendChild(script);
        })();

        let currentBets = [];
        let totalRotation = 0;

        document.querySelectorAll('.cell').forEach(cell => {
            cell.addEventListener('click', function () {
                const type = this.dataset.type;
                const val = this.dataset.val;
                const amt = parseInt(document.getElementById('bet-amount').value);

                if (amt <= 0 || isNaN(amt)) { Swal.fire('Error', 'Chưa nhập số gtlm cược!', 'error'); return; }

                this.classList.add('active');
                currentBets.push({ type, value: val, amount: amt });
                updateStatus();
            });
        });

        document.getElementById('btn-clear').addEventListener('click', () => {
            currentBets = [];
            document.querySelectorAll('.cell').forEach(c => c.classList.remove('active'));
            updateStatus();
        });

        function updateStatus() {
            const total = currentBets.reduce((sum, b) => sum + b.amount, 0);
            document.getElementById('status-marquee').textContent = currentBets.length > 0
                ? `ACTIVE BETS: ${currentBets.length} | TOTAL EXPOSURE: ${total.toLocaleString()} gtlm`
                : 'WAITING FOR BETS... CHOOSE NUMBERS OR AREAS ON THE BOARD.';
        }

        document.getElementById('btn-spin').addEventListener('click', async function () {
            if (currentBets.length === 0) { Swal.fire('Lưu ý', 'Bạn chưa đặt cược quân bài nào lên bàn!', 'warning'); return; }

            const btn = this;
            btn.disabled = true;
            document.getElementById('status-marquee').textContent = 'BALL IS SPINNING. NO MORE BETS!';

            try {
                const fd = new FormData();
                fd.append('bets', JSON.stringify(currentBets));

                const res = await fetch('roulette.php?action=spin_pro', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.success) {
                    const wheel = document.getElementById('wheel-inner');
                    const wheelOrder = [0, 32, 15, 19, 4, 21, 2, 25, 17, 34, 6, 27, 13, 36, 11, 30, 8, 23, 10, 5, 24, 16, 33, 1, 20, 14, 31, 9, 22, 18, 29, 7, 28, 12, 35, 3, 26];
                    const idx = wheelOrder.indexOf(data.number);

                    // Logic xoay mượt mà chân thực
                    totalRotation += (10 * 360) + (idx * (360 / 37));
                    wheel.style.transform = `rotate(-${totalRotation}deg)`;

                    setTimeout(() => {
                        const resOrb = document.getElementById('result-orb');
                        resOrb.textContent = data.number;
                        resOrb.style.color = 'white';
                        resOrb.style.backgroundColor = (data.color === 'red' ? '#e74c3c' : (data.color === 'black' ? '#2c3e50' : '#27ae60'));

                        document.getElementById('balance-val').textContent = data.newMoney;
                        document.getElementById('status-marquee').textContent = data.message;

                        btn.disabled = false;
                        if (data.totalWin > 0) {
                            confetti({ particleCount: 250, spread: 100, origin: { y: 0.6 }, colors: ['#ffd700', '#ffffff', '#27ae60'] });
                            Swal.fire({ title: '🎊 BIG WIN!', html: `<strong style="color: #27ae60; font-size: 24px;">+ ${data.totalWin.toLocaleString()} gtlm</strong><br><br><div style="text-align: left; font-size: 14px;">${data.breakdown.join('<br>')}</div>`, icon: 'success' });
                        } else {
                            Swal.fire({ title: 'Không trúng', text: data.message, icon: 'error' });
                        }

                        // Clear board
                        currentBets = [];
                        document.querySelectorAll('.cell').forEach(c => c.classList.remove('active'));
                    }, 6500);
                } else {
                    Swal.fire('Error', data.message, 'error');
                    btn.disabled = false;
                }
            } catch (e) {
                console.error(e);
                btn.disabled = false;
            }
        });
    </script>











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