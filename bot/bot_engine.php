<?php
/**
 * 🛡️ Ultimate Bot Engine v13.0 - The Boss Life
 * Full integration of User Dictionary: Trash talk, Greet, Beg, Reactions
 */

// 0. Helpers
function writeBotLog(string $email, string $action, string $details = "") {
    $logDir = __DIR__ . '/logs/';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $file = $logDir . date('Y-m-d') . '.log';
    $timestamp = date('H:i:s');
    $logLine = "[$timestamp] [$email] $action" . ($details ? ": $details" : "") . PHP_EOL;
    @file_put_contents($file, $logLine, FILE_APPEND);
}

function updateSyncData(string $key, $value) {
    $syncFile = __DIR__ . '/sessions/bot_sync.json';
    $data = file_exists($syncFile) ? json_decode(file_get_contents($syncFile), true) : [];
    $data[$key] = $value;
    $data['last_update'] = time();
    file_put_contents($syncFile, json_encode($data));
}

function getSyncData() {
    $syncFile = __DIR__ . '/sessions/bot_sync.json';
    return file_exists($syncFile) ? json_decode(file_get_contents($syncFile), true) : [];
}

// 1. Load config & brain
$config = require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/bot_brain.php';
$brain = new BotBrain();

$baseUrl = "http://localhost/1";
$cookieDir = __DIR__ . '/sessions/';

function executeBotAction(string $url, ?array $postData = null, string $cookieFile) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (BotArmy/13.0; BossLife)');
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

// ── V13.0 BOSS LIFE MODULES ──

function handleBossGreetings(string $baseUrl, string $cFile, int $userId, BotBrain $brain) {
    if (rand(1, 100) > 85) {
        $msg = $brain->generateMessage($userId, 'greet');
        executeBotAction($baseUrl . "/chat.php", ['message' => $msg], $cFile);
        echo "- 📢 Boss Greeting sent: \"$msg\"<br>";
    }
}

function handleTrashTalk(string $baseUrl, string $cFile, int $userId, BotBrain $brain, mysqli $conn) {
    if (rand(1, 100) > 90) {
        $msg = $brain->generateMessage($userId, 'trash_talk');
        executeBotAction($baseUrl . "/chat.php", ['message' => $msg], $cFile);
        echo "- 🤬 Boss Trash Talk: \"$msg\"<br>";
    }
}

function handleBegging(string $baseUrl, string $cFile, int $userId, float $money, BotBrain $brain) {
    if ($money < 50000 && rand(1, 100) > 70) {
        $msg = $brain->generateMessage($userId, 'beg');
        executeBotAction($baseUrl . "/chat.php", ['message' => $msg], $cFile);
        echo "- 🥺 Boss is 'borrowing' (Begging): \"$msg\"<br>";
    }
}

// ── MAIN LOOP ──
echo "<h1>🛡️ Bot Army Engine v13.0 (The Boss Life)</h1>";
$syncData = getSyncData();

foreach (array_slice($config['bot_emails'], 0, $config['settings']['max_bots_per_cycle']) as $email) {
    $botMd5 = md5($email);
    $cFile = $cookieDir . $botMd5 . ".txt";
    $sFile = $cookieDir . $botMd5 . ".state.json";
    $state = file_exists($sFile) ? json_decode(file_get_contents($sFile), true) : ['mood'=>'happy', 'savings'=>0];

    $res = executeBotAction($baseUrl . "/login.php", ['email' => $email, 'password' => $config['bot_password']], $cFile);
    if (isset($res['status']) && $res['status'] == 'success') {
        $userId = (int)$res['Iduser'];
        $userName = $res['Name'];
        $userMoney = (float)$res['Money'];

        echo "<h3>🤖 Bot: $userName (Balance: " . number_format($userMoney) . ")</h3>";

        // V13.0 BOSS ACTIONS
        handleBossGreetings($baseUrl, $cFile, $userId, $brain);
        handleTrashTalk($baseUrl, $cFile, $userId, $brain, $conn);
        handleBegging($baseUrl, $cFile, $userId, $userMoney, $brain);

        // V12.0 Features (Goals, Birthdays, etc.)
        if (date('m-d') === date('m-d', $userId * 86400)) {
            $msg = $brain->generateMessage($userId, 'birthday');
            executeBotAction($baseUrl . "/chat.php", ['message' => $msg], $cFile);
        }

        // Gameplay
        $bet = 5000;
        $isWin = (rand(0, 1));
        if ($isWin) {
            $conn->query("UPDATE users SET Money = Money + $bet WHERE Iduser = $userId");
            $msg = $brain->generateMessage($userId, 'win', ['amount' => number_format($bet)]);
            executeBotAction($baseUrl . "/api_social_feed.php", ['action' => 'create_post', 'content' => $msg], $cFile);
            echo "- 💰 Won and boasted: \"$msg\"<br>";
        } else {
            $conn->query("UPDATE users SET Money = Money - $bet WHERE Iduser = $userId");
            if (rand(1, 100) > 80) {
                $msg = $brain->generateMessage($userId, 'lose', ['amount' => number_format($bet)]);
                executeBotAction($baseUrl . "/chat.php", ['message' => $msg], $cFile);
                echo "- 😤 Lost and complained: \"$msg\"<br>";
            }
        }

        file_put_contents($sFile, json_encode($state));
    }
}
echo "<hr>✅ Bot Engine v13.0 Cycle Finished.";
