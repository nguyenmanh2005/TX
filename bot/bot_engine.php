<?php
/**
 * Bot Engine - The Brain of the Bot Army
 * Designed to be run via Cron Job every 5-10 minutes.
 */

require_once __DIR__ . '/../db_connect.php'; // Access DB directly for bot management
set_time_limit(0);

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
// If running in a subdirectory like /1/
if (strpos($_SERVER['REQUEST_URI'], '/1/') !== false) {
    $baseUrl .= "/1";
}

$cookieDir = __DIR__ . '/sessions/';
if (!is_dir($cookieDir))
    mkdir($cookieDir, 0777, true);

// ── CONFIGURATION (Updated for 15 Bots) ──
$botAccounts = [
    ['email' => 'bot01@gmail.com', 'pass' => '12345678@@A'],
    ['email' => 'bot02@gmail.com', 'pass' => '12345678@@A'],
    ['email' => 'bot03@gmail.com', 'pass' => '12345678@@A'],
    ['email' => 'bot04@gmail.com', 'pass' => '12345678@@A'],
    ['email' => 'bot05@gmail.com', 'pass' => '12345678@@A'],
    ['email' => 'bot06@gmail.com', 'pass' => '12345678@@A'],
    ['email' => 'bot07@gmail.com', 'pass' => '12345678@@A'],
    ['email' => 'bot08@gmail.com', 'pass' => '12345678@@A'],
    ['email' => 'bot09@gmail.com', 'pass' => '12345678@@A'],
    ['email' => 'bot10@gmail.com', 'pass' => '12345678@@A'],
    ['email' => 'bot11@gmail.com', 'pass' => '12345678@@A'],
    ['email' => 'bot12@gmail.com', 'pass' => '12345678@@A'],
    ['email' => 'bot13@gmail.com', 'pass' => '12345678@@A'],
    ['email' => 'bot14@gmail.com', 'pass' => '12345678@@A'],
    ['email' => 'bot15@gmail.com', 'pass' => '12345678@@A']
];

$chatMessages = [
    "Game này cuốn quá anh em ơi! 🔥",
    "Có ai vừa quay được gtlm không? tui đen quá 😢",
    "Admin gtlm.id.vn làm web mượt thật sự",
    "Mới leo lên top 10 xong, ai thách đấu không?",
    "Hôm nay điểm danh được nhiều quà phết!",
    "Blackjack nãy vừa thắng đậm, hên vãi!",
    "Hệ thống có vẻ ổn định đó admin",
    "Vừa nãy tui thử bug mà không được, bảo mật tốt đấy!"
];

// ── CORE FUNCTIONS ──
function executeBotAction($url, $postData = null, $cookieFile)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (BotArmy/2.0)');
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    if ($postData) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $response];
}

// ── MAIN ENGINE ──
echo "<h1>🚜 Bot Engine is running...</h1>";

// Pick 5-6 random bots to act this turn
$keys = array_rand($botAccounts, rand(1, 10));
if (!is_array($keys))
    $keys = [$keys];

foreach ($keys as $key) {
    $bot = $botAccounts[$key];
    $cFile = $cookieDir . md5($bot['email']) . ".txt";

    echo "<h3>🤖 Bot: {$bot['email']}</h3>";

    // 1. LOGIN
    $res = executeBotAction($baseUrl . "/login.php", ['email' => $bot['email'], 'password' => $bot['pass']], $cFile);
    $loginData = json_decode($res['body'], true);

    if ($res['code'] == 200 && isset($loginData['status']) && $loginData['status'] == 'success') {
        echo "- Đăng nhập OK<br>";

        // 2. DAILY LOGIN
        executeBotAction($baseUrl . "/api_daily_login.php", ['action' => 'claim'], $cFile);

        // 4. PLAY ALL GAMES (Dynamic Discovery)
        if (rand(1, 2) == 1) {
            $gameFiles = glob(__DIR__ . '/../games/*.php');
            $excludedGames = ['check_php.php', 'blackjack_process.php', 'baccarat_process.php', 'slot-sounds.js'];
            
            $validGames = [];
            foreach ($gameFiles as $file) {
                $name = basename($file);
                if (in_array($name, $excludedGames)) continue;
                $validGames[] = str_replace('.php', '', $name);
            }

            if (!empty($validGames)) {
                $gameKey = $validGames[array_rand($validGames)];
                $gameName = ucfirst($gameKey);
                $bet = rand(1000, 20000);
                $outcome = rand(1, 3); // 1: Win, 2: Lose, 3: Draw
                
                $msg = "";
                if ($outcome == 1) {
                    $winAmount = $bet * rand(2, 4);
                    $conn->query("UPDATE users SET Money = Money + $winAmount WHERE Iduser = " . ($loginData['Iduser'] ?? 0));
                    $msg = "🎉 Vừa thắng " . number_format($winAmount) . " GTLM ở trò $gameName! Đỏ vãi! 🎰";
                } elseif ($outcome == 2) {
                    $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = " . ($loginData['Iduser'] ?? 0));
                    $msg = "😢 Trò $gameName hút máu quá, vừa bay " . number_format($bet) . " GTLM rồi... 💸";
                } else {
                    $msg = "🤝 Vừa làm ván $gameName, may mà huề vốn.";
                }
                
                executeBotAction($baseUrl . "/chat.php", ['message' => $msg], $cFile);
                echo "- Đã chơi trò: $gameName<br>";
            }
        }

        // 5. CHAT (Randomly - general talk)
        if (rand(1, 4) == 1) {
            $msg = $chatMessages[array_rand($chatMessages)];
            executeBotAction($baseUrl . "/chat.php", ['message' => $msg], $cFile);
            echo "- Đã nhắn tin dạo: $msg<br>";
        }

        // 6. RANDOM SURF (To increase analytics data)
        $randomPages = ['/index.php', '/profile.php', '/shop.php', '/leaderboard.php', '/lucky_wheel.php'];
        $p = $randomPages[array_rand($randomPages)];
        executeBotAction($baseUrl . $p, null, $cFile);
        echo "- Đã dạo qua trang $p<br>";

        // 7. VULNERABILITY SCAN (Sanity Test)
        // Try to send weird parameters to an API to see if it crashes
        $badInput = ["id" => "' OR 1=1 --", "amount" => "-9999999"];
        $res = executeBotAction($baseUrl . "/api_statistics.php", $badInput, $cFile);
        if ($res['code'] >= 500) {
            executeBotAction($baseUrl . "/chat.php", ['message' => "⚠️ [Hệ thống] Cảnh báo lỗi 500 tại api_statistics.php!"], $cFile);
        }

    } else {
        echo "- Đăng nhập thất bại: " . ($loginData['message'] ?? 'Lỗi kết nối') . "<br>";
    }
}

echo "<hr>✅ Chu trình kết thúc.";
?>