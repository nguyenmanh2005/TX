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
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) {
    die("Không tìm thấy thông tin người dùng!");
}
$soDu = $user['Money'];
$tenNguoiChoi = $user['Name'];

$soBiMat = isset($_SESSION['so_bi_mat']) ? $_SESSION['so_bi_mat'] : rand(1, 100);
if (!isset($_SESSION['so_bi_mat'])) {
    $_SESSION['so_bi_mat'] = $soBiMat;
}

// Lấy kết quả từ session (nếu có)
$thongBao = $_SESSION['number_message'] ?? "";
$ketQuaClass = $_SESSION['number_class'] ?? "";
$laThang = $_SESSION['number_win'] ?? false;

// Xóa session sau khi lấy
unset($_SESSION['number_message']);
unset($_SESSION['number_class']);
unset($_SESSION['number_win']);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    $action = $_POST["action"] ?? "";
    
    if ($action === "new_game") {
        $soBiMat = rand(1, 100);
        $_SESSION['so_bi_mat'] = $soBiMat;
        $_SESSION['number_message'] = "🆕 Trò chơi mới! Đoán số từ 1 đến 100!";
        header("Location: number.php");
        exit();
    } elseif ($action === "guess") {
        $chonRaw = $_POST["so"] ?? "0";
        $chon = filter_var($chonRaw, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 100]]);
        if ($chon === false) {
            $chon = 0;
        }
        $cuocRaw = $_POST["cuoc"] ?? "0";
        $cuoc = (int) str_replace([",", ".", " "], "", $cuocRaw);

        if ($cuoc > $soDu || $cuoc <= 0) {
            $_SESSION['number_message'] = "⚠️ Số tiền cược không hợp lệ!";
            $_SESSION['number_class'] = "thua";
        } elseif ($chon < 1 || $chon > 100) {
            $_SESSION['number_message'] = "❌ Số phải từ 1 đến 100!";
            $_SESSION['number_class'] = "thua";
        } else {
            $khoangCach = abs($chon - $soBiMat);
            
            $laThang = false;
            $thang = 0;
            
            if ($chon === $soBiMat) {
                // Đoán đúng
                $thang = $cuoc * 10;
                $soDu += $thang;
                $_SESSION['number_message'] = "🎉 CHÍNH XÁC! Bạn thắng " . number_format($thang) . " VNĐ! Số bí mật: " . $soBiMat;
                $_SESSION['number_class'] = "thang";
                $_SESSION['number_win'] = true;
                $laThang = true;
                
                // Tạo game mới
                $soBiMat = rand(1, 100);
                $_SESSION['so_bi_mat'] = $soBiMat;
            } elseif ($khoangCach <= 5) {
                // Gần đúng (trong vòng 5)
                $thang = $cuoc * 2;
                $soDu += $thang;
                $_SESSION['number_message'] = "🔥 Rất gần! Cách " . $khoangCach . " số. Thắng " . number_format($thang) . " VNĐ!";
                $_SESSION['number_class'] = "thang";
                $_SESSION['number_win'] = true;
                $laThang = true;
            } else {
                // Sai
                $soDu -= $cuoc;
                $huong = ($chon < $soBiMat) ? "lớn hơn" : "nhỏ hơn";
                $_SESSION['number_message'] = "❌ Sai rồi! Số bí mật " . $huong . " " . $chon . ". Mất " . number_format($cuoc) . " VNĐ";
                $_SESSION['number_class'] = "thua";
                $_SESSION['number_win'] = false;
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
            $winAmount = $laThang ? $thang : 0;
            logGameHistoryWithAll($conn, $userId, 'Đoán Số', $cuoc, $winAmount, $laThang);
            
            // Redirect để tránh resubmit
            header("Location: number.php");
            exit();
        }
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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Number Guessing Game - Đoán Số</title>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/game-ui-enhanced.css">
    <link rel="stylesheet" href="assets/css/game-number-guessing.css">
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
    </style>
</head>
<body>
    <div class="number-guessing-container">
        <div class="game-box-number-enhanced">
            <div class="game-header-number-enhanced">
                <h1 class="game-title-number-enhanced">🎯 Number Guessing - Đoán Số</h1>
                <div class="balance-number-enhanced">
                    <span>💰</span>
                    <span><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span>
                </div>
            </div>
            
            <div class="number-game-area">
                <div class="number-display-area">
                    <div class="number-hint-box">
                        <h3>💡 Hướng Dẫn</h3>
                        <ul>
                            <li>Đoán số từ <strong>1 đến 100</strong></li>
                            <li>🎯 <strong>Đoán đúng:</strong> Thắng x10</li>
                            <li>🔥 <strong>Gần đúng (≤5 số):</strong> Thắng x2</li>
                            <li>❌ <strong>Sai:</strong> Mất tiền cược</li>
                        </ul>
                    </div>
                </div>
                
                <?php if ($thongBao): ?>
                    <div class="result-banner-number-enhanced <?= $ketQuaClass === 'thang' ? 'win animate-win' : 'lose animate-lose' ?>" id="resultBanner">
                        <?= htmlspecialchars($thongBao, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <?php if ($laThang): ?>
                        <canvas id="confetti-canvas"></canvas>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="game-controls-number-enhanced">
                    <form method="post" id="numberForm">
                        <div class="control-group-number-enhanced">
                            <label class="control-label-number-enhanced">🎯 Nhập số bạn đoán:</label>
                            <input type="number" name="so" id="soInput" class="control-input-number-enhanced" placeholder="Số từ 1 đến 100" required min="1" max="100">
                        </div>
                        
                        <div class="control-group-number-enhanced">
                            <label class="control-label-number-enhanced">💰 Số tiền cược:</label>
                            <input type="number" name="cuoc" id="cuocInput" class="control-input-number-enhanced" placeholder="Nhập số tiền cược" required min="1">
                            <div class="bet-quick-amounts-number-enhanced">
                                <button type="button" class="bet-quick-btn-number-enhanced" data-amount="10000">10K</button>
                                <button type="button" class="bet-quick-btn-number-enhanced" data-amount="50000">50K</button>
                                <button type="button" class="bet-quick-btn-number-enhanced" data-amount="100000">100K</button>
                                <button type="button" class="bet-quick-btn-number-enhanced" data-amount="200000">200K</button>
                            </div>
                        </div>
                        
                        <div class="button-group-number-enhanced">
                            <button type="submit" name="action" value="guess" class="guess-button-number-enhanced" id="submitBtn">🎯 Đoán Ngay</button>
                            <button type="submit" name="action" value="new_game" class="new-game-button-number-enhanced">🆕 Game Mới</button>
                        </div>
                    </form>
                </div>
                
                <div class="number-info-enhanced">
                    <h3>📖 Cách Chơi</h3>
                    <p>Hệ thống sẽ tạo một số bí mật từ 1 đến 100. Nhiệm vụ của bạn là đoán đúng số đó!</p>
                    <p><strong>Mẹo:</strong> Hãy chú ý đến gợi ý "lớn hơn" hoặc "nhỏ hơn" để thu hẹp phạm vi tìm kiếm!</p>
                </div>
                
                <p style="text-align: center; margin-top: 20px;">
                    <a href="index.php" style="color: #667eea; text-decoration: none; font-weight: 600;">🏠 Quay Lại Trang Chủ</a>
                </p>
            </div>
        </div>
    </div>

    <script src="assets/js/game-number-guessing.js"></script>
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

