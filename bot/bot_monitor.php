<?php
session_start();
require_once __DIR__ . '/../db_connect.php';
$config = require_once __DIR__ . '/config.php';

$botEmails = $config['bot_emails'];
$cookieDir = __DIR__ . '/sessions/';

// 1. Lấy thông tin cơ bản và trang bị từ DB
$emailsStr = "'" . implode("','", $botEmails) . "'";
$sql = "SELECT u.*, 
        c.name as cursor_name, c.cursor_image as c_img,
        af.frame_name as frame_name, af.ImageURL as f_img,
        cf.frame_name as chat_frame_name,
        ach.name as title_name
        FROM users u 
        LEFT JOIN cursors c ON u.current_cursor_id = c.id
        LEFT JOIN avatar_frames af ON u.avatar_frame_id = af.id
        LEFT JOIN chat_frames cf ON u.chat_frame_id = cf.id
        LEFT JOIN achievements ach ON u.active_title_id = ach.id
        WHERE u.Email IN ($emailsStr)";
$result = $conn->query($sql);
$dbBots = [];
while ($row = $result->fetch_assoc()) {
    $dbBots[$row['Email']] = $row;
}

$botData = [];
foreach ($botEmails as $email) {
    $dbInfo = $dbBots[$email] ?? null;
    if (!$dbInfo) continue;

    $userId = $dbInfo['Iduser'];
    $botMd5 = md5($email);
    $sFile = $cookieDir . $botMd5 . ".state.json";
    $state = file_exists($sFile) ? json_decode(file_get_contents($sFile), true) : null;

    // 2. Lấy kho đồ (Inventory)
    $inventory = [];
    // Cursors
    $invSql = "SELECT c.name FROM user_cursors uc JOIN cursors c ON uc.cursor_id = c.id WHERE uc.user_id = $userId";
    $invRes = $conn->query($invSql);
    while($r = $invRes->fetch_assoc()) $inventory['cursors'][] = $r['name'];
    
    // Frames
    $invSql = "SELECT f.frame_name FROM user_avatar_frames uaf JOIN avatar_frames f ON uaf.avatar_frame_id = f.id WHERE uaf.user_id = $userId";
    $invRes = $conn->query($invSql);
    while($r = $invRes->fetch_assoc()) $inventory['frames'][] = $r['frame_name'];

    // Chat Frames
    $invSql = "SELECT f.frame_name FROM user_chat_frames ucf JOIN chat_frames f ON ucf.chat_frame_id = f.id WHERE ucf.user_id = $userId";
    $invRes = $conn->query($invSql);
    while($r = $invRes->fetch_assoc()) $inventory['chat_frames'][] = $r['frame_name'];

    // 3. Lấy lịch sử 10 ván gần nhất
    $history = [];
    $histSql = "SELECT * FROM bot_transactions WHERE user_id = $userId ORDER BY created_at DESC LIMIT 10";
    $histRes = $conn->query($histSql);
    while($r = $histRes->fetch_assoc()) $history[] = $r;
    
    $botData[] = [
        'id' => $userId,
        'email' => $email,
        'name' => $dbInfo['Name'],
        'money' => $dbInfo['Money'],
        'avatar' => $dbInfo['ImageURL'] ?? 'chuot.png',
        'equipped' => [
            'cursor' => $dbInfo['cursor_name'] ?? 'Mặc định',
            'frame' => $dbInfo['frame_name'] ?? 'Mặc định',
            'title' => $dbInfo['title_name'] ?? 'Dân thường',
        ],
        'inventory' => $inventory,
        'history' => $history,
        'state' => $state,
        'last_active' => file_exists($sFile) ? filemtime($sFile) : 0
    ];
}

usort($botData, function($a, $b) {
    return $b['last_active'] <=> $a['last_active'];
});

$totalMoney = array_sum(array_column($botData, 'money'));
$activeCount = 0;
foreach($botData as $b) { if(time() - $b['last_active'] < 300) $activeCount++; }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>🛡️ Bot Army Intelligence Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #020617;
            --card-bg: #1e293b;
            --primary: #00f2fe;
            --secondary: #4facfe;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --glass: rgba(255, 255, 255, 0.03);
            --border: rgba(255, 255, 255, 0.1);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text);
            margin: 0;
            padding: 20px;
            background-image: radial-gradient(circle at 50% 50%, #1e293b 0%, #020617 100%);
            min-height: 100vh;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: var(--glass);
            backdrop-filter: blur(15px);
            padding: 25px;
            border-radius: 20px;
            border: 1px solid var(--border);
        }

        .header h1 { 
            margin: 0; 
            font-size: 28px;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--glass);
            padding: 25px;
            border-radius: 20px;
            text-align: center;
            border: 1px solid var(--border);
        }

        .stat-card h3 { color: var(--text-muted); font-size: 12px; margin: 0; text-transform: uppercase; }
        .stat-card p { font-size: 28px; font-weight: 700; margin: 10px 0 0; }

        .bot-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .bot-card {
            background: var(--card-bg);
            border-radius: 24px;
            padding: 25px;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }

        .bot-card:hover { border-color: var(--primary); transform: translateY(-5px); }

        .bot-header { display: flex; gap: 15px; margin-bottom: 15px; align-items: center; }
        .bot-avatar { width: 60px; height: 60px; border-radius: 15px; border: 2px solid var(--border); }
        .bot-meta h2 { margin: 0; font-size: 18px; }
        
        .badge { font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 700; margin-top: 5px; display: inline-block; }
        .badge-primary { background: rgba(0, 242, 254, 0.1); color: var(--primary); }
        .badge-success { background: rgba(16, 185, 129, 0.1); color: var(--success); }

        .info-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }

        .info-title { font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; }
        
        .equipped-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; }
        .eq-item { font-size: 12px; background: rgba(0,0,0,0.2); padding: 8px; border-radius: 8px; }
        .eq-item span { display: block; font-size: 10px; color: var(--text-muted); }

        .history-list { font-size: 11px; list-style: none; padding: 0; margin: 0; }
        .history-item { 
            display: flex; 
            justify-content: space-between; 
            padding: 5px 0; 
            border-bottom: 1px solid rgba(255,255,255,0.02);
        }
        .hist-win { color: var(--success); }
        .hist-lose { color: var(--danger); }

        .inventory-tags { display: flex; flex-wrap: wrap; gap: 5px; }
        .tag { font-size: 10px; background: var(--glass); border: 1px solid var(--border); padding: 2px 6px; border-radius: 4px; }

        .btn-refresh { background: var(--primary); color: var(--bg-dark); border: none; padding: 10px 20px; border-radius: 10px; font-weight: 700; cursor: pointer; }
    </style>
</head>
<body>

    <div class="header">
        <div>
            <h1>🛡️ Bot Army Intelligence</h1>
            <p style="margin: 5px 0 0; color: var(--text-muted);">Giám sát trang bị, kho đồ và lịch sử thực chiến</p>
        </div>
        <button class="btn-refresh" onclick="location.reload()">🔄 Làm mới dữ liệu</button>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Tổng quân số</h3>
            <p><?= count($botData) ?></p>
        </div>
        <div class="stat-card">
            <h3>Đang trực chiến</h3>
            <p style="color: var(--success);"><?= $activeCount ?></p>
        </div>
        <div class="stat-card">
            <h3>Tổng ngân quỹ</h3>
            <p style="color: var(--primary);"><?= number_format($totalMoney, 0, ',', '.') ?></p>
        </div>
    </div>

    <div class="bot-grid">
        <?php foreach ($botData as $bot): 
            $isOnline = (time() - $bot['last_active'] < 300);
            $state = $bot['state'];
        ?>
            <div class="bot-card">
                <div class="bot-header">
                    <img src="../<?= htmlspecialchars($bot['avatar']) ?>" class="bot-avatar">
                    <div class="bot-meta">
                        <h2>
                            <span style="color: <?= $isOnline ? 'var(--success)' : 'var(--text-muted)' ?>">●</span>
                            <?= htmlspecialchars($bot['name']) ?>
                        </h2>
                        <span class="badge badge-primary">🎭 <?= ucfirst($state['personality'] ?? 'Bình thường') ?></span>
                        <span class="badge badge-success">💰 <?= number_format($bot['money'], 0, ',', '.') ?> GTLM</span>
                    </div>
                </div>

                <!-- Section: Equipped Items -->
                <div class="info-section">
                    <span class="info-title">⚔️ VẬT PHẨM ĐANG SỬ DỤNG</span>
                    <div class="equipped-grid">
                        <div class="eq-item"><span>Con trỏ:</span> <?= $bot['equipped']['cursor'] ?></div>
                        <div class="eq-item"><span>Khung ảnh:</span> <?= $bot['equipped']['frame'] ?></div>
                        <div class="eq-item" style="grid-column: span 2;"><span>Danh hiệu:</span> <?= $bot['equipped']['title'] ?></div>
                    </div>
                </div>

                <!-- Section: Inventory -->
                <div class="info-section">
                    <span class="info-title">🎒 KHO ĐỒ (INVENTORY)</span>
                    <div class="inventory-tags">
                        <?php 
                        $items = array_merge($bot['inventory']['cursors'] ?? [], $bot['inventory']['frames'] ?? [], $bot['inventory']['chat_frames'] ?? []);
                        if (empty($items)) echo '<span style="font-size:10px; color:var(--text-muted)">Kho đồ trống</span>';
                        foreach($items as $item): ?>
                            <span class="tag"><?= htmlspecialchars($item) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Section: Game History -->
                <div class="info-section">
                    <span class="info-title">📜 LỊCH SỬ 10 VÁN GẦN NHẤT</span>
                    <ul class="history-list">
                        <?php if (empty($bot['history'])): ?>
                            <li style="color:var(--text-muted)">Chưa có dữ liệu ván đấu</li>
                        <?php endif; ?>
                        <?php foreach($bot['history'] as $h): ?>
                            <li class="history-item">
                                <span><?= strtoupper($h['game']) ?> (<?= $h['type'] == 'win' ? 'Thắng' : 'Thua' ?>)</span>
                                <span class="<?= $h['type'] == 'win' ? 'hist-win' : 'hist-lose' ?>">
                                    <?= ($h['type'] == 'win' ? '+' : '-') . number_format($h['amount'], 0) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div style="font-size: 9px; color: var(--text-muted); margin-top: 15px; text-align: right;">
                    Cập nhật lần cuối: <?= $bot['last_active'] ? date('H:i:s', $bot['last_active']) : 'N/A' ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        // Auto refresh every 60 seconds to save server resources but keep it fresh
        setTimeout(() => location.reload(), 60000);
    </script>
</body>
</html>
