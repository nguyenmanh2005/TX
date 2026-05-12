<?php
session_start();
require_once 'db_connect.php';
if (!isset($_SESSION['Iduser'])) { header("Location: login.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sàn Giao Dịch GTLM Premium</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/market_premium.css">
</head>
<body class="market-premium">

    <div class="market-container">
        <!-- 🎭 Header -->
        <div class="market-header">
            <div>
                <h1><i class="fa fa-shopping-bag"></i> Sàn Giao Dịch Premium</h1>
                <p style="color: var(--market-text-dim); margin: 5px 0 0 0; font-size: 14px;">Nơi mua bán vật phẩm hiếm giữa các cư dân và quân đoàn Bot</p>
            </div>
            
            <div class="market-nav">
                <div style="background: rgba(255,255,255,0.05); padding: 10px 20px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); margin-right: 15px; text-align: right;">
                    <div style="font-size: 10px; color: var(--market-text-dim); text-transform: uppercase;">Ngân khố của bạn</div>
                    <div style="font-size: 18px; font-weight: 800; color: var(--market-gold);" id="userBalance">-- GTLM</div>
                </div>
                <button class="market-btn market-btn-secondary" onclick="openPostModal()">
                    <i class="fa fa-plus"></i> Đăng bán
                </button>
                <a href="index.php" class="market-btn market-btn-primary">
                    <i class="fa fa-home"></i> Về sảnh
                </a>
            </div>
        </div>

        <!-- 🏷️ Filters (Coming soon) -->
        <div style="display: flex; gap: 10px; margin-bottom: 30px; overflow-x: auto; padding-bottom: 10px;">
            <button class="market-btn market-btn-secondary active" style="background: var(--market-primary); border: none;">Tất cả</button>
            <button class="market-btn market-btn-secondary">Danh hiệu (Titles)</button>
            <button class="market-btn market-btn-secondary">Khung Avatar</button>
            <button class="market-btn market-btn-secondary">Vật phẩm hiếm</button>
        </div>

        <!-- 📦 Listings -->
        <div class="listings-grid" id="marketListings">
            <!-- Listings load here via AJAX -->
        </div>
    </div>

    <!-- 🎒 Modal Đăng Bán -->
    <div id="postModal" style="display:none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; backdrop-filter: blur(15px); align-items:center; justify-content:center;">
        <div style="background: var(--market-bg); width: 500px; border-radius: 30px; border: 1px solid var(--market-primary); padding: 30px; position: relative; box-shadow: 0 0 50px rgba(99,102,241,0.3);">
            <button onclick="closeModal()" style="position: absolute; top: 20px; right: 20px; background: none; border: none; color: var(--market-text-dim); font-size: 24px; cursor: pointer;">&times;</button>
            <h2 style="margin-top: 0; color: var(--market-primary);">🎁 Đăng Bán Vật Phẩm</h2>
            
            <div id="itemSelector" style="margin-top: 20px;">
                <label style="display:block; font-size: 12px; color: var(--market-text-dim); margin-bottom: 10px;">Chọn vật phẩm trong kho của bạn</label>
                <div id="myItemsList" style="max-height: 200px; overflow-y: auto; background: rgba(0,0,0,0.2); border-radius: 15px; padding: 10px; border: 1px solid var(--glass-border);">
                    <!-- Items load here -->
                </div>
            </div>

            <div style="margin-top: 20px;">
                <label style="display:block; font-size: 12px; color: var(--market-text-dim); margin-bottom: 10px;">Giá bán (GTLM)</label>
                <input type="number" id="postPrice" placeholder="Nhập giá..." style="width: 100%; padding: 15px; border-radius: 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); color: white; outline: none;">
            </div>

            <button class="market-btn market-btn-primary" style="width: 100%; margin-top: 30px; justify-content: center;" onclick="submitPost()">
                XÁC NHẬN ĐĂNG BÁN
            </button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let selectedItem = null;

        function fetchBalance() {
            $.get('api_profile.php', function(res) {
                if (res.Money !== undefined) {
                    $('#userBalance').text(Number(res.Money).toLocaleString() + ' GTLM');
                }
            }, 'json');
        }

        function openPostModal() {
            $('#postModal').css('display', 'flex');
            $('#myItemsList').html('<div style="text-align:center; padding:20px;"><i class="fa fa-spinner fa-spin"></i> Đang tải kho đồ...</div>');
            
            $.get('api_marketplace.php', { action: 'get_my_items' }, function(res) {
                if (res.success) {
                    let html = '';
                    if (res.items.length === 0) {
                        html = '<div style="text-align:center; padding:20px; color:var(--market-text-dim);">Kho đồ trống!</div>';
                    } else {
                        res.items.forEach(i => {
                            html += `
                                <div class="selectable-item" onclick="selectItem(this, '${i.type}', ${i.id}, '${i.name}')" 
                                     style="padding: 12px; border-radius: 10px; cursor: pointer; margin-bottom: 5px; border: 1px solid transparent; transition: all 0.2s;">
                                    ${i.type === 'title' ? '🏆' : '🖼️'} ${i.name}
                                </div>
                            `;
                        });
                    }
                    $('#myItemsList').html(html);
                }
            }, 'json');
        }

        function selectItem(el, type, id, name) {
            $('.selectable-item').css({ 'background': 'transparent', 'border-color': 'transparent' });
            $(el).css({ 'background': 'rgba(99, 102, 241, 0.2)', 'border-color': 'var(--market-primary)' });
            selectedItem = { type, id, name };
        }

        function closeModal() {
            $('#postModal').hide();
        }

        function submitPost() {
            const price = $('#postPrice').val();
            if (!selectedItem) return Swal.fire('Lỗi', 'Vui lòng chọn vật phẩm!', 'error');
            if (!price || price <= 0) return Swal.fire('Lỗi', 'Vui lòng nhập giá hợp lệ!', 'error');

            $.post('api_marketplace.php', {
                action: 'list_item',
                item_id: selectedItem.id,
                item_type: selectedItem.type,
                item_name: selectedItem.name,
                price: price
            }, function(res) {
                if (res.success) {
                    Swal.fire('Thành công!', 'Vật phẩm đã được treo trên sàn.', 'success');
                    closeModal();
                    loadMarket();
                } else {
                    Swal.fire('Lỗi', res.message, 'error');
                }
            }, 'json');
        }

        function loadMarket() {
            $('#marketListings').html('<div style="grid-column: 1/-1; text-align: center; padding: 100px;"><i class="fa fa-spinner fa-spin fa-3x" style="color: var(--market-primary)"></i></div>');
            
            $.get('api_marketplace.php', { action: 'get_listings' }, function(res) {
                if (res.success) {
                    let html = '';
                    if (res.listings.length === 0) {
                        html = '<div style="grid-column: 1/-1; text-align: center; padding: 100px; color: var(--market-text-dim); background: var(--market-panel); border-radius: 30px; border: 1px dashed var(--glass-border);">Chợ đang trống. Hãy là người đầu tiên đăng bán!</div>';
                    } else {
                        res.listings.forEach(l => {
                            const badgeClass = l.item_type === 'title' ? 'badge-title' : (l.item_type === 'frame' ? 'badge-frame' : 'badge-item');
                            const icon = l.item_type === 'title' ? '🏆' : (l.item_type === 'frame' ? '🖼️' : '📦');
                            
                            html += `
                                <div class="item-card">
                                    <div class="item-type-badge ${badgeClass}">${icon} ${l.item_type}</div>
                                    <div class="item-name">${l.item_name}</div>
                                    <div class="item-price">
                                        ${Number(l.price).toLocaleString()} <small>GTLM</small>
                                    </div>
                                    <button class="market-btn market-btn-primary" style="width: 100%; justify-content: center;" onclick="buyItem(${l.id})">
                                        MUA NGAY
                                    </button>
                                    
                                    <div class="seller-info">
                                        <img src="https://ui-avatars.com/api/?name=${l.seller_name}&background=random" class="seller-avatar">
                                        <div class="seller-details">
                                            <span class="seller-name">${l.seller_name}</span>
                                            <span class="sale-date">${l.created_at}</span>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                    }
                    $('#marketListings').html(html);
                }
            }, 'json');
        }

        function buyItem(id) {
            Swal.fire({
                title: 'Xác nhận mua?',
                text: "Tiền sẽ được trừ ngay lập tức vào tài khoản.",
                icon: 'question',
                background: '#0f172a',
                color: '#fff',
                showCancelButton: true,
                confirmButtonColor: '#6366f1',
                cancelButtonColor: '#334155',
                confirmButtonText: 'Mua ngay',
                cancelButtonText: 'Để sau'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('api_marketplace.php', { action: 'buy', id: id }, function(res) {
                        if (res.success) {
                            Swal.fire({
                                title: 'Thành công!',
                                text: 'Bạn đã sở hữu vật phẩm này.',
                                icon: 'success',
                                background: '#0f172a',
                                color: '#fff'
                            });
                            loadMarket();
                            fetchBalance();
                        } else {
                            Swal.fire({
                                title: 'Lỗi!',
                                text: res.message,
                                icon: 'error',
                                background: '#0f172a',
                                color: '#fff'
                            });
                        }
                    }, 'json');
                }
            });
        }

        $(document).ready(function() {
            loadMarket();
            fetchBalance();
        });
    </script>
</body>
</html>