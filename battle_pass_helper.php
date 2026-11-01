<?php
// battle_pass_helper.php - Các hàm phụ trợ cập nhật nhiệm vụ Battle Pass
if (!defined('DB_CONNECT_INCLUDED')) {
    require_once 'db_connect.php';
}

function updateBPMission(mysqli $conn, int $userId, string $actionType, float|int $amount = 1) {
    $amount = (float)$amount;
    if ($amount <= 0) {
        return;
    }

    // Lấy nhiệm vụ active của action này — dùng prepared statement
    $stmtM = $conn->prepare("SELECT id, goal, reward_xp FROM bp_missions WHERE action = ?");
    $stmtM->bind_param("s", $actionType);
    $stmtM->execute();
    $missions = $stmtM->get_result();
    $stmtM->close();

    while ($m = $missions->fetch_assoc()) {
        $mid = (int)$m['id'];
        $goal = (float)($m['goal'] ?? 0);
        $rewardXp = (int)($m['reward_xp'] ?? 0);

        $stmtUM = $conn->prepare("SELECT progress, status FROM bp_user_missions WHERE user_id = ? AND mission_id = ?");
        $stmtUM->bind_param("ii", $userId, $mid);
        $stmtUM->execute();
        $um = $stmtUM->get_result()->fetch_assoc();
        $stmtUM->close();

        if (!$um) {
            $isCompleted = ($goal > 0 && $amount >= $goal);
            $status = $isCompleted ? 'completed' : 'active';
            $progressToSave = $isCompleted ? (int)$goal : (int)min($amount, 2147483647);

            $stmtIns = $conn->prepare("INSERT INTO bp_user_missions (user_id, mission_id, progress, status, last_updated) VALUES (?, ?, ?, ?, CURDATE())");
            $stmtIns->bind_param("iiis", $userId, $mid, $progressToSave, $status);
            $stmtIns->execute();
            $stmtIns->close();

            // Kiểm tra ngay nếu amount >= goal thì complete
            if ($isCompleted) {
                addBPXP($conn, $userId, $rewardXp);
            }
        } else if ($um['status'] == 'active') {
            $curProgress = (float)($um['progress'] ?? 0);
            $newProgressVal = $curProgress + $amount;
            $isCompleted = ($goal > 0 && $newProgressVal >= $goal);
            $status = $isCompleted ? 'completed' : 'active';
            $progressToSave = $isCompleted ? (int)$goal : (int)min($newProgressVal, 2147483647);

            if ($isCompleted) {
                addBPXP($conn, $userId, $rewardXp);
            }

            $stmtUpd = $conn->prepare("UPDATE bp_user_missions SET progress = ?, status = ?, last_updated = CURDATE() WHERE user_id = ? AND mission_id = ?");
            $stmtUpd->bind_param("isii", $progressToSave, $status, $userId, $mid);
            $stmtUpd->execute();
            $stmtUpd->close();
        }
    }
}

function addBPXP(mysqli $conn, int $userId, float|int $amount) {
    $amount = (int)round((float)$amount);
    if ($amount <= 0) {
        return;
    }

    $stmtS = $conn->prepare("SELECT level, xp FROM bp_stats WHERE user_id = ?");
    $stmtS->bind_param("i", $userId);
    $stmtS->execute();
    $stats = $stmtS->get_result()->fetch_assoc();
    $stmtS->close();

    if (!$stats) {
        $stmtIns = $conn->prepare("INSERT INTO bp_stats (user_id, xp) VALUES (?, ?)");
        $stmtIns->bind_param("ii", $userId, $amount);
        $stmtIns->execute();
        $stmtIns->close();
        return;
    }

    $newXP = $stats['xp'] + $amount;
    $level = (int)$stats['level'];
    $xpToNext = $level * 1000;

    while ($newXP >= $xpToNext) {
        $newXP -= $xpToNext;
        $level++;
        $xpToNext = $level * 1000;
    }

    $stmtUpd = $conn->prepare("UPDATE bp_stats SET level = ?, xp = ? WHERE user_id = ?");
    $stmtUpd->bind_param("iii", $level, $newXP, $userId);
    $stmtUpd->execute();
    $stmtUpd->close();
}
?>
