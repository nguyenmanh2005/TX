<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

require_once 'db_connect.php';
require_once 'vocabulary_helper.php';

$userId = (int)$_SESSION['Iduser'];
$action = $_GET['action'] ?? '';

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    $pieces = (int)($_POST['pieces'] ?? 0);
    $message = trim($_POST['message'] ?? 'Chúc may mắn!');
    
    if ($amount < 10000) {
        echo json_encode(['success' => false, 'message' => 'Lì xì tối thiểu 10,000 GTLM']);
        exit;
    }
    if ($pieces < 1 || $pieces > 50) {
        echo json_encode(['success' => false, 'message' => 'Số lượng bao từ 1 đến 50']);
        exit;
    }
    if ($amount / $pieces < 1000) {
        echo json_encode(['success' => false, 'message' => 'Mỗi bao tối thiểu 1,000 GTLM']);
        exit;
    }

    $message = mb_substr($message, 0, 100);
    $message = VocabularyHelper::mask($message);

    $conn->begin_transaction();
    try {
        // Lock user row
        $stmt = $conn->prepare("SELECT Money, Name, ImageURL FROM users WHERE Iduser = ? FOR UPDATE");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($userRow['Money'] < $amount) {
            throw new Exception("Không đủ GTLM để phát lì xì!");
        }

        // Trừ GTLM
        $conn->query("UPDATE users SET Money = Money - $amount WHERE Iduser = $userId");

        // Tạo Red Packet
        $stmt = $conn->prepare("INSERT INTO red_packets (sender_id, total_amount, total_parts, remaining_amount, remaining_parts, message) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("idddis", $userId, $amount, $pieces, $amount, $pieces, $message);
        $stmt->execute();
        $packetId = $stmt->insert_id;
        $stmt->close();

        // Broadcast to chat
        $username = $userRow['Name'];
        $avatar = $userRow['ImageURL'] ?? "https://ui-avatars.com/api/?name=" . urlencode($username);
        $chatMsg = "🧧 Đã phát Lì Xì: $message [Click để nhận](#packet-$packetId)";
        
        $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("isss", $userId, $username, $chatMsg, $avatar);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Đã phát lì xì thành công!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'claim' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $packetId = (int)($_POST['packet_id'] ?? 0);
    
    if ($packetId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Lock packet row
        $stmt = $conn->prepare("SELECT * FROM red_packets WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $packetId);
        $stmt->execute();
        $packet = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$packet) {
            throw new Exception("Lì xì không tồn tại!");
        }
        
        if ($packet['remaining_parts'] <= 0) {
            throw new Exception("Lì xì đã được nhận hết!");
        }

        // Check if already claimed
        $stmt = $conn->prepare("SELECT id FROM red_packet_claims WHERE packet_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $packetId, $userId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $stmt->close();
            throw new Exception("Bạn đã nhận lì xì này rồi!");
        }
        $stmt->close();

        // Calculate amount (random distribution)
        if ($packet['remaining_parts'] == 1) {
            $claimAmount = $packet['remaining_amount'];
        } else {
            $maxClaim = ($packet['remaining_amount'] / $packet['remaining_parts']) * 2;
            $claimAmount = rand(1000, (int)$maxClaim);
        }

        // Create claim
        $stmt = $conn->prepare("INSERT INTO red_packet_claims (packet_id, user_id, amount) VALUES (?, ?, ?)");
        $stmt->bind_param("iid", $packetId, $userId, $claimAmount);
        $stmt->execute();
        $stmt->close();

        // Update packet
        $newRemainingAmt = $packet['remaining_amount'] - $claimAmount;
        $newRemainingPieces = $packet['remaining_parts'] - 1;
        $stmt = $conn->prepare("UPDATE red_packets SET remaining_amount = ?, remaining_parts = ? WHERE id = ?");
        $stmt->bind_param("dii", $newRemainingAmt, $newRemainingPieces, $packetId);
        $stmt->execute();
        $stmt->close();

        // Add money to receiver
        $conn->query("UPDATE users SET Money = Money + $claimAmount WHERE Iduser = $userId");

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Bạn nhận được ' . number_format($claimAmount) . ' GTLM!', 'amount' => $claimAmount]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
