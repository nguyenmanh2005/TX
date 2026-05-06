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

$ketQua = $_SESSION['cho_han_result'] ?? [];
$thongBao = $_SESSION['cho_han_message'] ?? "";
$ketQuaClass = $_SESSION['cho_han_class'] ?? "";
$laThang = $_SESSION['cho_han_win'] ?? false;
$dice1 = $_SESSION['cho_han_dice1'] ?? 0;
$dice2 = $_SESSION['cho_han_dice2'] ?? 0;
$total = $_SESSION['cho_han_total'] ?? 0;

unset($_SESSION['cho_han_result']);
unset($_SESSION['cho_han_message']);
unset($_SESSION['cho_han_class']);
unset($_SESSION['cho_han_win']);
unset($_SESSION['cho_han_dice1']);
unset($_SESSION['cho_han_dice2']);
unset($_SESSION['cho_han_total']);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'play') {
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");
    $betOn = $_POST['bet_on'] ?? '';

    if ($cuoc > $soDu || $cuoc <= 0) {
        $_SESSION['cho_han_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['cho_han_class'] = "thua";
    } elseif (!in_array($betOn, ['cho', 'han'])) {
        $_SESSION['cho_han_message'] = "⚠️ Chọn Chẵn (Cho) hoặc Lẻ (Han)!";
        $_SESSION['cho_han_class'] = "thua";
    } else {
        // Lắc 2 xúc xắc
        $dice1 = rand(1, 6);
        $dice2 = rand(1, 6);
        $total = $dice1 + $dice2;
        
        // Xác định Chẵn (Cho) hay Lẻ (Han)
        $result = ($total % 2 === 0) ? 'cho' : 'han';
        
        if ($betOn === $result) {
            // Thắng
            $thang = $cuoc * 2;
            $soDu += $thang;
            
            $_SESSION['cho_han_message'] = "🎉 Thắng! Tổng: " . $total . " (" . ($result === 'cho' ? 'Chẵn' : 'Lẻ') . "). Thắng " . number_format($thang) . " VNĐ!";
            $_SESSION['cho_han_class'] = "thang";
            $_SESSION['cho_han_win'] = true;
        } else {
            // Thua
            $soDu -= $cuoc;
            $_SESSION['cho_han_message'] = "😢 Thua! Tổng: " . $total . " (" . ($result === 'cho' ? 'Chẵn' : 'Lẻ') . "). Mất " . number_format($cuoc) . " VNĐ";
            $_SESSION['cho_han_class'] = "thua";
            $_SESSION['cho_han_win'] = false;
        }
        
        $_SESSION['cho_han_dice1'] = $dice1;
        $_SESSION['cho_han_dice2'] = $dice2;
        $_SESSION['cho_han_total'] = $total;
        
        $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $capNhat->bind_param("di", $soDu, $userId);
        $capNhat->execute();
        $capNhat->close();
        
        require_once 'game_history_helper.php';
        $winAmount = $laThang ? $thang : 0;
        logGameHistoryWithAll($conn, $userId, 'Cho-Han', $cuoc, $winAmount, $laThang);
        
        header("Location: cho_han.php");
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

$diceEmoji = [
    1 => "⚀",
    2 => "⚁",
    3 => "⚂",
    4 => "⚃",
    5 => "⚄",
    6 => "⚅"
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cho-Han Game</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/game-cho-han.css">
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
    <div class="cho-han-container-enhanced">
        <div class="game-box-cho-han-enhanced">
            <div class="game-header-cho-han-enhanced">
                <h1 class="game-title-cho-han-enhanced">🎲 Cho-Han</h1>
                <div class="balance-cho-han-enhanced">
                    <span>💰</span>
                    <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
                </div>
            </div>
            
            <div class="cho-han-display-enhanced">
                <?php if ($dice1 > 0 && $dice2 > 0): ?>
                    <div class="dice-pair-display animated-fade-in-up">
                        <div class="dice-item-cho-han animate-bounce-in" style="animation-delay: 0.1s;">
                            <div class="dice-face-cho-han"><?= $diceEmoji[$dice1] ?></div>
                            <div class="dice-value-cho-han"><?= $dice1 ?></div>
                        </div>
                        <div class="plus-sign">+</div>
                        <div class="dice-item-cho-han animate-bounce-in" style="animation-delay: 0.2s;">
                            <div class="dice-face-cho-han"><?= $diceEmoji[$dice2] ?></div>
                            <div class="dice-value-cho-han"><?= $dice2 ?></div>
                        </div>
                        <div class="equals-sign">=</div>
                        <div class="total-display-cho-han animated-scale-in">
                            <div class="total-label-cho-han">Tổng</div>
                            <div class="total-value-cho-han"><?= $total ?></div>
                            <div class="result-type-cho-han <?= $total % 2 === 0 ? 'cho' : 'han' ?>">
                                <?= $total % 2 === 0 ? 'Chẵn (Cho)' : 'Lẻ (Han)' ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="dice-placeholder-cho-han">
                        <div class="placeholder-text-cho-han">Lắc xúc xắc để bắt đầu</div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($thongBao): ?>
                <div class="result-banner-cho-han-enhanced <?= $ketQuaClass === 'thang' ? 'win animate-win' : 'lose animate-lose' ?>">
                    <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            
            <div class="game-controls-cho-han-enhanced">
                <form method="post" id="choHanForm">
                    <input type="hidden" name="action" value="play">
                    <div class="control-group-cho-han-enhanced">
                        <label class="control-label-cho-han-enhanced">🎯 Đặt cược:</label>
                        <div class="bet-selector-cho-han">
                            <label class="bet-option-cho-han cho">
                                <input type="radio" name="bet_on" value="cho" checked>
                                <span class="bet-icon">⚪</span>
                                <span class="bet-label-cho-han">Chẵn (Cho)</span>
                                <span class="bet-multiplier">x2.0</span>
                            </label>
                            <label class="bet-option-cho-han han">
                                <input type="radio" name="bet_on" value="han">
                                <span class="bet-icon">⚫</span>
                                <span class="bet-label-cho-han">Lẻ (Han)</span>
                                <span class="bet-multiplier">x2.0</span>
                            </label>
                        </div>
                    </div>
                    <div class="control-group-cho-han-enhanced">
                        <label class="control-label-cho-han-enhanced">💰 Số tiền cược:</label>
                        <input type="number" name="cuoc" id="cuocInput" class="control-input-cho-han-enhanced" placeholder="Nhập số tiền cược" required min="1">
                        <div class="bet-quick-amounts-cho-han-enhanced">
                            <button type="button" class="bet-quick-btn-cho-han-enhanced" data-amount="10000">10K</button>
                            <button type="button" class="bet-quick-btn-cho-han-enhanced" data-amount="50000">50K</button>
                            <button type="button" class="bet-quick-btn-cho-han-enhanced" data-amount="100000">100K</button>
                            <button type="button" class="bet-quick-btn-cho-han-enhanced" data-amount="200000">200K</button>
                        </div>
                    </div>
                    <button type="submit" class="play-button-cho-han-enhanced">🎲 Lắc Xúc Xắc</button>
                </form>
            </div>
            
            <div class="cho-han-info-enhanced">
                <h3>📖 Cách Chơi</h3>
                <ul>
                    <li>Lắc 2 xúc xắc và cộng tổng</li>
                    <li>Chẵn (Cho): Tổng là số chẵn (2, 4, 6, 8, 10, 12)</li>
                    <li>Lẻ (Han): Tổng là số lẻ (3, 5, 7, 9, 11)</li>
                    <li>Thắng: x2.0 multiplier</li>
                </ul>
            </div>
            
            <p style="text-align: center; margin-top: 20px;">
                <a href="index.php" style="color: #667eea; text-decoration: none; font-weight: 600;">🏠 Quay Lại Trang Chủ</a>
            </p>
        </div>
    </div>
    
    <script src="assets/js/game-cho-han.js"></script>
    <script src="assets/js/game-animations-enhanced.js"></script>
    <script src="assets/js/sound-effects.js"></script>
</body>
</html>
