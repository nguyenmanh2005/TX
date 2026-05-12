<?php
/**
 * 🛡️ Omni-Bot Engine v16.5 - Deep-Social Upgrade
 */

// 1. Load config & brain (Moved to top for better IDE support)
require_once __DIR__ . '/../db_connect.php'; 
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/bot_brain.php';
require_once __DIR__ . '/../game_history_helper.php';

// 0. Helpers & Error Handling
$currentBotEmail = "SYSTEM";
$currentCookieFile = __DIR__ . '/sessions/system.txt';

$inError = false;

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    global $currentBotEmail, $baseUrl, $currentCookieFile, $inError;
    if ($inError) return false;
    $inError = true;
    
    $severity = ($errno & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) ? "CRITICAL" : "WARNING";
    $msg = "[$severity] $errstr in " . basename($errfile) . ":$errline";
    
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
    
    $msg = "[CRITICAL] " . $e->getMessage() . " in " . basename($e->getFile()) . ":" . $e->getLine();
    writeBotLog($currentBotEmail, "ERROR", "PHP_EXCEPTION", $msg);
    if (isset($baseUrl) && file_exists($currentCookieFile)) {
        executeBotAction($baseUrl . "/chat2.php", ['message' => "🚨 EXCEPTION: $msg"], $currentCookieFile);
    }
    $inError = false;
});

function writeBotLog(string $email, string $level, string $action, string $details = "") {
    $logDir = __DIR__ . '/logs/';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $file = $logDir . date('Y-m-d') . '.log';
    $timestamp = date('H:i:s d/m');
    $logLine = "[$timestamp] [$level] [$email] $action" . ($details ? ": $details" : "") . PHP_EOL;
    @file_put_contents($file, $logLine, FILE_APPEND);
}

function recordEconomySnapshot(mysqli $conn) {
    $historyFile = __DIR__ . '/sessions/economy_history.json';
    $history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
    
    // Tổng GTLM
    $botRes = $conn->query("SELECT SUM(Money) as total FROM users WHERE Email REGEXP '^bot[0-9]+@'")->fetch_assoc();
    $totalBot = (float)($botRes['total'] ?? 0);
    $humanRes = $conn->query("SELECT SUM(Money) as total FROM users WHERE Email NOT REGEXP '^bot[0-9]+@'")->fetch_assoc();
    $totalHuman = (float)($humanRes['total'] ?? 0);
    
    // Thống kê mood
    $moodCounts = ['happy' => 0, 'excited' => 0, 'tilted' => 0, 'depressed' => 0];
    $sessionFiles = glob(__DIR__ . '/sessions/*.state.json');
    foreach ($sessionFiles as $file) {
        $state = json_decode(file_get_contents($file), true);
        $m = $state['mood'] ?? 'happy';
        if (isset($moodCounts[$m])) $moodCounts[$m]++;
    }

    $history[] = [
        'time' => date('H:i d/m'), 
        'full_date' => date('Y-m-d H:i:s'), // Thêm full_date để filter chính xác hơn
        'bot' => $totalBot, 
        'human' => $totalHuman,
        'moods' => $moodCounts
    ];
    
    if (count($history) > 50000) array_shift($history);
    file_put_contents($historyFile, json_encode($history));
}

$brain = new BotBrain();

$baseUrl = "http://127.0.0.1/1";
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
$nameRes = $conn->query("SELECT Iduser, Name, Email FROM users WHERE Email REGEXP '^bot[0-9]+@'");
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
    if (PHP_VERSION_ID < 80500) {
        $cc = 'curl_close';
        $cc($ch);
    }
    return json_decode($response ?? '', true);
}

// ── MAIN LOOP ──
function executeBotCycle(mysqli $conn, array $config, string $cookieDir, string $baseUrl, BotBrain $brain, array $botNameMap, array $availableGames) {
    header('X-Accel-Buffering: no'); // Disable buffering for real-time streaming
    echo "<body style='background:#020617; color:#f8fafc; font-family:sans-serif; padding:20px;'>";
    echo "<h1 style='color:#818cf8;'>🛡️ Bot Army Engine v16.1 (Omni-Access)</h1>";

    $allBots = $config['bot_emails'];
    shuffle($allBots);
    $maxBots = isset($_GET['max_bots']) ? (int)$_GET['max_bots'] : $config['settings']['max_bots_per_cycle'];
    $activeBots = array_slice($allBots, 0, $maxBots);

    // Load shared mentorship data
    $mentorFile = __DIR__ . '/sessions/mentorship.json';
    $mentors = file_exists($mentorFile) ? json_decode(file_get_contents($mentorFile), true) : [];

    $updateMoneyStmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");

    // Lấy số lượng người chơi đang online (hoạt động trong 5 phút qua)
    $onlineRes = $conn->query("SELECT COUNT(*) as count FROM users WHERE last_active > NOW() - INTERVAL 5 MINUTE");
    $userCount = $onlineRes ? (int)$onlineRes->fetch_assoc()['count'] : 0;

    foreach ($activeBots as $email) {
        // ... (rest of the code is unchanged but now inside the function)
        // Note: I'll use a large block for the replacement to ensure it's correct.
    // Enable flushing for real-time output
    if (ob_get_level() > 0) ob_end_flush();
    ob_start();
    
    $currentBotEmail = $email;
    $botMd5 = md5($email);
    $cFile = $cookieDir . $botMd5 . ".txt";
    $currentCookieFile = $cFile;
    $sFile = $cookieDir . $botMd5 . ".state.json";
    
    $state = file_exists($sFile) ? json_decode(file_get_contents($sFile), true) : [];
    
    // Ensure all keys exist
    $state['wins'] = $state['wins'] ?? 0;
    $state['win_streak'] = $state['win_streak'] ?? 0;
    $state['lose_streak'] = $state['lose_streak'] ?? 0;
    $state['recent_messages'] = $state['recent_messages'] ?? [];
    $state['last_maintenance'] = $state['last_maintenance'] ?? '';
    $state['mood'] = $state['mood'] ?? 'happy';
    $state['is_bad_day'] = $state['is_bad_day'] ?? false;
    $state['last_mood_update'] = $state['last_mood_update'] ?? '';
    $state['history'] = $state['history'] ?? [];

    // --- MODULE 0.0: Memory Layer ---
    $memFile = __DIR__ . "/sessions/" . $botMd5 . ".memory.json";
    $memory = file_exists($memFile) ? json_decode(file_get_contents($memFile), true) : ['known_users' => []];


    // --- MODULE 0: Login with Retry ---
    $res = null;
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $res = executeBotAction($baseUrl . "/login.php", ['email' => $email, 'password' => $config['bot_password']], $cFile);
        if (isset($res['status']) && $res['status'] == 'success') break;
        if ($attempt < 3) sleep(1);
    }
    
    if (!isset($res['status']) || $res['status'] !== 'success') {
        writeBotLog($email, "ERROR", "Login failed after 3 attempts");
        continue;
    }

    $userId = (int)$res['Iduser'];
    $userName = $res['Name'];
    $userMoney = (float)$res['Money'];
    $personality = $brain->getPersonality($userId, $email);
    $isAnnouncer = ($personality === 'announcer');
    $msg = "Đang dạo chơi quanh trận địa... 😊";
    $chosenGame = "trận địa";

    // --- MODULE 0.0: Announcer Tasks ---
    if ($isAnnouncer) {
        echo "<div style='background:rgba(79, 70, 229, 0.2); padding:15px; border-radius:12px; margin-bottom:12px; border:1px solid rgba(99, 102, 241, 0.3);'>";
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

        echo "<div style='background:rgba(30, 41, 59, 0.7); padding:15px; border-radius:12px; margin-bottom:12px; border:1px solid rgba(255,255,255,0.1);'>";
        echo "<b style='color:#38bdf8;'>🤖 Bot: $userName</b> <span style='font-size:10px; color:#64748b;'>($personality" . ($state['is_bad_day'] ? " - 🥀 Bad Day" : "") . ")</span><br>";

        // --- MODULE 1: Maintenance & Tasks ---
        if (($state['last_maintenance'] ?? '') !== $todayStr) {
            echo "🔧 <span style='color:#fbbf24; font-size:13px;'>Bảo trì: Daily Rewards, Battle Pass, Quests...</span><br>";
            executeBotAction($baseUrl . "/api_daily_reward.php", ['action' => 'claim'], $cFile);
            executeBotAction($baseUrl . "/api_lucky_wheel.php", ['action' => 'spin'], $cFile);

            // Nhận thưởng Battle Pass
            $bpRes = executeBotAction($baseUrl . "/api_battle_pass.php?action=get_status", null, $cFile);
            if (isset($bpRes['success']) && $bpRes['success']) {
                for ($i = 1; $i <= $bpRes['level']; $i++) {
                    if (!in_array($i, $bpRes['claimed'])) {
                        executeBotAction($baseUrl . "/api_battle_pass.php", ['action' => 'claim_reward', 'level' => $i], $cFile);
                        echo "🎁 <span style='color:#4ade80; font-size:12px;'>Bot vừa nhận thưởng Battle Pass cấp $i</span><br>";
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

        // --- MODULE 2: Games ---
        $mood = $state['mood'] ?? 'happy';
        
        // --- BROKE CHECK (Cháy túi) ---
        // Nâng ngưỡng cháy túi lên 500,000 để đảm bảo an toàn tài chính
        if ($userMoney < 500000) {
            $state['mood'] = 'broke';
            echo "💸 <span style='color:#fca5a5;'>Trạng thái: Cần tích lũy vốn (Dưới 500k)!</span> Nghỉ chơi game, đi lượm lặt...<br>";
            if (rand(1, 100) <= 60) {
                $begMsg = $brain->generateMessage($userId, 'begging');
                executeBotAction($baseUrl . "/chat.php", ['message' => $begMsg], $cFile);
            }
            if (rand(1, 100) <= 40) {
                executeBotAction($baseUrl . "/api_giftcode.php", ['action' => 'claim_random'], $cFile);
            }
            goto social_module; // Bỏ qua Module Game
        }
        
        // Mood-based game selection
        $filteredGames = $availableGames;
        
        // --- LOSE STREAK: Chuyển game nếu thua 2 ván liên tiếp ---
        if ($state['lose_streak'] >= 2 && !empty($state['history'])) {
            $lastGame = $state['history'][0]['game'];
            $filteredGames = array_filter($availableGames, function($g) use ($lastGame) {
                return $g !== $lastGame;
            });
            if (empty($filteredGames)) $filteredGames = $availableGames;
            echo "🔄 <span style='color:#a78bfa;'>Đổi game:</span> Thua quá, chuyển từ $lastGame sang game khác...<br>";
        }

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

        // Calculate Bet Percentage
        switch ($chosenLevel) {
            case 'light': $betPercent = rand(1, 3); break;
            case 'medium': $betPercent = rand(5, 10); break;
            case 'heavy': $betPercent = rand(15, 25); break;
            case 'all_in': $betPercent = rand(50, 100); break;
            default: $betPercent = 2;
        }

        $bet = floor($userMoney * ($betPercent / 100));
        if ($bet < 1000) $bet = 1000;
        if ($bet > $userMoney) $bet = $userMoney;

        echo "🎲 <span style='color:#38bdf8;'>Mức cược:</span> " . strtoupper($chosenLevel) . " (" . $betPercent . "% - " . number_format($bet) . " GTLM)<br>";

        // --- MENTORSHIP: Check for a mentor if losing ---
        $learningFrom = null;
        if ($state['lose_streak'] >= 3 && !empty($mentors)) {
            $learningFrom = $mentors[array_rand($mentors)];
            $chosenGame = $learningFrom['game'];
            echo "🎓 <span style='color:#60a5fa;'>Đang học hỏi:</span> Theo chân tiền bối <b>{$learningFrom['name']}</b> tại ván $chosenGame...<br>";
            
            if (rand(1, 100) <= 40) {
                $lMsg = $brain->generateMessage($userId, 'learning', ['mentor' => $learningFrom['name'], 'game' => $chosenGame]);
                executeBotAction($baseUrl . "/chat.php", ['message' => $lMsg], $cFile);
            }
        } else {
            $chosenGame = $filteredGames[array_rand($filteredGames)];
        }

        // --- TILTED LOGIC (Martingale fallback) ---
        if ($state['lose_streak'] >= 3 && $chosenLevel !== 'all_in') {
            $state['mood'] = 'tilted';
            if (rand(1, 100) <= 30) {
                $bet = min($userMoney, $bet * 2); // Martingale nhẹ
                echo "📈 <span style='color:#fbbf24;'>Gấp thếp nhẹ:</span> Quyết tâm gỡ gạc...<br>";
            }
        }
            
            // Chat chửi thề / than vãn
            if (rand(1, 100) <= 70) {
                $tMsg = $brain->generateMessage($userId, 'tilted_chat');
                executeBotAction($baseUrl . "/chat.php", ['message' => $tMsg], $cFile);
            }

        if ($bet < 1000) $bet = 1000;

        $isWin = (rand(0, 1) === 1);
        $winAmount = $isWin ? $bet : 0;
        if ($isWin) {
            $updateMoneyStmt->bind_param("di", $bet, $userId);
            $updateMoneyStmt->execute();
            $state['wins']++;
            $state['win_streak']++;
            $state['lose_streak'] = 0;
            $state['mood'] = 'excited';
            echo "💰 <span style='color:#4ade80;'>Húp " . number_format($bet) . " GTLM tại $chosenGame</span><br>";
            
            // --- MENTORSHIP: Become a mentor if winning big ---
            if ($state['win_streak'] >= 5) {
                $mentors[$userId] = ['name' => $userName, 'game' => $chosenGame, 'time' => time()];
                // Giới hạn 5 mentor mới nhất
                if (count($mentors) > 5) array_shift($mentors);
                file_put_contents($mentorFile, json_encode($mentors));
                
                if (rand(1, 100) <= 30) {
                    $tMsg = $brain->generateMessage($userId, 'teaching', ['game' => $chosenGame]);
                    executeBotAction($baseUrl . "/chat.php", ['message' => $tMsg], $cFile);
                }
            }

            $msgType = ($state['win_streak'] >= 3) ? 'streak_win' : 'win';
            $msg = $brain->generateMessage($userId, $msgType, ['amount' => $bet]);
        } else {
            $negativeBet = -$bet;
            $updateMoneyStmt->bind_param("di", $negativeBet, $userId);
            $updateMoneyStmt->execute();
            $state['lose_streak']++;
            $state['win_streak'] = 0;
            $state['mood'] = ($state['lose_streak'] >= 3) ? 'tilted' : 'depressed';
            echo "💸 <span style='color:#f87171;'>Bay màu " . number_format($bet) . " GTLM tại $chosenGame</span><br>";
            
            $msgType = ($state['lose_streak'] >= 3) ? 'streak_lose' : 'lose';
            $msg = $brain->generateMessage($userId, $msgType, ['amount' => $bet]);
        }

        // --- RECORD HISTORY ---
        logGameHistory($conn, $userId, $chosenGame, $bet, $winAmount, $isWin);
        
        // --- SYNC FOR RIVALRY ---
        $syncFile = __DIR__ . '/sessions/bot_sync.json';
        $syncData = file_exists($syncFile) ? json_decode(file_get_contents($syncFile), true) : [];
        $syncData[$email] = [
            'name' => $userName,
            'result' => $isWin ? 'win' : 'lose',
            'amount' => $isWin ? $winAmount : $bet,
            'time' => time()
        ];
        // Clean old sync data (> 15 min)
        foreach($syncData as $e => $d) { if(time() - $d['time'] > 900) unset($syncData[$e]); }
        file_put_contents($syncFile, json_encode($syncData));

        // Cập nhật history vào state (tối đa 10 ván)
        array_unshift($state['history'], [
            'game' => $chosenGame,
            'bet' => $bet,
            'result' => $isWin ? 'win' : 'lose',
            'time' => date('H:i d/m')
        ]);
        $state['history'] = array_slice($state['history'], 0, 10);
        
        social_module: 
        // --- MODULE 3: Social & Interaction ---
        if (rand(1, 100) <= 75) {
            $chatMessages = executeBotAction($baseUrl . "/chat.php?action=load", null, $cFile);
            $isReplied = false;

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
                            $mNameClean = $conn->real_escape_string($pName);
                            $memRes = $conn->query("SELECT interaction_count FROM bot_memory WHERE bot_id = $userId AND player_name = '$mNameClean'");
                            if ($memRes && $row = $memRes->fetch_assoc()) $memLevel = $row['interaction_count'];
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
                        $msg = "@$pName " . $msg;
                        $isReplied = true; break;
                    }
                }
            }

            // 1.5. Rivalry & Alliance Reactions
            if (!$isReplied && rand(1, 100) <= 50) {
                $syncData = file_exists($syncFile) ? json_decode(file_get_contents($syncFile), true) : [];
                $myRivals = []; $myAllies = [];
                foreach($config['rivalries'] as $pair) {
                    if($pair[0] == $email) $myRivals[] = $pair[1];
                    if($pair[1] == $email) $myRivals[] = $pair[0];
                }
                foreach($config['alliances'] as $pair) {
                    if($pair[0] == $email) $myAllies[] = $pair[1];
                    if($pair[1] == $email) $myAllies[] = $pair[0];
                }

                foreach($syncData as $otherEmail => $data) {
                    if($otherEmail == $email) continue;
                    if(time() - $data['time'] > 300) continue; // Only react to last 5 min

                    if(in_array($otherEmail, $myRivals)) {
                        $type = ($data['result'] == 'lose') ? 'rival_win' : null; // "Haha rival thua"
                        if($type) {
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

                // 1.5. Rivalry & Alliance Reactions
                $rivalStateFile = __DIR__ . '/sessions/rivalry_state.json';
                $rivalState = file_exists($rivalStateFile) ? json_decode(file_get_contents($rivalStateFile), true) : ['last_rotation' => 0, 'last_reactions' => []];
                
                // Rotate pairs every 10 min
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

                if (!$isReplied && rand(1, 100) <= 60) {
                    $syncData = file_exists($syncFile) ? json_decode(file_get_contents($syncFile), true) : [];
                    $myRivals = [];
                    foreach($rivalState['current_rivals'] ?? [] as $idx => $pair) {
                        if ($pair[0] == $email || $pair[1] == $email) {
                            $other = ($pair[0] == $email) ? $pair[1] : $pair[0];
                            // Check rate limit (1 per 10 min for this pair)
                            if (time() - ($rivalState['last_reactions'][$idx] ?? 0) > 600) {
                                $myRivals[$idx] = $other;
                            }
                        }
                    }

                    foreach($myRivals as $idx => $otherEmail) {
                        if (isset($syncData[$otherEmail]) && (time() - $syncData[$otherEmail]['time'] < 300)) {
                            $data = $syncData[$otherEmail];
                            $type = ($data['result'] == 'win') ? 'rival_win' : 'rival_lose';
                            $rMsg = $brain->getRivalryMessage($type, $data['name']);
                            executeBotAction($baseUrl . "/chat.php", ['message' => $rMsg], $cFile);
                            
                            $rivalState['last_reactions'][$idx] = time();
                            file_put_contents($rivalStateFile, json_encode($rivalState));
                            $isReplied = true; break;
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

            // 2. Guild Interactions
            $guildInfo = executeBotAction($baseUrl . "/api_guilds.php?action=get_info&guild_id=1", null, $cFile); // Giả định guild ID 1 là guild chính
            if (isset($guildInfo['guild'])) {
                if (!($guildInfo['is_member'] ?? false)) {
                    executeBotAction($baseUrl . "/api_guilds.php", ['action' => 'join', 'guild_id' => $guildInfo['guild']['id']], $cFile);
                } else {
                    // Chat trong guild (Sử dụng API mới api_guild_chat.php)
                    if (rand(1, 100) <= 20) {
                        $guildMsg = $isWin ? "Anh em Bang mình ơi, tôi vừa húp ngập mặt!" : "Mới bay màu xong, ai cứu tôi với...";
                        executeBotAction($baseUrl . "/api_guild_chat.php", ['action' => 'send', 'message' => $guildMsg], $cFile);
                    }
                }
            }

            // 3. PVP Challenges
            // Check incoming challenges
            $pvpChallenges = executeBotAction($baseUrl . "/api_pvp_challenge.php?action=get_my_challenges&status=pending", null, $cFile);
            if (isset($pvpChallenges['challenges'])) {
                foreach ($pvpChallenges['challenges'] as $challenge) {
                    if ($challenge['opponent_id'] == $userId) {
                        // Chấp nhận thách đấu
                        executeBotAction($baseUrl . "/api_pvp_challenge.php", ['action' => 'accept_challenge', 'challenge_id' => $challenge['id']], $cFile);
                        writeBotLog($email, "INFO", "PVP", "Accepted challenge from #{$challenge['challenger_id']}");
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
            
            // Chủ động thách đấu (chủ yếu bot aggressive và trietly thích đàm đạo võ nghệ)
            if (($personality == 'aggressive' || $personality == 'trietly') && rand(1, 100) <= 15) {
                $otherBots = array_values($botNameMap);
                $target = $otherBots[array_rand($otherBots)];
                $targetId = $target['id'];
                
                if ($targetId != $userId) {
                    $gameType = ['coinflip', 'dice', 'rps', 'number'][rand(0, 3)];
                    executeBotAction($baseUrl . "/api_pvp_challenge.php", [
                        'action' => 'create_challenge',
                        'opponent_id' => $targetId,
                        'game_type' => $gameType,
                        'bet_amount' => rand(5000, 20000)
                    ], $cFile);
                    writeBotLog($email, "INFO", "PVP", "Challenged bot #$targetId to $gameType");
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
        $eventRes = executeBotAction($baseUrl . "/api_events.php?action=get_list&status=active", null, $cFile);
        if (isset($eventRes['events'])) {
            foreach ($eventRes['events'] as $ev) {
                if (!$ev['is_joined']) {
                    executeBotAction($baseUrl . "/api_events.php", ['action' => 'join', 'event_id' => $ev['id']], $cFile);
                }
                if (isset($ev['user_completed']) && $ev['user_completed'] && isset($ev['user_claimed']) && !$ev['user_claimed']) {
                    executeBotAction($baseUrl . "/api_events.php", ['action' => 'claim_reward', 'event_id' => $ev['id']], $cFile);
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
            
            // Mua hàng (Nếu bot giàu)
            if (isset($listings['listings']) && !empty($listings['listings']) && rand(1, 100) <= 30) {
                $item = $listings['listings'][array_rand($listings['listings'])];
                if ($item['seller_id'] != $userId && $userMoney > $item['price'] * 3) {
                    executeBotAction($baseUrl . "/api_marketplace.php", ['action' => 'buy', 'item_id' => $item['id']], $cFile);
                    writeBotLog($email, "INFO", "Marketplace", "Bought {$item['item_name']} for " . number_format($item['price']));
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

        // --- MODULE 8.5: Weekly Goal ---
        if (date('w') == 1 && ($state['last_goal_post'] ?? '') !== $todayStr) {
            $goalMsg = $brain->generateMessage($userId, 'goal');
            executeBotAction($baseUrl . "/api_social_feed.php", ['action' => 'post', 'content' => $goalMsg], $cFile);
            $state['last_goal_post'] = $todayStr;
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

        file_put_contents($sFile, json_encode($state));
        file_put_contents($memFile, json_encode($memory));
        
        // --- MODULE 11: Cleanup & Logout ---
        executeBotAction($baseUrl . "/api_logout.php", null, $cFile);
        echo "</div>";
        ob_flush();
        flush();
    }

    // --- MODULE 12: Mega Spin Participation (Global) ---
    // Bot thỉnh thoảng tham gia Mega Spin để làm sôi động Pool
    if (rand(1, 100) <= 30) {
        $genericCFile = $cookieDir . "generic_system.txt";
        $msStatus = executeBotAction($baseUrl . "/api_megaspin.php?action=get_status", null, $genericCFile);
        if (isset($msStatus['success']) && $msStatus['success']) {
            // Chọn ngẫu nhiên 3-5 bot tham gia trong cycle này
            $randomBots = array_rand($botNameMap, min(5, count($botNameMap)));
            if (!is_array($randomBots)) $randomBots = [$randomBots];
            
            foreach ($randomBots as $bEmail) {
                if (rand(1, 100) <= 40) {
                    $bData = $botNameMap[$bEmail];
                    $bCFile = __DIR__ . '/sessions/' . md5($bEmail) . '.cookie';
                    $amounts = [1000, 5000, 10000, 50000];
                    $pick = $amounts[array_rand($amounts)];
                    
                    // Giả lập Login và Tham gia
                    executeBotAction($baseUrl . "/api_login.php", ['email' => $bEmail, 'password' => '123456'], $bCFile);
                    executeBotAction($baseUrl . "/api_megaspin.php", ['action' => 'join', 'amount' => $pick], $bCFile);
                    echo "🎰 <span style='color:var(--primary);'>Bot <b>{$bData['name']}</b> đã tham gia Mega Spin với $pick GTLM</span><br>";
                }
            }
        }
    }

    $updateMoneyStmt->close();
    recordEconomySnapshot($conn);
    echo "<hr>✨ Cycle Finished (Omni-Access v16.2).";
}

// Chạy Bot Engine
executeBotCycle($conn, $config, $cookieDir, $baseUrl, $brain, $botNameMap, $availableGames);
