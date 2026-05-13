<?php
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}
require '../db_connect.php';

// AJAX history endpoint
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_history') {
    header('Content-Type: application/json; charset=utf-8');
    
    $id = $_SESSION['Iduser'] ?? 0;
    $sql = "SELECT * FROM history_hopmu WHERE Iduser = ? ORDER BY Time DESC LIMIT 20";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'history' => $history
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once '../load_theme.php';






$userId = $_SESSION['Iduser'];
$sql = "SELECT Money, Name FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();


// Get statistics from database for chart
$gameThang = 0;
$gameThua = 0;
$sqlStats = "SELECT COUNT(*) as total, SUM(CASE WHEN WinAmount > 0 THEN 1 ELSE 0 END) as wins FROM history_hopmu WHERE Iduser = ?";
$stmtStats = $conn->prepare($sqlStats);
$stmtStats->bind_param("i", $userId);
$stmtStats->execute();
$resultStats = $stmtStats->get_result();
if ($rowStats = $resultStats->fetch_assoc()) {
    $gameThang = $rowStats['wins'] ?? 0;
    $gameThua = ($rowStats['total'] ?? 0) - $gameThang;
}
$stmtStats->close();


if (!$user) {
    die("User not found");
}

$currentBalance = $user['Money'];
$userName = $user['Name'];

// Mức phí mỗi lần bóc
$cost = 50000;

// Xử lý AJAX request
if (isset($_GET['action']) && $_GET['action'] === 'open_bag') {
    header('Content-Type: application/json');

    if ($currentBalance < $cost) {
        echo json_encode([
            'success' => false,
            'message' => "⚠️ Bạn không đủ Số Gtlm. Cần " . number_format($cost) . " gtlm."
        ]);
        exit;
    }

    // Trừ phí
    $newBalanceAfterCost = $currentBalance - $cost;

    // Danh sách phần thưởng (Tỉ lệ trượt cao ~80%, thưởng lớn cực hiếm)
    $bags = [
        ["label" => "Trượt!", "reward" => 0],
        ["label" => "Trượt!", "reward" => 0],
        ["label" => "Trượt!", "reward" => 0],
        ["label" => "Trượt!", "reward" => 0],
        ["label" => "Trượt!", "reward" => 0],
        ["label" => "Trượt!", "reward" => 0],
        ["label" => "Trượt!", "reward" => 0],
        ["label" => "Trượt!", "reward" => 0],
        ["label" => "Trượt!", "reward" => 0],
        ["label" => "Trượt!", "reward" => 0],
        ["label" => "Trượt!", "reward" => 0],
        ["label" => "Trượt!", "reward" => 0],
        ["label" => "Trượt!", "reward" => 0],
        ["label" => "Trượt!", "reward" => 0],
        ["label" => "Trượt!", "reward" => 0],
        ["label" => "Trượt!", "reward" => 0],
        ["label" => "Trượt!", "reward" => 0],
        ["label" => "Trượt!", "reward" => 0],
        ["label" => "Trượt!", "reward" => 0],
        ["label" => "Trượt!", "reward" => 0],
        ["label" => "10.000 gtlm", "reward" => 10000],
        ["label" => "10.000 gtlm", "reward" => 10000],
        ["label" => "10.000 gtlm", "reward" => 10000],
        ["label" => "50.000 gtlm", "reward" => 50000],
        ["label" => "100.000 gtlm", "reward" => 100000],
    ];

    // Thêm các phần thưởng lớn vào pool với xác suất thấp (Jackpot style)
    if (rand(1, 100) <= 5) { // 5% cơ hội trúng quà cực cực lớn
        $jackpot = [
            ["label" => "200.000 gtlm", "reward" => 200000],
            ["label" => "500.000 gtlm", "reward" => 500000],
            ["label" => "1.000.000 gtlm", "reward" => 1000000],
        ];
        $randomBag = $jackpot[array_rand($jackpot)];
    } else {
        $randomBag = $bags[array_rand($bags)];
    }

    $rewardText = $randomBag['label'];
    $rewardAmount = $randomBag['reward'];
    $isWin = $rewardAmount > 0;

    $finalBalance = $newBalanceAfterCost + $rewardAmount;

    // Cập nhật DB
    $update = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
    $update->bind_param("di", $finalBalance, $userId);
    $update->execute();
    $update->close();

    // Lưu lịch sử
    require_once '../game_history_helper.php';
    logGameHistory($conn, $userId, 'Hộp Mù', $cost, $rewardAmount, $isWin);

    $historyStmt = $conn->prepare("INSERT INTO history_hopmu (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
    if ($historyStmt) {
        $historyStmt->bind_param("iisi", $userId, $cost, $rewardText, $rewardAmount);
        $historyStmt->execute();
        $historyStmt->close();
    }

    echo json_encode([
        'success' => true,
        'isWin' => $isWin,
        'message' => $isWin ? "🎉 Xin chúc mừng! Bạn nhận được: <b>$rewardText</b>" : "😢 Rất tiếc! Bạn đã bóc trượt. Thử lại nhé!",
        'newBalance' => number_format($finalBalance, 0, ',', '.'),
        'rewardAmount' => $rewardAmount
    ]);
    exit;
}

$message = "";

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Bóc Túi Mù</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <link rel="stylesheet" href="../assets/css/game-effects.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/canvas-confetti/1.6.0/confetti.browser.min.js"></script>
    <style>
        body {
            position: relative;
            cursor: url('../img/chuot.png'), auto !important;
            font-family: 'Segoe UI', sans-serif;
            background:
                <?= $bgGradientCSS ?>
            ;
            text-align: center;
            padding: 40px 20px;
            min-height: 100vh;
            overflow-x: hidden;
        }

        * {
            cursor: inherit;
        }

        button,
        a,
        input[type="button"],
        input[type="submit"],
        label,
        select {
            cursor: url('../img/tay.png'), pointer !important;
        }

        .game-container {
            max-width: 600px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.98);
            padding: 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
        }

        h1 {
            color: var(--primary-color);
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .balance-display {
            font-size: 22px;
            font-weight: 700;
            color: var(--success-color);
            padding: 15px;
            background: rgba(232, 245, 233, 0.5);
            border-radius: var(--border-radius);
            border: 2px solid var(--success-color);
            margin: 20px 0;
        }

        .cost-info {
            font-size: 18px;
            font-weight: 600;
            color: var(--warning-color);
            padding: 12px;
            background: rgba(243, 156, 18, 0.1);
            border-radius: var(--border-radius);
            border: 2px solid var(--warning-color);
            margin: 20px 0;
        }

        button {
            background: linear-gradient(135deg, #00796b 0%, #004d40 100%);
            color: white;
            font-size: 20px;
            font-weight: 700;
            padding: 16px 40px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            margin-top: 30px;
            box-shadow: 0 4px 15px rgba(0, 121, 107, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        button:hover::before {
            width: 300px;
            height: 300px;
        }

        button:hover:not(:disabled) {
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 8px 25px rgba(0, 121, 107, 0.6);
            background: linear-gradient(135deg, #004d40 0%, #00796b 100%);
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed !important;
        }

        .result {
            font-size: 22px;
            font-weight: 700;
            margin-top: 30px;
            padding: 20px;
            border-radius: var(--border-radius);
            animation: messageAppear 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            line-height: 1.8;
        }

        .result.win {
            color: #00ff00;
            background: rgba(40, 167, 69, 0.2);
            border: 3px solid #28a745;
            box-shadow: 0 0 25px rgba(40, 167, 69, 0.6);
            animation: messageAppear 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55), winPulse 1.5s ease infinite;
        }

        .result.lose {
            color: #ff6b6b;
            background: rgba(220, 53, 69, 0.2);
            border: 3px solid #dc3545;
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

            0%,
            100% {
                transform: scale(1);
                box-shadow: 0 0 25px rgba(40, 167, 69, 0.6);
            }

            50% {
                transform: scale(1.02);
                box-shadow: 0 0 40px rgba(40, 167, 69, 0.9);
            }
        }

        a {
            display: inline-block;
            margin-top: 25px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        a:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.5);
        }

        .bag-icon {
            font-size: 80px;
            margin: 20px 0;
            animation: bagShake 2s ease-in-out infinite;
        }

        @keyframes bagShake {

            0%,
            100% {
                transform: translateY(0) rotate(0deg);
            }

            10%,
            30%,
            50%,
            70%,
            90% {
                transform: translateY(-5px) rotate(-3deg);
            }

            20%,
            40%,
            60%,
            80% {
                transform: translateY(-5px) rotate(3deg);
            }
        }

        .bag-icon.opening {
            animation: bagOpening 1s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
        }

        @keyframes bagOpening {
            0% {
                transform: scale(1) rotate(0deg);
            }

            20% {
                transform: scale(1.2) rotate(-10deg);
            }

            40% {
                transform: scale(1.2) rotate(10deg);
            }

            60% {
                transform: scale(1.4) rotate(0deg);
                opacity: 1;
                filter: brightness(1);
            }

            80% {
                transform: scale(1.6) rotate(0deg);
                opacity: 0.5;
                filter: brightness(2);
            }

            100% {
                transform: scale(2) rotate(0deg);
                opacity: 0;
                filter: brightness(3);
            }
        }

        .result {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.5s ease-out;
        }

        .result.show {
            opacity: 1;
            transform: translateY(0);
        }
    
        /* Statistics Container */
        .stats-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .stat-item.wins {
            border-left: 4px solid #4ade80;
        }
        
        .stat-item.losses {
            border-left: 4px solid #ff6b6b;
        }
        
        .stat-item .label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        
        .stat-item .value {
            font-size: 28px;
            font-weight: 700;
            color: #ffd700;
        }
        
        .chart-box {
            display: flex;
            flex-direction: column;
        }
        
        .chart-box canvas {
            margin-top: 20px;
        }

    </style>
</head>

<body>

    <div class="game-container">
        <h1>🎁 Bóc Túi Mù</h1>
        <p style="font-size: 18px; margin: 10px 0;">Xin chào
            <strong><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></strong>
        </p>
        <div class="balance-display">💰 Số Gtlm hiện tại: <strong
                id="user-balance"><?= number_format($currentBalance, 0, ',', '.') ?> gtlm</strong></div>
        <div class="cost-info">💸 Chi phí mỗi lần bóc: <strong><?= number_format($cost, 0, ',', '.') ?> gtlm</strong>
        </div>

        <div class="bag-icon">🎁</div>

        <div id="result-container"></div>

        <form id="bagForm">
            <button type="submit" id="submitBtn">👉 Bóc túi ngay!</button>
        </form>
        <p><a href="../index.php">🏠 Quay Lại Trang Chủ</a></p>


    </div>

    <script>
        // Đảm bảo cursor luôn hoạt động
        document.addEventListener('DOMContentLoaded', function () {
            document.body.style.cursor = "url('../img/chuot.png'), auto";

            const interactiveElements = document.querySelectorAll('button, a, input, label, select');
            interactiveElements.forEach(el => {
                el.style.cursor = "url('../img/tay.png'), pointer";
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('bagForm');
            const submitBtn = document.getElementById('submitBtn');
            const bag = document.querySelector('.bag-icon');
            const resultContainer = document.getElementById('result-container');
            const balanceEl = document.getElementById('user-balance');

            if (form) {
                form.addEventListener('submit', async function (e) {
                    e.preventDefault();

                    if (submitBtn.disabled) return;

                    // Reset trạng thái
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = 'đang mở... 🎁';
                    bag.classList.add('opening');
                    resultContainer.innerHTML = '';

                    try {
                        const response = await fetch('hopmu.php?action=open_bag');

                        if (!response.ok) {
                            throw new Error('Server responded with ' + response.status);
                        }

                        const text = await response.text();
                        let data;
                        try {
                            data = JSON.parse(text);
                        } catch (e) {
                            console.error('Phản hồi không phải JSON:', text);
                            throw new Error('Lỗi dữ liệu từ server');
                        }

                        // Chờ hiệu ứng animation (1s)
                        setTimeout(() => {
                            bag.classList.remove('opening');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '👉 Bóc túi ngay!';

                            if (data.success) {
                                // Cập nhật Số Gtlm
                                balanceEl.textContent = data.newBalance + ' gtlm';

                                // Hiển thị kết quả
                                resultContainer.innerHTML = `
                                    <div class="result ${data.isWin ? 'win' : 'lose'} show">
                                        ${data.message}
                                    </div>
                                `;

                                // Nếu thắng thì bắn pháo giấy
                                if (data.isWin && typeof confetti === 'function') {
                                    confetti({
                                        particleCount: 150,
                                        spread: 100,
                                        origin: { y: 0.6 },
                                        colors: ['#ffd700', '#ffffff', '#ff6b6b', '#4facfe']
                                    });
                                }
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Thông báo',
                                    text: data.message
                                });
                            }
                        }, 1000);

                    } catch (error) {
                        console.error('Lỗi khi mở túi:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Lỗi kết nối',
                            text: 'Không thể kết nối đến máy chủ. Vui lòng thử lại sau.'
                        });
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '👉 Bóc túi ngay!';
                        bag.classList.remove('opening');
                    }
                });
            }
        });
    </script>












