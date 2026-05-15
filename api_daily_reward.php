<?php
session_start();
require_once 'db_connect.php';

$userId = $_SESSION['Iduser'] ?? 0;
if (!$userId) exit(json_encode(['success' => false]));

// 1. Tạo bảng stats nếu chưa có
$sqlCreate = "CREATE TABLE IF NOT EXISTS daily_login_stats (
    user_id INT PRIMARY KEY,
    last_claim_date DATE,
    streak INT DEFAULT 0
)";
$conn->query($sqlCreate);

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$today = date('Y-m-d');

switch ($action) {
    case 'check':
        $res = $conn->query("SELECT * FROM daily_login_stats WHERE user_id = $userId");
        $data = $res->fetch_assoc();
        
        if (!$data) {
            echo json_encode(['success' => true, 'can_claim' => true, 'streak' => 0]);
        } else {
            $canClaim = ($data['last_claim_date'] != $today);
            echo json_encode(['success' => true, 'can_claim' => $canClaim, 'streak' => $data['streak']]);
        }
        break;

    case 'claim':
        $res = $conn->query("SELECT * FROM daily_login_stats WHERE user_id = $userId");
        $data = $res->fetch_assoc();
        
        if ($data && $data['last_claim_date'] == $today) {
            echo json_encode(['success' => false, 'message' => 'Hôm nay bạn đã nhận quà rồi!']);
            break;
        }

        $streak = 1;
        if ($data) {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            if ($data['last_claim_date'] == $yesterday) {
                $streak = ($data['streak'] % 7) + 1;
            } else {
                $streak = 1;
            }
        }

        // Tính thưởng
        $rewards = [
            1 => 10000,
            2 => 25000,
            3 => 50000,
            4 => 100000,
            5 => 200000,
            6 => 500000,
            7 => 1000000
        ];
        $amount = $rewards[$streak] ?? 10000;

        $conn->begin_transaction();
        try {
            // Cộng  Gtlm
            $conn->query("UPDATE users SET Money = Money + $amount WHERE Iduser = $userId");
            
            // Cập nhật stats
            if ($data) {
                $conn->query("UPDATE daily_login_stats SET last_claim_date = '$today', streak = $streak WHERE user_id = $userId");
            } else {
                $conn->query("INSERT INTO daily_login_stats (user_id, last_claim_date, streak) VALUES ($userId, '$today', $streak)");
            }

            $conn->commit();
            echo json_encode(['success' => true, 'amount' => $amount, 'streak' => $streak]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;
}
