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
    case 'get_shop':
        $res = $conn->query("SELECT * FROM pets");
        $shop = [];
        while ($row = $res->fetch_assoc()) $shop[] = $row;
        
        $res = $conn->query("SELECT pet_id FROM user_pets WHERE user_id = $userId");
        $owned = [];
        while ($row = $res->fetch_row()) $owned[] = (int)$row[0];
        
        echo json_encode(['success' => true, 'shop' => $shop, 'owned' => $owned]);
        break;

    case 'buy_pet':
        $petId = (int)$_POST['pet_id'];
        
        // Check if already owned
        $check = $conn->prepare("SELECT id FROM user_pets WHERE user_id = ? AND pet_id = ?");
        $check->bind_param("ii", $userId, $petId);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Bạn đã sở hữu pet này rồi!']);
            exit;
        }
        
        // Get pet price
        $stmt = $conn->prepare("SELECT price, name FROM pets WHERE id = ?");
        $stmt->bind_param("i", $petId);
        $stmt->execute();
        $pet = $stmt->get_result()->fetch_assoc();
        
        if (!$pet) {
            echo json_encode(['success' => false, 'message' => 'Pet không tồn tại!']);
            exit;
        }
        
        // Check balance
        $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $userMoney = $stmt->get_result()->fetch_assoc()['Money'];
        
        if ($userMoney < $pet['price']) {
            echo json_encode(['success' => false, 'message' => 'Không đủ  Gtlm!']);
            exit;
        }
        
        // Transaction
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE users SET Money = Money - {$pet['price']} WHERE Iduser = $userId");
            $conn->query("INSERT INTO user_pets (user_id, pet_id, custom_name) VALUES ($userId, $petId, '{$pet['name']}')");
            $conn->commit();
            echo json_encode(['success' => true, 'message' => "Chúc mừng! Bạn đã nhận được {$pet['name']}!"]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi giao dịch!']);
        }
        break;

    case 'get_my_pets':
        $sql = "SELECT up.*, p.name as base_name, p.image_url, p.buff_type, p.buff_value 
                FROM user_pets up 
                JOIN pets p ON up.pet_id = p.id 
                WHERE up.user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $myPets = [];
        while ($row = $res->fetch_assoc()) $myPets[] = $row;
        echo json_encode(['success' => true, 'pets' => $myPets]);
        break;

    case 'activate_pet':
        $userPetId = (int)$_POST['user_pet_id'];
        $conn->query("UPDATE user_pets SET is_active = 0 WHERE user_id = $userId");
        $conn->query("UPDATE user_pets SET is_active = 1 WHERE id = $userPetId AND user_id = $userId");
        echo json_encode(['success' => true, 'message' => 'Đã triệu hồi pet!']);
        break;

    case 'rename_pet':
        $userPetId = (int)$_POST['user_pet_id'];
        $newName = trim($_POST['name']);
        if (strlen($newName) < 2 || strlen($newName) > 20) {
            echo json_encode(['success' => false, 'message' => 'Tên phải từ 2-20 ký tự!']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE user_pets SET custom_name = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sii", $newName, $userPetId, $userId);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Đã đổi tên pet!']);
        break;
}
?>
