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

$ketQua = $_SESSION['baccarat_result'] ?? null;
$thongBao = $_SESSION['baccarat_message'] ?? "";
$ketQuaClass = $_SESSION['baccarat_class'] ?? "";
$laThang = $_SESSION['baccarat_win'] ?? false;
$playerCards = $_SESSION['baccarat_player_cards'] ?? [];
$bankerCards = $_SESSION['baccarat_banker_cards'] ?? [];
$playerTotal = $_SESSION['baccarat_player_total'] ?? 0;
$bankerTotal = $_SESSION['baccarat_banker_total'] ?? 0;

unset($_SESSION['baccarat_result']);
unset($_SESSION['baccarat_message']);
unset($_SESSION['baccarat_class']);
unset($_SESSION['baccarat_win']);
unset($_SESSION['baccarat_player_cards']);
unset($_SESSION['baccarat_banker_cards']);
unset($_SESSION['baccarat_player_total']);
unset($_SESSION['baccarat_banker_total']);

// Hàm rút bài Baccarat (A=1, J/Q/K=0, số khác = giá trị)
function rutBaiBaccarat() {
    $bai = rand(1, 13);
    if ($bai == 1) return 1; // Át
    if ($bai > 10) return 0; // J/Q/K
    return $bai;
}

// Tính điểm Baccarat (chỉ lấy số cuối)
function tinhDiemBaccarat($cards) {
    $total = 0;
    foreach ($cards as $card) {
        $total += $card;
    }
    return $total % 10; // Chỉ lấy số cuối
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'play') {
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");
    $betOn = $_POST['bet_on'] ?? '';

    if ($cuoc > $soDu || $cuoc <= 0) {
        $_SESSION['baccarat_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['baccarat_class'] = "thua";
    } elseif (!in_array($betOn, ['player', 'banker', 'tie'])) {
        $_SESSION['baccarat_message'] = "⚠️ Chọn Player, Banker hoặc Tie!";
        $_SESSION['baccarat_class'] = "thua";
    } else {
        // Rút bài cho Player và Banker
        $playerCards = [rutBaiBaccarat(), rutBaiBaccarat()];
        $bankerCards = [rutBaiBaccarat(), rutBaiBaccarat()];
        
        $playerTotal = tinhDiemBaccarat($playerCards);
        $bankerTotal = tinhDiemBaccarat($bankerCards);
        
        // Baccarat rules: Rút thêm bài nếu cần
        // Player: Nếu tổng <= 5, rút thêm 1 bài
        if ($playerTotal <= 5) {
            $playerCards[] = rutBaiBaccarat();
            $playerTotal = tinhDiemBaccarat($playerCards);
        }
        
        // Banker: Phức tạp hơn, đơn giản hóa: nếu <= 5 rút thêm
        if ($bankerTotal <= 5) {
            $bankerCards[] = rutBaiBaccarat();
            $bankerTotal = tinhDiemBaccarat($bankerCards);
        }
        
        // Xác định kết quả
        $winner = '';
        if ($playerTotal > $bankerTotal) {
            $winner = 'player';
        } elseif ($bankerTotal > $playerTotal) {
            $winner = 'banker';
        } else {
            $winner = 'tie';
        }
        
        // Tính thắng thua
        $thang = 0;
        if ($betOn === $winner) {
            if ($winner === 'player') {
                $thang = $cuoc * 2; // 1:1
            } elseif ($winner === 'banker') {
                $thang = $cuoc * 1.95; // 1:0.95 (commission)
            } else { // tie
                $thang = $cuoc * 9; // 8:1
            }
            $soDu += $thang;
            $_SESSION['baccarat_message'] = "🎉 Thắng! " . ucfirst($winner) . " thắng! Thắng " . number_format($thang) . " VNĐ!";
            $_SESSION['baccarat_class'] = "thang";
            $_SESSION['baccarat_win'] = true;
        } else {
            $soDu -= $cuoc;
            $_SESSION['baccarat_message'] = "😢 Thua! " . ucfirst($winner) . " thắng. Mất " . number_format($cuoc) . " VNĐ";
            $_SESSION['baccarat_class'] = "thua";
            $_SESSION['baccarat_win'] = false;
        }
        
        $_SESSION['baccarat_player_cards'] = $playerCards;
        $_SESSION['baccarat_banker_cards'] = $bankerCards;
        $_SESSION['baccarat_player_total'] = $playerTotal;
        $_SESSION['baccarat_banker_total'] = $bankerTotal;
        
        $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $capNhat->bind_param("di", $soDu, $userId);
        $capNhat->execute();
        $capNhat->close();
        
        require_once 'game_history_helper.php';
        $winAmount = $laThang ? $thang : 0;
        logGameHistoryWithAll($conn, $userId, 'Baccarat', $cuoc, $winAmount, $laThang);
        
        header("Location: baccarat.php");
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

function getCardDisplay($value) {
    if ($value == 0) return 'K';
    if ($value == 1) return 'A';
    return $value;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Baccarat Game</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/game-baccarat.css">
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
    <div class="baccarat-container-enhanced">
        <div class="game-box-baccarat-enhanced">
            <div class="game-header-baccarat-enhanced">
                <h1 class="game-title-baccarat-enhanced">🃏 Baccarat</h1>
                <div class="balance-baccarat-enhanced">
                    <span>💰</span>
                    <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
                </div>
            </div>
            
            <div class="baccarat-table">
                <div class="player-side">
                    <div class="side-label">Player</div>
                    <div class="cards-container-baccarat">
                        <?php if (!empty($playerCards)): ?>
                            <?php foreach ($playerCards as $index => $card): ?>
                                <div class="baccarat-card card-dealing" style="animation-delay: <?= $index * 0.2 ?>s;">
                                    <div class="card-value"><?= getCardDisplay($card) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($playerTotal > 0): ?>
                        <div class="total-display-baccarat">Tổng: <?= $playerTotal ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="banker-side">
                    <div class="side-label">Banker</div>
                    <div class="cards-container-baccarat">
                        <?php if (!empty($bankerCards)): ?>
                            <?php foreach ($bankerCards as $index => $card): ?>
                                <div class="baccarat-card card-dealing" style="animation-delay: <?= $index * 0.2 ?>s;">
                                    <div class="card-value"><?= getCardDisplay($card) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($bankerTotal > 0): ?>
                        <div class="total-display-baccarat">Tổng: <?= $bankerTotal ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($thongBao): ?>
                <div class="result-banner-baccarat-enhanced <?= $ketQuaClass === 'thang' ? 'win animate-win' : 'lose animate-lose' ?>">
                    <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            
            <div class="game-controls-baccarat-enhanced">
                <form method="post" id="baccaratForm">
                    <input type="hidden" name="action" value="play">
                    <div class="control-group-baccarat-enhanced">
                        <label class="control-label-baccarat-enhanced">🎯 Đặt cược:</label>
                        <div class="bet-selector-baccarat">
                            <label class="bet-option-baccarat">
                                <input type="radio" name="bet_on" value="player" checked>
                                <span class="bet-label">Player (1:1)</span>
                            </label>
                            <label class="bet-option-baccarat">
                                <input type="radio" name="bet_on" value="banker">
                                <span class="bet-label">Banker (1:0.95)</span>
                            </label>
                            <label class="bet-option-baccarat">
                                <input type="radio" name="bet_on" value="tie">
                                <span class="bet-label">Tie (8:1)</span>
                            </label>
                        </div>
                    </div>
                    <div class="control-group-baccarat-enhanced">
                        <label class="control-label-baccarat-enhanced">💰 Số tiền cược:</label>
                        <input type="number" name="cuoc" id="cuocInput" class="control-input-baccarat-enhanced" placeholder="Nhập số tiền cược" required min="1">
                        <div class="bet-quick-amounts-baccarat-enhanced">
                            <button type="button" class="bet-quick-btn-baccarat-enhanced" data-amount="10000">10K</button>
                            <button type="button" class="bet-quick-btn-baccarat-enhanced" data-amount="50000">50K</button>
                            <button type="button" class="bet-quick-btn-baccarat-enhanced" data-amount="100000">100K</button>
                            <button type="button" class="bet-quick-btn-baccarat-enhanced" data-amount="200000">200K</button>
                        </div>
                    </div>
                    <button type="submit" class="play-button-baccarat-enhanced">🃏 Chơi Baccarat</button>
                </form>
            </div>
            
            <div class="baccarat-info-enhanced">
                <h3>📖 Cách Chơi</h3>
                <ul>
                    <li>Chọn Player, Banker hoặc Tie</li>
                    <li>Player thắng: 1:1</li>
                    <li>Banker thắng: 1:0.95 (có commission)</li>
                    <li>Tie: 8:1</li>
                    <li>Điểm cao nhất (≤9) thắng</li>
                </ul>
            </div>
            
            <p style="text-align: center; margin-top: 20px;">
                <a href="index.php" style="color: #667eea; text-decoration: none; font-weight: 600;">🏠 Quay Lại Trang Chủ</a>
            </p>
        </div>
    </div>
    
    <script src="assets/js/game-baccarat.js"></script>
    <script src="assets/js/game-animations-enhanced.js"></script>
</body>
</html>
