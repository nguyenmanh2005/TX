<?php
session_start();

// Kiểm tra đăng nhập
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
    $sql = "SELECT * FROM history_cs WHERE Iduser = ? ORDER BY Time DESC LIMIT 10";
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

// Get statistics
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
    $betAmount = 5000;

    if (!preg_match('/^\d{5}$/', $userInput)) {
        $message = "❌ Vui lòng nhập đúng 5 chữ số (VD: 12345)";
    } elseif ($money < $betAmount) {
        $message = "❌ Bạn không đủ 5.000 gtlm để tham gia!";
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
        $prizeTable = [0, 5000, 20000, 100000, 1000000, 10000000];
        $prize = $prizeTable[$correct];
        $isWin = ($prize > 0);

        $newBalance = $money - $betAmount + $prize;
        
        if ($isWin) {
            $message = "🎉 Chúc mừng! Bạn trúng $correct số! Nhận thưởng: " . number_format($prize) . " gtlm.";
        } else {
            $message = "😢 Rất tiếc, không trúng số nào. Kết quả là: $winning. Thử lại nhé!";
        }

        // Cập nhật Số Gtlm
        $stmtUpd = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $stmtUpd->bind_param("di", $newBalance, $userId);
        $stmtUpd->execute();
        $stmtUpd->close();

        // Insert vào history_cs table
        $historyStmt = $conn->prepare("INSERT INTO history_cs (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $historyStmt->bind_param("iisi", $userId, $betAmount, $winning, $prize);
        $historyStmt->execute();
        $historyStmt->close();

        // Track progress
        require_once '../game_history_helper.php';
        logGameHistoryWithAll($conn, $userId, 'Xổ số Mini', $betAmount, $prize, $isWin);

        require_once '../user_progress_helper.php';
        up_add_xp($conn, $userId, 10);

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
    <meta charset="UTF-8">
    <title>Xổ số Mini - Premium Gaming</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.7.3/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            padding: 40px 20px;
        }
        .game-card {
            max-width: 600px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
        }
        .lottery-title {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(to right, #4facfe 0%, #00f2fe 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .balance-box {
            background: rgba(46, 204, 113, 0.1);
            border: 1px solid rgba(46, 204, 113, 0.3);
            border-radius: 16px;
            padding: 15px;
            margin: 20px 0;
            font-size: 18px;
            font-weight: 600;
            color: #2ecc71;
        }
        .input-group {
            margin: 30px 0;
        }
        .input-group label {
            display: block;
            margin-bottom: 10px;
            opacity: 0.8;
        }
        input[type="text"] {
            width: 250px;
            background: rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 15px;
            color: white;
            font-size: 32px;
            font-weight: 800;
            text-align: center;
            letter-spacing: 8px;
            transition: all 0.3s;
        }
        input[type="text"]:focus {
            outline: none;
            border-color: #4facfe;
            box-shadow: 0 0 20px rgba(79, 172, 254, 0.3);
        }
        .btn-spin {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border: none;
            border-radius: 12px;
            padding: 15px 40px;
            color: #1a1a2e;
            font-size: 20px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-spin:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(79, 172, 254, 0.4);
        }
        .btn-spin:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .stats-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 40px;
            max-width: 1000px;
            margin-left: auto;
            margin-right: auto;
        }
        .glass-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 25px;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .history-table th {
            text-align: left;
            opacity: 0.5;
            font-weight: 500;
            padding: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .history-table td {
            padding: 12px 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .win-text { color: #2ecc71; }
        .lose-text { color: #e74c3c; }
    </style>
</head>
<body>
    <div class="game-card">
        <h1 class="lottery-title">🎲 Xổ số Mini</h1>
        <p>Phí tham gia: 5.000 gtlm / lượt</p>
        
        <div class="balance-box">
            💰 Số dư: <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span> gtlm
        </div>

        <form id="lottery-form">
            <div class="input-group">
                <label>Nhập 5 chữ số may mắn của bạn:</label>
                <input type="text" name="user_number" id="user-number" maxlength="5" placeholder="00000" required>
            </div>
            <button type="submit" class="btn-spin" id="btn-submit">🎰 Quay số ngay</button>
        </form>

        <div style="margin-top: 20px;">
            <a href="../index.php" style="color: #aaa; text-decoration: none; font-size: 14px;">🏠 Quay lại trang chủ</a>
        </div>
    </div>

    <div class="stats-section">
        <div class="glass-box">
            <h3>📊 Thống kê chiến dịch</h3>
            <div style="display: flex; gap: 30px; margin: 20px 0;">
                <div>
                    <div style="opacity: 0.6; font-size: 12px;">THẮNG</div>
                    <div id="stat-wins" style="font-size: 24px; font-weight: 700; color: #2ecc71;"><?= $gameThang ?></div>
                </div>
                <div>
                    <div style="opacity: 0.6; font-size: 12px;">THUA</div>
                    <div id="stat-losses" style="font-size: 24px; font-weight: 700; color: #e74c3c;"><?= $gameThua ?></div>
                </div>
            </div>
            <canvas id="gameChart" style="max-height: 200px;"></canvas>
        </div>

        <div class="glass-box">
            <h3>📋 Nhật ký tham gia</h3>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Kết quả</th>
                        <th>Thắng</th>
                        <th>Thời gian</th>
                    </tr>
                </thead>
                <tbody id="history-body">
                    <!-- Loaded via AJAX -->
                </tbody>
            </table>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            let gameChart;
            const ctx = document.getElementById('gameChart').getContext('2d');

            function initChart(wins, losses) {
                if (gameChart) gameChart.destroy();
                gameChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Thắng', 'Thua'],
                        datasets: [{
                            data: [wins, losses],
                            backgroundColor: ['#2ecc71', '#e74c3c'],
                            borderColor: 'transparent'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } }
                    }
                });
            }

            function loadHistory() {
                $.get('lottery.php?action=get_history', function(data) {
                    if (data.success) {
                        const tbody = $('#history-body');
                        tbody.empty();
                        data.history.forEach(item => {
                            const isWin = parseInt(item.WinAmount) > 0;
                            tbody.append(`
                                <tr>
                                    <td>${item.Result}</td>
                                    <td class="${isWin ? 'win-text' : 'lose-text'}">${parseInt(item.WinAmount).toLocaleString()}</td>
                                    <td style="font-size: 11px; opacity: 0.5;">${item.Time.split(' ')[1]}</td>
                                </tr>
                            `);
                        });
                    }
                });
            }

            $('#lottery-form').on('submit', function(e) {
                e.preventDefault();
                const btn = $('#btn-submit');
                const userNum = $('#user-number').val();

                if (userNum.length !== 5) {
                    Swal.fire('Lỗi', 'Vui lòng nhập đủ 5 chữ số!', 'error');
                    return;
                }

                btn.prop('disabled', true).text('🎰 Đang quay...');

                $.ajax({
                    url: 'lottery.php',
                    type: 'POST',
                    data: { user_number: userNum },
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    success: function(data) {
                        if (data.success) {
                            // Hieu ung quay so gia
                            let count = 0;
                            const timer = setInterval(() => {
                                $('#user-number').val(Math.floor(Math.random() * 90000 + 10000));
                                count++;
                                if (count > 20) {
                                    clearInterval(timer);
                                    $('#user-number').val(data.winning);
                                    $('#balance-val').text(data.balance);
                                    $('#stat-wins').text(data.stats.thang);
                                    $('#stat-losses').text(data.stats.thua);
                                    initChart(data.stats.thang, data.stats.thua);
                                    loadHistory();

                                    Swal.fire({
                                        title: data.win ? 'CHIẾN THẮNG!' : 'RẤT TIẾC!',
                                        text: data.message,
                                        icon: data.win ? 'success' : 'info',
                                        background: '#1a1a2e',
                                        color: '#fff'
                                    });

                                    btn.prop('disabled', false).text('🎰 Quay số ngay');
                                }
                            }, 50);
                        } else {
                            Swal.fire('Lỗi', data.message, 'error');
                            btn.prop('disabled', false).text('🎰 Quay số ngay');
                        }
                    },
                    error: function() {
                        Swal.fire('Lỗi', 'Không thể kết nối máy chủ!', 'error');
                        btn.prop('disabled', false).text('🎰 Quay số ngay');
                    }
                });
            });

            initChart(<?= $gameThang ?>, <?= $gameThua ?>);
            loadHistory();
        });
    </script>
</body>
</html>