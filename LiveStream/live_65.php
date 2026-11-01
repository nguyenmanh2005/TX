<?php
session_start();

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../game_history_helper.php';
require_once __DIR__ . '/bot_streamer_helper.php';

$botUser = getOrCreateBotStreamerUser($conn, 'bot_65', 50000000);
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
    if ($_GET['action'] === 'pvp_battle') {
        $win = (rand(0, 100) > 40); // 60% win rate for pro streamer
        $bet = 100000;
        $reward = $win ? $bet * 1.95 : 0;
        $diff = $win ? ($reward - $bet) : -$bet;
        $conn->query("UPDATE users SET Money = Money + $diff WHERE Iduser = $userId");
        $newMoney = $money + $diff;
        echo json_encode([
            'success' => true,
            'win' => $win,
            'reward' => $reward,
            'bet' => $bet,
            'balance' => number_format($newMoney, 0, ',', '.'),
            'opponent' => ['Đao Thần Ma Giáo 🗡️', 'Thiết Giáp Chiến Vương 🛡️', 'Cung Thủ Phong Lôi 🏹', 'Huyết Ma Kiếm Sĩ ⚔️'][rand(0, 3)]
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
    <title>⚔️ Đấu Trường PvP LiveStream - Bàn 65</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        body { margin: 0; background: <?= $bgGradientCSS ?? '#1f0d0d' ?>; color: #fff; font-family: 'Outfit', sans-serif; min-height: 100vh; padding: 20px; }
        .arena-card { max-width: 800px; margin: 0 auto; background: rgba(35, 12, 12, 0.9); border-radius: 24px; border: 1px solid rgba(239, 68, 68, 0.35); padding: 30px; box-shadow: 0 15px 50px rgba(0,0,0,0.7); }
        .versus-box { display: flex; justify-content: space-around; align-items: center; margin: 30px 0; background: rgba(0,0,0,0.3); border-radius: 20px; padding: 25px; border: 1px solid rgba(255,255,255,0.08); }
        .fighter { text-align: center; }
        .fighter-avatar { font-size: 3.5rem; margin-bottom: 8px; }
        .vs-badge { font-family: 'Orbitron'; font-size: 2.5rem; font-weight: 900; color: #ef4444; text-shadow: 0 0 15px rgba(239,68,68,0.6); }
        .btn-fight { background: linear-gradient(135deg, #ef4444, #b91c1c); color: #fff; border: none; padding: 18px 45px; border-radius: 50px; font-weight: 900; font-size: 1.2rem; cursor: pointer; transition: 0.2s; box-shadow: 0 0 20px rgba(239,68,68,0.5); }
        .btn-fight:hover { transform: scale(1.06); }
    </style>
</head>
<body>
    <div class="arena-card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h1 style="color:#f87171; margin:0; font-family:'Orbitron'; font-size:1.8rem;">⚔️ ĐẤU TRƯỜNG PvP ĐỈNH CAO</h1>
                <p style="opacity:0.8; margin-top:5px;">Khiêu chiến cao thủ liên minh, tranh tài rực lửa 1vs1</p>
            </div>
            <div style="background:rgba(239,68,68,0.15); border:1px solid #ef4444; padding:10px 20px; border-radius:16px; font-weight:800;">
                💰 <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span> GTLM
            </div>
        </div>

        <div class="versus-box">
            <div class="fighter">
                <div class="fighter-avatar">👑</div>
                <div style="font-weight:800; color:#fbbf24;">Chiến Thần Đấu Trường</div>
                <div style="font-size:0.85rem; color:#4ade80;">Bất bại 12 trận</div>
            </div>
            <div class="vs-badge">VS</div>
            <div class="fighter">
                <div class="fighter-avatar" id="oppAvatar">🗡️</div>
                <div style="font-weight:800; color:#f87171;" id="oppName">Đao Thần Ma Giáo</div>
                <div style="font-size:0.85rem; opacity:0.7;">Cao thủ khiêu chiến</div>
            </div>
        </div>

        <div style="text-align:center; margin-top:20px;">
            <div id="battleLog" style="margin-bottom:15px; font-weight:700; color:#fbbf24;">Sẵn sàng ra chiêu thách đấu 100.000 GTLM</div>
            <button id="btnFight" class="btn-fight">
                <i class="fa fa-swords"></i> XUẤT CHIÊU KHIÊU CHIẾN
            </button>
        </div>
    </div>

    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="../assets/js/bot_chat.js"></script>
    <script src="bots/bot_65.js?v=<?= time() ?>"></script>
</body>
</html>
