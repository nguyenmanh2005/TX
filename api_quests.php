<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập']);
    exit();
}

$userId = $_SESSION['Iduser'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Kiểm tra bảng tồn tại
$checkQuestsTable = $conn->query("SHOW TABLES LIKE 'quests'");
$checkUserQuestsTable = $conn->query("SHOW TABLES LIKE 'user_quests'");

if (!$checkQuestsTable || $checkQuestsTable->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Hệ thống nhiệm vụ chưa được kích hoạt. Vui lòng chạy file create_quests_tables.sql']);
    exit();
}

// Lấy nhiệm vụ của người dùng
if ($action === 'get_quests') {
    $questType = $_GET['type'] ?? 'daily'; // daily hoặc weekly
    $questDate = resolveQuestDate($questType);
    $quests = loadUserQuests($conn, $userId, $questType, $questDate);
    
    echo json_encode([
        'status' => 'success',
        'quests' => $quests,
        'quest_date' => $questDate
    ]);
}

// Claim reward
elseif ($action === 'claim_reward') {
    $questId = (int)$_POST['quest_id'];
    $questType = $_POST['quest_type'] ?? 'daily';
    $today = date('Y-m-d');
    
    // Xác định quest_date
    if ($questType === 'weekly') {
        $dayOfWeek = date('w');
        $daysToMonday = ($dayOfWeek == 0) ? 6 : $dayOfWeek - 1;
        $monday = date('Y-m-d', strtotime("-$daysToMonday days"));
        $questDate = $monday;
    } else {
        $questDate = $today;
    }
    
    // Lấy quest info
    $questSql = "SELECT * FROM quests WHERE id = ?";
    $questStmt = $conn->prepare($questSql);
    $questStmt->bind_param("i", $questId);
    $questStmt->execute();
    $questResult = $questStmt->get_result();
    $quest = $questResult->fetch_assoc();
    $questStmt->close();
    
    if (!$quest) {
        echo json_encode(['status' => 'error', 'message' => 'Nhiệm vụ không tồn tại']);
        exit();
    }
    
    // Lấy user_quest
    $userQuestSql = "SELECT * FROM user_quests WHERE user_id = ? AND quest_id = ? AND quest_date = ?";
    $userQuestStmt = $conn->prepare($userQuestSql);
    $userQuestStmt->bind_param("iis", $userId, $questId, $questDate);
    $userQuestStmt->execute();
    $userQuestResult = $userQuestStmt->get_result();
    $userQuest = $userQuestResult->fetch_assoc();
    $userQuestStmt->close();
    
    if (!$userQuest) {
        echo json_encode(['status' => 'error', 'message' => 'Bạn chưa có nhiệm vụ này']);
        exit();
    }
    
    if (!$userQuest['is_completed']) {
        echo json_encode(['status' => 'error', 'message' => 'Bạn chưa hoàn thành nhiệm vụ này']);
        exit();
    }
    
    if ($userQuest['is_claimed']) {
        echo json_encode(['status' => 'error', 'message' => 'Bạn đã nhận phần thưởng rồi']);
        exit();
    }
    
    // Cấp reward
    if ($quest['reward_money'] > 0) {
        $updateMoneySql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
        $updateMoneyStmt = $conn->prepare($updateMoneySql);
        $updateMoneyStmt->bind_param("di", $quest['reward_money'], $userId);
        $updateMoneyStmt->execute();
        $updateMoneyStmt->close();
    }
    
    // Cấp item nếu có
    if ($quest['reward_item_type'] && $quest['reward_item_id']) {
        $itemType = $quest['reward_item_type'];
        $itemId = (int)$quest['reward_item_id'];
        
        if ($itemType === 'theme') {
            // Cấp theme - kiểm tra và thêm vào user_themes
            $checkTable = $conn->query("SHOW TABLES LIKE 'user_themes'");
            if ($checkTable && $checkTable->num_rows > 0) {
                // Kiểm tra user đã có theme này chưa
                $checkSql = "SELECT * FROM user_themes WHERE user_id = ? AND theme_id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("ii", $userId, $itemId);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                $checkStmt->close();
                
                if ($result->num_rows == 0) {
                    // Thêm theme vào user_themes
                    $insertSql = "INSERT INTO user_themes (user_id, theme_id, is_active) VALUES (?, ?, 0)";
                    $insertStmt = $conn->prepare($insertSql);
                    $insertStmt->bind_param("ii", $userId, $itemId);
                    $insertStmt->execute();
                    $insertStmt->close();
                }
            }
        } elseif ($itemType === 'cursor') {
            // Cấp cursor - kiểm tra và thêm vào user_cursors
            $checkTable = $conn->query("SHOW TABLES LIKE 'user_cursors'");
            if ($checkTable && $checkTable->num_rows > 0) {
                // Kiểm tra user đã có cursor này chưa
                $checkSql = "SELECT * FROM user_cursors WHERE user_id = ? AND cursor_id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("ii", $userId, $itemId);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                $checkStmt->close();
                
                if ($result->num_rows == 0) {
                    // Thêm cursor vào user_cursors
                    $insertSql = "INSERT INTO user_cursors (user_id, cursor_id, is_active) VALUES (?, ?, 0)";
                    $insertStmt = $conn->prepare($insertSql);
                    $insertStmt->bind_param("ii", $userId, $itemId);
                    $insertStmt->execute();
                    $insertStmt->close();
                }
            }
        }
        // Có thể thêm các item type khác ở đây (frame, avatar, v.v.)
    }
    
    // Cập nhật is_claimed
    $claimSql = "UPDATE user_quests SET is_claimed = 1, claimed_at = NOW() WHERE user_id = ? AND quest_id = ? AND quest_date = ?";
    $claimStmt = $conn->prepare($claimSql);
    $claimStmt->bind_param("iis", $userId, $questId, $questDate);
    $claimStmt->execute();
    $claimStmt->close();
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Nhận phần thưởng thành công!',
        'reward_money' => $quest['reward_money']
    ]);
}

// Tóm tắt nhanh nhiệm vụ
elseif ($action === 'summary') {
    $questType = $_GET['type'] ?? 'daily';
    $questDate = resolveQuestDate($questType);
    $quests = loadUserQuests($conn, $userId, $questType, $questDate);
    
    $total = count($quests);
    $completed = 0;
    $claimed = 0;
    foreach ($quests as $q) {
        if (!empty($q['is_completed'])) {
            $completed++;
        }
        if (!empty($q['is_claimed'])) {
            $claimed++;
        }
    }
    
    $progressPercent = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
    
    $sorted = $quests;
    usort($sorted, function ($a, $b) {
        if ($a['is_claimed'] != $b['is_claimed']) {
            return $a['is_claimed'] <=> $b['is_claimed'];
        }
        if ($a['is_completed'] != $b['is_completed']) {
            return $b['is_completed'] <=> $a['is_completed'];
        }
        return $b['progress_percent'] <=> $a['progress_percent'];
    });
    
    $highlight = array_slice($sorted, 0, 3);
    
    echo json_encode([
        'status' => 'success',
        'summary' => [
            'total' => $total,
            'completed' => $completed,
            'claimed' => $claimed,
            'progress_percent' => $progressPercent,
            'quest_date' => $questDate,
            'quest_type' => $questType
        ],
        'quests' => $highlight
    ]);
}

else {
    echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ']);
}

// Hàm tính progress thực tế
function calculateQuestProgress($conn, $userId, $quest, $questDate) {
    $progress = 0;
    $requirementType = $quest['requirement_type'];
    $requirementValue = $quest['requirement_value'];
    $gameName = $quest['game_name'] ?? null;
    
    // Kiểm tra bảng game_history có tồn tại không
    $checkTable = $conn->query("SHOW TABLES LIKE 'game_history'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        return 0; // Chưa có game_history thì không thể tính progress
    }
    
    switch ($requirementType) {
        case 'play_games':
            $sql = "SELECT COUNT(*) as count FROM game_history WHERE user_id = ? AND DATE(played_at) = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $userId, $questDate);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $progress = $data['count'] ?? 0;
            $stmt->close();
            break;
            
        case 'win_games':
            $sql = "SELECT COUNT(*) as count FROM game_history WHERE user_id = ? AND is_win = 1 AND DATE(played_at) = ?";
            if ($gameName) {
                $sql = "SELECT COUNT(*) as count FROM game_history WHERE user_id = ? AND is_win = 1 AND game_name = ? AND DATE(played_at) = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $userId, $gameName, $questDate);
            } else {
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("is", $userId, $questDate);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $progress = $data['count'] ?? 0;
            $stmt->close();
            break;
            
        case 'earn_money':
            $sql = "SELECT SUM(win_amount - bet_amount) as total FROM game_history WHERE user_id = ? AND is_win = 1 AND DATE(played_at) = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $userId, $questDate);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $progress = max(0, $data['total'] ?? 0);
            $stmt->close();
            break;
            
        case 'play_specific_game':
            if ($gameName) {
                $sql = "SELECT COUNT(*) as count FROM game_history WHERE user_id = ? AND game_name = ? AND DATE(played_at) = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $userId, $gameName, $questDate);
                $stmt->execute();
                $result = $stmt->get_result();
                $data = $result->fetch_assoc();
                $progress = $data['count'] ?? 0;
                $stmt->close();
            }
            break;
            
        case 'big_win':
            $sql = "SELECT COUNT(*) as count FROM game_history WHERE user_id = ? AND win_amount >= ? AND DATE(played_at) = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ids", $userId, $requirementValue, $questDate);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $progress = $data['count'] ?? 0;
            $stmt->close();
            break;
            
        case 'streak_days':
            $progress = calculatePlayStreakDays($conn, $userId, $questDate, (int)$requirementValue);
            break;
    }
    
    return $progress;
}

function resolveQuestDate(string $questType): string {
    if ($questType === 'weekly') {
        $dayOfWeek = (int)date('w');
        $daysToMonday = ($dayOfWeek === 0) ? 6 : $dayOfWeek - 1;
        return date('Y-m-d', strtotime("-$daysToMonday days"));
    }
    
    return date('Y-m-d');
}

function loadUserQuests(mysqli $conn, int $userId, string $questType, string $questDate): array {
    $quests = [];
    
    $questsSql = "SELECT * FROM quests WHERE quest_type = ? AND is_active = 1 ORDER BY id ASC";
    $questsStmt = $conn->prepare($questsSql);
    if (!$questsStmt) {
        return $quests;
    }
    
    $questsStmt->bind_param("s", $questType);
    $questsStmt->execute();
    $questsResult = $questsStmt->get_result();
    
    while ($quest = $questsResult->fetch_assoc()) {
        $userQuestSql = "SELECT * FROM user_quests WHERE user_id = ? AND quest_id = ? AND quest_date = ?";
        $userQuestStmt = $conn->prepare($userQuestSql);
        if (!$userQuestStmt) {
            continue;
        }
        
        $userQuestStmt->bind_param("iis", $userId, $quest['id'], $questDate);
        $userQuestStmt->execute();
        $userQuestResult = $userQuestStmt->get_result();
        $userQuest = $userQuestResult->fetch_assoc();
        
        if (!$userQuest) {
            $insertSql = "INSERT INTO user_quests (user_id, quest_id, quest_date, progress) VALUES (?, ?, ?, 0)";
            $insertStmt = $conn->prepare($insertSql);
            if ($insertStmt) {
                $insertStmt->bind_param("iis", $userId, $quest['id'], $questDate);
                $insertStmt->execute();
                $insertStmt->close();
            }
            
            $userQuest = [
                'progress' => 0,
                'is_completed' => 0,
                'is_claimed' => 0
            ];
        }
        
        $actualProgress = calculateQuestProgress($conn, $userId, $quest, $questDate);
        
        if ($actualProgress != $userQuest['progress']) {
            $updateSql = "UPDATE user_quests SET progress = ? WHERE user_id = ? AND quest_id = ? AND quest_date = ?";
            $updateStmt = $conn->prepare($updateSql);
            if ($updateStmt) {
                $updateStmt->bind_param("diis", $actualProgress, $userId, $quest['id'], $questDate);
                $updateStmt->execute();
                $updateStmt->close();
            }
            
            $userQuest['progress'] = $actualProgress;
        }
        
        if ($userQuest['progress'] >= $quest['requirement_value'] && !$userQuest['is_completed']) {
            $completeSql = "UPDATE user_quests SET is_completed = 1, completed_at = NOW() WHERE user_id = ? AND quest_id = ? AND quest_date = ?";
            $completeStmt = $conn->prepare($completeSql);
            if ($completeStmt) {
                $completeStmt->bind_param("iis", $userId, $quest['id'], $questDate);
                $completeStmt->execute();
                $completeStmt->close();
            }
            $userQuest['is_completed'] = 1;
        }
        
        $quest['user_progress'] = (float)$userQuest['progress'];
        $quest['is_completed'] = (int)$userQuest['is_completed'];
        $quest['is_claimed'] = (int)$userQuest['is_claimed'];
        $quest['progress_percent'] = $quest['requirement_value'] > 0
            ? round(min(100, ($userQuest['progress'] / $quest['requirement_value']) * 100), 1)
            : 0;
        
        $quests[] = $quest;
        $userQuestStmt->close();
    }
    
    $questsStmt->close();
    return $quests;
}

function calculatePlayStreakDays(mysqli $conn, int $userId, string $questDate, int $requiredDays): int {
    $checkTable = $conn->query("SHOW TABLES LIKE 'game_history'");
    if (!$checkTable || $checkTable->num_rows == 0) {
        return 0;
    }
    
    $targetDays = max(1, $requiredDays);
    $maxLookback = min(60, max(7, $targetDays));
    
    $progress = 0;
    $currentDate = new DateTimeImmutable($questDate);
    
    // Sử dụng prepared statement trong vòng lặp - tạo mới mỗi lần để tránh lỗi bind_param
    for ($i = 0; $i < $maxLookback; $i++) {
        $dateStr = $currentDate->format('Y-m-d');
        
        $stmt = $conn->prepare("SELECT 1 FROM game_history WHERE user_id = ? AND DATE(played_at) = ? LIMIT 1");
        if (!$stmt) {
            break;
        }
        
        $stmt->bind_param("is", $userId, $dateStr);
        $stmt->execute();
        $stmt->store_result();
        $hasPlay = $stmt->num_rows > 0;
        $stmt->close();
        
        if (!$hasPlay) {
            break;
        }
        
        $progress++;
        if ($progress >= $targetDays) {
            break;
        }
        
        $currentDate = $currentDate->modify('-1 day');
    }
    
    return $progress;
}

?>

