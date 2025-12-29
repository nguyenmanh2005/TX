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

// Khởi tạo game
if (!isset($_SESSION['tictactoe_game']) || isset($_POST['new_game'])) {
    $_SESSION['tictactoe_game'] = [
        'board' => array_fill(0, 9, ''),
        'current_player' => 'X', // X = người chơi, O = bot
        'game_over' => false,
        'winner' => '',
        'moves' => 0,
        'started' => false,
        'cuoc' => 0
    ];
}

$game = $_SESSION['tictactoe_game'];
$thongBao = "";
$ketQuaClass = "";
$laThang = false;
$winAmount = 0;

// Hàm kiểm tra thắng
function checkWinner($board) {
    $winLines = [
        [0, 1, 2], [3, 4, 5], [6, 7, 8], // Hàng ngang
        [0, 3, 6], [1, 4, 7], [2, 5, 8], // Hàng dọc
        [0, 4, 8], [2, 4, 6] // Đường chéo
    ];
    
    foreach ($winLines as $line) {
        if ($board[$line[0]] !== '' && 
            $board[$line[0]] === $board[$line[1]] && 
            $board[$line[1]] === $board[$line[2]]) {
            return $board[$line[0]];
        }
    }
    
    // Kiểm tra hòa
    if (!in_array('', $board)) {
        return 'draw';
    }
    
    return '';
}

// AI Bot - Tìm nước đi tốt nhất
function getBotMove($board) {
    // 1. Kiểm tra bot có thể thắng không
    for ($i = 0; $i < 9; $i++) {
        if ($board[$i] === '') {
            $testBoard = $board;
            $testBoard[$i] = 'O';
            if (checkWinner($testBoard) === 'O') {
                return $i;
            }
        }
    }
    
    // 2. Chặn người chơi thắng
    for ($i = 0; $i < 9; $i++) {
        if ($board[$i] === '') {
            $testBoard = $board;
            $testBoard[$i] = 'X';
            if (checkWinner($testBoard) === 'X') {
                return $i;
            }
        }
    }
    
    // 3. Ưu tiên giữa bàn cờ
    if ($board[4] === '') {
        return 4;
    }
    
    // 4. Chọn góc
    $corners = [0, 2, 6, 8];
    shuffle($corners);
    foreach ($corners as $corner) {
        if ($board[$corner] === '') {
            return $corner;
        }
    }
    
    // 5. Chọn ngẫu nhiên ô trống
    $empty = [];
    for ($i = 0; $i < 9; $i++) {
        if ($board[$i] === '') {
            $empty[] = $i;
        }
    }
    
    return $empty[array_rand($empty)];
}

// Xử lý game logic
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");
    $cellIndex = isset($_POST["cell_index"]) ? (int)$_POST["cell_index"] : -1;
    
    if ($action === "start" && $cuoc > 0) {
        if ($cuoc > $soDu || $cuoc <= 0) {
            $thongBao = "⚠️ Số tiền cược không hợp lệ!";
            $ketQuaClass = "thua";
        } else {
            $_SESSION['tictactoe_game']['cuoc'] = $cuoc;
            $_SESSION['tictactoe_game']['started'] = true;
            $soDu -= $cuoc;
            
            $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
            $capNhat->bind_param("di", $soDu, $userId);
            $capNhat->execute();
            $capNhat->close();
            
            $thongBao = "🎯 Đã đặt cược " . number_format($cuoc) . " VNĐ! Bạn đi trước (X)!";
            $ketQuaClass = "thang";
        }
    } elseif ($action === "move" && $cellIndex >= 0 && $cellIndex < 9) {
        if (!$game['started']) {
            $thongBao = "⚠️ Hãy đặt cược trước!";
            $ketQuaClass = "thua";
        } elseif ($game['game_over']) {
            $thongBao = "⚠️ Game đã kết thúc!";
            $ketQuaClass = "thua";
        } elseif ($game['board'][$cellIndex] !== '') {
            $thongBao = "⚠️ Ô này đã được chọn!";
            $ketQuaClass = "thua";
        } elseif ($game['current_player'] !== 'X') {
            $thongBao = "⚠️ Đợi bot đi!";
            $ketQuaClass = "thua";
        } else {
            // Người chơi đi
            $_SESSION['tictactoe_game']['board'][$cellIndex] = 'X';
            $_SESSION['tictactoe_game']['moves']++;
            $_SESSION['tictactoe_game']['current_player'] = 'O';
            
            $board = $_SESSION['tictactoe_game']['board'];
            $winner = checkWinner($board);
            
            if ($winner === 'X') {
                // Người chơi thắng
                $multiplier = 2.0;
                $thang = $game['cuoc'] * $multiplier;
                $soDu += $thang;
                $winAmount = $thang;
                $laThang = true;
                
                $_SESSION['tictactoe_game']['game_over'] = true;
                $_SESSION['tictactoe_game']['winner'] = 'X';
                
                $thongBao = "🎉 Bạn thắng! Nhận " . number_format($thang) . " VNĐ!";
                $ketQuaClass = "thang";
                
                // Track quest progress
                require_once 'game_history_helper.php';
                logGameHistoryWithAll($conn, $userId, 'Tic Tac Toe', $game['cuoc'], $thang, true);
                
                // Cộng XP
                $baseXp = 15;
                $movesXp = max(0, 30 - $_SESSION['tictactoe_game']['moves']);
                $totalXp = $baseXp + $movesXp;
                up_add_xp($conn, $userId, $totalXp);
            } elseif ($winner === 'draw') {
                // Hòa
                $hoanTien = $game['cuoc'];
                $soDu += $hoanTien;
                
                $_SESSION['tictactoe_game']['game_over'] = true;
                $_SESSION['tictactoe_game']['winner'] = 'draw';
                
                $thongBao = "🤝 Hòa! Hoàn lại " . number_format($hoanTien) . " VNĐ!";
                $ketQuaClass = "";
                
                // Track quest progress
                require_once 'game_history_helper.php';
                logGameHistoryWithAll($conn, $userId, 'Tic Tac Toe', $game['cuoc'], 0, false);
            } else {
                // Bot đi
                $botMove = getBotMove($board);
                $_SESSION['tictactoe_game']['board'][$botMove] = 'O';
                $_SESSION['tictactoe_game']['moves']++;
                $_SESSION['tictactoe_game']['current_player'] = 'X';
                
                $board = $_SESSION['tictactoe_game']['board'];
                $winner = checkWinner($board);
                
                if ($winner === 'O') {
                    // Bot thắng
                    $_SESSION['tictactoe_game']['game_over'] = true;
                    $_SESSION['tictactoe_game']['winner'] = 'O';
                    
                    $thongBao = "😢 Bot thắng! Mất " . number_format($game['cuoc']) . " VNĐ!";
                    $ketQuaClass = "thua";
                    
                    // Track quest progress
                    require_once 'game_history_helper.php';
                    logGameHistoryWithAll($conn, $userId, 'Tic Tac Toe', $game['cuoc'], 0, false);
                } elseif ($winner === 'draw') {
                    // Hòa
                    $hoanTien = $game['cuoc'];
                    $soDu += $hoanTien;
                    
                    $_SESSION['tictactoe_game']['game_over'] = true;
                    $_SESSION['tictactoe_game']['winner'] = 'draw';
                    
                    $thongBao = "🤝 Hòa! Hoàn lại " . number_format($hoanTien) . " VNĐ!";
                    $ketQuaClass = "";
                    
                    // Track quest progress
                    require_once 'game_history_helper.php';
                    logGameHistoryWithAll($conn, $userId, 'Tic Tac Toe', $game['cuoc'], 0, false);
                } else {
                    $thongBao = "🤖 Bot đã đi! Đến lượt bạn!";
                    $ketQuaClass = "thang";
                }
            }
            
            // Cập nhật số dư
            $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
            $capNhat->bind_param("di", $soDu, $userId);
            $capNhat->execute();
            $capNhat->close();
        }
    }
}

// Lấy game state hiện tại
$game = $_SESSION['tictactoe_game'] ?? null;
if (!$game) {
    $_SESSION['tictactoe_game'] = [
        'board' => array_fill(0, 9, ''),
        'current_player' => 'X',
        'game_over' => false,
        'winner' => '',
        'moves' => 0,
        'started' => false,
        'cuoc' => 0
    ];
    $game = $_SESSION['tictactoe_game'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tic Tac Toe - Cờ Caro</title>
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
            max-width: 600px;
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
        
        .tic-tac-toe-board {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 30px 0;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .cell {
            aspect-ratio: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 3px solid transparent;
            color: white;
        }
        
        .cell:hover:not(.disabled) {
            transform: scale(1.05);
            border-color: #fff;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.5);
        }
        
        .cell.disabled {
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .cell.x {
            color: #4CAF50;
        }
        
        .cell.o {
            color: #f44336;
        }
        
        .cell.winning {
            animation: winningPulse 1s infinite;
        }
        
        @keyframes winningPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
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
        
        .player-info {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            font-size: 1.2em;
            font-weight: bold;
        }
        
        .player-info .player {
            padding: 10px 20px;
            border-radius: 10px;
            background: rgba(102, 126, 234, 0.1);
        }
        
        .player-info .player.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        @media (max-width: 600px) {
            .tic-tac-toe-board {
                gap: 5px;
            }
            
            .cell {
                font-size: 3em;
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
            <h1>⭕ Tic Tac Toe</h1>
            <p style="color: #666;">Cờ Caro - Đấu với Bot AI</p>
        </div>
        
        <div class="game-info">
            <div class="info-card">
                <div class="label">Số Dư</div>
                <div class="value"><?= number_format($soDu) ?> ₫</div>
            </div>
            <div class="info-card">
                <div class="label">Số Lượt</div>
                <div class="value"><?= $game['moves'] ?></div>
            </div>
            <div class="info-card">
                <div class="label">Lượt Đi</div>
                <div class="value"><?= $game['current_player'] === 'X' ? 'Bạn (X)' : 'Bot (O)' ?></div>
            </div>
        </div>
        
        <?php if ($thongBao): ?>
            <div class="message <?= $ketQuaClass ?>">
                <?= $thongBao ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$game['started']): ?>
            <div class="bet-section">
                <h3 style="margin-bottom: 15px;">💰 Đặt Cược</h3>
                <form method="POST" id="betForm">
                    <input type="hidden" name="action" value="start">
                    <div class="bet-input-group">
                        <input type="text" name="cuoc" id="cuoc" placeholder="Nhập số tiền cược" 
                               value="<?= $game['cuoc'] > 0 ? number_format($game['cuoc']) : '' ?>" required>
                        <button type="submit">Bắt Đầu</button>
                    </div>
                    <div class="quick-bet-buttons">
                        <button type="button" class="quick-bet-btn" onclick="setBet(10000)">10K</button>
                        <button type="button" class="quick-bet-btn" onclick="setBet(50000)">50K</button>
                        <button type="button" class="quick-bet-btn" onclick="setBet(100000)">100K</button>
                        <button type="button" class="quick-bet-btn" onclick="setBet(500000)">500K</button>
                        <button type="button" class="quick-bet-btn" onclick="setBet(<?= $soDu ?>)">Tất Cả</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <?php if ($game['started']): ?>
            <div class="player-info">
                <div class="player <?= $game['current_player'] === 'X' ? 'active' : '' ?>">
                    Bạn: X
                </div>
                <div class="player <?= $game['current_player'] === 'O' ? 'active' : '' ?>">
                    Bot: O
                </div>
            </div>
            
            <div class="tic-tac-toe-board" id="gameBoard">
                <?php for ($i = 0; $i < 9; $i++): ?>
                    <?php
                    $cellValue = $game['board'][$i];
                    $cellClass = '';
                    if ($cellValue === 'X') {
                        $cellClass = 'x';
                    } elseif ($cellValue === 'O') {
                        $cellClass = 'o';
                    }
                    if ($game['game_over'] || $game['current_player'] !== 'X') {
                        $cellClass .= ' disabled';
                    }
                    ?>
                    <div class="cell <?= $cellClass ?>" 
                         data-index="<?= $i ?>"
                         onclick="makeMove(<?= $i ?>)">
                        <?= $cellValue ?>
                    </div>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="newGameForm" style="display: none;">
            <input type="hidden" name="new_game" value="1">
        </form>
        
        <button class="new-game-btn" onclick="newGame()">
            🆕 Game Mới
        </button>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="index.php" style="color: #667eea; text-decoration: none; font-weight: bold;">
                ← Về Trang Chủ
            </a>
        </div>
    </div>
    
    <script>
        function setBet(amount) {
            document.getElementById('cuoc').value = amount.toLocaleString('vi-VN');
        }
        
        function makeMove(index) {
            const cell = document.querySelector(`[data-index="${index}"]`);
            if (cell.classList.contains('disabled')) {
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="move">
                <input type="hidden" name="cell_index" value="${index}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function newGame() {
            if (confirm('Bạn có chắc muốn bắt đầu game mới? Tiền cược hiện tại sẽ mất!')) {
                document.getElementById('newGameForm').submit();
            }
        }
    </script>
</body>
</html>

