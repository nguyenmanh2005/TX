<?php
session_start();
require_once 'db_connect.php';
require_once 'admin_helper.php';

if (!isset($_SESSION['Iduser'])) { header('Location: login.php'); exit(); }
$userId = (int)$_SESSION['Iduser'];
if (!isAdmin($conn, $userId)) { header("Location: 403.php"); exit(); }

// --- 1. Tổng quan Doanh thu (GTLM Sinks) ---
$revenueStats = [
    'total_burned' => 0,
    'marketplace_fees' => 0,
    'crafting_costs' => 0,
    'guild_fees' => 0,
    'battle_pass' => 0,
    'vip_purchases' => 0
];

// Marketplace Fees (5% mỗi giao dịch thành công)
$res = $conn->query("SELECT SUM(price * 0.05) as fees FROM marketplace_listings WHERE status = 'sold'");
$revenueStats['marketplace_fees'] = (float)($res->fetch_assoc()['fees'] ?? 0);

// Crafting Costs (Phí từ crafting_logs)
$res = $conn->query("SELECT SUM(gtlm_spent) as spent FROM crafting_logs");
$revenueStats['crafting_costs'] = (float)($res->fetch_assoc()['spent'] ?? 0);

// Guild Fees (Phí tạo Guild)
$res = $conn->query("SELECT COUNT(*) * 500000 as fees FROM guilds");
$revenueStats['guild_fees'] = (float)($res->fetch_assoc()['fees'] ?? 0);

// Battle Pass Premium Purchases
$res = $conn->query("SELECT COUNT(*) * 1000000 as revenue FROM bp_stats WHERE has_premium = 1");
$revenueStats['battle_pass'] = (float)($res->fetch_assoc()['revenue'] ?? 0);

$revenueStats['total_burned'] = $revenueStats['marketplace_fees'] + $revenueStats['crafting_costs'] + $revenueStats['guild_fees'] + $revenueStats['battle_pass'];

// --- 2. Phân tích Game Revenue ---
$gameRevenue = [];
$res = $conn->query("SELECT game_name, SUM(bet_amount - win_amount) as house_profit FROM game_history GROUP BY game_name ORDER BY house_profit DESC");
while($row = $res->fetch_assoc()) {
    $gameRevenue[] = $row;
}

// --- 3. ARPU & Conversion ---
$totalUsers = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$arpu = $totalUsers > 0 ? $revenueStats['total_burned'] / $totalUsers : 0;

$vipUsers = $conn->query("SELECT COUNT(*) FROM user_vip WHERE vip_level > 1")->fetch_row()[0];
$conversionRate = $totalUsers > 0 ? ($vipUsers / $totalUsers) * 100 : 0;

// --- 4. Churn Prediction (Người chơi không hoạt động > 7 ngày) ---
$churnedUsers = $conn->query("SELECT COUNT(*) FROM users WHERE last_active < DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_row()[0];
$churnRate = $totalUsers > 0 ? ($churnedUsers / $totalUsers) * 100 : 0;

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>GTLM Sink & Revenue Analytics</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #07090f;
            --surface: #0e111a;
            --surface2: #141825;
            --primary: #4f8dff;
            --accent: #22d3ee;
            --text: #e8eaf0;
            --muted: #636b80;
            --red: #fb7185;
            --green: #34d399;
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); padding: 40px; min-height: 100vh; }
        .container { max-width: 1200px; margin: 0 auto; position: relative; z-index: 1; }
        h1 { font-size: 28px; margin-bottom: 30px; display: flex; align-items: center; gap: 15px; letter-spacing: -0.5px; }
        h1 span { color: var(--primary); }
        
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 40px; }
        .card { background: var(--surface); padding: 25px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); transition: transform 0.2s; }
        .card:hover { transform: translateY(-3px); border-color: rgba(255,255,255,0.1); }
        .card-label { font-size: 11px; color: var(--muted); text-transform: uppercase; margin-bottom: 10px; letter-spacing: 1px; font-weight: 600; }
        .card-value { font-size: 26px; font-weight: 700; font-family: 'Space Mono', monospace; color: var(--primary); }
        
        .main-layout { display: grid; grid-template-columns: 1.5fr 1fr; gap: 25px; }
        .box { background: var(--surface); border-radius: 20px; padding: 30px; border: 1px solid rgba(255,255,255,0.05); }
        h2 { font-size: 16px; margin-bottom: 20px; color: var(--text); display: flex; align-items: center; gap: 10px; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; color: var(--muted); font-size: 11px; text-transform: uppercase; padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        td { padding: 15px 12px; border-bottom: 1px solid rgba(255,255,255,0.03); font-family: 'Space Mono', monospace; font-size: 14px; }
        
        .progress-item { margin-bottom: 25px; }
        .progress-info { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 10px; color: var(--text); }
        .progress-info span:last-child { color: var(--primary); font-weight: 700; }
        .progress-track { height: 8px; background: var(--surface2); border-radius: 10px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary), var(--accent)); border-radius: 10px; }

        .btn-back { display: inline-flex; align-items: center; gap: 8px; color: var(--muted); text-decoration: none; font-size: 13px; margin-bottom: 20px; transition: color 0.2s; }
        .btn-back:hover { color: var(--primary); }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin_analytics.php" class="btn-back"><i class="fas fa-arrow-left"></i> Quay lại Analytics chính</a>
        <h1><i class="fas fa-coins" style="color: var(--primary);"></i> Revenue & <span>GTLM Sink</span> Breakdown</h1>

        <div class="grid">
            <div class="card">
                <div class="card-label">Tổng GTLM Đã Đốt</div>
                <div class="card-value"><?= number_format($revenueStats['total_burned']) ?></div>
            </div>
            <div class="card">
                <div class="card-label">ARPU (GTLM/User)</div>
                <div class="card-value"><?= number_format($arpu, 1) ?></div>
            </div>
            <div class="card">
                <div class="card-label">Chuyển đổi VIP</div>
                <div class="card-value"><?= number_format($conversionRate, 1) ?>%</div>
            </div>
            <div class="card">
                <div class="card-label">Dự đoán Churn</div>
                <div class="card-value" style="color: var(--red);"><?= number_format($churnRate, 1) ?>%</div>
            </div>
        </div>

        <div class="main-layout">
            <div class="box">
                <h2><i class="fas fa-chart-line"></i> Lợi nhuận Hệ thống theo Game</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Loại Game</th>
                            <th>Lợi nhuận ròng</th>
                            <th>Đóng góp (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($gameRevenue as $gr): 
                            $percent = $revenueStats['total_burned'] > 0 ? ($gr['house_profit'] / $revenueStats['total_burned']) * 100 : 0;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($gr['game_name']) ?></td>
                            <td style="color: var(--green);"><?= number_format($gr['house_profit']) ?></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div class="progress-track" style="width: 80px;">
                                        <div class="progress-fill" style="width: <?= min(100, max(0, $percent)) ?>%;"></div>
                                    </div>
                                    <?= number_format($percent, 1) ?>%
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="box">
                <h2><i class="fas fa-fire"></i> Phân bổ Nguồn Đốt GTLM</h2>
                <div class="progress-item">
                    <div class="progress-info"><span>Phí Giao Dịch Chợ (5%)</span> <span><?= number_format($revenueStats['marketplace_fees']) ?></span></div>
                    <div class="progress-track"><div class="progress-fill" style="width: <?= $revenueStats['total_burned'] > 0 ? ($revenueStats['marketplace_fees']/$revenueStats['total_burned']*100) : 0 ?>%;"></div></div>
                </div>
                <div class="progress-item">
                    <div class="progress-info"><span>Chi phí Chế Tác</span> <span><?= number_format($revenueStats['crafting_costs']) ?></span></div>
                    <div class="progress-track"><div class="progress-fill" style="width: <?= $revenueStats['total_burned'] > 0 ? ($revenueStats['crafting_costs']/$revenueStats['total_burned']*100) : 0 ?>%;"></div></div>
                </div>
                <div class="progress-item">
                    <div class="progress-info"><span>Phí Thành Lập Guild</span> <span><?= number_format($revenueStats['guild_fees']) ?></span></div>
                    <div class="progress-track"><div class="progress-fill" style="width: <?= $revenueStats['total_burned'] > 0 ? ($revenueStats['guild_fees']/$revenueStats['total_burned']*100) : 0 ?>%;"></div></div>
                </div>
                <div class="progress-item">
                    <div class="progress-info"><span>Nâng Cấp Battle Pass</span> <span><?= number_format($revenueStats['battle_pass']) ?></span></div>
                    <div class="progress-track"><div class="progress-fill" style="width: <?= $revenueStats['total_burned'] > 0 ? ($revenueStats['battle_pass']/$revenueStats['total_burned']*100) : 0 ?>%;"></div></div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
