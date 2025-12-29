<?php
require 'db_connect.php';
// Hàm kiểm tra và cấp danh hiệu dựa trên xếp hạng
function checkAndAwardRankAchievements($conn) {
    // Lấy xếp hạng của tất cả người dùng
    $rankSql = "SELECT Iduser, Money, 
                (SELECT COUNT(*) + 1 FROM users u2 WHERE u2.Money > u1.Money) as rank
                FROM users u1
                ORDER BY Money DESC";
    $rankResult = $conn->query($rankSql);
    
    if (!$rankResult) {
        return;
    }
    
    while ($row = $rankResult->fetch_assoc()) {
        $userId = $row['Iduser'];
        $rank = (int)$row['rank'];
        
        // Chỉ xử lý top 10
        if ($rank > 10) {
            continue;
        }
        
        // Tìm achievement ID cho rank này
        $achievementSql = "SELECT id FROM achievements WHERE requirement_type = 'rank' AND requirement_value = ?";
        $achievementStmt = $conn->prepare($achievementSql);
        if ($achievementStmt) {
            $achievementStmt->bind_param("i", $rank);
            $achievementStmt->execute();
            $achievementResult = $achievementStmt->get_result();
            $achievement = $achievementResult->fetch_assoc();
            $achievementStmt->close();
            
            if ($achievement) {
                $achievementId = $achievement['id'];
                
                // Kiểm tra xem đã có achievement này chưa
                $checkSql = "SELECT * FROM user_achievements WHERE user_id = ? AND achievement_id = ?";
                $checkStmt = $conn->prepare($checkSql);
                if ($checkStmt) {
                    $checkStmt->bind_param("ii", $userId, $achievementId);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();
                    $checkStmt->close();
                    
                    // Nếu chưa có, cấp danh hiệu
                    if ($checkResult->num_rows == 0) {
                        // Thêm vào user_achievements
                        $insertSql = "INSERT INTO user_achievements (user_id, achievement_id) VALUES (?, ?)";
                        $insertStmt = $conn->prepare($insertSql);
                        if ($insertStmt) {
                            $insertStmt->bind_param("ii", $userId, $achievementId);
                            $insertStmt->execute();
                            $insertStmt->close();
                        }
                        
                        // Cấp phần thưởng
                        $rewardSql = "SELECT reward_money, name FROM achievements WHERE id = ?";
                        $rewardStmt = $conn->prepare($rewardSql);
                        if ($rewardStmt) {
                            $rewardStmt->bind_param("i", $achievementId);
                            $rewardStmt->execute();
                            $rewardResult = $rewardStmt->get_result();
                            $rewardData = $rewardResult->fetch_assoc();
                            $rewardStmt->close();
                            
                            if ($rewardData) {
                                if ($rewardData['reward_money'] > 0) {
                                $updateMoneySql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
                                $updateMoneyStmt = $conn->prepare($updateMoneySql);
                                if ($updateMoneyStmt) {
                                    $updateMoneyStmt->bind_param("di", $rewardData['reward_money'], $userId);
                                    $updateMoneyStmt->execute();
                                    $updateMoneyStmt->close();
                                }
                                }
                                
                                // Gửi thông báo
                                require_once 'notification_helper.php';
                                notifyAchievement($conn, $userId, $achievementId, $rewardData['name']);
                            }
                        }
                        
                        // Tự động kích hoạt danh hiệu nếu chưa có danh hiệu nào
                        $checkActiveSql = "SELECT active_title_id FROM users WHERE Iduser = ?";
                        $checkActiveStmt = $conn->prepare($checkActiveSql);
                        if ($checkActiveStmt) {
                            $checkActiveStmt->bind_param("i", $userId);
                            $checkActiveStmt->execute();
                            $checkActiveResult = $checkActiveStmt->get_result();
                            $activeData = $checkActiveResult->fetch_assoc();
                            $checkActiveStmt->close();
                            
                            if (!$activeData || !$activeData['active_title_id']) {
                                // Tự động kích hoạt danh hiệu mới nhận được
                                $updateTitleSql = "UPDATE users SET active_title_id = ? WHERE Iduser = ?";
                                $updateTitleStmt = $conn->prepare($updateTitleSql);
                                if ($updateTitleStmt) {
                                    $updateTitleStmt->bind_param("ii", $achievementId, $userId);
                                    $updateTitleStmt->execute();
                                    $updateTitleStmt->close();
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// Chỉ chứa hàm, không chạy tự động
?>

