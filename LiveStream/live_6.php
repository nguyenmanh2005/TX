<?php
session_start();

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../game_history_helper.php';
require_once __DIR__ . '/bot_streamer_helper.php';

$botUser = getOrCreateBotStreamerUser($conn, 'bot_6', 50000000);
$botUserId = (int)($botUser['Iduser'] ?? 0);
$_SESSION['Iduser_temp_bot'] = $botUserId;

$useBotTheme = $botUserId;
require_once __DIR__ . '/../load_theme.php';

// Fallback theme Bàn 6 (Đua Thú Cyberpunk Neon Xanh Lục - Tím)
$particleColor = $particleColor ?? '#00ff88';
$shapeColors = $shapeColors ?? ['#00ff88', '#00b894', '#12c2e9', '#ff007f'];
$bgGradient = $bgGradient ?? ['#050510', '#0a1020', '#0f0525'];
if (empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, ' . $bgGradient[0] . ' 0%, ' . $bgGradient[1] . ' 50%, ' . ($bgGradient[2] ?? $bgGradient[1]) . ' 100%)';
}

$userId = $botUserId;
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = (float)($user['Money'] ?? 50000000);
$userName = $user['Name'] ?? 'bot_6';
$stmt->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Bàn 06: Đua Thú Cyberpunk 2077 - Trận Địa Live 24/7</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <link rel="stylesheet" href="../assets/css/game-cyber-racing.css?v=<?= time() ?>">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: <?= $bgGradientCSS ?>;
            color: #fff;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }
        .stream-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 18px;
            background: rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0, 255, 136, 0.25);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .streamer-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(0, 255, 136, 0.12);
            border: 1px solid #00ff88;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 800;
        }
        .balance-chip {
            background: rgba(251, 191, 36, 0.15);
            border: 1px solid rgba(251, 191, 36, 0.4);
            color: #fbbf24;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 900;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .live-tag {
            background: #ff4757;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 900;
            padding: 3px 8px;
            border-radius: 6px;
            animation: pulse-live 1.2s infinite;
        }
        @keyframes pulse-live { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .bet-item {
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            user-select: none;
        }
        .bet-item:hover, .bet-item.selected {
            border-color: #00ff88 !important;
            box-shadow: 0 0 15px rgba(0, 255, 136, 0.4) !important;
            transform: translateY(-3px);
        }
        .quick-bet-btn {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s;
        }
        .quick-bet-btn:hover {
            background: #00ff88;
            color: #000;
            border-color: #00ff88;
        }
        .btn-submit-bet {
            background: linear-gradient(135deg, #00ff88 0%, #00b894 100%);
            color: #000;
            font-weight: 900;
            font-family: 'Orbitron', sans-serif;
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: 0 0 15px rgba(0, 255, 136, 0.5);
        }
        #threejs-background { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; pointer-events: none; }
    </style>
</head>
<body>

    <header class="stream-header">
        <div style="display: flex; align-items: center; gap: 12px;">
            <span class="live-tag">🔴 LIVE</span>
            <div class="streamer-pill">
                <i class="fa fa-robot" style="color: #00ff88;"></i>
                <span>Tay Đua Cyber 06 🏎️</span>
            </div>
        </div>
        <div class="balance-chip">
            <i class="fa fa-wallet"></i>
            <span id="currentBalance"><?= number_format($money) ?></span> GTLM
        </div>
    </header>

    <div class="cyber-racing-container" style="max-width: 1200px; margin: 0 auto; padding: 15px;">
        
        <!-- Trường Đua Racetrack -->
        <div class="racetrack-area" style="position: relative; border-radius: 16px; overflow: hidden;">
            <div class="status-overlay" id="statusOverlay">
                <h2 id="phaseText" style="font-family: 'Orbitron', sans-serif;">CHỜ ĐẶT CƯỢC</h2>
                <div class="timer" id="timerDisplay">20</div>
            </div>

            <div class="finish-line">FINISH</div>

            <div class="lane" data-animal="wolf">
                <div class="lane-name">🐺 Sói Cyber (x3.0)</div>
                <div class="animal-sprite" id="anim_wolf">🐺<span class="flame" style="display:none;">🔥</span></div>
            </div>
            <div class="lane" data-animal="fox">
                <div class="lane-name">🦊 Cáo Neon (x2.0)</div>
                <div class="animal-sprite" id="anim_fox">🦊<span class="flame" style="display:none;">🔥</span></div>
            </div>
            <div class="lane" data-animal="panther">
                <div class="lane-name">🐆 Báo Plasma (x4.0)</div>
                <div class="animal-sprite" id="anim_panther">🐆<span class="flame" style="display:none;">🔥</span></div>
            </div>
            <div class="lane" data-animal="bear">
                <div class="lane-name">🐻 Gấu Sắt (x5.0)</div>
                <div class="animal-sprite" id="anim_bear">🐻<span class="flame" style="display:none;">🔥</span></div>
            </div>
            <div class="lane" data-animal="eagle">
                <div class="lane-name">🦅 Đại Bàng Lượng Tử (x6.0)</div>
                <div class="animal-sprite" id="anim_eagle">🦅<span class="flame" style="display:none;">🔥</span></div>
            </div>
        </div>

        <!-- Bảng Đặt Cược -->
        <div class="betting-board" id="bettingBoard" style="margin-top: 15px;">
            <div class="bet-item" data-animal="wolf" id="betItem_wolf" data-allow-bot="true" onclick="selectAnimal('wolf')">
                <h3 style="color: #00ff88;">🐺 Sói Cyber</h3>
                <p>Thưởng: <strong>x3.0</strong></p>
                <input type="number" class="bet-input" id="bet_wolf" placeholder="0" min="0" data-allow-bot="true">
            </div>
            <div class="bet-item" data-animal="fox" id="betItem_fox" data-allow-bot="true" onclick="selectAnimal('fox')">
                <h3 style="color: #ff7f50;">🦊 Cáo Neon</h3>
                <p>Thưởng: <strong>x2.0</strong></p>
                <input type="number" class="bet-input" id="bet_fox" placeholder="0" min="0" data-allow-bot="true">
            </div>
            <div class="bet-item" data-animal="panther" id="betItem_panther" data-allow-bot="true" onclick="selectAnimal('panther')">
                <h3 style="color: #c471ed;">🐆 Báo Plasma</h3>
                <p>Thưởng: <strong>x4.0</strong></p>
                <input type="number" class="bet-input" id="bet_panther" placeholder="0" min="0" data-allow-bot="true">
            </div>
            <div class="bet-item" data-animal="bear" id="betItem_bear" data-allow-bot="true" onclick="selectAnimal('bear')">
                <h3 style="color: #f1c40f;">🐻 Gấu Sắt</h3>
                <p>Thưởng: <strong>x5.0</strong></p>
                <input type="number" class="bet-input" id="bet_bear" placeholder="0" min="0" data-allow-bot="true">
            </div>
            <div class="bet-item" data-animal="eagle" id="betItem_eagle" data-allow-bot="true" onclick="selectAnimal('eagle')">
                <h3 style="color: #12c2e9;">🦅 Đại Bàng Lượng Tử</h3>
                <p>Thưởng: <strong>x6.0</strong></p>
                <input type="number" class="bet-input" id="bet_eagle" placeholder="0" min="0" data-allow-bot="true">
            </div>
        </div>

        <!-- Controls Nút Cược -->
        <div class="controls-area" style="margin-top: 15px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; background: rgba(0,0,0,0.5); padding: 12px 18px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1);">
            <div class="quick-bets" style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button class="quick-bet-btn" data-allow-bot="true" id="btnBet10k" onclick="addBet(50000)">+50K</button>
                <button class="quick-bet-btn" data-allow-bot="true" id="btnBet100k" onclick="addBet(100000)">+100K</button>
                <button class="quick-bet-btn" data-allow-bot="true" id="btnBet500k" onclick="addBet(500000)">+500K</button>
                <button class="quick-bet-btn" data-allow-bot="true" id="btnBet1M" onclick="addBet(1000000)">+1M</button>
            </div>
            <button class="btn-submit-bet" id="btnSubmitBet" data-allow-bot="true" onclick="submitBet()">
                <i class="fa fa-flag-checkered"></i> XÁC NHẬN CƯỢC
            </button>
        </div>

    </div>

    <!-- Scripts Engine -->
    <script>
        window.IS_LIVE_MODE = true;
    </script>
    <script src="../assets/js/game-cyber-racing.js?v=<?= time() ?>"></script>
    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="../assets/js/bot_chat.js"></script>
    <script src="bots/bot_6.js"></script>

    <script>
        let selectedAnimal = 'wolf';
        function selectAnimal(animal) {
            selectedAnimal = animal;
            $('.bet-item').removeClass('selected active');
            $(`.bet-item[data-animal="${animal}"]`).addClass('selected active');
        }
        function addBet(amt) {
            const inp = $(`#bet_${selectedAnimal}`);
            const cur = parseInt(inp.val() || 0, 10);
            inp.val(cur + amt);
        }
        function submitBet() {
            const bets = [];
            ['wolf', 'fox', 'panther', 'bear', 'eagle'].forEach(a => {
                const val = parseInt($(`#bet_${a}`).val() || 0, 10);
                if (val > 0) bets.push({ animal: a, amount: val });
            });
            if (bets.length === 0) return;
            $.post('../api_cyber_racing.php', { action: 'bot_bet', bot_name: 'bot_6', bets: JSON.stringify(bets) }, function(res) {
                const data = typeof res === 'string' ? JSON.parse(res) : res;
                if (data.success && data.new_money !== undefined) {
                    $('#currentBalance').text(new Intl.NumberFormat('vi-VN').format(data.new_money));
                    $('.bet-input').val('');
                }
            });
        }

        // Định kỳ cập nhật số dư bot sau mỗi ván
        setInterval(() => {
            $.getJSON('../api_cyber_racing.php?action=get_state&bot_name=bot_6', function(d) {
                if (d && d.bot_money !== null && d.bot_money !== undefined) {
                    $('#currentBalance').text(new Intl.NumberFormat('vi-VN').format(d.bot_money));
                }
            });
        }, 3000);
    </script>
</body>
</html>
