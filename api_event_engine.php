<?php
session_start();
include 'db_connect.php';
require_once 'reward_helper.php';
require_once 'notification_helper.php';
require_once 'api_event_helper.php'; // getActiveSeasonalEvent()

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Lấy sự kiện đang active (Seasonal Event) — KHÔNG bao gồm draft
// Sử dụng getActiveSeasonalEvent() từ api_event_helper.php để tập trung logic query
$event   = getActiveSeasonalEvent($conn);
$eventId = $event['id'] ?? 0;

// Note: event_exchange_history được tạo qua sql/create_event_exchange_history.sql
// KHÔNG chạy CREATE TABLE trong file logic API (anti-pattern)

// ⚡ HÀM CHECK VÀ RESET TIẾN TRÌNH DYNAMIC THEO CHU KỲ (DAILY/WEEKLY)
// FIX: Dùng UPDATE ... WHERE updated_at < X thay vì SELECT rồi check, để MySQL tự lock atomic
// Không cần FOR UPDATE riêng vì UPDATE statement bản thân đã là atomic trong InnoDB
function checkAndResetMissionProgress(mysqli $conn, int $userId, int $eventId) {
    $todayStart = date('Y-m-d 00:00:00');
    $weekStart  = date('Y-m-d 00:00:00', strtotime('monday this week'));
    
    // Reset daily — atomic UPDATE, chỉ ảnh hưởng rows chưa reset hôm nay
    $stmtD = $conn->prepare("
        UPDATE user_mission_progress p
        JOIN event_missions m ON p.mission_id = m.id
        SET p.current_value = 0, p.is_completed = 0, p.is_claimed = 0, p.updated_at = NOW()
        WHERE p.user_id = ?
          AND m.event_id = ?
          AND m.cycle = 'daily'
          AND p.updated_at < ?
    ");
    $stmtD->bind_param("iis", $userId, $eventId, $todayStart);
    $stmtD->execute();
    $stmtD->close();
    
    // Reset weekly — atomic UPDATE
    $stmtW = $conn->prepare("
        UPDATE user_mission_progress p
        JOIN event_missions m ON p.mission_id = m.id
        SET p.current_value = 0, p.is_completed = 0, p.is_claimed = 0, p.updated_at = NOW()
        WHERE p.user_id = ?
          AND m.event_id = ?
          AND m.cycle = 'weekly'
          AND p.updated_at < ?
    ");
    $stmtW->bind_param("iis", $userId, $eventId, $weekStart);
    $stmtW->execute();
    $stmtW->close();
}


/**
 * ⚡ HÀM CÁ NHÂN HÓA NHIỆM VỤ DỰA TRÊN HỒ SƠ NGƯỜI CHƠI
 * FIX: Target đã scale được lưu vào cột `scaled_target` (JSON trong mission_config)
 * để tránh trường hợp VIP hết hạn giữa chừng làm mất tiến trình đã đạt theo target cũ.
 * Logic: khi claim, ta so với MAX(current_value, scaled_target_tại_thời_điểm_scale).
 */
function applyDynamicMissionScaling(&$mission, $isVip, $isBeginner) {
    // Đọc target đã được scale và lưu trước đó (nếu có)
    $savedTarget = (int)($mission['scaled_target'] ?? 0);
    
    if ($isVip) {
        $scaledTarget = (int)ceil($mission['target_value'] * 1.5);
        $mission['title']            = "[VIP] " . $mission['title'];
        $mission['reward_currency']  = (int)ceil($mission['reward_currency'] * 1.5);
        $mission['reward_xp']        = (int)ceil($mission['reward_xp'] * 1.5);
        // Nếu lần đầu scale (chưa lưu) hoặc đang là VIP → dùng target VIP
        $mission['target_value'] = $scaledTarget;
    } elseif ($isBeginner) {
        $scaledTarget = (int)ceil($mission['target_value'] * 0.5);
        $mission['title']        = "[Tân Thủ] " . $mission['title'];
        $mission['target_value'] = $scaledTarget;
        // Phần thưởng giữ nguyên để hỗ trợ newbie
    } else {
        // User bình thường: nếu trước đây đã scale theo VIP, giữ nguyên target đã lưu
        // để người chơi không bị mất tiến trình khi VIP hết hạn
        if ($savedTarget > 0) {
            $mission['target_value'] = $savedTarget;
        }
    }
}

switch ($action) {
    case 'get_event_data':
        if (!$eventId) {
            echo json_encode(['success' => false, 'message' => 'Hiện không có sự kiện nào diễn ra.']);
            exit;
        }

        // 1. Tự động kiểm tra và reset nhiệm vụ chu kỳ hằng ngày/tuần
        checkAndResetMissionProgress($conn, $userId, $eventId);

        // 3. Tiền tệ và điểm của User trong event này (Đã được prepare an toàn)
        $stmtUserEvent = $conn->prepare("SELECT * FROM user_event_data WHERE user_id = ? AND event_id = ?");
        $stmtUserEvent->bind_param("ii", $userId, $eventId);
        $stmtUserEvent->execute();
        $userData = $stmtUserEvent->get_result()->fetch_assoc();
        $stmtUserEvent->close();

        if (!$userData) {
            $stmtInsertUserEvent = $conn->prepare("INSERT INTO user_event_data (user_id, event_id) VALUES (?, ?)");
            $stmtInsertUserEvent->bind_param("ii", $userId, $eventId);
            $stmtInsertUserEvent->execute();
            $stmtInsertUserEvent->close();
            $userData = ['event_currency' => 0, 'points' => 0];
        }

        // 4. Danh sách nhiệm vụ và tiến trình (Đã được prepare an toàn)
        $stmtMissions = $conn->prepare("
            SELECT m.*, 
                   IFNULL(p.current_value, 0)  as current_value, 
                   IFNULL(p.is_completed, 0)   as is_completed, 
                   IFNULL(p.is_claimed, 0)     as is_claimed,
                   IFNULL(p.scaled_target, 0)  as scaled_target,
                   p.id                        as progress_row_id
            FROM event_missions m
            LEFT JOIN user_mission_progress p ON m.id = p.mission_id AND p.user_id = ?
            WHERE m.event_id = ?
        ");
        $stmtMissions->bind_param("ii", $userId, $eventId);
        $stmtMissions->execute();
        $missions = $stmtMissions->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtMissions->close();

        // ⚡ XỬ LÝ ĐIỀU KIỆN MỞ KHÓA MẠNG NHIỆM VỤ (PREREQUISITES)
        $claimedMissionIds = [];
        foreach ($missions as $m) {
            if ($m['is_claimed'] == 1) {
                $claimedMissionIds[] = (int)$m['id'];
            }
        }

        foreach ($missions as &$m) {
            $m['is_locked'] = 0;
            $m['prerequisite_title'] = '';
            
            if ($m['prerequisite_mission_id'] > 0) {
                $prereqId = (int)$m['prerequisite_mission_id'];
                if (!in_array($prereqId, $claimedMissionIds)) {
                    $m['is_locked'] = 1;
                    foreach ($missions as $m2) {
                        if ($m2['id'] == $prereqId) {
                            $m['prerequisite_title'] = $m2['title'];
                            break;
                        }
                    }
                }
            }
        }
        unset($m);

        // ⚡ CÁ NHÂN HÓA NHIỆM VỤ VÀ TÍNH TOÁN LẠI TIẾN TRÌNH
        $stmtUserRow = $conn->prepare("SELECT vip_expiry, xp FROM users WHERE Iduser = ?");
        $stmtUserRow->bind_param("i", $userId);
        $stmtUserRow->execute();
        $userRow = $stmtUserRow->get_result()->fetch_assoc();
        $stmtUserRow->close();

        $isVip = false;
        $isBeginner = false;
        if ($userRow) {
            $isVip = (strtotime($userRow['vip_expiry'] ?? '') > time());
            $isBeginner = ((int)$userRow['xp'] < 5000);
        }

        foreach ($missions as &$m) {
            $originalTarget = (int)$m['target_value'];
            applyDynamicMissionScaling($m, $isVip, $isBeginner);

            // Ghi scaled_target vào DB nếu user là VIP và chưa được lưu
            if ($isVip && (int)($m['scaled_target'] ?? 0) === 0 && $m['target_value'] !== $originalTarget) {
                $scaledForDb  = (int)$m['target_value'];
                $missionIdRef = (int)$m['id'];
                $stmtSave = $conn->prepare("
                    INSERT INTO user_mission_progress (user_id, mission_id, current_value, scaled_target, updated_at)
                    VALUES (?, ?, 0, ?, NOW())
                    ON DUPLICATE KEY UPDATE scaled_target = IF(scaled_target = 0 OR scaled_target IS NULL, ?, scaled_target)
                ");
                $stmtSave->bind_param("iiii", $userId, $missionIdRef, $scaledForDb, $scaledForDb);
                $stmtSave->execute();
                $stmtSave->close();
                $m['scaled_target'] = $scaledForDb; // cập nhật local để client nhận đúng
            }

            // Ghi đè is_completed dựa trên target_value mới
            if ($m['current_value'] >= $m['target_value']) {
                $m['is_completed'] = 1;
            } else {
                $m['is_completed'] = 0;
            }
        }
        unset($m);

        // 5. Cửa hàng đổi quà
        $stmtShop = $conn->prepare("SELECT * FROM event_exchange_shop WHERE event_id = ?");
        $stmtShop->bind_param("i", $eventId);
        $stmtShop->execute();
        $shopItems = $stmtShop->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtShop->close();

        // 6. Tính tổng điểm toàn server (Community Goal)
        $stmtTotal = $conn->prepare("SELECT SUM(points) as total FROM user_event_data WHERE event_id = ?");
        $stmtTotal->bind_param("i", $eventId);
        $stmtTotal->execute();
        $totalRes = $stmtTotal->get_result()->fetch_assoc();
        $totalServerPoints = (int)($totalRes['total'] ?? 0);
        $stmtTotal->close();

        echo json_encode([
            'success' => true,
            'event' => $event,
            'user_data' => $userData,
            'missions' => $missions,
            'shop_items' => $shopItems,
            'total_server_points' => $totalServerPoints
        ]);
        break;

    case 'claim_reward':
        // Simple rate limiting: 1 request per second
        $now = microtime(true) * 1000;
        if (isset($_SESSION['last_claim_time'])) {
            $diff = $now - $_SESSION['last_claim_time'];
            if ($diff < 1000) {
                echo json_encode(['success' => false, 'message' => 'Thao tác quá nhanh! Vui lòng đợi 1 giây.']);
                exit;
            }
        }
        $_SESSION['last_claim_time'] = $now;

        $missionId = (int)$_POST['mission_id'];
        
        $conn->begin_transaction();

        try {
            // ── STEP 1: Khóa hàng tiến trình trước (FOR UPDATE) để chặn concurrent claim ──
            // Thứ tự đúng: lock → reset → đọc lại. Không gọi reset trước lock để tránh
            // 2 UPDATE thừa mỗi lần claim (WARN: checkAndResetMission gọi 2 lần → đã fix)
            $stmtLock = $conn->prepare("SELECT id, current_value, is_completed, is_claimed FROM user_mission_progress WHERE user_id = ? AND mission_id = ? FOR UPDATE");
            $stmtLock->bind_param("ii", $userId, $missionId);
            $stmtLock->execute();
            $progress = $stmtLock->get_result()->fetch_assoc();
            $stmtLock->close();

            if (!$progress) throw new Exception("Bạn chưa bắt đầu nhiệm vụ này!");

            // ── STEP 2: Reset nhiệm vụ chu kỳ SAU KHI ĐÃ LOCK (chỉ 1 lần duy nhất) ──
            checkAndResetMissionProgress($conn, $userId, $eventId);

            // Đọc lại progress sau reset (để phát hiện nếu vừa bị reset)
            $stmtRefresh = $conn->prepare("SELECT * FROM user_mission_progress WHERE user_id = ? AND mission_id = ?");
            $stmtRefresh->bind_param("ii", $userId, $missionId);
            $stmtRefresh->execute();
            $progress = $stmtRefresh->get_result()->fetch_assoc();
            $stmtRefresh->close();

            $stmtMission = $conn->prepare("SELECT * FROM event_missions WHERE id = ?");
            $stmtMission->bind_param("i", $missionId);
            $stmtMission->execute();
            $mission = $stmtMission->get_result()->fetch_assoc();
            $stmtMission->close();

            if (!$mission) throw new Exception("Nhiệm vụ không tồn tại!");

            // ── STEP 3: Áp dụng dynamic scaling, tôn trọng target đã lưu nếu VIP hết hạn ──
            $stmtUser = $conn->prepare("SELECT vip_expiry, xp FROM users WHERE Iduser = ?");
            $stmtUser->bind_param("i", $userId);
            $stmtUser->execute();
            $userRow = $stmtUser->get_result()->fetch_assoc();
            $stmtUser->close();

            $isVip      = false;
            $isBeginner = false;
            if ($userRow) {
                $isVip      = (strtotime($userRow['vip_expiry'] ?? '') > time());
                $isBeginner = ((int)$userRow['xp'] < 5000);
            }
            // Truyền thêm scaled_target đã lưu trong progress (nếu có)
            $mission['scaled_target'] = (int)($progress['scaled_target'] ?? 0);
            applyDynamicMissionScaling($mission, $isVip, $isBeginner);

            // Ghi đè is_completed theo target_value mới đã scale
            if ($progress['current_value'] < $mission['target_value']) {
                throw new Exception("Nhiệm vụ chưa hoàn thành!");
            }
            if ($progress['is_claimed']) throw new Exception("Bạn đã nhận thưởng nhiệm vụ này rồi!");

            // ⚡ KIỂM TRA ĐIỀU KIỆN MỞ KHÓA (PREREQUISITE LOCKS)
            if ($mission['prerequisite_mission_id'] > 0) {
                $prereqId = (int)$mission['prerequisite_mission_id'];
                $stmtPrereq = $conn->prepare("SELECT is_claimed FROM user_mission_progress WHERE user_id = ? AND mission_id = ?");
                $stmtPrereq->bind_param("ii", $userId, $prereqId);
                $stmtPrereq->execute();
                $prereqResult = $stmtPrereq->get_result()->fetch_assoc();
                $isClaimed = $prereqResult['is_claimed'] ?? 0;
                $stmtPrereq->close();

                if (!$isClaimed) {
                    throw new Exception("Nhiệm vụ này đang khóa! Bạn cần hoàn thành và nhận thưởng nhiệm vụ điều kiện trước.");
                }
            }

            // Cập nhật trạng thái nhận thưởng
            $stmtUpdateClaim = $conn->prepare("UPDATE user_mission_progress SET is_claimed = 1 WHERE id = ?");
            $stmtUpdateClaim->bind_param("i", $progress['id']);
            $stmtUpdateClaim->execute();
            $stmtUpdateClaim->close();

            // ── FEAT 5: Thông báo mở khóa nhiệm vụ kế tiếp (prerequisite chain) ──
            // Sau khi claim mission này, tìm tất cả mission có prerequisite = $missionId
            $stmtUnlocked = $conn->prepare("
                SELECT m.id, m.title FROM event_missions m
                LEFT JOIN user_mission_progress p ON m.id = p.mission_id AND p.user_id = ?
                WHERE m.event_id = ?
                  AND m.prerequisite_mission_id = ?
                  AND (p.is_claimed IS NULL OR p.is_claimed = 0)
            ");
            $stmtUnlocked->bind_param("iii", $userId, $eventId, $missionId);
            $stmtUnlocked->execute();
            $unlockedMissions = $stmtUnlocked->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtUnlocked->close();

            foreach ($unlockedMissions as $unlocked) {
                createNotification($conn, $userId, 'event_update',
                    '🔓 Nhiệm Vụ Mới Mở Khóa!',
                    '"' . $unlocked['title'] . '" đã mở khóa sau khi bạn hoàn thành nhiệm vụ trước. Hãy thử sức ngay!',
                    '🗝️', 'event_center.php', $eventId, false
                );
            }

            // ⚡ CỘNG THƯỞNG STREAK (Khuyến khích đăng nhập mỗi ngày)
            $streakMultiplier = 1.0;
            $stmtStreak = $conn->prepare("SELECT streak_bonus_multiplier FROM user_streaks WHERE user_id = ?");
            $stmtStreak->bind_param("i", $userId);
            $stmtStreak->execute();
            $streakCheck = $stmtStreak->get_result();
            if ($streakCheck && $streakRow = $streakCheck->fetch_assoc()) {
                $streakMultiplier = (float)$streakRow['streak_bonus_multiplier'];
            }
            $stmtStreak->close();
            
            $rewardCurrency = round($mission['reward_currency'] * $streakMultiplier);
            $rewardXp = round($mission['reward_xp'] * $streakMultiplier);

            // Cộng GTLM sự kiện và điểm
            $stmtUpdateUserEvent = $conn->prepare("UPDATE user_event_data SET event_currency = event_currency + ?, points = points + ? WHERE user_id = ? AND event_id = ?");
            $stmtUpdateUserEvent->bind_param("iiii", $rewardCurrency, $rewardXp, $userId, $eventId);
            $stmtUpdateUserEvent->execute();
            $stmtUpdateUserEvent->close();

            // ==========================================
            // ✅ AUTO MILESTONE CHECK
            // ==========================================
            $stmtNewPoints = $conn->prepare("SELECT points FROM user_event_data WHERE user_id = ? AND event_id = ?");
            $stmtNewPoints->bind_param("ii", $userId, $eventId);
            $stmtNewPoints->execute();
            $newPoints = (int)($stmtNewPoints->get_result()->fetch_assoc()['points'] ?? 0);
            $stmtNewPoints->close();

            $milestoneConfig = json_decode($event['milestone_config'] ?? '[]', true) ?: [];

            foreach ($milestoneConfig as $milestone) {
                $mPoints = (int)($milestone['points'] ?? 0);
                $mType   = $milestone['reward_type'] ?? '';
                $mValue  = $milestone['reward_value'] ?? '';
                $mLabel  = $milestone['label'] ?? "Mốc $mPoints điểm";

                if ($newPoints >= $mPoints && $mPoints > 0) {
                    // Kiểm tra đã nhận milestone này chưa (dùng JSON column milestones_claimed)
                    $claimedKey   = "m_{$eventId}_{$mPoints}";
                    $stmtMilestoneCheck = $conn->prepare("
                        SELECT 1 FROM user_event_data
                        WHERE user_id = ? AND event_id = ?
                        AND JSON_CONTAINS(COALESCE(milestones_claimed, '[]'), ?)
                    ");
                    $claimedKeyJson = '"' . $claimedKey . '"';
                    $stmtMilestoneCheck->bind_param("iis", $userId, $eventId, $claimedKeyJson);
                    $stmtMilestoneCheck->execute();
                    $hasMilestoneClaimed = $stmtMilestoneCheck->get_result()->num_rows > 0;
                    $stmtMilestoneCheck->close();

                    if (!$hasMilestoneClaimed) {
                        // Trao thưởng milestone
                        deliverReward($userId, ['reward_type' => $mType, 'reward_value' => $mValue], $conn);
                        // Đánh dấu đã nhận
                        $stmtMilestoneClaim = $conn->prepare("
                            UPDATE user_event_data
                            SET milestones_claimed = JSON_ARRAY_APPEND(COALESCE(milestones_claimed, JSON_ARRAY()), '$', ?)
                            WHERE user_id = ? AND event_id = ?
                        ");
                        $stmtMilestoneClaim->bind_param("sii", $claimedKey, $userId, $eventId);
                        $stmtMilestoneClaim->execute();
                        $stmtMilestoneClaim->close();

                        createNotification($conn, $userId, 'event_update',
                            "🎯 Milestone: $mLabel",
                            "Chúc mừng! Bạn vượt mốc $mPoints điểm và nhận phần thưởng bất ngờ!",
                            '🎊', 'events.php', $eventId, true
                        );
                    }
                }
            }

            // ==========================================
            // ✅ EVENT MISSION CHAIN CHECK (Chuỗi nhiệm vụ)
            // ==========================================
            // Đếm số nhiệm vụ đã hoàn thành và nhận thưởng
            $stmtClaimedCount = $conn->prepare("
                SELECT COUNT(*) as c FROM user_mission_progress p 
                JOIN event_missions m ON p.mission_id = m.id
                WHERE p.user_id = ? AND m.event_id = ? AND p.is_claimed = 1
            ");
            $stmtClaimedCount->bind_param("ii", $userId, $eventId);
            $stmtClaimedCount->execute();
            $claimedCount = (int)($stmtClaimedCount->get_result()->fetch_assoc()['c'] ?? 0);
            $stmtClaimedCount->close();

            $chainConfig = json_decode($event['chain_config'] ?? '[]', true) ?: [];
            
            foreach ($chainConfig as $chain) {
                $cReq   = (int)($chain['required_missions'] ?? 0);
                $cType  = $chain['reward_type'] ?? '';
                $cValue = $chain['reward_value'] ?? '';
                $cLabel = $chain['label'] ?? "Chuỗi $cReq nhiệm vụ";

                if ($claimedCount >= $cReq && $cReq > 0) {
                    $chainKey = "chain_{$eventId}_{$cReq}";
                    $stmtChainCheck = $conn->prepare("
                        SELECT 1 FROM user_event_data
                        WHERE user_id = ? AND event_id = ?
                        AND JSON_CONTAINS(COALESCE(milestones_claimed, '[]'), ?)
                    ");
                    $chainKeyJson = '"' . $chainKey . '"';
                    $stmtChainCheck->bind_param("iis", $userId, $eventId, $chainKeyJson);
                    $stmtChainCheck->execute();
                    $hasChainClaimed = $stmtChainCheck->get_result()->num_rows > 0;
                    $stmtChainCheck->close();

                    if (!$hasChainClaimed) {
                        deliverReward($userId, ['reward_type' => $cType, 'reward_value' => $cValue], $conn);
                        
                        $stmtChainClaim = $conn->prepare("
                            UPDATE user_event_data
                            SET milestones_claimed = JSON_ARRAY_APPEND(COALESCE(milestones_claimed, JSON_ARRAY()), '$', ?)
                            WHERE user_id = ? AND event_id = ?
                        ");
                        $stmtChainClaim->bind_param("sii", $chainKey, $userId, $eventId);
                        $stmtChainClaim->execute();
                        $stmtChainClaim->close();

                        createNotification($conn, $userId, 'event_update',
                            "🎁 Chuỗi Nhiệm Vụ: $cLabel",
                            "Bạn đã hoàn thành đủ $cReq nhiệm vụ sự kiện và nhận được phần thưởng đặc biệt!",
                            '⭐', 'event_center.php', $eventId, true
                        );
                    }
                }
            }

            // ==========================================
            // ✅ GUILD CONTRIBUTION
            // ==========================================
            $stmtGuildRow = $conn->prepare("SELECT guild_id FROM guild_members WHERE user_id = ? LIMIT 1");
            $stmtGuildRow->bind_param("i", $userId);
            $stmtGuildRow->execute();
            $guildRow = $stmtGuildRow->get_result()->fetch_assoc();
            $stmtGuildRow->close();

            if ($guildRow && !empty($guildRow['guild_id'])) {
                $guildId  = (int)$guildRow['guild_id'];
                $missionXp = (int)($mission['reward_xp'] ?? 0);

                // Cộng điểm cho guild
                $stmtInsContribution = $conn->prepare("
                    INSERT INTO event_guild_contributions (event_id, guild_id, total_points)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE total_points = total_points + ?
                ");
                $stmtInsContribution->bind_param("iiii", $eventId, $guildId, $missionXp, $missionXp);
                $stmtInsContribution->execute();
                $stmtInsContribution->close();

                // Lấy tổng điểm guild mới
                $stmtGuildPoints = $conn->prepare("
                    SELECT total_points FROM event_guild_contributions
                    WHERE event_id = ? AND guild_id = ?
                ");
                $stmtGuildPoints->bind_param("ii", $eventId, $guildId);
                $stmtGuildPoints->execute();
                $guildPoints = (int)($stmtGuildPoints->get_result()->fetch_assoc()['total_points'] ?? 0);
                $stmtGuildPoints->close();

                // Đọc guild milestone config từ DB thay vì hardcode
                $guildMilestones = json_decode($event['guild_milestone_config'] ?? '[]', true) ?: [];

                foreach ($guildMilestones as $gm) {
                    $gmPointsVal = (int)($gm['points'] ?? 0);
                    if ($guildPoints >= $gmPointsVal && $gmPointsVal > 0) {
                        // Kiểm tra chưa nhận milestone này
                        $stmtAlreadyClaimed = $conn->prepare("
                            SELECT 1 FROM event_guild_milestone_claimed
                            WHERE event_id = ? AND guild_id = ? AND milestone = ?
                        ");
                        $stmtAlreadyClaimed->bind_param("iii", $eventId, $guildId, $gmPointsVal);
                        $stmtAlreadyClaimed->execute();
                        $alreadyClaimed = $stmtAlreadyClaimed->get_result()->num_rows > 0;
                        $stmtAlreadyClaimed->close();

                        if (!$alreadyClaimed) {
                            // Đánh dấu đã nhận
                            $stmtInsMilestoneClaim = $conn->prepare("
                                INSERT IGNORE INTO event_guild_milestone_claimed (event_id, guild_id, milestone)
                                VALUES (?, ?, ?)
                            ");
                            $stmtInsMilestoneClaim->bind_param("iii", $eventId, $guildId, $gmPointsVal);
                            $stmtInsMilestoneClaim->execute();
                            $stmtInsMilestoneClaim->close();

                            // Lấy thành viên active của guild (online trong 7 ngày gần nhất)
                            $stmtMembers = $conn->prepare("
                                SELECT gm2.user_id FROM guild_members gm2
                                JOIN users u ON gm2.user_id = u.Iduser
                                WHERE gm2.guild_id = ?
                                AND u.last_active >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                            ");
                            $stmtMembers->bind_param("i", $guildId);
                            $stmtMembers->execute();
                            $members = $stmtMembers->get_result()->fetch_all(MYSQLI_ASSOC);
                            $stmtMembers->close();

                            foreach ($members as $member) {
                                $mUid = (int)$member['user_id'];
                                deliverReward($mUid, ['reward_type' => $gm['reward_type'], 'reward_value' => $gm['reward_value']], $conn);
                                createNotification($conn, $mUid, 'event_update',
                                    "🏰 Guild Đạt Mốc: {$gm['label']}!",
                                    "Guild của bạn đã đạt {$gm['points']} điểm sự kiện! Tất cả thành viên nhận thưởng!",
                                    '⚔️', 'guilds.php', $guildId, true
                                );
                            }
                        }
                    }
                }
            }

                // Lấy tổng điểm guild mới
                $guildPoints = (int)$conn->query("
                    SELECT total_points FROM event_guild_contributions
                    WHERE event_id = $eventId AND guild_id = $guildId
                ")->fetch_assoc()['total_points'];

                // WARN fix: Đọc guild milestone config từ DB thay vì hardcode
                // Admin có thể tùy chỉnh trong cột guild_milestone_config của seasonal_events
                $guildMilestones = json_decode($event['guild_milestone_config'] ?? '[]', true) ?: [];

                foreach ($guildMilestones as $gm) {
                    if ($guildPoints >= $gm['points']) {
                        // Kiểm tra chưa nhận milestone này
                        $alreadyClaimed = $conn->query("
                            SELECT 1 FROM event_guild_milestone_claimed
                            WHERE event_id = $eventId AND guild_id = $guildId AND milestone = {$gm['points']}
                        ")->num_rows;

                        if (!$alreadyClaimed) {
                            // Đánh dấu đã nhận
                            $conn->query("
                                INSERT IGNORE INTO event_guild_milestone_claimed (event_id, guild_id, milestone)
                                VALUES ($eventId, $guildId, {$gm['points']})
                            ");

                            // Lấy thành viên active của guild (online trong 7 ngày gần nhất)
                            $members = $conn->query("
                                SELECT gm2.user_id FROM guild_members gm2
                                JOIN users u ON gm2.user_id = u.Iduser
                                WHERE gm2.guild_id = $guildId
                                AND u.last_active >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                            ")->fetch_all(MYSQLI_ASSOC);

                            foreach ($members as $member) {
                                $mUid = (int)$member['user_id'];
                                deliverReward($mUid, ['reward_type' => $gm['reward_type'], 'reward_value' => $gm['reward_value']], $conn);
                                createNotification($conn, $mUid, 'event_update',
                                    "🏰 Guild Đạt Mốc: {$gm['label']}!",
                                    "Guild của bạn đã đạt {$gm['points']} điểm sự kiện! Tất cả thành viên nhận thưởng!",
                                    '⚔️', 'guilds.php', $guildId, true
                                );
                            }
                        }
                    }
                }
            }

            $conn->commit();

            // ==========================================
            // ✅ BATTLE PASS XP HOOK (sau commit)
            // ==========================================
            require_once 'api_battle_pass.php';
            updateBPMission($conn, $userId, 'complete_event_mission', 1);

            echo json_encode(['success' => true, 'message' => 'Nhận thưởng thành công!', 'reward' => $mission['reward_currency']]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;


    case 'preview_item':
        $itemId = (int)($_GET['item_id'] ?? 0);
        $itemType = $_GET['item_type'] ?? '';
        
        $html = "Không có dữ liệu xem trước.";
        if ($itemType === 'title') {
            $stmtTitle = $conn->prepare("SELECT * FROM titles WHERE id = ?");
            $stmtTitle->bind_param("i", $itemId);
            $stmtTitle->execute();
            $title = $stmtTitle->get_result()->fetch_assoc();
            $stmtTitle->close();
            if ($title) {
                $html = "<div class='{$title['css_class']}' style='font-size:20px; font-weight:bold;'>[Tên User] {$title['name']}</div>";
            }
        } elseif ($itemType === 'chat_frame') {
            $stmtFrame = $conn->prepare("SELECT * FROM chat_frames WHERE id = ?");
            $stmtFrame->bind_param("i", $itemId);
            $stmtFrame->execute();
            $frame = $stmtFrame->get_result()->fetch_assoc();
            $stmtFrame->close();
            if ($frame) {
                $html = "<div style='{$frame['css_style']} padding:15px; border-radius:10px; display:inline-block;'>Xin chào! Khung chat của tôi nè.</div>";
            }
        } elseif ($itemType === 'theme') {
            $stmtTheme = $conn->prepare("SELECT * FROM themes WHERE id = ?");
            $stmtTheme->bind_param("i", $itemId);
            $stmtTheme->execute();
            $theme = $stmtTheme->get_result()->fetch_assoc();
            $stmtTheme->close();
            if ($theme) {
                $html = "<div style='background: {$theme['bg_gradient']}; padding: 30px; border-radius: 10px; color: white; font-weight: bold;'>Giao diện: {$theme['name']}</div>";
            }
        } elseif ($itemType === 'cursor') {
            $stmtCursor = $conn->prepare("SELECT * FROM cursors WHERE id = ?");
            $stmtCursor->bind_param("i", $itemId);
            $stmtCursor->execute();
            $cursor = $stmtCursor->get_result()->fetch_assoc();
            $stmtCursor->close();
            if ($cursor) {
                $html = "<div style=\"cursor: url('{$cursor['image_url']}'), auto; height: 100px; line-height: 100px; border: 2px dashed #ccc; border-radius:10px;\">Di chuột vào đây!</div>";
            }
        }
        
        echo json_encode(['success' => true, 'html' => $html]);
        break;

    case 'exchange_item':
        $itemId = (int)$_POST['item_id'];
        
        $conn->begin_transaction();
        try {
            $stmtItem = $conn->prepare("SELECT * FROM event_exchange_shop WHERE id = ? FOR UPDATE");
            $stmtItem->bind_param("i", $itemId);
            $stmtItem->execute();
            $item = $stmtItem->get_result()->fetch_assoc();
            $stmtItem->close();

            $stmtUserEvent = $conn->prepare("SELECT * FROM user_event_data WHERE user_id = ? AND event_id = ? FOR UPDATE");
            $stmtUserEvent->bind_param("ii", $userId, $eventId);
            $stmtUserEvent->execute();
            $userData = $stmtUserEvent->get_result()->fetch_assoc();
            $stmtUserEvent->close();

            if (!$item) throw new Exception("Vật phẩm không tồn tại!");
            if ($userData['event_currency'] < $item['cost_currency']) throw new Exception("Bạn không đủ Xu Sự Kiện!");
            if ($item['total_stock'] == 0) throw new Exception("Vật phẩm đã hết hàng!");

            // Kiểm tra giới hạn mỗi người
            $stmtClaimCount = $conn->prepare("SELECT COUNT(*) as total FROM event_exchange_history WHERE user_id = ? AND event_id = ? AND item_id = ?");
            $stmtClaimCount->bind_param("iii", $userId, $eventId, $item['item_id']);
            $stmtClaimCount->execute();
            $claimCount = $stmtClaimCount->get_result()->fetch_assoc()['total'];
            $stmtClaimCount->close();

            if ($item['limit_per_user'] > 0 && $claimCount >= $item['limit_per_user']) {
                throw new Exception("Bạn đã đạt giới hạn đổi vật phẩm này!");
            }

            // Trừ Gtlm sự kiện
            $stmtDeduct = $conn->prepare("UPDATE user_event_data SET event_currency = event_currency - ? WHERE user_id = ? AND event_id = ?");
            $stmtDeduct->bind_param("iii", $item['cost_currency'], $userId, $eventId);
            $stmtDeduct->execute();
            $stmtDeduct->close();

            // Trao giải (sử dụng deliverReward từ reward_helper.php)
            $rewardType = $item['item_type'];
            $rewardValue = $item['item_id'];
            
            if (in_array($rewardType, ['theme', 'cursor', 'chat_frame', 'xp', 'vip', 'buff'])) {
                $rewardToDeliver = ['reward_type' => 'item', 'reward_value' => $rewardType . ':' . $rewardValue];
            } else {
                $rewardToDeliver = ['reward_type' => $rewardType, 'reward_value' => $rewardValue];
            }
            
            if (!deliverReward($userId, $rewardToDeliver, $conn)) {
                throw new Exception("Lỗi trao thưởng hoặc bạn đã sở hữu vật phẩm này!");
            }

            // Ghi log lịch sử đổi quà
            $stmtLog = $conn->prepare("
                INSERT INTO event_exchange_history (event_id, user_id, item_id, item_name, item_type, cost_currency)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtLog->bind_param("iiissi", $eventId, $userId, $item['item_id'], $item['item_name'], $item['item_type'], $item['cost_currency']);
            $stmtLog->execute();
            $stmtLog->close();

            // Cập nhật kho
            if ($item['total_stock'] > 0) {
                $stmtStock = $conn->prepare("UPDATE event_exchange_shop SET total_stock = total_stock - 1 WHERE id = ?");
                $stmtStock->bind_param("i", $itemId);
                $stmtStock->execute();
                $stmtStock->close();
            }

            $conn->commit();
            $newBalance = (int)($userData['event_currency'] - $item['cost_currency']);
            echo json_encode(['success' => true, 'message' => 'Đổi quà thành công!', 'new_balance' => $newBalance]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get_leaderboard':
        if (!$eventId) {
            echo json_encode(['success' => false, 'message' => 'Hiện không có sự kiện nào diễn ra!']);
            exit;
        }

        // Tải top 50 bảng xếp hạng điểm vinh danh tích lũy
        $stmtLeaderboard = $conn->prepare("
            SELECT u.Name as username, u.Avatar as avatar, d.points, d.event_currency
            FROM user_event_data d
            JOIN users u ON d.user_id = u.Iduser
            WHERE d.event_id = ?
            ORDER BY d.points DESC, d.id ASC
            LIMIT 50
        ");
        $stmtLeaderboard->bind_param("i", $eventId);
        $stmtLeaderboard->execute();
        $leaderboard = $stmtLeaderboard->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtLeaderboard->close();

        // Tính thứ hạng cá nhân
        $myRank = '--';
        $myPoints = 0;
        
        $stmtMyPoints = $conn->prepare("SELECT points FROM user_event_data WHERE user_id = ? AND event_id = ?");
        $stmtMyPoints->bind_param("ii", $userId, $eventId);
        $stmtMyPoints->execute();
        $myRow = $stmtMyPoints->get_result()->fetch_assoc();
        $stmtMyPoints->close();
        
        // Item 5 Fix: points = 0 should keep myRank = '--'
        if ($myRow && (int)$myRow['points'] > 0) {
            $myPoints = (int)$myRow['points'];
            $stmtRank = $conn->prepare("SELECT COUNT(*) + 1 as rank FROM user_event_data WHERE event_id = ? AND points > ?");
            $stmtRank->bind_param("ii", $eventId, $myPoints);
            $stmtRank->execute();
            $myRank = (int)$stmtRank->get_result()->fetch_assoc()['rank'];
            $stmtRank->close();
        }

        echo json_encode([
            'success' => true,
            'leaderboard' => $leaderboard,
            'my_rank' => $myRank,
            'my_points' => $myPoints
        ]);
        break;

    case 'get_exchange_history':
        if (!$eventId) {
            echo json_encode(['success' => false, 'message' => 'Hiện không có sự kiện nào diễn ra!']);
            exit;
        }
        $stmtHistory = $conn->prepare("
            SELECT item_name, item_type, cost_currency, created_at 
            FROM event_exchange_history 
            WHERE event_id = ? AND user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 50
        ");
        $stmtHistory->bind_param("ii", $eventId, $userId);
        $stmtHistory->execute();
        $history = $stmtHistory->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtHistory->close();
        echo json_encode(['success' => true, 'history' => $history]);
        break;

    // ── FEAT 2: Lịch sử nhiệm vụ đã claim ──────────────────────────────────────
    case 'get_mission_history':
        if (!$eventId) {
            echo json_encode(['success' => false, 'message' => 'Hiện không có sự kiện nào diễn ra!']);
            exit;
        }
        $stmtMissionHistory = $conn->prepare("
            SELECT m.title, m.mission_type, m.reward_currency, m.reward_xp, m.cycle,
                   p.updated_at as claimed_at
            FROM user_mission_progress p
            JOIN event_missions m ON p.mission_id = m.id
            WHERE p.user_id = ? AND m.event_id = ? AND p.is_claimed = 1
            ORDER BY p.updated_at DESC
            LIMIT 100
        ");
        $stmtMissionHistory->bind_param("ii", $userId, $eventId);
        $stmtMissionHistory->execute();
        $missionHistory = $stmtMissionHistory->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtMissionHistory->close();
        echo json_encode(['success' => true, 'history' => $missionHistory]);
        break;

    // ── FEAT 4: BXH Bang Hội trong sự kiện ────────────────────────────────────
    case 'get_guild_leaderboard':
        if (!$eventId) {
            echo json_encode(['success' => false, 'message' => 'Hiện không có sự kiện nào diễn ra!']);
            exit;
        }
        $stmtGuildBoard = $conn->prepare("
            SELECT g.name as guild_name, g.icon as guild_icon,
                   c.total_points, c.guild_id,
                   COUNT(gm.user_id) as member_count
            FROM event_guild_contributions c
            JOIN guilds g ON c.guild_id = g.id
            LEFT JOIN guild_members gm ON gm.guild_id = c.guild_id
            WHERE c.event_id = ?
            GROUP BY c.guild_id
            ORDER BY c.total_points DESC
            LIMIT 20
        ");
        $stmtGuildBoard->bind_param("i", $eventId);
        $stmtGuildBoard->execute();
        $guildBoard = $stmtGuildBoard->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtGuildBoard->close();

        // Hạng của guild mình
        $stmtMyGuild = $conn->prepare("SELECT guild_id FROM guild_members WHERE user_id = ? LIMIT 1");
        $stmtMyGuild->bind_param("i", $userId);
        $stmtMyGuild->execute();
        $myGuildRow = $stmtMyGuild->get_result()->fetch_assoc();
        $myGuildId  = (int)($myGuildRow['guild_id'] ?? 0);
        $stmtMyGuild->close();

        $myGuildRank = '--';
        $myGuildPts  = 0;
        if ($myGuildId) {
            $stmtMyGuildPts = $conn->prepare("SELECT total_points FROM event_guild_contributions WHERE event_id = ? AND guild_id = ?");
            $stmtMyGuildPts->bind_param("ii", $eventId, $myGuildId);
            $stmtMyGuildPts->execute();
            $myGuildPts = (int)($stmtMyGuildPts->get_result()->fetch_assoc()['total_points'] ?? 0);
            $stmtMyGuildPts->close();
            
            $stmtMyGuildRank = $conn->prepare("SELECT COUNT(*) + 1 as r FROM event_guild_contributions WHERE event_id = ? AND total_points > ?");
            $stmtMyGuildRank->bind_param("ii", $eventId, $myGuildPts);
            $stmtMyGuildRank->execute();
            $myGuildRank = (int)$stmtMyGuildRank->get_result()->fetch_assoc()['r'];
            $stmtMyGuildRank->close();
        }
        echo json_encode([
            'success'        => true,
            'guild_board'    => $guildBoard,
            'my_guild_id'    => $myGuildId,
            'my_guild_rank'  => $myGuildRank,
            'my_guild_pts'   => $myGuildPts,
        ]);
        break;

    case 'get_last_event_summary':
        // Tìm sự kiện vừa kết thúc gần đây nhất (trạng thái inactive hoặc đã hết hạn)
        $lastEvent = $conn->query("SELECT * FROM seasonal_events WHERE status = 'inactive' OR ends_at < NOW() ORDER BY ends_at DESC LIMIT 1")->fetch_assoc();
        if (!$lastEvent) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy sự kiện cũ.']);
            exit;
        }
        $leId = (int)$lastEvent['id'];
        
        // Lấy kết quả của user hiện tại
        $stmtUserData = $conn->prepare("SELECT * FROM user_event_data WHERE user_id = ? AND event_id = ?");
        $stmtUserData->bind_param("ii", $userId, $leId);
        $stmtUserData->execute();
        $userData = $stmtUserData->get_result()->fetch_assoc();
        $stmtUserData->close();

        if (!$userData) {
            echo json_encode(['success' => false, 'message' => 'Bạn không tham gia sự kiện này.', 'event_id' => $leId]);
            exit;
        }
        
        // Item 5 Fix: rank = '--' if points = 0
        $rank = '--';
        if ((int)$userData['points'] > 0) {
            $stmtRank = $conn->prepare("SELECT COUNT(*) + 1 as r FROM user_event_data WHERE event_id = ? AND points > ?");
            $stmtRank->bind_param("ii", $leId, $userData['points']);
            $stmtRank->execute();
            $rank = (int)$stmtRank->get_result()->fetch_assoc()['r'];
            $stmtRank->close();
        }
        
        $stmtMissions = $conn->prepare("SELECT COUNT(*) as c FROM user_mission_progress p JOIN event_missions m ON p.mission_id = m.id WHERE p.user_id = ? AND m.event_id = ? AND p.is_claimed = 1");
        $stmtMissions->bind_param("ii", $userId, $leId);
        $stmtMissions->execute();
        $missionsCompleted = $stmtMissions->get_result()->fetch_assoc()['c'] ?? 0;
        $stmtMissions->close();

        echo json_encode([
            'success' => true,
            'event_id' => $leId,
            'event_name' => $lastEvent['name'],
            'emoji' => $lastEvent['theme_emoji'] ?: '🏆',
            'points' => (int)$userData['points'],
            'rank' => $rank,
            'missions_completed' => (int)$missionsCompleted,
            'leftover_currency' => (int)$userData['event_currency']
        ]);
        break;
}
?>
