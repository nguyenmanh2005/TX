<?php
/**
 * 🛡️ Bot Army Control Center (Dashboard) v15.7
 * Features: Multi-Game Stats, Economy Chart, Detailed Bot Inventory
 */
session_start();
require_once '../db_connect.php';

$syncFile = 'sessions/bot_sync.json';
$historyFile = 'sessions/economy_history.json';
$syncData = file_exists($syncFile) ? json_decode(file_get_contents($syncFile), true) : [];
$history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
$sessionFiles = glob('sessions/*.state.json');

// 1. Phân tích trạng thái bot từ Files
$botStates = [];
foreach ($sessionFiles as $file) {
    $emailMd5 = str_replace(['sessions/', '.state.json'], '', $file);
    $botStates[$emailMd5] = json_decode(file_get_contents($file), true);
}

$stats = [
    'total' => count($sessionFiles),
    'active' => 0,
    'moods' => ['happy' => 0, 'excited' => 0, 'tilted' => 0, 'depressed' => 0],
    'total_bot_money' => 0,
    'total_human_money' => 0,
];

// 2. Lấy dữ liệu Bot từ Database (Join Frames)
$botsList = [];
$sql = "SELECT u.Iduser, u.Name, u.Email, u.Money, u.ImageURL, 
               cf.frame_name as chat_frame_name, 
               af.frame_name as avatar_frame_name,
               ach.name as title_name
        FROM users u 
        LEFT JOIN chat_frames cf ON u.chat_frame_id = cf.id 
        LEFT JOIN avatar_frames af ON u.avatar_frame_id = af.id
        LEFT JOIN achievements ach ON u.active_title_id = ach.id
        WHERE u.Email LIKE '%bot%' 
        ORDER BY u.Iduser ASC";
$res = $conn->query($sql);

while($row = $res->fetch_assoc()) {
    $emailMd5 = md5($row['Email']);
    $state = $botStates[$emailMd5] ?? ['wins'=>0, 'mood'=>'happy'];
    
    // Thống kê nhanh
    $stats['total_bot_money'] += $row['Money'];
    $currentMood = $state['mood'] ?? 'happy';
    if (isset($stats['moods'][$currentMood])) $stats['moods'][$currentMood]++;
    
    $row['state'] = $state;
    $row['state']['mood'] = $currentMood; // Đảm bảo luôn có mood để hiển thị
    $botsList[] = $row;
}

// Lấy tổng tiền người thật
$humanRes = $conn->query("SELECT SUM(Money) as total FROM users WHERE Email NOT LIKE '%bot%'")->fetch_assoc();
$stats['total_human_money'] = (float)($humanRes['total'] ?? 0);

// Lấy dữ liệu biểu đồ
$chartLabels = []; $chartBotData = []; $chartHumanData = [];
foreach ($history as $point) {
    $chartLabels[] = $point['time'];
    $chartBotData[] = $point['bot'];
    $chartHumanData[] = $point['human'];
}

// Lấy 20 dòng log mới nhất
$logFile = 'logs/' . date('Y-m-d') . '.log';
$recentLogs = [];
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recentLogs = array_slice($lines, -15);
    $recentLogs = array_reverse($recentLogs);
}

// Kiểm tra xem có phải request AJAX không
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'stats' => $stats,
        'chartLabels' => $chartLabels,
        'chartBotData' => $chartBotData,
        'chartHumanData' => $chartHumanData,
        'recentLogs' => $recentLogs,
        'botsList' => $botsList
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>🛡️ Bot Control Center v15.7</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --primary: #6366f1; --secondary: #a855f7; --success: #22c55e; --danger: #ef4444; --warning: #f59e0b; --bg: #0f172a; --card: rgba(255,255,255,0.05); }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: white; margin: 0; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding: 20px; background: var(--card); border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); }
        .grid-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--card); padding: 20px; border-radius: 24px; border: 1px solid rgba(255, 255, 255, 0.1); }
        .stat-value { font-size: 28px; font-weight: 700; margin: 5px 0; }
        .stat-label { color: #94a3b8; font-size: 12px; text-transform: uppercase; }
        .main-layout { display: grid; grid-template-columns: 1fr 350px; gap: 20px; margin-bottom: 30px; }
        .panel { background: var(--card); border-radius: 24px; padding: 20px; border: 1px solid rgba(255, 255, 255, 0.1); }
        
        /* Bot Grid Style Optimized */
        .bot-list-panel { margin-top: 30px; }
        .bot-grid-container { 
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 15px;
            max-height: 800px;
            overflow-y: auto;
            padding: 10px;
            scrollbar-width: thin;
        }
        .bot-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 15px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .bot-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.06);
            border-color: var(--primary);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .bot-card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .bot-avatar { width: 45px; height: 45px; border-radius: 12px; object-fit: cover; border: 2px solid rgba(255,255,255,0.1); }
        .bot-info h3 { margin: 0; font-size: 15px; font-weight: 600; }
        .bot-info span { font-size: 10px; color: #94a3b8; }
        
        .bot-stats { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .money { color: var(--warning); font-weight: 700; font-size: 14px; }
        .wins { font-size: 12px; color: var(--primary); }
        
        .mood-badge { font-size: 9px; padding: 2px 6px; border-radius: 5px; text-transform: uppercase; font-weight: 800; }
        .mood-happy { background: #22c55e; color: white; }
        .mood-excited { background: #f59e0b; color: white; }
        .mood-tilted { background: #ef4444; color: white; }
        
        .inventory-tags { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 8px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 8px; }
        .tag { font-size: 9px; background: rgba(99, 102, 241, 0.1); color: #a5b4fc; padding: 2px 6px; border-radius: 4px; }
        .btn { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 12px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1 style="margin:0; font-size: 24px;">🛡️ Bot Army Control Center v15.7</h1>
                <span style="color: var(--success); font-size: 12px;">● Đang giám sát <?= $stats['total'] ?> thành viên Thiên Thần & Ác Quỷ</span>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="bot_engine.php" target="_blank" class="btn" style="background: var(--success)">⚡ Kích hoạt Engine</a>
                <a href="bot_manager.php?action=spawn" class="btn" style="background: var(--warning)">➕ Sinh Bot</a>
            </div>
        </div>

        <div class="grid-stats">
            <div class="stat-card">
                <div class="stat-label">Tổng Tài Sản Quân Đoàn</div>
                <div class="stat-value" style="color: var(--warning)"><?= number_format($stats['total_bot_money']) ?> GTLM</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Tỉ Lệ Lạm Phát</div>
                <?php $inflation = ($stats['total_bot_money'] / ($stats['total_human_money'] ?: 1)) * 100; ?>
                <div class="stat-value" style="color: <?= $inflation > 100 ? 'var(--danger)' : 'var(--success)' ?>">
                    <?= number_format($inflation, 1) ?>%
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Tâm Trạng Chủ Đạo</div>
                <div class="stat-value" style="color: var(--secondary)">Hạnh Phúc</div>
            </div>
        </div>

        <div class="main-layout">
            <div class="left-col">
                <div class="panel" style="margin-bottom: 20px;">
                    <h2 style="font-size: 18px; margin-top:0;">📈 Biến Động Tài Sản (Lịch Sử)</h2>
                    <div style="height: 350px;">
                        <canvas id="wealthLineChart"></canvas>
                    </div>
                </div>
                <div class="panel">
                    <h2 style="font-size: 18px; margin-top:0;">⚡ Hoạt Động Trực Tiếp (Live Logs)</h2>
                    <div id="liveLogs" style="height: 250px; overflow-y: auto; font-family: monospace; font-size: 11px; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 15px; line-height: 1.6;">
                        <?php foreach ($recentLogs as $log): ?>
                            <div style="margin-bottom: 4px; border-bottom: 1px solid rgba(255,255,255,0.03); padding-bottom: 2px;">
                                <?= htmlspecialchars($log) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="right-col">
                <div class="panel" style="margin-bottom: 20px;">
                    <h2 style="font-size: 18px; margin-top:0;">💰 Cán Cân Hiện Tại</h2>
                    <div style="height: 250px;">
                        <canvas id="economyChart"></canvas>
                    </div>
                </div>
                <div class="panel">
                    <h2 style="font-size: 18px; margin-top:0;">🎭 Phân bổ Tâm trạng</h2>
                    <div style="height: 250px;">
                        <canvas id="moodChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin: 30px 0 15px;">
            <h2 style="font-size: 20px; margin: 0;">📂 Quân Đoàn Chi Tiết (Grid View)</h2>
            <div style="position: relative; width: 300px;">
                <input type="text" id="searchBotInput" placeholder="🔍 Tìm tên hoặc ID bot..." 
                       style="width: 100%; padding: 10px 15px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: white; outline: none; transition: all 0.3s ease;">
            </div>
        </div>

        <div class="bot-grid-container" id="botGrid">
            <?php foreach ($botsList as $bot): ?>
                <div class="bot-card" data-name="<?= strtolower($bot['Name']) ?>" data-id="<?= $bot['Iduser'] ?>">
                    <div class="bot-card-header">
                        <img src="<?= $bot['ImageURL'] ?>" class="bot-avatar">
                        <div class="bot-info">
                            <h3><?= $bot['Name'] ?></h3>
                            <span>ID: #<?= $bot['Iduser'] ?></span>
                        </div>
                        <div style="margin-left: auto;">
                            <span class="mood-badge mood-<?= $bot['state']['mood'] ?? 'happy' ?>">
                                <?= $bot['state']['mood'] ?? 'happy' ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="bot-stats">
                        <div class="money"><?= number_format($bot['Money']) ?> GTLM</div>
                        <div class="wins">🏆 <?= $bot['state']['wins'] ?? 0 ?></div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px;">
                        <div class="inventory-tags" style="margin-top: 0; border: none; padding: 0;">
                            <?php if ($bot['chat_frame_name']): ?>
                                <span class="tag">💬</span>
                            <?php endif; ?>
                            <?php if ($bot['avatar_frame_name']): ?>
                                <span class="tag">🖼️</span>
                            <?php endif; ?>
                        </div>
                        <button class="btn" style="padding: 4px 10px; font-size: 10px; background: rgba(99,102,241,0.2); border: 1px solid var(--primary);" 
                                onclick="showBotDetail(<?= $bot['Iduser'] ?>, '<?= $bot['Name'] ?>')">
                            Soi Kho 🎒
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 🎒 Modal Soi Kho Đồ Bot -->
    <div id="botModal" style="display:none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; backdrop-filter: blur(10px); align-items:center; justify-content:center;">
        <div style="background: var(--bg); width: 600px; max-height: 80vh; border-radius: 30px; border: 1px solid var(--primary); padding: 30px; position: relative; overflow-y: auto; box-shadow: 0 0 50px rgba(99,102,241,0.3);">
            <button onclick="closeModal()" style="position: absolute; top: 20px; right: 20px; background: none; border: none; color: #94a3b8; font-size: 24px; cursor: pointer;">&times;</button>
            <h2 id="modalTitle" style="color: var(--primary); margin-top: 0;">🎒 Kho Đồ Bot</h2>
            <hr style="border: 0; border-top: 1px solid rgba(255,255,255,0.1); margin: 20px 0;">
            <div id="modalContent">
                <div style="text-align: center; padding: 40px; color: #94a3b8;">Đang lục lọi túi đồ...</div>
            </div>
        </div>
    </div>

    <script>
        function showBotDetail(botId, botName) {
            const modal = document.getElementById('botModal');
            const title = document.getElementById('modalTitle');
            const content = document.getElementById('modalContent');
            
            title.innerText = `🎒 Kho Đồ của ${botName}`;
            modal.style.display = 'flex';
            content.innerHTML = '<div style="text-align: center; padding: 40px; color: #94a3b8;">Đang lục lọi túi đồ...</div>';

            fetch(`api_bot_inventory.php?bot_id=${botId}`)
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        const d = res.data;
                        let html = '';
                        
                        const renderSection = (title, items, icon) => {
                            if (items.length === 0) return '';
                            return `<div style="margin-bottom: 20px;">
                                <h4 style="color: var(--warning); border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 5px;">${icon} ${title}</h4>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    ${items.map(i => `<span class="tag" style="padding: 5px 10px; font-size: 12px; background: rgba(255,255,255,0.05);">${i.name || i.icon || ''} ${i.name || ''}</span>`).join('')}
                                </div>
                            </div>`;
                        };

                        html += renderSection('Themes', d.themes, '🎨');
                        html += renderSection('Cursors', d.cursors, '🖱️');
                        html += renderSection('Khung Chat', d.chat_frames, '💬');
                        html += renderSection('Khung Avatar', d.avatar_frames, '🖼️');
                        html += renderSection('Thành Tựu', d.achievements, '🏆');

                        // 📈 Lịch sử chơi
                        html += `<div style="margin-top: 30px;">
                            <h4 style="color: var(--primary); border-bottom: 2px solid var(--primary); padding-bottom: 5px;">📈 Lịch Sử Chơi Gần Đây</h4>
                            <table style="width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px;">
                                <thead>
                                    <tr style="text-align: left; color: #94a3b8;">
                                        <th style="padding: 8px;">Game</th>
                                        <th style="padding: 8px;">Tiền</th>
                                        <th style="padding: 8px;">Kết quả</th>
                                        <th style="padding: 8px;">Thời gian</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${d.history.map(h => `
                                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                            <td style="padding: 8px; font-weight: 600;">${h.game}</td>
                                            <td style="padding: 8px;">${h.bet.toLocaleString()}</td>
                                            <td style="padding: 8px; color: ${h.result === 'win' ? '#22c55e' : '#ef4444'}; font-weight: 800;">
                                                ${h.result.toUpperCase()}
                                            </td>
                                            <td style="padding: 8px; color: #94a3b8;">${h.time}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                            ${d.history.length === 0 ? '<div style="text-align: center; padding: 20px; color: #64748b;">Chưa có dữ liệu ván đấu.</div>' : ''}
                        </div>`;

                        if (html === '') html = '<div style="text-align: center; padding: 40px; color: #94a3b8;">Bot này nghèo lắm, chưa có gì cả!</div>';
                        content.innerHTML = html;
                    }
                });
        }

        function closeModal() {
            document.getElementById('botModal').style.display = 'none';
        }

        // Đóng modal khi click ra ngoài
        window.onclick = function(event) {
            const modal = document.getElementById('botModal');
            if (event.target == modal) closeModal();
        }

        // 📈 Wealth History Chart
        const lineCtx = document.getElementById('wealthLineChart').getContext('2d');
        let wealthChart = new Chart(lineCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [
                    {
                        label: 'Bot Army Wealth',
                        data: <?= json_encode($chartBotData) ?>,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Human Wealth',
                        data: <?= json_encode($chartHumanData) ?>,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#94a3b8' } } },
                scales: {
                    x: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,0.05)' } },
                    y: { ticks: { color: '#64748b' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                }
            }
        });

        const ecoCtx = document.getElementById('economyChart').getContext('2d');
        let ecoChart = new Chart(ecoCtx, {
            type: 'doughnut',
            data: {
                labels: ['Tiền Bot', 'Tiền Người Thật'],
                datasets: [{
                    data: [<?= $stats['total_bot_money'] ?>, <?= $stats['total_human_money'] ?>],
                    backgroundColor: ['#6366f1', '#22c55e'],
                    borderWidth: 0,
                    hoverOffset: 20
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#94a3b8', font: { family: 'Outfit', size: 12 } } }
                },
                cutout: '70%'
            }
        });

        const moodCtx = document.getElementById('moodChart').getContext('2d');
        let moodChart = new Chart(moodCtx, {
            type: 'doughnut',
            data: {
                labels: ['Hạnh phúc', 'Hưng phấn', 'Cay cú', 'Trầm cảm'],
                datasets: [{ 
                    data: [<?= $stats['moods']['happy'] ?>, <?= $stats['moods']['excited'] ?>, <?= $stats['moods']['tilted'] ?>, <?= $stats['moods']['depressed'] ?>], 
                    backgroundColor: ['#22c55e', '#f59e0b', '#ef4444', '#64748b'], 
                    borderWidth: 0 
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8' } } } 
            }
        });

        // 🔄 Auto Refresh Logic
        async function refreshDashboard() {
            try {
                const response = await fetch('index.php?ajax=1');
                const data = await response.json();

                // Update Stats
                document.querySelectorAll('.stat-value')[0].innerText = new Intl.NumberFormat().format(data.stats.total_bot_money) + ' GTLM';
                const inflation = (data.stats.total_bot_money / (data.stats.total_human_money || 1)) * 100;
                const inflEl = document.querySelectorAll('.stat-value')[1];
                inflEl.innerText = inflation.toFixed(1) + '%';
                inflEl.style.color = inflation > 100 ? 'var(--danger)' : 'var(--success)';

                // Update Logs
                const logEl = document.getElementById('liveLogs');
                if (logEl) {
                    logEl.innerHTML = data.recentLogs.map(log => `<div style="margin-bottom: 4px; border-bottom: 1px solid rgba(255,255,255,0.03); padding-bottom: 2px;">${log}</div>`).join('');
                }

                // Update Charts
                wealthChart.data.labels = data.chartLabels;
                wealthChart.data.datasets[0].data = data.chartBotData;
                wealthChart.data.datasets[1].data = data.chartHumanData;
                wealthChart.update();

                ecoChart.data.datasets[0].data = [data.stats.total_bot_money, data.stats.total_human_money];
                ecoChart.update();

                moodChart.data.datasets[0].data = [data.stats.moods.happy, data.stats.moods.excited, data.stats.moods.tilted, data.stats.moods.depressed];
                moodChart.update();

            } catch (error) {
                console.error('Refresh failed:', error);
            }
        }

        setInterval(refreshDashboard, 10000); // Refresh every 10s

        // 🔍 Real-time Search Logic
        const searchInput = document.getElementById('searchBotInput');
        const botCards = document.querySelectorAll('.bot-card');

        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            
            botCards.forEach(card => {
                const name = card.getAttribute('data-name');
                const id = card.getAttribute('data-id');
                
                if (name.includes(query) || id.includes(query)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Tự động focus vào ô tìm kiếm khi nhấn phím '/'
        document.addEventListener('keydown', (e) => {
            if (e.key === '/' && document.activeElement !== searchInput) {
                e.preventDefault();
                searchInput.focus();
            }
        });
    </script>
</body>
</html>
