<?php
/**
 * 🚀 Bot Money Buff Utility v2.0 - Cyber Luxury Command Edition
 * Modes: Random, Targeted, Group (Rescue), Universal (All)
 */
require_once __DIR__ . '/../db_connect.php';
$config = require __DIR__ . '/config.php';

if (!is_dir(__DIR__ . '/logs')) {
    @mkdir(__DIR__ . '/logs', 0777, true);
}

// --- AJAX HANDLER ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'get_bots') {
        $res = $conn->query("SELECT Iduser, Name, Email, Money, ImageURL FROM users WHERE Email REGEXP '^bot[0-9]+@' ORDER BY Name ASC");
        $bots = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $bots[] = [
                    'Iduser' => (int)$row['Iduser'],
                    'Name' => $row['Name'],
                    'Email' => $row['Email'],
                    'Money' => (float)$row['Money'],
                    'ImageURL' => !empty($row['ImageURL']) ? $row['ImageURL'] : '../img/avatar_default.png'
                ];
            }
        }
        echo json_encode($bots);
        exit;
    }

    if ($action === 'scan_poor_bots') {
        $threshold = (float)($_GET['threshold'] ?? 500000);
        $target_amount = (float)($_GET['target_amount'] ?? 1000000);
        
        $res = $conn->query("SELECT Iduser, Name, Money, ImageURL FROM users WHERE Email REGEXP '^bot[0-9]+@' AND Money < $threshold ORDER BY Money ASC");
        $poorBots = [];
        $totalDeficit = 0;
        if ($res) {
            while ($bot = $res->fetch_assoc()) {
                $deficit = max(0, $target_amount - (float)$bot['Money']);
                $totalDeficit += $deficit;
                $poorBots[] = [
                    'Iduser' => (int)$bot['Iduser'],
                    'Name' => $bot['Name'],
                    'Money' => (float)$bot['Money'],
                    'Deficit' => $deficit,
                    'ImageURL' => !empty($bot['ImageURL']) ? $bot['ImageURL'] : '../img/avatar_default.png'
                ];
            }
        }
        echo json_encode([
            'count' => count($poorBots),
            'total_needed' => $totalDeficit,
            'bots' => $poorBots
        ]);
        exit;
    }

    if ($action === 'execute_buff') {
        $mode = $_POST['mode'] ?? '';
        $max_cap = (float)($_POST['max_cap'] ?? 10000000);
        $results = ['success' => 0, 'total_buffed' => 0, 'details' => []];

        if ($mode === 'random') {
            $count = max(1, (int)$_POST['count']);
            $min = (float)$_POST['min'];
            $max = (float)$_POST['max'];
            if ($min > $max) { $tmp = $min; $min = $max; $max = $tmp; }
            
            $res = $conn->query("SELECT Iduser, Name, Money FROM users WHERE Email REGEXP '^bot[0-9]+@' ORDER BY RAND() LIMIT $count");
            while ($bot = $res->fetch_assoc()) {
                $amount = rand((int)$min, (int)$max);
                if ($bot['Money'] + $amount > $max_cap) {
                    $amount = max(0, $max_cap - $bot['Money']);
                }
                
                if ($amount > 0) {
                    $conn->query("UPDATE users SET Money = Money + $amount WHERE Iduser = {$bot['Iduser']}");
                    $results['success']++;
                    $results['total_buffed'] += $amount;
                    $results['details'][] = "Bơm cho {$bot['Name']}: +" . number_format($amount) . " GTLM";
                }
            }
        } 
        elseif ($mode === 'target') {
            $botId = (int)$_POST['bot_id'];
            $amount = (float)$_POST['amount'];
            
            $bot = $conn->query("SELECT Name, Money FROM users WHERE Iduser = $botId")->fetch_assoc();
            if ($bot) {
                if ($bot['Money'] + $amount > $max_cap) {
                    $amount = max(0, $max_cap - $bot['Money']);
                }
                if ($amount > 0) {
                    $conn->query("UPDATE users SET Money = Money + $amount WHERE Iduser = $botId");
                    $results['success']++;
                    $results['total_buffed'] += $amount;
                    $results['details'][] = "Bơm cho {$bot['Name']}: +" . number_format($amount) . " GTLM (Tổng mới: " . number_format($bot['Money'] + $amount) . " GTLM)";
                }
            }
        }
        elseif ($mode === 'group') {
            $threshold = (float)$_POST['threshold'];
            $target_amount = (float)$_POST['target_amount'];
            
            $res = $conn->query("SELECT Iduser, Name, Money FROM users WHERE Email REGEXP '^bot[0-9]+@' AND Money < $threshold");
            while ($bot = $res->fetch_assoc()) {
                $diff = $target_amount - $bot['Money'];
                if ($diff > 0) {
                    if ($bot['Money'] + $diff > $max_cap) {
                        $diff = max(0, $max_cap - $bot['Money']);
                    }
                    if ($diff > 0) {
                        $conn->query("UPDATE users SET Money = Money + $diff WHERE Iduser = {$bot['Iduser']}");
                        $results['success']++;
                        $results['total_buffed'] += $diff;
                        $results['details'][] = "Cứu viện {$bot['Name']} lên " . number_format($bot['Money'] + $diff) . " GTLM (+" . number_format($diff) . ")";
                    }
                }
            }
        }
        elseif ($mode === 'universal') {
            $amount = (float)$_POST['universal_amount'];
            if ($amount > 0) {
                $res = $conn->query("SELECT Iduser, Name, Money FROM users WHERE Email REGEXP '^bot[0-9]+@'");
                while ($bot = $res->fetch_assoc()) {
                    $add = $amount;
                    if ($bot['Money'] + $add > $max_cap) {
                        $add = max(0, $max_cap - $bot['Money']);
                    }
                    if ($add > 0) {
                        $conn->query("UPDATE users SET Money = Money + $add WHERE Iduser = {$bot['Iduser']}");
                        $results['success']++;
                        $results['total_buffed'] += $add;
                    }
                }
                $results['details'][] = "Đã phân bổ đều +" . number_format($amount) . " GTLM cho {$results['success']} bot toàn quân đoàn.";
            }
        }

        // Ghi Log lịch sử
        $logMsg = "[" . date('Y-m-d H:i:s') . "] Mode: $mode, Success: {$results['success']}, Total: " . number_format($results['total_buffed']) . PHP_EOL;
        file_put_contents(__DIR__ . '/logs/buff_history.log', $logMsg, FILE_APPEND);

        // Lấy lại tổng thống kê mới
        $qStats = $conn->query("SELECT SUM(Money) as total, COUNT(*) as cnt FROM users WHERE Email REGEXP '^bot[0-9]+@'")->fetch_assoc();
        $results['new_total_bot_money'] = (float)($qStats['total'] ?? 0);
        $results['total_bots'] = (int)($qStats['cnt'] ?? 0);

        echo json_encode($results);
        exit;
    }
}

// --- DỮ LIỆU BAN ĐẦU CHO GIAO DIỆN ---
$qTotal = $conn->query("SELECT SUM(Money) as total, COUNT(*) as cnt FROM users WHERE Email REGEXP '^bot[0-9]+@'")->fetch_assoc();
$totalBotMoney = (float)($qTotal['total'] ?? 0);
$totalBotsCount = (int)($qTotal['cnt'] ?? 0);

$qPoor = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE Email REGEXP '^bot[0-9]+@' AND Money < 500000")->fetch_assoc();
$poorBotsCount = (int)($qPoor['cnt'] ?? 0);

// Đọc lịch sử buff
$logFile = __DIR__ . '/logs/buff_history.log';
$historyLogs = [];
$todayBuffTotal = 0;
$todayDate = date('Y-m-d');

if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!empty($lines)) {
        $recentLines = array_slice($lines, -30);
        $recentLines = array_reverse($recentLines);
        foreach ($recentLines as $line) {
            // Định dạng: [2026-05-16 12:21:23] Mode: random, Success: 6, Total: 4,260,004
            if (preg_match('/\[(.*?)\] Mode: (.*?), Success: (.*?), Total: (.*)/', $line, $m)) {
                $entryDate = substr($m[1], 0, 10);
                $cleanTotal = (float)str_replace(',', '', $m[4]);
                if ($entryDate === $todayDate) {
                    $todayBuffTotal += $cleanTotal;
                }
                $historyLogs[] = [
                    'time' => $m[1],
                    'mode' => trim($m[2]),
                    'success' => (int)trim($m[3]),
                    'total' => $m[4]
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚡ Kho Tiếp Viện GTLM Quân Đoàn | Bot Army Treasury</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg-body: #070a13;
            --bg-card: rgba(15, 23, 42, 0.75);
            --bg-card-hover: rgba(30, 41, 59, 0.85);
            --border-glass: rgba(255, 255, 255, 0.08);
            --border-focus: rgba(139, 92, 246, 0.45);

            --primary: #6366f1;
            --secondary: #8b5cf6;
            --success: #10b981;
            --danger: #ef4444;
            --warn: #f59e0b;
            --cyan: #06b6d4;
            --pink: #ec4899;

            --text-heading: #ffffff;
            --text-body: #cbd5e1;
            --text-muted: #94a3b8;
            --text-dim: #64748b;

            --radius-md: 14px;
            --radius-lg: 20px;
            --radius-xl: 26px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-body);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            padding: 24px 28px 80px 28px;
            background-image: 
                radial-gradient(circle at 15% 15%, rgba(99, 102, 241, 0.1) 0%, transparent 40%),
                radial-gradient(circle at 85% 75%, rgba(139, 92, 246, 0.1) 0%, transparent 40%);
            background-attachment: fixed;
            line-height: 1.5;
        }

        .container {
            max-width: 1340px;
            margin: 0 auto;
        }

        /* ── Header ── */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 26px;
            flex-wrap: wrap;
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .brand-icon-wrap {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.25), rgba(139, 92, 246, 0.25));
            border: 1px solid rgba(139, 92, 246, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: #fff;
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.25);
        }

        .header-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.65rem;
            font-weight: 900;
            color: #fff;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-badge-live {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
            font-size: 0.72rem;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 6px;
            letter-spacing: 0.04em;
        }

        .header-sub {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin-top: 3px;
        }

        .header-toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-toolbar {
            padding: 9px 16px;
            border-radius: 12px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid var(--border-glass);
            background: rgba(255, 255, 255, 0.04);
            color: var(--text-body);
        }

        .btn-toolbar:hover {
            background: rgba(255, 255, 255, 0.09);
            color: #fff;
            transform: translateY(-2px);
        }

        .btn-toolbar-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
        }

        /* ── Top KPI Grid ── */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }

        @media (max-width: 1024px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .kpi-grid { grid-template-columns: 1fr; }
        }

        .kpi-card {
            background: var(--bg-card);
            backdrop-filter: blur(14px);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 20px;
            position: relative;
            overflow: hidden;
            transition: all 0.25s ease;
        }

        .kpi-card:hover {
            transform: translateY(-3px);
            border-color: var(--border-focus);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
        }

        .kpi-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .kpi-title {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }

        .kpi-icon-wrap {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
        }

        .kpi-value {
            font-size: 1.65rem;
            font-weight: 900;
            color: #fff;
            line-height: 1.2;
            font-family: 'Outfit', sans-serif;
        }

        .kpi-subtitle {
            font-size: 0.75rem;
            color: var(--text-dim);
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* ── Main Layout: 2 Columns ── */
        .main-layout {
            display: grid;
            grid-template-columns: 1.35fr 1fr;
            gap: 24px;
        }

        @media (max-width: 1000px) {
            .main-layout { grid-template-columns: 1fr; }
        }

        .panel-box {
            background: var(--bg-card);
            backdrop-filter: blur(14px);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-bottom: 24px;
            position: relative;
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .panel-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Outfit', sans-serif;
        }

        /* ── Mode Selection Tabs ── */
        .mode-tabs {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            background: rgba(0, 0, 0, 0.35);
            padding: 5px;
            border-radius: 14px;
            margin-bottom: 24px;
            border: 1px solid var(--border-glass);
        }

        @media (max-width: 600px) {
            .mode-tabs { grid-template-columns: repeat(2, 1fr); }
        }

        .mode-tab-btn {
            padding: 11px 12px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.8rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .mode-tab-btn i {
            font-size: 1.1rem;
        }

        .mode-tab-btn:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.05);
        }

        .mode-tab-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
        }

        /* ── Form Controls ── */
        .form-section {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .form-label-hint {
            font-size: 0.72rem;
            color: var(--text-dim);
            font-weight: 500;
        }

        .input-control {
            width: 100%;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid var(--border-glass);
            padding: 12px 14px;
            border-radius: 12px;
            color: #fff;
            font-size: 0.92rem;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .input-control:focus {
            outline: none;
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
            background: rgba(0, 0, 0, 0.6);
        }

        select.input-control {
            font-family: 'Inter', sans-serif;
            cursor: pointer;
        }

        select.input-control option {
            background: #0f172a;
            color: #fff;
        }

        /* Preset Chips */
        .preset-chips {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 4px;
        }

        .chip-btn {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border-glass);
            color: var(--text-muted);
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .chip-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }

        /* Target Bot Live Card */
        .bot-preview-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-glass);
            border-radius: 14px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 6px;
        }

        .bot-preview-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid var(--border-glass);
        }

        .bot-preview-info {
            flex: 1;
            min-width: 0;
        }

        .bot-preview-name {
            font-weight: 800;
            color: #fff;
            font-size: 0.95rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .bot-preview-money {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 2px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .bot-preview-after {
            text-align: right;
        }

        .bot-preview-after-label {
            font-size: 0.7rem;
            color: var(--text-dim);
            text-transform: uppercase;
            font-weight: 700;
        }

        .bot-preview-after-val {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 800;
            font-size: 1rem;
            color: var(--success);
        }

        /* Group Scan Preview Card */
        .group-scan-box {
            background: rgba(245, 158, 11, 0.04);
            border: 1px solid rgba(245, 158, 11, 0.2);
            border-radius: 14px;
            padding: 14px 16px;
            margin-top: 10px;
        }

        /* Primary Action Button */
        .btn-execute {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            border: none;
            padding: 16px;
            border-radius: 14px;
            font-size: 0.98rem;
            font-weight: 900;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 12px;
            font-family: 'Outfit', sans-serif;
            letter-spacing: 0.02em;
        }

        .btn-execute:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(99, 102, 241, 0.55);
            background: linear-gradient(135deg, #7c3aed, #6366f1);
        }

        .btn-execute:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* ── Log List ── */
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 480px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .history-item {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            transition: all 0.2s ease;
        }

        .history-item:hover {
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(255, 255, 255, 0.15);
        }

        .history-tag {
            font-size: 0.68rem;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 5px;
            text-transform: uppercase;
        }

        .history-tag-random { background: rgba(99, 102, 241, 0.2); color: #a5b4fc; }
        .history-tag-target { background: rgba(236, 72, 153, 0.2); color: #f472b6; }
        .history-tag-group { background: rgba(245, 158, 11, 0.2); color: #fcd34d; }
        .history-tag-universal { background: rgba(16, 185, 129, 0.2); color: #6ee7b7; }

        /* Security Card */
        .security-banner {
            background: linear-gradient(135deg, rgba(6, 182, 212, 0.08), rgba(99, 102, 241, 0.08));
            border: 1px solid rgba(6, 182, 212, 0.25);
            border-radius: 14px;
            padding: 16px;
            margin-top: 18px;
        }

        .security-banner-title {
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--cyan);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }

        .security-banner-text {
            font-size: 0.75rem;
            color: var(--text-muted);
            line-height: 1.45;
        }

        /* Result details list */
        .buff-result-box {
            display: none;
            margin-top: 18px;
            padding: 16px;
            border-radius: 14px;
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.25);
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: rgba(0, 0, 0, 0.2); }
        ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.2); }
    </style>
</head>
<body>

<div class="container">

    <!-- ── Top Header ── -->
    <header class="page-header">
        <div class="header-brand">
            <div class="brand-icon-wrap">
                <i class="fa fa-vault"></i>
            </div>
            <div>
                <div class="header-title">
                    <span>KHO TIẾP VIỆN GTLM QUÂN ĐOÀN</span>
                    <span class="header-badge-live">TREASURY v2.0</span>
                </div>
                <div class="header-sub">
                    Phân phối ngân sách và bơm vốn tự động cho <?= $totalBotsCount ?> thành viên AI
                </div>
            </div>
        </div>

        <div class="header-toolbar">
            <a href="index.php" class="btn-toolbar">
                <i class="fa fa-arrow-left"></i> Về Dashboard
            </a>
            <a href="../index.php" class="btn-toolbar">
                <i class="fa fa-gamepad"></i> Sảnh Game
            </a>
            <a href="../lucky_spin.php" class="btn-toolbar">
                <i class="fa fa-dharmachakra"></i> Mega Spin
            </a>
            <a href="bot_runner_web.php" class="btn-toolbar btn-toolbar-primary">
                <i class="fa fa-play"></i> Runner Web
            </a>
        </div>
    </header>

    <!-- ── Top 4 KPI Cards ── -->
    <section class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-title">Tổng Vốn Quân Đoàn</span>
                <div class="kpi-icon-wrap" style="color: var(--success);">
                    <i class="fa fa-wallet"></i>
                </div>
            </div>
            <div class="kpi-value" id="kpiTotalMoney" style="color: var(--success);">
                <?= number_format($totalBotMoney) ?>
            </div>
            <div class="kpi-subtitle">
                <span>GTLM đang lưu hành toàn quân</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-title">Quân Số Khả Dụng</span>
                <div class="kpi-icon-wrap" style="color: var(--primary);">
                    <i class="fa fa-robot"></i>
                </div>
            </div>
            <div class="kpi-value" style="color: var(--primary);">
                <?= $totalBotsCount ?>
            </div>
            <div class="kpi-subtitle">
                <span>Bot AI sẵn sàng tham chiến 24/7</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-title">Nguy Cơ Cháy Túi</span>
                <div class="kpi-icon-wrap" style="color: var(--warn);">
                    <i class="fa fa-triangle-exclamation"></i>
                </div>
            </div>
            <div class="kpi-value" id="kpiPoorBots" style="color: <?= $poorBotsCount > 0 ? 'var(--warn)' : 'var(--success)' ?>;">
                <?= $poorBotsCount ?>
            </div>
            <div class="kpi-subtitle">
                <span style="color: <?= $poorBotsCount > 0 ? 'var(--warn)' : 'var(--text-dim)' ?>;">
                    <?= $poorBotsCount > 0 ? "⚠️ Cần cấp cứu (< 500k GTLM)" : "✅ Toàn quân đều dồi dào vốn" ?>
                </span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-title">Tiếp Viện Hôm Nay</span>
                <div class="kpi-icon-wrap" style="color: var(--cyan);">
                    <i class="fa fa-bolt"></i>
                </div>
            </div>
            <div class="kpi-value" id="kpiTodayBuff" style="color: var(--cyan);">
                <?= number_format($todayBuffTotal) ?>
            </div>
            <div class="kpi-subtitle">
                <span>GTLM đã bơm trong ngày</span>
            </div>
        </div>
    </section>

    <!-- ── Main 2 Columns Layout ── -->
    <div class="main-layout">

        <!-- ── Left Column: Buff Control Console ── -->
        <div class="panel-box">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fa fa-sliders" style="color: var(--secondary);"></i>
                    <span>TRÌNH ĐIỀU PHỐI TIẾP VỐN</span>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-dim);">
                    Chọn 1 trong 4 chế độ bơm vốn thông minh
                </div>
            </div>

            <!-- Mode Switch Tabs -->
            <div class="mode-tabs">
                <button type="button" class="mode-tab-btn active" onclick="switchMode('random', this)">
                    <i class="fa fa-dice"></i>
                    <span>Ngẫu Nhiên</span>
                </button>
                <button type="button" class="mode-tab-btn" onclick="switchMode('target', this)">
                    <i class="fa fa-bullseye"></i>
                    <span>Chỉ Định</span>
                </button>
                <button type="button" class="mode-tab-btn" onclick="switchMode('group', this)">
                    <i class="fa fa-hand-holding-dollar"></i>
                    <span>Thoát Nghèo</span>
                </button>
                <button type="button" class="mode-tab-btn" onclick="switchMode('universal', this)">
                    <i class="fa fa-earth-americas"></i>
                    <span>Toàn Quân</span>
                </button>
            </div>

            <form id="buffForm">
                <input type="hidden" name="mode" id="buffMode" value="random">

                <!-- 1. MODE: RANDOM AIRDROP -->
                <div id="fields_random" class="form-section">
                    <div class="form-group">
                        <label class="form-label">
                            <span>Số lượng Bot nhận thưởng (Max: <?= $totalBotsCount ?>)</span>
                            <span class="form-label-hint">Chọn ngẫu nhiên từ kho bot</span>
                        </label>
                        <input type="number" name="count" id="random_count" class="input-control" value="10" min="1" max="<?= $totalBotsCount ?>">
                        <div class="preset-chips">
                            <button type="button" class="chip-btn" onclick="setCount(5)">5 bot</button>
                            <button type="button" class="chip-btn" onclick="setCount(10)">10 bot</button>
                            <button type="button" class="chip-btn" onclick="setCount(25)">25 bot</button>
                            <button type="button" class="chip-btn" onclick="setCount(50)">50 bot</button>
                            <button type="button" class="chip-btn" onclick="setCount(<?= $totalBotsCount ?>)">Tất cả (<?= $totalBotsCount ?>)</button>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label">GTLM tối thiểu (Min)</label>
                            <input type="number" name="min" id="random_min" class="input-control" value="200000" step="50000">
                            <div class="preset-chips">
                                <button type="button" class="chip-btn" onclick="setMin(100000)">100K</button>
                                <button type="button" class="chip-btn" onclick="setMin(500000)">500K</button>
                                <button type="button" class="chip-btn" onclick="setMin(1000000)">1M</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">GTLM tối đa (Max)</label>
                            <input type="number" name="max" id="random_max" class="input-control" value="1500000" step="100000">
                            <div class="preset-chips">
                                <button type="button" class="chip-btn" onclick="setMax(1000000)">1M</button>
                                <button type="button" class="chip-btn" onclick="setMax(5000000)">5M</button>
                                <button type="button" class="chip-btn" onclick="setMax(10000000)">10M</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. MODE: TARGET INJECTION -->
                <div id="fields_target" class="form-section" style="display:none;">
                    <div class="form-group">
                        <label class="form-label">
                            <span>Chọn Bot Cần Tiếp Vốn</span>
                            <span class="form-label-hint">Tìm theo tên hoặc ID</span>
                        </label>
                        <select name="bot_id" id="botSelect" class="input-control" onchange="onBotSelected()">
                            <option value="">Đang tải danh sách bot...</option>
                        </select>
                    </div>

                    <!-- Target Bot Preview Card -->
                    <div id="targetBotPreview" class="bot-preview-card" style="display:none;">
                        <img id="prevAvatar" src="../img/avatar_default.png" alt="Bot" class="bot-preview-avatar">
                        <div class="bot-preview-info">
                            <div id="prevName" class="bot-preview-name">Bot 01</div>
                            <div class="bot-preview-money">
                                <span>Hiện có:</span>
                                <b id="prevCurrentMoney" style="color: #fff; font-family: 'JetBrains Mono', monospace;">0 GTLM</b>
                            </div>
                        </div>
                        <div class="bot-preview-after">
                            <div class="bot-preview-after-label">Sau khi bơm</div>
                            <div id="prevNewMoney" class="bot-preview-after-val">+0 GTLM</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Số GTLM Bơm Cụ Thể</label>
                        <input type="number" name="amount" id="target_amount" class="input-control" value="1000000" step="100000" oninput="updateTargetPreview()">
                        <div class="preset-chips">
                            <button type="button" class="chip-btn" onclick="setTargetAmount(500000)">+500K</button>
                            <button type="button" class="chip-btn" onclick="setTargetAmount(1000000)">+1M</button>
                            <button type="button" class="chip-btn" onclick="setTargetAmount(2000000)">+2M</button>
                            <button type="button" class="chip-btn" onclick="setTargetAmount(5000000)">+5M</button>
                            <button type="button" class="chip-btn" onclick="setTargetAmount(10000000)">+10M</button>
                            <button type="button" class="chip-btn" onclick="setTargetAmount(50000000)">+50M</button>
                        </div>
                    </div>
                </div>

                <!-- 3. MODE: GROUP WELFARE RESCUE -->
                <div id="fields_group" class="form-section" style="display:none;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label">Dưới ngưỡng nghèo (GTLM)</label>
                            <input type="number" name="threshold" id="group_threshold" class="input-control" value="500000" step="50000" onchange="scanPoorBots()">
                            <div class="preset-chips">
                                <button type="button" class="chip-btn" onclick="setGroupThreshold(200000)">200K</button>
                                <button type="button" class="chip-btn" onclick="setGroupThreshold(500000)">500K</button>
                                <button type="button" class="chip-btn" onclick="setGroupThreshold(1000000)">1M</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Cứu viện lên mức (Target)</label>
                            <input type="number" name="target_amount" id="group_target" class="input-control" value="1000000" step="100000" onchange="scanPoorBots()">
                            <div class="preset-chips">
                                <button type="button" class="chip-btn" onclick="setGroupTarget(1000000)">1M</button>
                                <button type="button" class="chip-btn" onclick="setGroupTarget(2000000)">2M</button>
                                <button type="button" class="chip-btn" onclick="setGroupTarget(5000000)">5M</button>
                            </div>
                        </div>
                    </div>

                    <!-- Live Scan Summary Box -->
                    <div class="group-scan-box" id="poorScanBox">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <span style="font-weight: 800; font-size: 0.85rem; color: var(--warn); display: flex; align-items: center; gap: 6px;">
                                <i class="fa fa-stethoscope"></i> Kết Quả Quét Cứu Trợ
                            </span>
                            <button type="button" onclick="scanPoorBots()" style="background: rgba(255,255,255,0.06); border: 1px solid var(--border-glass); color: #fff; font-size: 0.7rem; padding: 3px 8px; border-radius: 6px; cursor: pointer;">
                                <i class="fa fa-rotate"></i> Quét lại
                            </button>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.6;" id="poorScanContent">
                            Đang quét số lượng bot dưới ngưỡng...
                        </div>
                    </div>
                </div>

                <!-- 4. MODE: UNIVERSAL ARMY STIMULUS -->
                <div id="fields_universal" class="form-section" style="display:none;">
                    <div class="form-group">
                        <label class="form-label">
                            <span>Mức Thưởng Đồng Loạt Mỗi Bot Nhận Được</span>
                            <span class="form-label-hint">Tất cả <?= $totalBotsCount ?> bot đều được cộng thêm</span>
                        </label>
                        <input type="number" name="universal_amount" id="universal_amount" class="input-control" value="500000" step="100000" oninput="updateUniversalPreview()">
                        <div class="preset-chips">
                            <button type="button" class="chip-btn" onclick="setUniversalAmount(200000)">+200K</button>
                            <button type="button" class="chip-btn" onclick="setUniversalAmount(500000)">+500K</button>
                            <button type="button" class="chip-btn" onclick="setUniversalAmount(1000000)">+1M</button>
                            <button type="button" class="chip-btn" onclick="setUniversalAmount(2000000)">+2M</button>
                            <button type="button" class="chip-btn" onclick="setUniversalAmount(5000000)">+5M</button>
                        </div>
                    </div>

                    <div style="background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 12px; padding: 12px 14px; font-size: 0.8rem; color: var(--text-muted);">
                        <div style="font-weight: 800; color: #fff; margin-bottom: 4px;">Dự toán chi ngân sách:</div>
                        <div id="universalCalc" style="color: var(--success); font-family: 'JetBrains Mono', monospace; font-weight: 700;">
                            <?= $totalBotsCount ?> bot × 500,000 = <?= number_format($totalBotsCount * 500000) ?> GTLM
                        </div>
                    </div>
                </div>

                <!-- Global Safety Max Cap -->
                <div class="form-group" style="margin-top: 18px; border-top: 1px solid var(--border-glass); padding-top: 18px;">
                    <label class="form-label">
                        <span style="color: var(--warn); display: flex; align-items: center; gap: 6px;">
                            <i class="fa fa-shield-halved"></i> Giới hạn trần an toàn (Max Cap)
                        </span>
                        <span class="form-label-hint">Chống lạm phát, tài sản sau bơm không vượt quá mức này</span>
                    </label>
                    <input type="number" name="max_cap" id="max_cap" class="input-control" value="50000000" step="1000000">
                    <div class="preset-chips">
                        <button type="button" class="chip-btn" onclick="setMaxCap(10000000)">10 Triệu</button>
                        <button type="button" class="chip-btn" onclick="setMaxCap(25000000)">25 Triệu</button>
                        <button type="button" class="chip-btn" onclick="setMaxCap(50000000)">50 Triệu</button>
                        <button type="button" class="chip-btn" onclick="setMaxCap(100000000)">100 Triệu</button>
                    </div>
                </div>

                <!-- Main Submit Button -->
                <button type="submit" id="btnSubmit" class="btn-execute">
                    <i class="fa fa-bolt"></i>
                    <span>THỰC THI BƠM GTLM NGAY</span>
                </button>
            </form>

            <!-- Result Box -->
            <div id="buffResult" class="buff-result-box"></div>
        </div>

        <!-- ── Right Column: Logs & Security Rules ── -->
        <div>
            <!-- Live History Panel -->
            <div class="panel-box">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fa fa-clock-rotate-left" style="color: var(--cyan);"></i>
                        <span>Nhật Ký Tiếp Viện Gần Nhất</span>
                    </div>
                    <span style="font-size: 0.72rem; color: var(--text-dim);">
                        <?= count($historyLogs) ?> phiên ghi nhận
                    </span>
                </div>

                <div class="history-list" id="historyListContainer">
                    <?php if (empty($historyLogs)): ?>
                        <div style="text-align: center; color: var(--text-dim); font-size: 0.8rem; padding: 30px;">
                            Chưa có dữ liệu tiếp vốn nào trong file nhật ký.
                        </div>
                    <?php else: ?>
                        <?php foreach ($historyLogs as $log): 
                            $modeClass = 'history-tag-' . $log['mode'];
                            $modeName = strtoupper($log['mode']);
                            if ($log['mode'] == 'random') $modeName = 'NGẪU NHIÊN';
                            elseif ($log['mode'] == 'target') $modeName = 'CHỈ ĐỊNH';
                            elseif ($log['mode'] == 'group') $modeName = 'THOÁT NGHÈO';
                            elseif ($log['mode'] == 'universal') $modeName = 'TOÀN QUÂN';
                        ?>
                            <div class="history-item">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span class="history-tag <?= $modeClass ?>"><?= $modeName ?></span>
                                    <div>
                                        <div style="font-size: 0.82rem; font-weight: 800; color: #fff;">
                                            +<?= $log['total'] ?> GTLM
                                        </div>
                                        <div style="font-size: 0.7rem; color: var(--text-dim);">
                                            <?= $log['time'] ?> • <?= $log['success'] ?> bot
                                        </div>
                                    </div>
                                </div>
                                <i class="fa fa-circle-check" style="color: var(--success); font-size: 0.9rem;"></i>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Security Protocols Card -->
            <div class="security-banner">
                <div class="security-banner-title">
                    <i class="fa fa-shield-virus"></i>
                    <span>CƠ CHẾ BẢO VỆ KINH TẾ (ANTI-INFLATION)</span>
                </div>
                <div class="security-banner-text">
                    • <b>Ngưỡng Trần (Max Cap):</b> Mọi hành vi bơm tiền đều áp dụng trần an toàn, ngăn chặn bot tích lũy lượng tiền quá lớn gây lệch cán cân với người chơi thật.<br>
                    • <b>Cứu Viện Tự Động:</b> Giúp các bot "cháy túi" sau các ván cược thua nhanh chóng có đủ vốn để tiếp tục hoạt động trên 61 bàn livestream 24/7.<br>
                    • <b>Đồng Bộ Tức Thời:</b> Tiền được cộng trực tiếp vào CSDL MySQL của bảng <code>users</code> và có hiệu lực ngay trong chu kỳ cược tiếp theo của bot.
                </div>
            </div>
        </div>

    </div>

</div>

<!-- ── JavaScript Execution ── -->
<script>
    let cachedBots = [];
    const totalArmyCount = <?= $totalBotsCount ?>;

    function switchMode(mode, btn) {
        document.getElementById('buffMode').value = mode;
        document.querySelectorAll('.mode-tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        document.getElementById('fields_random').style.display = (mode === 'random' ? 'flex' : 'none');
        document.getElementById('fields_target').style.display = (mode === 'target' ? 'flex' : 'none');
        document.getElementById('fields_group').style.display = (mode === 'group' ? 'flex' : 'none');
        document.getElementById('fields_universal').style.display = (mode === 'universal' ? 'flex' : 'none');

        if (mode === 'target' && cachedBots.length === 0) {
            loadBotsList();
        } else if (mode === 'target') {
            updateTargetPreview();
        }

        if (mode === 'group') {
            scanPoorBots();
        }

        if (mode === 'universal') {
            updateUniversalPreview();
        }
    }

    // Preset Helpers
    function setCount(val) { document.getElementById('random_count').value = val; }
    function setMin(val) { document.getElementById('random_min').value = val; }
    function setMax(val) { document.getElementById('random_max').value = val; }
    function setTargetAmount(val) { 
        document.getElementById('target_amount').value = val; 
        updateTargetPreview(); 
    }
    function setGroupThreshold(val) { 
        document.getElementById('group_threshold').value = val; 
        scanPoorBots(); 
    }
    function setGroupTarget(val) { 
        document.getElementById('group_target').value = val; 
        scanPoorBots(); 
    }
    function setUniversalAmount(val) { 
        document.getElementById('universal_amount').value = val; 
        updateUniversalPreview(); 
    }
    function setMaxCap(val) { document.getElementById('max_cap').value = val; }

    function updateUniversalPreview() {
        const amt = parseFloat(document.getElementById('universal_amount').value) || 0;
        const total = totalArmyCount * amt;
        const el = document.getElementById('universalCalc');
        if (el) {
            el.textContent = `${totalArmyCount} bot × ${amt.toLocaleString()} = ${total.toLocaleString()} GTLM`;
        }
    }

    // Load Bot list for Target mode
    async function loadBotsList() {
        const sel = document.getElementById('botSelect');
        try {
            const res = await fetch('bot_buff.php?action=get_bots');
            cachedBots = await res.json();
            sel.innerHTML = '<option value="">-- Chọn một Bot từ danh sách (' + cachedBots.length + ' bot) --</option>' + 
                cachedBots.map(b => `<option value="${b.Iduser}" data-money="${b.Money}" data-avatar="${b.ImageURL}">${b.Name} (Hiện có: ${Number(b.Money).toLocaleString()} GTLM)</option>`).join('');
            
            if (cachedBots.length > 0) {
                sel.selectedIndex = 1;
                onBotSelected();
            }
        } catch (err) {
            console.error('Lỗi tải bot:', err);
            sel.innerHTML = '<option value="">Lỗi tải danh sách bot!</option>';
        }
    }

    function onBotSelected() {
        const sel = document.getElementById('botSelect');
        const prevBox = document.getElementById('targetBotPreview');
        if (!sel.value) {
            prevBox.style.display = 'none';
            return;
        }

        const opt = sel.options[sel.selectedIndex];
        const botName = opt.text.split(' (')[0];
        const curMoney = parseFloat(opt.getAttribute('data-money')) || 0;
        const avatar = opt.getAttribute('data-avatar') || '../img/avatar_default.png';

        document.getElementById('prevAvatar').src = avatar;
        document.getElementById('prevName').textContent = botName + ' (#' + sel.value + ')';
        document.getElementById('prevCurrentMoney').textContent = curMoney.toLocaleString() + ' GTLM';

        prevBox.style.display = 'flex';
        updateTargetPreview();
    }

    function updateTargetPreview() {
        const sel = document.getElementById('botSelect');
        if (!sel || !sel.value) return;
        const opt = sel.options[sel.selectedIndex];
        const curMoney = parseFloat(opt.getAttribute('data-money')) || 0;
        const buffAmount = parseFloat(document.getElementById('target_amount').value) || 0;
        const maxCap = parseFloat(document.getElementById('max_cap').value) || 50000000;

        let finalMoney = curMoney + buffAmount;
        if (finalMoney > maxCap) finalMoney = maxCap;

        const valEl = document.getElementById('prevNewMoney');
        if (valEl) {
            valEl.textContent = finalMoney.toLocaleString() + ' GTLM';
        }
    }

    // Scan poor bots
    async function scanPoorBots() {
        const threshold = document.getElementById('group_threshold').value || 500000;
        const targetAmount = document.getElementById('group_target').value || 1000000;
        const box = document.getElementById('poorScanContent');
        box.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Đang quét hệ thống...';

        try {
            const res = await fetch(`bot_buff.php?action=scan_poor_bots&threshold=${threshold}&target_amount=${targetAmount}`);
            const data = await res.json();

            if (data.count === 0) {
                box.innerHTML = `✅ Không có bot nào dưới ngưỡng <b>${Number(threshold).toLocaleString()} GTLM</b>. Toàn quân đang an toàn!`;
            } else {
                box.innerHTML = `
                    Phát hiện <b>${data.count} bot</b> đang dưới ngưỡng.<br>
                    Tổng ngân sách cần để nâng toàn bộ lên <b>${Number(targetAmount).toLocaleString()} GTLM</b> là: 
                    <span style="color:var(--warn); font-weight:800; font-family:'JetBrains Mono',monospace;">${Number(data.total_needed).toLocaleString()} GTLM</span>.
                `;
            }
        } catch (e) {
            box.innerHTML = 'Lỗi quét dữ liệu!';
        }
    }

    // Handle Form Submit
    document.getElementById('buffForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const mode = document.getElementById('buffMode').value;
        const btn = document.getElementById('btnSubmit');
        const resBox = document.getElementById('buffResult');

        let confirmTitle = 'Xác nhận bơm vốn GTLM?';
        let confirmText = 'Ngân sách sẽ được phân bổ tức thời vào ví bot được chọn.';

        if (mode === 'universal') {
            const amt = parseFloat(document.getElementById('universal_amount').value) || 0;
            confirmTitle = `Bơm đồng loạt toàn quân?`;
            confirmText = `Mỗi bot sẽ được cộng +${amt.toLocaleString()} GTLM.`;
        }

        const confirm = await Swal.fire({
            title: confirmTitle,
            text: confirmText,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#6366f1',
            cancelButtonColor: '#64748b',
            confirmButtonText: '🚀 Đồng Ý Bơm',
            cancelButtonText: 'Hủy Bỏ',
            background: '#0f172a',
            color: '#fff'
        });

        if (!confirm.isConfirmed) return;

        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ĐANG THỰC THI TIẾP VỐN...';

        try {
            const formData = new FormData(this);
            const response = await fetch('bot_buff.php?action=execute_buff', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-bolt"></i> <span>THỰC THI BƠM GTLM NGAY</span>';

            // Cập nhật Top KPI cards
            if (data.new_total_bot_money) {
                document.getElementById('kpiTotalMoney').textContent = Number(data.new_total_bot_money).toLocaleString();
            }

            // Hiển thị kết quả SweetAlert2
            Swal.fire({
                title: '🎉 Tiếp Vốn Thành Công!',
                html: `
                    <div style="font-size:0.95rem; margin-top:8px;">
                        Số bot đã bơm: <b style="color:#10b981;">${data.success}</b> bot<br>
                        Tổng GTLM đã bơm: <b style="color:#f59e0b; font-family:'JetBrains Mono',monospace;">+${Number(data.total_buffed).toLocaleString()} GTLM</b>
                    </div>
                `,
                icon: 'success',
                confirmButtonColor: '#10b981',
                confirmButtonText: 'Tuyệt Vời!',
                background: '#0f172a',
                color: '#fff'
            });

            // Hiển thị chi tiết
            resBox.style.display = 'block';
            resBox.innerHTML = `
                <div style="font-weight:800; color:var(--success); margin-bottom:6px; display:flex; align-items:center; gap:6px;">
                    <i class="fa fa-circle-check"></i> Đã hoàn tất tiếp vốn: ${data.success} bot (+${Number(data.total_buffed).toLocaleString()} GTLM)
                </div>
                <div style="max-height:120px; overflow-y:auto; font-size:0.75rem; color:var(--text-muted); line-height:1.5;">
                    ${data.details.map(d => `<div>• ${d}</div>`).join('')}
                </div>
            `;

            // Tải lại bot preview hoặc poor scan nếu cần
            if (mode === 'target') loadBotsList();
            if (mode === 'group') scanPoorBots();

        } catch (error) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-bolt"></i> <span>THỰC THI BƠM GTLM NGAY</span>';
            Swal.fire({
                title: 'Lỗi Xử Lý',
                text: 'Không thể tiếp vốn do lỗi mạng hoặc CSDL!',
                icon: 'error',
                background: '#0f172a',
                color: '#fff'
            });
        }
    });

    // Khởi tạo quét thoát nghèo ban đầu
    scanPoorBots();
</script>

</body>
</html>
