<?php
session_start();
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}
require 'db_connect.php';
require_once 'load_theme.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trung Tâm Sự Kiện Mùa Giải</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --event-primary: #f43f5e; /* Rose */
            --event-secondary: #fbbf24; /* Amber */
            --bg: #0f172a;
        }

        body {
            background: var(--bg);
            background-image: 
                radial-gradient(circle at 10% 10%, rgba(244, 63, 94, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 90% 90%, rgba(251, 191, 36, 0.1) 0%, transparent 40%);
            color: #fff;
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
        }

        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }

        /* ── Event Hero ── */
        .event-hero {
            background: linear-gradient(135deg, #f43f5e, #e11d48);
            border-radius: 30px;
            padding: 50px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(225, 29, 72, 0.3);
            margin-bottom: 40px;
        }

        .event-hero::before {
            content: '🧧';
            position: absolute;
            top: -20px;
            right: -20px;
            font-size: 200px;
            opacity: 0.1;
            transform: rotate(20deg);
        }

        .event-title { font-size: 48px; font-weight: 900; margin: 0; text-transform: uppercase; letter-spacing: 2px; }
        .event-timer { background: rgba(0,0,0,0.2); display: inline-block; padding: 10px 25px; border-radius: 50px; margin-top: 20px; font-weight: 700; }

        /* ── Currency Bar ── */
        .currency-bar {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 40px;
        }

        .currency-card {
            background: rgba(255,255,255,0.05);
            padding: 15px 30px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .currency-val { font-size: 24px; font-weight: 900; color: var(--event-secondary); }

        /* ── Tabs ── */
        .event-tabs {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .e-tab {
            background: rgba(255,255,255,0.05);
            border: none;
            color: #94a3b8;
            padding: 12px 30px;
            border-radius: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
        }

        .e-tab.active { background: var(--event-primary); color: #fff; box-shadow: 0 5px 15px rgba(244, 63, 94, 0.4); }

        /* ── Missions ── */
        .mission-card {
            background: rgba(255,255,255,0.03);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .progress-container { flex: 1; margin: 0 40px; }
        .progress-bar { height: 8px; background: rgba(255,255,255,0.1); border-radius: 10px; overflow: hidden; margin-top: 10px; }
        .progress-fill { height: 100%; background: linear-gradient(to right, #f43f5e, #fbbf24); transition: width 0.5s; }

        .btn-claim {
            background: var(--event-secondary);
            color: #000;
            border: none;
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 800;
            cursor: pointer;
        }

        .btn-claim:disabled { background: #334155; color: #94a3b8; cursor: not-allowed; }

        /* ── Shop ── */
        .shop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .exchange-card {
            background: rgba(255,255,255,0.05);
            border-radius: 24px;
            padding: 25px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .exchange-icon { font-size: 50px; margin-bottom: 15px; }

        .btn-exchange {
            width: 100%;
            background: #fff;
            color: #000;
            border: none;
            padding: 12px;
            border-radius: 15px;
            font-weight: 800;
            margin-top: 20px;
            cursor: pointer;
        }

        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 10px 20px;
            border-radius: 50px;
            text-decoration: none;
            backdrop-filter: blur(10px);
            z-index: 100;
        }

    </style>
</head>
<body>

    <a href="index.php" class="back-btn"><i class="fa fa-arrow-left"></i> Sảnh</a>

    <div class="container">
        <div class="event-hero" id="event-hero">
            <h1 class="event-title" id="event-name">SỰ KIỆN ĐANG TẢI...</h1>
            <div class="event-timer" id="event-timer">Kết thúc sau: -- ngày -- giờ</div>
        </div>

        <div class="currency-bar">
            <div class="currency-card">
                <span style="font-size: 30px;">🧧</span>
                <div>
                    <div class="currency-val" id="user-tokens">0</div>
                    <div style="font-size: 11px; opacity: 0.6; text-transform: uppercase;">Xu Sự Kiện</div>
                </div>
            </div>
            <div class="currency-card">
                <span style="font-size: 30px;">🏆</span>
                <div>
                    <div class="currency-val" id="user-points">0</div>
                    <div style="font-size: 11px; opacity: 0.6; text-transform: uppercase;">Điểm Vinh Danh</div>
                </div>
            </div>
        </div>

        <div class="event-tabs">
            <button class="e-tab active" onclick="switchTab('missions')">NHIỆM VỤ SỰ KIỆN</button>
            <button class="e-tab" onclick="switchTab('shop')">CỬA HÀNG ĐỔI QUÀ</button>
        </div>

        <div id="missions-section">
            <!-- Missions load here -->
        </div>

        <div id="shop-section" style="display: none;">
            <div class="shop-grid" id="shop-grid">
                <!-- Shop items load here -->
            </div>
        </div>
    </div>

    <script>
        let eventData = null;

        function loadEvent() {
            $.get('api_event_engine.php?action=get_event_data', function(res) {
                if (res.success) {
                    eventData = res;
                    $('#event-name').text(res.event.name);
                    $('#user-tokens').text(new Intl.NumberFormat().format(res.user_data.event_currency));
                    $('#user-points').text(new Intl.NumberFormat().format(res.user_data.points));

                    renderMissions(res.missions);
                    renderShop(res.shop_items);
                } else {
                    Swal.fire('Thông báo', res.message, 'info').then(() => { window.location.href = 'index.php'; });
                }
            });
        }

        function renderMissions(missions) {
            let html = '';
            missions.forEach(m => {
                const percent = Math.min(100, (m.current_value / m.target_value) * 100);
                const canClaim = m.is_completed && !m.is_claimed;
                
                html += `
                    <div class="mission-card">
                        <div style="width: 250px;">
                            <div style="font-weight: 800; font-size: 18px;">${m.title}</div>
                            <div style="color: var(--event-secondary); font-size: 14px; font-weight: 700;">Thưởng: ${m.reward_currency} Xu 🧧</div>
                        </div>
                        <div class="progress-container">
                            <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 800;">
                                <span>TIẾN TRÌNH</span>
                                <span>${new Intl.NumberFormat().format(m.current_value)} / ${new Intl.NumberFormat().format(m.target_value)}</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${percent}%"></div>
                            </div>
                        </div>
                        <button class="btn-claim" ${canClaim ? '' : 'disabled'} onclick="claimReward(${m.id})">
                            ${m.is_claimed ? 'ĐÃ NHẬN' : (m.is_completed ? 'NHẬN THƯỞNG' : 'CHƯA XONG')}
                        </button>
                    </div>
                `;
            });
            $('#missions-section').html(html);
        }

        function renderShop(items) {
            let html = '';
            items.forEach(item => {
                const isLimited = item.total_stock > 0 || item.limit_per_user == 1;
                html += `
                    <div class="exchange-card" style="position: relative;">
                        ${isLimited ? '<div style="position: absolute; top: 10px; left: 10px; background: #ef4444; color: white; padding: 2px 8px; border-radius: 5px; font-size: 10px; font-weight: 800;">LIMITED</div>' : ''}
                        <div class="exchange-icon">${item.item_type === 'title' ? '👑' : '🎁'}</div>
                        <h3 style="margin: 0; font-size: 20px;">${item.item_name}</h3>
                        <div style="color: var(--event-secondary); font-weight: 900; font-size: 24px; margin-top: 15px;">
                            ${new Intl.NumberFormat().format(item.cost_currency)} <span style="font-size: 14px;">XU</span>
                        </div>
                        <button class="btn-exchange" onclick="exchangeItem(${item.id})">ĐỔI QUÀ NGAY</button>
                        <div style="font-size: 12px; opacity: 0.5; margin-top: 10px;">Còn lại: ${item.total_stock === -1 ? 'Vô hạn' : item.total_stock} | Giới hạn: ${item.limit_per_user}</div>
                    </div>
                `;
            });
            $('#shop-grid').html(html);
        }

        function switchTab(tab) {
            $('.e-tab').removeClass('active');
            $(`button[onclick="switchTab('${tab}')"]`).addClass('active');
            if (tab === 'missions') {
                $('#missions-section').show();
                $('#shop-section').hide();
            } else {
                $('#missions-section').hide();
                $('#shop-section').show();
            }
        }

        function claimReward(id) {
            $.post('api_event_engine.php', { action: 'claim_reward', mission_id: id }, function(res) {
                if (res.success) {
                    Swal.fire('Thành công!', `Bạn nhận được ${res.reward} Xu Sự Kiện!`, 'success');
                    loadEvent();
                } else {
                    Swal.fire('Lỗi!', res.message, 'error');
                }
            });
        }

        function exchangeItem(id) {
            $.post('api_event_engine.php', { action: 'exchange_item', item_id: id }, function(res) {
                if (res.success) {
                    Swal.fire('Thành công!', res.message, 'success');
                    loadEvent();
                } else {
                    Swal.fire('Lỗi!', res.message, 'error');
                }
            });
        }

        $(document).ready(loadEvent);
    </script>
</body>
</html>
