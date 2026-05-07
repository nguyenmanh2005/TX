<?php
/**
 * 📊 Bot Army Status Dashboard - Realtime Monitoring
 * Premium Glassmorphism UI
 */
session_start();
require_once '../db_connect.php';

$syncFile = 'sessions/bot_sync.json';
$syncData = file_exists($syncFile) ? json_decode(file_get_contents($syncFile), true) : [];
$sessionFiles = glob('sessions/*.state.json');

$stats = [
    'total' => count($sessionFiles),
    'active' => 0,
    'moods' => ['happy' => 0, 'excited' => 0, 'tilted' => 0, 'depressed' => 0],
    'total_money' => 0,
    'total_investment' => 0,
    'gangs' => [],
    'top_rich' => []
];

$now = time();
foreach ($sessionFiles as $file) {
    $data = json_decode(file_get_contents($file), true);
    $mTime = filemtime($file);
    
    if ($now - $mTime < 600) $stats['active']++; 
    
    $mood = $data['mood'] ?? 'happy';
    if (isset($stats['moods'][$mood])) $stats['moods'][$mood]++;
    
    // Cộng dồn GTLM đầu tư
    $stats['total_investment'] += ($data['investment'] ?? 0);
    
    // Đếm băng đảng
    $botId = (int)str_replace(['sessions/', '.state.json', md5('')], '', $file); 
    $gangId = floor($botId / 5);
    $stats['gangs'][$gangId] = true;
}
$stats['total_gangs'] = count($stats['gangs']);

// Lấy Top Bot giàu nhất từ DB
$topBotsSql = "SELECT Name, Money, ImageURL FROM users WHERE Email LIKE '%bot%' ORDER BY Money DESC LIMIT 5";
$topBotsResult = $conn->query($topBotsSql);
while($row = $topBotsResult->fetch_assoc()) {
    $stats['top_rich'][] = $row;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>🛡️ Bot Army Dashboard v10.0</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --secondary: #a855f7;
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
            --bg: #0f172a;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            background: radial-gradient(circle at top right, #1e1b4b, #0f172a);
            color: white;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .grid-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            padding: 25px;
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.08);
        }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            margin: 10px 0;
            background: linear-gradient(to right, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            color: #94a3b8;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .panel {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        h2 {
            margin-top: 0;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .activity-item {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--primary);
        }

        .badge {
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-win { background: rgba(34, 197, 94, 0.2); color: #4ade80; }
        .badge-mood { background: rgba(99, 102, 241, 0.2); color: #818cf8; }

        .rich-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
        }

        .money { color: var(--warning); font-weight: 600; }

        .refresh-tag {
            font-size: 12px;
            color: var(--success);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
    <meta http-equiv="refresh" content="30">
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1 style="margin:0; font-size: 24px;">🛡️ Bot Army Dashboard</h1>
                <span class="refresh-tag">● Realtime Sync Active</span>
            </div>
            <div style="text-align: right">
                <div style="font-size: 14px; color: #94a3b8">Hệ thống v10.0</div>
                <div style="font-size: 12px"><?= date('H:i:s d/m/Y') ?></div>
            </div>
        </div>

        <div class="grid-stats">
            <div class="stat-card">
                <div class="stat-label">Tổng Quân Đoàn</div>
                <div class="stat-value"><?= $stats['total'] ?></div>
                <div style="color: var(--success); font-size: 12px;">Băng đảng: <?= $stats['total_gangs'] ?> nhóm</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Quỹ Đầu Tư</div>
                <div class="stat-value" style="color: var(--success)"><?= number_format($stats['total_investment'] / 1000, 1) ?>k</div>
                <div style="font-size: 12px; color: #94a3b8">Đang sinh lãi 0.1%</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Hưng Phấn (Excited)</div>
                <div class="stat-value" style="color: var(--warning)"><?= $stats['moods']['excited'] ?></div>
                <div style="font-size: 12px; color: #94a3b8">Đang thắng chuỗi</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Trầm Cảm (Depressed)</div>
                <div class="stat-value" style="color: #64748b"><?= $stats['moods']['depressed'] ?></div>
                <div style="font-size: 12px; color: #94a3b8">Đang đợi vốn</div>
            </div>
        </div>

        <div class="main-grid">
            <div class="panel">
                <h2>🔥 Hoạt Động Xã hội Gần Đây</h2>
                <?php if (isset($syncData['big_wins'])): ?>
                    <?php foreach (array_reverse($syncData['big_wins']) as $win): ?>
                        <div class="activity-item">
                            <div class="avatar" style="background: linear-gradient(45deg, #6366f1, #a855f7)"></div>
                            <div style="flex: 1">
                                <div style="font-weight: 600"><?= $win['user_name'] ?></div>
                                <div style="font-size: 13px; color: #94a3b8">Vừa thắng lớn tại Casino</div>
                            </div>
                            <div style="text-align: right">
                                <div class="money">+<?= number_format($win['amount']) ?></div>
                                <div class="badge badge-win">BIG WIN</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #94a3b8">Chưa có hoạt động nào được ghi nhận...</p>
                <?php endif; ?>
            </div>

            <div class="panel">
                <h2>💰 Top Bot Đại Gia</h2>
                <?php foreach ($stats['top_rich'] as $rich): ?>
                    <div class="rich-list-item">
                        <div style="display: flex; align-items: center; gap: 10px">
                            <img src="<?= $rich['ImageURL'] ?: '../img/default-avatar.png' ?>" style="width: 32px; height: 32px; border-radius: 8px;">
                            <span style="font-size: 14px"><?= $rich['Name'] ?></span>
                        </div>
                        <div class="money"><?= number_format($rich['Money']) ?></div>
                    </div>
                <?php endforeach; ?>
                
                <h2 style="margin-top: 30px">🎂 Sinh Nhật Hôm Nay</h2>
                <?php if (isset($syncData['today_birthdays'])): ?>
                    <?php foreach ($syncData['today_birthdays'] as $id => $name): ?>
                        <div class="activity-item">
                            <span>🎁</span>
                            <span style="font-size: 14px"><?= $name ?></span>
                            <span class="badge badge-mood">BIRTHDAY</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="font-size: 12px; color: #94a3b8">Hôm nay không có sinh nhật nào.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
