<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';
require_once 'load_theme.php';

$userId = $_SESSION['Iduser'];
$sql = "SELECT Money, Name FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) {
    die("Không tìm thấy thông tin người dùng!");
}
$soDu = $user['Money'];
$tenNguoiChoi = $user['Name'];

$ketQua = $_SESSION['limbo_result'] ?? null;
$thongBao = $_SESSION['limbo_message'] ?? "";
$ketQuaClass = $_SESSION['limbo_class'] ?? "";
$laThang = $_SESSION['limbo_win'] ?? false;
$multiplier = $_SESSION['limbo_multiplier'] ?? 0;

unset($_SESSION['limbo_result']);
unset($_SESSION['limbo_message']);
unset($_SESSION['limbo_class']);
unset($_SESSION['limbo_win']);
unset($_SESSION['limbo_multiplier']);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'play') {
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");
    $targetMultiplier = isset($_POST['target_multiplier']) ? (float)$_POST['target_multiplier'] : 0;

    if ($cuoc > $soDu || $cuoc <= 0) {
        $_SESSION['limbo_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['limbo_class'] = "thua";
    } elseif ($targetMultiplier < 1.01 || $targetMultiplier > 1000) {
        $_SESSION['limbo_message'] = "⚠️ Multiplier phải từ 1.01x đến 1000x!";
        $_SESSION['limbo_class'] = "thua";
    } else {
        // Generate random multiplier (0.00 to 1000.00)
        // Higher target = lower chance to win
        $maxMultiplier = 1000.00;
        $random = rand(1, 1000000) / 1000; // 0.001 to 1000.000
        
        // Calculate win chance based on target
        // Higher target = exponentially lower chance
        $winChance = (1 / $targetMultiplier) * 100;
        $randomChance = rand(1, 10000) / 100; // 0.01 to 100.00
        
        if ($randomChance <= $winChance && $random >= $targetMultiplier) {
            // Win
            $thang = $cuoc * $targetMultiplier;
            $soDu += $thang;
            
            $_SESSION['limbo_message'] = "🎉 Thắng! Multiplier: " . number_format($random, 2) . "x (Target: " . number_format($targetMultiplier, 2) . "x). Thắng " . number_format($thang) . " VNĐ!";
            $_SESSION['limbo_class'] = "thang";
            $_SESSION['limbo_win'] = true;
            $_SESSION['limbo_result'] = $random;
            $_SESSION['limbo_multiplier'] = $random;
        } else {
            // Lose
            $soDu -= $cuoc;
            $_SESSION['limbo_message'] = "💥 Thua! Multiplier chỉ đạt " . number_format($random, 2) . "x (Target: " . number_format($targetMultiplier, 2) . "x). Mất " . number_format($cuoc) . " VNĐ";
            $_SESSION['limbo_class'] = "thua";
            $_SESSION['limbo_win'] = false;
            $_SESSION['limbo_result'] = $random;
            $_SESSION['limbo_multiplier'] = $random;
        }
        
        $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $capNhat->bind_param("di", $soDu, $userId);
        $capNhat->execute();
        $capNhat->close();
        
        require_once 'game_history_helper.php';
        $winAmount = $laThang ? $thang : 0;
        logGameHistoryWithAll($conn, $userId, 'Limbo', $cuoc, $winAmount, $laThang);
        
        header("Location: limbo.php");
        exit();
    }
}

// Reload balance
$reloadSql = "SELECT Money FROM users WHERE Iduser = ?";
$reloadStmt = $conn->prepare($reloadSql);
$reloadStmt->bind_param("i", $userId);
$reloadStmt->execute();
$reloadResult = $reloadStmt->get_result();
$reloadUser = $reloadResult->fetch_assoc();
if ($reloadUser) {
    $soDu = $reloadUser['Money'];
}
$reloadStmt->close();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limbo Game</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/game-limbo.css">
    <link rel="stylesheet" href="assets/css/game-animations.css">
    <link rel="stylesheet" href="assets/css/game-specific-animations.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
        }
        * { cursor: inherit; }
        button, a, input { cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important; }
    </style>
</head>
<body>
    <div class="limbo-container-enhanced">
        <div class="game-box-limbo-enhanced">
            <div class="game-header-limbo-enhanced">
                <h1 class="game-title-limbo-enhanced">🚀 Limbo Game</h1>
                <div class="balance-limbo-enhanced">
                    <span>💰</span>
                    <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
                </div>
            </div>
            
            <div class="limbo-display-enhanced">
                <?php if ($multiplier > 0): ?>
                    <div class="multiplier-display-limbo animated-multiplier">
                        <div class="multiplier-value-limbo"><?= number_format($multiplier, 2) ?>x</div>
                        <div class="multiplier-label">Kết Quả</div>
                    </div>
                <?php else: ?>
                    <div class="multiplier-display-limbo">
                        <div class="multiplier-value-limbo">1.00x</div>
                        <div class="multiplier-label">Đặt Target Multiplier</div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($thongBao): ?>
                <div class="result-banner-limbo-enhanced <?= $ketQuaClass === 'thang' ? 'win' : 'lose' ?>">
                    <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            
            <div class="game-controls-limbo-enhanced">
                <form method="post" id="limboForm">
                    <input type="hidden" name="action" value="play">
                    <div class="control-group-limbo-enhanced">
                        <label class="control-label-limbo-enhanced">🎯 Target Multiplier (1.01x - 1000x):</label>
                        <input type="number" name="target_multiplier" id="targetMultiplierInput" class="control-input-limbo-enhanced" placeholder="Ví dụ: 2.00" step="0.01" min="1.01" max="1000" required>
                        <div class="multiplier-presets">
                            <button type="button" class="preset-btn" data-multiplier="1.5">1.5x</button>
                            <button type="button" class="preset-btn" data-multiplier="2.0">2.0x</button>
                            <button type="button" class="preset-btn" data-multiplier="5.0">5.0x</button>
                            <button type="button" class="preset-btn" data-multiplier="10.0">10.0x</button>
                            <button type="button" class="preset-btn" data-multiplier="50.0">50.0x</button>
                            <button type="button" class="preset-btn" data-multiplier="100.0">100.0x</button>
                        </div>
                        <div class="win-chance-display" id="winChanceDisplay">
                            <span>Cơ hội thắng: <strong id="winChanceValue">-</strong>%</span>
                        </div>
                    </div>
                    <div class="control-group-limbo-enhanced">
                        <label class="control-label-limbo-enhanced">💰 Số tiền cược:</label>
                        <input type="number" name="cuoc" id="cuocInput" class="control-input-limbo-enhanced" placeholder="Nhập số tiền cược" required min="1">
                        <div class="bet-quick-amounts-limbo-enhanced">
                            <button type="button" class="bet-quick-btn-limbo-enhanced" data-amount="10000">10K</button>
                            <button type="button" class="bet-quick-btn-limbo-enhanced" data-amount="50000">50K</button>
                            <button type="button" class="bet-quick-btn-limbo-enhanced" data-amount="100000">100K</button>
                            <button type="button" class="bet-quick-btn-limbo-enhanced" data-amount="200000">200K</button>
                        </div>
                    </div>
                    <button type="submit" class="play-button-limbo-enhanced">🚀 Chơi Limbo</button>
                </form>
            </div>
            
            <div class="limbo-info-enhanced">
                <h3>📖 Cách Chơi</h3>
                <ul>
                    <li>Đặt target multiplier (1.01x - 1000x)</li>
                    <li>Multiplier càng cao, cơ hội thắng càng thấp</li>
                    <li>Nếu kết quả >= target, bạn thắng</li>
                    <li>Thắng = Cược × Target Multiplier</li>
                </ul>
            </div>
            
            <p style="text-align: center; margin-top: 20px;">
                <a href="index.php" style="color: #667eea; text-decoration: none; font-weight: 600;">🏠 Quay Lại Trang Chủ</a>
            </p>
        </div>
    </div>
    
    <script src="assets/js/game-limbo.js"></script>
    <script src="assets/js/game-animations-enhanced.js"></script>
</body>
</html>

