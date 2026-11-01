<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

$userId = (int)$_SESSION['Iduser'];
$challengeId = (int)($_GET['id'] ?? 0);

// Lấy thông tin người dùng hiện tại
$stmtUser = $conn->prepare("SELECT Name, Money, ImageURL FROM users WHERE Iduser = ?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$currUser = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

$userName = $currUser['Name'] ?? 'Kỳ Thủ';
$userMoney = (float)($currUser['Money'] ?? 0);
$userAvatar = !empty($currUser['ImageURL']) ? $currUser['ImageURL'] : 'img/avatar_default.png';

// Nạp Theme nếu có
require_once 'load_theme.php';

$challenge = null;
if ($challengeId > 0) {
    $sql = "SELECT c.*, u1.Name as challenger_name, u1.ImageURL as challenger_avatar,
                   u2.Name as opponent_name, u2.ImageURL as opponent_avatar
            FROM pvp_challenges c
            JOIN users u1 ON c.challenger_id = u1.Iduser
            JOIN users u2 ON c.opponent_id = u2.Iduser
            WHERE c.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $challengeId);
    $stmt->execute();
    $challenge = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$challenge || $challenge['game_type'] != 'caro' || 
        ($challenge['challenger_id'] != $userId && $challenge['opponent_id'] != $userId)) {
        header("Location: pvp_caro.php");
        exit();
    }
} else {
    // Lấy các trận đấu Cờ Ca-rô đang diễn ra hoặc đang chờ của người chơi
    $stmtMy = $conn->prepare("
        SELECT c.*, 
               u1.Name as challenger_name, 
               u2.Name as opponent_name 
        FROM pvp_challenges c
        JOIN users u1 ON c.challenger_id = u1.Iduser
        JOIN users u2 ON c.opponent_id = u2.Iduser
        WHERE (c.challenger_id = ? OR c.opponent_id = ?) 
          AND c.game_type = 'caro' 
          AND c.status IN ('pending', 'accepted')
        ORDER BY c.id DESC LIMIT 6
    ");
    $stmtMy->bind_param("ii", $userId, $userId);
    $stmtMy->execute();
    $myMatches = $stmtMy->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtMy->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $challenge ? "Bàn Cờ Ca-rô PvP #$challengeId" : "♟️ Đấu Trường Cờ Ca-rô PvP Độc Lập" ?> | GTLM Gaming</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #1d4ed8;
            --gold: #fbbf24;
            --emerald: #10b981;
            --rose: #ef4444;
            --bg-dark: #070b14;
            --panel: rgba(15, 23, 42, 0.85);
            --border: rgba(255, 255, 255, 0.12);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: radial-gradient(circle at 50% 20%, #0f1f38 0%, var(--bg-dark) 100%);
            color: #f8fafc;
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            overflow-x: hidden;
            <?= isset($bgGradientCSS) ? "background-image: $bgGradientCSS; background-attachment: fixed;" : "" ?>
        }

        .caro-topbar {
            width: 100%;
            height: 65px;
            background: rgba(11, 15, 25, 0.95);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 25px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .topbar-btn {
            color: #94a3b8;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .topbar-btn:hover { color: #fff; }
        .user-balance-badge {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.4);
            padding: 6px 14px;
            border-radius: 12px;
            color: var(--emerald);
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        /* --- SẢNH LOBBY CỜ CA-RÔ RIÊNG BIỆT --- */
        .caro-lobby-container {
            width: 100%;
            max-width: 1000px;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        .hero-banner {
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.6) 0%, rgba(15, 23, 42, 0.9) 100%);
            border: 1px solid rgba(59, 130, 246, 0.4);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.5);
            position: relative;
            overflow: hidden;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid var(--primary);
            color: #93c5fd;
            font-size: 0.8rem;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 12px;
        }
        .hero-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.2rem;
            font-weight: 900;
            margin: 0 0 10px 0;
            background: linear-gradient(135deg, #fff 30%, #60a5fa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-desc {
            font-size: 1rem;
            color: #94a3b8;
            max-width: 650px;
            line-height: 1.5;
            margin: 0;
        }

        .caro-modes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
        }
        .mode-card {
            background: var(--panel);
            backdrop-filter: blur(14px);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            transition: 0.3s;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .mode-card:hover {
            border-color: var(--primary);
            transform: translateY(-4px);
        }
        .mode-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        .mode-icon {
            width: 55px;
            height: 55px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }
        .ai-icon { background: rgba(59, 130, 246, 0.2); border: 1px solid var(--primary); }
        .pvp-icon { background: rgba(239, 68, 68, 0.2); border: 1px solid var(--rose); }

        .bet-chips-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            margin: 18px 0;
        }
        .bet-chip-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #fff;
            padding: 8px 4px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 0.85rem;
            cursor: pointer;
            transition: 0.2s;
            text-align: center;
        }
        .bet-chip-btn.active, .bet-chip-btn:hover {
            background: var(--primary);
            border-color: #60a5fa;
            color: #fff;
            transform: scale(1.05);
        }
        .btn-launch-mode {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            border: none;
            padding: 14px 20px;
            border-radius: 12px;
            font-weight: 900;
            font-size: 1rem;
            cursor: pointer;
            box-shadow: 0 0 20px rgba(59,130,246,0.4);
            transition: 0.2s;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-launch-mode:hover { transform: scale(1.03); }

        .my-matches-section {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 25px;
        }
        .match-row {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        /* --- BÀN CỜ ĐANG ĐẤU (MATCH PLAYING) --- */
        .caro-match-layout {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            width: 100%;
        }
        .game-header {
            margin: 10px 0 20px 0;
            text-align: center;
            background: rgba(15, 23, 42, 0.85);
            padding: 12px 35px;
            border-radius: 50px;
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .player-info { display: flex; flex-direction: column; align-items: center; }
        .player-name { font-weight: 800; font-size: 1rem; color: #fff; }
        .player-symbol { font-size: 1.8rem; font-weight: 900; }
        .symbol-x { color: var(--rose); text-shadow: 0 0 12px rgba(239, 68, 68, 0.6); }
        .symbol-o { color: #38bdf8; text-shadow: 0 0 12px rgba(56, 189, 248, 0.6); }
        .vs-badge { background: var(--gold); color: #000; padding: 4px 12px; border-radius: 20px; font-weight: 900; font-size: 0.8rem; }

        .board-container {
            position: relative;
            background: rgba(15, 23, 42, 0.9);
            padding: 12px;
            border-radius: 16px;
            border: 2px solid rgba(59, 130, 246, 0.3);
            box-shadow: 0 20px 60px rgba(0,0,0,0.7);
        }
        .caro-board {
            display: grid;
            grid-template-columns: repeat(15, 34px);
            grid-template-rows: repeat(15, 34px);
            gap: 1px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            overflow: hidden;
        }
        .cell {
            background: #0b1120;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 900;
            cursor: pointer;
            transition: all 0.15s;
            user-select: none;
        }
        .cell:hover:not(.filled) {
            background: #1e293b;
        }
        .cell.filled { cursor: default; }
        .cell.last-move {
            box-shadow: inset 0 0 10px #fbbf24;
            background: rgba(251, 191, 36, 0.12);
        }

        .status-bar {
            margin-top: 18px;
            padding: 12px 28px;
            border-radius: 30px;
            font-weight: 800;
            font-size: 1rem;
            text-align: center;
            min-width: 280px;
            transition: all 0.3s;
        }
        .status-my-turn {
            background: rgba(16, 185, 129, 0.15);
            color: var(--emerald);
            border: 1px solid var(--emerald);
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.3);
        }
        .status-opponent-turn {
            background: rgba(251, 191, 36, 0.15);
            color: var(--gold);
            border: 1px solid var(--gold);
        }

        .winner-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(7, 11, 20, 0.9);
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 20;
            border-radius: 14px;
            backdrop-filter: blur(8px);
        }
        .winner-msg {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.8rem;
            color: var(--gold);
            margin-bottom: 10px;
            text-shadow: 0 0 25px rgba(251, 191, 36, 0.6);
        }

        @media (max-width: 600px) {
            .caro-board {
                grid-template-columns: repeat(15, 23px);
                grid-template-rows: repeat(15, 23px);
            }
            .cell { font-size: 13px; }
            .game-header { padding: 8px 20px; gap: 15px; }
            .hero-title { font-size: 1.6rem; }
        }
    </style>
</head>
<body>

    <!-- TOPBAR -->
    <header class="caro-topbar">
        <a href="<?= $challenge ? 'pvp_caro.php' : 'index.php' ?>" class="topbar-btn">
            <i class="fa fa-arrow-left"></i> <?= $challenge ? 'Rời Bàn Cờ' : 'Về Trang Chủ' ?>
        </a>
        <div style="font-weight: 900; font-size: 1.1rem; color: #60a5fa; display: flex; align-items: center; gap: 8px;">
            <span>♟️</span> ĐẤU TRƯỜNG CỜ CA-RÔ
        </div>
        <div class="user-balance-badge">
            <i class="fa fa-coins"></i>
            <span id="userMoneyTop"><?= number_format($userMoney, 0, ',', '.') ?></span> GTLM
        </div>
    </header>

    <?php if (!$challenge): ?>
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- 1. SẢNH LOBBY CỜ CA-RÔ ĐỘC LẬP (KHI TRUY CẬP TRỰC TIẾP PVP_CARO.PHP) -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <main class="caro-lobby-container">
        <div class="hero-banner">
            <div class="hero-badge"><i class="fa fa-bolt"></i> CHẾ ĐỘ ĐÁNH LIÊN TỤC 15x15</div>
            <h1 class="hero-title">ĐẤU TRƯỜNG CỜ CA-RÔ</h1>
            <p class="hero-desc">
                Cờ Ca-rô đã được tách riêng thành Sảnh Độc Lập! Chiến đấu liên tục từng nước cờ không cần chờ đợi với AI Đại Sư Ca-rô, hoặc tạo bàn thách đấu tay đôi giữa 2 kỳ thủ.
            </p>
        </div>

        <div class="caro-modes-grid">
            <!-- CHẾ ĐỘ 1: ĐẤU VỚI BOT ĐẠI SƯ CA-RÔ (ĐÁNH LIÊN TỤC) -->
            <div class="mode-card" style="border: 2px solid rgba(59, 130, 246, 0.5);">
                <div>
                    <div class="mode-header">
                        <div class="mode-icon ai-icon">🤖</div>
                        <div>
                            <h2 style="margin: 0; font-size: 1.3rem; color: #93c5fd;">Đại Sư Ca-rô (AI)</h2>
                            <p style="margin: 4px 0 0 0; color: #94a3b8; font-size: 0.85rem;">Đánh liên tục 24/7 • Phản xạ 0.3s</p>
                        </div>
                    </div>
                    <p style="color: #cbd5e1; font-size: 0.9rem; line-height: 1.4;">
                        Chiến đấu tức thì không cần đợi người khác! Bot AI tự động hóa giải thế cờ và phản đòn ngay lập tức.
                    </p>

                    <label style="font-weight: 800; font-size: 0.85rem; color: #94a3b8; display: block; margin-top: 15px;">CHỌN MỨC CƯỢC GTLM:</label>
                    <div class="bet-chips-row">
                        <div class="bet-chip-btn active" onclick="selectAiBet(10000)">10K</div>
                        <div class="bet-chip-btn" onclick="selectAiBet(50000)">50K</div>
                        <div class="bet-chip-btn" onclick="selectAiBet(100000)">100K</div>
                        <div class="bet-chip-btn" onclick="selectAiBet(500000)">500K</div>
                        <div class="bet-chip-btn" onclick="selectAiBet(1000000)">1M</div>
                    </div>
                </div>

                <button class="btn-launch-mode" onclick="startCaroWithBot()">
                    <i class="fa fa-play"></i> VÀO ĐÁNH NGAY VỚI AI
                </button>
            </div>

            <!-- CHẾ ĐỘ 2: THÁCH ĐẤU PvP NGƯỜI THẬT -->
            <div class="mode-card">
                <div>
                    <div class="mode-header">
                        <div class="mode-icon pvp-icon">⚔️</div>
                        <div>
                            <h2 style="margin: 0; font-size: 1.3rem; color: #fca5a5;">Thách Đấu Kỳ Thủ (1vs1)</h2>
                            <p style="margin: 4px 0 0 0; color: #94a3b8; font-size: 0.85rem;">Người vs Người • Tranh hùng xưng bá</p>
                        </div>
                    </div>
                    <p style="color: #cbd5e1; font-size: 0.9rem; line-height: 1.4;">
                        Thách đấu các cao thủ trực tuyến trong sảnh hoặc tạo bàn cờ chờ bạn bè vào tiếp chiêu.
                    </p>

                    <div style="margin: 18px 0; background: rgba(0,0,0,0.25); padding: 12px; border-radius: 12px;">
                        <div style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 5px;">Kỳ thủ đang trực tuyến:</div>
                        <div style="font-size: 1.1rem; font-weight: 800; color: #38bdf8;">🔥 Hơn 40 đối thủ sẵn sàng</div>
                    </div>
                </div>

                <a href="pvp_challenge.php" class="btn-launch-mode" style="background: linear-gradient(135deg, #ef4444, #b91c1c); text-decoration: none;">
                    <i class="fa fa-users"></i> SẢNH TÌM ĐỐI THỦ PvP
                </a>
            </div>
        </div>

        <!-- DANH SÁCH BÀN CỜ ĐANG CÓ -->
        <?php if (!empty($myMatches)): ?>
        <div class="my-matches-section">
            <h3 style="margin: 0 0 15px 0; font-size: 1.1rem; color: var(--gold); display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-chess-board"></i> BÀN CỜ CỦA BẠN ĐANG HOẠT ĐỘNG
            </h3>
            <?php foreach ($myMatches as $m): ?>
            <div class="match-row">
                <div>
                    <span style="font-weight: 800; color: #fff;">Bàn #<?= $m['id'] ?></span>: 
                    <span style="color: #60a5fa; font-weight: 700;"><?= htmlspecialchars($m['challenger_name']) ?></span> vs 
                    <span style="color: #f87171; font-weight: 700;"><?= htmlspecialchars($m['opponent_name']) ?></span>
                    <span style="margin-left: 10px; color: var(--gold); font-size: 0.85rem;">(<?= number_format($m['bet_amount']) ?> GTLM)</span>
                </div>
                <div>
                    <a href="pvp_caro.php?id=<?= $m['id'] ?>" class="btn-chip-btn active" style="text-decoration: none; padding: 6px 16px;">
                        VÀO BÀN CỜ 👉
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- LINK LIVESTREAM BÀN 66 -->
        <div style="background: rgba(15, 23, 42, 0.6); border: 1px dashed rgba(255,255,255,0.15); border-radius: 16px; padding: 15px 20px; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <span style="font-size: 1.8rem;">📺</span>
                <div>
                    <b style="color: #60a5fa;">Xem LiveStream Bàn 66:</b>
                    <span style="font-size: 0.85rem; color: #94a3b8; display: block;">Streamer Đại Sư Ca-rô đang phát trực tiếp 24/7</span>
                </div>
            </div>
            <a href="LiveStream/watch.php?id=66" class="topbar-btn" style="color: #38bdf8; font-weight: 800;">
                XEM LIVE <i class="fa fa-arrow-right"></i>
            </a>
        </div>
    </main>

    <script>
        let selectedAiBet = 10000;
        function selectAiBet(amt) {
            selectedAiBet = amt;
            $('.bet-chip-btn').removeClass('active');
            $(event.target).addClass('active');
        }

        function startCaroWithBot() {
            Swal.fire({
                title: 'Khởi tạo bàn cờ...',
                text: 'Đang kết nối với Đại Sư Ca-rô...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
                background: '#0f172a', color: '#fff'
            });

            $.post('api_pvp_challenge.php', {
                action: 'create_caro_bot',
                bet_amount: selectedAiBet
            }, function(res) {
                if (res.success && res.challenge_id) {
                    window.location.href = 'pvp_caro.php?id=' + res.challenge_id;
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Không thể vào bàn',
                        text: res.message || 'Lỗi hệ thống',
                        background: '#0f172a', color: '#fff'
                    });
                }
            }, 'json');
        }
    </script>

    <?php else: ?>
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!-- 2. BÀN CỜ CA-RÔ TRỰC TIẾP (KHI ĐANG TRONG TRẬN ĐẤU PVP_CARO.PHP?ID=X)-->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <main class="caro-match-layout">
        <div class="game-header">
            <div class="player-info" id="player1">
                <span class="player-name"><?= htmlspecialchars($challenge['challenger_name']) ?></span>
                <span class="player-symbol symbol-x">X</span>
            </div>
            <div class="vs-badge">VS</div>
            <div class="player-info" id="player2">
                <span class="player-name"><?= htmlspecialchars($challenge['opponent_name']) ?></span>
                <span class="player-symbol symbol-o">O</span>
            </div>
        </div>

        <div class="board-container">
            <div class="winner-overlay" id="winnerOverlay">
                <div class="winner-msg" id="winnerMsg">BẠN THẮNG!</div>
                <p id="rewardMsg" style="font-size: 1.2rem; font-weight: 700; color: #4ade80;">Bạn nhận được +<?= number_format($challenge['bet_amount'] * 2) ?> GTLM</p>
                <div style="display: flex; gap: 12px; margin-top: 20px; flex-wrap: wrap; justify-content: center;">
                    <button onclick="playAnotherGame()" class="btn-launch-mode" style="width: auto; padding: 12px 25px; background: linear-gradient(135deg, #10b981, #059669);">
                        <i class="fa fa-rotate"></i> ĐÁNH TIẾP VÁN MỚI
                    </button>
                    <a href="pvp_caro.php" class="btn-launch-mode" style="width: auto; padding: 12px 25px; background: rgba(255,255,255,0.1); border: 1px solid var(--border); text-decoration: none;">
                        VỀ SẢNH CỜ CA-RÔ
                    </a>
                </div>
            </div>
            <div class="caro-board" id="board">
                <!-- 15x15 Cells via JS -->
            </div>
        </div>

        <div class="status-bar status-my-turn" id="statusBar">
            Đang khởi tạo trận đấu...
        </div>
    </main>

    <script>
        const challengeId = <?= $challengeId ?>;
        const myId = <?= $userId ?>;
        const challengerId = <?= (int)$challenge['challenger_id'] ?>;
        const opponentId = <?= (int)$challenge['opponent_id'] ?>;
        const betAmount = <?= (float)$challenge['bet_amount'] ?>;
        const isOpponentBot = <?= (strpos($challenge['opponent_name'], 'bot_') === 0 || strpos($challenge['opponent_name'], 'Bot') === 0) ? 'true' : 'false' ?>;

        let gameActive = true;
        let isMyTurn = false;
        let audioCtx = null;

        // Âm thanh tiếng cờ gõ xuống bàn gỗ
        function playWoodClickSound() {
            try {
                if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(320, audioCtx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(120, audioCtx.currentTime + 0.08);
                gain.gain.setValueAtTime(0.7, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.08);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.09);
            } catch (e) {}
        }

        function initBoard() {
            const board = $('#board');
            board.empty();
            for (let r = 0; r < 15; r++) {
                for (let c = 0; c < 15; c++) {
                    board.append(`<div class="cell" data-row="${r}" data-col="${c}" onclick="makeMove(${r}, ${c})"></div>`);
                }
            }
        }

        function updateUI(challengeData) {
            const boardData = typeof challengeData.board_state === 'string' ? JSON.parse(challengeData.board_state) : challengeData.board_state;
            if (!boardData) return;

            $('.cell').removeClass('filled last-move').empty();
            
            boardData.forEach((row, r) => {
                row.forEach((cell, c) => {
                    const cellEl = $(`.cell[data-row="${r}"][data-col="${c}"]`);
                    if (cell === 1) {
                        cellEl.html('<span class="symbol-x">X</span>').addClass('filled');
                    } else if (cell === 2) {
                        cellEl.html('<span class="symbol-o">O</span>').addClass('filled');
                    }
                });
            });

            if (challengeData.status === 'completed') {
                gameActive = false;
                $('#winnerOverlay').css('display', 'flex');
                if (challengeData.winner_id == myId) {
                    $('#winnerMsg').text('🏆 BẠN THẮNG!').css('color', '#10b981');
                    $('#rewardMsg').text('Bạn húp trọn +' + (betAmount * 2).toLocaleString() + ' GTLM!').css('color', '#4ade80');
                } else if (challengeData.winner_id) {
                    $('#winnerMsg').text('😢 BẠN THUA!').css('color', '#ef4444');
                    $('#rewardMsg').text('Bạn bị trừ -' + betAmount.toLocaleString() + ' GTLM').css('color', '#f87171');
                } else {
                    $('#winnerMsg').text('🤝 HÒA NHAU!').css('color', '#fbbf24');
                }
                $('#statusBar').text('Trận đấu kết thúc').removeClass('status-my-turn status-opponent-turn');
                return;
            }

            isMyTurn = (challengeData.current_turn_id == myId);
            if (isMyTurn) {
                $('#statusBar').text('👉 Đến lượt bạn hạ quân cờ!').addClass('status-my-turn').removeClass('status-opponent-turn');
            } else {
                $('#statusBar').text('⏳ Đang chờ đối thủ hạ cờ...').addClass('status-opponent-turn').removeClass('status-my-turn');
            }
        }

        function pollGame() {
            if (!gameActive) return;

            $.get('api_pvp_challenge.php', { action: 'get_challenge', challenge_id: challengeId }, function(res) {
                if (res.success && res.challenge) {
                    updateUI(res.challenge);
                }
                if (gameActive && !isOpponentBot) {
                    setTimeout(pollGame, 1800);
                }
            });
        }

        function makeMove(row, col) {
            if (!isMyTurn || !gameActive) return;

            const targetCell = $(`.cell[data-row="${row}"][data-col="${col}"]`);
            if (targetCell.hasClass('filled')) return;

            // Đặt quân X ngay lập tức trên màn hình
            playWoodClickSound();
            const playerSymbol = (myId == challengerId) ? 'X' : 'O';
            const symbolClass = (myId == challengerId) ? 'symbol-x' : 'symbol-o';
            targetCell.html(`<span class="${symbolClass}">${playerSymbol}</span>`).addClass('filled last-move');
            isMyTurn = false;
            $('#statusBar').text('Đang xử lý nước cờ...').addClass('status-opponent-turn').removeClass('status-my-turn');

            $.post('api_pvp_challenge.php', {
                action: 'make_move',
                challenge_id: challengeId,
                row: row,
                col: col
            }, function(res) {
                if (res.success) {
                    // Nếu người chơi thắng
                    if (res.is_win) {
                        gameActive = false;
                        setTimeout(() => {
                            $('#winnerOverlay').css('display', 'flex');
                            $('#winnerMsg').text('🏆 BẠN THẮNG!').css('color', '#10b981');
                            $('#rewardMsg').text('Bạn húp trọn +' + (betAmount * 2).toLocaleString() + ' GTLM!');
                            $('#statusBar').text('Trận đấu kết thúc').removeClass('status-my-turn status-opponent-turn');
                        }, 300);
                        return;
                    }

                    // Nếu đối thủ là Bot đánh ngay trong response
                    if (res.bot_move) {
                        setTimeout(() => {
                            playWoodClickSound();
                            const bRow = res.bot_move.row;
                            const bCol = res.bot_move.col;
                            const botSymbol = (myId == challengerId) ? 'O' : 'X';
                            const botSymbolClass = (myId == challengerId) ? 'symbol-o' : 'symbol-x';
                            
                            $('.cell').removeClass('last-move');
                            $(`.cell[data-row="${bRow}"][data-col="${bCol}"]`)
                                .html(`<span class="${botSymbolClass}">${botSymbol}</span>`)
                                .addClass('filled last-move');

                            if (res.bot_win) {
                                gameActive = false;
                                setTimeout(() => {
                                    $('#winnerOverlay').css('display', 'flex');
                                    $('#winnerMsg').text('😢 BẠN THUA!').css('color', '#ef4444');
                                    $('#rewardMsg').text('Đại Sư Ca-rô đã khóa thế cờ ngũ tử!');
                                    $('#statusBar').text('Trận đấu kết thúc').removeClass('status-my-turn status-opponent-turn');
                                }, 300);
                            } else {
                                isMyTurn = true;
                                $('#statusBar').text('👉 Đến lượt bạn hạ quân cờ!').addClass('status-my-turn').removeClass('status-opponent-turn');
                            }
                        }, 250);
                    }
                } else {
                    Swal.fire({
                        text: res.message || 'Lỗi nước đi!',
                        icon: 'warning',
                        background: '#0f172a', color: '#fff', timer: 1500
                    });
                    isMyTurn = true;
                    targetCell.empty().removeClass('filled last-move');
                }
            }, 'json');
        }

        function playAnotherGame() {
            if (isOpponentBot) {
                Swal.fire({
                    title: 'Khởi tạo ván mới...',
                    didOpen: () => Swal.showLoading(),
                    background: '#0f172a', color: '#fff'
                });
                $.post('api_pvp_challenge.php', {
                    action: 'create_caro_bot',
                    bet_amount: betAmount
                }, function(res) {
                    if (res.success && res.challenge_id) {
                        window.location.href = 'pvp_caro.php?id=' + res.challenge_id;
                    } else {
                        window.location.href = 'pvp_caro.php';
                    }
                }, 'json');
            } else {
                window.location.href = 'pvp_caro.php';
            }
        }

        $(document).ready(function() {
            initBoard();
            pollGame();
        });
    </script>
    <?php endif; ?>

</body>
</html>
