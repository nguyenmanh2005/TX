<?php
/**
 * Pet Helper - Buffs and Leveling
 */

function get_active_pet_buff($userId) {
    global $conn;
    $sql = "SELECT p.buff_type, p.buff_value 
            FROM user_pets up 
            JOIN pets p ON up.pet_id = p.id 
            WHERE up.user_id = ? AND up.is_active = 1 
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        return $res->fetch_assoc();
    }
    return null;
}

function add_pet_xp($userId, $xpAmount = 10) {
    global $conn;
    
    // Get active pet
    $sql = "SELECT id, xp, level FROM user_pets WHERE user_id = ? AND is_active = 1 LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $pet = $res->fetch_assoc();
        $newXp = $pet['xp'] + $xpAmount;
        $newLevel = floor($newXp / 100) + 1; // 100 XP per level
        
        $updateSql = "UPDATE user_pets SET xp = ?, level = ? WHERE id = ?";
        $upStmt = $conn->prepare($updateSql);
        $upStmt->bind_param("iii", $newXp, $newLevel, $pet['id']);
        $upStmt->execute();
        
        return [
            'leveled_up' => ($newLevel > $pet['level']),
            'new_level' => $newLevel,
            'xp_added' => $xpAmount
        ];
    }
    return null;
}

function apply_win_buff($userId, $baseWinAmount) {
    $buff = get_active_pet_buff($userId);
    if ($buff && $buff['buff_type'] === 'win_bonus') {
        $bonus = ($baseWinAmount * $buff['buff_value']) / 100;
        return $baseWinAmount + $bonus;
    }
    return $baseWinAmount;
}
?>
