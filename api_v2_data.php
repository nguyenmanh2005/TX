<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db_connect.php';

$userId = isset($_SESSION['Iduser']) ? (int)$_SESSION['Iduser'] : 1;

// Lấy thông tin user hiện tại
$user = null;
try {
    $userStmt = $conn->prepare("SELECT Iduser, Name, Money, ImageURL, Role FROM users WHERE Iduser = ?");
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();
} catch (Throwable $e) {}

if (!$user) {
    $user = ['Iduser' => 1, 'Name' => 'Đạo Hữu', 'Money' => 0, 'ImageURL' => 'images.ico', 'Role' => 0];
}

$userMoney = (float)$user['Money'];

// Tính hạng người chơi
$userRank = 1;
try {
    $rankRes = $conn->query("SELECT COUNT(*) + 1 as rank FROM users WHERE Money > " . $userMoney);
    if ($rankRes && $row = $rankRes->fetch_assoc()) {
        $userRank = (int)$row['rank'];
    }
} catch (Throwable $e) {}

// Lấy Jackpot hiện tại
$currentJackpot = 100000000;
try {
    $jpRes = $conn->query("SELECT jackpot_pool FROM lottery_draws WHERE status = 'pending' ORDER BY id ASC LIMIT 1");
    if ($jpRes && $row = $jpRes->fetch_assoc()) {
        $currentJackpot = (float)$row['jackpot_pool'];
    }
} catch (Throwable $e) {}

// Lấy Bảng xếp hạng thật
$leaderboard = [];
try {
    $topRes = $conn->query("SELECT Iduser, Name, Money, ImageURL FROM users WHERE Email NOT REGEXP '^bot[0-9]+@' ORDER BY Money DESC LIMIT 8");
    $r = 1;
    while ($topRes && $row = $topRes->fetch_assoc()) {
        $leaderboard[] = [
            'rank' => $r++,
            'name' => $row['Name'],
            'money' => (float)$row['Money'],
            'avatar' => !empty($row['ImageURL']) ? $row['ImageURL'] : 'images.ico',
            'isVip' => ((float)$row['Money'] >= 10000000),
            'isMe' => ((int)$row['Iduser'] === $userId)
        ];
    }
} catch (Throwable $e) {}

// Lấy thống kê số ván thắng từ DB
$totalGames = 0;
$winRate = 50;
$totalEarned = 0;
try {
    $statsRes = $conn->query("SELECT COUNT(*) as total_games, SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END) as wins, SUM(win_amount) as total_earned FROM game_history WHERE user_id = $userId");
    if ($statsRes && $row = $statsRes->fetch_assoc()) {
        $totalGames = (int)($row['total_games'] ?? 0);
        $wins = (int)($row['wins'] ?? 0);
        $totalEarned = (float)($row['total_earned'] ?? 0);
        $winRate = $totalGames > 0 ? round(($wins / $totalGames) * 100) : 50;
    }
} catch (Throwable $e) {}

// Lấy tin chạy Ticker thực từ game_history
$tickerItems = [];
try {
    $winRes = $conn->query("SELECT gh.win_amount, gh.game_name, u.Name FROM game_history gh JOIN users u ON gh.user_id = u.Iduser WHERE gh.is_win = 1 ORDER BY gh.id DESC LIMIT 10");
    while ($winRes && $row = $winRes->fetch_assoc()) {
        $tickerItems[] = "🎉 " . $row['Name'] . " vừa thắng " . number_format($row['win_amount']) . " GTLM tại " . $row['game_name'] . "!";
    }
} catch (Throwable $e) {}

if (empty($tickerItems)) {
    $tickerItems = [
        "🎉 " . $user['Name'] . " vừa đăng nhập GTLM Gaming!",
        "🏆 Jackpot Hũ Rồng hôm nay đạt " . number_format($currentJackpot) . " GTLM!",
        "🔥 Chúc các Đạo Hữu chơi game may mắn và thắng lớn!"
    ];
}

$userLevel = max(1, min(100, (int)floor(log10(max(1, $userMoney)) * 3)));
$userXp = 740;
$userReqXp = 1000;
$userStreak = 7;
$userRefCode = "GTLM-" . strtoupper(substr(md5($userId), 0, 6));

echo json_encode([
    'user' => [
        'id' => (int)$user['Iduser'],
        'name' => $user['Name'],
        'money' => $userMoney,
        'money_formatted' => number_format($userMoney),
        'avatar' => !empty($user['ImageURL']) ? $user['ImageURL'] : 'images.ico',
        'rank' => $userRank,
        'level' => $userLevel,
        'xp' => $userXp,
        'requiredXp' => $userReqXp,
        'loginStreak' => $userStreak,
        'isVip' => ($userMoney >= 10000000)
    ],
    'jackpot' => $currentJackpot,
    'jackpot_formatted' => number_format($currentJackpot),
    'stats' => [
        'money' => $userMoney,
        'level' => $userLevel,
        'xp' => $userXp,
        'requiredXp' => $userReqXp,
        'loginStreak' => $userStreak,
        'bestStreak' => 14,
        'rank' => $userRank,
        'totalGames' => $totalGames,
        'winRate' => $winRate,
        'totalEarned' => $totalEarned,
        'achievements' => 18,
        'referralCode' => $userRefCode
    ],
    'ticker' => $tickerItems,
    'leaderboard' => $leaderboard
], JSON_UNESCAPED_UNICODE);
?>
