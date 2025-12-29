<?php
session_start();

require 'db_connect.php';

// Load theme
require_once 'load_theme.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

$id = $_SESSION['Iduser'];
$user = $conn->query("SELECT Money FROM users WHERE Iduser = $id")->fetch_assoc();
$money = intval($user['Money']);
$symbols = ['🍎', '🍌', '🍒', '🍇', '🍉', '🍍', '🥝', ' 🎭 '];

$s1 = $s2 = $s3 = '❔';
$msg = "🎰 Hãy thử vận may!";
$reward = 0;
$thang = $thua = $hoa = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bet = intval($_POST['bet'] ?? 0);
    if ($bet > 0 && $money >= $bet) {
        $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $id");

        $s1 = $symbols[rand(0, count($symbols) - 1)];
        $s2 = $symbols[rand(0, count($symbols) - 1)];
        $s3 = $symbols[rand(0, count($symbols) - 1)];
        $result = "$s1 - $s2 - $s3";

        if ($s1 === '🍎' && $s2 === '🍎' && $s3 === '🍎') {
            $reward = $bet * 10;
            $msg = "WTF Thật Không Thể Tin Nổi. Bạn Nhận Được: $reward!";
            $thang++;
        } elseif ($s1 === $s2 && $s2 === $s3) {
            $reward = $bet * 5;
            $msg = "Ô Mai Gót Oách Sà Lách Vô Cùng. Bạn Nhận Được: $reward!";
            $thang++;
        } elseif ($s1 === ' 🎭 ' && $s2 === ' 🎭 ' && $s3 === ' 🎭 ') {
            $reward = $bet * 50;
            $msg = "WTF Ân Bờ Lí Vơ Bồ Không Thể Nào!!! .Bạn Nhận Được: $reward!";
            $thang++;
        } elseif ($s1 === $s2 || $s2 === $s3 || $s1 === $s3) {
            $msg = "Cố Thêm Tí Nữa Là Múp Rồi";
            $hoa++;
        } else {
            $msg = "Buồn Quá Thử Lại Đi Nhé :)))";
            $thua++;
        }

        if ($reward > 0) {
            $conn->query("UPDATE users SET Money = Money + $reward WHERE Iduser = $id");
        }

        $stmt = $conn->prepare("INSERT INTO history_ac (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iisi", $id, $bet, $result, $reward);
        $stmt->execute();
        $stmt->close();

        $money = $conn->query("SELECT Money FROM users WHERE Iduser = $id")->fetch_assoc()['Money'];
    } else {
        $msg = "⚠️ Cược không hợp lệ hoặc số dư không đủ!";
        $s1 = $s2 = $s3 = '❌';
    }
}

$sql = "SELECT * FROM history_ac WHERE Iduser = $id ORDER BY Time DESC LIMIT 20";
$result = $conn->query($sql);
?><!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Máy Cần Gạt Quay</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="assets/js/game-effects.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
        <link rel="stylesheet" href="assets/css/game-effects.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhancements.css">
        <link rel="stylesheet" href="assets/css/game-effects.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhancements.css">
    <style>
        body {
            cursor: url('chuot.png'), auto;
            color: white;
            font-family: 'Segoe UI', Arial, sans-serif;
            text-align: center;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            position: relative;
        }
        
        /* Three.js canvas background */
        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }
        
        .slot-machine, .container {
            position: relative;
            z-index: 1;
        }

        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), pointer !important;
        }

        .slot-machine {
            margin: 40px auto;
            padding: 40px;
            background: rgba(51, 51, 51, 0.98);
            border-radius: var(--border-radius-lg);
            width: 100%;
            max-width: 500px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
            border: 3px solid rgba(255, 215, 0, 0.5);
            position: relative;
        }

        .slot-machine::before {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            background: linear-gradient(45deg, #ffd700, #ffed4e, #ffd700);
            border-radius: var(--border-radius-lg);
            z-index: -1;
            opacity: 0.3;
        }

        .reels {
            display: flex;
            justify-content: center;
            gap: 25px;
            margin: 30px 0;
            perspective: 1000px;
        }

        .reel {
            font-size: 70px;
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, #1a1a1a 0%, #000 100%);
            border-radius: var(--border-radius);
            display: flex;
            justify-content: center;
            align-items: center;
            border: 3px solid #ffd700;
            box-shadow: 
                inset 0 0 20px rgba(255, 215, 0, 0.3),
                0 0 20px rgba(255, 215, 0, 0.5);
            position: relative;
            overflow: hidden;
            transition: transform 0.1s ease;
        }

        .reel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, 
                transparent 0%, 
                rgba(255, 255, 255, 0.1) 50%, 
                transparent 100%);
            pointer-events: none;
        }

        .reel.spinning {
            animation: reelSpin 0.1s linear infinite;
            box-shadow: 
                inset 0 0 30px rgba(255, 215, 0, 0.6),
                0 0 40px rgba(255, 215, 0, 0.8);
        }

        .reel.stop-animation {
            animation: reelStop 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        @keyframes reelSpin {
            0% { transform: rotateY(0deg) scale(1); }
            50% { transform: rotateY(180deg) scale(1.1); }
            100% { transform: rotateY(360deg) scale(1); }
        }

        @keyframes reelStop {
            0% { transform: scale(1.2) rotateY(180deg); }
            50% { transform: scale(0.9) rotateY(0deg); }
            100% { transform: scale(1) rotateY(0deg); }
        }

        .reel.win {
            animation: winPulse 0.6s ease;
            box-shadow: 
                inset 0 0 30px rgba(0, 255, 0, 0.8),
                0 0 50px rgba(0, 255, 0, 0.6);
            border-color: #00ff00;
        }

        @keyframes winPulse {
            0%, 100% { transform: scale(1); }
            25% { transform: scale(1.15) rotate(5deg); }
            50% { transform: scale(1.2) rotate(-5deg); }
            75% { transform: scale(1.15) rotate(5deg); }
        }

        .money {
            margin-bottom: 20px;
            font-size: 22px;
            font-weight: 700;
            color: #ffd700;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .message {
            margin-top: 20px;
            font-weight: 700;
            font-size: 18px;
            padding: 15px;
            border-radius: var(--border-radius);
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .message.win {
            color: #00ff00;
            background: rgba(0, 255, 0, 0.2);
            border: 2px solid #00ff00;
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.5);
            animation: messageWin 0.6s ease;
        }

        .message.lose {
            color: #ff6b6b;
            background: rgba(255, 107, 107, 0.2);
            border: 2px solid #ff6b6b;
        }

        @keyframes messageWin {
            0% {
                transform: scale(0.8);
                opacity: 0;
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        input[type="number"] {
            padding: 12px 18px;
            width: 150px;
            font-size: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--border-radius);
            background: rgba(255, 255, 255, 0.95);
            color: var(--text-dark);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        input[type="number"]:focus {
            outline: none;
            border-color: #ffd700;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.2);
        }

        button {
            padding: 14px 28px;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 18px;
            font-weight: 700;
            color: white;
            box-shadow: 0 4px 15px rgba(255, 107, 53, 0.4);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-top: 15px;
        }

        button:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 107, 53, 0.6);
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .bottom-section {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            gap: 30px;
            margin: 40px auto;
            max-width: 1200px;
            flex-wrap: wrap;
        }

        .history-box {
            background: rgba(68, 68, 68, 0.98);
            padding: 25px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 600px;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .history-box h3 {
            color: #ffd700;
            margin-bottom: 15px;
            font-size: 22px;
        }

        .chart-box {
            background: rgba(68, 68, 68, 0.98);
            padding: 25px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .chart-box h3 {
            color: #ffd700;
            margin-bottom: 15px;
            font-size: 22px;
        }

        .chart-box canvas {
            max-width: 100%;
            max-height: 300px;
        }

        table {
            width: 100%;
            background: rgba(34, 34, 34, 0.8);
            color: white;
            font-size: 14px;
            border-collapse: collapse;
        }

        th {
            background: rgba(255, 215, 0, 0.3);
            color: #ffd700;
            padding: 12px;
            font-weight: 700;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        tr:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        a button {
            background: linear-gradient(135deg, #555 0%, #333 100%);
            margin-top: 20px;
        }

        a button:hover {
            background: linear-gradient(135deg, #666 0%, #444 100%);
        }
    </style>
</head>
<body>
    <canvas id="threejs-background"></canvas>
<div class="slot-machine">
    <h1>🎰 Máy Cần Gạt Quay</h1>
    <div class="money">💰 Số dư: <strong><?= $money ?></strong></div>
    <form method="post" onsubmit="startSpin(event)">
        <input type="number" name="bet" id="betInput" placeholder="Tiền cược" min="1" required>
        <br><br>
        <button type="submit">Quay 🎲</button>
        
    </form>
    <div class="reels">
        <div class="reel" id="r1"><?= htmlspecialchars($s1, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="reel" id="r2"><?= htmlspecialchars($s2, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="reel" id="r3"><?= htmlspecialchars($s3, ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <div class="message <?= ($thang > 0) ? 'win' : (($thua > 0) ? 'lose' : '') ?>" id="gameMessage"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
    <?php if ($reward > 0): ?>
        <div id="rewardDisplay" style="font-size: 24px; font-weight: 800; color: #22c55e; margin-top: 10px;">
            +<?= number_format($reward) ?> VNĐ
        </div>
    <?php endif; ?>
</div>
<br><br>
<a href="index.php">
    <button type="button" style="background:#555; color:white;">🏠 Trang chủ</button>
</a>


<div class="bottom-section">
    <div class="history-box">
        <h3>Lịch sử quay</h3>
        <?php if ($result->num_rows > 0): ?>
        <table border="1" cellpadding="10">
            <tr>
                <th>ID</th>
                <th>Bet</th>
                <th>Kết quả</th>
                <th>Thưởng</th>
                <th>Thời gian</th>
            </tr>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['Id'] ?></td>
                <td><?= number_format($row['Bet']) ?></td>
                <td><?= $row['Result'] ?></td>
                <td><?= number_format($row['WinAmount']) ?></td>
                <td><?= $row['Time'] ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
        <?php else: ?>
            <p>Chưa có lượt quay nào.</p>
        <?php endif; ?>
    </div>

    <div class="chart-box">
        <h3>Biểu đồ thống kê</h3>
        <canvas id="ketQuaChart"></canvas>
    </div>
</div>

<script>
const symbols = ['🍎','🍌','🍒','🍇','🍉','🍍','🥝','🎭'];
const delay = ms => new Promise(res => setTimeout(res, ms));

async function startSpin(event) {
    event.preventDefault();
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const r1 = document.getElementById('r1');
    const r2 = document.getElementById('r2');
    const r3 = document.getElementById('r3');
    const message = document.querySelector('.message');
    
    // Disable button
    submitBtn.disabled = true;
    submitBtn.textContent = 'Đang quay... 🎰';
    
    // Reset message
    message.className = 'message';
    message.textContent = '🎰 Đang quay...';
    
    // Start spinning animation
    r1.classList.add('spinning');
    r2.classList.add('spinning');
    r3.classList.add('spinning');
    
    // Button press effect
    GameEffects.buttonPressEffect(submitBtn);
    
    // Particle explosion at button
    const rect = submitBtn.getBoundingClientRect();
    GameEffects.createParticleExplosion(rect.left + rect.width / 2, rect.top + rect.height / 2, 20);
    
    // Spin reels with increasing delay
    const spinCount = 15;
    for (let i = 0; i < spinCount; i++) {
        r1.textContent = symbols[Math.floor(Math.random() * symbols.length)];
        r2.textContent = symbols[Math.floor(Math.random() * symbols.length)];
        r3.textContent = symbols[Math.floor(Math.random() * symbols.length)];
        await delay(50 + i * 10);
    }
    
    // Stop animation with delay for each reel
    r1.classList.remove('spinning');
    r1.classList.add('stop-animation');
    await delay(200);
    
    r2.classList.remove('spinning');
    r2.classList.add('stop-animation');
    await delay(200);
    
    r3.classList.remove('spinning');
    r3.classList.add('stop-animation');
    await delay(300);
    
    // Remove stop animation
    r1.classList.remove('stop-animation');
    r2.classList.remove('stop-animation');
    r3.classList.remove('stop-animation');
    
    // Check if win (all 3 same)
    const isWin = r1.textContent === r2.textContent && r2.textContent === r3.textContent;
    const isBigWin = isWin && (r1.textContent === '🎭' || r1.textContent === '🍎');
    
    if (isWin) {
        // Add glow effect to reels
        r1.classList.add('win');
        r2.classList.add('win');
        r3.classList.add('win');
        
        // Add glow effect
        GameEffects.addGlowEffect(r1, 2000);
        GameEffects.addGlowEffect(r2, 2000);
        GameEffects.addGlowEffect(r3, 2000);
        
        // Celebrate
        if (isBigWin) {
            GameEffects.celebrateBigWin(0);
        } else {
            GameEffects.celebrateWin(0);
        }
    } else {
        // Shake effect on lose
        GameEffects.showLoseEffect(document.querySelector('.slot-machine'));
    }
    
    // Submit form
    form.submit();
}

const ctx = document.getElementById('ketQuaChart').getContext('2d');
const ketQuaChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Thắng', 'Thua', 'Hòa'],
        datasets: [{
            label: 'Kết quả',
            data: [<?= $thang ?>, <?= $thua ?>, <?= $hoa ?>],
            backgroundColor: [
                'rgba(34, 197, 94, 0.7)',
                'rgba(239, 68, 68, 0.7)',
                'rgba(234, 179, 8, 0.7)'
            ],
            borderColor: [
                'rgba(34, 197, 94, 1)',
                'rgba(239, 68, 68, 1)',
                'rgba(234, 179, 8, 1)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' },
            title: { display: true, text: 'Tỉ lệ thắng / thua / hòa' }
        }
    }
});

// Initialize Three.js Background
(function() {
    window.themeConfig = {
        particleCount: <?= $particleCount ?>,
        particleSize: <?= $particleSize ?>,
        particleColor: '<?= $particleColor ?>',
        particleOpacity: <?= $particleOpacity ?>,
        shapeCount: <?= $shapeCount ?>,
        shapeColors: <?= json_encode($shapeColors) ?>,
        shapeOpacity: <?= $shapeOpacity ?>,
        bgGradient: <?= json_encode($bgGradient) ?>
    };
    const script = document.createElement('script');
    script.src = 'threejs-background.js';
    script.onload = function() { console.log('Three.js background loaded'); };
    document.head.appendChild(script);
})();
</script>

    <script src="assets/js/game-effects-auto.js"></script>

        <script src="assets/js/game-enhancements.js"></script>
<script>
    // Auto initialize game effects
    if (typeof GameEffectsAuto !== 'undefined') {
        GameEffectsAuto.init();
    }
</script>
</body>
</html>
