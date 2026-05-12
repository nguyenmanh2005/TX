<?php
session_start();
require_once 'db_connect.php';

// Lấy lịch sử mùa giải
$sql = "SELECT h.*, g.name as guild_name, g.tag as guild_tag, g.ImageURL as guild_logo
        FROM guild_war_history h
        JOIN guilds g ON h.guild_id = g.id
        ORDER BY h.season_end_date DESC, h.rank ASC";
$res = $conn->query($sql);

$history = [];
while ($row = $res->fetch_assoc()) {
    $date = date('d/m/Y', strtotime($row['season_end_date']));
    $history[$date][] = $row;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng Vàng Danh Dự - Guild War</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/lobby.css">
    <style>
        body {
            background: radial-gradient(circle at top, #1a1a2e 0%, #0f0f1a 100%);
            color: #fff;
            min-height: 100vh;
            padding: 40px 20px;
        }

        .hall-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .hall-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .hall-header h1 {
            font-size: 3em;
            background: linear-gradient(to bottom, #f1c40f, #d35400);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-transform: uppercase;
            letter-spacing: 5px;
            margin-bottom: 10px;
        }

        .season-block {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 40px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .season-title {
            font-size: 1.5em;
            color: #f1c40f;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid rgba(241, 196, 15, 0.3);
            padding-bottom: 10px;
        }

        .winner-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .winner-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .winner-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.08);
        }

        .winner-card.rank-1 {
            border: 2px solid #f1c40f;
            background: linear-gradient(135deg, rgba(241, 196, 15, 0.1), rgba(0,0,0,0));
        }

        .rank-number {
            font-size: 2em;
            font-weight: 900;
            width: 50px;
            text-align: center;
        }

        .rank-1 .rank-number { color: #f1c40f; }
        .rank-2 .rank-number { color: #bdc3c7; }
        .rank-3 .rank-number { color: #cd7f32; }

        .guild-logo {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            object-fit: cover;
        }

        .guild-info h3 { margin: 0; font-size: 1.2em; }
        .guild-tag { color: rgba(255,255,255,0.5); font-size: 0.9em; }
        .guild-stats { margin-top: 5px; font-size: 0.85em; color: #2ecc71; }

        .empty-hall {
            text-align: center;
            padding: 100px;
            color: rgba(255,255,255,0.3);
            font-size: 1.2em;
        }
    </style>
</head>
<body>

    <div class="hall-container">
        <div class="hall-header">
            <h1>Bảng Vàng Danh Dự</h1>
            <p>Vinh danh những Bang hội huyền thoại trên GTLM</p>
            <a href="index.php" class="btn btn-sm" style="margin-top: 20px; color: #aaa;">&larr; Quay lại sảnh</a>
        </div>

        <?php if (empty($history)): ?>
            <div class="empty-hall">
                <i class="fa fa-ghost" style="font-size: 4em; margin-bottom: 20px;"></i>
                <p>Chưa có mùa giải nào kết thúc. Hãy là người đầu tiên ghi tên mình vào lịch sử!</p>
            </div>
        <?php else: ?>
            <?php foreach ($history as $date => $winners): ?>
                <div class="season-block">
                    <div class="season-title">
                        <i class="fa fa-calendar-check"></i> Mùa giải kết thúc ngày <?= $date ?>
                    </div>
                    <div class="winner-grid">
                        <?php foreach ($winners as $w): ?>
                            <div class="winner-card rank-<?= $w['rank'] ?>">
                                <div class="rank-number">#<?= $w['rank'] ?></div>
                                <img src="<?= $w['guild_logo'] ?: 'img/guild_default.png' ?>" class="guild-logo" onerror="this.src='img/guild_default.png'">
                                <div class="guild-info">
                                    <h3><?= htmlspecialchars($w['guild_name']) ?></h3>
                                    <div class="guild-tag">[<?= htmlspecialchars($w['guild_tag']) ?>]</div>
                                    <div class="guild-stats">
                                        <i class="fa fa-star"></i> <?= number_format($w['points']) ?> điểm
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</body>
</html>
