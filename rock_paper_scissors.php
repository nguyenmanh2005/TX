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
if (!$stmt) {
    die("Lỗi prepare statement: " . $conn->error);
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) {
    die("Không tìm thấy thông tin người dùng!");
}
$soDu = $user['Money'];
$tenNguoiChoi = $user['Name'];

$ketQua = $_SESSION['rps_result'] ?? null;
$thongBao = $_SESSION['rps_message'] ?? "";
$ketQuaClass = $_SESSION['rps_class'] ?? "";
$laThang = $_SESSION['rps_win'] ?? false;
$playerChoice = $_SESSION['rps_player'] ?? null;
$computerChoice = $_SESSION['rps_computer'] ?? null;

unset($_SESSION['rps_result']);
unset($_SESSION['rps_message']);
unset($_SESSION['rps_class']);
unset($_SESSION['rps_win']);
unset($_SESSION['rps_player']);
unset($_SESSION['rps_computer']);

// Hàm xác định thắng thua
function determineWinner($player, $computer) {
    if ($player === $computer) {
        return 'tie';
    }
    
    $winConditions = [
        'rock' => 'scissors',
        'paper' => 'rock',
        'scissors' => 'paper'
    ];
    
    if ($winConditions[$player] === $computer) {
        return 'player';
    }
    
    return 'computer';
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'play') {
    $cuocRaw = $_POST["cuoc"] ?? "0";
    $cuoc = (int) str_replace([",", ".", " "], "", $cuocRaw);
    $playerChoice = isset($_POST['choice']) ? trim($_POST['choice']) : '';

    if ($cuoc > $soDu || $cuoc <= 0) {
        $_SESSION['rps_message'] = "⚠️ Số tiền cược không hợp lệ!";
        $_SESSION['rps_class'] = "thua";
    } elseif (!in_array($playerChoice, ['rock', 'paper', 'scissors'], true)) {
        $_SESSION['rps_message'] = "⚠️ Chọn Rock, Paper hoặc Scissors!";
        $_SESSION['rps_class'] = "thua";
    } else {
        // Computer chọn ngẫu nhiên
        $choices = ['rock', 'paper', 'scissors'];
        $computerChoice = $choices[array_rand($choices)];
        
        // Xác định thắng thua
        $winner = determineWinner($playerChoice, $computerChoice);
        
        // Tính thắng thua
        $laThang = false;
        $thang = 0;
        if ($winner === 'player') {
            $thang = $cuoc * 2; // 1:1
            $soDu += $thang;
            $_SESSION['rps_message'] = "🎉 Thắng! Bạn thắng với " . ucfirst($playerChoice) . "! Thắng " . number_format($thang) . " VNĐ!";
            $_SESSION['rps_class'] = "thang";
            $_SESSION['rps_win'] = true;
            $laThang = true;
        } elseif ($winner === 'tie') {
            // Hòa - không mất không thắng
            $_SESSION['rps_message'] = "🤝 Hòa! Cả hai đều chọn " . ucfirst($playerChoice) . ". Hoàn tiền cược!";
            $_SESSION['rps_class'] = "tie";
            $_SESSION['rps_win'] = false;
            $laThang = false;
        } else {
            $soDu -= $cuoc;
            $_SESSION['rps_message'] = "😢 Thua! Máy chọn " . ucfirst($computerChoice) . ". Mất " . number_format($cuoc) . " VNĐ";
            $_SESSION['rps_class'] = "thua";
            $_SESSION['rps_win'] = false;
            $laThang = false;
        }
        
        $_SESSION['rps_player'] = $playerChoice;
        $_SESSION['rps_computer'] = $computerChoice;
        
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
        
        require_once 'game_history_helper.php';
        $winAmount = $laThang ? $thang : 0;
        logGameHistoryWithAll($conn, $userId, 'Rock Paper Scissors', $cuoc, $winAmount, $laThang);
        
        header("Location: rock_paper_scissors.php");
        exit();
    }
}

// Reload balance
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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rock Paper Scissors Game</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/game-rock-paper-scissors.css">
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
        
        #confetti-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9999;
        }
        
        .rps-placeholder {
            font-size: 100px;
            color: #bdc3c7;
            font-weight: 900;
        }
    </style>
</head>
<body>
    <div class="rps-container-enhanced">
        <div class="game-box-rps-enhanced">
            <div class="game-header-rps-enhanced">
                <h1 class="game-title-rps-enhanced">✂️🪨📄 Rock Paper Scissors</h1>
                <div class="balance-rps-enhanced">
                    <span>💰</span>
                    <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
                </div>
            </div>
            
            <div class="rps-battle-area">
                <div class="player-side-rps">
                    <div class="side-label-rps">Bạn</div>
                    <div class="choice-display-rps player-choice" id="playerChoiceDisplay">
                        <?php if ($playerChoice): ?>
                            <div class="rps-icon <?= $playerChoice ?> animated-fade-in-up">
                                <?php
                                $icons = ['rock' => '🪨', 'paper' => '📄', 'scissors' => '✂️'];
                                echo $icons[$playerChoice] ?? '❓';
                                ?>
                            </div>
                            <div class="choice-name-rps"><?= ucfirst($playerChoice) ?></div>
                        <?php else: ?>
                            <div class="rps-placeholder">?</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="vs-divider-rps">VS</div>
                
                <div class="computer-side-rps">
                    <div class="side-label-rps">Máy</div>
                    <div class="choice-display-rps computer-choice" id="computerChoiceDisplay">
                        <?php if ($computerChoice): ?>
                            <div class="rps-icon <?= $computerChoice ?> animated-fade-in-up">
                                <?php
                                $icons = ['rock' => '🪨', 'paper' => '📄', 'scissors' => '✂️'];
                                echo $icons[$computerChoice] ?? '❓';
                                ?>
                            </div>
                            <div class="choice-name-rps"><?= ucfirst($computerChoice) ?></div>
                        <?php else: ?>
                            <div class="rps-placeholder">?</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if ($thongBao): ?>
                <div class="result-banner-rps-enhanced <?= $ketQuaClass === 'thang' ? 'win animate-win' : ($ketQuaClass === 'tie' ? 'tie animate-bounce' : 'lose animate-lose') ?>" id="resultBanner">
                    <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php if ($laThang): ?>
                    <canvas id="confetti-canvas"></canvas>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="game-controls-rps-enhanced">
                <form method="post" id="rpsForm">
                    <input type="hidden" name="action" value="play">
                    <div class="control-group-rps-enhanced">
                        <label class="control-label-rps-enhanced">🎯 Chọn của bạn:</label>
                        <div class="choice-buttons-rps">
                            <label class="choice-btn-rps rock-btn">
                                <input type="radio" name="choice" value="rock" required>
                                <span class="choice-icon-rps">🪨</span>
                                <span class="choice-label-rps">Rock</span>
                            </label>
                            <label class="choice-btn-rps paper-btn">
                                <input type="radio" name="choice" value="paper" required>
                                <span class="choice-icon-rps">📄</span>
                                <span class="choice-label-rps">Paper</span>
                            </label>
                            <label class="choice-btn-rps scissors-btn">
                                <input type="radio" name="choice" value="scissors" required>
                                <span class="choice-icon-rps">✂️</span>
                                <span class="choice-label-rps">Scissors</span>
                            </label>
                        </div>
                    </div>
                    <div class="control-group-rps-enhanced">
                        <label class="control-label-rps-enhanced">💰 Số tiền cược:</label>
                        <input type="number" name="cuoc" id="cuocInput" class="control-input-rps-enhanced" placeholder="Nhập số tiền cược" required min="1">
                        <div class="bet-quick-amounts-rps-enhanced">
                            <button type="button" class="bet-quick-btn-rps-enhanced" data-amount="10000">10K</button>
                            <button type="button" class="bet-quick-btn-rps-enhanced" data-amount="50000">50K</button>
                            <button type="button" class="bet-quick-btn-rps-enhanced" data-amount="100000">100K</button>
                            <button type="button" class="bet-quick-btn-rps-enhanced" data-amount="200000">200K</button>
                        </div>
                    </div>
                    <button type="submit" class="play-button-rps-enhanced">🎮 Chơi Ngay</button>
                </form>
            </div>
            
            <div class="rps-info-enhanced">
                <h3>📖 Cách Chơi</h3>
                <ul>
                    <li>Chọn Rock (🪨), Paper (📄) hoặc Scissors (✂️)</li>
                    <li>Rock thắng Scissors</li>
                    <li>Paper thắng Rock</li>
                    <li>Scissors thắng Paper</li>
                    <li>Thắng: x2.0, Hòa: hoàn tiền</li>
                </ul>
            </div>
            
            <p style="text-align: center; margin-top: 20px;">
                <a href="index.php" style="color: #667eea; text-decoration: none; font-weight: 600;">🏠 Quay Lại Trang Chủ</a>
            </p>
        </div>
    </div>
    
    <script src="assets/js/game-rock-paper-scissors.js"></script>
    <script src="assets/js/game-animations-enhanced.js"></script>
    <script src="assets/js/sound-effects.js"></script>
    <?php if ($laThang): ?>
    <script>
        // Confetti effect khi thắng
        (function() {
            const canvas = document.getElementById('confetti-canvas');
            if (!canvas) return;
            
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            const ctx = canvas.getContext('2d');
            
            const confetti = [];
            const colors = ['#ffd700', '#ff6b6b', '#4ecdc4', '#45b7d1', '#f39c12', '#e74c3c', '#9b59b6'];
            
            for (let i = 0; i < 150; i++) {
                confetti.push({
                    x: Math.random() * canvas.width,
                    y: -Math.random() * canvas.height,
                    r: Math.random() * 6 + 2,
                    d: Math.random() * confetti.length,
                    color: colors[Math.floor(Math.random() * colors.length)],
                    tilt: Math.floor(Math.random() * 10) - 10,
                    tiltAngleIncrement: Math.random() * 0.07 + 0.05,
                    tiltAngle: 0
                });
            }
            
            function draw() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                confetti.forEach((c, i) => {
                    ctx.beginPath();
                    ctx.lineWidth = c.r / 2;
                    ctx.strokeStyle = c.color;
                    ctx.moveTo(c.x + c.tilt + c.r, c.y);
                    ctx.lineTo(c.x + c.tilt, c.y + c.tilt + c.r);
                    ctx.stroke();
                    
                    c.tiltAngle += c.tiltAngleIncrement;
                    c.y += (Math.cos(c.d) + 3 + c.r / 2) / 2;
                    c.tilt = Math.sin(c.tiltAngle - i / 3) * 15;
                    
                    if (c.y > canvas.height) {
                        confetti[i] = {
                            x: Math.random() * canvas.width,
                            y: -20,
                            r: c.r,
                            d: c.d,
                            color: c.color,
                            tilt: Math.floor(Math.random() * 10) - 10,
                            tiltAngleIncrement: c.tiltAngleIncrement,
                            tiltAngle: 0
                        };
                    }
                });
                
                requestAnimationFrame(draw);
            }
            
            draw();
            
            setTimeout(() => {
                canvas.remove();
            }, 5000);
        })();
    </script>
    <?php endif; ?>
</body>
</html>
