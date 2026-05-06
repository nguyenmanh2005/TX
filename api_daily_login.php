<?php
/**
 * API xử lý Daily Login Rewards System
 * 
 * Actions:
 * - check_login: Kiểm tra và cập nhật đăng nhập hàng ngày
 * - get_status: Lấy trạng thái đăng nhập của user
 * - claim_reward: Nhận phần thưởng ngày hôm nay
 * - get_rewards_list: Lấy danh sách phần thưởng
 */

session_start();
require 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Kiểm tra bảng tồn tại
$checkTable = $conn->query("SHOW TABLES LIKE 'daily_login_rewards'");
if (!$checkTable || $checkTable->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Hệ thống Daily Login chưa được kích hoạt! Vui lòng chạy file create_daily_login_tables.sql trước.']);
    exit;
}

switch ($action) {
    case 'check_login':
        // Kiểm tra và cập nhật đăng nhập hàng ngày
        $today = date('Y-m-d');

        // Kiểm tra đã đăng nhập hôm nay chưa
        $checkSql = "SELECT * FROM user_daily_login WHERE user_id = ? AND login_date = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("is", $userId, $today);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($existing) {
            // Đã đăng nhập hôm nay rồi
            echo json_encode([
                'success' => true,
                'already_logged_in' => true,
                'consecutive_days' => $existing['consecutive_days'],
                'total_days' => $existing['total_days']
            ]);
            exit;
        }

        // Lấy thông tin đăng nhập gần nhất
        $lastLoginSql = "SELECT * FROM user_daily_login WHERE user_id = ? ORDER BY login_date DESC LIMIT 1";
        $lastLoginStmt = $conn->prepare($lastLoginSql);
        $lastLoginStmt->bind_param("i", $userId);
        $lastLoginStmt->execute();
        $lastLogin = $lastLoginStmt->get_result()->fetch_assoc();
        $lastLoginStmt->close();

        $consecutiveDays = 1;
        $totalDays = 1;
        $streakStartDate = $today;

        if ($lastLogin) {
            $lastDate = new DateTime($lastLogin['login_date']);
            $todayDate = new DateTime($today);
            $diff = $todayDate->diff($lastDate)->days;

            $totalDays = $lastLogin['total_days'] + 1;

            if ($diff == 1) {
                // Đăng nhập liên tiếp
                $consecutiveDays = $lastLogin['consecutive_days'] + 1;
                $streakStartDate = $lastLogin['streak_start_date'] ?? $lastLogin['login_date'];
            } else {
                // Bị gián đoạn, reset chuỗi
                $consecutiveDays = 1;
                $streakStartDate = $today;
            }
        }

        // Tính ngày phần thưởng (chu kỳ 7 ngày)
        $rewardDay = (($consecutiveDays - 1) % 7) + 1;

        // Lưu đăng nhập hôm nay
        $insertSql = "INSERT INTO user_daily_login (user_id, login_date, consecutive_days, total_days, last_reward_day, streak_start_date) 
                      VALUES (?, ?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE 
                      consecutive_days = VALUES(consecutive_days),
                      total_days = VALUES(total_days),
                      last_reward_day = VALUES(last_reward_day)";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("isiiis", $userId, $today, $consecutiveDays, $totalDays, $rewardDay, $streakStartDate);
        $insertStmt->execute();
        $insertStmt->close();

        echo json_encode([
            'success' => true,
            'already_logged_in' => false,
            'consecutive_days' => $consecutiveDays,
            'total_days' => $totalDays,
            'reward_day' => $rewardDay,
            'can_claim' => true
        ]);
        break;

    case 'get_status':
        // Lấy trạng thái đăng nhập của user
        $today = date('Y-m-d');

        // Lấy thông tin đăng nhập hôm nay
        $todaySql = "SELECT * FROM user_daily_login WHERE user_id = ? AND login_date = ?";
        $todayStmt = $conn->prepare($todaySql);
        $todayStmt->bind_param("is", $userId, $today);
        $todayStmt->execute();
        $todayData = $todayStmt->get_result()->fetch_assoc();
        $todayStmt->close();

        // Lấy thông tin đăng nhập gần nhất
        $lastSql = "SELECT * FROM user_daily_login WHERE user_id = ? ORDER BY login_date DESC LIMIT 1";
        $lastStmt = $conn->prepare($lastSql);
        $lastStmt->bind_param("i", $userId);
        $lastStmt->execute();
        $lastData = $lastStmt->get_result()->fetch_assoc();
        $lastStmt->close();

        $consecutiveDays = $lastData ? $lastData['consecutive_days'] : 0;
        $totalDays = $lastData ? $lastData['total_days'] : 0;
        $rewardDay = $lastData ? $lastData['last_reward_day'] : 0;
        $hasLoggedInToday = $todayData ? true : false;

        // Kiểm tra đã nhận phần thưởng hôm nay chưa
        $claimedSql = "SELECT * FROM user_daily_rewards_claimed WHERE user_id = ? AND claimed_date = ?";
        $claimedStmt = $conn->prepare($claimedSql);
        $claimedStmt->bind_param("is", $userId, $today);
        $claimedStmt->execute();
        $claimed = $claimedStmt->get_result()->fetch_assoc();
        $claimedStmt->close();

        $hasClaimedToday = $claimed ? true : false;

        echo json_encode([
            'success' => true,
            'has_logged_in_today' => $hasLoggedInToday,
            'has_claimed_today' => $hasClaimedToday,
            'consecutive_days' => $consecutiveDays,
            'total_days' => $totalDays,
            'reward_day' => $rewardDay,
            'can_claim' => $hasLoggedInToday && !$hasClaimedToday
        ]);
        break;

    case 'claim_reward':
        // Nhận phần thưởng ngày hôm nay
        $today = date('Y-m-d');

        // Kiểm tra đã đăng nhập hôm nay chưa
        $checkSql = "SELECT * FROM user_daily_login WHERE user_id = ? AND login_date = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("is", $userId, $today);
        $checkStmt->execute();
        $loginData = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$loginData) {
            echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng nhập hôm nay!']);
            exit;
        }

        // Kiểm tra đã nhận phần thưởng hôm nay chưa
        $claimedSql = "SELECT * FROM user_daily_rewards_claimed WHERE user_id = ? AND claimed_date = ?";
        $claimedStmt = $conn->prepare($claimedSql);
        $claimedStmt->bind_param("is", $userId, $today);
        $claimedStmt->execute();
        $claimed = $claimedStmt->get_result()->fetch_assoc();
        $claimedStmt->close();

        if ($claimed) {
            echo json_encode(['success' => false, 'message' => 'Bạn đã nhận phần thưởng hôm nay rồi!']);
            exit;
        }

        $rewardDay = $loginData['last_reward_day'];

        // Lấy thông tin phần thưởng
        $rewardSql = "SELECT * FROM daily_login_rewards WHERE day_number = ? AND is_active = 1";
        $rewardStmt = $conn->prepare($rewardSql);
        $rewardStmt->bind_param("i", $rewardDay);
        $rewardStmt->execute();
        $reward = $rewardStmt->get_result()->fetch_assoc();
        $rewardStmt->close();

        if (!$reward) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy phần thưởng!']);
            exit;
        }

        // Bắt đầu transaction
        $conn->begin_transaction();

        try {
            $rewardValue = $reward['reward_value'] * $reward['bonus_multiplier'];

            if ($reward['reward_type'] === 'money') {
                // Cộng gtlm
                $updateSql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("di", $rewardValue, $userId);
                $updateStmt->execute();
                $updateStmt->close();
            } elseif ($reward['reward_type'] === 'item' && $reward['item_type'] && $reward['item_id']) {
                // Thêm item
                $itemType = $reward['item_type'];
                $itemId = $reward['item_id'];

                $tableMap = [
                    'theme' => 'user_themes',
                    'cursor' => 'user_cursors',
                    'chat_frame' => 'user_chat_frames',
                    'avatar_frame' => 'user_avatar_frames'
                ];

                if (isset($tableMap[$itemType])) {
                    $tableName = $tableMap[$itemType];
                    $idColumn = $itemType === 'theme' ? 'theme_id' :
                        ($itemType === 'cursor' ? 'cursor_id' :
                            ($itemType === 'chat_frame' ? 'chat_frame_id' : 'avatar_frame_id'));

                    // Kiểm tra đã có item chưa
                    $checkItemSql = "SELECT * FROM $tableName WHERE user_id = ? AND $idColumn = ?";
                    $checkItemStmt = $conn->prepare($checkItemSql);
                    $checkItemStmt->bind_param("ii", $userId, $itemId);
                    $checkItemStmt->execute();
                    $hasItem = $checkItemStmt->get_result()->num_rows > 0;
                    $checkItemStmt->close();

                    if (!$hasItem) {
                        $insertItemSql = "INSERT INTO $tableName (user_id, $idColumn) VALUES (?, ?)";
                        $insertItemStmt = $conn->prepare($insertItemSql);
                        $insertItemStmt->bind_param("ii", $userId, $itemId);
                        $insertItemStmt->execute();
                        $insertItemStmt->close();
                    }
                }
            }

            // Lưu lịch sử nhận phần thưởng
            $insertClaimedSql = "INSERT INTO user_daily_rewards_claimed 
                                (user_id, reward_id, day_number, claimed_date, reward_type, reward_value, item_type, item_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $insertClaimedStmt = $conn->prepare($insertClaimedSql);
            $insertClaimedStmt->bind_param(
                "iiisdsis",
                $userId,
                $reward['id'],
                $rewardDay,
                $today,
                $reward['reward_type'],
                $rewardValue,
                $reward['item_type'],
                $reward['item_id']
            );
            $insertClaimedStmt->execute();
            $insertClaimedStmt->close();

            $conn->commit();

            // Gửi thông báo
            require_once 'notification_helper.php';
            createNotification(
                $conn,
                $userId,
                'event_update',
                'Nhận Phần Thưởng Đăng Nhập!',
                "Bạn đã nhận được " . number_format($rewardValue) . " gtlm từ phần thưởng đăng nhập ngày $rewardDay!",
                '🎁',
                'daily_login.php',
                null,
                false
            );

            echo json_encode([
                'success' => true,
                'message' => 'Nhận phần thưởng thành công!',
                'reward' => [
                    'type' => $reward['reward_type'],
                    'value' => $rewardValue,
                    'description' => $reward['description']
                ]
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;

    case 'get_rewards_list':
        // Lấy danh sách phần thưởng
        $rewardsSql = "SELECT * FROM daily_login_rewards WHERE is_active = 1 ORDER BY day_number";
        $rewardsResult = $conn->query($rewardsSql);

        $rewards = [];
        while ($row = $rewardsResult->fetch_assoc()) {
            $rewards[] = $row;
        }

        echo json_encode(['success' => true, 'rewards' => $rewards]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ!']);
        break;
}

$conn->close();

