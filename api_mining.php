<?php
session_start();
include 'db_connect.php';
require_once 'material_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

$userId = $_SESSION['Iduser'];
$action = $_GET['action'] ?? '';

const MAX_LEVEL = 50;
const TOTAL_SLOTS = 5;

// Configs
$minerConfig = [
    1 => ['name' => 'Thợ Mỏ Gỗ', 'cost' => 100000, 'rate' => 1000],
    2 => ['name' => 'Thợ Mỏ Vàng', 'cost' => 1000000, 'rate' => 15000],
    3 => ['name' => 'Thợ Mỏ Kim Cương', 'cost' => 10000000, 'rate' => 200000]
];
for ($i = 4; $i <= MAX_LEVEL; $i++) {
    $minerConfig[$i] = [
        'name' => 'Thợ Mỏ Cấp ' . $i . ($i == 50 ? ' (Tối Thượng)' : ''),
        'cost' => floor($minerConfig[$i - 1]['cost'] * 1.5),
        'rate' => floor($minerConfig[$i - 1]['rate'] * 1.45)
    ];
}

$storageConfig = [
    1 => ['hours' => 24, 'cost' => 0],
    2 => ['hours' => 48, 'cost' => 50000000],      // 50M
    3 => ['hours' => 72, 'cost' => 200000000],     // 200M
    4 => ['hours' => 168, 'cost' => 1000000000]    // 1B (1 week)
];

const BOOST_COST = 10000000; // 10M
const BOOST_HOURS = 12;

function getMaterialsForLevel($level) {
    if ($level <= 10) return ['stone'];
    if ($level <= 20) return ['stone', 'iron'];
    if ($level <= 35) return ['iron', 'gold'];
    return ['gold', 'diamond'];
}

function getUserUpgrades($conn, $userId) {
    $stmt = $conn->query("SELECT storage_level, boost_expires_at FROM user_mine_upgrades WHERE user_id = $userId");
    if ($row = $stmt->fetch_assoc()) {
        return $row;
    }
    return ['storage_level' => 1, 'boost_expires_at' => null];
}

function calculateSlotYield($lastClaimTimeStr, $ratePerHour, $maxHours, $boostExpiresAtStr) {
    $now = new DateTime();
    $lastClaim = new DateTime($lastClaimTimeStr);
    $diffSeconds = max(0, $now->getTimestamp() - $lastClaim->getTimestamp());
    
    $maxSeconds = $maxHours * 3600;
    if ($diffSeconds > $maxSeconds) {
        $diffSeconds = $maxSeconds;
    }

    // Tính toán thời gian Boost được áp dụng trong khoảng thời gian AFK
    $boostSeconds = 0;
    if ($boostExpiresAtStr) {
        $boostExp = new DateTime($boostExpiresAtStr);
        if ($boostExp > $lastClaim) {
            // End of effective boost is either NOW or BoostExpires (whichever is earlier)
            $endBoost = min($now->getTimestamp(), $boostExp->getTimestamp());
            // But it cannot exceed the Max Capacity time
            $endCapacity = $lastClaim->getTimestamp() + $maxSeconds;
            $endEffective = min($endBoost, $endCapacity);
            
            $boostSeconds = max(0, $endEffective - $lastClaim->getTimestamp());
        }
    }

    $normalSeconds = $diffSeconds - $boostSeconds;
    
    $ratePerSec = $ratePerHour / 3600;
    $accumulated = floor(($normalSeconds * $ratePerSec) + ($boostSeconds * $ratePerSec * 2)); // X2 for boosted
    
    return [
        'accumulated' => $accumulated,
        'capacity_percent' => min(100, round(($diffSeconds / $maxSeconds) * 100, 2)),
        'is_maxed' => ($diffSeconds >= $maxSeconds)
    ];
}

// 1. Fetch Info
if ($action === 'info') {
    $stmt = $conn->prepare("SELECT slot_index, miner_level, last_claim_time FROM user_miners WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $miners = [];
    foreach ($res as $row) {
        $miners[$row['slot_index']] = $row;
    }

    $upgrades = getUserUpgrades($conn, $userId);
    $storageLvl = (int)$upgrades['storage_level'];
    $maxHours = $storageConfig[$storageLvl]['hours'];
    $boostExpStr = $upgrades['boost_expires_at'];
    
    $slotsData = [];
    $totalAccumulated = 0;
    $totalRate = 0;
    
    for ($i = 1; $i <= TOTAL_SLOTS; $i++) {
        if (isset($miners[$i])) {
            $level = (int)$miners[$i]['miner_level'];
            $ratePerHour = $minerConfig[$level]['rate'];
            
            $yield = calculateSlotYield($miners[$i]['last_claim_time'], $ratePerHour, $maxHours, $boostExpStr);
            
            $totalAccumulated += $yield['accumulated'];
            $totalRate += $ratePerHour;
            
            $slotsData[$i] = [
                'empty' => false,
                'level' => $level,
                'name' => $minerConfig[$level]['name'],
                'rate' => $ratePerHour,
                'accumulated' => $yield['accumulated'],
                'capacity_percent' => $yield['capacity_percent']
            ];
        } else {
            $slotsData[$i] = ['empty' => true];
        }
    }
    
    $now = new DateTime();
    $guardInfo = null;
    $stmt = $conn->query("SELECT guard_type, expires_at FROM user_mine_guards WHERE user_id = $userId");
    if ($row = $stmt->fetch_assoc()) {
        $expires = new DateTime($row['expires_at']);
        if ($now < $expires) {
            $guardInfo = [
                'type' => $row['guard_type'],
                'remaining_hours' => round(($expires->getTimestamp() - $now->getTimestamp()) / 3600, 1)
            ];
        }
    }

    $boostInfo = null;
    if ($boostExpStr) {
        $bExp = new DateTime($boostExpStr);
        if ($now < $bExp) {
            $boostInfo = [
                'active' => true,
                'remaining_hours' => round(($bExp->getTimestamp() - $now->getTimestamp()) / 3600, 1)
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'slots' => $slotsData,
        'total_accumulated' => $totalAccumulated,
        'total_rate' => $totalRate,
        'guard' => $guardInfo,
        'boost' => $boostInfo,
        'storage_level' => $storageLvl,
        'storage_config' => $storageConfig,
        'config' => $minerConfig,
        'max_level' => MAX_LEVEL
    ]);
    exit;
}

// Upgrade Slot ... (Giữ nguyên logic cũ nhưng có sửa đổi time check)
if ($action === 'upgrade') {
    $slotIndex = (int)($_POST['slot'] ?? 0);
    $levelsToAdd = (int)($_POST['levels_to_add'] ?? 1);
    
    if ($slotIndex < 1 || $slotIndex > TOTAL_SLOTS || $levelsToAdd < 1) {
        echo json_encode(['success' => false, 'message' => 'Lỗi tham số!']);
        exit;
    }

    $conn->begin_transaction();
    try {
        $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmtLock->bind_param("i", $userId);
        $stmtLock->execute();
        $userRow = $stmtLock->get_result()->fetch_assoc();
        $stmtLock->close();

        $stmtMiner = $conn->prepare("SELECT miner_level, last_claim_time FROM user_miners WHERE user_id = ? AND slot_index = ? FOR UPDATE");
        $stmtMiner->bind_param("ii", $userId, $slotIndex);
        $stmtMiner->execute();
        $minerRow = $stmtMiner->get_result()->fetch_assoc();
        $stmtMiner->close();

        $currentLevel = $minerRow ? (int)$minerRow['miner_level'] : 0;
        $targetLevel = $currentLevel + $levelsToAdd;

        if ($targetLevel > MAX_LEVEL) throw new Exception("Thợ mỏ đã đạt cấp tối đa!");

        $totalCost = 0;
        for ($i = $currentLevel + 1; $i <= $targetLevel; $i++) {
            $totalCost += $minerConfig[$i]['cost'];
        }

        if ($userRow['Money'] < $totalCost) {
            throw new Exception("Không đủ GTLM để nâng cấp! Cần " . number_format($totalCost));
        }

        $conn->query("UPDATE users SET Money = Money - $totalCost WHERE Iduser = $userId");

        if ($currentLevel === 0) {
            $stmt = $conn->prepare("INSERT INTO user_miners (user_id, slot_index, miner_level, last_claim_time) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iii", $userId, $slotIndex, $targetLevel);
            $stmt->execute();
        } else {
            // Tự động claim slot này trước khi nâng cấp
            $upgrades = getUserUpgrades($conn, $userId);
            $maxHours = $storageConfig[$upgrades['storage_level']]['hours'];
            $yield = calculateSlotYield($minerRow['last_claim_time'], $minerConfig[$currentLevel]['rate'], $maxHours, $upgrades['boost_expires_at']);
            
            if ($yield['accumulated'] > 0) {
                $conn->query("UPDATE users SET Money = Money + " . $yield['accumulated'] . " WHERE Iduser = $userId");
            }

            $stmt = $conn->prepare("UPDATE user_miners SET miner_level = ?, last_claim_time = NOW() WHERE user_id = ? AND slot_index = ?");
            $stmt->bind_param("iii", $targetLevel, $userId, $slotIndex);
            $stmt->execute();
        }
        $conn->commit();
        $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
        echo json_encode(['success' => true, 'message' => "Nâng cấp lên Cấp $targetLevel thành công!", 'new_money' => number_format($newMoney)]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Claim All
if ($action === 'claim_all') {
    $conn->begin_transaction();
    try {
        $stmtLock = $conn->prepare("SELECT slot_index, miner_level, last_claim_time FROM user_miners WHERE user_id = ? FOR UPDATE");
        $stmtLock->bind_param("i", $userId);
        $stmtLock->execute();
        $miners = $stmtLock->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtLock->close();

        if (empty($miners)) throw new Exception("Bạn chưa có thợ mỏ!");

        $upgrades = getUserUpgrades($conn, $userId);
        $maxHours = $storageConfig[$upgrades['storage_level']]['hours'];
        $boostExpStr = $upgrades['boost_expires_at'];

        // Lấy thông tin Thú Cưng đang trang bị
        $stmtPet = $conn->prepare("SELECT p.buff_type, p.buff_value FROM user_pets up JOIN pets p ON up.pet_id = p.id WHERE up.user_id = ? AND up.is_active = 1");
        $stmtPet->bind_param("i", $userId);
        $stmtPet->execute();
        $activePet = $stmtPet->get_result()->fetch_assoc();
        $stmtPet->close();

        $miningYieldBuff = 1.0;
        $miningRareBuff = 1.0;
        if ($activePet) {
            if ($activePet['buff_type'] === 'mining_yield') $miningYieldBuff = (float)$activePet['buff_value'];
            if ($activePet['buff_type'] === 'mining_rare') $miningRareBuff = (float)$activePet['buff_value'];
        }

        $totalAccumulated = 0;
        $droppedMaterials = [];
        $now = new DateTime();

        foreach ($miners as $m) {
            $level = (int)$m['miner_level'];
            $ratePerHour = $minerConfig[$level]['rate'];
            
            $yield = calculateSlotYield($m['last_claim_time'], $ratePerHour, $maxHours, $boostExpStr);
            if ($yield['accumulated'] < ($ratePerHour/60)) continue; // Skip if too little (under 1 min)
            
            $totalAccumulated += ($yield['accumulated'] * $miningYieldBuff);

            // Drops calculations based on normal duration
            $lastClaim = new DateTime($m['last_claim_time']);
            $diffSecs = min($maxHours * 3600, $now->getTimestamp() - $lastClaim->getTimestamp());
            $hoursAFK = $diffSecs / 3600;
            
            $chance = min(95, 5 + ($level * 1.5)); 
            $chance = $chance * $miningRareBuff; // Áp dụng Buff Cáo Tinh Ranh
            $matPool = getMaterialsForLevel($level);
            
            for ($h = 0; $h < floor($hoursAFK); $h++) {
                if (rand(1, 100) <= $chance) {
                    $matIdx = array_rand($matPool);
                    $matCode = $matPool[$matIdx];
                    if (!isset($droppedMaterials[$matCode])) $droppedMaterials[$matCode] = 0;
                    $droppedMaterials[$matCode]++;
                }
            }
        }

        if ($totalAccumulated <= 0) throw new Exception("Chưa có đủ số dư để thu hoạch!");

        $conn->query("UPDATE users SET Money = Money + $totalAccumulated WHERE Iduser = $userId");
        $conn->query("UPDATE user_miners SET last_claim_time = NOW() WHERE user_id = $userId");

        // Đảm bảo có túi đồ chứng khoán
        $stmt_comm = $conn->prepare("INSERT IGNORE INTO user_commodities (user_id) VALUES (?)");
        $stmt_comm->bind_param("i", $userId);
        $stmt_comm->execute();

        $dropMsg = "";
        if (!empty($droppedMaterials)) {
            foreach ($droppedMaterials as $code => $qty) {
                // Update user_commodities
                $dbCol = strtolower($code);
                $stmt = $conn->prepare("UPDATE user_commodities SET $dbCol = $dbCol + ? WHERE user_id = ?");
                $stmt->bind_param("ii", $qty, $userId);
                $stmt->execute();
                
                $mName = strtoupper($code);
                $dropMsg .= "<br>🎁 Nhận $qty x Quặng $mName";
            }
        }

        $conn->commit();
        $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
        echo json_encode([
            'success' => true,
            'message' => 'Thu hoạch thành công +' . number_format($totalAccumulated) . ' GTLM!' . $dropMsg,
            'claimed' => $totalAccumulated,
            'new_money' => number_format($newMoney)
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Mua Nâng Cấp Kho
if ($action === 'buy_storage') {
    $conn->begin_transaction();
    try {
        $upgrades = getUserUpgrades($conn, $userId);
        $currentLvl = (int)$upgrades['storage_level'];
        $nextLvl = $currentLvl + 1;
        
        if (!isset($storageConfig[$nextLvl])) throw new Exception("Kho của bạn đã ở cấp Tối Đa!");
        
        $cost = $storageConfig[$nextLvl]['cost'];
        
        $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmtLock->bind_param("i", $userId);
        $stmtLock->execute();
        $userRow = $stmtLock->get_result()->fetch_assoc();
        
        if ($userRow['Money'] < $cost) throw new Exception("Bạn cần " . number_format($cost) . " GTLM để nâng cấp kho!");
        
        $conn->query("UPDATE users SET Money = Money - $cost WHERE Iduser = $userId");
        
        $stmt = $conn->prepare("INSERT INTO user_mine_upgrades (user_id, storage_level) VALUES (?, ?) ON DUPLICATE KEY UPDATE storage_level = ?");
        $stmt->bind_param("iii", $userId, $nextLvl, $nextLvl);
        $stmt->execute();
        
        $conn->commit();
        $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
        echo json_encode(['success' => true, 'message' => 'Nâng cấp Kho thành công! Sức chứa mới: ' . $storageConfig[$nextLvl]['hours'] . ' Giờ.', 'new_money' => number_format($newMoney)]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Mua Boost
if ($action === 'buy_boost') {
    $conn->begin_transaction();
    try {
        $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmtLock->bind_param("i", $userId);
        $stmtLock->execute();
        $userRow = $stmtLock->get_result()->fetch_assoc();
        
        if ($userRow['Money'] < BOOST_COST) throw new Exception("Bạn cần " . number_format(BOOST_COST) . " GTLM để mua Nước Tăng Lực!");
        
        $conn->query("UPDATE users SET Money = Money - " . BOOST_COST . " WHERE Iduser = $userId");
        
        $expires = (new DateTime())->modify('+' . BOOST_HOURS . ' hours')->format('Y-m-d H:i:s');
        
        $stmt = $conn->prepare("INSERT INTO user_mine_upgrades (user_id, boost_expires_at) VALUES (?, ?) ON DUPLICATE KEY UPDATE boost_expires_at = ?");
        $stmt->bind_param("iss", $userId, $expires, $expires);
        $stmt->execute();
        
        $conn->commit();
        $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
        echo json_encode(['success' => true, 'message' => 'Đã kích hoạt X2 Tốc Độ Đào trong ' . BOOST_HOURS . ' Giờ!', 'new_money' => number_format($newMoney)]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
