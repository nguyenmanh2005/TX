<?php
session_start();
if ((!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) && (!isset($_SESSION['Role']) || $_SESSION['Role'] != 1)) {
    header('Location: ../login.php');
    exit;
}
require_once '../db_connect.php';

$sessionFiles = glob('sessions/*.state.json');

// Map MD5(Email) to Bot Name
$botNames = [];
$resBots = $conn->query("SELECT Name, MD5(Email) as hash FROM users WHERE Email REGEXP '^bot[0-9]+@'");
if ($resBots) {
    while($row = $resBots->fetch_assoc()) {
        $botNames[$row['hash']] = $row['Name'];
    }
}

$botData = [];
$stats = [
    'avg_level' => 0,
    'total_xp' => 0,
    'roles' => ['commoner' => 0, 'whale' => 0, 'reporter' => 0, 'influencer' => 0],
    'top_levels' => []
];

foreach ($sessionFiles as $file) {
    $emailMd5 = str_replace(['sessions/', '.state.json'], '', $file);
    $state = json_decode(file_get_contents($file), true);
    
    $lvl = $state['level'] ?? 1;
    $xp = $state['xp'] ?? 0;
    $role = $state['social_role'] ?? 'commoner';
    
    $stats['avg_level'] += $lvl;
    $stats['total_xp'] += $xp;
    if (isset($stats['roles'][$role])) $stats['roles'][$role]++;
    
    $botData[] = [
        'md5' => $emailMd5,
        'name' => $botNames[$emailMd5] ?? 'Unknown Bot',
        'level' => $lvl,
        'xp' => $xp,
        'role' => $role,
        'wins' => $state['wins'] ?? 0,
        'mood' => $state['mood'] ?? 'happy'
    ];
}

if (count($botData) > 0) {
    $stats['avg_level'] = round($stats['avg_level'] / count($botData), 1);
    usort($botData, fn($a, $b) => $b['level'] <=> $a['level']);
    $stats['top_levels'] = array_slice($botData, 0, 10);
}

// Lấy tin nhắn Social gần đây từ Bot Reporters
$newsRes = $conn->query("SELECT u.Name, s.message, s.created_at FROM social_feed s JOIN users u ON s.user_id = u.Iduser WHERE u.Email REGEXP '^bot[0-9]+@' ORDER BY s.created_at DESC LIMIT 15");
$newsFeed = [];
if ($newsRes) {
    while($row = $newsRes->fetch_assoc()) $newsFeed[] = $row;
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>🧠 Bot Intelligence Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Outfit:wght@300;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #020617; --card: #0f172a; --primary: #38bdf8; --secondary: #6366f1; --accent: #f59e0b; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: #94a3b8; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { font-size: 2rem; color: #f8fafc; font-weight: 800; margin: 0; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--card); padding: 20px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.05); text-align: center; }
        .stat-card h3 { margin: 0; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: #64748b; }
        .stat-card .value { font-size: 2rem; font-weight: 800; color: #f8fafc; margin: 10px 0; }
        
        .main-layout { display: grid; grid-template-columns: 1fr 400px; gap: 20px; }
        .panel { background: var(--card); border-radius: 16px; padding: 20px; border: 1px solid rgba(255,255,255,0.05); }
        .panel h2 { font-size: 1.2rem; color: #f8fafc; margin-top: 0; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 10px; }
        
        .bot-row { display: flex; align-items: center; gap: 15px; padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.02); transition: background 0.2s; }
        .bot-row:hover { background: rgba(255,255,255,0.02); }
        .level-badge { background: var(--secondary); color: white; padding: 4px 10px; border-radius: 8px; font-weight: 800; font-size: 0.8rem; }
        .role-badge { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; padding: 2px 6px; border-radius: 4px; }
        .role-commoner { background: #334155; color: #cbd5e1; }
        .role-whale { background: #1e3a8a; color: #93c5fd; }
        .role-reporter { background: #7c2d12; color: #fdba74; }
        .role-influencer { background: #4d1d6e; color: #d8b4fe; }
        
        .news-item { margin-bottom: 15px; padding-left: 10px; border-left: 3px solid var(--primary); }
        .news-header { font-size: 0.8rem; font-weight: 700; color: #f8fafc; margin-bottom: 4px; }
        .news-time { font-size: 0.7rem; color: #475569; }
        .news-content { font-size: 0.9rem; line-height: 1.4; color: #cbd5e1; }

        .xp-bg { width: 100%; height: 6px; background: rgba(255,255,255,0.05); border-radius: 3px; margin-top: 8px; overflow: hidden; }
        .xp-fill { height: 100%; background: linear-gradient(90deg, #38bdf8, #6366f1); }
        
        .btn-back { display: inline-block; padding: 10px 20px; background: rgba(255,255,255,0.05); color: white; text-decoration: none; border-radius: 12px; font-weight: 600; margin-bottom: 20px; transition: 0.2s; }
        .btn-back:hover { background: rgba(255,255,255,0.1); }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="btn-back">← Quay lại Dashboard</a>
        <div class="header">
            <h1>🧠 Bot Intelligence Center</h1>
            <div style="text-align: right">
                <div style="color: var(--primary); font-weight: 800;">STATUS: EVOLVING</div>
                <div style="font-size: 0.8rem;">Hệ thống trí tuệ xã hội đang vận hành</div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Cấp độ Trung bình</h3>
                <div class="value"><?= $stats['avg_level'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Số lượng Phóng viên</h3>
                <div class="value" style="color: var(--accent)"><?= $stats['roles']['reporter'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Đại gia (Whales)</h3>
                <div class="value" style="color: #4ade80"><?= $stats['roles']['whale'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Tổng XP Quân đoàn</h3>
                <div class="value" style="color: var(--secondary)"><?= number_format($stats['total_xp']) ?></div>
            </div>
        </div>

        <div class="main-layout">
            <div class="panel">
                <h2>🏆 Danh sách Top Cao thủ (Level & XP)</h2>
                <?php if (empty($stats['top_levels'])): ?>
                    <p style="text-align: center; padding: 40px;">Chưa có dữ liệu tiến hóa... 🧬</p>
                <?php else: ?>
                    <?php foreach ($stats['top_levels'] as $bot): ?>
                    <div class="bot-row">
                        <div class="level-badge">LV.<?= $bot['level'] ?></div>
                        <div style="flex: 1">
                            <div style="color: #f8fafc; font-weight: 700; font-size: 1.1rem;"><?= htmlspecialchars($bot['name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div style="color: #64748b; font-size: 0.75rem;">Mã định danh: <?= substr($bot['md5'], 0, 8) ?></div>
                            <div style="display: flex; gap: 8px; align-items: center; margin-top: 4px;">
                                <span class="role-badge role-<?= $bot['role'] ?>"><?= $bot['role'] ?></span>
                                <span style="font-size: 0.7rem;">Thắng: <?= $bot['wins'] ?> ván</span>
                                <span style="font-size: 0.7rem;">Tâm trạng: <?= $bot['mood'] ?></span>
                            </div>
                            <div class="xp-bg">
                                <div class="xp-fill" style="width: <?= min(100, ($bot['xp'] / ($bot['level'] * 100)) * 100) ?>%"></div>
                            </div>
                        </div>
                        <div style="text-align: right">
                            <div style="font-size: 1.1rem; font-weight: 800; color: #f8fafc;"><?= number_format($bot['xp']) ?></div>
                            <div style="font-size: 0.6rem; color: #475569; text-transform: uppercase;">Kinh nghiệm</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="panel">
                <h2>📡 Social News Feed (Live)</h2>
                <div style="max-height: 700px; overflow-y: auto; padding-right: 10px;">
                    <?php if (empty($newsFeed)): ?>
                        <p style="text-align: center; padding: 40px; color: #475569;">Chưa có bản tin nào được ghi nhận. Đang chờ Phóng viên tác nghiệp... 📡</p>
                    <?php else: ?>
                        <?php foreach ($newsFeed as $news): ?>
                        <div class="news-item">
                            <div class="news-header">@<?= htmlspecialchars($news['Name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="news-content"><?= htmlspecialchars($news['message'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="news-time"><?= date('H:i:s d/m', strtotime($news['created_at'])) ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
