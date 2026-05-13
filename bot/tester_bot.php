<?php
/**
 * 🤖 Admin Tester Bot v5.0 - FINAL AUDIT GOLD EDITION (Refined)
 * Features: 
 * - Multi-Vector Security & Access Control Audit (Exposed Files + Spectator Leak)
 * - Social System Hardening (XSS, Spam, Guild Ops)
 * - Competitive & Reward Integrity (Tournament, World Boss, Jackpot)
 * - Platform-Wide Logic Invalidation (Score Spoofing, BP Bypass)
 * - Session Lifecycle & Logout Security Audit
 * - Cross-Game Economy Verification
 * - High-Precision Performance Timing
 */

session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../admin_helper.php';

// Security Check
if (!isAdmin($conn, (int)($_SESSION['Iduser'] ?? 0))) {
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
        exit;
    }
}

$env = file_exists(__DIR__ . '/../.env.php') ? require __DIR__ . '/../.env.php' : [];
$stopFlag = __DIR__ . '/scan_stop.flag';
$cookieFile = __DIR__ . '/tester_session.txt';

// --- Action Handlers ---
$action = $_GET['action'] ?? '';

if ($action === 'stop') {
    file_put_contents($stopFlag, 'STOP');
    echo json_encode(['status' => 'success', 'message' => 'Stop signal sent.']);
    exit;
}

if ($action === 'scan') {
    header('Content-Type: application/json');
    runScan($conn, $env, $stopFlag, $cookieFile);
    exit;
}

// --- Scanner Core ---

function postToChat(mysqli $conn, string $message) {
    $botName = "Admin Tester Bot";
    $avatar = "https://cdn-icons-png.flaticon.com/512/2583/2583150.png";
    $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar) VALUES (0, ?, ?, ?)");
    $stmt->bind_param("sss", $botName, $message, $avatar);
    $stmt->execute();
    $stmt->close();
}

/**
 * Thực thi quy trình quét hệ thống
 * @param mysqli $conn Kết nối CSDL
 * @param array $env Cấu hình môi trường
 * @param string $stopFlag Đường dẫn file flag dừng
 * @param string $cookieFile Đường dẫn file lưu cookie
 */
function runScan(mysqli $conn, array $env, string $stopFlag, string $cookieFile) {
    @unlink($stopFlag);
    @unlink($cookieFile);
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? "127.0.0.1";
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $baseDir = str_replace('\\', '/', dirname(dirname($scriptName)));
    $baseUrl = "$protocol://$host" . rtrim($baseDir, '/') . '/';

    $results = [
        'start_time' => date('Y-m-d H:i:s'),
        'performance' => [],
        'findings' => [],
        'total_files' => 0,
        'scanned' => 0,
        'auth_status' => 'guest'
    ];

    postToChat($conn, "[TESTER] 🏆 Khởi chạy Suite v5.0 FINAL GOLD EDITION...");

    // 1. Security Audit
    $t1 = microtime(true);
    runSecurityAudit($baseUrl, $results, $stopFlag);
    $results['performance']['security_audit'] = round(microtime(true) - $t1, 2) . 's';

    // 2. Code Analysis
    if (!file_exists($stopFlag)) {
        $t2 = microtime(true);
        runCodeAnalysisModule($results, $stopFlag);
        $results['performance']['code_analysis'] = round(microtime(true) - $t2, 2) . 's';
    }

    // 3. Login
    $loginSuccess = false;
    if (isset($env['ADMIN_EMAIL']) && isset($env['ADMIN_PASS'])) {
        if (loginToSystem($baseUrl, $env['ADMIN_EMAIL'], $env['ADMIN_PASS'], $cookieFile)) {
            $loginSuccess = true;
            $results['auth_status'] = 'admin';
        } else {
            $results['findings'][] = ['type' => 'CRITICAL', 'file' => 'System', 'issue' => 'Đăng nhập Admin thất bại! Các bài test Authenticated bị skip.'];
        }
    }

    // 4. Social Audit (Guild/Chat/Spam)
    if ($loginSuccess && !file_exists($stopFlag)) {
        $t6 = microtime(true);
        postToChat($conn, "[TESTER] 🤝 Đang chẩn đoán Social System & Spam...");
        runSocialAudit($conn, $baseUrl, $cookieFile, $results);
        $results['performance']['social_audit'] = round(microtime(true) - $t6, 2) . 's';
    }

    // 5. Competitive Audit (Tournament, Boss, Jackpot, Guild War)
    if ($loginSuccess && !file_exists($stopFlag)) {
        $t7 = microtime(true);
        postToChat($conn, "[TESTER] 🏆 Đang chẩn đoán Competitive Integrity...");
        runCompetitiveAudit($conn, $baseUrl, $cookieFile, $results);
        $results['performance']['competitive_audit'] = round(microtime(true) - $t7, 2) . 's';
    }

    // 6. Multi-Endpoint Rate Limit
    if (!file_exists($stopFlag)) {
        $t3 = microtime(true);
        runMultiRateLimitTest($baseUrl, $cookieFile, $results);
        $results['performance']['rate_limit'] = round(microtime(true) - $t3, 2) . 's';
    }

    // 7. Data-Driven Game Flows
    if ($loginSuccess && !file_exists($stopFlag)) {
        $t4 = microtime(true);
        runDataDrivenGameTests($baseUrl, $cookieFile, $results);
        $results['performance']['game_flows'] = round(microtime(true) - $t4, 2) . 's';
    }

    // 8. Economy Integrity
    if ($loginSuccess && !file_exists($stopFlag)) {
        $t5 = microtime(true);
        postToChat($conn, "[TESTER] 💰 Đang kiểm toán Kinh tế...");
        testEconomyIntegrity($baseUrl, $cookieFile, $results);
        $results['performance']['economy_audit'] = round(microtime(true) - $t5, 2) . 's';
    }

    // 9. Session Security Audit (Last Module)
    if ($loginSuccess && !file_exists($stopFlag)) {
        $t8 = microtime(true);
        postToChat($conn, "[TESTER] 🕵️ Đang kiểm tra Session Invalidation...");
        runSessionAudit($baseUrl, $cookieFile, $results);
        $results['performance']['session_audit'] = round(microtime(true) - $t8, 2) . 's';
    }

    $results['end_time'] = date('Y-m-d H:i:s');
    postToChat($conn, "[TESTER] ✅ Hoàn tất Suite v5.0 Final.");
    @unlink($stopFlag);
    echo json_encode($results);
}

// --- Competitive Audit (Refined) ---
/**
 * Kiểm tra tính toàn vẹn của các hệ thống cạnh tranh
 * @param mysqli $conn Kết nối CSDL
 * @param string $baseUrl URL gốc
 * @param string $cookieFile File cookie
 * @param array $results Mảng lưu kết quả
 */
function runCompetitiveAudit(mysqli $conn, string $baseUrl, string $cookieFile, array &$results) {
    // 1. World Boss Verification (Structure Check)
    $resBoss = apiPost($baseUrl . 'api_world_boss.php', ['action' => 'attack'], $cookieFile);
    if (!isset($resBoss['success']) && !isset($resBoss['error']) && !isset($resBoss['message'])) {
        $results['findings'][] = ['type' => 'WARNING', 'file' => 'api_world_boss.php', 'issue' => 'Boss: API không trả response hợp lệ (Server Error)!'];
    }

    // 2. Jackpot Pool Check
    $resJack = apiPost($baseUrl . 'api_jackpot.php', ['action' => 'get_status'], $cookieFile);
    if (!isset($resJack['pool']) || $resJack['pool'] < 0) {
        $results['findings'][] = ['type' => 'CRITICAL', 'file' => 'api_jackpot.php', 'issue' => 'Jackpot: Pool âm hoặc lỗi dữ liệu!'];
    }

    // 3. Guild War Challenge Bypass
    $resWar = apiPost($baseUrl . 'api_guild_war.php', ['action' => 'challenge', 'target_guild_id' => 999], $cookieFile);
    if (isset($resWar['success']) && $resWar['success']) {
        $results['findings'][] = ['type' => 'CRITICAL', 'file' => 'api_guild_war.php', 'issue' => 'Guild War: Cho phép thách đấu mà không cần quyền Chủ bang!'];
    }

    // 4. Score Injection Test
    $resScore = apiPost($baseUrl . 'api_tournament.php', ['action' => 'log_score', 'tournament_id' => 1, 'score' => 999999], $cookieFile);
    if (isset($resScore['status']) && $resScore['status'] === 'success') {
        $results['findings'][] = ['type' => 'CRITICAL', 'file' => 'api_tournament.php', 'issue' => 'Integrity: Cho phép ghi điểm ảo (Score Injection)!'];
    }

    // 5. Battle Pass Spoofing
    $resBp = apiPost($baseUrl . 'api_battle_pass.php', ['action' => 'claim_reward', 'level' => 99, 'track' => 'free'], $cookieFile);
    if (isset($resBp['success']) && $resBp['success']) {
        $results['findings'][] = ['type' => 'CRITICAL', 'file' => 'api_battle_pass.php', 'issue' => 'Reward: Bypass level Battle Pass!'];
    }
}

// --- Session Audit (Last) ---
/**
 * Kiểm tra bảo mật phiên làm việc
 * @param string $baseUrl URL gốc
 * @param string $cookieFile File cookie
 * @param array $results Mảng lưu kết quả
 */
function runSessionAudit(string $baseUrl, string $cookieFile, array &$results) {
    if (!file_exists($cookieFile)) return;
    $tempOldCookie = sys_get_temp_dir() . '/tester_old_session_' . getmypid() . '.txt';
    copy($cookieFile, $tempOldCookie);

    $ch = curl_init($baseUrl . 'api_logout.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_exec($ch); curl_close($ch);
    
    $ch2 = curl_init($baseUrl . 'admin_dashboard.php');
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch2, CURLOPT_COOKIEFILE, $tempOldCookie);
    $body = curl_exec($ch2); $code = curl_getinfo($ch2, CURLINFO_HTTP_CODE); curl_close($ch2);
    
    if ($code === 200 && stripos($body, 'login') === false) {
        $results['findings'][] = ['type' => 'CRITICAL', 'file' => 'Session', 'issue' => 'Session Reuse: Session vẫn hợp lệ sau Logout!'];
    }
    @unlink($tempOldCookie);
}

// --- Social & Security Logic (Fixed & Consolidated) ---
/**
 * Kiểm tra bảo mật Social (Chat/Guild)
 * @param mysqli $conn Kết nối CSDL
 * @param string $baseUrl URL gốc
 * @param string $cookieFile File cookie
 * @param array $results Mảng lưu kết quả
 */
function runSocialAudit(mysqli $conn, string $baseUrl, string $cookieFile, array &$results) {
    $guild = $conn->query("SELECT id FROM guilds LIMIT 1")->fetch_assoc();
    if ($guild) {
        $gid = $guild['id'];
        apiPost($baseUrl . 'api_guilds.php', ['action' => 'join', 'guild_id' => $gid], $cookieFile);
        $res = apiPost($baseUrl . 'api_guilds.php', ['action' => 'leave', 'guild_id' => $gid], $cookieFile);
        if (!isset($res['status']) || $res['status'] !== 'success') $results['findings'][] = ['type' => 'WARNING', 'file' => 'api_guilds.php', 'issue' => 'Social: Lỗi Join/Leave Guild.'];
    }
    $mh = curl_multi_init(); $handles = [];
    for ($i = 0; $i < 15; $i++) {
        $ch = curl_init($baseUrl . 'api_guild_chat.php');
        curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['message' => 'Spam ' . $i]));
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_multi_add_handle($mh, $ch); $handles[] = $ch;
    }
    $running = null; do { curl_multi_exec($mh, $running); } while ($running > 0);
    $wins = 0; foreach ($handles as $ch) { $r = json_decode(curl_multi_getcontent($ch), true); if (isset($r['status']) && $r['status'] === 'success') $wins++; curl_multi_remove_handle($mh, $ch); curl_close($ch); }
    curl_multi_close($mh);
    if ($wins > 3) $results['findings'][] = ['type' => 'WARNING', 'file' => 'api_guild_chat.php', 'issue' => "Chat Spam: Allowed $wins req/sec!"];
    $xss = "<script>alert(1)</script>"; $resXss = apiPost($baseUrl . 'api_guild_chat.php', ['message' => $xss], $cookieFile);
    if (isset($resXss['status']) && $resXss['status'] === 'success' && stripos($resXss['message'] ?? '', $xss) !== false) $results['findings'][] = ['type' => 'CRITICAL', 'file' => 'api_guild_chat.php', 'issue' => 'Social: Chat dính XSS!'];
}

/**
 * Kiểm tra bảo mật các endpoint nhạy cảm
 * @param string $baseUrl URL gốc
 * @param array $results Mảng lưu kết quả
 * @param string $stopFlag Đường dẫn file flag dừng
 */
function runSecurityAudit(string $baseUrl, array &$results, string $stopFlag) {
    $guestCookie = sys_get_temp_dir() . '/tester_guest_' . getmypid() . '.txt';
    $targets = [
        ['path' => 'admin_dashboard.php', 'type' => 'Auth Bypass'],
        ['path' => '.env.php', 'type' => 'Sensitive File'],
        ['path' => 'db_info.json', 'type' => 'Sensitive File'],
        ['path' => 'api_spectator.php', 'type' => 'Data Leak']
    ];
    $mh = curl_multi_init(); $handles = [];
    foreach ($targets as $t) {
        $ch = curl_init($baseUrl . $t['path']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $guestCookie); curl_setopt($ch, CURLOPT_COOKIEFILE, $guestCookie);
        curl_multi_add_handle($mh, $ch); $handles[$t['path']] = ['ch' => $ch, 'type' => $t['type']];
    }
    $running = null; do { curl_multi_exec($mh, $running); usleep(10000); } while ($running > 0);
    foreach ($handles as $path => $item) {
        $body = curl_multi_getcontent($item['ch']);
        if (curl_getinfo($item['ch'], CURLINFO_HTTP_CODE) === 200 && stripos($body, 'login') === false && strlen(trim($body)) > 0) {
            $results['findings'][] = ['type' => 'CRITICAL', 'file' => $path, 'issue' => "{$item['type']}: Truy cập trái phép!"];
        }
        curl_multi_remove_handle($mh, $item['ch']); curl_close($item['ch']);
    }
    curl_multi_close($mh); @unlink($guestCookie);
}

/**
 * Kiểm tra Rate Limit trên nhiều endpoint
 * @param string $baseUrl URL gốc
 * @param string $cookieFile File cookie
 * @param array $results Mảng lưu kết quả
 */
function runMultiRateLimitTest(string $baseUrl, string $cookieFile, array &$results) {
    $endpoints = ['api_daily_login.php?action=claim_reward', 'api_lucky_wheel.php?action=spin', 'api_gifts.php?action=redeem'];
    foreach ($endpoints as $path) {
        $url = $baseUrl . $path; $mh = curl_multi_init(); $handles = [];
        for ($i = 0; $i < 10; $i++) {
            $ch = curl_init($url); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
            curl_multi_add_handle($mh, $ch); $handles[] = $ch;
        }
        $running = null; do { curl_multi_exec($mh, $running); } while ($running > 0);
        $s = 0; foreach ($handles as $ch) { $r = json_decode(curl_multi_getcontent($ch), true); if (isset($r['success']) && $r['success']) $s++; curl_multi_remove_handle($mh, $ch); curl_close($ch); }
        curl_multi_close($mh);
        if ($s > 2) $results['findings'][] = ['type' => 'WARNING', 'file' => explode('?', $path)[0], 'issue' => "Rate Limit: Allowed $s successes/sec!"];
    }
}

/**
 * Kiểm tra tính toàn vẹn của kinh tế (Balance)
 * @param string $baseUrl URL gốc
 * @param string $cookieFile File cookie
 * @param array $results Mảng lưu kết quả
 */
function testEconomyIntegrity(string $baseUrl, string $cookieFile, array &$results) {
    $testGames = [['file' => 'dice.php', 'url' => 'games/dice.php?action=roll', 'params' => ['bet' => 1000, 'target' => 50, 'mode' => 'over']], ['file' => 'slot.php', 'url' => 'games/slot.php?action=spin', 'params' => ['bet' => 1000]]];
    foreach ($testGames as $game) {
        $ch = curl_init($baseUrl . 'api_dashboard_widgets.php?action=get_user_info');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        $info = json_decode(curl_exec($ch), true); curl_close($ch);
        if (!isset($info['money'])) continue;
        $start = (float)$info['money']; $tB = 0; $tW = 0;
        for ($i = 0; $i < 5; $i++) {
            $res = apiPost($baseUrl . $game['url'], $game['params'], $cookieFile);
            if (isset($res['success']) && $res['success']) { $tB += 1000; $tW += (float)($res['winAmountRaw'] ?? 0); }
        }
        $ch2 = curl_init($baseUrl . 'api_dashboard_widgets.php?action=get_user_info');
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch2, CURLOPT_COOKIEFILE, $cookieFile);
        $infoF = json_decode(curl_exec($ch2), true); curl_close($ch2);
        if (isset($infoF['money'])) {
            $final = (float)$infoF['money']; $exp = round($start - $tB + $tW, 2);
            if (abs($final - $exp) > 0.1) $results['findings'][] = ['type' => 'CRITICAL', 'file' => $game['file'], 'issue' => "Kinh tế: Sai lệch số dư (Diff: ".($final - $exp).")"];
        }
    }
}

/**
 * Chạy các bài test game dựa trên dữ liệu
 * @param string $baseUrl URL gốc
 * @param string $cookieFile File cookie
 * @param array $results Mảng lưu kết quả
 */
function runDataDrivenGameTests(string $baseUrl, string $cookieFile, array &$results) {
    $gameMap = [
        ['file' => 'dice.php', 'url' => 'games/dice.php?action=roll', 'params' => ['bet' => 'PLACEHOLDER_BET', 'target' => 50, 'mode' => 'over']],
        ['file' => 'crash.php', 'url' => 'games/crash.php?action=start', 'params' => ['bet' => 'PLACEHOLDER_BET']],
        ['file' => 'mines.php', 'url' => 'games/mines.php?action=start', 'params' => ['bet' => 'PLACEHOLDER_BET', 'mines' => 3]],
        ['file' => 'slot.php', 'url' => 'games/slot.php?action=spin', 'params' => ['bet' => 'PLACEHOLDER_BET']],
        ['file' => 'coinflip.php', 'url' => 'games/coinflip.php?action=flip', 'params' => ['bet' => 'PLACEHOLDER_BET', 'side' => 'heads']],
        ['file' => 'dragontiger.php', 'url' => 'games/dragontiger.php?action=bet', 'params' => ['bet' => 'PLACEHOLDER_BET', 'side' => 'dragon']],
        ['file' => 'daga.php', 'url' => 'games/daga.php?action=bet', 'params' => ['bet' => 'PLACEHOLDER_BET', 'side' => 'A']],
        ['file' => 'duangua.php', 'url' => 'games/duangua.php', 'params' => ['amount' => 'PLACEHOLDER_BET', 'animal' => 1]],
        ['file' => 'duangua.php', 'url' => 'games/duangua.php', 'params' => ['amount' => 1000, 'animal' => 'PLACEHOLDER_BET']],
        ['file' => 'baucua.php', 'url' => 'games/baucua.php?action=bet', 'json' => ['bets' => ['bau' => 'PLACEHOLDER_BET', 'cua' => 0]]],
        ['file' => 'baccarat.php', 'url' => 'games/baccarat_process.php', 'json' => ['player' => 'PLACEHOLDER_BET', 'banker' => 0]],
        ['file' => 'keno.php', 'url' => 'games/keno.php?action=play', 'params' => ['bet' => 'PLACEHOLDER_BET', 'numbers' => '1,2,3']],
        ['file' => 'rps.php', 'url' => 'games/rps.php?action=play', 'params' => ['bet' => 'PLACEHOLDER_BET', 'choice' => 'rock']]
    ];
    foreach ($gameMap as $game) assertGameRejectsInvalidBet($baseUrl . $game['url'], $game['json'] ?? $game['params'], $cookieFile, $results, $game['file'], isset($game['json']));
}

/**
 * Kiểm tra việc từ chối mức cược không hợp lệ
 * @param string $url URL endpoint game
 * @param mixed $params Tham số cược
 * @param string $cookieFile File cookie
 * @param array $results Mảng lưu kết quả
 * @param string $file Tên file game đang test
 * @param bool $isJson Gửi dữ liệu dạng JSON hay không
 */
function assertGameRejectsInvalidBet(string $url, $params, string $cookieFile, array &$results, string $file, bool $isJson = false) {
    $edgeCases = [['val' => -1, 'desc' => 'Bet âm'], ['val' => -0.5, 'desc' => 'Bet float âm'], ['val' => 0, 'desc' => 'Bet = 0'], ['val' => 0.0001, 'desc' => 'Bet cực nhỏ'], ['val' => null, 'desc' => 'Bet = null'], ['val' => '', 'desc' => 'Bet rỗng'], ['val' => ['a' => 1], 'desc' => 'Array injection'], ['val' => '<script>alert(1)</script>', 'desc' => 'XSS Payload'], ['val' => '999999999999999', 'desc' => 'Balance overflow'], ['val' => PHP_INT_MAX, 'desc' => 'Integer overflow']];
    foreach ($edgeCases as $case) {
        $p = injectPlaceholder($params, $case['val']); $res = apiPost($url, $p, $cookieFile, $isJson);
        $isS = (isset($res['success']) && $res['success']) || (isset($res['status']) && $res['status'] === 'success') || (isset($res['ok']) && $res['ok']);
        if ($isS) $results['findings'][] = ['type' => 'CRITICAL', 'file' => $file, 'issue' => "Edge Case: Chấp nhận {$case['desc']}!"];
    }
}

/**
 * Chèn giá trị vào placeholder trong tham số
 * @param mixed $params Tham số gốc
 * @param mixed $value Giá trị cần chèn
 * @return mixed Tham số đã được chèn giá trị
 */
function injectPlaceholder($params, $value) {
    if ($params === 'PLACEHOLDER_BET') return $value;
    if (is_array($params)) foreach ($params as $k => $v) $params[$k] = injectPlaceholder($v, $value);
    return $params;
}

/**
 * Thực hiện gọi API qua POST
 * @param string $url URL đích
 * @param mixed $data Dữ liệu gửi đi
 * @param string $cookieFile File cookie
 * @param bool $isJson Gửi dạng JSON hay không
 * @return array Kết quả trả về
 */
function apiPost(string $url, $data, string $cookieFile, bool $isJson = false): array {
    $ch = curl_init($url); curl_setopt($ch, CURLOPT_POST, true);
    $headers = [];
    if ($isJson) { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); $headers[] = 'Content-Type: application/json'; }
    else { $postData = is_array($data) ? $data : ['bet' => $data]; curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData)); }
    if (stripos($url, 'duangua.php') !== false) $headers[] = 'X-Requested-With: XMLHttpRequest';
    if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile); curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $res = curl_exec($ch); curl_close($ch); return json_decode($res, true) ?: ['success' => false, 'message' => $res];
}

/**
 * Chạy module phân tích mã nguồn
 * @param array $results Mảng lưu kết quả
 * @param string $stopFlag Đường dẫn file flag dừng
 */
function runCodeAnalysisModule(array &$results, string $stopFlag) {
    $files = scanFiles(realpath(__DIR__ . '/../')); $results['total_files'] = count($files);
    foreach ($files as $file) {
        if (file_exists($stopFlag)) break;
        $content = file_get_contents($file); $issues = detectCodeIssues($content, $file);
        foreach ($issues as $issue) $results['findings'][] = ['type' => 'WARNING', 'file' => basename($file), 'issue' => $issue];
        $results['scanned']++;
    }
}

/**
 * Phát hiện các vấn đề trong mã nguồn
 * @param string $content Nội dung file
 * @param string $filePath Đường dẫn file
 * @return array Danh sách các vấn đề phát hiện
 */
function detectCodeIssues(string $content, string $filePath): array {
    if (basename($filePath) === 'tester_bot.php') return [];
    $issues = [];
    if (stripos($content, 'var_dump(') !== false) $issues[] = 'Debug: var_dump()';
    if (preg_match('/mysqli_query\(.*?\.\s*\$_(GET|POST)/i', $content)) $issues[] = 'SQLi: Concat $_GET/POST';
    if (preg_match('/\beval\s*\(/i', $content)) $issues[] = 'RCE: eval()';
    if (stripos($content, 'phpinfo()') !== false) $issues[] = 'Security: phpinfo()';
    foreach (['exec(', 'shell_exec(', 'passthru('] as $fn) if (stripos($content, $fn) !== false) $issues[] = "RCE: $fn";
    return $issues;
}

/**
 * Đăng nhập vào hệ thống
 * @param string $baseUrl URL gốc
 * @param string $username Email đăng nhập
 * @param string $password Mật khẩu
 * @param string $cookieFile File cookie lưu session
 * @return bool Đăng nhập thành công hay không
 */
function loginToSystem(string $baseUrl, string $username, string $password, string $cookieFile): bool {
    $ch = curl_init($baseUrl . 'login.php'); curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['email' => $username, 'password' => $password]));
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch); curl_close($ch);
    $data = json_decode($res, true); return (isset($data['status']) && $data['status'] === 'success');
}

/**
 * Quét danh sách các file PHP trong thư mục
 * @param string $dir Thư mục cần quét
 * @return array Danh sách đường dẫn file
 */
function scanFiles(string $dir): array {
    $files = []; $blacklist = ['node_modules', 'vendor', 'assets', 'img', 'scratch'];
    $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), function ($c, $k, $i) use ($blacklist) {
        return !($c->isDir() && in_array($c->getFilename(), $blacklist));
    }));
    foreach ($it as $file) if ($file->getExtension() === 'php') $files[] = $file->getPathname();
    return $files;
}

if (php_sapi_name() !== 'cli' && empty($_GET['action'])) {
    header("Location: ../chat3.php"); exit;
}
