<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

require_once 'db_connect.php';

$userId = $_SESSION['Iduser'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_all':
        $stmt = $conn->prepare("SELECT * FROM pets ORDER BY price ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        $pets = [];
        while ($row = $result->fetch_assoc()) {
            $pets[] = $row;
        }
        echo json_encode(['success' => true, 'pets' => $pets]);
        break;

    case 'get_my_pets':
        $stmt = $conn->prepare("SELECT p.*, up.is_active FROM user_pets up JOIN pets p ON up.pet_id = p.id WHERE up.user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $my_pets = [];
        $active_pet = null;
        while ($row = $result->fetch_assoc()) {
            $my_pets[] = $row;
            if ($row['is_active'] == 1) {
                $active_pet = $row;
            }
        }
        echo json_encode(['success' => true, 'my_pets' => $my_pets, 'active_pet' => $active_pet]);
        break;

    case 'buy':
        $petId = isset($_POST['pet_id']) ? intval($_POST['pet_id']) : 0;
        if ($petId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Pet không hợp lệ']);
            exit;
        }

        $conn->begin_transaction();
        try {
            // Check if user already owns this pet
            $stmt = $conn->prepare("SELECT id FROM user_pets WHERE user_id = ? AND pet_id = ? FOR UPDATE");
            $stmt->bind_param("ii", $userId, $petId);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception('Bạn đã sở hữu thú cưng này rồi!');
            }

            // Get pet price
            $stmt = $conn->prepare("SELECT price, name FROM pets WHERE id = ?");
            $stmt->bind_param("i", $petId);
            $stmt->execute();
            $petRes = $stmt->get_result();
            if ($petRes->num_rows === 0) {
                throw new Exception('Thú cưng không tồn tại');
            }
            $pet = $petRes->fetch_assoc();
            $price = floatval($pet['price']);

            // Get user money
            $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if ($user['Money'] < $price) {
                throw new Exception('Không đủ GTLM để mua!');
            }

            // Deduct money
            $stmt = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
            $stmt->bind_param("di", $price, $userId);
            $stmt->execute();

            // Add pet to user
            $stmt = $conn->prepare("INSERT INTO user_pets (user_id, pet_id, is_active) VALUES (?, ?, 0)");
            $stmt->bind_param("ii", $userId, $petId);
            $stmt->execute();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Mua ' . $pet['name'] . ' thành công!', 'new_money' => $user['Money'] - $price]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'equip':
        $petId = isset($_POST['pet_id']) ? intval($_POST['pet_id']) : 0;
        if ($petId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Pet không hợp lệ']);
            exit;
        }

        $conn->begin_transaction();
        try {
            // Check ownership
            $stmt = $conn->prepare("SELECT id FROM user_pets WHERE user_id = ? AND pet_id = ? FOR UPDATE");
            $stmt->bind_param("ii", $userId, $petId);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                throw new Exception('Bạn chưa sở hữu thú cưng này!');
            }

            // Unequip all
            $stmt = $conn->prepare("UPDATE user_pets SET is_active = 0 WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();

            // Equip the new one
            $stmt = $conn->prepare("UPDATE user_pets SET is_active = 1 WHERE user_id = ? AND pet_id = ?");
            $stmt->bind_param("ii", $userId, $petId);
            $stmt->execute();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Đã trang bị thú cưng!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'unequip':
        $stmt = $conn->prepare("UPDATE user_pets SET is_active = 0 WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Đã tháo thú cưng!']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
}
