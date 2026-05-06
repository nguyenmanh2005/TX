<?php
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require '../db_connect.php';

// AJAX history endpoint
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_history') {
    header('Content-Type: application/json; charset=utf-8');

    $id = $_SESSION['Iduser'] ?? 0;
    $sql = "SELECT * FROM history_cs WHERE Iduser = ? ORDER BY Time DESC LIMIT 20";
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

// Load theme
require_once '../load_theme.php';

$userId = $_SESSION['Iduser'];
$sql = "SELECT Money, Name FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get statistics for chart
$gameThang = 0;
$gameThua = 0;
$sqlStats = "SELECT COUNT(*) as total, SUM(CASE WHEN WinAmount > 0 THEN 1 ELSE 0 END) as wins FROM history_cs WHERE Iduser = ?";
$stmtStats = $conn->prepare($sqlStats);
$stmtStats->bind_param("i", $userId);
$stmtStats->execute();
$resultStats = $stmtStats->get_result();
if ($rowStats = $resultStats->fetch_assoc()) {
    $gameThang = $rowStats['wins'] ?? 0;
    $gameThua = ($rowStats['total'] ?? 0) - $gameThang;
}
$stmtStats->close();

if (!$user) {
    die("Không tìm thấy thông tin người dùng!");
}
$money = $user['Money'];
$tenNguoiChoi = $user['Name'];

$message = "";
$winning = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['user_number'])) {
    $userInput = trim($_POST['user_number']);

    if (!preg_match('/^\d{5}$/', $userInput)) {
        $message = "❌ Vui lòng nhập đúng 5 chữ số (VD: 12345, 00129)";
    } else {
        // Sinh chuỗi ngẫu nhiên 5 chữ số
        $winning = "";
        for ($i = 0; $i < 5; $i++) {
            $winning .= strval(rand(0, 9));
        }

        // So sánh từng chữ số theo vị trí
        $correct = 0;
        for ($i = 0; $i < 5; $i++) {
            if ($userInput[$i] === $winning[$i]) {
                $correct++;
            }
        }

        // Tính thưởng theo số trúng
        $prizeTable = [0, 10000, 20000, 50000, 100000, 1000000];
        $prize = $prizeTable[$correct];
        $isWin = ($prize > 0);

        $newBalance = $money + $prize;
        if ($isWin) {
            $message = "🎉 Bạn trúng $correct số! Nhận thưởng: " . number_format($prize) . " gtlm.";
        } else {
            $message = "😢 Không trúng số nào. Thử lại nhé!";
        }

        // Cập nhật Số Gtlm
        $stmtUpd = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        if ($stmtUpd) {
            $stmtUpd->bind_param("di", $newBalance, $userId);
            $stmtUpd->execute();
            $stmtUpd->close();
        }

        // Insert vào history_cs table
        if (isset($_SESSION['Iduser'])) {
            $betAmount = 0;
            $resultStr = $winning;
            $winAmount = $prize;

            $historyStmt = $conn->prepare("INSERT INTO history_cs (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
            if ($historyStmt) {
                $historyStmt->bind_param("iisi", $userId, $betAmount, $resultStr, $winAmount);
                $historyStmt->execute();
                $historyStmt->close();
            }
        }

        // Track quest progress
        require_once '../game_history_helper.php';
        logGameHistoryWithAll($conn, $userId, 'Cơ hội triệu phú', 0, $prize, $isWin);

        require_once '../user_progress_helper.php';
        up_add_xp($conn, $userId, 5 + ($isWin ? 10 : 0));

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'winning' => $winning,
                'message' => $message,
                'correct' => $correct,
                'win' => $isWin,
                'balance' => number_format($newBalance, 0, ',', '.'),
                'stats' => [
                    'thang' => $gameThang + ($isWin ? 1 : 0),
                    'thua' => $gameThua + (!$isWin ? 1 : 0)
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $money = $newBalance;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <meta charset="UTF-8">
    <title>Vietlott Mini</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <link rel="stylesheet" href="../assets/css/game-effects.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <style>
        body {
            cursor: url('img/chuot.png'), auto !important;
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%);
            text-align: center;
            position: relative;
            \n padding: 40px 20px;
            min-height: 100vh;
        }

        * {
            cursor: inherit;
        }

        button,
        a,
        input[type="button"],
        input[type="submit"],
        label,
        select,
        input[type="text"] {
            cursor: url('img/tay.png'), pointer !important;
        }

        .game-container {
            max-width: 600px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.98);
            padding: 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
        }

        h1 {
            color: var(--primary-color);
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .balance-display {
            font-size: 22px;
            font-weight: 700;
            color: var(--success-color);
            padding: 15px;
            background: rgba(232, 245, 233, 0.5);
            border-radius: var(--border-radius);
            border: 2px solid var(--success-color);
            margin: 20px 0;
        }

        form {
            margin: 30px 0;
            padding: 25px;
            background: rgba(240, 248, 255, 0.5);
            border-radius: var(--border-radius);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        label {
            display: block;
            margin: 15px 0 10px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 18px;
        }

        input[type="text"] {
            padding: 14px 20px;
            font-size: 24px;
            font-weight: 700;
            width: 200px;
            text-align: center;
            border: 3px solid var(--border-color);
            border-radius: var(--border-radius);
            background: rgba(255, 255, 255, 0.95);
            color: var(--text-dark);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            letter-spacing: 4px;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.2);
        }

        button {
            padding: 14px 32px;
            font-size: 18px;
            font-weight: 700;
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            border: none;
            color: white;
            border-radius: var(--border-radius);
            cursor: pointer;
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        button:hover:not(:disabled) {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 25px rgba(46, 204, 113, 0.6);
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed !important;
        }

        .result {
            margin-top: 30px;
            font-size: 20px;
            font-weight: 700;
            padding: 20px;
            border-radius: var(--border-radius);
            animation: messageAppear 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            line-height: 1.8;
        }

        .result.win {
            color: #00ff00;
            background: rgba(40, 167, 69, 0.2);
            border: 3px solid #28a745;
            box-shadow: 0 0 25px rgba(40, 167, 69, 0.6);
            animation: messageAppear 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55), winPulse 1.5s ease infinite;
        }

        .result.lose {
            color: #ff6b6b;
            background: rgba(220, 53, 69, 0.2);
            border: 3px solid #dc3545;
        }

        @keyframes messageAppear {
            0% {
                opacity: 0;
                transform: translateY(-30px) scale(0.8);
            }

            50% {
                transform: translateY(5px) scale(1.1);
            }

            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes winPulse {

            0%,
            100% {
                transform: scale(1);
                box-shadow: 0 0 25px rgba(40, 167, 69, 0.6);
            }

            50% {
                transform: scale(1.02);
                box-shadow: 0 0 40px rgba(40, 167, 69, 0.9);
            }
        }

        .winning {
            font-weight: 700;
            font-size: 24px;
            color: #e67e22;
            margin-top: 20px;
            padding: 15px;
            background: rgba(230, 126, 34, 0.1);
            border-radius: var(--border-radius);
            border: 2px solid #e67e22;
            animation: winningAppear 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        @keyframes winningAppear {
            0% {
                opacity: 0;
                transform: scale(0.5) rotate(-180deg);
            }

            50% {
                transform: scale(1.2) rotate(10deg);
            }

            100% {
                opacity: 1;
                transform: scale(1) rotate(0deg);
            }
        }

        a {
            display: inline-block;
            margin-top: 25px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        a:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.5);
        }

        .bottom-section {
            margin-top: 50px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            max-width: 1000px;
            margin-left: auto;
            margin-right: auto;
        }

        .history-box,
        .chart-box {
            background: rgba(0, 121, 107, 0.9);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            color: white;
        }

        .history-box h3,
        .chart-box h3 {
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

        .history-box table td,
        .history-box table th {
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

        \n
    </style>
</head>

<body>


    <div class="game-container">
        <h1>🎲 Vietlott Mini - Dự Đoán 5 Chữ Số</h1>
        <div class="balance-display">
            💰 Số Gtlm hiện tại: <strong><?= number_format($money, 0, ',', '.') ?> gtlm</strong>
        </div>

        <form method="POST" id="lotteryForm">
            <label>🎯 Nhập 5 chữ số (VD: 12345):</label>
            <input type="text" name="user_number" id="userNumber" maxlength="5" pattern="\d{5}" required
                placeholder="00000">
            <br>
            <button type="submit" id="submitBtn">🎰 Quay số</button>
            <p><a href="../index.php">🏠 Quay Lại Trang Chủ</a></p>
        </form>

        <?php if ($message): ?>
            <div
                class="result <?= strpos($message, 'trúng') !== false || strpos($message, '🎉') !== false ? 'win' : 'lose' ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php if ($winning): ?>
                <div class="winning">🎯 Kết quả: <strong><?= htmlspecialchars($winning) ?></strong></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="bottom-section">
        <div class="history-box">
            <h3>📋 Lịch sử chơi (10 lần gần nhất)</h3>
            <table border="1" cellpadding="10" id="historyTable">
                <thead>
                    <tr style="background: rgba(255, 255, 255, 0.1);">
                        <th style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;">ID</th>
                        <th style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;">Cược</th>
                        <th style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;">Kết quả
                        </th>
                        <th style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;">Thắng</th>
                        <th style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;">Thời gian
                        </th>
                    </tr>
                </thead>
                <tbody id="historyBody">
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 15px; color: #aaa;">Chưa có lượt chơi nào
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="chart-box">
            <h3>📊 Thống kê</h3>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.7.3/sweetalert2.all.min.js"></script>
    <script>
        $(document).ready(function () {
            let gameChart;

            // Initialize Chart
            const ctxCs = document.getElementById('gameChart');
            if (ctxCs) {
                gameChart = new Chart(ctxCs.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Lần Thắng', 'Lần Thua'],
                        datasets: [{
                            label: 'Kết quả',
                            data: [<?= $gameThang ?>, <?= $gameThua ?>],
                            backgroundColor: ['rgba(74, 222, 128, 0.7)', 'rgba(255, 107, 107, 0.7)'],
                            borderColor: ['rgba(74, 222, 128, 1)', 'rgba(255, 107, 107, 1)'],
                            borderWidth: 2,
                            borderRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: 'rgba(255, 255, 255, 0.8)',
                                    font: { size: 13, weight: '600' },
                                    padding: 15,
                                    usePointStyle: true,
                                    pointStyle: 'circle'
                                }
                            }
                        }
                    }
                });
            }

            function updateUI(data) {
                if (!data.success) {
                    Swal.fire('Lỗi', data.message || 'Có lỗi xảy ra', 'error');
                    $('#submitBtn').prop('disabled', false).text('🎰 Quay số');
                    return;
                }

                // Simulate "Spinning" effect
                let count = 0;
                const interval = setInterval(() => {
                    const temp = String(Math.floor(Math.random() * 90000 + 10000));
                    $('#userNumber').val(temp);
                    count++;
                    if (count > 20) {
                        clearInterval(interval);

                        // Show real result
                        $('#userNumber').val(data.winning);

                        // Update Balance
                        $('.balance-display strong').text(data.balance + ' gtlm');

                        // Show message
                        Swal.fire({
                            title: data.win ? 'Chúc mừng!' : 'Rất tiếc!',
                            text: data.message + ' Kết quả đúng là: ' + data.winning,
                            icon: data.win ? 'success' : 'info',
                            confirmButtonText: 'Chơi tiếp'
                        });

                        // Update Stats
                        $('.stat-item.wins .value').text(data.stats.thang);
                        $('.stat-item.losses .value').text(data.stats.thua);

                        if (gameChart) {
                            gameChart.data.datasets[0].data = [data.stats.thang, data.stats.thua];
                            gameChart.update();
                        }

                        // Reload History
                        loadCsHistory();

                        $('#submitBtn').prop('disabled', false).text('🎰 Quay số');
                    }
                }, 50);
            }

            function loadCsHistory() {
                $.get('cs.php?action=get_history', function (data) {
                    if (data.success && data.history.length > 0) {
                        const tbody = $('#historyBody');
                        tbody.empty();
                        data.history.slice(0, 10).forEach((record, index) => {
                            tbody.append(`
                                <tr style="animation: slideIn 0.5s ease-out forwards; animation-delay: ${index * 0.05}s">
                                    <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                                    <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                                    <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">${record.Result || '-'}</td>
                                    <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                                    <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                                </tr>
                            `);
                        });
                    }
                });
            }

            $('#lotteryForm').on('submit', function (e) {
                e.preventDefault();
                const userNum = $('#userNumber').val();
                if (userNum.length !== 5) {
                    Swal.fire('Nhắc nhở', 'Vui lòng nhập đủ 5 chữ số', 'warning');
                    return;
                }

                $('#submitBtn').prop('disabled', true).text('Đang quay... 🎰');

                $.ajax({
                    url: 'cs.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    success: updateUI,
                    error: () => {
                        Swal.fire('Lỗi', 'Không thể kết nối máy chủ', 'error');
                        $('#submitBtn').prop('disabled', false).text('🎰 Quay số');
                    }
                });
            });

            // Load initial history
            loadCsHistory();

            // Auto initialize game effects
            if (typeof GameEffectsAuto !== 'undefined') {
                GameEffectsAuto.init();
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