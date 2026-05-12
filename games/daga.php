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
$stmt->close();

// Auto-create history table
$conn->query("CREATE TABLE IF NOT EXISTS history_daga (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Iduser INT NOT NULL,
    BetSide VARCHAR(20) NOT NULL,
    BetAmount DECIMAL(30,2) NOT NULL,
    Winner VARCHAR(20) NOT NULL,
    WinAmount DECIMAL(30,2) NOT NULL,
    Time DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (isset($_GET['action']) && $_GET['action'] === 'bet') {
    header('Content-Type: application/json');
    $side = $_POST['side']; // meron, wala, draw
    $amount = (int)$_POST['amount'];

    if ($amount <= 0 || $amount > $money) {
        echo json_encode(['success' => false, 'message' => 'Số dư không đủ!']);
        exit;
    }

    // Logic xác định người thắng
    // Meron: 48%, Wala: 48%, Draw: 4%
    $rand = rand(1, 100);
    $winner = '';
    if ($rand <= 48) $winner = 'meron';
    elseif ($rand <= 96) $winner = 'wala';
    else $winner = 'draw';

    $winAmount = -$amount;
    if ($side === $winner) {
        if ($side === 'draw') $winAmount = $amount * 8; // Draw x8
        else $winAmount = $amount * 0.95; // Side win x0.95 (commission)
        $winAmount += $amount; 
        $winAmount -= $amount; // Net win
    }
    
    // Thực tế winAmount trả về là số tiền THÊM VÀO hoặc BỊ TRỪ
    $finalWin = ($side === $winner) ? ($side === 'draw' ? $amount * 8 : $amount * 0.95) : -$amount;

    $newMoney = $money + $finalWin;
    $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
    $stmt->bind_param("di", $newMoney, $userId);
    $stmt->execute();

    // History
    $his = $conn->prepare("INSERT INTO history_daga (Iduser, BetSide, BetAmount, Winner, WinAmount, Time) VALUES (?, ?, ?, ?, ?, NOW())");
    $his->bind_param("isdsd", $userId, $side, $amount, $winner, $finalWin);
    $his->execute();

    echo json_encode([
        'success' => true,
        'winner' => $winner,
        'winAmount' => $finalWin,
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
    <title>🐓 Đá Gà Mini-game 🐓</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --meron: #ff4757;
            --wala: #2e86de;
            --draw: #2ecc71;
            --tet-gold: #fdcb6e;
        }
        body {
            background: #0a0a0a;
            color: white;
            font-family: 'Exo 2', sans-serif;
            text-align: center;
            overflow-x: hidden;
        }
        .arena {
            width: 95%;
            max-width: 900px;
            height: 450px;
            margin: 2rem auto;
            background: radial-gradient(circle, rgba(44, 62, 80, 0.5) 0%, rgba(26, 26, 26, 0.8) 100%), url('../assets/img/daga_bg.png') no-repeat center center;
            background-size: cover;
            position: relative;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 3rem;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5), inset 0 0 100px rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }
        .rooster {
            width: 180px;
            height: 180px;
            position: absolute;
            bottom: 60px;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            filter: drop-shadow(0 10px 20px rgba(0,0,0,0.5));
        }
        #rooster-meron { left: 80px; transform: scaleX(1); }
        #rooster-wala { right: 80px; transform: scaleX(-1); }

        .rooster-label {
            position: absolute;
            bottom: -30px;
            left: 50%;
            transform: translateX(-50%);
            padding: 5px 20px;
            border-radius: 50px;
            font-weight: 900;
            font-size: 0.8rem;
            letter-spacing: 2px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .shaking { animation: shake 0.1s infinite; }
        @keyframes shake {
            0% { transform: translate(2px, 1px) scaleX(var(--sx)); }
            50% { transform: translate(-2px, -1px) scaleX(var(--sx)); }
            100% { transform: translate(2px, -1px) scaleX(var(--sx)); }
        }

        .jump { animation: jump 0.4s infinite; }
        @keyframes jump {
            0%, 100% { bottom: 60px; }
            50% { bottom: 120px; }
        }

        .betting-area {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-bottom: 3rem;
            flex-wrap: wrap;
        }
        .bet-btn {
            padding: 1.2rem 2.5rem;
            font-size: 1.2rem;
            font-weight: 900;
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 2rem;
            cursor: pointer;
            transition: all 0.3s;
            color: white;
            text-transform: uppercase;
            letter-spacing: 2px;
            backdrop-filter: blur(10px);
        }
        .btn-meron { background: linear-gradient(135deg, var(--meron), #c0392b); box-shadow: 0 10px 20px rgba(231, 76, 60, 0.3); }
        .btn-wala { background: linear-gradient(135deg, var(--wala), #191970); box-shadow: 0 10px 20px rgba(52, 152, 219, 0.3); }
        .btn-draw { background: linear-gradient(135deg, var(--draw), #27ae60); box-shadow: 0 10px 20px rgba(46, 204, 113, 0.3); }
        
        .bet-btn:hover:not(:disabled) { transform: translateY(-5px) scale(1.05); filter: brightness(1.2); border-color: rgba(255,255,255,0.3); }
        
        .status-overlay {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            font-size: 5rem;
            font-weight: 900;
            text-shadow: 0 0 30px rgba(0,0,0,0.8);
            display: none;
            z-index: 100;
            letter-spacing: 10px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <h1 style="font-size: 3rem; font-weight: 900; margin: 2rem 0; letter-spacing: 5px; text-shadow: 0 0 20px rgba(255,255,255,0.2);">🐓 ĐÁ GÀ TRỰC TIẾP 🐓</h1>
    <div class="balance" style="margin-bottom: 2rem;">💰 Số dư: <b id="money-display" style="color: var(--tet-gold); font-size: 1.5rem; text-shadow: 0 0 10px rgba(253, 203, 110, 0.3);"><?= number_format($money, 0, ',', '.') ?></b> GTLM</div>

    <div class="arena">
        <div id="rooster-meron" class="rooster" style="--sx: 1">
            <span style="font-size: 7rem; filter: drop-shadow(0 5px 15px rgba(255,0,0,0.5));">🐓</span>
            <div class="rooster-label" style="background: var(--meron);">MERON</div>
        </div>
        <div id="rooster-wala" class="rooster" style="--sx: -1">
            <span style="font-size: 7rem; filter: drop-shadow(0 5px 15px rgba(0,0,255,0.5));">🐓</span>
            <div class="rooster-label" style="background: var(--wala);">WALA</div>
        </div>
        <div id="status" class="status-overlay">FIGHT!</div>
    </div>

    <div class="info-guide" style="max-width: 800px; margin: 1rem auto; background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 10px; font-size: 0.9rem; border-left: 5px solid var(--tet-gold);">
        💡 <b>THỬ VẬN:</b> Chọn số GTLM muốn liều và nhấn vào nút <b>CHIẾN MERON</b>, <b>CHIẾN WALA</b> hoặc <b>HÒA</b>. 
        Tỷ lệ húp: Meron/Wala (1 ăn 0.95), Hòa (1 ăn 8). Trận giao lưu sẽ diễn ra trong 3 giây!
    </div>

    <div class="controls">
        <div class="quick-bets" style="margin-bottom: 1rem; display: flex; justify-content: center; gap: 0.5rem;">
            <button class="btn-small" onclick="$('#bet-amount').val(1000)">1K</button>
            <button class="btn-small" onclick="$('#bet-amount').val(10000)">10K</button>
            <button class="btn-small" onclick="$('#bet-amount').val(50000)">50K</button>
            <button class="btn-small" onclick="$('#bet-amount').val(100000)">100K</button>
            <button class="btn-small" onclick="$('#bet-amount').val(<?= $money ?>)">TẤT TAY</button>
        </div>
        <input type="number" id="bet-amount" placeholder="GTLM thả thính..." value="1000" style="background: #000; color: #fff; border: 1px solid #444;"><br><br>
        <div class="betting-area">
            <button class="bet-btn btn-meron" onclick="placeBet('meron')">CHIẾN MERON<br><small>1 húp 0.95</small></button>
            <button class="bet-btn btn-draw" onclick="placeBet('draw')">HÒA (BDD)<br><small>1 húp 8</small></button>
            <button class="bet-btn btn-wala" onclick="placeBet('wala')">CHIẾN WALA<br><small>1 húp 0.95</small></button>
        </div>
    </div>

    <div class="history-log" style="max-width: 800px; margin: 2rem auto; background: #222; padding: 1rem; border-radius: 10px;">
        <h3>📜 LỊCH SỬ GẦN ĐÂY</h3>
        <div id="history-content" style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
            <!-- History bubbles will appear here -->
        </div>
    </div>

    <a href="../index.php" style="color: #888; text-decoration: none; display: block; margin-bottom: 3rem;">🏠 Quay lại sảnh</a>

    <style>
        .btn-small { background: #333; color: #fff; border: 1px solid #555; padding: 5px 15px; border-radius: 5px; cursor: pointer; }
        .btn-small:hover { background: #444; border-color: #777; }
        .history-bubble { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.2rem; }
        .bubble-meron { background: var(--meron); color: white; }
        .bubble-wala { background: var(--wala); color: white; }
        .bubble-draw { background: var(--draw); color: white; }
    </style>

    <script>
        let isPlaying = false;

        function loadHistory() {
            $.get('daga.php?action=get_history', function(data) {
                // Not implemented in PHP yet, let's just use a dummy for now or update PHP
            });
            // Let's just update the JS to add a bubble after each win
        }

        function addHistoryBubble(winner) {
            const char = winner === 'meron' ? 'M' : (winner === 'wala' ? 'W' : 'D');
            const cls = 'bubble-' + winner;
            $('#history-content').prepend(`<div class="history-bubble ${cls}">${char}</div>`);
            if ($('#history-content .history-bubble').length > 15) {
                $('#history-content .history-bubble:last').remove();
            }
        }

        function placeBet(side) {
            if (isPlaying) return;
            const amount = $('#bet-amount').val();
            if (amount < 1000) {
                Swal.fire('Lỗi', 'Liều tối thiểu 1.000 GTLM', 'error');
                return;
            }

            isPlaying = true;
            $('.bet-btn, .btn-small').prop('disabled', true);
            
            $('#status').text('ĐANG CHIẾN...').fadeIn();
            $('#rooster-meron, #rooster-wala').addClass('shaking jump');
            
            $('#rooster-meron').animate({ left: '300px' }, 2000);
            $('#rooster-wala').animate({ right: '300px' }, 2000);

            $.post('daga.php?action=bet', { side: side, amount: amount }, function(data) {
                if (!data.success) {
                    Swal.fire('Lỗi', data.message, 'error');
                    resetArena();
                    return;
                }

                setTimeout(() => {
                    $('#rooster-meron, #rooster-wala').removeClass('shaking jump');
                    $('#status').text(data.winner.toUpperCase() + ' HÚP!').css('color', data.winner === 'meron' ? 'red' : (data.winner === 'wala' ? 'blue' : 'green'));
                    
                    addHistoryBubble(data.winner);

                    if (data.winner === 'meron') {
                        $('#rooster-wala').fadeOut();
                        $('#rooster-meron').animate({ left: '325px' }, 500);
                    } else if (data.winner === 'wala') {
                        $('#rooster-meron').fadeOut();
                        $('#rooster-wala').animate({ right: '325px' }, 500);
                    } else {
                        // Draw
                    }

                    setTimeout(() => {
                        $('#money-display').text(data.newMoney);
                        if (data.winAmount > 0) {
                            Swal.fire('Húp Lớn!', `Chúc mừng! Bạn đã húp được ${(data.winAmount).toLocaleString()} GTLM!`, 'success');
                        } else {
                            Swal.fire('Về Cõi!', `Tiếc quá, ${data.winner} đã húp rồi.`, 'error');
                        }
                        resetArena();
                    }, 1000);
                }, 3000);
            });
        }

        function resetArena() {
            isPlaying = false;
            $('.bet-btn, .btn-small').prop('disabled', false);
            $('#status').hide();
            $('#rooster-meron').show().css('left', '100px');
            $('#rooster-wala').show().css('right', '100px');
        }
    </script>
</body>
</html>
