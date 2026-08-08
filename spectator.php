<?php
/**
 * 📺 Spectator Mode v2.0 - Twitch Mini Upgrade
 * Nền tảng xem live và tương tác với các cao thủ Trận Địa.
 */
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['Iduser'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trận Địa Live | Twitch Mini Spectator</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #09090b;
            --sidebar-bg: #18181b;
            --twitch-purple: #9146ff;
            --primary: #6366f1;
            --gold: #fbbf24;
            --card-hover: #27272a;
        }

        body {
            background: var(--bg-dark);
            color: #efeff1;
            font-family: 'Inter', sans-serif;
            margin: 0;
            overflow: hidden;
            height: 100vh;
        }

        .twitch-layout {
            display: grid;
            grid-template-columns: 280px 1fr 340px;
            height: 100vh;
        }

        /* 👈 Sidebar: Recommended Channels */
        .sidebar {
            background: var(--sidebar-bg);
            border-right: 1px solid rgba(255,255,255,0.05);
            display: flex;
            flex-direction: column;
            padding: 20px 0;
        }
        .sidebar-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 0 20px;
            margin-bottom: 15px;
            color: #adadb8;
        }
        .channel-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .channel-item:hover { background: var(--card-hover); }
        .channel-avatar {
            width: 32px; height: 32px; border-radius: 50%; background: #3f3f46;
            border: 2px solid var(--twitch-purple);
        }
        .channel-info { flex: 1; overflow: hidden; }
        .channel-name { font-weight: 700; font-size: 14px; white-space: nowrap; text-overflow: ellipsis; }
        .channel-game { font-size: 12px; color: #adadb8; }
        .live-status { display: flex; align-items: center; gap: 4px; font-size: 12px; }
        .live-dot { width: 8px; height: 8px; background: #eb0400; border-radius: 50%; }

        /* 📺 Main View: Streamer Area */
        .main-view {
            padding: 20px;
            overflow-y: auto;
            background: #000;
        }
        .featured-player {
            width: 100%;
            aspect-ratio: 16/9;
            background: #1f1f23;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
            border: 2px solid rgba(145, 70, 255, 0.3);
            margin-bottom: 20px;
        }
        .live-label {
            position: absolute; top: 15px; left: 15px;
            background: #eb0400; color: #fff; padding: 2px 8px;
            border-radius: 4px; font-weight: 700; font-size: 13px;
        }
        .viewer-count {
            position: absolute; top: 15px; right: 15px;
            background: rgba(0,0,0,0.6); padding: 2px 8px; border-radius: 4px;
            font-size: 12px;
        }

        /* 💰 Bet-along Panel */
        .bet-along-panel {
            position: absolute; bottom: 20px; left: 20px; right: 20px;
            background: rgba(0,0,0,0.8); backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 15px; padding: 15px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .btn-copy-trade {
            background: var(--twitch-purple); color: #fff; border: none;
            padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer;
            display: flex; align-items: center; gap: 8px;
        }

        /* 👉 Right Bar: Chat & Activity */
        .interaction-bar {
            background: var(--sidebar-bg);
            border-left: 1px solid rgba(255,255,255,0.05);
            display: flex;
            flex-direction: column;
        }
        .chat-header {
            padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05);
            text-align: center; font-weight: 700; font-size: 14px;
        }
        .chat-messages { flex: 1; padding: 15px; overflow-y: auto; font-size: 13px; }
        .chat-line { margin-bottom: 8px; line-height: 1.5; }
        .chat-user { font-weight: 700; color: var(--twitch-purple); }

        .tip-input-area {
            padding: 15px; background: rgba(0,0,0,0.2);
            display: flex; gap: 10px;
        }
        .tip-input-area input {
            flex: 1; background: #26262c; border: 1px solid #3f3f46;
            color: #fff; border-radius: 6px; padding: 8px;
        }
        .btn-send-tip { background: var(--gold); border: none; border-radius: 6px; padding: 0 15px; cursor: pointer; }

        /* 🏁 Grid of others */
        .other-lives {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 15px;
        }
        .mini-card {
            background: var(--sidebar-bg); border-radius: 8px; padding: 10px;
            cursor: pointer; transition: transform 0.2s;
        }
        .mini-card:hover { transform: scale(1.02); }
    </style>
</head>
<body>
    <div class="twitch-layout">
        <!-- 👈 Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-title">Kênh Đề Xuất</div>
            <div id="recommended-list">
                <!-- Load via JS -->
                <div class="channel-item">
                    <div class="channel-avatar"></div>
                    <div class="channel-info">
                        <div class="channel-name">Đang tìm cao thủ...</div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- 📺 Main View -->
        <main class="main-view">
            <div class="featured-player" id="featuredPlayer">
                <div class="live-label">TRỰC TIẾP</div>
                <div class="viewer-count"><i class="fa fa-user"></i> <span id="mainViewers">0</span> người xem</div>
                
                <!-- Game Mock Screen -->
                <div style="height:100%; display:flex; align-items:center; justify-content:center; flex-direction:column;">
                    <i class="fa fa-gamepad" style="font-size: 80px; opacity:0.1; margin-bottom:20px;"></i>
                    <h2 id="mainStreamerName">Chọn một trận đấu để xem</h2>
                    <p id="mainGameName" style="color:#adadb8">Hệ thống đang kết nối luồng dữ liệu...</p>
                </div>

                <div class="bet-along-panel" id="betPanel" style="display:none;">
                    <div>
                        <div style="font-size:12px; opacity:0.7">Tỉ lệ húp ván này:</div>
                        <div style="font-weight:900; color:var(--gold);">CỰC CAO (85%)</div>
                    </div>
                    <button class="btn-copy-trade" onclick="betAlong()">
                        <i class="fa fa-bolt"></i> THEO KÈO IDOL
                    </button>
                </div>
            </div>

            <!-- 📣 SPECTATOR CHEER / BUFF LOUNGE -->
            <div class="cheer-buff-panel" id="cheerPanel" style="
                background: rgba(24, 24, 27, 0.65);
                backdrop-filter: blur(15px);
                -webkit-backdrop-filter: blur(15px);
                border: 1px solid rgba(145, 70, 255, 0.25);
                border-radius: 16px;
                padding: 1.5rem;
                margin-top: 25px;
                box-shadow: 0 12px 40px rgba(0,0,0,0.5);
                display: none;
            ">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <div>
                        <h3 style="margin:0; font-family:'Inter', sans-serif; font-size:1.15rem; color:#fff; display:flex; align-items:center; gap:8px;">
                            <span style="font-size:1.5rem;">🔥</span> CỔ VŨ & BƠM BUFF IDOL
                        </h3>
                        <p style="margin:5px 0 0; font-size:0.8rem; color:#adadb8;">Gửi bùa phép cổ vũ để Idol húp đậm và nhận bảo hộ Trận Địa!</p>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:15px;">
                    <!-- Luck Buff -->
                    <div class="buff-card" onclick="purchaseBuff('luck')" style="
                        background: rgba(255, 255, 255, 0.02);
                        border: 1px solid rgba(251, 191, 36, 0.15);
                        border-radius: 12px;
                        padding: 18px 12px;
                        text-align: center;
                        cursor: pointer;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    ">
                        <div style="font-size:2.2rem; margin-bottom:10px;">🍀</div>
                        <h4 style="margin:0 0 5px; color:#fbbf24; font-size:0.95rem; font-weight:800;">Bùa May Mắn (Luck)</h4>
                        <p style="margin:0 0 15px; font-size:0.75rem; color:#adadb8; min-height:36px; line-height:1.4;">Tăng cơ hội húp lộc đậm đà của Idol trong 3 ván.</p>
                        <div style="background:rgba(251,191,36,0.12); color:#fbbf24; font-weight:900; font-size:0.85rem; padding:8px; border-radius:8px; border: 1px solid rgba(251,191,36,0.2);">15,000 GTLM</div>
                    </div>

                    <!-- Hype Buff -->
                    <div class="buff-card" onclick="purchaseBuff('hype')" style="
                        background: rgba(255, 255, 255, 0.02);
                        border: 1px solid rgba(99, 102, 241, 0.15);
                        border-radius: 12px;
                        padding: 18px 12px;
                        text-align: center;
                        cursor: pointer;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    ">
                        <div style="font-size:2.2rem; margin-bottom:10px;">🚀</div>
                        <h4 style="margin:0 0 5px; color:#6366f1; font-size:0.95rem; font-weight:800;">Tên Lửa Hype (Hype)</h4>
                        <p style="margin:0 0 15px; font-size:0.75rem; color:#adadb8; min-height:36px; line-height:1.4;">+20% lộc húp nhân hệ số thưởng cho Idol trong 3 ván.</p>
                        <div style="background:rgba(99,102,241,0.12); color:#818cf8; font-weight:900; font-size:0.85rem; padding:8px; border-radius:8px; border: 1px solid rgba(99,102,241,0.2);">25,000 GTLM</div>
                    </div>

                    <!-- Shield Buff -->
                    <div class="buff-card" onclick="purchaseBuff('shield')" style="
                        background: rgba(255, 255, 255, 0.02);
                        border: 1px solid rgba(34, 197, 94, 0.15);
                        border-radius: 12px;
                        padding: 18px 12px;
                        text-align: center;
                        cursor: pointer;
                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    ">
                        <div style="font-size:2.2rem; margin-bottom:10px;">🛡️</div>
                        <h4 style="margin:0 0 5px; color:#22c55e; font-size:0.95rem; font-weight:800;">Khiên Bảo Vệ (Shield)</h4>
                        <p style="margin:0 0 15px; font-size:0.75rem; color:#adadb8; min-height:36px; line-height:1.4;">Hoàn lại 50% lượng Chiến nếu Idol thất bại trong 3 ván.</p>
                        <div style="background:rgba(34,197,94,0.12); color:#4ade80; font-weight:900; font-size:0.85rem; padding:8px; border-radius:8px; border: 1px solid rgba(34,197,94,0.2);">20,000 GTLM</div>
                    </div>
                </div>
            </div>

            <style>
                .buff-card:hover {
                    transform: translateY(-5px);
                    background: rgba(145, 70, 255, 0.08) !important;
                    border-color: rgba(145, 70, 255, 0.4) !important;
                    box-shadow: 0 8px 24px rgba(145, 70, 255, 0.15);
                }
            </style>

            <div class="sidebar-title" style="padding-left:0; margin-top:30px;">Các trận đấu khác</div>
            <div class="other-lives" id="otherLives">
                <!-- Load via JS -->
            </div>
        </main>

        <!-- 👉 Chat Bar -->
        <aside class="interaction-bar">
            <div class="chat-header">CHAT PHÒNG XEM</div>
            <div class="chat-messages" id="liveChat">
                <div class="chat-line"><span class="chat-user">Lão Tiên Tri:</span> Chào các tiểu tử! Đang hóng hớt ván nào đấy?</div>
            </div>
            <div class="tip-input-area">
                <input type="number" id="tipAmount" placeholder="Tip lộc GTLM...">
                <button class="btn-send-tip" onclick="sendTip()"><i class="fa fa-heart"></i></button>
            </div>
        </aside>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let currentStreamId = null;

        function loadLiveStreams() {
            fetch('api_spectator.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_live'
            })
            .then(r => r.json())
            .then(data => {
                renderSidebar(data.lives || []);
                renderOtherGrid(data.lives || []);
                
                // Tự động chọn stream đầu tiên nếu chưa chọn
                if (!currentStreamId && data.lives && data.lives.length > 0) {
                    selectStream(data.lives[0]);
                }
            });
        }

        function renderSidebar(lives) {
            const list = document.getElementById('recommended-list');
            list.innerHTML = lives.map(l => `
                <div class="channel-item" onclick='selectStream(${JSON.stringify(l)})'>
                    <div class="channel-avatar" style="background: url('${l.avatar || 'img/avatar_default.png'}'); background-size: cover;"></div>
                    <div class="channel-info">
                        <div class="channel-name">${l.streamer_name}</div>
                        <div class="channel-game">${l.game_type}</div>
                    </div>
                    <div class="live-status">
                        <div class="live-dot"></div>
                        <span>${l.viewers || 0}</span>
                    </div>
                </div>
            `).join('');
        }

        function renderOtherGrid(lives) {
            const grid = document.getElementById('otherLives');
            grid.innerHTML = lives.map(l => `
                <div class="mini-card" onclick='selectStream(${JSON.stringify(l)})'>
                    <div style="aspect-ratio:16/9; background:#27272a; border-radius:4px; margin-bottom:8px; display:flex; align-items:center; justify-content:center;">
                        <i class="fa fa-play"></i>
                    </div>
                    <div style="font-weight:700; font-size:13px;">${l.streamer_name}</div>
                    <div style="font-size:11px; color:#adadb8;">${l.game_type}</div>
                </div>
            `).join('');
        }

        function selectStream(stream) {
            currentStreamId = stream.id;
            document.getElementById('mainStreamerName').textContent = stream.streamer_name;
            document.getElementById('mainGameName').textContent = "Đang ra chiêu tại: " + stream.game_type;
            document.getElementById('mainViewers').textContent = stream.viewers || Math.floor(Math.random() * 100);
            document.getElementById('betPanel').style.display = 'flex';
            document.getElementById('cheerPanel').style.display = 'block';
            
            // Log chat
            addChatLine("Vệ Binh Trận Địa", `Hệ thống đã kết nối thành công tới phòng của **${stream.streamer_name}**.`);
        }

        function addChatLine(user, msg) {
            const chat = document.getElementById('liveChat');
            const div = document.createElement('div');
            div.className = 'chat-line';
            div.innerHTML = `<span class="chat-user">${user}:</span> ${msg}`;
            chat.appendChild(div);
            chat.scrollTop = chat.scrollHeight;
        }

        function sendTip() {
            const amount = document.getElementById('tipAmount').value;
            if (!amount || !currentStreamId) return;
            
            fetch('api_spectator.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=tip&stream_id=${currentStreamId}&amount=${amount}`
            })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    addChatLine("BẠN", `Đã tip **${amount}** GTLM lộc! ❤️`);
                    document.getElementById('tipAmount').value = '';
                } else {
                    Swal.fire('Lỗi', data.message, 'error');
                }
            });
        }

        function purchaseBuff(buffType) {
            if (!currentStreamId) {
                Swal.fire('Lỗi', 'Vui lòng chọn phòng live để cổ vũ!', 'warning');
                return;
            }

            const buffNames = {
                'luck': 'Bùa May Mắn (🍀)',
                'hype': 'Tên Lửa Hype (🚀)',
                'shield': 'Khiên Hộ Mệnh (🛡️)'
            };

            Swal.fire({
                title: 'Bơm Bùa Cổ Vũ!',
                html: `Bạn có chắc muốn gửi tặng <b>${buffNames[buffType]}</b> cho Idol không?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'BƠM LUÔN! 🔥',
                cancelButtonText: 'Hủy',
                confirmButtonColor: '#9146ff',
                background: '#18181b',
                color: '#fff'
            }).then((res) => {
                if (res.isConfirmed) {
                    fetch('api_spectator.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=purchase_buff&stream_id=${currentStreamId}&buff_type=${buffType}`
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'CỔ VŨ THÀNH CÔNG!',
                                text: data.message,
                                icon: 'success',
                                background: '#18181b',
                                color: '#fff',
                                confirmButtonColor: '#22c55e'
                            });
                            addChatLine("HỆ THỐNG", `Bạn đã bơm thành công <b>${buffNames[buffType]}</b> cho Idol!`);
                        } else {
                            Swal.fire('Lỗi', data.message, 'error');
                        }
                    });
                }
            });
        }

        function betAlong() {
            Swal.fire({
                title: 'Theo Kèo Idol!',
                text: 'Bạn muốn cược bao nhiêu GTLM theo idol này?',
                input: 'number',
                inputAttributes: { min: 1000 },
                showCancelButton: true,
                confirmButtonText: 'QUẤT LUÔN! 🔥',
                confirmButtonColor: '#9146ff'
            }).then((res) => {
                if (res.isConfirmed) {
                    Swal.fire('Thành công!', 'Lệnh cược đã được khớp. Chúc bạn húp lộc!', 'success');
                }
            });
        }

        setInterval(loadLiveStreams, 10000);
        loadLiveStreams();
    </script>
</body>
</html>
