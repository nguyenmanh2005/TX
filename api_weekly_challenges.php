<?php
session_start();
require 'db_connect.php';
require_once 'user_progress_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập!']);
    exit();
}

$userId = (int)$_SESSION['Iduser'];
$action = $_POST['action'] ?? '';

// Week window
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));

if ($action === 'claim') {
    $challengeId = (int)($_POST['challenge_id'] ?? 0);
    if ($challengeId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID thử thách không hợp lệ!']);
        exit();
    }

    // Load challenge
    $sql = "SELECT wc.*, wcp.progress, wcp.is_completed, wcp.claimed
            FROM weekly_challenges wc
            LEFT JOIN weekly_challenge_progress wcp ON wc.id = wcp.challenge_id AND wcp.user_id = ?
            WHERE wc.id = ? AND wc.week_start = ? AND wc.week_end = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $userId, $challengeId, $weekStart, $weekEnd);
    $stmt->execute();
    $challenge = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$challenge) {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thử thách tuần!']);
        exit();
    }

    if ((int)$challenge['is_completed'] !== 1) {
        echo json_encode(['status' => 'error', 'message' => 'Chưa hoàn thành thử thách!']);
        exit();
    }

    if ((int)$challenge['claimed'] === 1) {
        echo json_encode(['status' => 'error', 'message' => 'Đã nhận phần thưởng!']);
        exit();
    }

    // Claim
    $conn->begin_transaction();
    try {
        $up = $conn->prepare("UPDATE weekly_challenge_progress SET claimed = 1, claimed_at = NOW() WHERE user_id = ? AND challenge_id = ?");
        $up->bind_param("ii", $userId, $challengeId);
        $up->execute();
        $up->close();

        if ((int)$challenge['reward_money'] > 0) {
            $money = (int)$challenge['reward_money'];
            $m = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            $m->bind_param("ii", $money, $userId);
            $m->execute();
            $m->close();
        }

        if ((int)$challenge['reward_xp'] > 0) {
            up_add_xp($conn, $userId, (int)$challenge['reward_xp']);
        }

        $conn->commit();
        echo json_encode([
            'status' => 'success',
            'message' => 'Nhận phần thưởng thành công! +' . number_format((int)$challenge['reward_money']) . ' VNĐ, +' . number_format((int)$challenge['reward_xp']) . ' XP'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ!']);

