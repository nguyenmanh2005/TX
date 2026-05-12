<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập!']);
    exit();
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? '';

if ($action === 'craft') {
    $recipeId = (int)($_POST['recipe_id'] ?? 0);
    
    // 1. Lấy thông tin công thức
    $stmt = $conn->prepare("SELECT * FROM crafting_recipes WHERE id = ?");
    $stmt->bind_param("i", $recipeId);
    $stmt->execute();
    $recipe = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$recipe) {
        echo json_encode(['status' => 'error', 'message' => 'Công thức không tồn tại!']);
        exit();
    }

    $reqs = json_decode($recipe['input_requirements'], true);
    $gtlmCost = $recipe['gtlm_cost'];
    $outType = $recipe['output_type'];
    $outId = $recipe['output_item_id'];

    // 1.1 Kiểm tra xem đã sở hữu item đầu ra chưa
    $outTable = ''; $outCol = '';
    if ($outType === 'theme') { $outTable = 'user_themes'; $outCol = 'theme_id'; }
    elseif ($outType === 'cursor') { $outTable = 'user_cursors'; $outCol = 'cursor_id'; }
    elseif ($outType === 'avatar_frame') { $outTable = 'user_avatar_frames'; $outCol = 'avatar_frame_id'; }
    elseif ($outType === 'chat_frame') { $outTable = 'user_chat_frames'; $outCol = 'chat_frame_id'; }

    if ($outTable) {
        $stmt = $conn->prepare("SELECT id FROM $outTable WHERE user_id = ? AND $outCol = ?");
        $stmt->bind_param("ii", $userId, $outId);
        $stmt->execute();
        $alreadyHas = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if ($alreadyHas) {
            echo json_encode(['status' => 'error', 'message' => 'Bạn đã sở hữu vật phẩm này rồi!']);
            exit();
        }
    }

    $conn->begin_transaction();
    try {
        // 2. Kiểm tra tiền
        $user = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc();
        if ($user['Money'] < $gtlmCost) {
            throw new Exception("Bạn không đủ GTLM để thực hiện chế tác!");
        }

        // 3. Kiểm tra và tiêu hao nguyên liệu
        foreach ($reqs as $type => $amt) {
            $tableName = '';
            $idCol = '';
            
            if ($type === 'theme') { $tableName = 'user_themes'; $idCol = 'theme_id'; }
            elseif ($type === 'cursor') { $tableName = 'user_cursors'; $idCol = 'cursor_id'; }
            elseif ($type === 'avatar_frame') { $tableName = 'user_avatar_frames'; $idCol = 'avatar_frame_id'; }
            elseif ($type === 'chat_frame') { $tableName = 'user_chat_frames'; $idCol = 'chat_frame_id'; }
            
            // Tìm các item nhàn rỗi (không active) để tiêu hao
            $sql = "SELECT id FROM $tableName WHERE user_id = ? ";
            if ($type !== 'avatar_frame') $sql .= " AND is_active = 0 ";
            $sql .= " LIMIT $amt";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $itemsToConsume = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (count($itemsToConsume) < $amt) {
                throw new Exception("Không đủ nguyên liệu $type!");
            }

            // Xóa nguyên liệu
            foreach ($itemsToConsume as $item) {
                $conn->query("DELETE FROM $tableName WHERE id = {$item['id']}");
            }
        }

        // 4. Trừ tiền
        $conn->query("UPDATE users SET Money = Money - $gtlmCost WHERE Iduser = $userId");

        // 5. Tính toán tỉ lệ thành công
        $roll = rand(1, 100);
        $isSuccess = ($roll <= $recipe['success_rate']);

        if ($isSuccess) {
            // Cấp item mới
            $type = $recipe['output_type'];
            $outputId = $recipe['output_item_id'];
            $tableName = '';
            $idCol = '';

            if ($type === 'theme') { $tableName = 'user_themes'; $idCol = 'theme_id'; }
            elseif ($type === 'cursor') { $tableName = 'user_cursors'; $idCol = 'cursor_id'; }
            elseif ($type === 'avatar_frame') { $tableName = 'user_avatar_frames'; $idCol = 'avatar_frame_id'; }
            elseif ($type === 'chat_frame') { $tableName = 'user_chat_frames'; $idCol = 'chat_frame_id'; }

            // Kiểm tra xem đã có item này chưa
            $stmt = $conn->prepare("SELECT id FROM $tableName WHERE user_id = ? AND $idCol = ?");
            $stmt->bind_param("ii", $userId, $outputId);
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if (!$exists) {
                $conn->query("INSERT INTO $tableName (user_id, $idCol) VALUES ($userId, $outputId)");
            }

            $conn->query("INSERT INTO crafting_logs (user_id, recipe_id, is_success, gtlm_spent) VALUES ($userId, $recipeId, 1, $gtlmCost)");
            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => "Rèn thành công! Bạn đã nhận được vật phẩm mới."]);
        } else {
            // Thất bại
            $conn->query("INSERT INTO crafting_logs (user_id, recipe_id, is_success, gtlm_spent) VALUES ($userId, $recipeId, 0, $gtlmCost)");
            $conn->commit();
            echo json_encode(['status' => 'failure', 'message' => "Rèn thất bại! Nguyên liệu đã bị tan chảy trong lò lửa."]);
        }

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ!']);
}
?>
