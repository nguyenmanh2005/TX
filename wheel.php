<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';
require_once 'load_theme.php';
require_once 'game_history_helper.php';

$userId = $_SESSION['Iduser'];
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$soDu = $user['Money'] ?? 0;
$tenNguoiChoi = $user['Name'] ?? 'Người chơi';
$stmt->close();

// Lấy kết quả trước đó (session)
$wheelResult = $_SESSION['wheel_result'] ?? null;
$wheelMessage = $_SESSION['wheel_message'] ?? '';
$wheelClass = $_SESSION['wheel_class'] ?? '';
$wheelWin = $_SESSION['wheel_win'] ?? false;

unset($_SESSION['wheel_result'], $_SESSION['wheel_message'], $_SESSION['wheel_class'], $_SESSION['wheel_win']);

// Cấu hình wheel với 12 sectors
$wheelSectors = [
    ['multiplier' => 0.5, 'color' => '#e74c3c', 'label' => 'x0.5'],
    ['multiplier' => 0.8, 'color' => '#e67e22', 'label' => 'x0.8'],
    ['multiplier' => 1.0, 'color' => '#f39c12', 'label' => 'x1.0'],
    ['multiplier' => 1.2, 'color' => '#f1c40f', 'label' => 'x1.2'],
    ['multiplier' => 1.5, 'color' => '#2ecc71', 'label' => 'x1.5'],
    ['multiplier' => 2.0, 'color' => '#27ae60', 'label' => 'x2.0'],
    ['multiplier' => 2.5, 'color' => '#3498db', 'label' => 'x2.5'],
    ['multiplier' => 3.0, 'color' => '#2980b9', 'label' => 'x3.0'],
    ['multiplier' => 4.0, 'color' => '#9b59b6', 'label' => 'x4.0'],
    ['multiplier' => 5.0, 'color' => '#8e44ad', 'label' => 'x5.0'],
    ['multiplier' => 7.0, 'color' => '#e91e63', 'label' => 'x7.0'],
    ['multiplier' => 10.0, 'color' => '#ffd700', 'label' => 'x10.0'],
];

$sectorCount = count($wheelSectors);
$anglePerSector = 360 / $sectorCount;

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'spin_wheel') {
    $cuoc = (int) str_replace(',', '', $_POST['cuoc'] ?? '0');

    if ($cuoc <= 0 || $cuoc > $soDu) {
        $_SESSION['wheel_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['wheel_class'] = "thua";
    } else {
        // Chọn sector ngẫu nhiên (weighted - sector cao hơn có xác suất thấp hơn)
        $weights = [10, 8, 7, 6, 5, 4, 3, 2, 1, 1, 1, 1]; // Sector cuối (x10) có weight thấp nhất
        $totalWeight = array_sum($weights);
        $rand = rand(1, $totalWeight);
        $sectorIndex = 0;
        $acc = 0;
        
        foreach ($weights as $i => $w) {
            $acc += $w;
            if ($rand <= $acc) {
                $sectorIndex = $i;
                break;
            }
        }
        
        $sector = $wheelSectors[$sectorIndex];
        $multiplier = $sector['multiplier'];
        $tienThang = $cuoc * $multiplier;
        
        // Tính toán: trừ cược, cộng payout
        $soDu = $soDu - $cuoc + $tienThang;
        $laThang = $multiplier >= 1.0;
        
        $msgBase = $laThang 
            ? "🎉 Bạn thắng " . number_format($tienThang, 0, ',', '.') . " VNĐ" 
            : "😢 Bạn mất " . number_format($cuoc - $tienThang, 0, ',', '.') . " VNĐ";
        $detail = " | Sector: " . $sector['label'];
        $_SESSION['wheel_message'] = $msgBase . $detail;
        $_SESSION['wheel_class'] = $laThang ? "thang" : "thua";
        $_SESSION['wheel_result'] = $sectorIndex;
        $_SESSION['wheel_win'] = $laThang;
        
        // Big win thông báo
        if ($tienThang >= 5000000) {
            $message = "🎉 " . htmlspecialchars($tenNguoiChoi) . " thắng lớn " . number_format($tienThang, 0, ',', '.') . " VNĐ tại Wheel!";
            $expiresAt = date('Y-m-d H:i:s', time() + 30);
            $checkTable = $conn->query("SHOW TABLES LIKE 'server_notifications'");
            if ($checkTable && $checkTable->num_rows > 0) {
                $insertSql = "INSERT INTO server_notifications (user_id, user_name, message, amount, notification_type, expires_at) VALUES (?, ?, ?, ?, 'big_win', ?)";
                $insertStmt = $conn->prepare($insertSql);
                if ($insertStmt) {
                    $insertStmt->bind_param("issds", $userId, $tenNguoiChoi, $message, $tienThang, $expiresAt);
                    $insertStmt->execute();
                    $insertStmt->close();
                }
            }
        }
        
        // Cập nhật số dư
        $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        if ($capNhat) {
            $capNhat->bind_param("di", $soDu, $userId);
            $capNhat->execute();
            $capNhat->close();
        }
        
        // Log lịch sử
        logGameHistoryWithAll($conn, $userId, 'Wheel', $cuoc, $tienThang, $laThang);
        
        // Reload số dư
        $reloadStmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
        $reloadStmt->bind_param("i", $userId);
        $reloadStmt->execute();
        $reloadResult = $reloadStmt->get_result();
        $reloadUser = $reloadResult->fetch_assoc();
        if ($reloadUser) {
            $soDu = $reloadUser['Money'];
        }
        $reloadStmt->close();
        
        header("Location: wheel.php");
        exit();
    }
}

// Reload số dư
$reloadStmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
$reloadStmt->bind_param("i", $userId);
$reloadStmt->execute();
$reloadResult = $reloadStmt->get_result();
$reloadUser = $reloadResult->fetch_assoc();
if ($reloadUser) {
    $soDu = $reloadUser['Money'];
}
$reloadStmt->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wheel - Vòng Quay</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/game-wheel.css">
    <link rel="stylesheet" href="assets/css/game-animations.css">
    <link rel="stylesheet" href="assets/css/game-specific-animations.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
        }
        * { cursor: inherit; }
        button, a, input { cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important; }
    </style>
</head>
<body>
    <div class="wheel-game-container-enhanced">
        <div class="game-box-wheel-enhanced">
            <div class="game-header-wheel-enhanced">
                <h1 class="game-title-wheel-enhanced">🎡 Wheel - Vòng Quay</h1>
                <div class="balance-wheel-enhanced">
                    <span>💰</span>
                    <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
                </div>
            </div>
            
            <?php if ($wheelMessage): ?>
                <div class="result-banner-wheel-enhanced <?= $wheelClass === 'thang' ? 'win' : 'lose' ?>">
                    <?= htmlspecialchars($wheelMessage, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            
            <div class="wheel-display-enhanced">
                <div class="wheel-wrapper-wheel-enhanced">
                    <div class="wheel-pointer-wheel-enhanced"></div>
                    <canvas id="wheelCanvas" width="400" height="400"></canvas>
                </div>
            </div>
            
            <div class="game-controls-wheel-enhanced">
                <form method="post" id="gameForm">
                    <input type="hidden" name="action" value="spin_wheel">
                    
                    <div class="control-group-wheel-enhanced">
                        <label class="control-label-wheel-enhanced">💰 Số tiền cược:</label>
                        <input type="number" name="cuoc" id="cuocInput" class="control-input-wheel-enhanced" placeholder="Nhập số tiền cược" required min="1">
                        <div class="bet-quick-amounts-wheel-enhanced">
                            <button type="button" class="bet-quick-btn-wheel-enhanced" data-amount="10000">10K</button>
                            <button type="button" class="bet-quick-btn-wheel-enhanced" data-amount="50000">50K</button>
                            <button type="button" class="bet-quick-btn-wheel-enhanced" data-amount="100000">100K</button>
                            <button type="button" class="bet-quick-btn-wheel-enhanced" data-amount="200000">200K</button>
                            <button type="button" class="bet-quick-btn-wheel-enhanced" data-amount="500000">500K</button>
                        </div>
                    </div>
                    
                    <button type="submit" id="spinButton" class="spin-button-wheel-enhanced">🎡 Quay Ngay</button>
                </form>
            </div>
            
            <div class="sectors-info-wheel-enhanced">
                <div class="sectors-title-wheel-enhanced">📊 Các Sector</div>
                <div class="sectors-grid-wheel-enhanced">
                    <?php foreach ($wheelSectors as $index => $sector): ?>
                        <div class="sector-item-wheel-enhanced" style="border-left: 4px solid <?= $sector['color'] ?>">
                            <span class="sector-label-wheel-enhanced"><?= $sector['label'] ?></span>
                            <span class="sector-color-wheel-enhanced" style="background: <?= $sector['color'] ?>"></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <p style="text-align: center; margin-top: 20px;">
                <a href="index.php" style="color: #9b59b6; text-decoration: none; font-weight: 600;">🏠 Quay Lại Trang Chủ</a>
            </p>
        </div>
    </div>
    
    <script>
        const wheelSectors = <?= json_encode($wheelSectors) ?>;
        const wheelResult = <?= $wheelResult !== null ? $wheelResult : 'null' ?>;
        const anglePerSector = <?= $anglePerSector ?>;
    </script>
    <script src="assets/js/game-wheel.js"></script>
    <script src="assets/js/game-animations-enhanced.js"></script>
</body>
</html>
