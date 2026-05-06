<?php
// Bật error reporting để debug
error_reporting(E_ALL);
ini_set('display_errors', 1); // Hiển thị lỗi để debug
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Bắt đầu output buffering
ob_start();

// Xử lý lỗi fatal
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Lỗi</title></head><body>";
        echo "<h1>Lỗi PHP Fatal Error</h1>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($error['file']) . "</p>";
        echo "<p><strong>Line:</strong> " . $error['line'] . "</p>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($error['message']) . "</p>";
        echo "<p><a href='login.php'>← Quay lại đăng nhập</a></p>";
        echo "</body></html>";
        exit();
    }
});

session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

// Kiểm tra file db_connect.php tồn tại
if (!file_exists('db_connect.php')) {
    ob_clean();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Lỗi Hệ Thống</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                color: white;
            }
            .error-box {
                background: rgba(255,255,255,0.95);
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                text-align: center;
                max-width: 500px;
                color: #333;
            }
            .error-box h1 { color: #e74c3c; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>⚠️ Lỗi Hệ Thống</h1>
            <p>Không tìm thấy file kết nối database.</p>
            <p><a href="login.php">← Quay lại đăng nhập</a></p>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Kiểm tra file db_connect.php tồn tại
if (!file_exists('db_connect.php')) {
    die("Lỗi: Không tìm thấy file db_connect.php");
}

try {
require 'db_connect.php';
} catch (Exception $e) {
    ob_clean();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Lỗi Load Database</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                color: white;
            }
            .error-box {
                background: rgba(255,255,255,0.95);
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                text-align: center;
                max-width: 500px;
                color: #333;
            }
            .error-box h1 { color: #e74c3c; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>⚠️ Lỗi Load Database</h1>
            <p><?= htmlspecialchars($e->getMessage()) ?></p>
            <p><a href="login.php">← Quay lại đăng nhập</a></p>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Kiểm tra kết nối database ngay sau khi require
if (!isset($conn) || !$conn) {
    ob_clean();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Lỗi Kết Nối Database</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                color: white;
            }
            .error-box {
                background: rgba(255,255,255,0.95);
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                text-align: center;
                max-width: 500px;
                color: #333;
            }
            .error-box h1 { color: #e74c3c; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>⚠️ Lỗi Kết Nối Database</h1>
            <p>Không thể khởi tạo kết nối database.</p>
            <p><a href="login.php">← Quay lại đăng nhập</a></p>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Load user_progress_helper nếu có
if (file_exists('user_progress_helper.php')) {
require_once 'user_progress_helper.php';
} else {
    // Fallback function nếu không có file
    if (!function_exists('up_add_xp')) {
        function up_add_xp($conn, $userId, $xp) {
            // Fallback - không làm gì nếu không có helper
            return true;
        }
    }
}

// Đảm bảo hàm achievements tồn tại để tránh lỗi nếu file khác chưa khai báo
if (!function_exists('checkMemoryAchievements')) {
    /**
     * Trả về danh sách thông báo achievements đạt được (không ghi DB để an toàn).
     */
    function checkMemoryAchievements($conn, $userId, $data = [])
    {
        $achievements = [];
        $moves = (int)($data['moves'] ?? 0);
        $time = (int)($data['time'] ?? 0);
        $difficulty = $data['difficulty'] ?? 'medium';
        $score = (int)($data['score'] ?? 0);
        $totalGames = (int)($data['total_games'] ?? 0);
        $won = $data['won'] ?? true;

        if ($won && $totalGames === 1) {
            $achievements[] = "🆕 Achievement: Ván đầu tiên thành công!";
        }
        if ($won && $difficulty === 'hard') {
            $achievements[] = "🔥 Achievement: Chinh phục độ khó Khó!";
        }
        if ($won && $time > 0 && $time <= 90) {
            $achievements[] = "⏱️ Achievement: Thần tốc (≤ 1m30)!";
        }
        if ($won && $moves > 0 && $moves <= 12) {
            $achievements[] = "🎯 Achievement: Siêu chuẩn (≤ 12 lượt)!";
        }
        if ($won && $score >= 500000) {
            $achievements[] = "🏅 Achievement: Điểm số 500K!";
        }

        return $achievements;
    }
}

// Kiểm tra kết nối database
if (!$conn || (isset($conn->connect_error) && $conn->connect_error)) {
    $errorMsg = $conn ? $conn->connect_error : "Không thể kết nối";
    error_log("Lỗi kết nối database: " . $errorMsg);
    // Hiển thị lỗi thay vì redirect để debug
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Lỗi Kết Nối Database</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
            }
            .error-box {
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                text-align: center;
                max-width: 500px;
            }
            .error-box h1 { color: #e74c3c; }
            .error-box p { color: #666; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>⚠️ Lỗi Kết Nối Database</h1>
            <p>Không thể kết nối đến cơ sở dữ liệu.</p>
            <p><a href="login.php">← Quay lại đăng nhập</a></p>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Load theme
if (file_exists('load_theme.php')) {
require_once 'load_theme.php';
}
// Đảm bảo $bgGradientCSS luôn được định nghĩa
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

$userId = $_SESSION['Iduser'];
$sql = "SELECT Money, Name FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Lỗi prepare statement: " . $conn->error);
    header("Location: login.php?error=db_prepare");
    exit();
}
$stmt->bind_param("i", $userId);
if (!$stmt->execute()) {
    error_log("Lỗi execute: " . $stmt->error);
    header("Location: login.php?error=db_execute");
    exit();
}
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) {
    error_log("Không tìm thấy thông tin người dùng cho ID: " . $userId);
    header("Location: login.php?error=user_not_found");
    exit();
}
$soDu = $user['Money'] ?? 0;
$tenNguoiChoi = $user['Name'] ?? 'Người chơi';

// Khởi tạo game - Chế độ chơi
$difficulty = $_POST['difficulty'] ?? $_SESSION['memory_game']['difficulty'] ?? 'medium';
$difficultySettings = [
    'easy' => ['gridSize' => 3, 'timeLimit' => 180, 'maxHints' => 5, 'multiplier' => 2.0, 'name' => 'Dễ'],
    'medium' => ['gridSize' => 4, 'timeLimit' => 300, 'maxHints' => 3, 'multiplier' => 2.5, 'name' => 'Trung Bình'],
    'hard' => ['gridSize' => 6, 'timeLimit' => 420, 'maxHints' => 2, 'multiplier' => 3.0, 'name' => 'Khó']
];

$currentDifficulty = $difficultySettings[$difficulty] ?? $difficultySettings['medium'];
$gridSize = $currentDifficulty['gridSize'];
$symbols = ["🍒", "🍋", "🍊", "🍇", "⭐", "💎", "🔔", "7️⃣", "🎰", "🎲", "🎯", "🎪", "🎨", "🎭", "🎬", "🎮", "🏆", "🎁", "💰", "💵", "🎊", "🎈", "🎉", "🎃", "🎄", "🎅", "🤶", "🎄", "🌟", "✨", "🔥", "💫"];

// Lấy best score từ database (kiểm tra cột có tồn tại không)
$bestScore = ['score' => 0, 'moves' => 999, 'time' => 999];

// Kiểm tra xem các cột có tồn tại không
$checkColumns = $conn->query("SHOW COLUMNS FROM users LIKE 'best_memory_score'");
$columnsExist = $checkColumns && $checkColumns->num_rows > 0;

if ($columnsExist) {
$bestScoreSql = "SELECT best_memory_score, best_memory_moves, best_memory_time FROM users WHERE Iduser = ?";
$bestScoreStmt = $conn->prepare($bestScoreSql);
if ($bestScoreStmt) {
    $bestScoreStmt->bind_param("i", $userId);
        if ($bestScoreStmt->execute()) {
    $bestScoreResult = $bestScoreStmt->get_result();
    if ($bestScoreResult && $bestScoreResult->num_rows > 0) {
        $bestData = $bestScoreResult->fetch_assoc();
        $bestScore = [
            'score' => $bestData['best_memory_score'] ?? 0,
            'moves' => $bestData['best_memory_moves'] ?? 999,
            'time' => $bestData['best_memory_time'] ?? 999
        ];
            }
        } else {
            error_log("Lỗi execute best score: " . $bestScoreStmt->error);
    }
    $bestScoreStmt->close();
    }
} else {
    // Các cột chưa tồn tại, tạo chúng (MySQL không hỗ trợ IF NOT EXISTS trong ALTER TABLE)
    $columnsToAdd = [
        'best_memory_score' => 'INT DEFAULT 0',
        'best_memory_moves' => 'INT DEFAULT 999',
        'best_memory_time' => 'INT DEFAULT 999'
    ];
    
    foreach ($columnsToAdd as $columnName => $columnDef) {
        $checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE '$columnName'");
        if (!$checkColumn || $checkColumn->num_rows == 0) {
            try {
                $alterSql = "ALTER TABLE users ADD COLUMN $columnName $columnDef";
                $conn->query($alterSql);
            } catch (Exception $e) {
                // Nếu lỗi do cột đã tồn tại hoặc lỗi khác, log và tiếp tục
                error_log("Lỗi tạo cột $columnName: " . $e->getMessage());
            }
        }
    }
}

// Lấy statistics từ game_history
$stats = [
    'total_games' => 0,
    'wins' => 0,
    'total_winnings' => 0,
    'total_bet' => 0,
    'win_rate' => 0
];

$statsSql = "SELECT 
    COUNT(*) as total_games,
    SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END) as wins,
    SUM(CASE WHEN is_win = 1 THEN win_amount ELSE 0 END) as total_winnings,
    SUM(bet_amount) as total_bet
    FROM game_history 
    WHERE user_id = ? AND game_name = 'Memory Game'";
$statsStmt = $conn->prepare($statsSql);
if ($statsStmt) {
    $statsStmt->bind_param("i", $userId);
    if (!$statsStmt->execute()) {
        error_log("Lỗi execute stats: " . $statsStmt->error);
    }
    $statsResult = $statsStmt->get_result();
    if ($statsResult && $statsResult->num_rows > 0) {
        $statsData = $statsResult->fetch_assoc();
        $stats['total_games'] = $statsData['total_games'] ?? 0;
        $stats['wins'] = $statsData['wins'] ?? 0;
        $stats['total_winnings'] = $statsData['total_winnings'] ?? 0;
        $stats['total_bet'] = $statsData['total_bet'] ?? 0;
        $stats['win_rate'] = $stats['total_games'] > 0 ? round(($stats['wins'] / $stats['total_games']) * 100, 1) : 0;
    }
    $statsStmt->close();
}

// Khởi tạo game mới
if (!isset($_SESSION['memory_game']) || isset($_POST['new_game']) || (isset($_POST['difficulty']) && ($_POST['difficulty'] !== ($_SESSION['memory_game']['difficulty'] ?? 'medium')))) {
    // Tạo cặp thẻ
    $cards = [];
    $pairs = array_slice($symbols, 0, (int)(($gridSize * $gridSize) / 2));
    foreach ($pairs as $symbol) {
        $cards[] = $symbol;
        $cards[] = $symbol;
    }
    shuffle($cards);
    
    $_SESSION['memory_game'] = [
        'cards' => $cards,
        'flipped' => [],
        'matched' => [],
        'moves' => 0,
        'started' => false,
        'cuoc' => 0,
        'start_time' => time(),
        'time_limit' => $currentDifficulty['timeLimit'],
        'hints_used' => 0,
        'max_hints' => $currentDifficulty['maxHints'],
        'difficulty' => $difficulty,
        'multiplier' => $currentDifficulty['multiplier']
    ];
}

$game = $_SESSION['memory_game'];
$thongBao = "";
$ketQuaClass = "";
$laThang = false;
$winAmount = 0;

// Xử lý game logic
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");
    $cardIndex = isset($_POST["card_index"]) ? (int)$_POST["card_index"] : -1;
    
    if ($action === "start" && $cuoc > 0) {
        if ($cuoc > $soDu || $cuoc <= 0) {
            $thongBao = "⚠️ Số tiền cược không hợp lệ!";
            $ketQuaClass = "thua";
        } else {
            $_SESSION['memory_game']['cuoc'] = $cuoc;
            $_SESSION['memory_game']['started'] = true;
            $_SESSION['memory_game']['start_time'] = time();
            $soDu -= $cuoc;
            
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
            
            $thongBao = "🎯 Đã đặt cược " . number_format($cuoc) . " VNĐ! Tìm các cặp thẻ giống nhau!";
            $ketQuaClass = "thang";
        }
    } elseif ($action === "flip" && $cardIndex >= 0 && $cardIndex < count($game['cards'])) {
        if (!$game['started']) {
            $thongBao = "⚠️ Hãy đặt cược trước!";
            $ketQuaClass = "thua";
        } elseif (in_array($cardIndex, $game['matched'])) {
            $thongBao = "⚠️ Thẻ này đã được ghép!";
            $ketQuaClass = "thua";
        } elseif (in_array($cardIndex, $game['flipped'])) {
            $thongBao = "⚠️ Thẻ này đang mở!";
            $ketQuaClass = "thua";
        } else {
            $_SESSION['memory_game']['flipped'][] = $cardIndex;
            $_SESSION['memory_game']['moves']++;
            
            $flipped = $_SESSION['memory_game']['flipped'];
            
            // Nếu đã lật 2 thẻ, kiểm tra match
            if (count($flipped) === 2) {
                $card1 = $game['cards'][$flipped[0]];
                $card2 = $game['cards'][$flipped[1]];
                
                if ($card1 === $card2) {
                    // Match!
                    $_SESSION['memory_game']['matched'][] = $flipped[0];
                    $_SESSION['memory_game']['matched'][] = $flipped[1];
                    $_SESSION['memory_game']['flipped'] = [];
                    
                    $matchedCount = count($_SESSION['memory_game']['matched']);
                    $totalCards = count($game['cards']);
                    
                    if ($matchedCount === $totalCards) {
                        // Thắng!
                        $timeBonus = 0;
                        $elapsed = time() - $game['start_time'];
                        $timeLimit = $game['time_limit'] ?? 300;
                        if ($elapsed < $timeLimit) {
                            $timeBonus = max(0, ($timeLimit - $elapsed) * 10); // Bonus theo thời gian còn lại
                        }
                        
                        $multiplier = $game['multiplier'] ?? 2.5; // Hệ số thưởng theo độ khó
                        $bonus = max(0, 1000 - ($_SESSION['memory_game']['moves'] * 50)); // Bonus theo số lượt
                        $thang = ($game['cuoc'] * $multiplier) + $bonus + $timeBonus;
                        $soDu += $thang;
                        $winAmount = $thang;
                        $laThang = true;
                        
                        // Tính điểm số
                        $score = ($game['cuoc'] * 100) + ($timeLimit - $elapsed) * 10 + (100 - $_SESSION['memory_game']['moves']) * 5;
                        $isNewRecord = false;
                        
                        // Kiểm tra và lưu best score (chỉ nếu cột tồn tại)
                        $checkColumns = $conn->query("SHOW COLUMNS FROM users LIKE 'best_memory_score'");
                        $columnsExist = $checkColumns && $checkColumns->num_rows > 0;
                        
                        if ($columnsExist) {
                        if ($score > $bestScore['score'] || 
                            ($score == $bestScore['score'] && $_SESSION['memory_game']['moves'] < $bestScore['moves']) ||
                            ($score == $bestScore['score'] && $_SESSION['memory_game']['moves'] == $bestScore['moves'] && $elapsed < $bestScore['time'])) {
                            $isNewRecord = true;
                            $updateBest = $conn->prepare("UPDATE users SET best_memory_score = ?, best_memory_moves = ?, best_memory_time = ? WHERE Iduser = ?");
                                if ($updateBest) {
                            $updateBest->bind_param("iiii", $score, $_SESSION['memory_game']['moves'], $elapsed, $userId);
                                    if (!$updateBest->execute()) {
                                        error_log("Lỗi update best score: " . $updateBest->error);
                                    }
                            $updateBest->close();
                                } else {
                                    error_log("Lỗi prepare update best score: " . $conn->error);
                                }
                            $bestScore = ['score' => $score, 'moves' => $_SESSION['memory_game']['moves'], 'time' => $elapsed];
                            }
                        }
                        
                        $recordText = $isNewRecord ? " 🏆 KỶ LỤC MỚI!" : "";
                        $thongBao = "🎉 Hoàn thành! Thắng " . number_format($thang) . " VNĐ! (Số lượt: " . $_SESSION['memory_game']['moves'] . ", Thời gian: " . gmdate("i:s", $elapsed) . ", Điểm: " . number_format($score) . ")" . $recordText;
                        $ketQuaClass = "thang";
                        
                        // Track quest progress
                        require_once 'game_history_helper.php';
                        logGameHistoryWithAll($conn, $userId, 'Memory Game', $game['cuoc'], $thang, true);
                        
                        // Cộng XP
                        $baseXp = 20;
                        $movesXp = max(0, 50 - $_SESSION['memory_game']['moves']);
                        $timeXp = max(0, 30 - floor($elapsed / 10)); // Bonus XP nếu hoàn thành nhanh
                        $difficultyXp = ($difficulty === 'hard' ? 10 : ($difficulty === 'medium' ? 5 : 0)); // Bonus XP theo độ khó
                        $totalXp = $baseXp + $movesXp + $timeXp + $difficultyXp;
                        up_add_xp($conn, $userId, $totalXp);
                        
                        // Kiểm tra và trao achievements
                        $achievements = checkMemoryAchievements($conn, $userId, [
                            'moves' => $_SESSION['memory_game']['moves'],
                            'time' => $elapsed,
                            'difficulty' => $difficulty,
                            'score' => $score,
                            'total_games' => $stats['total_games'] + 1,
                            'won' => true
                        ]);
                        if (!empty($achievements)) {
                            $thongBao .= "<br>" . implode("<br>", $achievements);
                        }
                        
                        // Reset game
                        unset($_SESSION['memory_game']);
                    } else {
                        $thongBao = "✅ Ghép đúng! Còn " . (int)(($totalCards - $matchedCount) / 2) . " cặp nữa!";
                        $ketQuaClass = "thang";
                    }
                } else {
                    // Không match, đợi 1 giây rồi lật lại
                    $thongBao = "❌ Không khớp! Thử lại!";
                    $ketQuaClass = "thua";
                    // Sẽ reset flipped sau khi hiển thị
                }
            } else {
                $thongBao = "🔄 Đã lật " . count($flipped) . " thẻ. Chọn thẻ thứ 2!";
                $ketQuaClass = "thang";
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
        }
    } elseif ($action === "reset_flipped") {
        // Reset flipped cards (sau khi không match)
        if (count($game['flipped']) === 2) {
            $_SESSION['memory_game']['flipped'] = [];
        }
    } elseif ($action === "use_hint") {
        // Sử dụng hint
        if (!$game['started']) {
            $thongBao = "⚠️ Hãy đặt cược trước!";
            $ketQuaClass = "thua";
        } elseif ($game['hints_used'] >= $game['max_hints']) {
            $thongBao = "⚠️ Đã hết hint!";
            $ketQuaClass = "thua";
        } else {
            // Tìm một cặp chưa được ghép và hiển thị hint
            $unmatched = [];
            $cardValues = [];
            for ($i = 0; $i < count($game['cards']); $i++) {
                if (!in_array($i, $game['matched'])) {
                    $cardValue = $game['cards'][$i];
                    if (!isset($cardValues[$cardValue])) {
                        $cardValues[$cardValue] = [];
                    }
                    $cardValues[$cardValue][] = $i;
                }
            }
            
            // Tìm cặp đầu tiên chưa được ghép
            $hintCards = [];
            foreach ($cardValues as $value => $indices) {
                if (count($indices) >= 2) {
                    $hintCards = array_slice($indices, 0, 2);
                    break;
                }
            }
            
            if (count($hintCards) === 2) {
                $_SESSION['memory_game']['hints_used']++;
                $_SESSION['memory_game']['hint_cards'] = $hintCards;
                $_SESSION['memory_game']['hint_time'] = time();
                $thongBao = "💡 Hint: Xem 2 thẻ được đánh dấu!";
                $ketQuaClass = "thang";
            } else {
                $thongBao = "⚠️ Không tìm thấy cặp để hint!";
                $ketQuaClass = "thua";
            }
        }
    }
}

// Lấy game state hiện tại
$game = $_SESSION['memory_game'] ?? null;
if (!$game) {
    // Tạo game mới nếu chưa có
    $cards = [];
    $pairs = array_slice($symbols, 0, (int)(($gridSize * $gridSize) / 2));
    foreach ($pairs as $symbol) {
        $cards[] = $symbol;
        $cards[] = $symbol;
    }
    shuffle($cards);
    
    $_SESSION['memory_game'] = [
        'cards' => $cards,
        'flipped' => [],
        'matched' => [],
        'moves' => 0,
        'started' => false,
        'cuoc' => 0,
        'start_time' => time(),
        'time_limit' => 300, // 5 phút (300 giây)
        'hints_used' => 0,
        'max_hints' => 3
    ];
    $game = $_SESSION['memory_game'];
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
    <title>Memory Game - Trò Chơi Trí Nhớ</title>
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
            max-width: 800px;
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
        
        .bet-input-group button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .memory-grid {
            display: grid;
            grid-template-columns: repeat(<?= $gridSize ?>, 1fr);
            gap: 10px;
            margin: 20px 0;
        }
        
        .memory-card {
            aspect-ratio: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5em;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 3px solid transparent;
        }
        
        .memory-card::before {
            content: '?';
            position: absolute;
            font-size: 2.5em;
            color: white;
            transition: opacity 0.3s;
        }
        
        .memory-card.flipped::before,
        .memory-card.matched::before {
            opacity: 0;
        }
        
        .memory-card:hover:not(.flipped):not(.matched) {
            transform: scale(1.05);
            border-color: #fff;
        }
        
        .memory-card.flipped,
        .memory-card.matched {
            background: white;
            border-color: #667eea;
        }
        
        .memory-card.matched {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .memory-card.flipped:not(.matched) {
            animation: flipCard 0.3s;
        }
        
        @keyframes flipCard {
            0% { transform: rotateY(0deg); }
            50% { transform: rotateY(90deg); }
            100% { transform: rotateY(0deg); }
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
        
        .hint-btn {
            padding: 12px 25px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(240, 147, 251, 0.4);
        }
        
        .hint-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(240, 147, 251, 0.6);
        }
        
        .hint-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .memory-card.hint-card {
            animation: hintPulse 1s ease-in-out infinite;
            border-color: #f5576c !important;
            box-shadow: 0 0 20px rgba(245, 87, 108, 0.6);
        }
        
        @keyframes hintPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .memory-card.matched {
            animation: matchSuccess 0.5s ease-out;
        }
        
        @keyframes matchSuccess {
            0% { transform: scale(1); }
            50% { transform: scale(1.2) rotate(5deg); }
            100% { transform: scale(1); }
        }
        
        #timerCard.warning {
            background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
            animation: timerWarning 1s ease-in-out infinite;
        }
        
        @keyframes timerWarning {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .best-score-section {
            margin-bottom: 20px;
        }
        
        .best-score-card {
            background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(253, 160, 133, 0.3);
        }
        
        .best-score-label {
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .best-score-details {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 0.95em;
        }
        
        .best-score-details span {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 12px;
            border-radius: 20px;
        }
        
        .difficulty-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .difficulty-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .difficulty-btn {
            background: white;
            border: 3px solid #dee2e6;
            border-radius: 12px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .difficulty-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .difficulty-btn.active {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .difficulty-name {
            font-size: 1.3em;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .difficulty-details {
            display: flex;
            flex-direction: column;
            gap: 5px;
            font-size: 0.9em;
        }
        
        .difficulty-details span {
            padding: 3px 0;
        }
        
        .current-difficulty-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 15px;
            font-size: 1.1em;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .statistics-section {
            margin-bottom: 20px;
        }
        
        .statistics-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .statistics-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .statistics-header h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.3em;
        }
        
        .toggle-stats {
            background: none;
            border: none;
            font-size: 1.2em;
            cursor: pointer;
            color: #667eea;
            transition: transform 0.3s;
        }
        
        .toggle-stats:hover {
            transform: scale(1.2);
        }
        
        .statistics-content {
            max-height: 500px;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        
        .statistics-content.collapsed {
            max-height: 0;
            overflow: hidden;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .stat-item {
            background: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .stat-label {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 1.5em;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .stat-value.positive {
            color: #28a745;
        }
        
        .stat-value.negative {
            color: #dc3545;
        }
        
        @media (max-width: 768px) {
            .game-container {
                padding: 20px;
                margin: 10px;
            }
            
            .game-header h1 {
                font-size: 2em;
            }
            
            .game-info {
                gap: 10px;
            }
            
            .info-card {
                min-width: 100px;
                padding: 12px 15px;
            }
            
            .info-card .value {
                font-size: 1.2em;
            }
            
            .memory-grid {
                gap: 8px;
            }
            
            .memory-card {
                font-size: 2em;
            }
            
            .bet-input-group {
                flex-direction: column;
            }
            
            .bet-input-group input {
                width: 100%;
            }
            
            .bet-input-group button {
                width: 100%;
            }
            
            .quick-bet-buttons {
                justify-content: center;
            }
            
            .quick-bet-btn {
                flex: 1;
                min-width: 60px;
            }
            
            .hint-btn {
                width: 100%;
                font-size: 1em;
                padding: 10px 20px;
            }
        }
        
        @media (max-width: 480px) {
            .game-container {
                padding: 15px;
            }
            
            .game-header h1 {
                font-size: 1.5em;
            }
            
            .info-card {
                min-width: 80px;
                padding: 10px 12px;
            }
            
            .info-card .label {
                font-size: 0.8em;
            }
            
            .info-card .value {
                font-size: 1em;
            }
            
            .memory-grid {
                gap: 5px;
            }
            
            .memory-card {
                font-size: 1.5em;
            }
            
            .message {
                font-size: 1em;
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="game-container">
        <div class="game-header">
            <h1>🧠 Memory Game</h1>
            <p style="color: #666;">Tìm các cặp thẻ giống nhau để thắng!</p>
        </div>
        
        <!-- Best Score Display -->
        <?php if ($bestScore['score'] > 0): ?>
        <div class="best-score-section">
            <div class="best-score-card">
                <div class="best-score-label">🏆 Kỷ Lục Cá Nhân</div>
                <div class="best-score-details">
                    <span>Điểm: <strong><?= number_format($bestScore['score']) ?></strong></span>
                    <span>Lượt: <strong><?= $bestScore['moves'] ?></strong></span>
                    <span>Thời gian: <strong><?= gmdate("i:s", $bestScore['time']) ?></strong></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Statistics Section -->
        <?php if ($stats['total_games'] > 0): ?>
        <div class="statistics-section">
            <div class="statistics-card">
                <div class="statistics-header">
                    <h3>📊 Thống Kê</h3>
                    <button class="toggle-stats" onclick="toggleStatistics()">▼</button>
                </div>
                <div class="statistics-content" id="statsContent">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-label">Tổng Số Game</div>
                            <div class="stat-value"><?= number_format($stats['total_games']) ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Số Lần Thắng</div>
                            <div class="stat-value"><?= number_format($stats['wins']) ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Tỷ Lệ Thắng</div>
                            <div class="stat-value"><?= $stats['win_rate'] ?>%</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Tổng Thắng</div>
                            <div class="stat-value"><?= number_format($stats['total_winnings']) ?> ₫</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Tổng Cược</div>
                            <div class="stat-value"><?= number_format($stats['total_bet']) ?> ₫</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Lợi Nhuận</div>
                            <div class="stat-value <?= ($stats['total_winnings'] - $stats['total_bet']) >= 0 ? 'positive' : 'negative' ?>">
                                <?= number_format($stats['total_winnings'] - $stats['total_bet']) ?> ₫
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
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
                <div class="label">Đã Ghép</div>
                <div class="value"><?= (int)(count($game['matched']) / 2) ?> / <?= (int)(($gridSize * $gridSize) / 2) ?></div>
            </div>
            <?php if ($game['started']): ?>
            <div class="info-card" id="timerCard">
                <div class="label">Thời Gian</div>
                <div class="value" id="timerValue"><?= gmdate("i:s", ($game['time_limit'] ?? 300) - (time() - $game['start_time'])) ?></div>
            </div>
            <div class="info-card">
                <div class="label">Hint</div>
                <div class="value"><?= ($game['max_hints'] ?? 3) - ($game['hints_used'] ?? 0) ?> / <?= $game['max_hints'] ?? 3 ?></div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($thongBao): ?>
            <div class="message <?= $ketQuaClass ?>">
                <?= $thongBao ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$game['started']): ?>
            <!-- Difficulty Selector -->
            <div class="difficulty-section">
                <h3 style="margin-bottom: 15px; text-align: center;">🎯 Chọn Độ Khó</h3>
                <div class="difficulty-buttons">
                    <?php foreach ($difficultySettings as $key => $setting): ?>
                        <button type="button" 
                                class="difficulty-btn <?= ($game['difficulty'] ?? 'medium') === $key ? 'active' : '' ?>"
                                data-difficulty="<?= $key ?>"
                                onclick="selectDifficulty('<?= $key ?>', event)">
                            <div class="difficulty-name"><?= $setting['name'] ?></div>
                            <div class="difficulty-details">
                                <span>📐 <?= $setting['gridSize'] ?>x<?= $setting['gridSize'] ?></span>
                                <span>⏱️ <?= gmdate("i:s", $setting['timeLimit']) ?></span>
                                <span>💡 <?= $setting['maxHints'] ?> hints</span>
                                <span>💰 x<?= $setting['multiplier'] ?></span>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="bet-section">
                <h3 style="margin-bottom: 15px;">💰 Đặt Cược</h3>
                <form method="POST" id="betForm">
                    <input type="hidden" name="action" value="start">
                    <input type="hidden" name="difficulty" id="selectedDifficulty" value="<?= $game['difficulty'] ?? 'medium' ?>">
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
        <?php else: ?>
            <!-- Current Difficulty Badge -->
            <div class="current-difficulty-badge">
                Độ khó: <strong><?= $currentDifficulty['name'] ?></strong> (<?= $gridSize ?>x<?= $gridSize ?>, x<?= $currentDifficulty['multiplier'] ?>)
            </div>
        <?php endif; ?>
        
        <?php if ($game['started']): ?>
            <div style="text-align: center; margin-bottom: 15px;">
                <button onclick="useHint()" class="hint-btn" id="hintBtn" 
                        <?= (($game['hints_used'] ?? 0) >= ($game['max_hints'] ?? 3)) ? 'disabled' : '' ?>>
                    💡 Sử Dụng Hint (<?= ($game['max_hints'] ?? 3) - ($game['hints_used'] ?? 0) ?>)
                </button>
            </div>
            <div class="memory-grid" id="memoryGrid">
                <?php for ($i = 0; $i < count($game['cards']); $i++): ?>
                    <?php
                    $isFlipped = in_array($i, $game['flipped']);
                    $isMatched = in_array($i, $game['matched']);
                    $isHint = isset($game['hint_cards']) && in_array($i, $game['hint_cards']) && (time() - ($game['hint_time'] ?? 0)) < 3;
                    $cardClass = '';
                    if ($isMatched) {
                        $cardClass = 'matched';
                    } elseif ($isFlipped) {
                        $cardClass = 'flipped';
                    }
                    if ($isHint) {
                        $cardClass .= ' hint-card';
                    }
                    ?>
                    <div class="memory-card <?= $cardClass ?>" 
                         data-index="<?= $i ?>"
                         onclick="flipCard(<?= $i ?>)">
                        <?php if ($isFlipped || $isMatched): ?>
                            <?= $game['cards'][$i] ?>
                        <?php endif; ?>
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
    
    <script src="assets/js/game-confetti.js"></script>
    <script>
        function setBet(amount) {
            document.getElementById('cuoc').value = amount.toLocaleString('vi-VN');
        }
        
        function selectDifficulty(difficulty, ev) {
            // Update UI
            document.querySelectorAll('.difficulty-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.difficulty === difficulty) {
                    btn.classList.add('active');
                }
            });
            
            // Update hidden input
            document.getElementById('selectedDifficulty').value = difficulty;
            
            // Reload page with new difficulty
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="difficulty" value="${difficulty}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function toggleStatistics() {
            const content = document.getElementById('statsContent');
            const toggle = document.querySelector('.toggle-stats');
            if (content.classList.contains('collapsed')) {
                content.classList.remove('collapsed');
                toggle.textContent = '▼';
            } else {
                content.classList.add('collapsed');
                toggle.textContent = '▶';
            }
        }
        
        function flipCard(index) {
            const card = document.querySelector(`[data-index="${index}"]`);
            if (card.classList.contains('flipped') || card.classList.contains('matched')) {
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="flip">
                <input type="hidden" name="card_index" value="${index}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function useHint() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="use_hint">';
            document.body.appendChild(form);
            form.submit();
        }
        
        function newGame() {
            if (confirm('Bạn có chắc muốn bắt đầu game mới? Tiền cược hiện tại sẽ mất!')) {
                document.getElementById('newGameForm').submit();
            }
        }
        
        // Timer countdown
        <?php if ($game['started']): ?>
        let timeLimit = <?= $game['time_limit'] ?? 300 ?>;
        let startTime = <?= $game['start_time'] ?? time() ?>;
        let currentTime = <?= time() ?>;
        let elapsed = currentTime - startTime;
        let remaining = Math.max(0, timeLimit - elapsed);
        
        function updateTimer() {
            const timerValue = document.getElementById('timerValue');
            const timerCard = document.getElementById('timerCard');
            
            if (remaining <= 0) {
                timerValue.textContent = '00:00';
                if (timerCard) {
                    timerCard.classList.add('warning');
                }
                // Game over - tự động reset
                setTimeout(() => {
                    alert('⏰ Hết thời gian! Game kết thúc!');
                    document.getElementById('newGameForm').submit();
                }, 1000);
                return;
            }
            
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            timerValue.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            
            // Cảnh báo khi còn 30 giây
            if (remaining <= 30 && timerCard) {
                timerCard.classList.add('warning');
            }
            
            remaining--;
        }
        
        updateTimer();
        setInterval(updateTimer, 1000);
        <?php endif; ?>
        
        // Auto reset flipped cards sau 1.5 giây nếu không match
        <?php if (count($game['flipped']) === 2 && !$laThang && !$thongBao): ?>
            setTimeout(function() {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="reset_flipped">';
                document.body.appendChild(form);
                form.submit();
            }, 1500);
        <?php endif; ?>
        
        // Confetti khi thắng
        <?php if ($laThang): ?>
        window.addEventListener('load', function() {
            if (typeof GameConfetti !== 'undefined') {
                const confetti = new GameConfetti();
                confetti.createConfetti(150, {
                    x: window.innerWidth / 2,
                    y: window.innerHeight / 2,
                    duration: 4000
                });
            }
        });
        <?php endif; ?>
        
        // Auto remove hint effect sau 3 giây
        <?php if (isset($game['hint_cards']) && (time() - ($game['hint_time'] ?? 0)) < 3): ?>
        setTimeout(function() {
            document.querySelectorAll('.hint-card').forEach(card => {
                card.classList.remove('hint-card');
            });
        }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>
<?php
// Flush output buffer để đảm bảo nội dung được hiển thị
ob_end_flush();
?>

