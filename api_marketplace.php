<?php
/**
 * API xử lý Marketplace/Trading System
 * 
 * Actions:
 * - list_item: Đăng bán item
 * - get_listings: Lấy danh sách items đang bán
 * - get_listing: Lấy chi tiết 1 listing
 * - buy_item: Mua item
 * - cancel_listing: Hủy listing
 * - get_my_listings: Lấy danh sách items của mình đang bán
 * - get_my_purchases: Lấy lịch sử mua hàng
 * - get_my_sales: Lấy lịch sử bán hàng
 * - add_to_wishlist: Thêm vào wishlist
 * - remove_from_wishlist: Xóa khỏi wishlist
 * - get_wishlist: Lấy wishlist
 */

session_start();
require 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Kiểm tra bảng tồn tại
$checkTable = $conn->query("SHOW TABLES LIKE 'marketplace_items'");
if (!$checkTable || $checkTable->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Hệ thống Marketplace chưa được kích hoạt! Vui lòng chạy file ALL_DATABASE_TABLES.sql trước.']);
    exit;
}

switch ($action) {
    case 'list_item':
        // Đăng bán item
        $itemType = $_POST['item_type'] ?? '';
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $price = (float) ($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $expiresDays = (int) ($_POST['expires_days'] ?? 0);

        if (!in_array($itemType, ['theme', 'cursor', 'chat_frame', 'avatar_frame'])) {
            echo json_encode(['success' => false, 'message' => 'Loại item không hợp lệ!']);
            exit;
        }

        if ($itemId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Item ID không hợp lệ!']);
            exit;
        }

        if ($price <= 0 || $price > 100000000) {
            echo json_encode(['success' => false, 'message' => 'Giá phải từ 1 đến 100.000.000 gtlm!']);
            exit;
        }

        // Kiểm tra user có item này không
        $tableMap = [
            'theme' => 'user_themes',
            'cursor' => 'user_cursors',
            'chat_frame' => 'user_chat_frames',
            'avatar_frame' => 'user_avatar_frames'
        ];

        $tableName = $tableMap[$itemType];
        $itemIdColumn = $itemType === 'theme' ? 'theme_id' :
            ($itemType === 'cursor' ? 'cursor_id' :
                ($itemType === 'chat_frame' ? 'chat_frame_id' : 'avatar_frame_id'));

        $checkSql = "SELECT * FROM $tableName WHERE user_id = ? AND $itemIdColumn = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("ii", $userId, $itemId);
        $checkStmt->execute();
        $hasItem = $checkStmt->get_result()->num_rows > 0;
        $checkStmt->close();

        if (!$hasItem) {
            echo json_encode(['success' => false, 'message' => 'Bạn không sở hữu item này!']);
            exit;
        }

        // Kiểm tra đã có listing active chưa
        $checkListingSql = "SELECT * FROM marketplace_items WHERE seller_id = ? AND item_type = ? AND item_id = ? AND status = 'active'";
        $checkListingStmt = $conn->prepare($checkListingSql);
        $checkListingStmt->bind_param("isi", $userId, $itemType, $itemId);
        $checkListingStmt->execute();
        if ($checkListingStmt->get_result()->num_rows > 0) {
            $checkListingStmt->close();
            echo json_encode(['success' => false, 'message' => 'Item này đã được đăng bán rồi!']);
            exit;
        }
        $checkListingStmt->close();

        // Tính expires_at
        $expiresAt = null;
        if ($expiresDays > 0) {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+$expiresDays days"));
        }

        // Bắt đầu transaction
        $conn->begin_transaction();

        try {
            // Xóa item từ inventory của seller
            $deleteSql = "DELETE FROM $tableName WHERE user_id = ? AND $itemIdColumn = ?";
            $deleteStmt = $conn->prepare($deleteSql);
            $deleteStmt->bind_param("ii", $userId, $itemId);
            $deleteStmt->execute();
            $deleteStmt->close();

            // Tạo listing
            $insertSql = "INSERT INTO marketplace_items (seller_id, item_type, item_id, price, description, expires_at) 
                         VALUES (?, ?, ?, ?, ?, ?)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("isidss", $userId, $itemType, $itemId, $price, $description, $expiresAt);
            $insertStmt->execute();
            $listingId = $conn->insert_id;
            $insertStmt->close();

            $conn->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Đăng bán thành công!',
                'listing_id' => $listingId
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;

    case 'get_listings':
        // Lấy danh sách items đang bán
        $itemType = $_GET['item_type'] ?? '';
        $minPrice = isset($_GET['min_price']) ? (float) $_GET['min_price'] : null;
        $maxPrice = isset($_GET['max_price']) ? (float) $_GET['max_price'] : null;
        $sortBy = $_GET['sort_by'] ?? 'created_at';
        $sortOrder = $_GET['sort_order'] ?? 'DESC';
        $limit = (int) ($_GET['limit'] ?? 50);
        $offset = (int) ($_GET['offset'] ?? 0);
        $searchQuery = trim($_GET['search'] ?? '');
        $rarity = $_GET['rarity'] ?? '';

        $sql = "SELECT mi.*, u.Name as seller_name, u.ImageURL as seller_avatar,
                u.active_title_id, a.icon as seller_title_icon, a.name as seller_title_name
                FROM marketplace_items mi
                INNER JOIN users u ON mi.seller_id = u.Iduser
                LEFT JOIN achievements a ON u.active_title_id = a.id
                WHERE mi.status = 'active'";

        $params = [];
        $types = '';

        if ($itemType) {
            $sql .= " AND mi.item_type = ?";
            $params[] = $itemType;
            $types .= 's';
        }

        if ($minPrice !== null) {
            $sql .= " AND mi.price >= ?";
            $params[] = $minPrice;
            $types .= 'd';
        }

        if ($maxPrice !== null) {
            $sql .= " AND mi.price <= ?";
            $params[] = $maxPrice;
            $types .= 'd';
        }

        // Kiểm tra hết hạn
        $sql .= " AND (mi.expires_at IS NULL OR mi.expires_at > NOW())";

        // Search filter (tìm trong item name hoặc description)
        if ($searchQuery) {
            $sql .= " AND (mi.description LIKE ? OR EXISTS (
                SELECT 1 FROM themes t WHERE t.id = mi.item_id AND mi.item_type = 'theme' AND t.theme_name LIKE ?
                UNION ALL
                SELECT 1 FROM cursors c WHERE c.id = mi.item_id AND mi.item_type = 'cursor' AND c.cursor_name LIKE ?
                UNION ALL
                SELECT 1 FROM chat_frames cf WHERE cf.id = mi.item_id AND mi.item_type = 'chat_frame' AND cf.frame_name LIKE ?
                UNION ALL
                SELECT 1 FROM avatar_frames af WHERE af.id = mi.item_id AND mi.item_type = 'avatar_frame' AND af.frame_name LIKE ?
            ))";
            $searchParam = '%' . $searchQuery . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= 'sssss';
        }

        // Rarity filter (nếu có cột rarity trong các bảng items)
        if ($rarity && in_array($rarity, ['common', 'rare', 'epic', 'legendary'])) {
            // Note: Cần kiểm tra xem bảng có cột rarity không
            $sql .= " AND EXISTS (
                SELECT 1 FROM themes t WHERE t.id = mi.item_id AND mi.item_type = 'theme' AND t.rarity = ?
                UNION ALL
                SELECT 1 FROM cursors c WHERE c.id = mi.item_id AND mi.item_type = 'cursor' AND (c.rarity = ? OR c.rarity IS NULL)
                UNION ALL
                SELECT 1 FROM chat_frames cf WHERE cf.id = mi.item_id AND mi.item_type = 'chat_frame' AND cf.rarity = ?
                UNION ALL
                SELECT 1 FROM avatar_frames af WHERE af.id = mi.item_id AND mi.item_type = 'avatar_frame' AND af.rarity = ?
            )";
            $params[] = $rarity;
            $params[] = $rarity;
            $params[] = $rarity;
            $params[] = $rarity;
            $types .= 'ssss';
        }

        // Sort
        $allowedSorts = ['created_at', 'price', 'views'];
        $sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'created_at';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY mi.$sortBy $sortOrder";

        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $conn->prepare($sql);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $listings = [];
        while ($row = $result->fetch_assoc()) {
            // Lấy tên item
            $itemName = getItemName($conn, $row['item_type'], $row['item_id']);
            $row['item_name'] = $itemName;
            $listings[] = $row;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'listings' => $listings]);
        break;

    case 'get_listing':
        // Lấy chi tiết 1 listing
        $listingId = (int) ($_GET['listing_id'] ?? 0);

        if ($listingId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Listing ID không hợp lệ!']);
            exit;
        }

        $sql = "SELECT mi.*, u.Name as seller_name, u.ImageURL as seller_avatar,
                u.active_title_id, a.icon as seller_title_icon, a.name as seller_title_name
                FROM marketplace_items mi
                INNER JOIN users u ON mi.seller_id = u.Iduser
                LEFT JOIN achievements a ON u.active_title_id = a.id
                WHERE mi.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $listingId);
        $stmt->execute();
        $listing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$listing) {
            echo json_encode(['success' => false, 'message' => 'Listing không tồn tại!']);
            exit;
        }

        // Tăng views
        $updateViewsSql = "UPDATE marketplace_items SET views = views + 1 WHERE id = ?";
        $updateViewsStmt = $conn->prepare($updateViewsSql);
        $updateViewsStmt->bind_param("i", $listingId);
        $updateViewsStmt->execute();
        $updateViewsStmt->close();

        // Lấy tên item
        $itemName = getItemName($conn, $listing['item_type'], $listing['item_id']);
        $listing['item_name'] = $itemName;

        // Kiểm tra có trong wishlist không
        $wishlistSql = "SELECT * FROM marketplace_wishlist WHERE user_id = ? AND listing_id = ?";
        $wishlistStmt = $conn->prepare($wishlistSql);
        $wishlistStmt->bind_param("ii", $userId, $listingId);
        $wishlistStmt->execute();
        $listing['in_wishlist'] = $wishlistStmt->get_result()->num_rows > 0;
        $wishlistStmt->close();

        echo json_encode(['success' => true, 'listing' => $listing]);
        break;

    case 'buy_item':
        // Mua item
        $listingId = (int) ($_POST['listing_id'] ?? 0);

        if ($listingId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Listing ID không hợp lệ!']);
            exit;
        }

        // Lấy thông tin listing
        $listingSql = "SELECT * FROM marketplace_items WHERE id = ? AND status = 'active'";
        $listingStmt = $conn->prepare($listingSql);
        $listingStmt->bind_param("i", $listingId);
        $listingStmt->execute();
        $listing = $listingStmt->get_result()->fetch_assoc();
        $listingStmt->close();

        if (!$listing) {
            echo json_encode(['success' => false, 'message' => 'Listing không tồn tại hoặc đã bán!']);
            exit;
        }

        if ($listing['seller_id'] == $userId) {
            echo json_encode(['success' => false, 'message' => 'Bạn không thể mua item của chính mình!']);
            exit;
        }

        // Kiểm tra hết hạn
        if ($listing['expires_at'] && strtotime($listing['expires_at']) < time()) {
            echo json_encode(['success' => false, 'message' => 'Listing đã hết hạn!']);
            exit;
        }

        // Kiểm tra Số Gtlm
        $userMoneySql = "SELECT Money FROM users WHERE Iduser = ?";
        $userMoneyStmt = $conn->prepare($userMoneySql);
        $userMoneyStmt->bind_param("i", $userId);
        $userMoneyStmt->execute();
        $userMoneyResult = $userMoneyStmt->get_result();
        $userMoneyData = $userMoneyResult ? $userMoneyResult->fetch_assoc() : null;
        $userMoney = $userMoneyData ? $userMoneyData['Money'] : 0;
        $userMoneyStmt->close();

        if ($userMoney < $listing['price']) {
            echo json_encode(['success' => false, 'message' => 'Số Gtlm không đủ!']);
            exit;
        }

        // Kiểm tra buyer đã có item này chưa
        $tableMap = [
            'theme' => 'user_themes',
            'cursor' => 'user_cursors',
            'chat_frame' => 'user_chat_frames',
            'avatar_frame' => 'user_avatar_frames'
        ];

        $tableName = $tableMap[$listing['item_type']];
        $itemIdColumn = $listing['item_type'] === 'theme' ? 'theme_id' :
            ($listing['item_type'] === 'cursor' ? 'cursor_id' :
                ($listing['item_type'] === 'chat_frame' ? 'chat_frame_id' : 'avatar_frame_id'));

        $checkBuyerSql = "SELECT * FROM $tableName WHERE user_id = ? AND $itemIdColumn = ?";
        $checkBuyerStmt = $conn->prepare($checkBuyerSql);
        $checkBuyerStmt->bind_param("ii", $userId, $listing['item_id']);
        $checkBuyerStmt->execute();
        if ($checkBuyerStmt->get_result()->num_rows > 0) {
            $checkBuyerStmt->close();
            echo json_encode(['success' => false, 'message' => 'Bạn đã có item này rồi!']);
            exit;
        }
        $checkBuyerStmt->close();

        // Tính phí giao dịch (5%)
        $transactionFee = $listing['price'] * 0.05;
        $sellerReceived = $listing['price'] - $transactionFee;

        // Bắt đầu transaction
        $conn->begin_transaction();

        try {
            // Trừ gtlm buyer
            $deductSql = "UPDATE users SET Money = Money - ? WHERE Iduser = ?";
            $deductStmt = $conn->prepare($deductSql);
            $deductStmt->bind_param("di", $listing['price'], $userId);
            $deductStmt->execute();
            $deductStmt->close();

            // Cộng gtlm seller
            $addSql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
            $addStmt = $conn->prepare($addSql);
            $addStmt->bind_param("di", $sellerReceived, $listing['seller_id']);
            $addStmt->execute();
            $addStmt->close();

            // Thêm item cho buyer
            $insertItemSql = "INSERT INTO $tableName (user_id, $itemIdColumn) VALUES (?, ?)";
            $insertItemStmt = $conn->prepare($insertItemSql);
            $insertItemStmt->bind_param("ii", $userId, $listing['item_id']);
            $insertItemStmt->execute();
            $insertItemStmt->close();

            // Cập nhật listing
            $updateListingSql = "UPDATE marketplace_items SET status = 'sold', sold_at = NOW() WHERE id = ?";
            $updateListingStmt = $conn->prepare($updateListingSql);
            $updateListingStmt->bind_param("i", $listingId);
            $updateListingStmt->execute();
            $updateListingStmt->close();

            // Lưu transaction
            $insertTransSql = "INSERT INTO marketplace_transactions 
                             (listing_id, seller_id, buyer_id, item_type, item_id, price, transaction_fee, seller_received) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $insertTransStmt = $conn->prepare($insertTransSql);
            $insertTransStmt->bind_param(
                "iiiisidd",
                $listingId,
                $listing['seller_id'],
                $userId,
                $listing['item_type'],
                $listing['item_id'],
                $listing['price'],
                $transactionFee,
                $sellerReceived
            );
            $insertTransStmt->execute();
            $insertTransStmt->close();

            // Xóa khỏi wishlist nếu có
            $deleteWishlistSql = "DELETE FROM marketplace_wishlist WHERE listing_id = ?";
            $deleteWishlistStmt = $conn->prepare($deleteWishlistSql);
            $deleteWishlistStmt->bind_param("i", $listingId);
            $deleteWishlistStmt->execute();
            $deleteWishlistStmt->close();

            $conn->commit();

            // Gửi thông báo
            require_once 'notification_helper.php';
            $buyerStmt = $conn->prepare("SELECT Name FROM users WHERE Iduser = ?");
            $buyerStmt->bind_param("i", $userId);
            $buyerStmt->execute();
            $buyerResult = $buyerStmt->get_result();
            $buyerData = $buyerResult->fetch_assoc();
            $buyerName = $buyerData['Name'] ?? 'Ai đó';
            $buyerStmt->close();
            createNotification(
                $conn,
                $listing['seller_id'],
                'gift_received',
                'Item Đã Được Bán!',
                "$buyerName đã mua item của bạn với giá " . number_format($listing['price']) . " gtlm!",
                '💰',
                'marketplace.php?tab=my_sales',
                $listingId,
                false
            );

            echo json_encode([
                'success' => true,
                'message' => 'Mua item thành công!',
                'transaction_fee' => $transactionFee,
                'seller_received' => $sellerReceived
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;

    case 'cancel_listing':
        // Hủy listing
        $listingId = (int) ($_POST['listing_id'] ?? 0);

        if ($listingId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Listing ID không hợp lệ!']);
            exit;
        }

        // Kiểm tra quyền
        $checkSql = "SELECT * FROM marketplace_items WHERE id = ? AND seller_id = ? AND status = 'active'";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("ii", $listingId, $userId);
        $checkStmt->execute();
        $listing = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$listing) {
            echo json_encode(['success' => false, 'message' => 'Listing không tồn tại hoặc bạn không có quyền!']);
            exit;
        }

        // Bắt đầu transaction
        $conn->begin_transaction();

        try {
            // Trả item về inventory
            $tableMap = [
                'theme' => 'user_themes',
                'cursor' => 'user_cursors',
                'chat_frame' => 'user_chat_frames',
                'avatar_frame' => 'user_avatar_frames'
            ];

            $tableName = $tableMap[$listing['item_type']];
            $itemIdColumn = $listing['item_type'] === 'theme' ? 'theme_id' :
                ($listing['item_type'] === 'cursor' ? 'cursor_id' :
                    ($listing['item_type'] === 'chat_frame' ? 'chat_frame_id' : 'avatar_frame_id'));

            $insertItemSql = "INSERT INTO $tableName (user_id, $itemIdColumn) VALUES (?, ?)";
            $insertItemStmt = $conn->prepare($insertItemSql);
            $insertItemStmt->bind_param("ii", $userId, $listing['item_id']);
            $insertItemStmt->execute();
            $insertItemStmt->close();

            // Cập nhật listing
            $updateSql = "UPDATE marketplace_items SET status = 'cancelled' WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("i", $listingId);
            $updateStmt->execute();
            $updateStmt->close();

            // Xóa khỏi wishlist
            $deleteWishlistSql = "DELETE FROM marketplace_wishlist WHERE listing_id = ?";
            $deleteWishlistStmt = $conn->prepare($deleteWishlistSql);
            $deleteWishlistStmt->bind_param("i", $listingId);
            $deleteWishlistStmt->execute();
            $deleteWishlistStmt->close();

            $conn->commit();

            echo json_encode(['success' => true, 'message' => 'Đã hủy listing!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;

    case 'get_my_listings':
        // Lấy danh sách items của mình đang bán
        $status = $_GET['status'] ?? 'active';

        $sql = "SELECT mi.* FROM marketplace_items mi WHERE mi.seller_id = ?";

        if ($status !== 'all') {
            $sql .= " AND mi.status = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $userId, $status);
        } else {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $userId);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $listings = [];
        while ($row = $result->fetch_assoc()) {
            $itemName = getItemName($conn, $row['item_type'], $row['item_id']);
            $row['item_name'] = $itemName;
            $listings[] = $row;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'listings' => $listings]);
        break;

    case 'get_my_purchases':
        // Lấy lịch sử mua hàng
        $limit = (int) ($_GET['limit'] ?? 50);

        $sql = "SELECT mt.*, u.Name as seller_name, u.ImageURL as seller_avatar
                FROM marketplace_transactions mt
                INNER JOIN users u ON mt.seller_id = u.Iduser
                WHERE mt.buyer_id = ?
                ORDER BY mt.created_at DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $purchases = [];
        while ($row = $result->fetch_assoc()) {
            $itemName = getItemName($conn, $row['item_type'], $row['item_id']);
            $row['item_name'] = $itemName;
            $purchases[] = $row;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'purchases' => $purchases]);
        break;

    case 'get_my_sales':
        // Lấy lịch sử bán hàng
        $limit = (int) ($_GET['limit'] ?? 50);

        $sql = "SELECT mt.*, u.Name as buyer_name, u.ImageURL as buyer_avatar
                FROM marketplace_transactions mt
                INNER JOIN users u ON mt.buyer_id = u.Iduser
                WHERE mt.seller_id = ?
                ORDER BY mt.created_at DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $sales = [];
        while ($row = $result->fetch_assoc()) {
            $itemName = getItemName($conn, $row['item_type'], $row['item_id']);
            $row['item_name'] = $itemName;
            $sales[] = $row;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'sales' => $sales]);
        break;

    case 'add_to_wishlist':
        // Thêm vào wishlist
        $listingId = (int) ($_POST['listing_id'] ?? 0);

        if ($listingId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Listing ID không hợp lệ!']);
            exit;
        }

        $sql = "INSERT IGNORE INTO marketplace_wishlist (user_id, listing_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $listingId);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Đã thêm vào wishlist!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Đã có trong wishlist rồi!']);
        }
        $stmt->close();
        break;

    case 'remove_from_wishlist':
        // Xóa khỏi wishlist
        $listingId = (int) ($_POST['listing_id'] ?? 0);

        if ($listingId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Listing ID không hợp lệ!']);
            exit;
        }

        $sql = "DELETE FROM marketplace_wishlist WHERE user_id = ? AND listing_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $listingId);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Đã xóa khỏi wishlist!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa!']);
        }
        $stmt->close();
        break;

    case 'get_recommendations':
        // Lấy gợi ý mua kèm dựa trên lịch sử mua
        $itemType = $_GET['item_type'] ?? '';
        $itemId = (int) ($_GET['item_id'] ?? 0);

        if (!$itemType || !$itemId) {
            echo json_encode(['success' => false, 'message' => 'Thiếu thông tin item!']);
            exit;
        }

        // Lấy danh sách users đã mua item này
        $buyersSql = "SELECT DISTINCT buyer_id FROM marketplace_transactions 
                     WHERE item_type = ? AND item_id = ? AND buyer_id != ? 
                     LIMIT 20";
        $buyersStmt = $conn->prepare($buyersSql);
        $buyersStmt->bind_param("sii", $itemType, $itemId, $userId);
        $buyersStmt->execute();
        $buyersResult = $buyersStmt->get_result();
        $buyerIds = [];
        while ($row = $buyersResult->fetch_assoc()) {
            $buyerIds[] = $row['buyer_id'];
        }
        $buyersStmt->close();

        $recommendations = [];

        if (!empty($buyerIds)) {
            // Lấy items khác mà những users này đã mua
            $placeholders = implode(',', array_fill(0, count($buyerIds), '?'));
            $recSql = "SELECT mt.item_type, mt.item_id, COUNT(*) as purchase_count
                      FROM marketplace_transactions mt
                      WHERE mt.buyer_id IN ($placeholders)
                      AND (mt.item_type != ? OR mt.item_id != ?)
                      GROUP BY mt.item_type, mt.item_id
                      ORDER BY purchase_count DESC
                      LIMIT 5";
            $recStmt = $conn->prepare($recSql);
            $types = str_repeat('i', count($buyerIds)) . 'si';
            $params = array_merge($buyerIds, [$itemType, $itemId]);
            $recStmt->bind_param($types, ...$params);
            $recStmt->execute();
            $recResult = $recStmt->get_result();

            while ($row = $recResult->fetch_assoc()) {
                // Lấy listing active của item này
                $listingSql = "SELECT mi.*, u.Name as seller_name, u.ImageURL as seller_avatar
                              FROM marketplace_items mi
                              INNER JOIN users u ON mi.seller_id = u.Iduser
                              WHERE mi.item_type = ? AND mi.item_id = ? AND mi.status = 'active'
                              AND (mi.expires_at IS NULL OR mi.expires_at > NOW())
                              ORDER BY mi.price ASC
                              LIMIT 1";
                $listingStmt = $conn->prepare($listingSql);
                $listingStmt->bind_param("si", $row['item_type'], $row['item_id']);
                $listingStmt->execute();
                $listing = $listingStmt->get_result()->fetch_assoc();
                $listingStmt->close();

                if ($listing) {
                    $itemName = getItemName($conn, $row['item_type'], $row['item_id']);
                    $recommendations[] = [
                        'listing' => $listing,
                        'item_name' => $itemName,
                        'purchase_count' => $row['purchase_count']
                    ];
                }
            }
            $recStmt->close();
        }

        echo json_encode(['success' => true, 'recommendations' => $recommendations]);
        break;

    case 'get_wishlist':
        // Lấy wishlist
        $sql = "SELECT mi.*, u.Name as seller_name, u.ImageURL as seller_avatar
                FROM marketplace_wishlist mw
                INNER JOIN marketplace_items mi ON mw.listing_id = mi.id
                INNER JOIN users u ON mi.seller_id = u.Iduser
                WHERE mw.user_id = ? AND mi.status = 'active'
                ORDER BY mw.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $wishlist = [];
        while ($row = $result->fetch_assoc()) {
            $itemName = getItemName($conn, $row['item_type'], $row['item_id']);
            $row['item_name'] = $itemName;
            $wishlist[] = $row;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'wishlist' => $wishlist]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ!']);
        break;
}

/**
 * Lấy tên item
 */
function getItemName($conn, $itemType, $itemId)
{
    $tableMap = [
        'theme' => ['table' => 'themes', 'column' => 'theme_name'],
        'cursor' => ['table' => 'cursors', 'column' => 'cursor_name'],
        'chat_frame' => ['table' => 'chat_frames', 'column' => 'frame_name'],
        'avatar_frame' => ['table' => 'avatar_frames', 'column' => 'frame_name']
    ];

    if (!isset($tableMap[$itemType])) {
        return 'Unknown Item';
    }

    $config = $tableMap[$itemType];
    $sql = "SELECT {$config['column']} FROM {$config['table']} WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $name = $result->num_rows > 0 ? $result->fetch_assoc()[$config['column']] : 'Unknown Item';
    $stmt->close();

    return $name;
}

$conn->close();

