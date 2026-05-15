<?php
/**
 * Helper function để ghi lại lịch sử game vào database
 * Sử dụng trong các game để track quest progress
 */
require_once 'api_event_helper.php';

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
 * Ghi lại lịch sử game và tự động cập nhật Streak + VIP + Reward Points + Social Feed + Materials + Dungeons
 */
function logGameHistoryWithAll(mysqli $conn, int $userId, string $gameName, float $betAmount = 0, float $winAmount = 0, bool $isWin = false)
{
    // Ghi lại game history
    $result = logGameHistory($conn, $userId, $gameName, $betAmount, $winAmount, $isWin);

    if ($result) {
        // Cập nhật Guild War Points
        require_once 'guild_war_helper.php';
        updateGuildWarPoints($conn, $userId, $winAmount, $betAmount);

        // Cập nhật Battle Pass missions
        require_once 'api_battle_pass.php';
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
        require_once 'api_jackpot.php';
        contributeToJackpot($conn, $betAmount);
        $jackpotWin = checkJackpotWin($conn, $userId);
        if ($jackpotWin > 0) {
            require_once 'api_notifications.php';
            sendNotification($conn, $userId, "🎉 NỔ HŨ RỒNG THẦN!", "Chúc mừng! Bạn vừa nổ hũ và nhận được " . number_format($jackpotWin) . " GTLM!", "system");
        }

        // Cập nhật tournament score
        updateTournamentScore($conn, $userId, $gameName, $winAmount);

        // --- Material & Dungeon Integration ---
        require_once 'material_helper.php';
        require_once 'dungeon_helper.php';

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
                        require_once 'notification_helper.php';
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

        // 4. Award XP (Base XP + Multipliers)
        $baseXP = 10; 
        if ($isWin) $baseXP += 15;
        if ($betAmount >= 100000) $baseXP += 10;
        
        $finalXP = (int)round($baseXP * $xpMultiplier);
        
        // Add to User Level progress
        if (file_exists('user_progress_helper.php')) {
            require_once 'user_progress_helper.php';
            up_add_xp($conn, $userId, $finalXP);
        }
        
        // Add to Seasonal Pass XP
        EventHelper::addSeasonalXP($conn, $userId, $finalXP);
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
?>