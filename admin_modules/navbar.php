<?php
/**
 * 🛡️ Unified Master Admin Navigation Bar Component
 * Rendered across all Admin modules for a consistent, seamless experience.
 */
function renderAdminNavbar($activePage = 'overview', $activeSub = '') {
    $mainNav = [
        'overview'     => ['label' => 'Tổng Quan', 'icon' => 'fa-gauge', 'url' => 'admin_dashboard.php?page=overview'],
        'economy'      => ['label' => 'Kinh Tế & Phân Tích', 'icon' => 'fa-vault', 'url' => 'admin_dashboard.php?page=economy&sub=overview'],
        'assets'       => ['label' => 'Người Dùng & Tài Sản', 'icon' => 'fa-users-gear', 'url' => 'admin_dashboard.php?page=assets&sub=users'],
        'events'       => ['label' => 'Sự Kiện & Cốt Truyện', 'icon' => 'fa-calendar-star', 'url' => 'admin_dashboard.php?page=events&sub=main'],
        'competitions' => ['label' => 'Sàn Đấu & Giải Đấu', 'icon' => 'fa-trophy', 'url' => 'admin_dashboard.php?page=competitions&sub=pvp']
    ];

    $subMenus = [
        'economy' => [
            'overview'     => ['label' => 'Tổng Quan GDP & Dòng Tiền', 'icon' => 'fa-gauge-high', 'url' => 'admin_dashboard.php?page=economy&sub=overview'],
            'games'        => ['label' => 'Hiệu Suất 61 Game', 'icon' => 'fa-dice', 'url' => 'admin_dashboard.php?page=economy&sub=games'],
            'billionaires' => ['label' => 'Bảng Phong Thần', 'icon' => 'fa-trophy', 'url' => 'admin_dashboard.php?page=economy&sub=billionaires'],
            'analytics'    => ['label' => 'Phân Tích Truy Cập', 'icon' => 'fa-chart-line', 'url' => 'admin_dashboard.php?page=economy&sub=analytics'],
            'revenue'      => ['label' => 'Báo Cáo Doanh Thu', 'icon' => 'fa-chart-pie', 'url' => 'admin_dashboard.php?page=economy&sub=revenue'],
            'anomalies'    => ['label' => 'Giám Sát Bất Thường', 'icon' => 'fa-shield-virus', 'url' => 'admin_dashboard.php?page=economy&sub=anomalies']
        ],
        'assets' => [
            'users'    => ['label' => 'Quản Lý Người Dùng', 'icon' => 'fa-users', 'url' => 'admin_dashboard.php?page=assets&sub=users'],
            'items'    => ['label' => 'Quản Lý Vật Phẩm', 'icon' => 'fa-box-open', 'url' => 'admin_dashboard.php?page=assets&sub=items'],
            'frames'   => ['label' => 'Quản Lý Khung Avatar', 'icon' => 'fa-crop-simple', 'url' => 'admin_dashboard.php?page=assets&sub=frames'],
            'crafting' => ['label' => 'Công Thức Chế Tạo', 'icon' => 'fa-hammer', 'url' => 'admin_dashboard.php?page=assets&sub=crafting']
        ],
        'events' => [
            'main'      => ['label' => 'Event Manager Tổng Lực', 'icon' => 'fa-bullhorn', 'url' => 'admin_dashboard.php?page=events&sub=main'],
            'flash'     => ['label' => 'Flash Event Chớp Nhoáng', 'icon' => 'fa-bolt', 'url' => 'admin_dashboard.php?page=events&sub=flash'],
            'storyline' => ['label' => 'Cốt Truyện & Lore', 'icon' => 'fa-book-skull', 'url' => 'admin_dashboard.php?page=events&sub=storyline']
        ],
        'competitions' => [
            'pvp'         => ['label' => 'Sàn Đấu PvP', 'icon' => 'fa-swords', 'url' => 'admin_dashboard.php?page=competitions&sub=pvp'],
            'tournaments' => ['label' => 'Giải Đấu Tournaments', 'icon' => 'fa-medal', 'url' => 'admin_dashboard.php?page=competitions&sub=tournaments']
        ]
    ];
    $themeCssPath = (strpos($_SERVER['PHP_SELF'], '/admin_modules/') !== false) ? 'admin_theme.css' : 'admin_modules/admin_theme.css';
    ?>
    <!-- Unified Admin Design System & Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $themeCssPath ?>">

    <!-- Master Admin Header & Topbar -->
    <style>
        .master-admin-header {
            background: rgba(13, 18, 34, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 12px 20px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
        }
        .master-top-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        .master-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            font-weight: 800;
            color: #fff;
            letter-spacing: 0.5px;
            text-decoration: none;
        }
        .master-brand i { color: #4f8dff; font-size: 18px; }
        .master-brand span {
            background: linear-gradient(135deg, #4f8dff, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .master-nav-links {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .master-nav-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 10px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
        }
        .master-nav-item:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.08);
        }
        .master-nav-item.active {
            color: #fff;
            background: linear-gradient(135deg, rgba(79, 141, 255, 0.2), rgba(167, 139, 250, 0.2));
            border-color: rgba(79, 141, 255, 0.4);
            box-shadow: 0 4px 15px rgba(79, 141, 255, 0.15);
        }
        .master-quick-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .master-btn-quick {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            color: #e2e8f0;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.2s ease;
        }
        .master-btn-quick:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
        }
        .master-btn-quick.danger {
            color: #fb7185;
            border-color: rgba(251, 113, 133, 0.2);
            background: rgba(251, 113, 133, 0.08);
        }
        .master-btn-quick.danger:hover {
            background: #fb7185;
            color: #fff;
        }
        /* Submenu Tabs */
        .master-sub-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            padding-top: 10px;
        }
        .master-sub-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #94a3b8;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.04);
            background: rgba(255, 255, 255, 0.02);
            transition: all 0.2s ease;
        }
        .master-sub-item:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.06);
        }
        .master-sub-item.active {
            color: #38bdf8;
            background: rgba(56, 189, 248, 0.1);
            border-color: rgba(56, 189, 248, 0.3);
        }
    </style>

    <header class="master-admin-header">
        <div class="master-top-row">
            <a href="admin_dashboard.php?page=overview" class="master-brand">
                <i class="fa-solid fa-shield-halved"></i>
                <div><span>ADMIN</span> PORTAL</div>
            </a>

            <nav class="master-nav-links">
                <?php foreach ($mainNav as $key => $item): ?>
                    <a href="<?= $item['url'] ?>" class="master-nav-item <?= $activePage === $key ? 'active' : '' ?>">
                        <i class="fa-solid <?= $item['icon'] ?>"></i>
                        <?= $item['label'] ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="master-quick-actions">
                <a href="bot/index.php" class="master-btn-quick" style="color: #c084fc;">
                    <i class="fa-solid fa-robot"></i> Bot Army
                </a>
                <a href="chat3.php" class="master-btn-quick" style="color: #fb923c;">
                    <i class="fa-solid fa-vial"></i> Tester Hub
                </a>
                <a href="index.php" class="master-btn-quick">
                    <i class="fa-solid fa-home"></i> Trang Chủ Game
                </a>
                <a href="logout.php" class="master-btn-quick danger" title="Đăng Xuất">
                    <i class="fa-solid fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <?php if (isset($subMenus[$activePage])): ?>
            <div class="master-sub-row">
                <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-right: 4px;">Phân hệ:</span>
                <?php foreach ($subMenus[$activePage] as $subKey => $sub): ?>
                    <a href="<?= $sub['url'] ?>" class="master-sub-item <?= $activeSub === $subKey ? 'active' : '' ?>">
                        <i class="fa-solid <?= $sub['icon'] ?>"></i>
                        <?= $sub['label'] ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </header>

    <script>
    // Xử lý chuyển tab siêu tốc cho các phân hệ đã gộp DOM (như Kinh Tế)
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.master-sub-item').forEach(function(item) {
            item.addEventListener('click', function(e) {
                if (typeof switchEcoTab === 'function') {
                    var url = this.getAttribute('href') || '';
                    var match = url.match(/[?&]sub=([a-z0-9_-]+)/i);
                    if (match && match[1]) {
                        var targetPane = document.getElementById('eco-pane-' + match[1]);
                        if (targetPane) {
                            e.preventDefault();
                            switchEcoTab(null, match[1]);
                        }
                    }
                }
            });
        });
    });
    </script>
    <?php
}
