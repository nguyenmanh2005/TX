<?php
session_start();
require_once 'db_connect.php';
require_once 'api_guild_social_helper.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['Iduser'];
$userGuild = $conn->query("SELECT g.id, g.Name FROM guild_members gm JOIN guilds g ON gm.guild_id = g.id WHERE gm.user_id = $userId")->fetch_assoc();

if (!$userGuild) {
    die("Bạn cần gia nhập bang hội để tham gia Raid Boss!");
}

$guildId = $userGuild['id'];

// Xử lý tấn công
if (isset($_POST['attack'])) {
    // Random damage từ 10,000 đến 100,000
    $damage = rand(10000, 100000);
    $res = GuildSocialHelper::attackRaidBoss($conn, $guildId, $userId, $damage);
    if ($res['success']) {
        $msg = "Bạn đã gây " . number_format($damage) . " sát thương!";
    } else {
        $error = $res['message'];
    }
}

// Xử lý triệu hồi Boss (nếu chưa có)
if (isset($_POST['spawn'])) {
    GuildSocialHelper::spawnRaidBoss($conn, $guildId);
}

$boss = $conn->query("SELECT * FROM guild_raid_bosses WHERE guild_id = $guildId AND status = 'active' AND expires_at > NOW()")->fetch_assoc();
$ranking = [];
if ($boss) {
    $ranking = $conn->query("SELECT u.Name, rp.damage_dealt FROM guild_raid_participation rp JOIN users u ON rp.user_id = u.Iduser WHERE rp.raid_id = {$boss['id']} ORDER BY rp.damage_dealt DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Guild Raid Boss | Epic Battle</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #020617;
            --boss-red: #ef4444;
            --card-bg: rgba(30, 41, 59, 0.7);
        }

        body {
            background: var(--bg);
            color: #fff;
            font-family: 'Outfit', sans-serif;
            margin: 0;
            padding: 40px;
            background-image: 
                radial-gradient(at 50% -20%, rgba(239, 68, 68, 0.15) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(99, 102, 241, 0.05) 0px, transparent 50%);
        }

        .container { max-width: 1000px; margin: 0 auto; }

        .boss-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 2px solid rgba(239, 68, 68, 0.2);
            border-radius: 40px;
            padding: 50px;
            text-align: center;
            box-shadow: 0 0 50px rgba(239, 68, 68, 0.1);
            margin-bottom: 40px;
        }

        .boss-name { font-size: 3.5rem; font-weight: 800; text-transform: uppercase; letter-spacing: 5px; color: var(--boss-red); text-shadow: 0 0 20px rgba(239, 68, 68, 0.5); margin: 0; }
        .boss-lvl { font-size: 1.2rem; font-weight: 600; color: #94a3b8; }

        .hp-container { margin: 40px 0; }
        .hp-bar { height: 30px; background: #000; border-radius: 15px; overflow: hidden; border: 2px solid var(--boss-red); position: relative; }
        .hp-fill { height: 100%; background: linear-gradient(90deg, #7f1d1d, #ef4444, #f87171); box-shadow: 0 0 20px var(--boss-red); transition: 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .hp-text { position: absolute; width: 100%; top: 0; left: 0; line-height: 30px; font-weight: 800; text-shadow: 0 0 5px #000; }

        .attack-zone { display: flex; justify-content: center; gap: 20px; }
        .btn-attack {
            background: var(--boss-red);
            color: #fff;
            border: none;
            padding: 20px 50px;
            border-radius: 20px;
            font-size: 1.5rem;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);
            transition: 0.3s;
        }
        .btn-attack:hover { transform: translateY(-5px) scale(1.05); box-shadow: 0 15px 30px rgba(239, 68, 68, 0.5); }
        .btn-attack:active { transform: scale(0.95); }

        .timer { font-size: 1.2rem; color: #94a3b8; margin-top: 20px; }

        .rank-card {
            background: var(--card-bg);
            border-radius: 24px;
            padding: 30px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .rank-item {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .rank-name { font-weight: 600; }
        .rank-dmg { color: var(--boss-red); font-weight: 800; }

        .no-boss {
            padding: 100px;
            text-align: center;
            background: rgba(255,255,255,0.02);
            border-radius: 30px;
            border: 2px dashed rgba(255,255,255,0.1);
        }

    </style>
</head>
<body>

    <div class="container">
        <header style="text-align: center; margin-bottom: 40px;">
            <h2 style="color: #94a3b8; text-transform: uppercase; letter-spacing: 2px;">Guild Raid</h2>
            <h3 style="font-size: 2rem;"><?= $userGuild['Name'] ?></h3>
        </header>

        <?php if ($boss): ?>
            <div class="boss-card">
                <p class="boss-lvl">GUILD RAID BOSS • LEVEL <?= $boss['level'] ?></p>
                <h1 class="boss-name"><?= $boss['boss_name'] ?></h1>
                
                <div class="hp-container">
                    <?php $perc = ($boss['current_hp'] / $boss['max_hp']) * 100; ?>
                    <div class="hp-bar">
                        <div class="hp-fill" style="width: <?= $perc ?>%;"></div>
                        <div class="hp-text"><?= number_format($boss['current_hp']) ?> / <?= number_format($boss['max_hp']) ?> HP</div>
                    </div>
                </div>

                <div class="attack-zone">
                    <form method="POST">
                        <button type="submit" name="attack" class="btn-attack">⚔️ TẤN CÔNG!</button>
                    </form>
                </div>

                <?php if (isset($msg)): ?>
                    <div style="margin-top:20px; color: #4ade80; font-weight: 800; font-size: 1.2rem;"><?= $msg ?></div>
                <?php endif; ?>

                <div class="timer">
                    <i class="fa fa-clock"></i> Thời gian còn lại: 
                    <span id="countdown"><?= $boss['expires_at'] ?></span>
                </div>
            </div>

            <div class="rank-card">
                <h3 style="margin-top: 0;"><i class="fa fa-ranking-star"></i> BẢNG ĐÓNG GÓP</h3>
                <?php if (empty($ranking)): ?>
                    <p style="color: #94a3b8;">Chưa có ai tấn công Boss.</p>
                <?php else: ?>
                    <?php foreach ($ranking as $i => $r): ?>
                        <div class="rank-item">
                            <span class="rank-name"><?= ($i+1) ?>. <?= $r['Name'] ?></span>
                            <span class="rank-dmg"><?= number_format($r['damage_dealt']) ?> DMG</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="no-boss">
                <i class="fa fa-skull" style="font-size: 4rem; color: #334155; margin-bottom: 20px;"></i>
                <h2>CHƯA CÓ BOSS XUẤT HIỆN</h2>
                <p style="color: #94a3b8; margin-bottom: 30px;">Boss Raid cần được triệu hồi bởi Chủ bang hoặc Phó bang.</p>
                <form method="POST">
                    <button type="submit" name="spawn" style="background: #fff; color: #000; border: none; padding: 15px 40px; border-radius: 12px; font-weight: 800; cursor: pointer;">TRIỆU HỒI BOSS (24H)</button>
                </form>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 50px;">
            <a href="guilds.php" style="color: #94a3b8; text-decoration: none; font-weight: 600;"><i class="fa fa-arrow-left"></i> QUAY LẠI BANG HỘI</a>
        </div>
    </div>

    <script>
        // Simple countdown script
        const expires = new Date("<?= $boss['expires_at'] ?? '' ?>").getTime();
        if (expires) {
            setInterval(() => {
                const now = new Date().getTime();
                const diff = expires - now;
                if (diff < 0) {
                    document.getElementById("countdown").innerHTML = "Hết thời gian!";
                    return;
                }
                const h = Math.floor(diff / (1000 * 60 * 60));
                const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const s = Math.floor((diff % (1000 * 60)) / 1000);
                document.getElementById("countdown").innerHTML = `${h}h ${m}m ${s}s`;
            }, 1000);
        }
    </script>
</body>
</html>
