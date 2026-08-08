<?php
/**
 * API Biệt Thự Hoàng Gia & Phòng Trưng Bày GTLM (Ý tưởng 3)
 * [NEW FILE] - Hoạt động độc lập 100%, kết nối hiển thị cúp chiến thắng từ Tháp Thần Bài
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php';

$action = $_GET['action'] ?? 'view';
$userId = isset($_SESSION['Iduser']) ? (int)$_SESSION['Iduser'] : 1;
$username = isset($_SESSION['Name']) ? $_SESSION['Name'] : 'Chủ Biệt Thự GTLM';
$avatar = isset($_SESSION['Avatar']) ? $_SESSION['Avatar'] : 'img/avatar_default.png';

// Danh mục cửa hàng nội thất luxury
function getFurnitureCatalog() {
    return [
        ['code' => 'furn_baccarat_table', 'name' => '🃏 Bàn Baccarat Đỏ Luxury', 'icon' => '🃏', 'price' => 50000, 'type' => 'furniture'],
        ['code' => 'furn_golden_sofa', 'name' => '🛋️ Ghế Sofa Bọc Vàng Hoàng Gia', 'icon' => '🛋️', 'price' => 75000, 'type' => 'furniture'],
        ['code' => 'pet_gold_dragon', 'name' => '🐉 Tiểu Long Rồng Vàng Tài Lộc', 'icon' => '🐉', 'price' => 150000, 'type' => 'pet'],
        ['code' => 'pet_lucky_cat', 'name' => '🐱 Mèo Thần Tài Chiêu Lộc', 'icon' => '🐱', 'price' => 30000, 'type' => 'pet'],
        ['code' => 'statue_golden_chip', 'name' => '🪙 Tượng Chip Khổng Lồ 1 Tỷ GTLM', 'icon' => '🪙', 'price' => 200000, 'type' => 'statue'],
        ['code' => 'furn_plinko_machine', 'name' => '🎰 Máy Vận May Plinko Độc Quyền', 'icon' => '🎰', 'price' => 120000, 'type' => 'furniture']
    ];
}

// Khởi tạo phòng biệt thự mặc định nếu chưa có
function getRoom($conn, $targetId) {
    $stmt = $conn->prepare("SELECT * FROM lounge_rooms WHERE user_id = ?");
    $stmt->bind_param("i", $targetId);
    $stmt->execute();
    $res = $stmt->get_result();
    $room = $res->fetch_assoc();
    $stmt->close();

    if (!$room) {
        // Lấy thông tin từ bảng users nếu có
        $uName = "Đạo Hữu #{$targetId}";
        $uAvt = "img/avatar_default.png";
        $stmtU = $conn->prepare("SELECT Name, ImageURL FROM users WHERE Iduser = ?");
        if ($stmtU) {
            $stmtU->bind_param("i", $targetId);
            $stmtU->execute();
            $resU = $stmtU->get_result()->fetch_assoc();
            if ($resU) {
                $uName = $resU['Name'];
                $uAvt = !empty($resU['ImageURL']) ? $resU['ImageURL'] : "img/avatar_default.png";
            }
            $stmtU->close();
        }

        $stmtIns = $conn->prepare("INSERT INTO lounge_rooms (user_id, username, avatar, room_name, theme_color, wallpaper_id, likes_count, visits_count) VALUES (?, ?, ?, ?, 'gold-luxury', 'bg_royal_velvet', 0, 0)");
        $rName = "Biệt Thự Hoàng Gia của " . $uName;
        $stmtIns->bind_param("isss", $targetId, $uName, $uAvt, $rName);
        $stmtIns->execute();
        $stmtIns->close();

        // Tặng sẵn 1 vật phẩm trang trí ban đầu
        $stmtInsItem = $conn->prepare("INSERT INTO lounge_items (user_id, item_code, item_name, item_type, icon_url, grid_x, grid_y, is_placed, acquired_from) VALUES (?, 'statue_starter', '🌟 Tượng Vàng Gia Nhập GTLM', 'statue', '🌟', 2, 2, 1, 'system')");
        $stmtInsItem->bind_param("i", $targetId);
        $stmtInsItem->execute();
        $stmtInsItem->close();

        return [
            'user_id' => $targetId, 'username' => $uName, 'avatar' => $uAvt,
            'room_name' => $rName, 'theme_color' => 'gold-luxury', 'wallpaper_id' => 'bg_royal_velvet',
            'likes_count' => 0, 'visits_count' => 0
        ];
    }
    return $room;
}

if ($action === 'view') {
    $targetId = intval($_GET['target_id'] ?? $userId);
    if ($targetId <= 0) $targetId = $userId;

    // Tăng lượt ghé thăm nếu không phải chủ phòng
    if ($targetId !== $userId) {
        $conn->query("UPDATE lounge_rooms SET visits_count = visits_count + 1 WHERE user_id = {$targetId}");
    }

    $room = getRoom($conn, $targetId);

    // Lấy toàn bộ vật phẩm trang trí trong phòng và trong kho
    $stmtItems = $conn->prepare("SELECT * FROM lounge_items WHERE user_id = ? ORDER BY id DESC");
    $stmtItems->bind_param("i", $targetId);
    $stmtItems->execute();
    $resItems = $stmtItems->get_result();
    $placedItems = [];
    $inventory = [];
    while ($row = $resItems->fetch_assoc()) {
        if ($row['is_placed'] == 1) $placedItems[] = $row;
        else $inventory[] = $row;
    }
    $stmtItems->close();

    // Lấy lời chúc Sổ Lưu Niệm
    $stmtGB = $conn->prepare("SELECT * FROM lounge_guestbook WHERE owner_id = ? ORDER BY id DESC LIMIT 10");
    $stmtGB->bind_param("i", $targetId);
    $stmtGB->execute();
    $resGB = $stmtGB->get_result();
    $guestbook = [];
    while ($row = $resGB->fetch_assoc()) $guestbook[] = $row;
    $stmtGB->close();

    echo json_encode([
        'success' => true,
        'room' => $room,
        'placed_items' => $placedItems,
        'inventory' => $inventory,
        'guestbook' => $guestbook,
        'is_owner' => ($targetId === $userId),
        'catalog' => getFurnitureCatalog()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'buy') {
    $code = $_POST['item_code'] ?? '';
    $catalog = getFurnitureCatalog();
    $selected = null;
    foreach ($catalog as $c) {
        if ($c['code'] === $code) { $selected = $c; break; }
    }
    if (!$selected) {
        echo json_encode(['success' => false, 'message' => 'Vật phẩm không tồn tại trong cửa hàng!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cost = floatval($selected['price']);
    $conn->begin_transaction();
    try {
        // Kiểm tra số dư GTLM
        $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmtLock->bind_param("i", $userId);
        $stmtLock->execute();
        $uRow = $stmtLock->get_result()->fetch_assoc();
        $stmtLock->close();

        if (!$uRow || floatval($uRow['Money']) < $cost) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Số dư GTLM của bạn không đủ! Hãy cày thêm tại Tháp Thần Bài hoặc Minigames.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Trừ GTLM
        $stmtSub = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
        $stmtSub->bind_param("di", $cost, $userId);
        $stmtSub->execute();
        $stmtSub->close();

        // Thêm vào kho trang trí (is_placed = 0)
        $stmtIns = $conn->prepare("INSERT INTO lounge_items (user_id, item_code, item_name, item_type, icon_url, grid_x, grid_y, is_placed, acquired_from) VALUES (?, ?, ?, ?, ?, 1, 1, 0, 'shop')");
        $stmtIns->bind_param("issss", $userId, $selected['code'], $selected['name'], $selected['type'], $selected['icon']);
        $stmtIns->execute();
        $stmtIns->close();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Mua thành công {$selected['name']}! Hãy vào kho bấm 'Trưng bày' để xếp vào phòng."], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Lỗi giao dịch mua vật phẩm: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($action === 'place') {
    $itemId = intval($_POST['item_id'] ?? 0);
    $isPlaced = intval($_POST['is_placed'] ?? 1);
    $stmt = $conn->prepare("UPDATE lounge_items SET is_placed = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("iii", $isPlaced, $itemId, $userId);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => $isPlaced ? 'Đã trưng bày vào phòng!' : 'Đã cất vào kho!'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'guestbook') {
    $ownerId = intval($_POST['owner_id'] ?? $userId);
    $comment = trim($_POST['comment'] ?? '');
    if (empty($comment)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập lời chúc Sổ Lưu Niệm!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO lounge_guestbook (owner_id, visitor_id, visitor_name, visitor_avatar, comment, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisss", $ownerId, $userId, $username, $avatar, $comment);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Đã gửi lời chúc Sổ Lưu Niệm thành công!'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'like') {
    $targetId = intval($_POST['target_id'] ?? $userId);
    $conn->query("UPDATE lounge_rooms SET likes_count = likes_count + 1 WHERE user_id = {$targetId}");
    echo json_encode(['success' => true, 'message' => 'Đã thả tim chúc phúc cho Biệt Thự! ❤️'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'list_rooms') {
    // Nếu chưa có đủ 10 phòng trong lounge_rooms, khởi tạo thật vào CSDL cho top người chơi từ bảng users với số tim = 0, lượt thăm = 0
    $resCount = $conn->query("SELECT COUNT(*) as cnt FROM lounge_rooms");
    $cntRow = $resCount ? $resCount->fetch_assoc() : ['cnt' => 0];
    if (intval($cntRow['cnt']) < 10) {
        $resU = $conn->query("SELECT Iduser, Name, ImageURL FROM users WHERE Iduser NOT IN (SELECT user_id FROM lounge_rooms) ORDER BY Money DESC LIMIT 15");
        if ($resU) {
            $stmtInit = $conn->prepare("INSERT IGNORE INTO lounge_rooms (user_id, username, avatar, room_name, theme_color, wallpaper_id, likes_count, visits_count) VALUES (?, ?, ?, ?, 'gold-luxury', 'bg_royal_velvet', 0, 0)");
            while ($rowU = $resU->fetch_assoc()) {
                $uid = (int)$rowU['Iduser'];
                $uname = $rowU['Name'];
                $uavt = !empty($rowU['ImageURL']) ? $rowU['ImageURL'] : 'img/avatar_default.png';
                $rname = 'Biệt Thự Hoàng Gia của ' . $uname;
                $stmtInit->bind_param("isss", $uid, $uname, $uavt, $rname);
                $stmtInit->execute();
            }
            if ($stmtInit) $stmtInit->close();
        }
    }

    // Lấy top phòng từ CSDL (số liệu thật 100%)
    $stmt = $conn->prepare("SELECT r.*, u.Name as u_name, u.ImageURL as u_avt FROM lounge_rooms r LEFT JOIN users u ON r.user_id = u.Iduser ORDER BY r.likes_count DESC, r.visits_count DESC LIMIT 30");
    $stmt->execute();
    $res = $stmt->get_result();
    $rooms = [];
    while ($row = $res->fetch_assoc()) {
        $rooms[] = [
            'user_id' => (int)$row['user_id'],
            'username' => !empty($row['u_name']) ? $row['u_name'] : $row['username'],
            'avatar' => !empty($row['u_avt']) ? $row['u_avt'] : ($row['avatar'] ?: 'img/avatar_default.png'),
            'room_name' => $row['room_name'],
            'likes_count' => (int)$row['likes_count'],
            'visits_count' => (int)$row['visits_count']
        ];
    }
    $stmt->close();

    echo json_encode(['success' => true, 'rooms' => $rooms], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action không hợp lệ'], JSON_UNESCAPED_UNICODE);
exit;
?>
