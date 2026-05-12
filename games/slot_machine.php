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
    $id = $_SESSION['Iduser'];
    $sql = "SELECT * FROM history_ac WHERE Iduser = ? ORDER BY Time DESC LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    echo json_encode(['success' => true, 'history' => $history], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once '../load_theme.php';

$userId = $_SESSION['Iduser'];
$sql = "SELECT Money, Name FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];

// Stats
$gameThang = 0; $gameThua = 0;
$sqlStats = "SELECT COUNT(*) as total, SUM(CASE WHEN WinAmount > 0 THEN 1 ELSE 0 END) as wins FROM history_ac WHERE Iduser = ?";
$stmtStats = $conn->prepare($sqlStats);
$stmtStats->bind_param("i", $userId);
$stmtStats->execute();
$resStats = $stmtStats->get_result()->fetch_assoc();
$gameThang = $resStats['wins'] ?? 0;
$gameThua = ($resStats['total'] ?? 0) - $gameThang;

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $bet = (int)($_POST['bet'] ?? 0);
    if ($bet <= 0 || $bet > $money) {
        echo json_encode(['success' => false, 'message' => '⚠️ Số dư không đủ hoặc tiền cược không hợp lệ!']);
        exit;
    }

    $symbols = ['🍎', '🍌', '🍒', '🍇', '🍉', '🍍', '🥝', '🎭'];
    $r1 = $symbols[rand(0, 7)];
    $r2 = $symbols[rand(0, 7)];
    $r3 = $symbols[rand(0, 7)];

    $reward = 0;
    $status = 'lose';
    if ($r1 === $r2 && $r2 === $r3) {
        $multiplier = ($r1 === '🎭') ? 50 : 10;
        $reward = $bet * $multiplier;
        $status = 'win';
    } elseif ($r1 === $r2 || $r2 === $r3 || $r1 === $r3) {
        $reward = $bet * 2;
        $status = 'win';
    }

    $newBalance = $money - $bet + $reward;
    $conn->query("UPDATE users SET Money = $newBalance WHERE Iduser = $userId");

    $resultStr = "$r1|$r2|$r3";
    $stmtIns = $conn->prepare("INSERT INTO history_ac (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
    $stmtIns->bind_param("iisi", $userId, $bet, $resultStr, $reward);
    $stmtIns->execute();

    require_once '../game_history_helper.php';
    logGameHistoryWithAll($conn, $userId, 'Máy Cần Gạt Quay', $bet, $reward, $reward > 0);

    echo json_encode([
        'success' => true,
        'reel1' => $r1, 'reel2' => $r2, 'reel3' => $r3,
        'reward' => $reward, 'status' => $status,
        'money' => $newBalance,
        'message' => ($reward > 0) ? "🎉 THẮNG! Nhận " . number_format($reward) . " gtlm" : "😢 THUA RỒI!"
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Mega Slot Premium</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.7.3/sweetalert2.all.min.js"></script>
    <style>
        body { background: #0f0c29; color: white; font-family: sans-serif; text-align: center; padding: 40px; }
        .slot-machine {
            background: linear-gradient(180deg, #1f1c2c 0%, #928dab 100%);
            border-radius: 40px;
            padding: 50px;
            display: inline-block;
            border: 8px solid #f1c40f;
            box-shadow: 0 0 50px rgba(241, 196, 15, 0.3);
        }
        .reels { display: flex; gap: 20px; margin: 30px 0; }
        .reel {
            width: 120px; height: 120px;
            background: #fff; color: #000;
            font-size: 60px; line-height: 120px;
            border-radius: 20px;
            border: 5px solid #2c3e50;
        }
        .controls { margin-top: 30px; }
        input[type="number"] {
            padding: 15px; border-radius: 10px; border: none; font-size: 20px; width: 150px; text-align: center;
        }
        .btn-spin {
            padding: 15px 50px; border-radius: 30px; border: none;
            background: #e74c3c; color: white; font-size: 24px; font-weight: 900;
            cursor: pointer; transition: 0.2s;
        }
        .btn-spin:hover { transform: scale(1.1); background: #c0392b; }
        .history-box { margin-top: 50px; max-width: 800px; margin-left: auto; margin-right: auto; background: rgba(0,0,0,0.5); padding: 30px; border-radius: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
    </style>
</head>
<body>
    <div class="slot-machine">
        <h1 style="color: #f1c40f; font-size: 40px; margin: 0;">🎰 MEGA SLOT</h1>
        <div style="font-size: 20px; margin-bottom: 20px;">💰 Số dư: <span id="balance"><?= number_format($money) ?></span> gtlm</div>
        
        <div class="reels">
            <div class="reel" id="r1">🍎</div>
            <div class="reel" id="r2">🍒</div>
            <div class="reel" id="r3">🎭</div>
        </div>

        <div class="controls">
            <input type="number" id="bet" value="10000" min="1000">
            <button class="btn-spin" onclick="spin()">SPIN!</button>
        </div>
    </div>

    <div class="history-box">
        <h3>📋 Lịch sử chơi</h3>
        <table>
            <thead><tr><th>Cược</th><th>Kết quả</th><th>Thắng</th><th>Thời gian</th></tr></thead>
            <tbody id="history-body"></tbody>
        </table>
    </div>

    <script>
        function loadHistory() {
            $.get('slot_machine.php?action=get_history', function(data) {
                if (data.success) {
                    const tbody = $('#history-body');
                    tbody.empty();
                    data.history.forEach(h => {
                        tbody.append(`<tr><td>${parseInt(h.Bet).toLocaleString()}</td><td>${h.Result}</td><td style="color: ${parseInt(h.WinAmount) > 0 ? '#2ecc71' : '#e74c3c'}">${parseInt(h.WinAmount).toLocaleString()}</td><td>${h.Time}</td></tr>`);
                    });
                }
            });
        }

        function spin() {
            const bet = $('#bet').val();
            const btn = $('.btn-spin');
            btn.prop('disabled', true).text('...ING');

            $.post('slot_machine.php', { bet: bet }, function(data) {
                if (data.success) {
                    let count = 0;
                    const timer = setInterval(() => {
                        const sym = ['🍎', '🍌', '🍒', '🍇', '🍉', '🍍', '🥝', '🎭'];
                        $('#r1').text(sym[Math.floor(Math.random()*8)]);
                        $('#r2').text(sym[Math.floor(Math.random()*8)]);
                        $('#r3').text(sym[Math.floor(Math.random()*8)]);
                        count++;
                        if (count > 20) {
                            clearInterval(timer);
                            $('#r1').text(data.reel1);
                            $('#r2').text(data.reel2);
                            $('#r3').text(data.reel3);
                            $('#balance').text(data.money.toLocaleString());
                            loadHistory();
                            Swal.fire({ title: data.status === 'win' ? 'WIN!' : 'LOSE', text: data.message, icon: data.status === 'win' ? 'success' : 'info' });
                            btn.prop('disabled', false).text('SPIN!');
                        }
                    }, 50);
                } else {
                    Swal.fire('Lỗi', data.message, 'error');
                    btn.prop('disabled', false).text('SPIN!');
                }
            }, 'json');
        }
        loadHistory();
    </script>
</body>
</html>