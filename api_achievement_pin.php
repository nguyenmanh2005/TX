<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập!']);
    exit();
}

$userId = $_SESSION['Iduser'];
$achId = isset($_POST['ach_id']) ? (int)$_POST['ach_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : 'pin'; // 'pin' or 'unpin'

if ($achId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID danh hiệu không hợp lệ!']);
    exit();
}

// Check if user owns this achievement
$check = $conn->prepare("SELECT id FROM user_achievements WHERE user_id = ? AND achievement_id = ?");
$check->bind_param("ii", $userId, $achId);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Bạn chưa đạt được danh hiệu này!']);
    exit();
}

if ($action === 'pin') {
    // Pin achievement
    $stmt = $conn->prepare("UPDATE user_achievements SET is_pinned = 1 WHERE user_id = ? AND achievement_id = ?");
    $stmt->bind_param("ii", $userId, $achId);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Đã ghim danh hiệu vào hồ sơ!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
} else {
    // Unpin achievement
    $stmt = $conn->prepare("UPDATE user_achievements SET is_pinned = 0 WHERE user_id = ? AND achievement_id = ?");
    $stmt->bind_param("ii", $userId, $achId);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Đã bỏ ghim danh hiệu!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
}
?>
