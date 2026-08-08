<?php
/**
 * Smart AI, Red Envelope & Sound FX Hub — Master Tester Dashboard (4A + 4B + 4C)
 * [NEW FILE] - Bảng kiểm thử hợp nhất toàn diện Hướng 4 cho Casino Platform GTLM
 * Hoạt động độc lập 100%, không ghi đè file hệ thống cũ
 */

session_start();
require_once __DIR__ . '/db_connect.php';

// Xử lý gửi tin nhắn giả lập cục bộ không lo rate-limit hay chưa đăng nhập (4A)
if (isset($_GET['action']) && $_GET['action'] === 'mock_send') {
    header('Content-Type: application/json; charset=utf-8');
    $msg = trim($_POST['message'] ?? '');
    if (!empty($msg)) {
        $userId = isset($_SESSION['Iduser']) ? (int)$_SESSION['Iduser'] : 1;
        $username = isset($_SESSION['Name']) ? $_SESSION['Name'] : 'Admin Tester';
        $avatar = isset($_SESSION['Avatar']) ? $_SESSION['Avatar'] : 'img/avatar_default.png';
        
        $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("isss", $userId, $username, $msg, $avatar);
            $stmt->execute();
            $stmt->close();
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

// Kiểm tra tình trạng bảng DB cho 4A và 4B
$tableBotReady = false;
$checkBot = $conn->query("SHOW TABLES LIKE 'bot_smart_chat_logs'");
if ($checkBot && $checkBot->num_rows > 0) $tableBotReady = true;

$tableEnvReady = false;
$checkEnv = $conn->query("SHOW TABLES LIKE 'red_envelopes'");
if ($checkEnv && $checkEnv->num_rows > 0) $tableEnvReady = true;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>⚡ Master Hub Tester: 4A AI Chat + 4B Mưa Lì Xì + 4C Sound FX</title>
    <link rel="stylesheet" href="assets/css/sound-fx-hub.css">
    <link rel="stylesheet" href="assets/css/red-envelope.css">
    <script src="assets/js/sound-fx-hub.js"></script>
    <script src="assets/js/red-envelope.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #311042 100%);
            color: #f8fafc;
            margin: 0;
            padding: 30px 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1250px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 25px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.6);
        }
        .header h1 {
            font-size: 36px;
            margin: 0 0 10px 0;
            background: linear-gradient(to right, #60a5fa, #c084fc, #fbbf24, #f87171);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 900;
        }
        .header p { color: #cbd5e1; font-size: 16px; margin: 0; }
        
        .alert-box {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid #ef4444;
            color: #fca5a5;
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-box.success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid #22c55e;
            color: #86efac;
        }

        /* Tabs Điều Hướng Hợp Nhất */
        .tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .tab-btn {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #cbd5e1;
            padding: 14px 24px;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tab-btn:hover, .tab-btn.active {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: #ffffff;
            border-color: #93c5fd;
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.5);
            transform: translateY(-2px);
        }
        .tab-btn.tab-gold.active {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border-color: #fef08a;
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.6);
        }
        .tab-btn.tab-red.active {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            border-color: #fca5a5;
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.6);
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        @media (max-width: 850px) { .grid-2 { grid-template-columns: 1fr; } }

        .card {
            background: rgba(30, 41, 59, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.4);
        }
        .card h2 {
            font-size: 20px;
            margin-top: 0;
            color: #60a5fa;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card h2.gold { color: #fbbf24; }
        .card h2.red { color: #f87171; }

        .btn-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 15px;
        }
        .btn {
            background: linear-gradient(135deg, #334155, #1e293b);
            color: white;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 12px;
            padding: 12px 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-align: center;
        }
        .btn:hover {
            transform: translateY(-2px);
            border-color: #60a5fa;
            box-shadow: 0 6px 18px rgba(96, 165, 250, 0.3);
            background: linear-gradient(135deg, #475569, #334155);
        }
        .btn.btn-gold {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border-color: #fef08a;
            color: #451a03;
        }
        .btn.btn-gold:hover {
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.5);
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
        }
        .btn.btn-red {
            background: linear-gradient(135deg, #b91c1c, #dc2626);
            border-color: #fca5a5;
        }
        .btn.btn-red:hover {
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.5);
            background: linear-gradient(135deg, #ef4444, #b91c1c);
        }

        /* Log Master Console */
        .master-log-card {
            margin-top: 25px;
            background: #090d16;
            border: 1px solid #334155;
            border-radius: 16px;
            padding: 20px;
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.8);
        }
        .master-log-card h3 {
            margin: 0 0 12px 0;
            color: #38bdf8;
            font-size: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .log-box {
            height: 240px;
            overflow-y: auto;
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 13px;
            color: #cbd5e1;
        }
        .log-item {
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #1e293b;
            line-height: 1.4;
        }
        .log-item span.time { color: #fbbf24; font-weight: bold; }
        .log-item span.tag-ai { color: #c084fc; font-weight: bold; }
        .log-item span.tag-env { color: #f87171; font-weight: bold; }
        .log-item span.tag-snd { color: #60a5fa; font-weight: bold; }

        .env-item {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid #475569;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚡ GTLM Master Hub Tester (4A + 4B + 4C)</h1>
            <p>Bảng điều khiển hợp nhất: Kiểm thử Trí Tuệ AI Ngữ Cảnh, Mưa Lì Xì Tranh Lộc & Synthesizer Âm Thanh</p>
            <div style="margin-top: 18px; display:flex; justify-content:center; gap:12px; flex-wrap:wrap;">
                <a href="chat.php" class="btn btn-gold" style="display:inline-flex; width:auto; text-decoration:none; padding:10px 22px;" target="_blank">💬 Mở Kênh Chat Thế Giới</a>
                <a href="plinko_royale_v3.php" class="btn" style="display:inline-flex; width:auto; text-decoration:none; padding:10px 22px; background:linear-gradient(135deg,#f59e0b,#d97706); color:#000; border-color:#fde047; font-weight:900;" target="_blank">🎰 Đấu Trường Plinko Royale V3</a>
                <a href="my_lounge.php" class="btn" style="display:inline-flex; width:auto; text-decoration:none; padding:10px 22px; background:linear-gradient(135deg,#8b5cf6,#6d28d9); color:#fff; border-color:#c084fc; font-weight:700;" target="_blank">🏡 Biệt Thự Hoàng Gia</a>
            </div>
        </div>

        <?php if (!$tableBotReady || !$tableEnvReady): ?>
        <div class="alert-box">
            <b>⚠️ Cảnh báo Database:</b><br>
            <?php if (!$tableBotReady) echo "- Bảng `bot_smart_chat_logs` (4A) chưa được tạo.<br>"; ?>
            <?php if (!$tableEnvReady) echo "- Bảng `red_envelopes` và `red_envelope_claims` (4B) chưa được tạo.<br>"; ?>
            <i>Vui lòng copy và chạy các block SQL đã gửi trong phpMyAdmin để toàn bộ tính năng lưu trữ & lịch sử hoạt động chính xác!</i>
        </div>
        <?php else: ?>
        <div class="alert-box success">
            <b>✅ Tất cả bảng Database (4A & 4B) đã sẵn sàng 100%!</b> Bạn có thể trải nghiệm toàn diện các tính năng bên dưới.
        </div>
        <?php endif; ?>

        <!-- Tabs Điều Hướng -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('tab-ai', this)">🤖 4A: AI Bot Trả Lời Ngữ Cảnh</button>
            <button class="tab-btn tab-red" onclick="switchTab('tab-env', this)">🧧 4B: Mưa Lì Xì & Tranh Lộc AI</button>
            <button class="tab-btn tab-gold" onclick="switchTab('tab-sound', this)">🎵 4C: Sound FX Synthesizer</button>
        </div>

        <!-- TAB 1: 4A AI Bot Trả Lời Ngữ Cảnh -->
        <div id="tab-ai" class="tab-content active">
            <div class="grid-2">
                <div class="card">
                    <h2>🤖 Kịch Bản Tin Nhắn Ngữ Cảnh (4A)</h2>
                    <p style="color:#cbd5e1; font-size:14px;">Bấm chọn tin nhắn mẫu để gửi vào Kênh Chat Thế Giới và xem AI Bot lập tức phản hồi đúng ngữ cảnh:</p>
                    <div class="btn-grid">
                        <button class="btn" onclick="sendMockChat('Anh em ơi hôm nay làm ván gì thơm @Cụ Giáo')">👴 Gọi @Cụ Giáo</button>
                        <button class="btn" onclick="sendMockChat('Đại gia @Whale có kèo gì cho em húp GTLM với')">🐳 Gọi @Whale</button>
                        <button class="btn" onclick="sendMockChat('@Plinko hướng dẫn em cách nổ hũ x100 với anh')">🎰 Gọi @Plinko</button>
                        <button class="btn btn-gold" onclick="sendMockChat('Vừa nổ hũ 500 triệu GTLM bên Plinko sướng quá!')">🎉 Ngữ Cảnh Thắng/Nổ Hũ</button>
                        <button class="btn btn-red" onclick="sendMockChat('Đen quá bay màu sạch nick GTLM rồi buồn!')">😭 Ngữ Cảnh Thua/Cháy</button>
                        <button class="btn" onclick="sendMockChat('Tối nay nên chiến game Sâm Lốc hay Đua Ngựa đây?')">❓ Ngữ Cảnh Tư Vấn Kèo</button>
                    </div>

                    <div style="margin-top: 20px; border-top: 1px dashed rgba(255,255,255,0.15); padding-top: 15px;">
                        <label style="color:#cbd5e1; font-size:13px; font-weight:600;">✍️ Hoặc tự nhập tin nhắn tùy ý:</label>
                        <div style="display:flex; gap:10px; margin-top:8px;">
                            <input type="text" id="customMsg" placeholder="VD: @Cụ Giáo ơi cho em xin lộc..." style="flex:1; background:#0f172a; border:1px solid #475569; color:#f8fafc; padding:10px 14px; border-radius:10px; outline:none;">
                            <button class="btn btn-gold" style="padding:10px 18px;" onclick="sendCustomChat()">Gửi & Quét AI</button>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h2>⚡ Tiến Trình & Trạng Thái Trí Tuệ AI</h2>
                    <p style="color:#cbd5e1; font-size:14px; line-height:1.6;">
                        • Hệ thống AI tự động quét mỗi <b>12 giây</b> hoặc kích hoạt ngay tức thì sau khi bạn bấm gửi tin nhắn.<br>
                        • Sử dụng từ vựng chuẩn dự án (`Rule 5.3`): <i>húp GTLM, bay màu, ra chiêu, ván giao lưu, trận địa...</i><br>
                        • Kết quả và phản hồi của Bot sẽ hiển thị trực tiếp bên dưới Console & Kênh Chat Thế Giới.
                    </p>
                    <button class="btn" style="width:100%; margin-top:15px; background:linear-gradient(135deg,#3b82f6,#2563eb);" onclick="triggerSmartAIScan()">🔄 Quét Cưỡng Chế AI Bot Ngay Lúc Này</button>
                </div>
            </div>
        </div>

        <!-- TAB 2: 4B Mưa Lì Xì & Tranh Lộc AI -->
        <div id="tab-env" class="tab-content">
            <div class="grid-2">
                <div class="card">
                    <h2 class="red">🧧 Tạo Mưa Lì Xì Phát Lộc (4B)</h2>
                    <p style="color:#cbd5e1; font-size:14px;">Bấm để các Bot Đại Gia phát lì xì, tạo cơn mưa lộc 🧧 rơi lả tả trên toàn bộ màn hình:</p>
                    <div class="btn-grid">
                        <button class="btn btn-gold" onclick="createMockRain('Đại Gia Whale', 'https://api.dicebear.com/7.x/avataaars/svg?seed=whale&style=circle', 100000, 5, 'Bổn thiếu gia vừa húp Baccarat 1 Tỷ, phát lộc cho anh em!')">🐳 @Whale Phát 100K (5 bao)</button>
                        <button class="btn" onclick="createMockRain('Cụ Giáo', 'https://api.dicebear.com/7.x/avataaars/svg?seed=cugia&style=circle', 250000, 8, 'Tâm bất biến giữa dòng đời vạn biến, chúc đạo hữu may mắn!')">👴 @Cụ Giáo Phát 250K (8 bao)</button>
                        <button class="btn" style="background:linear-gradient(135deg,#7c3aed,#4c1d95);" onclick="createMockRain('Thánh Nổ Plinko', 'https://api.dicebear.com/7.x/avataaars/svg?seed=plinko&style=circle', 500000, 10, 'Plinko x100 rực rỡ! Ai tay nhanh thì húp!')">🎰 @Plinko Phát 500K (10 bao)</button>
                        <button class="btn btn-red" onclick="createMockRain('Admin Phát Lộc', 'img/avatar_default.png', 1000000, 20, 'Lì xì tri ân toàn thể cộng đồng GTLM hôm nay!')">👑 Admin Phát 1 Tỷ (20 bao)</button>
                    </div>
                </div>

                <div class="card">
                    <h2 class="red">⚡ Danh Sách Lì Xì Đang Kích Hoạt</h2>
                    <div id="envList" style="min-height:110px;">
                        <p style="color:#94a3b8; font-style:italic;">⏳ Đang rà quét bao lì xì từ server...</p>
                    </div>
                    <button class="btn btn-gold" style="width:100%; margin-top:12px;" onclick="loadActiveList(true)">🔄 Làm Mới & Cho Bot AI Nhảy Vào Giật Lộc</button>
                </div>
            </div>
        </div>

        <!-- TAB 3: 4C Sound FX Synthesizer -->
        <div id="tab-sound" class="tab-content">
            <div class="grid-2">
                <div class="card">
                    <h2 class="gold">🎵 Bộ Hiệu Ứng Hoàng Gia Synthesizer (4C)</h2>
                    <p style="color:#cbd5e1; font-size:14px;">Âm thanh tổng hợp trực tiếp bằng dao động tần số Web Audio API — mượt mà, không lag, không lỗi 404:</p>
                    <div class="btn-grid">
                        <button class="btn btn-gold" onclick="testSound('jackpot')">🎰 Nổ Hũ Jackpot x100</button>
                        <button class="btn btn-gold" style="background:linear-gradient(135deg,#10b981,#047857); border-color:#6ee7b7; color:white;" onclick="testSound('lottery')">🏆 Thắng Xổ Số / Giật Lì Xì</button>
                        <button class="btn btn-red" onclick="testSound('boss')">🐉 Boss Long Thần Gầm Rú</button>
                        <button class="btn" style="background:linear-gradient(135deg,#6366f1,#4338ca);" onclick="testSound('pvp')">⚔️ Kèn Thách Đấu PvP</button>
                        <button class="btn" onclick="testSound('spin')">🌀 Vòng Quay Lucky Spin</button>
                        <button class="btn" onclick="testSound('pop')">💬 Âm Báo Tin Nhắn / Bot Pop</button>
                    </div>
                </div>

                <div class="card">
                    <h2 class="gold">⚡ Điều Khiển Sóng Âm & Trạng Thái</h2>
                    <p style="color:#cbd5e1; font-size:14px; line-height:1.6;">
                        • Bấm bất kỳ nút âm thanh bên trái để kiểm tra tốc độ phản hồi tức thì.<br>
                        • Trạng thái âm thanh toàn cục được đồng bộ và hiển thị ở thanh widget góc dưới trái (`#gtlm-sound-widget`).<br>
                        • Sóng âm nhấp nháy động (`sound-waves`) sẽ reo theo từng dải tần của hiệu ứng!
                    </p>
                    <button class="btn btn-gold" style="width:100%; margin-top:20px;" onclick="SoundFXHub.toggleMute()">🔊 Bật / Tắt Âm Thanh Toàn Hệ Thống</button>
                </div>
            </div>
        </div>

        <!-- Master Console Log Hợp Nhất -->
        <div class="master-log-card">
            <h3>
                <span>⚡ MASTER CONSOLE LOG (4A + 4B + 4C)</span>
                <button class="btn" style="padding:4px 12px; font-size:12px; height:auto;" onclick="clearMasterLog()">🧹 Xóa Log</button>
            </h3>
            <div class="log-box" id="masterLog">
                <div class="log-item"><span class="time">[<?php echo date('H:i:s'); ?>]</span> <span class="tag-ai">[System]</span> Master Hub đã khởi tạo thành công 3 tiến trình: 4A (AI Bot), 4B (Mưa Lì Xì), 4C (Sound Synthesizer). Sẵn sàng kiểm thử!</div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabId, el) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            el.classList.add('active');
        }

        function appendLog(html, tagType = 'AI') {
            const box = document.getElementById('masterLog');
            if (!box) return;
            const item = document.createElement('div');
            item.className = 'log-item';
            let tagColor = 'tag-ai';
            if (tagType === 'ENV') tagColor = 'tag-env';
            if (tagType === 'SND') tagColor = 'tag-snd';
            item.innerHTML = `<span class="time">[${new Date().toLocaleTimeString()}]</span> <span class="${tagColor}">[${tagType}]</span> ${html}`;
            box.insertBefore(item, box.firstChild);
        }

        function clearMasterLog() {
            document.getElementById('masterLog').innerHTML = '';
        }

        // --- Hàm xử lý 4C Sound FX ---
        function testSound(type) {
            appendLog(`Kích hoạt phát âm thanh Synthesizer: <b>${type.toUpperCase()}</b>`, 'SND');
            if (typeof SoundFXHub === 'undefined') return;
            if (type === 'jackpot') SoundFXHub.playJackpot();
            else if (type === 'lottery') SoundFXHub.playLotteryWin();
            else if (type === 'boss') SoundFXHub.playBossRoar();
            else if (type === 'pvp') {
                if (typeof SoundFXHub.playPvpChallenge === 'function') SoundFXHub.playPvpChallenge();
                else if (typeof SoundFXHub.playPvpHorn === 'function') SoundFXHub.playPvpHorn();
            }
            else if (type === 'spin') SoundFXHub.playLuckySpin();
            else if (type === 'pop') SoundFXHub.playPop();
        }

        // --- Hàm xử lý 4A AI Bot ---
        async function sendMockChat(msgText) {
            appendLog(`Đang gửi tin nhắn mẫu: "<i>${msgText}</i>"...`, 'AI');
            try {
                const formData = new FormData();
                formData.append('message', msgText);
                await fetch('smart_hub_tester.php?action=mock_send', { method: 'POST', body: formData });
                setTimeout(triggerSmartAIScan, 500);
            } catch(e) {
                appendLog(`<b style="color:#ef4444">Lỗi gửi tin nhắn:</b> ${e.message}`, 'AI');
            }
        }

        async function sendCustomChat() {
            const input = document.getElementById('customMsg');
            if (!input.value.trim()) return;
            await sendMockChat(input.value.trim());
            input.value = '';
        }

        async function triggerSmartAIScan() {
            appendLog('Đang gọi tiến trình quét AI: <code>api_bot_smart_chat.php?action=scan</code>...', 'AI');
            try {
                const res = await fetch('api_bot_smart_chat.php?action=scan');
                const rawText = await res.text();
                let data = null;
                try { data = JSON.parse(rawText); } 
                catch (e) {
                    appendLog(`<b style="color:#ef4444">Lỗi phản hồi từ server:</b> ${rawText.substring(0, 150)}...`, 'AI');
                    return;
                }

                if (data && data.success && data.bot_scan) {
                    if (data.bot_scan.actions && data.bot_scan.actions.length > 0) {
                        data.bot_scan.actions.forEach(act => appendLog(`🔥 ${act}`, 'AI'));
                        if (typeof SoundFXHub !== 'undefined') SoundFXHub.playPop();
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: '🤖 AI Bot Đã Phản Hồi!',
                                text: 'Các Bot vừa trả lời tin nhắn của bạn trên Kênh Chat Thế Giới.',
                                icon: 'success', toast: true, position: 'top-end', timer: 3000, showConfirmButton: false
                            });
                        }
                    } else {
                        appendLog('✅ Quét hoàn tất: Không có ngữ cảnh mới nào chưa được xử lý.', 'AI');
                    }
                } else {
                    appendLog(`<b style="color:#ef4444">Thông báo AI:</b> ${data?.message || data?.bot_scan?.message || 'Chưa có hành động nào.'}`, 'AI');
                }
            } catch(e) {
                appendLog(`<b style="color:#ef4444">Lỗi kết nối:</b> ${e.message}`, 'AI');
            }
        }

        // --- Hàm xử lý 4B Red Envelope Rain ---
        async function createMockRain(senderName, senderAvatar, amount, count, msg) {
            appendLog(`Đang khởi tạo Mưa Lì Xì: <b>${senderName}</b> phát <b>${new Intl.NumberFormat().format(amount)} GTLM</b> (${count} bao)...`, 'ENV');
            try {
                const formData = new FormData();
                formData.append('is_mock', 'true');
                formData.append('mock_sender_name', senderName);
                formData.append('mock_sender_avatar', senderAvatar);
                formData.append('total_amount', amount);
                formData.append('total_count', count);
                formData.append('message', msg);

                const res = await fetch('api_red_envelope.php?action=create', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    appendLog(`✅ Phát lộc thành công! Mưa lì xì [LÌ XÌ #${data.envelope_id}] đang rơi trên toàn màn hình...`, 'ENV');
                    if (typeof SoundFXHub !== 'undefined') SoundFXHub.playLotteryWin();
                    setTimeout(() => loadActiveList(false), 500);
                } else {
                    appendLog(`<b style="color:#ef4444">Lỗi tạo lì xì:</b> ${data.message}`, 'ENV');
                }
            } catch(e) {
                appendLog(`<b style="color:#ef4444">Lỗi kết nối:</b> ${e.message}`, 'ENV');
            }
        }

        async function loadActiveList(triggerBot) {
            try {
                const res = await fetch('api_red_envelope.php?action=list');
                const data = await res.json();
                const listEl = document.getElementById('envList');

                if (!data.success || !data.table_ready) {
                    listEl.innerHTML = `<p style="color:#ef4444">⚠️ Bảng DB chưa sẵn sàng.</p>`;
                    return;
                }

                if (data.bot_action) {
                    appendLog(`🤖 ${data.bot_action}`, 'ENV');
                    if (typeof SoundFXHub !== 'undefined') SoundFXHub.playPop();
                }

                const list = data.envelopes || [];
                if (list.length === 0) {
                    listEl.innerHTML = `<p style="color:#94a3b8; font-style:italic;">Hiện tại không có lì xì nào đang kích hoạt. Hãy bấm nút tạo ở bên trái!</p>`;
                    return;
                }

                let html = '';
                list.forEach(env => {
                    html += `
                        <div class="env-item">
                            <div>
                                <b style="color:#fbbf24">🧧 ${env.sender_name}</b><br>
                                <small style="color:#cbd5e1">Còn: ${new Intl.NumberFormat().format(env.remaining_amount)} GTLM (${env.remaining_count}/${env.total_count} bao)</small>
                            </div>
                            <button class="btn btn-gold" style="padding:6px 12px; font-size:13px;" onclick='RedEnvelopeHub.open(${JSON.stringify(env)})'>Tranh Lộc</button>
                        </div>
                    `;
                });
                listEl.innerHTML = html;
            } catch(e) {
                appendLog(`<b style="color:#ef4444">Lỗi tải danh sách lì xì:</b> ${e.message}`, 'ENV');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadActiveList(false);
            setInterval(() => loadActiveList(false), 10000);
        });
    </script>
</body>
</html>
