<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['Iduser'];
require_once 'load_theme.php';

if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

// Kiểm tra bảng tồn tại
$checkTable = $conn->query("SHOW TABLES LIKE 'marketplace_items'");
$tableExists = $checkTable && $checkTable->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛒 Chợ Trao Đổi</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        * {
            cursor: inherit;
        }

        button,
        a,
        input[type="button"],
        input[type="submit"],
        label,
        select,
        textarea {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
        }

        .header h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 36px;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 15px;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
            flex-wrap: wrap;
        }

        .tab {
            padding: 12px 24px;
            background: transparent;
            border: none;
            font-weight: 600;
            font-size: 15px;
            color: #666;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab:hover {
            color: #667eea;
        }

        .card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .listings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .listing-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
            border-radius: 15px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            cursor: pointer;
        }

        .listing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            border-color: #667eea;
        }

        .listing-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .item-type-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            background: #667eea;
            color: white;
        }

        .wishlist-btn {
            background: transparent;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #999;
            transition: color 0.3s ease;
        }

        .wishlist-btn.active {
            color: #dc3545;
        }

        .listing-price {
            font-size: 24px;
            font-weight: 800;
            color: #667eea;
            margin: 15px 0;
        }

        .listing-item-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .listing-description {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .listing-seller {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 14px;
            color: #666;
        }

        .seller-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
        }

        .listing-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
            flex: 1;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🛒 Chợ Trao Đổi</h1>
            <p style="color: #666; margin-top: 10px;">Mua bán và trao đổi items với người chơi khác</p>
        </div>

        <?php if (!$tableExists): ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h2>Hệ thống Marketplace chưa được kích hoạt!</h2>
                    <p>Vui lòng chạy file <code>ALL_DATABASE_TABLES.sql</code> trong phpMyAdmin trước.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('browse')">🛍️ Duyệt</button>
                    <button class="tab" onclick="switchTab('sell')">💰 Bán</button>
                    <button class="tab" onclick="switchTab('my_listings')">📋 Đang Bán</button>
                    <button class="tab" onclick="switchTab('wishlist')">❤️ Yêu Thích</button>
                    <button class="tab" onclick="switchTab('history')">📜 Lịch Sử</button>
                </div>

                <div id="tab-content">
                    <!-- Content sẽ được load động -->
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal bán item -->
    <div class="modal" id="sell-modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">💰 Đăng Bán Item</h2>
            <form id="sell-form" onsubmit="submitSell(event)">
                <div class="form-group">
                    <label>Loại Item</label>
                    <select name="item_type" id="sell-item-type" onchange="loadMyItems()" required>
                        <option value="">Chọn loại...</option>
                        <option value="theme">Theme</option>
                        <option value="cursor">Cursor</option>
                        <option value="chat_frame">Khung Chat</option>
                        <option value="avatar_frame">Khung Avatar</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Item</label>
                    <select name="item_id" id="sell-item-id" required>
                        <option value="">Chọn item...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Giá (gtlm)</label>
                    <input type="number" name="price" min="1" max="100000000" required>
                </div>
                <div class="form-group">
                    <label>Mô Tả (Tùy chọn)</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Hết Hạn Sau (Ngày, 0 = không hết hạn)</label>
                    <input type="number" name="expires_days" min="0" max="30" value="7">
                </div>
                <button type="submit" class="btn btn-primary">Đăng Bán</button>
                <button type="button" class="btn" onclick="closeModal('sell-modal')"
                    style="background: #999; margin-top: 10px;">Hủy</button>
            </form>
        </div>
    </div>

    <script>
        let currentTab = 'browse';
        let currentListing = null;

        function switchTab(tab) {
            currentTab = tab;
            $('.tab').removeClass('active');
            $(`.tab:contains(${getTabName(tab)})`).addClass('active');
            loadTabContent();
        }

        function getTabName(tab) {
            const names = {
                'browse': 'Duyệt',
                'sell': 'Bán',
                'my_listings': 'Đang Bán',
                'wishlist': 'Yêu Thích',
                'history': 'Lịch Sử'
            };
            return names[tab] || tab;
        }

        function loadTabContent() {
            const content = $('#tab-content');

            switch (currentTab) {
                case 'browse':
                    loadBrowseTab();
                    break;
                case 'sell':
                    loadSellTab();
                    break;
                case 'my_listings':
                    loadMyListingsTab();
                    break;
                case 'wishlist':
                    loadWishlistTab();
                    break;
                case 'history':
                    loadHistoryTab();
                    break;
            }
        }

        function loadBrowseTab() {
            const content = $('#tab-content');
            content.html(`
                <div class="filters">
                    <div class="filter-group">
                        <label>🔍 Tìm Kiếm</label>
                        <input type="text" id="filter-search" placeholder="Tìm theo tên hoặc mô tả..." onkeyup="applyFilters()">
                    </div>
                    <div class="filter-group">
                        <label>Loại Item</label>
                        <select id="filter-type" onchange="applyFilters()">
                            <option value="">Tất Cả</option>
                            <option value="theme">Theme</option>
                            <option value="cursor">Cursor</option>
                            <option value="chat_frame">Khung Chat</option>
                            <option value="avatar_frame">Khung Avatar</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Độ Hiếm</label>
                        <select id="filter-rarity" onchange="applyFilters()">
                            <option value="">Tất Cả</option>
                            <option value="common">Common</option>
                            <option value="rare">Rare</option>
                            <option value="epic">Epic</option>
                            <option value="legendary">Legendary</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Giá Từ</label>
                        <input type="number" id="filter-min-price" placeholder="Min" min="0" onchange="applyFilters()">
                    </div>
                    <div class="filter-group">
                        <label>Giá Đến</label>
                        <input type="number" id="filter-max-price" placeholder="Max" min="0" onchange="applyFilters()">
                    </div>
                    <div class="filter-group">
                        <label>Sắp Xếp</label>
                        <select id="filter-sort" onchange="applyFilters()">
                            <option value="created_at">Mới Nhất</option>
                            <option value="price">Giá: Thấp → Cao</option>
                            <option value="price">Giá: Cao → Thấp</option>
                            <option value="views">Nhiều Lượt Xem</option>
                        </select>
                    </div>
                </div>
                <div id="listings-grid" class="listings-grid">
                    <div class="empty-state">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Đang tải...</p>
                    </div>
                </div>
            `);
            loadListings();
        }

        function loadListings() {
            const type = $('#filter-type').val() || '';
            const sortBy = $('#filter-sort').val() || 'created_at';
            const search = $('#filter-search').val() || '';
            const rarity = $('#filter-rarity').val() || '';
            const minPrice = $('#filter-min-price').val() || '';
            const maxPrice = $('#filter-max-price').val() || '';

            let url = 'api_marketplace.php?action=get_listings';
            url += '&item_type=' + encodeURIComponent(type);
            url += '&sort_by=' + encodeURIComponent(sortBy);
            if (search) url += '&search=' + encodeURIComponent(search);
            if (rarity) url += '&rarity=' + encodeURIComponent(rarity);
            if (minPrice) url += '&min_price=' + encodeURIComponent(minPrice);
            if (maxPrice) url += '&max_price=' + encodeURIComponent(maxPrice);

            $.get(url, function (response) {
                if (response.success) {
                    displayListings(response.listings);
                }
            });
        }

        function applyFilters() {
            loadListings();
        }

        function displayListings(listings) {
            const grid = $('#listings-grid');

            if (listings.length === 0) {
                grid.html('<div class="empty-state"><i class="fas fa-box-open"></i><h2>Không có item nào</h2></div>');
                return;
            }

            let html = '';
            listings.forEach(listing => {
                const typeNames = {
                    'theme': 'Theme',
                    'cursor': 'Cursor',
                    'chat_frame': 'Khung Chat',
                    'avatar_frame': 'Khung Avatar'
                };

                html += `
                    <div class="listing-card" onclick="viewListing(${listing.id})">
                        <div class="listing-header">
                            <span class="item-type-badge">${typeNames[listing.item_type] || listing.item_type}</span>
                            <button class="wishlist-btn ${listing.in_wishlist ? 'active' : ''}" onclick="event.stopPropagation(); toggleWishlist(${listing.id})">
                                <i class="fas fa-heart"></i>
                            </button>
                        </div>
                        <div class="listing-item-name">${listing.item_name || 'Unknown'}</div>
                        <div class="listing-price">${number_format(listing.price)} gtlm</div>
                        ${listing.description ? '<div class="listing-description">' + listing.description.substring(0, 50) + '...</div>' : ''}
                        <div class="listing-seller">
                            <img src="${listing.seller_avatar || 'default-avatar.png'}" alt="${listing.seller_name}" class="seller-avatar" onerror="this.src='default-avatar.png'">
                            <span>${listing.seller_name}</span>
                        </div>
                        <div class="listing-actions">
                            <button class="btn btn-primary" onclick="event.stopPropagation(); buyItem(${listing.id})">
                                <i class="fas fa-shopping-cart"></i> Mua Ngay
                            </button>
                        </div>
                    </div>
                `;
            });

            grid.html(html);
        }

        function viewListing(listingId) {
            $.get('api_marketplace.php?action=get_listing&listing_id=' + listingId, function (response) {
                if (response.success) {
                    currentListing = response.listing;

                    Swal.fire({
                        title: response.listing.item_name,
                        html: `
                            <p><strong>Loại:</strong> ${response.listing.item_type}</p>
                            <p><strong>Giá:</strong> ${number_format(response.listing.price)} gtlm</p>
                            ${response.listing.description ? '<p><strong>Mô tả:</strong> ' + response.listing.description + '</p>' : ''}
                            <p><strong>Người bán:</strong> ${response.listing.seller_name}</p>
                            <p><strong>Lượt xem:</strong> ${response.listing.views}</p>
                            <div id="recommendations-container" style="margin-top: 20px;"></div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Mua Ngay',
                        cancelButtonText: 'Đóng',
                        width: '600px',
                        didOpen: () => {
                            // Load recommendations after modal opens
                            $.get('api_marketplace.php?action=get_recommendations&item_type=' + encodeURIComponent(response.listing.item_type) + '&item_id=' + response.listing.item_id, function (recResponse) {
                                if (recResponse.success && recResponse.recommendations.length > 0) {
                                    const recHtml = '<div style="padding-top: 20px; border-top: 2px solid #e0e0e0;"><h4 style="margin-bottom: 10px;">💡 Người mua item này cũng mua:</h4><div style="display: flex; flex-direction: column; gap: 10px;">' +
                                        recResponse.recommendations.map(rec =>
                                            `<div style="padding: 10px; background: #f5f5f5; border-radius: 8px; cursor: pointer;" onclick="Swal.close(); setTimeout(() => viewListing(${rec.listing.id}), 300);">
                                                <strong>${rec.item_name}</strong> - ${number_format(rec.listing.price)} gtlm
                                                <small style="color: #666;"> (${rec.purchase_count} người đã mua)</small>
                                            </div>`
                                        ).join('') + '</div></div>';
                                    $('#recommendations-container').html(recHtml);
                                }
                            });
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            Swal.fire({
                                title: 'Xác nhận',
                                text: 'Bạn có chắc muốn mua item này?',
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonText: 'Mua',
                                cancelButtonText: 'Hủy'
                            }).then((buyResult) => {
                                if (buyResult.isConfirmed) {
                                    $.post('api_marketplace.php', {
                                        action: 'buy_item',
                                        listing_id: listingId
                                    }, function (response) {
                                        if (response.success) {
                                            Swal.fire('Thành công', 'Đã mua item thành công!', 'success');
                                            loadTabContent();
                                        } else {
                                            Swal.fire('Lỗi', response.message, 'error');
                                        }
                                    });
                                }
                            });
                        }
                    });
                }
            });
        }

        function buyItem(listingId) {
            Swal.fire({
                title: 'Xác Nhận Mua',
                text: 'Bạn có chắc muốn mua item này?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Mua',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('api_marketplace.php', {
                        action: 'buy_item',
                        listing_id: listingId
                    }, function (response) {
                        if (response.success) {
                            Swal.fire('Thành công', 'Đã mua item thành công!', 'success');
                            loadTabContent();
                        } else {
                            Swal.fire('Lỗi', response.message, 'error');
                        }
                    });
                }
            });
        }

        function toggleWishlist(listingId) {
            $.get('api_marketplace.php?action=get_listing&listing_id=' + listingId, function (response) {
                if (response.success) {
                    const inWishlist = response.listing.in_wishlist;
                    const action = inWishlist ? 'remove_from_wishlist' : 'add_to_wishlist';

                    $.post('api_marketplace.php', {
                        action: action,
                        listing_id: listingId
                    }, function (response) {
                        if (response.success) {
                            loadTabContent();
                        }
                    });
                }
            });
        }

        function loadSellTab() {
            const content = $('#tab-content');
            content.html(`
                <div style="text-align: center; padding: 40px;">
                    <h2 style="margin-bottom: 20px;">💰 Đăng Bán Item</h2>
                    <p style="color: #666; margin-bottom: 30px;">Bán items của bạn để kiếm gtlm!</p>
                    <button class="btn btn-primary" onclick="openModal('sell-modal')">
                        <i class="fas fa-plus"></i> Đăng Bán Item Mới
                    </button>
                </div>
            `);
        }

        function loadMyItems() {
            const itemType = $('#sell-item-type').val();
            if (!itemType) {
                $('#sell-item-id').html('<option value="">Chọn item...</option>');
                return;
            }

            // Load items từ inventory (cần API riêng hoặc dùng API hiện có)
            $.get('api_gift.php?action=get_user_items&item_type=' + itemType, function (response) {
                if (response.success) {
                    let html = '<option value="">Chọn item...</option>';
                    response.items.forEach(item => {
                        html += `<option value="${item.id}">${item.name}</option>`;
                    });
                    $('#sell-item-id').html(html);
                }
            });
        }

        function submitSell(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'list_item');

            $.ajax({
                url: 'api_marketplace.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        Swal.fire('Thành công', 'Đã đăng bán item!', 'success');
                        closeModal('sell-modal');
                        switchTab('my_listings');
                    } else {
                        Swal.fire('Lỗi', response.message, 'error');
                    }
                }
            });
        }

        function loadMyListingsTab() {
            const content = $('#tab-content');
            content.html('<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Đang tải...</p></div>');

            $.get('api_marketplace.php?action=get_my_listings&status=active', function (response) {
                if (response.success) {
                    if (response.listings.length === 0) {
                        content.html('<div class="empty-state"><i class="fas fa-box-open"></i><h2>Bạn chưa có item nào đang bán</h2></div>');
                    } else {
                        displayMyListings(response.listings);
                    }
                }
            });
        }

        function displayMyListings(listings) {
            const content = $('#tab-content');
            let html = '<div class="listings-grid">';

            listings.forEach(listing => {
                html += `
                    <div class="listing-card">
                        <div class="listing-item-name">${listing.item_name}</div>
                        <div class="listing-price">${number_format(listing.price)} gtlm</div>
                        <div class="listing-description">${listing.description || 'Không có mô tả'}</div>
                        <div style="margin-bottom: 15px; font-size: 12px; color: #999;">
                            Lượt xem: ${listing.views}
                        </div>
                        <button class="btn btn-danger" onclick="cancelListing(${listing.id})">
                            <i class="fas fa-times"></i> Hủy Listing
                        </button>
                    </div>
                `;
            });

            html += '</div>';
            content.html(html);
        }

        function cancelListing(listingId) {
            Swal.fire({
                title: 'Xác Nhận',
                text: 'Bạn có chắc muốn hủy listing này? Item sẽ được trả về kho.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Hủy Listing',
                cancelButtonText: 'Không'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('api_marketplace.php', {
                        action: 'cancel_listing',
                        listing_id: listingId
                    }, function (response) {
                        if (response.success) {
                            Swal.fire('Thành công', 'Đã hủy listing!', 'success');
                            loadMyListingsTab();
                        } else {
                            Swal.fire('Lỗi', response.message, 'error');
                        }
                    });
                }
            });
        }

        function loadWishlistTab() {
            const content = $('#tab-content');
            content.html('<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Đang tải...</p></div>');

            $.get('api_marketplace.php?action=get_wishlist', function (response) {
                if (response.success) {
                    if (response.wishlist.length === 0) {
                        content.html('<div class="empty-state"><i class="fas fa-heart"></i><h2>Wishlist trống</h2></div>');
                    } else {
                        displayListings(response.wishlist);
                    }
                }
            });
        }

        function loadHistoryTab() {
            const content = $('#tab-content');
            content.html(`
                <div class="tabs" style="margin-bottom: 20px;">
                    <button class="tab active" onclick="loadPurchaseHistory()">Mua</button>
                    <button class="tab" onclick="loadSalesHistory()">Bán</button>
                </div>
                <div id="history-content"></div>
            `);
            loadPurchaseHistory();
        }

        function loadPurchaseHistory() {
            $.get('api_marketplace.php?action=get_my_purchases', function (response) {
                if (response.success) {
                    displayHistory(response.purchases, 'Mua');
                }
            });
        }

        function loadSalesHistory() {
            $.get('api_marketplace.php?action=get_my_sales', function (response) {
                if (response.success) {
                    displayHistory(response.sales, 'Bán');
                }
            });
        }

        function displayHistory(history, type) {
            const content = $('#history-content');

            if (history.length === 0) {
                content.html('<div class="empty-state"><i class="fas fa-history"></i><h2>Chưa có lịch sử</h2></div>');
                return;
            }

            let html = '<div class="listings-grid">';
            history.forEach(item => {
                html += `
                    <div class="listing-card">
                        <div class="listing-item-name">${item.item_name}</div>
                        <div class="listing-price">${number_format(item.price)} gtlm</div>
                        ${type === 'Bán' ? '<div style="color: #28a745; margin-top: 10px;">Nhận được: ' + number_format(item.seller_received) + ' gtlm</div>' : ''}
                        <div style="font-size: 12px; color: #999; margin-top: 10px;">
                            ${new Date(item.created_at).toLocaleString('vi-VN')}
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            content.html(html);
        }

        function applyFilters() {
            loadListings();
        }

        function openModal(modalId) {
            $('#' + modalId).addClass('active');
        }

        function closeModal(modalId) {
            $('#' + modalId).removeClass('active');
        }

        function number_format(number) {
            return new Intl.NumberFormat('vi-VN').format(number);
        }

        $(document).ready(function () {
            loadTabContent();
        });
    </script>
</body>

</html>