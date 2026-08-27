<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_11', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require '../db_connect.php';
require_once '../include_css.php';
require_once '../load_theme.php';



$userId = $botUserId;
$stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$money = $user['Money'];
$userName = $user['Name'];
$stmt->close();

if (isset($_GET['action']) && $_GET['action'] === 'play') {
    header('Content-Type: application/json');
    $bet = (int)$_POST['bet'];

    if ($bet < 1000 || $bet > $money) {
        echo json_encode(['success' => false, 'message' => 'Lượng Chiến không hợp lệ hoặc nick khô hạn!']);
        exit;
    }

    // Trừ GTLM cược
    $newMoney = $money - $bet;
    $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
    $stmt->bind_param("di", $newMoney, $userId);
    $stmt->execute();

    // Mô phỏng trận đấu Battle Royale
    // 5 vòng đấu
    // Vòng 1: 100 -> 50
    // Vòng 2: 50 -> 20
    // Vòng 3: 20 -> 10
    // Vòng 4: 10 -> 3
    // Vòng 5: 3 -> 1 (Winner)

    $rounds = [];
    $survived = true;
    $finalRank = 100;

    // Vòng 1
    $r1 = rand(1, 100);
    if ($r1 > 40) { $survived = false; $finalRank = rand(51, 100); }
    $rounds[] = ['round' => 1, 'survived' => $survived, 'playersLeft' => 50];

    if ($survived) {
        // Vòng 2
        $r2 = rand(1, 100);
        if ($r2 > 35) { $survived = false; $finalRank = rand(21, 50); } // 35% chance to pass
        $rounds[] = ['round' => 2, 'survived' => $survived, 'playersLeft' => 20];
    }

    if ($survived) {
        // Vòng 3
        $r3 = rand(1, 100);
        if ($r3 > 30) { $survived = false; $finalRank = rand(11, 20); } // 30% chance to pass
        $rounds[] = ['round' => 3, 'survived' => $survived, 'playersLeft' => 10];
    }

    if ($survived) {
        // Vòng 4
        $r4 = rand(1, 100);
        if ($r4 > 25) { $survived = false; $finalRank = rand(4, 10); } // 25% chance to pass
        $rounds[] = ['round' => 4, 'survived' => $survived, 'playersLeft' => 3];
    }

    if ($survived) {
        // Vòng 5 (Chung kết)
        $r5 = rand(1, 100);
        if ($r5 > 20) { $survived = false; $finalRank = rand(2, 3); } // 20% chance to pass
        $rounds[] = ['round' => 5, 'survived' => $survived, 'playersLeft' => 1];
    }

    $winAmount = 0;
    if ($finalRank == 1) {
        $winAmount = $bet * 10; // Jackpot x10
    } elseif ($finalRank <= 3) {
        $winAmount = $bet * 5; // Died in V5 (Rank 2-3) -> passed V4
    } elseif ($finalRank <= 10) {
        $winAmount = $bet * 2; // Died in V4 (Rank 4-10) -> passed V3
    }

    if ($winAmount > 0) {
        $newMoney += $winAmount;
        $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $stmt->bind_param("di", $newMoney, $userId);
        $stmt->execute();
        
        // Thông báo húp lớn nếu Top 1
        if ($finalRank == 1) {
            $msg = "🏆 " . htmlspecialchars($userName) . " đã HÚP TRỌN Battle Royale và nhận " . number_format($winAmount) . " GTLM! 👑";
            $expiresAt = date('Y-m-d H:i:s', time() + 60);
            $conn->query("INSERT INTO server_notifications (user_id, user_name, message, amount, notification_type, expires_at) VALUES ($userId, '$userName', '$msg', $winAmount, 'big_win', '$expiresAt')");
        }
    }

    echo json_encode([
        'success' => true,
        'rounds' => $rounds,
        'finalRank' => $survived ? 1 : $finalRank,
        'winAmount' => $winAmount,
        'newMoney' => number_format($newMoney, 0, ',', '.'),
        'rawMoney' => $newMoney
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>🔥 BATTLE ROYALE SỐ 🔥</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php echo getCSSIncludes(['special_effects' => true]); ?>
    <style>
        body {
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            color: #00f2fe;
            font-family: 'Exo 2', sans-serif;
            text-transform: uppercase;
            overflow-x: hidden;
        }
        body::before {
            content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: radial-gradient(circle, transparent 0%, rgba(0,0,0,0.8) 100%); z-index: -1;
        }
        .br-container { 
            max-width: 1400px; 
            margin: 2rem auto; 
            padding: 2rem; 
            background: rgba(10, 10, 20, 0.8); 
            backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 242, 254, 0.3); 
            border-radius: 2rem; 
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.8), 0 0 20px rgba(0, 242, 254, 0.1); 
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 2rem;
            align-items: start;
        }
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        .main-area {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            text-align: center;
        }
        .stats { 
            display: flex; 
            flex-direction: column;
            gap: 1rem;
            font-size: 1.1rem; 
            border-bottom: 1px solid rgba(255,255,255,0.05); 
            padding-bottom: 1.5rem; 
            font-weight: 800;
            letter-spacing: 1px;
            text-align: center;
        }
        .players-list { 
            display: grid; 
            grid-template-columns: repeat(14, 1fr); 
            gap: 8px; 
            padding: 1.5rem;
            background: rgba(0,0,0,0.3);
            border-radius: 1.5rem;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .player-icon { 
            width: 100%;
            aspect-ratio: 1;
            background: rgba(255,255,255,0.05); 
            border: 1px solid rgba(255,255,255,0.1); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            border-radius: 8px; 
            font-size: 0.7rem; 
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            color: rgba(255,255,255,0.5);
            cursor: url('../img/tay.png'), pointer !important;
        }
        .player-icon:hover:not(.dead):not(.me) {
            background: rgba(0, 242, 254, 0.2);
            border-color: #00f2fe;
            color: #fff;
            transform: scale(1.05);
        }
        .player-icon.dead { 
            background: rgba(255, 0, 0, 0.1); 
            border-color: #f00; 
            color: #f00; 
            transform: scale(0.8);
            opacity: 0.3;
            box-shadow: 0 0 10px rgba(255,0,0,0.2);
        }
        .player-icon.me { 
            background: #00f2fe; 
            color: #000; 
            font-weight: 900; 
            box-shadow: 0 0 20px rgba(0, 242, 254, 0.5); 
            border-color: #fff;
            transform: scale(1.1);
            z-index: 2;
        }
        
        .round-info { 
            font-size: 2.5rem; 
            color: #fff; 
            margin: 1.5rem 0; 
            text-shadow: 0 0 20px rgba(0, 242, 254, 0.5); 
            font-weight: 900;
            letter-spacing: 5px;
        }
        .btn-join { 
            background: linear-gradient(135deg, #00f2fe, #4facfe); 
            color: #000; 
            border: none; 
            padding: 1.2rem 4rem; 
            font-size: 1.8rem; 
            font-weight: 900; 
            border-radius: 50px; 
            cursor: pointer; 
            transition: all 0.3s; 
            box-shadow: 0 10px 30px rgba(0, 242, 254, 0.3); 
            text-transform: uppercase;
            letter-spacing: 3px;
        }
        .btn-join:hover:not(:disabled) { 
            transform: translateY(-5px) scale(1.05); 
            box-shadow: 0 15px 40px rgba(0, 242, 254, 0.5); 
        }
        .btn-join:disabled { filter: grayscale(1); opacity: 0.5; }
        
        #log { 
            font-family: 'Consolas', monospace; 
            font-size: 0.85rem; 
            color: #00f2fe; 
            background: rgba(0, 0, 0, 0.5); 
            padding: 1.5rem; 
            height: 120px; 
            overflow-y: auto; 
            text-align: left; 
            border: 1px solid rgba(0, 242, 254, 0.2); 
            border-radius: 1rem;
            margin: 1.5rem 0;
        }
    </style>
</head>
<body>
    <div class="br-container">
        <!-- SIDEBAR -->
        <div class="sidebar">
            <h1 style="text-align: center; margin: 0; font-family: 'Orbitron'; font-size: 2rem;">🏆 TRẬN ĐỊA 🏆</h1>

            <div class="info-guide" style="background: rgba(0, 242, 254, 0.1); border: 1px solid #00f2fe; padding: 1rem; border-radius: 10px; font-size: 0.85rem; text-align: left; text-transform: none; line-height: 1.4;">
                🛡️ <b>GIẢI THƯỞNG:</b> 100 người chơi, 5 vòng sinh tử. 
                <br>Qua <b>Vòng 3</b>: Húp x2 Chiến
                <br>Qua <b>Vòng 4</b>: Húp x5 Chiến
                <br>Sống sót đến cuối (<b>Hạng 1</b>): Húp <b>JACKPOT x10</b> GTLM đã Chiến!
            </div>

            <div class="stats" style="overflow-wrap: break-word; word-break: break-word;">
                <div style="margin-bottom: 1rem;">💰 TRONG NICK:<br><span id="balance" style="font-size: 1.2rem; color: #fff; display: inline-block; max-width: 100%; word-break: break-all;"><?= number_format($money, 0, ',', '.') ?></span> GTLM</div>
                <div>👥 CÒN LẠI:<br><span id="players-count" style="font-size: 1.5rem; color: #fff;">100</span>/100</div>
            </div>

            <div style="text-align: center;">
                <div class="quick-bets" style="margin-bottom: 1rem; display: flex; justify-content: center; gap: 0.5rem; flex-wrap: wrap;">
                    <button class="btn-small" onclick="$('#bet').val(1000)">1K</button>
                    <button class="btn-small" onclick="$('#bet').val(10000)">10K</button>
                    <button class="btn-small" onclick="$('#bet').val(50000)">50K</button>
                    <button class="btn-small" onclick="$('#bet').val(100000)">100K</button>
                    <button class="btn-small" onclick="$('#bet').val(500000)">500K</button>
                    <button class="btn-small" onclick="$('#bet').val(1000000)">1M</button>
                </div>
                <input type="number" id="bet" value="1000" step="1000" style="padding: 1rem; font-size: 1.5rem; background: #000; color: #fff; border: 1px solid #00f2fe; border-radius: 10px; width: 100%; text-align: center; box-sizing: border-box; margin-bottom: 1rem;" placeholder="GTLM thả thính...">
                <button id="join-btn" class="btn-join" style="width: 100%; padding: 1rem; font-size: 1.4rem;">RA CHIÊU</button>
            </div>

            <div id="log">Trận địa: Chào mừng <?= htmlspecialchars($userName) ?> tham gia giao lưu...</div>

            <p style="text-align: center;"><a href="../index.php" style="color: #4facfe; text-decoration: none; opacity: 0.6;">🏠 QUAY LẠI SẢNH</a></p>
        </div>

        <!-- MAIN AREA -->
        <div class="main-area">
            <div class="round-tracker" style="display: flex; justify-content: space-between; background: #111; padding: 1rem; border-radius: 50px;">
                <div class="r-step" id="step-1">Vòng 1</div>
                <div class="r-step" id="step-2">Vòng 2</div>
                <div class="r-step" id="step-3">Vòng 3</div>
                <div class="r-step" id="step-4">Vòng 4</div>
                <div class="r-step" id="step-5">Chung Kết</div>
            </div>

            <div id="round-text" class="round-info">CHỜ TRẬN ĐỊA BẮT ĐẦU...</div>

            <div class="players-list" id="players-grid">
                <!-- 100 players -->
            </div>
        </div>
    </div>

    <style>
        .r-step { flex: 1; text-align: center; font-size: 0.8rem; color: #555; position: relative; }
        .r-step.active { color: #00f2fe; font-weight: bold; text-shadow: 0 0 10px #00f2fe; }
        .r-step.done { color: #2ecc71; }
        .btn-small { background: #111; color: #00f2fe; border: 1px solid #00f2fe; padding: 5px 15px; border-radius: 5px; cursor: pointer; font-family: 'Oswald'; }
        .btn-small:hover { background: #00f2fe; color: #000; }
    </style>

    <script>
        let myPosition = 1;
        let isPlaying = false;

        function initGrid() {
            let html = '';
            for(let i=1; i<=100; i++) {
                let isMe = (i === myPosition); 
                html += `<div class="player-icon ${isMe ? 'me' : ''}" id="p-${i}" onclick="selectPosition(${i})">#${i}</div>`;
            }
            $('#players-grid').html(html);
            $('.r-step').removeClass('active done');
        }

        function selectPosition(pos) {
            if (isPlaying) return;
            myPosition = pos;
            initGrid();
        }

        initGrid();

        $('#join-btn').click(async function() {
            const bet = $('#bet').val();
            if (bet < 1000) { Swal.fire('!', 'Chiến tối thiểu 1.000 GTLM', 'warning'); return; }

            isPlaying = true;
            $(this).prop('disabled', true);
            initGrid();
            
            $.post('?action=play', { bet: bet }, async function(data) {
                if(!data.success) {
                    Swal.fire('Lỗi', data.message, 'error');
                    $('#join-btn').prop('disabled', false);
                    isPlaying = false;
                    return;
                }

                $('#balance').text(data.newMoney);

                for(let r of data.rounds) {
                    $(`#step-${r.round}`).addClass('active');
                    $('#round-text').text(`VÒNG GIAO LƯU ${r.round}: ĐANG LOẠI DẦN...`);
                    addLog(`Vòng ${r.round} bắt đầu...`);
                    
                    let eliminateCount = 100 - r.playersLeft;
                    let currentlyDead = $('.player-icon.dead').length;
                    let toKill = eliminateCount - currentlyDead;

                    let killed = 0;
                    while(killed < toKill) {
                        let target = Math.floor(Math.random() * 100) + 1;
                        if (target !== myPosition && !$(`#p-${target}`).hasClass('dead')) {
                            $(`#p-${target}`).addClass('dead');
                            killed++;
                        }
                    }

                    await sleep(1500);

                    if(!r.survived) {
                        $(`#p-${myPosition}`).addClass('dead');
                        $('#round-text').text(`VỀ CÕI Ở VỊ TRÍ #${data.finalRank}!`);
                        if (data.winAmount > 0) {
                            addLog(`Dừng ở hạng ${data.finalRank}. Giải an ủi: +${data.winAmount.toLocaleString()} GTLM`);
                            if (window.GameEffects) window.GameEffects.showWin(data.winAmount);
                            
                            const float = $('<div class="floating-win">+' + data.winAmount.toLocaleString('vi-VN') + '</div>').appendTo($(`#p-${myPosition}`));
                            gsap.to(float, { y: -100, opacity: 0, duration: 2, onComplete: () => float.remove() });
                            
                            $('#balance').text(data.newMoney);
                        } else {
                            addLog(`BẠN ĐÃ VỀ CÕI! Hạng: ${data.finalRank}`);
                            if (window.GameEffects) window.GameEffects.showLoss(bet);
                            
                            const float = $('<div class="floating-win" style="color: #ff4757;">-' + parseInt(bet).toLocaleString('vi-VN') + '</div>').appendTo($(`#p-${myPosition}`));
                            gsap.to(float, { y: -100, opacity: 0, duration: 2, onComplete: () => float.remove() });
                        }
                        $('#join-btn').prop('disabled', false);
                        isPlaying = false;
                        return;
                    }
                    
                    $(`#step-${r.round}`).removeClass('active').addClass('done');
                    $('#players-count').text(r.playersLeft);
                    addLog(`Bản thân đã sống sót qua Vòng ${r.round}.`);
                }

                $('#round-text').text(`CHÚC MỪNG! BẠN LÀ NGƯỜI DUY NHẤT CÒN SỐNG!`);
                addLog(`HÚP TRỌN JACKPOT! +${data.winAmount.toLocaleString()} GTLM!`);
                if (window.GameEffects) window.GameEffects.showWin(data.winAmount);
                
                const float = $('<div class="floating-win">+' + data.winAmount.toLocaleString('vi-VN') + '</div>').appendTo($(`#p-${myPosition}`));
                gsap.to(float, { y: -100, opacity: 0, duration: 2, onComplete: () => float.remove() });
                
                $('#balance').text(data.newMoney);
                $('#join-btn').prop('disabled', false);
                isPlaying = false;
            });
        });

        function addLog(msg) {
            $('#log').prepend(`<div>> ${msg}</div>`);
        }

        function sleep(ms) { return new Promise(resolve => setTimeout(resolve, ms)); }

        (function () {
            window.themeConfig = {
                particleCount: 300,
                particleSize: 0.05,
                particleColor: '#00f2fe',
                particleOpacity: 0.4,
                shapeCount: 15,
                shapeColors: ["#00f2fe", "#4facfe", "#ffffff"],
                shapeOpacity: 0.15,
                bgGradient: ["#000000", "#000510", "#001020"]
            };
            const prefix = '../';
            ['threejs-background.js', 'assets/js/game-effects.js'].forEach(src => {
                const s = document.createElement('script');
                s.src = prefix + src; s.async = false;
                document.head.appendChild(s);
            });
        })();
    </script>
    <canvas id="threejs-background"></canvas>

<!-- AUTO-GENERATED BOT SCRIPT -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script src="../assets/js/bots/bot_11.js?v=<?= time() ?>"></script>
</body>
</html>
