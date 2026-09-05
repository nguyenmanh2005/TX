<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_54', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require '../db_connect.php';
require_once '../include_css.php';
$useBotTheme = $botUserId;
require_once '../load_theme.php';

if (!isset($botUserId)) {
    header('Location: ../login.php');
    exit;
}

$userId = $botUserId;
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'] ?? 50000000;
$userName = $user['Name'] ?? 'Thánh Bài Tứ Sắc';
$stmt->close();

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    if ($action === 'win_turn') {
        $winAmt = 250000;
        $conn->query("UPDATE users SET Money = Money + $winAmt WHERE Iduser = $userId");
        $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
        logGameHistoryWithAll($conn, $userId, 'Tusac', 50000, $winAmt, true);
        echo json_encode(['success' => true, 'winAmount' => number_format($winAmt, 0, ',', '.'), 'money' => number_format($newMoney, 0, ',', '.')]);
        exit;
    } elseif ($action === 'eat_card') {
        $eatAmt = 50000;
        $conn->query("UPDATE users SET Money = Money + $eatAmt WHERE Iduser = $userId");
        $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
        echo json_encode(['success' => true, 'winAmount' => number_format($eatAmt, 0, ',', '.'), 'money' => number_format($newMoney, 0, ',', '.')]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>🎴 Tứ Sắc Cổ Truyền - Đỉnh Cao Sới Bạc</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <style>
        :root {
            --primary: #ffd700;
            --accent: #f59e0b;
            --glass: rgba(255, 255, 255, 0.06);
            --glass-border: rgba(255, 255, 255, 0.12);
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #fff;
            font-family: 'Exo 2', system-ui, sans-serif;
            margin: 0;
            overflow: hidden;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: url('../img/chuot.png'), auto !important;
        }
        * { cursor: inherit; }
        button, a, input, .card, .btn-action, .deck-back {
            cursor: url('../img/tay.png'), pointer !important;
        }

        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        /* 🏆 Badge Thông Báo Thắng / Thua Chuẩn Game ID 1 */
        #result-status-badge {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.5);
            background: rgba(10, 12, 24, 0.94);
            border-radius: 24px;
            padding: 24px 48px;
            text-align: center;
            z-index: 99999;
            pointer-events: none;
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 2px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.85);
            font-family: 'Orbitron', 'Exo 2', sans-serif;
            transition: transform 0.4s cubic-bezier(0.17, 0.89, 0.32, 1.49), opacity 0.4s;
            opacity: 0;
        }
        #result-badge-icon {
            font-size: 3.5rem;
            margin-bottom: 6px;
            animation: badgeIconBounce 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
        }
        @keyframes badgeIconBounce {
            0% { transform: scale(0) rotate(-20deg); }
            70% { transform: scale(1.3) rotate(10deg); }
            100% { transform: scale(1) rotate(0deg); }
        }
        #result-badge-title {
            font-size: 1.6rem;
            font-weight: 900;
            letter-spacing: 2px;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        #result-badge-amount {
            font-size: 1.3rem;
            font-weight: 800;
            opacity: 0.95;
            font-family: 'Orbitron', sans-serif;
        }
        #result-badge-msg {
            font-size: 0.82rem;
            opacity: 0.75;
            margin-top: 6px;
            max-width: 320px;
            font-family: 'Exo 2', sans-serif;
        }

        /* 🎴 Bàn chơi Tứ Sắc thu gọn */
        .table-area {
            width: 96vw;
            max-width: 980px;
            height: 94vh;
            max-height: 680px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 0.8rem 1.2rem;
            box-sizing: border-box;
            position: relative;
            z-index: 1;
            background: rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 1.8rem;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        }

        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 0.4rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .game-title {
            font-size: clamp(1.2rem, 3vw, 1.6rem);
            font-weight: 900;
            color: var(--primary);
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .balance-pill {
            background: rgba(251, 191, 36, 0.15);
            border: 1px solid var(--primary);
            padding: 4px 16px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--primary);
        }
        .btn-exit {
            color: #fff;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: bold;
            background: rgba(0, 0, 0, 0.3);
            transition: 0.3s;
        }
        .btn-exit:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: #fff;
        }

        .opponent {
            display: flex;
            justify-content: center;
            gap: 4px;
            opacity: 0.7;
            padding: 4px 0;
        }
        .opp-card {
            width: 24px;
            height: 72px;
            background: linear-gradient(135deg, #991b1b, #7f1d1d);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 4px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
        }

        .center-deck {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.8rem;
            flex-grow: 1;
            padding: 0.4rem 0;
        }
        .deck-back {
            width: 38px;
            height: 110px;
            background: linear-gradient(135deg, #b91c1c, #7f1d1d);
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.4);
            transition: 0.3s;
        }
        .deck-back:hover {
            transform: scale(1.05);
            border-color: var(--primary);
        }
        .discard-pile {
            width: 38px;
            height: 110px;
            border: 2px dashed rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            background: rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .turn-box {
            background: rgba(0, 0, 0, 0.3);
            padding: 8px 16px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .player-hand {
            display: flex;
            justify-content: center;
            gap: 2px;
            perspective: 1200px;
            height: 125px;
            align-items: flex-end;
            overflow-x: auto;
            padding-bottom: 6px;
        }
        .card {
            width: 34px;
            height: 105px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 6px;
            border: 1px solid rgba(0, 0, 0, 0.15);
            color: #000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 0.85rem;
            transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            box-shadow: 1px 4px 10px rgba(0, 0, 0, 0.3);
            user-select: none;
            flex-shrink: 0;
        }
        .card:hover {
            transform: translateY(-25px);
            z-index: 100;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.5);
        }
        .card.selected {
            transform: translateY(-35px);
            box-shadow: 0 0 15px #fbbf24;
            border: 2px solid #fbbf24;
            z-index: 101;
        }
        .card .rank {
            writing-mode: vertical-rl;
            text-orientation: upright;
            letter-spacing: 1px;
        }
        .card.red { color: #e11d48; border-bottom: 6px solid #e11d48; }
        .card.yellow { color: #d97706; border-bottom: 6px solid #d97706; }
        .card.blue { color: #2563eb; border-bottom: 6px solid #2563eb; }
        .card.white { color: #4b5563; border-bottom: 6px solid #4b5563; }

        /* 🎮 Cụm phím điều khiển dạng dock ngang */
        .controls {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding-top: 6px;
            flex-wrap: wrap;
        }
        .btn-action {
            padding: 0.55rem 1.4rem;
            border: none;
            border-radius: 40px;
            font-weight: 800;
            font-size: 0.82rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-action:hover {
            transform: translateY(-2px);
            filter: brightness(1.1);
        }
        .btn-action:active {
            transform: translateY(1px);
        }
        .btn-draw { background: #10b981; color: #fff; }
        .btn-discard { background: #ef4444; color: #fff; }
        .btn-eat { background: #3b82f6; color: #fff; }
        .btn-win { background: #f59e0b; color: #000; font-weight: 900; }
        .btn-reset { background: #8b5cf6; color: #fff; }

        .hint-bar {
            text-align: center;
            font-size: 0.75rem;
            opacity: 0.8;
            padding: 4px;
        }
    </style>
</head>
<body>

    <!-- 🏆 Badge thông báo thắng/thua giống game ID 1 -->
    <div id="result-status-badge">
        <div id="result-badge-icon">🏆</div>
        <div id="result-badge-title">Ù TỨ SẮC!</div>
        <div id="result-badge-amount">+250,000 GTLM</div>
        <div id="result-badge-msg">Làm tròn bài thành công!</div>
    </div>

    <div class="table-area">
        <!-- Thanh tiêu đề & Số dư GTLM -->
        <div class="header-bar">
            <div class="game-title">🎴 TỨ SẮC CỔ TRUYỀN</div>
            <div class="balance-pill">
                <span>SỐ GTLM: </span>
                <b id="userMoney"><?= number_format($money, 0, ',', '.') ?></b>
                <small>GTLM</small>
            </div>
            <a href="../index.php" class="btn-exit">🏠 THOÁT SẢNH</a>
        </div>

        <!-- Hàng bài đối thủ -->
        <div class="opponent" id="opp-top"></div>

        <!-- Khu vực nọc bài & chồng rác -->
        <div class="center-deck">
            <div class="deck-back" onclick="drawCard()" title="Bốc bài">🎴</div>
            <div class="discard-pile" id="discard"></div>
            <div class="turn-box">
                <div id="current-turn-info" style="font-size: 0.95rem; font-weight: 800; color: #fbbf24;">Lượt của bạn</div>
                <div style="font-size: 0.72rem; opacity: 0.75;" id="turn-sub-info">Bốc bài hoặc Húp bài</div>
            </div>
        </div>

        <!-- Thông báo gợi ý -->
        <div class="hint-bar" id="hint-text">
            💡 <b>Gợi ý:</b> Nhấn "Bốc bài" để thả thính hoặc chọn quân rác rồi nhấn "Ra chiêu".
        </div>

        <!-- Cụm phím hành động (Dock ngang) -->
        <div class="controls">
            <button class="btn-action btn-draw" id="btn-draw" onclick="drawCard()">🎲 BỐC BÀI</button>
            <button class="btn-action btn-eat" id="btn-eat" onclick="eatCard()">💎 HÚP BÀI</button>
            <button class="btn-action btn-discard" id="btn-discard" onclick="discardCard()">⚔️ RA CHIÊU</button>
            <button class="btn-action btn-win" id="btn-win" onclick="winGame()">🏆 Ù BÀI</button>
            <button class="btn-action btn-reset" id="btn-reset" style="display: none;" onclick="initGame()">🔄 VÁN MỚI</button>
        </div>

        <!-- Bài trên tay người chơi -->
        <div class="player-hand" id="hand"></div>
    </div>

    <!-- 🌌 Nền ThreeJS 3D Vũ Trụ Hoàng Kim -->
    <canvas id="threejs-background"></canvas>
    <script>
        window.themeConfig = {
            particleCount: <?= $particleCount ?? 800 ?>,
            particleSize: <?= $particleSize ?? 0.05 ?>,
            particleColor: '<?= $particleColor ?? "#ffd700" ?>',
            particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
            shapeCount: <?= $shapeCount ?? 15 ?>,
            shapeColors: <?= json_encode($shapeColors ?? ["#ffd700", "#ff4757", "#12c2e9", "#2ecc71"]) ?>,
            shapeOpacity: <?= $shapeOpacity ?? 0.35 ?>,
            bgGradient: <?= json_encode($bgGradient ?? ["#1a0b2e", "#2a1b3d", "#000000"]) ?>
        };
    </script>
    <script src="../threejs-background.js"></script>
    <script src="../assets/js/game-effects.js"></script>
    <script src="../assets/js/game-effects-auto.js"></script>

    <script>
        const ranks = ['Tướng', 'Sĩ', 'Tượng', 'Xe', 'Pháo', 'Mã', 'Tốt'];
        const colors = ['red', 'yellow', 'blue', 'white'];
        let hand = [];
        let selectedIndex = -1;
        let isGameEnded = false;

        function showResultBadge(type, title, amount, msg) {
            const badge = $('#result-status-badge');
            const icon = $('#result-badge-icon');
            const titleEl = $('#result-badge-title');
            const amountEl = $('#result-badge-amount');
            const msgEl = $('#result-badge-msg');

            if (type === 'win') {
                icon.text('🏆');
                titleEl.text(title || 'Ù TỨ SẮC!').css('color', '#fbbf24');
                amountEl.text(amount).css('color', '#4ade80');
                badge.css({
                    'border-color': 'rgba(251, 191, 36, 0.7)',
                    'box-shadow': '0 0 50px rgba(251, 191, 36, 0.35)'
                });
            } else {
                icon.text('💨');
                titleEl.text(title || 'BAY MÀU!').css('color', '#ff4757');
                amountEl.text(amount).css('color', '#ff4757');
                badge.css({
                    'border-color': 'rgba(239, 68, 68, 0.7)',
                    'box-shadow': '0 0 50px rgba(239, 68, 68, 0.35)'
                });
            }

            msgEl.text(msg || '');
            badge.stop(true, true).css({ display: 'block', opacity: 0, transform: 'translate(-50%, -50%) scale(0.6)' });
            
            setTimeout(() => {
                badge.css({ opacity: 1, transform: 'translate(-50%, -50%) scale(1)' });
            }, 10);

            setTimeout(() => {
                badge.css({ opacity: 0, transform: 'translate(-50%, -50%) scale(0.7)' });
                setTimeout(() => { badge.hide(); }, 400);
            }, 3500);
        }

        function initGame() {
            hand = [];
            selectedIndex = -1;
            isGameEnded = false;
            $('#btn-reset').hide();
            $('#btn-draw, #btn-eat, #btn-discard, #btn-win').show();
            $('#discard').html('');

            for (let i = 0; i < 20; i++) {
                hand.push({
                    rank: ranks[Math.floor(Math.random() * ranks.length)],
                    color: colors[Math.floor(Math.random() * colors.length)]
                });
            }
            renderHand();
            renderOpponents();
            $('#current-turn-info').text('Lượt của bạn');
            $('#turn-sub-info').text('Bốc bài hoặc Húp bài');
            $('#hint-text').html('💡 <b>Gợi ý:</b> Bốc bài hoặc chọn 1 quân rác rồi nhấn "Ra chiêu".');
        }

        function renderHand() {
            let html = '';
            hand.sort((a, b) => {
                if (a.color !== b.color) return a.color.localeCompare(b.color);
                return ranks.indexOf(a.rank) - ranks.indexOf(b.rank);
            });
            hand.forEach((c, i) => {
                html += `<div class="card ${c.color}" onclick="selectCard(${i})" id="card-${i}"><div class="rank">${c.rank}</div></div>`;
            });
            $('#hand').html(html);
        }

        function renderOpponents() {
            let html = '';
            for (let i = 0; i < 18; i++) {
                html += `<div class="opp-card"></div>`;
            }
            $('#opp-top').html(html);
        }

        function selectCard(i) {
            if (isGameEnded) return;
            $('.card').removeClass('selected');
            $(`#card-${i}`).addClass('selected');
            selectedIndex = i;
        }

        function discardCard() {
            if (isGameEnded) return;
            if (selectedIndex === -1) {
                // Tự động chọn lá đầu tiên nếu chưa chọn
                if (hand.length > 0) {
                    selectCard(0);
                } else {
                    return;
                }
            }
            const card = hand.splice(selectedIndex, 1)[0];
            $('#discard').html(`<div class="card ${card.color}"><div class="rank">${card.rank}</div></div>`);
            selectedIndex = -1;
            renderHand();
            $('#current-turn-info').text('Chờ đối thủ...');
            $('#turn-sub-info').text('Đối thủ đang tính bài');
            setTimeout(() => {
                if (!isGameEnded) {
                    $('#current-turn-info').text('Lượt của bạn');
                    $('#turn-sub-info').text('Bốc bài hoặc Húp bài');
                    $('#hint-text').html('💡 <b>Gợi ý:</b> Đến lượt bạn bốc bài hoặc húp bài vừa ra chiêu.');
                }
            }, 1400);
        }

        function drawCard() {
            if (isGameEnded) return;
            const newCard = {
                rank: ranks[Math.floor(Math.random() * ranks.length)],
                color: colors[Math.floor(Math.random() * colors.length)]
            };
            hand.push(newCard);
            renderHand();
            $('#hint-text').html(`💡 Bạn vừa bốc được quân <b>${newCard.rank} ${newCard.color}</b>. Hãy chọn 1 lá rác để ra chiêu.`);
        }

        function eatCard() {
            if (isGameEnded) return;
            $.post('?action=eat_card', function (res) {
                if (res.success) {
                    $('#userMoney').text(res.money);
                    const winAmt = parseInt((res.winAmount + '').replace(/[^0-9]/g, '')) || 50000;
                    if (window.GameEffects) window.GameEffects.showWin(winAmt);
                    showResultBadge('win', 'HÚP BÀI THÀNH BỘ!', '+' + res.winAmount + ' GTLM', 'Tạo bộ Khàn/Quàn hợp lệ thành công!');
                    $('#hint-text').html('💡 Bạn vừa húp bài thành công! Hãy ra chiêu 1 quân rác.');
                }
            });
        }

        function winGame() {
            if (isGameEnded) return;
            isGameEnded = true;
            $.post('?action=win_turn', function (res) {
                if (res.success) {
                    $('#userMoney').text(res.money);
                    const winAmt = parseInt((res.winAmount + '').replace(/[^0-9]/g, '')) || 250000;
                    if (window.GameEffects) window.GameEffects.showBigWin(winAmt);
                    showResultBadge('win', 'Ù TỨ SẮC TOÀN THẮNG!', '+' + res.winAmount + ' GTLM', '21 lệnh tròn bài đỉnh cao!');
                    $('#current-turn-info').text('Ù BÀI TOÀN THẮNG!');
                    $('#turn-sub-info').text('Chiến thắng ngoạn mục');
                    $('#btn-reset').show();
                    $('#btn-draw, #btn-eat, #btn-discard, #btn-win').hide();
                }
            });
        }

        initGame();
    </script>

    <!-- 🤖 Nạp Bot AI Chuyên Nghiệp Thánh Bài Tứ Sắc 54 -->
    <script src="../assets/js/bot_chat.js"></script>
    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="bots/bot_54.js"></script>

</body>
</html>
