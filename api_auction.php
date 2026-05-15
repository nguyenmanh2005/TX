<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_list':
        $sql = "SELECT a.*, u.Name as seller_name 
                FROM auctions a 
                JOIN users u ON a.seller_id = u.Iduser 
                WHERE a.status = 'active' AND a.ends_at > NOW() 
                ORDER BY a.created_at DESC";
        $res = $conn->query($sql);
        $list = [];
        while ($row = $res->fetch_assoc()) $list[] = $row;
        echo json_encode(['success' => true, 'list' => $list]);
        break;

    case 'get_my_items':
        $type = $_GET['type'] ?? 'avatar_frame';
        $tableMap = [
            'avatar_frame' => ['user' => 'user_avatar_frames', 'item' => 'avatar_frames', 'id' => 'avatar_frame_id'],
            'theme' => ['user' => 'user_themes', 'item' => 'themes', 'id' => 'theme_id'],
            'cursor' => ['user' => 'user_cursors', 'item' => 'cursors', 'id' => 'cursor_id'],
            'chat_frame' => ['user' => 'user_chat_frames', 'item' => 'chat_frames', 'id' => 'chat_frame_id'],
            'title' => ['user' => 'user_achievements', 'item' => 'achievements', 'id' => 'achievement_id']
        ];

        if (!isset($tableMap[$type])) {
            echo json_encode(['success' => false, 'message' => 'Loại vật phẩm không hợp lệ!']);
            exit;
        }

        $cfg = $tableMap[$type];
        
        // FIX N+1: Sử dụng NOT EXISTS để lọc item đã lên sàn hoặc chợ ngay trong 1 query
        $sql = "SELECT ut.*, i.name, i.icon 
                FROM {$cfg['user']} ut 
                JOIN {$cfg['item']} i ON ut.{$cfg['id']} = i.id 
                WHERE ut.user_id = ? 
                AND NOT EXISTS (
                    SELECT 1 FROM auctions WHERE item_type = ? AND item_id = ut.{$cfg['id']} AND status = 'active'
                )
                AND NOT EXISTS (
                    SELECT 1 FROM marketplace_listings WHERE item_type = ? AND item_id = ut.{$cfg['id']} AND status = 'active'
                )";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $userId, $type, $type);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['success' => true, 'items' => $items]);
        break;

    case 'create':
        $itemType = $_POST['item_type'] ?? '';
        $itemId = (int)($_POST['item_id'] ?? 0);
        $startPrice = (int)($_POST['start_price'] ?? 0);
        $buyoutPrice = !empty($_POST['buyout_price']) ? (int)$_POST['buyout_price'] : null;
        $durationHours = (int)($_POST['duration_hours'] ?? 24);

        if ($startPrice < 1000) exit(json_encode(['success' => false, 'message' => 'Giá tối thiểu 1,000!']));
        if ($durationHours < 1 || $durationHours > 72) $durationHours = 24;

        $itemTables = ['avatar_frame' => 'avatar_frames', 'theme' => 'themes', 'cursor' => 'cursors', 'chat_frame' => 'chat_frames', 'title' => 'achievements'];
        if (!isset($itemTables[$itemType])) exit(json_encode(['success' => false, 'message' => 'Loại không hợp lệ!']));

        $stmt = $conn->prepare("SELECT name, icon FROM {$itemTables[$itemType]} WHERE id = ?");
        $stmt->bind_param("i", $itemId);
        $stmt->execute();
        $itemData = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$itemData) exit(json_encode(['success' => false, 'message' => 'Vật phẩm không tồn tại!']));

        $endsAt = date('Y-m-d H:i:s', strtotime("+$durationHours hours"));
        $stmt = $conn->prepare("INSERT INTO auctions (seller_id, item_type, item_id, item_name, item_icon, start_price, current_price, buyout_price, ends_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isisssiis", $userId, $itemType, $itemId, $itemData['name'], $itemData['icon'], $startPrice, $startPrice, $buyoutPrice, $endsAt);
        
        if ($stmt->execute()) echo json_encode(['success' => true, 'message' => 'Đã lên sàn!']);
        else echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống']);
        break;

    case 'bid':
        $auctionId = (int)$_POST['auction_id'];
        $amount = (int)$_POST['amount'];

        $conn->begin_transaction();
        try {
            // FIX: Khóa phiên đấu giá
            $stmt = $conn->prepare("SELECT * FROM auctions WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $auctionId);
            $stmt->execute();
            $auction = $stmt->get_result()->fetch_assoc();

            if (!$auction || $auction['status'] !== 'active' || strtotime($auction['ends_at']) < time()) {
                throw new Exception("Phiên đấu giá đã kết thúc!");
            }

            if ($userId == $auction['seller_id']) throw new Exception("Không thể tự đấu giá!");

            $minBid = ($auction['winner_id'] === null) ? $auction['start_price'] : ($auction['current_price'] + $auction['min_increment']);
            if ($amount < $minBid) throw new Exception("Giá đặt tối thiểu là " . number_format($minBid));

            // FIX: Khóa tài khoản người đặt giá
            $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $userMoney = $stmt->get_result()->fetch_assoc()['Money'];

            if ($userMoney < $amount) throw new Exception("Số dư không đủ!");

            // Hoàn  Gtlm người cũ (nếu có)
            if ($auction['winner_id'] !== null) {
                $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
                $stmt->bind_param("ii", $auction['current_price'], $auction['winner_id']);
                $stmt->execute();
            }

            // Trừ  Gtlm người mới
            $stmt = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
            $stmt->bind_param("ii", $amount, $userId);
            $stmt->execute();

            // Cập nhật đấu giá & Anti-snipe
            $newEndsAt = $auction['ends_at'];
            if (strtotime($auction['ends_at']) - time() < 300) $newEndsAt = date('Y-m-d H:i:s', time() + 300);

            $stmt = $conn->prepare("UPDATE auctions SET current_price = ?, winner_id = ?, ends_at = ? WHERE id = ?");
            $stmt->bind_param("iisi", $amount, $userId, $newEndsAt, $auctionId);
            $stmt->execute();

            // Lưu lịch sử
            $stmt = $conn->prepare("INSERT INTO auction_bids (auction_id, bidder_id, amount) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $auctionId, $userId, $amount);
            $stmt->execute();

            if ($auction['buyout_price'] > 0 && $amount >= $auction['buyout_price']) {
                settleAuction($auctionId);
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Mua đứt thành công!', 'buyout' => true]);
                exit;
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Đặt giá thành công!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
}

function settleAuction($auctionId) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM auctions WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $auctionId);
    $stmt->execute();
    $auction = $stmt->get_result()->fetch_assoc();
    if (!$auction || $auction['status'] !== 'active') return;

    if ($auction['winner_id'] !== null) {
        // Thanh toán người bán (95%)
        $payout = $auction['current_price'] * 0.95;
        $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
        $stmt->bind_param("di", $payout, $auction['seller_id']);
        $stmt->execute();

        // Chuyển quyền sở hữu
        $type = $auction['item_type'];
        $tableMap = ['avatar_frame' => ['t' => 'user_avatar_frames', 'id' => 'avatar_frame_id'], 'theme' => ['t' => 'user_themes', 'id' => 'theme_id'], 'cursor' => ['t' => 'user_cursors', 'id' => 'cursor_id'], 'chat_frame' => ['t' => 'user_chat_frames', 'id' => 'chat_frame_id'], 'title' => ['t' => 'user_achievements', 'id' => 'achievement_id']];
        $cfg = $tableMap[$type];
        
        $conn->query("DELETE FROM {$cfg['t']} WHERE user_id = {$auction['seller_id']} AND {$cfg['id']} = {$auction['item_id']}");
        $conn->query("INSERT IGNORE INTO {$cfg['t']} (user_id, {$cfg['id']}) VALUES ({$auction['winner_id']}, {$auction['item_id']})");

        // Gửi thông báo an toàn
        $msgW = "Bạn đã thắng đấu giá {$auction['item_name']}.";
        $msgS = "Vật phẩm {$auction['item_name']} đã bán với giá " . number_format($auction['current_price']);
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?), (?, ?)");
        $stmt->bind_param("isis", $auction['winner_id'], $msgW, $auction['seller_id'], $msgS);
        $stmt->execute();
    }
    $conn->query("UPDATE auctions SET status = 'ended' WHERE id = $auctionId");
}
?>
