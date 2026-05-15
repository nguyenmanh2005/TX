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

// Lấy thông tin Guild của User bằng Prepared Statement
$stmt = $conn->prepare("SELECT * FROM guild_members WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$stmt->close();

$guildId = $member['guild_id'] ?? 0;

switch ($action) {
    case 'get_guild_pro':
        if (!$guildId) {
            echo json_encode(['success' => false, 'message' => 'Bạn chưa tham gia Guild!']);
            exit;
        }

        // 1. Thông tin Guild
        $stmt = $conn->prepare("SELECT * FROM guilds WHERE id = ?");
        $stmt->bind_param("i", $guildId);
        $stmt->execute();
        $guild = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // 2. Lãnh địa (Sử dụng Prepared Statement)
        $stmt = $conn->prepare("SELECT * FROM guild_territories WHERE owner_guild_id = ?");
        $stmt->bind_param("i", $guildId);
        $stmt->execute();
        $territories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // 3. Buffs (Sử dụng Prepared Statement)
        $stmt = $conn->prepare("SELECT * FROM guild_active_buffs WHERE guild_id = ? AND expires_at > NOW()");
        $stmt->bind_param("i", $guildId);
        $stmt->execute();
        $activeBuffs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // 4. Shop Items
        $shopItems = $conn->query("SELECT * FROM guild_shop_items WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            'success' => true,
            'guild' => $guild,
            'member' => $member,
            'territories' => $territories,
            'active_buffs' => $activeBuffs,
            'shop_items' => $shopItems
        ]);
        break;

    case 'buy_shop_item':
        $itemId = (int)($_POST['item_id'] ?? 0);
        
        $conn->begin_transaction();
        try {
            // FIX RACE CONDITION: Sử dụng FOR UPDATE để khóa bản ghi member
            $stmt = $conn->prepare("SELECT * FROM guild_members WHERE user_id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $member = $stmt->get_result()->fetch_assoc();

            $stmt = $conn->prepare("SELECT * FROM guild_shop_items WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $itemId);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();

            if (!$item) throw new Exception("Vật phẩm không tồn tại!");
            if ($member['contribution_points'] < $item['price_contribution']) throw new Exception("Bạn không đủ điểm đóng góp!");
            if ($item['stock'] == 0) throw new Exception("Vật phẩm đã hết hàng!");

            // Trừ điểm đóng góp
            $stmt = $conn->prepare("UPDATE guild_members SET contribution_points = contribution_points - ? WHERE user_id = ?");
            $stmt->bind_param("ii", $item['price_contribution'], $userId);
            $stmt->execute();

            // Trao giải an toàn
            if ($item['item_type'] === 'avatar_frame') {
                $stmt = $conn->prepare("INSERT IGNORE INTO user_avatar_frames (user_id, avatar_frame_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $userId, $item['item_id']);
                $stmt->execute();
            }

            // Cập nhật kho
            if ($item['stock'] > 0) {
                $stmt = $conn->prepare("UPDATE guild_shop_items SET stock = stock - 1 WHERE id = ?");
                $stmt->bind_param("i", $itemId);
                $stmt->execute();
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Mua vật phẩm thành công!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'activate_buff':
        if ($member['role'] > 2) {
            echo json_encode(['success' => false, 'message' => 'Không có quyền!']);
            exit;
        }

        $buffType = $_POST['buff_type'] ?? '';
        // FIX: White-list validation cho buff_type
        $allowedBuffs = ['exp_bonus', 'win_bonus', 'tax_reduction'];
        if (!in_array($buffType, $allowedBuffs)) {
            echo json_encode(['success' => false, 'message' => 'Loại Buff không hợp lệ!']);
            exit;
        }

        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $stmt = $conn->prepare("INSERT INTO guild_active_buffs (guild_id, buff_type, buff_value, expires_at) VALUES (?, ?, 5.0, ?)");
        $stmt->bind_param("iss", $guildId, $buffType, $expiresAt);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Kích hoạt Buff thành công!']);
        break;
}
?>
