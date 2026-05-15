<!-- 
    📜 GTLM Welcome & Credits Modal v2.0
    Hiển thị lần đầu khi người dùng truy cập website.
    Giới thiệu nền tảng, danh sách game, credits và quy tắc cộng đồng.
-->
<style>
    #gtlm-overlay {
        position: fixed !important;
        top: 0 !important; left: 0 !important;
        width: 100% !important; height: 100% !important;
        background: rgba(0, 0, 0, 0.9) !important;
        backdrop-filter: blur(18px) !important;
        -webkit-backdrop-filter: blur(18px) !important;
        z-index: 999999999 !important;
        display: flex; /* Bỏ !important để JS có thể ẩn */
        align-items: center !important;
        justify-content: center !important;
        opacity: 1; /* Bỏ !important để JS có thể tạo hiệu ứng fade out */
        transition: opacity 0.5s ease !important;
        pointer-events: auto !important;
    }

    .gtlm-modal {
        background: #ffffff;
        width: 92%;
        max-width: 640px;
        max-height: 88vh;
        border-radius: 24px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: gtlmPop 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 30px 60px rgba(0,0,0,0.4);
    }

    @keyframes gtlmPop {
        from { transform: scale(0.82) translateY(20px); opacity: 0; }
        to   { transform: scale(1) translateY(0); opacity: 1; }
    }

    /* ── Header ── */
    .gtlm-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 32px 36px 28px;
        text-align: center;
        flex-shrink: 0;
    }

    .gtlm-header .gtlm-logo {
        font-size: 42px;
        display: block;
        margin-bottom: 10px;
    }

    .gtlm-header h1 {
        font-size: 26px;
        font-weight: 800;
        color: #ffffff;
        margin: 0 0 6px;
        letter-spacing: -0.5px;
    }

    .gtlm-header p {
        font-size: 14px;
        color: rgba(255,255,255,0.82);
        margin: 0;
        line-height: 1.5;
    }

    /* ── Tab Navigation ── */
    .gtlm-tabs {
        display: flex;
        background: #f7f7f8;
        border-bottom: 1px solid #e8e8ec;
        flex-shrink: 0;
    }

    .gtlm-tab {
        flex: 1;
        padding: 13px 8px;
        border: none;
        background: transparent;
        font-size: 13px;
        font-weight: 600;
        color: #888;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        border-bottom: 3px solid transparent;
        margin-bottom: -1px;
    }

    .gtlm-tab:hover { color: #667eea; background: rgba(102,126,234,0.05); }

    .gtlm-tab.active {
        color: #667eea;
        border-bottom-color: #667eea;
        background: #ffffff;
    }

    /* ── Content ── */
    .gtlm-body {
        flex: 1;
        overflow-y: auto;
        padding: 28px 36px;
        scroll-behavior: smooth;
    }

    .gtlm-body::-webkit-scrollbar { width: 5px; }
    .gtlm-body::-webkit-scrollbar-track { background: transparent; }
    .gtlm-body::-webkit-scrollbar-thumb { background: #ddd; border-radius: 10px; }

    .gtlm-panel { display: none; }
    .gtlm-panel.active { display: block; }

    /* ── Panel 1: Giới thiệu ── */
    .gtlm-intro-hero {
        text-align: center;
        margin-bottom: 24px;
    }

    .gtlm-intro-hero h2 {
        font-size: 20px;
        font-weight: 700;
        color: #2d2d3a;
        margin: 0 0 10px;
    }

    .gtlm-intro-hero p {
        font-size: 14.5px;
        color: #666;
        line-height: 1.7;
        margin: 0;
    }

    .gtlm-features {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-top: 22px;
    }

    .gtlm-feature-card {
        background: #f8f8ff;
        border: 1px solid #ebebff;
        border-radius: 14px;
        padding: 16px;
        text-align: center;
    }

    .gtlm-feature-card .feat-icon {
        font-size: 28px;
        display: block;
        margin-bottom: 8px;
    }

    .gtlm-feature-card h4 {
        font-size: 13px;
        font-weight: 700;
        color: #3a3a5c;
        margin: 0 0 4px;
    }

    .gtlm-feature-card p {
        font-size: 12px;
        color: #888;
        margin: 0;
        line-height: 1.4;
    }

    .gtlm-currency-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: linear-gradient(135deg, #667eea15, #764ba215);
        border: 1px solid #c8c0f0;
        border-radius: 50px;
        padding: 10px 20px;
        margin: 20px auto 0;
        font-size: 14px;
        font-weight: 600;
        color: #5c4faa;
        width: fit-content;
        display: flex;
    }

    /* ── Bot Tab ── */
    .gtlm-bot-hero {
        text-align: center;
        margin-bottom: 20px;
    }

    .gtlm-bot-hero-icon { font-size: 40px; display: block; margin-bottom: 10px; }

    .gtlm-bot-hero h2 {
        font-size: 20px;
        font-weight: 700;
        color: #2d2d3a;
        margin: 0 0 6px;
    }

    .gtlm-bot-hero p { font-size: 13.5px; color: #888; margin: 0; }

    .gtlm-bot-intro {
        font-size: 13.5px;
        color: #555;
        line-height: 1.7;
        margin: 0 0 18px;
        background: #f8f7ff;
        border: 1px solid #e4e0ff;
        border-radius: 12px;
        padding: 14px 16px;
    }

    .gtlm-bot-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-bottom: 16px;
    }

    .gtlm-bot-card {
        background: #faf9ff;
        border: 1px solid #eeebff;
        border-radius: 12px;
        padding: 12px;
        display: flex;
        gap: 10px;
        align-items: flex-start;
    }

    .bot-card-icon { font-size: 20px; flex-shrink: 0; margin-top: 1px; }

    .gtlm-bot-card h5 {
        font-size: 12.5px;
        font-weight: 700;
        color: #3a2f7a;
        margin: 0 0 3px;
    }

    .gtlm-bot-card p {
        font-size: 11.5px;
        color: #888;
        margin: 0;
        line-height: 1.45;
    }

    .gtlm-bot-notice {
        background: #eef9ee;
        border: 1px solid #c4eac4;
        border-radius: 10px;
        padding: 12px 14px;
        display: flex;
        gap: 10px;
        align-items: flex-start;
        font-size: 12.5px;
        color: #3a6a3a;
        line-height: 1.6;
    }

    .notice-dot {
        width: 8px;
        height: 8px;
        background: #4caf50;
        border-radius: 50%;
        flex-shrink: 0;
        margin-top: 5px;
    }

    @media (max-width: 480px) {
        .gtlm-bot-grid { grid-template-columns: 1fr; }
    }

    /* ── Panel 2: Danh sách game ── */
    .gtlm-game-category {
        margin-bottom: 22px;
    }

    .gtlm-game-category h3 {
        font-size: 13px;
        font-weight: 700;
        color: #999;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin: 0 0 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .gtlm-game-category h3::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #eee;
    }

    .gtlm-game-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .gtlm-game-tag {
        background: #f3f2ff;
        border: 1px solid #dddaff;
        color: #5a53aa;
        border-radius: 8px;
        padding: 6px 12px;
        font-size: 13px;
        font-weight: 500;
    }

    .gtlm-game-tag.viet {
        background: #fff3f0;
        border-color: #ffd5cc;
        color: #c0442a;
    }

    .gtlm-game-tag.casual {
        background: #f0fff4;
        border-color: #c3f0d0;
        color: #2a7a4a;
    }

    .gtlm-game-tag.special {
        background: #fffbf0;
        border-color: #fde9a0;
        color: #8a6a10;
    }

    /* ── Panel 3: Credits ── */
    .gtlm-credit-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 11px 0;
        border-bottom: 1px solid #f2f2f2;
        gap: 12px;
    }

    .gtlm-credit-row:last-child { border-bottom: none; }

    .gtlm-credit-name {
        font-size: 14px;
        font-weight: 600;
        color: #2d2d3a;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .gtlm-credit-name span.icon { font-size: 18px; }

    .gtlm-credit-desc {
        font-size: 12.5px;
        color: #999;
        text-align: right;
        flex-shrink: 0;
    }

    .gtlm-credit-link {
        font-size: 11px;
        color: #667eea;
        text-decoration: none;
        display: block;
        margin-top: 2px;
    }

    .gtlm-credit-link:hover { text-decoration: underline; }

    .gtlm-made-badge {
        background: #f8f7ff;
        border: 1px solid #e4e0ff;
        border-radius: 12px;
        padding: 14px 18px;
        text-align: center;
        margin-top: 20px;
        font-size: 13px;
        color: #888;
        line-height: 1.6;
    }

    .gtlm-made-badge b { color: #667eea; }

    /* ── Panel 4: Quy tắc ── */
    .gtlm-rule-item {
        display: flex;
        gap: 14px;
        margin-bottom: 16px;
        align-items: flex-start;
    }

    .gtlm-rule-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .gtlm-rule-icon.green  { background: #eeffee; }
    .gtlm-rule-icon.red    { background: #fff0f0; }
    .gtlm-rule-icon.blue   { background: #f0f3ff; }
    .gtlm-rule-icon.yellow { background: #fffbee; }

    .gtlm-rule-text h4 {
        font-size: 14px;
        font-weight: 700;
        color: #2d2d3a;
        margin: 0 0 3px;
    }

    .gtlm-rule-text p {
        font-size: 13px;
        color: #777;
        margin: 0;
        line-height: 1.5;
    }

    .gtlm-disclaimer {
        background: #fff8ee;
        border: 1px solid #fde8b0;
        border-radius: 12px;
        padding: 14px 16px;
        font-size: 13px;
        color: #9a7020;
        line-height: 1.6;
        margin-top: 20px;
    }

    /* ── Footer ── */
    .gtlm-footer {
        padding: 20px 36px;
        background: #fafafa;
        border-top: 1px solid #eeeeee;
        flex-shrink: 0;
    }

    .gtlm-agree-label {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        margin-bottom: 14px;
        font-size: 13.5px;
        color: #555;
        user-select: none;
    }

    .gtlm-agree-label input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: #667eea;
        flex-shrink: 0;
    }

    .gtlm-enter-btn {
        width: 100%;
        padding: 15px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 14px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
        opacity: 0.45;
        pointer-events: none;
        letter-spacing: 0.2px;
    }

    .gtlm-enter-btn.ready {
        opacity: 1;
        pointer-events: auto;
        box-shadow: 0 8px 20px rgba(102,126,234,0.35);
    }

    .gtlm-enter-btn.ready:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 28px rgba(102,126,234,0.5);
    }

    .gtlm-enter-btn.ready:active {
        transform: translateY(0);
    }

    /* ── Responsive ── */
    @media (max-width: 480px) {
        .gtlm-header { padding: 24px 20px 20px; }
        .gtlm-body   { padding: 22px 20px; }
        .gtlm-footer { padding: 16px 20px; }
        .gtlm-features { grid-template-columns: 1fr; }
        .gtlm-tab { font-size: 11px; padding: 11px 4px; }
    }
</style>

<div id="gtlm-overlay">
    <div class="gtlm-modal" role="dialog" aria-modal="true" aria-labelledby="gtlm-title">

        <!-- Header -->
        <div class="gtlm-header">
            <span class="gtlm-logo">🎮</span>
            <h1 id="gtlm-title">Giải Trí Lành Mạnh</h1>
            <p>Nền tảng giải trí miễn phí — Không  Gtlm thật, không áp lực.<br>Vui chơi có trách nhiệm cùng cộng đồng.</p>
        </div>

        <!-- Tabs -->
        <div class="gtlm-tabs" role="tablist">
            <button class="gtlm-tab active" role="tab" aria-selected="true"  data-tab="intro">🏠 Giới thiệu</button>
            <button class="gtlm-tab"        role="tab" aria-selected="false" data-tab="games">🎲 Trò chơi</button>
            <button class="gtlm-tab"        role="tab" aria-selected="false" data-tab="credits">🛠️ Credits</button>
            <button class="gtlm-tab"        role="tab" aria-selected="false" data-tab="rules">📜 Quy tắc</button>
            <button class="gtlm-tab"        role="tab" aria-selected="false" data-tab="bots">🤖 Bot</button>
        </div>

        <!-- Body -->
        <div class="gtlm-body">

            <!-- Tab 1: Giới thiệu -->
            <div class="gtlm-panel active" id="tab-intro">
                <div class="gtlm-intro-hero">
                    <h2>Chào mừng đến với GTLM!</h2>
                    <p>
                        GTLM là nền tảng <strong>giải trí game hoàn toàn miễn phí</strong>, được xây dựng với mục đích
                        mang lại trải nghiệm vui chơi lành mạnh, an toàn cho mọi người.
                        <br><br>
                        <i style="color: #667eea;">⚠️ <b>Lưu ý quan trọng:</b> Dự án được phát triển phi lợi nhuận, không có ý định quảng bá, thương mại hóa hay kiếm tiền từ người dùng dưới bất kỳ hình thức nào.</i>
                        <br><br>
                       
                    </p>
                </div>

                <div class="gtlm-features">
                    <div class="gtlm-feature-card">
                        <span class="feat-icon">🎮</span>
                        <h4>50+ Trò Chơi</h4>
                        <p>Casino quốc tế và nhiều hơn nữa</p>
                    </div>
                    <div class="gtlm-feature-card">
                        <span class="feat-icon">🏆</span>
                        <h4>Xếp Hạng & Guild</h4>
                        <p>Thi đấu, gia nhập bang hội và leo bảng xếp hạng</p>
                    </div>
                    <div class="gtlm-feature-card">
                        <span class="feat-icon">🎁</span>
                        <h4>Phần Thưởng Hàng Ngày</h4>
                        <p>Điểm danh, nhiệm vụ, Battle Pass và sự kiện đặc biệt</p>
                    </div>
                    <div class="gtlm-feature-card">
                        <span class="feat-icon">💬</span>
                        <h4>Cộng Đồng Sôi Động</h4>
                        <p>Chat, kết bạn, Social Feed và Guild War hàng tuần</p>
                    </div>
                </div>

                <div class="gtlm-currency-badge">
                    <span>💰</span>
                    <span> Gtlm trong game: <strong>GTLM (Giải Trí Lành Mạnh)</strong> — hoàn toàn ảo, không có giá trị quy đổi thực tế</span>
                </div>
            </div>

            <!-- Tab 5: Bot -->
            <div class="gtlm-panel" id="tab-bots">

                <div class="gtlm-bot-hero">
                    <span class="gtlm-bot-hero-icon">🤖</span>
                    <h2>Hệ Thống Bot Tự Động</h2>
                    <p>Website có sử dụng Bot — đây là điều bạn cần biết trước khi chơi</p>
                </div>

                <p class="gtlm-bot-intro">
                    Để tạo ra một cộng đồng <strong>sôi động ngay từ đầu</strong>, GTLM sử dụng hệ thống Bot tự động
                    hoạt động song song với người chơi thật. Bot được lập trình để <strong>mô phỏng hành vi người dùng thực tế</strong>
                    một cách tự nhiên và minh bạch.
                </p>

                <div class="gtlm-bot-grid">
                    <div class="gtlm-bot-card">
                        <span class="bot-card-icon">🎮</span>
                        <div>
                            <h5>Chơi game cùng bạn</h5>
                            <p>Bot tham gia các ván cược, tạo ra lịch sử game và làm cho bảng xếp hạng luôn có dữ liệu</p>
                        </div>
                    </div>
                    <div class="gtlm-bot-card">
                        <span class="bot-card-icon">💬</span>
                        <div>
                            <h5>Tương tác trên chat</h5>
                            <p>Bot nhắn tin trên kênh chat tổng, phản hồi tin nhắn của người chơi và tạo không khí cộng đồng</p>
                        </div>
                    </div>
                    <div class="gtlm-bot-card">
                        <span class="bot-card-icon">📢</span>
                        <div>
                            <h5>Đăng bài Social Feed</h5>
                            <p>Bot đăng trạng thái, thả tim và bình luận trên bảng tin để tạo sự kiện trong cộng đồng</p>
                        </div>
                    </div>
                    <div class="gtlm-bot-card">
                        <span class="bot-card-icon">🤝</span>
                        <div>
                            <h5>Kết bạn & Guild</h5>
                            <p>Bot chủ động gửi lời mời kết bạn và tham gia Bang hội, Guild War cùng người chơi thật</p>
                        </div>
                    </div>
                    <div class="gtlm-bot-card">
                        <span class="bot-card-icon">🎁</span>
                        <div>
                            <h5>Nhận thưởng hàng ngày</h5>
                            <p>Bot tự điểm danh, quay vòng quay và hoàn thành nhiệm vụ như người chơi thật</p>
                        </div>
                    </div>
                    <div class="gtlm-bot-card">
                        <span class="bot-card-icon">⚔️</span>
                        <div>
                            <h5>Tham gia sự kiện</h5>
                            <p>Bot góp mặt trong Guild War, World Boss và Jackpot để các sự kiện luôn có người tham gia</p>
                        </div>
                    </div>
                </div>

                <div class="gtlm-bot-notice">
                    <span class="notice-dot"></span>
                    <div>
                        <strong>Minh bạch 100%:</strong> Bot tại GTLM <em>không phải</em> để gian lận hay tạo lợi thế tài chính.
                        Mục đích duy nhất là <strong>duy trì sự sôi động của cộng đồng</strong> và đảm bảo bạn luôn có
                        người để tương tác, kể cả khi số lượng người chơi thật còn ít.
                        Tài sản Bot <strong>không ảnh hưởng</strong> đến phần thưởng hay xếp hạng của bạn.
                    </div>
                </div>
            </div>

            <!-- Tab 2: Danh sách game -->
            <div class="gtlm-panel" id="tab-games">

                <div class="gtlm-game-category">
                    <h3>🃏 Casino Quốc Tế</h3>
                    <div class="gtlm-game-tags">
                        <span class="gtlm-game-tag">Baccarat</span>
                        <span class="gtlm-game-tag">Blackjack</span>
                        <span class="gtlm-game-tag">Poker</span>
                        <span class="gtlm-game-tag">Texas Hold'em</span>
                        <span class="gtlm-game-tag">Roulette</span>
                        <span class="gtlm-game-tag">Sicbo</span>
                        <span class="gtlm-game-tag">Craps</span>
                        <span class="gtlm-game-tag">Pai Gow</span>
                        <span class="gtlm-game-tag">Dragon Tiger</span>
                        <span class="gtlm-game-tag">Caribbean Stud</span>
                        <span class="gtlm-game-tag">Let It Ride</span>
                        <span class="gtlm-game-tag">Pontoon</span>
                        <span class="gtlm-game-tag">Red Dog</span>
                        <span class="gtlm-game-tag">Video Poker</span>
                        <span class="gtlm-game-tag">Three Card</span>
                        <span class="gtlm-game-tag">War</span>
                        <span class="gtlm-game-tag">Mahjong</span>
                        <span class="gtlm-game-tag">Fan Tan</span>
                    </div>
                </div>

                <div class="gtlm-game-category">
                    <h3 style="color:#c0442a">GAME HOT</h3>
                    <div class="gtlm-game-tags">
                        <span class="gtlm-game-tag viet">Thế Giới Linh Thú</span>
                        <span class="gtlm-game-tag viet">Xanh Đỏ Đối Kháng</span>
                        <span class="gtlm-game-tag viet">Trận Địa Trắng Đỏ</span>
                        <span class="gtlm-game-tag viet">Đại Chiến Thần Kê</span>
                        <span class="gtlm-game-tag viet">Sâm Lốc</span>
                        <span class="gtlm-game-tag viet">Ba Lá (Banharc)</span>
                        <span class="gtlm-game-tag viet">Tú Sắc</span>
                        <span class="gtlm-game-tag viet">Đua Ngựa</span>
                        <span class="gtlm-game-tag viet">Rút Thăm</span>
                        <span class="gtlm-game-tag viet">Hộp Mù</span>
                        <span class="gtlm-game-tag viet">Vietlott</span>
                    </div>
                </div>

                <div class="gtlm-game-category">
                    <h3 style="color:#2a7a4a">🎯 Casual & Skill Game</h3>
                    <div class="gtlm-game-tags">
                        <span class="gtlm-game-tag casual">Crash</span>
                        <span class="gtlm-game-tag casual">Mines</span>
                        <span class="gtlm-game-tag casual">Plinko</span>
                        <span class="gtlm-game-tag casual">Limbo</span>
                        <span class="gtlm-game-tag casual">HiLo</span>
                        <span class="gtlm-game-tag casual">Tower</span>
                        <span class="gtlm-game-tag casual">Dice</span>
                        <span class="gtlm-game-tag casual">Keno</span>
                        <span class="gtlm-game-tag casual">Coin Flip</span>
                        <span class="gtlm-game-tag casual">Number Guess</span>
                        <span class="gtlm-game-tag casual">Rock Paper Scissors</span>
                        <span class="gtlm-game-tag casual">Minesweeper</span>
                        <span class="gtlm-game-tag casual">Yahtzee</span>
                    </div>
                </div>

                <div class="gtlm-game-category">
                    <h3 style="color:#8a6a10">✨ Đặc Biệt & Sự Kiện</h3>
                    <div class="gtlm-game-tags">
                        <span class="gtlm-game-tag special">Slot Machine</span>
                        <span class="gtlm-game-tag special">Mega Spin</span>
                        <span class="gtlm-game-tag special">Lucky Wheel</span>
                        <span class="gtlm-game-tag special">Scratch Card</span>
                        <span class="gtlm-game-tag special">Lottery</span>
                        <span class="gtlm-game-tag special">Community Lottery</span>
                        <span class="gtlm-game-tag special">Battle Royale</span>
                        <span class="gtlm-game-tag special">JoJo Battle</span>
                        <span class="gtlm-game-tag special">Bingo</span>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Credits -->
            <div class="gtlm-panel" id="tab-credits">

                <div class="gtlm-credit-row">
                    <div class="gtlm-credit-name"><span class="icon">⚡</span> GSAP</div>
                    <div class="gtlm-credit-desc">
                        Hiệu ứng hoạt hình chuyên nghiệp
                        <a class="gtlm-credit-link" href="https://greensock.com/gsap/" target="_blank" rel="noopener">greensock.com →</a>
                    </div>
                </div>

                <div class="gtlm-credit-row">
                    <div class="gtlm-credit-name"><span class="icon">🌐</span> Three.js</div>
                    <div class="gtlm-credit-desc">
                        Hiệu ứng không gian 3D nền
                        <a class="gtlm-credit-link" href="https://threejs.org" target="_blank" rel="noopener">threejs.org →</a>
                    </div>
                </div>

                <div class="gtlm-credit-row">
                    <div class="gtlm-credit-name"><span class="icon">🔔</span> SweetAlert2</div>
                    <div class="gtlm-credit-desc">
                        Hệ thống thông báo thông minh
                        <a class="gtlm-credit-link" href="https://sweetalert2.github.io" target="_blank" rel="noopener">sweetalert2.github.io →</a>
                    </div>
                </div>

                <div class="gtlm-credit-row">
                    <div class="gtlm-credit-name"><span class="icon">🔤</span> Google Fonts</div>
                    <div class="gtlm-credit-desc">
                        Inter · Roboto · Outfit
                        <a class="gtlm-credit-link" href="https://fonts.google.com" target="_blank" rel="noopener">fonts.google.com →</a>
                    </div>
                </div>

                <div class="gtlm-credit-row">
                    <div class="gtlm-credit-name"><span class="icon">🎨</span> Font Awesome</div>
                    <div class="gtlm-credit-desc">
                        Bộ icon hiện đại
                        <a class="gtlm-credit-link" href="https://fontawesome.com" target="_blank" rel="noopener">fontawesome.com →</a>
                    </div>
                </div>

                <div class="gtlm-credit-row">
                    <div class="gtlm-credit-name"><span class="icon">🖱️</span> Sweezy Cursors</div>
                    <div class="gtlm-credit-desc">
                        Bộ con trỏ chuột độc đáo
                        <a class="gtlm-credit-link" href="https://sweezy-cursors.com" target="_blank" rel="noopener">sweezy-cursors.com →</a>
                    </div>
                </div>

                <div class="gtlm-credit-row">
                    <div class="gtlm-credit-name"><span class="icon">🖱️</span> RW-Designer</div>
                    <div class="gtlm-credit-desc">
                        Thư viện cursor cộng đồng
                        <a class="gtlm-credit-link" href="https://www.rw-designer.com/cursor-library" target="_blank" rel="noopener">rw-designer.com →</a>
                    </div>
                </div>

                <div class="gtlm-credit-row">
                    <div class="gtlm-credit-name"><span class="icon">🖱️</span> Custom Cursor</div>
                    <div class="gtlm-credit-desc">
                        Bộ sưu tập cursor miễn phí
                        <a class="gtlm-credit-link" href="https://custom-cursor.com/en/search/Free" target="_blank" rel="noopener">custom-cursor.com →</a>
                    </div>
                </div>

                <div class="gtlm-credit-row">
                    <div class="gtlm-credit-name"><span class="icon">🖼️</span> Kenney Assets</div>
                    <div class="gtlm-credit-desc">
                        Bộ bài tây (Playing Cards)
                        <a class="gtlm-credit-link" href="https://kenney.nl" target="_blank" rel="noopener">kenney.nl →</a>
                    </div>
                </div>

                <div class="gtlm-credit-row">
                    <div class="gtlm-credit-name"><span class="icon">📊</span> Chart.js</div>
                    <div class="gtlm-credit-desc">
                        Biểu đồ thống kê & dashboard
                        <a class="gtlm-credit-link" href="https://chartjs.org" target="_blank" rel="noopener">chartjs.org →</a>
                    </div>
                </div>

                <div class="gtlm-game-category" style="margin-top: 25px; margin-bottom: 10px;">
                    <h3 style="color:#764ba2">🎵 Âm nhạc & Hiệu ứng</h3>
                </div>

                <div class="gtlm-credit-row">
                    <div class="gtlm-credit-name"><span class="icon">🎶</span> YouTube Audio Library</div>
                    <div class="gtlm-credit-desc">
                        Nhạc nền trò chơi & livestream
                        <a class="gtlm-credit-link" href="https://www.youtube.com/audiolibrary" target="_blank" rel="noopener">youtube.com →</a>
                    </div>
                </div>

                <div class="gtlm-credit-row">
                    <div class="gtlm-credit-name"><span class="icon">🔊</span> Pixabay Music / Mixkit</div>
                    <div class="gtlm-credit-desc">
                        Âm thanh hiệu ứng (SFX) miễn phí
                        <a class="gtlm-credit-link" href="https://pixabay.com/music/" target="_blank" rel="noopener">pixabay.com →</a>
                    </div>
                </div>

                <div class="gtlm-credit-row">
                    <div class="gtlm-credit-name"><span class="icon">🎹</span> Bensound / Zapsplat</div>
                    <div class="gtlm-credit-desc">
                        Tài nguyên âm thanh chất lượng cao
                        <a class="gtlm-credit-link" href="https://www.bensound.com" target="_blank" rel="noopener">bensound.com →</a>
                    </div>
                </div>

                <div class="gtlm-credit-row">
                    <div class="gtlm-credit-name"><span class="icon">🎨</span> Freepik / Unsplash</div>
                    <div class="gtlm-credit-desc">
                        Tài nguyên đồ họa & Hình ảnh
                        <a class="gtlm-credit-link" href="https://freepik.com" target="_blank" rel="noopener">freepik.com →</a>
                    </div>
                </div>

                <div class="gtlm-game-category" style="margin-top: 25px; margin-bottom: 10px;">
                    <h3 style="color:#2d2d3a">🤖 Trí Tuệ Nhân Tạo (AI)</h3>
                </div>

                <div class="gtlm-credit-row">
                    <div class="gtlm-credit-name"><span class="icon">✨</span> Gemini / Grok / Claude / ChatGPT</div>
                    <div class="gtlm-credit-desc">
                        Hỗ trợ xây dựng Logic Game, Web Audio API (Slot Machine) và tối ưu hóa hệ thống.
                    </div>
                </div>

                <div class="gtlm-credit-row">
                    <div class="gtlm-credit-name"><span class="icon">🖼️</span> AI Image Generation</div>
                    <div class="gtlm-credit-desc">
                        Các hình ảnh nền (BR, Đá Gà, Tú Sắc) và Assets sự kiện được tạo bởi AI.
                    </div>
                </div>

                <div class="gtlm-game-category" style="margin-top: 25px; margin-bottom: 10px;">
                    <h3 style="color:#2d2d3a">🌐 Tài Nguyên Khác</h3>
                </div>

                <div class="gtlm-credit-row">
                    <div class="gtlm-credit-name"><span class="icon">👾</span> Itch.io Assets</div>
                    <div class="gtlm-credit-desc">
                        Hình ảnh Boss Thế Giới (World Boss)
                        <a class="gtlm-credit-link" href="https://itch.io" target="_blank" rel="noopener">itch.io →</a>
                    </div>
                </div>

                <div class="gtlm-credit-row">
                    <div class="gtlm-credit-name"><span class="icon">🏁</span> Transparent Textures</div>
                    <div class="gtlm-credit-desc">
                        Họa tiết nền giao diện
                        <a class="gtlm-credit-link" href="https://www.transparenttextures.com" target="_blank" rel="noopener">transparenttextures.com →</a>
                    </div>
                </div>

                <div class="gtlm-made-badge">
                    Được xây dựng với ❤️ bởi đội phát triển <b>GTLM</b>.<br>
                    Một số thư viện, tài nguyên hình ảnh và assets được sử dụng theo giấy phép của tác giả gốc.<br>
                    Xin cảm ơn các dự án mã nguồn mở và cộng đồng sáng tạo.
                </div>
            </div>

            <!-- Tab 4: Quy tắc -->
            <div class="gtlm-panel" id="tab-rules">

                <div class="gtlm-rule-item">
                    <div class="gtlm-rule-icon green">✅</div>
                    <div class="gtlm-rule-text">
                        <h4>Chơi công bằng</h4>
                        <p>Nghiêm cấm sử dụng mọi công cụ gian lận, cheat, hack hay khai thác lỗi hệ thống. Vi phạm sẽ bị khóa tài khoản vĩnh viễn.</p>
                    </div>
                </div>

                <div class="gtlm-rule-item">
                    <div class="gtlm-rule-icon blue">💬</div>
                    <div class="gtlm-rule-text">
                        <h4>Ứng xử văn minh</h4>
                        <p>Giao tiếp lịch sự, tôn trọng người chơi khác. Không spam, chửi bới, quảng cáo hoặc gây rối trên kênh chat tổng.</p>
                    </div>
                </div>

                <div class="gtlm-rule-item">
                    <div class="gtlm-rule-icon red">🔒</div>
                    <div class="gtlm-rule-text">
                        <h4>Bảo mật tài khoản</h4>
                        <p>Không chia sẻ thông tin đăng nhập với người khác. Dữ liệu cá nhân được mã hóa và bảo mật theo tiêu chuẩn AES-256.</p>
                    </div>
                </div>

                <div class="gtlm-rule-item">
                    <div class="gtlm-rule-icon yellow">🎯</div>
                    <div class="gtlm-rule-text">
                        <h4>Mục đích giải trí</h4>
                        <p>GTLM là nền tảng giải trí thuần túy. Đồng GTLM không có giá trị  Gtlm thực tế và không thể quy đổi ra  Gtlm mặt.</p>
                    </div>
                </div>

                <div class="gtlm-disclaimer">
                    ⚠️ <strong>Miễn trừ trách nhiệm:</strong> Chúng tôi không chịu trách nhiệm về các sự cố do lỗi mạng, thiết bị người dùng hoặc trường hợp bất khả kháng. Mọi quyết định trong game là trách nhiệm của người chơi.
                </div>
            </div>

        </div><!-- /gtlm-body -->

        <!-- Footer -->
        <div class="gtlm-footer">
            <label class="gtlm-agree-label" for="gtlm-checkbox">
                <input type="checkbox" id="gtlm-checkbox">
                <span>Tôi đã đọc, hiểu và đồng ý với các điều khoản sử dụng của GTLM.</span>
            </label>
            <button class="gtlm-enter-btn" id="gtlm-enter-btn" disabled>
                Bắt Đầu Trải Nghiệm 🚀
            </button>
        </div>

    </div><!-- /gtlm-modal -->
</div><!-- /gtlm-overlay -->

<script>
(function () {
    var overlay   = document.getElementById('gtlm-overlay');
    var checkbox  = document.getElementById('gtlm-checkbox');
    var enterBtn  = document.getElementById('gtlm-enter-btn');
    var tabs      = document.querySelectorAll('.gtlm-tab');
    var panels    = document.querySelectorAll('.gtlm-panel');

    // ── 24-hour visit check ──
    var lastSeen = localStorage.getItem('gtlm_welcomed_time');
    var now = new Date().getTime();
    var expiry = 24 * 60 * 60 * 1000; // 24 hours in ms

    /* Tạm thời tắt check 24h để test - sẽ hiện mỗi lần load trang */
    if (lastSeen && (now - lastSeen < expiry)) {
        overlay.style.display = 'none';
        // return; // Commented for testing
    }

    // Lock body scroll
    document.body.style.overflow = 'hidden';

    // ── Tab switching ──
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var target = this.getAttribute('data-tab');

            tabs.forEach(function (t) {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });
            panels.forEach(function (p) { p.classList.remove('active'); });

            this.classList.add('active');
            this.setAttribute('aria-selected', 'true');
            var panel = document.getElementById('tab-' + target);
            if (panel) panel.classList.add('active');
        });
    });

    // ── Checkbox toggle ──
    checkbox.addEventListener('change', function () {
        if (this.checked) {
            enterBtn.classList.add('ready');
            enterBtn.removeAttribute('disabled');
        } else {
            enterBtn.classList.remove('ready');
            enterBtn.setAttribute('disabled', '');
        }
    });

    // ── Enter button ──
    enterBtn.addEventListener('click', function () {
        if (!checkbox.checked) return;

        overlay.style.opacity = '0';
        overlay.style.pointerEvents = 'none';
        document.body.style.overflow = ''; // Mở khóa cuộn trang

        setTimeout(function () {
            overlay.style.display = 'none';
            localStorage.setItem('gtlm_welcomed', '1');

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '🎉 Chào mừng bạn!',
                    text: 'Chúc bạn có những giờ giải trí thật vui vẻ tại GTLM!',
                    icon: 'success',
                    timer: 2200,
                    showConfirmButton: false,
                    timerProgressBar: true
                });
            }
        }, 480);
    });
})();
</script>