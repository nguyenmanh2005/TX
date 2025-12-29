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

$ketQua = $_SESSION['dice_roll_result'] ?? [];
$thongBao = $_SESSION['dice_roll_message'] ?? "";
$ketQuaClass = $_SESSION['dice_roll_class'] ?? "";
$laThang = $_SESSION['dice_roll_win'] ?? false;
$total = $_SESSION['dice_roll_total'] ?? 0;

unset($_SESSION['dice_roll_result']);
unset($_SESSION['dice_roll_message']);
unset($_SESSION['dice_roll_class']);
unset($_SESSION['dice_roll_win']);
unset($_SESSION['dice_roll_total']);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'roll') {
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");
    $numDice = isset($_POST['num_dice']) ? (int)$_POST['num_dice'] : 2;
    $betType = $_POST['bet_type'] ?? 'total';
    $betValue = isset($_POST['bet_value']) ? (int)$_POST['bet_value'] : 0;

    if ($cuoc > $soDu || $cuoc <= 0) {
        $_SESSION['dice_roll_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['dice_roll_class'] = "thua";
    } elseif ($numDice < 2 || $numDice > 5) {
        $_SESSION['dice_roll_message'] = "⚠️ Chọn từ 2 đến 5 xúc xắc!";
        $_SESSION['dice_roll_class'] = "thua";
    } else {
        // Roll dice
        $diceResults = [];
        $total = 0;
        for ($i = 0; $i < $numDice; $i++) {
            $roll = rand(1, 6);
            $diceResults[] = $roll;
            $total += $roll;
        }
        
        $thang = 0;
        $win = false;
        
        if ($betType === 'total') {
            // Bet on total sum
            if ($total === $betValue) {
                $multiplier = $numDice * 2; // More dice = higher multiplier
                $thang = $cuoc * $multiplier;
                $win = true;
            }
        } elseif ($betType === 'high') {
            // Bet on high (total > average)
            $average = $numDice * 3.5;
            if ($total > $average) {
                $thang = $cuoc * 2;
                $win = true;
            }
        } elseif ($betType === 'low') {
            // Bet on low (total < average)
            $average = $numDice * 3.5;
            if ($total < $average) {
                $thang = $cuoc * 2;
                $win = true;
            }
        } elseif ($betType === 'even') {
            // Bet on even total
            if ($total % 2 === 0) {
                $thang = $cuoc * 2;
                $win = true;
            }
        } elseif ($betType === 'odd') {
            // Bet on odd total
            if ($total % 2 === 1) {
                $thang = $cuoc * 2;
                $win = true;
            }
        }
        
        if ($win) {
            $soDu += $thang;
            $_SESSION['dice_roll_message'] = "🎉 Thắng! Tổng: " . $total . ". Thắng " . number_format($thang) . " VNĐ!";
            $_SESSION['dice_roll_class'] = "thang";
            $_SESSION['dice_roll_win'] = true;
        } else {
            $soDu -= $cuoc;
            $_SESSION['dice_roll_message'] = "😢 Thua! Tổng: " . $total . ". Mất " . number_format($cuoc) . " VNĐ";
            $_SESSION['dice_roll_class'] = "thua";
            $_SESSION['dice_roll_win'] = false;
        }
        
        $_SESSION['dice_roll_result'] = $diceResults;
        $_SESSION['dice_roll_total'] = $total;
        
        $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $capNhat->bind_param("di", $soDu, $userId);
        $capNhat->execute();
        $capNhat->close();
        
        require_once 'game_history_helper.php';
        $winAmount = $laThang ? $thang : 0;
        logGameHistoryWithAll($conn, $userId, 'Dice Roll', $cuoc, $winAmount, $laThang);
        
        header("Location: dice_roll.php");
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
    <title>Dice Roll - Nhiều Xúc Xắc</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/game-dice-roll.css">
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
    <div class="dice-roll-container-enhanced">
        <div class="game-box-dice-roll-enhanced">
            <div class="game-header-dice-roll-enhanced">
                <h1 class="game-title-dice-roll-enhanced">🎲 Dice Roll</h1>
                <div class="balance-dice-roll-enhanced">
                    <span>💰</span>
                    <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
                </div>
            </div>
            
            <div class="dice-display-enhanced">
                <?php if (!empty($ketQua)): ?>
                    <div class="dice-results animated-fade-in-up">
                        <?php foreach ($ketQua as $dice): ?>
                            <div class="dice-item animate-bounce-in" style="animation-delay: <?= array_search($dice, $ketQua) * 0.1 ?>s;">
                                <div class="dice-face"><?= $diceEmoji[$dice] ?></div>
                                <div class="dice-value"><?= $dice ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="total-display animated-scale-in">
                        <div class="total-label">Tổng</div>
                        <div class="total-value"><?= $total ?></div>
                    </div>
                <?php else: ?>
                    <div class="dice-placeholder">
                        <div class="placeholder-text">Chọn số xúc xắc và đặt cược</div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($thongBao): ?>
                <div class="result-banner-dice-roll-enhanced <?= $ketQuaClass === 'thang' ? 'win animate-win' : 'lose animate-lose' ?>">
                    <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            
            <div class="game-controls-dice-roll-enhanced">
                <form method="post" id="diceRollForm">
                    <input type="hidden" name="action" value="roll">
                    <div class="control-group-dice-roll-enhanced">
                        <label class="control-label-dice-roll-enhanced">🎲 Số lượng xúc xắc (2-5):</label>
                        <select name="num_dice" id="numDiceSelect" class="control-select-dice-roll-enhanced">
                            <option value="2">2 xúc xắc</option>
                            <option value="3">3 xúc xắc</option>
                            <option value="4">4 xúc xắc</option>
                            <option value="5">5 xúc xắc</option>
                        </select>
                    </div>
                    <div class="control-group-dice-roll-enhanced">
                        <label class="control-label-dice-roll-enhanced">🎯 Loại cược:</label>
                        <div class="bet-type-selector">
                            <label class="bet-type-option">
                                <input type="radio" name="bet_type" value="total" checked>
                                <span>Đoán tổng</span>
                            </label>
                            <label class="bet-type-option">
                                <input type="radio" name="bet_type" value="high">
                                <span>Cao (> trung bình)</span>
                            </label>
                            <label class="bet-type-option">
                                <input type="radio" name="bet_type" value="low">
                                <span>Thấp (< trung bình)</span>
                            </label>
                            <label class="bet-type-option">
                                <input type="radio" name="bet_type" value="even">
                                <span>Chẵn</span>
                            </label>
                            <label class="bet-type-option">
                                <input type="radio" name="bet_type" value="odd">
                                <span>Lẻ</span>
                            </label>
                        </div>
                        <div class="bet-value-input" id="betValueInput">
                            <label>Giá trị tổng:</label>
                            <input type="number" name="bet_value" id="betValue" min="2" max="30" value="7" required>
                        </div>
                    </div>
                    <div class="control-group-dice-roll-enhanced">
                        <label class="control-label-dice-roll-enhanced">💰 Số tiền cược:</label>
                        <input type="number" name="cuoc" id="cuocInput" class="control-input-dice-roll-enhanced" placeholder="Nhập số tiền cược" required min="1">
                        <div class="bet-quick-amounts-dice-roll-enhanced">
                            <button type="button" class="bet-quick-btn-dice-roll-enhanced" data-amount="10000">10K</button>
                            <button type="button" class="bet-quick-btn-dice-roll-enhanced" data-amount="50000">50K</button>
                            <button type="button" class="bet-quick-btn-dice-roll-enhanced" data-amount="100000">100K</button>
                            <button type="button" class="bet-quick-btn-dice-roll-enhanced" data-amount="200000">200K</button>
                        </div>
                    </div>
                    <button type="submit" class="roll-button-dice-roll-enhanced">🎲 Lắc Xúc Xắc</button>
                </form>
            </div>
            
            <div class="dice-roll-info-enhanced">
                <h3>📖 Cách Chơi</h3>
                <ul>
                    <li>Chọn số lượng xúc xắc (2-5)</li>
                    <li>Chọn loại cược: Đoán tổng, Cao, Thấp, Chẵn, Lẻ</li>
                    <li>Nếu đoán tổng, nhập giá trị tổng mong muốn</li>
                    <li>Multiplier tăng theo số lượng xúc xắc</li>
                </ul>
            </div>
            
            <p style="text-align: center; margin-top: 20px;">
                <a href="index.php" style="color: #667eea; text-decoration: none; font-weight: 600;">🏠 Quay Lại Trang Chủ</a>
            </p>
        </div>
    </div>
    
    <script src="assets/js/game-dice-roll.js"></script>
    <script src="assets/js/game-animations-enhanced.js"></script>
</body>
</html>

