<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

// Load theme config
require_once 'load_theme.php';

$userId = $_SESSION['Iduser'];

// 1. Fetch active guild tournament details
$tour = $conn->query("SELECT * FROM guild_tournaments WHERE status = 'active' LIMIT 1")->fetch_assoc();

if (!$tour) {
    // If no active tournament, create one on the fly to avoid blank states!
    $conn->query("INSERT INTO guild_tournaments (name, prize_pool) VALUES ('Đại Chiến Bang Hội - Mùa 1', 1000000)");
    $tour = $conn->query("SELECT * FROM guild_tournaments WHERE status = 'active' LIMIT 1")->fetch_assoc();
}

$tourId = (int)$tour['id'];
$tourName = $tour['name'];
$prizePool = (int)$tour['prize_pool'];
$endsAt = $tour['ends_at'];

// Compute time remaining
$endTimeStamp = strtotime($endsAt);
$timeLeft = $endTimeStamp - time();

// 2. Fetch all scores in this tournament
$sql = "
    SELECT gts.guild_id, gts.user_id, gts.points, 
           g.name as guild_name, g.tag as guild_tag, g.ImageURL as guild_emblem, 
           u.Name as member_name, u.ImageURL as member_avatar
    FROM guild_tournament_scores gts
    JOIN guilds g ON gts.guild_id = g.id
    JOIN users u ON gts.user_id = u.Iduser
    WHERE gts.tournament_id = ?
    ORDER BY gts.guild_id, gts.points DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $tourId);
$stmt->execute();
$res = $stmt->get_result();

$guildData = [];
while ($row = $res->fetch_assoc()) {
    $gid = $row['guild_id'];
    if (!isset($guildData[$gid])) {
        $guildData[$gid] = [
            'id' => $gid,
            'name' => $row['guild_name'],
            'tag' => $row['guild_tag'],
            'emblem' => $row['guild_emblem'],
            'members' => [],
            'top_5_sum' => 0
        ];
    }
    
    $guildData[$gid]['members'][] = [
        'user_id' => $row['user_id'],
        'name' => $row['member_name'],
        'avatar' => $row['member_avatar'],
        'points' => (int)$row['points']
    ];
}
$stmt->close();

// Calculate top 5 sum for each guild
foreach ($guildData as &$g) {
    // Sort members by points descending
    usort($g['members'], function($a, $b) {
        return $b['points'] <=> $a['points'];
    });
    
    $top5 = array_slice($g['members'], 0, 5);
    $sum = 0;
    foreach ($top5 as $m) {
        $sum += $m['points'];
    }
    $g['top_5_sum'] = $sum;
}
unset($g);

// Sort guilds by top 5 sum descending
usort($guildData, function($a, $b) {
    return $b['top_5_sum'] <=> $a['top_5_sum'];
});

// 3. Fetch current user's guild details (if any)
$myGuild = null;
$myScore = 0;
$myRankInGuild = 0;

$myGuildQuery = $conn->query("
    SELECT gm.guild_id, g.name as guild_name, g.tag as guild_tag, g.ImageURL as guild_emblem
    FROM guild_members gm
    JOIN guilds g ON gm.guild_id = g.id
    WHERE gm.user_id = $userId
    LIMIT 1
");

if ($myGuildQuery && $myGuildQuery->num_rows > 0) {
    $myGuild = $myGuildQuery->fetch_assoc();
    $myGId = (int)$myGuild['guild_id'];
    
    // Get user score in active tournament
    $myScoreQuery = $conn->query("
        SELECT points 
        FROM guild_tournament_scores 
        WHERE tournament_id = $tourId AND guild_id = $myGId AND user_id = $userId
        LIMIT 1
    ");
    if ($myScoreQuery && $myScoreQuery->num_rows > 0) {
        $myScore = (int)$myScoreQuery->fetch_assoc()['points'];
    }
    
    // Find rank inside user's guild
    if (isset($guildData[$myGId])) {
        foreach ($guildData[$myGId]['members'] as $idx => $m) {
            if ($m['user_id'] == $userId) {
                $myRankInGuild = $idx + 1;
                break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đại Chiến Bang Hội - Vegas Royale</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1e293b;
        }

        * { cursor: inherit; }
        button, a { cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important; }

        .tournament-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .hero-banner {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.95) 0%, rgba(30, 41, 59, 0.9) 100%);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 40px;
            color: white;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .hero-content h1 {
            font-size: 38px;
            font-weight: 900;
            background: linear-gradient(135deg, #fbbf24 0%, #ef4444 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0 0 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .hero-content p {
            color: #94a3b8;
            font-size: 16px;
            margin: 0;
        }

        .timer-box {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 20px 30px;
            border-radius: 20px;
            text-align: center;
            backdrop-filter: blur(10px);
        }

        .timer-val {
            font-size: 26px;
            font-weight: 800;
            color: #fbbf24;
            letter-spacing: 1px;
        }

        .timer-lbl {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            color: #94a3b8;
            margin-top: 5px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        @media (max-width: 900px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .card-title {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 20px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
            padding-bottom: 15px;
        }

        .standing-row {
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 16px;
            padding: 15px 20px;
            margin-bottom: 15px;
            background: rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
        }

        .standing-row:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.05);
            background: white;
        }

        .rank-number {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 16px;
        }

        .rank-1 { background: #fef08a; color: #a16207; border: 2px solid #eab308; }
        .rank-2 { background: #e2e8f0; color: #475569; border: 2px solid #94a3b8; }
        .rank-3 { background: #ffedd5; color: #c2410c; border: 2px solid #f97316; }
        .rank-other { background: rgba(0, 0, 0, 0.03); color: #64748b; }

        .guild-emblem-img {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid rgba(0,0,0,0.05);
        }

        .guild-meta {
            flex-grow: 1;
        }

        .guild-name {
            font-weight: 800;
            font-size: 16px;
            color: #1e293b;
        }

        .guild-tag {
            font-size: 11px;
            font-weight: 700;
            background: rgba(99, 102, 241, 0.1);
            color: #6366f1;
            padding: 2px 6px;
            border-radius: 6px;
            margin-left: 6px;
            text-transform: uppercase;
        }

        .guild-points {
            text-align: right;
        }

        .guild-points-val {
            font-size: 18px;
            font-weight: 850;
            color: #ef4444;
        }

        .guild-points-lbl {
            font-size: 10px;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
        }

        .expand-btn {
            background: none;
            border: none;
            color: #6366f1;
            cursor: pointer;
            padding: 5px;
            font-size: 18px;
            transition: transform 0.2s;
        }

        .expanded-members-box {
            display: none;
            padding: 15px;
            background: rgba(0, 0, 0, 0.02);
            border-radius: 12px;
            margin-top: -10px;
            margin-bottom: 15px;
            border: 1px dashed rgba(0, 0, 0, 0.08);
            border-top: none;
        }

        .member-squad-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-bottom: 1px solid rgba(0,0,0,0.03);
        }

        .member-squad-row:last-child {
            border-bottom: none;
        }

        .squad-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
        }

        .squad-name {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            flex-grow: 1;
        }

        .squad-status-badge {
            font-size: 9px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .status-squad { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
        .status-reserve { background: rgba(0, 0, 0, 0.04); color: #64748b; }

        .rules-list {
            padding-left: 20px;
            margin: 0;
        }

        .rules-list li {
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 12px;
            color: #475569;
        }
    </style>
</head>
<body>
    <div class="tournament-container">
        <!-- Hero Banner -->
        <div class="hero-banner">
            <div class="hero-content">
                <h1>🏆 <?= htmlspecialchars($tourName) ?></h1>
                <p>Tổng giải thưởng: <b style="color: #fbbf24; font-size: 18px;"><?= number_format($prizePool) ?> GTLM</b> chia đều cho bang hội thắng cuộc!</p>
            </div>
            
            <div class="timer-box">
                <div class="timer-val" id="countdown-timer">--:--:--</div>
                <div class="timer-lbl">Thời gian còn lại</div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Left Side: Leaderboard -->
            <div>
                <div class="glass-card">
                    <h2 class="card-title"><i class="fas fa-trophy" style="color:#fbbf24;"></i> Bảng Xếp Hạng Bang Hội</h2>
                    
                    <?php if (empty($guildData)): ?>
                        <div style="text-align:center; padding: 50px 20px; color:#64748b;">
                            <i class="fas fa-flag-checkered" style="font-size: 45px; color:#cbd5e1; margin-bottom:15px;"></i>
                            <p style="font-weight: 700; color:#334155;">Giải đấu chưa ghi nhận điểm số</p>
                            <p style="font-size: 13px;">Hãy là bang hội đầu tiên nổ hũ để ghi tên lên bảng vàng!</p>
                        </div>
                    <?php else: 
                        $rank = 0;
                        foreach ($guildData as $guildId => $g):
                            $rank++;
                            $rankClass = $rank <= 3 ? "rank-{$rank}" : "rank-other";
                    ?>
                        <div class="standing-row">
                            <div class="rank-number <?= $rankClass ?>"><?= $rank ?></div>
                            <img src="<?= htmlspecialchars($g['emblem'] ?: 'img/default-guild.png') ?>" 
                                 class="guild-emblem-img" alt="Emblem">
                            <div class="guild-meta">
                                <span class="guild-name"><?= htmlspecialchars($g['name']) ?></span>
                                <span class="guild-tag"><?= htmlspecialchars($g['tag']) ?></span>
                            </div>
                            <div class="guild-points">
                                <div class="guild-points-val"><?= number_format($g['top_5_sum']) ?></div>
                                <div class="guild-points-lbl">Điểm (Top 5)</div>
                            </div>
                            <button class="expand-btn" onclick="toggleMembers(<?= $guildId ?>)">
                                <i class="fas fa-chevron-down" id="chevron-<?= $guildId ?>"></i>
                            </button>
                        </div>

                        <!-- Dropdown of contributors -->
                        <div class="expanded-members-box" id="members-<?= $guildId ?>">
                            <div style="font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; border-bottom: 1px solid rgba(0,0,0,0.03); padding-bottom: 5px;">
                                Chi tiết thành viên đóng góp:
                            </div>
                            <?php foreach ($g['members'] as $idx => $m): 
                                $isSquad = $idx < 5;
                                $squadBadge = $isSquad ? "status-squad" : "status-reserve";
                                $squadText = $isSquad ? "Chủ lực (Top 5)" : "Dự bị";
                            ?>
                                <div class="member-squad-row">
                                    <span style="font-size: 12px; font-weight: 700; color: #64748b; width: 20px;"><?= $idx + 1 ?>.</span>
                                    <img src="<?= htmlspecialchars($m['avatar'] ?: 'img/default-avatar.png') ?>" 
                                         class="squad-avatar" alt="Avatar">
                                    <span class="squad-name"><?= htmlspecialchars($m['name']) ?></span>
                                    <span class="squad-status-badge <?= $squadBadge ?>"><?= $squadText ?></span>
                                    <span style="font-size: 13px; font-weight: 700; color: #1e293b;"><?= number_format($m['points']) ?> pts</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Side: Rules & Personal Contribution -->
            <div>
                <!-- My Guild Widget -->
                <div class="glass-card">
                    <h2 class="card-title"><i class="fas fa-shield-halved" style="color:#6366f1;"></i> Bang Hội Của Tôi</h2>
                    
                    <?php if ($myGuild): ?>
                        <div style="display:flex; align-items:center; gap: 15px; margin-bottom: 20px; background: rgba(99, 102, 241, 0.05); padding: 15px; border-radius: 16px; border: 1px dashed rgba(99, 102, 241, 0.2);">
                            <img src="<?= htmlspecialchars($myGuild['guild_emblem'] ?: 'img/default-guild.png') ?>" 
                                 style="width: 50px; height: 50px; border-radius: 12px; object-fit: cover;" alt="My Guild">
                            <div>
                                <div style="font-weight: 800; font-size: 15px; color:#1e293b;"><?= htmlspecialchars($myGuild['guild_name']) ?></div>
                                <div style="font-size: 11px; color:#6366f1; font-weight: 700; text-transform: uppercase; margin-top: 3px;">Tag: [<?= htmlspecialchars($myGuild['guild_tag']) ?>]</div>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px; text-align: center;">
                            <div style="background: rgba(0,0,0,0.02); padding: 12px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.04);">
                                <div style="font-size: 18px; font-weight: 800; color: #6366f1;"><?= number_format($myScore) ?></div>
                                <div style="font-size: 10px; font-weight: 700; color:#64748b; text-transform: uppercase; margin-top: 3px;">Điểm của tôi</div>
                            </div>
                            <div style="background: rgba(0,0,0,0.02); padding: 12px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.04);">
                                <div style="font-size: 18px; font-weight: 800; color: #a855f7;"><?= $myRankInGuild ? "#" . $myRankInGuild : "Dự bị" ?></div>
                                <div style="font-size: 10px; font-weight: 700; color:#64748b; text-transform: uppercase; margin-top: 3px;">Hạng trong bang</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px 10px; color:#64748b;">
                            <i class="fas fa-link-slash" style="font-size: 35px; color:#cbd5e1; margin-bottom: 10px;"></i>
                            <p style="font-size: 13px; line-height: 1.6;">Bạn chưa gia nhập bang hội nào! Hãy tạo bang hoặc xin vào bang hội bất kỳ để bắt đầu tích điểm giải đấu!</p>
                            <a href="guild.php" style="display:inline-block; margin-top:12px; padding: 8px 16px; background: rgba(99, 102, 241, 0.1); color:#6366f1; text-decoration:none; font-weight:700; font-size:12px; border-radius:8px;">
                                <i class="fas fa-door-open"></i> Xem Danh Sách Bang Hội
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Rules Widget -->
                <div class="glass-card">
                    <h2 class="card-title"><i class="fas fa-circle-info" style="color:#64748b;"></i> Quy Lệ Giải Đấu</h2>
                    <ul class="rules-list">
                        <li>
                            <b>🔥 Cơ chế tính điểm:</b> Điểm của Bang hội được tính bằng <b>Tổng điểm của 5 thành viên có điểm cao nhất</b> (Top 5 Squad) trong bang hội đó.
                        </li>
                        <li>
                            <b>🎯 Cách tích điểm:</b> Bất kỳ chiến thắng nào tại các sảnh game đều mang lại điểm. Cược càng to thắng càng đậm, điểm càng nhân đôi! (1 điểm tối thiểu + 1 điểm cho mỗi 10k GTLM thắng cược).
                        </li>
                        <li>
                            <b>🎁 Phần thưởng:</b> Toàn bộ phần thưởng <b><?= number_format($prizePool) ?> GTLM</b> sẽ được chia đều cho 5 thành viên Chủ lực (Top 5) của bang hội đạt Hạng 1 khi thời gian đếm ngược kết thúc!
                        </li>
                        <li>
                            <b>⚡ Online cùng nhau:</b> Rủ đồng đội cùng online cược lớn để bứt phá bảng xếp hạng bang hội ngay hôm nay!
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Back Button -->
        <p style="text-align: center; margin-top: 20px;">
            <a href="index.php" style="color: white; text-decoration: none; font-weight: 700; font-size: 16px; display: inline-flex; align-items: center; gap: 8px; padding: 12px 30px; background: linear-gradient(135deg, #fbbf24, #ef4444); border-radius: 50px; box-shadow: 0 10px 25px rgba(239, 68, 68, 0.3);">
                <i class="fas fa-home"></i> Quay Lại Trang Chủ
            </a>
        </p>
    </div>

    <script>
        function toggleMembers(guildId) {
            const membersBox = document.getElementById('members-' + guildId);
            const chevron = document.getElementById('chevron-' + guildId);
            if (membersBox.style.display === 'block') {
                membersBox.style.display = 'none';
                chevron.style.transform = 'rotate(0deg)';
            } else {
                membersBox.style.display = 'block';
                chevron.style.transform = 'rotate(180deg)';
            }
        }

        // Live Countdown timer
        let timeLeft = <?= $timeLeft ?>;
        function updateTimer() {
            if (timeLeft <= 0) {
                document.getElementById('countdown-timer').innerText = "ĐÃ KẾT THÚC";
                return;
            }
            let days = Math.floor(timeLeft / 86400);
            let hours = Math.floor((timeLeft % 86400) / 3600);
            let minutes = Math.floor((timeLeft % 3600) / 60);
            let seconds = timeLeft % 60;
            
            let timeStr = "";
            if (days > 0) timeStr += days + "d ";
            timeStr += (hours < 10 ? "0" : "") + hours + ":" + 
                       (minutes < 10 ? "0" : "") + minutes + ":" + 
                       (seconds < 10 ? "0" : "") + seconds;
            
            document.getElementById('countdown-timer').innerText = timeStr;
            timeLeft--;
        }
        setInterval(updateTimer, 1000);
        updateTimer();
    </script>
</body>
</html>
