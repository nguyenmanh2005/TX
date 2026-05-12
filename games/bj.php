<?php
session_start();

require '../db_connect.php';


// AJAX history endpoint
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_history') {
    header('Content-Type: application/json; charset=utf-8');

    $id = $_SESSION['Iduser'] ?? 0;
    $sql = "SELECT * FROM history_bj WHERE Iduser = ? ORDER BY Time DESC LIMIT 20";
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


if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}

// Load theme
require_once '../load_theme.php';

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

// Hàm rút bài (A=1 hoặc 11 sẽ xử lý ở tính điểm)
function rutBai()
{
    $bai = rand(1, 13);
    // Át = 1, mặt J/Q/K = 10 điểm
    if ($bai > 10)
        return 10;
    return $bai;
}

// Tính điểm bài, xét Át (A) có thể là 1 hoặc 11
function tinhDiem($cards)
{
    if (!is_array($cards)) {
        return 0;
    }

    $total = 0;
    $soAt = 0;
    foreach ($cards as $card) {
        if ($card == 1) {
            $soAt++;
            $total += 11; // tạm tính át = 11
        } else {
            $total += $card;
        }
    }

    // Nếu tổng > 21 và có át, trừ 10 từng át cho đến khi <= 21 hoặc hết át
    while ($total > 21 && $soAt > 0) {
        $total -= 10;
        $soAt--;
    }

    return $total;
}

// Khởi tạo game mới
function khoiTaoGame($cuoc)
{
    $_SESSION['cuoc'] = $cuoc;
    $_SESSION['player_cards'] = [rutBai(), rutBai()];
    $_SESSION['dealer_cards'] = [rutBai(), rutBai()];
    $_SESSION['game_over'] = false;
    $_SESSION['ketqua'] = "";
    $_SESSION['ketquaShort'] = "";
}

// Xử lý action
$action = $_POST['action'] ?? '';
$cuoc = (int) ($_POST['cuoc'] ?? 0);
$ketQuaClass = "";

if ($action === 'start') {
    if ($cuoc <= 0 || $cuoc > $soDu) {
        $_SESSION['ketqua'] = "Số gtlm cược không hợp lệ!";
        $_SESSION['ketquaShort'] = "";
        $ketQuaClass = "bg-yellow-400";
        $_SESSION['game_over'] = true;
    } else {
        khoiTaoGame($cuoc);
    }
} elseif ($action === 'hit' && !($_SESSION['game_over'] ?? true)) {
    $_SESSION['player_cards'][] = rutBai();
    $playerTotal = tinhDiem($_SESSION['player_cards']);
    if ($playerTotal > 21) {
        // Người chơi bust => thua
        $moneyBefore = $soDu;
        $cuoc = $_SESSION['cuoc'];
        $soDu -= $cuoc;
        $_SESSION['game_over'] = true;
        $_SESSION['ketqua'] = "Tham Thì Chết Nhưng Hãy Nhớ Buông Bỏ Không Phải Là Hạnh Phúc";
        $_SESSION['ketquaShort'] = "Thua";
        $ketQuaClass = "bg-red-500 text-white animate-pulse";

        // Cập nhật Số Gtlm
        $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $stmt->bind_param("ii", $soDu, $userId);
        $stmt->execute();
        $stmt->close();

        // Lưu lịch sử
        $dealerTotal = tinhDiem($_SESSION['dealer_cards']);
        $stmt = $conn->prepare("INSERT INTO blackjack_history (Iduser, Result, Bet, PlayerScore, DealerScore, MoneyBefore, MoneyAfter, PlayedAt) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isiiiii", $userId, $_SESSION['ketquaShort'], $cuoc, $playerTotal, $dealerTotal, $moneyBefore, $soDu);
        $stmt->execute();
        $stmt->close();

        // Track quest progress
        require_once '../game_history_helper.php';
        logGameHistory($conn, $userId, 'Blackjack', $cuoc, 0, false);

        // Insert vào history_bj table
        $historyStmt = $conn->prepare("INSERT INTO history_bj (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        if ($historyStmt) {
            $result = $_POST['result'] ?? 'Unknown';
            $bet = (int) ($_POST['bet'] ?? 0);
            $winAmount = (int) ($_POST['win'] ?? $reward ?? 0);
            $userId = $_SESSION['Iduser'] ?? 0;
            $historyStmt->bind_param("iisi", $userId, $bet, $result, $winAmount);
            $historyStmt->execute();
            $historyStmt->close();
        }
    }
} elseif ($action === 'stand' && !($_SESSION['game_over'] ?? true)) {
    // Dealer rút bài
    $dealerCards = $_SESSION['dealer_cards'] ?? [];
    $playerCards = $_SESSION['player_cards'] ?? [];
    $dealerTotal = tinhDiem($dealerCards);
    $playerTotal = tinhDiem($playerCards);

    while ($dealerTotal < 17) {
        $dealerCards[] = rutBai();
        $dealerTotal = tinhDiem($dealerCards);
    }
    $_SESSION['dealer_cards'] = $dealerCards;

    // So điểm
    $_SESSION['game_over'] = true;
    $moneyBefore = $soDu;
    $cuoc = $_SESSION['cuoc'];

    if ($dealerTotal > 21 || $playerTotal > $dealerTotal) {
        $soDu += $cuoc;
        $_SESSION['ketqua'] = "Không Thể Tin Nổi! Nhận " . number_format($cuoc) . " gtlm";
        $_SESSION['ketquaShort'] = "Thắng";
        $ketQuaClass = "bg-green-500 text-white animate-bounce";
    } elseif ($playerTotal == $dealerTotal) {
        $_SESSION['ketqua'] = "Hòa!";
        $_SESSION['ketquaShort'] = "Hòa";
        $ketQuaClass = "bg-yellow-400";
    } else {
        $soDu -= $cuoc;
        $_SESSION['ketqua'] = "Queen GTLM thắng, bạn mất " . number_format($cuoc) . " gtlm";
        $_SESSION['ketquaShort'] = "Thua";
        $ketQuaClass = "bg-red-500 text-white animate-pulse";
    }

    // Cập nhật Số Gtlm
    $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
    $stmt->bind_param("ii", $soDu, $userId);
    $stmt->execute();
    $stmt->close();

    // Lưu lịch sử
    $stmt = $conn->prepare("INSERT INTO blackjack_history (Iduser, Result, Bet, PlayerScore, DealerScore, MoneyBefore, MoneyAfter, PlayedAt) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("isiiiii", $userId, $_SESSION['ketquaShort'], $cuoc, $playerTotal, $dealerTotal, $moneyBefore, $soDu);
    $stmt->execute();
    $stmt->close();

    // Track quest progress
    require_once '../game_history_helper.php';
    $winAmount = 0;
    $isWin = false;
    if ($_SESSION['ketquaShort'] === 'Thắng') {
        $winAmount = $cuoc * 2; // Thắng gấp đôi
        $isWin = true;
    } elseif ($_SESSION['ketquaShort'] === 'Hòa') {
        $winAmount = $cuoc; // Hòa thì hoàn gtlm
    }
    logGameHistory($conn, $userId, 'Blackjack', $cuoc, $winAmount, $isWin);

    // Track tournament progress
    require_once '../tournament_helper.php';
    logTournamentGame($conn, $userId, 'Blackjack', $cuoc, $winAmount, $isWin);
}

// Lấy trạng thái hiện tại
$playerCards = $_SESSION['player_cards'] ?? [];
$dealerCards = $_SESSION['dealer_cards'] ?? [];
$gameOver = $_SESSION['game_over'] ?? true;
$ketQua = $_SESSION['ketqua'] ?? "";

// Clear result message after display
if (!empty($ketQua)) {
    $ketQuaDisplay = $ketQua;
    unset($_SESSION['ketqua'], $_SESSION['ketquaShort']);
} else {
    $ketQuaDisplay = "";
}

$playerTotal = tinhDiem($playerCards);
$dealerTotal = tinhDiem($dealerCards);

// Lấy toàn bộ lịch sử
$lichSu = [];
$stmt = $conn->prepare("SELECT * FROM blackjack_history WHERE Iduser = ? ORDER BY PlayedAt DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $lichSu[] = $row;
}
$stmt->close();

// Chuẩn bị dữ liệu biểu đồ
$thang = $thua = $hoa = 0;
foreach ($lichSu as $lich) {
    if ($lich['Result'] === 'Thắng')
        $thang++;
    elseif ($lich['Result'] === 'Thua')
        $thua++;
    elseif ($lich['Result'] === 'Hòa')
        $hoa++;
}


// AJAX Game Actions handler
if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    // Refresh scores and state after logic above
    $playerCardsObj = [];
    foreach ($playerCards as $card) {
        $playerCardsObj[] = $card == 1 ? 'A' : ($card == 10 ? '10' : (string) $card);
    }

    $dealerCardsObj = [];
    foreach ($dealerCards as $card) {
        $dealerCardsObj[] = $card == 1 ? 'A' : ($card == 10 ? '10' : (string) $card);
    }

    echo json_encode([
        'success' => true,
        'action' => $action,
        'playerCards' => $playerCardsObj,
        'dealerCards' => $dealerCardsObj, // Frontend handles hidden card if !gameOver
        'playerTotal' => $playerTotal,
        'dealerTotal' => $dealerTotal,
        'gameOver' => $gameOver,
        'message' => $ketQuaDisplay,
        'messageClass' => $ketQuaClass,
        'balance' => number_format($soDu, 0, ',', '.'),
        'rawBalance' => $soDu,
        'stats' => [
            'thang' => $thang,
            'thua' => $thua,
            'hoa' => $hoa
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Đóng kết nối
$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8" />
    <title>Blackjack chuẩn</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="../assets/css/loading.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <link rel="stylesheet" href="../assets/css/game-effects.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <style>
        body {
            cursor: url('../chuot.png'), auto !important;
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            position: relative;
        }

        * {
            cursor: inherit;
        }

        button,
        a,
        input[type="button"],
        input[type="submit"],
        label,
        select,
        input[type="number"] {
            cursor: url('../img/tay.png'), pointer !important;
        }

        .card {
            display: inline-block;
            width: 70px;
            height: 100px;
            line-height: 100px;
            border: 3px solid #333;
            border-radius: var(--border-radius);
            margin: 0 10px;
            font-weight: 700;
            font-size: 24px;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            color: #000;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease;
            animation: cardDeal 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s;
        }

        .card:hover::before {
            left: 100%;
        }

        @keyframes cardDeal {
            0% {
                opacity: 0;
                transform: translateY(-50px) rotateY(-180deg) scale(0.5);
            }

            50% {
                transform: translateY(10px) rotateY(0deg) scale(1.1);
            }

            100% {
                opacity: 1;
                transform: translateY(0) rotateY(0deg) scale(1);
            }
        }

        .card.new-card {
            animation: newCardDeal 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        @keyframes newCardDeal {
            0% {
                opacity: 0;
                transform: translateX(-100px) rotateZ(-90deg) scale(0.3);
            }

            50% {
                transform: translateX(10px) rotateZ(10deg) scale(1.1);
            }

            100% {
                opacity: 1;
                transform: translateX(0) rotateZ(0deg) scale(1);
            }
        }

        .card:hover {
            transform: translateY(-8px) scale(1.05);
            box-shadow: 0 10px 30px rgba(52, 152, 219, 0.5);
            border-color: var(--secondary-color);
        }

        .card.hidden {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: #fff;
            position: relative;
        }

        .card.hidden::after {
            content: '?';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 40px;
            color: #fff;
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

        button {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2) !important;
            font-weight: 600 !important;
            border-radius: var(--border-radius) !important;
        }

        button:hover:not(:disabled) {
            transform: translateY(-4px) scale(1.05) !important;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.35) !important;
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed !important;
        }

        .bg-green-500 {
            animation: winPulse 1.5s ease infinite;
            box-shadow: 0 0 30px rgba(34, 197, 94, 0.6) !important;
        }

        .bg-red-500 {
            animation: loseShake 0.8s ease;
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.5) !important;
        }

        .bg-yellow-400 {
            animation: drawPulse 1s ease infinite;
        }

        @keyframes winPulse {

            0%,
            100% {
                transform: scale(1);
                box-shadow: 0 0 30px rgba(34, 197, 94, 0.6);
            }

            50% {
                transform: scale(1.03);
                box-shadow: 0 0 50px rgba(34, 197, 94, 0.9);
            }
        }

        @keyframes drawPulse {

            0%,
            100% {
                transform: scale(1);
                box-shadow: 0 0 20px rgba(250, 204, 21, 0.5);
            }

            50% {
                transform: scale(1.02);
                box-shadow: 0 0 30px rgba(250, 204, 21, 0.7);
            }
        }

        @keyframes loseShake {

            0%,
            100% {
                transform: translateX(0) rotate(0deg);
            }

            10%,
            30%,
            50%,
            70%,
            90% {
                transform: translateX(-10px) rotate(-5deg);
            }

            20%,
            40%,
            60%,
            80% {
                transform: translateX(10px) rotate(5deg);
            }
        }

        input[type="number"] {
            padding: 12px 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--border-radius);
            background: rgba(255, 255, 255, 0.95);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            font-size: 16px;
        }

        input[type="number"]:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.2);
        }

        h1,
        h2,
        h3 {
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
            margin: 15px 0;
        }

        @keyframes messageAppear {
            0% {
                opacity: 0;
                transform: translateY(-30px) scale(0.8);
            }

            50% {
                transform: translateY(5px) scale(1.05);
            }

            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .chart-box canvas {
            margin-top: 20px;
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

<body class="py-10" style="background: <?= $bgGradientCSS ?>; background-attachment: fixed; min-height: 100vh;">

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
                                <th class="border border-gray-300 px-3 py-1">Điểm bạn</th>
                                <th class="border border-gray-300 px-3 py-1">Điểm dealer</th>
                                <th class="border border-gray-300 px-3 py-1">Số Gtlm trước</th>
                                <th class="border border-gray-300 px-3 py-1">Số Gtlm sau</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($lichSu) === 0): ?>
                                <tr>
                                    <td colspan="7" class="text-center p-4">Chưa có lịch sử chơi</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lichSu as $lich): ?>
                                    <tr>
                                        <td class="border border-gray-300 px-3 py-1"><?= htmlspecialchars($lich['PlayedAt']) ?>
                                        </td>
                                        <td class="border border-gray-300 px-3 py-1"><?= htmlspecialchars($lich['Result']) ?>
                                        </td>
                                        <td class="border border-gray-300 px-3 py-1"><?= number_format($lich['Bet']) ?></td>
                                        <td class="border border-gray-300 px-3 py-1"><?= $lich['PlayerScore'] ?></td>
                                        <td class="border border-gray-300 px-3 py-1"><?= $lich['DealerScore'] ?></td>
                                        <td class="border border-gray-300 px-3 py-1"><?= number_format($lich['MoneyBefore']) ?>
                                        </td>
                                        <td class="border border-gray-300 px-3 py-1"><?= number_format($lich['MoneyAfter']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Blackjack Game -->
            <div class="bg-white p-8 rounded-xl shadow-xl lg:w-1/3">
                <h1 class="text-3xl font-bold mb-6 text-center">Bờ Lách Jack</h1>

                <h2 class="text-xl mb-2 text-center">Xin chào,
                    <strong><?= htmlspecialchars($tenNguoiChoi, ENT_QUOTES, 'UTF-8') ?></strong>
                </h2>
                <div class="balance-display text-center">💰 Số Gtlm: <strong
                        id="balance-amount"><?= number_format($soDu, 0, ',', '.') ?> gtlm</strong></div>

                <div id="game-container">
                    <!-- Form bắt đầu ván mới -->
                    <div id="start-form-container"
                        class="<?= (!isset($_SESSION['player_cards']) || $gameOver) ? '' : 'hidden' ?>">
                        <form id="start-form" method="POST" class="max-w-md mx-auto mb-6">
                            <input type="number" name="cuoc" placeholder="Nhập số gtlm cược"
                                class="w-full px-4 py-2 border rounded mb-3" required min="1" max="<?= $soDu ?>">
                            <input type="hidden" name="action" value="start">
                            <button type="submit"
                                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 w-full">Bắt đầu ván
                                mới</button>
                        </form>
                    </div>

                    <!-- Thông báo kết quả -->
                    <div id="result-container" class="mb-6 <?= !empty($ketQuaDisplay) ? '' : 'hidden' ?>">
                        <div id="result-message-box" class="p-6 rounded-lg text-white <?= $ketQuaClass ?>"
                            style="animation: messageAppear 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);">
                            <p id="result-text" class="text-2xl font-bold mb-2 text-center">
                                <?= htmlspecialchars($ketQuaDisplay, ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
                    </div>

                    <!-- Khu vực chơi bài -->
                    <div id="play-area" class="<?= isset($_SESSION['player_cards']) ? '' : 'hidden' ?>">
                        <!-- Bài người chơi -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-3">🃏 Bài của bạn (<span
                                    id="player-score"><?= $playerTotal ?></span> điểm):</h3>
                            <div id="player-cards" class="flex flex-wrap justify-center gap-2">
                                <?php foreach ($playerCards as $index => $card): ?>
                                    <div
                                        class="card <?= ($index >= count($playerCards) - 1 && !$gameOver) ? 'new-card' : '' ?>">
                                        <?= $card == 1 ? 'A' : ($card == 10 ? '10' : $card) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Bài Queen GTLM -->
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-3">🎰 Bài Queen GTLM (<span
                                    id="dealer-score"><?= $gameOver ? $dealerTotal : '?' ?></span> điểm):</h3>
                            <div id="dealer-cards" class="flex flex-wrap justify-center gap-2">
                                <?php if (!$gameOver && isset($dealerCards[0])): ?>
                                    <div class="card">
                                        <?= $dealerCards[0] == 1 ? 'A' : ($dealerCards[0] == 10 ? '10' : $dealerCards[0]) ?>
                                    </div>
                                    <div class="card hidden"></div>
                                <?php else: ?>
                                    <?php foreach ($dealerCards as $card): ?>
                                        <div class="card">
                                            <?= $card == 1 ? 'A' : ($card == 10 ? '10' : $card) ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Các nút hành động -->
                        <div id="action-buttons" class="flex justify-center gap-3 <?= $gameOver ? 'hidden' : '' ?>">
                            <form class="game-action-form" method="POST">
                                <input type="hidden" name="action" value="hit">
                                <button type="submit"
                                    class="bg-green-600 text-white px-5 py-2 rounded hover:bg-green-700">Hit (Rút
                                    thêm)</button>
                            </form>
                            <form class="game-action-form" method="POST">
                                <input type="hidden" name="action" value="stand">
                                <button type="submit"
                                    class="bg-red-600 text-white px-5 py-2 rounded hover:bg-red-700">Stand
                                    (Dừng)</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Nút quay lại trang chủ -->
                <div class="mt-6 text-center">
                    <a href="../index.php"
                        class="inline-block bg-gray-700 text-white px-6 py-2 rounded hover:bg-black transition">⬅️ Quay
                        lại Trang Chủ</a>
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
        document.addEventListener('DOMContentLoaded', function () {
            document.body.style.cursor = "url('../chuot.png'), auto";

            const interactiveElements = document.querySelectorAll('button, a, input, label, select');
            interactiveElements.forEach(el => {
                el.style.cursor = "url('../img/tay.png'), pointer";
            });

            // Thêm animation cho card mới khi rút bài
            const cards = document.querySelectorAll('.card.new-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.animationDelay = (index * 0.1) + 's';
                }, 100);
            });
        });

        const ctx = document.getElementById('chartKetQua').getContext('2d');
        window.gameChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Thắng', 'Thua', 'Hòa'],
                datasets: [{
                    label: 'Số trận',
                    data: [<?= $thang ?>, <?= $thua ?>, <?= $hoa ?>],
                    backgroundColor: ['#22c55e', '#ef4444', '#facc15'],
                    borderColor: ['#16a34a', '#b91c1c', '#eab308'],
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
        (function () {
            // Pass theme config từ PHP sang JavaScript
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

            // Load Three.js background script
            const script = document.createElement('script');
            script.src = '../threejs-background.js';
            script.onload = function () {
                console.log('Three.js background loaded');
            };
            document.head.appendChild(script);
        })();
    </script>



    <script src="../assets/js/game-effects.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="../assets/js/game-effects-auto.js"></script>

    <script src="../assets/js/game-enhancements.js"></script>
    <script>
        // AJAX Game Logic
        $(document).ready(function () {
            function updateUI(data) {
                if (!data.success) {
                    Swal.fire('Lỗi', data.message || 'Có lỗi xảy ra', 'error');
                    return;
                }

                // Update balance
                $('#balance-amount').text(data.balance + ' gtlm');

                // Update messages
                if (data.message) {
                    $('#result-text').text(data.message);
                    $('#result-message-box').attr('class', 'p-6 rounded-lg text-white ' + data.messageClass);
                    $('#result-container').removeClass('hidden');
                } else {
                    $('#result-container').addClass('hidden');
                }

                // Update scores
                $('#player-score').text(data.playerTotal);
                $('#dealer-score').text(data.gameOver ? data.dealerTotal : '?');

                // Update cards
                renderCards($('#player-cards'), data.playerCards, !data.gameOver);
                renderCards($('#dealer-cards'), data.dealerCards, false, !data.gameOver);

                // Update stats and chart
                if (data.stats && window.gameChart) {
                    window.gameChart.data.datasets[0].data = [data.stats.thang, data.stats.thua, data.stats.hoa];
                    window.gameChart.update();
                }

                // Show/Hide containers
                if (data.gameOver) {
                    $('#start-form-container').removeClass('hidden');
                    $('#action-buttons').addClass('hidden');
                    refreshHistory();
                } else {
                    $('#start-form-container').addClass('hidden');
                    $('#action-buttons').removeClass('hidden');
                    $('#play-area').removeClass('hidden');
                }
            }

            function renderCards(container, cards, animateLast, isDealerHidden = false) {
                const oldCardCount = container.find('.card').length;
                container.empty();
                cards.forEach((card, index) => {
                    if (isDealerHidden && index === 1) {
                        container.append('<div class="card hidden"></div>');
                    } else {
                        const isNew = animateLast && index >= oldCardCount;
                        container.append(`<div class="card ${isNew ? 'new-card' : ''}">${card}</div>`);
                    }
                });
            }

            function refreshHistory() {
                $.get('bj.php?action=get_history', function (data) {
                    if (data.success && data.history.length > 0) {
                        const tbody = $('table tbody');
                        tbody.empty();
                        data.history.forEach(item => {
                            const bet = Number(item.Bet).toLocaleString('vi-VN');
                            const mBefore = Number(item.MoneyBefore).toLocaleString('vi-VN');
                            const mAfter = Number(item.MoneyAfter).toLocaleString('vi-VN');
                            tbody.append(`
                                <tr>
                                    <td class="border border-gray-300 px-3 py-1">${item.PlayedAt}</td>
                                    <td class="border border-gray-300 px-3 py-1">${item.Result}</td>
                                    <td class="border border-gray-300 px-3 py-1">${bet}</td>
                                    <td class="border border-gray-300 px-3 py-1">${item.PlayerScore}</td>
                                    <td class="border border-gray-300 px-3 py-1">${item.DealerScore}</td>
                                    <td class="border border-gray-300 px-3 py-1">${mBefore}</td>
                                    <td class="border border-gray-300 px-3 py-1">${mAfter}</td>
                                </tr>
                            `);
                        });
                    }
                });
            }

            $(document).on('submit', '#start-form, .game-action-form', function (e) {
                e.preventDefault();
                const form = $(this);
                const formData = form.serialize();

                $.ajax({
                    url: 'bj.php',
                    type: 'POST',
                    data: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    success: function (response) {
                        updateUI(response);
                    },
                    error: function () {
                        Swal.fire('Lỗi', 'Không thể kết nối đến máy chủ', 'error');
                    }
                });
            });
        });

        // Auto initialize game effects
        if (typeof GameEffectsAuto !== 'undefined') {
            GameEffectsAuto.init();
        }
    </script>











    <!-- Premium Effects System -->
    <canvas id="threejs-background"></canvas>
    <script>
        (function () {
            window.themeConfig = {
                particleCount: <?= $particleCount ?? 800 ?>,
                particleSize: <?= $particleSize ?? 0.05 ?>,
                particleColor: '<?= $particleColor ?? "#ffffff" ?>',
                particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
                shapeCount: <?= $shapeCount ?? 10 ?>,
                shapeColors: <?= json_encode($shapeColors ?? ["#667eea", "#764ba2", "#4facfe", "#00f2fe"]) ?>,
                shapeOpacity: <?= $shapeOpacity ?? 0.3 ?>,
                bgGradient: <?= json_encode($bgGradient ?? ["#667eea", "#764ba2", "#4facfe"]) ?>
            };
            const prefix = window.location.pathname.includes('/games/') ? '../' : '';
            const scripts = ['threejs-background.js', 'assets/js/game-effects.js', 'assets/js/game-effects-auto.js'];

            scripts.forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src;
                s.async = false;
                document.head.appendChild(s);
            });
        })();
    

    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for bj game
    const ctxBj = document.getElementById('gameChart');
    if (ctxBj) {
        const gameChart = new Chart(ctxBj.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadBjHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for bj game
    const ctxBj = document.getElementById('gameChart');
    if (ctxBj) {
        const gameChart = new Chart(ctxBj.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadBjHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for bj game
    const ctxBj = document.getElementById('gameChart');
    if (ctxBj) {
        const gameChart = new Chart(ctxBj.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadBjHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for bj game
    const ctxBj = document.getElementById('gameChart');
    if (ctxBj) {
        const gameChart = new Chart(ctxBj.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadBjHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for bj game
    const ctxBj = document.getElementById('gameChart');
    if (ctxBj) {
        const gameChart = new Chart(ctxBj.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadBjHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for bj game
    const ctxBj = document.getElementById('gameChart');
    if (ctxBj) {
        const gameChart = new Chart(ctxBj.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadBjHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for bj game
    const ctxBj = document.getElementById('gameChart');
    if (ctxBj) {
        const gameChart = new Chart(ctxBj.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadBjHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for bj game
    const ctxBj = document.getElementById('gameChart');
    if (ctxBj) {
        const gameChart = new Chart(ctxBj.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadBjHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for bj game
    const ctxBj = document.getElementById('gameChart');
    if (ctxBj) {
        const gameChart = new Chart(ctxBj.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadBjHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for bj game
    const ctxBj = document.getElementById('gameChart');
    if (ctxBj) {
        const gameChart = new Chart(ctxBj.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadBjHistory);



    // Improved history loading function
    
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for bj game
    const ctxBj = document.getElementById('gameChart');
    if (ctxBj) {
        const gameChart = new Chart(ctxBj.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadBjHistory);



    // Improved history loading function
    async function loadBjHistory() {
        try {
            const response = await fetch('bj.php?action=get_history', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">${record.Id}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;">${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                ${record.Result || '-'}
                            </td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: ${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};">${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;">${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for bj game
    const ctxBj = document.getElementById('gameChart');
    if (ctxBj) {
        const gameChart = new Chart(ctxBj.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= $gameThang ?>, <?= $gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', loadBjHistory);

</script>













<div class="bottom-section">
    <div class="history-box">
        <h3>📋 Lịch sử chơi (10 lần gần nhất)</h3>
        <table border="1" cellpadding="10" id="historyTable">
            <thead>
                <tr style="background: rgba(255, 255, 255, 0.1);">
                    <th style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;">ID</th>
                    <th style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;">Cược</th>
                    <th style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;">Kết quả</th>
                    <th style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;">Thắng</th>
                    <th style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;">Thời gian</th>
                </tr>
            </thead>
            <tbody id="historyBody">
                <tr><td colspan="5" style="text-align: center; padding: 15px; color: #aaa;">Chưa có lượt chơi nào</td></tr>
            </tbody>
        </table>
    </div>
    
    <div class="chart-box">
        <h3>📊 Thống kê</h3>
        <div class="stats-container">
            <div class="stat-item wins">
                <div class="label">Lần Thắng</div>
                <div class="value"><?= $gameThang ?></div>
            </div>
            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

            <div class="stat-item losses">
                <div class="label">Lần Thua</div>
                <div class="value"><?= $gameThua ?></div>
            </div>
        </div>
        <canvas id="gameChart" style="max-height: 300px;"></canvas>
    </div>
</div>

</body>

</html>