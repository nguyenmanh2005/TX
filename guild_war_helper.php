<?php
/**
 * Guild War Helper
 * Xử lý các logic liên quan đến đua top Bang hội hàng tuần
 */

/**
 * Cộng điểm Guild War và Lãnh thổ khi thành viên thắng game
 * @param mysqli $conn
 * @param int $userId
 * @param float $winAmount
 * @param float $betAmount
 * @param string $gameName
 */
function updateGuildWarPoints(mysqli $conn, int $userId, float $winAmount, float $betAmount, string $gameName = '') {
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

    // --- 🗺️ TERRITORY WAR LOGIC ---
    if ($points > 0 && !empty($gameName)) {
        $gameToTerritory = [
            'Thiên Thần Ác Quỷ' => 1, 'Xanh Đỏ Đối Kháng' => 1,
            'Rồng Hổ' => 2, 'Poker Texas' => 2, 'Baccarat' => 2, 'Trận Địa Trắng Đỏ' => 2,
            'Bầu Cua' => 3, 'Thế Giới Linh Thú' => 3,
            'Đá Gà' => 4, 'Đua Ngựa' => 4, 'Đại Chiến Thần Kê' => 4
        ];

        $territoryId = 0;
        foreach ($gameToTerritory as $g => $tid) {
            if (stripos($gameName, $g) !== false) { $territoryId = $tid; break; }
        }

        if ($territoryId > 0) {
            // 1. Cộng TP cho Guild tại vùng này
            $conn->query("INSERT INTO guild_territory_points (guild_id, territory_id, tp_amount) 
                          VALUES ($guildId, $territoryId, $points) 
                          ON DUPLICATE KEY UPDATE tp_amount = tp_amount + $points");

            // 2. Cập nhật tổng TP của vùng
            $conn->query("UPDATE territories SET total_tp = total_tp + $points WHERE id = $territoryId");

            // 3. Kiểm tra xem Guild này có đủ điều kiện chiếm đóng không
            // Điều kiện: Top TP tại vùng đó và TP > 5000 (Ví dụ)
            $topGuild = $conn->query("SELECT guild_id, tp_amount FROM guild_territory_points 
                                      WHERE territory_id = $territoryId ORDER BY tp_amount DESC LIMIT 1")->fetch_assoc();
            
            if ($topGuild && $topGuild['guild_id'] == $guildId && $topGuild['tp_amount'] >= 5000) {
                // Kiểm tra xem đã là chủ sở hữu chưa
                $currentOwner = $conn->query("SELECT occupying_guild_id FROM territories WHERE id = $territoryId")->fetch_assoc();
                if ($currentOwner['occupying_guild_id'] != $guildId) {
                    $conn->query("UPDATE territories SET occupying_guild_id = $guildId WHERE id = $territoryId");
                    
                    // Ghi log vào arena_memory để Bot hóng hớt
                    $gName = $conn->query("SELECT Name FROM guilds WHERE id = $guildId")->fetch_assoc()['Name'];
                    $tName = $conn->query("SELECT name FROM territories WHERE id = $territoryId")->fetch_assoc()['name'];
                    $conn->query("INSERT INTO arena_memory (event_type, target_name, value) 
                                  VALUES ('territory_capture', '$gName', '{\"territory\":\"$tName\"}')");
                }
            }
        }
    }
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
