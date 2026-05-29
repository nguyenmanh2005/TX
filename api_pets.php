<?php
/**
 * 🐾 Pet Offline Rewards API
 * Tính toán và phát thưởng GTLM dựa trên thời gian người chơi offline.
 */
require_once 'db_connect.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['Iduser'];

// 1. Lấy thông tin Pet của người dùng
$stmt = $conn->prepare("SELECT * FROM user_pets WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$pet = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pet) {
    // Tặng Pet tân thủ nếu chưa có
    $petName = 'Linh Khuyển Trận Địa';
    $rate = 500;
    $capacity = 12000;
    $stmtIns = $conn->prepare("INSERT INTO user_pets (user_id, pet_name, hourly_rate, max_capacity) VALUES (?, ?, ?, ?)");
    $stmtIns->bind_param("isii", $userId, $petName, $rate, $capacity);
    $stmtIns->execute();
    $stmtIns->close();
    
    $pet = ['hourly_rate' => 500, 'max_capacity' => 12000, 'last_claim' => date('Y-m-d H:i:s')];
}

// 2. Tính toán số tiền tích lũy
$lastClaim = strtotime($pet['last_claim']);
$timeDiffSeconds = time() - $lastClaim;
$hoursPassed = $timeDiffSeconds / 3600;

$collected = floor($hoursPassed * $pet['hourly_rate']);
$actualCollected = min($pet['max_capacity'], $collected);

$action = $_GET['action'] ?? 'status';

if ($action === 'claim' && $actualCollected > 0) {
    $conn->begin_transaction();
    try {
        // Cộng tiền cho user
        $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
        $stmt->bind_param("di", $actualCollected, $userId);
        $stmt->execute();
        $stmt->close();

        // Reset thời gian nhận
        $stmt = $conn->prepare("UPDATE user_pets SET last_claim = NOW() WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        echo json_encode(['success' => true, 'amount' => $actualCollected]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    // Trả về trạng thái tích lũy hiện tại
    echo json_encode([
        'success' => true,
        'collected' => (int)$actualCollected,
        'max' => (int)$pet['max_capacity'],
        'pet_name' => $pet['pet_name']
    ]);
}
