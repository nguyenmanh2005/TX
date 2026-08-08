<?php
require_once 'db_connect.php';
session_start();

/**
 * 🏛️ Public Hall of Fame - Bảng Vàng Trận Địa
 * Vinh danh những cao thủ kiệt xuất nhất trong tuần.
 */

// 1. Top Húp Đậm Nhất Tuần (Biggest Net Winnings)
$topWin = $conn->query("
    SELECT h.user_id, u.Name, u.ImageURL, SUM(CASE WHEN h.is_win = 1 THEN h.win_amount - h.bet_amount ELSE -h.bet_amount END) as net_winnings
    FROM game_history h
    JOIN users u ON h.user_id = u.Iduser
    WHERE h.played_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND u.Email NOT REGEXP '^bot[0-9]+@'
    GROUP BY h.user_id
    HAVING net_winnings > 0
    ORDER BY net_winnings DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// 2. Top Cày Cuốc (Most Games Played)
$topGrinders = $conn->query("
    SELECT h.user_id, u.Name, u.ImageURL, COUNT(h.id) as total_games
    FROM game_history h
    JOIN users u ON h.user_id = u.Iduser
    WHERE h.played_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND u.Email NOT REGEXP '^bot[0-9]+@'
    GROUP BY h.user_id
    ORDER BY total_games DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// 3. Top Đại Gia Burn GTLM (Highest Total Bet)
$topBurners = $conn->query("
    SELECT h.user_id, u.Name, u.ImageURL, SUM(h.bet_amount) as total_burned
    FROM game_history h
    JOIN users u ON h.user_id = u.Iduser
    WHERE h.played_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND u.Email NOT REGEXP '^bot[0-9]+@'
    GROUP BY h.user_id 
    ORDER BY total_burned DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đại Lộ Danh Vọng | Hall of Fame</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap');
        
        :root {
            --bg-color: #0f0c29;
            --gold: #FFD700;
            --silver: #C0C0C0;
            --bronze: #CD7F32;
        }

        body {
            background: linear-gradient(135deg, var(--bg-color), #302b63, #24243e);
            color: #fff;
            font-family: 'Outfit', sans-serif;
            margin: 0;
            padding: 40px 15px;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .header {
            text-align: center;
            margin-bottom: 50px;
            animation: fadeInDown 1s ease;
        }

        .header h1 {
            font-size: 3rem;
            font-weight: 900;
            background: linear-gradient(to right, #bf953f, #fcf6ba, #b38728, #fbf5b7, #aa771c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin: 0;
            text-shadow: 0 5px 15px rgba(255, 215, 0, 0.2);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .categories {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }

        .category-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 12px 30px;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            backdrop-filter: blur(5px);
        }

        .category-btn.active, .category-btn:hover {
            background: linear-gradient(45deg, #FFD700, #FDB931);
            color: #000;
            border-color: transparent;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.4);
            transform: translateY(-2px);
        }

        /* 3D Podium Design */
        .podium-container {
            display: none;
            justify-content: center;
            align-items: flex-end;
            gap: 15px;
            height: 450px;
            margin-top: 50px;
            animation: zoomIn 0.5s ease;
        }
        .podium-container.active {
            display: flex;
        }

        .podium-slot {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 30%;
            max-width: 250px;
            position: relative;
        }

        .podium-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            margin-bottom: 15px;
            position: relative;
            z-index: 2;
            box-shadow: 0 10px 20px rgba(0,0,0,0.5);
            transition: transform 0.3s;
        }

        .podium-slot:hover .podium-avatar {
            transform: scale(1.1);
        }

        .rank-crown {
            position: absolute;
            top: -40px;
            font-size: 40px;
            z-index: 3;
            animation: float 2s infinite ease-in-out;
        }

        .podium-name {
            font-size: 1.3rem;
            font-weight: 800;
            text-align: center;
            margin-bottom: 5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.8);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }

        .podium-value {
            font-size: 1.1rem;
            color: #FFD700;
            font-weight: 700;
            margin-bottom: 15px;
            background: rgba(0,0,0,0.5);
            padding: 4px 12px;
            border-radius: 12px;
        }

        .podium-base {
            width: 100%;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 3rem;
            font-weight: 900;
            color: rgba(0,0,0,0.3);
            box-shadow: inset 0 20px 50px rgba(255,255,255,0.2), 0 -10px 20px rgba(0,0,0,0.5);
            position: relative;
            overflow: hidden;
        }

        .podium-base::after {
            content: '';
            position: absolute;
            top: 0; left: -100%; width: 50%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shine 3s infinite;
        }

        /* Rank 1 - Center */
        .rank-1 { order: 2; z-index: 3; }
        .rank-1 .podium-avatar { width: 140px; height: 140px; border-color: var(--gold); box-shadow: 0 0 30px var(--gold); }
        .rank-1 .rank-crown { color: var(--gold); font-size: 60px; top: -50px; }
        .rank-1 .podium-base { height: 220px; background: linear-gradient(to bottom, #FFD700, #B8860B); }

        /* Rank 2 - Left */
        .rank-2 { order: 1; z-index: 2; }
        .rank-2 .podium-avatar { border-color: var(--silver); box-shadow: 0 0 20px var(--silver); }
        .rank-2 .rank-crown { color: var(--silver); }
        .rank-2 .podium-base { height: 160px; background: linear-gradient(to bottom, #E0E0E0, #808080); }

        /* Rank 3 - Right */
        .rank-3 { order: 3; z-index: 1; }
        .rank-3 .podium-avatar { border-color: var(--bronze); box-shadow: 0 0 15px var(--bronze); }
        .rank-3 .rank-crown { color: var(--bronze); font-size: 30px; top: -30px; }
        .rank-3 .podium-base { height: 120px; background: linear-gradient(to bottom, #CD7F32, #8B4513); }

        /* Vùng Danh Sách Top 4-10 */
        .top-list-container {
            width: 100%;
            max-width: 800px;
            margin: 40px auto 0;
            background: rgba(0,0,0,0.3);
            border-radius: 20px;
            padding: 20px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .top-list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            margin-bottom: 10px;
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            transition: all 0.3s;
        }

        .top-list-item:hover {
            background: rgba(255,255,255,0.1);
            transform: scale(1.02);
        }

        .list-rank {
            font-size: 1.5rem;
            font-weight: 900;
            color: #888;
            width: 50px;
        }

        .list-player-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }

        .list-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.2);
        }

        .list-name {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .list-value {
            font-size: 1.2rem;
            color: var(--gold);
            font-weight: 800;
            text-align: right;
        }

        /* Hiệu ứng Chữ Lấp Lánh */
        .sparkle-text {
            background: linear-gradient(90deg, #ff0000, #ffff00, #ff00f3, #0033ff, #ff00c4, #ff0000);
            background-size: 400%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: animateGradient 5s linear infinite;
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
            font-weight: 900 !important;
        }

        .sparkle-gold {
            background: linear-gradient(90deg, #FFDF00, #D4AF37, #FFDF00, #FFF8DC, #FFDF00);
            background-size: 300%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: animateGradient 3s linear infinite;
            text-shadow: 0 0 15px rgba(255, 215, 0, 0.8);
        }

        /* Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        @keyframes shine {
            100% { left: 200%; }
        }
        @keyframes animateGradient {
            0% { background-position: 0% 50%; }
            100% { background-position: 100% 50%; }
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes zoomIn {
            from { opacity: 0; transform: scale(0.8); }
            to { opacity: 1; transform: scale(1); }
        }

        .back-btn {
            display: inline-block;
            margin-top: 50px;
            color: #fff;
            text-decoration: none;
            font-size: 1.2rem;
            font-weight: 700;
            background: rgba(255,255,255,0.1);
            padding: 12px 30px;
            border-radius: 30px;
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .back-btn:hover {
            background: rgba(255,255,255,0.2);
            transform: translateX(-5px);
        }

        @media (max-width: 768px) {
            .header h1 { font-size: 2rem; }
            .podium-slot { width: 33%; }
            .rank-1 .podium-avatar { width: 90px; height: 90px; }
            .rank-2 .podium-avatar, .rank-3 .podium-avatar { width: 70px; height: 70px; }
            .podium-name { font-size: 1rem; }
            .podium-value { font-size: 0.9rem; padding: 2px 8px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Đại Lộ Danh Vọng</h1>
            <p>Vinh danh những huyền thoại của Trận Địa trong tuần qua</p>
        </div>

        <div class="categories">
            <button class="category-btn active" onclick="showLeaderboard('profit')"><i class="fa fa-trophy"></i> Top Lợi Nhuận</button>
            <button class="category-btn" onclick="showLeaderboard('burner')"><i class="fa fa-fire"></i> Top Đại Gia</button>
            <button class="category-btn" onclick="showLeaderboard('grinder')"><i class="fa fa-khanda"></i> Top Dân Cày</button>
        </div>

        <!-- Bục Vinh Quang: Top Lợi Nhuận -->
        <div id="board-profit" class="podium-container active">
            <?php renderPodium($topWin, 'GTLM', 'net_winnings'); ?>
        </div>

        <!-- Bục Vinh Quang: Top Đại Gia -->
        <div id="board-burner" class="podium-container">
            <?php renderPodium($topBurners, 'GTLM', 'total_burned'); ?>
        </div>

        <!-- Bục Vinh Quang: Top Dân Cày -->
        <div id="board-grinder" class="podium-container">
            <?php renderPodium($topGrinders, 'Ván', 'total_games'); ?>
        </div>

        <div style="text-align: center;">
            <a href="index.php" class="back-btn"><i class="fa fa-arrow-left"></i> Quay lại Sảnh Chính</a>
        </div>
    </div>

    <?php
    function renderPodium($dataList, $unit, $valueKey) {
        // --- 1. RENDER BỤC 3D (TOP 1, 2, 3) ---
        echo "<div style='display: flex; justify-content: center; align-items: flex-end; gap: 15px; width: 100%; height: 350px;'>";
        $order = [1, 0, 2]; // Vị trí render: Rank 2, Rank 1, Rank 3
        
        foreach ($order as $index) {
            $player = isset($dataList[$index]) ? $dataList[$index] : ['Name' => 'Chưa có', 'ImageURL' => 'img/avatar_default.png', $valueKey => 0];
            $rank = $index + 1;
            $avatar = $player['ImageURL'] ?: 'img/avatar_default.png';
            $name = htmlspecialchars($player['Name']);
            $val = is_numeric($player[$valueKey]) ? number_format($player[$valueKey]) : $player[$valueKey];
            
            // Hiệu ứng chữ lấp lánh cho Top 1
            $nameClass = ($rank == 1) ? 'podium-name sparkle-text' : 'podium-name sparkle-gold';

            echo "
            <div class='podium-slot rank-{$rank}'>
                <i class='fa fa-crown rank-crown'></i>
                <img src='{$avatar}' class='podium-avatar' alt='Avatar'>
                <div class='{$nameClass}'>{$name}</div>
                <div class='podium-value'>{$val} {$unit}</div>
                <div class='podium-base'>{$rank}</div>
            </div>";
        }
        echo "</div>";

        // --- 2. RENDER LIST (TOP 4 -> 10) ---
        if (count($dataList) > 3) {
            echo "<div class='top-list-container'>";
            for ($i = 3; $i < min(10, count($dataList)); $i++) {
                $player = $dataList[$i];
                $rank = $i + 1;
                $avatar = $player['ImageURL'] ?: 'img/avatar_default.png';
                $name = htmlspecialchars($player['Name']);
                $val = is_numeric($player[$valueKey]) ? number_format($player[$valueKey]) : $player[$valueKey];

                echo "
                <div class='top-list-item'>
                    <div class='list-rank'>#{$rank}</div>
                    <div class='list-player-info'>
                        <img src='{$avatar}' class='list-avatar'>
                        <div class='list-name'>{$name}</div>
                    </div>
                    <div class='list-value'>{$val} {$unit}</div>
                </div>";
            }
            echo "</div>";
        }
    }
    ?>

    <script>
        function showLeaderboard(id) {
            // Đổi active button
            document.querySelectorAll('.category-btn').forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');

            // Đổi active board
            document.querySelectorAll('.podium-container').forEach(board => board.classList.remove('active'));
            document.getElementById('board-' + id).classList.add('active');
            
            // Bắn pháo hoa mỗi lần chuyển tab
            fireConfetti();
        }

        function fireConfetti() {
            var duration = 3000;
            var end = Date.now() + duration;

            (function frame() {
                confetti({
                    particleCount: 5,
                    angle: 60,
                    spread: 55,
                    origin: { x: 0 },
                    colors: ['#FFD700', '#FFA500', '#FFFFFF']
                });
                confetti({
                    particleCount: 5,
                    angle: 120,
                    spread: 55,
                    origin: { x: 1 },
                    colors: ['#FFD700', '#FFA500', '#FFFFFF']
                });

                if (Date.now() < end) {
                    requestAnimationFrame(frame);
                }
            }());
        }

        // Tự động bắn pháo hoa khi load trang
        window.onload = function() {
            setTimeout(fireConfetti, 500);
        };
    </script>
</body>
</html>
