<?php
require_once 'db_connect.php';

/**
 * Lấy sự kiện seasonal đang active.
 *
 * Đây là nguồn sự thật duy nhất cho truy vấn seasonal_events.
 * Toàn bộ code nên dùng hàm này thay vì tự query trực tiếp.
 *
 * @param mysqli $conn       Database connection
 * @param bool   $forUpdate  TRUE khi gọi trong transaction (thêm FOR UPDATE lock)
 * @param string $columns    Columns cần SELECT (mặc định '*')
 * @return array|null        Row dữ liệu hoặc null nếu không có event active
 */
function getActiveSeasonalEvent(mysqli $conn, bool $forUpdate = false, string $columns = '*'): ?array {
    static $cache = [];
    $cacheKey = ($forUpdate ? 'update_' : 'read_') . $columns;
    
    // Trả về cache nếu đã query trong scope request hiện tại
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $lock = $forUpdate ? ' FOR UPDATE' : '';
    // Dùng starts_at/ends_at để tránh event bị đánh dấu active nhưng chưa/đã hết thời gian
    $sql = "SELECT {$columns} FROM seasonal_events
            WHERE status = 'active'
              AND starts_at <= NOW()
              AND ends_at   >= NOW()
            LIMIT 1{$lock}";
    $result = $conn->query($sql);
    if (!$result) return null;
    $row = $result->fetch_assoc();
    
    $data = $row ?: null;
    $cache[$cacheKey] = $data;
    return $data;
}

class EventHelper {
    /**
     * Lấy Game of the Day cho ngày hôm nay.
     * Nếu chưa có, chọn ngẫu nhiên một game.
     */
    public static function getGameOfTheDay(mysqli $conn) {
        $today = date('Y-m-d');
        
        // Kiểm tra trong DB
        $stmt = $conn->prepare("SELECT game_name FROM daily_tournament_records WHERE event_date = ?");
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = $res->fetch_assoc();
        $stmt->close();

        if ($data) {
            return $data['game_name'];
        }

        // Tỷ lệ/Danh sách fallback game cứng cũ
        $fallbackGames = [
            'Baccarat', 'Blackjack', 'Roulette', 'Sicbo', 'Xanh Đỏ Đối Kháng', 
            'RPS', 'Vietlott', 'Xóc Đĩa', 'Poker', 'Bầu Cua',
            'Slot Cyber', 'Mega Spin', 'Horse Race'
        ];

        // Đọc động từ thư mục games/
        $availableGames = [];
        $gamesDir = __DIR__ . '/games/';
        if (is_dir($gamesDir)) {
            $files = scandir($gamesDir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                $path = $gamesDir . $file;
                if (is_file($path) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $name = pathinfo($file, PATHINFO_FILENAME);
                    
                    // Lọc bỏ tệp tin helper, process, widget phụ trợ
                    if (
                        strpos($name, 'process') !== false ||
                        strpos($name, 'widget') !== false ||
                        strpos($name, 'daily_challenge') !== false ||
                        strpos($name, 'slot-sounds') !== false ||
                        strpos($name, '$path') !== false
                    ) {
                        continue;
                    }
                    
                    $availableGames[] = $name;
                }
            }
        }

        if (empty($availableGames)) {
            $randomGame = $fallbackGames[array_rand($fallbackGames)];
        } else {
            // Chọn ngẫu nhiên một game viết liền không dấu
            $selectedFileKey = $availableGames[array_rand($availableGames)];
            
            // Map tên tệp tin sang Tên Game tiếng Việt đẹp mắt
            $gameMapping = [
                'baccarat'           => 'Baccarat',
                'banharc'            => 'Bắn Hạc (BanhArc)',
                'battleroyale'       => 'Đấu Trường Sinh Tử',
                'baucua'             => 'Bầu Cua (Thế Giới Linh Thú)',
                'bingo'              => 'Bingo',
                'bj'                 => 'Blackjack Premium',
                'bjo'                => 'Blackjack Cổ Điển',
                'blackjack'          => 'Blackjack',
                'caribbean'          => 'Poker Caribbean',
                'coinflip'           => 'Tung Đồng Xu (Coinflip)',
                'community_lottery'  => 'Xổ Số Cộng Đồng',
                'craps'              => 'Đổ Xúc Xắc (Craps)',
                'crash'              => 'Đua Phi Thuyền (Crash)',
                'daga'               => 'Đá Gà (Đại Chiến Thần Kê)',
                'dice'               => 'Dice',
                'dragontiger'        => 'Rồng Hổ (Dragon Tiger)',
                'duangua'            => 'Đua Ngựa',
                'fantan'             => 'Fan Tan',
                'hilo'               => 'Hi-Lo',
                'holdem'             => 'Texas Hold\'em',
                'hopmu'              => 'Hộp Mù (Blind Box)',
                'horserace'          => 'Đua Ngựa 3D',
                'jojo_battle'        => 'Đại Chiến Jojo',
                'keno'               => 'Keno',
                'letitride'          => 'Let It Ride Poker',
                'limbo'              => 'Limbo',
                'lottery'            => 'Xổ Số Siêu Tốc',
                'mahjong'            => 'Mạt Chược',
                'megaspin'           => 'Vòng Quay Siêu Cấp',
                'mines'              => 'Mines (Dò Mìn)',
                'minesweeper'        => 'Dò Mìn Cổ Điển',
                'number'             => 'Con Số May Mắn',
                'paigow'             => 'Pai Gow Poker',
                'plinko'             => 'Plinko',
                'poker'              => 'Poker',
                'pontoon'            => 'Pontoon',
                'reddog'             => 'Red Dog',
                'roulette'           => 'Roulette',
                'rps'                => 'Oẳn Tù Tì (RPS)',
                'ruttham'            => 'Rút Thăm Trúng Thưởng',
                'samloc'             => 'Sâm Lốc',
                'scratch'            => 'Thẻ Cào May Mắn',
                'sicbo'              => 'Sicbo',
                'sicbo_v2'           => 'Sicbo Pro',
                'slot'               => 'Slot Machine',
                'slot_machine'       => 'Máy Vận May Premium',
                'threecard'          => 'Bài 3 Lá (Three Card Poker)',
                'tower'              => 'Tháp May Mắn',
                'tusac'              => 'Tứ Sắc',
                'videopoker'         => 'Video Poker',
                'vietlott'           => 'Vietlott',
                'war'                => 'Chiến Tranh Bài (Casino War)',
                'xocdia'             => 'Xóc Đĩa (Trận Địa Trắng Đỏ)',
                'yahtzee'            => 'Yahtzee'
            ];
            
            if (isset($gameMapping[$selectedFileKey])) {
                $randomGame = $gameMapping[$selectedFileKey];
            } else {
                // Fallback: tự động tạo tên viết hoa đẹp mắt
                $randomGame = ucwords(str_replace(['_', '-'], ' ', $selectedFileKey));
            }
        }
        
        // Lưu vào DB
        $stmt = $conn->prepare("INSERT IGNORE INTO daily_tournament_records (game_name, event_date) VALUES (?, ?)");
        $stmt->bind_param("ss", $randomGame, $today);
        $stmt->execute();
        $stmt->close();

        return $randomGame;
    }

    /**
     * Cập nhật điểm Daily Tournament
     */
    public static function updateDailyScore(mysqli $conn, int $userId, float $amount) {
        if ($amount <= 0) return;
        $today = date('Y-m-d');
        
        $sql = "INSERT INTO daily_tournament_scores (user_id, event_date, score) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE score = score + ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isdd", $userId, $today, $amount, $amount);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Xử lý Combo Streak — lưu vào DB thay vì $_SESSION
     * BUG FIX #8: Trước đây dùng $_SESSION['combo_games'] nên mất streak khi đổi thiết bị.
     * Nay lưu vào bảng user_combo_streaks (user_id, session_date, game_list).
     * Streak reset khi sang ngày mới, không phải khi đổi session/thiết bị.
     *
     * SQL cần chạy 1 lần:
     * CREATE TABLE IF NOT EXISTS user_combo_streaks (
     *     user_id      INT NOT NULL PRIMARY KEY,
     *     session_date DATE NOT NULL,
     *     game_list    TEXT,
     *     updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
     * );
     */
    public static function handleComboStreak(mysqli $conn, int $userId, string $gameName) {
        $today = date('Y-m-d');

        // Lấy record hiện tại từ DB
        $stmt = $conn->prepare("SELECT session_date, game_list FROM user_combo_streaks WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Nếu sang ngày mới → reset game_list
        if (!$row || $row['session_date'] !== $today) {
            $gameList = [];
        } else {
            $gameList = !empty($row['game_list']) ? json_decode($row['game_list'], true) : [];
            if (!is_array($gameList)) $gameList = [];
        }

        // Nếu chưa chơi game này hôm nay → thêm vào danh sách
        if (!in_array($gameName, $gameList)) {
            $gameList[] = $gameName;
            $uniqueCount = count($gameList);
            $gameListJson = json_encode($gameList);

            // Upsert vào DB
            $stmt = $conn->prepare("
                INSERT INTO user_combo_streaks (user_id, session_date, game_list)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE session_date = VALUES(session_date), game_list = VALUES(game_list)
            ");
            $stmt->bind_param("iss", $userId, $today, $gameListJson);
            $stmt->execute();
            $stmt->close();

            // Bonus thresholds: 3 games = 5%, 5 games = 10%, 10 games = 20%
            $bonusPercent = 0;
            if ($uniqueCount == 3)  $bonusPercent = 0.05;
            elseif ($uniqueCount == 5)  $bonusPercent = 0.10;
            elseif ($uniqueCount == 10) $bonusPercent = 0.20;

            if ($bonusPercent > 0) {
                return [
                    'count'         => $uniqueCount,
                    'bonus_percent' => $bonusPercent,
                    'message'       => "Combo Streak x{$uniqueCount}! Bạn nhận được bonus " . ($bonusPercent * 100) . "% XP & Vàng cho các ván tiếp theo trong ngày hôm nay!"
                ];
            }
        }
        return null;
    }


    /**
     * Lấy Season hiện tại
     */
    public static function getActiveSeason(mysqli $conn) {
        $today = date('Y-m-d');
        $sql = "SELECT * FROM seasonal_pass_configs WHERE is_active = 1 AND ? BETWEEN start_date AND end_date LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $res = $stmt->get_result();
        $season = $res->fetch_assoc();
        $stmt->close();
        return $season;
    }

    /**
     * Cộng XP cho Seasonal Pass
     */
    public static function addSeasonalXP(mysqli $conn, int $userId, int $xpAmount) {
        $season = self::getActiveSeason($conn);
        if (!$season) return;

        $seasonId = $season['id'];
        
        // Lấy progress hiện tại
        $stmt = $conn->prepare("SELECT current_level, current_xp FROM user_seasonal_pass_progress WHERE user_id = ? AND season_id = ?");
        $stmt->bind_param("ii", $userId, $seasonId);
        $stmt->execute();
        $res = $stmt->get_result();
        $progress = $res->fetch_assoc();
        $stmt->close();

        if (!$progress) {
            $stmt = $conn->prepare("INSERT INTO user_seasonal_pass_progress (user_id, season_id, current_level, current_xp) VALUES (?, ?, 1, ?)");
            $stmt->bind_param("iii", $userId, $seasonId, $xpAmount);
            $stmt->execute();
            $stmt->close();
            return;
        }

        $level = $progress['current_level'];
        $xp = $progress['current_xp'] + $xpAmount;

        // Logic lên level (vd: 1000 XP mỗi level)
        while ($xp >= 1000) {
            $xp -= 1000;
            $level++;
        }

        $stmt = $conn->prepare("UPDATE user_seasonal_pass_progress SET current_level = ?, current_xp = ? WHERE user_id = ? AND season_id = ?");
        $stmt->bind_param("iiii", $level, $xp, $userId, $seasonId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Kiểm tra và tự động kích hoạt Flash Event ngẫu nhiên (tối đa 2 lần/ngày)
     *
     * @deprecated BUG FIX #6: Hàm này dựa vào xác suất 3% per page-load,
     *   không kích hoạt nếu không có ai online. Dùng cron_flash_event.php
     *   (chạy mỗi 30 phút) thay thế cho môi trường production.
     *   Giữ lại để không break code cũ đang gọi, nhưng không nên gọi mới.
     */
    public static function checkOrTriggerFlashEvent(mysqli $conn) {
        $today = date('Y-m-d');
        
        // 1. Kiểm tra xem hiện tại có Flash Event nào đang hoạt động không
        $activeRes = $conn->query("SELECT id FROM flash_events WHERE status = 'active' AND NOW() BETWEEN start_time AND end_time LIMIT 1");
        if ($activeRes && $activeRes->num_rows > 0) {
            return; // Đã có event đang chạy
        }
        
        // 2. Đếm số Flash Event đã kích hoạt hôm nay
        $todayStart = $today . ' 00:00:00';
        $todayEnd = $today . ' 23:59:59';
        $countRes = $conn->query("SELECT COUNT(*) as cnt FROM flash_events WHERE start_time BETWEEN '$todayStart' AND '$todayEnd'");
        $countToday = $countRes ? (int)$countRes->fetch_assoc()['cnt'] : 0;
        
        if ($countToday >= 2) {
            return; // Hôm nay đã chạy đủ 2 lần
        }
        
        // 3. Tỉ lệ ngẫu nhiên 3% kích hoạt trên mỗi lượt request tải trang
        if (rand(1, 100) <= 3) {
            $duration = rand(15, 30); // 15 đến 30 phút
            $stmt = $conn->prepare("INSERT INTO flash_events (multiplier, start_time, end_time, status) VALUES (2.00, NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE), 'active')");
            $stmt->bind_param("i", $duration);
            $stmt->execute();
            $stmt->close();
            
            // Bắn tin thông báo lên Chat hệ thống để tạo FOMO cực mạnh!
            $announceMsg = "⚡ SỰ KIỆN CHỚP NHOÁNG (FLASH EVENT)! Cổng trời mở ra x2 phần thưởng GTLM cho TOÀN BỘ trận địa trong {$duration} phút tiếp theo! Cơ hội duy nhất trong ngày, mau ra chiêu!";
            $sysAvatar = 'https://cdn-icons-png.flaticon.com/512/1041/1041044.png';
            $stmtChat = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar, created_at) VALUES (0, 'Hệ Thống', ?, ?, NOW())");
            $stmtChat->bind_param("ss", $announceMsg, $sysAvatar);
            $stmtChat->execute();
            $stmtChat->close();
        }
    }

    /**
     * Lấy hệ số nhân thưởng Flash Event hiện tại
     */
    public static function getActiveFlashMultiplier(mysqli $conn) {
        // Tự động dọn dẹp các sự kiện đã hết hạn
        $conn->query("UPDATE flash_events SET status = 'expired' WHERE status = 'active' AND NOW() > end_time");
        
        // Kiểm tra xem có sự kiện active nào không
        $res = $conn->query("SELECT multiplier FROM flash_events WHERE status = 'active' AND NOW() BETWEEN start_time AND end_time LIMIT 1");
        if ($res && $res->num_rows > 0) {
            return (float)$res->fetch_assoc()['multiplier'];
        }
        
        return 1.00;
    }
}
?>
