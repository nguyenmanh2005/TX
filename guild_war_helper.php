<?php
/**
 * Guild War Helper
 * Xử lý các logic liên quan đến đua top Bang hội hàng tuần
 */

/**
 * Cộng điểm Guild War khi thành viên thắng game
 * @param mysqli $conn
 * @param int $userId
 * @param float $winAmount
 * @param float $betAmount
 */
function updateGuildWarPoints(mysqli $conn, int $userId, float $winAmount, float $betAmount) {
    if ($winAmount <= $betAmount) return;
    
    // Tìm guild của user
    $sql = "SELECT guild_id FROM guild_members WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $member = $res->fetch_assoc();
    $stmt->close();
    
    if (!$member) return;
    
    $guildId = $member['guild_id'];
    $points = floor(($winAmount - $betAmount) / 1000); // 1 điểm cho mỗi 1000 gtlm lãi
    
    if ($points > 0) {
        // Đảm bảo có dòng trong guild_weekly_stats
        $ensureSql = "INSERT IGNORE INTO guild_weekly_stats (guild_id) VALUES (?)";
        $ensureStmt = $conn->prepare($ensureSql);
        $ensureStmt->bind_param("i", $guildId);
        $ensureStmt->execute();
        $ensureStmt->close();
        
        // Cập nhật điểm tuần
        $updateSql = "UPDATE guild_weekly_stats SET points = points + ?, wins = wins + 1, matches = matches + 1 WHERE guild_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ii", $points, $guildId);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Cộng kinh nghiệm cho Guild
        $expSql = "UPDATE guilds SET experience = experience + ? WHERE id = ?";
        $expStmt = $conn->prepare($expSql);
        $guildExp = ceil($points / 10);
        $expStmt->bind_param("ii", $guildExp, $guildId);
        $expStmt->execute();
        $expStmt->close();
    }

    // Cập nhật điểm cho Guild Challenge (nếu đang trong thời gian thi đấu)
    $challengeSql = "UPDATE guild_challenges 
                    SET challenger_score = CASE WHEN challenger_id = ? THEN challenger_score + ? ELSE challenger_score END,
                        challenged_score = CASE WHEN challenged_id = ? THEN challenged_score + ? ELSE challenged_score END
                    WHERE (challenger_id = ? OR challenged_id = ?) 
                    AND status = 1 
                    AND NOW() BETWEEN start_time AND end_time";
    $challengeStmt = $conn->prepare($challengeSql);
    $profit = $winAmount - $betAmount;
    $challengeStmt->bind_param("ididii", $guildId, $profit, $guildId, $profit, $guildId, $guildId);
    $challengeStmt->execute();
    $challengeStmt->close();
}

/**
 * Lấy các trận thách đấu đang diễn ra của một guild
 */
function getActiveGuildChallenges(mysqli $conn, int $guildId) {
    $sql = "SELECT c.*, g1.name as challenger_name, g1.tag as challenger_tag, 
                   g2.name as challenged_name, g2.tag as challenged_tag
            FROM guild_challenges c
            JOIN guilds g1 ON c.challenger_id = g1.id
            JOIN guilds g2 ON c.challenged_id = g2.id
            WHERE (c.challenger_id = ? OR c.challenged_id = ?) 
            AND c.status = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $guildId, $guildId);
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Lấy danh hiệu (Trophies) của một guild
 */
function getGuildTrophies(mysqli $conn, int $guildId) {
    $sql = "SELECT * FROM guild_trophies WHERE guild_id = ? ORDER BY awarded_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $guildId);
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Lấy danh sách top Guild trong tuần
 */
function getWeeklyGuildLeaderboard(mysqli $conn, int $limit = 10) {
    $sql = "SELECT g.id, g.name, g.tag, g.level, g.leader_id, u.Name as leader_name, 
                   s.points, s.wins, s.matches
            FROM guild_weekly_stats s
            JOIN guilds g ON s.guild_id = g.id
            JOIN users u ON g.leader_id = u.Iduser
            ORDER BY s.points DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Kiểm tra và reset tuần mới (Guild War Reset)
 * Thường gọi hàm này ở header hoặc trang Guild
 */
function checkGuildWarReset(mysqli $conn) {
    // Logic reset tuần (ví dụ: Thứ 2 hàng tuần)
    // Để đơn giản, ta có thể lưu 'last_reset_week' trong một bảng config
    // Nếu chưa có bảng config, ta có thể dùng bảng guild_war_history để check
}
