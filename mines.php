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
$minesResult = $_SESSION['mines_result'] ?? null;
$minesMessage = $_SESSION['mines_message'] ?? '';
$minesClass = $_SESSION['mines_class'] ?? '';
$minesWin = $_SESSION['minesWin'] ?? false;
$minesCashout = $_SESSION['mines_cashout'] ?? null;

unset($_SESSION['mines_result'], $_SESSION['mines_message'], $_SESSION['mines_class'], $_SESSION['minesWin'], $_SESSION['mines_cashout']);

// Cấu hình game
$gridSize = 5; // 5x5 grid
$maxMines = 3; // Tối đa 3 mìn

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'play_mines') {
    $cuoc = (int) str_replace(',', '', $_POST['cuoc'] ?? '0');
    $numMines = (int) ($_POST['num_mines'] ?? 1);
    $numMines = max(1, min($numMines, $maxMines));
    $action = $_POST['game_action'] ?? 'start';

    if ($cuoc <= 0 || $cuoc > $soDu) {
        $_SESSION['mines_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['mines_class'] = "thua";
    } else {
        if ($action === 'start') {
            // Tạo grid với mìn ngẫu nhiên
            $totalCells = $gridSize * $gridSize;
            $minePositions = [];
            $availablePositions = range(0, $totalCells - 1);
            shuffle($availablePositions);
            
            for ($i = 0; $i < $numMines; $i++) {
                $minePositions[] = $availablePositions[$i];
            }
            
            $_SESSION['mines_grid'] = $minePositions;
            $_SESSION['mines_revealed'] = [];
            $_SESSION['mines_bet'] = $cuoc;
            $_SESSION['mines_num_mines'] = $numMines;
            
            $_SESSION['mines_message'] = "🎮 Game bắt đầu! Chọn ô để mở. Cẩn thận với mìn!";
            $_SESSION['mines_class'] = "info";
        } elseif ($action === 'reveal') {
            $cellIndex = (int) ($_POST['cell_index'] ?? -1);
            $grid = $_SESSION['mines_grid'] ?? [];
            $revealed = $_SESSION['mines_revealed'] ?? [];
            $bet = $_SESSION['mines_bet'] ?? $cuoc;
            $numMines = $_SESSION['mines_num_mines'] ?? 1;
            
            if (in_array($cellIndex, $revealed)) {
                $_SESSION['mines_message'] = "⚠️ Ô này đã được mở!";
                $_SESSION['mines_class'] = "warning";
            } elseif (in_array($cellIndex, $grid)) {
                // Trúng mìn - thua
                $soDu -= $bet;
                $_SESSION['mines_message'] = "💣 Bạn trúng mìn! Mất " . number_format($bet) . " VNĐ";
                $_SESSION['mines_class'] = "thua";
                $_SESSION['minesWin'] = false;
                $_SESSION['mines_result'] = ['hit_mine' => true, 'cell' => $cellIndex];
                
                // Xóa session
                unset($_SESSION['mines_grid'], $_SESSION['mines_revealed'], $_SESSION['mines_bet'], $_SESSION['mines_num_mines']);
            } else {
                // An toàn - tính multiplier
                $revealed[] = $cellIndex;
                $safeCells = count($revealed);
                $multiplier = 1 + ($safeCells * 0.1); // Tăng 10% mỗi ô an toàn
                $potentialWin = $bet * $multiplier;
                
                $_SESSION['mines_revealed'] = $revealed;
                $_SESSION['mines_message'] = "✅ Ô an toàn! Multiplier: x" . number_format($multiplier, 2) . " | Tiềm năng: " . number_format($potentialWin) . " VNĐ";
                $_SESSION['mines_class'] = "info";
                $_SESSION['mines_result'] = ['safe' => true, 'cell' => $cellIndex, 'multiplier' => $multiplier];
            }
        } elseif ($action === 'cashout') {
            $revealed = $_SESSION['mines_revealed'] ?? [];
            $bet = $_SESSION['mines_bet'] ?? $cuoc;
            $numMines = $_SESSION['mines_num_mines'] ?? 1;
            
            if (empty($revealed)) {
                $_SESSION['mines_message'] = "⚠️ Chưa mở ô nào!";
                $_SESSION['mines_class'] = "warning";
            } else {
                $safeCells = count($revealed);
                $multiplier = 1 + ($safeCells * 0.1);
                $tienThang = $bet * $multiplier;
                $soDu = $soDu - $bet + $tienThang;
                
                $_SESSION['mines_message'] = "🎉 Cashout thành công! Thắng " . number_format($tienThang) . " VNĐ (x" . number_format($multiplier, 2) . ")";
                $_SESSION['mines_class'] = "thang";
                $_SESSION['minesWin'] = true;
                $_SESSION['mines_cashout'] = $tienThang;
                
                // Big win thông báo
                if ($tienThang >= 5000000) {
                    $message = "🎉 " . htmlspecialchars($tenNguoiChoi) . " thắng lớn " . number_format($tienThang, 0, ',', '.') . " VNĐ tại Mines!";
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
                
                // Log lịch sử
                logGameHistoryWithAll($conn, $userId, 'Mines', $bet, $tienThang, true);
                
                // Xóa session
                unset($_SESSION['mines_grid'], $_SESSION['mines_revealed'], $_SESSION['mines_bet'], $_SESSION['mines_num_mines']);
            }
        }
        
        // Cập nhật số dư
        $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        if ($capNhat) {
            $capNhat->bind_param("di", $soDu, $userId);
            $capNhat->execute();
            $capNhat->close();
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
        
        header("Location: mines.php");
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
    <title>Mines - Game Mìn</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/game-mines.css">
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
    <div class="mines-container-enhanced">
        <div class="game-box-mines-enhanced">
            <div class="game-header-mines-enhanced">
                <h1 class="game-title-mines-enhanced">💣 Mines</h1>
                <div class="balance-mines-enhanced">
                    <span>💰</span>
                    <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
                </div>
            </div>
            
            <?php if ($minesMessage): ?>
                <div class="result-banner-mines-enhanced <?= $minesClass === 'thang' ? 'win' : ($minesClass === 'thua' ? 'lose' : 'info') ?>">
                    <?= htmlspecialchars($minesMessage, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            
            <div class="game-controls-mines-enhanced">
                <form method="post" id="gameForm">
                    <input type="hidden" name="action" value="play_mines">
                    <input type="hidden" name="game_action" id="gameAction" value="start">
                    
                    <div class="control-group-mines-enhanced">
                        <label class="control-label-mines-enhanced">💣 Số mìn:</label>
                        <select name="num_mines" id="numMinesSelect" class="control-select-mines-enhanced" required>
                            <option value="1">1 mìn (Dễ)</option>
                            <option value="2">2 mìn (Trung bình)</option>
                            <option value="3" selected>3 mìn (Khó)</option>
                        </select>
                    </div>
                    
                    <div class="control-group-mines-enhanced">
                        <label class="control-label-mines-enhanced">💰 Số tiền cược:</label>
                        <input type="number" name="cuoc" id="cuocInput" class="control-input-mines-enhanced" placeholder="Nhập số tiền cược" required min="1">
                        <div class="bet-quick-amounts-mines-enhanced">
                            <button type="button" class="bet-quick-btn-mines-enhanced" data-amount="10000">10K</button>
                            <button type="button" class="bet-quick-btn-mines-enhanced" data-amount="50000">50K</button>
                            <button type="button" class="bet-quick-btn-mines-enhanced" data-amount="100000">100K</button>
                            <button type="button" class="bet-quick-btn-mines-enhanced" data-amount="200000">200K</button>
                        </div>
                    </div>
                    
                    <button type="submit" id="startButton" class="play-button-mines-enhanced">🎮 Bắt Đầu</button>
                </form>
            </div>
            
            <div id="minesGrid" class="mines-grid-enhanced" style="display: none;">
                <!-- Grid sẽ được tạo bằng JavaScript -->
            </div>
            
            <div id="gameInfo" class="game-info-mines-enhanced" style="display: none;">
                <div class="info-item">
                    <span>Multiplier hiện tại:</span>
                    <span id="currentMultiplier">x1.00</span>
                </div>
                <div class="info-item">
                    <span>Tiềm năng:</span>
                    <span id="potentialWin">0 VNĐ</span>
                </div>
                <button type="button" id="cashoutButton" class="cashout-button-mines-enhanced">💰 Cash Out</button>
            </div>
            
            <div class="payout-info-mines-enhanced">
                <div class="payout-title-mines-enhanced">📊 Multiplier</div>
                <div class="payout-list-mines-enhanced">
                    <div class="payout-item-mines-enhanced">
                        <div class="payout-count-mines-enhanced">1 ô</div>
                        <div class="payout-multiplier-mines-enhanced">x1.1</div>
                    </div>
                    <div class="payout-item-mines-enhanced">
                        <div class="payout-count-mines-enhanced">2 ô</div>
                        <div class="payout-multiplier-mines-enhanced">x1.2</div>
                    </div>
                    <div class="payout-item-mines-enhanced">
                        <div class="payout-count-mines-enhanced">3 ô</div>
                        <div class="payout-multiplier-mines-enhanced">x1.3</div>
                    </div>
                    <div class="payout-item-mines-enhanced">
                        <div class="payout-count-mines-enhanced">5 ô</div>
                        <div class="payout-multiplier-mines-enhanced">x1.5</div>
                    </div>
                    <div class="payout-item-mines-enhanced">
                        <div class="payout-count-mines-enhanced">10 ô</div>
                        <div class="payout-multiplier-mines-enhanced">x2.0</div>
                    </div>
                </div>
            </div>
            
            <p style="text-align: center; margin-top: 20px;">
                <a href="index.php" style="color: #e74c3c; text-decoration: none; font-weight: 600;">🏠 Quay Lại Trang Chủ</a>
            </p>
        </div>
    </div>
    
    <script src="assets/js/game-mines.js"></script>
    <script src="assets/js/game-animations-enhanced.js"></script>
</body>
</html>

