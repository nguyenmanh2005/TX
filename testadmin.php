<?php
session_start();
require_once 'db_connect.php';

// Bảo mật: Chỉ cho phép admin truy cập
$isAdmin = (isset($_SESSION['admin']) && $_SESSION['admin'] === true) || (isset($_SESSION['Role']) && $_SESSION['Role'] == 1);
if (!$isAdmin) {
    header("Location: index.php");
    exit();
}

// Load theme để đồng bộ giao diện
require_once 'load_theme.php';
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚙️ Cổng Quản Trị Hệ Thống - Admin Portal</title>
    <!-- Preload fonts and icons asynchronously to prevent render-blocking and enable instant load -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono&display=swap"></noscript>
    
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css"></noscript>
    
    <style>
        :root {
            --bg: #0b0f19;
            --panel: rgba(17, 24, 39, 0.7);
            --primary: #8b5cf6;
            --primary-glow: rgba(139, 92, 246, 0.4);
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --text-main: #f3f4f6;
            --text-sub: #9ca3af;
            --glass-border: rgba(255, 255, 255, 0.08);
            --glass-hover: rgba(255, 255, 255, 0.03);
            --border-radius: 20px;
        }

        body {
            background: var(--bg);
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(139, 92, 246, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(16, 185, 129, 0.08) 0%, transparent 40%);
            color: var(--text-main);
            font-family: 'Outfit', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 40px 20px;
            animation: fadeIn 0.6s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* --- Header Portal --- */
        .portal-header {
            background: var(--panel);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 30px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }

        .header-title h1 {
            margin: 0;
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, #a78bfa, #c084fc, #60a5fa);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-title p {
            margin: 8px 0 0 0;
            color: var(--text-sub);
            font-size: 14px;
            font-weight: 500;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .btn-portal {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 15px var(--primary-glow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-portal:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.6);
        }

        .btn-home {
            background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-home:hover {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        /* --- Search Widget --- */
        .search-container {
            position: relative;
            max-width: 600px;
            margin: 0 auto 40px;
        }

        .search-input {
            width: 100%;
            padding: 18px 24px 18px 60px;
            background: rgba(17, 24, 39, 0.8);
            border: 2px solid var(--glass-border);
            border-radius: 50px;
            color: var(--text-main);
            font-size: 16px;
            font-family: inherit;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-glow);
            background: #111827;
        }

        .search-icon {
            position: absolute;
            left: 24px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: var(--text-sub);
            transition: color 0.3s ease;
        }

        .search-input:focus + .search-icon {
            color: var(--primary);
        }

        /* --- Categories & Grid layout --- */
        .category-section {
            margin-bottom: 45px;
            animation: slideUp 0.6s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .category-title {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid var(--glass-border);
            padding-bottom: 10px;
        }

        .pages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .page-card {
            background: var(--panel);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1.5px solid var(--glass-border);
            border-radius: 16px;
            padding: 22px;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .page-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: transparent;
            transition: background 0.3s ease;
        }

        .page-card.admin-type::before {
            background: var(--primary);
        }

        .page-card.user-type::before {
            background: var(--success);
        }

        .page-card:hover {
            transform: translateY(-5px);
            border-color: rgba(255, 255, 255, 0.15);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            background: var(--glass-hover);
        }

        .page-card.admin-type:hover {
            box-shadow: 0 10px 25px rgba(139, 92, 246, 0.15);
        }

        .page-card.user-type:hover {
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.1);
        }

        .page-badge {
            align-self: flex-start;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .page-badge.admin-badge {
            background: rgba(139, 92, 246, 0.15);
            color: #c084fc;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        .page-badge.user-badge {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .page-info-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .page-icon {
            font-size: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
        }

        .page-card.admin-type:hover .page-icon {
            background: rgba(139, 92, 246, 0.2);
            color: #c084fc;
        }

        .page-card.user-type:hover .page-icon {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }

        .page-name {
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
        }

        .page-path {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: var(--text-sub);
            margin-bottom: 10px;
            word-break: break-all;
        }

        .page-description {
            font-size: 13px;
            color: var(--text-sub);
            line-height: 1.5;
            margin-bottom: 15px;
            flex-grow: 1;
        }

        .page-footer-link {
            font-size: 12px;
            font-weight: 600;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 6px;
            transition: transform 0.2s ease;
        }

        .page-card.user-type .page-footer-link {
            color: var(--success);
        }

        .page-card:hover .page-footer-link {
            transform: translateX(4px);
        }

        /* --- Empty Results Widget --- */
        .empty-results {
            display: none;
            text-align: center;
            padding: 60px 20px;
            background: var(--panel);
            border: 1px dashed var(--glass-border);
            border-radius: var(--border-radius);
            margin: 40px 0;
        }

        .empty-results i {
            font-size: 48px;
            color: var(--text-sub);
            margin-bottom: 20px;
        }

        .empty-results h3 {
            font-size: 20px;
            margin: 0 0 10px 0;
            color: #fff;
        }

        .empty-results p {
            margin: 0;
            color: var(--text-sub);
            font-size: 14px;
        }
    </style>
</head>
<body>

    <div class="container">
        
        <!-- --- PORTAL HEADER --- -->
        <header class="portal-header">
            <div class="header-title">
                <h1><i class="fa fa-sliders"></i> Cổng Quản Trị Hệ Thống</h1>
                <p>Bản đồ điều hướng toàn bộ trang Quản lý (Admin) và Người chơi (User) trong hệ thống</p>
            </div>
            <div class="header-actions">
                <a href="index.php" class="btn-portal btn-home"><i class="fa fa-home"></i> Sảnh Chính</a>
                <a href="admin_dashboard.php" class="btn-portal"><i class="fa fa-tachometer-alt"></i> Admin Dashboard</a>
            </div>
        </header>

        <!-- --- SEARCH BAR --- -->
        <div class="search-container">
            <input type="text" class="search-input" id="portalSearch" placeholder="Tìm kiếm trang theo tên, mô tả hoặc đường dẫn..." onkeyup="searchPortal()">
            <i class="fa fa-magnifying-glass search-icon"></i>
        </div>

        <!-- --- EMPTY RESULTS --- -->
        <div class="empty-results" id="emptyResults">
            <i class="fa fa-circle-question"></i>
            <h3>Không tìm thấy kết quả</h3>
            <p>Vui lòng thử tìm kiếm bằng từ khóa khác hoặc đường dẫn đầy đủ của tệp tin.</p>
        </div>

        <!-- --- CATEGORIES AND PAGES LIST --- -->
        
        <!-- Category 1: Admin & Management -->
        <section class="category-section" data-cat-name="Quản trị & Cấu hình">
            <h2 class="category-title" style="color: #c084fc;"><i class="fa fa-user-shield"></i> Quản trị & Cấu hình (Admin)</h2>
            <div class="pages-grid">
                
                <a href="admin_dashboard.php" class="page-card admin-type" data-keywords="dashboard thong ke bieu do bieu thong bao system">
                    <span class="page-badge admin-badge">Admin</span>
                    <div class="page-info-header">
                        <span class="page-icon">📊</span>
                        <span class="page-name">Dashboard Tổng</span>
                    </div>
                    <div class="page-path">admin_dashboard.php</div>
                    <div class="page-description">Bảng phân tích, thống kê hoạt động, số lượng giao dịch và thông báo khẩn toàn hệ thống.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="admin_advanced_center.php" class="page-card admin-type" data-keywords="advanced backup restore db database logs logger debug console backup">
                    <span class="page-badge admin-badge">Admin</span>
                    <div class="page-info-header">
                        <span class="page-icon">⚙️</span>
                        <span class="page-name">Trung tâm Quản trị Nâng cao</span>
                    </div>
                    <div class="page-path">admin_advanced_center.php</div>
                    <div class="page-description">Chạy lệnh cấu hình SQL, xem log nhật ký chi tiết hệ thống, sao lưu và phục hồi dữ liệu.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="admin_manage_users.php" class="page-card admin-type" data-keywords="users nguoi choi ban khoa gtlm edit profile role edit user ban lock block status">
                    <span class="page-badge admin-badge">Admin</span>
                    <div class="page-info-header">
                        <span class="page-icon">👥</span>
                        <span class="page-name">Quản lý Người Chơi</span>
                    </div>
                    <div class="page-path">admin_manage_users.php</div>
                    <div class="page-description">Xem danh sách người chơi, thay đổi GTLM, cập nhật vai trò, khóa hoặc mở khóa tài khoản.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="admin_manage_items.php" class="page-card admin-type" data-keywords="items vat pham shop cuahang chinh sua shop vat pham item xoa fix">
                    <span class="page-badge admin-badge">Admin</span>
                    <div class="page-info-header">
                        <span class="page-icon">🛍️</span>
                        <span class="page-name">Quản lý Vật Phẩm Shop</span>
                    </div>
                    <div class="page-path">admin_manage_items.php</div>
                    <div class="page-description">Xem danh sách vật phẩm trong cửa hàng tổng hợp, cập nhật thông số và giá bán gtlm.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="admin_manage_frames.php" class="page-card admin-type" data-keywords="frames khung chat avatar delete xoa frame edit chat avatar">
                    <span class="page-badge admin-badge">Admin</span>
                    <div class="page-info-header">
                        <span class="page-icon">🖼️</span>
                        <span class="page-name">Quản lý Khung Chat & Avatar</span>
                    </div>
                    <div class="page-path">admin_manage_frames.php</div>
                    <div class="page-description">Quản lý toàn bộ khung trang trí ảnh đại diện và khung văn bản chat, sửa đổi trạng thái.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="admin_add_items.php" class="page-card admin-type" data-keywords="add item them vat pham shop new vatpham">
                    <span class="page-badge admin-badge">Admin</span>
                    <div class="page-info-header">
                        <span class="page-icon">➕</span>
                        <span class="page-name">Thêm Vật Phẩm Cửa Hàng</span>
                    </div>
                    <div class="page-path">admin_add_items.php</div>
                    <div class="page-description">Khởi tạo và thiết kế vật phẩm mới: đặt tên, định giá, tạo ảnh hiển thị và thiết lập độ hiếm.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="admin_add_frames.php" class="page-card admin-type" data-keywords="add frame them khung chat avatar new frame image decoration">
                    <span class="page-badge admin-badge">Admin</span>
                    <div class="page-info-header">
                        <span class="page-icon">✨</span>
                        <span class="page-name">Thêm Khung Chat & Avatar</span>
                    </div>
                    <div class="page-path">admin_add_frames.php</div>
                    <div class="page-description">Thiết kế và thêm mới các khung trang trí Chat hoặc Avatar để làm quà sự kiện hoặc bán trong shop.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="admin_Event_Manager.php" class="page-card admin-type" data-keywords="event manager quan ly su kien seasonal random jackpot countdown config">
                    <span class="page-badge admin-badge">Admin</span>
                    <div class="page-info-header">
                        <span class="page-icon">🎡</span>
                        <span class="page-name">Quản Lý Sự Kiện Tổng</span>
                    </div>
                    <div class="page-path">admin_Event_Manager.php</div>
                    <div class="page-description">Quản trị toàn diện sự kiện: Kích hoạt Lucky Hour, quản lý nhiệm vụ mùa giải và jackpot.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="admin_storyline.php" class="page-card admin-type" data-keywords="storyline cot truyen nhiem vu cuoc phieu luu game main lore chapter">
                    <span class="page-badge admin-badge">Admin</span>
                    <div class="page-info-header">
                        <span class="page-icon">📖</span>
                        <span class="page-name">Quản lý Nhiệm Vụ Cốt Truyện</span>
                    </div>
                    <div class="page-path">admin_storyline.php</div>
                    <div class="page-description">Quản lý tiến trình phiêu lưu và nhiệm vụ cốt truyện chính, bổ sung chương mới.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="admin_flash_event.php" class="page-card admin-type" data-keywords="flash event su kien nhanh toc hanh hot deal sale gold discount">
                    <span class="page-badge admin-badge">Admin</span>
                    <div class="page-info-header">
                        <span class="page-icon">⚡</span>
                        <span class="page-name">Quản lý Sự Kiện Chớp Nhoáng</span>
                    </div>
                    <div class="page-path">admin_flash_event.php</div>
                    <div class="page-description">Khởi chạy sự kiện chớp nhoáng (Flash Event) ngẫu nhiên, cấu hình phần thưởng GTLM đặc biệt.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="admin_tournaments.php" class="page-card admin-type" data-keywords="tournaments giai dau bracket prize coin match config">
                    <span class="page-badge admin-badge">Admin</span>
                    <div class="page-info-header">
                        <span class="page-icon">🏆</span>
                        <span class="page-name">Quản lý Giải Đấu PvP</span>
                    </div>
                    <div class="page-path">admin_tournaments.php</div>
                    <div class="page-description">Quản trị các giải đấu PvP cộng đồng, lập sơ đồ ghép cặp đối đầu và trao thưởng giải đấu.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="admin_pvp_manager.php" class="page-card admin-type" data-keywords="pvp challenges thach dau pvp solo cuoc gtlm challenge combat play admin cancel refund force winner">
                    <span class="page-badge admin-badge">Admin</span>
                    <div class="page-info-header">
                        <span class="page-icon">⚔️</span>
                        <span class="page-name">Quản lý Thách Đấu PvP</span>
                    </div>
                    <div class="page-path">admin_pvp_manager.php</div>
                    <div class="page-description">Bảng điều phối thách đấu PvP: Giám sát đấu trường trực tuyến, hủy hoàn tiền cược hoặc xử thắng khẩn cấp.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="admin_analytics.php" class="page-card admin-type" data-keywords="analytics bieu do thong ke bieu do doanh thu login active log user tracker gd">
                    <span class="page-badge admin-badge">Admin</span>
                    <div class="page-info-header">
                        <span class="page-icon">📈</span>
                        <span class="page-name">Phân Tích & Hệ Thống</span>
                    </div>
                    <div class="page-path">admin_analytics.php</div>
                    <div class="page-description">Theo dõi luồng hành vi của người chơi, phân tích tỷ lệ hoạt động và các chỉ số kinh tế.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="admin_crafting.php" class="page-card admin-type" data-keywords="crafting che tao cong thuc nguyen lieu config slot recipe craft item">
                    <span class="page-badge admin-badge">Admin</span>
                    <div class="page-info-header">
                        <span class="page-icon">⚒️</span>
                        <span class="page-name">Cấu Hình Công Thức Chế Tạo</span>
                    </div>
                    <div class="page-path">admin_crafting.php</div>
                    <div class="page-description">Thiết lập công thức ghép mảnh chế tạo vật phẩm hiếm, kiểm tra tỷ lệ thành công của lò rèn.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

            </div>
        </section>

        <!-- Category 2: Core User Pages -->
        <section class="category-section" data-cat-name="Cá nhân & Tài khoản">
            <h2 class="category-title" style="color: #34d399;"><i class="fa fa-user"></i> Cá nhân & Tài khoản</h2>
            <div class="pages-grid">
                
                <a href="index.php" class="page-card user-type" data-keywords="index home lobby sanh chinh dai sanh game list chat widget checkin">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🏛️</span>
                        <span class="page-name">Đại Sảnh Vegas Royale</span>
                    </div>
                    <div class="page-path">index.php</div>
                    <div class="page-description">Đại sảnh chính của website, hiển thị các tính năng cốt lõi, danh sách game và kiểm tra hàng ngày.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="in4.php" class="page-card user-type" data-keywords="in4 profile ho so cap do level xp streak vip chat frame information">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">👤</span>
                        <span class="page-name">Hồ Sơ Cá Nhân (Profile)</span>
                    </div>
                    <div class="page-path">in4.php</div>
                    <div class="page-description">Hiển thị cấp độ, XP hiện tại, chuỗi đăng nhập tốt nhất, số GTLM sở hữu và khung trang trí.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="editProfile.php" class="page-card user-type" data-keywords="edit profile cap nhat avatar mat khau password name doi ten">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">✏️</span>
                        <span class="page-name">Chỉnh Sửa Hồ Sơ</span>
                    </div>
                    <div class="page-path">editProfile.php</div>
                    <div class="page-description">Cập nhật ảnh đại diện cá nhân, thay đổi tên hiển thị và cập nhật mật khẩu đăng nhập an toàn.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="account_center.php" class="page-card user-type" data-keywords="account center security bao mat 2fa otp nhat ky dang nhap audit block">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🛡️</span>
                        <span class="page-name">Trung Tâm Tài Khoản Nâng Cao</span>
                    </div>
                    <div class="page-path">account_center.php</div>
                    <div class="page-description">Tăng cường bảo mật: Đăng ký xác thực 2 lớp, theo dõi lịch sử và thiết bị đăng nhập của tài khoản.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="inventory.php" class="page-card user-type" data-keywords="inventory hom do tui do su dung cuoc vatpham cursor theme chat frame decoration">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🎒</span>
                        <span class="page-name">Hòm Đồ Cá Nhân (Túi đồ)</span>
                    </div>
                    <div class="page-path">inventory.php</div>
                    <div class="page-description">Lưu trữ toàn bộ vật phẩm, chủ đề trang web và con trỏ chuột đã mua để kích hoạt sử dụng.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="select_title.php" class="page-card user-type" data-keywords="select title danh hieu thanh tich nickname icon highlight">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🏷️</span>
                        <span class="page-name">Lựa Chọn Danh Hiệu</span>
                    </div>
                    <div class="page-path">select_title.php</div>
                    <div class="page-description">Lựa chọn các danh hiệu mở khóa từ thành tựu để đeo hiển thị nổi bật bên cạnh tên người chơi.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="achievements.php" class="page-card user-type" data-keywords="achievements thanh tuu danh hieu gtlm thuong medal rank up trophy">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🏅</span>
                        <span class="page-name">Hệ Thống Thành Tựu</span>
                    </div>
                    <div class="page-path">achievements.php</div>
                    <div class="page-description">Bảng thành tích Vegas Royale: Đạt cột mốc chơi game, chiến thắng nhận GTLM và mở khóa danh hiệu độc quyền.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="friends.php" class="page-card user-type" data-keywords="friends ban be ket ban tim ban block report pvp challenge send message">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🤝</span>
                        <span class="page-name">Danh Sách Bạn Bè</span>
                    </div>
                    <div class="page-path">friends.php</div>
                    <div class="page-description">Quản lý danh sách bạn bè, gửi lời mời kết bạn, kiểm tra trạng thái online và gửi lời mời PvP nhanh.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="private_message.php" class="page-card user-type" data-keywords="private message hop thu tin nhan mat inbox mail gui tin chat rieng">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">📩</span>
                        <span class="page-name">Hộp Thư Tin Nhắn</span>
                    </div>
                    <div class="page-path">private_message.php</div>
                    <div class="page-description">Gửi và nhận tin nhắn riêng tư bảo mật mã hóa cao giữa người chơi với nhau trong hệ thống.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="notifications.php" class="page-card user-type" data-keywords="notifications thong bao thongtin hethong gift reward boss event update mail">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🔔</span>
                        <span class="page-name">Trung Tâm Thông Báo</span>
                    </div>
                    <div class="page-path">notifications.php</div>
                    <div class="page-description">Hộp thư hệ thống: Thông báo nhận thưởng, nhắc nhở boss xuất hiện, lời thách đấu PvP của đối thủ.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

            </div>
        </section>

        <!-- Category 3: Games -->
        <section class="category-section" data-cat-name="Trò chơi giải trí">
            <h2 class="category-title" style="color: #60a5fa;"><i class="fa fa-gamepad"></i> Trò chơi giải trí</h2>
            <div class="pages-grid">
                
                <a href="games.php" class="page-card user-type" data-keywords="games tro choi pvp world boss dungeon tai xiu cuoc gtlm lucky wheel list">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🎮</span>
                        <span class="page-name">Cổng Trò Chơi Vegas</span>
                    </div>
                    <div class="page-path">games.php</div>
                    <div class="page-description">Danh mục đầy đủ tất cả tựa game cá cược, PvP, nhập vai viễn chinh tích hợp trên nền tảng.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="pvp_arena.php" class="page-card user-type" data-keywords="pvp arena dau truong thach dau pvp solo cuoc gtlm challenge combat play">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">⚔️</span>
                        <span class="page-name">Đấu Trường Quyết Đấu PvP</span>
                    </div>
                    <div class="page-path">pvp_arena.php</div>
                    <div class="page-description">Hệ thống thách đấu trực tiếp: Tạo phòng, đặt cược số tiền GTLM, phân định thắng bại thời gian thực.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="world_boss.php" class="page-card user-type" data-keywords="world boss mathan raid boss tank healer dps damage top tier bounty">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🌋</span>
                        <span class="page-name">Đại Chiến Ma Thần (World Boss)</span>
                    </div>
                    <div class="page-path">world_boss.php</div>
                    <div class="page-description">Tham gia chiến trường săn Boss theo Phase với 3 Class (Sát thủ, Hộ vệ, Thần y) để giành quà S-tier.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="tournament.php" class="page-card user-type" data-keywords="tournament giai dau pvp cup tranhtai bracket dau loai pvp gold">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🏆</span>
                        <span class="page-name">Giải Đấu Tranh Cúp PvP</span>
                    </div>
                    <div class="page-path">tournament.php</div>
                    <div class="page-description">Tham gia đăng ký đấu giải loại trực tiếp PvP, theo dõi sơ đồ nhánh đấu và tranh đoạt phần thưởng lớn.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="dungeon.php" class="page-card user-type" data-keywords="dungeon quai vat ai vien chinh duong ham vuot ai chienthanh quai map">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🏰</span>
                        <span class="page-name">Phó Bản Viễn Chinh (Dungeon)</span>
                    </div>
                    <div class="page-path">dungeon.php</div>
                    <div class="page-description">Vượt ải diệt quái vật thu thập mảnh nguyên liệu chế tạo trang bị, tính toán bước đi chiến thuật.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="lucky_wheel.php" class="page-card user-type" data-keywords="lucky wheel vong quay may man cuoc gold free spin qua vip jackpot">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🎡</span>
                        <span class="page-name">Vòng Quay May Mắn</span>
                    </div>
                    <div class="page-path">lucky_wheel.php</div>
                    <div class="page-description">Thử vận may hằng ngày với vòng quay GTLM, cơ hội nổ hũ nhận giải thưởng Jackpot khổng lồ.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="hopmu.php" class="page-card user-type" data-keywords="hopmu gacha blind box cuoc vatpham hiem open box gachapon random lucky">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🎁</span>
                        <span class="page-name">Hộp Mù Gacha (Blind Box)</span>
                    </div>
                    <div class="page-path">hopmu.php</div>
                    <div class="page-description">Mở khóa ngẫu nhiên các vật phẩm, chủ đề, khung chat đặc biệt và quý hiếm với tỷ lệ may mắn.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="games/community_lottery.php" class="page-card user-type" data-keywords="lottery xo so cong dong mua ve jackpot 20h draw live draw numbers">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🔮</span>
                        <span class="page-name">Xổ Số Cộng Đồng</span>
                    </div>
                    <div class="page-path">games/community_lottery.php</div>
                    <div class="page-description">Chọn 6 số may mắn để tranh đoạt Jackpot khổng lồ, quay thưởng trực tiếp bouncing balls lúc 20:00 hằng ngày.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="poker.php" class="page-card user-type" data-keywords="poker game bai xi phe texas holdem cards dealer pot bet table chip">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🃏</span>
                        <span class="page-name">Sảnh Bài Poker Texas</span>
                    </div>
                    <div class="page-path">poker.php</div>
                    <div class="page-description">Game bài đấu trí kinh điển Texas Hold'em, đặt cược, tố bài cân não và tranh đoạt quỹ cược lớn.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="caothe.php" class="page-card user-type" data-keywords="caothe cao the cuoc cao bai ba cay 3 cay points bet win cards">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">💳</span>
                        <span class="page-name">Bài Cào Cược May Mắn</span>
                    </div>
                    <div class="page-path">caothe.php</div>
                    <div class="page-description">Game bài cào ba cây cược nhanh, so điểm với nhà cái hoặc người chơi để nhận tỷ lệ thưởng hấp dẫn.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

            </div>
        </section>

        <!-- Category 4: Social & Support -->
        <section class="category-section" data-cat-name="Cộng đồng & Giao dịch">
            <h2 class="category-title" style="color: #f59e0b;"><i class="fa fa-comments"></i> Cộng đồng & Giao dịch</h2>
            <div class="pages-grid">
                
                <a href="shop.php" class="page-card user-type" data-keywords="shop cuahang mua vatpham cursor theme chat avatar frame gtlm buy">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🛒</span>
                        <span class="page-name">Cửa Hàng Hệ Thống</span>
                    </div>
                    <div class="page-path">shop.php</div>
                    <div class="page-description">Mua sắm con trỏ, chủ đề trang web và khung trang trí để cá nhân hóa tài khoản cá nhân.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="chat.php" class="page-card user-type" data-keywords="chat room phòng chat public global chatbox message emoji color title">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">💬</span>
                        <span class="page-name">Phòng Chat Số 1 (Global)</span>
                    </div>
                    <div class="page-path">chat.php</div>
                    <div class="page-description">Kênh trò chuyện cộng đồng chính, hiển thị tin nhắn, khung chat và danh hiệu hiển thị rực rỡ.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="chat2.php" class="page-card user-type" data-keywords="chat2 room phong chat phong 2 secondary room">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🗣️</span>
                        <span class="page-name">Phòng Chat Số 2</span>
                    </div>
                    <div class="page-path">chat2.php</div>
                    <div class="page-description">Phòng chat dự phòng số 2 dành cho giao lưu hội nhóm, bàn luận giải đấu hoặc trao đổi vật phẩm.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="social_feed.php" class="page-card user-type" data-keywords="social feed bang tin bai dang status like cam xuc comment post share">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">📰</span>
                        <span class="page-name">Bảng Tin Mạng Xã Hội</span>
                    </div>
                    <div class="page-path">social_feed.php</div>
                    <div class="page-description">Mạng xã hội nội bộ Vegas Royale: Đăng status, hình ảnh, thả cảm xúc tim/like và comment giao lưu.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="marketplace.php" class="page-card user-type" data-keywords="marketplace cho den giao dich vatpham mua ban sell buy item trading black market">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">⚖️</span>
                        <span class="page-name">Chợ Đen Giao Dịch (Market)</span>
                    </div>
                    <div class="page-path">marketplace.php</div>
                    <div class="page-description">Nơi người chơi tự do đăng bán các vật phẩm, trang bị mở từ hộp mù hoặc chế tạo cho nhau để kiếm GTLM.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="auction.php" class="page-card user-type" data-keywords="auction dau gia bid gia thau vatpham hiem dau gia nguoc time end">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🔨</span>
                        <span class="page-name">Sàn Đấu Giá Vật Phẩm</span>
                    </div>
                    <div class="page-path">auction.php</div>
                    <div class="page-description">Sàn đấu giá trực tiếp: Đấu giá các trang bị, vật phẩm hiếm bậc nhất hệ thống để sở hữu chúng độc quyền.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="mentor_center.php" class="page-card user-type" data-keywords="mentor center su do su phu de tu quest level up reward gt">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🤝</span>
                        <span class="page-name">Hệ Thống Sư Đồ (Mentor)</span>
                    </div>
                    <div class="page-path">mentor_center.php</div>
                    <div class="page-description">Liên kết Sư phụ - Đệ tử hướng dẫn lẫn nhau vượt phó bản, cùng nhận thưởng lớn GTLM.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="leaderboard.php" class="page-card user-type" data-keywords="leaderboard bang xep hang dai sanh top money level win rank">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🏆</span>
                        <span class="page-name">Bảng Xếp Hạng Toàn Server</span>
                    </div>
                    <div class="page-path">leaderboard.php</div>
                    <div class="page-description">Vinh danh dũng sĩ: Bảng vàng Top Cao Thủ giàu nhất, cấp độ cao nhất và thắng PvP nhiều nhất.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="hall_of_fame.php" class="page-card user-type" data-keywords="hall of fame den tho danh vong top cao thu huyen thoai vinh danh">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">🏛️</span>
                        <span class="page-name">Đền Thờ Danh Vọng</span>
                    </div>
                    <div class="page-path">hall_of_fame.php</div>
                    <div class="page-description">Đền thờ ghi danh vĩnh viễn các cao thủ huyền thoại có đóng góp lớn nhất cho Vegas Royale.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

            </div>
        </section>

        <!-- Category 5: Utils & System -->
        <section class="category-section" data-cat-name="Tiện ích & Hệ thống">
            <h2 class="category-title" style="color: #ef4444;"><i class="fa fa-wrench"></i> Tiện ích & Hệ thống</h2>
            <div class="pages-grid">
                
                <a href="statistics.php" class="page-card user-type" data-keywords="statistics thong ke doanh so game ti le pvp nap rut log gd admin">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">📊</span>
                        <span class="page-name">Thống Kê Hệ Thống</span>
                    </div>
                    <div class="page-path">statistics.php</div>
                    <div class="page-description">Hiển thị thông số vận hành tổng quan toàn website: Tỷ lệ cược thắng, tổng số GTLM ảo lưu hành.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

                <a href="about.php" class="page-card user-type" data-keywords="about ve chung toi dieu khoan casino casino gtlm policy rules">
                    <span class="page-badge user-badge">User</span>
                    <div class="page-info-header">
                        <span class="page-icon">ℹ️</span>
                        <span class="page-name">Về Chúng Tôi (About)</span>
                    </div>
                    <div class="page-path">about.php</div>
                    <div class="page-description">Thông tin giới thiệu về nền tảng Vegas Royale, điều khoản dịch vụ và cam kết bảo mật.</div>
                    <div class="page-footer-link">Truy cập <i class="fa fa-chevron-right"></i></div>
                </a>

            </div>
        </section>

    </div>

    <!-- --- PORTAL JAVASCRIPT --- -->
    <script>
        function searchPortal() {
            const query = document.getElementById('portalSearch').value.toLowerCase().trim();
            const cards = document.querySelectorAll('.page-card');
            const sections = document.querySelectorAll('.category-section');
            const emptyResults = document.getElementById('emptyResults');
            
            let totalVisible = 0;

            sections.forEach(section => {
                let sectionVisibleCount = 0;
                const sectionGrid = section.querySelector('.pages-grid');
                const sectionCards = section.querySelectorAll('.page-card');

                sectionCards.forEach(card => {
                    const name = card.querySelector('.page-name').textContent.toLowerCase();
                    const path = card.querySelector('.page-path').textContent.toLowerCase();
                    const desc = card.querySelector('.page-description').textContent.toLowerCase();
                    const keywords = card.getAttribute('data-keywords').toLowerCase();

                    // Search condition
                    if (
                        name.includes(query) || 
                        path.includes(query) || 
                        desc.includes(query) || 
                        keywords.includes(query)
                    ) {
                        card.style.display = 'flex';
                        sectionVisibleCount++;
                        totalVisible++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Hide category section if no cards are visible inside it
                if (sectionVisibleCount > 0) {
                    section.style.display = 'block';
                } else {
                    section.style.display = 'none';
                }
            });

            // Handle empty results widget
            if (totalVisible === 0) {
                emptyResults.style.display = 'block';
            } else {
                emptyResults.style.display = 'none';
            }
        }

        // Focus search input automatically on load
        window.addEventListener('DOMContentLoaded', () => {
            document.getElementById('portalSearch').focus();
        });
    </script>

    <!-- --- THREE.JS BACKGROUND --- -->
    <canvas id="threejs-background"></canvas>
    <!-- Use high-speed jsDelivr CDN for Three.js -->
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/build/three.min.js"></script>
    <script>
        (function () {
            window.themeConfig = {
                particleCount: <?= $particleCount ?? 800 ?>,
                particleSize: <?= $particleSize ?? 0.05 ?>,
                particleColor: '<?= $particleColor ?? "#ffffff" ?>',
                particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
                shapeCount: <?= $shapeCount ?? 10 ?>,
                shapeColors: <?= json_encode($shapeColors ?? ["#667eea", "#764ba2", "#4facfe", "#00f2fe"]) ?>,
                shapeOpacity: <?= $shapeOpacity ?? 0.3 ?>,
                bgGradient: <?= json_encode($bgGradient ?? ["#667eea", "#764ba2", "#4facfe"]) ?>
            };
            const script = document.createElement('script');
            script.src = 'threejs-background.js';
            document.head.appendChild(script);
        })();
    </script>
</body>
</html>
