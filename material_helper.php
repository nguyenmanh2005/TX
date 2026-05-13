<?php
if (!defined('DB_CONNECT_INCLUDED')) {
    require_once 'db_connect.php';
}

/**
 * Add or remove materials for a user
 */
function add_material(mysqli $conn, int $userId, string $materialCode, int $quantity, string $source, string $sourceRef = '') {
    if ($quantity == 0) return true;

    // Get material ID
    $stmt = $conn->prepare("SELECT id FROM materials WHERE code = ?");
    $stmt->bind_param("s", $materialCode);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows == 0) return false;
    $materialId = $res->fetch_assoc()['id'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update user_materials
        $stmt = $conn->prepare("INSERT INTO user_materials (user_id, material_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?");
        $stmt->bind_param("iiii", $userId, $materialId, $quantity, $quantity);
        $stmt->execute();

        // Check if quantity is negative (not allowed)
        $stmt = $conn->prepare("SELECT quantity FROM user_materials WHERE user_id = ? AND material_id = ?");
        $stmt->bind_param("ii", $userId, $materialId);
        $stmt->execute();
        $currentQty = $stmt->get_result()->fetch_assoc()['quantity'];
        
        if ($currentQty < 0) {
            $conn->rollback();
            return false;
        }

        // Log transaction
        $stmt = $conn->prepare("INSERT INTO material_transactions (user_id, material_id, quantity, source, source_ref) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiss", $userId, $materialId, $quantity, $source, $sourceRef);
        $stmt->execute();

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

/**
 * Get user material inventory
 */
function get_user_materials(mysqli $conn, int $userId) {
    $sql = "SELECT m.*, um.quantity 
            FROM materials m 
            JOIN user_materials um ON m.id = um.material_id 
            WHERE um.user_id = ? AND um.quantity > 0
            ORDER BY m.rarity DESC, m.family ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get current win streak for a user
 */
function get_current_win_streak(mysqli $conn, int $userId) {
    $sql = "SELECT is_win FROM game_history WHERE user_id = ? ORDER BY played_at DESC LIMIT 20";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $streak = 0;
    while ($row = $res->fetch_assoc()) {
        if ($row['is_win']) $streak++;
        else break;
    }
    return $streak;
}

/**
 * Get diminishing return multiplier based on daily collection
 */
function get_daily_penalty_multiplier(mysqli $conn, int $userId, string $rarity) {
    if ($rarity === 'none' || $rarity === 'common') return 1.0;

    $today = date('Y-m-d');
    $col = $rarity . '_count';
    
    $stmt = $conn->prepare("SELECT $col FROM material_daily_caps WHERE user_id = ? AND date = ?");
    $stmt->bind_param("is", $userId, $today);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $count = $row ? $row[$col] : 0;
    
    // Diminishing returns logic
    if ($rarity === 'uncommon') {
        if ($count >= 50) return 0.2;
        if ($count >= 20) return 0.5;
    } else if ($rarity === 'rare') {
        if ($count >= 10) return 0.1; // Giảm 90%
        if ($count >= 3) return 0.5;  // Giảm 50%
    } else if ($rarity === 'epic' || $rarity === 'legendary') {
        if ($count >= 2) return 0.05; // Giảm 95%
        if ($count >= 1) return 0.2;  // Giảm 80%
    }
    
    return 1.0;
}

/**
 * Increment daily material count (no longer blocks)
 */
function increment_daily_count(mysqli $conn, int $userId, string $rarity) {
    if ($rarity === 'none') return;
    $today = date('Y-m-d');
    $col = $rarity . '_count';
    $conn->query("INSERT INTO material_daily_caps (user_id, date, $col) VALUES ($userId, '$today', 1) 
                  ON DUPLICATE KEY UPDATE $col = $col + 1");
}

/**
 * Roll for material drop after a game
 */
function roll_material_drop(mysqli $conn, int $userId, string $gameType, string $outcome, float $bet = 0, float $winAmount = 0, int $streak = 0) {
    // 1. Basic drop rates (Base)
    $rates = [
        'lose' => [150, 20, 0, 0, 0],
        'win' => [350, 80, 10, 0, 0],
        'streak' => [500, 150, 50, 5, 0],
        'big_bet' => [600, 200, 80, 10, 0],
        'jackpot' => [1000, 400, 150, 30, 1]
    ];

    $type = 'lose';
    if ($winAmount >= 5000000) $type = 'jackpot';
    else if ($bet >= 500000) $type = 'big_bet';
    else if ($streak >= 3) $type = 'streak';
    else if ($winAmount > 0) $type = 'win';

    $currentRates = $rates[$type];

    // Get Penalty Multipliers for each rarity
    $mUncommon = get_daily_penalty_multiplier($conn, $userId, 'uncommon');
    $mRare = get_daily_penalty_multiplier($conn, $userId, 'rare');
    $mEpic = get_daily_penalty_multiplier($conn, $userId, 'epic');
    $mLegendary = get_daily_penalty_multiplier($conn, $userId, 'legendary');

    // Pity check
    $stmt = $conn->prepare("SELECT * FROM material_pity WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $pity = $stmt->get_result()->fetch_assoc();
    if (!$pity) {
        $conn->query("INSERT INTO material_pity (user_id) VALUES ($userId)");
        $pity = ['rare_counter' => 0, 'epic_counter' => 0, 'legendary_counter' => 0];
    }

    $roll = rand(1, 1000);
    $rarity = 'none';

    // Priority 1: Check Pity triggers (Pity bypasses diminishing returns for fairness)
    if ($pity['legendary_counter'] >= 500) $rarity = 'legendary';
    else if ($pity['epic_counter'] >= 200) $rarity = 'epic';
    else if ($pity['rare_counter'] >= 50) $rarity = 'rare';
    else {
        // Normal rolls with Diminishing Returns applied
        if ($roll <= ($currentRates[4] * $mLegendary)) $rarity = 'legendary';
        else if ($roll <= ($currentRates[3] * $mEpic)) $rarity = 'epic';
        else if ($roll <= ($currentRates[2] * $mRare)) $rarity = 'rare';
        else if ($roll <= ($currentRates[1] * $mUncommon)) $rarity = 'uncommon';
        else if ($roll <= $currentRates[0]) $rarity = 'common';
    }

    // Increment daily count for whatever dropped
    increment_daily_count($conn, $userId, $rarity);

    // Update Pity Counters (Corrected Logic)
    $updates = [];
    if ($rarity === 'legendary') $updates[] = "legendary_counter = 0";
    else $updates[] = "legendary_counter = legendary_counter + 1";
    
    if ($rarity === 'epic') $updates[] = "epic_counter = 0";
    else $updates[] = "epic_counter = epic_counter + 1";
    
    if ($rarity === 'rare') $updates[] = "rare_counter = 0";
    else $updates[] = "rare_counter = rare_counter + 1";

    $updateStr = implode(", ", $updates);
    $conn->query("UPDATE material_pity SET $updateStr WHERE user_id = $userId");

    if ($rarity === 'none') return null;

    // Pick material
    $stmt = $conn->prepare("SELECT code, icon, name FROM materials WHERE rarity = ? AND family = 'energy' AND is_active = 1 ORDER BY RAND() LIMIT 1");
    $stmt->bind_param("s", $rarity);
    $stmt->execute();
    $mat = $stmt->get_result()->fetch_assoc();

    if ($mat) {
        $qty = ($rarity === 'common') ? rand(1, 3) : 1;
        add_material($conn, $userId, $mat['code'], $qty, 'game_drop', $gameType);
        return ['code' => $mat['code'], 'name' => $mat['name'], 'icon' => $mat['icon'], 'rarity' => $rarity, 'quantity' => $qty];
    }

    return null;
}
?>
