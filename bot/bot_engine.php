<?php
/**
 * 🛡️ Ultimate Bot Engine v8.0 - Full Feature Integration
 * Autonomous, Emotional, and Socially Active
 */
 
// 0. Logging Helper
function writeBotLog(string $email, string $action, string $details = "") {
    $logDir = __DIR__ . '/logs/';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $file = $logDir . date('Y-m-d') . '.log';
    $timestamp = date('H:i:s');
    $logLine = "[$timestamp] [$email] $action" . ($details ? ": $details" : "") . PHP_EOL;
    @file_put_contents($file, $logLine, FILE_APPEND);
}

// 1. Load configuration
$config = require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../db_connect.php';

// 2. Settings
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); 
set_time_limit($config['settings']['timeout']);
$isCli = (php_sapi_name() === 'cli');
$isLocal = $isCli || (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || $_SERVER['HTTP_HOST'] === '127.0.0.1'));

$baseUrl = $isCli ? "http://localhost/1" : (($isLocal ? "http" : "https") . "://$_SERVER[HTTP_HOST]");
if (strpos($baseUrl, '/1') === false && !$isCli) {
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/1/') !== false) $baseUrl .= "/1";
}

$cookieDir = __DIR__ . '/sessions/';
if (!is_dir($cookieDir)) mkdir($cookieDir, 0700, true);

// ── CORE REQUEST FUNCTION ──
function executeBotAction(string $url, ?array $postData = null, string $cookieFile, bool $isLocal) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (BotArmy/8.0; FullFeature)');
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$isLocal);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $isLocal ? 0 : 2);
    if ($postData) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $response];
}

// ── HELPER: Send Chat ──
function sendBotChat(string $baseUrl, string $cFile, string $sFile, array &$state, string $message, bool $isLocal) {
    if (time() - ($state['last_chat_time'] ?? 0) < 15) return false;
    $recent = $state['recent_messages'] ?? [];
    if (in_array($message, $recent)) return false;

    $res = executeBotAction($baseUrl . "/chat.php", ['message' => $message], $cFile, $isLocal);
    if ($res['code'] == 200) {
        $state['last_chat_time'] = time();
        $state['recent_messages'][] = $message;
        if (count($state['recent_messages']) > 5) array_shift($state['recent_messages']);
        return true;
    }
    return false;
}

// ── SMART BUDGETING ──
function getLiquidBalance(float $money, array &$state) {
    $savings = $state['savings'] ?? 0;
    // Keep 40% of wealth in "savings" (mental lock)
    if ($money > $savings) {
        $state['savings'] = $money * 0.4;
    }
    return $money - ($state['savings'] ?? 0);
}

// ── FEATURE MODULES ──

function interactWithMysteryBox(string $baseUrl, string $cFile, float $liquidBalance) {
    if ($liquidBalance > 100000 && rand(1, 100) > 85) {
        executeBotAction($baseUrl . "/hopmu.php", ['open' => '1'], $cFile, true); // Use POST to trigger
        echo "- 🎁 Opened a Mystery Box (50k cost)<br>";
    }
}

function interactWithLuckyDraw(string $baseUrl, string $cFile, float $liquidBalance) {
    if ($liquidBalance > 100000 && rand(1, 100) > 85) {
        executeBotAction($baseUrl . "/ruttham.php", ['bag_index' => rand(0, 5)], $cFile, true);
        echo "- 🎟️ Participated in Lucky Draw<br>";
    }
}

function interactWithScratchCards(string $baseUrl, string $cFile) {
    if (rand(1, 100) > 70) {
        executeBotAction($baseUrl . "/caothe.php", null, $cFile, true);
        echo "- 🃏 Scratched cards for daily reward<br>";
    }
}

function interactWithTitles(string $baseUrl, string $cFile, int $userId, mysqli $conn) {
    if (rand(1, 100) > 90) {
        // Find best title (lowest requirement_value is higher rank)
        $sql = "SELECT a.id FROM achievements a INNER JOIN user_achievements ua ON a.id = ua.achievement_id WHERE ua.user_id = ? AND a.requirement_type = 'rank' ORDER BY a.requirement_value ASC LIMIT 1";
        $stmt = $conn->prepare($sql); $stmt->bind_param("i", $userId); $stmt->execute();
        if ($res = $stmt->get_result()->fetch_assoc()) {
            executeBotAction($baseUrl . "/select_title.php", ['select_title' => '1', 'title_id' => $res['id']], $cFile, true);
            echo "- 🏷️ Updated display title to ID #{$res['id']}<br>";
        }
        $stmt->close();
    }
}

function interactWithGifting(string $baseUrl, string $cFile, int $userId, float $money, mysqli $conn) {
    if ($money > 2000000 && rand(1, 100) > 95) {
        $sql = "SELECT Iduser, Name FROM users WHERE Iduser != ? AND Money < 500000 ORDER BY RAND() LIMIT 1";
        $stmt = $conn->prepare($sql); $stmt->bind_param("i", $userId); $stmt->execute();
        if ($row = $stmt->get_result()->fetch_assoc()) {
            $gift = rand(50000, 200000);
            executeBotAction($baseUrl . "/api_gift.php", [
                'action' => 'send_money', 'to_user_id' => $row['Iduser'], 'amount' => $gift, 'message' => "Lộc phát cho người anh em nhé! 🍀"
            ], $cFile, true);
            echo "- 💸 Gifted $gift GTLM to {$row['Name']}<br>";
        }
        $stmt->close();
    }
}

function handleNotifications(string $baseUrl, string $cFile) {
    $res = executeBotAction($baseUrl . "/api_notifications.php?action=get_list&unread_only=1", null, $cFile, true);
    $data = json_decode($res['body'] ?? '', true);
    if (isset($data['notifications']) && !empty($data['notifications'])) {
        foreach ($data['notifications'] as $n) {
            executeBotAction($baseUrl . "/api_notifications.php", ['action' => 'mark_read', 'notification_id' => $n['id']], $cFile, true);
        }
        echo "- 🔔 Read " . count($data['notifications']) . " notifications<br>";
    }
}

// ── REUSE PREVIOUS BRAIN LOGIC ──
require_once __DIR__ . '/bot_brain.php';
$brain = new BotBrain();

// ── MAIN LOOP ──
echo "<h1>🛡️ Bot Army Engine v8.0 (Maximum Interaction)</h1>";

$allBots = $config['bot_emails'];
shuffle($allBots);
$selectedEmails = array_slice($allBots, 0, $config['settings']['max_bots_per_cycle']);

foreach ($selectedEmails as $email) {
    $botMd5 = md5($email);
    $cFile = $cookieDir . $botMd5 . ".txt";
    $sFile = $cookieDir . $botMd5 . ".state.json"; 
    
    $defaultState = [
        'last_chat_time' => 0, 'stats' => ['wins' => 0, 'losses' => 0, 'win_streak' => 0, 'lose_streak' => 0],
        'idols' => [], 'mood' => 'happy', 'recent_messages' => [], 'savings' => 0
    ];
    $state = file_exists($sFile) ? array_merge($defaultState, json_decode(file_get_contents($sFile), true)) : $defaultState;

    $winStreak = $state['stats']['win_streak'] ?? 0;
    $loseStreak = $state['stats']['lose_streak'] ?? 0;
    if ($winStreak >= 3) $state['mood'] = 'excited';
    elseif ($loseStreak >= 5) $state['mood'] = 'depressed';
    elseif ($loseStreak >= 3) $state['mood'] = 'tilted';
    else $state['mood'] = 'happy';

    $hour = (int)date('H');
    $activityChance = ($hour < 7) ? 20 : (($hour < 9) ? 60 : 100);
    if (strpos($email, 'bot01') === false && rand(1, 100) > $activityChance) continue;

    $res = executeBotAction($baseUrl . "/login.php", ['email' => $email, 'password' => $config['bot_password']], $cFile, $isLocal);
    $loginData = json_decode($res['body'] ?? '', true);
    
    if ($res['code'] == 200 && isset($loginData['status']) && $loginData['status'] == 'success') {
        $userId = (int)$loginData['Iduser'];
        $userName = $loginData['Name'];
        $userMoney = (float)$loginData['Money'];
        $liquidBalance = getLiquidBalance($userMoney, $state);
        $personality = $brain->getPersonality($userId);
        
        echo "<h3>🤖 Bot: $userName ($personality, {$state['mood']}, Balance: " . number_format($userMoney) . ")</h3>";

        // 1. Daily & Free Rewards
        executeBotAction($baseUrl . "/api_daily_login.php?action=claim", null, $cFile, true);
        interactWithScratchCards($baseUrl, $cFile);
        handleNotifications($baseUrl, $cFile);

        // 2. Spending Liquid Balance (Fun & Risk)
        interactWithMysteryBox($baseUrl, $cFile, $liquidBalance);
        interactWithLuckyDraw($baseUrl, $cFile, $liquidBalance);
        if (rand(1, 100) > 80) executeBotAction($baseUrl . "/api_lucky_wheel.php?action=spin", null, $cFile, true);
        
        // 3. Social & Identity
        interactWithTitles($baseUrl, $cFile, $userId, $conn);
        interactWithGifting($baseUrl, $cFile, $userId, $userMoney, $conn);
        
        // 4. Missions & Trivia
        $missionRes = executeBotAction($baseUrl . "/api_daily_missions.php?action=get_missions", null, $cFile, true);
        $mD = json_decode($missionRes['body'] ?? '', true);
        if (isset($mD['missions'])) {
            foreach ($mD['missions'] as $m) if ($m['status'] === 'completed' && !$m['is_claimed']) executeBotAction($baseUrl . "/api_daily_missions.php", ['action' => 'claim_reward', 'mission_id' => $m['id']], $cFile, true);
        }
        if (rand(1, 100) < 15) {
            $tRes = executeBotAction($baseUrl . "/api_trivia.php?action=get_question", null, $cFile, true);
            $tD = json_decode($tRes['body'] ?? '', true);
            if (isset($tD['success'])) executeBotAction($baseUrl . "/api_trivia.php", ['action' => 'submit_answer', 'question_id' => $tD['id'], 'answer' => $tD['correct_answer']], $cFile, true);
        }

        // 5. Game Play (Restricted by liquid balance)
        if ($liquidBalance > 1000 && rand(1, 100) > 25) {
            $gameFiles = glob(__DIR__ . '/../games/*.php');
            $gameKey = str_replace('.php', '', basename($gameFiles[array_rand($gameFiles)]));
            if (!in_array($gameKey, ['check_php', 'blackjack_process', 'baccarat_process'])) {
                // Determine bet based on liquid balance
                $percent = ($state['mood'] == 'tilted') ? 0.1 : 0.05;
                $bet = max(500, min($liquidBalance * $percent, 1000000));
                
                $outcome = (rand(1, 100) <= 46) ? 1 : 2; // 46% win rate
                if ($outcome == 1) {
                    $win = $bet * rand(2, 4);
                    $conn->query("UPDATE users SET Money = Money + $win WHERE Iduser = $userId");
                    $state['stats']['wins']++; $state['stats']['win_streak']++; $state['stats']['lose_streak'] = 0;
                    $msg = $brain->generateMessage($userId, 'win', ['amount' => number_format($win), 'game' => ucfirst($gameKey)], $state['mood']);
                } else {
                    $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");
                    $state['stats']['losses']++; $state['stats']['win_streak'] = 0; $state['stats']['lose_streak']++;
                    $msg = $brain->generateMessage($userId, 'lose', ['amount' => number_format($bet), 'game' => ucfirst($gameKey)], $state['mood']);
                }
                if ($msg) sendBotChat($baseUrl, $cFile, $sFile, $state, $msg, $isLocal);
            }
        }

        // 6. Logout & Save
        executeBotAction($baseUrl . "/logout.php", null, $cFile, $isLocal);
        file_put_contents($sFile, json_encode($state), LOCK_EX);
    }
}
echo "<hr>✅ Bot Engine v8.0 Cycle Finished.";
