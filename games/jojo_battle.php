<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}

require '../db_connect.php';


// AJAX history endpoint
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_history') {
    header('Content-Type: application/json; charset=utf-8');
    
    $id = $_SESSION['Iduser'] ?? 0;
    $sql = "SELECT * FROM history_vq WHERE Iduser = ? ORDER BY Time DESC LIMIT 20";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'history' => $history
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once '../load_theme.php';

$userId = $_SESSION['Iduser'];
$sql = "SELECT Money, Name FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();


// Get statistics from database for chart
$gameThang = 0;
$gameThua = 0;
$sqlStats = "SELECT COUNT(*) as total, SUM(CASE WHEN WinAmount > 0 THEN 1 ELSE 0 END) as wins FROM history_vq WHERE Iduser = ?";
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

// --- AJAX ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'place_bet') {
        $chon = $_POST['chon'] ?? '';
        $cuoc = (int) ($_POST['cuoc'] ?? 0);
        $roundId = (int) ($_POST['round_id'] ?? 0);

        if ($cuoc > $money || $cuoc <= 0) {
            echo json_encode(['success' => false, 'message' => '⚠️ Số Gtlm không đủ!']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO bets (user_id, round_id, chosen_character, amount) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisd", $userId, $roundId, $chon, $cuoc);
        if ($stmt->execute()) {
            $newMoney = $money - $cuoc;
            $conn->query("UPDATE users SET Money = $newMoney WHERE Iduser = $userId");
            echo json_encode(['success' => true, 'newBalance' => number_format($newMoney) . ' gtlm']);
        } else {
            echo json_encode(['success' => false, 'message' => '❌ Lỗi hệ thống!']);
        }
        exit;
    }

    if ($action === 'get_result') {
        $roundId = (int) ($_POST['round_id'] ?? 0);
        $winner = rand(0, 1) ? "JoJo" : "Dio";

        $stmt = $conn->prepare("INSERT INTO rounds (round_id, winner) VALUES (?, ?)");
        $stmt->bind_param("is", $roundId, $winner);
        $stmt->execute();

        $stmt = $conn->prepare("SELECT chosen_character, amount FROM bets WHERE round_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $roundId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();

        $totalWin = 0;
        $totalBet = 0;
        $chosen = '';
        while ($bet = $res->fetch_assoc()) {
            $totalBet += $bet['amount'];
            $chosen = $bet['chosen_character'];
            if ($bet['chosen_character'] === $winner)
                $totalWin += $bet['amount'] * 2;
        }

        if ($totalWin > 0)
            $conn->query("UPDATE users SET Money = Money + $totalWin WHERE Iduser = $userId");

        // Insert vào history_vq table
        $historyStmt = $conn->prepare("INSERT INTO history_vq (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        if ($historyStmt) {
            $resultStr = "Cược: $chosen - Thắng: $winner";
            $historyStmt->bind_param("iisi", $userId, $totalBet, $resultStr, $totalWin);
            $historyStmt->execute();
            $historyStmt->close();
        }

        if (file_exists('../game_history_helper.php')) {
            require_once '../game_history_helper.php';
            logGameHistoryWithAll($conn, $userId, 'Vòng Quay JoJo', $totalBet, $totalWin, $totalWin > 0);
        }

        $stmt = $conn->query("SELECT Money FROM users WHERE Iduser = $userId");
        $newBalance = $stmt->fetch_assoc()['Money'];

        echo json_encode(['success' => true, 'winner' => $winner, 'winAmount' => $totalWin, 'newBalance' => number_format($newBalance) . ' gtlm', 'message' => ($totalWin > 0) ? "⭐ VICTORY!" : "💀 RETIRED!"]);
        exit;
    }
}

$roundId = time();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>JOJO ULTIMATE: PREMIUM EDITION</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Cinzel:wght@900&family=Bangers&family=Noto+Sans+JP:wght@900&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --jojo: #00d4ff;
            --dio: #f5c832;
            --gold: #f5c832;
            --dark: #080010;
        }

        body {
            margin: 0;
            background: var(--dark) !important;
            color: white;
            font-family: 'Poppins', sans-serif;
            overflow: hidden;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .money-badge {
            background: rgba(0, 0, 0, 0.8);
            padding: 10px 30px;
            border-radius: 50px;
            border: 2px solid var(--gold);
            color: var(--gold);
            font-weight: 900;
        }

        .theatre {
            flex: 1;
            margin: 20px;
            background: rgba(10, 5, 25, 0.8);
            backdrop-filter: blur(20px);
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        .hp-arena {
            display: flex;
            gap: 50px;
            padding: 40px;
        }

        .hp-slot {
            flex: 1;
            height: 30px;
            background: #222;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }

        .hp-fill {
            height: 100%;
            transition: width 0.3s;
        }

        .hp-j {
            background: var(--jojo);
            box-shadow: 0 0 20px var(--jojo);
        }

        .hp-d {
            background: var(--dio);
            box-shadow: 0 0 20px var(--dio);
        }

        .combat-stage {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 100px;
        }

        .fighter {
            width: 300px;
            text-align: center;
        }

        .fighter img {
            width: 100%;
            filter: drop-shadow(0 0 30px #000);
        }

        .vs-logo {
            font-family: 'Cinzel';
            font-size: 80px;
            font-weight: 900;
        }

        .control-ui {
            padding: 30px;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .duel-btn {
            background: var(--gold);
            color: #000;
            padding: 15px 80px;
            border-radius: 40px;
            font-weight: 900;
            font-size: 24px;
            border: none;
            cursor: pointer;
            font-family: 'Cinzel';
        }

        .card {
            padding: 20px 40px;
            border: 2px solid #444;
            border-radius: 20px;
            cursor: pointer;
            transition: 0.3s;
        }

        .card.active {
            border-color: var(--gold);
            background: rgba(245, 200, 50, 0.1);
        }

        #fx-canvas {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 1000;
        }
    
        /* History Box Styles */
        .bottom-section {
            margin-top: 50px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            max-width: 1000px;
            margin-left: auto;
            margin-right: auto;
        }

        .history-box, .chart-box {
            background: rgba(0, 121, 107, 0.9);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            color: white;
        }

        .history-box h3, .chart-box h3 {
            margin-top: 0;
            font-size: 20px;
            color: #ffd700;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .history-box table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .history-box table tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideIn 0.5s ease-out forwards;
        }

        .history-box table td, .history-box table th {
            padding: 10px;
            text-align: center;
        }

        .history-box table th {
            background: rgba(255, 255, 255, 0.1);
            font-weight: 700;
            color: #ffd700;
        }

        .history-box table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        @media (max-width: 768px) {
            .bottom-section {
                grid-template-columns: 1fr;
                gap: 20px;
            }
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div id="fx-canvas"></div>
    <div
        style="padding: 20px; display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.5);">
        <a href="../index.php" style="color: white; text-decoration: none; font-weight: 900;">🏠 TRANG CHỦ</a>
        <div class="money-badge">💰 <span id="money-val"><?= number_format($money) ?> gtlm</span></div>
    </div>

    <div class="theatre">
        <div class="hp-arena">
            <div class="hp-slot">
                <div class="hp-fill hp-d" id="hp-d" style="width: 100%;"></div>
            </div>
            <div class="vs-logo" style="font-size: 20px;">VS</div>
            <div class="hp-slot">
                <div class="hp-fill hp-j" id="hp-j" style="width: 100%;"></div>
            </div>
        </div>
        <div class="combat-stage">
            <div class="fighter" id="f-dio"><img src="img/dio.png"></div>
            <div class="vs-logo">VS</div>
            <div class="fighter" id="f-jojo"><img src="img/jotaro.png"></div>
        </div>
        <div class="control-ui">
            <div style="display: flex; gap: 20px;">
                <div class="card" onclick="pick('Dio', this)">DIO</div>
                <div class="card" onclick="pick('JoJo', this)">JOTARO</div>
            </div>
            <input type="number" id="cuoc" value="100000"
                style="background: #111; color: #fff; border: 1px solid #444; padding: 10px; border-radius: 10px; text-align: center; width: 200px;">
            <button class="duel-btn" onclick="startDuel()" id="btn-duel">KÍCH HOẠT!</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let selected = null;
        let active = false;

        function pick(s, el) {
            if (active) return;
            selected = s;
            $('.card').removeClass('active');
            $(el).addClass('active');
        }

        async function startDuel() {
            if (active || !selected) return;
            const amt = $('#cuoc').val();
            active = true;
            $('#btn-duel').prop('disabled', true);

            try {
                const res = await $.post('jojo_battle.php?action=place_bet', { chon: selected, cuoc: amt, round_id: Date.now() });
                if (!res.success) { Swal.fire('Lỗi', res.message, 'error'); active = false; return; }
                $('#money-val').text(res.newBalance);

                const result = await $.post('jojo_battle.php?action=get_result', { round_id: Date.now() });

                // Animation logic (Simplified for stability)
                gsap.to('.fighter', { x: (Math.random() - 0.5) * 50, repeat: 10, yoyo: true, duration: 0.1 });

                setTimeout(() => {
                    if (result.winAmount > 0) {
                        if (window.GameEffects) GameEffects.showWin(result.winAmount);
                        Swal.fire('THẮNG!', result.message, 'success');
                    } else {
                        if (window.GameEffects) GameEffects.showLoss();
                        Swal.fire('THUA!', result.message, 'error');
                    }
                    $('#money-val').text(result.newBalance);
                    active = false;
                    $('#btn-duel').prop('disabled', false);
                }, 2000);

            } catch (e) { active = false; $('#btn-duel').prop('disabled', false); }
        }
    </script>




    <!-- Statistics and History Section -->
    <div class="stats-card-container" style="max-width: 1000px; margin: 20px auto; padding: 0 20px;">
        <div class="glass-card" style="display: block; padding: 20px;">
            <h3 style="font-family: 'Cinzel'; color: var(--gold); margin-top: 0;">BÁO CÁO CHIẾN DỊCH</h3>
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

    <div class="history-box" style="max-width: 1000px; margin: 20px auto; padding: 0 20px;">
        <div class="glass-card" style="display: block; padding: 20px;">
            <h3 style="font-family: 'Cinzel'; color: var(--gold); margin-top: 0;">NHẬT KÝ CHIẾN ĐẤU</h3>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--gold);">
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

    <!-- Premium Effects System -->
    <canvas id="threejs-background"></canvas>
    <script>
        async function loadVqHistory() {
            try {
                const response = await fetch('../game_history_universal.php?action=get_history&game=vq');
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
                particleCount: 800,
                particleSize: 0.05,
                particleColor: '#ffffff',
                particleOpacity: 0.6,
                shapeCount: 10,
                shapeColors: ["#667eea", "#764ba2", "#4facfe", "#00f2fe"],
                shapeOpacity: 0.3,
                bgGradient: ["#080010", "#1a0033", "#000000"]
            };
            const prefix = '../';
            ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'].forEach(src => {
                const s = document.createElement('script'); s.src = prefix + src; s.async = false; document.head.appendChild(s);
            });
        })();

        document.addEventListener('DOMContentLoaded', function() {
            loadVqHistory();
            const ctx = document.getElementById('gameChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Thắng', 'Thua'],
                        datasets: [{
                            data: [<?= $gameThang ?>, <?= $gameThua ?>],
                            backgroundColor: ['#f5c832', '#ff4757'],
                            borderColor: 'transparent'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                labels: { color: '#fff', font: { family: 'Cinzel' } }
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
