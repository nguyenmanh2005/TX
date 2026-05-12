<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
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
$sqlStats = "SELECT COUNT(*) as total, SUM(CASE WHEN WinAmount > 0 THEN 1 ELSE 0 END) as wins FROM history_minesweeper WHERE Iduser = ?";
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

// AJAX handler
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false, 'message' => ''];

    if ($action === 'new_game') {
        $board = array_fill(0, 25, 0);
        $mines = [];
        while (count($mines) < 3) {
            $pos = rand(0, 24);
            if (!in_array($pos, $mines)) {
                $mines[] = $pos;
                $board[$pos] = -1;
            }
        }
        $_SESSION['mines_board'] = $board;
        $_SESSION['mines_revealed'] = [];
        $_SESSION['mines_cuoc'] = 0;
        $response = [
            'success' => true,
            'message' => '🆕 Ván mới bắt đầu! Hãy đặt cược.',
            'newBalance' => number_format($soDu, 0, ',', '.') . ' gtlm',
            'board' => array_fill(0, 25, '?')
        ];
    } elseif ($action === 'start') {
        $cuoc = (int) ($_POST['cuoc'] ?? 0);
        if ($cuoc <= 0 || $cuoc > $soDu) {
            $response['message'] = '⚠️ Số gtlm cược không hợp lệ hoặc không đủ Số Gtlm!';
        } else {
            $_SESSION['mines_cuoc'] = $cuoc;
            $soDu -= $cuoc;
            $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
            $capNhat->bind_param("di", $soDu, $userId);
            $capNhat->execute();
            $response = [
                'success' => true,
                'message' => '🎯 Đã đặt cược ' . number_format($cuoc, 0, ',', '.') . ' gtlm! Chúc may mắn!',
                'newBalance' => number_format($soDu, 0, ',', '.') . ' gtlm'
            ];
        }
    } elseif ($action === 'reveal') {
        $cell = (int) ($_POST['cell'] ?? -1);
        if ($_SESSION['mines_cuoc'] <= 0) {
            $response['message'] = '⚠️ Hãy đặt cược trước khi mở ô!';
        } elseif (in_array($cell, $_SESSION['mines_revealed'])) {
            $response['message'] = '⚠️ Ô này đã được mở!';
        } elseif ($cell < 0 || $cell >= 25) {
            $response['message'] = '⚠️ Ô không hợp lệ!';
        } else {
            $_SESSION['mines_revealed'][] = $cell;
            $board = $_SESSION['mines_board'];

            if ($board[$cell] === -1) {
                // Thua
                require_once '../game_history_helper.php';
                logGameHistory($conn, $userId, 'Minesweeper', $_SESSION['mines_cuoc'], 0, false);

                $historyStmt = $conn->prepare("INSERT INTO history_minesweeper (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, 'Thua', 0, NOW())");
                $historyStmt->bind_param("ii", $userId, $_SESSION['mines_cuoc']);
                $historyStmt->execute();

                $response = [
                    'success' => true,
                    'isGameOver' => true,
                    'isWin' => false,
                    'message' => '💣 BÙM! Bạn đã trúng mìn!',
                    'cellValue' => '💣',
                    'newBalance' => number_format($soDu, 0, ',', '.') . ' gtlm'
                ];
                // Reset session sau khi thua
                $_SESSION['mines_cuoc'] = 0;
            } else {
                // An toàn hoặc Thắng
                $safeCount = count($_SESSION['mines_revealed']);
                $totalSafe = 22;

                if ($safeCount >= $totalSafe) {
                    $thang = $_SESSION['mines_cuoc'] * 3;
                    $soDu += $thang;
                    $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
                    $capNhat->bind_param("di", $soDu, $userId);
                    $capNhat->execute();

                    require_once '../game_history_helper.php';
                    logGameHistory($conn, $userId, 'Minesweeper', $_SESSION['mines_cuoc'], $thang, true);

                    $historyStmt = $conn->prepare("INSERT INTO history_minesweeper (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, 'Thắng', ?, NOW())");
                    $historyStmt->bind_param("iii", $userId, $_SESSION['mines_cuoc'], $thang);
                    $historyStmt->execute();

                    $response = [
                        'success' => true,
                        'isGameOver' => true,
                        'isWin' => true,
                        'message' => '🎉 CHIẾN THẮNG! Bạn nhận được ' . number_format($thang, 0, ',', '.') . ' gtlm!',
                        'cellValue' => '💎',
                        'newBalance' => number_format($soDu, 0, ',', '.') . ' gtlm'
                    ];
                    $_SESSION['mines_cuoc'] = 0;
                } else {
                    $response = [
                        'success' => true,
                        'isGameOver' => false,
                        'message' => '✅ An toàn! Tiếp tục nào!',
                        'cellValue' => '💎'
                    ];
                }
            }
        }
    }
    echo json_encode($response);
    exit;
}

// Khởi tạo ván đầu tiên nếu chưa có
if (!isset($_SESSION['mines_board'])) {
    $board = array_fill(0, 25, 0);
    $mines = [];
    while (count($mines) < 3) {
        $pos = rand(0, 24);
        if (!in_array($pos, $mines)) {
            $mines[] = $pos;
            $board[$pos] = -1;
        }
    }
    $_SESSION['mines_board'] = $board;
    $_SESSION['mines_revealed'] = [];
    $_SESSION['mines_cuoc'] = 0;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Dò Mìn - AJAX Edition</title>
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
            padding: 30px;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            width: 95%;
            max-width: 600px;
            color: white;
            z-index: 1;
        }

        .game-title {
            font-size: 32px;
            margin-bottom: 20px;
            font-weight: 800;
            color: #fff;
        }

        .balance {
            font-size: 20px;
            color: #ffd700;
            margin-bottom: 30px;
        }

        .mines-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-bottom: 30px;
        }

        .mine-cell {
            aspect-ratio: 1;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            transition: all 0.3s;
            cursor: url('../img/tay.png'), pointer !important;
        }

        .mine-cell:hover:not(.revealed):not(.mine) {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .mine-cell.revealed {
            background: rgba(40, 167, 69, 0.3);
            border-color: #28a745;
            color: #28a745;
        }

        .mine-cell.mine {
            background: rgba(220, 53, 69, 0.3);
            border-color: #dc3545;
            animation: shake 0.5s;
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

        input[type="number"] {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 12px 20px;
            border-radius: 12px;
            color: white;
            font-size: 18px;
            width: 80%;
            margin-bottom: 20px;
            text-align: center;
        }

        .btn-game {
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 700;
            border: none;
            color: white;
            transition: 0.3s;
            cursor: url('../img/tay.png'), pointer !important;
        }

        .btn-start {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        }

        .btn-new {
            background: rgba(255, 255, 255, 0.1);
        }

        .btn-game:hover {
            transform: translateY(-2px);
            filter: brightness(1.2);
        }

        .btn-game:disabled {
            opacity: 0.5;
            cursor: not-allowed !important;
        }

        .thongbao {
            margin-top: 25px;
            padding: 15px;
            border-radius: 12px;
            font-weight: 600;
            min-height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .thongbao.thang {
            background: rgba(40, 167, 69, 0.1);
            color: #4ade80;
        }

        .thongbao.thua {
            background: rgba(220, 53, 69, 0.1);
            color: #ff6b6b;
        }

        .home-link {
            color: rgba(255, 255, 255, 0.5);
            text-decoration: none;
            font-size: 14px;
            margin-top: 20px;
            display: inline-block;
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
        <h1 class="game-title">💣 Dò Mìn AJAX</h1>
        <div class="balance">💰 Số Gtlm: <b id="balance-val"><?= number_format($soDu, 0, ',', '.') ?> gtlm</b></div>

        <div class="mines-grid" id="mines-grid">
            <?php for ($i = 0; $i < 25; $i++): ?>
                <button class="mine-cell" data-cell="<?= $i ?>"></button>
            <?php endfor; ?>
        </div>

        <div id="bet-section">
            <input type="number" id="bet-amount" placeholder="gtlm cược (gtlm)" min="1" max="<?= $soDu ?>">
            <div style="display: flex; justify-content: center; gap: 10px;">
                <button id="btn-start" class="btn-game btn-start">🎯 Đặt cược</button>
                <button id="btn-new" class="btn-game btn-new">🆕 Làm mới</button>
            </div>
        </div>

        <div id="status-box" class="thongbao">Sẵn sàng! Hãy đặt cược.</div>

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
            const grid = document.getElementById('mines-grid');
            const cells = document.querySelectorAll('.mine-cell');
            const btnStart = document.getElementById('btn-start');
            const btnNew = document.getElementById('btn-new');
            const statusBox = document.getElementById('status-box');
            const balanceVal = document.getElementById('balance-val');
            const betAmount = document.getElementById('bet-amount');

            let isGameActive = false;

            // Reset UI
            function resetUI(boardData) {
                cells.forEach((cell, idx) => {
                    cell.className = 'mine-cell';
                    cell.textContent = '';
                    cell.disabled = false;
                });
                isGameActive = false;
                betAmount.disabled = false;
                btnStart.disabled = false;
            }

            // Action Start
            btnStart.addEventListener('click', async () => {
                const amount = betAmount.value;
                if (!amount || amount <= 0) return Swal.fire('Lỗi', 'Vui lòng nhập gtlm cược!', 'error');

                try {
                    const formData = new FormData();
                    formData.append('cuoc', amount);
                    const res = await fetch('minesweeper.php?action=start', { method: 'POST', body: formData });
                    const data = await res.json();

                    if (data.success) {
                        isGameActive = true;
                        betAmount.disabled = true;
                        btnStart.disabled = true;
                        balanceVal.textContent = data.newBalance;
                        statusBox.textContent = data.message;
                        statusBox.className = 'thongbao';
                    } else {
                        Swal.fire('Thông báo', data.message, 'warning');
                    }
                } catch (e) {
                    console.error(e);
                }
            });

            // Action New Game
            btnNew.addEventListener('click', async () => {
                try {
                    const res = await fetch('minesweeper.php?action=new_game');
                    const data = await res.json();
                    if (data.success) {
                        resetUI();
                        statusBox.textContent = data.message;
                        statusBox.className = 'thongbao';
                    }
                } catch (e) {
                    console.error(e);
                }
            });

            // Action Reveal
            grid.addEventListener('click', async (e) => {
                const cell = e.target.closest('.mine-cell');
                if (!cell || !isGameActive || cell.classList.contains('revealed')) return;

                const cellIdx = cell.dataset.cell;
                try {
                    const formData = new FormData();
                    formData.append('cell', cellIdx);
                    const res = await fetch('minesweeper.php?action=reveal', { method: 'POST', body: formData });
                    const data = await res.json();

                    if (data.success) {
                        cell.textContent = data.cellValue;
                        cell.classList.add(data.cellValue === '💣' ? 'mine' : 'revealed');
                        statusBox.textContent = data.message;

                        if (data.isGameOver) {
                            isGameActive = false;
                            statusBox.className = 'thongbao ' + (data.isWin ? 'thang' : 'thua');
                            balanceVal.textContent = data.newBalance;

                            if (data.isWin && typeof confetti === 'function') {
                                confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } });
                            }

                            setTimeout(() => {
                                Swal.fire(data.isWin ? 'Thắng rồi!' : 'Bùm!', data.message, data.isWin ? 'success' : 'error');
                                if (!data.isWin) btnNew.click(); // Auto reset nếu thua
                            }, 500);
                        }
                    } else {
                        Swal.fire('Hệ thống', data.message, 'info');
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
    
    // Chart.js for minesweeper game
    const ctxMinesweeper = document.getElementById('gameChart');
    if (ctxMinesweeper) {
        const gameChart = new Chart(ctxMinesweeper.getContext('2d'), {
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
    window.addEventListener('load', loadMinesweeperHistory);



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
    
    // Chart.js for minesweeper game
    const ctxMinesweeper = document.getElementById('gameChart');
    if (ctxMinesweeper) {
        const gameChart = new Chart(ctxMinesweeper.getContext('2d'), {
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
    window.addEventListener('load', loadMinesweeperHistory);



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
    
    // Chart.js for minesweeper game
    const ctxMinesweeper = document.getElementById('gameChart');
    if (ctxMinesweeper) {
        const gameChart = new Chart(ctxMinesweeper.getContext('2d'), {
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
    window.addEventListener('load', loadMinesweeperHistory);



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
    
    // Chart.js for minesweeper game
    const ctxMinesweeper = document.getElementById('gameChart');
    if (ctxMinesweeper) {
        const gameChart = new Chart(ctxMinesweeper.getContext('2d'), {
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
    window.addEventListener('load', loadMinesweeperHistory);



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
    
    // Chart.js for minesweeper game
    const ctxMinesweeper = document.getElementById('gameChart');
    if (ctxMinesweeper) {
        const gameChart = new Chart(ctxMinesweeper.getContext('2d'), {
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
    window.addEventListener('load', loadMinesweeperHistory);



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
    
    // Chart.js for minesweeper game
    const ctxMinesweeper = document.getElementById('gameChart');
    if (ctxMinesweeper) {
        const gameChart = new Chart(ctxMinesweeper.getContext('2d'), {
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
    window.addEventListener('load', loadMinesweeperHistory);



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
    
    // Chart.js for minesweeper game
    const ctxMinesweeper = document.getElementById('gameChart');
    if (ctxMinesweeper) {
        const gameChart = new Chart(ctxMinesweeper.getContext('2d'), {
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
    window.addEventListener('load', loadMinesweeperHistory);



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
    
    // Chart.js for minesweeper game
    const ctxMinesweeper = document.getElementById('gameChart');
    if (ctxMinesweeper) {
        const gameChart = new Chart(ctxMinesweeper.getContext('2d'), {
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
    window.addEventListener('load', loadMinesweeperHistory);



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
    
    // Chart.js for minesweeper game
    const ctxMinesweeper = document.getElementById('gameChart');
    if (ctxMinesweeper) {
        const gameChart = new Chart(ctxMinesweeper.getContext('2d'), {
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
    window.addEventListener('load', loadMinesweeperHistory);



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
    
    // Chart.js for minesweeper game
    const ctxMinesweeper = document.getElementById('gameChart');
    if (ctxMinesweeper) {
        const gameChart = new Chart(ctxMinesweeper.getContext('2d'), {
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
    window.addEventListener('load', loadMinesweeperHistory);



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
    
    // Chart.js for minesweeper game
    const ctxMinesweeper = document.getElementById('gameChart');
    if (ctxMinesweeper) {
        const gameChart = new Chart(ctxMinesweeper.getContext('2d'), {
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
    window.addEventListener('load', loadMinesweeperHistory);



    // Improved history loading function
    async function loadMinesweeperHistory() {
        try {
            const response = await fetch('minesweeper.php?action=get_history', {
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
    
    // Chart.js for minesweeper game
    const ctxMinesweeper = document.getElementById('gameChart');
    if (ctxMinesweeper) {
        const gameChart = new Chart(ctxMinesweeper.getContext('2d'), {
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
    window.addEventListener('load', loadMinesweeperHistory);

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