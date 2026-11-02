<?php
/**
 * 🎒 Bot Inventory & Performance Analytics API
 * Returns all owned items and comprehensive win/loss metrics for a specific bot
 */
require_once '../db_connect.php';

header('Content-Type: application/json');

if (!isset($_GET['bot_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing bot_id']);
    exit;
}

$botId = (int)$_GET['bot_id'];

// 1. Get User Profile
$stmt = $conn->prepare("SELECT Iduser, Name, Email, Money, ImageURL FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $botId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userData) {
    echo json_encode(['status' => 'error', 'message' => 'Bot not found']);
    exit;
}

// 2. Get Themes
$themes = [];
$stmt = $conn->prepare("SELECT t.name, t.description FROM themes t INNER JOIN user_themes ut ON t.id = ut.theme_id WHERE ut.user_id = ?");
$stmt->bind_param("i", $botId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $themes[] = $row;
$stmt->close();

// 3. Get Cursors
$cursors = [];
$stmt = $conn->prepare("SELECT c.name, c.description FROM cursors c INNER JOIN user_cursors uc ON c.id = uc.cursor_id WHERE uc.user_id = ?");
$stmt->bind_param("i", $botId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $cursors[] = $row;
$stmt->close();

// 4. Get Chat Frames
$chatFrames = [];
$stmt = $conn->prepare("SELECT cf.frame_name as name FROM chat_frames cf INNER JOIN user_chat_frames ucf ON cf.id = ucf.chat_frame_id WHERE ucf.user_id = ?");
$stmt->bind_param("i", $botId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $chatFrames[] = $row;
$stmt->close();

// 5. Get Avatar Frames
$avatarFrames = [];
$stmt = $conn->prepare("SELECT af.frame_name as name FROM avatar_frames af INNER JOIN user_avatar_frames uaf ON af.id = uaf.avatar_frame_id WHERE uaf.user_id = ?");
$stmt->bind_param("i", $botId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $avatarFrames[] = $row;
$stmt->close();

// 6. Get Achievements
$achievements = [];
$stmt = $conn->prepare("SELECT a.name, a.icon, a.rarity FROM achievements a INNER JOIN user_achievements ua ON a.id = ua.achievement_id WHERE ua.user_id = ?");
$stmt->bind_param("i", $botId);
$stmt->execute();
$res = $stmt->get_result();
if ($res) {
    while ($row = $res->fetch_assoc()) $achievements[] = $row;
}
$stmt->close();

// 7. Session Data (wins, losses, history)
$emailMd5 = md5($userData['Email']);
$sFile = "sessions/" . $emailMd5 . ".state.json";
$state = [];
$sessionHistory = [];
if (file_exists($sFile)) {
    $state = json_decode(file_get_contents($sFile), true) ?: [];
    $sessionHistory = $state['history'] ?? [];
}

// 8. Performance Statistics (Database + Session Reconciliation)
$dbStats = null;
$dbMatches = [];
$checkTable = $conn->query("SHOW TABLES LIKE 'game_history'");
if ($checkTable && $checkTable->num_rows > 0) {
    $qStats = $conn->prepare("SELECT 
        COUNT(*) as total_games,
        COALESCE(SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END), 0) as total_wins,
        COALESCE(SUM(CASE WHEN is_win = 0 THEN 1 ELSE 0 END), 0) as total_losses,
        COALESCE(SUM(CASE WHEN is_win = 1 THEN win_amount ELSE 0 END), 0) as total_win_gtlm,
        COALESCE(SUM(CASE WHEN is_win = 0 THEN bet_amount ELSE 0 END), 0) as total_lose_gtlm,
        COALESCE(SUM(win_amount - bet_amount), 0) as net_profit_gtlm
    FROM game_history 
    WHERE user_id = ?");
    $qStats->bind_param("i", $botId);
    $qStats->execute();
    $dbStats = $qStats->get_result()->fetch_assoc();
    $qStats->close();

    $qMatches = $conn->prepare("SELECT game_name, bet_amount, win_amount, is_win, played_at 
        FROM game_history 
        WHERE user_id = ? 
        ORDER BY id DESC 
        LIMIT 20");
    $qMatches->bind_param("i", $botId);
    $qMatches->execute();
    $mRes = $qMatches->get_result();
    while ($m = $mRes->fetch_assoc()) {
        $isWin = (int)$m['is_win'] === 1;
        $bet = (float)$m['bet_amount'];
        $winAmt = (float)$m['win_amount'];
        $loseAmt = $isWin ? 0 : $bet;
        $profit = $isWin ? ($winAmt > 0 ? $winAmt - $bet : $bet) : -$bet;
        
        $dbMatches[] = [
            'game' => $m['game_name'],
            'bet' => $bet,
            'result' => $isWin ? 'win' : 'lose',
            'win_amount' => $isWin ? $winAmt : 0,
            'lose_amount' => $loseAmt,
            'profit' => $profit,
            'time' => date('H:i d/m', strtotime($m['played_at']))
        ];
    }
    $qMatches->close();
}

$totalWins = (int)($state['wins'] ?? 0);
$totalLosses = (int)($state['losses'] ?? 0);
$totalWinGTLM = 0;
$totalLoseGTLM = 0;

$matchList = [];
if (!empty($dbMatches)) {
    $matchList = $dbMatches;
    if ($dbStats && (int)$dbStats['total_games'] > 0) {
        $totalWins = max($totalWins, (int)$dbStats['total_wins']);
        $totalLosses = max($totalLosses, (int)$dbStats['total_losses']);
        $totalWinGTLM = (float)$dbStats['total_win_gtlm'];
        $totalLoseGTLM = (float)$dbStats['total_lose_gtlm'];
    }
}

// Fallback to session history if needed
if (empty($matchList) && !empty($sessionHistory)) {
    foreach ($sessionHistory as $sh) {
        $isWin = ($sh['result'] ?? '') === 'win';
        $bet = (float)($sh['bet'] ?? 0);
        $winAmt = $isWin ? ($bet > 0 ? $bet * 1.95 : 0) : 0;
        $loseAmt = $isWin ? 0 : $bet;
        $profit = $isWin ? ($winAmt > 0 ? $winAmt - $bet : $bet) : -$bet;
        
        $totalWinGTLM += $winAmt;
        $totalLoseGTLM += $loseAmt;
        if (!$isWin) $totalLosses++;

        $matchList[] = [
            'game' => $sh['game'] ?? 'Trận địa',
            'bet' => $bet,
            'result' => $sh['result'] ?? 'lose',
            'win_amount' => $winAmt,
            'lose_amount' => $loseAmt,
            'profit' => $profit,
            'time' => $sh['time'] ?? date('H:i d/m')
        ];
    }
}

// Recalculate totals from matchList if still 0
if ($totalWinGTLM == 0 && $totalLoseGTLM == 0 && !empty($matchList)) {
    foreach ($matchList as $m) {
        if ($m['result'] === 'win') {
            $totalWinGTLM += (float)($m['win_amount'] ?: $m['bet']);
        } else {
            $totalLoseGTLM += (float)($m['lose_amount'] ?: $m['bet']);
        }
    }
}

$totalGames = $totalWins + $totalLosses;
$winRate = $totalGames > 0 ? round(($totalWins / $totalGames) * 100, 1) : 0;
$netProfit = $totalWinGTLM - $totalLoseGTLM;

echo json_encode([
    'status' => 'success',
    'data' => [
        'bot' => [
            'id' => (int)$userData['Iduser'],
            'name' => $userData['Name'],
            'email' => $userData['Email'],
            'money' => (float)$userData['Money'],
            'image' => !empty($userData['ImageURL']) ? $userData['ImageURL'] : '../img/avatar_default.png',
            'mood' => $state['mood'] ?? 'happy'
        ],
        'stats' => [
            'total_games' => $totalGames,
            'total_wins' => $totalWins,
            'total_losses' => $totalLosses,
            'win_rate' => $winRate,
            'total_win_gtlm' => $totalWinGTLM,
            'total_lose_gtlm' => $totalLoseGTLM,
            'net_profit_gtlm' => $netProfit
        ],
        'themes' => $themes,
        'cursors' => $cursors,
        'chat_frames' => $chatFrames,
        'avatar_frames' => $avatarFrames,
        'achievements' => $achievements,
        'history' => $matchList
    ]
]);
