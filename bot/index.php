<?php
session_start();
if ((!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) && (!isset($_SESSION['Role']) || $_SESSION['Role'] != 1)) {
    header('Location: ../login.php');
    exit;
}
require_once '../db_connect.php';
$config = require 'config.php';

$syncFile = 'sessions/bot_sync.json';
$historyFile = 'sessions/economy_history.json';
$syncData = file_exists($syncFile) ? json_decode(file_get_contents($syncFile), true) : [];
$history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];

// 1. Logic Reset State
if (isset($_GET['action']) && $_GET['action'] === 'reset_state' && isset($_GET['bot_id'])) {
    $botId = (int)$_GET['bot_id'];
    $botRes = $conn->query("SELECT Email FROM users WHERE Iduser = $botId")->fetch_assoc();
    if ($botRes) {
        $botMd5 = md5($botRes['Email']);
        @unlink("sessions/$botMd5.state.json");
        @unlink("sessions/$botMd5.txt");
        header("Location: index.php?msg=" . urlencode("Reset thành công bot #$botId"));
        exit;
    }
}

// 1.2 Handle Random Event Trigger / Switch
if (isset($_GET['action']) && ($_GET['action'] === 'trigger_event' || $_GET['action'] === 'switch_event')) {
    define('ALLOW_EVENT_WEB', true);
    $_GET['force'] = 1;
    require_once '../cron_random_events.php';
    header("Location: index.php?msg=" . urlencode("Đã bắt đầu sự kiện mới thành công!"));
    exit;
}

// 1.3 Handle Spawn Bots
if (isset($_GET['action']) && $_GET['action'] === 'spawn') {
    $count = isset($_GET['count']) ? max(1, (int)$_GET['count']) : 1;
    $count = min($count, 50); // Giới hạn 50 bot/lần
    
    // Lấy số lớn nhất hiện tại 1 lần duy nhất
    $maxRes = $conn->query("SELECT MAX(CAST(SUBSTRING(Email, 4, LOCATE('@', Email) - 4) AS UNSIGNED)) as max_num FROM users WHERE Email REGEXP '^bot[0-9]+@'");
    $currentMax = (int)($maxRes->fetch_assoc()['max_num'] ?? 0);
    
    $env = file_exists(__DIR__ . '/../.env.php') ? require __DIR__ . '/../.env.php' : [];
    $passText = $env['BOT_PASSWORD'] ?? '123456';
    $passHash = password_hash($passText, PASSWORD_DEFAULT);
    
    $values = [];
    $params = [];
    $types = "";
    $spawned = 0;
    
    for ($i = 1; $i <= $count; $i++) {
        $nextNumber = $currentMax + $i;
        $newName = "Bot " . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
        $email = "bot" . $nextNumber . "@gmail.com";
        $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($newName) . "&background=random";
        
        $values[] = "(?, ?, ?, 1000000, ?)";
        $params[] = $newName;
        $params[] = $email;
        $params[] = $passHash;
        $params[] = $avatarUrl;
        $types .= "ssss";
        $spawned++;
    }
    
    if (!empty($values)) {
        $sql = "INSERT INTO users (Name, Email, Pass, Money, ImageURL) VALUES " . implode(", ", $values);
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
    }
    
    $msg = "Đã sinh thành công $spawned bot siêu tốc.";
    header("Location: index.php?msg=" . urlencode($msg));
    exit;
}

$sessionFiles = glob('sessions/*.state.json');

// 2. Phân tích trạng thái bot từ Files
$botStates = [];
foreach ($sessionFiles as $file) {
    $emailMd5 = str_replace(['sessions/', '.state.json'], '', $file);
    $botStates[$emailMd5] = json_decode(file_get_contents($file), true);
}

// 3. Phân tích Log để tìm API Fail
$apiFailures = [];
$logFile = 'logs/' . date('Y-m-d') . '.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    preg_match_all('/\[ERROR\] \[(.*?)\] (.*?): (.*)/', $logContent, $matches);
    if (!empty($matches[2])) {
        foreach ($matches[2] as $index => $source) {
            $label = (strpos($source, 'http') === 0) ? parse_url($source, PHP_URL_PATH) : $source;
            $label = str_ireplace('config.php', '[HIDDEN]', $label);
            $apiFailures[$label] = ($apiFailures[$label] ?? 0) + 1;
        }
    }
    arsort($apiFailures);
}

$stats = [
    'total' => count($sessionFiles),
    'active' => 0,
    'moods' => ['happy' => 0, 'excited' => 0, 'tilted' => 0, 'depressed' => 0],
    'total_bot_money' => 0,
    'total_human_money' => 0,
    'top_bots_today' => [],
    'active_event' => null
];

// Lấy event đang chạy
$activeEv = $conn->query("SELECT event_name, description, ends_at FROM random_events WHERE is_active = 1 AND ends_at > NOW() LIMIT 1")->fetch_assoc();
$stats['active_event'] = $activeEv;

// Lấy Top Bot thắng nhiều nhất hôm nay
$today = date('Y-m-d');
$sqlTop = "SELECT u.Name, u.ImageURL, SUM(gh.win_amount - gh.bet_amount) as total_profit
           FROM users u
           JOIN game_history gh ON u.Iduser = gh.user_id
           WHERE gh.played_at >= '$today 00:00:00' AND u.Email REGEXP '^bot[0-9]+@'
           GROUP BY u.Iduser
           ORDER BY total_profit DESC
           LIMIT 5";
$topRes = $conn->query($sqlTop);
if ($topRes) {
    while($topRow = $topRes->fetch_assoc()) {
        $stats['top_bots_today'][] = $topRow;
    }
}

// 4. Lấy dữ liệu Bot từ Database (Join Frames)
$botsList = [];
$sql = "SELECT u.Iduser, u.Name, u.Email, u.Money, u.ImageURL, 
               cf.frame_name as chat_frame_name, 
               af.frame_name as avatar_frame_name,
               ach.name as title_name
        FROM users u 
        LEFT JOIN chat_frames cf ON u.chat_frame_id = cf.id 
        LEFT JOIN avatar_frames af ON u.avatar_frame_id = af.id
        LEFT JOIN achievements ach ON u.active_title_id = ach.id
        WHERE u.Email REGEXP '^bot[0-9]+@' 
        ORDER BY u.Iduser ASC";
$res = $conn->query($sql);

while($row = $res->fetch_assoc()) {
    $emailMd5 = md5($row['Email']);
    $state = $botStates[$emailMd5] ?? ['wins'=>0, 'mood'=>'happy'];
    
    // Thống kê nhanh
    $stats['total_bot_money'] += $row['Money'];
    $currentMood = $state['mood'] ?? 'happy';
    if (isset($stats['moods'][$currentMood])) $stats['moods'][$currentMood]++;
    
    $row['state'] = $state;
    $row['state']['mood'] = $currentMood;
    $botsList[] = $row;
}

// Lấy tổng GTLM người thật
$humanRes = $conn->query("SELECT SUM(Money) as total FROM users WHERE Email NOT REGEXP '^bot[0-9]+@'")->fetch_assoc();
$stats['total_human_money'] = (float)($humanRes['total'] ?? 0);

// 5. Kiểm tra sức khỏe Bot
require_once 'bot_health.php';
$healthSummary = getBotHealthSummary($conn, $config, $botStates);

// Lọc dữ liệu biểu đồ theo Range
$range = $_GET['range'] ?? '1d';
$filteredHistory = [];
$now = time();

foreach ($history as $point) {
    $pTime = isset($point['full_date']) ? strtotime($point['full_date']) : strtotime(str_replace('/', '-', $point['time'] . '/' . date('Y')));
    
    $include = false;
    if ($range === '1d' && ($now - $pTime) <= 86400) $include = true;
    else if ($range === '7d' && ($now - $pTime) <= 86400 * 7) $include = true;
    else if ($range === '30d' && ($now - $pTime) <= 86400 * 30) $include = true;
    else if ($range === 'all') $include = true;

    if ($include) {
        $filteredHistory[] = $point;
    }
}

// Downsampling cho các range dài để tránh lag biểu đồ
if (count($filteredHistory) > 100) {
    $step = ceil(count($filteredHistory) / 50);
    $downsampled = [];
    for ($i = 0; $i < count($filteredHistory); $i += $step) {
        $downsampled[] = $filteredHistory[$i];
    }
    $filteredHistory = $downsampled;
}

$chartLabels = []; $chartBotData = []; $chartHumanData = [];
$moodHistory = ['happy' => [], 'excited' => [], 'tilted' => [], 'depressed' => []];

foreach ($filteredHistory as $point) {
    $chartLabels[] = $point['time'];
    $chartBotData[] = $point['bot'];
    $chartHumanData[] = $point['human'];
    
    if (isset($point['moods'])) {
        foreach ($moodHistory as $m => &$vals) {
            $vals[] = $point['moods'][$m] ?? 0;
        }
    }
}

// Lấy 20 dòng log mới nhất
$logFile = 'logs/' . date('Y-m-d') . '.log';
$recentLogs = [];
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recentLogs = array_slice($lines, -15);
    $recentLogs = array_reverse($recentLogs);
}

// Kiểm tra xem có phải request AJAX không
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'stats' => $stats,
        'chartLabels' => $chartLabels,
        'chartBotData' => $chartBotData,
        'chartHumanData' => $chartHumanData,
        'moodHistory' => $moodHistory,
        'recentLogs' => $recentLogs,
        'botsList' => $botsList,
        'healthSummary' => $healthSummary
    ]);
    exit;
}

$inflation = ($stats['total_bot_money'] / ($stats['total_human_money'] ?: 1)) * 100;

// Tính toán tâm trạng chủ đạo động
$moodMeta = [
    'excited' => [
        'name' => 'Hưng Phấn',
        'color' => 'var(--warn)',
        'hex' => '#f59e0b',
        'icon' => 'fa-bolt'
    ],
    'happy' => [
        'name' => 'Hạnh Phúc',
        'color' => 'var(--success)',
        'hex' => '#10b981',
        'icon' => 'fa-face-smile'
    ],
    'tilted' => [
        'name' => 'Cay Cú',
        'color' => 'var(--danger)',
        'hex' => '#ef4444',
        'icon' => 'fa-fire-flame-curved'
    ],
    'depressed' => [
        'name' => 'Thất Vọng',
        'color' => '#94a3b8',
        'hex' => '#94a3b8',
        'icon' => 'fa-face-sad-tear'
    ]
];

$dominantMoodKey = 'happy';
$maxMoodCount = -1;
foreach ($stats['moods'] as $mKey => $mCount) {
    if ($mCount > $maxMoodCount) {
        $maxMoodCount = $mCount;
        $dominantMoodKey = $mKey;
    }
}
$dominantMood = $moodMeta[$dominantMoodKey] ?? $moodMeta['happy'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛡️ Bot Army Control Center | GTLM Admin Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg-body: #070a13;
            --bg-card: rgba(15, 23, 42, 0.75);
            --bg-card-hover: rgba(30, 41, 59, 0.85);
            --border-glass: rgba(255, 255, 255, 0.08);
            --border-focus: rgba(139, 92, 246, 0.4);

            --primary: #6366f1;
            --secondary: #8b5cf6;
            --success: #10b981;
            --danger: #ef4444;
            --warn: #f59e0b;
            --cyan: #06b6d4;

            --text-heading: #ffffff;
            --text-body: #cbd5e1;
            --text-muted: #94a3b8;
            --text-dim: #64748b;

            --radius-md: 14px;
            --radius-lg: 20px;
            --radius-xl: 26px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-body);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            padding: 24px 28px 80px 28px;
            background-image: 
                radial-gradient(circle at 10% 15%, rgba(99, 102, 241, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 90% 75%, rgba(139, 92, 246, 0.08) 0%, transparent 40%);
            background-attachment: fixed;
            line-height: 1.5;
        }

        h1, h2, h3, h4, .brand-font {
            font-family: 'Outfit', sans-serif;
            color: var(--text-heading);
            letter-spacing: -0.02em;
        }

        /* ── Modern Scrollbars ── */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.2); }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.25); }

        .dashboard-container {
            max-width: 1600px;
            margin: 0 auto;
        }

        /* ── Top Header Navigation ── */
        .admin-header {
            background: var(--bg-card);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-xl);
            padding: 18px 24px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .header-brand-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: #fff;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header-title-wrap h1 {
            font-size: 1.35rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header-badge-live {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
            font-size: 0.72rem;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 6px;
            letter-spacing: 0.04em;
        }

        .header-sub-status {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 2px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-dot-pulse {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--success);
            box-shadow: 0 0 8px var(--success);
            animation: pulse-dot 1.5s infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.85); }
        }

        .header-toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-toolbar {
            padding: 9px 16px;
            border-radius: 12px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid var(--border-glass);
            background: rgba(255, 255, 255, 0.04);
            color: var(--text-body);
        }

        .btn-toolbar:hover {
            background: rgba(255, 255, 255, 0.09);
            color: #fff;
            transform: translateY(-2px);
        }

        .btn-toolbar-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
        }

        .btn-toolbar-primary:hover {
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
            color: #fff;
        }

        .btn-toolbar-warn {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(234, 88, 12, 0.25));
            color: var(--warn);
            border: 1px solid rgba(245, 158, 11, 0.4);
        }

        .btn-toolbar-warn:hover {
            background: var(--warn);
            color: #000;
        }

        /* ── Alert Banner ── */
        .alert-box {
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--success);
            padding: 12px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            font-weight: 700;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ── KPI Metrics Cards ── */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }

        .kpi-card {
            background: var(--bg-card);
            backdrop-filter: blur(14px);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 20px;
            position: relative;
            overflow: hidden;
            transition: all 0.25s ease;
        }

        .kpi-card:hover {
            transform: translateY(-3px);
            border-color: var(--border-focus);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }

        .kpi-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .kpi-title {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }

        .kpi-icon-wrap {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .kpi-value {
            font-size: 1.7rem;
            font-weight: 900;
            color: #fff;
            line-height: 1.2;
            font-family: 'Outfit', sans-serif;
        }

        .kpi-subtitle {
            font-size: 0.75rem;
            color: var(--text-dim);
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* ── Main Layout: Analytics & Intelligence ── */
        .dashboard-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 22px;
            margin-bottom: 30px;
        }

        .panel-box {
            background: var(--bg-card);
            backdrop-filter: blur(14px);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 22px;
            margin-bottom: 22px;
            position: relative;
            transition: border-color 0.2s;
        }

        .panel-box:hover {
            border-color: rgba(255, 255, 255, 0.15);
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            gap: 12px;
        }

        .panel-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Range Selector Buttons */
        .range-picker {
            display: flex;
            gap: 4px;
            background: rgba(0, 0, 0, 0.3);
            padding: 3px;
            border-radius: 10px;
            border: 1px solid var(--border-glass);
        }

        .range-picker-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 7px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .range-picker-btn:hover {
            color: #fff;
        }

        .range-picker-btn.active {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.4);
        }

        /* Chart Canvas Wrappers */
        .chart-box-large {
            height: 300px;
            position: relative;
        }

        .chart-box-medium {
            height: 240px;
            position: relative;
        }

        /* Terminal Logs Console */
        .terminal-console {
            height: 240px;
            overflow-y: auto;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.78rem;
            background: rgba(0, 0, 0, 0.45);
            padding: 14px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-glass);
            line-height: 1.6;
        }

        .terminal-line {
            padding: 3px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            color: #94a3b8;
            word-break: break-all;
        }

        .terminal-line:last-child {
            border-bottom: none;
        }

        /* ── Right Column Cards ── */
        .event-feature-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.12), rgba(139, 92, 246, 0.15));
            border: 1px solid rgba(139, 92, 246, 0.35);
            border-radius: var(--radius-md);
            padding: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
        }

        .op-bot-podium {
            text-align: center;
            padding: 14px 10px;
        }

        .op-avatar-wrap {
            position: relative;
            display: inline-block;
            margin-bottom: 12px;
        }

        .op-avatar-img {
            width: 84px;
            height: 84px;
            border-radius: 22px;
            border: 3px solid var(--warn);
            box-shadow: 0 0 25px rgba(245, 158, 11, 0.35);
            object-fit: cover;
        }

        .op-crown-badge {
            position: absolute;
            bottom: -6px;
            right: -6px;
            background: var(--warn);
            color: #000;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 0.85rem;
            border: 3px solid var(--bg-body);
        }

        /* Health Metric Box */
        .health-stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            text-align: center;
            margin-bottom: 14px;
            background: rgba(0, 0, 0, 0.25);
            padding: 10px;
            border-radius: 12px;
            border: 1px solid var(--border-glass);
        }

        .health-stat-col:not(:last-child) {
            border-right: 1px solid var(--border-glass);
        }

        .health-stat-num {
            font-size: 1.25rem;
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
        }

        .health-stat-label {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* ── Bot Army Detailed Grid View ── */
        .army-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin: 20px 0 16px 0;
            flex-wrap: wrap;
        }

        .army-search-input {
            width: 320px;
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            padding: 10px 16px 10px 38px;
            font-size: 0.88rem;
            color: #fff;
            outline: none;
            transition: all 0.2s;
            font-family: inherit;
        }

        .army-search-input:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
        }

        .army-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
            max-height: 850px;
            overflow-y: auto;
            padding: 4px;
        }

        .bot-card {
            background: var(--bg-card);
            backdrop-filter: blur(14px);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 16px;
            transition: all 0.25s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .bot-card:hover {
            transform: translateY(-4px);
            background: var(--bg-card-hover);
            border-color: rgba(139, 92, 246, 0.4);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }

        .bot-card-top {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .bot-avatar-thumb {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.12);
            flex-shrink: 0;
        }

        .bot-card-info {
            flex: 1;
            min-width: 0;
        }

        .bot-card-name {
            font-size: 0.95rem;
            font-weight: 800;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .bot-card-id {
            font-size: 0.72rem;
            color: var(--text-dim);
            font-weight: 600;
        }

        .mood-chip {
            font-size: 0.68rem;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .mood-happy     { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .mood-excited   { background: rgba(245, 158, 11, 0.2); color: var(--warn); }
        .mood-tilted    { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
        .mood-depressed { background: rgba(100, 116, 139, 0.2); color: #cbd5e1; }

        .bot-card-metrics {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(0, 0, 0, 0.25);
            padding: 8px 10px;
            border-radius: 10px;
            border: 1px solid var(--border-glass);
            font-size: 0.8rem;
        }

        .bot-money {
            font-weight: 800;
            color: var(--warn);
        }

        .bot-wins {
            font-weight: 700;
            color: var(--primary);
        }

        .bot-card-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
        }

        .btn-card-action {
            flex: 1;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            border: 1px solid var(--border-glass);
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        .btn-inspect {
            background: rgba(99, 102, 241, 0.15);
            color: #a5b4fc;
            border-color: rgba(99, 102, 241, 0.3);
        }

        .btn-inspect:hover {
            background: var(--primary);
            color: #fff;
        }

        .btn-reset {
            background: rgba(239, 68, 68, 0.12);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.3);
        }

        .btn-reset:hover {
            background: var(--danger);
            color: #fff;
        }

        /* ── Modal Soi Kho Đồ Bot ── */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.82);
            z-index: 9999;
            backdrop-filter: blur(12px);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-dialog {
            background: #0d1222;
            width: 100%;
            max-width: 680px;
            max-height: 85vh;
            border-radius: var(--radius-xl);
            border: 1px solid rgba(139, 92, 246, 0.4);
            padding: 28px;
            position: relative;
            overflow-y: auto;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
        }

        .modal-close-btn {
            position: absolute;
            top: 20px; right: 20px;
            background: rgba(255, 255, 255, 0.05);
            border: none;
            color: var(--text-muted);
            width: 32px; height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .modal-close-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
        }

        /* ── Responsive ── */
        @media (max-width: 1100px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            body { padding: 14px 14px 60px 14px; }
            .admin-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 16px;
            }
            .header-toolbar {
                width: 100%;
            }
            .btn-toolbar {
                flex: 1;
                justify-content: center;
            }
            .army-search-input {
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <div class="dashboard-container">

        <!-- ── Top Header Navigation ── -->
        <header class="admin-header">
            <div class="header-brand">
                <div class="header-brand-icon">
                    <i class="fa fa-shield-halved"></i>
                </div>
                <div class="header-title-wrap">
                    <h1>
                        BOT ARMY CONTROL CENTER
                        <span class="header-badge-live">v16.5</span>
                    </h1>
                    <div class="header-sub-status">
                        <span class="status-dot-pulse"></span>
                        <span>Đang giám sát <b><?= $stats['total'] ?></b> thành viên Thiên Thần & Ác Quỷ</span>
                    </div>
                </div>
            </div>

            <div class="header-toolbar">
                <a href="../v3/index.php" class="btn-toolbar" title="Sảnh Game Chính">
                    <i class="fa fa-house"></i> <span>Sảnh Game</span>
                </a>
                <a href="../games/megaspin.php" class="btn-toolbar" title="Vòng Quay Mega Spin">
                    <i class="fa fa-dharmachakra"></i> <span>Mega Spin</span>
                </a>
                <a href="bot_buff.php" class="btn-toolbar" title="Buff Tiền GTLM">
                    <i class="fa fa-coins"></i> <span>Buff GTLM</span>
                </a>
                <a href="bot_runner.php" class="btn-toolbar btn-toolbar-primary" title="Trình Điều Khiển Web">
                    <i class="fa fa-rocket"></i> <span>Runner Web</span>
                </a>
                <button onclick="spawnBotsPrompt()" class="btn-toolbar btn-toolbar-warn" title="Sinh Bot Tự Động">
                    <i class="fa fa-plus"></i> <span>Sinh Bot</span>
                </button>
            </div>
        </header>

        <!-- ── Flash Message Alert ── -->
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert-box" id="flashAlert">
                <span><i class="fa fa-check-circle" style="margin-right: 6px;"></i> <?= htmlspecialchars($_GET['msg']) ?></span>
                <button onclick="document.getElementById('flashAlert').style.display='none'" style="background:none; border:none; color:var(--success); font-size:18px; cursor:pointer;">&times;</button>
            </div>
            <script>
                if (window.history.replaceState) {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('msg');
                    window.history.replaceState({path: url.href}, '', url.href);
                }
            </script>
        <?php endif; ?>

        <!-- ── Top KPI Stat Cards ── -->
        <section class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title">Quân Đoàn Bot</span>
                    <div class="kpi-icon-wrap" style="color: var(--primary);">
                        <i class="fa fa-robot"></i>
                    </div>
                </div>
                <div class="kpi-value"><?= $stats['total'] ?></div>
                <div class="kpi-subtitle">
                    <span style="color: var(--success); font-weight: 700;">● <?= $healthSummary['healthy'] ?> Khỏe mạnh</span>
                    <span>• <?= $healthSummary['warning'] + $healthSummary['critical'] ?> Cảnh báo</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title">Tổng Tài Sản Bot</span>
                    <div class="kpi-icon-wrap" style="color: var(--warn);">
                        <i class="fa fa-vault"></i>
                    </div>
                </div>
                <div class="kpi-value" style="color: var(--warn);"><?= number_format($stats['total_bot_money']) ?> <span style="font-size: 1rem; color: var(--text-muted);">GTLM</span></div>
                <div class="kpi-subtitle">
                    <span>Người thật: <?= number_format($stats['total_human_money']) ?> GTLM</span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title">Tỉ Lệ Lạm Phát</span>
                    <div class="kpi-icon-wrap" style="color: <?= $inflation > 100 ? 'var(--danger)' : 'var(--success)' ?>;">
                        <i class="fa fa-chart-line"></i>
                    </div>
                </div>
                <div class="kpi-value" style="color: <?= $inflation > 100 ? 'var(--danger)' : 'var(--success)' ?>;">
                    <?= number_format($inflation, 1) ?>%
                </div>
                <div class="kpi-subtitle">
                    <span style="color: <?= $inflation > 100 ? 'var(--danger)' : 'var(--success)' ?>; font-weight: 700;">
                        <?= $inflation > 100 ? '⚠️ Cảnh báo lạm phát cao' : '✅ Cân bằng ổn định' ?>
                    </span>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title">Tâm Trạng Chủ Đạo</span>
                    <div class="kpi-icon-wrap" id="dominantMoodIconWrap" style="color: <?= $dominantMood['color'] ?>;">
                        <i id="dominantMoodIcon" class="fa <?= $dominantMood['icon'] ?>"></i>
                    </div>
                </div>
                <div class="kpi-value" id="dominantMoodValue" style="color: <?= $dominantMood['color'] ?>;">
                    <?= $dominantMood['name'] ?>
                </div>
                <div class="kpi-subtitle" id="dominantMoodSubtitle">
                    <span>🟢 <?= $stats['moods']['happy'] ?> • 🟡 <?= $stats['moods']['excited'] ?> • 🔴 <?= $stats['moods']['tilted'] ?><?= !empty($stats['moods']['depressed']) ? ' • ⚪ ' . $stats['moods']['depressed'] : '' ?></span>
                </div>
            </div>
        </section>

        <!-- ── Main Dashboard: 2-Column Analytics ── -->
        <div class="dashboard-layout">

            <!-- ── Left Column: Charts & Terminal Logs ── -->
            <div class="left-col">

                <!-- Chart 1: Wealth Economics -->
                <div class="panel-box">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="fa fa-chart-area" style="color: var(--primary);"></i>
                            <span>Biến Động Kinh Tế Quân Đoàn</span>
                        </div>
                        <div class="range-picker">
                            <button onclick="setRange('1d')" class="range-picker-btn active" id="btn-1d">1N</button>
                            <button onclick="setRange('7d')" class="range-picker-btn" id="btn-7d">7N</button>
                            <button onclick="setRange('30d')" class="range-picker-btn" id="btn-30d">1T</button>
                            <button onclick="setRange('all')" class="range-picker-btn" id="btn-all">Tất cả</button>
                        </div>
                    </div>
                    <div class="chart-box-large">
                        <canvas id="mainChart"></canvas>
                    </div>
                </div>

                <!-- Chart 2: Mood Dynamics -->
                <div class="panel-box">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="fa fa-masks-theater" style="color: var(--secondary);"></i>
                            <span>Biến Động Tâm Trạng Theo Thời Gian</span>
                        </div>
                    </div>
                    <div class="chart-box-medium">
                        <canvas id="moodLineChart"></canvas>
                    </div>
                </div>

                <!-- Console: Live Terminal Logs -->
                <div class="panel-box">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="fa fa-terminal" style="color: var(--cyan);"></i>
                            <span>Nhật Ký Hoạt Động (Live Logs)</span>
                        </div>
                        <button onclick="clearLogs()" style="background: rgba(239, 68, 68, 0.12); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.3); padding: 4px 10px; border-radius: 8px; font-size: 0.72rem; font-weight: 800; cursor: pointer; transition: all 0.2s;">
                            <i class="fa fa-trash"></i> Clear Logs
                        </button>
                    </div>
                    <div id="liveLogs" class="terminal-console">
                        <?php foreach ($recentLogs as $log): ?>
                            <div class="terminal-line">
                                <?= str_ireplace('config.php', '[REDACTED]', htmlspecialchars($log)) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

            <!-- ── Right Column: Random Event, OP Bot, Health ── -->
            <div class="right-col">

                <!-- 1. Random Event Featured Card -->
                <div class="panel-box" style="border-color: rgba(99, 102, 241, 0.35);">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="fa fa-dice" style="color: var(--primary);"></i>
                            <span>Sự Kiện Ngẫu Nhiên</span>
                        </div>
                    </div>

                    <div id="activeEventAdmin">
                        <?php if ($stats['active_event']): ?>
                            <div class="event-feature-card">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                                    <div style="font-weight: 800; font-size: 0.95rem; color: #fff; margin-bottom: 4px;"><?= $stats['active_event']['event_name'] ?></div>
                                    <span style="font-size: 0.65rem; background: rgba(16, 185, 129, 0.25); color: var(--success); padding: 2px 7px; border-radius: 6px; font-weight: 800; white-space: nowrap;">ĐANG CHẠY</span>
                                </div>
                                <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 12px; line-height: 1.4;"><?= $stats['active_event']['description'] ?></div>
                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--warn);"><i class="fa fa-clock"></i> Kết thúc: <?= date('H:i:s', strtotime($stats['active_event']['ends_at'])) ?></div>
                                    <a href="index.php?action=switch_event" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; text-decoration: none; padding: 6px 12px; border-radius: 8px; font-size: 0.75rem; font-weight: 800; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35); transition: transform 0.15s;" onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform='scale(1)'">
                                        <i class="fa fa-dice"></i> Bắt đầu sự kiện khác
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 18px 10px;">
                                <div style="font-size: 0.8rem; color: var(--text-dim); margin-bottom: 12px;">Không có sự kiện nào đang diễn ra.</div>
                                <a href="index.php?action=trigger_event" class="btn-toolbar btn-toolbar-primary" style="display: inline-flex;">
                                    <i class="fa fa-bolt"></i> ⚡ Kích hoạt ngay
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. OP Bot Podium -->
                <div class="panel-box" style="border-color: rgba(245, 158, 11, 0.35); background: rgba(245, 158, 11, 0.03);">
                    <div class="panel-header">
                        <div class="panel-title" style="color: var(--warn);">
                            <i class="fa fa-crown"></i>
                            <span>OP BOT HÔM NAY</span>
                        </div>
                        <span style="font-size: 0.65rem; background: var(--warn); color: #000; padding: 2px 6px; border-radius: 4px; font-weight: 900;">TOP PROFIT</span>
                    </div>

                    <div id="opBotSection">
                        <?php if (!empty($stats['top_bots_today'])): 
                            $opBot = $stats['top_bots_today'][0]; ?>
                            <div class="op-bot-podium">
                                <div class="op-avatar-wrap">
                                    <img src="<?= $opBot['ImageURL'] ?>" alt="OP Bot" class="op-avatar-img">
                                    <div class="op-crown-badge">1</div>
                                </div>
                                <h3 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 2px;"><?= $opBot['Name'] ?></h3>
                                <div style="color: var(--success); font-weight: 800; font-size: 1.1rem;">+<?= number_format($opBot['total_profit']) ?> GTLM</div>
                                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 4px;">Húp mạnh nhất quân đoàn hôm nay! 🚀</div>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 25px; color: var(--text-dim); font-size: 0.8rem;">Đang tìm kiếm OP Bot...</div>
                        <?php endif; ?>
                    </div>

                    <!-- Runner-ups (Rank 2-5) -->
                    <div id="topBotsToday" style="margin-top: 14px; border-top: 1px solid var(--border-glass); padding-top: 12px;">
                        <?php foreach (array_slice($stats['top_bots_today'], 1) as $index => $bot): ?>
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; padding: 8px 10px; background: rgba(255,255,255,0.02); border-radius: 10px; border: 1px solid var(--border-glass);">
                                <span style="font-weight: 900; color: var(--text-dim); font-size: 0.8rem; width: 18px;">#<?= $index+2 ?></span>
                                <img src="<?= $bot['ImageURL'] ?>" style="width: 30px; height: 30px; border-radius: 8px; object-fit: cover;">
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-size: 0.82rem; font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= $bot['Name'] ?></div>
                                    <div style="font-size: 0.72rem; color: var(--success); font-weight: 800;">+<?= number_format($bot['total_profit']) ?> GTLM</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 3. Health & API Failures -->
                <div class="panel-box">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="fa fa-heart-pulse" style="color: var(--success);"></i>
                            <span>Sức Khỏe Quân Đoàn</span>
                        </div>
                    </div>

                    <div id="healthSummarySection">
                        <div class="health-stats-row">
                            <div class="health-stat-col">
                                <div class="health-stat-num" style="color: var(--success);"><?= $healthSummary['healthy'] ?></div>
                                <div class="health-stat-label">Khỏe mạnh</div>
                            </div>
                            <div class="health-stat-col">
                                <div class="health-stat-num" style="color: var(--warn);"><?= $healthSummary['warning'] ?></div>
                                <div class="health-stat-label">Cảnh báo</div>
                            </div>
                            <div class="health-stat-col">
                                <div class="health-stat-num" style="color: var(--danger);"><?= $healthSummary['critical'] ?></div>
                                <div class="health-stat-label">Lỗi nặng</div>
                            </div>
                        </div>

                        <div id="healthDetailsList" style="max-height: 140px; overflow-y: auto;">
                            <?php if (empty($healthSummary['details'])): ?>
                                <div style="font-size: 0.75rem; color: var(--text-dim); text-align: center; padding: 10px; border: 1px dashed var(--border-glass); border-radius: 10px;">
                                    Tất cả bot đều đang hoạt động tốt. ✨
                                </div>
                            <?php else: ?>
                                <?php foreach ($healthSummary['details'] as $detail): ?>
                                    <div style="font-size: 0.72rem; padding: 6px 10px; background: rgba(255,255,255,0.02); border-radius: 8px; margin-bottom: 5px; border-left: 3px solid <?= $detail['status'] == 'critical' ? 'var(--danger)' : 'var(--warn)' ?>;">
                                        <div style="font-weight: 800; color: #fff;"><?= $detail['email'] ?></div>
                                        <div style="color: var(--text-muted);"><?= implode(', ', $detail['issues']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 4. Mini Economy & Mood Doughnuts -->
                <div class="panel-box" style="overflow: hidden;">
                    <div class="panel-header" style="margin-bottom: 14px;">
                        <div class="panel-title">
                            <i class="fa fa-pie-chart" style="color: var(--cyan);"></i>
                            <span>Cán Cân & Tâm Trạng Hiện Tại</span>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 12px; align-items: start;">
                        <!-- Column 1: Tiền Tệ -->
                        <div style="min-width: 0; display: flex; flex-direction: column; align-items: center; width: 100%;">
                            <div style="text-align: center; font-size: 0.76rem; color: var(--text-muted); font-weight: 700; margin-bottom: 6px;">Tiền Tệ</div>
                            <div style="position: relative; width: 100%; height: 115px; max-width: 130px; margin: 0 auto;">
                                <canvas id="economyChart"></canvas>
                            </div>
                            <div id="ecoCustomLegend" style="margin-top: 10px; display: flex; flex-direction: column; gap: 4px; width: 100%; font-size: 0.7rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(99, 102, 241, 0.08); padding: 4px 7px; border-radius: 6px; border: 1px solid rgba(99, 102, 241, 0.2);">
                                    <span style="color: #a5b4fc; display: flex; align-items: center; gap: 5px;">
                                        <span style="width: 7px; height: 7px; border-radius: 50%; background: #6366f1; display: inline-block;"></span> Bot
                                    </span>
                                    <span id="ecoBotPct" style="font-weight: 800; color: #fff; font-family: 'JetBrains Mono', monospace; font-size: 0.68rem;">...</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(16, 185, 129, 0.08); padding: 4px 7px; border-radius: 6px; border: 1px solid rgba(16, 185, 129, 0.2);">
                                    <span style="color: #6ee7b7; display: flex; align-items: center; gap: 5px;">
                                        <span style="width: 7px; height: 7px; border-radius: 50%; background: #10b981; display: inline-block;"></span> Người thật
                                    </span>
                                    <span id="ecoHumanPct" style="font-weight: 800; color: #fff; font-family: 'JetBrains Mono', monospace; font-size: 0.68rem;">...</span>
                                </div>
                            </div>
                        </div>

                        <!-- Column 2: Cảm Xúc -->
                        <div style="min-width: 0; display: flex; flex-direction: column; align-items: center; width: 100%;">
                            <div style="text-align: center; font-size: 0.76rem; color: var(--text-muted); font-weight: 700; margin-bottom: 6px;">Cảm Xúc</div>
                            <div style="position: relative; width: 100%; height: 115px; max-width: 130px; margin: 0 auto;">
                                <canvas id="moodChart"></canvas>
                            </div>
                            <div id="moodCustomLegend" style="margin-top: 10px; display: grid; grid-template-columns: 1fr 1fr; gap: 4px; width: 100%; font-size: 0.68rem;">
                                <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(245, 158, 11, 0.08); padding: 4px 5px; border-radius: 6px; border: 1px solid rgba(245, 158, 11, 0.2);" title="Hưng phấn">
                                    <span style="color: #fcd34d; display: flex; align-items: center; gap: 3px;">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #f59e0b; display: inline-block;"></span> Phấn
                                    </span>
                                    <b id="moodLegendExcited" style="color: #fff; font-family: 'JetBrains Mono', monospace;"><?= $stats['moods']['excited'] ?></b>
                                </div>
                                <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(239, 68, 68, 0.08); padding: 4px 5px; border-radius: 6px; border: 1px solid rgba(239, 68, 68, 0.2);" title="Cay cú">
                                    <span style="color: #fca5a5; display: flex; align-items: center; gap: 3px;">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #ef4444; display: inline-block;"></span> Cay
                                    </span>
                                    <b id="moodLegendTilted" style="color: #fff; font-family: 'JetBrains Mono', monospace;"><?= $stats['moods']['tilted'] ?></b>
                                </div>
                                <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(16, 185, 129, 0.08); padding: 4px 5px; border-radius: 6px; border: 1px solid rgba(16, 185, 129, 0.2);" title="Hạnh phúc">
                                    <span style="color: #6ee7b7; display: flex; align-items: center; gap: 3px;">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981; display: inline-block;"></span> Phúc
                                    </span>
                                    <b id="moodLegendHappy" style="color: #fff; font-family: 'JetBrains Mono', monospace;"><?= $stats['moods']['happy'] ?></b>
                                </div>
                                <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(148, 163, 184, 0.08); padding: 4px 5px; border-radius: 6px; border: 1px solid rgba(148, 163, 184, 0.2);" title="Thất vọng">
                                    <span style="color: #cbd5e1; display: flex; align-items: center; gap: 3px;">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #64748b; display: inline-block;"></span> Nản
                                    </span>
                                    <b id="moodLegendDepressed" style="color: #fff; font-family: 'JetBrains Mono', monospace;"><?= $stats['moods']['depressed'] ?></b>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <!-- ── Detailed Bot Army Grid Section ── -->
        <section>
            <div class="army-section-header">
                <div>
                    <h2 style="font-size: 1.25rem; font-weight: 800;">
                        📂 DANH SÁCH QUÂN ĐOÀN CHI TIẾT
                    </h2>
                    <div style="font-size: 0.78rem; color: var(--text-muted);">
                        Quản lý toàn bộ <?= count($botsList) ?> thành viên tự động
                    </div>
                </div>

                <div style="position: relative;">
                    <i class="fa fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-dim); font-size: 0.85rem;"></i>
                    <input type="text" id="searchBotInput" class="army-search-input" placeholder="Tìm tên hoặc ID bot... (Nhấn / để tìm)">
                </div>
            </div>

            <div class="army-grid" id="botGrid">
                <?php foreach ($botsList as $bot): ?>
                    <div class="bot-card" data-name="<?= strtolower($bot['Name']) ?>" data-id="<?= $bot['Iduser'] ?>">
                        <div class="bot-card-top">
                            <img src="<?= $bot['ImageURL'] ?>" class="bot-avatar-thumb" alt="<?= $bot['Name'] ?>" onerror="this.src='../img/avatar_default.png'">
                            <div class="bot-card-info">
                                <div class="bot-card-name"><?= $bot['Name'] ?></div>
                                <div class="bot-card-id">ID: #<?= $bot['Iduser'] ?></div>
                            </div>
                            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                                <span class="mood-chip mood-<?= $bot['state']['mood'] ?? 'happy' ?>">
                                    <?= $bot['state']['mood'] ?? 'happy' ?>
                                </span>
                                <?php if (isset($bot['state']['fail_count']) && $bot['state']['fail_count'] >= 5): ?>
                                    <span style="background: var(--danger); color: white; font-size: 0.65rem; padding: 1px 4px; border-radius: 4px; font-weight: 800;">
                                        ⚠️ <?= $bot['state']['fail_count'] ?> FAIL
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="bot-card-metrics">
                            <div class="bot-money">
                                <i class="fa fa-coins"></i> <?= number_format($bot['Money']) ?> GTLM
                            </div>
                            <div class="bot-wins">
                                🏆 <?= $bot['state']['wins'] ?? 0 ?> ván
                            </div>
                        </div>

                        <div class="bot-card-actions">
                            <button class="btn-card-action btn-inspect" onclick="showBotDetail(<?= $bot['Iduser'] ?>, '<?= $bot['Name'] ?>')">
                                <i class="fa fa-briefcase"></i> Soi Kho
                            </button>
                            <a href="index.php?action=reset_state&bot_id=<?= $bot['Iduser'] ?>" 
                               class="btn-card-action btn-reset"
                               onclick="return confirmReset(event, this.href)">
                                <i class="fa fa-rotate"></i> Reset
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

    </div>

    <!-- ── Modal Soi Kho Đồ Bot ── -->
    <div id="botModal" class="modal-overlay">
        <div class="modal-dialog">
            <button onclick="closeModal()" class="modal-close-btn">&times;</button>
            <h2 id="modalTitle" style="color: var(--primary); font-size: 1.3rem; margin-bottom: 14px;">🎒 Kho Đồ Bot</h2>
            <hr style="border: 0; border-top: 1px solid var(--border-glass); margin-bottom: 20px;">
            <div id="modalContent">
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">Đang lục lọi túi đồ...</div>
            </div>
        </div>
    </div>

    <script>
        // Prompt Sinh Bot
        function spawnBotsPrompt() {
            Swal.fire({
                title: '➕ Sinh Bot Siêu Tốc',
                text: 'Nhập số lượng bot muốn tạo thêm (Tối đa 50 bot/lần):',
                input: 'number',
                inputAttributes: { min: 1, max: 50, step: 1 },
                inputValue: 1,
                showCancelButton: true,
                confirmButtonColor: '#6366f1',
                cancelButtonColor: '#64748b',
                confirmButtonText: '🚀 Sinh Ngay',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const count = parseInt(result.value);
                    if (count > 0) {
                        window.location.href = "index.php?action=spawn&count=" + count;
                    }
                }
            });
        }

        // Confirm Reset
        function confirmReset(e, url) {
            e.preventDefault();
            Swal.fire({
                title: 'Reset Bot này?',
                text: 'Hành động này sẽ xóa session và trạng thái tạm thời của bot.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Reset',
                cancelButtonText: 'Hủy'
            }).then((res) => {
                if (res.isConfirmed) {
                    window.location.href = url;
                }
            });
            return false;
        }

        // Modal Soi Kho Đồ
        function showBotDetail(botId, botName) {
            const modal = document.getElementById('botModal');
            const title = document.getElementById('modalTitle');
            const content = document.getElementById('modalContent');
            
            title.innerText = `🎒 Kho Đồ của ${botName}`;
            modal.style.display = 'flex';
            content.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--text-muted);"><i class="fa fa-spinner fa-spin"></i> Đang lục lọi túi đồ...</div>';

            fetch(`api_bot_inventory.php?bot_id=${botId}`)
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        const d = res.data;
                        let html = '';
                        
                        const renderSection = (title, items, icon) => {
                            if (!items || items.length === 0) return '';
                            return `<div style="margin-bottom: 20px;">
                                <h4 style="color: var(--warn); border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 6px; margin-bottom: 10px; font-size: 0.9rem;">${icon} ${title}</h4>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    ${items.map(i => `<span style="padding: 4px 10px; font-size: 0.78rem; background: rgba(255,255,255,0.06); border-radius: 6px; border: 1px solid rgba(255,255,255,0.08);">${i.name || i.icon || ''} ${i.name || ''}</span>`).join('')}
                                </div>
                            </div>`;
                        };

                        html += renderSection('Themes Giao Diện', d.themes, '🎨');
                        html += renderSection('Con Trỏ Chuột', d.cursors, '🖱️');
                        html += renderSection('Khung Chat', d.chat_frames, '💬');
                        html += renderSection('Khung Avatar', d.avatar_frames, '🖼️');
                        html += renderSection('Thành Tựu', d.achievements, '🏆');

                        // Lịch sử chơi gần đây
                        html += `<div style="margin-top: 25px;">
                            <h4 style="color: var(--primary); border-bottom: 2px solid var(--primary); padding-bottom: 6px; font-size: 0.9rem;">📈 Lịch Sử Chơi Gần Đây</h4>
                            <table style="width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.78rem;">
                                <thead>
                                    <tr style="text-align: left; color: var(--text-muted); border-bottom: 1px solid rgba(255,255,255,0.1);">
                                        <th style="padding: 8px;">Game</th>
                                        <th style="padding: 8px;">Cược</th>
                                        <th style="padding: 8px;">Kết quả</th>
                                        <th style="padding: 8px;">Thời gian</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${(d.history || []).map(h => `
                                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.04);">
                                            <td style="padding: 8px; font-weight: 700; color: #fff;">${h.game}</td>
                                            <td style="padding: 8px; color: var(--warn); font-weight: 700;">${Number(h.bet).toLocaleString()}</td>
                                            <td style="padding: 8px; color: ${h.result === 'win' ? 'var(--success)' : 'var(--danger)'}; font-weight: 800;">
                                                ${(h.result || '').toUpperCase()}
                                            </td>
                                            <td style="padding: 8px; color: var(--text-dim);">${h.time}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                            ${(!d.history || d.history.length === 0) ? '<div style="text-align: center; padding: 20px; color: var(--text-dim);">Chưa có dữ liệu ván đấu.</div>' : ''}
                        </div>`;

                        if (html === '') html = '<div style="text-align: center; padding: 40px; color: var(--text-muted);">Bot này chưa có trang bị nào!</div>';
                        content.innerHTML = html;
                    }
                });
        }

        function closeModal() {
            document.getElementById('botModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('botModal');
            if (event.target == modal) closeModal();
        }

        // 📈 Wealth History Chart
        const lineCtx = document.getElementById('mainChart').getContext('2d');
        let wealthChart = new Chart(lineCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [
                    {
                        label: 'Tài sản Quân đoàn Bot',
                        data: <?= json_encode($chartBotData) ?>,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.12)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Tài sản Người thật',
                        data: <?= json_encode($chartHumanData) ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.12)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#94a3b8', font: { family: 'Outfit' } } } },
                scales: {
                    x: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,0.04)' } },
                    y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,0.04)' } }
                }
            }
        });

        // 🎭 Mood History Line Chart
        const moodLineCtx = document.getElementById('moodLineChart').getContext('2d');
        let moodLineChart = new Chart(moodLineCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [
                    { label: 'Hạnh phúc', data: <?= json_encode($moodHistory['happy']) ?>, borderColor: '#10b981', tension: 0.4 },
                    { label: 'Hưng phấn', data: <?= json_encode($moodHistory['excited']) ?>, borderColor: '#f59e0b', tension: 0.4 },
                    { label: 'Cay cú', data: <?= json_encode($moodHistory['tilted']) ?>, borderColor: '#ef4444', tension: 0.4 },
                    { label: 'Thất vọng', data: <?= json_encode($moodHistory['depressed']) ?>, borderColor: '#64748b', tension: 0.4 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8', boxWidth: 10, font: { family: 'Outfit' } } } },
                scales: {
                    x: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,0.04)' } },
                    y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,0.04)' } }
                }
            }
        });

        // Economy Doughnut
        const ecoCtx = document.getElementById('economyChart').getContext('2d');
        let ecoChart = new Chart(ecoCtx, {
            type: 'doughnut',
            data: {
                labels: ['Bot', 'Người thật'],
                datasets: [{
                    data: [<?= $stats['total_bot_money'] ?>, <?= $stats['total_human_money'] ?>],
                    backgroundColor: ['#6366f1', '#10b981'],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                let val = ctx.parsed || 0;
                                return ' ' + ctx.label + ': ' + Number(val).toLocaleString() + ' GTLM';
                            }
                        }
                    }
                },
                cutout: '72%'
            }
        });

        // Mood Doughnut
        const moodCtx = document.getElementById('moodChart').getContext('2d');
        let moodChart = new Chart(moodCtx, {
            type: 'doughnut',
            data: {
                labels: ['Hạnh phúc', 'Hưng phấn', 'Cay cú', 'Thất vọng'],
                datasets: [{ 
                    data: [<?= $stats['moods']['happy'] ?>, <?= $stats['moods']['excited'] ?>, <?= $stats['moods']['tilted'] ?>, <?= $stats['moods']['depressed'] ?>], 
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#64748b'], 
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                let val = ctx.parsed || 0;
                                return ' ' + ctx.label + ': ' + val + ' bot';
                            }
                        }
                    }
                },
                cutout: '68%'
            }
        });

        function updateEconomyPercentages(botMoney, humanMoney) {
            const total = Number(botMoney) + Number(humanMoney);
            const botPct = total > 0 ? ((botMoney / total) * 100).toFixed(1) : '0';
            const humanPct = total > 0 ? ((humanMoney / total) * 100).toFixed(1) : '0';
            const elBot = document.getElementById('ecoBotPct');
            const elHuman = document.getElementById('ecoHumanPct');
            if (elBot) elBot.textContent = botPct + '%';
            if (elHuman) elHuman.textContent = humanPct + '%';
        }

        function updateMoodLegend(moods) {
            if (!moods) return;
            const elExcited = document.getElementById('moodLegendExcited');
            const elTilted = document.getElementById('moodLegendTilted');
            const elHappy = document.getElementById('moodLegendHappy');
            const elDepressed = document.getElementById('moodLegendDepressed');
            if (elExcited) elExcited.textContent = moods.excited ?? 0;
            if (elTilted) elTilted.textContent = moods.tilted ?? 0;
            if (elHappy) elHappy.textContent = moods.happy ?? 0;
            if (elDepressed) elDepressed.textContent = moods.depressed ?? 0;
        }

        function updateDominantMoodUI(moods) {
            if (!moods) return;
            const moodMeta = {
                excited: { name: 'Hưng Phấn', color: 'var(--warn)', icon: 'fa-bolt' },
                happy: { name: 'Hạnh Phúc', color: 'var(--success)', icon: 'fa-face-smile' },
                tilted: { name: 'Cay Cú', color: 'var(--danger)', icon: 'fa-fire-flame-curved' },
                depressed: { name: 'Thất Vọng', color: '#94a3b8', icon: 'fa-face-sad-tear' }
            };

            let dominantKey = 'happy';
            let maxCount = -1;
            for (const [key, cnt] of Object.entries(moods)) {
                if (cnt > maxCount) {
                    maxCount = cnt;
                    dominantKey = key;
                }
            }

            const dom = moodMeta[dominantKey] || moodMeta.happy;
            const domVal = document.getElementById('dominantMoodValue');
            const domIcon = document.getElementById('dominantMoodIcon');
            const domIconWrap = document.getElementById('dominantMoodIconWrap');
            const domSub = document.getElementById('dominantMoodSubtitle');

            if (domVal) {
                domVal.textContent = dom.name;
                domVal.style.color = dom.color;
            }
            if (domIcon) {
                domIcon.className = 'fa ' + dom.icon;
            }
            if (domIconWrap) {
                domIconWrap.style.color = dom.color;
            }
            if (domSub) {
                let subHtml = `<span>🟢 ${moods.happy || 0} • 🟡 ${moods.excited || 0} • 🔴 ${moods.tilted || 0}`;
                if (moods.depressed && moods.depressed > 0) {
                    subHtml += ` • ⚪ ${moods.depressed}`;
                }
                subHtml += `</span>`;
                domSub.innerHTML = subHtml;
            }
        }

        // Khởi tạo phần trăm ban đầu
        updateEconomyPercentages(<?= $stats['total_bot_money'] ?>, <?= $stats['total_human_money'] ?>);

        // 🔄 Range Filter
        let currentRange = '1d';
        function setRange(range) {
            currentRange = range;
            document.querySelectorAll('.range-picker-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('btn-' + range).classList.add('active');
            refreshData();
        }

        // Clear Logs Action
        async function clearLogs() {
            if (!confirm('Bạn có chắc chắn muốn xóa toàn bộ log lỗi hôm nay không?')) return;
            try {
                const response = await fetch('bot_health.php?action=clear_logs');
                const res = await response.json();
                if (res.status === 'success') {
                    refreshData();
                }
            } catch (error) {
                console.error('Clear logs failed:', error);
            }
        }

        // Realtime Polling
        async function refreshData() {
            try {
                const response = await fetch('index.php?ajax=1&range=' + currentRange);
                const data = await response.json();

                // Update Charts
                wealthChart.data.labels = data.chartLabels;
                wealthChart.data.datasets[0].data = data.chartBotData;
                wealthChart.data.datasets[1].data = data.chartHumanData;
                wealthChart.update();

                ecoChart.data.datasets[0].data = [data.stats.total_bot_money, data.stats.total_human_money];
                ecoChart.update();

                moodChart.data.datasets[0].data = [data.stats.moods.happy, data.stats.moods.excited, data.stats.moods.tilted, data.stats.moods.depressed];
                moodChart.update();

                moodLineChart.data.labels = data.chartLabels;
                moodLineChart.data.datasets[0].data = data.moodHistory.happy;
                moodLineChart.data.datasets[1].data = data.moodHistory.excited;
                moodLineChart.data.datasets[2].data = data.moodHistory.tilted;
                moodLineChart.data.datasets[3].data = data.moodHistory.depressed;
                moodLineChart.update();

                // Update Doughnut Custom Legends & Dominant Mood
                updateEconomyPercentages(data.stats.total_bot_money, data.stats.total_human_money);
                updateMoodLegend(data.stats.moods);
                updateDominantMoodUI(data.stats.moods);

                // Update Logs
                const logEl = document.getElementById('liveLogs');
                if (logEl) {
                    logEl.innerHTML = data.recentLogs.map(log => `<div class="terminal-line">${log}</div>`).join('');
                }

                // Update Event Admin
                const eventAdminEl = document.getElementById('activeEventAdmin');
                if (eventAdminEl) {
                    if (data.stats.active_event) {
                        eventAdminEl.innerHTML = `
                            <div class="event-feature-card">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                                    <div style="font-weight: 800; font-size: 0.95rem; color: #fff; margin-bottom: 4px;">${data.stats.active_event.event_name}</div>
                                    <span style="font-size: 0.65rem; background: rgba(16, 185, 129, 0.25); color: var(--success); padding: 2px 7px; border-radius: 6px; font-weight: 800; white-space: nowrap;">ĐANG CHẠY</span>
                                </div>
                                <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 12px; line-height: 1.4;">${data.stats.active_event.description}</div>
                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--warn);"><i class="fa fa-clock"></i> Kết thúc: ${data.stats.active_event.ends_at.split(' ')[1]}</div>
                                    <a href="index.php?action=switch_event" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; text-decoration: none; padding: 6px 12px; border-radius: 8px; font-size: 0.75rem; font-weight: 800; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35); transition: transform 0.15s;" onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform='scale(1)'">
                                        <i class="fa fa-dice"></i> Bắt đầu sự kiện khác
                                    </a>
                                </div>
                            </div>
                        `;
                    } else {
                        eventAdminEl.innerHTML = `
                            <div style="text-align: center; padding: 18px 10px;">
                                <div style="font-size: 0.8rem; color: var(--text-dim); margin-bottom: 12px;">Không có sự kiện nào đang diễn ra.</div>
                                <a href="index.php?action=trigger_event" class="btn-toolbar btn-toolbar-primary" style="display: inline-flex;">
                                    <i class="fa fa-bolt"></i> ⚡ Kích hoạt ngay
                                </a>
                            </div>
                        `;
                    }
                }

            } catch (error) {
                console.error('Refresh failed:', error);
            }
        }

        setInterval(refreshData, 10000); // 10s auto sync

        // 🔍 Search Bot Army
        const searchInput = document.getElementById('searchBotInput');
        const botCards = document.querySelectorAll('.bot-card');

        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            botCards.forEach(card => {
                const name = card.getAttribute('data-name');
                const id = card.getAttribute('data-id');
                if (name.includes(query) || id.includes(query)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Quick focus on '/'
        document.addEventListener('keydown', (e) => {
            if (e.key === '/' && document.activeElement !== searchInput) {
                e.preventDefault();
                searchInput.focus();
            }
        });
    </script>
</body>
</html>
