<?php
session_start();
require_once 'db_connect.php';

$userId = $_SESSION['Iduser'] ?? 0;
if (!$userId) exit(json_encode(['success' => false]));

// 1. Khởi tạo Database Marketplace
$setup = "
CREATE TABLE IF NOT EXISTS marketplace_listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT,
    item_type ENUM('title', 'frame', 'item', 'theme', 'cursor', 'chat_frame', 'avatar_frame'),
    item_id INT,
    item_name VARCHAR(255),
    price BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'sold', 'cancelled') DEFAULT 'active'
);
";
$conn->query($setup);
// Thêm cột nếu bảng đã tồn tại
$conn->query("ALTER TABLE marketplace_listings MODIFY COLUMN item_type ENUM('title', 'frame', 'item', 'theme', 'cursor', 'chat_frame', 'avatar_frame')");

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_listings':
        $res = $conn->query("SELECT m.*, u.Name as seller_name 
                            FROM marketplace_listings m 
                            JOIN users u ON m.seller_id = u.Iduser 
                            WHERE m.status = 'active' 
                            ORDER BY m.created_at DESC");
        $listings = [];
        while ($row = $res->fetch_assoc()) $listings[] = $row;
        echo json_encode(['success' => true, 'listings' => $listings]);
        break;

    case 'get_my_items':
        // Lấy danh hiệu sở hữu
        $titles = $conn->query("SELECT t.id, t.name, 'title' as type FROM achievements t JOIN user_titles ut ON t.id = ut.title_id WHERE ut.user_id = $userId");
        $items = [];
        while($r = $titles->fetch_assoc()) $items[] = $r;
        
        // Lấy khung avatar sở hữu
        $frames = $conn->query("SELECT f.id, f.frame_name as name, 'frame' as type FROM avatar_frames f JOIN user_avatar_frames uaf ON f.id = uaf.frame_id WHERE uaf.user_id = $userId");
        while($r = $frames->fetch_assoc()) $items[] = $r;

        echo json_encode(['success' => true, 'items' => $items]);
        break;

    case 'list_item':
        $itemId = (int)$_POST['item_id'];
        $itemType = $_POST['item_type']; // title, frame
        $price = (int)$_POST['price'];
        $itemName = $conn->real_escape_string($_POST['item_name']);

        if ($price <= 0) exit(json_encode(['success' => false, 'message' => 'Giá không hợp lệ!']));

        $conn->query("INSERT INTO marketplace_listings (seller_id, item_type, item_id, item_name, price) 
                     VALUES ($userId, '$itemType', $itemId, '$itemName', $price)");
        
        echo json_encode(['success' => true]);
        break;

    case 'buy':
        $listingId = (int) $_POST['id'];
        $listing = $conn->query("SELECT * FROM marketplace_listings WHERE id = $listingId AND status = 'active'")->fetch_assoc();
        
        if (!$listing) exit(json_encode(['success' => false, 'message' => 'Vật phẩm không còn tồn tại!']));
        if ($listing['seller_id'] == $userId) exit(json_encode(['success' => false, 'message' => 'Bạn không thể mua đồ của chính mình!']));

        $price = $listing['price'];
        $user = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc();
        
        if ($user['Money'] < $price) exit(json_encode(['success' => false, 'message' => 'Bạn không đủ tiền!']));

        $conn->begin_transaction();
        try {
            // Trừ tiền người mua
            $conn->query("UPDATE users SET Money = Money - $price WHERE Iduser = $userId");
            
            // Cộng tiền cho người bán (trừ 5% phí sàn)
            $netPrice = $price * 0.95;
            $conn->query("UPDATE users SET Money = Money + $netPrice WHERE Iduser = " . $listing['seller_id']);
            
            // Chuyển quyền sở hữu vật phẩm
            if ($listing['item_type'] == 'title') {
                $conn->query("UPDATE user_titles SET user_id = $userId WHERE user_id = " . $listing['seller_id'] . " AND title_id = " . $listing['item_id']);
            } elseif ($listing['item_type'] == 'theme') {
                $conn->query("UPDATE user_themes SET user_id = $userId WHERE user_id = " . $listing['seller_id'] . " AND theme_id = " . $listing['item_id']);
            } elseif ($listing['item_type'] == 'cursor') {
                $conn->query("UPDATE user_cursors SET user_id = $userId WHERE user_id = " . $listing['seller_id'] . " AND cursor_id = " . $listing['item_id']);
            } elseif ($listing['item_type'] == 'chat_frame') {
                $conn->query("UPDATE user_chat_frames SET user_id = $userId WHERE user_id = " . $listing['seller_id'] . " AND chat_frame_id = " . $listing['item_id']);
            } elseif ($listing['item_type'] == 'avatar_frame') {
                $conn->query("UPDATE user_avatar_frames SET user_id = $userId WHERE user_id = " . $listing['seller_id'] . " AND avatar_frame_id = " . $listing['item_id']);
            }
            
            // Cập nhật listing
            $conn->query("UPDATE marketplace_listings SET status = 'sold' WHERE id = $listingId");
            
            // Thông báo cho người bán
            require_once 'api_notifications.php';
            sendNotification($conn, $listing['seller_id'], "🛒 Đã bán vật phẩm!", "Vật phẩm " . $listing['item_name'] . " đã được bán với giá " . number_format($price) . " GTLM (Sau thuế: " . number_format($netPrice) . ")", "system");

            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
}
