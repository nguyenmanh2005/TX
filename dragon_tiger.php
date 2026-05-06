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

$ketQua = $_SESSION['dragon_tiger_result'] ?? null;
$thongBao = $_SESSION['dragon_tiger_message'] ?? "";
$ketQuaClass = $_SESSION['dragon_tiger_class'] ?? "";
$laThang = $_SESSION['dragon_tiger_win'] ?? false;
$dragonCard = $_SESSION['dragon_tiger_dragon'] ?? null;
$tigerCard = $_SESSION['dragon_tiger_tiger'] ?? null;

unset($_SESSION['dragon_tiger_result']);
unset($_SESSION['dragon_tiger_message']);
unset($_SESSION['dragon_tiger_class']);
unset($_SESSION['dragon_tiger_win']);
unset($_SESSION['dragon_tiger_dragon']);
unset($_SESSION['dragon_tiger_tiger']);

// Hàm rút bài (1-13, A=1, J/Q/K=11/12/13)
function rutBaiDragonTiger() {
    return rand(1, 13);
}

// Chuyển số thành tên bài
function getCardNameDT($value) {
    $cards = [1 => 'A', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 
              8 => '8', 9 => '9', 10 => '10', 11 => 'J', 12 => 'Q', 13 => 'K'];
    return $cards[$value] ?? $value;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'play') {
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");
    $betOn = $_POST['bet_on'] ?? '';

    if ($cuoc > $soDu || $cuoc <= 0) {
        $_SESSION['dragon_tiger_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['dragon_tiger_class'] = "thua";
    } elseif (!in_array($betOn, ['dragon', 'tiger', 'tie'])) {
        $_SESSION['dragon_tiger_message'] = "⚠️ Chọn Dragon, Tiger hoặc Tie!";
        $_SESSION['dragon_tiger_class'] = "thua";
    } else {
        // Rút bài cho Dragon và Tiger
        $dragonCard = rutBaiDragonTiger();
        $tigerCard = rutBaiDragonTiger();
        
        // Xác định kết quả
        $winner = '';
        if ($dragonCard > $tigerCard) {
            $winner = 'dragon';
        } elseif ($tigerCard > $dragonCard) {
            $winner = 'tiger';
        } else {
            $winner = 'tie';
        }
        
        // Tính thắng thua
        $thang = 0;
        if ($betOn === $winner) {
            if ($winner === 'dragon' || $winner === 'tiger') {
                $thang = $cuoc * 2; // 1:1
            } else { // tie
                $thang = $cuoc * 9; // 8:1
            }
            $soDu += $thang;
            $_SESSION['dragon_tiger_message'] = "🎉 Thắng! " . ucfirst($winner) . " thắng! Thắng " . number_format($thang) . " VNĐ!";
            $_SESSION['dragon_tiger_class'] = "thang";
            $_SESSION['dragon_tiger_win'] = true;
        } else {
            $soDu -= $cuoc;
            $_SESSION['dragon_tiger_message'] = "😢 Thua! " . ucfirst($winner) . " thắng. Mất " . number_format($cuoc) . " VNĐ";
            $_SESSION['dragon_tiger_class'] = "thua";
            $_SESSION['dragon_tiger_win'] = false;
        }
        
        $_SESSION['dragon_tiger_dragon'] = $dragonCard;
        $_SESSION['dragon_tiger_tiger'] = $tigerCard;
        
        $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $capNhat->bind_param("di", $soDu, $userId);
        $capNhat->execute();
        $capNhat->close();
        
        require_once 'game_history_helper.php';
        $winAmount = $laThang ? $thang : 0;
        logGameHistoryWithAll($conn, $userId, 'Dragon Tiger', $cuoc, $winAmount, $laThang);
        
        header("Location: dragon_tiger.php");
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
    <title>Dragon Tiger Game</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/game-dragon-tiger.css">
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
    <div class="dragon-tiger-container-enhanced">
        <div class="game-box-dragon-tiger-enhanced">
            <div class="game-header-dragon-tiger-enhanced">
                <h1 class="game-title-dragon-tiger-enhanced">🐉🐅 Dragon Tiger</h1>
                <div class="balance-dragon-tiger-enhanced">
                    <span>💰</span>
                    <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
                </div>
            </div>
            
            <div class="dragon-tiger-table">
                <div class="dragon-side">
                    <div class="side-label-dragon">🐉 Dragon</div>
                    <?php if ($dragonCard): ?>
                        <div class="dt-card dragon-card card-dealing animated-fade-in-up">
                            <div class="card-value-dt"><?= getCardNameDT($dragonCard) ?></div>
                        </div>
                    <?php else: ?>
                        <div class="dt-card-placeholder">?</div>
                    <?php endif; ?>
                </div>
                
                <div class="vs-divider">VS</div>
                
                <div class="tiger-side">
                    <div class="side-label-tiger">🐅 Tiger</div>
                    <?php if ($tigerCard): ?>
                        <div class="dt-card tiger-card card-dealing animated-fade-in-up">
                            <div class="card-value-dt"><?= getCardNameDT($tigerCard) ?></div>
                        </div>
                    <?php else: ?>
                        <div class="dt-card-placeholder">?</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($thongBao): ?>
                <div class="result-banner-dragon-tiger-enhanced <?= $ketQuaClass === 'thang' ? 'win animate-win' : 'lose animate-lose' ?>">
                    <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            
            <div class="game-controls-dragon-tiger-enhanced">
                <form method="post" id="dragonTigerForm">
                    <input type="hidden" name="action" value="play">
                    <div class="control-group-dragon-tiger-enhanced">
                        <label class="control-label-dragon-tiger-enhanced">🎯 Đặt cược:</label>
                        <div class="bet-selector-dragon-tiger">
                            <label class="bet-option-dragon-tiger dragon-option">
                                <input type="radio" name="bet_on" value="dragon" checked>
                                <span class="bet-icon">🐉</span>
                                <span class="bet-label-dt">Dragon (1:1)</span>
                            </label>
                            <label class="bet-option-dragon-tiger tie-option">
                                <input type="radio" name="bet_on" value="tie">
                                <span class="bet-icon">⚡</span>
                                <span class="bet-label-dt">Tie (8:1)</span>
                            </label>
                            <label class="bet-option-dragon-tiger tiger-option">
                                <input type="radio" name="bet_on" value="tiger">
                                <span class="bet-icon">🐅</span>
                                <span class="bet-label-dt">Tiger (1:1)</span>
                            </label>
                        </div>
                    </div>
                    <div class="control-group-dragon-tiger-enhanced">
                        <label class="control-label-dragon-tiger-enhanced">💰 Số tiền cược:</label>
                        <input type="number" name="cuoc" id="cuocInput" class="control-input-dragon-tiger-enhanced" placeholder="Nhập số tiền cược" required min="1">
                        <div class="bet-quick-amounts-dragon-tiger-enhanced">
                            <button type="button" class="bet-quick-btn-dragon-tiger-enhanced" data-amount="10000">10K</button>
                            <button type="button" class="bet-quick-btn-dragon-tiger-enhanced" data-amount="50000">50K</button>
                            <button type="button" class="bet-quick-btn-dragon-tiger-enhanced" data-amount="100000">100K</button>
                            <button type="button" class="bet-quick-btn-dragon-tiger-enhanced" data-amount="200000">200K</button>
                        </div>
                    </div>
                    <button type="submit" class="play-button-dragon-tiger-enhanced">🎮 Chơi Dragon Tiger</button>
                </form>
            </div>
            
            <div class="dragon-tiger-info-enhanced">
                <h3>📖 Cách Chơi</h3>
                <ul>
                    <li>Chọn Dragon, Tiger hoặc Tie</li>
                    <li>Dragon/Tiger thắng: 1:1</li>
                    <li>Tie: 8:1</li>
                    <li>Lá bài cao hơn thắng</li>
                </ul>
            </div>
            
            <p style="text-align: center; margin-top: 20px;">
                <a href="index.php" style="color: #667eea; text-decoration: none; font-weight: 600;">🏠 Quay Lại Trang Chủ</a>
            </p>
        </div>
    </div>
    
    <script src="assets/js/game-dragon-tiger.js"></script>
    <script src="assets/js/game-animations-enhanced.js"></script>
    <script src="assets/js/sound-effects.js"></script>
</body>
</html>
