<?php
session_start();
require '../db_connect.php';
require_once '../load_theme.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['Iduser'];
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
        echo json_encode(['success' => false, 'message' => 'Lượng liều không hợp lệ hoặc nick khô hạn!']);
        exit;
    }

    // Trừ tiền cược
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
    $survivalChance1 = 50; 
    $r1 = rand(1, 100);
    if ($r1 > $survivalChance1) { $survived = false; $finalRank = rand(51, 100); }
    $rounds[] = ['round' => 1, 'survived' => $survived, 'playersLeft' => 50];

    if ($survived) {
        // Vòng 2
        $r2 = rand(1, 100);
        if ($r2 > 40) { $survived = false; $finalRank = rand(21, 50); } // 40% chance to pass
        $rounds[] = ['round' => 2, 'survived' => $survived, 'playersLeft' => 20];
    }

    if ($survived) {
        // Vòng 3
        $r3 = rand(1, 100);
        if ($r3 > 50) { $survived = false; $finalRank = rand(11, 20); } // 50% chance to pass
        $rounds[] = ['round' => 3, 'survived' => $survived, 'playersLeft' => 10];
    }

    if ($survived) {
        // Vòng 4
        $r4 = rand(1, 100);
        if ($r4 > 30) { $survived = false; $finalRank = rand(4, 10); } // 30% chance to pass
        $rounds[] = ['round' => 4, 'survived' => $survived, 'playersLeft' => 3];
    }

    if ($survived) {
        // Vòng 5 (Chung kết)
        $r5 = rand(1, 100);
        if ($r5 > 33) { $survived = false; $finalRank = rand(2, 3); } // 33% chance to pass
        $rounds[] = ['round' => 5, 'survived' => $survived, 'playersLeft' => 1];
    }

    $winAmount = 0;
    if ($survived && $finalRank == 1) {
        $winAmount = $bet * 50; // Jackpot x50
        $newMoney += $winAmount;
        $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $stmt->bind_param("di", $newMoney, $userId);
        $stmt->execute();
        
        // Thông báo húp lớn
        $msg = "🏆 " . htmlspecialchars($userName) . " đã HÚP TRỌN Battle Royale và nhận " . number_format($winAmount) . " GTLM! 👑";
        $expiresAt = date('Y-m-d H:i:s', time() + 60);
        $conn->query("INSERT INTO server_notifications (user_id, user_name, message, amount, notification_type, expires_at) VALUES ($userId, '$userName', '$msg', $winAmount, 'big_win', '$expiresAt')");
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
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: #020205 url('../assets/img/br_bg.png') no-repeat center center fixed;
            background-size: cover;
            color: #00f2fe;
            font-family: 'Exo 2', sans-serif;
            text-transform: uppercase;
            overflow-x: hidden;
        }
        body::before {
            content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: radial-gradient(circle, transparent 0%, rgba(0,0,0,0.8) 100%); z-index: -1;
        }
        .container { 
            max-width: 1000px; 
            margin: 2rem auto; 
            padding: 3rem; 
            background: rgba(10, 10, 20, 0.8); 
            backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 242, 254, 0.3); 
            border-radius: 3rem; 
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.8), 0 0 20px rgba(0, 242, 254, 0.1); 
        }
        .stats { 
            display: flex; 
            justify-content: space-around; 
            margin-bottom: 2.5rem; 
            font-size: 1.2rem; 
            border-bottom: 1px solid rgba(255,255,255,0.05); 
            padding-bottom: 1.5rem; 
            font-weight: 800;
            letter-spacing: 2px;
        }
        .players-list { 
            display: grid; 
            grid-template-columns: repeat(10, 1fr); 
            gap: 12px; 
            margin: 2.5rem 0; 
            padding: 1rem;
            background: rgba(0,0,0,0.3);
            border-radius: 2rem;
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
    <div class="container">
        <h1>🏆 TRẬN ĐỊA SINH TỒN 🏆</h1>

        <div class="info-guide" style="background: rgba(0, 242, 254, 0.1); border: 1px solid #00f2fe; padding: 1rem; border-radius: 10px; margin-bottom: 2rem; font-size: 0.9rem; text-align: left; text-transform: none;">
            🛡️ <b>THỬ VẬN:</b> Bạn cùng 99 người chơi khác sẽ trải qua 5 vòng giao lưu sinh tử. 
            Tại mỗi vòng, một số lượng người giao lưu sẽ bị loại ngẫu nhiên. Nếu bạn sống sót đến cuối cùng (Hạng 1), bạn sẽ <b>HÚP JACKPOT x50</b> GTLM đã liều!
        </div>

        <div class="round-tracker" style="display: flex; justify-content: space-between; margin-bottom: 2rem; background: #111; padding: 1rem; border-radius: 50px;">
            <div class="r-step" id="step-1">Vòng 1</div>
            <div class="r-step" id="step-2">Vòng 2</div>
            <div class="r-step" id="step-3">Vòng 3</div>
            <div class="r-step" id="step-4">Vòng 4</div>
            <div class="r-step" id="step-5">Chung Kết</div>
        </div>

        <div class="stats">
            <div>💰 TRONG NICK: <span id="balance"><?= number_format($money, 0, ',', '.') ?></span> GTLM</div>
            <div>👥 CÒN LẠI: <span id="players-count">100</span>/100</div>
        </div>

        <div id="round-text" class="round-info">CHỜ TRẬN ĐỊA BẮT ĐẦU...</div>

        <div class="players-list" id="players-grid">
            <!-- 100 players -->
        </div>

        <div id="log">Trận địa: Chào mừng <?= htmlspecialchars($userName) ?> tham gia giao lưu...</div>

        <div style="margin-top: 2rem;">
            <div class="quick-bets" style="margin-bottom: 1rem; display: flex; justify-content: center; gap: 0.5rem;">
                <button class="btn-small" onclick="$('#bet').val(1000)">1K</button>
                <button class="btn-small" onclick="$('#bet').val(10000)">10K</button>
                <button class="btn-small" onclick="$('#bet').val(50000)">50K</button>
                <button class="btn-small" onclick="$('#bet').val(100000)">100K</button>
            </div>
            <input type="number" id="bet" value="1000" step="1000" style="padding: 1rem; font-size: 1.5rem; background: #000; color: #fff; border: 1px solid #00f2fe; border-radius: 10px; width: 200px; text-align: center;" placeholder="GTLM thả thính...">
            <br><br>
            <button id="join-btn" class="btn-join">RA CHIÊU (HÚP X50)</button>
        </div>
        
        <p style="margin-top: 2rem;"><a href="../index.php" style="color: #4facfe; text-decoration: none;">🏠 QUAY LẠI SẢNH</a></p>
    </div>

    <style>
        .r-step { flex: 1; text-align: center; font-size: 0.8rem; color: #555; position: relative; }
        .r-step.active { color: #00f2fe; font-weight: bold; text-shadow: 0 0 10px #00f2fe; }
        .r-step.done { color: #2ecc71; }
        .btn-small { background: #111; color: #00f2fe; border: 1px solid #00f2fe; padding: 5px 15px; border-radius: 5px; cursor: pointer; font-family: 'Oswald'; }
        .btn-small:hover { background: #00f2fe; color: #000; }
    </style>

    <script>
        function initGrid() {
            let html = '';
            for(let i=1; i<=100; i++) {
                let isMe = (i === 1); 
                html += `<div class="player-icon ${isMe ? 'me' : ''}" id="p-${i}">${isMe ? 'YOU' : '#'+i}</div>`;
            }
            $('#players-grid').html(html);
            $('.r-step').removeClass('active done');
        }
        initGrid();

        $('#join-btn').click(async function() {
            const bet = $('#bet').val();
            if (bet < 1000) { Swal.fire('!', 'Liều tối thiểu 1.000 GTLM', 'warning'); return; }

            $(this).prop('disabled', true);
            initGrid();
            
            $.post('battleroyale.php?action=play', { bet: bet }, async function(data) {
                if(!data.success) {
                    Swal.fire('Lỗi', data.message, 'error');
                    $('#join-btn').prop('disabled', false);
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
                        let target = Math.floor(Math.random() * 99) + 2;
                        if (!$(`#p-${target}`).hasClass('dead')) {
                            $(`#p-${target}`).addClass('dead');
                            killed++;
                        }
                    }

                    await sleep(1500);

                    if(!r.survived) {
                        $('#p-1').addClass('dead');
                        $('#round-text').text(`BẠN ĐÃ VỀ CÕI Ở VỊ TRÍ #${data.finalRank}!`);
                        addLog(`BẠN ĐÃ VỀ CÕI! Hạng: ${data.finalRank}`);
                        Swal.fire('BAY MÀU!', `Vị trí dừng chân: #${data.finalRank}`, 'error');
                        $('#join-btn').prop('disabled', false);
                        return;
                    }
                    
                    $(`#step-${r.round}`).removeClass('active').addClass('done');
                    $('#players-count').text(r.playersLeft);
                    addLog(`Bản thân đã sống sót qua Vòng ${r.round}.`);
                }

                $('#round-text').text(`CHÚC MỪNG! BẠN LÀ NGƯỜI DUY NHẤT CÒN SỐNG!`);
                addLog(`HÚP TRỌN JACKPOT! +${data.winAmount.toLocaleString()} GTLM!`);
                Swal.fire('ĂN NGẬP MẶT!', `Bạn nhận được ${data.winAmount.toLocaleString()} GTLM!`, 'success');
                $('#balance').text(data.newMoney);
                $('#join-btn').prop('disabled', false);
            });
        });

        function addLog(msg) {
            $('#log').prepend(`<div>> ${msg}</div>`);
        }

        function sleep(ms) { return new Promise(resolve => setTimeout(resolve, ms)); }
    </script>
</body>
</html>
