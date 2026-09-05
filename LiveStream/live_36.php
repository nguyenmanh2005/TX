<?php
session_start();

require '../db_connect.php';          // ← PHẢI load trước để có $conn
require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_36', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;


// Kiểm tra đăng nhập


// db_connect.php đã được load ở trên

// AJAX history endpoint
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_history') {
    header('Content-Type: application/json; charset=utf-8');

    $id = $botUserId ?? 0;
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

$userId = $botUserId;
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
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } elseif ($money < $betAmount) {
        // Tự động nạp thêm GTLM cho bot streamer để luồng livestream 24/7 không bị dừng
        $conn->query("UPDATE users SET Money = 50000000 WHERE Iduser = " . (int)$userId);
        $money = 50000000;
    }

    if (preg_match('/^\d{5}$/', $userInput) && $money >= $betAmount) {
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
        if ($historyStmt) {
            $historyStmt->bind_param("iisi", $userId, $betAmount, $winning, $prize);
            $historyStmt->execute();
            $historyStmt->close();
        }

        // Track progress
        require_once '../game_history_helper.php';
        if (function_exists('logGameHistoryWithAll')) {
            logGameHistoryWithAll($conn, $userId, 'Xổ số Mini', $betAmount, $prize, $isWin);
        }

        if (file_exists('../user_progress_helper.php')) {
            require_once '../user_progress_helper.php';
            if (function_exists('up_add_xp')) {
                up_add_xp($conn, $userId, 10);
            }
        }

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'winning' => $winning,
                'message' => $message,
                'correct' => $correct,
                'win' => $isWin,
                'prize' => $prize,
                'balance' => number_format($newBalance, 0, ',', '.'),
                'stats' => [
                    'wins' => $gameThang + ($isWin ? 1 : 0),
                    'losses' => $gameThua + (!$isWin ? 1 : 0)
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/sweetalert2.all.min.js"></script>
    <script src="../assets/js/game-effects.js"></script>
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
            position: relative;
            max-width: 600px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
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
            grid-template-columns: 1fr;
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

        /* Hiệu ứng thắng/thua chuẩn Thế Giới Linh Thú */
        .floating-win { 
            position: absolute; 
            bottom: 45%; 
            left: 50%; 
            transform: translateX(-50%); 
            font-family: 'Orbitron', 'Inter', sans-serif; 
            font-weight: 900; 
            font-size: 2.2rem; 
            pointer-events: none; 
            text-shadow: 0 0 15px rgba(0,0,0,0.8), 0 0 30px currentColor; 
            z-index: 100;
            white-space: nowrap;
        }
        .game-card.lose-shake { 
            animation: lose-shake 0.5s cubic-bezier(.36,.07,.19,.97) both; 
        }
        @keyframes lose-shake { 
            10%, 90% { transform: translate3d(-3px, 0, 0); } 
            20%, 80% { transform: translate3d(4px, 0, 0); } 
            30%, 50%, 70% { transform: translate3d(-6px, 0, 0); } 
            40%, 60% { transform: translate3d(6px, 0, 0); } 
        }
        .game-card.win-pulse {
            animation: win-pulse 0.8s ease-out;
        }
        @keyframes win-pulse {
            0% { box-shadow: 0 0 20px rgba(46, 204, 113, 0.3); }
            50% { box-shadow: 0 0 70px rgba(46, 204, 113, 0.8); }
            100% { box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3); }
        }
        .result-status-badge {
            margin-top: 20px;
            padding: 12px 24px;
            border-radius: 14px;
            font-weight: 800;
            font-size: 1.05rem;
            text-align: center;
            display: none;
            backdrop-filter: blur(10px);
            animation: badgePop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes badgePop {
            0% { transform: scale(0.85); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .result-status-badge.win {
            background: rgba(46, 204, 113, 0.15);
            border: 1px solid rgba(46, 204, 113, 0.5);
            color: #2ecc71;
            text-shadow: 0 0 10px rgba(46, 204, 113, 0.5);
            box-shadow: 0 4px 20px rgba(46, 204, 113, 0.2);
        }
        .result-status-badge.lose {
            background: rgba(231, 76, 60, 0.15);
            border: 1px solid rgba(231, 76, 60, 0.5);
            color: #ff6b6b;
            text-shadow: 0 0 10px rgba(231, 76, 60, 0.5);
            box-shadow: 0 4px 20px rgba(231, 76, 60, 0.2);
        }
    </style>
</head>
<body>
    <canvas id="threejs-background"></canvas>
    <div class="game-card">
        <h1 class="lottery-title">🎲 Xổ số Mini</h1>
        <p>Phí tham gia: 5.000 gtlm / lượt</p>
        
        <div class="balance-box">
            💰 Số dư: <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span> gtlm
        </div>

        <div id="lottery-form">
            <div class="input-group">
                <label>Nhập 5 chữ số may mắn của bạn:</label>
                <input type="text" name="user_number" id="user-number" maxlength="5" placeholder="00000" required>
            </div>
            <button type="button" class="btn-spin" id="btn-submit">🎰 Quay số ngay</button>
            <div id="result-status-badge" class="result-status-badge"></div>
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
    </div>

    <!-- Game logic moved to end of file to avoid SyntaxError from load_theme scripts -->

<!-- AUTO-GENERATED BOT SCRIPT -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script src="bots/bot_36.js?v=<?= time() ?>"></script>
<!-- Temporarily disabled ThreeJS auto-init to fix syntax error -->
<!--
<script>
    (function () {
        window.themeConfig = {
            particleCount: <?= (int)($particleCount ?? 800) ?>,
            particleColor: '<?= htmlspecialchars($particleColor ?? "#ffffff", ENT_QUOTES) ?>',
            shapeColors: <?= json_encode($shapeColors ?? ["#667eea", "#764ba2", "#4facfe", "#00f2fe"]) ?: '["#667eea", "#764ba2", "#4facfe", "#00f2fe"]' ?>,
            bgGradient: <?= json_encode($bgGradient ?? ["#667eea", "#764ba2", "#4facfe"]) ?: '["#667eea", "#764ba2", "#4facfe"]' ?>
        };
        const prefix = '../';
        ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'].forEach(src => {
            const s = document.createElement('script');
            s.src = prefix + src; s.async = false;
            document.head.appendChild(s);
        });
    })();
</script>
-->

<!-- GAME LOGIC + ThreeJS: Đặt ngay trước </body> -->
<script>
(function() {
    'use strict';
    var wins0 = <?= (int)$gameThang ?>;
    var losses0 = <?= (int)$gameThua ?>;
    var gameChart = null;

    function initChart(wins, losses) {
        var ctx = document.getElementById('gameChart');
        if (!ctx || typeof Chart === 'undefined') return;
        if (gameChart) gameChart.destroy();
        gameChart = new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Th\u1eafng', 'Thua'],
                datasets: [{ data: [wins, losses], backgroundColor: ['#2ecc71', '#e74c3c'], borderColor: 'transparent' }]
            },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });
    }

    function loadHistory() {
        if (typeof jQuery === 'undefined') return;
        jQuery.get('live_36.php?action=get_history', function(res) {
            if (!res || !res.success) return;
            var tbody = jQuery('#history-body');
            tbody.empty();
            res.history.forEach(function(item) {
                var isWin = parseInt(item.WinAmount) > 0;
                tbody.append('<tr><td>' + item.Result + '</td><td class="' + (isWin ? 'win-text' : 'lose-text') + '">' + parseInt(item.WinAmount).toLocaleString() + '</td><td style="font-size:11px;opacity:0.5">' + item.Time.split(' ')[1] + '</td></tr>');
            });
        });
    }

    function doSpin() {
        var btn = document.getElementById('btn-submit');
        var inp = document.getElementById('user-number');
        if (!btn || !inp) return;
        var userNum = inp.value;
        if (userNum.length !== 5) {
            if (typeof Swal !== 'undefined') Swal.fire('L\u1ed7i', 'Vui l\u00f2ng nh\u1eadp \u0111\u1ee7 5 ch\u1eef s\u1ed1!', 'error');
            return;
        }
        btn.disabled = true;
        btn.textContent = '\ud83c\udfb0 \u0110ang quay...';
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'live_36.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function() {
            btn.disabled = false;
            btn.textContent = '\ud83c\udfb0 Quay s\u1ed1 ngay';
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    var count = 0;
                    var timer = setInterval(function() {
                        inp.value = Math.floor(Math.random() * 90000 + 10000);
                        count++;
                        if (count > 20) {
                            clearInterval(timer);
                            inp.value = data.winning;
                            var el;
                            el = document.getElementById('balance-val'); if (el) el.textContent = data.balance;
                            el = document.getElementById('stat-wins');   if (el) el.textContent = data.stats.wins;
                            el = document.getElementById('stat-losses'); if (el) el.textContent = data.stats.losses;
                            initChart(data.stats.wins, data.stats.losses);
                            loadHistory();

                            // ── Hiệu ứng thông báo thắng thua chuẩn Thế Giới Linh Thú ──
                            var prize = parseInt(data.prize || 0);
                            var card = jQuery('.game-card');

                            if (data.win) {
                                card.removeClass('lose-shake').addClass('win-pulse');
                                setTimeout(function() { card.removeClass('win-pulse'); }, 800);

                                if (window.GameEffects) {
                                    if (prize >= 100000) {
                                        window.GameEffects.showBigWin(prize);
                                    } else {
                                        window.GameEffects.showWin(prize);
                                    }
                                }

                                var floatWin = jQuery('<div class="floating-win" style="color:#2ecc71;">+' + prize.toLocaleString('vi-VN') + ' GTLM</div>').appendTo(card);
                                if (typeof gsap !== 'undefined') {
                                    gsap.to(floatWin, { y: -90, opacity: 0, duration: 2.2, onComplete: function() { floatWin.remove(); } });
                                } else {
                                    setTimeout(function() { floatWin.fadeOut(400, function() { floatWin.remove(); }); }, 1800);
                                }

                                jQuery('#result-status-badge')
                                    .removeClass('lose')
                                    .addClass('win')
                                    .html('🎉 <b>CHIẾN THẮNG!</b> Trúng ' + data.correct + ' số (+' + prize.toLocaleString('vi-VN') + ' GTLM)')
                                    .fadeIn(300);
                            } else {
                                card.addClass('lose-shake');
                                if (window.GameEffects) {
                                    window.GameEffects.showLoss(5000);
                                }

                                var floatLose = jQuery('<div class="floating-win" style="color:#ff4757;">-5.000 GTLM</div>').appendTo(card);
                                if (typeof gsap !== 'undefined') {
                                    gsap.to(floatLose, { y: -90, opacity: 0, duration: 2.2, onComplete: function() { floatLose.remove(); } });
                                } else {
                                    setTimeout(function() { floatLose.fadeOut(400, function() { floatLose.remove(); }); }, 1800);
                                }
                                setTimeout(function() { card.removeClass('lose-shake'); }, 500);

                                jQuery('#result-status-badge')
                                    .removeClass('win')
                                    .addClass('lose')
                                    .html('😢 <b>RẤT TIẾC!</b> Không trùng khớp (-5.000 GTLM)')
                                    .fadeIn(300);
                            }

                            setTimeout(function() {
                                jQuery('#result-status-badge').fadeOut(400);
                            }, 4000);
                        }
                    }, 50);
                } else {
                    if (typeof Swal !== 'undefined') Swal.fire('L\u1ed7i', data.message || 'C\u00f3 l\u1ed7i x\u1ea3y ra', 'error');
                }
            } catch(e) {
                console.error('[Live 36] JSON error:', xhr.responseText.substring(0, 300));
                if (typeof Swal !== 'undefined') Swal.fire('L\u1ed7i', 'Server l\u1ed7i!', 'error');
            }
        };
        xhr.onerror = function() {
            btn.disabled = false;
            btn.textContent = '\ud83c\udfb0 Quay s\u1ed1 ngay';
            if (typeof Swal !== 'undefined') Swal.fire('L\u1ed7i', 'Kh\u00f4ng k\u1ebft n\u1ed1i \u0111\u01b0\u1ee3c server!', 'error');
        };
        xhr.send('user_number=' + encodeURIComponent(userNum));
    }

    function setupGame() {
        initChart(wins0, losses0);
        loadHistory();
        var btn = document.getElementById('btn-submit');
        if (!btn) { console.error('[Live 36] ERROR: btn-submit not found!'); return; }
        console.log('[Live 36] OK: btn-submit found, binding events');
        
        btn.onclick = function() {
            console.log('[Live 36] click triggered');
            doSpin();
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupGame);
    } else {
        setupGame();
    }
})();
</script>

<!-- ThreeJS Background -->
<script>
(function() {
    window.themeConfig = {
        particleCount: <?= (int)($particleCount ?? 800) ?>,
        particleColor: '<?= addslashes(htmlspecialchars($particleColor ?? '#ffffff', ENT_QUOTES)) ?>',
        shapeColors: <?= json_encode(is_array($shapeColors) ? $shapeColors : ['#667eea','#764ba2','#4facfe','#00f2fe']) ?>,
        bgGradient:   <?= json_encode(is_array($bgGradient)  ? $bgGradient  : ['#667eea','#764ba2','#4facfe']) ?>
    };
    ['threejs-background.js','assets/js/game-effects.js','assets/js/game-effects-auto.js'].forEach(function(src) {
        var s = document.createElement('script'); s.src = '../' + src; s.async = false; document.body.appendChild(s);
    });
})();
</script>

</body>
</html>
