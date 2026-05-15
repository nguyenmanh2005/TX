<?php
session_start();
require_once 'db_connect.php';
require_once 'api_guild_social_helper.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['Iduser'];
$userGuild = $conn->query("SELECT guild_id FROM guild_members WHERE user_id = $userId")->fetch_assoc();
$myGuildId = $userGuild['guild_id'] ?? null;

$territories = GuildSocialHelper::getTerritoryMap($conn);
$myBonuses = $myGuildId ? GuildSocialHelper::getGuildPassiveBonuses($conn, $myGuildId) : null;

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Guild Territory Map | Conquest</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --primary: #6366f1;
            --accent: #f59e0b;
        }

        body {
            background: var(--bg);
            color: #fff;
            font-family: 'Outfit', sans-serif;
            margin: 0;
            padding: 40px;
            background-image: radial-gradient(circle at 50% 50%, #1e293b 0%, #0f172a 100%);
        }

        .container { max-width: 1200px; margin: 0 auto; }

        header {
            text-align: center;
            margin-bottom: 50px;
        }

        h1 { font-size: 3rem; font-weight: 800; background: linear-gradient(to right, #818cf8, #f472b6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        .layout { display: grid; grid-template-columns: 1fr 350px; gap: 40px; }

        /* Map Styling */
        .map-grid {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 5px;
            background: rgba(255,255,255,0.05);
            padding: 10px;
            border-radius: 20px;
            border: 2px solid rgba(255,255,255,0.1);
            aspect-ratio: 1/1;
        }

        .map-cell {
            background: rgba(15, 23, 42, 0.8);
            border-radius: 4px;
            aspect-ratio: 1/1;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.3s;
            position: relative;
            border: 1px solid rgba(255,255,255,0.05);
            overflow: hidden;
        }

        .map-cell:hover {
            transform: scale(1.1);
            z-index: 10;
            background: rgba(255,255,255,0.1);
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.4);
        }

        .map-cell.occupied { border-width: 2px; }
        .map-cell.mine { border-color: var(--primary); background: rgba(99, 102, 241, 0.2); }
        .map-cell.enemy { border-color: #ef4444; background: rgba(239, 68, 68, 0.1); }

        .cell-icon { font-size: 1.2rem; opacity: 0.5; }
        .map-cell.occupied .cell-icon { opacity: 1; }

        .guild-logo-mini {
            position: absolute;
            width: 70%;
            height: 70%;
            object-fit: contain;
            opacity: 0.8;
        }

        /* Sidebar Stats */
        .sidebar-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 30px;
            margin-bottom: 20px;
        }

        .bonus-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .bonus-val { color: var(--accent); font-weight: 800; }

        .tooltip {
            position: absolute;
            background: #000;
            padding: 10px;
            border-radius: 8px;
            font-size: 0.8rem;
            display: none;
            z-index: 100;
            width: 150px;
            pointer-events: none;
        }

        .map-cell:hover .tooltip { display: block; bottom: 100%; left: 50%; transform: translateX(-50%); }

        .legend {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #94a3b8;
        }
        .legend-item { display: flex; align-items: center; gap: 5px; }
        .dot { width: 12px; height: 12px; border-radius: 50%; }

    </style>
</head>
<body>

    <div class="container">
        <header>
            <h1>GUILD TERRITORY MAP</h1>
            <p style="color: #94a3b8;">Mở rộng bờ cõi, nhận passive bonus cho toàn bang hội</p>
        </header>

        <div class="layout">
            <div class="map-section">
                <div class="map-grid">
                    <?php foreach ($territories as $t): 
                        $class = $t['guild_id'] ? ($t['guild_id'] == $myGuildId ? 'mine' : 'enemy') : '';
                        $icon = '';
                        switch($t['bonus_type']) {
                            case 'coin': $icon = '💰'; break;
                            case 'exp': $icon = '⭐'; break;
                            case 'drop_rate': $icon = '💎'; break;
                        }
                    ?>
                    <div class="map-cell <?= $class ?> <?= $t['guild_id'] ? 'occupied' : '' ?>">
                        <?php if ($t['guild_logo']): ?>
                            <img src="<?= $t['guild_logo'] ?>" class="guild-logo-mini" alt="">
                        <?php else: ?>
                            <span class="cell-icon"><?= $icon ?></span>
                        <?php endif; ?>
                        
                        <div class="tooltip">
                            <strong><?= $t['guild_name'] ?? 'Hoang dã' ?></strong><br>
                            Bonus: <?= $icon ?> +<?= round(($t['bonus_value'] - 1) * 100) ?>%<br>
                            Tọa độ: <?= $t['x'] ?>,<?= $t['y'] ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="legend">
                    <div class="legend-item"><div class="dot" style="background: var(--primary);"></div> Bang hội của bạn</div>
                    <div class="legend-item"><div class="dot" style="background: #ef4444;"></div> Bang hội đối thủ</div>
                    <div class="legend-item"><div class="dot" style="background: rgba(255,255,255,0.1);"></div> Đất trống</div>
                </div>
            </div>

            <div class="sidebar">
                <div class="sidebar-card">
                    <h3>📊 PASSIVE BONUSES</h3>
                    <?php if ($myBonuses): ?>
                        <div class="bonus-item">
                            <span>Vàng (Coin)</span>
                            <span class="bonus-val">+<?= round($myBonuses['coin'] * 100) ?>%</span>
                        </div>
                        <div class="bonus-item">
                            <span>Kinh nghiệm (EXP)</span>
                            <span class="bonus-val">+<?= round($myBonuses['exp'] * 100) ?>%</span>
                        </div>
                        <div class="bonus-item">
                            <span>Tỉ lệ rơi đồ (Drop)</span>
                            <span class="bonus-val">+<?= round($myBonuses['drop_rate'] * 100) ?>%</span>
                        </div>
                        <p style="font-size: 0.8rem; color: #94a3b8; margin-top: 15px;">Các chỉ số này sẽ được cộng trực tiếp vào mỗi ván thắng của bạn.</p>
                    <?php else: ?>
                        <p>Bạn chưa tham gia bang hội nào.</p>
                    <?php endif; ?>
                </div>

                <div class="sidebar-card" style="border-color: var(--accent);">
                    <h3 style="color: var(--accent);">⚔️ CÁCH CHIẾM ĐẤT</h3>
                    <p style="font-size: 0.9rem; line-height: 1.6;">Lãnh thổ được trao cho bang hội có điểm **Guild War** cao nhất tuần hoặc thông qua các trận thách đấu bang hội trực tiếp.</p>
                    <button style="width:100%; background: var(--accent); border:none; color:#000; padding:12px; border-radius:12px; font-weight:800; cursor:pointer;">THÁCH ĐẤU CHIẾM ĐẤT</button>
                </div>

                <a href="guilds.php" style="display:block; text-align:center; color:#94a3b8; text-decoration:none; font-weight:600; margin-top:20px;">
                    <i class="fa fa-arrow-left"></i> QUAY LẠI BANG HỘI
                </a>
            </div>
        </div>
    </div>

</body>
</html>
