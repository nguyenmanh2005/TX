<?php
session_start();
require 'db_connect.php';
if (!isset($_SESSION['Iduser'])) { header("Location: login.php"); exit(); }
require_once 'load_theme.php';
$userId = $_SESSION['Iduser'];
$user = $conn->query("SELECT * FROM users WHERE Iduser = $userId")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gifting Premium - Trao Gửi Yêu Thương</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #6366f1;
            --accent: #ec4899;
            --glass: rgba(255, 255, 255, 0.9);
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-attachment: fixed;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            padding: 40px 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: var(--glass);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        h1 { text-align: center; font-size: 40px; font-weight: 900; margin-bottom: 30px; background: linear-gradient(to right, #6366f1, #ec4899); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        .money-card {
            background: #fff;
            padding: 20px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            font-size: 24px;
            font-weight: 800;
            color: #059669;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
        }

        .tabs { display: flex; gap: 10px; margin-bottom: 30px; }
        .tab { flex: 1; padding: 15px; border-radius: 15px; border: 2px solid transparent; background: #f1f5f9; cursor: pointer; font-weight: 700; transition: 0.3s; }
        .tab.active { background: #fff; border-color: var(--primary); color: var(--primary); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }

        .form-section { display: none; }
        .form-section.active { display: block; animation: fadeIn 0.4s ease; }

        .form-group { margin-bottom: 25px; }
        label { display: block; font-weight: 700; margin-bottom: 10px; color: #475569; }
        input, select, textarea { width: 100%; padding: 15px; border-radius: 12px; border: 2px solid #e2e8f0; outline: none; transition: 0.3s; }
        input:focus { border-color: var(--primary); }

        /* ── Gift Wrapping ── */
        .wrap-options { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .wrap-card {
            background: #fff;
            border: 2px solid #e2e8f0;
            padding: 15px;
            border-radius: 15px;
            text-align: center;
            cursor: pointer;
            transition: 0.3s;
        }
        .wrap-card.selected { border-color: var(--accent); background: #fdf2f8; }
        .wrap-card i { font-size: 30px; color: var(--accent); margin-bottom: 10px; display: block; }
        .wrap-name { font-size: 14px; font-weight: 700; }

        .anonymous-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8fafc;
            padding: 15px;
            border-radius: 12px;
            cursor: pointer;
        }

        .btn-send {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            padding: 18px;
            border-radius: 15px;
            border: none;
            font-size: 20px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);
            transition: 0.3s;
        }
        .btn-send:hover { transform: translateY(-3px); box-shadow: 0 20px 25px -5px rgba(99, 102, 241, 0.4); }

        /* ── Gift Box Animation ── */
        .gift-box-anim {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            display: none;
            align-items: center; justify-content: center;
            flex-direction: column;
        }
        .box-img { font-size: 150px; animation: wobble 1s infinite; cursor: pointer; }
        @keyframes wobble {
            0%, 100% { transform: rotate(0deg) scale(1); }
            25% { transform: rotate(-10deg) scale(1.1); }
            75% { transform: rotate(10deg) scale(1.1); }
        }
        .confetti { position: absolute; }

        .history-card { background: #fff; padding: 20px; border-radius: 15px; margin-bottom: 15px; display: flex; align-items: center; gap: 15px; border: 1px solid #e2e8f0; }
        .history-icon { width: 50px; height: 50px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; }

        .user-list {
            position: absolute; width: 100%; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; max-height: 200px; overflow-y: auto; z-index: 50; display: none;
        }
        .user-item { padding: 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9; }
        .user-item:hover { background: #f8fafc; }

    </style>
</head>
<body>

    <div class="container">
        <h1>🎁 Gifting Premium</h1>
        
        <div class="money-card">
            <i class="fa fa-wallet"></i>
            <span><?= number_format($user['Money']) ?> GTLM</span>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="switchTab('send')">Tặng Quà</button>
            <button class="tab" onclick="switchTab('history')">Lịch Sử</button>
        </div>

        <div id="send-tab" class="form-section active">
            <div class="form-group" style="position: relative;">
                <label>👤 Người nhận</label>
                <input type="text" id="user-search" placeholder="Tìm tên người chơi..." oninput="searchUser()">
                <div id="user-list" class="user-list"></div>
                <input type="hidden" id="to-user-id">
            </div>

            <div class="tabs">
                <button class="tab active" onclick="switchGiftType('money')">Gửi  Gtlm</button>
                <button class="tab" onclick="switchGiftType('item')">Gửi Vật Phẩm</button>
            </div>

            <div id="money-form" class="gift-type-section">
                <div class="form-group">
                    <label>💰 Số lượng GTLM</label>
                    <input type="number" id="gift-amount" placeholder="Nhập số  Gtlm...">
                </div>
            </div>

            <div id="item-form" class="gift-type-section" style="display: none;">
                <div class="form-group">
                    <label>Loại vật phẩm</label>
                    <select id="item-type" onchange="loadMyItems()">
                        <option value="">-- Chọn loại --</option>
                        <option value="avatar_frame">Khung Ảnh</option>
                        <option value="theme">Giao diện</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Chọn vật phẩm</label>
                    <select id="item-id">
                        <option value="">-- Chọn vật phẩm --</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>🎁 Chọn hộp quà</label>
                <div class="wrap-options">
                    <div class="wrap-card selected" onclick="selectWrap('standard')" data-wrap="standard">
                        <i class="fa fa-box"></i>
                        <div class="wrap-name">Thường</div>
                    </div>
                    <div class="wrap-card" onclick="selectWrap('vip')" data-wrap="vip">
                        <i class="fa fa-gem"></i>
                        <div class="wrap-name">Hộp VIP</div>
                    </div>
                    <div class="wrap-card" onclick="selectWrap('tet')" data-wrap="tet">
                        <i class="fa fa-envelope"></i>
                        <div class="wrap-name">Lì Xì Tết</div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>💬 Lời nhắn</label>
                <textarea id="gift-message" placeholder="Chúc bạn chơi game vui vẻ!"></textarea>
            </div>

            <div class="form-group">
                <label class="anonymous-toggle" onclick="toggleAnon()">
                    <input type="checkbox" id="is-anonymous">
                    <span>Tặng ẩn danh (Người nhận không thấy tên bạn)</span>
                </label>
            </div>

            <button class="btn-send" onclick="sendGift()">GỬI QUÀ NGAY</button>
        </div>

        <div id="history-tab" class="form-section">
            <div id="history-list"></div>
        </div>
    </div>

    <!-- Animation Overlay -->
    <div class="gift-box-anim" id="gift-anim" onclick="openBox()">
        <div class="box-img" id="anim-box-icon">🎁</div>
        <div style="margin-top: 20px; font-size: 24px; font-weight: 800;" id="anim-text">Bạn nhận được một món quà!</div>
        <p style="opacity: 0.7;">Chạm vào hộp để mở</p>
    </div>

    <script>
        let currentGiftType = 'money';
        let selectedWrap = 'standard';

        function switchTab(tab) {
            $('.tab').removeClass('active');
            $(`button[onclick="switchTab('${tab}')"]`).addClass('active');
            $('.form-section').removeClass('active');
            $(`#${tab}-tab`).addClass('active');
            if(tab === 'history') loadHistory();
        }

        function switchGiftType(type) {
            currentGiftType = type;
            $('.gift-type-section').hide();
            $(`#${type}-form`).show();
            $('#send-tab .tab').removeClass('active');
            $(`button[onclick="switchGiftType('${type}')"]`).addClass('active');
        }

        function selectWrap(wrap) {
            selectedWrap = wrap;
            $('.wrap-card').removeClass('selected');
            $(`.wrap-card[data-wrap="${wrap}"]`).addClass('selected');
        }

        function searchUser() {
            const q = $('#user-search').val();
            if(q.length < 2) { $('#user-list').hide(); return; }
            $.get('api_gift.php?action=get_users&search=' + q, function(res) {
                if(res.success) {
                    let html = res.users.map(u => `<div class="user-item" onclick="selectUser(${u.id}, '${u.name}')">${u.name}</div>`).join('');
                    $('#user-list').html(html).show();
                }
            });
        }

        function selectUser(id, name) {
            $('#to-user-id').val(id);
            $('#user-search').val(name);
            $('#user-list').hide();
        }

        function loadMyItems() {
            const type = $('#item-type').val();
            if(!type) return;
            $.get('api_gift.php?action=get_user_items&item_type=' + type, function(res) {
                if(res.success) {
                    let html = res.items.map(i => `<option value="${i.id}">${i.name}</option>`).join('');
                    $('#item-id').html(html);
                }
            });
        }

        function sendGift() {
            const data = {
                action: currentGiftType === 'money' ? 'send_money' : 'send_item',
                to_user_id: $('#to-user-id').val(),
                amount: $('#gift-amount').val(),
                item_type: $('#item-type').val(),
                item_id: $('#item-id').val(),
                message: $('#gift-message').val(),
                gift_wrap: selectedWrap,
                is_anonymous: $('#is-anonymous').is(':checked') ? 1 : 0
            };

            $.post('api_gift.php', data, function(res) {
                if(res.success) {
                    Swal.fire('Thành công', 'Quà đã được gửi đi!', 'success');
                } else {
                    Swal.fire('Lỗi', res.message, 'error');
                }
            });
        }

        function loadHistory() {
            $.get('api_gift.php?action=get_history', function(res) {
                if(res.success) {
                    let html = res.history.map(h => `
                        <div class="history-card">
                            <div class="history-icon">${h.gift_type === 'money' ? '💰' : '🎁'}</div>
                            <div>
                                <div style="font-weight: 800;">${h.from_user_name} ➔ ${h.to_user_name}</div>
                                <div style="font-size: 13px; color: #64748b;">${h.gift_type === 'money' ? h.gift_value + ' GTLM' : 'Vật phẩm'}</div>
                                <div style="font-style: italic; font-size: 12px;">"${h.message || '...'}"</div>
                            </div>
                        </div>
                    `).join('');
                    $('#history-list').html(html);
                }
            });
        }
    </script>
</body>
</html>