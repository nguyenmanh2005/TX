<?php
session_start();
require 'db_connect.php';

// Kiểm tra quyền admin
require_once 'admin_helper.php';
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}
$currentUserId = (int)$_SESSION['Iduser'];
// Kiểm tra quyền Super Admin (Role >= 2)
if (!isSuperAdmin($conn, $currentUserId)) {
    header("Location: Shared/403/403.php");
    exit();
}

// Lấy tổng GDP (Tổng GTLM của tất cả người chơi)
$resGDP = $conn->query("SELECT SUM(Money) as total_gdp, COUNT(*) as total_users FROM users");
$gdpData = $resGDP->fetch_assoc();
$totalGDP = $gdpData['total_gdp'] ?? 0;
$totalUsers = $gdpData['total_users'] ?? 0;

// Tính trung bình GTLM mỗi user
$avgGDP = $totalUsers > 0 ? $totalGDP / $totalUsers : 0;

// Lấy top 10 tỷ phú (hút nhiều GTLM nhất)
$resTop = $conn->query("SELECT Name, Money FROM users ORDER BY Money DESC LIMIT 10");
$topBillionaires = [];
while ($r = $resTop->fetch_assoc()) {
    $topBillionaires[] = $r;
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Admin Economy Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #1a1a2e; color: #fff; margin: 0; padding: 20px; }
        .dashboard-header { text-align: center; margin-bottom: 30px; }
        .dashboard-header h1 { color: #4facfe; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: #16213e; padding: 20px; border-radius: 12px; border-left: 5px solid #e94560; box-shadow: 0 4px 15px rgba(0,0,0,0.3); text-align: center; }
        .stat-card h3 { margin: 0 0 10px 0; color: #a2a2bd; font-size: 16px; }
        .stat-card .value { font-size: 28px; font-weight: bold; color: #fff; }
        .chart-container { background: #16213e; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); max-width: 800px; margin: 0 auto 40px; }
        .table-container { background: #16213e; padding: 20px; border-radius: 12px; max-width: 800px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        th { color: #4facfe; }
        .nav-btn { display: inline-block; padding: 10px 20px; background: #4facfe; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold; margin-bottom: 20px; }
        .nav-btn:hover { background: #00f2fe; }
    </style>
</head>
<body>
    <a href="admin_dashboard.php" class="nav-btn"><i class="fas fa-arrow-left"></i> Quay lại Admin</a>
    <div class="dashboard-header">
        <h1>📊 SERVER ECONOMY DASHBOARD</h1>
        <p>Quản lý dòng GTLM và lạm phát toàn máy chủ</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card" style="border-left-color: #ffd700;">
            <h3>Tổng GDP Server</h3>
            <div class="value"><?= number_format($totalGDP) ?> GTLM</div>
        </div>
        <div class="stat-card" style="border-left-color: #4facfe;">
            <h3>Tổng Số Tài Khoản</h3>
            <div class="value"><?= number_format($totalUsers) ?></div>
        </div>
        <div class="stat-card" style="border-left-color: #e94560;">
            <h3>Trung Bình / Người</h3>
            <div class="value"><?= number_format($avgGDP) ?> GTLM</div>
        </div>
    </div>

    <div class="chart-container">
        <h3 style="text-align: center; margin-bottom: 20px;">Top 10 Phú Hào Server (Chiếm GDP)</h3>
        <canvas id="wealthChart"></canvas>
    </div>

    <div class="table-container">
        <h3>🏆 Bảng Phong Thần (Top Tài Sản)</h3>
        <table>
            <tr>
                <th>Hạng</th>
                <th>Người Chơi</th>
                <th>Tài Sản (GTLM)</th>
                <th>% Toàn Server</th>
            </tr>
            <?php foreach ($topBillionaires as $index => $u): ?>
                <tr>
                    <td>#<?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($u['Name']) ?></td>
                    <td style="color: #ffd700; font-weight: bold;"><?= number_format($u['Money']) ?></td>
                    <td><?= $totalGDP > 0 ? round(($u['Money'] / $totalGDP) * 100, 2) : 0 ?>%</td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <script>
        const ctx = document.getElementById('wealthChart').getContext('2d');
        
        // Prepare data
        const labels = <?= json_encode(array_column($topBillionaires, 'Name')) ?>;
        const data = <?= json_encode(array_column($topBillionaires, 'Money')) ?>;
        const total = <?= $totalGDP ?>;
        
        // Sum top 10
        const top10Sum = data.reduce((a, b) => parseFloat(a) + parseFloat(b), 0);
        const others = total - top10Sum;
        
        if (others > 0) {
            labels.push('Còn lại (Toàn Server)');
            data.push(others);
        }

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                        '#FF9F40', '#E7E9ED', '#8AC926', '#1982C4', '#6A4C93',
                        '#2e2e48' // Color for 'Others'
                    ],
                    borderWidth: 1,
                    borderColor: '#1a1a2e'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'right', labels: { color: '#fff' } }
                }
            }
        });
    </script>
</body>
</html>
