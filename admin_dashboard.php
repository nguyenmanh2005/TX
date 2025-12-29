<?php
session_start();
require_once 'db_connect.php';

// Helpers
function tableExists(mysqli $conn, string $table): bool
{
    $safeTable = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    return $result && $result->num_rows > 0;
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $safeTable = $conn->real_escape_string($table);
    $safeCol = $conn->real_escape_string($column);
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$safeTable}' AND COLUMN_NAME = '{$safeCol}'";
    $result = $conn->query($sql);
    return $result && $result->num_rows > 0;
}

function fetchOne(mysqli $conn, string $sql): ?array
{
    $result = $conn->query($sql);
    return $result ? $result->fetch_assoc() : null;
}

// Auth check: yêu cầu đăng nhập; kiểm tra quyền admin nếu có cột role/is_admin
if (!isset($_SESSION['Iduser'])) {
    header('Location: login.php');
    exit();
}

$currentUserId = (int)$_SESSION['Iduser'];
$isAdmin = false;
$roleChecked = false;

if (tableExists($conn, 'users')) {
    if (columnExists($conn, 'users', 'role')) {
        $row = fetchOne($conn, "SELECT role FROM users WHERE Iduser = {$currentUserId}");
        if ($row && strtolower((string)$row['role']) === 'admin') {
            $isAdmin = true;
        }
        $roleChecked = true;
    } elseif (columnExists($conn, 'users', 'is_admin')) {
        $row = fetchOne($conn, "SELECT is_admin FROM users WHERE Iduser = {$currentUserId}");
        if ($row && (int)$row['is_admin'] === 1) {
            $isAdmin = true;
        }
        $roleChecked = true;
    }
}

// Nếu có cột quyền mà không phải admin -> chặn; nếu không có cột quyền thì cho phép xem (đọc-only)
if ($roleChecked && !$isAdmin) {
    http_response_code(403);
    echo "⛔ Bạn không có quyền truy cập trang dashboard admin.";
    exit();
}

// Metrics containers
$stats = [
    'users' => [
        'total' => null,
        'new7d' => null,
        'active15m' => null,
        'warnings' => []
    ],
    'games' => [
        'ac' => ['rounds' => null, 'wins' => null, 'totalBet' => null],
        'warnings' => []
    ],
    'system' => [
        'dbOk' => true,
        'warnings' => [],
        'errors' => []
    ],
    'logs' => []
];

// User stats
if (tableExists($conn, 'users')) {
    $row = fetchOne($conn, "SELECT COUNT(*) AS c FROM users");
    $stats['users']['total'] = $row ? (int)$row['c'] : 0;

    if (columnExists($conn, 'users', 'created_at')) {
        $row = fetchOne($conn, "SELECT COUNT(*) AS c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['users']['new7d'] = $row ? (int)$row['c'] : 0;
    } else {
        $stats['users']['warnings'][] = "Thiếu cột created_at → không tính được user mới 7 ngày.";
    }

    if (columnExists($conn, 'users', 'last_active')) {
        $row = fetchOne($conn, "SELECT COUNT(*) AS c FROM users WHERE last_active >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $stats['users']['active15m'] = $row ? (int)$row['c'] : 0;
    } else {
        $stats['users']['warnings'][] = "Thiếu cột last_active → không tính được user online 15 phút.";
    }
} else {
    $stats['system']['errors'][] = "Bảng users chưa tồn tại.";
}

// Game stats: lịch sử AC (slot) nếu có
if (tableExists($conn, 'history_ac')) {
    $row = fetchOne($conn, "SELECT COUNT(*) AS c, SUM(Bet) AS totalBet, SUM(CASE WHEN WinAmount > 0 THEN 1 ELSE 0 END) AS winCount FROM history_ac");
    if ($row) {
        $stats['games']['ac']['rounds'] = (int)$row['c'];
        $stats['games']['ac']['wins'] = (int)$row['winCount'];
        $stats['games']['ac']['totalBet'] = $row['totalBet'] !== null ? (float)$row['totalBet'] : 0;
    }
} else {
    $stats['games']['warnings'][] = "Chưa có bảng history_ac để thống kê game AC.";
}

// Error log (đọc 20 dòng cuối nếu có)
$errorLogPath = __DIR__ . '/error_log';
if (file_exists($errorLogPath)) {
    $lines = @file($errorLogPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        $stats['logs'] = array_slice($lines, -20);
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="icon" href="images.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #0f172a;
            --card: #111827;
            --text: #e5e7eb;
            --muted: #9ca3af;
            --accent: #60a5fa;
            --error: #f87171;
            --warning: #fbbf24;
            --success: #34d399;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: radial-gradient(circle at 10% 20%, rgba(96,165,250,0.12), transparent 25%),
                        radial-gradient(circle at 80% 10%, rgba(52,211,153,0.12), transparent 20%),
                        var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 24px;
        }
        h1 { margin: 0 0 16px; font-size: 28px; }
        .grid { display: grid; gap: 16px; }
        .cards { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .card {
            background: linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02));
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px;
            padding: 16px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.25);
        }
        .card h3 { margin: 0 0 8px; font-size: 16px; color: var(--muted); }
        .card .value { font-size: 28px; font-weight: 700; }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            color: #0f172a;
        }
        .pill.success { background: var(--success); }
        .pill.warning { background: var(--warning); }
        .pill.error { background: var(--error); color: #fff; }
        ul { margin: 8px 0 0; padding-left: 18px; color: var(--muted); }
        .section {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 18px;
            padding: 18px;
            margin-top: 12px;
        }
        .logs { max-height: 260px; overflow: auto; background: #0b1221; padding: 12px; border-radius: 12px; font-family: 'SFMono-Regular', Consolas, monospace; font-size: 12px; }
        .muted { color: var(--muted); font-size: 14px; }
    </style>
</head>
<body>
    <h1>📊 Admin Dashboard</h1>

    <div class="grid cards">
        <div class="card">
            <h3>Người dùng</h3>
            <div class="value"><?= $stats['users']['total'] !== null ? number_format($stats['users']['total']) : 'N/A' ?></div>
            <?php if ($stats['users']['new7d'] !== null): ?>
                <div class="pill success"><i class="fa fa-user-plus"></i> +<?= number_format($stats['users']['new7d']) ?> / 7 ngày</div>
            <?php else: ?>
                <div class="pill warning"><i class="fa fa-exclamation-triangle"></i> Thiếu created_at</div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Online (15 phút)</h3>
            <div class="value"><?= $stats['users']['active15m'] !== null ? number_format($stats['users']['active15m']) : 'N/A' ?></div>
            <?php if ($stats['users']['active15m'] === null): ?>
                <div class="pill warning"><i class="fa fa-clock"></i> Thiếu last_active</div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Game AC - Lượt chơi</h3>
            <div class="value"><?= $stats['games']['ac']['rounds'] !== null ? number_format($stats['games']['ac']['rounds']) : 'N/A' ?></div>
            <?php if ($stats['games']['ac']['totalBet'] !== null): ?>
                <div class="pill success"><i class="fa fa-coins"></i> Tổng cược: <?= number_format($stats['games']['ac']['totalBet'], 0) ?></div>
            <?php else: ?>
                <div class="pill warning"><i class="fa fa-exclamation-triangle"></i> Thiếu bảng history_ac</div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Game AC - Thắng</h3>
            <div class="value">
                <?php
                if ($stats['games']['ac']['wins'] !== null && $stats['games']['ac']['rounds'] !== null && $stats['games']['ac']['rounds'] > 0) {
                    $winrate = ($stats['games']['ac']['wins'] / max(1, $stats['games']['ac']['rounds'])) * 100;
                    echo number_format($stats['games']['ac']['wins']) . " ( " . number_format($winrate, 1) . "% )";
                } elseif ($stats['games']['ac']['wins'] !== null) {
                    echo number_format($stats['games']['ac']['wins']);
                } else {
                    echo 'N/A';
                }
                ?>
            </div>
        </div>
    </div>

    <div class="section">
        <h3>⚠️ Cảnh báo</h3>
        <ul>
            <?php
            $warnings = array_merge($stats['users']['warnings'], $stats['games']['warnings'], $stats['system']['warnings']);
            if (empty($warnings)) {
                echo "<li>Không có cảnh báo.</li>";
            } else {
                foreach ($warnings as $w) {
                    echo "<li>" . htmlspecialchars($w) . "</li>";
                }
            }
            ?>
        </ul>
        <?php if (!empty($stats['system']['errors'])): ?>
            <h4>❌ Lỗi hệ thống</h4>
            <ul>
                <?php foreach ($stats['system']['errors'] as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <?php if (!empty($stats['logs'])): ?>
        <div class="section">
            <h3>📝 error_log (20 dòng gần nhất)</h3>
            <div class="logs">
                <?php foreach ($stats['logs'] as $line): ?>
                    <?= htmlspecialchars($line) ?><br>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <p class="muted">Không tìm thấy hoặc không đọc được file error_log.</p>
    <?php endif; ?>
</body>
</html>

