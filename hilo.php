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

$ketQua = $_SESSION['hilo_result'] ?? null;
$thongBao = $_SESSION['hilo_message'] ?? "";
$ketQuaClass = $_SESSION['hilo_class'] ?? "";
$laThang = $_SESSION['hilo_win'] ?? false;
$currentCard = $_SESSION['hilo_current_card'] ?? null;
$nextCard = $_SESSION['hilo_next_card'] ?? null;
$streak = $_SESSION['hilo_streak'] ?? 0;

unset($_SESSION['hilo_result']);
unset($_SESSION['hilo_message']);
unset($_SESSION['hilo_class']);
unset($_SESSION['hilo_win']);
unset($_SESSION['hilo_current_card']);
unset($_SESSION['hilo_next_card']);
unset($_SESSION['hilo_streak']);

// Hàm rút bài (1-13, A=1, J/Q/K=11/12/13)
function rutBaiHilo() {
    return rand(1, 13);
}

// Chuyển số thành tên bài
function getCardName($value) {
    $cards = [1 => 'A', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7', 
              8 => '8', 9 => '9', 10 => '10', 11 => 'J', 12 => 'Q', 13 => 'K'];
    return $cards[$value] ?? $value;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");
    $guess = $_POST['guess'] ?? '';

    if ($cuoc > $soDu || $cuoc <= 0) {
        $_SESSION['hilo_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['hilo_class'] = "thua";
    } elseif (!in_array($guess, ['higher', 'lower', 'same'])) {
        $_SESSION['hilo_message'] = "⚠️ Chọn Higher, Lower hoặc Same!";
        $_SESSION['hilo_class'] = "thua";
    } else {
        if ($action === 'start') {
            // Bắt đầu game mới
            $currentCard = rutBaiHilo();
            $nextCard = rutBaiHilo();
            $streak = 0;
            
            $_SESSION['hilo_current_card'] = $currentCard;
            $_SESSION['hilo_next_card'] = $nextCard;
            $_SESSION['hilo_streak'] = $streak;
            
            header("Location: hilo.php");
            exit();
        } elseif ($action === 'guess') {
            // Đoán
            $currentCard = $_SESSION['hilo_current_card'] ?? rutBaiHilo();
            $nextCard = $_SESSION['hilo_next_card'] ?? rutBaiHilo();
            $streak = $_SESSION['hilo_streak'] ?? 0;
            
            $win = false;
            $multiplier = 1.0;
            
            if ($guess === 'higher' && $nextCard > $currentCard) {
                $win = true;
                $multiplier = 2.0;
            } elseif ($guess === 'lower' && $nextCard < $currentCard) {
                $win = true;
                $multiplier = 2.0;
            } elseif ($guess === 'same' && $nextCard === $currentCard) {
                $win = true;
                $multiplier = 13.0; // Xác suất 1/13
            }
            
            if ($win) {
                $thang = $cuoc * $multiplier;
                $soDu += $thang;
                $streak++;
                
                $_SESSION['hilo_message'] = "🎉 Đúng! Thắng " . number_format($thang) . " VNĐ! (Streak: " . $streak . ")";
                $_SESSION['hilo_class'] = "thang";
                $_SESSION['hilo_win'] = true;
                
                // Tiếp tục với lá bài mới
                $_SESSION['hilo_current_card'] = $nextCard;
                $_SESSION['hilo_next_card'] = rutBaiHilo();
                $_SESSION['hilo_streak'] = $streak;
            } else {
                $soDu -= $cuoc;
                $streak = 0;
                
                $_SESSION['hilo_message'] = "😢 Sai! Mất " . number_format($cuoc) . " VNĐ";
                $_SESSION['hilo_class'] = "thua";
                $_SESSION['hilo_win'] = false;
                
                // Reset game
                unset($_SESSION['hilo_current_card']);
                unset($_SESSION['hilo_next_card']);
                unset($_SESSION['hilo_streak']);
            }
            
            $_SESSION['hilo_result'] = $nextCard;
            
            $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
            $capNhat->bind_param("di", $soDu, $userId);
            $capNhat->execute();
            $capNhat->close();
            
            require_once 'game_history_helper.php';
            $winAmount = $laThang ? $thang : 0;
            logGameHistoryWithAll($conn, $userId, 'Hi-Lo', $cuoc, $winAmount, $laThang);
            
            header("Location: hilo.php");
            exit();
        } elseif ($action === 'cashout') {
            // Cashout và nhận thưởng streak
            $streak = $_SESSION['hilo_streak'] ?? 0;
            if ($streak > 0) {
                $bonus = $cuoc * $streak * 0.5; // Bonus dựa trên streak
                $soDu += $bonus;
                
                $_SESSION['hilo_message'] = "💰 Cashout! Nhận bonus " . number_format($bonus) . " VNĐ từ streak " . $streak . "!";
                $_SESSION['hilo_class'] = "thang";
                
                $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
                $capNhat->bind_param("di", $soDu, $userId);
                $capNhat->execute();
                $capNhat->close();
                
                unset($_SESSION['hilo_current_card']);
                unset($_SESSION['hilo_next_card']);
                unset($_SESSION['hilo_streak']);
                
                header("Location: hilo.php");
                exit();
            }
        }
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
    <title>Hi-Lo Game</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/game-hilo.css">
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
    <div class="hilo-container-enhanced">
        <div class="game-box-hilo-enhanced">
            <div class="game-header-hilo-enhanced">
                <h1 class="game-title-hilo-enhanced">📈 Hi-Lo Game</h1>
                <div class="balance-hilo-enhanced">
                    <span>💰</span>
                    <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
                </div>
            </div>
            
            <div class="hilo-display-enhanced">
                <?php if ($currentCard): ?>
                    <div class="current-card-display animated-fade-in-up">
                        <div class="card-label">Lá bài hiện tại</div>
                        <div class="hilo-card current"><?= getCardName($currentCard) ?></div>
                    </div>
                    <div class="streak-display-hilo">
                        <div class="streak-label">🔥 Streak</div>
                        <div class="streak-value"><?= $streak ?></div>
                    </div>
                <?php else: ?>
                    <div class="start-prompt">
                        <div class="prompt-text">Nhấn "Bắt Đầu" để chơi!</div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($thongBao): ?>
                <div class="result-banner-hilo-enhanced <?= $ketQuaClass === 'thang' ? 'win animate-win' : 'lose animate-lose' ?>">
                    <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            
            <?php if ($nextCard && $ketQuaClass === 'thua'): ?>
                <div class="next-card-reveal animated-scale-in">
                    <div class="card-label">Lá bài tiếp theo</div>
                    <div class="hilo-card next"><?= getCardName($nextCard) ?></div>
                </div>
            <?php endif; ?>
            
            <div class="game-controls-hilo-enhanced">
                <?php if (!$currentCard): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="start">
                        <div class="control-group-hilo-enhanced">
                            <label class="control-label-hilo-enhanced">💰 Số tiền cược:</label>
                            <input type="number" name="cuoc" id="cuocInput" class="control-input-hilo-enhanced" placeholder="Nhập số tiền cược" required min="1">
                            <div class="bet-quick-amounts-hilo-enhanced">
                                <button type="button" class="bet-quick-btn-hilo-enhanced" data-amount="10000">10K</button>
                                <button type="button" class="bet-quick-btn-hilo-enhanced" data-amount="50000">50K</button>
                                <button type="button" class="bet-quick-btn-hilo-enhanced" data-amount="100000">100K</button>
                                <button type="button" class="bet-quick-btn-hilo-enhanced" data-amount="200000">200K</button>
                            </div>
                        </div>
                        <button type="submit" class="start-button-hilo-enhanced">🎮 Bắt Đầu</button>
                    </form>
                <?php else: ?>
                    <form method="post" id="guessForm">
                        <input type="hidden" name="action" value="guess">
                        <input type="hidden" name="cuoc" value="<?= $_POST['cuoc'] ?? 10000 ?>">
                        <div class="control-group-hilo-enhanced">
                            <label class="control-label-hilo-enhanced">🎯 Đoán lá bài tiếp theo:</label>
                            <div class="guess-buttons-hilo">
                                <button type="submit" name="guess" value="lower" class="guess-btn-hilo lower">
                                    <span class="guess-icon">⬇️</span>
                                    <span class="guess-label">Lower</span>
                                    <span class="guess-multiplier">x2.0</span>
                                </button>
                                <button type="submit" name="guess" value="same" class="guess-btn-hilo same">
                                    <span class="guess-icon">⚡</span>
                                    <span class="guess-label">Same</span>
                                    <span class="guess-multiplier">x13.0</span>
                                </button>
                                <button type="submit" name="guess" value="higher" class="guess-btn-hilo higher">
                                    <span class="guess-icon">⬆️</span>
                                    <span class="guess-label">Higher</span>
                                    <span class="guess-multiplier">x2.0</span>
                                </button>
                            </div>
                        </div>
                    </form>
                    <form method="post" style="margin-top: 20px;">
                        <input type="hidden" name="action" value="cashout">
                        <input type="hidden" name="cuoc" value="<?= $_POST['cuoc'] ?? 10000 ?>">
                        <button type="submit" class="cashout-button-hilo-enhanced">💰 Cashout (Streak: <?= $streak ?>)</button>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="hilo-info-enhanced">
                <h3>📖 Cách Chơi</h3>
                <ul>
                    <li>Đoán lá bài tiếp theo sẽ Higher, Lower hay Same</li>
                    <li>Higher/Lower: x2.0 multiplier</li>
                    <li>Same: x13.0 multiplier (khó hơn)</li>
                    <li>Streak càng cao, bonus cashout càng lớn</li>
                </ul>
            </div>
            
            <p style="text-align: center; margin-top: 20px;">
                <a href="index.php" style="color: #667eea; text-decoration: none; font-weight: 600;">🏠 Quay Lại Trang Chủ</a>
            </p>
        </div>
    </div>
    
    <script src="assets/js/game-hilo.js"></script>
    <script src="assets/js/game-animations-enhanced.js"></script>
    <script src="assets/js/sound-effects.js"></script>
</body>
</html>

