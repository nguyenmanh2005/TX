<?php
/**
 * 🛡️ Ultimate Bot Engine v9.0 - Maximum Engagement
 * Features: Marketplace, PvP Challenges, Social Posting, Reward Redemption, Profile Visits
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
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (BotArmy/9.0; MaximumEngagement)');
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

// ── HELPER: Earn Reward Points ──
function earnRewardPoints(mysqli $conn, int $userId, int $points, string $desc) {
    // Ensure table exists
    $conn->query("INSERT INTO reward_points (user_id, available_points, total_points) VALUES ($userId, $points, $points) ON DUPLICATE KEY UPDATE available_points = available_points + $points, total_points = total_points + $points");
    $stmt = $conn->prepare("INSERT INTO reward_point_transactions (user_id, points, transaction_type, description) VALUES (?, ?, 'earn', ?)");
    $stmt->bind_param("iis", $userId, $points, $desc);
    $stmt->execute();
    $stmt->close();
}

// ── V9.0 MODULES ──

function interactWithSocialPosting(string $baseUrl, string $cFile, string $userName, string $mood, float $money) {
    if (rand(1, 100) > 90) { // 10% chance to post something
        $posts = [
            'happy' => ["Hôm nay đẹp trời quá, vào làm vài ván Coinflip thôi! 🍀", "Server dạo này sôi động thật, yêu anh em! ❤️", "Vừa sắm bộ đồ mới, mọi người thấy sao? 😎"],
            'excited' => ["THẮNG LỚN RỒI ANH EM ƠI!!! 💸💸💸", "Vận may đang tới, không gì cản nổi! 🔥", "Ai muốn nhận lộc không? Comment bên dưới nhé! 👇"],
            'tilted' => ["Cái bàn này bị ám rồi à? Sao thua mãi thế! 😡", "Đen thôi đỏ quên đi... mai làm lại!", "Bực mình thật sự, tí nữa vào gỡ lại gấp đôi! ⚡"],
            'depressed' => ["Hết tiền rồi... ai cứu tui với 😭", "Có ai rủ đi chơi game gì vui vui không?", "Hôm nay không phải ngày của mình... 🌧️"]
        ];
        $content = $posts[$mood][array_rand($posts[$mood])];
        executeBotAction($baseUrl . "/api_social_feed.php", ['action' => 'create_post', 'content' => $content], $cFile, true);
        echo "- 📝 Posted on Social Feed: \"$content\"<br>";
    }
}

function interactWithMarketplace(string $baseUrl, string $cFile, float $liquidBalance) {
    // Buy logic: 5% chance to buy if wealthy
    if ($liquidBalance > 500000 && rand(1, 100) > 95) {
        $res = executeBotAction($baseUrl . "/api_marketplace.php?action=get_listings&limit=5", null, $cFile, true);
        $data = json_decode($res['body'] ?? '', true);
        if (isset($data['listings'][0])) {
            $item = $data['listings'][rand(0, count($data['listings'])-1)];
            if ($item['price'] < $liquidBalance * 0.2) {
                executeBotAction($baseUrl . "/api_marketplace.php", ['action' => 'buy_item', 'listing_id' => $item['id']], $cFile, true);
                echo "- 🛒 Bought item \"{$item['item_name']}\" from Marketplace for " . number_format($item['price']) . "<br>";
            }
        }
    }
}

function interactWithPvPChallenges(string $baseUrl, string $cFile, int $userId, float $liquidBalance, mysqli $conn) {
    if ($liquidBalance > 50000 && rand(1, 100) > 92) {
        // Pick a target (human or bot)
        $sql = "SELECT Iduser, Name FROM users WHERE Iduser != ? AND Money > 50000 ORDER BY RAND() LIMIT 1";
        $stmt = $conn->prepare($sql); $stmt->bind_param("i", $userId); $stmt->execute();
        if ($target = $stmt->get_result()->fetch_assoc()) {
            $gameTypes = ['coinflip', 'dice', 'rps'];
            $bet = rand(10000, 50000);
            $res = executeBotAction($baseUrl . "/api_pvp_challenge.php", [
                'action' => 'create_challenge', 'opponent_id' => $target['Iduser'], 'game_type' => $gameTypes[array_rand($gameTypes)], 'bet_amount' => $bet
            ], $cFile, true);
            echo "- ⚔️ Sent PvP Challenge to {$target['Name']} ($bet GTLM)<br>";
        }
        $stmt->close();
    }
    // Also check for pending challenges to accept
    $res = executeBotAction($baseUrl . "/api_pvp_challenge.php?action=get_my_challenges&status=pending", null, $cFile, true);
    $data = json_decode($res['body'] ?? '', true);
    if (isset($data['challenges'][0])) {
        $c = $data['challenges'][0];
        if ($c['opponent_id'] == $userId) {
            executeBotAction($baseUrl . "/api_pvp_challenge.php", ['action' => 'accept_challenge', 'challenge_id' => $c['id']], $cFile, true);
            // Submit choice immediately
            $choices = ['coinflip' => ['heads', 'tails'], 'dice' => ['1','2','3','4','5','6'], 'rps' => ['rock','paper','scissors']];
            $myChoice = $choices[$c['game_type']][array_rand($choices[$c['game_type']])];
            executeBotAction($baseUrl . "/api_pvp_challenge.php", ['action' => 'submit_choice', 'challenge_id' => $c['id'], 'choice' => $myChoice], $cFile, true);
            echo "- 🛡️ Accepted & Played PvP Challenge ID #{$c['id']}<br>";
        }
    }
}

function interactWithRewardRedemption(string $baseUrl, string $cFile, int $userId, mysqli $conn) {
    if (rand(1, 100) > 85) {
        $sql = "SELECT available_points FROM reward_points WHERE user_id = ?";
        $stmt = $conn->prepare($sql); $stmt->bind_param("i", $userId); $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $pts = $res['available_points'] ?? 0;
        $stmt->close();

        if ($pts >= 1000) {
            // Find affordable reward
            $sql = "SELECT id FROM reward_point_rewards WHERE cost_points <= ? AND is_active = 1 ORDER BY cost_points DESC LIMIT 1";
            $stmt = $conn->prepare($sql); $stmt->bind_param("i", $pts); $stmt->execute();
            if ($reward = $stmt->get_result()->fetch_assoc()) {
                executeBotAction($baseUrl . "/api_reward_points.php", ['action' => 'redeem', 'reward_id' => $reward['id']], $cFile, true);
                echo "- 🏆 Redeemed Reward Points for Item #{$reward['id']}<br>";
            }
            $stmt->close();
        }
    }
}

function interactWithProfileVisits(string $baseUrl, string $cFile, int $userId, mysqli $conn) {
    if (rand(1, 100) > 80) {
        $sql = "SELECT Iduser FROM users WHERE Iduser != ? ORDER BY Money DESC LIMIT 10"; // Visit top players
        $res = $conn->query($sql);
        while ($row = $res->fetch_assoc()) {
            if (rand(1, 10) > 7) {
                executeBotAction($baseUrl . "/api_profile.php?action=get_profile&user_id=" . $row['Iduser'], null, $cFile, true);
                echo "- 👁️ Visited Profile of User #{$row['Iduser']}<br>";
                break;
            }
        }
    }
}

// ── REUSE BRAIN ──
require_once __DIR__ . '/bot_brain.php';
$brain = new BotBrain();

// ── MAIN LOOP ──
echo "<h1>🛡️ Bot Army Engine v9.0 (Maximum Engagement)</h1>";

$allBots = $config['bot_emails'];
shuffle($allBots);
$selectedEmails = array_slice($allBots, 0, $config['settings']['max_bots_per_cycle']);

foreach ($selectedEmails as $email) {
    $botMd5 = md5($email);
    $cFile = $cookieDir . $botMd5 . ".txt";
    $sFile = $cookieDir . $botMd5 . ".state.json"; 
    $state = file_exists($sFile) ? json_decode(file_get_contents($sFile), true) : ['stats' => ['wins'=>0,'losses'=>0,'win_streak'=>0,'lose_streak'=>0], 'mood'=>'happy', 'savings'=>0];

    // Login
    $res = executeBotAction($baseUrl . "/login.php", ['email' => $email, 'password' => $config['bot_password']], $cFile, $isLocal);
    $loginData = json_decode($res['body'] ?? '', true);
    
    if ($res['code'] == 200 && isset($loginData['status']) && $loginData['status'] == 'success') {
        $userId = (int)$loginData['Iduser'];
        $userName = $loginData['Name'];
        $userMoney = (float)$loginData['Money'];
        $liquidBalance = $userMoney - ($state['savings'] ?? 0);
        $personality = $brain->getPersonality($userId);
        
        echo "<h3>🤖 Bot: $userName ($personality, {$state['mood']}, " . number_format($userMoney) . ")</h3>";

        // V9.0 ACTIONS
        interactWithSocialPosting($baseUrl, $cFile, $userName, $state['mood'], $userMoney);
        interactWithMarketplace($baseUrl, $cFile, $liquidBalance);
        interactWithPvPChallenges($baseUrl, $cFile, $userId, $liquidBalance, $conn);
        interactWithRewardRedemption($baseUrl, $cFile, $userId, $conn);
        interactWithProfileVisits($baseUrl, $cFile, $userId, $conn);

        // V8.0 & Legacy Actions (Daily mission, wheel, scratch etc.)
        executeBotAction($baseUrl . "/api_daily_login.php?action=claim", null, $cFile, true);
        if (rand(1, 100) > 70) executeBotAction($baseUrl . "/caothe.php", null, $cFile, true);
        
        // Game Logic + Reward Points Earning
        if ($liquidBalance > 1000 && rand(1, 100) > 30) {
            $bet = max(500, min($liquidBalance * 0.05, 100000));
            $isWin = (rand(1, 100) <= 46);
            if ($isWin) {
                $win = $bet * 2;
                $conn->query("UPDATE users SET Money = Money + $win WHERE Iduser = $userId");
                earnRewardPoints($conn, $userId, 100, "Thắng game (Bot Engine)");
                $state['stats']['win_streak']++; $state['stats']['lose_streak'] = 0; $state['mood'] = 'excited';
            } else {
                $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");
                $state['stats']['win_streak'] = 0; $state['stats']['lose_streak']++; $state['mood'] = ($state['stats']['lose_streak'] > 3) ? 'tilted' : 'happy';
            }
        }

        // Logout & Save State
        executeBotAction($baseUrl . "/logout.php", null, $cFile, $isLocal);
        file_put_contents($sFile, json_encode($state));
    }
}
echo "<hr>✅ Bot Engine v9.0 Cycle Finished.";
