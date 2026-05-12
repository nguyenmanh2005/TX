<?php
session_start();
require_once 'db_connect.php';
require_once 'guild_war_helper.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['Iduser'];
$leaderboard = getWeeklyGuildLeaderboard($conn, 20);

// Lấy thông tin guild của user hiện tại
$userGuildSql = "SELECT g.id, g.name, g.tag, s.points, s.wins,
                (SELECT COUNT(*) + 1 FROM guild_weekly_stats WHERE points > s.points) as rank
                FROM guild_members gm
                JOIN guilds g ON gm.guild_id = g.id
                LEFT JOIN guild_weekly_stats s ON g.id = s.guild_id
                WHERE gm.user_id = ?";
$stmt = $conn->prepare($userGuildSql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$myGuild = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Lấy các thách đấu hiện tại
$activeChallenges = getActiveGuildChallenges($conn, $myGuild ? $myGuild['id'] : 0);
$pendingChallenges = [];
if ($myGuild) {
    $stmt = $conn->prepare("SELECT c.*, g.name as challenger_name FROM guild_challenges c JOIN guilds g ON c.challenger_id = g.id WHERE c.challenged_id = ? AND c.status = 0");
    $stmt->bind_param("i", $myGuild['id']);
    $stmt->execute();
    $pendingChallenges = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đua Top Bang Hội (Guild War)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/lobby.css">
    <style>
        :root {
            --gold: #f1c40f;
            --silver: #bdc3c7;
            --bronze: #cd7f32;
        }
        
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .war-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
        }

        .war-header {
            text-align: center;
            margin-bottom: 40px;
            background: rgba(255, 255, 255, 0.05);
            padding: 30px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .war-header h1 {
            font-size: 3em;
            margin-bottom: 10px;
            background: linear-gradient(to right, #f1c40f, #e67e22);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .timer {
            font-size: 1.2em;
            color: #e74c3c;
            font-weight: bold;
            display: inline-block;
            padding: 10px 20px;
            background: rgba(231, 76, 60, 0.1);
            border-radius: 50px;
            border: 1px solid rgba(231, 76, 60, 0.3);
        }

        .my-guild-stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 30px;
            background: rgba(52, 152, 219, 0.1);
            padding: 20px;
            border-radius: 15px;
            border: 1px solid rgba(52, 152, 219, 0.3);
        }

        .stat-item {
            text-align: center;
        }

        .stat-label {
            display: block;
            font-size: 0.9em;
            color: #bdc3c7;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 1.5em;
            font-weight: bold;
            color: #3498db;
        }

        .leaderboard-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        .leaderboard-table th {
            padding: 15px;
            text-align: left;
            color: #bdc3c7;
            text-transform: uppercase;
            font-size: 0.8em;
            letter-spacing: 1px;
        }

        .guild-row {
            background: rgba(255, 255, 255, 0.03);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .guild-row:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: scale(1.01);
        }

        .guild-row td {
            padding: 20px 15px;
        }

        .guild-row td:first-child {
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
            font-weight: bold;
            font-size: 1.2em;
            width: 60px;
            text-align: center;
        }

        .guild-row td:last-child {
            border-top-right-radius: 12px;
            border-bottom-right-radius: 12px;
            text-align: right;
            font-weight: bold;
            color: #2ecc71;
            font-size: 1.1em;
        }

        .rank-1 { color: var(--gold); }
        .rank-2 { color: var(--silver); }
        .rank-3 { color: var(--bronze); }

        .guild-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .guild-tag {
            background: rgba(255,255,255,0.1);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            color: #3498db;
            font-weight: bold;
        }

        .guild-name {
            font-weight: 600;
            font-size: 1.1em;
        }

        .leader-name {
            font-size: 0.85em;
            color: #7f8c8d;
            display: block;
        }

        .prize-info {
            margin-top: 50px;
            background: rgba(241, 196, 15, 0.05);
            padding: 25px;
            border-radius: 15px;
            border: 1px solid rgba(241, 196, 15, 0.2);
        }

        .prize-info h3 {
            color: var(--gold);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .prize-list {
            list-style: none;
            padding: 0;
        }

        .prize-list li {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .prize-list li:last-child {
            border-bottom: none;
        }

        .btn-back {
            position: fixed;
            top: 20px;
            left: 20px;
            padding: 10px 20px;
            background: rgba(255,255,255,0.1);
            color: #fff;
            text-decoration: none;
            border-radius: 10px;
            backdrop-filter: blur(5px);
            z-index: 100;
            transition: all 0.3s;
        }

        .btn-back:hover {
            background: rgba(255,255,255,0.2);
        }
    </style>
</head>
<body>
    <a href="guilds.php" class="btn-back"><i class="fa fa-arrow-left"></i> Quay lại</a>

    <div class="war-container">
        <div class="war-header">
            <h1><i class="fa fa-shield-halved"></i> Đua Top Bang Hội</h1>
            <p>Chiến đấu cùng đồng đội để giành lấy vinh quang và phần thưởng khổng lồ!</p>
            <div class="timer">
                <i class="fa fa-clock"></i> Kết thúc sau: <?= $timeLeft ?>
            </div>
        </div>

        <?php if ($myGuild): ?>
        <div class="my-guild-stats">
            <div class="stat-item">
                <span class="stat-label">Bang hội</span>
                <span class="stat-value"><?= htmlspecialchars($myGuild['name']) ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Hạng hiện tại</span>
                <span class="stat-value">#<?= $myGuild['rank'] ?: '--' ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Điểm tuần</span>
                <span class="stat-value"><?= number_format($myGuild['points'] ?: 0) ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Cúp chiến công</span>
                <span class="stat-value"><i class="fa fa-trophy" style="color: gold;"></i> <?= $myGuild['id'] ? (int)$conn->query("SELECT trophies_count FROM guilds WHERE id = ".$myGuild['id'])->fetch_assoc()['trophies_count'] : 0 ?></span>
            </div>
        </div>

        <!-- Section Thách Đấu -->
        <div class="social-war-section" style="margin-bottom: 40px;">
            <h2 style="color: #f1c40f; margin-bottom: 20px;"><i class="fa fa-swords"></i> Thách Đấu Bang Hội (24h)</h2>
            
            <?php if ($pendingChallenges && $pendingChallenges->num_rows > 0): ?>
            <div class="pending-challenges" style="background: rgba(231, 76, 60, 0.1); padding: 20px; border-radius: 15px; border: 1px solid #e74c3c; margin-bottom: 20px;">
                <h3 style="color: #e74c3c; font-size: 1.1em; margin-bottom: 10px;">Lời mời thách đấu mới!</h3>
                <?php while($pc = $pendingChallenges->fetch_assoc()): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Bang <strong><?= htmlspecialchars($pc['challenger_name']) ?></strong> đang thách đấu bang bạn!</span>
                        <button onclick="acceptChallenge(<?= $pc['id'] ?>)" class="btn-action" style="background: #2ecc71; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer;">Chấp nhận</button>
                    </div>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>

            <div class="active-challenges-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <?php if ($activeChallenges->num_rows > 0): ?>
                    <?php while($ac = $activeChallenges->fetch_assoc()): ?>
                        <div class="challenge-card" style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.1); text-align: center;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <div>
                                    <div style="font-weight: bold; color: #3498db;"><?= htmlspecialchars($ac['challenger_name']) ?></div>
                                    <div style="font-size: 1.5em;"><?= number_format($ac['challenger_score']) ?></div>
                                </div>
                                <div style="font-weight: bold; color: #e74c3c; font-size: 1.2em;">VS</div>
                                <div>
                                    <div style="font-weight: bold; color: #3498db;"><?= htmlspecialchars($ac['challenged_name']) ?></div>
                                    <div style="font-size: 1.5em;"><?= number_format($ac['challenged_score']) ?></div>
                                </div>
                            </div>
                            <div class="timer-war" data-end="<?= $ac['end_time'] ?>" style="color: #bdc3c7; font-size: 0.9em;">
                                Kết thúc sau: <span class="countdown">...</span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; color: #7f8c8d; padding: 20px; border: 1px dashed #7f8c8d; border-radius: 15px;">
                        Chưa có trận thách đấu nào đang diễn ra.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="my-guild-stats" style="justify-content: center; color: #bdc3c7;">
            Bạn chưa gia nhập Bang hội nào để tham gia Đua Top & Thách Đấu!
        </div>
        <?php endif; ?>

        <table class="leaderboard-table">
            <thead>
                <tr>
                    <th>Hạng</th>
                    <th>Bang Hội</th>
                    <th style="text-align: center;">Trận thắng</th>
                    <th style="text-align: right;">Điểm Chiến Công</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rank = 1;
                if ($leaderboard->num_rows > 0): 
                    while($row = $leaderboard->fetch_assoc()):
                        $rankClass = ($rank <= 3) ? "rank-$rank" : "";
                ?>
                <tr class="guild-row" onclick="challengeGuild(<?= $row['id'] ?>, '<?= addslashes($row['name']) ?>')">
                    <td class="<?= $rankClass ?>"><?= $rank ?></td>
                    <td>
                        <div class="guild-info">
                            <span class="guild-tag"><?= htmlspecialchars($row['tag']) ?></span>
                            <div>
                                <span class="guild-name"><?= htmlspecialchars($row['name']) ?></span>
                                <span class="leader-name">Chủ bang: <?= htmlspecialchars($row['leader_name']) ?></span>
                            </div>
                        </div>
                    </td>
                    <td style="text-align: center;"><?= number_format($row['wins']) ?></td>
                    <td style="text-align: right;"><?= number_format($row['points']) ?></td>
                </tr>
                <?php 
                    $rank++;
                    endwhile; 
                else:
                ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 40px; color: #7f8c8d;">Chưa có dữ liệu đua top tuần này.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="prize-info">
            <h3><i class="fa fa-trophy"></i> Cơ Cấu Giải Thưởng Tuần</h3>
            <ul class="prize-list">
                <li>
                    <span><strong class="rank-1">Top 1 Bang Hội:</strong></span>
                    <span>5.000.000 gtlm + Title "Vô Địch Bang Hội"</span>
                </li>
                <li>
                    <span><strong class="rank-2">Top 2 Bang Hội:</strong></span>
                    <span>2.500.000 gtlm</span>
                </li>
                <li>
                    <span><strong class="rank-3">Top 3 Bang Hội:</strong></span>
                    <span>1.000.000 gtlm</span>
                </li>
                <li>
                    <span><strong>Top 4 - 10:</strong></span>
                    <span>500.000 gtlm</span>
                </li>
            </ul>
            <p style="font-size: 0.85em; color: #bdc3c7; margin-top: 15px; font-style: italic;">
                * Phần thưởng sẽ được chuyển vào Quỹ Bang Hội sau khi kết thúc tuần. Chủ bang có quyền phân phối phần thưởng cho thành viên.
            </p>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function challengeGuild(id, name) {
            Swal.fire({
                title: 'Thách đấu Bang hội',
                text: "Bạn có muốn thách đấu Bang " + name + " trong 24h đua GTLM không?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f1c40f',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Gửi lời thách!',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api_guild_war.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=challenge&target_guild_id=' + id
                    })
                    .then(r => r.json())
                    .then(data => {
                        Swal.fire(data.success ? 'Thành công' : 'Lỗi', data.message, data.success ? 'success' : 'error');
                    });
                }
            })
        }

        function acceptChallenge(challengeId) {
            fetch('api_guild_war.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=accept&challenge_id=' + challengeId
            })
            .then(r => r.json())
            .then(data => {
                Swal.fire('Kết quả', data.message, data.success ? 'success' : 'error').then(() => location.reload());
            });
        }

        // Countdown Timer
        function updateCountdowns() {
            document.querySelectorAll('.timer-war').forEach(el => {
                const endTime = new Date(el.dataset.end).getTime();
                const now = new Date().getTime();
                const diff = endTime - now;

                if (diff <= 0) {
                    el.querySelector('.countdown').innerHTML = "Đang tổng kết...";
                } else {
                    const h = Math.floor(diff / 3600000);
                    const m = Math.floor((diff % 3600000) / 60000);
                    const s = Math.floor((diff % 60000) / 1000);
                    el.querySelector('.countdown').innerHTML = `${h}h ${m}m ${s}s`;
                }
            });
        }
        setInterval(updateCountdowns, 1000);
        updateCountdowns();
    </script>
</body>
</html>
