<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_cyber_racing', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require_once '../db_connect.php';
require_once '../include_css.php';
require_once '../load_theme.php';

if (!isset($botUserId)) {
    header('Location: ../login.php');
    exit;
}

$isLive = isset($_GET['live']) && $_GET['live'] == 1;

if ($isLive) {
    require_once __DIR__ . '/../LiveStream/bot_streamer_helper.php';
    $botUser = getOrCreateBotStreamerUser($conn, 'bot_cyber_racing', 88888000);
    $money = $botUser['Money'];
    $userName = $botUser['Name'];
    $isAdmin = false;
} else {
    $userId = (int)$botUserId;
    $stmt = $conn->prepare("SELECT Money, Name, Role FROM users WHERE Iduser = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $money = $user['Money'] ?? 0;
    $userName = $user['Name'] ?? 'Player';
    $isAdmin = ($user['Role'] == 1);
}

// Xử lý các action nội bộ nếu cần (chỉ render view)
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đua Thú Cyberpunk - Trận Địa GTLM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <?php echo getCSSIncludes(['special_effects' => true]); ?>
    <script src="../assets/js/game-effects.js"></script>
    <script src="../assets/js/game-effects-auto.js"></script>
    <link rel="stylesheet" href="../assets/css/game-cyber-racing.css?v=<?= time() ?>">
</head>
<body style="background: <?= $bgGradientCSS ?? '#0a0a0a' ?>;">

<div class="cyber-racing-container">
<?php if ($isLive): ?>
    <div class="header" style="justify-content: center; background: rgba(0,255,136,0.1); border-bottom: 1px solid rgba(0,255,136,0.3); padding: 5px;">
        <div style="text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;">
            <p style="margin:0; color:#00ff88; font-size: 0.75rem; font-weight: 900; letter-spacing: 1px;"><i class="fa fa-robot"></i> STREAMER BOT: <?= htmlspecialchars($userName) ?></p>
            <div style="font-size: 1.1rem; font-weight: 900; color: #fff; background: rgba(0,0,0,0.5); padding: 2px 15px; border-radius: 20px;">
                <i class="fa fa-wallet" style="color:var(--gold);"></i> <span id="currentBalance"><?= number_format($money) ?></span> <small style="color:var(--gold); font-size: 0.7rem;">GTLM</small>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="header">
        <a href="../index.php" class="btn-back"><i class="fa fa-arrow-left"></i> Rời Bàn</a>
        <div class="title-box">
            <h1>ĐUA THÚ CYBERPUNK <span>2077</span></h1>
            <p>Tỷ lệ trả thưởng: x5.0 (No Fee)</p>
        </div>
        <div class="user-balance">
            <i class="fa fa-wallet"></i> <span id="currentBalance"><?= number_format($money) ?></span> GTLM
        </div>
    </div>
<?php endif; ?>

    <!-- Khu vực Đua (Racetrack) -->
    <div class="racetrack-area">
        <div class="status-overlay" id="statusOverlay">
            <h2 id="phaseText">CHỜ ĐẶT CƯỢC</h2>
            <div class="timer" id="timerDisplay">20</div>
        </div>

        <div class="finish-line">FINISH</div>

        <div class="lane" data-animal="wolf">
            <div class="lane-name">Sói Cyber</div>
            <div class="animal-sprite" id="anim_wolf">🐺<span class="flame" style="display:none;">🔥</span></div>
        </div>
        <div class="lane" data-animal="fox">
            <div class="lane-name">Cáo Neon</div>
            <div class="animal-sprite" id="anim_fox">🦊<span class="flame" style="display:none;">🔥</span></div>
        </div>
        <div class="lane" data-animal="panther">
            <div class="lane-name">Báo Plasma</div>
            <div class="animal-sprite" id="anim_panther">🐆<span class="flame" style="display:none;">🔥</span></div>
        </div>
        <div class="lane" data-animal="bear">
            <div class="lane-name">Gấu Sắt</div>
            <div class="animal-sprite" id="anim_bear">🐻<span class="flame" style="display:none;">🔥</span></div>
        </div>
        <div class="lane" data-animal="eagle">
            <div class="lane-name">Đại Bàng Lượng Tử</div>
            <div class="animal-sprite" id="anim_eagle">🦅<span class="flame" style="display:none;">🔥</span></div>
        </div>
    </div>

    <!-- Bảng Tỷ Lệ Thưởng -->
    <div class="payout-table">
        <h3>BẢNG TỶ LỆ TRẢ THƯỞNG</h3>
        <div class="payout-grid">
            <div class="payout-card top1">
                <h4>TOP 1</h4>
                <p>x3.0</p>
            </div>
            <div class="payout-card top2">
                <h4>TOP 2</h4>
                <p>x2.0</p>
            </div>
            <div class="payout-card top3">
                <h4>TOP 3</h4>
                <p>x0.5</p>
            </div>
            <div class="payout-card top45">
                <h4>TOP 4 & 5</h4>
                <p>Còn cái nịt</p>
            </div>
        </div>
    </div>

    <!-- Khu vực Đặt cược -->
    <div class="betting-board" id="bettingBoard">
        <div class="bet-item" data-animal="wolf" onclick="selectAnimal('wolf')">
            <h3>🐺 Sói Cyber</h3>
            <p>Tổng cược: <span class="total-bet-pool" id="pool_wolf">0</span></p>
            <p class="potential-text">Dự kiến húp: <span class="potential-win" id="win_wolf">0</span></p>
            <input type="number" class="bet-input" id="bet_wolf" placeholder="0" min="0" onkeyup="updateBetInput('wolf')" onchange="updateBetInput('wolf')" onclick="event.stopPropagation()">
        </div>
        <div class="bet-item" data-animal="fox" onclick="selectAnimal('fox')">
            <h3>🦊 Cáo Neon</h3>
            <p>Tổng cược: <span class="total-bet-pool" id="pool_fox">0</span></p>
            <p class="potential-text">Dự kiến húp: <span class="potential-win" id="win_fox">0</span></p>
            <input type="number" class="bet-input" id="bet_fox" placeholder="0" min="0" onkeyup="updateBetInput('fox')" onchange="updateBetInput('fox')" onclick="event.stopPropagation()">
        </div>
        <div class="bet-item" data-animal="panther" onclick="selectAnimal('panther')">
            <h3>🐆 Báo Plasma</h3>
            <p>Tổng cược: <span class="total-bet-pool" id="pool_panther">0</span></p>
            <p class="potential-text">Dự kiến húp: <span class="potential-win" id="win_panther">0</span></p>
            <input type="number" class="bet-input" id="bet_panther" placeholder="0" min="0" onkeyup="updateBetInput('panther')" onchange="updateBetInput('panther')" onclick="event.stopPropagation()">
        </div>
        <div class="bet-item" data-animal="bear" onclick="selectAnimal('bear')">
            <h3>🐻 Gấu Sắt</h3>
            <p>Tổng cược: <span class="total-bet-pool" id="pool_bear">0</span></p>
            <p class="potential-text">Dự kiến húp: <span class="potential-win" id="win_bear">0</span></p>
            <input type="number" class="bet-input" id="bet_bear" placeholder="0" min="0" onkeyup="updateBetInput('bear')" onchange="updateBetInput('bear')" onclick="event.stopPropagation()">
        </div>
        <div class="bet-item" data-animal="eagle" onclick="selectAnimal('eagle')">
            <h3>🦅 Đại Bàng Lượng Tử</h3>
            <p>Tổng cược: <span class="total-bet-pool" id="pool_eagle">0</span></p>
            <p class="potential-text">Dự kiến húp: <span class="potential-win" id="win_eagle">0</span></p>
            <input type="number" class="bet-input" id="bet_eagle" placeholder="0" min="0" onkeyup="updateBetInput('eagle')" onchange="updateBetInput('eagle')" onclick="event.stopPropagation()">
        </div>
    </div>

<?php if (!$isLive): ?>
    <!-- Controls -->
    <div class="controls-area">
        <div class="quick-bets">
            <button onclick="addBetSelected(10000)">+10K</button>
            <button onclick="addBetSelected(50000)">+50K</button>
            <button onclick="addBetSelected(100000)">+100K</button>
            <button onclick="addBetSelected(500000)">+500K</button>
            <button onclick="addBetSelected(1000000)">+1M</button>
            <button onclick="addBetSelected(5000000)">+5M</button>
            <button onclick="addBetSelected('ALL')" class="btn-all-in">ALL IN</button>
            <button onclick="clearBets()" class="btn-clear">XÓA</button>
        </div>
        <button class="btn-submit-bet" id="btnSubmitBet" onclick="submitBet()">XÁC NHẬN CƯỢC</button>
    </div>
<?php endif; ?>
</div>

<script src="../assets/js/game-cyber-racing.js?v=<?= time() ?>"></script>
<script>
    (function () {
        window.themeConfig = {
            particleCount: 500,
            particleSize: 0.05,
            particleColor: '#00ff88',
            particleOpacity: 0.4,
            shapeCount: 15,
            shapeColors: ["#00ff88", "#00b894", "#ffffff"],
            shapeOpacity: 0.15,
            bgGradient: ["#000000", "#002a1b", "#0a0a0a"]
        };
        const prefix = '../';
        ['threejs-background.js'].forEach(src => {
            const s = document.createElement('script');
            s.src = prefix + src; s.async = false;
            document.head.appendChild(s);
        });
    })();
</script>
<canvas id="threejs-background"></canvas>
<?php if ($isLive): ?>
<style>
    .bot-virtual-cursor {
        position: fixed; z-index: 99999999; pointer-events: none; opacity: 0; transform: scale(1);
        display: flex; flex-direction: column; align-items: center; filter: drop-shadow(0 5px 15px rgba(0,255,136,0.6));
    }
    .cursor-pointer-arrow { transform: rotate(-15deg); transform-origin: top left; }
    .cursor-bot-tag {
        background: rgba(0, 0, 0, 0.85); border: 1px solid #00ff88; color: #fff; padding: 4px 10px; border-radius: 20px;
        font-size: 0.7rem; font-weight: 800; white-space: nowrap; margin-top: 5px; display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(0,255,136,0.3);
    }
    .bot-tag-dot { display: inline-block; width: 6px; height: 6px; background: #00ff88; border-radius: 50%; box-shadow: 0 0 8px #00ff88; animation: pulse-dot 1.5s infinite; }
    @keyframes pulse-dot { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.5); opacity: 0.4; } }
</style>

<div id="botVirtualCursor" class="bot-virtual-cursor">
    <div class="cursor-pointer-arrow">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
            <path d="M3 3l7 18 3-7 7-3L3 3z" fill="#00ff88" stroke="#ffffff" stroke-width="2" stroke-linejoin="round"/>
        </svg>
    </div>
    <div class="cursor-bot-tag">
        <span class="bot-tag-dot"></span>
        <span id="cursorBotName"><?= htmlspecialchars($userName) ?></span>
    </div>
</div>

<script>
    window.IS_LIVE_MODE = true;
    let botHasBetThisCycle = false;
    let lastCycleId = 0;

    function autoSpectatorLoop() {
        if (!window.currentPhase || window.currentPhase !== 'betting') return;
        if (window.currentCycleId !== lastCycleId) {
            botHasBetThisCycle = false;
            lastCycleId = window.currentCycleId;
        }

        if (botHasBetThisCycle || Math.random() > 0.3) return;
        botHasBetThisCycle = true;

        const animals = ['wolf', 'fox', 'panther', 'bear', 'eagle'];
        const chosenAnimal = animals[Math.floor(Math.random() * animals.length)];
        const betAmount = Math.floor(Math.random() * 8 + 2) * 10000; 
        
        const cursor = $('#botVirtualCursor');
        gsap.set(cursor, { opacity: 1, left: 100, top: 100, scale: 1 });

        const targetTile = $(`.bet-item[data-animal="${chosenAnimal}"]`);
        if (targetTile.length === 0) return;
        const offset1 = targetTile.offset();

        gsap.to(cursor, {
            left: offset1.left + targetTile.width() / 2,
            top: offset1.top + targetTile.height() / 2,
            duration: 0.9,
            ease: "power2.out",
            onComplete: () => {
                gsap.to(cursor, { scale: 0.7, duration: 0.12, yoyo: true, repeat: 1, onComplete: () => {
                    targetTile.find('input').val(betAmount);
                    const betsData = [{ animal: chosenAnimal, amount: betAmount }];
                    $.post('../api_cyber_racing.php', { action: 'bot_bet', bets: JSON.stringify(betsData) }, function(res) {
                        const parsed = typeof res === 'string' ? JSON.parse(res) : res;
                        if (parsed.success && parsed.new_money !== undefined) {
                            $('#currentBalance').text(new Intl.NumberFormat('vi-VN').format(parsed.new_money));
                        }
                        gsap.to(cursor, { opacity: 0, delay: 0.5, duration: 0.4 });
                    });
                }});
            }
        });
    }

    setInterval(autoSpectatorLoop, 2000);
</script>
<?php endif; ?>

<!-- AUTO-GENERATED BOT SCRIPT -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script>
    if (typeof BotVirtualCursor !== "undefined") {
        BotVirtualCursor.init("Bot Streamer");
        setInterval(() => {
            const allBtns = Array.from(document.querySelectorAll("button, .btn-bet, .chip, .spin-btn, #btnSpin, .bet-button, .card, .btn-primary, .btn-success, input[type='button'], input[type='submit']"));
            const btns = allBtns.filter(b => {
                if(b.offsetParent === null || b.disabled) return false;
                const txt = (b.innerText || b.value || "").toLowerCase();
                const cls = (b.className || "").toLowerCase();
                const id = (b.id || "").toLowerCase();
                
                if(txt.includes("hướng dẫn") || txt.includes("trang chủ") || txt.includes("nạp") || txt.includes("rút") || txt.includes("lịch sử") || txt.includes("quay lại") || txt.includes("thoát")) return false;
                if(cls.includes("back") || cls.includes("help") || cls.includes("guide") || cls.includes("close") || cls.includes("swal") || cls.includes("nav")) return false;
                if(id.includes("guide") || id.includes("back") || id.includes("close") || id.includes("nav")) return false;
                
                return true;
            });
            
            if(btns.length > 0) {
                const btn = btns[Math.floor(Math.random() * btns.length)];
                BotVirtualCursor.moveToElement($(btn), 1, 0, () => {
                    setTimeout(() => { 
                        BotVirtualCursor.simulateClick(() => {
                            try { btn.click(); } catch(e){}
                        });
                    }, 500);
                });
            }
        }, 3000 + Math.random() * 4000);
    }
</script>

</body>
</html>
