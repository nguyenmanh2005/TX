<?php
/**
 * 🛡️ Bot Army Control Center (Dashboard) v15.7
 * Features: Multi-Game Stats, Economy Chart, Detailed Bot Inventory
 */
session_start();
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
        header("Location: index.php?msg=Reset thành công bot #$botId");
        exit;
    }
}

$syncFile = 'sessions/bot_sync.json';
$historyFile = 'sessions/economy_history.json';
$syncData = file_exists($syncFile) ? json_decode(file_get_contents($syncFile), true) : [];
$history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
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
            // Nếu là URL, rút gọn lại. Nếu là PHP_SYSTEM thì giữ nguyên.
            $label = (strpos($source, 'http') === 0) ? parse_url($source, PHP_URL_PATH) : $source;
            // Bảo mật: Xóa tên file nhạy cảm khỏi nhãn
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
    'top_bots_today' => []
];

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
    $row['state']['mood'] = $currentMood; // Đảm bảo luôn có mood để hiển thị
    $botsList[] = $row;
}

// Lấy tổng GTLM người thật
$humanRes = $conn->query("SELECT SUM(Money) as total FROM users WHERE Email NOT LIKE '%bot%'")->fetch_assoc();
$stats['total_human_money'] = (float)($humanRes['total'] ?? 0);

// 5. Kiểm tra sức khỏe Bot
require_once 'bot_health.php';
$healthSummary = getBotHealthSummary($conn, $config);

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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>🛡️ Bot Control Center v15.7</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --primary: #6366f1; --secondary: #a855f7; --success: #22c55e; --danger: #ef4444; --status-warn: #f59e0b; --bg: #0f172a; --card: rgba(255,255,255,0.05); }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: white; margin: 0; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding: 20px; background: var(--card); border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); }
        .grid-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--card); padding: 20px; border-radius: 24px; border: 1px solid rgba(255, 255, 255, 0.1); }
        .stat-value { font-size: 28px; font-weight: 700; margin: 5px 0; }
        .stat-label { color: #94a3b8; font-size: 12px; text-transform: uppercase; }
        .main-layout { display: grid; grid-template-columns: 1fr 350px; gap: 20px; margin-bottom: 30px; }
        .panel { background: var(--card); border-radius: 24px; padding: 20px; border: 1px solid rgba(255, 255, 255, 0.1); }
        
        /* Bot Grid Style Optimized */
        .bot-list-panel { margin-top: 30px; }
        .bot-grid-container { 
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 15px;
            max-height: 800px;
            overflow-y: auto;
            padding: 10px;
            scrollbar-width: thin;
        }
        .bot-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 15px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .bot-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.06);
            border-color: var(--primary);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .bot-card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .bot-avatar { width: 45px; height: 45px; border-radius: 12px; object-fit: cover; border: 2px solid rgba(255,255,255,0.1); }
        .bot-info h3 { margin: 0; font-size: 15px; font-weight: 600; }
        .bot-info span { font-size: 10px; color: #94a3b8; }
        
        .bot-stats { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .money { color: var(--status-warn); font-weight: 700; font-size: 14px; }
        .wins { font-size: 12px; color: var(--primary); }
        
        .mood-badge { font-size: 9px; padding: 2px 6px; border-radius: 5px; text-transform: uppercase; font-weight: 800; }
        .mood-happy { background: #22c55e; color: white; }
        .mood-excited { background: #f59e0b; color: white; }
        .mood-tilted { background: #ef4444; color: white; }
        .mood-depressed { background: #64748b; color: white; }
        
        .api-fail-list { font-size: 11px; list-style: none; padding: 0; }
        .api-fail-item { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .api-fail-count { color: var(--danger); font-weight: 700; }
        
        .inventory-tags { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 8px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 8px; }
        .tag { font-size: 9px; background: rgba(99, 102, 241, 0.1); color: #a5b4fc; padding: 2px 6px; border-radius: 4px; }
        .btn { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 12px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 600; }
        
        .range-selector { display: flex; gap: 5px; background: rgba(255,255,255,0.05); padding: 4px; border-radius: 10px; }
        .range-btn { background: transparent; border: none; color: #94a3b8; font-size: 10px; font-weight: 600; padding: 5px 10px; border-radius: 7px; cursor: pointer; transition: all 0.2s; }
        .range-btn.active { background: var(--primary); color: white; }
        .range-btn:hover:not(.active) { background: rgba(255,255,255,0.05); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1 style="margin:0; font-size: 24px;">🛡️ Bot Army Control Center v15.7</h1>
                <span style="color: var(--success); font-size: 12px;">● Đang giám sát <?= $stats['total'] ?> thành viên Thiên Thần & Ác Quỷ</span>
            </div>
            <div style="display: flex; gap: 10px;">
               
                <a href="../chat3.php" class="btn" style="background: #334155; border: 1px solid rgba(255,255,255,0.1)">📋 Báo Cáo Tester</a>
                <a href="../games/megaspin.php" class="btn" style="background: #065f46; border: 1px solid rgba(255,255,255,0.1)">🎡 Mega Spin</a>
                <a href="bot_buff.php" class="btn" style="background: #1e293b; border: 1px solid rgba(255,255,255,0.1)">💰 Buff Tiền</a>
                <a href="bot_runner.php" class="btn" style="background: linear-gradient(135deg, #6366f1, #a855f7)">🚀 Trình Điều Khiển Web</a>
                <a href="javascript:void(0)" onclick="spawnBots()" class="btn" style="background: var(--status-warn)">➕ Sinh Bot</a>
            </div>
        </div>

        <script>
        function spawnBots() {
            let count = prompt("Bạn muốn sinh thêm bao nhiêu bot? (Tối đa 50)", "1");
            if (count && !isNaN(count) && count > 0) {
                window.location.href = "bot_manager.php?action=spawn&count=" + count;
            }
        }
        </script>
        </div>

        <div class="grid-stats">
            <div class="stat-card">
                <div class="stat-label">Tổng Tài Sản Quân Đoàn</div>
                <div class="stat-value" style="color: var(--status-warn)"><?= number_format($stats['total_bot_money']) ?> GTLM</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Tỉ Lệ Lạm Phát</div>
                <?php $inflation = ($stats['total_bot_money'] / ($stats['total_human_money'] ?: 1)) * 100; ?>
                <div class="stat-value" style="color: <?= $inflation > 100 ? 'var(--danger)' : 'var(--success)' ?>">
                    <?= number_format($inflation, 1) ?>%
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Tâm Trạng Chủ Đạo</div>
                <div class="stat-value" style="color: var(--secondary)">Hạnh Phúc</div>
            </div>
        </div>

        <div class="main-layout">
            <div class="left-col">
                <div class="panel" style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h2 style="font-size: 18px; margin:0;">📊 Biến Động Tài Sản</h2>
                        <div class="range-selector">
                            <button onclick="setRange('1d')" class="range-btn active" id="btn-1d">1N</button>
                            <button onclick="setRange('7d')" class="range-btn" id="btn-7d">7N</button>
                            <button onclick="setRange('30d')" class="range-btn" id="btn-30d">1T</button>
                            <button onclick="setRange('all')" class="range-btn" id="btn-all">Tất cả</button>
                        </div>
                    </div>
                    <div style="height: 300px;">
                        <canvas id="mainChart"></canvas>
                    </div>
                </div>
                <div class="panel" style="margin-bottom: 20px;">
                    <h2 style="font-size: 18px; margin-top:0;">🎭 Biến Động Tâm Trạng (Lịch Sử)</h2>
                    <div style="height: 250px;">
                        <canvas id="moodLineChart"></canvas>
                    </div>
                </div>
                <div class="panel">
                    <h2 style="font-size: 18px; margin-top:0;">⚡ Hoạt Động Trực Tiếp (Live Logs)</h2>
                    <div id="liveLogs" style="height: 250px; overflow-y: auto; font-family: monospace; font-size: 11px; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 15px; line-height: 1.6;">
                        <?php foreach ($recentLogs as $log): ?>
                            <div style="margin-bottom: 4px; border-bottom: 1px solid rgba(255,255,255,0.03); padding-bottom: 2px;">
                                <?= str_ireplace('config.php', '[REDACTED]', htmlspecialchars($log)) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="right-col">
                <div class="panel" style="margin-bottom: 20px; border: 2px solid var(--status-warn); background: rgba(245, 158, 11, 0.05);">
                    <h2 style="font-size: 18px; margin-top:0; color: var(--status-warn); display: flex; align-items: center; gap: 10px;">
                        <span>👑 OP BOT HÔM NAY</span>
                        <span style="font-size: 10px; background: var(--status-warn); color: black; padding: 2px 6px; border-radius: 4px;">TOP PROFIT</span>
                    </h2>
                    <div id="opBotSection">
                        <?php if (!empty($stats['top_bots_today'])): 
                            $opBot = $stats['top_bots_today'][0]; ?>
                            <div style="text-align: center; padding: 20px 10px;">
                                <div style="position: relative; display: inline-block;">
                                    <img src="<?= $opBot['ImageURL'] ?>" style="width: 80px; height: 80px; border-radius: 24px; border: 3px solid var(--status-warn); box-shadow: 0 0 20px rgba(245, 158, 11, 0.4);">
                                    <div style="position: absolute; bottom: -10px; right: -10px; background: var(--status-warn); color: black; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 900; border: 3px solid var(--bg);">1</div>
                                </div>
                                <h3 style="margin: 15px 0 5px; font-size: 20px;"><?= $opBot['Name'] ?></h3>
                                <div style="color: var(--success); font-weight: 800; font-size: 18px;">+<?= number_format($opBot['total_profit']) ?> GTLM</div>
                                <div style="font-size: 11px; color: #94a3b8; margin-top: 5px;">Húp mạnh nhất quân đoàn hôm nay! 🚀</div>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 30px; color: #64748b; font-size: 12px;">Đang tìm kiếm OP Bot...</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel" style="margin-bottom: 20px;">
                    <h2 style="font-size: 18px; margin-top:0; color: var(--danger);">🚨 Tổng Hợp API Fail</h2>
                    <div style="background: rgba(239, 68, 68, 0.05); padding: 15px; border-radius: 15px; border: 1px solid rgba(239, 68, 68, 0.1);">
                        <ul class="api-fail-list">
                            <?php if (empty($apiFailures)): ?>
                                <li style="color: #94a3b8; text-align: center; padding: 10px;">✨ Hệ thống ổn định, 0 lỗi API.</li>
                            <?php else: ?>
                                <?php foreach (array_slice($apiFailures, 0, 8) as $api => $count): 
                                    $severity = $count > 10 ? 'var(--danger)' : ($count > 5 ? 'var(--status-warn)' : 'var(--success)');
                                ?>
                                    <li class="api-fail-item" style="border-color: rgba(255,255,255,0.03);">
                                        <code style="color: #f8fafc; font-size: 10px;"><?= htmlspecialchars($api) ?></code>
                                        <span style="color: <?= $severity ?>; font-weight: 800; font-size: 12px;"><?= $count ?> 🔥</span>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <div class="panel" style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h2 style="font-size: 18px; margin:0;">🏥 Sức Khỏe Quân Đoàn</h2>
                        <button onclick="clearLogs()" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); padding: 4px 10px; border-radius: 8px; font-size: 10px; font-weight: 800; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='rgba(239, 68, 68, 0.2)'" onmouseout="this.style.background='rgba(239, 68, 68, 0.1)'">
                            🗑️ Clear lỗi
                        </button>
                    </div>
                    <div id="healthSummarySection">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px; text-align: center;">
                            <div style="flex:1;">
                                <div style="font-size: 18px; font-weight: 800; color: #22c55e;"><?= $healthSummary['healthy'] ?></div>
                                <div style="font-size: 10px; color: #94a3b8;">Khỏe mạnh</div>
                            </div>
                            <div style="flex:1; border-left: 1px solid rgba(255,255,255,0.05); border-right: 1px solid rgba(255,255,255,0.05);">
                                <div style="font-size: 18px; font-weight: 800; color: #fbbf24;"><?= $healthSummary['warning'] ?></div>
                                <div style="font-size: 10px; color: #94a3b8;">Cảnh báo</div>
                            </div>
                            <div style="flex:1;">
                                <div style="font-size: 18px; font-weight: 800; color: #ef4444;"><?= $healthSummary['critical'] ?></div>
                                <div style="font-size: 10px; color: #94a3b8;">Lỗi nặng</div>
                            </div>
                        </div>
                        <div id="healthDetailsList" style="max-height: 150px; overflow-y: auto;">
                            <?php if (empty($healthSummary['details'])): ?>
                                <div style="font-size: 11px; color: #64748b; text-align: center; padding: 10px; border: 1px dashed rgba(255,255,255,0.1); border-radius: 10px;">
                                    Tất cả bot đều đang hoạt động tốt. ✨
                                </div>
                            <?php else: ?>
                                <?php foreach ($healthSummary['details'] as $detail): ?>
                                    <div style="font-size: 10px; padding: 8px; background: rgba(255,255,255,0.02); border-radius: 8px; margin-bottom: 5px; border-left: 3px solid <?= $detail['status'] == 'critical' ? '#ef4444' : '#fbbf24' ?>;">
                                        <div style="font-weight: 800;"><?= $detail['email'] ?></div>
                                        <div style="color: #94a3b8;"><?= implode(', ', $detail['issues']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="panel" style="margin-bottom: 20px;">
                    <h2 style="font-size: 18px; margin-top:0; color: var(--primary);">🏆 BXH Phụ</h2>
                    <div id="topBotsToday">
                        <?php foreach (array_slice($stats['top_bots_today'], 1) as $index => $bot): ?>
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; padding: 8px; background: rgba(255,255,255,0.02); border-radius: 12px; border: 1px solid rgba(255,255,255,0.03);">
                                <span style="font-weight: 800; color: #94a3b8; min-width: 20px;">#<?= $index+2 ?></span>
                                <img src="<?= $bot['ImageURL'] ?>" style="width: 30px; height: 30px; border-radius: 8px; opacity: 0.8;">
                                <div style="flex: 1;">
                                    <div style="font-size: 12px; font-weight: 600;"><?= $bot['Name'] ?></div>
                                    <div style="font-size: 10px; color: var(--success);">+<?= number_format($bot['total_profit']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="panel" style="margin-bottom: 20px;">
                    <h2 style="font-size: 18px; margin-top:0;">💰 Cán Cân Hiện Tại</h2>
                    <div style="height: 180px;">
                        <canvas id="economyChart"></canvas>
                    </div>
                </div>
                <div class="panel">
                    <h2 style="font-size: 18px; margin-top:0;">🎭 Tâm trạng</h2>
                    <div style="height: 180px;">
                        <canvas id="moodChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin: 30px 0 15px;">
            <h2 style="font-size: 20px; margin: 0;">📂 Quân Đoàn Chi Tiết (Grid View)</h2>
            <div style="position: relative; width: 300px;">
                <input type="text" id="searchBotInput" placeholder="🔍 Tìm tên hoặc ID bot..." 
                       style="width: 100%; padding: 10px 15px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: white; outline: none; transition: all 0.3s ease;">
            </div>
        </div>

        <div class="bot-grid-container" id="botGrid">
            <?php foreach ($botsList as $bot): ?>
                <div class="bot-card" data-name="<?= strtolower($bot['Name']) ?>" data-id="<?= $bot['Iduser'] ?>">
                    <div class="bot-card-header">
                        <img src="<?= $bot['ImageURL'] ?>" class="bot-avatar">
                        <div class="bot-info">
                            <h3><?= $bot['Name'] ?></h3>
                            <span>ID: #<?= $bot['Iduser'] ?></span>
                        </div>
                        <div style="margin-left: auto;">
                            <span class="mood-badge mood-<?= $bot['state']['mood'] ?? 'happy' ?>">
                                <?= $bot['state']['mood'] ?? 'happy' ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="bot-stats">
                        <div class="money"><?= number_format($bot['Money']) ?> GTLM</div>
                        <div class="wins">🏆 <?= $bot['state']['wins'] ?? 0 ?></div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px;">
                        <div class="inventory-tags" style="margin-top: 0; border: none; padding: 0;">
                            <?php if ($bot['chat_frame_name']): ?>
                                <span class="tag">💬</span>
                            <?php endif; ?>
                            <?php if ($bot['avatar_frame_name']): ?>
                                <span class="tag">🖼️</span>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; gap: 5px;">
                            <button class="btn" style="padding: 4px 10px; font-size: 10px; background: rgba(99,102,241,0.2); border: 1px solid var(--primary);" 
                                    onclick="showBotDetail(<?= $bot['Iduser'] ?>, '<?= $bot['Name'] ?>')">
                                Soi Kho 🎒
                            </button>
                            <a href="index.php?action=reset_state&bot_id=<?= $bot['Iduser'] ?>" 
                               class="btn" style="padding: 4px 10px; font-size: 10px; background: rgba(239,68,68,0.2); border: 1px solid var(--danger);"
                               onclick="return confirm('Reset toàn bộ trạng thái và session của bot này?')">
                                Reset 🔄
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 🎒 Modal Soi Kho Đồ Bot -->
    <div id="botModal" style="display:none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; backdrop-filter: blur(10px); align-items:center; justify-content:center;">
        <div style="background: var(--bg); width: 600px; max-height: 80vh; border-radius: 30px; border: 1px solid var(--primary); padding: 30px; position: relative; overflow-y: auto; box-shadow: 0 0 50px rgba(99,102,241,0.3);">
            <button onclick="closeModal()" style="position: absolute; top: 20px; right: 20px; background: none; border: none; color: #94a3b8; font-size: 24px; cursor: pointer;">&times;</button>
            <h2 id="modalTitle" style="color: var(--primary); margin-top: 0;">🎒 Kho Đồ Bot</h2>
            <hr style="border: 0; border-top: 1px solid rgba(255,255,255,0.1); margin: 20px 0;">
            <div id="modalContent">
                <div style="text-align: center; padding: 40px; color: #94a3b8;">Đang lục lọi túi đồ...</div>
            </div>
        </div>
    </div>

    <script>
        function showBotDetail(botId, botName) {
            const modal = document.getElementById('botModal');
            const title = document.getElementById('modalTitle');
            const content = document.getElementById('modalContent');
            
            title.innerText = `🎒 Kho Đồ của ${botName}`;
            modal.style.display = 'flex';
            content.innerHTML = '<div style="text-align: center; padding: 40px; color: #94a3b8;">Đang lục lọi túi đồ...</div>';

            fetch(`api_bot_inventory.php?bot_id=${botId}`)
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        const d = res.data;
                        let html = '';
                        
                        const renderSection = (title, items, icon) => {
                            if (items.length === 0) return '';
                            return `<div style="margin-bottom: 20px;">
                                <h4 style="color: var(--status-warn); border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 5px;">${icon} ${title}</h4>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    ${items.map(i => `<span class="tag" style="padding: 5px 10px; font-size: 12px; background: rgba(255,255,255,0.05);">${i.name || i.icon || ''} ${i.name || ''}</span>`).join('')}
                                </div>
                            </div>`;
                        };

                        html += renderSection('Themes', d.themes, '🎨');
                        html += renderSection('Cursors', d.cursors, '🖱️');
                        html += renderSection('Khung Chat', d.chat_frames, '💬');
                        html += renderSection('Khung Avatar', d.avatar_frames, '🖼️');
                        html += renderSection('Thành Tựu', d.achievements, '🏆');

                        // 📈 Lịch sử chơi
                        html += `<div style="margin-top: 30px;">
                            <h4 style="color: var(--primary); border-bottom: 2px solid var(--primary); padding-bottom: 5px;">📈 Lịch Sử Chơi Gần Đây</h4>
                            <table style="width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px;">
                                <thead>
                                    <tr style="text-align: left; color: #94a3b8;">
                                        <th style="padding: 8px;">Game</th>
                                        <th style="padding: 8px;">GTLM</th>
                                        <th style="padding: 8px;">Kết quả</th>
                                        <th style="padding: 8px;">Thời gian</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${d.history.map(h => `
                                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <td style="padding: 8px; font-weight: 600;">${h.game}</td>
                                            <td style="padding: 8px;">${h.bet.toLocaleString()}</td>
                                            <td style="padding: 8px; color: ${h.result === 'win' ? '#22c55e' : '#ef4444'}; font-weight: 800;">
                                                ${h.result.toUpperCase()}
                                            </td>
                                            <td style="padding: 8px; color: #94a3b8;">${h.time}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                            ${d.history.length === 0 ? '<div style="text-align: center; padding: 20px; color: #64748b;">Chưa có dữ liệu ván đấu.</div>' : ''}
                        </div>`;

                        if (html === '') html = '<div style="text-align: center; padding: 40px; color: #94a3b8;">Bot này nghèo lắm, chưa có gì cả!</div>';
                        content.innerHTML = html;
                    }
                });
        }

        function closeModal() {
            document.getElementById('botModal').style.display = 'none';
        }

        // Đóng modal khi click ra ngoài
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
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Tài sản Người thật',
                        data: <?= json_encode($chartHumanData) ?>,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#94a3b8' } } },
                scales: {
                    x: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                    y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,0.05)' } }
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
                    { label: 'Hạnh phúc', data: <?= json_encode($moodHistory['happy']) ?>, borderColor: '#22c55e', tension: 0.4 },
                    { label: 'Hưng phấn', data: <?= json_encode($moodHistory['excited']) ?>, borderColor: '#f59e0b', tension: 0.4 },
                    { label: 'Cay cú', data: <?= json_encode($moodHistory['tilted']) ?>, borderColor: '#ef4444', tension: 0.4 },
                    { label: 'Thất vọng', data: <?= json_encode($moodHistory['depressed']) ?>, borderColor: '#64748b', tension: 0.4 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8', boxWidth: 10 } } },
                scales: {
                    x: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                    y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                }
            }
        });

        const ecoCtx = document.getElementById('economyChart').getContext('2d');
        let ecoChart = new Chart(ecoCtx, {
            type: 'doughnut',
            data: {
                labels: ['GTLM Bot', 'GTLM Người Thật'],
                datasets: [{
                    data: [<?= $stats['total_bot_money'] ?>, <?= $stats['total_human_money'] ?>],
                    backgroundColor: ['#6366f1', '#22c55e'],
                    borderWidth: 0,
                    hoverOffset: 20
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#94a3b8', font: { family: 'Outfit', size: 12 } } }
                },
                cutout: '70%'
            }
        });

        const moodCtx = document.getElementById('moodChart').getContext('2d');
        let moodChart = new Chart(moodCtx, {
            type: 'doughnut',
            data: {
                labels: ['Hạnh phúc', 'Hưng phấn', 'Cay cú', 'Thất vọng'],
                datasets: [{ 
                    data: [<?= $stats['moods']['happy'] ?>, <?= $stats['moods']['excited'] ?>, <?= $stats['moods']['tilted'] ?>, <?= $stats['moods']['depressed'] ?>], 
                    backgroundColor: ['#22c55e', '#f59e0b', '#ef4444', '#64748b'], 
                    borderWidth: 0 
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8' } } } 
            }
        });

        // 🔄 Auto Refresh Logic
        let currentRange = '1d';
        function setRange(range) {
            currentRange = range;
            document.querySelectorAll('.range-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('btn-' + range).classList.add('active');
            refreshData();
        }

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

        async function refreshData() {
            try {
                const response = await fetch('index.php?ajax=1&range=' + currentRange);
                const data = await response.json();

                // Update Stats
                document.querySelectorAll('.stat-value')[0].innerText = new Intl.NumberFormat().format(data.stats.total_bot_money) + ' GTLM';
                const inflation = (data.stats.total_bot_money / (data.stats.total_human_money || 1)) * 100;
                const inflEl = document.querySelectorAll('.stat-value')[1];
                inflEl.innerText = inflation.toFixed(1) + '%';
                inflEl.style.color = inflation > 100 ? 'var(--danger)' : 'var(--success)';

                // Update Logs
                const logEl = document.getElementById('liveLogs');
                if (logEl) {
                    logEl.innerHTML = data.recentLogs.map(log => `<div style="margin-bottom: 4px; border-bottom: 1px solid rgba(255,255,255,0.03); padding-bottom: 2px;">${log}</div>`).join('');
                }

                // Update Charts
                wealthChart.data.labels = data.chartLabels;
                wealthChart.data.datasets[0].data = data.chartBotData;
                wealthChart.data.datasets[1].data = data.chartHumanData;
                wealthChart.update();

                ecoChart.data.datasets[0].data = [data.stats.total_bot_money, data.stats.total_human_money];
                ecoChart.update();

                moodChart.data.datasets[0].data = [data.stats.moods.happy, data.stats.moods.excited, data.stats.moods.tilted, data.stats.moods.depressed];
                moodChart.update();

                // Update Mood Line Chart
                moodLineChart.data.labels = data.chartLabels;
                moodLineChart.data.datasets[0].data = data.moodHistory.happy;
                moodLineChart.data.datasets[1].data = data.moodHistory.excited;
                moodLineChart.data.datasets[2].data = data.moodHistory.tilted;
                moodLineChart.data.datasets[3].data = data.moodHistory.depressed;
                moodLineChart.update();

                // Update Top & OP Bots
                const opBotEl = document.getElementById('opBotSection');
                const topBotsEl = document.getElementById('topBotsToday');
                if (data.stats.top_bots_today && data.stats.top_bots_today.length > 0) {
                    const opBot = data.stats.top_bots_today[0];
                    if (opBotEl) {
                        opBotEl.innerHTML = `
                            <div style="text-align: center; padding: 20px 10px;">
                                <div style="position: relative; display: inline-block;">
                                    <img src="${opBot.ImageURL}" style="width: 80px; height: 80px; border-radius: 24px; border: 3px solid var(--status-warn); box-shadow: 0 0 20px rgba(245,158,11,0.4);">
                                    <div style="position: absolute; bottom: -10px; right: -10px; background: var(--status-warn); color: black; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 900; border: 3px solid var(--bg);">1</div>
                                </div>
                                <h3 style="margin: 15px 0 5px; font-size: 20px;">${opBot.Name}</h3>
                                <div style="color: var(--success); font-weight: 800; font-size: 18px;">+${new Intl.NumberFormat().format(opBot.total_profit)} GTLM</div>
                                <div style="font-size: 11px; color: #94a3b8; margin-top: 5px;">Húp mạnh nhất quân đoàn hôm nay! 🚀</div>
                            </div>
                        `;
                    }
                    if (topBotsEl) {
                        topBotsEl.innerHTML = data.stats.top_bots_today.slice(1).map((bot, index) => `
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; padding: 8px; background: rgba(255,255,255,0.02); border-radius: 12px; border: 1px solid rgba(255,255,255,0.03);">
                                <span style="font-weight: 800; color: #94a3b8; min-width: 20px;">#${index+2}</span>
                                <img src="${bot.ImageURL}" style="width: 30px; height: 30px; border-radius: 8px; opacity: 0.8;">
                                <div style="flex: 1;">
                                    <div style="font-size: 12px; font-weight: 600;">${bot.Name}</div>
                                    <div style="font-size: 10px; color: var(--success);">+${new Intl.NumberFormat().format(bot.total_profit)}</div>
                                </div>
                            </div>
                        `).join('');
                    }
                }

                // Update Health Summary
                const healthEl = document.getElementById('healthSummarySection');
                if (healthEl && data.healthSummary) {
                    let detailsHtml = '';
                    if (data.healthSummary.details.length === 0) {
                        detailsHtml = '<div style="font-size: 11px; color: #64748b; text-align: center; padding: 10px; border: 1px dashed rgba(255,255,255,0.1); border-radius: 10px;">Tất cả bot đều đang hoạt động tốt. ✨</div>';
                    } else {
                        detailsHtml = data.healthSummary.details.map(d => `
                            <div style="font-size: 10px; padding: 8px; background: rgba(255,255,255,0.02); border-radius: 8px; margin-bottom: 5px; border-left: 3px solid ${d.status === 'critical' ? '#ef4444' : '#fbbf24'};">
                                <div style="font-weight: 800;">${d.email}</div>
                                <div style="color: #94a3b8;">${d.issues.join(', ')}</div>
                            </div>
                        `).join('');
                    }

                    healthEl.innerHTML = `
                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px; text-align: center;">
                            <div style="flex:1;">
                                <div style="font-size: 18px; font-weight: 800; color: #22c55e;">${data.healthSummary.healthy}</div>
                                <div style="font-size: 10px; color: #94a3b8;">Khỏe mạnh</div>
                            </div>
                            <div style="flex:1; border-left: 1px solid rgba(255,255,255,0.05); border-right: 1px solid rgba(255,255,255,0.05);">
                                <div style="font-size: 18px; font-weight: 800; color: #fbbf24;">${data.healthSummary.warning}</div>
                                <div style="font-size: 10px; color: #94a3b8;">Cảnh báo</div>
                            </div>
                            <div style="flex:1;">
                                <div style="font-size: 18px; font-weight: 800; color: #ef4444;">${data.healthSummary.critical}</div>
                                <div style="font-size: 10px; color: #94a3b8;">Lỗi nặng</div>
                            </div>
                        </div>
                        <div id="healthDetailsList" style="max-height: 150px; overflow-y: auto;">
                            ${detailsHtml}
                        </div>
                    `;
                }

            } catch (error) {
                console.error('Refresh failed:', error);
            }
        }

        setInterval(refreshData, 10000); // Refresh every 10s

        // 🔍 Real-time Search Logic
        const searchInput = document.getElementById('searchBotInput');
        const botCards = document.querySelectorAll('.bot-card');

        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            
            botCards.forEach(card => {
                const name = card.getAttribute('data-name');
                const id = card.getAttribute('data-id');
                
                if (name.includes(query) || id.includes(query)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Tự động focus vào ô tìm kiếm khi nhấn phím '/'
        document.addEventListener('keydown', (e) => {
            if (e.key === '/' && document.activeElement !== searchInput) {
                e.preventDefault();
                searchInput.focus();
            }
        });
    </script>
</body>
</html>
