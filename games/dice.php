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


// Get statistics from database for chart
$gameThang = 0;
$gameThua = 0;
$sqlStats = "SELECT COUNT(*) as total, SUM(CASE WHEN WinAmount > 0 THEN 1 ELSE 0 END) as wins FROM history_dice WHERE Iduser = ?";
$stmtStats = $conn->prepare($sqlStats);
$stmtStats->bind_param("i", $userId);
$stmtStats->execute();
$resultStats = $stmtStats->get_result();
if ($rowStats = $resultStats->fetch_assoc()) {
    $gameThang = $rowStats['wins'] ?? 0;
    $gameThua = ($rowStats['total'] ?? 0) - $gameThang;
}
$stmtStats->close();

$money = $user['Money'];
$userName = $user['Name'];
$stmt->close();

$conn->query("CREATE TABLE IF NOT EXISTS history_dice (Id INT AUTO_INCREMENT PRIMARY KEY, Iduser INT NOT NULL, Bet DECIMAL(30,2) NOT NULL, Result VARCHAR(255) NOT NULL, WinAmount DECIMAL(30,2) NOT NULL, Time DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false];
    if ($action === 'roll') {
        $bet = (float) ($_POST['bet'] ?? 0);
        $target = (float) ($_POST['target'] ?? 50);
        $mode = $_POST['mode'] ?? 'over';
        if ($bet <= 0 || $bet > $money || $target < 2 || $target > 98) {
            $response['message'] = "Yêu cầu không hợp lệ!";
        } else {
            $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");
            $result = rand(0, 10000) / 100;
            $win = ($mode === 'over') ? ($result > $target) : ($result < $target);
            $winChance = ($mode === 'over') ? (100 - $target) : $target;
            $winChance = max(0.01, min(99, $winChance));
            $multiplier = round((99 / $winChance), 4);
            $winAmount = $win ? round($bet * $multiplier) : 0;
            if ($winAmount > 0)
                $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = $userId");
            $profit = $winAmount - $bet;
            $resStr = "Target: $mode $target | Result: $result";
            $his = $conn->prepare("INSERT INTO history_dice (Iduser,Bet,Result,WinAmount,Time) VALUES (?,?,?,?,NOW())");
            $his->bind_param("idss", $userId, $bet, $resStr, $profit);
            $his->execute();
            logGameHistoryWithAll($conn, $userId, 'Dice', $bet, $winAmount, $win);
            $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
            $response = [
                'success' => true,
                'result' => $result,
                'win' => $win,
                'multiplier' => $multiplier,
                'winAmount' => number_format($winAmount, 0, ',', '.'),
                'money' => number_format($newMoney, 0, ',', '.'),
                'winChance' => round($winChance, 2) . '%'
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
    <title>Quantum Dice Premium - Vegas Royale</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;500;700&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php echo getCSSIncludes(['special_effects' => true]); ?>
    <style>
        :root {
            --primary: #f5c842;
            --accent: #f1c40f;
            --win: #2ecc71;
            --lose: #ff4757;
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
        }

        /* Cố định con trỏ chuột trên mọi phần tử */
        * {
            cursor: url('../img/tay.png'), auto !important;
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

        .game-area {
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
            padding: 40px;
        }

        .dice-container {
            width: 100%;
            max-width: 700px;
            position: relative;
            padding: 60px 0;
        }

        .slider-track {
            height: 12px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            position: relative;
        }

        .slider-fill {
            position: absolute;
            height: 100%;
            border-radius: 10px;
            transition: 0.1s;
        }

        .slider-handle {
            position: absolute;
            top: 50%;
            width: 36px;
            height: 36px;
            background: #fff;
            border-radius: 10px;
            transform: translate(-50%, -50%);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.4);
            cursor: grab;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .slider-handle::after {
            content: '║';
            color: #000;
            font-size: 1rem;
            font-weight: 900;
        }

        .result-marker {
            position: absolute;
            top: -60px;
            width: 60px;
            height: 60px;
            transform: translateX(-50%) scale(0);
            z-index: 15;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dice-visual {
            width: 100%;
            height: 100%;
            background: #fff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Orbitron';
            font-weight: 900;
            font-size: 1rem;
            color: #000;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .result-marker.win .dice-visual {
            background: var(--win);
            color: #000;
            box-shadow: 0 0 30px var(--win);
        }

        .result-marker.lose .dice-visual {
            background: var(--lose);
            color: #fff;
            box-shadow: 0 0 30px var(--lose);
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
            background: linear-gradient(135deg, var(--primary), #e67e22);
            color: #000;
            box-shadow: 0 10px 30px rgba(245, 200, 66, 0.3);
            width: 100%;
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
            overflow: hidden;
        }

        .stat-item b {
            font-size: 1.1rem;
            font-family: 'Orbitron';
            color: var(--accent);
            white-space: nowrap;
        }

        .mode-toggle {
            display: flex;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50px;
            padding: 4px;
            margin-bottom: 1rem;
        }

        .mode-btn {
            flex: 1;
            padding: 10px;
            border-radius: 50px;
            border: none;
            background: none;
            color: #fff;
            font-size: 0.75rem;
            font-weight: 900;
            cursor: pointer;
            text-transform: uppercase;
        }

        .mode-btn.active {
            background: #fff;
            color: #000;
        }

        #userMoney {
            font-size: 1rem !important;
            word-break: break-all;
        }
    
        /* Statistics Container */
        .stats-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .stat-item.wins {
            border-left: 4px solid #4ade80;
        }
        
        .stat-item.losses {
            border-left: 4px solid #ff6b6b;
        }
        
        .stat-item .label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        
        .stat-item .value {
            font-size: 28px;
            font-weight: 700;
            color: #ffd700;
        }
        
        .chart-box {
            display: flex;
            flex-direction: column;
        }
        
        .chart-box canvas {
            margin-top: 20px;
        }

    </style>
</head>

<body>
    <div class="main-container">
        <div class="glass-card">
            <div class="sidebar">
                <div>
                    <h1
                        style="margin:0; font-size: 2.2rem; font-weight: 900; color: var(--primary); font-family: 'Orbitron'; letter-spacing: 2px;">
                        DICE</h1>
                    <p style="margin:0; opacity:0.4; font-size: 0.75rem; letter-spacing: 1px;">Quantum Slide Protocol
                    </p>
                </div>
                <div class="mode-toggle">
                    <button class="mode-btn active" onclick="setMode('over')">Lớn hơn</button>
                    <button class="mode-btn" onclick="setMode('under')">Nhỏ hơn</button>
                </div>
                <div class="input-group">
                    <label>Gtlm cược (gtlm)</label>
                    <input type="number" id="betAmount" value="10000" min="1000">
                    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:5px; margin-top:8px;">
                        <button onclick="adjBet(0.5)"
                            style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:8px; padding:5px; font-size:0.6rem; cursor:pointer;">1/2</button>
                        <button onclick="adjBet(2)"
                            style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:8px; padding:5px; font-size:0.6rem; cursor:pointer;">x2</button>
                        <button onclick="adjBet('max')"
                            style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; border-radius:8px; padding:5px; font-size:0.6rem; cursor:pointer;">MAX</button>
                    </div>
                </div>
                <div class="stat-grid">
                    <div class="stat-item"><span>NHÂN THƯỞNG</span><b id="multiplierDisp">x1.98</b></div>
                    <div class="stat-item"><span>TỶ LỆ THẮNG</span><b id="chanceDisp">50.00%</b></div>
                </div>
                <button id="rollBtn" class="btn-roll" onclick="playDice()">🎲 LẮC XÚC XẮC</button>
                <button class="btn-roll" onclick="DiceTutorial.start()"
                    style="background:rgba(255,255,255,0.1); color:#fff; font-size:0.8rem; padding:0.8rem; margin-top:-0.5rem; box-shadow:none;">📖
                    HƯỚNG DẪN CHƠI</button>
                <div style="margin-top:auto; padding-top:1rem; border-top:1px solid rgba(255,255,255,0.1);">
                    <div class="stat-item" style="background:rgba(0,0,0,0.4)">
                        <span>Số Gtlm HIỆN TẠI</span>
                        <div style="display:flex; align-items:baseline; justify-content:center; gap:5px;"><b
                                id="userMoney"
                                style="color:var(--accent)"><?= number_format($money, 0, ',', '.') ?></b><small
                                style="opacity:0.5; font-size:0.6rem;">GTLM</small></div>
                    </div>
                </div>
            </div>
            <div class="game-area">
                <div class="dice-container">
                    <div class="result-marker" id="resultMarker">
                        <div class="dice-visual" id="diceVal">0.00</div>
                    </div>
                    <div class="slider-track" id="sliderTrack" style="overflow:hidden">
                        <div class="slider-fill" id="loseFill"
                            style="position:absolute; height:100%; width:100%; left:0; top:0; background:var(--lose); opacity:0.3">
                        </div>
                        <div class="slider-fill" id="sliderFill"
                            style="position:absolute; height:100%; top:0; background:var(--win); opacity:0.8"></div>
                        <div class="slider-handle" id="sliderHandle"></div>
                    </div>
                    <div class="track-labels"
                        style="display:flex; justify-content:space-between; margin-top:10px; opacity:0.4; font-family:'Orbitron'; font-size:0.7rem;">
                        <span>0</span><span>25</span><span>50</span><span>75</span><span>100</span>
                    </div>
                </div>
                <div style="margin-top: 20px; text-align: center;">
                    <div id="targetValDisp"
                        style="font-size: 4rem; font-weight: 900; font-family: 'Orbitron'; color: var(--primary);">50.00
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics and History Section -->
    <div class="stats-card-container" style="max-width: 1100px; margin: 20px auto; padding: 0 20px;">
        <div class="glass-card" style="display: block; padding: 20px;">
            <h3 style="font-family: 'Orbitron'; color: var(--primary); margin-top: 0;">BÁO CÁO CHIẾN DỊCH</h3>
            <div class="stats-container">
                <div class="stat-item wins">
                    <div class="label">Lần Thắng</div>
                    <div class="value"><?= $gameThang ?></div>
                </div>
                <div class="stat-item losses">
                    <div class="label">Lần Thua</div>
                    <div class="value"><?= $gameThua ?></div>
                </div>
            </div>
            <canvas id="gameChart" style="max-height: 300px;"></canvas>
        </div>
    </div>

    <div class="history-box" style="max-width: 1100px; margin: 20px auto; padding: 0 20px;">
        <div class="glass-card" style="display: block; padding: 20px;">
            <h3 style="font-family: 'Orbitron'; color: var(--primary); margin-top: 0;">NHẬT KÝ CHIẾN ĐẤU</h3>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--primary);">
                        <th style="padding: 10px; text-align: center;">ID</th>
                        <th style="padding: 10px; text-align: right;">Cược</th>
                        <th style="padding: 10px; text-align: left;">Kết quả</th>
                        <th style="padding: 10px; text-align: right;">Nhận</th>
                        <th style="padding: 10px; text-align: right;">Thời gian</th>
                    </tr>
                </thead>
                <tbody id="history-tbody">
                    <!-- AJAX Load -->
                </tbody>
            </table>
            <p id="history-loading" style="text-align: center; opacity: 0.5; margin-top: 20px;">Đang tải dữ liệu...</p>
        </div>
    </div>

    <script>
        let currentTarget = 50.00, currentMode = 'over', isRolling = false;
        function setMode(m) {
            currentMode = m; $('.mode-btn').removeClass('active');
            if (m === 'over') $('.mode-btn').first().addClass('active'); else $('.mode-btn').last().addClass('active');
            updateSlider();
        }
        function adjBet(ratio) {
            const money = parseFloat($('#userMoney').text().replace(/\./g, ''));
            if (ratio === 'max') $('#betAmount').val(money);
            else $('#betAmount').val(Math.floor(($('#betAmount').val() || 0) * ratio));
        }
        const track = document.getElementById('sliderTrack'), handle = document.getElementById('sliderHandle'), fill = document.getElementById('sliderFill');
        function updateSlider() {
            handle.style.left = currentTarget + '%';
            if (currentMode === 'over') { fill.style.left = currentTarget + '%'; fill.style.width = (100 - currentTarget) + '%'; }
            else { fill.style.left = '0%'; fill.style.width = currentTarget + '%'; }
            const chance = (currentMode === 'over' ? (100 - currentTarget) : currentTarget);
            $('#chanceDisp').text(chance.toFixed(2) + '%'); $('#multiplierDisp').text('x' + (99 / chance).toFixed(4));
            $('#targetValDisp').text(currentTarget.toFixed(2));
        }
        function handleDrag(e) {
            if (isRolling) return;
            const rect = track.getBoundingClientRect();
            let p = (((e.clientX || e.touches[0].clientX) - rect.left) / rect.width) * 100;
            currentTarget = Math.max(2, Math.min(98, p)); updateSlider();
        }
        track.addEventListener('mousedown', (e) => { handleDrag(e); window.addEventListener('mousemove', handleDrag); window.addEventListener('mouseup', () => window.removeEventListener('mousemove', handleDrag)); });
        updateSlider();

        function playDice() {
            if (isRolling) return;
            isRolling = true; $('#rollBtn').prop('disabled', true).text('ĐANG LẮC...');
            $('#resultMarker').removeClass('visible win lose');
            $.post('dice.php?action=roll', { bet: $('#betAmount').val(), target: currentTarget, mode: currentMode }, function (res) {
                if (res.success) {
                    $('#userMoney').text(res.money);
                    const marker = document.getElementById('resultMarker'), diceVal = document.getElementById('diceVal');
                    const tl = gsap.timeline();
                    tl.to(marker, { opacity: 1, scale: 1, duration: 0.1 });
                    for (let i = 0; i < 8; i++) {
                        tl.to(marker, { left: (Math.random() * 80 + 10) + '%', duration: 0.08, onUpdate: () => { diceVal.innerText = Math.floor(Math.random() * 100); } });
                    }
                    tl.to(marker, {
                        left: res.result + '%', duration: 0.5, ease: "back.out(1.5)",
                        onComplete: () => {
                            diceVal.innerText = res.result.toFixed(2);
                            marker.classList.add(res.win ? 'win' : 'lose');
                            if (window.GameEffects) res.win ? window.GameEffects.showWin(parseInt(res.winAmount.replace(/\./g, ''))) : window.GameEffects.showLoss(0);
                            setTimeout(() => { 
                                $('#rollBtn').prop('disabled', false).text('🎲 LẮC XÚC XẮC'); 
                                isRolling = false; 
                                loadDiceHistory(); 
                            }, 1000);
                        }
                    });
                } else { Swal.fire('Lỗi', res.message, 'error'); $('#rollBtn').prop('disabled', false).text('🎲 LẮC XÚC XẮC'); isRolling = false; }
            });
        }

        async function loadDiceHistory() {
            try {
                const response = await fetch('../game_history_universal.php?action=get_history&game=dice');
                if (!response.ok) return;
                const data = await response.json();
                if (data.success && data.history) {
                    const tbody = document.getElementById('history-tbody');
                    const loading = document.getElementById('history-loading');
                    if (data.history.length > 0) {
                        tbody.innerHTML = '';
                        data.history.forEach(record => {
                            const row = document.createElement('tr');
                            row.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                            row.innerHTML = `
                                <td style="padding: 10px; text-align: center;">${record.Id}</td>
                                <td style="padding: 10px; text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                                <td style="padding: 10px; text-align: left;">${record.Result}</td>
                                <td style="padding: 10px; text-align: right; color: ${record.WinAmount > 0 ? '#00ff88' : '#ff4757'}">
                                    ${record.WinAmount > 0 ? '+' : ''}${parseInt(record.WinAmount).toLocaleString('vi-VN')}
                                </td>
                                <td style="padding: 10px; text-align: right; opacity: 0.6;">${record.Time}</td>
                            `;
                            tbody.appendChild(row);
                        });
                        if (loading) loading.style.display = 'none';
                    } else {
                        if (loading) loading.textContent = 'Chưa có lịch sử chơi.';
                    }
                }
            } catch (error) {
                console.error('History load error:', error);
            }
        }

        (function () {
            window.themeConfig = { 
                particleCount: 600, 
                particleSize: 0.05, 
                particleColor: '#f5c842', 
                particleOpacity: 0.5, 
                shapeCount: 12, 
                shapeColors: ["#f5c842", "#f1c40f", "#ffffff"], 
                shapeOpacity: 0.2, 
                bgGradient: ["#000000", "#1a1a1a", "#2c3e50"] 
            };
            const prefix = '../';
            ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js', 'assets/js/dice-tutorial.js'].forEach(src => {
                const s = document.createElement('script'); s.src = prefix + src; s.async = false; document.head.appendChild(s);
            });
        })();

        document.addEventListener('DOMContentLoaded', function() {
            loadDiceHistory();
            const ctx = document.getElementById('gameChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Thắng', 'Thua'],
                        datasets: [{
                            data: [<?= $gameThang ?>, <?= $gameThua ?>],
                            backgroundColor: ['#f1c40f', '#ff4757'],
                            borderColor: 'transparent'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                labels: { color: '#fff', font: { family: 'Orbitron' } }
                            }
                        }
                    }
                });
            }
        });
    </script>
    <canvas id="threejs-background"></canvas>
</body>
</html>
