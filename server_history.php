<?php
session_start();
require_once 'db_connect.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sử Ký Trận Địa - Những Huyền Thoại GTLM</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ancient-gold: #d4af37;
            --cyber-blue: #0ea5e9;
            --history-bg: #020617;
            --lore-panel: rgba(15, 23, 42, 0.8);
            --border-glow: rgba(212, 175, 55, 0.3);
        }

        body {
            background: var(--history-bg);
            color: #f8fafc;
            font-family: 'Outfit', sans-serif;
            margin: 0;
            padding: 0;
            background-image: 
                radial-gradient(circle at 50% 50%, rgba(14, 165, 233, 0.05) 0%, transparent 50%),
                url('https://www.transparenttextures.com/patterns/stardust.png');
            overflow-x: hidden;
        }

        .chronicle-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 100px 20px;
            position: relative;
        }

        /* 📜 Timeline Line */
        .chronicle-container::before {
            content: '';
            position: absolute;
            left: 50%;
            top: 200px;
            bottom: 100px;
            width: 2px;
            background: linear-gradient(to bottom, transparent, var(--ancient-gold), var(--cyber-blue), transparent);
            transform: translateX(-50%);
            opacity: 0.3;
        }

        .header {
            text-align: center;
            margin-bottom: 100px;
        }

        .header h1 {
            font-family: 'Cinzel', serif;
            font-size: 56px;
            margin: 0;
            background: linear-gradient(135deg, #fde047 0%, #d4af37 50%, #fde047 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-transform: uppercase;
            letter-spacing: 10px;
            filter: drop-shadow(0 0 20px rgba(253, 224, 71, 0.3));
        }

        .header p {
            color: #94a3b8;
            font-size: 18px;
            letter-spacing: 2px;
            margin-top: 20px;
            font-style: italic;
        }

        .lore-entry {
            position: relative;
            margin-bottom: 80px;
            width: 45%;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .lore-entry:nth-child(even) {
            margin-left: auto;
            text-align: left;
        }

        .lore-entry:nth-child(odd) {
            margin-right: auto;
            text-align: right;
        }

        /* 🔘 Center Point */
        .lore-entry::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            background: var(--ancient-gold);
            border: 4px solid var(--history-bg);
            border-radius: 50%;
            top: 20px;
            z-index: 10;
            box-shadow: 0 0 15px var(--ancient-gold);
        }

        .lore-entry:nth-child(odd)::after { right: -21.5%; }
        .lore-entry:nth-child(even)::after { left: -21.5%; }

        .lore-card {
            background: var(--lore-panel);
            backdrop-filter: blur(15px);
            padding: 30px;
            border-radius: 24px;
            border: 1px solid var(--border-glow);
            position: relative;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            overflow: hidden;
        }

        .lore-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, transparent, var(--ancient-gold), transparent);
        }

        .lore-date {
            font-family: 'Cinzel', serif;
            color: var(--ancient-gold);
            font-size: 14px;
            margin-bottom: 10px;
            display: block;
        }

        .lore-era {
            font-size: 11px;
            color: var(--cyber-blue);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 15px;
            display: block;
        }

        .lore-title {
            font-family: 'Cinzel', serif;
            font-size: 24px;
            margin: 0 0 15px 0;
            color: #fff;
        }

        .lore-desc {
            color: #cbd5e1;
            line-height: 1.8;
            font-size: 15px;
            margin: 0;
        }

        /* ✨ Importance Styles */
        .importance-3 {
            transform: scale(1.05);
            z-index: 5;
        }
        .importance-3 .lore-card {
            border: 2px solid var(--ancient-gold);
            box-shadow: 0 0 40px rgba(212, 175, 55, 0.2);
        }
        .importance-3 .lore-title {
            color: #fbbf24;
            text-shadow: 0 0 10px rgba(251, 191, 36, 0.4);
        }

        .lore-type-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 10px;
            background: rgba(255,255,255,0.05);
            padding: 4px 10px;
            border-radius: 50px;
            color: #94a3b8;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .nav-buttons {
            position: fixed;
            top: 30px;
            left: 30px;
            z-index: 100;
        }

        .btn-back {
            background: rgba(255,255,255,0.05);
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            font-size: 14px;
            transition: 0.3s;
        }

        .btn-back:hover {
            background: var(--ancient-gold);
            color: black;
        }

        /* 🔮 Prophecy Section */
        .prophecy-section {
            margin-top: 150px;
            padding: 50px;
            background: radial-gradient(circle at center, rgba(14, 165, 233, 0.1) 0%, transparent 70%);
            border-radius: 50px;
            text-align: center;
        }

        .prophecy-title {
            font-family: 'Cinzel', serif;
            font-size: 32px;
            color: var(--cyber-blue);
            margin-bottom: 30px;
        }

        .prophecy-content {
            font-size: 20px;
            color: #94a3b8;
            font-style: italic;
            max-width: 600px;
            margin: 0 auto;
        }

        @media (max-width: 768px) {
            .chronicle-container::before { left: 30px; }
            .lore-entry { width: 85% !important; margin-left: 60px !important; text-align: left !important; }
            .lore-entry::after { left: -42px !important; }
        }
    </style>
</head>
<body>

    <div class="nav-buttons">
        <a href="index.php" class="btn-back"><i class="fa fa-arrow-left"></i> Quay lại Sảnh</a>
    </div>

    <div class="chronicle-container">
        <div class="header">
            <h1>Sử Ký Trận Địa</h1>
            <p>"Nơi những ván cược trở thành huyền thoại, nơi kẻ vô danh hóa anh hùng."</p>
        </div>

        <div class="timeline">
            <?php
            $res = $conn->query("SELECT * FROM server_lore ORDER BY event_date DESC");
            if ($res && $res->num_rows > 0) {
                while ($row = $res->fetch_assoc()) {
                    $typeIcon = [
                        'record' => '🏆',
                        'guild' => '🏰',
                        'bot' => '🤖',
                        'boss' => '👹',
                        'community' => '📜'
                    ][$row['event_type']] ?? '✨';

                    echo "
                    <div class='lore-entry importance-{$row['importance_level']}'>
                        <div class='lore-card'>
                            <span class='lore-type-badge'>{$typeIcon} " . strtoupper($row['event_type']) . "</span>
                            <span class='lore-date'>" . date('d M, Y', strtotime($row['event_date'])) . "</span>
                            <span class='lore-era'>• {$row['era_name']} •</span>
                            <h2 class='lore-title'>{$row['event_title']}</h2>
                            <p class='lore-desc'>{$row['event_description']}</p>
                        </div>
                    </div>
                    ";
                }
            } else {
                echo "<p style='text-align:center; color: #94a3b8;'>Trang sử ký vẫn còn đang bỏ ngỏ, chờ đợi những anh hùng viết tiếp...</p>";
            }
            ?>
        </div>

        <div class="prophecy-section">
            <h2 class="prophecy-title">🔮 Lời Sấm Truyền</h2>
            <div class="prophecy-content">
                "Khi Ma Thần Hủy Diệt đạt đến cấp độ 100, một kho báu cổ xưa sẽ trỗi dậy từ lòng đất, biến kẻ trắng tay thành bậc đế vương của Trận Địa."
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Hiệu ứng Fade In khi cuộn
        $(window).scroll(function() {
            $('.lore-entry').each(function() {
                var top_of_element = $(this).offset().top;
                var bottom_of_screen = $(window).scrollTop() + $(window).innerHeight();
                if (bottom_of_screen > top_of_element + 100) {
                    $(this).css({'opacity': '1', 'transform': 'translateY(0)'});
                }
            });
        });

        $(document).ready(function() {
            $('.lore-entry').css({'opacity': '0', 'transform': 'translateY(50px)'});
            $(window).scroll(); // Trigger lần đầu
        });
    </script>
</body>
</html>
