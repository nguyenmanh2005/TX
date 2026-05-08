<?php
/**
 * 🛡️ Omni-Bot Engine v16.1 - Total Web Access
 * Coverage: Auth, Games, Daily Maintenance, Quests, Social Feed (Like/Comment), Friends, Notifications
 */

// 0. Helpers
function writeBotLog(string $email, string $level, string $action, string $details = "") {
    $logDir = __DIR__ . '/logs/';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $file = $logDir . date('Y-m-d') . '.log';
    $timestamp = date('H:i:s');
    $logLine = "[$timestamp] [$level] [$email] $action" . ($details ? ": $details" : "") . PHP_EOL;
    @file_put_contents($file, $logLine, FILE_APPEND);
}

function recordEconomySnapshot(mysqli $conn) {
    $historyFile = __DIR__ . '/sessions/economy_history.json';
    $history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
    $botRes = $conn->query("SELECT SUM(Money) as total FROM users WHERE Email LIKE '%bot%'")->fetch_assoc();
    $totalBot = (float)($botRes['total'] ?? 0);
    $humanRes = $conn->query("SELECT SUM(Money) as total FROM users WHERE Email NOT LIKE '%bot%'")->fetch_assoc();
    $totalHuman = (float)($humanRes['total'] ?? 0);
    $history[] = ['time' => date('H:i d/m'), 'bot' => $totalBot, 'human' => $totalHuman];
    if (count($history) > 50) array_shift($history);
    file_put_contents($historyFile, json_encode($history));
}

// 1. Load config & brain
require_once __DIR__ . '/../db_connect.php'; 
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/bot_brain.php';
$brain = new BotBrain();

$baseUrl = "http://localhost/1";
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
$nameRes = $conn->query("SELECT Iduser, Name, Email FROM users WHERE Email LIKE '%bot%'");
while($row = $nameRes->fetch_assoc()) {
    $botNameMap[$row['Email']] = ['id' => $row['Iduser'], 'name' => $row['Name']];
}

function executeBotAction(string $url, ?array $postData = null, string $cookieFile) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (BotArmy/16.1; OmniAccess)');
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if ($postData) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response ?? '', true);
}

// ── MAIN LOOP ──
echo "<body style='background:#020617; color:#f8fafc; font-family:sans-serif; padding:20px;'>";
echo "<h1 style='color:#818cf8;'>🛡️ Bot Army Engine v16.1 (Omni-Access)</h1>";

$allBots = $config['bot_emails'];
shuffle($allBots);
$activeBots = array_slice($allBots, 0, $config['settings']['max_bots_per_cycle']);

$updateMoneyStmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");

foreach ($activeBots as $email) {
    $botMd5 = md5($email);
    $cFile = $cookieDir . $botMd5 . ".txt";
    $sFile = $cookieDir . $botMd5 . ".state.json";
    
    $state = file_exists($sFile) ? json_decode(file_get_contents($sFile), true) : ['wins'=>0, 'recent_messages' => [], 'last_maintenance' => ''];

    $res = executeBotAction($baseUrl . "/login.php", ['email' => $email, 'password' => $config['bot_password']], $cFile);
    
    if (isset($res['status']) && $res['status'] == 'success') {
        $userId = (int)$res['Iduser'];
        $userName = $res['Name'];
        $userMoney = (float)$res['Money'];
        
        echo "<div style='background:rgba(30, 41, 59, 0.7); padding:15px; border-radius:12px; margin-bottom:12px; border:1px solid rgba(255,255,255,0.1);'>";
        echo "<b style='color:#38bdf8;'>🤖 Bot: $userName</b> | <span style='color:#94a3b8; font-size:11px;'>$email</span><br>";

        // --- MODULE 1: Maintenance & Tasks ---
        $todayStr = date('Y-m-d');
        if (($state['last_maintenance'] ?? '') !== $todayStr) {
            echo "🔧 <span style='color:#fbbf24; font-size:13px;'>Bảo trì: Daily Tasks, Quests, Notifications...</span><br>";
            executeBotAction($baseUrl . "/api_daily_login.php", ['action' => 'claim_reward'], $cFile);
            executeBotAction($baseUrl . "/api_lucky_wheel.php", ['action' => 'spin'], $cFile);
            
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
                foreach($missionRes['missions'] as $m) {
                    if ($m['is_completed'] && !$m['is_claimed']) {
                        executeBotAction($baseUrl . "/api_daily_missions.php", ['action' => 'claim_reward', 'mission_id' => $m['id']], $cFile);
                    }
                }
            }
            // Dọn dẹp thông báo
            executeBotAction($baseUrl . "/api_notifications.php", ['action' => 'mark_all_read'], $cFile);
            
            $state['last_maintenance'] = $todayStr;
        }

        // --- MODULE 2: Games ---
        $chosenGame = $availableGames[array_rand($availableGames)];
        $personality = $brain->getPersonality($userId);
        $bet = floor($userMoney * (($personality == 'aggressive' ? rand(5, 12) : rand(1, 5)) / 100));
        if ($bet < 1000) $bet = 1000;

        $isWin = (rand(0, 1) === 1);
        if ($isWin) {
            $updateMoneyStmt->bind_param("di", $bet, $userId);
            $updateMoneyStmt->execute();
            $state['wins']++;
            echo "💰 <span style='color:#4ade80;'>Thắng " . number_format($bet) . " tại $chosenGame</span><br>";
            $msg = $brain->generateMessage($userId, 'win', ['amount' => $bet]);
        } else {
            $negativeBet = -$bet;
            $updateMoneyStmt->bind_param("di", $negativeBet, $userId);
            $updateMoneyStmt->execute();
            echo "💸 <span style='color:#f87171;'>Thua " . number_format($bet) . " tại $chosenGame</span><br>";
            $msg = $brain->generateMessage($userId, 'lose', ['amount' => $bet]);
        }

        // --- MODULE 3: Social & Interaction ---
        if (rand(1, 100) <= 75) {
            $chatMessages = executeBotAction($baseUrl . "/chat.php?action=load", null, $cFile);
            $isReplied = false;

            // 1. Logic Phản hồi (Reply)
            if (!empty($chatMessages) && is_array($chatMessages) && rand(1, 100) <= 40) {
                $recent = array_slice($chatMessages, -10); // Lấy 10 tin mới nhất
                foreach ($recent as $chat) {
                    if ($chat['username'] !== $userName) { // Không trả lời chính mình
                        $msg = "@{$chat['username']} " . $msg;
                        $isReplied = true;
                        break;
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
            
            // 3. Tương tác Social Feed (Like/Comment dạo)
            $feedRes = executeBotAction($baseUrl . "/api_social_feed.php?action=get_feed", null, $cFile);
            if (isset($feedRes['data']) && !empty($feedRes['data'])) {
                $randomPost = $feedRes['data'][array_rand($feedRes['data'])];
                executeBotAction($baseUrl . "/api_social_feed.php", ['action' => 'toggle_like', 'feed_id' => $randomPost['id']], $cFile);
                if (rand(1, 100) <= 20) {
                    executeBotAction($baseUrl . "/api_social_feed.php", ['action' => 'add_comment', 'feed_id' => $randomPost['id'], 'comment_text' => 'Bác này đỉnh thế!'], $cFile);
                }
            }

            // 4. Đăng Feed (Chỉ đăng khi thắng) & Chat
            if ($isWin) {
                executeBotAction($baseUrl . "/api_social_feed.php", ['action' => 'create_post', 'content' => $msg], $cFile);
            }

            if (rand(1, 100) <= 60) {
                executeBotAction($baseUrl . "/chat.php", ['message' => $msg], $cFile);
                echo "💬 <span style='color:#38bdf8;'>Đã " . ($isReplied ? "phản hồi" : "tương tác") . " Social & Chat.</span><br>";
                if (!isset($state['recent_messages']) || !is_array($state['recent_messages'])) $state['recent_messages'] = [];
                array_unshift($state['recent_messages'], $msg);
                $state['recent_messages'] = array_slice($state['recent_messages'], 0, 5);
            }
        }

        file_put_contents($sFile, json_encode($state));
        echo "</div>";
    } else {
        writeBotLog($email, "ERROR", "Login Failed", $res['message'] ?? 'Unknown error');
    }
}

$updateMoneyStmt->close();
recordEconomySnapshot($conn);
echo "<hr>✨ Cycle Finished (Omni-Access v16.1).";
