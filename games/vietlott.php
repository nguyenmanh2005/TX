<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: ../index.php");
    exit();
}

require '../db_connect.php';


// AJAX history endpoint
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_history') {
    header('Content-Type: application/json; charset=utf-8');
    
    $id = $_SESSION['Iduser'] ?? 0;
    $sql = "SELECT * FROM history_vietlott WHERE Iduser = ? ORDER BY Time DESC LIMIT 20";
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
$sqlStats = "SELECT COUNT(*) as total, SUM(CASE WHEN WinAmount > 0 THEN 1 ELSE 0 END) as wins FROM history_vietlott WHERE Iduser = ?";
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

// --- AJAX HANDLER ---
if (isset($_GET['action']) && $_GET['action'] === 'buy_vietlott') {
    header('Content-Type: application/json');
    $cost = 500000;

    if ($money < $cost) {
        echo json_encode(['success' => false, 'message' => '❌ Không đủ gtlm! Mỗi vé 50.000 gtlm.']);
        exit;
    }

    $rawNumbers = $_POST['numbers'] ?? '';
    if (empty($rawNumbers)) {
        echo json_encode(['success' => false, 'message' => '❌ Vui lòng chọn ít nhất 1 số.']);
        exit;
    }

    $selected = array_unique(array_map('intval', explode(',', $rawNumbers)));
    if (count($selected) < 1 || count($selected) > 6) {
        echo json_encode(['success' => false, 'message' => '❌ Vui lòng chọn từ 1 đến 6 số.']);
        exit;
    }

    // Quay số (6 số từ 1-45) với kiểm soát tỉ lệ
    function generateWinningNumbers()
    {
        $p = range(1, 45);
        shuffle($p);
        return array_slice($p, 0, 6);
    }

    $winningNumbers = generateWinningNumbers();
    $matchedNumbers = array_values(array_intersect($selected, $winningNumbers));
    $matchCount = count($matchedNumbers);

    // Kiểm soát tỉ lệ trúng giải lớn (3-6 số)
    if ($matchCount >= 3) {
        $chance = 100; // Mặc định 100%
        if ($matchCount === 3)
            $chance = 10;    // 10% trúng thật
        if ($matchCount === 4)
            $chance = 2;     // 2% trúng thật
        if ($matchCount === 5)
            $chance = 0.5;   // 0.5% trúng thật
        if ($matchCount === 6)
            $chance = 0.05;  // 0.05% trúng thật
        if (mt_rand(1, 10000) > $chance * 100) {
            // Re-roll: Quay lại cho đến khi matchCount <= 2
            $limit = 0;
            while ($matchCount >= 3 && $limit < 50) {
                $winningNumbers = generateWinningNumbers();
                $matchedNumbers = array_values(array_intersect($selected, $winningNumbers));
                $matchCount = count($matchedNumbers);
                $limit++;
            }
        }
    }
    sort($winningNumbers);

    // Tính thưởng
    $prize = 0;
    switch ($matchCount) {
        case 1:
            $prize = 10000;
            break;
        case 2:
            $prize = 50000;
            break;
        case 3:
            $prize = 200000;
            break;
        case 4:
            $prize = 1000000;
            break;
        case 5:
            $prize = 10000000;
            break;
        case 6:
            $prize = 100000000;
            break;
        default:
            $prize = 0;
    }

    $newMoney = $money - $cost + $prize;
    $conn->query("UPDATE users SET Money = $newMoney WHERE Iduser = $userId");
        
        // Insert vào history_vietlott table
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['Iduser'])) {
            $userId = $_SESSION['Iduser'];
            $betAmount = (int)($_POST['bet'] ?? 0);
            $resultStr = $_POST['result'] ?? 'Unknown';
            $winAmount = (int)($reward ?? 0);
            
            $historyStmt = $conn->prepare("INSERT INTO history_vietlott (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
            if ($historyStmt) {
                $historyStmt->bind_param("iisi", $userId, $betAmount, $resultStr, $winAmount);
                $historyStmt->execute();
                $historyStmt->close();
            }
        }

    if (file_exists('../game_history_helper.php')) {
        require_once '../game_history_helper.php';
        logGameHistoryWithAll($conn, $userId, 'Vietlott', $cost, $prize, $prize > 0);
    }

    echo json_encode([
        'success' => true,
        'winningNumbers' => $winningNumbers,
        'matchedNumbers' => $matchedNumbers,
        'prize' => $prize,
        'newBalance' => number_format($newMoney) . ' gtlm',
        'message' => ($prize > 0) ? "🎉 CHÚC MỪNG! Bạn trúng " . number_format($prize) . " gtlm!" : "😢 Rất tiếc! Chúc bạn may mắn lần sau."
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Vietlott Premium - Cơ Hội Đổi Đời</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Poppins:wght@400;600;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --v-gold: #ffd700;
            --v-blue: #0055a4;
            --v-red: #ed1c24;
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
            backdrop-filter: blur(15px);
            border-bottom: 2px solid var(--v-red);
            box-sizing: border-box;
        }

        .logo-vietlott {
            font-family: 'Cinzel', serif;
            font-size: 26px;
            color: var(--v-gold);
            letter-spacing: 3px;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
        }

        .user-money {
            background: rgba(0, 0, 0, 0.4);
            padding: 8px 25px;
            border-radius: 30px;
            border: 1px solid var(--v-gold);
            font-weight: 800;
            color: var(--v-gold);
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.1);
        }

        .main-container {
            margin: 50px 0;
            max-width: 900px;
            width: 100%;
            padding: 0 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Glass Panel */
        .glass-panel {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 40px;
            padding: 40px;
            width: 100%;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.5);
            position: relative;
        }

        .section-title {
            font-size: 14px;
            font-weight: 800;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 25px;
        }

        /* Number Selection Grid */
        .number-grid {
            display: grid;
            grid-template-columns: repeat(9, 1fr);
            gap: 10px;
            margin-bottom: 30px;
        }

        .num-box {
            aspect-ratio: 1;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .num-box:hover {
            transform: scale(1.1);
            background: rgba(255, 255, 255, 0.2);
            border-color: var(--v-gold);
        }

        .num-box.selected {
            background: var(--v-red);
            border-color: var(--v-gold);
            color: white;
            box-shadow: 0 0 20px rgba(237, 28, 36, 0.6);
            transform: scale(1.15);
        }

        /* Drawing Area */
        .drawing-zone {
            background: rgba(0, 0, 0, 0.3);
            padding: 30px;
            border-radius: 30px;
            margin: 30px 0;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .winning-balls {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }

        .ball {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 900;
            color: black;
            background: radial-gradient(circle at 30% 30%, #fff 0%, #ffd700 80%, #b8860b 100%);
            box-shadow: 4px 4px 10px rgba(0, 0, 0, 0.5);
            transform: translateY(-30px);
            opacity: 0;
            animation: ballDrop 0.5s forwards;
        }

        .ball.matched {
            background: radial-gradient(circle at 30% 30%, #fff 0%, #ed1c24 80%, #a00 100%);
            color: white;
            box-shadow: 0 0 20px #ed1c24;
        }

        @keyframes ballDrop {
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Actions */
        .action-row {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .buy-btn {
            background: linear-gradient(135deg, var(--v-red) 0%, #a00 100%);
            color: white;
            border: none;
            padding: 18px 60px;
            border-radius: 50px;
            font-size: 20px;
            font-weight: 900;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 10px 30px rgba(237, 28, 36, 0.3);
        }

        .buy-btn:hover:not(:disabled) {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 40px rgba(237, 28, 36, 0.5);
        }

        .buy-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .status-msg {
            position: fixed;
            bottom: 30px;
            background: rgba(0, 0, 0, 0.8);
            border: 2px solid var(--v-red);
            color: #fff;
            padding: 12px 40px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
        }

        .home-link {
            color: rgba(255, 255, 255, 0.4);
            text-decoration: none;
            font-size: 14px;
            margin-top: 40px;
        }

        .home-link:hover {
            color: var(--v-gold);
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
        <div class="logo-vietlott">VIETLOTT PREMUM</div>
        <div class="user-money">💰 <span id="money-val"><?= number_format($money) ?> gtlm</span></div>
        <div style="font-size: 13px; color: #666;">PLAYER: <b><?= htmlspecialchars($userName) ?></b></div>
    </header>

    <div class="main-container">
        <div class="glass-panel">
            <div class="section-title">CHỌN TỪ 1 ĐẾN 6 SỐ (1-45)</div>

            <div class="number-grid">
                <?php for ($i = 1; $i <= 45; $i++): ?>
                    <div class="num-box" onclick="toggleNumber(this, <?= $i ?>)"><?= $i ?></div>
                <?php endfor; ?>
            </div>

            <div class="drawing-zone">
                <div class="section-title" id="draw-label">KẾT QUẢ QUAY THƯỞNG</div>
                <div class="winning-balls" id="ball-container">
                    <!-- Balls will appear here -->
                </div>
            </div>

            <div class="action-row">
                <button class="buy-btn" id="buy-trigger">🎫 MUA VÉ - 50.000 gtlm</button>
                <div style="color: #888; font-size: 12px;">BẠN ĐÃ CHỌN: <span id="selected-list"
                        style="color: var(--v-gold); font-weight: 800;">-</span></div>
            </div>
        </div>

        <a href="../index.php" class="home-link">🏠 QUAY LẠI TRANG CHỦ</a>
    </div>

    <div class="status-msg" id="status-bar">CHỌN SỐ VÀ NHẤN "MUA VÉ" ĐỂ THỬ VẬN MAY!</div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function () {
            window.themeConfig = { particleCount: <?= $particleCount ?>, particleSize: <?= $particleSize ?>, particleColor: '<?= $particleColor ?>', particleOpacity: <?= $particleOpacity ?>, shapeCount: <?= $shapeCount ?>, shapeColors: <?= json_encode($shapeColors) ?>, shapeOpacity: <?= $shapeOpacity ?>, bgGradient: <?= json_encode($bgGradient) ?> };
            const script = document.createElement('script'); script.src = '../threejs-background.js'; document.head.appendChild(script);
        })();

        let selectedNumbers = [];
        let isSpinning = false;

        function toggleNumber(el, num) {
            if (isSpinning) return;
            const idx = selectedNumbers.indexOf(num);
            if (idx > -1) {
                selectedNumbers.splice(idx, 1);
                el.classList.remove('selected');
            } else {
                if (selectedNumbers.length >= 6) {
                    Swal.fire({ text: 'Bạn chỉ được chọn tối đa 6 số!', icon: 'warning', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
                    return;
                }
                selectedNumbers.push(num);
                el.classList.add('selected');
            }
            selectedNumbers.sort((a, b) => a - b);
            document.getElementById('selected-list').textContent = selectedNumbers.length > 0 ? selectedNumbers.join(', ') : '-';
        }

        async function buyTicket() {
            if (isSpinning) return;
            if (selectedNumbers.length < 1) {
                Swal.fire('Lỗi', 'Vui lòng chọn ít nhất 1 số!', 'error');
                return;
            }

            isSpinning = true;
            document.getElementById('buy-trigger').disabled = true;
            document.getElementById('status-bar').textContent = "🎰 ĐANG QUAY SỐ... HỒI HỘP QUÁ!";
            const container = document.getElementById('ball-container');
            container.innerHTML = '';

            try {
                const formData = new FormData();
                formData.append('numbers', selectedNumbers.join(','));

                const res = await fetch('vietlott.php?action=buy_vietlott', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    // Animation quay bóng
                    for (let i = 0; i < 6; i++) {
                        await new Promise(r => setTimeout(r, 600));
                        const val = data.winningNumbers[i];
                        const ball = document.createElement('div');
                        ball.className = 'ball';
                        if (data.matchedNumbers.includes(val)) {
                            ball.classList.add('matched');
                        }
                        ball.textContent = val;
                        container.appendChild(ball);
                    }

                    setTimeout(() => {
                        document.getElementById('money-val').textContent = data.newBalance;
                        document.getElementById('status-bar').textContent = data.message;

                        if (data.prize > 0) {
                            if (typeof confetti === 'function') {
                                confetti({ particleCount: 200, spread: 80, origin: { y: 0.6 }, colors: ['#ffd700', '#ed1c24'] });
                            }
                            Swal.fire({ title: '🎊 CHÚC MỪNG!', text: data.message, icon: 'success', confirmButtonColor: '#ed1c24' });
                        } else {
                            Swal.fire({ title: 'Rất tiếc', text: data.message, icon: 'info', confirmButtonColor: '#0055a4' });
                        }

                        isSpinning = false;
                        document.getElementById('buy-trigger').disabled = false;
                    }, 500);

                } else {
                    Swal.fire('Lỗi', data.message, 'error');
                    isSpinning = false;
                    document.getElementById('buy-trigger').disabled = false;
                    document.getElementById('status-bar').textContent = "HÃY THỬ LẠI!";
                }
            } catch (e) {
                console.error(e);
                isSpinning = false;
                document.getElementById('buy-trigger').disabled = false;
            }
        }

        document.getElementById('buy-trigger').onclick = buyTicket;
    </script>

    
    


    


    


    <!-- Premium Effects System -->
    <canvas id="threejs-background"></canvas>
    <script>
        (function() {
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
    
    // Load game history for vietlott
    
            });
            
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            if (data.success && data.history.length > 0) {
                const tbody = document.getElementById('historyBody');
                tbody.innerHTML = '';
                
                data.history.forEach((record, index) => {
                    const row = document.createElement('tr');
                    row.style.animation = \`slideIn 0.5s ease-out forwards\`;
                    row.style.animationDelay = (index * 0.05) + 's';
                    row.innerHTML = \`
                        <td>${record.Result}</td>
                        <td>${Number(record.Bet).toLocaleString('vi-VN')}</td>
                        <td>${record.Result}</td>
                        <td style="color: ${record.WinAmount > 0 ? '#28a745' : '#dc3545'}">
                            ${record.WinAmount > 0 ? '+' : ''}${Number(record.WinAmount).toLocaleString('vi-VN')}
                        </td>
                    \`;
                    tbody.appendChild(row);
                });
            }
        } catch (error) {
            console.error('Lỗi load history:', error);
        }
    }
    
    // Auto load history when page loads
    



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
    
    // Chart.js for vietlott game
    const ctxVietlott = document.getElementById('gameChart');
    if (ctxVietlott) {
        const gameChart = new Chart(ctxVietlott.getContext('2d'), {
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
    window.addEventListener('load', loadVietlottHistory);



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
    
    // Chart.js for vietlott game
    const ctxVietlott = document.getElementById('gameChart');
    if (ctxVietlott) {
        const gameChart = new Chart(ctxVietlott.getContext('2d'), {
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
    window.addEventListener('load', loadVietlottHistory);



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
    
    // Chart.js for vietlott game
    const ctxVietlott = document.getElementById('gameChart');
    if (ctxVietlott) {
        const gameChart = new Chart(ctxVietlott.getContext('2d'), {
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
    window.addEventListener('load', loadVietlottHistory);



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
    
    // Chart.js for vietlott game
    const ctxVietlott = document.getElementById('gameChart');
    if (ctxVietlott) {
        const gameChart = new Chart(ctxVietlott.getContext('2d'), {
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
    window.addEventListener('load', loadVietlottHistory);



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
    
    // Chart.js for vietlott game
    const ctxVietlott = document.getElementById('gameChart');
    if (ctxVietlott) {
        const gameChart = new Chart(ctxVietlott.getContext('2d'), {
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
    window.addEventListener('load', loadVietlottHistory);



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
    
    // Chart.js for vietlott game
    const ctxVietlott = document.getElementById('gameChart');
    if (ctxVietlott) {
        const gameChart = new Chart(ctxVietlott.getContext('2d'), {
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
    window.addEventListener('load', loadVietlottHistory);



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
    
    // Chart.js for vietlott game
    const ctxVietlott = document.getElementById('gameChart');
    if (ctxVietlott) {
        const gameChart = new Chart(ctxVietlott.getContext('2d'), {
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
    window.addEventListener('load', loadVietlottHistory);



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
    
    // Chart.js for vietlott game
    const ctxVietlott = document.getElementById('gameChart');
    if (ctxVietlott) {
        const gameChart = new Chart(ctxVietlott.getContext('2d'), {
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
    window.addEventListener('load', loadVietlottHistory);



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
    
    // Chart.js for vietlott game
    const ctxVietlott = document.getElementById('gameChart');
    if (ctxVietlott) {
        const gameChart = new Chart(ctxVietlott.getContext('2d'), {
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
    window.addEventListener('load', loadVietlottHistory);



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
    
    // Chart.js for vietlott game
    const ctxVietlott = document.getElementById('gameChart');
    if (ctxVietlott) {
        const gameChart = new Chart(ctxVietlott.getContext('2d'), {
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
    window.addEventListener('load', loadVietlottHistory);



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
    
    // Chart.js for vietlott game
    const ctxVietlott = document.getElementById('gameChart');
    if (ctxVietlott) {
        const gameChart = new Chart(ctxVietlott.getContext('2d'), {
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
    window.addEventListener('load', loadVietlottHistory);



    // Improved history loading function
    async function loadVietlottHistory() {
        try {
            const response = await fetch('vietlott.php?action=get_history', {
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
    
    // Chart.js for vietlott game
    const ctxVietlott = document.getElementById('gameChart');
    if (ctxVietlott) {
        const gameChart = new Chart(ctxVietlott.getContext('2d'), {
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
    window.addEventListener('load', loadVietlottHistory);

</script>>














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
