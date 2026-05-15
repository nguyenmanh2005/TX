<?php
/**
 * 🚀 Bot Money Buff Utility v1.0
 * Modes: Random, Targeted, Group
 */
require_once __DIR__ . '/../db_connect.php';
$config = require __DIR__ . '/config.php';

// --- AJAX HANDLER ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'get_bots') {
        $res = $conn->query("SELECT Iduser, Name, Email, Money FROM users WHERE Email REGEXP '^bot[0-9]+@' ORDER BY Name ASC");
        $bots = [];
        while($row = $res->fetch_assoc()) $bots[] = $row;
        echo json_encode($bots);
        exit;
    }

    if ($action === 'execute_buff') {
        $mode = $_POST['mode'] ?? '';
        $max_cap = (float)($_POST['max_cap'] ?? 10000000); // 10M default cap
        $results = ['success' => 0, 'total_buffed' => 0, 'details' => []];

        if ($mode === 'random') {
            $count = (int)$_POST['count'];
            $min = (float)$_POST['min'];
            $max = (float)$_POST['max'];
            
            $res = $conn->query("SELECT Iduser, Name, Money FROM users WHERE Email REGEXP '^bot[0-9]+@' ORDER BY RAND() LIMIT $count");
            while($bot = $res->fetch_assoc()) {
                $amount = rand($min, $max);
                // Check cap
                if ($bot['Money'] + $amount > $max_cap) $amount = max(0, $max_cap - $bot['Money']);
                
                if ($amount > 0) {
                    $conn->query("UPDATE users SET Money = Money + $amount WHERE Iduser = {$bot['Iduser']}");
                    $results['success']++;
                    $results['total_buffed'] += $amount;
                    $results['details'][] = "Buffed {$bot['Name']} +".number_format($amount);
                }
            }
        } 
        elseif ($mode === 'target') {
            $botId = (int)$_POST['bot_id'];
            $amount = (float)$_POST['amount'];
            
            $bot = $conn->query("SELECT Name, Money FROM users WHERE Iduser = $botId")->fetch_assoc();
            if ($bot) {
                if ($bot['Money'] + $amount > $max_cap) $amount = max(0, $max_cap - $bot['Money']);
                if ($amount > 0) {
                    $conn->query("UPDATE users SET Money = Money + $amount WHERE Iduser = $botId");
                    $results['success']++;
                    $results['total_buffed'] += $amount;
                    $results['details'][] = "Buffed {$bot['Name']} +".number_format($amount);
                }
            }
        }
        elseif ($mode === 'group') {
            $threshold = (float)$_POST['threshold'];
            $target_amount = (float)$_POST['target_amount'];
            
            $res = $conn->query("SELECT Iduser, Name, Money FROM users WHERE Email REGEXP '^bot[0-9]+@' AND Money < $threshold");
            while($bot = $res->fetch_assoc()) {
                $diff = $target_amount - $bot['Money'];
                if ($diff > 0) {
                    $conn->query("UPDATE users SET Money = $target_amount WHERE Iduser = {$bot['Iduser']}");
                    $results['success']++;
                    $results['total_buffed'] += $diff;
                    $results['details'][] = "Restored {$bot['Name']} to ".number_format($target_amount);
                }
            }
        }

        // Log to file
        $logMsg = "[" . date('Y-m-d H:i:s') . "] Mode: $mode, Success: {$results['success']}, Total: ".number_format($results['total_buffed']) . PHP_EOL;
        file_put_contents(__DIR__ . '/logs/buff_history.log', $logMsg, FILE_APPEND);

        echo json_encode($results);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>🚀 Bot Army Buff Money</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #6366f1; --bg: #0f172a; --panel: #1e293b; --text: #f8fafc; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 40px; }
        .container { max-width: 600px; margin: 0 auto; background: var(--panel); padding: 30px; border-radius: 24px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
        h1 { font-weight: 800; text-align: center; margin-bottom: 30px; background: linear-gradient(135deg, #818cf8, #c084fc); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
        
        .tabs { display: flex; gap: 10px; margin-bottom: 25px; background: rgba(0,0,0,0.2); padding: 5px; border-radius: 12px; }
        .tab-btn { flex: 1; padding: 10px; border: none; background: transparent; color: #94a3b8; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s; }
        .tab-btn.active { background: var(--primary); color: white; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }
        
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 13px; color: #94a3b8; margin-bottom: 8px; font-weight: 600; }
        input, select { width: 100%; background: #0f172a; border: 1px solid rgba(255,255,255,0.1); padding: 12px; border-radius: 10px; color: white; box-sizing: border-box; }
        
        .btn-submit { width: 100%; background: linear-gradient(135deg, #6366f1, #a855f7); color: white; border: none; padding: 15px; border-radius: 12px; font-weight: 800; cursor: pointer; transition: transform 0.2s; margin-top: 10px; }
        .btn-submit:active { transform: scale(0.98); }
        
        #result { margin-top: 25px; padding: 20px; border-radius: 12px; background: rgba(0,0,0,0.2); font-size: 13px; display: none; }
        .success-text { color: #4ade80; font-weight: 800; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #64748b; text-decoration: none; font-size: 13px; }
    </style>
</head>
<body>

<div class="container">
    <h1>💰 Buff  Gtlm Quân Đoàn</h1>

    <div class="tabs">
        <button class="tab-btn active" onclick="switchMode('random', this)">Ngẫu nhiên</button>
        <button class="tab-btn" onclick="switchMode('target', this)">Chỉ định</button>
        <button class="tab-btn" onclick="switchMode('group', this)">Theo nhóm</button>
    </div>

    <form id="buffForm">
        <input type="hidden" name="mode" id="buffMode" value="random">

        <!-- RANDOM MODE -->
        <div id="random_fields">
            <div class="form-group">
                <label>Số lượng Bot (Max: <?= count($config['bot_emails']) ?>)</label>
                <input type="number" name="count" value="10">
            </div>
            <div style="display: flex; gap: 10px;">
                <div class="form-group" style="flex:1;">
                    <label> Gtlm tối thiểu (Min)</label>
                    <input type="number" name="min" value="100000">
                </div>
                <div class="form-group" style="flex:1;">
                    <label> Gtlm tối đa (Max)</label>
                    <input type="number" name="max" value="1000000">
                </div>
            </div>
        </div>

        <!-- TARGET MODE -->
        <div id="target_fields" style="display:none;">
            <div class="form-group">
                <label>Chọn Bot</label>
                <select name="bot_id" id="botSelect">
                    <option value="">Đang tải danh sách...</option>
                </select>
            </div>
            <div class="form-group">
                <label>Số  Gtlm Buff cụ thể</label>
                <input type="number" name="amount" value="1000000">
            </div>
        </div>

        <!-- GROUP MODE -->
        <div id="group_fields" style="display:none;">
            <div class="form-group">
                <label>Dưới ngưỡng tài sản (Dưới X sẽ được buff)</label>
                <input type="number" name="threshold" value="500000">
            </div>
            <div class="form-group">
                <label>Bơm lên mức (Sau buff sẽ có X)</label>
                <input type="number" name="target_amount" value="1000000">
            </div>
        </div>

        <div class="form-group">
            <label>Giới hạn trần (Max Cap - Tránh lạm phát)</label>
            <input type="number" name="max_cap" value="10000000">
        </div>

        <button type="submit" class="btn-submit">🚀 THỰC THI BUFF  Gtlm</button>
    </form>

    <div id="result"></div>

    <a href="index.php" class="back-link">← Quay lại Dashboard</a>
</div>

<script>
    function switchMode(mode, btn) {
        document.getElementById('buffMode').value = mode;
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        document.getElementById('random_fields').style.display = (mode === 'random' ? 'block' : 'none');
        document.getElementById('target_fields').style.display = (mode === 'target' ? 'block' : 'none');
        document.getElementById('group_fields').style.display = (mode === 'group' ? 'block' : 'none');

        if (mode === 'target') loadBots();
    }

    async function loadBots() {
        const sel = document.getElementById('botSelect');
        const res = await fetch('bot_buff.php?action=get_bots');
        const bots = await res.json();
        sel.innerHTML = bots.map(b => `<option value="${b.Iduser}">${b.Name} (${new Intl.NumberFormat().format(b.Money)} GTLM)</option>`).join('');
    }

    document.getElementById('buffForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button');
        const resDiv = document.getElementById('result');
        
        btn.disabled = true;
        btn.innerText = '⏳ ĐANG XỬ LÝ...';

        const formData = new FormData(e.target);
        const response = await fetch('bot_buff.php?action=execute_buff', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        btn.disabled = false;
        btn.innerText = '🚀 THỰC THI BUFF  Gtlm';

        resDiv.style.display = 'block';
        resDiv.innerHTML = `
            <div class="success-text">🎉 Buff thành công!</div>
            <div>Tổng số bot được bơm: <b>${data.success}</b></div>
            <div>Tổng GTLM đã bơm: <b>${new Intl.NumberFormat().format(data.total_buffed)}</b></div>
            <div style="max-height:100px; overflow-y:auto; margin-top:10px; color:#94a3b8; font-size:11px;">
                ${data.details.map(d => `<div>• ${d}</div>`).join('')}
            </div>
        `;
    });
</script>

</body>
</html>
