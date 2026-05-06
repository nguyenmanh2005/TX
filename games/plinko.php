<?php
session_start();
include '../db_connect.php';
require_once '../include_css.php';
include '../load_theme.php';
require_once '../game_history_helper.php';

if (!isset($_SESSION['Iduser'])) {
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['Iduser'];
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$userName = $user['Name'];
$stmt->close();

$conn->query("CREATE TABLE IF NOT EXISTS history_plinko (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Iduser INT NOT NULL,
    Bet DECIMAL(30,2) NOT NULL,
    Result VARCHAR(255) NOT NULL,
    WinAmount DECIMAL(30,2) NOT NULL,
    Time DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$multipliers = [5.0, 2.0, 1.2, 0.5, 0.2, 0.5, 1.2, 2.0, 5.0];

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false];

    if ($action === 'drop') {
        $totalBet = (float) ($_POST['bet'] ?? 0);
        $ballCount = (int) ($_POST['ballCount'] ?? 1);

        if ($totalBet <= 0 || $totalBet > $money) {
            $response['message'] = "Số Gtlm không đủ!";
        } elseif ($ballCount < 1 || $ballCount > 50) {
            $response['message'] = "Số lượng bóng từ 1-50!";
        } else {
            $betPerBall = $totalBet / $ballCount;
            $conn->query("UPDATE users SET Money = Money - $totalBet WHERE Iduser = $userId");

            $results = [];
            $totalWin = 0;

            for ($b = 0; $b < $ballCount; $b++) {
                $path = [];
                $slot = 0;
                for ($i = 0; $i < 8; $i++) {
                    $dir = rand(0, 1);
                    $path[] = $dir;
                    if ($dir === 1)
                        $slot++;
                }
                $mult = $multipliers[$slot];
                $win = round($betPerBall * $mult);
                $totalWin += $win;

                $results[] = [
                    'path' => $path,
                    'slot' => $slot,
                    'multiplier' => $mult,
                    'winAmount' => $win
                ];
            }

            if ($totalWin > 0) {
                $conn->query("UPDATE users SET Money = Money + $totalWin WHERE Iduser = $userId");
            }

            $profit = $totalWin - $totalBet;
            $resStr = "$ballCount Balls | xAvg " . round($totalWin / $totalBet, 2);
            $his = $conn->prepare("INSERT INTO history_plinko (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
            $his->bind_param("idss", $userId, $totalBet, $resStr, $profit);
            $his->execute();

            logGameHistoryWithAll($conn, $userId, 'Plinko', $totalBet, $totalWin, $totalWin > 0);

            $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
            $response = [
                'success' => true,
                'results' => $results,
                'totalWin' => number_format($totalWin, 0, ',', '.'),
                'money' => number_format($newMoney, 0, ',', '.'),
                'betPerBall' => $betPerBall
            ];
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
    <title>Plinko Royale Premium - Vegas</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;500;700&display=swap"
        rel="stylesheet">
    <?php echo getCSSIncludes(['special_effects' => true]); ?>
    <style>
        :root {
            --primary: #12c2e9;
            --accent: #f1c40f;
            --glass: rgba(255, 255, 255, 0.08);
        }

        body {
            margin: 0;
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Inter', sans-serif;
            overflow: hidden;
            cursor: url('../img/chuot.png'), auto !important;
        }

        .main-container {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 2.5rem;
            padding: 2.5rem;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.6);
            width: 98%;
            max-width: 1300px;
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 3rem;
            min-height: 88vh;
            align-self: center;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.8rem;
        }

        .plinko-area {
            position: relative;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 2.5rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 60px;
        }

        .board-container {
            position: relative;
            width: 600px;
            height: 500px;
            margin-top: 20px;
        }

        .pin {
            position: absolute;
            width: 10px;
            height: 10px;
            background: radial-gradient(circle at 35% 35%, #fff, #aaa);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
        }

        .pin.hit {
            background: #fff;
            box-shadow: 0 0 20px #fff, 0 0 40px var(--primary);
            transform: translate(-50%, -50%) scale(1.5);
            transition: 0.1s;
        }

        .pockets-container {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            justify-content: center;
            padding-bottom: 20px;
        }

        .pocket {
            width: 58px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Orbitron';
            font-weight: 900;
            font-size: 0.8rem;
            transition: 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .pocket.v-low {
            background: linear-gradient(to bottom, #ff4e50, #f9d423);
            color: #000;
        }

        .pocket.v-mid {
            background: linear-gradient(to bottom, #12c2e9, #c471ed);
            color: #fff;
        }

        .pocket.v-high {
            background: linear-gradient(to bottom, #f1c40f, #e67e22);
            color: #000;
        }

        .pocket.hit {
            transform: scale(1.3);
            box-shadow: 0 0 40px currentColor;
            z-index: 10;
        }

        .ball {
            position: absolute;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #fff, var(--primary));
            box-shadow: 0 0 15px var(--primary);
            pointer-events: none;
            z-index: 100;
        }

        .input-group {
            background: rgba(0, 0, 0, 0.4);
            padding: 1rem 1.4rem;
            border-radius: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .input-group label {
            display: block;
            font-size: 0.65rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 8px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .input-group input {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.3rem;
            font-weight: 900;
            width: 100%;
            outline: none;
            font-family: 'Orbitron';
        }

        .btn-action {
            padding: 1.4rem;
            border-radius: 1.8rem;
            border: none;
            font-weight: 900;
            font-size: 1.5rem;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary), #c471ed);
            color: #fff;
            box-shadow: 0 15px 35px rgba(18, 194, 233, 0.4);
        }

        .btn-action:hover:not(:disabled) {
            transform: translateY(-4px);
            filter: brightness(1.1);
            box-shadow: 0 20px 45px rgba(18, 194, 233, 0.5);
        }

        .btn-action:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .quick-select {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-top: 10px;
        }

        .quick-btn {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            border-radius: 10px;
            padding: 8px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: 0.2s;
            font-weight: 800;
        }

        .quick-btn:hover {
            background: rgba(18, 194, 233, 0.25);
            border-color: var(--primary);
        }

        #totalWinDisplay {
            font-family: 'Orbitron';
            color: var(--accent);
            font-size: 2.2rem;
            font-weight: 900;
            text-shadow: 0 0 25px rgba(241, 196, 15, 0.5);
            line-height: 1;
            margin-top: 5px;
        }
    </style>
</head>

<body>
    <div class="main-container">
        <div class="glass-card">
            <div class="sidebar">
                <div style="margin-bottom: 0.5rem;">
                    <h1
                        style="margin:0; font-size: 2.8rem; font-weight: 900; color: var(--primary); font-family: 'Orbitron'; letter-spacing: 2px;">
                        PLINKO</h1>
                    <p style="margin:0; opacity:0.4; font-size: 0.85rem; letter-spacing: 1px;">Vegas Royale Premium</p>
                </div>

                <div class="input-group">
                    <label>Tổng Gtlm cược</label>
                    <input type="number" id="totalBet" value="10000" min="1000" step="5000">
                    <div class="quick-select">
                        <button class="quick-btn" onclick="adjBet(0.5)">1/2</button>
                        <button class="quick-btn" onclick="adjBet(2)">x2</button>
                        <button class="quick-btn" onclick="adjBet('max')">MAX</button>
                        <button class="quick-btn" onclick="$('#totalBet').val(10000)">10K</button>
                    </div>
                </div>

                <div class="input-group">
                    <label>Số lượng bóng</label>
                    <input type="number" id="ballCount" value="5" min="1" max="50">
                    <div class="quick-select">
                        <button class="quick-btn" onclick="$('#ballCount').val(1)">1</button>
                        <button class="quick-btn" onclick="$('#ballCount').val(10)">10</button>
                        <button class="quick-btn" onclick="$('#ballCount').val(20)">20</button>
                        <button class="quick-btn" onclick="$('#ballCount').val(50)">50</button>
                    </div>
                </div>

                <div
                    style="background:rgba(0,0,0,0.25); padding:1.2rem; border-radius:1.5rem; border:1px dashed rgba(255,255,255,0.12);">
                    <span
                        style="opacity:0.5; font-size:0.7rem; font-weight:700; letter-spacing:1px; display:block; margin-bottom:5px;">Gtlm
                        CƯỢC MỖI BÓNG</span>
                    <div id="betPerBall"
                        style="font-size:1.4rem; font-weight:900; color:rgba(255,255,255,0.9); font-family: 'Orbitron';">
                        0</div>
                </div>

                <button id="dropBtn" class="btn-action" onclick="dropBalls()">🎱 THẢ BÓNG</button>

                <div style="margin-top:auto; padding-top:1.5rem; border-top:1px solid rgba(255,255,255,0.1);">
                    <div
                        style="background:rgba(0,0,0,0.2); padding: 1rem; border-radius: 1.2rem; border: 1px solid rgba(255,255,255,0.05);">
                        <span
                            style="opacity:0.5; font-size:0.75rem; font-weight:700; display:block; margin-bottom:5px;">SỐ
                            DƯ HIỆN TẠI</span>
                        <div style="display:flex; align-items: baseline; gap: 8px; flex-wrap: wrap;">
                            <span id="userMoney"
                                style="font-weight:900; font-size:1.5rem; font-family: 'Orbitron'; color:var(--accent); word-break: break-all;"><?php echo number_format($money, 0, ',', '.'); ?></span>
                            <span style="font-size: 0.8rem; font-weight: 900; opacity: 0.5;">GTLM</span>
                        </div>
                    </div>
                    <div style="text-align: center; margin-top: 1.2rem;">
                        <a href="../index.php"
                            style="color: #fff; text-decoration: none; font-size: 0.85rem; opacity: 0.3; transition: 0.3s;"
                            onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.3">← Quay về
                            Dashboard</a>
                    </div>
                </div>
            </div>

            <div class="plinko-area">
                <div style="text-align:center; z-index: 5;">
                    <span
                        style="opacity:0.6; font-size:0.75rem; font-weight:800; letter-spacing:3px; text-transform:uppercase;">TỔNG
                        THẮNG</span>
                    <div id="totalWinDisplay">0</div>
                </div>

                <div class="board-container" id="board">
                    <!-- Pins will be generated here -->
                </div>

                <div class="pockets-container" id="pockets">
                    <!-- Pockets will be generated here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        const rows = 8;
        const multipliers = <?= json_encode($multipliers) ?>;
        const pins = [];
        const board = document.getElementById('board');
        const pockets = document.getElementById('pockets');

        // Generate Pins
        for (let r = 0; r < rows; r++) {
            const count = r + 3;
            const y = 40 + r * 55;
            for (let i = 0; i < count; i++) {
                const x = 300 + (i - (count - 1) / 2) * 55;
                const pin = document.createElement('div');
                pin.className = 'pin';
                pin.style.left = x + 'px';
                pin.style.top = y + 'px';
                board.appendChild(pin);
                pins.push({ el: pin, x, y });
            }
        }

        // Generate Pockets
        multipliers.forEach((m, i) => {
            const pkt = document.createElement('div');
            let cls = 'v-low';
            if (m >= 2) cls = 'v-high';
            else if (m >= 1) cls = 'v-mid';

            pkt.className = 'pocket ' + cls;
            pkt.id = 'pocket-' + i;
            pkt.innerHTML = 'x' + m;
            pockets.appendChild(pkt);
        });

        function updateBetInfo() {
            const total = parseFloat($('#totalBet').val()) || 0;
            const count = parseInt($('#ballCount').val()) || 1;
            $('#betPerBall').text(Math.floor(total / count).toLocaleString('vi-VN'));
        }
        $('#totalBet, #ballCount').on('input', updateBetInfo);
        updateBetInfo();

        function adjBet(ratio) {
            const cur = parseFloat($('#totalBet').val()) || 0;
            const money = parseFloat($('#userMoney').text().replace(/\./g, ''));
            if (ratio === 'max') $('#totalBet').val(money);
            else $('#totalBet').val(Math.floor(cur * ratio));
            updateBetInfo();
        }

        let activeBalls = 0;
        let sessionWin = 0;

        function dropBalls() {
            if (activeBalls > 0) return;
            const bet = $('#totalBet').val();
            const ballCount = $('#ballCount').val();

            $('#dropBtn').prop('disabled', true);
            sessionWin = 0;
            $('#totalWinDisplay').text('0');

            $.post('plinko.php?action=drop', { bet, ballCount }, function (res) {
                if (res.success) {
                    $('#userMoney').text(res.money);
                    activeBalls = res.results.length;

                    res.results.forEach((data, index) => {
                        setTimeout(() => {
                            animateSingleBall(data);
                        }, index * 200); // Staggered drop
                    });
                } else {
                    Swal.fire('Lỗi', res.message, 'error');
                    $('#dropBtn').prop('disabled', false);
                }
            });
        }

        function animateSingleBall(data) {
            const ball = document.createElement('div');
            ball.className = 'ball';
            board.appendChild(ball);

            let curX = 300, curY = -20;
            gsap.set(ball, { x: curX, y: curY });

            const tl = gsap.timeline({
                onComplete: () => {
                    // Hit pocket
                    const pkt = document.getElementById('pocket-' + data.slot);
                    pkt.classList.add('hit');
                    setTimeout(() => pkt.classList.remove('hit'), 500);

                    sessionWin += data.winAmount;
                    $('#totalWinDisplay').text(sessionWin.toLocaleString('vi-VN'));

                    if (window.GameEffects) {
                        if (data.multiplier >= 2) window.GameEffects.showWin(data.winAmount);
                        window.GameEffects.plinkoTrail(ball.getBoundingClientRect().left, ball.getBoundingClientRect().top, '#fff');
                    }

                    gsap.to(ball, {
                        opacity: 0, scale: 0, duration: 0.3, onComplete: () => {
                            ball.remove();
                            activeBalls--;
                            if (activeBalls === 0) {
                                $('#dropBtn').prop('disabled', false);
                                if (sessionWin > 0 && window.GameEffects) {
                                    // show final total if big
                                    const totalBet = parseFloat($('#totalBet').val());
                                    if (sessionWin > totalBet * 2) window.GameEffects.showBigWin(sessionWin);
                                }
                            }
                        }
                    });
                }
            });

            data.path.forEach((dir, r) => {
                const nextY = 40 + r * 55;
                curX += dir === 1 ? 27.5 : -27.5;

                tl.to(ball, {
                    x: curX,
                    y: nextY - 10,
                    duration: 0.3,
                    ease: "back.out(1.7)",
                    onStart: () => {
                        // Flash nearest pin
                        pins.forEach(p => {
                            if (Math.abs(p.x - curX) < 10 && Math.abs(p.y - nextY) < 10) {
                                p.el.classList.add('hit');
                                setTimeout(() => p.el.classList.remove('hit'), 150);
                            }
                        });
                    }
                });
            });

            const finalX = 300 + (data.slot - (multipliers.length - 1) / 2) * 55;
            tl.to(ball, { x: finalX, y: 480, duration: 0.4, ease: "power2.in" });
        }
    </script>

    <canvas id="threejs-background"></canvas>
    <script>
        (function () {
            window.themeConfig = {
                particleCount: 600,
                particleSize: 0.05,
                particleColor: '#12c2e9',
                particleOpacity: 0.5,
                shapeCount: 8,
                shapeColors: ["#12c2e9", "#c471ed", "#f64f59"],
                shapeOpacity: 0.2,
                bgGradient: ["#0f0c29", "#302b63", "#24243e"]
            };
            const prefix = '../';
            ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'].forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src; s.async = false;
                document.head.appendChild(s);
            });
        })();
    </script>
</body>

</html>