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
$thongBao = $_SESSION['game2048_message'] ?? "";
$ketQuaClass = $_SESSION['game2048_class'] ?? "";
$laThang = $_SESSION['game2048_win'] ?? false;
$score = $_SESSION['game2048_score'] ?? 0;
$winAmount = $_SESSION['game2048_win_amount'] ?? 0;
$highestTile = $_SESSION['game2048_highest_tile'] ?? 0;

// Xóa session sau khi lấy
unset($_SESSION['game2048_message']);
unset($_SESSION['game2048_class']);
unset($_SESSION['game2048_win']);
unset($_SESSION['game2048_score']);
unset($_SESSION['game2048_win_amount']);
unset($_SESSION['game2048_highest_tile']);

// Xử lý kết quả game
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'save_result') {
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");
    $finalScore = (int)($_POST["score"] ?? 0);
    $finalHighestTile = (int)($_POST["highest_tile"] ?? 0);
    
    if ($cuoc > 0 && $finalScore > 0) {
        // Tính thưởng: mỗi điểm = 50 VNĐ, bonus theo ô cao nhất
        $baseReward = $finalScore * 50;
        $bonus = 0;
        if ($finalHighestTile >= 2048) {
            $bonus = 10000; // Bonus 10K nếu đạt 2048
        } elseif ($finalHighestTile >= 1024) {
            $bonus = 5000; // Bonus 5K nếu đạt 1024
        } elseif ($finalHighestTile >= 512) {
            $bonus = 2000; // Bonus 2K nếu đạt 512
        } elseif ($finalHighestTile >= 256) {
            $bonus = 1000; // Bonus 1K nếu đạt 256
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
        logGameHistoryWithAll($conn, $userId, '2048 Game', $cuoc, $thang, true);
        
        // Cộng XP
        $baseXp = 15;
        $tileXp = min(50, $finalHighestTile / 40);
        $totalXp = $baseXp + $tileXp;
        up_add_xp($conn, $userId, $totalXp);
        
        $_SESSION['game2048_message'] = "🎉 Điểm: " . number_format($finalScore) . " | Ô cao nhất: " . $finalHighestTile . "! Thắng " . number_format($thang) . " VNĐ!";
        $_SESSION['game2048_class'] = "thang";
        $_SESSION['game2048_win'] = true;
        $_SESSION['game2048_score'] = $finalScore;
        $_SESSION['game2048_win_amount'] = $thang;
        $_SESSION['game2048_highest_tile'] = $finalHighestTile;
        
        header("Location: game2048.php");
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
    <title>2048 Game - Trò Chơi Số</title>
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
        
        #gameBoard {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            max-width: 400px;
            margin: 20px auto;
            padding: 10px;
            background: #bbada0;
            border-radius: 10px;
        }
        
        .tile {
            aspect-ratio: 1;
            background: #eee4da;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            font-weight: bold;
            color: #776e65;
            transition: all 0.2s;
        }
        
        .tile.empty {
            background: rgba(238, 228, 218, 0.35);
        }
        
        .tile-2 { background: #eee4da; }
        .tile-4 { background: #ede0c8; }
        .tile-8 { background: #f2b179; color: #f9f6f2; }
        .tile-16 { background: #f59563; color: #f9f6f2; }
        .tile-32 { background: #f67c5f; color: #f9f6f2; }
        .tile-64 { background: #f65e3b; color: #f9f6f2; }
        .tile-128 { background: #edcf72; color: #f9f6f2; font-size: 1.5em; }
        .tile-256 { background: #edcc61; color: #f9f6f2; font-size: 1.5em; }
        .tile-512 { background: #edc850; color: #f9f6f2; font-size: 1.5em; }
        .tile-1024 { background: #edc53f; color: #f9f6f2; font-size: 1.2em; }
        .tile-2048 { background: #edc22e; color: #f9f6f2; font-size: 1.2em; }
        
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
            #gameBoard {
                max-width: 100%;
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
            <h1>🎯 2048 Game</h1>
            <p style="color: #666;">Trò Chơi Số - Gộp các ô giống nhau để đạt 2048!</p>
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
                <div class="label">Ô Cao Nhất</div>
                <div class="value" id="highestTileDisplay">0</div>
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
            <div id="gameBoard"></div>
            <div class="game-controls">
                <div class="control-buttons">
                    <button class="control-btn up" onclick="move('up')">↑</button>
                    <button class="control-btn left" onclick="move('left')">←</button>
                    <button class="control-btn right" onclick="move('right')">→</button>
                    <button class="control-btn down" onclick="move('down')">↓</button>
                </div>
            </div>
        </div>
        
        <div class="instructions">
            <h3>📖 Hướng Dẫn</h3>
            <ul>
                <li>Điều khiển bằng phím mũi tên hoặc nút trên màn hình</li>
                <li>Gộp các ô có cùng số để tạo số lớn hơn</li>
                <li>Mục tiêu: Đạt ô 2048 để thắng lớn!</li>
                <li>Mỗi điểm = 50 VNĐ thưởng</li>
                <li>Bonus: 256 = +1K, 512 = +2K, 1024 = +5K, 2048 = +10K</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="index.php" style="color: #667eea; text-decoration: none; font-weight: bold;">
                ← Về Trang Chủ
            </a>
        </div>
    </div>
    
    <script>
        let board = [];
        let score = 0;
        let highestTile = 0;
        let betAmount = 0;
        let gameStarted = false;
        const SIZE = 4;
        
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
            initGame();
            gameStarted = true;
        }
        
        function initGame() {
            board = Array(SIZE).fill().map(() => Array(SIZE).fill(0));
            score = 0;
            highestTile = 0;
            document.getElementById('scoreDisplay').textContent = '0';
            document.getElementById('highestTileDisplay').textContent = '0';
            addRandomTile();
            addRandomTile();
            render();
        }
        
        function addRandomTile() {
            const emptyCells = [];
            for (let i = 0; i < SIZE; i++) {
                for (let j = 0; j < SIZE; j++) {
                    if (board[i][j] === 0) {
                        emptyCells.push({row: i, col: j});
                    }
                }
            }
            if (emptyCells.length > 0) {
                const randomCell = emptyCells[Math.floor(Math.random() * emptyCells.length)];
                board[randomCell.row][randomCell.col] = Math.random() < 0.9 ? 2 : 4;
            }
        }
        
        function render() {
            const gameBoard = document.getElementById('gameBoard');
            gameBoard.innerHTML = '';
            
            for (let i = 0; i < SIZE; i++) {
                for (let j = 0; j < SIZE; j++) {
                    const tile = document.createElement('div');
                    tile.className = 'tile';
                    if (board[i][j] === 0) {
                        tile.classList.add('empty');
                    } else {
                        tile.classList.add(`tile-${board[i][j]}`);
                        tile.textContent = board[i][j];
                        if (board[i][j] > highestTile) {
                            highestTile = board[i][j];
                            document.getElementById('highestTileDisplay').textContent = highestTile;
                        }
                    }
                    gameBoard.appendChild(tile);
                }
            }
        }
        
        function move(direction) {
            if (!gameStarted) return;
            
            const prevBoard = board.map(row => [...row]);
            let moved = false;
            
            if (direction === 'left') {
                moved = moveLeft();
            } else if (direction === 'right') {
                moved = moveRight();
            } else if (direction === 'up') {
                moved = moveUp();
            } else if (direction === 'down') {
                moved = moveDown();
            }
            
            if (moved) {
                addRandomTile();
                render();
                checkGameOver();
            }
        }
        
        function moveLeft() {
            let moved = false;
            for (let i = 0; i < SIZE; i++) {
                const row = board[i].filter(val => val !== 0);
                for (let j = 0; j < row.length - 1; j++) {
                    if (row[j] === row[j + 1]) {
                        row[j] *= 2;
                        score += row[j];
                        row[j + 1] = 0;
                        moved = true;
                    }
                }
                const merged = row.filter(val => val !== 0);
                while (merged.length < SIZE) merged.push(0);
                board[i] = merged;
            }
            document.getElementById('scoreDisplay').textContent = score.toLocaleString('vi-VN');
            return moved;
        }
        
        function moveRight() {
            let moved = false;
            for (let i = 0; i < SIZE; i++) {
                const row = board[i].filter(val => val !== 0);
                for (let j = row.length - 1; j > 0; j--) {
                    if (row[j] === row[j - 1]) {
                        row[j] *= 2;
                        score += row[j];
                        row[j - 1] = 0;
                        moved = true;
                    }
                }
                const merged = row.filter(val => val !== 0);
                while (merged.length < SIZE) merged.unshift(0);
                board[i] = merged;
            }
            document.getElementById('scoreDisplay').textContent = score.toLocaleString('vi-VN');
            return moved;
        }
        
        function moveUp() {
            let moved = false;
            for (let j = 0; j < SIZE; j++) {
                const col = [];
                for (let i = 0; i < SIZE; i++) {
                    if (board[i][j] !== 0) col.push(board[i][j]);
                }
                for (let i = 0; i < col.length - 1; i++) {
                    if (col[i] === col[i + 1]) {
                        col[i] *= 2;
                        score += col[i];
                        col[i + 1] = 0;
                        moved = true;
                    }
                }
                const merged = col.filter(val => val !== 0);
                while (merged.length < SIZE) merged.push(0);
                for (let i = 0; i < SIZE; i++) {
                    board[i][j] = merged[i];
                }
            }
            document.getElementById('scoreDisplay').textContent = score.toLocaleString('vi-VN');
            return moved;
        }
        
        function moveDown() {
            let moved = false;
            for (let j = 0; j < SIZE; j++) {
                const col = [];
                for (let i = 0; i < SIZE; i++) {
                    if (board[i][j] !== 0) col.push(board[i][j]);
                }
                for (let i = col.length - 1; i > 0; i--) {
                    if (col[i] === col[i - 1]) {
                        col[i] *= 2;
                        score += col[i];
                        col[i - 1] = 0;
                        moved = true;
                    }
                }
                const merged = col.filter(val => val !== 0);
                while (merged.length < SIZE) merged.unshift(0);
                for (let i = 0; i < SIZE; i++) {
                    board[i][j] = merged[i];
                }
            }
            document.getElementById('scoreDisplay').textContent = score.toLocaleString('vi-VN');
            return moved;
        }
        
        function checkGameOver() {
            // Kiểm tra còn ô trống
            for (let i = 0; i < SIZE; i++) {
                for (let j = 0; j < SIZE; j++) {
                    if (board[i][j] === 0) return;
                    // Kiểm tra có thể gộp
                    if (i < SIZE - 1 && board[i][j] === board[i + 1][j]) return;
                    if (j < SIZE - 1 && board[i][j] === board[i][j + 1]) return;
                }
            }
            
            // Game Over
            gameStarted = false;
            if (score > 0) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="save_result">
                    <input type="hidden" name="cuoc" value="${betAmount}">
                    <input type="hidden" name="score" value="${score}">
                    <input type="hidden" name="highest_tile" value="${highestTile}">
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
            if (!gameStarted) return;
            
            switch(e.key) {
                case 'ArrowUp': move('up'); e.preventDefault(); break;
                case 'ArrowDown': move('down'); e.preventDefault(); break;
                case 'ArrowLeft': move('left'); e.preventDefault(); break;
                case 'ArrowRight': move('right'); e.preventDefault(); break;
            }
        });
    </script>
</body>
</html>

