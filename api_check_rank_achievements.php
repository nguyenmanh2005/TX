<?php
/**
 * Optimized version: Checks and awards rank achievements for a specific user.
 * This is much faster than checking all users on every page load.
 */
function checkAndAwardRankAchievements($conn, $specificUserId = null) {
    if (!$specificUserId) return; // Only process if a target user is provided

    // Get the rank and money of the specific user
    // We use a subquery to calculate the rank for just this user
    $rankSql = "SELECT Iduser, Money, 
                (SELECT COUNT(*) + 1 FROM users u2 WHERE u2.Money > u1.Money) as rank
                FROM users u1
                WHERE u1.Iduser = ?";
    
    $stmt = $conn->prepare($rankSql);
    if (!$stmt) return;
    
    $stmt->bind_param("i", $specificUserId);
    $stmt->execute();
    $rankResult = $stmt->get_result();
    $row = $rankResult->fetch_assoc();
    $stmt->close();
    
    if (!$row) return;

    $userId = $row['Iduser'];
    $rank = (int)$row['rank'];
    
    // Only handle top 10
    if ($rank > 10) {
        return;
    }
    
    // Find achievement ID for this rank
    $achievementSql = "SELECT id, name, reward_money FROM achievements WHERE requirement_type = 'rank' AND requirement_value = ?";
    $achievementStmt = $conn->prepare($achievementSql);
    if ($achievementStmt) {
        $achievementStmt->bind_param("i", $rank);
        $achievementStmt->execute();
        $achievementResult = $achievementStmt->get_result();
        $achievement = $achievementResult->fetch_assoc();
        $achievementStmt->close();
        
        if ($achievement) {
            $achievementId = $achievement['id'];
            
            // Check if user already has this achievement
            $checkSql = "SELECT id FROM user_achievements WHERE user_id = ? AND achievement_id = ?";
            $checkStmt = $conn->prepare($checkSql);
            if ($checkStmt) {
                $checkStmt->bind_param("ii", $userId, $achievementId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $hasAchievement = ($checkResult->num_rows > 0);
                $checkStmt->close();
                
                // If they don't have it, award it
                if (!$hasAchievement) {
                    $conn->begin_transaction();
                    try {
                        // 1. Add to user_achievements
                        $insertSql = "INSERT INTO user_achievements (user_id, achievement_id) VALUES (?, ?)";
                        $insertStmt = $conn->prepare($insertSql);
                        $insertStmt->bind_param("ii", $userId, $achievementId);
                        $insertStmt->execute();
                        $insertStmt->close();
                        
                        // 2. Grant reward money
                        if ($achievement['reward_money'] > 0) {
                            $updateMoneySql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
                            $updateMoneyStmt = $conn->prepare($updateMoneySql);
                            $updateMoneyStmt->bind_param("di", $achievement['reward_money'], $userId);
                            $updateMoneyStmt->execute();
                            $updateMoneyStmt->close();
                        }
                        
                        // 3. Send notification
                        if (file_exists('notification_helper.php')) {
                            require_once 'notification_helper.php';
                            if (function_exists('notifyAchievement')) {
                                notifyAchievement($conn, $userId, $achievementId, $achievement['name']);
                            }
                        }
                        
                        // 4. Auto-activate title if user has no active title
                        $checkActiveSql = "SELECT active_title_id FROM users WHERE Iduser = ?";
                        $checkActiveStmt = $conn->prepare($checkActiveSql);
                        $checkActiveStmt->bind_param("i", $userId);
                        $checkActiveStmt->execute();
                        $activeData = $checkActiveStmt->get_result()->fetch_assoc();
                        $checkActiveStmt->close();
                        
                        if (!$activeData || !$activeData['active_title_id']) {
                            $updateTitleSql = "UPDATE users SET active_title_id = ? WHERE Iduser = ?";
                            $updateTitleStmt = $conn->prepare($updateTitleSql);
                            $updateTitleStmt->bind_param("ii", $achievementId, $userId);
                            $updateTitleStmt->execute();
                            $updateTitleStmt->close();
                        }
                        
                        $conn->commit();
                    } catch (Exception $e) {
                        $conn->rollback();
                    }
                }
            }
        }
    }
}
?>
