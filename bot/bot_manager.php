<?php
/**
 * 🥚 Bot Manager v4.0 - Advanced Controller
 * Features: Dark Mode UI, Mass Spawn, Economy Tracking
 */
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../admin_helper.php';

$userId = (int)($_SESSION['Iduser'] ?? 0);
requireSuperAdmin($conn, $userId); // Chỉ Super Admin & Owner được vào

$env = file_exists(__DIR__ . '/../.env.php') ? require __DIR__ . '/../.env.php' : [];

// Handle Spawn Actions
$msg = '';
$msgType = 'success';

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action === 'spawn') {
        $count = isset($_GET['count']) ? max(1, (int)$_GET['count']) : 1;
        $count = min($count, 50); // Giới hạn 50 bot/lần
        
        $spawned = 0;
        $duplicates = 0;
        
        for ($i = 0; $i < $count; $i++) {
            // Lấy số tiếp theo
            $res = $conn->query("SELECT COUNT(*) as total FROM users WHERE Email REGEXP '^bot[0-9]+@'");
            $nextNumber = $res->fetch_assoc()['total'] + 1 + $i; // +$i vì query count không tăng ngay lập tức trong vòng lặp nếu ta dùng transaction/chưa commit
            
            // Fix better next number logic to avoid collision when mass spawning:
            // Lấy số lớn nhất hiện tại
            $maxRes = $conn->query("SELECT MAX(CAST(SUBSTRING(Email, 4, LOCATE('@', Email) - 4) AS UNSIGNED)) as max_num FROM users WHERE Email REGEXP '^bot[0-9]+@'");
            $maxNum = (int)($maxRes->fetch_assoc()['max_num'] ?? 0);
            $nextNumber = $maxNum + 1;
            
            $newName = "Bot " . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
            $email = "bot" . $nextNumber . "@gmail.com";
            $passText = $env['BOT_PASSWORD'] ?? '123456';
            $passHash = password_hash($passText, PASSWORD_DEFAULT);
            $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($newName) . "&background=random";
            
            // Check trùng
            $check = $conn->prepare("SELECT Iduser FROM users WHERE Email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $duplicates++;
                continue;
            }
            
            // Tạo bot
            $stmt = $conn->prepare("INSERT INTO users (Name, Email, Pass, Money, ImageURL) VALUES (?, ?, ?, 1000000, ?)");
            $stmt->bind_param("ssss", $newName, $email, $passHash, $avatarUrl);
            if ($stmt->execute()) {
                $spawned++;
            }
        }
        
        $msg = "Đã sinh thành công $spawned bot.";
        if ($duplicates > 0) $msg .= " ($duplicates bot bị trùng email).";
        header("Location: index.php?msg=" . urlencode($msg));
        exit;
    }
}

if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}

// Fetch Stats
$stats = [];
$stats['total'] = (int)($conn->query("SELECT COUNT(*) as c FROM users WHERE Email REGEXP '^bot[0-9]+@'")->fetch_assoc()['c']);
$stats['money'] = (float)($conn->query("SELECT SUM(Money) as c FROM users WHERE Email REGEXP '^bot[0-9]+@'")->fetch_assoc()['c']);

// Top Bots
$topBots = $conn->query("SELECT Name, Email, Money FROM users WHERE Email REGEXP '^bot[0-9]+@' ORDER BY Money DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bot Army Controller</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root {
    --bg: #07090f;
    --surface: #0e111a;
    --surface2: #141825;
    --border: rgba(255,255,255,0.07);
    --border2: rgba(255,255,255,0.12);
    --text: #e8eaf0;
    --muted: #636b80;
    --blue: #4f8dff;
    --cyan: #22d3ee;
    --green: #34d399;
    --amber: #fbbf24;
    --red: #fb7185;
    --purple: #a78bfa;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    padding: 40px;
    background-image: 
        radial-gradient(circle at 15% 50%, rgba(79, 141, 255, 0.05), transparent 25%),
        radial-gradient(circle at 85% 30%, rgba(167, 139, 250, 0.05), transparent 25%);
}
.wrapper { max-width: 1100px; margin: 0 auto; position: relative; z-index: 1; }

.header {
    display: flex; justify-content: space-between; align-items: flex-end;
    margin-bottom: 40px; padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
}
.header h1 { font-size: 32px; font-weight: 800; letter-spacing: -1px; }
.header h1 span { color: var(--blue); }
.header p { color: var(--muted); margin-top: 5px; font-size: 14px; }

.btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 24px; border-radius: 12px;
    font-size: 14px; font-weight: 600; text-decoration: none;
    transition: all 0.2s; cursor: pointer; border: none;
}
.btn-ghost { background: var(--surface2); color: var(--text); border: 1px solid var(--border2); }
.btn-ghost:hover { background: var(--surface); border-color: var(--blue); }
.btn-primary { background: linear-gradient(135deg, #4f8dff, #3b82f6); color: #fff; box-shadow: 0 4px 15px rgba(79, 141, 255, 0.3); }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(79, 141, 255, 0.4); }
.btn-danger { background: rgba(251, 113, 133, 0.1); color: var(--red); border: 1px solid rgba(251, 113, 133, 0.3); }

.alert {
    padding: 16px 20px; border-radius: 12px; margin-bottom: 24px;
    background: rgba(52, 211, 153, 0.1); border: 1px solid rgba(52, 211, 153, 0.2);
    color: var(--green); display: flex; align-items: center; gap: 10px; font-weight: 500;
}

.stats-grid {
    display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px;
}
.stat-card {
    background: var(--surface); border: 1px solid var(--border); border-radius: 20px;
    padding: 30px; display: flex; align-items: center; gap: 20px;
}
.stat-icon {
    width: 60px; height: 60px; border-radius: 16px;
    display: flex; align-items: center; justify-content: center; font-size: 24px;
}
.stat-card.blue .stat-icon { background: rgba(79, 141, 255, 0.1); color: var(--blue); }
.stat-card.amber .stat-icon { background: rgba(251, 191, 36, 0.1); color: var(--amber); }
.stat-label { font-size: 13px; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-bottom: 5px; }
.stat-val { font-size: 36px; font-weight: 800; font-family: 'Space Mono', monospace; }

.section {
    background: var(--surface); border: 1px solid var(--border); border-radius: 20px;
    padding: 30px; margin-bottom: 30px;
}
.section h2 { font-size: 18px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

.controls-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;
}
.control-card {
    background: var(--surface2); border: 1px solid var(--border2); border-radius: 16px;
    padding: 20px; text-align: center; transition: 0.3s;
}
.control-card:hover { border-color: var(--blue); transform: translateY(-3px); }
.control-card i { font-size: 32px; color: var(--purple); margin-bottom: 15px; display: block; }
.control-card h3 { font-size: 16px; margin-bottom: 15px; }

table { width: 100%; border-collapse: collapse; }
th, td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border); }
th { font-size: 12px; color: var(--muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
tr:hover td { background: rgba(255, 255, 255, 0.02); }
td { font-size: 14px; }
.money-cell { font-family: 'Space Mono', monospace; font-weight: 600; color: var(--amber); }
.rank-badge {
    width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-weight: bold; font-size: 12px;
}
.rank-1 { background: linear-gradient(135deg, #facc15, #eab308); color: #000; box-shadow: 0 0 15px rgba(234, 179, 8, 0.4); }
.rank-2 { background: linear-gradient(135deg, #94a3b8, #64748b); color: #fff; }
.rank-3 { background: linear-gradient(135deg, #b45309, #78350f); color: #fff; }
.rank-other { background: var(--surface2); color: var(--muted); }

</style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <div>
            <h1>Bot Army <span>Controller</span></h1>
            <p>Quản lý và sinh sản tự động các AI Bot cho hệ thống sinh thái.</p>
        </div>
        <div>
            <a href="../admin_dashboard.php" class="btn btn-ghost"><i class="fa fa-arrow-left"></i> Về Dashboard</a>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert">
            <i class="fa fa-check-circle"></i> <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fa fa-robot"></i></div>
            <div>
                <div class="stat-label">Tổng Quy Mô Quân Đoàn</div>
                <div class="stat-val"><?= number_format($stats['total']) ?></div>
            </div>
        </div>
        <div class="stat-card amber">
            <div class="stat-icon"><i class="fa fa-coins"></i></div>
            <div>
                <div class="stat-label">Tổng Tài Sản Đang Giữ (GTLM)</div>
                <div class="stat-val"><?= number_format($stats['money']) ?></div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2><i class="fa fa-server"></i> Máy Chế Tạo Bot (Spawn Engine)</h2>
        <div class="controls-grid">
            <div class="control-card">
                <i class="fa fa-egg" style="color: var(--green)"></i>
                <h3>Sinh 1 Bot</h3>
                <a href="?action=spawn&count=1" class="btn btn-ghost" style="width: 100%; justify-content: center;">Bắt Đầu</a>
            </div>
            <div class="control-card">
                <i class="fa fa-cubes" style="color: var(--blue)"></i>
                <h3>Sinh 10 Bot</h3>
                <a href="?action=spawn&count=10" class="btn btn-ghost" style="width: 100%; justify-content: center;">Bắt Đầu</a>
            </div>
            <div class="control-card" style="border-color: rgba(167, 139, 250, 0.3); background: rgba(167, 139, 250, 0.05);">
                <i class="fa fa-industry" style="color: var(--purple)"></i>
                <h3>Sản Xuất Hàng Loạt (50)</h3>
                <a href="?action=spawn&count=50" class="btn btn-primary" style="width: 100%; justify-content: center;" onclick="return confirm('Sản xuất 50 bot có thể tốn vài giây xử lý. Bạn có chắc chắn?')">Kích Hoạt Engine</a>
            </div>
        </div>
    </div>

    <div class="section">
        <h2><i class="fa fa-trophy" style="color: var(--amber)"></i> Top 10 Cá Mập (Richest Bots)</h2>
        <table>
            <thead>
                <tr>
                    <th width="80">Hạng</th>
                    <th>Tên Bot</th>
                    <th>Email Định Danh</th>
                    <th style="text-align: right;">Tài Sản (GTLM)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topBots as $index => $bot): 
                    $rank = $index + 1;
                    $rankClass = 'rank-other';
                    if ($rank == 1) $rankClass = 'rank-1';
                    else if ($rank == 2) $rankClass = 'rank-2';
                    else if ($rank == 3) $rankClass = 'rank-3';
                ?>
                <tr>
                    <td><div class="rank-badge <?= $rankClass ?>"><?= $rank ?></div></td>
                    <td style="font-weight: 600; color: #fff;"><?= htmlspecialchars($bot['Name']) ?></td>
                    <td style="color: var(--muted);"><?= htmlspecialchars($bot['Email']) ?></td>
                    <td style="text-align: right;" class="money-cell"><?= number_format($bot['Money']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($topBots)): ?>
                <tr><td colspan="4" style="text-align: center; color: var(--muted);">Chưa có bot nào trong hệ thống.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
