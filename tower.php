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

$ketQua = $_SESSION['tower_result'] ?? null;
$thongBao = $_SESSION['tower_message'] ?? "";
$ketQuaClass = $_SESSION['tower_class'] ?? "";
$laThang = $_SESSION['tower_win'] ?? false;
$towerLevel = $_SESSION['tower_level'] ?? 0;

unset($_SESSION['tower_result']);
unset($_SESSION['tower_message']);
unset($_SESSION['tower_class']);
unset($_SESSION['tower_win']);
unset($_SESSION['tower_level']);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'play') {
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");
    $selectedColor = $_POST['color'] ?? '';

    if ($cuoc > $soDu || $cuoc <= 0) {
        $_SESSION['tower_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['tower_class'] = "thua";
    } elseif (!in_array($selectedColor, ['red', 'black'])) {
        $_SESSION['tower_message'] = "⚠️ Vui lòng chọn màu!";
        $_SESSION['tower_class'] = "thua";
    } else {
        // Generate tower (10 levels)
        $tower = [];
        $correctPath = [];
        
        for ($i = 0; $i < 10; $i++) {
            $colors = ['red', 'black'];
            $level = [];
            for ($j = 0; $j <= $i; $j++) {
                $level[] = $colors[array_rand($colors)];
            }
            $tower[] = $level;
            
            // Determine correct path
            if ($i === 0) {
                $correctPath[] = $selectedColor;
            } else {
                // Follow previous correct path
                $prevIndex = $correctPath[$i - 1] === 'red' ? 0 : 1;
                $correctPath[] = $tower[$i][$prevIndex];
            }
        }
        
        // Check if user's path is correct
        $userPath = [$selectedColor];
        $isCorrect = true;
        
        for ($i = 1; $i < 10; $i++) {
            $prevColor = $userPath[$i - 1];
            $nextIndex = $prevColor === 'red' ? 0 : 1;
            $nextColor = $tower[$i][$nextIndex];
            $userPath[] = $nextColor;
            
            if ($nextColor !== $correctPath[$i]) {
                $isCorrect = false;
                break;
            }
        }
        
        if ($isCorrect) {
            // Win - multiplier increases with level
            $multiplier = 1.0 + ($towerLevel * 0.1);
            $thang = $cuoc * $multiplier;
            $soDu += $thang;
            
            $_SESSION['tower_message'] = "🎉 Thắng! Multiplier: " . number_format($multiplier, 2) . "x. Thắng " . number_format($thang) . " VNĐ!";
            $_SESSION['tower_class'] = "thang";
            $_SESSION['tower_win'] = true;
            $_SESSION['tower_result'] = $towerLevel;
        } else {
            // Lose
            $soDu -= $cuoc;
            $_SESSION['tower_message'] = "💥 Thua! Mất " . number_format($cuoc) . " VNĐ";
            $_SESSION['tower_class'] = "thua";
            $_SESSION['tower_win'] = false;
        }
        
        $_SESSION['tower_data'] = $tower;
        $_SESSION['tower_path'] = $userPath;
        
        $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $capNhat->bind_param("di", $soDu, $userId);
        $capNhat->execute();
        $capNhat->close();
        
        require_once 'game_history_helper.php';
        $winAmount = $laThang ? $thang : 0;
        logGameHistoryWithAll($conn, $userId, 'Tower', $cuoc, $winAmount, $laThang);
        
        header("Location: tower.php");
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
    <title>Tower Game</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/game-tower.css">
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
    <div class="tower-container-enhanced">
        <div class="game-box-tower-enhanced">
            <div class="game-header-tower-enhanced">
                <h1 class="game-title-tower-enhanced">🏗️ Tower Game</h1>
                <div class="balance-tower-enhanced">
                    <span>💰</span>
                    <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
                </div>
            </div>
            
            <?php if (isset($_SESSION['tower_data'])): ?>
                <div class="tower-display-enhanced" id="towerDisplay">
                    <?php 
                    $tower = $_SESSION['tower_data'];
                    $userPath = $_SESSION['tower_path'] ?? [];
                    for ($i = 0; $i < count($tower); $i++): 
                    ?>
                        <div class="tower-level">
                            <?php foreach ($tower[$i] as $j => $color): ?>
                                <div class="tower-block <?= $color ?> <?= isset($userPath[$i]) && $userPath[$i] === $color ? 'selected' : '' ?>">
                                    <?= $color === 'red' ? '🔴' : '⚫' ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endfor; ?>
                </div>
                <?php unset($_SESSION['tower_data'], $_SESSION['tower_path']); ?>
            <?php endif; ?>
            
            <?php if ($thongBao): ?>
                <div class="result-banner-tower-enhanced <?= $ketQuaClass === 'thang' ? 'win' : 'lose' ?>">
                    <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            
            <div class="game-controls-tower-enhanced">
                <form method="post" id="towerForm">
                    <input type="hidden" name="action" value="play">
                    <div class="control-group-tower-enhanced">
                        <label class="control-label-tower-enhanced">🎨 Chọn màu bắt đầu:</label>
                        <div class="color-selector-tower">
                            <button type="button" class="color-btn-tower red" data-color="red">
                                <span class="color-icon">🔴</span>
                                <span>Đỏ</span>
                            </button>
                            <button type="button" class="color-btn-tower black" data-color="black">
                                <span class="color-icon">⚫</span>
                                <span>Đen</span>
                            </button>
                        </div>
                        <input type="hidden" name="color" id="selectedColor" required>
                    </div>
                    <div class="control-group-tower-enhanced">
                        <label class="control-label-tower-enhanced">💰 Số tiền cược:</label>
                        <input type="number" name="cuoc" id="cuocInput" class="control-input-tower-enhanced" placeholder="Nhập số tiền cược" required min="1">
                        <div class="bet-quick-amounts-tower-enhanced">
                            <button type="button" class="bet-quick-btn-tower-enhanced" data-amount="10000">10K</button>
                            <button type="button" class="bet-quick-btn-tower-enhanced" data-amount="50000">50K</button>
                            <button type="button" class="bet-quick-btn-tower-enhanced" data-amount="100000">100K</button>
                            <button type="button" class="bet-quick-btn-tower-enhanced" data-amount="200000">200K</button>
                        </div>
                    </div>
                    <button type="submit" class="play-button-tower-enhanced">🏗️ Xây Tháp</button>
                </form>
            </div>
            
            <div class="tower-info-enhanced">
                <h3>📖 Cách Chơi</h3>
                <ul>
                    <li>Chọn màu bắt đầu (Đỏ hoặc Đen)</li>
                    <li>Tháp sẽ tự động xây theo màu bạn chọn</li>
                    <li>Nếu đi đúng đường, bạn thắng với multiplier tăng dần</li>
                    <li>Tháp có 10 tầng, multiplier tối đa 2.0x</li>
                </ul>
            </div>
            
            <p style="text-align: center; margin-top: 20px;">
                <a href="index.php" style="color: #667eea; text-decoration: none; font-weight: 600;">🏠 Quay Lại Trang Chủ</a>
            </p>
        </div>
    </div>
    
    <script src="assets/js/game-tower.js"></script>
    <script src="assets/js/game-animations-enhanced.js"></script>
</body>
</html>

