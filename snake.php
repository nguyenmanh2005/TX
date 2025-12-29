<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';
require_once 'user_progress_helper.php';

// Kiểm tra kết nối database
if (!$conn || $conn->connect_error) {
    die("Lỗi kết nối database: " . ($conn ? $conn->connect_error : "Không thể kết nối"));
}

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

// Xử lý kết quả game từ session
$thongBao = $_SESSION['snake_message'] ?? "";
$ketQuaClass = $_SESSION['snake_class'] ?? "";
$laThang = $_SESSION['snake_win'] ?? false;
$score = $_SESSION['snake_score'] ?? 0;
$winAmount = $_SESSION['snake_win_amount'] ?? 0;

// Xóa session sau khi lấy
unset($_SESSION['snake_message']);
unset($_SESSION['snake_class']);
unset($_SESSION['snake_win']);
unset($_SESSION['snake_score']);
unset($_SESSION['snake_win_amount']);

// Xử lý kết quả game
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'save_result') {
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");
    $finalScore = (int)($_POST["score"] ?? 0);
    
    if ($cuoc > 0 && $finalScore > 0) {
        // Tính thưởng: mỗi điểm = 100 VNĐ, bonus nếu điểm cao
        $baseReward = $finalScore * 100;
        $bonus = 0;
        if ($finalScore >= 50) {
            $bonus = 5000; // Bonus 5K nếu đạt 50 điểm
        } elseif ($finalScore >= 30) {
            $bonus = 2000; // Bonus 2K nếu đạt 30 điểm
        } elseif ($finalScore >= 20) {
            $bonus = 1000; // Bonus 1K nếu đạt 20 điểm
        }
        
        $thang = $baseReward + $bonus;
        $soDu += $thang;
        $laThang = true;
        
        // Cập nhật số dư
        $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $capNhat->bind_param("di", $soDu, $userId);
        $capNhat->execute();
        $capNhat->close();
        
        // Track quest progress
        require_once 'game_history_helper.php';
        logGameHistoryWithAll($conn, $userId, 'Snake Game', $cuoc, $thang, true);
        
        // Cộng XP
        $baseXp = 10;
        $scoreXp = min(50, $finalScore);
        $totalXp = $baseXp + $scoreXp;
        up_add_xp($conn, $userId, $totalXp);
        
        $_SESSION['snake_message'] = "🎉 Điểm số: " . $finalScore . "! Thắng " . number_format($thang) . " VNĐ!";
        $_SESSION['snake_class'] = "thang";
        $_SESSION['snake_win'] = true;
        $_SESSION['snake_score'] = $finalScore;
        $_SESSION['snake_win_amount'] = $thang;
        
        header("Location: snake.php");
        exit();
    }
}

// Luôn reload số dư từ database
$reloadSql = "SELECT Money FROM users WHERE Iduser = ?";
$reloadStmt = $conn->prepare($reloadSql);
if ($reloadStmt) {
    $reloadStmt->bind_param("i", $userId);
    $reloadStmt->execute();
    $reloadResult = $reloadStmt->get_result();
    $reloadUser = $reloadResult->fetch_assoc();
    if ($reloadUser) {
        $soDu = $reloadUser['Money'];
    }
    $reloadStmt->close();
}
if ($stmt) {
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Snake Game - Rắn Săn Mồi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <style>
        body {
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
        }
        
        .game-container {
            max-width: 700px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        
        .game-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .game-header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 2.5em;
        }
        
        .game-info {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            text-align: center;
            min-width: 120px;
        }
        
        .info-card .label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .info-card .value {
            font-size: 1.5em;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .bet-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .bet-input-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .bet-input-group input {
            flex: 1;
            min-width: 150px;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1.1em;
        }
        
        .bet-input-group button {
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: bold;
            transition: transform 0.2s;
        }
        
        .bet-input-group button:hover {
            transform: scale(1.05);
        }
        
        .quick-bet-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .quick-bet-btn {
            padding: 8px 15px;
            background: #e9ecef;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .quick-bet-btn:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .game-area {
            text-align: center;
            margin: 20px 0;
        }
        
        #gameCanvas {
            border: 3px solid #667eea;
            border-radius: 10px;
            background: #1a1a2e;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }
        
        .game-controls {
            margin: 20px 0;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .control-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            max-width: 300px;
            margin: 0 auto;
        }
        
        .control-btn {
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.5em;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .control-btn:hover {
            transform: scale(1.1);
        }
        
        .control-btn:active {
            transform: scale(0.95);
        }
        
        .control-btn.up { grid-column: 2; }
        .control-btn.down { grid-column: 2; grid-row: 3; }
        .control-btn.left { grid-column: 1; grid-row: 2; }
        .control-btn.right { grid-column: 3; grid-row: 2; }
        
        .instructions {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: left;
        }
        
        .instructions h3 {
            margin-top: 0;
            color: #667eea;
        }
        
        .instructions ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .message {
            text-align: center;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            font-size: 1.2em;
            font-weight: bold;
        }
        
        .message.thang {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .message.thua {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        
        .new-game-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.2em;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
            transition: transform 0.2s;
        }
        
        .new-game-btn:hover {
            transform: scale(1.02);
        }
        
        @media (max-width: 600px) {
            #gameCanvas {
                width: 100%;
                height: auto;
            }
            
            .game-container {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="game-container">
        <div class="game-header">
            <h1>🐍 Snake Game</h1>
            <p style="color: #666;">Rắn Săn Mồi - Điều khiển bằng phím mũi tên hoặc nút</p>
        </div>
        
        <div class="game-info">
            <div class="info-card">
                <div class="label">Số Dư</div>
                <div class="value"><?= number_format($soDu) ?> ₫</div>
            </div>
            <div class="info-card">
                <div class="label">Điểm Số</div>
                <div class="value" id="scoreDisplay">0</div>
            </div>
            <div class="info-card">
                <div class="label">Tiền Cược</div>
                <div class="value" id="betDisplay">0 ₫</div>
            </div>
        </div>
        
        <?php if ($thongBao): ?>
            <div class="message <?= $ketQuaClass ?>">
                <?= $thongBao ?>
            </div>
        <?php endif; ?>
        
        <div class="bet-section" id="betSection">
            <h3 style="margin-bottom: 15px;">💰 Đặt Cược</h3>
            <div class="bet-input-group">
                <input type="text" id="cuoc" placeholder="Nhập số tiền cược" required>
                <button type="button" onclick="startGame()">Bắt Đầu</button>
            </div>
            <div class="quick-bet-buttons">
                <button type="button" class="quick-bet-btn" onclick="setBet(10000)">10K</button>
                <button type="button" class="quick-bet-btn" onclick="setBet(50000)">50K</button>
                <button type="button" class="quick-bet-btn" onclick="setBet(100000)">100K</button>
                <button type="button" class="quick-bet-btn" onclick="setBet(500000)">500K</button>
                <button type="button" class="quick-bet-btn" onclick="setBet(<?= $soDu ?>)">Tất Cả</button>
            </div>
        </div>
        
        <div class="game-area" id="gameArea" style="display: none;">
            <canvas id="gameCanvas" width="400" height="400"></canvas>
            <div class="game-controls">
                <div class="control-buttons">
                    <button class="control-btn up" onclick="changeDirection('up')">↑</button>
                    <button class="control-btn left" onclick="changeDirection('left')">←</button>
                    <button class="control-btn right" onclick="changeDirection('right')">→</button>
                    <button class="control-btn down" onclick="changeDirection('down')">↓</button>
                </div>
            </div>
        </div>
        
        <div class="instructions">
            <h3>📖 Hướng Dẫn</h3>
            <ul>
                <li>Điều khiển rắn bằng phím mũi tên hoặc nút trên màn hình</li>
                <li>Ăn thức ăn (🍎) để tăng điểm và dài ra</li>
                <li>Tránh đâm vào tường hoặc vào chính mình</li>
                <li>Mỗi điểm = 100 VNĐ thưởng</li>
                <li>Bonus: 20 điểm = +1K, 30 điểm = +2K, 50 điểm = +5K</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="index.php" style="color: #667eea; text-decoration: none; font-weight: bold;">
                ← Về Trang Chủ
            </a>
        </div>
    </div>
    
    <script>
        let canvas, ctx;
        let snake = [];
        let food = {};
        let direction = 'right';
        let nextDirection = 'right';
        let score = 0;
        let gameRunning = false;
        let gameLoop;
        let betAmount = 0;
        const gridSize = 20;
        const tileCount = 20;
        
        function init() {
            canvas = document.getElementById('gameCanvas');
            ctx = canvas.getContext('2d');
        }
        
        function setBet(amount) {
            document.getElementById('cuoc').value = amount.toLocaleString('vi-VN');
        }
        
        function startGame() {
            const cuocInput = document.getElementById('cuoc');
            const cuocValue = cuocInput.value.replace(/,/g, '');
            betAmount = parseInt(cuocValue);
            
            if (!betAmount || betAmount <= 0) {
                alert('⚠️ Vui lòng nhập số tiền cược hợp lệ!');
                return;
            }
            
            if (betAmount > <?= $soDu ?>) {
                alert('⚠️ Số tiền cược vượt quá số dư!');
                return;
            }
            
            // Ẩn bet section, hiện game
            document.getElementById('betSection').style.display = 'none';
            document.getElementById('gameArea').style.display = 'block';
            document.getElementById('betDisplay').textContent = betAmount.toLocaleString('vi-VN') + ' ₫';
            
            // Khởi tạo game
            resetGame();
            gameRunning = true;
            gameLoop = setInterval(update, 100);
        }
        
        function resetGame() {
            snake = [
                {x: 10, y: 10}
            ];
            direction = 'right';
            nextDirection = 'right';
            score = 0;
            document.getElementById('scoreDisplay').textContent = '0';
            generateFood();
        }
        
        function generateFood() {
            food = {
                x: Math.floor(Math.random() * tileCount),
                y: Math.floor(Math.random() * tileCount)
            };
            
            // Đảm bảo thức ăn không trùng với rắn
            for (let segment of snake) {
                if (segment.x === food.x && segment.y === food.y) {
                    generateFood();
                    return;
                }
            }
        }
        
        function update() {
            if (!gameRunning) return;
            
            direction = nextDirection;
            
            // Di chuyển đầu rắn
            const head = {x: snake[0].x, y: snake[0].y};
            
            switch(direction) {
                case 'up': head.y--; break;
                case 'down': head.y++; break;
                case 'left': head.x--; break;
                case 'right': head.x++; break;
            }
            
            // Kiểm tra va chạm tường
            if (head.x < 0 || head.x >= tileCount || head.y < 0 || head.y >= tileCount) {
                gameOver();
                return;
            }
            
            // Kiểm tra va chạm với chính mình
            for (let segment of snake) {
                if (head.x === segment.x && head.y === segment.y) {
                    gameOver();
                    return;
                }
            }
            
            snake.unshift(head);
            
            // Kiểm tra ăn thức ăn
            if (head.x === food.x && head.y === food.y) {
                score++;
                document.getElementById('scoreDisplay').textContent = score;
                generateFood();
            } else {
                snake.pop();
            }
            
            draw();
        }
        
        function draw() {
            // Xóa canvas
            ctx.fillStyle = '#1a1a2e';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            // Vẽ rắn
            ctx.fillStyle = '#4CAF50';
            for (let segment of snake) {
                ctx.fillRect(segment.x * gridSize, segment.y * gridSize, gridSize - 2, gridSize - 2);
            }
            
            // Vẽ đầu rắn (màu khác)
            ctx.fillStyle = '#2e7d32';
            ctx.fillRect(snake[0].x * gridSize, snake[0].y * gridSize, gridSize - 2, gridSize - 2);
            
            // Vẽ thức ăn
            ctx.fillStyle = '#f44336';
            ctx.beginPath();
            ctx.arc(
                food.x * gridSize + gridSize / 2,
                food.y * gridSize + gridSize / 2,
                gridSize / 2 - 2,
                0,
                2 * Math.PI
            );
            ctx.fill();
        }
        
        function changeDirection(newDirection) {
            // Không cho phép đi ngược lại
            if ((direction === 'up' && newDirection === 'down') ||
                (direction === 'down' && newDirection === 'up') ||
                (direction === 'left' && newDirection === 'right') ||
                (direction === 'right' && newDirection === 'left')) {
                return;
            }
            nextDirection = newDirection;
        }
        
        function gameOver() {
            gameRunning = false;
            clearInterval(gameLoop);
            
            if (score > 0) {
                // Lưu kết quả
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="save_result">
                    <input type="hidden" name="cuoc" value="${betAmount}">
                    <input type="hidden" name="score" value="${score}">
                `;
                document.body.appendChild(form);
                form.submit();
            } else {
                alert('😢 Game Over! Bạn chưa đạt điểm nào!');
                location.reload();
            }
        }
        
        // Điều khiển bằng bàn phím
        document.addEventListener('keydown', (e) => {
            if (!gameRunning) return;
            
            switch(e.key) {
                case 'ArrowUp': changeDirection('up'); e.preventDefault(); break;
                case 'ArrowDown': changeDirection('down'); e.preventDefault(); break;
                case 'ArrowLeft': changeDirection('left'); e.preventDefault(); break;
                case 'ArrowRight': changeDirection('right'); e.preventDefault(); break;
            }
        });
        
        // Khởi tạo khi trang load
        window.onload = init;
    </script>
</body>
</html>

