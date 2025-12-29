<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

// Load theme
require_once 'load_theme.php';

$userId = $_SESSION['Iduser'];

// Helpers
function table_exists(mysqli $conn, string $name): bool {
    $safe = $conn->real_escape_string($name);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

// Ensure tables
if (!table_exists($conn, 'weekly_challenges')) {
    $conn->query("CREATE TABLE IF NOT EXISTS weekly_challenges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        week_start DATE NOT NULL,
        week_end DATE NOT NULL,
        challenge_type VARCHAR(50) NOT NULL,
        challenge_name VARCHAR(255) NOT NULL,
        description TEXT,
        requirement_value INT NOT NULL,
        reward_money INT DEFAULT 0,
        reward_xp INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_week_type (week_start, challenge_type)
    )");
}

if (!table_exists($conn, 'weekly_challenge_progress')) {
    $conn->query("CREATE TABLE IF NOT EXISTS weekly_challenge_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        challenge_id INT NOT NULL,
        progress INT DEFAULT 0,
        is_completed TINYINT(1) DEFAULT 0,
        completed_at TIMESTAMP NULL,
        claimed TINYINT(1) DEFAULT 0,
        claimed_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(Iduser) ON DELETE CASCADE,
        FOREIGN KEY (challenge_id) REFERENCES weekly_challenges(id) ON DELETE CASCADE,
        UNIQUE KEY uniq_user_challenge (user_id, challenge_id),
        INDEX idx_user_challenge (user_id, challenge_id)
    )");
}

// User info
$stmt = $conn->prepare("SELECT Iduser, Name, Money FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Week window (Mon-Sun)
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));

// Load challenges for this week
$challenges = [];
$sql = "SELECT wc.*, 
        COALESCE(wcp.progress, 0) as user_progress,
        COALESCE(wcp.is_completed, 0) as is_completed,
        COALESCE(wcp.claimed, 0) as claimed
        FROM weekly_challenges wc
        LEFT JOIN weekly_challenge_progress wcp ON wc.id = wcp.challenge_id AND wcp.user_id = ?
        WHERE wc.week_start = ? AND wc.week_end = ?
        ORDER BY wc.id";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $userId, $weekStart, $weekEnd);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $challenges[] = $row;
$stmt->close();

// Auto-generate if empty
if (empty($challenges)) {
    $defaults = [
        [
            'type' => 'play_games',
            'name' => 'Chơi 50 ván',
            'desc' => 'Chơi tổng cộng 50 ván trong tuần',
            'req' => 50,
            'money' => 150000,
            'xp' => 200
        ],
        [
            'type' => 'win_games',
            'name' => 'Thắng 20 ván',
            'desc' => 'Thắng 20 ván bất kỳ trong tuần',
            'req' => 20,
            'money' => 250000,
            'xp' => 300
        ],
        [
            'type' => 'earn_money',
            'name' => 'Kiếm 2,000,000 VNĐ',
            'desc' => 'Tổng lợi nhuận +2,000,000 VNĐ trong tuần',
            'req' => 2000000,
            'money' => 500000,
            'xp' => 500
        ],
        [
            'type' => 'streak_days',
            'name' => 'Đăng nhập 5 ngày',
            'desc' => 'Hoạt động 5 ngày khác nhau trong tuần',
            'req' => 5,
            'money' => 200000,
            'xp' => 250
        ],
    ];

    foreach ($defaults as $c) {
        $ins = $conn->prepare("INSERT INTO weekly_challenges (week_start, week_end, challenge_type, challenge_name, description, requirement_value, reward_money, reward_xp)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE challenge_name = VALUES(challenge_name)");
        $ins->bind_param("sssssiii", $weekStart, $weekEnd, $c['type'], $c['name'], $c['desc'], $c['req'], $c['money'], $c['xp']);
        $ins->execute();
        $ins->close();
    }
    // Re-run select
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $userId, $weekStart, $weekEnd);
    $stmt->execute();
    $res = $stmt->get_result();
    $challenges = [];
    while ($row = $res->fetch_assoc()) $challenges[] = $row;
    $stmt->close();
}

// Update progress
if (!empty($challenges)) {
    require_once 'game_history_helper.php';
    $rangeStart = $weekStart . ' 00:00:00';
    $rangeEnd = $weekEnd . ' 23:59:59';

    foreach ($challenges as $c) {
        if ($c['is_completed'] == 1) continue;
        $progress = 0;
        switch ($c['challenge_type']) {
            case 'play_games':
                $q = $conn->prepare("SELECT COUNT(*) as cnt FROM game_history WHERE user_id = ? AND played_at BETWEEN ? AND ?");
                $q->bind_param("iss", $userId, $rangeStart, $rangeEnd);
                $q->execute(); $r = $q->get_result()->fetch_assoc(); $progress = (int)($r['cnt'] ?? 0); $q->close();
                break;
            case 'win_games':
                $q = $conn->prepare("SELECT COUNT(*) as cnt FROM game_history WHERE user_id = ? AND is_win = 1 AND played_at BETWEEN ? AND ?");
                $q->bind_param("iss", $userId, $rangeStart, $rangeEnd);
                $q->execute(); $r = $q->get_result()->fetch_assoc(); $progress = (int)($r['cnt'] ?? 0); $q->close();
                break;
            case 'earn_money':
                $q = $conn->prepare("SELECT SUM(win_amount - bet_amount) as profit FROM game_history WHERE user_id = ? AND played_at BETWEEN ? AND ?");
                $q->bind_param("iss", $userId, $rangeStart, $rangeEnd);
                $q->execute(); $r = $q->get_result()->fetch_assoc(); $progress = max(0, (int)($r['profit'] ?? 0)); $q->close();
                break;
            case 'streak_days':
                $q = $conn->prepare("SELECT COUNT(DISTINCT DATE(played_at)) as days FROM game_history WHERE user_id = ? AND played_at BETWEEN ? AND ?");
                $q->bind_param("iss", $userId, $rangeStart, $rangeEnd);
                $q->execute(); $r = $q->get_result()->fetch_assoc(); $progress = (int)($r['days'] ?? 0); $q->close();
                break;
        }

        $isDone = $progress >= (int)$c['requirement_value'] ? 1 : 0;
        $up = $conn->prepare("INSERT INTO weekly_challenge_progress (user_id, challenge_id, progress, is_completed, completed_at)
                              VALUES (?, ?, ?, ?, IF(? = 1, NOW(), NULL))
                              ON DUPLICATE KEY UPDATE progress = VALUES(progress), is_completed = VALUES(is_completed), completed_at = IF(VALUES(is_completed)=1, NOW(), completed_at)");
        $up->bind_param("iiiii", $userId, $c['id'], $progress, $isDone, $isDone);
        $up->execute();
        $up->close();
    }

    // reload
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $userId, $weekStart, $weekEnd);
    $stmt->execute();
    $res = $stmt->get_result();
    $challenges = [];
    while ($row = $res->fetch_assoc()) $challenges[] = $row;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thử thách tuần</title>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: <?= $bgGradientCSS ?? "'#0f172a'" ?>; color: #fff; font-family: 'Segoe UI', Arial, sans-serif; }
        .container { max-width: 960px; margin: 20px auto; background: rgba(0,0,0,0.35); padding: 20px; border-radius: 16px; }
        .card { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 16px; margin-bottom: 12px; }
        .progress { background: rgba(255,255,255,0.1); border-radius: 999px; overflow: hidden; height: 12px; margin: 10px 0; }
        .bar { height: 100%; background: linear-gradient(90deg, #60a5fa, #7c3aed); width: 0; transition: width .4s; }
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; background: #111827; border: 1px solid rgba(255,255,255,0.1); font-size: 12px; }
        button { padding: 10px 14px; border: none; border-radius: 10px; cursor: pointer; font-weight: 700; }
        .claim { background: #22c55e; color: #0f172a; }
        .claim:disabled { opacity: .6; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🏆 Thử thách tuần (<?= date('d/m', strtotime($weekStart)) ?> - <?= date('d/m', strtotime($weekEnd)) ?>)</h2>
        <?php if (empty($challenges)): ?>
            <p>Chưa có thử thách tuần.</p>
        <?php else: ?>
            <?php foreach ($challenges as $c):
                $progress = (int)$c['user_progress'];
                $req = (int)$c['requirement_value'];
                $percent = $req > 0 ? min(100, ($progress / $req) * 100) : 0;
                $completed = (int)$c['is_completed'] === 1;
                $claimed = (int)$c['claimed'] === 1;
            ?>
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <h3 style="margin:0;"><?= htmlspecialchars($c['challenge_name']) ?></h3>
                        <div class="badge"><?= htmlspecialchars($c['challenge_type']) ?></div>
                    </div>
                    <div style="text-align:right;">
                        <div>Thưởng: <?= number_format($c['reward_money']) ?> VNĐ + <?= number_format($c['reward_xp']) ?> XP</div>
                        <div><?= $progress ?> / <?= $req ?></div>
                    </div>
                </div>
                <p><?= htmlspecialchars($c['description']) ?></p>
                <div class="progress"><div class="bar" style="width: <?= $percent ?>%;"></div></div>
                <button class="claim" data-id="<?= $c['id'] ?>" <?= (!$completed || $claimed) ? 'disabled' : '' ?>>
                    <?= $claimed ? 'Đã nhận' : ($completed ? 'Nhận thưởng' : 'Chưa hoàn thành') ?>
                </button>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
    $('.claim').on('click', function() {
        const btn = $(this);
        const id = btn.data('id');
        if (btn.prop('disabled')) return;
        btn.prop('disabled', true).text('Đang xử lý...');
        $.post('api_weekly_challenges.php', { action: 'claim', challenge_id: id }, function(res) {
            if (res.status === 'success') {
                Swal.fire('🎉 Thành công', res.message, 'success').then(() => location.reload());
            } else {
                Swal.fire('❌ Lỗi', res.message || 'Không thể nhận thưởng', 'error');
                btn.prop('disabled', false).text('Nhận thưởng');
            }
        }, 'json').fail(function() {
            Swal.fire('❌ Lỗi', 'Không thể kết nối server', 'error');
            btn.prop('disabled', false).text('Nhận thưởng');
        });
    });
    </script>
</body>
</html>

