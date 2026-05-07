<?php
/**
 * Bot Army Status Dashboard
 * Displays real-time status of all bots.
 */

require_once __DIR__ . '/../db_connect.php';
$config = require_once __DIR__ . '/config.php';

$cookieDir = __DIR__ . '/sessions/';
$bots = $config['bot_emails'];

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bot Army Dashboard - Blackjack Royale</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Outfit:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00f2fe;
            --secondary: #4facfe;
            --dark: #0f172a;
            --darker: #020617;
            --glass: rgba(255, 255, 255, 0.05);
            --border: rgba(255, 255, 255, 0.1);
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        body {
            background-color: var(--darker);
            color: #e2e8f0;
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 20px;
            background-image: radial-gradient(circle at 50% 50%, #1e293b 0%, #020617 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: var(--glass);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            border-radius: 16px;
        }

        h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 24px;
            margin: 0;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--glass);
            border: 1px solid var(--border);
            padding: 20px;
            border-radius: 16px;
            text-align: center;
        }

        .stat-card h3 {
            margin: 0;
            font-size: 14px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: 700;
            margin-top: 10px;
            color: #fff;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        th {
            text-align: left;
            padding: 15px;
            color: #94a3b8;
            font-weight: 600;
            font-size: 14px;
        }

        tr.bot-row {
            background: var(--glass);
            transition: transform 0.2s, background 0.2s;
        }

        tr.bot-row:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: scale(1.01);
        }

        td {
            padding: 15px;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        td:first-child {
            border-left: 1px solid var(--border);
            border-radius: 12px 0 0 12px;
        }

        td:last-child {
            border-right: 1px solid var(--border);
            border-radius: 0 12px 12px 0;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-online { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .status-offline { background: rgba(239, 68, 68, 0.2); color: var(--danger); }

        .bot-name { font-weight: 600; color: #fff; }
        .bot-email { font-size: 12px; color: #64748b; }

        .balance { color: var(--primary); font-weight: 700; }
        .win-count { color: var(--success); }
        .lose-count { color: var(--danger); }

        .refresh-btn {
            background: linear-gradient(to right, var(--primary), var(--secondary));
            color: #000;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🛡️ Bot Army Command Center</h1>
            <a href="bot_status.php" class="refresh-btn">Làm mới dữ liệu</a>
        </header>

        <?php
        $botData = [];
        $totalMoney = 0;
        $totalWins = 0;
        $totalLosses = 0;
        $activeBots = 0;

        foreach ($bots as $email) {
            $botMd5 = md5($email);
            $sFile = $cookieDir . $botMd5 . ".state.json";
            $state = file_exists($sFile) ? json_decode(file_get_contents($sFile), true) : [];
            
            // Get current balance from DB
            $stmt = $conn->prepare("SELECT Name, Money FROM users WHERE Email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();
            $user = $res->fetch_assoc();
            $stmt->close();

            $isOnline = (isset($state['last_chat_time']) && (time() - $state['last_chat_time'] < 3600)); // Active in last hour
            if ($isOnline) $activeBots++;

            $wins = $state['stats']['wins'] ?? 0;
            $losses = $state['stats']['losses'] ?? 0;
            $totalWins += $wins;
            $totalLosses += $losses;
            $totalMoney += ($user['Money'] ?? 0);

            $botData[] = [
                'email' => $email,
                'name' => $user['Name'] ?? 'Unknown',
                'money' => $user['Money'] ?? 0,
                'wins' => $wins,
                'losses' => $losses,
                'is_online' => $isOnline,
                'last_msg' => $state['last_message'] ?? 'N/A'
            ];
        }
        ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Bot Hoạt Động</h3>
                <div class="value"><?php echo $activeBots; ?> / <?php echo count($bots); ?></div>
            </div>
            <div class="stat-card">
                <h3>Tổng Tài Sản</h3>
                <div class="value balance"><?php echo number_format($totalMoney); ?> GTLM</div>
            </div>
            <div class="stat-card">
                <h3>Tổng Thắng/Thua</h3>
                <div class="value">
                    <span class="win-count"><?php echo $totalWins; ?></span> / <span class="lose-count"><?php echo $totalLosses; ?></span>
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Bot / Email</th>
                    <th>Trạng thái</th>
                    <th>Số dư</th>
                    <th>Thắng</th>
                    <th>Thua</th>
                    <th>Tin nhắn cuối</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($botData as $bot): ?>
                <tr class="bot-row">
                    <td>
                        <div class="bot-name"><?php echo htmlspecialchars($bot['name']); ?></div>
                        <div class="bot-email"><?php echo htmlspecialchars($bot['email']); ?></div>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $bot['is_online'] ? 'status-online' : 'status-offline'; ?>">
                            <?php echo $bot['is_online'] ? 'Hoạt động' : 'Ngoại tuyến'; ?>
                        </span>
                    </td>
                    <td class="balance"><?php echo number_format($bot['money']); ?></td>
                    <td class="win-count"><?php echo $bot['wins']; ?></td>
                    <td class="lose-count"><?php echo $bot['losses']; ?></td>
                    <td style="font-size: 12px; color: #94a3b8;"><?php echo htmlspecialchars($bot['last_msg']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
