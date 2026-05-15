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
    $sql = "SELECT * FROM history_slot WHERE Iduser = ? ORDER BY Time DESC LIMIT 20";
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
$sqlStats = "SELECT COUNT(*) as total, SUM(CASE WHEN WinAmount > 0 THEN 1 ELSE 0 END) as wins FROM history_slot WHERE Iduser = ?";
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

$symbols = ["🍒", "🍋", "🍊", "🍇", "⭐", "💎", "🔔", "7️⃣"];

// --- AJAX HANDLER ---
if (isset($_GET['action']) && $_GET['action'] === 'spin') {
    header('Content-Type: application/json');
    $cuoc = (float) ($_GET['bet'] ?? 0);

    if ($cuoc < 1000) {
        echo json_encode(['success' => false, 'message' => '⚠️ Cược tối thiểu 1.000 gtlm!']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // SELECT FOR UPDATE để khóa bản ghi user
        $stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ? FOR UPDATE");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || $user['Money'] < $cuoc) {
            throw new Exception('⚠️ Số dư không đủ để thực hiện quay!');
        }

        // Quay 3 cuộn
        $reels = [];
        for ($i = 0; $i < 3; $i++) {
            $reels[] = $symbols[array_rand($symbols)];
        }

        $winAmount = 0;
        $isWin = false;

        // Tính thắng (Jackpot x10 cho 3 cái, x1.5 cho bất kỳ 2 cái)
        if ($reels[0] === $reels[1] && $reels[1] === $reels[2]) {
            $isWin = true;
            if ($reels[0] === "💎") $winAmount = $cuoc * 10;
            elseif ($reels[0] === "7️⃣") $winAmount = $cuoc * 8;
            elseif ($reels[0] === "⭐") $winAmount = $cuoc * 6;
            else $winAmount = $cuoc * 4;
        } elseif ($reels[0] === $reels[1] || $reels[1] === $reels[2] || $reels[0] === $reels[2]) {
            $isWin = true;
            $winAmount = floor($cuoc * 1.5);
        }

        // Cập nhật số dư tương đối
        $stmt = $conn->prepare("UPDATE users SET Money = Money - ? + ? WHERE Iduser = ?");
        $stmt->bind_param("ddi", $cuoc, $winAmount, $userId);
        $stmt->execute();

        // Ghi log lịch sử riêng của slot
        $resultStr = implode("|", $reels);
        $historyStmt = $conn->prepare("INSERT INTO history_slot (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $historyStmt->bind_param("idid", $userId, $cuoc, $resultStr, $winAmount);
        $historyStmt->execute();
        $historyStmt->close();

        // Log tổng quát (Quest, BattlePass, etc)
        if (file_exists('../game_history_helper.php')) {
            require_once '../game_history_helper.php';
            logGameHistoryWithAll($conn, $userId, 'Slot Machine', $cuoc, $winAmount, $isWin);
        }

        $conn->commit();

        $finalBalanceVal = $user['Money'] - $cuoc + $winAmount;
        echo json_encode([
            'success' => true,
            'reels' => $reels,
            'winAmount' => $winAmount,
            'newBalance' => number_format($finalBalanceVal) . ' gtlm',
            'message' => $isWin ? "🎉 CHÚC MỪNG! Bạn thắng " . number_format($winAmount) . " gtlm!" : "💀 Rất tiếc! Chúc bạn may mắn lần sau."
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Slot Machine Premium - Neon Fortune</title>
    <script src="slot-sounds.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Poppins:wght@400;600;800&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --neon-gold: #ffd700;
            --neon-purple: #bc13fe;
            --glass: rgba(255, 255, 255, 0.1);
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

        .header-nav {
            width: 100%;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(255, 215, 0, 0.2);
            box-sizing: border-box;
        }

        .game-logo {
            font-family: 'Cinzel', serif;
            font-size: 28px;
            color: var(--neon-gold);
            letter-spacing: 5px;
            text-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
        }

        .user-balance {
            background: rgba(0, 0, 0, 0.4);
            padding: 10px 30px;
            border-radius: 40px;
            border: 2px solid var(--neon-gold);
            font-weight: 800;
            color: var(--neon-gold);
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.2);
        }

        .main-stage {
            margin-top: 60px;
            text-align: center;
            max-width: 800px;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Slot Cabinet */
        .slot-cabinet {
            background: rgba(20, 20, 20, 0.8);
            backdrop-filter: blur(20px);
            border: 4px solid #333;
            border-radius: 50px;
            padding: 40px;
            box-shadow: 0 50px 100px rgba(0, 0, 0, 0.8), inset 0 0 50px rgba(255, 215, 0, 0.1);
            border-color: var(--neon-gold);
            position: relative;
            margin-bottom: 40px;
            width: 100%;
            max-width: 600px;
        }

        .slot-cabinet::after {
            content: '';
            position: absolute;
            inset: -10px;
            border-radius: 60px;
            border: 2px solid var(--neon-purple);
            opacity: 0.3;
            pointer-events: none;
        }

        .reels-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            background: #111;
            padding: 20px;
            border-radius: 20px;
            border: 2px solid #444;
            overflow: hidden;
            height: 180px;
        }

        .reel-window {
            width: 140px;
            height: 140px;
            background: white;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 80px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(180deg, #fff 0%, #eee 100%);
            box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.2);
        }

        .reel-strip {
            position: absolute;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: transform 0.1s linear;
        }

        .symbol {
            height: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Controls */
        .controls {
            margin-top: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .bet-input-wrap {
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(0, 0, 0, 0.4);
            padding: 5px 20px;
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .bet-input-wrap label {
            font-size: 14px;
            font-weight: 600;
            color: #888;
        }

        .bet-input-wrap input {
            background: transparent;
            border: none;
            color: var(--neon-gold);
            font-size: 20px;
            font-weight: 800;
            width: 150px;
            text-align: center;
            outline: none;
        }

        .spin-btn {
            background: linear-gradient(135deg, var(--neon-gold) 0%, #b8860b 100%);
            color: black;
            border: none;
            padding: 20px 60px;
            border-radius: 50px;
            font-size: 24px;
            font-weight: 900;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.4);
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .spin-btn:hover:not(:disabled) {
            transform: scale(1.1) translateY(-5px);
            box-shadow: 0 0 50px rgba(255, 215, 0, 0.6);
        }

        .spin-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            filter: grayscale(1);
        }

        .status-marquee {
            position: fixed;
            bottom: 30px;
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid var(--neon-gold);
            color: var(--neon-gold);
            padding: 12px 50px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 1px;
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.2);
        }

        .back-link {
            color: rgba(255, 255, 255, 0.3);
            text-decoration: none;
            font-size: 14px;
            margin-top: 30px;
            transition: 0.3s;
        }

        .back-link:hover {
            color: var(--neon-gold);
        }

        @keyframes blurRolling {
            0% {
                filter: blur(0);
            }

            50% {
                filter: blur(10px);
            }

            100% {
                filter: blur(0);
            }
        }

        .rolling {
            animation: blurRolling 0.1s infinite;
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


    <header class="header-nav">
        <div class="game-logo">NEON FORTUNE SLOTS</div>
        <div class="user-balance">💰 <span id="balance-txt"><?= number_format($soDu) ?> gtlm</span></div>
        <div style="font-size: 13px; color: #666;">PLAYER: <b><?= htmlspecialchars($tenNguoiChoi) ?></b></div>
    </header>

    <div class="main-stage">
        <div class="slot-cabinet">
            <div class="reels-container">
                <div class="reel-window" id="reel-0">🍒</div>
                <div class="reel-window" id="reel-1">🍒</div>
                <div class="reel-window" id="reel-2">🍒</div>
            </div>

            <div class="controls">
                <div class="bet-input-wrap">
                    <label>MỨC CƯỢC</label>
                    <input type="number" id="bet-amount" value="10000" min="1000" step="1000">
                </div>
                <button class="spin-btn" id="spin-trigger">🎰 QUAY NGAY</button>
            </div>
        </div>

        <a href="../index.php" class="back-link">🏠 QUAY LẠI SẢNH CHỜ</a>
    </div>

    <div class="status-marquee" id="status-bar">CHÚC BẠN MAY MẮN! HÃY CHỌN MỨC CƯỢC VÀ BẤM QUAY.</div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function () {
            window.themeConfig = { particleCount: <?= $particleCount ?>, particleSize: <?= $particleSize ?>, particleColor: '<?= $particleColor ?>', particleOpacity: <?= $particleOpacity ?>, shapeCount: <?= $shapeCount ?>, shapeColors: <?= json_encode($shapeColors) ?>, shapeOpacity: <?= $shapeOpacity ?>, bgGradient: <?= json_encode($bgGradient) ?> };
            const script = document.createElement('script'); script.src = '../threejs-background.js'; document.head.appendChild(script);
        })();

        const symbols = ["🍒", "🍋", "🍊", "🍇", "⭐", "💎", "7️⃣", "🔔"];
        let spinning = false;

        async function spin() {
            if (spinning) return;

            const bet = parseInt(document.getElementById('bet-amount').value);
            if (isNaN(bet) || bet < 1000) {
                Swal.fire('Lỗi', 'Cược tối thiểu 1.000 gtlm!', 'error');
                return;
            }

            // 🪙 Bỏ coin vào máy
            SlotSounds.insertCoin();

            spinning = true;
            document.getElementById('spin-trigger').disabled = true;
            document.getElementById('status-bar').textContent = "🎰 ĐANG QUAY... CHỜ ĐỢI VẬN MAY MỈM CƯỜI!";

            const reelWindows = [
                document.getElementById('reel-0'),
                document.getElementById('reel-1'),
                document.getElementById('reel-2')
            ];

            // 🎰 Tiếng lever sau khi bỏ coin 150ms
            setTimeout(() => SlotSounds.spin(), 150);

            // Bắt đầu hiệu ứng quay ảo + loop tick sound mỗi cột
            const reelLoops = reelWindows.map((rw) => {
                rw.classList.add('rolling');
                // Mỗi cột tick speed hơi khác nhau cho sinh động
                const speedMs = 75 + Math.floor(Math.random() * 20);
                return SlotSounds.startReelLoop(speedMs);
            });

            // Đồng thời đổi symbol ngẫu nhiên trên màn hình
            const visualIntervals = reelWindows.map((rw) => {
                return setInterval(() => {
                    rw.textContent = symbols[Math.floor(Math.random() * symbols.length)];
                }, 80);
            });

            try {
                const res = await fetch(`slot.php?action=spin&bet=${bet}`);
                const data = await res.json();

                if (data.success) {
                    // Dừng từng cột theo thứ tự, mỗi cột cách nhau 500ms
                    for (let i = 0; i < 3; i++) {
                        await new Promise(r => setTimeout(r, i === 0 ? 600 : 500));

                        // Dừng loop tick + visual của cột này
                        reelLoops[i].stop();
                        clearInterval(visualIntervals[i]);
                        reelWindows[i].classList.remove('rolling');
                        reelWindows[i].textContent = data.reels[i];

                        // 🛑 Tiếng "thụp" dừng cột
                        SlotSounds.reelStop(i);

                        // Hiệu ứng pop nhỏ
                        reelWindows[i].style.transform = "scale(1.2)";
                        setTimeout(() => reelWindows[i].style.transform = "scale(1)", 120);
                    }

                    // Chờ hiệu ứng pop xong rồi xử lý kết quả
                    await new Promise(r => setTimeout(r, 400));

                    document.getElementById('balance-txt').textContent = data.newBalance;
                    document.getElementById('status-bar').textContent = data.message;

                    if (data.winAmount > 0) {
                        const isBigWin = data.winAmount >= bet * 5;

                        if (isBigWin) {
                            // 💰 Big Win / Jackpot
                            SlotSounds.bigWin();
                        } else {
                            // 🎉 Thắng thường
                            SlotSounds.win();
                        }

                        // 💵 Gtlm đổ ra sau 300ms
                        setTimeout(() => SlotSounds.coinDrop(), 300);

                        // Confetti
                        if (typeof confetti === 'function') {
                            confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } });
                            if (isBigWin) {
                                setTimeout(() => {
                                    confetti({ particleCount: 300, spread: 160, origin: { y: 0.6 }, colors: ['#ffd700', '#ffffff', '#bc13fe'] });
                                }, 400);
                            }
                        }

                        Swal.fire({
                            title: isBigWin ? '💰 JACKPOT!!!' : '🎊 CHIẾN THẮNG!',
                            text: data.message,
                            icon: 'success',
                            confirmButtonColor: '#ffd700',
                            confirmButtonText: 'TUYỆT VỜI'
                        });

                    } else {
                        // 😞 Thua
                        SlotSounds.lose();
                    }

                } else {
                    // Lỗi từ server — dừng hết
                    reelLoops.forEach(loop => loop.stop());
                    visualIntervals.forEach(clearInterval);
                    reelWindows.forEach(rw => {
                        rw.classList.remove('rolling');
                    });
                    Swal.fire('Lỗi', data.message, 'error');
                    document.getElementById('status-bar').textContent = "HÃY THỬ LẠI!";
                }

            } catch (e) {
                console.error(e);
                reelLoops.forEach(loop => loop.stop());
                visualIntervals.forEach(clearInterval);
                reelWindows.forEach(rw => rw.classList.remove('rolling'));
                document.getElementById('status-bar').textContent = "Lỗi kết nối, thử lại nhé!";
            }

            spinning = false;
            document.getElementById('spin-trigger').disabled = false;
        }

        document.getElementById('spin-trigger').onclick = spin;
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
            <h3>📋 Lịch sử quay Slot (20 lần gần nhất)</h3>
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
            <h3>📊 Thống kê Slot</h3>
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
        async function loadSlotHistory() {
            try {
                const response = await fetch('slot.php?action=get_history', {
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
            loadSlotHistory();
            
            const ctxSlot = document.getElementById('gameChart');
            if (ctxSlot) {
                new Chart(ctxSlot.getContext('2d'), {
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
