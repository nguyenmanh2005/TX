<?php
session_start();

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../game_history_helper.php';
require_once __DIR__ . '/bot_streamer_helper.php';

$botUser = getOrCreateBotStreamerUser($conn, 'bot_64', 50000000);
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
    if ($_GET['action'] === 'train_pet') {
        $xp = rand(50, 200);
        $reward = rand(15000, 80000);
        $conn->query("UPDATE users SET Money = Money + $reward WHERE Iduser = $userId");
        $newMoney = $money + $reward;
        echo json_encode([
            'success' => true,
            'xp' => $xp,
            'reward' => $reward,
            'balance' => number_format($newMoney, 0, ',', '.'),
            'pet' => ['Cửu Vĩ Linh Hồ 🦊', 'Kỳ Lân Hoàng Kim 🦄', 'Bạch Hổ Thần Thú 🐯', 'Huyền Vũ Băng Giá 🐢'][rand(0, 3)]
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
    <title>🐾 Vườn Linh Thú LiveStream - Bàn 64</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Orbitron:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        body { margin: 0; background: <?= $bgGradientCSS ?? '#051811' ?>; color: #fff; font-family: 'Outfit', sans-serif; min-height: 100vh; padding: 20px; }
        .pets-card { max-width: 800px; margin: 0 auto; background: rgba(10, 30, 22, 0.9); border-radius: 24px; border: 1px solid rgba(16, 185, 129, 0.3); padding: 30px; box-shadow: 0 15px 50px rgba(0,0,0,0.6); }
        .pets-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 25px 0; }
        .pet-item { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 18px; padding: 15px; text-align: center; }
        .pet-avatar { font-size: 3rem; margin-bottom: 8px; }
        .btn-train { background: linear-gradient(135deg, #10b981, #059669); color: #fff; border: none; padding: 16px 36px; border-radius: 50px; font-weight: 800; font-size: 1.1rem; cursor: pointer; transition: 0.2s; box-shadow: 0 0 15px rgba(16,185,129,0.4); }
        .btn-train:hover { transform: scale(1.05); }
    </style>
</head>
<body>
    <div class="pets-card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h1 style="color:#10b981; margin:0; font-family:'Orbitron'; font-size:1.8rem;">🐾 VƯỜN LINH THÚ GTLM</h1>
                <p style="opacity:0.8; margin-top:5px;">Nuôi dưỡng, ấp trứng & huấn luyện linh thú xuất trận</p>
            </div>
            <div style="background:rgba(16,185,129,0.15); border:1px solid #10b981; padding:10px 20px; border-radius:16px; font-weight:800;">
                💰 <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span> GTLM
            </div>
        </div>

        <div class="pets-grid">
            <div class="pet-item">
                <div class="pet-avatar">🦊</div>
                <div style="font-weight:800; color:#fb923c;">Cửu Vĩ Hồ</div>
                <div style="font-size:0.8rem; opacity:0.7;">Cấp 45 • Tinh anh</div>
            </div>
            <div class="pet-item">
                <div class="pet-avatar">🦄</div>
                <div style="font-weight:800; color:#f472b6;">Kỳ Lân Vàng</div>
                <div style="font-size:0.8rem; opacity:0.7;">Cấp 60 • Thần thoại</div>
            </div>
            <div class="pet-item">
                <div class="pet-avatar">🐯</div>
                <div style="font-weight:800; color:#facc15;">Bạch Hổ</div>
                <div style="font-size:0.8rem; opacity:0.7;">Cấp 52 • Chiến thần</div>
            </div>
            <div class="pet-item">
                <div class="pet-avatar">🐢</div>
                <div style="font-weight:800; color:#38bdf8;">Huyền Vũ</div>
                <div style="font-size:0.8rem; opacity:0.7;">Cấp 48 • Hộ vệ</div>
            </div>
        </div>

        <div style="text-align:center; margin-top:20px;">
            <div id="petLog" style="margin-bottom:15px; color:#34d399; font-weight:700;">Linh thú đang tràn đầy sinh lực sẵn sàng huấn luyện</div>
            <button id="btnTrainPet" class="btn-train">
                <i class="fa fa-paw"></i> HUẤN LUYỆN LINH THÚ RINH LỘC
            </button>
        </div>
    </div>

    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="../assets/js/bot_chat.js"></script>
    <script src="bots/bot_64.js?v=<?= time() ?>"></script>
</body>
</html>
