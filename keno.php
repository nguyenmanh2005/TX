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

$ketQua = $_SESSION['keno_result'] ?? null;
$thongBao = $_SESSION['keno_message'] ?? "";
$ketQuaClass = $_SESSION['keno_class'] ?? "";
$laThang = $_SESSION['keno_win'] ?? false;
$selectedNumbers = $_SESSION['keno_selected'] ?? [];
$drawnNumbers = $_SESSION['keno_drawn'] ?? [];

unset($_SESSION['keno_result']);
unset($_SESSION['keno_message']);
unset($_SESSION['keno_class']);
unset($_SESSION['keno_win']);
unset($_SESSION['keno_selected']);
unset($_SESSION['keno_drawn']);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'play') {
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");
    $selectedNumbers = isset($_POST['numbers']) ? json_decode($_POST['numbers']) : [];

    if ($cuoc > $soDu || $cuoc <= 0) {
        $_SESSION['keno_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['keno_class'] = "thua";
    } elseif (count($selectedNumbers) < 1 || count($selectedNumbers) > 10) {
        $_SESSION['keno_message'] = "⚠️ Chọn từ 1 đến 10 số!";
        $_SESSION['keno_class'] = "thua";
    } else {
        // Draw 20 numbers from 1-80
        $allNumbers = range(1, 80);
        shuffle($allNumbers);
        $drawnNumbers = array_slice($allNumbers, 0, 20);
        sort($drawnNumbers);
        
        // Count matches
        $matches = count(array_intersect($selectedNumbers, $drawnNumbers));
        
        // Calculate payout based on matches
        $multiplier = 0;
        $numSelected = count($selectedNumbers);
        
        // Payout table based on number of selections and matches
        $payoutTable = [
            1 => [1 => 3.5], // 1 number: 1 match = 3.5x
            2 => [2 => 12], // 2 numbers: 2 matches = 12x
            3 => [2 => 1, 3 => 42], // 3 numbers: 2 matches = 1x, 3 matches = 42x
            4 => [2 => 1, 3 => 3, 4 => 100], // 4 numbers: 2 matches = 1x, 3 matches = 3x, 4 matches = 100x
            5 => [3 => 2, 4 => 10, 5 => 500], // 5 numbers: 3 matches = 2x, 4 matches = 10x, 5 matches = 500x
            6 => [3 => 1, 4 => 4, 5 => 25, 6 => 1000], // 6 numbers
            7 => [4 => 2, 5 => 8, 6 => 50, 7 => 2000], // 7 numbers
            8 => [4 => 1, 5 => 4, 6 => 20, 7 => 100, 8 => 5000], // 8 numbers
            9 => [5 => 2, 6 => 10, 7 => 50, 8 => 200, 9 => 10000], // 9 numbers
            10 => [5 => 1, 6 => 5, 7 => 25, 8 => 100, 9 => 500, 10 => 100000] // 10 numbers
        ];
        
        if (isset($payoutTable[$numSelected][$matches])) {
            $multiplier = $payoutTable[$numSelected][$matches];
        }
        
        if ($multiplier > 0) {
            // Win
            $thang = $cuoc * $multiplier;
            $soDu += $thang;
            
            $_SESSION['keno_message'] = "🎉 Thắng! Trúng " . $matches . "/" . $numSelected . " số! Multiplier: " . number_format($multiplier, 2) . "x. Thắng " . number_format($thang) . " VNĐ!";
            $_SESSION['keno_class'] = "thang";
            $_SESSION['keno_win'] = true;
        } else {
            // Lose
            $soDu -= $cuoc;
            $_SESSION['keno_message'] = "😢 Thua! Chỉ trúng " . $matches . "/" . $numSelected . " số. Mất " . number_format($cuoc) . " VNĐ";
            $_SESSION['keno_class'] = "thua";
            $_SESSION['keno_win'] = false;
        }
        
        $_SESSION['keno_result'] = $matches;
        $_SESSION['keno_selected'] = $selectedNumbers;
        $_SESSION['keno_drawn'] = $drawnNumbers;
        
        $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $capNhat->bind_param("di", $soDu, $userId);
        $capNhat->execute();
        $capNhat->close();
        
        require_once 'game_history_helper.php';
        $winAmount = $laThang ? $thang : 0;
        logGameHistoryWithAll($conn, $userId, 'Keno', $cuoc, $winAmount, $laThang);
        
        header("Location: keno.php");
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
    <title>Keno Game</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/game-keno.css">
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
    <div class="keno-container-enhanced">
        <div class="game-box-keno-enhanced">
            <div class="game-header-keno-enhanced">
                <h1 class="game-title-keno-enhanced">🎯 Keno Game</h1>
                <div class="balance-keno-enhanced">
                    <span>💰</span>
                    <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
                </div>
            </div>
            
            <div class="keno-display-enhanced">
                <div class="selected-numbers-display" id="selectedNumbersDisplay">
                    <div class="display-label">Số đã chọn (<span id="selectedCount">0</span>/10):</div>
                    <div class="numbers-list" id="selectedNumbersList"></div>
                </div>
                
                <?php if (!empty($drawnNumbers)): ?>
                    <div class="drawn-numbers-display animated-fade-in">
                        <div class="display-label">Số được quay (20 số):</div>
                        <div class="numbers-grid-drawn">
                            <?php foreach ($drawnNumbers as $num): ?>
                                <div class="keno-number drawn <?= in_array($num, $selectedNumbers) ? 'matched' : '' ?>">
                                    <?= $num ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="keno-numbers-grid">
                <?php for ($i = 1; $i <= 80; $i++): ?>
                    <button type="button" class="keno-number-btn <?= in_array($i, $selectedNumbers) ? 'selected' : '' ?> <?= !empty($drawnNumbers) && in_array($i, $drawnNumbers) ? 'drawn' : '' ?> <?= !empty($drawnNumbers) && in_array($i, $selectedNumbers) && in_array($i, $drawnNumbers) ? 'matched' : '' ?>" data-number="<?= $i ?>">
                        <?= $i ?>
                    </button>
                <?php endfor; ?>
            </div>
            
            <?php if ($thongBao): ?>
                <div class="result-banner-keno-enhanced <?= $ketQuaClass === 'thang' ? 'win' : 'lose' ?>">
                    <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            
            <div class="game-controls-keno-enhanced">
                <form method="post" id="kenoForm">
                    <input type="hidden" name="action" value="play">
                    <input type="hidden" name="numbers" id="selectedNumbersInput" value="[]">
                    <div class="control-group-keno-enhanced">
                        <label class="control-label-keno-enhanced">💰 Số tiền cược:</label>
                        <input type="number" name="cuoc" id="cuocInput" class="control-input-keno-enhanced" placeholder="Nhập số tiền cược" required min="1">
                        <div class="bet-quick-amounts-keno-enhanced">
                            <button type="button" class="bet-quick-btn-keno-enhanced" data-amount="10000">10K</button>
                            <button type="button" class="bet-quick-btn-keno-enhanced" data-amount="50000">50K</button>
                            <button type="button" class="bet-quick-btn-keno-enhanced" data-amount="100000">100K</button>
                            <button type="button" class="bet-quick-btn-keno-enhanced" data-amount="200000">200K</button>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="clear-button-keno" id="clearNumbers">🗑️ Xóa Tất Cả</button>
                        <button type="submit" class="play-button-keno-enhanced">🎯 Quay Số</button>
                    </div>
                </form>
            </div>
            
            <div class="keno-info-enhanced">
                <h3>📖 Cách Chơi</h3>
                <ul>
                    <li>Chọn từ 1 đến 10 số từ 1-80</li>
                    <li>Hệ thống sẽ quay 20 số ngẫu nhiên</li>
                    <li>Trúng càng nhiều số, thắng càng lớn</li>
                    <li>Multiplier phụ thuộc vào số lượng số chọn và số trúng</li>
                </ul>
            </div>
            
            <p style="text-align: center; margin-top: 20px;">
                <a href="index.php" style="color: #667eea; text-decoration: none; font-weight: 600;">🏠 Quay Lại Trang Chủ</a>
            </p>
        </div>
    </div>
    
    <script src="assets/js/game-keno.js"></script>
    <script src="assets/js/game-animations-enhanced.js"></script>
</body>
</html>

