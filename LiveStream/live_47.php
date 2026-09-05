<?php
session_start();

require '../db_connect.php'; // DB trước khi gọi helper
require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_47', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;



// AJAX history endpoint
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_history') {
    header('Content-Type: application/json; charset=utf-8');
    
    $id = $botUserId ?? 0;
    $sql = "SELECT * FROM history_rps WHERE Iduser = ? ORDER BY Time DESC LIMIT 20";
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

$userId = $botUserId;
$sql = "SELECT Money, Name FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();


// Get statistics from database for chart
$gameThang = 0;
$gameThua = 0;
$sqlStats = "SELECT COUNT(*) as total, SUM(CASE WHEN WinAmount > 0 THEN 1 ELSE 0 END) as wins FROM history_rps WHERE Iduser = ?";
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
if (isset($_GET['action']) && $_GET['action'] === 'play_rps') {
    header('Content-Type: application/json');
    $chon = $_POST["chon"] ?? "";
    $cuoc = (float) ($_POST["cuoc"] ?? 0);

    if (!in_array($chon, ["Đá", "Giấy", "Kéo"])) {
        echo json_encode(['success' => false, 'message' => '❌ Vui lòng chọn Đá, Giấy hoặc Kéo!']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // SELECT FOR UPDATE để khóa bản ghi user
        $stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ? FOR UPDATE");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || $user['Money'] < $cuoc || $cuoc <= 0) {
            throw new Exception('⚠️ Số dư không đủ hoặc cược không hợp lệ!');
        }

        $choices = ["Đá", "Giấy", "Kéo"];
        $botChon = $choices[rand(0, 2)];
        $emojis = ["Đá" => "👊", "Giấy" => "✋", "Kéo" => "✌️"];

        $status = ""; // win, draw, lose
        $msg = "";
        $winAmount = 0;
        $laThang = false;

        if ($chon === $botChon) {
            $status = "draw";
            $msg = "🤝 Hòa! Cả hai cùng chọn " . $emojis[$chon] . ".";
            $winAmount = $cuoc; // Refund
        } elseif (
            ($chon === "Đá" && $botChon === "Kéo") ||
            ($chon === "Giấy" && $botChon === "Đá") ||
            ($chon === "Kéo" && $botChon === "Giấy")
        ) {
            $status = "win";
            $laThang = true;
            $winAmount = $cuoc * 2;
            $msg = "🎉 Bạn thắng! " . $emojis[$chon] . " thắng " . $emojis[$botChon] . ". Nhận " . number_format($cuoc) . " gtlm!";
        } else {
            $status = "lose";
            $msg = "😢 Bạn thua! " . $emojis[$chon] . " thua " . $emojis[$botChon] . ".";
            $winAmount = 0;
        }

        // Cập nhật số dư tương đối
        $stmt = $conn->prepare("UPDATE users SET Money = Money - ? + ? WHERE Iduser = ?");
        $stmt->bind_param("ddi", $cuoc, $winAmount, $userId);
        $stmt->execute();

        // Ghi log lịch sử riêng của rps
        $resultStr = "$chon vs $botChon";
        $profit = $winAmount - $cuoc;
        $historyStmt = $conn->prepare("INSERT INTO history_rps (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        $historyStmt->bind_param("idid", $userId, $cuoc, $resultStr, $profit);
        $historyStmt->execute();
        $historyStmt->close();

        if (file_exists('../game_history_helper.php')) {
            require_once '../game_history_helper.php';
            logGameHistoryWithAll($conn, $userId, 'RPS', $cuoc, ($laThang ? $cuoc : 0), $laThang);
        }

        $conn->commit();

        $newBalanceVal = $user['Money'] - $cuoc + $winAmount;
        echo json_encode([
            'success' => true,
            'userChon' => $chon,
            'botChon' => $botChon,
            'userEmoji' => $emojis[$chon],
            'botEmoji' => $emojis[$botChon],
            'status' => $status,
            'newMoney' => number_format($newBalanceVal) . ' gtlm',
            'message' => $msg,
            'laThang' => $laThang
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
    <title>Oẳn Tù Tì - Premium Edition</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/canvas-confetti/1.6.0/confetti.browser.min.js"></script>
    <script src="../assets/js/game-effects.js"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            cursor: url('../img/chuot.png'), auto !important;
            font-family: 'Poppins', sans-serif;
            background: transparent;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            overflow: hidden;
        }

        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: <?= $bgGradientCSS ?>;
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 40px;
            padding: 50px;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.6);
            text-align: center;
            width: 600px;
            position: relative;
        }

        .vs-stage {
            display: flex;
            justify-content: space-around;
            align-items: center;
            margin: 40px 0;
            min-height: 150px;
        }

        .hand-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .hand-emoji {
            font-size: 80px;
            filter: drop-shadow(0 0 20px rgba(255, 255, 255, 0.3));
            transition: 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .vs-label {
            font-size: 40px;
            font-weight: 800;
            color: gold;
            font-style: italic;
            opacity: 0.5;
        }

        .player-label {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #aaa;
        }

        .balance {
            color: gold;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .choice-group {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 30px 0;
        }

        .choice-btn {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 35px;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            outline: none;
        }

        .choice-btn:hover {
            transform: translateY(-5px);
            border-color: gold;
            background: rgba(255, 215, 0, 0.1);
        }

        .choice-btn.active {
            border-color: gold;
            background: gold;
            transform: scale(1.1);
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.4);
        }

        .bet-input {
            padding: 12px 25px;
            border-radius: 25px;
            border: none;
            background: rgba(0, 0, 0, 0.3);
            color: white;
            width: 180px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: bold;
        }

        .btn-play {
            padding: 15px 50px;
            border-radius: 35px;
            border: none;
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 2px;
            cursor: pointer;
            transition: 0.3s;
            width: 100%;
            max-width: 300px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        .btn-play:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            filter: grayscale(1);
        }

        .status-msg {
            margin-top: 30px;
            font-size: 18px;
            font-weight: 600;
            min-height: 25px;
            color: gold;
        }

        @keyframes shake {
            0% {
                transform: translateY(0);
            }

            25% {
                transform: translateY(-30px);
            }

            50% {
                transform: translateY(0);
            }

            75% {
                transform: translateY(-30px);
            }

            100% {
                transform: translateY(0);
            }
        }

        .shaking {
            animation: shake 0.5s infinite linear;
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

    <!-- Badge thông báo thắng/thua/hòa giống game ID 1 -->
    <div id="result-status-badge" style="
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) scale(0.5);
        background: rgba(0,0,0,0.88);
        border-radius: 20px;
        padding: 28px 52px;
        text-align: center;
        z-index: 10000;
        pointer-events: none;
        backdrop-filter: blur(22px);
        border: 2px solid rgba(255,255,255,0.15);
        box-shadow: 0 25px 80px rgba(0,0,0,0.8);
        font-family: 'Outfit', 'Poppins', sans-serif;
        transition: transform 0.4s cubic-bezier(0.17, 0.89, 0.32, 1.49), opacity 0.4s;
        opacity: 0;
    ">
        <div id="result-badge-icon" style="font-size: 3.5rem; margin-bottom: 8px;"></div>
        <div id="result-badge-title" style="font-size: 1.8rem; font-weight: 800; letter-spacing: 2px; margin-bottom: 6px;"></div>
        <div id="result-badge-amount" style="font-size: 1.3rem; font-weight: 700; opacity: 0.9;"></div>
        <div id="result-badge-msg" style="font-size: 0.85rem; opacity: 0.65; margin-top: 6px; max-width: 320px;"></div>
    </div>

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
            ['../threejs-background.js', '../assets/js/game-effects-auto.js'].forEach(src => {
                const s = document.createElement('script');
                s.src = src;
                s.async = false;
                document.head.appendChild(s);
            });
        })();

        // === Badge kết quả giống game ID 1 ===
        function showResultBadge(status, winAmount, statusMsg) {
            const badge = document.getElementById('result-status-badge');
            const icon  = document.getElementById('result-badge-icon');
            const title = document.getElementById('result-badge-title');
            const amtEl = document.getElementById('result-badge-amount');
            const msgEl = document.getElementById('result-badge-msg');

            if (status === 'win') {
                badge.style.borderColor = '#f1c40f';
                badge.style.boxShadow   = '0 25px 80px rgba(0,0,0,0.8), 0 0 80px rgba(241,196,15,0.5)';
                icon.textContent  = '🏆';
                title.textContent = 'THẮNG!';
                title.style.color = '#f1c40f';
                amtEl.textContent = '+' + parseInt(winAmount).toLocaleString('vi-VN') + ' GTLM';
                amtEl.style.color = '#f1c40f';
            } else if (status === 'draw') {
                badge.style.borderColor = '#3498db';
                badge.style.boxShadow   = '0 25px 80px rgba(0,0,0,0.8), 0 0 60px rgba(52,152,219,0.4)';
                icon.textContent  = '🤝';
                title.textContent = 'HÒA GTLM';
                title.style.color = '#3498db';
                amtEl.textContent = 'Hoàn cược';
                amtEl.style.color = '#3498db';
            } else {
                badge.style.borderColor = '#e74c3c';
                badge.style.boxShadow   = '0 25px 80px rgba(0,0,0,0.8), 0 0 60px rgba(231,76,60,0.4)';
                icon.textContent  = '❌';
                title.textContent = 'THUA!';
                title.style.color = '#e74c3c';
                amtEl.textContent = '';
                amtEl.style.color = '#e74c3c';
            }
            msgEl.textContent = statusMsg || '';

            badge.style.display = 'block';
            requestAnimationFrame(() => {
                badge.style.transform = 'translate(-50%, -50%) scale(1.05)';
                badge.style.opacity   = '1';
                setTimeout(() => { badge.style.transform = 'translate(-50%, -50%) scale(1)'; }, 150);
            });
            setTimeout(() => {
                badge.style.transform = 'translate(-50%, -50%) scale(0.8)';
                badge.style.opacity   = '0';
                setTimeout(() => { badge.style.display = 'none'; }, 400);
            }, 3500);
        }
    </script>


    <div class="glass-panel">
        <h1 style="margin: 0; font-size: 32px; letter-spacing: 3px;">✌️ OẲN TÙ TÌ</h1>
        <div class="balance">💰 VÀNG: <span id="balance-val"><?= number_format($soDu) ?></span></div>

        <div class="vs-stage">
            <div class="hand-container">
                <div class="player-label">BẠN</div>
                <div class="hand-emoji" id="user-hand">👊</div>
            </div>
            <div class="vs-label">VS</div>
            <div class="hand-container">
                <div class="player-label">BOT</div>
                <div class="hand-emoji" id="bot-hand">👊</div>
            </div>
        </div>

        <div class="choice-group">
            <button class="choice-btn" data-choice="Đá">👊</button>
            <button class="choice-btn" data-choice="Giấy">✋</button>
            <button class="choice-btn" data-choice="Kéo">✌️</button>
        </div>

        <input type="number" id="cuoc" class="bet-input" value="10000" step="5000" min="1" max="<?= $soDu ?>">
        <div style="margin-top: 10px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 8px; justify-content: center;">
            <button type="button" class="btn-game" style="font-size: 13px; padding: 6px 12px; background: #333;" onclick="document.getElementById('cuoc').value = 10000">10K GTLM</button>
            <button type="button" class="btn-game" style="font-size: 13px; padding: 6px 12px; background: #444;" onclick="document.getElementById('cuoc').value = 100000">100K GTLM</button>
            <button type="button" class="btn-game" style="font-size: 13px; padding: 6px 12px; background: #555;" onclick="document.getElementById('cuoc').value = 500000">500K GTLM</button>
            <button type="button" class="btn-game" style="font-size: 13px; padding: 6px 12px; background: #666;" onclick="document.getElementById('cuoc').value = 1000000">1M GTLM</button>
            <button type="button" class="btn-game" style="font-size: 13px; padding: 6px 12px; background: #777;" onclick="document.getElementById('cuoc').value = 5000000">5M GTLM</button>
            <button type="button" class="btn-game" style="font-size: 13px; padding: 6px 12px; background: #e74c3c;" onclick="document.getElementById('cuoc').value = <?= $soDu ?>">MAX</button>
        </div>
        <button class="btn-play" id="btn-play">CHẾT NÀY!</button>

        <div class="status-msg" id="status-msg">Chọn Đá, Giấy hoặc Kéo để so tài!</div>
        <p><a href="../index.php" style="color: rgba(255,255,255,0.3); text-decoration: none; font-size: 14px;">🏠 Trang
                chủ</a></p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>

        let selectedChoice = "";
        document.querySelectorAll('.choice-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.choice-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                selectedChoice = this.dataset.choice;
                document.getElementById('user-hand').textContent = this.textContent;
            });
        });

        document.getElementById('btn-play').addEventListener('click', async function () {
            const cuoc = document.getElementById('cuoc').value;
            if (!selectedChoice) { Swal.fire('Lỗi', 'Hãy chọn Đá, Giấy hoặc Kéo!', 'warning'); return; }
            if (cuoc <= 0) { Swal.fire('Lỗi', 'gtlm cược không hợp lệ!', 'error'); return; }

            const btn = this;
            btn.disabled = true;

            const userHand = document.getElementById('user-hand');
            const botHand = document.getElementById('bot-hand');
            const statusMsg = document.getElementById('status-msg');

            // Animation lắc tay
            userHand.textContent = '👊';
            botHand.textContent = '👊';
            userHand.classList.add('shaking');
            botHand.classList.add('shaking');
            statusMsg.textContent = 'Oẳn tù tì...';

            try {
                const fd = new FormData();
                fd.append('chon', selectedChoice);
                fd.append('cuoc', cuoc);

                const res = await fetch('?action=play_rps', { method: 'POST', body: fd });
                const data = await res.json();

                setTimeout(() => {
                    userHand.classList.remove('shaking');
                    botHand.classList.remove('shaking');

                    if (data.success) {
                        userHand.textContent = data.userEmoji;
                        botHand.textContent = data.botEmoji;
                        document.getElementById('balance-val').textContent = data.newMoney;
                        
                        if (data.status === 'win') {
                            showResultBadge('win', cuoc, data.message);
                            if (typeof GameEffects !== 'undefined') GameEffects.showWin(cuoc);
                            confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } });
                        } else if (data.status === 'draw') {
                            showResultBadge('draw', 0, data.message);
                        } else {
                            showResultBadge('lose', 0, data.message);
                            if (typeof GameEffects !== 'undefined') GameEffects.showLoss(cuoc);
                        }
                        btn.disabled = false;
                    } else {
                        showResultBadge('lose', 0, data.message);
                        btn.disabled = false;
                    }
                }, 1500); // 1.5s animation
            } catch (e) {
                console.error(e);
                btn.disabled = false;
            }
        });
    </script>

<!-- Bot AI Script -->
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script src="bots/bot_47.js?v=<?= time() ?>"></script>

</body>
</html>
