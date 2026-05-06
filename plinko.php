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
$plinkoResult = $_SESSION['plinko_result'] ?? null;
$plinkoMultiplier = $_SESSION['plinko_multiplier'] ?? null;
$plinkoMessage = $_SESSION['plinko_message'] ?? '';
$plinkoClass = $_SESSION['plinko_class'] ?? '';
$plinkoWin = $_SESSION['plinko_win'] ?? false;

unset($_SESSION['plinko_result'], $_SESSION['plinko_multiplier'], $_SESSION['plinko_message'], $_SESSION['plinko_class'], $_SESSION['plinko_win']);

// Cấu hình hệ số theo độ rủi ro
$riskConfigs = [
    'low' => [0.5, 0.7, 1.0, 1.3, 1.5, 2.0, 3.0, 5.0],
    'medium' => [0.3, 0.6, 0.9, 1.4, 2.0, 3.5, 5.0, 9.0],
    'high' => [0.2, 0.4, 0.8, 1.6, 2.5, 4.0, 8.0, 14.0],
];

// Phân bố xác suất (8 slot) gần Pascal để cân bằng
$slotWeights = [1, 3, 6, 10, 10, 6, 3, 1];
$slotCount = count($slotWeights);

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'drop_plinko') {
    $cuoc = (int) str_replace(',', '', $_POST['cuoc'] ?? '0');
    $risk = $_POST['risk'] ?? 'medium';
    $risk = array_key_exists($risk, $riskConfigs) ? $risk : 'medium';

    if ($cuoc <= 0 || $cuoc > $soDu) {
        $_SESSION['plinko_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['plinko_class'] = "thua";
    } else {
        // Chọn slot theo weight
        $totalWeight = array_sum($slotWeights);
        $rand = rand(1, $totalWeight);
        $slotIndex = 0;
        $acc = 0;
        foreach ($slotWeights as $i => $w) {
            $acc += $w;
            if ($rand <= $acc) { $slotIndex = $i; break; }
        }

        $multiplier = $riskConfigs[$risk][$slotIndex];
        $tienThang = $cuoc * $multiplier;
        // Kết quả thực: trừ cược, cộng payout
        $soDu = $soDu - $cuoc + $tienThang;
        $laThang = $multiplier >= 1.0;

        $msgBase = $laThang ? "🎉 Bạn thắng " . number_format($tienThang, 0, ',', '.') . " VNĐ" : "😢 Bạn mất " . number_format($cuoc - $tienThang, 0, ',', '.') . " VNĐ";
        $detail = " | Slot: #" . ($slotIndex + 1) . " • Hệ số x" . rtrim(rtrim(number_format($multiplier, 2, '.', ''), '0'), '.');
        $_SESSION['plinko_message'] = $msgBase . $detail;
        $_SESSION['plinko_class'] = $laThang ? "thang" : "thua";
        $_SESSION['plinko_result'] = $slotIndex;
        $_SESSION['plinko_multiplier'] = $multiplier;
        $_SESSION['plinko_win'] = $laThang;

        // Big win thông báo
        if ($tienThang >= 5000000) {
            $message = "🎉 " . htmlspecialchars($tenNguoiChoi) . " thắng lớn " . number_format($tienThang, 0, ',', '.') . " VNĐ tại Plinko!";
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

        // Log lịch sử & quests
        logGameHistoryWithAll($conn, $userId, 'Plinko', $cuoc, $tienThang, $laThang);
    }

    // Reload để tránh resubmit
    header("Location: plinko.php");
    exit();
}

// Luôn reload số dư mới nhất
$reloadStmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
if ($reloadStmt) {
    $reloadStmt->bind_param("i", $userId);
    $reloadStmt->execute();
    $reloadRes = $reloadStmt->get_result()->fetch_assoc();
    if ($reloadRes) { $soDu = $reloadRes['Money']; }
    $reloadStmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Plinko</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhancements.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/game-effects.css">
    <link rel="stylesheet" href="assets/css/game-plinko.css">
    <link rel="stylesheet" href="assets/css/game-animations.css">
    <link rel="stylesheet" href="assets/css/game-specific-animations.css">
</head>
<body class="game-body" style="background: <?= $bgGradientCSS ?>; background-attachment: fixed;">
    <div class="game-container plinko-container">
        <header class="game-header-enhanced">
            <div>
                <p class="breadcrumb-mini">Mini-game mới</p>
                <h1 class="game-title-enhanced">Plinko</h1>
            </div>
            <div class="game-balance-enhanced">
                <span class="balance-icon">💰</span>
                <span class="balance-value"><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
            </div>
        </header>

        <div class="game-layout">
            <section class="plinko-board-card card-modern">
                <div class="plinko-board">
                    <?php
                        // Vẽ slot payout
                        $currentRisk = $_GET['risk'] ?? 'medium';
                        $currentRisk = array_key_exists($currentRisk, $riskConfigs) ? $currentRisk : 'medium';
                        $payouts = $riskConfigs[$currentRisk];
                        foreach ($payouts as $index => $multi):
                    ?>
                        <div class="plinko-slot <?= $plinkoResult === $index ? 'active-slot' : '' ?>">
                            <span class="slot-multiplier">x<?= rtrim(rtrim(number_format($multi, 2, '.', ''), '0'), '.') ?></span>
                            <div class="slot-well"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="plinko-legend">
                    <span class="legend-dot dot-low"></span> Low •
                    <span class="legend-dot dot-medium"></span> Medium •
                    <span class="legend-dot dot-high"></span> High
                </div>
            </section>

            <section class="card-modern control-panel">
                <form method="post" class="plinko-form" id="plinkoForm">
                    <input type="hidden" name="action" value="drop_plinko">
                    <div class="control-group-enhanced">
                        <label class="control-label-enhanced">💰 Số tiền cược</label>
                        <input type="number" name="cuoc" id="cuocInput" placeholder="Nhập số tiền" min="1" required>
                        <div class="bet-quick-amounts-enhanced">
                            <?php foreach ([10000,50000,100000,200000] as $amt): ?>
                                <button type="button" class="bet-quick-btn-enhanced" data-amount="<?= $amt ?>"><?= number_format($amt,0,',','.') ?>đ</button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="control-group-enhanced">
                        <label class="control-label-enhanced">🎯 Độ rủi ro</label>
                        <div class="risk-group">
                            <label class="chip">
                                <input type="radio" name="risk" value="low" <?= ($plinkoResult !== null && $plinkoMultiplier && $plinkoMultiplier <= 2) ? 'checked' : '' ?> required>
                                <span class="chip-label chip-low">Low</span>
                            </label>
                            <label class="chip">
                                <input type="radio" name="risk" value="medium" checked>
                                <span class="chip-label chip-medium">Medium</span>
                            </label>
                            <label class="chip">
                                <input type="radio" name="risk" value="high">
                                <span class="chip-label chip-high">High</span>
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="game-btn-enhanced game-btn-primary-enhanced btn-drop">🎯 Thả bóng</button>
                    <p class="note">Payout thay đổi theo độ rủi ro. Low an toàn, High payout cao.</p>
                </form>

                <?php if ($plinkoMessage): ?>
                    <div class="result-banner <?= $plinkoClass === 'thang' ? 'result-win' : 'result-lose' ?>">
                        <?= htmlspecialchars($plinkoMessage, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <div class="game-footer-links">
            <a class="text-link" href="index.php">🏠 Trang chủ</a>
        </div>
    </div>

    <script src="assets/js/game-plinko.js"></script>
    <script src="assets/js/game-animations-enhanced.js"></script>
    <?php if ($plinkoWin): ?>
        <script>document.addEventListener('DOMContentLoaded', ()=>{ if (window.GamePlinko) { GamePlinko.fireConfetti(); } });</script>
    <?php endif; ?>
</body>
</html>


