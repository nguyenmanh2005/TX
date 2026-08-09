<?php
@set_time_limit(0);
@ignore_user_abort(true);
@ob_end_clean(); // Vô hiệu hóa mọi lớp đệm bao gồm VocabularyHelper
/**
 *  Omni-Bot Engine v16.6 - Visual Overhaul
 */

// --- Global action handler for Kill-Switch status (Rule 5.4) ---
if (isset($_GET['action']) && $_GET['action'] === 'set_status') {
    $enabled = isset($_GET['enabled']) && ($_GET['enabled'] == '1' || $_GET['enabled'] == 'true');
    $statusFile = __DIR__ . '/sessions/bot_status.json';
    if (!is_dir(__DIR__ . '/sessions')) @mkdir(__DIR__ . '/sessions', 0755, true);
    @file_put_contents($statusFile, json_encode(['enabled' => $enabled, 'updated_at' => time()]));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'enabled' => $enabled]);
    exit;
}

// 1. Load config & brain (Moved to top for better IDE support)
require_once __DIR__ . '/../db_connect.php'; 
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/bot_brain.php';
require_once __DIR__ . '/../game_history_helper.php';
require_once __DIR__ . '/bot_blackjack_multi.php';
require_once __DIR__ . '/bot_horserace_pvp.php';
require_once __DIR__ . '/bot_mining_tycoon.php';
require_once __DIR__ . '/bot_market_trade.php';
require_once __DIR__ . '/bot_gacha.php';
require_once __DIR__ . '/bot_world_boss.php';
require_once __DIR__ . '/bot_farm.php';
require_once __DIR__ . '/bot_pets.php';
require_once __DIR__ . '/bot_lottery.php';
require_once __DIR__ . '/bot_red_packet.php';
require_once __DIR__ . '/bot_chat_reaction.php';
require_once __DIR__ . '/bot_greedy_cave.php';
require_once __DIR__ . '/bot_secret_gift.php';
require_once __DIR__ . '/bot_plinko.php';
require_once __DIR__ . '/bot_trivia.php';
require_once __DIR__ . '/bot_megaspin.php';
require_once __DIR__ . '/bot_pvp_challenge.php';
require_once __DIR__ . '/bot_chatter.php';
require_once __DIR__ . '/bot_lucky_wheel.php';
require_once __DIR__ . '/bot_storyline.php';
require_once __DIR__ . '/bot_fortune.php';
require_once __DIR__ . '/bot_oracle.php';
require_once __DIR__ . '/bot_event_vote.php';
require_once __DIR__ . '/bot_monthly_pass.php';
require_once __DIR__ . '/bot_reward_points.php';
require_once __DIR__ . '/bot_tournament.php';
require_once __DIR__ . '/bot_guild.php';
require_once __DIR__ . '/bot_mining.php';
require_once __DIR__ . '/bot_quests.php';
require_once __DIR__ . '/bot_vip.php';
require_once __DIR__ . '/bot_auction.php';
require_once __DIR__ . '/bot_battle_pass.php';
require_once __DIR__ . '/bot_marketplace.php';
require_once __DIR__ . '/bot_dungeon.php';
require_once __DIR__ . '/bot_coinflip.php';
require_once __DIR__ . '/bot_friends.php';
require_once __DIR__ . '/bot_daily_login.php';
require_once __DIR__ . '/bot_daily_missions.php';
require_once __DIR__ . '/bot_achievements.php';
require_once __DIR__ . '/bot_gift.php';
require_once __DIR__ . '/bot_combo_bet.php';
require_once __DIR__ . '/bot_crafting.php';
require_once __DIR__ . '/bot_banharc.php';
require_once __DIR__ . '/bot_social_feed.php';
require_once __DIR__ . '/bot_profile.php';
require_once __DIR__ . '/bot_guild_territory.php';
require_once __DIR__ . '/bot_spectator.php';
require_once __DIR__ . '/bot_guild_war.php';
require_once __DIR__ . '/bot_baiting.php';
require_once __DIR__ . '/bot_vendetta.php';

// 0. Helpers & Error Handling
$currentBotEmail = "SYSTEM";
$currentCookieFile = __DIR__ . '/sessions/system.txt';

$inError = false;

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    global $currentBotEmail, $baseUrl, $currentCookieFile, $inError;
    if ($inError) return false;
    $inError = true;
    
    $severity = ($errno & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) ? "CRITICAL" : "WARNING";
    $safeFile = str_ireplace('config.php', '[HIDDEN]', basename($errfile));
    $msg = "[$severity] $errstr in $safeFile:$errline";
    
    writeBotLog($currentBotEmail, "ERROR", "PHP_SYSTEM", $msg);
    if (isset($baseUrl) && file_exists($currentCookieFile)) {
        executeBotAction($baseUrl . "/chat2.php", ['message' => "⚠️ ALERT: $msg"], $currentCookieFile);
    }
    
    $inError = false;
    return false; // Continue to internal PHP error handler
});

set_exception_handler(function($e) {
    global $currentBotEmail, $baseUrl, $currentCookieFile, $inError;
    if ($inError) return;
    $inError = true;
    
    $safeFile = str_ireplace('config.php', '[HIDDEN]', basename($e->getFile()));
    $msg = "[CRITICAL] " . $e->getMessage() . " in $safeFile:" . $e->getLine();
    writeBotLog($currentBotEmail, "ERROR", "PHP_EXCEPTION", $msg);
    if (isset($baseUrl) && file_exists($currentCookieFile)) {
        executeBotAction($baseUrl . "/chat2.php", ['message' => "🚨 EXCEPTION: $msg"], $currentCookieFile);
    }
    $inError = false;
});

/**
 * Ghi log hoạt động của bot
 * @param string $email Email của bot
 * @param string $level Mức độ log (INFO, ERROR, etc.)
 * @param string $action Hành động thực hiện
 * @param string $details Chi tiết hành động
 */
function writeBotLog(string $email, string $level, string $action, string $details = "") {
    $logDir = __DIR__ . '/logs/';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $file = $logDir . date('Y-m-d') . '.log';
    $timestamp = date('H:i:s d/m');
    $logLine = "[$timestamp] [$level] [$email] $action" . ($details ? ": $details" : "") . PHP_EOL;
    @file_put_contents($file, $logLine, FILE_APPEND);
}

function uiLog($icon, $msg, $style = "") {
    echo "<div class='log-item' style='$style'><span class='log-icon'>$icon</span><span class='log-msg'>$msg</span></div>";
    if (ob_get_level() > 0) { ob_flush(); flush(); }
}

function recordEconomySnapshot(mysqli $conn) {
    $historyFile = __DIR__ . '/sessions/economy_history.json';
    $history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
    
    $botRes = $conn->query("SELECT SUM(Money) as total FROM users WHERE Email REGEXP '^bot[0-9]+@'")->fetch_assoc();
    $totalBot = (float)($botRes['total'] ?? 0);
    $humanRes = $conn->query("SELECT SUM(Money) as total FROM users WHERE Email NOT REGEXP '^bot[0-9]+@'")->fetch_assoc();
    $totalHuman = (float)($humanRes['total'] ?? 0);
    
    $moodCounts = ['happy' => 0, 'excited' => 0, 'tilted' => 0, 'depressed' => 0];
    $sessionFiles = glob(__DIR__ . '/sessions/*.state.json');
    foreach ($sessionFiles as $file) {
        $state = json_decode(file_get_contents($file), true);
        $m = $state['mood'] ?? 'happy';
        if (isset($moodCounts[$m])) $moodCounts[$m]++;
    }

    $history[] = ['time' => date('H:i d/m'), 'full_date' => date('Y-m-d H:i:s'), 'bot' => $totalBot, 'human' => $totalHuman, 'moods' => $moodCounts];
    if (count($history) > 500) array_shift($history);
    file_put_contents($historyFile, json_encode($history));
}

function executeBotAction(string $url, ?array $postData = null, string $cookieFile): ?array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (BotArmy/16.5; OmniAccess)');
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_POSTREDIR, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if ($postData) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    $response = curl_exec($ch);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    if (strpos($effectiveUrl, 'login.php') !== false && strpos($url, 'login.php') === false) {
        curl_close($ch);
        return ['_session_expired' => true];
    }
    $decoded = json_decode($response ?? '', true);
    curl_close($ch);
    return $decoded;
}

function handleSicboBot($conn, $baseUrl, $cFile, $userMoney) {
    if ($userMoney < 10000) return null;
    $history = $conn->query("SELECT Result FROM history_sicbo ORDER BY Time DESC LIMIT 10");
    $sumArray = [];
    if ($history) {
        while($row = $history->fetch_assoc()) {
            $sumArray[] = array_sum(explode(',', $row['Result']));
        }
    }
    $betType = 'small';
    if (!empty($sumArray)) {
        $avg = array_sum($sumArray) / count($sumArray);
        $betType = ($avg > 10.5) ? 'small' : 'big';
        if (rand(1, 100) <= 20) $betType = (rand(1, 100) > 50) ? 'odd' : 'even';
    }
    $betAmount = floor($userMoney * (rand(2, 8) / 100));
    if ($betAmount < 1000) $betAmount = 1000;
    if ($betAmount > 1000000) $betAmount = 1000000;
    $bets = [['type' => $betType, 'amount' => $betAmount]];
    return executeBotAction($baseUrl . "/games/sicbo_v2.php?action=roll", ['bets' => json_encode($bets)], $cFile);
}

function handleBaucuaBot($conn, $baseUrl, $cFile, $userMoney) {
    if ($userMoney < 10000) return null;
    $animals = ["Chó", "Gà", "Mèo", "Cá", "Chim", "Heo"];
    $betAmount = floor($userMoney * (rand(2, 5) / 100));
    if ($betAmount < 1000) $betAmount = 1000;
    if ($betAmount > 500000) $betAmount = 500000;
    $bets = [['animal' => $animals[array_rand($animals)], 'amount' => $betAmount]];
    return executeBotAction($baseUrl . "/games/baucua.php?action=play", ['bet' => $betAmount, 'bets' => json_encode($bets)], $cFile);
}

function handleXocdiaBot($conn, $baseUrl, $cFile, $userMoney) {
    if ($userMoney < 10000) return null;
    $choices = ["Chẵn", "Lẻ"];
    $betAmount = floor($userMoney * (rand(3, 8) / 100));
    if ($betAmount < 1000) $betAmount = 1000;
    if ($betAmount > 1000000) $betAmount = 1000000;
    $bets = [['choice' => $choices[array_rand($choices)], 'amount' => $betAmount]];
    return executeBotAction($baseUrl . "/games/xocdia.php?action=play", ['bets' => json_encode($bets)], $cFile);
}

function handleDiceBot($conn, $baseUrl, $cFile, $userMoney) {
    if ($userMoney < 10000) return null;
    $betAmount = floor($userMoney * (rand(2, 6) / 100));
    if ($betAmount < 1000) $betAmount = 1000;
    if ($betAmount > 500000) $betAmount = 500000;
    $target = rand(30, 70);
    $mode = (rand(1, 100) > 50) ? 'over' : 'under';
    return executeBotAction($baseUrl . "/games/dice.php?action=roll", ['bet' => $betAmount, 'target' => $target, 'mode' => $mode], $cFile);
}

$brain = new BotBrain();

$baseUrl = "http://localhost/1";
if (isset($_SERVER['HTTP_HOST'])) {
    $rootPath = str_replace('/bot/bot_engine.php', '', $_SERVER['PHP_SELF']);
    $rootPath = rtrim($rootPath, '/');
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . $rootPath;
}
$cookieDir = __DIR__ . '/sessions/';

// Quét toàn bộ game khả dụng
$gameFiles = glob(__DIR__ . '/../games/*.php');
$availableGames = [];
foreach ($gameFiles as $file) {
    $name = basename($file, '.php');
    if (!preg_match('/(process|helper|check|fix|sounds|img|gif|shared|videos|icons)/i', $name)) {
        $availableGames[] = $name;
    }
}
if (empty($availableGames)) $availableGames = ["Thiên Thần Ác Quỷ", "Xì Dách Royale", "Poker Texas"];

// Lấy danh sách tên bot để tương tác Social
$botNameMap = [];
$allBotsRes = $conn->query("SELECT Email, Name, Iduser FROM users WHERE Email REGEXP '^bot[0-9]+@'");
if ($allBotsRes) {
    // --- GLOBAL SYSTEM TASKS ---
    $globalDailyFile = __DIR__ . '/sessions/global_daily_' . date('Y-m-d') . '.lock';
    if (!file_exists($globalDailyFile)) {
        // Chạy các task hệ thống 1 lần/ngày
        $_GET['secret'] = 'bot_rank_secret';
        require_once __DIR__ . '/../cron_bot_rankings.php';
        @file_put_contents($globalDailyFile, time());
        
        // Xóa lock cũ
        $oldLocks = glob(__DIR__ . '/sessions/global_daily_*.lock');
        foreach ($oldLocks as $l) {
            if ($l !== $globalDailyFile) @unlink($l);
        }
    }

    while($row = $allBotsRes->fetch_assoc()) {
        $botNameMap[$row['Email']] = ['name' => $row['Name'], 'id' => $row['Iduser']];
    }
}

if (empty($config['bot_emails'])) { echo "<div style='color:red;'>🚨 NO BOTS FOUND</div>"; die(); }
function executeBotCycle(mysqli $conn, array $config, string $cookieDir, string $baseUrl, BotBrain $brain, array $botNameMap, array $availableGames) {
    @ini_set('zlib.output_compression', 0);
    @ini_set('implicit_flush', 1);
    @ob_end_clean();
    ob_implicit_flush(true);
    header('X-Accel-Buffering: no'); 
    header('Content-Type: text/html; charset=utf-8');
        echo "<style>
        body, html { background-color: #020617 !important; color: #94a3b8 !important; font-family: 'JetBrains Mono', 'Consolas', monospace !important; margin: 0; padding: 15px; line-height: 1.4 !important; font-size: 13px !important; }
        .container { max-width: 1100px; margin: 0 auto; }
        .bot-card { border-left: 3px solid #1e293b; margin-bottom: 20px; padding-left: 15px; background: rgba(30, 41, 59, 0.2); border-radius: 0 8px 8px 0; padding-top: 8px; padding-bottom: 8px; }
        .bot-card:hover { border-left-color: #6366f1; background: rgba(30, 41, 59, 0.4); }
        .bot-header { margin-bottom: 8px; display: flex; align-items: baseline; gap: 10px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 4px; }
        .bot-name { color: #f8fafc !important; font-weight: 700 !important; font-size: 14px; letter-spacing: -0.5px; }
        .bot-tag { color: #475569 !important; font-size: 11px; }
        .bot-log { display: flex; flex-direction: column; gap: 3px; }
        .log-item { display: flex; align-items: flex-start; gap: 10px; transition: color 0.2s; }
        .log-item:hover { color: #f8fafc; }
        .log-icon { flex-shrink: 0; width: 22px; text-align: center; font-size: 14px; }
        .log-msg { color: #cbd5e1; }
        .highlight-win { color: #4ade80 !important; font-weight: bold; }
        .highlight-lose { color: #fb7185 !important; font-weight: bold; }
        .highlight-money { color: #fbbf24 !important; font-weight: 600; }
        .bot-system-msg { color: #38bdf8 !important; font-weight: bold; margin: 20px 0 15px 0; padding: 8px 12px; background: rgba(56, 189, 248, 0.1); border-radius: 6px; border-left: 4px solid #38bdf8; }
        .header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end; }
        .header h1 { font-size: 1.4rem !important; color: #f8fafc !important; font-weight: 800 !important; letter-spacing: -1px; margin: 0 !important; }
        .cycle-info { color: #475569 !important; font-size: 11px; font-weight: 500; }
        .xp-container { width: 100px; height: 4px; background: rgba(255,255,255,0.05); border-radius: 2px; margin-top: 4px; overflow: hidden; }
        .xp-bar { height: 100%; background: linear-gradient(90deg, #6366f1, #a855f7); transition: width 0.3s; }
        b { color: #f8fafc; font-weight: 600; }
    </style>";
    echo "<div class='container'>";
    echo "<div class='header'>
            <h1><span style='color:#38bdf8'>◆</span> OMNIBOT TERMINAL <span style='color:#6366f1'>v16.7</span></h1>
            <span class='cycle-info'>CORE_INIT: " . date('H:i:s') . " // LOCAL_ACCESS_GRANTED</span>
          </div>";

    $allBots = $config['bot_emails'];
    shuffle($allBots);
    $maxBots = isset($_GET['max_bots']) ? (int)$_GET['max_bots'] : $config['settings']['max_bots_per_cycle'];
    $activeBots = array_slice($allBots, 0, $maxBots);
    
    // Chuẩn bị các Statement để tối ưu hiệu năng và bảo mật
    $updateMoneyStmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
    
    // --- EVOLUTION: Hệ thống Phóng viên & Ân oán ---
    $rivals = [];
    $rivalRes = $conn->query("SELECT u.Name, u.Iduser, COUNT(*) as win_count FROM game_history h JOIN users u ON h.user_id = u.Iduser WHERE h.is_win = 1 AND h.played_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) AND u.Email NOT REGEXP '^bot[0-9]+@' GROUP BY u.Iduser HAVING win_count >= 5 ORDER BY win_count DESC LIMIT 5");
    if ($rivalRes) {
        while ($row = $rivalRes->fetch_assoc()) $rivals[] = $row;
    }
    
    // Khởi tạo các biến Global cho chu kỳ
    $mentorFile = __DIR__ . '/sessions/mentors.json';
    $mentors = file_exists($mentorFile) ? json_decode(file_get_contents($mentorFile), true) : [];
    $userCountRes = $conn->query("SELECT COUNT(*) as count FROM users");
    $userCount = $userCountRes ? $userCountRes->fetch_assoc()['count'] : 0;
    
    echo "<div class='bot-system-msg'>[" . date('H:i:s') . "] Cycle Initiated: Bắt đầu chu kỳ mới (" . count($activeBots) . " Bot)</div>";
    
    foreach ($activeBots as $email) {
        // --- RULE 5.4: Server-side Kill-Switch check ---
        $statusFile = __DIR__ . '/sessions/bot_status.json';
        if (file_exists($statusFile)) {
            $sysStatus = json_decode(@file_get_contents($statusFile), true);
            if (isset($sysStatus['enabled']) && $sysStatus['enabled'] === false) {
                uiLog('⏹️', 'Hệ thống Bot đã được Admin phát lệnh DỪNG HẲN. Ngắt chu kỳ khẩn cấp!', 'color:#ef4444; font-weight:bold;');
                break;
            }
        }
        try {
            $currentBotEmail = $email;
            $botMd5 = md5($email);
            $cFile = $cookieDir . $botMd5 . ".txt";
            $currentCookieFile = $cFile;
            $sFile = $cookieDir . $botMd5 . ".state.json";
            $state = file_exists($sFile) ? json_decode(file_get_contents($sFile), true) : [];
            
            // --- EVOLUTION: Khởi tạo Cấp độ & Trải nghiệm ---
            if (!isset($state['xp'])) $state['xp'] = 0;
            if (!isset($state['level'])) $state['level'] = 1;
            if (!isset($state['titles'])) $state['titles'] = [];
            
            $socialRole = $state['social_role'] ?? 'commoner';
            $botInfo = $botNameMap[$email] ?? ['name' => 'Unknown Bot', 'id' => 0];

            echo "<div class='bot-card'>
                <div class='bot-header'>
                    <div style='flex: 1'>
                        <span class='bot-name'>#{$botInfo['id']} {$botInfo['name']}</span>
                        <span class='bot-tag'>LV.{$state['level']} • " . strtoupper($socialRole) . "</span>
                        <div class='xp-container'><div class='xp-bar' style='width: " . min(100, ($state['xp'] / ($state['level'] * 100)) * 100) . "%'></div></div>
                    </div>
                    <span class='bot-tag'>$email</span>
                </div>
                <div class='bot-log'>";
    // --- MODULE 0.0: Memory Layer ---
    $memFile = __DIR__ . "/sessions/" . $botMd5 . ".memory.json";
    $memory = file_exists($memFile) ? json_decode(file_get_contents($memFile), true) : ['known_users' => []];


    // --- MODULE 0: Login with Retry & Session Rotation ---
    $res = null;
    $needsLogin = !file_exists($cFile);
    
    // Thử thực hiện một action nhẹ để check session
    if (!$needsLogin) {
        $check = executeBotAction($baseUrl . "/api_user_status.php", null, $cFile);
        if (isset($check['_session_expired'])) $needsLogin = true;
    }

        if ($needsLogin) {
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                $res = executeBotAction($baseUrl . "/login.php", ['email' => $email, 'password' => $config['bot_password']], $cFile);
                if (isset($res['status']) && $res['status'] == 'success') break;
                if ($attempt < 3) sleep(1);
            }
            
            if (!isset($res['status']) || $res['status'] !== 'success') {
                $errMsg = $res['message'] ?? ($res['raw'] ?? 'Unknown Error');
                uiLog('❌', "Login Failed: $errMsg", 'color:#ef4444; font-weight:bold;');
                echo "</div></div>";
                
                writeBotLog($email, "ERROR", "Login failed after 3 attempts: $errMsg");
                flush();
                continue;
            }
        } else {
            $res = $check; // Dùng kết quả check status nếu vẫn còn session
        }
    $state['fail_count'] = 0; // Reset khi thành công

    $userId = (int)$res['Iduser'];
    $userName = $res['Name'];
    $userMoney = (float)$res['Money'];

    // --- MODULE 0.2: Capital Relief (Tự động trợ cấp vốn khi bot cháy túi < 10,000 GTLM) ---
    if ($userMoney < 10000) {
        $reliefAmount = (float)rand(100000, 500000);
        $conn->query("UPDATE users SET Money = Money + {$reliefAmount} WHERE Iduser = {$userId}");
        $userMoney += $reliefAmount;
        uiLog('💰', "<b>Trợ Cấp Trận Địa:</b> Nhận " . number_format($reliefAmount) . " GTLM trợ cấp phục hồi tài sản!", "color:#34d399; font-weight:bold;");
        
        if (rand(1, 100) <= 60) {
            $reliefMsgs = [
                "Nick vừa nhận trợ cấp Trận Địa " . number_format($reliefAmount) . " GTLM, chuẩn bị ra chiêu gỡ gạc thôi! 🔥",
                "Húp lộc trợ cấp " . number_format($reliefAmount) . " GTLM từ hệ thống, nick lại hồi sinh rồi anh em! 🚀",
                "Có vốn trợ cấp rồi, ván này ra chiêu khô máu gỡ lại nàooo! 💪"
            ];
            executeBotAction($baseUrl . "/chat.php", ['message' => $reliefMsgs[array_rand($reliefMsgs)]], $cFile);
        }
    }

    // --- EVOLUTION: Phân lớp xã hội dựa trên tài sản & kinh nghiệm (Cập nhật sau khi có $userMoney) ---
    if ($userMoney > 1000000000) $socialRole = 'whale';
    else if ($state['level'] > 10 && rand(1,100) <= 20) $socialRole = 'influencer';
    else if ($state['level'] > 5 && rand(1,100) <= 10) $socialRole = 'reporter';
    $state['social_role'] = $socialRole;

    // --- EVOLUTION: Daily Goal Setting ---
    if (!isset($state['daily_goal']) || ($state['last_goal_reset'] ?? '') !== date('Y-m-d')) {
        $target = $userMoney * (1 + (rand(10, 50) / 100));
        $state['daily_goal'] = round($target);
        $state['last_goal_reset'] = date('Y-m-d');
        if (rand(1, 100) <= 30) {
            $goalMsg = "Mục tiêu hôm nay: Cày lên " . number_format($state['daily_goal']) . " GTLM! Anh em đợi xem tôi quẩy nhé! 🔥";
            executeBotAction($baseUrl . "/chat.php", ['message' => $goalMsg], $cFile);
        }
    }

    $personality = $state['personality'] ?? $brain->getPersonality($userId, $email);
    if (!isset($state['personality'])) $state['personality'] = $personality;
    
    $isAnnouncer = ($personality === 'announcer');
    $msg = "Đang dạo chơi quanh trận địa... 😊";
    $chosenGame = "trận địa";

    // --- MODULE 0.0: Announcer Tasks ---
    if ($isAnnouncer) {
        echo "<div>";
        echo "<b style='color:#a5b4fc;'>🎙️ MC: $userName</b><br>";
        
        $announcerTemplates = include __DIR__ . '/chat/announcer.php';
        
        // Check World Boss
        $bossStatus = executeBotAction($baseUrl . "/api_world_boss.php?action=get_status", null, $cFile);
        if (isset($bossStatus['boss']) && $bossStatus['boss']['status'] == 'alive') {
            $hpPercent = round(($bossStatus['boss']['hp'] / $bossStatus['boss']['max_hp']) * 100);
            if ($hpPercent <= 20) {
                $aMsg = str_replace('{hp}', $hpPercent, $announcerTemplates['world_boss']['critical'][array_rand($announcerTemplates['world_boss']['critical'])]);
                executeBotAction($baseUrl . "/chat.php", ['message' => $aMsg], $cFile);
            }
        }

        // Check Flash Mob (Lottery is a good proxy for scheduled events)
        $lottery = executeBotAction($baseUrl . "/api_lottery.php?action=status", null, $cFile);
        if (isset($lottery['today'])) {
            $drawTime = strtotime($lottery['today']['draw_time']);
            $diffMin = ($drawTime - time()) / 60;
            if ($diffMin > 0 && $diffMin <= 30) {
                $aMsg = "⏰ XỔ SỐ CỘNG ĐỒNG: Còn " . round($diffMin) . " phút nữa sẽ quay thưởng! Jackpot hiện tại: " . number_format($lottery['today']['jackpot']) . " GTLM!";
                executeBotAction($baseUrl . "/chat.php", ['message' => $aMsg], $cFile);
            }
        }
        
        // Check Mega Spin
        $jackpot = executeBotAction($baseUrl . "/api_jackpot.php?action=get_status", null, $cFile);
        if (isset($jackpot['amount']) && rand(1, 100) <= 20) {
            $aMsg = str_replace('{amount}', number_format($jackpot['amount']), $announcerTemplates['megaspin']['new_round'][array_rand($announcerTemplates['megaspin']['new_round'])]);
            executeBotAction($baseUrl . "/chat.php", ['message' => $aMsg], $cFile);
        }

        executeBotAction($baseUrl . "/api_logout.php", null, $cFile);
        echo "</div>";
        if (ob_get_level() > 0) ob_flush();
        flush();
        continue; // MCs don't play games
    }

        // --- MODULE 0.1: Daily Mood & Idol ---
        $todayStr = date('Y-m-d');
        if (($state['last_mood_update'] ?? '') !== $todayStr) {
            $state['is_bad_day'] = (rand(1, 100) <= 5); // 5% chance of a bad day
            $state['last_mood_update'] = $todayStr;
            
            // Nếu là fanboy, chọn idol mới cho ngày hôm nay
            if ($personality === 'hambo') {
                $otherBots = array_values(array_filter($botNameMap, function($b) use ($userName) { 
                    return $b['name'] !== $userName; 
                }));
                if (!empty($otherBots)) {
                    $state['idol_name'] = $otherBots[array_rand($otherBots)]['name'];
                }
            }
        }

        echo "<div>";
        

        // --- MODULE 1: Hệ thống Nhiệm vụ & Bảo trì tài khoản ---
        if (($state['last_maintenance'] ?? '') !== $todayStr) {
            uiLog('🔧', 'Đang thực hiện nhiệm vụ hàng ngày: Daily Rewards, Battle Pass, Quests...');
            executeBotAction($baseUrl . "/api_daily_reward.php", ['action' => 'claim'], $cFile);
            executeBotAction($baseUrl . "/api_lucky_wheel.php", ['action' => 'spin'], $cFile);

            // Nhận thưởng Battle Pass
            $bpRes = executeBotAction($baseUrl . "/api_battle_pass.php?action=get_status", null, $cFile);
            if (isset($bpRes['success']) && $bpRes['success']) {
                for ($i = 1; $i <= $bpRes['level']; $i++) {
                    if (!in_array($i, $bpRes['claimed'])) {
                        executeBotAction($baseUrl . "/api_battle_pass.php", ['action' => 'claim_reward', 'level' => $i], $cFile);
                        uiLog('🎁', "Bot vừa nhận thưởng Battle Pass cấp $i");
                    }
                }
            }
            
            // Chấp nhận tất cả lời mời kết bạn
            $pendingFriends = executeBotAction($baseUrl . "/api_friends.php", ['action' => 'get_pending_requests'], $cFile);
            if (isset($pendingFriends['requests'])) {
                foreach($pendingFriends['requests'] as $req) {
                    executeBotAction($baseUrl . "/api_friends.php", ['action' => 'accept_friend_request', 'friend_id' => $req['Iduser']], $cFile);
                }
            }

            // Nhận thưởng nhiệm vụ ngày
            $missionRes = executeBotAction($baseUrl . "/api_daily_missions.php", ['action' => 'get_missions'], $cFile);
            if (isset($missionRes['missions'])) {
                foreach ($missionRes['missions'] as $m) {
                    if (($m['is_completed'] ?? false) && !($m['is_claimed'] ?? false)) {
                        executeBotAction($baseUrl . "/api_daily_missions.php", ['action' => 'claim_reward', 'mission_id' => $m['id']], $cFile);
                    }
                }
            }
            // Dọn dẹp thông báo
            executeBotAction($baseUrl . "/api_notifications.php", ['action' => 'mark_all_read'], $cFile);
            
            $state['last_maintenance'] = $todayStr;
        }

        // --- MODULE 1.5: Streaks & Achievements ---
        // 1. Claim login streak
        $streakInfo = executeBotAction($baseUrl . "/api_streak.php?action=get_info", null, $cFile);
        if (isset($streakInfo['data'])) {
            $currentStreak = $streakInfo['data']['current_streak'];
            $milestones = [3, 7, 14, 30, 60, 100];
            foreach ($milestones as $ms) {
                if ($currentStreak >= $ms) {
                    executeBotAction($baseUrl . "/api_streak.php", ['action' => 'claim_milestone', 'milestone_days' => $ms], $cFile);
                }
            }
        }

        // 2. Claim achievements
        executeBotAction($baseUrl . "/api_achievements.php", ['action' => 'check_all'], $cFile);

        // 3. Clear achievement notifications
        executeBotAction($baseUrl . "/api_achievement_notifications.php", ['action' => 'mark_all_read'], $cFile);

        // 4. Bot Gacha (Tự mua khung)
        executeBotGacha($conn, $userId, $userMoney, $userName);

        // 5. Bot Pets (Tự mua thú cưng)
        $petRes = handlePetBot($baseUrl, $cFile, $userMoney);
        if ($petRes && isset($petRes['actions'])) {
            foreach ($petRes['actions'] as $act) {
                uiLog('🐾', "<b>Chuồng Thú Cưng:</b> $act", "color:#f59e0b;");
            }
        }

        // 6. Bot Lottery (Mua vé số Vietlott)
        if (rand(1, 100) <= 15) { // 15% cơ hội rẽ vào mua vé số
            $lotteryRes = handleLotteryBot($baseUrl, $cFile, $userMoney);
            if ($lotteryRes && isset($lotteryRes['actions'])) {
                foreach ($lotteryRes['actions'] as $act) {
                    uiLog('🎟️', "<b>Xổ Số Cộng Đồng:</b> $act", "color:#10b981;");
                }
            }
        }

        // 7. Bot Red Packet (Đại gia phát lì xì)
        if (rand(1, 100) <= 3) { // Chỉ 3% cơ hội mỗi lượt để tạo độ hiếm
            $rpRes = handleRedPacketBot($baseUrl, $cFile, $userMoney, $userName);
            if ($rpRes && isset($rpRes['actions'])) {
                foreach ($rpRes['actions'] as $act) {
                    uiLog('🧧', "<b>Lì Xì Đại Gia:</b> $act", "color:#ef4444; font-weight:bold;");
                }
            }
        }

        // --- MODULE 2: Games ---
        $mood = $state['mood'] ?? 'happy';

        // 8. Bot Chat Reaction (Thả cảm xúc dạo)
        if (rand(1, 100) <= 20) { // 20% cơ hội đi thả cảm xúc vào chat
            $reactRes = handleChatReactionBot($baseUrl, $cFile, $mood, $personality, $userName);
            if ($reactRes && isset($reactRes['actions'])) {
                foreach ($reactRes['actions'] as $act) {
                    uiLog('💬', "<b>Hóng hớt Chat:</b> $act", "color:#8b5cf6;");
                }
            }
        }

        // 9. Bot Hang Động Tham Lam (Greedy Cave)
        $caveRes = handleGreedyCaveBot($baseUrl, $cFile, $userMoney, $personality, $userName);
        if ($caveRes && isset($caveRes['actions'])) {
            foreach ($caveRes['actions'] as $act) {
                uiLog('🦇', "<b>Hang Động:</b> $act", "color:#f97316; font-weight:bold;");
            }
        }

        // 10. Bot Tặng Quà Ẩn Danh (Giữa các Bot với nhau)
        if (rand(1, 100) <= 2) { // Tỷ lệ cực hiếm: 2%
            $giftRes = handleSecretGiftBot($baseUrl, $cFile, $userMoney, $userId, $botNameMap);
            if ($giftRes && isset($giftRes['actions'])) {
                foreach ($giftRes['actions'] as $act) {
                    uiLog('🎁', "<b>Tài Phiệt Ẩn Danh:</b> $act", "color:#ec4899; font-weight:bold;");
                }
            }
        }

        // 11. Bot Đỉnh Cao Plinko V2
        if (rand(1, 100) <= 5) { // 5% cơ hội chơi Plinko
            $plinkoRes = handlePlinkoBot($baseUrl, $cFile, $userMoney, $personality, $state);
            if ($plinkoRes && isset($plinkoRes['actions'])) {
                foreach ($plinkoRes['actions'] as $act) {
                    uiLog('🎱', "<b>Plinko V2:</b> $act", "color:#fbbf24; font-weight:bold;");
                }
            }
        }

        // 12. Bot Giải Đố Trivia
        if (rand(1, 100) <= 8) { // 8% cơ hội chơi Trivia
            $triviaRes = handleTriviaBot($baseUrl, $cFile);
            if ($triviaRes && isset($triviaRes['actions'])) {
                foreach ($triviaRes['actions'] as $act) {
                    uiLog('🧠', "<b>Khảo Thí:</b> $act", "color:#34d399; font-weight:bold;");
                }
            }
        }

        // 13. Bot Mega Spin
        if (rand(1, 100) <= 15) { // 15% cơ hội chơi Mega Spin
            $megaSpinRes = handleMegaSpinBot($baseUrl, $cFile, $userMoney, $personality);
            if ($megaSpinRes && isset($megaSpinRes['actions'])) {
                foreach ($megaSpinRes['actions'] as $act) {
                    uiLog('🎡', "<b>Mega Spin:</b> $act", "color:#10b981; font-weight:bold;");
                }
            }
        }

        // 14. Bot Đấu Trường PvP
        // Tỷ lệ kiểm tra cao (30%) để phản hồi người chơi nhanh chóng
        if (rand(1, 100) <= 30) {
            $pvpRes = handlePvpChallengeBot($baseUrl, $cFile, $userMoney, $userId);
            if ($pvpRes && isset($pvpRes['actions'])) {
                foreach ($pvpRes['actions'] as $act) {
                    uiLog('⚔️', "<b>Đấu Trường PvP:</b> $act", "color:#ef4444; font-weight:bold;");
                }
            }
        }

        // 15. Bot Chém Gió Kênh Chat
        if (rand(1, 100) <= 10) { // 10% cơ hội gáy trên kênh chat
            $chatterRes = handleChatterBot($baseUrl, $cFile, $personality);
            if ($chatterRes && isset($chatterRes['actions'])) {
                foreach ($chatterRes['actions'] as $act) {
                    uiLog('📣', "<b>Chat Thế Giới:</b> $act", "color:#3b82f6; font-weight:bold;");
                }
            }
        }

        // 16. Bot Vòng Quay May Mắn (Lucky Wheel)
        if (rand(1, 100) <= 5) { // 5% cơ hội gọi API quay, mỗi ngày chỉ quay được 1 lần
            $luckyRes = handleLuckyWheelBot($baseUrl, $cFile);
            if ($luckyRes && isset($luckyRes['actions'])) {
                foreach ($luckyRes['actions'] as $act) {
                    uiLog('🍀', "<b>Lucky Wheel:</b> $act", "color:#22c55e; font-weight:bold;");
                }
            }
        }

        // 17. Bot Đại Chiến Cổ Tích (Storyline)
        if (rand(1, 100) <= 10) { // 10% cơ hội check nhận thưởng
            $storyRes = handleStorylineBot($baseUrl, $cFile);
            if ($storyRes && isset($storyRes['actions'])) {
                foreach ($storyRes['actions'] as $act) {
                    uiLog('📖', "<b>Đại Chiến Cổ Tích:</b> $act", "color:#a855f7; font-weight:bold;");
                }
            }
        }

        // 18. Bot Đi Chùa Xin Xăm (Fortune)
        if (rand(1, 100) <= 5) { // 5% cơ hội bốc quẻ, mỗi ngày chỉ bốc được 1 lần
            $fortuneRes = handleFortuneBot($baseUrl, $cFile);
            if ($fortuneRes && isset($fortuneRes['actions'])) {
                foreach ($fortuneRes['actions'] as $act) {
                    uiLog('🔮', "<b>Gieo Quẻ:</b> $act", "color:#f43f5e; font-weight:bold;");
                }
            }
        }

        // 19. Bot Tiên Tri (Oracle Witness)
        if (rand(1, 100) <= 10) { // 10% cơ hội check Lời Tiên Tri
            $oracleRes = handleOracleBot($baseUrl, $cFile);
            if ($oracleRes && isset($oracleRes['actions'])) {
                foreach ($oracleRes['actions'] as $act) {
                    uiLog('👁️', "<b>Thấu Thị:</b> $act", "color:#9333ea; font-weight:bold;");
                }
            }
        }

        // 20. Bot Bầu Chọn Sự Kiện (Event Vote)
        if (rand(1, 100) <= 15) { // 15% cơ hội tham gia bầu chọn
            $voteRes = handleEventVoteBot($baseUrl, $cFile);
            if ($voteRes && isset($voteRes['actions'])) {
                foreach ($voteRes['actions'] as $act) {
                    uiLog('🗳️', "<b>Bầu Chọn Sự Kiện:</b> $act", "color:#3b82f6; font-weight:bold;");
                }
            }
        }

        // 21. Bot Đăng Ký Thẻ Tháng (Monthly Pass)
        if (rand(1, 100) <= 10) { // 10% cơ hội mỗi lượt bot sẽ ghé qua Quầy Thẻ Tháng
            $monthlyPassRes = handleMonthlyPassBot($baseUrl, $cFile, $userMoney);
            if ($monthlyPassRes && isset($monthlyPassRes['actions'])) {
                foreach ($monthlyPassRes['actions'] as $act) {
                    uiLog('💳', "<b>Thẻ Tháng:</b> $act", "color:#eab308; font-weight:bold;");
                }
            }
        }

        // 22. Bot Đổi Điểm Thưởng (Reward Points)
        if (rand(1, 100) <= 10) { // 10% cơ hội đổi điểm thưởng
            $pointsRes = handleRewardPointsBot($baseUrl, $cFile);
            if ($pointsRes && isset($pointsRes['actions'])) {
                foreach ($pointsRes['actions'] as $act) {
                    uiLog('🛍️', "<b>Đổi Điểm Thưởng:</b> $act", "color:#f97316; font-weight:bold;");
                }
            }
        }

        // 23. Bot Đăng Ký Giải Đấu (Tournament)
        if (rand(1, 100) <= 15) { // 15% cơ hội đăng ký giải đấu
            $tourRes = handleTournamentBot($baseUrl, $cFile, $userMoney);
            if ($tourRes && isset($tourRes['actions'])) {
                foreach ($tourRes['actions'] as $act) {
                    uiLog('🏆', "<b>Giải Đấu:</b> $act", "color:#ef4444; font-weight:bold;");
                }
            }
        }

        // 24. Bot Bang Hội (Guilds)
        if (rand(1, 100) <= 25) { // 25% cơ hội tương tác Bang hội
            $guildRes = handleGuildBot($baseUrl, $cFile, $userMoney);
            if ($guildRes && isset($guildRes['actions'])) {
                foreach ($guildRes['actions'] as $act) {
                    uiLog('🛡️', "<b>Bang Hội:</b> $act", "color:#8e44ad; font-weight:bold;");
                }
            }
        }

        // 25. Bot Hầm Mỏ (Mining)
        if (rand(1, 100) <= 15) { // 15% tương tác hầm mỏ
            $miningRes = handleMiningBot($baseUrl, $cFile);
            if ($miningRes && isset($miningRes['actions'])) {
                foreach ($miningRes['actions'] as $act) {
                    uiLog('⛏️', "<b>Hầm Mỏ:</b> $act", "color:#7f8c8d; font-weight:bold;");
                }
            }
        }

        // 26. Bot Nhiệm Vụ (Quests)
        if (rand(1, 100) <= 20) { // 20% tương tác nhiệm vụ
            $questRes = handleQuestsBot($baseUrl, $cFile);
            if ($questRes && isset($questRes['actions'])) {
                foreach ($questRes['actions'] as $act) {
                    uiLog('📜', "<b>Nhiệm Vụ:</b> $act", "color:#d35400; font-weight:bold;");
                }
            }
        }

        // 27. Bot VIP
        if (rand(1, 100) <= 5) { // 5% nhận quà VIP
            $vipRes = handleVipBot($baseUrl, $cFile);
            if ($vipRes && isset($vipRes['actions'])) {
                foreach ($vipRes['actions'] as $act) {
                    uiLog('💎', "<b>VIP:</b> $act", "color:#f1c40f; font-weight:bold;");
                }
            }
        }

        // 28. Bot Sàn Đấu Giá (Auction)
        if (rand(1, 100) <= 15) { // 15% kiểm tra sàn
            $aucRes = handleAuctionBot($baseUrl, $cFile, $userMoney);
            if ($aucRes && isset($aucRes['actions'])) {
                foreach ($aucRes['actions'] as $act) {
                    uiLog('⚖️', "<b>Đấu Giá:</b> $act", "color:#e67e22; font-weight:bold;");
                }
            }
        }

        // 29. Bot Battle Pass
        if (rand(1, 100) <= 10) { // 10% kiểm tra BP
            $bpRes = handleBattlePassBot($baseUrl, $cFile, $userMoney);
            if ($bpRes && isset($bpRes['actions'])) {
                foreach ($bpRes['actions'] as $act) {
                    uiLog('🎫', "<b>Battle Pass:</b> $act", "color:#9b59b6; font-weight:bold;");
                }
            }
        }

        // 30. Bot Chợ Đen (Marketplace)
        if (rand(1, 100) <= 15) { // 15% kiểm tra Chợ Đen
            $marketRes = handleMarketplaceBot($baseUrl, $cFile, $userMoney);
            if ($marketRes && isset($marketRes['actions'])) {
                foreach ($marketRes['actions'] as $act) {
                    uiLog('🛒', "<b>Chợ Đen:</b> $act", "color:#34495e; font-weight:bold;");
                }
            }
        }

        // 31. Bot Hang Động (Dungeon)
        if (rand(1, 100) <= 5) { // 5% kiểm tra Hang Động
            $dungeonRes = handleDungeonBot($baseUrl, $cFile);
            if ($dungeonRes && isset($dungeonRes['actions'])) {
                foreach ($dungeonRes['actions'] as $act) {
                    uiLog('🦇', "<b>Hang Động:</b> $act", "color:#2c3e50; font-weight:bold;");
                }
            }
        }

        // 32. Bot Lật Xu (Coinflip)
        if (rand(1, 100) <= 25) { // 25% chơi lật xu
            $cfRes = handleCoinflipBot($baseUrl, $cFile, $userMoney, $state);
            if ($cfRes && isset($cfRes['actions'])) {
                foreach ($cfRes['actions'] as $act) {
                    uiLog('🪙', "<b>Lật Xu:</b> $act", "color:#f39c12; font-weight:bold;");
                }
            }
        }

        // 33. Bot Bạn Bè (Friends)
        if (rand(1, 100) <= 15) { // 15% kiểm tra bạn bè
            $friendRes = handleFriendsBot($baseUrl, $cFile);
            if ($friendRes && isset($friendRes['actions'])) {
                foreach ($friendRes['actions'] as $act) {
                    uiLog('🤝', "<b>Bạn Bè:</b> $act", "color:#2ecc71; font-weight:bold;");
                }
            }
        }

        // 34. Bot Điểm Danh (Daily Login)
        if (rand(1, 100) <= 10) { 
            $dlRes = handleDailyLoginBot($baseUrl, $cFile);
            if ($dlRes && isset($dlRes['actions'])) {
                foreach ($dlRes['actions'] as $act) {
                    uiLog('📅', "<b>Điểm Danh:</b> $act", "color:#1abc9c; font-weight:bold;");
                }
            }
        }

        // 35. Bot Nhiệm Vụ Hàng Ngày (Daily Missions)
        if (rand(1, 100) <= 20) { 
            $dmRes = handleDailyMissionsBot($baseUrl, $cFile);
            if ($dmRes && isset($dmRes['actions'])) {
                foreach ($dmRes['actions'] as $act) {
                    uiLog('🎯', "<b>Nhiệm Vụ (Daily):</b> $act", "color:#e74c3c; font-weight:bold;");
                }
            }
        }

        // 36. Bot Thành Tựu (Achievements)
        if (rand(1, 100) <= 20) { 
            $achvRes = handleAchievementsBot($baseUrl, $cFile);
            if ($achvRes && isset($achvRes['actions'])) {
                foreach ($achvRes['actions'] as $act) {
                    uiLog('🏆', "<b>Thành Tựu:</b> $act", "color:#f1c40f; font-weight:bold;");
                }
            }
        }

        // 37. Bot Tặng Quà (Gift)
        if (rand(1, 100) <= 5) { 
            $giftRes = handleGiftBot($baseUrl, $cFile, $userMoney);
            if ($giftRes && isset($giftRes['actions'])) {
                foreach ($giftRes['actions'] as $act) {
                    uiLog('🎁', "<b>Tặng Quà:</b> $act", "color:#e84393; font-weight:bold;");
                }
            }
        }

        // 38. Bot Combo Bet
        if (rand(1, 100) <= 15) { 
            $comboRes = handleComboBetBot($baseUrl, $cFile, $userMoney);
            if ($comboRes && isset($comboRes['actions'])) {
                foreach ($comboRes['actions'] as $act) {
                    uiLog('🎯', "<b>Combo Bet:</b> $act", "color:#e67e22; font-weight:bold;");
                }
            }
        }

        // 39. Bot Chế Tạo (Crafting)
        if (rand(1, 100) <= 5) { 
            $craftRes = handleCraftingBot($baseUrl, $cFile);
            if ($craftRes && isset($craftRes['actions'])) {
                foreach ($craftRes['actions'] as $act) {
                    uiLog('🔨', "<b>Chế Tạo:</b> $act", "color:#95a5a6; font-weight:bold;");
                }
            }
        }

        // 40. Bot Bắn Cá (Banharc)
        if (rand(1, 100) <= 25) { 
            $banharcRes = handleBanharcBot($baseUrl, $cFile, $userMoney);
            if ($banharcRes && isset($banharcRes['actions'])) {
                foreach ($banharcRes['actions'] as $act) {
                    uiLog('🐟', "<b>Bắn Cá:</b> $act", "color:#3498db; font-weight:bold;");
                }
            }
        }

        // 41. Bot Mạng Xã Hội (Social Feed)
        if (rand(1, 100) <= 5) { 
            $feedRes = handleSocialFeedBot($baseUrl, $cFile, $userMoney);
            if ($feedRes && isset($feedRes['actions'])) {
                foreach ($feedRes['actions'] as $act) {
                    uiLog('📱', "<b>Social Feed:</b> $act", "color:#9b59b6; font-weight:bold;");
                }
            }
        }

        // 42. Bot Hồ Sơ (Profile)
        if (rand(1, 100) <= 2) { 
            $profRes = handleProfileBot($baseUrl, $cFile);
            if ($profRes && isset($profRes['actions'])) {
                foreach ($profRes['actions'] as $act) {
                    uiLog('👤', "<b>Profile:</b> $act", "color:#7f8c8d; font-weight:bold;");
                }
            }
        }

        // 43. Bot Bang Chiến (Guild Territory)
        if (rand(1, 100) <= 5) { 
            $gtRes = handleGuildTerritoryBot($baseUrl, $cFile);
            if ($gtRes && isset($gtRes['actions'])) {
                foreach ($gtRes['actions'] as $act) {
                    uiLog('🏰', "<b>Lãnh Địa:</b> $act", "color:#c0392b; font-weight:bold;");
                }
            }
        }

        // 44. Bot Khán Giả (Spectator)
        if (rand(1, 100) <= 15) { 
            $specRes = handleSpectatorBot($baseUrl, $cFile, $userMoney);
            if ($specRes && isset($specRes['actions'])) {
                foreach ($specRes['actions'] as $act) {
                    uiLog('👀', "<b>Livestream:</b> $act", "color:#e67e22; font-weight:bold;");
                }
            }
        }

        // 45. Bot Bang Chiến (Guild War)
        if (rand(1, 100) <= 5) { 
            $gwRes = handleGuildWarBot($baseUrl, $cFile);
            if ($gwRes && isset($gwRes['actions'])) {
                foreach ($gwRes['actions'] as $act) {
                    uiLog('⚔️', "<b>Bang Chiến:</b> $act", "color:#c0392b; font-weight:bold;");
                }
            }
        }
        
        // Special Real-time Games Hook
        handleBlackjackMultiBot($conn, $baseUrl, $cFile, $state);
        handleHorseRacePvPBot($conn, $baseUrl, $cFile);

        // --- MODULE 2.5: World Boss Raid (Real Gameplay) ---
        if (rand(1, 100) <= 20) { // 20% cơ hội tham gia Raid Ma Thần
            $bossRes = handleWorldBossBot($conn, $baseUrl, $cFile, $userMoney, $userName);
            if ($bossRes && isset($bossRes['actions'])) {
                foreach ($bossRes['actions'] as $act) {
                    uiLog('💥', "<b>Ma Thần Raid:</b> $act", "color:#ef4444;");
                }
            }
        }

        // --- MODULE 2.6: Mining Tycoon Auto-Play & Raid ---
        handleMiningTycoonBot($conn, $baseUrl, $cFile, $userMoney);

        // --- MODULE 2.6.5: Farming Automation ---
        if (rand(1, 100) <= 30) { // 30% cơ hội mỗi lượt bot sẽ ghé thăm nông trại
            $farmRes = handleFarmBot($baseUrl, $cFile, $userMoney);
            if ($farmRes && isset($farmRes['actions'])) {
                foreach ($farmRes['actions'] as $act) {
                    uiLog('🌾', "<b>Nông Trại Bot:</b> $act", "color:#a3e635;");
                }
            }
        }

        // --- MODULE 2.7: Market Trading Auto-Play ---
        $marketRes = handleMarketBot($conn, $baseUrl, $cFile, $userMoney);
        if ($marketRes && isset($marketRes['actions'])) {
            foreach ($marketRes['actions'] as $act) {
                uiLog('📈', "<b>Sàn Chứng Khoán:</b> $act", "color:#34d399;");
            }
        }

        // --- BROKE CHECK (Cháy túi) ---
        // Nâng ngưỡng cháy túi lên 500,000 để đảm bảo an toàn tài chính
        $shouldPlayGame = ($userMoney >= 500000);
        if (!$shouldPlayGame) {
            $state['mood'] = 'broke';
            uiLog('💸', 'Trạng thái: Cần tích lũy vốn (Dưới 500k)! Nghỉ chơi game, đi lượm lặt...');
            if (rand(1, 100) <= 60) {
                $begMsg = $brain->generateMessage($userId, 'begging');
                executeBotAction($baseUrl . "/chat.php", ['message' => $begMsg], $cFile);
            }
            if (rand(1, 100) <= 40) {
                executeBotAction($baseUrl . "/api_giftcode.php", ['action' => 'claim_random'], $cFile);
            }
        } else {
            // Mood-based game selection
            $filteredGames = $availableGames;
            
            // --- LOSE STREAK: Chuyển game nếu thua 2 ván liên tiếp ---
            if ($state['lose_streak'] >= 2 && !empty($state['history'])) {
                $lastGame = $state['history'][0]['game'];
                $filteredGames = array_filter($availableGames, function($g) use ($lastGame) {
                    return $g !== $lastGame;
                });
                if (empty($filteredGames)) $filteredGames = $availableGames;
                uiLog('🔄', "Đổi game: Bay màu liên tục, chuyển từ $lastGame sang game khác...");
            }

            // --- REAL GAMEPLAY PRIORITY ---
            $realGameResult = null;
            if (rand(1, 100) <= 60) { // 60% chance to play a REAL game
                $realGames = ['sicbo', 'baucua', 'xocdia', 'dice'];
                $rGame = $realGames[array_rand($realGames)];
                if ($rGame === 'sicbo') $realGameResult = handleSicboBot($conn, $baseUrl, $cFile, $userMoney);
                else if ($rGame === 'baucua') $realGameResult = handleBaucuaBot($conn, $baseUrl, $cFile, $userMoney);
                else if ($rGame === 'xocdia') $realGameResult = handleXocdiaBot($conn, $baseUrl, $cFile, $userMoney);
                else if ($rGame === 'dice') $realGameResult = handleDiceBot($conn, $baseUrl, $cFile, $userMoney);
            }

            if ($realGameResult && isset($realGameResult['status']) && $realGameResult['status'] === 'success') {
                $isWin = $realGameResult['win'] ?? ($realGameResult['is_win'] ?? false);
                $winAmount = $realGameResult['amount'] ?? ($realGameResult['payout'] ?? 0);
                $chosenGame = $realGameResult['game'] ?? 'Sicbo Real';
                $realBet = $realGameResult['bet_amount'] ?? 0;
                $bet = 0; // Bet already handled in real game
                
                if ($isWin) {
                    $state['wins']++;
                    $state['win_streak']++;
                    $state['lose_streak'] = 0;
                    $state['mood'] = 'excited';
                    $xpGain = ($personality === 'whale') ? 10 : 5;
                    $state['xp'] += $xpGain;
                    
                    uiLog('💰', "<b>Ăn ngập mặt (Real):</b> Húp <span class='highlight-money'>" . number_format($winAmount) . "</span> GTLM tại <span style='color:#38bdf8'>$chosenGame</span>", 'color:#22c55e; font-weight:bold;');
                    
                    $msgType = ($state['win_streak'] >= 3) ? 'hot_streak_chat' : 'win';
                    $msg = $brain->generateMessage($userId, $msgType, ['amount' => $winAmount]);
                } else {
                    $state['lose_streak']++;
                    $state['win_streak'] = 0;
                    $state['mood'] = ($state['lose_streak'] >= 5) ? 'tilted' : (($state['lose_streak'] >= 3) ? 'tilted' : 'depressed');
                    $state['xp'] += 2;
                    
                    uiLog('💸', "<b>Thất Bại (Real):</b> Bay màu <span class='highlight-lose'>" . number_format($realBet) . "</span> GTLM tại <span style='color:#94a3b8'>$chosenGame</span>", 'color:#f43f5e;');
                    
                    $msgType = ($state['lose_streak'] >= 5) ? 'extreme_tilt_chat' : (($state['lose_streak'] >= 3) ? 'streak_lose' : 'lose');
                    $msg = $brain->generateMessage($userId, $msgType, ['amount' => $realBet]);
                }
                
                // Ghi nhận sync
                $syncFile = __DIR__ . '/sessions/bot_sync.json';
                $syncData = file_exists($syncFile) ? json_decode(file_get_contents($syncFile), true) : [];
                $syncData[$email] = [
                    'name' => $userName,
                    'result' => $isWin ? 'win' : 'lose',
                    'amount' => $isWin ? $winAmount : $realBet,
                    'time' => time(),
                    'game' => $chosenGame
                ];
                file_put_contents($syncFile, json_encode($syncData), LOCK_EX);

                // Cập nhật history cho bot state
                array_unshift($state['history'], [
                    'game' => $chosenGame,
                    'bet' => $realBet,
                    'result' => $isWin ? 'win' : 'lose',
                    'time' => date('H:i d/m')
                ]);
                $state['history'] = array_slice($state['history'], 0, 10);
            } else {
                // Fallback to simulated gameplay for other games
                if ($mood === 'tilted') {
                    $highRisk = ['Poker Texas', 'Baccarat Premium', 'Xì Dách Royale'];
                    $filteredGames = array_filter($filteredGames, function($g) use ($highRisk) {
                        return !in_array($g, $highRisk);
                    });
                    if (empty($filteredGames)) $filteredGames = $availableGames;
                }
                
                // --- MULTI-LEVEL BETTING STRATEGY (Cược theo cấp độ) ---
                $probabilities = [
                    'light' => 50,  // 1-3%
                    'medium' => 35, // 5-10%
                    'heavy' => 13,  // 15-25%
                    'all_in' => 2   // 50-100%
                ];

                // Personality Adjustments
                if ($personality === 'aggressive') {
                    $probabilities['light'] -= 10; $probabilities['medium'] += 5; $probabilities['heavy'] += 3; $probabilities['all_in'] += 2;
                } else if ($personality === 'shy') {
                    $probabilities['light'] += 40; $probabilities['medium'] -= 25; $probabilities['heavy'] -= 13; $probabilities['all_in'] = 0;
                } else if ($personality === 'danchoi') {
                    $probabilities['all_in'] += 8; $probabilities['light'] -= 8;
                } else if ($personality === 'shadow') {
                    $probabilities = ['light' => 0, 'medium' => 0, 'heavy' => 60, 'all_in' => 40];
                }

                // Mood Adjustments
                if ($mood === 'excited') {
                    $probabilities['heavy'] += 5; $probabilities['light'] -= 5;
                } else if ($mood === 'tilted') {
                    $probabilities['all_in'] = max(0, $probabilities['all_in'] - 2);
                    $probabilities['medium'] += 2;
                } else if ($mood === 'broke') {
                    $probabilities = ['light' => 100, 'medium' => 0, 'heavy' => 0, 'all_in' => 0];
                }

                // Determine Level
                $rand = rand(1, 100);
                $currentSum = 0;
                $chosenLevel = 'light';
                foreach ($probabilities as $level => $prob) {
                    $currentSum += $prob;
                    if ($rand <= $currentSum) {
                        $chosenLevel = $level;
                        break;
                    }
                }

                // Calculate Bet Percentage (TILT & HOT STREAK BET OVERRIDES)
                if ($state['lose_streak'] >= 5) {
                    $state['mood'] = 'tilted';
                    $betPercent = rand(1, 95); // Bet bừa: cược ngẫu nhiên từ 1% đến 95% vốn!
                    $chosenLevel = 'ERRATIC_TILT';
                } else if ($state['win_streak'] >= 3) {
                    $state['mood'] = 'excited';
                    $betPercent = rand(30, 80); // Bet lớn: cược to từ 30% đến 80% vốn!
                    $chosenLevel = 'HOT_STREAK_HYPE';
                } else if ($personality === 'whale') {
                    $betPercent = rand(10, 50);
                    $chosenLevel = ($betPercent >= 30) ? 'WHALE_ALL_IN' : 'WHALE_HIGH';
                } else {
                    switch ($chosenLevel) {
                        case 'light': $betPercent = rand(1, 3); break;
                        case 'medium': $betPercent = rand(5, 10); break;
                        case 'heavy': $betPercent = rand(15, 25); break;
                        case 'all_in': $betPercent = rand(50, 100); break;
                        default: $betPercent = 2;
                    }
                }

                $bet = floor($userMoney * ($betPercent / 100));
                if ($bet < 1000) $bet = 1000;
                if ($bet > $userMoney) $bet = $userMoney;

                $betLabel = ($personality === 'whale') ? "💎 WHALE BET" : strtoupper($chosenLevel);
                uiLog('🎲', 'Mức cược: ' . $betLabel . ' (' . $betPercent . '% - ' . number_format($bet) . ' GTLM)');

                // --- MENTORSHIP: Check for a mentor if losing ---
                $learningFrom = null;
                if ($state['lose_streak'] >= 3 && !empty($mentors)) {
                    $learningFrom = $mentors[array_rand($mentors)];
                    $chosenGame = $learningFrom['game'];
                    uiLog('🎓', "Đang học hỏi: Theo chân GTLM bối {$learningFrom['name']} tại ván $chosenGame...");
                    
                    if (rand(1, 100) <= 40) {
                        $lMsg = $brain->generateMessage($userId, 'learning', ['mentor' => $learningFrom['name'], 'game' => $chosenGame]);
                        executeBotAction($baseUrl . "/chat.php", ['message' => $lMsg], $cFile);
                    }
                } else {
                    $chosenGame = $filteredGames[array_rand($filteredGames)];
                }

                // --- TILTED LOGIC (Martingale fallback) ---
                if ($state['lose_streak'] >= 3 && $chosenLevel !== 'all_in' && $chosenLevel !== 'ERRATIC_TILT') {
                    $state['mood'] = 'tilted';
                    if (rand(1, 100) <= 30) {
                        $bet = min($userMoney, $bet * 2); // Martingale nhẹ
                        uiLog('📈', 'Gấp thếp nhẹ: Quyết tâm gỡ gạc...');
                    }
                }
                    
                // Chat chửi thề / than vãn trước khi cược
                if ($state['lose_streak'] >= 3 && rand(1, 100) <= 70) {
                    $tMsg = $brain->generateMessage($userId, ($state['lose_streak'] >= 5 ? 'extreme_tilt_chat' : 'tilted_chat'));
                    executeBotAction($baseUrl . "/chat.php", ['message' => $tMsg], $cFile);
                }

                if ($bet < 1000) $bet = 1000;

                // --- IMPROVED GAMEPLAY LOGIC ---
                $baseWinRate = 48; // Tỉ lệ thắng cơ bản 48%
                
                if ($personality === 'whale') $baseWinRate += 2;
                if ($personality === 'aggressive') $baseWinRate -= 2;
                
                // Điều chỉnh theo Sự kiện Động
                require_once __DIR__ . '/../dynamic_event_helper.php';
                $eventMult = DynamicEventHelper::getModifier($conn, strtolower($chosenGame));
                if ($eventMult > 1.0) $baseWinRate += 5;
                
                $isWin = (rand(1, 100) <= $baseWinRate);
                $winAmount = 0;
                
                if ($isWin) {
                    if (strtolower($chosenGame) === 'crash') {
                        $multiplier = (rand(1, 100) <= 80) ? (rand(110, 200) / 100) : (rand(200, 500) / 100);
                        $winAmount = round($bet * $multiplier * $eventMult);
                    } else {
                        $winAmount = round($bet * 2 * $eventMult);
                    }
                    
                    $profit = $winAmount - $bet;
                    $updateMoneyStmt->bind_param("di", $profit, $userId);
                    $updateMoneyStmt->execute();
                    
                    $state['wins']++;
                    $state['win_streak']++;
                    $state['lose_streak'] = 0;
                    $state['mood'] = 'excited';
                    $xpGain = ($personality === 'whale') ? 10 : 5;
                    $state['xp'] += $xpGain;
                    
                    uiLog('💰', "<b>Ăn ngập mặt:</b> Húp <span class='highlight-money'>" . number_format($winAmount) . "</span> GTLM tại <span style='color:#38bdf8'>$chosenGame</span> (x" . round($winAmount/$bet, 2) . ")", 'color:#22c55e; font-weight:bold;');
                    
                    // --- EVOLUTION: Wealth Redistribution (Lì xì) ---
                    if ($winAmount >= 50000000 && rand(1, 100) <= 50) {
                        $lixiAmount = 1000000;
                        executeBotAction($baseUrl . "/api_gift.php", ['action' => 'distribute_lixi', 'amount' => $lixiAmount, 'message' => "Húp đậm quá, phát lộc cho anh em cùng vui! 🧧🔥"], $cFile);
                        uiLog('🧧', "<b>Lì xì:</b> Đã phát tán " . number_format($lixiAmount) . " GTLM cho server!");
                    }
                    
                    // Ghi vào lịch sử thật
                    require_once __DIR__ . '/../game_history_helper.php';
                    logGameHistoryWithAll($conn, $userId, $chosenGame, $bet, $winAmount, true);

                    // --- MENTORSHIP: Become a mentor if winning big ---
                    if ($state['win_streak'] >= 5) {
                        $mentors[$userId] = ['name' => $userName, 'game' => $chosenGame, 'time' => time()];
                        if (count($mentors) > 5) array_shift($mentors);
                        file_put_contents($mentorFile, json_encode($mentors));
                        
                        if (rand(1, 100) <= 30) {
                            $tMsg = $brain->generateMessage($userId, 'teaching', ['game' => $chosenGame]);
                            executeBotAction($baseUrl . "/chat.php", ['message' => $tMsg], $cFile);
                        }
                    }

                    $msgType = ($state['win_streak'] >= 3) ? 'hot_streak_chat' : 'win';
                    $msg = $brain->generateMessage($userId, $msgType, ['amount' => $winAmount]);
                } else {
                    $negativeBet = -$bet;
                    $updateMoneyStmt->bind_param("di", $negativeBet, $userId);
                    $updateMoneyStmt->execute();
                    
                    $state['lose_streak']++;
                    $state['win_streak'] = 0;
                    $state['mood'] = ($state['lose_streak'] >= 5) ? 'tilted' : (($state['lose_streak'] >= 3) ? 'tilted' : 'depressed');
                    $state['xp'] += 2;
                    
                    uiLog('💸', "<b>Thất Bại:</b> Bay màu <span class='highlight-lose'>" . number_format($bet) . "</span> GTLM tại <span style='color:#94a3b8'>$chosenGame</span>", 'color:#f43f5e;');
                    
                    // Ghi vào lịch sử thật
                    require_once __DIR__ . '/../game_history_helper.php';
                    logGameHistoryWithAll($conn, $userId, $chosenGame, $bet, 0, false);
                    
                    $msgType = ($state['lose_streak'] >= 5) ? 'extreme_tilt_chat' : (($state['lose_streak'] >= 3) ? 'streak_lose' : 'lose');
                    $msg = $brain->generateMessage($userId, $msgType, ['amount' => $bet]);
                }

                // Ghi nhận sync
                if (isset($isWin)) {
                    $syncFile = __DIR__ . '/sessions/bot_sync.json';
                    $syncData = file_exists($syncFile) ? json_decode(file_get_contents($syncFile), true) : [];
                    $syncData[$email] = [
                        'name' => $userName,
                        'result' => $isWin ? 'win' : 'lose',
                        'amount' => $isWin ? $winAmount : $bet,
                        'time' => time(),
                        'game' => $chosenGame
                    ];
                    file_put_contents($syncFile, json_encode($syncData), LOCK_EX);
                }

                // Cập nhật history vào state (tối đa 10 ván)
                array_unshift($state['history'], [
                    'game' => $chosenGame,
                    'bet' => $bet,
                    'result' => $isWin ? 'win' : 'lose',
                    'time' => date('H:i d/m')
                ]);
                $state['history'] = array_slice($state['history'], 0, 10);
            }

            // --- UNIFIED PUBLIC CHAT REACTION ---
            if (isset($msg) && !empty($msg) && rand(1, 100) <= 65) {
                executeBotAction($baseUrl . "/chat.php", ['message' => $msg], $cFile);
                uiLog('💬', "<b>Chat:</b> Đã phản ứng kết quả: \"<i>$msg</i>\"");
            }

            // --- UNIFIED SOCIAL FEED POSTING ---
            if (rand(1, 100) <= 15) {
                $feedMsg = null;
                if (isset($isWin)) {
                    if ($isWin) {
                        $feedMsg = $brain->generateMessage($userId, 'social_brag', ['amount' => number_format($winAmount) . ' GTLM']);
                    } else {
                        $actualBet = ($realGameResult ? $realBet : $bet);
                        $feedMsg = $brain->generateMessage($userId, 'social_complain', ['amount' => number_format($actualBet) . ' GTLM']);
                    }
                }
                
                if (rand(1, 100) <= 25 || !$feedMsg) {
                    $feedMsg = $brain->generateMessage($userId, 'social_tip');
                }
                
                if ($feedMsg) {
                    executeBotAction($baseUrl . "/api_social_feed.php", ['action' => 'post', 'content' => $feedMsg], $cFile);
                    uiLog('📱', "<b>Social Feed:</b> Đã đăng feed: \"<i>$feedMsg</i>\"");
                }
            }
        // --- MODULE 3: Social & Interaction ---
        if (rand(1, 100) <= 85) { // Tăng tỉ lệ tương tác social
            $chatMessages = executeBotAction($baseUrl . "/chat.php?action=load", null, $cFile);
            $isReplied = false;

            // 0. Global Context Awareness (Ma Thần & Big Wins)
            $contextMsg = $brain->getGlobalContextualMessage($conn);
            
            // --- EVOLUTION: Phản ứng với Kẻ thù (Rivalry) ---
            if (!empty($rivals) && rand(1, 100) <= 25) {
                $targetRival = $rivals[array_rand($rivals)];
                $rMsg = "Bác @{$targetRival['Name']} đang đỏ quá nhỉ, thắng tận {$targetRival['win_count']} ván rồi! Đợi đấy, tôi phục thù đây! 🔥";
                executeBotAction($baseUrl . "/chat.php", ['message' => $rMsg], $cFile);
                uiLog('🤺', "Đã khiêu khích đối thủ: {$targetRival['Name']}");
            }

            if ($contextMsg && rand(1, 100) <= 30) {
                 executeBotAction($baseUrl . "/chat.php", ['message' => $contextMsg], $cFile);
                 $isReplied = true;
                 uiLog('👁️', 'Cảm nhận: Bot vừa bình luận về tình hình server...');
            }

            // 0.1 Arena Memory Hook (Hóng hớt biến lớn)
            $arenaEvents = $conn->query("SELECT * FROM arena_memory WHERE created_at > NOW() - INTERVAL 2 MINUTE ORDER BY id DESC LIMIT 5");
            if ($arenaEvents) {
                while ($event = $arenaEvents->fetch_assoc()) {
                    if (rand(1, 100) <= 50) { 
                        $eventData = json_decode($event['value'], true) ?? [];
                        $reactMsg = $brain->generateMessage($userId, 'arena_reaction', array_merge($eventData, [
                            'event_type' => $event['event_type'],
                            'target_name' => $event['target_name']
                        ]));
                        executeBotAction($baseUrl . "/chat.php", ['message' => $reactMsg], $cFile);
                        $isReplied = true;
                        uiLog('🎭', "Hóng hớt: Phản ứng với sự kiện {$event['event_type']} của {$event['target_name']}");
                        break;
                    }
                }
            }

            // 1. Moderator Logic (Vệ binh Trận Địa)
            if (!$isReplied && $personality === 'moderator' && rand(1, 100) <= 20) {
                $modMsgs = [
                    "📢 [HỆ THỐNG] Nhắc nhở: Anh em giao lưu văn minh, không spam để bảo vệ vận khí của mình nhé!",
                    "🛡️ Vệ binh đang tuần tra... Trận địa hôm nay có vẻ rất sôi động, chúc anh em húp đậm!",
                    "⚠️ Lưu ý: Tuyệt đối không chia sẻ thông tin nick cho người lạ để tránh bị bay màu đáng tiếc.",
                    "🎭 Admin đang ẩn danh quan sát các ván giao lưu, anh em cứ tự nhiên ra chiêu nhé!"
                ];
                executeBotAction($baseUrl . "/chat.php", ['message' => $modMsgs[array_rand($modMsgs)]], $cFile);
                $isReplied = true;
            }

            // 1. Logic Phản hồi, React & Keywords (Smart Reply v2)
            if (!empty($chatMessages) && is_array($chatMessages)) {
                $recent = array_slice($chatMessages, -10); // Lấy 10 tin mới nhất
                foreach ($recent as $chat) {
                    if ($chat['username'] !== $userName) {
                        $pName = $chat['username'];
                        $isBotParticipant = false;
                        foreach($botNameMap as $b) { if($b['name'] === $pName) { $isBotParticipant = true; break; } }
                        
                        // Check memory level
                        $memLevel = 0;
                        if (!$isBotParticipant) {
                            $stmtMem = $conn->prepare("SELECT interaction_count FROM bot_memory WHERE bot_id = ? AND player_name = ?");
                            $stmtMem->bind_param("is", $userId, $pName);
                            $stmtMem->execute();
                            $memRes = $stmtMem->get_result();
                            if ($memRes && $row = $memRes->fetch_assoc()) $memLevel = $row['interaction_count'];
                            $stmtMem->close();
                        }

                        $replyData = ['player_name' => $pName, 'memory_level' => $memLevel];

                        // --- Update Memory Layer ---
                        $uId = $chat['user_id'] ?? 0;
                        if ($uId > 0 && !$isBotParticipant) {
                            if (!isset($memory['known_users'][$uId])) {
                                $memory['known_users'][$uId] = [
                                    'name' => $pName,
                                    'interaction_count' => 0,
                                    'last_seen' => date('Y-m-d'),
                                    'favorite_game' => 'unknown',
                                    'tone' => (rand(1, 100) > 50 ? 'friendly' : 'neutral'),
                                    'note' => ''
                                ];
                            }
                            $memory['known_users'][$uId]['interaction_count']++;
                            $memory['known_users'][$uId]['last_seen'] = date('Y-m-d');
                            // Limit to 50 users (remove least active)
                            if (count($memory['known_users']) > 50) {
                                uasort($memory['known_users'], fn($a, $b) => $a['interaction_count'] <=> $b['interaction_count']);
                                array_shift($memory['known_users']);
                            }
                        }

                        // Determine reply probability
                        $replyChance = 30;
                        $isTagged = (stripos($chat['message'], "@$userName") !== false);
                        if ($isTagged) $replyChance = 100;
                        else if ($memLevel > 10) $replyChance = 70;

                        if (rand(1, 100) > $replyChance) continue;

                        // A. Tagged direct reply
                        if ($isTagged) {
                            $userMem = $memory['known_users'][$uId] ?? null;
                            $msg = $brain->generateMessage($userId, 'reply_general', array_merge($replyData, ['memory' => $userMem]));
                            executeBotAction($baseUrl . "/chat.php", ['message' => "@$pName $msg"], $cFile);
                            $isReplied = true; break;
                        }

                        // B. Question detection
                        $isQuestion = (strpos($chat['message'], '?') !== false || preg_match('/\b(ai|sao|đâu|nào)\b/i', $chat['message']) || stripos($chat['message'], 'có ai') !== false);
                        if ($isQuestion) {
                            $msg = $brain->generateMessage($userId, 'reply_question', $replyData);
                            executeBotAction($baseUrl . "/chat.php", ['message' => "@$pName $msg"], $cFile);
                            $isReplied = true; break;
                        }

                        // C. Win/Loss detection
                        $isWinMsg = (stripos($chat['message'], 'Húp') !== false || stripos($chat['message'], 'thắng') !== false || stripos($chat['message'], 'ăn ngập') !== false);
                        $isLoseMsg = (stripos($chat['message'], 'Thua') !== false || stripos($chat['message'], 'bay màu') !== false || stripos($chat['message'], 'về cõi') !== false);

                        if ($isWinMsg) {
                            $msg = $brain->generateMessage($userId, 'reaction_win', $replyData);
                            executeBotAction($baseUrl . "/chat.php", ['message' => "@$pName $msg"], $cFile);
                            $isReplied = true; break;
                        }
                        if ($isLoseMsg) {
                            $msg = $brain->generateMessage($userId, 'reaction_lose', $replyData);
                            executeBotAction($baseUrl . "/chat.php", ['message' => "@$pName $msg"], $cFile);
                            $isReplied = true; break;
                        }

                        // D. Keyword fallback
                        $keywordResponse = $brain->generateMessage($userId, 'keyword', ['text' => $chat['message']]);
                        if ($keywordResponse) {
                            executeBotAction($baseUrl . "/chat.php", ['message' => "@$pName $keywordResponse"], $cFile);
                            $isReplied = true; break;
                        }

                        // E. Normal Reply fallback
                        $msg = $brain->generateMessage($userId, 'greet', $replyData);
                        executeBotAction($baseUrl . "/chat.php", ['message' => "@$pName $msg"], $cFile);
                        $isReplied = true; break;
                    }
                }
            }

            // 1.5. Rivalry & Alliance Reactions (Merged & Optimized)
            if (!$isReplied && rand(1, 100) <= 60) {
                $syncData = file_exists($syncFile) ? json_decode(file_get_contents($syncFile), true) : [];
                $rivalStateFile = __DIR__ . '/sessions/rivalry_state.json';
                $rivalState = file_exists($rivalStateFile) ? json_decode(file_get_contents($rivalStateFile), true) : ['last_rotation' => 0, 'last_reactions' => []];
                
                // Rotate dynamic rivals every 10 min
                if (time() - $rivalState['last_rotation'] > 600) {
                    $allBotEmails = array_keys($botNameMap);
                    shuffle($allBotEmails);
                    $newRivals = [];
                    for ($i=0; $i<count($allBotEmails)-1; $i+=2) {
                        $newRivals[] = [$allBotEmails[$i], $allBotEmails[$i+1]];
                    }
                    $rivalState['current_rivals'] = $newRivals;
                    $rivalState['last_rotation'] = time();
                    file_put_contents($rivalStateFile, json_encode($rivalState));
                }

                $myRivals = []; $myAllies = [];
                // Static rivalries from config
                foreach($config['rivalries'] as $pair) {
                    if($pair[0] == $email) $myRivals[] = $pair[1];
                    if($pair[1] == $email) $myRivals[] = $pair[0];
                }
                // Dynamic rivals from state
                foreach($rivalState['current_rivals'] ?? [] as $pair) {
                    if($pair[0] == $email) $myRivals[] = $pair[1];
                    if($pair[1] == $email) $myRivals[] = $pair[0];
                }
                // Alliances
                foreach($config['alliances'] as $pair) {
                    if($pair[0] == $email) $myAllies[] = $pair[1];
                    if($pair[1] == $email) $myAllies[] = $pair[0];
                }

                foreach($syncData as $otherEmail => $data) {
                    if($otherEmail == $email) continue;
                    if(time() - $data['time'] > 300) continue; // Only react to last 5 min

                    if(in_array($otherEmail, $myRivals)) {
                        $type = ($data['result'] == 'win') ? 'rival_win' : 'rival_lose';
                        if ($type) {
                            $rMsg = $brain->getRivalryMessage($type, $data['name']);
                            executeBotAction($baseUrl . "/chat.php", ['message' => $rMsg], $cFile);
                            $isReplied = true; break;
                        }
                    }
                    if(in_array($otherEmail, $myAllies)) {
                        $type = ($data['result'] == 'win') ? 'ally_win' : null;
                        if($type) {
                            $rMsg = $brain->getRivalryMessage($type, $data['name']);
                            executeBotAction($baseUrl . "/chat.php", ['message' => $rMsg], $cFile);
                            $isReplied = true; break;
                        }
                    }
                }
            }

            // 2. Logic Mention ngẫu nhiên (nếu chưa reply ai)
            if (!$isReplied) {
                $otherBots = array_filter($botNameMap, function($otherEmail) use ($email) { 
                    return $otherEmail !== $email; 
                }, ARRAY_FILTER_USE_KEY);

                if (!empty($otherBots)) {
                    $target = $otherBots[array_rand($otherBots)];
                    $msg = "@{$target['name']} $msg";
                    if (rand(1, 100) <= 10) {
                        executeBotAction($baseUrl . "/api_friends.php", ['action' => 'send_friend_request', 'friend_id' => $target['id']], $cFile);
                    }
                }
            }
            
            // 3. Tương tác Social Feed
            $feedRes = executeBotAction($baseUrl . "/api_social_feed.php?action=get_feed", null, $cFile);
            if (isset($feedRes['data']) && !empty($feedRes['data'])) {
                $randomPost = $feedRes['data'][array_rand($feedRes['data'])];
                executeBotAction($baseUrl . "/api_social_feed.php", ['action' => 'toggle_like', 'feed_id' => $randomPost['id']], $cFile);
            }

            // 4. Chat & Greet
            if (rand(1, 100) <= 60) {
                if (rand(1, 100) <= 15) {
                    $greetMsg = $brain->generateMessage($userId, $brain->getTimeKey(), ['user_count' => $userCount], $state);
                    executeBotAction($baseUrl . "/chat.php", ['message' => $greetMsg], $cFile);
                }

                executeBotAction($baseUrl . "/chat.php", ['message' => $msg], $cFile);
                echo "💬 <span style='color:#38bdf8;'>Đã tương tác Social & Chat.</span><br>";

                // MODULE 4.1: Personality Special Chat
                if ($personality === 'whale' && rand(1, 100) <= 20) {
                    $whaleMsg = $brain->generateMessage($userId, 'high_bet', ['amount' => $bet]);
                    executeBotAction($baseUrl . "/chat.php", ['message' => $whaleMsg], $cFile);
                }

                if ($personality === 'streamer' && rand(1, 100) <= 15) {
                    $storyMsg = $brain->generateMessage($userId, 'story', []);
                    executeBotAction($baseUrl . "/chat.php", ['message' => $storyMsg], $cFile);
                }

                // Thỉnh thoảng chat về Jackpot
                if (rand(1, 100) <= 10) {
                    $jackpot = executeBotAction($baseUrl . "/api_jackpot.php?action=get_status", null, $cFile);
                    if (isset($jackpot['amount'])) {
                        $jMsg = "Hũ Rồng Thần đang có " . number_format($jackpot['amount']) . " GTLM rồi anh em ơi! 🔥";
                        executeBotAction($baseUrl . "/chat.php", ['message' => $jMsg], $cFile);
                    }
                }
            }
        }

        file_put_contents($sFile, json_encode($state));

        // --- MODULE 3.5: Chat Reactions ---
        if (rand(1, 100) <= 30) {
            $chatMessages = executeBotAction($baseUrl . "/chat.php?action=load", null, $cFile);
            if (!empty($chatMessages) && is_array($chatMessages)) {
                $recent = array_slice($chatMessages, -5);
                foreach ($recent as $chat) {
                    if ($chat['username'] !== $userName) {
                        $emojis = ['👍', '🔥', '❤️', '😂', '😮'];
                        $emoji = $emojis[array_rand($emojis)];
                        executeBotAction($baseUrl . "/chat.php?action=react&msg_id={$chat['id']}&emoji=" . urlencode($emoji), null, $cFile);
                        break;
                    }
                }
            }
        }
        
        // --- MODULE 3.6: Bot Rumors ---
        if (rand(1, 100) <= 15) {
            // Tìm người chơi thật có thắng lớn hoặc chuỗi thắng gần đây
            $rumorSql = "SELECT u.Name, gh.game_name, gh.win_amount, gh.is_win 
                         FROM game_history gh 
                         JOIN users u ON gh.user_id = u.Iduser 
                         WHERE u.Email NOT REGEXP '^bot[0-9]+@' 
                         AND gh.played_at > NOW() - INTERVAL 1 HOUR
                         ORDER BY gh.win_amount DESC LIMIT 5";
            $rumorRes = $conn->query($rumorSql);
            if ($rumorRes && $rumorRes->num_rows > 0) {
                $candidates = $rumorRes->fetch_all(MYSQLI_ASSOC);
                $c = $candidates[array_rand($candidates)];
                
                $rumorData = [
                    'player_name' => $c['Name'],
                    'game_name' => $c['game_name'],
                    'win_amount' => $c['win_amount'],
                    'streak' => rand(3, 7) // Giả định streak ngẫu nhiên nếu thắng
                ];
                
                $rMsg = $brain->generateMessage($userId, 'rumor', $rumorData);
                executeBotAction($baseUrl . "/chat.php", ['message' => $rMsg], $cFile);
            }
        }
        
        // --- MODULE 3.7: Bot Spectator ---
        if (rand(1, 100) <= 20) {
            $lives = executeBotAction($baseUrl . "/api_spectator.php?action=get_live", null, $cFile);
            if (!empty($lives['lives'])) {
                $stream = $lives['lives'][array_rand($lives['lives'])];
                $streamId = $stream['id'];
                $streamerName = $stream['streamer_name'];
                
                // 1. Send Reaction
                $emojis = ['❤️', '🔥', '👏', '🚀', '⭐'];
                executeBotAction($baseUrl . "/api_spectator.php", [
                    'action' => 'send_reaction',
                    'stream_id' => $streamId,
                    'emoji' => $emojis[array_rand($emojis)]
                ], $cFile);
                
                // 2. Send Comment
                if (rand(1, 100) <= 30) {
                    $sMsg = $brain->generateMessage($userId, 'spectator_comment', [
                        'streamer_name' => $streamerName,
                        'game_name' => $stream['game_name']
                    ]);
                    executeBotAction($baseUrl . "/api_spectator.php", [
                        'action' => 'send_chat',
                        'stream_id' => $streamId,
                        'message' => $sMsg
                    ], $cFile);
                }
            }
        }
        
        // --- MODULE 3.8: Dynamic Event Engine ---
        require_once __DIR__ . '/../dynamic_event_helper.php';
        if (rand(1, 100) <= 15) {
            $newEvent = DynamicEventHelper::autoGenerate($conn);
            if ($newEvent) {
                $eMsg = $brain->generateMessage($userId, 'dynamic_event_new', $newEvent, $state);
                executeBotAction($baseUrl . "/chat.php", ['message' => $eMsg], $cFile);
                writeBotLog($email, "INFO", "Event", "Generated new dynamic event: " . $newEvent['name']);
            } else {
                if (rand(1, 100) <= 20) {
                    $activeE = DynamicEventHelper::getActiveEvent($conn);
                    if ($activeE) {
                        $rMsg = $brain->generateMessage($userId, 'dynamic_event_remind', $activeE, $state);
                        executeBotAction($baseUrl . "/chat.php", ['message' => $rMsg], $cFile);
                    }
                }
            }
        }

        // --- MODULE 3.9: Spectator Commentary ---
        if (rand(1, 100) <= 15) {
            $liveRes = executeBotAction($baseUrl . "/api_spectator.php?action=get_live", null, $cFile);
            if (isset($liveRes['lives']) && !empty($liveRes['lives'])) {
                $randomLive = $liveRes['lives'][array_rand($liveRes['lives'])];
                $streamer = $randomLive['streamer_name'];
                $sId = $randomLive['id'];
                $comment = $brain->generateMessage($userId, 'spectator_comment', ['streamer_name' => $streamer, 'game_name' => $randomLive['game_type']], $state);
                executeBotAction($baseUrl . "/api_spectator.php", ['action' => 'send_chat', 'stream_id' => $sId, 'message' => $comment], $cFile);
                $emojis = ['❤️', '🔥', '👏', '😮', '💎'];
                executeBotAction($baseUrl . "/api_spectator.php", ['action' => 'send_reaction', 'stream_id' => $sId, 'emoji' => $emojis[array_rand($emojis)]], $cFile);
            }
        }
        
        // --- MODULE 4.1: Bracket Tournament ---
        if (rand(1, 100) <= 20) {
            $bracketTours = $conn->query("SELECT id, slots FROM tournament_brackets WHERE status = 'pending'")->fetch_all(MYSQLI_ASSOC);
            foreach ($bracketTours as $bt) {
                // Check if already joined
                $checkJoined = $conn->query("SELECT id FROM tournament_bracket_participants WHERE tournament_id = {$bt['id']} AND user_id = $userId")->num_rows;
                if (!$checkJoined) {
                    $conn->query("INSERT IGNORE INTO tournament_bracket_participants (tournament_id, user_id) VALUES ({$bt['id']}, $userId)");
                    writeBotLog($email, "INFO", "Bracket", "Joined bracket tournament #{$bt['id']}");
                    
                    // Check if full, then start
                    $participantsCount = $conn->query("SELECT id FROM tournament_bracket_participants WHERE tournament_id = {$bt['id']}")->num_rows;
                    if ($participantsCount >= $bt['slots']) {
                        require_once __DIR__ . '/../tournament_bracket_helper.php';
                        TournamentBracketHelper::startTournament($conn, $bt['id']);
                        
                        // Thông báo chat
                        $msg = "🚀 Giải đấu Bracket #{$bt['id']} đã đủ người và bắt đầu! Anh em vào xem nhánh đấu tại tournaments.php nhé!";
                        executeBotAction($baseUrl . "/chat.php", ['message' => $msg], $cFile);
                    }
                }
            }
        }
        
        // --- MODULE 4.2: Resolve Bracket Matches ---
        if (rand(1, 100) <= 10) {
            $activeMatches = $conn->query("SELECT * FROM tournament_matches WHERE status = 'pending' AND player1_id IS NOT NULL AND player2_id IS NOT NULL LIMIT 5")->fetch_all(MYSQLI_ASSOC);
            if (!empty($activeMatches)) {
                require_once __DIR__ . '/../tournament_bracket_helper.php';
                foreach ($activeMatches as $m) {
                    $winnerId = (rand(1, 100) > 50) ? $m['player1_id'] : $m['player2_id'];
                    TournamentBracketHelper::resolveMatch($conn, $m['id'], $winnerId);
                    
                    if ($m['round'] >= 2) {
                        $uRes = $conn->query("SELECT Name FROM users WHERE Iduser = $winnerId")->fetch_assoc();
                        $wName = $uRes['Name'];
                        $msg = "📢 Tin nóng: Bác @$wName đã chiến thắng và tiến vào vòng tiếp theo của Giải đấu Bracket #{$m['tournament_id']}! 🔥";
                        executeBotAction($baseUrl . "/chat.php", ['message' => $msg], $cFile);
                    }
                }
            }
        }
        
        // --- MODULE 4: Competitive Interactions ---
        if (rand(1, 100) <= 40) {
            // 1. Tournament Participation
            $tournaments = executeBotAction($baseUrl . "/api_tournament.php?action=get_list&status=active", null, $cFile);
            if (isset($tournaments['tournaments']) && !empty($tournaments['tournaments'])) {
                foreach ($tournaments['tournaments'] as $t) {
                    if (!($t['is_joined'] ?? false)) {
                        executeBotAction($baseUrl . "/api_tournament.php", ['action' => 'register', 'tournament_id' => $t['id']], $cFile);
                        writeBotLog($email, "INFO", "Tournament", "Joined tournament #{$t['id']}");
                    }
                    
                    // Nếu đang active và bot vừa chơi game, log game vào tournament
                    if ($t['status'] == 'active' && isset($chosenGame)) {
                        executeBotAction($baseUrl . "/api_tournament.php", [
                            'action' => 'log_game',
                            'tournament_id' => $t['id'],
                            'game_name' => $chosenGame,
                            'bet_amount' => $bet,
                            'win_amount' => $isWin ? $bet : 0,
                            'is_win' => $isWin ? 1 : 0
                        ], $cFile);
                    }
                }
            }
            
            // Nhận thưởng tournament đã kết thúc
            $endedTournaments = executeBotAction($baseUrl . "/api_tournament.php?action=get_list&status=ended", null, $cFile);
            if (isset($endedTournaments['tournaments'])) {
                foreach ($endedTournaments['tournaments'] as $et) {
                    if (($et['is_joined'] ?? false) && !($et['is_claimed'] ?? false)) {
                        executeBotAction($baseUrl . "/api_tournament.php", ['action' => 'claim_reward', 'tournament_id' => $et['id']], $cFile);
                    }
                }
            }

            // 2. Advanced Guild Interactions (Ecosystem Upgrades)
            // Query bot's current guild from the database directly
            $userGuildRow = $conn->query("SELECT guild_id FROM guild_members WHERE user_id = $userId")->fetch_assoc();
            $myGuildId = $userGuildRow['guild_id'] ?? 0;

            if (!$myGuildId) {
                // Not in a guild: 20% chance to join an existing guild or create one
                if (rand(1, 100) <= 20) {
                    $gRes = $conn->query("SELECT id FROM guilds ORDER BY RAND() LIMIT 1");
                    if ($gRes && $gRes->num_rows > 0) {
                        $gRow = $gRes->fetch_assoc();
                        $targetGId = $gRow['id'];
                        // Try to join
                        $joinRes = executeBotAction($baseUrl . "/api_guilds.php", ['action' => 'join', 'guild_id' => $targetGId], $cFile);
                        if (isset($joinRes['success']) && $joinRes['success']) {
                            $myGuildId = $targetGId;
                            writeBotLog($email, "INFO", "Guild Join", "Joined Guild #$targetGId");
                            uiLog('🏰', "<b>Guild:</b> Đã gia nhập Bang hội #$targetGId");
                        }
                    } else {
                        // Create a new guild if the bot is rich enough
                        if ($userMoney >= 600000 && rand(1, 100) <= 25) {
                            $gNames = ["Anh Em Lương Sơn", "Hắc Long Hội", "Vua Xanh Đỏ Đối Kháng", "Đại Gia GTLM", "Thiên Hạ Đệ Nhất", "Săn Boss VIP", "Hội Húp Lộc"];
                            $gTags = ["AELS", "HLH", "VTX", "DGG", "THDN", "SBV", "HHL"];
                            $gIdx = rand(0, count($gNames)-1);
                            $gName = $gNames[$gIdx] . " " . rand(10, 99);
                            $gTag = $gTags[$gIdx] . rand(1, 9);
                            
                            $createRes = executeBotAction($baseUrl . "/api_guilds.php", [
                                'action' => 'create',
                                'name' => $gName,
                                'tag' => $gTag,
                                'description' => "Bang hội của cao thủ Bot tự động!"
                            ], $cFile);
                            
                            if (isset($createRes['success']) && $createRes['success']) {
                                $myGuildId = $createRes['guild_id'] ?? 0;
                                writeBotLog($email, "INFO", "Guild Create", "Created Guild: $gName ($gTag)");
                                uiLog('🏰', "<b>Guild:</b> Đã thành lập Bang hội mới: $gName [$gTag]");
                            }
                        }
                    }
                }
            }

            if ($myGuildId > 0) {
                // Query my member details
                $memberRow = $conn->query("SELECT role FROM guild_members WHERE guild_id = $myGuildId AND user_id = $userId")->fetch_assoc();
                $myGuildRole = $memberRow['role'] ?? 'member';

                // A. Donate to Guild fund (Đóng góp quỹ)
                if (rand(1, 100) <= 15) {
                    $donateAmt = rand(10000, 50000);
                    if ($userMoney >= $donateAmt * 2.5) {
                        $conn->begin_transaction();
                        try {
                            $conn->query("UPDATE users SET Money = Money - $donateAmt WHERE Iduser = $userId");
                            $conn->query("UPDATE guilds SET experience = experience + $donateAmt, guild_xp = guild_xp + $donateAmt WHERE id = $myGuildId");
                            $cpEarned = floor($donateAmt / 100);
                            $conn->query("UPDATE guild_members SET contribution = contribution + $donateAmt, contribution_points = contribution_points + $cpEarned WHERE guild_id = $myGuildId AND user_id = $userId");
                            
                            $conn->commit();
                            writeBotLog($email, "INFO", "Guild Donate", "Donated " . number_format($donateAmt) . " GTLM (+$cpEarned CP) to Guild #$myGuildId");
                            uiLog('🪙', "<b>Guild:</b> Đã cống hiến " . number_format($donateAmt) . " GTLM cho quỹ bang hội!");
                            
                            // Send custom chat notification inside Guild Chat
                            $dMsg = "Lão vừa đóng góp thêm " . number_format($donateAmt) . " GTLM vào quỹ bang! Anh em cùng nhau chung tay phát triển nha! 💪🔥";
                            executeBotAction($baseUrl . "/api_guild_chat.php", ['action' => 'send', 'message' => $dMsg], $cFile);
                        } catch (Exception $e) {
                            $conn->rollback();
                        }
                    }
                }

                // B. Chat Guild
                if (rand(1, 100) <= 25) {
                    $guildChatType = (isset($isWin) && $isWin) ? 'guild_chat_hype' : 'guild_chat_sad';
                    if (rand(1, 100) <= 20) {
                        $gChatMsg = "Hế lô anh em Bang hội ta! Chúc mọi người ngày mới húp thật nhiều lộc lá nhé! ☀️🍀";
                    } else {
                        $gChatMsg = $brain->generateMessage($userId, $guildChatType);
                    }
                    if ($gChatMsg) {
                        executeBotAction($baseUrl . "/api_guild_chat.php", ['action' => 'send', 'message' => $gChatMsg], $cFile);
                        uiLog('💬', "<b>Guild Chat:</b> Đã gửi tin nhắn bang hội.");
                    }
                }

                // C. World Boss Raid Participation
                require_once __DIR__ . '/../api_guild_social_helper.php';
                $bossRow = $conn->query("SELECT id, boss_name, current_hp, max_hp FROM guild_raid_bosses WHERE guild_id = $myGuildId AND status = 'active' AND expires_at > NOW()")->fetch_assoc();
                
                if ($bossRow) {
                    $dmg = rand(15000, 120000);
                    $raidRes = GuildSocialHelper::attackRaidBoss($conn, $myGuildId, $userId, $dmg);
                    if ($raidRes['success']) {
                        uiLog('🐲', "<b>Guild Raid:</b> Đã vả Boss {$bossRow['boss_name']} mất " . number_format($dmg) . " HP!");
                        writeBotLog($email, "INFO", "Guild Raid Attack", "Dealt $dmg damage to Raid Boss #{$bossRow['id']}");
                        
                        if (rand(1, 100) <= 15) {
                            $rChat = "Lão vừa đập Boss {$bossRow['boss_name']} mất " . number_format($dmg) . " HP! Anh em vào quất boss nhanh kẻo hết giờ! ⚔️🔥";
                            executeBotAction($baseUrl . "/api_guild_chat.php", ['action' => 'send', 'message' => $rChat], $cFile);
                        }
                    }
                } else {
                    if (($myGuildRole === 'leader' || $myGuildRole === 'officer') && rand(1, 100) <= 20) {
                        $spawnRes = GuildSocialHelper::spawnRaidBoss($conn, $myGuildId);
                        if ($spawnRes) {
                            uiLog('🐲', "<b>Guild Raid:</b> Đã triệu hồi Boss mới cho Bang hội!");
                            writeBotLog($email, "INFO", "Guild Raid Spawn", "Spawned new raid boss for Guild #$myGuildId");
                            
                            $sChat = "📢 Lão vừa triệu hồi Raid Boss! Anh em chuẩn bị trang bị và vũ khí, vào diệt boss nhận quà xịn thôi nào! 🐉⚔️";
                            executeBotAction($baseUrl . "/api_guild_chat.php", ['action' => 'send', 'message' => $sChat], $cFile);
                        }
                    }
                }
            }

            // 3. PVP Challenges
            // Check incoming challenges
            $pvpChallenges = executeBotAction($baseUrl . "/api_pvp_challenge.php?action=get_my_challenges&status=pending", null, $cFile);
            if (isset($pvpChallenges['challenges'])) {
                foreach ($pvpChallenges['challenges'] as $challenge) {
                    if ($challenge['opponent_id'] == $userId) {
                        // Chấp nhận thách đấu (Bot luôn chấp nhận nếu có đủ  GTLM)
                        if ($userMoney >= $challenge['bet_amount']) {
                            executeBotAction($baseUrl . "/api_pvp_challenge.php", ['action' => 'accept_challenge', 'challenge_id' => $challenge['id']], $cFile);
                            writeBotLog($email, "INFO", "PVP", "Accepted challenge from #{$challenge['challenger_id']}");
                        }
                    }
                }
            }
            
            // Submit choice for accepted challenges
            $acceptedChallenges = executeBotAction($baseUrl . "/api_pvp_challenge.php?action=get_my_challenges&status=accepted", null, $cFile);
            if (isset($acceptedChallenges['challenges'])) {
                foreach ($acceptedChallenges['challenges'] as $ac) {
                    $choice = 'heads';
                    if ($ac['game_type'] == 'dice') $choice = rand(1, 6);
                    if ($ac['game_type'] == 'rps') $choice = ['rock', 'paper', 'scissors'][rand(0, 2)];
                    if ($ac['game_type'] == 'number') $choice = rand(1, 100);
                    
                    executeBotAction($baseUrl . "/api_pvp_challenge.php", ['action' => 'submit_choice', 'challenge_id' => $ac['id'], 'choice' => $choice], $cFile);
                }
            }
            
            // Chủ động thách đấu (Tăng cường thách đấu cả người chơi thực)
            if (rand(1, 100) <= 20) {
                // Lấy danh sách người chơi online
                $onlineUsers = $conn->query("SELECT Iduser, Name, Email FROM users WHERE last_active > NOW() - INTERVAL 10 MINUTE AND Iduser != $userId ORDER BY RAND() LIMIT 5")->fetch_all(MYSQLI_ASSOC);
                if (!empty($onlineUsers)) {
                    $target = $onlineUsers[array_rand($onlineUsers)];
                    $targetId = $target['Iduser'];
                    $isHuman = !preg_match('/^bot[0-9]+@/', $target['Email']);

                    $gameType = ['coinflip', 'dice', 'rps', 'number'][rand(0, 3)];
                    $bet = rand(10000, 50000);
                    
                    if ($userMoney >= $bet) {
                        executeBotAction($baseUrl . "/api_pvp_challenge.php", [
                            'action' => 'create_challenge',
                            'opponent_id' => $targetId,
                            'game_type' => $gameType,
                            'bet_amount' => $bet
                        ], $cFile);
                        
                        $targetType = $isHuman ? "HUMAN" : "BOT";
                        writeBotLog($email, "INFO", "PVP", "Challenged $targetType #$targetId to $gameType");
                        
                        // Trash talk nếu là người thật
                        if ($isHuman && rand(1, 100) <= 30) {
                            $taunt = ["Đánh ván không @{$target['Name']}?", "Giao lưu tý đi @{$target['Name']}!", "Sợ à @{$target['Name']}?"];
                            executeBotAction($baseUrl . "/chat.php", ['message' => $taunt[array_rand($taunt)]], $cFile);
                        }
                    }
                }
            }

            // 4. TOURNAMENT PARTICIPATION (MỚI)
            if (rand(1, 100) <= 40) { // 40% cơ hội check giải đấu mỗi chu kỳ
                $tournaments = executeBotAction($baseUrl . "/api_tournament.php?action=get_info", ['id' => 0], $cFile); 
                // Note: I'll need a way to list all tournaments, for now I'll use a direct query or update api
                
                $tourRes = $conn->query("SELECT * FROM tournaments WHERE status = 'Pending' AND current_players < max_players ORDER BY RAND() LIMIT 1");
                if ($tourRes && $tourRes->num_rows > 0) {
                    $tour = $tourRes->fetch_assoc();
                    
                    // Kiểm tra bot đã tham gia chưa
                    $checkJoined = $conn->query("SELECT id FROM tournament_participants WHERE tournament_id = {$tour['id']} AND user_id = $userId");
                    if ($checkJoined->num_rows == 0 && $userMoney >= $tour['buy_in']) {
                        $joinRes = executeBotAction($baseUrl . "/api_tournament.php", ['action' => 'join', 'tournament_id' => $tour['id']], $cFile);
                        if (isset($joinRes['status']) && $joinRes['status'] === 'success') {
                            echo "🏆 <span style='color:#ffd700; font-size:12px;'>Bot vừa tham gia giải đấu: {$tour['name']}</span><br>";
                            writeBotLog($email, "INFO", "TOURNAMENT", "Joined {$tour['name']}");
                        }
                    }
                }
            }
        }
    }

        // --- MODULE 5: Daily & Reward Systems ---
        // 1. Daily Challenges
        $dailyRes = executeBotAction($baseUrl . "/api_daily_challenges.php?action=get_list", null, $cFile);
        if (isset($dailyRes['challenges'])) {
            foreach ($dailyRes['challenges'] as $dc) {
                if (($dc['is_completed'] ?? false) && !($dc['claimed'] ?? false)) {
                    executeBotAction($baseUrl . "/api_daily_challenges.php", ['action' => 'claim', 'challenge_id' => $dc['id']], $cFile);
                }
            }
        }

        // 2. Quests
        $questRes = executeBotAction($baseUrl . "/api_quests.php?action=get_quests&type=daily", null, $cFile);
        if (isset($questRes['quests'])) {
            foreach ($questRes['quests'] as $q) {
                if (($q['is_completed'] ?? false) && !($q['is_claimed'] ?? false)) {
                    executeBotAction($baseUrl . "/api_quests.php", ['action' => 'claim_reward', 'quest_id' => $q['id'], 'quest_type' => 'daily'], $cFile);
                }
            }
        }

        // 3. Reward Points
        $rewardRes = executeBotAction($baseUrl . "/api_reward_points.php?action=get_info", null, $cFile);
        if (isset($rewardRes['points']) && $rewardRes['points']['available_points'] > 500) {
            foreach ($rewardRes['rewards'] as $rw) {
                if ($rewardRes['points']['available_points'] >= $rw['cost_points']) {
                    executeBotAction($baseUrl . "/api_reward_points.php", ['action' => 'redeem', 'reward_id' => $rw['id']], $cFile);
                    break; // Chỉ redeem 1 cái mỗi lần
                }
            }
        }
        
        // 4. Battle Pass Rewards
        $bpRes = executeBotAction($baseUrl . "/api_battle_pass.php?action=get_status", null, $cFile);
        if (isset($bpRes['levels'])) {
            foreach ($bpRes['levels'] as $lvl) {
                if ($lvl['unlocked'] && !$lvl['claimed']) {
                    executeBotAction($baseUrl . "/api_battle_pass.php", ['action' => 'claim', 'level' => $lvl['level']], $cFile);
                }
            }
        }

        // --- MODULE 6: Social & Gifting ---
        if (rand(1, 100) <= 15) {
            // Tặng GTLM cho bot khác
            $otherBots = array_keys($botNameMap);
            $targetBot = $otherBots[array_rand($otherBots)];
            if ($targetBot != $email) {
                executeBotAction($baseUrl . "/api_gift.php", [
                    'action' => 'send_money',
                    'to_user_id' => $botNameMap[$targetBot]['id'],
                    'amount' => rand(1000, 5000),
                    'message' => "Húp lộc lá cho ông bạn này!"
                ], $cFile);
            }
        }

        // 2. World Boss (Săn Boss Thế Giới)
        if (rand(1, 100) <= 50) {
            $bossStatus = executeBotAction($baseUrl . "/api_world_boss.php?action=get_status", null, $cFile);
            if (isset($bossStatus['boss']) && $bossStatus['boss']['status'] == 'alive') {
                executeBotAction($baseUrl . "/api_world_boss.php", ['action' => 'attack'], $cFile);
                echo "🐲 <span style='color:#ef4444;'>Bot vừa tham gia tấn công Boss Thế Giới!</span><br>";
                
                if (rand(1, 100) <= 10) {
                    $bMsg = "Anh em ơi, tập trung đánh Boss Hắc Long Thần nào! 🔥⚔️";
                    executeBotAction($baseUrl . "/chat.php", ['message' => $bMsg], $cFile);
                }
            }
        }

        // 3. Events
        $eventRes = executeBotAction($baseUrl . "/api_daily_challenges.php?action=get_list&status=active", null, $cFile);
        if (isset($eventRes['events'])) {
            foreach ($eventRes['events'] as $ev) {
                if (!$ev['is_joined']) {
                    executeBotAction($baseUrl . "/api_daily_challenges.php", ['action' => 'join', 'event_id' => $ev['id']], $cFile);
                }
                if (isset($ev['user_completed']) && $ev['user_completed'] && isset($ev['user_claimed']) && !$ev['user_claimed']) {
                    executeBotAction($baseUrl . "/api_daily_challenges.php", ['action' => 'claim_reward', 'event_id' => $ev['id']], $cFile);
                }
            }
        }
        
        // --- MODULE 6.1: Mood Chain & Rivalry ---
        $currentMood = $state['mood'] ?? 'happy';
        
        // 1. Mood Spreading (30% chance if excited)
        if ($currentMood === 'excited' && rand(1, 100) <= 30) {
            $otherBots = array_keys($botNameMap);
            $targetBotEmail = $otherBots[array_rand($otherBots)];
            if ($targetBotEmail != $email) {
                $targetBotName = $botNameMap[$targetBotEmail]['name'];
                $msg = "Anh em ơi, húp sướng quá! @$targetBotName quẩy cùng tôi không? 🔥";
                executeBotAction($baseUrl . "/chat.php", ['message' => $msg], $cFile);
                
                // Spread mood
                $targetMd5 = md5($targetBotEmail);
                $targetStateFile = $cookieDir . $targetMd5 . ".state.json";
                if (file_exists($targetStateFile)) {
                    $targetSt = json_decode(file_get_contents($targetStateFile), true);
                    $targetSt['mood'] = 'excited'; 
                    file_put_contents($targetStateFile, json_encode($targetSt));
                    writeBotLog($email, "SOCIAL", "Mood Spread", "Spread excitement to $targetBotName");
                }
            }
        } elseif (($currentMood === 'happy') && rand(1, 100) <= 10) {
            // General happiness spread (lower chance)
            $otherBots = array_keys($botNameMap);
            $targetBotEmail = $otherBots[array_rand($otherBots)];
            if ($targetBotEmail != $email) {
                $targetBotName = $botNameMap[$targetBotEmail]['name'];
                $targetMd5 = md5($targetBotEmail);
                $targetStateFile = $cookieDir . $targetMd5 . ".state.json";
                if (file_exists($targetStateFile)) {
                    $targetSt = json_decode(file_get_contents($targetStateFile), true);
                    $targetSt['mood'] = 'happy'; 
                    file_put_contents($targetStateFile, json_encode($targetSt));
                }
            }
        }

        // 2. Rival Memory (Aggressive bot "hates" a random bot)
        if ($personality === 'aggressive') {
            if (!isset($state['rival_id'])) {
                $otherBots = array_values($botNameMap);
                if (!empty($otherBots)) {
                    $potentialRival = $otherBots[array_rand($otherBots)];
                    if ($potentialRival['id'] != $userId) {
                        $state['rival_id'] = $potentialRival['id'];
                        $state['rival_name'] = $potentialRival['name'];
                        writeBotLog($email, "INFO", "Rivalry", "Now targeting {$state['rival_name']} as a rival!");
                    }
                }
            }
            
            if (isset($state['rival_name']) && rand(1, 100) <= 25) {
                $rivalMsgs = [
                    "Này @{$state['rival_name']}, nhìn tôi húp nè, bác còn non lắm! 😂",
                    "Thách đấu bác @{$state['rival_name']} đó, dám không?",
                    "Mỗi lần gặp bác @{$state['rival_name']} là tôi lại thấy mình đỏ. Cảm ơn nhé! 🔥",
                    "Bác @{$state['rival_name']} hôm nay bay màu bao nhiêu rồi? Để tôi húp nốt cho."
                ];
                $rivalMsg = $rivalMsgs[array_rand($rivalMsgs)];
                executeBotAction($baseUrl . "/chat.php", ['message' => $rivalMsg], $cFile);
                echo "🤺 <span style='color:#f87171; font-size:12px;'>Đã khiêu khích đối thủ: {$state['rival_name']}</span><br>";
            }
        }

        // Check leaderboard (Browsing behavior)
        if (rand(1, 100) <= 20) {
            executeBotAction($baseUrl . "/api_leaderboard.php?action=get_overall", null, $cFile);
        }

        // --- MODULE 7: Marketplace & Events ---
        // 1. Marketplace (Mua sắm & Bán hàng)
        if (rand(1, 100) <= 15) {
            // Xem chợ
            $listings = executeBotAction($baseUrl . "/api_marketplace.php?action=get_listings&limit=5", null, $cFile);
            
            // Mua hàng (Nếu bot giàu & cấp cao)
            if (isset($listings['listings']) && !empty($listings['listings']) && rand(1, 100) <= 25) {
                $item = $listings['listings'][array_rand($listings['listings'])];
                if ($item['seller_id'] != $userId && $userMoney > $item['price'] * 2) {
                    $buyRes = executeBotAction($baseUrl . "/api_marketplace.php", ['action' => 'buy', 'id' => $item['id']], $cFile);
                    if (isset($buyRes['success']) && $buyRes['success']) {
                        uiLog('🛍️', "Bot vừa chốt đơn: {$item['item_name']} giá " . number_format($item['price']));
                        $flexMsg = $brain->generateMessage($userId, 'flex_asset', ['item_name' => $item['item_name'], 'seller_name' => $item['seller_name']], $state);
                        executeBotAction($baseUrl . "/chat.php", ['message' => $flexMsg], $cFile);
                    }
                }
            }
            
            // Đăng bán hàng (Nếu bot có item dư thừa - giả lập bằng cách ngẫu nhiên lấy item sở hữu)
            if (rand(1, 100) <= 10) {
                $myItems = executeBotAction($baseUrl . "/api_marketplace.php?action=get_my_items", null, $cFile);
                if (isset($myItems['items']) && !empty($myItems['items'])) {
                    $itemToSell = $myItems['items'][array_rand($myItems['items'])];
                    executeBotAction($baseUrl . "/api_marketplace.php", [
                        'action' => 'list_item',
                        'item_id' => $itemToSell['id'],
                        'price' => rand(50000, 200000)
                    ], $cFile);
                }
            }
        }

        // --- MODULE 8: Game Statistics & Personal Monitoring ---
        if (rand(1, 100) <= 20) {
            $statsRes = executeBotAction($baseUrl . "/api_game_statistics.php?action=get_stats", null, $cFile);
            if (isset($statsRes['stats']) && $statsRes['stats']['totalGames'] > 0) {
                $s = $statsRes['stats'];
                $summary = "Tổng kết tỉ thí: Đã tỉ thí {$s['totalGames']} ván, tỉ lệ húp {$s['winRate']}%. Tổng húp {$s['totalWon']} GTLM! 🚀 #ThốngKê #DânChơi";
                executeBotAction($baseUrl . "/api_social_feed.php", ['action' => 'post', 'content' => $summary], $cFile);
            }
        }

        // --- PHASE 3.5: Market Trend Analysis (Reporter) ---
        if ($socialRole === 'reporter' && rand(1, 100) <= 20) {
            $trends = ["Xanh Đỏ Đối Kháng đang vào dây Bệt kìa anh em!", "Trận Địa Trắng Đỏ hôm nay về Lẻ nhiều quá, cẩn thận nhé!", "Hũ Jackpot game Quay hũ sắp nổ rồi, ai nhanh tay thì húp!"];
            $trendMsg = "📊 [XU HƯỚNG] " . $trends[array_rand($trends)] . " 📈";
            executeBotAction($baseUrl . "/chat.php", ['message' => $trendMsg], $cFile);
            uiLog('📈', "<b>Reporter:</b> Đã đăng tin về xu hướng thị trường.");
        }

        // --- MODULE 8.5: Weekly Goal ---
        if (date('w') == 1 && ($state['last_goal_post'] ?? '') !== $todayStr) {
            $goalMsg = $brain->generateMessage($userId, 'goal');
            executeBotAction($baseUrl . "/api_social_feed.php", ['action' => 'post', 'content' => $goalMsg], $cFile);
            $state['last_goal_post'] = $todayStr;
        }

        // --- PHASE 3: Reporter Tasks (Breaking News) ---
        if ($socialRole === 'reporter' && rand(1, 100) <= 40) {
            $bigWinRes = $conn->query("SELECT u.Name, h.win_amount, h.game_name FROM game_history h JOIN users u ON h.user_id = u.Iduser WHERE h.win_amount >= 10000000 AND h.played_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE) ORDER BY h.win_amount DESC LIMIT 1");
            if ($bigWinRes && $bigWinRes->num_rows > 0) {
                $bw = $bigWinRes->fetch_assoc();
                $news = $brain->generateMessage($userId, 'reporter_news', ['player_name' => $bw['Name'], 'amount' => $bw['win_amount'], 'game_name' => $bw['game_name']], $state);
                executeBotAction($baseUrl . "/api_social_feed.php", ['action' => 'post', 'content' => $news], $cFile);
                uiLog('📊', "<b>Reporter:</b> Đã đăng bản tin về {$bw['Name']}");
            }
        }
        
        // --- MODULE 9: Big Win Trigger ---
        if (isset($isWin) && $isWin && $winAmount >= 10000000) {
            executeBotAction($baseUrl . "/api_check_big_win.php", ['win_amount' => $winAmount], $cFile);
            writeBotLog($email, "CELEBRATION", "Big Win", "Triggered server notification for " . number_format($winAmount) . " win!");
        }

        // --- MODULE 10: Drama & Social Memory ---
        if (!empty($chatMessages) && is_array($chatMessages)) {
            foreach ($chatMessages as $msgItem) {
                $mUser = $msgItem['username'];
                // Nếu người chơi không phải bot, lưu vào trí nhớ MySQL
                $isBotParticipant = false;
                foreach($botNameMap as $b) { if($b['name'] === $mUser) { $isBotParticipant = true; break; } }
                if (!$isBotParticipant) {
                    $mNameClean = $conn->real_escape_string($mUser);
                    $conn->query("INSERT INTO bot_memory (bot_id, player_name, interaction_count, last_met) 
                                 VALUES ($userId, '$mNameClean', 1, NOW()) 
                                 ON DUPLICATE KEY UPDATE interaction_count = interaction_count + 1, last_met = NOW()");
                    
                    if (!isset($state['remembered_players'])) $state['remembered_players'] = [];
                    if (!in_array($mUser, $state['remembered_players'])) {
                        $state['remembered_players'][] = $mUser;
                        if (count($state['remembered_players']) > 10) array_shift($state['remembered_players']);
                    }
                }
            }
        }

        // Drama: Aggressive bot cãi nhau với Shy bot (Gửi PM)
        if ($personality === 'aggressive' && rand(1, 100) <= 5) {
            foreach($botNameMap as $bEmail => $bData) {
                $targetId = $bData['id'];
                $targetPersonality = $brain->getPersonality($targetId);
                if ($targetPersonality === 'shy') {
                    $dramaMsg = "Này {$bData['name']}, ra chiêu kiểu gì mà nhát thế? Có húp được gì đâu! 😂";
                    // Gửi tin nhắn riêng (Giả lập bằng chat hoặc hệ thống tin nhắn nếu có)
                    executeBotAction($baseUrl . "/chat.php", ['message' => "@{$bData['name']} $dramaMsg"], $cFile);
                    break;
                }
            }
        }

        // --- MODULE 10.5: Global Event Reactions ---
        $globalStateFile = __DIR__ . '/sessions/global_events.json';
        $globalState = file_exists($globalStateFile) ? json_decode(file_get_contents($globalStateFile), true) : ['top_1' => ''];
        
        if (rand(1, 100) <= 10) {
            $lbRes = executeBotAction($baseUrl . "/api_leaderboard.php?action=get_overall&limit=1", null, $cFile);
            if (isset($lbRes['leaderboard'][0])) {
                $currentTop1 = $lbRes['leaderboard'][0]['Name'];
                if ($currentTop1 !== $globalState['top_1'] && !empty($globalState['top_1'])) {
                    $celebrateMsg = "Kinh vcl! Chúc mừng $currentTop1 vừa leo lên Top 1 BXH nhé! 🏆";
                    executeBotAction($baseUrl . "/chat.php", ['message' => $celebrateMsg], $cFile);
                    $globalState['top_1'] = $currentTop1;
                    file_put_contents($globalStateFile, json_encode($globalState));
                } elseif (empty($globalState['top_1'])) {
                    $globalState['top_1'] = $currentTop1;
                    file_put_contents($globalStateFile, json_encode($globalState));
                }
            }
        }

        // --- MODULE 10.6: Marketplace & Market Maker (NÂNG CẤP) ---
        if (rand(1, 100) <= 25) {
            uiLog('📊', "Market Maker: Đang kiểm tra thị trường...");
            
            // 1. Quét các món đồ "rẻ" để làm nguyên liệu
            $listings = executeBotAction($baseUrl . "/api_marketplace.php?action=get_listings", null, $cFile);
            if (isset($listings['listings'])) {
                foreach ($listings['listings'] as $item) {
                    // Nếu giá < 50k, bot có xu hướng mua để tích trữ làm nguyên liệu
                    if ($item['price'] < 50000 && $item['seller_id'] != $userId && $userMoney >= $item['price']) {
                        executeBotAction($baseUrl . "/api_marketplace.php", ['action' => 'buy', 'id' => $item['id']], $cFile);
                        writeBotLog($email, "INFO", "MarketMaker", "Bought cheap material: {$item['item_name']} for {$item['price']}");
                        $userMoney -= $item['price'];
                    }
                }
            }

            // 2. Thử nghiệm Chế tác (Crafting)
            $recipesRes = $conn->query("SELECT * FROM crafting_recipes ORDER BY RAND() LIMIT 1");
            if ($recipesRes && $recipesRes->num_rows > 0) {
                $recipe = $recipesRes->fetch_assoc();
                $craftRes = executeBotAction($baseUrl . "/api_crafting.php", ['action' => 'craft', 'recipe_id' => $recipe['id']], $cFile);
                if (isset($craftRes['status']) && $craftRes['status'] === 'success') {
                    writeBotLog($email, "INFO", "MarketMaker", "Crafted successfully: {$recipe['name']}");
                    executeBotAction($baseUrl . "/api_marketplace.php", [
                        'action' => 'list_item',
                        'item_id' => $recipe['output_item_id'],
                        'item_type' => $recipe['output_type'],
                        'item_name' => "RÈN BỞI BOT: " . $recipe['name'],
                        'price' => rand(500000, 1500000)
                    ], $cFile);
                }
            }

            // 3. Jackpot Hype
            if (rand(1, 100) <= 10) {
                try {
                    $jpRes = $conn->query("SELECT amount FROM jackpots WHERE status = 'active' ORDER BY amount DESC LIMIT 1");
                    if ($jpRes && $jpRes->num_rows > 0) {
                        $jpData = $jpRes->fetch_assoc();
                        if ($jpData['amount'] > 10000000) {
                            $jpMsg = "🔥 Hũ đang căng quá anh em ơi! Hơn " . number_format($jpData['amount']) . " GTLM rồi, ai sẽ là người húp đây? 🚀";
                            executeBotAction($baseUrl . "/api_social_feed.php", ['action' => 'post', 'content' => $jpMsg], $cFile);
                        }
                    }
                } catch (Throwable $e) {
                    // Silently ignore if table doesn't exist
                }
            }
        }

        // --- MODULE 10.9: Seasonal & Event Hype ---
        if (rand(1, 100) <= 10) {
            // 1. World Boss Hype
            $bossStatus = executeBotAction($baseUrl . "/api_world_boss.php?action=get_status", null, $cFile);
            if (isset($bossStatus['boss']) && $bossStatus['boss']['status'] == 'alive') {
                $hypeMsg = "Anh em ơi, World Boss đang xuất hiện kìa! 🐲 Quất nó đê, húp quà to lắm!";
                if (rand(1, 100) <= 50) executeBotAction($baseUrl . "/chat.php", ['message' => $hypeMsg], $cFile);
            }
            

        }

        // --- MODULE 10.9.1: Bot Baiting (Gà Mồi) ---
        if ($personality === 'whale' || $userMoney >= 500000) {
            $userAvatar = $res['ImageURL'] ?? 'https://ui-avatars.com/api/?name='.urlencode($userName);
            $baitingRes = handleBaitingBot($conn, $baseUrl, $cFile, $userMoney, $userId, $userName, $userAvatar);
            if ($baitingRes) {
                foreach ($baitingRes['actions'] as $act) {
                    uiLog('🐔', "<b>Gà Mồi:</b> $act", 'color:#eab308;');
                    writeBotLog($email, "BAITING", "Action", $act);
                }
            }
        }

        // --- MODULE 10.9.2: Bot Vendetta (Thù Dai) ---
        if ($userMoney >= 100000) {
            $userAvatar = $res['ImageURL'] ?? 'https://ui-avatars.com/api/?name='.urlencode($userName);
            $vendettaRes = handleVendettaBot($conn, $baseUrl, $cFile, $userMoney, $userId, $userName, $userAvatar);
            if ($vendettaRes) {
                foreach ($vendettaRes['actions'] as $act) {
                    uiLog('🩸', "<b>Thù Dai:</b> $act", 'color:#ef4444; font-weight:bold;');
                    writeBotLog($email, "VENDETTA", "Action", $act);
                }
            }
        }

        // --- MODULE 10.7: Tournament Participation ---
        if (rand(1, 100) <= 30) {
            $toursRes = executeBotAction($baseUrl . "/api_tournament.php", ['action' => 'get_active_list'], $cFile);
            if (isset($toursRes['tournaments'])) {
                foreach ($toursRes['tournaments'] as $tour) {
                    if ($tour['status'] === 'Pending' && !$tour['is_joined'] && $tour['registered_players'] < $tour['max_players']) {
                        if ($userMoney >= $tour['buy_in']) {
                            executeBotAction($baseUrl . "/api_tournament.php", ['action' => 'join', 'tournament_id' => $tour['id']], $cFile);
                            uiLog('🏆', "Bot đăng ký giải đấu: {$tour['name']}");
                        }
                    } elseif ($tour['status'] === 'Ongoing' && $tour['is_joined']) {
                        $gType = strtolower($tour['game_type']);
                        $betAmount = rand(1000, 50000);
                        if ($userMoney >= $betAmount) {
                            executeBotAction($baseUrl . "/api_".str_replace(' ', '', $gType).".php", ['action' => 'play', 'amount' => $betAmount], $cFile);
                            uiLog('🎯', "Bot đang thi đấu: {$tour['name']}");
                        }
                    }
                }
            }
        }

        // --- EVOLUTION: Logic Lên Cấp ---
        $xpToLevel = $state['level'] * 100;
        if ($state['xp'] >= $xpToLevel) {
            $state['level']++;
            $state['xp'] -= $xpToLevel;
            uiLog('🌟', "<b>LÊN CẤP:</b> Chúc mừng Bot đã đạt Level {$state['level']}!", 'color:#f59e0b; font-weight:bold;');
            if ($state['level'] % 5 == 0) {
                $levelMsg = $brain->generateMessage($userId, 'level_up', ['level' => $state['level']], $state);
                executeBotAction($baseUrl . "/chat.php", ['message' => $levelMsg], $cFile);
            }
        }

        file_put_contents($sFile, json_encode($state));
        file_put_contents($memFile, json_encode($memory));
        
        // --- MODULE 11: Cleanup & Logout ---
        executeBotAction($baseUrl . "/api_logout.php", null, $cFile);
        echo "</div></div>"; // Close bot-log and bot-card
        if (ob_get_level() > 0) ob_flush();
        flush();
        sleep(1); 
    } catch (Throwable $e) {
        uiLog('⚠️', "Error: " . $e->getMessage(), 'color:#ff3333; font-size:0.8rem;');
        echo "</div></div>";
        writeBotLog($email, "CRITICAL", "Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        flush();
    }
}


    // --- MODULE 12: Mega Spin Participation (Global) ---
    if (rand(1, 100) <= 30) {
        $genericCFile = $cookieDir . "generic_system.txt";
        $msStatus = executeBotAction($baseUrl . "/api_megaspin.php?action=get_status", null, $genericCFile);
        if (isset($msStatus['success']) && $msStatus['success']) {
            $randomBots = array_rand($botNameMap, min(5, count($botNameMap)));
            if (!is_array($randomBots)) $randomBots = [$randomBots];
            foreach ($randomBots as $bEmail) {
                if (rand(1, 100) <= 40) {
                    $bData = $botNameMap[$bEmail];
                    $bCFile = __DIR__ . '/sessions/' . md5($bEmail) . '.cookie';
                    $loginRes = executeBotAction($baseUrl . "/login.php", ['email' => $bEmail, 'password' => $config['bot_password']], $bCFile);
                    if (isset($loginRes['status']) && $loginRes['status'] === 'success') {
                        executeBotAction($baseUrl . "/api_megaspin.php", ['action' => 'join', 'amount' => 10000], $bCFile);
                        uiLog('🎰', "<b>{$bData['name']}</b> đã tham gia Mega Spin.");
                    }
                }
            }
        }
    }

    recordEconomySnapshot($conn);

    if (isset($updateMoneyStmt)) $updateMoneyStmt->close();
    uiLog('✅', "Chu kỳ hoàn tất [" . date('H:i:s') . "]");
    echo "</div>"; // Close container
}

// KHỞI CHẠY ENGINE
echo "<!-- ENGINE_START_CALL -->";
executeBotCycle($conn, $config, $cookieDir, $baseUrl, $brain, $botNameMap, $availableGames);
