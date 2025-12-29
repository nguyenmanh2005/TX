<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

// Load theme
require_once 'load_theme.php';

$userId = $_SESSION['Iduser'];

// Lấy thông tin người dùng
$sql = "SELECT Iduser, Name, Money FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Kiểm tra bảng tồn tại
$checkQuestsTable = $conn->query("SHOW TABLES LIKE 'quests'");
$questsTableExists = $checkQuestsTable && $checkQuestsTable->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhiệm Vụ - Quests</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/animations.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            animation: fadeIn 0.6s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .quests-container {
            max-width: 1200px;
            margin: 0 auto;
            animation: slideUp 0.6s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header-quests {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 24px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3),
                        0 0 0 1px rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
            animation: fadeInDown 0.6s ease;
            position: relative;
            overflow: hidden;
        }
        
        .header-quests::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.05) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        .header-quests h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 42px;
            font-weight: 800;
            letter-spacing: -1px;
        }
        
        .quests-tabs {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            background: rgba(247, 247, 247, 0.8);
            padding: 8px;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .tab-button {
            flex: 1;
            padding: 18px 24px;
            background: transparent;
            color: #666;
            border: 2px solid transparent;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .tab-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: left 0.3s ease;
            z-index: -1;
        }
        
        .tab-button.active {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .tab-button.active::before {
            left: 0;
        }
        
        .tab-button:hover:not(.active) {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            transform: translateY(-2px);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .quests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .quest-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 3px solid transparent;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .quest-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.1) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        
        .quest-card.completed {
            border-color: #28a745;
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.15) 0%, rgba(255, 255, 255, 0.98) 100%);
        }

        .quest-card.completed::before {
            background: radial-gradient(circle, rgba(40, 167, 69, 0.15) 0%, transparent 70%);
        }
        
        .quest-card.claimed {
            opacity: 0.6;
            border-color: #ccc;
        }
        
        .quest-card:hover {
            transform: translateY(-10px) scale(1.04) rotate(1deg);
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.25),
                        0 0 0 1px rgba(102, 126, 234, 0.1);
        }

        .quest-card:hover::before {
            opacity: 1;
        }
        
        .quest-card.completed:hover {
            box-shadow: 0 18px 45px rgba(40, 167, 69, 0.3),
                        0 0 30px rgba(40, 167, 69, 0.2);
        }
        
        .quest-icon {
            font-size: 56px;
            text-align: center;
            margin-bottom: 20px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.1));
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .quest-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
            text-align: center;
        }
        
        .quest-description {
            color: var(--text-dark);
            margin-bottom: 15px;
            text-align: center;
            min-height: 50px;
        }
        
        .quest-progress {
            margin: 15px 0;
        }
        
        .progress-bar {
            width: 100%;
            height: 25px;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color) 0%, var(--secondary-color) 100%);
            border-radius: 15px;
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 600;
        }
        
        .quest-reward {
            text-align: center;
            margin-top: 15px;
            font-weight: 600;
            color: var(--success-color);
            font-size: 18px;
        }
        
        .claim-button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--success-color) 0%, #27ae60 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: all 0.3s ease;
            margin-top: 15px;
        }
        
        .claim-button:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
        }
        
        .claim-button:disabled {
            opacity: 0.5;
            cursor: not-allowed !important;
        }
        
        .completed-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--success-color);
            color: white;
            padding: 5px 10px;
            border-radius: var(--border-radius);
            font-size: 12px;
            font-weight: 600;
        }
        
        .claimed-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--text-light);
            color: white;
            padding: 5px 10px;
            border-radius: var(--border-radius);
            font-size: 12px;
            font-weight: 600;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .back-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            font-size: 18px;
            color: var(--text-dark);
        }
    </style>
</head>
<body>
    <div class="quests-container">
        <div class="header-quests">
            <h1>🎯 Nhiệm Vụ - Quests</h1>
            <div style="margin-top: 15px; font-size: 18px; color: var(--success-color); font-weight: 600;">
                💰 Số dư: <?php echo number_format($user['Money'], 0, ',', '.'); ?> VNĐ (ảo)
            </div>
            <div style="margin-top: 10px; font-size: 14px; color: var(--warning-color); font-weight: 600; background: rgba(255, 193, 7, 0.1); padding: 10px; border-radius: var(--border-radius); border: 2px solid var(--warning-color);">
                ⚠️ Lưu ý: Web không hỗ trợ nạp/rút tiền thật. Tất cả tiền trong web đều là ảo, chỉ dùng để chơi game giải trí.
            </div>
            <?php if (!$questsTableExists): ?>
                <div style="background: rgba(220, 53, 69, 0.2); border: 2px solid #dc3545; color: #dc3545; padding: 15px; border-radius: var(--border-radius); margin: 20px 0; font-weight: 600;">
                    ⚠️ Hệ thống nhiệm vụ chưa được kích hoạt. Vui lòng chạy file <strong>create_quests_tables.sql</strong> trong database.
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($questsTableExists): ?>
        <div class="quests-tabs">
            <button class="tab-button active" onclick="switchTab('daily')">📅 Nhiệm Vụ Hàng Ngày</button>
            <button class="tab-button" onclick="switchTab('weekly')">📆 Nhiệm Vụ Hàng Tuần</button>
        </div>
        
        <!-- Daily Quests Tab -->
        <div id="daily-tab" class="tab-content active">
            <div class="loading" id="daily-loading">Đang tải nhiệm vụ...</div>
            <div class="quests-grid" id="daily-quests" style="display: none;"></div>
        </div>
        
        <!-- Weekly Quests Tab -->
        <div id="weekly-tab" class="tab-content">
            <div class="loading" id="weekly-loading">Đang tải nhiệm vụ...</div>
            <div class="quests-grid" id="weekly-quests" style="display: none;"></div>
        </div>
        <?php endif; ?>
        
        <a href="index.php" class="back-link">🏠 Về Trang Chủ</a>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
            
            const interactiveElements = document.querySelectorAll('button, a, input, label, select');
            interactiveElements.forEach(el => {
                el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            });
            
            // Load quests
            loadQuests('daily');
            loadQuests('weekly');
        });
        
        function switchTab(tabType) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            const targetTab = document.getElementById(tabType + '-tab');
            if (targetTab) {
                targetTab.classList.add('active');
            }
            
            // Add active class to clicked button
            if (event && event.target) {
                event.target.classList.add('active');
            }
        }
        
        function loadQuests(type) {
            const loadingEl = document.getElementById(type + '-loading');
            const questsEl = document.getElementById(type + '-quests');
            
            fetch(`api_quests.php?action=get_quests&type=${type}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        loadingEl.style.display = 'none';
                        questsEl.style.display = 'grid';
                        renderQuests(questsEl, data.quests, type);
                    } else {
                        loadingEl.innerHTML = '❌ ' + data.message;
                    }
                })
                .catch(error => {
                    console.error('Error loading quests:', error);
                    loadingEl.innerHTML = '❌ Có lỗi xảy ra khi tải nhiệm vụ';
                });
        }
        
        function renderQuests(container, quests, type) {
            container.innerHTML = '';
            
            if (quests.length === 0) {
                container.innerHTML = '<div class="loading">Chưa có nhiệm vụ nào</div>';
                return;
            }
            
            quests.forEach(quest => {
                const card = document.createElement('div');
                card.className = 'quest-card';
                if (quest.is_completed) card.classList.add('completed');
                if (quest.is_claimed) card.classList.add('claimed');
                
                const progressPercent = quest.progress_percent || 0;
                const progressText = `${Math.min(quest.user_progress, quest.requirement_value)} / ${quest.requirement_value}`;
                
                card.innerHTML = `
                    ${quest.is_claimed ? '<div class="claimed-badge">✓ Đã nhận</div>' : ''}
                    ${quest.is_completed && !quest.is_claimed ? '<div class="completed-badge">✓ Hoàn thành</div>' : ''}
                    <div class="quest-icon">${quest.icon || '🎯'}</div>
                    <div class="quest-name">${escapeHtml(quest.name)}</div>
                    <div class="quest-description">${escapeHtml(quest.description)}</div>
                    <div class="quest-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${progressPercent}%">
                                ${progressPercent >= 15 ? progressText : ''}
                            </div>
                        </div>
                    </div>
                    <div class="quest-reward">
                        💰 Phần thưởng: ${number_format(quest.reward_money, 0, ',', '.')} VNĐ
                    </div>
                    ${quest.is_completed && !quest.is_claimed ? 
                        `<button class="claim-button" onclick="claimReward(${quest.id}, '${type}')">Nhận Phần Thưởng</button>` : 
                        quest.is_claimed ? 
                        `<button class="claim-button" disabled>Đã nhận phần thưởng</button>` :
                        `<button class="claim-button" disabled>Chưa hoàn thành</button>`
                    }
                `;
                
                container.appendChild(card);
            });
        }
        
        function claimReward(questId, questType) {
            const formData = new FormData();
            formData.append('action', 'claim_reward');
            formData.append('quest_id', questId);
            formData.append('quest_type', questType);
            
            fetch('api_quests.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('🎉 ' + data.message + '\n💰 Bạn nhận được: ' + number_format(data.reward_money, 0, ',', '.') + ' VNĐ');
                    // Reload quests
                    loadQuests(questType);
                    // Reload page to update balance
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    alert('❌ ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error claiming reward:', error);
                alert('❌ Có lỗi xảy ra khi nhận phần thưởng');
            });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function number_format(number, decimals, dec_point, thousands_sep) {
            number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
            const n = !isFinite(+number) ? 0 : +number;
            const prec = !isFinite(+decimals) ? 0 : Math.abs(decimals);
            const sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep;
            const dec = (typeof dec_point === 'undefined') ? '.' : dec_point;
            let s = '';
            
            const toFixedFix = function (n, prec) {
                const k = Math.pow(10, prec);
                return '' + Math.round(n * k) / k;
            };
            
            s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
            if (s[0].length > 3) {
                s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
            }
            if ((s[1] || '').length < prec) {
                s[1] = s[1] || '';
                s[1] += new Array(prec - s[1].length + 1).join('0');
            }
            return s.join(dec);
        }
    </script>
</body>
</html>

