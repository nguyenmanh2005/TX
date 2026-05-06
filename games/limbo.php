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

$conn->query("CREATE TABLE IF NOT EXISTS history_limbo (Id INT AUTO_INCREMENT PRIMARY KEY, Iduser INT NOT NULL, Bet DECIMAL(30,2) NOT NULL, Result VARCHAR(255) NOT NULL, WinAmount DECIMAL(30,2) NOT NULL, Time DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false];
    if ($action === 'roll') {
        $bet = (float) ($_POST['bet'] ?? 0);
        $target = (float) ($_POST['target'] ?? 2.0);
        if ($bet <= 0 || $bet > $money || $target < 1.01 || $target > 1000000) {
            $response['message'] = "Tham số không hợp lệ!";
        } else {
            $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");
            // Provably fair logic
            $houseEdge = 1.0;
            $result = (100 / (rand(1, 100000000) / 1000000)) * (1 - $houseEdge / 100);
            $result = max(1.00, round($result, 2));
            $win = ($result >= $target);
            $winAmount = $win ? round($bet * $target) : 0;
            if ($winAmount > 0)
                $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $userId");
            $profit = $winAmount - $bet;
            $resStr = "Target: x$target | Result: x$result";
            $his = $conn->prepare("INSERT INTO history_limbo (Iduser,Bet,Result,WinAmount,Time) VALUES (?,?,?,?,NOW())");
            $his->bind_param("idss", $userId, $bet, $resStr, $profit);
            $his->execute();
            logGameHistoryWithAll($conn, $userId, 'Limbo', $bet, $winAmount, $win);
            $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
            $response = [
                'success' => true,
                'result' => $result,
                'win' => $win,
                'winAmount' => number_format($winAmount, 0, ',', '.'),
                'money' => number_format($newMoney, 0, ',', '.'),
                'winChance' => round((1 / $target) * 99, 2) . '%'
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
    <title>Limbo High-Voltage - Vegas Royale</title>
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
            --primary: #00d2ff;
            --accent: #f1c40f;
            --success: #2ecc71;
            --danger: #ff4757;
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
            border-radius: 2rem;
            padding: 1.5rem;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.6);
            width: 95%;
            max-width: 1100px;
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 1.5rem;
            max-height: 92vh;
            align-self: center;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        .display-area {
            position: relative;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            border-radius: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .input-group {
            background: rgba(0, 0, 0, 0.4);
            padding: 0.8rem 1.2rem;
            border-radius: 1.2rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .input-group label {
            display: block;
            font-size: 0.6rem;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 5px;
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

        .btn-roll {
            padding: 1.2rem;
            border-radius: 1.5rem;
            border: none;
            font-weight: 900;
            font-size: 1.5rem;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary), #3a7bd5);
            color: #fff;
            box-shadow: 0 10px 30px rgba(0, 210, 255, 0.3);
        }

        .btn-roll:hover:not(:disabled) {
            transform: translateY(-3px);
            filter: brightness(1.1);
            box-shadow: 0 15px 40px rgba(0, 210, 255, 0.4);
        }

        .btn-roll:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Rocket Styling */
        .rocket-wrap {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 5;
            pointer-events: none;
        }

        .rocket {
            width: 40px;
            height: 70px;
            position: relative;
            filter: drop-shadow(0 0 15px var(--primary));
        }

        .rocket-body {
            width: 24px;
            height: 50px;
            background: linear-gradient(180deg, #fff, #ccc);
            border-radius: 50% 50% 20% 20%;
            margin: 0 auto;
            position: relative;
        }

        .rocket-tip {
            width: 0;
            height: 0;
            border-left: 12px solid transparent;
            border-right: 12px solid transparent;
            border-bottom: 15px solid var(--danger);
            position: absolute;
            top: -12px;
            left: 0;
        }

        .rocket-window {
            width: 8px;
            height: 8px;
            background: #00d2ff;
            border-radius: 50%;
            position: absolute;
            top: 12px;
            left: 50%;
            transform: translateX(-50%);
            box-shadow: 0 0 10px #00d2ff;
        }

        .rocket-fin {
            position: absolute;
            bottom: 0;
            width: 0;
            height: 0;
        }

        .rocket-fin.l {
            border-right: 10px solid var(--danger);
            border-top: 15px solid transparent;
            left: -10px;
        }

        .rocket-fin.r {
            border-left: 10px solid var(--danger);
            border-top: 15px solid transparent;
            right: -10px;
        }

        .flames {
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            display: none;
        }

        .flame-main {
            width: 14px;
            height: 30px;
            background: linear-gradient(to top, #ff4e50, #f9d423);
            border-radius: 50% 50% 20% 20%;
            animation: flicker 0.1s infinite alternate;
        }

        @keyframes flicker {
            0% {
                transform: scale(1);
                opacity: 0.8;
            }

            100% {
                transform: scale(1.2) scaleX(0.8);
                opacity: 1;
            }
        }

        .multiplier-display {
            font-size: 6rem;
            font-weight: 900;
            font-family: 'Orbitron';
            color: #fff;
            text-shadow: 0 0 30px rgba(255, 255, 255, 0.2);
            transition: 0.1s;
            z-index: 10;
            position: relative;
            margin-bottom: 20px;
        }

        .multiplier-display.win {
            color: var(--success);
            text-shadow: 0 0 50px var(--success);
        }

        .multiplier-display.loss {
            color: var(--danger);
            text-shadow: 0 0 50px var(--danger);
        }

        .target-indicator {
            position: absolute;
            bottom: 40px;
            background: rgba(255, 255, 255, 0.05);
            padding: 10px 25px;
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .target-indicator span {
            font-size: 0.6rem;
            opacity: 0.5;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .target-indicator b {
            font-size: 1.2rem;
            font-family: 'Orbitron';
            color: var(--accent);
        }

        .stat-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .stat-item {
            background: rgba(0, 0, 0, 0.2);
            padding: 0.8rem;
            border-radius: 1rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.03);
        }

        .stat-item span {
            display: block;
            font-size: 0.55rem;
            opacity: 0.4;
            margin-bottom: 3px;
            font-weight: 700;
        }

        .stat-item b {
            font-size: 0.9rem;
            font-family: 'Orbitron';
            color: var(--accent);
        }

        /* Electric Sparks FX */
        .spark {
            position: absolute;
            pointer-events: none;
            background: #fff;
            border-radius: 50%;
            z-index: 5;
        }
    </style>
</head>

<body>
    <div class="main-container">
        <div class="glass-card">
            <div class="sidebar">
                <div style="margin-bottom: 0.5rem;">
                    <h1
                        style="margin:0; font-size: 2.5rem; font-weight: 900; color: var(--primary); font-family: 'Orbitron'; letter-spacing: 2px;">
                        LIMBO</h1>
                    <p style="margin:0; opacity:0.4; font-size: 0.8rem; letter-spacing: 1px;">High Voltage · Premium</p>
                </div>

                <div class="input-group">
                    <label>Gtlm cược (gtlm)</label>
                    <input type="number" id="betAmount" value="10000" min="1000" step="5000">
                    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:5px; margin-top:8px;">
                        <button onclick="adjBet(0.5)"
                            style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:8px; padding:5px; font-size:0.65rem; cursor:pointer;">1/2</button>
                        <button onclick="adjBet(2)"
                            style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:8px; padding:5px; font-size:0.65rem; cursor:pointer;">x2</button>
                        <button onclick="adjBet('max')"
                            style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:8px; padding:5px; font-size:0.65rem; cursor:pointer;">MAX</button>
                    </div>
                </div>

                <div class="input-group">
                    <label>Mục tiêu (Multiplier)</label>
                    <input type="number" id="targetMult" value="2.00" min="1.01" step="0.1">
                    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:5px; margin-top:8px;">
                        <button onclick="$('#targetMult').val(1.50); updateInfo();"
                            style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:6px; padding:4px; font-size:0.6rem; cursor:pointer;">1.5x</button>
                        <button onclick="$('#targetMult').val(2.00); updateInfo();"
                            style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:6px; padding:4px; font-size:0.6rem; cursor:pointer;">2x</button>
                        <button onclick="$('#targetMult').val(10.00); updateInfo();"
                            style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:6px; padding:4px; font-size:0.6rem; cursor:pointer;">10x</button>
                        <button onclick="$('#targetMult').val(100.00); updateInfo();"
                            style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:6px; padding:4px; font-size:0.6rem; cursor:pointer;">100x</button>
                    </div>
                </div>

                <div class="stat-grid">
                    <div class="stat-item">
                        <span>XÁC SUẤT</span>
                        <b id="chanceDisp">49.5%</b>
                    </div>
                    <div class="stat-item">
                        <span>THẮNG DỰ KIẾN</span>
                        <b id="potentialWin">20.000</b>
                    </div>
                </div>

                <button id="rollBtn" class="btn-roll" onclick="playLimbo()">⚡ PHÓNG TÊN LỬA</button>

                <div style="margin-top:auto; padding-top:1rem; border-top:1px solid rgba(255,255,255,0.1);">
                    <div
                        style="background:rgba(0,0,0,0.2); padding: 0.8rem; border-radius: 1.2rem; border: 1px solid rgba(255,255,255,0.05);">
                        <span
                            style="opacity:0.5; font-size:0.65rem; font-weight:700; display:block; margin-bottom:3px;">Số
                            Gtlm HIỆN TẠI</span>
                        <div style="display:flex; align-items: baseline; gap: 5px; flex-wrap: wrap;">
                            <span id="userMoney"
                                style="font-weight:900; font-size:1.2rem; font-family: 'Orbitron'; color:var(--accent); word-break: break-all;"><?php echo number_format($money, 0, ',', '.'); ?></span>
                            <span style="font-size: 0.65rem; font-weight: 900; opacity: 0.5;">GTLM</span>
                        </div>
                    </div>
                    <div style="text-align: center; margin-top: 0.8rem;">
                        <a href="../index.php"
                            style="color: #fff; text-decoration: none; font-size: 0.75rem; opacity: 0.3;">← Quay về
                            Dashboard</a>
                    </div>
                </div>
            </div>

            <div class="display-area">
                <div id="resultMult" class="multiplier-display">1.00x</div>

                <div class="rocket-wrap" id="rocketWrapper">
                    <div class="rocket">
                        <div class="rocket-tip"></div>
                        <div class="rocket-body">
                            <div class="rocket-window"></div>
                            <div class="rocket-fin l"></div>
                            <div class="rocket-fin r"></div>
                        </div>
                        <div class="flames" id="rocketFlames">
                            <div class="flame-main"></div>
                        </div>
                    </div>
                </div>

                <div class="target-indicator">
                    <span>Mục tiêu hiện tại</span>
                    <b id="targetDisp">2.00x</b>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateInfo() {
            const t = parseFloat($('#targetMult').val()) || 1.01;
            const b = parseFloat($('#betAmount').val()) || 0;
            const chance = (1 / t * 99).toFixed(2);
            $('#chanceDisp').text(chance + '%');
            $('#potentialWin').text(Math.floor(t * b).toLocaleString('vi-VN'));
            $('#targetDisp').text(t.toFixed(2) + 'x');
        }
        $('#targetMult, #betAmount').on('input', updateInfo);
        updateInfo();

        function adjBet(ratio) {
            const cur = parseFloat($('#betAmount').val()) || 0;
            const money = parseFloat($('#userMoney').text().replace(/\./g, ''));
            if (ratio === 'max') $('#betAmount').val(money);
            else $('#betAmount').val(Math.floor(cur * ratio));
            updateInfo();
        }

        let isRolling = false;

        function playLimbo() {
            if (isRolling) return;
            const bet = $('#betAmount').val();
            const target = $('#targetMult').val();
            isRolling = true;

            $('#rollBtn').prop('disabled', true).text('ĐANG PHÓNG...');
            $('#resultMult').removeClass('win loss').text('1.00x').css('opacity', '1');
            $('#rocketFlames').show();
            gsap.set('#rocketWrapper', { bottom: '20px', opacity: 1, scale: 1 });

            // Start "Warp" background if exists
            if (window.backgroundEngine) window.backgroundEngine.setSpeed(2);

            $.post('limbo.php?action=roll', { bet, target }, function (res) {
                if (res.success) {
                    $('#userMoney').text(res.money);

                    const duration = res.result > 10 ? 2 : 1;
                    const resultMult = res.result;
                    const isWin = res.win;

                    // Counter Animation
                    animateCounter(1.00, resultMult, duration, isWin, res);
                } else {
                    Swal.fire('Lỗi', res.message, 'error');
                    $('#rollBtn').prop('disabled', false).text('⚡ PHÓNG TÊN LỬA');
                    $('#rocketFlames').hide();
                    isRolling = false;
                }
            });
        }

        function animateCounter(start, end, duration, isWin, res) {
            const el = document.getElementById('resultMult');
            let obj = { val: start };

            gsap.to(obj, {
                val: end,
                duration: duration,
                ease: "power2.in",
                onUpdate: () => {
                    el.innerText = obj.val.toFixed(2) + 'x';
                    createSparks();
                    // Move rocket up
                    const progress = (obj.val - start) / (end - start || 1);
                    gsap.set('#rocketWrapper', { bottom: (20 + progress * 300) + 'px' });

                    if (obj.val > 5) {
                        gsap.set(el, { x: (Math.random() - 0.5) * 5, y: (Math.random() - 0.5) * 5 });
                    }
                },
                onComplete: () => {
                    gsap.set(el, { x: 0, y: 0 });
                    $('#rocketFlames').hide();

                    if (isWin) {
                        el.classList.add('win');
                        gsap.to(el, { scale: 1.3, duration: 0.3, yoyo: true, repeat: 1, ease: "back.out(2)" });
                        gsap.to('#rocketWrapper', { y: -500, opacity: 0, duration: 0.5, ease: "power2.in" });
                        const rawWin = parseInt((res.winAmount + '').replace(/[^0-9]/g, '')) || 0;
                        if (window.GameEffects) window.GameEffects.showWin(rawWin);
                    } else {
                        el.classList.add('loss');
                        gsap.to(el, { x: 10, duration: 0.05, repeat: 5, yoyo: true });
                        gsap.to('#rocketWrapper', { scale: 1.5, opacity: 0, duration: 0.2 });
                        if (window.GameEffects) window.GameEffects.showLoss(0);
                    }

                    if (window.backgroundEngine) window.backgroundEngine.setSpeed(0.05);

                    setTimeout(() => {
                        $('#rollBtn').prop('disabled', false).text('⚡ PHÓNG TÊN LỬA');
                        isRolling = false;
                    }, 1000);
                }
            });
        }

        function createSparks() {
            const area = document.querySelector('.display-area');
            for (let i = 0; i < 2; i++) {
                const spark = document.createElement('div');
                spark.className = 'spark';
                const size = Math.random() * 3 + 1;
                spark.style.width = size + 'px';
                spark.style.height = size + 'px';
                spark.style.left = '50%';
                spark.style.top = '50%';
                area.appendChild(spark);

                const angle = Math.random() * Math.PI * 2;
                const dist = Math.random() * 200 + 50;
                gsap.to(spark, {
                    x: Math.cos(angle) * dist,
                    y: Math.sin(angle) * dist,
                    opacity: 0,
                    duration: 0.5,
                    onComplete: () => spark.remove()
                });
            }
        }
    </script>

    <canvas id="threejs-background"></canvas>
    <script>
        (function () {
            window.themeConfig = {
                particleCount: 800,
                particleSize: 0.06,
                particleColor: '#00d2ff',
                particleOpacity: 0.6,
                shapeCount: 12,
                shapeColors: ["#00d2ff", "#3a7bd5", "#f1c40f"],
                shapeOpacity: 0.2,
                bgGradient: ["#000428", "#004e92", "#000000"]
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