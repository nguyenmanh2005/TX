<?php
session_start();
if (!isset($_SESSION['Iduser'])) { header("Location: login.php"); exit; }
require 'db_connect.php';

// Tuần hiện tại bắt đầu từ thứ 2
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd   = date('Y-m-d', strtotime('sunday this week'));

// BXH tuần hiện tại (tính real-time từ game_history)
$stmt = $conn->prepare("
    SELECT 
        u.Iduser, u.Name, u.ImageURL,
        SUM(CASE WHEN gh.is_win = 1 
            THEN gh.win_amount - gh.bet_amount 
            ELSE -gh.bet_amount END) as net_winnings,
        COUNT(*) as total_games,
        SUM(gh.is_win) as wins
    FROM game_history gh
    JOIN users u ON gh.user_id = u.Iduser
    WHERE gh.played_at >= ? 
      AND u.Email NOT REGEXP '^bot[0-9]+@'
    GROUP BY gh.user_id
    HAVING net_winnings > 0
    ORDER BY net_winnings DESC
    LIMIT 20
");
$stmt->bind_param("s", $weekStart);
$stmt->execute();
$currentRankings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Vị trí của user hiện tại
$myRank = null;
$myUserId = (int)$_SESSION['Iduser'];
foreach ($currentRankings as $i => $row) {
    if ((int)$row['Iduser'] === $myUserId) {
        $myRank = $i + 1;
        break;
    }
}

// Reset tiếp theo (thứ 2 tuần sau)
$nextReset  = date('Y-m-d H:i:s', strtotime('next monday 00:05'));
$secondsLeft = strtotime($nextReset) - time();

$rewards = [1 => 5000000, 2 => 2000000, 3 => 1000000];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>BXH Tuần - Trận Địa Giao Lưu</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #6366f1;
            --secondary: #a855f7;
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
            --bg: #0f172a;
            --card: rgba(255, 255, 255, 0.05);
            --gold: #fbbf24;
            --silver: #94a3b8;
            --bronze: #b45309;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: #f8fafc;
            margin: 0;
            padding: 0;
            background-image: radial-gradient(circle at 50% 0%, rgba(99, 102, 241, 0.15) 0%, transparent 50%);
        }

        .leaderboard-wrap {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .header-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .header-section h1 {
            font-size: 32px;
            font-weight: 800;
            margin: 0;
            background: linear-gradient(135deg, #fff 0%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .timer-box {
            background: var(--card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }

        .timer-label {
            font-size: 14px;
            color: #94a3b8;
            margin-bottom: 4px;
        }

        .timer-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
            font-variant-numeric: tabular-nums;
        }

        .week-range {
            text-align: right;
            font-size: 14px;
            color: #64748b;
        }

        .reward-strip {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }

        .reward-card {
            flex: 1;
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.05);
            background: var(--card);
            transition: transform 0.3s ease;
        }

        .reward-card:hover {
            transform: translateY(-5px);
        }

        .reward-card.gold { border-color: rgba(251, 191, 36, 0.3); background: linear-gradient(180deg, rgba(251, 191, 36, 0.1) 0%, transparent 100%); }
        .reward-card.silver { border-color: rgba(148, 163, 184, 0.3); background: linear-gradient(180deg, rgba(148, 163, 184, 0.1) 0%, transparent 100%); }
        .reward-card.bronze { border-color: rgba(180, 83, 9, 0.3); background: linear-gradient(180deg, rgba(180, 83, 9, 0.1) 0%, transparent 100%); }

        .reward-icon {
            font-size: 32px;
            margin-bottom: 8px;
        }

        .reward-amount {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            margin-top: 4px;
        }

        .rank-row {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 20px;
            border-radius: 20px;
            margin-bottom: 12px;
            background: var(--card);
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.2s ease;
        }

        .rank-row:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .rank-row.is-me {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.1);
        }

        .rank-num {
            width: 40px;
            text-align: center;
            font-weight: 800;
            font-size: 18px;
            color: #64748b;
        }

        .rank-row.top-1 .rank-num { color: var(--gold); }
        .rank-row.top-2 .rank-num { color: var(--silver); }
        .rank-row.top-3 .rank-num { color: var(--bronze); }

        .rank-avatar {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        .rank-name {
            flex: 1;
            font-weight: 600;
            font-size: 16px;
        }

        .rank-stats {
            text-align: right;
        }

        .rank-gtlm {
            font-weight: 700;
            font-size: 16px;
            color: var(--success);
        }

        .rank-games {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }

        .my-rank-banner {
            text-align: center;
            padding: 20px;
            background: linear-gradient(90deg, transparent, rgba(99, 102, 241, 0.1), transparent);
            border-radius: 20px;
            margin-top: 30px;
            font-size: 15px;
            color: #94a3b8;
        }

        .my-rank-banner strong {
            color: #fff;
            font-size: 18px;
        }

        @media (max-width: 600px) {
            .reward-strip { flex-direction: column; }
            .timer-box { flex-direction: column; text-align: center; gap: 15px; }
            .week-range { text-align: center; }
        }
    </style>
</head>
<body>
<div class="leaderboard-wrap">

    <div class="header-section">
        <h1>🏆 Bảng Xếp Hạng Tuần</h1>
        <p style="color: #64748b; margin-top: 10px;">Ra chiêu ngay để húp GTLM khủng từ giải đấu hàng tuần!</p>
    </div>

    <div class="timer-box">
        <div>
            <div class="timer-label">Reset & trao thưởng sau</div>
            <div class="timer-value" id="countdown">--:--:--</div>
        </div>
        <div class="week-range">
            Tuần <?= date('d/m', strtotime($weekStart)) ?> – <?= date('d/m', strtotime($weekEnd)) ?>
        </div>
    </div>

    <div class="reward-strip">
        <div class="reward-card gold">
            <div class="reward-icon">🏆</div>
            <div style="font-size:12px; color: var(--gold); font-weight: 700;">HẠNG 1</div>
            <div class="reward-amount"><?= number_format($rewards[1], 0, ',', '.') ?> GTLM</div>
        </div>
        <div class="reward-card silver">
            <div class="reward-icon">🥈</div>
            <div style="font-size:12px; color: var(--silver); font-weight: 700;">HẠNG 2</div>
            <div class="reward-amount"><?= number_format($rewards[2], 0, ',', '.') ?> GTLM</div>
        </div>
        <div class="reward-card bronze">
            <div class="reward-icon">🥉</div>
            <div style="font-size:12px; color: #b45309; font-weight: 700;">HẠNG 3</div>
            <div class="reward-amount"><?= number_format($rewards[3], 0, ',', '.') ?> GTLM</div>
        </div>
    </div>

    <?php if (empty($currentRankings)): ?>
        <div style="text-align:center; color: #64748b; padding: 60px 0; background: var(--card); border-radius: 30px; border: 1px dashed rgba(255,255,255,0.1);">
            <div style="font-size: 48px; margin-bottom: 20px;">🎮</div>
            Chưa có cao thủ nào xuất hiện tuần này.<br>Hãy là người đầu tiên ghi danh vào sử ký!
        </div>
    <?php else: ?>
        <div class="rank-list">
            <?php foreach ($currentRankings as $i => $player):
                $pos   = $i + 1;
                $isMe  = (int)$player['Iduser'] === $myUserId;
                $topClass = $pos <= 3 ? "top-$pos" : "";
                $meClass  = $isMe ? "is-me" : "";
                $medal = ['', '🥇', '🥈', '🥉'][$pos] ?? $pos;
                $avatar = $player['ImageURL'] ?: "https://ui-avatars.com/api/?name=" . urlencode($player['Name']) . "&background=random";
                $winRate = $player['total_games'] > 0 ? round($player['wins'] / $player['total_games'] * 100) : 0;
            ?>
            <div class="rank-row <?= $topClass ?> <?= $meClass ?>">
                <div class="rank-num"><?= $medal ?></div>
                <img class="rank-avatar" src="<?= htmlspecialchars($avatar) ?>" alt="">
                <div class="rank-name">
                    <?= htmlspecialchars($player['Name']) ?>
                    <?php if ($isMe): ?><span style="font-size:11px; color: var(--primary); font-weight: 700; margin-left: 5px;">BẠN</span><?php endif; ?>
                </div>
                <div class="rank-stats">
                    <div class="rank-gtlm">+<?= number_format($player['net_winnings'], 0, ',', '.') ?></div>
                    <div class="rank-games"><?= $player['total_games'] ?> ván · <?= $winRate ?>% húp</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="my-rank-banner">
        <?php if ($myRank): ?>
            Vị trí hiện tại: <strong>Hạng <?= $myRank ?></strong>
            <?php if ($myRank <= 3): ?> — 🎉 Đang húp quà top!<?php endif; ?>
        <?php else: ?>
            Bạn chưa có mặt trong BXH. Ra chiêu ngay để húp quà! 🎮
        <?php endif; ?>
    </div>

</div>

<script>
const secondsLeft = <?= $secondsLeft ?>;
let remaining = secondsLeft;

function updateCountdown() {
    if (remaining <= 0) {
        document.getElementById('countdown').textContent = 'Đang tổng kết...';
        setTimeout(() => location.reload(), 5000);
        return;
    }
    const d = Math.floor(remaining / 86400);
    const h = Math.floor((remaining % 86400) / 3600);
    const m = Math.floor((remaining % 3600) / 60);
    const s = remaining % 60;
    
    let parts = '';
    if (d > 0) parts += d + 'n ';
    parts += String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
    
    document.getElementById('countdown').textContent = parts;
    remaining--;
}
updateCountdown();
setInterval(updateCountdown, 1000);
</script>
</body>
</html>