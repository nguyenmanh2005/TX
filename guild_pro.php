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
    <title>Trung Tâm Lãnh Địa & Guild Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #8b5cf6;
            --secondary: #ec4899;
            --bg: #0f172a;
            --panel: rgba(30, 41, 59, 0.7);
        }

        body {
            background: var(--bg);
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(139, 92, 246, 0.1) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(236, 72, 153, 0.1) 0%, transparent 40%);
            color: #fff;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            padding-bottom: 50px;
        }

        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }

        .header {
            text-align: center;
            padding: 40px 0;
            background: var(--panel);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            border: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 30px;
        }

        .guild-stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
        }

        .stat-item {
            background: rgba(255,255,255,0.05);
            padding: 10px 25px;
            border-radius: 15px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .stat-value { font-size: 20px; font-weight: 800; color: #facc15; }
        .stat-label { font-size: 12px; color: #94a3b8; text-transform: uppercase; }

        /* ── Territories ── */
        .section-title {
            font-size: 24px;
            font-weight: 900;
            margin: 40px 0 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .territory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
        }

        .territory-card {
            background: var(--panel);
            border-radius: 24px;
            padding: 25px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .territory-card:hover { transform: translateY(-5px); border-color: var(--primary); }

        .territory-status {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 800;
        }

        .status-owned { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .status-free { background: rgba(148, 163, 184, 0.2); color: #94a3b8; }

        .buff-badge {
            display: inline-block;
            margin-top: 15px;
            background: rgba(139, 92, 246, 0.1);
            color: var(--primary);
            padding: 5px 15px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
        }

        /* ── Guild Shop ── */
        .shop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .shop-item {
            background: var(--panel);
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .item-icon {
            font-size: 50px;
            margin-bottom: 15px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .item-price {
            font-size: 18px;
            font-weight: 800;
            color: #facc15;
            margin: 10px 0;
        }

        .btn-buy {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 10px;
            border-radius: 12px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-buy:hover { opacity: 0.9; transform: scale(1.02); }

        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: var(--panel);
            color: white;
            padding: 10px 20px;
            border-radius: 50px;
            text-decoration: none;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            z-index: 100;
        }
    </style>
</head>
<body>

    <a href="guild.php" class="back-btn"><i class="fa fa-arrow-left"></i> Bang Hội</a>

    <div class="container">
        <header class="header">
            <h1 style="font-size: 32px; font-weight: 900; margin: 0;">Trung Tâm Nâng Cao</h1>
            <p style="color: #94a3b8; margin-top: 5px;">Quản lý lãnh địa, mua sắm và kích hoạt Buff Bang Hội</p>
            
            <div class="guild-stats">
                <div class="stat-item">
                    <div class="stat-value" id="user-contribution">0</div>
                    <div class="stat-label">Điểm đóng góp của bạn</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value" id="guild-territory-count">0</div>
                    <div class="stat-label">Lãnh địa chiếm đóng</div>
                </div>
            </div>
        </header>

        <h2 class="section-title"><i class="fa fa-map-marked-alt" style="color: var(--primary);"></i> Lãnh Địa Bang Hội</h2>
        <div class="territory-grid" id="territory-grid">
            <!-- Territories load here -->
        </div>

        <h2 class="section-title"><i class="fa fa-shopping-cart" style="color: var(--secondary);"></i> Guild Shop</h2>
        <div class="shop-grid" id="shop-grid">
            <!-- Shop items load here -->
        </div>
    </div>

    <script>
        function loadGuildPro() {
            $.get('api_guild_pro.php?action=get_guild_pro', function(res) {
                if (res.success) {
                    $('#user-contribution').text(new Intl.NumberFormat().format(res.member.contribution_points));
                    $('#guild-territory-count').text(res.territories.length);

                    // Render Territories
                    let tHtml = '';
                    // Giả định chúng ta load toàn bộ lãnh địa từ server (cần cập nhật api để trả về all_territories)
                    // Ở đây tôi sẽ render những cái guild đang sở hữu trước
                    res.territories.forEach(t => {
                        tHtml += `
                            <div class="territory-card">
                                <div class="territory-status status-owned">ĐANG CHIẾM ĐÓNG</div>
                                <h3 style="margin: 0; font-size: 20px;">${t.name}</h3>
                                <div style="color: #94a3b8; font-size: 13px;">Vị trí: ${t.location_code}</div>
                                <div class="buff-badge">
                                    <i class="fa fa-bolt"></i> Buff: ${t.buff_type === 'win_bonus' ? '+' + t.buff_value + '% tỉ lệ thắng' : t.buff_type}
                                </div>
                            </div>
                        `;
                    });
                    if (tHtml === '') tHtml = '<p style="grid-column: 1/-1; text-align: center; opacity: 0.5;">Bang hội chưa chiếm đóng lãnh địa nào.</p>';
                    $('#territory-grid').html(tHtml);

                    // Render Shop
                    let sHtml = '';
                    res.shop_items.forEach(item => {
                        sHtml += `
                            <div class="shop-item">
                                <div class="item-icon">${getItemIcon(item.item_type)}</div>
                                <div style="font-weight: 800; font-size: 16px;">${item.item_name}</div>
                                <div class="item-price">${new Intl.NumberFormat().format(item.price_contribution)} CP</div>
                                <button class="btn-buy" onclick="buyItem(${item.id})">MUA NGAY</button>
                            </div>
                        `;
                    });
                    $('#shop-grid').html(sHtml);
                } else {
                    Swal.fire('Thông báo', res.message, 'info');
                }
            });
        }

        function getItemIcon(type) {
            switch(type) {
                case 'avatar_frame': return '🖼️';
                case 'theme': return '🎨';
                case 'title': return '👑';
                default: return '📦';
            }
        }

        function buyItem(id) {
            $.post('api_guild_pro.php', { action: 'buy_shop_item', item_id: id }, function(res) {
                if (res.success) {
                    Swal.fire('Thành công!', res.message, 'success');
                    loadGuildPro();
                } else {
                    Swal.fire('Lỗi!', res.message, 'error');
                }
            });
        }

        $(document).ready(loadGuildPro);
    </script>
</body>
</html>
