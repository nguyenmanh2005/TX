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

// --- Logic Cập nhật Biến động giá Sàn ---
function updateMarketPrices($conn) {
    // Lock table to prevent race conditions during update
    $conn->query("LOCK TABLES market_commodities WRITE");
    
    $res = $conn->query("SELECT *, UNIX_TIMESTAMP() - UNIX_TIMESTAMP(last_updated) AS seconds_since_update FROM market_commodities");
    $commodities = $res->fetch_all(MYSQLI_ASSOC);
    
    $needsUpdate = false;
    if (count($commodities) > 0) {
        if ($commodities[0]['seconds_since_update'] > 20) { // Mỗi 20 giây update giá 1 lần
            $needsUpdate = true;
        }
    }

    if ($needsUpdate) {
        foreach ($commodities as $c) {
            $history = $c['history_prices'] ? json_decode($c['history_prices'], true) : [];
            $history[] = (float)$c['current_price'];
            if (count($history) > 24) array_shift($history); // Lưu 24 mốc gần nhất
            
            // Dao động từ -15% đến +15%
            $fluctuation = rand(-15, 15) / 100;
            
            // Nếu rớt giá quá sâu (dưới 20% base), buff tỉ lệ tăng
            if ($c['current_price'] < $c['base_price'] * 0.2) {
                $fluctuation = rand(5, 30) / 100;
            }
            // Nếu giá cao quá (trên 300% base), nerf tỉ lệ giảm
            else if ($c['current_price'] > $c['base_price'] * 3) {
                $fluctuation = rand(-30, -5) / 100;
            }

            $newPrice = round($c['current_price'] * (1 + $fluctuation));
            // Không bao giờ để giá rớt xuống 0
            if ($newPrice < 10) $newPrice = 10;

            $historyJson = json_encode($history);
            $conn->query("UPDATE market_commodities SET current_price = $newPrice, history_prices = '$historyJson', last_updated = NOW() WHERE id = {$c['id']}");
        }
    }
    
    $conn->query("UNLOCK TABLES");
}

switch ($action) {
    case 'info':
        updateMarketPrices($conn);
        
        $marketRes = $conn->query("SELECT * FROM market_commodities")->fetch_all(MYSQLI_ASSOC);
        
        // Đảm bảo user có túi đồ quặng
        $stmt = $conn->prepare("INSERT IGNORE INTO user_commodities (user_id) VALUES (?)");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        $userRes = $conn->query("SELECT * FROM user_commodities WHERE user_id = $userId")->fetch_assoc();
        
        // Trả về cả dữ liệu market và inventory
        echo json_encode(['success' => true, 'market' => $marketRes, 'inventory' => $userRes]);
        break;

    case 'trade':
        $type = $_POST['type'] ?? ''; // 'buy' or 'sell'
        $commodityCode = $_POST['code'] ?? '';
        $amount = (int)($_POST['amount'] ?? 0);

        if ($amount <= 0 || !in_array($type, ['buy', 'sell'])) {
            echo json_encode(['success' => false, 'message' => 'Lệnh giao dịch không hợp lệ']);
            exit;
        }

        $conn->begin_transaction();
        try {
            updateMarketPrices($conn); // Cập nhật giá lỡ chưa cập nhật
            
            // Lock market row
            $stmt = $conn->prepare("SELECT * FROM market_commodities WHERE commodity_code = ? FOR UPDATE");
            $stmt->bind_param("s", $commodityCode);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();

            if (!$item) throw new Exception("Mã cổ phiếu/quặng không tồn tại");

            $pricePerUnit = $item['current_price'];
            $totalCost = $pricePerUnit * $amount;

            // Lấy thông tin Thú Cưng đang trang bị cho buff market_price
            $stmtPet = $conn->prepare("SELECT p.buff_type, p.buff_value, p.name FROM user_pets up JOIN pets p ON up.pet_id = p.id WHERE up.user_id = ? AND up.is_active = 1");
            $stmtPet->bind_param("i", $userId);
            $stmtPet->execute();
            $activePet = $stmtPet->get_result()->fetch_assoc();
            $stmtPet->close();

            $marketPriceBuff = 1.0;
            $petName = '';
            if ($activePet && $activePet['buff_type'] === 'market_price') {
                $marketPriceBuff = (float)$activePet['buff_value'];
                $petName = $activePet['name'];
            }
            
            $dbCol = strtolower($commodityCode);
            if (in_array($commodityCode, ['WHEAT', 'CORN', 'TOMATO', 'APPLE', 'WATERMELON', 'STRAWBERRY', 'GRAPE', 'PEACH', 'CHERRY', 'LEMON', 'BANANA', 'KIWI', 'MANGO', 'PINEAPPLE', 'COCONUT', 'MELON', 'ORANGE', 'AVOCADO', 'PEAR', 'POMEGRANATE'])) {
                $dbCol = 'crop_' . $dbCol;
            }
            
            // Lock User & Commodities
            $stmt = $conn->prepare("SELECT Money, Role FROM users WHERE Iduser = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            
            $stmt = $conn->prepare("SELECT * FROM user_commodities WHERE user_id = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $inventory = $stmt->get_result()->fetch_assoc();

            if ($type === 'buy') {
                if ($user['Money'] < $totalCost) {
                    throw new Exception("Không đủ GTLM để mua! Cần " . number_format($totalCost) . " GTLM.");
                }
                // Trừ GTLM, cộng quặng
                $stmt = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
                $stmt->bind_param("di", $totalCost, $userId);
                $stmt->execute();
                
                $stmt = $conn->prepare("UPDATE user_commodities SET $dbCol = $dbCol + ? WHERE user_id = ?");
                $stmt->bind_param("ii", $amount, $userId);
                $stmt->execute();
                
            } else if ($type === 'sell') {
                if ($inventory[$dbCol] < $amount) {
                    throw new Exception("Bạn không có đủ số lượng $commodityCode để bán!");
                }
                
                // Base profit + Pet buff
                $sellProfit = $totalCost * $marketPriceBuff;
                
                // VIP Role buffs (KOC/Thương Gia)
                $userRole = (int)($user['Role'] ?? 0);
                if ($userRole === 3) {
                    // Role 3 (Thương Gia) -> Bonus 10%
                    $sellProfit = $sellProfit * 1.10;
                } else if ($userRole === 2) {
                    // Role 2 (KOC) -> Bonus 5%
                    $sellProfit = $sellProfit * 1.05;
                }

                // Trừ quặng, cộng GTLM
                $stmt = $conn->prepare("UPDATE user_commodities SET $dbCol = $dbCol - ? WHERE user_id = ?");
                $stmt->bind_param("ii", $amount, $userId);
                $stmt->execute();
                
                $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
                $stmt->bind_param("di", $sellProfit, $userId);
                $stmt->execute();
            }

            $conn->commit();
            
            // Get new money
            $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $newMoney = $stmt->get_result()->fetch_assoc()['Money'];
            
            $msg = 'Giao dịch thành công!';
            if ($type === 'sell' && $marketPriceBuff > 1.0) {
                $msg = 'Bán thành công! (Áp dụng buff ' . $petName . ' x' . $marketPriceBuff . ' giá)';
            }

            echo json_encode([
                'success' => true, 
                'message' => $msg,
                'new_money' => $newMoney
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
}
?>
