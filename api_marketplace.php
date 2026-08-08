<?php
/**
 * 🧠 Marketplace API v2.0 - Sàn Giao Dịch Cổ Vật
 * Xử lý mua bán và lưu trữ "linh hồn/lịch sử" vật phẩm.
 */
session_start();
require_once 'db_connect.php';

$userId = $_SESSION['Iduser'] ?? 0;
if (!$userId) exit(json_encode(['success' => false]));

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 🎭 Hàm sinh cốt truyện vật phẩm
function generateItemStory($conn, $itemName, $sellerName) {
    $loreTemplates = [
        "Vật phẩm này từng thuộc sở hữu của cao thủ $sellerName, mang theo linh khí của hàng ngàn ván thắng.",
        "Được đúc kết từ tàn tích của Ma Thần Hủy Diệt, món bảo vật này đã mang lại vận khí cho $sellerName.",
        "Một cổ vật hiếm có từ thời sơ khai của Trận Địa, từng được các đại gia tranh giành kịch liệt.",
        "Mang dấu ấn của những cuộc Guild War khói lửa, món đồ này của $sellerName là minh chứng cho một thời đại hoàng kim."
    ];
    return $conn->real_escape_string($loreTemplates[array_rand($loreTemplates)]);
}

switch ($action) {
    case 'get_listings':
        $res = $conn->query("SELECT m.*, u.Name as seller_name 
                            FROM marketplace_listings m 
                            JOIN users u ON m.seller_id = u.Iduser 
                            WHERE m.status = 'active' 
                            ORDER BY m.created_at DESC");
        $listings = [];
        while ($row = $res->fetch_assoc()) {
            // Mỗi lần load, tăng nhẹ view để tạo hiệu ứng FOMO (hoặc tăng thực tế khi click xem chi tiết)
            $conn->query("UPDATE marketplace_listings SET total_views = total_views + 1 WHERE id = " . $row['id']);
            $listings[] = $row;
        }
        echo json_encode(['success' => true, 'listings' => $listings]);
        break;

    case 'list_item':
        $itemId = (int)$_POST['item_id'];
        $itemType = $_POST['item_type'];
        $price = (int)$_POST['price'];
        $itemName = $conn->real_escape_string($_POST['item_name']);
        
        $uData = $conn->query("SELECT Name FROM users WHERE Iduser = $userId")->fetch_assoc();
        $sellerName = $uData['Name'];
        $story = generateItemStory($conn, $itemName, $sellerName);

        if ($price <= 0) exit(json_encode(['success' => false, 'message' => 'Giá không hợp lệ!']));

        $conn->query("INSERT INTO marketplace_listings (seller_id, item_type, item_id, item_name, price, original_owner_name, highest_price, item_story) 
                     VALUES ($userId, '$itemType', $itemId, '$itemName', $price, '$sellerName', $price, '$story')");
        
        echo json_encode(['success' => true]);
        break;

    case 'buy':
        $listingId = (int) $_POST['id'];
        
        $conn->begin_transaction();
        try {
            // Lock listing to prevent double buying
            $listing = $conn->query("SELECT m.*, u.Name as seller_name FROM marketplace_listings m JOIN users u ON m.seller_id = u.Iduser WHERE m.id = $listingId AND m.status = 'active' FOR UPDATE")->fetch_assoc();
            
            if (!$listing) {
                $conn->rollback();
                exit(json_encode(['success' => false, 'message' => 'Vật phẩm không còn tồn tại hoặc đã bị người khác mua!']));
            }
            if ($listing['seller_id'] == $userId) {
                $conn->rollback();
                exit(json_encode(['success' => false, 'message' => 'Bạn không thể mua đồ của chính mình!']));
            }

            $price = $listing['price'];
            
            // Lock buyer's money
            $buyerData = $conn->query("SELECT Money, Name FROM users WHERE Iduser = $userId FOR UPDATE")->fetch_assoc();
            if ($buyerData['Money'] < $price) {
                $conn->rollback();
                exit(json_encode(['success' => false, 'message' => 'Bạn không đủ GTLM!']));
            }

            // Lock seller's money
            $conn->query("SELECT Iduser FROM users WHERE Iduser = " . $listing['seller_id'] . " FOR UPDATE");

            // Execute transfers
            $conn->query("UPDATE users SET Money = Money - $price WHERE Iduser = $userId");
            $netPrice = $price * 0.95;
            $conn->query("UPDATE users SET Money = Money + $netPrice WHERE Iduser = " . $listing['seller_id']);
            
            // Chuyển quyền sở hữu
            if ($listing['item_type'] == 'title') {
                $conn->query("UPDATE user_titles SET user_id = $userId WHERE user_id = " . $listing['seller_id'] . " AND title_id = " . $listing['item_id']);
            } elseif ($listing['item_type'] == 'frame' || $listing['item_type'] == 'avatar_frame') {
                $conn->query("UPDATE user_avatar_frames SET user_id = $userId WHERE user_id = " . $listing['seller_id'] . " AND frame_id = " . $listing['item_id']);
            }

            // Ghi lịch sử chuyển nhượng
            $conn->query("INSERT INTO marketplace_item_history (listing_id, seller_name, buyer_name, price) 
                         VALUES ($listingId, '{$listing['seller_name']}', '{$buyerData['Name']}', $price)");

            $conn->query("UPDATE marketplace_listings SET status = 'sold' WHERE id = $listingId");
            
            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
        
    case 'get_my_items':
        $titles = $conn->query("SELECT t.id, t.name, 'title' as type FROM achievements t JOIN user_titles ut ON t.id = ut.title_id WHERE ut.user_id = $userId");
        $items = [];
        while($r = $titles->fetch_assoc()) $items[] = $r;
        $frames = $conn->query("SELECT f.id, f.frame_name as name, 'avatar_frame' as type FROM avatar_frames f JOIN user_avatar_frames uaf ON f.id = uaf.frame_id WHERE uaf.user_id = $userId");
        while($r = $frames->fetch_assoc()) $items[] = $r;
        echo json_encode(['success' => true, 'items' => $items]);
        break;
}
