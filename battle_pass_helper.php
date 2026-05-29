<?php
// battle_pass_helper.php - Các hàm phụ trợ cập nhật nhiệm vụ Battle Pass
if (!defined('DB_CONNECT_INCLUDED')) {
    require_once 'db_connect.php';
}

function updateBPMission(mysqli $conn, int $userId, string $actionType, int $amount = 1) {
    // Lấy nhiệm vụ active của action này — dùng prepared statement
    $stmtM = $conn->prepare("SELECT id, goal, reward_xp FROM bp_missions WHERE action = ?");
    $stmtM->bind_param("s", $actionType);
    $stmtM->execute();
    $missions = $stmtM->get_result();
    $stmtM->close();

    while ($m = $missions->fetch_assoc()) {
        $mid = $m['id'];
        $stmtUM = $conn->prepare("SELECT progress, status FROM bp_user_missions WHERE user_id = ? AND mission_id = ?");
        $stmtUM->bind_param("ii", $userId, $mid);
        $stmtUM->execute();
        $um = $stmtUM->get_result()->fetch_assoc();
        $stmtUM->close();

        if (!$um) {
            $stmtIns = $conn->prepare("INSERT INTO bp_user_missions (user_id, mission_id, progress) VALUES (?, ?, ?)");
            $stmtIns->bind_param("iii", $userId, $mid, $amount);
            $stmtIns->execute();
            $stmtIns->close();
            // Kiểm tra ngay nếu amount >= goal thì complete
            if ($amount >= $m['goal']) {
                addBPXP($conn, $userId, $m['reward_xp']);
                $stmtComp = $conn->prepare("UPDATE bp_user_missions SET status = 'completed' WHERE user_id = ? AND mission_id = ?");
                $stmtComp->bind_param("ii", $userId, $mid);
                $stmtComp->execute();
                $stmtComp->close();
            }
        } else if ($um['status'] == 'active') {
            $newProgress = $um['progress'] + $amount;
            $status = ($newProgress >= $m['goal']) ? 'completed' : 'active';

            if ($status == 'completed') {
                addBPXP($conn, $userId, $m['reward_xp']);
            }

            $stmtUpd = $conn->prepare("UPDATE bp_user_missions SET progress = ?, status = ? WHERE user_id = ? AND mission_id = ?");
            $stmtUpd->bind_param("isii", $newProgress, $status, $userId, $mid);
            $stmtUpd->execute();
            $stmtUpd->close();
        }
    }
}

function addBPXP(mysqli $conn, int $userId, int $amount) {
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
    $level = $stats['level'];
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
