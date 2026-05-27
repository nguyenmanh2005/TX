<?php
/**
 * 📖 Storyline Event - Hành Trình Khai Phá Trận Địa
 * Nơi người chơi theo dõi cốt truyện mỗi ngày và hoàn thành nhiệm vụ để nhận GTLM cực khủng.
 */
require_once 'db_connect.php';
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hành Trình Khai Phá Trận Địa | Storyline Event</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800;900&family=Bangers&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #8b5cf6;
            --primary-glow: rgba(139, 92, 246, 0.4);
            --bg: #030712;
            --card-bg: rgba(17, 24, 39, 0.7);
            --text: #f3f4f6;
            --accent: #ffd700;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            margin: 0;
            padding: 40px 20px;
            background-image: 
                radial-gradient(at 0% 0%, rgba(139, 92, 246, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(236, 72, 153, 0.1) 0px, transparent 50%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        h1 {
            font-size: 2.8rem;
            font-weight: 900;
            margin: 0;
            background: linear-gradient(to right, #a78bfa, #f472b6);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-back {
            color: #9ca3af;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s;
        }
        .btn-back:hover {
            color: #fff;
        }

        .grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 28px;
            padding: 40px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
            position: relative;
            overflow: hidden;
        }

        /* 🎭 Narrative Box */
        .story-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .chapter-tag {
            background: linear-gradient(90deg, var(--primary), #ec4899);
            padding: 6px 16px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 0 15px var(--primary-glow);
        }

        .chapter-title {
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            margin: 10px 0 0;
        }

        .story-content {
            font-size: 18px;
            line-height: 1.8;
            color: #d1d5db;
            margin-bottom: 40px;
            font-style: italic;
            position: relative;
            padding-left: 20px;
            border-left: 3px solid var(--primary);
        }

        /* 🎯 Mission Box */
        .mission-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .mission-title {
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--accent);
            letter-spacing: 1px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .progress-container {
            margin: 15px 0;
        }

        .progress-bar {
            height: 16px;
            background: rgba(0, 0, 0, 0.4);
            border-radius: 999px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.05);
            position: relative;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), #ec4899);
            border-radius: 999px;
            transition: width 0.8s ease-in-out;
        }

        .progress-text {
            font-size: 13px;
            font-weight: 700;
            color: #9ca3af;
            text-align: right;
            margin-top: 6px;
        }

        .btn-claim {
            display: block;
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%);
            border: none;
            border-radius: 16px;
            color: white;
            font-weight: 800;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 10px 20px var(--primary-glow);
            text-align: center;
        }
        .btn-claim:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px var(--primary-glow);
        }
        .btn-claim:disabled {
            background: rgba(255, 255, 255, 0.05);
            color: #4b5563;
            cursor: not-allowed;
            box-shadow: none;
        }

        /* 📋 Sidebar Chapters Timeline */
        .timeline-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 28px;
            padding: 30px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }

        .sidebar-title {
            font-weight: 800;
            font-size: 18px;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 25px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding-bottom: 15px;
        }

        .timeline-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
            position: relative;
        }

        .timeline-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.04);
            cursor: pointer;
            transition: all 0.2s;
        }

        .timeline-item:hover {
            background: rgba(255,255,255,0.04);
            transform: translateX(5px);
        }

        .timeline-item.active {
            background: rgba(139, 92, 246, 0.1);
            border-color: var(--primary);
        }

        .timeline-item.locked {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .chapter-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: bold;
            flex-shrink: 0;
        }

        .timeline-item.completed .chapter-icon {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid #10b981;
        }

        .timeline-item.active .chapter-icon {
            background: rgba(139, 92, 246, 0.2);
            color: #a78bfa;
            border: 1px solid var(--primary);
        }

        .timeline-item.locked .chapter-icon {
            background: rgba(255, 255, 255, 0.05);
            color: #6b7280;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .chapter-info {
            flex: 1;
        }

        .chapter-name {
            font-weight: 700;
            font-size: 14px;
            color: #fff;
        }

        .chapter-status {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 4px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .chapter-reward {
            font-size: 12px;
            font-weight: bold;
            color: var(--accent);
        }
    </style>
</head>
<body>

    <div class="container">
        <header>
            <div>
                <a href="events.php" class="btn-back"><i class="fa fa-arrow-left"></i> Quay lại Sự Kiện</a>
                <h1 id="page-title">Hành Trình Khai Phá Trận Địa</h1>
            </div>
        </header>

        <div class="grid">
            <!-- 📖 Left Column: Active Story Presentation -->
            <div class="card" id="storyPanel">
                <div style="text-align: center; padding: 40px 0; opacity: 0.5;">
                    <i class="fa fa-spinner fa-spin" style="font-size: 40px; color: var(--primary);"></i>
                    <p style="margin-top: 15px; font-weight: 600;">Đang giải mã ký ức trận địa...</p>
                </div>
            </div>

            <!-- 📋 Right Column: Chapters Timeline -->
            <div class="timeline-card">
                <div class="sidebar-title"><i class="fa fa-book-open"></i> Biên Niên Sử Chương</div>
                <div class="timeline-list" id="timelineList">
                    <!-- Chapter timeline will load here dynamically -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let eventChapters = [];
        let userProgressState = null;
        let selectedChapterNumber = null;

        async function loadStoryState() {
            try {
                const res = await fetch('api_storyline.php?action=get_state');
                const data = await res.json();
                
                if (!data.success) {
                    Swal.fire({
                        title: 'Thông báo',
                        text: data.message,
                        icon: 'info',
                        confirmButtonText: 'Quay lại'
                    }).then(() => window.location.href = 'index.php');
                    return;
                }

                eventChapters = data.chapters;
                userProgressState = data.state;
                
                // Mặc định chọn chương hiện tại đang unlock mà chưa hoàn thành, hoặc chương cuối cùng nếu đã hoàn thành hết
                if (selectedChapterNumber === null) {
                    selectedChapterNumber = Math.min(
                        userProgressState.completed_chapters + 1,
                        userProgressState.total_chapters
                    );
                }

                // ⚡ ĐỒNG BỘ THEME TỪ SỰ KIỆN MÙA (SEASONAL EVENT)
                if (data.seasonal_theme) {
                    const t = data.seasonal_theme;
                    if (t.primary) {
                        document.documentElement.style.setProperty('--primary', t.primary);
                        document.documentElement.style.setProperty('--primary-glow', t.primary + '66');
                    }
                    if (t.secondary) {
                        document.documentElement.style.setProperty('--accent', t.secondary);
                    }
                    if (t.bg) {
                        document.body.style.background = t.bg;
                        document.body.style.backgroundImage = `radial-gradient(at 0% 0%, ${t.primary}26 0px, transparent 50%), radial-gradient(at 100% 100%, ${t.secondary}1a 0px, transparent 50%)`;
                    }
                    if (t.name) {
                        document.getElementById('page-title').innerHTML = `${t.emoji || '📖'} ${t.name} - Cốt Truyện`;
                        document.title = `${t.name} | Storyline Event`;
                    }
                }

                renderTimeline();
                renderStoryChapter();
            } catch (err) {
                console.error(err);
                Swal.fire('Lỗi', 'Không thể đồng bộ dữ liệu cốt truyện!', 'error');
            }
        }

        function renderTimeline() {
            const list = document.getElementById('timelineList');
            list.innerHTML = eventChapters.map(ch => {
                const num = ch.chapter_number;
                let statusClass = 'locked';
                let statusText = 'Đang khóa';
                let icon = '<i class="fa fa-lock"></i>';

                if (num <= userProgressState.completed_chapters) {
                    statusClass = 'completed';
                    statusText = 'Đã hoàn thành';
                    icon = '<i class="fa fa-check"></i>';
                } else if (num <= userProgressState.unlocked_chapters) {
                    statusClass = 'active';
                    statusText = 'Đang mở';
                    icon = `<i class="fa fa-book"></i>`;
                }

                const isActiveSelection = (num === selectedChapterNumber) ? 'active' : '';

                return `
                    <div class="timeline-item ${statusClass} ${isActiveSelection}" onclick="selectChapter(${num}, '${statusClass}')">
                        <div class="chapter-icon">${icon}</div>
                        <div class="chapter-info">
                            <div class="chapter-name">Chương ${num}: ${ch.chapter_title}</div>
                            <div class="chapter-status">${statusText}</div>
                        </div>
                        <div class="chapter-reward">+${parseInt(ch.reward_money).toLocaleString()} GTLM</div>
                    </div>
                `;
            }).join('');
        }

        function selectChapter(num, statusClass) {
            if (statusClass === 'locked') {
                Swal.fire('Đang khóa', 'Hoàn thành các chương trước để mở khóa chương này!', 'warning');
                return;
            }
            selectedChapterNumber = num;
            renderTimeline();
            renderStoryChapter();
        }

        function renderStoryChapter() {
            const ch = eventChapters.find(c => c.chapter_number === selectedChapterNumber);
            if (!ch) return;

            const isCompleted = selectedChapterNumber <= userProgressState.completed_chapters;
            const isUnlocked = selectedChapterNumber <= userProgressState.unlocked_chapters;
            
            // Tính toán tiến trình
            const betsRequired = ch.target_bets;
            const betsPlaced = userProgressState.bets_placed_today;
            const pct = Math.min(100, (betsPlaced / betsRequired) * 100);

            let actionHtml = '';
            if (isCompleted) {
                actionHtml = `<button class="btn-claim" disabled><i class="fa fa-check-circle"></i> ĐÃ HOÀN THÀNH CHƯƠNG</button>`;
            } else if (isUnlocked) {
                const canClaim = betsPlaced >= betsRequired;
                actionHtml = `
                    <button class="btn-claim" ${canClaim ? '' : 'disabled'} onclick="claimChapterReward(${ch.chapter_number})">
                        ${canClaim ? '<i class="fa fa-gift"></i> NHẬN PHẦN THƯỞNG & ĐỌC CHƯƠNG TIẾP' : '<i class="fa fa-sword"></i> CHƯA ĐỦ ĐIỀU KIỆN HOÀN THÀNH'}
                    </button>
                `;
            }

            const panel = document.getElementById('storyPanel');
            panel.innerHTML = `
                <div class="story-header">
                    <div>
                        <span class="chapter-tag">Chương ${ch.chapter_number}</span>
                        <div class="chapter-title">${ch.chapter_title}</div>
                    </div>
                    <div style="font-weight: 800; color: var(--accent); font-size: 20px;">
                        🏆 +${parseInt(ch.reward_money).toLocaleString()} GTLM
                    </div>
                </div>
                
                <div class="story-content">
                    "${ch.story_text.replace(/\n/g, '<br>')}"
                </div>

                <div class="mission-box">
                    <div class="mission-title">
                        <i class="fa fa-compass animate-spin"></i> Nhiệm vụ chương này
                    </div>
                    <p style="font-size: 15px; margin: 0 0 10px; font-weight: 600;">
                        Bạn cần tham gia đặt ít nhất <b>${betsRequired}</b> lượt cược ở bất kỳ trò chơi nào trên trận địa hôm nay.
                    </p>
                    <div class="progress-container">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${pct}%"></div>
                        </div>
                        <div class="progress-text"> Tiến độ hôm nay: ${betsPlaced} / ${betsRequired} cược (${Math.round(pct)}%)</div>
                    </div>
                </div>

                ${actionHtml}
            `;
        }

        async function claimChapterReward(num) {
            Swal.fire({
                title: 'Đang mở hòm...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            try {
                const res = await fetch('api_storyline.php?action=claim', {
                    method: 'POST',
                    headers: { 'Content-Type:': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ 'chapter_number': num })
                });
                const data = await res.json();

                if (data.success) {
                    Swal.fire({
                        title: 'HÚP GIAO DỊCH!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonText: 'Đọc tiếp'
                    }).then(() => {
                        selectedChapterNumber = null; // Tự động load chương kế
                        loadStoryState();
                    });
                } else {
                    Swal.fire('Thất bại', data.message, 'error');
                }
            } catch (err) {
                console.error(err);
                Swal.fire('Lỗi', 'Không thể hoàn tất giao dịch!', 'error');
            }
        }

        $(document).ready(() => {
            loadStoryState();
        });
    </script>
</body>
</html>
