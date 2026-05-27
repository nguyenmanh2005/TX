<?php
/**
 * 🌋 World Boss Raid - Đại Chiến Ma Thần
 * Hệ thống săn Boss có hệ thống phân vai (Tank/DPS/Healer), kỹ năng Boss theo Phase,
 * hồi sinh theo lịch trình cố định và phần thưởng xếp hạng theo tỷ lệ % đóng góp sát thương.
 */
require_once 'db_connect.php';
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['Iduser'];

// Lấy thông tin Boss hiện tại
$boss = $conn->query("SELECT * FROM world_boss WHERE status = 'active' LIMIT 1")->fetch_assoc();

// Nếu không có boss active: lấy 3 lần boss bị tiêu diệt gần nhất
$recentDefeated = [];
if (!$boss) {
    $res = $conn->query("
        SELECT b.name, b.level,
               am.target_name AS killer,
               am.created_at
        FROM arena_memory am
        JOIN world_boss b ON am.value LIKE CONCAT('%', b.name, '%')
        WHERE am.event_type = 'boss_kill'
        ORDER BY am.created_at DESC
        LIMIT 3
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) $recentDefeated[] = $row;
    }
    if (empty($recentDefeated)) {
        $res2 = $conn->query("
            SELECT target_name AS killer, created_at
            FROM arena_memory
            WHERE event_type = 'boss_kill'
            ORDER BY created_at DESC
            LIMIT 3
        ");
        if ($res2) while ($r = $res2->fetch_assoc()) $recentDefeated[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đại Chiến Ma Thần | World Boss Raid</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #020617;
            --hp-red: #ef4444;
            --hp-yellow: #facc15;
            --primary: #8b5cf6;
        }

        body {
            background: var(--bg);
            color: #fff;
            font-family: 'Inter', sans-serif;
            margin: 0;
            overflow: hidden;
            height: 100vh;
        }

        .raid-container {
            width: 100vw; height: 100vh;
            background: radial-gradient(circle at center, #1e1b4b 0%, #020617 100%);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            position: relative;
        }

        /* 🐉 Boss Area */
        .boss-area {
            position: relative; text-align: center;
            z-index: 10; transition: transform 0.1s;
        }
        .boss-image {
            width: 320px; filter: drop-shadow(0 0 50px rgba(239, 68, 68, 0.4));
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-20px); } }

        /* 🩸 HP Bar */
        .hp-container {
            width: 600px; height: 30px; background: rgba(0,0,0,0.5);
            border: 2px solid rgba(255,255,255,0.1); border-radius: 15px;
            margin-top: 30px; position: relative; overflow: hidden;
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.2);
        }
        .hp-fill {
            height: 100%; width: 100%;
            background: linear-gradient(90deg, var(--hp-red), var(--hp-yellow));
            transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .hp-text {
            position: absolute; width: 100%; text-align: center;
            line-height: 30px; font-weight: 900; font-size: 14px; text-shadow: 0 2px 4px rgba(0,0,0,0.8);
        }

        .boss-name {
            font-family: 'Bangers', cursive; font-size: 54px; color: #fff;
            letter-spacing: 3px; margin-bottom: 5px; text-shadow: 0 0 20px rgba(139, 92, 246, 0.5);
        }

        /* ⚔️ UI Panels */
        .side-panel {
            position: absolute; top: 20px; width: 300px;
            background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.08); border-radius: 24px; padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .left-panel { left: 20px; }
        .right-panel { right: 20px; }

        .panel-title { font-weight: 900; font-size: 13px; text-transform: uppercase; margin-bottom: 15px; color: var(--hp-yellow); display: flex; align-items: center; gap: 10px; letter-spacing: 0.5px; }
        
        .damage-item {
            display: flex; justify-content: space-between; align-items: center; padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 13px;
        }

        /* 🛡️ Role Buttons styling */
        .role-btn {
            flex: 1; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
            color: #94a3b8; padding: 12px 5px; border-radius: 14px; font-weight: 800; font-size: 11px;
            cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; flex-direction: column; align-items: center; gap: 6px;
        }
        .role-btn:hover { background: rgba(255,255,255,0.08); color: #fff; transform: translateY(-1px); }
        .role-btn.active {
            background: linear-gradient(135deg, #f59e0b, #d97706) !important;
            border-color: #fbbf24 !important;
            color: #000 !important;
            box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3);
            transform: translateY(-2px);
        }

        /* 🎮 Action Bar */
        .action-bar {
            position: absolute; bottom: 40px; display: flex; flex-direction: column; align-items: center; gap: 15px;
        }
        .btn-attack {
            padding: 16px 50px; border-radius: 20px; border: none;
            background: linear-gradient(135deg, #ef4444 0%, #8b5cf6 100%);
            color: white; font-weight: 900; font-size: 18px; cursor: pointer;
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            letter-spacing: 0.5px;
        }
        .btn-attack:hover { transform: scale(1.05) translateY(-3px); box-shadow: 0 15px 35px rgba(239, 68, 68, 0.6); }
        .btn-attack:active { transform: scale(0.95); }

        /* 💥 Damage Popups */
        .damage-popup {
            position: absolute; color: #fff; font-family: 'Bangers', cursive; font-size: 40px;
            pointer-events: none; z-index: 100; animation: fadeUp 1s cubic-bezier(0.25, 1, 0.5, 1) forwards;
            text-shadow: 0 0 15px #f00;
        }
        @keyframes fadeUp { 0% { opacity: 1; transform: translateY(0) scale(0.8); } 100% { opacity: 0; transform: translateY(-120px) scale(1.6); } }

        .shake { animation: shake 0.1s infinite; }
        @keyframes shake { 0% { transform: translate(2px, 1px) rotate(0deg); } 20% { transform: translate(-3px, 0px) rotate(1deg); } 40% { transform: translate(1px, -1px) rotate(1deg); } 60% { transform: translate(-1px, 1px) rotate(-1deg); } 80% { transform: translate(-1px, -1px) rotate(1deg); } 100% { transform: translate(1px, -2px) rotate(-1deg); } }
    </style>
</head>
<body>
    <div class="raid-container">
        <!-- 👈 Left: Live Feed & Role Selector -->
        <div class="side-panel left-panel">
            <div class="panel-title"><i class="fa fa-bolt"></i> Nhật ký chiến trường</div>
            <div id="attack-feed" style="max-height: 150px; overflow-y: hidden; margin-bottom: 20px;">
                <!-- Live attacks -->
            </div>

            <!-- ⚔️ SYSTEM CLASS/ROLE SELECTOR -->
            <div style="border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 15px;">
                <div class="panel-title" style="margin-bottom: 10px;"><i class="fa fa-shield-halved"></i> Vai Trò Tấn Công</div>
                <div style="display: flex; gap: 8px;">
                    <button class="role-btn" id="role-btn-dps" onclick="changeRole('dps')">
                        <span style="font-size: 20px;">⚔️</span>
                        <span>SÁT THỦ</span>
                    </button>
                    <button class="role-btn" id="role-btn-tank" onclick="changeRole('tank')">
                        <span style="font-size: 20px;">🛡️</span>
                        <span>HỘ VỆ</span>
                    </button>
                    <button class="role-btn" id="role-btn-healer" onclick="changeRole('healer')">
                        <span style="font-size: 20px;">💚</span>
                        <span>THẦN Y</span>
                    </button>
                </div>
                <div id="role-benefit-info" style="font-size: 11px; opacity: 0.7; margin-top: 12px; line-height: 1.4; background: rgba(0,0,0,0.2); padding: 8px 12px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.03);">
                    Đang giải mã thuộc tính vai trò...
                </div>
            </div>
        </div>

        <!-- 🐉 Boss Area -->
        <div class="boss-area" id="bossContainer">
            <?php if ($boss): ?>
                <div class="boss-name"><?= htmlspecialchars($boss['name']) ?></div>
                <div style="font-size: 14px; opacity: 0.7; margin-bottom: 5px;">CẤP ĐỘ <?= $boss['level'] ?></div>
                
                <!-- Dynamic Phase Widget Container -->
                <div id="bossPhaseWidget" style="background: rgba(0,0,0,0.5); padding: 8px 20px; border-radius: 20px; border: 1.5px solid rgba(255,255,255,0.1); display: inline-block; margin-bottom: 20px; font-weight: 800; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.3s;">
                    Đang giải mã kết cấu trận pháp...
                </div>
                <br>
                <img src="https://cdn-icons-png.flaticon.com/512/10061/10061141.png" class="boss-image" id="bossImg">
                
                <div class="hp-container">
                    <div class="hp-fill" id="hpFill"></div>
                    <div class="hp-text" id="hpText">-- / -- HP</div>
                </div>
            <?php else: ?>
                <!-- 💀 NO BOSS SCREEN — tích hợp lịch respawn cố định và đếm ngược thời gian thực -->
                <div style="display:flex;flex-direction:column;align-items:center;gap:20px;padding:20px;">
                    <img src="https://cdn-icons-png.flaticon.com/512/10061/10061141.png"
                         style="width:250px;filter:grayscale(100%) brightness(0.4) drop-shadow(0 0 30px rgba(255,255,255,0.05));animation:float 4s ease-in-out infinite;"
                         alt="Boss chưa xuất hiện">

                    <div style="background:rgba(15, 23, 42, 0.6); backdrop-filter: blur(15px); border:1px solid rgba(255,255,255,0.08); border-radius:24px; padding:30px 40px; text-align:center; max-width:480px; box-shadow: 0 15px 35px rgba(0,0,0,0.5);">
                        <div style="font-family:'Bangers',cursive; font-size:36px; letter-spacing:3px; color:#94a3b8; margin-bottom:8px;">MA THẦN ĐÃ BỊ DIỆT</div>
                        <div style="font-size:14px; opacity:0.7; line-height:1.7;">
                            Chiến trường đã được giải tỏa... Ma Thần kế tiếp sẽ tự động trỗi dậy theo <strong style="color:#fbbf24;">Lịch trình hồi sinh cố định</strong> hằng ngày!
                        </div>

                        <!-- Real-time Respawn Countdown Timer -->
                        <div style="margin-top: 20px; font-weight: 900; font-size: 15px; color: #fbbf24; border: 1.5px dashed rgba(251, 191, 36, 0.4); padding: 12px 25px; border-radius: 16px; display: inline-block; background: rgba(251, 191, 36, 0.05);" id="nextRespawnCountdown">
                            Ma Thần xuất hiện sau: --:--:--
                        </div>

                        <div style="font-size: 11px; opacity: 0.5; margin-top: 10px;">
                            ⏰ Khung giờ hồi sinh: 09:00 | 15:00 | 21:00 mỗi ngày
                        </div>

                        <?php if (!empty($recentDefeated)): ?>
                        <div style="margin-top:25px; border-top:1px solid rgba(255,255,255,0.08); padding-top:20px; text-align: left;">
                            <div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#94a3b8; margin-bottom:12px; text-align: center;"><i class="fa fa-skull"></i> 3 Lần Hạ Gục Gần Nhất</div>
                            <?php foreach ($recentDefeated as $d): ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.04); font-size:13px;">
                                <span>🔥 <b><?= htmlspecialchars($d['killer'] ?? 'Vô Danh') ?></b>
                                <?php if (!empty($d['name'])): ?>(<em style="opacity:.6"><?= htmlspecialchars($d['name']) ?></em>)<?php endif; ?></span>
                                <span style="opacity:.5; font-size:11px;"><?= date('d/m H:i', strtotime($d['created_at'])) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <a href="index.php" style="display:inline-block; margin-top:25px; padding:12px 35px; background:linear-gradient(135deg,#8b5cf6,#6366f1); border-radius:14px; color:#fff; font-weight:800; text-decoration:none; letter-spacing:1px; font-size:14px; transition: transform 0.2s;">&#8592; VỀ SẢNH CHÍNH</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- 👉 Right: Leaderboard & Reward Tiers Info -->
        <div class="side-panel right-panel">
            <div class="panel-title"><i class="fa fa-crown"></i> Top Sát Thương</div>
            <div id="damage-leaderboard" style="max-height: 180px; overflow-y: hidden;">
                <!-- Leaderboard -->
            </div>
            
            <div style="margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 15px;">
                <div style="font-size: 12px; opacity: 0.7;">Sát thương của bạn:</div>
                <div id="my-damage" style="font-weight: 900; color: var(--hp-yellow); font-size: 20px;">0</div>
            </div>

            <!-- 🎁 REWARD PERCENT TIERS WIDGET -->
            <div style="margin-top: 15px; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.05); padding: 12px 15px; border-radius: 16px; font-size: 11px; line-height: 1.5;">
                <div style="font-weight: 900; color: #fbbf24; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px;"><i class="fa fa-gift"></i> Quà Tặng Theo Sát Thương %</div>
                <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
                    <span style="color:#ef4444; font-weight:700;">🥇 Hạng S (Top 10%):</span>
                    <span>5M GTLM + Theme</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:3px;">
                    <span style="color:#3b82f6; font-weight:700;">🥈 Hạng A (Top 30%):</span>
                    <span>2.5M GTLM + Trỏ Chuột</span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:#94a3b8; font-weight:700;">🥉 Hạng B (Còn lại):</span>
                    <span>500k GTLM</span>
                </div>
            </div>
        </div>

        <!-- 🎮 Action Bar -->
        <?php if ($boss): ?>
        <div class="action-bar">
            <button class="btn-attack" id="attackBtn" onclick="attackBoss()">TUNG CHIÊU (-5,000 GTLM)</button>
        </div>
        <?php endif; ?>

        <div style="position: absolute; top: 20px; left: 50%; transform: translateX(-50%);">
            <a href="index.php" style="color: #fff; text-decoration: none; font-size: 14px; opacity: 0.6;"><i class="fa fa-arrow-left"></i> Rời Chiến Trường</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const bossId = <?= $boss['id'] ?? 1 ?>;
        let lastSyncTime = 0;
        let nextSpawnEpoch = 0;
        let respawnTimer = null;
        let activeRole = 'dps';

        const roleInfos = {
            dps: {
                title: "⚔️ Vai Trò: SÁT THỦ",
                benefit: "Tăng thêm 50% sát thương cơ bản đòn đánh.",
                btnText: "TUNG CHIÊU (-5,000 GTLM) ⚔️ +50% Dame"
            },
            tank: {
                title: "🛡️ Vai Trò: HỘ VỆ",
                benefit: "Giảm 30% GTLM phí tấn công và hoàn toàn miễn nhiễm sát thương phản đòn ở Phase 3.",
                btnText: "TUNG CHIÊU (-3,500 GTLM) 🛡️ Miễn Phản Đòn"
            },
            healer: {
                title: "💚 Vai Trò: THẦN Y",
                benefit: "Trả lại 1,250 GTLM vàng hồi sức sau mỗi đòn đánh.",
                btnText: "TUNG CHIÊU (-5,000 GTLM) 💚 Trị Liệu +1.2K GTLM"
            }
        };

        // 📅 HÀM ĐẾM NGƯỢC THỜI GIAN THỰC ĐẾN KHUNG GIỜ HỒI SINH
        function startRespawnCountdown(epoch) {
            nextSpawnEpoch = epoch;
            if (respawnTimer) clearInterval(respawnTimer);
            
            const cdEl = document.getElementById('nextRespawnCountdown');
            if (!cdEl) return;
            
            function tick() {
                const now = Math.floor(Date.now() / 1000);
                const diff = nextSpawnEpoch - now;
                if (diff <= 0) {
                    clearInterval(respawnTimer);
                    cdEl.innerHTML = `<span style="color:#22c55e;"><i class="fa fa-sync animate-spin"></i> Ma Thần đang hồi sinh! Hãy tải lại trang!</span>`;
                    setTimeout(() => location.reload(), 2500);
                    return;
                }
                
                const h = Math.floor(diff / 3600);
                const m = Math.floor((diff % 3600) / 60);
                const s = diff % 60;
                const pad = n => String(n).padStart(2, '0');
                cdEl.innerHTML = `Ma Thần xuất hiện sau: <strong style="font-size:18px;">${pad(h)}:${pad(m)}:${pad(s)}</strong>`;
            }
            
            tick();
            respawnTimer = setInterval(tick, 1000);
        }

        // 🛡️ HÀM THAY ĐỔI VAI TRÒ CHIẾN ĐẤU
        async function changeRole(role) {
            const formData = new FormData();
            formData.append('action', 'set_role');
            formData.append('role', role);
            formData.append('id', bossId);

            try {
                const res = await fetch(`api_world_boss.php`, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        icon: 'success',
                        title: data.message
                    });
                    syncBoss();
                } else {
                    Swal.fire('Lỗi', data.message, 'error');
                }
            } catch(e) {
                console.error(e);
            }
        }

        // 🧠 ĐỒNG BỘ DỮ LIỆU CHIẾN TRƯỜNG DYNAMIC
        async function syncBoss() {
            try {
                const res = await fetch(`api_world_boss.php?action=sync&id=${bossId}`);
                const data = await res.json();

                if (!data.success) return;

                // Tự kích hoạt đếm ngược nếu Boss bị tiêu diệt
                if (data.status === 'defeated') {
                    if (data.next_spawn_epoch) {
                        startRespawnCountdown(data.next_spawn_epoch);
                    }
                    return;
                }

                // Cập nhật HP
                const hpFill = document.getElementById('hpFill');
                const hpText = document.getElementById('hpText');
                if (hpFill && hpText) {
                    const percent = (data.hp / data.max_hp) * 100;
                    hpFill.style.width = percent + '%';
                    hpText.textContent = data.hp.toLocaleString() + ' / ' + data.max_hp.toLocaleString() + ' HP';
                }

                // Đồng bộ và render vai trò hiện tại của người chơi
                activeRole = data.my_role || 'dps';
                document.querySelectorAll('.role-btn').forEach(btn => btn.classList.remove('active'));
                const activeBtn = document.getElementById(`role-btn-${activeRole}`);
                if (activeBtn) activeBtn.classList.add('active');

                const rInfo = roleInfos[activeRole];
                if (rInfo) {
                    document.getElementById('role-benefit-info').innerHTML = `<strong>${rInfo.title}</strong><br>${rInfo.benefit}`;
                    const attackBtn = document.getElementById('attackBtn');
                    if (attackBtn) {
                        attackBtn.innerHTML = rInfo.btnText;
                    }
                }

                // Cập nhật Phase Widget và hiệu ứng Glow, Background
                const phaseWidget = document.getElementById('bossPhaseWidget');
                const bossImg = document.getElementById('bossImg');
                const container = document.querySelector('.raid-container');
                
                if (phaseWidget && bossImg && container) {
                    if (data.phase === 1) {
                        phaseWidget.innerHTML = `<span style="color: #60a5fa;"><i class="fa fa-fire animate-pulse"></i> Phase 1: Thượng Cổ Thức Tỉnh</span>`;
                        phaseWidget.style.borderColor = "rgba(96, 165, 250, 0.4)";
                        bossImg.style.filter = "drop-shadow(0 0 50px rgba(96, 165, 250, 0.4))";
                        container.style.background = "radial-gradient(circle at center, #1e1b4b 0%, #020617 100%)";
                    } else if (data.phase === 2) {
                        phaseWidget.innerHTML = `<span style="color: #10b981;"><i class="fa fa-wind animate-pulse"></i> Phase 2: Băng Hỏa Kiếp (Giảm 30% dame)</span>`;
                        phaseWidget.style.borderColor = "rgba(16, 185, 129, 0.4)";
                        bossImg.style.filter = "drop-shadow(0 0 60px rgba(16, 185, 129, 0.5))";
                        container.style.background = "radial-gradient(circle at center, #0f2e20 0%, #020617 100%)";
                    } else if (data.phase === 3) {
                        const hour = new Date().getHours();
                        const isNight = hour >= 20;
                        let lockStatus = isNight ? `<span style="color: #ef4444;"><i class="fa fa-dragon animate-bounce"></i> Phase 3: Phản Phục Trận (Phản đòn nguy kịch!)</span>` : `<span style="color: #f59e0b;"><i class="fa fa-lock"></i> Phase 3: Phản Phục Trận (CHỈ TẤN CÔNG SAU 20:00!)</span>`;
                        phaseWidget.innerHTML = lockStatus;
                        phaseWidget.style.borderColor = isNight ? "rgba(239, 68, 68, 0.5)" : "rgba(245, 158, 11, 0.4)";
                        bossImg.style.filter = isNight ? "drop-shadow(0 0 80px rgba(239, 68, 68, 0.8))" : "drop-shadow(0 0 40px rgba(245, 158, 11, 0.4)) grayscale(60%)";
                        container.style.background = isNight ? "radial-gradient(circle at center, #450a0a 0%, #020617 100%)" : "radial-gradient(circle at center, #2e1d0f 0%, #020617 100%)";
                    }
                }

                // Cập nhật Leaderboard kèm Badge vai trò
                const lb = document.getElementById('damage-leaderboard');
                if (lb) {
                    const roleIcons = { dps: '⚔️', tank: '🛡️', healer: '💚' };
                    lb.innerHTML = data.leaderboard.map((item, i) => {
                        const icon = roleIcons[item.role] || '⚔️';
                        return `
                            <div class="damage-item">
                                <span style="font-weight: 700;">#${i+1} ${icon} ${item.Name}</span>
                                <span style="color:var(--hp-yellow); font-weight:800;">${item.damage.toLocaleString()}</span>
                            </div>
                        `;
                    }).join('');
                }

                // Cập nhật sát thương cá nhân
                const myDmgEl = document.getElementById('my-damage');
                if (myDmgEl) myDmgEl.textContent = data.my_damage.toLocaleString();

                // Cập nhật Feed
                const feed = document.getElementById('attack-feed');
                if (feed && data.recent_attacks.length > 0) {
                    feed.innerHTML = data.recent_attacks.map(a => `
                        <div style="font-size:12px; margin-bottom:8px; opacity:0.8;">
                            <span style="color:var(--primary); font-weight:700;">${a.Name}</span> gây <b>${a.val.toLocaleString()}</b> dame
                        </div>
                    `).join('');
                }

                if (data.hp <= 0 && data.status === 'active') {
                    Swal.fire('CHIẾN THẮNG!', 'Thượng Cổ Ma Thần đã chính thức gục ngã! Phần thưởng S-Tier đang được phân phối...', 'success').then(() => location.reload());
                }
            } catch (e) {
                console.error("Lỗi đồng bộ boss:", e);
            }
        }

        // ⚔️ HÀM TẤN CÔNG MA THẦN
        async function attackBoss() {
            const btn = document.getElementById('attackBtn');
            if (btn) btn.disabled = true;

            try {
                const res = await fetch(`api_world_boss.php?action=attack&id=${bossId}`);
                const data = await res.json();

                if (data.success) {
                    showDamage(data.damage);
                    shakeBoss();
                    syncBoss();
                    if (data.message) {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000,
                            icon: data.message.includes('THẦN PHẠT') || data.message.includes('Tanker') ? 'success' : (data.message.includes('giáp gai') || data.message.includes('phản kích') ? 'warning' : 'info'),
                            title: data.message
                        });
                    }
                } else {
                    Swal.fire('Trận Pháp Ngăn Cản', data.message, 'error');
                }
            } catch(e) {
                console.error(e);
            }
            
            setTimeout(() => { if (btn) btn.disabled = false; }, 500);
        }

        function showDamage(dmg) {
            const popup = document.createElement('div');
            popup.className = 'damage-popup';
            popup.textContent = '-' + dmg.toLocaleString();
            popup.style.left = (window.innerWidth / 2 + (Math.random() * 200 - 100)) + 'px';
            popup.style.top = (window.innerHeight / 2 - 100) + 'px';
            document.body.appendChild(popup);
            setTimeout(() => popup.remove(), 1000);
        }

        function shakeBoss() {
            const boss = document.getElementById('bossContainer');
            if (boss) {
                boss.classList.add('shake');
                setTimeout(() => boss.classList.remove('shake'), 200);
            }
        }

        // Đồng bộ mỗi 2 giây
        setInterval(syncBoss, 2000);
        syncBoss();
    </script>
</body>
</html>
