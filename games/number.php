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

            // Lưu lịch sử (optional but recommended for backend consistency)
            require_once '../game_history_helper.php';
            logGameHistory($conn, $userId, 'GuessNumber', $cuoc, $thang, $isWin);
        
        // Insert vào history_number table
        $historyStmt = $conn->prepare("INSERT INTO history_number (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        if ($historyStmt) {
            $result = $_POST['result'] ?? 'Unknown';
            $bet = (int)($_POST['bet'] ?? 0);
            $winAmount = (int)($_POST['win'] ?? $reward ?? 0);
            $userId = $_SESSION['Iduser'] ?? 0;
            $historyStmt->bind_param("iisi", $userId, $bet, $result, $winAmount);
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
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <link rel="stylesheet" href="../assets/css/game-effects.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <style>
        body {
            position: relative;
            cursor: url('../img/chuot.png'), auto !important;
            font-family: 'Segoe UI', sans-serif;
            text-align: center;
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            padding: 50px;
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .game-box {
            background: rgba(40, 44, 52, 0.95);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            width: 95%;
            max-width: 550px;
            color: white;
            z-index: 1;
        }

        .game-title {
            font-size: 32px;
            margin-bottom: 10px;
            font-weight: 800;
            color: #fff;
        }

        .balance {
            font-size: 20px;
            color: #ffd700;
            margin-bottom: 25px;
        }

        .hint-box {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 12px;
            font-size: 15px;
            margin-bottom: 25px;
            color: rgba(255, 255, 255, 0.8);
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
            color: rgba(255, 255, 255, 0.9);
            padding-left: 5px;
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
        }

        input:focus {
            border-color: #ffd700;
            outline: none;
            background: rgba(255, 255, 255, 0.15);
        }

        .btn-game {
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 700;
            border: none;
            color: white;
            transition: 0.3s;
            cursor: url('../img/tay.png'), pointer !important;
            width: 100%;
            margin-top: 10px;
        }

        .btn-guess {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            font-size: 18px;
            letter-spacing: 1px;
        }

        .btn-new {
            background: rgba(255, 255, 255, 0.1);
            width: auto;
            padding: 10px 20px;
            font-size: 14px;
        }

        .btn-game:hover {
            transform: translateY(-2px);
            filter: brightness(1.2);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .status-msg {
            margin-top: 25px;
            padding: 18px;
            border-radius: 12px;
            font-weight: 600;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.4s ease;
        }

        .status-msg.win {
            background: rgba(40, 167, 69, 0.15);
            color: #4ade80;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .status-msg.lose {
            background: rgba(220, 53, 69, 0.15);
            color: #ff6b6b;
            border: 1px solid rgba(220, 53, 69, 0.3);
            animation: shake 0.5s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            20% {
                transform: translateX(-5px);
            }

            40% {
                transform: translateX(5px);
            }

            60% {
                transform: translateX(-5px);
            }

            80% {
                transform: translateX(5px);
            }
        }

        .home-link {
            color: rgba(255, 255, 255, 0.5);
            text-decoration: none;
            font-size: 14px;
            margin-top: 20px;
            display: inline-block;
            transition: 0.3s;
        }

        .home-link:hover {
            color: white;
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


    <div class="game-box">
        <h1 class="game-title">🎯 Con Số May Mắn</h1>
        <div class="balance">💰 Số Gtlm: <b id="balance-val"><?= number_format($soDu, 0, ',', '.') ?> gtlm</b></div>

        <div class="hint-box">
            💡 Gợi ý: Đoán đúng con số bí mật (1-100) để nhận **x10** gtlm thưởng!<br>
            Đoán gần đúng (sai lệch ≤ 5) vẫn nhận được **x2** gtlm thưởng.
        </div>

        <div id="game-form">
            <div class="input-group">
                <label>🎯 Con số bạn chọn (1-100):</label>
                <input type="number" id="guess-number" placeholder="Ví dụ: 50" min="1" max="100">
            </div>
            <div class="input-group">
                <label>💰 Số gtlm cược (gtlm):</label>
                <input type="number" id="bet-amount" placeholder="Ví dụ: 10000" min="1" max="<?= $soDu ?>">
            </div>
            <button id="btn-guess" class="btn-game btn-guess">🎯 ĐOÁN NGAY</button>
            <div style="margin-top: 15px;">
                <button id="btn-new" class="btn-game btn-new">🆕 Làm mới game</button>
            </div>
        </div>

        <div id="status-msg" class="status-msg" style="display: none;"></div>

        <a href="../index.php" class="home-link">🏠 Quay lại trang chủ</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Three.js Background
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

        document.addEventListener('DOMContentLoaded', function () {
            const btnGuess = document.getElementById('btn-guess');
            const btnNew = document.getElementById('btn-new');
            const numInput = document.getElementById('guess-number');
            const betInput = document.getElementById('bet-amount');
            const statusMsg = document.getElementById('status-msg');
            const balanceVal = document.getElementById('balance-val');

            // Handle Guess
            btnGuess.addEventListener('click', async () => {
                const num = numInput.value;
                const bet = betInput.value;

                if (!num || num < 1 || num > 100) return Swal.fire('Lỗi', 'Vui lòng chọn số từ 1 đến 100!', 'error');
                if (!bet || bet <= 0) return Swal.fire('Lỗi', 'Vui lòng nhập gtlm cược hợp lệ!', 'error');

                btnGuess.disabled = true;
                statusMsg.style.display = 'none';

                try {
                    const formData = new FormData();
                    formData.append('so', num);
                    formData.append('cuoc', bet);

                    const res = await fetch('number.php?action=guess', { method: 'POST', body: formData });
                    const data = await res.json();

                    btnGuess.disabled = false;

                    if (data.success) {
                        balanceVal.textContent = data.newBalance;
                        statusMsg.textContent = data.message;
                        statusMsg.className = 'status-msg ' + (data.isWin ? 'win' : 'lose');
                        statusMsg.style.display = 'flex';

                        if (data.isWin && typeof confetti === 'function') {
                            confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } });
                        }
                    } else {
                        Swal.fire('Hệ thống', data.message, 'warning');
                    }
                } catch (e) {
                    btnGuess.disabled = false;
                    console.error(e);
                }
            });

            // Handle New Game
            btnNew.addEventListener('click', async () => {
                try {
                    const res = await fetch('number.php?action=new_game');
                    const data = await res.json();
                    if (data.success) {
                        numInput.value = '';
                        statusMsg.style.display = 'flex';
                        statusMsg.className = 'status-msg';
                        statusMsg.textContent = data.message;
                        Swal.fire('Thành công', data.message, 'success');
                    }
                } catch (e) {
                    console.error(e);
                }
            });
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
    

    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for number game
    const ctxNumber = document.getElementById('gameChart');
    if (ctxNumber) {
        const gameChart = new Chart(ctxNumber.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
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
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadNumberHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for number game
    const ctxNumber = document.getElementById('gameChart');
    if (ctxNumber) {
        const gameChart = new Chart(ctxNumber.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
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
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadNumberHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for number game
    const ctxNumber = document.getElementById('gameChart');
    if (ctxNumber) {
        const gameChart = new Chart(ctxNumber.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
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
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadNumberHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for number game
    const ctxNumber = document.getElementById('gameChart');
    if (ctxNumber) {
        const gameChart = new Chart(ctxNumber.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
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
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadNumberHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for number game
    const ctxNumber = document.getElementById('gameChart');
    if (ctxNumber) {
        const gameChart = new Chart(ctxNumber.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
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
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadNumberHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for number game
    const ctxNumber = document.getElementById('gameChart');
    if (ctxNumber) {
        const gameChart = new Chart(ctxNumber.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
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
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadNumberHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for number game
    const ctxNumber = document.getElementById('gameChart');
    if (ctxNumber) {
        const gameChart = new Chart(ctxNumber.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
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
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadNumberHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for number game
    const ctxNumber = document.getElementById('gameChart');
    if (ctxNumber) {
        const gameChart = new Chart(ctxNumber.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
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
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadNumberHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for number game
    const ctxNumber = document.getElementById('gameChart');
    if (ctxNumber) {
        const gameChart = new Chart(ctxNumber.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
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
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadNumberHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for number game
    const ctxNumber = document.getElementById('gameChart');
    if (ctxNumber) {
        const gameChart = new Chart(ctxNumber.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
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
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadNumberHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for number game
    const ctxNumber = document.getElementById('gameChart');
    if (ctxNumber) {
        const gameChart = new Chart(ctxNumber.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
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
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadNumberHistory);



    // Improved history loading function
    async function loadNumberHistory() {
        try {
            const response = await fetch('number.php?action=get_history', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for number game
    const ctxNumber = document.getElementById('gameChart');
    if (ctxNumber) {
        const gameChart = new Chart(ctxNumber.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
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
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadNumberHistory);

</script>













<div class="bottom-section">
    <div class="history-box">
        <h3>📋 Lịch sử chơi (10 lần gần nhất)</h3>
        <table border="1" cellpadding="10" id="historyTable">
            <thead>
                <tr style="background: rgba(255, 255, 255, 0.1);">
                    <th style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;">ID</th>
                    <th style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;">Cược</th>
                    <th style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;">Kết quả</th>
                    <th style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;">Thắng</th>
                    <th style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;">Thời gian</th>
                </tr>
            </thead>
            <tbody id="historyBody">
                <tr><td colspan="5" style="text-align: center; padding: 15px; color: #aaa;">Chưa có lượt chơi nào</td></tr>
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

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

</body>

</html>