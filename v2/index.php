<?php
/**
 * View V2 - Giao diện React / Vite mới cho GTLM Gaming
 * Tích hợp dữ liệu trực tiếp từ Session & DB MySQL
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db_connect.php';

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
$recentWins = [];
$events = [];
$baseUrl = '/1/'; // Absolute base path

require_once __DIR__ . '/../api_event_helper.php';

// 1. Event center - sự kiện từ bảng events (nếu tồn tại)
try {
    $checkEvt = $conn->query("SHOW TABLES LIKE 'events'");
    if ($checkEvt && $checkEvt->num_rows > 0) {
        $evtRes = $conn->query("SELECT name, description, icon, link FROM events WHERE status = 'active' ORDER BY id DESC LIMIT 4");
        while ($evtRes && $evtRow = $evtRes->fetch_assoc()) {
            $events[] = [
                'icon'       => !empty($evtRow['icon']) ? $evtRow['icon'] : '🎁',
                'title'      => $evtRow['name'],
                'desc'       => !empty($evtRow['description']) ? mb_strimwidth($evtRow['description'], 0, 40, '...') : 'Sự kiện đặc biệt',
                'badge'      => 'HOT',
                'badgeColor' => 'bg-red-500 text-white border-red-400',
                'href'       => !empty($evtRow['link']) ? $baseUrl . ltrim($evtRow['link'], '/') : $baseUrl . 'event_center.php'
            ];
        }
    }
} catch (Throwable $e) {}

// 2. Seasonal Event (nếu chưa có từ bảng events)
if (count($events) === 0) {
    $seasonalEvent = null;
    if (function_exists('getActiveSeasonalEvent')) {
        $seasonalEvent = getActiveSeasonalEvent($conn);
    }
    if ($seasonalEvent) {
        $events[] = [
            'icon'       => '🧧',
            'title'      => $seasonalEvent['name'],
            'desc'       => 'Sự kiện mùa giải đang diễn ra',
            'badge'      => 'MỚI',
            'badgeColor' => 'bg-red-500 text-white border-red-400',
            'href'       => $baseUrl . 'event_center.php'
        ];
    }
}

// 3. World Boss (luôn check riêng)
try {
    $wbRes = $conn->query("SELECT * FROM world_boss WHERE status = 'active' LIMIT 1");
    if ($wbRes && $wbRes->num_rows > 0) {
        $events[] = [
            'icon'       => '🐲',
            'title'      => 'Đại Chiến Ma Thần',
            'desc'       => 'World Boss đang xuất hiện!',
            'badge'      => 'LIVE',
            'badgeColor' => 'bg-purple-600 text-white border-purple-400',
            'href'       => $baseUrl . 'world_boss.php'
        ];
    }
} catch (Throwable $e) {}

// 4. Battle Pass (luôn check riêng)
try {
    $bpRes = $conn->query("SELECT name FROM bp_seasons WHERE is_active = 1 AND NOW() BETWEEN start_time AND end_time LIMIT 1");
    if ($bpRes && $bpRow = $bpRes->fetch_assoc()) {
        $events[] = [
            'icon'       => '🎖️',
            'title'      => 'Battle Pass',
            'desc'       => $bpRow['name'],
            'badge'      => 'HOT',
            'badgeColor' => 'bg-amber-500 text-black border-amber-400',
            'href'       => $baseUrl . 'battle_pass.php'
        ];
    }
} catch (Throwable $e) {}

// 5. Storyline Event (luôn có)
$events[] = [
    'icon'       => '📖',
    'title'      => 'Khai Phá Ký Ức',
    'desc'       => 'Nhiệm vụ cốt truyện hàng ngày',
    'badge'      => 'SỰ KIỆN',
    'badgeColor' => 'bg-violet-500 text-white border-violet-400',
    'href'       => $baseUrl . 'storyline_event.php'
];

// 6. Lucky Wheel (luôn có)
$events[] = [
    'icon'       => '🎡',
    'title'      => 'Vòng Quay May Mắn',
    'desc'       => 'Thử vận may mỗi ngày',
    'badge'      => 'MỚI',
    'badgeColor' => 'bg-blue-500 text-white border-blue-400',
    'href'       => $baseUrl . 'lucky_wheel.php'
];

// Giới hạn tối đa 5 sự kiện
$events = array_slice($events, 0, 5);
try {
    $winRes = $conn->query("SELECT gh.win_amount, gh.game_name, u.Name FROM game_history gh JOIN users u ON gh.user_id = u.Iduser WHERE gh.is_win = 1 ORDER BY gh.id DESC LIMIT 10");
    while ($winRes && $row = $winRes->fetch_assoc()) {
        $tickerItems[] = "🎉 " . $row['Name'] . " vừa thắng " . number_format($row['win_amount']) . " GTLM tại " . $row['game_name'] . "!";
        if (count($recentWins) < 5) {
            $recentWins[] = [
                'name' => $row['Name'],
                'game' => $row['game_name'],
                'amount' => (float)$row['win_amount']
            ];
        }
    }
} catch (Throwable $e) {}

if (empty($tickerItems)) {
    $tickerItems = [
        "🎉 " . $user['Name'] . " vừa đăng nhập GTLM Gaming!",
        "🏆 Jackpot Hũ Rồng hôm nay đạt " . number_format($currentJackpot) . " GTLM!",
        "🔥 Chúc các Đạo Hữu chơi game may mắn và thắng lớn!"
    ];
}
if (empty($recentWins)) {
    $recentWins = [
        ['name' => 'Lan Hương', 'game' => 'Slot Machine', 'amount' => 12000000],
        ['name' => 'Minh Quân', 'game' => 'Crash Flight', 'amount' => 5000000]
    ];
}

$userLevel = max(1, min(100, (int)floor(log10(max(1, $userMoney)) * 3)));
$userXp = 740;
$userReqXp = 1000;
$userStreak = 7;
$userRefCode = "GTLM-" . strtoupper(substr(md5($userId), 0, 6));

$realData = [
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
    'leaderboard' => $leaderboard,
    'events' => $events,
    'wins' => $recentWins
];
$ts = time();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <base href="../">
    <link rel="icon" type="image/x-icon" href="images.ico" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>GTLM Gaming V2 (React/Vite)</title>
    <script>
      window.REAL_DATA = <?= json_encode($realData, JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <link rel="stylesheet" crossorigin href="v2/assets/index-ZOzDEAfS.css?v=<?= $ts ?>">
<script>
window.onerror = function(message, source, lineno, colno, error) {
    const errorDiv = document.createElement('div');
    errorDiv.style.position = 'fixed';
    errorDiv.style.top = '0';
    errorDiv.style.left = '0';
    errorDiv.style.width = '100vw';
    errorDiv.style.height = '100vh';
    errorDiv.style.backgroundColor = 'rgba(0,0,0,0.9)';
    errorDiv.style.color = '#ff4444';
    errorDiv.style.zIndex = '999999';
    errorDiv.style.padding = '20px';
    errorDiv.style.fontFamily = 'monospace';
    errorDiv.style.whiteSpace = 'pre-wrap';
    errorDiv.style.overflow = 'auto';
    errorDiv.innerHTML = `<h1>CRITICAL ERROR</h1>
<p><strong>Message:</strong> ${message}</p>
<p><strong>Source:</strong> ${source}:${lineno}:${colno}</p>
<p><strong>Stack:</strong> ${error ? error.stack : 'N/A'}</p>`;
    document.body.appendChild(errorDiv);
};
</script>
  </head>
  <body>
    <!-- UI Switcher Floating Bar for V2 -->
    <div id="v2-ui-switcher" style="position: fixed; top: 15px; right: 20px; z-index: 99999; display: flex; align-items: center; gap: 8px; background: rgba(15, 23, 42, 0.92); backdrop-filter: blur(15px); padding: 6px 14px; border-radius: 999px; border: 1.5px solid rgba(255, 255, 255, 0.2); box-shadow: 0 10px 25px rgba(0,0,0,0.5); font-family: system-ui, -apple-system, sans-serif;">
      <span style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-right: 4px;">Giao Diện:</span>
      <a href="switch_ui.php?v=v1" title="Về Giao diện V1 (PHP Mặc Định)" style="color: #60a5fa; font-weight: 800; text-decoration: none; padding: 5px 12px; border-radius: 20px; font-size: 12px; background: rgba(96, 165, 250, 0.15); border: 1px solid rgba(96, 165, 250, 0.3); transition: all 0.2s; display: flex; align-items: center; gap: 5px;">🖥️ V1</a>
      <a href="switch_ui.php?v=v2" title="Giao diện V2 (React/Vite) Hiện Tại" style="color: #000; font-weight: 900; text-decoration: none; padding: 5px 12px; border-radius: 20px; font-size: 12px; background: linear-gradient(135deg, #fbbf24, #f59e0b); border: none; box-shadow: 0 2px 10px rgba(245, 158, 11, 0.4); display: flex; align-items: center; gap: 5px;">⚡ V2</a>
      <a href="switch_ui.php?v=v3" title="Chuyển sang Giao diện V3 (Dashboard 3 Cột)" style="color: #4ade80; font-weight: 800; text-decoration: none; padding: 5px 12px; border-radius: 20px; font-size: 12px; background: rgba(74, 222, 128, 0.15); border: 1px solid rgba(74, 222, 128, 0.3); transition: all 0.2s; display: flex; align-items: center; gap: 5px;">✨ V3</a>
    </div>

    <div id="root"></div>

    <script type="module" crossorigin src="v2/assets/index-DP_Ep1RF.js?v=<?= $ts ?>"></script>
  </body>
</html>
