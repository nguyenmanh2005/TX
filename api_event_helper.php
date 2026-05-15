<?php
require_once 'db_connect.php';

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

        // Nếu chưa có, chọn ngẫu nhiên
        $availableGames = [
            'Baccarat', 'Blackjack', 'Roulette', 'Sicbo', 'Tài Xỉu', 
            'RPS', 'Vietlott', 'Xóc Đĩa', 'Poker', 'Bầu Cua',
            'Slot Cyber', 'Mega Spin', 'Horse Race'
        ];
        
        $randomGame = $availableGames[array_rand($availableGames)];
        
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
     * Xử lý Combo Streak trong session
     */
    public static function handleComboStreak(mysqli $conn, int $userId, string $gameName) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        if (!isset($_SESSION['combo_games'])) {
            $_SESSION['combo_games'] = [];
        }

        // Nếu chưa chơi game này trong session
        if (!in_array($gameName, $_SESSION['combo_games'])) {
            $_SESSION['combo_games'][] = $gameName;
            $uniqueCount = count($_SESSION['combo_games']);

            // Bonus thresholds: 3 games = 5%, 5 games = 10%, 10 games = 20%
            $bonusPercent = 0;
            if ($uniqueCount == 3) $bonusPercent = 0.05;
            elseif ($uniqueCount == 5) $bonusPercent = 0.10;
            elseif ($uniqueCount == 10) $bonusPercent = 0.20;

            if ($bonusPercent > 0) {
                // Trả về thông tin bonus để logic game áp dụng (thường là bonus vào winAmount)
                return [
                    'count' => $uniqueCount,
                    'bonus_percent' => $bonusPercent,
                    'message' => "Combo Streak x{$uniqueCount}! Bạn nhận được bonus " . ($bonusPercent * 100) . "% XP & Vàng cho các ván tiếp theo trong session này!"
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
}
?>
