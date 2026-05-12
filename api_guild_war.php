<?php
session_start();
require_once 'db_connect.php';
require_once 'notification_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập!']);
    exit();
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? '';

// Kiểm tra quyền chủ bang
function isGuildLeader($conn, $userId) {
    $stmt = $conn->prepare("SELECT id FROM guilds WHERE leader_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $guild = $res->fetch_assoc();
    $stmt->close();
    return $guild ? $guild['id'] : false;
}

// Gửi lời thách đấu
if ($action === 'challenge') {
    $myGuildId = isGuildLeader($conn, $userId);
    if (!$myGuildId) {
        echo json_encode(['success' => false, 'message' => 'Chỉ Chủ bang mới có quyền thách đấu!']);
        exit();
    }

    $targetGuildId = (int)$_POST['target_guild_id'];
    if ($myGuildId == $targetGuildId) {
        echo json_encode(['success' => false, 'message' => 'Bạn không thể tự thách đấu bang của mình!']);
        exit();
    }

    // Kiểm tra xem bang kia đã có trận nào chưa
    $stmt = $conn->prepare("SELECT id FROM guild_challenges WHERE (challenger_id = ? OR challenged_id = ?) AND status = 1");
    $stmt->bind_param("ii", $targetGuildId, $targetGuildId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Bang đối thủ đang trong một trận chiến khác!']);
        exit();
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO guild_challenges (challenger_id, challenged_id, status) VALUES (?, ?, 0)");
    $stmt->bind_param("ii", $myGuildId, $targetGuildId);
    if ($stmt->execute()) {
        // Thông báo cho chủ bang đối thủ
        $stmt2 = $conn->prepare("SELECT leader_id, name FROM guilds WHERE id = ?");
        $stmt2->bind_param("i", $targetGuildId);
        $stmt2->execute();
        $target = $stmt2->get_result()->fetch_assoc();
        
        $myGuildNameSql = "SELECT name FROM guilds WHERE id = ?";
        $stmt3 = $conn->prepare($myGuildNameSql);
        $stmt3->bind_param("i", $myGuildId);
        $stmt3->execute();
        $myName = $stmt3->get_result()->fetch_assoc()['name'];

        notifyChallenge($conn, $target['leader_id'], $myName);
        
        echo json_encode(['success' => true, 'message' => 'Đã gửi lời thách đấu! Đang chờ đối phương chấp nhận.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống!']);
    }
    exit();
}

// Chấp nhận thách đấu
if ($action === 'accept') {
    $myGuildId = isGuildLeader($conn, $userId);
    $challengeId = (int)$_POST['challenge_id'];

    $stmt = $conn->prepare("UPDATE guild_challenges SET status = 1, start_time = NOW(), end_time = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ? AND challenged_id = ? AND status = 0");
    $stmt->bind_param("ii", $challengeId, $myGuildId);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Cuộc chiến đã bắt đầu! 24h đua GTLM bắt đầu ngay bây giờ.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Không thể chấp nhận thách đấu!']);
    }
    exit();
}

// Tổng kết trận đấu (thường gọi qua Cron hoặc khi xem kết quả)
if ($action === 'finish') {
    $challengeId = (int)$_POST['challenge_id'];
    $stmt = $conn->prepare("SELECT * FROM guild_challenges WHERE id = ? AND status = 1 AND NOW() > end_time");
    $stmt->execute();
    $c = $stmt->get_result()->fetch_assoc();
    if ($c) {
        $winnerId = ($c['challenger_score'] > $c['challenged_score']) ? $c['challenger_id'] : $c['challenged_id'];
        if ($c['challenger_score'] == $c['challenged_score']) $winnerId = 0; // Hòa

        $conn->begin_transaction();
        try {
            $stmtU = $conn->prepare("UPDATE guild_challenges SET status = 2 WHERE id = ?");
            $stmtU->bind_param("i", $challengeId);
            $stmtU->execute();

            if ($winnerId > 0) {
                $trophyName = "Thắng trận War vs " . (($winnerId == $c['challenger_id']) ? "Bang ID ".$c['challenged_id'] : "Bang ID ".$c['challenger_id']);
                $stmtT = $conn->prepare("INSERT INTO guild_trophies (guild_id, trophy_name) VALUES (?, ?)");
                $stmtT->bind_param("is", $winnerId, $trophyName);
                $stmtT->execute();
                
                $stmtG = $conn->prepare("UPDATE guilds SET trophies_count = trophies_count + 1 WHERE id = ?");
                $stmtG->bind_param("i", $winnerId);
                $stmtG->execute();
            }
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Đã tổng kết trận đấu!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi tổng kết.']);
        }
    }
    exit();
}
?>
