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

// Kiểm tra bảng daily_bonus
$checkTable = $conn->query("SHOW TABLES LIKE 'daily_bonus'");
if (!$checkTable || $checkTable->num_rows === 0) {
    // Tạo bảng nếu chưa có
    $createTable = "CREATE TABLE IF NOT EXISTS daily_bonus (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        claim_date DATE NOT NULL,
        day_streak INT DEFAULT 1,
        bonus_amount DECIMAL(15,2) DEFAULT 0,
        claimed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_date (user_id, claim_date),
        FOREIGN KEY (user_id) REFERENCES users(Iduser) ON DELETE CASCADE
    )";
    $conn->query($createTable);
}

$thongBao = "";
$ketQuaClass = "";
$bonusAmount = 0;
$dayStreak = 1;
$canClaim = false;

// Lấy thông tin bonus hôm nay
$today = date('Y-m-d');
$bonusSql = "SELECT * FROM daily_bonus WHERE user_id = ? AND claim_date = ?";
$bonusStmt = $conn->prepare($bonusSql);
$bonusStmt->bind_param("is", $userId, $today);
$bonusStmt->execute();
$bonusResult = $bonusStmt->get_result();
$todayBonus = $bonusResult->fetch_assoc();
$bonusStmt->close();

// Lấy streak hiện tại
$yesterday = date('Y-m-d', strtotime('-1 day'));
$yesterdaySql = "SELECT day_streak FROM daily_bonus WHERE user_id = ? AND claim_date = ?";
$yesterdayStmt = $conn->prepare($yesterdaySql);
$yesterdayStmt->bind_param("is", $userId, $yesterday);
$yesterdayStmt->execute();
$yesterdayResult = $yesterdayStmt->get_result();
$yesterdayBonus = $yesterdayResult->fetch_assoc();
$yesterdayStmt->close();

if ($yesterdayBonus) {
    $dayStreak = $yesterdayBonus['day_streak'] + 1;
} else {
    $dayStreak = 1;
}

// Tính bonus dựa trên streak
$baseBonus = 10000; // 10K VNĐ
$bonusAmount = $baseBonus * $dayStreak;
$maxStreak = 7; // Tối đa 7 ngày
if ($dayStreak > $maxStreak) {
    $dayStreak = $maxStreak;
    $bonusAmount = $baseBonus * $maxStreak;
}

if ($todayBonus) {
    $canClaim = false;
    $thongBao = "✅ Bạn đã nhận bonus hôm nay rồi! Quay lại vào ngày mai.";
    $ketQuaClass = "info";
} else {
    $canClaim = true;
}

// Xử lý claim bonus
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'claim') {
    if (!$canClaim) {
        $_SESSION['daily_bonus_message'] = "⚠️ Bạn đã nhận bonus hôm nay rồi!";
        $_SESSION['daily_bonus_class'] = "error";
    } else {
        // Thêm bonus vào tài khoản
        $soDu += $bonusAmount;
        
        // Lưu vào database
        $insertSql = "INSERT INTO daily_bonus (user_id, claim_date, day_streak, bonus_amount) VALUES (?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE day_streak = VALUES(day_streak), bonus_amount = VALUES(bonus_amount)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("isid", $userId, $today, $dayStreak, $bonusAmount);
        $insertStmt->execute();
        $insertStmt->close();
        
        // Cập nhật số dư
        $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $capNhat->bind_param("di", $soDu, $userId);
        $capNhat->execute();
        $capNhat->close();
        
        $_SESSION['daily_bonus_message'] = "🎉 Chúc mừng! Bạn nhận được " . number_format($bonusAmount, 0, ',', '.') . " VNĐ! (Streak: " . $dayStreak . " ngày)";
        $_SESSION['daily_bonus_class'] = "success";
        
        header("Location: daily_bonus.php");
        exit();
    }
}

if (isset($_SESSION['daily_bonus_message'])) {
    $thongBao = $_SESSION['daily_bonus_message'];
    $ketQuaClass = $_SESSION['daily_bonus_class'];
    unset($_SESSION['daily_bonus_message']);
    unset($_SESSION['daily_bonus_class']);
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
    <title>Daily Bonus</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/daily-bonus.css">
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
    <div class="daily-bonus-container">
        <div class="daily-bonus-box">
            <div class="daily-bonus-header">
                <h1 class="daily-bonus-title">🎁 Daily Bonus</h1>
                <div class="balance-display-bonus">
                    <span>💰</span>
                    <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
                </div>
            </div>
            
            <div class="bonus-display">
                <div class="streak-display">
                    <div class="streak-label">🔥 Streak</div>
                    <div class="streak-value"><?= $dayStreak ?> ngày</div>
                </div>
                <div class="bonus-amount-display">
                    <div class="bonus-label">Phần Thưởng</div>
                    <div class="bonus-value"><?= number_format($bonusAmount, 0, ',', '.') ?> VNĐ</div>
                </div>
            </div>
            
            <div class="streak-calendar">
                <h3>📅 Lịch Nhận Bonus</h3>
                <div class="calendar-grid">
                    <?php for ($i = 1; $i <= 7; $i++): ?>
                        <div class="calendar-day <?= $i <= $dayStreak ? 'claimed' : '' ?> <?= $i === $dayStreak && $canClaim ? 'today' : '' ?>">
                            <div class="day-number"><?= $i ?></div>
                            <div class="day-bonus"><?= number_format($baseBonus * $i, 0, ',', '.') ?> VNĐ</div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <?php if ($thongBao): ?>
                <div class="bonus-message <?= $ketQuaClass ?>">
                    <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            
            <?php if ($canClaim): ?>
                <form method="post" class="claim-form">
                    <input type="hidden" name="action" value="claim">
                    <button type="submit" class="claim-button">🎁 Nhận Bonus Ngay</button>
                </form>
            <?php else: ?>
                <div class="claim-info">
                    <p>⏰ Bạn đã nhận bonus hôm nay. Quay lại vào ngày mai để nhận tiếp!</p>
                </div>
            <?php endif; ?>
            
            <div class="bonus-info">
                <h3>📖 Thông Tin</h3>
                <ul>
                    <li>Nhận bonus mỗi ngày để tăng streak</li>
                    <li>Streak càng cao, bonus càng nhiều</li>
                    <li>Streak tối đa: 7 ngày (70,000 VNĐ)</li>
                    <li>Nếu bỏ lỡ 1 ngày, streak sẽ reset về 1</li>
                </ul>
            </div>
            
            <p style="text-align: center; margin-top: 20px;">
                <a href="index.php" style="color: #667eea; text-decoration: none; font-weight: 600;">🏠 Quay Lại Trang Chủ</a>
            </p>
        </div>
    </div>
</body>
</html>

