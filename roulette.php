<?php
session_start();

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
if (!$stmt) {
    die("Lỗi prepare statement: " . $conn->error);
}
$stmt->bind_param("i", $userId);
if (!$stmt->execute()) {
    die("Lỗi execute: " . $stmt->error);
}
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) {
    die("Không tìm thấy thông tin người dùng!");
}
$soDu = $user['Money'];
$tenNguoiChoi = $user['Name'];

// Lấy kết quả từ session (nếu có)
$ketQua = $_SESSION['roulette_result'] ?? 0;
$mauKetQua = $_SESSION['roulette_color'] ?? "";
$thongBao = $_SESSION['roulette_message'] ?? "";
$ketQuaClass = $_SESSION['roulette_class'] ?? "";
$laThang = $_SESSION['roulette_win'] ?? false;

// Xóa session sau khi lấy
unset($_SESSION['roulette_result']);
unset($_SESSION['roulette_color']);
unset($_SESSION['roulette_message']);
unset($_SESSION['roulette_class']);
unset($_SESSION['roulette_win']);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'spin_roulette') {
    $chon = isset($_POST["chon"]) ? trim($_POST["chon"]) : "";
    $cuocRaw = $_POST["cuoc"] ?? "0";
    $cuoc = (int) str_replace([",", ".", " "], "", $cuocRaw);

    if (!in_array($chon, ["Đỏ", "Đen", "Xanh"])) {
        $_SESSION['roulette_message'] = "❌ Chọn màu hợp lệ!";
        $_SESSION['roulette_class'] = "thua";
    } elseif ($cuoc > $soDu || $cuoc <= 0) {
        $_SESSION['roulette_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['roulette_class'] = "thua";
    } else {
        $ketQua = rand(0, 36);
        
        // 0 = Xanh, số lẻ = Đỏ, số chẵn = Đen
        $mauKetQua = "";
        if ($ketQua === 0) {
            $mauKetQua = "Xanh";
        } elseif ($ketQua % 2 === 0) {
            $mauKetQua = "Đen";
        } else {
            $mauKetQua = "Đỏ";
        }
        
        $thang = 0;
        $laThang = false;
        
        if ($chon === $mauKetQua) {
            $thang = ($ketQua === 0) ? $cuoc * 14 : $cuoc * 2; // Xanh x14, Đỏ/Đen x2
            $soDu += $thang;
            $_SESSION['roulette_message'] = "🎉 Bạn thắng " . number_format($thang) . " VNĐ! Kết quả: " . $ketQua . " (" . $mauKetQua . ")";
            $_SESSION['roulette_class'] = "thang";
            $_SESSION['roulette_win'] = true;
            $laThang = true;
            
            if ($thang >= 5000000) {
                $message = "🎉 " . htmlspecialchars($tenNguoiChoi) . " vừa thắng lớn " . number_format($thang, 0, ',', '.') . " VNĐ trong Roulette! 🎊";
                $expiresAt = date('Y-m-d H:i:s', time() + 30);
                $checkTable = $conn->query("SHOW TABLES LIKE 'server_notifications'");
                if ($checkTable && $checkTable->num_rows > 0) {
                    $insertSql = "INSERT INTO server_notifications (user_id, user_name, message, amount, notification_type, expires_at) VALUES (?, ?, ?, ?, 'big_win', ?)";
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
            $_SESSION['roulette_message'] = "😢 Bạn mất " . number_format($cuoc) . " VNĐ. Kết quả: " . $ketQua . " (" . $mauKetQua . ")";
            $_SESSION['roulette_class'] = "thua";
            $_SESSION['roulette_win'] = false;
            $laThang = false;
        }

        // Cập nhật số dư
        $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        if ($capNhat) {
            $capNhat->bind_param("di", $soDu, $userId);
            if (!$capNhat->execute()) {
                error_log("Lỗi cập nhật số dư: " . $capNhat->error);
            }
            $capNhat->close();
        } else {
            error_log("Lỗi prepare update: " . $conn->error);
        }
        
        // Track quest progress và tự động cập nhật streak, VIP, reward points, social feed
        require_once 'game_history_helper.php';
        logGameHistoryWithAll($conn, $userId, 'Roulette', $cuoc, $thang, $laThang);
        
        // Lưu kết quả vào session
        $_SESSION['roulette_result'] = $ketQua;
        $_SESSION['roulette_color'] = $mauKetQua;
        
        // Redirect để tránh resubmit
        header("Location: roulette.php");
        exit();
    }
}

// Luôn reload số dư từ database để đảm bảo chính xác
$reloadSql = "SELECT Money FROM users WHERE Iduser = ?";
$reloadStmt = $conn->prepare($reloadSql);
if ($reloadStmt) {
    $reloadStmt->bind_param("i", $userId);
    if ($reloadStmt->execute()) {
    $reloadResult = $reloadStmt->get_result();
    $reloadUser = $reloadResult->fetch_assoc();
    if ($reloadUser) {
        $soDu = $reloadUser['Money'];
        }
    } else {
        error_log("Lỗi reload số dư: " . $reloadStmt->error);
    }
    $reloadStmt->close();
} else {
    error_log("Lỗi prepare reload: " . $conn->error);
}
if (isset($stmt) && $stmt) {
    $stmt->close();
}

// Tính lại màu kết quả nếu có kết quả (chỉ khi chưa có từ session)
if (isset($ketQua) && $ketQua !== null && $ketQua !== "" && empty($mauKetQua)) {
    if ($ketQua === 0) {
        $mauKetQua = "Xanh";
    } elseif ($ketQua % 2 === 0) {
        $mauKetQua = "Đen";
    } else {
        $mauKetQua = "Đỏ";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Roulette</title>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhancements.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
        <link rel="stylesheet" href="assets/css/game-effects.css">
    <link rel="stylesheet" href="assets/css/game-roulette.css">
    <link rel="stylesheet" href="assets/css/game-animations.css">
    <link rel="stylesheet" href="assets/css/game-specific-animations.css">
</head>
<body class="game-body" style="background: <?= $bgGradientCSS ?>; background-attachment: fixed;">
    <div class="game-container-enhanced roulette-container">
        <header class="game-header-enhanced">
            <div>
                <p class="breadcrumb-mini">Game cổ điển</p>
                <h1 class="game-title-enhanced">Roulette</h1>
            </div>
            <div class="game-balance-enhanced">
                <span class="balance-icon">💰</span>
                <span class="balance-value"><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
            </div>
        </header>

        <div class="roulette-layout">
            <section class="card-modern wheel-card">
        <div class="roulette-wheel">
                    <div class="wheel-inner" style="--spin-angle: <?= (isset($ketQua) && $ketQua !== null && $ketQua !== "") ? (360 * 5 + ($ketQua * 9.73)) : 0 ?>deg;">
                        <div class="wheel-gradient"></div>
                        <div class="wheel-pointer">▼</div>
                    </div>
                    <div class="result-number"><?= (isset($ketQua) && $ketQua !== null && $ketQua !== "") ? htmlspecialchars($ketQua, ENT_QUOTES, 'UTF-8') : '?' ?></div>
        </div>
                <div class="legend">
                    <span class="legend-dot dot-red"></span> Đỏ (số lẻ) x2 •
                    <span class="legend-dot dot-black"></span> Đen (số chẵn) x2 •
                    <span class="legend-dot dot-green"></span> Xanh (0) x14
        </div>
            </section>
    
            <section class="card-modern control-card">
                <form method="post" id="gameForm" class="game-controls-enhanced">
        <input type="hidden" name="action" value="spin_roulette">
                    <div class="control-group-enhanced">
                        <label class="control-label-enhanced">🎯 Chọn màu</label>
                        <select name="chon" id="chonSelect" class="control-select-enhanced" required>
            <option value="">-- Chọn màu --</option>
            <option value="Đỏ">🔴 Đỏ (Số lẻ) - x2</option>
            <option value="Đen">⚫ Đen (Số chẵn) - x2</option>
            <option value="Xanh">🟢 Xanh (Số 0) - x14</option>
                        </select>
                    </div>
                    <div class="control-group-enhanced">
                        <label class="control-label-enhanced">💰 Số tiền cược</label>
                        <input type="number" name="cuoc" id="cuocInput" class="control-input-enhanced bet-amount-input" placeholder="Nhập số tiền cược" required min="1">
                        <div class="bet-quick-amounts-enhanced">
                            <?php foreach ([10000, 50000, 100000, 200000, 500000] as $amt): ?>
                                <button type="button" class="bet-quick-btn-enhanced" data-amount="<?= $amt ?>"><?= number_format($amt,0,',','.') ?>đ</button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" id="submitBtn" class="game-btn-enhanced game-btn-primary-enhanced btn-spin">🎡 Quay Roulette</button>
                    <p class="helper-text">Mẹo: Xanh rủi ro cao (x14), Đỏ/Đen an toàn hơn.</p>
    </form>

    <?php if ($thongBao): ?>
                    <div class="result-banner <?= $ketQuaClass === 'thang' ? 'result-win' : 'result-lose shake-on-lose' ?>">
            <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>
            </section>
</div>

        <div class="game-footer-links">
            <a class="text-link" href="index.php">🏠 Trang chủ</a>
            <a class="text-link" href="leaderboard.php">🏆 Bảng xếp hạng</a>
        </div>
    </div>

    <script src="assets/js/game-ui-enhanced.js"></script>
    <script src="assets/js/game-roulette.js"></script>
    <script src="assets/js/game-animations-enhanced.js"></script>
<script>
        document.addEventListener('DOMContentLoaded', () => {
            new GameUIEnhanced();
            if (window.GameRoulette) { window.GameRoulette.init(); }
        });
</script>
</body>
</html>

