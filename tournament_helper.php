<?php
require 'db_connect.php';
/**
 * Tournament Helper Functions
 * 
 * Các hàm helper để tích hợp Tournament vào các game
 */

/**
 * Kiểm tra và log game vào tournament nếu user đã đăng ký
 * 
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param string $gameName Tên game (phải khớp với game_type trong tournament)
 * @param float $betAmount Số tiền cược
 * @param float $winAmount Số tiền thắng (0 nếu thua)
 * @param bool $isWin true nếu thắng, false nếu thua
 */
function logTournamentGame($conn, $userId, $gameName, $betAmount, $winAmount, $isWin) {
    // Kiểm tra bảng tournaments có tồn tại không
    $checkTable = $conn->query("SHOW TABLES LIKE 'tournaments'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        return; // Bảng chưa tồn tại, không làm gì
    }
    
    // Tìm các tournament đang active mà user đã đăng ký
    $sql = "SELECT t.id, t.game_type, t.min_bet
            FROM tournaments t
            INNER JOIN tournament_participants tp ON t.id = tp.tournament_id
            WHERE t.status = 'active'
            AND tp.user_id = ?
            AND (t.game_type = 'All' OR t.game_type = ?)
            AND t.min_bet <= ?
            AND NOW() BETWEEN t.start_time AND t.end_time";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isd", $userId, $gameName, $betAmount);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($tournament = $result->fetch_assoc()) {
        // Tính điểm số
        $scorePoints = calculateTournamentScore($betAmount, $winAmount, $isWin);
        
        // Ghi lại game vào tournament
        $conn->begin_transaction();
        try {
            // Insert vào tournament_games
            $insertSql = "INSERT INTO tournament_games 
                         (tournament_id, user_id, game_name, bet_amount, win_amount, is_win, score_points) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("iissddi", 
                $tournament['id'], 
                $userId, 
                $gameName, 
                $betAmount, 
                $winAmount, 
                $isWin ? 1 : 0, 
                $scorePoints
            );
            $insertStmt->execute();
            $insertStmt->close();
            
            // Cập nhật thống kê participant
            $updateSql = "UPDATE tournament_participants 
                         SET total_games = total_games + 1,
                             total_wins = total_wins + ?,
                             total_bet = total_bet + ?,
                             total_win_amount = total_win_amount + ?,
                             score = score + ?
                         WHERE tournament_id = ? AND user_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("iddddii", 
                $isWin ? 1 : 0, 
                $betAmount, 
                $winAmount, 
                $scorePoints, 
                $tournament['id'], 
                $userId
            );
            $updateStmt->execute();
            $updateStmt->close();
            
            // Cập nhật ranking
            updateTournamentRanking($conn, $tournament['id']);
            
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            // Log error nhưng không throw để không ảnh hưởng đến game
            error_log("Tournament log error: " . $e->getMessage());
        }
    }
    
    $stmt->close();
}

/**
 * Tính điểm số cho tournament
 */
function calculateTournamentScore($betAmount, $winAmount, $isWin) {
    if ($isWin) {
        // Thắng: điểm = tiền thắng * 0.1 + tiền cược * 0.05
        return ($winAmount * 0.1) + ($betAmount * 0.05);
    } else {
        // Thua: điểm = tiền cược * 0.01 (khuyến khích chơi)
        return $betAmount * 0.01;
    }
}

/**
 * Cập nhật xếp hạng cho tournament
 */
function updateTournamentRanking($conn, $tournamentId) {
    // Sử dụng ROW_NUMBER() để tính rank
    // MySQL 8.0+ hỗ trợ ROW_NUMBER()
    $sql = "UPDATE tournament_participants tp1
            JOIN (
                SELECT id, 
                       @row_number := @row_number + 1 as new_rank
                FROM tournament_participants, (SELECT @row_number := 0) r
                WHERE tournament_id = ?
                ORDER BY score DESC, total_wins DESC, total_win_amount DESC
            ) tp2 ON tp1.id = tp2.id
            SET tp1.rank = tp2.new_rank
            WHERE tp1.tournament_id = ?";
    
    // Nếu MySQL < 8.0, dùng cách khác
    $version = $conn->server_info;
    if (version_compare($version, '8.0.0', '<')) {
        // Cách tương thích với MySQL cũ
        $sql = "SET @rank = 0;
                UPDATE tournament_participants tp
                SET rank = (
                    SELECT rank FROM (
                        SELECT id, @rank := @rank + 1 as rank
                        FROM tournament_participants
                        WHERE tournament_id = ?
                        ORDER BY score DESC, total_wins DESC, total_win_amount DESC
                    ) r WHERE r.id = tp.id
                )
                WHERE tournament_id = ?";
    }
    
    $stmt = $conn->prepare($sql);
    if (strpos($sql, 'SET') !== false) {
        // Multi-statement
        $conn->multi_query($sql);
        while ($conn->next_result()) {;} // Flush results
    } else {
        $stmt->bind_param("ii", $tournamentId, $tournamentId);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Kiểm tra user có đang tham gia tournament nào không
 */
function getUserActiveTournaments($conn, $userId) {
    $checkTable = $conn->query("SHOW TABLES LIKE 'tournaments'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        return [];
    }
    
    $sql = "SELECT t.*
            FROM tournaments t
            INNER JOIN tournament_participants tp ON t.id = tp.tournament_id
            WHERE tp.user_id = ?
            AND t.status IN ('registration', 'active')
            AND NOW() <= t.end_time";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tournaments = [];
    while ($row = $result->fetch_assoc()) {
        $tournaments[] = $row;
    }
    $stmt->close();
    
    return $tournaments;
}

