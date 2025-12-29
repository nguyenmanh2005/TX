<?php
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

// Load theme
require_once 'load_theme.php';

$userId = $_SESSION['Iduser'];
$sql = "SELECT Money, Name FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$soDu = $user['Money'];
$tenNguoiChoi = $user['Name'];

$danhSachConVat = ["Chó", "Mèo", "Gà", "Cá", "Chim"];
$emoji = [
    "Chó" => "🐶", // Emoji chú chó dễ thương
    "Mèo" => "😺", // Emoji mèo cười vui nhộn
    "Gà" => "🐔", // Emoji gà năng động
    "Cá" => "🐟", // Emoji cá đơn giản, rõ ràng
    "Chim" => "🐦" // Emoji chim bay lượn sinh động
];

// Lấy kết quả từ session (nếu có)
$ketQua = $_SESSION['baucua_result'] ?? [];
$emojiKetQua = $_SESSION['baucua_emoji'] ?? [];
$thongBao = $_SESSION['baucua_message'] ?? "";
$ketQuaClass = $_SESSION['baucua_class'] ?? "";
$laThang = $_SESSION['baucua_win'] ?? false;

// Xóa session sau khi lấy
unset($_SESSION['baucua_result']);
unset($_SESSION['baucua_emoji']);
unset($_SESSION['baucua_message']);
unset($_SESSION['baucua_class']);
unset($_SESSION['baucua_win']);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'play_baucua') {
    $chon = $_POST["chon"] ?? "";
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");

    if (!in_array($chon, $danhSachConVat)) {
        $_SESSION['baucua_message'] = "❌ Chọn con vật hợp lệ!";
        $_SESSION['baucua_class'] = "thua";
    } elseif ($cuoc > $soDu || $cuoc <= 0) {
        $_SESSION['baucua_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['baucua_class'] = "thua";
    } else {
        $ketQua = [];
        $emojiKetQua = [];
        for ($i = 0; $i < 3; $i++) {
            $rand = $danhSachConVat[rand(0, count($danhSachConVat) - 1)];
            $ketQua[] = $rand;
            $emojiKetQua[] = $emoji[$rand];
        }

        $soLan = array_count_values($ketQua)[$chon] ?? 0;
        $thang = 0;
        if ($soLan === 1) $thang = $cuoc * 2;
        elseif ($soLan === 2) $thang = $cuoc * 3;
        elseif ($soLan === 3) $thang = $cuoc * 5;

        if ($soLan > 0) {
            $soDu += $thang;
            $_SESSION['baucua_message'] = "Không thể tin nổi! Nhận được " . number_format($thang) . " VNĐ";
            $_SESSION['baucua_class'] = "thang";
            $_SESSION['baucua_win'] = true;
            
            // Kiểm tra thắng lớn và tạo thông báo toàn server
            if ($thang >= 10000000) { // 10 triệu
                $message = "🎉 " . htmlspecialchars($tenNguoiChoi) . " vừa thắng lớn " . number_format($thang, 0, ',', '.') . " VNĐ trong Bầu Cua! Chúc mừng! 🎊";
                $expiresAt = date('Y-m-d H:i:s', time() + 30);
                $checkTable = $conn->query("SHOW TABLES LIKE 'server_notifications'");
                if ($checkTable && $checkTable->num_rows > 0) {
                    $insertSql = "INSERT INTO server_notifications (user_id, user_name, message, amount, notification_type, expires_at) 
                                  VALUES (?, ?, ?, ?, 'big_win', ?)";
                    $insertStmt = $conn->prepare($insertSql);
                    if ($insertStmt) {
                        $insertStmt->bind_param("issds", $userId, $tenNguoiChoi, $message, $thang, $expiresAt);
                        $insertStmt->execute();
                        $insertStmt->close();
                    }
                }
            }
        } else {
            $soDu -= $cuoc;
            $_SESSION['baucua_message'] = "Quá buồn nhưng nên nhớ buông bỏ không phải là hạnh phúc! Mất " . number_format($cuoc) . " VNĐ";
            $_SESSION['baucua_class'] = "thua";
            $_SESSION['baucua_win'] = false;
        }

        // Cập nhật số dư vào database
        $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        if ($capNhat) {
            $capNhat->bind_param("di", $soDu, $userId);
            $capNhat->execute();
            $capNhat->close();
        }
        
        // Track quest progress và tự động cập nhật streak, VIP, reward points, social feed
        require_once 'game_history_helper.php';
        $winAmount = $soLan > 0 ? $thang : 0;
        logGameHistoryWithAll($conn, $userId, 'Bầu Cua', $cuoc, $winAmount, $soLan > 0);
        
        // Track tournament progress
        require_once 'tournament_helper.php';
        logTournamentGame($conn, $userId, 'Bầu Cua', $cuoc, $winAmount, $soLan > 0);
        
        // Lưu kết quả vào session
        $_SESSION['baucua_result'] = $ketQua;
        $_SESSION['baucua_emoji'] = $emojiKetQua;
        
        // Redirect để tránh resubmit
        header("Location: baucua.php");
        exit();
    }
}
?>

<!-- HTML -->
<!DOCTYPE html>
<html lang="vi">
<head>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <meta charset="UTF-8">
    <title>Bầu Cua Emoji (Database)</title>
    
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/game-effects.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhancements.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/game-baucua.css">
    <link rel="stylesheet" href="assets/css/game-animations.css">
    <link rel="stylesheet" href="assets/css/game-specific-animations.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            font-family: 'Segoe UI', sans-serif;
            text-align: center;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            padding: 50px;
            min-height: 100vh;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select, option {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        select, select *, select option, select option:hover {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        select:focus, select:active {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .game-box {
            display: inline-block;
            background: rgba(0, 121, 107, 0.98);
            padding: 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(0, 121, 107, 0.4);
            border: 3px solid rgba(255, 255, 255, 0.5);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            max-width: 600px;
            position: relative;
            overflow: hidden;
        }


        .game-box:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 12px 40px rgba(0, 121, 107, 0.6);
        }

        .game-box h1 {
            color: white;
            margin: 15px 0;
            font-size: 28px;
            font-weight: 700;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.3);
        }

        select, input {
            padding: 14px 18px;
            margin: 12px;
            font-size: 16px;
            border-radius: var(--border-radius);
            border: 2px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.95);
            color: var(--text-dark);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            width: 80%;
            max-width: 300px;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        select option {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            background: rgba(255, 255, 255, 0.95);
            color: var(--text-dark);
        }

        select:focus, input:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.2);
        }

        button {
            padding: 16px 32px;
            font-size: 18px;
            font-weight: 600;
            border: none;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: var(--border-radius);
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
            transition: var(--transition);
            margin: 15px;
        }

        button:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.6);
            background: linear-gradient(135deg, #20c997 0%, #28a745 100%);
        }

        .result-container {
            margin-top: 30px;
            min-height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .result {
            font-size: 80px;
            padding: 30px;
            border-radius: var(--border-radius);
            display: inline-block;
            position: relative;
            animation: resultAppear 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        @keyframes resultAppear {
            0% {
                transform: scale(0) rotate(-180deg);
                opacity: 0;
            }
            50% {
                transform: scale(1.2) rotate(10deg);
            }
            100% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }

        .result.shaking {
            animation: shakeDice 0.5s ease-in-out infinite;
        }

        @keyframes shakeDice {
            0%, 100% { transform: translateX(0) rotate(0deg); }
            10% { transform: translateX(-15px) rotate(-10deg); }
            20% { transform: translateX(15px) rotate(10deg); }
            30% { transform: translateX(-15px) rotate(-10deg); }
            40% { transform: translateX(15px) rotate(10deg); }
            50% { transform: translateX(-10px) rotate(-5deg); }
            60% { transform: translateX(10px) rotate(5deg); }
            70% { transform: translateX(-5px) rotate(-2deg); }
            80% { transform: translateX(5px) rotate(2deg); }
            90% { transform: translateX(0) rotate(0deg); }
        }

        .result .emoji-item {
            display: inline-block;
            margin: 0 10px;
            animation: emojiPop 0.6s ease backwards;
        }

        .result .emoji-item:nth-child(1) { animation-delay: 0.1s; }
        .result .emoji-item:nth-child(2) { animation-delay: 0.2s; }
        .result .emoji-item:nth-child(3) { animation-delay: 0.3s; }

        @keyframes emojiPop {
            0% {
                transform: scale(0) rotate(-360deg);
                opacity: 0;
            }
            50% {
                transform: scale(1.3) rotate(180deg);
            }
            100% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }

        .thang {
            background: rgba(40, 167, 69, 0.3);
            border: 3px solid #28a745;
            box-shadow: 0 0 25px rgba(40, 167, 69, 0.6), inset 0 0 20px rgba(40, 167, 69, 0.2);
            animation: winGlow 2s ease-in-out infinite;
        }

        @keyframes winGlow {
            0%, 100% {
                box-shadow: 0 0 25px rgba(40, 167, 69, 0.6), inset 0 0 20px rgba(40, 167, 69, 0.2);
            }
            50% {
                box-shadow: 0 0 40px rgba(40, 167, 69, 0.9), inset 0 0 30px rgba(40, 167, 69, 0.4);
            }
        }

        .thua {
            animation: loseShake 0.8s ease;
            background: rgba(220, 53, 69, 0.2);
            border: 3px solid #dc3545;
        }

        @keyframes loseShake {
            0%, 100% { transform: translateX(0) rotate(0deg); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-10px) rotate(-5deg); }
            20%, 40%, 60%, 80% { transform: translateX(10px) rotate(5deg); }
        }

        .balance {
            margin-top: 20px;
            font-size: 22px;
            font-weight: 700;
            color: #ffd700;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            padding: 15px;
            background: rgba(255, 215, 0, 0.2);
            border-radius: var(--border-radius);
            border: 2px solid #ffd700;
        }

        .thongbao {
            margin-top: 20px;
            font-size: 20px;
            font-weight: 700;
            padding: 15px 25px;
            border-radius: var(--border-radius);
            animation: slideIn 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .thongbao.thang {
            color: #00ff00;
            background: rgba(40, 167, 69, 0.3);
            border: 3px solid #28a745;
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.5);
            animation: slideIn 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55), messageWin 1s ease 0.6s;
        }

        .thongbao.thua {
            color: #ff6b6b;
            background: rgba(220, 53, 69, 0.3);
            border: 3px solid #dc3545;
            animation: slideIn 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55), messageLose 0.5s ease 0.6s;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.8);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes messageWin {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes messageLose {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .loading {
            font-size: 60px;
            margin-top: 30px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .shaking-text {
            display: inline-block;
            animation: shakeText 0.3s ease-in-out infinite;
        }

        @keyframes shakeText {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* Three.js canvas background */
        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }
        
        /* Canvas cho pháo hoa (nếu có) */
        canvas:not(#threejs-background) {
            position: fixed;
            pointer-events: none;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 999;
        }
        
        /* Đảm bảo nội dung hiển thị trên background */
        .game-box {
            position: relative;
            z-index: 1;
        }

        .game-box a {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .game-box a:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.5);
        }
    </style>
</head>
<body>
    <canvas id="threejs-background"></canvas>

<?php if ($laThang): ?>
    <canvas id="phaohoa"></canvas>
    <script>
        const canvas = document.getElementById("phaohoa");
        const ctx = canvas.getContext("2d");
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        let particles = [];
        function createFirework() {
            let x = Math.random() * canvas.width;
            let y = Math.random() * canvas.height / 2;
            let colors = ["#ffd700", "#ff6b6b", "#4ecdc4", "#45b7d1"];
            let particleCount = 30; // Giảm xuống 30
            for (let i = 0; i < particleCount; i++) {
                let angle = (Math.PI * 2 * i) / particleCount;
                let speed = 2 + Math.random() * 2;
                particles.push({
                    x: x,
                    y: y,
                    dx: Math.cos(angle) * speed,
                    dy: Math.sin(angle) * speed,
                    life: 50,
                    maxLife: 50,
                    color: colors[Math.floor(Math.random() * colors.length)],
                    size: 2
                });
            }
        }
        function update() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            for (let i = particles.length - 1; i >= 0; i--) {
                let p = particles[i];
                p.x += p.dx;
                p.y += p.dy;
                p.dy += 0.1;
                p.life--;
                if (p.life <= 0) {
                    particles.splice(i, 1);
                    continue;
                }
                let alpha = p.life / p.maxLife;
                ctx.globalAlpha = alpha;
                ctx.fillStyle = p.color;
                ctx.fillRect(p.x, p.y, 3, 3);
            }
            ctx.globalAlpha = 1;
        }
        // Tạo 2 pháo hoa
        for (let i = 0; i < 2; i++) {
            setTimeout(() => createFirework(), i * 500);
        }
        let updateInterval = setInterval(update, 50); // Tăng lên 50ms
        setTimeout(() => {
            clearInterval(updateInterval);
            document.getElementById("phaohoa").remove();
        }, 3000);
    </script>
<?php endif; ?>

<div class="baucua-container-enhanced">
    <div class="game-box-baucua-enhanced">
        <div class="game-header-baucua-enhanced">
            <h1 class="game-title-baucua-enhanced">🎲 Bầu Cua Tôm Cá</h1>
            <div class="balance-baucua-enhanced">
                <span>💰</span>
                <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
            </div>
        </div>
        
        <?php if (!empty($emojiKetQua)): ?>
            <div class="result-display-baucua-enhanced">
                <div class="result-dice-enhanced" id="resultContainer">
                    <?php foreach ($emojiKetQua as $index => $emoji): ?>
                        <div class="dice-item-enhanced" data-animal="<?= htmlspecialchars($ketQua[$index] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <?= $emoji ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="animal-selection-enhanced">
            <div class="control-group-baucua-enhanced">
                <label class="control-label-baucua-enhanced">🏆 Chọn con vật:</label>
                <select name="chon" id="chonSelect" class="control-select-baucua-enhanced" required>
                    <option value="">-- Chọn con vật --</option>
                    <?php foreach ($danhSachConVat as $cv): ?>
                        <option value="<?= htmlspecialchars($cv, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($cv, ENT_QUOTES, 'UTF-8') ?> <?= $emoji[$cv] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="animal-grid-enhanced">
                <?php foreach ($danhSachConVat as $cv): ?>
                    <div class="animal-card-enhanced" data-animal="<?= htmlspecialchars($cv, ENT_QUOTES, 'UTF-8') ?>">
                        <span class="animal-emoji-enhanced"><?= $emoji[$cv] ?></span>
                        <span class="animal-name-enhanced"><?= htmlspecialchars($cv, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="game-controls-baucua-enhanced">
            <form method="post" id="gameForm">
                <input type="hidden" name="action" value="play_baucua">
                <div class="control-group-baucua-enhanced">
                    <label class="control-label-baucua-enhanced">💰 Số tiền cược:</label>
                    <input type="number" name="cuoc" id="cuocInput" class="control-input-baucua-enhanced" placeholder="Nhập số tiền cược" required min="1">
                    <div class="bet-quick-amounts-baucua-enhanced">
                        <button type="button" class="bet-quick-btn-baucua-enhanced" data-amount="10000">10K</button>
                        <button type="button" class="bet-quick-btn-baucua-enhanced" data-amount="50000">50K</button>
                        <button type="button" class="bet-quick-btn-baucua-enhanced" data-amount="100000">100K</button>
                        <button type="button" class="bet-quick-btn-baucua-enhanced" data-amount="200000">200K</button>
                        <button type="button" class="bet-quick-btn-baucua-enhanced" data-amount="500000">500K</button>
                    </div>
                </div>
                <button type="submit" id="playButton" class="play-button-baucua-enhanced">🎲 Xóc Đĩa</button>
            </form>
        </div>

        <?php if ($thongBao): ?>
            <div class="result-banner-baucua-enhanced <?= $ketQuaClass === 'thang' ? 'win' : 'lose' ?>" id="messageDisplay">
                <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        
        <div class="payout-info-baucua-enhanced">
            <div class="payout-title-baucua-enhanced">📊 Bảng Payout</div>
            <div class="payout-list-baucua-enhanced">
                <div class="payout-item-baucua-enhanced">
                    <div class="payout-count-baucua-enhanced">1 con</div>
                    <div class="payout-multiplier-baucua-enhanced">x2</div>
                </div>
                <div class="payout-item-baucua-enhanced">
                    <div class="payout-count-baucua-enhanced">2 con</div>
                    <div class="payout-multiplier-baucua-enhanced">x3</div>
                </div>
                <div class="payout-item-baucua-enhanced">
                    <div class="payout-count-baucua-enhanced">3 con</div>
                    <div class="payout-multiplier-baucua-enhanced">x5</div>
                </div>
            </div>
        </div>
        
        <p style="text-align: center; margin-top: 20px;">
            <a href="index.php" style="color: #00796b; text-decoration: none; font-weight: 600; font-size: 16px;">🏠 Quay Lại Trang Chủ</a>
        </p>
    </div>
</div>

<script>
    // Đảm bảo cursor luôn hoạt động
    document.addEventListener('DOMContentLoaded', function() {
        // Set cursor cho body
        document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
        
        // Set cursor cho tất cả các phần tử tương tác
        const interactiveElements = document.querySelectorAll('button, a, input, label, select');
        interactiveElements.forEach(el => {
            el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
        });
        
        // Đặc biệt xử lý select
        const selectEl = document.getElementById('animalSelect');
        if (selectEl) {
            selectEl.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            selectEl.addEventListener('mouseenter', function() {
                this.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            });
            selectEl.addEventListener('focus', function() {
                this.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            });
            selectEl.addEventListener('mousedown', function() {
                this.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            });
        }
    });
    
    // Form submission handled by BaucuaEnhanced class
    const form = document.getElementById('gameForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const playButton = document.getElementById('playButton');
            if (playButton && playButton.disabled) {
                e.preventDefault();
                return;
            }
        });
    }
    
    // Auto animate dice if result available
    <?php if (!empty($emojiKetQua)): ?>
        setTimeout(() => {
            if (window.baucuaEnhanced) {
                window.baucuaEnhanced.animateDiceRoll();
                window.baucuaEnhanced.highlightWinningDice();
            }
        }, 500);
    <?php endif; ?>
    
    // Initialize Three.js Background
    (function() {
        // Pass theme config từ PHP sang JavaScript
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
        
        // Load Three.js background script
        const script = document.createElement('script');
        script.src = 'threejs-background.js';
        script.onload = function() {
            console.log('Three.js background loaded');
        };
        document.head.appendChild(script);
    })();
</script>

    <script src="assets/js/game-effects.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/game-enhancements.js"></script>
    <script src="assets/js/game-effects-auto.js"></script>
    <script src="assets/js/game-baucua.js"></script>
    <script src="assets/js/game-animations-enhanced.js"></script>

<script>
    // Auto initialize game effects
    if (typeof GameEffectsAuto !== 'undefined') {
        GameEffectsAuto.init();
    }
</script>
</body>
</html>
