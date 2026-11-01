<?php
session_start();

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../game_history_helper.php';
require_once __DIR__ . '/bot_streamer_helper.php';

$botUser = getOrCreateBotStreamerUser($conn, 'bot_62', 50000000);
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
$userName = $user ? $user['Name'] : 'bot_62';
$stmt->close();

// AJAX Action Handler cho thu hoạch mỏ
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];

    if ($action === 'claim_all') {
        $reward = rand(50000, 250000);
        $conn->query("UPDATE users SET Money = Money + $reward WHERE Iduser = $userId");
        $newMoney = $money + $reward;
        echo json_encode([
            'success' => true,
            'reward' => $reward,
            'balance' => number_format($newMoney, 0, ',', '.'),
            'message' => "Thu hoạch thành công +" . number_format($reward) . " GTLM!"
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
    <title>⛏️ Mỏ Khoáng Tycoon LiveStream - Bàn 62</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/game-mining.css">
    <style>
        body {
            margin: 0;
            background: <?= $bgGradientCSS ?? 'linear-gradient(135deg, #0f0c29, #302b63, #24243e)' ?>;
            color: #fff;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            padding: 20px;
        }
        .mining-live-card {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(15, 20, 35, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 215, 0, 0.25);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 15px 45px rgba(0,0,0,0.5);
        }
        .mine-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 25px 0;
        }
        .mine-tile {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 18px;
            padding: 20px;
            text-align: center;
            transition: 0.3s;
        }
        .mine-tile:hover {
            border-color: #ffd700;
            transform: translateY(-4px);
        }
        .btn-claim-live {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #fff;
            border: none;
            padding: 16px 36px;
            font-size: 1.15rem;
            font-weight: 800;
            border-radius: 50px;
            cursor: pointer;
            box-shadow: 0 0 20px rgba(245, 158, 11, 0.4);
            transition: 0.2s;
        }
        .btn-claim-live:hover { transform: scale(1.05); }
    </style>
</head>
<body>
    <div class="mining-live-card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h1 style="font-family:'Orbitron',sans-serif; color:#ffd700; margin:0; font-size:1.8rem;">⛏️ TYCOON MỎ KHOÁNG GTLM</h1>
                <p style="opacity:0.8; margin:5px 0 0 0;">Khai thác tự động, săn tinh thể kim cương & vàng rực rỡ</p>
            </div>
            <div style="background:rgba(255,215,0,0.15); border:1px solid #ffd700; padding:10px 20px; border-radius:16px; font-weight:800;">
                💰 <span id="balance-val"><?= number_format($money, 0, ',', '.') ?></span> GTLM
            </div>
        </div>

        <div class="mine-grid">
            <div class="mine-tile">
                <div style="font-size:2.5rem; margin-bottom:10px;">💎</div>
                <div style="font-weight:800; color:#38bdf8;">Mỏ Kim Cương VIP</div>
                <div style="font-size:0.85rem; opacity:0.7;">Tốc độ: 120.000 / h</div>
            </div>
            <div class="mine-tile">
                <div style="font-size:2.5rem; margin-bottom:10px;">🌟</div>
                <div style="font-weight:800; color:#fbbf24;">Mỏ Vàng Hoàng Kim</div>
                <div style="font-size:0.85rem; opacity:0.7;">Tốc độ: 85.000 / h</div>
            </div>
            <div class="mine-tile">
                <div style="font-size:2.5rem; margin-bottom:10px;">🔮</div>
                <div style="font-weight:800; color:#c084fc;">Mỏ Tinh Thể Lượng Tử</div>
                <div style="font-size:0.85rem; opacity:0.7;">Tốc độ: 60.000 / h</div>
            </div>
        </div>

        <div style="text-align:center; margin-top:20px;">
            <div style="margin-bottom:15px; font-size:1.2rem; font-weight:700;">
                Tích lũy khoáng sản: <span id="accumulatedDisplay" style="color:#10b981; font-family:'Orbitron'; font-size:1.4rem;">+150.000</span> GTLM
            </div>
            <button id="btnClaimAll" class="btn-claim-live">
                <i class="fa fa-hand-holding-dollar"></i> THU HOẠCH TẤT CẢ KHOÁNG SẢN
            </button>
        </div>
    </div>

    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="../assets/js/bot_chat.js"></script>
    <script src="bots/bot_62.js?v=<?= time() ?>"></script>
</body>
</html>
