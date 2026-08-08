<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['Iduser'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Check active pet cho farm_time
$stmtPet = $conn->prepare("SELECT p.buff_type, p.buff_value FROM user_pets up JOIN pets p ON up.pet_id = p.id WHERE up.user_id = ? AND up.is_active = 1");
$stmtPet->bind_param("i", $userId);
$stmtPet->execute();
$activePet = $stmtPet->get_result()->fetch_assoc();
$stmtPet->close();

$farmTimeBuff = 1.0;
if ($activePet && $activePet['buff_type'] === 'farm_time') {
    $farmTimeBuff = (float)$activePet['buff_value'];
}

// Cấu hình hạt giống
$seedConfigs = [
    'WHEAT' => ['price' => 200, 'time' => 60],      // 1 phút
    'CORN' => ['price' => 500, 'time' => 180],      // 3 phút
    'TOMATO' => ['price' => 1500, 'time' => 300],   // 5 phút
    'APPLE' => ['price' => 4000, 'time' => 600],    // 10 phút
    'WATERMELON' => ['price' => 15000, 'time' => 1800], // 30 phút
    'STRAWBERRY' => ['price' => 30000, 'time' => 3600], // 1 tiếng
    'GRAPE' => ['price' => 70000, 'time' => 7200],      // 2 tiếng
    'PEACH' => ['price' => 150000, 'time' => 14400],    // 4 tiếng
    'CHERRY' => ['price' => 8000, 'time' => 900],       // 15 phút
    'LEMON' => ['price' => 20000, 'time' => 2700],      // 45 phút
    'BANANA' => ['price' => 45000, 'time' => 5400],     // 1.5 tiếng
    'KIWI' => ['price' => 90000, 'time' => 9000],       // 2.5 tiếng
    'MANGO' => ['price' => 120000, 'time' => 12600],    // 3.5 tiếng
    'PINEAPPLE' => ['price' => 200000, 'time' => 18000], // 5 tiếng
    'COCONUT' => ['price' => 350000, 'time' => 28800],  // 8 tiếng
    'MELON' => ['price' => 600000, 'time' => 43200],    // 12 tiếng
    'ORANGE' => ['price' => 450000, 'time' => 36000],   // 10 tiếng
    'AVOCADO' => ['price' => 800000, 'time' => 57600],  // 16 tiếng
    'PEAR' => ['price' => 1200000, 'time' => 72000],    // 20 tiếng
    'POMEGRANATE' => ['price' => 2000000, 'time' => 86400] // 24 tiếng
];
$fertilizerPrice = 500;

switch ($action) {
    case 'get_farm':
        // Lấy thông tin user
        $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $userMoney = $stmt->get_result()->fetch_assoc()['Money'];

        // Lấy thông tin túi đồ farm
        $stmt = $conn->prepare("SELECT seed_wheat, seed_corn, seed_tomato, seed_apple, seed_watermelon, seed_strawberry, seed_grape, seed_peach, seed_cherry, seed_lemon, seed_banana, seed_kiwi, seed_mango, seed_pineapple, seed_coconut, seed_melon, seed_orange, seed_avocado, seed_pear, seed_pomegranate, crop_wheat, crop_corn, crop_tomato, crop_apple, crop_watermelon, crop_strawberry, crop_grape, crop_peach, crop_cherry, crop_lemon, crop_banana, crop_kiwi, crop_mango, crop_pineapple, crop_coconut, crop_melon, crop_orange, crop_avocado, crop_pear, crop_pomegranate, fertilizer FROM user_commodities WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $inventory = $stmt->get_result()->fetch_assoc();
        
        if (!$inventory) {
            $conn->query("INSERT IGNORE INTO user_commodities (user_id) VALUES ($userId)");
            $inventory = ['seed_wheat'=>0, 'seed_corn'=>0, 'seed_tomato'=>0, 'seed_apple'=>0, 'seed_watermelon'=>0, 'seed_strawberry'=>0, 'seed_grape'=>0, 'seed_peach'=>0, 'seed_cherry'=>0, 'seed_lemon'=>0, 'seed_banana'=>0, 'seed_kiwi'=>0, 'seed_mango'=>0, 'seed_pineapple'=>0, 'seed_coconut'=>0, 'seed_melon'=>0, 'seed_orange'=>0, 'seed_avocado'=>0, 'seed_pear'=>0, 'seed_pomegranate'=>0, 'crop_wheat'=>0, 'crop_corn'=>0, 'crop_tomato'=>0, 'crop_apple'=>0, 'crop_watermelon'=>0, 'crop_strawberry'=>0, 'crop_grape'=>0, 'crop_peach'=>0, 'crop_cherry'=>0, 'crop_lemon'=>0, 'crop_banana'=>0, 'crop_kiwi'=>0, 'crop_mango'=>0, 'crop_pineapple'=>0, 'crop_coconut'=>0, 'crop_melon'=>0, 'crop_orange'=>0, 'crop_avocado'=>0, 'crop_pear'=>0, 'crop_pomegranate'=>0, 'fertilizer'=>0];
        }

        // Lấy thông tin 9 ô đất
        $plots = array_fill(0, 9, null);
        $stmt = $conn->prepare("SELECT * FROM user_farm_plots WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $plots[$row['plot_index']] = $row;
        }

        echo json_encode([
            'success' => true,
            'money' => $userMoney,
            'inventory' => $inventory,
            'plots' => $plots,
            'now' => date('Y-m-d H:i:s')
        ]);
        break;

    case 'buy_item':
        $item = $_POST['item'] ?? '';
        $amount = (int)($_POST['amount'] ?? 1);
        
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Số lượng không hợp lệ']);
            exit;
        }

        $price = 0;
        $dbCol = '';
        if (isset($seedConfigs[$item])) {
            $price = $seedConfigs[$item]['price'];
            $dbCol = 'seed_' . strtolower($item);
        } else if ($item === 'FERTILIZER') {
            $price = $fertilizerPrice;
            $dbCol = 'fertilizer';
        } else {
            echo json_encode(['success' => false, 'message' => 'Vật phẩm không tồn tại']);
            exit;
        }

        $totalCost = $price * $amount;

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $userMoney = $stmt->get_result()->fetch_assoc()['Money'];

            if ($userMoney < $totalCost) {
                throw new Exception("Không đủ GTLM! Cần " . number_format($totalCost));
            }

            // Trừ GTLM
            $stmt = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
            $stmt->bind_param("di", $totalCost, $userId);
            $stmt->execute();

            // Cộng đồ
            $stmt = $conn->prepare("UPDATE user_commodities SET $dbCol = $dbCol + ? WHERE user_id = ?");
            $stmt->bind_param("ii", $amount, $userId);
            $stmt->execute();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Mua thành công!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'plant':
        $plotIndex = (int)($_POST['plot_index'] ?? -1);
        $seedCode = $_POST['seed_code'] ?? '';

        if ($plotIndex < 0 || $plotIndex > 8 || !isset($seedConfigs[$seedCode])) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit;
        }

        $dbCol = 'seed_' . strtolower($seedCode);

        $conn->begin_transaction();
        try {
            // Check seed count
            $stmt = $conn->prepare("SELECT $dbCol FROM user_commodities WHERE user_id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $inv = $stmt->get_result()->fetch_assoc();

            if (!$inv || $inv[$dbCol] < 1) {
                throw new Exception("Bạn không có hạt giống này!");
            }

            // Check plot
            $stmt = $conn->prepare("SELECT id, seed_code FROM user_farm_plots WHERE user_id = ? AND plot_index = ? FOR UPDATE");
            $stmt->bind_param("ii", $userId, $plotIndex);
            $stmt->execute();
            $plot = $stmt->get_result()->fetch_assoc();

            if ($plot && $plot['seed_code']) {
                throw new Exception("Ô đất này đã được trồng cây rồi!");
            }

            // Deduct seed
            $stmt = $conn->prepare("UPDATE user_commodities SET $dbCol = $dbCol - 1 WHERE user_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();

            // Plant
            $growTime = $seedConfigs[$seedCode]['time'] * $farmTimeBuff;
            if ($plot) {
                $stmt = $conn->prepare("UPDATE user_farm_plots SET seed_code = ?, planted_at = NOW(), harvest_time = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?");
                $stmt->bind_param("sii", $seedCode, $growTime, $plot['id']);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("INSERT INTO user_farm_plots (user_id, plot_index, seed_code, planted_at, harvest_time) VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))");
                $stmt->bind_param("iisi", $userId, $plotIndex, $seedCode, $growTime);
                $stmt->execute();
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Đã gieo hạt!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'harvest':
        $plotIndex = (int)($_POST['plot_index'] ?? -1);
        
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT id, seed_code, harvest_time FROM user_farm_plots WHERE user_id = ? AND plot_index = ? FOR UPDATE");
            $stmt->bind_param("ii", $userId, $plotIndex);
            $stmt->execute();
            $plot = $stmt->get_result()->fetch_assoc();

            if (!$plot || !$plot['seed_code']) {
                throw new Exception("Ô đất này trống!");
            }

            if (strtotime($plot['harvest_time']) > time()) {
                throw new Exception("Cây chưa chín, không thể thu hoạch!");
            }

            $seedCode = $plot['seed_code'];
            $dbCol = 'crop_' . strtolower($seedCode);

            // Xóa cây trên đất
            $stmt = $conn->prepare("UPDATE user_farm_plots SET seed_code = NULL, planted_at = NULL, harvest_time = NULL WHERE id = ?");
            $stmt->bind_param("i", $plot['id']);
            $stmt->execute();

            // Cộng nông sản ngẫu nhiên từ 1 đến 3
            $yield = rand(1, 3);
            $stmt = $conn->prepare("UPDATE user_commodities SET $dbCol = $dbCol + ? WHERE user_id = ?");
            $stmt->bind_param("ii", $yield, $userId);
            $stmt->execute();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => "Thu hoạch thành công +$yield nông sản!"]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
        
    case 'fertilize':
        $plotIndex = (int)($_POST['plot_index'] ?? -1);
        
        $mode = $_POST['mode'] ?? 'single';
        
        $conn->begin_transaction();
        try {
            // Lấy thông tin ô đất trước
            $stmt = $conn->prepare("SELECT id, seed_code, planted_at, harvest_time FROM user_farm_plots WHERE user_id = ? AND plot_index = ? FOR UPDATE");
            $stmt->bind_param("ii", $userId, $plotIndex);
            $stmt->execute();
            $plot = $stmt->get_result()->fetch_assoc();

            if (!$plot || !$plot['seed_code']) {
                throw new Exception("Ô đất này trống!");
            }
            $timeLeft = strtotime($plot['harvest_time']) - time();
            if ($timeLeft <= 0) {
                throw new Exception("Cây đã chín rồi, không cần bón phân!");
            }

            $growTime = $seedConfigs[$plot['seed_code']]['time'] * $farmTimeBuff;
            $reducePerFert = max(1, floor($growTime / 2));
            
            $fertNeeded = 1;
            if ($mode === 'max') {
                $fertNeeded = ceil($timeLeft / $reducePerFert);
            }

            // Lấy thông tin user (GTLM) và túi đồ (phân bón)
            $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $userMoney = $stmt->get_result()->fetch_assoc()['Money'];

            $stmt = $conn->prepare("SELECT fertilizer FROM user_commodities WHERE user_id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $inv = $stmt->get_result()->fetch_assoc();

            $currentFert = $inv ? $inv['fertilizer'] : 0;
            $missingFert = max(0, $fertNeeded - $currentFert);
            $costToBuyMissing = $missingFert * $fertilizerPrice;

            if ($missingFert > 0) {
                if ($userMoney < $costToBuyMissing) {
                    throw new Exception("Bạn cần $fertNeeded bao phân bón nhưng chỉ có $currentFert. Cần thêm " . number_format($costToBuyMissing) . " GTLM để mua phần thiếu!");
                }
                // Trừ GTLM mua phân bón
                $stmt = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
                $stmt->bind_param("di", $costToBuyMissing, $userId);
                $stmt->execute();
            }

            // Trừ phân bón trong kho (tối đa là số lượng đang có)
            $fertToDeduct = min($currentFert, $fertNeeded);
            if ($fertToDeduct > 0) {
                $stmt = $conn->prepare("UPDATE user_commodities SET fertilizer = fertilizer - ? WHERE user_id = ?");
                $stmt->bind_param("ii", $fertToDeduct, $userId);
                $stmt->execute();
            }

            // Cập nhật lại harvest_time
            $reduceSeconds = $reducePerFert * $fertNeeded;
            $stmt = $conn->prepare("UPDATE user_farm_plots SET harvest_time = DATE_SUB(harvest_time, INTERVAL ? SECOND) WHERE id = ?");
            $stmt->bind_param("ii", $reduceSeconds, $plot['id']);
            $stmt->execute();

            $conn->commit();
            
            $msg = "Đã bón $fertNeeded bao phân!";
            if ($missingFert > 0) {
                $msg .= " (Hệ thống tự mua $missingFert bao thiếu giá " . number_format($costToBuyMissing) . " GTLM)";
            }
            if ($mode === 'max' || ($timeLeft - $reduceSeconds) <= 0) {
                $msg .= " Cây đã chín!";
            }

            echo json_encode(['success' => true, 'message' => $msg]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'harvest_all':
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT id, seed_code, harvest_time FROM user_farm_plots WHERE user_id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            
            $harvested = 0;
            $yields = [];

            while ($plot = $res->fetch_assoc()) {
                if ($plot['seed_code'] && strtotime($plot['harvest_time']) <= time()) {
                    $seedCode = $plot['seed_code'];
                    $dbCol = 'crop_' . strtolower($seedCode);
                    
                    $stmtDel = $conn->prepare("UPDATE user_farm_plots SET seed_code = NULL, planted_at = NULL, harvest_time = NULL WHERE id = ?");
                    $stmtDel->bind_param("i", $plot['id']);
                    $stmtDel->execute();
                    
                    $yield = rand(1, 3);
                    if (!isset($yields[$dbCol])) $yields[$dbCol] = 0;
                    $yields[$dbCol] += $yield;
                    $harvested++;
                }
            }

            if ($harvested == 0) {
                throw new Exception("Không có cây nào để thu hoạch!");
            }

            foreach ($yields as $col => $amt) {
                $stmtAdd = $conn->prepare("UPDATE user_commodities SET $col = $col + ? WHERE user_id = ?");
                $stmtAdd->bind_param("ii", $amt, $userId);
                $stmtAdd->execute();
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => "Thu hoạch nhanh $harvested cây thành công!", 'harvested' => $harvested]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'plant_all':
        $seedCode = $_POST['seed_code'] ?? '';
        $limit = (int)($_POST['limit'] ?? 9);
        if ($limit <= 0 || !isset($seedConfigs[$seedCode])) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit;
        }

        $dbCol = 'seed_' . strtolower($seedCode);
        $growTime = $seedConfigs[$seedCode]['time'] * $farmTimeBuff;

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT $dbCol FROM user_commodities WHERE user_id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $inv = $stmt->get_result()->fetch_assoc();

            if (!$inv || $inv[$dbCol] < 1) {
                throw new Exception("Hết hạt giống!");
            }
            $availableSeeds = min($inv[$dbCol], $limit);

            $stmt = $conn->prepare("SELECT id, plot_index, seed_code FROM user_farm_plots WHERE user_id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            
            $emptyPlots = [];
            $occupiedIndices = [];
            while ($plot = $res->fetch_assoc()) {
                if (!$plot['seed_code']) {
                    $emptyPlots[] = $plot;
                } else {
                    $occupiedIndices[] = $plot['plot_index'];
                }
            }
            
            for ($i = 0; $i < 9; $i++) {
                if (!in_array($i, $occupiedIndices) && !in_array($i, array_column($emptyPlots, 'plot_index'))) {
                    $emptyPlots[] = ['id' => null, 'plot_index' => $i];
                }
            }

            $toPlant = min(count($emptyPlots), $availableSeeds);
            if ($toPlant == 0) {
                throw new Exception("Đất đã đầy hoặc không đủ hạt!");
            }

            $stmt = $conn->prepare("UPDATE user_commodities SET $dbCol = $dbCol - ? WHERE user_id = ?");
            $stmt->bind_param("ii", $toPlant, $userId);
            $stmt->execute();

            for ($i = 0; $i < $toPlant; $i++) {
                $p = $emptyPlots[$i];
                if ($p['id']) {
                    $stmt = $conn->prepare("UPDATE user_farm_plots SET seed_code = ?, planted_at = NOW(), harvest_time = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = ?");
                    $stmt->bind_param("sii", $seedCode, $growTime, $p['id']);
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare("INSERT INTO user_farm_plots (user_id, plot_index, seed_code, planted_at, harvest_time) VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))");
                    $stmt->bind_param("iisi", $userId, $p['plot_index'], $seedCode, $growTime);
                    $stmt->execute();
                }
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => "Gieo hạt nhanh $toPlant cây!", 'planted' => $toPlant]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'fertilize_all':
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT fertilizer FROM user_commodities WHERE user_id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $inv = $stmt->get_result()->fetch_assoc();
            $availableFert = $inv ? $inv['fertilizer'] : 0;

            if ($availableFert < 1) {
                throw new Exception("Không đủ phân bón trong kho!");
            }

            $stmt = $conn->prepare("SELECT id, seed_code, harvest_time FROM user_farm_plots WHERE user_id = ? AND seed_code IS NOT NULL FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result();

            $growingPlots = [];
            while ($plot = $res->fetch_assoc()) {
                if (strtotime($plot['harvest_time']) > time()) {
                    $growingPlots[] = $plot;
                }
            }

            $toFertilize = min(count($growingPlots), $availableFert);
            if ($toFertilize == 0) {
                throw new Exception("Không có cây nào cần bón phân!");
            }

            $stmt = $conn->prepare("UPDATE user_commodities SET fertilizer = fertilizer - ? WHERE user_id = ?");
            $stmt->bind_param("ii", $toFertilize, $userId);
            $stmt->execute();

            for ($i = 0; $i < $toFertilize; $i++) {
                $p = $growingPlots[$i];
                $growTime = $seedConfigs[$p['seed_code']]['time'];
                $reduceSeconds = max(1, floor($growTime / 2));

                $stmt = $conn->prepare("UPDATE user_farm_plots SET harvest_time = DATE_SUB(harvest_time, INTERVAL ? SECOND) WHERE id = ?");
                $stmt->bind_param("ii", $reduceSeconds, $p['id']);
                $stmt->execute();
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => "Đã bón phân cho $toFertilize cây!", 'fertilized' => $toFertilize]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
}
?>
