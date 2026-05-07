<?php
/**
 * Professional Bot Engine - Production Ready
 * Features: SSL Detection, Session Cleanup, Isolated Config, Secure DB Ops, Logging, Retry Logic
 */
 
// 0. Logging Helper
function writeBotLog($email, $action, $details = "") {
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

// 2. Performance & Stability Settings
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Enable strict reporting for try/catch
set_time_limit($config['settings']['timeout']);
$isCli = (php_sapi_name() === 'cli');
$isLocal = $isCli || (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || $_SERVER['HTTP_HOST'] === '127.0.0.1'));

if ($isCli) {
    // Khi chạy bằng file .bat, mặc định dùng localhost/1
    $baseUrl = "http://localhost/1";
} else {
    $baseUrl = ($isLocal ? "http" : "https") . "://$_SERVER[HTTP_HOST]";
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/1/') !== false) {
        $baseUrl .= "/1";
    }
}

$cookieDir = __DIR__ . '/sessions/';
if (!is_dir($cookieDir))
    mkdir($cookieDir, 0700, true);

// 3. Session Housekeeping (Delete files older than 24h)
$files = glob($cookieDir . "*.txt");
foreach ($files as $file) {
    if (time() - filemtime($file) > $config['settings']['session_lifetime']) {
        @unlink($file);
    }
}

// 4. Helper for Secure Bot Transactions with Audit Log
function updateBotBalance($conn, int $userId, float $amount, string $game, string $type = 'win')
{
    if ($userId <= 0)
        return false;

    $conn->begin_transaction();
    try {
        // Update User Money
        $sql = ($type === 'win')
            ? "UPDATE users SET Money = Money + ? WHERE Iduser = ?"
            : "UPDATE users SET Money = Money - ? WHERE Iduser = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("di", $amount, $userId);
        $stmt->execute();
        $stmt->close();

        // Audit Log (Optional but recommended)
        $logSql = "INSERT INTO bot_transactions (user_id, amount, game, type, created_at) VALUES (?, ?, ?, ?, NOW())";
        $logStmt = $conn->prepare($logSql);
        $logStmt->bind_param("idss", $userId, $amount, $game, $type);
        $logStmt->execute();
        $logStmt->close();

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

// ── CORE REQUEST FUNCTION ──
function executeBotAction(string $url, ?array $postData = null, string $cookieFile, bool $isLocal)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (BotArmy/5.0; Final)');
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
    $ch = null;
    return ['code' => $code, 'body' => $response];
}

/**
 * Tính toán GTLM cược thông minh dựa trên tài sản và tính cách
 */
function calculateSmartBet(float $money, string $personality): int {
    if ($money <= 1000) return rand(100, 500); // Quá nghèo thì đánh nhỏ

    // Tỷ lệ siêu nhỏ (0.5%) sẽ All In (Tất tay)
    if (rand(1, 200) === 1) {
        return (int)$money; 
    }

    $percent = 0.02; // Mặc định 2%
    switch ($personality) {
        case 'arrogant':
        case 'boss': $percent = rand(15, 25) / 100; break;     // 15-25% (Chơi ngông / Bố đời)
        case 'aggressive': $percent = rand(5, 10) / 100; break; // 5-10% (Hung hãn)
        case 'chaotic': $percent = rand(1, 50) / 100; break;    // 1-50% (Hỗn loạn cực độ)
        case 'smartass': $percent = rand(3, 7) / 100; break;    // 3-7% (Tính toán kỹ)
        case 'dead_inside': $percent = rand(1, 10) / 100; break;// 1-10% (Bất cần đời)
        case 'memelord': $percent = rand(4, 8) / 100; break;    // 4-8% (Chơi lầy)
        case 'dramaqueen': $percent = rand(5, 15) / 100; break; // 5-15% (Làm quá)
        case 'penguin': $percent = rand(1, 3) / 100; break;     // 1-3% (Ngáo ngơ)
        case 'corporate': $percent = rand(2, 4) / 100; break;   // 2-4% (KPI văn phòng)
        case 'philosophy': $percent = rand(2, 6) / 100; break;  // 2-6% (Triết lý)
        case 'funny':
        case 'friendly': $percent = rand(2, 5) / 100; break;    // 2-5% (Vui vẻ)
        case 'shy': $percent = rand(5, 15) / 1000; break;       // 0.5-1.5% (Nhút nhát)
    }

    $bet = $money * $percent;
    
    // Giới hạn cược để không bị cháy túi quá nhanh hoặc đánh quá nhỏ
    $minBet = 500;
    $maxBet = 5000000; // Tối đa 5M GTLM mỗi ván
    
    return (int)floor(max($minBet, min($bet, $maxBet)));
}

// 4. Load Brain & Config
require_once __DIR__ . '/bot_brain.php';
$brain = new BotBrain();

// Generate all bot names to avoid bot-to-bot loops
$allBotNames = [];
for ($i = 1; $i <= 15; $i++) {
    $allBotNames[] = "Bot " . str_pad($i, 2, '0', STR_PAD_LEFT);
}

// ── HELPER: Send Chat & Update State ──
function sendBotChat(string $baseUrl, string $cFile, string $sFile, array &$state, string $message, bool $isLocal) {
    $cooldown = 30; // 30 seconds
    if (time() - $state['last_chat_time'] < $cooldown) {
        return false;
    }
    
    // Prevent duplicate messages (Check last 5)
    $recentMessages = $state['recent_messages'] ?? [];
    if (in_array($message, $recentMessages)) {
        return false;
    }

    $res = executeBotAction($baseUrl . "/chat.php", ['message' => $message], $cFile, $isLocal);
    if ($res['code'] == 200) {
        $state['last_chat_time'] = time();
        $state['last_message'] = $message;
        
        // Update recent messages
        $recentMessages[] = $message;
        if (count($recentMessages) > 5) {
            array_shift($recentMessages);
        }
        $state['recent_messages'] = $recentMessages;
        
        // Simulate typing time
        $typingDelay = rand(1, 3);
        sleep($typingDelay);
        
        file_put_contents($sFile, json_encode($state), LOCK_EX); 
        return true;
    }
    return false;
}

// ── MAIN ENGINE ──
echo "<h1>🛡️ Bot Army Engine v6.3 (Strict & Robust)</h1>";

$allBots = $config['bot_emails'];
shuffle($allBots);
$botCount = min(count($allBots), $config['settings']['max_bots_per_cycle']);
$selectedEmails = array_slice($allBots, 0, $botCount);
 
// 4.5. Cycle Logging Setup
$cycleLogDir = __DIR__ . '/cycle_logs/';
if (!is_dir($cycleLogDir)) @mkdir($cycleLogDir, 0755, true);
$currentCycleLogFile = $cycleLogDir . date('Y-m-d_H') . 'h.log'; // Group by hour to avoid too many files
$cycleOutput = "── BOT CYCLE START: " . date('Y-m-d H:i:s') . " ──" . PHP_EOL;
$cycleOutput .= "Selected Bots: " . count($selectedEmails) . PHP_EOL;

// ── DYNAMIC ACTIVITY CALCULATION ──
// Count active users in last 15 minutes to adjust bot presence
$onlineCount = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT user_id) as online FROM site_analytics WHERE user_id > 0 AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    if ($stmt) {
        $stmt->execute();
        $onlineRes = $stmt->get_result();
        $row = $onlineRes->fetch_assoc();
        $onlineCount = (int)($row['online'] ?? 0);
        $stmt->close();
    }
} catch (Exception $e) {
    // If table doesn't have created_at or other error, fallback to 0
}
$trafficBonus = min(50, $onlineCount * 5); // Max +50% chance if many users online

foreach ($selectedEmails as $email) {
    $cycleOutput .= "[$email] Starting actions..." . PHP_EOL;
    $botMd5 = md5($email);
    $cFile = $cookieDir . $botMd5 . ".txt";
    $sFile = $cookieDir . $botMd5 . ".state.json"; 
    
    // Load state
    // Load state with extended memory
    $defaultState = [
        'last_chat_time' => 0, 
        'last_message' => '',
        'recent_messages' => [],
        'stats' => ['wins' => 0, 'losses' => 0, 'win_streak' => 0, 'consecutive_losses' => 0],
        'preferred_games' => [],
        'frequent_users' => [], // [username => count]
        'past_opponents' => [] // [userId]
    ];
    $state = file_exists($sFile) ? array_merge($defaultState, json_decode(file_get_contents($sFile), true)) : $defaultState;

    // ── BIRTHDAY SYSTEM ──
    $botSeed = crc32($email);
    $birthMonth = ($botSeed % 12) + 1;
    $birthDay = (($botSeed / 12) % 28) + 1;
    $isBirthday = (date('n') == $birthMonth && date('j') == $birthDay);
    $yearKey = "birthday_posted_" . date('Y');
    
    if ($isBirthday && !isset($state[$yearKey])) {
        // We'll post later after authentication
    }

    // ── BIORHYTHM CHECK (Time-based Activity) ──
    $hour = (int)date('H');
    $isNight = ($hour >= 0 && $hour < 7);
    $isMorning = ($hour >= 7 && $hour < 9);
    $isEvening = ($hour >= 20 && $hour < 23);
    
    $activityChance = 100; // Mặc định hoạt động 100%
    if ($isNight) {
        $activityChance = 15; // Ban đêm chỉ 15% bot thức (Cú đêm)
    } elseif ($isMorning) {
        $activityChance = 60; // Sáng sớm 60% bot thức dậy
    } elseif ($isEvening) {
        $activityChance = 90; // Buổi tối hoạt động mạnh 90%
    }
    
    // Apply traffic bonus
    $finalChance = min(100, $activityChance + $trafficBonus);
    
    // Luôn cho phép ít nhất 1-2 con bot cố định hoạt động để server không trống
    $isAlwaysAwake = (strpos($email, 'bot01') !== false || strpos($email, 'bot02') !== false);
    
    if (!$isAlwaysAwake && rand(1, 100) > $finalChance) {
        echo "- Bot is currently sleeping (Biorhythm: $hour:00, Traffic Bonus: +$trafficBonus%, Final Chance: $finalChance%)<br>";
        continue; 
    }

    echo "<h3>🤖 Processing: " . htmlspecialchars($email) . "</h3>";
 
    $loginSuccess = false;
    $loginData = [];
    $retryCount = 0;
    $maxRetries = 2; // Tổng cộng 3 lần thử
 
    while ($retryCount <= $maxRetries && !$loginSuccess) {
        $res = executeBotAction($baseUrl . "/login.php", ['email' => $email, 'password' => $config['bot_password']], $cFile, $isLocal);
        $loginData = json_decode($res['body'] ?? '', true);
        
        if ($res['code'] == 200 && isset($loginData['status']) && $loginData['status'] == 'success') {
            $loginSuccess = true;
        } else {
            $retryCount++;
            if ($retryCount <= $maxRetries) {
                writeBotLog($email, "Login Failed", "Retry $retryCount/$maxRetries");
                usleep(500000); // Wait 0.5s before retry
            }
        }
    }
 
    if ($loginSuccess) {
        writeBotLog($email, "Login Success");
        $userId = (int) ($loginData['Iduser'] ?? 0);
        $userName = $loginData['Name'] ?? 'Bot';
        $userMoney = (float) ($loginData['Money'] ?? 0);
        $isPoor = ($userMoney < 1000);
        echo "- Authenticated as $userName (ID: $userId, Balance: $userMoney)<br>";
        
        // Birthday Check
        if ($isBirthday && !isset($state[$yearKey])) {
            $bdayMsg = $brain->generateMessage($userId, 'birthday');
            if ($bdayMsg) {
                $stmt = $conn->prepare("INSERT INTO social_feed (user_id, activity_type, message) VALUES (?, 'birthday', ?)");
                if ($stmt) {
                    $stmt->bind_param("is", $userId, $bdayMsg);
                    $stmt->execute();
                    $stmt->close();
                    echo "- Happy Birthday! Posted status: $bdayMsg<br>";
                    writeBotLog($email, "Birthday Status", $bdayMsg);
                    sendBotChat($baseUrl, $cFile, $sFile, $state, $bdayMsg, $isLocal);
                }
            }
            $state[$yearKey] = true;
            file_put_contents($sFile, json_encode($state), LOCK_EX);
        }
 
        // A. Daily Tasks (Always do this first, even if poor)
        executeBotAction($baseUrl . "/api_daily_login.php", ['action' => 'claim'], $cFile, $isLocal);
 
        // A1. Streak & VIP
        $streakRes = executeBotAction($baseUrl . "/api_streak.php", ['action' => 'get_info'], $cFile, $isLocal);
        $streakInfo = json_decode($streakRes['body'] ?? '', true);
        if (isset($streakInfo['data']['current_streak'])) {
            $currStreak = $streakInfo['data']['current_streak'];
            $milestones = [3, 7, 14, 30, 60, 100];
            foreach ($milestones as $m) {
                if ($currStreak >= $m) {
                    executeBotAction($baseUrl . "/api_streak.php", ['action' => 'claim_milestone', 'milestone_days' => $m], $cFile, $isLocal);
                }
            }
        }
 
        $vipRes = executeBotAction($baseUrl . "/api_vip.php", ['action' => 'get_info'], $cFile, $isLocal);
        $vipInfo = json_decode($vipRes['body'] ?? '', true);
        if (isset($vipInfo['data']['daily_bonus']) && $vipInfo['data']['daily_bonus'] > 0) {
            executeBotAction($baseUrl . "/api_vip.php", ['action' => 'claim_daily_bonus'], $cFile, $isLocal);
        }
 
        // A2. Achievements (Visit page to auto-claim & Flex)
        executeBotAction($baseUrl . "/api_check_rank_achievements.php", null, $cFile, $isLocal);
        executeBotAction($baseUrl . "/achievements.php", null, $cFile, $isLocal);
        
        $notifRes = executeBotAction($baseUrl . "/api_notifications.php?action=get_list&unread_only=1", null, $cFile, $isLocal);
        $notifs = json_decode($notifRes['body'] ?? '', true);
        if (isset($notifs['notifications'])) {
            foreach ($notifs['notifications'] as $n) {
                if ($n['type'] === 'achievement' && !$n['is_read']) {
                    $flexMsg = $brain->generateMessage($userId, 'flex_achievement', ['title' => $n['title']]);
                    if ($flexMsg && sendBotChat($baseUrl, $cFile, $sFile, $state, $flexMsg, $isLocal)) {
                        echo "- Flexed achievement in chat: {$n['title']}<br>";
                        // Mark as read
                        executeBotAction($baseUrl . "/api_notifications.php", ['action' => 'mark_read', 'notification_id' => $n['id']], $cFile, $isLocal);
                    }
                }
            }
        }
        echo "- Processed Daily Tasks, Streak, VIP and Achievements<br>";

        // B1. Social PM to Frequent Users (Random chance)
        if (!$isPoor && !empty($state['frequent_users']) && rand(1, 100) > 95) {
            $frequentList = array_keys($state['frequent_users']);
            $targetUser = $frequentList[array_rand($frequentList)];
            
            $stmt = $conn->prepare("SELECT Iduser FROM users WHERE Name = ? LIMIT 1");
            $stmt->bind_param("s", $targetUser);
            $stmt->execute();
            $targetData = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($targetData) {
                $socialMsg = $brain->generateMessage($userId, 'social_pm');
                if ($socialMsg) {
                    executeBotAction($baseUrl . "/api_friends.php", [
                        'action' => 'send_message',
                        'to_user_id' => $targetData['Iduser'],
                        'message' => $socialMsg
                    ], $cFile, $isLocal);
                    echo "- Sent social PM to $targetUser: $socialMsg<br>";
                    writeBotLog($email, "Social PM", "To $targetUser: $socialMsg");
                }
            }
        }
        
        if ($isPoor) {
            echo "- Balance too low. Entering Begging Mode...<br>";
            $begMsg = $brain->generateMessage($userId, 'beg');
            if ($begMsg) {
                if (sendBotChat($baseUrl, $cFile, $sFile, $state, $begMsg, $isLocal)) {
                    echo "- Begged on public chat: $begMsg<br>";
                }
                
                // Send Private Message to a random friend
                $friendsRes = executeBotAction($baseUrl . "/api_friends.php?action=get_friends", null, $cFile, $isLocal);
                $friendsData = json_decode($friendsRes['body'] ?? '', true);
                if (isset($friendsData['friends']) && !empty($friendsData['friends'])) {
                    $randomFriend = $friendsData['friends'][array_rand($friendsData['friends'])];
                    executeBotAction($baseUrl . "/api_friends.php", [
                        'action' => 'send_message',
                        'to_user_id' => $randomFriend['friend_id'],
                        'message' => $begMsg
                    ], $cFile, $isLocal);
                    echo "- Sent private beg message to {$randomFriend['friend_name']}<br>";
                }
            }
        }

        // B2. Reward Shop Check (If points > 500)
        if (rand(1, 100) > 90) {
            $stmt = $conn->prepare("SELECT available_points FROM reward_points WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $pointsRes = $stmt->get_result();
            if ($pointsRes && $pRow = $pointsRes->fetch_assoc()) {
                if ($pRow['available_points'] >= 500) {
                    $rid = rand(1, 2);
                    executeBotAction($baseUrl . "/api_reward_points.php", ['action' => 'redeem', 'reward_id' => $rid], $cFile, $isLocal);
                    echo "- Attempted to redeem reward ID $rid using points<br>";
                }
            }
            $stmt->close();
        }

        // C. Respond to PvP Challenges
        $notifRes = executeBotAction($baseUrl . "/api_notifications.php?action=get_list&unread_only=1", null, $cFile, $isLocal);
        $notifs = json_decode($notifRes['body'] ?? '', true);
        if (isset($notifs['notifications'])) {
            foreach ($notifs['notifications'] as $n) {
                if ($n['is_read'] || $isPoor) continue; 
                
                if (strpos($n['title'], 'Thách Đấu') !== false) {
                    $challengeId = $n['related_id'];
                    $challengerId = $n['related_id']; 
                    $pastOpponents = $state['past_opponents'] ?? [];
                    $isFamiliar = in_array($challengerId, $pastOpponents);
                    
                    $personality = $brain->getPersonality($userId);
                    $acceptChance = ($personality == 'shy') ? 10 : (($personality == 'aggressive') ? 90 : 50);
                    if ($isFamiliar) $acceptChance = min(100, $acceptChance + 25);
                    
                    if (rand(1, 100) <= $acceptChance) {
                        executeBotAction($baseUrl . "/api_pvp_challenge.php", ['action' => 'accept_challenge', 'challenge_id' => $challengeId], $cFile, $isLocal);
                        echo "- Accepted Challenge #$challengeId (Familiar: " . ($isFamiliar ? "Yes" : "No") . ")<br>";
                        
                        if (!$isFamiliar) {
                            $pastOpponents[] = $challengerId;
                            if (count($pastOpponents) > 5) array_shift($pastOpponents);
                            $state['past_opponents'] = $pastOpponents;
                        }
                    }
                }
            }
        }

        // D. Perform PvP Actions (If not poor)
        if (!$isPoor) {
            $myPvpRes = executeBotAction($baseUrl . "/api_pvp_challenge.php?action=get_my_challenges&status=accepted", null, $cFile, $isLocal);
            $myPvps = json_decode($myPvpRes['body'] ?? '', true);
            if (isset($myPvps['challenges'])) {
                foreach ($myPvps['challenges'] as $c) {
                    $isChallenger = ($c['challenger_id'] == $userId);
                    $hasSubmitted = $isChallenger ? !empty($c['challenger_choice']) : !empty($c['opponent_choice']);
                    
                    if (!$hasSubmitted) {
                        $choices = ['coinflip' => ['heads', 'tails'], 'dice' => ['1','2','3','4','5','6'], 'rps' => ['rock', 'paper', 'scissors'], 'number' => [(string)rand(1, 100)]];
                        $myChoice = $choices[$c['game_type']][array_rand($choices[$c['game_type']])] ?? '1';
                        executeBotAction($baseUrl . "/api_pvp_challenge.php", ['action' => 'submit_choice', 'challenge_id' => $c['id'], 'choice' => $myChoice], $cFile, $isLocal);
                        echo "- Submitted PvP choice: $myChoice<br>";
                    }
                }
            }
        }

        // E. Tournament Registration
        $activeTournamentId = 0;
        $tourRes = executeBotAction($baseUrl . "/api_tournament.php?action=get_list&status=active", null, $cFile, $isLocal);
        $tourData = json_decode($tourRes['body'] ?? '', true);
        if (isset($tourData['tournaments']) && !empty($tourData['tournaments'])) {
            foreach ($tourData['tournaments'] as $t) {
                if ($t['is_registered'] == 0) {
                    executeBotAction($baseUrl . "/api_tournament.php", ['action' => 'register', 'tournament_id' => $t['id']], $cFile, $isLocal);
                    echo "- Registered for Tournament #{$t['id']}<br>";
                }
                $activeTournamentId = $t['id']; 
                
                // Randomly flex about tournament
                if (rand(1, 100) > 90) {
                    $flexMsg = $brain->generateMessage($userId, 'flex_tournament', ['game' => $t['name']]);
                    if ($flexMsg) sendBotChat($baseUrl, $cFile, $sFile, $state, $flexMsg, $isLocal);
                }
            }
        }
 
        // G. Leaderboard Check & Flexing
        $rankRes = executeBotAction($baseUrl . "/api_leaderboard.php?action=get_user_rank&type=overall", null, $cFile, $isLocal);
        $rankData = json_decode($rankRes['body'] ?? '', true);
        if (isset($rankData['rank'])) {
            $currentRank = (int)$rankData['rank'];
            $oldRank = $state['last_rank'] ?? 999;
            
            if ($currentRank < $oldRank || ($currentRank <= 10 && rand(1, 100) > 90)) {
                $flexMsg = $brain->generateMessage($userId, 'flex_rank', ['rank' => $currentRank]);
                if ($flexMsg && sendBotChat($baseUrl, $cFile, $sFile, $state, $flexMsg, $isLocal)) {
                    echo "- Flexed rank in chat: Hạng $currentRank<br>";
                }
            }
            $state['last_rank'] = $currentRank;
        }

        // H. Chat Interaction (Memory: Remember users, Context: 15 messages)
        $chatRes = executeBotAction($baseUrl . "/chat.php?action=load", null, $cFile, $isLocal);
        $messages = json_decode($chatRes['body'] ?? '', true);
        if (is_array($messages)) {
            $recent = array_slice($messages, -15); 
            $reply = $brain->analyzeChat($userId, $recent, $userName, $allBotNames, $state);
            if ($reply) {
                if (sendBotChat($baseUrl, $cFile, $sFile, $state, $reply, $isLocal)) {
                    echo "- Replied to chat: $reply<br>";
                    writeBotLog($email, "Chat Reply", $reply);
                }
            }
        }

        // F. Play Random Games (If not poor)
        $isTilted = ($state['stats']['consecutive_losses'] >= 3);
        if ($isTilted && rand(1, 100) > 50) {
            $tiltMsg = $brain->generateMessage($userId, 'tilted');
            if ($tiltMsg) sendBotChat($baseUrl, $cFile, $sFile, $state, $tiltMsg, $isLocal);
            echo "- Bot is tilted (Losses: {$state['stats']['consecutive_losses']}). Skipping games...<br>";
        } elseif (!$isPoor && rand(1, 100) > 20) {
            $gameDir = 'games';
            $gameFiles = glob(__DIR__ . '/../games/*.php');
            if (empty($gameFiles)) { $gameFiles = glob(__DIR__ . '/../game/*.php'); $gameDir = 'game'; }
            
            $validGames = [];
            foreach ($gameFiles as $f) {
                $name = basename($f);
                if (!in_array($name, ['check_php.php', 'blackjack_process.php', 'baccarat_process.php', 'slot-sounds.js', 'tower_process.php']) && strpos($name, '.php') !== false)
                    $validGames[] = str_replace('.php', '', $name);
            }

            if (!empty($validGames)) {
                $gameKey = $validGames[array_rand($validGames)];
                $bet = calculateSmartBet($userMoney, $brain->getPersonality($userId));
                $randOutcome = rand(1, 100);
                $outcome = 3; // Default: Draw/Skip
                if ($randOutcome <= 45) $outcome = 1; // Win (45%)
                elseif ($randOutcome <= 85) $outcome = 2; // Lose (40%)
                
                if ($outcome !== 3) {
                    executeBotAction($baseUrl . "/$gameDir/$gameKey.php", null, $cFile, $isLocal);
                    
                    // Tournament Logging
                    if ($activeTournamentId > 0) {
                        executeBotAction($baseUrl . "/api_tournament.php", [
                            'action' => 'log_game',
                            'tournament_id' => $activeTournamentId,
                            'game_name' => $gameKey,
                            'bet_amount' => $bet,
                            'win_amount' => ($outcome == 1 ? $bet * 2 : 0), // Estimate win amount for logging
                            'is_win' => ($outcome == 1 ? 1 : 0)
                        ], $cFile, $isLocal);
                    }
                }
                
                $win = 0; // Initialize to avoid IDE warnings
                $msg = "";
                if ($outcome == 1) { // Win
                    $win = $bet * rand(2, 5);
                    updateBotBalance($conn, $userId, (float)$win, $gameKey, 'win');
                    $msg = $brain->generateMessage($userId, 'win', ['amount' => number_format($win), 'game' => ucfirst($gameKey)]);
                    
                    $state['stats']['wins']++;
                    $state['stats']['win_streak']++;
                    $state['stats']['consecutive_losses'] = 0;
                    writeBotLog($email, "Game Win", "$gameKey (+$win)");

                    // Streak Flexing
                    if ($state['stats']['win_streak'] >= 3 && rand(1, 100) <= 80) {
                        $streakMsg = $brain->generateMessage($userId, 'win_streak', ['count' => $state['stats']['win_streak']]);
                        if ($streakMsg) sendBotChat($baseUrl, $cFile, $sFile, $state, $streakMsg, $isLocal);
                    }
                } elseif ($outcome == 2) { // Lose
                    updateBotBalance($conn, $userId, (float)$bet, $gameKey, 'lose');
                    $msg = $brain->generateMessage($userId, 'lose', ['amount' => number_format($bet), 'game' => ucfirst($gameKey)]);
                    
                    $state['stats']['losses']++;
                    $state['stats']['win_streak'] = 0;
                    $state['stats']['consecutive_losses']++;
                    writeBotLog($email, "Game Lose", "$gameKey (-$bet)");

                    // Streak Complaining
                    if ($state['stats']['consecutive_losses'] >= 3 && rand(1, 100) <= 80) {
                        $streakMsg = $brain->generateMessage($userId, 'lose_streak', ['count' => $state['stats']['consecutive_losses']]);
                        if ($streakMsg) sendBotChat($baseUrl, $cFile, $sFile, $state, $streakMsg, $isLocal);
                    }
                }
                
                // AI Memory: Preferred games
                if (!isset($state['preferred_games'][$gameKey])) {
                    $state['preferred_games'][$gameKey] = 0;
                }
                $state['preferred_games'][$gameKey]++;
                
                if ($msg) {
                    if (sendBotChat($baseUrl, $cFile, $sFile, $state, $msg, $isLocal)) {
                        echo "- Played $gameKey and posted to chat.<br>";
                        
                        // Social Feed (Use explicit $conn)
                        if ($outcome == 1 && rand(1, 100) > 70) {
                            $feedMsg = $brain->generateStatus($userId, 'win', ['amount' => number_format($win), 'game' => ucfirst($gameKey)]);
                            if ($feedMsg) {
                                $sql = "INSERT INTO social_feed (user_id, activity_type, message) VALUES (?, 'big_win', ?)";
                                $stmt = $conn->prepare($sql);
                                if ($stmt) {
                                    $stmt->bind_param("is", $userId, $feedMsg);
                                    $stmt->execute();
                                    $stmt->close();
                                    echo "- Posted win to Social Feed<br>";
                                }
                            }
                        }
                    }
                }
            }
        }

        // G. Shopping & Branding (Updated: Buy & Use items)
        if (!$isPoor && rand(1, 100) > 90) {
            if ($userMoney > 200000 && rand(1, 100) > 85) {
                // Buy something new (15% chance if rich enough)
                $shopActions = [
                    'buy_theme' => 'theme_id',
                    'buy_cursor' => 'cursor_id',
                    'buy_chat_frame' => 'chat_frame_id',
                    'buy_avatar_frame' => 'avatar_frame_id'
                ];
                $type = array_rand($shopActions);
                $field = $shopActions[$type];
                $itemId = rand(1, 20); // Try a random ID
                
                executeBotAction($baseUrl . "/shop.php", [$type => '1', $field => $itemId], $cFile, $isLocal);
                echo "- Bought/Activated shop item: $field #$itemId<br>";
                // Post to Social Feed
                if (rand(1, 100) > 60) {
                    $status = $brain->generateStatus($userId, 'shopping');
                    $stmt = $conn->prepare("INSERT INTO social_feed (user_id, activity_type, message) VALUES (?, 'shopping', ?)");
                    if ($stmt) {
                        $stmt->bind_param("is", $userId, $status);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            } else {
                // Use something from inventory (10% chance)
                $inventoryActions = [
                    'activate_theme' => 'theme_id',
                    'activate_cursor' => 'cursor_id',
                    'activate_chat_frame' => 'chat_frame_id',
                    'activate_avatar_frame' => 'avatar_frame_id'
                ];
                $action = array_rand($inventoryActions);
                $field = $inventoryActions[$action];
                $itemId = rand(1, 15); 
                
                executeBotAction($baseUrl . "/inventory.php", [$action => '1', $field => $itemId], $cFile, $isLocal);
                echo "- Equipped item from inventory: $field #$itemId<br>";
            }
        }

        // H. Guild Actions (New: 10% chance)
        if (rand(1, 100) > 90) {
            $hasGuild = isset($state['guild_id']) && $state['guild_id'] > 0;
            
            if (!$hasGuild) {
                // Check current status from API first
                $guildRes = executeBotAction($baseUrl . "/api_guilds.php?action=get_info", null, $cFile, $isLocal);
                $guildData = json_decode($guildRes['body'] ?? '', true);
                
                if ($guildData && $guildData['success'] && isset($guildData['guild']['is_member']) && $guildData['guild']['is_member']) {
                    $state['guild_id'] = $guildData['guild']['id'];
                    file_put_contents($sFile, json_encode($state), LOCK_EX);
                    $hasGuild = true;
                } else {
                    // Not in guild, try to join
                    $searchRes = executeBotAction($baseUrl . "/api_guilds.php?action=search&keyword=a", null, $cFile, $isLocal);
                    $searchData = json_decode($searchRes['body'] ?? '', true);
                    if (!empty($searchData['guilds'])) {
                        $targetGuild = $searchData['guilds'][array_rand($searchData['guilds'])];
                        executeBotAction($baseUrl . "/api_guilds.php", ['action' => 'join', 'guild_id' => $targetGuild['id']], $cFile, $isLocal);
                        echo "- Sent join request to guild: {$targetGuild['name']}<br>";
                        // Post to Social Feed
                        if (rand(1, 100) > 70) {
                            $status = "Vừa xin gia nhập guild {$targetGuild['name']}, hi vọng anh em sớm duyệt! 🤝";
                            $stmt = $conn->prepare("INSERT INTO social_feed (user_id, activity_type, message) VALUES (?, 'guild_join', ?)");
                            if ($stmt) {
                                $stmt->bind_param("is", $userId, $status);
                                $stmt->execute();
                                $stmt->close();
                            }
                        }
                    }
                }
            }

            if ($hasGuild) {
                // Already in guild, send a message
                $guildMsg = $brain->generateMessage($userId, 'greet');
                executeBotAction($baseUrl . "/api_guilds.php", ['action' => 'chat', 'guild_id' => $state['guild_id'], 'message' => $guildMsg], $cFile, $isLocal);
                echo "- Messaged guild chat: $guildMsg<br>";
            }
        }

        // I. Marketplace Interaction (New: 5% chance)
        if (!$isPoor && rand(1, 100) > 95) {
            $listingId = rand(1, 20);
            $res = executeBotAction($baseUrl . "/api_marketplace.php", ['action' => 'buy_item', 'listing_id' => $listingId], $cFile, $isLocal);
            if ($res['code'] == 200) {
                echo "- Bought item from Marketplace<br>";
                // Post to Social Feed
                if (rand(1, 100) > 60) {
                    $status = "Vừa săn được món hời trên Marketplace! 😎";
                    $stmt = $conn->prepare("INSERT INTO social_feed (user_id, activity_type, message) VALUES (?, 'marketplace', ?)");
                    if ($stmt) {
                        $stmt->bind_param("is", $userId, $status);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
        }

        // J. Social Gifting (New: Wealthy bots only)
        if ($userMoney > 1000000 && rand(1, 100) > 95) {
            // Find a real random user (excluding self and bots if possible, but let's keep it simple: any real user)
            $stmt = $conn->prepare("SELECT Iduser FROM users WHERE Iduser != ? ORDER BY RAND() LIMIT 1");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $userRes = $stmt->get_result();
            $userData = $userRes->fetch_assoc();
            $stmt->close();
            $targetUserId = $userData ? (int)$userData['Iduser'] : 0;
            
            if ($targetUserId > 0) {
                $giftAmount = rand(20000, 100000);
                executeBotAction($baseUrl . "/api_gift.php", [
                    'action' => 'send_money', 
                    'to_user_id' => $targetUserId, 
                    'amount' => $giftAmount, 
                    'message' => "Lộc phát! Chúc anh em chơi vui vẻ, húp mạnh nhé! 🍀"
                ], $cFile, $isLocal);
                echo "- Gifted $giftAmount gtlm to User #$targetUserId<br>";
                // Post to Social Feed
                if (rand(1, 100) > 50) {
                    $status = "Vừa phát lộc cho người bạn #{$targetUserId} ít GTLM, chúc bạn may mắn nhé! 🧧";
                    $stmt = $conn->prepare("INSERT INTO social_feed (user_id, activity_type, message) VALUES (?, 'gifting', ?)");
                    if ($stmt) {
                        $stmt->bind_param("is", $userId, $status);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
        }

        // K. Title Management (Enhanced)
        if (rand(1, 100) > 90) {
            $personality = $brain->getPersonality($userId);
            $changeChance = ($personality == 'arrogant') ? 40 : (($personality == 'shy') ? 5 : 15);
            
            if (rand(1, 100) <= $changeChance) {
                // Pick a random title from a wider pool (assuming up to 30 titles exist)
                $titleId = rand(1, 30);
                executeBotAction($baseUrl . "/select_title.php", ['select_title' => '1', 'title_id' => $titleId], $cFile, $isLocal);
                echo "- Updated display title to #$titleId (Personality: $personality)<br>";
            }
        }

        // L. Social Outbound (Friend requests & Challenges)
        if (rand(1, 100) > 85) { // 15% chance to be social
            // Randomly pick a user from chat to friend or challenge
            if (!empty($messages)) {
                $randomMsg = $messages[array_rand($messages)];
                $targetId = $randomMsg['user_id'] ?? 0;
                $targetName = $randomMsg['username'] ?? '';
                
                if ($targetId > 0 && $targetId != $userId) {
                    if (rand(1, 2) == 1) {
                        // Friend request
                        executeBotAction($baseUrl . "/api_friends.php", ['action' => 'send_friend_request', 'friend_id' => $targetId], $cFile, $isLocal);
                        echo "- Sent friend request to $targetName<br>";
                    } else {
                        // PvP Challenge
                        $gameTypes = ['coinflip', 'dice', 'rps', 'number'];
                        $gt = $gameTypes[array_rand($gameTypes)];
                        $bet = calculateSmartBet($userMoney, $brain->getPersonality($userId));
                        executeBotAction($baseUrl . "/api_pvp_challenge.php", [
                            'action' => 'create_challenge', 
                            'opponent_id' => $targetId, 
                            'game_type' => $gt, 
                            'bet_amount' => $bet
                        ], $cFile, $isLocal);
                        echo "- Challenged $targetName to $gt for $bet GTLM<br>";
                    }
                }
            }
        }

        // M. Social Feed Interactions (New: 20% chance)
        if (rand(1, 100) > 80) {
            $res = executeBotAction($baseUrl . "/api_social_feed.php", ['action' => 'get_feed'], $cFile, $isLocal);
            if ($res['code'] == 200) {
                $feedData = json_decode($res['body'], true);
                if ($feedData && $feedData['status'] === 'success' && !empty($feedData['data'])) {
                    $post = $feedData['data'][array_rand($feedData['data'])];
                    $postId = $post['id'];
                    $authorId = $post['user_id'];
                    $authorName = $post['Name'];
                    $type = $post['activity_type'];

                    if ($authorId != $userId) {
                        // 1. Like
                        if (rand(1, 100) > 50) {
                            executeBotAction($baseUrl . "/api_social_feed.php", ['action' => 'toggle_like', 'feed_id' => $postId], $cFile, $isLocal);
                            echo "- Liked $authorName's post<br>";
                        }

                        // 2. Comment
                        if (rand(1, 100) > 70) {
                            $commentType = ($type == 'big_win' || $type == 'win') ? 'win' : 'lose';
                            $comment = $brain->generateComment($userId, $commentType);
                            executeBotAction($baseUrl . "/api_social_feed.php", ['action' => 'add_comment', 'feed_id' => $postId, 'comment_text' => $comment], $cFile, $isLocal);
                            echo "- Commented on $authorName's post: $comment<br>";
                        }

                        // 3. Add Friend (New: 30% chance if big_win)
                        if ($type == 'big_win' && rand(1, 100) > 70) {
                            executeBotAction($baseUrl . "/api_friends.php", ['action' => 'send_friend_request', 'friend_id' => $authorId], $cFile, $isLocal);
                            echo "- Sent friend request to idol $authorName after seeing big win<br>";
                        }
                    }
                }
            }
        }

        // N. Random Status Post (New: 2% chance)
        if (rand(1, 100) > 98) {
            $status = $brain->generateStatus($userId, 'random');
            $stmt = $conn->prepare("INSERT INTO social_feed (user_id, activity_type, message) VALUES (?, 'random', ?)");
            if ($stmt) {
                $stmt->bind_param("is", $userId, $status);
                $stmt->execute();
                $stmt->close();
            }
            echo "- Posted a random status update<br>";
        }

        // O. Daily Missions (New: 20% chance to check and claim)
        if (rand(1, 100) > 80) {
            $missionRes = executeBotAction($baseUrl . "/api_daily_missions.php?action=get_missions", null, $cFile, $isLocal);
            if ($missionRes['code'] == 200) {
                $missionData = json_decode($missionRes['body'], true);
                if ($missionData && $missionData['success'] && !empty($missionData['missions'])) {
                    foreach ($missionData['missions'] as $mission) {
                        if ($mission['is_completed'] && !$mission['is_claimed']) {
                            executeBotAction($baseUrl . "/api_daily_missions.php", ['action' => 'claim_reward', 'mission_id' => $mission['id']], $cFile, $isLocal);
                            echo "- Claimed reward for daily mission: {$mission['title']}<br>";
                        }
                    }
                }
            }
        }

        // P. Lucky Wheel (New: 20% chance to spin)
        if (rand(1, 100) > 80) {
            $wheelCheck = executeBotAction($baseUrl . "/api_lucky_wheel.php?action=check_spin", null, $cFile, $isLocal);
            if ($wheelCheck['code'] == 200) {
                $wheelData = json_decode($wheelCheck['body'], true);
                if ($wheelData && $wheelData['status'] === 'success' && !$wheelData['has_spun']) {
                    $spinRes = executeBotAction($baseUrl . "/api_lucky_wheel.php", ['action' => 'spin'], $cFile, $isLocal);
                    if ($spinRes['code'] == 200) {
                        $spinData = json_decode($spinRes['body'], true);
                        if ($spinData && $spinData['status'] === 'success') {
                            echo "- Spun the Lucky Wheel and won: {$spinData['reward']['reward_name']}<br>";
                        }
                    }
                }
            }
        }

        // Q. Trivia Game (New: 5% chance to play)
        if (rand(1, 100) > 95) {
            $startRes = executeBotAction($baseUrl . "/api_trivia.php", ['action' => 'start_game', 'total_questions' => 5], $cFile, $isLocal);
            $startData = json_decode($startRes['body'] ?? '', true);
            if ($startData && $startData['success']) {
                $gameId = $startData['game_id'];
                echo "- Started Trivia game #$gameId<br>";
                
                for ($i = 0; $i < 5; $i++) {
                    $qRes = executeBotAction($baseUrl . "/api_trivia.php?action=get_question&game_id=$gameId", null, $cFile, $isLocal);
                    $qData = json_decode($qRes['body'] ?? '', true);
                    if ($qData && $qData['success'] && isset($qData['question'])) {
                        $qId = $qData['question']['id'];
                        // 70% chance to pick a random answer A-D
                        $ans = ['A', 'B', 'C', 'D'][rand(0, 3)];
                        executeBotAction($baseUrl . "/api_trivia.php", ['action' => 'submit_answer', 'game_id' => $gameId, 'question_id' => $qId, 'answer' => $ans], $cFile, $isLocal);
                        usleep(100000); // 0.1s
                    }
                }
                executeBotAction($baseUrl . "/api_trivia.php", ['action' => 'finish_game', 'game_id' => $gameId], $cFile, $isLocal);
                echo "- Finished Trivia game #$gameId<br>";
            }
        }

        // R. PvP Challenge Response (New: 30% chance to check)
        if (rand(1, 100) > 70) {
            $pvpRes = executeBotAction($baseUrl . "/api_pvp_challenge.php?action=get_my_challenges&status=pending", null, $cFile, $isLocal);
            if ($pvpRes['code'] == 200) {
                $pvpData = json_decode($pvpRes['body'], true);
                if ($pvpData && $pvpData['success'] && !empty($pvpData['challenges'])) {
                    foreach ($pvpData['challenges'] as $challenge) {
                        // Only respond if we are the opponent
                        if ($challenge['opponent_id'] == $userId) {
                            $personality = $brain->getPersonality($userId);
                            $acceptChance = ($personality == 'shy') ? 10 : (($personality == 'aggressive') ? 90 : 50);
                            
                            if (rand(1, 100) <= $acceptChance && $userMoney >= $challenge['bet_amount']) {
                                // Accept
                                executeBotAction($baseUrl . "/api_pvp_challenge.php", ['action' => 'accept_challenge', 'challenge_id' => $challenge['id']], $cFile, $isLocal);
                                echo "- Accepted PvP challenge #{$challenge['id']} from {$challenge['challenger_name']}<br>";
                            }
                        }
                    }
                }
            }
            
            // Check accepted challenges to submit choice
            $pvpAccepted = executeBotAction($baseUrl . "/api_pvp_challenge.php?action=get_my_challenges&status=accepted", null, $cFile, $isLocal);
            if ($pvpAccepted['code'] == 200) {
                $pvpData = json_decode($pvpAccepted['body'], true);
                if ($pvpData && $pvpData['success'] && !empty($pvpData['challenges'])) {
                    foreach ($pvpData['challenges'] as $challenge) {
                        $isChallenger = ($challenge['challenger_id'] == $userId);
                        $hasSubmitted = $isChallenger ? !empty($challenge['challenger_choice']) : !empty($challenge['opponent_choice']);
                        
                        if (!$hasSubmitted) {
                            $choice = '';
                            switch ($challenge['game_type']) {
                                case 'coinflip': $choice = rand(0,1) ? 'heads' : 'tails'; break;
                                case 'dice': $choice = (string)rand(1,6); break;
                                case 'rps': $choice = ['rock', 'paper', 'scissors'][rand(0,2)]; break;
                                case 'number': $choice = (string)rand(1,100); break;
                            }
                            if ($choice) {
                                executeBotAction($baseUrl . "/api_pvp_challenge.php", ['action' => 'submit_choice', 'challenge_id' => $challenge['id'], 'choice' => $choice], $cFile, $isLocal);
                                echo "- Submitted choice '$choice' for PvP challenge #{$challenge['id']}<br>";
                            }
                        }
                    }
                }
            }
        }

        // S. Quests (New: 20% chance to check and claim)
        if (rand(1, 100) > 80) {
            foreach (['daily', 'weekly'] as $qType) {
                $questRes = executeBotAction($baseUrl . "/api_quests.php?action=get_quests&type=$qType", null, $cFile, $isLocal);
                if ($questRes['code'] == 200) {
                    $questData = json_decode($questRes['body'], true);
                    if ($questData && $questData['status'] === 'success' && !empty($questData['quests'])) {
                        foreach ($questData['quests'] as $quest) {
                            if ($quest['is_completed'] && !$quest['is_claimed']) {
                                executeBotAction($baseUrl . "/api_quests.php", ['action' => 'claim_reward', 'quest_id' => $quest['id'], 'quest_type' => $qType], $cFile, $isLocal);
                                echo "- Claimed reward for $qType quest: {$quest['title']}<br>";
                            }
                        }
                    }
                }
            }
        }

        // T. Events (New: 20% chance to check)
        if (rand(1, 100) > 80) {
            $eventRes = executeBotAction($baseUrl . "/api_events.php?action=get_list&status=active", null, $cFile, $isLocal);
            if ($eventRes['code'] == 200) {
                $eventData = json_decode($eventRes['body'], true);
                if ($eventData && $eventData['success'] && !empty($eventData['events'])) {
                    foreach ($eventData['events'] as $event) {
                        if (!$event['is_joined']) {
                            executeBotAction($baseUrl . "/api_events.php", ['action' => 'join', 'event_id' => $event['id']], $cFile, $isLocal);
                            echo "- Joined active event: {$event['title']}<br>";
                        }
                        
                        // Check for claimable rewards
                        if ($event['is_joined'] && $event['user_completed'] && !$event['user_claimed']) {
                            executeBotAction($baseUrl . "/api_events.php", ['action' => 'claim_reward', 'event_id' => $event['id']], $cFile, $isLocal);
                            echo "- Claimed reward for completed event: {$event['title']}<br>";
                        }
                    }
                }
            }
        }

        // U. Friend Request Handling (New: 20% chance to check)
        if (rand(1, 100) > 80) {
            $pendingRes = executeBotAction($baseUrl . "/api_friends.php?action=get_pending_requests", null, $cFile, $isLocal);
            if ($pendingRes['code'] == 200) {
                $pendingData = json_decode($pendingRes['body'], true);
                if ($pendingData && $pendingData['success'] && !empty($pendingData['requests'])) {
                    foreach ($pendingData['requests'] as $request) {
                        $requesterId = $request['Iduser'];
                        $requesterName = $request['Name'];
                        
                        $personality = $brain->getPersonality($userId);
                        $acceptChance = ($personality == 'friendly' || $personality == 'funny') ? 95 : 
                                       (($personality == 'shy') ? 70 : 30);
                        
                        if (rand(1, 100) <= $acceptChance) {
                            executeBotAction($baseUrl . "/api_friends.php", ['action' => 'accept_friend_request', 'friend_id' => $requesterId], $cFile, $isLocal);
                            echo "- Accepted friend request from $requesterName<br>";
                            
                            // Post a social comment if friendly
                            if ($personality == 'friendly' && rand(1, 100) > 50) {
                                $feedMsg = "Vừa kết bạn với @$requesterName, rất vui được làm quen nhé! 😊";
                                $stmt = $conn->prepare("INSERT INTO social_feed (user_id, activity_type, message) VALUES (?, 'friendship', ?)");
                                if ($stmt) {
                                    $stmt->bind_param("is", $userId, $feedMsg);
                                    $stmt->execute();
                                    $stmt->close();
                                }
                            }
                        } else {
                            // Optionally remove/reject (using remove_friend as it deletes the pending link)
                            if (rand(1, 100) > 70) {
                                executeBotAction($baseUrl . "/api_friends.php", ['action' => 'remove_friend', 'friend_id' => $requesterId], $cFile, $isLocal);
                                echo "- Rejected friend request from $requesterName (Personality: $personality)<br>";
                            }
                        }
                    }
                }
            }
        }

        usleep(500000); // 0.5s delay

        // ── DAILY REPORT (11 PM - Midnight) ──
        $reportKey = "daily_report_" . date('Y-m-d');
        if ($hour == 23 && !isset($state[$reportKey])) {
            $totalWins = $state['stats']['wins'];
            $totalLosses = $state['stats']['losses'];
            $reportMsg = "Tổng kết ngày hôm nay: Thắng $totalWins ván, Thua $totalLosses ván. " . 
                        ($totalWins > $totalLosses ? "Một ngày đại thắng! 😎" : "Thua keo này ta bày keo khác... 🧘");
            
            $stmt = $conn->prepare("INSERT INTO social_feed (user_id, activity_type, message) VALUES (?, 'daily_report', ?)");
            if ($stmt) {
                $stmt->bind_param("is", $userId, $reportMsg);
                $stmt->execute();
                $stmt->close();
                $state[$reportKey] = true;
                file_put_contents($sFile, json_encode($state), LOCK_EX);
            }
        }
        
        // ── AUTO LOGOUT ──
        executeBotAction($baseUrl . "/logout.php", null, $cFile, $isLocal);
        $cycleOutput .= "[$email] Cycle finished and logged out." . PHP_EOL;
    } else {
        echo "- Login Failed for " . htmlspecialchars($email) . "<br>";
        $cycleOutput .= "[$email] Login failed." . PHP_EOL;
    }
}
// -- WRITE CYCLE LOG --
$cycleOutput .= "-- BOT CYCLE END: " . date('Y-m-d H:i:s') . " --" . PHP_EOL . PHP_EOL;
@file_put_contents($currentCycleLogFile, $cycleOutput, FILE_APPEND);

echo "<hr>? Security & Social cycle completed. Log written to cycle_logs/";
