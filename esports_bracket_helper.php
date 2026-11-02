<?php
/**
 * ⚔️ ESPORTS TOURNAMENT BRACKET & PREDICTION ENGINE
 * 
 * Hệ thống Đấu Trường Giải Đấu Loại Trực Tiếp (Bracket Tree) & Sàn Dự Đoán Quán Quân:
 * - Tự động mở giải đấu Cờ Ca-rô AI định kỳ mỗi 30 phút một mùa giải.
 * - 8 Kỳ thủ AI hạt giống tranh tài qua 3 vòng: Tứ kết ➔ Bán kết ➔ Chung kết.
 * - Sàn Dự Đoán Quán Quân (Outright Betting) với Odds tự động, trả thưởng GTLM lớn.
 * - Đồng bộ thời gian thực chuẩn xác 100% cho mọi người chơi.
 */

if (!function_exists('initEsportsTournamentTables')) {
function initEsportsTournamentTables($conn) {
    $sql1 = "CREATE TABLE IF NOT EXISTS esports_tournaments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        season_id INT NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        game_type VARCHAR(50) DEFAULT 'caro_ai',
        status ENUM('betting', 'quarter_finals', 'semi_finals', 'finals', 'finished') DEFAULT 'betting',
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        champion_id VARCHAR(50) NULL,
        champion_name VARCHAR(100) NULL,
        total_bets_pool DECIMAL(15,2) DEFAULT 0,
        total_payout DECIMAL(15,2) DEFAULT 0,
        bracket_data LONGTEXT NULL,
        payout_settled TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_season (season_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $sql2 = "CREATE TABLE IF NOT EXISTS esports_predictions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tournament_id INT NOT NULL,
        season_id INT NOT NULL,
        user_id INT NOT NULL,
        user_name VARCHAR(100) NOT NULL,
        predicted_player_id VARCHAR(50) NOT NULL,
        predicted_player_name VARCHAR(100) NOT NULL,
        odds DECIMAL(6,2) NOT NULL,
        bet_amount DECIMAL(15,2) NOT NULL,
        payout_amount DECIMAL(15,2) DEFAULT 0,
        status ENUM('pending', 'won', 'lost') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_season (season_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $conn->query($sql1);
    $conn->query($sql2);
}
}

if (!function_exists('getEsportsPlayersList')) {
function getEsportsPlayersList() {
    return [
        'alphax' => [
            'id' => 'alphax',
            'name' => 'AlphaX',
            'title' => 'Đệ Nhất Khai Cuộc',
            'avatar' => '🔴',
            'color' => '#ef4444',
            'elo' => 2850,
            'seed' => 1,
            'odds' => 2.50,
            'style' => 'Tấn công dồn dập, ép biên thần tốc'
        ],
        'deepo' => [
            'id' => 'deepo',
            'name' => 'DeepO',
            'title' => 'Thần Toán Trung Cuộc',
            'avatar' => '⭕',
            'color' => '#3b82f6',
            'elo' => 2820,
            'seed' => 2,
            'odds' => 3.20,
            'style' => 'Tính toán sâu 12 nước cờ đôi'
        ],
        'zenzero' => [
            'id' => 'zenzero',
            'name' => 'ZenZero',
            'title' => 'Khiên Thần Ca-rô',
            'avatar' => '🟣',
            'color' => '#a855f7',
            'elo' => 2780,
            'seed' => 3,
            'odds' => 4.50,
            'style' => 'Bậc thầy phòng thủ & phản kích'
        ],
        'stockcaro' => [
            'id' => 'stockcaro',
            'name' => 'StockCaro',
            'title' => 'Pháo Đài Bất Bại',
            'avatar' => '🟢',
            'color' => '#10b981',
            'elo' => 2740,
            'seed' => 4,
            'odds' => 5.80,
            'style' => 'Kiểm soát trung lộ cực kỳ chắc chắn'
        ],
        'dragonai' => [
            'id' => 'dragonai',
            'name' => 'DragonAI',
            'title' => 'Hỏa Long Sát Chiêu',
            'avatar' => '🟡',
            'color' => '#fbbf24',
            'elo' => 2700,
            'seed' => 5,
            'odds' => 7.20,
            'style' => 'Thế cờ 3 quân phân nhánh hiểm hóc'
        ],
        'phantomx' => [
            'id' => 'phantomx',
            'name' => 'PhantomX',
            'title' => 'Bóng Ma Đảo Ngược',
            'avatar' => '⚪',
            'color' => '#94a3b8',
            'elo' => 2680,
            'seed' => 6,
            'odds' => 9.00,
            'style' => 'Lội ngược dòng phút chót ngoạn mục'
        ],
        'cyberbot' => [
            'id' => 'cyberbot',
            'name' => 'CyberBot',
            'title' => 'Tia Chớp Lượng Tử',
            'avatar' => '🟠',
            'color' => '#f97316',
            'elo' => 2640,
            'seed' => 7,
            'odds' => 11.50,
            'style' => 'Hạ cờ tốc hành trong 0.2 giây'
        ],
        'titango' => [
            'id' => 'titango',
            'name' => 'TitanGo',
            'title' => 'Thạch Bàn Trầm Ổn',
            'avatar' => '🟤',
            'color' => '#78716c',
            'elo' => 2610,
            'seed' => 8,
            'odds' => 15.00,
            'style' => 'Lì đòn, biến hóa khó đoán'
        ]
    ];
}
}

/**
 * 🎲 Hàm xác định người thắng trận dựa trên Elo và hạt giống mùa giải
 */
if (!function_exists('simulateMatchWinner')) {
function simulateMatchWinner($p1Id, $p2Id, $seasonId, $matchKey) {
    $players = getEsportsPlayersList();
    $p1 = $players[$p1Id] ?? null;
    $p2 = $players[$p2Id] ?? null;

    if (!$p1) return $p2Id;
    if (!$p2) return $p1Id;

    // Tính xác suất Elo: P(A thắng B) = 1 / (1 + 10^((EloB - EloA)/400))
    $prob1 = 1 / (1 + pow(10, ($p2['elo'] - $p1['elo']) / 400));

    // Tạo số ngẫu nhiên xác định theo hạt giống mùa giải để đồng bộ mọi người xem
    $hash = md5("match_{$seasonId}_{$matchKey}_{$p1Id}_{$p2Id}");
    $randVal = (hexdec(substr($hash, 0, 6)) % 10000) / 10000.0;

    return ($randVal < $prob1) ? $p1Id : $p2Id;
}
}

if (!function_exists('getEsportsCurrentSeasonInfo')) {
function getEsportsCurrentSeasonInfo($now = null) {
    if ($now === null) $now = time();
    $cycleSeconds = 1800; // 30 phút một mùa giải
    // Mốc khởi chạy MÙA #1 (Khởi đầu tính từ mùa đầu tiên khi ra mắt)
    $launchTimestamp = 1793628000;
    $launchBase = (int)floor($launchTimestamp / $cycleSeconds);
    $rawSeason = (int)floor($now / $cycleSeconds);

    $seasonNumber = max(1, ($rawSeason - $launchBase + 1));
    $elapsed = $now % $cycleSeconds;

    $seasonStartTime = date('Y-m-d H:i:s', $rawSeason * $cycleSeconds);
    $seasonEndTime = date('Y-m-d H:i:s', ($rawSeason + 1) * $cycleSeconds);

    return [
        'season_id' => $seasonNumber,
        'season_number' => $seasonNumber,
        'elapsed' => $elapsed,
        'start_time' => $seasonStartTime,
        'end_time' => $seasonEndTime,
        'cycle_seconds' => $cycleSeconds
    ];
}
}

/**
 * 🏛️ Lấy hoặc tạo Mùa Giải Đấu Trường Hiện Tại
 */
if (!function_exists('getCurrentEsportsTournament')) {
function getCurrentEsportsTournament($conn, $forceStage = null) {
    initEsportsTournamentTables($conn);

    $seasonInfo = getEsportsCurrentSeasonInfo();
    $seasonId = $seasonInfo['season_id'];
    $seasonNumber = $seasonInfo['season_number'];
    $elapsed = $seasonInfo['elapsed'];
    $seasonStartTime = $seasonInfo['start_time'];
    $seasonEndTime = $seasonInfo['end_time'];

    // Xác định trạng thái hiện tại theo tiến trình 30 phút hoặc session force
    $stageOverride = $forceStage ?? ($_SESSION['esports_force_stage'] ?? null);

    if ($stageOverride && in_array($stageOverride, ['betting', 'quarter_finals', 'semi_finals', 'finals', 'finished'])) {
        $currentStage = $stageOverride;
        if ($currentStage === 'betting') {
            $timeRemainingInStage = 600;
            $stageTitle = 'Mở Sàn Đặt Cược Dự Đoán Quán Quân';
        } elseif ($currentStage === 'quarter_finals') {
            $timeRemainingInStage = 480;
            $stageTitle = 'Đang Thi Đấu Vòng Tứ Kết (4 Trận)';
        } elseif ($currentStage === 'semi_finals') {
            $timeRemainingInStage = 360;
            $stageTitle = 'Đang Thi Đấu Vòng Bán Kết (2 Trận)';
        } elseif ($currentStage === 'finals') {
            $timeRemainingInStage = 240;
            $stageTitle = 'Trận Chung Kết Đỉnh Cao - Tranh Cúp Vàng';
        } else {
            $timeRemainingInStage = 120;
            $stageTitle = 'Đã Tìm Ra Quán Quân - Chuẩn Bị Mùa Mới';
        }
    } else {
        if ($elapsed < 600) {
            $currentStage = 'betting';
            $timeRemainingInStage = 600 - $elapsed;
            $stageTitle = 'Mở Sàn Đặt Cược Dự Đoán Quán Quân';
        } elseif ($elapsed < 1080) {
            $currentStage = 'quarter_finals';
            $timeRemainingInStage = 1080 - $elapsed;
            $stageTitle = 'Đang Thi Đấu Vòng Tứ Kết (4 Trận)';
        } elseif ($elapsed < 1440) {
            $currentStage = 'semi_finals';
            $timeRemainingInStage = 1440 - $elapsed;
            $stageTitle = 'Đang Thi Đấu Vòng Bán Kết (2 Trận)';
        } elseif ($elapsed < 1680) {
            $currentStage = 'finals';
            $timeRemainingInStage = 1680 - $elapsed;
            $stageTitle = 'Trận Chung Kết Đỉnh Cao - Tranh Cúp Vàng';
        } else {
            $currentStage = 'finished';
            $timeRemainingInStage = 1800 - $elapsed;
            $stageTitle = 'Đã Tìm Ra Quán Quân - Chuẩn Bị Mùa Mới';
        }
    }

    // Lấy thông tin từ database nếu đã có
    $stmt = $conn->prepare("SELECT * FROM esports_tournaments WHERE season_id = ?");
    $stmt->bind_param("i", $seasonId);
    $stmt->execute();
    $tour = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $players = getEsportsPlayersList();

    // Tính toán toàn bộ cây thi đấu (Bracket Tree)
    // 4 Trận Tứ Kết (Quarter-Finals)
    $qf1_winner = simulateMatchWinner('alphax', 'titango', $seasonId, 'qf1');
    $qf2_winner = simulateMatchWinner('stockcaro', 'dragonai', $seasonId, 'qf2');
    $qf3_winner = simulateMatchWinner('deepo', 'cyberbot', $seasonId, 'qf3');
    $qf4_winner = simulateMatchWinner('zenzero', 'phantomx', $seasonId, 'qf4');

    // 2 Trận Bán Kết (Semi-Finals)
    $sf1_winner = simulateMatchWinner($qf1_winner, $qf2_winner, $seasonId, 'sf1');
    $sf2_winner = simulateMatchWinner($qf3_winner, $qf4_winner, $seasonId, 'sf2');

    // 1 Trận Chung Kết (Grand Final)
    $final_winner = simulateMatchWinner($sf1_winner, $sf2_winner, $seasonId, 'final');
    $champion = $players[$final_winner];

    $isQfFinished = in_array($currentStage, ['semi_finals', 'finals', 'finished']);
    $isQfPlaying = ($currentStage === 'quarter_finals');

    $isSfFinished = in_array($currentStage, ['finals', 'finished']);
    $isSfPlaying = ($currentStage === 'semi_finals');

    $isFinalFinished = ($currentStage === 'finished');
    $isFinalPlaying = ($currentStage === 'finals');

    $bracketTree = [
        'qf' => [
            [
                'match_id' => 1,
                'round_name' => 'Tứ Kết 1',
                'title' => 'TỨ KẾT 1: AlphaX vs TitanGo',
                'p1' => $players['alphax'],
                'p2' => $players['titango'],
                'winner' => $isQfFinished ? $qf1_winner : null,
                'status' => $isQfFinished ? 'finished' : ($isQfPlaying ? 'playing' : 'upcoming'),
                'p1_score' => $isQfFinished ? ($qf1_winner === 'alphax' ? 1 : 0) : 0,
                'p2_score' => $isQfFinished ? ($qf1_winner === 'titango' ? 1 : 0) : 0
            ],
            [
                'match_id' => 2,
                'round_name' => 'Tứ Kết 2',
                'title' => 'TỨ KẾT 2: StockCaro vs DragonAI',
                'p1' => $players['stockcaro'],
                'p2' => $players['dragonai'],
                'winner' => $isQfFinished ? $qf2_winner : null,
                'status' => $isQfFinished ? 'finished' : ($isQfPlaying ? 'playing' : 'upcoming'),
                'p1_score' => $isQfFinished ? ($qf2_winner === 'stockcaro' ? 1 : 0) : 0,
                'p2_score' => $isQfFinished ? ($qf2_winner === 'dragonai' ? 1 : 0) : 0
            ],
            [
                'match_id' => 3,
                'round_name' => 'Tứ Kết 3',
                'title' => 'TỨ KẾT 3: DeepO vs CyberBot',
                'p1' => $players['deepo'],
                'p2' => $players['cyberbot'],
                'winner' => $isQfFinished ? $qf3_winner : null,
                'status' => $isQfFinished ? 'finished' : ($isQfPlaying ? 'playing' : 'upcoming'),
                'p1_score' => $isQfFinished ? ($qf3_winner === 'deepo' ? 1 : 0) : 0,
                'p2_score' => $isQfFinished ? ($qf3_winner === 'cyberbot' ? 1 : 0) : 0
            ],
            [
                'match_id' => 4,
                'round_name' => 'Tứ Kết 4',
                'title' => 'TỨ KẾT 4: ZenZero vs PhantomX',
                'p1' => $players['zenzero'],
                'p2' => $players['phantomx'],
                'winner' => $isQfFinished ? $qf4_winner : null,
                'status' => $isQfFinished ? 'finished' : ($isQfPlaying ? 'playing' : 'upcoming'),
                'p1_score' => $isQfFinished ? ($qf4_winner === 'zenzero' ? 1 : 0) : 0,
                'p2_score' => $isQfFinished ? ($qf4_winner === 'phantomx' ? 1 : 0) : 0
            ]
        ],
        'sf' => [
            [
                'match_id' => 5,
                'round_name' => 'Bán Kết 1',
                'title' => 'BÁN KẾT 1: ' . ($players[$qf1_winner]['name'] ?? 'TBD') . ' vs ' . ($players[$qf2_winner]['name'] ?? 'TBD'),
                'p1' => ($isQfFinished || $isSfPlaying) ? $players[$qf1_winner] : $players['alphax'],
                'p2' => ($isQfFinished || $isSfPlaying) ? $players[$qf2_winner] : $players['dragonai'],
                'winner' => $isSfFinished ? $sf1_winner : null,
                'status' => $isSfFinished ? 'finished' : ($isSfPlaying ? 'playing' : 'upcoming'),
                'p1_score' => $isSfFinished ? ($sf1_winner === $qf1_winner ? 1 : 0) : 0,
                'p2_score' => $isSfFinished ? ($sf1_winner === $qf2_winner ? 1 : 0) : 0
            ],
            [
                'match_id' => 6,
                'round_name' => 'Bán Kết 2',
                'title' => 'BÁN KẾT 2: ' . ($players[$qf3_winner]['name'] ?? 'TBD') . ' vs ' . ($players[$qf4_winner]['name'] ?? 'TBD'),
                'p1' => ($isQfFinished || $isSfPlaying) ? $players[$qf3_winner] : $players['deepo'],
                'p2' => ($isQfFinished || $isSfPlaying) ? $players[$qf4_winner] : $players['zenzero'],
                'winner' => $isSfFinished ? $sf2_winner : null,
                'status' => $isSfFinished ? 'finished' : ($isSfPlaying ? 'playing' : 'upcoming'),
                'p1_score' => $isSfFinished ? ($sf2_winner === $qf3_winner ? 1 : 0) : 0,
                'p2_score' => $isSfFinished ? ($sf2_winner === $qf4_winner ? 1 : 0) : 0
            ]
        ],
        'final' => [
            'match_id' => 7,
            'round_name' => 'Chung Kết',
            'title' => '👑 CHUNG KẾT VÔ ĐỊCH: ' . ($players[$sf1_winner]['name'] ?? 'TBD') . ' vs ' . ($players[$sf2_winner]['name'] ?? 'TBD'),
            'p1' => ($isSfFinished || $isFinalPlaying) ? $players[$sf1_winner] : $players['alphax'],
            'p2' => ($isSfFinished || $isFinalPlaying) ? $players[$sf2_winner] : $players['zenzero'],
            'winner' => $isFinalFinished ? $final_winner : null,
            'status' => $isFinalFinished ? 'finished' : ($isFinalPlaying ? 'playing' : 'upcoming'),
            'p1_score' => $isFinalFinished ? ($final_winner === $sf1_winner ? 1 : 0) : 0,
            'p2_score' => $isFinalFinished ? ($final_winner === $sf2_winner ? 1 : 0) : 0
        ],
        'champion' => $isFinalFinished ? $champion : null
    ];

    $title = "Đại Chiến Cờ Ca-rô AI Siêu Cấp - Mùa #" . $seasonNumber;
    $bracketJson = json_encode($bracketTree, JSON_UNESCAPED_UNICODE);

    if (!$tour) {
        $stmtInsert = $conn->prepare("INSERT INTO esports_tournaments (season_id, title, status, start_time, end_time, champion_id, champion_name, bracket_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $champId = $isFinalFinished ? $final_winner : null;
        $champName = $isFinalFinished ? $champion['name'] : null;
        $stmtInsert->bind_param("isssssss", $seasonId, $title, $currentStage, $seasonStartTime, $seasonEndTime, $champId, $champName, $bracketJson);
        $stmtInsert->execute();
        $tourDbId = $stmtInsert->insert_id;
        $stmtInsert->close();
    } else {
        $tourDbId = (int)$tour['id'];
        $stmtUpdate = $conn->prepare("UPDATE esports_tournaments SET status = ?, bracket_data = ?, champion_id = ?, champion_name = ? WHERE id = ?");
        $champId = ($currentStage === 'finished') ? $final_winner : null;
        $champName = ($currentStage === 'finished') ? $champion['name'] : null;
        $stmtUpdate->bind_param("ssssi", $currentStage, $bracketJson, $champId, $champName, $tourDbId);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }

    // Tự động thanh toán trả thưởng khi giải đấu kết thúc (finished)
    if ($currentStage === 'finished' && empty($tour['payout_settled'])) {
        settleEsportsPredictionsPayout($conn, $seasonId, $final_winner, $champion['name']);
        $conn->query("UPDATE esports_tournaments SET payout_settled = 1 WHERE season_id = $seasonId");
    }

    // Lấy tổng cược hiện tại của mùa này
    $stmtPool = $conn->prepare("SELECT IFNULL(SUM(bet_amount), 0) as total_pool, COUNT(*) as total_bets FROM esports_predictions WHERE season_id = ?");
    $stmtPool->bind_param("i", $seasonId);
    $stmtPool->execute();
    $poolData = $stmtPool->get_result()->fetch_assoc();
    $stmtPool->close();

    return [
        'id' => $tourDbId,
        'season_id' => $seasonId,
        'season_number' => $seasonNumber,
        'title' => $title,
        'stage' => $currentStage,
        'stage_title' => $stageTitle,
        'elapsed' => $elapsed,
        'seconds_remaining' => $timeRemainingInStage,
        'season_seconds_remaining' => 1800 - $elapsed,
        'start_time' => $seasonStartTime,
        'end_time' => $seasonEndTime,
        'total_pool' => (float)$poolData['total_pool'],
        'total_bets' => (int)$poolData['total_bets'],
        'bracket' => $bracketTree,
        'players' => array_values($players)
    ];
}
}

/**
 * 💰 Thanh toán trả thưởng cho khán giả dự đoán chính xác Quán Quân
 */
if (!function_exists('settleEsportsPredictionsPayout')) {
function settleEsportsPredictionsPayout($conn, $seasonId, $championId, $championName) {
    require_once __DIR__ . '/game_history_helper.php';

    // 1. Tìm tất cả dự đoán của mùa giải này
    $stmt = $conn->prepare("SELECT * FROM esports_predictions WHERE season_id = ? AND status = 'pending'");
    $stmt->bind_param("i", $seasonId);
    $stmt->execute();
    $bets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($bets)) return;

    foreach ($bets as $bet) {
        $betId = (int)$bet['id'];
        $userId = (int)$bet['user_id'];
        $betAmt = (float)$bet['bet_amount'];
        $odds = (float)$bet['odds'];

        if ($bet['predicted_player_id'] === $championId) {
            // Thắng cược!
            $payout = $betAmt * $odds;
            $conn->begin_transaction();
            try {
                $conn->query("UPDATE users SET Money = Money + $payout WHERE Iduser = $userId");
                $conn->query("UPDATE esports_predictions SET status = 'won', payout_amount = $payout WHERE id = $betId");

                if (function_exists('logGameHistory')) {
                    logGameHistory($conn, $userId, 'ESPORTS PREDICTION WIN', $betAmt, $payout, true);
                }
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
            }
        } else {
            // Thua cược
            $conn->query("UPDATE esports_predictions SET status = 'lost', payout_amount = 0 WHERE id = $betId");
            if (function_exists('logGameHistory')) {
                logGameHistory($conn, $userId, 'ESPORTS PREDICTION LOSE', $betAmt, 0, false);
            }
        }
    }

    // Bắn sự kiện realtime thông báo Quán Quân
    if (file_exists(__DIR__ . '/realtime/realtime_publisher.php')) {
        require_once __DIR__ . '/realtime/realtime_publisher.php';
        if (class_exists('RealtimePublisher')) {
            RealtimePublisher::publish('live_61', 'esports_champion_announced', [
                'season_id' => $seasonId,
                'champion_id' => $championId,
                'champion_name' => $championName,
                'message' => "🏆 CHÚC MỪNG KỲ THỦ [{$championName}] ĐÃ ĐOẠT CÚP VÔ ĐỊCH MÙA #{$seasonId}!"
            ]);
        }
    }
}
}

/**
 * 🎯 Đặt Cược Dự Đoán Quán Quân
 */
if (!function_exists('placeEsportsPrediction')) {
function placeEsportsPrediction($conn, $userId, $playerId, $amount) {
    $seasonInfo = getEsportsCurrentSeasonInfo();
    $seasonId = $seasonInfo['season_id'];
    $elapsed = $seasonInfo['elapsed'];

    // Chỉ cho phép đặt cược trong 10 phút đầu (0 - 600s) hoặc khi được chọn chế độ mở cược
    $stageOverride = $_SESSION['esports_force_stage'] ?? null;
    $isBettingStage = ($stageOverride === 'betting') || (!$stageOverride && $elapsed < 600);

    if (!$isBettingStage) {
        return [
            'success' => false,
            'message' => 'Đã hết thời gian đặt cược mùa giải này (Đang trong giai đoạn thi đấu). Vui lòng chọn "Đấu Lại Từ Đầu (Mở Cược)" hoặc chờ mùa giải kế tiếp sau ít phút nhé!'
        ];
    }

    $amount = (float)$amount;
    if ($amount < 1000) {
        return ['success' => false, 'message' => 'Mức cược dự đoán tối thiểu là 1,000 GTLM!'];
    }

    $players = getEsportsPlayersList();
    if (!isset($players[$playerId])) {
        return ['success' => false, 'message' => 'Kỳ thủ bạn chọn không hợp lệ!'];
    }

    $targetPlayer = $players[$playerId];
    $odds = (float)$targetPlayer['odds'];

    // Kiểm tra số dư người dùng
    $stmtUser = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
    $stmtUser->bind_param("i", $userId);
    $stmtUser->execute();
    $uRow = $stmtUser->get_result()->fetch_assoc();
    $stmtUser->close();

    if (!$uRow) {
        return ['success' => false, 'message' => 'Không tìm thấy thông tin tài khoản!'];
    }

    $currentMoney = (float)$uRow['Money'];
    $userName = $uRow['Name'] ?? 'Đạo Hữu';

    if ($currentMoney < $amount) {
        return [
            'success' => false,
            'message' => 'Số dư không đủ! Sếp cần thêm ' . number_format($amount - $currentMoney) . ' GTLM để đặt cược.'
        ];
    }

    $tour = getCurrentEsportsTournament($conn);
    $tourId = (int)$tour['id'];

    $conn->begin_transaction();
    try {
        // Trừ tiền cược
        $stmtDeduct = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
        $stmtDeduct->bind_param("di", $amount, $userId);
        $stmtDeduct->execute();
        $stmtDeduct->close();

        // Ghi nhận vé cược
        $stmtBet = $conn->prepare("INSERT INTO esports_predictions (tournament_id, season_id, user_id, user_name, predicted_player_id, predicted_player_name, odds, bet_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmtBet->bind_param("iiisssdd", $tourId, $seasonId, $userId, $userName, $playerId, $targetPlayer['name'], $odds, $amount);
        $stmtBet->execute();
        $betId = $stmtBet->insert_id;
        $stmtBet->close();

        // Cập nhật pool giải đấu
        $conn->query("UPDATE esports_tournaments SET total_bets_pool = total_bets_pool + $amount WHERE id = $tourId");

        require_once __DIR__ . '/game_history_helper.php';
        if (function_exists('logGameHistory')) {
            logGameHistory($conn, $userId, 'ESPORTS PREDICTION BET', $amount, 0, false);
        }

        $conn->commit();

        $newBalance = $currentMoney - $amount;
        $potentialPayout = $amount * $odds;

        return [
            'success' => true,
            'message' => "Dự đoán thành công! Sếp đã đặt " . number_format($amount) . " GTLM cho {$targetPlayer['name']} (Odds x{$odds}). Tiền thắng ước tính: " . number_format($potentialPayout) . " GTLM 🏆",
            'bet_id' => $betId,
            'new_balance' => number_format($newBalance),
            'potential_payout' => number_format($potentialPayout),
            'player_name' => $targetPlayer['name'],
            'odds' => $odds
        ];

    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => 'Lỗi xử lý cược: ' . $e->getMessage()];
    }
}
}

/**
 * 📋 Lấy danh sách cược của người dùng ở mùa hiện tại
 */
if (!function_exists('getUserEsportsPredictions')) {
function getUserEsportsPredictions($conn, $userId, $seasonId = null) {
    initEsportsTournamentTables($conn);

    if ($seasonId === null) {
        $now = time();
        $cycleSeconds = 1800;
        $seasonId = (int)floor($now / $cycleSeconds);
    }

    $stmt = $conn->prepare("SELECT * FROM esports_predictions WHERE user_id = ? AND season_id = ? ORDER BY id DESC");
    $stmt->bind_param("ii", $userId, $seasonId);
    $stmt->execute();
    $bets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $bets;
}
}
