<?php
/**
 * ⚔️ ESPORTS TOURNAMENT BRACKET & PREDICTION ENGINE
 * 
 * Hệ thống Đấu Trường Giải Đấu Loại Trực Tiếp (Bracket Tree) & Sàn Dự Đoán Quán Quân:
 * - 3 GIẢI ĐẤU song song: Cờ Ca-rô AI, Cờ Vua AI, Cờ Tướng AI (mỗi giải 8 kỳ thủ AI riêng).
 * - Mỗi giải tự động mở mùa mới mỗi 30 phút. Các giải LỆCH PHA 10 phút nên lúc nào cũng có
 *   một giải đang mở cược, một giải đang thi đấu, một giải sắp trao cúp.
 * - 8 Kỳ thủ AI hạt giống tranh tài qua 3 vòng: Tứ kết ➔ Bán kết ➔ Chung kết.
 * - Sàn Dự Đoán Quán Quân (Outright Betting) với Odds tự động, trả thưởng GTLM lớn.
 * - Đồng bộ thời gian thực chuẩn xác 100% cho mọi người chơi (giai đoạn chạy theo đồng hồ thật).
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

/**
 * 🎮 Cấu hình 3 giải đấu: Cờ Ca-rô, Cờ Vua, Cờ Tướng
 * - offset: lệch pha đồng hồ (giây) để 3 giải không trùng giai đoạn với nhau
 * - id_offset: tách vùng season_id trong DB (cột season_id là UNIQUE) để mỗi giải có mùa riêng
 */
if (!function_exists('getEsportsGamesConfig')) {
function getEsportsGamesConfig() {
    return [
        'caro' => [
            'code' => 'caro',
            'db_type' => 'caro_ai',
            'label' => 'Cờ Ca-rô',
            'icon' => '⭕',
            'offset' => 0,
            'id_offset' => 0,
            'title_name' => 'Đại Chiến Cờ Ca-rô AI Siêu Cấp'
        ],
        'chess' => [
            'code' => 'chess',
            'db_type' => 'chess_ai',
            'label' => 'Cờ Vua',
            'icon' => '♞',
            'offset' => 600,
            'id_offset' => 1000000,
            'title_name' => 'Đại Chiến Cờ Vua AI Siêu Cấp'
        ],
        'xiangqi' => [
            'code' => 'xiangqi',
            'db_type' => 'xiangqi_ai',
            'label' => 'Cờ Tướng',
            'icon' => '帥',
            'offset' => 1200,
            'id_offset' => 2000000,
            'title_name' => 'Đại Chiến Cờ Tướng AI Siêu Cấp'
        ]
    ];
}
}

if (!function_exists('normalizeEsportsGame')) {
function normalizeEsportsGame($game) {
    $game = strtolower(trim((string)$game));
    $games = getEsportsGamesConfig();
    return isset($games[$game]) ? $game : 'caro';
}
}

/**
 * Dựng danh sách 8 kỳ thủ theo thứ tự hạt giống 1 ➔ 8
 * Mỗi dòng: [id, tên, danh hiệu, avatar, màu, elo, odds, lối đánh]
 */
if (!function_exists('esportsBuildRoster')) {
function esportsBuildRoster(array $rows) {
    $roster = [];
    $seed = 1;
    foreach ($rows as $r) {
        $roster[$r[0]] = [
            'id' => $r[0],
            'name' => $r[1],
            'title' => $r[2],
            'avatar' => $r[3],
            'color' => $r[4],
            'elo' => $r[5],
            'seed' => $seed++,
            'odds' => $r[6],
            'style' => $r[7]
        ];
    }
    return $roster;
}
}

if (!function_exists('getEsportsPlayersList')) {
function getEsportsPlayersList($game = 'caro') {
    $game = normalizeEsportsGame($game);

    if ($game === 'chess') {
        return esportsBuildRoster([
            ['cv_deepking', 'DeepKing', 'Vua Của Các Vị Vua', '👑', '#fbbf24', 2890, 2.40, 'Khai cuộc sách vở, nuốt chửng đối thủ ở trung cuộc'],
            ['cv_stockmate', 'StockMate', 'Động Cơ Bất Bại', '🛡️', '#38bdf8', 2860, 3.00, 'Tàn cuộc chính xác như cỗ máy'],
            ['cv_knightmare', 'KnightMare', 'Ác Mộng Của Quân Mã', '🐴', '#a855f7', 2820, 4.20, 'Phong cách mã dị biệt, hy sinh quân đầy bất ngờ'],
            ['cv_rookstorm', 'RookStorm', 'Bão Xe Đôi', '🏰', '#f97316', 2790, 5.50, 'Song xe chiếm cột mở, đè bẹp cánh vua'],
            ['cv_bishopx', 'BishopX', 'Tượng Cặp Xuyên Phá', '⛪', '#10b981', 2750, 7.00, 'Tượng cặp quét thẳng các đường chéo dài'],
            ['cv_queenzilla', 'QueenZilla', 'Nữ Hoàng Chớp Nhoáng', '💎', '#ec4899', 2720, 8.80, 'Hậu tung hoành, tấn công chớp nhoáng'],
            ['cv_pawnstar', 'PawnStar', 'Tốt Thí Thành Hậu', '⚡', '#eab308', 2680, 11.00, 'Đẩy tốt tuyệt đỉnh, biến tốt thành hậu'],
            ['cv_gambitzero', 'GambitZero', 'Kẻ Liều Lĩnh Gambit', '🔥', '#ef4444', 2640, 14.50, 'Thí quân liều lĩnh ngay từ khai cuộc']
        ]);
    }

    if ($game === 'xiangqi') {
        return esportsBuildRoster([
            ['ct_longtuong', 'Long Tướng', 'Rồng Thiêng Cung Thành', '🐉', '#ef4444', 2880, 2.40, 'Pháo đầu đè trung lộ, ép sát cung thành'],
            ['ct_phongma', 'Phong Mã', 'Song Mã Ẩm Tuyền', '🐎', '#38bdf8', 2850, 3.10, 'Song mã linh hoạt, tấn công liên hoàn'],
            ['ct_thienphao', 'Thiên Pháo', 'Pháo Gài Chiếu Tướng', '💥', '#f97316', 2810, 4.30, 'Pháo gài chiếu tướng bất ngờ'],
            ['ct_haxe', 'Hạ Xe', 'Thủ Thành Bất Bại', '🏯', '#10b981', 2770, 5.60, 'Thủ chắc cung thành, phản công bằng xe'],
            ['ct_kiemthan', 'Kiếm Thần', 'Tốt Qua Sông', '⚔️', '#a855f7', 2740, 7.20, 'Tốt qua sông dồn ép từng bước'],
            ['ct_anphong', 'Ẩn Phong', 'Cao Thủ Ẩn Danh', '🌪️', '#94a3b8', 2700, 9.00, 'Kỳ thủ ẩn mình, thế trận khó lường'],
            ['ct_hoalong', 'Hỏa Long', 'Công Thành Bạt Địa', '🔥', '#eab308', 2660, 11.50, 'Đổi quân hung hãn, công thành bạt địa'],
            ['ct_bachho', 'Bạch Hổ', 'Hổ Trắng Phản Đòn', '🐅', '#f1f5f9', 2620, 15.00, 'Lì đòn, chờ thời cơ phản đòn chí mạng']
        ]);
    }

    return esportsBuildRoster([
        ['alphax', 'AlphaX', 'Đệ Nhất Khai Cuộc', '🔴', '#ef4444', 2850, 2.50, 'Tấn công dồn dập, ép biên thần tốc'],
        ['deepo', 'DeepO', 'Thần Toán Trung Cuộc', '⭕', '#3b82f6', 2820, 3.20, 'Tính toán sâu 12 nước cờ đôi'],
        ['zenzero', 'ZenZero', 'Khiên Thần Ca-rô', '🟣', '#a855f7', 2780, 4.50, 'Bậc thầy phòng thủ & phản kích'],
        ['stockcaro', 'StockCaro', 'Pháo Đài Bất Bại', '🟢', '#10b981', 2740, 5.80, 'Kiểm soát trung lộ cực kỳ chắc chắn'],
        ['dragonai', 'DragonAI', 'Hỏa Long Sát Chiêu', '🟡', '#fbbf24', 2700, 7.20, 'Thế cờ 3 quân phân nhánh hiểm hóc'],
        ['phantomx', 'PhantomX', 'Bóng Ma Đảo Ngược', '⚪', '#94a3b8', 2680, 9.00, 'Lội ngược dòng phút chót ngoạn mục'],
        ['cyberbot', 'CyberBot', 'Tia Chớp Lượng Tử', '🟠', '#f97316', 2640, 11.50, 'Hạ cờ tốc hành trong 0.2 giây'],
        ['titango', 'TitanGo', 'Thạch Bàn Trầm Ổn', '🟤', '#78716c', 2610, 15.00, 'Lì đòn, biến hóa khó đoán']
    ]);
}
}

/**
 * 🎲 Hàm xác định người thắng trận dựa trên Elo và hạt giống mùa giải
 */
if (!function_exists('simulateMatchWinner')) {
function simulateMatchWinner($p1Id, $p2Id, $seasonId, $matchKey, $game = 'caro') {
    $players = getEsportsPlayersList($game);
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

/**
 * ⏱️ Xác định giai đoạn theo thời gian đã trôi qua trong mùa (0 - 1800 giây)
 * Trả về [stage, giây còn lại của giai đoạn, tiêu đề giai đoạn]
 */
if (!function_exists('esportsStageFromElapsed')) {
function esportsStageFromElapsed($elapsed) {
    if ($elapsed < 600) {
        return ['betting', 600 - $elapsed, 'Mở Sàn Đặt Cược Dự Đoán Quán Quân'];
    } elseif ($elapsed < 1080) {
        return ['quarter_finals', 1080 - $elapsed, 'Đang Thi Đấu Vòng Tứ Kết (4 Trận)'];
    } elseif ($elapsed < 1440) {
        return ['semi_finals', 1440 - $elapsed, 'Đang Thi Đấu Vòng Bán Kết (2 Trận)'];
    } elseif ($elapsed < 1680) {
        return ['finals', 1680 - $elapsed, 'Trận Chung Kết Đỉnh Cao - Tranh Cúp Vàng'];
    }
    return ['finished', 1800 - $elapsed, 'Đã Tìm Ra Quán Quân - Chuẩn Bị Mùa Mới'];
}
}

if (!function_exists('getEsportsCurrentSeasonInfo')) {
function getEsportsCurrentSeasonInfo($now = null, $game = 'caro') {
    if ($now === null) $now = time();
    $game = normalizeEsportsGame($game);
    $gameCfg = getEsportsGamesConfig()[$game];
    $offset = (int)$gameCfg['offset'];

    $cycleSeconds = 1800; // 30 phút một mùa giải
    // Mốc khởi chạy MÙA XUÂN (Khởi đầu tính từ mùa đầu tiên khi ra mắt)
    $launchTimestamp = 1793628000;
    $launchBase = (int)floor($launchTimestamp / $cycleSeconds);

    // Đồng hồ riêng của từng giải (lệch pha để 3 giải không trùng giai đoạn)
    $shiftedNow = $now + $offset;
    $rawSeason = (int)floor($shiftedNow / $cycleSeconds);

    $seasonIndex = max(0, ($rawSeason - $launchBase)); // 0, 1, 2, 3...
    $seasonNumber = $seasonIndex + 1; // 1, 2, 3, 4...
    // season_id lưu DB: tách vùng riêng cho từng giải (cột season_id là UNIQUE)
    $seasonId = $seasonNumber + (int)$gameCfg['id_offset'];

    // 🌸 ☀️ 🍂 ❄️ 4 Mùa: XUÂN - HẠ - THU - ĐÔNG
    $seasonsList = [
        0 => [
            'name' => 'MÙA XUÂN',
            'code' => 'xuan',
            'icon' => '🌸',
            'badge_class' => 'season-spring',
            'tag' => '🌸 MÙA XUÂN',
            'theme' => 'Sắc Xuân Rực Rỡ'
        ],
        1 => [
            'name' => 'MÙA HẠ',
            'code' => 'ha',
            'icon' => '☀️',
            'badge_class' => 'season-summer',
            'tag' => '☀️ MÙA HẠ',
            'theme' => 'Hạ Lửa Rực Cháy'
        ],
        2 => [
            'name' => 'MÙA THU',
            'code' => 'thu',
            'icon' => '🍂',
            'badge_class' => 'season-autumn',
            'tag' => '🍂 MÙA THU',
            'theme' => 'Thu Vàng Khúc Biệt'
        ],
        3 => [
            'name' => 'MÙA ĐÔNG',
            'code' => 'dong',
            'icon' => '❄️',
            'badge_class' => 'season-winter',
            'tag' => '❄️ MÙA ĐÔNG',
            'theme' => 'Băng Tuyết Vô Địch'
        ]
    ];

    // Mùa giải luân phiên Xuân - Hạ - Thu - Đông theo đồng hồ thật (không cho người chơi can thiệp)
    $cycleIndex = $seasonIndex % 4;

    $currentSeason = $seasonsList[$cycleIndex];
    $yearNumber = (int)floor($seasonIndex / 4) + 1;

    $seasonTag = $currentSeason['tag'];
    if ($yearNumber > 1) {
        $seasonTag .= " (NĂM {$yearNumber})";
    }

    $title = $gameCfg['title_name'] . " - " . $currentSeason['name'] . ($yearNumber > 1 ? " (Năm {$yearNumber})" : "");
    $elapsed = $shiftedNow % $cycleSeconds;

    // Thời điểm bắt đầu / kết thúc THẬT của mùa (trừ lại phần lệch pha)
    $seasonStartTime = date('Y-m-d H:i:s', $rawSeason * $cycleSeconds - $offset);
    $seasonEndTime = date('Y-m-d H:i:s', ($rawSeason + 1) * $cycleSeconds - $offset);

    return [
        'game' => $game,
        'game_label' => $gameCfg['label'],
        'season_id' => $seasonId,
        'season_number' => $seasonNumber,
        'season_name' => $currentSeason['name'],
        'season_icon' => $currentSeason['icon'],
        'season_tag' => $seasonTag,
        'season_badge_class' => $currentSeason['badge_class'],
        'season_theme' => $currentSeason['theme'],
        'title' => $title,
        'elapsed' => $elapsed,
        'start_time' => $seasonStartTime,
        'end_time' => $seasonEndTime,
        'cycle_seconds' => $cycleSeconds
    ];
}
}

/**
 * 🌳 Dựng toàn bộ cây thi đấu 8 kỳ thủ (4 trận tứ kết ➔ 2 trận bán kết ➔ 1 chung kết)
 * Cặp đấu theo hạt giống: #1 vs #8, #4 vs #5, #2 vs #7, #3 vs #6.
 * Bán kết / Chung kết CHỈ lộ đối thủ khi vòng trước đã đấu xong (hoặc đang diễn ra đúng vòng đó).
 */
if (!function_exists('esportsBuildBracket')) {
function esportsBuildBracket(array $players, $seasonId, $stage, $game) {
    $ids = array_keys($players); // đã sắp theo hạt giống 1 ➔ 8
    $pairs = [[0, 7], [3, 4], [1, 6], [2, 5]];

    $qfWinners = [];
    foreach ($pairs as $i => $pair) {
        $qfWinners[$i] = simulateMatchWinner($ids[$pair[0]], $ids[$pair[1]], $seasonId, 'qf' . ($i + 1), $game);
    }
    $sf1Winner = simulateMatchWinner($qfWinners[0], $qfWinners[1], $seasonId, 'sf1', $game);
    $sf2Winner = simulateMatchWinner($qfWinners[2], $qfWinners[3], $seasonId, 'sf2', $game);
    $finalWinner = simulateMatchWinner($sf1Winner, $sf2Winner, $seasonId, 'final', $game);
    $champion = $players[$finalWinner];

    $isQfFinished = in_array($stage, ['semi_finals', 'finals', 'finished']);
    $isQfPlaying = ($stage === 'quarter_finals');
    $isSfFinished = in_array($stage, ['finals', 'finished']);
    $isSfPlaying = ($stage === 'semi_finals');
    $isFinalFinished = ($stage === 'finished');
    $isFinalPlaying = ($stage === 'finals');

    $qf = [];
    foreach ($pairs as $i => $pair) {
        $a = $ids[$pair[0]];
        $b = $ids[$pair[1]];
        $w = $qfWinners[$i];
        $qf[] = [
            'match_id' => $i + 1,
            'round_name' => 'Tứ Kết ' . ($i + 1),
            'title' => 'TỨ KẾT ' . ($i + 1) . ': ' . $players[$a]['name'] . ' vs ' . $players[$b]['name'],
            'p1' => $players[$a],
            'p2' => $players[$b],
            'winner' => $isQfFinished ? $w : null,
            'status' => $isQfFinished ? 'finished' : ($isQfPlaying ? 'playing' : 'upcoming'),
            'p1_score' => $isQfFinished ? ($w === $a ? 1 : 0) : 0,
            'p2_score' => $isQfFinished ? ($w === $b ? 1 : 0) : 0
        ];
    }

    $showSf = ($isQfFinished || $isSfPlaying);
    $sfDefs = [
        [5, 'Bán Kết 1', 'BÁN KẾT 1', 'TK1', 'TK2', $qfWinners[0], $qfWinners[1], $sf1Winner],
        [6, 'Bán Kết 2', 'BÁN KẾT 2', 'TK3', 'TK4', $qfWinners[2], $qfWinners[3], $sf2Winner]
    ];
    $sf = [];
    foreach ($sfDefs as $d) {
        $sf[] = [
            'match_id' => $d[0],
            'round_name' => $d[1],
            'title' => $showSf
                ? ($d[2] . ': ' . ($players[$d[5]]['name'] ?? 'TBD') . ' vs ' . ($players[$d[6]]['name'] ?? 'TBD'))
                : ($d[2] . ' (Thắng ' . $d[3] . ' vs Thắng ' . $d[4] . ')'),
            'p1' => $showSf ? $players[$d[5]] : null,
            'p2' => $showSf ? $players[$d[6]] : null,
            'winner' => $isSfFinished ? $d[7] : null,
            'status' => $isSfFinished ? 'finished' : ($isSfPlaying ? 'playing' : 'upcoming'),
            'p1_score' => $isSfFinished ? ($d[7] === $d[5] ? 1 : 0) : 0,
            'p2_score' => $isSfFinished ? ($d[7] === $d[6] ? 1 : 0) : 0
        ];
    }

    $showFinal = ($isSfFinished || $isFinalPlaying);
    $final = [
        'match_id' => 7,
        'round_name' => 'Chung Kết',
        'title' => $showFinal
            ? ('👑 CHUNG KẾT VÔ ĐỊCH: ' . ($players[$sf1Winner]['name'] ?? 'TBD') . ' vs ' . ($players[$sf2Winner]['name'] ?? 'TBD'))
            : '👑 CHUNG KẾT VÔ ĐỊCH (Thắng BK1 vs Thắng BK2)',
        'p1' => $showFinal ? $players[$sf1Winner] : null,
        'p2' => $showFinal ? $players[$sf2Winner] : null,
        'winner' => $isFinalFinished ? $finalWinner : null,
        'status' => $isFinalFinished ? 'finished' : ($isFinalPlaying ? 'playing' : 'upcoming'),
        'p1_score' => $isFinalFinished ? ($finalWinner === $sf1Winner ? 1 : 0) : 0,
        'p2_score' => $isFinalFinished ? ($finalWinner === $sf2Winner ? 1 : 0) : 0
    ];

    return [
        'tree' => [
            'qf' => $qf,
            'sf' => $sf,
            'final' => $final,
            'champion' => $isFinalFinished ? $champion : null
        ],
        'final_winner' => $finalWinner,
        'champion' => $champion
    ];
}
}

/**
 * 🏛️ Lấy hoặc tạo Mùa Giải Đấu Trường Hiện Tại (theo từng môn: caro / chess / xiangqi)
 * Tham số $forceStage được giữ lại cho tương thích nhưng KHÔNG còn tác dụng: giai đoạn chỉ chạy theo đồng hồ thật.
 */
if (!function_exists('getCurrentEsportsTournament')) {
function getCurrentEsportsTournament($conn, $forceStage = null, $game = 'caro') {
    initEsportsTournamentTables($conn);

    $game = normalizeEsportsGame($game);
    $gameCfg = getEsportsGamesConfig()[$game];

    $seasonInfo = getEsportsCurrentSeasonInfo(null, $game);
    $seasonId = $seasonInfo['season_id'];
    $seasonNumber = $seasonInfo['season_number'];
    $seasonName = $seasonInfo['season_name'];
    $seasonTag = $seasonInfo['season_tag'];
    $seasonBadgeClass = $seasonInfo['season_badge_class'];
    $title = $seasonInfo['title'];
    $elapsed = $seasonInfo['elapsed'];
    $seasonStartTime = $seasonInfo['start_time'];
    $seasonEndTime = $seasonInfo['end_time'];

    list($currentStage, $timeRemainingInStage, $stageTitle) = esportsStageFromElapsed($elapsed);

    // Lấy thông tin từ database nếu đã có
    $stmt = $conn->prepare("SELECT * FROM esports_tournaments WHERE season_id = ?");
    $stmt->bind_param("i", $seasonId);
    $stmt->execute();
    $tour = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $players = getEsportsPlayersList($game);

    $built = esportsBuildBracket($players, $seasonId, $currentStage, $game);
    $bracketTree = $built['tree'];
    $final_winner = $built['final_winner'];
    $champion = $built['champion'];

    $bracketJson = json_encode($bracketTree, JSON_UNESCAPED_UNICODE);
    $dbGameType = $gameCfg['db_type'];

    if (!$tour) {
        $stmtInsert = $conn->prepare("INSERT INTO esports_tournaments (season_id, title, game_type, status, start_time, end_time, champion_id, champion_name, bracket_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $champId = ($currentStage === 'finished') ? $final_winner : null;
        $champName = ($currentStage === 'finished') ? $champion['name'] : null;
        $stmtInsert->bind_param("issssssss", $seasonId, $title, $dbGameType, $currentStage, $seasonStartTime, $seasonEndTime, $champId, $champName, $bracketJson);
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
        settleEsportsPredictionsPayout($conn, $seasonId, $final_winner, $champion['name'], $gameCfg['label'], $seasonNumber);
        $conn->query("UPDATE esports_tournaments SET payout_settled = 1 WHERE season_id = " . (int)$seasonId);
    }

    // Lấy tổng cược hiện tại của mùa này
    $stmtPool = $conn->prepare("SELECT IFNULL(SUM(bet_amount), 0) as total_pool, COUNT(*) as total_bets FROM esports_predictions WHERE season_id = ?");
    $stmtPool->bind_param("i", $seasonId);
    $stmtPool->execute();
    $poolData = $stmtPool->get_result()->fetch_assoc();
    $stmtPool->close();

    return [
        'id' => $tourDbId,
        'game' => $game,
        'game_label' => $gameCfg['label'],
        'season_id' => $seasonId,
        'season_number' => $seasonNumber,
        'season_name' => $seasonName,
        'season_tag' => $seasonTag,
        'season_badge_class' => $seasonBadgeClass,
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
 * 📡 Trạng thái nhanh của cả 3 giải (không ghi DB) để hiển thị trên thanh chọn giải
 */
if (!function_exists('getEsportsAllGamesStatus')) {
function getEsportsAllGamesStatus() {
    $out = [];
    foreach (getEsportsGamesConfig() as $code => $cfg) {
        $info = getEsportsCurrentSeasonInfo(null, $code);
        list($stage, $remaining, $stageTitle) = esportsStageFromElapsed($info['elapsed']);
        $out[$code] = [
            'game' => $code,
            'label' => $cfg['label'],
            'icon' => $cfg['icon'],
            'stage' => $stage,
            'stage_title' => $stageTitle,
            'seconds_remaining' => $remaining,
            'season_tag' => $info['season_tag'],
            'season_number' => $info['season_number']
        ];
    }
    return $out;
}
}

/**
 * 💰 Thanh toán trả thưởng cho khán giả dự đoán chính xác Quán Quân
 */
if (!function_exists('settleEsportsPredictionsPayout')) {
function settleEsportsPredictionsPayout($conn, $seasonId, $championId, $championName, $gameLabel = '', $seasonNumber = null) {
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
            $shownSeason = $seasonNumber !== null ? $seasonNumber : $seasonId;
            $gameText = $gameLabel !== '' ? " {$gameLabel}" : '';
            RealtimePublisher::publish('live_61', 'esports_champion_announced', [
                'season_id' => $seasonId,
                'champion_id' => $championId,
                'champion_name' => $championName,
                'message' => "🏆 CHÚC MỪNG KỲ THỦ [{$championName}] ĐÃ ĐOẠT CÚP VÔ ĐỊCH GIẢI" . mb_strtoupper($gameText) . " MÙA #{$shownSeason}!"
            ]);
        }
    }
}
}

/**
 * 🎯 Đặt Cược Dự Đoán Quán Quân
 */
if (!function_exists('placeEsportsPrediction')) {
function placeEsportsPrediction($conn, $userId, $playerId, $amount, $game = 'caro') {
    $game = normalizeEsportsGame($game);
    $seasonInfo = getEsportsCurrentSeasonInfo(null, $game);
    $seasonId = $seasonInfo['season_id'];
    $elapsed = $seasonInfo['elapsed'];

    // Chỉ cho phép đặt cược trong 10 phút đầu (0 - 600s) của mỗi mùa giải
    $isBettingStage = ($elapsed < 600);

    if (!$isBettingStage) {
        return [
            'success' => false,
            'message' => 'Đã hết thời gian đặt cược mùa giải này (đang trong giai đoạn thi đấu). Vui lòng chờ mùa giải kế tiếp nhé!'
        ];
    }

    $amount = (float)$amount;
    if ($amount < 1000) {
        return ['success' => false, 'message' => 'Mức cược dự đoán tối thiểu là 1,000 GTLM!'];
    }

    $players = getEsportsPlayersList($game);
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

    $tour = getCurrentEsportsTournament($conn, null, $game);
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
