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

$ketQua = $_SESSION['crash_result'] ?? null;
$thongBao = $_SESSION['crash_message'] ?? "";
$ketQuaClass = $_SESSION['crash_class'] ?? "";
$laThang = $_SESSION['crash_win'] ?? false;
$multiplier = $_SESSION['crash_multiplier'] ?? 0;

unset($_SESSION['crash_result']);
unset($_SESSION['crash_message']);
unset($_SESSION['crash_class']);
unset($_SESSION['crash_win']);
unset($_SESSION['crash_multiplier']);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");
    $autoCashout = isset($_POST['auto_cashout']) ? (float)$_POST['auto_cashout'] : 0;

    if ($cuoc > $soDu || $cuoc <= 0) {
        $_SESSION['crash_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['crash_class'] = "thua";
    } else {
        if ($action === 'bet') {
            // Lưu bet vào session để chờ crash
            $_SESSION['crash_bet'] = $cuoc;
            $_SESSION['crash_auto_cashout'] = $autoCashout;
            $_SESSION['crash_bet_time'] = time();
            
            // Redirect để bắt đầu game
            header("Location: crash.php?start=1");
            exit();
        } elseif ($action === 'cashout') {
            // User cashout thủ công
            if (!isset($_SESSION['crash_bet']) || !isset($_SESSION['crash_multiplier'])) {
                $_SESSION['crash_message'] = "⚠️ Không có bet đang chạy!";
                $_SESSION['crash_class'] = "thua";
            } else {
                $bet = $_SESSION['crash_bet'];
                $currentMultiplier = $_SESSION['crash_multiplier'];
                $thang = $bet * $currentMultiplier;
                $soDu += $thang;
                
                $_SESSION['crash_message'] = "🎉 Cashout thành công! Multiplier: " . number_format($currentMultiplier, 2) . "x. Thắng " . number_format($thang) . " VNĐ!";
                $_SESSION['crash_class'] = "thang";
                $_SESSION['crash_win'] = true;
                $_SESSION['crash_result'] = $currentMultiplier;
                
                unset($_SESSION['crash_bet']);
                unset($_SESSION['crash_multiplier']);
                unset($_SESSION['crash_auto_cashout']);
                
                $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
                $capNhat->bind_param("di", $soDu, $userId);
                $capNhat->execute();
                $capNhat->close();
                
                require_once 'game_history_helper.php';
                logGameHistoryWithAll($conn, $userId, 'Crash', $bet, $thang, true);
                
                header("Location: crash.php");
                exit();
            }
        }
    }
}

// Xử lý crash game
if (isset($_GET['start']) && isset($_SESSION['crash_bet'])) {
    // Generate crash point (1.00x - 10.00x)
    $crashPoint = 1.00 + (rand(1, 900) / 100); // 1.00 to 10.00
    
    // Simulate multiplier progression
    $multiplier = 1.00;
    $step = 0.01;
    $maxMultiplier = min($crashPoint, 10.00);
    
    // Check auto cashout
    if (isset($_SESSION['crash_auto_cashout']) && $_SESSION['crash_auto_cashout'] > 0) {
        if ($_SESSION['crash_auto_cashout'] <= $maxMultiplier) {
            // Auto cashout triggered
            $bet = $_SESSION['crash_bet'];
            $thang = $bet * $_SESSION['crash_auto_cashout'];
            $soDu += $thang;
            
            $_SESSION['crash_message'] = "🎉 Auto Cashout! Multiplier: " . number_format($_SESSION['crash_auto_cashout'], 2) . "x. Thắng " . number_format($thang) . " VNĐ!";
            $_SESSION['crash_class'] = "thang";
            $_SESSION['crash_win'] = true;
            $_SESSION['crash_result'] = $_SESSION['crash_auto_cashout'];
            
            $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
            $capNhat->bind_param("di", $soDu, $userId);
            $capNhat->execute();
            $capNhat->close();
            
            require_once 'game_history_helper.php';
            logGameHistoryWithAll($conn, $userId, 'Crash', $bet, $thang, true);
            
            unset($_SESSION['crash_bet']);
            unset($_SESSION['crash_multiplier']);
            unset($_SESSION['crash_auto_cashout']);
            
            header("Location: crash.php");
            exit();
        }
    }
    
    // Check if crashed
    if ($multiplier >= $crashPoint) {
        // Crashed - lose bet
        $bet = $_SESSION['crash_bet'];
        $soDu -= $bet;
        
        $_SESSION['crash_message'] = "💥 Crashed tại " . number_format($crashPoint, 2) . "x! Mất " . number_format($bet) . " VNĐ";
        $_SESSION['crash_class'] = "thua";
        $_SESSION['crash_win'] = false;
        $_SESSION['crash_result'] = $crashPoint;
        
        $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $capNhat->bind_param("di", $soDu, $userId);
        $capNhat->execute();
        $capNhat->close();
        
        require_once 'game_history_helper.php';
        logGameHistoryWithAll($conn, $userId, 'Crash', $bet, 0, false);
        
        unset($_SESSION['crash_bet']);
        unset($_SESSION['crash_multiplier']);
        unset($_SESSION['crash_auto_cashout']);
        
        header("Location: crash.php");
        exit();
    }
    
    // Store current multiplier
    $_SESSION['crash_multiplier'] = $maxMultiplier;
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
    <title>Crash Game</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/game-crash.css">
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
    <div class="crash-container-enhanced">
        <div class="game-box-crash-enhanced">
            <div class="game-header-crash-enhanced">
                <h1 class="game-title-crash-enhanced">🚀 Crash Game</h1>
                <div class="balance-crash-enhanced">
                    <span>💰</span>
                    <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
                </div>
            </div>
            
            <div class="crash-display-enhanced">
                <div class="crash-multiplier-display" id="multiplierDisplay">
                    <?php if (isset($_SESSION['crash_multiplier'])): ?>
                        <span class="multiplier-value"><?= number_format($_SESSION['crash_multiplier'], 2) ?>x</span>
                    <?php else: ?>
                        <span class="multiplier-value">1.00x</span>
                    <?php endif; ?>
                </div>
                <div class="crash-graph" id="crashGraph"></div>
            </div>
            
            <?php if ($thongBao): ?>
                <div class="result-banner-crash-enhanced <?= $ketQuaClass === 'thang' ? 'win' : 'lose' ?>">
                    <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            
            <div class="game-controls-crash-enhanced">
                <?php if (!isset($_SESSION['crash_bet'])): ?>
                    <form method="post" id="betForm">
                        <input type="hidden" name="action" value="bet">
                        <div class="control-group-crash-enhanced">
                            <label class="control-label-crash-enhanced">💰 Số tiền cược:</label>
                            <input type="number" name="cuoc" id="cuocInput" class="control-input-crash-enhanced" placeholder="Nhập số tiền cược" required min="1">
                            <div class="bet-quick-amounts-crash-enhanced">
                                <button type="button" class="bet-quick-btn-crash-enhanced" data-amount="10000">10K</button>
                                <button type="button" class="bet-quick-btn-crash-enhanced" data-amount="50000">50K</button>
                                <button type="button" class="bet-quick-btn-crash-enhanced" data-amount="100000">100K</button>
                                <button type="button" class="bet-quick-btn-crash-enhanced" data-amount="200000">200K</button>
                                <button type="button" class="bet-quick-btn-crash-enhanced" data-amount="500000">500K</button>
                            </div>
                        </div>
                        <div class="control-group-crash-enhanced">
                            <label class="control-label-crash-enhanced">🎯 Auto Cashout (tùy chọn):</label>
                            <input type="number" name="auto_cashout" id="autoCashoutInput" class="control-input-crash-enhanced" placeholder="Ví dụ: 2.00" step="0.01" min="1.01" max="10.00">
                            <small style="color: #7f8c8d; font-size: 12px;">Tự động cashout khi đạt multiplier này</small>
                        </div>
                        <button type="submit" class="bet-button-crash-enhanced">🚀 Đặt Cược</button>
                    </form>
                <?php else: ?>
                    <div class="active-bet-display">
                        <div class="active-bet-info">
                            <p>💰 Cược: <strong><?= number_format($_SESSION['crash_bet'], 0, ',', '.') ?> VNĐ</strong></p>
                            <?php if (isset($_SESSION['crash_auto_cashout']) && $_SESSION['crash_auto_cashout'] > 0): ?>
                                <p>🎯 Auto Cashout: <strong><?= number_format($_SESSION['crash_auto_cashout'], 2) ?>x</strong></p>
                            <?php endif; ?>
                        </div>
                        <form method="post">
                            <input type="hidden" name="action" value="cashout">
                            <button type="submit" class="cashout-button-crash-enhanced">💰 Cashout Ngay</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="crash-info-enhanced">
                <h3>📖 Cách Chơi</h3>
                <ul>
                    <li>Đặt cược và xem multiplier tăng dần</li>
                    <li>Cashout trước khi crash để thắng</li>
                    <li>Multiplier càng cao, thắng càng nhiều</li>
                    <li>Có thể đặt Auto Cashout để tự động cashout</li>
                </ul>
            </div>
            
            <p style="text-align: center; margin-top: 20px;">
                <a href="index.php" style="color: #667eea; text-decoration: none; font-weight: 600;">🏠 Quay Lại Trang Chủ</a>
            </p>
        </div>
    </div>
    
    <script src="assets/js/game-crash.js"></script>
    <script src="assets/js/game-animations-enhanced.js"></script>
</body>
</html>

