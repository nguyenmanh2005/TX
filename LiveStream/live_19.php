<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';

// --- KÊNH ĐANG BẢO TRÌ ---
die('<div style="color: #fbbf24; text-align: center; font-family: sans-serif; margin-top: 20vh;"><h2><i class="fa fa-wrench"></i> KÊNH LIVE NÀY ĐANG BẢO TRÌ</h2><p>Xin lỗi đạo hữu, kênh này đang được nâng cấp. Vui lòng chọn kênh khác!</p></div>');

$botUser = getOrCreateBotStreamerUser($conn, 'bot_19', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

require_once '../db_connect.php';

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
            pointer-events: none;
        }

        @keyframes rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        .jackpot-label { position: relative; z-index: 1; font-size: 18px; font-weight: 700; color: #94a3b8; letter-spacing: 4px; text-transform: uppercase; margin-bottom: 10px; }
        .jackpot-value { 
            position: relative; z-index: 1;
            font-size: 80px; font-weight: 800; color: var(--gold); 
            text-shadow: 0 0 40px rgba(251, 191, 36, 0.4);
            font-family: 'JetBrains Mono', monospace;
        }

        .countdown-box { position: relative; z-index: 1; margin-top: 20px; font-size: 20px; font-weight: 600; color: #64748b; }
        .countdown-timer { color: var(--text); font-family: 'JetBrains Mono', monospace; font-size: 32px; margin-top: 10px; display: block; }

        /* --- Ticket Picker --- */
        .main-grid { display: grid; grid-template-columns: 1.5fr 1fr 1fr; gap: 20px; }
        
        @media (max-width: 900px) {
            .main-grid { grid-template-columns: 1fr; }
        }

        /* Custom Scrollbar for inner boxes */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

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

        /* --- GLOBAL HOME BUTTON --- */
        .home-fab {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 10001;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 22px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 999px;
            color: white;
            text-decoration: none;
            font-weight: 700;
            font-size: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer !important;
        }

        .home-fab:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.4);
            color: white;
            text-decoration: none;
        }

        .home-fab-icon {
            font-size: 20px;
            line-height: 1;
        }

        @media (max-width: 768px) {
            .home-fab {
                top: 15px;
                left: 15px;
                padding: 10px 18px;
                font-size: 14px;
            }
        }
        /* Bonus Wheel Modal */
        #bonus-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }
        .wheel-box {
            background: var(--panel-bg);
            border: 1px solid var(--glass-border);
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 0 30px rgba(0,0,0,0.8);
            position: relative;
        }
        .wheel-container {
            position: relative;
            width: 280px;
            height: 280px;
            margin: 20px auto;
        }
        .wheel {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 5px solid #fff;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
            /* 4 colors: red, blue, green, yellow */
            background: conic-gradient(
                #f43f5e 0deg 90deg,
                #3b82f6 90deg 180deg,
                #10b981 180deg 270deg,
                #f59e0b 270deg 360deg
            );
            transition: transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99);
            position: relative;
        }
        .wheel-pointer {
            position: absolute;
            top: -15px;
            left: calc(50% - 15px);
            width: 30px;
            height: 30px;
            background: white;
            clip-path: polygon(50% 100%, 0 0, 100% 0);
            z-index: 10;
            filter: drop-shadow(0 4px 4px rgba(0,0,0,0.5));
        }
        .wheel-label {
            position: absolute;
            width: 50%;
            height: 20px;
            top: calc(50% - 10px);
            left: 50%;
            transform-origin: 0 50%;
            color: white;
            font-weight: 800;
            font-size: 14px;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .wl-1 { transform: rotate(-45deg); }
        .wl-2 { transform: rotate(45deg); }
        .wl-3 { transform: rotate(135deg); }
        .wl-4 { transform: rotate(225deg); }
        
        .spin-action-btn {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 18px;
            font-weight: 800;
            border-radius: 10px;
            cursor: pointer;
            text-transform: uppercase;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        .spin-action-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.6);
        }
        .spin-action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
    </style>
</head>
<body>

<a href="../index.php" class="home-fab fade-in">
    <span class="home-fab-icon">🏠</span>
    <span class="home-fab-text">Trang chủ</span>
</a>

<!-- BONUS WHEEL MODAL -->
<div id="bonus-modal">
    <div class="wheel-box">
        <h2 style="margin: 0; color: var(--gold); text-shadow: 0 0 10px rgba(251, 191, 36, 0.5);">VÒNG QUAY MAY MẮN</h2>
        <div style="font-size: 13px; opacity: 0.8; margin-top: 5px;">Vé đang chọn: <span id="bw-ticket-id" style="font-weight: 800; color: var(--primary);"></span></div>
        
        <div class="wheel-container">
            <div class="wheel-pointer"></div>
            <div class="wheel" id="bonus-wheel">
                <div class="wheel-label wl-1">x Tổng User</div>
                <div class="wheel-label wl-2">x2 GTLM</div>
                <div class="wheel-label wl-3">+50K GTLM</div>
                <div class="wheel-label wl-4">+1 Vé Free</div>
            </div>
        </div>
        
        <button class="spin-action-btn" id="start-spin-btn" onclick="executeBonusSpin()">BẮT ĐẦU QUAY</button>
        <div style="margin-top: 15px;">
            <button onclick="closeBonusModal()" style="background: transparent; border: 1px solid rgba(255,255,255,0.2); color: white; padding: 8px 20px; border-radius: 8px; cursor: pointer;">Đóng</button>
        </div>
    </div>
</div>

<div class="container">
    <div class="jackpot-card">
        <div class="jackpot-label">JACKPOT CỘNG ĐỒNG</div>
        <div class="jackpot-value" id="jackpot-amount">0 GTLM</div>
        <div class="countdown-box">
            QUAY THƯỞNG LÚC 20:00 HẰNG NGÀY<br>
            <span class="countdown-timer" id="countdown">00:00:00</span>
            <?php 
            $isAdmin = (isset($_SESSION['admin']) && $_SESSION['admin'] == true) || (isset($_SESSION['Role']) && $_SESSION['Role'] == 1);
            if ($isAdmin): 
            ?>
                <div style="margin-top: 15px; display: flex; gap: 10px; justify-content: center;">
                    <button class="buy-btn" style="background: linear-gradient(135deg, #64748b, #475569); font-size: 13px; padding: 6px 12px;" onclick="triggerTestDraw()">⚡ QUAY TEST (ẢO)</button>
                    <button class="buy-btn" style="background: linear-gradient(135deg, #f43f5e, #be123c); font-size: 13px; padding: 6px 12px;" onclick="triggerForceDraw()">🔴 QUAY CHỐT SỔ LUÔN</button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="live-draw-area">
        <h2 style="margin: 0; color: var(--gold);" id="live-draw-title">🔴 ĐANG QUAY SỐ TRỰC TIẾP</h2>
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
            <div id="my-tickets" style="display: flex; flex-direction: column; gap: 10px; max-height: 400px; overflow-y: auto; padding-right: 5px;">
                <!-- User tickets -->
                <div style="opacity: 0.5; font-size: 14px; text-align: center; padding: 20px;">Bạn chưa mua vé nào.</div>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-title" style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 10px;"><i class="fa fa-users" style="color: #10b981;"></i> VÉ CỘNG ĐỒNG</div>
            </div>
            <div style="margin-bottom: 15px;">
                <input type="text" id="search-community" placeholder="Tìm tên / số..." oninput="loadCommunityTickets()" style="width: 100%; padding: 8px 12px; border-radius: 8px; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: white; outline: none; font-family: inherit; font-size: 13px;">
            </div>
            <div id="community-tickets" style="display: flex; flex-direction: column; gap: 10px; max-height: 350px; overflow-y: auto; padding-right: 5px;">
                <!-- Community tickets -->
                <div style="opacity: 0.5; font-size: 14px; text-align: center; padding: 20px;">Đang tải...</div>
            </div>
        </div>
    </div>

    <!-- Prizes -->
    <div class="panel-card" style="margin-top: 30px;">
        <div class="panel-title"><i class="fa fa-gift" style="color: #f43f5e;"></i> CƠ CẤU GIẢI THƯỞNG</div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
            <div style="background: rgba(251, 191, 36, 0.1); border: 1px solid var(--gold); padding: 15px; border-radius: 12px; text-align: center;">
                <div style="color: var(--gold); font-weight: 800; font-size: 18px;">GIẢI ĐẶC BIỆT</div>
                <div style="font-size: 13px; opacity: 0.8; margin-top: 5px;">Trùng 6 số</div>
                <div style="font-weight: 800; font-size: 20px; margin-top: 5px; color: var(--gold)">JACKPOT</div>
            </div>
            <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 12px; text-align: center; border: 1px solid var(--glass-border);">
                <div style="color: #fff; font-weight: 800; font-size: 18px;">GIẢI NHẤT</div>
                <div style="font-size: 13px; opacity: 0.8; margin-top: 5px;">Trùng 5 số</div>
                <div style="color: var(--primary); font-weight: 800; font-size: 20px; margin-top: 5px;">1,000,000</div>
            </div>
            <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 12px; text-align: center; border: 1px solid var(--glass-border);">
                <div style="color: #fff; font-weight: 800; font-size: 18px;">GIẢI BỐN</div>
                <div style="font-size: 13px; opacity: 0.8; margin-top: 5px;">Trùng 4 số</div>
                <div style="color: var(--primary); font-weight: 800; font-size: 20px; margin-top: 5px;">160,000</div>
            </div>
            <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 12px; text-align: center; border: 1px solid var(--glass-border);">
                <div style="color: #fff; font-weight: 800; font-size: 18px;">GIẢI NĂM</div>
                <div style="font-size: 13px; opacity: 0.8; margin-top: 5px;">Trùng 3 số</div>
                <div style="color: var(--primary); font-weight: 800; font-size: 20px; margin-top: 5px;">80,000</div>
            </div>
            <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 12px; text-align: center; border: 1px solid var(--glass-border);">
                <div style="color: #fff; font-weight: 800; font-size: 18px;">GIẢI SÁU</div>
                <div style="font-size: 13px; opacity: 0.8; margin-top: 5px;">Trùng 2 số</div>
                <div style="color: var(--primary); font-weight: 800; font-size: 20px; margin-top: 5px;">40,000</div>
            </div>
            <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 12px; text-align: center; border: 1px solid var(--glass-border);">
                <div style="color: #fff; font-weight: 800; font-size: 18px;">GIẢI BẢY</div>
                <div style="font-size: 13px; opacity: 0.8; margin-top: 5px;">Trùng 1 số</div>
                <div style="color: var(--primary); font-weight: 800; font-size: 20px; margin-top: 5px;">20,000</div>
            </div>
        </div>
    </div>

    <!-- History Section -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px; margin-top: 30px;">
        <!-- Global Draw History -->
        <div class="panel-card" style="margin-top: 0;">
            <div class="panel-title"><i class="fa fa-history"></i> LỊCH SỬ QUAY THƯỞNG</div>
            <div style="max-height: 400px; overflow-y: auto; padding-right: 5px;">
                <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                    <thead style="text-align: left; opacity: 0.6; font-size: 13px;">
                        <tr>
                            <th style="padding: 10px; position: sticky; top: 0; background: var(--panel);">Ngày quay</th>
                            <th style="position: sticky; top: 0; background: var(--panel);">Kết quả</th>
                            <th style="position: sticky; top: 0; background: var(--panel);">Giải thưởng</th>
                        </tr>
                    </thead>
                    <tbody id="history-body">
                        <!-- History rows -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Personal Ticket History -->
        <div class="panel-card" style="margin-top: 0;">
            <div class="panel-title" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <div style="display: flex; align-items: center; gap: 10px;"><i class="fa fa-clipboard-list" style="color: #f97316;"></i> LỊCH SỬ VÉ CỦA BẠN</div>
                <input type="text" id="search-my-history" placeholder="Tìm ngày / số..." oninput="loadMyTicketHistory()" style="padding: 6px 12px; border-radius: 6px; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: white; outline: none; font-family: inherit; font-size: 12px; width: 140px;">
            </div>
            <div style="max-height: 400px; overflow-y: auto; padding-right: 5px;">
                <table style="width: 100%; border-collapse: collapse; margin-top: 0;">
                    <thead style="text-align: left; opacity: 0.6; font-size: 13px;">
                        <tr>
                            <th style="padding: 10px; position: sticky; top: 0; background: var(--panel);">Ngày</th>
                            <th style="position: sticky; top: 0; background: var(--panel);">Số chọn</th>
                            <th style="position: sticky; top: 0; background: var(--panel);">Kết quả</th>
                        </tr>
                    </thead>
                    <tbody id="my-ticket-history-body">
                        <!-- My history rows -->
                    </tbody>
                </table>
            </div>
        </div>
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
                    let borderStyle = '1px solid rgba(255,255,255,0.05)';
                    let glowStyle = '';
                    let prizeLabel = '';
                    let bonusButton = '';
                    
                    if (t.prize_amount > 0) {
                        if (t.prize_level === 6) {
                            borderStyle = '1px solid var(--gold)';
                            glowStyle = 'box-shadow: 0 0 15px rgba(251, 191, 36, 0.4);';
                            prizeLabel = `<div style="font-size: 12px; color: var(--gold); font-weight: 800; margin-top: 5px;">🎉 TRÚNG ${formatMoney(t.prize_amount)}</div>`;
                        } else if (t.prize_level >= 4) {
                            borderStyle = '1px solid #10b981'; // Green for high tiers
                            glowStyle = 'box-shadow: 0 0 10px rgba(16, 185, 129, 0.3);';
                            prizeLabel = `<div style="font-size: 12px; color: #10b981; font-weight: 800; margin-top: 5px;">✨ TRÚNG ${formatMoney(t.prize_amount)}</div>`;
                        } else {
                            borderStyle = '1px solid #f97316'; // Orange/Bronze for low tiers
                            glowStyle = 'box-shadow: 0 0 8px rgba(249, 115, 22, 0.2);';
                            prizeLabel = `<div style="font-size: 12px; color: #f97316; font-weight: 800; margin-top: 5px;">🔥 TRÚNG ${formatMoney(t.prize_amount)}</div>`;
                        }

                        if (t.is_bonus_spun === 0) {
                            bonusButton = `<button onclick="spinBonus(${t.id})" style="margin-top: 8px; width: 100%; padding: 6px; border-radius: 6px; border: none; background: linear-gradient(135deg, #10b981, #059669); color: white; font-weight: 800; font-size: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px; animation: pulse 2s infinite;"><i class="fa fa-gift"></i> QUAY BONUS</button>`;
                        } else {
                            bonusButton = `<div style="margin-top: 8px; font-size: 11px; opacity: 0.5;"><i class="fa fa-check"></i> Đã nhận thưởng bonus</div>`;
                        }
                    }

                    ticketsBox.append(`<div style="background: rgba(255,255,255,0.05); padding: 12px; border-radius: 12px; border: ${borderStyle}; ${glowStyle} text-align: center;">
                        <div style="display: flex; justify-content: center; gap: 8px;">
                            ${t.numbers.split(',').map(n => `<span style="font-weight: 800; color: var(--primary);">${n}</span>`).join(' ')}
                        </div>
                        ${prizeLabel}
                        ${bonusButton}
                    </div>`);
                });
            } else {
                ticketsBox.append(`<div style="opacity: 0.5; font-size: 14px; text-align: center; padding: 20px;">Bạn chưa mua vé nào.</div>`);
            }

            // Draw Check
            if (data.today.status === 'drawn' || data.today.status === 'paid') {
                showLiveDraw(data.today.winning_numbers, '🔴 KẾT QUẢ QUAY SỐ HÔM NAY (' + data.today.date + ')', true);
            } else if (data.last_draw && (data.last_draw.status === 'drawn' || data.last_draw.status === 'paid')) {
                showLiveDraw(data.last_draw.winning_numbers, '🏆 KẾT QUẢ KỲ QUAY GẦN NHẤT (' + data.last_draw.date + ')', false);
                if (typeof isDrawing !== 'undefined') isDrawing = false;
            } else if (typeof isDrawing !== 'undefined' && isDrawing) {
                // Keep polling every 3 seconds until the cron job completes the draw
                setTimeout(refreshStatus, 3000);
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

    function showLiveDraw(winningNums, titleText = '🔴 ĐANG QUAY SỐ TRỰC TIẾP', isLive = false) {
        $('#live-draw-area').show();
        if ($('#live-draw-title').length) {
            $('#live-draw-title').html(titleText);
        }
        if (!isLive) {
            $('#live-draw-area').css({
                'border-color': 'rgba(255, 255, 255, 0.2)',
                'animation': 'none',
                'background': 'linear-gradient(135deg, #0f172a, #1e293b)'
            });
        } else {
            $('#live-draw-area').css({
                'border-color': 'var(--gold)',
                'animation': 'pulse-border 2s infinite',
                'background': 'linear-gradient(135deg, #1e1b4b, #312e81)'
            });
        }
        const container = $('#winning-balls');
        if (container.children().length > 0 && container.attr('data-nums') === winningNums) return; // Already shown exactly these numbers

        container.empty();
        container.attr('data-nums', winningNums);

        const nums = winningNums.split(',');
        if (!isLive) {
            nums.forEach(n => {
                container.append(`<div class="winning-ball" style="animation: none;">${n}</div>`);
            });
        } else {
            nums.forEach((n, i) => {
                setTimeout(() => {
                    container.append(`<div class="winning-ball">${n}</div>`);
                }, i * 1500); // Reveal one by one every 1.5s
            });
        }
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

    function triggerTestDraw() {
        $.post('../api_lottery.php?action=test_draw', function(data) {
            if (data.success) {
                $('#winning-balls').empty();
                $('#live-draw-area').hide();
                
                Swal.fire({
                    icon: 'info',
                    title: 'Bắt đầu quay số ẢO!',
                    text: data.message,
                    showConfirmButton: false,
                    timer: 2000
                });
                
                setTimeout(() => {
                    refreshStatus(); // this will show the animation
                }, 2000);
                
                // Revert after 12 seconds
                setTimeout(() => {
                    $.post('../api_lottery.php?action=revert_test', function() {
                        $('#winning-balls').empty();
                        $('#live-draw-area').hide();
                        refreshStatus();
                    });
                }, 14000);
            } else {
                Swal.fire('Lỗi', data.message, 'error');
            }
        });
    }

    function triggerForceDraw() {
        Swal.fire({
            title: 'Chốt Sổ Ngay Lập Tức?',
            text: "Kỳ quay sẽ kết thúc ngay, trả thưởng cho người chơi và mở ngay kỳ quay mới!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f43f5e',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Đúng, Chốt ngay!',
            cancelButtonText: 'Hủy'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('../api_lottery.php?action=force_draw', function(data) {
                    if (data.success) {
                        $('#winning-balls').empty();
                        $('#live-draw-area').hide();
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Chốt sổ thành công!',
                            text: data.message,
                            showConfirmButton: false,
                            timer: 2000
                        });
                        
                        setTimeout(() => {
                            refreshStatus();
                            loadHistory();
                        }, 2000);
                    } else {
                        Swal.fire('Lỗi', data.message, 'error');
                    }
                });
            }
        });
    }

    let currentBonusTicketId = null;

    function spinBonus(ticketId) {
        currentBonusTicketId = ticketId;
        $('#bw-ticket-id').text('#' + ticketId);
        
        // Reset wheel and buttons
        $('#bonus-wheel').css({
            'transition': 'none',
            'transform': 'rotate(0deg)'
        });
        $('#start-spin-btn').prop('disabled', false).text('BẮT ĐẦU QUAY');
        
        // Show Modal
        $('#bonus-modal').css('display', 'flex');
    }

    function closeBonusModal() {
        $('#bonus-modal').fadeOut();
    }

    function executeBonusSpin() {
        if (!currentBonusTicketId) return;
        
        $('#start-spin-btn').prop('disabled', true).text('ĐANG QUAY...');
        
        // Give it an initial spin effect before API returns (optional)
        // We will just wait for API to return the target degree
        $.post('../api_lottery.php?action=spin_bonus', {ticket_id: currentBonusTicketId}, function(res) {
            if (res.success) {
                // Determine target angle based on prize
                let targetDeg = 0;
                // red (x_users) = 315, blue (x2) = 225, green (50k) = 135, yellow (free) = 45
                // We add 3600 (10 spins) to make it spin long
                if (res.prize_type === 'x_users') targetDeg = 3600 + 315;
                else if (res.prize_type === 'x2') targetDeg = 3600 + 225;
                else if (res.prize_type === 'fixed_50k') targetDeg = 3600 + 135;
                else if (res.prize_type === 'free_ticket') targetDeg = 3600 + 45;
                
                // Add a random variance +- 20 degrees so it doesn't land exactly center every time
                let variance = Math.floor(Math.random() * 40) - 20;
                targetDeg += variance;

                // Spin it!
                $('#bonus-wheel').css({
                    'transition': 'transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99)',
                    'transform': `rotate(${targetDeg}deg)`
                });

                // Wait 4 seconds for animation to finish
                setTimeout(() => {
                    closeBonusModal(); // Đóng vòng quay trước khi báo kết quả
                    
                    let icon = 'success';
                    if (res.prize_type === 'x_users') icon = 'warning';
                    
                    Swal.fire({
                        title: 'BÙM! 🎇',
                        html: res.message, // using html to support the red admin warning text
                        icon: icon,
                        confirmButtonText: 'TUYỆT VỜI!'
                    }).then(() => {
                        refreshStatus();
                        if (typeof updateHeaderMoney === 'function') updateHeaderMoney();
                    });
                }, 4000);

            } else {
                closeBonusModal();
                Swal.fire('Oái!', res.message, 'error');
            }
        }, 'json').fail(function() {
            closeBonusModal();
            Swal.fire('Lỗi kết nối', 'Không thể quay thưởng lúc này', 'error');
        });
    }

    function loadCommunityTickets() {
        const search = $('#search-community').val() || '';
        $.get('../api_lottery.php?action=community_tickets&search=' + encodeURIComponent(search), function(data) {
            if (!data.success) return;
            const container = $('#community-tickets');
            container.empty();
            if (data.tickets.length > 0) {
                data.tickets.forEach(t => {
                    const avatarStyle = t.frame ? `background: url('${t.frame}') center/cover; padding: 4px;` : '';
                    container.append(`
                        <div style="background: rgba(255,255,255,0.05); padding: 10px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 10px;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; overflow: hidden; ${avatarStyle} flex-shrink: 0;">
                                <img src="${t.avatar}" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                            </div>
                            <div style="flex-grow: 1; overflow: hidden;">
                                <div style="font-weight: 800; font-size: 13px; color: #fff; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;">${t.name}</div>
                                <div style="display: flex; gap: 4px; margin-top: 4px;">
                                    ${t.numbers.split(',').map(n => `<span style="font-size: 11px; font-weight: 700; background: rgba(99, 102, 241, 0.2); color: var(--primary); padding: 2px 4px; border-radius: 4px;">${n}</span>`).join('')}
                                </div>
                            </div>
                        </div>
                    `);
                });
            } else {
                container.append(`<div style="opacity: 0.5; font-size: 14px; text-align: center; padding: 20px;">Không tìm thấy vé nào.</div>`);
            }
        }, 'json');
    }

    function loadMyTicketHistory() {
        const search = $('#search-my-history').val() || '';
        $.get('../api_lottery.php?action=my_ticket_history&search=' + encodeURIComponent(search), function(data) {
            if (!data.success) return;
            const body = $('#my-ticket-history-body');
            body.empty();
            if (data.history.length > 0) {
                data.history.forEach(h => {
                    let statusLabel = '';
                    if (h.draw_status === 'paid' || h.draw_status === 'drawn') {
                        if (h.prize_amount > 0) {
                            statusLabel = `<span style="color: #10b981; font-weight: 800;">TRÚNG ${formatMoney(h.prize_amount)}</span>`;
                        } else {
                            statusLabel = `<span style="opacity: 0.5;">Trượt</span>`;
                        }
                    } else {
                        statusLabel = `<span style="color: var(--gold);">Đang chờ</span>`;
                    }

                    body.append(`
                        <tr style="border-bottom: 1px solid var(--glass-border);">
                            <td style="padding: 15px 10px; font-weight: 600; font-size: 13px;">${h.draw_date}</td>
                            <td style="font-size: 12px; font-family: 'JetBrains Mono', monospace;">
                                ${h.numbers.split(',').map(n => `<span style="color: ${h.winning_numbers && h.winning_numbers.includes(n) ? '#10b981' : 'var(--primary)'}; font-weight: bold;">${n}</span>`).join(' ')}
                            </td>
                            <td style="font-size: 12px;">${statusLabel}</td>
                        </tr>
                    `);
                });
            } else {
                body.append(`<tr><td colspan="3" style="text-align: center; padding: 20px; opacity: 0.5;">Không có lịch sử.</td></tr>`);
            }
        }, 'json');
    }

    $(document).ready(function() {
        refreshStatus();
        loadHistory();
        loadCommunityTickets();
        loadMyTicketHistory();
        setInterval(updateCountdown, 1000);

        // Custom Cursor
        document.body.style.cursor = "url('../chuot.png'), auto";
        const interactiveElements = document.querySelectorAll('button, a, label, select, .num-btn');
        interactiveElements.forEach(el => {
            el.style.cursor = "url('../img/tay.png'), pointer";
        });
        const textInputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="password"]');
        textInputs.forEach(input => {
            input.style.cursor = "text";
        });
    });
</script>

<!-- AUTO-GENERATED BOT SCRIPT -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script src="bots/bot_19.js"></script>

</body>
</html>

