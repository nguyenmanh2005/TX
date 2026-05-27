<?php
/**
 * Helper function để ghi lại lịch sử game vào database
 * Sử dụng trong các game để track quest progress
 */
require_once __DIR__ . '/api_event_helper.php';

/**
 * Ghi lại lịch sử chơi game
 * @param mysqli $conn Database connection
 * @param int $userId ID người dùng
 * @param string $gameName Tên game (ví dụ: 'Blackjack', 'CYBER PETS', 'Slot')
 * @param float $betAmount Số gtlm cược
 * @param float $winAmount Số gtlm thắng (0 nếu thua)
 * @param bool $isWin Có thắng không
 * @return bool True nếu thành công, False nếu thất bại
 */
function logGameHistory(mysqli $conn, int $userId, string $gameName, float $betAmount = 0, float $winAmount = 0, bool $isWin = false)
{
    // Kiểm tra bảng game_history có tồn tại không
    $checkTable = $conn->query("SHOW TABLES LIKE 'game_history'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        // Bảng chưa tồn tại, không ghi log
        return false;
    }

    // Kiểm tra connection
    if (!$conn || $conn->connect_error) {
        return false;
    }

    // Insert vào game_history
    $sql = "INSERT INTO game_history (user_id, game_name, bet_amount, win_amount, is_win, played_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Error preparing game_history query: " . $conn->error);
        return false;
    }

    $stmt->bind_param("isddi", $userId, $gameName, $betAmount, $winAmount, $isWin);
    $result = $stmt->execute();

    if (!$result) {
        error_log("Error inserting game_history: " . $stmt->error);
        $stmt->close();
        return false;
    }

    $stmt->close();
    return true;
}

/**
 * Tính số gtlm kiếm được từ các game (win_amount - bet_amount)
 * @param mysqli $conn Database connection
 * @param int $userId ID người dùng
 * @param string $date Ngày cần tính (format: Y-m-d)
 * @return float Số gtlm kiếm được
 */
function calculateEarnedMoney(mysqli $conn, int $userId, string $date)
{
    $checkTable = $conn->query("SHOW TABLES LIKE 'game_history'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        return 0;
    }

    $sql = "SELECT SUM(win_amount - bet_amount) as total 
            FROM game_history 
            WHERE user_id = ? AND is_win = 1 AND DATE(played_at) = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("is", $userId, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    return max(0, $data['total'] ?? 0);
}

/**
 * Đếm số lần chơi game
 * @param mysqli $conn Database connection
 * @param int $userId ID người dùng
 * @param string $date Ngày cần đếm (format: Y-m-d)
 * @param string|null $gameName Tên game cụ thể (null nếu đếm tất cả)
 * @return int Số lần chơi
 */
function countGamesPlayed(mysqli $conn, int $userId, string $date, ?string $gameName = null)
{
    $checkTable = $conn->query("SHOW TABLES LIKE 'game_history'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        return 0;
    }

    if ($gameName) {
        $sql = "SELECT COUNT(*) as count 
                FROM game_history 
                WHERE user_id = ? AND game_name = ? AND DATE(played_at) = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("iss", $userId, $gameName, $date);
    } else {
        $sql = "SELECT COUNT(*) as count 
                FROM game_history 
                WHERE user_id = ? AND DATE(played_at) = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("is", $userId, $date);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    return $data['count'] ?? 0;
}

/**
 * Đếm số lần thắng
 * @param mysqli $conn Database connection
 * @param int $userId ID người dùng
 * @param string $date Ngày cần đếm (format: Y-m-d)
 * @param string|null $gameName Tên game cụ thể (null nếu đếm tất cả)
 * @return int Số lần thắng
 */
function countWins(mysqli $conn, int $userId, string $date, ?string $gameName = null)
{
    $checkTable = $conn->query("SHOW TABLES LIKE 'game_history'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        return 0;
    }

    if ($gameName) {
        $sql = "SELECT COUNT(*) as count 
                FROM game_history 
                WHERE user_id = ? AND is_win = 1 AND game_name = ? AND DATE(played_at) = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("iss", $userId, $gameName, $date);
    } else {
        $sql = "SELECT COUNT(*) as count 
                FROM game_history 
                WHERE user_id = ? AND is_win = 1 AND DATE(played_at) = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("is", $userId, $date);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    return $data['count'] ?? 0;
}

/**
 * Cập nhật tiến độ Events tự động
 * Gọi hàm này sau khi logGameHistory để tự động cập nhật events
 */
function updateEventProgress(mysqli $conn, int $userId, string $actionType, float $actionValue)
{
    // Kiểm tra bảng events có tồn tại không
    $checkTable = $conn->query("SHOW TABLES LIKE 'events'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        return; // Bảng chưa tồn tại, không làm gì
    }

    // Tìm các sự kiện đang active mà user đã tham gia
    $sql = "SELECT ep.*, e.*
            FROM event_participants ep
            JOIN events e ON ep.event_id = e.id
            WHERE ep.user_id = ?
            AND e.status = 'active'
            AND e.is_active = 1
            AND NOW() BETWEEN e.start_time AND e.end_time
            AND ep.is_completed = 0
            AND e.requirement_type = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return;
    }

    $stmt->bind_param("is", $userId, $actionType);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($event = $result->fetch_assoc()) {
        // Cập nhật tiến độ
        $newProgress = $event['progress'] + $actionValue;

        // Kiểm tra đã hoàn thành chưa
        $isCompleted = ($newProgress >= $event['requirement_value']);

        $conn->begin_transaction();
        try {
            // Cập nhật progress
            $updateSql = "UPDATE event_participants 
                         SET progress = ?, is_completed = ?, completed_at = ?
                         WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $completedAt = $isCompleted ? date('Y-m-d H:i:s') : null;
            $updateStmt->bind_param("diss", $newProgress, $isCompleted, $completedAt, $event['id']);
            $updateStmt->execute();
            $updateStmt->close();

            // Ghi lại progress log
            $logSql = "INSERT INTO event_progress (participant_id, action_type, action_value) 
                      VALUES (?, ?, ?)";
            $logStmt = $conn->prepare($logSql);
            $logStmt->bind_param("isd", $event['id'], $actionType, $actionValue);
            $logStmt->execute();
            $logStmt->close();

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Event progress update error: " . $e->getMessage());
        }
    }

    $stmt->close();
}

/**
 * Ghi lại lịch sử game và tự động cập nhật Events
 */
function logGameHistoryWithEvents(mysqli $conn, int $userId, string $gameName, float $betAmount = 0, float $winAmount = 0, bool $isWin = false)
{
    // Ghi lại game history
    $result = logGameHistory($conn, $userId, $gameName, $betAmount, $winAmount, $isWin);

    if ($result) {
        // Cập nhật events progress
        // play_games: mỗi game = +1
        updateEventProgress($conn, $userId, 'play_games', 1);

        // win_games: mỗi lần thắng = +1
        if ($isWin) {
            updateEventProgress($conn, $userId, 'win_games', 1);
        }

        // earn_money: số gtlm kiếm được (win_amount - bet_amount nếu thắng)
        if ($isWin && $winAmount > $betAmount) {
            $earned = $winAmount - $betAmount;
            updateEventProgress($conn, $userId, 'earn_money', $earned);
        }

        // big_win: nếu thắng lớn
        if ($isWin && $winAmount >= 1000000) {
            updateEventProgress($conn, $userId, 'big_win', $winAmount);
        }
    }

    return $result;
}

/**
 * Cập nhật streak khi chơi game
 * Gọi hàm này sau khi logGameHistory để tự động cập nhật streak
 */
function updateStreak(mysqli $conn, int $userId)
{
    // Kiểm tra bảng user_streaks có tồn tại không
    $checkTable = $conn->query("SHOW TABLES LIKE 'user_streaks'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        return; // Bảng chưa tồn tại, không làm gì
    }

    $today = date('Y-m-d');

    // Lấy thông tin streak hiện tại
    $sql = "SELECT * FROM user_streaks WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return;
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $streakData = $result->fetch_assoc();
    $stmt->close();

    // Nếu chưa có record, tạo mới
    if (!$streakData) {
        $sql = "INSERT INTO user_streaks (user_id, current_streak, longest_streak, last_play_date, total_days_played)
                VALUES (?, 1, 1, ?, 1)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $userId, $today);
        $stmt->execute();
        $stmt->close();
        return;
    }

    $lastPlayDate = $streakData['last_play_date'];
    $currentStreak = $streakData['current_streak'] ?? 0;
    $longestStreak = $streakData['longest_streak'] ?? 0;
    $totalDaysPlayed = $streakData['total_days_played'] ?? 0;

    // Tính toán streak mới
    $newStreak = 1;
    $newTotalDays = $totalDaysPlayed;

    if ($lastPlayDate) {
        $lastDate = new DateTime($lastPlayDate);
        $todayDate = new DateTime($today);
        $diff = $lastDate->diff($todayDate)->days;

        if ($diff == 0) {
            // Cùng ngày, không tăng streak
            $newStreak = $currentStreak;
        } elseif ($diff == 1) {
            // Ngày hôm qua, tiếp tục streak
            $newStreak = $currentStreak + 1;
            $newTotalDays = $totalDaysPlayed + 1;
        } else {
            // Cách nhiều ngày, reset streak
            $newStreak = 1;
            $newTotalDays = $totalDaysPlayed + 1;
        }
    } else {
        // Lần đầu chơi
        $newStreak = 1;
        $newTotalDays = 1;
    }

    // Cập nhật longest streak nếu cần
    $newLongestStreak = max($longestStreak, $newStreak);

    // Tính bonus multiplier
    $streakBonus = 1.00;
    if ($newStreak >= 30) {
        $streakBonus = 2.00;
    } elseif ($newStreak >= 14) {
        $streakBonus = 1.50;
    } elseif ($newStreak >= 7) {
        $streakBonus = 1.25;
    } elseif ($newStreak >= 3) {
        $streakBonus = 1.10;
    }

    // Cập nhật database
    $sql = "UPDATE user_streaks 
            SET current_streak = ?, 
                longest_streak = ?, 
                last_play_date = ?, 
                total_days_played = ?,
                streak_bonus_multiplier = ?
            WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisidi", $newStreak, $newLongestStreak, $today, $newTotalDays, $streakBonus, $userId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Ghi lại lịch sử game và tự động cập nhật Streak
 */
function logGameHistoryWithStreak(mysqli $conn, int $userId, string $gameName, float $betAmount = 0, float $winAmount = 0, bool $isWin = false)
{
    // Ghi lại game history
    $result = logGameHistory($conn, $userId, $gameName, $betAmount, $winAmount, $isWin);

    if ($result) {
        // Cập nhật streak
        updateStreak($conn, $userId);
    }

    return $result;
}

/**
 * Cập nhật VIP total_spent khi chơi game
 */
function updateVipSpent(mysqli $conn, int $userId, float $betAmount)
{
    // Kiểm tra bảng user_vip có tồn tại không
    $checkTable = $conn->query("SHOW TABLES LIKE 'user_vip'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        return; // Bảng chưa tồn tại
    }

    // Cập nhật total_spent
    $sql = "UPDATE user_vip SET total_spent = total_spent + ? WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("di", $betAmount, $userId);
        $stmt->execute();
        $stmt->close();
    }

    // Kiểm tra và nâng cấp VIP level nếu cần
    $sql = "SELECT uv.total_spent, uv.vip_level 
            FROM user_vip uv 
            WHERE uv.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userVip = $result->fetch_assoc();
    $stmt->close();

    if ($userVip) {
        // Tìm VIP level phù hợp
        $sql = "SELECT level FROM vip_levels 
                WHERE required_spent <= ? 
                ORDER BY level DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("d", $userVip['total_spent']);
        $stmt->execute();
        $result = $stmt->get_result();
        $newLevel = $result->fetch_assoc();
        $stmt->close();

        if ($newLevel && $newLevel['level'] > $userVip['vip_level']) {
            // Nâng cấp VIP level
            $sql = "UPDATE user_vip SET vip_level = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $newLevel['level'], $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

/**
 * Cập nhật Reward Points khi chơi game
 */
function updateRewardPoints(mysqli $conn, int $userId, float $betAmount, float $winAmount, bool $isWin)
{
    // Kiểm tra bảng reward_points có tồn tại không
    $checkTable = $conn->query("SHOW TABLES LIKE 'reward_points'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        return; // Bảng chưa tồn tại
    }

    // Tính điểm thưởng: 1 điểm cho mỗi 10,000 gtlm cược, bonus khi thắng
    $basePoints = floor($betAmount / 10000);
    $winBonus = $isWin ? floor($winAmount / 20000) : 0;
    $totalPoints = $basePoints + $winBonus;

    if ($totalPoints <= 0) {
        return;
    }

    // Cập nhật points
    $sql = "INSERT INTO reward_points (user_id, total_points, available_points, lifetime_points)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            total_points = total_points + VALUES(total_points),
            available_points = available_points + VALUES(available_points),
            lifetime_points = lifetime_points + VALUES(lifetime_points)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $userId, $totalPoints, $totalPoints, $totalPoints);
    $stmt->execute();
    $stmt->close();

    // Ghi transaction
    $checkTransactions = $conn->query("SHOW TABLES LIKE 'reward_point_transactions'");
    if ($checkTransactions && $checkTransactions->num_rows > 0) {
        $description = "Chơi game: +$basePoints điểm" . ($winBonus > 0 ? " + $winBonus điểm thưởng thắng" : "");
        $sql = "INSERT INTO reward_point_transactions 
                (user_id, points, transaction_type, description)
                VALUES (?, ?, 'earn_game', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $userId, $totalPoints, $description);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Kiểm tra xem có event nhân hệ số tiền thưởng nào đang active không
 */
function getActiveEventMultiplier(mysqli $conn, string $type): float {
    $sql = "SELECT config FROM random_events WHERE is_active = 1 AND ends_at > NOW() AND event_type = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 1.0;
    $stmt->bind_param("s", $type);
    $stmt->execute();
    $res = $stmt->get_result();
    $event = $res->fetch_assoc();
    $stmt->close();

    if (!$event) return 1.0;
    $config = json_decode($event['config'], true);
    
    if ($type === 'double_win') {
        return (float)($config['win_multiplier'] ?? 1.0);
    }
    
    return 1.0;
}

/**
 * Đếm số lần thua liên tiếp của user
 */
function get_current_lose_streak(mysqli $conn, int $userId) {
    $sql = "SELECT is_win FROM game_history WHERE user_id = ? ORDER BY played_at DESC LIMIT 20";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $streak = 0;
    while ($row = $res->fetch_assoc()) {
        if (!$row['is_win']) $streak++;
        else break;
    }
    $stmt->close();
    return $streak;
}

/**
 * Kiểm tra và trao huy hiệu trận đấu (Badges)
 */
function checkGameBadges(mysqli $conn, int $userId, string $gameName, float $betAmount, float $winAmount, bool $isWin) {
    // 1. Lấy danh sách huy hiệu user chưa có
    $sql = "SELECT a.* FROM achievements a 
            LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
            WHERE ua.id IS NULL AND a.requirement_type IS NOT NULL";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return;
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $achievements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($achievements)) return;

    require_once __DIR__ . '/notification_helper.php';

    foreach ($achievements as $ach) {
        $type = $ach['requirement_type'];
        $target = (float)$ach['requirement_value'];
        $isUnlocked = false;

        switch ($type) {
            case 'total_games':
                $res = $conn->query("SELECT COUNT(*) as count FROM game_history WHERE user_id = $userId");
                if ($res && $row = $res->fetch_assoc()) {
                    if ($row['count'] >= $target) $isUnlocked = true;
                }
                break;
            case 'win_streak':
                if ($isWin) {
                    if (function_exists('get_current_win_streak')) {
                        if (get_current_win_streak($conn, $userId) >= $target) $isUnlocked = true;
                    }
                }
                break;
            case 'night_owl':
                $hour = (int)date('H');
                if ($hour >= 2 && $hour <= 4) $isUnlocked = true;
                break;
            case 'lose_streak_survive':
                if (!$isWin) {
                    if (get_current_lose_streak($conn, $userId) >= $target) $isUnlocked = true;
                }
                break;
            case 'single_win':
                if ($winAmount >= $target) $isUnlocked = true;
                break;
            default:
                // Xử lý game_count:name
                if (strpos($type, 'game_count:') === 0) {
                    $targetGame = str_replace('game_count:', '', $type);
                    if (stripos($gameName, $targetGame) !== false) {
                        $res = $conn->query("SELECT COUNT(*) as count FROM game_history WHERE user_id = $userId AND game_name LIKE '%$targetGame%'");
                        if ($res && $row = $res->fetch_assoc()) {
                            if ($row['count'] >= $target) $isUnlocked = true;
                        }
                    }
                }
                break;
        }

        if ($isUnlocked) {
            $achId = $ach['id'];
            // Trao huy hiệu
            $conn->query("INSERT INTO user_achievements (user_id, achievement_id) VALUES ($userId, $achId)");
            
            // Phần thưởng nếu có
            if ($ach['reward_money'] > 0) {
                $conn->query("UPDATE users SET Money = Money + {$ach['reward_money']} WHERE Iduser = $userId");
            }

            // Thông báo
            if (function_exists('notifyAchievement')) {
                notifyAchievement($conn, $userId, $achId, $ach['name']);
            }
        }
    }
}

/**
 * Ghi lại lịch sử game và tự động cập nhật Streak + VIP + Reward Points + Social Feed + Materials + Dungeons + Badges
 */
function logGameHistoryWithAll(mysqli $conn, int $userId, string $gameName, float $betAmount = 0, float $winAmount = 0, bool $isWin = false)
{
    // Ghi lại game history
    $result = logGameHistory($conn, $userId, $gameName, $betAmount, $winAmount, $isWin);

    if ($result) {
        // --- 🎯 CORE DAILY GAMIFICATION MECHANICS ---
        require_once __DIR__ . '/lucky_hour_helper.php';
        require_once __DIR__ . '/daily_challenge_helper.php';
        require_once __DIR__ . '/user_buff_helper.php';

        // A. Secret Lucky Hour Bonus (+20%)
        if ($isWin && $winAmount > 0) {
            $lhBonus = LuckyHourHelper::applyLuckyHourBonus($conn, $userId, $winAmount, $gameName);
            if ($lhBonus > 0) {
                $winAmount += $lhBonus;
                if (!isset($_SESSION['pending_notifications'])) $_SESSION['pending_notifications'] = [];
                $_SESSION['pending_notifications'][] = [
                    'type' => 'success',
                    'title' => '🕵️‍♂️ SECRET LUCKY HOUR!',
                    'message' => 'Bạn đã phát hiện Lucky Hour bí mật! Nhận thêm +20% lộc húp (+ ' . number_format($lhBonus) . ' GTLM)!'
                ];
            }
        }

        // B. Daily Game Challenges Check
        DailyChallengeHelper::checkAndComplete($conn, $userId, $gameName, $betAmount, $winAmount, $isWin);

        // C. Spectator Hype Buff check (+20% payout multiplier)
        if ($isWin && $winAmount > 0) {
            $hypeUses = UserBuffHelper::getBuffUses($conn, $userId, 'hype');
            if ($hypeUses > 0) {
                $hypeBonus = round($winAmount * 0.20);
                if ($hypeBonus > 0) {
                    UserBuffHelper::consumeBuff($conn, $userId, 'hype');
                    $conn->query("UPDATE users SET Money = Money + $hypeBonus WHERE Iduser = $userId");
                    $conn->query("INSERT INTO bot_transactions (user_id, amount, type, reason) VALUES ($userId, $hypeBonus, 'receive', 'Cổ vũ từ Người xem: Buff Hype kích hoạt (+20% lộc)')");
                    $winAmount += $hypeBonus;
                    
                    if (!isset($_SESSION['pending_notifications'])) $_SESSION['pending_notifications'] = [];
                    $_SESSION['pending_notifications'][] = [
                        'type' => 'success',
                        'title' => '🔥 HYPE BOOST!',
                        'message' => 'Buff Hype từ người xem cổ vũ kích hoạt! Nhận thêm +20% lộc húp (+ ' . number_format($hypeBonus) . ' GTLM)!'
                    ];
                }
            }
        }

        // D. Spectator Shield Loss Protection check (50% loss protected)
        if (!$isWin) {
            $shieldRefund = UserBuffHelper::applyLossProtection($conn, $userId, $betAmount);
            if ($shieldRefund > 0) {
                if (!isset($_SESSION['pending_notifications'])) $_SESSION['pending_notifications'] = [];
                $_SESSION['pending_notifications'][] = [
                    'type' => 'info',
                    'title' => '🛡️ KHIÊN HỘ MỆNH KÍCH HOẠT!',
                    'message' => 'Khiên bảo vệ từ người xem đã kích hoạt! Bảo hiểm hoàn lại 50% số lượng liều (+ ' . number_format($shieldRefund) . ' GTLM)!'
                ];
            }
        }

        // Cập nhật Guild War Points
        require_once __DIR__ . '/guild_war_helper.php';
        updateGuildWarPoints($conn, $userId, $winAmount, $betAmount, $gameName);

        // Cập nhật Battle Pass missions
        require_once __DIR__ . '/api_battle_pass.php';
        updateBPMission($conn, $userId, 'play_game', 1);
        if ($winAmount > 0) {
            updateBPMission($conn, $userId, 'win_money', $winAmount);
        }

        // Cập nhật streak
        updateStreak($conn, $userId);

        // Cập nhật VIP spent
        updateVipSpent($conn, $userId, $betAmount);

        // Cập nhật reward points
        updateRewardPoints($conn, $userId, $betAmount, $winAmount, $isWin);

        // Cập nhật Jackpot
        require_once __DIR__ . '/api_jackpot.php';
        contributeToJackpot($conn, $betAmount);
        $jackpotWin = checkJackpotWin($conn, $userId);
        if ($jackpotWin > 0) {
            require_once __DIR__ . '/api_notifications.php';
            sendNotification($conn, $userId, "🎉 NỔ HŨ RỒNG THẦN!", "Chúc mừng! Bạn vừa nổ hũ và nhận được " . number_format($jackpotWin) . " GTLM!", "system");
        }

        // Cập nhật tournament score
        updateTournamentScore($conn, $userId, $gameName, $winAmount);

        // --- GUILD VS GUILD TOURNAMENT SCORE TRACKING ---
        try {
            // Check if player is in a guild
            $gSql = "SELECT guild_id FROM guild_members WHERE user_id = ? LIMIT 1";
            $gStmt = $conn->prepare($gSql);
            if ($gStmt) {
                $gStmt->bind_param("i", $userId);
                $gStmt->execute();
                $gRes = $gStmt->get_result()->fetch_assoc();
                $gStmt->close();
                
                if ($gRes) {
                    $guildId = (int)$gRes['guild_id'];
                    
                    // Check active guild tournament
                    $gtSql = "SELECT id FROM guild_tournaments WHERE status = 'active' LIMIT 1";
                    $gtRes = $conn->query($gtSql);
                    if ($gtRes && $gtRes->num_rows > 0) {
                        $gtId = (int)$gtRes->fetch_assoc()['id'];
                        
                        // Calculate contributed tournament points:
                        // 1 point minimum for any win, + 1 point for every 10,000 GTLM won!
                        if ($isWin && $winAmount > 0) {
                            $pts = max(1, (int)floor($winAmount / 10000));
                            
                            // Insert or update score
                            $scoreStmt = $conn->prepare("
                                INSERT INTO guild_tournament_scores (tournament_id, guild_id, user_id, points)
                                VALUES (?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE points = points + ?
                            ");
                            if ($scoreStmt) {
                                $scoreStmt->bind_param("iiiii", $gtId, $guildId, $userId, $pts, $pts);
                                $scoreStmt->execute();
                                $scoreStmt->close();
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("Guild tournament score logging error: " . $e->getMessage());
        }

        // --- Material & Dungeon Integration ---
        require_once __DIR__ . '/material_helper.php';
        require_once __DIR__ . '/dungeon_helper.php';

        // 1. Roll for material drop
        $drop = roll_material_drop($conn, $userId, $gameName, $isWin ? 'win' : 'lose', $betAmount, $winAmount, 0);
        if ($drop) {
            // Save to session so frontend can show notification
            if (!isset($_SESSION['last_drops'])) $_SESSION['last_drops'] = [];
            $_SESSION['last_drops'][] = $drop;
        }

        // 2. Update Dungeon Progress
        update_dungeon_progress($conn, $userId, 'hunt', 1);
        if ($isWin) {
            update_dungeon_progress($conn, $userId, 'accumulate', $winAmount);
        }
        update_dungeon_progress($conn, $userId, 'specialist', 1, $gameName);
        
        // survivor: high bet
        if ($betAmount >= 500000) {
            update_dungeon_progress($conn, $userId, 'survivor', $betAmount);
        }
        // streak: win streak
        $winStreak = get_current_win_streak($conn, $userId);
        update_dungeon_progress($conn, $userId, 'streak', $winStreak);
        
        $today = date('Y-m-d');
        $stmtExp = $conn->prepare("SELECT COUNT(DISTINCT game_name) as cnt FROM game_history WHERE user_id = ? AND DATE(played_at) = ?");
        $stmtExp->bind_param("is", $userId, $today);
        $stmtExp->execute();
        $distinctGames = $stmtExp->get_result()->fetch_assoc()['cnt'];
        update_dungeon_progress($conn, $userId, 'explorer', $distinctGames);

        // Tạo feed activity cho big win
        if ($isWin && $winAmount >= 5000000) {
            $checkFeed = $conn->query("SHOW TABLES LIKE 'social_feed'");
            if ($checkFeed && $checkFeed->num_rows > 0) {
                $userSql = "SELECT Name FROM users WHERE Iduser = ?";
                $userStmt = $conn->prepare($userSql);
                if ($userStmt) {
                    $userStmt->bind_param("i", $userId);
                    $userStmt->execute();
                    $userResult = $userStmt->get_result();
                    $user = $userResult->fetch_assoc();
                    $userStmt->close();

                    if ($user) {
                        require_once __DIR__ . '/notification_helper.php';
                        $feedMessage = "🎉 " . htmlspecialchars($user['Name']) . " vừa thắng lớn " . number_format($winAmount, 0, ',', '.') . " gtlm trong " . $gameName . "!";
                        createFeedActivity($conn, $userId, 'big_win', $feedMessage, ['game' => $gameName, 'amount' => $winAmount]);
                    }
                }
            }
        }
        
        // 3. Update Community Challenges
        updateCommunityChallenge($conn, $userId, 'game_played', 1);
        if ($isWin) {
            updateCommunityChallenge($conn, $userId, 'game_won', 1);
        }

        // --- NEW EVENT SYSTEM INTEGRATION ---
        // 1. Game of the Day & XP Multipliers
        $gotd = EventHelper::getGameOfTheDay($conn);
        $xpMultiplier = ($gameName === $gotd) ? 2.0 : 1.0;
        
        // 2. Combo Streak
        $combo = EventHelper::handleComboStreak($conn, $userId, $gameName);
        if ($combo) {
            if (!isset($_SESSION['pending_notifications'])) $_SESSION['pending_notifications'] = [];
            $_SESSION['pending_notifications'][] = ['type' => 'success', 'title' => 'COMBO STREAK!', 'message' => $combo['message']];
            $xpMultiplier += $combo['bonus_percent'];
        }

        // 3. Update Daily Tournament Score (Game of the Day only)
        if ($gameName === $gotd && $winAmount > 0) {
            EventHelper::updateDailyScore($conn, $userId, $winAmount);
        }

        // ── 🔥 RANDOM EVENT MULTIPLIERS (double_win & golden_hour) ──────────
        // Bug fix: Trước đây các random_event này chỉ xuất hiện trên banner,
        // không thực sự ảnh hưởng đến game. Nay được tích hợp tại đây.

        // double_win: nhân đôi số tiền thắng (có giới hạn max_bonus_per_win)
        if ($isWin && $winAmount > 0) {
            $dwEvent = $conn->query("
                SELECT config FROM random_events
                WHERE is_active = 1 AND event_type = 'double_win' AND ends_at > NOW()
                LIMIT 1
            ")->fetch_assoc();
            if ($dwEvent) {
                $dwCfg    = json_decode($dwEvent['config'], true);
                $dwMult   = (float)($dwCfg['win_multiplier']     ?? 2.0);
                $dwCap    = (float)($dwCfg['max_bonus_per_win']  ?? 500000);
                $dwBonus  = min(round($winAmount * ($dwMult - 1)), $dwCap);
                if ($dwBonus > 0) {
                    $conn->query("UPDATE users SET Money = Money + $dwBonus WHERE Iduser = $userId");
                    $conn->query("INSERT INTO bot_transactions (user_id, amount, type, reason)
                                  VALUES ($userId, $dwBonus, 'receive', 'Random Event: Nhân Đôi Chiến Thắng (+bonus)')");
                    $winAmount += $dwBonus;
                    if (!isset($_SESSION['pending_notifications'])) $_SESSION['pending_notifications'] = [];
                    $_SESSION['pending_notifications'][] = [
                        'type'    => 'success',
                        'title'   => '🔥 NHÂN ĐÔI CHIẾN THẮNG!',
                        'message' => 'Random Event kích hoạt! Nhận thêm +' . number_format($dwBonus) . ' GTLM từ buff x' . $dwMult . '!'
                    ];
                }
            }

            // weekend_boost: cũng nhân tiền thắng (win_multiplier 3x)
            $wbEvent = $conn->query("
                SELECT config FROM random_events
                WHERE is_active = 1 AND event_type = 'weekend_boost' AND ends_at > NOW()
                LIMIT 1
            ")->fetch_assoc();
            if ($wbEvent) {
                $wbCfg   = json_decode($wbEvent['config'], true);
                $wbMult  = (float)($wbCfg['win_multiplier'] ?? 3.0);
                $wbBonus = round($winAmount * ($wbMult - 1));
                if ($wbBonus > 0) {
                    $conn->query("UPDATE users SET Money = Money + $wbBonus WHERE Iduser = $userId");
                    $winAmount += $wbBonus;
                }
                // XP multiplier từ weekend_boost
                $wbXpMult = (float)($wbCfg['xp_multiplier'] ?? 1.5);
                $xpMultiplier *= $wbXpMult;
            }
        }

        // golden_hour: nhân đôi XP
        $ghEvent = $conn->query("
            SELECT config FROM random_events
            WHERE is_active = 1 AND event_type = 'golden_hour' AND ends_at > NOW()
            LIMIT 1
        ")->fetch_assoc();
        if ($ghEvent) {
            $ghCfg      = json_decode($ghEvent['config'], true);
            $ghXpMult   = (float)($ghCfg['xp_multiplier'] ?? 2.0);
            $xpMultiplier *= $ghXpMult;
        }
        // ────────────────────────────────────────────────────────────────────

        // 4. Award XP (Base XP + Multipliers)
        $baseXP = 10; 
        if ($isWin) $baseXP += 15;
        if ($betAmount >= 100000) $baseXP += 10;
        
        $finalXP = (int)round($baseXP * $xpMultiplier);
        
        // Add to User Level progress
        if (file_exists('user_progress_helper.php')) {
            require_once __DIR__ . '/user_progress_helper.php';
            up_add_xp($conn, $userId, $finalXP);
        }
        
        // Add to Seasonal Pass XP
        EventHelper::addSeasonalXP($conn, $userId, $finalXP);

        // 5. Check Game Badges (New Achievement System)
        checkGameBadges($conn, $userId, $gameName, $betAmount, $winAmount, $isWin);

        // 6. Update Seasonal Event Mission Progress
        updateEventMissionProgress($conn, $userId, $gameName, $betAmount, $winAmount, $isWin);
    }

    // --- REFERRAL CHAIN COMMISSION (30 Days) ---
    try {
        $uInfoQuery = $conn->query("SELECT referred_by, referral_date FROM users WHERE Iduser = $userId");
        if ($uInfoQuery) {
            $uInfo = $uInfoQuery->fetch_assoc();
            if ($isWin && $uInfo && !empty($uInfo['referred_by'])) {
                $referralDate = !empty($uInfo['referral_date']) ? $uInfo['referral_date'] : date('Y-m-d H:i:s');
                $daysSinceRef = (time() - strtotime($referralDate)) / 86400;
                if ($daysSinceRef <= 30) {
                    $commission = floor($winAmount * 0.01); // 1% hoa hồng từ lộc
                    if ($commission > 0) {
                        $referrerId = (int)$uInfo['referred_by'];
                        $conn->query("UPDATE users SET Money = Money + $commission WHERE Iduser = $referrerId");
                        $conn->query("INSERT INTO referral_commissions (referrer_id, referee_id, amount, game_name) VALUES ($referrerId, $userId, $commission, '$gameName')");
                        
                        // Thông báo cho người giới thiệu
                        require_once __DIR__ . '/notification_helper.php';
                        if (function_exists('addNotification')) {
                            addNotification($conn, $referrerId, 'referral', "Bạn vừa húp được " . number_format($commission) . " GTLM lộc từ thành viên giới thiệu tại ván $gameName!", 'success');
                        }
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        // Cột referred_by có thể chưa được thêm vào database, bỏ qua
    }

    // --- REAL-TIME ALERTS & BOT MEMORY ---
    if ($isWin && $winAmount >= 500000) { 
        $alertMsg = "User ID #$userId vừa húp " . number_format($winAmount) . " GTLM tại $gameName";
        $conn->query("INSERT INTO admin_alerts (type, user_id, message, severity) VALUES ('big_win', $userId, '$alertMsg', 'info')");
        
        // Feed the bots' memory
        $uData = $conn->query("SELECT Name FROM users WHERE Iduser = $userId")->fetch_assoc();
        $userName = $uData ? $uData['Name'] : 'Ai đó';
        $val = json_encode(['game' => $gameName, 'amount' => $winAmount]);
        $conn->query("INSERT INTO arena_memory (event_type, user_id, target_name, value) VALUES ('big_win', $userId, '$userName', '$val')");

        // 🖋️ GHI VÀO SỬ KÝ (Nếu thắng cực lớn)
        if ($winAmount >= 10000000) {
            require_once __DIR__ . '/lore_helper.php';
            $loreDesc = generateBigWinLore($userName, $gameName, $winAmount);
            recordServerLore($conn, 'record', "Đại Thắng Tại $gameName", $loreDesc, ($winAmount >= 100000000 ? 3 : 2));
        }
    }

    return $result;
}

/**
 * Cập nhật điểm số giải đấu
 */
function updateTournamentScore(mysqli $conn, int $userId, string $gameName, float $winAmount) {
    if ($winAmount <= 0) return;
    
    // Tìm giải đấu đang diễn ra cho loại game này
    $sql = "SELECT id FROM tournaments WHERE status = 'Ongoing' AND (game_type = ? OR game_type = 'All') LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return;
    
    $stmt->bind_param("s", $gameName);
    $stmt->execute();
    $res = $stmt->get_result();
    $tour = $res->fetch_assoc();
    $stmt->close();
    
    if ($tour) {
        $tourId = $tour['id'];
        // Kiểm tra xem user có tham gia giải đấu này không
        $stmt = $conn->prepare("SELECT id FROM tournament_participants WHERE tournament_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $tourId, $userId);
        $stmt->execute();
        $isParticipant = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        
        if ($isParticipant) {
            // Cập nhật điểm (Win amount = Score)
            $conn->query("INSERT INTO tournament_scores (tournament_id, user_id, score) VALUES ($tourId, $userId, $winAmount)
                         ON DUPLICATE KEY UPDATE score = score + $winAmount");
        }
    }
}

/**
 * Cập nhật tiến độ nhiệm vụ cộng đồng
 */
function updateCommunityChallenge(mysqli $conn, int $userId, string $type, int $value) {
    // Tìm các nhiệm vụ đang active
    // Ví dụ type: game_played (tổng số ván chơi), game_won (tổng số ván thắng)
    $res = $conn->query("SELECT id, target_count, current_count FROM community_challenges WHERE status = 'active'");
    if ($res && $res->num_rows > 0) {
        while ($challenge = $res->fetch_assoc()) {
            $challengeId = $challenge['id'];
            
            // Cập nhật current_count của challenge
            $conn->query("UPDATE community_challenges SET current_count = current_count + $value WHERE id = $challengeId");
            
            // Cập nhật contribution của user
            $conn->query("INSERT INTO community_challenge_participation (challenge_id, user_id, contribution) 
                          VALUES ($challengeId, $userId, $value)
                          ON DUPLICATE KEY UPDATE contribution = contribution + $value");
            
            // Kiểm tra hoàn thành
            if ($challenge['current_count'] + $value >= $challenge['target_count']) {
                $conn->query("UPDATE community_challenges SET status = 'completed' WHERE id = $challengeId");
            }
        }
    }
}

/**
 * Cập nhật tiến độ nhiệm vụ sự kiện mùa giải (Seasonal Event Missions)
 */
function updateEventMissionProgress(mysqli $conn, int $userId, string $gameName, float $betAmount, float $winAmount, bool $isWin) {
    // 1. Kiểm tra sự kiện mùa giải đang active (dùng helper tập trung)
    $activeEvent = getActiveSeasonalEvent($conn, false, 'id');
    if (!$activeEvent) return;
    $eventId = (int)$activeEvent['id'];

    // 2. Lấy danh sách nhiệm vụ của sự kiện
    $stmt = $conn->prepare("SELECT * FROM event_missions WHERE event_id = ?");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $missions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($missions)) return;

    $knownGames = [
        'baccarat' => 'Baccarat',
        'crash' => 'Crash',
        'blackjack' => 'Blackjack',
        'roulette' => 'Roulette',
        'sicbo' => 'Sicbo',
        'taixiu' => 'Tài Xỉu',
        'baucua' => 'Bầu Cua',
        'daga' => 'Đá Gà',
        'dragontiger' => 'Rồng Hổ',
        'slot' => 'Slot',
        'poker' => 'Poker',
        'keno' => 'Keno',
        'plinko' => 'Plinko',
        'mines' => 'Mines',
    ];

    foreach ($missions as $m) {
        $mId = (int)$m['id'];
        $type = $m['mission_type'];
        $title = $m['title'];

        // Kiểm tra xem nhiệm vụ có giới hạn game cụ thể nào không
        $limitedToGame = null;
        foreach ($knownGames as $key => $name) {
            if (mb_stripos($title, $name) !== false || mb_stripos($title, $key) !== false) {
                $limitedToGame = $key;
                break;
            }
        }

        // Nếu nhiệm vụ giới hạn cho game khác game đang chơi → bỏ qua
        if ($limitedToGame !== null && mb_stripos($gameName, $limitedToGame) === false) {
            continue;
        }

        // Xác định số lượng tăng thêm cho nhiệm vụ này
        $increment = 0;
        if ($type === 'play_game') {
            $increment = 1;
        } elseif ($type === 'win_game' && $isWin) {
            $increment = 1;
        } elseif ($type === 'bet') {
            $increment = $betAmount;
        } elseif ($type === 'earn_money') {
            $increment = max(0, $winAmount - $betAmount);
        } elseif ($type === 'big_win' && $isWin && $winAmount >= 500000) {
            $increment = 1;
        }

        if ($increment <= 0) continue;

        // Cập nhật tiến trình trong user_mission_progress (Atomic INSERT/UPDATE)
        $stmtUp = $conn->prepare("
            INSERT INTO user_mission_progress (user_id, mission_id, current_value, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                current_value = current_value + VALUES(current_value),
                updated_at = NOW()
        ");
        $stmtUp->bind_param("iii", $userId, $mId, $increment);
        $stmtUp->execute();
        $stmtUp->close();
    }
}
?>