<?php
/**
 * API cho Daily Missions System
 * Xử lý các thao tác với nhiệm vụ hàng ngày
 */

session_start();
require 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

$userId = $_SESSION['Iduser'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'get_missions';

// Kiểm tra bảng tồn tại
$checkTable = $conn->query("SHOW TABLES LIKE 'daily_mission_templates'");
if (!$checkTable || $checkTable->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Hệ thống Daily Missions chưa được kích hoạt! Vui lòng chạy file create_daily_missions_tables.sql trước.']);
    exit;
}

/**
 * Tạo nhiệm vụ hàng ngày cho user (nếu chưa có)
 */
function generateDailyMissions($conn, $userId, $date = null) {
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    // Lấy các template active
    $sql = "SELECT * FROM daily_mission_templates WHERE is_active = 1 ORDER BY priority DESC";
    $result = $conn->query($sql);
    
    while ($template = $result->fetch_assoc()) {
        // Kiểm tra đã có nhiệm vụ này chưa
        $checkSql = "SELECT id FROM user_daily_missions 
                     WHERE user_id = ? AND mission_template_id = ? AND mission_date = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("iis", $userId, $template['id'], $date);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->num_rows > 0;
        $checkStmt->close();
        
        if (!$exists) {
            // Tạo nhiệm vụ mới
            $insertSql = "INSERT INTO user_daily_missions 
                         (user_id, mission_template_id, mission_date, requirement_value, progress)
                         VALUES (?, ?, ?, ?, 0)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("iisi", $userId, $template['id'], $date, $template['requirement_value']);
            $insertStmt->execute();
            $insertStmt->close();
        }
    }
}

/**
 * Cập nhật progress cho nhiệm vụ
 */
function updateMissionProgress($conn, $userId, $missionType, $value = 1, $date = null) {
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    // Tìm template theo type
    $templateSql = "SELECT id FROM daily_mission_templates WHERE mission_type = ? AND is_active = 1";
    $templateStmt = $conn->prepare($templateSql);
    $templateStmt->bind_param("s", $missionType);
    $templateStmt->execute();
    $templateResult = $templateStmt->get_result();
    
    while ($template = $templateResult->fetch_assoc()) {
        // Lấy nhiệm vụ của user
        $missionSql = "SELECT * FROM user_daily_missions 
                      WHERE user_id = ? AND mission_template_id = ? AND mission_date = ?";
        $missionStmt = $conn->prepare($missionSql);
        $missionStmt->bind_param("iis", $userId, $template['id'], $date);
        $missionStmt->execute();
        $missionResult = $missionStmt->get_result();
        $mission = $missionResult->fetch_assoc();
        $missionStmt->close();
        
        if ($mission && !$mission['is_completed']) {
            // Cập nhật progress
            $newProgress = min($mission['progress'] + $value, $mission['requirement_value']);
            $isCompleted = ($newProgress >= $mission['requirement_value']);
            
            $updateSql = "UPDATE user_daily_missions 
                         SET progress = ?, is_completed = ?, completed_at = ?
                         WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $completedAt = $isCompleted ? date('Y-m-d H:i:s') : null;
            $updateStmt->bind_param("iisi", $newProgress, $isCompleted, $completedAt, $mission['id']);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }
    
    $templateStmt->close();
}

// Xử lý các action
switch ($action) {
    case 'get_missions':
        // Lấy nhiệm vụ của user hôm nay
        $date = date('Y-m-d');
        
        // Tạo nhiệm vụ nếu chưa có
        generateDailyMissions($conn, $userId, $date);
        
        // Lấy nhiệm vụ
        $sql = "SELECT udm.*, dmt.title, dmt.description, dmt.mission_type, 
                       dmt.reward_type, dmt.reward_value, dmt.reward_item_id, dmt.difficulty
                FROM user_daily_missions udm
                JOIN daily_mission_templates dmt ON udm.mission_template_id = dmt.id
                WHERE udm.user_id = ? AND udm.mission_date = ?
                ORDER BY dmt.priority DESC, dmt.difficulty ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $userId, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $missions = [];
        while ($row = $result->fetch_assoc()) {
            $row['progress_percent'] = $row['requirement_value'] > 0 
                ? min(100, round(($row['progress'] / $row['requirement_value']) * 100))
                : 0;
            $missions[] = $row;
        }
        $stmt->close();
        
        // Lấy thống kê
        $statsSql = "SELECT * FROM user_mission_stats WHERE user_id = ?";
        $statsStmt = $conn->prepare($statsSql);
        $statsStmt->bind_param("i", $userId);
        $statsStmt->execute();
        $statsResult = $statsStmt->get_result();
        $stats = $statsResult->fetch_assoc() ?: [
            'total_missions_completed' => 0,
            'total_rewards_earned' => 0,
            'current_streak' => 0,
            'longest_streak' => 0
        ];
        $statsStmt->close();
        
        echo json_encode([
            'success' => true,
            'missions' => $missions,
            'stats' => $stats,
            'date' => $date
        ]);
        break;
        
    case 'claim_reward':
        // Nhận phần thưởng
        $missionId = (int)($_POST['mission_id'] ?? 0);
        
        if (!$missionId) {
            echo json_encode(['success' => false, 'message' => 'Mission ID không hợp lệ!']);
            exit;
        }
        
        // Lấy thông tin nhiệm vụ
        $sql = "SELECT udm.*, dmt.reward_type, dmt.reward_value, dmt.reward_item_id
                FROM user_daily_missions udm
                JOIN daily_mission_templates dmt ON udm.mission_template_id = dmt.id
                WHERE udm.id = ? AND udm.user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $missionId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $mission = $result->fetch_assoc();
        $stmt->close();
        
        if (!$mission) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy nhiệm vụ!']);
            exit;
        }
        
        if (!$mission['is_completed']) {
            echo json_encode(['success' => false, 'message' => 'Chưa hoàn thành nhiệm vụ!']);
            exit;
        }
        
        if ($mission['is_claimed']) {
            echo json_encode(['success' => false, 'message' => 'Đã nhận phần thưởng rồi!']);
            exit;
        }
        
        // Cập nhật và trao thưởng
        $conn->begin_transaction();
        try {
            // Đánh dấu đã nhận
            $updateSql = "UPDATE user_daily_missions 
                         SET is_claimed = 1, claimed_at = NOW()
                         WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("i", $missionId);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Trao thưởng
            if ($mission['reward_type'] === 'money' && $mission['reward_value'] > 0) {
                $rewardSql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
                $rewardStmt = $conn->prepare($rewardSql);
                $rewardStmt->bind_param("di", $mission['reward_value'], $userId);
                $rewardStmt->execute();
                $rewardStmt->close();
            }
            
            // Ghi lịch sử
            $historySql = "INSERT INTO daily_mission_history 
                          (user_id, mission_template_id, mission_date, reward_type, reward_value, reward_item_id)
                          VALUES (?, ?, ?, ?, ?, ?)";
            $historyStmt = $conn->prepare($historySql);
            $historyStmt->bind_param("iissdi", 
                $userId, 
                $mission['mission_template_id'],
                $mission['mission_date'],
                $mission['reward_type'],
                $mission['reward_value'],
                $mission['reward_item_id']
            );
            $historyStmt->execute();
            $historyStmt->close();
            
            // Cập nhật stats
            $statsSql = "INSERT INTO user_mission_stats 
                        (user_id, total_missions_completed, total_rewards_earned, last_mission_date)
                        VALUES (?, 1, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        total_missions_completed = total_missions_completed + 1,
                        total_rewards_earned = total_rewards_earned + VALUES(total_rewards_earned),
                        last_mission_date = VALUES(last_mission_date)";
            $statsStmt = $conn->prepare($statsSql);
            $today = date('Y-m-d');
            $statsStmt->bind_param("ids", $userId, $mission['reward_value'], $today);
            $statsStmt->execute();
            $statsStmt->close();
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Nhận phần thưởng thành công!',
                'reward' => [
                    'type' => $mission['reward_type'],
                    'value' => $mission['reward_value']
                ]
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_history':
        // Lấy lịch sử hoàn thành
        $limit = (int)($_GET['limit'] ?? 20);
        
        $sql = "SELECT dmh.*, dmt.title, dmt.description
                FROM daily_mission_history dmh
                JOIN daily_mission_templates dmt ON dmh.mission_template_id = dmt.id
                WHERE dmh.user_id = ?
                ORDER BY dmh.completed_at DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'history' => $history]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ!']);
        break;
}

$conn->close();







