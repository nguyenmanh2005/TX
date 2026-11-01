<?php
session_start();

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../game_history_helper.php';
require_once __DIR__ . '/bot_streamer_helper.php';

$botUser = getOrCreateBotStreamerUser($conn, 'bot_66', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;
$_SESSION['Iduser'] = $botUserId;

$userId = $botUserId;
require_once __DIR__ . '/../load_theme.php';

$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user ? (float)$user['Money'] : 50000000;
$stmt->close();

if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    if ($_GET['action'] === 'caro_move') {
        $win = (rand(0, 100) > 35);
        $reward = $win ? 190000 : 0;
        $diff = $win ? 90000 : -100000;
        $conn->query("UPDATE users SET Money = Money + $diff WHERE Iduser = $userId");
        $newMoney = $money + $diff;
        echo json_encode([
            'success' => true,
            'win' => $win,
            'reward' => $reward,
            'balance' => number_format($newMoney, 0, ',', '.'),
            'coord' => chr(65 + rand(0, 9)) . rand(1, 10),
            'pattern' => ['Khóa Chặt 4 Nước Thẳng', 'Bẫy Tam Hoa Đôi', 'Phản Công Ép Góc', 'Ngũ Tử Liền Hàng'][rand(0, 3)]
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>♟️ Cờ Ca-rô Đối Kháng LiveStream - Bàn 66</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        body { margin: 0; background: <?= $bgGradientCSS ?? '#0a1026' ?>; color: #fff; font-family: 'Outfit', sans-serif; min-height: 100vh; padding: 20px; }
        .caro-card { max-width: 800px; margin: 0 auto; background: rgba(14, 23, 42, 0.9); border-radius: 24px; border: 1px solid rgba(59, 130, 246, 0.35); padding: 30px; box-shadow: 0 15px 50px rgba(0,0,0,0.7); }
        .caro-board-mini { display: grid; grid-template-columns: repeat(7, 45px); gap: 4px; justify-content: center; margin: 25px 0; background: rgba(255,255,255,0.05); padding: 15px; border-radius: 16px; }
        .caro-cell { width: 45px; height: 45px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: 800; }
        .btn-move { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff; border: none; padding: 16px 40px; border-radius: 50px; font-weight: 800; font-size: 1.15rem; cursor: pointer; transition: 0.2s; box-shadow: 0 0 15px rgba(59,130,246,0.5); }
        .btn-move:hover { transform: scale(1.05); }
    </style>
</head>
<body>
    <div class="caro-card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h1 style="color:#60a5fa; margin:0; font-family:'Orbitron'; font-size:1.8rem;">♟️ CỜ CA-RÔ ĐỐI KHÁNG PvP</h1>
                <p style="opacity:0.8; margin-top:5px;">Trí tuệ đỉnh cao, bày binh bố trận khóa chặt 5 quân liền hàng</p>
            </div>
            <div style="background:rgba(59,130,246,0.15); border:1px solid #3b82f6; padding:10px 20px; border-radius:16px; font-weight:800;">
                💰 <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span> GTLM
            </div>
        </div>

        <div class="caro-board-mini" id="caroGrid">
            <?php
            $sampleMoves = ['❌', '⭕', '', '❌', '', '⭕', '', '', '❌', '⭕', '❌', '⭕', '', '❌', '', '', '❌', '⭕', '❌', '', '⭕', '', '❌', '', '⭕', '', '', '❌', '⭕', '', '❌', '⭕', '❌', '', ''];
            foreach ($sampleMoves as $m) {
                $color = ($m === '❌') ? '#ef4444' : '#38bdf8';
                echo "<div class='caro-cell' style='color:$color;'>$m</div>";
            }
            ?>
        </div>

        <div style="text-align:center; margin-top:20px;">
            <div id="caroLog" style="margin-bottom:15px; font-weight:700; color:#60a5fa;">Đại sư đang phân tích thế cờ đối thủ</div>
            <button id="btnCaroMove" class="btn-move">
                <i class="fa fa-chess-board"></i> HẠ QUÂN CHIẾN THUẬT
            </button>
        </div>
    </div>

    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="../assets/js/bot_chat.js"></script>
    <script src="bots/bot_66.js?v=<?= time() ?>"></script>
</body>
</html>
