<?php
session_start();

// Kiểm tra session
if (session_status() !== PHP_SESSION_ACTIVE) {
    die("Lỗi: Không thể khởi tạo session.");
}

require 'db_connect.php';

// Load theme
require_once 'load_theme.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['Iduser'];

// Lấy thông tin user
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if (!$user = $result->fetch_assoc()) {
    die("Lỗi: Không tìm thấy thông tin người dùng.");
}
$soDu = $user['Money'];
$tenNguoiChoi = $user['Name'];
$stmt->close();

// Initialize game state
$gameOver = $_SESSION['game_over'] ?? false;
$ketQua = $_SESSION['ketqua'] ?? "";
$ketQuaClass = $_SESSION['ketqua_class'] ?? "";

// Hàm rút bài và xác định màu
function rutBai() {
    // Generate a random card (1-52)
    $bai = rand(1, 52);
    $cardValue = ($bai - 1) % 13 + 1;
    $suit = floor(($bai - 1) / 13);
    // Color is red for Hearts/Diamonds, black for Spades/Clubs
    $color = ($suit <= 1) ? 'red' : 'black';
    // Suits and symbols for card display (not image selection)
    $suitNames = ['Hearts', 'Diamonds', 'Spades', 'Clubs'];
    $suitSymbols = ['♥', '♦', '♠', '♣'];
    $cardNames = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
    // Display text (e.g., "A♥")
    $cardDisplay = $cardNames[$cardValue - 1] . $suitSymbols[$suit];
    return [
        'value' => $cardValue,
        'suit' => $suitNames[$suit],
        'color' => $color,
        'display' => $cardDisplay
    ];
}

/*
// Alternative rutBai() without specific suits (uncomment to use)
function rutBai() {
    // Randomly choose red or black
    $color = rand(0, 1) ? 'red' : 'black';
    // Random card value
    $cardNames = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
    $cardValue = rand(1, 13);
    $cardDisplay = $cardNames[$cardValue - 1];
    return [
        'value' => $cardValue,
        'suit' => $color, // No specific suit
        'color' => $color,
        'display' => $cardDisplay
    ];
}
*/

// Xử lý hành động chơi lại
if (isset($_POST['reset_game'])) {
    unset($_SESSION['game_over'], $_SESSION['card'], $_SESSION['cuoc'], $_SESSION['guess'], $_SESSION['ketquaShort']);
    session_write_close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Xử lý action
$action = $_POST['action'] ?? '';
$cuoc = isset($_POST['cuoc']) ? (int)$_POST['cuoc'] : 0;
$guess = $_POST['guess'] ?? '';

if ($action === 'guess' && !$gameOver) {
    if ($cuoc <= 0 || $cuoc > $soDu || !in_array($guess, ['red', 'black'])) {
        $_SESSION['ketqua'] = "Số tiền cược hoặc lựa chọn màu không hợp lệ!";
        $_SESSION['ketqua_class'] = "bg-yellow-400";
        $_SESSION['game_over'] = true; // Set gameOver for invalid inputs
        $ketQua = $_SESSION['ketqua'];
        $ketQuaClass = $_SESSION['ketqua_class'];
    } else {
        $_SESSION['cuoc'] = $cuoc;
        $_SESSION['guess'] = $guess;
        $_SESSION['card'] = rutBai();
        $card = $_SESSION['card'];
        $moneyBefore = $soDu;

        if ($card['color'] === $guess) {
            $soDu += $cuoc * 2;
            $_SESSION['ketqua'] = "Chúc mừng! Đúng màu {$card['color']}! Thắng " . number_format($cuoc * 2) . " VNĐ";
            $_SESSION['ketquaShort'] = "Thắng";
            $_SESSION['ketqua_class'] = "bg-green-500 text-white animate-bounce";
        } else {
            $soDu -= $cuoc;
            $_SESSION['ketqua'] = "Sai rồi! Màu {$card['color']}. Mất " . number_format($cuoc) . " VNĐ";
            $_SESSION['ketquaShort'] = "Thua";
            $_SESSION['ketqua_class'] = "bg-red-500 text-white animate-pulse";
        }

        // Update ketQua and ketQuaClass for immediate display
        $ketQua = $_SESSION['ketqua'];
        $ketQuaClass = $_SESSION['ketqua_class'];

        // Cập nhật số dư
        $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $stmt->bind_param("ii", $soDu, $userId);
        if (!$stmt->execute()) {
            die("Lỗi: Không thể cập nhật số dư.");
        }
        $stmt->close();

        // Lưu lịch sử
        $stmt = $conn->prepare("INSERT INTO color_guess_history (Iduser, Result, Bet, Guess, CardColor, CardDisplay, MoneyBefore, MoneyAfter, PlayedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $ketQuaShort = $_SESSION['ketquaShort'];
        $cardColor = $card['color'];
        $cardDisplay = $card['display'];
        $stmt->bind_param("isiissii", $userId, $ketQuaShort, $cuoc, $guess, $cardColor, $cardDisplay, $moneyBefore, $soDu);
        if (!$stmt->execute()) {
            die("Lỗi: Không thể lưu lịch sử.");
        }
        $stmt->close();

        // Track quest progress và tự động cập nhật streak, VIP, reward points, social feed
        require_once 'game_history_helper.php';
        $winAmount = ($card['color'] === $guess) ? $cuoc * 2 : 0;
        $isWin = ($card['color'] === $guess);
        logGameHistoryWithAll($conn, $userId, 'Bot', $cuoc, $winAmount, $isWin);

        $_SESSION['game_over'] = true;
    }
}

// Lấy trạng thái hiện tại
$card = $_SESSION['card'] ?? null;
$gameOver = $_SESSION['game_over'] ?? false;

// Lấy toàn bộ lịch sử
$lichSu = [];
$stmt = $conn->prepare("SELECT * FROM color_guess_history WHERE Iduser = ? ORDER BY PlayedAt DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $lichSu[] = $row;
}
$stmt->close();

// Chuẩn bị dữ liệu biểu đồ
$thang = $thua = 0;
foreach ($lichSu as $lich) {
    if ($lich['Result'] === 'Thắng') $thang++;
    else $thua++;
}

// Đóng kết nối
$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <title>Đoán Màu Bài</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
        <link rel="stylesheet" href="assets/css/game-effects.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhancements.css">
        <link rel="stylesheet" href="assets/css/game-effects.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhancements.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            position: relative;
        }
        
        /* Three.js canvas background */
        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }
        
        .container, .max-w-7xl {
            position: relative;
            z-index: 1;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select, input[type="number"] {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .card {
            width: 160px;
            height: 220px;
            border: 4px solid #fff;
            border-radius: var(--border-radius-lg);
            background-size: cover;
            background-position: center;
            position: relative;
            perspective: 1000px;
            transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease;
            transform-style: preserve-3d;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            animation: cardAppear 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        @keyframes cardAppear {
            0% {
                opacity: 0;
                transform: scale(0.5) rotateY(-180deg);
            }
            50% {
                transform: scale(1.1) rotateY(-90deg);
            }
            100% {
                opacity: 1;
                transform: scale(1) rotateY(0deg);
            }
        }

        .card-front, .card-back {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            border-radius: var(--border-radius-lg);
            color: #000;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            background-size: cover;
            background-position: center;
            overflow: hidden;
        }

        .card-front {
            background: url('images/den.png') no-repeat;
            background-size: cover;
            transform: rotateY(0deg);
        }

        .card-back {
            background: url('images/do.png') no-repeat;
            background-size: cover;
            transform: rotateY(180deg);
            color: #fff;
        }

        .card.flipped {
            transform: rotateY(180deg);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.5);
        }
        
        .card:hover:not(.flipped) {
            transform: translateY(-10px) scale(1.05);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5);
        }

        .card-top-left {
            font-size: 24px;
            font-weight: bold;
            position: absolute;
            top: 10px;
            left: 10px;
            line-height: 1;
        }

        .card-bottom-right {
            font-size: 24px;
            font-weight: bold;
            position: absolute;
            bottom: 10px;
            right: 10px;
            line-height: 1;
            transform: rotate(180deg);
        }

        .card-center {
            font-size: 60px;
            text-align: center;
            display: flex;
            justify-content: center;
            align-items: center;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
            height: 100%;
        }

        .card.hidden-card {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: transparent;
            border: 4px solid #555;
            position: relative;
            animation: cardPulse 2s ease-in-out infinite;
        }
        
        .card.hidden-card::before {
            content: '?';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 80px;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 700;
        }
        
        @keyframes cardPulse {
            0%, 100% {
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            }
            50% {
                box-shadow: 0 8px 35px rgba(255, 255, 255, 0.3);
            }
        }

        .game-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius-lg);
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .game-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.4);
        }

        button {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        button:hover:not(:disabled) {
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.35);
        }
        
        button:disabled {
            opacity: 0.6;
            cursor: not-allowed !important;
        }

        button:active:not(:disabled) {
            transform: translateY(-2px) scale(1.02);
        }

        .result-message {
            animation: messageAppear 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            border-radius: var(--border-radius);
            padding: 20px;
        }
        
        .result-message.bg-green-500 {
            animation: messageAppear 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55), winPulse 1.5s ease infinite;
            box-shadow: 0 0 30px rgba(34, 197, 94, 0.6);
        }
        
        .result-message.bg-red-500 {
            animation: messageAppear 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55), loseShake 0.8s ease;
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.5);
        }

        @keyframes messageAppear {
            0% {
                opacity: 0;
                transform: translateY(-30px) scale(0.8);
            }
            50% {
                transform: translateY(5px) scale(1.1);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        @keyframes winPulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 30px rgba(34, 197, 94, 0.6);
            }
            50% {
                transform: scale(1.03);
                box-shadow: 0 0 50px rgba(34, 197, 94, 0.9);
            }
        }
        
        @keyframes loseShake {
            0%, 100% { transform: translateX(0) rotate(0deg); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-10px) rotate(-5deg); }
            20%, 40%, 60%, 80% { transform: translateX(10px) rotate(5deg); }
        }

        input[type="number"] {
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            padding: 12px 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--border-radius);
            background: rgba(255, 255, 255, 0.95);
            font-size: 16px;
        }

        input[type="number"]:focus {
            border-color: var(--secondary-color);
            outline: none;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.2);
        }
        
        .balance-display {
            font-size: 20px;
            font-weight: 700;
            color: var(--success-color);
            padding: 15px;
            background: rgba(232, 245, 233, 0.5);
            border-radius: var(--border-radius);
            border: 2px solid var(--success-color);
            margin: 15px 0;
        }
        
        h1, h2 {
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .bg-white {
            background: rgba(255, 255, 255, 0.98) !important;
            border: 2px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2) !important;
            border-radius: var(--border-radius-lg) !important;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .bg-white:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25) !important;
        }
    </style>
</head>
<body class="py-10">
    <canvas id="threejs-background"></canvas>
<div class="max-w-7xl mx-auto px-4">
    <div class="flex flex-col lg:flex-row gap-6">
        <!-- Lịch sử chơi -->
        <div class="bg-white p-6 rounded-xl shadow-xl lg:w-1/3">
            <h2 class="text-2xl font-bold mb-4 text-center">Lịch sử chơi</h2>
            <div class="overflow-x-auto">
                <table class="table-auto w-full text-left border-collapse border border-gray-300">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="border border-gray-300 px-3 py-1">Thời gian</th>
                            <th class="border border-gray-300 px-3 py-1">Kết quả</th>
                            <th class="border border-gray-300 px-3 py-1">Cược</th>
                            <th class="border border-gray-300 px-3 py-1">Đoán</th>
                            <th class="border border-gray-300 px-3 py-1">Màu bài</th>
                            <th class="border border-gray-300 px-3 py-1">Lá bài</th>
                            <th class="border border-gray-300 px-3 py-1">Số dư trước</th>
                            <th class="border border-gray-300 px-3 py-1">Số dư sau</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($lichSu) === 0): ?>
                            <tr><td colspan="8" class="text-center p-4">Chưa có lịch sử chơi</td></tr>
                        <?php else: ?>
                            <?php foreach ($lichSu as $lich): ?>
                            <tr>
                                <td class="border border-gray-300 px-3 py-1"><?= htmlspecialchars($lich['PlayedAt']) ?></td>
                                <td class="border border-gray-300 px-3 py-1"><?= htmlspecialchars($lich['Result']) ?></td>
                                <td class="border border-gray-300 px-3 py-1"><?= number_format($lich['Bet']) ?></td>
                                <td class="border border-gray-300 px-3 py-1"><?= $lich['Guess'] == 'red' ? 'Đỏ' : 'Đen' ?></td>
                                <td class="border border-gray-300 px-3 py-1"><?= $lich['CardColor'] == 'red' ? 'Đỏ' : 'Đen' ?></td>
                                <td class="border border-gray-300 px-3 py-1"><?= htmlspecialchars($lich['CardDisplay']) ?></td>
                                <td class="border border-gray-300 px-3 py-1"><?= number_format($lich['MoneyBefore']) ?></td>
                                <td class="border border-gray-300 px-3 py-1"><?= number_format($lich['MoneyAfter']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Game Đoán Màu -->
        <div class="game-container lg:w-1/3">
            <h1 class="text-3xl font-bold mb-6 text-center" style="color: var(--primary-color);">🎴 Đoán Màu Bài</h1>
            <h2 class="text-xl mb-2 text-center" style="color: var(--text-dark);">Xin chào, <strong><?= htmlspecialchars($tenNguoiChoi, ENT_QUOTES, 'UTF-8') ?></strong></h2>
            <div class="balance-display text-center">💰 Số dư: <strong><?= number_format($soDu, 0, ',', '.') ?> VNĐ</strong></div>

            <!-- Hiển thị lá bài -->
            <div class="flex justify-center mb-6">
                <?php if ($card && $gameOver): ?>
                    <div class="card <?php if ($gameOver) echo 'flipped'; ?>" style="background-image: url('images/<?= $card['color'] == 'red' ? 'do.png' : 'den.png' ?>');">
                        <div class="card-front">
                            <div class="card-top-left"><?= htmlspecialchars($card['display'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="card-center"><?= htmlspecialchars($card['display'][strlen($card['display']) - 1], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="card-bottom-right"><?= htmlspecialchars($card['display'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="card-back">
                            <div class="card-top-left"><?= htmlspecialchars($card['display'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="card-center"><?= htmlspecialchars($card['display'][strlen($card['display']) - 1], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="card-bottom-right"><?= htmlspecialchars($card['display'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card hidden-card"></div>
                <?php endif; ?>
            </div>

            <!-- Hiển thị thông báo kết quả -->
            <?php if (!empty($ketQua)): ?>
                <div class="result-message mb-6 text-center <?= htmlspecialchars($ketQuaClass, ENT_QUOTES, 'UTF-8') ?>">
                    <p class="text-xl font-bold"><?= htmlspecialchars($ketQua, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <?php
                // Clear message after display to prevent it from showing again
                unset($_SESSION['ketqua'], $_SESSION['ketqua_class']);
                ?>
            <?php endif; ?>

            <!-- Form đoán màu -->
            <form method="POST" class="max-w-md mx-auto">
                <div class="mb-4">
                    <input type="number" name="cuoc" placeholder="Nhập số tiền cược" class="w-full px-4 py-2 border rounded bg-white text-black" required min="1" max="<?= $soDu ?>" value="<?= isset($_SESSION['cuoc']) ? $_SESSION['cuoc'] : '' ?>">
                </div>
                <div class="flex justify-center gap-6 mb-4">
                    <button type="submit" name="guess" value="red" class="bg-red-600 text-white px-6 py-2 rounded hover:bg-red-700 flex items-center" <?= $gameOver ? 'disabled' : '' ?>>
                        <span class="mr-2">♥♦</span> Đỏ
                    </button>
                    <button type="submit" name="guess" value="black" class="bg-gray-800 text-white px-6 py-2 rounded hover:bg-gray-900 flex items-center" <?= $gameOver ? 'disabled' : '' ?>>
                        <span class="mr-2">♠♣</span> Đen
                    </button>
                </div>
                <input type="hidden" name="action" value="guess">
            </form>

            <!-- Nút chơi lại -->
            <?php if ($gameOver): ?>
                <form method="POST" class="mt-4 text-center">
                    <button type="submit" name="reset_game" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">🎮 Chơi lại</button>
                </form>
            <?php endif; ?>

            <!-- Nút quay lại trang chủ -->
            <div class="mt-6 text-center">
                <a href="index.php" class="inline-block bg-gray-700 text-white px-6 py-2 rounded hover:bg-black transition">⬅️ Quay lại Trang Chủ</a>
            </div>
        </div>

        <!-- Biểu đồ thống kê -->
        <div class="bg-white p-6 rounded-xl shadow-xl lg:w-1/3">
            <h2 class="text-2xl font-bold mb-4 text-center">Thống kê kết quả</h2>
            <canvas id="chartKetQua" width="300" height="300"></canvas>
        </div>
    </div>
</div>

<script>
    // Đảm bảo cursor luôn hoạt động
    document.addEventListener('DOMContentLoaded', function() {
        document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
        
        const interactiveElements = document.querySelectorAll('button, a, input, label, select');
        interactiveElements.forEach(el => {
            el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
        });
        
        // Animation cho card flip
        const card = document.querySelector('.card.flipped');
        if (card) {
            setTimeout(() => {
                card.style.animation = 'cardFlip 0.8s cubic-bezier(0.4, 0, 0.2, 1)';
            }, 100);
        }
    });
    
    const ctx = document.getElementById('chartKetQua').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Thắng', 'Thua'],
            datasets: [{
                label: 'Số trận',
                data: [<?= $thang ?>, <?= $thua ?>],
                backgroundColor: ['#22c55e', '#ef4444'],
                borderColor: ['#16a34a', '#b91c1c'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                title: {
                    display: true,
                    text: 'Biểu đồ tỷ lệ kết quả các ván chơi'
                }
            }
        }
    });
    
    // Initialize Three.js Background
    (function() {
        window.themeConfig = {
            particleCount: <?= $particleCount ?>,
            particleSize: <?= $particleSize ?>,
            particleColor: '<?= $particleColor ?>',
            particleOpacity: <?= $particleOpacity ?>,
            shapeCount: <?= $shapeCount ?>,
            shapeColors: <?= json_encode($shapeColors) ?>,
            shapeOpacity: <?= $shapeOpacity ?>,
            bgGradient: <?= json_encode($bgGradient) ?>
        };
        const script = document.createElement('script');
        script.src = 'threejs-background.js';
        script.onload = function() { console.log('Three.js background loaded'); };
        document.head.appendChild(script);
    })();
</script>

    <script src="assets/js/game-effects.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="assets/js/game-effects-auto.js"></script>

        <script src="assets/js/game-enhancements.js"></script>
<script>
    // Auto initialize game effects
    if (typeof GameEffectsAuto !== 'undefined') {
        GameEffectsAuto.init();
    }
</script>
</body>
</html>