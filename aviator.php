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

$ketQua = $_SESSION['aviator_result'] ?? null;
$thongBao = $_SESSION['aviator_message'] ?? "";
$ketQuaClass = $_SESSION['aviator_class'] ?? "";
$laThang = $_SESSION['aviator_win'] ?? false;
$multiplier = $_SESSION['aviator_multiplier'] ?? 0;
$crashedAt = $_SESSION['aviator_crashed_at'] ?? 0;

unset($_SESSION['aviator_result']);
unset($_SESSION['aviator_message']);
unset($_SESSION['aviator_class']);
unset($_SESSION['aviator_win']);
unset($_SESSION['aviator_multiplier']);
unset($_SESSION['aviator_crashed_at']);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");
    $autoCashout = isset($_POST['auto_cashout']) ? (float)$_POST['auto_cashout'] : 0;

    if ($cuoc > $soDu || $cuoc <= 0) {
        $_SESSION['aviator_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['aviator_class'] = "thua";
    } else {
        if ($action === 'bet') {
            // Lưu bet vào session
            $_SESSION['aviator_bet'] = $cuoc;
            $_SESSION['aviator_auto_cashout'] = $autoCashout;
            $_SESSION['aviator_bet_time'] = time();
            
            header("Location: aviator.php?start=1");
            exit();
        } elseif ($action === 'cashout') {
            // User cashout thủ công
            if (!isset($_SESSION['aviator_bet']) || !isset($_SESSION['aviator_multiplier'])) {
                $_SESSION['aviator_message'] = "⚠️ Không có bet đang chạy!";
                $_SESSION['aviator_class'] = "thua";
            } else {
                $bet = $_SESSION['aviator_bet'];
                $currentMultiplier = $_SESSION['aviator_multiplier'];
                $thang = $bet * $currentMultiplier;
                $soDu += $thang;
                
                $_SESSION['aviator_message'] = "🎉 Cashout thành công! Multiplier: " . number_format($currentMultiplier, 2) . "x. Thắng " . number_format($thang) . " VNĐ!";
                $_SESSION['aviator_class'] = "thang";
                $_SESSION['aviator_win'] = true;
                $_SESSION['aviator_result'] = $currentMultiplier;
                
                unset($_SESSION['aviator_bet']);
                unset($_SESSION['aviator_multiplier']);
                unset($_SESSION['aviator_auto_cashout']);
                
                $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
                $capNhat->bind_param("di", $soDu, $userId);
                $capNhat->execute();
                $capNhat->close();
                
                require_once 'game_history_helper.php';
                logGameHistoryWithAll($conn, $userId, 'Aviator', $bet, $thang, true);
                
                header("Location: aviator.php");
                exit();
            }
        }
    }
}

// Xử lý aviator game
if (isset($_GET['start']) && isset($_SESSION['aviator_bet'])) {
    // Generate crash point (1.00x - 1000.00x)
    $crashPoint = 1.00 + (rand(1, 99900) / 100); // 1.00 to 1000.00
    
    // Simulate multiplier progression
    $multiplier = 1.00;
    $step = 0.01;
    $maxMultiplier = min($crashPoint, 1000.00);
    
    // Check auto cashout
    if (isset($_SESSION['aviator_auto_cashout']) && $_SESSION['aviator_auto_cashout'] > 0) {
        if ($_SESSION['aviator_auto_cashout'] <= $maxMultiplier) {
            // Auto cashout triggered
            $bet = $_SESSION['aviator_bet'];
            $thang = $bet * $_SESSION['aviator_auto_cashout'];
            $soDu += $thang;
            
            $_SESSION['aviator_message'] = "🎉 Auto Cashout! Multiplier: " . number_format($_SESSION['aviator_auto_cashout'], 2) . "x. Thắng " . number_format($thang) . " VNĐ!";
            $_SESSION['aviator_class'] = "thang";
            $_SESSION['aviator_win'] = true;
            $_SESSION['aviator_result'] = $_SESSION['aviator_auto_cashout'];
            
            $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
            $capNhat->bind_param("di", $soDu, $userId);
            $capNhat->execute();
            $capNhat->close();
            
            require_once 'game_history_helper.php';
            logGameHistoryWithAll($conn, $userId, 'Aviator', $bet, $thang, true);
            
            unset($_SESSION['aviator_bet']);
            unset($_SESSION['aviator_multiplier']);
            unset($_SESSION['aviator_auto_cashout']);
            
            header("Location: aviator.php");
            exit();
        }
    }
    
    // Check if crashed
    if ($multiplier >= $crashPoint) {
        // Crashed - lose bet
        $bet = $_SESSION['aviator_bet'];
        $soDu -= $bet;
        
        $_SESSION['aviator_message'] = "💥 Crashed tại " . number_format($crashPoint, 2) . "x! Mất " . number_format($bet) . " VNĐ";
        $_SESSION['aviator_class'] = "thua";
        $_SESSION['aviator_win'] = false;
        $_SESSION['aviator_result'] = $crashPoint;
        $_SESSION['aviator_crashed_at'] = $crashPoint;
        
        $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $capNhat->bind_param("di", $soDu, $userId);
        $capNhat->execute();
        $capNhat->close();
        
        require_once 'game_history_helper.php';
        logGameHistoryWithAll($conn, $userId, 'Aviator', $bet, 0, false);
        
        unset($_SESSION['aviator_bet']);
        unset($_SESSION['aviator_multiplier']);
        unset($_SESSION['aviator_auto_cashout']);
        
        header("Location: aviator.php");
        exit();
    }
    
    // Store current multiplier
    $_SESSION['aviator_multiplier'] = $maxMultiplier;
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
    <title>Aviator Game</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/game-aviator.css">
    <link rel="stylesheet" href="assets/css/game-animations.css">
    <link rel="stylesheet" href="assets/css/game-specific-animations.css">
    <link rel="stylesheet" href="assets/css/sound-control.css">
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
    <div class="aviator-container-enhanced">
        <div class="game-box-aviator-enhanced">
            <div class="game-header-aviator-enhanced">
                <h1 class="game-title-aviator-enhanced">✈️ Aviator Game</h1>
                <div class="balance-aviator-enhanced">
                    <span>💰</span>
                    <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
                </div>
            </div>
            
            <div class="aviator-display-enhanced">
                <div class="airplane-container" id="airplaneContainer">
                    <div class="airplane" id="airplane">✈️</div>
                </div>
                <div class="multiplier-display-aviator" id="multiplierDisplay">
                    <?php if (isset($_SESSION['aviator_multiplier'])): ?>
                        <span class="multiplier-value-aviator"><?= number_format($_SESSION['aviator_multiplier'], 2) ?>x</span>
                    <?php else: ?>
                        <span class="multiplier-value-aviator">1.00x</span>
                    <?php endif; ?>
                </div>
                <div class="aviator-graph" id="aviatorGraph"></div>
            </div>
            
            <?php if ($thongBao): ?>
                <div class="result-banner-aviator-enhanced <?= $ketQuaClass === 'thang' ? 'win animate-win' : 'lose animate-lose' ?>">
                    <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            
            <div class="game-controls-aviator-enhanced">
                <?php if (!isset($_SESSION['aviator_bet'])): ?>
                    <form method="post" id="betForm">
                        <input type="hidden" name="action" value="bet">
                        <div class="control-group-aviator-enhanced">
                            <label class="control-label-aviator-enhanced">💰 Số tiền cược:</label>
                            <input type="number" name="cuoc" id="cuocInput" class="control-input-aviator-enhanced" placeholder="Nhập số tiền cược" required min="1">
                            <div class="bet-quick-amounts-aviator-enhanced">
                                <button type="button" class="bet-quick-btn-aviator-enhanced" data-amount="10000">10K</button>
                                <button type="button" class="bet-quick-btn-aviator-enhanced" data-amount="50000">50K</button>
                                <button type="button" class="bet-quick-btn-aviator-enhanced" data-amount="100000">100K</button>
                                <button type="button" class="bet-quick-btn-aviator-enhanced" data-amount="200000">200K</button>
                            </div>
                        </div>
                        <div class="control-group-aviator-enhanced">
                            <label class="control-label-aviator-enhanced">🎯 Auto Cashout (tùy chọn):</label>
                            <input type="number" name="auto_cashout" id="autoCashoutInput" class="control-input-aviator-enhanced" placeholder="Ví dụ: 2.00" step="0.01" min="1.01" max="1000.00">
                            <small style="color: #7f8c8d; font-size: 12px;">Tự động cashout khi đạt multiplier này</small>
                        </div>
                        <button type="submit" class="bet-button-aviator-enhanced">✈️ Đặt Cược</button>
                    </form>
                <?php else: ?>
                    <div class="active-bet-display-aviator">
                        <div class="active-bet-info-aviator">
                            <p>💰 Cược: <strong><?= number_format($_SESSION['aviator_bet'], 0, ',', '.') ?> VNĐ</strong></p>
                            <?php if (isset($_SESSION['aviator_auto_cashout']) && $_SESSION['aviator_auto_cashout'] > 0): ?>
                                <p>🎯 Auto Cashout: <strong><?= number_format($_SESSION['aviator_auto_cashout'], 2) ?>x</strong></p>
                            <?php endif; ?>
                        </div>
                        <form method="post">
                            <input type="hidden" name="action" value="cashout">
                            <button type="submit" class="cashout-button-aviator-enhanced">💰 Cashout Ngay</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="aviator-info-enhanced">
                <h3>📖 Cách Chơi</h3>
                <ul>
                    <li>Đặt cược và xem máy bay bay lên</li>
                    <li>Multiplier tăng dần theo thời gian</li>
                    <li>Cashout trước khi crash để thắng</li>
                    <li>Có thể đặt Auto Cashout để tự động cashout</li>
                </ul>
            </div>
            
            <p style="text-align: center; margin-top: 20px;">
                <a href="index.php" style="color: #667eea; text-decoration: none; font-weight: 600;">🏠 Quay Lại Trang Chủ</a>
            </p>
        </div>
    </div>
    
    <script src="assets/js/game-aviator.js"></script>
    <script src="assets/js/game-animations-enhanced.js"></script>
    <script src="assets/js/sound-effects.js"></script>
</body>
</html>

