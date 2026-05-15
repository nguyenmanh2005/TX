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
    <title>Thú Cưng - Mascot System</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }

        .pets-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            justify-content: center;
        }

        .tab-btn {
            padding: 12px 25px;
            background: rgba(255,255,255,0.2);
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            backdrop-filter: blur(5px);
        }

        .tab-btn.active {
            background: white;
            color: #667eea;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .pet-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }

        .pet-card {
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
        }

        .pet-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }

        .pet-card.active {
            border-color: #fbbf24;
            background: linear-gradient(135deg, #fff 0%, #fffbeb 100%);
        }

        .active-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #fbbf24;
            color: #000;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
            box-shadow: 0 4px 10px rgba(251, 191, 36, 0.4);
        }

        .pet-image {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            transition: transform 0.5s;
        }

        .pet-card:hover .pet-image {
            transform: scale(1.1) rotate(5deg);
        }

        .pet-name {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .pet-level {
            font-size: 14px;
            color: #667eea;
            font-weight: 700;
            margin-bottom: 15px;
            display: block;
        }

        .pet-buff {
            background: rgba(102, 126, 234, 0.1);
            padding: 10px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 20px;
            color: #4c51bf;
            font-weight: 600;
        }

        .btn-pet {
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            border: none;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-buy { background: #667eea; color: white; }
        .btn-activate { background: #2dd4bf; color: white; }
        .btn-rename { background: #f1f5f9; color: #475569; margin-top: 10px; }

        .xp-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 10px;
            margin-top: 15px;
            overflow: hidden;
        }

        .xp-fill {
            height: 100%;
            background: #fbbf24;
            transition: width 0.5s;
        }

        .back-home {
            text-align: center;
            margin-top: 40px;
        }

        .back-link {
            color: white;
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            opacity: 0.8;
            transition: opacity 0.3s;
        }

        .back-link:hover { opacity: 1; }
    </style>
</head>
<body>
    <div class="pets-container">
        <h1 style="text-align: center; color: white; margin-bottom: 30px; font-weight: 800; font-size: 36px; text-shadow: 0 4px 10px rgba(0,0,0,0.2);">
            🐾 Hệ Thống Thú Cưng
        </h1>

        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('my-pets')">Thú Cưng Của Tôi</button>
            <button class="tab-btn" onclick="showTab('shop')">Cửa Hàng Pet</button>
        </div>

        <div id="my-pets-section" class="tab-content">
            <div id="my-pets-grid" class="pet-grid">
                <!-- My pets load here -->
            </div>
        </div>

        <div id="shop-section" class="tab-content" style="display: none;">
            <div id="shop-grid" class="pet-grid">
                <!-- Shop pets load here -->
            </div>
        </div>

        <div class="back-home">
            <a href="index.php" class="back-link"><i class="fa fa-arrow-left"></i> Quay lại Sảnh</a>
        </div>
    </div>

    <script>
        function showTab(tab) {
            $('.tab-btn').removeClass('active');
            $(`.tab-btn[onclick="showTab('${tab}')"]`).addClass('active');
            $('.tab-content').hide();
            $(`#${tab}-section`).show();
            if (tab === 'my-pets') loadMyPets();
            else loadShop();
        }

        function loadMyPets() {
            $.get('api_pets.php?action=get_my_pets', function(res) {
                if (res.success) {
                    let html = '';
                    if (res.pets.length === 0) {
                        html = '<div style="grid-column: 1/-1; text-align: center; color: white; padding: 50px;">Bạn chưa có thú cưng nào. Hãy vào Cửa Hàng ngay!</div>';
                    } else {
                        res.pets.forEach(pet => {
                            const xpPercent = (pet.xp % 100);
                            html += `
                                <div class="pet-card ${pet.is_active == 1 ? 'active' : ''}">
                                    ${pet.is_active == 1 ? '<div class="active-badge">Đang Triệu Hồi</div>' : ''}
                                    <div class="pet-image">${getPetEmoji(pet.base_name)}</div>
                                    <div class="pet-name">${pet.custom_name}</div>
                                    <span class="pet-level">Cấp Độ ${pet.level}</span>
                                    <div class="pet-buff">
                                        ⚡ Passive: +${pet.buff_value}% ${pet.buff_type === 'win_bonus' ? ' Gtlm Thắng' : 'Kinh Nghiệm'}
                                    </div>
                                    <div class="xp-bar"><div class="xp-fill" style="width: ${xpPercent}%"></div></div>
                                    <p style="font-size: 11px; color: #999; margin-top: 5px;">XP: ${pet.xp}</p>
                                    ${pet.is_active == 0 ? `<button class="btn-pet btn-activate" onclick="activatePet(${pet.id})">Triệu Hồi</button>` : ''}
                                    <button class="btn-pet btn-rename" onclick="renamePet(${pet.id}, '${pet.custom_name}')">Đổi Tên</button>
                                </div>
                            `;
                        });
                    }
                    $('#my-pets-grid').html(html);
                }
            });
        }

        function loadShop() {
            $.get('api_pets.php?action=get_shop', function(res) {
                if (res.success) {
                    let html = '';
                    res.shop.forEach(pet => {
                        const isOwned = res.owned.includes(parseInt(pet.id));
                        html += `
                            <div class="pet-card">
                                <div class="pet-image">${getPetEmoji(pet.name)}</div>
                                <div class="pet-name">${pet.name}</div>
                                <div class="pet-buff">
                                    ⚡ Passive: +${pet.buff_value}% ${pet.buff_type === 'win_bonus' ? ' Gtlm Thắng' : 'Kinh Nghiệm'}
                                </div>
                                <div style="font-size: 18px; font-weight: 800; color: #fbbf24; margin-bottom: 20px;">
                                    ${new Intl.NumberFormat('vi-VN').format(pet.price)} GTLM
                                </div>
                                ${isOwned ? 
                                    '<button class="btn-pet" style="background:#e2e8f0; color:#94a3b8; cursor:not-allowed;">Đã Sở Hữu</button>' : 
                                    `<button class="btn-pet btn-buy" onclick="buyPet(${pet.id}, '${pet.name}', ${pet.price})">Mua Ngay</button>`
                                }
                            </div>
                        `;
                    });
                    $('#shop-grid').html(html);
                }
            });
        }

        function getPetEmoji(name) {
            if (name.includes('Mèo')) return '🐱';
            if (name.includes('Chó')) return '🐶';
            if (name.includes('Rồng')) return '🐲';
            return '🐾';
        }

        function buyPet(id, name, price) {
            Swal.fire({
                title: 'Xác nhận mua?',
                text: `Bạn muốn mua ${name} với giá ${new Intl.NumberFormat('vi-VN').format(price)} GTLM?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Mua Ngay',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('api_pets.php', { action: 'buy_pet', pet_id: id }, function(res) {
                        if (res.success) {
                            Swal.fire('Thành công!', res.message, 'success');
                            loadShop();
                        } else {
                            Swal.fire('Lỗi!', res.message, 'error');
                        }
                    });
                }
            });
        }

        function activatePet(id) {
            $.post('api_pets.php', { action: 'activate_pet', user_pet_id: id }, function(res) {
                if (res.success) {
                    Swal.fire({
                        title: 'Triệu Hồi Thú Cưng',
                        text: res.message,
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    loadMyPets();
                }
            });
        }

        function renamePet(id, currentName) {
            Swal.fire({
                title: 'Đổi tên thú cưng',
                input: 'text',
                inputValue: currentName,
                showCancelButton: true,
                confirmButtonText: 'Lưu',
                cancelButtonText: 'Hủy',
                inputValidator: (value) => {
                    if (!value) return 'Bạn cần nhập tên!';
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('api_pets.php', { action: 'rename_pet', user_pet_id: id, name: result.value }, function(res) {
                        if (res.success) {
                            Swal.fire('Đã đổi tên!', '', 'success');
                            loadMyPets();
                        } else {
                            Swal.fire('Lỗi!', res.message, 'error');
                        }
                    });
                }
            });
        }

        $(document).ready(() => loadMyPets());
    </script>
</body>
</html>
