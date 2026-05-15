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
    <title>Sàn Đấu Giá Vật Phẩm</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #6366f1;
            --secondary: #a855f7;
            --accent: #f59e0b;
            --bg-glass: rgba(255, 255, 255, 0.1);
            --border-glass: rgba(255, 255, 255, 0.2);
        }

        body {
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            color: #fff;
            font-family: 'Inter', sans-serif;
            padding-bottom: 50px;
        }

        .auction-header {
            text-align: center;
            padding: 40px 0;
            background: rgba(0,0,0,0.2);
            backdrop-filter: blur(10px);
            margin-bottom: 40px;
            border-bottom: 1px solid var(--border-glass);
        }

        .auction-title {
            font-size: 42px;
            font-weight: 900;
            background: linear-gradient(to right, #fff, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .auction-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
            flex-wrap: wrap;
        }

        .btn-create {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            border: none;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-create:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(99, 102, 241, 0.5);
        }

        .auction-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
        }

        .auction-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(15px);
            border: 1px solid var(--border-glass);
            border-radius: 24px;
            padding: 25px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .auction-card:hover {
            transform: translateY(-10px);
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255,255,255,0.4);
        }

        .item-preview {
            width: 100%;
            height: 180px;
            background: rgba(0,0,0,0.2);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 80px;
            margin-bottom: 20px;
            position: relative;
            box-shadow: inset 0 0 20px rgba(0,0,0,0.3);
        }

        .auction-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--accent);
            color: #000;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .item-name {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 15px;
            color: #fff;
        }

        .price-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .price-label { font-size: 13px; color: #aaa; }
        .price-value { font-size: 16px; font-weight: 700; color: var(--accent); }

        .time-left {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #ef4444;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .btn-bid {
            width: 100%;
            padding: 12px;
            border-radius: 14px;
            border: 1px solid var(--border-glass);
            background: rgba(255,255,255,0.1);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-bid:hover {
            background: white;
            color: #000;
        }

        .seller-info {
            margin-top: 15px;
            font-size: 12px;
            color: #888;
            display: flex;
            justify-content: space-between;
        }

        /* ── Modal Styles ── */
        .swal2-popup {
            background: #1e1b4b !important;
            color: #fff !important;
            border-radius: 24px !important;
        }

        .item-selector {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 20px;
            padding: 10px;
        }

        .select-item-card {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 10px;
            cursor: pointer;
            border: 2px solid transparent;
            text-align: center;
        }

        .select-item-card.active {
            border-color: var(--accent);
            background: rgba(245, 158, 11, 0.1);
        }

        .select-item-card img, .select-item-card .icon {
            width: 50px;
            height: 50px;
            margin-bottom: 5px;
        }

        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            color: white;
            text-decoration: none;
            background: rgba(255,255,255,0.1);
            padding: 10px 20px;
            border-radius: 50px;
            backdrop-filter: blur(10px);
            z-index: 100;
        }
    </style>
</head>
<body>
    <a href="index.php" class="back-btn"><i class="fa fa-arrow-left"></i> Sảnh</a>

    <header class="auction-header">
        <h1 class="auction-title">Sàn Đấu Giá</h1>
        <p style="opacity: 0.7;">Săn lùng những vật phẩm hiếm có và độc nhất</p>
    </header>

    <div class="container">
        <div class="auction-controls">
            <div class="tabs">
                <!-- Tabs could go here -->
            </div>
            <button class="btn-create" onclick="openCreateModal()">
                <i class="fa fa-plus"></i> Tạo Đấu Giá Mới
            </button>
        </div>

        <div id="auction-grid" class="auction-grid">
            <!-- Auctions load here -->
        </div>
    </div>

    <script>
        function loadAuctions() {
            $.get('api_auction.php?action=get_list', function(res) {
                if (res.success) {
                    let html = '';
                    if (res.list.length === 0) {
                        html = '<div style="grid-column: 1/-1; text-align: center; padding: 100px; opacity: 0.5;">Hiện chưa có vật phẩm nào đang đấu giá.</div>';
                    } else {
                        res.list.forEach(item => {
                            const timeStr = getTimeRemaining(item.ends_at);
                            html += `
                                <div class="auction-card">
                                    <div class="auction-badge">${item.item_type}</div>
                                    <div class="item-preview">${item.item_icon}</div>
                                    <div class="item-name">${item.item_name}</div>
                                    
                                    <div class="price-info">
                                        <div class="price-label">Giá hiện tại</div>
                                        <div class="price-value">${new Intl.NumberFormat().format(item.current_price)} gtlm</div>
                                    </div>
                                    
                                    ${item.buyout_price ? `
                                    <div class="price-info">
                                        <div class="price-label">Giá mua đứt</div>
                                        <div class="price-value" style="color: #10b981;">${new Intl.NumberFormat().format(item.buyout_price)} gtlm</div>
                                    </div>
                                    ` : ''}

                                    <div class="time-left">
                                        <i class="fa fa-clock"></i> <span>${timeStr}</span>
                                    </div>

                                    <button class="btn-bid" onclick="bidModal(${item.id}, ${item.current_price}, ${item.min_increment}, ${item.buyout_price})">
                                        Đặt Giá / Mua Đứt
                                    </button>

                                    <div class="seller-info">
                                        <span>Bởi: ${item.seller_name}</span>
                                        <span>ID: #${item.id}</span>
                                    </div>
                                </div>
                            `;
                        });
                    }
                    $('#auction-grid').html(html);
                }
            });
        }

        function getTimeRemaining(endTime) {
            const end = new Date(endTime).getTime();
            const now = new Date().getTime();
            const diff = end - now;
            if (diff <= 0) return "Đã kết thúc";
            
            const h = Math.floor(diff / 3600000);
            const m = Math.floor((diff % 3600000) / 60000);
            const s = Math.floor((diff % 60000) / 1000);
            
            return `${h}h ${m}m ${s}s`;
        }

        function bidModal(id, currentPrice, minInc, buyout) {
            const minBid = currentPrice + minInc;
            Swal.fire({
                title: 'Đấu Giá Vật Phẩm',
                html: `
                    <p style="font-size: 14px; opacity: 0.8; margin-bottom: 20px;">
                        Giá hiện tại: <b>${new Intl.NumberFormat().format(currentPrice)}</b> gtlm<br>
                        Mức giá đặt tối thiểu: <b>${new Intl.NumberFormat().format(minBid)}</b> gtlm
                    </p>
                    <input type="number" id="bid_amount" class="swal2-input" placeholder="Nhập số  Gtlm..." value="${minBid}">
                    ${buyout ? `
                        <div style="margin-top: 15px; padding: 15px; background: rgba(16, 185, 129, 0.1); border-radius: 12px; border: 1px solid #10b981;">
                            <span style="font-size: 13px;">Giá mua đứt:</span><br>
                            <b style="color: #10b981; font-size: 20px;">${new Intl.NumberFormat().format(buyout)} gtlm</b><br>
                            <button onclick="buyoutAction(${id}, ${buyout})" class="btn-create" style="margin: 10px auto 0; padding: 8px 20px; background: #10b981;">Mua Đứt Ngay</button>
                        </div>
                    ` : ''}
                `,
                showCancelButton: true,
                confirmButtonText: 'Đặt Giá',
                cancelButtonText: 'Hủy',
                preConfirm: () => {
                    const amount = $('#bid_amount').val();
                    if (!amount || amount < minBid) {
                        Swal.showValidationMessage(`Giá đặt tối thiểu là ${minBid}`);
                    }
                    return amount;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    placeBid(id, result.value);
                }
            });
        }

        function placeBid(id, amount) {
            $.post('api_auction.php', { action: 'bid', auction_id: id, amount: amount }, function(res) {
                if (res.success) {
                    Swal.fire('Thành công!', res.message, 'success');
                    loadAuctions();
                } else {
                    Swal.fire('Lỗi!', res.message, 'error');
                }
            });
        }

        function buyoutAction(id, amount) {
            placeBid(id, amount);
        }

        let selectedItemType = 'avatar_frame';
        let selectedItemId = null;

        function openCreateModal() {
            Swal.fire({
                title: 'Tạo Đấu Giá Mới',
                width: '800px',
                html: `
                    <div style="text-align: left;">
                        <label>1. Chọn loại vật phẩm:</label>
                        <select id="create_type" class="swal2-select" onchange="loadUserItems(this.value)">
                            <option value="avatar_frame">Khung ảnh đại diện</option>
                            <option value="theme">Giao diện (Theme)</option>
                            <option value="cursor">Con trỏ chuột</option>
                            <option value="chat_frame">Khung chat</option>
                            <option value="title">Danh hiệu</option>
                        </select>

                        <label>2. Chọn vật phẩm:</label>
                        <div id="user_items_list" class="item-selector">
                            <p style="grid-column: 1/-1; text-align: center;">Đang tải vật phẩm...</p>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                            <div>
                                <label>3. Giá khởi điểm:</label>
                                <input type="number" id="create_start_price" class="swal2-input" placeholder="Ví dụ: 10000">
                            </div>
                            <div>
                                <label>4. Giá mua đứt (Tùy chọn):</label>
                                <input type="number" id="create_buyout_price" class="swal2-input" placeholder="Ví dụ: 100000">
                            </div>
                        </div>

                        <label>5. Thời gian đấu giá:</label>
                        <select id="create_duration" class="swal2-select">
                            <option value="1">1 Giờ</option>
                            <option value="3">3 Giờ</option>
                            <option value="6">6 Giờ</option>
                            <option value="12">12 Giờ</option>
                            <option value="24" selected>24 Giờ (1 Ngày)</option>
                            <option value="48">48 Giờ (2 Ngày)</option>
                        </select>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Đưa Lên Sàn',
                didOpen: () => {
                    loadUserItems('avatar_frame');
                },
                preConfirm: () => {
                    if (!selectedItemId) {
                        Swal.showValidationMessage('Vui lòng chọn vật phẩm!');
                        return false;
                    }
                    const startPrice = $('#create_start_price').val();
                    if (!startPrice || startPrice < 1000) {
                        Swal.showValidationMessage('Giá khởi điểm tối thiểu 1,000!');
                        return false;
                    }
                    return {
                        item_type: $('#create_type').val(),
                        item_id: selectedItemId,
                        start_price: startPrice,
                        buyout_price: $('#create_buyout_price').val(),
                        duration_hours: $('#create_duration').val()
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('api_auction.php', { action: 'create', ...result.value }, function(res) {
                        if (res.success) {
                            Swal.fire('Thành công!', res.message, 'success');
                            loadAuctions();
                        } else {
                            Swal.fire('Lỗi!', res.message, 'error');
                        }
                    });
                }
            });
        }

        function loadUserItems(type) {
            selectedItemId = null;
            $('#user_items_list').html('<p style="grid-column: 1/-1; text-align: center;">Đang tải vật phẩm...</p>');
            $.get('api_auction.php?action=get_my_items&type=' + type, function(res) {
                if (res.success) {
                    let html = '';
                    if (res.items.length === 0) {
                        html = '<p style="grid-column: 1/-1; text-align: center; color: #ff4d4d;">Bạn không có vật phẩm nào khả dụng loại này!</p>';
                    } else {
                        res.items.forEach(item => {
                            const id = type === 'title' ? item.achievement_id : (type === 'chat_frame' ? item.chat_frame_id : item[type + '_id']);
                            html += `
                                <div class="select-item-card" onclick="selectItem(this, ${id})">
                                    <div class="icon" style="font-size: 24px;">${item.icon}</div>
                                    <div style="font-size: 10px; margin-top: 5px;">${item.name}</div>
                                </div>
                            `;
                        });
                    }
                    $('#user_items_list').html(html);
                }
            });
        }

        function selectItem(el, id) {
            $('.select-item-card').removeClass('active');
            $(el).addClass('active');
            selectedItemId = id;
        }

        $(document).ready(() => {
            loadAuctions();
            setInterval(loadAuctions, 10000); // Tự động cập nhật mỗi 10 giây
        });
    </script>
</body>
</html>
