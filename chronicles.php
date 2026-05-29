<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require_once 'load_theme.php';

// Đảm bảo bảng tồn tại (không DDL trong thực tế, giả sử đã có hoặc hướng dẫn user chạy trong SQL)
$lore = [];
$check = $conn->query("SHOW TABLES LIKE 'server_lore'");
if ($check->num_rows > 0) {
    $res = $conn->query("SELECT * FROM server_lore ORDER BY event_date DESC");
    while ($row = $res->fetch_assoc()) {
        $lore[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Biên Niên Sử Trận Địa</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Cormorant+Garamond:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        body {
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #d4af37;
            font-family: 'Cormorant Garamond', serif;
            margin: 0;
            padding: 40px 20px;
        }

        .chronicles-container {
            max-width: 900px;
            margin: 0 auto;
            background: rgba(10, 10, 15, 0.9);
            border: 2px solid #8b6b23;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 0 50px rgba(139, 107, 35, 0.2), inset 0 0 20px rgba(139, 107, 35, 0.1);
            position: relative;
        }

        .chronicles-container::before {
            content: '';
            position: absolute;
            top: 10px; left: 10px; right: 10px; bottom: 10px;
            border: 1px solid rgba(212, 175, 55, 0.3);
            pointer-events: none;
        }

        .header-title {
            text-align: center;
            font-family: 'Cinzel', serif;
            font-size: 48px;
            color: #d4af37;
            text-shadow: 0 0 10px rgba(212, 175, 55, 0.5);
            margin-bottom: 50px;
            letter-spacing: 5px;
        }

        .timeline {
            position: relative;
            padding: 20px 0;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 0; bottom: 0; left: 50%;
            width: 2px;
            background: linear-gradient(to bottom, transparent, #8b6b23, transparent);
            transform: translateX(-50%);
        }

        .event {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            position: relative;
        }

        .event:nth-child(even) {
            flex-direction: row-reverse;
        }

        .event-date {
            width: 45%;
            text-align: right;
            font-size: 24px;
            font-style: italic;
            color: #b89947;
        }

        .event:nth-child(even) .event-date {
            text-align: left;
        }

        .event-content {
            width: 45%;
            background: rgba(255, 255, 255, 0.03);
            padding: 20px;
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 5px;
            transition: 0.3s;
        }

        .event-content:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(212, 175, 55, 0.5);
            box-shadow: 0 0 20px rgba(212, 175, 55, 0.1);
        }

        .event-title {
            font-family: 'Cinzel', serif;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #fff;
        }

        .event-desc {
            font-size: 18px;
            line-height: 1.6;
            color: #ccc;
        }

        .event-dot {
            position: absolute;
            left: 50%;
            width: 16px;
            height: 16px;
            background: #d4af37;
            border-radius: 50%;
            transform: translateX(-50%);
            box-shadow: 0 0 10px #d4af37;
        }

        .nav-back {
            display: inline-block;
            margin-bottom: 20px;
            color: #d4af37;
            text-decoration: none;
            font-family: 'Cinzel', serif;
            font-size: 18px;
            transition: 0.3s;
        }

        .nav-back:hover {
            color: #fff;
            text-shadow: 0 0 5px #fff;
        }

        .empty-lore {
            text-align: center;
            font-size: 20px;
            color: #888;
            font-style: italic;
            padding: 50px;
        }
    </style>
</head>
<body>
    <div class="chronicles-container">
        <a href="index.php" class="nav-back">← Trở Về Trận Địa</a>
        <h1 class="header-title">Biên Niên Sử Server</h1>
        
        <?php if (empty($lore)): ?>
            <div class="empty-lore">Trang sách còn dang dở... Chưa có sự kiện lịch sử nào được ghi lại.</div>
        <?php else: ?>
            <div class="timeline">
                <?php foreach ($lore as $item): ?>
                    <div class="event">
                        <div class="event-date"><?= date('d/m/Y', strtotime($item['event_date'])) ?></div>
                        <div class="event-dot"></div>
                        <div class="event-content">
                            <div class="event-title"><?= htmlspecialchars($item['title']) ?></div>
                            <div class="event-desc"><?= nl2br(htmlspecialchars($item['description'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
