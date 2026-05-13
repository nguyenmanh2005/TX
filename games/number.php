<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}

require '../db_connect.php';
require_once '../load_theme.php';

/** @var int $particleCount */
/** @var float $particleSize */
/** @var string $particleColor */
/** @var float $particleOpacity */
/** @var int $shapeCount */
/** @var array $shapeColors */
/** @var float $shapeOpacity */
/** @var array $bgGradient */
/** @var string $bgGradientCSS */
require_once '../game_history_helper.php';

$userId = $_SESSION['Iduser'];
$sql = "SELECT Money, Name FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS history_number (Id INT AUTO_INCREMENT PRIMARY KEY, Iduser INT NOT NULL, Bet DECIMAL(30,2) NOT NULL, Result VARCHAR(255) NOT NULL, WinAmount DECIMAL(30,2) NOT NULL, Time DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Get statistics from database for chart
$gameThang = 0;
$gameThua = 0;
$sqlStats = "SELECT COUNT(*) as total, SUM(CASE WHEN WinAmount > 0 THEN 1 ELSE 0 END) as wins FROM history_number WHERE Iduser = ?";
$stmtStats = $conn->prepare($sqlStats);
$stmtStats->bind_param("i", $userId);
$stmtStats->execute();
$resultStats = $stmtStats->get_result();
if ($rowStats = $resultStats->fetch_assoc()) {
    $gameThang = $rowStats['wins'] ?? 0;
    $gameThua = ($rowStats['total'] ?? 0) - $gameThang;
}
$stmtStats->close();


$soDu = $user['Money'];
$tenNguoiChoi = $user['Name'];

// Khởi tạo số bí mật nếu chưa có
if (!isset($_SESSION['so_bi_mat'])) {
    $_SESSION['so_bi_mat'] = rand(1, 100);
}

// AJAX handler
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false, 'message' => ''];

    if ($action === 'new_game') {
        $_SESSION['so_bi_mat'] = rand(1, 100);
        $response = [
            'success' => true,
            'message' => '🆕 Trò chơi mới đã bắt đầu! Đoán số từ 1 đến 100.',
            'newBalance' => number_format($soDu, 0, ',', '.') . ' gtlm'
        ];
    } elseif ($action === 'guess') {
        $chon = (int) ($_POST['so'] ?? 0);
        $cuoc = (int) ($_POST['cuoc'] ?? 0);

        if ($cuoc <= 0 || $cuoc > $soDu) {
            $response['message'] = '⚠️ Số gtlm cược không hợp lệ hoặc không đủ Số Gtlm!';
        } elseif ($chon < 1 || $chon > 100) {
            $response['message'] = '❌ Vui lòng chọn số từ 1 đến 100!';
        } else {
            $soBiMat = $_SESSION['so_bi_mat'];
            $khoangCach = abs($chon - $soBiMat);
            $thang = 0;
            $isWin = false;

            if ($chon === $soBiMat) {
                $thang = $cuoc * 10;
                $isWin = true;
                $msg = "🎉 CHÍNH XÁC! Bạn thắng " . number_format($thang, 0, ',', '.') . " gtlm!";
                $_SESSION['so_bi_mat'] = rand(1, 100); // Reset game mới
            } elseif ($khoangCach <= 5) {
                $thang = $cuoc * 2;
                $isWin = true;
                $msg = "🔥 Rất gần! Cách " . $khoangCach . " đơn vị. Thắng " . number_format($thang, 0, ',', '.') . " gtlm!";
            } else {
                $soDu -= $cuoc;
                $isWin = false;
                $huong = ($chon < $soBiMat) ? "LỚN HƠN" : "NHỎ HƠN";
                $msg = "❌ Sai rồi! Số bí mật " . $huong . " số bạn chọn. Mất " . number_format($cuoc, 0, ',', '.') . " gtlm.";
            }

            if ($isWin) {
                $soDu += $thang;
            }

            // Cập nhật database
            $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
            $capNhat->bind_param("di", $soDu, $userId);
            $capNhat->execute();

            // Lưu lịch sử
            logGameHistoryWithAll($conn, $userId, 'Number Guess', $cuoc, $thang, $isWin);
        
            // Insert vào history_number table
            $historyStmt = $conn->prepare("INSERT INTO history_number (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
            if ($historyStmt) {
                $resultText = "Guess: $chon (Secret: $soBiMat)";
                $profit = $thang - $cuoc;
                $historyStmt->bind_param("idss", $userId, $cuoc, $resultText, $profit);
                $historyStmt->execute();
                $historyStmt->close();
            }

            $response = [
                'success' => true,
                'isWin' => $isWin,
                'message' => $msg,
                'newBalance' => number_format($soDu, 0, ',', '.') . ' gtlm'
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
    <title>Con Số May Mắn - Premium AJAX</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/canvas-confetti/1.6.0/confetti.browser.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <link rel="stylesheet" href="../assets/css/game-effects.css">
    <style>
        body {
            margin: 0;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .main-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 20px;
        }

        .game-box {
            background: rgba(40, 44, 52, 0.8);
            backdrop-filter: blur(20px);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            width: 100%;
            max-width: 550px;
            text-align: center;
            margin-bottom: 40px;
        }

        .game-title {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, #ffd700 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .balance {
            font-size: 20px;
            color: #ffd700;
            margin-bottom: 25px;
            font-weight: 600;
        }

        .hint-box {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 25px;
            color: rgba(255, 255, 255, 0.7);
            border: 1px dashed rgba(255, 255, 255, 0.2);
        }

        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            opacity: 0.9;
        }

        input[type="number"] {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 14px 20px;
            border-radius: 12px;
            color: white;
            font-size: 18px;
            width: 100%;
            box-sizing: border-box;
            transition: 0.3s;
            outline: none;
        }

        input:focus {
            border-color: #ffd700;
            background: rgba(255, 255, 255, 0.15);
        }

        .btn-game {
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 700;
            border: none;
            color: white;
            transition: 0.3s;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-guess {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
        }

        .btn-guess:hover {
            transform: translateY(-2px);
            filter: brightness(1.1);
        }

        .btn-new {
            background: rgba(255, 255, 255, 0.1);
            font-size: 14px;
            margin-top: 15px;
        }

        .status-msg {
            margin-top: 25px;
            padding: 18px;
            border-radius: 12px;
            font-weight: 600;
            display: none;
            animation: slideUp 0.4s ease;
        }

        .status-msg.win { background: rgba(40, 167, 69, 0.2); border: 1px solid #28a745; color: #4ade80; }
        .status-msg.lose { background: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #ff6b6b; }

        .footer-container {
            width: 100%;
            max-width: 1100px;
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
        }

        @media (max-width: 992px) {
            .footer-container { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>
    <div class="main-container">
        <div class="game-box">
            <h1 class="game-title">🎯 Con Số May Mắn</h1>
            <div class="balance">💰 Số dư: <b id="balance-val"><?= number_format($soDu, 0, ',', '.') ?> gtlm</b></div>

            <div class="hint-box">
                💡 Gợi ý: Đoán đúng số bí mật (1-100) nhận <b>x10</b> thưởng!<br>
                Đoán gần đúng (sai lệch ≤ 5) nhận <b>x2</b> thưởng.
            </div>

            <div id="game-form">
                <div class="input-group">
                    <label>🎯 Con số bạn chọn (1-100):</label>
                    <input type="number" id="guess-number" placeholder="Ví dụ: 50" min="1" max="100">
                </div>
                <div class="input-group">
                    <label>💰 Số gtlm cược:</label>
                    <input type="number" id="bet-amount" placeholder="Ví dụ: 1000" min="1" max="<?= $soDu ?>">
                </div>
                <button id="btn-guess" class="btn-game btn-guess">🎯 ĐOÁN NGAY</button>
                <button id="btn-new" class="btn-game btn-new">🆕 Làm mới game</button>
            </div>

            <div id="status-msg" class="status-msg"></div>

            <div style="margin-top: 30px;">
                <a href="../index.php" style="color: rgba(255,255,255,0.5); text-decoration: none; font-size: 14px;">← Quay về Dashboard</a>
            </div>
        </div>

        <div class="footer-container">
            <div class="game-box" style="max-width: none; text-align: left; padding: 2rem;">
                <h3 style="margin-top: 0; color: #ffd700;"><i class="fas fa-history"></i> LỊCH SỬ CHƠI</h3>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                        <thead>
                            <tr style="border-bottom: 2px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.6);">
                                <th style="padding: 12px 8px; text-align: left;">Mã</th>
                                <th style="padding: 12px 8px; text-align: right;">Cược</th>
                                <th style="padding: 12px 8px;">Kết Quả</th>
                                <th style="padding: 12px 8px; text-align: right;">Thắng</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody">
                            <tr><td colspan="4" style="text-align: center; padding: 20px; opacity: 0.5;">Đang tải...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="game-box" style="max-width: none; padding: 2rem;">
                <h3 style="margin-top: 0; color: #ffd700;">THỐNG KÊ</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div style="background: rgba(74, 222, 128, 0.1); border-radius: 10px; padding: 15px;">
                        <div style="font-size: 10px; color: #4ade80;">THẮNG</div>
                        <div style="font-size: 20px; font-weight: bold;"><?= $gameThang ?></div>
                    </div>
                    <div style="background: rgba(255, 107, 107, 0.1); border-radius: 10px; padding: 15px;">
                        <div style="font-size: 10px; color: #ff6b6b;">THUA</div>
                        <div style="font-size: 20px; font-weight: bold;"><?= $gameThua ?></div>
                    </div>
                </div>
                <canvas id="gameChart" style="max-height: 150px;"></canvas>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        $(document).ready(function () {
            const btnGuess = $('#btn-guess');
            const statusMsg = $('#status-msg');

            btnGuess.on('click', async () => {
                const num = $('#guess-number').val();
                const bet = $('#bet-amount').val();

                if (!num || num < 1 || num > 100) return Swal.fire('Lỗi', 'Chọn số từ 1-100', 'error');
                if (!bet || bet <= 0) return Swal.fire('Lỗi', 'Cược không hợp lệ', 'error');

                btnGuess.prop('disabled', true).text('ĐANG XỬ LÝ...');
                statusMsg.hide();

                try {
                    const res = await $.post('number.php?action=guess', { so: num, cuoc: bet });
                    btnGuess.prop('disabled', false).text('🎯 ĐOÁN NGAY');

                    if (res.success) {
                        $('#balance-val').text(res.newBalance);
                        statusMsg.text(res.message).removeClass('win lose').addClass(res.isWin ? 'win' : 'lose').show();
                        if (res.isWin) confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } });
                        loadNumberHistory();
                    } else {
                        Swal.fire('Lỗi', res.message, 'warning');
                    }
                } catch (e) {
                    btnGuess.prop('disabled', false).text('🎯 ĐOÁN NGAY');
                    console.error(e);
                }
            });

            $('#btn-new').on('click', async () => {
                try {
                    const res = await $.getJSON('number.php?action=new_game');
                    if (res.success) {
                        $('#guess-number').val('');
                        statusMsg.hide();
                        Swal.fire('Thành công', res.message, 'success');
                    }
                } catch (e) { console.error(e); }
            });

            loadNumberHistory();
            initChart();
        });

        async function loadNumberHistory() {
            try {
                const res = await $.getJSON('../api_game_history.php?game=Number Guess');
                if (res.success && res.history) {
                    const tbody = $('#historyTableBody');
                    if (res.history.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px; opacity: 0.5;">Chưa có lịch sử</td></tr>';
                        return;
                    }
                    tbody.html(res.history.slice(0, 10).map(record => `
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <td style="padding: 12px 8px; opacity: 0.7;">#${record.id}</td>
                            <td style="padding: 12px 8px; text-align: right;">${parseInt(record.bet_amount).toLocaleString()}</td>
                            <td style="padding: 12px 8px;"><span style="color: ${record.is_win ? '#4ade80' : '#ff6b6b'}">${record.is_win ? 'THẮNG' : 'THUA'}</span></td>
                            <td style="padding: 12px 8px; text-align: right;">${parseInt(record.win_amount).toLocaleString()}</td>
                        </tr>
                    `).join(''));
                }
            } catch (e) { console.error(e); }
        }

        function initChart() {
            const ctx = document.getElementById('gameChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Thắng', 'Thua'],
                        datasets: [{
                            data: [<?= $gameThang ?>, <?= $gameThua ?>],
                            backgroundColor: ['rgba(74, 222, 128, 0.6)', 'rgba(255, 107, 107, 0.6)'],
                            borderColor: ['#4ade80', '#ff6b6b'],
                            borderWidth: 1
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: '#fff', font: { size: 10 } } } } }
                });
            }
        }

        (function () {
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
            script.src = '../threejs-background.js';
            document.head.appendChild(script);
        })();
    </script>
</body>
</html>