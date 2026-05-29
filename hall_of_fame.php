<?php
require_once 'db_connect.php';
session_start();

/**
 * 🏛️ Public Hall of Fame - Bảng Vàng Trận Địa
 * Vinh danh những cao thủ kiệt xuất nhất trong tuần.
 */

// 1. Top Húp Đậm Nhất Tuần (Biggest Single Win)
$topWin = $conn->query("
    SELECT h.*, u.Name, u.ImageURL 
    FROM game_history h
    JOIN users u ON h.user_id = u.Iduser
    WHERE h.is_win = 1 AND h.played_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY h.win_amount DESC LIMIT 1
")->fetch_assoc();

// 2. Top Chiến Thần Chuỗi Thắng (Longest Streak - Giả lập từ history hoặc dùng bảng streaks)
$topStreak = $conn->query("
    SELECT s.*, u.Name, u.ImageURL 
    FROM user_streaks s
    JOIN users u ON s.user_id = u.Iduser
    ORDER BY s.longest_streak DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// 3. Top Đại Gia Burn GTLM (Highest Total Bet)
$topBurners = $conn->query("
    SELECT user_id, u.Name, u.ImageURL, SUM(bet_amount) as total_burned
    FROM game_history h
    JOIN users u ON h.user_id = u.Iduser
    WHERE h.played_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY user_id ORDER BY total_burned DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Bảng Vàng Trận Địa | Hall of Fame</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #020617;
            --gold: #fbbf24;
            --card: rgba(30, 41, 59, 0.7);
        }
        body {
            background: var(--bg);
            color: #f8fafc;
            font-family: 'Inter', sans-serif;
            padding: 40px;
            background-image: radial-gradient(circle at 50% 50%, #1e1b4b 0%, #020617 100%);
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 60px; }
        .header h1 { font-size: 48px; font-weight: 900; color: var(--gold); text-transform: uppercase; letter-spacing: 5px; }
        
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        
        .hof-card {
            background: var(--card);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 30px;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }
        .hof-card::before {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: conic-gradient(transparent, rgba(251, 191, 36, 0.1), transparent 30%);
            animation: rotate 10s linear infinite;
        }
        @keyframes rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        .big-win-hero {
            grid-column: span 2;
            text-align: center;
            border: 2px solid var(--gold);
            box-shadow: 0 0 50px rgba(251, 191, 36, 0.2);
        }
        .hero-avatar { width: 120px; height: 120px; border-radius: 50%; border: 4px solid var(--gold); margin-bottom: 20px; }
        .hero-name { font-size: 32px; font-weight: 900; color: #fff; margin-bottom: 10px; }
        .hero-amount { font-size: 54px; font-weight: 900; color: var(--gold); margin: 10px 0; }

        .list-item {
            display: flex; align-items: center; gap: 15px; padding: 15px;
            background: rgba(0,0,0,0.2); border-radius: 15px; margin-bottom: 10px;
        }
        .rank { font-size: 20px; font-weight: 900; color: var(--gold); width: 30px; }
        .item-avatar { width: 45px; height: 45px; border-radius: 50%; }
        .item-name { flex: 1; font-weight: 700; }
        .item-val { font-weight: 900; color: var(--gold); }
        
        .section-title { font-size: 24px; font-weight: 900; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Bảng Vàng Trận Địa</h1>
            <p style="opacity: 0.6;">Vinh danh những huyền thoại húp lộc tuần này</p>
        </div>

        <div class="grid">
            <!-- 🏆 HERO: Biggest Win -->
            <?php if ($topWin): ?>
            <div class="hof-card big-win-hero">
                <div style="position: relative; z-index: 1;">
                    <div class="section-title" style="justify-content: center; color: var(--gold);">
                        <i class="fa fa-crown"></i> KỶ LỤC HÚP ĐẬM TUẦN
                    </div>
                    <img src="<?= $topWin['ImageURL'] ?: 'img/avatar_default.png' ?>" class="hero-avatar">
                    <div class="hero-name"><?= htmlspecialchars($topWin['Name']) ?></div>
                    <div class="hero-amount"><?= number_format($topWin['win_amount']) ?> GTLM</div>
                    <div style="opacity: 0.7;">Tại Trận Địa: <?= htmlspecialchars($topWin['game_name']) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 🔥 Top Streaks -->
            <div class="hof-card">
                <div style="position: relative; z-index: 1;">
                    <div class="section-title"><i class="fa fa-fire" style="color: #ef4444;"></i> CHIẾN THẦN CHUỖI THẮNG</div>
                    <?php foreach ($topStreak as $i => $s): ?>
                    <div class="list-item">
                        <div class="rank">#<?= $i+1 ?></div>
                        <img src="<?= $s['ImageURL'] ?: 'img/avatar_default.png' ?>" class="item-avatar">
                        <div class="item-name"><?= htmlspecialchars($s['Name']) ?></div>
                        <div class="item-val"><?= $s['longest_streak'] ?> Ngày</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 💰 Top Burners -->
            <div class="hof-card">
                <div style="position: relative; z-index: 1;">
                    <div class="section-title"><i class="fa fa-bolt" style="color: #38bdf8;"></i> ĐẠI GIA RA CHIÊU</div>
                    <?php foreach ($topBurners as $i => $b): ?>
                    <div class="list-item">
                        <div class="rank">#<?= $i+1 ?></div>
                        <img src="<?= $b['ImageURL'] ?: 'img/avatar_default.png' ?>" class="item-avatar">
                        <div class="item-name"><?= htmlspecialchars($b['Name']) ?></div>
                        <div class="item-val"><?= number_format($b['total_burned']) ?> GTLM</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 50px;">
            <a href="index.php" style="color: var(--gold); text-decoration: none; font-weight: 800;">
                <i class="fa fa-arrow-left"></i> QUAY LẠI TRẬN ĐỊA
            </a>
        </div>
    </div>
</body>
</html>
