<?php
/**
 * API Plinko Royale V3 Multi-Drop & AI Race
 * [NEW FILE] - Đấu Trường Plinko Thả 100 Bóng & Tranh Top với AI Bot
 * Tuân thủ Rule 2.1 & không đè file cũ
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/game_history_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để vào Đấu Trường Plinko Royale!'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['Iduser'];

// Cấu hình Hệ Số Nhân Plinko Royale V3 (8, 12, 16 Hàng)
$royaleConfig = [
    8 => [
        'low' => [5.6, 2.1, 1.1, 1, 0.5, 1, 1.1, 2.1, 5.6],
        'medium' => [13, 3, 1.3, 0.7, 0.4, 0.7, 1.3, 3, 13],
        'high' => [29, 4, 1.5, 0.3, 0.2, 0.3, 1.5, 4, 29]
    ],
    12 => [
        'low' => [10, 3, 1.6, 1.4, 1.1, 1, 0.5, 1, 1.1, 1.4, 1.6, 3, 10],
        'medium' => [33, 11, 4, 2, 1.1, 0.6, 0.3, 0.6, 1.1, 2, 4, 11, 33],
        'high' => [170, 24, 8.1, 2, 0.7, 0.2, 0.2, 0.2, 0.7, 2, 8.1, 24, 170]
    ],
    16 => [
        'low' => [16, 9, 2, 1.4, 1.4, 1.2, 1.1, 1, 0.5, 1, 1.1, 1.2, 1.4, 1.4, 2, 9, 16],
        'medium' => [110, 41, 10, 5, 3, 1.5, 1, 0.5, 0.3, 0.5, 1, 1.5, 3, 5, 10, 41, 110],
        'high' => [1000, 130, 26, 9, 4, 2, 0.2, 0.2, 0.2, 0.2, 0.2, 2, 4, 9, 26, 130, 1000]
    ]
];

$action = $_GET['action'] ?? '';

// 1. Config
if ($action === 'config') {
    $resU = $conn->query("SELECT Name, Money FROM users WHERE Iduser = {$userId}");
    $uRow = $resU ? $resU->fetch_assoc() : ['Name' => 'Đại Gia', 'Money' => 0];
    
    echo json_encode([
        'success' => true,
        'config' => $royaleConfig,
        'balance' => (float)$uRow['Money'],
        'username' => $uRow['Name']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Drop Balls (Thả Bóng Multi-Drop)
if ($action === 'drop') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid Request Method'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $totalBet = (float)($_POST['bet'] ?? 0);
    $ballCount = (int)($_POST['ballCount'] ?? 1);
    $rows = (int)($_POST['rows'] ?? 8);
    $risk = $_POST['risk'] ?? 'low';

    // Anti-Negative & Overflow checks
    if ($totalBet < 1000) {
        echo json_encode(['success' => false, 'message' => 'Cược tối thiểu 1,000 GTLM mỗi ván thả!'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($ballCount < 1 || $ballCount > 100) {
        echo json_encode(['success' => false, 'message' => 'Số bóng mỗi lần thả từ 1 đến 100 bóng!'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!isset($royaleConfig[$rows][$risk])) {
        echo json_encode(['success' => false, 'message' => 'Cấu hình bàn thả bóng không hợp lệ!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $multipliers = $royaleConfig[$rows][$risk];
    $betPerBall = $totalBet / $ballCount;

    $conn->begin_transaction();
    try {
        // Lock row user
        $stmtLock = $conn->prepare("SELECT Name, Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmtLock->bind_param("i", $userId);
        $stmtLock->execute();
        $uRow = $stmtLock->get_result()->fetch_assoc();
        $stmtLock->close();

        if (!$uRow || $totalBet > $uRow['Money']) {
            throw new Exception("Số dư của bạn không đủ " . number_format($totalBet) . " GTLM!");
        }

        $userName = $uRow['Name'];

        // Trừ GTLM cược
        $conn->query("UPDATE users SET Money = Money - {$totalBet} WHERE Iduser = {$userId}");

        $results = [];
        $totalWin = 0;
        $maxMultHit = 0;
        $jackpotSlot = false;

        for ($b = 0; $b < $ballCount; $b++) {
            $path = [];
            $slot = 0;
            for ($i = 0; $i < $rows; $i++) {
                $dir = rand(0, 1);
                $path[] = $dir;
                if ($dir === 1) {
                    $slot++;
                }
            }
            $mult = $multipliers[$slot];
            if ($mult > $maxMultHit) {
                $maxMultHit = $mult;
            }
            if ($mult >= 100) {
                $jackpotSlot = true;
            }
            $win = round($betPerBall * $mult);
            $totalWin += $win;

            $results[] = [
                'path' => $path,
                'slot' => $slot,
                'multiplier' => $mult,
                'winAmount' => $win
            ];
        }

        // Cộng GTLM thắng
        if ($totalWin > 0) {
            $conn->query("UPDATE users SET Money = Money + {$totalWin} WHERE Iduser = {$userId}");
        }

        // Nếu nổ hũ khủng x100 hoặc x1000 hoặc thắng trên 1 triệu GTLM, thông báo Kênh Chat & Tặng Cúp Biệt Thự!
        $trophyAwarded = false;
        if ($maxMultHit >= 100 || $totalWin >= 1000000) {
            // Ghi tin nhắn vinh danh lên Kênh Chat Thế Giới (chat_messages)
            $msgContent = "🎉 **[PLINKO ROYALE V3]** Đại gia **{$userName}** vừa thả **{$ballCount} bóng** nổ Siêu Hũ **X{$maxMultHit}** húp trọn **" . number_format($totalWin) . " GTLM**! 💥👑";
            $botId = 101; // ID Bot Khách vinh danh
            $stmtChat = $conn->prepare("INSERT INTO chat_messages (user_id, message, created_at) VALUES (?, ?, NOW())");
            if ($stmtChat) {
                $stmtChat->bind_param("is", $botId, $msgContent);
                $stmtChat->execute();
                $stmtChat->close();
            }

            // Kiểm tra tặng Cúp Hoàng Gia vào Biệt Thự (lounge_items)
            $resCheckTrophy = $conn->query("SELECT id FROM lounge_items WHERE user_id = {$userId} AND item_code = 'trophy_plinko_royale'");
            if ($resCheckTrophy && $resCheckTrophy->num_rows === 0) {
                $stmtInsTrophy = $conn->prepare("INSERT INTO lounge_items (user_id, item_code, item_name, item_type, icon_url, is_placed, acquired_from) VALUES (?, 'trophy_plinko_royale', '🏆 Cúp Vàng Plinko Royale X1000', 'trophy', '🏆', 0, 'plinko_royale_v3')");
                if ($stmtInsTrophy) {
                    $stmtInsTrophy->bind_param("i", $userId);
                    $stmtInsTrophy->execute();
                    $stmtInsTrophy->close();
                    $trophyAwarded = true;
                }
            }
        }

        // Ghi lịch sử game universal
        if (function_exists('logGameHistoryWithAll')) {
            logGameHistoryWithAll($conn, $userId, 'Plinko Royale V3', $totalBet, $totalWin, $totalWin > $totalBet);
        } elseif (function_exists('logGameHistory')) {
            logGameHistory($conn, $userId, 'Plinko Royale V3', $totalBet, $totalWin, $totalWin > $totalBet);
        }

        $conn->commit();

        $resBalance = $conn->query("SELECT Money FROM users WHERE Iduser = {$userId}");
        $newBalance = $resBalance ? (float)$resBalance->fetch_assoc()['Money'] : 0;

        echo json_encode([
            'success' => true,
            'results' => $results,
            'totalWin' => $totalWin,
            'newBalance' => $newBalance,
            'maxMultHit' => $maxMultHit,
            'isJackpot' => $jackpotSlot,
            'trophyAwarded' => $trophyAwarded,
            'message' => $jackpotSlot ? "💥 CHÚC MỪNG BẠN VỪA NỔ SIÊU HŨ X{$maxMultHit} ROYALE!" : "Thả bóng thành công!"
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 3. AI Stream (Đua Top Thả Bóng trực tiếp từ AI Bot Army)
if ($action === 'ai_stream') {
    $aiBots = [
        ['name' => '@Whale (Đại Gia Săn Hũ)', 'avt' => 'img/avatar_default.png', 'baseBet' => 500000],
        ['name' => '@Cụ Giáo (Huyền Thoại)', 'avt' => 'img/avatar_default.png', 'baseBet' => 200000],
        ['name' => '@Plinko (Thần Hổ Vàng)', 'avt' => 'img/avatar_default.png', 'baseBet' => 1000000],
        ['name' => '@Tiểu Linh GTLM', 'avt' => 'img/avatar_default.png', 'baseBet' => 50000],
        ['name' => '@Hắc Long Trấn Ải', 'avt' => 'img/avatar_default.png', 'baseBet' => 300000],
        ['name' => '@Thần Bài 100 Tầng', 'avt' => 'img/avatar_default.png', 'baseBet' => 150000]
    ];

    $streamCount = rand(2, 4);
    $drops = [];

    for ($i = 0; $i < $streamCount; $i++) {
        $bot = $aiBots[array_rand($aiBots)];
        $rows = [8, 12, 16][array_rand([0, 1, 2])];
        $risk = ['low', 'medium', 'high'][array_rand([0, 1, 2])];
        $mults = $royaleConfig[$rows][$risk];
        
        // Ngẫu nhiên cho AI lọt lỗ
        $slotIdx = array_rand($mults);
        $multHit = $mults[$slotIdx];
        $bet = $bot['baseBet'] * rand(1, 5);
        $win = round($bet * $multHit);

        $drops[] = [
            'botName' => $bot['name'],
            'avatar' => $bot['avt'],
            'rows' => $rows,
            'risk' => strtoupper($risk),
            'bet' => $bet,
            'multiplier' => $multHit,
            'winAmount' => $win,
            'isJackpot' => ($multHit >= 110)
        ];
    }

    echo json_encode(['success' => true, 'drops' => $drops], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action không hợp lệ!'], JSON_UNESCAPED_UNICODE);
exit;
