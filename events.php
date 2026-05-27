<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require_once 'load_theme.php';
require_once 'api_event_helper.php'; // getActiveSeasonalEvent()
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%)';
}

$userId = $_SESSION['Iduser'];

// Lấy thông tin Event Mùa Giải (dùng helper tập trung)
$seasonalEvent   = getActiveSeasonalEvent($conn); // SELECT *
$upcomingSeasonal = $conn->query("SELECT * FROM seasonal_events WHERE status = 'upcoming' ORDER BY starts_at ASC LIMIT 3")->fetch_all(MYSQLI_ASSOC);

// Lấy thông tin World Boss
$worldBoss = $conn->query("SELECT * FROM world_boss WHERE status = 'active' LIMIT 1")->fetch_assoc();

// Kiểm tra bảng events
$checkTable = $conn->query("SHOW TABLES LIKE 'events'");
$eventsTableExists = $checkTable && $checkTable->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sảnh Sự Kiện - Event Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { margin: 0; padding: 20px; font-family: 'Outfit', sans-serif; background: <?= $bgGradientCSS ?>; color: #fff; min-height: 100vh; background-attachment: fixed; }
        .hub-container { max-width: 1200px; margin: 0 auto; }
        .hub-hero { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 30px; padding: 50px; text-align: center; margin-bottom: 40px; position: relative; overflow: hidden; backdrop-filter: blur(10px); }
        .hub-hero::before { content: '🎆'; position: absolute; font-size: 150px; opacity: 0.1; right: -20px; top: -30px; transform: rotate(15deg); }
        .hero-title { font-size: 48px; font-weight: 900; margin: 0; background: linear-gradient(135deg, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        
        .section-title { font-size: 24px; font-weight: 800; border-bottom: 2px solid rgba(255,255,255,0.1); padding-bottom: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        
        /* Horizontal Scroll Cards */
        .major-events { display: flex; gap: 20px; overflow-x: auto; padding-bottom: 20px; }
        .major-card { flex: 0 0 320px; background: rgba(0,0,0,0.4); border-radius: 20px; padding: 25px; border: 1px solid rgba(255,255,255,0.1); position: relative; transition: 0.3s; text-decoration: none; color: #fff; display: flex; flex-direction: column; }
        .major-card:hover { transform: translateY(-5px); border-color: #fbbf24; box-shadow: 0 10px 25px rgba(251, 191, 36, 0.2); }
        .mc-icon { font-size: 40px; margin-bottom: 15px; }
        .mc-title { font-size: 22px; font-weight: 800; margin-bottom: 5px; }
        .mc-desc { font-size: 14px; opacity: 0.7; flex: 1; margin-bottom: 20px; }
        .mc-status { display: inline-block; padding: 5px 12px; border-radius: 50px; font-size: 11px; font-weight: 800; background: rgba(255,255,255,0.1); margin-top: auto; text-align: center; }
        .mc-status.active { background: #22c55e; color: #000; }
        .live-countdown { margin-top: 10px; font-size: 13px; font-weight: 800; color: #ef4444; background: rgba(239,68,68,0.1); padding: 5px 10px; border-radius: 8px; border: 1px solid rgba(239,68,68,0.3); display: inline-block; }
        
        /* Event Calendar */
        .calendar-section { background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 25px; margin-bottom: 40px; }
        .cal-item { display: flex; align-items: center; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .cal-item:last-child { border-bottom: none; }
        .cal-time { font-size: 14px; font-weight: 800; color: #fbbf24; width: 150px; }
        .cal-name { flex: 1; font-weight: 700; font-size: 16px; }
        .cal-badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 800; }
        .badge-upcoming { background: rgba(59,130,246,0.2); color: #60a5fa; border: 1px solid rgba(59,130,246,0.3); }
        .badge-active { background: rgba(34,197,94,0.2); color: #4ade80; border: 1px solid rgba(34,197,94,0.3); }
        .badge-daily { background: rgba(168,85,247,0.2); color: #c084fc; border: 1px solid rgba(168,85,247,0.3); }
        
        /* Grid cho Basic Events */
        .basic-events-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .basic-card { background: rgba(255,255,255,0.05); border-radius: 16px; padding: 20px; border: 1px solid rgba(255,255,255,0.05); transition: 0.3s; }
        .basic-card:hover { background: rgba(255,255,255,0.1); }
        .bc-title { font-weight: 700; font-size: 18px; margin-bottom: 5px; }
        .bc-reward { color: #fbbf24; font-weight: 800; margin: 10px 0; }
        .bc-btn { width: 100%; padding: 10px; border-radius: 10px; border: none; font-weight: 800; cursor: pointer; transition: 0.2s; }
        .btn-claim { background: #fbbf24; color: #000; }
        .btn-join { background: #3b82f6; color: #fff; }
        .btn-claimed { background: #475569; color: #94a3b8; cursor: not-allowed; }
        
        /* Skeleton */
        @keyframes shimmer { 0% { background-position: -800px 0; } 100% { background-position: 800px 0; } }
        .skeleton { background: linear-gradient(90deg, rgba(255,255,255,0.06) 25%, rgba(255,255,255,0.12) 37%, rgba(255,255,255,0.06) 63%); background-size: 800px 100%; animation: shimmer 1.4s infinite linear; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="hub-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <a href="index.php" style="color: #fff; text-decoration: none; opacity: 0.7;"><i class="fa fa-arrow-left"></i> Về sảnh chính</a>
            <a href="event_archive.php" style="color: #38bdf8; text-decoration: none; font-weight: 800; background: rgba(56, 189, 248, 0.1); padding: 8px 15px; border-radius: 50px;"><i class="fa fa-book"></i> Biên Niên Sử</a>
        </div>
        
        <div class="hub-hero">
            <h1 class="hero-title">ĐẠI SẢNH SỰ KIỆN</h1>
            <p style="font-size: 18px; opacity: 0.8; margin-top: 10px;">Nơi hội tụ những thử thách và phần thưởng vinh quang nhất</p>
        </div>

        <h2 class="section-title"><i class="fa fa-star text-warning"></i> SỰ KIỆN TÂM ĐIỂM</h2>
        <div class="major-events">
            <a href="event_center.php" class="major-card">
                <?php 
                $badgeHtml = '';
                if ($seasonalEvent) {
                    $now = time();
                    $sAt = strtotime($seasonalEvent['starts_at']);
                    $eAt = strtotime($seasonalEvent['ends_at']);
                    if ($now - $sAt <= 2 * 86400) {
                        $badgeHtml = '<div style="position:absolute;top:10px;right:10px;background:#ef4444;color:#fff;padding:4px 10px;border-radius:8px;font-size:10px;font-weight:900;box-shadow:0 0 10px rgba(239,68,68,0.5);animation:pulse 2s infinite;">🔥 MỚI</div>';
                    } elseif ($eAt - $now <= 2 * 86400 && $eAt > $now) {
                        $badgeHtml = '<div style="position:absolute;top:10px;right:10px;background:#f59e0b;color:#000;padding:4px 10px;border-radius:8px;font-size:10px;font-weight:900;box-shadow:0 0 10px rgba(245,158,11,0.5);animation:pulse 2s infinite;">⏳ SẮP HẾT</div>';
                    }
                }
                echo $badgeHtml;
                ?>
                <style>@keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }</style>
                <div class="mc-icon">🧧</div>
                <div class="mc-title">Sự Kiện Mùa Giải</div>
                <div class="mc-desc"><?= $seasonalEvent ? htmlspecialchars($seasonalEvent['name']) : 'Đang chờ cập nhật...' ?></div>
                <div>
                    <div class="mc-status <?= $seasonalEvent ? 'active' : '' ?>"><?= $seasonalEvent ? 'ĐANG DIỄN RA' : 'SẮP RA MẮT' ?></div>
                    <?php if ($seasonalEvent && isset($seasonalEvent['ends_at'])): ?>
                        <br><div class="live-countdown" data-ends="<?= $seasonalEvent['ends_at'] ?>">⏳ Đang tính toán...</div>
                    <?php endif; ?>
                </div>
            </a>
            <a href="world_boss.php" class="major-card" style="background: linear-gradient(135deg, rgba(239,68,68,0.2), rgba(0,0,0,0.4)); border-color: rgba(239,68,68,0.3);">
                <div class="mc-icon">🐉</div>
                <div class="mc-title">Đại Chiến Ma Thần</div>
                <div class="mc-desc">World Boss toàn server. Hợp lực tiêu diệt để nhận siêu phần thưởng.</div>
                <div>
                    <div class="mc-status <?= $worldBoss ? 'active' : '' ?>" <?= $worldBoss ? '' : 'style="background:#ef4444;"' ?>><?= $worldBoss ? 'BOSS ĐÃ XUẤT HIỆN' : 'CHỜ HỒI SINH' ?></div>
                    <?php if (!$worldBoss): ?>
                        <br><div class="live-countdown" data-ends="<?= date('Y-m-d 20:00:00') ?>">⏳ Nộ Long Phase: Tính toán...</div>
                    <?php endif; ?>
                </div>
            </a>
            <a href="battle_pass.php" class="major-card">
                <div class="mc-icon">🎖️</div>
                <div class="mc-title">Battle Pass</div>
                <div class="mc-desc">Hoàn thành nhiệm vụ hàng ngày/tuần để thăng cấp và nhận quà độc quyền.</div>
                <div>
                    <div class="mc-status active">MÙA 1</div>
                </div>
            </a>
            <a href="storyline_event.php" class="major-card" style="background: linear-gradient(135deg, rgba(139,92,246,0.2), rgba(0,0,0,0.4)); border-color: rgba(139,92,246,0.3);">
                <div class="mc-icon">📖</div>
                <div class="mc-title">Khai Phá Ký Ức</div>
                <div class="mc-desc">Theo dấu cốt truyện trận địa mỗi ngày và hoàn thành nhiệm vụ để húp GTLM siêu khủng.</div>
                <div>
                    <div class="mc-status active" style="background: #8b5cf6; color: #fff;">ĐANG KHAI MỞ</div>
                </div>
            </a>
        </div>

        <!-- 📅 Lịch Sự Kiện -->
        <h2 class="section-title" style="margin-top: 40px;"><i class="fa fa-calendar-alt text-primary"></i> LỊCH TRÌNH SỰ KIỆN</h2>
        <div class="calendar-section">
            <?php if ($seasonalEvent): ?>
            <div class="cal-item">
                <div class="cal-time"><?= date('d/m/Y', strtotime($seasonalEvent['ends_at'])) ?></div>
                <div class="cal-name"><?= htmlspecialchars($seasonalEvent['name']) ?></div>
                <div class="cal-badge badge-active">ĐANG DIỄN RA</div>
            </div>
            <?php endif; ?>
            
            <div class="cal-item">
                <div class="cal-time">20:00 Hàng Ngày</div>
                <div class="cal-name">Đại Chiến Ma Thần - Phase Nộ Long</div>
                <div class="cal-badge badge-daily">HẰNG NGÀY</div>
            </div>

            <?php foreach ($upcomingSeasonal as $up): ?>
            <div class="cal-item">
                <div class="cal-time"><?= date('d/m/Y', strtotime($up['starts_at'])) ?></div>
                <div class="cal-name"><?= htmlspecialchars($up['name']) ?></div>
                <div class="cal-badge badge-upcoming">SẮP DIỄN RA</div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($seasonalEvent) && empty($upcomingSeasonal)): ?>
            <div style="opacity:0.5; text-align:center; padding: 20px;">Lịch trình hiện đang trống. Cập nhật sắp tới!</div>
            <?php endif; ?>
        </div>

        <!-- 🗳️ Bình Chọn Sự Kiện -->
        <h2 class="section-title" style="margin-top: 40px;"><i class="fa fa-poll"></i> BÌNH CHỌN SỰ KIỆN TIẾP THEO</h2>
        <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; padding: 25px; margin-bottom: 40px;">
            <p style="opacity: 0.8; margin-top: 0; margin-bottom: 20px;">Bạn muốn thấy sự kiện nào xuất hiện trong mùa tới? Hãy bỏ phiếu ngay!</p>
            <div id="voting-options" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                <!-- Vote options loaded here -->
            </div>
        </div>

        <h2 class="section-title" style="margin-top: 40px;"><i class="fa fa-list"></i> NHIỆM VỤ CƠ BẢN</h2>
        <?php if (!$eventsTableExists): ?>
            <div style="background:rgba(220,53,69,0.2); border:1px solid #dc3545; color:#fff; padding:15px; border-radius:10px;">
                ⚠️ Bảng events chưa được cài đặt.
            </div>
        <?php else: ?>
            <div class="basic-events-grid" id="events-list">
                <!-- Skeleton items will be injected here -->
            </div>
        <?php endif; ?>
    </div>

    <script>
        const formatMoney = (amount) => new Intl.NumberFormat('vi-VN').format(amount) + ' GTLM';
        const formatNum = (num) => new Intl.NumberFormat('vi-VN').format(num);

        function updateCountdowns() {
            $('.live-countdown').each(function() {
                const endsAt = $(this).data('ends');
                if (!endsAt) return;
                
                let target = new Date(endsAt).getTime();
                // Nếu World Boss ở quá khứ (đã qua 20:00), set cho ngày mai
                if ($(this).text().includes('Nộ Long Phase') && target < Date.now()) {
                    target += 86400000;
                }

                const diff = target - Date.now();
                if (diff <= 0) {
                    $(this).text('⏳ Sắp bắt đầu / Đã kết thúc');
                    return;
                }
                
                const d = Math.floor(diff / 86400000);
                const h = Math.floor((diff % 86400000) / 3600000);
                const m = Math.floor((diff % 3600000) / 60000);
                const s = Math.floor((diff % 60000) / 1000);
                const pad = n => String(n).padStart(2, '0');
                
                let timeStr = '';
                if (d > 0) timeStr += `${d}d `;
                timeStr += `${pad(h)}:${pad(m)}:${pad(s)}`;
                
                $(this).text(`⏳ Còn lại: ${timeStr}`);
            });
        }
        setInterval(updateCountdowns, 1000);
        updateCountdowns();

        function renderSkeleton() {
            let html = '';
            for(let i=0; i<4; i++) {
                html += `<div class="basic-card">
                    <div class="skeleton" style="height:20px; width:70%; margin-bottom:10px;"></div>
                    <div class="skeleton" style="height:14px; width:90%; margin-bottom:15px;"></div>
                    <div class="skeleton" style="height:24px; width:50%; margin-bottom:15px;"></div>
                    <div class="skeleton" style="height:40px; width:100%;"></div>
                </div>`;
            }
            $('#events-list').html(html);
        }

        function loadEvents() {
            if ($('#events-list').length === 0) return;
            renderSkeleton();
            $.get('api_events.php?action=get_list&status=all', function(res) {
                if(!res.success) return;
                if(res.events.length === 0) {
                    $('#events-list').html('<div style="grid-column: 1/-1; text-align:center; padding:40px; opacity:0.5;">Không có sự kiện cơ bản nào đang diễn ra.</div>');
                    return;
                }

                let html = '';
                res.events.forEach(e => {
                    const progress = e.user_progress || 0;
                    const isCompleted = e.user_completed;
                    const isClaimed = e.user_claimed;
                    const isJoined = e.is_joined;
                    
                    let btnHTML = '';
                    if(!isJoined) {
                        btnHTML = `<button class="bc-btn btn-join" onclick="joinEvent(${e.id})">NHẬN NHIỆM VỤ</button>`;
                    } else if(isClaimed) {
                        btnHTML = `<button class="bc-btn btn-claimed" disabled>ĐÃ NHẬN THƯỞNG</button>`;
                    } else if(isCompleted) {
                        btnHTML = `<button class="bc-btn btn-claim" onclick="claimReward(${e.id})">NHẬN ${formatMoney(e.reward_value)}</button>`;
                    } else {
                        const pct = Math.min(100, Math.round((progress/e.requirement_value)*100));
                        btnHTML = `
                            <div style="font-size:12px; margin-bottom:5px; font-weight:700;">TIẾN ĐỘ: ${formatNum(progress)}/${formatNum(e.requirement_value)}</div>
                            <div style="height:8px; background:rgba(255,255,255,0.1); border-radius:10px; overflow:hidden;">
                                <div style="height:100%; width:${pct}%; background:#3b82f6;"></div>
                            </div>
                        `;
                    }

                    let badgeHtml = '';
                    if (e.starts_at && (Date.now() - new Date(e.starts_at).getTime()) <= 2 * 86400000) {
                        badgeHtml = '<div style="position:absolute;top:10px;right:10px;background:#ef4444;color:#fff;padding:2px 8px;border-radius:5px;font-size:10px;font-weight:900;animation:pulse 2s infinite;">MỚI</div>';
                    } else if (e.ends_at) {
                        const diff = new Date(e.ends_at).getTime() - Date.now();
                        if (diff > 0 && diff <= 2 * 86400000) {
                            badgeHtml = '<div style="position:absolute;top:10px;right:10px;background:#f59e0b;color:#000;padding:2px 8px;border-radius:5px;font-size:10px;font-weight:900;animation:pulse 2s infinite;">SẮP HẾT</div>';
                        }
                    }

                    html += `
                        <div class="basic-card" style="position:relative;">
                            ${badgeHtml}
                            <div class="bc-title">${e.name}</div>
                            <div style="font-size:13px; opacity:0.7;">${e.description}</div>
                            <div class="bc-reward">🎁 ${formatMoney(e.reward_value)}</div>
                            <div style="margin-top: 15px;">${btnHTML}</div>
                        </div>
                    `;
                });
                $('#events-list').html(html);
            }, 'json');
        }

        function joinEvent(id) {
            $.post('api_events.php', { action: 'join', event_id: id }, function(res) {
                if(res.success) { Swal.fire('Nhận thành công!', '', 'success'); loadEvents(); }
                else Swal.fire('Lỗi', res.message, 'error');
            }, 'json');
        }

        function claimReward(id) {
            $.post('api_events.php', { action: 'claim_reward', event_id: id }, function(res) {
                if(res.success) { Swal.fire('Thành công!', 'Bạn đã nhận ' + formatMoney(res.reward.money), 'success'); loadEvents(); }
                else Swal.fire('Lỗi', res.message, 'error');
            }, 'json');
        }

        function loadVoting() {
            $('#voting-options').html('<div style="opacity:0.5; padding: 20px;">Đang tải...</div>');
            $.get('api_event_vote.php?action=get_options', function(res) {
                if(res.success) {
                    let html = '';
                    if (res.options.length === 0) {
                        $('#voting-options').html('<div style="opacity:0.5;">Hiện không có cuộc bình chọn nào.</div>');
                        return;
                    }
                    res.options.forEach(opt => {
                        const pct = res.total_votes > 0 ? Math.round((opt.votes / res.total_votes) * 100) : 0;
                        const isMyVote = res.my_vote == opt.id;
                        const votedStyle = isMyVote ? 'border-color: #22c55e; background: rgba(34, 197, 94, 0.1);' : '';
                        
                        let btnHtml = '';
                        if (res.my_vote) {
                            btnHtml = isMyVote ? `<div style="color: #22c55e; font-weight: 800; font-size: 13px;"><i class="fa fa-check"></i> ĐÃ BÌNH CHỌN</div>` : `<div style="opacity: 0.5; font-size: 13px; font-weight: 800;">${pct}% (${opt.votes} phiếu)</div>`;
                        } else {
                            btnHtml = `<button onclick="voteEvent(${opt.id})" style="background: rgba(255,255,255,0.1); border: none; color: #fff; padding: 8px 15px; border-radius: 10px; font-weight: 800; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='#3b82f6'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">BÌNH CHỌN</button>`;
                        }

                        html += `
                        <div style="background: rgba(255,255,255,0.05); border: 2px solid rgba(255,255,255,0.05); border-radius: 15px; padding: 20px; transition: 0.3s; ${votedStyle}">
                            <div style="display: flex; gap: 15px; align-items: center; margin-bottom: 15px;">
                                <div style="font-size: 30px;">${opt.icon}</div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 800; font-size: 16px;">${opt.title}</div>
                                    <div style="font-size: 12px; opacity: 0.7;">${opt.description}</div>
                                </div>
                            </div>
                            
                            <!-- Progress bar -->
                            ${res.my_vote ? `
                            <div style="height: 6px; background: rgba(255,255,255,0.1); border-radius: 10px; margin-bottom: 10px; overflow: hidden;">
                                <div style="height: 100%; width: ${pct}%; background: ${isMyVote ? '#22c55e' : '#3b82f6'};"></div>
                            </div>` : ''}

                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                ${res.my_vote && isMyVote ? `<div style="opacity: 0.7; font-size: 13px; font-weight: 800;">${pct}% (${opt.votes} phiếu)</div>` : '<div></div>'}
                                ${btnHtml}
                            </div>
                        </div>`;
                    });
                    $('#voting-options').html(html);
                }
            }, 'json');
        }

        function voteEvent(id) {
            Swal.fire({
                title: 'Xác nhận bình chọn',
                text: "Mỗi người chỉ được bình chọn 1 lần duy nhất!",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Đồng ý',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('api_event_vote.php', { action: 'vote', option_id: id }, function(res) {
                        if (res.success) {
                            Swal.fire('Thành công', res.message, 'success');
                            loadVoting();
                        } else {
                            Swal.fire('Lỗi', res.message, 'error');
                        }
                    }, 'json');
                }
            });
        }

        $(document).ready(function() {
            loadEvents();
            loadVoting();
        });
    </script>
</body>
</html>