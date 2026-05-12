<?php
session_start();

require '../db_connect.php';

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
        $_SESSION['ketqua'] = "Số Gtlm cược không hợp lệ!";
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

        // Cập nhật số dư
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

        // Track quest progress và tự động cập nhật streak, VIP, reward points, social feed
        require_once '../game_history_helper.php';
        logGameHistoryWithAll($conn, $userId, 'Blackjack', $cuoc, 0, false);
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
        $_SESSION['ketqua'] = "Không Thể Tin Nổi! Nhận " . number_format($cuoc) . " VNĐ";
        $_SESSION['ketquaShort'] = "Thắng";
        $ketQuaClass = "bg-green-500 text-white animate-bounce";
    } elseif ($playerTotal == $dealerTotal) {
        $_SESSION['ketqua'] = "Hòa!";
        $_SESSION['ketquaShort'] = "Hòa";
        $ketQuaClass = "bg-yellow-400";
    } else {
        $soDu -= $cuoc;
        $_SESSION['ketqua'] = "Nhà cái thắng, bạn mất " . number_format($cuoc) . " VNĐ";
        $_SESSION['ketquaShort'] = "Thua";
        $ketQuaClass = "bg-red-500 text-white animate-pulse";
    }

    // Cập nhật số dư
    $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
    $stmt->bind_param("ii", $soDu, $userId);
    $stmt->execute();
    $stmt->close();

    // Lưu lịch sử
    $stmt = $conn->prepare("INSERT INTO blackjack_history (Iduser, Result, Bet, PlayerScore, DealerScore, MoneyBefore, MoneyAfter, PlayedAt) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("isiiiii", $userId, $_SESSION['ketquaShort'], $cuoc, $playerTotal, $dealerTotal, $moneyBefore, $soDu);
    $stmt->execute();
    $stmt->close();

    // Track quest progress và tự động cập nhật streak, VIP, reward points, social feed
    require_once '../game_history_helper.php';
    $winAmount = 0;
    $isWin = false;
    if ($_SESSION['ketquaShort'] === 'Thắng') {
        $winAmount = $cuoc * 2; // Thắng gấp đôi
        $isWin = true;
    } elseif ($_SESSION['ketquaShort'] === 'Hòa') {
        $winAmount = $cuoc; // Hòa thì hoàn Gtlm
    }
    logGameHistoryWithAll($conn, $userId, 'Blackjack', $cuoc, $winAmount, $isWin);

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
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
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
    </style>
</head>

<body class="py-10" style="background: <?= $bgGradientCSS ?>; background-attachment: fixed; min-height: 100vh;">
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
                                <th class="border border-gray-300 px-3 py-1">Điểm bạn</th>
                                <th class="border border-gray-300 px-3 py-1">Điểm dealer</th>
                                <th class="border border-gray-300 px-3 py-1">Số dư trước</th>
                                <th class="border border-gray-300 px-3 py-1">Số dư sau</th>
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
                    <strong><?= htmlspecialchars($tenNguoiChoi, ENT_QUOTES, 'UTF-8') ?></strong></h2>
                <div class="balance-display text-center">💰 Số dư: <strong><?= number_format($soDu, 0, ',', '.') ?>
                        VNĐ</strong></div>

                <?php if (!isset($_SESSION['player_cards']) || $gameOver): ?>
                    <!-- Form bắt đầu ván mới -->
                    <form method="POST" class="max-w-md mx-auto mb-6">
                        <input type="number" name="cuoc" placeholder="Nhập số Gtlm cược"
                            class="w-full px-4 py-2 border rounded mb-3" required min="1" max="<?= $soDu ?>">
                        <input type="hidden" name="action" value="start">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 w-full">Bắt
                            đầu ván mới</button>
                    </form>
                <?php endif; ?>

                <?php if (!empty($ketQuaDisplay)): ?>
                    <div class="mb-6 p-6 rounded-lg text-white <?= $ketQuaClass ?>"
                        style="animation: messageAppear 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);">
                        <p class="text-2xl font-bold mb-2 text-center">
                            <?= htmlspecialchars($ketQuaDisplay, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['player_cards']) && !$gameOver): ?>
                    <!-- Hiển thị bài -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-3">🃏 Bài của bạn (<?= $playerTotal ?> điểm):</h3>
                        <div class="flex flex-wrap justify-center gap-2">
                            <?php foreach ($playerCards as $index => $card): ?>
                                <div class="card <?= $index >= count($playerCards) - 1 ? 'new-card' : '' ?>">
                                    <?= $card == 1 ? 'A' : ($card == 10 ? '10' : $card) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-3">🎰 Bài Nhà Cái:</h3>
                        <div class="flex flex-wrap justify-center gap-2">
                            <div class="card">
                                <?= $dealerCards[0] == 1 ? 'A' : ($dealerCards[0] == 10 ? '10' : $dealerCards[0]) ?></div>
                            <div class="card hidden"></div>
                        </div>
                    </div>

                    <!-- Nút Hit / Stand -->
                    <div class="flex justify-center gap-3">
                        <form method="POST">
                            <input type="hidden" name="action" value="hit">
                            <button type="submit" class="bg-green-600 text-white px-5 py-2 rounded hover:bg-green-700">Hit
                                (Rút thêm)</button>
                        </form>
                        <form method="POST">
                            <input type="hidden" name="action" value="stand">
                            <button type="submit" class="bg-red-600 text-white px-5 py-2 rounded hover:bg-red-700">Stand
                                (Dừng)</button>
                        </form>
                    </div>
                <?php elseif (isset($_SESSION['player_cards']) && $gameOver): ?>
                    <!-- Hiển thị bài cuối cùng khi game kết thúc -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-3">🃏 Bài của bạn (<?= $playerTotal ?> điểm):</h3>
                        <div class="flex flex-wrap justify-center gap-2">
                            <?php foreach ($playerCards as $card): ?>
                                <div class="card">
                                    <?= $card == 1 ? 'A' : ($card == 10 ? '10' : $card) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-3">🎰 Bài Nhà Cái (<?= $dealerTotal ?> điểm):</h3>
                        <div class="flex flex-wrap justify-center gap-2">
                            <?php foreach ($dealerCards as $card): ?>
                                <div class="card">
                                    <?= $card == 1 ? 'A' : ($card == 10 ? '10' : $card) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>


                <?php endif; ?>

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
                el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
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
        new Chart(ctx, {
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
        // Auto initialize game effects
        if (typeof GameEffectsAuto !== 'undefined') {
            GameEffectsAuto.init();
        }
    </script>
</body>

</html>