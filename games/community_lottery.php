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
    <title>Xổ Số Cộng Đồng | GTLM Gaming</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #020617;
            --panel: rgba(15, 23, 42, 0.7);
            --primary: #6366f1;
            --gold: #fbbf24;
            --text: #f8fafc;
            --glass: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
        }

        body {
            background: var(--bg);
            background-image: 
                radial-gradient(circle at 0% 0%, rgba(99, 102, 241, 0.1) 0%, transparent 40%),
                radial-gradient(circle at 100% 100%, rgba(251, 191, 36, 0.05) 0%, transparent 40%);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            margin: 0; padding: 0; min-height: 100vh;
        }

        .container { max-width: 1000px; margin: 0 auto; padding: 40px 20px; }

        /* --- Jackpot Header --- */
        .jackpot-card {
            background: var(--panel);
            backdrop-filter: blur(20px);
            border-radius: 40px;
            padding: 60px 40px;
            text-align: center;
            border: 1px solid var(--glass-border);
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }

        .jackpot-card::before {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: conic-gradient(from 0deg, transparent, rgba(251, 191, 36, 0.1), transparent 40%);
            animation: rotate 10s linear infinite;
        }

        @keyframes rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        .jackpot-label { font-size: 18px; font-weight: 700; color: #94a3b8; letter-spacing: 4px; text-transform: uppercase; margin-bottom: 10px; }
        .jackpot-value { 
            font-size: 80px; font-weight: 800; color: var(--gold); 
            text-shadow: 0 0 40px rgba(251, 191, 36, 0.4);
            font-family: 'JetBrains Mono', monospace;
        }

        .countdown-box { margin-top: 20px; font-size: 20px; font-weight: 600; color: #64748b; }
        .countdown-timer { color: var(--text); font-family: 'JetBrains Mono', monospace; font-size: 32px; margin-top: 10px; display: block; }

        /* --- Ticket Picker --- */
        .main-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px; }

        .panel-card {
            background: var(--panel);
            border-radius: 24px;
            padding: 30px;
            border: 1px solid var(--glass-border);
        }

        .panel-title { font-size: 20px; font-weight: 800; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }

        .number-grid {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 8px;
        }

        .num-btn {
            aspect-ratio: 1;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--glass-border);
            color: white;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
            font-size: 14px;
        }

        .num-btn:hover { background: rgba(255,255,255,0.1); border-color: var(--primary); }
        .num-btn.selected { background: var(--primary); border-color: var(--primary); box-shadow: 0 0 15px rgba(99, 102, 241, 0.5); }

        .selection-summary {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0,0,0,0.3);
            padding: 20px;
            border-radius: 16px;
        }

        .selected-nums-row { display: flex; gap: 10px; }
        .ball {
            width: 35px; height: 35px; border-radius: 50%;
            background: var(--primary); color: white;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 14px;
        }
        .ball.empty { background: rgba(255,255,255,0.05); border: 1px dashed var(--glass-border); color: transparent; }

        .buy-btn {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none; color: white; padding: 12px 24px; border-radius: 12px;
            font-weight: 800; cursor: pointer; transition: 0.3s;
        }
        .buy-btn:disabled { opacity: 0.3; cursor: not-allowed; }

        /* --- Live Draw Animation --- */
        #live-draw-area {
            display: none;
            margin-top: 40px;
            padding: 40px;
            background: linear-gradient(135deg, #1e1b4b, #312e81);
            border-radius: 32px;
            text-align: center;
            border: 2px solid var(--gold);
            animation: pulse-border 2s infinite;
        }

        @keyframes pulse-border {
            0%, 100% { border-color: var(--gold); box-shadow: 0 0 20px rgba(251, 191, 36, 0.2); }
            50% { border-color: #fff; box-shadow: 0 0 40px rgba(251, 191, 36, 0.4); }
        }

        .winning-balls { display: flex; justify-content: center; gap: 20px; margin-top: 30px; }
        .winning-ball {
            width: 60px; height: 60px; border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, #fff, var(--gold));
            color: #000; font-size: 24px; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
            animation: bounceIn 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) backwards;
        }

        @keyframes bounceIn { 0% { transform: scale(0); } 60% { transform: scale(1.2); } 100% { transform: scale(1); } }

    </style>
</head>
<body>

<div class="container">
    <div class="jackpot-card">
        <div class="jackpot-label">JACKPOT CỘNG ĐỒNG</div>
        <div class="jackpot-value" id="jackpot-amount">0 GTLM</div>
        <div class="countdown-box">
            QUAY THƯỞNG LÚC 20:00 HẰNG NGÀY<br>
            <span class="countdown-timer" id="countdown">00:00:00</span>
        </div>
    </div>

    <div id="live-draw-area">
        <h2 style="margin: 0; color: var(--gold);">🔴 ĐANG QUAY SỐ TRỰC TIẾP</h2>
        <div class="winning-balls" id="winning-balls">
            <!-- Balls appear one by one -->
        </div>
    </div>

    <div class="main-grid">
        <div class="panel-card">
            <div class="panel-title"><i class="fa fa-ticket-alt" style="color: var(--primary);"></i> CHỌN SỐ MAY MẮN</div>
            <div class="number-grid">
                <?php for($i=1; $i<=99; $i++): ?>
                    <button class="num-btn" onclick="toggleNum('<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>')"><?= str_pad($i, 2, '0', STR_PAD_LEFT) ?></button>
                <?php endfor; ?>
            </div>
            
            <div class="selection-summary">
                <div class="selected-nums-row" id="selected-row">
                    <div class="ball empty"></div>
                    <div class="ball empty"></div>
                    <div class="ball empty"></div>
                    <div class="ball empty"></div>
                    <div class="ball empty"></div>
                    <div class="ball empty"></div>
                </div>
                <button class="buy-btn" id="buy-btn" onclick="buyTicket()" disabled>MUA VÉ (10,000 GTLM)</button>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-title"><i class="fa fa-user-tag" style="color: var(--secondary);"></i> VÉ CỦA BẠN (HÔM NAY)</div>
            <div id="my-tickets" style="display: flex; flex-direction: column; gap: 10px;">
                <!-- User tickets -->
                <div style="opacity: 0.5; font-size: 14px; text-align: center; padding: 20px;">Bạn chưa mua vé nào.</div>
            </div>
        </div>
    </div>

    <!-- History -->
    <div class="panel-card" style="margin-top: 30px;">
        <div class="panel-title"><i class="fa fa-history"></i> LỊCH SỬ QUAY THƯỞNG</div>
        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
            <thead style="text-align: left; opacity: 0.6; font-size: 13px;">
                <tr>
                    <th style="padding: 10px;">Ngày quay</th>
                    <th>Kết quả</th>
                    <th>Giải thưởng</th>
                </tr>
            </thead>
            <tbody id="history-body">
                <!-- History rows -->
            </tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let selectedNums = [];
    let drawTime = null;
    let isDrawing = false;

    function formatMoney(n) { return Number(n).toLocaleString(); }

    function toggleNum(n) {
        const idx = selectedNums.indexOf(n);
        if (idx > -1) {
            selectedNums.splice(idx, 1);
            $(`.num-btn:contains('${n}')`).removeClass('selected');
        } else if (selectedNums.length < 6) {
            selectedNums.push(n);
            $(`.num-btn:contains('${n}')`).addClass('selected');
        }
        updateSelectionRow();
    }

    function updateSelectionRow() {
        const row = $('#selected-row');
        row.empty();
        const sorted = [...selectedNums].sort();
        for (let i = 0; i < 6; i++) {
            if (sorted[i]) {
                row.append(`<div class="ball">${sorted[i]}</div>`);
            } else {
                row.append(`<div class="ball empty"></div>`);
            }
        }
        $('#buy-btn').prop('disabled', selectedNums.length !== 6);
    }

    function refreshStatus() {
        $.get('../api_lottery.php?action=status', function(data) {
            if (!data.success) return;
            
            $('#jackpot-amount').text(formatMoney(data.today.jackpot) + ' GTLM');
            drawTime = new Date(data.today.draw_time.replace(/-/g, "/"));
            
            // Tickets
            const ticketsBox = $('#my-tickets');
            ticketsBox.empty();
            if (data.user_tickets.length > 0) {
                data.user_tickets.forEach(t => {
                    ticketsBox.append(`<div style="background: rgba(255,255,255,0.05); padding: 12px; border-radius: 12px; display: flex; justify-content: center; gap: 8px;">
                        ${t.split(',').map(n => `<span style="font-weight: 800; color: var(--primary);">${n}</span>`).join(' ')}
                    </div>`);
                });
            } else {
                ticketsBox.append(`<div style="opacity: 0.5; font-size: 14px; text-align: center; padding: 20px;">Bạn chưa mua vé nào.</div>`);
            }

            // Draw Check
            if (data.today.status === 'drawn' || data.today.status === 'paid') {
                showLiveDraw(data.today.winning_numbers);
            }
        });
    }

    function updateCountdown() {
        if (!drawTime) return;
        const now = new Date();
        const diff = drawTime - now;
        
        if (diff <= 0) {
            $('#countdown').text('00:00:00');
            if (!isDrawing) {
                isDrawing = true;
                setTimeout(refreshStatus, 2000); // Trigger draw check
            }
            return;
        }

        const h = Math.floor(diff / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        const s = Math.floor((diff % 60000) / 1000);
        
        $('#countdown').text(
            String(h).padStart(2, '0') + ':' + 
            String(m).padStart(2, '0') + ':' + 
            String(s).padStart(2, '0')
        );
    }

    function buyTicket() {
        if (selectedNums.length !== 6) return;
        const nums = [...selectedNums].sort().join(',');
        $.post('../api_lottery.php?action=buy', { numbers: nums }, function(data) {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Mua vé thành công!', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
                selectedNums = [];
                $('.num-btn').removeClass('selected');
                updateSelectionRow();
                refreshStatus();
            } else {
                Swal.fire('Lỗi', data.message, 'error');
            }
        });
    }

    function showLiveDraw(winningNums) {
        $('#live-draw-area').show();
        const container = $('#winning-balls');
        if (container.children().length > 0) return; // Already shown

        const nums = winningNums.split(',');
        nums.forEach((n, i) => {
            setTimeout(() => {
                container.append(`<div class="winning-ball">${n}</div>`);
            }, i * 1500); // Reveal one by one every 1.5s
        });
    }

    function loadHistory() {
        $.get('../api_lottery.php?action=history', function(data) {
            const body = $('#history-body');
            body.empty();
            data.history.forEach(h => {
                body.append(`
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <td style="padding: 15px 10px; font-weight: 600;">${h.draw_date}</td>
                        <td>${h.winning_numbers ? h.winning_numbers.split(',').join(' ') : '---'}</td>
                        <td style="color: var(--gold); font-weight: 800;">${formatMoney(h.jackpot_pool)}</td>
                    </tr>
                `);
            });
        });
    }

    $(document).ready(function() {
        refreshStatus();
        loadHistory();
        setInterval(updateCountdown, 1000);
    });
</script>
</body>
</html>
