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
    $sql = "SELECT * FROM history_ruttham WHERE Iduser = ? ORDER BY Time DESC LIMIT 20";
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
$sqlStats = "SELECT COUNT(*) as total, SUM(CASE WHEN WinAmount > 0 THEN 1 ELSE 0 END) as wins FROM history_ruttham WHERE Iduser = ?";
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

// --- AJAX HANDLER ---
if (isset($_GET['action']) && $_GET['action'] === 'rut_tham') {
    header('Content-Type: application/json');
    $cost = 50000;

    if ($soDu < $cost) {
        echo json_encode(['success' => false, 'message' => '⚠️ Số Gtlm không đủ! Cần ' . number_format($cost) . ' gtlm.']);
        exit;
    }

    $bags = [
        ["label" => "Trượt!", "reward" => 0, "chance" => 50],
        ["label" => "10.000 gtlm", "reward" => 10000, "chance" => 20],
        ["label" => "50.000 gtlm", "reward" => 50000, "chance" => 12],
        ["label" => "100.000 gtlm", "reward" => 100000, "chance" => 12],
        ["label" => "200.000 gtlm", "reward" => 270000, "chance" => 3],
        ["label" => "500.000 gtlm", "reward" => 500000, "chance" => 2],
        ["label" => "1.000.000 gtlm", "reward" => 1000000, "chance" => 1],
    ];

    $rand = mt_rand(1, 100);
    $sum = 0;
    $finalBag = $bags[0];
    foreach ($bags as $bag) {
        $sum += $bag['chance'];
        if ($rand <= $sum) {
            $finalBag = $bag;
            break;
        }
    }

    $rewardAmount = $finalBag['reward'];
    $newBalance = $soDu - $cost + $rewardAmount;

    $conn->query("UPDATE users SET Money = $newBalance WHERE Iduser = $userId");

    if (file_exists('../game_history_helper.php')) {
        require_once '../game_history_helper.php';
        logGameHistoryWithAll($conn, $userId, 'Rút Thăm', $cost, ($rewardAmount > 0 ? $rewardAmount : 0), $rewardAmount > 0);
    }

    echo json_encode([
        'success' => true,
        'rewardLabel' => $finalBag['label'],
        'rewardAmount' => $rewardAmount,
        'newBalance' => number_format($newBalance) . ' gtlm',
        'message' => $rewardAmount > 0 ? "🎉 CHÚC MỪNG! Bạn nhận được " . $finalBag['label'] . "!" : "😢 Rất tiếc, túi này không có gì!"
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Lucky Draw Premium - Săn Kho Báu</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Poppins:wght@400;600;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --gold: #ffd700;
            --emerald: #27ae60;
            --dark-bg: #072a1a;
        }

        body {
            margin: 0;
            cursor: url('../img/chuot.png'), auto !important;
            font-family: 'Poppins', sans-serif;
            background:
                <?= $bgGradientCSS ?>
            ;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            color: white;
            overflow-x: hidden;
        }

        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        .header-bar {
            width: 100%;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 215, 0, 0.2);
            box-sizing: border-box;
        }

        .logo {
            font-family: 'Cinzel', serif;
            font-size: 26px;
            color: var(--gold);
            letter-spacing: 4px;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
        }

        .token-val {
            background: rgba(0, 0, 0, 0.4);
            padding: 8px 25px;
            border-radius: 30px;
            border: 1px solid var(--gold);
            color: var(--gold);
            font-weight: 800;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.1);
        }

        .game-zone {
            margin-top: 50px;
            text-align: center;
            max-width: 1000px;
            width: 100%;
            padding: 0 20px;
        }

        .cost-tag {
            font-size: 16px;
            color: #aaa;
            margin-bottom: 30px;
            letter-spacing: 2px;
        }

        .cost-tag b {
            color: var(--gold);
        }

        .bags-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin: 40px 0;
        }

        .bag-box {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 30px;
            padding: 40px;
            cursor: pointer;
            transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .bag-box:hover {
            transform: translateY(-15px) scale(1.05);
            border-color: var(--gold);
            background: rgba(255, 215, 0, 0.1);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        }

        .bag-icon {
            font-size: 70px;
            margin-bottom: 15px;
            filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.3));
            transition: 0.3s;
        }

        .bag-label {
            font-size: 14px;
            font-weight: 800;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .bag-box.opening .bag-icon {
            animation: shake 0.5s infinite;
        }

        @keyframes shake {
            0% {
                transform: rotate(0);
            }

            25% {
                transform: rotate(10deg);
            }

            50% {
                transform: rotate(0);
            }

            75% {
                transform: rotate(-10deg);
            }

            100% {
                transform: rotate(0);
            }
        }

        .status-marquee {
            position: fixed;
            bottom: 30px;
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid var(--gold);
            color: var(--gold);
            padding: 10px 40px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 1px;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.1);
        }

        .btn-home {
            color: rgba(255, 255, 255, 0.4);
            text-decoration: none;
            font-size: 14px;
            margin-top: 40px;
            display: inline-block;
            transition: 0.3s;
        }

        .btn-home:hover {
            color: var(--gold);
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
</head>

<body>


    <header class="header-bar">
        <div class="logo">LUCKY DRAW PREMIUM</div>
        <div class="token-val">💰 <span id="balance-val"><?= number_format($soDu) ?> gtlm</span></div>
        <div style="font-size: 13px; color: #666;">PLAYER: <b><?= htmlspecialchars($tenNguoiChoi) ?></b></div>
    </header>

    <div class="game-zone">
        <div class="cost-tag">CHI PHÍ MỖI LẦN MỞ: <b>50.000 gtlm</b></div>

        <div class="bags-grid" id="bags-container">
            <?php for ($i = 1; $i <= 6; $i++): ?>
                <div class="bag-box" onclick="drawBag(this, <?= $i ?>)">
                    <div class="bag-icon">🎁</div>
                    <div class="bag-label">Túi số <?= $i ?></div>
                </div>
            <?php endfor; ?>
        </div>

        <a href="../index.php" class="btn-home">🏠 QUAY LẠI SẢNH CHỜ</a>
    </div>

    <div class="status-marquee" id="status-msg">CHỌN MỘT TÚI QUÀ BẤT KỲ ĐỂ THỬ VẬN MAY CỦA BẠN!</div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function () {
            window.themeConfig = { particleCount: <?= $particleCount ?>, particleSize: <?= $particleSize ?>, particleColor: '<?= $particleColor ?>', particleOpacity: <?= $particleOpacity ?>, shapeCount: <?= $shapeCount ?>, shapeColors: <?= json_encode($shapeColors) ?>, shapeOpacity: <?= $shapeOpacity ?>, bgGradient: <?= json_encode($bgGradient) ?> };
            const script = document.createElement('script'); script.src = '../threejs-background.js'; document.head.appendChild(script);
        })();

        let isLocked = false;

        async function drawBag(box, num) {
            if (isLocked) return;
            isLocked = true;

            const icon = box.querySelector('.bag-icon');
            const status = document.getElementById('status-msg');

            box.classList.add('opening');
            status.textContent = `ĐANG KIỂM TRA TÚI SỐ ${num}... CHỜ CHÚT NHÉ!`;

            try {
                const res = await fetch('ruttham.php?action=rut_tham');
                const data = await res.json();

                if (data.success) {
                    setTimeout(() => {
                        box.classList.remove('opening');

                        if (data.rewardAmount > 0) {
                            icon.textContent = "💰";
                            if (typeof confetti === 'function') {
                                confetti({ particleCount: 200, spread: 80, origin: { y: 0.6 }, colors: ['#ffd700', '#ffffff'] });
                            }
                            Swal.fire({ title: '🎉 CHIẾN THẮNG!', text: data.message, icon: 'success', confirmButtonColor: '#27ae60' });
                        } else {
                            icon.textContent = "💨";
                            Swal.fire({ title: 'Rất tiếc', text: data.message, icon: 'error', confirmButtonColor: '#e74c3c' });
                        }

                        document.getElementById('balance-val').textContent = data.newBalance;
                        status.textContent = data.message;

                        // Reset túi sau 3 giây
                        setTimeout(() => {
                            icon.textContent = "🎁";
                            isLocked = false;
                            status.textContent = 'CHỌN MỘT TÚI QUÀ BẤT KỲ ĐỂ THỬ VẬN MAY CỦA BẠN!';
                        }, 3000);

                    }, 2000);
                } else {
                    Swal.fire('Lỗi', data.message, 'error');
                    box.classList.remove('opening');
                    isLocked = false;
                    status.textContent = 'CHỌN MẢNG CƯỢC KHÁC HOẶC NẠP THÊM VÀNG.';
                }
            } catch (e) {
                console.error(e);
                box.classList.remove('opening');
                isLocked = false;
            }
        }
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

    <div class="bottom-section">
        <div class="history-box">
            <h3>📋 Lịch sử mở quà (20 lần gần nhất)</h3>
            <div class="table-responsive">
                <table class="w-full text-left border-collapse" id="historyTable">
                    <thead>
                        <tr class="bg-white/10 text-yellow-400">
                            <th class="p-3 border border-white/10">ID</th>
                            <th class="p-3 border border-white/10 text-right">Cược</th>
                            <th class="p-3 border border-white/10">Kết quả</th>
                            <th class="p-3 border border-white/10 text-right">Thắng</th>
                            <th class="p-3 border border-white/10 text-right">Thời gian</th>
                        </tr>
                    </thead>
                    <tbody id="historyBody">
                        <tr>
                            <td colspan="5" class="text-center p-6 text-gray-400 italic">Chưa có lượt chơi nào</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="chart-box">
            <h3>📊 Thống kê mở quà</h3>
            <div class="stats-container grid grid-cols-2 gap-4 mb-6">
                <div class="stat-item wins p-4 rounded-lg bg-green-500/10 border-l-4 border-green-500 text-center">
                    <div class="label text-xs text-gray-400 uppercase tracking-wider mb-1">Lần Thắng</div>
                    <div class="value text-2xl font-bold text-green-400"><?= $gameThang ?></div>
                </div>
                <div class="stat-item losses p-4 rounded-lg bg-red-500/10 border-l-4 border-red-500 text-center">
                    <div class="label text-xs text-gray-400 uppercase tracking-wider mb-1">Lần Thua</div>
                    <div class="value text-2xl font-bold text-red-400"><?= $gameThua ?></div>
                </div>
            </div>
            <div class="relative h-[250px]">
                <canvas id="gameChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        async function loadRutthamHistory() {
            try {
                const response = await fetch('ruttham.php?action=get_history', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) return;
                const data = await response.json();
                if (data.success && data.history && data.history.length > 0) {
                    const tbody = document.getElementById('historyBody');
                    if (tbody) {
                        tbody.innerHTML = '';
                        data.history.slice(0, 10).forEach((record, index) => {
                            const newRow = document.createElement('tr');
                            newRow.className = 'border-b border-white/5 hover:bg-white/5 transition-colors';
                            newRow.style.animation = `slideIn 0.5s ease-out forwards ${index * 0.05}s`;
                            newRow.style.opacity = '0';
                            
                            const winVal = parseInt(record.WinAmount);
                            const winColor = winVal > 0 ? 'text-green-400' : 'text-red-400';
                            
                            newRow.innerHTML = `
                                <td class="p-3 border border-white/10 text-center text-gray-300 font-mono text-sm">${record.Id}</td>
                                <td class="p-3 border border-white/10 text-right text-gray-200">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                                <td class="p-3 border border-white/10 text-gray-200 font-semibold">${record.Result || '-'}</td>
                                <td class="p-3 border border-white/10 text-right font-bold ${winColor}">${winVal.toLocaleString('vi-VN')}</td>
                                <td class="p-3 border border-white/10 text-right text-xs text-gray-400">${record.Time}</td>
                            `;
                            tbody.appendChild(newRow);
                        });
                    }
                }
            } catch (error) {
                console.error('Load history error:', error);
            }
        }

        // Initialize Charts and Events
        document.addEventListener('DOMContentLoaded', function() {
            loadRutthamHistory();
            
            const ctxRuttham = document.getElementById('gameChart');
            if (ctxRuttham) {
                new Chart(ctxRuttham.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Thắng', 'Thua'],
                        datasets: [{
                            data: [<?= $gameThang ?>, <?= $gameThua ?>],
                            backgroundColor: ['rgba(74, 222, 128, 0.7)', 'rgba(255, 107, 107, 0.7)'],
                            borderColor: ['rgba(74, 222, 128, 1)', 'rgba(255, 107, 107, 1)'],
                            borderWidth: 2,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: 'rgba(255, 255, 255, 0.8)', padding: 20, usePointStyle: true }
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
