<?php
session_start();
require_once '../db_connect.php';
if (!isset($_SESSION['Iduser'])) { header("Location: ../login.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đua Ngựa Pari-Mutuel | GTLM Gaming</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #020617;
            --panel: rgba(15, 23, 42, 0.7);
            --primary: #6366f1;
            --secondary: #a855f7;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gold: #fbbf24;
            --text: #f8fafc;
            --glass: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
        }

        body {
            background: var(--bg);
            background-image: 
                radial-gradient(circle at 0% 0%, rgba(99, 102, 241, 0.1) 0%, transparent 40%),
                radial-gradient(circle at 100% 100%, rgba(168, 85, 247, 0.1) 0%, transparent 40%);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* --- Header --- */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: var(--panel);
            padding: 20px 30px;
            border-radius: 24px;
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
        }

        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #818cf8, #c084fc);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .user-stats {
            display: flex;
            gap: 20px;
            font-weight: 600;
        }

        .money-box {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            padding: 8px 16px;
            border-radius: 12px;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        /* --- Track --- */
        .race-track-container {
            background: var(--panel);
            border-radius: 32px;
            padding: 40px;
            border: 1px solid var(--glass-border);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .track-lane {
            height: 60px;
            border-bottom: 1px dashed rgba(255,255,255,0.1);
            position: relative;
            display: flex;
            align-items: center;
        }

        .track-lane:last-child { border-bottom: none; }

        .horse {
            position: absolute;
            left: 0;
            width: 50px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            transition: left 0.1s linear;
            z-index: 10;
        }

        .horse::after {
            content: attr(data-num);
            position: absolute;
            top: -15px;
            font-size: 10px;
            font-weight: 800;
            background: rgba(0,0,0,0.5);
            padding: 2px 6px;
            border-radius: 4px;
        }

        .finish-line {
            position: absolute;
            right: 50px;
            top: 40px;
            bottom: 40px;
            width: 4px;
            background: repeating-linear-gradient(0deg, #fff, #fff 10px, #000 10px, #000 20px);
            box-shadow: 0 0 20px rgba(255,255,255,0.2);
        }

        /* --- Betting Panel --- */
        .betting-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .horse-bet-card {
            background: var(--panel);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }

        .horse-bet-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }

        .horse-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .horse-num-circle {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 20px;
            background: var(--primary);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .horse-odds {
            font-family: 'JetBrains Mono', monospace;
            font-size: 24px;
            font-weight: 800;
            color: var(--gold);
        }

        .bet-controls {
            display: flex;
            flex-direction: column;
            gap: 8px;
            text-align: right;
        }

        .bet-input-group {
            display: flex;
            gap: 8px;
        }

        .bet-input {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--glass-border);
            color: white;
            padding: 8px 12px;
            border-radius: 10px;
            width: 80px;
            font-weight: 600;
            outline: none;
        }

        .bet-btn {
            background: var(--primary);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
        }

        .bet-btn:hover { filter: brightness(1.2); }
        .bet-btn:disabled { opacity: 0.3; cursor: not-allowed; }

        .my-bet-info {
            font-size: 12px;
            color: var(--success);
            font-weight: 600;
        }

        /* --- Status Info --- */
        .status-bar {
            text-align: center;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .countdown {
            font-size: 32px;
            color: var(--gold);
            font-family: 'JetBrains Mono', monospace;
        }

        /* --- Overlays --- */
        #result-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(10px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            animation: fadeIn 0.5s ease;
        }

        .result-content {
            text-align: center;
            background: var(--panel);
            padding: 60px;
            border-radius: 40px;
            border: 2px solid var(--gold);
            box-shadow: 0 0 100px rgba(251, 191, 36, 0.2);
        }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .winner-announcement {
            font-size: 60px;
            font-weight: 800;
            color: var(--gold);
            margin-bottom: 20px;
        }

        /* --- Colors for horses --- */
        .h-1 { color: #ff4e50; }
        .h-2 { color: #f9d423; }
        .h-3 { color: #34e89e; }
        .h-4 { color: #00d2ff; }
        .h-5 { color: #a445b2; }
        .h-6 { color: #ff0099; }

    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <a href="../index.php" style="color: #64748b; text-decoration: none; font-size: 14px; display: block; margin-bottom: 5px;"><i class="fa fa-arrow-left"></i> Về sảnh</a>
            <h1>RACING DERBY</h1>
        </div>
        <div class="user-stats">
            <div class="money-box"><i class="fa fa-coins"></i> <span id="user-money">0</span> GTLM</div>
        </div>
    </div>

    <div class="status-bar">
        <span id="phase-text">GIAI ĐOẠN CƯỢC</span>: <span class="countdown" id="timer">00:60</span>
    </div>

    <div class="race-track-container">
        <div class="finish-line"></div>
        <div class="track-lane"><div class="horse h-1" id="horse-1" data-num="1">🐎</div></div>
        <div class="track-lane"><div class="horse h-2" id="horse-2" data-num="2">🐎</div></div>
        <div class="track-lane"><div class="horse h-3" id="horse-3" data-num="3">🐎</div></div>
        <div class="track-lane"><div class="horse h-4" id="horse-4" data-num="4">🐎</div></div>
        <div class="track-lane"><div class="horse h-5" id="horse-5" data-num="5">🐎</div></div>
        <div class="track-lane"><div class="horse h-6" id="horse-6" data-num="6">🐎</div></div>
    </div>

    <div class="betting-grid">
        <!-- 6 horse cards -->
        <?php for($i=1; $i<=6; $i++): ?>
        <div class="horse-bet-card">
            <div class="horse-info">
                <div class="horse-num-circle h-<?= $i ?>"><?= $i ?></div>
                <div>
                    <div class="horse-odds" id="odds-<?= $i ?>">x10.0</div>
                    <div style="font-size: 11px; color: #64748b;">Tổng cược: <span id="pool-<?= $i ?>">0</span></div>
                </div>
            </div>
            <div class="bet-controls">
                <div class="my-bet-info" id="my-bet-<?= $i ?>">Chưa cược</div>
                <div class="bet-input-group">
                    <input type="number" class="bet-input" id="input-<?= $i ?>" placeholder="Số tiền" min="1000" step="1000">
                    <button class="bet-btn" onclick="placeBet(<?= $i ?>)">CƯỢC</button>
                </div>
            </div>
        </div>
        <?php endfor; ?>
    </div>
</div>

<div id="result-overlay">
    <div class="result-content">
        <div style="font-size: 24px; font-weight: 700; color: #94a3b8; margin-bottom: 10px;">WINNER IS</div>
        <div class="winner-announcement" id="winner-text">HORSE #1</div>
        <div id="win-message" style="font-size: 20px; color: var(--success); font-weight: 700; display: none;">BẠN ĐÃ THẮNG +<span id="win-amount">0</span> GTLM!</div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let currentPhase = 'betting';
    let raceId = 0;
    let winnerHorse = null;
    let isRacing = false;
    let pollInterval = null;

    function formatMoney(n) {
        return Number(n).toLocaleString();
    }

    function updateStatus() {
        $.get('../api_horse_race.php?action=get_status', function(data) {
            if (!data.success) return;

            raceId = data.race_id;
            currentPhase = data.status;
            winnerHorse = data.winner_horse;
            
            $('#user-money').text(formatMoney(data.user_money));
            $('#timer').text(data.time_left);
            
            // Update Odds & Pools
            for (let i = 1; i <= 6; i++) {
                $(`#odds-${i}`).text('x' + data.odds[i]);
                $(`#pool-${i}`).text(formatMoney(data.horse_pools[i]));
                if (data.user_bets[i]) {
                    $(`#my-bet-${i}`).text('Đã cược: ' + formatMoney(data.user_bets[i]));
                } else {
                    $(`#my-bet-${i}`).text('Chưa cược');
                }
            }

            if (currentPhase === 'betting') {
                $('#phase-text').text('GIAI ĐOẠN CƯỢC');
                $('#result-overlay').hide();
                $('.bet-btn').prop('disabled', false);
                resetHorses();
                isRacing = false;
            } else if (currentPhase === 'racing') {
                $('#phase-text').text('ĐANG ĐUA...');
                $('.bet-btn').prop('disabled', true);
                if (!isRacing) startRaceAnimation(winnerHorse);
            } else if (currentPhase === 'result') {
                $('#phase-text').text('KẾT QUẢ');
                showResult(winnerHorse);
            }
        });
    }

    function resetHorses() {
        $('.horse').css('left', '0%');
    }

    function startRaceAnimation(winner) {
        isRacing = true;
        const trackWidth = $('.race-track-container').width() - 100;
        const finishLinePos = trackWidth;

        // Animate each horse with some randomness
        for (let i = 1; i <= 6; i++) {
            const isWinner = (i == winner);
            const duration = 12000; // 12s animation
            
            // Simulate progress
            let currentPos = 0;
            const interval = setInterval(() => {
                if (currentPos >= 100) {
                    clearInterval(interval);
                    return;
                }
                
                // Winner pulls ahead at the end, others lag slightly
                let step = Math.random() * 2;
                if (isWinner && currentPos > 70) step += 1.5;
                if (!isWinner && currentPos > 85) step *= 0.5;
                
                currentPos += step;
                if (currentPos > 95 && !isWinner) currentPos = 95; // Don't cross finish yet
                if (currentPos > 100) currentPos = 100;
                
                $(`#horse-${i}`).css('left', `calc(${currentPos}% - 50px)`);
            }, 100);
        }
    }

    function showResult(winner) {
        $('#winner-text').text('HORSE #' + winner);
        $('#result-overlay').css('display', 'flex');
        
        // Optional: check if user won this race (would need another API call or data in get_status)
        // For simplicity, I'll just show the winner.
    }

    function placeBet(horseNum) {
        const amount = $(`#input-${horseNum}`).val();
        if (!amount || amount < 1000) {
            Swal.fire('Lỗi', 'Số tiền cược tối thiểu là 1,000 GTLM', 'error');
            return;
        }

        $.post('../api_horse_race.php?action=place_bet', {
            horse_num: horseNum,
            amount: amount
        }, function(data) {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Đặt cược thành công',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
                $(`#input-${horseNum}`).val('');
                updateStatus();
            } else {
                Swal.fire('Lỗi', data.message, 'error');
            }
        });
    }

    $(document).ready(function() {
        updateStatus();
        pollInterval = setInterval(updateStatus, 1000);
    });

</script>
</body>
</html>
