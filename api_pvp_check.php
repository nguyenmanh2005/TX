<?php
/**
 * 🔍 PvP Challenge Checker
 * Kiểm tra:
 * 1. Lời thách đấu mới (pending) - dành cho người bị thách (Player 2)
 * 2. Challenge vừa được chấp nhận (accepted) - dành cho người thách (Player 1)
 */
require_once 'db_connect.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['has_challenge' => false, 'has_accepted' => false]);
    exit;
}

$userId = $_SESSION['Iduser'];

// 1. Tìm thách đấu mới nhất ở trạng thái 'pending' mà mình là người bị thách đấu
$stmt = $conn->prepare("
    SELECT c.id, c.bet_amount, u.Name as challenger_name 
    FROM pvp_challenges c
    JOIN users u ON c.challenger_id = u.Iduser
    WHERE c.opponent_id = ? AND c.status = 'pending'
    ORDER BY c.created_at DESC LIMIT 1
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$pending = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 2. Tìm challenge mà mình là CHALLENGER và vừa được accept (trong 2 phút qua)
$stmt2 = $conn->prepare("
    SELECT c.id, c.bet_amount, u.Name as opponent_name
    FROM pvp_challenges c
    JOIN users u ON c.opponent_id = u.Iduser
    WHERE c.challenger_id = ? 
      AND c.status = 'accepted'
      AND c.accepted_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
    ORDER BY c.accepted_at DESC LIMIT 1
");
$stmt2->bind_param("i", $userId);
$stmt2->execute();
$accepted = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

$response = [
    'has_challenge' => false,
    'has_accepted'  => false,
];

if ($pending) {
    $response['has_challenge']    = true;
    $response['challenge_id']     = $pending['id'];
    $response['challenger_name']  = $pending['challenger_name'];
    $response['bet']              = number_format($pending['bet_amount']);
}

if ($accepted) {
    $response['has_accepted']     = true;
    $response['accepted_id']      = $accepted['id'];
    $response['accepted_by']      = $accepted['opponent_name'];
    $response['accepted_bet']     = number_format($accepted['bet_amount']);
}

echo json_encode($response);
