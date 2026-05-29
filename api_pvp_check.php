<?php
/**
 * 🔍 PvP Challenge Checker
 * Kiểm tra xem người dùng hiện tại có lời thách đấu nào đang chờ không.
 */
require_once 'db_connect.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['has_challenge' => false]);
    exit;
}

$userId = $_SESSION['Iduser'];

// Tìm thách đấu mới nhất ở trạng thái 'pending' mà mình là người bị thách đấu
$stmt = $conn->prepare("
    SELECT c.id, c.bet_amount, u.Name as challenger_name 
    FROM pvp_challenges c
    JOIN users u ON c.challenger_id = u.Iduser
    WHERE c.opponent_id = ? AND c.status = 'pending'
    ORDER BY c.created_at DESC LIMIT 1
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($res) {
    echo json_encode([
        'has_challenge' => true,
        'challenge_id' => $res['id'],
        'challenger_name' => $res['challenger_name'],
        'bet' => number_format($res['bet_amount'])
    ]);
} else {
    echo json_encode(['has_challenge' => false]);
}
